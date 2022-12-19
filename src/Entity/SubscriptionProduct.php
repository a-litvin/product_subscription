<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="BelVG\ProductSubscription\Repository\SubscriptionProductRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class SubscriptionProduct
{
    /**
     * @var int
     *
     * @ORM\Column(name="id_subscription_product", type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Subscription
     *
     * @ORM\ManyToOne(targetEntity="Subscription", inversedBy="subscriptionProducts")
     * @ORM\JoinColumn(name="id_subscription", referencedColumnName="id_subscription")
     */
    private $subscription;

    /**
     * @var int
     *
     * @ORM\Column(name="id_product", type="integer", nullable=false, options={"unsigned":true})
     */
    private $productId;

    /**
     * @var int
     *
     * @ORM\Column(name="id_product_attribute", type="integer", nullable=false, options={"unsigned":true})
     */
    private $productAttributeId;

    /**
     * @var bool
     *
     * @ORM\Column(name="next_shipment_only", type="boolean", nullable=true)
     */
    private $nextShipmentOnly;

    /**
     * @var bool
     *
     * @ORM\Column(name="skip_next_shipment_only", type="boolean", nullable=true)
     */
    private $skipNextShipmentOnly;

    /**
     * @var \DateTimeImmutable
     *
     * @ORM\Column(name="created_at", type="datetime_immutable")
     */
    private $createdAt;

    /**
     * @var \DateTimeImmutable|null
     *
     * @ORM\Column(name="updated_at", type="datetime_immutable")
     */
    private $updatedAt;

    /**
     * @param Subscription $subscription
     * @param int $productId
     * @param int $productAttributeId
     */
    public function __construct(Subscription $subscription, int $productId, int $productAttributeId)
    {
        $this->subscription = $subscription;
        $this->productId = $productId;
        $this->productAttributeId = $productAttributeId;
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getProductId(): int
    {
        return $this->productId;
    }

    /**
     * @return int
     */
    public function getProductAttributeId(): int
    {
        return $this->productAttributeId;
    }

    /**
     * @return bool
     */
    public function isNextShipmentOnly(): bool
    {
        return true === $this->nextShipmentOnly;
    }

    /**
     * @return bool
     */
    public function isSkipNextShipmentOnly(): bool
    {
        return true === $this->skipNextShipmentOnly;
    }

    /**
     * @return bool
     */
    public function isScheduled(): bool
    {
        return false === $this->nextShipmentOnly && false === $this->skipNextShipmentOnly;
    }

    /**
     * Set next shipment only.
     */
    public function setNextShipmentOnly(): void
    {
        $this->nextShipmentOnly = true;
        $this->skipNextShipmentOnly = false;
    }

    /**
     * Skip next shipment only.
     */
    public function skipNextShipmentOnly(): void
    {
        $this->skipNextShipmentOnly = true;
        $this->nextShipmentOnly = false;
    }

    /**
     * Set scheduled.
     */
    public function setScheduled(): void
    {
        $this->nextShipmentOnly = false;
        $this->skipNextShipmentOnly = false;
    }

    /**
     * @ORM\PreUpdate()
     */
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
