<?php

namespace App\Models\Statistics;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

const CREATE_USER_ONE_CUSTOMER = 'command';   //统计单customer数据
const CREATE_USER_ALL_CUSTOMER = 'schedule';  //统计全体customer数据
class SellCountModel extends Model
{
    protected $connection='mysql_proxy';
    protected $table = 'tb_sys_product_sales';
    protected $fillable = ['product_id', 'quantity_7', 'quantity_14', 'quantity_30', 'quantity_all', 'start_time', 'end_time', 'memo', 'create_user_name', 'create_time', 'update_user_name', 'update_time'];

    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';


    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /**
     * @param $obj
     * @return array
     */
    public static function obj2array($obj)
    {
        if (empty($obj)) return [];
        return json_decode(json_encode($obj), true);
    }

    public static function get_product_sell_count($product_id, $days = array(0))
    {
        $rtn = array();
        foreach ($days as $day) {
            $start_time = date('Y-m-d H:i:s', time() - $day * 3600 * 24);
            echo $day . '天的起始时间:' . $start_time . PHP_EOL;
            //全部商品--包括保证金头款和尾款商品
            $all_sell = \DB::table('oc_customerpartner_to_order AS cto')
                ->leftJoin('oc_customer AS c', 'c.customer_id', '=', 'cto.customer_id')
                ->where('c.status', '=', 1)
                ->whereIn('cto.order_product_status', [5, 13]);
            if ($day > 0) {
                $all_sell->where('cto.date_added', '>', $start_time);
            }
            if ($product_id) {
                $all_sell->where('cto.product_id', '=', $product_id);
            }
            $all_sell = $all_sell->groupBy('cto.product_id')
                ->select(['cto.product_id'])
                ->selectRaw(' sum( cto.quantity )  as sum')
                ->get();
            $all_sell = self::obj2array($all_sell);
            $all_sell = array_combine(array_column($all_sell, 'product_id'), $all_sell);
            //保证金尾款商品
            //$margin = DB::table('tb_sys_margin_agreement as ma')
            //    ->join('tb_sys_margin_process as mp', 'ma.id', '=', 'mp.margin_id')
            //    ->join('oc_order_product as op', 'mp.rest_product_id', '=', 'op.product_id')
            //    ->join('oc_order as o', 'op.order_id', '=', 'o.order_id')
            //    ->where('o.order_status_id', 5)
            //    ->where('ma.status', '>=', 6);
            //if ($day > 0) {
            //    $margin->where('o.date_modified', '>', $start_time);
            //}
            //if ($product_id) {
            //    $margin->where('ma.product_id', '=', $product_id);
            //}
            //$margin = $margin->groupBy('ma.product_id')
            //    ->select(['ma.product_id'])
            //    ->selectRaw('sum(op.quantity) as sum')
            //    ->get();
            // 保证金尾款需要保证尾款产品和头款产品不相等
            // cross join 不然条数不对
            $margin = \DB::table('tb_sys_margin_agreement as ma')
                ->crossjoin('tb_sys_margin_process as mp', 'ma.id', '=', 'mp.margin_id')
                ->crossjoin('oc_order_product as op',
                    [
                        ['ma.product_id', '!=', 'op.product_id'],
                        ['ma.id', '=', 'op.agreement_id'],
                        ['mp.advance_product_id', '!=', 'op.product_id'],
                        ])
                ->crossjoin('oc_order as o', 'op.order_id', '=', 'o.order_id')
                ->where('o.order_status_id', 5)
                ->where('op.type_id', 2)
                ->where('ma.status', '>=', 6);
            if ($day > 0) {
                $margin->where('o.date_modified', '>', $start_time);
            }
            if ($product_id) {
                $margin->where('ma.product_id', '=', $product_id);
            }
            $margin = $margin->groupBy('ma.product_id')
                ->select(['ma.product_id'])
                ->selectRaw('sum(op.quantity) as sum')
                ->get();
            $margin = self::obj2array($margin);
            $margin = array_combine(array_column($margin, 'product_id'), $margin);
            $rtn[$day] = array('all' => $all_sell, 'margin' => $margin);
        }
        return $rtn;
    }

    //统计近XX天的销售量
    public static function sell_count($product_id)
    {
        $days = array(0, 7, 14, 30);
        $res = self::get_product_sell_count($product_id, $days);
        // 统计数据
        // 将保证金尾款数据加回原店铺
        $rtn = array();
        foreach ($days as $d) {
            $sell_tmp=array_column($res[$d]['all'],'product_id');
            $margin_tmp=array_column($res[$d]['margin'],'product_id');
            $all_tmp=array_unique(array_merge($sell_tmp,$margin_tmp));
            foreach ($all_tmp as $k) {  //k:product_id
                if (!isset($rtn[$k])) {
                    foreach ($days as $dd) {
                        $rtn[$k][$dd] = 0;
                    }
                }

                $product_num =isset($res[$d]['all'][$k]) ? $res[$d]['all'][$k]['sum'] : 0;
                $margin_num = isset($res[$d]['margin'][$k]) ? $res[$d]['margin'][$k]['sum'] : 0;
                $rtn[$k][$d] = $product_num + $margin_num;
            }
        }
        // 组织数据
        $db_data = array();
        $curr_time = date('Y-m-d H:i:s', time());
        $creater = $product_id ? CREATE_USER_ONE_CUSTOMER : CREATE_USER_ALL_CUSTOMER;
        foreach ($rtn as $k => $v) {
            $product_id = $k;
            $db_data[$product_id] = array(
                'product_id' => array(
                    'product_id' => $product_id
                ),
                'data' => array(
                    'end_time' => $curr_time,
//                    'is_deleted' => 0,
                    'create_user_name' => $creater
                )
            );
            foreach ($days as $d) {
                if ($d == 0) {
                    $db_data[$product_id]['data']['quantity_all'] = $rtn[$product_id][$d];
                } else {
                    $db_data[$product_id]['data']['quantity_' . $d] = $rtn[$product_id][$d];
                }

            }
        }
        //  重置状态
//        DB::table('tb_sys_product_sales')->update(array('is_deleted' => 1));
        //  入库
        foreach ($db_data as $k => $v) {
            extract($v);
            $show_txt = date('Y-m-d H:i:s');
            $show_txt .= ' 更新数据：product_id-' . $product_id['product_id'];
            foreach ($days as $d) {
                if ($d == 0) {
                    $show_txt .= '；总量为：' . $data['quantity_all'];
                } else {
                    $show_txt .= '；' . $d . '天的量为：' . $data['quantity_' . $d];
                }
            }
            $show_txt .= PHP_EOL;
            echo $show_txt;
            self::updateOrCreate($product_id, $data);
        }
    }
}