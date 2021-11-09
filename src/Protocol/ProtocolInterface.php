<?php

declare(strict_types=1);

namespace Plattry\Network\Protocol;

use Plattry\Network\Connection\ConnectionInterface;

/**
 * Protocol instance for processing messages.
 */
interface ProtocolInterface
{
    /**
     * Check packet length.
     * @param ConnectionInterface $connection
     * @return int
     */
    public function check(ConnectionInterface $connection): int;
}
