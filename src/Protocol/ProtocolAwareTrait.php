<?php

declare(strict_types=1);

namespace Plattry\Network\Protocol;

/**
 * Helper that sets protocol instance.
 */
trait ProtocolAwareTrait
{
    /**
     * @var ProtocolInterface|null
     */
    protected ?ProtocolInterface $protocol = null;

    /**
     * @param ProtocolInterface|null $protocol
     */
    public function setProtocol(?ProtocolInterface $protocol): void
    {
        $this->protocol = $protocol;
    }
}
