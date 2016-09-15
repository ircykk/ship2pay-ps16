<?php

class Hook extends HookCore
{
    public static function getHookModuleExecList($hook_name = null)
    {
        $context = Context::getContext();
        $cache_id = 'hook_module_exec_list_'.(isset($context->shop->id) ? '_'.$context->shop->id : '' ).((isset($context->customer)) ? '_'.$context->customer->id : '');
        if (!Cache::isStored($cache_id) || $hook_name == 'displayPayment' || $hook_name == 'displayBackOfficeHeader')
        {
            $frontend = true;
            $groups = array();
            $use_groups = Group::isFeatureActive();
            if (isset($context->employee))
                $frontend = false;
            else
            {
                // Get groups list
                if ($use_groups)
                {
                    if (isset($context->customer) && $context->customer->isLogged())
                        $groups = $context->customer->getGroups();
                    elseif (isset($context->customer) && $context->customer->isLogged(true))
                        $groups = array((int)Configuration::get('PS_GUEST_GROUP'));
                    else
                        $groups = array((int)Configuration::get('PS_UNIDENTIFIED_GROUP'));
                }
            }
            
            // SQL Request
            $sql = new DbQuery();
            $sql->select('h.`name` as hook, m.`id_module`, h.`id_hook`, m.`name` as module, h.`live_edit`');
            $sql->from('module', 'm');
            if ($hook_name != 'displayBackOfficeHeader')
            {
                $sql->join(Shop::addSqlAssociation('module', 'm', true, 'module_shop.enable_device & '.(int)Context::getContext()->getDevice()));
                $sql->innerJoin('module_shop', 'ms', 'ms.`id_module` = m.`id_module`');
            }
            $sql->innerJoin('hook_module', 'hm', 'hm.`id_module` = m.`id_module`');
            $sql->innerJoin('hook', 'h', 'hm.`id_hook` = h.`id_hook`');
            if ($hook_name != 'displayPayment')
                $sql->where('h.name != "displayPayment"');
            // For payment modules, we check that they are available in the contextual country
            elseif ($frontend)
            {
                // ShipToPay remove fix (override still exists but module not)
                if(Module::getInstanceByName('shiptopay')->active)
                    $sql->innerJoin('shiptopay', 'stp', 'stp.`id_payment` = m.`id_module` AND stp.`id_carrier` = ' . (int)$context->cart->id_carrier . ' AND stp.`id_shop` = ' . (int)$context->shop->id);

                if (Validate::isLoadedObject($context->country))
                    $sql->where('(h.name = "displayPayment" AND (SELECT id_country FROM '._DB_PREFIX_.'module_country mc WHERE mc.id_module = m.id_module AND id_country = '.(int)$context->country->id.' AND id_shop = '.(int)$context->shop->id.' LIMIT 1) = '.(int)$context->country->id.')');
                if (Validate::isLoadedObject($context->currency))
                    $sql->where('(h.name = "displayPayment" AND (SELECT id_currency FROM '._DB_PREFIX_.'module_currency mcr WHERE mcr.id_module = m.id_module AND id_currency IN ('.(int)$context->currency->id.', -1, -2) LIMIT 1) IN ('.(int)$context->currency->id.', -1, -2))');
            }
            if (Validate::isLoadedObject($context->shop))
                $sql->where('hm.id_shop = '.(int)$context->shop->id);

            if ($frontend)
            {
                if ($use_groups)
                {
                    $sql->leftJoin('module_group', 'mg', 'mg.`id_module` = m.`id_module`');
                    if (Validate::isLoadedObject($context->shop))
                        $sql->where('mg.id_shop = '.((int)$context->shop->id).' AND  mg.`id_group` IN ('.implode(', ', $groups).')');
                    else
                        $sql->where('mg.`id_group` IN ('.implode(', ', $groups).')');
                }
            }

            $sql->groupBy('hm.id_hook, hm.id_module');
            $sql->orderBy('hm.`position`');

            $list = array();
            if ($result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql))
                foreach ($result as $row)
                {
                    $row['hook'] = strtolower($row['hook']);
                    if (!isset($list[$row['hook']]))
                        $list[$row['hook']] = array();

                    $list[$row['hook']][] = array(
                        'id_hook' => $row['id_hook'],
                        'module' => $row['module'],
                        'id_module' => $row['id_module'],
                        'live_edit' => $row['live_edit'],
                    );
                }
            if ($hook_name != 'displayPayment' && $hook_name != 'displayBackOfficeHeader')
            {
                Cache::store($cache_id, $list);
                // @todo remove this in 1.6, we keep it in 1.5 for retrocompatibility
                self::$_hook_modules_cache_exec = $list;
            }
        }
        else
            $list = Cache::retrieve($cache_id);

        // If hook_name is given, just get list of modules for this hook
        if ($hook_name)
        {
            $retro_hook_name = strtolower(Hook::getRetroHookName($hook_name));
            $hook_name = strtolower($hook_name);

            $return = array();
            $inserted_modules = array();
            if (isset($list[$hook_name]))
                $return = $list[$hook_name];
            foreach ($return as $module)
                $inserted_modules[] = $module['id_module'];
            if (isset($list[$retro_hook_name]))
                foreach ($list[$retro_hook_name] as $retro_module_call)
                    if (!in_array($retro_module_call['id_module'], $inserted_modules))
                        $return[] = $retro_module_call;

            return (count($return) > 0 ? $return : false);
        }
        else
            return $list;
    }
}