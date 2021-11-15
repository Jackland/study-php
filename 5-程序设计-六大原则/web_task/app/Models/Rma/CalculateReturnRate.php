<?php
/**
 * Created by PhpStorm.
 * User: Lu.Chen
 * Date: 2020/4/27
 * Time: 14:45
 */

namespace App\Models\Rma;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use SebastianBergmann\CodeCoverage\Report\PHP;

class CalculateReturnRate extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    //计算退返率，并更新
    public function updateRate()
    {
        \Log::info('商品退返率更新开始:'.time());
        $purchase = $this->purchaseNum();
        $return = $this->returnNum();
        $lastReturnRateList = $this->getLastReturnRateList();

        foreach ($purchase as $productId=>$quantity)
        {
            if (0 == $quantity) continue;
            $rNum = isset($return[$productId])?$return[$productId]:0;
            $rate = 0;
            if ($quantity > 10){
                $rate = sprintf('%.2f', round(100 * $rNum/$quantity, 2));
            }
            $is_changed = 1;
            $last_return_rate = $rate;
            // 有更新数据且是return rate 相等的需要把is_changed 更改成 0
            if(isset($lastReturnRateList[$productId])){
                $last_return_rate = $lastReturnRateList[$productId]->return_rate;
                if($last_return_rate == $rate){
                    $is_changed = 0;
                }
            }

            $data = [
                'purchase_num'  => $quantity,
                'return_num'    => $rNum,
                'return_rate'   => $rate,
                'last_return_rate'   => $last_return_rate,
                'is_changed'    => $is_changed,
                'return_date_modified'  => date('Y-m-d H:i:s'),
            ];

            \DB::connection('mysql_proxy')->table('oc_product_crontab')
                ->updateOrInsert([
                    'product_id'    => $productId
                ],$data);
        }
        \Log::info('商品退返率更新完成:'.time());
    }

    public function getLastReturnRateList()
    {
        return \DB::connection('mysql_proxy')
                    ->table('oc_product_crontab')
                    ->select('last_return_rate','product_id','return_rate')
                    ->get()
                    ->keyBy('product_id')
                    ->toArray();
    }

    //采购数量
    public function purchaseNum()
    {
/*        $orderProduct = DB::table('oc_order_product as op')
            ->leftJoin('oc_order as o', 'o.order_id', 'op.order_id')
            ->whereIn('o.order_status_id', [5,13])
            ->selectRaw('ifnull(sum(quantity),0) as quantity, product_id')
            ->groupBy('op.product_id')
            ->get();
        $purchase = [];
        foreach ($orderProduct as $k=>$v)
        {
            $purchase[$v->product_id] = $v->quantity;
        }*/

/*        $purchase = DB::table('tb_sys_product_all_sales')
            ->pluck('quantity', 'product_id')
            ->toArray();*/

        $orderProduct = \DB::connection('mysql_proxy')->table('oc_customerpartner_to_order')
            ->whereIn('order_product_status', [5,13])
            ->selectRaw('sum(quantity) as quantity, product_id')
            ->groupBy('product_id')
            ->get();
        $purchase = [];
        $restProduct = $this->marginRestProduct();
        foreach ($orderProduct as $k=>$v)
        {
            if (isset($restProduct[$v->product_id])){//是旧版现货保证金尾款产品
                if (!isset($purchase[$restProduct[$v->product_id]])){
                    $purchase[$restProduct[$v->product_id]] = 0;
                }
                $purchase[$restProduct[$v->product_id]] += $v->quantity;
            }else{
                if (!isset($purchase[$v->product_id])){
                    $purchase[$v->product_id] = 0;
                }
                $purchase[$v->product_id] += $v->quantity;
            }
        }

        return $purchase;
    }

    //退返数量
    public function returnNum()
    {
        $rmaProduct = \DB::connection('mysql_proxy')->table('oc_yzc_rma_order_product as rop')
            ->leftJoin('oc_yzc_rma_order as ro', 'ro.id', '=','rop.rma_id')
            ->leftJoin('oc_product as p', 'p.product_id', '=','rop.product_id')
            ->leftJoin('tb_sys_customer_sales_order as so', ['so.order_id'=>'ro.from_customer_order_id', 'so.buyer_id'=>'ro.buyer_id'])
            ->leftJoin('tb_sys_customer_sales_order_line as sol', ['sol.header_id'=>'so.id', 'sol.item_code'=>'p.sku'])
            ->leftJoin('tb_sys_order_associated as ass', ['ass.order_product_id'=>'rop.order_product_id', 'sol.id'=>'ass.sales_order_line_id'])
            ->select('rop.id', 'ass.id as ass_id', 'ass.qty as ass_qty', 'rop.product_id', 'rop.quantity')
            ->where([
                'ro.order_type'     => 1,
                'ro.cancel_rma'     => 0,
                'so.order_status'   => 32
            ])
            ->where('ass.id', '>', 0)
            ->get();

        $return = [];//商品在每个采购订单销售订单的关联关系下的退返数量
        $assQty = [];//商品在每个采购订单销售订单的关联关系下的数量
        $restProduct = $this->marginRestProduct();
        foreach ($rmaProduct as $k=>$v)
        {
            if (!$v->ass_id) continue;
            if (isset($restProduct[$v->product_id])){//是旧版现货保证金尾款产品
                if (!isset($return[$restProduct[$v->product_id]][$v->ass_id])){
                    $return[$restProduct[$v->product_id]][$v->ass_id] = 0;
                }
                $return[$restProduct[$v->product_id]][$v->ass_id] += $v->quantity;
            }else{
                if (!isset($return[$v->product_id][$v->ass_id])){
                    $return[$v->product_id][$v->ass_id] = 0;
                }
                $return[$v->product_id][$v->ass_id] += $v->quantity;
            }

            $assQty[$v->product_id][$v->ass_id] = $v->ass_qty;
        }

        foreach ($return as $productId => $value){

            foreach ($value as $assId => $quantity){
                if (!isset($assQty[$productId][$assId])){
                    $return[$productId][$assId] = 0;
                    continue;
                }
                if ($quantity > $assQty[$productId][$assId]){
                    //该产品对某单申请的退返品数量之和 大于 该单采销关联的总数量时，认定退返数量为该采销关联总数量
                    $return[$productId][$assId] = $assQty[$productId][$assId];
                }
            }
        }
        $returnNum = [];
        foreach ($return as $productId => $v){
            $returnNum[$productId] = array_sum($v);
        }

        return $returnNum;
    }

    //旧版保证金尾款商品 rest_product_id与product_id的对应关系
    public function marginRestProduct()
    {
        return \DB::connection('mysql_proxy')->table('tb_sys_margin_process as mp')
            ->leftJoin('tb_sys_margin_agreement as ma', 'mp.margin_id', '=', 'ma.id')
            ->where('mp.process_status', '>', 2)
            ->whereRaw('mp.rest_product_id != ma.product_id')
            ->pluck('ma.product_id', 'mp.rest_product_id')
            ->toArray();
    }
}