<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * \App\Models\Customer\Customer
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
 * @property float|null $line_of_credit 信用额度
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
 * @property int $service_agreement 是否同意服务协议 0.未同意 1.已同意
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereAccountAttributes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereAccountingType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereAdditionalFlag($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereAddressId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereCart($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereCompanyAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereCompanyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereCustomField($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereCustomerGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereDateAdded($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereFax($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereFirstname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereGigaMallId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereLanguageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereLastname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereLineOfCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereLogisticsCustomerName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereNewsletter($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereNickname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer wherePrepareEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereSafe($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereSalt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereServiceAgreement($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereStoreId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereTelephone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereTrusteeship($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereUserMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereUserNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereVat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Customer whereWishlist($value)
 * @mixin \Eloquent
 * @property-read \App\Models\Customer\Country $country
 */
class Customer extends Model
{
    protected $table = 'oc_customer';
    public $timestamps = false;
    protected $connection = 'mysql_proxy';
    protected $primaryKey = 'customer_id';

    public static function getCustomerInfoById($customerId)
    {
        return self::select(['customer_id', 'nickname as username', 'user_number', 'email'])
            ->where(['customer_id' => $customerId])
            ->first();
    }

    public static function isPartner($customer_id)
    {
        $customer_id = \DB::connection('mysql_proxy')
            ->table('oc_customerpartner_to_customer')
            ->where('customer_id', $customer_id)
            ->where('is_partner', 1)
            ->value('customer_id');
        return $customer_id ? true : false;
    }

    public static function disableAccount($customer_id)
    {
        return self::where('customer_id', $customer_id)->update(['status' => 0]);
    }

    public static function getCustomerStatus($customer_id)
    {
        return self::where('customer_id', $customer_id)->value('status');
    }

    /**
     * 通过用户IDs获取存在部分的ID
     *
     * @param $ids
     * @return Collection
     */
    public static function getCustomerIdsByIds($ids)
    {
        return self::whereIn('customer_id', $ids)
            ->pluck('customer_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'country_id');
    }
}
