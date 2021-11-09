<?php

declare(strict_types=1);

namespace Plattry\Network\Connection;

/**
 * An instance of a socket connection.
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
    public function receive(?int $length = null): string;

    /**
     * Send data to output buffer.
     * @param string $data
     */
    public function send(string $data): void;

    /**
     * Close socket and destroy connection.
     */
    public function close(): void;
}
