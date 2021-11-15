<?php

namespace App\Models\Pay;

use Framework\Model\EloquentModel;

/**
 * App\Models\Pay\PaymentReturnCode
 *
 * @property int $id
 * @property string $error_code 错误码
 * @property string $description 错误码描述
 * @property int $payment_type 第三方支付服务商0:信用额度1：联动，2：信用卡
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Pay\PaymentReturnCode newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Pay\PaymentReturnCode newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Pay\PaymentReturnCode query()
 * @mixin \Eloquent
 */
class PaymentReturnCode extends EloquentModel
{
    protected $table = 'tb_payment_return_code';

    protected $fillable = [
        'error_code',
        'description',
        'payment_type',
    ];

}
