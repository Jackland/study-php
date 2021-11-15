<?php

namespace App\Repositories\FeeOrder;

use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\FeeOrder\FeeOrderFeeType;
use App\Enums\FeeOrder\StorageFeeStatus;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Link\OrderAssociated;
use App\Models\Link\OrderAssociatedPre;
use App\Models\Margin\MarginProcess;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Product\ProductSetInfo;
use App\Models\StorageFee\StorageFee;
use App\Models\StorageFee\StorageFeeDetail;
use App\Repositories\Margin\MarginRepository;
use App\Widgets\ImageToolTipWidget;
use Illuminate\Database\Query\Expression;
use kriss\bcmath\BC;
use kriss\bcmath\BCS;

class StorageFeeRepository
{
    use RequestCachedDataTrait;

    /**
     * 获取可以绑定的仓租数据,方法同时调用了getCanBindByAssociatedPre()、getCanBindByPurchaseData(),然后进行数据合并
     *
     * @param array $orderAssociatedPreIds 说明参考getCanBindByAssociatedPre
     * @param array $purchaseData 说明参考getCanBindByPurchaseData
     * @param false $needFeeDetails 是否需要仓租
     * @return array
     * @see getCanBindByAssociatedPre
     * @see getCanBindByPurchaseData
     */
    public function getAllCanBind(array $orderAssociatedPreIds, array $purchaseData = [], bool $needFeeDetails = false): array
    {
        $associatedStorages = $this->getCanBindByAssociatedPre($orderAssociatedPreIds, $needFeeDetails);
        $purchaseStorages = $this->getCanBindByPurchaseData($purchaseData, $needFeeDetails);
        if (empty($associatedStorages) && empty($purchaseStorages)) {
            // 都没有数据直接返回空数组
            return [];
        }
        // 如果只存在一个
        if (!empty($associatedStorages) && empty($purchaseStorages)) {
            return $associatedStorages;
        }
        if (empty($associatedStorages) && !empty($purchaseStorages)) {
            return $purchaseStorages;
        }
        // 重新组装
        foreach ($purchaseStorages as $salesOrderId => $purchaseStorage) {
            foreach ($purchaseStorage as $orderProductId => $item) {
                $associatedItem = $item;
                if (!empty($associatedStorages[$salesOrderId][$orderProductId])) {
                    // 相同就进行合并
                    $associatedItem = $associatedStorages[$salesOrderId][$orderProductId];
                    $associatedItem['qty'] += $item['qty'];
                    $associatedItem['need_pay'] += $item['need_pay'];
                    $associatedItem['paid'] += $item['paid'];
                    $associatedItem['storage_fee_ids'] = array_merge($associatedItem['storage_fee_ids'], $item['storage_fee_ids']);
                }
                $associatedStorages[$salesOrderId][$orderProductId] = $associatedItem;// 重新赋值
            }
        }
        return $associatedStorages;
    }

