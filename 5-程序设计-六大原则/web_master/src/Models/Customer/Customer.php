<?php

namespace App\Models\Customer;

use App\Enums\Buyer\BuyerType;
use App\Enums\Buyer\BuyerVATType;
use App\Enums\Common\CountryEnum;
use App\Helper\StringHelper;
use App\Models\Buyer\Buyer;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Repositories\Customer\CustomerRepository;
use Framework\Model\EloquentModel;

/**
 * App\Models\Customer\Customer
 *
 * @property int $customer_id
 * @property int $customer_group_id
 * @property int $store_id
 * @property int $language_id
 * @property string $firstname
 * @property string $lastname
 * @property string $nickname 用户昵称
 * @property string $user_number 8位数字用户编号
 * @property string $email
 * @property string $telephone
 * @property string $fax
 * @property string $password
 * @property string $salt
 * @property string|null $cart
 * @property string|null $wishlist
 * @property int $newsletter
 * @property int $address_id
 * @property string $custom_field
 * @property string $ip
 * @property int $status
 * @property int $safe
 * @property string $token
 * @property string $code
 * @property string $date_added
 * @property int $user_mode 登录的用户模式
 * @property string|null $line_of_credit 信用额度
 * @property int $country_id
 * @property int|null $additional_flag
 * @property int|null $giga_mall_id
 * @property string|null $logistics_customer_name
 * @property int|null $accounting_type 核算类型
 * @property string|null $prepare_email 备用邮箱
 * @property string|null $vat VAT
 * @property string|null $company_name 公司名称
 * @property string|null $company_address 公司地址
 * @property int|null $trusteeship Buyer是否在平台上托管
 * @property int $account_attributes 账户属性 详细见oc_account_attributes
 * @property int $service_agreement 是否同意服务协议 0.未同意 1.已同意 已弃用 改为tb_sys_agreement_version
 * @property-read \App\Models\Customer\Country $country
 * @property-read \App\Models\CustomerPartner\CustomerPartnerToCustomer $store
 * @property-read mixed $currency
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\Customer newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\Customer newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\Customer query()
 * @mixin \Eloquent
 * @property string|null $province 注册省/州
 * @property string|null $city 注册城市
 * @property-read mixed $full_name
 * @property-read \App\Models\Buyer\Buyer $buyer
 * @property-read \App\Models\CustomerPartner\CustomerPartnerToCustomer $seller
 * @property-read \App\Models\Customer\CustomerGroupDescription $groupdesc
 * @property string|null $company_code 公司CODE
 * @property string|null $register_country 注册国家
 * @property string|null $register_province 注册省/州
 * @property string|null $register_city 注册城市
 * @property string|null $register_postal_code 邮政编码
 * @property string|null $register_address 注册详细地址
 * @property int $telephone_verified_at 手机号验证时间
 * @property-read int $buyer_type 账户类型：BuyerType，不校验是否是 seller
 * @property-read bool $is_partner 是否是 seller
 * @property-read bool $is_eu_vat_buyer 是否是欧盟buyer
 * @property-read string $valid_mask_telephone 获取验证过的打星的手机号
 * @property int|null $telephone_country_code_id 手机号国家码, oc_telephone_country_code 表的 id
 * @property-read \App\Models\Customer\TelephoneCountryCode $telephoneCountryCode
 */
class Customer extends EloquentModel
{
    protected $table = 'oc_customer';
    protected $primaryKey = 'customer_id';

    protected $fillable = [
        'customer_group_id',
        'store_id',
        'language_id',
        'firstname',
        'lastname',
        'nickname',
        'user_number',
        'email',
        'telephone',
        'fax',
        'cart',
        'wishlist',
        'newsletter',
        'address_id',
        'custom_field',
        'status',
        'safe',
        'token',
        'code',
        'date_added',
        'user_mode',
        'line_of_credit',
        'country_id',
        'additional_flag',
        'giga_mall_id',
        'logistics_customer_name',
        'accounting_type',
        'prepare_email',
        'vat',
        'company_name',
        'company_address',
        'trusteeship',
        'account_attributes',
        'service_agreement',
        'telephone_verified_at',
        'telephone_country_code_id',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'country_id');
    }

    public function getCurrencyAttribute()
    {
        return $this->country->currency;
    }

    public function store()
    {
        return $this->hasOne(CustomerPartnerToCustomer::class, 'customer_id');
    }

    public function seller()
    {
        return $this->hasOne(CustomerPartnerToCustomer::class, 'customer_id', 'customer_id');
    }

    public function buyer()
    {
        return $this->hasOne(Buyer::class, 'buyer_id', 'customer_id');
    }

    public function groupdesc()
    {
        return $this->hasOne(CustomerGroupDescription::class, 'customer_group_id', 'customer_group_id');
    }

    public function telephoneCountryCode()
    {
        return $this->hasOne(TelephoneCountryCode::class, 'id', 'telephone_country_code_id');
    }

    public function getTelephoneAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['telephone'] ?? null);
    }

    public function getCompanyNameAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['company_name'] ?? null);
    }

    public function getCompanyAddressAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['company_address'] ?? null);
    }

    public function getRegisterAddressAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['register_address'] ?? null);
    }

    public function getFullNameAttribute()
    {
        return html_entity_decode($this->firstname . $this->lastname);
    }

    private $_isPartner = null;

    /**
     * 是否是 seller
     * @return bool
     */
    public function getIsPartnerAttribute()
    {
        if ($this->_isPartner === null) {
            $this->_isPartner = app(CustomerRepository::class)->checkIsSeller($this->customer_id);
        }
        return $this->_isPartner;
    }

    /**
     * buyer 的账户类型，一件代发/上门取货
     * 不校验是否是 seller
     * @return int
     */
    public function getBuyerTypeAttribute(): int
    {
        return in_array($this->customer_group_id, COLLECTION_FROM_DOMICILE)
            ? BuyerType::PICK_UP
            : BuyerType::DROP_SHIP;
    }

    /**
     * 获取验证过的打星的手机号（没验证过的显示完整的手机号）
     * @return string
     */
    public function getValidMaskTelephoneAttribute(): string
    {
        $countryCode = ($this->telephone_country_code_id ? ('+' . $this->telephoneCountryCode->code . ' ') : '');
        if ($this->telephone_verified_at <= 0) {
            return $countryCode . $this->telephone;
        }
        return $countryCode . StringHelper::maskCellphone($this->telephone);
    }

    private $_isEuVatBuyer = null;
    /**
     * 是否是德国免税buyer
     * @return bool
     */
    public function getIsEuVatBuyerAttribute(): bool
    {
        if ($this->_isEuVatBuyer === null) {
            if (!$this->is_partner && $this->buyer) {
                $this->_isEuVatBuyer = $this->country_id == CountryEnum::GERMANY && $this->buyer->vat_type == BuyerVATType::EUROPEAN_UNION;
            } else {
                $this->_isEuVatBuyer = false;
            }
        }
        return $this->_isEuVatBuyer;
    }
}
