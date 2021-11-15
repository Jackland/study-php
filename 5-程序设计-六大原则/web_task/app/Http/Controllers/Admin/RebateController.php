<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order\Order;
use App\Models\Rebate\Agreement;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class RebateController extends Controller
{
    public function repair(Request $request)
    {
        //查询错误的返点信息
        $orders = $this->getErrorOrders();
        $repairData = $this->getRepairDataByErrorOrder($orders);
        if ($request->filled('repair')) {
            //插入修复数据
            Log::useFiles(storage_path('logs/admin/rebate-repair.log'));
            Log::info('--------返点错误数据修复开始(' . Auth::user()->name . ')--------');
            DB::table('oc_rebate_agreement_order')->insert($repairData);
            Log::info('--------返点错误数据修复结束--------');
            //重新获取数据，理论上应该获取不到了
            $orders = $this->getErrorOrders();
            $repairData = $this->getRepairDataByErrorOrder($orders);
        }
        return view('admin.rebate.repair', compact('orders', 'repairData'));
    }

    /**
     * 获取错误的订单
     *
     * @return Collection
     */
    private function getErrorOrders()
    {
        return Order::query()->leftJoin('oc_order_product as oop', 'oc_order.order_id', '=', 'oop.order_id')
            ->leftJoin('oc_rebate_agreement_order as orao', 'orao.order_id', '=', 'oc_order.order_id')
            ->where('oc_order.order_id', '>', 653790)
            ->where('oc_order.order_status_id',5)
            ->where('oop.type_id', 1)
            ->whereNull('orao.order_id')
            ->get(['oc_order.order_id', 'orao.agreement_id']);
    }

    /**
     * 根据错误订单获取修复数据
     * @param Collection $orders
     * @return array
     */
    private function getRepairDataByErrorOrder(Collection $orders)
    {
        if ($orders->isEmpty()) {
            return [];
        }
        $orderIds = $orders->pluck('order_id')->toArray();
        $insertList = Order::query()->leftJoin('oc_order_product as oop', 'oc_order.order_id', '=', 'oop.order_id')
            ->leftJoin('oc_rebate_agreement_item as orai', function ($join) {
                $join->on('orai.agreement_id', '=', 'oop.agreement_id')
                    ->whereColumn('orai.product_id', '=', 'oop.product_id');
            })
            ->whereIn('oc_order.order_id', $orderIds)
            ->where('oop.type_id', 1)
            ->whereNotNull('oop.agreement_id')
            ->get(['oop.agreement_id', 'orai.id', 'oop.product_id', 'oop.quantity', 'oc_order.order_id', 'oop.order_product_id', 'oc_order.date_modified']);
        $insertData = [];
        foreach ($insertList as $item) {
            $insertData[] = [
                'agreement_id' => $item->agreement_id,
                'item_id' => $item->id,
                'product_id' => $item->product_id,
                'qty' => $item->quantity,
                'order_id' => $item->order_id,
                'order_product_id' => $item->order_product_id,
                'type' => 1,
                'memo' => '补录',
                'create_user_name' => Auth::user()->name,
                'create_time' => $item->date_modified,
                'update_time' => $item->date_modified,
                'program_code' => 'V1.0',
            ];
        }
        return $insertData;
    }
}
