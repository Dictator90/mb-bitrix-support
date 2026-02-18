<?php

namespace MB\Bitrix\Traits;

/**
 * Trait for implementation Observer pattern
 */
trait ObservableTrait
{
    /**
     * @var array<callable> observers
     */
    private array $observers = [];

    /**
     * Добавление наблюдателя
     */
    public function attach(callable $observer, string $event = '*'): void
    {
        $this->observers[$event][] = $observer;
    }

    /**
     * Удаление наблюдателя
     */
    public function detach(callable $observer, string $event = '*'): void
    {
        if (!isset($this->observers[$event])) {
            return;
        }

        $key = array_search($observer, $this->observers[$event], true);
        if ($key !== false) {
            unset($this->observers[$event][$key]);
        }
    }

    /**
     * Уведомление наблюдателей
     */
    public function notify(string $event = '*', mixed $data = null): void
    {
        $observers = array_merge(
            $this->observers[$event] ?? [],
            $this->observers['*'] ?? []
        );

        foreach ($observers as $observer) {
            $observer($this, $event, $data);
        }
    }
}