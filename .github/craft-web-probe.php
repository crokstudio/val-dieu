<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');
ob_start();

$publicKeyPath = __DIR__ . '/' . pathinfo(__FILE__, PATHINFO_FILENAME) . '.pem';

try {
    $craftRoot = null;
    foreach ([dirname(__DIR__), dirname(__DIR__, 2) . '/craft'] as $candidate) {
        if (
            is_file($candidate . '/.val-dieu-craft-root') &&
            trim((string)file_get_contents($candidate . '/.val-dieu-craft-root')) === 'val-dieu-craft-root-v1' &&
            is_file($candidate . '/bootstrap.php') &&
            is_file($candidate . '/vendor/autoload.php')
        ) {
            $craftRoot = $candidate;
            break;
        }
    }
    if ($craftRoot === null) {
        throw new RuntimeException('The Craft application root could not be located.');
    }

    require $craftRoot . '/bootstrap.php';
    $app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/web.php';
    $module = $app->getModule('github-dispatch');
    if ($module === null) {
        throw new RuntimeException('The GitHub dispatch module is unavailable in the web bootstrap.');
    }
    $module->getControllerPath();
    $app->run();
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
