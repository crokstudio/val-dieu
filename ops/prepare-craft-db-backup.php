<?php

declare(strict_types=1);

$tempDir = getenv('VALDIEU_DB_BACKUP_TEMP_DIR');
$fileSpecifications = [
    'VALDIEU_DB_BACKUP_CNF' => 'database.cnf',
    'VALDIEU_DB_BACKUP_DATABASE_FILE' => 'database-name.txt',
    'VALDIEU_DB_BACKUP_CHARSET_FILE' => 'database-charset.txt',
    'VALDIEU_DB_BACKUP_SINGLE_TRANSACTION_FILE' => 'single-transaction.txt',
    'VALDIEU_DB_BACKUP_IGNORED_TABLES_FILE' => 'ignored-tables.nul',
];

if (!is_string($tempDir) || !preg_match('~^/[A-Za-z0-9._/-]+$~', $tempDir)) {
    throw new RuntimeException('Invalid private database temp directory.');
}

$realTempDir = realpath($tempDir);
$tempPermissions = fileperms($tempDir);
if (
    !is_string($realTempDir) ||
    !is_dir($realTempDir) ||
    !is_writable($realTempDir) ||
    !is_int($tempPermissions) ||
    (($tempPermissions & 0777) !== 0700)
) {
    throw new RuntimeException('Private database temp directory is unavailable.');
}

$paths = [];
foreach ($fileSpecifications as $environmentKey => $expectedName) {
    $path = getenv($environmentKey);
    if (
        !is_string($path) ||
        !preg_match('~^/[A-Za-z0-9._/-]+$~', $path) ||
        dirname($path) !== $realTempDir ||
        basename($path) !== $expectedName ||
        file_exists($path) ||
        is_link($path)
    ) {
        throw new RuntimeException('Unsafe private database backup file path.');
    }
    $paths[$environmentKey] = $path;
}

$db = Craft::$app->getDb();
if (!$db->getIsMysql()) {
    throw new RuntimeException('The Craft database is not MySQL-compatible.');
}
if (Craft::$app->getConfig()->getGeneral()->backupCommand !== null) {
    throw new RuntimeException('A custom Craft backup command cannot be reproduced safely.');
}

$parsedDsn = \craft\helpers\Db::parseDsn($db->dsn);
$database = $parsedDsn['dbname'] ?? null;
if (!is_string($database) || !preg_match('~^[A-Za-z0-9_]+$~', $database)) {
    throw new RuntimeException('Invalid database name.');
}

$schema = $db->getSchema();
if (!$schema instanceof \craft\db\mysql\Schema) {
    throw new RuntimeException('Unsupported database backup schema.');
}

$charset = Craft::$app->getConfig()->getDb()->getCharset();
if (!is_string($charset) || !preg_match('~^[A-Za-z0-9_-]+$~', $charset)) {
    throw new RuntimeException('Invalid database charset.');
}

$lineValue = static function(mixed $value): string {
    if ($value === null) {
        return '';
    }
    if (!is_scalar($value)) {
        throw new RuntimeException('Invalid database client setting.');
    }

    $value = (string)$value;
    if (str_contains($value, "\0") || str_contains($value, "\r") || str_contains($value, "\n")) {
        throw new RuntimeException('Invalid database client setting.');
    }

    return $value;
};

$quotedOptionValue = static function(mixed $value) use ($lineValue): string {
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $lineValue($value)) . '"';
};

// Mirror Craft 5.10's private MySQL option file without relying on its cached
// sys_get_temp_dir() value.
$contents = '[client]' . PHP_EOL .
    'user=' . $quotedOptionValue($db->username) . PHP_EOL .
    'password=' . $quotedOptionValue($db->password);

if (isset($parsedDsn['unix_socket'])) {
    $contents .= PHP_EOL . 'socket=' . $quotedOptionValue($parsedDsn['unix_socket']);
} else {
    $port = $lineValue($parsedDsn['port'] ?? '');
    if ($port !== '' && (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535)) {
        throw new RuntimeException('Invalid database port.');
    }
    $contents .= PHP_EOL . 'host=' . $quotedOptionValue($parsedDsn['host'] ?? '');
    if ($port !== '') {
        $contents .= PHP_EOL . 'port=' . $port;
    }
}

$attributes = is_array($db->attributes) ? $db->attributes : [];
foreach ([
    \PDO::MYSQL_ATTR_SSL_CA => 'ssl_ca',
    \PDO::MYSQL_ATTR_SSL_CERT => 'ssl_cert',
    \PDO::MYSQL_ATTR_SSL_KEY => 'ssl_key',
] as $attribute => $option) {
    if (isset($attributes[$attribute])) {
        $contents .= PHP_EOL . $option . '=' . $quotedOptionValue($attributes[$attribute]);
    }
}

$ignoredTables = [];
foreach ($db->getIgnoredBackupTables() as $table) {
    $table = $schema->getRawTableName($table);
    if (!is_string($table) || !preg_match('~^[A-Za-z0-9_]+$~', $table)) {
        throw new RuntimeException('Invalid ignored database table.');
    }
    $ignoredTables[] = $table;
}

// Match Craft's workaround for the MySQL 8.0.32 mysqldump regression.
$serverVersion = \craft\helpers\App::normalizeVersion($db->getServerVersion());
$useSingleTransaction = version_compare($serverVersion, '8', '>=') &&
    version_compare($serverVersion, '8.0.32', '<');

$writePrivateFile = static function(string $path, string $data): void {
    $handle = fopen($path, 'xb');
    if ($handle === false) {
        throw new RuntimeException('Unable to create private database backup metadata.');
    }

    try {
        $offset = 0;
        $length = strlen($data);
        while ($offset < $length) {
            $written = fwrite($handle, substr($data, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to write private database backup metadata.');
            }
            $offset += $written;
        }
        if (!fflush($handle)) {
            throw new RuntimeException('Unable to flush private database backup metadata.');
        }
    } finally {
        fclose($handle);
    }

    if (!chmod($path, 0600)) {
        throw new RuntimeException('Unable to protect private database backup metadata.');
    }
    clearstatcache(true, $path);
    $permissions = fileperms($path);
    if (
        !is_file($path) ||
        is_link($path) ||
        !is_int($permissions) ||
        (($permissions & 0777) !== 0600)
    ) {
        throw new RuntimeException('Unsafe private database backup metadata.');
    }
};

$writePrivateFile($paths['VALDIEU_DB_BACKUP_CNF'], $contents . PHP_EOL);
$writePrivateFile($paths['VALDIEU_DB_BACKUP_DATABASE_FILE'], $database . PHP_EOL);
$writePrivateFile($paths['VALDIEU_DB_BACKUP_CHARSET_FILE'], $charset . PHP_EOL);
$writePrivateFile(
    $paths['VALDIEU_DB_BACKUP_SINGLE_TRANSACTION_FILE'],
    ($useSingleTransaction ? '1' : '0') . PHP_EOL,
);
$ignoredData = $ignoredTables === [] ? '' : implode("\0", $ignoredTables) . "\0";
$writePrivateFile($paths['VALDIEU_DB_BACKUP_IGNORED_TABLES_FILE'], $ignoredData);

return true;
