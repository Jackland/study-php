<?php

namespace App\Models\Rma;

use App\Models\Customer\Customer;
use Framework\Model\EloquentModel;

/**
 * App\Models\Rma\YzcRmaFile
 *
 * @property int $id 自增主键
 * @property int $rma_id rma_id
 * @property string|null $file_name 文件名称
 * @property int|null $size 文件大小
 * @property string|null $file_path 文件路径
 * @property int $buyer_id BuyerId(seller_id)
 * @property bool|null $type 1:buyer上传的附件2：seller上传的附件
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property-read \App\Models\Customer\Customer $buyer
 * @property-read \App\Models\Rma\YzcRmaOrder $rma
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rma\YzcRmaFile newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rma\YzcRmaFile newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rma\YzcRmaFile query()
 * @mixin \Eloquent
 */
class YzcRmaFile extends EloquentModel
{
    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = 'update_time';
    protected $table = 'oc_yzc_rma_file';
    public $timestamps = true;

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id', 'customer_id');
    }

    public function rma()
    {
        return $this->belongsTo(YzcRmaOrder::class, 'rma_id', 'id');
    }

}
