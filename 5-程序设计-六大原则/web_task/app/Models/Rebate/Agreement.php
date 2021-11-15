<?php

namespace App\Models\Rebate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Class Agreement
 * @package App\Models\Rebate
 */
class Agreement extends Model
{

    protected $table = 'oc_rebate_agreement';
    protected $item_table = 'oc_rebate_agreement_item';

    /**
     * @param $keyVal
     */
    public function insertSingle($keyVal)
    {
        \DB::connection('mysql_proxy')->table($this->table)
            ->insert($keyVal);
    }

    /**
     * @param $keyVal
     */
    public function insertItem($keyVal)
    {
        \DB::connection('mysql_proxy')->table($this->item_table)
            ->insert($keyVal);
    }

    /**
     * 注：因为 tb_sys_outer_storeid_to_sellerid 可能存在多条关于buyer的数据，
     * 为了不降低查询效率，不适用子查询/分组
     * 导致查询出来的结果集 可能存在相同且有效的重复数据，需要在程序中手动处理(重复数据一般很少)
     *
     * @param string $start_time
     * @param string $end_time
     * @param array $notInGroup
     * @return \Illuminate\Support\Collection
     */
    public function getRebateRecharge(string $start_time, string $end_time, array $notInGroup)
    {
        return \DB::connection('mysql_proxy')
            ->table($this->table . ' as ra')
            ->leftJoin('tb_sys_credit_line_amendment_record as r', function ($join) {
                $join->on('r.customer_id', '=', 'ra.buyer_id')
                    ->on('r.header_id', '=', 'ra.id')
                    ->where('r.type_id', '=', 4);
            })
            ->leftJoin('oc_virtual_pay_record as vr', function ($join) {
                $join->on('vr.customer_id', '=', 'ra.buyer_id')
                    ->on('vr.relation_id', '=', 'ra.id')
                    ->where('vr.type', '=', 3);
            })
            ->leftJoin('oc_customer as seller', 'seller.customer_id', '=', 'ra.seller_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'seller.customer_id')
            ->leftJoin('oc_customer as buyer', 'buyer.customer_id', '=', 'ra.buyer_id')
            ->leftJoin('tb_sys_outer_storeid_to_sellerid as osts','osts.self_buyer_id','=','ra.buyer_id')
            ->where([
                ['ra.status', '=', 3],
                ['ra.rebate_result', '=', 7],
            ])
            ->where(function($query) use ($start_time, $end_time) {
                $query->whereBetween('r.date_added', [$start_time, $end_time])
                    ->orWhereBetween('vr.create_time', [$start_time, $end_time]);
            })
            ->whereNotIn('seller.customer_group_id', $notInGroup)
            ->select([
                'ra.id',
                'seller.firstname as seller_firstname', 'seller.lastname as seller_lastname', 'seller.user_number as seller_user_number',
                'buyer.firstname as buyer_firstname', 'buyer.lastname as buyer_lastname', 'buyer.user_number as buyer_user_number',
                'ra.agreement_code',
                'r.new_line_of_credit', 'old_line_of_credit', 'ra.update_time as date_added',
                'vr.amount',
                'ctc.screenname', 'seller.country_id','seller.accounting_type',
                'osts.self_buyer_id',
            ])
            ->orderBy('seller.country_id', 'desc')
            ->orderBy('ra.update_time', 'ASC')
            ->get();
    }

}
