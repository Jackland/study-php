<?php

namespace App\Models\Statistics;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use function foo\func;

//用户画像数据
class UserPortraitModel extends Model
{
    protected $connection = 'mysql_proxy';
    protected $table = 'oc_buyer_user_portrait';
    protected $fillable = ['buyer_id', 'monthly_sales_count', 'total_amount_platform', 'total_amount_returned', 'total_amount_refund', 'return_rate_value',
        'return_rate', 'order_count_platform', 'order_count_rebate', 'total_amount_margin', 'complex_complete_rate_value', 'complex_complete_rate',
        'first_order_date', 'registration_date', 'create_user_name', 'program_code', 'main_category_id'];

    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';

    /**
     * 获取成交笔数 首单时间
     * @param int $customer_id
     * @return array
     */
    public static function get_order_data($customer_id = 0)
    {
        $build = DB::table('oc_order');
        if ($customer_id) {
            $build->where('customer_id', $customer_id);
        }
        $res = $build->where('order_status_id', 5)
            ->selectRaw('customer_id,count(*) as num ,min(date_added) as first_order_date')
            ->groupBy('customer_id')
            ->get();
        return (array)$res->toArray();
    }

    /**
     * 获取近30天销售笔数
     * @param int $customer_id
     * @return array
     */
    public static function get_order_count_near_30($customer_id = 0)
    {
        $build = DB::table('oc_order as o')
            ->leftJoin('oc_order_product as op', 'op.order_id', '=', 'o.order_id')
            ->where('o.order_status_id', '=', '5')
            ->where('o.date_added', '>', date('Y-m-d H:i:s', time() - 3600 * 24 * 30))
            ->select(['customer_id'])
            ->selectRaw('sum(op.quantity) AS sum')
            ->groupBy('o.customer_id');
        if ($customer_id) {
            $build->where('customer_id', $customer_id);
        }

        return (array)$build->get()->toArray();
    }

    /**
     * 某个客户某些类别下订单购买数量
     * @param $customerId
     * @param $categoryHighestMap
     * @return array
     */
    public static function historyOrderCustomerHighestCategoryProductNum($categoryHighestMap, $customerId = 0)
    {
        $highestCategoryCustomerIdNumMap = [];
        foreach ($categoryHighestMap as $highestCategory => $categories) {
            $customerNumMap = \DB::table('oc_order as o')
                ->join('oc_order_product as op', 'op.order_id', '=', 'o.order_id')
                ->join('oc_product_to_category as pc', 'op.product_id', '=', 'pc.product_id')
                ->join('oc_product as p', 'p.product_id', '=', 'op.product_id')
                ->where('p.product_type', 0)
                ->where('o.order_status_id', '=', '5')
                ->whereIn('pc.category_id', $categories)
                ->when($customerId != 0, function ($q) use ($customerId) {
                    $q->where('o.customer_id', $customerId);
                })
                ->groupBy(['o.customer_id'])
                ->selectRaw("o.customer_id, sum(op.quantity) as num")
                ->get()
                ->pluck('num', 'customer_id')
                ->toArray();

            $highestCategoryCustomerIdNumMap[$highestCategory] = $customerNumMap;
        }

        return $highestCategoryCustomerIdNumMap;
    }

    /**
     * 所有最高级对应的所有子分类包含自己 (Furniture分类取到二级，其他分类取到一级)
     * @return array
     */
    public static function categoryHighestMap()
    {
        $map = [];
        $categories = DB::table('oc_category')->whereIn('parent_id', [0, 255])->where('category_id', '!=', 255)->get();
        foreach ($categories as $category) {
            $map[$category->category_id][] = $category->category_id;
            self::recursionSelectChild($category->category_id, $category->category_id, $map);
        }
        $map[255][] = 255;

        return $map;
    }

