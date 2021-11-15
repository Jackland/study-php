<?php

namespace App\Models\Product;

use App\Models\Future\FuturesProductLock;
use App\Models\Margin\ProductLock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Class Product
 * @package App\Models\Product
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
 * @property string|null $manufacturer 亚马逊侧爬虫获取制造商
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
 * @property int $subtract
 * @property int $minimum
 * @property int $sort_order
 * @property int $status
 * @property int $viewed
 * @property string $date_added
 * @property string|null $date_modified
 * @property int $price_display
 * @property int $quantity_display
 * @property int $combo_flag 组合SKU标志位：0-非组合SKU；1-组合SKU
 * @property int $buyer_flag 是否允许单独售卖，1允许，0不允许
 * @property int|null $downloaded 素材包下载次数
 * @property int $is_deleted 软删除标志位，1-软删除 0-正常数据
 * @property int $part_flag 是否为配件0：非配件，1：配件
 * @property int|null $is_once_available 是否曾经上过架 0 否 1是
 * @property float|null $freight 运费(单位为当前币种)
 * @property float|null $package_fee 打包费(单位为当前币种)
 * @property float|null $weight_kg 重 单位千克
 * @property float|null $length_cm 长 单位厘米
 * @property float|null $width_cm 宽 单位厘米
 * @property float|null $height_cm 高 单位厘米
 * @property string|null $sync_qty_date 同步上架库存更新时间
 * @property int $product_type 产品类型，用于搜索过滤，product_type字典值维护在oc_setting表，code:product_type_dic
 * @property string|null $name_ori
 * @property int $danger_flag 商品危险品标识 0:非危险品; 1:危险品
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Product\ProductSetInfo[] $comboProducts
 * @property-read int|null $combo_products_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Product\SysBatch[] $sysBatch
 * @property-read int|null $sys_batch_count
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereAHref($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereAsin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereBrand($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereBuyerFlag($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereComboFlag($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereDateAdded($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereDateAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereDateModified($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereDownloaded($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereEan($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereFreight($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereFromTheManuf($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereHeightCm($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereIsOnceAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereIsbn($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereJan($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereLength($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereLengthClassId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereLengthCm($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereManufacturer($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereManufacturerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereMinimum($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereMpn($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereNameOri($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product wherePackageFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product wherePartFlag($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product wherePoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product wherePriceDisplay($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereProductType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereQuantityDisplay($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereShipping($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereSku($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereStockStatusId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereSubtract($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereSyncQtyDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereTaxClassId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereUpc($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereViewed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereWeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereWeightClassId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereWeightKg($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereWidth($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Product whereWidthCm($value)
 * @mixin \Eloquent
 */
class Product extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'product_id';

    /**
     * Product constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->table = 'oc_product';
        parent::__construct($attributes);
    }

    /**
     * @param $sku
     * @return \Illuminate\Support\Collection|null
     */
    public function getProductsBySku($sku)
    {
        if (empty($sku)) {
            return null;
        }
        return DB::connection('mysql_proxy')
            ->table($this->table . ' as p')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'ctp.customer_id')
            ->select(['p.product_id', 'p.sku', 'c.country_id', 'p.image'])
            ->where([
                ['p.is_deleted', '=', 0],
                ['p.buyer_flag', '=', 1],
            ])
            ->whereIn('p.sku', $sku)
            ->whereNotIn('c.customer_id', [696, 694, 838, 908, 746, 340, 631, 491, 907])
            ->get();
    }

    /**
     * @param $product_id
     * @return int
     */
    public function checkIsOverSize($product_id)
    {
        return DB::connection('mysql_proxy')
            ->table('oc_product as p')
            ->join('oc_product_to_tag as ptt', 'ptt.product_id', '=', 'p.product_id')
            ->join('oc_tag as t', 't.tag_id', '=', 'ptt.tag_id')
            ->where([
                ['p.product_id', '=', $product_id],
                ['t.tag_id', '=', 1],
                ['t.status', '=', 1]
            ])
            ->count('*');
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getShipmentTimeFiles()
    {
        return DB::connection('mysql_proxy')
            ->table('oc_shipment_time')
            ->select(['country_id', 'file_path', 'file_name'])
            ->distinct()
            ->get();
    }

    /**
     * @param $product_id
     * @return \Illuminate\Support\Collection
     */
    public function getProductImages($product_id)
    {
        return DB::connection('mysql_proxy')
            ->table('oc_product_image')
            ->select(['image'])
            ->where('product_id', $product_id)
            ->orderBy('sort_order')
            ->get();

    }


    public static function updateByProductIds($product_ids, $data)
    {
        return self::whereIn('product_id', $product_ids)
            ->update($data);
    }

    /**
     * 获取产品在库库存
     * @return int
     * user：wangjinxin
     * date：2020/4/11 9:55
     */
    public function getInStockQuantity()
    {
        $combo = $this->comboProducts()->get();
        if ($combo->isNotEmpty()) {
            $qty = [];
            foreach ($combo as $item) {
                /** @var self $son_product */
                $son_product = static::find($item->set_product_id);
                $temp_qty = $son_product->getInStockQuantity();
                $qty[] = (int)floor($temp_qty / $item['qty']);
            }
            return !empty($qty) ? (int)min($qty) : 0;
        }

        return (int)$this->sysBatch()->sum('onhand_qty');
    }

    /**
     * 获取产品计算锁定库存 包含现货 期货
     * user：wangjinxin
     * date：2020/4/21 13:36
     */
    public function getComputeLockQty()
    {
        $margin_lock = (new ProductLock())->getProductMarginComputeQty($this->product_id);
        $futures_lock = (new FuturesProductLock())->getProductFuturesComputeQty($this->product_id);

        return $margin_lock + $futures_lock;
    }

    public function comboProducts()
    {
        return $this->hasMany(ProductSetInfo::class, 'product_id');
    }

    public function sysBatch()
    {
        return $this->hasMany(SysBatch::class, 'product_id');
    }

    public function tags()
    {
        return $this->hasMany(Tag::class, 'product_id');
    }
    public function description()
    {
        // 该关联其实还存在 language_id 的限制，但由于目前都是 1 所有忽略，后期可以转为使用 morphOne
        return $this->hasOne(ProductDescription::class, 'product_id', 'product_id');
    }
}
