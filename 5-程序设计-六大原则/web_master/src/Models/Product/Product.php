<?php

namespace App\Models\Product;

use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductCustomFieldType;
use App\Enums\Product\ProductFeeType;
use App\Enums\Product\ProductStatus;
use App\Enums\Product\ProductType;
use App\Enums\Seller\SellerPriceStatus;
use App\Models\Customer\Customer;
use App\Models\CustomerPartner\ProductGroup;
use App\Models\CustomerPartner\ProductGroupLink;
use App\Models\Enum\StockStatus;
use App\Models\Link\CustomerPartnerToProduct;
use App\Models\Product\Option\Option;
use App\Models\Product\Option\OptionValueDescription;
use App\Models\Product\Option\ProductImage;
use App\Models\Product\Option\ProductOptionValue;
use App\Models\Product\Option\ProductPackageFile;
use App\Models\Product\Option\ProductPackageImage;
use App\Models\Product\Option\ProductPackageVideo;
use App\Models\Product\Option\SellerPrice;
use App\Models\Product\Package\ProductPackageOriginalDesignImage;
use Framework\Model\Eloquent\Builder;
use Framework\Model\EloquentModel;
use Illuminate\Support\Collection;

/**
 * App\Models\Product\Product
 *
 * @property int $product_id
 * @property string $model
 * @property string $sku
 * @property string $upc
 * @property string $ean
 * @property string $jan
 * @property string $isbn
 * @property string $mpn
 * @property string $asin
 * @property string $location
 * @property int $quantity
 * @property int $stock_status_id
 * @property string|null $image
 * @property int $manufacturer_id
 * @property \App\Models\Product\Manufacturer $manufacturer 亚马逊侧爬虫获取制造商
 * @property string|null $from_the_manuf 亚马逊侧制造商信息
 * @property string|null $aHref 亚马逊售卖链接
 * @property string|null $brand 品牌
 * @property int $shipping
 * @property float $price
 * @property int $points
 * @property int $tax_class_id
 * @property string|null $date_available
 * @property float $weight
 * @property int $weight_class_id
 * @property float $length
 * @property float $width
 * @property float $height
 * @property int $length_class_id
 * @property bool $subtract
 * @property int $minimum
 * @property int $sort_order
 * @property int $status 0已下架,1已上架,-1待上架
 * @property int $viewed
 * @property \Illuminate\Support\Carbon $date_added
 * @property \Illuminate\Support\Carbon|null $date_modified
 * @property int $price_display
 * @property int $quantity_display
 * @property int $combo_flag 组合SKU标志位：0-非组合SKU；1-组合SKU
 * @property int $buyer_flag 是否允许单独售卖，1允许，0不允许
 * @property int|null $downloaded 素材包下载次数
 * @property int $is_deleted 软删除标志位，1-软删除 0-正常数据
 * @property int $part_flag 是否为配件0：非配件，1：配件
 * @property int|null $is_once_available 是否曾经上过架 0 否 1是
 * @property float $freight 运费(单位为当前币种)
 * @property float $package_fee 打包费(单位为当前币种)
 * @property float|null $weight_kg 重 单位千克
 * @property float|null $length_cm 长 单位厘米
 * @property float|null $width_cm 宽 单位厘米
 * @property float|null $height_cm 高 单位厘米
 * @property string|null $sync_qty_date 同步上架库存更新时间
 * @property int $product_type 产品类型，用于搜索过滤，product_type字典值维护在oc_setting表，code:product_type_dic
 * @property float $peak_season_surcharge 产品运费的旺季附加费
 * @property int $product_audit_id 产品信息的最新审核ID
 * @property int $price_audit_id 产品价格的最新审核ID
 * @property int $need_install 是否需要安装 0不需要 1需要
 * @property string|null $product_size 产品尺寸
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Product\Product[] $associatesProducts
 * @property-read int|null $associates_products_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Product\Batch[] $batches
 * @property-read int|null $batches_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Product\Category[] $categories
 * @property-read int|null $categories_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Product\ProductSetInfo[] $parentProducts
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Product\ProductSetInfo[] $combos
 * @property-read int|null $combos_count
 * @property-read \App\Models\Customer\Customer $customerPartner
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\CustomerPartner\ProductGroupLink[] $customerPartnerProductGroupLinks
 * @property-read int|null $customer_partner_product_group_links_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\CustomerPartner\ProductGroup[] $customerPartnerProductGroups
 * @property-read int|null $customer_partner_product_groups_count
 * @property-read \App\Models\Link\CustomerPartnerToProduct $customerPartnerToProduct
 * @property-read \App\Models\Product\ProductDescription $description
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Product\Option\ProductImage[] $images
 * @property-read int|null $images_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Product\Option\ProductPackageFile[] $packageFiles
 * @property-read int|null $package_files_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Product\Option\ProductPackageImage[] $packageImages
 * @property-read int|null $package_images_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Product\Option\ProductPackageVideo[] $packageVideos
 * @property-read int|null $package_videos_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Product\Option\ProductOptionValue[] $productOptionValues
 * @property-read int|null $product_option_values_count
 * @property-read \App\Models\Enum\StockStatus $stockStatus
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\Product\Tag[] $tags
 * @property-read int|null $tags_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Product newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Product newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Product query()
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Product\ProductFee[] $fee
 * @property-read int|null $fee_count
 * @property-read int|null $parent_products_count
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product available()
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\Product\Option\OptionValueDescription[] $productNewOptionNames
 * @property-read int|null $product_new_option_names_count
 * @property string|null $non_sellable_on 不可售卖平台，buyer_flag = 1生效，使用英文逗号分隔，
 * @property-read \App\Models\Product\ProductExts $ext
 * @property-read \App\Models\Product\ProductCustomField[]|Collection $customFields 所有的用户自定义字段
 * @property-read \App\Models\Product\ProductCustomField[] $informationCustomFields
 * @property-read \App\Models\Product\ProductCustomField[] $dimensionCustomFields
 * @property-read \App\Models\Product\ProductCertificationDocument[] $certificationDocuments
 * @property-read \App\Models\Product\ProductFee $packageFeeDropShip 一件代发打包费
 * @property-read \App\Models\Product\ProductFee $packageFeeWillCall 自提货打包费
 * @property-read \App\Models\Product\Option\SellerPrice $sellerPriceNoEffect 待生效的未来价格
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Product\Package\ProductPackageOriginalDesignImage[] $packageOriginalDesignImages
 * @property-read int|null $package_original_design_images_count
 * @property-read ProductCrontab $productCrontab
 * @property int $danger_flag 商品危险品标识 0:非危险品; 1:危险品
 * @property float $danger_fee 商品危险品附加费
 */
