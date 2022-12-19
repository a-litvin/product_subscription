<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Install;

use Configuration;
use Db;
use Module;
use Tools;

class Installer
{
    public const BRAINTREE_MODULE_NAME = 'braintreeofficial';
    public const PRODUCT_SUBSCRIPTION_CATALOG_PRICE_RULES = 'PRODUCT_SUBSCRIPTION_CATALOG_PRICE_RULES';
    public const PRODUCT_SUBSCRIPTION_CRON_EMAIL = 'PRODUCT_SUBSCRIPTION_CRON_EMAIL';

    private const INSTALL_SQL_FILE = 'install.sql';
    private const UNINSTALL_SQL_FILE = 'uninstall.sql';

    /**
     * @var array
     */
    private $hooksToRegister = [
        'displayAdminProductsExtra',
        'actionProductUpdate',
        'actionProductDelete',
        'displayProductAdditionalInfo',
        'actionCartSave',
        'actionObjectProductInCartDeleteBefore',
        'header',
        'displayCartExtraProductActions',
        'displayCustomerAccount',
        'actionObjectAddressDeleteAfter',
        'actionAuthentication',
        'displayOrderConfirmation',
        'actionCarrierProcess',
        'displayProductPriceBlock',
        'actionListMailThemes',
        'actionObjectCustomerDeleteAfter'
    ];

    /**
     * @param Module $module
     * @return bool
     */
    public function install(Module $module): bool
    {
        return
            $this->registerHooks($module)
            && $this->installDatabase($module->name)
            && $this->copyServicesToPrestashopBundle();
    }

    /**
     * @return bool
     */
    public function enable(): bool
    {
        return $this->copyServicesToPrestashopBundle();
    }

    /**
     * @param Module $module
     * @return bool
     */
    public function uninstall(Module $module): bool
    {
        return
            Configuration::deleteByName(self::PRODUCT_SUBSCRIPTION_CATALOG_PRICE_RULES)
            && $this->uninstallDatabase($module->name)
            && $this->deleteServicesFromPrestashopBundle();
    }

    /**
     * @return bool
     */
    public function disable(): bool
    {
        return $this->deleteServicesFromPrestashopBundle();
    }

    /**
     * @param Module $module
     *
     * @return bool
     */
    private function registerHooks(Module $module): bool
    {
        if (Module::isEnabled(self::BRAINTREE_MODULE_NAME)) {
            $this->hooksToRegister[] = 'actionObjectAddAfter';
        }

        return (bool) $module->registerHook($this->hooksToRegister);
    }

    /**
     * @param string $moduleName
     *
     * @return bool
     */
    private function installDatabase(string $moduleName): bool
    {
        $sqlFile = _PS_MODULE_DIR_ . $moduleName . DIRECTORY_SEPARATOR . self::INSTALL_SQL_FILE;
        $sql = $this->getCleanedSql($sqlFile);

        if (null === $sql) {
            return false;
        }

        return $this->executeQueries([$sql]);
    }

    /**
     * @param string $moduleName
     *
     * @return bool
     */
    private function uninstallDatabase(string $moduleName): bool
    {
        $sqlFile = _PS_MODULE_DIR_ . $moduleName . DIRECTORY_SEPARATOR . self::UNINSTALL_SQL_FILE;
        $sql = $this->getCleanedSql($sqlFile);

        if (null === $sql) {
            return false;
        }

        return $this->executeQueries([$sql]);
    }

    /**
     * @param array $queries
     * @return bool
     */
    private function executeQueries(array $queries): bool
    {
        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $sql
     *
     * @return string|null
     */
    private function getCleanedSql(string $sql): ?string
    {
        if (!file_exists($sql)) {
            return null;
        }

        $sql = file_get_contents($sql);

        if (false === $sql) {
            return null;
        }

        return trim(
            str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), $sql)
        );
    }

    /**
     * @return bool
     */
    private function copyServicesToPrestashopBundle(): bool
    {
        return Tools::copy(_PS_MODULE_DIR_ . 'productsubscription/app/config/productsubscription.yml', _PS_ROOT_DIR_ . '/src/PrestaShopBundle/Resources/config/services/adapter/productsubscription.yml');
    }

    /**
     * @return bool
     */
    private function deleteServicesFromPrestashopBundle(): bool
    {
        $file = _PS_ROOT_DIR_ . '/src/PrestaShopBundle/Resources/config/services/adapter/productsubscription.yml';

        if (file_exists($file) && is_file($file)) {
            return unlink($file);
        }

        return true;
    }
}
