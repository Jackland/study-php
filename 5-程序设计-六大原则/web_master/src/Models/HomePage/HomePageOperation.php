<?php

namespace App\Models\HomePage;

use App\Models\Product\Category;
use Framework\Model\EloquentModel;

/**
 * App\Models\HomePage\HomePageOperation
 *
 * @property int $id 主键id
 * @property int $type 运营位类型 0：分类 1：产品
 * @property string $name 运营位名称
 * @property string|null $show_copywriter 显示文案
 * @property int|null $image_menu_id 图片地址menuId
 * @property int $sort 显示顺序 范围1-99
 * @property int $status 是否有效 0：无效 1：有效
 * @property int $is_delete 是否删除 0：否 1：是
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $create_name 创建人
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $update_name 修改名称
 * @property int $operation_id 运营位id 产品类型0
 * @property-read mixed $image
 * @property-read mixed $url
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\HomePage\HomePageOperationDetail[] $homePageOperationDetails
 * @property-read int|null $home_page_operation_details_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\HomePage\HomePageOperationDetail[] $homePageOperationValidDetails
 * @property-read int|null $home_page_operation_valid_details_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\HomePage\HomePageOperation newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\HomePage\HomePageOperation newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\HomePage\HomePageOperation query()
 * @mixin \Eloquent
 */
class HomePageOperation extends EloquentModel
{
    protected $table = 'tb_home_page_operation';

    protected $dates = [
        'create_time',
        'update_time',
    ];
    protected $appends = ['url'];

    protected $fillable = [
        'type',
        'name',
        'show_copywriter',
        'image_menu_id',
        'sort',
        'status',
        'is_delete',
        'create_time',
        'create_name',
        'update_time',
        'update_name',
    ];

    public function getUrlAttribute()
    {
        if ($this->type) {
            return null;
        }

        $exists = Category::where(['category_id' => $this->operation_id, 'status' => 1, 'is_deleted' => 0])->exists();
        if(!$exists){
            return null;
        }

        return url(['product/category', 'category_id' => $this->operation_id]);
    }

    public function homePageOperationDetails()
    {
        return $this->hasMany(HomePageOperationDetail::class, 'header_id')->orderBy('sort', 'desc');
    }

    public function homePageOperationValidDetails()
    {
        return $this->hasMany(HomePageOperationDetail::class, 'header_id')
            ->where([
                //'status' => 1,
                'is_delete' => 0,
            ])
            ->orderBy('sort');
    }

}
