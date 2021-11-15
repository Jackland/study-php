<?php

use App\Components\Storage\StorageCloud;
use App\Enums\Common\CountryEnum;
use App\Enums\Common\YesNoEnum;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Helper\CountryHelper;
use App\Helper\SummernoteHtmlEncodeHelper;
use App\Logging\Logger;
use App\Models\CustomerPartner\CustomerPartnerToProduct;
use App\Models\Customer\Country as CountryModel;
use App\Models\Product\Option\ProductPackageFile;
use App\Models\Product\Package\ProductPackageOriginalDesignImage;
use App\Models\Product\Product;
use App\Models\Product\ProductDescription;
use App\Models\Product\ProductAudit;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Repositories\Freight\EuropeFreightRepository;
use App\Repositories\Margin\ContractRepository;
use App\Repositories\Marketing\CampaignRepository;
use App\Repositories\Marketing\MarketingTimeLimitDiscountRepository;
use App\Repositories\Product\CategoryRepository;
use App\Repositories\Product\Option\OptionValueRepository;
use App\Repositories\Product\PackageRepository;
use App\Repositories\Product\ProductAuditRepository;
use App\Repositories\Product\ProductOptionRepository;
use App\Repositories\Product\ProductPriceRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Seller\SellerProductRatioRepository;
use App\Repositories\Seller\SellerRepository;
use App\Services\FeeOrder\StorageFeeCalculateService;
use App\Services\Marketing\CampaignService;
use App\Services\Marketing\MarketingTimeLimitDiscountService;
use App\Services\Product\ProductService;
use Framework\App;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\JsonResponse;
use ZipStream\Exception\FileNotFoundException;
use ZipStream\Exception\FileNotReadableException;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;
use App\Repositories\Marketing\MarketingDiscountRepository;
use App\Helper\MoneyHelper;

/**
 * Class ControllerProductProduct
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountCustomerpartnerMargin $model_account_customerpartner_margin
 * @property ModelAccountCustomerpartnerRebates $model_account_customerpartner_rebates
 * @property ModelAccountCustomer $model_account_customer
 * @property ModelAccountNotification $model_account_notification
 * @property ModelAccountwkquotesadmin $model_account_wk_quotes_admin
 * @property ModelCatalogCategory $model_catalog_category
 * @property ModelCatalogInformation $model_catalog_information
 * @property ModelCatalogManufacturer $model_catalog_manufacturer
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCatalogReview $model_catalog_review
 * @property ModelCustomerpartnerBargain $model_customerpartner_bargain
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 * @property ModelCustomerpartnerInformation $model_customerpartner_information
 * @property ModelCustomerpartnerMaster $model_customerpartner_master
 * @property ModelCustomerpartnerProductReview $model_customerpartner_product_review
 * @property ModelCustomerpartnerStoreRate $model_customerpartner_store_rate
 * @property ModelExtensionModulePrice $model_extension_module_price
 * @property ModelExtensionModuleProductShow $model_extension_module_product_show
 * @property ModelExtensionModuleShipmentTime $model_extension_module_shipment_time
 * @property ModelFuturesAgreement $model_futures_agreement
 * @property ModelFuturesContract $model_futures_contract
 * @property ModelFuturesTemplate $model_futures_template
 * @property ModelLocalisationCountry $model_localisation_country
 * @property ModelMarketingCampaignActivity $model_marketing_campaign_activity
 * @property ModelMessageMessage $model_message_message
 * @property ModelToolImage $model_tool_image
 * @property ModelCustomerpartnerSellerCenterIndex model_customerpartner_seller_center_index
 */
class ControllerProductProduct extends Controller
{
    const COUNTRY_JAPAN = 107;

    protected $java_redis_cache = null ;

    public function __construct(Registry $registry)
    {
        $this->java_redis_cache = app('redis')->driver('b2b_java'); //搜索大功能和java配合专用redis
        parent::__construct($registry);
    }

    private $country_map = [
        'JPN'  => 107,
        'GBR'  => 222,
        'DEU'  => 81,
        'USA'  => 223
    ];

    /**
     * 产品详情页
     * @throws FilesystemException
     * @throws Exception
     */
    public function index()
    {
        $this->load->language('product/product');

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => url()->to(['common/home']),
        );

        $product_id = (int)$this->request->get('product_id', 0);
        $auditId = (int)$this->request->get('audit_id', 0);//Seller编辑产品后预览产品，这是[审核记录]的主键

        $this->load->model('catalog/category');
        $this->load->model('account/customer');
        $this->load->model('customerpartner/master');
        $currency = $this->session->get('currency');
        $precision = intval($decimal_place=$this->currency->getDecimalPlace($currency));//货币小数位数
        $data['decimal_place']=$decimal_place;
        $data['currency']=$currency;
        // add by LiLei 判断用户是否登录
        $customFields = $this->customer->getId();
        // 验证是否为Seller
        $data['isSeller'] = $this->customer->isPartner();
        $buyer_id     = $this->customer->isLogged() ? $this->customer->isPartner() ? 0 : $this->customer->getId() : 0;
        $customerCountryId = null;
        if ($customFields) {
            $data['isLogin'] = true;
            // 判断Customer国别
            $customerCountryId = $this->customer->getCountryId();
        } else {
            $data['isLogin'] = false;
        }
        $isCollectionFromDomicile         = $this->customer->isCollectionFromDomicile();//是否为上门取货buyer
        $data['isCollectionFromDomicile'] = $isCollectionFromDomicile;
        $data['isInnerAutoBuy'] = intval($this->customer->innerAutoBuyAttr1());//内部自动购买产销异体账号
        // 获取产品是否是囤货产品
        $checkRet = app(CustomerRepository::class)->getUnsupportStockData([$product_id]);
        $data['unsupport_stock'] = in_array($product_id,$checkRet);
        /**
         * 更新对应notification为已读
         */
        if ($data['isSeller']) {
            $this->load->model('account/notification');
            if (!empty($this->request->get('ca_id', ''))) {
                $this->model_account_notification->updateIsRead($this->request->get('ca_id'));
            } elseif ($product_id) {
                $this->model_account_notification->updateIsReadNotByCa($product_id, (int)$this->customer->getId(), 0);
            }
        }

        // 获取当前国家
        $countryCode = $this->session->get('country', 'USA');
        // 获取国家ID
        // 获取countryId
        $countryId = CountryHelper::getCountryByCode($countryCode);

        $this->load->model('catalog/product');
        //139 无效产品只能通过链接进入产品详情，不能通过搜索从缩略图进入
        $product_info = $this->model_catalog_product->getProductByDetails($product_id, $customFields,0);
        $data['originalProductImages'] = ProductPackageOriginalDesignImage::query()
            ->where('product_id',$product_id)
            ->get()
            ->toArray();
        $data['original_product_image_url'] = $this->config->get('original_product_image_big');
        // 获取originalImage
        //用来区分保证金头款
        $product_type = $product_info['product_type'];
        $data['big_client_discount'] = 0; //大客户折扣
        $data['xu_store_discount'] = null;
        if ($product_info && $product_info['country_id'] == $countryId) {
            //统计30天浏览量(PV)
            $this->lastThirtyDaysVisit($product_id);
            //统计每个用户浏览商品的最后时间(原始：3天内是否浏览过)
            if ($customFields) {
                $this->lastThirdDaysVisitedCustomerProduct($customFields, $product_id);

                if ($product_type == ProductType::NORMAL) {
                    $data['xu_store_discount'] = app(MarketingTimeLimitDiscountService::class)->calculateCurrentDiscountInfo(customer()->getId(), $product_info['customer_id'], $product_id);
                    $data['big_client_discount'] = $data['xu_store_discount']['current_selected']['discount'] ?? 0;
                }
            }

            $data['loginId'] = $customFields;
            $data['customer_id'] = $product_info['customer_id'];
            $data['seller_id'] = $product_info['customer_id'];//Seller ID
            $data['store_code'] = $product_info['store_code'];
            $data['seller_status'] = $product_info['seller_status'];
            $data['product_type'] = $product_info['product_type'];
            $data['rebate_exists'] = $product_info['rebate_exists'];//是否参加返点
            //14103 所有上过架的产品都可以被检索到，如果产品已失效，则在检索结果页和商品详情页标注出商品无效的原因
            $data['unsee']  = $product_info['unsee'];
            $data['freight']  = $product_info['freight'];
            $data['package_fee']  = $product_info['package_fee'];
            $data['pageView'] = $product_info['viewed'];
            $data['return_rate_str'] = $product_info['return_rate_str'];
            $data['day30Sales'] = $product_info['30Day'];
            $data['all_days_sale'] = $product_info['all_days_sale'];
            $data['download_cnt'] = $product_info['download_cnt'];
            $data['seller_price'] = $product_info['seller_price'];
            $data['can_sell'] = $product_info['canSell'];   // bts 是否建立关联
            $data['quantity'] = $product_info['c2pQty'];
            $data['price_display'] = $product_info['price_display'];
            $data['quantity_display'] = $product_info['quantity_display'];
            $data['is_delicacy_price'] = $product_info['is_delicacy_price'];
            $data['danger_flag'] = $product_info['danger_flag'];
            if (customer()->isLogged()
                && customer()->isPartner()
                && (customer()->getId() != $product_info['customer_id'])
            ) {
                // Seller查看了其他Seller 的产品，则 隐藏价格和库存
                $data['price_display'] = 0;
                $data['quantity_display'] = 0;
            }
            $data['sku'] = $product_info['sku'];
            $data['mpn'] = $product_info['mpn'];
            $data['horn_mark'] = $product_info['is_new'] ? 'new' : '';
            $data['name'] = $product_info['name'];//产品名称
            $data['combo_flag'] = $product_info['combo_flag'];
            $data['page_product_type_name'] = app(ProductRepository::class)->getProductTypeNameForBuyer($product_info);
            $data['page_package_size_list'] = app(ProductRepository::class)->getPackageSizeForBuyer($product_info, $countryId);
            $data['need_install'] = $product_info['need_install'];
            $data['product_size'] = $product_info['product_size'];
            $data['non_sellable_on'] = !empty($product_info['non_sellable_on'])
                ? explode(',', $product_info['non_sellable_on'])
                : []; // non sellable on
            $data['is_seller_self'] = $is_seller_self = false;//是Seller自己的产品
            if ($data['isLogin']) {
                if ($data['loginId'] == $product_info['customer_id']) {
                    $data['is_seller_self'] = $is_seller_self = true;
                }
            }
            $data['stock_tip_html'] = $this->stockTipHtml($product_id);

            // 33309 新增字段
            /** @var Product $productInfo */
            $productInfo = Product::queryRead()->with(['ext'])->where('product_id', $product_id)->first();
            $data['upc'] = $productInfo->upc;
            $data['is_customize'] = $productInfo->ext->is_customize ?? '';
            $data['origin_place'] = $productInfo->ext ? CountryModel::queryRead()->where('iso_code_3', $productInfo->ext->origin_place_code)->value('name') : '';
            $data['filler'] = app(OptionValueRepository::class)->getNameByOptionValueId($productInfo->ext->filler ?? '');
            $data['information_custom_field'] = $productInfo->informationCustomFields;
            $data['assemble_length'] = $this->formatAssembleField($productInfo->ext->assemble_length ?? '');
            $data['assemble_width'] = $this->formatAssembleField($productInfo->ext->assemble_width ?? '');
            $data['assemble_height'] = $this->formatAssembleField($productInfo->ext->assemble_height ?? '');
            $data['assemble_weight'] = $this->formatAssembleField($productInfo->ext->assemble_weight ?? '');
            $data['dimensions_custom_field'] = $productInfo->dimensionCustomFields;

            $data['unit_length'] = $countryId == CountryEnum::AMERICA ? 'in.' : 'cm';
            $data['unit_weight'] = $countryId == CountryEnum::AMERICA ? 'lbs' : 'kg';

            // 33309 documents
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

            $data['certification_documents'] = $formatCertificationDocuments;
            $packageFiles = $productInfo->packageFiles;
            $data['material_manuals'] = [];
            foreach ($packageFiles as $packageFile) {
                /** @var ProductPackageFile $packageFile */
                $tmp = [
                    'orig_url' => $packageFile->file,
                    'name' =>$packageFile->origin_file_name,
                ];
                app(ProductService::class)->resolveImageItem($tmp, true);
                $data['material_manuals'][] = $tmp;
            }

            $url = '';
            //region url
            if (isset($this->request->get['path'])) {
                $url .= '&path=' . $this->request->get['path'];
            }
            if (isset($this->request->get['filter'])) {
                $url .= '&filter=' . $this->request->get['filter'];
            }
            if (isset($this->request->get['manufacturer_id'])) {
                $url .= '&manufacturer_id=' . $this->request->get['manufacturer_id'];
            }
            if (isset($this->request->get['tag'])) {
                $url .= '&tag=' . $this->request->get['tag'];
            }
            if (isset($this->request->get['description'])) {
                $url .= '&description=' . $this->request->get['description'];
            }
            if (isset($this->request->get['category_id'])) {
                $url .= '&category_id=' . $this->request->get['category_id'];
            }
            if (isset($this->request->get['sub_category'])) {
                $url .= '&sub_category=' . $this->request->get['sub_category'];
            }
            if (isset($this->request->get['sort'])) {
                $url .= '&sort=' . $this->request->get['sort'];
            }
            if (isset($this->request->get['order'])) {
                $url .= '&order=' . $this->request->get['order'];
            }
            if (isset($this->request->get['page'])) {
                $url .= '&page=' . $this->request->get['page'];
            }
            if (isset($this->request->get['limit'])) {
                $url .= '&limit=' . $this->request->get['limit'];
            }
            //endregion
            //region 顶部面包屑信息加入，产品的分类层级信息，点击分类可进入分类筛选列表页
            $result         = app(CategoryRepository::class)->getCategoryByProductId($product_id);
            $arr_categories = [];
            $arr_cateid     = [];
            $max_length     = 0;
            foreach ($result as $key => $value) {
                if ($value['count'] > $max_length) {
                    $max_length     = $value['count'];
                    $arr_categories = $value['arr_label'];
                    $arr_cateid     = $value['arr_id'];
                }
            }
            foreach ($arr_categories as $key => $value) {
                $data['breadcrumbs'][] = array(
                    'text' => $value,
                    'href' => $this->url->link('product/category', 'category_id=' . $arr_cateid[$key]),
                );
            }
            $product_name        = $product_info['name'];
            $product_name_length = mb_strlen($product_info['name']);
            if ($product_name_length > 120) {
                $product_name = mb_substr($product_info['name'], 0, 116) . '......';
            }
            $data['breadcrumbs'][] = array(
                'text' => $product_name,
                'href' => $this->url->link('product/product', $url . '&product_id=' . $product_id)
            );
            //endregion
            $this->document->setTitle($product_info['name']);
            $this->document->setDescription($product_info['meta_description']);
            $this->document->setKeywords($product_info['meta_keyword']);
            $this->document->addLink(url()->to(['product/product', 'product_id'=>$product_id]), 'canonical');
            $this->document->addScript('catalog/view/javascript/jquery/magnific/jquery.magnific-popup.min.js');
            $this->document->addStyle('catalog/view/javascript/jquery/magnific/magnific-popup.css');

            $data['heading_title'] = $product_info['name'];
            $data['text_login']    = sprintf($this->language->get('text_login'), url()->to(['account/login']), url()->to(['account/register']));




            $data['product_id']    = $product_id;
            $data['manufacturer']  = $product_info['manufacturer'];
            $data['manufacturers'] = url()->to(['product/manufacturer/info', 'manufacturer_id' => $product_info['manufacturer_id']]);
            $data['description'] = SummernoteHtmlEncodeHelper::decode($product_info['description']);
            $data['return_warranty_text'] = SummernoteHtmlEncodeHelper::decode($product_info['return_warranty_text']);
            $selfSupport = $product_info['self_support'];//是否自营 1为自营 0为非自营




            //超大件
            $is_oversize = $this->model_catalog_product->checkIsOversizeItem($product_id);
            $data['is_oversize'] = $is_oversize ? "true" : "false";
            if ($is_oversize) {
                $data['oversize_material_policy'] = configDB('oversize_material_policy');
            }
            //标签同一拼接方法
            $special_tags = $this->model_catalog_product->getProductTagHtmlForDetailPage($product_id);
            if (!empty($special_tags)) {
                $data['special_tags'] = $special_tags;
            }




            //region
            if ($data['isLogin'] && !$data['isSeller']) {
                $product = Product::find($product_id);
                $storageFeeRepo = app(StorageFeeRepository::class);
                if ($storageFeeRepo->canEnterStorageFee($product)) {
                    //需要入仓租的再继续查询仓租费等信息
                    list($volume,) = $storageFeeRepo->calculateProductVolume($product);
                    list($feeDay) = app(StorageFeeCalculateService::class)->calculateStorageFeeOneDay($countryId, 2, $volume);
                    $data['storage_fee_day'] = $feeDay;
                    $data['storage_fee_description_id'] = app(StorageFeeRepository::class)->getStorageFeeDescriptionId($countryId);
                }
            }
            $data['country_code'] = $countryCode;
            //endregion




            //定义 url
            //region Message to Seller 按钮
            $data['can_contact_mail'] = true;
            if (isset($product_info['customer_id']) && ($product_info['customer_id'] == $this->customer->getId())) {
                $data['can_contact_mail'] = false;
            } else {
                $data['can_contact_mail'] = configDB('marketplace_customercontactseller'); // marketplace_customercontactseller value=1
            }
            $data['contact_seller_link'] = url()->to(['message/seller/addMessage', 'receiver_id' => $product_info['customer_id'], 'item_code' => $product_info['sku']]);
            $data['login_link'] = url()->to(['account/login']);
            //endregion




            $this->load->model('tool/image');
            //region 产品图片 产品主图
            $thumb = $product_info['image'];
            if (isset($thumb)) {
                $image_url     = $this->model_tool_image->resize($thumb, configDB('theme_' . configDB('config_theme') . '_image_popup_width'), configDB('theme_' . configDB('config_theme') . '_image_popup_height'));
                $data['popup'] = $image_url;
                $data['thumb'] = $image_url;
            } else {
                $data['popup'] = '';
                $data['thumb'] = '';
            }
            //endregion




            //region 产品图片 产品轮播图
            $data['images'] = array();
            $results        = $this->model_catalog_product->getProductImages($product_id);
            foreach ($results as $result) {
                $image = $result['image'];
                $data['images'][] = array(
                    'popup' => $this->model_tool_image->resize($image, configDB('theme_' . configDB('config_theme') . '_image_popup_width'), configDB('theme_' . configDB('config_theme') . '_image_popup_height')),
                    'thumb' => $this->model_tool_image->resize($image, configDB('theme_' . configDB('config_theme') . '_image_additional_width'), configDB('theme_' . configDB('config_theme') . '_image_additional_height'))
                );
            }
            //endregion




            //region price
            /** @var ModelCatalogProduct $modelCatalogProduct */
            $modelCatalogProduct = $this->model_catalog_product;
            $be_delicacy = false;
            $ret = $modelCatalogProduct->getDelicacyManagePrice($product_id, (int)$this->customer->getId(), $product_info['customer_id']);
            $productDelicacyPrice = $ret['price'] ?? null;
            if ( $productDelicacyPrice !== null
                && !$data['rebate_exists']
            ) {
                $product_info['price'] = $productDelicacyPrice;
                $be_delicacy = true;
            }
            $data['is_delicacy_effected'] = $ret['is_delicacy_effected'] ?? false;
            $data['text_custom_or_price'] = $data['is_delicacy_effected'] ? 'Custom' : 'Price';
            //endregion

            //region
            $actual_price = 0;
            if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
                $tax_price = $this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax'));
                //#31737 商品详情页针对免税价调整
                $tax_price = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($product_info['customer_id'], customer()->getModel(), $tax_price);
                $actual_price = $tax_price;
                $data['price'] = $this->currency->formatCurrencyPrice($tax_price, $currency);
            } else {
                $data['price'] = false;
            }
            //endregion

