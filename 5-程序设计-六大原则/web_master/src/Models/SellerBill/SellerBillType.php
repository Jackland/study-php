<?php

namespace App\Models\SellerBill;

use Framework\Model\EloquentModel;

/**
 * App\Models\SellerBill\SellerBillType
 *
 * @property int $type_id 类别ID
 * @property string $code 类别码,用于PHP国际化语言文件名拼接
 * @property string $description 类别中文名
 * @property int $parent_type_id 上一级别的类别id 最高级别的类别时为0
 * @property int $rank_id 类别层级 从1开始递增，范围越细，数字越大
 * @property int $is_revenue 是否是seller的收入项，为财务录入项时，也为1
 * @property int $sort 类别显示排序 从1开始递增，越小越优先
 * @property int $status 是否启用
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillType newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillType newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillType query()
 * @mixin \Eloquent
 */
class SellerBillType extends EloquentModel
{
    protected $table = 'tb_seller_bill_type';
    protected $primaryKey = 'type_id';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'code',
        'description',
        'parent_type_id',
        'rank_id',
        'is_revenue',
        'sort',
        'status',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];
}
