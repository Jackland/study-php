<?php

use App\Catalog\Controllers\BaseController;
use App\Enums\Seller\SellerStoreAuditType;
use App\Enums\Seller\SellerStoreHome\ModuleType;
use App\Exception\UserSeeException;
use App\Helper\CountryHelper;
use App\Models\Customer\Customer;
use App\Models\Seller\SellerStore;
use App\Repositories\Seller\SellerRepository;
use App\Repositories\Seller\SellerStoreAuditRepository;
use App\Repositories\Seller\SellerStoreRepository;
use Carbon\Carbon;
use Framework\Exception\Http\NotFoundException;

class ControllerSellerStoreIntroduction extends BaseController
{
    // 店铺介绍页
    public function index()
    {
        $sellerId = (int)request('id');
        if (!$sellerId) {
            return $this->redirect(['common/home']);
        }
        $seller = Customer::query()->find($sellerId);
        if (!$seller) {
            throw new NotFoundException('未知的 sellerID: ' . $sellerId);
        }

        $previewKey = request('preview_key');
        $previewInfo = [];
        if ($previewKey) {
            // 预览
            try {
                $previewInfo = app(SellerStoreAuditRepository::class)->getPreviewInfoForView($sellerId, SellerStoreAuditType::INTRODUCTION, $previewKey);
            } catch (UserSeeException $e) {
                return $e->getMessage();
            }
            if ($previewInfo['redirect']) {
                return $this->redirect(['seller_store/introduction', 'id' => $sellerId, 'preview_key' => $previewInfo['redirect']]);
            }
            view()->share('must_show_store_introduction_menu', true); // 预览时需要有 store introduction 的菜单
        } else {
            // 非预览，当无数据时跳转到产品页
            $sellerStore = SellerStore::query()->where('seller_id', $sellerId)->first();
            if (!$sellerStore || !$sellerStore->store_introduction_json) {
                return $this->redirect(['seller_store/products', 'id' => $sellerId]);
            }
        }

        $sellerStoreRepo = app(SellerStoreRepository::class);
        $data = [
            'is_login' => customer()->isLogged(),
            'seller_id' => $sellerId,
            'preview_key' => $previewInfo['key'] ?? null,
            'intro' => $previewInfo['view_data'] ?? $sellerStoreRepo->getStoreIntroductionViewData($sellerId),
            'preview_data_json' => $previewInfo['db_json'] ?? '',
            'score' => '--',
            'score_date' => '',
            'comprehensive' => [],
            'return_info' => [],
            'module_introduction' => $sellerStoreRepo->getStoreHomeModuleViewData($sellerId, ModuleType::STORE_INTRODUCTION),
        ];

        // 退换货等数据
        /** @var ModelCatalogSearch $modelCatalogSearch */
        $modelCatalogSearch = load()->model('catalog/search');
        $data['return_info'] = $modelCatalogSearch->getSellerRateInfo($sellerId);

        // 评分数据
        if (customer()->isLogged()) {
            /** @var ModelCustomerpartnerSellerCenterIndex $modelCustomerPartnerSellerCenterIndex */
            $modelCustomerPartnerSellerCenterIndex = load()->model('customerpartner/seller_center/index');
            $taskInfo = $modelCustomerPartnerSellerCenterIndex->getSellerNowScoreTaskNumberEffective($sellerId);
            if (!isset($taskInfo['performance_score'])) {
                // 无评分 且 在3个月内是外部新seller
                if (app(SellerRepository::class)->isOutNewSeller($sellerId, 3)) {
                    $data['score'] = 'New Seller';
                }
            } else {
                $score = number_format(round($taskInfo['performance_score'], 2), 2);
                $data['score'] = $score;
                $data['score_date'] = Carbon::createFromFormat('Ymd', $taskInfo['score_task_number'])->format('Y-m-d');
                $data['comprehensive'] = $modelCustomerPartnerSellerCenterIndex->comprehensiveSellerData($sellerId, CountryHelper::getCurrentId(), 1);
            }
        }

        return $this->render('seller_store/introduction/index', $data, 'buyer_seller_store');
    }
}