            //region add by xxli
            $discountResult = $this->model_catalog_product->getDiscount($customFields, $product_info['customer_id']);
            if ($discountResult) {
                $data['price'] = $this->model_catalog_product->getDiscountPrice($product_info['price'], $discountResult);

                // #31737 商品详情页针对免税价调整
                if ($customerCountryId && $this->customer->getGroupId() == 13) {
                    if ($product_info['product_type'] != ProductType::NORMAL) {
                        $data['price'] = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice(intval($product_info['customer_id']), $data['price'], $customerCountryId);
                    } else {
                        [, $data['price'],] = app(ProductPriceRepository::class)->getProductTaxExemptionPrice(intval($product_info['customer_id']), $data['price']);
                    }
                } else {
                    if ($product_info['product_type'] == ProductType::NORMAL) {
                        $data['price'] = app(ProductPriceRepository::class)->getProductActualPriceByBuyer(intval($product_info['customer_id']), customer()->getModel(), $data['price']);
                    }
                }

                $actual_price = $data['price'];
                $data['price'] = $this->currency->formatCurrencyPrice($data['price'], $currency);
            }
            //endregion end xxli




            //region 开通云送仓
            //开通云送仓
            //2020年4月8日
            //zjg
            //1、美国
            //2.测试店铺、服务店铺、保证金店铺的产品、期货/现货保证金定金类产品详情页不出现云送仓的选项
            //101867 删除测试店铺
            //$data['cwf'] = 0;   //没有云送仓
            //$data['cwf'] = 1;   //有运送仓
            //$data['cwf'] = 2;   //什么都不显示
            //
            //$data['cwf']=0;  //没有云送仓
            //添加云送仓的价格数据
            $data['total_cost_price'] = [];
            $customerCurrency = $this->session->get('currency');//用户币种
            if ($data['is_seller_self']) {
                //Seller看自己的产品的选项
                if ($this->customer->getCountryId() && $this->customer->isUSA()) {
                    if (!(in_array($product_info['seller_id'], SERVICE_STORE_ARRAY) || $product_type)) {    //||in_array($product_info['seller_id'],array(694,696,746,907,908))
                        $data['cwf'] = 1;   //有运送仓
                    } else {    //美国的测试店铺，不显示运费
                        $data['cwf'] = 2;   //什么都不显示
                    }
                } else {   //其他国家 正常展示
                    $data['cwf'] = 0;
                }


                $homepick_package_fee = $this->model_catalog_product->getNewPackageFee($product_id, true);//上门取货的打包费
                $dropshop_backage_fee = $this->model_catalog_product->getNewPackageFee($product_id, false);//一件代发的打包费

                $data['seller_extra_fee']['homepick']        = round($homepick_package_fee, 2);
                $data['seller_extra_fee']['dropshop']        = round(((float)$dropshop_backage_fee + (float)$data['freight']), 2);
                $data['seller_extra_fee_show']['homepick']   = $this->currency->formatCurrencyPrice($data['seller_extra_fee']['homepick'], $customerCurrency);
                $data['seller_extra_fee_show']['dropshop']   = $this->currency->formatCurrencyPrice($data['seller_extra_fee']['dropshop'], $customerCurrency);
                $data['seller_package_fee_show']['homepick'] = $this->currency->formatCurrencyPrice($homepick_package_fee, $customerCurrency);
                $data['seller_package_fee_show']['dropshop'] = $this->currency->formatCurrencyPrice($dropshop_backage_fee, $customerCurrency);
                $data['seller_freight_show']['homepick']     = $this->currency->formatCurrencyPrice(0, $customerCurrency);
                $data['seller_freight_show']['dropshop']     = $this->currency->formatCurrencyPrice($data['freight'], $customerCurrency);
                $data['total_cost_price']['dropshop_or_homepick']= $this->currency->formatCurrencyPrice($actual_price + $data['seller_extra_fee']['homepick'], $customerCurrency);
                //添加云送仓的价格数据
                if ($data['cwf'] == 1) {
                    //获取云送仓费用
                    $cwf_freight = $this->freight->getFreightAndPackageFeeByProducts(array($product_id));
                    $cwf_freight = $cwf_freight[$product_id];
                    if (($product_info['combo_flag']) && $product_info['combo_flag'] == 1) {    //combo
                        $extra_all_fee   = 0;
                        $package_all_fee = 0;
                        $freight_all_fee = 0;
                        $overweightSurcharge = 0;//超重附加费
                        $freight_rate    = 0;
                        $volume_all      = 0;
                        $weightTotal = 0;
                        $weightList = "";
                        $wthStr = "";
                        $tmpIndex = 1;
                        foreach ($cwf_freight as $tmp_k => $tmp_v) {
                            $extra_all_fee   += ($tmp_v['package_fee'] + $tmp_v['freight']) * $tmp_v['qty'];
                            $package_all_fee += $tmp_v['package_fee'] * $tmp_v['qty'];
                            $freight_all_fee += $tmp_v['freight'] * $tmp_v['qty'];
                            $freight_rate    = $tmp_v['freight_rate'];   //单价不用叠加
                            $volume_all      += $tmp_v['volume_inch'] * $tmp_v['qty'];
                            $overweightSurcharge += ($tmp_v['overweight_surcharge'] ?? 0);
                            $actualWeight = round($tmp_v['actual_weight'], 2);
                            $weightTotal += $actualWeight * $tmp_v['qty'];
                            $weightList .= sprintf($this->language->get('weight_detail_tip'),$tmpIndex,$actualWeight,$tmp_v['qty']);
                            $wthStr .= sprintf($this->language->get('volume_combo_detail_tip'),$tmpIndex,$tmp_v['length_inch'],$tmp_v['width_inch'],$tmp_v['height_inch'],$tmp_v['qty']);
                            $tmpIndex++;
                        }
                        $extra_all_fee += $overweightSurcharge;
                        $data['seller_extra_fee']['cwf']             = round($extra_all_fee, 2);
                        $data['total_cost_price']['cwf']             = $this->currency->formatCurrencyPrice($actual_price + $data['seller_extra_fee']['cwf'], $customerCurrency);
                        $data['seller_extra_fee_show']['cwf']        = $this->currency->formatCurrencyPrice($data['seller_extra_fee']['cwf'], $customerCurrency);
                        $data['seller_package_fee_show']['cwf']      = $this->currency->formatCurrencyPrice($package_all_fee, $customerCurrency);
                        $data['seller_freight_show']['cwf']          = $this->currency->formatCurrencyPrice($freight_all_fee, $customerCurrency);
                        $data['seller_freight_rate']['cwf']          = $freight_rate;
                        $data['seller_volume']['cwf']                = $volume_all;
                        $data['seller_overweight_surcharge']['cwf'] = $overweightSurcharge;
                        $data['seller_overweight_surcharge_show']['cwf'] = $this->currency->formatCurrencyPrice($overweightSurcharge, $customerCurrency);
                        $data['seller_weight_list_str']['cwf'] = $weightList;
                        $data['seller_weight_total']['cwf']  = sprintf('%.2f', $weightTotal);
                        $data['seller_wth_str']['cwf']  = $wthStr;
                    } else {
                        $data['seller_extra_fee']['cwf']            = round(((float)$cwf_freight['package_fee'] + (float)$cwf_freight['freight'] + (float)$cwf_freight['overweight_surcharge']), 2);
                        $data['total_cost_price']['cwf']            = $this->currency->formatCurrencyPrice($actual_price + $data['seller_extra_fee']['cwf'], $customerCurrency);
                        $data['seller_extra_fee_show']['cwf']       = $this->currency->formatCurrencyPrice($data['seller_extra_fee']['cwf'], $customerCurrency);
                        $data['seller_package_fee_show']['cwf']     = $this->currency->formatCurrencyPrice($cwf_freight['package_fee'], $customerCurrency);
                        $data['seller_freight_show']['cwf']         = $this->currency->formatCurrencyPrice($cwf_freight['freight'], $customerCurrency);
                        $data['seller_freight_rate']['cwf']         = $cwf_freight['freight_rate'];
                        $data['seller_volume']['cwf']               = $cwf_freight['volume_inch'];
                        $data['seller_overweight_surcharge']['cwf'] = $cwf_freight['overweight_surcharge'];
                        $data['seller_overweight_surcharge_show']['cwf'] = $this->currency->formatCurrencyPrice($cwf_freight['overweight_surcharge'], $customerCurrency);
                        $data['seller_weight_total']['cwf'] = round($cwf_freight['actual_weight'], 2);
                        $data['seller_wth_str']['cwf'] = sprintf($this->language->get('volume_detail_tip'),$cwf_freight['length_inch'],$cwf_freight['width_inch'],$cwf_freight['height_inch']);
                    }
                }
            } else {
                //Buyer看的产品的选项
                if ($this->customer->getCountryId() && $this->customer->isUSA() && !$isCollectionFromDomicile) {   //美国一键代发
                    if (!(in_array($product_info['seller_id'], SERVICE_STORE_ARRAY) || $product_type)) {    //||in_array($product_info['seller_id'],array(694,696,746,907,908))
                        $data['cwf'] = 1;   //有运送仓
                    } else {    //美国的测试店铺，不显示运费
                        $data['cwf'] = 2;   //什么都不显示
                    }
                    //云送仓的入口关闭，当产品归属Seller为Giga Onsite的seller时，一件代发的buyer账号产品详情页隐藏云送仓的购买入口
                    if ($product_info['seller_accounting_type'] == CustomerAccountingType::GIGA_ONSIDE || $product_info['seller_email'] == 'joybuy-us@gigacloudlogistics.com') {
                        $data['cwf'] = 0;
                    }
                } else {   //其他国家 正常展示
                    $data['cwf'] = 0;
                }

                if ($isCollectionFromDomicile) {    // 上门取货
                    $data['extra_fee']['dropshop_or_homepick'] = round($data['package_fee'], 2);
                } else {
                    $data['extra_fee']['dropshop_or_homepick'] = round(((float)$data['package_fee'] + (float)$data['freight']), 2);
                }
                // 真实的total cost
                $data['total_cost_price']['dropshop_or_homepick'] = $this->currency->formatCurrencyPrice($actual_price + $data['extra_fee']['dropshop_or_homepick'], $customerCurrency);
                $data['extra_fee_show']['dropshop_or_homepick']   = $this->currency->formatCurrencyPrice($data['extra_fee']['dropshop_or_homepick'], $customerCurrency);
                $data['package_fee_show']['dropshop_or_homepick'] = $this->currency->formatCurrencyPrice($data['package_fee'], $customerCurrency);
                if ($isCollectionFromDomicile) {
                    $data['freight_show']['dropshop_or_homepick'] = $this->currency->formatCurrencyPrice(0, $customerCurrency);
                } else {
                    $data['freight_show']['dropshop_or_homepick'] = $this->currency->formatCurrencyPrice($data['freight'], $customerCurrency);
                }

                //添加云送仓的价格数据
                if ($data['cwf'] == 1) {
                    //获取云送仓费用
                    $cwf_freight = $this->freight->getFreightAndPackageFeeByProducts(array($product_id));
                    $cwf_freight = $cwf_freight[$product_id];
                    if (($product_info['combo_flag']) && $product_info['combo_flag'] == 1) {    //combo
                        $extra_all_fee   = 0;
                        $package_all_fee = 0;
                        $freight_all_fee = 0;
                        $overweightSurcharge = 0;//超重附加费
                        $freight_rate    = 0;
                        $volume_all      = 0;
                        $weightTotal = 0;
                        $weightList = "";
                        $wthStr = "";
                        $tmpIndex = 1;
                        foreach ($cwf_freight as $tmp_k => $tmp_v) {
                            $extra_all_fee   += ($tmp_v['package_fee'] + $tmp_v['freight']) * $tmp_v['qty'];
                            $package_all_fee += $tmp_v['package_fee'] * $tmp_v['qty'];
                            $freight_all_fee += $tmp_v['freight'] * $tmp_v['qty'];
                            $freight_rate    = $tmp_v['freight_rate'];   //单价不用叠加
                            $volume_all      += $tmp_v['volume_inch'] * $tmp_v['qty'];
                            $overweightSurcharge += ($tmp_v['overweight_surcharge'] ?? 0);
                            $actualWeight = round($tmp_v['actual_weight'], 2);
                            $weightTotal += $actualWeight * $tmp_v['qty'];
                            $weightList .= sprintf($this->language->get('weight_detail_tip'),$tmpIndex,$actualWeight,$tmp_v['qty']);
                            $wthStr .= sprintf($this->language->get('volume_combo_detail_tip'),$tmpIndex,$tmp_v['length_inch'],$tmp_v['width_inch'],$tmp_v['height_inch'],$tmp_v['qty']);
                            $tmpIndex++;
                        }
                        $extra_all_fee += $overweightSurcharge;
                        $data['extra_fee']['cwf']            = round($extra_all_fee, 2);
                        $data['total_cost_price']['cwf']     = $this->currency->formatCurrencyPrice($actual_price + $data['extra_fee']['cwf'], $customerCurrency);
                        $data['extra_fee_show']['cwf']       = $this->currency->formatCurrencyPrice($data['extra_fee']['cwf'], $customerCurrency);
                        $data['package_fee_show']['cwf']     = $this->currency->formatCurrencyPrice($package_all_fee, $customerCurrency);
                        $data['freight_show']['cwf']         = $this->currency->formatCurrencyPrice($freight_all_fee, $customerCurrency);
                        $data['freight_rate']['cwf']         = $freight_rate;
                        $data['volume']['cwf']               = $volume_all;
                        $data['overweight_surcharge']['cwf'] = $overweightSurcharge;
                        $data['overweight_surcharge_show']['cwf'] = $this->currency->formatCurrencyPrice($overweightSurcharge, $customerCurrency);
                        $data['weight_list_str']['cwf'] = $weightList;
                        $data['weight_total']['cwf']  =  sprintf('%.2f', $weightTotal);
                        $data['wth_str']['cwf']  = $wthStr;
                    } else {
                        $data['extra_fee']['cwf']            = round(((float)$cwf_freight['package_fee'] + (float)$cwf_freight['freight'] + (float)$cwf_freight['overweight_surcharge']), 2);
                        $data['total_cost_price']['cwf']     = $this->currency->formatCurrencyPrice($actual_price + $data['extra_fee']['cwf'], $customerCurrency);
                        $data['extra_fee_show']['cwf']       = $this->currency->formatCurrencyPrice($data['extra_fee']['cwf'], $customerCurrency);
                        $data['package_fee_show']['cwf']     = $this->currency->formatCurrencyPrice($cwf_freight['package_fee'], $customerCurrency);
                        $data['freight_show']['cwf']         = $this->currency->formatCurrencyPrice($cwf_freight['freight'], $customerCurrency);
                        $data['freight_rate']['cwf']         = $cwf_freight['freight_rate'];
                        $data['volume']['cwf']               = $cwf_freight['volume_inch'];
                        $data['overweight_surcharge']['cwf'] = $cwf_freight['overweight_surcharge'];
                        $data['overweight_surcharge_show']['cwf'] = $this->currency->formatCurrencyPrice($cwf_freight['overweight_surcharge'], $customerCurrency);
                        $data['weight_total']['cwf'] = round($cwf_freight['actual_weight'], 2);
                        $data['wth_str']['cwf'] = sprintf($this->language->get('volume_detail_tip'),$cwf_freight['length_inch'],$cwf_freight['width_inch'],$cwf_freight['height_inch']);
                    }
                }
            }
            //1363 云送仓增加超重附加费
            $data['overweight_surcharge_rate']['cwf'] = (configDB('cwf_overweight_surcharge_rate') * 100) . '%';//超重附加费费率
            $data['overweight_surcharge_min_weight']['cwf'] = configDB('cwf_overweight_surcharge_min_weight');//超重附加费最低单位体积

