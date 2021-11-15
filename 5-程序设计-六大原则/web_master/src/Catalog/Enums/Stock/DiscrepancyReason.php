<?php

namespace App\Catalog\Enums\Stock;

use Framework\Enum\BaseEnum;

/**
 * 库存单据类型
 *
 * Class DiscrepancyReason
 * @package App\Enums\Stock
 */
class DiscrepancyReason extends BaseEnum
{
    //入库
    const RESHIPMENT_INBOUND = 101; // RMA重发入库
    const PURCHASE_ORDER = 102; // 采购订单（入库）

    //出库
    const CWF_ORDER = 201; // 云送仓订单 （出库/锁定）
    const RMA_REFUND = 202;//RMA退货(出库)
    const SALES_ORDER = 203;//销售订单出库（出库）
    const RESHIPMENT_OUTBOUND = 204;//重发出库
    const INVENTORY_REDUCTION = 205;//库存下调
    const INVENTORY_DEFICIT = 206;//库存盘亏
    //锁定
    const BLOCKED_ASR = 301;//
    const BLOCKED_PENDING_CHARGES = 302;//未支付仓租
    const BLOCKED_CANCELED_SALES_ORDER = 303;//取消了订单但未申请RMA
    const BLOCKED_APPLYING_RMA = 304;//申请RMA但是未通过
    const BLOCKED_CANCELED_RMA_ORDER = 305;//取消rma重发单
    const SOLD_BUT_NOT_SHIPPED = 306;//已售未发***这个状态目前不会出现
    const RMA_BUT_NOT_SHIPPED = 307;//RMA重发单未发
    const BUYER_INVENTORY_REDUCTION_LOCK = 308; // 库存下调锁定
    const BUYER_INVENTORY_DEFICIT_LOCK = 309; // 库存盘亏锁定
    const BUYER_SALES_ORDER_PRE_LOCK = 310; // 销售单库存预锁定


    public static function getViewItems()
    {
        return [
            static::RESHIPMENT_INBOUND => 'Reshipment inbound',
            static::PURCHASE_ORDER => 'Purchased',
            static::CWF_ORDER => 'Sales Order outbound',
            static::RMA_REFUND => 'Return outbound',
            static::SALES_ORDER => 'Sales Order outbound',
            static::RESHIPMENT_OUTBOUND => 'Reshipment outbound',
            static::INVENTORY_REDUCTION => 'Inventory Deduction',
            static::INVENTORY_DEFICIT => 'Inventory Loss',
            static::BLOCKED_ASR => 'ASR to be paid',
            static::BLOCKED_PENDING_CHARGES => 'Pending charges',
            static::BLOCKED_CANCELED_SALES_ORDER => 'Order canceled without RMA request',//'Blocked(Canceled Sales Order)'
            static::BLOCKED_APPLYING_RMA => 'RMA approval pending',
            static::BLOCKED_CANCELED_RMA_ORDER => 'Blocked(Canceled RMA Order)',
            static::SOLD_BUT_NOT_SHIPPED => 'Unshipped',//'Sold but not Shipped'
            static::RMA_BUT_NOT_SHIPPED => 'Unshipped',//'RMA but not Shipped'
            static::BUYER_SALES_ORDER_PRE_LOCK => 'Pre-locked',
            static::BUYER_INVENTORY_REDUCTION_LOCK => 'Inventory reduction',
            static::BUYER_INVENTORY_DEFICIT_LOCK => 'Inventory deficit',
        ];
    }

    public static function getDiscrepancyInvoiceType($reason)
    {
        $return = [
            static::RESHIPMENT_INBOUND => DiscrepancyInvoiceType::RESHIPMENT_ORDER,
            static::PURCHASE_ORDER => DiscrepancyInvoiceType::PURCHASE_ORDER,
            static::CWF_ORDER => DiscrepancyInvoiceType::SALES_ORDER,
            static::RMA_REFUND => DiscrepancyInvoiceType::RMA,
            static::SALES_ORDER => DiscrepancyInvoiceType::SALES_ORDER,
            static::BLOCKED_ASR => DiscrepancyInvoiceType::SALES_ORDER,
            static::BLOCKED_PENDING_CHARGES => DiscrepancyInvoiceType::SALES_ORDER,
            static::BLOCKED_CANCELED_SALES_ORDER => DiscrepancyInvoiceType::SALES_ORDER,
            static::BLOCKED_APPLYING_RMA => DiscrepancyInvoiceType::RMA,
            static::BLOCKED_CANCELED_RMA_ORDER => 'Blocked(Canceled RMA Order)',
            static::SOLD_BUT_NOT_SHIPPED => DiscrepancyInvoiceType::SALES_ORDER,
            static::RMA_BUT_NOT_SHIPPED => DiscrepancyInvoiceType::RESHIPMENT_ORDER,
            static::RESHIPMENT_OUTBOUND => DiscrepancyInvoiceType::RESHIPMENT_ORDER,
            static::BUYER_SALES_ORDER_PRE_LOCK => DiscrepancyInvoiceType::SALES_ORDER,
            static::BUYER_INVENTORY_REDUCTION_LOCK => DiscrepancyInvoiceType::BLOCKED,
            static::BUYER_INVENTORY_DEFICIT_LOCK => DiscrepancyInvoiceType::BLOCKED,
        ];
        return $return[$reason] ?? 0;
    }

}
