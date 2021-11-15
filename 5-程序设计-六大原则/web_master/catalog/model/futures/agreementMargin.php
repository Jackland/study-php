<?php

namespace Catalog\model\futures;

use App\Enums\Common\YesNoEnum;
use App\Enums\Future\FuturesMarginPayRecordType;
use App\Models\Futures\FuturesContract;
use App\Repositories\Futures\AgreementRepository;
use Illuminate\Database\Capsule\Manager as DB;
use Squareup\Exception;

class agreementMargin
{
    protected static $table = 'oc_futures_agreement_margin_pay_record';


    /**
     *  seller支付期货保证金
     * @param int $seller_id
     * @param $contract_id
     * @param int $agreement_id oc_futures_margin_agreement.id协议ID
     * @param float $amount
     * @param string $pay_method
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
            DB::connection()->beginTransaction();

            //扣减期货合约的余额  判断协议是否使用了合约中抵押物的金额
            $collateralBalance = self::updateContractBalance($contract_id, $map['amount'], $pay_method);
            if ($collateralBalance > 0) {
                if ($map['amount'] - $collateralBalance > 0) {
                    $map['amount'] = $map['amount'] - $collateralBalance;
                    DB::table(self::$table)->insert($map);
                }

                $map['type'] = FuturesMarginPayRecordType::SELLER_COLLATERAL;
                $map['amount'] = $collateralBalance;
                DB::table(self::$table)->insert($map);
            } else {
                DB::table(self::$table)->insert($map);
            }

            DB::connection()->commit();
            return true;
        } catch (Exception $e) {
            DB::connection()->rollBack();
            return false;
        }

    }

    /**
     * seller返还期货保证金
     * @param int $seller_id
     * @param int $agreement_id
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
        if ($pay_method == FuturesMarginPayRecordType::LINE_OF_CREDIT) {
            return self::insertMarginPayRecord($flow_type, $bill_type, ...func_get_args());
        }

        // 区分协议应收款是否使用抵押物
        list($sellerBillPayRecordAmount, $sellerCollateralPayRecordAmount) = app(AgreementRepository::class)->getAgreementSellerBillAndCollateralAmount(intval($seller_id), intval($agreement_id));

        // 添加监测金额异常
        if (bcsub($amount, $sellerCollateralPayRecordAmount, 2)  != $sellerBillPayRecordAmount) {
            throw new \Exception('sellerBackFutureMargin error');
        }

        // 返还抵押物记录
        if ($sellerCollateralPayRecordAmount > 0) {
            self::insertMarginPayRecord($flow_type, $bill_type, $seller_id, $agreement_id, $sellerCollateralPayRecordAmount,  FuturesMarginPayRecordType::SELLER_COLLATERAL, YesNoEnum::YES);
        }

        // 返还应收款记录
        if ($amount - $sellerCollateralPayRecordAmount > 0) {
            return self::insertMarginPayRecord($flow_type, $bill_type, $seller_id, $agreement_id, $amount - $sellerCollateralPayRecordAmount,  $pay_method, $bill_status);
        }

        return true;
    }

    /**
     * seller支付违约金
     * @param int $seller_id
     * @param int $agreement_id
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
     * @param int $seller_id
     * @param int $agreement_id
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
     * @param int $seller_id
     * @param int $agreement_id
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
        return DB::table(self::$table)->insert($map);
    }

    /**
     * 更新期货合约余额
     * @param $contractId
     * @param $amount
     * @param $payMethod
     * @return float|int|mixed 使用了多少抵押物金额
     */
    public static function updateContractBalance($contractId, $amount, $payMethod)
    {
        $collateralBalance = 0;

        /** @var FuturesContract $contract */
        $contract = FuturesContract::query()->lockForUpdate()->find($contractId);
        if ($payMethod == FuturesMarginPayRecordType::SELLER_BILL && $contract->collateral_balance > 0) {
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

        return DB::table('oc_futures_agreement_margin_pay_record')
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
}