            $link_url_list = [
                107  => 56,
                222  => 54,
                223  => 57,
                81   => 55
            ];
            if(isset($link_url_list[$countryId])){
                $data['help_center_url'] = url()->to(['information/information', 'information_id' => $link_url_list[$countryId]]);
            }else{
                $data['help_center_url'] = url()->to(['information/information', 'information_id' => 57]);
            }
            //一件代发 预计送达时间
            if ((customer()->isLogged() && !$isCollectionFromDomicile) || customer()->isPartner()) {
                $estimatedDeliveryTime = app(ProductRepository::class)->getEstimatedDeliveryTime($countryId, $is_oversize);
                if ($estimatedDeliveryTime) {
                    $data['estimated_ship_day_show'] = 1;
                    $data['estimated_ship_day_min'] = $estimatedDeliveryTime->ship_day_min;
                    $data['estimated_ship_day_max'] = $estimatedDeliveryTime->ship_day_max;
                }
            }
            //云送仓帮助说明
            if ($data['isSeller']) {
                $data['cwf_info_id'] = configDB('cwf_help_id');
            } else {
                $data['cwf_info_id'] = configDB('cwf_help_information_id');
            }
            //endregion




            //region 产品 Quantity
            if ($product_info['minimum']) {
                $data['minimum'] = $product_info['minimum'];
            } else {
                $data['minimum'] = 1;
            }
            //endregion




            //region 评论 隐藏
            $this->load->model('catalog/review');
            $data['tab_review']    = sprintf($this->language->get('tab_review'), $product_info['reviews']);
            $data['review_status'] = 0;//$data['review_status'] = configDB('config_review_status');//评论 隐藏
            $data['review_guest']  = false;//B2B页面改版，隐藏review输入框 //if (configDB('config_review_guest') || $this->customer->isLogged())
            if ($this->customer->isLogged()) {
                $data['customer_name'] = $this->customer->getFirstName() . '&nbsp;' . $this->customer->getLastName();
            } else {
                $data['customer_name'] = '';
            }
            $data['reviews'] = sprintf($this->language->get('text_reviews'), (int)$product_info['reviews']);
            $data['rating']  = (int)$product_info['rating'];
            // Captcha
            if($data['review_status']) {
                if (configDB('captcha_' . configDB('config_captcha') . '_status') && in_array('review', (array)configDB('config_captcha_page'))) {
                    $data['captcha'] = $this->load->controller('extension/captcha/' . configDB('config_captcha'));
                } else {
                    $data['captcha'] = '';
                }
            }
            //endregion




            $receipt_array = $this->model_catalog_product->getReceptionProduct();//获取预期入库的商品时间和数量
            $receipt_temp  = isset($receipt_array[$product_id]) ? $receipt_array[$product_id] : null;




            $data['tags'] = array();
            if ($product_info['tag']) {
                $tags = explode(',', $product_info['tag']);

                foreach ($tags as $tag) {
                    $data['tags'][] = array(
                        'tag' => trim($tag),
                        'href' => $this->url->link('product/search', 'tag=' . trim($tag))
                    );
                }
            }




            $this->model_catalog_product->updateViewed($product_id);




            //region 101594 促销业务，产品后面显示促销活动名称
            $campaignsMap = app(CampaignRepository::class)->getProductsCampaignsMap([$product_id]);
            $data['activity_promotion_list'] = app(CampaignService::class)->formatPromotionContentForCampaigns($campaignsMap[$product_id]);
            //endregion



            //region 产品的颜色材质等信息
            $productOption = app(ProductOptionRepository::class)->getProductOptionByProductId($product_id);
            $data['color_name'] = isset($productOption['color_name']) ? $productOption['color_name'] : '';
            $data['material_name'] = isset($productOption['material_name']) ? $productOption['material_name'] : '';
            //endregion
            //region 同款产品展示
            $product_option_list = [];
            if ($product_info['is_deleted'] != YesNoEnum::YES) {
                $product_option_list = app(ProductOptionRepository::class)->getProductOptionAssociateForBuyer($product_id);
            }
            $data['product_option_list'] = $product_option_list;
            //endregion




            //region 获取 product中的仓库
            $this->load->model('extension/module/product_show');
            /** @var ModelExtensionModuleProductShow $productShowModel */
            $productShowModel = $this->model_extension_module_product_show;
            if ((customer()->isLogged() && $product_info['unsee'] == 0) || customer()->isPartner()) {
                $warehouseDistribution = $productShowModel->getWarehouseDistributionByProductId($product_id);
                if($isCollectionFromDomicile) {
                    $data['warehouse_list'] = $warehouseDistribution;
                    //中源的产品分仓显示库存时，取CA2-ZY的库存数据显示在前台的CA2仓库中。中源的seller编号：W 501
                    if($product_info['customer_id']=='3222'){
                        $special=$productShowModel->getWarehouseDistributionSpecial($product_id,$product_info['sku']);
                        if($special && $special['stock_qty']>0){
                            foreach ($data['warehouse_list'] as $key=>$val){
                                if($val['warehouse_code']=='CA2'){
                                    $data['warehouse_list'][$key]['stock_qty']=$special['stock_qty'];
                                    break;
                                }
                            }
                        }
                    }
                }
                //出库时效
                if ($warehouseDistribution) {
                    $handlingTimeList = app(ProductRepository::class)->getHandlingTime($warehouseDistribution, $is_oversize, $isCollectionFromDomicile);
                    if ($handlingTimeList) {
                        $data['ship_min'] = $handlingTimeList['ship_min'];
                        $data['ship_max'] = $handlingTimeList['ship_max'];
                        $data['cloud_ship_min'] = $handlingTimeList['cloud_ship_min'];
                        $data['cloud_ship_max'] = $handlingTimeList['cloud_ship_max'];
                        $data['ship_remark'] = $handlingTimeList['ship_remark'];
                    }
                }
            }
            //endregion




            //region 页面 my_agreement 选择
            $this->load->model('extension/module/price');
            /** @var ModelExtensionModulePrice $priceModel */
            $priceModel = $this->model_extension_module_price;
            $extendArr = [
                'qty' => -1,
                'no_use_time_limit_qty' => 1,
            ];
            $transaction_info = $priceModel->getProductPriceInfo($product_id, $this->customer->getId(), [], $data['isSeller'], true, $extendArr);
            // 是否展示活动库存 & 其它库存 的弹窗
            $data['other_stock_qty'] = $transaction_info['base_info']['quantity'];  // 其它库存
            $data['discount_stock_qty'] = 0; // 活动库存
            $data['can_show_discount_stock_diaglog'] = 0; // 是否展示活动库存弹窗
            // $data['margin_max_qty'] = 0;
            if ($transaction_info && isset($transaction_info['base_info']['time_limit_qty'])) {
                $data['quantity'] = (int)$transaction_info['base_info']['quantity'] + (int)$transaction_info['base_info']['time_limit_qty'];
                $data['other_stock_qty'] = $transaction_info['base_info']['quantity'];
                $data['discount_stock_qty'] = $transaction_info['base_info']['time_limit_qty'];
                $data['can_show_discount_stock_diaglog'] = $transaction_info['base_info']['time_limit_starting'] ? 1 : 0;
                // $data['margin_max_qty'] = max($data['other_stock_qty'], $data['discount_stock_qty']);
                // 活动库存为0 时候，相当于没有折扣
                if ($data['discount_stock_qty'] <= 0) {
                    $data['xu_store_discount']['limit_discount'] = null;
                    $data['xu_store_discount']['current_selected'] = $data['xu_store_discount']['store_discount'] ?? null;
                    $data['big_client_discount'] = $data['xu_store_discount']['current_selected']['discount'] ?? 0;
                    $data['can_show_discount_stock_diaglog'] = 0;
                }
            }

            $data['transaction_info'] = $transaction_info;
            $data['count_transaction_type'] = isset($transaction_info['transaction_type']) ? count($transaction_info['transaction_type']) : 0;
            //$product_type 获取头款的协议
            if(ProductType::MARGIN_DEPOSIT == $product_type){
                $margin_agreement_code = $priceModel->getMarginAgreementId($product_id);
                $data['margin_agreement_code'] = $margin_agreement_code;
            }elseif (ProductType::FUTURE_MARGIN_DEPOSIT == $product_type){
                $this->load->model('futures/agreement');
                $data['futures_agreement_code'] = $this->model_futures_agreement->getFuturesCodeByAdvanceProductId($product_id);
            }
            //endregion




            //组装BID模块html
            $data['product_pending']=$product_pending=false;
            if ($data['isLogin']) {
                //region 返点部分HTML BID
                $this->load->model('account/customerpartner/rebates');
                //$rebate_array = $this->model_account_customerpartner_rebates->getRebatesTemplateDisplayForProductPage($product_id);
                $rebate_array=$this->model_account_customerpartner_rebates->get_rebates_template_display_batch($product_id);

                if (!empty($rebate_array)  && (!$data['isSeller'] || $is_seller_self)) {
                    $this->load->language('account/customerpartner/rebates');
                    //$can_bid_rebates = $this->model_account_customerpartner_rebates->checkRebatesProcessing($data['loginId'],$product_id);
                    //查询当前产品是否正在参与返点
                    $product_rebate_bid_count=$this->model_account_customerpartner_rebates->get_product_bid_count(array($product_id),$this->customer->getId());
                    $product_rebate_bid_count=array_combine(array_column($product_rebate_bid_count,'product_id'),array_column($product_rebate_bid_count,'num'));
                    // 生效且pendding
                    $product_rebate_pending=$this->model_account_customerpartner_rebates->get_product_pendding(array($product_id),$this->customer->getId());
                    $product_rebate_pending=array_combine(array_column($product_rebate_pending,'product_id'),$product_rebate_pending);
                    $can_bid_rebates=true;
                    $bid_err_tips='';

                    if(isset($product_rebate_pending[$product_id])){
                        $can_bid_rebates=false;
                        $data['product_pending']=$product_pending=true;   //产品rebate正在pending
                        $bid_err_tips=sprintf($this->language->get('rebate_bid_pending'),$product_rebate_pending[$product_id]['agreement_code']);
                    }

                    /**
                     * 如果 该商品对应的所有模板bid的数量都达到了限制名额数，则bid按钮禁用
                     */
                    $bid_limit = $this->model_account_customerpartner_rebates->checkAllTemplateCanBidByProduct($product_id);
                    if (!$bid_limit) {
                        $can_bid_rebates = false;
                        $bid_err_tips = $this->language->get('error_0_unused_num');
                    }

                    foreach ($rebate_array as $key => $item) {
                        $price_list = array();
                        foreach ($item['child'] as $kk => $vv) {
                            if ($vv['product_id'] == $product_id) {
                                //#31737 商品详情页返点针对免税价调整
                                $vv['price'] = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($item['seller_id'], customer()->getModel(), $vv['price']);
                                $price_list[] = $vv['price'] - $vv['rebate_amount'];
                            }
                        }
                        $price_min = min($price_list) < 0 ? 0 : min($price_list);
                        $price_max = max($price_list) < 0 ? 0 : max($price_list);
                        if ($price_min == $price_max) {
                            $rebate_price = $this->currency->format(round($price_min, $precision), $currency);
                        } else {
                            $rebate_price = $this->currency->format(round($price_min, $precision), $currency) . ' - ' . $this->currency->format(round($price_max, $precision), $currency);
                        }
                        $item['price_currency'] = $rebate_price;

                        $rebate_array[$key] = $item;
                    }

                    $rebate_info = [];
                    $rebate_info['can_bid_rebates'] = $can_bid_rebates;
                    $rebate_info['bid_err_tips']    = $bid_err_tips;
                    $rebate_info['url_information'] = url()->to(['information/information', 'information_id'=>(customer()->isPartner() ? configDB('rebates_information_id_seller') : configDB('rebates_information_id_buyer'))]);
                    $rebate_info['list']            = $rebate_array;
                    $data['rebate_info']            = $rebate_info;
                }
                //endregion




                //region margin 保证金 BID
                $marginContract = app(ContractRepository::class)->getContractByProductId($product_info['seller_id'], $product_id);
                $this->load->language('account/customerpartner/margin');
                $this->load->model('account/customerpartner/margin');
                $margin_list = $this->model_account_customerpartner_margin->getMarginTemplateForProduct($product_id);
                if ($margin_list && (!$data['isSeller'] || $is_seller_self)) {
                    foreach ($margin_list as $k => $item) {
                        //#31737 商品详情页现货针对免税价调整
                        $price = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($item['seller_id'], customer()->getModel(), $item['price']);
                        if ($item['min_num'] == $item['max_num']) {
                            $num = $item['min_num'];
                        } else {
                            $num = $item['min_num'] . ' - ' . $item['max_num'];
                        }
                        $item['price_currency'] = $this->currency->format(round($price, $precision), $currency);
                        $item['quantity_show']  = $num;

                        $margin_list[$k] = $item;
                    }
                    $data['margin_info'] = [
                        'payment_ratio'   => $margin_list[0]['payment_ratio'],
                        'url_information' => url()->to(['information/information', 'information_id' => configDB('margin_information_id')]),
                        'lists'           => $margin_list,
                        'is_bid' => ($marginContract) ? $marginContract->is_bid : 1,
                    ];
                }
                //endregion




                //region futures 期货保证金 BID
                $this->load->language('futures/template');
                $this->load->model('futures/contract');
                $data['contracts'] = $this->model_futures_contract->getContractsByProductId($product_id);
                $data['count_contracts'] =count($data['contracts']);
                foreach ($data['contracts'] as $key => $item) {
                    // 判断是否存在可以bid的合约
                    if ($item['is_bid'] == 1) {
                        $data['future_bid'] = 1;
                    }
                    $data['contracts'][$key]['margin_unit_price_show'] = $this->currency->formatCurrencyPrice($item['margin_unit_price'], $currency);
                    $data['contracts'][$key]['last_unit_price_show'] = $this->currency->formatCurrencyPrice($item['last_unit_price'], $currency);
                }
                //endregion


                //region 阶梯价格展示
                $data['quote_price_details'] = [];
                // 是否参与议价
                $this->load->model('customerpartner/bargain');
                /** @var ModelCustomerpartnerBargain $mcb */
                $mcb = $this->model_customerpartner_bargain;
                $product_quote_status = configDB('total_wk_pro_quote_status') && $mcb->checkProductsIsBargain($product_id);

                if (configDB('module_marketplace_status') && configDB('total_wk_pro_quote_status')) {
                    $this->load->model('account/wk_quotes_admin');
                    /** @var ModelAccountwkquotesadmin $quoteModel */
                    $quoteModel = $this->model_account_wk_quotes_admin;
                    // 议价部分
                    $data['quote_price_details'] = $quoteModel->getQuotePriceDetailsShow($product_id, $currency);
                }
                //endregion
                //region 议价部分HTML BID
                $data['show_bid_on_title'] = false;
                if ($product_quote_status && $data['unsee'] == 0) {
                    if (!$data['isSeller']) {
                        $data['show_bid_on_title'] = true;
                    }

                    //product_type非0，则不允许议价
                    if ($product_type != 0) {
                        $data['show_bid_on_title'] = false;
                    }
                }
                //endregion
            }




