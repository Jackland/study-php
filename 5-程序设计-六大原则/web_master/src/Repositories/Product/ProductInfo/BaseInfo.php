<?php

namespace App\Repositories\Product\ProductInfo;

use App\Components\Storage\StorageCloud;
use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Product\ProductCustomFieldType;
use App\Enums\Product\ProductStatus;
use App\Enums\Product\ProductType;
use App\Helper\SummernoteHtmlEncodeHelper;
use App\Models\Product\Category;
use App\Models\Product\Option\ProductImage;
use App\Models\Product\Option\ProductPackageFile;
use App\Models\Product\Option\ProductPackageImage;
use App\Models\Product\Package\ProductPackageOriginalDesignImage;
use App\Models\Product\Product;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Seller\SellerInfo\BaseInfo as SellerBaseInfo;
use App\Services\Product\ProductService;
use App\Widgets\ImageToolTipWidget;
use Framework\Helper\Json;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @property-read string $name 产品名称，已经废弃，使用 getName() 代替
 * @property-read bool $priceVisible 价格是否可见，已经废弃，使用 getPriceVisible() 代替
 * @property-read bool $qtyVisible 库存是否可见，已经废弃，使用 getQtyVisible() 代替
 * @property-read bool $delicacyManagementPrice 精细化价格，已经废弃，使用 getDelicacyPrice() 代替
 * @property-read float $price 普通价格，已经废弃，使用 getPrice() 代替
 */
class BaseInfo
{
    use RequestCachedDataTrait;
    use Traits\BaseInfo\CustomerDelicacySolveTrait;
    use Traits\BaseInfo\LoadRelationsTrait;
    use Traits\BaseInfo\CustomerSupportTrait;

    // BaseInfoExt 中的是将相关数据写到一起的 Trait
    use Traits\BaseInfoExt\PriceInfoTrait;
    use Traits\BaseInfoExt\FeeInfoTrait;
    use Traits\BaseInfoExt\SizeInfoTrait;
    use Traits\BaseInfoExt\OptionInfoTrait;

    // 产品可用的条件
    const AVAILABLE_CONDITION = [
        'is_deleted' => 0,
        'status' => ProductStatus::ON_SALE,
        'buyer_flag' => 1,
    ];

    // 外部不可调用整个对象，如果需要，请定义单独的属性字段供外部直接获取属性
    private $product;

    // 外部可访问的属性和方法定义成 public
    /**
     * 产品ID
     * @var int
     */
    public $id;
    /**
     * SKU
     * @var string
     */
    public $sku;
    /**
     * UPC
     * @var string
     */
    public $upc;
    /**
     * MPN
     * @var string
     */
    public $mpn;
    /**
     * 上架库存
     * @var int
     */
    public $quantity;
    /**
     * 素材包被下载次数
     * @var int
     */
    public $download_count;
    /**
     * 是否是 combo
     * @var bool
     */
    public $is_combo;
    /**
     * 产品类型
     * @see ProductType
     * @var int
     */
    public $product_type;
    /**
     * 状态
     * @see ProductStatus
     * @var int
     */
    public $status;
    /**
     * 是否已上架
     * @var bool
     */
    public $is_on_shelf;
    /**
     * 是否被删除
     * @var bool
     */
    public $is_deleted;
    /**
     * buyer 是否可以单独购买
     * @var bool
     */
    public $can_buyer_buy;

    public function __construct(Product $product)
    {
        $this->product = $product;

        $this->id = $product->product_id;
        $this->sku = $product->sku;
        $this->upc = $product->upc;
        $this->mpn = $product->mpn;
        $this->quantity = $product->quantity;
        $this->download_count = (int)$product->downloaded;
        $this->is_combo = (bool)$product->combo_flag;
        $this->product_type = $product->product_type;
        $this->status = $product->status;
        $this->is_on_shelf = $product->status === ProductStatus::ON_SALE;
        $this->is_deleted = (bool)$product->is_deleted;
        $this->can_buyer_buy = (bool)$product->buyer_flag;
    }

    public function __get($name)
    {
        // 兼容被 deprecated 的属性
        if ($name === 'name') {
            return $this->getName();
        }
        if ($name === 'priceVisible') {
            return $this->getPriceVisible();
        }
        if ($name === 'qtyVisible') {
            return $this->getQtyVisible();
        }
        if ($name === 'delicacyManagementPrice') {
            return $this->getDelicacyPrice();
        }
        if ($name === 'price') {
            return $this->getPrice();
        }
        throw new InvalidArgumentException("{$name} not exist");
    }

    /**
     * 获取产品名称
     * @return string
     */
    public function getName(): string
    {
        $this->loadRelations('description');
        return html_entity_decode($this->product->description->name);
    }

    /**
     * 获取商品描述
     *
     * @return string
     */
    public function getDescription(): string
    {
        $this->loadRelations('description');
        return SummernoteHtmlEncodeHelper::decode($this->product->description->description);
    }

