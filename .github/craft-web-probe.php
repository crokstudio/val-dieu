<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');
ob_start();

$publicKeyPath = dirname(__DIR__) . '/storage/runtime/' . pathinfo(__FILE__, PATHINFO_FILENAME) . '.pem';

try {
    require dirname(__DIR__) . '/bootstrap.php';
    $app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/web.php';
    if ($app->getModule('github-dispatch') === null) {
        throw new RuntimeException('The GitHub dispatch module is unavailable in the web bootstrap.');
    }
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    @unlink($publicKeyPath);
    @unlink(__FILE__);
    echo 'ok';
} catch (Throwable $error) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    $message = substr($error::class . ': ' . $error->getMessage(), 0, 400);
    $publicKey = @file_get_contents($publicKeyPath);
    $encrypted = '';

    if (!is_string($publicKey) || !openssl_public_encrypt($message, $encrypted, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
        @unlink($publicKeyPath);
        @unlink(__FILE__);
        http_response_code(500);
        echo 'encryption-failed';
        exit;
    }

    @unlink($publicKeyPath);
    @unlink(__FILE__);
    http_response_code(500);
    echo 'encrypted_error=' . base64_encode($encrypted);
}
