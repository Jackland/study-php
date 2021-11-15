<?php

namespace App\Repositories\SalesOrder\Validate;

use App\Enums\Product\ProductStatus;
use App\Enums\Product\ProductType;
use App\Models\Product\Product;

class salesOrderSkuValidate
{
    // 获取导单时的sku拦截逻辑，① 普通产品中如存在 [服务店铺] 产品拦截并提示失败信息，② 现货保证金 + ③ 期货保证金 + ④ 补运费产品 拦截并提示失败信息。
    private $isCollectionFromDomicile;
    private $countryId;
    private $data;
    private $errorSku;
    const SERVICES_STORE_ID = [340, 491, 631, 838];

    public function __construct()
    {
        $this->isCollectionFromDomicile = customer()->isCollectionFromDomicile();
        $this->countryId = customer()->getCountryId();
    }

    public function withSkus(array $skus): self
    {
        $new = clone $this;
        $new->data = compact('skus');
        return $new;
    }

    /**
     * 校验是否正确
     * @return array
     */
    public function validateSkus(): array
    {
        $rules = $this->getRules();
        $validator = validator($this->data, $rules, $this->getRuleMessages());
        $ret = [
            'msg' => '',
            'errorSku' => '',
            'code' => 1,
        ];
        if ($validator->fails()) {
            return [
                'msg' => $validator->errors()->first(),
                'errorSku' => $this->errorSku,
                'code' => 0,
            ];
        }

        return $ret;
    }

    protected function getRules(): array
    {
        return [
            'skus' => [
                'array',
                function ($attribute, $value, $fail) {
                    if($value){
                        $existsSku = array_map('strtoupper', $this->getExistsSku($value));
                        // 不校验平台没有的sku
                        $validSkus = array_map('strtoupper', $this->getValidSkus($value));
                        foreach ($value as $k => $v) {
                            if (!in_array(strtoupper($v), $validSkus) && in_array(strtoupper($v), $existsSku)) {
                                $this->errorSku = $v;
                                $fail('Line %s is a service that cannot be shipped as a regular item. Please check it.');
                                break;
                            }
                        }
                    }
                }
            ],
        ];
    }

    protected function getRuleMessages(): array
    {
        return [
            'required' => 'Line %s is a service that cannot be shipped as a regular item. Please check it.',
            'array' => 'Line %s is a service that cannot be shipped as a regular item. Please check it.',
        ];
    }

    /**
     * 获取导单时的sku拦截逻辑，① 普通产品中如存在 [服务店铺] 产品拦截并提示失败信息，② 现货保证金 + ③ 期货保证金 + ④ 补运费产品 拦截并提示失败信息。
     * @param array $skus
     * @return array
     */
    public function getValidSkus(array $skus): array
    {
        return Product::query()->alias('p')
            ->leftJoinRelations(['customerPartnerToProduct as ctp'])
            ->leftJoin('oc_customer as c', 'c.customer_id', 'ctp.customer_id')
            ->where('c.country_id', $this->countryId)
            ->where([
                'p.is_deleted' => 0,
                'p.status' => ProductStatus::ON_SALE,
                'p.buyer_flag' => 1,
                'p.product_type' => ProductType::NORMAL,
            ])
            ->whereIn('p.sku', $skus)
            ->whereNotIn('c.customer_id', static::SERVICES_STORE_ID)
            ->select('p.sku')
            ->distinct()
            ->get()
            ->pluck('sku')
            ->toArray();
    }

    public function getExistsSku(array $skus): array
    {
        return Product::query()->alias('p')
            ->leftJoinRelations(['customerPartnerToProduct as ctp'])
            ->leftJoin('oc_customer as c', 'c.customer_id', 'ctp.customer_id')
            ->where('c.country_id', $this->countryId)
            ->whereIn('p.sku', $skus)
            ->where(function($q){
                $q->whereIn('c.customer_id', static::SERVICES_STORE_ID)->orWhere('p.product_type','<>',ProductType::NORMAL);
            })
            ->select('p.sku')
            ->distinct()
            ->get()
            ->pluck('sku')
            ->toArray();
    }

}
