<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Repository;

use BelVG\ProductSubscription\Entity\SubscriptionBlockingReason;
use Doctrine\ORM\EntityRepository;
use PrestaShop\PrestaShop\Core\Domain\Exception\DomainException;

class SubscriptionBlockingReasonRepository extends EntityRepository
{
    /**
     * @param string $handle
     *
     * @return SubscriptionBlockingReason
     *
     * @throws DomainException
     */
    public function getOrCreateByHandle(string $handle): SubscriptionBlockingReason
    {
        if (!in_array($handle, SubscriptionBlockingReason::$reasons, true)) {
            throw new DomainException(sprintf('There is no reason with handle %s.', $handle));
        }

        $reason = $this->findOneBy([
            'handle' => $handle,
        ]);

        if (null !== $reason) {
            return $reason;
        }

        $reason = new SubscriptionBlockingReason($handle);
        $this->_em->persist($reason);
        $this->_em->flush();

        return $reason;
    }
}
