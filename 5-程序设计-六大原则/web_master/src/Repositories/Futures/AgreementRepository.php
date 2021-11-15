<?php

namespace App\Repositories\Futures;

use App\Enums\Future\FuturesMarginPayRecordBillType;
use App\Enums\Future\FuturesMarginPayRecordFlowType;
use App\Enums\Future\FuturesMarginPayRecordType;
use App\Enums\Product\ProductTransactionType;
use App\Models\Cart\Cart;
use App\Models\Futures\FuturesAgreementMarginPayRecord;
use App\Models\Futures\FuturesContractMarginPayRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

class AgreementRepository
{
    /**
     * 获取seller协议保证金支出的应收款和抵押物金额
     * @param int $sellerId
     * @param int $agreementId
     * @return array
     */
    public function getAgreementSellerBillAndCollateralAmount(int $sellerId,int $agreementId)
    {
        $sellerBillPayRecordAmount = 0;
        $sellerCollateralPayRecordAmount = 0;

        // seller保证金支出花费的应收款记录
        $sellerBillPayRecord = FuturesAgreementMarginPayRecord::query()
            ->where('agreement_id', $agreementId)
            ->where('customer_id', $sellerId)
            ->where('type', FuturesMarginPayRecordType::SELLER_BILL)
            ->where('flow_type', FuturesMarginPayRecordFlowType::SELLER_DEPOSIT_EXPAND)
            ->where('bill_type', FuturesMarginPayRecordBillType::EXPEND)
            ->first();

        if (!empty($sellerBillPayRecord)) {
            $sellerBillPayRecordAmount = $sellerBillPayRecord->amount;
        }

        // seller保证金支出花费的抵押款记录
        $sellerCollateralPayRecord = FuturesAgreementMarginPayRecord::query()
            ->where('agreement_id', $agreementId)
            ->where('customer_id', $sellerId)
            ->where('type', FuturesMarginPayRecordType::SELLER_COLLATERAL)
            ->where('flow_type', FuturesMarginPayRecordFlowType::SELLER_DEPOSIT_EXPAND)
            ->where('bill_type', FuturesMarginPayRecordBillType::EXPEND)
            ->first();

        if (!empty($sellerCollateralPayRecord)) {
            $sellerCollateralPayRecordAmount = $sellerCollateralPayRecord->amount;
        }

        return [$sellerBillPayRecordAmount, $sellerCollateralPayRecordAmount];
    }

    /**
     * 获取seller 抵押物支付期货保证金
     *
     * @param int $sellerId
     * @return array [$sellerIncomeAccount, $sellerExpendAccount]
     */
    public function getSellerCollateralAmount(int $sellerId)
    {
        $sellerIncomeAccount = 0;// 收入
        $sellerExpendAccount = 0;// 支出
        $futuresAgreementMarginPayRecord = FuturesAgreementMarginPayRecord::query()
            ->where('customer_id', $sellerId)
            ->where('type', FuturesMarginPayRecordType::SELLER_COLLATERAL)
            ->where('bill_type', FuturesMarginPayRecordBillType::INCOME)
            ->sum('amount');
        $sellerIncomeAccount += $futuresAgreementMarginPayRecord ?? 0;
        $futuresContractMarginPayRecord = FuturesContractMarginPayRecord::query()
            ->where('customer_id', $sellerId)
            ->where('type', FuturesMarginPayRecordType::SELLER_COLLATERAL)
            ->groupBy(['bill_type'])
            ->get(['bill_type', new Expression('sum(amount) as amount')])
            ->pluck('amount', 'bill_type')->toArray();
        $sellerIncomeAccount += ($futuresContractMarginPayRecord[FuturesMarginPayRecordBillType::INCOME] ?? 0);
        $sellerExpendAccount += ($futuresContractMarginPayRecord[FuturesMarginPayRecordBillType::EXPEND] ?? 0);
        return [$sellerIncomeAccount, $sellerExpendAccount];
    }

    /**
     * 购物车是否存在协议
     * @param int $customerId BuyerId
     * @param int $productId
     * @param int $agreementId
     * @param int $deliveryType
     * @param string $apiId
     * @return bool
     */
    public function cartExistFuturesAgreement($customerId, $productId, $agreementId, $deliveryType, $apiId)
    {
        $result = Cart::query()
            ->where('api_id', '=', $apiId)
            ->where('customer_id', '=', $customerId)
            ->where('product_id', '=', $productId)
            ->where('type_id', '=', ProductTransactionType::FUTURE)
            ->where('agreement_id', '=', $agreementId)
            ->where('delivery_type', '=', $deliveryType)
            ->exists();
        return $result;
    }

    /**
     * 购物车是否存在其他期货协议
     * @param int $customerId BuyerId
     * @param int $productId
     * @param int $agreementId 当前这条协议
     * @param int $toDeliveryType
     * @param string $apiId
     * @return bool
     */
    public function cartExistFuturesAgreementOther($customerId, $productId, $agreementId, $toDeliveryType, $apiId)
    {
        $result = Cart::query()
            ->where('api_id', '=', $apiId)
            ->where('customer_id', '=', $customerId)
            ->where('product_id', '=', $productId)
            ->where('type_id', '=', ProductTransactionType::FUTURE)
            ->where('delivery_type', '=', $toDeliveryType)
            ->exists();
        return $result;
    }
}
