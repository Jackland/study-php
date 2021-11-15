<?php

use App\Enums\Seller\SellerStoreHome\ModuleProductRecommendAngleTipKey;
use App\Enums\Seller\SellerStoreHome\ModuleProductRecommendTitleKey;
use App\Enums\Seller\SellerStoreHome\ModuleProductTypeAutoSortType;
use App\Enums\Seller\SellerStoreHome\ModuleProductTypeMode;
use App\Enums\Seller\SellerStoreHome\ModuleStoreIntroductionIcon;
use App\Enums\Seller\SellerStoreHome\ModuleType;

/**
 * 此处仅做前后端对接的文档和备注使用，不做业务相关逻辑
 * 同步以下文档：
 * @link https://ones.ai/wiki/#/team/8wP6mUy7/space/7a6Hof7n/page/DxPovhCJ
 */

/**
 * 说明：
 * 1. 以下为整个首页模块的存储结构，举例使用所有模块的最大化内容
 * 2. 所有产品的数据非完整结构，具体按照实际模块需要的数据展示时给出，数据库仅存储产品的 id（产品分类类似）
 * 3. 所有的 bool 类型使用 0 和 1，保证前后端处理逻辑一致
 * 4. 所有对象值形式的（如：ModuleType::BANNER），全部为枚举，给到前端会有 options 选项
 * 5. 以下内容未标识必填说明，具体以需求文档的为准
 */

$allModules = [
    [
        'type' => ModuleType::BANNER, // 全屏轮播
        'data' => [
            'banners' => [
                ['pic' => 'wkseller/xxx', 'link' => 'https://b2b.gigacloudlogistics.com/'],
                ['pic' => 'wkseller/xxx', 'link' => 'https://b2b.gigacloudlogistics.com/'],
            ],
        ],
    ],
    [
        'type' => ModuleType::MAIN_PRODUCT, // 主推产品
        'data' => [
            'products' => [
                [
                    'product' => [
                        'id' => 1,
                        'image' => 'wkseller/xxx',
                        'name' => 'name',
                        '...' => '...'
                    ],
                    'tags' => ['tagA', 'tagB'],
                ],
                [
                    'product' => [
                        'id' => 2,
                        'image' => 'wkseller/xxx',
                        'name' => 'name',
                        '...' => '...'
                    ],
                    'tags' => ['tagA', 'tagB'],
                ],
            ],
            'title_show' => 0,
            'title' => 'title',
            'title_sub' => 'title sub',
            'display_value' => [
                'product_name' => 1,
                'item_code' => 1,
                'complex_transaction' => 1,
                'tag' => 1,
                'price' => 1,
                'qty_available' => 1,
            ],
        ],
    ],
    [
        'type' => ModuleType::PRODUCT_RECOMMEND, // 产品推荐
        'data' => [
            'products' => [
                [
                    'id' => 1,
                    'image' => 'wkseller/xxx',
                    'name' => 'name',
                    '...' => '...'
                ],
                [
                    'id' => 2,
                    'image' => 'wkseller/xxx',
                    'name' => 'name',
                    '...' => '...'
                ],
            ],
            'title_show' => 1,
            'title_key' => ModuleProductRecommendTitleKey::NEW_ARRIVALS,
            'title_value' => 'New Arrivals', // 前端显示用，不存库
            'angle_tip_key' => ModuleProductRecommendAngleTipKey::NEW,
            'angle_tip_value' => 'New', // 前端显示用，不存库
        ],
    ],
    [
        'type' => ModuleType::COMPLEX_TRANSACTION, // 复杂交易
        'data' => [
            'rebate' => [
                'products' => [
                    ['id' => 1, 'available' => 1, '...' => '...'],
                    ['id' => 2, 'available' => 0, '...' => '...'],
                ],
            ],
            'margin' => [
                'products' => [
                    ['id' => 1, 'available' => 1, '...' => '...'],
                    ['id' => 2, 'available' => 0, '...' => '...'],
                ],
            ],
            'future' => [
                'products' => [
                    ['id' => 1, 'available' => 1, '...' => '...'],
                    ['id' => 2, 'available' => 0, '...' => '...'],
                ],
            ],
            'title_show' => 1,
            'display_value' => [
                'product_name' => 1,
                'item_code' => 1,
                'price' => 1,
                'template_info' => 1,
                'buy_now' => 1,
                'rebate' => 1,
            ],
        ],
    ],
    [
        'type' => ModuleType::PRODUCT_TYPE, // 产品分类
        'data' => [
            'mode' => ModuleProductTypeMode::AUTO,
            'mode_auto' => [
                'product_types' => [
                    [
                        'type_id' => 1,
                        'type_name' => 'product type name',
                        'products' => [
                            ['id' => 1, 'available' => 1, '...' => '...'],
                            ['id' => 1, 'available' => 1, '...' => '...'],
                            ['id' => 1, 'available' => 1, '...' => '...'],
                        ],
                    ],
                ],
                'sort_type' => ModuleProductTypeAutoSortType::HOT_SALE,
                'each_count' => 4, // 数量
            ],
            'mode_manual' => [
                'product_types' => [
                    [
                        'type_id' => 1,
                        'type_name' => 'product type name',
                        'products' => [
                            ['id' => 1, 'available' => 1, '...' => '...'],
                            ['id' => 1, 'available' => 1, '...' => '...'],
                            ['id' => 1, 'available' => 1, '...' => '...'],
                        ],
                    ],
                ],
            ],
        ]
    ],
    [
        'type' => ModuleType::PRODUCT_RANK, // 产品排名
        'data' => [
            'title_show' => 1,
            'title_sub' => 'title sub',
            'count' => 3, // 数量
            'display_value' => [
                'item_code' => 1,
                'price' => 1,
                'qty_available' => 1,
                'complex_transaction' => 1,
                'sales_ranking' => 1,
                'cart_tip' => 1,
            ],
        ],
    ],
    [
        'type' => ModuleType::STORE_INTRODUCTION, // 店铺介绍
        'data' => [
            'pics' => [
                ['pic' => 'wkseller/xxxx'],
                ['pic' => 'wkseller/xxxx'],
                ['pic' => 'wkseller/xxxx'],
            ],
            'desc' => 'content with \n aaa',
            'tags' => [
                [
                    'icon' => ModuleStoreIntroductionIcon::ICON_1,
                    'title' => 'title',
                    'content' => 'content with \n aaa',
                ],
                [
                    'icon' => ModuleStoreIntroductionIcon::ICON_3,
                    'title' => 'title',
                    'content' => 'content with \n aaa',
                ],
                [
                    'icon' => ModuleStoreIntroductionIcon::ICON_2,
                    'title' => 'title',
                    'content' => 'content with \n aaa',
                ],
                [
                    'icon' => ModuleStoreIntroductionIcon::ICON_6,
                    'title' => 'title',
                    'content' => 'content with \n aaa',
                ],
            ],
        ],
    ],
];
