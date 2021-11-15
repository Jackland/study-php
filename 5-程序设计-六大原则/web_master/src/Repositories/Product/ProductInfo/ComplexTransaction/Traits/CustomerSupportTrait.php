<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction\Traits;

use App\Models\Customer\Customer;

/**
 * AbsComplexTransactionInfo|AbsComplexTransactionRepository 对 customer 的支持
 */
trait CustomerSupportTrait
{
    /**
     * 非该 trait 不建议直接调用该属性，应该定义成方法提供调用
     * @var Customer
     */
    private $customer;

    /**
     * 设置 customer
     * @param Customer $customer
     */
    public function setCustomer(Customer $customer)
    {
        $this->customer = $customer;
    }

    /**
     * 获取 customer
     * @return Customer
     */
    protected function getCustomer(): Customer
    {
        return $this->customer;
    }

    /**
     * customer 是否被设置
     * @return bool
     */
    protected function isCustomerSet(): bool
    {
        return !!$this->customer;
    }

    /**
     * 是否是 buyer
     * @return bool
     */
    protected function isCustomerBuyer(): bool
    {
        return !$this->customer->is_partner;
    }
}