    /**
     * 根据预绑定的销售单与采购单的关系，获取仓租信息
     * @param array $orderAssociatedPreIds
     * @param bool $needFeeDetails 在不需要获取费用明细时设为 false
     * @return array [$salesOrderId => [$orderProductId => [一组数据], $orderProductId => [一组数据]]，其中一组数据可能为空数组
     *                注意这里面$orderProductId如果是现货，尾款都使用的是头款的order_product_id
     */
    public function getCanBindByAssociatedPre(array $orderAssociatedPreIds, bool $needFeeDetails = false): array
    {
        $key = [__CLASS__, __FUNCTION__, $orderAssociatedPreIds, $needFeeDetails];
        if ($data = $this->getRequestCachedData($key)) {
            return $data;
        }

        $associatedPres = OrderAssociatedPre::query()->with('orderProduct')
            ->whereIn('id', $orderAssociatedPreIds)->get();

        $usedStorageFeeIds = []; // 记录本次获取已经被取出的仓租id，防止重复取用
        $data = []; // 最终结果数组
        foreach ($associatedPres as $associatedPre) {
            if (!isset($data[$associatedPre->sales_order_id])) {
                $data[$associatedPre->sales_order_id] = [];
            }
            $query = StorageFee::query()
                ->with(['product', 'order'])
                ->where('order_id', $associatedPre->order_id)
                ->where('order_product_id', $associatedPre->order_product_id)
                ->whereIn('status', StorageFeeStatus::canBindStatus());
            // 现货尾款使用头款的order product id，为了整合数据
            $orderProductId = $associatedPre->order_product_id;
            if (isset($usedStorageFeeIds[$orderProductId])) {
                // 剔除本次已经被取用的仓租id
                $query->whereNotIn('id', $usedStorageFeeIds[$orderProductId]);
            }
            $models = $query->orderBy('id', 'asc')->limit($associatedPre->qty)->get();

            $item = [];
            if ($models->isNotEmpty() && $models->count() > 0) {
                $item = $this->formatCanBindStorageItem($models, $needFeeDetails);
                $ids = $item['storage_fee_ids'];
                if (empty($usedStorageFeeIds[$orderProductId])) {
                    $usedStorageFeeIds[$orderProductId] = $ids;
                } else {
                    $usedStorageFeeIds[$orderProductId] = array_merge($usedStorageFeeIds[$orderProductId], $ids);
                }
            }
            $data[$associatedPre->sales_order_id][$orderProductId] = $item;
        }

        $this->setRequestCachedData($key, $data);
        return $data;
    }

    /**
     * @param $purchaseData [{type_id:交易类型,agreement_id:协议id,quantity:数量,order_id:销售订单ID}]
     * @param false $needFeeDetails
     * @return array
     */
    public function getCanBindByPurchaseData($purchaseData, bool $needFeeDetails = false): array
    {
        $usedStorageFeeIds = []; // 记录本次获取已经被取出的仓租id，防止重复取用
        $data = []; // 最终结果数组
        foreach ($purchaseData as $purchaseDatum) {
            if ($purchaseDatum['quantity'] <= 0) {
                continue;
            }
            if ($purchaseDatum['type_id'] == ProductTransactionType::MARGIN) {
                // 获取头款order product
                $orderProduct = app(MarginRepository::class)->getAdvanceOrderProductByAgreementId($purchaseDatum['agreement_id']);
                if (!$orderProduct) {
                    continue;
                }
                $orderProductId = $orderProduct->order_product_id;
                $usedStorageFeeId = $usedStorageFeeIds[$orderProductId] ?? [];
                $storages = $this->getAgreementRestStorageFee($purchaseDatum['type_id'], $purchaseDatum['agreement_id'], $purchaseDatum['quantity'], $usedStorageFeeId);
                if($storages->isEmpty()){
                    continue;
                }
                $item = $this->formatCanBindStorageItem($storages, $needFeeDetails);
                $ids = $item['storage_fee_ids'];
                if (empty($usedStorageFeeIds[$orderProductId])) {
                    $usedStorageFeeIds[$orderProductId] = $ids;
                } else {
                    $usedStorageFeeIds[$orderProductId] = array_merge($usedStorageFeeIds[$orderProductId], $ids);
                }
                $data[$purchaseDatum['order_id']][$orderProductId] = $item;
            }
        }
        return $data;
    }

    /**
     * 格式化仓租数据
     *
     * @param $storages
     * @param false $needFeeDetails
     * @return array
     */
    private function formatCanBindStorageItem($storages, bool $needFeeDetails = false)
    {
        $needPay = 0;
        $paid = 0;
        $ids = [];
        foreach ($storages as $model) {
            $needPay += $model->fee_unpaid < 0 ? 0 : $model->fee_unpaid;
            $paid += $model->fee_paid;
            $ids[] = $model->id;
        }
        /** @var StorageFee $model */
        $model = $storages[0];
        $qty = $storages->count();

        /** @var Order $order */
        $order = $model->order;
        if ($model->transaction_type_id == ProductTransactionType::MARGIN) {
            // 如果是现货交易，采购采购订单应该为头款订单
            $order = app(MarginRepository::class)->getAdvanceOrderByAgreementId($model->agreement_id);
        }
        return [
            'item_code' => $model->product_sku,
            'product_id' => $model->product_id,
            'qty' => $qty,
            'is_combo' => (int)$model->product->combo_flag,
            'size_info' => json_decode($model->product_size_json, true),
            'volume' => $model->volume_m,
            'days' => $model->days,
            'purchase_paid_time' => $order ? $order->date_modified->toDateTimeString() : null,
            'purchase_order_id' => optional($order)->order_id,
            'need_pay' => $needPay,
            'paid' => $paid,
            'fee_detail_range' => $needFeeDetails ? $this->getFeeDetailRange($model, $qty) : [],
            'storage_fee_ids' => $ids,
            'is_in_stock' => $model->order_id != 0// 是否已经在buyer库存
        ];
    }