            //region 改价 涨价
            $product_will_change = $this->model_catalog_product->checkPriceWillChange($product_id, $this->customer->getId());
            $rawProduct = $product_info['rawPrice'];//产品原始价格
            // #31737 商品详情页针对免税价调整
            if ($product_info['product_type'] == ProductType::NORMAL) {
                $rawProduct = app(ProductPriceRepository::class)->getProductActualPriceByBuyer(intval($product_info['customer_id']), customer()->getModel(), $rawProduct);
            }
            //上门取货buyer 非精细化
            if(!$isCollectionFromDomicile ){
                $rawProduct = round($rawProduct,2);
            }
            if (isset($product_will_change) || !empty($product_will_change)) {
                date_default_timezone_set('America/Los_Angeles');
                foreach ($product_will_change as $change) {
                    $format_date = date('Y-m-d H:00:00', strtotime($change['effect_time']));
                    $change_price = $change['new_price'];
                    if ($discountResult) {
                        $change_price = $this->model_catalog_product->getDiscountPrice($change_price, $discountResult);
                        // #31737 商品详情页针对免税价调整
                        if ($customerCountryId && $this->customer->getGroupId() == 13) {
                            if ($product_info['product_type'] != ProductType::NORMAL) {
                                $change_price = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice(intval($product_info['customer_id']), $change_price, $customerCountryId);
                            } else {
                                [, $change_price,] = app(ProductPriceRepository::class)->getProductTaxExemptionPrice(intval($product_info['customer_id']), $change_price);
                            }
                        } else {
                            if ($product_info['product_type'] == ProductType::NORMAL) {
                                $change_price = app(ProductPriceRepository::class)->getProductActualPriceByBuyer(intval($product_info['customer_id']), customer()->getModel(), $change_price);
                            }
                        }
                    }
                    if($change['type'] == 'sp' && !$isCollectionFromDomicile){
                        $change_price = round($change_price,2);
                    }
                    // type = sp 显示不出来
                    if ($change_price > doubleval($actual_price) && strtotime($format_date) > time() && (!$be_delicacy || ($be_delicacy && $change['type'] == 'dm'))) {
                        if (!isset($min_time) || $min_time <= $format_date) {
                            $new_price = $change_price;
                            $min_time = $format_date;
                            $data['priceChangeDate'] = '@'.$format_date;
                            $data['prichChangeRemainSeconds'] = strtotime($change['effect_time']) - time();
                        }
                    }
                }

                if (isset($new_price)) {
                    $priceChange = doubleval($new_price) - doubleval($actual_price);//涨价的金额
                    $priceChangeCurrency = $this->currency->formatCurrencyPrice($priceChange, $currency);
                    $data['priceChangeText']     = sprintf($this->language->get('text_price_change'), $priceChangeCurrency);
                    $data['priceChangeAfterCurrency'] = $this->currency->formatCurrencyPrice($new_price, $currency);//改价后的金额
                }
            }
            $data['laTimezoneNum'] = abs(timezone_offset_get(new DateTimeZone(date_default_timezone_get()), new DateTime()) / 3600);


            if ($rawProduct && $actual_price < $rawProduct && $this->customer->getGroupId() != 13) {
                $data['rawPrice'] = $this->currency->formatCurrencyPrice($rawProduct, $currency);
            }
            if ($rawProduct && $this->customer->getGroupId() != 13) {
                $this->load->model('customerpartner/DelicacyManagement');
                $data['is_rebate'] =  $this->model_customerpartner_DelicacyManagement->checkProductIsRebate($product_id,$this->customer->getId());
            }
            //endregion




            //region add by lilei 添加Parameter (Shipment Time展示)
            $moduleShipmentTimeStatus = configDB('module_shipment_time_status');
            if ($moduleShipmentTimeStatus) {
                // 获取countryId对应的shipment time
                $this->load->model('extension/module/shipment_time');
                $shipmentTimePage = $this->model_extension_module_shipment_time->getShipmentTime($countryId);
                $data['module_shipment_time_status'] = $moduleShipmentTimeStatus;
                $data['shipmentTimePage'] = SummernoteHtmlEncodeHelper::decode($shipmentTimePage->page_description);
            } else {
                $data['module_shipment_time_status'] = 0;
            }
            //endregion




            //region 判断是否需要展示预计到货时间和数量 More on the way
            $verify = [
                'arrival_available' => 0,
                'arrival_qty_show'  => 0
            ];
            if($receipt_temp){
                $verify = $productShowModel->verifyCheck($product_info);
            }
            $data['receipt_html'] = $this->receiptHtml($verify, $receipt_temp, $product_info['seller_accounting_type']);//More on the way
            //endregion




            //region 查看该产品是否被订阅 edit by xxl
            $productWishList = $this->model_catalog_product->getWishListProduct($product_id, $this->customer->getId());
            $data['productWishList'] = $productWishList;
            //endregion




            //region 促销费用标签页
            $market_description = $this->model_catalog_product->getMarketPromotionDescription($product_id);
            if(isset($market_description) && !empty($market_description)){
                //测试需要换行，那就给她换行，反正是大爷
                // ps 附议
                foreach ($market_description as $key => $description){
                    $market_description[$key]['description'] = preg_replace('/\r\n/','</br>',$description['description']);
                }
                $data['market_promotion'] = $market_description;
            }
            //endregion




            //region Seller基本信息 相关产品 copy from catalog\controller\extension\module\marketplace.php
            if ($product_info['customer_id'] && $product_info['seller_status']) {
                $partner = $this->model_customerpartner_master->getProfile($product_info['customer_id']);
                if ($partner) {
                    if (configDB('marketplace_product_name_display')) {
                        if (configDB('marketplace_product_name_display') == 'sn') {
                            $data['displayName'] = $partner['firstname'] . " " . $partner['lastname'];
                        } else if (configDB('marketplace_product_name_display') == 'cn') {
                            $data['displayName'] = $partner['screenname'];
                        } else {
                            $data['displayName'] = $partner['screenname'] . " (" . $partner['firstname'] . " " . $partner['lastname'] . ")";
                        }
                    }
                    if (configDB('marketplace_product_image_display')) {
                        $partner['companylogo'] = $partner[configDB('marketplace_product_image_display')];
                    }
                    if ($partner['companylogo'] && StorageCloud::image()->fileExists($partner['companylogo'])) {
                        // 旧图片路径也存在于 catalog/xxx 等目录之下，已确认全部迁移，直接取OSS图片
                        $partner['thumb'] = StorageCloud::image()->getUrl($partner['companylogo'], ['w' => 100, 'h' => 100]);
                    } else if (configDB('marketplace_default_image_name')) {
                        $partner['thumb'] = $this->model_tool_image->resize(configDB('marketplace_default_image_name'), 100, 100);
                    } else {
                        $partner['thumb'] = $this->model_tool_image->resize('no_image.png', 100, 100);
                    }
                    $partner['sellerHref']     = url()->to(['customerpartner/profile', 'id' => $product_info['customer_id']]);

                    $this->load->model('customerpartner/store_rate');
                    $store_return_rate_mark              = $this->model_customerpartner_store_rate->returnsMarkByRate($partner['returns_rate']);//店铺退返率标签
                    $store_response_rate_mark            = $this->model_customerpartner_store_rate->responseMarkByRate($partner['response_rate']);//店铺回复率标签
                    $return_approval_rate                = $this->model_catalog_product->returnApprovalRate($product_info['customer_id']);
                    $partner['store_return_rate_mark']   = $store_return_rate_mark;
                    $partner['store_response_rate_mark'] = $store_response_rate_mark;
                    $partner['return_approval_rate']     = $return_approval_rate;
                    $data['partner']                     = $partner;

                    //店铺评分--start
                    //充值流程说明页
                    if (ENV_DROPSHIP_YZCM == 'dev_35') {
                        $information_id = 131;
                    } elseif (ENV_DROPSHIP_YZCM == 'dev_17') {
                        $information_id = 130;
                    } elseif (ENV_DROPSHIP_YZCM == 'pro') {
                        $information_id = 133;
                    } else {
                        $information_id = 133;
                    }
                    if ($data['isLogin']) {
                        $data['is_out_new_seller'] = app(SellerRepository::class)->isOutNewSeller($product_info['customer_id'], 3);
                        $this->load->model('customerpartner/seller_center/index');
                        $task_info = $this->model_customerpartner_seller_center_index->getSellerNowScoreTaskNumberEffective($product_info['customer_id']);
                        $data['new_seller_score'] = false;
                        $data['comprehensive'] = ['seller_show' => 0];

                        //无评分 且 在3个月内是外部新seller
                        if (!isset($task_info['performance_score']) && $data['is_out_new_seller']) {
                            $data['new_seller_score'] = true;
                            $data['comprehensive'] = ['seller_show' => 1];
                        } else {
                            if ($task_info) {
                                $data['comprehensive'] = [
                                    'seller_show' => 1,
                                    'total' => isset($task_info['performance_score']) ? number_format(round($task_info['performance_score'], 2), 2) : '0',
                                    'url' => url()->to(['information/information', 'information_id' => $information_id]),
                                ];
                            }
                        }
                    }
                    //店铺评分--end
                }
                //Related Products copy from catalog\controller\extension\module\marketplace.php
                $data['latest'] = array();
                if (configDB('marketplace_product_show_seller_product')) { // marketplace_product_show_seller_product value=1
                    $filter_array = array(
                        'start'         => 0,
                        'limit'         => 4,
                        'customer_id'   => $product_info['customer_id'],
                        'filter_status' => 1,
                        'filter_store'  => intval(configDB('config_store_id')),
                        'min_quantity'  => 1,
                        'filter_product_ids_not' => [$product_id],
                    );
                    $this->load->model('account/customerpartner');
                    $data['latest'] = $this->model_account_customerpartner->getProductsSeller($filter_array);
                }
            }
            //endregion

            // 是否可以发国际单
            $europeFreightRepo = app(EuropeFreightRepository::class);
            $internaltion = $europeFreightRepo->getInternationalConfig($this->customer->getCountryId());
            $data['can_internaltion'] = $internaltion ? true : false;

            // 校验是否是giga onsite
            $data['is_giga_onsite'] = app(ProductService::class)->checkIsGigaOnsiteProduct($product_id);

