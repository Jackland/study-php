<?php

namespace App\Models\Statistics;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

//用户画像数据
class RebateModel extends Model
{

    public static function obj2array($obj)
    {
        if (empty($obj)) return [];
        return json_decode(json_encode($obj), true);
    }

    // 获取超时的agreemtn 列表
    public static function get_agreement_timeout()
    {
        $res = DB::connection('mysql_proxy')
            ->table('oc_rebate_agreement')
            ->whereRaw("status=1 and '" . date('Y-m-d H:i:s', time()) . "'>DATE_FORMAT(DATE_ADD(create_time,INTERVAL 1 DAY),'%Y-%m-%d %H:%i:%s')")
            ->get(['id', 'agreement_code', 'create_time']);
        return self::obj2array($res);
    }

    // 修改超时状态
    public static function set_agreement_timeout($agreement_id_list)
    {
        return DB::connection('mysql_proxy')
            ->table('oc_rebate_agreement')
            ->whereIn('id', $agreement_id_list)
            ->update(array('status' => 4));
    }

    //获取还有七天就过期的agreement 列表
    public static function get_agreement_expire_7_day()
    {
        $res = DB::connection('mysql_proxy')
            ->table('oc_rebate_agreement')
            ->where('rebate_result', '=', 1)
            ->whereRaw('CURRENT_TIMESTAMP()>DATE_SUB(expire_time,INTERVAL 7 DAY)')
            ->get(['id', 'agreement_code', 'create_time']);
        return self::obj2array($res);
    }

    //将还有7天过期的agreement的rebate_result 设置成2
    public static function set_expire_status($expire_id_list)
    {
        return DB::connection('mysql_proxy')
            ->table('oc_rebate_agreement')
            ->whereIn('id', $expire_id_list)
            ->update(array('rebate_result' => 2));
    }

    //已经到期而且状态为1,2的agreement
    public static function get_already_expire_agreement()
    {
        $res = DB::connection('mysql_proxy')
            ->table('oc_rebate_agreement')
            ->where(function ($query) {
                $query->where('rebate_result', '=', 1)->orWhere('rebate_result', '=', 2);
            })
            ->whereRaw('CURRENT_TIMESTAMP()> expire_time')
            ->get(['id', 'agreement_code', 'qty', 'day', 'seller_id', 'buyer_id', 'effect_time', 'expire_time', 'create_time']);
        return self::obj2array($res);
    }

    //查询agreement 已经卖掉的商品数量
    public static function seller_num($agreement_id_list)
    {
        //普通订单  type=1
        $order = DB::connection('mysql_proxy')
            ->table('oc_rebate_agreement_order')
            ->where('type', '=', 1)
            ->whereIn('agreement_id', $agreement_id_list)
            ->groupBy('agreement_id')
            ->select('agreement_id')
            ->selectRaw('sum(qty) as qty')
            ->get();
        $order = self::obj2array($order);
        $order = array_combine(array_column($order, 'agreement_id'), array_column($order, 'qty'));
        //RMA 订单
        $rma_order = DB::connection('mysql_proxy')
            ->table('oc_rebate_agreement_order')
            ->where('type', '=', 2)
            ->whereIn('agreement_id', $agreement_id_list)
            ->groupBy('agreement_id')
            ->select('agreement_id')
            ->selectRaw('sum(qty) as qty')
            ->get();
        $rma_order = self::obj2array($rma_order);
        $rma_order = array_combine(array_column($rma_order, 'agreement_id'), array_column($rma_order, 'qty'));
        //数据操作，相减
        $rtn = array();
        foreach ($order as $k => $v) {
            $rtn[$k] = $v - ((isset($rma_order[$k])) ? $rma_order[$k] : 0);
        }
        return $rtn;
    }

    //设置agreement 成功或失败状态
    public static function set_agreement_status($id_list, $status)
    {
        if ($id_list) {
            return DB::connection('mysql_proxy')
                ->table('oc_rebate_agreement')
                ->whereIn('id', $id_list)
                ->update(array('rebate_result' => $status));
        }
    }


    // 获取店铺名称
    public static function get_store_name_list($seller_id_list)
    {
        $res = DB::connection('mysql_proxy')
            ->table('oc_customerpartner_to_customer')
            ->whereIn('customer_id', $seller_id_list)
            ->get(['customer_id', 'screenname']);
        return self::obj2array($res);
    }


    //获取协议对应的product 信息
    public static function get_agreement_product_info($agreement_id_list)
    {
        $res = DB::connection('mysql_proxy')
            ->table('oc_rebate_agreement_item as ai')
            ->leftJoin('oc_product as p', 'ai.product_id', '=', 'p.product_id')
            ->whereIn('agreement_id', $agreement_id_list)
            ->select(['ai.agreement_id', 'p.product_id', 'p.sku', 'p.mpn'])
            ->get();
        return self::obj2array($res);
    }


    /*****
     * rebate_remind
     * 2020年2月14日
     * zjg
     * @param $country_id
     * @param $type
     * @return array|mixed
     */
    //获取某个地区seller 的agreement
    public static function get_agreement_by_country($country_id, $type)
    {
        $res = DB::connection('mysql_proxy')
            ->table('oc_rebate_agreement as a')
            ->leftJoin('oc_customer as oc', 'a.' . $type, '=', 'oc.customer_id')
            ->where('oc.country_id', '=', $country_id)
            ->where('a.status', '=', 3)
            ->where(function (Builder $query) {
                $query->where('rebate_result', '=', 1)
                    ->orWhere('rebate_result', '=', 2);
            })
            ->get(['a.id', 'a.agreement_code', 'a.seller_id', 'a.buyer_id', 'a.expire_time', 'a.day', 'a.qty', 'a.rebate_result']);
        return self::obj2array($res);
    }


}