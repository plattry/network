<?php

declare(strict_types=1);

namespace Plattry\Network\Connection;

use Plattry\Network\Protocol\ProtocolTrait;
use Plattry\Utils\Debug;
use Throwable;

/**
 * Class Tcp
 * @package Plattry\Network\Connection
 */
class Tcp implements ConnectionInterface
{
    use EventTrait;

    use ProtocolTrait;

    /**
     * All connections
     * @var static[]
     */
    protected static array $pool = [];

    /**
     * Buffer size
     * @var int
     */
    protected static int $buffer_size = 65535;

    /**
     * Connection resource
     * @var mixed
     */
    protected mixed $fd;

    /**
     * Connection attribute
     * @var array
     */
    protected array $attribute;

    /**
     * The length of current packet
     * @var int
     */
    protected int $packet = 0;

    /**
     * The read event watcher
     * @var \EvIo|null
     */
    protected \EvIo|null $reader;

    /**
     * The write event watcher
     * @var \EvIo|null
     */
    protected \EvIo|null $writer;

    /**
     * Tcp constructor.
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

        $this->event && $this->event->trigger(Event::CONNECT);
    }

    /**
     * Suspend receiving data.
     * @return void
     */
    public function pause(): void
    {
        $this->reader->stop();
        $this->writer->stop();
    }

    /**
     * Resume receiving data.
     * @return void
     */
    public function resume(): void
    {
        $this->reader->start();

        if ($this->writer->data !== '') {
            $this->writer->start();
        }
    }

    /**
     * Handler of reader.
     * @param \EvIo   $ev
     * @param integer $event
     * @return void
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
                $this->event && $this->event->trigger(Event::MESSAGE, $this);

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
                $this->event && $this->event->trigger(Event::MESSAGE, $this);

                $this->reader->data = substr($this->reader->data, $this->packet + 1);

                $this->packet = 0;
            }
        } catch (Throwable $t) {
            Debug::handleThrow($t);
        }
    }

    /**
     * Handler of writer.
     * @param \EvIo   $ev
     * @param integer $event
     * @return void
     */
    protected function write(\EvIo $ev, int $event = \Ev::WRITE): void
    {
        try {
            $length = fwrite($ev->fd, $this->writer->data, 8192);
            if (false === $length) {
                if (!is_resource($this->fd) || feof($this->fd)) {
                    $this->event && $this->event->trigger(Event::ERROR);
                    $this->close();
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
    public function receive(int|null $length = null): string
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

        $this->event && $this->event->trigger(Event::CLOSE);

        unset(static::$pool[spl_object_id($this)]);
    }

    /**
     * @return static[]
     */
    public static function getPool(): array
    {
        return static::$pool;
    }
}
