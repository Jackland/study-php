<?php

namespace App\Repositories\Product\ProductInfo\Traits\BaseInfoExt;

use App\Enums\Common\CountryEnum;
use Framework\Exception\NotSupportException;

/**
 * 尺寸相关的：尺寸、组装大小等
 */
trait SizeInfoTrait
{
    /**
     * 获取尺寸信息，长宽高重量
     * @param array $config
     * @return array{unit_length: string, unit_weight: string, general: array, combo: array}
     */
    public function getSizeInfo(array $config = []): array
    {
        $config = array_merge([
            'unit' => 'default', // 强制使用某单位：cm/inches
        ], $config);

        $precision = 2;
        $useInches = $config['unit'] === 'default'
            ? $this->getCountryId() === CountryEnum::AMERICA
            : $config['unit'] === 'inches';
        $data = [
            'unit_length' => $useInches ? 'inches' : 'cm',
            'unit_weight' => $useInches ? 'lbs' : 'kg',
            'general' => [], // 普通产品的尺寸
            'combo' => [], // combo 的尺寸
        ];
        if ($this->is_combo) {
            $this->loadRelations(['combos', 'combos.setProduct']);
            $sizes = [];
            foreach ($this->product->combos as $combo) {
                if ($useInches) {
                    $size = [
                        'length' => number_format($combo->setProduct->length, $precision),
                        'width' => number_format($combo->setProduct->width, $precision),
                        'height' => number_format($combo->setProduct->height, $precision),
                        'weight' => number_format($combo->setProduct->weight, $precision),
                    ];
                } else {
                    $size = [
                        'length' => number_format($combo->setProduct->length_cm, $precision),
                        'width' => number_format($combo->setProduct->width_cm, $precision),
                        'height' => number_format($combo->setProduct->height_cm, $precision),
                        'weight' => number_format($combo->setProduct->weight_kg, $precision),
                    ];
                }
                $size['product_id'] = $combo->set_product_id;
                $size['qty'] = $combo->qty;
                $size['sku'] = $combo->setProduct->sku;
                $sizes[] = $size;
            }
            $data['combo'] = $sizes;
        } else {
            if ($useInches) {
                $size = [
                    'length' => number_format($this->product->length, $precision),
                    'width' => number_format($this->product->width, $precision),
                    'height' => number_format($this->product->height, $precision),
                    'weight' => number_format($this->product->weight, $precision),
                ];
            } else {
                $size = [
                    'length' => number_format($this->product->length_cm, $precision),
                    'width' => number_format($this->product->width_cm, $precision),
                    'height' => number_format($this->product->height_cm, $precision),
                    'weight' => number_format($this->product->weight_kg, $precision),
                ];
            }
            $data['general'] = $size;
        }

        return $data;
    }

    /**
     * 获取总体积
     * @param array $config
     * @return float
     * @throws NotSupportException
     */
    public function getFullVolume(array $config = []): float
    {
        //$info = $this->getSizeInfo($config);
        throw new NotSupportException('暂未实现，目前系统中各个地方需要用到体积的地方计算方式不太一致，需要重新整理，先占位');
    }

    /**
     * 获取总重量
     * @param array $config
     * @throws NotSupportException
     */
    public function getFullWeight(array $config = [])
    {
        //$info = $this->getSizeInfo($config);
        throw new NotSupportException('暂未实现，目前需求未知，先占位');
    }

    /**
     * 组装信息
     * @param array $config
     * @return array
     */
    public function getAssembleLengthInfo(array $config = []): array
    {
        $config = array_merge([
            'value' => false, // 给出 value 值
            'show' => true, // 给出展示值
        ], $config);

        $this->loadRelations('ext');
        $data = [];
        if (!($this->product->ext)) {
            return $data;
        }
        if ($config['value']) {
            $data = array_merge([
                'length' => $this->product->ext->assemble_length,
                'width' => $this->product->ext->assemble_width,
                'height' => $this->product->ext->assemble_height,
                'weight' => $this->product->ext->assemble_weight,
            ]);
        }
        if ($config['show']) {
            $data = array_merge([
                'length_show' => $this->product->ext->assemble_length_show,
                'width_show' => $this->product->ext->assemble_width_show,
                'height_show' => $this->product->ext->assemble_height_show,
                'weight_show' => $this->product->ext->assemble_weight_show
            ]);
        }

        return $data;
    }
}
