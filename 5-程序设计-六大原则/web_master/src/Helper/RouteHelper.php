<?php

namespace App\Helper;

use Framework\Exception\InvalidConfigException;

class RouteHelper
{
    /**
     * 当前路由在或不在某个组内
     * @param string $group
     * @param bool $isIn 为 true 表示在组内，false 表示不在组内
     * @return bool
     * @throws InvalidConfigException
     */
    public static function isCurrentMatchGroup(string $group, $isIn = true): bool
    {
        $config = [
            // 店铺主页
            'storeHome' => [
                'seller_store/home' => 1,
                'seller_store/introduction' => 1,
                'seller_store/products' => 1,
                'account/marketing_time_limit/sellerOnSale' => 1,  //店铺限时限量活动-进行中
                'account/marketing_time_limit/sellerWillSale' => 1,//店铺限时限量活动-即将开始
                'customerpartner/profile' => 1,
            ],
            // 以下为菜单栏为白色的路由
            'notBuyerMenu' => [
                'common/home' => 1,
                'product/category' => 1,
                'product/product' => 1,
                'product/search' => 1,
                'customerpartner/profile' => 1,
                'marketing_campaign/activity/index' => 1,
                'product/column' => 1,
                'checkout/cart' => 1,
                'checkout/pre_order' => 1,
                'checkout/confirm/toPay' => 1,
                'checkout/success' => 1,
                'information/information' => 1,
                'customerpartner/contacted_seller' => 1,
                'account/coupon/index' => 1,
                'account/wishlist' => 1,
                'checkout/cwf_info' => 1,
                'account/logout' => 1,
                'information/contact' => 1,
                'product/channel/getChannelData' => 1,
                'account/marketing_time_limit' => 1,      //首页限时限量活动
                'account/marketing_time_limit/index' => 1,//首页限时限量活动
            ],
        ];

        if (!isset($config[$group])) {
            throw new InvalidConfigException($group . '未配置');
        }

        $is = array_key_exists(request('route', 'common/home'), $config[$group]);
        return $isIn ? $is : !$is;
    }

    /**
     * 获取网站的 url 地址
     * @param array $paths
     * @param array $queries
     * @return string
     */
    public static function getSiteAbsoluteUrl($paths = [], $queries = []): string
    {
        $http = get_env('HTTPS_ENABLE', false) ? 'https' : 'http';
        $url = $http . '://' . get_env('HOST_NAME', 'localhost');
        if ($paths) {
            $paths = (array)$paths;
            $url .= implode('/', $paths);
        }
        if ($queries) {
            $url .= '?' . http_build_query($queries);
        }
        return $url;
    }
}