    /**
     * 获取复杂交易未付尾款的仓租数据
     *
     * @param int $transactionType 交易类型
     * @param string $agreementId 协议ID
     * @param int $quantity 数量
     * @param array $usedStorageFeeIds 需要忽略的仓租id
     * @return StorageFee[]|\Illuminate\Support\Collection
     */
    public function getAgreementRestStorageFee(int $transactionType, string $agreementId, int $quantity, array $usedStorageFeeIds = [])
    {
        return StorageFee::query()
            ->where('transaction_type_id', '=', $transactionType)
            ->where('agreement_id', '=', $agreementId)
            ->where('order_id', '=', 0)
            ->where('order_product_id', '=', 0)
            ->where('status', StorageFeeStatus::WAIT)
            ->when(!empty($usedStorageFeeIds), function ($query) use ($usedStorageFeeIds) {
                $query->whereNotIn('id', $usedStorageFeeIds);
            })
            ->limit($quantity)// 查出指定数量的
            ->get();
    }

    /**
     * 检查根据预绑定确定的仓租是否需要支付仓租
     *
     * @param array $orderAssociatedPreIds
     * @param array $purchaseData
     * @return float
     * @see getAllCanBind
     */
    public function getAllCanBindNeedPay(array $orderAssociatedPreIds, array $purchaseData = [])
    {
        $data = $this->getAllCanBind($orderAssociatedPreIds, $purchaseData, false);
        $bcs = BCS::create(0, ['scale' => 4]);
        foreach ($data as $salesOrderId => $infos) {
            foreach ($infos as $orderProductId => $item) {
                if ($item) {
                    $bcs->add($item['need_pay']);
                }
            }
        }
        return (float)$bcs->getResult();
    }

    /**
     * 获取可以 RMA 的仓租id
     * @param int $orderId 采购订单ID
     * @param array $orderProductInfo [$orderProductId => $qtn, ]
     * @return array [$orderProductId => [$storageFeeId, $storageFeeId], ]
     */
    public function getCanRMAStorageFeeIdsByOrder($orderId, array $orderProductInfo)
    {
        $ids = [];
        foreach ($orderProductInfo as $orderProductId => $qtn) {
            $ids[$orderProductId] = StorageFee::query()
                ->select('id')
                ->where('order_id', $orderId)
                ->where('order_product_id', $orderProductId)
                ->where('status', StorageFeeStatus::WAIT)
                ->orderBy('id', 'asc')
                ->limit($qtn)
                ->pluck('id')
                ->toArray();
        }

        return $ids;
    }

    /**
     * 根据绑定关系获取已绑定的仓租
     * @param int $associatedId 销售采购绑定表id
     * @return array 仓租id数组
     */
    public function getBoundStorageFeeIdsByAssociated($associatedId)
    {
        $associated = OrderAssociated::find($associatedId);
        return StorageFee::query()
            ->select('id')
            ->where('order_product_id', $associated->order_product_id)
            ->where('sales_order_line_id', $associated->sales_order_line_id)
            ->where('status', StorageFeeStatus::BIND)
            ->orderBy('id', 'asc')
            ->pluck('id')
            ->toArray();
    }

