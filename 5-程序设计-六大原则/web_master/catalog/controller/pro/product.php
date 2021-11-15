<?php

use App\Catalog\Forms\Product\AddForm;
use App\Components\Locker;
use App\Components\Storage\StorageCloud;
use App\Enums\Common\YesNoEnum;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Onsite\OnsiteFreightConfig;
use App\Enums\Product\ProductAuditStatus;
use App\Enums\Product\ProductAuditType;
use App\Helper\ProductHelper;
use App\Helper\SummernoteHtmlEncodeHelper;
use App\Logging\Logger;
use App\Models\Customer\Country;
use App\Models\CustomerPartner\CustomerPartnerToProduct;
use App\Models\Link\ProductToTag;
use App\Models\Product\Option\Option;
use App\Models\Product\Product;
use App\Models\Product\ProductAudit;
use App\Models\Product\ProductCertificationDocumentType;
use App\Models\Product\ProductCustomField;
use App\Repositories\Onsite\OnsiteFreightRepository;
use App\Repositories\Product\CategoryRepository;
use App\Repositories\Product\ProductAuditRepository;
use App\Repositories\Product\ProductOptionRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Seller\SellerRepository;
use App\Services\Product\ProductAuditService;
use App\Services\Product\ProductService;
use Framework\App;
use Framework\Http\Request;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountCustomerpartnerProductGroup $model_account_customerpartner_ProductGroup
 * @property ModelAccountCustomerpartnerRebates $model_account_customerpartner_rebates
 * @property ModelCatalogProduct $model_catalog_product
 */
