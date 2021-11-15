<?php

namespace App\Models\Seller;

use Framework\Model\EloquentModel;

/**
 * App\Models\Seller\SellerAccountApply
 *
 * @property int $id 自增主键ID
 * @property int $seller_client_id seller客户主键ID
 * @property int $contract_id seller签约主键ID
 * @property int $country_id 建店国别
 * @property string $email 用户邮箱
 * @property string|null $password 初始密码
 * @property int $account_type seller帐号属性 同oc_customer的核算类型accounting_type
 * @property int $account_default_status 账户状态 0:禁用 1:启用
 * @property int $store_default_status 店铺发布状态 0:未发布 1:已发布
 * @property string $client_number 客户号
 * @property string $store_name 店铺名称
 * @property int $approve_status 账号审核状态 1:申请中（默认） 2:审核中（等待分配客户号） 3:审核通过（等待分配客户号） 4:审核未通过（未分配客户号）5:开户成功  6:审核中（已分配客户号） 7:审核通过（已分配客户号） 8:审核未通过（已分配客户号）
 * @property int $credit_account_type 收款账户类型 0:银行账户 1:第三方帐号信息(P卡)
 * @property string $currency_code 币种CODE
 * @property string|null $bank_name 银行账号信息-银行名称
 * @property string|null $bank_account 银行账号信息-银行账户
 * @property string|null $bank_site_name 银行账号信息-开户行名称
 * @property string|null $swift_code 银行账号信息-联行号Swiftcode
 * @property string|null $vat_number VAT Number
 * @property string|null $third_pay_name 第三方账号（派安盈）-名称
 * @property string|null $payoneer_id 第三方账号（派安盈）-ID
 * @property string|null $payoneer_email 第三方账号（派安盈）-账号
 * @property int $confirm_file 收款账户确认函
 * @property int|null $vat_file VAT 证书
 * @property int $bd_user_id 当前跟进BD
 * @property int $manager_id 运营顾问
 * @property int $build_connection 建立联系
 * @property string|null $remark 开户申请备注
 * @property string $create_username 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $update_username 更新人
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerAccountApply newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerAccountApply newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerAccountApply query()
 * @mixin \Eloquent
 * @property int|null $ascription_type 注册类型：0：企业注册 1：个人注册
 * @property int|null $agent_operation_type 是否是代运营：0-非代运营；1-代运营
 * @property int|null $not_support_self_delivery 是否是非seller自发货：1-是；0-否
 * @property int|null $not_support_store_goods 是否是不支持囤货：1-是；0-否
 * @property int $can_create_time_limit 是否可以创建限时限量活动：1-是；0-否
 * @property string $legal_person_name 法定代表人姓名
 * @property string $taxpayer_number 纳税人识别号
 * @property string|null $third_part_currency 第三方账号信息-币种
 */
class SellerAccountApply extends EloquentModel
{
    protected $table = 'tb_seller_account_apply';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'seller_client_id',
        'contract_id',
        'country_id',
        'email',
        'password',
        'account_type',
        'account_default_status',
        'store_default_status',
        'client_number',
        'store_name',
        'approve_status',
        'credit_account_type',
        'currency_code',
        'bank_name',
        'bank_account',
        'bank_site_name',
        'swift_code',
        'vat_number',
        'third_pay_name',
        'payoneer_id',
        'payoneer_email',
        'confirm_file',
        'vat_file',
        'bd_user_id',
        'manager_id',
        'build_connection',
        'remark',
        'create_username',
        'create_time',
        'update_username',
        'update_time',
    ];
}
