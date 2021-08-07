<?php

declare(strict_types=1);

namespace Plattry\Network\Protocol;

use Plattry\Network\Connection\ConnectionInterface;

/**
 * Interface ProtocolInterface
 * @package Plattry\Network\Protocol
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