    /**
     * 获取产品主图
     * @param int|null $width
     * @param int|null $height
     * @return string
     */
    public function getImage(?int $width = null, ?int $height = null): string
    {
        /** @var \ModelToolImage $toolImage */
        $toolImage = load()->model('tool/image');
        return $toolImage->resize($this->product->image, $width, $height);
    }

    /**
     * 获取产品图片列表
     *
     * @param int|null $width
     * @param int|null $height
     * @return array
     */
    public function getImageList(?int $width = null, ?int $height = null): array
    {
        $this->loadRelations('images');
        /** @var \ModelToolImage $toolImage */
        $toolImage = load()->model('tool/image');
        return $this->product->images->sortByDesc('sort_order')
            ->map(function (ProductImage $productImage) use ($width, $height, $toolImage) {
                return $toolImage->resize($productImage->image, $width, $height);
            })->toArray();
    }

    /**
     * 获取产品其他图片
     *
     * @param int|null $width
     * @param int|null $height
     * @return array
     * @throws \Exception
     */
    public function getOtherMaterialImagesList(?int $width = null, ?int $height = null): array
    {
        $this->loadRelations('packageImages');
        /** @var \ModelToolImage $toolImage */
        $toolImage = load()->model('tool/image');
        return $this->product->packageImages
            ->map(function (ProductPackageImage $productImage) use ($width, $height, $toolImage) {
                return $toolImage->resize($productImage->image, $width, $height);
            })->toArray();
    }

    /**
     * 获取原创图片
     * @param int|null $width
     * @param int|null $height
     * @return array
     * @throws \Exception
     */
    public function getOriginalDesignImages(?int $width = null, ?int $height = null): array
    {
        $this->loadRelations('packageOriginalDesignImages');
        /** @var \ModelToolImage $toolImage */
        $toolImage = load()->model('tool/image');
        return $this->product->packageOriginalDesignImages
            ->map(function (ProductPackageOriginalDesignImage $productImage) use ($width, $height, $toolImage) {
                return $toolImage->resize($productImage->image, $width, $height);
            })->toArray();
    }

    /**
     * 库存是否可见
     * @return bool
     */
    public function getQtyVisible(): bool
    {
        return $this->delicacyQtyVisible;
    }

    /**
     * 获取展示的可售库存
     * @param array $config
     * @return string
     */
    public function getShowQty(array $config = []): string
    {
        $config = array_merge([
            'invisibleValue' => '**',
            'overMax' => null,
        ], $config);

        if (!$this->getQtyVisible()) {
            return $config['invisibleValue'];
        }

        if ($config['overMax']) {
            if ($this->quantity > $config['overMax']) {
                return $config['overMax'] . '+';
            }
        }
        return (string)$this->quantity;
    }

    /**
     * 获取展示的产品标签，C / LTL 等
     * @return string
     */
    public function getShowTags(): string
    {
        $this->loadRelations('tags');
        return $this->product->tags->map(function ($tag) {
            return ImageToolTipWidget::widget([
                'tip' => $tag->description,
                'image' => $tag->icon,
            ])->render();
        })->implode(' ');
    }

    /**
     * 复杂交易标签
     * @var array
     */
    private $complexTags = [];

    /**
     * 追加复杂交易的标签
     * @param $tag
     */
    public function addComplexTag($tag)
    {
        $this->complexTags[] = $tag;
    }

    /**
     * 获取复杂交易的标签
     * @return array
     */
    public function getComplexTags(): array
    {
        return array_unique($this->complexTags);
    }

    /**
     * 产品是否可用
     * 逻辑如下：设置了精细化以精细化为准，否则按照产品的状态判断
     * @return bool
     */
    public function getIsAvailable(): bool
    {
        if ($this->delicacyProductVisible !== null) {
            return $this->delicacyProductVisible;
        }

        foreach (self::AVAILABLE_CONDITION as $attribute => $value) {
            if ($this->product->{$attribute} != $value) {
                return false;
            }
        }
        return true;
    }

    /**
     * seller id
     * @return int
     */
    public function getSellerId(): int
    {
        $this->loadRelations('customerPartner');
        return $this->product->customerPartner->customer_id;
    }

    /**
     * seller 的信息
     * @return SellerBaseInfo
     */
    public function getSellerBaseInfo(): SellerBaseInfo
    {
        return $this->requestCachedData([__CLASS__, __FUNCTION__, $this->id, 'v1'], function () {
            $this->loadRelations(['customerPartner', 'customerPartner.seller']);
            return new SellerBaseInfo($this->product->customerPartner, $this->product->customerPartner->seller);
        });
    }

    /**
     * 国家 id
     * @return int
     */
    public function getCountryId(): int
    {
        return $this->getSellerBaseInfo()->country_id;
    }

    /**
     * 产品分类
     * @return Collection|Category[]
     */
    public function getCategories(): Collection
    {
        $this->loadRelations('categories');
        return $this->product->categories;
    }

