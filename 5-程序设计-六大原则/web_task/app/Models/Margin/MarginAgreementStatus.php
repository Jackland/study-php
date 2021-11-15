<?php

namespace App\Models\Margin;

use Illuminate\Database\Eloquent\Model;

class MarginAgreementStatus extends Model
{

    const APPLIED = 1;
    const PENDING = 2;
    const APPROVED = 3;
    const TIME_OUT = 5;
    const TO_BE_PAID = 6;
    const BACK_ORDER = 9;//现货保证金协议Seller未成功履约
    const DEFAULT = 10;//现货保证金协议Buyer未成功履约

    //自定义状态 扩展  需要和yzc MarginAgreementLogType同名字段的值保持一致

    const APPLIED_TO_FAILED = 20;//seller从未打开此agreement,超时未处理
    const PENDING_TO_FAILED = 25; //seller查看了此agreement，超时未处理
    const ADVANCED_PRODUCT_PAY_FAILED = 30; //buyer未支付定金，且超时未处理

    const BUYER_FAILED = 55; //buyer违约,协议失效

    protected $table = 'tb_sys_margin_agreement_status';
    protected $primaryKey = 'margin_agreement_status_id';
    public $timestamps = false;

    protected $guarded = [];

    public static function getViewItems()
    {
        return [
            self::APPLIED => 'Applied',
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::TIME_OUT => 'Time Out',
            self::TO_BE_PAID => 'To be paid',
            self::BACK_ORDER => 'Back Order',
            self::DEFAULT => 'Default',
        ];
    }

    public static function getDescription($status)
    {
        $items = self::getViewItems();
        return isset($items[$status]) ? $items[$status] : 'Unknown';
    }

}
