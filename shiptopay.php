<?php

/**
 * Ship2pay PrestaShop 1.6x module.
 * 
 * @author Ireneusz Kierkowski <ircykk@gmail.com>
 * @version 2.1
 * @package ShipToPay
 *
 */

class ShipToPay extends Module
{
    function __construct()
    {
	    $this->name = 'shiptopay';
	    $this->tab = 'administration';
	    $this->version = '2.1';
	    $this->author = 'addonsPresta.com';
	    $this->ps_versions_compliancy = array(
	    	'min' => '1.6.0.0'
    	);

		$this->bootstrap = true;

	    parent::__construct();

	    $this->displayName = $this->l('Ship2Pay');
	    $this->description = $this->l('Assign delivery options for payment in the store.');
    }

    function install()
    {
    	$db = (bool)Db::getInstance(_PS_USE_SQL_SLAVE_)->Execute('
		CREATE TABLE `' . _DB_PREFIX_ . 'shiptopay` (
		`id_shop` INT(11) NOT NULL, 
		`id_carrier` INT(11) NOT NULL, 
		`id_payment` INT(11) NOT NULL,
    	UNIQUE KEY `key` (`id_shop`, `id_carrier`, `id_payment`))');

        if (!parent::install() || !$db || !$this->registerHook('actionCarrierUpdate'))
            return false;

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall() || !Db::getInstance(_PS_USE_SQL_SLAVE_)->Execute('DROP TABLE ' . _DB_PREFIX_ . 'shiptopay') || !$this->unregisterHook('actionCarrierUpdate'))
            return false;

        return true;
    }

    public function postProcess()
    {
    	if(Tools::isSubmit('submitUpdateShipToPay'))
    	{
    		Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'shiptopay` WHERE `id_shop` = ' . (int)$this->context->shop->id);

    		foreach ($_POST as $key => $value)
    		{
    			if(substr($key, 0, 9) === 'SHIPTOPAY')
    			{
    				$explode = explode('_', $key);

    				Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'shiptopay` VALUES (' . (int)$this->context->shop->id . ', ' . (int)$explode[1] . ', ' . (int)$explode[2] . ')');
    			}
    		}

    		$this->_html .= $this->displayConfirmation($this->l('Settings updated successfully.'));
    	}
    }

    public function getContent()
    {
    	$this->_html = '';
    	$this->postProcess();

    	$helper = $this->initForm();

		$this->_html .= $helper->generateForm($this->fields_form);

		return $this->_html;
    }

	
	private function initForm()
	{
		$languages = Language::getLanguages(false);
		foreach ($languages as $k => $language)
			$languages[$k]['is_default'] = (int)$language['id_lang'] == Configuration::get('PS_LANG_DEFAULT');

		$helper = new HelperForm();
		$helper->module = $this;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->languages = $languages;
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		$helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
		$helper->allow_employee_form_lang = true;
		$helper->toolbar_scroll = true;
		$helper->title = $this->displayName;
		$helper->submit_action = 'submitUpdateShipToPay';
		$helper->tpl_vars = array(
			'fields_value' => array()
		);

		$this->fields_form[0]['form'] = array(
			'tinymce' => true,
			'legend' => array(
				'title' => $this->displayName,
				'icon' => 'icon-cogs'
			),
			'submit' => array(
				'name' => 'submitUpdateShipToPay',
				'title' => $this->l('Save '),
				'class' => 'button pull-right'
			),
			'input' => array()
		);
    	
		$carriers = Carrier::getCarriers($this->context->language->id, false, false, false, null, Carrier::ALL_CARRIERS);
		$payment_modules = array();

		/* Get all modules then select only payment ones */
		$modules = Module::getModulesOnDisk(true);

		foreach ($modules as $module)
		{
			if ($module->tab == 'payments_gateways')
			{
				if ($module->id)
				{
					$payment_modules[] = array('name' => $module->displayName . ' (' . $module->name . ')' , 'id' => $module->id);
				}
			}
		}

		foreach ($carriers as $carrier)
		{
			$this->fields_form[0]['form']['input'][] = array(
				'type' => 'checkbox',
				'label' => $carrier['name'],
				'name' => 'SHIPTOPAY_' . $carrier['id_carrier'],
				'values' => array(
					'query' => $payment_modules,
					'id' => 'id',
					'name' => 'name'
				)
			);

			/* Set field values */
			foreach ($payment_modules as $module)
			{
				$helper->tpl_vars['fields_value']['SHIPTOPAY_' . $carrier['id_carrier'] . '_' . $module['id']] = $this->getShipToPay($carrier['id_carrier'], $module['id']);
			}
		}

		return $helper;
	}

	private function getShipToPay($id_carrier, $id_payment)
	{
		return Db::getInstance()->getRow('
		SELECT * FROM `'._DB_PREFIX_.'shiptopay` 
			WHERE `id_shop` = ' . (int)$this->context->shop->id . ' 
			AND `id_carrier` = ' . (int)$id_carrier . ' 
			AND `id_payment` = ' . (int)$id_payment);
	}

	public function hookActionCarrierUpdate($carrier)
	{
		// Update id_carrier in shiptopay table
		Db::getInstance()->Execute('UPDATE `'._DB_PREFIX_.'shiptopay` SET `id_carrier` = ' . (int)$carrier['carrier']->id . ' WHERE `id_carrier` = ' . (int)$carrier['id_carrier']);
	}
}
