<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service\VaultingHelper;

use BelVG\ProductSubscription\Interfaces\VaultingHelperInterface;
use Braintree\CreditCard;
use Braintree\Gateway;
use BraintreeOfficialAddons\classes\AbstractMethodBraintreeOfficial;
use BraintreeOfficialAddons\classes\BraintreeOfficialCustomer;
use BraintreeOfficialAddons\classes\BraintreeOfficialOrder;
use BraintreeOfficialAddons\classes\BraintreeOfficialVaulting;
use BraintreeOfficialAddons\services\ServiceBraintreeOfficialCustomer;
use BraintreeOfficialAddons\services\ServiceBraintreeOfficialOrder;
use BraintreeOfficialAddons\services\ServiceBraintreeOfficialVaulting;
use Configuration;
use Customer;
use PrestaShop\PrestaShop\Core\Domain\Exception\DomainException;
use Validate;

class BraintreeOfficialVaultingHelper implements VaultingHelperInterface
{
    public const MODULE_NAME = 'braintreeofficial';
    public const BRAINTREE_CARD_PAYMENT = 'card-braintree';

    public const TEST_CART_NUMBER = '4111111111111111';
    public const TEST_CART_EXPIRATION_DATE = '06/22';
    public const TEST_CART_CVV = '100';

    /**
     * @var ServiceBraintreeOfficialCustomer
     */
    private $serviceBraintreeOfficialCustomer;

    /**
     * @var int
     */
    private $vaultId;

    /**
     * @var Gateway
     */
    private $gateway;

    /**
     * @var \stdClass
     */
    private $methodBraintreeOfficial;

    /**
     * @var ServiceBraintreeOfficialVaulting
     */
    private $serviceBraintreeOfficialVaulting;

    public function __construct()
    {
        $this->serviceBraintreeOfficialCustomer = new ServiceBraintreeOfficialCustomer();
        $this->serviceBraintreeOfficialVaulting = new ServiceBraintreeOfficialVaulting();
        //$this->methodBraintreeOfficial = AbstractMethodBraintreeOfficial::load('BraintreeOfficial');
        $this->methodBraintreeOfficial = (new MethodBraintreeOfficialFactory())->create('BraintreeOfficial');
        $this->initialize();
    }

    /**
     * @param BraintreeOfficialOrder $braintreeOrder
     * @param int $customerId
     *
     * @return $this
     */
    public function process(BraintreeOfficialOrder $braintreeOrder, int $customerId): self
    {
        $transactionId = $braintreeOrder->id_transaction;
        $transaction = $this->gateway->transaction()->find($transactionId);
        $token = $transaction->creditCardDetails->token;
        $braintreeCustomer = $this->getBraintreeCustomer($customerId);
        $braintreeOfficialVaulting = $this->getBraintreeOfficialVaultingByCustomerAndToken($braintreeCustomer, $token);
        $this->vaultId = (int) $braintreeOfficialVaulting->id;

        return $this;
    }

    public function validate(array $values)
    {
        $this->methodBraintreeOfficial->setParameters($values);
        $this->methodBraintreeOfficial->validation();

        return $this->methodBraintreeOfficial->getDetailsTransaction();
    }

    /**
     * {@inheritDoc}
     */
    public function getVaultId(): int
    {
        return $this->vaultId;
    }

    /**
     * {@inheritDoc}
     */
    public function getModuleName(): string
    {
        return self::MODULE_NAME;
    }

    /**
     * {@inheritDoc}
     */
    public function getTokenByVaultId(int $vaultId): string
    {
        $vaulting = new BraintreeOfficialVaulting($vaultId);

        return $vaulting->token;
    }

    /**
     * @param int $customerId
     * @param string $method
     *
     * @return array
     */
    public function getPaymentMethods(int $customerId, string $method): array
    {
        $paymentMethods = $this->serviceBraintreeOfficialVaulting->getCustomerMethods($customerId, $method);

        foreach ($paymentMethods as $key => $method) {
            $nonce = $this->methodBraintreeOfficial->createMethodNonce($method['token']);
            $paymentMethods[$key]['nonce'] = $nonce;
        }

        return $paymentMethods;
    }

    /**
     * @param array $paymentMethods
     * @param string $token
     *
     * @return string|null
     */
    public function getNonceByToken(array $paymentMethods, string $token): ?string
    {
        foreach ($paymentMethods as $paymentMethod) {
            if ($token === $paymentMethod['token']) {
                return $paymentMethod['nonce'];
            }
        }

        return null;
    }

    /**
     * @return string
     */
    public function generateClientToken(): string
    {
        return $this->methodBraintreeOfficial->init();
    }