class ControllerProProduct extends Controller
{
    const DEFAULT_BLANK_IMAGE = 'default/blank.png';

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        //如果是Buyer
        if (!$this->customer->isPartner()) {
            $this->response->redirectTo($this->url->link('common/home', '', true))->send();
        }
    }

    /**
     * 获取产品原始地
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function originPlaces()
    {
        $originPlaces = Country::queryRead()->select(['name', 'iso_code_3 as code'])->orderBy('name')->get();
        return $this->jsonSuccess(['origin_places' => $originPlaces]);
    }

    /**
     * 获取产品认证属性
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function certificationTypes()
    {
        $certificationTypes = ProductCertificationDocumentType::query()->valid()->orderBy('title')->select(['id', 'title'])->get();
        return $this->jsonSuccess(['certification_types' => $certificationTypes]);
    }

    public function index()
    {
        view()->meta(['name' => 'google', 'content' => 'notranslate']);

        $this->load->language('account/customerpartner/addproduct');
        if ($this->request->get('product_id',0)) {
            if ($this->request->get('edit_type') == 'copy') {
                $this->document->setTitle(__('添加产品', [], 'catalog/document'));
                $data['is_add'] = true;
            } else {
                $this->document->setTitle(__('编辑产品', [], 'catalog/document'));
                if ($this->request->get('audit_id',0)){
                    $this->document->setTitle(__('产品详情(单)',[],'catalog/document'));
                }
                $data['is_add'] = false;
            }
        } else {
            $this->document->setTitle(__('添加产品',[],'catalog/document'));
            $data['is_add'] = true;
        }
        $data['cancel'] = $this->url->link('account/customerpartner/productlist');

        $url = App::url();
        $breadcrumbs[] = [
            'text' => __('产品管理',[],'catalog/document'),
            'href' => 'javascript:void(0);',
            'separator' => $this->language->get('text_separator')
        ];
        if (intval($this->request->get('product_id')) == 0) {
            $breadcrumbs[] = [
                'text' => __('添加产品',[],'catalog/document'),
                'href' => $url->link('pro/product'),
                'separator' => $this->language->get('text_separator')
            ];
        } elseif (intval($this->request->get('product_id')) > 0 && intval($this->request->get('audit_id')) == 0) {
            $breadcrumbs[] = [
                'text' => __('编辑产品',[],'catalog/document'),
                'href' => $url->link('pro/product', ['product_id' => $this->request->get('product_id')]),
                'separator' => $this->language->get('text_separator')
            ];
        } elseif (intval($this->request->get('product_id')) > 0 && intval($this->request->get('audit_id')) > 0) {
            $breadcrumbs[] = [
                'text' => __('产品详情',[],'catalog/document'),
                'href' => $url->link('pro/product', ['product_id' => $this->request->get('product_id'), 'audit_id' => $this->request->get('audit_id'), 'audit_is_edit' => intval($this->request->get('audit_is_edit'))]),
                'separator' => $this->language->get('text_separator')
            ];
        }
        $data['breadcrumbs'] = $breadcrumbs;

        if (
            $this->config->get('marketplace_separate_view')
            && isset($this->session->data['marketplace_separate_view'])
            && $this->session->data['marketplace_separate_view'] == 'separate'
        ) {
            //Seller页面
            $data['separate_view'] = true;
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        } else {
            $data['separate_view'] = false;
            $data['separate_column_left'] = '';
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');
        }

        // 实际的内容放在这儿
        $data['form_content'] = $this->load->controller(
            'pro/product/addproduct', request('product_id', 0));
        $this->response->setOutput($this->load->view('pro/product/index', $data));
    }

    //新的新增商品页面
    public function addProduct($product_id = null)
    {
        $upload_input = $this->load->controller('upload/upload_component/upload_input');
        $auditId = (int)request()->get('audit_id');//审核记录主键ID
        $auditIsEdit = (int)request()->get('audit_is_edit', 0);//是否为编辑审核记录
        $productAudit = ProductAudit::query()->select('status', 'audit_type')->find($auditId);
        if (!is_null($productAudit)
            && ($productAudit->status == ProductAuditStatus::PENDING || $productAudit->status == ProductAuditStatus::NOT_APPROVED)
            && $auditIsEdit > 0
            && $productAudit->audit_type == ProductAuditType::PRODUCT_INFO) {
            $auditIsEdit = 1;
        }else {
            $auditIsEdit = 0;
        }
        $isReadOnly = ($auditId > 0 && $auditIsEdit <= 0) ? 1 : 0;

        $data = compact('upload_input', 'product_id');
        $data['app_version'] = APP_VERSION;
        $data['country_id'] = customer()->getCountryId();
        $data['account_type'] = customer()->getAccountType();
        $data['audit_id'] = $auditId;
        $data['isReadOnly'] = $isReadOnly;
        $data['edit_type'] = (string)request()->get('edit_type') == 'copy' ? 'copy' : ''; //复制商品
        $data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();
        $data['on_site_seller_info'] = [
            'is_onsite_seller' => customer()->isGigaOnsiteSeller() ? 1 : 0,
            'ltl_quote' => 0,
        ];
        if (customer()->isGigaOnsiteSeller()) {
            $freightInfo = app(OnsiteFreightRepository::class)->calculateOnsiteFreightInfo(customer()->getId());
            if ($freightInfo['ltl_quote']) {
                $data['on_site_seller_info']['ltl_quote'] = 1;
            }
        }

        return $this->load->view('pro/product/addproduct', $data);
    }

    /**
     * onsite类型seller检测运费是否配置相关接口
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function checkFreight()
    {
        $productId = $this->request->post('product_id', 0);
        if (customer()->getAccountType() != CustomerAccountingType::GIGA_ONSIDE) {
            return $this->jsonSuccess(['back_code' => 200]);
        }
        /** @var Product $currentProduct */
        $currentProduct = CustomerPartnerToProduct::query()->alias('pt')
            ->leftJoin('oc_product as op', 'pt.product_id', '=', 'op.product_id')
            ->where('pt.customer_id', customer()->getId())
            ->where('pt.product_id', $productId)
            ->select(['op.product_id', 'op.status'])
            ->first();
        if (empty($currentProduct)) {
            return $this->jsonFailed(__('哎呀！ 报错了，这可能是系统的问题。 请稍后重试或向我们报告问题！', [], 'common'));
        }

        //上架的直接放行
        if ($currentProduct->status == 1) {
            return $this->jsonSuccess(['back_code' => 200]);
        }
        //有无待审核记录
        $waitCheckExist = ProductAudit::query()
            ->where('product_id', $productId)
            ->where('status', ProductAuditStatus::PENDING)
            ->where('audit_type', ProductAuditType::PRODUCT_INFO)
            ->where('is_delete', 0)
            ->exists();

        if ($waitCheckExist) {
            //检测onsite类型seller 配置运费信息
            $result = ProductHelper::sendProductsFreightRequest([$productId], 1);
            if (empty($result) || !is_array($result)) {
                return $this->jsonFailed(__('哎呀！ 报错了，这可能是系统的问题。 请稍后重试或向我们报告问题！', [], 'common'));
            }

            if (isset($result['data']['errors']) && !empty($result['data']['errors'])) {
                $errorInfo = array_shift($result['data']['errors']);
                Logger::productFreight("onsite seller 的商品{$productId}运费API计算出错：" . json_encode($errorInfo));
                if (in_array($errorInfo['code'] ?? -1, OnsiteFreightConfig::getGigaOnsiteIllegalCode())) {
                    if ($errorInfo['code'] == OnsiteFreightConfig::GIGAONSITE_CODE_NOT_CONFIG_LTL) {
                        $errorInfo['code'] = OnsiteFreightConfig::GIGAONSITE_CODE_NOT_CONFIG;
                    }
                    return $this->jsonSuccess(['back_code' => $errorInfo['code']]);
                } else {
                    return $this->jsonFailed(__('哎呀！ 报错了，这可能是系统的问题。 请稍后重试或向我们报告问题！', [], 'common'));
                }
            }
        }

        return $this->jsonSuccess(['back_code' => 200]);
    }

    /**
     * 检测seller创建产品mpn是否可用
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function checkMpnValid()
    {
        $mpn = request('mpn', '');

        if (app(ProductRepository::class)->getProductInfoByCustomerIdAndMpn(customer()->getId(), $mpn)) {
            return $this->jsonFailed(__('失败！MPN不能重复。',[],'validation/product'));
        }

        if (customer()->isGigaOnsiteSeller() && app(ProductRepository::class)->hasSkuProductByMpnAndCountryId($mpn, customer()->getCountryId())) {
            return $this->jsonFailed(__('失败！MPN不能与已存在的Item Code重复。',[],'validation/product'));
        }

        return $this->jsonSuccess();
    }

    //检查是否有关联产品的增删 新接口 只提示
    public function checkRelationDelicacyProduct()
    {
        $post = $this->request->post();
        $productId = (int)$this->request->get('product_id', 0);
        $rtn = ['code' => 0, 'msg' => ''];
        $productAssociatedList = $post['product_associated'];
        $this->load->language('account/customerpartner/rebates');
        //返点模板
        /** @var \ModelAccountCustomerpartnerRebates $modelReb */
        $modelReb = load()->model('account/customerpartner/rebates');
        $tplNumList = $modelReb->get_rebate_all_count($productAssociatedList);
        //新增商品
        if (!$productId) {
            if ($tplNumList) {
                $rtn = [
                    'code' => 1,
                    'msg' => __('该系列产品已设置返点模板，并且您关联了相似型号的新产品。 如果要将它们包括在返点模板中，请修改返点模板。', [], 'controller/product'),
                ];
            }
            return $this->returnJson($rtn);
        }
        //编辑商品
        $productInfo = Product::query()->with('associatesProducts')->find($productId);
        if (!$productInfo) {
            $rtn = [
                'code' => 1,
                'msg' => 'Product Info Error',
            ];
            return $this->returnJson($rtn);
        }
        //优先校验 可单独售卖->不可单独售卖，最后不要直接return了，这个校验通过，还需要继续往下执行校验
        if ($productInfo->buyer_flag == 1 && $post['buyer_flag'] == 0) {
            $existDelicacyManagement = db('oc_delicacy_management')
                ->where('seller_id', customer()->getId())
                ->where('product_id', $productId)
                ->exists();
            if ($tplNumList && $existDelicacyManagement) {
                $rtn = [
                    'code' => 1,
                    'msg' => __('您已经将此产品设置为不可单独售卖，如果您要从返点模板（专有价格）中删除产品，请修改返点模板（专有价格）。但是，它不会影响现行协议。', [], 'controller/product'),
                ];
                return $this->returnJson($rtn);
            } elseif ($tplNumList) {
                $rtn = [
                    'code' => 1,
                    'msg' => __('您已经将此产品设置为不可单独售卖，如果您要从返点模板中删除产品，请修改返点模板。但是，它不会影响现行协议。', [], 'controller/product'),
                ];
                return $this->returnJson($rtn);
            } elseif ($existDelicacyManagement) {
                $rtn = [
                    'code' => 1,
                    'msg' => __('您已经将此产品设置为不可单独售卖，如果您要从专有价格中删除产品，请修改专有价格。但是，它不会影响现行协议。', [], 'controller/product'),
                ];
                return $this->returnJson($rtn);
            }
        }
        //校验产品关联关系  新增时候没有校验精细化，产品说编辑时候也一样，不校验精细化，只校验返点模板
        $oldAssociatedProductIds = $productInfo->associatesProducts()->where('associate_product_id', '!=', $productId)->pluck('associate_product_id')->toArray();
        if ($oldAssociatedProductIds || $productAssociatedList) {
            sort($oldAssociatedProductIds);
            sort($productAssociatedList);
            if ($oldAssociatedProductIds != $productAssociatedList) {
                $msg = '';
                $oldDiffProduct = $newDiffProduct = [];
                foreach ($oldAssociatedProductIds as $oldProduct) {
                    if (!in_array($oldProduct, $productAssociatedList)) {
                        $oldDiffProduct[] = $oldProduct; //删掉的商品id
                    }
                }
                foreach ($productAssociatedList as $newProduct) {
                    if (!in_array($newProduct, $oldAssociatedProductIds)) {
                        $newDiffProduct[] = $newProduct; //新增的商品id
                    }
                }
                if (empty($oldDiffProduct) && !empty($newDiffProduct)) { //只新增
                    $tplNumList = $modelReb->get_rebate_all_count($oldAssociatedProductIds);
                    if ($tplNumList) {
                        $msg = __('该系列产品已设置返点模板，并且您关联了相似型号的新产品。 如果要将它们包括在返点模板中，请修改返点模板。', [], 'controller/product');
                    }
                } elseif (!empty($oldDiffProduct) && empty($newDiffProduct)) { //只删除
                    $tplNumList = $modelReb->get_rebate_all_count($oldDiffProduct);
                    if ($tplNumList) {
                        $msg = __('您已从同一型号中删除产品。如果您想从返点模板中删除它们，请修改返点模板。但是，它不会影响现行协议。', [], 'controller/product');
                    }
                } elseif (!empty($oldDiffProduct) && !empty($newDiffProduct)) { //有增有删
                    $tplNumListDel = $modelReb->get_rebate_all_count($oldDiffProduct); //删掉的
                    $tplNumListAdd = $modelReb->get_rebate_all_count($newDiffProduct); //新增的
                    if ($tplNumListDel && $tplNumListAdd) {
                        $msg = __('该系列产品已设置返点模板，并且您关联了相似型号的新产品。 如果要将它们包括在返点模板中，请修改返点模板。同时，您已从同一型号中删除了产品。 如果您想从返点模板中删除它们，请修改返点模板。但是，它不会影响现行协议。', [], 'controller/product');
                    } elseif ($tplNumListDel) {
                        $msg = __('您已从同一型号中删除产品。如果您想从返点模板中删除它们，请修改返点模板。但是，它不会影响现行协议。', [], 'controller/product');
                    } elseif ($tplNumListAdd) {
                        $msg = __('您已经将此产品设置为不可单独售卖，如果您要从返点模板（专有价格）中删除产品，请修改返点模板（专有价格）。但是，它不会影响现行协议。', [], 'controller/product');
                    }
                }
                if ($msg) {
                    $rtn = [
                        'code' => 1,
                        'msg' => $msg,
                    ];
                    return $this->returnJson($rtn);
                }
            }
        }
        return $this->returnJson($rtn);
    }

    //创建新商品接口
    public function storeProduct(AddForm $requestForm)
    {
        $requestMessage = $requestForm->validator();
        if ($requestMessage) {
            $ret = [
                'msg' => $requestMessage,
                'code' => 0,
            ];
            return $this->returnJson($ret);
        }
        $post = $this->request->post();
        $this->htmlEntity($post);
        $customer_id = $this->customer->getId();
        $this->load->language('account/customerpartner/addproduct');
        $this->load->model('account/customerpartner');
        /** @var ModelAccountCustomerpartner $modelAc */
        $modelAc = $this->model_account_customerpartner;

        $post['product_group_ids'] = $post['product_group_ids'] ? explode(',', $post['product_group_ids']) : [];
        $post['seller_id'] = $customer_id;
        // 专利产品为未选中时图片列表置为空
        if($post['original_product'] == 0){
            $post['original_design'] = [];
        }
        $auditId = $post['audit_id'] ?? 0;

        if ($auditId && !empty($post['product_id'])){//编辑审核记录(产品信息)
            $productResult = app(ProductAuditService::class)->updateOrInsertInfo($auditId, $post);
        } else {//添加/编辑产品
            $lock = Locker::addEditProduct(customer()->getId(), 5);
            if (!$lock->acquire()) {
                $ret = [
                    'msg' => 'Saving,Please Wait.',
                    'info' => '',
                    'code' => 0,
                ];
                return $this->returnJson($ret);
            }

            if (!isset($post['product_id']) || !$post['product_id']) {
                $productResult = $modelAc->addProduct($post);
            } else {
                $productResult = $modelAc->editProduct($post);
            }

            //如果是不可售卖商品，可以直接上架(提交审核时候直接上架，草稿则正常存草稿)，只能放在这，因为event里面有很多逻辑处理，有顺序执行问题
            if (isset($productResult['product_id']) && $productResult['product_id'] > 0) {
                app(ProductService::class)->resetProductInfo($productResult, $post);
            }

            $lock->release();
        }


        if ($productResult === false || !$productResult['product_id']) {
            $ret = [
                'msg' => __('保存失败', [], 'common'),
                'info' => '',
                'code' => 0,
            ];
        } else {
            $ret = [
                'msg' => __('保存成功', [], 'common'),
                'info' => $productResult,
                'code' => 1,
            ];
        }
        return $this->returnJson($ret);
    }

    private function htmlEntity(&$data)
    {
        $data['name'] = SummernoteHtmlEncodeHelper::encode($data['name'], true);
        //sunjiehuan说以后数据库的产品描述就存HTML源码；所以这里不转义。
    }

    // region api
    // 获取当前的产品目录
    public function getCates()
    {
        $cate = $this->getCategories();
        function resolve(&$arr)
        {
            array_walk($arr, function (&$item) {
                if ($item['name']) $item['name'] = html_entity_decode($item['name']);
                if ($item['son']) resolve($item['son']);
            });
        }

        resolve($cate);
        $this->returnJson($cate);
    }

    // 获取用户组信息
    public function getCustomerGroup()
    {
        $this->load->model('Account/Customerpartner/ProductGroup');
        $this->returnJson(
            $this->model_Account_Customerpartner_ProductGroup
                ->getAllListAndNoPage([], $this->customer->getId())
        );
    }

    //获取颜色和材质接口和dim，放在一起
    public function getColorAndMaterial()
    {
        $colorOptionNames = app(ProductOptionRepository::class)->getOptionsWithSortById(Option::COLOR_OPTION_ID)->toArray();
        $materialOptionNames = app(ProductOptionRepository::class)->getOptionsWithSortById(Option::MATERIAL_OPTION_ID)->toArray();
        $dimAndLimit = app(ProductRepository::class)->getDimLimitWeightAndSeparateEnquiry();
        $result = [
            'color_options' => $colorOptionNames,
            'material_options' => $materialOptionNames,
            'dim_and_limit' => $dimAndLimit,
        ];
        return $this->jsonSuccess($result);
    }

    //获取曾经选择的类目
    public function getOnceSelectedCategory()
    {
        $customerId = $this->customer->getId();
        $lists = $this->orm::table('oc_store_selected_category')
            ->where('customer_id', $customerId)
            ->orderByDesc('update_time')
            ->limit(200)
            ->get()
            ->map(function ($item) {
                return app(CategoryRepository::class)->getUpperCategory($item->category_id, [], true);
                //return $item;
            });
        //过滤掉失效数据
        $showLists = [];
        foreach ($lists->toArray() ?? [] as $item) {
            if ($item['category_name'] && $item['category_ids']) {
                $showLists[] = $item;
            }
        }
        $result = ['lists' => $showLists];
        return $this->jsonSuccess($result);
    }

    //获取商品设置的参数，没有的话，拿店铺的，拿不到拿系统的
    public function getProductReturnWarranty(Request $request)
    {
        $productId = (int)$request->get('product_id', 0);
        if ($productId) {
            $result = app(ProductRepository::class)->getProductReturnWarranty($productId);
        } else {
            $result = app(ProductAuditRepository::class)->getStoreReturnWarranty();
        }
        return $this->jsonSuccess($result);
    }

    /**
     * 查找关联产品
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getProductAssociates()
    {
        $productIds = request('product_ids', '');
        $excludeProductIds = request('exclude_product_ids', '');
        if (empty($productIds)) {
            return $this->jsonSuccess([]);
        }
        $productIds = explode(',', $productIds);
        $excludeProductIds = explode(',', $excludeProductIds);

        $data = app(ProductRepository::class)->getAssociateProductsByProductIds($productIds, $excludeProductIds);
        return $this->jsonSuccess($data);
    }

    // 重写获取商品接口
    public function getProductInfo(Request $request)
    {
        $productId = $request->get('product_id', 0);
        $productExist = $this->orm::table('oc_customerpartner_to_product')
            ->where('product_id', $productId)
            ->where('customer_id', customer()->getId())
            ->exists();
        if (!$productExist) {
            return $this->jsonFailed('product is not exist');
        }
        $productInfo = app(ProductRepository::class)->getSellerProductInfo($productId);
        if ($productInfo === false) {
            return $this->jsonFailed('product is not exist');
        }
        $productInfo['name'] = SummernoteHtmlEncodeHelper::decode($productInfo['name'], true);
        $productInfo['product_size'] = SummernoteHtmlEncodeHelper::decode($productInfo['product_size'], true);
        $productInfo['description'] = SummernoteHtmlEncodeHelper::decode($productInfo['description']);
        $result = array_merge(
            $productInfo,
            $this->getProductMaterialPackage($productId),
            ['product_image' => $this->getProductExtraInfo($productId)]
        );

        return $this->jsonSuccess($result);
    }

    /**
     * 获取关联商品的api
     */
    public function getAssociatedProducts()
    {
        $co = new Collection(json_decode(file_get_contents('php://input'), true));
        $pageSize = $co->get('page_size', 5);
        $currentPage = $co->get('page', 1);
        /** @var Builder $query */
        $query = $this->orm
            ->table(DB_PREFIX . 'product as p')
            ->select([
                'p.product_id', 'p.mpn', 'p.sku', 'pd.name', 'p.image','p.product_size'
            ])
            ->selectRaw(
                'round(p.length,2) as length,
            round(p.width,2) as width,
            round(p.height,2) as height,
            round(p.weight,2) as weight'
            )
            ->join(DB_PREFIX . 'customerpartner_to_product as c2p', ['c2p.product_id' => 'p.product_id'])
            ->leftJoin(DB_PREFIX . 'product_description as pd', ['pd.product_id' => 'p.product_id'])
            ->where([
//                'p.status' => 1, //seller 可以选择所有的产品（已下架,已上架,待上架）
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1, //允许单独售卖
                'c2p.customer_id' => (int)$this->customer->getId(),
            ])
            ->whereIn('p.product_type',[0])  // [0,3]
            ->when(
                !empty($co->get('filter_search')),
                function (Builder $q) use ($co) {
                    $q->where(function (Builder $q) use ($co) {
                        $filter = trim(htmlspecialchars($co->get('filter_search')));
                        $q->orWhere('p.mpn', 'like', "%{$filter}%")
                            ->orWhere('p.sku', 'like', "%{$filter}%")
                            ->orWhere('pd.name', 'like', "%{$filter}%");
                    });
                }
            )
            ->when(
                !empty($co->get('product_id')),
                function (Builder $q) use ($co) {
                    $q->whereNotIn('p.product_id', $co->get('product_id'));
                }
            )
            ->orderBy('p.product_id', 'desc');
        $total = $query->count();
        if ($total <= ($currentPage - 1) * $pageSize) $currentPage = 1;
        /** @var Collection $res */
        $res = $query->forPage($currentPage, $pageSize)->get();
        $ret = [];
        $this->load->model('catalog/product');
        /** @var ModelCatalogProduct $mcp */
        $mcp = $this->model_catalog_product;
        $product_ids = $res->pluck('product_id')->toArray();
        // hard code  attribute中的color属性id为13
        $colors = $mcp->getProductOptionValueByProductIds($product_ids, Option::MIX_OPTION_ID, (int)$this->customer->getId());
        $newColor = $mcp->getProductOptionValueByProductIds($product_ids, Option::COLOR_OPTION_ID, (int)$this->customer->getId());
        $material = $mcp->getProductOptionValueByProductIds($product_ids, Option::MATERIAL_OPTION_ID, (int)$this->customer->getId());
        $fillerData = app(ProductRepository::class)->getProductExtByIds(collect($res)->pluck('product_id')->toArray());

        $customFieldData = app(ProductRepository::class)->getCustomFieldByIds(collect($res)->pluck('product_id')->toArray());
        $res->each(function ($item) use ($customFieldData,$fillerData, $colors, $newColor,$material,&$ret) {
            $row = get_object_vars($item);
            $pId = $row['product_id'];
            $row['name'] = htmlspecialchars_decode($row['name']);
            $row['image'] = $this->resize($row['image'], 50, 50);
            $tmpColor = isset($newColor[$pId]) ? $newColor[$pId] : (isset($colors[$pId]) ? $colors[$pId] : '');
            $row['color'] = $tmpColor;
            $row['material'] = isset($material[$pId]) ? $material[$pId] : '';
            $row['product_size'] = $item->product_size;
            //获取配置的客户字段信息
            $row['custom_field'] = $customFieldData[$pId] ?? [];
            $row['filler'] = $fillerData[$pId]['filler_option_value']['name'] ?? null;

            $ret[] = $row;
        });
        $this->returnJson(['data' => $ret, 'total' => $total, 'page' => $currentPage]);
    }

    /**
     * 获取子产品的api
     */
    public function getComboProducts()
    {
        $co = new Collection(json_decode(file_get_contents('php://input'), true));
        $pageSize = $co->get('page_size', 5);
        $currentPage = $co->get('page', 1);
        /** @var Builder $query */
        $query = $this->orm
            ->table(DB_PREFIX . 'product as p')
            ->select([
                'p.product_id', 'p.mpn', 'p.sku', 'pd.name', 'p.image',
            ])
            ->selectRaw(
                'round(p.length,2) as length,round(p.width,2) as width,round(p.height,2) as height,round(p.weight,2) as weight,round(p.length_cm,2) as length_cm,round(p.width_cm,2) as width_cm,round(p.height_cm,2) as height_cm,round(p.weight_kg,2) as weight_kg'
            )
            ->join(DB_PREFIX . 'customerpartner_to_product as c2p', ['c2p.product_id' => 'p.product_id'])
            ->leftJoin(DB_PREFIX . 'product_description as pd', ['pd.product_id' => 'p.product_id'])
            ->where([
                'c2p.customer_id' => (int)$this->customer->getId(),
                'p.combo_flag' => 0,
                'p.is_deleted' => 0,
            ])
            ->whereIn('p.product_type',[0]) //[0,3]
            ->when(
                !empty($co->get('filter_search')),
                function (Builder $q) use ($co) {
                    $q->where(function (Builder $q) use ($co) {
                        $filter = htmlspecialchars(trim($co->get('filter_search')));
                        $q->orWhere('p.mpn', 'like', "%{$filter}%")
                            ->orWhere('p.sku', 'like', "%{$filter}%")
                            ->orWhere('pd.name', 'like', "%{$filter}%");
                    });
                }
            )
            ->when(
                !empty($co->get('product_id')),
                function (Builder $q) use ($co) {
                    $q->whereNotIn('p.product_id', $co->get('product_id'));
                }
            )
            ->orderBy('p.product_id', 'desc');
        $total = $query->count();
        if ($total <= ($currentPage - 1) * $pageSize) $currentPage = 1;
        /** @var Collection $res */
        $res = $query->forPage($currentPage, $pageSize)->get();
        $ret = [];
        //$this->load->model('catalog/product');
        /** @var ModelCatalogProduct $mcp */
        //$mcp = $this->model_catalog_product;
        $product_ids = $res->pluck('product_id')->toArray();
        //颜色页面暂时没展示，先注释掉，根据需求决定是否放开
        //$colors = $mcp->getProductOptionValueByProductIds($product_ids, 13, (int)$this->customer->getId());
        $countryId = customer()->getCountryId();
        $tags = ProductToTag::query()
            ->whereIn('product_id', $product_ids)
            ->where('tag_id', (int)configDB('tag_id_oversize'))
            ->get()
            ->keyBy('product_id');

        $res->each(function ($item) use (&$ret,$countryId,$tags) {
            $row = get_object_vars($item);
            //$pId = $row['product_id'];
            //$row['color'] = $colors[$pId] ?? ''; //注释原因同上
            $row['name'] = htmlspecialchars_decode($row['name']);
            $row['image'] = $this->resize($row['image'], 50, 50);
            $row['length'] = customer()->isUSA() ? $row['length'] : $row['length_cm'];
            $row['width'] = customer()->isUSA() ? $row['width'] : $row['width_cm'];
            $row['height'] = customer()->isUSA() ? $row['height'] : $row['height_cm'];
            $row['weight'] = customer()->isUSA() ? $row['weight'] : $row['weight_kg'];
            $row['is_ltl'] = isset($tags[$row['product_id']]) ? 1 : 0;
            unset($row['length_cm'], $row['width_cm'], $row['height_cm'], $row['weight_kg']);
            $ret[] = $row;
        });
        $this->returnJson(['data' => $ret, 'total' => $total, 'page' => $currentPage]);
    }

    // end region

    /**
     * 采用递归的方法获取目录类
     *
     * @param  $first
     * @return array
     */
    protected function getCategories($first = null)
    {
        $cats = $this->categories($first);
        if (!$cats) {
            return [];
        }
        foreach ($cats as $i => $cat) {
            $cats[$i]['son'] = $this->getCategories($cat['category_id']);
        }

        return $cats;
    }

    protected function categories(?int $categoryId): array
    {
        $this->load->model('account/customerpartner');
        /** @var ModelAccountCustomerpartner $modelAccountCustomerpartner */
        $modelAccountCustomerpartner = $this->model_account_customerpartner;
        return $modelAccountCustomerpartner->getCategoryByParentCategoryId($categoryId);
    }

    /**
     * 获取商品素材包信息
     *
     * @param int|null $productId
     * @return array
     */
    protected function getProductMaterialPackage(?int $productId): array
    {
        if (empty($productId)) return [];
        $material_images = $this->orm->table(DB_PREFIX . 'product_package_image')
            ->select(['image as orig_url', 'product_package_image_id as m_id', 'file_upload_id as file_id'])
            ->selectRaw('case when origin_image_name != "" then origin_image_name else image_name end as name')
            ->where(['product_id' => $productId])
            ->get();
        $material_images = $material_images->map(function ($item) {
            return $this->resolveMaterialFile(get_object_vars($item));
        });
        $material_images = $material_images->toArray();
        $material_manuals = $this->orm->table(DB_PREFIX . 'product_package_file')
            ->select(['file as orig_url', 'product_package_file_id as m_id', 'file_upload_id as file_id'])
            ->selectRaw('case when origin_file_name != "" then origin_file_name else file_name end as name')
            ->where(['product_id' => $productId])
            ->get();
        $material_manuals = $material_manuals->map(function ($item) {
            return $this->resolveMaterialFile(get_object_vars($item));
        });
        $material_manuals = $material_manuals->toArray();
        $material_video = $this->orm->table(DB_PREFIX . 'product_package_video')
            ->select(['video as orig_url', 'product_package_video_id as m_id', 'file_upload_id as file_id'])
            ->selectRaw('case when origin_video_name != "" then origin_video_name else video_name end as name')
            ->where(['product_id' => $productId])
            ->get();
        $material_video = $material_video->map(function ($item) {
            return $this->resolveMaterialFile(get_object_vars($item));
        });
        $material_video = $material_video->toArray();

        $original_design = $this->orm->table(DB_PREFIX . 'product_package_original_design_image')
            ->select(['image as orig_url', 'product_package_original_design_image_id as m_id', 'file_upload_id as file_id'])
            ->selectRaw('case when origin_image_name != "" then origin_image_name else image_name end as name')
            ->where(['product_id' => $productId])
            ->get();
        $original_design = $original_design->map(function ($item) {
            return $this->resolveMaterialFile(get_object_vars($item));
        });
        $original_product = $original_design->isEmpty() ? YesNoEnum::NO : YesNoEnum::YES;
        return compact('material_images', 'material_manuals', 'material_video','original_design','original_product');
    }

    /**
     * oc_product_package_image
     * oc_product_package_file
     * oc_product_package_video
     * 中数据取出来时候进行处理 (兼容之前lxx的写法)
     *
     * @param array $item
     * @return array
     */
    protected function resolveMaterialFile(array $item)
    {
        $this->resolveImageItem($item, true);
        return $item;
    }

    /**
     * 获取商品额外信息 即不在oc_product表中的信息
     *
     * @param int|null $product_id
     * @return array
     */
    protected function getProductExtraInfo(?int $product_id): array
    {
        $product_image = $this->orm
            ->table(DB_PREFIX . 'product_image')
            ->select('image as orig_url', 'sort_order')
            ->where(['product_id' => $product_id])
            ->orderBy('sort_order')
            ->get();
        $sort = 0;
        $product_image = $product_image->map(function ($item) use (&$sort) {
            $item = get_object_vars($item);
            $item['sort_order'] = $sort++;
            $this->resolveImageItem($item);
            return $item;
        });

        return $product_image->toArray();
    }

    // 返回json数据
    protected function returnJson($res)
    {
        $this->response->returnJson($res);
    }

    /**
     * @param string|null $imagePath 相对路径 相对于image/目录的路径
     * @param int $width
     * @param int $height
     * @return string
     */
    protected function resize(string $imagePath = null, int $width = 100, int $height = 100): string
    {
        return StorageCloud::image()->getUrl($imagePath, [
            'w' => $width,
            'h' => $height,
            'no-image' => static::DEFAULT_BLANK_IMAGE,
        ]);
    }

    /**
     * 处理图片相关信息
     * @param array $item
     * @param bool $isMaterialFiles
     */
    protected function resolveImageItem(array &$item, $isMaterialFiles = false)
    {
        if (stripos($item['orig_url'], 'http') === 0) {
            // http 的路径不处理
            return;
        }
        if ($isMaterialFiles) {
            if (preg_match('/^(\d+)\/(\d+)\/(file|image|video)\/(.*)/', $item['orig_url'])) {
                // 兼容原 根目录/productPackage 下的文件
                // 57/10821/image/1547113262_1.jpg 之前的写法是这样的
                $item['replace_url'] = $item['orig_url'];
                $item['orig_url'] = 'productPackage/' . $item['orig_url'];
            }
        }
        $item['thumb'] = $this->resize($item['orig_url'], 100, 100);
        $item['is_blank'] = (int)(strpos($item['thumb'], static::DEFAULT_BLANK_IMAGE) !== false);
        if (!$item['is_blank']) {
            $item['url'] = StorageCloud::image()->getUrl($item['orig_url'], ['check-exist' => false]);
        }
    }

}
