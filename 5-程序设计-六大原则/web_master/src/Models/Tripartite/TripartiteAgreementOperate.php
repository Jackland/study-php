<?php

namespace App\Models\Tripartite;

use App\Enums\Tripartite\TripartiteAgreementOperateType;
use App\Helper\CountryHelper;
use App\Models\Customer\Customer;
use Framework\Model\EloquentModel;

/**
 * App\Models\Tripartite\TripartiteAgreementOperate
 *
 * @property int $id ID
 * @property int $agreement_id 协议ID
 * @property int $request_id 请求ID
 * @property int $customer_id 用户ID， 0为系统
 * @property string|null $message 消息
 * @property int $type 操作类型，1:发起协议,2:同意,3:拒绝,4:申请终止,5:同意终止,6:拒绝终止,7:取消,8:自动终止,9取消申请自动取消,10终止申请自动取消,11取消申请,12同意取消,13拒绝取消
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreementOperate newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreementOperate newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreementOperate query()
 * @property-read string $type_name
 * @property-read TripartiteAgreement $agreement
 * @property-read Customer $customer
 * @property-read TripartiteAgreementRequest $request
 * @mixin \Eloquent
 */
class TripartiteAgreementOperate extends EloquentModel
{
    protected $table = 'oc_tripartite_agreement_operate';

    protected $appends = ['type_name'];


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

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function agreement()
    {
        return $this->belongsTo(TripartiteAgreement::class, 'agreement_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function request()
    {
        return $this->belongsTo(TripartiteAgreementRequest::class, 'request_id');
    }

    /**
     * 获取操作类型状态
     * @return string
     */
    public function getTypeNameAttribute(): string
    {
        $name = TripartiteAgreementOperateType::getViewItems()[$this->type] ?? '';

        if ($this->type == TripartiteAgreementOperateType::SEND_TERMINATED_REQUEST) {
            $formatTime = $this->request->request_time->setTimezone(CountryHelper::getTimezone($this->customer->country_id))->format('m/d/Y');
            $name = str_replace('value', $formatTime, $name);
        }

        if ($this->type == TripartiteAgreementOperateType::SEND_CANCEL_REQUEST) {

            $name = str_replace('value', $this->customer->is_partner ? 'Buyer' : 'Seller', $name);
        }

        return $name;
    }
}
