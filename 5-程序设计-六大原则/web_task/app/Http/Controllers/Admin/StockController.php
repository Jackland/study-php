<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AdminErrorShowException;
use App\Helpers\EloquentHelper;
use App\Models\Customer\Customer;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class StockController extends Controller
{
    //region 绑定库存
    public function bindIndex(Request $request)
    {
        if ($request->filled(['sales_order_id'])) {
            //全部输入才能继续
            $salesOrderId = $request->input('sales_order_id');
            $bindData = $this->getBindData($salesOrderId);
            if (!$bindData['success']) {
                // 如果不允许执行，返回错误
                unset($bindData['success']);
                return view('admin.stock.bind', $bindData);
            }
            if ($request->filled('save')) {
                // 执行按钮，执行sql
                $res = $this->sqlStatement($bindData['sqls'], 'BIND');
                if ($res) {
                    return redirect()->route('stock.bind', $request->all(['sales_order_id']))
                        ->with('success', "操作成功");
                } else {
                    return redirect()->route('stock.bind', $request->all(['sales_order_id']))
                        ->withErrors(['error_message' => "操作失败"]);
                }
            }
            // 如果允许执行，返回需要执行的信息
            return view('admin.stock.bind', [
                'canSave' => !empty($bindData['sqls'])
            ])->with($bindData);
        }
        return view('admin.stock.bind');
    }

    /**
     * 获取绑定库存的信息
     *
     * @param $salesOrderId
     * @return array ['success'=>bool,'sqls'=>[],...]
     */
    private function getBindData($salesOrderId)
    {
        $return = [
            'success' => false,
        ];
        $userName = Auth::user()->name;
        // 查询销售订单信息
        $salesOrder = $this->getSalesOrderData($salesOrderId);
        if (!$salesOrder) {
            $return['msg'] = '订单不存在';
            return $return;
        }
        $return['salesOrder'] = $salesOrder;
        // 订单状态必须要是1
        if ($salesOrder->order_status != 1) {
            $return['msg'][] = '订单状态错误';
        }
        $salesOrderLines = $this->getSalesOrderLines($salesOrder->id);
        if ($salesOrderLines->count() <= 0) {
            $return['msg'][] = '订单不存在line';
        }
        $return['salesOrderLines'] = $salesOrderLines;
        // 订单必须要是没绑定的,也就是在tb_sys_order_associated表没记录，也就是下面要插入的数据
        // 查询已经绑定的库存数量，如果不足需要补
        $associatedList = $this->getAssociatedList($salesOrder->id);
        if ($associatedList->isNotEmpty()) {
            $return['associatedList'] = $associatedList;
            $return['msg'][] = '订单已绑定库存';
        }
        $allStockList = [];
        $isStorageFee = false;// 是否有仓租，用于判断订单状态改为BP 还是pending charges
        $storageFeeUpdate = [];
        $sqls = [];
        $storageFeeData = [];
        foreach ($salesOrderLines as $salesOrderLine) {
            $stockList = $this->getStockData($salesOrder->buyer_id, $salesOrderLine->item_code);
            // 只要库存大于0的
            $stockList = $stockList->where('leftQty', '>', 0);
            // 如果没有，直接返回错误
            if ($stockList->count() == 0) {
                $return['msg'][] = $salesOrderLine->item_code . '库存信息不存在';
                continue;
            }
            // 组装所有库存信息，用于前端展示
            $allStockList = array_merge($allStockList, $stockList->toArray());

            $totalQty = $salesOrderLine->qty;//需要消耗的库存
            // 判断库存是否充足
            if ($stockList->sum('leftQty') < $totalQty || $stockList->sum('onhand_qty') < $totalQty) {
                $return['msg'][] = $salesOrderLine->item_code . '库存不足';
                continue;
            }
            $comboInfoList = [];
            foreach ($stockList as $stock) {
                if ($stock->leftQty > 0) {
                    if ($stock->leftQty >= $totalQty) {
                        // 如果剩余库存大于总库存，则直接消耗
                        $sqls[] = "INSERT INTO tb_sys_order_associated (sales_order_id,sales_order_line_id,order_id,order_product_id,qty,product_id,seller_id,buyer_id,image_id,Memo,CreateUserName) 
                            VALUE ('{$salesOrder->id}','{$salesOrderLine->id}','{$stock->oc_order_id}','{$stock->order_product_id}','{$totalQty}','{$stock->sku_id}','{$stock->seller_id}','{$stock->buyer_id}','0','手动绑定库存','{$userName}');";
                        $bindQty = $totalQty;
                        $totalQty = 0;
                    } else {
                        //小于先有多少消耗多少
                        $sqls[] = "INSERT INTO tb_sys_order_associated (sales_order_id,sales_order_line_id,order_id,order_product_id,qty,product_id,seller_id,buyer_id,image_id,Memo,CreateUserName) 
                            VALUE ('{$salesOrder->id}','{$salesOrderLine->id}','{$stock->oc_order_id}','{$stock->order_product_id}','{$stock->leftQty}','{$stock->sku_id}','{$stock->seller_id}','{$stock->buyer_id}','0','手动绑定库存','{$userName}');";
                        $bindQty = $stock->leftQty;
                        $totalQty -= $stock->leftQty;
                    }
                    // 组装combo信息
                    if ($this->isCombo($stock->sku_id)) {
                        $comboInfo = $this->getComboInfoByOrderCombo($stock->oc_order_id, $salesOrderLine->item_code);
                        $comboInfoList[] = array_merge([$salesOrderLine->item_code => $bindQty], $comboInfo);
                    }
                    if ($bindQty > 0) {
                        // 获取仓租信息
                        $storageFee = $this->getStorageFee($stock->oc_order_id, $stock->order_product_id, $bindQty);
                        if ($storageFee->isNotEmpty()) {
                            if ($storageFee->sum('fee_unpaid') > 0) {
                                $isStorageFee = true;
                            }
                            $storageFeeUpdate[] = [
                                'sales_order_id' => $salesOrder->id,
                                'sales_order_line_id' => $salesOrderLine->id,
                                'storage_fee_ids' => $storageFee->implode('id', ','),
                            ];
                            $storageFeeData = array_merge($storageFeeData,$storageFee->toArray());
                        }
                    }
                    // 如果都匹配上了直接结束
                    if ($totalQty <= 0) {
                        break;
                    }
                }
            }
            // 如果有combo信息，生成combo sql
            if (!empty($comboInfoList)) {
                $comboInfoStr = json_encode($comboInfoList);
                $sqls[] = "UPDATE tb_sys_customer_sales_order_line SET combo_info = '$comboInfoStr' WHERE id = {$salesOrderLine->id};";
            }
        }
        if (!empty($return['msg'])) {
            //已上操作有错误信息都打出
            return $return;
        }
        $salesOrderStatus = 127;
        foreach ($storageFeeUpdate as $item) {
            $sqls[] = "UPDATE oc_storage_fee SET sales_order_id = {$item['sales_order_id']},sales_order_line_id = {$item['sales_order_line_id']},`status`=20 WHERE id in ({$item['storage_fee_ids']})";
        }
        if ($salesOrder->order_status != $salesOrderStatus) {
            $sqls[] = "UPDATE tb_sys_customer_sales_order SET order_status={$salesOrderStatus} where id = {$salesOrder->id};";
        }
        $return['storageFeeData'] = $storageFeeData;
        $return['stockList'] = $allStockList;
        $return['success'] = true;
        $return['sqls'] = $sqls;
        return $return;
    }
    //endregion

    //region FBA出库
    public function fbaIndex(Request $request)
    {
        $this->validate($request, [
            'qty' => 'nullable|integer|min:1|max:999'
        ]);
        if ($request->filled(['user_number', 'item_code', 'qty'])) {
            $userNumber = $request->input('user_number');
            $itemCode = $request->input('item_code');
            $orderId = (int)$request->input('order_id', 0);
            $qty = $request->input('qty');
            $fbaData = $this->getFbaData($userNumber, $itemCode, $qty, $orderId);
            if (!$fbaData['success']) {
                // 如果不允许执行，返回错误
                unset($fbaData['success']);
                return view('admin.stock.fba', $fbaData);
            }
            if ($request->filled('save')) {
                // 执行按钮，执行sql
                $res = $this->sqlStatement($fbaData['sqls'], 'FBA');
                if ($res) {
                    return redirect()->route('stock.fba', $request->all(['user_number', 'item_code', 'qty' ,'order_id']))
                        ->with('success', "操作成功");
                } else {
                    return redirect()->route('stock.fba', $request->all(['user_number', 'item_code', 'qty' ,'order_id']))
                        ->withErrors(['error_message' => "操作失败"]);
                }
            }
            // 如果允许执行，返回需要执行的信息
            return view('admin.stock.fba', [
                'canSave' => !empty($fbaData['sqls'])
            ])->with($fbaData);
        }
        return view('admin.stock.fba');
    }

    /**
     * 获取BO出库的信息
     *
     * @param string $userNumber
     * @param string $itemCode
     * @param int $qty
     * @param int $orderId
     *
     * @return array ['success'=>bool,'sqls'=>[],...]
     */
    private function getFbaData(string $userNumber, string $itemCode, int $qty, int $orderId = 0)
    {
        $return = [
            'success' => false,
        ];
        $userName = Auth::user()->name;
        // 查询用户信息
        $customerId = Customer::where('user_number', $userNumber)->value('customer_id');
        if (!$customerId) {
            $return['msg'] = '用户不存在';
            return $return;
        }
        // 查询用户sku的库存信息
        $stockList = $this->getStockData($customerId, $itemCode, $orderId);
        if ($stockList->isEmpty()) {
            $return['msg'] = '无库存';
            return $return;
        }
        // 判断库存是否充足
        $return['stockList'] = $stockList;
        $onhandQty = $stockList->sum('onhand_qty');
        $leftQty = $stockList->sum('leftQty');
        if ($onhandQty < $qty || $leftQty < $qty) {
            $return['msg'] = '库存不足';
            return $return;
        }
        $sqls = [];
        $storageFeeData = [];
        foreach ($stockList as $stock) {
            if ($stock->leftQty > 0) {
                if ($stock->leftQty >= $qty) {
                    //如果剩余库存大于总库存，则直接消耗
                    $sqls[] = "INSERT INTO tb_sys_order_associated (sales_order_id,sales_order_line_id,order_id,order_product_id,qty,product_id,seller_id,buyer_id,image_id,Memo,CreateUserName) 
                            VALUE ('0','0','{$stock->oc_order_id}','{$stock->order_product_id}','{$qty}','{$stock->sku_id}','{$stock->seller_id}','{$stock->buyer_id}','0','FBA 出库','{$userName}');";
                    $sqls[] = "INSERT INTO tb_sys_delivery_line (SalesHeaderId,SalesLineId,TrackingId,ProductId,DeliveryType,DeliveryQty,CostId,type,Memo,create_user_name,create_time
                   ) VALUE (0,0,0,{$stock->sku_id},1,{$qty},{$stock->id},1,'FBA 出库','{$userName}',now());";
                    $sqls[] = "UPDATE tb_sys_cost_detail SET onhand_qty = onhand_qty - {$qty} WHERE id = {$stock->id};";
                    $bindQty = $qty;
                    $qty = 0;
                } else {
                    //小于先有多少消耗多少
                    $sqls[] = "INSERT INTO tb_sys_order_associated (sales_order_id,sales_order_line_id,order_id,order_product_id,qty,product_id,seller_id,buyer_id,image_id,Memo,CreateUserName) 
                            VALUE ('0','0','{$stock->oc_order_id}','{$stock->order_product_id}','{$stock->leftQty}','{$stock->sku_id}','{$stock->seller_id}','{$stock->buyer_id}','0','FBA 出库','{$userName}');";
                    $sqls[] = "INSERT INTO tb_sys_delivery_line (SalesHeaderId,SalesLineId,TrackingId,ProductId,DeliveryType,DeliveryQty,CostId,type,Memo,create_user_name,create_time
                            ) VALUE (0,0,0,{$stock->sku_id},1,{$stock->leftQty},{$stock->id},1,'FBA 出库','{$userName}',now());";
                    $sqls[] = "UPDATE tb_sys_cost_detail SET onhand_qty = onhand_qty - {$stock->leftQty} WHERE id = {$stock->id};";
                    $bindQty = $stock->leftQty;
                    $qty -= $stock->leftQty;
                }
                //查询仓租信息
                $storageFee = $this->getStorageFee($stock->oc_order_id, $stock->order_product_id, $bindQty);
                if ($storageFee->isNotEmpty()) {
                    $storageFeeId = $storageFee->implode('id', ',');
                    $sqls[] = "UPDATE oc_storage_fee SET `status`=30,end_type = 4 WHERE id in ({$storageFeeId});";
                    $storageFeeData = array_merge($storageFeeData,$storageFee->toArray());
                }
                if ($qty == 0) {
                    break;
                }
            }
        }
        $return['storageFeeData'] = $storageFeeData;
        $return['success'] = true;
        $return['sqls'] = $sqls;
        return $return;
    }
    //endregion

    //region BO出库
    public function boIndex(Request $request)
    {
        $this->validate($request, [
            'qty' => 'nullable|integer|min:1|max:999'
        ]);
        if ($request->filled(['user_number', 'item_code', 'qty'])) {
            //全部输入才能继续
            $userNumber = $request->input('user_number');
            $salesOrderId = (string)$request->input('sales_order_id', '');
            $orderId = (string)$request->input('order_id', '');
            $itemCode = $request->input('item_code');
            $qty = $request->input('qty');
            $boData = $this->getBoData($userNumber, $salesOrderId, $orderId, $itemCode, $qty);
            if (!$boData['success']) {
                // 如果不允许执行，返回错误
                unset($boData['success']);
                return view('admin.stock.bo', $boData);
            }
            if ($request->filled('save')) {
                // 执行按钮，执行sql
                $res = $this->sqlStatement($boData['sqls'], 'BO');
                if ($res) {
                    return redirect()->route('stock.bo', $request->all(['user_number', 'sales_order_id', 'order_id', 'item_code', 'qty']))
                        ->with('success', "操作成功");
                } else {
                    return redirect()->route('stock.bo', $request->all(['user_number', 'sales_order_id', 'order_id', 'item_code', 'qty']))
                        ->withErrors(['error_message' => "操作失败"]);
                }
            }
            // 如果允许执行，返回需要执行的信息
            return view('admin.stock.bo', [
                'canSave' => !empty($boData['sqls'])
            ])->with($boData);
        }
        return view('admin.stock.bo');
    }

    /**
     * 获取BO出库的信息
     *
     * @param string $userNumber 用户号
     * @param string $salesOrderId 销售订单ID
     * @param string $orderId 采购订单ID
     * @param string $itemCode
     * @param integer $qty
     *
     * @return array ['success'=>bool,'sqls'=>[],...]
     */
    private function getBoData(string $userNumber, string $salesOrderId, string $orderId, string $itemCode, int $qty)
    {
        $return = [
            'success' => false,
        ];
        $userName = Auth::user()->name;
        // 查询用户信息
        $customerId = Customer::where('user_number', $userNumber)->value('customer_id');
        if (!$customerId) {
            $return['msg'] = '用户不存在';
            return $return;
        }
        $salesOrder = null;
        if ($salesOrderId) {
            $salesOrder = $this->getSalesOrderData($salesOrderId);
            // 查询销售订单信息 订单状态要为16
            if (!$salesOrder) {
                $return['msg'] = '订单不存在';
                return $return;
            }
            // 订单状态必须要是16
            $return['salesOrder'] = $salesOrder;
            if ($salesOrder->order_status != 16) {
                $return['msg'] = '订单状态错误';
                return $return;
            }
            // 查询对应item code的销售订单明细
            $salesOrderLines = $this->getSalesOrderLines($salesOrder->id, $itemCode);
            if ($salesOrderLines->count() <= 0) {
                $return['msg'] = '订单明细不存在';
                return $return;
            }
            $return['salesOrderLines'] = $salesOrderLines;
        }
        $stockList = $this->getStockData($customerId, $itemCode, $orderId);
        if ($stockList->isEmpty()) {
            $return['msg'] = '库存信息不存在';
            return $return;
        }
        $sqls = [];
        $storageFeeData = [];
        // 查询对应采购单的库存信息
        if ($orderId > 0) {
            //如果指定了批次
            // 比较库存数量
            $stockData = $stockList->first();
            // 查询已经绑定的库存数量，如果不足需要补
            $associatedQty = $qty;
            if ($salesOrder) {
                $associatedList = $this->getAssociatedList($salesOrder->id, $stockData->order_product_id);
                if ($associatedList->isNotEmpty()) {
                    $associatedQty -= $associatedList->sum('qty');
                    $return['associatedList'] = $associatedList;
                }
            }
            $onhandQty = $stockData->onhand_qty;
            $leftQty = $stockData->leftQty;
            if ($onhandQty < $associatedQty || $leftQty < $associatedQty) {
                $return['msg'] = '库存不足';
                return $return;
            }
            if ($associatedQty > 0) {
                $sqls[] = "INSERT INTO tb_sys_order_associated (sales_order_id,sales_order_line_id,order_id,order_product_id,qty,product_id,seller_id,buyer_id,image_id,Memo,CreateUserName) 
                            VALUE ('0','0','{$stockData->oc_order_id}','{$stockData->order_product_id}','{$associatedQty}','{$stockData->sku_id}','{$stockData->seller_id}','{$stockData->buyer_id}','0','BO 出库','{$userName}');";
                //查询仓租信息
                $storageFee = $this->getStorageFee($stockData->oc_order_id, $stockData->order_product_id, $associatedQty);
                if ($storageFee->isNotEmpty()) {
                    $storageFeeId = $storageFee->implode('id', ',');
                    $sqls[] = "UPDATE oc_storage_fee SET `status`=30,end_type = 3 WHERE id in ({$storageFeeId});";
                    $storageFeeData = array_merge($storageFeeData,$storageFee->toArray());
                }
            }
            // 查询出库记录是否正确，如果不足要补记录
            $deliveryLines = $this->getDeliveryLines($stockData->id);
            if ($deliveryLines->isNotEmpty()) {
                $return['deliveryLines'] = $deliveryLines;
                $deliveryQty = $deliveryLines->sum('DeliveryQty');
                $costDetail = $this->getCostDetail($stockData->id);
                if ($deliveryQty != ($costDetail->original_qty - $costDetail->onhand_qty)) {
                    // 这种情况碰到再考虑兼容
                    $return['msg'] = '出库数量错误';
                    return $return;
                }
            }
            $sqls[] = "INSERT INTO tb_sys_delivery_line (SalesHeaderId,SalesLineId,TrackingId,ProductId,DeliveryType,DeliveryQty,CostId,type,Memo,create_user_name,create_time
                ) VALUE (0,0,0,{$stockData->sku_id},1,{$qty},{$stockData->id},6,'BO 出库','{$userName}',now());";
            // 在库库存要扣数量
            $sqls[] = "UPDATE tb_sys_cost_detail SET onhand_qty = onhand_qty - {$qty} WHERE id = {$stockData->id};";
        } else {
            //没有指定采购单，循环匹配
            $onhandQty = $stockList->sum('onhand_qty');
            $leftQty = $stockList->sum('leftQty');
            if ($onhandQty < $qty || $leftQty < $qty) {
                $return['msg'] = '库存不足';
                return $return;
            }
            foreach ($stockList as $stock) {
                if ($stock->leftQty > 0) {
                    if ($stock->leftQty >= $qty) {
                        $associatedQty = $qty;
                        $qty = 0;
                    } else {
                        //小于先有多少消耗多少
                        $qty -= $stock->leftQty;
                        $associatedQty = $stock->leftQty;

                    }
                    if ($salesOrder) {
                        //判断是否已经绑定过库存
                        $associatedList = $this->getAssociatedList($salesOrder->id, $stock->order_product_id);
                        if ($associatedList->isNotEmpty()) {
                            $associatedQty -= $associatedList->sum('qty');
                            $return['associatedList'] = $associatedList;
                        }
                    }
                    if ($associatedQty > 0) {
                        $sqls[] = "INSERT INTO tb_sys_order_associated (sales_order_id,sales_order_line_id,order_id,order_product_id,qty,product_id,seller_id,buyer_id,image_id,Memo,CreateUserName) 
                            VALUE ('0','0','{$stock->oc_order_id}','{$stock->order_product_id}','{$associatedQty}','{$stock->sku_id}','{$stock->seller_id}','{$stock->buyer_id}','0','BO 出库','{$userName}');";
                        //查询仓租信息
                        $storageFee = $this->getStorageFee($stock->oc_order_id, $stock->order_product_id, $associatedQty);
                        if ($storageFee->isNotEmpty()) {
                            $storageFeeId = $storageFee->implode('id', ',');
                            $sqls[] = "UPDATE oc_storage_fee SET `status`=30,end_type = 3 WHERE id in ({$storageFeeId});";
                            $storageFeeData = array_merge($storageFeeData,$storageFee->toArray());
                        }
                    }
                    // 查询出库记录是否正确，如果不足要补记录
                    $deliveryLines = $this->getDeliveryLines($stock->id);
                    if ($deliveryLines->isNotEmpty()) {
                        $return['deliveryLines'] = $deliveryLines;
                        $deliveryQty = $deliveryLines->sum('DeliveryQty');
                        $costDetail = $this->getCostDetail($stock->id);
                        if ($deliveryQty != ($costDetail->original_qty - $costDetail->onhand_qty)) {
                            // 这种情况碰到再考虑兼容
                            $return['msg'] = '出库数量错误';
                            return $return;
                        }
                    }
                    $sqls[] = "INSERT INTO tb_sys_delivery_line (SalesHeaderId,SalesLineId,TrackingId,ProductId,DeliveryType,DeliveryQty,CostId,type,Memo,create_user_name,create_time
                                ) VALUE (0,0,0,{$stock->sku_id},1,{$qty},{$stock->id},6,'BO 出库','{$userName}',now());";
                    // 在库库存要扣数量
                    $sqls[] = "UPDATE tb_sys_cost_detail SET onhand_qty = onhand_qty - {$qty} WHERE id = {$stock->id};";
                    if ($qty == 0) {
                        break;
                    }
                }
            }
        }
        $return['stockList'] = $stockList;
        $return['storageFeeData'] = $storageFeeData;
        $return['success'] = true;
        $return['sqls'] = $sqls;
        return $return;
    }
    //endregion

    //region 公用方法
    /**
     * 获取库存信息
     *
     * @param int $buyerId
     * @param string $sku
     * @param int $orderId
     * @return \Illuminate\Support\Collection
     */
    private function getStockData(int $buyerId, string $sku, $orderId = 0)
    {
        $orderAssociated = DB::table('tb_sys_order_associated')->groupBy(['order_product_id'])->select([
            DB::raw('sum(qty) AS associateQty'),
            'order_product_id'
        ]);
        $rmaOrder = DB::table('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->where('ro.buyer_id', $buyerId)->where('ro.cancel_rma', 0)->where('status_refund', '<>', 2)
            ->where('ro.order_type', 2)->groupBy(['rop.product_id', 'ro.order_id'])->select([
                'rop.product_id',
                'ro.order_id',
                DB::raw('sum(rop.quantity) AS qty'),
            ]);
        return DB::table('tb_sys_cost_detail as cost')->leftJoin('oc_product as p', 'cost.sku_id', '=', 'p.product_id')
            ->leftJoin('tb_sys_receive_line as rline', 'rline.id', '=', 'cost.source_line_id')
            ->leftJoin('oc_order_product as ocp', function ($join) {
                $join->on('ocp.order_id', '=', 'rline.oc_order_id')->on('ocp.product_id', '=', 'cost.sku_id');
            })->leftJoin(DB::raw('(' . EloquentHelper::getCompleteSql($orderAssociated) . ') as t'), 't.order_product_id', '=', 'ocp.order_product_id')
            ->leftJoin(DB::raw('(' . EloquentHelper::getCompleteSql($rmaOrder) . ') as t2'), function ($join) {
                $join->on('t2.product_id', '=', 'ocp.product_id')->on('t2.order_id', '=', 'ocp.order_id');
            })->where('cost.onhand_qty', '>=', 0)->where('type', 1)->where('p.sku', $sku)
            ->when($orderId, function ($query) use ($orderId) {
                $query->where('rline.oc_order_id', $orderId);
            })->where('cost.buyer_id', $buyerId)->select([
                'cost.seller_id',
                'cost.buyer_id',
                'cost.onhand_qty',
                'cost.id',
                'rline.oc_order_id',
                'cost.sku_id',
                'ocp.order_product_id',
                DB::raw('cost.original_qty - ifnull(t.associateQty, 0)-ifnull(t2.qty,0) AS leftQty')
            ])->get();
    }

    /**
     * 获取库存明细信息
     *
     * @param $costId
     * @return \Illuminate\Database\Query\Builder|mixed
     */
    private function getCostDetail($costId)
    {
        return DB::table('tb_sys_cost_detail')->find($costId);
    }

    /**
     * 获取出库记录
     *
     * @param $costId
     * @return \Illuminate\Support\Collection
     */
    private function getDeliveryLines($costId)
    {
        return DB::table('tb_sys_delivery_line')->where('CostId', $costId)->get();
    }

    /**
     * 获取销售订单详情
     *
     * @param $orderId
     * @return object|null
     */
    private function getSalesOrderData($orderId)
    {
        return DB::table('tb_sys_customer_sales_order')->where('order_id', $orderId)->select([
            'order_id',
            'id',
            'order_status',
            'buyer_id'
        ])->first();
    }

    /**
     * 获取销售订单明细
     *
     * @param $headerId
     * @param null $itemCode
     * @return \Illuminate\Support\Collection
     */
    private function getSalesOrderLines($headerId, $itemCode = null)
    {
        return DB::table('tb_sys_customer_sales_order_line')->where('header_id', $headerId)
            ->when($itemCode, function ($query) use ($itemCode) {
                $query->where('item_code', $itemCode);
            })->select([
                'id',
                'item_code',
                'qty'
            ])->get();
    }

    /**
     * 判断销售订单是否已经绑定库存
     *
     * @param $salesOrderId
     * @return bool
     */
    private function checkBind($salesOrderId)
    {
        $orderAssociated = $this->getAssociatedList($salesOrderId);
        return $orderAssociated->isNotEmpty();
    }

    /**
     * 获取销售订单绑定的库存明细
     *
     * @param     $salesOrderId
     * @param int $orderProductId
     *
     * @return \Illuminate\Support\Collection
     */
    private function getAssociatedList($salesOrderId, $orderProductId = 0)
    {
        return DB::table('tb_sys_order_associated')->where('sales_order_id', $salesOrderId)
            ->when($orderProductId, function ($query) use ($orderProductId) {
                $query->where('order_product_id', $orderProductId);
            })->get();
    }

    /**
     * 获取批次仓租
     *
     * @param $orderId
     * @param $orderProductId
     * @param $qty
     * @return \Illuminate\Support\Collection
     */
    private function getStorageFee($orderId, $orderProductId, $qty)
    {
        return DB::table('oc_storage_fee')->where('order_id', $orderId)->where('order_product_id', $orderProductId)
            ->where('status', 10)
            ->orderBy('created_at')->limit($qty)->get([
                'id', 'order_id', 'order_product_id', 'fee_unpaid', 'days', 'status', 'sales_order_id', 'sales_order_line_id', 'end_type'
            ]);
    }

    /**
     * 判断商品是否是combo
     *
     * @param $productId
     * @return int|mixed
     */
    private function isCombo($productId)
    {
        $product = DB::table('oc_product')->where('product_id', (int)$productId)->first(['combo_flag']);
        return $product->combo_flag ?? 0;
    }

    /**
     * 获取line的combo信息
     *
     * @param $itemCode
     * @param $notLienId
     * @return mixed
     */
    private function getComboInfoByOrderLineItemCode($itemCode, $notLienId)
    {
        return DB::table('tb_sys_customer_sales_order_line')->where('item_code', $itemCode)
            ->where('id', '<>', $notLienId)->select([
                'combo_info',
            ])->orderByDesc('id')->first(['combo_info'])->combo_info;
    }

    /**
     * 获取采购单的combo信息
     *
     * @param $orderId
     * @param $itemCode
     * @return array
     */
    private function getComboInfoByOrderCombo($orderId, $itemCode)
    {
        $list = DB::table('tb_sys_order_combo')->where('order_id', $orderId)->where('item_code', $itemCode)->get([
            'item_code',
            'set_item_code',
            'qty'
        ]);
        $comboInfo = [];
        foreach ($list as $item) {
            $comboInfo[$item->set_item_code] = $item->qty;
        }
        return $comboInfo;
    }

    /**
     * 批量执行sql
     *
     * @param $sqls
     * @param $type
     * @return bool
     * @throws Exception
     */
    private function sqlStatement($sqls, $type)
    {
        Log::useFiles(storage_path('logs/admin/stock.log'));
        Log::info('--------' . $type . '库存绑定开始(' . Auth::user()->name . ')--------');
        DB::beginTransaction();
        try {
            foreach ($sqls as $sql) {
                DB::statement($sql);
            }
            DB::commit();
            $res = true;
        } catch (Exception $exception) {
            DB::rollBack();
            $res = false;
        }
        Log::info('执行结果:' . ($res ? '成功' : '失败'));
        Log::info('--------' . $type . '库存绑定结束--------');
        return $res;
    }
    //endregion
}
