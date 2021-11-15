<?php
/**
 * 采购单金额
 * Created by PhpStorm.
 * User: zhousuyang
 */

namespace App\Models\Product;


use Illuminate\Support\Facades\DB;

class ProductOrderMoney
{
    /**
     * 某产品90天内采购单金额
     * @param $product_id
     */
    public function orderMoney($product_id)
    {

        //原产品对应的保证金产品，因为第一版保证金协议中 原产品product_id/头款产品product_id/尾款产品product_id 三个各不相同；
        //头款产品product_id/尾款产品product_id 的采购单金额应当给 原产品product_id
        $sql = "
    SELECT
        mp.advance_product_id
        , mp.rest_product_id 
    FROM
        tb_sys_margin_agreement AS ma
        JOIN tb_sys_margin_process AS mp ON mp.margin_id = ma.id 
    WHERE
        ma.product_id = {$product_id}";

        $query = \DB::connection('mysql_proxy')->select($sql);
        $rows  = isset($query[0]) ? $query : [];

        $process_product = [];
        foreach ($rows as $key=>$value) {
            if (isset($value->advance_product_id)) {
                $process_product[] = $value->advance_product_id;
            }
            if (isset($value->rest_product_id)) {
                $process_product[] = $value->rest_product_id;
            }
        }

        $condition = '';
        if ($process_product) {
            $process_product[] = $product_id;
            $product_str       = implode(',', $process_product);
            $condition         .= ' op.product_id IN (' . $product_str . ') ';
        } else {
            $condition .= ' op.product_id=' . $product_id;
        }




        $day = 90;

        $sql = "
    SELECT 
        o.order_id
        , op.order_product_id
        , op.total
        , op.price
        , op.service_fee
        , op.service_fee_per
        , op.quantity
        , op.freight_per
        , op.package_fee
        , IFNULL(pq.amount,0) as quote
    FROM oc_order As o 
    JOIN oc_order_product AS op ON op.order_id=o.order_id
    LEFT JOIN oc_product_quote pq ON (o.order_id=pq.order_id AND op.product_id=pq.product_id)
    WHERE
        {$condition}
        AND TIMESTAMPDIFF(DAY, o.date_added ,NOW()) < {$day}";

        $query = \DB::connection('mysql_proxy')->select($sql);
        $rows  = isset($query[0]) ? $query : [];

        $totalPrice = 0.0;
        foreach ($rows as $key => $value) {
            $detail=[];
            $detail['quantity']    = $value->quantity;
            $detail['SalesPrice']  = $value->price;
            $detail['serviceFee']  = $value->service_fee;
            $detail['freight_per'] = $value->freight_per;
            $detail['package_fee'] = $value->package_fee;
            $detail['quote']       = $value->quote;

            $totalPrice += ((double)$detail['quantity'] * $detail['SalesPrice'] + $detail['serviceFee'] + ($detail['freight_per']  + $detail['package_fee']) * $detail['quantity'] - $detail['quote']);


            //RMA金额 不考虑RMA金额
            //$order_id = $value->order_id;
            //$seller_id =$value->seller_id;
            //$order_product_id = $value->order_product_id;
            //$rmaInfo = $this->getSellerAgreeRmaOrderInfoInOrderHistory($order_id, $seller_id, $order_product_id);
            //foreach ($rmaInfo as $rma){
            //    $totalPrice -= $rma['actual_refund_amount'];
            //}
        }






        $date_now = date('Y-m-d H:i:s');


        //是否存在原纪录
        $sql   = "SELECT id FROM oc_product_crontab  WHERE product_id={$product_id}";
        $query = \DB::connection('mysql_proxy')->select($sql);
        $one   = isset($query[0]) ? $query[0] : [];

        if ($one) {
            $sql_exec = "UPDATE oc_product_crontaboc_product_crontab SET 
    order_money={$totalPrice} 
    ,order_money_date_modified='{$date_now}'
    WHERE product_id={$product_id}";
        } else {
            $sql_exec = "
            INSERT INTO oc_product_crontab
            SET product_id = {$product_id},
            date_added='{$date_now}',
            order_money = {$totalPrice},
            order_money_date_modified = '{$date_now}'";
        }

        \DB::connection('mysql_proxy')->update($sql_exec);
        //echo "product_id={$product_id}, order_money={$money}".PHP_EOL;
    }


