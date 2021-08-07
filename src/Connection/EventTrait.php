<?php

declare(strict_types=1);

namespace Plattry\Network\Connection;

/**
 * Trait EventTrait
 * @package Plattry\Network\Connection
 */
trait EventTrait
{
    /**
     * Event
     * @var Event|null
     */
    protected Event|null $event = null;

    /**
     * Set event to register and trigger event.
     * @param Event|null $event
     * @return void
     */
    public function setEvent(Event|null $event): void
    {
        $this->event = $event;
    }
}
