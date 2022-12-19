<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service;

use Address;
use BelVG\ProductSubscription\Entity\Subscription;
use BelVG\ProductSubscription\Entity\SubscriptionBlockingReason;
use Cart;
use Doctrine\ORM\EntityManagerInterface;

class SubscriptionAddressService
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var Address
     */
    private $address;

    /**
     * @var SubscriptionBlockingReason
     */
    private $reason;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param Address $address
     */
    public function process(Address $address)
    {
        $this->address = $address;
        $this->run();
    }

    /**
     * Blocking a subscription if there is no delivery address.
     */
    private function run(): void
    {
        $softDelete = (bool) $this->address->deleted;

        if (true === $softDelete) {
            return;
        }

        $needFlush = false;
        $customerId = $this->address->id_customer;
        $subscriptions = $this->entityManager->getRepository(Subscription::class)->findBy([
            'customerId' => $customerId,
        ]);

        foreach ($subscriptions as $subscription) {
            $cart = new Cart($subscription->getCartId());

            if (0 == $cart->id_address_delivery && $subscription->isActive()) {
                $subscription->addReason($this->getDeliveryAddressReason());
                $needFlush = true;
            }
        }

        if (true === $needFlush) {
            $this->entityManager->flush();
        }

    }

    /**
     * @return SubscriptionBlockingReason
     */
    private function getDeliveryAddressReason(): SubscriptionBlockingReason
    {
        if ($this->reason instanceof SubscriptionBlockingReason) {
            return $this->reason;
        }

        $this->reason = $this->entityManager
            ->getRepository(SubscriptionBlockingReason::class)
            ->getOrCreateByHandle(SubscriptionBlockingReason::NO_DELIVERY_ADDRESS);

        return $this->reason;
    }
}
