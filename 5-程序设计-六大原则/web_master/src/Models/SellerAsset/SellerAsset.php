<?php

namespace App\Models\SellerAsset;

use App\Models\Customer\Customer;
use Framework\Model\EloquentModel;

/**
 * App\Models\SellerAsset\SellerAsset
 *
 * @property int $id 主键ID自增
 * @property int|null $customer_id Seller ID
 * @property string|null $ocean_freight 海运费
 * @property string|null $tariff 关税
 * @property string|null $unloading_charges 卸货费
 * @property string|null $storage_fee 仓租（定时计算）
 * @property string|null $collateral_value 在库抵押物金额
 * @property string|null $shipping_value 在途抵押物金额
 * @property string|null $life_money_deposit 人民币押金(单位:人民币)
 * @property string|null $supply_chain_finance 供应链金融
 * @property string|null $asset_adjustment 资产调整金额
 * @property int|null $alarm_level 0:未触发警报线 1:一级警报线 2:二级警报线
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property-read \App\Models\Customer\Customer|null $seller
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerAsset\SellerAsset newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerAsset\SellerAsset newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerAsset\SellerAsset query()
 * @mixin \Eloquent
 */
class SellerAsset extends EloquentModel
{
    protected $table = 'oc_seller_asset';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'customer_id',
        'ocean_freight',
        'tariff',
        'unloading_charges',
        'storage_fee',
        'collateral_value',
        'shipping_value',
        'life_money_deposit',
        'supply_chain_finance',
        'asset_adjustment',
        'alarm_level',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];

    public function seller()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
