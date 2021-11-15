<?php

namespace App\Models\Margin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MarginProcess extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /**
     * 根据协议编号获取协议详细信息
     *
     * @param $agreement_id tb_sys_margin_agreement.agreement_id
     * @param $seller_id tb_sys_margin_agreement.seller_id
     * @param null $agreement_key tb_sys_margin_agreement.id
     * @return mixed
     */
    public function getMarginAgreementDetail($agreement_id = null, $seller_id = null, $agreement_key = null)
    {
        if (isset($agreement_id) || isset($agreement_key)) {
            $where_clause = [];
            if (isset($agreement_id)) {
                $where_clause[] = ['ma.agreement_id', '=', $agreement_id];
            }
            if (isset($agreement_key)) {
                $where_clause[] = ['ma.id', '=', $agreement_key];
            }
            if (isset($seller_id)) {
                $where_clause[] = ['ma.seller_id', '=', $seller_id];
            }
            $ret = DB::table('tb_sys_margin_agreement as ma')
                ->join('oc_product as p','ma.product_id', '=', 'p.product_id')
                ->join('oc_customer as buyer','ma.buyer_id', '=', 'buyer.customer_id')
                ->join('oc_customerpartner_to_customer as ctc','ma.seller_id', '=', 'ctc.customer_id')
                ->leftJoin('tb_sys_margin_agreement_status as mas','ma.status', '=', 'mas.margin_agreement_status_id')
                ->select([
                    'ma.id',
                    'ma.agreement_id',
                    'ma.seller_id',
                    'ctc.screenname AS seller_name',
                    'ma.day',
                    'ma.num',
                    'ma.price AS unit_price',
                    'ma.money AS sum_price',
                    'ma.period_of_application',
                    'ma.create_time AS applied_time',
                    'ma.update_time AS update_time',
                    'ma.status',
                    'ma.effect_time',
                    'ma.expire_time',
                    'mas.name AS status_name',
                    'mas.color AS status_color',
                    'p.sku',
                    'p.mpn',
                    'p.quantity AS available_qty',
                    'buyer.customer_id AS buyer_id',
                    'buyer.nickname',
                    'buyer.user_number',
                    'buyer.customer_group_id'
                ])
                ->where($where_clause)
                ->first();
            return $ret;
        }
    }

    /**
     * 获取审批超时的保证金记录
     *
     * @return \Illuminate\Support\Collection
     */
    public function getApproveTimeoutMarginDetail()
    {
        $ret = DB::table('tb_sys_margin_agreement as ma')
            ->select(['ma.id', 'ma.agreement_id', 'ma.seller_id', 'ma.buyer_id', 'ma.product_id','ma.status'])
            ->whereIn('ma.status', [1, 2])
            ->whereRaw('TIMESTAMPDIFF(DAY, update_time, NOW()) >= `period_of_application`')
            ->get();
        return $ret;
    }

    /**
     * 获取订金支付超时的保证金记录
     *
     * @return \Illuminate\Support\Collection
     */
    public function getDepositPayTimeoutMarginDetail()
    {
        $ret = DB::table('tb_sys_margin_agreement as ma')
            ->join('tb_sys_margin_process as mp', 'ma.id', '=', 'mp.margin_id')
            ->select(['ma.id', 'ma.agreement_id', 'ma.seller_id', 'ma.buyer_id', 'mp.advance_product_id','ma.num'])
            ->where([
                ['ma.status', '<>', '5'],
                ['mp.process_status', '=', '1'],
                ['mp.create_time', '<', date('Y-m-d H:i:s', strtotime("-1 day"))]
            ])
            ->get();
        return $ret;
    }

    /**
     * 获取保证金即将过期的记录
     *
     * @return \Illuminate\Support\Collection
     */
    public function getSoldWillExpireMarginDetail()
    {
        $ret = DB::table('tb_sys_margin_agreement as ma')
            ->join('tb_sys_margin_process as mp', 'ma.id', '=', 'mp.margin_id')
            ->join('oc_product as p', 'p.product_id', '=', 'mp.rest_product_id')
            ->join('oc_customer as buyer', 'buyer.customer_id', '=', 'ma.buyer_id')
            ->join('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ma.seller_id')
            ->select([
                'ma.id',
                'ma.agreement_id',
                'ma.seller_id',
                'ctc.screenname',
                'ma.buyer_id',
                'buyer.nickname',
                'buyer.user_number',
                'ma.num',
                'ma.effect_time',
                'ma.expire_time',
                'mp.rest_product_id',
                'p.sku',
                'p.mpn',
                'p.quantity'
            ])
            ->where([
                ['ma.status', '=', '6'],
                ['ma.expire_time', '>', date('Y-m-d H:i:s', time())],
                ['ma.expire_time', '<', date('Y-m-d H:i:s', strtotime("+7 day"))]
            ])
            ->get();
        return $ret;
    }

    /**
     * 获取过期的现货协议
     * @return \Illuminate\Support\Collection
     */
    public function getSoleExpireAgreements()
    {
        return DB::table('tb_sys_margin_agreement as ma')
            ->join('tb_sys_margin_process as mp', 'ma.id', '=', 'mp.margin_id')
            ->join('oc_product as p', 'p.product_id', '=', 'mp.rest_product_id')
            ->join('oc_customer as buyer', 'buyer.customer_id', '=', 'ma.buyer_id')
            ->join('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ma.seller_id')
            ->select([
                'ma.id',
                'ma.agreement_id',
                'ma.seller_id',
                'ctc.screenname',
                'ma.buyer_id',
                'buyer.nickname',
                'buyer.user_number',
                'ma.num',
                'ma.effect_time',
                'ma.expire_time',
                'mp.rest_product_id',
                'p.sku',
                'p.mpn',
                'p.quantity',
                'p.product_id',
                'p.combo_flag',
            ])
            ->where([
                ['ma.status', '=', '6'],
                ['ma.expire_time', '<', date('Y-m-d H:i:s', time())]
            ])
            ->get();
    }

    /**
     * 获取保证金未发送站内信的调货记录
     *
     * @return \Illuminate\Support\Collection
     */
    public function getDispatchRecord()
    {
        $ret = DB::table('tb_sys_margin_agreement as ma')
            ->join('tb_sys_margin_adjustment as mad', 'ma.id', '=', 'mad.margin_id')
            ->join('oc_customer as buyer', 'buyer.customer_id', '=', 'ma.buyer_id')
            ->join('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ma.seller_id')
            ->join('oc_product as p', 'p.product_id', '=', 'ma.product_id')
            ->select(['mad.id as dispatch_id', 'ma.agreement_id', 'ma.agreement_id', 'ma.num as margin_num', 'ma.seller_id', 'ctc.screenname', 'ma.buyer_id', 'buyer.nickname',
                'buyer.user_number', 'mad.unaccomplished_num', 'mad.adjust_num', 'mad.create_time as dispatch_time', 'mad.adjustment_reason', 'mad.remark', 'p.sku', 'p.mpn'])
            ->where('mad.message_send', '<>', '1')
            ->get();
        return $ret;
    }

    public function updateMarginInformation($id, $data)
    {
        if (empty($id) || empty($data)) {
            return 0;
        }
        return DB::table('tb_sys_margin_agreement')->where('id', $id)->update($data);
    }

    public function updateMarginProduct($product_id, $data){
        if (empty($product_id) || empty($data)) {
            return 0;
        }
        return DB::transaction(function () use ($product_id, $data){
            DB::table('oc_product')->where('product_id', $product_id)->update($data);
            DB::table('oc_customerpartner_to_product')->where('product_id', $product_id)->update(array('quantity'=>0));
        });
    }

    public function updateMarginDispatch($dispatch_id, $data)
    {
        if (empty($dispatch_id) || empty($data)) {
            return 0;
        }
        return DB::table('tb_sys_margin_adjustment')->where('id', $dispatch_id)->update($data);
    }

    public function future2Margin($agreementId)
    {
        return DB::table('oc_futures_margin_delivery')
            ->where('margin_agreement_id', $agreementId)
            ->value('agreement_id');
    }


    public function getMarginCompletedCount($margin_id,$seller_id)
    {
        $qty_sum = DB::table('oc_product_lock_log as ll')
            ->selectRaw('SUM(ll.qty / pl.set_qty) as qty_sum')
            ->leftJoin('oc_product_lock as pl', 'pl.id', '=', 'll.product_lock_id')
            ->whereIn('ll.change_type', [1, 2])
            ->where('pl.agreement_id', $margin_id)
            ->where(['pl.seller_id' => $seller_id, 'pl.type_id' => 2])
            ->groupBy(['ll.product_lock_id'])
            ->get()
            ->max('qty_sum');
        return abs($qty_sum);
    }


}
