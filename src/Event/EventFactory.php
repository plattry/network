<?php

declare(strict_types = 1);

namespace Plattry\Network\Event;

use Plattry\Network\Connection\ConnectionInterface;

/**
 * Factory for creating connection event.
 */
class EventFactory
{
    /**
     * @param ConnectionInterface $connection
     * @return CloseEvent
     */
    public static function createCloseEvent(ConnectionInterface $connection): CloseEvent
    {
        return new CloseEvent($connection);
    }

    /**
     * @param ConnectionInterface $connection
     * @return ConnectEvent
     */
    public static function createConnectEvent(ConnectionInterface $connection): ConnectEvent
    {
        return new ConnectEvent($connection);
    }

    /**
     * @param ConnectionInterface $connection
     * @return MessageEvent
     */
    public static function createMessageEvent(ConnectionInterface $connection): MessageEvent
    {
        return new MessageEvent($connection);
    }

    /**
     * @param ConnectionInterface $connection
     * @return ErrorEvent
     */
    public static function createErrorEvent(ConnectionInterface $connection): ErrorEvent
    {
        return new ErrorEvent($connection);
    }
}
