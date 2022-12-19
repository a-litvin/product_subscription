<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service\Csv;

use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PrestaShop\PrestaShop\Core\Import\File\CsvFileReader;
use PrestaShop\PrestaShop\Core\Import\File\DataRow\DataRowInterface;
use PrestaShop\PrestaShop\Core\Domain\Exception\DomainException;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractHelper
{
    /**
     * @var CsvFileReader
     */
    private $csvFileReader;

    /**
     * @var Generator
     */
    private $generator;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var int
     */
    protected $rowsNumber;

    /**
     * @param CsvFileReader $csvFileReader
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(CsvFileReader $csvFileReader, EntityManagerInterface $entityManager)
    {
        $this->csvFileReader = $csvFileReader;
        $this->entityManager = $entityManager;
    }

    /**
     * @param \SplFileInfo $fileInfo
     *
     * @return DataRowInterface
     */
    public function getHeader(\SplFileInfo $fileInfo): DataRowInterface
    {
        $this->generator = $this->csvFileReader->read($fileInfo);

        return $this->generator->current();
    }

    /**
     * @param \SplFileInfo $fileInfo
     *
     * @return Generator
     */
    public function getRowGenerator(\SplFileInfo $fileInfo): Generator
    {
        if (null === $this->generator || 0 !== $this->generator->key()) {
            $this->generator = $this->csvFileReader->read($fileInfo);
        }

        $this->generator->next();

        return $this->generator;
    }

    /**
     * @param \SplFileInfo $fileInfo
     */
    public function countRowsNumber(\SplFileInfo $fileInfo)
    {
        $this->rowsNumber = 0;

        $fileHandle = fopen($fileInfo->getPathname(),'r');

        while (false !== fgets($fileHandle)) {
            $this->rowsNumber++;
        }

        fclose($fileHandle);
    }

    /**
     * @param DataRowInterface $header
     *
     * @return bool
     */
    public function validateHeader(DataRowInterface $header): bool
    {
        $headerColumns = $this->getHeaderColumns();

        if (count($header) !== count($headerColumns)) {
            return false;
        }

        foreach ($header as $dataCell) {
            if (!in_array($dataCell->getValue(), $headerColumns, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param SymfonyStyle $io
     */
    public function preProcess(SymfonyStyle $io): void
    {
    }

    /**
     * @param SymfonyStyle $io
     */
    public function postProcess(SymfonyStyle $io): void
    {
    }

    /**
     * @param DataRowInterface $dataCell
     * @param SymfonyStyle $io
     *
     * @throws DomainException
     */
    abstract public function processRow(DataRowInterface $dataCell, SymfonyStyle $io): void;

    /**
     * @return array
     */
    abstract public function getHeaderColumns(): array;
}
