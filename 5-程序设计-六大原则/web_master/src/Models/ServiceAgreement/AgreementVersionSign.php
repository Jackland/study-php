<?php

namespace App\Models\ServiceAgreement;

use Framework\Model\EloquentModel;

/**
 * App\Models\ServiceAgreement\AgreementVersionSign
 *
 * @property int $id 主键ID
 * @property int $agreement_id 协议ID,1用户服务协议
 * @property string $sign_no 签署编号
 * @property int $version_id 版本id
 * @property int $customer_id 客户ID
 * @property int $information_id oc_information中的ID
 * @property string $ip ip
 * @property string|null $area 区域
 * @property int $result 结果,1同意,0不同意
 * @property int $status 状态,1有效,0失效
 * @property \Illuminate\Support\Carbon|null $sign_time 签署时间
 * @property \Illuminate\Support\Carbon|null $effect_time 生效时间
 * @property \Illuminate\Support\Carbon|null $expire_time 失效时间
 * @property-read \App\Models\ServiceAgreement\AgreementVersion $version
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\ServiceAgreement\AgreementVersionSign newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\ServiceAgreement\AgreementVersionSign newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\ServiceAgreement\AgreementVersionSign query()
 * @mixin \Eloquent
 */
class AgreementVersionSign extends EloquentModel
{
    protected $table = 'tb_sys_agreement_version_sign';

    protected $dates = [
        'sign_time',
        'effect_time',
        'expire_time',
    ];

    protected $fillable = [
        'sign_no',
        'version_id',
        'customer_id',
        'information_id',
        'ip',
        'area',
        'result',
        'status',
        'sign_time',
        'effect_time',
        'expire_time',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function version()
    {
        return $this->belongsTo(AgreementVersion::class, 'version_id', 'id');
    }
}
