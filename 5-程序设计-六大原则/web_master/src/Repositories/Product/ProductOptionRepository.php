<?php

namespace App\Repositories\Product;

use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductStatus;
use App\Models\Product\Option\Option;
use App\Models\Product\Option\OptionValue;
use App\Models\Product\Option\OptionValueDescription;
use App\Models\Product\Product;
use App\Models\Product\ProductAssociate;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;

class ProductOptionRepository
{
    /**
     * 获取某个属性的值
     * @param int $optionId
     * @return OptionValueDescription[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getOptionsById(int $optionId)
    {
        return OptionValueDescription::query()->alias('d')
            ->join('oc_option_value as v', 'd.option_value_id', '=', 'v.option_value_id')
            ->where('v.status', YesNoEnum::YES)
            ->where('v.is_deleted', YesNoEnum::NO)
            ->where('d.option_id', $optionId)
            ->orderBy('d.name')
            ->select(['d.*'])
            ->get();
    }

    /**
     * 获取某个属性列表,附带排序
     * @param int $optionId
     * @return OptionValueDescription[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getOptionsWithSortById(int $optionId)
    {
        return OptionValue::query()->valid()
            ->join('oc_option_value_description as ovd', 'oc_option_value.option_value_id', '=', 'ovd.option_value_id')
            ->where('ovd.option_id', $optionId)
            ->orderBy('ovd.name')
            ->selectRaw('ovd.*')
            ->get();
    }

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
     *
     * @param int $productId
     * @return string
     */
    public function getProductAttribute(int $productId)
    {
        $productOption = $this->getProductOptionByProductId($productId);
        $color_name = isset($productOption['color_name']) ? $productOption['color_name'] : '';
        $material_name = isset($productOption['material_name']) ? $productOption['material_name'] : '';

        $attrbuteArr = [];
        if ($color_name) {
            $attrbuteArr[] = $color_name;
        }
        if ($material_name) {
            $attrbuteArr[] = $material_name;
        }

        return implode(' + ', $attrbuteArr);
    }

    /**
     * Buyer的产品详情页，获取同款产品
     * @param int $productId
     * @return array
     * @throws \Exception
     */
    public function getProductOptionAssociateForBuyer($productId)
    {
        $productOptionResults = $this->getOptionAssociateForBuyerByProductId($productId);
        $productOptionList = $this->getProductOptionsForPage($productId, $productOptionResults);
        return $productOptionList;
    }


    /**
     * 生成同款不同色产品在产品详情页的Option选项列表
     * @param int $productId 当前产品
     * @param array $productOptionListDB 参数来自getOptionAssociateByProductId()方法返回值 或 getOptionByProductIds()方法返回值
     * @param array $replaceArr [ product_id=>['key'=>'value',...],... ] 如Seller预览产品详情页，需要替换Option选项列表中的信息
     * @return array
     * @throws \Exception
     */
    public function getProductOptionsForPage($productId, $productOptionListDB = [], $replaceArr = [])
    {
        $modelToolImage = load()->model('tool/image');

        $width = configDB('theme_' . configDB('config_theme') . '_image_additional_width', 74);
        $height = configDB('theme_' . configDB('config_theme') . '_image_additional_height', 74);

        $fillerData = app(ProductRepository::class)->getProductExtByIds(array_column($productOptionListDB,'product_id'));
        $customFieldData = app(ProductRepository::class)->getCustomFieldByIds(array_column($productOptionListDB,'product_id'));

        $productOptionList = [];
        $productOptionFirst = [];
        if ($productOptionListDB) {
            foreach ($productOptionListDB as $result) {
                $image = $result['image'];
                $colorName = $result['color_name'];
                $materialName = $result['material_name'];
                //填充物
                $filler = $fillerData[$result['product_id']]['filler_option_value']['name'] ?? '';
                //自定义字段
                $customFields = $customFieldData[$result['product_id']] ?? [];

                if (isset($replaceArr[$result['product_id']])) {
                    $image = $replaceArr[$result['product_id']]['image'];
                    $colorName = $replaceArr[$result['product_id']]['color_name'];
                    $materialName = $replaceArr[$result['product_id']]['material_name'];
                }
                $tmp = [];
                mb_strlen($colorName) > 0 && $tmp[] = $colorName;
                mb_strlen($materialName) > 0 && $tmp[] = $materialName;
                mb_strlen($filler) > 0 && $tmp[] = $filler;

                $customFields && $tmp = array_merge($tmp, array_column($customFields, 'value'));
                $result['title'] = implode(' + ', $tmp);
                $result['title_html'] = implode('<span class="text-warning"> + </span>', $tmp);
                $result['thumb'] = $modelToolImage->resize($image, $width, $height);
                if ($result['product_id'] == $productId) {
                    $productOptionFirst = $result;
                } else {
                    $productOptionList[] = $result;
                }
            }
            array_unshift($productOptionList, $productOptionFirst);
        }
        return $productOptionList;
    }

    /**
     * 获取产品 颜色材质等option信息
     * @param array|string|int $productIds
     * @return array
     */
    public function getOptionByProductIds($productIds)
    {
        if (is_string($productIds) || is_int($productIds)) {
            $productIds = [(int)$productIds];
        }


        $results = Product::query()->alias('p')
            ->leftJoinRelations(['description as pd'])
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

        /** @var \ModelCatalogProduct $mcp */
        $mcp = load()->model('catalog/product');
        $historyProductOptionValues = [];
        foreach ($customerProductIds as $customerId => $productIds) {
            $historyProductOptionValues += $mcp->getProductOptionValueByProductIds($productIds, Option::MIX_OPTION_ID, $customerId);
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
     * 获取同款产品，以及图片、颜色、材质等信息
     * Buyer产品详情页专用
     * @param int $productId
     * @return array [['product_id'=>'', 'image'=>'', product_size'=>'', 'color_name'=>'', 'material_name'=>''],......]
     */
    public function getOptionAssociateForBuyerByProductId(int $productId)
    {
        $results = ProductAssociate::query()->alias('pa')
            ->leftJoin('oc_product AS p', 'p.product_id', 'pa.associate_product_id')
            ->where('pa.product_id', $productId)
            ->where('p.status', ProductStatus::ON_SALE)
            ->where('p.is_deleted', YesNoEnum::NO)
            ->get()
            ->toArray();
        $associate_product_ids = array_column($results, 'associate_product_id');

        if (!$associate_product_ids || !is_array($associate_product_ids)) {
            return [];
        }


        $results = $this->getOptionByProductIds($associate_product_ids);
        return $results;
    }
}
