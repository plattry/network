<?php

declare(strict_types=1);

namespace Plattry\Network;

use Plattry\Dispatcher\DispatcherAwareTrait;
use Plattry\Network\Connection\Tcp;
use Plattry\Network\Protocol\ProtocolAwareTrait;
use Plattry\Utils\Debug;
use Throwable;

/**
 * Network server for receiving and sending packets.
 */
class Server
{
    use DispatcherAwareTrait;
    use ProtocolAwareTrait;
    use SignalTrait;

    /**
     * @var string|null
     */
    protected ?string $ip = null;

    /**
     * @var int|null
     */
    protected ?int $port = null;

    /**
     * The type of transport protocol(tcp, udp)
     * @var string|null
     */
    protected ?string $transport = null;

    /**
     * Socket context
     * @var mixed|resource|null
     */
    protected mixed $context = null;

    /**
     * Socket
     * @var mixed|null
     */
    protected mixed $fd = null;

    /**
     * IO Event watcher
     */
    protected ?\EvIo $watcher = null;

    /**
     * @param string $ip
     * @param int $port
     * @param string $transport
     */
    public function __construct(string $ip, int $port, string $transport = 'tcp')
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->transport = $transport;
        $this->context = stream_context_create(['socket' => ['so_reuseport' => 1]]);
    }

    /**
     * Set options and params for socket context.
     * @param array $options
     * @param array $params
     */
    public function setContext(array $options, array $params = []): void
    {
        $options = array_merge(stream_context_get_options($this->context), $options);
        stream_context_set_option($this->context, $options);

        $params = array_merge(stream_context_get_params($this->context), $params);
        stream_context_set_params($this->context, $params);
    }

    /**
     * Bind socket context and address to socket.
     * @param string $address
     * @param mixed $context
     * @return mixed
     * @throws \RuntimeException
     */
    protected function bind(string $address, mixed $context): mixed
    {
        $flags = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;

        $fd = stream_socket_server($address, $errno, $msg, $flags, $context);

        !is_resource($fd) &&
        throw new \RuntimeException("Failed to listen $address, code: $errno, msg: $msg");

        stream_set_blocking($fd, false);

        return $fd;
    }

    /**
     * Start to listen port.
     */
    public function listen(): void
    {
        $address = sprintf("%s://%s:%d", $this->transport, $this->ip, $this->port);

        !is_resource($this->fd) &&
        $this->fd = $this->bind($address, $this->context);

        is_null($this->watcher) &&
        $this->watcher = \EvIo::createStopped($this->fd, \Ev::READ, [$this, "acceptTcp"]);

        $this->watcher->start();

        $this->installSignal();

        \Ev::run();
    }

    /**
     * Pause to listen port.
     */
    public function pause(): void
    {
        $this->watcher->stop();
    }

    /**
     * Establish tcp connection and transmit data.
     */
    protected function acceptTcp(): void
    {
        $fd = stream_socket_accept($this->fd, 0, $client);
        stream_set_blocking($fd, false);

        $splitPos = strpos($client, ':');
        $attribute = [
            'LOCAL_IP' => $this->ip,
            'LOCAL_PORT' => $this->port,
            'REMOTE_IP' => substr($client, 0, $splitPos),
            'REMOTE_PORT' => substr($client, $splitPos + 1)
        ];

        try {
            $connection = new Tcp($fd, $attribute);
            $connection->setDispatcher($this->dispatcher);
            $connection->setProtocol($this->protocol);
            $connection->resume();
        } catch (Throwable $t) {
            Debug::handleThrow($t);
        }
    }
}
