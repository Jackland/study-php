<?php

namespace App\Models\Seller\SellerStore\HomeModuleJson;

use Framework\Model\BaseValidateModel;

abstract class BaseModule extends BaseValidateModel
{
    const MARK = 'mark';
    const REMOVE = 'remove';

    private $isFullValidate = false;
    private $shouldValidateProductAvailable = false;
    private $markOrRemoveProductUnavailable = self::REMOVE; // remove/mark
    private $isSellerEdit = false;

    /**
     * 设置是否需要全量校验
     * @param bool $is
     */
    public function setFullValidate(bool $is)
    {
        $this->isFullValidate = $is;
    }

    /**
     * 是否需要全量校验
     * @return bool
     */
    public function isFullValidate(): bool
    {
        return $this->isFullValidate;
    }

    /**
     * 设置是否需要校验产品的可用性
     * @param bool $is
     */
    public function setValidateProductAvailable(bool $is)
    {
        $this->shouldValidateProductAvailable = $is;
    }

    /**
     * 是否需要校验产品的可用性
     * @return bool
     */
    public function shouldValidateProductAvailable(): bool
    {
        return $this->shouldValidateProductAvailable;
    }

    /**
     * 设置对于不可用的产品是标记为不可用
     */
    public function setProductUnavailableMark()
    {
        $this->markOrRemoveProductUnavailable = self::MARK;
    }

    /**
     * 不可用产品是否移除
     * @return bool
     */
    public function isUnavailableProductRemove(): bool
    {
        return $this->markOrRemoveProductUnavailable === self::REMOVE;
    }

    /**
     * 不可用产品是否标记
     * @return bool
     */
    public function isUnavailableProductMark(): bool
    {
        return $this->markOrRemoveProductUnavailable === self::MARK;
    }

    /**
     * 是 seller 编辑
     * @return $this
     */
    public function setSellerEdit(): self
    {
        $this->isSellerEdit = true;

        return $this;
    }

    /**
     * 是否是 seller 编辑
     * @return bool
     */
    public function isSellerEdit(): bool
    {
        return $this->isSellerEdit;
    }

    /**
     * 获取数据库需要的数据
     * @return array
     */
    abstract public function getDBData(): array;

    /**
     * 获取视图需要的数据
     * @return array
     */
    abstract public function getViewData(): array;

    /**
     * 对 buyer 是否可见
     * @param array $dbData
     * @return bool
     */
    public function canShowForBuyer(array $dbData): bool
    {
        if (!$dbData) {
            // 未编辑模块时模块数据为空，不可见
            return false;
        }

        // 其他情况，比如是否有产品时

        return true;
    }
}