            //region Seller编辑产品后预览产品，取审核记录中的信息进行替换
            if ($this->customer->isPartner() && $auditId > 0) {
                $productInfoNow = [
                    "product_id" => $product_info['sku'],
                    "sku" => $product_info['sku'],
                    "combo_flag" => $product_info['combo_flag'],
                    "length" => $product_info['length'],
                    "width" => $product_info['width'],
                    "height" => $product_info['height'],
                    "weight" => $product_info['weight'],
                    "length_cm" => $product_info['length_cm'],
                    "width_cm" => $product_info['width_cm'],
                    "height_cm" => $product_info['height_cm'],
                    "weight_kg" => $product_info['weight_kg'],
                ];
                $dataAudit = $this->getProductFromAudit($auditId, $product_id, $productInfoNow);
                $data = array_merge($data, $dataAudit);
            }
            //endregion
            $data['symbolLeft'] = $this->currency->getSymbolLeft($currency);
            $data['symbolRight'] = $this->currency->getSymbolRight($currency);
            $data['app_version'] = APP_VERSION;
            $data['volume_lower'] = CLOUD_LOGISTICS_VOLUME_LOWER;
            $data['header'] = $this->load->controller('common/header');
            $data['footer'] = $this->load->controller('common/footer');
            $this->response->setOutput($this->load->view('product/product', $data));
        } else {
            //region 产品不存在
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_error'),
                'href' => url()->to(['product/product', 'product_id' => $product_id]),
            );


            $data['continue'] = url()->to(['common/home']);

            $this->document->setTitle($this->language->get('text_error'));
            $this->response->setStatusCode(404);


            $data['heading_title'] = 'Product Not Found';
            $data['header'] = $this->load->controller('common/header');
            $data['footer'] = $this->load->controller('common/footer');
            $this->response->setOutput($this->load->view('error/not_found', $data));
            //endregion
        }
    }

    public function checkProductInCart()
    {
        $product_id = $this->request->post('product_id');
        $delivery_type = $this->request->post('delivery_type');
        $cart = $this->orm->table('oc_cart')
            ->where('product_id', $product_id)
            ->where('delivery_type', $delivery_type)
            ->where('customer_id', $this->customer->getId())
            ->count();
        return $this->response->success(['cart'=>$cart]);
    }

    /**
     * 产品评论展示
     * @throws Exception
     */
    public function review()
    {
        $this->load->language('product/product');

        $this->load->model('catalog/review');

        $product_id = (int)$this->request->get('product_id', 0);
        $page = (int)$this->request->get('page', 1);
        $page_limit = (int)$this->request->get('page_limit', 5);

        $data['reviews'] = array();

        $review_total = $this->model_catalog_review->getTotalReviewsByProductId($product_id);

        $results = $this->model_catalog_review->getReviewsByProductId($product_id, ($page - 1) * $page_limit, $page_limit);
        foreach ($results as $result) {
            //add  by xxli
            $this->load->model('customerpartner/product_review');
            $review_files = $this->model_customerpartner_product_review->getReviewFiles($result['review_id']);
            $data['files'] = array();
            if ($review_files) {
                foreach ($review_files as $file) {
                    $data['files'][] = "storage/reviewFiles/" . $file['path'];
                }
            } else {
                $data['files'] = '';
            }
            //end
            $data['reviews'][] = array(
                'author' => $result['author'],
                'text' => nl2br($result['text']),
                'seller_review' => nl2br($result['seller_review']),
                'rating' => (int)$result['rating'],
                'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
                'files' => $data['files']
            );
        }

        $pagination = new Pagination();
        $pagination->total = $review_total;
        $pagination->page = $page;
        $pagination->limit = $page_limit;
        $pagination->renderScript = false;
        $pagination->url = $this->url->link('product/product/review', 'product_id=' . $product_id . '&page={page}');

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf($this->language->get('text_pagination'), ($review_total) ? (($page - 1) * 5) + 1 : 0, ((($page - 1) * 5) > ($review_total - 5)) ? $review_total : ((($page - 1) * 5) + 5), $review_total, ceil($review_total / 5));

        $this->response->setOutput($this->load->view('product/review', $data));
    }

    /**
     * 产品评论保存
     * @throws Exception
     */
    public function write()
    {
        $this->load->language('product/product');

        $json = array();

        $product_id = (int)$this->request->get('product_id', 0);

        if ($this->request->serverBag->get('REQUEST_METHOD') == 'POST') {
            if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 25)) {
                $json['error'] = $this->language->get('error_name');
            }

            if ((utf8_strlen($this->request->post['text']) < 25) || (utf8_strlen($this->request->post['text']) > 1000)) {
                $json['error'] = $this->language->get('error_text');
            }

            if (empty($this->request->post['rating']) || $this->request->post['rating'] < 0 || $this->request->post['rating'] > 5) {
                $json['error'] = $this->language->get('error_rating');
            }

            // Captcha
            if (configDB('captcha_' . configDB('config_captcha') . '_status') && in_array('review', (array)configDB('config_captcha_page'))) {
                $captcha = $this->load->controller('extension/captcha/' . configDB('config_captcha') . '/validate');

                if ($captcha) {
                    $json['error'] = $captcha;
                }
            }

            if (!isset($json['error'])) {
                $this->load->model('catalog/review');

                $this->model_catalog_review->addReview($product_id, $this->request->post);

                $json['success'] = $this->language->get('text_success');
            }
        }

        $this->response->headers->set('Content-Type', 'application/json');
        $this->response->setOutput(json_encode($json));
    }

    private $downloadUseStream = false; // 为 true 时使用 Stream 模式，false 时使用从 OSS 下载到本地打包模式

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \League\Flysystem\FilesystemException
     * @throws Exception
     */
    public function downloadZip()
    {
        $productId = intval($this->request->get('product_id', 0));
        if (!$productId) {
            return $this->jsonFailed();
        }
        try {
            return app(PackageRepository::class)->download($productId, customer()->getModel());
        } catch (\Throwable $e) {
            return $this->jsonFailed();
        }
    }

    /**
     * @return JsonResponse
     * @throws FilesystemException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function download()
    {
        $productId = intval($this->request->post('product_id', 0));
        $customerId = intval($this->request->post('customer_id', 0));
        if ($customerId < 1 || $productId < 1) {
            return $this->jsonFailed();
        }
        $storage = StorageCloud::root();
        $product = ProductDescription::query()->select(['packed_zip_path','packed_time'])->where('product_id', $productId)->first();
        $auditUpdateTime = ProductAudit::query()->where('product_id', $productId)->value('update_time');
        if ($product->packed_zip_path && $storage->fileExists($product->packed_zip_path)) {
            return $this->jsonSuccess();
        }
        // 如果zip不存在或者打包时间小于产品审核时间就打包
        if ((!$storage->fileExists($product->packed_zip_path) || $auditUpdateTime < $product->packed_time) && empty($this->cache->get('packing_product_zip_' . $productId))) {
            try {
                $response = app(ProductService::class)->packedZip($customerId, $productId);
                $result = $response->toArray();
                if (!$result['code'] == 200) {
                    Logger::packZip('打包请求失败:' . $result['message'], 'error');
                    return $this->jsonFailed();
                }
                $this->cache->set('packing_product_zip_' . $productId, 1, 24 * 3600);
            } catch (Exception $e) {
                Logger::packZip('打包请求失败:' . $e->getMessage(), 'error');
                return $this->jsonFailed();
            }
        }
        return $this->jsonFailed('Packing', [], 300);
    }

    /**
     * @throws FilesystemException
     * @throws FileNotFoundException
     * @throws FileNotReadableException
     * @throws \ZipStream\Exception\OverflowException
     * @throws Exception
     */
        public function download2()
    {
        set_time_limit(0);
        $runTime = time();
        $product_id  = intval($this->request->get('product_id', 0));
        $customer_id = intval($this->request->get('customer_id', 0));
        if ($product_id < 1 || $customer_id < 1) {
            exit();
        }

        $this->load->model('catalog/product');
        $this->load->language('product/product');
        /** @var ModelCatalogProduct $mca */
        $mca = $this->model_catalog_product;
        $product_info = $mca->getProduct($product_id, $customer_id);
        $is_oversize = $mca->checkIsOversizeItem($product_id);
        //记录素材包下载次数
        if ($product_info['customer_id'] == $customer_id){
            $mca->packageDownloadHistory($product_id);
        }
        $itemCode = $product_info['self_support'] == 1 ? $product_info['sku'] : $product_info['mpn'];
        $sellerName = $product_info['screenname'];
        //2020.07.03 单个下载产品加入收藏
        $this->load->model('account/wishlist');
        $this->model_account_wishlist->setProductsToWishGroup($product_id);

        // zip文件下载之后的名称
        $downname = str_replace(
            [' ', ',','/','\\'],
            '_',
            $sellerName . '_' . $itemCode . '_' . date('Ymd', $runTime) . '.zip'
        );

        if ($this->downloadUseStream) {
            $options = new Archive();
            $options->setSendHttpHeaders(true);
            $zip = new ZipStream($downname, $options);
        } else {
            if (!is_dir(DIR_STORAGE_PRODUCT_PACKAGE)){
                mkdir(DIR_STORAGE_PRODUCT_PACKAGE, 0777, true);
            }
            $zipFileName = DIR_STORAGE_PRODUCT_PACKAGE . $downname;
            if (file_exists($zipFileName)) {
                @unlink($zipFileName);
            }
            $zip = new ZipArchive();
            if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                exit('open die');
            }
        }
        $images = array_map(function ($item) {
            return $item['image'];
        }, $mca->getProductImages($product_id));
        $product_info['image'] && array_push($images, $product_info['image']);
        $description_html        = SummernoteHtmlEncodeHelper::decode($product_info['description']);
        $specification_html      = $mca->specificationForDownload($product_info);
        $styleReturn = '<style> .return-policy, .warranty-policy {border: 1px solid #dbdbdb;}.return-policy .policy-title, .warranty-policy .policy-title {border-bottom: 1px solid #dbdbdb;padding: 14px 22px;}.tab-content .text-max {font-size: 22px;}.text-bold {font-weight: bold;}.text-larger {font-size: 16px;}.ml-1 {margin-left: 10px;}.return-policy .policy-content {padding: 0 22px 30px 22px;}.text-bule {color: #0041bc;}.mt-3 {margin-top: 30px;}h4, .h4 {font-size: 18px;}h4, .h4, h5, .h5, h6, .h6 {margin-top: 10px;margin-bottom: 10px;}h1, h2, h3, h4, h5, h6, .h1, .h2, .h3, .h4, .h5, .h6 {font-family: inherit;font-weight: 500;line-height: 1.1;color: inherit;}.policy-content ul, .shipping-content ol {padding-left: 15px;}ul, ol {margin-top: 0;margin-bottom: 10px;}.policy-content ul > li {word-break: break-all;}.text-warning {color: #ff6600;}.mt-5 {margin-top: 50px;}.mt-2 {margin-top: 20px;}.warranty-policy .policy-content {padding: 30px 22px;}.tab-content .text-danger {color: #e64545;}.text-danger {color: #e64545;}</style>';
        $return_warranty_text_html = $styleReturn . SummernoteHtmlEncodeHelper::decode($product_info['return_warranty_text']);
        //调用方法，对要打包的根目录进行操作，并将ZipArchive的对象传递给方法
        $this->addFileToZip(
            DIR_PRODUCT_PACKAGE . "/" . $customer_id . "/" . $product_id . "/image/",
            $zip, "image", $description_html, $images, $runTime, $customer_id);
        $this->addFileToZip(
            DIR_PRODUCT_PACKAGE . "/" . $customer_id . "/" . $product_id . "/file/", $zip,
            "file", $description_html, $images, $runTime, $customer_id, 'description');
        $this->addFileToZip(
            DIR_PRODUCT_PACKAGE . "/" . $customer_id . "/" . $product_id . "/file/", $zip,
            "file", $specification_html, $images, $runTime, $customer_id, 'specification');
        $this->addFileToZip(
            DIR_PRODUCT_PACKAGE . "/" . $customer_id . "/" . $product_id . "/file/", $zip,
            "file", $return_warranty_text_html, $images, $runTime, $customer_id, 'returns & warranty');

        // 支持发往国际的商品 需要下载对应的 国际单补运费信息
        $europeFreightRepo = app(EuropeFreightRepository::class);
        $internaltion = $europeFreightRepo->getInternationalConfig($this->customer->getCountryId());
        if ($internaltion) {
            $internationFulfillmentFeeHtml = $this->internationalFulfillmentFee($product_id);
            $this->addFileToZip(
                DIR_PRODUCT_PACKAGE . "/" . $customer_id . "/" . $product_id . "/file/", $zip,
                "file", $internationFulfillmentFeeHtml, $images, $runTime, $customer_id, 'international fulfillment fee');
        }


        //seller上传的素材包
        foreach (['image', 'file', 'video'] as $value) {
            $packagesArr = $mca->getProductPackages($product_id, $value);
            if ($packagesArr) {
                $this->addFileToZipFromDB($zip, $packagesArr, $value);
            }
        }

        if ($is_oversize) {
            $this->addFileToZip(DIR_PRODUCT_PACKAGE . "/oversizeNotice/", $zip);
        }
        // shipmentTime add by lilei
        $moduleShipmentTimeStatus = configDB('module_shipment_time_status');
        if ($moduleShipmentTimeStatus) {
            // 获取当前国家
            $countryCode = $this->session->get('country', 'USA');
            // 获取国家ID
            // 获取countryId
            $countryId = CountryHelper::getCountryByCode($countryCode);
            // 获取countryId对应的shipment time
            $this->load->model('extension/module/shipment_time');
            $shipmentTimePage = $this->model_extension_module_shipment_time->getShipmentTime($countryId);
            // 获取文件路径
            if ($shipmentTimePage->file_path) {
                $shipmentFilePath = StorageCloud::shipmentFile()->getLocalTempPath($shipmentTimePage->file_path);
                $shipmentFileRename = $shipmentTimePage->file_name;
                $zip->addFile($shipmentFileRename, $shipmentFilePath);
                StorageCloud::shipmentFile()->deleteLocalTempFile($shipmentFilePath);
            }
        }
        // end
        if ($this->downloadUseStream) {
            $zip->finish();
        } else {
            $zip->close(); //关闭处理的zip文件
            // 下载文件
            header("Location: " . HTTPS_SERVER . 'storage/product_package/' . $downname);
            exit();
        }
    }

    /**
     * 获取欧洲补运费展示信息
     * @return JsonResponse
     */
    public function getEuropeFreight()
    {
        $this->load->language('product/product');
        $productId = $this->request->post('product_id', '');
        if (! $productId) {
            return $this->jsonFailed($this->language->get('text_error'));
        }

        $europeFreightRepo = app(EuropeFreightRepository::class);
        $freightAll = $europeFreightRepo->getAllCountryFreight($productId);

        foreach ($freightAll as &$value) {
            $freight = $value['freight'] < 0 ? 0 : ceil($value['freight']);
            $value['freight'] = $this->currency->formatCurrencyPrice($freight, $this->session->get('currency'));
        }
        $data['list'] = $freightAll;

        return $this->jsonSuccess($data);
    }

    /**
     * @throws Exception
     */
    function initPriceChangeComponent()
    {
        $this->response->headers->set('Content-Type', 'application/json');
        $this->load->model('catalog/product');
        /** @var ModelCatalogProduct $modelCatalogProduct */
        $modelCatalogProduct = $this->model_catalog_product;
        $customFields = $this->customer->getId();
        $product_id = $this->request->get('product_id', 0);
        $price_change = $modelCatalogProduct->productPriceChangeTime($product_id);
        // 精细化管理
        $productDelicacy = $this->getDelicacyManageDetail($product_id, (int)$this->customer->getId());
        if ($productDelicacy) {
            if ($productDelicacy['is_update'] == 1) {
                $this->response->setOutput(json_encode([]));
                return;
            } else {
                $price_change = [
                    'new_price' => $productDelicacy['price'],
                    'effect_time' => strtotime($productDelicacy['effective_time'])
                ];
            }
        }

        $price_effect_timestamp = $price_change['effect_time'];

        $data = array();
        $server_timestamp = $_SERVER['REQUEST_TIME'];
        if (isset($price_effect_timestamp) && $price_effect_timestamp) {
            $countdown_second = $price_effect_timestamp - $server_timestamp;
            if ($countdown_second < 0) {
                $countdown_second = 0;
            }
            $data['price_change_second'] = $countdown_second;

            $discount = $this->model_catalog_product->getDiscountByProductId($customFields, $product_id);
            if ($discount) {
                $data['new_price'] = $this->model_catalog_product->getDiscountPrice($price_change['new_price'], $discount);
            } else {
                $data['new_price'] = $price_change['new_price'];
            }
            $data['new_price'] = $this->currency->formatCurrencyPrice($data['new_price'], $this->session->data['currency']);
        }

        $this->response->setOutput(json_encode($data));
    }


    /**
     * 获取商品是否对某个buyer显示
     * 先决条件为用户必须登录 且为 buyer
     * @param int $productId 商品id
     * @param int $buyerId 购买用户id
     * @return bool
     */
    private function checkProductCanDisplay(int $productId, int $buyerId): bool
    {
        $dmg_exist = $this->orm->table('oc_delicacy_management_group as dmg')
            ->join('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->join('oc_customerpartner_product_group_link as pgl', 'pgl.product_group_id', '=', 'dmg.product_group_id')
            ->where([
                ['bgl.buyer_id', '=', $buyerId],
                ['pgl.product_id', '=', $productId],
                ['dmg.status', '=', 1],
                ['pgl.status', '=', 1],
                ['bgl.status', '=', 1]
            ])
            ->exists();
        if ($dmg_exist) {
            return false;
        }

        $dm_exist = $this->orm->table('oc_delicacy_management')
            ->where([
                'buyer_id' => $buyerId,
                'product_id' => $productId,
                'product_display' => 0,
            ])
            ->exists();

        return $dm_exist ? false : true;
    }


    /**
     * 获取精细化价格详情
     *
     * @param int $productId
     * @param int $buyerId
     * @return array|null  null表示该商品没有参与精细化管理
     */
    private function getDelicacyManageDetail(int $productId, int $buyerId): ?array
    {
        $res = $this->orm
            ->table('oc_delicacy_management')
            ->where([
                'buyer_id' => $buyerId,
                'product_id' => $productId,
                'product_display' => 1
            ])
            ->first();
        if(!$res){
            return null;
        }else{
            return $res->toArray();
        }
    }


    /**
     * @param string $path
     * @param ZipArchive|ZipStream $zip
     * @param null $dir
     * @param null $html
     * @param null $imagesArray
     * @param null $run_id
     * @param null $customer_id
     * @param string $file_name
     * @throws FilesystemException
     * @throws FileNotFoundException
     * @throws FileNotReadableException
     */
    private function addFileToZip(
        $path,
        $zip,
        $dir = null,
        $html = null,
        $imagesArray = null,
        $run_id = null,
        $customer_id = null,
        $file_name = 'description'
    )
    {
        /**
         * 如果 HTML为null, 代表有素材包, 则直接取素材包。
         * 如果 HTML不为null，则代表没有素材包；如果 路径为 image，则获取主图，如果路径为file，则生产description文件
         */
        if (is_null($html)) {
            if (file_exists($path)) {
                $handler = opendir($path); //打开当前文件夹由$path指定。
                while (($filename = readdir($handler)) !== false) {
                    if ($filename != "." && $filename != "..") {//文件夹文件名字为'.'和‘..’，不要对他们进行操作
                        if (is_dir($path . "/" . $filename)) {// 如果读取的某个对象是文件夹，则递归
                            $this->addFileToZip($path . "/" . $filename, $zip);
                        } else { //将文件加入zip对象
                            $this->addLocalFileToZip($zip, $dir . $filename, $path . '/' . $filename, false);
                        }
                    }
                }
                @closedir($path);
            }
        } else {
            if ($dir == 'image') {
                foreach (array_unique($imagesArray) as $image) {
                    if (!empty($image)) {
                        $this->addCloudFileToZip($zip, $dir . '/' . basename($image), $image);
                    }
                }
            } else if ($dir == 'file') {
                if (trim($html)) {
                    $customer_id = $customer_id ?: 0;
                    $htmlPath =
                        DIR_STORAGE_PRODUCT_PACKAGE .
                        md5('product_'.$file_name.'_' . $customer_id . '_' . $run_id) . '.html';
                    !is_file($htmlPath) && touch($htmlPath);
                    $fh = fopen($htmlPath, "w");
                    fwrite($fh, $html);
                    fclose($fh);
                    $this->addLocalFileToZip($zip, $dir . "/" . $file_name .'.html', $htmlPath, false);
                }
            }
        }
    }


    /**
     * 添加素材包
     *
     * 注：此方法没有关闭 ZipArchive 资源链接
     *
     * @param ZipArchive $zip
     * @param array $filesArr 将要添加进zip的文件数组
     * @param string $type image/file/video
     * @throws FilesystemException
     */
    private function addFileToZipFromDB($zip, $filesArr, $type = 'image')
    {
        if (!in_array($type, ['image', 'file', 'video'])) {
            return;
        }
        $added_file_arr = [];   // 已添加到压缩包的文件路径
        $file_name_key = $type . '_name';
        $origin_file_name_key = 'origin_' . $type . '_name';
        foreach ($filesArr as $item) {
            $relativePath = $item->{$type};
            // 路径匹配
            $prefix = '';
            if (preg_match('/^(\d+)\/(\d+)\/(file|image|video)\/(.*)/', $relativePath)) {
                // 兼容原素材包路径
                $prefix = 'productPackage/';
            }
            $filePath = $prefix . $relativePath;
            $addingFileName = $type . '/' . ($item->{$origin_file_name_key} ?: $item->{$file_name_key});
            // 如果已存在，则使用 重命名后的文件名
            if (in_array($addingFileName, $added_file_arr)) {
                $addingFileName = $type . '/' . token(5) . ($item->{$file_name_key});
            }
            $added_file_arr[] = $addingFileName;

            $this->addCloudFileToZip($zip, $addingFileName, $filePath);
        }
    }

    /**
     * 添加远程文件到zip
     * @param ZipArchive|ZipStream $zip
     * @param string $zipFileName
     * @param string $path
     * @throws FilesystemException
     */
    protected function addCloudFileToZip($zip, $zipFileName, $path)
    {
        if (!StorageCloud::image()->fileExists($path)) {
            Logger::imageCloud(['zip cloud not exist', $path], 'warning');
            return;
        }
        if ($this->downloadUseStream) {
            /** @var ZipStream $zip */
            $stream = StorageCloud::image()->readStream($path);
            $zip->addFileFromStream($zipFileName, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } else {
            /** @var ZipArchive $zip */
            $path = StorageCloud::image()->getLocalTempPath($path);
            $zip->addFile($path, $zipFileName);
        }
    }

    /**
     * 添加本地文件到zip
     * @param ZipArchive|ZipStream $zip
     * @param string $zipFileName
     * @param string $path
     * @param bool $checkExist
     * @throws FileNotFoundException
     * @throws FileNotReadableException
     */
    protected function addLocalFileToZip($zip, $zipFileName, $path, $checkExist = true)
    {
        if ($checkExist && !file_exists($path)) {
            Logger::imageCloud(['zip local not exist', $path], 'warning');
            return;
        }
        if ($this->downloadUseStream) {
            /** @var ZipStream $zip */
            $zip->addFileFromPath($zipFileName, $path);
        } else {
            /** @var ZipArchive $zip */
            $zip->addFile($path, $zipFileName);
        }
    }

    /**
     * 展示返点议价窗口
     * @throws Exception
     */
    public function showRebatesBidModal()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', url()->to(['account/customer_order']));
            $this->response->redirect(url()->to(['account/login']));
        }
        $data = array();
        $this->load->language('account/customerpartner/rebates');
        $data['language']=$this->load->language('account/customerpartner/rebates');
        $this->load->model('account/customerpartner/rebates');
        $product_id = intval(get_value_or_default($this->request->get, 'product_id', 0));
        $soldQty    = intval(get_value_or_default($this->request->get, 'soldQty', 0));
        $data['plan_id']    = intval(get_value_or_default($this->request->get, 'plan_id', 0));
        $country_id = $this->customer->getCountryId();
        $data['soldQty'] = intval($soldQty * 0.8);

        //获取产品信息
        $data['product_info']=$this->model_account_customerpartner_rebates->get_product_info_by_id($product_id);

        $session=$this->session->data;
        $symbol = $this->currency->getSymbolLeft($session['currency']);
        if (empty($symbol)) {
            $symbol = $this->currency->getSymbolRight($session['currency']);
        }
        if (empty($symbol)) {
            $symbol = '$';
        }
        $data['symbol']=$symbol;
        $decimal_place=$this->currency->getDecimalPlace($session['currency']);
        $data['decimal_place']=$decimal_place;

        //协议
        $this->load->model('catalog/information');
        $information_info = $this->model_catalog_information->getInformation(configDB('rebates_buyer'));

        if (!empty($information_info)) {
            $data['clause_url'] = url()->to(['information/information', 'information_id'=>$information_info['information_id']]);
            $data['clause_title'] = $information_info['title'];
        }

        if (isset($product_id)) {
            $precision = 2;
            if (CountryEnum::JAPAN == $country_id){
                $precision = 0;
            }
            $template_data = $this->model_account_customerpartner_rebates->get_rebates_template_display_batch($product_id);
            $template_data=array_combine(array_column($template_data,'id'),$template_data);
            $template_sort_data=$this->model_account_customerpartner_rebates->get_rebates_template_sort($product_id);
            //获取product 被bid 的次数
            $plan_bid_product_list=array_column($template_data,'child');
            $plan_bid_product_list_new=array_reduce($plan_bid_product_list,'array_merge',array());
            $plan_bid_product_list=$plan_bid_product_list_new;
            unset($plan_bid_product_list_new);
            $plan_product_list=array_unique(array_column($plan_bid_product_list,'product_id'));
            // bid 的num
            $product_rebate_bid_count=$this->model_account_customerpartner_rebates->get_product_bid_count($plan_product_list,$this->customer->getId());
            $product_rebate_bid_count=array_combine(array_column($product_rebate_bid_count,'product_id'),array_column($product_rebate_bid_count,'num'));

            // place limit
            $used_nums = $this->model_account_customerpartner_rebates->listAgreementUsedNumber(array_column($template_data, 'id'));

            // 生效且pendding
            $product_rebate_pending=$this->model_account_customerpartner_rebates->get_product_pendding($plan_product_list,$this->customer->getId());
            $product_rebate_pending=array_combine(array_column($product_rebate_pending,'product_id'),$product_rebate_pending);
            $sort_template_data=array();
            $this->load->model('customerpartner/DelicacyManagement');
            $delicacy_model=$this->model_customerpartner_DelicacyManagement;
            foreach ($template_sort_data as $k=>$v){
                if(!isset($template_data[$v])){
                    continue;
                }
                $template_data[$v]['quantity']=0;
                $template_data[$v]['all_need_num']=0;
                foreach ($template_data[$v]['child'] as $kk=>&$vv){
                    //place bid  精细化-产品隐藏
                    if(!$delicacy_model->checkIsDisplay($vv['product_id'],$this->customer->getId())){
                        unset($template_data[$v]['child'][$kk]);
                        continue;
                    }
                    $bid_num    = isset($product_rebate_bid_count[$vv['product_id']])?$product_rebate_bid_count[$vv['product_id']]:0;
                    $vv['image']=$this->check_pic($vv['image']);
                    $vv['product_url']= url()->to(['product/product', 'product_id' => $vv['product_id']]);
                    $vv['bid_num']=$bid_num;
                    $vv['is_pending']=isset($product_rebate_pending[$vv['product_id']])?$product_rebate_pending[$vv['product_id']]['agreement_code']:'';
                    if(isset($product_rebate_pending[$vv['product_id']])){
                        //不处理
                    }else{
                        $template_data[$v]['quantity'] += $vv['quantity'];
                        $template_data[$v]['all_need_num']+=$vv['need_num'];
                    }

//                    $vv['product_need']= ($vv['quantity']-$vv['need_num'])>0?$vv['quantity']-$vv['need_num']:0;
                }
                $bid_num_total=($template_data[$v]['quantity']-$template_data[$v]['all_need_num']);
                $template_data[$v]['bid_num_total']=($bid_num_total>0)?$bid_num_total:0;
                $min_bid=floor( ($template_data[$v]['quantity']-$template_data[$v]['all_need_num'])*0.8);
                $template_data[$v]['min_bid']=($min_bid>=0)?$min_bid:0;
//                $template_data[$v]['bid_num_total']=array_sum(array_column($template_data[$v]['child'],'product_need'));
//                $template_data[$v]['min_bid']=floor( array_sum(array_column($template_data[$v]['child'],'product_need'))*0.8);
                $sort_template_data[$v]=$template_data[$v];
            }
            $template_data=$sort_template_data;
            unset($sort_template_data);
            if (!empty($template_data)) {
                foreach ($template_data as $key => &$item) {
                    $item['is_limit'] = false;
                    if ($item['limit_num'] >= 0) {
                        $item['is_limit'] = true;
                        $used_num = $used_nums[$item['id']] ?? 0;
                        $item['unused_num'] = $item['limit_num'] - $used_num > 0 ? $item['limit_num'] - $used_num : 0;
                    }
                    foreach ($item['child'] as $childk=>$childv){
						//#31737 商品详情页返点针对免税价调整
                        $childv['price'] = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($item['seller_id'], customer()->getModel(), $childv['price']);
                        $template_data[$key]['child'][$childk]['price']=$this->currency->formatCurrencyPrice($childv['price'],session('currency'),'',false);
                        $template_data[$key]['child'][$childk]['rebate_amount']=$this->currency->formatCurrencyPrice($childv['rebate_amount'],session('currency'),'',false);
                        $template_data[$key]['child'][$childk]['min_sell_price']=$this->currency->formatCurrencyPrice($childv['min_sell_price'],session('currency'),'',false);
                        $template_data[$key]['child'][$childk]['after_price']=$this->currency->formatCurrencyPrice($childv['price']-$childv['rebate_amount'],session('currency'),'',false);
                    }
                    //排序
                    array_multisort(array_column($item['child'],'after_price'),SORT_DESC,$item['child']);
                }
                $data['rebates_templates'] = $template_data;
            }
        }
        $this->response->setOutput($this->load->view('product/rebates_bid_modal', $data));
    }

    /**
     * check   pic 是否存在----带图片缩放
     * @param string $path
     * @param int $width
     * @param int $height
     * @return string
     * @throws Exception
     */
    public function check_pic($path,$width=400,$height=400){
        //图片缩放
        $this->load->model('tool/image');
        if ($path) {
            $image = $this->model_tool_image->resize($path, 30, 30);
        } else {
            $image = $this->model_tool_image->resize('placeholder.png', 30, 30);
        }
        return $image;
    }

    /**
     * 产品库存惜售的提示
     * @param int $productId
     * @return string
     * @throws Exception
     */
    private function stockTipHtml(int $productId): string
    {
        /** @var ModelCommonProduct $modelCommonProduct */
        $modelCommonProduct = load()->model('common/product');
        // 在库库存-锁定库存
        $availableQty = $modelCommonProduct->getProductAvailableQuantity($productId);
        // 可售库存
        $onShelfQty = $modelCommonProduct->getProductOnShelfQuantity($productId);
        if ($onShelfQty === 0) {
            // 可售为0时不展示
            return '';
        }
        // 剩余库存=在库库存-锁定库存-可售卖库存
        $leftQty = $availableQty - $onShelfQty;
        if ($leftQty <= 10) {
            // 剩余库存数量小于等于10，则展示“库存全部开放”
            $tip = 'Total Stock is Available';
        } else {
            $percent = $leftQty / $onShelfQty; // 剩余/可售
            $level = 'Low';
            if ($percent > 1) {
                $level = 'High';
            } elseif ($percent >= 0.5) {
                $level = 'Medium';
            }
            $tip = "Surplus Stock Level: <strong>{$level}</strong>" ;
        }
        return '<span style="margin-left: 10px; color: #333; font-size: 13px">' . $tip . '</span>';
    }

    /**
     * 产品即将到货 More on the way
     * @param array $verify
     * @param array $receipt
     * @param int|string $seller_accounting_type
     * @return string
     */
    private function receiptHtml($verify, $receipt, $seller_accounting_type)
    {
        $html = '';
        if ($verify['arrival_available'] && $receipt['expect_date']) {
            $html .= '
<div style="position: relative; top:-4px">
    <span class="label-more-on-the-way" style="color: #294fc4; font-style: italic; font-size: 13px; margin-left: 15px">More on the way</span>
    <div style="position: absolute;
        border: 1px solid #183464;
        background: #fff;
        border-radius: 4px;
        width: 220px;
        text-align: left;
        padding: 10px;
        margin-left: 20px;
        margin-top: 3px;
        z-index: 9;
        display: none;
        box-shadow: 0 3px 9px rgba(0, 0, 0, 0.42);">';
            //102372 外部Seller的产品不显示预计入库时间
            if ($seller_accounting_type != 2) {
                $html .= '
        <span style="font-size: 12px;line-height: 20px;margin-bottom: 10px;">Date of next arrival:&nbsp;' . $receipt['expect_date'] . '</span>
        <br/>';
            }
            $html.='
        <span style="font-size: 12px;line-height: 20px;margin-bottom: 10px;">Estimated QTY of next arrival:&nbsp;' . $receipt['expect_qty'] . '</span>
    </div>
</div>';
        }

        return $html;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function showMarginBidModal()
    {
        $this->load->language('account/customerpartner/margin');
        $this->load->model('account/customerpartner/margin');
        $product_id = (int)$this->request->get('product_id');
        $sold_qty = $this->request->get('sold_qty');
        $flag = $this->request->get('flag');
        $index = max(intval($this->request->get('index')), 0);
        $data['sold_qty'] = $sold_qty;
        $country_id = $this->customer->getCountryId();
        $currency = $this->session->get('currency');//USD
        $data['currency'] = $currency;
        $sku = '';
        $payment_ratio = '';
        $update_time = '';

        if (isset($product_id)) {
            $precision = 2;
            if (CountryEnum::JAPAN == $country_id) {
                $format = '%d';
                $precision = 0;
            } else {
                $format = '%.2f';
            }

            $template_data = $this->model_account_customerpartner_margin->getMarginTemplateForProduct($product_id);
            $product_base_price = $this->model_account_customerpartner_margin->getProductBasePrice($product_id);

            if (!empty($template_data)) {
                foreach ($template_data as $key => $item) {
                    //#31737 现货quick view针对免税价调整
                    $item['price'] = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($item['seller_id'], customer()->getModel(), $item['price']);
                    $template_data[$key]['show_price'] = $this->currency->format($item['price'], $currency);
                    $update_time = ($item['update_time'] > $update_time) ? $item['update_time'] : $update_time;
                    $sku = $item['sku'];
                    $deposit_per = round($item['price'] * $item['payment_ratio'] / 100, $precision);//头款单价,产品定金单价
                    $tail_price = round($item['price'] - $deposit_per, $precision);//现货保证金模板尾款单价

                    //现货保证金模板定金金额
                    $min = sprintf($format, $item['min_num'] * $deposit_per);
                    $max = $margin_template_front_money = sprintf($format, $item['max_num'] * $deposit_per);
                    if ($item['max_num'] == $item['min_num']) {
                        $template_data[$key]['margin_template_front_money'] = $min;
                        $template_data[$key]['show_margin_template_front_money'] = $this->currency->format($min, $currency);
                        $template_data[$key]['show_margin_template_front_money_toThousands'] = number_format($min, $this->currency->getDecimalPlace($currency));
                    } else {
                        $template_data[$key]['margin_template_front_money'] = $min . ' - ' . $max;
                        $template_data[$key]['show_margin_template_front_money'] = $this->currency->format($min, $currency) . ' - ' . $this->currency->format($max, $currency);
                        $template_data[$key]['show_margin_template_front_money_toThousands'] = number_format($min, $this->currency->getDecimalPlace($currency)) . ' - ' . number_format($max, $this->currency->getDecimalPlace($currency));
                    }

                    //现货保证金模板尾款单价
                    $template_data[$key]['margin_template_tail_price'] = $margin_template_tail_price = sprintf($format, $tail_price);

                    //现货保证金模板协议金额
                    $min = sprintf($format, $item['min_num'] * $item['price']);
                    $max = $margin_template_agreement_money = sprintf($format, $item['max_num'] * $item['price']);
                    if ($max == $min) {
                        $template_data[$key]['margin_template_agreement_money'] = $min;
                    } else {
                        $template_data[$key]['margin_template_agreement_money'] = $min . ' - ' . $max;
                    }


                    $template_data[$key]['price'] = sprintf($format, $item['price']);
                    $payment_ratio = $template_data[$key]['payment_ratio'] = sprintf('%.2f', $item['payment_ratio']) . '%';

                    if ($item['is_default']) {
                        $default_template = [
                            'num' => $item['max_num'],
                            'price' => sprintf($format, $item['price']),
                            'day' => $item['day'],
                            'payment_ratio' => $template_data[$key]['payment_ratio'],
                            'margin_template_front_money' => $margin_template_front_money,
                            'margin_template_tail_price' => $margin_template_tail_price,
                            'margin_template_agreement_money' => $margin_template_agreement_money,
                            'bond_template_id' => $item['bond_template_id'],
                            'show_price' => $this->currency->format($item['price'], $currency),
                            'show_margin_template_front_money' => $this->currency->format($margin_template_front_money, $currency),
                            'show_margin_template_front_money_toThousands' => number_format($margin_template_front_money, $this->currency->getDecimalPlace($currency)),
                        ];
                        $data['default_template'] = $default_template;
                    } elseif (!isset($data['default_template'])) {

                        $default_template = [
                            'num' => '',
                            'price' => '',
                            'day' => '',
                            'payment_ratio' => $template_data[$key]['payment_ratio'],
                            'margin_template_front_money' => '',
                            'margin_template_tail_price' => '',
                            'margin_template_agreement_money' => '',
                            'bond_template_id' => $item['bond_template_id'],
                            'show_price' => '',
                            'show_margin_template_front_money' => '',
                            'show_margin_template_front_money_toThousands' => '',
                        ];
                        $data['default_template'] = $default_template;
                    }
                }
                $data['margin_templates'] = $template_data;
            }
        }

        $symbol = $this->currency->getSymbolLeft($currency);
        if (empty($symbol)) {
            $symbol = $this->currency->getSymbolRight($currency);
        }
        if (empty($symbol)) {
            $symbol = '$';
        }
        $decimal_place = $this->currency->getDecimalPlace($currency);
        $data['symbol'] = $symbol;
        $data['decimal_place'] = $decimal_place;

        $this->load->model('catalog/information');
        $information_info = $this->model_catalog_information->getInformation(configDB('margin_information_id'));

        if (isset($information_info)) {
            $data['clause_url'] = url()->to(['information/information', 'information_id' => $information_info['information_id']]);
            $data['clause_title'] = $information_info['title'];
        }

        $data['product_id'] = $product_id;
        $data['update_time'] = $update_time;
        $data['sku'] = $sku;
        $data['payment_ratio'] = $payment_ratio;
        $data['base_price'] = $product_base_price;
        $data['config_min_num'] = 5;

        //替换label的货币符号
        $data['margin_price'] = sprintf($this->language->get("margin_price"), $symbol);
        $data['margin_bid_ex_price'] = sprintf($this->language->get("margin_bid_ex_price"), $symbol);

        $data['country_id'] = $country_id;
        $data['isJapan'] = customer()->isJapan();
        $data['index'] = $index;//表示第几个合约的下标值 0 start
        $data['save_action'] = $this->url->link('account/product_quotes/margin_agreement/addAgreement');
        $data['quick_view_save_action'] = $this->url->link('account/product_quotes/margin_agreement/addAutoMarginAgreement');
        $data['decimal_place'] = customer()->isJapan() ? 0 : 2;
        $data['currency_symbol_left'] = $this->currency->getSymbolLeft(session('currency'));
        $data['currency_symbol_right'] = $this->currency->getSymbolRight(session('currency'));
        $data['is_japan'] = customer()->isJapan();
        [$discountInfo, $otherStock, $discountQty] = $this->getDiscountInfo($product_id, ProductTransactionType::MARGIN);
        if ($flag) {
            $data['discount_info'] = $discountInfo;
            // 没有活动 或者只有全店活动时候  应该返回的是其它库存     有限时或者限时+全店时候 应该返回其它库存和限时库存的最大值
            if ($data['discount_info']['discount_type'] == 0 || $data['discount_info']['discount_type'] == 1) {
                $data['sold_qty'] = $otherStock;
            } else {
                $data['sold_qty'] = max($otherStock, $discountQty); // 原来的最大qty = avaialbe qty，现在：max(其它库存，活动库存)
            }

            return $this->render('product/margin_quick_view_modal', $data);
        }
        $data['sold_qty'] = $otherStock;
        return $this->render('product/margin_bid_modal', $data);
    }

    /**
     * @throws Exception
     */
    public function showFuturesBidModal()
    {
        $product_id = $this->request->get('product_id');
        if (!$product_id) {
            return;
        }
        $this->load->model('futures/agreement');
        $country_id = $this->customer->getCountryId();
        $currency = $this->session->get('currency');
        $this->load->model('futures/contract');
        $this->load->language('futures/template');
        $is_bid = $this->request->get('quick') ? 0 : 1;
        $data['contracts'] = $this->model_futures_contract->getContractsByProductId($product_id,$is_bid);
        $data['contracts_num'] = count($data['contracts']);
        $data['sku'] = $this->orm->table('oc_product')->where('product_id', $product_id)->value('sku');
        if (customer()->isJapan()) {
            $format = '%d';
            $precision = 0;
        } else {
            $format = '%.2f';
            $precision = 2;
        }

        // 可使用的现货存款百分比
        $isJapan = $this->customer->isJapan();
        $depositPercentagesStr = $this->config->get('future_margin_deposit_percentages') ?: '';
        $depositPercentages = explode(',', $depositPercentagesStr);
        $depositPercentages =  array_map(function ($item) use ($isJapan) {
            return $isJapan ? floor($item) : sprintf("%.2f", round($item, 2));
        }, $depositPercentages);

        foreach ($data['contracts'] as &$item) {
            $item['margin_unit_price_show'] = $this->currency->formatCurrencyPrice($item['margin_unit_price'], $currency);
            $item['last_unit_price_show'] = $this->currency->formatCurrencyPrice($item['last_unit_price'], $currency);
            $item['remain_num'] = $this->model_futures_agreement->getContractRemainQty($item['id']);
            $item['delivery_type_show'] = ModelFuturesContract::DELIVERY_TYPES[$item['delivery_type']];
            $item['payment_ratio_show'] = sprintf($format, round($item['payment_ratio'],$precision));
            if ($item['delivery_type'] == 3) {
                $item['price'] = $item['last_unit_price'];
                $item['delivery_type_tip']=$this->language->get('tip_futures_delivery_1');
                if ($item['margin_unit_price'] < $item['last_unit_price']) {
                    $item['price'] = $item['margin_unit_price'];
                    $item['delivery_type_tip']=$this->language->get('tip_futures_delivery_2');
                }
            } elseif ($item['delivery_type'] == 1) {
                $item['price'] = $item['last_unit_price'];
                $item['delivery_type_tip']=$this->language->get('tip_futures_delivery_1');
            } else {
                $item['price'] = $item['margin_unit_price'];
                $item['delivery_type_tip']=$this->language->get('tip_futures_delivery_2');
            }
            // $item['old_price_show'] = $this->currency->formatCurrencyPrice($item['price'], $currency);
            // $item['old_margin_unit_price'] = $this->currency->formatCurrencyPrice($item['margin_unit_price'], $currency);
            // $item['old_last_unit_price'] = $this->currency->formatCurrencyPrice($item['last_unit_price'], $currency);
            // if ($data['big_client_discount'] > 0 && isset($this->request->get['quick'])) {
            //     $item['price'] = MoneyHelper::upperAmount(bcmul($item['price'], $data['big_client_discount'] / 100, 3), customer()->isJapan() ? 0 : 2);
            //     $item['margin_unit_price'] = MoneyHelper::upperAmount(bcmul($item['margin_unit_price'], $data['big_client_discount'] / 100, 3), customer()->isJapan() ? 0 : 2);
            //     $item['last_unit_price'] = MoneyHelper::upperAmount(bcmul($item['last_unit_price'], $data['big_client_discount'] / 100, 3), customer()->isJapan() ? 0 : 2);
            //     $item['margin_unit_price_show'] = $this->currency->formatCurrencyPrice($item['margin_unit_price'], $currency);
            //     $item['last_unit_price_show'] = $this->currency->formatCurrencyPrice($item['last_unit_price'], $currency);
            // }
            $item['price_show'] = $this->currency->formatCurrencyPrice($item['price'], $currency);
            $item['per_deposit'] = sprintf($format, round($item['price'] * $item['payment_ratio'] / 100, $precision));
            $item['deposit'] = sprintf($format, round($item['per_deposit'] * $item['min_num'], $precision));
            $item['deposit_show'] = $this->currency->formatCurrencyPrice($item['deposit'], $currency);
            $item['is_special_payment_ratio'] = !in_array($item['payment_ratio_show'], $depositPercentages);
        }
        $data['is_futures'] = count($data['contracts']) ?: null;
        $data['country_id'] = $country_id;
        $data['contract_id'] = $this->request->get('contract_id');
        $data['symbolLeft'] = $this->currency->getSymbolLeft($this->session->get('currency'));
        $data['symbolRight'] = $this->currency->getSymbolRight($this->session->get('currency'));
        $data['currency'] = $this->session->get('currency');
        $data['deposit_percentages'] = join(',', $depositPercentages);
        $data['delivery_type'] = $this->customer->isCollectionFromDomicile() ? 1 : 0;

        if ($this->request->get('quick')) {
            // 店铺活动
            [$discountInfo, $otherStock, $discountQty] = $this->getDiscountInfo($product_id, ProductTransactionType::FUTURE);
            $data['discount_info'] = $discountInfo;
            return $this->response->setOutput($this->load->view('product/futures_quick_view', $data));
        }

        return $this->response->setOutput($this->load->view('product/futures_bid_modal', $data));
    }

    /**
     * 获取折扣信息
     * @param int $productId
     * @param int $transactionType
     * @return array
     */
    private function getDiscountInfo(int $productId, int $transactionType)
    {
        $sellerId = CustomerPartnerToProduct::query()->where('product_id', $productId)->value('customer_id');
        $xuDiscountInfo = app(MarketingTimeLimitDiscountService::class)->calculateCurrentDiscountInfo(customer()->getId(), $sellerId, $productId);
        $this->load->model('extension/module/price');
        /** @var ModelExtensionModulePrice $priceModel */
        $priceModel = $this->model_extension_module_price;
        $extendArr = ['qty' => -1, 'show_discount' => 1, 'no_use_time_limit_qty' => 1];
        $transactionInfo = $priceModel->getProductPriceInfo($productId, $this->customer->getId(), [], false, true, $extendArr);
        // 说明有限时活动（开始 + 未开始） 那么就能拿到其它库存和活动库存
        // 其它库存 = 上架库存（C） - 活动锁定（当前开始的+未开始的）（A+B）
        if ($transactionInfo && isset($transactionInfo['base_info']['time_limit_qty'])) {
            $otherStock = $transactionInfo['base_info']['quantity']; // 其它库存
            $discountQty = $transactionInfo['base_info']['time_limit_qty']; // 活动库存
            if ($discountQty <= 0) {
                $xuDiscountInfo['limit_discount'] = null;
                $xuDiscountInfo['current_selected'] = $xuDiscountInfo['store_discount'] ?? null;
            }
        } else {
            // 说明 没有正在进行或没生效的活动
            $otherStock = $discountQty = $transactionInfo['base_info']['quantity'];
        }
        $discountInfo = app(MarketingTimeLimitDiscountRepository::class)->handleMarginFutureDiscountInfo($xuDiscountInfo, $transactionType, $otherStock, $discountQty);

        return [$discountInfo, $otherStock, $discountQty];
    }

    /**
     * 阶梯价
     * @throws Exception
     */
    public function showQuoteBidModal()
    {
        $product_id = $this->request->query->getInt('product_id');

        $currency = $this->session->get('currency');
        $customFields = $this->customer->getId();
        $precision = $this->currency->getDecimalPlace($currency);//货币小数位数

        $this->load->language('product/product');
        $this->language->load('account/product_quotes/wk_product_quotes');

        $data['text_tooltip']=$this->language->get('text_tooltip');
        $data['text_add_quote']=$this->language->get('text_add_quote');
        $data['text_close']=$this->language->get('text_close');
        $data['text_price']=$this->language->get('text_price');
        $data['text_quanity']=$this->language->get('text_quanity');
        $data['text_ask']=$this->language->get('text_request_message');
        $data['text_send']=$this->language->get('text_send');
        $data['text_error_mail']=$this->language->get('text_error_mail');
        $data['text_success_mail']=$this->language->get('text_success_mail');
        $data['text_error_option']=$this->language->get('text_error_option');
        $data['login'] = $this->url->link('account/login', '', true);
        $data['customer_id'] = $this->customer->getId();

        $data['quantity_placeholder'] = 'Min '.$this->config->get('wk_pro_quote_quantity');
        $data['action'] = $this->url->link('account/product_quotes/wk_product_quotes/add', '', true);

        $data['is_japan'] = false;
        if (!empty($this->customer->getCountryId()) && $this->customer->getCountryId() == JAPAN_COUNTRY_ID) {
            $data['is_japan'] = true;
        }

        $data['isCollectionFromDomicile'] = !$this->customer->isCollectionFromDomicile();

        $data['quote_price_details'] = [];
        if ($this->config->get('module_marketplace_status') && $this->config->get('total_wk_pro_quote_status')) {
            $this->load->model('account/wk_quotes_admin');
            /** @var ModelAccountwkquotesadmin $quoteModel */
            $quoteModel = $this->model_account_wk_quotes_admin;
            // 议价部分
            $data['quote_price_details'] = $quoteModel->getQuotePriceDetailsShow($product_id, $currency);
        }

        //商品可用库存
        $this->load->model('catalog/product');
        $product_info = $this->model_catalog_product->getProductByDetails($product_id, $customFields,0);
        $data['quantity'] = $product_info['c2pQty'];

        //重置quantitiy
        $data['isSeller'] = $this->customer->isPartner();
        $this->load->model('extension/module/price');
        /** @var ModelExtensionModulePrice $priceModel */
        $priceModel = $this->model_extension_module_price;
        $extendArr = [
            'qty' => -1,
            'no_use_time_limit_qty' => 1,
        ];
        $transaction_info = $priceModel->getProductPriceInfo($product_id, $this->customer->getId(), [], $data['isSeller'], true, $extendArr);
        // 是否展示活动库存 & 其它库存 的弹窗
        $data['quantity'] = $transaction_info['base_info']['quantity'];  // 其它库存


        $this->response->setOutput($this->load->view('product/quote_bid_modal', $data));
    }


    /**
     * rebate bid
     * 2020年1月30日
     * add by zjg
     * 返点4期  buyer rebate bid
     * @throws Exception
     */
    public function rebate_bid(){
        $this->load->language('account/customerpartner/rebates');
        $data=$this->request->post;
        //数据校验
        $error_info=array();
        if(!isset($data['tpl_id'])||!is_numeric($data['tpl_id'])||!$data['tpl_id']){
            $error_info[]=$this->language->get('db_error');
        }
        if(!isset($data['day'])||!is_numeric($data['day'])||!$data['day']){
            $error_info[]=$this->language->get('error_rebates_bid_day');
        }
        if(!isset($data['qty'])||!is_numeric($data['qty'])||!$data['qty']){
            $error_info[]=$this->language->get('error_rebates_bid_day');
        }
           $data['remark'] = array_key_exists('remark', $data) ? ltrim($data['remark']) : '';
           if(mb_strlen($data['remark'], 'utf-8') < 1 || mb_strlen($data['remark'], 'utf-8') > 2000){
           $error_info[]=$this->language->get('error_remark');
           }
        //bid 的数量
        foreach ($data['child'] as $K=>$v){
            if(!isset($v['product_id'])){
                $error_info[]=$this->language->get('db_error');
                continue;
            }
            if(!isset($v['rebate_amount'])||$v['rebate_amount']<=0){
                $error_info[]=$this->language->get('error_rabate_value_rate');
            }
            if(!isset($v['min_sell_price'])||$v['min_sell_price']<0){
                $error_info[]=$this->language->get('error_rebates_bid_price_limit');
            }
        }

        $this->load->model('account/customerpartner/rebates');

        /**
         * 校验 是否还有限制名额（place limit）
         * 1. 如果 limit_num < 0, 不做任何限制
         * 2. 如果 limit_num 为 null/0, 均不可以bid
         * 3. 如果 limit_num - used_num <=0 ,也不可以bid
         */
        $limit_num = $this->model_account_customerpartner_rebates->getLimitNumber($data['tpl_id']);
        if ($limit_num == null || $limit_num == 0) {
            $this->response->returnJson(array(
                'status' => 0,
                'data' => [$this->language->get('error_0_unused_num')]
            ));
        } elseif ($limit_num > 0) {
            $used_num = $this->model_account_customerpartner_rebates->getAgreementUsedNumber($data['tpl_id']);
            if ($limit_num - $used_num <= 0) {
                $this->response->returnJson(array(
                    'status' => 0,
                    'data' => [$this->language->get('error_0_unused_num')]
                ));
            }
        }

        //校验是否存在 生效且在pending 的产品
        $product_rebate_pending=$this->model_account_customerpartner_rebates->get_product_pendding($bid_product_list,$this->customer->getId());
        foreach($product_rebate_pending as $k =>$v){
            $error_info[]='product id:'.$v['product_id'].' is pending:';
        }
        if($error_info){    //数据非法，不能提交
            $this->response->returnJson(array(
                'status'=>0,
                'data'=>$error_info
            ));
        }
        //精细化前打开modal  ，设置精细化，然后bid   ----bid错误
        $change_flag=0;
        $this->load->model('customerpartner/DelicacyManagement');
        $delicacy_model=$this->model_customerpartner_DelicacyManagement;
        foreach ($data['child'] as $k=>$v){
            if(!$delicacy_model->checkIsDisplay($v['product_id'],$this->customer->getId())){
                $change_flag=1;   //modal 打开后 ，修改了精细化可见
                break;
            }
        }
        if($change_flag){
            $this->response->returnJson(array(
                'status'=>0,
                'data'=>[$this->language->get('page_error')]
            ));
        }
        //业务逻辑
        $this->load->model('account/customerpartner/rebates');
        $bid_res = $this->model_account_customerpartner_rebates->save_rebate_bid($this->customer->getId(),$data);
        if($bid_res){   //bid 成功  发送站内信
            $name= $this->customer->getNickName();
            $agreement_info=$this->model_account_customerpartner_rebates->get_agreement_info($bid_res);
            $agreement_id=$agreement_info['agreement_code'];
            $item1_show=array();
            foreach ($agreement_info['child'] as $k=>$v){
                $tmp='<a href="'.$this->url->link('product/product', 'product_id=' . $v['product_id']).'" target="_blank">';
                $tmp.=$v['sku'];
                $tmp.='</a>';
                if($v['mpn']){
                    $tmp.='('.$v['mpn'].')';
                }
                $item1_show[]=$tmp;
            }
            $item1_show=implode(',',$item1_show);
            $subject = 'Rebate-New bid request: '.$name.' has submitted a rebate bid request ：#'.$agreement_id;
            $message = '<table   border="0" cellspacing="0" cellpadding="0">';
            $message .= '<tr><th align="left">Agreement ID:&nbsp;</th><td style="width: 650px">
                          <a href="/index.php?route=account/product_quotes/rebates_contract/rebatesAgreementList&agreement_id='.$agreement_info['id'].'" target="_blank">' . $agreement_id . '</a>
                          </td></tr> ';
            $message .= '<tr><th align="left">Buyer:&nbsp;</th><td style="width: 650px"> '.$name.' </td></tr>';
            $message .= '<tr><th align="left">Products:&nbsp;</th><td style="width: 650px">'.$item1_show.'</td></tr>';
            $message .= '<tr><th align="left">Days:&nbsp;</th><td style="width: 650px">' .$data['day']. '</td></tr>';
            $message .= '<tr><th align="left">Min. Total Selling Quantity:&nbsp;</th><td style="width: 650px">' . $data['qty'] . '</td></tr>';
            $message .= '<tr><td align="left">It will be expired in 24H. Please deal with it in time</td></tr></table>';
            $this->load->model('message/message');
            $this->model_message_message->addSystemMessageToBuyer('bid_rebates',$subject,$message,$agreement_info['seller_id']);
        }
        $this->response->returnJson(array(
            'status' => $bid_res ? 1 : 0,
            'data' => [$bid_res],
        ));

    }

    /**
     * 下载素材包-生成简单的子属性素材页面
     *
     * @param int $productId 商品ID
     * @return string
     */
    private function internationalFulfillmentFee($productId)
    {
        $europeFreightRepo = app(EuropeFreightRepository::class);
        $freightAll = $europeFreightRepo->getAllCountryFreight($productId);

        $subHtml = '';
        if ($freightAll) {
            $europeFreightRepo = app(EuropeFreightRepository::class);
            $freightAll = $europeFreightRepo->getAllCountryFreight($productId);

            foreach ($freightAll as $value) {
                $freight = $value['freight'] < 0 ? 0 : ceil($value['freight']);
                $freightFormat = $this->currency->formatCurrencyPrice($freight, $this->session->get('currency'));

                $subHtml .= sprintf($this->language->get('text_download_fulfillment_fee_html_body'), $value['country_en'], $value['country_code'], $freightFormat);
            }
        } else {
            $subHtml = sprintf($this->language->get('text_download_fulfillment_fee_html_body_no_data'), $this->language->get('text_product_not_international_error'));
        }

        $html = sprintf($this->language->get('text_download_fulfillment_fee_html_head'), $this->language->get('text_country'), $this->language->get('text_country_code'), $this->language->get('text_fulfillment_fee'), $subHtml);

        return $html;
    }


    /**
     * 近30天浏览量
     *
     * @param  int  $product_id
     * @return bool
     */
    private function  lastThirtyDaysVisit($product_id = 0)
    {
        if ($product_id > 0){
           $key =  Product::lastThirtyDaysVisitKey();
           $this->java_redis_cache->hIncrBy($key,$product_id,1);
        }
        return true ;
    }

    /**
     * 近3天商品
     *
     * @param  int  $customer_id
     * @param  int  $product_id
     * @return bool
     */
    private function  lastThirdDaysVisitedCustomerProduct($customer_id = 0 ,$product_id = 0)
    {
        if ($customer_id && $product_id){
            $customer_key =  Product::lastThirdDaysVisitedCustomerIdKey($customer_id);
            $exist_set = $this->java_redis_cache->exists($customer_key);
            $this->java_redis_cache->sadd($customer_key,$product_id);
            if (!$exist_set){
                //自初次创建起，4天后过期；
                $this->java_redis_cache->expire($customer_key, 86400*4 - 1);
            }

        }
        return true ;
    }

    /**
     * Seller编辑产品后，进行[预览产品]。该方法返回审核记录中的信息，用于替换产品详情页中的内容。
     * @param int $auditId
     * @param int $productId
     * @return array
     * @throws Exception
     */
    private function getProductFromAudit($auditId, $productId, $productInfoNow)
    {
        $url = App::url();
        $language = load()->language('product/product');
        /** @var ModelToolImage $modelToolImage */
        $modelToolImage = load()->model('tool/image');
        $currency = session('currency', 'USD');


        $auditInfo = app(ProductAuditRepository::class)->getSellerProductAuditInfo($auditId, $productId, true);
        if ($auditInfo === false) {
            return [];
        }

        $data = [];
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $language['text_home'],
            'href' => $url->to('common/home')
        );
        $category_id = $auditInfo['preview_category_id'];
        $categoryList = app(CategoryRepository::class)->getUpperCategoryList($category_id);
        if ($categoryList) {
            $categoryList = array_reverse($categoryList);
        }
        foreach ($categoryList as $key => $value) {
            $data['breadcrumbs'][] = array(
                'text' => $value['name'],
                'href' => $url->to(['product/category', 'category_id' => $value['category_id']]),
            );
        }
        $data['breadcrumbs'][] = array(
            'text' => $auditInfo['name'],//产品名称
            'href' => $url->to(['product/product', 'product_id' => $productId])
        );
        $data['color_name'] = $auditInfo['color_name'];
        $data['material_name'] = $auditInfo['material_name'];

        //关联产品
        $productOptionList = [];
        $associated_product_ids = array_merge($auditInfo['associated_product_ids'], [$productId]);
        if ($associated_product_ids) {
            //替换同款列表中当前产品的颜色、材质等
            $replaceArr = [
                $productId => [
                    'image' => $auditInfo['image'],
                    'color_name' => $auditInfo['color_name'],
                    'material_name' => $auditInfo['material_name'],
                ]];
            $productOptionResults = app(ProductOptionRepository::class)->getOptionByProductIds($associated_product_ids);
            $productOptionList = app(ProductOptionRepository::class)->getProductOptionsForPage($productId, $productOptionResults, $replaceArr);
        }
        $data['product_option_list'] = $productOptionList;
        $data['name'] = $auditInfo['name'];
        $data['heading_title'] = $auditInfo['name'];
        $data['description'] = SummernoteHtmlEncodeHelper::decode($auditInfo['description']);//产品描述
        $data['return_warranty_text'] = $auditInfo['return_warranty_text'];//退返协议
        //素材包
        $image_url = $modelToolImage->resize($auditInfo['image'], configDB('theme_' . configDB('config_theme') . '_image_popup_width'), configDB('theme_' . configDB('config_theme') . '_image_popup_height'));
        $data['popup'] = $image_url;
        $data['thumb'] = $image_url;
        $data['images'] = [];
        if (is_array($auditInfo['product_image'])) {
            foreach ($auditInfo['product_image'] as $image) {
                $data['images'][] = [
                    'popup' => $modelToolImage->resize($image['orig_url'], configDB('theme_' . configDB('config_theme') . '_image_popup_width'), configDB('theme_' . configDB('config_theme') . '_image_popup_height')),
                    'thumb' => $modelToolImage->resize($image['orig_url'], configDB('theme_' . configDB('config_theme') . '_image_additional_width'), configDB('theme_' . configDB('config_theme') . '_image_additional_height')),
                ];
            }
        }

        //价格
        $data['seller_price'] = $auditInfo['price'];
        $data['price'] = $this->currency->formatCurrencyPrice($auditInfo['price'], $currency);
        $data['rawPrice'] = $auditInfo['price'];
        $data['price_display'] = $auditInfo['price_display'];
        $data['page_package_size_list'] = app(ProductRepository::class)->getPackageSizeForBuyer($productInfoNow, $this->customer->getCountryId(), $auditInfo['combo']);

        // 33309 新增字段
        $data['upc'] = $auditInfo['upc'];
        $data['is_customize'] = $auditInfo['is_customize'] ?? '';
        $data['origin_place'] = isset($auditInfo['origin_place_code']) ? CountryModel::queryRead()->where('iso_code_3', $auditInfo['origin_place_code'])->value('name') : '';
        $data['filler'] = app(OptionValueRepository::class)->getNameByOptionValueId($auditInfo['filler'] ?? '');
        $data['information_custom_field'] = $auditInfo['information_custom_field'];
        $data['assemble_length'] = $this->formatAssembleField($auditInfo['assemble_length'] ?? '');
        $data['assemble_width'] = $this->formatAssembleField($auditInfo['assemble_width'] ?? '');
        $data['assemble_height'] = $this->formatAssembleField($auditInfo['assemble_height'] ?? '');
        $data['assemble_weight'] = $this->formatAssembleField($auditInfo['assemble_weight'] ??'');
        $data['dimensions_custom_field'] = $auditInfo['dimensions_custom_field'];

        // 33309 documents
        $data['certification_documents'] = $auditInfo['certification_documents'];
        $data['material_manuals'] = $auditInfo['material_manuals'];

        return $data;
	}

    /**
     * @param $assembleField
     * @return string
     */
    private function formatAssembleField($assembleField)
    {
        if ($assembleField == -1.00) {
            return 'Not Applicable';
        }

        if (empty($assembleField)) {
            return 'Seller maintenance in progress';
        }

        return $assembleField;
    }

    /**
     * 获取某一个产品的出库时效
     * @throws Exception
     */
    public function getHandlingTime()
    {
        $productId = intval(request()->get('product_id', 0));
        $this->load->model('catalog/product');
        $is_oversize = $this->model_catalog_product->checkIsOversizeItem($productId);
        //region 获取 product中的仓库
        $this->load->model('extension/module/product_show');
        /** @var ModelExtensionModuleProductShow $productShowModel */
        $productShowModel = $this->model_extension_module_product_show;
        $warehouseDistribution = $productShowModel->getWarehouseDistributionByProductId($productId);
        $handlingTimeList = app(ProductRepository::class)->getHandlingTime($warehouseDistribution, $is_oversize, customer()->isCollectionFromDomicile());
        $data = [];
        if ($handlingTimeList) {
            $data['ship_min'] = $handlingTimeList['ship_min'];
            $data['ship_max'] = $handlingTimeList['ship_max'];
            $data['cloud_ship_min'] = $handlingTimeList['cloud_ship_min'];
            $data['cloud_ship_max'] = $handlingTimeList['cloud_ship_max'];
        }
        $this->json($data ?: (object)$data);
    }
}
