<?php

class  ControllerEventCustomer extends Controller
{
    public function addCustomerAfter(&$route, &$args, &$output)
    {
        if (!(isset($args[1]) && $args[1] == 1)) return;
        $c_id = $output;
        $customerInfo = $this->orm
            ->table(DB_PREFIX . 'customerpartner_to_customer')
            ->where(['customer_id' => $c_id])
            ->first();
        if ($customerInfo) {
            $info = get_object_vars($customerInfo);
            $updateInfo = [];
            !$info['companybanner'] && $updateInfo['companybanner'] = 'default/default_banner.jpg';
            !$info['avatar'] && $updateInfo['avatar'] = 'default/default_logo.jpg';
            $this->orm
                ->table(DB_PREFIX . 'customerpartner_to_customer')
                ->where(['customer_id' => $c_id])
                ->update($updateInfo);
        } else {
            $updateInfo = [];
            $updateInfo['companybanner'] = 'default/default_banner.jpg';
            $updateInfo['avatar'] = 'default/default_logo.jpg';
            $updateInfo['customer_id'] = $c_id;
            $updateInfo['is_partner'] = 1;
            $this->orm
                ->table(DB_PREFIX . 'customerpartner_to_customer')
                ->insert($updateInfo);
        }
    }
}