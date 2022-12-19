<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service\Csv;

use Address;
use BelVG\ProductSubscription\Entity\Subscription;
use BelVG\ProductSubscription\Entity\SubscriptionPayment;
use BelVG\ProductSubscription\Entity\SubscriptionPeriodicity;
use BelVG\ProductSubscription\ReadModel\CarrierReadModel;
use BelVG\ProductSubscription\Service\VaultingHelper\BraintreeOfficialVaultingHelper;
use BraintreeOfficialAddons\classes\BraintreeOfficialVaulting;
use BraintreeOfficialAddons\services\ServiceBraintreeOfficialCustomer;
use Carrier;
use Cart;
use Configuration;
use Customer;
use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\PrestaShop\Core\Domain\Exception\DomainException;
use PrestaShop\PrestaShop\Core\Import\File\CsvFileReader;
use PrestaShop\PrestaShop\Core\Import\File\DataRow\DataRowInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Validate;

class SubscriptionHelper extends AbstractHelper
{
    private const HEADER_COLUMNS = [
        'id' => 0,
        'title' => 1,
        'customer_id' => 2,
        'status' => 3,
        'active' => 4,
        'first_delivery' => 5,
        'next_delivery' => 6,
        'id_periodicity' => 7,
        'last_delivery' => 8,
        'billing_id' => 9,
        'shipping_id' => 10,
        'card_token' => 11,
        'points' => 12,
        'id_carrier' => 13,
        'customer_message' => 14,
        'admin_message' => 15,
        'coupon_code' => 16,
    ];

    private const CARRIERS = [
        'Store Pickup' => [
            10221,
            10237,
            10238,
            10242,
            10343,
            10442,
        ],
        'DHL Global Mail' => [
            10236,
            10330,
            10331,
            10333,
            10336,
            10340,
        ],
        'UPS Next Day Air' => [
            20,
            10293,
            10300,
            10310,
            10324,
        ],
        'UPS Second Day Air' => [
            2,
            10245,
            10299,
        ],
        'UPS Ground' => [
            1,
            10294,
            10296,
            10301,
            10302,
            10306,
            10308,
            10325,
            10326,
            10327,
            10406,
        ],
        'UPS 3 Day Select' => [
            23,
        ],
        'USPS International Express' => [
            10240,
            57,
        ],
        'USPS Priority Mail' => [
            52,
            10292,
            10295,
            10298,
            10304,
            10305,
            10311,
            10430,
        ],
        'USPS Priority Express' => [
            50,
            10291,
            10297,
            10303,
            10312,
        ],
        'USPS Priority Mail International' => [
            155,
            10239,
            10241,
        ],
        'Platinum Customer Free Shipping' => [
            255,
            10309,
            10319,
        ],
        'Wellness Rewards Free Shipping' => [
            10328,
            10428,
        ],
        'DHL International' => [
            10334,
            10335,
            10337,
            10339,
            10344,
        ],
        'International Platinum Shipping (DHL)' => [
            256,
            10329,
            10332,
            10338,
        ],
        'POS' => [
            10345,
        ],
        'Standard Shipping' => [
            10218,
            10221,
            10236,
            10237,
            10238,
            10239,
            10240,
            10241,
            10242,
            10243,
            10244,
            10245,
            10246,
            10247,
            10248,
            10249,
            10250,
            10251,
            10252,
            10253,
            10254,
            10255,
            10256,
            10257,
            10258,
            10259,
            10260,
            10261,
            10262,
            10263,
            10264,
            10265,
            10266,
            10267,
            10268,
            10269,
            10270,
            10271,
            10272,
            10273,
            10274,
            10275,
            10276,
            10277,
            10278,
            10279,
            10280,
            10281,
            10282,
            10283,
            10284,
            10285,
            10286,
            10287,
            10288,
            10289,
            10290,
            10291,
            10292,
            10293,
            10294,
            10295,
            10296,
            10297,
            10298,
            10299,
            10300,
            10301,
            10302,
            10303,
            10304,
            10305,
            10306,
            10307,
            10308,
            10309,
            10310,
            10311,
            10312,
            10313,
            10314,
            10315,
            10316,
            10317,
            10318,
            10319,
            10320,
            10321,
            10322,
            10323,
            10324,
            10325,
            10326,
            10327,
            10328,
            10329,
            10330,
            10331,
            10332,
            10333,
            10334,
            10335,
            10336,
            10337,
            10338,
            10339,
            10340,
            10341,
            10342,
            10449,
            10450,
        ],
        'UPS Worldwide Economy' => [
            10445,
        ]
    ];

    /**
     * @var BraintreeOfficialVaultingHelper
     */
    private $braintreeOfficialVaultingHelper;

    /**
     * @var array
     */
    private $carrierMap = [];

    /**
     * @var CarrierReadModel
     */
    private $carrierReadModel;

    /**
     * @param CsvFileReader $csvFileReader
     * @param EntityManagerInterface $entityManager
     * @param CarrierReadModel $carrierReadModel
     */
    public function __construct(CsvFileReader $csvFileReader, EntityManagerInterface $entityManager, CarrierReadModel $carrierReadModel)
    {
        parent::__construct($csvFileReader, $entityManager);
        $this->carrierReadModel = $carrierReadModel;
        $this->braintreeOfficialVaultingHelper = new BraintreeOfficialVaultingHelper();
        $this->prepareCarrierData();
    }

