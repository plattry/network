<?php

declare(strict_types = 1);

namespace Plattry\Network\Event;

use Plattry\Dispatcher\StoppableEvent;
use Plattry\Network\Connection\ConnectionInterface;

/**
 * Triggered when the connection is closed.
 */
class CloseEvent extends StoppableEvent
{
    /**
     * @var ConnectionInterface
     */
    private ConnectionInterface $connection;

    /**
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }
}
