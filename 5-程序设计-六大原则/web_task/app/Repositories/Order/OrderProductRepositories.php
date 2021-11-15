<?php

namespace App\Repositories\Order;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrderProductRepositories
{
    /**
     * 获取当前14天&当前7天的销售额
     * @return array[]
     */
    public function getRecentTimeOrderProductAmount(): array
    {
        $builder = DB::connection('mysql_proxy')->table('oc_order_product as op')
            ->leftJoin('oc_order as oo','oo.order_id','op.order_id')
            ->leftJoin('oc_customer as c','oo.customer_id','c.customer_id')
            ->leftJoin('oc_product_quote as pq', function ($join) {
                $join->on('pq.order_id', '=', 'op.order_id')->on('pq.product_id', '=', 'op.product_id');
            })
            ->where('oo.order_status_id',5) // completed
            ->select(
                'op.price as op_price',
                'pq.price as pq_price',
                'op.poundage',
                'op.quantity as op_quantity',
                'op.service_fee_per',
                'op.freight_per',
                'op.package_fee',
                'pq.amount_price_per',
                'pq.amount_service_fee_per',
                'op.type_id',
                'op.agreement_id',
                'op.order_product_id',
                'op.product_id',
                'op.coupon_amount',
                'op.campaign_amount',
                'c.country_id',
                'c.customer_group_id'
            );
        $builder14 = clone $builder;
        $amount_7 = $builder->where('oo.date_added','>',Carbon::now()->subDays(7)->format('Y-m-d H:i:s'));
        $amount_14 = $builder14->where('oo.date_added','>',Carbon::now()->subDays(14)->format('Y-m-d H:i:s'));
        $list_7 = [];
        foreach($amount_7->cursor() as $items){
            $unitPrice = $items->op_price - (float)$items->amount_price_per;
            $freight_per = in_array($items->customer_group_id,[24,25,26]) ? 0 : $items->freight_per;
            $serviceFeeTotal = 0;
            if(in_array($items->country_id,[81,222])){
                $serviceFeePer = $items->service_fee_per;
                $serviceFeeTotal = ($serviceFeePer - (float)$items->amount_service_fee_per) * $items->op_quantity;
            }
            $freight = $freight_per + $items->package_fee;
            //$couponAmount = (float)$items->coupon_amount;
            $couponAmount = 0;
            $campaignAmount = 0;
            //$campaignAmount = (float)$items->campaign_amount;
            $advancePrice = $items->op_quantity*($this->getSingleAdvancePrice($items->type_id,$items->agreement_id));
            $finalTotalPrice = sprintf('%.2f',(($unitPrice + $freight)*$items->op_quantity + $serviceFeeTotal - $couponAmount - $campaignAmount + $advancePrice));
            if(!isset($list_7[$items->product_id])){
                $list_7[$items->product_id] = round($finalTotalPrice,2);
            }else{
                $list_7[$items->product_id] += round($finalTotalPrice,2);
            }
        }
        $list_14 = [];
        foreach($amount_14->cursor() as $items){
            $unitPrice = $items->op_price - (float)$items->amount_price_per;
            $freight_per = in_array($items->customer_group_id,[24,25,26]) ? 0 : $items->freight_per;
            $serviceFeeTotal = 0;
            if(in_array($items->country_id,[81,222])){
                $serviceFeePer = $items->service_fee_per;
                $serviceFeeTotal = ($serviceFeePer - (float)$items->amount_service_fee_per) * $items->op_quantity;
            }
            $freight = $freight_per + $items->package_fee;
            //$couponAmount = (float)$items->coupon_amount;
            $couponAmount = 0;
            $campaignAmount = 0;
            //$campaignAmount = (float)$items->campaign_amount;
            $advancePrice = $items->op_quantity*($this->getSingleAdvancePrice($items->type_id,$items->agreement_id));
            $finalTotalPrice = sprintf('%.2f',(($unitPrice + $freight)*$items->op_quantity + $serviceFeeTotal - $couponAmount - $campaignAmount + $advancePrice));
            if(!isset($list_14[$items->product_id])){
                $list_14[$items->product_id] = round($finalTotalPrice,2);
            }else{
                $list_14[$items->product_id] += round($finalTotalPrice,2);
            }
        }
        return [$list_7,$list_14];

    }

    public function getSingleAdvancePrice(int $type_id,$agreement_id)
    {
        $key = $type_id . '_' . $agreement_id;
        $price = 0;
        if(Cache::has($key)){
            return Cache::get($key);
        }
        // 期货尾款支付
        if($type_id == 3){
            $data = DB::connection('mysql_proxy')->table('oc_futures_margin_agreement as f')
                ->leftJoin('oc_futures_margin_delivery as d','d.agreement_id','f.id')
                ->where('f.id',$agreement_id)
                ->selectRaw('(f.unit_price - d.last_unit_price) as price')
                ->get()
                ->first();
            if($data) {
                $price = round($data->price, 2);
            }
        }

        if($type_id == 2){
            // 判断是否是期货转现货
            $data = DB::connection('mysql_proxy')->table('oc_futures_margin_agreement as f')
                ->leftJoin('oc_futures_margin_delivery as d','d.agreement_id','f.id')
                ->where('d.margin_agreement_id',$agreement_id)
                ->selectRaw('(f.unit_price - d.margin_last_price) as price')
                ->get()
                ->first();

            if($data && $data->price){
                $price = round($data->price,2);
            }else{
                $price = DB::connection('mysql_proxy')->table('tb_sys_margin_agreement')
                    ->where('id',$agreement_id)
                    ->value('deposit_per');
            }
        }
        Cache::add($key,$price,5);
        return $price;
    }

