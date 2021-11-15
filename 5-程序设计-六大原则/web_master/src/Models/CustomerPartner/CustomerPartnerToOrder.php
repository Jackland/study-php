<?php

namespace App\Models\CustomerPartner;

use App\Models\Order\Order;
use Framework\Model\EloquentModel;

/**
 * App\Models\CustomerPartner\CustomerPartnerToOrder
 *
 * @property int $id
 * @property int $order_id
 * @property int $customer_id
 * @property int $product_id
 * @property int $order_product_id
 * @property float $price
 * @property float $quantity
 * @property string $shipping
 * @property float $shipping_rate
 * @property string $payment
 * @property float $payment_rate
 * @property float $admin
 * @property float $customer
 * @property float $shipping_applied
 * @property string $commission_applied
 * @property string $currency_code
 * @property string $currency_value
 * @property string $details
 * @property int $paid_status
 * @property string $date_added
 * @property int $order_product_status
 * @property string|null $option_data
 * @property int $seller_access
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToOrder newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToOrder newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToOrder query()
 * @mixin \Eloquent
 * @property-read \App\Models\Order\Order $order
 */
class CustomerPartnerToOrder extends EloquentModel
{
    protected $table = 'oc_customerpartner_to_order';

    protected $fillable = [
        'order_id',
        'customer_id',
        'product_id',
        'order_product_id',
        'price',
        'quantity',
        'shipping',
        'shipping_rate',
        'payment',
        'payment_rate',
        'admin',
        'customer',
        'shipping_applied',
        'commission_applied',
        'currency_code',
        'currency_value',
        'details',
        'paid_status',
        'date_added',
        'order_product_status',
        'option_data',
        'seller_access',
    ];

    public function order()
    {
        return $this->hasOne(Order::class, 'order_id', 'order_id');
    }
}
