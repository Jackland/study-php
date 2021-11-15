<?php

namespace App\Models\Statistics;

use App\Console\Commands\OnlineStatistic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RequestRecord extends Model
{

    protected $connection='mysql_proxy';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /**
     * 获取前一日 在线的平台供应商
     *
     * @return \Illuminate\Support\Collection
     */
    public function getLastDayOnlineForSeller()
    {
        // 有库存的
        $sellerIdsSubQuery = DB::table('tb_sys_batch as b')
            ->leftJoin('oc_product as p', 'p.product_id', 'b.product_id')
            ->where('b.onhand_qty', '>', 0)
            ->where([
                'p.status' => 1,
                'p.buyer_flag' => 1,
                'p.is_deleted' => 0,
                'p.product_type' => 0,
            ])
            ->select('b.customer_id')
            ->groupBy(['b.customer_id']);
        // 在线的用户
        $lastDayTable = 'oc_request_log_' . date('Ym', strtotime("-1 day"));
        $requestCustomerIdsSubQuery = DB::table($lastDayTable . ' as req')
            ->whereBetween('req.add_date',
                [date('Y-m-d 00:00:00', strtotime("-1 day")), date('Y-m-d 23:59:59', strtotime("-1 day"))]
            )
            ->select('req.customer_id')
            ->groupBy(['req.customer_id']);
        $objs = DB::table('oc_customerpartner_to_customer as ctc')
            ->join('oc_customer as c', 'c.customer_id', '=', 'ctc.customer_id')
            ->join('oc_country as ct', 'ct.country_id', '=', 'c.country_id')
            ->select([
                'ctc.customer_id', 'ctc.screenname', 'c.accounting_type','c.nickname','c.firstname','c.lastname',
                'c.country_id', 'ct.iso_code_2 as country_name',
            ])
            ->where([
                ['c.status', '=', 1]
            ])
            ->whereIn('c.accounting_type', [1, 2])
            ->whereIn('ctc.customer_id', $sellerIdsSubQuery)
            ->whereNotIn('ctc.customer_id', $requestCustomerIdsSubQuery)
            ->distinct()
            ->orderBy('c.accounting_type', 'ASC')
            ->orderBy('c.country_id', 'DESC')
            ->get();
        return $objs;

    }

    /**
     * 获取前一日 在线的平台买家
     *
     * @return \Illuminate\Support\Collection
     */
    public function getLastDayOnlineForBuyer()
    {
        $lastDayTable = 'oc_request_log_' . date('Ym', strtotime("-1 day"));
        $objs = DB::table('oc_customer as c')
            ->join($lastDayTable . ' as req', 'req.customer_id', '=', 'c.customer_id')
            ->join('oc_country as ct', 'ct.country_id', '=', 'c.country_id')
            ->leftJoin('tb_sys_buyer_account_manager as m', 'm.BuyerId', '=', 'c.customer_id')
            ->leftJoin('oc_customer as b', 'b.customer_id', '=', 'm.AccountId')
            ->select([
                'c.customer_id', 'c.nickname', 'c.user_number', 'c.accounting_type', 'c.firstname', 'c.lastname',
                'c.country_id', 'ct.iso_code_2 as country_name',
                'b.customer_id as bd_id', 'b.firstname as bd_firstname', 'b.lastname as bd_lastname',
            ])
            ->where([
                ['c.status', '=', 1],
                ['c.customer_group_id', '<>', 23]
            ])
            ->whereBetween('req.add_date',
                [date('Y-m-d 00:00:00', strtotime("-1 day")), date('Y-m-d 23:59:59', strtotime("-1 day"))]
            )
            ->whereIn('c.accounting_type', [1, 2])
            ->whereNotExists(function ($query) {
                $query->from('oc_customerpartner_to_customer as seller')
                    ->select(['seller.customer_id'])
                    ->whereRaw('seller.customer_id = c.customer_id');
            })
            ->distinct()
            ->orderBy('c.accounting_type', 'ASC')
            ->orderBy('c.country_id', 'DESC')
            ->get();
        return $objs;
    }

    /**
     * @param $seller_id
     * @param $groupId
     * @return array
     */
    public function getAccountManager($seller_id, $groupId)
    {
        return DB::table('oc_buyer_to_seller as bts')
            ->leftJoin('oc_customer as c','c.customer_id','bts.buyer_id')
            ->leftJoin('oc_sys_user_to_customer as tc','c.customer_id','tc.account_manager_id')
            ->leftJoin('tb_sys_user as su','tc.user_id','=','su.id')
            ->where('c.customer_group_id', $groupId)
            ->whereNotIn('c.customer_id',OnlineStatistic::HIGH_MANAGE_IDS)
            ->where('bts.seller_id', $seller_id)
            ->selectRaw('su.username')
            ->pluck('username')
            ->toArray();
    }

    /**
     * @param $groupId
     * @return array
     */
    public function getAccountsByGroupId($groupId)
    {
        return DB::table('oc_sys_user_to_customer as tc')
            ->leftJoin('tb_sys_user as su','tc.user_id','=','su.id')
            ->select('su.username', 'tc.account_manager_id')
            ->get()
            ->keyBy('account_manager_id')
            ->toArray();
    }

}
