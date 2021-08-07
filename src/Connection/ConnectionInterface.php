<?php

declare(strict_types=1);

namespace Plattry\Network\Connection;

/**
 * Interface ConnectionInterface
 * @package Plattry\Network\Connection
 */
interface ConnectionInterface
{
    /**
     * Get connection attribute.
     * @return array
     */
    public function getAttribute(): array;

    /**
     * Receive data from input buffer.
     * @param int|null $length
     * @return string
     */
    public function receive(int|null $length = null): string;

    /**
     * Send data to output buffer.
     * @param string $data
     * @return void
     */
    public function send(string $data): void;

    /**
     * Close connection.
     * @return void
     */
    public function close(): void;
}
