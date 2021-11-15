<?php

namespace App\Models\SellerBill;

use Framework\Model\EloquentModel;

/**
 * App\Models\SellerBill\ServiceFeeCategoryDetail
 *
 * @property int $id 主键ID
 * @property int|null $service_fee_category_id 服务费用分类id
 * @property string|null $service_project 服务项目
 * @property string|null $service_project_english 服务项目(英文)
 * @property int|null $sort 排序
 * @property int|null $status 状态 0：启用  1：禁用
 * @property int|null $delete_flag 状态 0：未删除  1：删除
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property string|null $code 服务项目编码
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\ServiceFeeCategoryDetail newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\ServiceFeeCategoryDetail newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\ServiceFeeCategoryDetail query()
 * @mixin \Eloquent
 */
class ServiceFeeCategoryDetail extends EloquentModel
{
    protected $table = 'tb_service_fee_category_detail';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'service_fee_category_id',
        'service_project',
        'service_project_english',
        'sort',
        'status',
        'delete_flag',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
        'code',
    ];
}
