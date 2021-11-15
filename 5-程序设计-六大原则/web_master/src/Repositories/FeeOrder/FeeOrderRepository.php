<?php

namespace App\Repositories\FeeOrder;

use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Common\YesNoEnum;
use App\Enums\Country\Country;
use App\Enums\FeeOrder\FeeOrderFeeType;
use App\Enums\FeeOrder\FeeOrderOrderType;
use App\Enums\FeeOrder\FeeOrderStatus;
use App\Enums\FeeOrder\StorageFeeStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Link\OrderAssociatedPre;
use App\Models\Product\Product;
use App\Models\SalesOrder\CustomerOrderModifyLog;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Safeguard\SafeguardBillRepository;
use Carbon\Carbon;
use Illuminate\Database\Concerns\BuildsQueries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use kriss\bcmath\BCS;
use ModelAccountSalesOrderMatchInventoryWindow;
use ModelToolImage;
use Exception;

class FeeOrderRepository
{
    use RequestCachedDataTrait;

    /**
     * @param $feeOrderIdArr
     * @param null|int $feeType
     * @return array
     */
    public function findFeeOrderInfo($feeOrderIdArr, $feeType = null)
    {
        return FeeOrder::query()
            ->whereIn('id', $feeOrderIdArr)
            ->when($feeType, function ($query) use ($feeType) {
                $query->where('fee_type', $feeType);
            })
            ->get()
            ->toArray();
    }

    /**
     * @param $feeOrderIdArr
     * @param null|int $feeType
     * @return int|mixed
     */
    public function findFeeOrderTotal($feeOrderIdArr, $feeType = null)
    {
        return FeeOrder::query()
            ->whereIn('id', $feeOrderIdArr)
            ->when($feeType, function ($query) use ($feeType) {
                $query->where('fee_type', $feeType);
            })
            ->sum('fee_total');
    }

    /**
     * @param $feeOrderIdArr
     * @param null|int $feeType
     * @return bool
     */
    public function checkFeeOrdersUseBalance($feeOrderIdArr, $feeType = null)
    {
        return FeeOrder::query()
            ->whereIn('id', $feeOrderIdArr)
            ->where('balance', '>', 0)
            ->when($feeType, function ($query) use ($feeType) {
                $query->where('fee_type', $feeType);
            })
            ->exists();
    }