    /**
     * @param Customer $customer
     * @param string $token
     *
     * @return int|null
     */
    public function getVaultIdByCustomerAndToken(Customer $customer, string $token): ?int
    {
        $braintreeCustomer = $this->getBraintreeCustomer($customer->id);

        if (null === $braintreeCustomer) {
            return null;
        }

        $braintreeOfficialVaulting = $this->getBraintreeOfficialVaultingByCustomerAndToken($braintreeCustomer, $token);

        if (null === $braintreeOfficialVaulting) {
            return null;
        }

        return (int) $braintreeOfficialVaulting->id;
    }

    /**
     * @param string $transactionId
     *
     * @return int|null
     */
    public function getOrderIdByTransactionId(string $transactionId): ?int
    {
        $braintreeOfficialOrder = (new ServiceBraintreeOfficialOrder())->loadByTransactionId($transactionId);

        if (false === $braintreeOfficialOrder) {
            return null;
        }

        return (int) $braintreeOfficialOrder->id_order;
    }

    /**
     * @return Gateway
     */
    public function getGateway(): Gateway
    {
        return $this->gateway;
    }

    /**
     * Test mode setup.
     */
    public function setForceSandboxMode(): void
    {
        $environment = 'sandbox';
        $mode = 'SANDBOX';

        $this->initializeGateway($environment, $mode);
    }

    /**
     * @param int $customerId
     * @param string $token
     * @param int $sandbox
     *
     * @return BraintreeOfficialCustomer
     *
     * @throws DomainException
     */
    public function addExistedBraintreeCustomer(int $customerId, string $token, int $sandbox): BrainTreeOfficialCustomer
    {
        $customer = new Customer($customerId);
        $email = $customer->email;

        $braintreeResourceCollection = $this->gateway->customer()->search([
            \Braintree\CustomerSearch::email()->is($email),
            \Braintree\CustomerSearch::paymentMethodToken()->is($token),
        ]);

        $ids = $braintreeResourceCollection->getIds();

        if (0 === count($ids)) {
            throw new DomainException(sprintf('Braintree data is not found for customer with ID:%d.', $customerId));
        }

        if (1 < count($ids)) {
            throw new DomainException(sprintf('Customer with ID:%d has more then one reference.', $customerId));
        }

        $braintreeCustomer = new BrainTreeOfficialCustomer();
        $braintreeCustomer->id_customer = $customerId;
        $braintreeCustomer->reference = $ids[0];
        $braintreeCustomer->sandbox = $sandbox;
        $braintreeCustomer->profile_key = pSQL(md5($this->gateway->config->getMerchantId()));

        $braintreeCustomer->save();

        return $braintreeCustomer;
    }

    /**
     * @param Customer $customer
     * @param bool $isSandboxMode
     *
     * @return BraintreeOfficialCustomer
     */
    public function createBraintreeCustomer(Customer $customer, bool $isSandboxMode): BrainTreeOfficialCustomer
    {
        $data = array(
            'firstName' => $customer->firstname,
            'lastName' => $customer->lastname,
            'email' => $customer->email
        );

        $result = $this->gateway->customer()->create($data);
        $profileKey = md5($this->gateway->config->getMerchantId());

        $brainTreeCustomer = new BrainTreeOfficialCustomer();
        $brainTreeCustomer->id_customer = $customer->id;
        $brainTreeCustomer->reference = $result->customer->id;
        $brainTreeCustomer->sandbox = (int) $isSandboxMode;
        $brainTreeCustomer->profile_key = pSQL($profileKey);
        $brainTreeCustomer->save();

        return $brainTreeCustomer;
    }

    /**
     * @param BraintreeOfficialCustomer $braintreeOfficialCustomer
     * @param string $token
     *
     * @return BraintreeOfficialVaulting
     */
    public function createVaultingByToken(BrainTreeOfficialCustomer $braintreeOfficialCustomer, string $token): BraintreeOfficialVaulting
    {
        $creditCard = $this->gateway->creditCard()->find($token);

        $vaulting = new BraintreeOfficialVaulting();
        $vaulting->id_braintreeofficial_customer = $braintreeOfficialCustomer->id;
        $vaulting->payment_tool = self::BRAINTREE_CARD_PAYMENT;
        $vaulting->token = $token;

        $vaulting->info = $this->getVaultingInfo($creditCard);

        $vaulting->save();

        return $vaulting;
    }

