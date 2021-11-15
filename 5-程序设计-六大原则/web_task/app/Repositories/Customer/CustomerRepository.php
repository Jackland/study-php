<?php

namespace App\Repositories\Customer;


use App\Models\Customer\Customer;

class CustomerRepository
{
    public function getCountryId($customerId)
    {
        return Customer::query()
            ->with('country')
            ->select(['customer_id', 'country_id'])
            ->where(['customer_id' => $customerId])
            ->first();
    }

    public function isCollectionFromDomicile($customerId)
    {
        $groupId = Customer::query()
            ->where(['customer_id' => $customerId])
            ->value('customer_group_id');
        return in_array($groupId, [25, 24, 26]);
    }

    //判断当前用户是否有
    public function hasCwfFreight($customerId, $countryId)
    {
        if ($countryId == 223 && !$this->isCollectionFromDomicile($customerId)) {
            return true;
        }
        return false;
    }
    /**
     * 获取客户号
     * @param $customerId
     * @return mixed
     */
    public function getCustomerNumber($customerId)
    {
        $customer = Customer::query()
            ->select(['firstname', 'lastname'])
            ->where(['customer_id' => $customerId])
            ->first();

        return $customer ? $customer->firstname . $customer->lastname : '';
    }
}