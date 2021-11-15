<?php

namespace App\Enums\SellerBill;

use Framework\Enum\BaseEnum;

/**
 * 结算类型 - 不完全枚举
 * tb_seller_bill_type --> code
 *
 * Class SettlementStatus
 * @package App\Enums\SellerBill
 */
class SellerBillTypeCode extends BaseEnum
{
    const ORDER = 'order'; // 订单
    const REFUND = 'refund'; // 返金
    const OTHER = 'other'; // 其他服务费用
    const REVENUE = 'revenue'; // 收入类
    const PAYMENT = 'payment'; // 支出类
    const OTHER_PAYMENT = 'other_payment'; // 其他费用类
    const INTEREST = 'interest'; // 供应链金融费用
    const V3_ORDER = 'v3_order'; // 一级：订单
    const V3_DISBURSEMENT = 'v3_disbursement'; // 垫付费用
    const V3_PLATFORM = 'v3_platform'; // 平台费
    const V3_OTHER = 'v3_other'; // 费用项
    const V3_NORMAL_ORDER = 'v3_normal_order'; // 普通订单
    const V3_SPOT_PRICE = 'v3_spot_price'; // 现货全款
    const V3_REBATE = 'v3_rebate'; // 返点交易
    const V3_MARGIN_DEPOSIT = 'v3_margin_deposit'; // 现货保证金定金
    const V3_FUTURE_DEPOSIT = 'v3_future_deposit'; // 期货保证金定金
    const V3_MARGIN_TAIL = 'v3_margin_tail'; // 现货保证金尾款
    const V3_FUTURE_TAIL = 'v3_future_tail'; // 期货保证金尾款
    const V3_FUTURE_TO_MARGIN_DEPOSIT = 'v3_future_to_margin_deposit'; // 期货转现货保证金交易
    const V3_RMA_REFUND = 'v3_rma_refund'; // 退返品返金
    const V3_REVENUE_PROMOTION_DETAIL = 'v3_revenue_promotion_detail'; // 促销补贴收入
    const V3_COMPLEX_REBATE = 'v3_complex_rebate'; // 返点交易支出
    const V3_COMPLEX_FUTURE = 'v3_complex_future'; // 期货保证金交易支出
    const V3_COMPLEX_MARGIN = 'v3_complex_margin'; // 现货办证金支出
    const V3_PAYMENT_PROMOTION_DETAIL = 'v3_payment_promotion_detail'; // 促销补贴支出
    const V3_PLATFORM_FEE_DETAIL = 'v3_platform_fee_detail'; // 平台费
    const V3_BID_BREAK_DETAIL = 'v3_bid_break_detail'; // 复杂交易违约平台费
    const V3_RMA_PROCESS_DETAIL = 'v3_rma_process_detail'; // RMA处理费
    const V3_LOGISTIC_DETAIL = 'v3_logistic_detail'; // 物流费
    const V3_STORAGE_DETAIL = 'v3_storage_detail'; // 仓租费
    const V3_INTEREST_DETAIL = 'v3_interest_detail'; // 欠款利息
    const V3_UNLOAD_DETAIL = 'v3_unload_detail'; // 卸货费
    const V3_SPECIAL_DETAIL = 'v3_special_detail'; // 特殊费用
    const V3_SEA_FREIGHT_DETAIL_REBATE = 'v3_sea_freight_detail_rebate'; // 海运费返点
    const V3_FREIGHT_REBATE = 'v3_freight_rebate'; // 运费返点
    const V3_LOGISTIC = 'v3_logistic'; // 物流费 - 二级
    const V3_SEA_FREIGHT = 'v3_sea_freight'; // 海运费 - 二级

