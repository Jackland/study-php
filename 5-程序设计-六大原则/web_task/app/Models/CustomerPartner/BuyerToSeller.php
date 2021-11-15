<?php

namespace App\Models\CustomerPartner;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class BuyerToSeller extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = 'oc_buyer_to_seller';
    }

    public function getBTSList($startID, $endID = 0, $page = 1)
    {
        return DB::table($this->table . ' as cts')
            ->where('id', '>=', $startID)
            ->when(!empty($endID), function (Builder $query) use ($endID) {
                $query->where('id', '<=', $endID);
            })
            ->forPage($page, 200)
            ->get(['seller_id', 'buyer_id', 'id']);
    }

    /**
     * @param int $seller_id
     * @param int $buyer_id
     * @return float
     */
    public function sumPurchaseOrderMoney($seller_id, $buyer_id)
    {
        $obj = DB::table('oc_customerpartner_to_order as cto')
            ->join('oc_order_product as op', 'op.order_product_id', '=', 'cto.order_product_id')
            ->join('oc_order as o', 'o.order_id', '=', 'cto.order_id')
            ->leftJoin('oc_product_quote as pq', [['pq.order_id', '=', 'cto.order_id'], ['pq.product_id', '=', 'cto.product_id']])
            ->selectRaw('sum((op.price+op.service_fee_per)*op.quantity-IfNULL(pq.amount,0)) as total')
            ->where([
                ['cto.customer_id', '=', $seller_id],
                ['o.customer_id', '=', $buyer_id]
            ])
            ->first();
        if (empty($obj) || empty($obj->total)) {
            return 0;
        } else {
            return $obj->total;
        }
    }

    public function countPurchaseOrder($seller_id, $buyer_id)
    {
        return DB::table('oc_customerpartner_to_order as cto')
            ->join('oc_order as o', 'o.order_id', '=', 'cto.order_id')
            ->where([
                ['cto.customer_id', '=', $seller_id],
                ['o.customer_id', '=', $buyer_id]
            ])
            ->count();
    }

    public function sumRMAMoney($seller_id, $buyer_id)
    {
        return DB::table('oc_yzc_rma_order as o')
            ->join('oc_yzc_rma_order_product as op', 'op.rma_id', '=', 'o.id')
            ->where([
                ['o.seller_id', '=', $seller_id],
                ['o.buyer_id', '=', $buyer_id],
                ['op.status_refund', '=', 1]
            ])
            ->sum('op.actual_refund_amount');
    }

    public function countMargin($seller_id, $buyer_id)
    {
        return DB::table('tb_sys_margin_agreement AS a')
            ->join('tb_sys_margin_process AS p', 'p.margin_id', '=', 'a.id')
            ->where([
                ['a.buyer_id', '=', $buyer_id],
                ['a.seller_id', '=', $seller_id]
            ])
            ->whereNotNull('p.advance_order_id')
            ->count();

    }

    public function sumMarginMoney($seller_id, $buyer_id)
    {
        $advanceObj = DB::table('tb_sys_margin_agreement AS a')
            ->join('tb_sys_margin_process AS p', 'p.margin_id', '=', 'a.id')
            ->join('oc_order_product as op', [
                ['op.order_id', '=', 'p.advance_order_id'], ['op.product_id', '=', 'p.advance_product_id']
            ])
            ->where([
                ['a.buyer_id', '=', $buyer_id],
                ['a.seller_id', '=', $seller_id]
            ])
            ->selectRaw(
                'sum( ( op.price + IFNULL( op.service_fee_per, 0 ) ) * op.quantity ) AS total'
            )
            ->first();

        $restObj = DB::table('tb_sys_margin_agreement AS a')
            ->join('tb_sys_margin_process AS p', 'p.margin_id', '=', 'a.id')
            ->join('tb_sys_margin_order_relation AS r', 'r.margin_process_id', '=', 'p.id')
            ->join('oc_order_product as opp', [
                ['opp.order_id', '=', 'r.rest_order_id'], ['opp.product_id', '=', 'p.rest_product_id']
            ])
            ->where([
                ['a.buyer_id', '=', $buyer_id],
                ['a.seller_id', '=', $seller_id]
            ])
            ->selectRaw(
                'sum( ( opp.price + IFNULL( opp.service_fee_per, 0 ) ) * opp.quantity ) AS total'
            )
            ->first();
        return ($advanceObj->total ?? 0) + ($restObj->total ?? 0);
    }

    /**
     * @param $seller_id
     * @param $buyer_id
     * @return mixed|string
     */
    public function getLastTime($seller_id, $buyer_id)
    {
        $purchaseObj = DB::table('oc_customerpartner_to_order as cto')
            ->join('oc_order as o', 'o.order_id', '=', 'cto.order_id')
            ->where([
                ['cto.customer_id', '=', $seller_id],
                ['o.customer_id', '=', $buyer_id]
            ])
            ->select(['cto.date_added'])
            ->orderBy('cto.date_added', 'DESC')
            ->first();

        $time = '';
        if (!empty($purchaseObj)) {
            $time = $purchaseObj->date_added;
        }
        return $time;
    }

    public function updateTransaction($id, $data)
    {
        if (empty($id) || empty($data)) {
            return;
        }
        DB::table($this->table)->where('id', $id)->update($data);
    }

}
