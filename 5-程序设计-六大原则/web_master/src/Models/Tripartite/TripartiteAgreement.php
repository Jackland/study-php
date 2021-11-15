<?php

namespace App\Models\Tripartite;

use App\Enums\Tripartite\TripartiteAgreementRequestStatus;
use App\Enums\Tripartite\TripartiteAgreementStatus as Status;
use App\Models\Customer\Customer;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Repositories\Tripartite\AgreementRepository;
use Carbon\Carbon;
use Framework\Model\EloquentModel;

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
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreement newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreement newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreement query()
 * @mixin \Eloquent
 * @property-read string $status_color
 * @property-read string $status_name
 * @property-read array $template_replaces
 * @property int $seven_remind 倒计时七天提醒
 * @property int $request_type seller 请求类型
 * @property bool $can_tripartite_renewal 是否能续签
 * @property bool $can_tripartite_edit 是否能编辑
 * @property array $replace_value_input 转换给模板信息的标识
 * @property bool $cancel_handle_request_remain_time 剩余自动取消的时间
 * @property bool $early_termination buyer是否显示过早终止
 * @property bool $early_cancel buyer是否显示过早取消
 * @property array $request_seller_record seller 最近请求信息
 * @property array $request_buyer_record buyer 最近请求信息
 * @property string $content 组装后的协议信息
 * @property string $records 返回协议操作记录
 * @property array $recordsSort 返回分类后的协议操作记录
 * @property-read \App\Models\CustomerPartner\CustomerPartnerToCustomer $seller
 * @property-read \App\Models\Customer\Customer $buyer
 * @property-read \App\Models\Tripartite\TripartiteAgreementRequest[] $requests
 * @property-read \App\Models\Tripartite\TripartiteAgreementTemplate $template
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Tripartite\TripartiteAgreementOperate[] $tripartite_agreement_operate
 * @property-read int|null $tripartite_agreement_operate_count
 */
class TripartiteAgreement extends EloquentModel
{
    protected $table = 'oc_tripartite_agreement';

    protected $dates = [
        'effect_time',
        'expire_time',
        'terminate_time',
        'seller_approved_time',
        'create_time',
        'update_time',
    ];

    protected $appends = ['status_color', 'status_name'];

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
     * description:获取seller关联的店铺
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function seller()
    {
        return $this->hasOne(CustomerPartnerToCustomer::class, 'customer_id', 'seller_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id', 'customer_id');
    }