    /**
     * 通过账单版本获取对应版本的一级费用项目
     *
     * @param string $version 账单版本
     * @return array
     */
    public static function getBillTypeByVersion($version)
    {
        $typeArr = [];
        switch (strtoupper($version)) {
            case SellerBillProgramCode::V1:
                $typeArr = [
                    ['key' => __(self::ORDER, [], 'controller/bill'), 'value' => 1, 'code' => self::ORDER],
                    ['key' => __(self::REFUND, [], 'controller/bill'), 'value' => 2, 'code' => self::REFUND],
                    ['key' => __(self::OTHER, [], 'controller/bill'), 'value' => 3, 'code' => self::OTHER]
                ];
                break;
            case SellerBillProgramCode::V2:
                $typeArr = [
                    ['key' => __(self::REVENUE, [], 'controller/bill'), 'value' => 32, 'code' => self::REVENUE],
                    ['key' => __(self::PAYMENT, [], 'controller/bill'), 'value' => 33, 'code' => self::PAYMENT],
                    ['key' => __(self::OTHER_PAYMENT, [], 'controller/bill'), 'value' => 34, 'code' => self::OTHER_PAYMENT],
                    ['key' => __(self::INTEREST, [], 'controller/bill'), 'value' => 36, 'code' => self::INTEREST]
                ];
                break;
            case SellerBillProgramCode::V3:
                $typeArr = [
                    ['key' => __(self::V3_ORDER, [], 'controller/bill'), 'value' => 81, 'code' => self::V3_ORDER],
                    ['key' => __(self::V3_DISBURSEMENT, [], 'controller/bill'), 'value' => 82, 'code' => self::V3_DISBURSEMENT],
                    ['key' => __(self::V3_PLATFORM, [], 'controller/bill'), 'value' => 83, 'code' => self::V3_PLATFORM],
                    ['key' => __(self::V3_OTHER, [], 'controller/bill'), 'value' => 84, 'code' => self::V3_OTHER],
                ];
                break;
        }

        return $typeArr;
    }

    // 获取自定已费用编号类目
    public static function getDefineNoTypes()
    {
        return [
            self::V3_PLATFORM_FEE_DETAIL,
            self::V3_LOGISTIC_DETAIL,
            self::V3_STORAGE_DETAIL,
            self::V3_INTEREST_DETAIL
        ];
    }

    // 获取需要展示编号类型类目
    public static function getShowNoTypes()
    {
        return [
            self::V3_NORMAL_ORDER,
            self::V3_SPOT_PRICE,
            self::V3_REBATE,
            self::V3_MARGIN_DEPOSIT,
            self::V3_FUTURE_DEPOSIT,
            self::V3_MARGIN_TAIL,
            self::V3_FUTURE_TAIL,
            self::V3_FUTURE_TO_MARGIN_DEPOSIT
        ];
    }

    // 获取直接获取货值类目
    public static function getProductPriceTotalTypes()
    {
        return [
            self::V3_MARGIN_DEPOSIT,
            self::V3_FUTURE_DEPOSIT,
            self::V3_FUTURE_TO_MARGIN_DEPOSIT
        ];
    }

    // 获取需要计算单件货值类目
    public static function getProductPriceTypes()
    {
        return [
            self::V3_NORMAL_ORDER,
            self::V3_SPOT_PRICE,
            self::V3_REBATE,
            self::V3_MARGIN_TAIL,
            self::V3_FUTURE_TAIL
        ];
    }

    // 获取直接展示0运费类目
    public static function getShowFrightZeroTypes()
    {
        return [
            self::V3_MARGIN_DEPOSIT,
            self::V3_FUTURE_DEPOSIT,
            self::V3_FUTURE_TO_MARGIN_DEPOSIT
        ];
    }

    // 获取需要展示数量的类目
    public static function getShowQuantityTypes()
    {
        return [
            self::V3_NORMAL_ORDER,
            self::V3_SPOT_PRICE,
            self::V3_REBATE,
            self::V3_MARGIN_TAIL,
            self::V3_FUTURE_TAIL
        ];
    }

    // 获取需要计算单件运费类目
    public static function getShowFrightTypes()
    {
        return [
            self::V3_NORMAL_ORDER,
            self::V3_SPOT_PRICE,
            self::V3_REBATE,
            self::V3_MARGIN_TAIL,
            self::V3_FUTURE_TAIL
        ];
    }

    // 获取需要展示类型描述的类目
    public static function getShowDescTypes()
    {
        return [
            self::V3_SPOT_PRICE,
            self::V3_REBATE,
            self::V3_MARGIN_DEPOSIT,
            self::V3_FUTURE_DEPOSIT,
            self::V3_MARGIN_TAIL,
            self::V3_FUTURE_TAIL,
            self::V3_FUTURE_TO_MARGIN_DEPOSIT,
            self::V3_COMPLEX_REBATE,
            self::V3_RMA_REFUND
        ];
    }

    // 获取自动计算费用类目
    public static function getAutoCalcTypes()
    {
        return [
            self::V3_LOGISTIC_DETAIL,
            self::V3_STORAGE_DETAIL,
            self::V3_INTEREST_DETAIL
        ];
    }
}