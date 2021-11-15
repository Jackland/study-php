<?php

namespace App\Models\Tripartite;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Tripartite\TripartiteAgreement
 *
 * @property int $id ID
 * @property string $agreement_no 协议编号
 * @property string $title 协议名称
 * @property int $seller_id seller用户ID
 * @property int $buyer_id buyer用户ID
 * @property int $status 协议状态，1:待处理,5:已取消,10:已拒绝,15:待生效,20:已生效,25:已终止
 * @property \Illuminate\Support\Carbon $effect_time 协议生效时间
 * @property \Illuminate\Support\Carbon $expire_time 协议到期时间
 * @property \Illuminate\Support\Carbon $terminate_time 协议终止时间
 * @property int $template_id 模板ID
 * @property string|null $template_replace_value 模板替换值 json
 * @property \Illuminate\Support\Carbon|null $seller_approved_time 签署时间
 * @property string|null $download_url 下载地址
 * @property int $is_deleted
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreement query()
 * @property-read TripartiteAgreementRequest[] $requests
 * @mixin \Eloquent
 */
class TripartiteAgreement extends Model
{
    protected $table = 'oc_tripartite_agreement';

    public $timestamps = false;

    protected $dates = [
        'effect_time',
        'expire_time',
        'terminate_time',
        'seller_approved_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'agreement_no',
        'title',
        'seller_id',
        'buyer_id',
        'status',
        'effect_time',
        'expire_time',
        'terminate_time',
        'template_id',
        'template_replace_value',
        'seller_approved_time',
        'download_url',
        'is_deleted',
        'create_time',
        'update_time',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function requests()
    {
        return $this->hasMany(TripartiteAgreementRequest::class);
    }
}
