<?php

use App\Enums\FeeOrder\FeeOrderStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Models\Delivery\CostDetail;
use App\Models\Delivery\DeliveryLine;
use App\Models\Delivery\ReceiveLine;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Link\OrderAssociated;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesReorder;
use App\Models\SalesOrder\CustomerSalesReorderLine;
use App\Services\FeeOrder\FeeOrderService;
use Carbon\Carbon;
use Framework\App;

class ControllerAccountOutstock extends \Controller
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        if (OC_ENV != 'dev') {
            header('/');
        }
    }

    // 后续去掉
    public function salesOrder()
    {
        $msg = validator($this->request->attributes->all(), [
            'order_id' => 'required',
            'user_number' => 'required',
        ]);
        if (count($msg->errors()) > 0) {
            return $this->response->redirectTo($this->url->link('error/not_found'));
        }
        $orderId = trim($this->request->attributes->get('order_id'));
        $userNumber = trim($this->request->attributes->get('user_number'));
        $salesOrder = CustomerSalesOrder::query()
            ->with(['orderAssociates'])
            ->alias('cso')
            ->select(['cso.*'])
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'cso.buyer_id')
            ->where([
                'cso.order_id' => $orderId,
                'c.user_number' => $userNumber,
            ])
            ->first();
        if (!$salesOrder) {
            return $this->response->redirectTo($this->url->link('error/not_found'));
        }
        $con = App::orm()->getConnection();
        try {
            $con->beginTransaction();
            // 销售单状态已完成
            $salesOrder->order_status = CustomerSalesOrderStatus::COMPLETED;
            $salesOrder->save();
            $associates = $salesOrder->orderAssociates;
            $associates->each(function (OrderAssociated $item) {
                // 对于每条明细找到对应的receive line明细
                $receiveLine = ReceiveLine::query()
                    ->with(['costDetail'])
                    ->where([
                        'oc_order_id' => $item->order_id,
                        'product_id' => $item->product_id,
                    ])
                    ->first();
                $costDetail = $receiveLine->costDetail;
                if ($costDetail->onhand_qty < $item->qty) {
                    throw new Exception("库存数量不足，绑定明细[{$item->id}],需要[{$item->qty}],实际[{$costDetail->onhand_qty}]");
                }
                // cost detail 出库
                $costDetail->onhand_qty = $costDetail->onhand_qty - $item->qty;
                $costDetail->save();
                // delivery line 记录
                $delivery = new DeliveryLine();
                $delivery->SalesHeaderId = $item->sales_order_id;
                $delivery->SalesLineId = $item->sales_order_line_id;
                $delivery->TrackingId = 0;
                $delivery->ProductId = $item->product_id;
                $delivery->DeliveryType = 1;
                $delivery->DeliveryQty = $item->qty;
                $delivery->CostId = $costDetail->id;
                $delivery->Memo = 'wangjinxin add';
                $delivery->create_user_name = 'wangjinxin';
                $delivery->create_time = Carbon::now();
                $delivery->save();
            });
            // 费用单支付
            $feeOrder = FeeOrder::query()
                ->where([
                    'order_id' => $salesOrder->id,
                    'buyer_id' => $salesOrder->buyer_id,
                    'status' => FeeOrderStatus::WAIT_PAY,
                ])
                ->orderByDesc('id')
                ->first();
            if ($feeOrder) {
                app(FeeOrderService::class)->changeFeeOrderStatus($feeOrder, FeeOrderStatus::COMPLETE);
            }
            $con->commit();
        } catch (Exception $e) {
            $con->rollBack();
            throw new Exception($e);
        }
        return $this->response->setOutput("销售单{$salesOrder->order_id}出库成功");
    }

    // 后续去掉
    public function salesReOrder()
    {
        $msg = validator($this->request->attributes->all(), [
            'order_id' => 'required',
            'user_number' => 'required',
        ]);
        if (count($msg->errors()) > 0) {
            return $this->response->redirectTo($this->url->link('error/not_found'));
        }
        $orderId = trim($this->request->attributes->get('order_id'));
        $userNumber = trim($this->request->attributes->get('user_number'));
        $salesReOrder = CustomerSalesReorder::query()
            ->with(['reorderLines'])
            ->alias('csro')
            ->select(['csro.*'])
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'csro.buyer_id')
            ->where([
                'csro.reorder_id' => $orderId,
                'c.user_number' => $userNumber,
            ])
            ->first();
        if (!$salesReOrder) {
            return $this->response->redirectTo($this->url->link('error/not_found'));
        }
        $con = App::orm()->getConnection();
        try {
            $con->beginTransaction();
            // 重发单状态已完成
            $salesReOrder->order_status = CustomerSalesOrderStatus::COMPLETED;
            $salesReOrder->save();
            $reorderLines = $salesReOrder->reorderLines;
            $reorderLines->each(function (CustomerSalesReorderLine $reorderLine) use ($salesReOrder) {
                $costDetail = CostDetail::query()
                    ->where([
                        'rma_id' => $salesReOrder->rma_id,
                        'sku_id' => $reorderLine->product_id,
                    ])
                    ->first();
                if (!$costDetail || $costDetail->onhand_qty < $reorderLine->qty) {
                    $actualQty = $costDetail ? $costDetail->onhand_qty : 0;
                    throw new Exception(
                        "库存数量不足，绑定明细[{$reorderLine->id}],需要[{$reorderLine->qty}],实际[{$actualQty}]"
                    );
                }
                // cost detail 出库
                $costDetail->onhand_qty = $costDetail->onhand_qty - $reorderLine->qty;
                $costDetail->save();
                // delivery line 记录
                $delivery = new DeliveryLine();
                $delivery->SalesHeaderId = $reorderLine->reorder_header_id;
                $delivery->SalesLineId = $reorderLine->id;
                $delivery->TrackingId = 0;
                $delivery->ProductId = $reorderLine->product_id;
                $delivery->DeliveryType = 1;
                $delivery->DeliveryQty = $reorderLine->qty;
                $delivery->CostId = $costDetail->id;
                $delivery->Memo = 'wangjinxin add';
                $delivery->create_user_name = 'wangjinxin';
                $delivery->create_time = Carbon::now();
                $delivery->save();
            });
            $con->commit();
        } catch (Exception $e) {
            $con->rollBack();
            throw new Exception($e);
        }
        return $this->response->setOutput("重发单:{$salesReOrder->reorder_id}出库成功");
    }
}
