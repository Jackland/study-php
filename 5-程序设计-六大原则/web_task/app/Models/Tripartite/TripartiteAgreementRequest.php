<?php

namespace App\Models\Tripartite;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Tripartite\TripartiteAgreementRequest
 *
 * @property int $id ID
 * @property int $agreement_id 协议ID
 * @property int $sender_id 发起人用户ID
 * @property int $handle_id 待处理用户ID
 * @property \Illuminate\Support\Carbon $request_time 申请时间
 * @property int $type 申请类型 1终止 2取消
 * @property string $reason 理由
 * @property int $status 操作类型，1申请中 2同意 3拒绝 4自动取消 5过期
 * @property int $agreement_status 申请终止时的状态
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreementRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreementRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreementRequest query()
 * @property-read TripartiteAgreement $agreement
 * @mixin \Eloquent
 */
class TripartiteAgreementRequest extends Model
{
    protected $table = 'oc_tripartite_agreement_request';

    public $timestamps = false;

    protected $dates = [
        'request_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'agreement_id',
        'sender_id',
        'handle_id',
        'request_time',
        'type',
        'reason',
        'status',
        'agreement_status',
        'create_time',
        'update_time',
    ];

    public function agreement()
    {
        return $this->belongsTo(TripartiteAgreement::class, 'agreement_id');
    }
}
