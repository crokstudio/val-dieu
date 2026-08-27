<?php

declare(strict_types=1);

namespace modules\githubdispatch;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\helpers\ElementHelper;
use craft\helpers\Queue;
use modules\githubdispatch\jobs\DispatchRepository;
use Throwable;
use yii\base\Event;
use yii\base\Module as BaseModule;

final class Module extends BaseModule
{
    private const LOG_CATEGORY = 'github-dispatch';

    private bool $dispatchQueued = false;

    public function init(): void
    {
        parent::init();

        foreach ([
            Element::EVENT_AFTER_PROPAGATE => 'save',
            Element::EVENT_AFTER_DELETE => 'delete',
            Element::EVENT_AFTER_RESTORE => 'restore',
        ] as $eventName => $trigger) {
            Event::on(
                Entry::class,
                $eventName,
                function(Event $event) use ($trigger): void {
                    $this->queueDispatch($event, $trigger);
                },
            );
        }
    }

    private function queueDispatch(Event $event, string $trigger): void
    {
        $entry = $event->sender;
        if (!$entry instanceof Entry) {
            return;
        }

        $isConsoleRequest = Craft::$app->getRequest()->getIsConsoleRequest();

        if (
            (!$isConsoleRequest && $this->dispatchQueued) ||
            ElementHelper::isDraftOrRevision($entry) ||
            $entry->propagating ||
            $entry->resaving
        ) {
            return;
        }

        // Matrix/nested entries can emit several events during one control
        // panel request. One rebuild is sufficient for the whole request.
        if (!$isConsoleRequest) {
            $this->dispatchQueued = true;
        }

        try {
            Queue::push(new DispatchRepository([
                'entryId' => (int)$entry->id,
                'entryUid' => $entry->uid,
                'siteId' => (int)$entry->siteId,
                'trigger' => $trigger,
            ]));
        } catch (Throwable $exception) {
            $this->dispatchQueued = false;
            Craft::error(sprintf(
                'Unable to queue the GitHub dispatch: exception=%s entry_id=%d',
                $exception::class,
                (int)$entry->id,
            ), self::LOG_CATEGORY);

            throw $exception;
        }
    }
}
