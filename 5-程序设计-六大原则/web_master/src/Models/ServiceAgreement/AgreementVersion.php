<?php

namespace App\Models\ServiceAgreement;

use Framework\Model\EloquentModel;

/**
 * App\Models\ServiceAgreement\AgreementVersion
 *
 * @property int $id 主键ID
 * @property int $agreement_id 协议ID,1用户服务协议
 * @property int $agreement_type 协议类型,1服务,2隐私,3授权,4业务
 * @property string $name 版本名称
 * @property string $version 版本号
 * @property int $information_id oc_information中的ID, 协议版本地址 route=information/information&information_id=?
 * @property int $status 状态,1有效,0无效
 * @property int $is_sign 是否提醒签署,1是,0否
 * @property \Illuminate\Support\Carbon $effect_time 生效时间
 * @property int $is_deleted 是否删除,1是,0否
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ServiceAgreement\AgreementVersionSign[] $agreementVersionSigns
 * @property-read int|null $agreement_version_signs_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\ServiceAgreement\AgreementVersion newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\ServiceAgreement\AgreementVersion newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\ServiceAgreement\AgreementVersion query()
 * @mixin \Eloquent
 */
class AgreementVersion extends EloquentModel
{
    const AGREEMENT_ID_BY_CUSTOMER_LOGIN = 1; // 用户服务协议，首次登陆需签署

    protected $table = 'tb_sys_agreement_version';

    protected $dates = [
        'effect_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'agreement_id',
        'agreement_type',
        'name',
        'version',
        'information_id',
        'status',
        'is_sign',
        'effect_time',
        'is_deleted',
        'create_time',
        'update_time',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function agreementVersionSigns()
    {
        return $this->hasMany(AgreementVersionSign::class, 'version_id');
    }
}
