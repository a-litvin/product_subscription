<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service\Csv;

use BelVG\ProductSubscription\ReadModel\LoyaltyReadModel;
use Customer;
use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\PrestaShop\Core\Domain\Exception\DomainException;
use PrestaShop\PrestaShop\Core\Import\File\CsvFileReader;
use PrestaShop\PrestaShop\Core\Import\File\DataRow\DataRowInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class LoyaltyHelper extends AbstractHelper
{
    private const HEADER_COLUMNS = [
        'id_loyalty' => 0,
        'id_loyalty_state' => 1,
        'id_customer' => 2,
        'id_order' => 3,
        'id_cart' => 4,
        'id_cart_rule' => 5,
        'points' => 6,
        'date_add' => 7,
        'date_upd' => 8,
    ];

    /**
     * @var LoyaltyReadModel
     */
    private $loyaltyReadModel;

    /**
     * @var array
     */
    private $customers = [];

    /**
     * @param CsvFileReader $csvFileReader
     * @param EntityManagerInterface $entityManager
     * @param LoyaltyReadModel $loyaltyReadModel
     */
    public function __construct(CsvFileReader $csvFileReader, EntityManagerInterface $entityManager, LoyaltyReadModel $loyaltyReadModel)
    {
        parent::__construct($csvFileReader, $entityManager);
        $this->loyaltyReadModel = $loyaltyReadModel;
    }

    /**
     * {@inheritDoc}
     */
    public function preProcess(SymfonyStyle $io): void
    {
        $this->loyaltyReadModel->getConnection()->getConfiguration()->setSQLLogger(null);

        $io->progressStart($this->rowsNumber);
    }

    /**
     * {@inheritDoc}
     */
    public function processRow(DataRowInterface $dataCell, SymfonyStyle $io): void
    {
        $customerId = (int) $dataCell[self::HEADER_COLUMNS['id_customer']]->getValue();

        if (!Customer::existsInDatabase($customerId, 'customer')) {
            throw new DomainException(sprintf('Customer with ID:%d is not found in DB.', $customerId));
        }

        $points = (float) $dataCell[self::HEADER_COLUMNS['points']]->getValue();
        $this->customers[$customerId][] = $points;

        $io->progressAdvance();
    }

    /**
     * {@inheritDoc}
     */
    public function postProcess(SymfonyStyle $io): void
    {
        $io->progressFinish();
        $io->writeln('End file processing.');

        $this->preparePointsData($io);
        $this->clearPoints($io);

        foreach ($this->customers as $customerId => $customerPoints) {
            $this->loyaltyReadModel->addPoints($customerId, $customerPoints);
            $io->writeln(sprintf('Adding %d points to customer %d.', $customerPoints, $customerId));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaderColumns(): array
    {
        return array_keys(self::HEADER_COLUMNS);
    }

    /**
     * @param SymfonyStyle $io
     */
    private function preparePointsData(SymfonyStyle $io): void
    {
        foreach ($this->customers as $customerId => $customerPoints) {
            $totalPoints = array_sum($customerPoints);

            if (0 > $totalPoints) {
                unset($this->customers[$customerId]);
                $io->warning(sprintf("Can't add %d points to customer %d.", $totalPoints, $customerId));
            } else {
                $this->customers[$customerId] = $totalPoints;
            }
        }
    }

    /**
     * @param SymfonyStyle $io
     */
    private function clearPoints(SymfonyStyle $io): void
    {
        $this->loyaltyReadModel->clearAllPoints(array_keys($this->customers));
        $io->writeln('Clearing all points in DB has completed.');
        $io->writeln('Start file processing.');
    }
}
