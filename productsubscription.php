<?php

use BelVG\ProductSubscription\Entity\Subscription;
use BelVG\ProductSubscription\Entity\SubscriptionAvailability;
use BelVG\ProductSubscription\Entity\SubscriptionCartProduct;
use BelVG\ProductSubscription\Entity\SubscriptionHistory;
use BelVG\ProductSubscription\Entity\SubscriptionPeriodicity;
use BelVG\ProductSubscription\Install\Installer;
use BelVG\ProductSubscription\ReadModel\PeriodicityReadModel;
use BelVG\ProductSubscription\ReadModel\SubscriptionCartProductReadModel;
use BelVG\ProductSubscription\Service\Cart\CartContextService;
use BelVG\ProductSubscription\Service\RedeemPointsService;
use BelVG\ProductSubscription\Service\SubscriptionAddressService;
use BelVG\ProductSubscription\Service\SubscriptionOrderCartService;
use BelVG\ProductSubscription\Service\SubscriptionService;
use BelVG\ProductSubscription\Service\VaultingHelper\BraintreeOfficialVaultingHelper;
use BraintreeOfficialAddons\classes\BraintreeOfficialOrder;
use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\Layout\Layout;
use PrestaShop\PrestaShop\Core\MailTemplate\ThemeCollectionInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\ThemeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__.'/vendor/autoload.php';

