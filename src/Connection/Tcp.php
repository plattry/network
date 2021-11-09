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

    const STATUS_CLOSED = 0;
    const STATUS_CONNECTED = 1;

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
    protected array $attribute;

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
     * @param array $attribute
     */
    public function __construct(mixed $fd, array $attribute = [])
    {
        static::$pool[spl_object_id($this)] = $this;

        $this->fd = $fd;
        stream_set_blocking($this->fd, false);
        stream_set_read_buffer($this->fd, 0);

        $this->attribute = $attribute;

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
