<?php

/**
 * Class ModelAccountRegisterApply
 */
class ModelAccountRegisterApply extends Model
{
    public function addCustomerRegister($data)
    {
        $this->orm::table(DB_PREFIX . 'customer_register')
            ->insert($data);
    }
}