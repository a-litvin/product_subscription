<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class SubscriptionPeriodicity
{
    /**
     * @var int
     *
     * @ORM\Column(name="id_periodicity", type="integer")
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
     * @ORM\Column(name="`interval`", type="smallint", nullable=false, unique=true)
     */
    private $interval;

    /**
     * @var string
     *
     * @ORM\Column(name="`name`", type="string", length=64, nullable=false)
     */
    private $name;

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
     * @param string $name
     * @param int $interval
     */
    public function __construct(string $name, int $interval)
    {
        $this->name = $name;
        $this->interval = $interval;
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @return int
     */
    public function getInterval(): int
    {
        return $this->interval;
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
     * @param int $interval
     */
    public function setInterval(int $interval): void
    {
        $this->interval = $interval;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @param int|null $oldId
     */
    public function setOldId(?int $oldId): void
    {
        $this->oldId = $oldId;
    }

    /**
     * @ORM\PreUpdate()
     */
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
