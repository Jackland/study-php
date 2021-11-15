<?php

namespace App\Models\HomePage;

use App\Models\Product\Category;
use App\Models\Product\Channel\ChannelParamConfig;
use Framework\Model\EloquentModel;

/**
 * App\Models\HomePage\HomePageOperationDetail
 *
 * @property int $id 主键id
 * @property int $header_id 首页运营位主键id
 * @property int|null $classify_type 类型 0：频道 1：分类 运营位分类类型使用
 * @property string|null $classify_name 子运营位名称 运营位分类类型使用
 * @property string|null $show_copywriter 显示文案 运营位分类类型使用
 * @property int|null $image_menu_id 显示图片menuId 运营位分类类型使用
 * @property int|null $product_id 产品id 运营位产品类型使用
 * @property string|null $sku sku产品编号 itemCode 运营位产品类型使用
 * @property string|null $store_name 店铺名称 运营位产品类型使用
 * @property int|null $country_id 国家id
 * @property int $sort 顺序 1-99范围
 * @property int|null $status 是否有效 0：无效 1：有效
 * @property int $is_delete 是否删除 0：否 1：是
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $create_name 创建人
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $update_name 修改人
 * @property int|null $classify_id 子运营位id 运营位分类类型使用
 * @property-read mixed $image
 * @property-read mixed $url
 * @property-read \App\Models\HomePage\HomePageOperation $homePageOperation
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\HomePage\HomePageOperationDetail newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\HomePage\HomePageOperationDetail newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\HomePage\HomePageOperationDetail query()
 * @mixin \Eloquent
 */
class HomePageOperationDetail extends EloquentModel
{
    protected $table = 'tb_home_page_operation_detail';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $appends = ['url'];

    protected $fillable = [
        'header_id',
        'classify_type',
        'classify_name',
        'show_copywriter',
        'image_menu_id',
        'product_id',
        'sku',
        'store_name',
        'country_id',
        'sort',
        'status',
        'is_delete',
        'create_time',
        'create_name',
        'update_time',
        'update_name',
    ];

    public function homePageOperation(){
        return $this->belongsTo(HomePageOperation::class, 'header_id');
    }

    public function getUrlAttribute()
    {
        if(!$this->classify_type){
            $type = strtolower(str_ireplace(' ','_', ChannelParamConfig::where('id',$this->classify_id)->value('name')));
            return url(['product/channel/getChannelData', 'type' => $type]);
        }else{
            // 根据分类需要判断分类是否有效
            $exists = Category::where(['category_id' => $this->classify_id, 'status' => 1, 'is_deleted' => 0])->exists();
            if(!$exists){
                return null;
            }
            return url(['product/category', 'category_id' => $this->classify_id]);
        }

    }

}