    /**
     * 根据绑定关系获取绑定的现货尾款仓租数据
     *
     * @param int|OrderAssociated $associated OrderAssociatedId|OrderAssociated对象
     * @return array
     */
    public function getBindMarginRestStorageFeeByAssociated($associated): array
    {
        if (!($associated instanceof OrderAssociated)) {
            $associated = OrderAssociated::find($associated);
        }
        return StorageFee::query()
            ->select('id')
            ->where('order_product_id', $associated->order_product_id)
            ->where('sales_order_line_id', $associated->sales_order_line_id)
            ->where('status', StorageFeeStatus::BIND)
            // 只有上线后的尾款仓租数据才又下面两个字段,之前的还是统一补交仓租费
            ->where('transaction_type_id', '=', ProductTransactionType::MARGIN)
            ->where('agreement_id', '=', $associated->orderProduct->agreement_id)
            ->pluck('id')
            ->toArray();
    }

    /**
     * 根据采购单信息获取绑定的现货尾款仓租数据
     *
     * @param int $orderProductId
     * @param int $agreementId
     * @return array
     */
    public function getOrderProductMarginRestStorageFeeByAssociated(int $orderProductId, int $agreementId): array
    {
        return StorageFee::query()
            ->select('id')
            ->where('order_product_id', $orderProductId)
            ->whereIn('status', [StorageFeeStatus::WAIT, StorageFeeStatus::BIND])
            // 只有上线后的尾款仓租数据才又下面两个字段,之前的还是统一补交仓租费
            ->where('transaction_type_id', '=', ProductTransactionType::MARGIN)
            ->where('agreement_id', '=', $agreementId)
            ->pluck('id')
            ->toArray();
    }

    /**
     * 根据销售单获取已经绑定的仓租
     * @param $salesOrderIds
     * @return array [sales_order_id=>[storageFeeId1 => salesOrderLineId1]]
     */
    public function getBoundStorageFeeBySalesOrder($salesOrderIds)
    {
        $ids = [];
        $storageFeeList = StorageFee::query()
            ->select(['id', 'sales_order_id', 'sales_order_line_id'])
            ->whereIn('sales_order_id', $salesOrderIds)
            ->where('status', StorageFeeStatus::BIND)
            ->orderBy('id', 'asc')
            ->get();
        foreach ($storageFeeList as $storageFee) {
            $ids[$storageFee->sales_order_id][$storageFee->id] = $storageFee->sales_order_line_id;
        }
        return $ids;
    }

