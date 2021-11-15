<?php

namespace App\Services\Seller;

use App\Enums\Seller\SellerStoreAuditStatus;
use App\Models\Seller\SellerStoreAudit;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SellerStoreAuditService
{
    /**
     * 新建待审核
     * @param int $sellerId
     * @param int $type
     * @param string $json
     * @return SellerStoreAudit
     */
    public function createNewAudit(int $sellerId, int $type, string $json): SellerStoreAudit
    {
        $audit = new SellerStoreAudit([
            'seller_id' => $sellerId,
            'type' => $type,
            'status' => SellerStoreAuditStatus::AUDIT_WAIT,
            'audit_data' => $json,
        ]);
        $audit->preview_key = Str::random();
        $audit->save();
        return $audit;
    }

    /**
     * 更新或新建草稿
     * @param int $sellerId
     * @param int $type
     * @param string $json
     * @return SellerStoreAudit
     */
    public function updateOrCreateDraft(int $sellerId, int $type, string $json): SellerStoreAudit
    {
        $audit = SellerStoreAudit::firstOrCreate([
            'seller_id' => $sellerId,
            'type' => $type,
            'status' => SellerStoreAuditStatus::DRAFT,
        ]);
        $audit->audit_data = $json;
        $audit->preview_key = Str::random();
        $audit->created_at = Carbon::now(); // 修改新的新建时间
        $audit->is_deleted = 0; // 删除的改回未删除，复用
        $audit->save();
        return $audit;
    }

    /**
     * 更新或新建预览
     * @param int $sellerId
     * @param int $type
     * @param string $json
     * @return SellerStoreAudit
     */
    public function updateOrCreatePreview(int $sellerId, int $type, string $json): SellerStoreAudit
    {
        $audit = SellerStoreAudit::firstOrCreate([
            'seller_id' => $sellerId,
            'type' => $type,
            'status' => SellerStoreAuditStatus::PREVIEW,
        ]);
        $audit->audit_data = $json;
        $audit->preview_key = Str::random();
        $audit->created_at = Carbon::now(); // 修改新的新建时间
        $audit->is_deleted = 0; // 删除的改回未删除，复用
        $audit->save();
        return $audit;
    }

    /**
     * 删除草稿
     * @param int $sellerId
     * @param int $type
     * @return bool
     */
    public function deleteDraft(int $sellerId, int $type): bool
    {
        SellerStoreAudit::query()->where([
            'seller_id' => $sellerId,
            'type' => $type,
            'status' => SellerStoreAuditStatus::DRAFT,
        ])->update(['is_deleted' => 1, 'updated_at' => Carbon::now()]);
        return true;
    }

    /**
     * 删除待审核
     * @param int $sellerId
     * @param int $type
     * @return bool
     */
    public function deleteWaitAudit(int $sellerId, int $type): bool
    {
        SellerStoreAudit::query()->where([
            'seller_id' => $sellerId,
            'type' => $type,
            'status' => SellerStoreAuditStatus::AUDIT_WAIT,
        ])->update(['is_deleted' => 1, 'updated_at' => Carbon::now()]);
        return true;
    }
}
