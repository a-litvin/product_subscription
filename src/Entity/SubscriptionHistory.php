<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class SubscriptionHistory
{
    /**
     * @var int
     *
     * @ORM\Column(name="id_subscription_history", type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="id_order", type="integer", nullable=false, options={"unsigned":true})
     */
    private $orderId;

    /**
     * @var Subscription
     *
     * @ORM\ManyToOne(targetEntity="Subscription", inversedBy="subscriptionHistories")
     * @ORM\JoinColumn(name="id_subscription", referencedColumnName="id_subscription", nullable=false)
     */
    private $subscription;

    /**
     * @var \DateTimeImmutable
     *
     * @ORM\Column(name="created_at", type="datetime_immutable", nullable=false)
     */
    private $createdAt;

    /**
     * @param Subscription $subscription
     * @param int $orderId
     */
    public function __construct(Subscription $subscription, int $orderId)
    {
        $this->subscription = $subscription;
        $this->orderId = $orderId;
        $this->createdAt = new \DateTimeImmutable();
    }
}