    public function requests()
    {
        return $this->hasMany(TripartiteAgreementRequest::class, 'agreement_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function template()
    {
        return $this->belongsTo(TripartiteAgreementTemplate::class, 'template_id');
    }

    /**
     * description:获取用户操作表记录信息
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tripartite_agreement_operate()
    {
        return $this->hasMany(TripartiteAgreementOperate::class, 'agreement_id', 'id');
    }

    /**
     * 获取状态名称
     * @return string
     */
    public function getStatusNameAttribute(): string
    {
        return Status::getViewItems()[$this->status] ?? '';
    }

    /**
     * 获取模板替换的值
     * @return array
     */
    public function getTemplateReplacesAttribute(): array
    {
        return $this->template_replace_value ? json_decode($this->template_replace_value, true) : [];
    }

    /**
     * 获取状态颜色属性
     * @return string
     */
    public function getStatusColorAttribute(): string
    {
        return Status::getColorItems()[$this->status] ?? '';
    }

    /**
     * 是否能编辑
     * @param bool $isBuyer
     * @return bool
     */
    public function canEdit(bool $isBuyer = true): bool
    {
        $this->attributes['can_tripartite_edit'] = false;
        if (!$isBuyer) {
            return $this->attributes['can_tripartite_edit'];
        }

        if (in_array($this->status, [Status::TO_BE_SIGNED, Status::REJECTED])) {
            $this->attributes['can_tripartite_edit'] = true;
        }
        if ($this->status == Status::CANCELED && empty($this->seller_approved_time)) {
            $this->attributes['can_tripartite_edit'] = true;
        }

        return $this->attributes['can_tripartite_edit'];
    }

    /**
     * 是否能处理 （有seller或者buyer 发起的请求）
     * @param bool $isBuyer
     * @return bool
     */
    public function canHandle(bool $isBuyer = true): bool
    {
        $this->attributes['can_tripartite_handle'] = false;

        if (!$isBuyer && $this->status == Status::TO_BE_SIGNED) {
            $this->attributes['can_tripartite_handle'] = true;
        }

        $handleId = $isBuyer ? $this->buyer_id : $this->seller_id;
        if (in_array($this->status, Status::approvedStatus()) &&
            TripartiteAgreementRequest::query()->where([
                'agreement_id' => $this->id,
                'handle_id' => $handleId,
                'status' => TripartiteAgreementRequestStatus::PENDING
            ])->exists()
        ) {
            $this->attributes['can_tripartite_handle'] = true;
        }

        return $this->attributes['can_tripartite_handle'];
    }

    /**
     * 是否能取消
     * @param bool $isBuyer
     * @return bool
     */
    public function canCancel(bool $isBuyer = true): bool
    {
        $this->attributes['can_tripartite_cancel'] = false;

        // seller在待生效能发送取消申请
        if (!$isBuyer && $this->status == Status::TO_BE_ACTIVE) {
            $this->attributes['can_tripartite_cancel'] = true;
        }

        if ($isBuyer && in_array($this->status, [Status::TO_BE_SIGNED, Status::REJECTED, Status::TO_BE_ACTIVE,])) {
            $this->attributes['can_tripartite_cancel'] = true;
        }

        // 已存在取消或终止申请的不能再次发送取消
        if (collect($this->requests)
            ->where('sender_id', $isBuyer ? $this->buyer_id : $this->seller_id)
            ->where('status', TripartiteAgreementRequestStatus::PENDING)
            ->isNotEmpty()) {
            $this->attributes['can_tripartite_cancel'] = false;
        }

        return $this->attributes['can_tripartite_cancel'];
    }

    /**
     * 是否能删除(已取消状态且无签署日期的记录有此按钮，即未签署的取消协议可删除)
     * @param bool $isBuyer
     * @return bool
     */
    public function canDelete(bool $isBuyer = true): bool
    {
        $this->attributes['can_tripartite_delete'] = false;
        if ($isBuyer && in_array($this->status, [Status::CANCELED]) && $this->is_deleted == 0 && empty($this->seller_approved_time)) {
            $this->attributes['can_tripartite_delete'] = true;
        }

        return $this->attributes['can_tripartite_delete'];
    }

    /**
     * 是否能续签
     * @param bool $isBuyer
     * @return bool
     */
    public function canRenewal(bool $isBuyer = true): bool
    {
        $this->attributes['can_tripartite_renewal'] = false;
        $days = app(AgreementRepository::class)->diffDays(Carbon::now(), $this->terminate_time);
        if ($isBuyer && in_array($this->status, [Status::ACTIVE]) && $days <= 7) {
            $this->attributes['can_tripartite_renewal'] = true;
        }
        return $this->attributes['can_tripartite_renewal'];
    }

    /**
     * 是否能终止协议 (协议中 或者待生效状态有此按钮，若有任意一方在申请终止期间，按按钮不显示)
     * @param bool $isBuyer
     * @return bool
     */
    public function canTerminate(bool $isBuyer = true): bool
    {
        $this->attributes['can_tripartite_terminate'] = false;
        if (in_array($this->status, Status::approvedStatus())) {
            $this->attributes['can_tripartite_terminate'] = true;
        }

        // 已存在取消或终止申请的不能再次发送终止
        if (collect($this->requests)
            ->where('sender_id', $isBuyer ? $this->buyer_id : $this->seller_id)
            ->where('status', TripartiteAgreementRequestStatus::PENDING)
            ->isNotEmpty()) {
            $this->attributes['can_tripartite_terminate'] = false;
        }

        // 已终止过的不能发送
        if ($this->expire_time != $this->terminate_time) {
            $this->attributes['can_tripartite_terminate'] = false;
        }

        // 协议结束当天，不能发送
        if (Carbon::now()->addDay()->gt($this->expire_time)) {
            $this->attributes['can_tripartite_terminate'] = false;
        }

        // 协议只有1天的，不能终止
        if (Carbon::parse($this->expire_time)->toDateString() == Carbon::parse($this->effect_time)->toDateString()) {
            $this->attributes['can_tripartite_terminate'] = false;
        }

        return $this->attributes['can_tripartite_terminate'];
    }
}
