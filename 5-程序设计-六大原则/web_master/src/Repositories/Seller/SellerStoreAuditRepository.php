<?php

namespace App\Repositories\Seller;

use App\Enums\Seller\SellerStoreAuditStatus;
use App\Enums\Seller\SellerStoreAuditType;
use App\Exception\UserSeeException;
use App\Models\Seller\SellerStoreAudit;

class SellerStoreAuditRepository
{
    /**
     * seller 是否有正在审核中的信息
     * @param int $sellerId
     * @param int $type
     * @return bool
     */
    public function isSellerHasAudit(int $sellerId, int $type): bool
    {
        return SellerStoreAudit::query()->where([
            'seller_id' => $sellerId,
            'type' => $type,
            'status' => SellerStoreAuditStatus::AUDIT_WAIT,
            'is_deleted' => 0,
        ])->exists();
    }

    /**
     * seller 最新的预览数据是否有变化
     * @param int $sellerId
     * @param int $type
     * @param string $previewKey
     * @return bool
     */
    public function isPreviewDataChanged(int $sellerId, int $type, string $previewKey): bool
    {
        $audit = SellerStoreAudit::query()->where([
            'seller_id' => $sellerId,
            'type' => $type,
            'status' => SellerStoreAuditStatus::PREVIEW,
            'is_deleted' => 0,
        ])->orderByDesc('updated_at')->first();
        if (!$audit) {
            // 最新的不存在也表示变化了
            return true;
        }
        if ($audit->preview_key !== $previewKey) {
            return true;
        }
        return false;
    }

    /**
     * 获取预览数据
     * @param int $sellerId
     * @param int $type
     * @param string $previewKey
     * @return SellerStoreAudit|string|null 为 string 时表示最新的预览值发生变化，为 null 时表示无预览数据，为 SellerStoreAudit 表示当前预览数据
     */
    public function getPreviewData(int $sellerId, int $type, string $previewKey)
    {
        $audit = SellerStoreAudit::query()
            ->where([
                'seller_id' => $sellerId,
                'type' => $type,
                'preview_key' => $previewKey,
            ])
            ->first();
        if ($audit && $audit->status === SellerStoreAuditStatus::AUDIT_WAIT) {
            // 待审核的预览，此处不判断是否已经删除
            return $audit;
        }
        if (!$audit || $audit->status === SellerStoreAuditStatus::PREVIEW) {
            // 未查到或者是预览
            if (!$audit || $audit->is_deleted) {
                // 未查到或者已删除时，取最新未删除的预览数据
                $audit = SellerStoreAudit::query()->where([
                    'seller_id' => $sellerId,
                    'type' => $type,
                    'status' => SellerStoreAuditStatus::PREVIEW,
                    'is_deleted' => 0,
                ])->orderByDesc('updated_at')->first();
                if (!$audit) {
                    // 无预览数据
                    return null;
                }
                // 预览数据发生变化
                return $audit->preview_key;
            }
            // 预览数据未删除
        }

        return $audit;
    }

    /**
     * 获取用于视图的预览数据
     * @param int $sellerId
     * @param int $type
     * @param string $previewKey
     * @return array
     * @throws UserSeeException
     */
    public function getPreviewInfoForView(int $sellerId, int $type, string $previewKey): array
    {
        $previewInfo = [
            'key' => customer()->getId() === $sellerId ? $previewKey : null, // 存在该值时可以操作提交审核
            'db_json' => null, // 提交审核时的数据，key 存在时才存在
            'view_data' => [], // 预览时的视图展示数据
            'redirect' => false, // 重定向，当该值为 string 时，表示为重定向的 preview_key
        ];

        $previewData = $this->getPreviewData($sellerId, $type, $previewKey);
        if (!$previewData) {
            throw new UserSeeException('No preview content.');
        }
        if (is_string($previewData)) {
            // 预览信息发生变化，则跳转到最新的预览页面
            $previewInfo['redirect'] = $previewData;
            return $previewInfo;
        }
        if ($previewData->status === SellerStoreAuditStatus::AUDIT_WAIT) {
            if ($previewData->is_deleted) {
                // 审核信息被删除
                throw new UserSeeException('Modifications have been done to the current content under review.');
            }
            // 审核时不出现顶部提交审核的操作栏
            $previewInfo['key'] = null;
            $previewInfo['db_json'] = null;
        } elseif ($previewData->status === SellerStoreAuditStatus::PREVIEW && $previewInfo['key']) {
            // 预览时可以提交审核
            $previewInfo['db_json'] = $previewData->audit_data;
        }
        if ($type === SellerStoreAuditType::HOME) {
            $previewInfo['view_data'] = app(SellerStoreRepository::class)->coverModulesDBJsonToViewData($previewData->audit_data, $sellerId);
        } elseif ($type === SellerStoreAuditType::INTRODUCTION) {
            $previewInfo['view_data'] = app(SellerStoreRepository::class)->coverStoreIntroductionJsonToViewData($previewData->audit_data);
        }

        return $previewInfo;
    }
}