    /**
     * 获取费用单提交数据
     *
     * @param array $saleOrderIdArr
     * @param array $storageFeeOrderIds
     * @param array $safeguardFeeOrderIds
     * @param int $customerId
     * @return array
     */
    public function getFeeOrderConfirmDataById(array $saleOrderIdArr, array $storageFeeOrderIds, array $safeguardFeeOrderIds, $customerId)
    {
        /** @var ModelToolImage $modelToolImage */
        $modelToolImage = load()->model('tool/image');
        $list = [];
        $totalFee = 0;
        //获取费用单信息
        $saleOrderList = CustomerSalesOrder::query()->whereIn('id', $saleOrderIdArr)->where('buyer_id', $customerId)
            ->with([
                'feeOrders' => function ($q) use ($storageFeeOrderIds) {
                    $q->whereIn('id', $storageFeeOrderIds);
                },
                'feeOrders.storageDetails',
                'lines',
                'lines.orderAssociates',
            ])->get();
        if ($storageFeeOrderIds) {
            //获取费用单详情
            $storageFeeDetails = app(StorageFeeRepository::class)->getDetailsByFeeOrder($storageFeeOrderIds, true);
        }
        // 构建销售单选择的保障服务数据[sales_order_id=>[config_id,...],...]
        $safeguardFeeOrderConfigList = [];
        if(!empty($safeguardFeeOrderIds)){
            $safeguardFeeOrders = FeeOrder::with('safeguardDetails')->whereIn('id', $safeguardFeeOrderIds)->get();
            foreach ($safeguardFeeOrders as $safeguardFeeOrder) {
                foreach ($safeguardFeeOrder->safeguardDetails as $safeguardDetail) {
                    $safeguardFeeOrderConfigList[$safeguardFeeOrder->order_id][] = $safeguardDetail->safeguard_config_id;
                }
            }
        }
        //拼装费用单和订单信息
        foreach ($saleOrderList as $key => $saleOrder) {
            if (empty($list[$saleOrder->id])) {
                $list[$saleOrder->id] = [
                    //订单号
                    'id' => $saleOrder->id,
                    'sales_id' => $saleOrder->id,
                    'sales_order_id' => $saleOrder->order_id,
                    'sales_import_mode' => $saleOrder->import_mode,
                    'ship_info' => "{$saleOrder->ship_name}({$saleOrder->ship_phone}), {$saleOrder->ship_address1}{$saleOrder->ship_address2}, {$saleOrder->ship_city}, {$saleOrder->ship_state}, {$saleOrder->ship_zip_code}, {$saleOrder->ship_country}",
                    'safeguard_config_ids' => $safeguardFeeOrderConfigList[$saleOrder->id] ?? []
                ];
            }
            $products = Product::query()->with(['tags'])->whereIn('product_id', $saleOrder->lines->pluck('product_id'))->get(['image','sku'])->keyBy('sku')->toArray();
            $feeDetails = [];
            if (!$saleOrder->feeOrders->isEmpty()) {
                $feeDetails = $storageFeeDetails[$saleOrder->feeOrders[0]->id] ?? [];
            }
            //组装仓租明细
            foreach ($saleOrder->lines as $line) {
                $itemCode = $line['item_code'];
                $list[$saleOrder->id]['lines'][$itemCode] = [
                    'product_id' => $line['product_id'] ?? '',
                    'item_code' => $itemCode,
                    'image' => $modelToolImage->resize($products[$itemCode]['image'] ?? '', 40, 40),
                    'tags' => $products[$itemCode]['product_tags'] ?? [],
                    'qty' => $line['qty'],
                    //是否有仓租
                    'is_storage_fee' => false,
                    'storage_fee_infos' => [],
                    //仓租费
                    'line_total_storage_fee' => 0,
                ];
                foreach ($feeDetails as $feeDetail) {
                    if ($feeDetail['item_code'] == $itemCode) {
                        if ($feeDetail['need_pay'] > 0) {
                            $list[$saleOrder->id]['lines'][$itemCode]['is_storage_fee'] = true;
                        }
                        $list[$saleOrder->id]['lines'][$itemCode]['line_total_storage_fee'] += $feeDetail['need_pay'];
                        $list[$saleOrder->id]['lines'][$itemCode]['storage_fee_infos'][] = $feeDetail;
                    }
                }
                if (empty($line['product_id'])) {
                    $list[$saleOrder->id]['lines'][$itemCode]['product_id'] = $line->orderAssociates[0]->product_id ?? 0;
                }
                if (empty($line['product_id'])) {
                    $list[$saleOrder->id]['lines'][$itemCode]['product_id'] = $feeDetails[0]['product_id'] ?? 0;
                }
                // 计算产品的货值价格和物流费
                $purchaseOrderProductCostTotalLine = 0;
                $purchaseOrderProductFreightTotalLine = 0;
                $purchaseOrderProductServiceTotalLine = 0;
                foreach ($line->orderAssociates as $orderAssociate) {
                    $orderProduct = app(OrderRepository::class)->getOrderProductPrice($orderAssociate->order_product_id);
                    $purchaseOrderProductCostTotalLine += $orderProduct->price * $orderAssociate->qty;
                    $purchaseOrderProductFreightTotalLine += ($orderProduct->freight_per + $orderProduct->package_fee) * $orderAssociate->qty;
                    $purchaseOrderProductServiceTotalLine += $orderProduct->service_fee_per * $orderAssociate->qty;
                }
                $list[$saleOrder->id]['lines'][$itemCode]['costAmount'] = $purchaseOrderProductCostTotalLine;
                $list[$saleOrder->id]['lines'][$itemCode]['freightAmount'] = $purchaseOrderProductFreightTotalLine;
                $list[$saleOrder->id]['lines'][$itemCode]['serviceAmount'] = $purchaseOrderProductServiceTotalLine;
                $list[$saleOrder->id]['lines'][$itemCode]['costAndFreightAmount'] = $purchaseOrderProductCostTotalLine + $purchaseOrderProductFreightTotalLine + $purchaseOrderProductServiceTotalLine;
            }
        }
        //计算总计
        foreach ($list as &$item) {
            $lineTotalFee = 0;
            $lineCostAndFreightAmount = 0;
            $lineQty = 0;
            $lineQtyMax = 0;
            foreach ($item['lines'] as $line) {
                $lineTotalFee += $line['line_total_storage_fee'];
                $lineCostAndFreightAmount += $line['costAndFreightAmount'];
                $lineQty += $line['qty'];
                $lineQtyMax = max($lineQtyMax,$line['qty']);
            }
            $totalFee += $lineTotalFee;
            $item['line_total_fee'] = $lineTotalFee;
            $item['sale_order_qty'] = $lineQty;
            $item['sale_order_qty_max'] = $lineQtyMax;
            $item['costAndFreightAmount'] = $lineCostAndFreightAmount;
        }
        return [
            //是否需要支付
            'need_pay_order' => !empty($list),
            //总费用
            'total_fee' => $totalFee,
            'order_data' => $list,
        ];
    }

