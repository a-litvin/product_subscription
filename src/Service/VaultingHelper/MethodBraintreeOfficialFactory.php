<?php

namespace BelVG\ProductSubscription\Service\VaultingHelper;

use BraintreeOfficialAddons\classes\AbstractMethodBraintreeOfficial;

class MethodBraintreeOfficialFactory
{
    public function create($method)
    {
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $method) && file_exists(_PS_OVERRIDE_DIR_.'modules/braintreeofficial/classes/Method'.$method.'.php')) {
            include_once _PS_MODULE_DIR_.'braintreeofficial/classes/Method'.$method.'.php';
            include_once _PS_OVERRIDE_DIR_.'modules/braintreeofficial/classes/Method'.$method.'.php';
            $method_class = 'Method'.$method.'Override';
            return new $method_class();
        }
        return AbstractMethodBraintreeOfficial::load('BraintreeOfficial');
    }
}
