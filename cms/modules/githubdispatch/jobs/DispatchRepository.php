<?php

declare(strict_types=1);

namespace modules\githubdispatch\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;
use Throwable;
use UnexpectedValueException;
use yii\queue\RetryableJobInterface;

final class DispatchFailed extends RuntimeException
{
    public function __construct(
        public readonly bool $retryable,
        string $message,
    ) {
        // Deliberately omit the original Guzzle exception: it retains the PSR-7
        // request and therefore the Authorization header.
        parent::__construct($message);
    }
}

final class DispatchRepository extends BaseJob implements RetryableJobInterface
{
    private const DEFAULT_REPOSITORY = 'crokstudio/val-dieu';
    private const LOG_CATEGORY = 'github-dispatch';

    public int $entryId = 0;
    public ?string $entryUid = null;
    public int $siteId = 0;
    public string $trigger = 'save';

    public function execute($queue): void
    {
        $token = App::env('GITHUB_DISPATCH_TOKEN');
        $repository = App::env('GITHUB_DISPATCH_REPOSITORY') ?: self::DEFAULT_REPOSITORY;

        if (!is_string($token) || trim($token) === '') {
            throw new RuntimeException('GITHUB_DISPATCH_TOKEN is missing.');
        }

        if (
            !is_string($repository) ||
            !preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository)
        ) {
            throw new RuntimeException('GITHUB_DISPATCH_REPOSITORY is invalid.');
        }

        [$owner, $repo] = explode('/', $repository, 2);

        try {
            $response = Craft::createGuzzleClient([
                'base_uri' => 'https://api.github.com',
                'connect_timeout' => 5,
                'timeout' => 15,
                'allow_redirects' => false,
                'http_errors' => true,
            ])->post(sprintf(
                '/repos/%s/%s/dispatches',
                rawurlencode($owner),
                rawurlencode($repo),
            ), [
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'Authorization' => sprintf('Bearer %s', trim($token)),
                    'User-Agent' => 'val-dieu-craft-dispatch',
                    'X-GitHub-Api-Version' => '2026-03-10',
                ],
                'json' => [
                    'event_type' => 'craft-content-saved',
                    'client_payload' => [
                        'entry_id' => $this->entryId,
                        'entry_uid' => $this->entryUid,
                        'site_id' => $this->siteId,
                        'trigger' => $this->trigger,
                    ],
                ],
            ]);

            if ($response->getStatusCode() !== 204) {
                throw new UnexpectedValueException(sprintf(
                    'Unexpected GitHub response: HTTP %d',
                    $response->getStatusCode(),
                ));
            }

            Craft::info(sprintf(
                'GitHub dispatch accepted for entry_id=%d.',
                $this->entryId,
            ), self::LOG_CATEGORY);
        } catch (Throwable $exception) {
            $status = null;
            $requestId = null;
            $retryable = false;

            if ($exception instanceof RequestException && $exception->hasResponse()) {
                $status = $exception->getResponse()->getStatusCode();
                $requestId = $exception->getResponse()->getHeaderLine('X-GitHub-Request-Id');
                $retryable = $status === 408 || $status === 429 || $status >= 500;
            } elseif ($exception instanceof GuzzleException) {
                $retryable = true;
            }

            Craft::error(sprintf(
                'GitHub dispatch failed: status=%s request_id=%s entry_id=%d exception=%s',
                $status ?? 'no-response',
                $requestId ?: '-',
                $this->entryId,
                $exception::class,
            ), self::LOG_CATEGORY);

            throw new DispatchFailed($retryable, sprintf(
                'GitHub dispatch failed: status=%s request_id=%s entry_id=%d',
                $status ?? 'no-response',
                $requestId ?: '-',
                $this->entryId,
            ));
        }
    }

    public function getTtr(): int
    {
        return 30;
    }

    public function canRetry($attempt, $error): bool
    {
        if ((int)$attempt >= 3) {
            return false;
        }

        // Yii invokes this again with a null error when it reserves a delayed
        // retry from the database; the original decision was made below.
        return $error === null || $error instanceof DispatchFailed && $error->retryable;
    }

    protected function defaultDescription(): ?string
    {
        return 'Trigger the Val-Dieu GitHub rebuild';
    }
}