    /**
     * 根据费用单获取费用单明细，用于列表展示
     * @param array $feeOrderIds
     * @param bool $needFeeDetails 在不需要获取费用明细时设为 false
     * @return array [$feeOrderId => [二维数组数据], $feeOrderId => [二维数组数据],]
     */
    public function getDetailsByFeeOrder(array $feeOrderIds, $needFeeDetails = false)
    {
        $feeOrders = FeeOrder::query()
            ->with([
                'storageDetails',
                'storageDetails.storageFee',
                'storageDetails.storageFee.order',
                'storageDetails.storageFee.product',
                'storageDetails.storageFee.product.tags',
                'storageDetails.storageFee.product.customerPartner.store',
            ])
            ->where('fee_type', FeeOrderFeeType::STORAGE)
            ->whereIn('id', $feeOrderIds)
            ->get();
        if ($feeOrders->isEmpty()) {
            return [];
        }
        $result = [];
        foreach ($feeOrders as $feeOrder) {
            $items = [];
            foreach ($feeOrder->storageDetails as $storageDetail) {
                $storageFee = $storageDetail->storageFee;
                $groupKey = $storageDetail->storageFee->order_product_id; // 按批次组合产品信息
                if ($storageFee->transaction_type_id == ProductTransactionType::MARGIN) {
                    // 如果是现货交易未付尾款的仓租，获取头款订单
                    $purchaseOrder = app(MarginRepository::class)->getAdvanceOrderByAgreementId($storageFee->agreement_id);
                    // 获取头款订单产品
                    $purchaseOrderProduct = app(MarginRepository::class)->getAdvanceOrderProductByAgreementId($storageFee->agreement_id);
                    if ($groupKey == 0) {
                        $groupKey = $purchaseOrderProduct->order_product_id;
                    }
                } else {
                    $purchaseOrder = $storageFee->order;
                }
                if (isset($items[$groupKey])) {
                    $items[$groupKey]['qty'] += 1;
                    $items[$groupKey]['need_pay'] = bcadd($items[$groupKey]['need_pay'], $storageDetail->storage_fee, 4);
                    $items[$groupKey]['paid'] = bcadd($items[$groupKey]['paid'], $storageDetail->storage_fee_paid, 4);
                    $items[$groupKey]['storage_fee_ids'][] = $storageDetail->storage_fee_id;
                    continue;
                }
                $product = $storageFee->product;
                $items[$groupKey] = [
                    'item_code' => $storageFee->product_sku,
                    'product_id' => $storageFee->product_id,
                    'order_product_id' => $groupKey,
                    'seller_id' => $product->customerPartner->customer_id,
                    'product_image' => $product->image,
                    'product_tags' => $product->tags->map(function ($tag) {
                        return ImageToolTipWidget::widget([
                            'tip' => $tag->description,
                            'image' => $tag->icon,
                        ])->render();
                    })->toArray(),
                    'seller_store_name' => $product->customerPartner->store->screenname,
                    'qty' => 1,
                    'is_combo' => (int)$product->combo_flag,
                    'size_info' => json_decode($storageFee->product_size_json, true),
                    'volume' => $storageFee->volume_m,
                    'days' => $storageDetail->days,
                    'purchase_paid_time' => $purchaseOrder ? $purchaseOrder->date_modified->format('Y-m-d H:i:s') : '',
                    'purchase_order_id' => $purchaseOrder ? $purchaseOrder->order_id : '',
                    'fee_order_type' => $feeOrder->order_type,
                    'fee_order_id' => $feeOrder->order_id,
                    'need_pay' => $storageDetail->storage_fee,
                    'paid' => $storageDetail->storage_fee_paid,
                    'fee_detail_range' => [], // 后续计算，因为需要 qty
                    '__storage_fee' => $storageFee, // 临时记录，返回前被 unset 掉
                    'storage_fee_ids' => [$storageDetail->storage_fee_id],
                    'is_in_stock' => $storageFee->order_id != 0,
                ];
            }
            $result[$feeOrder->id] = array_values($items);

            $result[$feeOrder->id] = array_map(function ($item) use ($needFeeDetails) {
                $storageFee = $item['__storage_fee'];
                unset($item['__storage_fee']);
                $item['fee_detail_range'] = $needFeeDetails ? $this->getFeeDetailRange($storageFee, $item['qty'], $item['days']) : [];
                return $item;
            }, $result[$feeOrder->id]);
        }

        return $result;
    }

    /**
     * 获取仓租明细区间
     * @param StorageFee $storageFee
     * @param int $qty
     * @param null|int $endDay 终止日期，为 null 表示到当前为止
     * @return array [['start' => 1, 'end' => 90, 'storage_fee' => 3.5, 'total' => 20], [xxx]]
     */
    public function getFeeDetailRange(StorageFee $storageFee, $qty = 1, $endDay = null)
    {
        /** @var StorageFeeDetail[] $details */
        $details = $storageFee->details()
            ->orderBy('day', 'asc') // 按照在库天数排序，因为可能存在前一天的数据重新计算的可能
            ->get();
        $range = [];
        $feeModeRepo = app(StorageFeeModeRepository::class);
        $lastFeeModeId = 0; // 上一个计费模式
        $lastFeeModeFee = -1; // 上一个计费费率，因为存在费率为0的情况，因此初始为-1
        $index = -1; // 区间索引
        foreach ($details as $detail) {
            if ($endDay && $detail->day > $endDay) {
                // 超过指定的日期后终止
                break;
            }
            if ($lastFeeModeId !== $detail->fee_mode_id) {
                // 计费模式切换
                $lastFeeModeId = $detail->fee_mode_id;
                $feeModeFee = $feeModeRepo->getFeeByMode($detail->feeMode);
                if ($lastFeeModeFee != $feeModeFee) {
                    // 费率确实有变动
                    // 切到下一个区间
                    $index++;

                    $lastFeeModeFee = $feeModeFee;
                    $range[$index] = [
                        'start' => $detail->day,
                        'end' => $detail->day,
                        'storage_fee' => $lastFeeModeFee,
                        'total' => round($detail->fee_today, 4),
                    ];
                    continue;
                }
            }
            // 计费模式未变或费率未变，只需要累加部分数据
            $range[$index]['end'] = $detail->day;
            $range[$index]['total'] = BC::create(['scale' => 4])->add($range[$index]['total'], $detail->fee_today);
        }
        if ($qty > 1) {
            // 大于1的，显示的总金额为总数的总金额
            $range = array_map(function ($item) use ($qty) {
                $item['total'] = BC::create(['scale' => 2, 'round' => true])->mul($item['total'], $qty);
                return $item;
            }, $range);
        }

        return array_values($range);
    }

