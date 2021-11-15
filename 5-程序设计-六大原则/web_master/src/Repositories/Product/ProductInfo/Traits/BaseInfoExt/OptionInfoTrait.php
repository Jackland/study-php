<?php

namespace App\Repositories\Product\ProductInfo\Traits\BaseInfoExt;

use App\Models\Product\Option\Option;

trait OptionInfoTrait
{
    /**
     * 获取颜色材质信息
     * @return array{color_name: string, material_name: string}
     */
    public function getOptionInfo(): array
    {
        $this->loadRelations(['productOptionValues', 'productOptionValues.optionValueDescription']);
        $data = [
            'color_name' => '',
            'material_name' => '',
        ];
        $options = $this->product->productOptionValues->keyBy('option_id');
        if ($options->has(Option::COLOR_OPTION_ID) && $options[Option::COLOR_OPTION_ID]->optionValueDescription) {
            $data['color_name'] = $options[Option::COLOR_OPTION_ID]->optionValueDescription->name;
        }
        if ($options->has(Option::MATERIAL_OPTION_ID) && $options[Option::MATERIAL_OPTION_ID]->optionValueDescription) {
            $data['material_name'] = $options[Option::MATERIAL_OPTION_ID]->optionValueDescription->name;
        }
        if ($options->has(Option::MIX_OPTION_ID) && $options[Option::MIX_OPTION_ID]->optionValueDescription) {
            // 如果存在 13 的数据，且颜色和材质没值的，把13的值给颜色或材质（优先颜色）
            if (!$data['color_name']) {
                $data['color_name'] = $options[Option::MIX_OPTION_ID]->optionValueDescription->name;
            } elseif (!$data['material_name']) {
                $data['material_name'] = $options[Option::MIX_OPTION_ID]->optionValueDescription->name;
            }
        }

        return $data;
    }

    /**
     * 颜色名称
     * @return string
     */
    public function getColorName(): string
    {
        return $this->getOptionInfo()['color_name'];
    }

    /**
     * 材质名称
     * @return string
     */
    public function getMaterialName(): string
    {
        return $this->getOptionInfo()['material_name'];
    }
}
