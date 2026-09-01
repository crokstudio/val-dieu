<?php
/**
 * Yii Application Config
 *
 * Edit this file at your own risk!
 *
 * The array returned by this file will get merged with
 * vendor/craftcms/cms/src/config/app.php and app.[web|console].php, when
 * Craft's bootstrap script is defining the configuration for the entire
 * application.
 *
 * You can define custom modules and system components, and even override the
 * built-in system components.
 *
 * If you want to modify the application config for *only* web requests or
 * *only* console requests, create an app.web.php or app.console.php file in
 * your config/ folder, alongside this one.
 *
 * Read more about application configuration:
 * @link https://craftcms.com/docs/5.x/reference/config/app.html
 */

use craft\helpers\App;
use craft\helpers\MailerHelper;
use craft\mail\transportadapters\Smtp;

// Keep this integration independent from Composer so production can deploy it
// without changing the remotely-installed dependency set.
$moduleRoot = dirname(__DIR__) . '/modules';
$releasesRoot = $moduleRoot . '/githubdispatch-releases';
$currentRelease = $moduleRoot . '/githubdispatch-current';
clearstatcache(true, $currentRelease);
if (is_link($currentRelease)) {
    $resolvedReleasesRoot = realpath($releasesRoot);
    $githubDispatchRoot = realpath($currentRelease);
    if (
        !is_string($resolvedReleasesRoot) ||
        !is_string($githubDispatchRoot) ||
        !is_dir($resolvedReleasesRoot) ||
        !is_dir($githubDispatchRoot) ||
        !preg_match(
            '~\\A' . preg_quote($resolvedReleasesRoot, '~') . '/[0-9a-f]{40}\\z~',
            $githubDispatchRoot,
        )
    ) {
        throw new RuntimeException('The GitHub dispatch release link is invalid.');
    }
} else {
    $githubDispatchRoot = $moduleRoot . '/githubdispatch';
}
Craft::setAlias('@modules/githubdispatch', $githubDispatchRoot);
require_once $githubDispatchRoot . '/jobs/DispatchRepository.php';
require_once $githubDispatchRoot . '/Module.php';

$mailerComponent = static function(): object {
    $config = App::mailerConfig();
    $smtpHost = App::env('VALDIEU_SMTP_HOST');

    // Retain the stored Craft transport until the complete SMTP configuration
    // has been installed on the server.
    if (!is_string($smtpHost) || trim($smtpHost) === '') {
        return Craft::createObject($config);
    }

    $requiredVariables = [
        'VALDIEU_SMTP_USERNAME',
        'VALDIEU_SMTP_PASSWORD',
        'VALDIEU_SMTP_FROM_EMAIL',
    ];
    foreach ($requiredVariables as $requiredVariable) {
        $value = App::env($requiredVariable);
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException("{$requiredVariable} is missing.");
        }
    }

    $adapter = MailerHelper::createTransportAdapter(Smtp::class, [
        'host' => $smtpHost,
        'port' => App::env('VALDIEU_SMTP_PORT') ?: 587,
        'useAuthentication' => true,
        'username' => App::env('VALDIEU_SMTP_USERNAME'),
        'password' => App::env('VALDIEU_SMTP_PASSWORD'),
    ]);

    $fromEmail = trim((string) App::env('VALDIEU_SMTP_FROM_EMAIL'));
    $fromName = trim((string) (App::env('VALDIEU_SMTP_FROM_NAME') ?: 'Val-Dieu'));
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('VALDIEU_SMTP_FROM_EMAIL is invalid.');
    }

    $config['from'] = [$fromEmail => $fromName];
    $config['replyTo'] = $fromEmail;
    $config['transport'] = $adapter->defineTransport();

    return Craft::createObject($config);
};

return [
    'id' => App::env('CRAFT_APP_ID') ?: 'CraftCMS',
    'modules' => [
        'github-dispatch' => \modules\githubdispatch\Module::class,
    ],
    'bootstrap' => ['github-dispatch'],
    'components' => [
        'mailer' => $mailerComponent,
    ],
];
