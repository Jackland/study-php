<?php

namespace App\Repositories\Product;

use App\Components\RemoteApi;
use App\Components\RemoteApi\B2BManager\DTO\Freight\FreightProductDTO;
use App\Components\Storage\StorageCloud;
use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Common\YesNoEnum;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Product\ProductCustomFieldType;
use App\Enums\Product\ProductStatus;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Helper\ProductHelper;
use App\Logging\Logger;
use App\Models\Product\ProductCustomField;
use App\Models\Product\ProductExts;
use App\Models\Stock\ReceiptsOrderDetail;
use App\Models\Link\CustomerPartnerToProduct;
use App\Enums\Track\CountryExts;
use App\Models\Link\ProductToTag;
use App\Models\Product\Product;
use App\Models\Product\ProductAssociate;
use App\Models\Product\ProductDescription;
use App\Models\Product\Tag;
use App\Models\Product\ProductSetInfo;
use App\Repositories\Futures\ContractRepository;
use App\Repositories\Margin\MarginRepository;
use App\Repositories\Product\Option\OptionValueRepository;
use App\Repositories\Product\ProductInfo\ComplexTransaction\FuturesInfoRepository;
use App\Repositories\Product\ProductInfo\ComplexTransaction\MarginInfoRepository;
use App\Repositories\Product\ProductInfo\ComplexTransaction\ProductQuoteInfoRepository;
use App\Repositories\Product\ProductInfo\ComplexTransaction\QuoteInfoRepository;
use App\Repositories\Product\ProductInfo\ComplexTransaction\RebateInfoRepository;
use App\Repositories\Product\ProductInfo\ProductInfoFactory;
use App\Repositories\Product\ProductInfo\ProductPriceRangeFactory;
use App\Repositories\Product\ProductInfo\Traits\BaseInfoExt\SizeInfoTrait;
use App\Repositories\Rebate\RebateRepository;
use App\Repositories\Setup\SetupRepository;
use App\Services\Product\ProductAuditService;
use Carbon\Carbon;
use App\Models\Product\Option\Option;
use App\Widgets\ImageToolTipWidget;
use Exception;
use Cart\Currency;
use ModelAccountCustomerpartner;
use ModelAccountCustomerpartnerMargin;
use ModelAccountCustomerpartnerProductGroup;
use ModelAccountCustomerpartnerRebates;
use ModelAccountwkquotesadmin;
use ModelCatalogProduct;
use ModelCommonProduct;
use ModelFuturesContract;
use ModelFuturesProduct;
use ModelToolImage;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use App\Models\Track\CountryExts as CountryExtsModel;
use ModelCommonCategory;
use Throwable;

class ProductRepository
{
    use RequestCachedDataTrait;

    /**
     * 获取一组商品的图片
     *
     * @param array $productIds
     *
     * @return array [product_id=>image]
     */
    public function getImageByProductId(array $productIds)
    {
        $productList = Product::whereIn('product_id', $productIds)->select(['product_id', 'image'])->get();
        return $productList->pluck('image', 'product_id')->toArray();
    }

    /**
     * 获取一组商品的sku
     * @param array $productIds
     * @return array [product_id=>sku]
     */
    public function getSkuByProductIds(array $productIds)
    {
        $productList = Product::whereIn('product_id', $productIds)->select(['product_id', 'sku'])->get();
        return $productList->pluck('sku', 'product_id')->toArray();
    }

    /**
     * 根据item_code 和 seller_id批量获取产品信息
     *
     * @param $itemCodeSeller [item_code => seller_id,...]
     *
     * @return array [item_code-seller_id=>[product_id,image,tags],...] ps:item_code 会转换成大写
     */
    public function getProductInfoBySellerItemCode($itemCodeSeller)
    {
        if (empty($itemCodeSeller)) {
            return [];
        }
        $product = Product::leftJoin('oc_customerpartner_to_product as ctp', 'oc_product.product_id', '=', 'ctp.product_id')
            ->with('tags')->select(['oc_product.product_id', 'oc_product.product_type', 'oc_product.image', 'oc_product.sku', 'ctp.customer_id']);
        //构建查询条件
        $product->where('product_type', 0)->where(function ($query) use ($itemCodeSeller) {
            foreach ($itemCodeSeller as $itemCode => $sellerId) {
                //这里没有考虑sku存在mpn里的情况，如果有问题，再调整
                $query->orWhere([
                    ['oc_product.sku', '=', $itemCode],
                    ['ctp.customer_id', '=', $sellerId]
                ]);
            }
        });
        $productList = $product->get();
        //开始组装返回数据
        $return = [];
        foreach ($productList as $productItem) {
            $return[strtoupper($productItem->sku) . '-' . $productItem->customer_id] = $this->formatProductShowInfo($productItem);
        }
        return $return;
    }

    /**
     * 根据商品id获取商品的信息
     *
     * @param int|array $productId 商品id
     * @return array [product_id=>[product_id,image,tags],...]
     */
    public function getProductInfoByProductId($productId)
    {
        if (empty($productId)) {
            return [];
        }
        $productList = Product::with('tags')
            ->select(['product_id', 'image', 'product_type','sku'])
            ->find($productId);
        //开始组装返回数据
        if ($productList instanceof Product) {
            return $this->formatProductShowInfo($productList);
        } else {
            $return = [];
            foreach ($productList as $productItem) {
                $return[$productItem->product_id] = $this->formatProductShowInfo($productItem);
            }
            return $return;
        }
    }

    /**
     * @param array $categoryIds
     * @param int $sellerId
     * @return array
     * @throws Exception
     */
    public function getProductIdsByCategoryIds($categoryIds, $sellerId = 0): array
    {
        $cats = [];
        /** @var ModelCommonCategory $modelCate */
        $modelCate = load()->model('common/category');
        foreach ((array)$categoryIds as $categoryId) {
            $cats = array_unique(array_merge($cats, array_column($modelCate->getSonCategories($categoryId),'category_id')));
        }
        $productIds = Product::query()->alias('p')
            ->leftJoin('oc_product_to_category as ptc', 'p.product_id', '=', 'ptc.product_id')
            ->whereIn('ptc.category_id', $cats)
            ->where([
                'p.buyer_flag' => 1,
                'p.status' => 1,
                'p.is_deleted' => 0,
            ])
            ->when($sellerId, function (\Framework\Model\Eloquent\Builder $q) use ($sellerId) {
                return $q->leftJoinRelations(['customerPartnerToProduct as ctp'])
                    ->where('ctp.customer_id', $sellerId);
            })
            ->pluck('p.product_id')
            ->toArray();

        return array_unique($productIds);
    }

    /**
     * 获取某个用户某个mpn的产品信息
     * @param int $customerId
     * @param string $mpn
     * @param array|string[] $column
     * @return Product
     */
    public function getProductInfoByCustomerIdAndMpn(int $customerId, string $mpn, array $column = ['*'])
    {
        return Product::query()->alias('p')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->where('p.mpn', $mpn)
            ->where('ctp.customer_id', $customerId)
            ->first($column);
    }

    /**
     * 相同国别存在sku为某个mpn值的产品
     * @param string $mpn
     * @param int|null $countryId
     * @return bool
     */
    public function hasSkuProductByMpnAndCountryId(string $mpn, ?int $countryId): bool
    {
        if (empty($mpn) || empty($countryId)) {
            return true;
        }

        return Product::queryRead()->alias('p')
            ->joinRelations('customerPartnerToProduct as ctp')
            ->join('oc_customer as c', 'ctp.customer_id', '=', 'c.customer_id')
            ->where('p.sku', strtoupper(trim($mpn)))
            ->where('c.country_id', $countryId)
            ->exists();
    }

    /**
     * 获取某个用户某个mpn的产品信息new
     * @param int $customerId
     * @param string $mpn
     * @param array|string[] $column
     * @return Product
     */
    public function getProductInfoByCustomerIdAndMpnInfo(int $customerId, string $mpn, array $column = ['p.*', 'ex.is_original_design'])
    {
        return Product::query()->alias('p')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_exts as ex', 'ex.product_id', '=', 'p.product_id')
            ->where('p.mpn', $mpn)
            ->where('ctp.customer_id', $customerId)
            ->first($column);
    }

    /**
     * 检测计费重量是否超标
     * @param $width
     * @param $height
     * @param $length
     * @param $weight
     * @param array $dimLimitWeightAndSeparateEnquiry
     * @return mixed
     */
    public function checkChargeableWeightExceed($width, $height, $length, $weight, $dimLimitWeightAndSeparateEnquiry = [])
    {
        if (empty($dimLimitWeightAndSeparateEnquiry)) {
            $dimLimitWeightAndSeparateEnquiry = $this->getDimLimitWeightAndSeparateEnquiry();
        }

        $limitWeight = max([($width * $height * $length) / $dimLimitWeightAndSeparateEnquiry['dim'], $weight]);

        return $limitWeight > $dimLimitWeightAndSeparateEnquiry['limit_weight'] && $dimLimitWeightAndSeparateEnquiry['separate_enquiry'];
    }

    /**
     * combo品验证是否超规格
     * @param array $productArr
     * @return integer
     */
    public function checkComboChargeableWeightExceed($productArr = [])
    {
        if (empty($productArr)) {
            return -1; //combo品异常
        }
        $dimLimitWeightAndSeparateEnquiry = $this->getDimLimitWeightAndSeparateEnquiry();
        if (!$dimLimitWeightAndSeparateEnquiry['separate_enquiry'] || !$dimLimitWeightAndSeparateEnquiry['dim']) {
            return 1; //没开启单独询价，直接不验证
        }
        $voWeight = 0;
        foreach ($productArr as $item) {
            $voWeight += max($item['length'] * $item['width'] * $item['height'] / $dimLimitWeightAndSeparateEnquiry['dim'], $item['weight']) * $item['quantity'];
        }
        if ($voWeight > $dimLimitWeightAndSeparateEnquiry['limit_weight']) {
            return -2; //超规格
        }
        return 1;
    }

