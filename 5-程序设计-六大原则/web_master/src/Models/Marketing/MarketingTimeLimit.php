<?php

namespace App\Models\Marketing;

use App\Enums\Marketing\MarketingTimeLimitStatus;
use App\Enums\Product\ProductTransactionType;
use App\Helper\LangHelper;
use App\Repositories\Marketing\MarketingTimeLimitDiscountRepository;
use Framework\Model\EloquentModel;

/**
 * App\Models\Marketing\MarketingTimeLimit
 *
 * @property int $id ID
 * @property int $seller_id seller_id
 * @property string $name 活动的名称
 * @property string $transaction_type 交易类型，-1为不限制
 * @property int $low_qty 最低购买数量
 * @property int $store_nav_show 店铺导航栏菜单
 * @property int $pre_hot 开始前24小时预热展示
 * @property \Illuminate\Support\Carbon $effective_time 生效时间
 * @property \Illuminate\Support\Carbon $expiration_time 失效时间
 * @property int $is_del 是否删除 0：未删除  1：已删除
 * @property int $status 1未终止,2终止
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property-read int $effective_status
 * @property-read int $effective_status_name
 * @property-read string $transaction_type_format
 * @property-read string $color_status_name
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Marketing\MarketingTimeLimitProduct[] $products
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingTimeLimit newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingTimeLimit newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingTimeLimit query()
 * @mixin \Eloquent
 */
class MarketingTimeLimit extends EloquentModel
{
    protected $table = 'oc_marketing_time_limit';
    protected $appends = ['transaction_type_format', 'effective_status_name', 'effective_status', 'color_status_name'];

    protected $dates = [
        'effective_time',
        'expiration_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'seller_id',
        'name',
        'transaction_type',
        'low_qty',
        'store_nav_show',
        'pre_hot',
        'effective_time',
        'expiration_time',
        'is_del',
        'status',
        'create_time',
        'update_time',
    ];

    public function products()
    {
        return $this->hasMany(MarketingTimeLimitProduct::class, 'head_id', 'id');
    }

    public function getTransactionTypeFormatAttribute()
    {
        if ($this->transaction_type == -1) {
            return LangHelper::isChinese() ? '不限' : 'No limit';
        }
        $transactionsTypes = explode(',', $this->transaction_type);
        $returnTransactionFormat = [];
        foreach ($transactionsTypes as $transactionsType) {
            $returnTransactionFormat[] = ProductTransactionType::getDescription($transactionsType);
        }

        return collect($returnTransactionFormat)->join(', ');
        // return implode(',', $returnTransactionFormat);
    }

    public function getEffectiveStatusAttribute()
    {
        return app(MarketingTimeLimitDiscountRepository::class)->getTimeLimitDiscountEffectiveStatus($this);
    }

    public function getEffectiveStatusNameAttribute()
    {
        $effectiveStatus = app(MarketingTimeLimitDiscountRepository::class)->getTimeLimitDiscountEffectiveStatus($this);
        return MarketingTimeLimitStatus::getDescription($effectiveStatus);
    }

    public function getColorStatusNameAttribute()
    {
        $effectiveStatus = app(MarketingTimeLimitDiscountRepository::class)->getTimeLimitDiscountEffectiveStatus($this);
        return MarketingTimeLimitStatus::getColorDescription($effectiveStatus);
    }

}
