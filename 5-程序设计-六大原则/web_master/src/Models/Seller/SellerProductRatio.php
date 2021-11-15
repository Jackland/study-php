<?php

namespace App\Models\Seller;

use Framework\Model\EloquentModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use kriss\bcmath\BCS;

/**
 * App\Models\Seller\SellerProductRatio
 *
 * @property int $id
 * @property int $seller_id seller id
 * @property string $product_ratio 货值比
 * @property \Illuminate\Support\Carbon|null $effective_time 当前货值比生效时间，用于记录
 * @property string|null $product_ratio_next 下次生效的货值比，可为空
 * @property \Illuminate\Support\Carbon|null $effective_time_next 下次货值比生效时间,如果立即生效或者已经生效将是空
 * @property \Illuminate\Support\Carbon $create_time
 * @property \Illuminate\Support\Carbon $update_time
 * @property-read string $service_ratio 服务费比例
 * @property-read string|null $service_ratio_next 下次生效的服务费比例
 * @property-read SellerProductRatioLog[]|Collection $logs 日志
 * @property-read int|null $logs_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerProductRatio newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerProductRatio newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerProductRatio query()
 * @mixin \Eloquent
 */
class SellerProductRatio extends EloquentModel
{
    protected $table = 'oc_seller_product_ratio';

    protected $dates = [
        'effective_time',
        'effective_time_next',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'seller_id',
        'product_ratio',
        'effective_time',
        'product_ratio_next',
        'effective_time_next',
        'create_time',
        'update_time',
    ];
    protected $appends = ['service_ratio', 'service_ratio_next'];

    private $ratioScale = 3;

    // 日志
    public function logs()
    {
        return $this->hasMany(SellerProductRatioLog::class);
    }


    public function setProductRatioAttribute($value)
    {
        // 清空临时变量
        unset($this->_serviceRatio['product_ratio']);
        $this->attributes['product_ratio'] = $value;
    }

    public function getProductRatioAttribute($value)
    {
        if (!isset($value)) {
            return null;
        }
        return BCS::create($value, ['scale' => $this->ratioScale])->getResult();
    }

    public function setProductRatioNextAttribute($value)
    {
        // 清空临时变量
        unset($this->_serviceRatio['product_ratio_next']);
        $this->attributes['product_ratio_next'] = $value;
    }

    public function getProductRatioNextAttribute($value)
    {
        if (!isset($value)) {
            return null;
        }
        return BCS::create($value, ['scale' => $this->ratioScale])->getResult();
    }

    /**
     * 当前生效的服务费比例
     *
     * @return float|null
     */
    public function getServiceRatioAttribute()
    {
        return $this->getServiceRatio('product_ratio');
    }

    /**
     * 待生效的服务费比例
     *
     * @return float|null
     */
    public function getServiceRatioNextAttribute()
    {
        return $this->getServiceRatio('product_ratio_next');
    }

    private $_serviceRatio = [];

    /**
     * 用商品货值比例计算出服务费比例
     *
     * @param $key
     * @return float|null
     */
    private function getServiceRatio($key): ?float
    {
        if (!isset($this->attributes[$key])) {
            return null;
        }
        if (isset($this->_serviceRatio[$key])) {
            return $this->_serviceRatio[$key];
        }
        return $this->_serviceRatio[$key] = BCS::create(1, ['scale' => $this->ratioScale])->sub($this->attributes[$key])->getResult();
    }
}