    /**
     * {@inheritDoc}
     */
    public function processRow(DataRowInterface $dataCell, SymfonyStyle $io): void
    {
        $active = (int) $dataCell[self::HEADER_COLUMNS['active']]->getValue();

        if (0 === $active) {
            $io->writeln('Skipping non active subscription...');
        }

        $customerId = (int) $dataCell[self::HEADER_COLUMNS['customer_id']]->getValue();

        if (!Customer::existsInDatabase($customerId, 'customer')) {
            throw new DomainException(sprintf('Customer with ID:%d is not found in DB.', $customerId));
        }

        $customer = new Customer($customerId);
        $token = $dataCell[self::HEADER_COLUMNS['card_token']]->getValue();
        $sandbox = (int) Configuration::get('BRAINTREEOFFICIAL_SANDBOX');

        $braintreeCustomer = (new ServiceBraintreeOfficialCustomer())->loadCustomerByMethod($customerId, $sandbox);

        if (false === $braintreeCustomer) {
            $braintreeCustomer = $this->braintreeOfficialVaultingHelper->addExistedBraintreeCustomer($customerId, $token, $sandbox);
            $io->writeln(sprintf('Braintree customer was created with ID:%d.', $braintreeCustomer->id));
        }

        $vaultId = $this->braintreeOfficialVaultingHelper->getVaultIdByCustomerAndToken($customer, $token);

        if (null === $vaultId) {
            $vaulting = $this->braintreeOfficialVaultingHelper->createVaultingByToken($braintreeCustomer, $token);
            $io->writeln(sprintf('Braintree vaulting was created with ID:%d.', $vaulting->id));
        } else {
            $vaulting = new BraintreeOfficialVaulting($vaultId);
        }

        $billingId = (int) $dataCell[self::HEADER_COLUMNS['billing_id']]->getValue();

        if (!Address::existsInDatabase($billingId, 'address')) {
            throw new DomainException(sprintf('There is no address with ID:%d.', $billingId));
        }

        $shippingId = (int) $dataCell[self::HEADER_COLUMNS['shipping_id']]->getValue();

        if (!Address::existsInDatabase($shippingId, 'address')) {
            throw new DomainException(sprintf('There is no address with ID:%d.', $shippingId));
        }

        $carrierOldId = (int) $dataCell[self::HEADER_COLUMNS['id_carrier']]->getValue();

        if (isset($this->carrierMap[$carrierOldId])) {
            $carrierName = $this->carrierMap[$carrierOldId];
        } else {
            throw new DomainException(sprintf('There is no carrier with old ID:%d.', $carrierOldId));
        }

        $carrierReferenceId = $this->carrierReadModel->getCarrierReferenceIdByName($carrierName);
        $carrier = Carrier::getCarrierByReference($carrierReferenceId);

        if (false === $carrier) {
            throw new DomainException(sprintf('There is no carrier with reference ID:%d.', $carrierReferenceId));
        }

        $subscriptionOldId = (int) $dataCell[self::HEADER_COLUMNS['id']]->getValue();

        $subscription = $this->entityManager->getRepository(Subscription::class)->findOneBy([
            'oldId' => $subscriptionOldId,
        ]);

        if (null !== $subscription) {
            return;
        }

        $periodicity = $this->entityManager->getRepository(SubscriptionPeriodicity::class)->findOneBy([
            'oldId' => (int) $dataCell[self::HEADER_COLUMNS['id_periodicity']]->getValue(),
        ]);

        if (null === $periodicity) {
            throw new DomainException(sprintf('There is no periodicity with old ID:%d.', $customerId));
        }

        $cart = new Cart();
        $cart->id_customer = $customerId;
        $cart->id_carrier = $carrier->id;
        $cart->id_address_delivery = $shippingId;
        $cart->id_address_invoice = $billingId;
        $cart->secure_key = $customer->secure_key;
        $cart->id_shop = $customer->id_shop;
        $cart->id_shop_group = $customer->id_shop_group;
        $cart->id_lang = $customer->id_lang;
        $cart->id_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');

        if ($cart->add()) {
            $io->writeln(sprintf('The cart was created with ID:%d.', $cart->id));
        }

        $subscription = new Subscription($customerId, (int) $cart->id, $periodicity);
        $subscription->setOldId($subscriptionOldId);
        $subscription->setNextDelivery(
            \DateTimeImmutable::createFromFormat('!Y-m-d', $dataCell[self::HEADER_COLUMNS['next_delivery']]->getValue())
        );
        $customerMessage = $dataCell[self::HEADER_COLUMNS['customer_message']]->getValue();

        if (!empty($customerMessage)) {
            $subscription->setCustomerMessage($customerMessage);
        }

        $name = $dataCell[self::HEADER_COLUMNS['title']]->getValue();

        if (!empty($name)) {
            $subscription->setName($name);
        }

        $subscriptionPayment = new SubscriptionPayment(
            $subscription,
            $this->braintreeOfficialVaultingHelper->getModuleName(),
            (int) $vaulting->id_braintreeofficial_customer
        );

        $this->entityManager->persist($subscription);
        $this->entityManager->persist($subscriptionPayment);

        $io->writeln(sprintf('The subscription was created with old ID:%d.', $subscriptionOldId));
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

    /**
     * Preparing carriers' data.
     */
    private function prepareCarrierData(): void
    {
        foreach (self::CARRIERS as $carrierName => $carrierIds) {
            $this->carrierMap += array_fill_keys($carrierIds, $carrierName);
        }
    }
}
