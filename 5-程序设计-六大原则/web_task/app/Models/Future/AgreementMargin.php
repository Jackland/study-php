<?php

namespace App\Models\Future;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;

class AgreementMargin extends Model
{
    protected  $table = 'oc_futures_agreement_margin_pay_record';
    public $timestamps = false;
    protected $connection = 'mysql_proxy';

    /**
     *  seller支付期货保证金
     * @param $seller_id
     * @param $contract_id
     * @param $agreement_id
     * @param $amount
     * @param $pay_method
     * @return bool
     * @throws \Exception
     */
    public static function sellerPayFutureMargin($seller_id, $contract_id, $agreement_id, $amount, $pay_method)
    {
        $map['customer_id'] = $seller_id;
        $map['agreement_id'] = $agreement_id;
        $map['type'] = $pay_method;
        $map['flow_type'] = 1;
        $map['bill_type'] = 1;
        $map['bill_status'] = 1;// 期货协议保证金已在合约中扣除,不计入账单
        $map['amount'] = $amount;
        try {
            \DB::connection()->beginTransaction();

            //扣减期货合约的余额  判断协议是否使用了合约中抵押物的金额
            $collateralBalance = self::updateContractBalance($contract_id, $map['amount'], $pay_method);
            if ($collateralBalance > 0) {
                if ($map['amount'] - $collateralBalance > 0) {
                    $map['amount'] = $map['amount'] - $collateralBalance;
                    \DB::table('oc_futures_agreement_margin_pay_record')->insert($map);
                }

                $map['type'] = FuturesContractMarginPayRecord::SELLER_COLLATERAL;
                $map['amount'] = $collateralBalance;
                \DB::table('oc_futures_agreement_margin_pay_record')->insert($map);
            } else {
                \DB::table('oc_futures_agreement_margin_pay_record')->insert($map);
            }

            \DB::connection()->commit();
            return true;
        } catch (\Exception $e) {
            \DB::connection()->rollBack();
            return false;
        }

    }

    /**
     * seller返还期货保证金
     * @param $seller_id
     * @param $agreement_id
     * @param $amount
     * @param $pay_method
     * @param int $bill_status
     * @return bool
     * @throws \Exception
     */
    public static function sellerBackFutureMargin($seller_id, $agreement_id, $amount, $pay_method, $bill_status = 0)
    {
        $flow_type = 2;
        $bill_type = 2;

        // 信用额度处理
        if ($pay_method == FuturesContractMarginPayRecord::LINE_OF_CREDIT) {
            return self::insertMarginPayRecord($flow_type, $bill_type, ...func_get_args());
        }

        // 区分协议应收款是否使用抵押物
        list($sellerBillPayRecordAmount, $sellerCollateralPayRecordAmount) = self::getAgreementSellerBillAndCollateralAmount(intval($seller_id), intval($agreement_id));

        // 添加监测金额异常
        if (bcsub($amount, $sellerCollateralPayRecordAmount, 2)  != $sellerBillPayRecordAmount) {
            throw new \Exception('sellerBackFutureMargin error');
        }

        // 返还抵押物记录
        if ($sellerCollateralPayRecordAmount > 0) {
            self::insertMarginPayRecord($flow_type, $bill_type, $seller_id, $agreement_id, $sellerCollateralPayRecordAmount,  FuturesContractMarginPayRecord::SELLER_COLLATERAL, 1);
        }

        if ($amount - $sellerCollateralPayRecordAmount > 0) {
            // 返还应收款记录
            return self::insertMarginPayRecord($flow_type, $bill_type, $seller_id, $agreement_id, $amount - $sellerCollateralPayRecordAmount,  $pay_method, $bill_status);
        }

        return true;
    }

    /**
     * seller支付违约金
     * @param $seller_id
     * @param $agreement_id
     * @param $amount
     * @param $pay_method
     * @return bool
     */
    public static function sellerWithHoldFutureMargin($seller_id, $agreement_id, $amount, $pay_method)
    {
        $flow_type = 3;
        $bill_type = 1;
        return self::insertMarginPayRecord($flow_type, $bill_type, ...func_get_args());
    }

    /**
     * seller支付平台费
     * @param $seller_id
     * @param $agreement_id
     * @param $amount
     * @param $pay_method
     * @return bool
     */
    public static function sellerPayFuturePlatform($seller_id, $agreement_id, $amount, $pay_method)
    {
        $flow_type = 4;
        $bill_type = 1;
        return self::insertMarginPayRecord($flow_type, $bill_type, ...func_get_args());
    }

