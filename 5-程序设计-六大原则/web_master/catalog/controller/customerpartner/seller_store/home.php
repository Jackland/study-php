<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Catalog\Forms\CustomerPartner\SellerStore\Home\AllModuleSaveForm;
use App\Catalog\Forms\CustomerPartner\SellerStore\Home\ModuleSaveForm;
use App\Catalog\Forms\CustomerPartner\SellerStore\StoreAuditCancelForm;
use App\Catalog\Search\CustomerPartner\SellerStoreHome\SellerProductsSearch;
use App\Enums\Seller\SellerStoreAuditStatus;
use App\Enums\Seller\SellerStoreAuditType;
use App\Enums\Seller\SellerStoreHome\ModuleProductRecommendAngleTipKey;
use App\Enums\Seller\SellerStoreHome\ModuleProductRecommendTitleKey;
use App\Enums\Seller\SellerStoreHome\ModuleProductTypeAutoSortType;
use App\Enums\Seller\SellerStoreHome\ModuleProductTypeMode;
use App\Enums\Seller\SellerStoreHome\ModuleStoreIntroductionIcon;
use App\Helper\RouteHelper;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Seller\SellerStore;
use App\Models\Seller\SellerStoreAudit;
use App\Repositories\Seller\SellerRepository;
use App\Repositories\Seller\SellerStoreRepository;

class ControllerCustomerpartnerSellerStoreHome extends AuthSellerController
{
    // 编辑页
    public function index()
    {
        $sellerId = $this->customer->getId();
        $seller = CustomerPartnerToCustomer::find($sellerId);

        $data = [
            'seller' => $seller,
            'score' => 0,
            'site_url' => RouteHelper::getSiteAbsoluteUrl(),
        ];

        // 评分数据
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
        }

        return $this->render('customerpartner/seller_store/home/index', $data, 'seller_no_left');
    }

    // 初始化信息
    public function initData()
    {
        $sellerId = $this->customer->getId();

        $data = [
            'published' => [
                'modules' => [],
                'updated_time' => null,
                'audit_info' => [
                    'id' => 0,
                    'status' => null,
                    'refuse_reason' => '',
                ],
            ],
            'unpublished' => [
                'modules' => [],
                'updated_time' => null,
            ],
            'options' => [
                'module_product_recommend_angle_tip' => ModuleProductRecommendAngleTipKey::getViewItems(),
                'module_product_recommend_title' => ModuleProductRecommendTitleKey::getViewItems(),
                'module_product_type_auto_sort' => ModuleProductTypeAutoSortType::getViewItems(),
                'module_product_type_mode' => ModuleProductTypeMode::getViewItems(),
                'module_introduction_icon' => ModuleStoreIntroductionIcon::getViewItems(),
            ],
        ];
        $sellerStoreRepo = app(SellerStoreRepository::class);
        // 已发布信息
        $sellerStore = SellerStore::query()->where('seller_id', $sellerId)->first();
        if ($sellerStore && $sellerStore->store_home_json) {
            $data['published']['modules'] = $sellerStoreRepo->coverModulesDBJsonToViewData($sellerStore->store_home_json, $sellerStore->seller_id, true);
            $data['published']['updated_time'] = $sellerStore->store_home_json_updated_at->format('Y-m-d H:i:s');
            $data['published']['audit_info']['status'] = SellerStoreAuditStatus::AUDIT_PASS;
        }
        // 审核中或审核驳回的信息
        $sellerStoreAudit = SellerStoreAudit::query()
            ->where('seller_id', $sellerId)
            ->where('type', SellerStoreAuditType::HOME)
            ->where('is_deleted', 0)
            ->whereIn('status', [SellerStoreAuditStatus::AUDIT_WAIT, SellerStoreAuditStatus::AUDIT_REFUSE]) // 待审核和驳回的可见
            ->when($sellerStore && $sellerStore->store_home_json_updated_at, function ($q) use ($sellerStore) {
                $q->where('created_at', '>', $sellerStore->store_home_json_updated_at); // 提交审核时间晚于当前在用的
            })
            ->orderByDesc('created_at')
            ->first();
        if ($sellerStoreAudit) {
            $data['published']['modules'] = $sellerStoreRepo->coverModulesDBJsonToViewData($sellerStoreAudit->audit_data, $sellerStoreAudit->seller_id, true);
            $data['published']['updated_time'] = $sellerStoreAudit->audit_at ? $sellerStoreAudit->audit_at->format('Y-m-d H:i:s') : $sellerStoreAudit->updated_at->format('Y-m-d H:i:s');
            $data['published']['audit_info']['id'] = $sellerStoreAudit->id;
            $data['published']['audit_info']['status'] = $sellerStoreAudit->status;
            if ($sellerStoreAudit->status === SellerStoreAuditStatus::AUDIT_REFUSE) {
                $data['published']['audit_info']['refuse_reason'] = $sellerStoreAudit->refuse_reason;
            }
        }

        // 草稿信息
        $sellerStoreDraft = SellerStoreAudit::query()
            ->where('seller_id', $sellerId)
            ->where('type', SellerStoreAuditType::HOME)
            ->where('is_deleted', 0)
            ->where('status', SellerStoreAuditStatus::DRAFT) // 草稿
            ->orderByDesc('updated_at')
            ->first();
        if ($sellerStoreDraft) {
            $data['unpublished']['modules'] = $sellerStoreRepo->coverModulesDBJsonToViewData($sellerStoreDraft->audit_data, $sellerStoreDraft->seller_id, true);
            $data['unpublished']['updated_time'] = $sellerStoreDraft->updated_at->format('Y-m-d H:i:s');
        }

        return $this->jsonSuccess($data);
    }

    // 模块保存,不存储到库，只做数据交互
    public function moduleSave(ModuleSaveForm $form)
    {
        if ($error = $form->getFirstError()) {
            return $this->jsonFailed($error);
        }
        try {
            $data = $form->getViewData();
        } catch (Throwable $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess($data);
    }

    // 全部保存，保存到库
    public function allSave(AllModuleSaveForm $form)
    {
        if ($error = $form->getFirstError()) {
            return $this->jsonFailed($error);
        }
        try {
            $data = $form->save();
        } catch (Throwable $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess($data);
    }

    // 审核取消
    public function auditCancel(StoreAuditCancelForm $form)
    {
        if ($error = $form->getFirstError()) {
            return $this->jsonFailed($error);
        }
        try {
            $data = $form->cancel();
        } catch (Throwable $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess($data);
    }

    // seller sku&mpn查询
    public function search(SellerProductsSearch $searchForm)
    {
        $data = $searchForm->search();
        if (empty($data['error'])) {
            return $this->jsonSuccess($data);
        }

        return $this->jsonFailed($data['error']);
    }
}
