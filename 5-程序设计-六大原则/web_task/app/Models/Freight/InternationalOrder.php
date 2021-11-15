<?php

namespace App\Models\Freight;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Freight\InternationalOrder
 *
 * @property int $id
 * @property int|null $country_id 国别id
 * @property int|null $country_code_mapping_id tb_sys_country_code_mapping.id
 * @property string|null $country_en 国家(英文)
 * @property string|null $country_ch 国家(中文)
 * @property string|null $country_code 国别code
 * @property string|null $freight_fee 英国运费
 * @property string|null $freight2_fee 德国运费
 * @property string|null $freight5_fee 德国运费
 * @property string|null $freight10_fee 德国运费
 * @property string|null $freight15_fee 德国运费
 * @property string|null $freight25_fee 德国运费
 * @property string|null $freight30_fee 德国运费
 * @property string|null $freight40_fee 德国运费
 * @property string|null $clearance_fee 清关费
 * @property string|null $memo 备注
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $create_username 创建人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string|null $update_username 更新人
 * @property string|null $program_code 版本号
 */
class InternationalOrder extends Model
{
    protected $table = 'tb_sys_international_order';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'country_id',
        'country_code_mapping_id',
        'country_en',
        'country_ch',
        'country_code',
        'freight_fee',
        'freight2_fee',
        'freight5_fee',
        'freight10_fee',
        'freight15_fee',
        'freight25_fee',
        'freight30_fee',
        'freight40_fee',
        'clearance_fee',
        'memo',
        'create_time',
        'create_username',
        'update_time',
        'update_username',
        'program_code',
    ];
}
