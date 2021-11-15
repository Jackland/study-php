<?php

namespace App\Repositories\Product\ProductInfo\Traits\BaseInfo;

use App\Enums\Buyer\BuyerType;
use App\Models\Customer\Customer;
use App\Repositories\Customer\CustomerRepository;
use InvalidArgumentException;

/**
 * BaseInfo 对 customer 信息的支持
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
    private function getCustomer(): Customer
    {
        return $this->customer;
    }

    /**
     * customer 是否被设置
     * @return bool
     */
    private function isCustomerSet(): bool
    {
        return !!$this->customer;
    }

    /**
     * customer_id
     * @return int
     */
    private function getCustomerId(): int
    {
        if (!$this->isCustomerSet()) {
            throw new InvalidArgumentException('Must setCustomer first');
        }
        return $this->customer->customer_id;
    }

    /**
     * customer 是否是 seller
     * @return bool
     */
    private function isCustomerIsSeller(): bool
    {
        if (!$this->isCustomerSet()) {
            throw new InvalidArgumentException('Must setCustomer first');
        }
        return $this->requestCachedData([__CLASS__, __FUNCTION__, $this->customer->customer_id, 'v1'], function () {
            return app(CustomerRepository::class)->checkIsSeller($this->customer->customer_id);
        });
    }

    /**
     * 是否是上门取货 buyer
     * @return bool
     */
    private function isCustomerIsWillCallBuyer(): bool
    {
        if (!$this->isCustomerSet()) {
            throw new InvalidArgumentException('Must setCustomer first');
        }
        return $this->customer->buyer_type === BuyerType::PICK_UP && !$this->isCustomerIsSeller();
    }

    /**
     * customer 的国家
     * @return int
     */
    private function getCustomerCountryId(): int
    {
        if (!$this->isCustomerSet()) {
            throw new InvalidArgumentException('Must setCustomer first');
        }
        return $this->customer->country_id;
    }

    /**
     * 该 customer 是否是 seller 自己
     * @return bool
     */
    private function isCustomerSellerSelf(): bool
    {
        if (!$this->isCustomerSet()) {
            throw new InvalidArgumentException('Must setCustomer first');
        }
        return $this->customer->customer_id === $this->getSellerId();
    }
}