    /**
     * @param $purchaseRunId
     * @param int $customerId
     * @param int|null $feeType 费用单类型
     * @return FeeOrder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getFeeOrderByRunId($purchaseRunId, $customerId, ?int $feeType = null)
    {
        return FeeOrder::where('purchase_run_id', $purchaseRunId)
            ->where('buyer_id', $customerId)
            ->when($feeType, function ($query) use ($feeType) {
                $query->where('fee_type', $feeType);
            })
            ->get();
    }

    /**
     * 根据保单id查询对应的费用单
     *
     * @param int|null $safeguardBillId
     * @return FeeOrder|Builder|null
     */
    public function getFeeOrderBySafeguardBillId(?int $safeguardBillId)
    {
        if (!$safeguardBillId) {
            return null;
        }
        return FeeOrder::where('order_type', FeeOrderOrderType::SALES)
            ->where('fee_type', FeeOrderFeeType::SAFEGUARD)
            ->whereHas('safeguardDetails', function ($query) use ($safeguardBillId) {
                $query->where('safeguard_bill_id', $safeguardBillId);
            })
            ->first();
    }

    /**
     * 根据run id获取可以支付的费用单
     * 只要有一笔费用单不能支付就返回null
     *
     * @param $purchaseRunId
     * @param int $customerId
     * @param int|null $feeType
     * @return array|null
     */
    public function getCanPayFeeOrderByRunId($purchaseRunId, $customerId, ?int $feeType = null)
    {
        $feeOrderList = $this->getFeeOrderByRunId($purchaseRunId, $customerId, $feeType);
        if ($feeOrderList->isNotEmpty()) {
            $feeOrderList->load(['storageDetails', 'storageDetails.storageFee']);
            if($feeType === FeeOrderFeeType::STORAGE){
                $needPay = true;
                foreach ($feeOrderList as $feeOrder) {
                    //判断是否需要支付
                    if (!($this->isFeeOrderNeedPay($feeOrder))) {
                        $needPay = false;
                        break;
                    }
                }
                if ($needPay) {
                    //如果没有不需要支付的，直接返回订单id
                    return $feeOrderList->pluck('id', 'order_id')->toArray();
                }
            } elseif ($feeType === FeeOrderFeeType::SAFEGUARD) {
                $feeOrderIds = [];
                foreach ($feeOrderList as $feeOrder) {
                    //判断是否需要支付
                    if ($this->isFeeOrderNeedPay($feeOrder)) {
                        $feeOrderIds[$feeOrder->order_id] = $feeOrder->id;
                    }
                }
                return $feeOrderIds;
            }
        }
        //只要有一笔不需要支付，返回空的
        return null;
    }

    /**
     * 过滤出一组需要支付的订单
     *
     * @param $feeOrders
     * @return Collection
     */
    public function filterNeedPayFeeOrder($feeOrders)
    {
        $return = collect();
        foreach ($feeOrders as $feeOrder) {
            if (($this->isFeeOrderNeedPay($feeOrder))) {
                $return->push($feeOrder);
            }
        }
        return $return;
    }

