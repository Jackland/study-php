<?php

namespace App\Models\SellerBill;

use Framework\Model\EloquentModel;

/**
 * App\Models\SellerBill\ServiceFeeCategory
 *
 * @property int $id 主键ID
 * @property string|null $service_fee_category 服务费用分类
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\ServiceFeeCategory newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\ServiceFeeCategory newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\ServiceFeeCategory query()
 * @mixin \Eloquent
 */
class ServiceFeeCategory extends EloquentModel
{
    protected $table = 'tb_service_fee_category';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'service_fee_category',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];
}
