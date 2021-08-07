<?php

declare(strict_types=1);

namespace Plattry\Network\Protocol;

use Plattry\Network\Connection\ConnectionInterface;

/**
 * Class Http
 * @package Plattry\Network\Protocol
 */
class Http implements ProtocolInterface
{
    /**
     * @inheritDoc
     */
    public function check(ConnectionInterface $connection): int
    {
        $input = $connection->receive();

        $crlf = strpos($input, "\r\n\r\n");
        if (false === $crlf) {
            if (strlen($input) >= 16384) {
                $connection->send("HTTP/1.1 413 Request Entity Too Large\r\n\r\n");
                $connection->close();
            }

            return 0;
        }

        $method = strstr($input, ' ', true);

        if (
            'GET' === $method || 'HEAD' === $method ||
            'DELETE' === $method || 'OPTIONS' === $method || 'TRACE' === $method
        ) {
            return $crlf + 4;
        }

        if ('POST' !== $method && 'PUT' !== $method && 'PATCH' !== $method) {
            $connection->send("HTTP/1.1 400 Bad Request\r\n\r\n");
            $connection->close();
            return 0;
        }

        $header = substr($input, 0, $crlf);
        preg_match("/\r\ncontent-length: ?(\d+)/i", $header, $match);
        if (!isset($match[1]) || !is_numeric($match[1])) {
            $connection->send("HTTP/1.1 400 Bad Request\r\n\r\n");
            $connection->close();
            return 0;
        }

        return $crlf + 4 + (int)$match[1];
    }
}