    /**
     * 保存seller流水记录
     * @param $flow_type
     * @param $bill_type
     * @param $seller_id
     * @param $agreement_id
     * @param $amount
     * @param $pay_method
     * @param int $bill_status
     * @return bool
     */
    public static function insertMarginPayRecord($flow_type, $bill_type, $seller_id, $agreement_id, $amount, $pay_method, $bill_status = 0)
    {
        $map['customer_id'] = $seller_id;
        $map['agreement_id'] = $agreement_id;
        $map['type'] = $pay_method;
        $map['flow_type'] = $flow_type;
        $map['bill_type'] = $bill_type;
        $map['bill_status'] = $bill_status;
        $map['amount'] = $amount;
        return \DB::table('oc_futures_agreement_margin_pay_record')->insert($map);
    }

    /**
     * 更新期货合约余额
     * @param $contract_id
     * @param $amount
     * @param $payMethod
     * @return float|int 使用了多少抵押物金额
     */
    public static function updateContractBalance($contract_id, $amount, $payMethod)
    {
        $collateralBalance = 0;

        /** @var Contract $contract */
        $contract = Contract::query()->lockForUpdate()->find($contract_id);
        if ($payMethod == FuturesContractMarginPayRecord::SELLER_BILL && $contract->collateral_balance > 0) {
            $collateralBalance = $contract->collateral_balance > $amount ? $amount : $contract->collateral_balance;
        }

        $availableBalance = bcsub($contract->available_balance, $amount, 2);
        $newCollateralBalance = bcsub($contract->collateral_balance, $collateralBalance, 2);

        $contract->update([
            'available_balance' => $availableBalance,
            'collateral_balance' => $newCollateralBalance,
        ]);

        return $collateralBalance;
    }

    /**
     * 对方违约返还的现金支付记录
     * @param array $agreementIds
     * @param int $customerId
     * @param int $billStatus
     * @param bool $isSeller
     * @return array
     */
    public static function otherPartyBreachedReturnedPayRecords(array $agreementIds, int $customerId, bool $isSeller = true, int $billStatus = 0)
    {
        if (empty($agreementIds)) {
            return [];
        }

        //3:seller违约金 5:buyer违约金
        $billType = $isSeller ? 5 : 3;

        return \DB::table('oc_futures_agreement_margin_pay_record')
            ->whereIn('agreement_id', $agreementIds)
            ->where('customer_id', $customerId)
            ->where('flow_type', $billType)
            ->where('bill_type', 2)
            ->where('bill_status', $billStatus)
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
    }

    /**
     * 某个合约(bid)的协议在某些状态下会占用的seller保证金
     * @param int $contractId
     * @param array $status
     * @param int $precision
     * @return mixed
     */
    public static function agreementSellerUnitAmountByContractIdAndStatus(int $contractId, array $status, $precision = 2)
    {
        return \DB::table('oc_futures_margin_agreement')
            ->where('contract_id', $contractId)
            ->whereIn('agreement_status', $status)
            ->where('is_bid', 1)
            ->selectRaw("round(unit_price * seller_payment_ratio * 0.01, " . $precision . ") * num as amount")
            ->get()
            ->sum('amount');
    }

    /**
     * 获取seller协议保证金支出的应收款和抵押物金额
     * @param int $sellerId
     * @param int $agreementId
     * @return array
     */
    public static function getAgreementSellerBillAndCollateralAmount(int $sellerId,int $agreementId)
    {
        $sellerBillPayRecordAmount = 0;
        $sellerCollateralPayRecordAmount = 0;

        // seller保证金支出花费的应收款记录
        $sellerBillPayRecord = \DB::table('oc_futures_agreement_margin_pay_record')
            ->where('agreement_id', $agreementId)
            ->where('customer_id', $sellerId)
            ->where('type', 3)
            ->where('flow_type', 1)
            ->where('bill_type', 1)
            ->first();

        if (!empty($sellerBillPayRecord)) {
            $sellerBillPayRecordAmount = $sellerBillPayRecord->amount;
        }

        // seller保证金支出花费的抵押款记录
        $sellerCollateralPayRecord = \DB::table('oc_futures_agreement_margin_pay_record')
            ->where('agreement_id', $agreementId)
            ->where('customer_id', $sellerId)
            ->where('type', 4)
            ->where('flow_type', 1)
            ->where('bill_type', 1)
            ->first();

        if (!empty($sellerCollateralPayRecord)) {
            $sellerCollateralPayRecordAmount = $sellerCollateralPayRecord->amount;
        }

        return [$sellerBillPayRecordAmount, $sellerCollateralPayRecordAmount];
    }
}