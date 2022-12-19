<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="BelVG\ProductSubscription\Repository\SubscriptionCartProductRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class SubscriptionCartProduct
{
    /**
     * @var int
     *
     * @ORM\Column(name="id_subscription_cart_product", type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="id_cart", type="integer", nullable=false, options={"unsigned":true})
     */
    private $cartId;

    /**
     * @var int
     *
     * @ORM\Column(name="id_product", type="integer", nullable=false, options={"unsigned":true})
     */
    private $productId;

    /**
     * @var int
     *
     * @ORM\Column(name="id_product_attribute", type="integer", nullable=false, options={"unsigned":true, "default":0})
     */
    private $productAttributeId;

    /**
     * @var SubscriptionPeriodicity
     *
     * @ORM\ManyToOne(targetEntity="SubscriptionPeriodicity")
     * @ORM\JoinColumn(name="id_periodicity", referencedColumnName="id_periodicity", nullable=false)
     */
    private $periodicity;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_active", type="boolean", nullable=false)
     */
    private $isActive;

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
     * @param SubscriptionPeriodicity $periodicity
     * @param int $cartId
     * @param int $productId
     * @param int $productAttributeId
     * @param bool $isActive
     */
    public function __construct(
        SubscriptionPeriodicity $periodicity,
        int $cartId,
        int $productId,
        int $productAttributeId = 0,
        bool $isActive = true
    ) {
        $this->periodicity = $periodicity;
        $this->cartId = $cartId;
        $this->productId = $productId;
        $this->productAttributeId = $productAttributeId;
        $this->isActive = $isActive;
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return true === $this->isActive;
    }

    /**
     * Setting entity to be active.
     */
    public function setActive(): void
    {
        $this->isActive = true;
    }

    /**
     * @return SubscriptionPeriodicity
     */
    public function getPeriodicity(): SubscriptionPeriodicity
    {
        return $this->periodicity;
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
     * @param SubscriptionPeriodicity $periodicity
     */
    public function setPeriodicity(SubscriptionPeriodicity $periodicity): void
    {
        $this->periodicity = $periodicity;
    }

    /**
     * @ORM\PreUpdate()
     */
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
