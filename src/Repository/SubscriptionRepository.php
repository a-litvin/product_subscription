<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Repository;

use BelVG\ProductSubscription\Entity\Subscription;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use PrestaShop\PrestaShop\Core\Domain\Exception\DomainException;

class SubscriptionRepository extends EntityRepository
{
    /**
     * @param int $customerId
     *
     * @return array|Subscription[]
     */
    public function findAllWithAssociationsByCustomerId(int $customerId): array
    {
        $qb = $this->createQueryBuilder('s');
        $expr = $qb->expr();

        return $qb
            ->addSelect('p, r, pr')
            ->innerJoin('s.periodicity', 'p')
            ->leftJoin('s.reasons', 'r')
            ->leftJoin('s.subscriptionProducts', 'pr')
            ->where($expr->eq('s.customerId', ':customerId'))
            ->andWhere($expr->eq('s.isDeleted', ':isDeleted'))
            ->setParameter('customerId', $customerId)
            ->setParameter('isDeleted', false, Type::BOOLEAN)
            ->orderBy('s.nextDelivery')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $id
     *
     * @return Subscription|null
     */
    public function findWithHistories(int $id): ?Subscription
    {
        $qb = $this->createQueryBuilder('s');
        $expr = $qb->expr();

        return $qb
            ->addSelect('sh')
            ->leftJoin('s.subscriptionHistories', 'sh')
            ->where($expr->eq('s.id', ':id'))
            ->setParameter('id', $id, Type::INTEGER)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param \DateTimeImmutable $date
     *
     * @return array|Subscription[]
     */
    public function findAllActiveWithAssociationsByDateDelivery(\DateTimeImmutable $date): array
    {
        $qb = $this->createQueryBuilder('s');
        $expr = $qb->expr();

        return $qb
            ->addSelect('p, r, pr')
            ->innerJoin('s.periodicity', 'p')
            ->leftJoin('s.reasons', 'r')
            ->leftJoin('s.subscriptionProducts', 'pr')
            ->where($expr->lte('s.nextDelivery', ':nextDelivery'))
            ->andWhere($expr->eq('s.isActive', ':isActive'))
            ->andWhere($expr->eq('s.isDeleted', ':isDeleted'))
            ->setParameter('nextDelivery', $date, Type::DATETIME_IMMUTABLE)
            ->setParameter('isActive', true, Type::BOOLEAN)
            ->setParameter('isDeleted', false, Type::BOOLEAN)
            ->orderBy('s.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $id
     *
     * @return Subscription
     *
     * @throws DomainException
     */
    public function getActiveWithAssociations(int $id): Subscription
    {
        $qb = $this->createQueryBuilder('s');
        $expr = $qb->expr();

        $subscription = $qb
            ->addSelect('p, r, pr')
            ->innerJoin('s.periodicity', 'p')
            ->leftJoin('s.reasons', 'r')
            ->leftJoin('s.subscriptionProducts', 'pr')
            ->where($expr->eq('s.isActive', ':isActive'))
            ->andWhere($expr->eq('s.isDeleted', ':isDeleted'))
            ->andWhere($expr->eq('s.id', ':id'))
            ->setParameter('id', $id, Type::INTEGER)
            ->setParameter('isActive', true, Type::BOOLEAN)
            ->setParameter('isDeleted', false, Type::BOOLEAN)
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $subscription) {
            throw new DomainException(sprintf('There is no active subscription with ID:%d.', $id));
        }

        return $subscription;
    }

    /**
     * @param int $subscriptionId
     *
     * @return Subscription|null
     */
    public function findWithAssociations(int $subscriptionId): ?Subscription
    {
        $qb = $this->createQueryBuilder('s');
        $expr = $qb->expr();

        return $qb
            ->addSelect('p, r, pr')
            ->innerJoin('s.periodicity', 'p')
            ->leftJoin('s.reasons', 'r')
            ->leftJoin('s.subscriptionProducts', 'pr')
            ->where($expr->eq('s.id', ':subscriptionId'))
            ->setParameter('subscriptionId', $subscriptionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param int $subscriptionId
     *
     * @return Subscription|null
     */
    public function findWithBlockingReasons(int $subscriptionId): ?Subscription
    {
        $qb = $this->createQueryBuilder('s');
        $expr = $qb->expr();

        return $qb
            ->addSelect('r')
            ->leftJoin('s.reasons', 'r')
            ->where($expr->eq('s.id', ':subscriptionId'))
            ->setParameter('subscriptionId', $subscriptionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

	/**
	 * @param int $customerId Customer id
	 * @param int $vaultId BraintreeOfficialVaulting id
	 *
	 * @return array|Subscription[]
	 */
	public function findAllWithAssociationsByCustomerIdAndVaultId(int $customerId, int $vaultId): array
	{
		$qb = $this->createQueryBuilder('s');
		$expr = $qb->expr();

		return $qb
			->addSelect('sp')
			->leftJoin('s.subscriptionPayment', 'sp')
			->where($expr->eq('s.customerId', ':customerId'))
			->andWhere($expr->eq('s.isDeleted', ':isDeleted'))
			->andWhere($expr->eq('sp.vaultId', ':idVault'))
			->setParameter('customerId', $customerId)
			->setParameter('idVault', $vaultId)
			->setParameter('isDeleted', false, Type::BOOLEAN)
			->orderBy('s.id')
			->getQuery()
			->getResult();
	}
}
