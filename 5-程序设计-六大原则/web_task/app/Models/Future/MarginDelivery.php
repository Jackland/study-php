<?php

namespace App\Models\Future;

use App\Models\Product\ProductLock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Margin\MarginAgreementLog;
use Carbon\Carbon;
use App\Models\Margin\MarginAgreementStatus;

class MarginDelivery extends Model
{
    protected $table = 'oc_futures_margin_delivery';
    public $timestamps = false;
    protected $connection = 'mysql_proxy';

    public static function setDeliveryTimeOut()
    {
        $time = date('Y-m-d H:i:s', strtotime('- 3 day'));
        $collection = \DB::table('oc_futures_margin_delivery as d')
            ->select('d.agreement_id', 'd.delivery_status', 'd.last_purchase_num', 'd.margin_apply_num','a.num')
            ->leftJoin('oc_futures_margin_agreement as a', 'a.id', '=', 'd.agreement_id')
            ->whereIn('d.delivery_status', [3, 5,7])
            ->where('d.update_time', '<', $time)
            ->where('a.contract_id','=',0) // 期货老版数据
            ->get();
        if ($collection->isEmpty()) {
            return;
        }
        try {
            \DB::beginTransaction();
            $ProductLock = new FuturesProductLock();
            // 一、Seller交付后，Buyer需要在3个自然日内，选择交付方式，若超时则判定buyer违约（delivery status = Unexectued）
            // delivery_status为3为seller成功交付,delivery_status为7是Seller拒绝Buyer的交割形式
            $filtered = $collection->whereIn('delivery_status', [3, 7]);
            if (!$filtered->isEmpty()) {
                $agreement_ids = $filtered->pluck('agreement_id')->toArray();
                // 1.返还seller期货保证金
                MarginPayRecord::backFutureMargin($agreement_ids);
                // 2. 释放库存
                foreach ($filtered as $item) {
                    $qty = intval($item->num);
                    $ProductLock->TailOut($item->agreement_id, $qty, $item->agreement_id, FuturesProductLock::LOCK_TAIL_TIMEOUT);
                }
                // 3. 修改交割状态为buyer违约
                self::whereIn('agreement_id', $agreement_ids)->update(['delivery_status' => 4]);
            }
            // 二、Buyer选择交割方式后，Seller需要在3个自然日内，处理交割申请，若超时则判定Seller违约（delivery status = back order）
            // delivery_status为5为buyer已选择交割方式
            $filtered = $collection->where('delivery_status', 5);
            if (!$filtered->isEmpty()) {
                $agreement_ids = $filtered->pluck('agreement_id')->toArray();
                // 1.扣除seller期货保证金
                MarginPayRecord::withholdFutureMargin($agreement_ids);
                // 2. 释放库存
                foreach ($filtered as $item) {
                    $qty = intval($item->num);
                    $ProductLock->TailOut($item->agreement_id, $qty, $item->agreement_id, FuturesProductLock::LOCK_TAIL_TIMEOUT);
                }
                // 3. 修改交割状态为seller违约
                self::whereIn('agreement_id', $agreement_ids)->update(['delivery_status' => 2]);
            }
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error($e);
        }
    }
    // Buyer在24小时内未支付现货保证金，判定Buyer违约
    public static function stockMarginTimeOut()
    {
        $time = date('Y-m-d H:i:s', strtotime('- 1 day'));
        $build = \DB::table('oc_futures_margin_delivery as d')
            ->leftJoin('oc_futures_margin_agreement as a','d.agreement_id','=','a.id')
            ->select('agreement_id')
            ->leftJoin('tb_sys_margin_process as p', 'd.margin_agreement_id', '=', 'p.margin_id')
            ->where('d.delivery_status', 6)
            ->where('p.process_status', 1)
            ->where('a.contract_id','<>',0)
            ->where('d.confirm_delivery_date', '<', $time);
        $collection = $build->get();
        if ($collection->isEmpty()) {
            return;
        }
        try {
            \DB::beginTransaction();
            // 2. 释放库存
            $agreement_ids = $collection->pluck('agreement_id')->toArray();
            $ProductLock = new FuturesProductLock();
            $locked_agreement = ProductLock::getProductLockByAgreementIds($agreement_ids)->keyBy('agreement_id')->toArray();
            foreach ($agreement_ids as $id) {
                if (isset($locked_agreement[$id]['qty'])) {
                    $qty = $locked_agreement[$id]['qty'] / $locked_agreement[$id]['set_qty'];
                    $ProductLock->TailOut($id, $qty, $id, FuturesProductLock::LOCK_TAIL_TIMEOUT);
                }
            }
            self::buyerMarginTimeOut($agreement_ids);
            // 1. 修改交割状态为Buyer违约
            $build->update(['delivery_status' => 4]);

            //现货四期 协议日志记录
            $logType = MarginAgreementStatus::BUYER_FAILED;
            $content = MarginAgreementStatus::getDescription(MarginAgreementStatus::TO_BE_PAID) . '->'
                . MarginAgreementStatus::getDescription(MarginAgreementStatus::DEFAULT);
            foreach ($agreement_ids as $agreement_id) {
                MarginAgreementLog::query()->firstOrCreate(['agreement_id' => $agreement_id, 'type' => $logType],
                    [
                        'customer_id' => 0,
                        'content' => json_encode(['agreement_status' => $content]),
                        'operator' => 'system',
                        'create_time' => Carbon::now(),
                        'update_time' => Carbon::now(),
                    ]);
            }

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error($e);
        }
    }
    // Buyer在24小时内未支付现货保证金，判定Buyer违约
    public static function stockMargin()
    {
        $time = date('Y-m-d H:i:s', strtotime('- 1 day'));
        $build = \DB::table('oc_futures_margin_delivery as d')
            ->leftJoin('oc_futures_margin_agreement as a','d.agreement_id','=','a.id')
            ->select('agreement_id')
            ->leftJoin('tb_sys_margin_process as p', 'd.margin_agreement_id', '=', 'p.margin_id')
            ->where('d.delivery_status', 6)
            ->where('p.process_status', 1)
            ->where('a.contract_id','=',0) // 期货老版数据
            ->where('d.confirm_delivery_date', '<', $time);
        $collection = $build->get();
        if ($collection->isEmpty()) {
            return;
        }
        try {
            \DB::beginTransaction();
            // 1. 修改交割状态为Buyer违约
            $build->update(['delivery_status' => 4]);
            // 2. 释放库存
            $agreement_ids = $collection->pluck('agreement_id')->toArray();
            $ProductLock = new FuturesProductLock();
            $locked_agreement = ProductLock::getProductLockByAgreementIds($agreement_ids)->keyBy('agreement_id')->toArray();
            foreach ($agreement_ids as $id) {
                if (isset($locked_agreement[$id]['qty'])) {
                    $qty = $locked_agreement[$id]['qty'] / $locked_agreement[$id]['set_qty'];
                    $ProductLock->TailOut($id, $qty, $id, FuturesProductLock::LOCK_TAIL_TIMEOUT);
                }
            }
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error($e);
        }
    }