    /**
     * 判断一批费用单是否能支付
     *
     * @param $feeOrderIds
     * @return bool
     */
    public function checkFeeOrderNeedPay($feeOrderIds)
    {
        $feeOrderList = FeeOrder::with(['storageDetails', 'storageDetails.storageFee'])
            ->whereIn('id', $feeOrderIds)->get();
        foreach ($feeOrderList as $feeOrder) {
            if (!($this->isFeeOrderNeedPay($feeOrder))) {
                return false;
            }
        }
        return true;
    }

    /**
     * 费用单是否需要支付
     *
     * @param FeeOrder $feeOrder
     * @return bool
     */
    public function isFeeOrderNeedPay(FeeOrder $feeOrder)
    {
        //是待支付
        //支付金额大于0
        //在超时时间内
        //一键代发订单需要判断销售单未支付
        return $this->getFeeOrderNeedPayStatus($feeOrder) === 1;
    }

    /**
     * 获取费用单是否可支付的具体状态
     *
     * @param FeeOrder $feeOrder
     * @return int 1-可支付 不等于1都是不可支付
     *          2-状态变更无需支付
     *          3-支付金额为0
     *          4-超时
     *          5-销售单不存在
     *          6-销售单状态错误
     *          7-费用单已支付
     *          8-销售订单SKU发生变化
     *          9-费用单支付的仓租已经支付
     *          10-保障服务费用单关联的销售订单已经有生效的保单了
     */
    public function getFeeOrderNeedPayStatus(FeeOrder $feeOrder)
    {
        if ($feeOrder->status == FeeOrderStatus::COMPLETE) {
            //费用单已支付
            return 7;
        }
        if ($feeOrder->status != FeeOrderStatus::WAIT_PAY) {
            //状态变更无需支付
            return 2;
        }
        if($feeOrder->fee_total <= 0){
            //支付金额为0
            return 3;
        }
        if (Carbon::now()->gt($feeOrder->created_at->addMinute(config('expire_time', 30)))) {
            //超时
            return 4;
        }
        if ($feeOrder->order_type == FeeOrderOrderType::SALES) {
            if (empty($feeOrder->orderInfo)){
                //销售单不存在
                return 5;
            }
            if ($feeOrder->purchase_run_id) {
                //一键代发
                if ($feeOrder->orderInfo->order_status != CustomerSalesOrderStatus::TO_BE_PAID) {
                    //销售单状态错误
                    return 6;
                }
            } else {
                //上门取货,必须要是销售单的状态是费用待支付
                if ($feeOrder->orderInfo->order_status != CustomerSalesOrderStatus::PENDING_CHARGES) {
                    //销售单状态错误
                    return 6;
                }
            }
            if($feeOrder->fee_type === FeeOrderFeeType::STORAGE){
                $feeOrderSku = [];// 校验SKU是否发生变化
                foreach ($feeOrder->storageDetails as $storageDetail) {
                    $feeOrderSku[] = $storageDetail->storageFee->product_sku;
                    // 校验仓租是否已支付
                    if ($storageDetail->storageFee->status === StorageFeeStatus::COMPLETED) {
                        return 9;
                    }
                }
                if (!empty($feeOrderSku)) {
                    $feeOrderSku = array_filter(array_unique($feeOrderSku));
                    foreach ($feeOrderSku as $sku) {
                        $modifySku = CustomerOrderModifyLog::where('header_id', $feeOrder->order_id)
                            ->where('process_code', 2)
                            ->whereIn('status', [1, 2])//操作中和操作成功的都算
                            ->where('before_record', 'like', "%ItemCode:{$sku}%")
                            ->exists();
                        if ($modifySku) {
                            return 8;
                        }
                    }
                }
            } elseif ($feeOrder->fee_type === FeeOrderFeeType::SAFEGUARD) {
                // 保障服务校验销售单是否已经购买了保障服务
                $safeguardBill = app(SafeguardBillRepository::class)->getSafeguardBillsBySaleId($feeOrder->order_id);
                if ($safeguardBill->isNotEmpty()) {
                    // 如果购买过直接退出
                    return 10;
                }
            }
        }
        return 1;
    }

    /**
     * 判断订单是否能取消
     *
     * @param FeeOrder $feeOrder
     * @return bool
     */
    public function isFeeOrderCanCancel(FeeOrder $feeOrder)
    {
        return $feeOrder->status == FeeOrderStatus::WAIT_PAY;
    }

