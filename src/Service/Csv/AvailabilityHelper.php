<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service\Csv;

use BelVG\ProductSubscription\Entity\SubscriptionAvailability;
use PrestaShop\PrestaShop\Core\Domain\Exception\DomainException;
use PrestaShop\PrestaShop\Core\Import\File\DataRow\DataRowInterface;
use Product;
use Symfony\Component\Console\Style\SymfonyStyle;

class AvailabilityHelper extends AbstractHelper
{
    private const HEADER_COLUMNS = [
        'id_product' => 0,
        'id_shop' => 1,
        'period_types' => 2,
        'only_subscription' => 3,
        'status' => 4,
    ];

    /**
     * {@inheritDoc}
     */
    public function processRow(DataRowInterface $dataCell, SymfonyStyle $io): void
    {
        $productId = (int) $dataCell[self::HEADER_COLUMNS['id_product']]->getValue();

        if (!Product::existsInDatabase($productId, 'product')) {
            throw new DomainException(sprintf('There is no product with ID:%d in database.', $productId));
        }

        $availability = $this->entityManager->getRepository(SubscriptionAvailability::class)->findOneBy([
            'productId' => $productId,
        ]);

        if (null !== $availability) {
            return;
        }

        $availability = new SubscriptionAvailability($productId);
        $this->entityManager->persist($availability);

        $io->writeln(sprintf('Subscription availability was created for product with ID:%d.', $productId));
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaderColumns(): array
    {
        return array_keys(self::HEADER_COLUMNS);
    }

    /**
     * {@inheritDoc}
     */
    public function postProcess(SymfonyStyle $io): void
    {
        $this->entityManager->flush();
    }
}