    /**
     * @param $parentId
     * @param $highestCategoryId
     * @param $map
     */
    public static function recursionSelectChild($parentId, $highestCategoryId, &$map)
    {
        $categories = DB::table('oc_category')->where('parent_id', $parentId)->get();
        foreach ($categories as $category) {
            $map[$highestCategoryId][] = $category->category_id;
            self::recursionSelectChild($category->category_id, $highestCategoryId, $map);
        }
    }

    /**
     * 获取全部成交金额
     * @param int $customer_id
     * @return mixed
     */
    public static function sell_price($customer_id = 0)
    {

        $build = DB::table('oc_order as o')
            ->join('oc_order_product as op', function ($join) {
                $join->on('o.order_id', '=', 'op.order_id');
            })
            ->leftJoin(
                'oc_product_quote as q', function ($query) {
                $query->on([['o.order_id', '=', 'q.order_id'],
                    ['op.product_id', '=', 'q.product_id']])
                    ->where('q.status', '=', 3);
            })
            ->select(['o.customer_id'])
            ->selectRaw('sum(`op`.`quantity`*(`op`.`price`+`op`.`service_fee`+`op`.`freight_per`+`op`.`package_fee`) - IFNULL(q.amount,0)) as total ')
            ->where('o.order_status_id', '=', '5')
            ->groupBy('o.customer_id');
        if ($customer_id) {
            $build->where('o.customer_id', $customer_id);
        }
        return (array)$build->get()->toArray();
    }