class ProductSubscription extends Module
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var array
     */
    private $fieldsPeriodicityForm = [];

    public function __construct()
    {
        $this->name = 'productsubscription';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'BelVG LLC';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_,
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Products subscriptions', [], 'Modules.Productsubscription.Productsubscription');
        $this->description = $this->trans('This module allows your customers create products subscriptions to make automatic orders.', [], 'Modules.Productsubscription.Productsubscription');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?', [], 'Modules.Productsubscription.Productsubscription');

    }

    /**
     * @return bool
     */
    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        return (new Installer())->install($this);
    }

    /**
     * @param bool $force_all
     *
     * @return bool
     */
    public function enable($force_all = false): bool
    {
        if (!parent::enable($force_all)) {
            return false;
        }

        return (new Installer())->enable();
    }

    /**
     * @return bool
     */
    public function uninstall(): bool
    {
        if (!parent::uninstall()) {
            return false;
        }

        (new CartContextService())->clearCookie();

        return (new Installer())->uninstall($this);
    }

    /**
     * @param bool $force_all
     *
     * @return bool
     */
    public function disable($force_all = false)
    {
        if (!parent::disable($force_all)) {
            return false;
        }

        return (new Installer())->disable();
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        $html = '';

        if (Tools::isSubmit('submit' . 'product_subscription_config')) {
            $email = Tools::getValue(Installer::PRODUCT_SUBSCRIPTION_CRON_EMAIL);
            if (!Validate::isEmail($email)) {
                $html .= $this->displayError($this->trans('Cron reports email is invalid', [], 'Admin.Actions'));
            } else {
                Configuration::updateValue(Installer::PRODUCT_SUBSCRIPTION_CRON_EMAIL, $email);
                $html .= $this->displayConfirmation($this->trans('Settings updated', [], 'Admin.Actions'));
            }
        }

        if (Tools::isSubmit('submit' . 'product_subscription_catalog_price_rule')) {
            $ruleIds = Tools::getValue(Installer::PRODUCT_SUBSCRIPTION_CATALOG_PRICE_RULES);

            if (empty($ruleIds)) {
                $html .= $this->displayError($this->trans('Invalid Configuration value', [], 'Admin.Actions'));
            } else {
                Configuration::updateValue(Installer::PRODUCT_SUBSCRIPTION_CATALOG_PRICE_RULES, serialize($ruleIds));
                $html .= $this->displayConfirmation($this->trans('Settings updated', [], 'Admin.Actions'));
            }
        }

        $idPeriodicity = (int) Tools::getValue('id_periodicity');
        $em = $this->getEntityManager();

        if (Tools::isSubmit('saveproduct_subscription_periodicity')) {
            if ($idPeriodicity) {
                $periodicity = $em->getRepository(SubscriptionPeriodicity::class)->find($idPeriodicity);
                $periodicity->setInterval((int) Tools::getValue('interval'));
                $periodicity->setName(Tools::getValue('name'));
                $em->flush();
            } else {
                $existedPeriodicity = $this->getEntityManager()->getRepository(SubscriptionPeriodicity::class)->findOneBy([
                    'interval' => (int) Tools::getValue('interval'),
                ]);

                if (null !== $existedPeriodicity) {
                    $html .= $this->displayError($this->trans('Periodicity already exists', [], 'Modules.Productsubscription.Productsubscription'));
                } else {
                    $periodicity = new SubscriptionPeriodicity(
                        Tools::getValue('name'),
                        (int) Tools::getValue('interval')
                    );
                    $em->persist($periodicity);
                    $em->flush();
                }
            }
        } elseif (Tools::isSubmit('updateproduct_subscription_periodicity') || Tools::isSubmit('addproduct_subscription_periodicity')) {
            $helper = $this->initPeriodicityForm();

            if ($idPeriodicity) {
                $periodicity = $em->getRepository(SubscriptionPeriodicity::class)->find($idPeriodicity);
                $this->fieldsPeriodicityForm[0]['form']['input'][] = array('type' => 'hidden', 'name' => 'id_periodicity');
                $helper->fields_value['id_periodicity'] = $idPeriodicity;
                $helper->fields_value['interval'] = $periodicity->getInterval();
                $helper->fields_value['name'] = $periodicity->getName();
            } else {
                $helper->fields_value['interval'] = '';
                $helper->fields_value['name'] = '';
            }

            $html .= $helper->generateForm($this->fieldsPeriodicityForm);

            return $html;
        } elseif (Tools::getIsset('deleteproduct_subscription_periodicity')) {
            if ($idPeriodicity = Tools::getValue('id_periodicity')) {
                $periodicity = $em->getReference(SubscriptionPeriodicity::class, $idPeriodicity);
                $em->remove($periodicity);
                $em->flush();
            }
        }

        $html .= $this->renderConfigForm();
        $html .= $this->renderPriceRuleForm();
        $html .= $this->renderPeriodicityList();

        return $html;
    }

    /**
     * @return HelperForm
     */
    public function initPeriodicityForm(): HelperForm
    {
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $this->fieldsPeriodicityForm[0] = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Periodicity block', [], 'Modules.Productsubscription.Productsubscription'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Interval value', [], 'Modules.Productsubscription.Productsubscription'),
                        'name' => 'interval',
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Name of interval', [], 'Modules.Productsubscription.Productsubscription'),
                        'name' => 'name',
                        'size' => 20,
                        'required' => true,
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;

        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'save' . 'product_subscription_periodicity';

        return $helper;
    }

    /**
     * @return string
     */
    public function renderPeriodicityList(): string
    {
        $periodicities = $this->getContainer()->get(PeriodicityReadModel::class)->getAll();

        $fields_list = [
            'id_periodicity' => [
                'title' => $this->trans('ID', [], 'Modules.Productsubscription.Productsubscription'),
                'width' => 60,
                'type' => 'int',
                'search' => false,
                'orderby' => false,
            ],
            'interval' => [
                'title' => $this->trans('Interval value', [], 'Modules.Productsubscription.Productsubscription'),
                'width' => 100,
                'type' => 'int',
                'search' => false,
                'orderby' => false,
            ],
            'name' => [
                'title' => $this->trans('Name of interval', [], 'Modules.Productsubscription.Productsubscription'),
                'width' => 140,
                'type' => 'text',
                'search' => false,
                'orderby' => false,
            ],
            'created_at' => [
                'title' => $this->trans('Time of creation', [], 'Modules.Productsubscription.Productsubscription'),
                'width' => 140,
                'type' => 'date',
                'search' => false,
                'orderby' => false,
            ],
            'updated_at' => [
                'title' => $this->trans('Update time', [], 'Modules.Productsubscription.Productsubscription'),
                'width' => 140,
                'type' => 'date',
                'search' => false,
                'orderby' => false,
            ],
        ];

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->identifier = 'id_periodicity';
        $helper->table = 'product_subscription_periodicity';
        $helper->listTotal = count($periodicities);
        $helper->actions = [
            'edit',
            'delete',
            ];
        $helper->show_toolbar = true;
        $helper->toolbar_btn['new'] =  [
            'href' => AdminController::$currentIndex.'&configure='.$this->name.'&add'.$helper->table.'&token='.Tools::getAdminTokenLite('AdminModules'),
            'desc' => $this->trans('Add new', [], 'Admin.Actions')
        ];
        $helper->title = $this->trans('Intervals of periodicity', [], 'Modules.Productsubscription.Productsubscription');

        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        return $helper->generateList($periodicities, $fields_list);
    }

    /**
     * @return string
     */
    public function renderConfigForm(): string
    {
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => $this->trans('General settings', [], 'Modules.Productsubscription.Productsubscription'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->trans('Cron reports email', [], 'Modules.Productsubscription.Productsubscription'),
                    'name' => Installer::PRODUCT_SUBSCRIPTION_CRON_EMAIL,
                    'required' => true,
                    'prefix' => '<i class="icon icon-envelope"></i>',
                    'col' => 3
                ]
            ],
            'submit' => [
                'title' => $this->trans('Save', [], 'Admin.Actions'),
                'class' => 'btn btn-default pull-right',
            ]
        ];

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;

        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . 'product_subscription_config';

        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->trans('Save', [], 'Admin.Actions'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                    '&token='.Tools::getAdminTokenLite('AdminModules'),
            ],
        ];

        $helper->fields_value[Installer::PRODUCT_SUBSCRIPTION_CRON_EMAIL] = Tools::getValue(Installer::PRODUCT_SUBSCRIPTION_CRON_EMAIL, Configuration::get(Installer::PRODUCT_SUBSCRIPTION_CRON_EMAIL));

        return $helper->generateForm($fieldsForm);
    }

    /**
     * @return string
     */
    public function renderPriceRuleForm(): string
    {
        $specificPriceRules = new PrestaShopCollection('SpecificPriceRule');

        foreach ($specificPriceRules as $rule) {
            $rules[] = [
                'id' => $rule->id,
                'name' => $rule->name,
            ];
        }

        if (empty($rules)) {
            return '';
        }

        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => $this->trans('Catalog price rules for subscription products', [], 'Modules.Productsubscription.Productsubscription'),
            ],
            'input' => [
                [
                    'type' => 'select',
                    'label' => $this->trans('Catalog price rules', [], 'Modules.Productsubscription.Productsubscription'),
                    'name' => Installer::PRODUCT_SUBSCRIPTION_CATALOG_PRICE_RULES . '[]',
                    'required' => true,
                    'multiple' => true,
                    'options' => [
                        'query' => $rules,
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ]
            ],
            'submit' => [
                'title' => $this->trans('Save', [], 'Admin.Actions'),
                'class' => 'btn btn-default pull-right',
            ]
        ];

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;

        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . 'product_subscription_catalog_price_rule';

        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->trans('Save', [], 'Admin.Actions'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                    '&token='.Tools::getAdminTokenLite('AdminModules'),
            ],
        ];

        $helper->fields_value[Installer::PRODUCT_SUBSCRIPTION_CATALOG_PRICE_RULES . '[]'] = Tools::getValue(Installer::PRODUCT_SUBSCRIPTION_CATALOG_PRICE_RULES, unserialize(Configuration::get(Installer::PRODUCT_SUBSCRIPTION_CATALOG_PRICE_RULES)));

        return $helper->generateForm($fieldsForm);
    }

    /**
     * Header hook.
     */
    public function hookHeader(): void
    {
        $pages = ['product', 'cart'];

        if (in_array($this->context->controller->php_self, $pages, true)) {
            $this->context->controller->addjqueryPlugin('fancybox');
            $this->context->controller->registerJavascript(
                'module-productsubscription-ajax',
                'modules/'.$this->name.'/views/js/front/update_subscription_product.js'
            );
        }

        if ($this->context->controller instanceof ProductSubscriptionAccountModuleFrontController) {
            $this->context->controller->addjqueryPlugin('fancybox');
            $this->context->controller->addJqueryUI('ui.datepicker');

            if (Module::isEnabled('braintreeofficial') && Configuration::get('BRAINTREEOFFICIAL_VAULTING')) {
                $module = Module::getInstanceByName('braintreeofficial');

                $this->context->controller->registerJavascript($this->name . '-braintreegateway-client', 'https://js.braintreegateway.com/web/3.57.0/js/client.min.js', array('server' => 'remote'));
                $this->context->controller->registerJavascript($this->name . '-braintreegateway-hosted', 'https://js.braintreegateway.com/web/3.57.0/js/hosted-fields.min.js', array('server' => 'remote'));
                $this->context->controller->registerJavascript($this->name . '-braintreegateway-data', 'https://js.braintreegateway.com/web/3.57.0/js/data-collector.min.js', array('server' => 'remote'));
                $this->context->controller->registerJavascript($this->name . '-braintreegateway-3ds', 'https://js.braintreegateway.com/web/3.57.0/js/three-d-secure.min.js', array('server' => 'remote'));
                $this->context->controller->registerStylesheet($this->name . '-braintreecss', 'modules/braintreeofficial/views/css/braintree.css');
                $module->addJsVarsLangBT();
                $module->addJsVarsBT();
                $this->context->controller->registerJavascript($this->name . '-braintreejs', 'modules/braintreeofficial/views/js/payment_bt.js');
            }

            if (Module::isEnabled('gcorderform')) {
                $this->context->controller->registerStylesheet($this->name . '-gcorderform2-css', 'modules/gcorderform/views/css/gcorderform.css');
            }
        }
    }

    /**
     * @param array $params
     */
    public function hookActionCarrierProcess(array $params): void
    {
        $subscriptionCartProductReadModel = $this->getContainer()->get(SubscriptionCartProductReadModel::class);
        $subscriptionProducts = $subscriptionCartProductReadModel->getActiveIdsByCartId((int) $params['cart']->id);

        $this->context->smarty->assign([
            'productsubscription_remember_my_card' => !empty($subscriptionProducts),
        ]);
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayAdminProductsExtra(array $params): string
    {
        $html = '';
        $idProduct = $params['id_product'] ?? Tools::getValue('id_product');

        if (!Validate::isLoadedObject($product = new Product((int)$idProduct))) {
            $html .= $this->displayError($this->l('Please, save this product before'));

            return $html;
        }

        $availability = $this->getEntityManager()->getRepository(SubscriptionAvailability::class)->findOneBy([
            'productId' => $idProduct,
        ]);
        $this->smarty->assign([
            'checkboxName' => $this->name . '_availability',
            'active' => $availability ? $availability->isAvailable() : false,
        ]);
        $html .= $this->display(__FILE__, 'views/templates/hook/display_admin_products_extra.tpl');

        return $html;
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayProductPriceBlock(array $params): string
    {
        if ('after_price' !== $params['type']) {
            return '';
        }

        $productId = $params['product']->getId();
        $availability = $this->getEntityManager()->getRepository(SubscriptionAvailability::class)->findOneBy([
            'productId' => $productId,
        ]);

        if (null === $availability || !$availability->isAvailable()) {
            return '';
        }

        $cartId = $params['cart']->id;
        $productAttributeId = (int) $params['product']->offsetGet('id_product_attribute');
        $subscriptionCartProduct = $this->getEntityManager()->getRepository(SubscriptionCartProduct::class)->findOneBy([
            'cartId' => $cartId,
            'productId' => $productId,
            'productAttributeId' => $productAttributeId,
        ]);

        
        $periodicities = $this->getContainer()->get(PeriodicityReadModel::class)->getAll();
        $firstPeriodicityId = $periodicities[0]['id_periodicity'];
        $preselectedPeriodicityId = $subscriptionCartProduct ? $subscriptionCartProduct->getPeriodicity()->getId() : $firstPeriodicityId;
        $showPeriodicityBlock = $subscriptionCartProduct ? true : false;

        $this->smarty->assign([
            'periodicities' => $periodicities,
            'selectName' => $this->name . '_periodicity',
            'selectedPeriodicityId' => $preselectedPeriodicityId,
            'firstPeriodicityId' => $firstPeriodicityId,
            'showPeriodicityBlock' => $showPeriodicityBlock,
            'cartId' => $params['cart']->id,
            'productId' => $productId,
            'productAttributeId' => $productAttributeId,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/display_product_price_block.tpl');
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayCartExtraProductActions(array $params)
    {
    	$productId = $params['product']['id'];

        $availability = $this->getEntityManager()->getRepository(SubscriptionAvailability::class)->findOneBy([
            'productId' => $productId,
        ]);

        if (null !== $availability && $availability->isAvailable()) {
        	$cartId = $params['cart']->id;

            $productAttributeId = (int) $params['product']['id_product_attribute'];
            $subscriptionCartProduct = $this->getEntityManager()->getRepository(SubscriptionCartProduct::class)->findOneBy([
                'cartId' => $cartId,
                'productId' => $productId,
                'productAttributeId' => $productAttributeId,
            ]);

            $selectedPeriodicityId = (null !== $subscriptionCartProduct && $subscriptionCartProduct->isActive()) ? $subscriptionCartProduct->getPeriodicity()->getId() : (int) Tools::getValue('productsubscription_periodicity');
            if ($selectedPeriodicityId == null) {
                $cookie = new Cookie('subscription_data');
                $cookieName = 'product_'.$productId;
                if (isset($cookie->$cookieName) && !empty($cookie->$cookieName))
                    $selectedPeriodicityId = (int)$cookie->$cookieName;
            }

            $this->smarty->assign([
                'periodicities' => $this->getContainer()->get(PeriodicityReadModel::class)->getAll(),
                'selectName' => $this->name . '_periodicity',
                'selectedPeriodicityId' => $selectedPeriodicityId,
                'cartId' => $cartId,
                'productId' => $productId,
                'productAttributeId' => $productAttributeId,
            ]);

            if ($params['short']) {
                if($selectedPeriodicityId) {
                    return $this->display(__FILE__, 'views/templates/hook/display_cart_extra_product_actions_short.tpl');
                } else {
                    return '';
                }
            } else {
                return $this->display(__FILE__, 'views/templates/hook/display_cart_extra_product_actions.tpl');
            }
        }

        if ($params['short']) {
            return '';
        } else {
            return $this->display(__FILE__, 'views/templates/hook/display_cart_extra_product_actions_empty.tpl');
        }
    }

	/**
	 * @param array $params
	 *
	 * @return string
	 */
	public function hookDisplayOrderConfirmationProduct(array $params)
	{
		if (is_array($params['product'])) {
			$params['product']['id'] = $params['product']['product_id'];
			$params['cart']->id = Tools::getValue('id_cart');
		}
		return $this->hookDisplayCartExtraProductActions($params);

	}

    /**
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayCustomerAccount(array $params): string
    {
        return $this->display(__FILE__, 'views/templates/hook/display_customer_account.tpl');
    }

    /**
     * @param array $params
     */
    public function hookDisplayOrderConfirmation(array $params): void
    {
        if (!Module::isEnabled('braintreeofficial') || !Configuration::get('BRAINTREEOFFICIAL_VAULTING')) {
            return;
        }

        $cartContextService = new CartContextService($this->context);

        if (!$cartContextService->isShipNow()) {
            return;
        }

        $cartContextService->restoreCartContext();
    }

    /**
     * @param array $params
     */
    public function hookActionProductUpdate(array $params): void
    {
        $active = Tools::getIsset($this->name . '_availability');
        $product = $params['product'];
        $productId = $product->id;

        $em = $this->getEntityManager();

        $availability = $em->getRepository(SubscriptionAvailability::class)->findOneBy([
            'productId' => $productId,
        ]);

        if (null === $availability && false === $active) {
            return;
        }

        if (null !== $availability) {
            $availability->setIsAvailable($active);
        } else {
            $availability = new SubscriptionAvailability($productId);
            $em->persist($availability);
        }

        $em->flush();
    }

    /**
     * @param array $params
     */
    public function hookActionAuthentication(array $params): void
    {
        $cartContextService = new CartContextService($this->context);

        if ($cartContextService->isShipNow()) {
            $cartContextService->restoreCartContext();
        }
    }

    /**
     * @param array $params
     */
    public function hookActionObjectAddAfter(array $params): void
    {
        if (!$params['object'] instanceof BraintreeOfficialOrder) {
            return;
        }

        if (!Configuration::get('BRAINTREEOFFICIAL_VAULTING')) {
            return;
        }

        if (null === $this->context->controller) {
            return;
        }

        $cart = $params['cart'];
        $braintreeOfficialOrder = $params['object'];
        $orderId = (int) $braintreeOfficialOrder->id_order;
        $customerId = (int) $cart->id_customer;

        $cartContextService = new CartContextService($this->context);

        if ($cartContextService->isShipNow()) {
            $subscriptionId = $cartContextService->getOriginSubscriptionId();
            $em = $this->getEntityManager();
            $subscription = $em->getRepository(Subscription::class)->findWithAssociations($subscriptionId);

            $shipNowService = $this->getContainer()->get(SubscriptionOrderCartService::class);
            $shipNowService->updateSubscriptionAfterShipment($subscription);
            $shipNowService->addMessageToOrder(
                $subscription,
                $orderId,
                $this->context
            );

            $redeemPointsService = new RedeemPointsService($this->context);

            if ($redeemPointsService->isRedeemPointsApplied($subscription)) {
                $subscriptionCart = new Cart($subscription->getCartId());
                $redeemPointsService->setPointsRedeem(0, $subscriptionCart);
            }

            $subscriptionHistory = new SubscriptionHistory($subscription, $orderId);
            $em->persist($subscriptionHistory);
            $em->flush();

            return;
        }

        try {
            $helper = new BraintreeOfficialVaultingHelper();
            $helper->process($braintreeOfficialOrder, $customerId);

            $subscriptionService = $this->getContainer()->get(SubscriptionService::class);
            $subscriptionService->process($cart, $helper);
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog(sprintf("Can't create a subscription. Exception occurred: %s.", $exception->getMessage()));
        }
    }

    /**
     * @param array $params
     */
    public function hookActionProductDelete(array $params): void
    {
        $idProduct = $params['id_product'] ?? Tools::getValue('id_product');

        $em = $this->getEntityManager();
        $availability = $em->getRepository(SubscriptionAvailability::class)->findOneBy([
            'productId' => $idProduct,
        ]);

        if (null !== $availability) {
            $em->remove($availability);
            $em->flush();
        }
    }

    /**
     * @param array $params
     */
    public function hookActionObjectAddressDeleteAfter(array $params)
    {
        $service = $this->getContainer()->get(SubscriptionAddressService::class);
        $service->process($params['object']);
    }

    /**
     * @param array $params
     *
     * @throws \PrestaShop\PrestaShop\Core\Exception\InvalidArgumentException
     */
    public function hookActionCartSave(array $params): void
    {
        if (null === $this->context->controller) {
            return;
        }

        if (null === $params['cart']) {
            return;
        }

        $cart = $params['cart'];
        $lastProduct = $cart->getLastProduct();

        if (false === $lastProduct) {
            return;
        }

        $em = $this->getEntityManager();
        $subscriptionCartProduct = $em->getRepository(SubscriptionCartProduct::class)
            ->findOneBy([
                'cartId' => $cart->id,
                'productId' => $lastProduct['id_product'],
                'productAttributeId' => $lastProduct['id_product_attribute'],
            ]);

        if (null !== $subscriptionCartProduct && !$subscriptionCartProduct->isActive()) {
            $subscriptionCartProduct->setActive();
            $em->flush();
        }
    }

    /**
     * @param array $params
     */
    public function hookActionObjectProductInCartDeleteBefore(array $params): void
    {
        $cartId = $params['id_cart'];
        $productId = $params['id_product'];
        $productAttributeId = $params['id_product_attribute'];

        $em = $this->getEntityManager();
        $subscriptionCartProduct = $em->getRepository(SubscriptionCartProduct::class)->findOneBy([
            'cartId' => $cartId,
            'productId' => $productId,
            'productAttributeId' => $productAttributeId,
        ]);

        if (null !== $subscriptionCartProduct) {
            $em->remove($subscriptionCartProduct);
            $em->flush();
        }
    }

    /**
     * @param array $hookParams
     */
    public function hookActionListMailThemes(array $hookParams)
    {
        if (!isset($hookParams['mailThemes'])) {
            return;
        }

        /** @var ThemeCollectionInterface $themes */
        $themes = $hookParams['mailThemes'];

        /** @var ThemeInterface $theme */
        foreach ($themes as $theme) {
            if (!in_array($theme->getName(), ['classic', 'modern'])) {
                continue;
            }

            // Add a layout to each theme
            $theme->getLayouts()->add(new Layout(
                'productsubscription_cron',
                __DIR__ . '/mails/layouts/productsubscription_cron_' . $theme->getName() . '_layout.html.twig',
                '',
                $this->name
            ));
        }
    }

    /**
     * @param array $params
     */
    public function hookActionObjectCustomerDeleteAfter(array $params)
    {
        $customer = $params['object'];
        $em = $this->getEntityManager();
        $subscriptions = $em->getRepository(Subscription::class)->findAllWithAssociationsByCustomerId((int)$customer->id);
        if (!empty($subscriptions)) {
            foreach ($subscriptions as $subscription) {
                $subscription->remove();
            }
            $em->flush();
        }

    }

    /**
     * @return EntityManagerInterface
     */
    private function getEntityManager(): EntityManagerInterface
    {
        if (null === $this->em) {
            $this->em = $this->getContainer()->get('doctrine.orm.entity_manager');
        }

        return $this->em;
    }

//    /**
//     * @return ContainerInterface
//     */
//    private function getContainer(): ContainerInterface
//    {
//        if (null === $this->context->controller->getContainer()) {
//            $this->context->controller->init();
//        }
//
//        return $this->context->controller->getContainer();
//    }
}