class Product extends EloquentModel
{
    public const CREATED_AT = 'date_added';
    public const UPDATED_AT = 'date_modified';

    protected $table = 'oc_product';
    protected $primaryKey = 'product_id';
    public $timestamps = true;

    protected $fillable = [
        'model',
        'sku',
        'upc',
        'ean',
        'jan',
        'isbn',
        'mpn',
        'asin',
        'location',
        'quantity',
        'stock_status_id',
        'image',
        'manufacturer_id',
        'manufacturer',
        'from_the_manuf',
        'aHref',
        'brand',
        'shipping',
        'price',
        'points',
        'tax_class_id',
        'date_available',
        'weight',
        'weight_class_id',
        'length',
        'width',
        'height',
        'length_class_id',
        'subtract',
        'minimum',
        'sort_order',
        'status',
        'viewed',
        'date_added',
        'date_modified',
        'price_display',
        'quantity_display',
        'combo_flag',
        'buyer_flag',
        'downloaded',
        'is_deleted',
        'part_flag',
        'is_once_available',
        'freight',
        'package_fee',
        'weight_kg',
        'length_cm',
        'width_cm',
        'height_cm',
        'sync_qty_date',
        'product_type',
        'product_audit_id',
        'price_audit_id',
        'need_install',
        'product_size',
        'peak_season_surcharge',
        'danger_flag',
        'danger_fee'
    ];


