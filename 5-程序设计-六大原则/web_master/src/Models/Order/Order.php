<?php

namespace App\Models\Order;

use App\Models\Customer\Customer;
use App\Models\Link\OrderAssociated;
use Framework\Model\EloquentModel;
use Illuminate\Support\Carbon;

/**
 * App\Models\Order\Order
 *
 * @property int $order_id
 * @property int $invoice_no
 * @property string $invoice_prefix
 * @property int $store_id
 * @property string $store_name
 * @property string $store_url
 * @property int $customer_id
 * @property int $customer_group_id
 * @property string $firstname
 * @property string $lastname
 * @property string $email
 * @property string $telephone
 * @property string $fax
 * @property string $custom_field
 * @property string $payment_firstname
 * @property string $payment_lastname
 * @property string $payment_company
 * @property string $payment_address_1
 * @property string $payment_address_2
 * @property string $payment_city
 * @property string $payment_postcode
 * @property string $payment_country
 * @property int $payment_country_id
 * @property string $payment_zone
 * @property int $payment_zone_id
 * @property string $payment_address_format
 * @property string $payment_custom_field
 * @property string $payment_method
 * @property string $payment_code
 * @property string $shipping_firstname
 * @property string $shipping_lastname
 * @property string $shipping_company
 * @property string $shipping_address_1
 * @property string $shipping_address_2
 * @property string $shipping_city
 * @property string $shipping_postcode
 * @property string $shipping_country
 * @property int $shipping_country_id
 * @property string $shipping_zone
 * @property int $shipping_zone_id
 * @property string $shipping_address_format
 * @property string $shipping_custom_field
 * @property string $shipping_method
 * @property string $shipping_code
 * @property string $comment
 * @property string $total
 * @property int $order_status_id
 * @property int $affiliate_id
 * @property string $commission
 * @property int $marketing_id
 * @property string $tracking
 * @property int $language_id
 * @property int $currency_id
 * @property string $currency_code
 * @property string $currency_value
 * @property string $ip
 * @property string $forwarded_ip
 * @property string $user_agent
 * @property string $accept_language
 * @property Carbon $date_added
 * @property Carbon $date_modified
 * @property string|null $current_currency_value 订单当前汇率
 * @property int|null $delivery_type 发货类型
 * @property int|null $cloud_logistics_id 云送仓的oc_order_cloud_logistics_id
 * @property-read \App\Models\Customer\Customer $buyer
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Link\OrderAssociated[] $orderAssociates
 * @property-read int|null $order_associates_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Order\OrderProduct[] $orderProducts
 * @property-read int|null $order_products_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\Order newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\Order newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\Order query()
 * @mixin \Eloquent
 */
class Order extends EloquentModel
{
    protected $table = 'oc_order';
    protected $primaryKey = 'order_id';

    protected $dates = [
        'date_added',
        'date_modified',
    ];

    protected $fillable = [
        'invoice_no',
        'invoice_prefix',
        'store_id',
        'store_name',
        'store_url',
        'customer_id',
        'customer_group_id',
        'firstname',
        'lastname',
        'email',
        'telephone',
        'fax',
        'custom_field',
        'payment_firstname',
        'payment_lastname',
        'payment_company',
        'payment_address_1',
        'payment_address_2',
        'payment_city',
        'payment_postcode',
        'payment_country',
        'payment_country_id',
        'payment_zone',
        'payment_zone_id',
        'payment_address_format',
        'payment_custom_field',
        'payment_method',
        'payment_code',
        'shipping_firstname',
        'shipping_lastname',
        'shipping_company',
        'shipping_address_1',
        'shipping_address_2',
        'shipping_city',
        'shipping_postcode',
        'shipping_country',
        'shipping_country_id',
        'shipping_zone',
        'shipping_zone_id',
        'shipping_address_format',
        'shipping_custom_field',
        'shipping_method',
        'shipping_code',
        'comment',
        'total',
        'order_status_id',
        'affiliate_id',
        'commission',
        'marketing_id',
        'tracking',
        'language_id',
        'currency_id',
        'currency_code',
        'currency_value',
        'ip',
        'forwarded_ip',
        'user_agent',
        'accept_language',
        'date_added',
        'date_modified',
        'current_currency_value',
        'delivery_type',
        'cloud_logistics_id',
    ];

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class, 'order_id');
    }

    public function orderAssociates()
    {
        return $this->hasMany(OrderAssociated::class, 'order_id');
    }
}
