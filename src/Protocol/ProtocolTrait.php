<?php

declare(strict_types=1);

namespace Plattry\Network\Protocol;

/**
 * Trait ProtocolTrait
 * @package Plattry\Network\Protocol
 */
trait ProtocolTrait
{
    /**
     * The enveloper and analyser
     * @var ProtocolInterface|null
     */
    protected ProtocolInterface|null $protocol = null;

    /**
     * Set protocol to envelop and analyse data.
     * @param ProtocolInterface|null $protocol
     * @return void
     */
    public function setProtocol(ProtocolInterface|null $protocol): void
    {
        $this->protocol = $protocol;
    }
}
