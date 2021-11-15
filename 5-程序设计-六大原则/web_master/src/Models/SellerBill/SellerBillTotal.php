<?php

namespace App\Models\SellerBill;

use Framework\Model\EloquentModel;

/**
 * App\Models\SellerBill\SellerBillTotal
 *
 * @property int $id 自增主键
 * @property int|null $header_id 头表ID
 * @property int|null $type_id 类型ID
 * @property string|null $code Code
 * @property string|null $title Title
 * @property float|null $value Value
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillTotal newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillTotal newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillTotal query()
 * @mixin \Eloquent
 */
class SellerBillTotal extends EloquentModel
{
    protected $table = 'tb_seller_bill_total';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'header_id',
        'type_id',
        'code',
        'title',
        'value',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];
}