    /**
     * 获取现货未购买的尾款产品仓租
     *
     * @param array $agreementIds 协议数组 [1,2,3]
     * @return array
     */
    public function getMarginRestNeedPayByAgreementId(array $agreementIds): array
    {
        $storageFees = StorageFee::query()
            ->where('transaction_type_id', '=', ProductTransactionType::MARGIN)
            ->whereIn('agreement_id', $agreementIds)
            ->where('order_id', '=', 0)
            ->where('order_product_id', '=', 0)
            ->where('fee_unpaid', '>', 0)
            ->groupBy(['agreement_id'])
            ->get([
                'id','days','agreement_id',
                new Expression("sum(fee_unpaid) as need_pay"),
                new Expression("sum(fee_paid) as paid")
            ]);
        $return = [];
        foreach ($storageFees as $storageFee) {
            $return[$storageFee->agreement_id] = $storageFee;
        }
        return $return;
    }
    /**
     * 根据仓租id获取需要支付的仓租
     * @param array $ids
     * @return float|int 大于0的值
     */
    public function getNeedPayByStorageFeeIds(array $ids)
    {
        $models = StorageFee::query()
            ->whereIn('id', $ids)
            ->where('fee_unpaid', '>', 0)
            ->get();
        if ($models->isEmpty()) {
            return 0;
        }
        $bc = BCS::create(0, ['scale' => 2]);
        foreach ($models as $model) {
            $bc->add($model->fee_unpaid);
        }
        return $bc->getResult();
    }

    /**
     * 检查已绑定的销售单是否需要支付
     * @param $salesOrderId
     * @return bool
     */
    public function checkBoundSalesOrderNeedPay($salesOrderId)
    {
        return StorageFee::query()
            ->where('sales_order_id', $salesOrderId)
            ->where('status', StorageFeeStatus::BIND)
            ->where('fee_unpaid', '>', 0)
            ->exists();
    }

    /**
     * 获取指定sales order line的待支付仓租费用
     *
     * @param int $salesOrderId
     * @param int SalesOrderLineId
     *
     * @return float|int
     */
    public function getBoundSalesOrderNeedPay(int $salesOrderId,int $salesOrderLineId = 0)
    {
        $feeUnpaid = StorageFee::query()
            ->where('sales_order_id', $salesOrderId)
            ->when($salesOrderLineId, function ($query) use ($salesOrderLineId) {
                $query->where('sales_order_line_id', $salesOrderLineId);
            })
            ->where('status', StorageFeeStatus::BIND)
            ->sum('fee_unpaid');
        return $feeUnpaid ?? 0;
    }

    /**
     * 检查是否已经存在当日的仓租记录
     * @param int $countryId 指定国家
     * @param string $date 指定计算日期，格式Y-m-d
     * @return bool
     */
    public function checkHasCalculatedByCountry($countryId, $date)
    {
        return StorageFeeDetail::query()->alias('a')
            ->leftJoinRelations('storageFee as b')
            ->where('a.fee_date', $date)
            ->where('b.country_id', $countryId)
            ->exists();
    }

