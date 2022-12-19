<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Command\Import;

use BelVG\ProductSubscription\Service\Csv\AbstractHelper;
use BelVG\ProductSubscription\Service\Csv\AvailabilityHelper;
use BelVG\ProductSubscription\Service\Csv\LoyaltyHelper;
use BelVG\ProductSubscription\Service\Csv\PeriodicityHelper;
use BelVG\ProductSubscription\Service\Csv\SubscriptionHelper;
use BelVG\ProductSubscription\Service\Csv\SubscriptionProductHelper;
use Configuration;
use PrestaShop\PrestaShop\Core\Exception\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ImportCsvCommand extends ContainerAwareCommand
{
    public const AVAILABILITY_FILE = 'ps_belvg_sub_subscription_products.csv';
    public const PERIODICITY_FILE = 'ps_belvg_sub_periodicity.csv';
    public const SUBSCRIPTION_FILE = 'ps_belvg_sub_sets.csv';
    public const SUBSCRIPTION_PRODUCT_FILE = 'ps_belvg_sub_subscription.csv';
    public const LOYALTY_FILE = 'ps_wr_loyalty.csv';

    public const SUPPORTED_CSV_FILES = [
        self::AVAILABILITY_FILE,
        self::PERIODICITY_FILE,
        self::SUBSCRIPTION_FILE,
        self::SUBSCRIPTION_PRODUCT_FILE,
        self::LOYALTY_FILE,
    ];

    private const FILENAME_ARGUMENT = 'filename';

    /**
     * @var string
     */
    protected static $defaultName = 'productsubscription:import:csv';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->addArgument(self::FILENAME_ARGUMENT, InputArgument::REQUIRED, 'The filename with relative path.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     *
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting...');

        $isSandboxMode = (bool) Configuration::get('BRAINTREEOFFICIAL_SANDBOX');
        $file = new \SplFileInfo($input->getArgument(self::FILENAME_ARGUMENT));

        if (true === $isSandboxMode
            && in_array($file->getFilename(), [self::SUBSCRIPTION_FILE], true)
        ) {
            $io->warning('You should switch Braintree Module to production mode.');

            return;
        }

        $this->validateFilename($file);

        /** @var AbstractHelper $helper */
        $helper = $this->getContainer()->get($this->getApplicableHelperClass($file));
        $header = $helper->getHeader($file);

        if (false === $helper->validateHeader($header)) {
            throw new InvalidArgumentException("The file doesn't contain valid headers.");
        }

        $helper->countRowsNumber($file);

        $helper->preProcess($io);

        $generator = $helper->getRowGenerator($file);

        while ($dataRow = $generator->current()) {
            try {
                $helper->processRow($dataRow, $io);
            } catch (\Throwable $exception) {
                $this->logger->error('An error occurred importing the line of the csv file.', [
                    'dataRow' => $dataRow,
                    'filename' => $file->getFilename(),
                    'message' => $exception->getMessage(),
                ]);
            } finally {
                $generator->next();
            }
        }

        $helper->postProcess($io);

        $io->title('Import was done!');
    }

    /**
     * @param \SplFileInfo $fileInfo
     *
     * @throws InvalidArgumentException
     */
    private function validateFilename(\SplFileInfo $fileInfo)
    {
        if (!in_array($fileInfo->getFilename(), self::SUPPORTED_CSV_FILES, true)) {
            throw new InvalidArgumentException(sprintf('The unsupported file: %s.', $fileInfo->getFilename()));
        }
    }

    /**
     * @param \SplFileInfo $fileInfo
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    private function getApplicableHelperClass(\SplFileInfo $fileInfo): string
    {
        switch ($fileInfo->getFilename()) {
            case self::AVAILABILITY_FILE:
                return AvailabilityHelper::class;
            case self::PERIODICITY_FILE:
                return PeriodicityHelper::class;
            case self::SUBSCRIPTION_FILE:
                return SubscriptionHelper::class;
            case self::SUBSCRIPTION_PRODUCT_FILE:
                return SubscriptionProductHelper::class;
            case self::LOYALTY_FILE:
                return LoyaltyHelper::class;
            default:
                throw new InvalidArgumentException(sprintf('Command for processing %s file not found.', $fileInfo->getFilename()));
        }
    }
}
