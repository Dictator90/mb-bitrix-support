<?php

namespace MB\Bitrix\Traits;

use Bitrix\Main\Event;
use Bitrix\Main\EventResult;

trait BitrixEventsObservableTrait
{
    private array $observers = [];

    protected function attach(string $moduleId, string $event): void
    {
        $this->observers[$event][] = new Event($moduleId, $event);
    }

    /**
     * Remove Bitrix Events
     */
    protected function detach(string $event): void
    {
        if (!isset($this->observers[$event])) {
            return;
        }

        unset($this->observers[$event]);
    }

    /**
     * Sending Bitrix Events
     *
     * <code>
     *     $this->notify(
     *         'onAfterSendResult',
     *         ['entity' => $this],
     *         function(EventResult[] $results) {
     *             foreach ($results as $result) {
     *              if ($result->getType() === EventResult::SUCCESS) {
     *                  $this->processSuccess($result->getParameters());
     *              }
     *          }
     *         })
     * </code>
     *
     * @see EventResult
     */
    protected function notify(string $event, callable|array|null $parameters, callable|null $resultCallback = null): void
    {
        $observers = $this->observers[$event] ?? [];

        /** @var Event $observer */
        foreach ($observers as $observer) {
            if ($parameters) {
                $observer->setParameters($parameters);
            }
            $observer->send();
            if ($resultCallback) {
                $resultCallback($observer->getResults());
            }
        }
    }

    /**
     * Send event and return only successful results
     *
     * @param string $event Event name
     * @param array $parameters Event parameters
     * @return EventResult[] Successful results only
     */
    protected function notifySuccessOnly(string $event, array $parameters = []): array
    {
        $result = [];

        $this->notify($event, $parameters, function($results) use (&$result) {
            /** @var EventResult $res */
            foreach ($results as $res) {
                if ($res->getType() === EventResult::SUCCESS) {
                    $result[] = $res;
                }
            }
        });

        return $result;
    }

    /**
     * Send event and check if any handler returned error
     *
     * @param string $event Event name
     * @param array $parameters Event parameters
     * @return bool True if any handler returned error
     */
    protected function notifyHasErrors(string $event, array $parameters = []): bool
    {
        $result = false;

        $this->notify($event, $parameters, function($results) use (&$result) {
            foreach ($results as $res) {
                if ($res->getType() === EventResult::ERROR) {
                    $result = true;
                    break;
                }
            }
        });

        return $result;
    }
}