<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Catalog\Forms\CustomerPartner\SellerStore\StoreAuditCancelForm;
use App\Catalog\Forms\CustomerPartner\SellerStore\StoreIntroductionSaveForm;
use App\Enums\Seller\SellerStoreAuditStatus;
use App\Enums\Seller\SellerStoreAuditType;
use App\Models\Seller\SellerStore;
use App\Models\Seller\SellerStoreAudit;
use App\Repositories\Seller\SellerStoreRepository;

class ControllerCustomerpartnerSellerStoreIntroduction extends AuthSellerController
{
    // 店铺介绍页
    public function index()
    {
        $data = [];
        return $this->render('customerpartner/seller_store/introduction/index', $data, 'seller');
    }

    // save
    public function store(StoreIntroductionSaveForm $form)
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

    // 获取店铺介绍信息
    public function getStoreInfo()
    {
        $sellerId = (int)customer()->getId();
        $data = [
            'updated_time' => null,
            'audit_info' => [
                'id' => 0,
                'status' => null,
                'refuse_reason' => '',
            ],
            'saved_info' => null,
            'seller_id' => $sellerId,
        ];

        $sellerStoreRepo = app(SellerStoreRepository::class);
        // 已发布信息
        $sellerStore = SellerStore::query()->where('seller_id', $sellerId)->first();
        if ($sellerStore && $sellerStore->store_introduction_json) {
            $data['saved_info'] = $sellerStoreRepo->coverStoreIntroductionJsonToViewData($sellerStore->store_introduction_json);
            $data['updated_time'] = $sellerStore->store_introduction_json_updated_at->format('Y-m-d H:i:s');
            $data['audit_info']['status'] = SellerStoreAuditStatus::AUDIT_PASS;
        }
        // 审核中或审核驳回的信息
        $sellerStoreAudit = SellerStoreAudit::query()
            ->where('seller_id', $sellerId)
            ->where('type', SellerStoreAuditType::INTRODUCTION)
            ->where('is_deleted', 0)
            ->whereIn('status', [SellerStoreAuditStatus::AUDIT_WAIT, SellerStoreAuditStatus::AUDIT_REFUSE]) // 待审核和驳回的可见
            ->when($sellerStore && $sellerStore->store_introduction_json_updated_at, function ($q) use ($sellerStore) {
                $q->where('created_at', '>', $sellerStore->store_introduction_json_updated_at); // 提交审核时间晚于当前在用的
            })
            ->orderByDesc('created_at')
            ->first();
        if ($sellerStoreAudit) {
            $data['saved_info'] = $sellerStoreRepo->coverStoreIntroductionJsonToViewData($sellerStoreAudit->audit_data);
            $data['updated_time'] = $sellerStoreAudit->audit_at ? $sellerStoreAudit->audit_at->format('Y-m-d H:i:s') : $sellerStoreAudit->updated_at->format('Y-m-d H:i:s');
            $data['audit_info']['id'] = $sellerStoreAudit->id;
            $data['audit_info']['status'] = $sellerStoreAudit->status;
            if ($sellerStoreAudit->status === SellerStoreAuditStatus::AUDIT_REFUSE) {
                $data['audit_info']['refuse_reason'] = $sellerStoreAudit->refuse_reason;
            }
        }

        return $this->jsonSuccess($data);
    }
}