    /**
     * 产品类型名称
     * 该类型名字与 product_type 字段无关
     * @return string Combo Item|Replacement Part|General Item
     */
    public function getProductTypeName(): string
    {
        return app(ProductRepository::class)->getProductTypeNameForBuyer($this->product->attributesToArray());
    }

    /**
     * 素材包文件
     * @return array
     */
    public function getPackageFiles(): array
    {
        $this->loadRelations('packageFiles');
        return $this->product->packageFiles->map(function (ProductPackageFile $productPackageFile) {
            $tmp = [
                'orig_url' => $productPackageFile->file,
                'name' => $productPackageFile->origin_file_name,
            ];
            app(ProductService::class)->resolveImageItem($tmp, true);
            return $tmp;
        })->toArray();
    }

    /**
     * 获取商品认证文件列表
     */
    public function getCertificationDocuments(): array
    {
        $this->loadRelations('certificationDocuments');
        $formatCertificationDocuments = [];
        foreach ($this->product->certificationDocuments as $certificationDocument) {
            $formatCertificationDocuments[] = [
                'orig_url' => $certificationDocument->url,
                'url' => StorageCloud::image()->getUrl($certificationDocument->url, ['check-exist' => false]),
                'type_id' => $certificationDocument->type_id,
                'type_name' => $certificationDocument->type_name,
                'name' => $certificationDocument->name,
            ];
        }
        return $formatCertificationDocuments;
    }

    /**
     * 填充物 product_exts.filter的描述
     * @return string
     */
    public function getFilterName(): string
    {
        $this->loadRelations('ext.fillerOptionValue');
        $filterName = '';
        if ($this->product->ext && $this->product->ext->fillerOptionValue) {
            $filterName = $this->product->ext->fillerOptionValue->name;
        }
        return $filterName;
    }

    /**
     * 退返品政策数据
     * @return array{return_warranty: array, return_warranty_text: string}
     */
    public function getReturnWarrantyInfo(): array
    {
        $this->loadRelations('description');
        return [
            // 退返品政策数据
            'return_warranty' => $this->product->description->return_warranty ? Json::decode($this->product->description->return_warranty) : [],
            // 退返品政策文本
            'return_warranty_text' => SummernoteHtmlEncodeHelper::decode($this->product->description->return_warranty_text)
        ];
    }

    /**
     * 获取商品退返品率以及退返品率描述
     * @return array{return_rate:float,return_rate_str:string}
     */
    public function getReturnRate(): array
    {
        $this->loadRelations('productCrontab');
        $returnRate = [
            'return_rate' => 0.00,
            'return_rate_str' => '',
        ];
        if (!$this->product->productCrontab) {
            return $returnRate;
        }
        $returnRate['return_rate'] = floatval($this->product->productCrontab->return_rate);
        if ($returnRate['return_rate'] > 10) {
            $returnRate['return_rate_str'] = 'High';
        } elseif ($returnRate['return_rate'] > 4) {
            $returnRate['return_rate_str'] = 'Moderate';
        } else {
            $returnRate['return_rate_str'] = 'Low';
        }
        return $returnRate;
    }

    /**
     * 获取商品原产地信息
     * @return array{country_id: int, code: string, name: string}
     */
    public function getOriginPlaceInfo(): array
    {
        $this->loadRelations('ext.originPlaceCountry');
        $data = [
            'country_id' => 0,
            'code' => '',
            'name' => '',
        ];
        if ($this->product->ext && $this->product->ext->originPlaceCountry) {
            $data['country_id'] = $this->product->ext->originPlaceCountry->country_id;
            $data['code'] = $this->product->ext->originPlaceCountry->iso_code_3;
            $data['name'] = $this->product->ext->originPlaceCountry->name;
        }

        return $data;
    }

    /**
     *  定制化标识 0否 1是
     * @return bool
     */
    public function getIsCustomize(): bool
    {
        $this->loadRelations('ext');
        if (!$this->product->ext) {
            return false;
        }
        return boolval($this->product->ext->is_customize);
    }

    /**
     * 获取用户自定义字段
     * @param array|int $filedTypes [1,2] or 1
     * @return array
     * @see ProductCustomFieldType 类型参考该枚举
     */
    public function getCustomerFiled($filedTypes = []): array
    {
        if (!$filedTypes) {
            $filedTypes = ProductCustomFieldType::getValues();
        } elseif (!is_array($filedTypes)) {
            $filedTypes = (array)$filedTypes;
        }
        $this->loadRelations('customFields');
        $customerFields = [];
        foreach ($filedTypes as $filedType) {
            $customerFields[$filedType] = array_values($this->product->customFields
                ->where('type', $filedType)->sortBy('sort')
                ->makeHidden(['id', 'product_id', 'type', 'sort'])
                ->toArray());
        }
        return $customerFields;
    }

    /**
     * 获取危险品信息
     * @return array{danger_flag:int,danger_fee:float}
     */
    public function getDangerInfo(): array
    {
        return [
            'danger_flag' => $this->product->danger_flag,
            'danger_fee' => $this->product->danger_fee,
        ];
    }
}
