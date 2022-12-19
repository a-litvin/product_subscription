<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service\Csv;

use BelVG\ProductSubscription\Entity\SubscriptionPeriodicity;
use PrestaShop\PrestaShop\Core\Import\File\DataRow\DataRowInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PeriodicityHelper extends AbstractHelper
{
    private const HEADER_COLUMNS = [
        'id_belvg_periodicity' => 0,
        'sort_order' => 1,
        'repeat_each' => 2,
        'repeat_each_type' => 3,
        'expires_after' => 4,
        'expires_after_type' => 5,
        'exclude_weekdays' => 6,
        'require_payment_before' => 7,
    ];

    /**
     * {@inheritDoc}
     */
    public function processRow(DataRowInterface $dataCell, SymfonyStyle $io): void
    {
        $oldId = (int) $dataCell[self::HEADER_COLUMNS['id_belvg_periodicity']]->getValue();

        $periodicity = $this->entityManager->getRepository(SubscriptionPeriodicity::class)->findOneBy([
            'oldId' => $oldId,
        ]);

        if (null !== $periodicity) {
            return;
        }

        $interval = (int) $dataCell[self::HEADER_COLUMNS['repeat_each']]->getValue();
        $name = $interval . ' days';

        $periodicity = new SubscriptionPeriodicity($name, $interval);
        $periodicity->setOldId($oldId);
        $this->entityManager->persist($periodicity);

        $io->writeln(sprintf('Periodicity was created for interval %s.', $name));
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
