<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="BelVG\ProductSubscription\Repository\SubscriptionBlockingReasonRepository")
 */
class SubscriptionBlockingReason
{
    public const NO_DELIVERY_ADDRESS = 'NO_DELIVERY_ADDRESS';
    public const PAUSED_BY_CUSTOMER = 'PAUSED_BY_CUSTOMER';

    public static $reasons = [
        self::NO_DELIVERY_ADDRESS,
        self::PAUSED_BY_CUSTOMER,
    ];

    /**
     * @var int
     *
     * @ORM\Column(name="id_reason", type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="handle", type="string", length=64, nullable=false, unique=true)
     */
    private $handle;

    /**
     * @var string|null
     *
     * @ORM\Column(name="`name`", type="string", length=128, nullable=true)
     */
    private $name;

    /**
     * @var \DateTimeImmutable
     *
     * @ORM\Column(name="created_at", type="datetime_immutable", nullable=false)
     */
    private $createdAt;

    /**
     * @param string $handle
     */
    public function __construct(string $handle)
    {
        $this->handle = $handle;
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @return string
     */
    public function getHandle(): string
    {
        return $this->handle;
    }
}
