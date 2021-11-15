<?php

namespace App\Components\UniqueGenerator\Enums;

use App\Models\Customer\Customer;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Order\OrderInvoice;
use App\Models\Pay\LineOfCreditRecord;
use App\Models\Pay\RechargeApply;
use App\Models\Safeguard\SafeguardBill;
use App\Models\SalesOrder\CustomerSalesOrderPickUp;
use App\Models\Tripartite\TripartiteAgreement;
use Framework\Enum\BaseEnum;
use InvalidArgumentException;

class ServiceEnum extends BaseEnum
{
    // 业务名称，值用下划线分隔
    const USER_NUMBER = 'user_number';
    const SAFEGUARD_BILL_NO = 'safeguard_no'; //保单号
    const FEE_ORDER = 'fee_order_no'; //费用单
    const TRIPARTITE_NO = 'agreement_no'; //采销协议编号
    const AMENDMENT_RECORD_NO = 'amendment_record_no'; // 账户额度变动流水编号
    const RECHARGE_APPLY_NO = 'recharge_apply_no'; // 充值流水号
    const SALES_ORDER_PICK_UP_BOL_NUM = 'sales_order_pick_up_bol_num'; // 上门取货自提货业务的BOL文件
    const ORDER_INVOICE_NO = 'order_invoice_no'; // 采购单Invoice流水号

    public static function checkDatabaseExistConfig(string $service)
    {
        $map = [
            // service => [ModelClass, column]
            // 或：service => callable($value)
            self::USER_NUMBER => [Customer::class, 'user_number'],
            self::SAFEGUARD_BILL_NO => [SafeguardBill::class, 'safeguard_no'],
            self::FEE_ORDER => [FeeOrder::class, 'order_no'],
            self::TRIPARTITE_NO => [TripartiteAgreement::class, 'agreement_no'],
            self::AMENDMENT_RECORD_NO => [LineOfCreditRecord::class, 'serial_number'],
            self::RECHARGE_APPLY_NO => [RechargeApply::class, 'serial_number'],
            self::SALES_ORDER_PICK_UP_BOL_NUM => [CustomerSalesOrderPickUp::class, 'bol_num'],
            self::ORDER_INVOICE_NO => [OrderInvoice::class, 'serial_number'],
        ];

        if (!isset($map[$service])) {
            throw new InvalidArgumentException('数据库校验值唯一必须配置映射关系');
        }

        return $map[$service];
    }
}
