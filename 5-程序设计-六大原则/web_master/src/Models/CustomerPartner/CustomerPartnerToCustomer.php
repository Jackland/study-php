<?php

namespace App\Models\CustomerPartner;

use App\Components\Storage\StorageCloud;
use App\Components\Traits\ModelImageSolveTrait;
use App\Models\Customer\Customer;
use App\Models\Seller\SellerStore;
use App\Repositories\Customer\CustomerScoreRepository;
use Framework\Model\EloquentModel;
use ModelToolImage;

/**
 * App\Models\CustomerPartner\CustomerPartnerToCustomer
 *
 * @property int $customer_id
 * @property int $is_partner
 * @property string $screenname
 * @property string $gender
 * @property string $shortprofile
 * @property string $avatar
 * @property string $qr_code 腾讯企点二维码图片路径
 * @property string $twitterid
 * @property string $paypalid
 * @property string|null $paypalfirstname
 * @property string|null $paypallastname
 * @property string $country
 * @property string $facebookid
 * @property string $backgroundcolor
 * @property string $companybanner
 * @property string $companylogo
 * @property string $companylocality
 * @property string $companyname
 * @property string $companydescription
 * @property string $countrylogo
 * @property string $otherpayment
 * @property string|null $taxinfo
 * @property string $commission
 * @property int|null $self_support 是否自营 1为自营 0为非自营
 * @property int|null $menu_show 是否展示在Menu上默认最多6个
 * @property int|null $show 是否显示该Seller
 * @property string|null $customer_name 在库-大建云 店铺名
 * @property int|null $accounting_type 核算类型
 * @property string $cc_web_id cc客服WebID
 * @property string $cc_wc cc客服wc参数
 * @property string $customer_service_access_id 7moor客服
 * @property string $returns_rate 店铺退返品率，负数表示总销量小于10，2.97表示2.97%
 * @property string|null $returns_rate_date_modified
 * @property string $response_rate 店铺消息回复率，负数表示message量为0，2.97表示2.97%
 * @property string|null $response_rate_date_modified
 * @property float|null $performance_score seller的最新用户评分
 * @property string|null $score_task_number 最新评分的批次号
 * @property int $has_recommend_count 已经推荐次数
 * @property int $max_recommend_count 最大推荐次数
 * @property int $is_recommended_new 是否是新的需要推荐的
 * @property-read \App\Models\Customer\Customer $customer
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer query()
 * @mixin \Eloquent
 * @property int $store_audit_id 店铺信息的最新审核ID
 * @property string|null $return_warranty {"return":{"undelivered":{"days":7,"rate":25,"allow_return":1},"delivered":{"before_days":7,"after_days":0,"delivered_checked":0}},"warranty":{"month":3,"conditions":[]}}
 * @property-read string $avatar_show
 * @property-read mixed $company_banner_show
 * @property-read mixed $company_logo_show
 * @property-read bool $has_futures
 * @property-read bool $has_margin
 * @property-read bool $has_rebates
 * @property-read \App\Models\Seller\SellerStore $sellerStore
 * @property-read float $return_approval_rate 店铺退返品同意率
 * @property-read string $response_rate_str Result: high,moderate,low,空字符串
 * @property-read string $return_approval_rate_str Result: high,moderate,low,空字符串
 * @property-read string $return_rate_str  Result: high,moderate,low,空字符串
 * @property-read float|null $valid_performance_score 有效的评分
 */
class CustomerPartnerToCustomer extends EloquentModel
{
    use ModelImageSolveTrait;

    protected $table = 'oc_customerpartner_to_customer';
    protected $primaryKey = 'customer_id';
    protected $appends = ['avatarShow'];

    protected $fillable = [
        'is_partner',
        'screenname',
        'gender',
        'shortprofile',
        'avatar',
        'qr_code',
        'twitterid',
        'paypalid',
        'paypalfirstname',
        'paypallastname',
        'country',
        'facebookid',
        'backgroundcolor',
        'companybanner',
        'companylogo',
        'companylocality',
        'companyname',
        'companydescription',
        'countrylogo',
        'otherpayment',
        'taxinfo',
        'commission',
        'self_support',
        'menu_show',
        'show',
        'customer_name',
        'accounting_type',
        'cc_web_id',
        'cc_wc',
        'customer_service_access_id',
        'returns_rate',
        'returns_rate_date_modified',
        'response_rate',
        'response_rate_date_modified',
        'performance_score',
        'score_task_number',
        'has_recommend_count',
        'max_recommend_count',
        'store_audit_id',
        'return_warranty',
        'is_recommended_new',
    ];

    public function customer()
    {
        return $this->hasOne(Customer::class, 'customer_id', 'customer_id');
    }

    public function sellerStore()
    {
        return $this->hasOne(SellerStore::class, 'seller_id', 'customer_id');
    }

    public function getScreennameAttribute(): string
    {
        return html_entity_decode($this->attributes['screenname']);
    }

    public function getReturnRateStrAttribute(): string
    {
        if (is_null($this->returns_rate) || $this->returns_rate < 0) {
            return 'N/A';
        }
        if ($this->returns_rate > 10) {
            return 'High';
        } elseif ($this->returns_rate > 4) {
            return 'Moderate';
        } else {
            return 'Low';
        }
    }

    public function getResponseRateStrAttribute(): string
    {
        if (is_null($this->response_rate) || $this->response_rate < 0) {
            return 'N/A';
        }
        if ($this->response_rate > 1) {
            return 'High';
        } elseif ($this->response_rate > 0) {
            return 'Moderate';
        } else {
            return 'Low';
        }
    }

    public function getReturnApprovalRateStrAttribute(): string
    {
        if (is_null($this->return_approval_rate) || $this->return_approval_rate <= 0) {
            return 'N/A';
        }
        if ($this->return_approval_rate > 90) {
            return 'High';
        } elseif ($this->return_approval_rate > 60) {
            return 'Moderate';
        } else {
            return 'Low';
        }
    }

    public function getCompanyBannerShowAttribute()
    {
        return $this->getImageShow('avatar');
    }

    public function getCompanyLogoShowAttribute()
    {
        return $this->getImageShow('avatar', 300, 80);
    }

    public function getAvatarShowAttribute()
    {
        return $this->getImageShow('avatar', 120);
    }

    private function getImageShow($attribute, $w = null, $h = null)
    {
        $config = array_merge(['w' => $w, 'h' => $h], $this->_imageSolveConfig);

        $value = $this->$attribute;
        if ($value && StorageCloud::image()->fileExists($value)) {
            return StorageCloud::image()->getUrl($value, $config);
        }
        $default = configDB('marketplace_default_image_name');
        if ($default && file_exists(DIR_IMAGE . $default)) {
            if ($this->avatar !== 'removed') {
                /** @var ModelToolImage $toolImage */
                $toolImage = load()->model('tool/image');
                return $toolImage->resize($default, $config['w'], $config['h']);
            }
        }
        return '';
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
}