    /**
     * 检查是否已经存在该订单的仓租记录
     * @param int $orderId 采购订单号
     * @return bool
     */
    public function checkHasCalculatedByOrder($orderId)
    {
        return StorageFeeDetail::query()->alias('a')
            ->leftJoinRelations('storageFee as b')
            ->where('b.order_id', $orderId)
            ->exists();
    }

    /**
     * 检查是否已经存在该协议的仓租记录
     * @param int $type 交易类型
     * @param int $agreementId 协议id
     * @return bool
     */
    public function checkHasCalculatedByAgreement($type, $agreementId)
    {
        return StorageFeeDetail::query()->alias('a')
            ->leftJoinRelations('storageFee as b')
            ->where('b.transaction_type_id', $type)
            ->where('b.agreement_id', $agreementId)
            ->exists();
    }

    /**
     * 预留根据国别获取仓租计费说明id
     *
     * @param int $countryId
     * @return null|int
     */
    public function getStorageFeeDescriptionId($countryId)
    {
        $key = "storage_fee_description_{$countryId}";

        return configDB($key);
    }

    /**
     * 判断一个产品是否会入仓租库
     *
     * @param Product $product
     *
     * @return bool
     */
    public function canEnterStorageFee(Product $product)
    {
        if (!in_array($product->product_type, [ProductType::NORMAL, ProductType::MARGIN_DEPOSIT])) {
            // 仅常规产品、现货头款入仓租
            return false;
        }
        if (in_array($product->customerPartnerToProduct->customer_id, SERVICE_STORE_ARRAY)) {
            // 服务店铺产品不入仓租
            return false;
        }
        list($volume,) = $this->calculateProductVolume($product);
        if ($volume <= 0) {
            // 尺寸小于等于0不入仓租
            return false;
        }

        return true;
    }

    /**
     * 计算产品的体积
     * @param Product $product
     * @return array [volume, size]
     */
    public function calculateProductVolume(Product $product)
    {
        $cacheKey = [__CLASS__, __FUNCTION__, 'v1', $product->product_id];
        $data = $this->getRequestCachedData($cacheKey);
        if ($data) {
            return $data;
        }

        $size = [];
        if ($product->combo_flag) {
            // combo 品为多个的尺寸
            /** @var ProductSetInfo[] $combos */
            $combos = $product->combos()->with(['setProduct'])->get();
            foreach ($combos as $combo) {
                $size[] = [
                    'length' => $combo->setProduct->length,
                    'width' => $combo->setProduct->width,
                    'height' => $combo->setProduct->height,
                    'qty' => $combo->qty,
                ];
            }
        } else {
            $size[] = [
                'length' => $product->length,
                'width' => $product->width,
                'height' => $product->height,
                'qty' => 1,
            ];
        }
        $volume = 0;
        $config = [
            'scale' => 4, // 保留4位
            'ceil' => true, // 向上保留
        ];
        foreach ($size as $item) {
            // 单个体积
            $thisVolume = BCS::create(1, $config)
                ->mul(
                    $this->inch2cm($item['length']),
                    $this->inch2cm($item['width']),
                    $this->inch2cm($item['height'])
                ) // 厘米 长 * 宽 * 高
                ->div(pow(10, 6)) // 立方厘米转立方米
                ->getResult();
            // 乘数量
            if ($item['qty'] > 1) {
                $thisVolume = BCS::create($thisVolume, $config)->mul($item['qty'])->getResult();
            }
            // 累加
            if ($thisVolume > 0) {
                $volume = BC::create($config)->add($volume, $thisVolume);
            }
        }
        $data = [$volume, $size];

        $this->setRequestCachedData($cacheKey, $data);
        return $data;
    }

    /**
     * 英寸转厘米
     * @param $inch
     * @param int $scale
     * @return float|int
     */
    private function inch2cm($inch, $scale = 10)
    {
        if ($inch <= 0) {
            return 0;
        }
        return BC::create(['scale' => $scale])->mul($inch, 2.54);
    }
}
