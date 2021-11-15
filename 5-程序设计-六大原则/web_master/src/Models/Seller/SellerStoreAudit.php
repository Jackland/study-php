<?php

namespace App\Models\Seller;

use App\Models\Customer\Customer;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use Framework\Model\EloquentModel;

/**
 * App\Models\Seller\SellerStoreAudit
 *
 * @property int $id
 * @property int $seller_id SellerID
 * @property int $seller_store_id seller_store 表的ID
 * @property int $type 审核类型：10店铺首页，20店铺介绍页
 * @property string $audit_data 待审核数据，json
 * @property int $status 审核状态：5预览，10草稿，20待审核，30通过，40驳回
 * @property string|null $refuse_reason 驳回原因
 * @property string $preview_key 预览的key
 * @property int $is_deleted 是否删除
 * @property \Illuminate\Support\Carbon|null $audit_at 审核时间
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 修改时间
 * @property-read \App\Models\CustomerPartner\CustomerPartnerToCustomer $seller
 * @property-read \App\Models\Customer\Customer $sellerCustomer
 * @property-read \App\Models\Seller\SellerStore $sellerStore
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerStoreAudit newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerStoreAudit newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerStoreAudit query()
 * @mixin \Eloquent
 * @property string|null $audit_user 审核人
 */
class SellerStoreAudit extends EloquentModel
{
    protected $table = 'oc_seller_store_audit';
    public $timestamps = true;

    protected $dates = [
        'audit_at',
    ];

    protected $fillable = [
        'seller_id',
        'seller_store_id',
        'type',
        'audit_data',
        'status',
        'refuse_reason',
        'is_deleted',
        'audit_at',
    ];

    public function sellerStore()
    {
        return $this->hasOne(SellerStore::class, 'id', 'seller_store_id');
    }

    public function seller()
    {
        return $this->hasOne(CustomerPartnerToCustomer::class, 'seller_id', 'seller_id');
    }

    public function sellerCustomer()
    {
        return $this->hasOne(Customer::class, 'customer_id', 'seller_id');
    }
}
