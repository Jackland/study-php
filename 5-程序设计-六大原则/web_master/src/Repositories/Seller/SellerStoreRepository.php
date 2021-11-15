<?php

namespace App\Repositories\Seller;

use App\Enums\Seller\SellerStoreHome\ModuleType;
use App\Models\Seller\SellerStore;
use App\Models\Seller\SellerStore\HomeModuleJson\SellerBasedModuleInterface;
use Framework\Helper\Json;

class SellerStoreRepository
{
    /**
     * 获取已发布的店铺主页的所有模块或单个模块的数图数据
     * @param int $sellerId
     * @param null|string $moduleType
     * @return array
     */
    public function getStoreHomeModuleViewData(int $sellerId, $moduleType = null): array
    {
        $json = SellerStore::query()->where('seller_id', $sellerId)->value('store_home_json');
        if ($moduleType === null) {
            // 所有模块
            return $this->coverModulesDBJsonToViewData($json, $sellerId);
        }
        // 单个模块
        $module = ModuleType::getModuleModelByValue($moduleType);
        if ($json) {
            foreach (Json::decode($json) as $item) {
                if ($item['type'] == $moduleType) {
                    $module->loadAttributes($item['data']);
                    return $module->getViewData();
                }
            }
        }
        return $module->getViewData();
    }

    /**
     * 转化数据库的 json 数据到视图需要的数据
     * @param string|null $json
     * @param int $sellerId
     * @param bool $isInSellerEdit 是否是 seller 在编辑修改，为 false 时，会过滤对 buyer 不可见的模块（比如模块下无产品的时候）和不可用的产品
     * @return array
     */
    public function coverModulesDBJsonToViewData(?string $json, int $sellerId, bool $isInSellerEdit = false): array
    {
        if (!$json) {
            return [];
        }
        $modules = Json::decode($json);
        return array_filter(array_map(function ($item) use ($sellerId, $isInSellerEdit) {
            $module = ModuleType::getModuleModelByValue($item['type']);
            if ($module instanceof SellerBasedModuleInterface) {
                $module->setSellerId($sellerId);
            }
            if ($isInSellerEdit && !$item['data']) {
                // seller 编辑时空模块也需要返回
                return $item;
            }
            if ($isInSellerEdit) {
                // seller 编辑时标记产品为 unavailable
                $module->setProductUnavailableMark();
                $module->setSellerEdit();
            }
            $module->loadAttributes($item['data']);

            if (!$isInSellerEdit && !$module->canShowForBuyer($item['data'])) {
                // 非 seller 编辑时，并且模块不可见时
                return null;
            }
            $item['data'] = $module->getViewData();
            return $item;
        }, $modules));
    }

    /**
     * 获取已发布的店铺介绍页的视图数据
     * @param int $sellerId
     * @return array
     */
    public function getStoreIntroductionViewData(int $sellerId): array
    {
        $json = SellerStore::query()->where('seller_id', $sellerId)->value('store_introduction_json');
        return $this->coverStoreIntroductionJsonToViewData($json);
    }

    /**
     * 转化店铺介绍的 json 数据到 视图数据
     * 当 json 为空时会返回默认值
     * @param $json
     * @return array
     */
    public function coverStoreIntroductionJsonToViewData($json): array
    {
        $info = new SellerStore\SellerStoreIntroductionJson();
        if ($json) {
            $info->loadAttributes(Json::decode($json));
        }
        return $info->getViewData();
    }

    /**
     * 转化店铺介绍的 json 数据到 db数据
     * 当 json 为空时会返回默认值
     * @param $json
     * @return array
     */
    public function coverStoreIntroductionJsonToDBData($json): array
    {
        $info = new SellerStore\SellerStoreIntroductionJson();
        if ($json) {
            $info->loadAttributes(Json::decode($json));
        }
        return $info->getDBData();
    }

}
