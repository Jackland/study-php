<?php

use App\Enums\Pay\VirtualPayType;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Safeguard\SafeguardClaim;

/**
 * Class ModelAccountBalanceVirtualPayRecord
 */
class ModelAccountBalanceVirtualPayRecord extends Model
{

    public function searchRecord($filter_data)
    {
        $query = $this->orm->table('oc_virtual_pay_record as vp')
            ->leftJoin('oc_yzc_rma_order as rma', 'vp.relation_id','=','rma.id')
            ->leftJoin('oc_rebate_agreement as r', 'vp.relation_id','=','r.id')
            ->where('vp.customer_id', $filter_data['customer_id'])
            ->when($filter_data['type'], function ($query) use ($filter_data){
                if (1 == $filter_data['type']){//REVENUE
                    return $query->whereIn('vp.type', VirtualPayType::getRevenueType());
                }else{
                    return $query->whereIn('vp.type', VirtualPayType::getPaymentType());
                }
            })
            ->when($filter_data['timeFrom'], function ($query) use ($filter_data){
                return $query->where('vp.create_time', '>=', $filter_data['timeFrom']);
            })
            ->when($filter_data['timeTo'], function ($query) use ($filter_data){
                return $query->where('vp.create_time', '<=', $filter_data['timeTo'].' 23:59:59');
            })
            ->when(4 != $filter_data['timeSpace'], function ($query) use ($filter_data){
                if (1 == $filter_data['timeSpace']){
                    $t = date('Y-m-d 00:00:00', strtotime('-7 day'));
                }elseif (2 == $filter_data['timeSpace']){
                    $t = date('Y-m-d 00:00:00', strtotime('-1 month'));
                }elseif (3 == $filter_data['timeSpace']){
                    $t = date('Y-m-d 00:00:00', strtotime('-1 year'));
                }else{
                    $t = '2019';
                }
                return $query->where('vp.create_time', '>=', $t);
            });

        $count = $query->count();

        $query->select('vp.*', 'rma.rma_order_id','r.agreement_code')
            ->OrderBy('id', 'desc');
        if (isset($filter_data['start']) && isset($filter_data['limit'])){
            $query->offset($filter_data['start'])
                ->limit($filter_data['limit']);
        }

        $list = $query->get();
        $precision = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0:2;
        foreach ($list as $k=>$v)
        {
            $v->amount = round($v->amount, $precision);
            $v->revenue = '';
            $v->payment = '';
            $v->method = VirtualPayType::getDescription($v->type);
            if (in_array($v->type, VirtualPayType::getPaymentType())) {
                // 支出
                $v->payment = '- ' . $v->amount;
            } elseif (in_array($v->type, VirtualPayType::getRevenueType())) {
                // 收入
                $v->revenue = '+ ' . $v->amount;
            }
            switch ($v->type) {
                case VirtualPayType::PURCHASE_ORDER_PAY:
                {
                    $v->url = $this->url->link('account/order/purchaseOrderInfo', '&order_id=' . $v->relation_id);
                    break;
                }
                case VirtualPayType::RMA_REFUND:
                {
                    $v->url = $this->url->link('account/rma_order_detail', '&rma_id=' . $v->relation_id);
                    break;
                }
                case VirtualPayType::REBATE:
                {
                    $v->url = $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementList', '&agreement_id=' . $v->relation_id);
                    break;
                }
                case VirtualPayType::STORAGE_FEE:
                case VirtualPayType::SAFEGUARD_PAY:
                case VirtualPayType::SAFEGUARD_REFUND:
                case VirtualPayType::STORAGE_FEE_REFUND:
                {
                    $feeOrder = FeeOrder::find($v->relation_id);
                    $v->url = $feeOrder ?
                        $this->url->link('account/order', ['filter_fee_order_no' => $feeOrder->order_no, '#' => 'tab_fee_order'])
                        : '';
                    $v->fee_order_no = $feeOrder ? $feeOrder->order_no : '';
                    break;
                }
                case VirtualPayType::SAFEGUARD_RECHARGE:
                {
                    $claim = SafeguardClaim::query()->find($v->relation_id);
                    if ($claim) {
                        $v->url = $this->url->link('account/safeguard/claim/claimDetail', ['claim_id' => $claim->id]);
                        $v->fee_order_no = $claim->claim_no;
                    }
                    break;
                }
            }
        }

        return ['total'=>$count, 'record_list'=>obj2array($list)];
    }

    //type 1,采购订单支付;2,RMA退款;3,返金; 4，费用单
    public function insertData($customerId, $relationId, $amount, $type=1)
    {
        if (0.00 == $amount || 0 == $amount){
            return;
        }
        return $this->orm->table('oc_virtual_pay_record')
            ->insertGetId([
                'serial_number' => currentZoneDate($this->session, date('Ymd'), 'Ymd').rand(100000, 999999),
                'relation_id'   => $relationId,
                'customer_id'   => $customerId,
                'type'          => $type,
                'amount'        => $amount,
                'create_time'   => date('Y-m-d H:i:s')
            ]);
    }
}
