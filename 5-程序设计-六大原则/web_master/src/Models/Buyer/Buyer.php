<?php

namespace App\Models\Buyer;

use App\Models\Customer\Customer;
use App\Models\TelephoneCountryCode;
use App\Repositories\Customer\CustomerScoreRepository;
use Framework\Model\Eloquent\Builder;
use Framework\Model\EloquentModel;

/**
 * App\Models\Buyer\Buyer
 *
 * @property int $id 主键ID 自增主键ID
 * @property int|null $buyer_id 用户ID
 * @property string|null $cloud_freight_rate 云送仓费率,调整为按立方英尺计算，所以默认值1.5
 * @property int $cloud_freight_rate_modify 云送仓的费率是否被修改过，默认都是0，如果被云送仓修改过，则标记为1
 * @property int $cloud_guide_status 云送仓Label Update Guide标志位
 * @property string $airwallex_identifier AirwallexIdentifier标识：usernumber + _ + buyerId,未申请的用户为'0'
 * @property string|null $airwallex_id AirwallexID
 * @property string|null $memo 备注
 * @property float|null $performance_score buyer的最新用户评分
 * @property string|null $score_task_number 最新评分的批次号
 * @property string|null $selector_cellphone 选品人手机号
 * @property string|null $selector_wechat 选品人微信
 * @property string|null $selector_qq 选品人QQ
 * @property int $vat_type 1本土 2欧盟
 * @property string|null $selector_country_id 选品人国家id
 * @property string|null $contacts_phone 联系人电话
 * @property string|null $contacts_country_id 联系人国家id
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\Buyer newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\Buyer newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\Buyer query()
 * @mixin \Eloquent
 * @property-read \App\Models\Customer\Customer $customer
 * @property-read \App\Models\Buyer\BuyerUserPortrait $userPortrait
 * @property-read float|null $valid_performance_score 有效的评分
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Buyer\Buyer validPerformanceScoreOver($score)
 * @property bool $register_type 注册类型：0：企业注册 1：个人注册
 * @property bool $ascription_type 归属类型：0：本土 1：海外
 * @property string $legal_person_name 法人姓名
 * @property bool $build_connection 是否与GIGA Cloud Logistics B2B平台已发布店铺建立联系 0：否1：是
 */
class Buyer extends EloquentModel
{
    protected $table = 'oc_buyer';

    protected $fillable = [
        'buyer_id',
        'cloud_freight_rate',
        'cloud_freight_rate_modify',
        'cloud_guide_status',
        'airwallex_identifier',
        'airwallex_id',
        'memo',
        'performance_score',
        'score_task_number',
        'selector_cellphone',
        'selector_wechat',
        'selector_qq',
        'selector_country_id',
        'contacts_country_id',
        'contacts_phone',
        'contacts_open_status'
    ];

    public function customer()
    {
        return $this->hasOne(Customer::class, 'customer_id', 'buyer_id');
    }

    public function telephone_country_code()
    {
        return $this->hasOne(TelephoneCountryCode::class, 'id', 'contacts_country_id');
    }


    public function userPortrait()
    {
        return $this->hasOne(BuyerUserPortrait::class, 'buyer_id', 'buyer_id');
    }

    /**
     * 获取有效的评分数据
     * @return float|null
     */
    public function getValidPerformanceScoreAttribute()
    {
        $taskNumber = app(CustomerScoreRepository::class)->getLastTaskNumber();
        if (!$taskNumber || $this->score_task_number !== $taskNumber) {
            return null;
        }
        return $this->performance_score;
    }

    /**
     * 有效评分大于 N 的
     * @param Builder $query
     * @param $score
     * @return Builder
     */
    public function scopeValidPerformanceScoreOver(Builder $query, $score)
    {
        $taskNumber = app(CustomerScoreRepository::class)->getLastTaskNumber();
        return $query->where('performance_score', '>=', $score)
            ->where('score_task_number', $taskNumber);
    }
}
