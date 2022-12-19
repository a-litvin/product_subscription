<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Command;

use BelVG\ProductSubscription\Entity\Subscription;
use BelVG\ProductSubscription\Entity\SubscriptionHistory;
use BelVG\ProductSubscription\ReadModel\SubscriptionReadModel;
use BelVG\ProductSubscription\Service\Cart\CartContextService;
use BelVG\ProductSubscription\Service\Cart\CartRuleService;
use BelVG\ProductSubscription\Service\RedeemPointsService;
use BelVG\ProductSubscription\Service\SubscriptionOrderCartService;
use BelVG\ProductSubscription\Service\VaultingHelper\BraintreeOfficialVaultingHelper;
use BraintreeOfficialAddons\classes\BraintreeOfficialVaulting;
use BraintreeOfficialAddons\services\ServiceBraintreeOfficialCustomer;
use BelVG\ProductSubscription\Interfaces\Command\Report\ReportSenderInterface;
use BelVG\ProductSubscription\Service\Command\Report\Item;
use Cart;
use Configuration;
use Context;
use Currency;
use Customer;
use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\PrestaShop\Core\Domain\Exception\DomainException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Tools;

class CronCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'productsubscription:cron';

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var BraintreeOfficialVaultingHelper
     */
    private $braintreeOfficialVaultingHelper;

    /**
     * @var Context
     */
    private $context;
    /**
     * @var SubscriptionOrderCartService
     */
    private $shipNowService;

    /**
     * @var RedeemPointsService
     */
    private $redeemPointsService;

    /**
     * @var SubscriptionReadModel
     */
    private $subscriptionReadModel;

    /**
     * @var bool
     */
    private $sandboxMode;

    /**
     * @var CartRuleService
     */
    private $cartRuleService;

    /**
     * @var ReportSenderInterface
     */
    private $reportSender;

    /**
     * @param EntityManagerInterface $entityManager
     * @param SubscriptionOrderCartService $shipNowService
     * @param SubscriptionReadModel $subscriptionReadModel
     * @param CartRuleService $cartRuleService
     * @param ReportSenderInterface $reportSender
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        SubscriptionOrderCartService $shipNowService,
        SubscriptionReadModel $subscriptionReadModel,
        CartRuleService $cartRuleService,
        ReportSenderInterface $reportSender
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->shipNowService = $shipNowService;
        $this->subscriptionReadModel = $subscriptionReadModel;
        $this->braintreeOfficialVaultingHelper = new BraintreeOfficialVaultingHelper();
        $this->context = Context::getContext();
        $this->redeemPointsService = new RedeemPointsService($this->context);
        $this->cartRuleService = $cartRuleService;
        $this->reportSender = $reportSender;
    }

    /**
     * {@inheritDoc}
     */
    public function configure()
    {
        $this->addOption('production', null, InputOption::VALUE_OPTIONAL, 'Is production mode?', false);
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $productionMode = 'true' === $input->getOption('production');

        if (true === $productionMode) {
            $this->sandboxMode = (bool) Configuration::get('BRAINTREEOFFICIAL_SANDBOX');
            $titleText = $this->sandboxMode ? 'Sandbox mode.' : 'Production mode.';
            $io->title($titleText);
        } else {
            $this->sandboxMode = true;
            $this->braintreeOfficialVaultingHelper->setForceSandboxMode();
            $io->title('Forced sandbox mode.');
        }

        $io->title('Starting...');

        $now = new \DateTimeImmutable('today');
        $activeSubscriptionIds = $this->subscriptionReadModel->getActiveIdsByDateDelivery($now);

        if (empty($activeSubscriptionIds)) {
            $io->title('There are no active subscriptions by today.');
            $this->makeReportDisableSubscription($input, $output);
            $this->reportSender->send($this->context->getTranslator()->trans('There are no active subscriptions by today.'));
            return;
        }

        foreach ($activeSubscriptionIds as $id) {
            $io->writeln(sprintf('Processing auto shipment for subscription with ID %s...', $id));

            try {
                /** @var Subscription $subscription */
                $subscription = $this->entityManager->getRepository(Subscription::class)->getActiveWithAssociations((int)$id);
            } catch (DomainException $exception) {
                $io->writeln($exception->getMessage());
                $this->reportSender->addReportItem($subscription, Item::FAILED, $exception->getMessage());

                continue;
            }

            $message = $this->initContext($subscription);

            if (null !== $message) {
                $io->writeln($message);
                $this->reportSender->addReportItem($subscription, Item::FAILED, $message);

                continue;
            };

            $customer = new Customer($subscription->getCustomerId());
            if(empty($customer->id)){
                continue;
            }
            $success = true;

            if (true === $this->sandboxMode) {
                $braintreeSandboxCustomer = (new ServiceBraintreeOfficialCustomer())->loadCustomerByMethod($customer->id, $this->sandboxMode);

                if (false === $braintreeSandboxCustomer) {
                    $braintreeSandboxCustomer = $this->braintreeOfficialVaultingHelper->createBraintreeCustomer($customer, $this->sandboxMode);
                    $io->writeln(sprintf('Braintree sandbox customer was created with ID:%d.', $braintreeSandboxCustomer->id));
                }

                try {
                    $vaulting = $this->braintreeOfficialVaultingHelper->getBraintreeOfficialVaultingByCustomer($braintreeSandboxCustomer);
                } catch (DomainException $exception) {
                    $vaulting = new BraintreeOfficialVaulting($subscription->getSubscriptionPayment()->getVaultId());

                    if (!$this->braintreeOfficialVaultingHelper->isSandboxVaulting($vaulting)) {
                        $io->writeln($exception->getMessage());
                        $this->reportSender->addReportItem($subscription, Item::FAILED, $exception->getMessage());
                        continue;
                    }
                }

                if (null === $vaulting) {
                    $vaulting = $this->braintreeOfficialVaultingHelper->createSandboxVaulting($braintreeSandboxCustomer);
                    $io->writeln(sprintf('Braintree sandbox vaulting was created with ID:%d.', $vaulting->id));
                }

                $token = $vaulting->token;
            } else {
                $token = $this->braintreeOfficialVaultingHelper->getTokenByVaultId($subscription->getSubscriptionPayment()->getVaultId());
            }

            $paymentMethods = $this->braintreeOfficialVaultingHelper->getPaymentMethods(
                $subscription->getCustomerId(),
                BraintreeOfficialVaultingHelper::BRAINTREE_CARD_PAYMENT
            );
            $nonce = $this->braintreeOfficialVaultingHelper->getNonceByToken($paymentMethods, $token);

            try {
                $result = $this->braintreeOfficialVaultingHelper->validate([
                    'payment_method_nonce' => $nonce,
                    'payment_method_bt' => BraintreeOfficialVaultingHelper::BRAINTREE_CARD_PAYMENT,
                    'bt_vaulting_token' => $token,
                ]);
            } catch (\Throwable $exception) {
                $io->error(sprintf(
                    'Can not create auto ship for subscription with ID:%s. An exception has occurred: %s.',
                    $subscription->getId(),
                    $exception->getMessage()
                ));
                $success = false;
                $this->reportSender->addReportItem($subscription, Item::FAILED, $exception->getMessage());
            }

            if (true === $success) {
                $this->shipNowService->updateSubscriptionAfterShipment($subscription);
                $orderId = $this->braintreeOfficialVaultingHelper->getOrderIdByTransactionId($result['transaction_id']);
                $this->shipNowService->addMessageToOrder(
                    $subscription,
                    $orderId,
                    $this->context
                );

                if ($this->redeemPointsService->isRedeemPointsApplied($subscription)) {
                    $subscriptionCart = new Cart($subscription->getCartId());
                    $this->redeemPointsService->setPointsRedeem(0, $subscriptionCart);
                }

                $subscriptionHistory = new SubscriptionHistory($subscription, $orderId);
                $this->entityManager->persist($subscriptionHistory);
                $this->entityManager->flush();

                $io->success(sprintf(
                    'The order with ID:%s is created according to the subscription with ID:%s.',
                    $orderId,
                    $subscription->getId()
                ));
                $this->reportSender->addReportItem($subscription, Item::SUCCESSFUL);
            }

            $this->entityManager->clear();
            unset($subscription);
            (new CartContextService())->clearCookie();
        }

        $this->makeReportDisableSubscription($input, $output);

        $io->title('Cron job has done!');
        $this->reportSender->send('New order(s) in auto ships');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function makeReportDisableSubscription(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable('today');
        $allSubscriptionIds = $this->subscriptionReadModel->getAllIdsByDateDelivery($now);
        foreach ($allSubscriptionIds as $id) {

            try {
                /** @var Subscription $subscription */
                $subscription = $this->entityManager->getRepository(Subscription::class)->findWithAssociations((int)$id);
            } catch (DomainException $exception) {
                continue;
            }
            $customer = new Customer($subscription->getCustomerId());
            if(empty($customer->id)){
                continue;
            }

            if (!$subscription->isActive()) {
                $io->writeln(sprintf('Reporting disable subscription with ID %s...', $id));
                $this->reportSender->addReportItem($subscription, Item::FAILED, "Disable more than 1 day");

            }

        }
    }

    /**
     * @param Subscription $subscription
     *
     * @return string|null
     */
    private function initContext(Subscription $subscription): ?string
    {
        if (!$subscription->isActive()){
            return sprintf("The subscription with %d is disabled.", $subscription->getId());
        }

        $subscriptionCart = new Cart($subscription->getCartId());

        if (!$subscriptionCart->hasProducts()) {
            $this->shipNowService->removeSubscription($subscription);

            return sprintf("The subscription with %d doesn't have products. Removed.", $subscription->getId());
        }

        // temporarily. Will be replaced by cloned cart in CartContextService.
        $this->context->cart = $subscriptionCart;

        $currency = Tools::setCurrency($this->context->cookie);
        $this->context->currency = $currency;
        $customer = new Customer($subscription->getCustomerId());
        $customer->logged = 1;
        $this->context->customer = $customer;
        $this->context->employee = new \Employee(0);
        $this->context->shop = new \Shop($this->context->customer->id_shop);


        $clonedSubscriptionCart = $this->shipNowService->getClonedOrderCart($subscription);

        if (!$clonedSubscriptionCart->hasProducts()) {
            $this->shipNowService->updateSubscriptionAfterShipment($subscription);

            return sprintf("The auto shipment for subscription with %d doesn't have products. Skipped.", $subscription->getId());
        }

        $this->redeemPointsService->copyRedeemPoints($subscriptionCart, $clonedSubscriptionCart);
        $this->cartRuleService->moveCartRules($subscriptionCart, $clonedSubscriptionCart);

        return null;
    }
}
