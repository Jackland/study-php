<?php

namespace App\Components\RemoteApi\B2BManager\DTO\Freight;

use App\Components\RemoteApi\DTO\BaseDTO;
use Illuminate\Support\Collection;

/**
 * 请求b2b manage 运费产品对象
 * @property float $length 长
 * @property float $width 宽
 * @property float $height 高
 * @property float $actualWeight 重量
 * @property int $qty 数量
 * @property int $day 天数
 * @property bool $ltlFlag 是否是超大件
 * @property bool $dangerFlag 是否是危险品
 * @property bool $comboFlag 是否是combo
 * @property Collection|FreightProductDTO[] $comboList 子combo list
 */
class FreightProductDTO extends BaseDTO
{
    private $defaultParams = [
        'length' => 0,
        'width' => 0,
        'height' => 0,
        'actualWeight' => 0,
        'ltlFlag' => false,
        'dangerFlag' => false,
        'day' => 1,
        'qty' => 1,
        'comboFlag' => false,
        'comboList' => []
    ];

    /**
     * 必须为bool的字段
     * @var string[]
     */
    private $boolConfig = [
        'ltlFlag',
        'dangerFlag',
        'comboFlag',
    ];

    public function __construct($attributes = [])
    {
        $attributes = array_merge($this->defaultParams, $attributes);
        foreach ($attributes['comboList'] as $key => $comboItem) {
            if ($comboItem instanceof FreightProductDTO) {
                $attributes['comboList'][$key] = $comboItem->toArray();
            } else {
                // 这里必须要么全是FreightProductDTO对象，要么全是数组
                break;
            }
        }
        if (!empty($attributes['comboList'])) {
            $attributes['comboFlag'] = true;

        }
        // 字段格式化
        foreach ($attributes as $key => &$attribute) {
            if (in_array($key, $this->boolConfig)) {
                $attribute = boolval($attribute);
            }
        }
        parent::__construct($attributes);
    }

    /**
     * 添加combo item
     * @param FreightProductDTO $comboItem
     * @return $this
     */
    public function addComboItem(FreightProductDTO $comboItem): FreightProductDTO
    {
        $this->attributes['comboFlag'] = true;
        $comboArr = $comboItem->toArray();
        unset($comboArr['comboFlag'], $comboArr['comboList']);
        $this->attributes['comboList'][] = $comboItem->toArray();
        if ($comboItem->ltlFlag) {
            // combo产品子产品有一个ltl，整个产品为ltl
            $this->attributes['ltlFlag'] = true;
        }
        return $this;
    }
}
