<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class SubscriptionAvailability
{
    /**
     * @var int
     *
     * @ORM\Column(name="id_availability", type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="id_product", type="integer", nullable=false, unique=true, options={"unsigned":true})
     */
    private $productId;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_available", type="boolean", nullable=false)
     */
    private $isAvailable;

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
     * @param int $productId
     * @param bool $isAvailable
     */
    public function __construct(int $productId, bool $isAvailable = true)
    {
        $this->productId = $productId;
        $this->isAvailable = $isAvailable;
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @return bool
     */
    public function isAvailable(): bool
    {
        return true === $this->isAvailable;
    }

    /**
     * @param bool $isAvailable
     */
    public function setIsAvailable(bool $isAvailable): void
    {
        $this->isAvailable = $isAvailable;
    }

    /**
     * @ORM\PreUpdate()
     */
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