    /**
     * @param BraintreeOfficialCustomer $braintreeOfficialCustomer
     *
     * @return BraintreeOfficialVaulting
     */
    public function createSandboxVaulting(BrainTreeOfficialCustomer $braintreeOfficialCustomer): BraintreeOfficialVaulting
    {
        $result = $this->gateway->creditCard()->create([
            'customerId' => $braintreeOfficialCustomer->reference,
            'number' => self::TEST_CART_NUMBER,
            'expirationDate' => self::TEST_CART_EXPIRATION_DATE,
            'cvv' => self::TEST_CART_CVV,
        ]);

        $creditCard = $result->creditCard;

        $vaulting = new BraintreeOfficialVaulting();
        $vaulting->id_braintreeofficial_customer = $braintreeOfficialCustomer->id;
        $vaulting->payment_tool = self::BRAINTREE_CARD_PAYMENT;
        $vaulting->token = $creditCard->token;
        $vaulting->info = $this->getVaultingInfo($creditCard);

        $vaulting->save();

        return $vaulting;
    }

    /**
     * @param int $customerId
     *
     * @return BraintreeOfficialCustomer|null
     */
    private function getBraintreeCustomer(int $customerId): ?BraintreeOfficialCustomer
    {
        $braintreeCustomer = $this->serviceBraintreeOfficialCustomer->loadCustomerByMethod(
            $customerId, (int) Configuration::get('BRAINTREEOFFICIAL_SANDBOX')
        );

        if (false === $braintreeCustomer) {
            return null;
        }

        return $braintreeCustomer;
    }

    /**
     * @param BraintreeOfficialCustomer $braintreeCustomer
     * @param string $token
     *
     * @return BraintreeOfficialVaulting|null
     */
    private function getBraintreeOfficialVaultingByCustomerAndToken(BraintreeOfficialCustomer $braintreeCustomer, string $token): ?BraintreeOfficialVaulting
    {
        $collection = new \PrestaShopCollection(BraintreeOfficialVaulting::class);
        $collection->where('token', '=', pSQL($token));
        $collection->where('id_braintreeofficial_customer', '=', (int) $braintreeCustomer->id);
        $braintreeOfficialVaulting = $collection->getFirst();

        if (false === $braintreeOfficialVaulting) {
            return null;
        }

        return $braintreeOfficialVaulting;
    }

    /**
     * @param BraintreeOfficialCustomer $braintreeCustomer
     *
     * @return BraintreeOfficialVaulting|null
     *
     * @throws DomainException
     */
    public function getBraintreeOfficialVaultingByCustomer(BraintreeOfficialCustomer $braintreeCustomer): ?BraintreeOfficialVaulting
    {
        $collection = new \PrestaShopCollection(BraintreeOfficialVaulting::class);
        $collection->where('id_braintreeofficial_customer', '=', (int) $braintreeCustomer->id);
        $result = $collection->getResults();

        if (empty($result)) {
            return null;
        }

        if (1 === count($result)) {
            return $result[0];
        }

        throw new DomainException(sprintf('The Braintree customer with ID:%d has more than one vaulting.', $braintreeCustomer->id));
    }

    /**
     * @param BraintreeOfficialVaulting $vaulting
     *
     * @return bool
     */
    public function isSandboxVaulting(BraintreeOfficialVaulting $vaulting): bool
    {
        $braintreeOfficialCustomer = new BraintreeOfficialCustomer($vaulting->id_braintreeofficial_customer);

        if (!Validate::isLoadedObject($braintreeOfficialCustomer)) {
            return false;
        }

        return 1 === (int) $braintreeOfficialCustomer->sandbox;
    }

    /**
     * Gateway initializing
     */
    private function initialize(): void
    {
        $environment = Configuration::get('BRAINTREEOFFICIAL_SANDBOX') ? 'sandbox' : 'production';
        $mode = Configuration::get('BRAINTREEOFFICIAL_SANDBOX') ? 'SANDBOX' : 'LIVE';

        $this->initializeGateway($environment, $mode);
    }

    /**
     * @param string $environment
     * @param string $mode
     */
    private function initializeGateway(string $environment, string $mode): void
    {
        $this->gateway = new Gateway([
            'environment' => $environment,
            'publicKey' => Configuration::get('BRAINTREEOFFICIAL_PUBLIC_KEY_' . $mode),
            'privateKey' => Configuration::get('BRAINTREEOFFICIAL_PRIVATE_KEY_' . $mode),
            'merchantId' => Configuration::get('BRAINTREEOFFICIAL_MERCHANT_ID_' . $mode),
        ]);
    }

    /**
     * @param CreditCard $creditCard
     *
     * @return string
     */
    private function getVaultingInfo(CreditCard $creditCard): string
    {
        $info = $creditCard->cardType.': *';
        $info .= $creditCard->last4.' ';
        $info .= $creditCard->expirationMonth.'/';
        $info .= $creditCard->expirationYear;

        return $info;
    }
}
