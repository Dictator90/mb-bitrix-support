<?php

namespace MB\Bitrix\UI\Traits;

trait HasJsEvent
{
    protected array $events = [];

    public function getJsEvents(): string
    {
        $result = '';
        foreach ($this->events as $event => $handler) {
            $result .= $event . '="' . $handler . '"';
        }
        return $result;
    }

    public function getJsEventsArray(): array
    {
        return $this->events;
    }

    public function setJsEvents(array $events): static
    {
        $this->events = $events;
        return $this;
    }

    public function addJsEvent(string $event, string $handler)
    {
        $this->events[$event] = $handler;
        return $this;
    }
}
