<?php

namespace App\Models\Agreement;

use Framework\Model\EloquentModel;

/**
 * App\Models\Agreement\AgreementCommonPerformer
 *
 * @property int $id 自增主键
 * @property int $agreement_type 协议类型 agreement_type字典值维护在oc_setting表,code:common_performer_type;部分列举0: 现货保证金类型
 * @property string|null $agreement_id 协议ID
 * @property int $product_id 产品ID
 * @property int $buyer_id BuyerId
 * @property int $is_signed 是否是协议签订者,0:协议从属用户，1:协议主用户
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Agreement\AgreementCommonPerformer newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Agreement\AgreementCommonPerformer newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Agreement\AgreementCommonPerformer query()
 * @mixin \Eloquent
 */
class AgreementCommonPerformer extends EloquentModel
{
    protected $table = 'oc_agreement_common_performer';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'agreement_type',
        'agreement_id',
        'product_id',
        'buyer_id',
        'is_signed',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];
}
