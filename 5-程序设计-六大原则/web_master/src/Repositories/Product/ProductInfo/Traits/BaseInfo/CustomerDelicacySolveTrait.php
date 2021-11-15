<?php

namespace App\Repositories\Product\ProductInfo\Traits\BaseInfo;

/**
 * BaseInfo 支持精细化
 */
trait CustomerDelicacySolveTrait
{
    /**
     * 精细化价格，为0表示无精细化价格
     * @var float
     */
    private $delicacyPrice = 0;
    /**
     * 精细化未来价格，为0时表示无未来价格
     * @var float
     */
    private $delicacyFuturePrice = 0;
    /**
     * 精细化价格是否可见
     * @var bool
     */
    private $delicacyPriceVisible = false;
    /**
     * 精细化库存是否可见
     * @var bool
     */
    private $delicacyQtyVisible = false;
    /**
     * 精细化产品是否可见
     * @var bool|null
     */
    private $delicacyProductVisible = null;

    /**
     * 设置精细化价格
     * @param float $price
     */
    public function setDelicacyPrice(float $price)
    {
        $this->delicacyPrice = $price;
    }

    /**
     * 设置精细化未来价格
     * @param float $price
     */
    public function setDelicacyFuturePrice(float $price)
    {
        $this->delicacyFuturePrice = $price;
    }

    /**
     * 设置精细化价格是否可见
     * @param bool $is
     */
    public function setDelicacyPriceVisible(bool $is)
    {
        $this->delicacyPriceVisible = $is;
    }

    /**
     * 设置精细化库存是否可见
     * @param bool $is
     */
    public function setDelicacyQtyVisible(bool $is)
    {
        $this->delicacyQtyVisible = $is;
    }

    /**
     * 设置精细化产品是否可见
     * @param bool $is
     */
    public function setDelicacyProductVisible(bool $is)
    {
        $this->delicacyProductVisible = $is;
    }
}
