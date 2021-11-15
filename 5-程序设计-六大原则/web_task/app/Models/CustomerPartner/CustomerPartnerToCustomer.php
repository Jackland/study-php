<?php

namespace App\Models\CustomerPartner;

use Illuminate\Database\Eloquent\Model;

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
 * @property float $commission
 * @property int|null $self_support 是否自营 1为自营 0为非自营
 * @property int|null $menu_show 是否展示在Menu上默认最多6个
 * @property int|null $show 是否显示该Seller
 * @property string|null $customer_name 在库-大建云 店铺名
 * @property int|null $accounting_type 核算类型
 * @property string $cc_web_id cc客服WebID
 * @property string $cc_wc cc客服wc参数
 * @property string $customer_service_access_id 7moor客服
 * @property float $returns_rate 店铺退返品率，负数表示总销量小于10，2.97表示2.97%
 * @property string|null $returns_rate_date_modified
 * @property float $response_rate 店铺消息回复率，负数表示message量为0，2.97表示2.97%
 * @property string|null $response_rate_date_modified
 * @property float|null $performance_score seller的最新用户评分
 * @property string|null $score_task_number 最新评分的批次号
 * @property int $has_recommend_count 已经推荐次数
 * @property int $max_recommend_count 最大推荐次数
 * @property int $store_audit_id 店铺信息的最新审核ID
 * @property string|null $return_warranty {"return":{"undelivered":{"days":7,"rate":25,"allow_return":1},"delivered":{"before_days":7,"after_days":0}},"warranty":{"month":3,"conditions":[]}}
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereAccountingType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereBackgroundcolor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereCcWc($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereCcWebId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereCommission($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereCompanybanner($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereCompanydescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereCompanylocality($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereCompanylogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereCompanyname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereCountrylogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereCustomerName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereCustomerServiceAccessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereFacebookid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereHasRecommendCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereIsPartner($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereMaxRecommendCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereMenuShow($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereOtherpayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer wherePaypalfirstname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer wherePaypalid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer wherePaypallastname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer wherePerformanceScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereQrCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereResponseRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereResponseRateDateModified($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereReturnWarranty($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereReturnsRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereReturnsRateDateModified($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereScoreTaskNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereScreenname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereSelfSupport($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereShortprofile($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereShow($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereStoreAuditId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereTaxinfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToCustomer whereTwitterid($value)
 * @mixin \Eloquent
 */
class CustomerPartnerToCustomer extends Model
{
    protected $table = 'oc_customerpartner_to_customer';
    protected $primaryKey = 'customer_id';
}
