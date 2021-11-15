<?php

namespace App\Models\Product\Channel;

use App\Enums\Product\Channel\ModuleProductsNums;
use App\Enums\Product\Channel\ModuleType;

abstract class BaseChannel
{
    const NEW_STORE_CHANNEL_PAGE_LIMIT = 21;
    const FEATURE_STORE_CHANNEL_PAGE_LIMIT = 12;
    private $isStoreChannel = false;

    /**
     * 设置频道是否是store
     * @return bool
     */
    public function getIsStoreChannel(): bool
    {
        return $this->isStoreChannel;
    }

    /**
     * 设置频道store状态
     * @param bool $isStoreChannel
     */
    protected function setIsStoreChannel(bool $isStoreChannel)
    {
        $this->isStoreChannel = $isStoreChannel;
    }

    /**
     * 根据channel获取当前频道的产品信息
     * @param int $type
     * @param array $param
     * @return array
     */
    public function getProductsInfoByIds(int $type, array $param): array
    {
        return $this->getBaseProductsInfo(...func_get_args());
    }

    /**
     * 根据channel获取当前频道的seller店铺下的产品信息
     * @param int $type
     * @param array $param
     * @return array
     */
    public function getProductsInfoBySellerIds(int $type, array $param): array
    {
        return $this->getBaseProductsInfo(...func_get_args());
    }

    /**
     * 下拉加载通过分类查询的信息
     * @param int $type
     * @param array $param
     * @return array
     */
    public function getProductsInfoBySearch(int $type, array $param): array
    {
        return $this->getBaseProductsInfo(...func_get_args());
    }

    private function getBaseProductsInfo(int $type, array $param): array
    {
        $module = ModuleType::getModuleModelByValue($type);
        // 设置当前模块展示个数
        if ($this->isStoreChannel) {
            if($type == ModuleType::NEW_STORE){
                $module->setShowNum(self::NEW_STORE_CHANNEL_PAGE_LIMIT);
            }
            if($type == ModuleType::FEATURED_STORES){
                $module->setShowNum(self::FEATURE_STORE_CHANNEL_PAGE_LIMIT);
            }

        } else {
            $module->setShowNum(ModuleProductsNums::getDescription($type, 20));
        }

        return $module->getData($param);
    }

    /**
     * 获取首次加载时channel整体数据 非异步加载数据无须param
     * @param array $param
     * @return array
     */
    abstract function getChannelData(array $param = []): array;
}
