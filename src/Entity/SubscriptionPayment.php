<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class SubscriptionPayment
{
    /**
     * @var int
     *
     * @ORM\Column(name="id_subscription_payment", type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Subscription
     *
     * @ORM\OneToOne(targetEntity="Subscription")
     * @ORM\JoinColumn(name="id_subscription", referencedColumnName="id_subscription", nullable=false)
     */
    private $subscription;

    /**
     * @var string
     *
     * @ORM\Column(name="module_name", type="string", length=128, nullable=false)
     */
    private $moduleName;

    /**
     * @var int
     *
     * @ORM\Column(name="id_vault", type="integer", nullable=false, options={"unsigned":true})
     */
    private $vaultId;

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
     * @param Subscription $subscription
     * @param string $moduleName
     * @param int $vaultId
     */
    public function __construct(Subscription $subscription, string $moduleName, int $vaultId)
    {
        $this->subscription = $subscription;
        $this->moduleName = $moduleName;
        $this->vaultId = $vaultId;
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @return int
     */
    public function getVaultId(): int
    {
        return $this->vaultId;
    }

    /**
     * @return string
     */
    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    /**
     * @param int $vaultId
     */
    public function setVaultId(int $vaultId): void
    {
        $this->vaultId = $vaultId;
    }

    /**
     * @ORM\PreUpdate()
     */
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
