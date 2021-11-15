<?php

namespace App\Enums\SalesOrder;

use Framework\Enum\BaseEnum;

/**
 * 销售订单状态
 * 对应 tb_sys_dictionary 表下的 CUSTOMER_ORDER_STATUS 的值
 */
class CustomerSalesOrderStatus extends BaseEnum
{
    const TO_BE_PAID = 1; // 订单明细状态除了Cancelled之外，其他订单明细处于Backordered时这个订单的状态为NewOrder表示缺货
    const BEING_PROCESSED = 2; // 订单明细状态除了Cancelled之外，其他订单明细处于Pending时这个订单的状态为Being Processed表示这个订单一切正常，可以发货
    const ON_HOLD = 4; // 表示这个订单存在问题，需要处理
    const CANCELED = 16; // 订单取消
    const COMPLETED = 32; // 订单完成
    const LTL_CHECK = 64; // 超大件确认等待
    const PENDING_CHARGES = 127; // 费用待支付
    const ASR_TO_BE_PAID = 128; // 待支付签收服务费
    const CHECK_LABEL = 129; // 美国dropship业务 check Label 状态更改
    const ABNORMAL_ORDER = 256; // 表示这个订单存在问题,无法同步omd，需要手动处理
    const WAITING_FOR_PICK_UP = 257; // Waiting for Pick-up 待取货

    public static function getViewItems()
    {
        return [
            // 无特殊情况，定义该值后不能修改描述，因为有些地方是直接通过 LIKE '%Being Processed%' 这种去查数据的
            static::TO_BE_PAID => 'To Be Paid',
            static::BEING_PROCESSED => 'Being Processed',
            static::ON_HOLD => 'On Hold',
            static::CANCELED => 'Canceled',
            static::COMPLETED => 'Completed',
            static::LTL_CHECK => 'LTL Check',
            static::PENDING_CHARGES => 'Pending Charges',
            static::ASR_TO_BE_PAID => 'ASR To Be Paid',
            static::CHECK_LABEL => 'Check Label',
            static::ABNORMAL_ORDER => 'Abnormal Order',
            static::WAITING_FOR_PICK_UP => 'Waiting for Pick-up',
        ];
    }

    public static function canCancel()
    {
        return [
            static::TO_BE_PAID,
            static::BEING_PROCESSED,
            static::ON_HOLD,
            static::LTL_CHECK,
            static::PENDING_CHARGES,
            static::CHECK_LABEL,
        ];
    }

    public static function inAndAfterBeingProcessed()
    {
        return [
            static::BEING_PROCESSED,
            static::CANCELED,
            static::COMPLETED,
        ];
    }

    /**
     * 上门取货Buyer销售单列表页 Order Status 下拉列表
     * @param int $countryId
     * @return array
     */
    public static function listStatusForBuyerPickup($countryId = 223)
    {
        $result = [
            CustomerSalesOrderStatus::TO_BE_PAID => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::TO_BE_PAID),
            CustomerSalesOrderStatus::BEING_PROCESSED => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::BEING_PROCESSED),
            CustomerSalesOrderStatus::ON_HOLD => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::ON_HOLD),
            CustomerSalesOrderStatus::CANCELED => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::CANCELED),
            CustomerSalesOrderStatus::COMPLETED => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::COMPLETED),
            CustomerSalesOrderStatus::LTL_CHECK => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::LTL_CHECK),
            CustomerSalesOrderStatus::PENDING_CHARGES => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::PENDING_CHARGES),
            CustomerSalesOrderStatus::ASR_TO_BE_PAID => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::ASR_TO_BE_PAID),
            CustomerSalesOrderStatus::CHECK_LABEL => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::CHECK_LABEL),
        ];
        if ($countryId == AMERICAN_COUNTRY_ID) {
            $result[CustomerSalesOrderStatus::WAITING_FOR_PICK_UP] = CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::WAITING_FOR_PICK_UP);
        }
        return $result;
    }
}
