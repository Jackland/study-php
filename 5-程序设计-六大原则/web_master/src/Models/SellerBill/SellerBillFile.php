<?php

namespace App\Models\SellerBill;

use Framework\Model\EloquentModel;

/**
 * App\Models\SellerBill\SellerBillFile
 *
 * @property int $id 自增主键
 * @property int|null $seller_bill_id 头表ID
 * @property string|null $file_name 文件名称
 * @property int|null $file_size 文件大小
 * @property string|null $file_type 文件类型
 * @property string|null $file_path 文件路径 (相对路径)
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillFile newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillFile newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillFile query()
 * @mixin \Eloquent
 */
class SellerBillFile extends EloquentModel
{
    protected $table = 'tb_sys_seller_bill_file';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'seller_bill_id',
        'file_name',
        'file_size',
        'file_type',
        'file_path',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];
}
