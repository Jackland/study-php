<?php

namespace App\Models\Tripartite;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Tripartite\TripartiteAgreementOperate
 *
 * @property int $id ID
 * @property int $agreement_id 协议ID
 * @property int $request_id 协议请求ID
 * @property int $customer_id 用户ID， 0为系统
 * @property string|null $message 消息
 * @property int $type 操作类型，1:发起协议,2:同意,3:拒绝,4:申请终止,5:同意终止,6:拒绝终止,7:取消,8:自动终止,9取消申请自动取消,10终止申请自动取消,11取消申请,12同意取消,13拒绝取消
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreementOperate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreementOperate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreementOperate query()
 * @mixin \Eloquent
 */
class TripartiteAgreementOperate extends Model
{
    protected $table = 'oc_tripartite_agreement_operate';

    public $timestamps = false;

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'agreement_id',
        'request_id',
        'customer_id',
        'message',
        'type',
        'create_time',
    ];
}