    public static function getRecentTimeProductDownloadTimes(): array
    {
        $builder = DB::connection('mysql_proxy')->table('tb_sys_product_package_info')
            ->where('CreateTime','>',Carbon::now()->subDays(14)->format('Y-m-d H:i:s'))
            ->groupBy(['product_id'])
            ->selectRaw('count(*) as count,product_id');
        $list_14 = [];
        foreach($builder->cursor() as $items){
            $list_14[$items->product_id] = $items->count;
        }

        return $list_14;

    }

    public static function getRecentDropPrice(): array
    {
        $builder = DB::connection('mysql_proxy')->table('oc_seller_price_history as sph')
            ->leftJoin('oc_customerpartner_to_product as ctp','ctp.product_id','sph.product_id')
            ->leftJoin('oc_customer as c','c.customer_id','ctp.customer_id')
            ->where('sph.status',1)
            ->where('c.country_id',223)
            ->orderBy('sph.id','desc')
            ->select('sph.*');
        $builder14 = clone $builder;
        $dropPrice2 = $builder->where('sph.add_date','>',Carbon::now()->subDays(2)->format('Y-m-d H:i:s'));
        $dropPrice14 = $builder14->where('sph.add_date','>',Carbon::now()->subDays(14)->format('Y-m-d H:i:s'));
        $list_2 = [];
        foreach($dropPrice2->cursor() as $items){
            if(isset($list_2[$items->product_id])){
                continue;
            }else{
                $prePrice = DB::connection('mysql_proxy')->table('oc_seller_price_history as sph')
                    ->where('sph.status',1)
                    ->where('sph.product_id',$items->product_id)
                    ->WhereNotIn('sph.id',[$items->id])
                    ->orderBy('sph.id','desc')
                    ->value('price') ?? 0;
                $currentPrice = $items->price;
                if($prePrice != 0){
                    $list_2[$items->product_id]['price'] = round(($prePrice - $currentPrice),4);
                    $list_2[$items->product_id]['seller_price_time'] = $items->add_date;
                }
            }
        }

        $list_14 = [];
        foreach($dropPrice14->cursor() as $items){
            if(isset($list_14[$items->product_id])){
                continue;
            }else{
                $prePrice = DB::connection('mysql_proxy')->table('oc_seller_price_history as sph')
                        ->where('sph.status',1)
                        ->where('sph.product_id',$items->product_id)
                        ->WhereNotIn('sph.id',[$items->id])
                        ->orderBy('sph.id','desc')
                        ->value('price') ?? 0;
                $currentPrice = $items->price;
                if($prePrice != 0){
                    $list_14[$items->product_id]['price'] = round(($prePrice - $currentPrice)/$prePrice,4);
                    $list_14[$items->product_id]['seller_price_time'] = $items->add_date;
                }
            }
        }

        return [$list_2,$list_14];
    }

    public static function getSellerReturnApprovalRate()
    {
        $res = DB::connection('mysql_proxy')->table('oc_yzc_rma_order as ro')
            ->groupBy(['ro.seller_id'])
            ->selectRaw('seller_id,count(*) as amount')
            ->get()
            ->keyBy('seller_id')
            ->toArray();

        $approval = DB::connection('mysql_proxy')->table('oc_yzc_rma_order_product as op')
            ->leftJoin('oc_yzc_rma_order as ro', 'ro.id', '=', 'op.rma_id')
            ->where('ro.seller_status', 2)
            ->where(function ($query) {
                //rma_type RMA类型 1:仅重发;2:仅退款;3:即退款又重发
                //status_refund 返金状态 0:初始状态;1:同意;2:拒绝
                //status_reshipment 重发状态 0:初始;1:同意;2:拒绝
                $query->where([['rma_type', '=', 3], ['status_refund', '=', 1], ['status_reshipment', '=', 1]])
                    ->orWhere([['rma_type', '=', 2], ['status_refund', '=', 1]])
                    ->orWhere([['rma_type', '=', 1], ['status_reshipment', '=', 1]]);
            })
            ->groupBy(['ro.seller_id'])
            ->selectRaw('seller_id,count(*) as amount')
            ->get()
            ->keyBy('seller_id')
            ->toArray();
        $ret = [];
        foreach ($res as $key => $value) {
            if ($value->amount) {
                if (isset($approval[$key])) {
                    $getApproval = $approval[$key]->amount;
                } else {
                    $getApproval = 0;
                }
                $ret[$key] = sprintf('%.2f', $getApproval * 100 / $value->amount);
            } else {
                $ret[$key] = 0;
            }
        }
       return $ret;
    }
}