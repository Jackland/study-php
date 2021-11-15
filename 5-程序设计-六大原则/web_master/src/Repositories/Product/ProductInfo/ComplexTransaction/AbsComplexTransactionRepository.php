<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction;

use App\Models\Customer\Customer;

abstract class AbsComplexTransactionRepository
{
    use Traits\CustomerSupportTrait;
    use Traits\InfoRepository\CustomerSupportTrait;
    use Traits\WithBuyerPriceRepositoryTrait;

    protected $productIds;

    public function __construct(array $ids)
    {
        $this->productIds = $ids;
    }

    private $basedCustomerId;

    /**
     * 基于某个用户
     * @param int|string|null $customerId
     * @return AbsComplexTransactionRepository
     */
    public function withCustomerId($customerId): self
    {
        if (!$customerId) {
            return $this;
        }

        $new = clone $this;
        $new->basedCustomerId = (int)$customerId;

        return $new;
    }

    /**
     * @return array|AbsComplexTransactionInfo[]
     */
    public function getInfos(): array
    {
        $customer = null;
        if ($this->basedCustomerId) {
            $customer = Customer::find($this->basedCustomerId);
            $this->setCustomer($customer);
        }

        $models = $this->getModelsWithOneProductOneInfo($this->productIds);

        $infos = [];
        foreach ($this->productIds as $productId) {
            // 循环 $this->productIds 而非 $models 是为了保持返回的产品顺序和原来一致
            if (!isset($models[$productId])) {
                continue;
            }
            $info = $this->newInfoModel($models[$productId]);
            $infos[$info->id] = $info;
        }

        $this->supportInfoCustomer($infos, $customer);
        $this->solveWithPriceRanges($infos);

        return $infos;
    }

    /**
     * 实例化一个 Info 模型
     * @param mixed $model getModelsWithOneProductOneInfo() 获取到的 model
     * @return AbsComplexTransactionInfo
     */
    abstract protected function newInfoModel($model): AbsComplexTransactionInfo;

    /**
     * 获取一个产品一个信息的对象数组，用于填充 Info 模型
     * @param array $productIds
     * @return iterable 保证 key 为 productId
     */
    abstract protected function getModelsWithOneProductOneInfo(array $productIds): iterable;
}
