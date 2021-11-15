<?php

namespace App\Models\Rebate;

use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * \App\Models\Rebate\RebateAgreementProduct
 *
 * @property int $id 自增id
 * @property int|null $agreement_id 返点协议id
 * @property int|null $product_id 返点协议中真正参与的id
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property string|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property string|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property int|null $buyer_id
 * @property-read \App\Models\Product\Product|null $product
 * @property-read \App\Models\Rebate\RebateAgreement|null $rebateAgreement
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementProduct newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementProduct newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementProduct query()
 * @mixin \Eloquent
 */
class RebateAgreementProduct extends EloquentModel
{
    protected $table = 'oc_rebate_agreement_product';

    public function rebateAgreement()
    {
        return $this->belongsTo(RebateAgreement::class, 'agreement_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
