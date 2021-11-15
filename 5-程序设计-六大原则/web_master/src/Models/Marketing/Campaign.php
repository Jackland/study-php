<?php

namespace App\Models\Marketing;

use App\Enums\Marketing\CampaignTransactionType;
use App\Enums\Marketing\CampaignType;
use Carbon\Carbon;
use Framework\Model\Eloquent\Builder;
use Framework\Model\EloquentModel;
use Illuminate\Database\Eloquent\Collection;

/**
 * App\Models\Marketing\Campaign
 *
 * @property int $id
 * @property string $code 随机且唯一的code
 * @property string $name 活动名称(buyer端活动名称)
 * @property string|null $seller_activity_name 活动名称(seller端活动名称)
 * @property int $type 0->other;1->banner; 2->满送;3->满减
 * @property string $transaction_type 交易类型，-1为不限制
 * @property int $country_id 国家ID
 * @property string $effective_time 生效时间
 * @property string $expiration_time 失效时间
 * @property string $apply_start_time 申请开始时间
 * @property string $apply_end_time 申请截止时间
 * @property int $seller_num 可报名的商家数
 * @property int $product_num_per 每个商家可报名的最大产品数
 * @property string $require_category 可参与活动的产品分类id(用逗号分隔)，限制申请活动的产品类别
 * @property string|null $require_pro_start_time 产品创建的开始时间
 * @property string|null $require_pro_end_time 产品结束的开始时间
 * @property int $require_pro_min_stock 产品最低库存(上架库存)（0,标识为不做限制）
 * @property string $description 描述
 * @property int $is_release 是否发布
 * @property int $image_id 顶部图片 tb_upload_file.id
 * @property string $create_time 创建时间
 * @property string $update_time 修改时间
 * @property int $is_noticed 是否站内信通知了seller
 * @property int|null $is_send 是否发送站内信 0:不发送站内信 1:发送站内信
 * @property CampaignCondition|Collection $conditions
 * @property array $transaction_types $transaction_type的,切割数组
 * @property-read string $transaction_name
 * @property-read int|null $conditions_count
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Marketing\Campaign available()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Marketing\Campaign fullTypes()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\Campaign newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\Campaign newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\Campaign query()
 * @mixin \Eloquent
 * @property string|null $background_color 页面背景颜色
 */
class Campaign extends EloquentModel
{
    protected $table = 'oc_marketing_campaign';

    protected $fillable = [
        'code',
        'name',
        'seller_activity_name',
        'type',
        'transaction_type',
        'country_id',
        'effective_time',
        'expiration_time',
        'apply_start_time',
        'apply_end_time',
        'seller_num',
        'product_num_per',
        'require_category',
        'require_pro_start_time',
        'require_pro_end_time',
        'require_pro_min_stock',
        'description',
        'is_release',
        'image_id',
        'create_time',
        'update_time',
        'is_noticed',
        'is_send',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function conditions()
    {
        return $this->hasMany(CampaignCondition::class, 'mc_id');
    }

    /**
     * $transaction_type的,切割数组
     * @return string[]
     */
    public function getTransactionTypesAttribute()
    {
        $transactionType = explode(',', $this->transaction_type);
        if (in_array(-1, $transactionType)) {
            $transactionType = [-1];
        }
        return $transactionType;
    }

    /**
     * 获取交易类型名称
     * @return string
     */
    public function getTransactionNameAttribute()
    {
        $transactionsNameMap = CampaignTransactionType::transactionsNameMap($this->type);

        $names = [];
        foreach ($this->transaction_types as $type) {
            if (isset($transactionsNameMap[$type])) {
                $names[] = $transactionsNameMap[$type];
            }
        }

        return join(' & ', $names);
    }

    /**
     * 生效的（已发布，在有效时间内）
     * @param Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAvailable(Builder $query)
    {
        return $query->where('is_release', 1)
            ->where('effective_time', '<=', Carbon::now())
            ->where('expiration_time', '>', Carbon::now());
    }

    /**
     * 满减，满送类型
     * @param Builder $query
     * @return Builder
     */
    public function scopeFullTypes(Builder $query)
    {
        return $query->whereIn('type', CampaignType::fullTypes());
    }
}
