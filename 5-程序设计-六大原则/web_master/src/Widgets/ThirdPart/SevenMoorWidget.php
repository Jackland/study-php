<?php

namespace App\Widgets\ThirdPart;

use App\Assets\Common\SevenMoorAsset;
use App\Components\Storage\StorageCloud;
use Framework\Widget\Widget;
use League\Flysystem\FilesystemException;

class SevenMoorWidget extends Widget
{
    /**
     * 是否处于维护状态
     * @var int
     */
    public $isMaintain = 0;
    /**
     * seller 店铺主页
     * @var string[]
     */
    protected $sellerStoreRoutes = [
        'seller_store/home',
        'seller_store/introduction',
        'seller_store/products',
        'customerpartner/profile',
    ];
    /**
     * seller 产品详情
     * @var string[]
     */
    protected $sellerProductDetailRoutes = [
        'product/product',
    ];

    public function run()
    {
        if ($this->isMaintain) {
            return $this->runMaintain();
        }
        return $this->runNormal();
    }

    private function runMaintain()
    {
        $config = configDB();
        $customer = customer();
        $accessId = $config->get('config_customer_service_access_id') ?: '402613e0-f191-11e9-9a99-ab9f77aaeb99';
        if ($customer && $customer->isLogged()) {
            $userId = md5($customer->getId() . '$oristand@com*&');
            $nickname = $customer->getNickName();
        } else {
            if (isset($_COOKIE['_ayo']) && !empty($_COOKIE['_ayo'])) {
                $userId = $_COOKIE['_ayo'];
            } else {
                $userId = md5(token(15) . '$oristand@com*&');
                setcookie('_ayo', $userId);
            }
            $nickname = 'VISITOR_' . strtoupper(substr($userId, 0, 5));
        }
        $this->getView()->js("https://ykf-webchat.yuntongxun.com/javascripts/7moorInit.js?accessId={$accessId}&autoShow=true&language=EN", [
            'async' => 'async',
        ]);
        $this->getView()->registerAssets(SevenMoorAsset::class);
        $nickname = addslashes($nickname);
        $this->getView()->script("sevenMoor.init('{$userId}', '{$nickname}', 1);");

        return '';
    }

    private function runNormal()
    {
        $config = configDB();
        $globalEnable = $config->get('config_customer_service') ?: 0;
        if (!$globalEnable) {
            // 全局关闭
            return '';
        }

        $chatType = 0;
        $accessId = '';
        $avatar = '';
        $customer = customer();
        $route = request('route', 'common/home');
        // 全局开启，并且在需要开启的页面
        if (in_array($route, array_merge($this->sellerStoreRoutes, $this->sellerProductDetailRoutes)) && !$customer->isPartner()) {
            // seller专属
            [$accessId, $avatar] = $this->getSpecialAccessIdAndAvatarByRoute($route);
            if ($accessId) {
                // seller 客服
                $chatType = 2;
            }
            try {
                if (StorageCloud::image()->fileExists($avatar)) {
                    $avatar = StorageCloud::image()->getUrl($avatar, ['w' => 100, 'h' => 100, 'check-exist' => false]);
                } else {
                    $avatar = '';
                }
            } catch (FilesystemException $e) {
                $avatar = '';
            }
        } else {
            // 平台客服
            if (($customer->isLogged() && !$customer->isPartner()) || ($customer->isPartner() && $route == 'account/ticket/lists')) {
                $chatType = 1;
            }
        }

        if (!$accessId) {
            $accessId = $config->get('config_customer_service_access_id') ?: '402613e0-f191-11e9-9a99-ab9f77aaeb99';
        }
        if ($customer && $customer->isLogged()) {
            $userId = md5($customer->getId() . '$oristand@com*&');
            $nickname = $customer->getNickName();
        } else {
            if (isset($_COOKIE['_ayo']) && !empty($_COOKIE['_ayo'])) {
                $userId = $_COOKIE['_ayo'];
            } else {
                $userId = md5(token(15) . '$oristand@com*&');
                setcookie('_ayo', $userId);
            }
            $nickname = 'visitor';
        }

        $this->getView()->js("https://ykf-webchat.yuntongxun.com/javascripts/7moorInit.js?accessId={$accessId}&autoShow=true&language=EN", [
            'async' => 'async',
        ]);
        $this->getView()->registerAssets(SevenMoorAsset::class);
        $nickname = addslashes($nickname);
        $this->getView()->script("sevenMoor.init('{$userId}', '{$nickname}', {$chatType}, '{$avatar}');");

        return '';
    }

    /**
     * @param $route
     * @return array
     */
    protected function getSpecialAccessIdAndAvatarByRoute($route): array
    {
        $orm = db();
        $request = request();
        $data = [];
        if (in_array($route, $this->sellerStoreRoutes)) {
            $id = $request->get('id');
            if ($id) {
                $data = $orm->table('oc_customerpartner_to_customer')
                    ->select(['customer_service_access_id', 'avatar'])
                    ->where([
                        ['customer_id', '=', $request->get('id')]
                    ])
                    ->first();
                $data = obj2array($data);
            }
        } elseif (in_array($route, $this->sellerProductDetailRoutes)) {
            $productId = $request->get('product_id');
            if ($productId) {
                $data = $orm->table('oc_customerpartner_to_customer as ctc')
                    ->join('oc_customerpartner_to_product as ctp', 'ctp.customer_id', '=', 'ctc.customer_id')
                    ->select(['customer_service_access_id', 'avatar'])
                    ->where([
                        ['ctp.product_id', '=', $productId]
                    ])
                    ->first();
                $data = obj2array($data);
            }
        }
        return [$data['customer_service_access_id'] ?? '', $data['avatar'] ?? ''];
    }
}
