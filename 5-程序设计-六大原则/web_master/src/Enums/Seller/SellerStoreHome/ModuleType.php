<?php

namespace App\Enums\Seller\SellerStoreHome;

use App\Models\Seller\SellerStore\HomeModuleJson\BaseModule;
use App\Models\Seller\SellerStore\HomeModuleJson\ModuleBanner;
use App\Models\Seller\SellerStore\HomeModuleJson\ModuleComplexTransaction;
use App\Models\Seller\SellerStore\HomeModuleJson\ModuleMainProduct;
use App\Models\Seller\SellerStore\HomeModuleJson\ModuleProductRank;
use App\Models\Seller\SellerStore\HomeModuleJson\ModuleProductRecommend;
use App\Models\Seller\SellerStore\HomeModuleJson\ModuleProductType;
use App\Models\Seller\SellerStore\HomeModuleJson\ModuleStoreIntroduction;
use Framework\Enum\BaseEnum;
use InvalidArgumentException;

class ModuleType extends BaseEnum
{
    const BANNER = 'banner'; // 全屏轮播
    const MAIN_PRODUCT = 'main_product'; // 主推产品
    const PRODUCT_RECOMMEND = 'product_recommend'; // 产品推荐
    const COMPLEX_TRANSACTION = 'complex_transaction'; // 复杂交易
    const PRODUCT_TYPE = 'product_type'; // 产品分类
    const PRODUCT_RANK = 'product_rank'; // 产品排名
    const STORE_INTRODUCTION = 'store_introduction'; // 店铺介绍

    public static function getModuleModelByValue($value): BaseModule
    {
        $map = [
            self::BANNER => ModuleBanner::class,
            self::MAIN_PRODUCT => ModuleMainProduct::class,
            self::PRODUCT_RECOMMEND => ModuleProductRecommend::class,
            self::COMPLEX_TRANSACTION => ModuleComplexTransaction::class,
            self::PRODUCT_TYPE => ModuleProductType::class,
            self::PRODUCT_RANK => ModuleProductRank::class,
            self::STORE_INTRODUCTION => ModuleStoreIntroduction::class,
        ];
        if (!isset($map[$value])) {
            throw new InvalidArgumentException("{$value} 不存在");
        }
        return app()->make($map[$value]);
    }
}