    /**
     * 获取系统dim值和最大限制值，是否单独询价
     * @return array
     */
    public function getDimLimitWeightAndSeparateEnquiry()
    {
        $freightParamDetail = db('tb_freight_param_category as c')
            ->join('tb_freight_version as v', 'c.version_id', '=', 'v.id')
            ->selectRaw('c.*')
            ->where('v.status', 1)
            ->where('c.type', 2)
            ->first();

        $limitWeight = db('tb_freight_param_detail')->where('param_category_id', $freightParamDetail->id)->where('code', 'OVER_SPECIFICATION_TOTAL_WEIGHT')->value('value');
        $dim = db('tb_freight_param_detail')->where('param_category_id', $freightParamDetail->id)->where('code', 'DIM')->value('value');

        $quoteCategoryId = db('tb_freight_quote_category')->where('param_category_id', $freightParamDetail->id)
            ->where('version_id', $freightParamDetail->version_id)
            ->where('type', 3)
            ->value('id');

        $separateEnquiry = db('tb_freight_quote_detail')->where('quote_category_id', $quoteCategoryId)
            ->where('code', 'OVER_SPECIFICATION_SURCHARGE_QUOTE')
            ->value('value');

        //separate_enquiry  -1:单独询价
        return ['dim' => $dim, 'limit_weight' => $limitWeight, 'separate_enquiry' => $separateEnquiry == -1];
    }

    /**
     * 格式化商品数据
     *
     * @param Product $productItem 必须包含product_id、image、tags信息，如果对返回有修改，这里也要相应修改
     *
     * @return array [product_id,image,tags,product_name]
     */
    private function formatProductShowInfo(Product $productItem)
    {
        $tags = [];
        if ($productItem->tags->isNotEmpty()) {
            foreach ($productItem->tags as $tag) {
                $tags[] = ImageToolTipWidget::widget([
                    'tip' => $tag->description,
                    'image' => $tag->icon,
                    'class' => $tag->class_style
                ])->render();
            }
        }
        return [
            'product_id' => $productItem->product_id,
            'image' => $productItem->image,
            'product_type' => $productItem->product_type,
            'sku' => $productItem->sku,
            'tags' => $tags,
            'product_name' => $productItem->description->name,
            'full_image' => StorageCloud::image()->getUrl($productItem->image, ['w' => 60, 'h' => 60]),
        ];
    }

    /**
     * 获取商品的退返品参数
     * @param int $productId
     * @return array
     */
    public function getProductReturnWarranty($productId)
    {
        $proDescri = ProductDescription::query()->where('product_id', $productId)->first();
        if ($proDescri->return_warranty) {
            return json_decode($proDescri->return_warranty, true);
        }
        return app(ProductAuditRepository::class)->getStoreReturnWarranty();
    }

    /**
     * 获取(生成)商品sku码;此方法需要在生成product_id后及未插入oc_customerpartner_to_product表前调用
     * @param string $mpn
     * @param $comboFlag
     * @return string
     */
    public function getProductSku($mpn, $comboFlag = 0)
    {
        $customerId = customer()->getId();
        $accountType = customer()->getAccountType();
        $storeName = trim(customer()->getFirstName()) . trim(customer()->getLastName());
        if ($accountType != 2) { //外部账户
            return $mpn;
        }
        if ($comboFlag == 1) { //客户编号+S编号，如W501S00005
            $comboNum = db('oc_product as op')
                ->leftJoin('oc_customerpartner_to_product as octp', 'op.product_id', '=', 'octp.product_id')
                ->where('op.combo_flag', '=', 1)
                ->where('octp.customer_id', '=', $customerId)
                ->count();

            return $this->getOuterSellerComboSku($comboNum, $storeName, $customerId);
        } else { //客户编号+序列号（在现有序列号的基础上新增）
            $seqValue = db('tb_sys_sequence')->where('seq_key', 'b2b_add_product_number')->value('seq_value');
            db('tb_sys_sequence')->where('seq_key', 'b2b_add_product_number')
                ->update(['seq_value' => $seqValue + 1, 'update_time' => Carbon::now()]);
            return $this->getOuterSellerSku($seqValue, $storeName, $customerId);
        }

    }

    /**
     * 处理批量修改价格
     * @param array $rowPrices
     * @param int $customerId
     * @param int $skipCheckPrice 是否需要检测运费占比超过40%
     * @return int[]
     */
    public function modifyPrices(array $rowPrices, int $customerId, int $skipCheckPrice = 0)
    {
        // 美国的O账号检测运费
        if (!customer()->isUSA() || customer()->isInnerAccount()) {
            $skipCheckPrice = 0;
        }

        // 金额验证
        $pricePreg = customer()->isJapan() ? '/^(\d{1,7})$/' : '/^(\d{1,7})(\.\d{0,2})?$/';
        //2019-01-31 23:00:00
        $datePreg = '/^[1-9]\d{3}(-|\/)(0?[1-9]|1[0-2])(-|\/)(0?[1-9]|[1-2][0-9]|3[0-1])\s+(20|21|22|23|([0-1]?\d))(:[0-5]\d:[0-5]\d)?$/';
        // 日期24小时
        $validateTime = Carbon::now()->addDay(1)->format('Y-m-d H:i:s');
        $minPrice = customer()->isJapan() ? 0 : 0.00;
        $maxPrice = customer()->isJapan() ? 9999999 : 9999999.99;

        $productRepository = app(ProductRepository::class);
        $productAuditService = app(ProductAuditService::class);
        /** @var ModelCommonProduct $commonProductModel */
        $commonProductModel = load()->model('common/product');
        $errorCount = 0;
        $error = [];
        $skipCheckPriceSkus = [];
        // 验证
        foreach ($rowPrices as $key => &$rowPrice) {
            $effectTime = '';
            $line = $key + 1;
            if (empty($rowPrice['MPN'])) {
                $errorCount++;
                $error[] = __('第:line行，MPN不能为空！', ['line' => $line], 'repositories/product');
                continue;
            }

            if ($rowPrice['Modify Price'] == '') {
                $errorCount++;
                $error[] = __('第:line行，更新价格不能为空！', ['line' => $line], 'repositories/product');
                continue;
            }

            if (!is_numeric($rowPrice['Modify Price']) || !preg_match($pricePreg, floatval($rowPrice['Modify Price']))) {
                $errorCount++;
                $error[] = __('第:line行，更新价格格式不正确！', ['line' => $line], 'repositories/product');
                continue;
            }

            if ($rowPrice['Modify Price'] < $minPrice || $rowPrice['Modify Price'] > $maxPrice) {
                $errorCount++;
                $error[] = __('第:line行，更新价格只能在:min到:max之间！', ['line' => $line, 'min' => $minPrice, 'max' => $maxPrice], 'repositories/product');
                continue;
            }

            /** @var Product $product */
            $product = $productRepository->getProductInfoByCustomerIdAndMpn($customerId, strtoupper($rowPrice['MPN']));
            if (empty($product) || $product->product_type != ProductType::NORMAL) {
                $errorCount++;
                $error[] = __('第:line行，MPN与相应产品不匹配！', ['line' => $line], 'repositories/product');
                continue;
            }

            // 待上架的产品随意修改价格，不需根据时间
            if ($product->status != ProductStatus::WAIT_SALE && !empty($rowPrice['Date of Effect'])) {
                if (!preg_match($datePreg, $rowPrice['Date of Effect'])) {
                    $errorCount++;
                    $error[] = __('第:line行，生效时间格式不正确！', ['line' => $line], 'repositories/product');
                    continue;
                }

                if (substr_count($rowPrice['Date of Effect'], ':') == 0) {
                    $rowPrice['Date of Effect'] = $rowPrice['Date of Effect'] . ':00:00';
                }

                // 存在分秒的需要加1小时
                $parseEffectDate = Carbon::parse($rowPrice['Date of Effect']);
                $minute = $parseEffectDate->minute;
                $second = $parseEffectDate->second;
                if ($minute != 0 || $second != 0) {
                    $parseEffectDate = $parseEffectDate->addHour();
                }
                $rowPrice['Date of Effect'] = $parseEffectDate->format('Y-m-d H');

                $effectTime = date('Y-m-d H:00:00', strtotime(changeInputByZone(analyze_time_string($rowPrice['Date of Effect']), session('country', 'USA'))));
                // 降价需判断不能早于当前时间
                if (Carbon::now()->format('Y-m-d H:i:s') > $effectTime && $product->price > $rowPrice['Modify Price']) {
                    $errorCount++;
                    $error[] = __('第:line行，生效时间不能早于当前时间！', ['line' => $line], 'repositories/product');
                    continue;
                }

                // 涨价的才需判断24小时
                if ($validateTime > $effectTime && $product->price < $rowPrice['Modify Price']) {
                    $errorCount++;
                    $error[] = __('第:line行，生效时间必须晚于当前时间24小时以上！', ['line' => $line], 'repositories/product');
                    continue;
                }
            }

            $rowPrice['effect_time'] = $effectTime ?: '';
            $rowPrice['product'] = $product;

            if ($skipCheckPrice) {
                $alarmPrice = $commonProductModel->getAlarmPrice($product->product_id, true, $product->toArray());
                if (bccomp($alarmPrice, floatval($rowPrice['Modify Price']), 4) === 1) {
                    $skipCheckPriceSkus[] = $rowPrice['MPN'];
                }
            }
        }

        unset($rowPrice);

        if ($errorCount > 0) {
            return [$errorCount, $error, []];
        }

        if (!empty($skipCheckPriceSkus)) {
            return [0, [], $skipCheckPriceSkus];
        }

        // 处理
        foreach ($rowPrices as $rowPrice) {
            /** @var Product $product */
            $product = $rowPrice['product'];
            try {
                $productAuditService->modifyProductPrice($product->product_id, $customerId, customer()->getCountryId(), floatval($rowPrice['Modify Price']), $rowPrice['effect_time']);
            } catch (Throwable $e) {
                Logger::modifyPrices('批量修改价格报错：' . $e->getMessage(), 'error');
                $error[] = $e->getMessage();
            }
        }

        return [0, $error, []];
    }

