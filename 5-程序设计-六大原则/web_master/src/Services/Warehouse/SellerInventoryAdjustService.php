<?php

namespace App\Services\Warehouse;

use App\Components\RemoteApi;
use App\Components\RemoteApi\B2BManager\Enums\FileResourceTypeEnum;
use App\Enums\Warehouse\SellerInventoryAdjustStatus;
use App\Models\Warehouse\SellerInventoryAdjust;
use App\Models\Warehouse\SellerInventoryAdjustLine;
use Throwable;

class SellerInventoryAdjustService
{
    /**
     * 更新seller盘亏
     * @param int $customerId
     * @param $data
     * @param $files
     * @return bool
     */
    public function updateInventoryAdjust($customerId, $data, $files)
    {
        return true; // 33395 - Seller盘亏规则修改

        $adjust = SellerInventoryAdjust::query()
            ->where('customer_id', $customerId)
            ->find($data['inventory_id']);
        if (empty($adjust)) {
            return false;
        }
        if ($adjust->status != SellerInventoryAdjustStatus::TO_CONFIRM) {
            return false;
        }
        $detailsCount = SellerInventoryAdjustLine::query()
            ->where('inventory_id', $data['inventory_id'])
            ->count();
        if ($detailsCount != count($data['details'])) {
            return false;
        }
        $fileList = RemoteApi::file()->upload(FileResourceTypeEnum::SELLER_INVENTORY_ADJUSTMENT_FILE_CONFIRM, $files);
        try {
            dbTransaction(function () use ($data, $fileList) {
                RemoteApi::file()->confirmUpload($fileList->menuId, $fileList->list->pluck('subId')->toArray());

                SellerInventoryAdjust::query()
                    ->where('inventory_id', $data['inventory_id'])
                    ->update([
                        'status' => SellerInventoryAdjustStatus::TO_AUDIT,
                        'confirm_file_menu_id' => $fileList->menuId,
                    ]);

                foreach ($data['details'] as $item) {
                    SellerInventoryAdjustLine::query()
                        ->where('inventory_line_id', $item['inventory_line_id'])
                        ->where('inventory_id', $data['inventory_id'])
                        ->update($item);
                }
            });
        } catch (Throwable $e) {
            return false;
        }

        return true;
    }
}
