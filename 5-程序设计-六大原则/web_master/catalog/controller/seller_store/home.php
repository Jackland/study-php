<?php

use App\Catalog\Controllers\BaseController;
use App\Enums\Seller\SellerStoreAuditType;
use App\Exception\UserSeeException;
use App\Models\Customer\Customer;
use App\Models\Seller\SellerStore;
use App\Repositories\Seller\SellerStoreAuditRepository;
use App\Repositories\Seller\SellerStoreRepository;
use Framework\Exception\Http\NotFoundException;

class ControllerSellerStoreHome extends BaseController
{
    // 店铺首页
    public function index()
    {
        $sellerId = request('id');
        if (!$sellerId) {
            return $this->redirect(['common/home']);
        }
        $seller = Customer::query()->find($sellerId);
        if (!$seller) {
            throw new NotFoundException('未知的 sellerID: ' . $sellerId);
        }

        $data = [
            'modules' => [],
            'preview_key' => null,
            'preview_data_json' => '',
            'seller_id' => $sellerId,
        ];

        $previewKey = request('preview_key');
        if ($previewKey) {
            // 预览
            try {
                $previewInfo = app(SellerStoreAuditRepository::class)->getPreviewInfoForView($sellerId, SellerStoreAuditType::HOME, $previewKey);
            } catch (UserSeeException $e) {
                return $e->getMessage();
            }
            if ($previewInfo['redirect']) {
                return $this->redirect(['seller_store/home', 'id' => $sellerId, 'preview_key' => $previewInfo['redirect']]);
            }
            view()->share('must_show_store_home_menu', true); // 预览时需要有 store home 的菜单
            $data['preview_key'] = $previewInfo['key'];
            $data['modules'] = $previewInfo['view_data'];
            $data['preview_data_json'] = $previewInfo['db_json'];
        } else {
            // 非预览，当无数据时跳转到产品页
            $sellerStore = SellerStore::query()->where('seller_id', $sellerId)->first();
            if (!$sellerStore || !$sellerStore->store_home_json) {
                return $this->redirect(['seller_store/products', 'id' => $sellerId]);
            }
            $data['modules'] = app(SellerStoreRepository::class)->coverModulesDBJsonToViewData($sellerStore->store_home_json, $sellerId);
        }

        return $this->render('seller_store/home/index', $data, 'buyer_seller_store');
    }
}
