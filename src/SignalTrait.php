<?php

declare(strict_types=1);

namespace Plattry\Network;

use Plattry\Network\Connection\Tcp;

/**
 * Trait SignalTrait
 * @package Plattry\Network
 * @method pause() void
 */
trait SignalTrait
{
    /**
     * @var \EvSignal
     */
    protected \EvSignal $sigQuit;

    /**
     * Install signal event.
     */
    protected function installSignal()
    {
        !isset($this->sigQuit) &&
        $this->sigQuit = new \EvSignal(SIGQUIT, [$this, 'handleSigQuit']);
    }

    /**
     * SIGQUIT event.
     */
    protected function handleSigQuit()
    {
        $this->pause();

        $pools = Tcp::getPool();
        array_walk($pools, fn($tcp) => $tcp->close());

        \Ev::stop();
    }
}