    /**
     * @param array $storageId
     * @param array $where
     * @return array|BuildsQueries[]|Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getPaidFeeOrderByStorage(array $storageId, $where = [])
    {
        return FeeOrder::where('status', FeeOrderStatus::COMPLETE)
            ->whereHas('storageDetails', function ($query) use ($storageId) {
                $query->whereIn('storage_fee_id', $storageId);
            })
            ->when(!empty($where), function ($query) use ($where) {
                $query->where($where);
            })
            ->get();
    }

    /**
     * 根据销售单id获取可以取消的费用单数据
     *
     * @param $salesOrderId
     *
     * @return Collection
     */
    public function getCanCancelFeeOrderBySalesOrderId($salesOrderId)
    {
        $feeOrders = FeeOrder::where('order_type', FeeOrderOrderType::SALES)
            ->where('order_id', $salesOrderId)->get();
        $returnFeeOrders = collect();
        foreach ($feeOrders as $feeOrder) {
            if ($this->isFeeOrderCanCancel($feeOrder)) {
                $returnFeeOrders->push($feeOrder);
            }
        }
        return $returnFeeOrders;
    }

    /**
     * 获取费用单同一批提交的费用单
     *
     * @param FeeOrder $feeOrder
     * @param bool $isOneself 是否包含自己
     *
     * @return Builder[]|\Illuminate\Database\Eloquent\Collection|Collection
     */
    public function getFeeOrderRelates(FeeOrder $feeOrder, $isOneself = false)
    {
        if (!($feeOrder->purchase_run_id) && !($feeOrder->fee_order_run_id)) {
            return collect();
        }
        return FeeOrder::query()
            ->where('order_type', $feeOrder->order_type)
            ->where(function ($query) use ($feeOrder) {
                if($feeOrder->purchase_run_id){
                    $query->where('purchase_run_id', $feeOrder->purchase_run_id);
                }
                if($feeOrder->fee_order_run_id){
                    $query->orWhere('fee_order_run_id', $feeOrder->fee_order_run_id);
                }
            })
            ->where(function ($query) {
                $query->where(function ($query2) {
                    $query2->where('fee_type', FeeOrderFeeType::STORAGE)
                        ->where('fee_total', '>', 0);
                })->orWhere('fee_type', '<>', FeeOrderFeeType::STORAGE);
            })
            ->where('buyer_id', $feeOrder->buyer_id)
            ->where('status', FeeOrderStatus::WAIT_PAY)
            ->when(!$isOneself, function ($query) use ($feeOrder) {
                $query->where('id', '<>', $feeOrder->id);
            })
            ->get();
    }

    /**
     * @author xxl
     * @description 获取费用单手续费
     * @date 11:39 2020/11/6
     * @param array $feeOrderIdArr
     * @return int|mixed
     **/
    public function findFeeOrderPoundage($feeOrderIdArr)
    {
        return FeeOrder::query()
            ->whereIn('id', $feeOrderIdArr)
            ->sum('poundage');
    }

