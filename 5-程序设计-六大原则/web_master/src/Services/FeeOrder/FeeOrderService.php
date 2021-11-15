<?php

namespace App\Services\FeeOrder;

use App\Components\UniqueGenerator;
use App\Components\UniqueGenerator\Enums\ServiceEnum;
use App\Enums\Charge\ChargeType;
use App\Enums\Country\Country;
use App\Enums\FeeOrder\FeeOrderExceptionCode;
use App\Enums\FeeOrder\FeeOrderFeeType;
use App\Enums\FeeOrder\FeeOrderOrderType;
use App\Enums\FeeOrder\FeeOrderStatus;
use App\Enums\FeeOrder\StorageFeeStatus;
use App\Enums\Pay\PayCode;
use App\Enums\Pay\VirtualPayType;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Safeguard\SafeguardBillOrderType;
use App\Enums\YzcRmaOrder\RmaType;
use App\Models\Customer\Customer;
use App\Models\FeeOrder\FeeOrder;
use App\Models\FeeOrder\FeeOrderSafeguardDetail;
use App\Models\FeeOrder\FeeOrderStorageDetail;
use App\Models\Rma\YzcRmaOrder;
use App\Models\Safeguard\SafeguardConfig;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\StorageFee\StorageFee;
use App\Repositories\FeeOrder\FeeOrderRepository;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Safeguard\SafeguardBillRepository;
use App\Repositories\Safeguard\SafeguardClaimRepository;
use App\Repositories\Safeguard\SafeguardConfigRepository;
use App\Services\Customer\LineOfCreditService;
use App\Services\Order\OrderService;
use App\Services\Safeguard\SafeguardBillService;
use Carbon\Carbon;
use Closure;
use Exception;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class FeeOrderService
{
    /**
     * @var string $country
     */
    private $country;

    /**
     * @var int $customer_id
     */
    private $customer_id;

    public function __construct()
    {
        $this->country = session('country', 'USA');
        $this->customer_id = (int)session('customer_id', 0);
    }

    /**
     * 生成销售单费用单 支持连续多个生成
     * @param array $salesInfo 销售单信息
     * ps:不校验销售单id或者仓租id是否真的存在，不校验销售单id和销售单line id的关系
     *
     * exm: [
     *       salesOrderId => [
     *                   storageFeeId1 => salesOrderLineId1,
     *                   storageFeeId2 => salesOrderLineId2,
     *                   storageFeeId3 => salesOrderLineId1,
     *                   ...
     *              ],
     *      ....
     *    ]
     * @param string|null $feeOrderRunId 可选参数
     * @param string|null $runId 可选参数
     * @return array [salesOrderId => feeOrderId]
     * @throws Exception
     */
    public function createSalesFeeOrder(array $salesInfo,string $feeOrderRunId = null, string $runId = null): array
    {
        $ret = [];
        foreach ($salesInfo as $salesOrderId => $storageInfos) {
            $ret[$salesOrderId] = $this->createSingleStorageFeeOrder((int)$salesOrderId, $storageInfos, $feeOrderRunId, $runId);
        }
        return $ret;
    }

    /**
     * 创建rma费用单 只有包含退货退款的rma才会产生仓租 仓租费上线之前的rma不予处理 返回null;
     * 需要考虑2种情况：
     * 1.采购订单退款
     * 2.取消的销售订单退款
     * 下面对于这2种情况分别考虑
     * @param int $rmaId
     * @return int|null 费用单id
     * @throws Exception
     */
    public function createRmaFeeOrder(int $rmaId)
    {
        $yzcRmaOrder = YzcRmaOrder::query()->with(['yzcRmaOrderProduct'])->find($rmaId);
        if ($yzcRmaOrder->order_type == RmaType::SALES_ORDER) {
            return $this->createSalesOrderRmaFeeOrder($yzcRmaOrder);
        } else {
            return $this->createPurchaseOrderRmaFeeOrder($yzcRmaOrder);
        }
    }

    /**
     * 创建保障服务费用单
     *
     * @param array $salesOrderList [sales_order_id => [1,2,3]]
     * @param string $feeOrderRunId 费用单统一批次标识
     * @param null $runId 一件代发下单页run id
     * @return array [fee_order_list => 所有的费用单ID,按销售单对应费用单的形式,need_pay_fee_order_list=>需要支付的费用单ID]
     * @throws \Framework\Exception\Exception
     */
    public function createSafeguardFeeOrder(array $salesOrderList,string $feeOrderRunId, $runId = null): array
    {
        $feeOrderIds = [];
        $needPayFeeOrderIds = [];// 需要支付的费用单
        foreach ($salesOrderList as $salesOrderId => $safeguardConfigIds) {
            list($feeOrderId, $needPay) = $this->createSingleSafeguardFeeOrder($salesOrderId, $safeguardConfigIds, $feeOrderRunId, $runId);
            if ($feeOrderId === false) {
                continue;
            }
            $feeOrderIds[$salesOrderId] = $feeOrderId;
            if ($needPay) {
                $needPayFeeOrderIds[] = $feeOrderId;
            }
        }
        return ['fee_order_list' => $feeOrderIds, 'need_pay_fee_order_list' => $needPayFeeOrderIds];
    }

    /**
     * @param int $customerId
     * @param int $orderId 头款采购单ID
     * @param StorageFee[]|Collection $storageFees
     * @return FeeOrder|null 返回费用单ID
     * @throws Exception
     */
    public function createMarginFeeOrder(int $customerId, int $orderId, Collection $storageFees): ?FeeOrder
    {
        $feeTotal = $storageFees->sum('fee_unpaid');
        $feeOrder = $this->createFeeOrder(FeeOrderOrderType::ORDER, FeeOrderFeeType::STORAGE, $orderId, $customerId, $feeTotal);
        $this->insertStorageFeeDetails($feeOrder, $storageFees);
        return $feeOrder;
    }

    /**
     * 目前仅供一件代发导单使用
     * 获取或者创建保障服务费用单，优先使用run id获取当前可用的保障服务费用单
     * 如果没有才会调用createSafeguardFeeOrder()创建
     *
     * @param string $runId
     * @param int $customerId
     * @param array $salesOrderList 数据格式参考createSafeguardFeeOrder()
     * @param string $feeOrderRunId
     * @return array 只会返回需要支付的费用单ID
     * @throws \Framework\Exception\Exception
     */
    public function findOrCreateSafeguardFeeOrderIdsWithRunId(string $runId, int $customerId, array $salesOrderList, string $feeOrderRunId): array
    {
        $feeOrderList = app(FeeOrderRepository::class)->getCanPayFeeOrderByRunId($runId, $customerId, FeeOrderFeeType::SAFEGUARD);
        if (!empty($feeOrderList)) {
            // 修改fee_order_run_id
            FeeOrder::whereIn('id', $feeOrderList)
                ->update(['fee_order_run_id' => $feeOrderRunId]);
            return $feeOrderList;
        }
        if (empty($salesOrderList)) {
            return [];
        }
        // 先校验保障服务
        foreach ($salesOrderList as $salesOrderId => $safeguardIds) {
            $res = app(SafeguardConfigRepository::class)->checkCanBuySafeguardBuSalesOrder($salesOrderId, $safeguardIds);
            if (!$res['success']) {
                throw new Exception('Information shown on this screen has been updated.');
            }
        }
        // 如果没有就重新创建
        $feeOrderList = $this->createSafeguardFeeOrder($salesOrderList, $feeOrderRunId, $runId);
        return $feeOrderList['need_pay_fee_order_list'] ?? [];
    }

    /**
     * 改变费用单状态 不会校验旧的状态和新的状态值是否一致
     * @param int|FeeOrder $feeOrderId 费用单id或者费用单model实例
     * @param int $status 状态
     * @param Closure|null $callback 回调函数 传入参数为旧FeeOrder模型 和 新FeeOrder模型
     * @throws Exception
     */
    public function changeFeeOrderStatus($feeOrderId, int $status, Closure $callback = null)
    {
        if (!($feeOrderId instanceof FeeOrder)) {
            $feeOrder = FeeOrder::query()->find($feeOrderId);
        } else {
            $feeOrder = $feeOrderId;
        }
        $this->checkCanOperate($feeOrder, $status);
        $newFeeOrder = clone $feeOrder;
        $newFeeOrder->status = $status;
        switch ($status) {
            case FeeOrderStatus::COMPLETE:
            {
                $newFeeOrder->paid_at = Carbon::now();
                $newFeeOrder->save();
                switch ($newFeeOrder->fee_type) {
                    case FeeOrderFeeType::STORAGE :
                        app(StorageFeeService::class)->payByFeeOrder([$feeOrder->id]);
                        break;
                    case FeeOrderFeeType::SAFEGUARD :
                        // 保障服务费用单完成
                        // 创建保单
                        $safeguardBill = app(SafeguardBillRepository::class)->getSafeguardBillsBySaleId($feeOrder->order_id);
                        if ($safeguardBill->isEmpty()) {
                            // 没购买才生成
                            foreach ($feeOrder->safeguardDetails as $safeguardDetail) {
                                $safeguardBill = app(SafeguardBillService::class)->createSafeguardBill([
                                    'safeguard_config_id' => $safeguardDetail->safeguard_config_id,
                                    'order_type' => SafeguardBillOrderType::SALES,
                                    'buyer_id' => $feeOrder->buyer_id,
                                    'country_id' => optional($feeOrder->buyer)->country_id,
                                    'order_id' => $safeguardDetail->sales_order_id,
                                    'effective_time' => Carbon::now()->toDateTimeString(),
                                ]);
                                if ($safeguardBill) {
                                    $safeguardDetail->update(['safeguard_bill_id' => $safeguardBill->id]);
                                }
                            }
                        }
                        break;
                }
                break;
            }
            case FeeOrderStatus::EXPIRED:
            {
                $newFeeOrder->save();
                // 仓租费用单
                if ($newFeeOrder->fee_order_run_id) {
                    //取消同一次提交的其他费用单
                    FeeOrder::where('fee_order_run_id', $newFeeOrder->fee_order_run_id)
                        ->where('buyer_id', $newFeeOrder->buyer_id)
                        ->where('status', FeeOrderStatus::WAIT_PAY)
                        ->update([
                            'status' => FeeOrderStatus::EXPIRED
                        ]);
                }
                // 仓租费用单
                if ($newFeeOrder->purchase_run_id) {
                    //取消同一批提交的费用单
                    FeeOrder::where('order_type', $newFeeOrder->order_type)
                        ->where('purchase_run_id', $newFeeOrder->purchase_run_id)
                        ->where('buyer_id', $newFeeOrder->buyer_id)
                        ->where('status', FeeOrderStatus::WAIT_PAY)
                        ->update([
                            'status' => FeeOrderStatus::EXPIRED
                        ]);
                }
                //取消关联的采购单
                if ($feeOrder->order_type == FeeOrderOrderType::SALES) {
                    app(OrderService::class)->cancelOcOrderByFeeOrderId($feeOrder->id);
                }

                break;
            }
            default:
                $newFeeOrder->save();
                break;
        }
        if ($callback) {
            call_user_func_array($callback, [$newFeeOrder, $feeOrder]);
        }
    }

    /**
     * 费用单退款
     * 费用单必须是已支付状态
     * 仓租费用单取消会回退仓租费用
     * 保障服务费用单退款后会取消保单
     *
     * @param $feeOrderId
     * @return bool
     * @throws Exception
     */
    public function refundFeeOrder($feeOrderId)
    {
        if (!($feeOrderId instanceof FeeOrder)) {
            $feeOrder = FeeOrder::query()->find($feeOrderId);
        } else {
            $feeOrder = $feeOrderId;
        }
        if ($feeOrder->status !== FeeOrderStatus::COMPLETE || !($feeOrder->paid_at)) {
            // 费用单未支付不能退款
            return false;
        }
        // 判断保单是否理赔成功
        if($feeOrder->fee_type == FeeOrderFeeType::SAFEGUARD){
            $safeguardBillIds = $feeOrder->safeguardDetails->pluck('safeguard_bill_id')->toArray();
            if (app(SafeguardClaimRepository::class)->checkBillIsClaimSuccessful($safeguardBillIds)) {
                //理赔成功的不让退款
                return false;
            }
        }
        //记录退款数据退款
        $updateData['status'] = FeeOrderStatus::REFUND;
        $updateData['refund_amount'] = $feeOrder->actual_paid;
        $updateData['refunded_at'] = Carbon::now();
        if ($feeOrder->payment_code === PayCode::PAY_VIRTUAL) {
            // 虚拟支付退回虚拟支付
            $updateData['refund_code'] = $feeOrder->payment_code;
            if ($feeOrder->fee_total > 0) {
                /** @var \ModelAccountBalanceVirtualPayRecord $modelAccountBalanceVirtualPayRecord */
                $modelAccountBalanceVirtualPayRecord = load()->model('account/balance/virtual_pay_record');
                $type = $feeOrder->fee_type == FeeOrderFeeType::SAFEGUARD ? VirtualPayType::SAFEGUARD_REFUND : VirtualPayType::STORAGE_FEE_REFUND;
                $modelAccountBalanceVirtualPayRecord->insertData($feeOrder->buyer_id, $feeOrder->id, $updateData['refund_amount'], $type);
            }
        } else {
            // 其他退回信用额度
            $updateData['refund_code'] = PayCode::PAY_LINE_OF_CREDIT;
            if ($feeOrder->fee_total > 0) {
                $memo = '';
                $type = $feeOrder->fee_type == FeeOrderFeeType::SAFEGUARD ? ChargeType::REFUND_SAFEGUARD : ChargeType::REFUND_STORAGE_FEE;
                app(LineOfCreditService::class)->addLineOfCredit($feeOrder->buyer_id, $feeOrder->fee_total, $type, $feeOrder->id, $memo);
            }
        }
        $res = $feeOrder->update($updateData);
        if ($res) {
            if ($feeOrder->fee_type == FeeOrderFeeType::STORAGE) {
                // 处理仓租状态变更
                foreach ($feeOrder->storageDetails as $storageDetail) {
                    // 回退对应的钱与数据
                    StorageFee::query()
                        ->where('id', $storageDetail->storage_fee_id)
                        ->update([
                            'fee_paid' => new Expression("fee_paid-{$storageDetail->storage_fee}"),
                            'fee_unpaid' => new Expression("fee_unpaid+{$storageDetail->storage_fee}")
                        ]);
                }

            } elseif ($feeOrder->fee_type == FeeOrderFeeType::SAFEGUARD) {
                // 处理保单过期
                foreach ($feeOrder->safeguardDetails as $safeguardDetail) {
                    if ($safeguardDetail->safeguard_bill_id) {
                        app(SafeguardBillService::class)->cancelSafeguardBill($safeguardDetail->safeguard_bill_id);
                    }
                }
            }
        }
        return true;
    }

    /**
     * 校验费用单是否需要变更状态
     * @param FeeOrder $feeOrder
     * @param int $status
     * @return bool
     * @throws \Framework\Exception\Exception
     * @see FeeOrderStatus::getViewItems()
     */
    private function checkCanOperate(FeeOrder $feeOrder, int $status): bool
    {
        if (!in_array($status, FeeOrderStatus::getValues())) {
            throw new \Framework\Exception\Exception('Error status code.');
        }
        // fee order的初始状态必定为0
        if ($feeOrder->status == FeeOrderStatus::COMPLETE) {
            throw new \Framework\Exception\Exception(
                "Charges order {$feeOrder->order_no} has already been paid.",
                FeeOrderExceptionCode::ALREADY_PAID
            );
        }
        if ($feeOrder->status == FeeOrderStatus::EXPIRED) {
            throw new \Framework\Exception\Exception(
                "Charges order {$feeOrder->order_no} has already been canceled.",
                FeeOrderExceptionCode::ALREADY_CANCELED
            );
        }

        return true;
    }

    /**
     * 创建单个费用单
     *
     * @param int $orderType 参考FeeOrderOrderType:class
     * @param int $feeType 参考FeeOrderFeeType:class
     * @param int $orderId 订单ID  销售单Id或者RMA id
     * @param int $buyerId
     * @param float $feeTotal 费用单总金额
     * @param string|null $feeOrderRunId
     * @param string|null $runId
     * @return FeeOrder
     * @throws Exception
     */
    private function createFeeOrder(int $orderType, int $feeType, int $orderId, int $buyerId, float $feeTotal, string $feeOrderRunId = null, string $runId = null): FeeOrder
    {
        // 创建费用单头表
        $feeOrder = new FeeOrder();
        $countryId = Customer::where('customer_id', $buyerId)->value('country_id');
        $countryId = intval($countryId ?: Country::AMERICAN);
        $feeOrder->order_no = UniqueGenerator::date()
            ->service(ServiceEnum::FEE_ORDER)
            ->country($countryId)
            ->prefix(FeeOrderFeeType::getOrderNoPrefix($feeType))
            ->random();
        $feeOrder->order_type = $orderType;
        $feeOrder->order_type_alias = Arr::get(FeeOrderOrderType::getViewItemsAlias(), $orderType);
        $feeOrder->order_id = $orderId;
        $feeOrder->buyer_id = $buyerId;
        $feeOrder->poundage = 0; // 初始手续费为0 后续代码修改
        if ($feeOrderRunId) {
            $feeOrder->fee_order_run_id = $feeOrderRunId;
        }
        if ($runId) {
            $feeOrder->purchase_run_id = $runId;
        }
        $feeOrder->fee_type = $feeType;
        $feeOrder->status = FeeOrderStatus::WAIT_PAY;
        // 费用单费用计算
        $feeOrder->fee_total = $feeTotal;
        $feeOrder->save();
        return $feeOrder;
    }

    /**
     * 生成单个销售单的仓租费用单
     * @param int $salesOrderId 销售单id
     * @param array $storageInfos
     * @param string|null $feeOrderRunId
     * @param string|null $runId
     * @return int 费用单id
     * @throws Exception
     */
    private function createSingleStorageFeeOrder(int $salesOrderId, array $storageInfos, string $feeOrderRunId = null, string $runId = null): int
    {
        // 获取所有存储信息
        $salesOrder = CustomerSalesOrder::find($salesOrderId);
        $storageFeeInfos = StorageFee::query()->whereIn('id', array_keys($storageInfos))->get();
        $feeTotal = app(StorageFeeRepository::class)->getNeedPayByStorageFeeIds($storageFeeInfos->pluck('id')->toArray());
        $feeOrder = $this->createFeeOrder(FeeOrderOrderType::SALES, FeeOrderFeeType::STORAGE,
            $salesOrderId, $salesOrder->buyer_id, $feeTotal, $feeOrderRunId, $runId);
        // 批量插入feeOrderStorageDetail表
        $this->insertStorageFeeDetails($feeOrder, $storageFeeInfos, $storageInfos);
        return $feeOrder->id;
    }

    /**
     * 生成单个保障服务费用单
     *
     * @param int $salesOrderId
     * @param array $safeguardConfigIds
     * @param string $feeOrderRunId
     * @param string|null $purchaseRunId
     * @return array [费用单ID,是否需要支付]
     * @throws \Framework\Exception\Exception
     */
    private function createSingleSafeguardFeeOrder(int $salesOrderId, array $safeguardConfigIds, string $feeOrderRunId, string $purchaseRunId = null)
    {
        $salesOrder = CustomerSalesOrder::query()->find($salesOrderId);
        if (!$salesOrder->buyer) {
            return [false];
        }
        $safeguardConfigList = SafeguardConfig::query()->whereIn('id', $safeguardConfigIds)->get();
        list($feeTotal, $safeguardServiceFeeList, $orderBaseAmount) = app(FeeOrderRepository::class)->calculateSafeguardFeeOrderData($salesOrder, $safeguardConfigList, $purchaseRunId);
        if ($feeTotal === false) {
            return [false];
        }
        if ($purchaseRunId) {
            //过期销售单下其他同一个run id提交的的保障服务费用单
            /** @var FeeOrder[] $unpaidFeeOrders */
            $unpaidFeeOrders = FeeOrder::where('fee_type', FeeOrderFeeType::SAFEGUARD)
                ->where('purchase_run_id', $purchaseRunId)
                ->where('order_type', FeeOrderOrderType::SALES)
                ->where('order_id', $salesOrder->id)
                ->where('status', FeeOrderStatus::WAIT_PAY)
                ->get();
            foreach ($unpaidFeeOrders as $unpaidFeeOrder) {
                $this->changeFeeOrderStatus($unpaidFeeOrder, FeeOrderStatus::EXPIRED);
            }
        }
        $feeOrder = $this->createFeeOrder(FeeOrderOrderType::SALES, FeeOrderFeeType::SAFEGUARD,
            $salesOrderId, $salesOrder->buyer_id, $feeTotal, $feeOrderRunId, $purchaseRunId);
        // 插入费用单明细
        $insertDetails = [];
        $safeguardConfigList->each(function (SafeguardConfig $safeguardConfig) use ($feeOrder, $salesOrderId, $safeguardServiceFeeList, $orderBaseAmount, &$insertDetails) {
            $insertDetails[] = [
                'fee_order_id' => $feeOrder->id,
                'safeguard_config_id' => $safeguardConfig->id,
                'safeguard_bill_id' => 0,// 初始生成是没有的
                'sales_order_id' => $salesOrderId,
                'safeguard_fee' => $safeguardServiceFeeList[$safeguardConfig->id],// 单个费用
                'order_base_amount' => $orderBaseAmount,
                'create_time' => Carbon::now(),
                'update_time' => Carbon::now(),
            ];
        });
        FeeOrderSafeguardDetail::query()->insert($insertDetails);
        // 0元的直接完成费用单
        $needPay = true;
        if ($feeOrder->fee_total <= 0) {
            $needPay = false;
            // 无需支付，记录成余额支付
            $feeOrderData = [
                'payment_method' => PayCode::getDescriptionWithPoundage(PayCode::PAY_LINE_OF_CREDIT),
                'payment_code' => PayCode::PAY_LINE_OF_CREDIT,
                'balance' => 0
            ];
            $feeOrder->update($feeOrderData);
            $this->changeFeeOrderStatus($feeOrder, FeeOrderStatus::COMPLETE);
        }
        return [$feeOrder->id, $needPay];
    }

    /**
     * 生成采购订单rma的费用单
     * @param YzcRmaOrder $yzcRmaOrder
     * @return null|int 费用单id
     * @throws Exception
     */
    private function createPurchaseOrderRmaFeeOrder(YzcRmaOrder $yzcRmaOrder)
    {
        $yzcProduct = $yzcRmaOrder->yzcRmaOrderProduct;
        // 获取退款的数量
        $rQty = $yzcProduct->quantity;
        // 获取所有可用的仓租信息表
        $storageFeeRepo = app(StorageFeeRepository::class);
        $canRmaStorageFeeIds = Arr::get(
            $storageFeeRepo->getCanRMAStorageFeeIdsByOrder(
                $yzcRmaOrder->order_id,
                [$yzcProduct->order_product_id => $rQty]
            ),
            $yzcProduct->order_product_id
        );
        $storageFeeList = StorageFee::query()->whereIn('id', $canRmaStorageFeeIds)->get();
        // 要退款的数量 > 可用的仓租数量 返回null
        if ($rQty > $storageFeeList->count()) {
            return null;
        }
        // 计算费用单价格
        $feeTotal = $storageFeeRepo->getNeedPayByStorageFeeIds($canRmaStorageFeeIds);
        // 生成费用单主表
        $feeOrder = $this->createFeeOrder(FeeOrderOrderType::RMA, FeeOrderFeeType::STORAGE,
            $yzcRmaOrder->id, $yzcRmaOrder->buyer_id, $feeTotal);
        // 批量插入feeOrderStorageDetail表
        $this->insertStorageFeeDetails($feeOrder, $storageFeeList);
        return $feeOrder->id;
    }

    /**
     * 生成销售订单rma的费用单
     * @param YzcRmaOrder $yzcRmaOrder
     * @return int|null 费用id
     * @throws Exception
     */
    private function createSalesOrderRmaFeeOrder(YzcRmaOrder $yzcRmaOrder)
    {
        // 获取退款的数量
        $rQty = $yzcRmaOrder->associate_product->qty;
        // 获取所有可用的仓租信息表
        $storageFeeRepo = app(StorageFeeRepository::class);
        $canRmaStorageFeeIds = $storageFeeRepo
            ->getBoundStorageFeeIdsByAssociated($yzcRmaOrder->associate_product->id);
        $storageFeeList = StorageFee::query()->whereIn('id', $canRmaStorageFeeIds)->get();
        // 要退款的数量 > 可用的仓租数量
        if ($rQty > $storageFeeList->count()) {
            return null;
        }
        // 费用单费用计算
        $feeTotal = $storageFeeRepo->getNeedPayByStorageFeeIds($canRmaStorageFeeIds);
        // 生成费用单主表
        $feeOrder = $this->createFeeOrder(FeeOrderOrderType::RMA, FeeOrderFeeType::STORAGE,
            $yzcRmaOrder->id, $yzcRmaOrder->buyer_id, $feeTotal);
        // 批量插入feeOrderStorageDetail表
        $this->insertStorageFeeDetails($feeOrder, $storageFeeList);
        return $feeOrder->id;
    }

    /**
     * 批量插入feeOrderStorageDetail表
     *
     * @param FeeOrder $feeOrder
     * @param Collection $storageFeeList
     * @param array $storageInfos [{storageFeeId:sales_order_line_id}]
     * @return bool
     */
    private function insertStorageFeeDetails(FeeOrder $feeOrder, Collection $storageFeeList, array $storageInfos = [])
    {
        $insertArrays = [];
        $storageFeeList->each(function (StorageFee $item) use ($feeOrder, $storageInfos, &$insertArrays) {
            $temp = [];
            $temp['fee_order_id'] = $feeOrder->id;
            $temp['storage_fee_id'] = $item->id;
            $temp['days'] = $item->days;
            if (in_array($feeOrder->order_type, [FeeOrderOrderType::SALES, FeeOrderOrderType::ORDER])) {
                $temp['storage_fee'] = bccomp($item->fee_unpaid, 0) === -1 ? 0 : $item->fee_unpaid;
                if (!empty($storageInfos)) {
                    $temp['sales_order_line_id'] = Arr::get($storageInfos, $item->id);
                }
            } elseif ($feeOrder->order_type === FeeOrderOrderType::RMA) {
                $temp['storage_fee'] = $item->fee_unpaid;
            }
            $temp['storage_fee_paid'] = $item->fee_paid;
            $temp['created_at'] = Carbon::now();
            $temp['updated_at'] = Carbon::now();
            array_push($insertArrays, $temp);
        });
        return FeeOrderStorageDetail::query()->insert($insertArrays);
    }


    /**
     * 生成一个新的order no
     * @param string $prefix
     * @return string
     */
    private function generateOrderNo(string $prefix = '')
    {
        return UniqueGenerator::date()
            ->service(ServiceEnum::FEE_ORDER)
            ->country(81)
            ->prefix($prefix)
            ->random();
    }

    /**
     * @Author xxl
     * @Description 修改费用单信息
     * @Date 16:22 2020/10/8
     * @param array $feeOrderIdData 修改的费用单数据
     */
    public function updateFeeOrderInfo($feeOrderIdData)
    {
        FeeOrder::query()
            ->where('id', $feeOrderIdData['id'])
            ->update($feeOrderIdData);
    }

    /**
     * 批量修改费用单信息
     *
     * @param $feeOrderId
     * @param $data
     * @return bool|int
     */
    public function batchUpdateFeeOrderInfo($feeOrderId,$data)
    {
        return FeeOrder::query()
            ->whereIn('id', $feeOrderId)
            ->update($data);
    }

    /**
     * 根据销售单取消费用单
     * @param $salesOrderId
     * @throws \Framework\Exception\Exception
     */
    public function cancelFeeOrderBySalesOrderId($salesOrderId)
    {
        $feeOrders = app(FeeOrderRepository::class)->getCanCancelFeeOrderBySalesOrderId($salesOrderId);
        foreach ($feeOrders as $feeOrder) {
            $this->changeFeeOrderStatus($feeOrder, FeeOrderStatus::EXPIRED);
        }
    }

    /**
     * 根据采购订单号取消费用单
     *
     * @param int $ocOrderId
     * @throws \Framework\Exception\Exception
     */
    public function cancelFeeOrderByOcOrderId(int $ocOrderId)
    {
        $feeOrders = app(OrderRepository::class)->getFeeOrderByOrderId($ocOrderId);
        $feeOrderRepo = app(FeeOrderRepository::class);
        foreach ($feeOrders as $feeOrder) {
            if ($feeOrderRepo->isFeeOrderCanCancel($feeOrder)) {
                $this->changeFeeOrderStatus($feeOrder, FeeOrderStatus::EXPIRED);
            }
        }
    }

    /**
     * 取消保障服务费用单
     *
     * @param int $salesOrderId
     * @return bool
     * @throws Exception
     */
    public function cancelSafeguardFeeOrderBySalesOrderId(int $salesOrderId)
    {
        // 获取销售订单绑定的所有费用单
        /** @var FeeOrder[] $feeOrders */
        $feeOrders = FeeOrder::where('fee_type', FeeOrderFeeType::SAFEGUARD)
            ->where('order_type', FeeOrderOrderType::SALES)
            ->where('order_id', $salesOrderId)
            ->get();
        // 循环
        foreach ($feeOrders as $feeOrder) {
            switch ($feeOrder->status) {
                case FeeOrderStatus::WAIT_PAY:
                    // 未支付的改成取消
                    $this->changeFeeOrderStatus($feeOrder, FeeOrderStatus::EXPIRED);
                    break;
                case FeeOrderStatus::COMPLETE:
                    // 已支付的走退款
                    $this->refundFeeOrder($feeOrder);
                    break;
            }
        }
        return true;
    }

    public function refundStorageFeeOrderBySalesOrderId(int $salesOrderId)
    {
        /** @var FeeOrder[] $feeOrders */
        $feeOrders = FeeOrder::where('fee_type', FeeOrderFeeType::STORAGE)
            ->where('order_type', FeeOrderOrderType::SALES)
            ->where('order_id', $salesOrderId)
            ->where('status', FeeOrderStatus::COMPLETE)
            ->get();
        foreach ($feeOrders as $feeOrder) {
            $this->refundFeeOrder($feeOrder);
        }
    }

    /**
     * 更新费用单的 is_show字段
     * 注意如果设置is_show=1,会同时将is_show=0的created_at设置成当前时间
     *
     * @param array|int $feeOrderId
     * @param int $isShow
     */
    public function updateFeeOrderIsShow($feeOrderId, $isShow)
    {
        if (is_array($feeOrderId)) {
            $feeOrder = FeeOrder::whereIn('id', $feeOrderId);
        } else {
            $feeOrder = FeeOrder::where('id', $feeOrderId);
        }
        $updateData = [
            'is_show' => $isShow
        ];
        if ($isShow) {
            //如果修改成1 ，则要把is show 为0 的创建时间改为当前时间
            (clone $feeOrder)->where('is_show', 0)->update(['created_at' => Carbon::now()]);
        }
        $feeOrder->update($updateData);
    }

    /**
     * 获取费用单run id，用于标识同一批费用单
     *
     * @return string
     * @throws Exception
     */
    public function generateFeeOrderRunId()
    {
        return msectime() . random_int(10000, 99999);
    }
}
