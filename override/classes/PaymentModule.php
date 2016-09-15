<?php

abstract class PaymentModule extends PaymentModuleCore
{
    public function validateOrder($id_cart, $id_order_state, $amount_paid, $payment_method = 'Unknown',
        $message = null, $extra_vars = array(), $currency_special = null, $dont_touch_amount = false,
        $secure_key = false, Shop $shop = null)
    {
        if (!$this->context->cart->isVirtualCart() && Module::getInstanceByName('shiptopay')->active) {

            // Check if payment option is valid for selected delivery method [ship2pay]
            $sql = new DbQuery();
            $sql->select('*');
            $sql->from('shiptopay', 's2p');
            $sql->where('s2p.`id_shop` = '.(int)$this->context->shop->id);
            $sql->where('s2p.`id_carrier` = '.(int)$this->context->cart->id_carrier);
            $sql->where('s2p.`id_payment` = '.(int)$this->id);

            if (!$result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql)) {
                PrestaShopLogger::addLog('PaymentModule::validateOrder - Payment option not valid for selected delivery method', 1, null, 'Cart', (int)$id_cart, true);
                die(Tools::displayError('Payment option ['.$payment_method.'] is not valid for selected delivery method'));
            }
        }

        return parent::validateOrder($id_cart, $id_order_state, $amount_paid, $payment_method,
            $message, $extra_vars, $currency_special, $dont_touch_amount,
            $secure_key, $shop);
    }
}