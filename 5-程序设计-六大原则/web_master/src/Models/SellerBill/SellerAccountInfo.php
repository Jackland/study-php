<?php

namespace App\Models\SellerBill;

use Framework\Model\EloquentModel;

/**
 * App\Models\SellerBill\SellerAccountInfo
 *
 * @property int $id 自增主键 自增主键
 * @property int $seller_id SellerId (customer_id 关联oc_customer)
 * @property string|null $legal_person 法定代表人
 * @property string|null $swift_code SwiftCode
 * @property string|null $tel 联系电话
 * @property string|null $tel_bak 备用联系电话
 * @property string $company 公司名称
 * @property string $address 公司地址
 * @property string|null $bank_name 开户行名称
 * @property string|null $bank_account 银行账号
 * @property string|null $bank_address 银行地址
 * @property int $account_type 1:对公账户 2:对私账户 3:P卡
 * @property string|null $p_id
 * @property string|null $p_email
 * @property int $apply_id 最新适用的审批记录ID
 * @property int $status 账户启用状态 1：启用0：禁用
 * @property int $is_deleted 是否删除
 * @property string|null $memo 备注
 * @property string $create_user_name 创建者
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerAccountInfo newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerAccountInfo newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerAccountInfo query()
 * @mixin \Eloquent
 */
class SellerAccountInfo extends EloquentModel
{
    protected $table = 'tb_sys_seller_account_info';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'seller_id',
        'legal_person',
        'swift_code',
        'tel',
        'tel_bak',
        'company',
        'address',
        'bank_name',
        'bank_account',
        'bank_address',
        'account_type',
        'p_id',
        'p_email',
        'apply_id',
        'status',
        'is_deleted',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];

    public function getTelAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['tel'] ?? null);
    }

    public function getTekBakAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['tel_bak'] ?? null);
    }

    public function getCompanyAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['company'] ?? null);
    }

    public function getAddressAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['address'] ?? null);
    }

    public function getPEmailAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['p_email'] ?? null);
    }
}
