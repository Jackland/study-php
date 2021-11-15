<?php

namespace App\Models\Purchase;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public function getNoCancelPurchaseOrder()
    {
        //获取订单超时时间
        $expireInfo = \DB::table('oc_setting as os')
            ->select(['os.value'])
            ->where('os.key', '=', 'expire_time')
            ->first();
        $expire_time = $expireInfo->value;
        //获取所有未完成超过30分钟的采购订单
        $unCompleteResult = \DB::table('oc_order as oo')
            ->whereRaw('oo.order_status_id = 0 and oo.date_added<DATE_SUB(now(),INTERVAL ' . $expire_time . ' MINUTE)')
            ->selectRaw('oo.order_id')
            ->get();
        $unCompleteOrderInfo = json_decode(json_encode($unCompleteResult), true);
        return $unCompleteOrderInfo;
    }

    public function getNoCancelPurchaseOrderLine($order_id)
    {
        //获取该采购订单的明细
        $unCompleteLineResult = \DB::table('oc_order as oo')
            ->leftjoin('oc_order_product as oop', 'oop.order_id', '=', 'oo.order_id')
            ->leftjoin('oc_product as op', 'op.product_id', '=', 'oop.product_id')
            ->leftjoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'op.product_id')
            ->leftjoin('oc_customer as oc', 'oc.customer_id', '=', 'ctp.customer_id')
            ->where('oo.order_id', '=', $order_id)
            ->selectRaw('oo.order_id,oo.payment_code,oop.order_product_id,oop.product_id,
            oop.quantity,oc.customer_id,oc.accounting_type,op.combo_flag,oo.date_added,oop.type_id,oop.agreement_id')
            ->get();
        $unCompleteOrderLineInfo = json_decode($unCompleteLineResult, true);
        return $unCompleteOrderLineInfo;
    }

    public function getOrderStatus($order_id)
    {
        $orderResult = \DB::table('oc_order as oo')
            ->where('oo.order_id', '=', $order_id)
            ->select('oo.order_status_id')
            ->lockForUpdate()
            ->first();
        return $orderResult->order_status_id;
    }

    public function getPreDeliveryLines($order_product_id)
    {
        $deliveryLines = \DB::table('tb_sys_seller_delivery_pre_line as dpl')
            ->where('dpl.order_product_id', '=', $order_product_id)
            ->selectRaw('dpl.id,dpl.product_id,dpl.batch_id,dpl.qty')
            ->get();
        return json_decode($deliveryLines, true);
    }


    public function getBxStore()
    {
        $bxStores = \DB::table('oc_setting as os')
            ->select('os.value')
            ->where('os.key', '=', 'config_customer_group_ignore_check')
            ->first();
        return json_decode($bxStores->value, true);
    }

    public function cancelPurchaseOrder($order_id)
    {
        \DB::table("oc_order as oo")
            ->where('oo.order_id', '=', $order_id)
            ->update(['oo.order_status_id' => '7']);
    }

    public function getUmfPayId($order_id)
    {
        $result = \DB::table("tb_payment_info as tpi")
            ->where('tpi.order_id_yzc', '=', $order_id)
            ->select('tpi.*')
            ->get();
        $result = $this->obj2array($result);
        return $result;
    }

    /**
     * [rebackMarginSuffixStore description] 更新保证金尾款库存
     * @param int $product_id
     * @param $num
     */
    public function rebackMarginSuffixStore($product_id, $num)
    {
        //返还上架数量
        // 同步更改子sku所属的其他combo的上架库存数量。
        //返还产品上架数量
        \DB::update("update oc_product set quantity = quantity +" . $num . " where product_id =" . $product_id);
        \DB::update("update oc_customerpartner_to_product set quantity = quantity +" . $num . " where product_id =" . $product_id);

        $setProductInfoArr = \DB::table('tb_sys_product_set_info as psi')
            ->where('psi.product_id', $product_id)
            ->whereNotNull('psi.set_product_id')
            ->select('psi.set_product_id', 'psi.set_mpn', 'psi.qty')
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        foreach ($setProductInfoArr as $setProductInfo) {

            // 同步新增SKU的库存
            \DB::update("update oc_product set quantity = quantity +" . $num * $setProductInfo['qty'] . " where product_id =" . $setProductInfo['set_product_id']);
            \DB::update("update oc_customerpartner_to_product set quantity = quantity +" . $num * $setProductInfo['qty'] . " where product_id =" . $setProductInfo['set_product_id']);

            // 同步更改子sku所属的其他combo的上架库存数量。
            $this->updateOtherMarginComboQuantity($setProductInfo['set_product_id'], $product_id);
        }
        /**
         * 如果当前产品是 其他combo 的组成，则需要同步修改之
         */
        $this->updateOtherMarginComboQuantity($product_id, 0);
    }

    public function updateOtherMarginComboQuantity($product_id, $filter_combo_id = 0)
    {
        $result = \DB::table('tb_sys_product_set_info as psi')
            ->whereRaw("psi.set_product_id = " . $product_id . " and psi.product_id !=" . $filter_combo_id)
            ->select('psi.product_id')
            ->get();
        $otherCombos = $this->obj2array($result);
        foreach ($otherCombos as $combo) {
            // 获取该combo之下的所有sku 的库存、组成combo的比例qty
            $sonSkuResult = \DB::table('tb_sys_product_set_info as psi')
                ->whereRaw("psi.product_id=" . $combo['product_id'] . " and psi.set_product_id is not null")
                ->selectRaw("psi.set_product_id, psi.qty, (select sum(onhand_qty) from tb_sys_batch where product_id = psi.set_product_id) as quantity")
                ->get();
            $sonSkuArr = $this->obj2array($sonSkuResult);
            $tempArr = [];
            foreach ($sonSkuArr as $son) {
                //舍去法取整，获取当前sku 最高可以组成几个combo品
                $tempArr[] = floor($son['quantity'] / $son['qty']);
            }
            // 根据木桶效应，可以组成combo的最大数量取决于 其中sku组成的最小值
            $maxQuantity = !empty($tempArr) ? min($tempArr) : 0;
            $comboOnShelfResult = \DB::table('oc_product AS op')
                ->where('op.product_id', '=', $combo['product_id'])
                ->select('op.quantity')
                ->lockForUpdate()
                ->first();
            $comboOnShelfResult = $this->obj2array($comboOnShelfResult);
            if ($maxQuantity <= ($comboOnShelfResult['quantity'] ?? 0)) {
                \DB::update("update oc_product set quantity = " . $maxQuantity . " where product_id =" . $combo['product_id'] . " and subtract = 1");
                \DB::update("update oc_customerpartner_to_product set quantity =" . $maxQuantity . " where product_id=" . $combo['product_id']);
            }
        }
    }

    /**
     * 反库存
     * @param $preDeliveryLine
     * @param $outFlag 外部店铺标志
     */
    public function rebackStock($purchaseOrder, $preDeliveryLine, $outFlag)
    {
        // 返还批次库存
        \DB::update("update tb_sys_batch set onhand_qty = onhand_qty+" . $preDeliveryLine['qty'] . " where batch_id = " . $preDeliveryLine['batch_id']);
        if (!$outFlag) {
            //内部店铺
            $syncResult = \DB::table('oc_product as op')
                ->where('op.product_id', '=', $preDeliveryLine['product_id'])
                ->selectRaw("ifnull(sync_qty_date,'2018-01-01 00:00:00') as sync_qty_date")
                ->lockForUpdate()
                ->first();
            $sync_qty_date = $syncResult->sync_qty_date;
            $date_added = strtotime($purchaseOrder['date_added']);
            if ($date_added > strtotime($sync_qty_date)) {
                //返还产品上架数量
                \DB::update("update oc_product set quantity = quantity +" . $preDeliveryLine['qty'] . " where product_id =" . $preDeliveryLine['product_id']);
                \DB::update("update oc_customerpartner_to_product set quantity = quantity +" . $preDeliveryLine['qty'] . " where product_id =" . $preDeliveryLine['product_id']);
            }
        } else {
            //外部店铺
            //返还产品上架数量
            \DB::update("update oc_product set quantity = quantity +" . $preDeliveryLine['qty'] . " where product_id =" . $preDeliveryLine['product_id']);
            \DB::update("update oc_customerpartner_to_product set quantity = quantity +" . $preDeliveryLine['qty'] . " where product_id =" . $preDeliveryLine['product_id']);
        }

        // 同步更改子sku所属的其他combo的上架库存数量。
        $this->updateOtherComboQuantity($preDeliveryLine['product_id'], $purchaseOrder);
        //设置预出库表的这状态
        \DB::update("update tb_sys_seller_delivery_pre_line set status = 0 where id=" . $preDeliveryLine['id']);
    }

    public function reback_batch($preDeliveryLine){
        // 返还批次库存
        \DB::update("update tb_sys_batch set onhand_qty = onhand_qty+" . $preDeliveryLine['qty'] . " where batch_id = " . $preDeliveryLine['batch_id']);
        //设置预出库表的这状态
        \DB::update("update tb_sys_seller_delivery_pre_line set status = 0 where id=" . $preDeliveryLine['id']);
    }

    //退还上架库存
    public function reback_stock_ground($margin,$order){
        //获取协议状态
        $margin_info=\DB::table('tb_sys_margin_agreement')
            ->where('id','=',$margin['margin_id'])
            ->select('status')->first();
        if(in_array($margin_info->status,[9,10])){    //margin 已经取消
            // 抹平lock 中数据
            $this->clear_product_lock($margin['margin_id'],$order['quantity'],$order['order_id']);
            // 返还上架库存
            $this->rebackMarginSuffixStore($order['product_id'], $order['quantity']);
        }
    }

    public function updateOtherComboQuantity($product_id, $purchaseOrder, $filter_combo_id = 0)
    {
        $result = \DB::table('tb_sys_product_set_info as psi')
            ->whereRaw("psi.set_product_id = " . $product_id . " and psi.product_id !=" . $filter_combo_id)
            ->select('psi.product_id')
            ->get();
        $otherCombos = $this->obj2array($result);
        foreach ($otherCombos as $combo) {
            // 获取该combo之下的所有sku 的库存、组成combo的比例qty
            $sonSkuResult = \DB::table('tb_sys_product_set_info as psi')
                ->whereRaw("psi.product_id=" . $combo['product_id'] . " and psi.set_product_id is not null")
                ->selectRaw("psi.set_product_id, psi.qty, (select sum(onhand_qty) from tb_sys_batch where product_id = psi.set_product_id) as quantity")
                ->get();
            $sonSkuArr = $this->obj2array($sonSkuResult);
            $tempArr = [];
            foreach ($sonSkuArr as $son) {
                //舍去法取整，获取当前sku 最高可以组成几个combo品
                $tempArr[] = floor($son['quantity'] / $son['qty']);
            }
            // 根据木桶效应，可以组成combo的最大数量取决于 其中sku组成的最小值
            $maxQuantity = !empty($tempArr) ? min($tempArr) : 0;
            $comboOnShelfResult = \DB::table('oc_product AS op')
                ->where('op.product_id', '=', $combo['product_id'])
                ->select('op.quantity')
                ->lockForUpdate()
                ->first();
            $comboOnShelfResult = $this->obj2array($comboOnShelfResult);
            if ($maxQuantity <= ($comboOnShelfResult['quantity'] ?? 0)) {
                \DB::update("update oc_product set quantity = " . $maxQuantity . " where product_id =" . $combo['product_id'] . " and subtract = 1");
                \DB::update("update oc_customerpartner_to_product set quantity =" . $maxQuantity . " where product_id=" . $combo['product_id']);
            }
        }
    }

    function obj2array($obj)
    {
        if (empty($obj)) return [];
        return json_decode(json_encode($obj), true);
    }

    public function serviceStoreReback($product_id, $qty)
    {
        \DB::update("update oc_product set quantity = quantity +" . $qty . " where product_id =" . $product_id);
        \DB::update("update oc_customerpartner_to_product set quantity = quantity +" . $qty . " where product_id =" . $product_id);
    }

    public function marginStoreReback($product_id, $qty)
    {
        \DB::update("update oc_product set quantity = quantity +" . $qty . " where product_id =" . $product_id);
        \DB::update("update oc_customerpartner_to_product set quantity = quantity +" . $qty . " where product_id =" . $product_id);
    }

    public function checkMarginProduct($purchaseOrder)
    {
        $result = \DB::table("tb_sys_margin_process as smp")
            ->leftJoin("tb_sys_margin_agreement as sma", "smp.margin_id", "=", "sma.id")
            ->select("sma.product_id", "sma.num","smp.margin_id")
            ->where("smp.advance_product_id", "=", $purchaseOrder['product_id'])
            ->first();
        return $this->obj2array($result);
    }

    public function checkRestMarginProduct($purchaseOrder)
    {
        $result = \DB::table("tb_sys_margin_process as smp")
            ->leftJoin("tb_sys_margin_agreement as sma", "smp.margin_id", "=", "sma.id")
            ->leftJoin('oc_customerpartner_to_product  as otp','otp.product_id','=','smp.rest_product_id')
            ->select("otp.customer_id as seller_id","sma.product_id", "sma.num",'smp.margin_id')
            ->where([
                "smp.rest_product_id" => $purchaseOrder['product_id'],
                "smp.margin_id" => $purchaseOrder['agreement_id'],
            ])
            ->first();
        return $this->obj2array($result);
    }

    //期货头款商品，以及期货保证金版本
    public function checkFuturesAdvanceProduct($purchaseOrder)
    {
        $result = \DB::table("oc_futures_margin_process as fp")
            ->leftJoin("oc_futures_margin_agreement as fa", "fa.id", "=", "fp.agreement_id")
            ->select("fp.advance_product_id", "fp.agreement_id", "fa.contract_id","fa.is_bid")
            ->where("fp.advance_product_id", "=", $purchaseOrder['product_id'])
            ->where('fa.agreement_status', "=", 3)
            ->where('fp.process_status', "=", 1)
            ->first();
        return $this->obj2array($result);
    }

    //货期尾款商品
    public function checkRestFuturesProduct($purchaseOrder)
    {
        $result = \DB::table("oc_futures_margin_agreement as fa")
            ->leftJoin("oc_futures_margin_delivery as fd", "fa.id", "=", "fd.agreement_id")
            ->select('fd.agreement_id','fa.product_id', 'fa.seller_id', 'fa.buyer_id')
            ->where([
                "fa.agreement_status"   => 7,
                "fd.delivery_status"    => 6,
                "fa.product_id"         => $purchaseOrder['product_id'],
                "fd.agreement_id"       => $purchaseOrder['agreement_id'],
            ])
            ->first();
        return $this->obj2array($result);
    }

    //是否是期货转现货
    public function futuresToMargin($marginId)
    {
        return \DB::table('oc_futures_margin_delivery')
            ->where([
                'margin_agreement_id'   => $marginId
            ])
            ->value('agreement_id');
    }

    public function deleteMarginProductLock($agreement_id)
    {
        $futuresToMargin = $this->futuresToMargin($agreement_id);
        if (!$futuresToMargin){//期货转现货订单未支付成功，不解除库存锁定
            $ids=\DB::table("oc_product_lock")
                ->where([
                    'agreement_id' => $agreement_id,
                    'type_id' => 2, //margin type
                ])
                ->get(['id']);
            $ids=$this->obj2array($ids);
            $ids=array_column($ids,'id');
            if ($ids) {
                \DB::table("oc_product_lock_log")->whereIn('product_lock_id', $ids)->delete();
            }
            \DB::table("oc_product_lock")
                ->where([
                    'agreement_id' => $agreement_id,
                    'type_id' => 2, //margin type
                ])->delete();
        }

        \DB::table("oc_agreement_common_performer")
            ->where([
                'agreement_id' => $agreement_id,
                'agreement_type' => 0, //margin type
            ])->delete();

    }

    public function updateMarginProductLock($agreement_id, $num,$order_id)
    {
        $list = \DB::table("oc_product_lock")
            ->where([
                'agreement_id' => $agreement_id,
                'type_id' => 2, //margin type
            ])
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        $length = count($list);
        if ($length == 1) {
            $delRes = \DB::table('oc_product_lock_log')
                ->where('product_lock_id', '=', $list[0]['id'])
                ->where('transaction_id', '=', $order_id)
                ->delete();
            if ($delRes) {
                // 删除成功再增加数量
                \DB::table("oc_product_lock")
                    ->where([
                        'agreement_id' => $agreement_id,
                        'type_id' => 2, //margin type
                    ])
                    ->increment('qty', $num);
            }
        } else {
            foreach ($list as $item) {
                // 删除log
                $delRes = \DB::table('oc_product_lock_log')
                    ->where('product_lock_id', '=', $item['id'])
                    ->where('transaction_id', '=', $order_id)
                    ->delete();
                if ($delRes) {
                    // 删除成功再增加数量
                    $set_qty = $item['set_qty'];
                    \DB::table("oc_product_lock")
                        ->where('id', $item['id'])
                        ->increment('qty', $set_qty * $num);
                }
            }
        }
    }


    /**
     * 期货保证金头款，二期
     * @param $productId
     * @param $agreementId
     */
    public function updateFuturesAdvanceProductSecond($productId, $agreementId)
    {
        //头款产品下架
        //期货协议释放库存 is_lock=0
        \DB::table('oc_product')
            ->where('product_id', '=', $productId)
            ->where('product_type', '=', 2)
            ->update([
                'status' => 0,
            ]);
        \DB::table('oc_futures_margin_agreement')
            ->where('id', '=', $agreementId)
            ->update([
                'agreement_status' => 6,//Time Out
                'is_lock'          => 0,
            ]);
    }

    //期货头款库存恢复
    public function updateFuturesAdvanceProductStock($productId)
    {
        \DB::table('oc_product')
            ->where('product_id','=', $productId)
            ->where('product_type', '=', 2)
            ->update(['quantity'=>1]);
        \DB::table('oc_customerpartner_to_product')
            ->where([
                'product_id'    => $productId
            ])
            ->update(['quantity'=>1]);
    }

    // 释放期货协议锁定的合约数量
    public function unLockAgreementNum($agreement_id)
    {
        \DB::table('oc_futures_margin_agreement')
            ->where([
                'id' => $agreement_id
            ])
            ->update(['is_lock' => 0]);
    }

    public function clear_product_lock($agreement_id,$num,$order_id){
        $list = \DB::table("oc_product_lock")
            ->where([
                'agreement_id' => $agreement_id,
                'type_id' => 2, //margin type
            ])
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        $length = count($list);
        if ($length == 1) {
            \DB::table("oc_product_lock")
                ->where([
                    'agreement_id' => $agreement_id,
                    'type_id' => 2, //margin type
                ])
                ->decrement('qty', $num);
            \DB::table('oc_product_lock_log')
                ->insert(array(
                    'product_lock_id'=>$list[0]['id'],
                    'qty'=>$num*-1,
                    'change_type'=>3,
                    'transaction_id'=>$order_id,
                    'memo'=>'yzc_task_work订单退返',
                    'create_user_name'=>'yzc_task_work',
                    'create_time'=>date('Y-m-d H:i:s',time())
                ));
        } else {
            foreach ($list as $item) {
                $set_qty = $item['set_qty'];
                \DB::table("oc_product_lock")
                    ->where('id', $item['id'])
                    ->decrement('qty', $set_qty * $num);
                //添加log
                \DB::table('oc_product_lock_log')
                    ->insert(array(
                        'product_lock_id'=>$item['id'],
                        'qty'=>$set_qty * $num*-1,
                        'change_type'=>3,
                        'transaction_id'=>$order_id,
                        'memo'=>'yzc_task_work订单退返',
                        'create_user_name'=>'yzc_task_work',
                        'create_time'=>date('Y-m-d H:i:s',time())
                    ));
            }
        }
    }

    /**
     * combo品的库存退回
     * @param $product_id
     * @param $quantity
     */
    public function rebackComboProduct($product_id, $quantity)
    {
        \DB::update("update oc_product set quantity = quantity +" . $quantity . " where product_id =" . $product_id);
        \DB::update("update oc_customerpartner_to_product set quantity = quantity +" . $quantity . " where product_id =" . $product_id);
    }
}
