<?php

namespace App\Models\Admin;

use Framework\Model\EloquentModel;

/**
 * App\Models\Admin\User
 *
 * @property int $id 自增主键
 * @property string $login_name 登录名
 * @property string $username 用户名
 * @property string $employee_id 员工编号
 * @property string $password 密码
 * @property int|null $department_id 部门ID
 * @property int $status 帐号状态
 * @property int|null $login_limit 登录限制（1:允许登录;0:限制登录）
 * @property \Illuminate\Support\Carbon|null $expiration_time 过期时间（为NULL表示永不过期）
 * @property string|null $email 邮箱
 * @property string|null $mobile_phone 手机号码
 * @property string|null $tel 电话号码
 * @property int|null $customer_id B2B用户表对应的CustomerId
 * @property int|null $picture_id tb_upload_file.id
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 更新者
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Admin\User newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Admin\User newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Admin\User query()
 * @mixin \Eloquent
 */
class User extends EloquentModel
{
    protected $table = 'tb_sys_user';

    protected $dates = [
        'expiration_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'login_name',
        'username',
        'employee_id',
        'password',
        'department_id',
        'status',
        'login_limit',
        'expiration_time',
        'email',
        'mobile_phone',
        'tel',
        'customer_id',
        'picture_id',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
    ];
}
