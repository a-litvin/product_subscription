<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Entity;

use BelVG\ProductSubscription\DTO\ProductDTO;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="BelVG\ProductSubscription\Repository\SubscriptionRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Subscription
{
    /**
     * @var int
     *
     * @ORM\Column(name="id_subscription", type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int|null
     *
     * @ORM\Column(name="id_old", type="integer", nullable=true, unique=true)
     */
    private $oldId;

    /**
     * @var int
     *
     * @ORM\Column(name="id_customer", type="integer", nullable=false, options={"unsigned":true})
     */
    private $customerId;

    /**
     * @var SubscriptionPeriodicity
     *
     * @ORM\ManyToOne(targetEntity="SubscriptionPeriodicity")
     * @ORM\JoinColumn(name="id_periodicity", referencedColumnName="id_periodicity", nullable=false)
     */
    private $periodicity;

    /**
     * @var int
     *
     * @ORM\Column(name="id_cart", type="integer", nullable=false, options={"unsigned":true})
     */
    private $cartId;

    /**
     * @var \DateTimeImmutable
     *
     * @ORM\Column(name="next_delivery", type="date_immutable", nullable=true)
     */
    private $nextDelivery;

    /**
     * @var string|null
     *
     * @ORM\Column(name="`name`", type="string", length=64, nullable=true)
     */
    private $name;

    /**
     * @var string|null
     *
     * @ORM\Column(name="customer_message", type="string", length=256, nullable=true)
     */
    private $customerMessage;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_active", type="boolean", nullable=false)
     */
    private $isActive;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_deleted", type="boolean", nullable=false)
     */
    private $isDeleted = false;

    /**
     * @var Collection|SubscriptionBlockingReason[]
     *
     * @ORM\ManyToMany(targetEntity="SubscriptionBlockingReason")
     * @ORM\JoinTable(
     *     joinColumns={@ORM\JoinColumn(name="id_subscription", referencedColumnName="id_subscription")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="id_reason", referencedColumnName="id_reason")}
     * )
     */
    private $reasons;

    /**
     * @var Collection|SubscriptionProduct[]
     *
     * @ORM\OneToMany(targetEntity="SubscriptionProduct", mappedBy="subscription", orphanRemoval=true)
     */
    private $subscriptionProducts;

    /**
     * @var SubscriptionPayment
     *
     * @ORM\OneToOne(targetEntity="SubscriptionPayment", mappedBy="subscription", orphanRemoval=true)
     */
    private $subscriptionPayment;

    /**
     * @var Collection|SubscriptionHistory[]
     *
     * @ORM\OneToMany(targetEntity="SubscriptionHistory", mappedBy="subscription")
     */
    private $subscriptionHistories;

    /**
     * @var \DateTimeImmutable
     *
     * @ORM\Column(name="created_at", type="datetime_immutable", nullable=false)
     */
    private $createdAt;

    /**
     * @var \DateTimeImmutable|null
     *
     * @ORM\Column(name="updated_at", type="datetime_immutable")
     */
    private $updatedAt;

    /**
     * @param int $customerId
     * @param int $cartId
     * @param SubscriptionPeriodicity $periodicity
     * @param bool $isActive
     */
    public function __construct (
        int $customerId,
        int $cartId,
        SubscriptionPeriodicity $periodicity,
        bool $isActive = true
    ) {
        $this->customerId = $customerId;
        $this->cartId = $cartId;
        $this->periodicity = $periodicity;
        $this->isActive = $isActive;
        $this->createdAt = new \DateTimeImmutable();
        $this->reasons = new ArrayCollection();
        $this->subscriptionProducts = new ArrayCollection();
        $this->subscriptionHistories = new ArrayCollection();
    }

    /**
     * @param SubscriptionBlockingReason $reason
     */
    public function addReason(SubscriptionBlockingReason $reason): void
    {
        if (!$this->reasons->contains($reason)) {
            $this->reasons->add($reason);
        }

        $this->disable();
    }

    /**
     * @param SubscriptionBlockingReason $reason
     */
    public function removeReason(SubscriptionBlockingReason $reason): void
    {
        if ($this->reasons->contains($reason)) {
            $this->reasons->removeElement($reason);
        }

        if (!$this->hasReasons()) {
            $this->enable();
        }
    }

    /**
     * Removing of all reasons.
     */
    public function removeAllReasons(): void
    {
        $this->reasons = new ArrayCollection();
    }

    /**
     * @return bool
     */
    public function hasReasons(): bool
    {
        return !$this->reasons->isEmpty();
    }

    /**
     * @return bool
     */
    public function hasSubscriptionProducts(): bool
    {
        return !$this->subscriptionProducts->isEmpty();
    }

    /**
     * @return bool
     */
    public function hasHistories(): bool
    {
        return !$this->subscriptionHistories->isEmpty();
    }

    /**
     * @return bool
     */
    public function hasPausedByCustomerReason(): bool
    {
        if (!$this->hasReasons()) {
            return false;
        }

        return $this->reasons->exists(function ($key, SubscriptionBlockingReason $reason) {
            return SubscriptionBlockingReason::PAUSED_BY_CUSTOMER === $reason->getHandle();
        });
    }

    /**
     * @param int|null $oldId
     */
    public function setOldId(?int $oldId): void
    {
        $this->oldId = $oldId;
    }

    /**
     * @param string|null $name
     */
    public function setName(?string $name): void
    {
        $this->name = (empty($name)?'Autoship 1':$name);
    }

    /**
     * Make to inactive.
     */
    public function disable()
    {
        $this->isActive = false;
    }

    /**
     * Soft deleting.
     */
    public function remove()
    {
        $this->isDeleted = true;
    }

    /**
     * Make to active.
     */
    public function enable()
    {
        $this->isActive = true;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return int|null
     */
    public function getOldId(): ?int
    {
        return $this->oldId;
    }

    /**
     * @return int
     */
    public function getCartId(): int
    {
        return $this->cartId;
    }

    /**
     * @return int
     */
    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    /**
     * @return SubscriptionPayment
     */
    public function getSubscriptionPayment(): SubscriptionPayment
    {
        return $this->subscriptionPayment;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getCustomerMessage(): ?string
    {
        return $this->customerMessage;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getNextDelivery(): \DateTimeImmutable
    {
        return $this->nextDelivery;
    }

    /**
     * @return SubscriptionPeriodicity
     */
    public function getPeriodicity(): SubscriptionPeriodicity
    {
        return $this->periodicity;
    }

    /**
     * @return SubscriptionProduct[]|Collection
     */
    public function getSubscriptionProducts(): Collection
    {
        return $this->subscriptionProducts;
    }

    /**
     * @param int $productId
     * @param int $productAttributeId
     *
     * @return SubscriptionProduct|null
     */
    public function getSubscriptionProduct(int $productId, int $productAttributeId): ?SubscriptionProduct
    {
        if (!$this->hasSubscriptionProducts()) {
            return null;
        }

        $filtered = $this->subscriptionProducts->filter(function (SubscriptionProduct $subscriptionProduct) use ($productId, $productAttributeId) {
            return $productId === $subscriptionProduct->getProductId() && $productAttributeId === $subscriptionProduct->getProductAttributeId();
        });

        if ($filtered->isEmpty()) {
            return null;
        }

        return $filtered->first();
    }

    /**
     * @param ProductDTO $productDTO
     *
     * @return SubscriptionProduct|null
     */
    public function getSubscriptionProductByProductDTO(ProductDTO $productDTO): ?SubscriptionProduct
    {
        return $this->getSubscriptionProduct($productDTO->productId, $productDTO->productAttributeId);
    }

    /**
     * @param \DateTimeImmutable $nextDelivery
     */
    public function setNextDelivery(\DateTimeImmutable $nextDelivery): void
    {
        $this->nextDelivery = $nextDelivery;
    }

    /**
     * @param SubscriptionPeriodicity $periodicity
     */
    public function setPeriodicity(SubscriptionPeriodicity $periodicity): void
    {
        $this->periodicity = $periodicity;
    }

    /**
     * @param string|null $customerMessage
     */
    public function setCustomerMessage(?string $customerMessage): void
    {
        $this->customerMessage = $customerMessage;
    }

    /**
     * @ORM\PreUpdate()
     */
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