    /**
     * [sellerBackOrder description]交货日期后，平台判定Seller库存不足无法交付导致协议违约
     * @param array $ids
     */
    public static function sellerBackOrder($ids)
    {
        // 1.更新记录，以及站内信发送
        self::updateSellerInfo($ids);
        // 2.修改状态为seller违约
        self::whereIn('agreement_id', $ids)
            ->update(['delivery_status' => 2]);
    }
    // seller未按约定时间交付，再过30个自然日未交付，则判定seller违约
    public static function sellerNotDelivery()
    {
        $time = date('Y-m-d H:i:s', strtotime('- 30 day'));
        $collection = \DB::table('oc_futures_margin_agreement as a')
            ->select('a.id')
            ->leftJoin('oc_futures_margin_delivery as d', 'a.id', '=', 'd.agreement_id')
            ->where('a.expected_delivery_date', '<', $time)
            ->where('d.delivery_status', 1)
            ->where('a.contract_id','=',0) // 期货老版数据
            ->get();
        if ($collection->isEmpty()) {
            return;
        }
        try {
            \DB::beginTransaction();
            $ids = $collection->pluck('id')->toArray();
            // 1.扣除seller期货保证金
            MarginPayRecord::withholdFutureMargin($ids);
            // 2.修改状态为seller违约
            self::whereIn('agreement_id', $ids)
                ->update(['delivery_status' => 2]);
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error($e);
        }
    }

    public static function updateSellerInfo($ids)
    {
        $collection = Agreement::getAgreementInfo($ids);
        foreach($collection as $key => $value){
            // 记录log
            $log['info'] = [
                'agreement_id' => $value->id,
                'customer_id' =>  0,
                'type' => 32, //back order
                'operator' =>  'System',
            ];
            $log['agreement_status'] = [$value->agreement_status, $value->agreement_status];
            $log['delivery_status'] = [$value->delivery_status,2];
            Agreement::addAgreementLog($log['info'],
                $log['agreement_status'],
                $log['delivery_status']
            );
            // 记录apply
            $record = [
                'agreement_id'=> $value->id,
                'customer_id' => 0,
                'apply_type'  => 2,
                'status'      => 1,
            ];
            Agreement::addFutureApply($record);
            $record['remark'] = 'Seller超时未交付';
            // 发站内信
            $condition_buyer['from'] = 0;
            $condition_buyer['to'] = $value->buyer_id;
            $condition_buyer['country_id'] = $value->country_id;
            $condition_buyer['status'] = 0;
            $condition_buyer['communication_type'] = 3;
            $condition_buyer['info'] = $value;
            $condition_buyer['remark'] = $record['remark'];
            Agreement::addFuturesAgreementCommunication($value->id,3,$condition_buyer);
            $condition_seller['from'] = 0;
            $condition_seller['to'] = $value->seller_id;
            $condition_seller['country_id'] = $value->country_id;
            $condition_seller['status'] = 0;
            $condition_seller['communication_type'] = 3;
            $condition_seller['info'] = $value;
            $condition_seller['remark'] = $record['remark'];
            Agreement::addFuturesAgreementCommunication($value->id,4,$condition_seller);
            // 退钱
            self::chargeMoney(1,$value);
        }

    }

