<?php

namespace App\Models\Futures;

use Framework\Model\EloquentModel;

/**
 * \App\Models\Futures\FuturesMarginDelivery
 *
 * @property int $id 自增主键
 * @property int $agreement_id oc_futures_margin_agreement的id
 * @property bool $delivery_type buyer的交割方式 （1.支付期货协议尾款交割；2.转现货保证金进行交割；3.转现货保证金和支付尾款混合交割模式）
 * @property bool $delivery_status 交付状态(前置条件:Agreement status = Sold),1为Forward Delivery,2为Back Order，3为Being Processed，4为Unexectued，5为Processing，6为To be Paid，7为Being Processed（Seller拒绝Buyer的交割形式），8为Completed
 * @property string|null $delivery_date seller交付日期
 * @property string|null $confirm_delivery_date seller审核交割日期,当前美国时间
 * @property int $last_purchase_num 尾款采购数量
 * @property float $last_unit_price 尾款单价
 * @property int $margin_apply_num 转现货保证金申请数量
 * @property float $margin_unit_price 转现货保证金单价
 * @property float $margin_deposit_amount 转现货保证金需补足定金金额
 * @property float $margin_last_price 转现货保证金尾款单价
 * @property float $margin_agreement_amount 转现货保证金协议金额
 * @property bool $margin_days 转现货保证金协议天数
 * @property int $margin_agreement_id 现货保证金协议ID
 * @property bool $cancel_appeal_apply 是否取消申述申请
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginDelivery newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginDelivery newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginDelivery query()
 * @mixin \Eloquent
 * @property bool|null $last_sync_status 与其他最后一次交互时协议状态
 * @property string|null $expire_time 协议到期时间
 */
class FuturesMarginDelivery extends EloquentModel
{
    protected $table = 'oc_futures_margin_delivery';
}
