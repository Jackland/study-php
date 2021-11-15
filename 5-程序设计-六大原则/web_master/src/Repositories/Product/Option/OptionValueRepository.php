<?php

namespace App\Repositories\Product\Option;

class OptionValueRepository
{
    /**
     * 通过option_id获取信息
     *
     * @param int $id
     * @param int $age
     * @return array|object
     */
    public function getOptionValueInfo($productId, $optionId)
    {
        return db('oc_product_option_value')
            ->where('product_id', $productId)
            ->where('option_id', $optionId)
            ->first();
    }


    /**
     * 通过option_value_id获取名称
     * @param $optionValueId
     * @return \Illuminate\Database\Capsule\Manager|\Illuminate\Database\Query\Builder|mixed
     */
    public function getNameByOptionValueId($optionValueId)
    {
        if (empty($optionValueId)) {
            return '';
        }

        $result = db('oc_option_value_description')
            ->where('option_value_id', $optionValueId)
            ->value('name');
        return trim($result);
    }
}
