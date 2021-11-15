<?php

namespace App\Models\Warehouse;

use Framework\Model\EloquentModel;

/**
 * App\Models\Warehouse\ReceiptsOrderShippingOrderBook
 *
 * @property int $id
 * @property int $receive_order_id 入库单表ID
 * @property string $company_name 公司名称
 * @property string $address 地址
 * @property string $contacts 联系人
 * @property string $contact_number 联系电话
 * @property string|null $consignee 收货人
 * @property string|null $notify_party 到货受通知人
 * @property int $is_self_bond 是否自有Bond 0:否 1:是
 * @property string|null $bond_title Bond抬头
 * @property string|null $bond_address Bond地址
 * @property string|null $bond_cin Bond CIN(CBP Identification Number)
 * @property string $marks_numbers 箱唛
 * @property int $container_load 箱量
 * @property string|null $shipping_list 运输信息列表JSON [{"description":"xx","hscode":"xx","qty":2,"weight":"xx","volume":"xx"}]
 * @property int $terms_of_delivery 贸易方式: 1.FOB 2.DDP
 * @property int $is_use_trailer 是否使用拖车 1:是 0:否
 * @property string|null $trailer_address 拖车地址
 * @property string|null $trailer_contact 拖车联系方式
 * @property string|null $special_product_type 特殊商品类型IDs(xx,xxx,xxxx) 1.危险品 2.反倾销 3.带电池
 * @property string|null $remark 备注
 * @property \Illuminate\Support\Carbon $create_time 添加时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\ReceiptsOrderShippingOrderBook newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\ReceiptsOrderShippingOrderBook newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\ReceiptsOrderShippingOrderBook query()
 * @mixin \Eloquent
 */
class ReceiptsOrderShippingOrderBook extends EloquentModel
{
    protected $table = 'tb_sys_receipts_order_shipping_order_book';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'receive_order_id',
        'company_name',
        'address',
        'contacts',
        'contact_number',
        'consignee',
        'notify_party',
        'is_self_bond',
        'bond_title',
        'bond_address',
        'bond_cin',
        'marks_numbers',
        'container_load',
        'shipping_list',
        'terms_of_delivery',
        'is_use_trailer',
        'trailer_address',
        'trailer_contact',
        'special_product_type',
        'remark',
        'create_time',
        'update_time',
    ];
}
