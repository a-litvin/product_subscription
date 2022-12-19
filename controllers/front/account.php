<?php

declare(strict_types=1);

use BelVG\ProductSubscription\DTO\ProductDTO;
use BelVG\ProductSubscription\Entity\Subscription;
use BelVG\ProductSubscription\Entity\SubscriptionBlockingReason;
use BelVG\ProductSubscription\Entity\SubscriptionPeriodicity;
use BelVG\ProductSubscription\Entity\SubscriptionProduct;
use BelVG\ProductSubscription\ReadModel\PeriodicityReadModel;
use BelVG\ProductSubscription\Repository\SubscriptionRepository;
use BelVG\ProductSubscription\Service\Cart\CartCloneService;
use BelVG\ProductSubscription\Service\Cart\CartContextService;
use BelVG\ProductSubscription\Service\Cart\CartRuleService;
use BelVG\ProductSubscription\Service\CartPresenterService;
use BelVG\ProductSubscription\Service\GCOrderFormService;
use BelVG\ProductSubscription\Service\RedeemPointsService;
use BelVG\ProductSubscription\Service\SubscriptionAvailabilityService;
use BelVG\ProductSubscription\Service\SubscriptionOrderCartService;
use BelVG\ProductSubscription\Service\VaultingHelper\BraintreeOfficialVaultingHelper;
use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\PrestaShop\Core\Domain\Exception\DomainException;
use BraintreeOfficialAddons\services\ServiceBraintreeOfficialVaulting;

class ProductSubscriptionAccountModuleFrontController extends ModuleFrontController
{
    public const DUMMY_SHIPPING_COST = 'calculated at place order';

    /**
     * @var bool
     */
    public $auth = true;

    /**
     * @var bool
     */
    public $guestAllowed = false;

    /**
     * @var PeriodicityReadModel
     */
    private $periodicityReadModel;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var CartPresenterService
     */
    private $cartPresenterService;

    /* @var ServiceBraintreeOfficialVaulting */
    protected $serviceBraintreeOfficialVaulting;

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        parent::init();

