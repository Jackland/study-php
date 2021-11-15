<?php

namespace App\Services\Product;

use App\Components\BatchInsert;
use App\Models\Product\Product;
use App\Models\Product\ProductLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProductLogService
{
    /**
     * 添加产品变动日志
     *
     * @param int|array|Product[]|Product $product 需要添加的产品int：产品ID，array：产品ID数组，产品对象，产品对象集合
     * @param int $type 类型参考 src/Enums/Product/ProductLogType.php
     * @param string $reason
     * @param array|string $beforeData 修改前数据，如果传数组，会被转换成json
     * @param array|string $modifiedData 修改后数据，如果传数组，会被转换成json
     * @param string $createUser
     *
     * @return bool
     */
    public function addLog($product, int $type, $reason = '', $beforeData = '', $modifiedData = '', $createUser = '')
    {
        if (empty($product)) {
            return false;
        }
        $batchInsert = new BatchInsert();
        $batchInsert->begin(ProductLog::class, 100);
        if ($product instanceof Collection || is_array($product)) {
            if (is_array($product) && is_integer($product[0])) {
                // 如果是id数组
                $product = Product::query()->whereIn('product_id', $product)->get();
            }
            $product->load('customerPartnerToProduct');
            foreach ($product as $pItem) {
                $batchInsert->addRow(
                    $this->initLogData($pItem, $type, $reason, $beforeData, $modifiedData, $createUser)
                );
            }
        } elseif ($product instanceof Product) {
            $batchInsert->addRow(
                $this->initLogData($product, $type, $reason, $beforeData, $modifiedData, $createUser)
            );
        } elseif (is_integer($product)) {
            $product = Product::query()->where('product_id', $product)->first();
            $batchInsert->addRow(
                $this->initLogData($product, $type, $reason, $beforeData, $modifiedData, $createUser)
            );
        }
        $batchInsert->end();
        return true;
    }

    private function initLogData(Product $product, $type, $reason = '', $beforeData = '', $modifiedData = '', $createUser = '')
    {
        return [
            'product_id' => $product->product_id,
            'seller_id' => $product->customerPartnerToProduct ? $product->customerPartnerToProduct->customer_id : 0,
            'item_code' => $product->sku,
            'mpn' => $product->mpn,
            'type' => $type,
            'before_data' => is_array($beforeData) ? json_encode($beforeData) : $beforeData,
            'modified_data' => is_array($modifiedData) ? json_encode($modifiedData) : $modifiedData,
            'reason' => $reason,
            'create_user' => $createUser,
            'created_at' => Carbon::now()->toDateTimeString()
        ];
    }
}