    /**
     * 获取费用单关联的其他费用单
     *
     * @param FeeOrder $feeOrder
     * @param string|null $feeType
     * @param int|null $status
     * @return FeeOrder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getRelatedFeeOrder(FeeOrder $feeOrder, ?string $feeType = null, ?int $status = null)
    {
        return FeeOrder::query()
            ->where('fee_order_run_id', $feeOrder->fee_order_run_id)
            ->where('order_id', $feeOrder->order_id)
            ->where('is_show', YesNoEnum::YES)
            ->filterWhere([
                ['fee_type', '=', $feeType],
                ['status', '=', $status],
            ])
            ->get();
    }

    /**
     * @param CustomerSalesOrder $salesOrder
     * @param Collection $safeguardConfigList
     * @param null $purchaseRunId
     * @return array [总费用,费用明细=>[config_id:子费用]] 如果总费用===false，则说明异常
     * @throws Exception
     */
    public function calculateSafeguardFeeOrderData(CustomerSalesOrder $salesOrder,Collection $safeguardConfigList, $purchaseRunId = null)
    {
        if ($safeguardConfigList->isEmpty()) {
            return [false];
        }
        $cacheKey = [__CLASS__, __FUNCTION__, $salesOrder->id, $safeguardConfigList->implode('id', ',')];
        $res = $this->getRequestCachedData($cacheKey);
        if ($res) {
            return $res;
        }
        $countryId = $salesOrder->buyer->country_id;
        $bcsConfig = [
            'scale' => 2,
        ];
        if ($countryId === Country::JAPAN) {
            // 日本要用进一法
            $bcsConfig = [
                'scale' => 0,
                'ceil' => true
            ];
        }
        $feeTotal = BCS::create(0, $bcsConfig);// 算所有服务对应这个销售单的费用
        $orderBaseAmount = BCS::create(0, ['scale' => 2]);
        if (in_array($salesOrder->order_status, [CustomerSalesOrderStatus::BEING_PROCESSED, CustomerSalesOrderStatus::PENDING_CHARGES])) {
            // 如果订单BP、PC，查出已绑定信息
            $orderAssociates = $salesOrder->orderAssociates()->with('orderProduct')->get();
        } else {
            // 如果还是new order 查出pre里的绑定信息以及需要采购的信息
            $orderAssociates = OrderAssociatedPre::where('sales_order_id', $salesOrder->id)
                ->where('run_id', $purchaseRunId)
                ->where('associate_type', 1)
                ->with('orderProduct')->get();
            // 获取需要采购的产品数据
            if ($purchaseRunId) {
                /** @var ModelAccountSalesOrderMatchInventoryWindow $matchModel */
                $matchModel = load()->model('account/sales_order/match_inventory_window');
                // 获取下单页数据
                $purchaseRecords = $matchModel->getPurchaseRecord($purchaseRunId, $salesOrder->buyer_id, true, $salesOrder->id);
                foreach ($purchaseRecords as $purchaseRecord) {
                    $lineData = [
                        'type_id' => $purchaseRecord['type_id'],
                        'agreement_id' => $purchaseRecord['agreement_id'],
                        'product_id' => $purchaseRecord['product_id'],
                        'customer_id' => $salesOrder->buyer_id,
                        'country_id' => $countryId,
                        'seller_id' => $purchaseRecord['seller_id'],
                        'quantity' => $matchModel->getPurchaseSumQtyByRunId($purchaseRunId, $purchaseRecord['product_id']),
                    ];
                    // 获取商品价格数据
                    $lineInfo = $matchModel->getLineTotal($lineData);
                    // 计算商品单价+物流费+打包费
                    $productMoney = BCS::create($lineInfo['price'], ['scale' => 2])
                        ->add($lineInfo['deposit_per'])->add($lineInfo['freight'])->add($lineInfo['package_fee'])->getResult();
                    // 增加商品总价
                    $orderBaseAmount->add($purchaseRecord['quantity'] * $productMoney);
                }
            }
        }
        // 计算投保基数
        $orderRepo = app(OrderRepository::class);
        foreach ($orderAssociates as $orderAssociate) {
            if (!($orderAssociate->orderProduct) || $orderAssociate->qty <= 0) {
                // 不存在或者数量异常跳过
                continue;
            }
            $qty = $orderAssociate->qty;
            $orderProduct = $orderRepo->getOrderProductPrice($orderAssociate->orderProduct);
            // 计算预绑定商品货值
            $productMoney = BCS::create($orderProduct->price, ['scale' => 2])
                ->add($orderProduct->service_fee_per)
                ->add($orderProduct->freight_per)
                ->add($orderProduct->package_fee)
                ->getResult();
            // 增加总价
            $orderBaseAmount->add($qty * $productMoney);
        }
        $safeguardServiceFeeList = [];
        $orderBaseAmountMoney = $orderBaseAmount->getResult();
        foreach ($safeguardConfigList as $safeguardConfig) {
            // 计算所有保障服务的总服务费用
            $safeguardServiceFeeList[$safeguardConfig->id]
                = $safeguardServiceFee
                = BCS::create($orderBaseAmountMoney, $bcsConfig)->mul($safeguardConfig->service_rate)->getResult();
            $feeTotal->add($safeguardServiceFee);
        }
        $res = [
            $feeTotal->getResult(),
            $safeguardServiceFeeList,
            $orderBaseAmountMoney
        ];
        $this->setRequestCachedData($cacheKey, $res);
        return $res;
    }
}
