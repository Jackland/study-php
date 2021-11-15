<?php

namespace App\Repositories\Product;


use App\Models\Product\Option\Option;
use App\Models\Product\Product;
use App\Models\Setting;
use DB;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;

class ProductOptionRepository
{
    /**
     * 获取某一个产品的 颜色材质等信息
     * @param int $productId
     * @return array
     */
    public function getProductOptionByProductId(int $productId)
    {
        $results = $this->getOptionByProductIds($productId);
        if (!$results) {
            return [];
        }

        $result = [
            'product_id' => $results[0]['product_id'],
            'color_name' => $results[0]['color_name'],
            'material_name' => $results[0]['material_name'],
        ];
        return $result;
    }

    /**
     * 获取产品 颜色材质等option信息
     * @param array $productIds
     * @return array
     */
    public function getOptionByProductIds($productIds)
    {
        if (is_string($productIds) || is_int($productIds)) {
            $productIds = [(int)$productIds];
        }


        $results = Product::query()
            ->from('oc_product as p')
            ->leftJoin('oc_product_description as pd', 'pd.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_product AS cp', 'cp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_option_value AS pov_ncolor', function (JoinClause $j) {
                $j->on('pov_ncolor.product_id', '=', 'p.product_id')
                    ->where('pov_ncolor.option_id', '=', Option::COLOR_OPTION_ID);//New Color #646需求之后
            })
            ->leftJoin('oc_product_option_value AS pov_material', function (JoinClause $j) {
                $j->on('pov_material.product_id', '=', 'p.product_id')
                    ->where('pov_material.option_id', '=', Option::MATERIAL_OPTION_ID);//Material
            })
            ->leftJoin('oc_option_value_description AS ovd_ncolor', 'ovd_ncolor.option_value_id', '=', 'pov_ncolor.option_value_id')
            ->leftJoin('oc_option_value_description AS ovd_material', 'ovd_material.option_value_id', '=', 'pov_material.option_value_id')
            ->select(['p.product_id', 'p.sku', 'p.mpn', 'p.image', 'pd.name', 'cp.customer_id'])
            ->addSelect(new Expression("IFNULL(p.product_size, '') AS product_size"))
            ->addSelect(new Expression("
            CASE
                WHEN ovd_ncolor.name IS NOT NULL THEN ovd_ncolor.name
            ELSE ''
            END as color_name
            "))
            ->addSelect(new Expression("IFNULL(ovd_material.name, '') AS material_name"))
            ->addSelect(['ovd_ncolor.name AS ncolor_name', 'ovd_ncolor.option_value_id AS ncolor_option_value_id'])
            ->addSelect(['ovd_material.option_value_id AS material_option_value_id'])
            ->whereIn('p.product_id', $productIds)
            ->orderBy('p.product_id', 'DESC')
            ->groupBy('p.product_id')
            ->get()
            ->toArray();

        $customerProductIds = [];
        foreach ($results as $result) {
            if (!empty($result['color_name'])) {
                continue;
            }
            $customerProductIds[$result['customer_id']][] = $result['product_id'];
        }

        $historyProductOptionValues = [];
        foreach ($customerProductIds as $customerId => $productIds) {
            $historyProductOptionValues += $this->getProductOptionValueByProductIds($productIds, Option::MIX_OPTION_ID, $customerId);
        }

        foreach ($results as &$result) {
            if (empty($result['color_name']) && isset($historyProductOptionValues[$result['product_id']])) {
                $result['color_name'] = $historyProductOptionValues[$result['product_id']];
            }
        }
        unset($result);

        return $results;
    }


    /**
     * 获取一组商品的属性值
     * @param array $productIds
     * @param int $optionId
     * @param int $customerId
     * @return array
     */
    public function getProductOptionValueByProductIds(array $productIds, int $optionId, int $customerId = 0): array
    {
        $queryOrigin = DB::table('oc_product_option_value as p')
            ->select(['op.name as attr', 'p.product_id'])
            ->leftJoin('oc_option_description as od', ['p.option_id' => 'od.option_id'])
            ->leftJoin(
                'oc_option_value_description as op',
                [
                    'op.option_id' => 'p.option_id',
                    'op.option_value_id' => 'p.option_value_id'
                ]
            )
            ->where([
                'od.option_id' => $optionId,
                'op.language_id' => 1,
            ])
            ->whereIn('p.product_id', $productIds);
        // 覆盖自定义颜色
        if ($customerId) {
            $queryCustom = DB::table('oc_product_option_value as p')
                ->select(['cod.name as attr', 'p.product_id'])
                ->leftJoin(
                    'oc_customer_option_description as cod',
                    [
                        'cod.option_id' => 'p.option_id',
                        'cod.option_value_id' => 'p.option_value_id',
                    ]
                )
                ->where([
                    'cod.customer_id' => $customerId,
                    'p.option_id' => $optionId,
                    'cod.language_id' => 1,
                ])
                ->whereIn('p.product_id', $productIds);
        }
        if (isset($queryCustom)) {
            $queryOrigin = $queryOrigin->union($queryCustom);
        }
        $res = $queryOrigin->get();
        if ($res->isEmpty()) {
            return [];
        }
        $ret = [];
        $res->each(function ($val) use (&$ret) {
            $ret[$val->product_id] = htmlspecialchars_decode($val->attr);
        });

        return $ret;
    }
}