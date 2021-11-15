<?php

namespace App\Catalog\Search\CustomerPartner\SellerStoreHome;

use App\Enums\Seller\SellerStoreHome\ModuleType;
use App\Helper\StringHelper;
use App\Models\Product\Product;
use App\Repositories\Product\ProductInfo\BaseInfo;
use App\Repositories\Product\ProductInfo\ProductInfoFactory;
use App\Repositories\Product\ProductRepository;
use Framework\Model\RequestForm\RequestForm;
use Illuminate\Validation\Rule;

class SellerProductsSearch extends RequestForm
{
    public $q; // 查询内容
    public $type; // 模块类型
    public $complex_transaction; // 复杂交易中的类型
    public $category_id; // seller 产品分类

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        return [
            'q' => ['required', function ($attribute, $value, $fail) {
                if (StringHelper::stringCharactersLen($value) < 2) {
                    $fail('MPN or SKU must be greater than 2 characters.');
                    return;
                }
            }],
            'type' => ['required', Rule::in(ModuleType::getValues())],
            'complex_transaction' => [
                Rule::requiredIf(function () {
                    return $this->type == ModuleType::COMPLEX_TRANSACTION;
                }),
                'nullable',
                Rule::in(['rebate', 'margin', 'future'])
            ],
            'category_id' => [
                Rule::requiredIf(function () {
                    return $this->type == ModuleType::PRODUCT_TYPE;
                }),
                'nullable',
                'integer',
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getAutoLoadRequestData()
    {
        return $this->request->get();
    }

    public function search()
    {
        if (!$this->isValidated()) {
            // 校验不通过返回错误信息
            return [
                'error' => $this->getFirstError(),
            ];
        }
        // 实际业务处理
        $customerId = customer()->getId();
        $builder = Product::query()->alias('p')
            ->leftJoinRelations(['customerPartnerToProduct as ctp'])
            ->where(BaseInfo::AVAILABLE_CONDITION)
            ->where('ctp.customer_id', $customerId)
            ->where(function ($query) {
                $query->where('p.sku', 'like', "%{$this->q}%")
                    ->orWhere('p.mpn', 'like', "%{$this->q}%");
            });
        // 主推产品 seller 上架产品 无其他要求
        // 产品推荐 seller 上架产品 无其他要求
        // 复杂交易 seller 上架产品 & 相对应复杂交易
        if ($this->type == ModuleType::COMPLEX_TRANSACTION) {
            $complexTransactionProductIds = app(ProductRepository::class)->getComplexTransactionProductsBySellerId($customerId, [$this->complex_transaction => true]);
            $builder = $builder->whereIn('p.product_id', $complexTransactionProductIds);
        }
        // 产品分类 seller 上架产品 & 在此分类下
        if ($this->type == ModuleType::PRODUCT_TYPE) {
            $categoryIdsProductIds = app(ProductRepository::class)->getProductIdsByCategoryIds([$this->category_id], $customerId);
            $builder = $builder->whereIn('p.product_id', $categoryIdsProductIds);
        }
        $productIds = $builder
            ->orderByDesc('p.sku')
            ->select('p.product_id')
            ->get()
            ->pluck('product_id')
            ->toArray();
        // 获取产品的基础信息
        $productInfoFactory = new ProductInfoFactory();
        $baseInfos = $productInfoFactory->withIds($productIds)->getBaseInfoRepository()
            ->withCustomerId($customerId)
            ->getInfos();
        $products = [];
        foreach ($baseInfos as $baseInfo) {
            $products[] = [
                'id' => $baseInfo->id,
                'image' => $baseInfo->getImage(60, 60),
                'name' => $baseInfo->getName(),
                'sku' => $baseInfo->sku,
                'mpn' => $baseInfo->mpn,
                'price' => $baseInfo->getShowPrice(null, ['symbolSmall' => true]),
                'qty' => $baseInfo->getShowQty(),
                'tags' => $baseInfo->getShowTags(),
                'available' => (int)$baseInfo->getIsAvailable(),
            ];
        }

        return [
            'products' => $products
        ];
    }
}