    /**
     * 获取外部seller combo sku
     *
     * @param int $comboNum
     * @param string $storeName
     * @param int $customerId
     *
     * @return string
     */
    private function getOuterSellerComboSku($comboNum, $storeName, $customerId)
    {
        $sku = $storeName . 'S' . str_pad($comboNum + 1, 5, "0", STR_PAD_LEFT);
        $count = db('oc_product as op')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'op.product_id', '=', 'ctp.product_id')
            ->where('ctp.customer_id', $customerId)
            ->where(function (Builder $query) use ($sku) {
                $query->where('op.sku', $sku);
            })
            ->count();
        if ($count > 0) {
            return $this->getOuterSellerComboSku($comboNum + 1, $storeName, $customerId);
        }
        return $sku;
    }

    /**
     * 获取外部seller的sku
     *
     * @param int $num
     * @param string $storeName
     * @param int $customerId
     *
     * @return string
     */
    private function getOuterSellerSku($num, $storeName, $customerId)
    {
        $sku = $storeName . $num;
        $count = db('oc_product as op')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'op.product_id', '=', 'ctp.product_id')
            ->where('ctp.customer_id', $customerId)
            ->where(function (Builder $query) use ($sku) {
                $query->where('op.sku', $sku);
            })
            ->count();
        if ($count > 0) {
            db('tb_sys_sequence')->where('seq_key', 'b2b_add_product_number')
                ->update(['seq_value' => $num + 1, 'update_time' => Carbon::now()]);
            return $this->getOuterSellerSku($num + 1, $storeName, $customerId);
        }
        return $sku;
    }

    /**
     * 厘米 英寸转换
     *
     * @param float $size
     * @param int $from
     * @param int $to
     * @return string|float
     */
    public function calculateInchesAndCm($size, $from, $to)
    {
        $translateRate = ProductHelper::getTranslateStandard();
        //英寸转厘米
        if ($from == 1 && $to == 2) {
            return round($size * $translateRate['inch_to_cm'], 2);
        }
        //厘米转英寸
        return round($size * $translateRate['cm_to_inch'], 2);
    }

    /**
     * 英镑 kg转换
     *
     * @param float $weight
     * @param int $from
     * @param int $to
     * @return string|float
     */
    public function calculatePoundAndKg($weight, $from, $to)
    {
        $translateRate = ProductHelper::getTranslateStandard();
        //镑->千克
        if ($from == 1 && $to == 2) {
            return round($weight * $translateRate['pound_to_kg'], 2);
        }
        //千克->英镑
        return round($weight * $translateRate['kg_to_pound'], 2);
    }

    /**
     * 产品详情页显示的 Product Type 显示文字
     * @param array $product oc_product表的一条记录
     * @return string
     */
    public function getProductTypeNameForBuyer($product = [])
    {
        if (!$product || !is_array($product)) {
            return '';
        }
        if ($product['combo_flag'] == 1) {
            return 'Combo Item';
        }
        if ($product['part_flag'] == 1) {
            return 'Replacement Part';
        }
        return 'General Item';
    }


    /**
     * 产品详情页显示的 Package Size 显示文字
     * 后期可以替换为:
     * @see SizeInfoTrait::getSizeInfo()
     * @param array $product oc_product表的一条记录
     * @param int $countryId
     * @param array $subQuery 给定的ComboList子产品 [['product_id'=>0,'name'=>'','sku'=>'','mpn'=>'','qty'=>0,'两套尺寸'=>0],...]
     * @return string
     */
    public function getPackageSizeForBuyer($product = [], $countryId, $subQuery = [])
    {
        $results = [];
        $precision = 2;

        if (!$product || !is_array($product)) {
            return $results;
        }
        if ($countryId == AMERICAN_COUNTRY_ID) {
            $unitLength = 'inches';
            $unitWeight = 'lbs';
        } else {
            $unitLength = 'cm';
            $unitWeight = 'kg';
        }
        if ($product['combo_flag']) {
            if (!$subQuery) {
                $subQuery = $this->getSubQueryByProductId($product['product_id']);
            }
            foreach ($subQuery as $key => $value) {
                $qty = $value['qty'];
                if ($countryId == AMERICAN_COUNTRY_ID) {
                    $length = number_format($value['length'], $precision);
                    $width = number_format($value['width'], $precision);
                    $height = number_format($value['height'], $precision);
                    $weight = number_format($value['weight'], $precision);
                } else {
                    $length = number_format($value['length_cm'], $precision);
                    $width = number_format($value['width_cm'], $precision);
                    $height = number_format($value['height_cm'], $precision);
                    $weight = number_format($value['weight_kg'], $precision);
                }
                $result = [
                    'sku' => $value['sku'],
                    'msg' => "Package Quantity:&nbsp;&nbsp;{$qty}&nbsp;&nbsp;&nbsp;&nbsp;{$length}&nbsp;*&nbsp;{$width}&nbsp;*&nbsp;{$height}&nbsp;{$unitLength}&nbsp;&nbsp;{$weight}{$unitWeight}",
                ];
                $results[] = $result;
            }
            return $results;
        } else {
            if ($countryId == AMERICAN_COUNTRY_ID) {
                $length = number_format($product['length'], $precision);
                $width = number_format($product['width'], $precision);
                $height = number_format($product['height'], $precision);
                $weight = number_format($product['weight'], $precision);
            } else {
                $length = number_format($product['length_cm'], $precision);
                $width = number_format($product['width_cm'], $precision);
                $height = number_format($product['height_cm'], $precision);
                $weight = number_format($product['weight_kg'], $precision);
            }
            $result = [
                'sku' => $product['sku'],
                'msg' => "{$length}&nbsp;*&nbsp;{$width}&nbsp;*&nbsp;{$height}&nbsp;{$unitLength}&nbsp;&nbsp;{$weight}{$unitWeight}",
                'length' => $length,
                'width' => $width,
                'height' => $height,
                'weight' => $weight,
                'unitLength' => $unitLength,
                'unitWeight' => $unitWeight,
            ];
            $results[] = $result;
        }
        return $results;
    }


    /**
     * 产品详情页 Combo品获取子产品
     * @param int $productId
     * @return array
     */
    public function getSubQueryByProductId($productId)
    {
        $subResult = ProductSetInfo::query()->alias('ps')
            ->leftJoinRelations(['setProduct as p'])
            ->where('ps.product_id', $productId)
            ->select([
                'p.sku',
                'p.mpn',
                'p.weight',
                'p.height',
                'p.length',
                'p.width',
                'p.weight_kg',
                'p.length_cm',
                'p.width_cm',
                'p.height_cm',
                'ps.qty',
                'ps.set_product_id',
                'ps.product_id',
            ])
            ->get()
            ->toArray();
        return $subResult;
    }

    /**
     * 获取某些产品关联的所有产品
     * @param array $productIds
     * @param array $excludeProductIds
     * @return array
     */
    public function getAssociateProductsByProductIds(array $productIds, array $excludeProductIds = []): array
    {
        $associateProductIds = ProductAssociate::query()
            ->whereIn('product_id', $productIds)
            ->pluck('associate_product_id')
            ->merge($productIds) // 这些产品都需要关联自己，防止数据错误，这边加上自己的关联
            ->diff($excludeProductIds)
            ->unique()
            ->toArray();

        $productInfoFactory = new ProductInfoFactory();
        $products = $productInfoFactory->withIds($associateProductIds)->getBaseInfoRepository()->withUnavailable(false)->getInfos();

        $data = [];
        rsort($associateProductIds);
        foreach ($associateProductIds as $associateProductId) {
            if (isset($products[$associateProductId])) {
                $product = $products[$associateProductId];
                $data[] = [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'mpn' => $product->mpn,
                    'image' => $product->getImage(50, 50),
                    'name' => $product->getName(),
                    'color' => $product->getColorName(),
                    'material' => $product->getMaterialName(),
                    'filler' => $product->getFilterName(),
                    'custom_field' => $product->getCustomerFiled(ProductCustomFieldType::INFORMATION)[ProductCustomFieldType::INFORMATION],
                ];
            }
        }

        return $data;
    }

    /**
     * 获取商品信息 seller后台接口获取数据专用
     *
     * @param int $productId
     * @return array|bool
     */
    public function getSellerProductInfo($productId)
    {
        $productInfo = Product::with(['description', 'ext'])->find($productId);
        if (!$productInfo) {
            return false;
        }

        //没有新颜色,默认给空字符串
        $colorOptionValueId = $materialOptionValueId = '';
        $color = app(OptionValueRepository::class)->getOptionValueInfo($productId, Option::COLOR_OPTION_ID);
        if ($color) {
            $colorOptionValueId = $color->option_value_id;
        }
        $material = app(OptionValueRepository::class)->getOptionValueInfo($productId, Option::MATERIAL_OPTION_ID);
        if ($material) {
            $materialOptionValueId = $material->option_value_id;
        }
        //颜色材质等信息
        /** @var ModelCatalogProduct $mcp */
        $mcp = load()->model('catalog/product');
        $customerId = customer()->getId();

        $color = $mcp->getProductNewColorAndMaterial($productId, $customerId, Option::MIX_OPTION_ID);
        $Newcolor = $mcp->getProductNewColorAndMaterial($productId, $customerId, Option::COLOR_OPTION_ID);
        $material = $mcp->getProductNewColorAndMaterial($productId, $customerId, Option::MATERIAL_OPTION_ID);

        $colorName = $Newcolor['color_material'] ? $Newcolor['color_material'] : ($color['color_material'] ?? '');
        $materialName = $material['color_material'] ?? '';

        //分组信息
        /** @var ModelAccountCustomerpartnerProductGroup $macao */
        $macao = load()->model('Account/Customerpartner/ProductGroup');
        $groupInfos = $macao->getGroupIDsByProductIDs($customerId, [$productId]);

        //类目信息
        $productCategory = app(ProductToCategoryRepository::class)->getCategoryInfoByProductId($productId);
        /** @var ModelToolImage $modelToolImage */
        $modelToolImage = load()->model('tool/image');
        //关联商品 子商品
        /** @var ModelAccountCustomerpartner $mace */
        $mace = load()->model('account/customerpartner');
        $associateProducts = $this->getSellerProductAssociateByProductId($productId);

        $customFieldData = app(ProductRepository::class)->getCustomFieldByIds(array_column($associateProducts,'product_id'));
        $fillerData = app(ProductRepository::class)->getProductExtByIds(array_column($associateProducts,'product_id'));

        array_walk($associateProducts, function (&$item) use ($mcp, $fillerData, $customFieldData, $customerId, $modelToolImage) {
            $item['image'] = $modelToolImage->resize($item['image'], 50, 50);
            $newColor = $mcp->getProductNewColorAndMaterial($item['product_id'], $customerId, Option::COLOR_OPTION_ID);
            $material = $mcp->getProductNewColorAndMaterial($item['product_id'], $customerId, Option::MATERIAL_OPTION_ID);
            $item['color'] = $newColor['color_material'];
            $item['material'] = $material['color_material'];
            if (!$newColor['color_material']) {
                $newColor = $mcp->getProductNewColorAndMaterial($item['product_id'], $customerId, Option::MIX_OPTION_ID);
                $item['color'] = $newColor['color_material'];
            }
            //获取配置的客户字段信息
            $item['custom_field'] = $customFieldData[$item['product_id']] ?? [];
            $item['filler'] =$fillerData[$item['product_id']]['filler_option_value']['name'] ?? null;

        });
        // 子产品
        $comboProducts = $mace->getComboProductByOrm($productId);
        array_walk($comboProducts, function (&$item) use ($modelToolImage) {
            $item['length'] = customer()->isUSA() ? $item['length'] : $item['length_cm'];
            $item['width'] = customer()->isUSA() ? $item['width'] : $item['width_cm'];
            $item['height'] = customer()->isUSA() ? $item['height'] : $item['height_cm'];
            $item['weight'] = customer()->isUSA() ? $item['weight'] : $item['weight_kg'];
            $item['image'] = $modelToolImage->resize($item['image'], 50, 50);
            $item['is_ltl'] = ProductToTag::query()->isLTL($item['product_id']) ? 1 : 0;
        });
        //ltl判断
        $isLtl = db('oc_product_to_tag')->where('product_id', $productId)->where('tag_id', (int)configDB('tag_id_oversize'))->exists();

        $certificationDocuments = $productInfo->certificationDocuments;
        $formatCertificationDocuments = [];
        foreach ($certificationDocuments as $certificationDocument) {
            $formatCertificationDocument['orig_url'] = $certificationDocument->url;
            $formatCertificationDocument['thumb'] = StorageCloud::image()->getUrl($certificationDocument->url, ['check-exist' => false]);
            $formatCertificationDocument['url'] = StorageCloud::image()->getUrl($certificationDocument->url, ['check-exist' => false]);
            $formatCertificationDocument['type_name'] = $certificationDocument->type_name;
            $formatCertificationDocument['name'] = $certificationDocument->name;
            $formatCertificationDocument['type_id'] = $certificationDocument->type_id;
            $formatCertificationDocuments[] = $formatCertificationDocument;
        }

        $result = [
            'price_display' => $productInfo->price_display,
            'quantity_display' => $productInfo->quantity_display,
            'product_id' => $productInfo->product_id,
            'name' => $productInfo->description->name,
            'description' => $productInfo->description->description,
            'price' => $productInfo->price,
            'sku' => $productInfo->sku,
            'mpn' => $productInfo->mpn,
            'quantity' => $productInfo->quantity,
            'image' => $productInfo->image,
            'image_show_url' => $modelToolImage->resize($productInfo->image, 100, 100),
            'weight' => customer()->isUSA() ? $productInfo->weight : $productInfo->weight_kg,
            'length' => customer()->isUSA() ? $productInfo->length : $productInfo->length_cm,
            'width' => customer()->isUSA() ? $productInfo->width : $productInfo->width_cm,
            'height' => customer()->isUSA() ? $productInfo->height : $productInfo->height_cm,
            'status' => $productInfo->status,
            'color' => $colorOptionValueId,
            'color_name' => $colorName,
            'material' => $materialOptionValueId,
            'material_name' => $materialName,
            'combo_flag' => $productInfo->combo_flag,
            'buyer_flag' => $productInfo->buyer_flag,
            'part_flag' => $productInfo->part_flag,
            'product_size' => $productInfo->product_size,
            'need_install' => $productInfo->need_install,
            'product_group_ids' => empty($groupInfos) ? '' : implode(',', $groupInfos),
            'product_category' => $productCategory,
            'product_associated' => $associateProducts,
            'combo' => $comboProducts,
            'product_type' => app(ProductAuditRepository::class)->getProductTypeWithProductInfo($productInfo->combo_flag, $productInfo->part_flag), //这里的prudcut_type并不是商品属性product_type
            'is_ltl' => $isLtl ? 1 : 0,
            'non_sellable_on' => $productInfo->non_sellable_on,
            'upc' => $productInfo->upc,
            'is_customize' => $productInfo->ext->is_customize ?? 0,
            'origin_place_code' => $productInfo->ext->origin_place_code ?? '',
            'filler' => $productInfo->ext->filler,
            'information_custom_field' => $productInfo->informationCustomFields()->select(['name', 'value', 'sort'])->get(),
            'assemble_length' => $productInfo->ext->assemble_length ?? '',
            'assemble_width' => $productInfo->ext->assemble_width ?? '',
            'assemble_height' => $productInfo->ext->assemble_height ?? '',
            'assemble_weight' => $productInfo->ext->assemble_weight ?? '',
            'dimensions_custom_field' => $productInfo->dimensionCustomFields()->select(['name', 'value', 'sort'])->get(),
            'certification_documents' => $formatCertificationDocuments,
        ];

        return $result;
    }

    /**
     * 用在Seller编辑产品页，展示已存在的同款产品列表
     * @param int $productId
     * @return array
     */
    public function getSellerProductAssociateByProductId($productId)
    {
        $results = ProductAssociate::query()->alias('pa')
            ->leftJoin('oc_product AS p', 'p.product_id', '=', 'pa.associate_product_id')
            ->leftJoin('oc_product_description AS pd', 'pd.product_id', '=', 'pa.associate_product_id')
            ->select(['p.sku', 'p.mpn', 'p.image', 'p.product_size', 'pd.name', 'p.product_id'])
            ->where('pa.product_id', $productId)
            ->where('pa.associate_product_id', '!=', $productId)
            ->where('p.is_deleted', YesNoEnum::NO)
            ->get()
            ->toArray();
        return $results;
    }

    /**
     * Seller的Product List页面，Valid产品的历史价格
     * @param int $productId
     * @return Collection
     */
    public function getPriceHistoryForSeller($productId)
    {
        $results = db('oc_seller_price_history  AS sph')
            ->where([
                ['sph.product_id', '=', $productId],
                ['sph.status', '=', 1]
            ])
            ->get();
        return $results;
    }

    /**
     * 获取父产品的SKU
     * @param array $productIds
     * @return array ['子ID' =>['父ID' =>['product_id' => 319152,'sku' => SUYANG004,'set_product_id' => 318937',set_sku' => SUYANG001],...],...]
     */
    public function getParentSkuByProductIds($productIds = [])
    {
        if (!is_array($productIds)) {
            $productIds = (array)$productIds;
        }

        $resultsDb = ProductSetInfo::query()->alias('psi')
            ->leftJoinRelations('product as p')
            ->leftJoinRelations('setProduct as subp')
            ->select(['p.product_id', 'p.sku', 'psi.set_product_id', 'subp.sku AS set_sku'])
            ->whereIn('psi.set_product_id', $productIds)
            ->where('p.is_deleted', '=', 0)
            ->get()
            ->toArray();
        $results = [];//[ '子ID'=>[ '父ID'=>[], ], ]
        foreach ($resultsDb as $key => $value) {
            $results[$value['set_product_id']][$value['product_id']] = $value;
        }
        $returnData = [];
        foreach ($productIds as $set_product_id) {
            if (isset($results[$set_product_id])) {
                $returnData[$set_product_id] = $results[$set_product_id];
            }
        }
        return $returnData;
    }

    /**
     * 判断当前产品的子产品属于的combo产品
     * Seller产品列表页[删除产品]专用
     * @param array $productIds
     * @return array ['子SKU'=>['别的Combo品SKU'=>'别的Combo品SKU']]
     */
    public function getAllParentByParentProductIds($productIds = [])
    {
        if (!is_array($productIds)) {
            $productIds = (array)$productIds;
        }

        $allProductIds = [];
        $allParents = [];
        foreach ($productIds as $productId) {
            $allProductIds[$productId] = $productId;
            $subProducts = ProductSetInfo::query()->alias('psi')
                ->leftJoinRelations('product as p')
                ->leftJoinRelations('setProduct as set_p')
                ->select([
                    'psi.product_id',
                    'p.sku',
                    'psi.set_product_id',
                    'set_p.sku as set_sku'
                ])
                ->where('psi.product_id', '=', $productId)
                ->get()
                ->toArray();

            foreach ($subProducts as $subProduct) {
                $mainProductId = $subProduct['product_id'];
                $mainSku = $subProduct['sku'];
                $setProductId = $subProduct['set_product_id'];
                $setSku = $subProduct['set_sku'];
                $allProductIds[$setProductId] = $setProductId;
                $otherParent = ProductSetInfo::query()->alias('psi')
                    ->leftJoinRelations('product as op')
                    ->select([
                        'psi.product_id AS other_product_id',
                        'psi.set_product_id AS other_set_product_id',
                        'op.sku AS other_sku'])
                    ->where('psi.set_product_id', '=', $setProductId)
                    ->where('is_deleted', '=', 0)
                    ->get()
                    ->toArray();
                foreach ($otherParent as $op) {
                    $tmp = [];
                    $tmp['set_product_id'] = $setProductId;//子产品
                    $tmp['set_sku'] = $setSku;
                    $tmp['product_id'] = $mainProductId;//父产品
                    $tmp['sku'] = $mainSku;
                    $tmp['other_product_id'] = $op['other_product_id'];//叔叔
                    $tmp['other_sku'] = $op['other_sku'];
                    $allParents[$setProductId][$op['other_product_id']] = $tmp;
                    $allProductIds[$tmp['other_product_id']] = $tmp['other_product_id'];
                }
            }
        }

        $canIdSkuArr = [];//product_id=>sku
        $canNotSets = [];
        $canNotSkuArr = []; // product_id=>sku,sku,sku
        foreach ($allParents as $subProductId => $comboProducts) {
            //若$comboProducts个数大于1，则此子产品属于多个combo品
            //若$comboProducts个数等于1，则此子产品属于一个combo品
            if (count($comboProducts) > 1) {
                foreach ($comboProducts as $mainProductId => $comboProduct) {
                    $otherProductId = $comboProduct['other_product_id'];
                    if ($productId != $otherProductId) {
                        $canNotSets[$subProductId][$otherProductId] = $comboProduct;
                    }
                }
            } else {
                $comboProduct = reset($comboProducts);
                $canIdSkuArr[$subProductId] = $comboProduct['set_sku'];
            }
        }

        foreach ($canNotSets as $subProductId => $otherComboProducts) {
            $otherComboProduct = reset($otherComboProducts);
            $setSku = $otherComboProduct['set_sku'];
            $canNotSkuArr[$setSku] = implode(',', array_column($otherComboProducts, 'other_sku'));
        }

        $results = [
            'allProductIds' => $allProductIds,
            'canIdSkuArr' => $canIdSkuArr,
            'canNotSkuArr' => $canNotSkuArr,
        ];
        return $results;
    }

    /**
     * 某个产品的出库时效
     * @param array $warehouseList [['stock_qty'=>'在库数量', 'warehouse_id'=>'仓库主键'],...] 某个产品有库存分布
     * @param bool $isLTL
     * @param bool|null $isCollectionFromDomicile true 是上门取货的Buyer, false 是一件代发的Buyer
     * @return array 只有五个key有用 [
     * 'cloud_ship_min'=>0,
     * 'cloud_ship_max'=>0,
     * 'ship_remark'=>'',
     * 'ship_min'=>0,
     * 'ship_max'=>0,
     * ]
     */
    public function getHandlingTime($warehouseList = [], $isLTL = false, $isCollectionFromDomicile = false)
    {
        if (!$warehouseList) {
            return [];
        }
        $warehouseIdArray = [];
        foreach ($warehouseList as $key => $value) {
            //1.如果是combo品的子产品，那么就会有key"isComboShip"，若isComboShip==1，则就取该仓库的出库时效
            //2.如果是非combo品，那么 stock_qty>0，就取该仓库的出库时效
            if (array_key_exists('isComboShip', $value)) {
                if($value['isComboShip']){
                    $warehouseIdArray[] = $value['warehouse_id'];
                }
            } else {
                if ($value['stock_qty'] > 0) {
                    $warehouseIdArray[] = $value['warehouse_id'];
                }
            }
        }
        if (!$warehouseIdArray) {
            return [];
        }

        $extsList = db('tb_warehouses_exts')
            ->whereIn('warehouse_id', $warehouseIdArray)
            ->get();
        $extsList = obj2array($extsList);
        $count = count($extsList);
        if ($count == 0) {
            return [];
        } elseif ($count == 1) {
            $ext = reset($extsList);
            $return = [];
            $return['ship_remark'] = trim($ext['ship_remark']);
            $return['cloud_ship_min'] = $ext['cloud_ship_min'];
            $return['cloud_ship_max'] = $ext['cloud_ship_max'];
            if ($isCollectionFromDomicile) {
                $return['ship_min'] = $isLTL ? $ext['pick_up_ltl_ship_min'] : $ext['pick_up_normal_ship_min'];
                $return['ship_max'] = $isLTL ? $ext['pick_up_ltl_ship_max'] : $ext['pick_up_normal_ship_max'];
            } else {
                $return['ship_min'] = $isLTL ? $ext['ltl_ship_min'] : $ext['normal_ship_min'];
                $return['ship_max'] = $isLTL ? $ext['ltl_ship_max'] : $ext['normal_ship_max'];
            }
            return $return;
        } else {
            $normalShipMin = 0;//普通产品出库时效最少用时，单位：天
            $normalShipMax = 0;//普通产品出库时效最多用时，单位：天
            $ltlShipMin = 0;//LTL产品出库时效最少用时，单位：天
            $ltlShipMax = 0;//LTL产品出库时效最多用时，单位：天
            $cloudShipMin = 0;//云送仓出库时效最少用时，单位：天
            $cloudShipMax = 0;//云送仓出库时效最多用时，单位：天
            $pickUpNormalShipMin = 0;//上门取货产品出库时效最少用时，单位：天
            $pickUpNormalShipMax = 0;//上门取货产品出库时效最多用时，单位：天
            $pickUpLTLShipMin = 0;//上门取货LTL产品出库时效最少用时，单位：天
            $pickUpLTLShipMax = 0;//上门取货LTL产品出库时效最多用时，单位：天
            $countIndex = 0;
            foreach ($extsList as $key => $value) {
                if ($value['send_flag'] == 1) {//仓库不支持发货作业
                    continue;
                }
                $value['normal_ship_min'] = intval($value['normal_ship_min']);
                $value['normal_ship_max'] = intval($value['normal_ship_max']);
                $value['ltl_ship_min'] = intval($value['ltl_ship_min']);
                $value['ltl_ship_max'] = intval($value['ltl_ship_max']);
                $value['cloud_ship_min'] = intval($value['cloud_ship_min']);
                $value['cloud_ship_max'] = intval($value['cloud_ship_max']);
                $value['pick_up_normal_ship_min'] = intval($value['pick_up_normal_ship_min']);
                $value['pick_up_normal_ship_max'] = intval($value['pick_up_normal_ship_max']);
                $value['pick_up_ltl_ship_min'] = intval($value['pick_up_ltl_ship_min']);
                $value['pick_up_ltl_ship_max'] = intval($value['pick_up_ltl_ship_max']);
                $countIndex++;
                if ($normalShipMin == 0) {
                    $normalShipMin = $value['normal_ship_min'];
                } else {
                    $normalShipMin = ($value['normal_ship_min'] > 0 && $value['normal_ship_min'] < $normalShipMin) ? ($value['normal_ship_min']) : ($normalShipMin);
                }

                if ($normalShipMax == 0) {
                    $normalShipMax = $value['normal_ship_max'];
                } else {
                    $normalShipMax = ($value['normal_ship_max'] > $normalShipMax) ? ($value['normal_ship_max']) : ($normalShipMax);
                }

                if ($ltlShipMin == 0) {
                    $ltlShipMin = $value['ltl_ship_min'];
                } else {
                    $ltlShipMin = ($value['ltl_ship_min'] > 0 && $value['ltl_ship_min'] < $ltlShipMin) ? ($value['ltl_ship_min']) : ($ltlShipMin);
                }

                if ($ltlShipMax == 0) {
                    $ltlShipMax = $value['ltl_ship_max'];
                } else {
                    $ltlShipMax = ($value['ltl_ship_max'] > $ltlShipMax) ? ($value['ltl_ship_max']) : ($ltlShipMax);
                }

                if ($cloudShipMin == 0) {
                    $cloudShipMin = $value['cloud_ship_min'];
                } else {
                    $cloudShipMin = ($value['cloud_ship_min'] > 0 && $value['cloud_ship_min'] < $cloudShipMin) ? ($value['cloud_ship_min']) : ($cloudShipMin);
                }

                if ($cloudShipMax == 0) {
                    $cloudShipMax = $value['cloud_ship_max'];
                } else {
                    $cloudShipMax = ($value['cloud_ship_max'] > $cloudShipMax) ? ($value['cloud_ship_max']) : ($cloudShipMax);
                }
                if ($pickUpNormalShipMin == 0) {
                    $pickUpNormalShipMin = $value['pick_up_normal_ship_min'];
                } else {
                    $pickUpNormalShipMin = ($value['pick_up_normal_ship_min'] > 0 && $value['pick_up_normal_ship_min'] < $pickUpNormalShipMin) ? ($value['pick_up_normal_ship_min']) : ($pickUpNormalShipMin);
                }
                if ($pickUpNormalShipMax == 0) {
                    $pickUpNormalShipMax = $value['pick_up_normal_ship_max'];
                } else {
                    $pickUpNormalShipMax = ($value['pick_up_normal_ship_max'] > $pickUpNormalShipMax) ? ($value['pick_up_normal_ship_max']) : ($pickUpNormalShipMax);
                }
                if ($pickUpLTLShipMin == 0) {
                    $pickUpLTLShipMin = $value['pick_up_ltl_ship_min'];
                } else {
                    $pickUpLTLShipMin = ($value['pick_up_ltl_ship_min'] > 0 && $value['pick_up_ltl_ship_min'] < $pickUpLTLShipMin) ? ($value['pick_up_ltl_ship_min']) : ($pickUpLTLShipMin);
                }
                if ($pickUpLTLShipMax == 0) {
                    $pickUpLTLShipMax = $value['pick_up_ltl_ship_max'];
                } else {
                    $pickUpLTLShipMax = ($value['pick_up_ltl_ship_max'] > $pickUpLTLShipMax) ? ($value['pick_up_ltl_ship_max']) : ($pickUpLTLShipMax);
                }
            }
            if ($countIndex > 0) {
                $return = [];
                $return['ship_remark'] = '';
                $return['cloud_ship_min'] = $cloudShipMin;
                $return['cloud_ship_max'] = $cloudShipMax;
                if ($isCollectionFromDomicile) {
                    $return['ship_min'] = $isLTL ? $pickUpLTLShipMin : $pickUpNormalShipMin;
                    $return['ship_max'] = $isLTL ? $pickUpLTLShipMax : $pickUpNormalShipMax;
                } else {
                    $return['ship_min'] = $isLTL ? $ltlShipMin : $normalShipMin;
                    $return['ship_max'] = $isLTL ? $ltlShipMax : $normalShipMax;
                }
                return $return;
            }
        }
        return [];
    }


    /**
     * 获取产品预计送达时间范
     *
     * @param int $countryId 国家ID
     * @param int|boolean $is_oversize 是否超大件（真：是 假：否）
     * @return mixed
     */
    public function getEstimatedDeliveryTime($countryId, $isOversize = 0)
    {
        return CountryExtsModel::where('country_id', $countryId)
            ->where('show_flag', YesNoEnum::YES)
            ->where('type', $isOversize ? CountryExts::LTL : CountryExts::COMMON)
            ->select('ship_day_min', 'ship_day_max', 'type')
            ->first();
    }

    public function getSellerProducts(int $customerId, string $codeOrMpnFilter = '', int $limit = 5)
    {
        return Product::query()->alias('p')
            ->select(['p.product_id', 'p.mpn', 'p.sku', 'pd.name', 'p.image', 'p.length', 'p.width', 'p.height', 'p.weight'])
            ->join('oc_customerpartner_to_product as c2p', 'c2p.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_description as pd', 'pd.product_id', '=', 'p.product_id')
            ->where([
                'p.is_deleted' => YesNoEnum::NO,
                'p.combo_flag' => 0,
                'c2p.customer_id' => $customerId,
                'p.product_type' => ProductType::NORMAL,
            ])
            ->when(!empty($codeOrMpnFilter), function ($q) use ($codeOrMpnFilter) {
                $q->where(function ($q) use ($codeOrMpnFilter) {
                    $filter = htmlspecialchars(trim($codeOrMpnFilter));
                    $filter = str_replace('_', '\_', $filter);
                    $filter = str_replace('%', '\%', $filter);
                    $q->orWhere('p.mpn', 'like', "%{$filter}%")->orWhere('p.sku', 'like', "%{$filter}%");
                });
            }
            )
            ->orderBy('p.product_id', 'desc')
            ->when($limit > 0, function ($q) use ($limit) {
                $q->limit($limit);
            })
            ->get();
    }

    /**
     * 通过mpn 获取客户 普通&有效&非Combo 产品信息
     *
     * @param int $customerId
     * @param array $mpns
     * @return CustomerPartnerToProduct[]|Collection
     */
    public function getSellerNormalProductsBySkus(int $customerId, array $mpns)
    {
        return CustomerPartnerToProduct::query()->alias('c2p')
            ->leftJoinRelations(['product as p'])
            ->leftJoin('oc_product_description as pd', 'pd.product_id', '=', 'p.product_id')
            ->select(['p.product_id', 'p.mpn', 'p.sku', 'pd.name', 'p.image', 'p.length', 'p.width', 'p.height', 'p.weight', 'p.price'])
            ->where([
                'c2p.customer_id' => $customerId,
                'p.is_deleted' => YesNoEnum::NO,
                'p.combo_flag' => YesNoEnum::NO,
                'p.product_type' => ProductType::NORMAL,
            ])
            ->whereIn('p.mpn', $mpns)
            ->get();
    }

    /**
     * 获取商品在某种入库单状态的总数量
     * @param array $productIds
     * @param int $status
     * @return array 返回值中：product_id:商品id expectedQtySum:预计入库总数量 receivedQtySum:仓库收到总数量
     *
     */
    public function countProductQtyInReceiptsStatus(array $productIds,int $status)
    {
        return ReceiptsOrderDetail::query()->alias('rod')
            ->selectRaw('rod.product_id,sum(expected_qty) as expectedQtySum,sum(received_qty) as receivedQtySum')
            ->join('tb_sys_receipts_order as ro', 'rod.receive_order_id', '=', 'ro.receive_order_id')
            ->whereIn('rod.product_id', array_unique($productIds))
            ->where('ro.status', $status)
            ->groupBy('rod.product_id')
            ->get()
            ->toArray();
    }

    /**
     * 获取Sku
     * @param int $productId
     * @return string|mixed
     */
    public function getSkuByProductId($productId)
    {
        return Product::query()->where('product_id', $productId)->value('sku');
    }

    public function getProductInfoById($productId)
    {
        return Product::query()
            ->alias('p')
            ->select(['p.product_id', 'p.mpn', 'p.sku', 'p.image', 'p.length', 'p.width', 'p.height', 'p.weight'])
            ->where('product_id', $productId)
            ->first();
    }

    /**
     * 获取产品的基本信息和价格区间
     * @param array $productIds
     * @param int|string|null $customerId
     * @param array $config
     * @return array [[$productId => BaseInfo], ProductPriceRangeFactory, transactionPriceRange]
     */
    public function getProductBaseInfosAndPriceRanges(array $productIds, $customerId, $config = []): array
    {
        $config = array_merge([
            'withProductComplexTag' => false, // 填充产品基本信息的复杂交易 tag
            'withUnavailable' => false, // 包含不可用的产品
            'withTransactionPriceRange' => true, // 包含交易类型的价格
        ], $config);

        $productInfoFactory = new ProductInfoFactory();
        $productPriceFactory = new ProductPriceRangeFactory();
        $transactionPriceRange = [];         // [productId][交易方式] => [价格, 价格]

        $repository = $productInfoFactory->withIds($productIds)->getBaseInfoRepository()
            ->withCustomerId($customerId);
        if ($config['withUnavailable']) {
            $repository = $repository->withUnavailable();
        }
        $baseInfos = $repository->getInfos();
        $priceVisibleProductIds = []; // 价格可见的产品
        foreach ($baseInfos as $info) {
            if ($info->getPriceVisible()) {
                $priceVisibleProductIds[] = $info->id;
                $productPriceFactory->addPrice($info->id, [$info->price, $info->getDelicacyPrice()]);
                if ($config['withTransactionPriceRange']) {
                    $transactionPriceRange[$info->id][ProductTransactionType::NORMAL] = [$info->price];
                }
            }
        }

        $productComplexIds = [
            RebateInfoRepository::class => [],
            MarginInfoRepository::class => [],
            FuturesInfoRepository::class => [],
        ];
        if ($priceVisibleProductIds || $config['withProductComplexTag']) {
            // 产品价格可见，或者要复杂交易的标签时
            $complexTransactionClass = [
                RebateInfoRepository::class,
                MarginInfoRepository::class,
                FuturesInfoRepository::class,
                QuoteInfoRepository::class,
                ProductQuoteInfoRepository::class,
            ];
            if (!$priceVisibleProductIds) {
                // 当价格不可见时，仅需要查复杂交易的
                $complexTransactionClass = array_keys($productComplexIds);
            }

            foreach ($complexTransactionClass as $class) {
                $infos = $productInfoFactory
                    ->withIds($config['withProductComplexTag'] ? $productIds : $priceVisibleProductIds) // 需要复杂交易标签时需要查询所有产品，否则只需要查询价格可见的
                    ->buildComplexTransactionRepository($class)
                    ->withPriceRange($customerId) // 当价格不可见时，不查询 buyer 价格，因为复杂交易只需要查模版的价格区间
                    ->getInfos();

                // 添加价格区间
                if ($priceVisibleProductIds) {
                    foreach ($infos as $productId => $info) {
                        if (in_array($productId, $priceVisibleProductIds)) {
                            // 仅价格可见的加入价格区间
                            $productPriceFactory->addPrice($productId, $info->getPriceRange());
                            // 交易类型的价格区间
                            if ($config['withTransactionPriceRange']) {
                                if (is_a($class, "App\Repositories\Product\ProductInfo\ComplexTransaction\RebateInfoRepository", true)) {
                                    foreach ($info->templatePriceRange as $mxx) {
                                        if ($mxx > 0) {
                                            $transactionPriceRange[$productId][ProductTransactionType::REBATE][] = $mxx;
                                        }
                                    }
                                } elseif (is_a($class, "App\Repositories\Product\ProductInfo\ComplexTransaction\MarginInfoRepository", true)) {
                                    foreach ($info->templatePriceRange as $mxx) {
                                        if ($mxx > 0) {
                                            $transactionPriceRange[$productId][ProductTransactionType::MARGIN][] = $mxx;
                                        }
                                    }
                                } elseif (is_a($class, "App\Repositories\Product\ProductInfo\ComplexTransaction\FuturesInfoRepository", true)) {
                                    foreach ($info->templatePriceRange as $mxx) {
                                        if ($mxx > 0) {
                                            $transactionPriceRange[$productId][ProductTransactionType::FUTURE][] = $mxx;
                                        }
                                    }
                                } elseif (is_a($class, "App\Repositories\Product\ProductInfo\ComplexTransaction\QuoteInfoRepository", true)) {
                                    foreach ($info->templatePriceRange as $mxx) {
                                        if ($mxx > 0) {
                                            $transactionPriceRange[$productId][ProductTransactionType::SPOT][] = $mxx;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // 添加复杂交易标签
                if ($config['withProductComplexTag'] && in_array($class, array_keys($productComplexIds))) {
                    foreach ($infos as $productId => $info) {
                        [$min, $max] = $info->templatePriceRange; // 只按照模版的价格区间判断是否需要显示复杂交易标签
                        if ($min > 0 && $max > 0) {
                            $productComplexIds[$class][] = $productId;
                        }
                    }
                }
            }
        }

        if ($config['withProductComplexTag']) {
            foreach ($baseInfos as $info) {
                if (in_array($info->id, $productComplexIds[RebateInfoRepository::class])) {
                    $info->addComplexTag('Rebates');
                }
                if (in_array($info->id, $productComplexIds[MarginInfoRepository::class])) {
                    $info->addComplexTag('Margin');
                }
                if (in_array($info->id, $productComplexIds[FuturesInfoRepository::class])) {
                    $info->addComplexTag('Future');
                }
            }
        }

        return [$baseInfos, $productPriceFactory, $transactionPriceRange];
    }

    /**
     * 获取复杂交易的产品ID
     * @param int $sellerId
     * @param array $config
     * @return array
     */
    public function getComplexTransactionProductsBySellerId(int $sellerId, $config = [])
    {
        $config = array_merge([
            'rebate' => false,
            'margin' => false,
            'future' => false
        ], $config);

        $collection = collect([]);
        if ($config['rebate']) {
            $rebatesProducts = app(RebateRepository::class)->getRebateProductsBySellerId($sellerId);
            $collection = $collection->merge($rebatesProducts);
        }

        if ($config['margin']) {
            $marginProducts = app(MarginRepository::class)->getMarginProductsBySellerId($sellerId);
            $collection = $collection->merge($marginProducts);
        }

        if ($config['future']) {
            $futuresProducts = app(ContractRepository::class)->getFuturesProductsBySellerId($sellerId);
            $collection = $collection->merge($futuresProducts);
        }

        return $collection->unique()->all();
    }

    /**
     * seller有效的产品sku及标记
     * @param int $sellerId
     * @param string $mpnSku
     * @return Product[]|\Framework\Model\Eloquent\Builder[]
     */
    public function getSellerValidSkusIncludeTags(int $sellerId, string $mpnSku = '')
    {
        return Product::query()->alias('p')
            ->join('oc_customerpartner_to_product as ctp', 'p.product_id', '=', 'ctp.product_id')
            ->when(!empty($mpnSku), function ($q) use ($mpnSku) {
                $mpnSkus = explode('/', $mpnSku);
                $mpnSku = $mpnSkus[0];
                $q->where(function ($query) use ($mpnSku) {
                    $query->where('p.sku', 'like', '%' . $mpnSku . '%')
                        ->orWhere('p.mpn', 'like', '%' . $mpnSku . '%');
                });
            })
            ->where('ctp.customer_id', $sellerId)
            ->available()
            ->with('tags')
            ->select(['p.sku', 'p.product_id', 'p.mpn'])
            ->get()
            ->each(function ($product) {
                /** @var Product $product */
                $tags = [];
                if ($product->tags->isNotEmpty()) {
                    foreach ($product->tags as $tag) {
                        /** @var Tag $tag */
                        $tags[] = $tag->tag_widget;
                    }
                }
                unset($product->tags);
                $product->tags = $tags;
            });
    }

    /**
     * 获取某些产品的信息和标签
     * @param array $productIds
     * @param int $width
     * @param int $height
     * @return array
     */
    public function getProductsMapIncludeTagsByIds(array $productIds ,int $width = 60,int $height = 60 )
    {
        return Product::query()
            ->whereIn('product_id', $productIds)
            ->with('tags')
            ->select(['sku', 'product_id', 'mpn', 'image'])
            ->get()
            ->each(function ($product) use ($width,$height) {
                /** @var Product $product */
                $tags = [];
                if ($product->tags->isNotEmpty()) {
                    foreach ($product->tags as $tag) {
                        /** @var Tag $tag */
                        $tags[] = $tag->tag_widget;
                    }
                }
                unset($product->tags);
                $product->tags = $tags;
                $product->image = StorageCloud::image()->getUrl($product->image, ['w' => $width, 'h' => $height]);
            })
            ->keyBy('product_id')
            ->toArray();
    }

    /**
     * 获取产品的某些信息和属性和标签
     * @param int $sellerId
     * @param int $productId
     * @return Product|false
     * @throws Exception
     */
    public function getProductInfoIncludeAttributeAndTags(int $sellerId, int $productId)
    {
        $product = Product::query()
            ->alias('p')
            ->join('oc_customerpartner_to_product as ctp', 'p.product_id', '=', 'ctp.product_id')
            ->where('ctp.customer_id', $sellerId)
            ->with(['tags', 'productNewOptionNames'])
            ->find($productId, ['p.*']);
        if (empty($product)) {
            return false;
        }

        $tags = [];
        if ($product->tags->isNotEmpty()) {
            foreach ($product->tags as $tag) {
                /** @var Tag $tag */
                $tags[] = $tag->tag_widget;
            }
        }
        unset($product->tags);
        $product->tags = $tags;

        $attributes = [];
        if ($product->productNewOptionNames->isNotEmpty()) {
            foreach ($product->productNewOptionNames as $option) {
                $attributes[$option->option_id] = $option->name;
            }
        } else {
            /** @var ModelCatalogProduct $mcp */
            $mcp = load()->model('catalog/product');
            $optionValue = $mcp->getProductOptionValue($productId, Option::MIX_OPTION_ID, $sellerId);
            if ($optionValue) {
                $attributes[Option::MIX_OPTION_ID] = $optionValue;
            }
        }

        unset($product->productNewOptionNames);
        ksort($attributes);
        $product->attributes = $attributes;

        return $product;
    }

    /**
     * 获取某个产品半年的价格（供折线图使用）
     * @param int $productId
     * @param bool $isUSA
     * @param $precision
     * @return array
     * @throws Exception
     */
    public function getProductHistoricalPrices(int $productId, bool $isUSA, $precision)
    {
        $openPriceDates = [];
        $openPriceDetail = [];
        $lastPrice = 0;

        $time = date('Y-m-d H:i:s', strtotime('-180 days'));

        /** @var ModelFuturesProduct $modelFuturesProduct */
        $modelFuturesProduct = load()->model('futures/product');
        $openPrices = $modelFuturesProduct->historyOpenPricesByProductId($productId, $time);

        foreach ($openPrices as $openPrice) {
            if ($openPrice['min_price'] == $lastPrice) {
                continue;
            }

            if (!$isUSA) {
                $orderCreatedAt = $modelFuturesProduct->orderCreatedAtByProductIdDatePrice($productId, $openPrice['format_date'], $openPrice['min_price']);
                $openPriceTime = strtotime(changeOutPutByZone($orderCreatedAt, session()));
            } else {
                $openPriceTime = strtotime($openPrice['format_date']);
            }

            $openPriceDates[] = date('m.d', $openPriceTime);
            $openPriceDetail[] = round($openPrice['min_price'], $precision);
            $lastPrice = $openPrice['min_price'];
        }

        /** @var Currency $currency */
        $currency = app('registry')->get('currency');
        $currencyCode = session()->get('currency');

        $openPriceDetail = empty($openPriceDetail) ? [0] : $openPriceDetail;
        return [
            'open_price' => max($openPriceDetail) != min($openPriceDetail) ? $currency->formatCurrencyPrice(min($openPriceDetail), $currencyCode) . ' - ' . $currency->formatCurrencyPrice(max($openPriceDetail), $currencyCode) : $currency->formatCurrencyPrice(min($openPriceDetail), $currencyCode),
            'open_price_detail' => [
                'dates' => json_encode($openPriceDates),
                'prices' => json_encode($openPriceDetail),
                'year' => date('Y', strtotime('-180 days')) < date('Y') ? date('Y') - 1 . ' - ' . date('Y') : date('Y'),
            ],
        ];
    }

    /**
     * 获取某个产品的当前所有价格
     * @param Product $product
     * @param $precision
     * @param array $transactionTypes
     * @return array
     * @throws Exception
     */
    public function getProductCurrentPrices(Product $product, $precision, $transactionTypes = [])
    {
        /** @var Currency $currency */
        $currency = app('registry')->get('currency');
        $currencyCode = session()->get('currency');

        $allPrices = [];
        $referencePrices = [];

        //open
        if (in_array(ProductTransactionType::NORMAL, $transactionTypes)) {
            $currentPrice = $currency->format(round($product->price, $precision), $currencyCode);
            $allPrices[] = $product->price;
            $referencePrices['current_price'] = $currentPrice;
        }

        //rebate
        if (in_array(ProductTransactionType::REBATE, $transactionTypes)) {
            $rebatePrices = [];
            /** @var ModelAccountCustomerpartnerRebates $modelAccountCustomerPartnerRebates */
            $modelAccountCustomerPartnerRebates = load()->model('account/customerpartner/rebates');
            $rebates = $modelAccountCustomerPartnerRebates->get_rebates_template_display_batch($product->product_id);
            foreach ($rebates as $rebate) {
                $prices = [];
                foreach ($rebate['child'] as $rebateChild) {
                    if ($rebateChild['product_id'] == $product->product_id) {
                        $prices[] = $rebateChild['price'] - $rebateChild['rebate_amount'];
                    }
                }
                $minPrice = min($prices) < 0 ? 0 : min($prices);
                $maxPrice = max($prices) < 0 ? 0 : max($prices);
                $allPrices[] = $minPrice;
                $allPrices[] = $maxPrice;
                if ($minPrice == $maxPrice) {
                    $rebatePrice = $currency->format(round($minPrice, $precision), $currencyCode);
                } else {
                    $rebatePrice = $currency->format(round($minPrice, $precision), $currencyCode) . ' - ' . round($maxPrice, $precision);
                }
                $rebatePrices[] = [
                    'price' => $rebatePrice,
                    'msg' => $rebate['qty'] . ' PCS in ' . $rebate['day'] . ' Days',
                ];
            }
            $referencePrices['rebate_price'] = $rebatePrices;
        }

        //future
        if (in_array(ProductTransactionType::FUTURE, $transactionTypes)) {
            $futurePrices = [];
            /** @var ModelFuturesContract $modelFuturesContract */
            $modelFuturesContract = load()->model('futures/contract');
            $contracts = $modelFuturesContract->getContractsByProductId($product->product_id);
            foreach ($contracts as $contract) {
                $allPrices[] = $contract['margin_unit_price'];
                $allPrices[] = $contract['last_unit_price'];
                $marginUnitPrice = $currency->formatCurrencyPrice($contract['margin_unit_price'], $currencyCode);
                $lastUnitPrice = $currency->formatCurrencyPrice($contract['last_unit_price'], $currencyCode);
                if ($contract['margin_unit_price'] == $contract['last_unit_price']) {
                    $futurePrice = $lastUnitPrice;
                } elseif ($contract['margin_unit_price'] > $contract['last_unit_price']) {
                    $futurePrice = $lastUnitPrice . ' - ' . $marginUnitPrice;
                } else {
                    $futurePrice = $marginUnitPrice . ' - ' . $lastUnitPrice;
                }
                $futurePrices[] = [
                    'price' => $futurePrice,
                    'msg' => substr($contract['delivery_date'], 0,10)
                ];
            }
            $referencePrices['future_price'] = $futurePrices;
        }

        //margin
        if (in_array(ProductTransactionType::MARGIN, $transactionTypes)) {
            $marginPrices = [];
            /** @var ModelAccountCustomerpartnerMargin $modelAccountCustomerPartnerMargin */
            $modelAccountCustomerPartnerMargin = load()->model('account/customerpartner/margin');
            $margins = $modelAccountCustomerPartnerMargin->getMarginTemplateForProduct($product->product_id);
            foreach ($margins as $margin) {
                $allPrices[] = $margin['price'];
                $marginPrices[] = [
                    'price' => $currency->format(round($margin['price'], $precision), $currencyCode),
                    'msg' => ($margin['min_num'] == $margin['max_num'] ? $margin['min_num'] : $margin['min_num'] . ' - ' . $margin['max_num']) . ' PCS'
                ];
            }
            $referencePrices['margin_price'] = $marginPrices;
        }

        //spot
        if (in_array(ProductTransactionType::SPOT, $transactionTypes)) {
            $spotPrices = [];
            /** @var ModelAccountwkquotesadmin $modelAccountWkQuotesAdmin */
            $modelAccountWkQuotesAdmin = load()->model('account/wk_quotes_admin');
            $quotes = $modelAccountWkQuotesAdmin->getQuoteDetailsByProductId($product->product_id);
            foreach ($quotes as $quote) {
                $allPrices[] = $quote['home_pick_up_price'];
                $spotPrices[] = [
                    'price' => $currency->format(round($quote['home_pick_up_price'], $precision), $currencyCode),
                    'msg' => ($quote['max_quantity'] == $quote['min_quantity'] ? $quote['min_quantity'] : $quote['min_quantity'] . ' - ' . $quote['max_quantity']) . ' PCS'
                ];
            }
            $referencePrices['spot_price'] = $spotPrices;
        }

        return [
            'reference_price' => max($allPrices) != min($allPrices) ? $currency->formatCurrencyPrice(min($allPrices), $currencyCode) . ' - ' . $currency->formatCurrencyPrice(max($allPrices), $currencyCode) : $currency->formatCurrencyPrice(min($allPrices), $currencyCode),
            'reference_price_detail' => $referencePrices,
        ];
    }

    /**
     * 从b2b manage获取指定产品的运费信息
     *
     * @param array $productList [{product_id,qty}],一组商品，包含数量，内部会判断这组商品是否包含ltl商品，如果有一个ltl，则全部按ltl计费
     * @param int $customerId
     * @param bool $isSingleRequest 是否单个请求,传true的话，产品将不会因为包含ltl而合并请求
     * @return false|array 获取失败会返回false
     *                     成功返回数组，ltl_flag为是否含有ltl产品
     *                     如果ltl_flag == true,data为运费信息 (@see FreightDTO) 对象
     *                     如果ltl_flag == false,data为运费数组，key为商品id value为 (@see FreightDTO) 对象
     */
    public function getB2BManageProductFreightByProductList(array $productList, int $customerId, bool $isSingleRequest = false)
    {
        $productVolumes = $this->getProductVolumeByProductId(array_column($productList,'product_id'));
        if (!$productVolumes) {
            return false;
        }
        $productQty = array_column($productList, 'qty', 'product_id');
        $productVolumes = collect($productVolumes);// 转换成collection方便后面查询
        $ltlProduct = $productVolumes
            ->where('ltl_flag', true)
            ->all();
        $return = [
            'ltl_flag' => false,
            'data' => []
        ];
        if(!empty($ltlProduct) && !$isSingleRequest){
            $return['ltl_flag'] = true;
            // 如果包含ltl
            $freightProduct = new FreightProductDTO([
                'ltlFlag' => true, 'length' => 1,
                'width' => 1, 'height' => 1, 'actualWeight' => 1
            ]);
            foreach ($productVolumes as $productVolume) {
                if ($productVolume['combo_flag']) {
                    // 如果是combo 把子产品组装进去
                    foreach ($productVolume['combo_list'] as $key => $value) {
                        $freightProduct->addComboItem(new FreightProductDTO([
                            'ltlFlag' => boolval($value['ltl_flag']),
                            'dangerFlag' => boolval($value['danger_flag']),
                            'length' => floatval($value['length']),
                            'width' => floatval($value['width']),
                            'height' => floatval($value['height']),
                            'actualWeight' => floatval($value['weight']),
                            'qty' => $value['qty'] * $productQty[$productVolume['product_id']],//总的数量
                        ]));
                    }
                } else {
                    $freightProduct->addComboItem(new FreightProductDTO([
                        'ltlFlag' => boolval($productVolume['ltl_flag']),
                        'dangerFlag' => boolval($productVolume['danger_flag']),
                        'length' => floatval($productVolume['length']),
                        'width' => floatval($productVolume['width']),
                        'height' => floatval($productVolume['height']),
                        'actualWeight' => floatval($productVolume['weight']),
                        'qty' => $productQty[$productVolume['product_id']],
                    ]));
                }
            }
            try {
                $freightDTO = RemoteApi::freight()->getFreight($freightProduct, $customerId);
            } catch (Exception $exception) {
                return false;
            }
            $return['data'] = $freightDTO;
        } else {
            // 不包含LTL
            foreach ($productVolumes as $productVolume) {
                $freightProduct = new FreightProductDTO([
                    'ltlFlag' => boolval($productVolume['ltl_flag']),
                    'dangerFlag' => boolval($productVolume['danger_flag']),
                    'length' => $productVolume['length'],
                    'width' => $productVolume['width'],
                    'height' => $productVolume['height'],
                    'actualWeight' => $productVolume['weight'],
                    'qty' => $productQty[$productVolume['product_id']],
                    'day' => 1,
                ]);
                if ($productVolume['combo_flag']) {
                    foreach ($productVolume['combo_list'] as $value) {
                        $freightProduct->addComboItem(new FreightProductDTO([
                            'ltlFlag' => boolval($value['ltl_flag']),
                            'dangerFlag' => boolval($value['danger_flag']),
                            'length' => floatval($value['length']),
                            'width' => floatval($value['width']),
                            'height' => floatval($value['height']),
                            'actualWeight' => floatval($value['weight']),
                            'qty' => $value['qty'],
                        ]));
                    }
                }
                try {
                    $freightDTO = RemoteApi::freight()->getFreight($freightProduct, $customerId);
                } catch (Exception $exception) {
                    $freightDTO = false;
                }
                $return['data'][$productVolume['product_id']] = $freightDTO;
            }
        }
        return $return;
    }

    /**
     * 获取产品体积 长宽高以及体积
     * 如果是combo品还会获取子产品的信息
     *
     * @param int|array $productId
     * @return array
     */
    public function getProductVolumeByProductId($productId)
    {
        $key = [__CLASS__, __FUNCTION__, $productId];
        if ($data = $this->getRequestCachedData($key)) {
            return $data;
        }
        // 查询商品信息
        $productList = Product::query()->alias('p')
            ->leftJoinRelations('customerPartnerToProduct as c2p')
            ->leftJoin('oc_product_to_tag AS p2t', function ($j) {
                $j->on('p2t.product_id', '=', 'p.product_id')
                    ->where('p2t.tag_id', '=', 1);
            })
            ->whereIn('p.product_id', (array)$productId)
            ->get([
                'p.product_id',
                'p.sku',
                'p.mpn',
                'p.weight',
                'p.length',
                'p.width',
                'p.height',
                'p.combo_flag',
                'p.danger_flag',
                'p2t.tag_id AS ltl_flag'
            ]);
        if ($productList->isEmpty()) {
            return [];
        }
        $return = [];
        // 查询所有的子产品信息
        $productSetInfoList = ProductSetInfo::query()->alias('ps')
            ->leftJoinRelations('setProduct as p')
            ->leftJoin('oc_product_to_tag AS p2t', function ($j) {
                $j->on('p2t.product_id', '=', 'ps.set_product_id')
                    ->where('p2t.tag_id', '=', 1);
            })
            ->whereIn('ps.product_id', $productList->pluck('product_id')->toArray())
            ->get([
                'ps.product_id',
                'p.sku',
                'p.mpn',
                'p.weight',
                'p.height',
                'p.length',
                'p.width',
                'p.danger_flag',
                'ps.qty',
                'ps.set_product_id',
                'p2t.tag_id AS ltl_flag'
            ]);
        foreach ($productList as $product) {
            $productInfo = [];
            $productInfo['product_id'] = $product->product_id;
            $productInfo['weight'] = $product->weight;
            $productInfo['length'] = $product->length;
            $productInfo['width'] = $product->width;
            $productInfo['height'] = $product->height;
            $productInfo['ltl_flag'] = boolval($product->ltl_flag);
            $productInfo['danger_flag'] = $product->danger_flag;
            $productInfo['combo_flag'] = $product->combo_flag;
            if ($product->combo_flag) {
                //如果是combo品，则再查询子产品
                $__productSetInfoList = $productSetInfoList->where('product_id',$product->product_id)->all();
                foreach ($__productSetInfoList as $productSetInfo) {
                    $productInfo['combo_list'][] = [
                        'weight' => $productSetInfo->weight,
                        'height' => $productSetInfo->height,
                        'length' => $productSetInfo->length,
                        'width' => $productSetInfo->width,
                        'ltl_flag' => boolval($productSetInfo->ltl_flag),
                        'danger_flag' => $productSetInfo->danger_flag,
                        'qty' => $productSetInfo->qty,
                        'set_product_id' => $productSetInfo->set_product_id
                    ];
                }
                unset($__productSetInfoList);
            }
            //设置单个缓存
            $this->setRequestCachedData([__CLASS__, __FUNCTION__, $product->product_id], $productInfo);
            $return[] = $productInfo;
        }
        if (is_int($productId)) {
            //查询的单个产品直接返回
            return $return[0] ?? [];
        }
        //设置组合缓存
        if ($return) {
            $this->setRequestCachedData($key, $return);
        }
        return $return;
    }

    /**
     * 根据productId获取seller信息
     * @param int $productId
     * @return \Illuminate\Database\Eloquent\Model|Builder|object|null
     */
    public function getSellerInfoByProductId($productId)
    {
        return db('oc_customerpartner_to_product as ctp')
            ->select('ctc.screenname','ctc.customer_id')
            ->join('oc_customerpartner_to_customer as ctc', 'ctp.customer_id', '=', 'ctc.customer_id')
            ->where(['ctp.product_id' => $productId])
            ->first();
    }

    /**
     * 商品是否为 giga 或者 joy
     * @param string $sku
     * @return bool
     */
    public function isGigaOrJoyProduct(string $sku)
    {
        $sellerIds = app(SetupRepository::class)->getValueByKey('JOY_BUY_SELLER_ID');
        return Product::query()->alias('p')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customer as c', 'ctp.customer_id', '=', 'c.customer_id')
            ->where('p.sku', '=', $sku)
            ->where(function ($query) use ($sellerIds) {
                $query->whereIn('ctp.customer_id', explode(',', $sellerIds))
                    ->orWhere('c.accounting_type', '=', CustomerAccountingType::GIGA_ONSIDE);
            })
            ->exists();
    }

    //获取不可售卖平台，由于商品管理里的新增时候时候是写死在js里面，这儿只能也写死
    public function getForbiddenSellPlatform()
    {
        return ['Amazon', 'Wayfair', 'Walmart', 'Shopify', 'eBay', 'Overstock', 'Home Depot', "Lowe's", 'Wish', 'Newegg', 'AliExpress'];
    }

    /**
     * @param array $products
     * @param array $selects
     * @param array $relations
     * @return \App\Models\Product\Product[]|\Framework\Model\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Query\Builder[]|\Illuminate\Support\Collection
     */
    public function getProductByIds(array $products, array $selects = [], array $relations = [])
    {
        $query = Product::query();
        if (!empty($selects)) {
            $query->select($selects);
        }
        if (!empty($relations)) {
            $query->with($relations);
        }
        return $query->whereIn('product_id', $products)->get();
    }

    /**
     * description:获取添加商品配置的字段信息
     * @param array $productIds
     * @param int $type
     * @return array
     */
    public function getCustomFieldByIds(array $productIds,int $type=1)
    {
        return ProductCustomField::query()
            ->whereIn('product_id', $productIds)
            ->where('type', $type)
            ->get()
            ->groupBy('product_id')
            ->toArray();
    }

    /**
     * description:获取商品的ext
     * @param array $productIds
     * @return array
     */
    public function getProductExtByIds(array $productIds)
    {
        return ProductExts::query()
            ->whereIn('product_id', $productIds)
            ->with(['fillerOptionValue:option_value_id,name'])
            ->get(['id','product_id','filler'])
            ->keyBy('product_id')
            ->toArray();
    }

}