    public static function chargeMoney($type, $agreement)
    {
        $point = $agreement->country_id == 107 ? 0 : 2;
        $amount = round($agreement->unit_price * $agreement->seller_payment_ratio / 100, $point) * $agreement->num;
        $contract_info = Contract::firstPayRecordContracts($agreement->seller_id,[$agreement->contract_id]);
        switch ($type) {
            case 1:
                // seller 取消交付
                $paid_amount = ($agreement->unit_price * $agreement->seller_payment_ratio / 100) * $agreement->num;
                $paid_amount = round($paid_amount, $point);
                // seller 缴纳的平台费
                $platform_amount = round($paid_amount * 0.05, $point);
                $buyer_amount = round(($paid_amount - $platform_amount),$point);
                // 退还给buyer的钱 $paid_amount
                // seller 本金拿回
                AgreementMargin::sellerBackFutureMargin($agreement->seller_id, $agreement->id, $amount, $contract_info[0]['pay_type']);
                // seller 赔付 buyer
                AgreementMargin::sellerWithHoldFutureMargin($agreement->seller_id, $agreement->id, $buyer_amount, $contract_info[0]['pay_type']);
                // seller 赔付 平台费用
                AgreementMargin::sellerPayFuturePlatform($agreement->seller_id, $agreement->id, $platform_amount, $contract_info[0]['pay_type']);
                // 授信额度退回
                //if ($contract_info[0]['pay_type'] == 1) {
                //    credit::insertCreditBill($agreement->seller_id, $amount, 2);
                //}
                Agreement::addCreditRecord($agreement, $amount, 1);
                Agreement::addCreditRecord($agreement, $buyer_amount, 2);
                break;
            case 2:
            case 3:
                // seller 本金拿回
                agreementMargin::sellerBackFutureMargin($agreement->seller_id, $agreement->id, $amount, $contract_info[0]['pay_type']);
                if ($contract_info[0]['pay_type'] == 1) {
                    credit::insertCreditBill($agreement->seller_id, $amount, 2);
                }
                break;
        }
    }

    // buyer在30天未支付期货尾款的，则判定buyer违约
    public static function buyerNotPayTailMoney()
    {
        $agreement_ids = MarginProcess::getProcessTimeOut();
        if (!$agreement_ids) {
            return;
        }
        try {
            \DB::beginTransaction();
            $ProductLock = new FuturesProductLock();
            // 1. 修改交割状态为buyer违约
            self::whereIn('agreement_id', $agreement_ids)->update(['delivery_status' => 4]);
            // 2. 释放库存
            $locked_agreement = ProductLock::getProductLockByAgreementIds($agreement_ids)->keyBy('agreement_id')->toArray();
            foreach ($agreement_ids as $id) {
                if (isset($locked_agreement[$id]['qty'])) {
                    $qty = $locked_agreement[$id]['qty'] / $locked_agreement[$id]['set_qty'];
                    $ProductLock->TailOut($id, $qty, $id, FuturesProductLock::LOCK_TAIL_TIMEOUT);
                }
            }
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error($e);
        }
    }
    //buyer 保证金头款超时违约
    public static function buyerMarginTimeOut($ids)
    {
        $collection = Agreement::getAgreementInfo($ids);
        foreach($collection as $key => $value){
            // 记录log
            $log['info'] = [
                'agreement_id' => $value->id,
                'customer_id' =>  0,
                'type' => 33, //系统判断buyer  time out 保证金头款没付
                'operator' =>  'System',
            ];
            $log['agreement_status'] = [$value->agreement_status, $value->agreement_status];
            $log['delivery_status'] = [$value->delivery_status,4];
            Agreement::addAgreementLog($log['info'],
                $log['agreement_status'],
                $log['delivery_status']
            );
            $condition_buyer['from'] = 0;
            $condition_buyer['to'] = $value->seller_id;
            $condition_buyer['country_id'] = $value->country_id;
            $condition_buyer['status'] = null;
            $condition_buyer['communication_type'] = 7;
            $condition_buyer['info'] = $value;
            Agreement::addFuturesAgreementCommunication($value->id,7,$condition_buyer);
            // 退钱
            self::chargeMoney(2,$value);
        }
    }


}