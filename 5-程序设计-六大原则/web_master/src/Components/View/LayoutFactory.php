<?php

namespace App\Components\View;

use App\Components\View\Layouts\BuyerSellerStoreLayout;
use App\Components\View\Layouts\LayoutInterface;
use Framework\Exception\InvalidConfigException;

class LayoutFactory
{
    const LAYOUT_LOGIN_SHOW_LOGIN = 'layout_login_show_login'; // login layout header 右上角的 register/login 是否显示

    public static function register(): array
    {
        $layouts = [
            // layouts 下的 view 名称 => view 的参数

            // 登录页面的布局
            'login' => function () {
                return [
                    'header' => load()->controller('common/header', [
                        'display_top' => false,
                        'display_search' => false,
                        'display_account_info' => false,
                        'display_menu' => false,
                        'display_common_ticket' => false,
                        'display_shipment_time' => false,
                        'display_forgot_password_header' => view()->params(self::LAYOUT_LOGIN_SHOW_LOGIN) ?: false,
                    ]),
                    'footer' => load()->controller('common/footer', [
                        'is_show_notice' => false,
                        'is_show_message' => false,
                    ]),
                ];
            },

            // buyer 端的产品展示等的布局
            'home' => function () {
                return [
                    'header' => load()->controller('common/header'),
                    'footer' => load()->controller('common/footer'),
                ];
            },
            // buyer 端个人数据管理的布局
            'buyer' => function () {
                return [
                    'header' => load()->controller('common/header'),
                    'footer' => load()->controller('common/footer'),
                ];
            },
            // buyer 端的 seller 页布局
            'buyer_seller_store' => BuyerSellerStoreLayout::class,

            // seller 端的主布局
            'seller' => function () {
                return [
                    'header' => load()->controller('account/customerpartner/header'),
                    'separate_column_left' => load()->controller('account/customerpartner/column_left'),
                    'footer' => load()->controller('account/customerpartner/footer'),
                ];
            },
            // seller 端无左侧栏的布局
            'seller_no_left' => function () {
                return [
                    'header' => load()->controller('account/customerpartner/header'),
                    'footer' => load()->controller('account/customerpartner/footer'),
                ];
            }
        ];

        $data = [];
        foreach ($layouts as $name => $layout) {
            if (is_callable($layout)) {
                $data[$name] = $layout;
                continue;
            }
            if (is_string($layout) && is_a($layout, LayoutInterface::class, true)) {
                $data[$name] = function () use ($layout) {
                    /** @var LayoutInterface|string $layout */
                    $layout = app()->make($layout);
                    return $layout->getParams();
                };
                continue;
            }
            throw new InvalidConfigException("不支持的配置: {$name}");
        }

        return $data;
    }
}
