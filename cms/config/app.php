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
require_once $githubDispatchRoot . '/jobs/DispatchRepository.php';
require_once $githubDispatchRoot . '/Module.php';

return [
    'id' => App::env('CRAFT_APP_ID') ?: 'CraftCMS',
    'modules' => [
        'github-dispatch' => \modules\githubdispatch\Module::class,
    ],
    'bootstrap' => ['github-dispatch'],
];
