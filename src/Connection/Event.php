<?php

declare(strict_types=1);

namespace Plattry\Network\Connection;

use Closure;
use Plattry\Utils\Debug;
use Throwable;

/**
 * Class Event
 * @package Plattry\Network\Connection
 */
class Event
{
    /**
     * Emitted when a socket connection is successfully established
     * @var int
     */
    public const CONNECT = 1;

    /**
     * Emitted when data is received
     * @var int
     */
    public const MESSAGE = 2;

    /**
     * Emitted when a socket connection sends a FIN packet
     * @var int
     */
    public const CLOSE = 3;

    /**
     * Emitted when an error occurs with connection
     * @var int
     */
    public const ERROR = 4;

    /**
     * Action and callback
     * @var array
     */
    protected array $events = [];

    /**
     * Register an event.
     * @param integer $action
     * @param Closure $callback
     * @return void
     */
    public function register(int $action, Closure $callback): void
    {
        $this->events[$action] = $callback;
    }

    /**
     * Trigger an event.
     * @param integer $action
     * @param mixed ...$data
     * @return void
     */
    public function trigger(int $action, mixed ...$data): void
    {
        if (!isset($this->events[$action])) return;

        try {
            call_user_func_array($this->events[$action], $data);
        } catch (Throwable $t) {
            Debug::handleThrow($t);
        }
    }
}