    public function stockStatus()
    {
        return $this->hasOne(StockStatus::class, 'stock_status_id', 'stock_status_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'oc_product_to_category', 'product_id', 'category_id');
    }

    public function customerPartnerProductGroups()
    {
        return $this->belongsToMany(ProductGroup::class, 'oc_customerpartner_product_group_link', 'product_id', 'product_group_id');
    }

    public function customerPartnerProductGroupLinks()
    {
        return $this->hasMany(ProductGroupLink::class, 'product_id');
    }

    public function customerPartnerToProduct()
    {
        return $this->hasOne(CustomerPartnerToProduct::class, 'product_id', 'product_id');
    }

    public function customerPartner()
    {
        return $this->hasOneThrough(
            Customer::class,
            CustomerPartnerToProduct::class,
            'product_id',
            'customer_id',
            'product_id',
            'customer_id'
        );
    }

    public function description()
    {
        // 该关联其实还存在 language_id 的限制，但由于目前都是 1 所有忽略，后期可以转为使用 morphOne
        return $this->hasOne(ProductDescription::class, 'product_id', 'product_id');
    }

    public function ext()
    {
        return $this->hasOne(ProductExts::class, 'product_id', 'product_id');
    }

    public function customFields()
    {
        return $this->hasMany(ProductCustomField::class, 'product_id');
    }

    public function informationCustomFields()
    {
        return $this->hasMany(ProductCustomField::class, 'product_id')->where('type', ProductCustomFieldType::INFORMATION);
    }

    public function dimensionCustomFields()
    {
        return $this->hasMany(ProductCustomField::class, 'product_id')->where('type', ProductCustomFieldType::DIMENSIONS);
    }

    public function certificationDocuments()
    {
        return $this->hasMany(ProductCertificationDocument::class, 'product_id');
    }

    public function manufacturer()
    {
        return $this->hasOne(Manufacturer::class, 'manufacturer_id', 'manufacturer_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'oc_product_to_tag', 'product_id', 'tag_id')->orderBy('sort_order');
    }

    public function fee()
    {
        return $this->hasMany(ProductFee::class,'product_id');
    }

    public function productCrontab()
    {
        return $this->hasOne(ProductCrontab::class, 'product_id');
    }

    /**
     * 生效的（已上架的普通可售卖的产品）
     * @param Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAvailable(Builder $query)
    {
        $alias = $query->getModel()->getAlias();
        $prefix = $alias ? $alias . '.' : '';

        return $query->where($prefix . 'is_deleted', YesNoEnum::NO)
            ->where($prefix . 'status', ProductStatus::ON_SALE)
            ->where($prefix . 'buyer_flag', YesNoEnum::YES)
            ->where($prefix . 'product_type', ProductType::NORMAL);
    }

    /**
     * 近30天浏览量key
     *
     * @return string
     */
    public static function  lastThirtyDaysVisitKey()
    {
        return 'b2b:last30visit';
    }

    /**
     * 近3天是否浏览过商品
     * @param int $customer_id
     *
     * @return string
     */
    public static function  lastThirdDaysVisitedCustomerIdKey($customer_id = 0)
    {
        return 'b2b:3DayVisits:' . $customer_id .':' . date('Y.m.d');
    }

    public function combos()
    {
        return $this->hasMany(ProductSetInfo::class, 'product_id');
    }

    public function parentProducts()
    {
        return $this->hasMany(ProductSetInfo::class, 'set_product_id', 'product_id');
    }

    public function batches()
    {
        return $this->hasMany(Batch::class, 'product_id', 'product_id')
            ->where('onhand_qty', '>', 0);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('sort_order');
    }

    public function packageFiles()
    {
        return $this->hasMany(ProductPackageFile::class, 'product_id');
    }

    public function packageImages()
    {
        return $this->hasMany(ProductPackageImage::class, 'product_id');
    }

    public function packageVideos()
    {
        return $this->hasMany(ProductPackageVideo::class, 'product_id');
    }

    public function packageOriginalDesignImages()
    {
        return $this->hasMany(ProductPackageOriginalDesignImage::class, 'product_id');
    }

    public function productOptionValues()
    {
        return $this->hasMany(ProductOptionValue::class, 'product_id');
    }

    public function associatesProducts()
    {
        return $this->belongsToMany(Product::class, 'oc_product_associate', 'product_id', 'associate_product_id');
    }

    /**
     * 关联产品新属性名称
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function productNewOptionNames()
    {
        return $this->belongsToMany(OptionValueDescription::class, 'oc_product_option_value', 'product_id', 'option_value_id', 'product_id', 'option_value_id')
            ->whereIn('oc_product_option_value.option_id', [Option::COLOR_OPTION_ID, Option::MATERIAL_OPTION_ID]);
    }

    public function packageFeeDropShip()
    {
        return $this->hasOne(ProductFee::class, 'product_id', 'product_id')
            ->where('type', ProductFeeType::PACKAGE_FEE_DROP_SHIP);
    }

    public function packageFeeWillCall()
    {
        return $this->hasOne(ProductFee::class, 'product_id', 'product_id')
            ->where('type', ProductFeeType::PACKAGE_FEE_WILL_CALL);
    }

    public function sellerPriceNoEffect()
    {
        return $this->hasOne(SellerPrice::class, 'product_id', 'product_id')
            ->where('status', SellerPriceStatus::WAIT)
            ->orderByDesc('id');
    }
}
