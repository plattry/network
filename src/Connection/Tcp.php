<?php

declare(strict_types=1);

namespace Plattry\Network\Connection;

use Plattry\Dispatcher\DispatcherAwareTrait;
use Plattry\Network\Event\EventFactory;
use Plattry\Network\Protocol\ProtocolAwareTrait;
use Plattry\Utils\Debug;
use Throwable;

/**
 * Instance of TCP connection.
 */
class Tcp implements ConnectionInterface
{
    use DispatcherAwareTrait;
    use ProtocolAwareTrait;

    /**
     * Connection status.
     */
    public const STATUS_CLOSED = 0;
    public const STATUS_CONNECTED = 1;

    /**
     * Connection default attributes.
     */
    protected const ATTRIBUTE_DEFAULT = [
        self::ATTRIBUTE_ID => null,
        self::ATTRIBUTE_LOCAL_IP => null,
        self::ATTRIBUTE_LOCAL_PORT => null,
        self::ATTRIBUTE_REMOTE_IP => null,
        self::ATTRIBUTE_REMOTE_PORT => null
    ];

    /**
     * All connections.
     * @var array
     */
    protected static array $pool = [];

    /**
     * @var int
     */
    protected static int $buffer_size = 65535;

    /**
     * @var int
     */
    protected int $status = self::STATUS_CLOSED;

    /**
     * @var mixed
     */
    protected mixed $fd;

    /**
     * Connection attribute.
     * @var array
     */
    protected array $attribute = self::ATTRIBUTE_DEFAULT;

    /**
     * The length of current packet.
     * @var int
     */
    protected int $packet = 0;

    /**
     * The read event watcher.
     * @var \EvIo|null
     */
    protected ?\EvIo $reader;

    /**
     * The write event watcher.
     * @var \EvIo|null
     */
    protected ?\EvIo $writer;

    /**
     * @param mixed $fd
     * @param string $remote_address
     */
    public function __construct(mixed $fd, string $remote_address)
    {
        // Init connection unique id
        $id = spl_object_id($this);

        static::$pool[$id] = $this;

        // Init socket
        $this->fd = $fd;
        stream_set_blocking($this->fd, false);
        stream_set_read_buffer($this->fd, 0);

        // Init attributes
        $local_address = (string) stream_socket_get_name($this->fd, false);
        $local_split_pos = strpos($local_address, ':');
        $remote_split_pos = strpos($remote_address, ':');
        $this->attribute[self::ATTRIBUTE_ID] = $id;
        $this->attribute[self::ATTRIBUTE_LOCAL_IP] = substr($local_address, 0, $local_split_pos++);
        $this->attribute[self::ATTRIBUTE_LOCAL_PORT] = substr($local_address, $local_split_pos);
        $this->attribute[self::ATTRIBUTE_REMOTE_IP] = substr($remote_address, 0, $remote_split_pos++);
        $this->attribute[self::ATTRIBUTE_REMOTE_PORT] = substr($remote_address, $remote_split_pos);

        // Init reader and writer
        $this->reader = \EvIo::createStopped($this->fd, \Ev::READ, [$this, "read"], "");
        $this->writer = \EvIo::createStopped($this->fd, \Ev::WRITE, [$this, "write"], "");
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Suspend receiving data.
     */
    public function pause(): void
    {
        $this->reader->stop();
        $this->writer->stop();
    }

    /**
     * Resume receiving data.
     */
    public function resume(): void
    {
        if ($this->status === self::STATUS_CLOSED) {
            !is_null($this->dispatcher) &&
            $this->dispatcher->dispatch(EventFactory::createConnectEvent($this));

            $this->status = self::STATUS_CONNECTED;
        }

        $this->reader->start();

        if ($this->writer->data !== '') {
            $this->writer->start();
        }
    }

    /**
     * Handler of reader.
     * @param \EvIo $ev
     * @param int $event
     */
    protected function read(\EvIo $ev, int $event = \Ev::READ): void
    {
        try {
            $buffer = fread($ev->fd, static::$buffer_size);
            if ("" === $buffer || false === $buffer) {
                if (!is_resource($this->fd) || feof($this->fd)) {
                    $this->close();
                }

                return;
            }

            $this->reader->data .= $buffer;

            if (is_null($this->protocol)) {
                !is_null($this->dispatcher) &&
                $this->dispatcher->dispatch(EventFactory::createMessageEvent($this));

                $this->reader->data = "";

                return;
            }

            if (0 === $this->packet) {
                $this->packet = $this->protocol->check($this);

                if (0 === $this->packet) {
                    return;
                }
            }

            if (strlen($this->reader->data) >= $this->packet) {
                !is_null($this->dispatcher) &&
                $this->dispatcher->dispatch(EventFactory::createMessageEvent($this));

                $this->reader->data = substr($this->reader->data, $this->packet + 1);

                $this->packet = 0;
            }
        } catch (Throwable $t) {
            Debug::handleThrow($t);
        }
    }

    /**
     * Handler of writer.
     * @param \EvIo $ev
     * @param int $event
     */
    protected function write(\EvIo $ev, int $event = \Ev::WRITE): void
    {
        try {
            $length = fwrite($ev->fd, $this->writer->data, 8192);
            if (false === $length) {
                if (!is_resource($this->fd) || feof($this->fd)) {
                    !is_null($this->dispatcher) &&
                    $this->dispatcher->dispatch(EventFactory::createErrorEvent($this));
                }

                return;
            }

            $this->writer->data = substr($this->writer->data, $length);
            if ("" === $this->writer->data) {
                $this->writer->stop();
            }
        } catch (Throwable $t) {
            Debug::handleThrow($t);
        }
    }

    /**
     * @inheritDoc
     */
    public function getAttribute(): array
    {
        return $this->attribute;
    }
    
    /**
     * @inheritDoc
     */
    public function receive(?int $length = null): string
    {
        if (is_int($length)) {
            return substr($this->reader->data, 0, $length);
        }

        if ($this->packet > 0) {
            return substr($this->reader->data, 0, $this->packet);
        }

        return $this->reader->data;
    }

    /**
     * @inheritDoc
     */
    public function send(string $data): void
    {
        $this->writer->data .= $data;

        $this->writer->start();
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        $this->pause();

        fclose($this->fd);

        $this->status = self::STATUS_CLOSED;

        !is_null($this->dispatcher) &&
        $this->dispatcher->dispatch(EventFactory::createCloseEvent($this));

        unset(static::$pool[spl_object_id($this)]);
    }

    /**
     * @return array
     */
    public static function getPool(): array
    {
        return static::$pool;
    }
}