        $this->periodicityReadModel = $this->container->get(PeriodicityReadModel::class);
        $this->em = $this->container->get('doctrine.orm.entity_manager');
        $this->cartPresenterService = new CartPresenterService();
        $this->serviceBraintreeOfficialVaulting = new ServiceBraintreeOfficialVaulting();
    }

    /**
     * {@inheritDoc}
     */
    public function initContent()
    {
        parent::initContent();

        $this->context->smarty->assign(
            array_merge(
                ['subscriptionsData' => $this->getTemplateData()],
                $this->getInitialTemplateData()
            )
        );

        $this->setTemplate('module:productsubscription/views/templates/front/account.tpl');
    }

    /**
     * {@inheritDoc}
     */
    public function setMedia()
    {
        parent::setMedia();

        $this->registerJavascript(
            'module-productsubscription-subscriptions-update',
            'modules/' . $this->module->name . '/views/js/front/update_subscriptions.js'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function postProcess()
    {
        parent::postProcess();

        if (!$this->validateAjaxRequest()) {
            return;
        }

        $cart = $this->context->cart;

        $method = Tools::getValue('action') . 'Ajax';

        if (!method_exists($this, $method)) {
            return;
        }

        $response = $this->$method();

        $cart->save();

        $this->ajaxResponse((array)$response);

        exit;
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();
        $page['body_classes']['page-customer-account'] = true;
        return $page;
    }

    /**
     * @return array
     */
    private function getTemplateData(): array
    {
        $customerId = $this->context->customer->id;

        $subscriptions = $this->em->getRepository(Subscription::class)->findAllWithAssociationsByCustomerId($customerId);
        $data = [];

        foreach ($subscriptions as $subscription) {
            $subscriptionTemplateData = $this->getSubscriptionTemplateData($subscription);

            if (empty($subscriptionTemplateData['products'])) {
                continue;
            }

            $data[] = $subscriptionTemplateData;
        }

        return $data;
    }

    /**
     * @return array
     */
    private function getInitialTemplateData(): array
    {
        return [
            'periodicityList' => $this->periodicityReadModel->getAll(),
            'locale' => $this->context->currentLocale,
            'currencyCode' => $this->context->currency->iso_code,
        ];
    }

    /**
     * @param Subscription $subscription
     *
     * @return array
     */
    private function getSubscriptionTemplateData(Subscription $subscription, int $cloneCartId = 0): array
    {
        if (empty($cloneCartId))
        {
            $cartId = $subscription->getCartId();
            $cart = new Cart($cartId);
        } else {
            $cart = new Cart($cloneCartId);
            $cartId = $cloneCartId;
            $subscriptionProducts = $subscription->getSubscriptionProducts();
            foreach ($subscriptionProducts as $subscriptionProduct) {
                if ($subscriptionProduct->isSkipNextShipmentOnly()) {
                    $productId = $subscriptionProduct->getProductId();
                    $cart->deleteProduct($productId);
                }
            }
        }

        $addressDelivery = new Address((int)$cart->id_address_delivery);
        $state = new State((int)($addressDelivery->id_state));
        $this->cartPresenterService->calculate($cart);
        $redeemPointsService = new RedeemPointsService($this->context);
        $cartRuleService = $this->container->get(CartRuleService::class);
        $paymentMethod = null;
        $paymentMethods = $this->serviceBraintreeOfficialVaulting->getCustomerGroupedMethods($this->context->customer->id);
        $methodVaultId = $subscription->getSubscriptionPayment()->getVaultId();
        foreach ($paymentMethods['card-braintree'] as $method) {
            if ($method['id_braintreeofficial_vaulting'] == $methodVaultId) $paymentMethod = $method;
        }
        $reduction = 0;
        $products = $cart->getProducts();
        foreach ($products as $product) {
            $reduction += ($product['price_without_reduction'] - $product['price_with_reduction']);
        }
        $points_redeemed = $redeemPointsService->getRedeemPointsApplied($subscription);

        return [
            'subscription' => $subscription,
            'pausedByCustomer' => $subscription->hasPausedByCustomerReason(),
            'products' => $products,
            'address_delivery' => $addressDelivery,
            'carrier' => new Carrier((int)$cart->id_carrier),
            'shippingAmount' => $this->cartPresenterService->getShippingAmount(),
            'shippingFree' => $this->cartPresenterService->getShippingFree(),
            'productsAmount' => $this->cartPresenterService->getProductsAmount(),
            'totalAmount' => $this->cartPresenterService->getTotalAmount(),
            'isRedeemPoints' => $redeemPointsService->isRedeemPointsApplied($subscription),
            'redeemPoints' => $points_redeemed,
            'cart_rule' => $cartRuleService->cartHasCartRule($cartId),
            'payment_method' => $paymentMethod,
            'reduction' => $reduction,
            'redeemPointsValue' => Tools::displayPrice(LRPDiscountHelper::getPointsMoneyValue($points_redeemed)),
            'state' => $state->name
        ];
    }

    /**
     * Periodicity update.
     */
    private function setPeriodicityAjax(): void
    {
        $subscription = $this->getSubscriptionById((int)Tools::getValue('idSubscription'));

        if (null === $subscription) {
            return;
        }

        $periodicity = $this->getPeriodicityById((int)Tools::getValue('value'));

        if (null === $periodicity) {
            return;
        }

        $subscription->setPeriodicity($periodicity);
        $this->em->flush();
    }

    /**
     * Next delivery day update.
     */
    private function setNextDeliveryAjax(): void
    {

        $dateString = substr(Tools::getValue('value'), 0, -2) . date("Y");
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $date = \DateTimeImmutable::createFromFormat('!m/d/Y', $dateString);
        $subscription->setNextDelivery($date);
        $this->em->flush();
    }

    /**
     * @return string[]|void
     */
    private function updateProductQuantityAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $productId = (int)Tools::getValue('idProduct');
        $productAttributeId = (int)Tools::getValue('idProductAttribute');
        $quantity = (int)Tools::getValue('value');

        if ($quantity < 1) {
            $this->errors['message'] = $this->trans(
                'The quantity of products must not be less than one.', [], 'Modules.Productsubscription.Account'
            );

            return;
        }

        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $cart = new Cart($subscription->getCartId());
        $productCartQuantity = $cart->getProductQuantity($productId, $productAttributeId)['quantity'];
        $difference = $quantity - $productCartQuantity;
        $operator = $difference > 0 ? 'up' : 'down';

        $cart->updateQty(
            abs($difference),
            $productId,
            $productAttributeId,
            0,
            $operator,
            (int)$cart->id_address_delivery,
            new Shop((int)$cart->id_shop),
            false,
            true
        );

        $this->cartPresenterService->calculate($cart);

        return [
            '#productsubscription-account-shipping-amount-' . $subscriptionId => self::DUMMY_SHIPPING_COST,
            '#productsubscription-account-subtotal-amount-' . $subscriptionId => $this->getFormattedPrice(
                $this->getTotalWithoutShippingAndTax($this->cartPresenterService)
            ),
            '#productsubscription-account-total-amount-' . $subscriptionId => $this->getFormattedPrice(
                $this->getTotalWithoutShippingAndTax($this->cartPresenterService)
            ),
        ];
    }

    /**
     * @return array|void
     */
    private function updateSubscriptionNameAjax()
    {
        $subscriptionName = trim(Tools::getValue('value', ''));

        if (empty($subscriptionName)) {
            $this->errors['message'] = $this->trans(
                'The Subscription name can not be blank.', [], 'Modules.Productsubscription.Account'
            );

            return;
        }

        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $subscription->setName($subscriptionName);
        $this->em->flush();

        return [
            '#account-subscription-name-' . $subscriptionId => $subscriptionName,
        ];
    }

    /**
     * @return array|void
     */
    private function displayMessageFormAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $this->context->smarty->assign([
            'message' => $subscription->getCustomerMessage() ?? '',
            'id_subscription' => $subscriptionId,
        ]);

        return [
            'form' => $this->context->smarty->fetch('module:productsubscription/views/templates/front/account-message.tpl')
        ];
    }

    /**
     * @return array|void
     */
    private function displayPaymentFormAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $cart = new Cart($subscription->getCartId());

        if ($subscription->hasReasons()) {
            $this->errors['message'] = $this->trans(
                'Subscription is blocked.', [], 'Modules.Productsubscription.Account'
            );

            return;
        }

        $subscriptionPayment = $subscription->getSubscriptionPayment();
        $paymentModule = $subscriptionPayment->getModuleName();

        if (!Module::isEnabled($paymentModule)) {
            $this->errors['message'] = $this->trans(
                'Payment module is not available.', [], 'Modules.Productsubscription.Account'
            );

            return;
        }

        if (!Configuration::get('BRAINTREEOFFICIAL_VAULTING')) {
            $this->errors['message'] = $this->trans(
                'Vaulting option is disabled.', [], 'Modules.Productsubscription.Account'
            );

            return;
        }

        /** @var SubscriptionOrderCartService $shipNowService */
        $shipNowService = $this->container->get(SubscriptionOrderCartService::class);
        $redeemPointsService = new RedeemPointsService($this->context);
        $cartRuleService = $this->container->get(CartRuleService::class);
        $subscriptionCart = new Cart($subscription->getCartId());

        $copySubscriptionCart = $shipNowService->getClonedOrderCart($subscription);

        if (!$copySubscriptionCart->hasProducts()) {
            $shipNowService->updateSubscriptionAfterShipment($subscription);
            $cartContextService = new CartContextService($this->context);
            $cartContextService->restoreCartContext();

            $this->errors['message'] = $this->trans(
                'There is no products for shipment.', [], 'Modules.Productsubscription.Account'
            );
            $this->errors['refresh'] = true;

            return;
        }

        $redeemPointsService->copyRedeemPoints($subscriptionCart, $copySubscriptionCart);
        $cartRuleService->moveCartRules($subscriptionCart, $copySubscriptionCart);

        $braintreeOfficialVaultingHelper = new BraintreeOfficialVaultingHelper();
        $clientToken = $braintreeOfficialVaultingHelper->generateClientToken();

        if (isset($clientToken['error_code'])) {
            $this->context->smarty->assign(array(
                'init_error' => $this->l('Error Braintree initialization ') . $clientToken['error_code'] . ' : ' . $clientToken['error_msg'],
            ));
        }

        $paymentMethods = $braintreeOfficialVaultingHelper->getPaymentMethods(
            $this->context->customer->id,
            BraintreeOfficialVaultingHelper::BRAINTREE_CARD_PAYMENT
        );
        $subscriptionToken = $braintreeOfficialVaultingHelper->getTokenByVaultId($subscriptionPayment->getVaultId());

        $this->cartPresenterService->calculate($cart);

        $redeemPointsService = new RedeemPointsService($this->context);
        $points_redeemed = $redeemPointsService->getRedeemPointsApplied($subscription);
        $subscriptionData = $this->getSubscriptionTemplateData($subscription, (int)$copySubscriptionCart->id);
        $periodicity = $subscription->getPeriodicity()->getId();
        $cookie = new Cookie('subscription_data');
        $cookie->setExpire(time() + 20 * 60);
        $cartProducts = $cart->getProducts(true);
        foreach ($cartProducts as $cartProduct) {
            $cookieName = 'product_'.$cartProduct['id_product'];
            $cookie->$cookieName = $periodicity;
        }

        $cookie->write();


        $this->context->smarty->assign([
            'active_vaulting' => true,
            'payment_methods' => $paymentMethods,
            'subscription_token' => $subscriptionToken,
            'error_msg' => Tools::getValue('bt_error_msg'),
            'braintreeToken' => $clientToken,
            'braintreeSubmitUrl' => $this->context->link->getModuleLink('braintreeofficial', 'validation', array(), true),
            'baseDir' => $this->context->link->getBaseLink($this->context->shop->id, true),
            'method_bt' => BRAINTREE_CARD_PAYMENT,
            'id_subscription' => $subscriptionId,
            'subscriptionData' => $subscriptionData,
            'locale' => $this->context->currentLocale,
            'currencyCode' => $this->context->currency->iso_code,
            'isPayment' => true,
            'total_tax' => $this->getTotalTax($this->cartPresenterService),
            'redeemPoints' => $points_redeemed,
            'autoshipNumber' => $subscriptionId,
            'isShippingFree' => $subscriptionData['shippingFree'],
            'shippingAmount' => $subscriptionData['shippingAmount'],
        ]);

        return [
            'form' => $this->context->smarty->fetch('module:productsubscription/views/templates/front/payment_bt.tpl'),
            'braintree' => true,
        ];
    }

    /**
     * @return array|void
     */
    private function displayPointsFormAjax()
    {
        if (!Module::isEnabled('loyaltyrewardpoints')) {
            $this->errors['message'] = $this->trans(
                'Loyalty reward points module is not available.', [], 'Modules.Productsubscription.Account'
            );

            return;
        }

        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);
        $redeemPointsService = new RedeemPointsService($this->context);

        if (null === $subscription) {
            return;
        }

        $this->context->smarty->assign([
            'id_subscription' => $subscriptionId,
            'customer_points' => $redeemPointsService->getCustomerPoints($subscription),
        ]);

        return [
            'form' => $this->context->smarty->fetch('module:productsubscription/views/templates/front/account-points.tpl'),
        ];
    }

    /**
     * @return array|void
     */
    private function displayPromoCodeFormAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $this->context->smarty->assign([
            'id_subscription' => $subscriptionId,
        ]);

        return [
            'form' => $this->context->smarty->fetch('module:productsubscription/views/templates/front/promo-code.tpl'),
        ];
    }

    /**
     * @return string[]|void
     */
    private function addPointsAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $formData = $this->extractFormData(json_decode(Tools::getValue('formData')));

        $redeemPoints = (int)$formData['redeem_points'];
        $cart = new Cart($subscription->getCartId());

        $redeemPointsService = new RedeemPointsService($this->context);

        try {
            $redeemPoints = $redeemPointsService->clearRedeemPoints($redeemPoints, $cart);
        } catch (DomainException $exception) {
            $this->errors['message'] = $this->trans(
                $exception->getMessage(), [], 'Modules.Productsubscription.Account'
            );

            return;
        }

        if ($redeemPoints > 0) {
            $redeemPointsService->setPointsRedeem($redeemPoints, $cart);
        }
        $points_redeemed = $redeemPointsService->getRedeemPointsApplied($subscription);
        $this->cartPresenterService->calculate($cart);

        return [
            '#productsubscription-account-total-amount-' . $subscriptionId => $this->getFormattedPrice(
                $this->getTotalWithoutShippingAndTax($this->cartPresenterService)
            ),
            '#productsubscription-account-redeempoints-amount-' . $subscriptionId => Tools::displayPrice(LRPDiscountHelper::getPointsMoneyValue($points_redeemed)),
            '#productsubscription-account-redeem-points-toggle-' . $subscriptionId => [
                'data-action' => 'clearPoints',
                'text' => $this->module->l('Clear redeem points'),
            ],
            '#productsubscription-account-redeem-points-remove-' . $subscriptionId => [
                'text' => $this->module->l('(remove)'),
            ],
            '#productsubscription-account-redeempoints-wrapper-' . $subscriptionId => [
                'class' => 'show',
            ],
        ];
    }

    private function addPromoCodeAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $formData = $this->extractFormData(json_decode(Tools::getValue('formData')));
        $promoCode = trim($formData['promo_code']);

        if (empty($promoCode)) {
            $this->errors['message'] = $this->trans(
                'You must enter a voucher code.', [], 'Shop.Notifications.Error'
            );

            return;
        }

        if (!Validate::isCleanHtml($promoCode)) {
            $this->errors['message'] = $this->trans(
                'The voucher code is invalid.', [], 'Shop.Notifications.Error'
            );

            return;
        }

        $cartRule = new CartRule(CartRule::getIdByCode($promoCode));

        if (!Validate::isLoadedObject($cartRule)) {
            $this->errors['message'] = $this->trans(
                'This voucher does not exist.', [], 'Shop.Notifications.Error'
            );

            return;
        }

        $contextCart = Validate::isLoadedObject($this->context->cart) ? $this->context->cart : null;

        $cart = new Cart($subscription->getCartId());
        $this->context->cart = $cart;

        $error = $cartRule->checkValidity($this->context, false, true);

        if (null !== $contextCart) {
            $this->context->cart = $contextCart;
        }

        if ($error) {
            $this->errors['message'] = $error;

            return;
        }

        $cart->addCartRule($cartRule->id);

        $this->cartPresenterService->calculate($cart);

        return [
            '#productsubscription-account-total-amount-' . $subscriptionId => $this->getFormattedPrice(
                $this->getTotalWithoutShippingAndTax($this->cartPresenterService)
            ),
            '#productsubscription-account-promo-code-toggle-' . $subscriptionId => [
                'data-action' => 'clearPromoCode',
                'text' => 'Clear promo code',
            ],
        ];
    }

    /**
     * @return array|void
     */
    private function clearPromoCodeAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $cartRuleService = $this->container->get(CartRuleService::class);
        $cart = new Cart($subscription->getCartId());
        $cartRuleService->removeCartRules($cart);

        $this->cartPresenterService->calculate($cart);

        return [
            '#productsubscription-account-total-amount-' . $subscriptionId => $this->getFormattedPrice(
                $this->getTotalWithoutShippingAndTax($this->cartPresenterService)
            ),
            '#productsubscription-account-promo-code-toggle-' . $subscriptionId => [
                'data-action' => 'displayPromoCodeForm',
                'text' => 'Apply promo code',
            ],
        ];
    }

    /**
     * @return array|void
     */
    private function clearPointsAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $cart = new Cart($subscription->getCartId());

        $redeemPointsService = new RedeemPointsService($this->context);
        $redeemPointsService->setPointsRedeem(0, $cart);

        $this->cartPresenterService->calculate($cart);

        return [
            '#productsubscription-account-total-amount-' . $subscriptionId => $this->getFormattedPrice(
                $this->cartPresenterService->getTotalAmount()
            ),
            '#productsubscription-account-redeem-points-toggle-' . $subscriptionId => [
                'data-action' => 'displayPointsForm',
                'text' => $this->module->l('Redeem Wellness Rewards Points'),
            ],
            '#productsubscription-account-redeempoints-wrapper-' . $subscriptionId => [
                'class' => 'hide',
            ],
        ];
    }

    /**
     * Returning real cart to context.
     */
    private function cancelPaymentFormAjax(): void
    {
        $cartContextService = new CartContextService($this->context);

        if (!$cartContextService->isShipNow()) {
            return;
        }

        $copySubscriptionCartId = $cartContextService->getCopySubscriptionCartId();
        $originSubscriptionId = $cartContextService->getOriginSubscriptionId();

        if (null !== $copySubscriptionCartId) {
            $redeemPointsService = new RedeemPointsService($this->context);
            $copySubscriptionCart = new Cart($copySubscriptionCartId);
            $redeemPointsService->setPointsRedeem(0, $copySubscriptionCart);
        }

        if (null !== $originSubscriptionId && null !== $copySubscriptionCartId) {
            $subscription = $this->em->getRepository(Subscription::class)->find($originSubscriptionId);
            $cartRuleService = $this->container->get(CartRuleService::class);
            $cartRuleService->moveCartRules($copySubscriptionCart, new Cart($subscription->getCartId()));
        }

        $cartContextService->restoreCartContext();
    }

    /**
     * @return array|void
     */
    private function displayQuickOrderFormAjax()
    {
        $moduleName = 'gcorderform';

        if (!Module::isEnabled($moduleName)) {
            $this->errors['message'] = $this->trans(
                'GcOrderForm module is not installed.', [], 'Modules.Productsubscription.Account'
            );

            return;
        }

        if (1 !== (int)Configuration::get('GCOF_TEMPLATE')) {
            $this->errors['message'] = $this->trans(
                'Please choose version 1 of template for GcOrderForm module.', [], 'Modules.Productsubscription.Account'
            );

            return;
        }

        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscriptionIndex = (int)Tools::getValue('indexSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $gcOrderFormService = $this->container->get(GCOrderFormService::class);
        $categoryIds = explode('-', Configuration::get('GCOF_CATEGORIES'));

        if (empty($categoryIds)) {
            $this->errors['message'] = $this->trans(
                'There is no available product categories for subscription.', [], 'Modules.Productsubscription.Account'
            );

            return;
        }

        $gcOrderFormCategories = $gcOrderFormService->toGCOrderFormFormat($categoryIds);

        $categoryOnload = Configuration::get('GCOF_CATEGORY_ON_LOAD', 0);
        $onlyStock = (bool)Configuration::get('GCOF_ONLY_STOCK');
        $gcOrderFormModule = Module::getInstanceByName($moduleName);

        $products = [];
        $selectedCategoryId = null;
        SubscriptionAvailabilityService::setForceDiscount(true);

        if ($categoryOnload != 0) {
            if ($categoryOnload == 1) {
                $products = $gcOrderFormService->getAvailableProductsOfCategory($gcOrderFormModule, $onlyStock);
            } else {
                $products = $gcOrderFormService->getAvailableProductsOfCategory($gcOrderFormModule, $onlyStock, (int)$categoryOnload);
                $selectedCategoryId = $categoryOnload;
            }
        }

        $controller_url = $this->context->link->getModuleLink($this->module->name, 'account');

        $this->context->smarty->assign([
            'gcof_psversion' => '17',
            'gcof_ssl' => (int)Tools::usingSecureMode(),
            'gcof_categories' => $gcOrderFormCategories,
            'gcof_quantity_buttons' => Configuration::get('GCOF_QUANTITY'),
            'gcof_empty' => Configuration::get('GCOF_EMPTY'),
            'gcof_image' => 0,
            'gcof_link' => Configuration::get('GCOF_LINK'),
            'gcof_image_size' => Image::getSize(Configuration::get('GCOF_THUMBNAIL')),
            'gcof_big_size' => Image::getSize(Configuration::get('GCOF_BIG_IMAGE')),
            'gcof_cat_size' => Image::getSize('order_form_cat_default'),
            'gcof_stock' => Configuration::get('GCOF_STOCK'),
            'gcof_products' => $products,
            'gcof_confirmtxt' => $gcOrderFormModule->l('You selected products, would you like to add them to the cart ?'),
            'gcof_img_dir' => Tools::getShopDomain(true) . __PS_BASE_URI__ . 'modules/' . $gcOrderFormModule->name . '/views/img/',
            'gcof_lang' => Context::getContext()->cookie->id_lang,
            'controller_url' => $controller_url,
            'gcof_picture' => Configuration::get('GCOF_PICTURE'),
            'gcof_onlyinstock' => Configuration::get('GCOF_ONLYINSTOCK'),
            'gcof_price' => Configuration::get('GCOF_PRICE'),
            'id_subscription' => $subscriptionId,
            'selectedCategoryId' => $selectedCategoryId,
            'subscriptionIndex' => $subscriptionIndex,
        ]);

        return [
            'form' => $this->context->smarty->fetch('module:productsubscription/views/templates/front/gcorderform.tpl')
        ];
    }

    /**
     * @return array
     */
    private function getProductsByCategoryAjax(): array
    {
        $categoryId = (int)Tools::getValue('idCategory');
        $onlyStock = (bool)Configuration::get('GCOF_ONLY_STOCK');

        $gcOrderFormModule = Module::getInstanceByName('gcorderform');
        $gcOrderFormService = $this->container->get(GCOrderFormService::class);

        SubscriptionAvailabilityService::setForceDiscount(true);

        return $gcOrderFormService->getAvailableProductsOfCategory($gcOrderFormModule, $onlyStock, $categoryId);
    }

    /**
     * Adding quick order products to subscription
     */
    private function addProductsToSubscriptionAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscriptionIndex = (int)Tools::getValue('indexSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId, 'findWithAssociations');

        if (null === $subscription) {
            return;
        }

        $products = json_decode(Tools::getValue('products'));

        $productDTOs = array_map(function ($item) {
            return new ProductDTO(
                $item->id_product,
                $item->id_product_attribute,
                $item->quantity
            );
        }, $products);

        $cartCloneService = $this->container->get(CartCloneService::class);
        $cart = new Cart($subscription->getCartId());

        foreach ($productDTOs as $productDTO) {
            $cartCloneService->updateCartProducts($cart, $productDTO);
        }

        $this->context->smarty->assign(
            array_merge(
                [
                    'subscriptionData' => $this->getSubscriptionTemplateData($subscription),
                ],
                $this->getInitialTemplateData()
            )
        );

        return [
            '#subscription-wrapper-' . $subscriptionId => $this->context->smarty->fetch('module:productsubscription/views/templates/front/_partials/subscription.tpl'),
        ];
    }

    /**
     * @return array|void
     */
    private function displayEditCardFormAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $subscriptionPayment = $subscription->getSubscriptionPayment();
        $paymentModule = $subscriptionPayment->getModuleName();

        if (!Module::isEnabled($paymentModule)) {
            $this->errors['message'] = $this->trans(
                'Payment module is not available.', [], 'Modules.Productsubscription.Account'
            );

            return;
        }

        if (!Configuration::get('BRAINTREEOFFICIAL_VAULTING')) {
            $this->errors['message'] = $this->trans(
                'Vaulting option is disabled.', [], 'Modules.Productsubscription.Account'
            );

            return;
        }

        $braintreeOfficialVaultingHelper = new BraintreeOfficialVaultingHelper();
        $paymentMethods = $braintreeOfficialVaultingHelper->getPaymentMethods(
            $this->context->customer->id,
            BraintreeOfficialVaultingHelper::BRAINTREE_CARD_PAYMENT
        );
        $subscriptionToken = $braintreeOfficialVaultingHelper->getTokenByVaultId($subscriptionPayment->getVaultId());

        $this->context->smarty->assign([
            'active_vaulting' => true,
            'payment_methods' => $paymentMethods,
            'subscription_token' => $subscriptionToken,
            'baseDir' => $this->context->link->getBaseLink($this->context->shop->id, true),
            'id_subscription' => $subscriptionId,
            'isCreditCardForm' => true
        ]);

        return [
            'form' => $this->context->smarty->fetch('module:productsubscription/views/templates/front/edit_card.tpl')
        ];
    }

    public function updateCreditCardAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $formData = $this->extractFormData(json_decode(Tools::getValue('formData')));
        $cardToken = $formData['bt_vaulting_token'];

        if (empty($cardToken)) {
            return;
        }

        $customer = $this->context->customer;
        $braintreeOfficialVaultingHelper = new BraintreeOfficialVaultingHelper();
        $vaultId = $braintreeOfficialVaultingHelper->getVaultIdByCustomerAndToken($customer, $cardToken);

        if (null !== $vaultId && $vaultId !== $subscription->getSubscriptionPayment()->getVaultId()) {
            $subscription->getSubscriptionPayment()->setVaultId($vaultId);

            $this->em->flush();
        }

        // Remove when Kostya make better
        $paymentMethod = null;
        $paymentMethods = $this->serviceBraintreeOfficialVaulting->getCustomerGroupedMethods($this->context->customer->id);
        $methodVaultId = $subscription->getSubscriptionPayment()->getVaultId();
        foreach ($paymentMethods['card-braintree'] as $method) {
            if ($method['id_braintreeofficial_vaulting'] == $methodVaultId) $paymentMethod = $method;
        }

        return [
            '#subscription-billing-payment-method-' . $subscriptionId => $paymentMethod['info'],
        ];

    }

    /**
     * @return array|void
     */
    private function updateMessageAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $formData = $this->extractFormData(json_decode(Tools::getValue('formData')));
        $message = $formData['message'];

        $shortMessage = $message;

        if (strlen($message) >= 10) {
            $shortMessage = Tools::substr($message, 0, 10) . '…';
        }

        $subscription->setCustomerMessage($message);

        $this->em->flush();

        if (strlen($message) == 0) {
            return [
                '#productsubscription-account-message-' . $subscriptionId => '',
                '#productsubscription-account-message-edit-' . $subscriptionId => 'Add an order message',
            ];
        }

        return [
            '#productsubscription-account-message-' . $subscriptionId => 'Order message: ' . $shortMessage,
            '#productsubscription-account-message-edit-' . $subscriptionId => 'Edit',
        ];
    }

    /**
     * @return array|void
     */
    private function displayAddressesFormAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $customer = new Customer($subscription->getCustomerId());
        $cart = new Cart($subscription->getCartId());

        $this->context->smarty->assign([
            'addresses' => $customer->getAddresses($this->context->language->id),
            'shippingAddressId' => $cart->id_address_delivery,
            'id_subscription' => $subscriptionId,
        ]);

        return [
            'form' => $this->context->smarty->fetch('module:productsubscription/views/templates/front/account-addresses.tpl'),
        ];
    }

    /**
     * @return array|void
     */
    private function selectShippingAddressAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId, 'findWithBlockingReasons');

        if (null === $subscription) {
            return;
        }

        $formData = $this->extractFormData(json_decode(Tools::getValue('formData')));
        $shippingAddressId = (int)$formData['shipping-address-id'];

        $cart = new Cart($subscription->getCartId());
        $oldCarrierId = $cart->id_carrier;
        $cart->updateAddressId($cart->id_address_delivery, $shippingAddressId);

        $deliveryOptions = $cart->getDeliveryOptionList();
        $carrierIds = array_map(function ($carrier) {
            return trim($carrier, ',');
        }, array_keys($deliveryOptions[$shippingAddressId]??array()));

        if (in_array($oldCarrierId, $carrierIds)) {
            $carrierId = $oldCarrierId;
        } else {
            $carrierId = reset($carrierIds);
            if (!$carrierId) $carrierId = Configuration::get('PS_CARRIER_DEFAULT');
        }

        $cart->setDeliveryOption([
            $cart->id_address_delivery => $carrierId . ',',
        ]);

        if ($cart->update()) {
            $this->removeDeliveryAddressReason($subscription);
            $address = new Address($shippingAddressId);
            $carrier = new Carrier((int)trim($carrierId, ','));
            $state = new State((int)($address->id_state));
            $addressOther = "{$address->city} {$state->name} {$address->postcode} {$address->country}";

            return [
                '#productsubscription-account-shipping-address1-' . $subscriptionId => $address->address1,
                '#productsubscription-account-shipping-address2-' . $subscriptionId => $address->address2,
                '#productsubscription-account-shipping-address-other-' . $subscriptionId => $addressOther,
                '#productsubscription-account-subscription-status-' . $subscriptionId => $this->getSubscriptionStatus($subscription),
                '#productsubscription-account-carrier-name-' . $subscriptionId => $carrier->name,
                '#productsubscription-account-shipping-amount-' . $subscriptionId => self::DUMMY_SHIPPING_COST,
            ];
        }
    }

    /**
     * @return array
     */
    private function displayNewAddressFormAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');

        $this->context->smarty->assign([
            'addressForm' => $this->makeAddressForm(),
            'id_subscription' => $subscriptionId,
        ]);

        return [
            'form' => $this->context->smarty->fetch('module:productsubscription/views/templates/front/_partials/address-form.tpl'),
        ];
    }

    /**
     * @return array|void
     */
    private function addShippingAddressAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId, 'findWithBlockingReasons');

        if (null === $subscription) {
            return;
        }

        $addressForm = $this->makeAddressForm();
        $formData = $this->extractFormData(json_decode(Tools::getValue('formData')));
        $saved = $addressForm->fillWith($formData)->submit();

        if (true === $saved) {
            $shippingAddress = $addressForm->getAddress();

            $cart = new Cart($subscription->getCartId());
            $cart->updateAddressId($cart->id_address_delivery, $shippingAddress->id);
            $cart->setDeliveryOption([
                $cart->id_address_delivery => $cart->id_carrier . ','
            ]);

            $address = new Address($shippingAddress->id);
            $state = new State((int)$address->id_state);
            $addressOther = "{$address->city} {$state->name} {$address->postcode} {$address->country}";

            if ($cart->update()) {
                $this->removeDeliveryAddressReason($subscription);
                return [
                    '#productsubscription-account-shipping-address1-' . $subscriptionId => $address->address1,
                    '#productsubscription-account-shipping-address2-' . $subscriptionId => $address->address2,
                    '#productsubscription-account-shipping-address-other-' . $subscriptionId => $addressOther,
                    '#productsubscription-account-subscription-status-' . $subscriptionId => $this->getSubscriptionStatus($subscription),
                ];
            }
        }

        $this->context->smarty->assign([
            'addressForm' => $addressForm,
            'id_subscription' => $subscriptionId,
        ]);

        return [
            'form' => $this->context->smarty->fetch('module:productsubscription/views/templates/front/_partials/address-form.tpl'),
        ];
    }

    /**
     * @return array|void
     */
    private function displayCancelSubscriptionFormAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $this->context->smarty->assign([
            'id_subscription' => $subscriptionId,
        ]);

        return [
            'form' => $this->context->smarty->fetch('module:productsubscription/views/templates/front/cancel-subscription.tpl')
        ];
    }

    /**
     * @param Subscription $subscription
     * @param int $productId
     * @param int $productAttributeId
     *
     * @return array
     */
    private function displayCancelProductFormAjax(Subscription $subscription, int $productId, int $productAttributeId)
    {
        $this->context->smarty->assign([
            'id_subscription' => $subscription->getId(),
            'id_product' => $productId,
            'id_product_attribute' => $productAttributeId,
        ]);

        return [
            'form' => $this->context->smarty->fetch('module:productsubscription/views/templates/front/cancel-product.tpl')
        ];
    }

    /**
     * @param Subscription|null $subscription
     *
     * @return string[]|void
     */
    private function cancelSubscriptionAjax(Subscription $subscription = null)
    {
        $subscription = $subscription ?? $this->getSubscriptionById((int)Tools::getValue('idSubscription'), 'findWithHistories');

        if (null === $subscription) {
            return;
        }

        $subscriptionId = $subscription->getId();

        if ($subscription->hasHistories()) {
            $subscription->remove();
        } else {
            $this->em->remove($subscription);
        }

        $this->em->flush();

        return [
            '#subscription-wrapper-' . $subscriptionId => '',
        ];
    }

    /**
     * @return string[]|void
     */
    private function pauseSubscriptionAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $this->addPauseByCustomerReason($subscription);

        return [
            '#productsubscription-account-subscription-status-' . $subscriptionId => $this->getSubscriptionStatus($subscription),
            '#productsubscription-account-pause-subscription-toggle-' . $subscriptionId => [
                'data-action' => 'unpauseSubscription',
                'text' => 'Unpause Autoship',
            ],
        ];
    }

    /**
     * @return array|void
     */
    private function unpauseSubscriptionAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $this->removePauseByCustomerReason($subscription);

        return [
            '#productsubscription-account-subscription-status-' . $subscriptionId => $this->getSubscriptionStatus($subscription),
            '#productsubscription-account-pause-subscription-toggle-' . $subscriptionId => [
                'data-action' => 'pauseSubscription',
                'text' => 'Pause Autoship'
            ],
        ];
    }

    /**
     * @return array|void
     */
    private function displayDeliveryFormAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $cart = new Cart($subscription->getCartId());
        $deliveryAddressId = $cart->id_address_delivery;

        $deliveryOptions = $cart->getDeliveryOptionList();

        $this->context->smarty->assign([
            'id_language' => $this->context->language->id,
            'id_subscription' => $subscriptionId,
            'id_address' => $deliveryAddressId,
            'delivery_options' => $deliveryOptions[$deliveryAddressId],
            'delivery_option' => $cart->getDeliveryOption()[$deliveryAddressId],
            'locale' => $this->context->currentLocale,
            'currencyCode' => $this->context->currency->iso_code,
        ]);

        return [
            'form' => $this->context->smarty->fetch('module:productsubscription/views/templates/front/account-delivery.tpl')
        ];
    }

    /**
     * @return array|void
     */
    private function selectDeliveryAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $formData = $this->extractFormData(json_decode(Tools::getValue('formData')));

        $cart = new Cart($subscription->getCartId());
        $carrierId = $formData['delivery_option'];
        $cart->setDeliveryOption([
            $cart->id_address_delivery => $carrierId . ','
        ]);

        if (!$cart->update()) {
            return;
        }

        $this->cartPresenterService->calculate($cart);
        $carrier = new Carrier((int)$carrierId);

        $isFree = $this->cartPresenterService->getShippingFree();

        return [
            '#productsubscription-account-shipping-amount-' . $subscriptionId => self::DUMMY_SHIPPING_COST,
            '#productsubscription-account-total-amount-' . $subscriptionId => $this->getFormattedPrice($this->cartPresenterService->getTotalAmount())
            ,
            '#productsubscription-account-subtotal-amount-' . $subscriptionId => $this->getFormattedPrice(
                $this->getTotalWithoutShippingAndTax($this->cartPresenterService)
            ),
            '#productsubscription-account-carrier-is-shippingfree-' . $subscriptionId => ($isFree) ? $this->module->l('-FREE') : ''
            ,
            '#productsubscription-account-carrier-name-' . $subscriptionId => $carrier->name,
        ];
    }

    /**
     * Product actions.
     */
    private function editProductAjax()
    {
        $subscriptionId = (int)Tools::getValue('idSubscription');
        $subscription = $this->getSubscriptionById($subscriptionId);

        if (null === $subscription) {
            return;
        }

        $method = Tools::getValue('value') . 'Ajax';

        if (!method_exists($this, $method)) {
            return;
        }

        $productId = (int)Tools::getValue('idProduct');
        $productAttributeId = (int)Tools::getValue('idProductAttribute');

        return $this->$method($subscription, $productId, $productAttributeId);
    }

    /**
     * @param Subscription $subscription
     * @param int $productId
     * @param int $productAttributeId
     *
     * @return string[]|void
     */
    private function cancelProductAjax(Subscription $subscription, int $productId, int $productAttributeId)
    {
        $cart = new Cart($subscription->getCartId());

        if (!$cart->deleteProduct($productId, $productAttributeId)) {
            return;
        }

        $products = $cart->getProducts();

        if (empty($products)) {
            return $this->cancelSubscriptionAjax($subscription);
        }

        $this->cartPresenterService->calculate($cart);
        $subscriptionId = $subscription->getId();

        return [
            '#productsubscription-account-product-' . $subscriptionId . '-' . $productId . '-' . $productAttributeId => [
                'class' => 'removed-product',
                'text' => ''
            ],
            '#productsubscription-account-shipping-amount-' . $subscriptionId => self::DUMMY_SHIPPING_COST,
            '#productsubscription-account-subtotal-amount-' . $subscriptionId => $this->getFormattedPrice(
                $this->getTotalWithoutShippingAndTax($this->cartPresenterService)
            ),
            '#productsubscription-account-total-amount-' . $subscriptionId => $this->getFormattedPrice(
                $this->getTotalWithoutShippingAndTax($this->cartPresenterService)
            ),
        ];
    }

    /**
     * @param Subscription $subscription
     * @param int $productId
     * @param int $productAttributeId
     */
    private function setProductNextOnlyAjax(Subscription $subscription, int $productId, int $productAttributeId): void
    {
        $subscriptionProduct = $this->em
            ->getRepository(SubscriptionProduct::class)
            ->getOrCreate($subscription, $productId, $productAttributeId);

        $subscriptionProduct->setNextShipmentOnly();

        $this->em->flush();
    }

    /**
     * @param Subscription $subscription
     * @param int $productId
     * @param int $productAttributeId
     */
    private function setProductNextSkipAjax(Subscription $subscription, int $productId, int $productAttributeId): void
    {
        $subscriptionProduct = $this->em
            ->getRepository(SubscriptionProduct::class)
            ->getOrCreate($subscription, $productId, $productAttributeId);

        $subscriptionProduct->skipNextShipmentOnly();

        $this->em->flush();
    }

    /**
     * @param Subscription $subscription
     * @param int $productId
     * @param int $productAttributeId
     */
    private function setProductScheduledAjax(Subscription $subscription, int $productId, int $productAttributeId): void
    {
        $subscriptionProduct = $this->em
            ->getRepository(SubscriptionProduct::class)
            ->getOrCreate($subscription, $productId, $productAttributeId);

        $subscriptionProduct->setScheduled();

        $this->em->flush();
    }

    /**
     * @return bool
     */
    private function validateAjaxRequest(): bool
    {
        if (!$this->ajax
            || !Tools::getIsset('action')
            || !Tools::getIsset('idSubscription')
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param array $response
     *
     * @return mixed
     */
    private function ajaxResponse(array $response)
    {
        $success = true;

        if (!empty($this->errors)) {
            $success = false;
        }

        $this->ajaxRender(
            json_encode(
                array_merge($response, $this->errors, ['success' => $success])
            )
        );
    }

    /**
     * @param int $id
     *
     * @param string $repoMethod
     * @return Subscription|null
     */
    private function getSubscriptionById(int $id, string $repoMethod = 'find'): ?Subscription
    {
        $subscription = null;

        if (method_exists(SubscriptionRepository::class, $repoMethod)) {
            $subscription = $this->em->getRepository(Subscription::class)->$repoMethod($id);
        }

        if (null === $subscription) {
            $this->errors['message'] = $this->trans(
                sprintf('Subscription with ID:%s is not found.', $id), [], 'Modules.Productsubscription.Account'
            );

            return null;
        }

        return $subscription;
    }

    /**
     * @param int $id
     *
     * @return SubscriptionPeriodicity|null
     */
    private function getPeriodicityById(int $id): ?SubscriptionPeriodicity
    {
        $periodicity = $this->em->getRepository(SubscriptionPeriodicity::class)->find($id);

        if (null === $periodicity) {
            $this->errors['message'] = $this->trans(
                sprintf('Periodicity with ID:%s is not found.', $id), [], 'Modules.Productsubscription.Account'
            );

            return null;
        }

        return $periodicity;
    }

    /**
     * @param int|float|string $price
     *
     * @return string
     */
    private function getFormattedPrice($price): string
    {
        return $shippingFormattedPrice = $this->context->currentLocale->formatPrice(
            $price,
            $this->context->currency->iso_code
        );
    }

    /**
     * @param array $data
     *
     * @return array
     */
    private function extractFormData(array $data): array
    {
        $cleanedData = [];

        foreach ($data as $item) {
            $cleanedData[$item->name] = $item->value;
        }

        return $cleanedData;
    }

    /**
     * @param Subscription $subscription
     *
     * @return string
     */
    private function getSubscriptionStatus(Subscription $subscription): string
    {
        return $subscription->isActive() ? '' : ' (disabled)';
    }

    /**
     * @param Subscription $subscription
     */
    private function removeDeliveryAddressReason(Subscription $subscription): void
    {
        $subscription->removeReason(
            $this->em->getRepository(SubscriptionBlockingReason::class)
                ->getOrCreateByHandle(SubscriptionBlockingReason::NO_DELIVERY_ADDRESS)
        );

        $this->em->flush();
    }

    /**
     * @param Subscription $subscription
     */
    private function addPauseByCustomerReason(Subscription $subscription): void
    {
        $subscription->addReason(
            $this->em->getRepository(SubscriptionBlockingReason::class)
                ->getOrCreateByHandle(SubscriptionBlockingReason::PAUSED_BY_CUSTOMER)
        );

        $this->em->flush();
    }

    /**
     * @param Subscription $subscription
     */
    private function removePauseByCustomerReason(Subscription $subscription): void
    {
        $subscription->removeReason(
            $this->em->getRepository(SubscriptionBlockingReason::class)
                ->getOrCreateByHandle(SubscriptionBlockingReason::PAUSED_BY_CUSTOMER)
        );

        $this->em->flush();
    }

    /**
     * @param CartPresenterService $cartPresenterService
     *
     * @return float
     */
    private function getTotalWithoutShippingAndTax(CartPresenterService $cartPresenterService): float
    {
        return $cartPresenterService->getTotalAmountTaxExcluded() - $cartPresenterService->getShippingAmount();
    }

    /**
     * @param CartPresenterService $cartPresenterService
     *
     * @return float
     */
    protected function getTotalTax(CartPresenterService $cartPresenterService): float
    {
        return $cartPresenterService->getTotalAmountTaxExcluded() - $cartPresenterService->getTotalAmount();
    }
}
