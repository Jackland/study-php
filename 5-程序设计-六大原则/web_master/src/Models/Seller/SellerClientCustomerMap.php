<?php

namespace App\Models\Seller;

use Framework\Model\EloquentModel;

/**
 * App\Models\Seller\SellerClientCustomerMap
 *
 * @property int $id 自增主键ID
 * @property int $seller_client_id 客户ID
 * @property int $apply_id 开户记录ID
 * @property int $seller_id sellerID
 * @property int|null $is_send 邮件是否发送  0 未发送 1 发送
 * @property \Illuminate\Support\Carbon|null $send_date 发送时间
 * @property string $create_username 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $update_username 更新人
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerClientCustomerMap newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerClientCustomerMap newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerClientCustomerMap query()
 * @mixin \Eloquent
 * @property-read \App\Models\Seller\SellerAccountApply $sellerAccountApply
 */
class SellerClientCustomerMap extends EloquentModel
{
    protected $table = 'tb_seller_client_customer_map';

    protected $dates = [
        'send_date',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'seller_client_id',
        'apply_id',
        'seller_id',
        'is_send',
        'send_date',
        'create_username',
        'create_time',
        'update_username',
        'update_time',
    ];

    public function sellerAccountApply()
    {
        return $this->hasOne(SellerAccountApply::class,'id','apply_id');
    }
}
