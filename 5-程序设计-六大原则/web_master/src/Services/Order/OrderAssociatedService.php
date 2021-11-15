<?php

namespace App\Services\Order;

use App\Enums\SalesOrder\CustomerSalesOrderLineItemStatus;
use App\Logging\Logger;
use App\Models\Link\OrderAssociated;
use App\Models\Order\OrderCombo;
use App\Models\SalesOrder\CustomerSalesOrderLine;

class OrderAssociatedService
{
    /**
     * 根据最新的销售订单明细信息重新处理绑定关系
     * 减少或删除绑定关系
     * 新的销售订单明细中的 qty 减少时，如果是 combo，需要重新修改 combo_info 信息才能解绑
     * @param int $salesOrderId
     * @return bool
     */
    public function unbindBySalesOrderNewLines(int $salesOrderId)
    {
        $associatedModels = OrderAssociated::query()
            ->with(['orderCombos'])
            ->where('sales_order_id', $salesOrderId)
            ->get();
        if ($associatedModels->isEmpty()) {
            return true;
        }
        $logInfo = [
            '按新的销售单明细解除绑定关系' => func_get_args(),
            '删除' => [],
            '修改' => [],
            '修改前' => [],
        ];
        $lines = CustomerSalesOrderLine::query()
            ->where('header_id', $salesOrderId)
            ->where('item_status', '!=', CustomerSalesOrderLineItemStatus::DELETED) // 非已删除的
            ->get();
        $linesKeyedQty = collect();
        foreach ($lines as $line) {
            if (!$line->combo_info) {
                // 非 combo
                $key = implode('_', ['k', $line->id]);
                if (!isset($linesKeyedQty[$key])) {
                    $linesKeyedQty[$key] = 0;
                }
                $linesKeyedQty[$key] += $line->qty;
                continue;
            }
            // combo
            $comboInfo = json_decode($line->combo_info, true);
            foreach ($comboInfo as $comboItem) {
                $parentSkuQty = $comboItem[$line->item_code];
                unset($comboItem[$line->item_code]);
                $comboItem = collect($comboItem)
                    ->map(function ($value, $key) {
                        return ['item_code' => $key, 'qty' => $value];
                    })->values();
                $subItems = $comboItem->values()->sortBy('item_code'); // 子 sku 按照 item_code 排序，因为该值在 json 中无序
                // $key = implode('_', ['k', $lineId, 排序后的子item_code+数量])
                $key = collect(['k', $line->id])->merge($subItems->flatten())->implode('_');
                if (!isset($linesKeyedQty[$key])) {
                    $linesKeyedQty[$key] = 0;
                }
                $linesKeyedQty[$key] += $parentSkuQty;
            }
        }

        $deleteIds = []; // 需要删除绑定关系的
        $updateData = []; // 需要更新数量信息的
        foreach ($associatedModels as $item) {
            if (!$item->orderCombos) {
                // 非 combo
                $key = implode('_', ['k', $item->sales_order_line_id]);
            } else {
                // combo
                $subItems = $item->orderCombos->sortBy('set_item_code')->map(function (OrderCombo $combo) {
                    return ['item_code' => $combo->set_item_code, 'qty' => $combo->qty];
                });
                $key = collect(['k', $item->sales_order_line_id])->merge($subItems->flatten())->implode('_');
            }
            $realQty = $linesKeyedQty->get($key, 0);
            if ($realQty <= 0) {
                // 不存在时或者不足时，删除绑定关系
                $deleteIds[] = $item->id;
                continue;
            }
            if ($item->qty <= $realQty) {
                // 当前绑定的比实际需要的少，保留
                $linesKeyedQty[$key] -= $item->qty;
                continue;
            }
            // 当前绑定的比实际的多，删除部分
            $updateData[$item->id] = [
                'qty' => $realQty,
            ];
            $logInfo['修改前'][$item->id] = [
                'qty' => $item->qty,
            ];
        }
        $logInfo['删除'] = $deleteIds;
        $logInfo['修改'] = $updateData;
        Logger::salesOrder($logInfo);

        dbTransaction(function () use ($deleteIds, $updateData) {
            if ($deleteIds) {
                $this->deleteAssociatedRecord($deleteIds);
            }
            if ($updateData) {
                foreach ($updateData as $id => $data) {
                    OrderAssociated::query()->where('id', $id)->update($data);
                }
            }
        });

        return true;
    }

    /**
     * 删除绑定记录
     * @param array $ids
     */
    protected function deleteAssociatedRecord($ids)
    {
        $modelsKeyById = OrderAssociated::query()->whereIn('id', $ids)->get()->keyBy('id');
        // 插入新增记录
        db('tb_sys_order_associated_deleted_record')->insert(array_map(function ($id) use ($modelsKeyById) {
            $associated = $modelsKeyById[$id];
            return [
                'id' => $associated->id,
                'sales_order_id' => $associated->sales_order_id,
                'sales_order_line_id' => $associated->sales_order_line_id,
                'order_id' => $associated->order_id,
                'order_product_id' => $associated->order_product_id,
                'qty' => $associated->qty,
                'product_id' => $associated->product_id,
                'seller_id' => $associated->seller_id,
                'buyer_id' => $associated->buyer_id,
                'pre_id' => $associated->pre_id,
                'image_id' => $associated->image_id,
                'Memo' => $associated->Memo,
                'CreateUserName' => $associated->CreateUserName,
                'CreateTime' => $associated->CreateTime,
                'UpdateUserName' => $associated->UpdateUserName,
                'UpdateTime' => $associated->UpdateTime,
                'ProgramCode' => $associated->ProgramCode,
                'created_time' => date('Y-m-d H:i:s'),
                'created_user_name' => customer() && customer()->isLogged() ? customer()->getModel()->full_name : 'b2b-system',
            ];
        }, $ids));
        // 删除绑定关系
        OrderAssociated::query()->whereIn('id', $ids)->delete();
    }
}