    /**
     * 参考自：catalog\model\account\customerpartner.php getSellerAgreeRmaOrderInfoInOrderHistory()
     * @param $order_id
     * @param $seller_id
     * @param $order_product_id
     * @return mixed
     */
    public function getSellerAgreeRmaOrderInfoInOrderHistory($order_id, $seller_id, $order_product_id)
    {
        //1.表格里展示的退返品应该只有被Seller同意的（状态是Approved）=
        //2.针对采购订单的退款，是会退库存的，且只能同意一次，Quantity为-退货数量，金额为-退款总金额
        //3.针对Canceled销售订单的退款，是会退库存的，且可以申请多次并同意多次，退库存成功的那次Quantity为-退货数量，其他没有退库存的申请，Quantity都为0，金额为-每次退款金额
        //4.针对Completed销售订单的退款，是不会退库存的，且可以申请多次并同意多次，每条的Quantity都为0，金额为-退款金额。
        //5.针对Completed销售订单的重发，是不会退库存的，且可以申请多次并同意多次，每条的Quantity都为0，金额为0。
        //6.表格中的Is Return挪到表格最后一列，在这列后面再加RMA Type（Reshipment、Refund）和 RMA ID
        //7.如果是RMA记录，Sales Date 空着
        $cancel_arr = [];
        $objs       = \DB::connection('mysql_proxy')->table('oc_yzc_rma_order as ro')
            ->join('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->select([
                'ro.id',
                'ro.order_id',
                'ro.buyer_id',
                'ro.from_customer_order_id',
                'ro.order_type',
                'rop.actual_refund_amount',
                'rop.rma_type',
                'ro.processed_date',
            ])
            ->where([
                ['ro.order_id', '=', $order_id],
                ['ro.seller_id', '=', $seller_id],
                ['rop.order_product_id', '=', $order_product_id],
            ])->where(function ($query) {
                //返金和重发同时申请
                $query->where([['rop.rma_type', '=', 3], ['rop.status_refund', '=', 1]])
                    ->orWhere([['rop.rma_type', '=', 2], ['rop.status_refund', '=', 1]]);
                //->orWhere([['rop.rma_type','=',1],['rop.status_reshipment','=',1]]);
            })
            ->whereIn('ro.seller_status', [2, 3])  // seller  [approved , pending]
            ->orderBy('ro.processed_date', 'asc')
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();

        //查询结果满足第一条
        foreach ($objs as $key => &$value) {
            if ($value['rma_type'] == 1) {
                $objs[$key]['rma_name'] = 'Reshipment';
            } elseif ($value['rma_type'] == 2) {
                $objs[$key]['rma_name'] = 'Refund';
            } else {
                $objs[$key]['rma_name'] = 'Reshipment&Refund';
            }
            //满足第二条
            if ($value['order_type'] == 2) {

            } else {
                //销售订单的rma
                // 销售订单状态
                $order_info             =
                    \DB::connection('mysql_proxy')->table('tb_sys_customer_sales_order')
                        ->where([
                            'order_id' => $value['from_customer_order_id'],
                            'buyer_id' => $value['buyer_id'],
                        ])
                        ->select('order_status', 'id')
                        ->first();
                $from_customer_order_id = $order_info->id;

                if ($order_info->order_status == 16) {
                    //取消的销售订单
                    //3.针对Canceled销售订单的退款，是会退库存的，且可以申请多次并同意多次，退库存成功的那次Quantity为-退货数量，其他没有退库存的申请，Quantity都为0，金额为-每次退款金额
                    //if(!isset($cancel_arr[$from_customer_order_id])){
                    //    $value['quantity'] =
                    //        DB::table('tb_sys_order_associated')
                    //            ->where([
                    //                'order_id' => $order_id,
                    //                'order_product_id' => $order_product_id,
                    //                'sales_order_id' => $from_customer_order_id,
                    //            ])
                    //            ->value('qty');
                    //    $cancel_arr[ $from_customer_order_id] = 1;
                    //}else{
                    //    $value['quantity'] = 0;
                    //}
                } elseif ($order_info->order_status == 32) {
                    //complete的销售订单
                    //满足 .4 .5
                    if ($value['rma_type'] == 1) {
                        //$value['quantity'] = 0;
                        $value['actual_refund_amount'] = 0;
                    } elseif ($value['rma_type'] == 2) {
                        //$value['quantity'] = 0;
                    } else {
                        //需要更改 processed_date 假如没有时间的话
                        //if(!$value['processed_date']){
                        //    $value['processed_date'] =
                        //        DB::table('tb_sys_credit_line_amendment_record')
                        //            ->where(
                        //                [
                        //                    'type_id'=>3,
                        //                    'header_id'=>$value['id'],
                        //                ]
                        //            )
                        //            ->value('date_added');
                        //}
                        //$value['quantity'] = 0;
                    }
                } else {
                    //和complete一样
                    if ($value['rma_type'] == 1) {
                        //$value['quantity'] = 0;
                        $value['actual_refund_amount'] = 0;
                    } elseif ($value['rma_type'] == 2) {
                        //$value['quantity'] = 0;
                    } else {
                        //$value['quantity'] = 0;
                    }
                }
            }
        }
        return $objs;
    }
}