    /*
     * 获取退货总金额
     */
    public static function refund_price($customer_id = 0)
    {
        $build = DB::table('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->leftJoin('tb_sys_customer_sales_order as cso', 'cso.order_id', '=', 'ro.from_customer_order_id')
            ->where(function ($query) {
                $query->where('cso.order_status', '=', '16');
                $query->orwhere('ro.order_type', '=', '2');
            })
            ->where('rop.status_refund', '=', '1')
            ->select('ro.buyer_id')
            ->selectRaw('sum(rop.actual_refund_amount) as sum')
            ->groupBy('ro.buyer_id');
        if ($customer_id) {
            $build->where('ro.buyer_id', '=', $customer_id);
        }
        return (array)$build->get()->toArray();
    }


    /**
     * 获取返金总金额
     * @param $customer_id
     * @return array
     */
    public static function return_price($customer_id = 0)
    {
        $build = DB::table('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->leftJoin('tb_sys_customer_sales_order as cso', 'cso.order_id', '=', 'ro.from_customer_order_id')
            ->where([['cso.order_status', '=', 32], ['rop.status_refund', '=', 1]])
            ->select('ro.buyer_id')
            ->selectRaw('sum(rop.actual_refund_amount) as sum')
            ->groupBy('ro.buyer_id');
        if ($customer_id) {
            $build->where('ro.buyer_id', '=', $customer_id);
        }
        return (array)$build->get()->toArray();
    }

    /**
     * 保证金协议总金额
     * @param $customer_id
     * @return array
     */
    public static function margin_agreement($customer_id = 0)
    {
        $build = DB::table('tb_sys_margin_agreement')
            ->where('status', '=', 8)
            ->select('buyer_id')
            ->selectRaw('price * num as agreement_price');
        if ($customer_id) {
            $build->where('buyer_id', '=', $customer_id);
        }
        return (array)$build->get()->toArray();
    }

    /**
     * 保证金协议头款
     * @param $customer_id
     * @return array
     */
    public static function margin_head($customer_id = 0)
    {
        //最内层，获取agreement_id
        $agreement_id_build = DB::table('tb_sys_margin_agreement as ma')
            ->leftJoin('tb_sys_margin_process as mp', 'ma.id', '=', 'mp.margin_id')
            ->leftJoin('oc_order_product as op', 'mp.rest_product_id', '=', 'op.product_id')
            ->leftJoin('oc_order as o', 'op.order_id', '=', 'o.order_id')
            ->where('status', '=', 8)
            ->where('o.order_status_id', '=', '5')
            ->select('ma.buyer_id', 'ma.num', 'ma.agreement_id')
            ->groupBy('ma.id')
            ->havingRaw('sum( op.quantity)  >= ma.num');
        if ($customer_id) {
            $agreement_id_build->where('buyer_id', '=', $customer_id);
        }
        //第二层  选取agreement_id 用于where in
        $agreement_id_build_use_in = DB::table(DB::raw("({$agreement_id_build->toSql()}) as t"))
            ->mergeBindings($agreement_id_build)
            ->select('agreement_id');

        //第三层 获取头款
        $build = DB::table('tb_sys_margin_agreement as ma')
            ->leftJoin('tb_sys_margin_process as mp', 'ma.id', '=', 'mp.margin_id')
            ->leftJoin('oc_order as o', 'mp.advance_order_id', '=', 'o.order_id')
            ->leftJoin('oc_order_product as op', [['mp.advance_product_id', '=', 'op.product_id'], ['o.order_id', '=', 'op.order_id']])
//            ->where('status', '=', 8)
//            ->where('o.order_status_id', '=', '5')
            ->whereIn('ma.agreement_id', function ($query) use ($agreement_id_build_use_in) {
                $query->from(DB::raw("({$agreement_id_build_use_in->toSql()}) as q"))
                    ->mergeBindings($agreement_id_build_use_in);
            })
            ->mergeBindings($agreement_id_build_use_in)
            ->select('ma.buyer_id')
            ->selectRaw('sum(( op.price + op.service_fee_per + op.freight_per+op.package_fee) * op.quantity) as sum')
            ->groupBy('ma.buyer_id');

        return (array)$build->get()->toArray();
    }

    /**
     * 保证金协议尾款
     * @param int $customer_id
     * @return array
     */
    public static function margin_end($customer_id = 0)
    {
        //内层
        $build = DB::table('tb_sys_margin_agreement as ma')
            ->leftJoin('tb_sys_margin_process as mp', 'ma.id', '=', 'mp.margin_id')
            ->leftJoin('oc_order_product as op', 'mp.rest_product_id', '=', 'op.product_id')
            ->leftJoin('oc_order as o', 'op.order_id', '=', 'o.order_id')
            ->where('status', '=', 8)
            ->where('o.order_status_id', '=', '5')
            ->select('ma.buyer_id', 'ma.num')
            ->selectRaw('sum(( op.price + op.service_fee_per + op.freight_per+op.package_fee) * op.quantity) as sum')
            ->groupBy('ma.id')
            ->havingRaw('sum( op.quantity)  >= ma.num');
        if ($customer_id) {
            $build->where('buyer_id', '=', $customer_id);
        }
        //外层
        $out_build = DB::table(DB::raw("({$build->toSql()}) as t"))
            ->mergeBindings($build)
            ->select(['buyer_id'])
            ->selectRaw('sum( sum ) as sum')
            ->groupBy('buyer_id');
        return (array)$out_build->get()->toArray();
    }


    /**
     * 获取用户注册时间
     * @param $customer_id
     * @return array
     */
    public static function get_user_regiest_time($customer_id = 0)
    {
        $build = DB::table('oc_customer')
            ->whereNotIn('customer_id', function ($query) {
                $query->select('customer_id')->from('oc_customerpartner_to_customer');
            })
            ->where('status', '=', 1)
            ->select(['customer_id', 'date_added']);
        if ($customer_id) {
            $build->where('customer_id', '=', $customer_id);
        }
        return $build->get()->toArray();
    }

    /**
     * 返点参与度
     */
    public static function return_point($customer_id = 0)
    {
        $build = DB::table('tb_sys_rebate_contract')
            ->where('status', '=', 3)
            ->select('buyer_id')
            ->selectRaw('count(*) as num')
            ->groupBy('buyer_id');
        if ($customer_id) {
            $build->where('buyer_id', '=', $customer_id);
        }
        return $build->get()->toArray();
    }


}
