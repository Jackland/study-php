<?php

namespace App\Models\CWF;

use App\Models\File\FileUpload;
use Framework\Model\EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * \App\Models\CWF\CloudWholesaleFulfillmentFileUpload
 *
 * @property int $id 主键
 * @property int $file_upload_id 关联oc_file_upload主键
 * @property bool $is_validate_success 对于文件中的内容是否校验通过
 * @property string|null $error_info 错误信息，校验失败的错误信息
 * @property string $create_time 创建时间
 * @property int|null $create_id create_id
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\CWF\CloudWholesaleFulfillmentFileExplain[] $explains
 * @property-read int|null $explains_count
 * @property-read \App\Models\File\FileUpload $fileUpload
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\CloudWholesaleFulfillmentFileUpload newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\CloudWholesaleFulfillmentFileUpload newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\CloudWholesaleFulfillmentFileUpload query()
 * @mixin \Eloquent
 */
class CloudWholesaleFulfillmentFileUpload extends EloquentModel
{
    protected $table = 'tb_cloud_wholesale_fulfillment_file_upload';

    protected $primaryKey = 'id';

    public function fileUpload(): BelongsTo
    {
        return $this->belongsTo(FileUpload::class, 'file_upload_id');
    }

    public function explains(): HasMany
    {
        return $this->hasMany(CloudWholesaleFulfillmentFileExplain::class, 'cwf_file_upload_id');
    }
}
