<?php

use App\Helper\CountryHelper;
use App\Models\Customer\Customer;
use App\Repositories\Seller\SellerRepository;
use App\Widgets\VATToolTipWidget;

/**
 * Class ControllerAccountCustomerPartnerDelicacyManagement
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelCommonProduct $model_common_product
 */
class ControllerAccountCustomerPartnerDelicacyManagement extends Controller
{
    private $customer_id = null;
    private $isPartner = false;

    /**
     * @var ModelCustomerPartnerDelicacyManagement $model
     */
    private $model;

    /**
     * 精度
     * @var int $precision
     */
    private $precision;

    /**
     * ControllerAccountCustomerPartnerDelicacyManagement constructor.
     * @param Registry $registry
     * @throws Exception
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        $this->load->model('account/customerpartner');
        $this->isPartner = $this->model_account_customerpartner->chkIsPartner();
        if (empty($this->customer_id) || !$this->isPartner) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        // model
        $this->load->model('customerpartner/DelicacyManagement');
        $this->model = $this->model_customerpartner_DelicacyManagement;
        $this->load->language('account/customerpartner/delicacy_management');
        $this->precision = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
        $this->load->model('common/product');
    }

    /**
     * @return void
     */
    private function checkPOST()
    {
        if (!request()->isMethod('POST')) {
            $response = [
                'error' => 1,
                'msg' => 'Bad Method!',
                'jump_url' => ''
            ];
            $this->returnJson($response);
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function index()
    {
        $data = $this->load->language('account/customerpartner/delicacy_management');
        $this->document->addScript("catalog/view/javascript/layer/layer.js");
        $this->document->addScript("catalog/view/javascript/laydate/laydate.js");

        $this->document->setTitle($this->language->get('heading_title'));

        $this->getList($data);
    }

    /**
     * @todo 如果当前buyer尚未和seller关联，则需要给出提示并返回。
     *
     * @param array $data
     * @throws ReflectionException
     * @throws Exception
     */
    private function getList($data)
    {
        $data['chkIsPartner'] = $this->isPartner;
        $url = "";

        //判断是否是日本用户
        $data['isJapan'] = true;
        if (empty($this->customer->getCountryId()) || $this->customer->getCountryId() != JAPAN_COUNTRY_ID) {
            $data['isJapan'] = false;
        }

        //判断是否是日本用户
        $data['isAmerica'] = true;
        if (empty($this->customer->getCountryId()) || $this->customer->getCountryId() != AMERICAN_COUNTRY_ID) {
            $data['isAmerica'] = false;
        }

        $data['is_show_freight'] = false;
        if (in_array($this->customer->getAccountType(), [1, 2]) && $data['isAmerica']) {
            $data['is_show_freight'] = true;
        }


        $data['breadcrumbs'] = [
            [
                'text' => $data['heading_parent_title'],
                'href' => 'javascript:void(0);'
            ],
            [
                'text' => $data['heading_title'],
                'href' => $this->url->link('account/customerpartner/delicacymanagement', $url, true)
            ]
        ];

        $data['buyer_id'] = (int)($this->request->get('buyer_id', 0));
        $data['product_id'] = (int)($this->request->get( 'product_id', 0));

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');


        $data['separate_view'] = false;

        $data['separate_column_left'] = '';

        if (
            $this->config->get('marketplace_separate_view')
            && isset($this->session->data['marketplace_separate_view'])
            && session('marketplace_separate_view') == 'separate'
        ) {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');

            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        }

        /**
         * url
         */
        $data['url_batch_add_by_set_invisible_buyers'] = $this->url->link('account/customerpartner/delicacymanagement/batchAddSetInvisibleByProducts', '', true);
        $data['url_batch_add_by_set_invisible_products'] = $this->url->link('account/customerpartner/delicacymanagement/batchAddSetInvisibleByBuyers', '', true);
        $data['url_batch_add_by_set_invisible'] = $this->url->link('account/customerpartner/delicacymanagement/batchSetInvisible', '', true);
        $data['url_batch_remove'] = $this->url->link('account/customerpartner/delicacymanagement/batchRemove', '', true);
        $data['url_edit'] = $this->url->link('account/customerpartner/delicacymanagement/edit', '', true);
        $data['url_add'] = $this->url->link('account/customerpartner/delicacymanagement/add', '', true);
        $data['url_batch_add'] = $this->url->link('account/customerpartner/delicacymanagement/batchAdd', '', true);
        $data['url_get_products_except_in_delicacy'] = $this->url->link('account/customerpartner/delicacymanagement/getAllProductsExceptInDelicacy', '', true);
        $data['url_get_buyers_except_in_delicacy'] = $this->url->link('account/customerpartner/delicacymanagement/getAllBuyersExceptInDelicacy', '', true);
        $data['url_set_price'] = $this->url->link('account/customerpartner/delicacymanagement/batchSetPrice', '', true);
        $data['url_add_by_set_price'] = $this->url->link('account/customerpartner/delicacymanagement/batchAddBySetPrice', '', true);
        $data['url_help'] = $this->url->link('information/information&information_id=61', '', true);

        $data['time_server'] = date('Y-m-d H:i:s');
        $data['url_buyer_group'] = $this->url->link('account/customerpartner/buyergroup&group_id=', '', true);
        $data['url_product_group'] = $this->url->link('account/customerpartner/productgroup&group_id=', '', true);
        $data['url_product_detail'] = $this->url->link('product/product&product_id=', '', true);
        $data['url_download'] = $this->url->link('account/customerpartner/delicacyManagement/download&type=', '', true);
        $data['url_download_template'] = $this->url->link('account/customerpartner/delicacyManagement/downloadTemplate','',true);
        $data['product_price_proportion'] = PRODUCT_PRICE_PROPORTION;
        $data['is_non_inner_account'] = $this->customer->isNonInnerAccount();

        // tips 时区跟随当前国别
        $country_times = [
            'DEU' => 'Berlin',
            'JPN' => 'Tokyo',
            'GBR' => 'London',
            'USA' => 'Pacific'
        ];
        if (in_array(session('country'), array_keys($country_times))) {
            $data['tip_update_time'] = str_replace('_current_country_', $country_times[session('country')], $this->language->get('tip_update_time'));
            $data['tip_table_effective_time'] = str_replace('_current_country_', $country_times[session('country')], $this->language->get('tip_table_effective_time'));
            $data['tip_table_expiration_time'] = str_replace('_current_country_', $country_times[session('country')], $this->language->get('tip_table_expiration_time'));
            $data['text_24_price_protect'] = str_replace('_current_country_', $country_times[session('country')], $this->language->get('text_24_price_protect'));
        }
        // 是否显示云送仓提醒
        $data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();

        $this->response->setOutput($this->load->view('account/customerpartner/delicacy_management', $data));
    }

    public function getAllProducts()
    {
        $page = (int)get_value_or_default($this->request->get, 'page', 1);
        $pageSize = (int)get_value_or_default($this->request->get, 'pageSize', 9999);
//        $buyerID = (int)get_value_or_default($this->request->get, 'buyer_id', 0);

        // 获取该seller_id 关联的所有product
        $data = $this->model->getAllProducts($this->customer_id, $page, $pageSize);
        $noComboProductIDArr = [];
        $comboProductIDArr = [];
        foreach ($data as &$item) {
            // item 中加入oversize信息
            $item->oversize_alarm_price = $this->model_common_product->getAlarmPrice($item->product_id, true);
            //如果是combo
            if ($item->combo_flag == 1) {
                $comboProductIDArr[] = $item->product_id;
            } else {
                $noComboProductIDArr[] = $item->product_id;
            }
        }
        unset($item);
        // 获取非combo的 在库库存
        $inStockQuantityObjs = $this->model->getInStockQuantity($noComboProductIDArr);
        $inStockProductArr = [];
        foreach ($inStockQuantityObjs as $inStockQuantityObj) {
            $inStockProductArr[$inStockQuantityObj->product_id] = $inStockQuantityObj->instock_quantity;
        }

        // combo根据子SKU计算在库库存
        $comboInfoObjs = $this->model->getComboInfo($comboProductIDArr);
        $comboProductArr = [];
        foreach ($comboInfoObjs as $comboInfoObj) {
            $comboProductArr[$comboInfoObj->product_id][] = [
                'qty' => $comboInfoObj->qty,
                'set_product_id' => $comboInfoObj->set_product_id
            ];
        }
        foreach ($comboProductArr as $productID => $comboInfo) {
            $temp = [];
            foreach ($comboInfo as $value) {
                //舍去法取整，获取当前sku 最高可以组成几个combo品
                $temp[] = empty($value['qty']) ? 0 : floor(get_value_or_default($inStockProductArr, $value['set_product_id'], 0) / $value['qty']);
            }
            // 根据木桶效应，可以组成combo的最大数量取决于 其中sku组成的最小值
            $inStockProductArr[$productID] = empty($temp) ? 0 : min($temp);
        }

        $num = ($page - 1) * $pageSize + 1;
        foreach ($data as &$item) {
            $item->instock_quantity = get_value_or_default($inStockProductArr, $item->product_id, 0);
            $item->num = $num;
            $num++;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    /**
     * @throws Exception
     */
    public function getAllProductsAndGroup()
    {
        $page = (int)$this->request->get('page',1);
        $pageSize = (int)$this->request->get('pageSize',9999);
        $keywords = trim($this->request->get('keywords','')) ;

        $otherData = ['keywords' => $keywords];

        // 获取该seller_id 关联的所有product
        $data = $this->model->getAllProducts($this->customer_id, $page, $pageSize ,$otherData);
        $total = $this->model->getAllProductsTotal($this->customer_id , $otherData);

        $productIDs = [];
        foreach ($data as $datum) {
            $productIDs[] = $datum->product_id;
        }

        $productGroups = [];
        $this->load->model('account/customerpartner/ProductGroup');
        $productGroupObjs = $this->model_account_customerpartner_ProductGroup->getGroupByProducts($this->customer_id, $productIDs);
        foreach ($productGroupObjs as $productGroupObj) {
            $productGroups[$productGroupObj->product_id][] = [
                'group_id' => $productGroupObj->group_id,
                'group_name' => $productGroupObj->name,
            ];
        }

        $num = ($page - 1) * $pageSize + 1;
        foreach ($data as &$item) {
            $item->groups = get_value_or_default($productGroups, $item->product_id, []);
            $item->short_product_name = truncate($item->product_name, 45);
            // item 中加入oversize信息
            $item->oversize_alarm_price = $this->model_common_product->getAlarmPrice($item->product_id, true);
            $item->num = $num;
            $num++;
        }

        $result['total'] = $total;
        $result['rows'] = $data ;

        $this->response->headers->set('Content-Type','application/json') ;
        return $this->response->json(json_encode($result));
    }

    /**
     * 获取尚未和该 buyer 建立精细化的product
     */
    public function getAllProductsExceptInDelicacy()
    {
        $page = (int)$this->request->get('page',1);
        $pageSize = (int)$this->request->get('pageSize',9999) ;
        $buyerID = (int)$this->request->get('buyer_id',0) ;
        $keywords = trim($this->request->get('keywords','')) ;

        // 如果 存在buyer_id 则排除当前seller和buyer_id已经精细化管理的产品
        $inDelicacyManagementArr = [];
        if ($buyerID != 0) {
            $inDelicacyManagementArr = $this->model->getProductsInDelicacyManagement($this->customer_id, $buyerID);
            $inDelicacyManagementGroupArr = $this->model->getProductsInDelicacyManagementGroup($this->customer_id, $buyerID);
            $inDelicacyManagementArr = array_unique(array_merge($inDelicacyManagementArr, $inDelicacyManagementGroupArr));
        }

        $other_data['keywords'] = $keywords ;
        if ($inDelicacyManagementArr){
            $other_data['product_ids'] = $inDelicacyManagementArr ;
        }

        // 获取该seller_id 关联的所有product
        $data = $this->model->getAllProducts($this->customer_id, $page, $pageSize , $other_data);
        $total = $this->model->getAllProductsTotal($this->customer_id, $other_data);

        $noComboProductIDArr = [];
        $comboProductIDArr = [];
        $responseData = []; // 最终结果
        foreach ($data as $item) {
          /*  if (in_array($item->product_id, $inDelicacyManagementArr)) {  //替换到上面方法 not in
                continue;
            }*/
            // item 中加入oversize信息
            $item->oversize_alarm_price = $this->model_common_product->getAlarmPrice($item->product_id, true);
            //如果是combo
            if ($item->combo_flag == 1) {
                $comboProductIDArr[] = $item->product_id;
            } else {
                $noComboProductIDArr[] = $item->product_id;
            }
            $responseData[] = $item;
        }
        unset($data);

        // 获取非combo的 在库库存
        $inStockQuantityObjs = $this->model->getInStockQuantity($noComboProductIDArr);
        $inStockProductArr = [];
        foreach ($inStockQuantityObjs as $inStockQuantityObj) {
            $inStockProductArr[$inStockQuantityObj->product_id] = $inStockQuantityObj->instock_quantity;
        }

        // combo根据子SKU计算在库库存
        $comboInfoObjs = $this->model->getComboInfo($comboProductIDArr);
        $comboProductArr = [];
        foreach ($comboInfoObjs as $comboInfoObj) {
            $comboProductArr[$comboInfoObj->product_id][] = [
                'qty' => $comboInfoObj->qty,
                'set_product_id' => $comboInfoObj->set_product_id
            ];
        }
        foreach ($comboProductArr as $productID => $comboInfo) {
            $temp = [];
            foreach ($comboInfo as $value) {
                //舍去法取整，获取当前sku 最高可以组成几个combo品
                $temp[] = empty($value['qty']) ? 0 : floor(get_value_or_default($inStockProductArr, $value['set_product_id'], 0) / $value['qty']);
            }
            // 根据木桶效应，可以组成combo的最大数量取决于 其中sku组成的最小值
            $inStockProductArr[$productID] = empty($temp) ? 0 : min($temp);
        }

        $num = ($page - 1) * $pageSize + 1;
        foreach ($responseData as &$item) {
            $item->instock_quantity = get_value_or_default($inStockProductArr, $item->product_id, 0);
            $item->num = $num++;
        }

        $result['total'] = $total ;
        $result['rows'] = $responseData ;

        $this->response->headers->set('Content-Type','application/json') ;
        return $this->response->json(json_encode($result));
    }

    public function getAllBuyers()
    {
        $page = (int)$this->request->get('page',1) ;
        $pageSize = (int)$this->request->get('pageSize',9999) ;
        $keywords = trim($this->request->get('keywords',''));

        $otherData = ['keywords' => $keywords];

        $data = $this->model->getAllBuyers($this->customer_id, $page, $pageSize,$otherData);
        $total = $this->model->getAllBuyersTotal($this->customer_id , $otherData) ;

        $num = ($page - 1) * $pageSize + 1;

        $buyerIds = collect($data)->pluck('buyer_id');
        $buyerCustomerModelMap = Customer::query()->with('buyer')->whereIn('customer_id', $buyerIds)->get()->keyBy('customer_id');
        foreach ($data as &$item) {
            if (in_array($item->customer_group_id, COLLECTION_FROM_DOMICILE)) {
                $item->is_home_pickup = true;
            }else{
                $item->is_home_pickup = false;
            }
            $item->nickname = $item->nickname . '(' . $item->user_number . ')';
            $item->num = $num;
            $item->ex_vat = VATToolTipWidget::widget(['customer' => $buyerCustomerModelMap->get($item->buyer_id), 'is_show_vat' => false])->render();
            $num++;
        }

        $result['rows'] = $data;
        $result['total'] = $total ;

        $this->response->headers->set('Content-Type','application/json') ;
        return $this->response->json(json_encode($result));
    }

    public function getAllBuyersExceptInDelicacy()
    {
        $page = (int)$this->request->get('page',1) ;
        $pageSize = (int)$this->request->get('pageSize',9999) ;
        $productId = (int)$this->request->get('product_id',0) ;

        $inDelicacyManagementArr = [];
        if ($productId) {
            $inDelicacyManagements = $this->model->getBuyersInDelicacyManagement($this->customer_id, $productId);
            $inDelicacyManagementGroups = $this->model->getBuyersInDelicacyManagementGroup($this->customer_id, $productId);
            $inDelicacyManagementArr = array_unique(array_merge($inDelicacyManagementArr, $inDelicacyManagements, $inDelicacyManagementGroups));
        }

        $data = $this->model->getAllBuyers($this->customer_id, $page, $pageSize);
        $responseData = [];
        $num = ($page - 1) * $pageSize + 1;

        $buyerIds = collect($data)->pluck('buyer_id');
        $buyerCustomerModelMap = Customer::query()->with('buyer')->whereIn('customer_id', $buyerIds)->get()->keyBy('customer_id');
        foreach ($data as &$item) {
            if (in_array($item->buyer_id, $inDelicacyManagementArr)) {
                continue;
            }
            $item->nickname = $item->nickname . '(' . $item->user_number . ')';
            $item->num = $num;
            $num++;
            if (in_array($item->customer_group_id, COLLECTION_FROM_DOMICILE)) {
                $item->is_home_pickup = true;
            }else{
                $item->is_home_pickup = false;
            }
            $item->ex_vat = VATToolTipWidget::widget(['customer' => $buyerCustomerModelMap->get($item->buyer_id), 'is_show_vat' => false])->render();
            $responseData[] = $item;
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($responseData));
    }

    public function getAll()
    {
        trim_strings($this->request->get);

        $input['seller_id'] = $this->customer_id;
        $input['page'] = intval(get_value_or_default($this->request->get, 'page', 1));
        $input['pageSize'] = intval(get_value_or_default($this->request->get, 'pageSize', 20));
        if (isset_and_not_empty($this->request->get, 'sku_or_mpn')) {
            $input['search_str'] = $this->request->get['sku_or_mpn'];
        }
        if (isset_and_not_empty($this->request->get, 'buyer_id')) {
            $input['buyer_id'] = $this->request->get['buyer_id'];
        }
        if (isset_and_not_empty($this->request->get, 'product_id')) {
            $input['product_id'] = $this->request->get['product_id'];
        }
        if (isset_and_not_empty($this->request->get, 'buyer_nickname')) {
            $input['buyer_nickname'] = $this->request->get['buyer_nickname'];
        }
        $data = $this->model->getAll($input, 1);
        $bid_delicacy_ids = $this->model->getBidDelicacyRecord($input['seller_id']);
        $num = ($input['page'] - 1) * $input['pageSize'] + 1;

        $buyerIds = collect($data['data'])->pluck('buyer_id');
        $buyerCustomerModelMap = Customer::query()->with('buyer')->whereIn('customer_id', $buyerIds)->get()->keyBy('customer_id');
        foreach ($data['data'] as &$datum) {
            if(in_array($datum->id,$bid_delicacy_ids)){
                $datum->disable_flag = true;
            }
            $datum->num = $num++;
            $datum->ex_vat = VATToolTipWidget::widget(['customer' => $buyerCustomerModelMap->get($datum->buyer_id), 'is_show_vat' => false])->render();
            $datum->buyer_nickname = $datum->buyer_nickname . '(' . $datum->user_number . ')';
            if ($datum->new_effect_time < date('Y-m-d H:i:s')) {
                $datum->new_effect_time = null;
                $datum->new_price = null;
            }
            if (in_array($datum->customer_group_id, COLLECTION_FROM_DOMICILE)) {
                $datum->is_home_pickup = true;
            }else{
                $datum->is_home_pickup = false;
            }
        }
        $this->returnJson(['total' => $data['total'], 'rows' => $data['data']]);
    }

//region update内容
    // 批量设置不可见
    public function batchAddSetInvisibleByProducts()
    {
        $this->checkPOST();

        if (!isset_and_not_empty($this->request->post, 'products') || !is_array($this->request->post['products'])) {
            $response = [
                'error' => 1,
                'msg' => 'Please select at least one product!',
            ];
            $this->returnJson($response);
        }

        if (!isset_and_not_empty($this->request->post, 'buyer_id') || !is_numeric($this->request->post['buyer_id'])) {
            $response = [
                'error' => 1,
                'msg' => 'Please select the buyer!',
            ];
            $this->returnJson($response);
        }

        if (!$this->model->checkIsConnect($this->customer_id, $this->request->post['buyer_id'])) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_established_cooperation'),
            ];
            $this->returnJson($response);
        }

        $products = array_unique($this->request->post['products']);
        $buyer_id = get_value_or_default($this->request->post, 'buyer_id', 0);

        $temp = $this->model->getDelicacyIDByProducts($products, $this->customer_id, $buyer_id);

        $addArr = [];
        $editArr = [];
        $delicacyIDArr = [];
        foreach ($temp as $item) {
            $delicacyIDArr[] = $item->id;
            $editArr[] = $item->product_id;
        }

        foreach ($products as $product) {
            if (!in_array($product, $editArr)) {
                $addArr[] = $product;
            }
        }

        // 批量添加
        $this->model->batchAddInvisible($addArr, [$buyer_id], $this->customer_id, 0);
        // 批量设置
        $this->model->batchSetInvisible($addArr, $this->customer_id, 0);

        $response = [
            'error' => 0,
            'msg' => 'Success!',
        ];
        $this->returnJson($response);
    }

    public function batchAddSetInvisibleByBuyers()
    {
        $this->checkPOST();
        trim_strings($this->request->post);
        if (!isset_and_not_empty($this->request->post, 'buyers') || !is_array($this->request->post['buyers'])) {
            $response = [
                'error' => 1,
                'msg' => 'Please select at least one buyer!',
            ];
            $this->returnJson($response);
        }

        if (!isset_and_not_empty($this->request->post, 'product_id') || !is_numeric($this->request->post['product_id'])) {
            $response = [
                'error' => 1,
                'msg' => 'Please select the product!',
            ];
            $this->returnJson($response);
        }

        $buyers = array_unique($this->request->post['buyers']);
        foreach ($buyers as $buyer) {
            if (!$this->model->checkIsConnect($this->customer_id, $buyer)) {
                $response = [
                    'error' => 1,
                    'msg' => $this->language->get('error_established_cooperation'),
                ];
                $this->returnJson($response);
            }
        }
        $product_id = get_value_or_default($this->request->post, 'product_id', 0);

        /**
         * 根据 seller_id,product_id,buyer_id 判断,
         * 如果已加入到精细化管理的, 则修改
         * 否则, 则新新添加
         */
        $temp = $this->model->getDelicacyIDByBuyers($buyers, $this->customer_id, $product_id);

        $addArr = [];
        $editArr = [];
        $delicacyIDArr = [];
        foreach ($temp as $item) {
            $delicacyIDArr[] = $item->id;
            $editArr[] = $item->buyer_id;
        }

        foreach ($buyers as $buyer) {
            if (!in_array($buyer, $editArr)) {
                $addArr[] = $buyer;
            }
        }

        // 批量添加
        $this->model->batchAddInvisible([$product_id], $addArr, $this->customer_id, 0);
        // 批量设置
        $this->model->batchSetInvisible($addArr, $this->customer_id, 0);

        $response = [
            'error' => 0,
            'msg' => 'Success!',
        ];
        $this->returnJson($response);
    }

    /**
     * 批量设置不可见
     */
    public function batchSetInvisible()
    {
        $this->checkPOST();
        if (!isset_and_not_empty($this->request->post, 'ids') || !is_array($this->request->post)) {
            $response = [
                'error' => 1,
                'msg' => 'Please select at least one!',
            ];
            $this->returnJson($response);
        }
        $ids = [];
        foreach ($this->request->post['ids'] as $id) {
            $id = trim($id);
            if (is_numeric($id)) {
                $ids[] = $id;
            }
        }

        $this->model->batchSetInvisible($ids, $this->customer_id, 0);
        $response = [
            'error' => 0,
            'msg' => 'Success!',
        ];
        $this->returnJson($response);
    }

    public function batchRemove()
    {
        $this->checkPOST();
        if (!isset_and_not_empty($this->request->post, 'ids') || !is_array($this->request->post)) {
            $response = [
                'error' => 1,
                'msg' => 'Please select at least a product/buyer!',
            ];
            $this->returnJson($response);
        }
        $ids = [];
        foreach ($this->request->post['ids'] as $id) {
            $id = trim($id);
            if (is_numeric($id)) {
                $ids[] = $id;
            }
        }

        $this->model->batchRemove($ids, $this->customer_id);
        $response = [
            'error' => 0,
            'msg' => 'Success!',
        ];
        $this->returnJson($response);
    }

    /**
     * 时间条件：
     * 1.生效时间 < 失效时间
     * 2.如果涨价 生效时间 > now + 24hour
     * 3.如果涨价 生效时间 < 失效时间(即 大于 24小时)
     */
    public function edit()
    {
        $this->checkPOST();

        trim_strings($this->request->post);

        $current_time = date('Y-m-d H:i:s');

        !isset_and_not_empty($this->request->post, 'effective_time') && $this->request->post['effective_time'] = date('Y-m-d H:i:s', time());
        !isset_and_not_empty($this->request->post, 'expiration_time') && $this->request->post['expiration_time'] = '9999-01-01 00:00:00';
        $this->request->post['product_display'] = get_value_or_default($this->request->post, 'product_display', 1);

        // 时间不合法
        if ($this->request->post['expiration_time'] < $current_time || $this->request->post['effective_time'] > $this->request->post['expiration_time']) {
            $response = [
                'error' => 1,
                'msg' => 'Please choose the Time of Effect/Failure!',
            ];
            $this->returnJson($response);
        }
        // 产品价格可见 则需要验证 价格的合法性
        if ($this->request->post['product_display'] == 1 && (!isset($this->request->post['delicacy_price']) || !is_numeric($this->request->post['delicacy_price']))) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_enter_buyer_price'),
            ];
            $this->returnJson($response);
        }

        if (!isset_and_not_empty($this->request->post, 'id')) {
            $response = [
                'error' => 1,
                'msg' => 'Error,please try again!',
                'is_flush_table' => 1
            ];
            $this->returnJson($response);
        }

        $data = $this->model->getSingle($this->request->post['id']);
        if (empty($data) || $data->seller_id != $this->customer_id) {
            $response = [
                'error' => 1,
                'msg' => 'Error,please try again!',
                'is_flush_table' => 1
            ];
            $this->returnJson($response);
        }
        $current_price = $data->product_display == 1 ? $data->current_price : $data->basic_price;

        if ($this->request->post['product_display'] == 1) {
            if ($this->request->post['delicacy_price'] > $current_price && strtotime($this->request->post['effective_time']) < (time() + 86400)) {
                $response = [
                    'error' => 1,
                    'msg' => str_replace('#', date('Y-m-d H:i:s', time()), $this->language->get('text_24_price_protect')),
                ];
                $this->returnJson($response);
            }
            // 涨价 触发24小时保护，失效时间也要大于24小时
            if ($this->request->post['delicacy_price'] > $current_price && strtotime($this->request->post['expiration_time']) < (time() + 86400)) {
                $response = [
                    'error' => 1,
                    'msg' => $this->language->get('error_choose_time'),
                ];
                $this->returnJson($response);
            }
        }

        $this->request->post['current_price'] = $current_price;   // 用于更新

        $this->model->edit($this->request->post);
        $data = $this->model->getSingle($this->request->post['id']);
        $response = [
            'error' => 0,
            'msg' => 'Success!',
            'data' => $data
        ];
        $this->returnJson($response);
    }

    /**
     * 添加
     *
     * @var int product_id
     * @var int buyer_id
     * @var string effective_time
     * @var string expiration_time
     * @var int product_display [0,1]
     * @var float delicacy_price
     */
    public function add()
    {
        $this->checkPOST();
        trim_strings($this->request->post);
        $current_timestamp = time();
        $current_time = date('Y-m-d H:i:s', $current_timestamp);

        if (!isset_and_not_empty($this->request->post, 'product_id')) {
            $response = [
                'error' => 1,
                'msg' => 'Please choose one product!',
            ];
            $this->returnJson($response);
        }

        if (!isset_and_not_empty($this->request->post, 'buyer_id')) {
            $response = [
                'error' => 1,
                'msg' => 'Please choose one buyer!',
            ];
            $this->returnJson($response);
        }

        // 验证 buyer/product 是否可用
        if (!$this->model->checkCustomerActive($this->request->post['buyer_id']) || !$this->request->post['product_id']) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        if (!$this->model->checkIsConnect($this->customer_id, $this->request->post['buyer_id'])) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_established_cooperation')
            ];
            $this->returnJson($response);
        }

        !isset_and_not_empty($this->request->post, 'effective_time') && $this->request->post['effective_time'] = $current_time;
        !isset_and_not_empty($this->request->post, 'expiration_time') && $this->request->post['expiration_time'] = '9999-01-01 00:00:00';

        if ($this->request->post['expiration_time'] < $current_time || $this->request->post['effective_time'] > $this->request->post['expiration_time']) {
            $response = [
                'error' => 1,
                'msg' => 'Please choose the Time of Effect/Failure!',
            ];
            $this->returnJson($response);
        }

        $this->request->post['product_display'] = get_value_or_default($this->request->post, 'product_display', 1);

        if ($this->request->post['product_display'] == 1) {
            if (!isset($this->request->post['delicacy_price']) || !is_numeric($this->request->post['delicacy_price'])) {
                $response = [
                    'error' => 1,
                    'msg' => $this->language->get('error_enter_buyer_price')
                ];
                $this->returnJson($response);
            }

            $productInfo = $this->model->getProductInfo($this->request->post['product_id'], $this->customer_id);

            if (empty($productInfo)) {
                $response = [
                    'error' => 1,
                    'msg' => 'Please choose one product!',
                ];
                $this->returnJson($response);
            }

            if ($productInfo->price < $this->request->post['delicacy_price'] && (strtotime($this->request->post['effective_time']) < ($current_timestamp + 86400))) {
                $response = [
                    'error' => 1,
                    'msg' => str_replace('#', date('Y-m-d H:i:s', $current_timestamp), $this->language->get('text_24_price_protect')),
                ];
                $this->returnJson($response);
            }

            // 涨价 触发24小时保护，失效时间需要大于 当前的24小时以后
            if ($productInfo->price < $this->request->post['delicacy_price'] && (strtotime($this->request->post['expiration_time']) < ($current_timestamp + 86400))) {
                $response = [
                    'error' => 1,
                    'msg' => $this->language->get('error_choose_time'),
                ];
                $this->returnJson($response);
            }
            $this->request->post['basic_price'] = $productInfo->price;
        }

        !isset_and_not_empty($this->request->post, 'delicacy_price') && $this->request->post['delicacy_price'] = 0;

        /**
         * 如果已存在，则给出提示
         */
        $isExist = $this->model->checkIsExists($this->customer_id, $this->request->post['buyer_id'], $this->request->post['product_id']);
        if ($isExist) {
            $response = [
                'error' => 1,
                'msg' => 'You\'ve already added this record!',
            ];
            $this->returnJson($response);
//            $this->model->batchRemove([$isExist], $this->customer_id);
        }

        $id = $this->model->add($this->request->post, $this->customer_id);
        $data = $this->model->getSingle($id);
        $data->num = $id;
        $response = [
            'error' => 0,
            'msg' => 'Success!',
            'data' => $data
        ];
        $this->returnJson($response);
    }

    /**
     * Product tab 批量添加 buyer
     */
    public function batchAdd()
    {
        $this->checkPOST();
        trim_strings($this->request->post);
        $current_timestamp = time();
        $current_time = date('Y-m-d H:i:s', $current_timestamp);

        // Check Param: product_id
        if (!isset_and_not_empty($this->request->post, 'product_id')) {
            $response = [
                'error' => 1,
                'msg' => 'Please choose one product!',
            ];
            $this->returnJson($response);
        }

        // Check Param: buyers
        if (!isset_and_not_empty($this->request->post, 'buyers') || !is_array($this->request->post['buyers']) || empty($this->request->post['buyers'])) {
            $response = [
                'error' => 1,
                'msg' => 'Please choose one buyer!',
            ];
            $this->returnJson($response);
        }

        // 获取当前 product 信息
        $productInfo = $this->model->getProductInfo($this->request->post['product_id'], $this->customer_id);
        if (empty($productInfo)) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }
        $freight = $productInfo->freight ?: 0;

        $buyerIDs = [];
        foreach ($this->request->post['buyers'] as &$buyer) {
            //Check Param: buyer_id
            if (!isset_and_not_empty($buyer, 'buyer_id') || !$this->model->checkCustomerActive($buyer['buyer_id'])) {
                $response = [
                    'error' => 1,
                    'msg' => $this->language->get('error_common'),
                ];
                $this->returnJson($response);
                break;
            }
            //Check Param: modify_price
            if (!isset($buyer['modify_price']) || !is_numeric($buyer['modify_price'])) {
                $response = [
                    'error' => 1,
                    'msg' => $this->language->get('error_enter_buyer_price')
                ];
                $this->returnJson($response);
            }

            //Check Param: effective_time & expiration_time
            //如果 effective_time & expiration_time 均不为空, 则 effective_time < expiration_time 要成立
            if (
                isset_and_not_empty($buyer, 'effective_time') &&
                isset_and_not_empty($buyer, 'expiration_time') &&
                ($buyer['effective_time'] < $current_time || $buyer['effective_time'] > $buyer['expiration_time'])
            ) {
                $response = [
                    'error' => 1,
                    'msg' => 'Please choose the Time of Effect/Failure again!',
                ];
                $this->returnJson($response);
            }

            /**
             * 如果价格调高, 则需要24小时后生效。
             *  1.如果 effective_time 不为空 验证 effective_time 是否大于 当前24小时
             *  2.如果 effective_time 为空 ，则 effective_time = now + 24hour
             * 如果价格不调高：
             *  如果为空，则 effective_time = now
             */
            if ($productInfo->price < $buyer['modify_price']) {
                if (isset_and_not_empty($buyer, 'effective_time') && strtotime($buyer['effective_time']) < ($current_timestamp + 86400)) {
                    $response = [
                        'error' => 1,
                        'msg' => $this->language->get('error_choose_time'),
                    ];
                    $this->returnJson($response);
                }
                !isset_and_not_empty($buyer, 'effective_time') && $buyer['effective_time'] = date('Y-m-d H:i:s', $current_timestamp + 86400);
            } else {
                !isset_and_not_empty($buyer, 'effective_time') && $buyer['effective_time'] = $current_time;
            }
            !isset_and_not_empty($buyer, 'expiration_time') && $buyer['expiration_time'] = '9999-01-01 00:00:00';

            $buyer['basic_price'] = $productInfo->price;
            $isExist = $this->model->checkIsExists($this->customer_id, $buyer['buyer_id'], $this->request->post['product_id']);
            if ($isExist) {
                $response = [
                    'error' => 1,
                    'msg' => 'You\'ve already added this record!',
                ];
                $this->returnJson($response);
//            $this->model->batchRemove([$isExist], $this->customer_id);
            }
            $buyerIDs[] = $buyer['buyer_id'];
        }
        if (!$this->model->checkIsConnectByBuyers($this->customer_id, array_unique($buyerIDs))) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }
        unset($buyer);
        foreach ($this->request->post['buyers'] as $buyer) {
            $temp = [
                'buyer_id' => $buyer['buyer_id'],
                'product_id' => $this->request->post['product_id'],
                'product_display' => 1,
                'delicacy_price' => $buyer['modify_price'],
                'effective_time' => $buyer['effective_time'],
                'expiration_time' => $buyer['expiration_time'],
                'basic_price' => $buyer['basic_price'],
            ];
            $this->model->add($temp, $this->customer_id);
        }
        $response = [
            'error' => 0,
            'msg' => 'Success!',
        ];
        $this->returnJson($response);
    }

    public function batchSetPrice()
    {
        $this->checkPOST();
        trim_strings($this->request->post);
        if (!isset($this->request->post['delicacy_price']) || !is_numeric($this->request->post['delicacy_price'])) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_enter_buyer_price'),
            ];
            $this->returnJson($response);
        }

        $current_timestamp = time();
        $current_time = date('Y-m-d H:i:s', $current_timestamp);

        !isset_and_not_empty($this->request->post, 'effective_time') && $this->request->post['effective_time'] = $current_time;
        !isset_and_not_empty($this->request->post, 'expiration_time') && $this->request->post['expiration_time'] = '9999-01-01 00:00:00';

        /**
         * @var string $after_24_hours 当前24小时之后
         * @var string $effective_time_hour 生效时间的年月日小时部分(分秒为0)
         */
        $after_24_hours = ($current_time == date('Y-m-d H:00:00'))
            ? date('Y-m-d H:00:00', strtotime('+1 day', $current_timestamp))
            : date('Y-m-d H:00:00', strtotime('+1 day +1 hour', $current_timestamp));
        $effective_time_hour = $this->request->post['effective_time'] == date('Y-m-d H:00:00', strtotime($this->request->post['effective_time']))
            ? $this->request->post['effective_time']
            : date('Y-m-d H:00:00', strtotime($this->request->post['effective_time']) + 3600);

        if ($this->request->post['expiration_time'] < $current_time || $this->request->post['effective_time'] > $this->request->post['expiration_time']) {
            $response = [
                'error' => 1,
                'msg' => 'Please choose the Time of Effect/Failure!',
            ];
            $this->returnJson($response);
        }

        if (!isset_and_not_empty($this->request->post, 'data')) {
            $response = [
                'error' => 1,
                'msg' => 'Please select at least one buyer!',
            ];
            $this->returnJson($response);
        }

        $idArr = [];
        foreach ($this->request->post['data'] as $id) {
            if (is_numeric($id) && !in_array($id, $idArr)) {
                $idArr[] = $id;
            }
        }

        $info = $this->model->getInfoByIDArr($idArr, $this->customer_id);
        $updateArr = [];
        foreach ($info as $obj) {
            $updateTemp = [
                'id' => $obj->id,
                'expiration_time' => $this->request->post['expiration_time'],
                'current_price' => $obj->product_display == 1 ? $obj->current_price : $obj->basic_price,
                'delicacy_price' => $this->request->post['delicacy_price'],
                'basic_price' => $obj->basic_price,

            ];
            if ($obj->current_price < $this->request->post['delicacy_price']) {
                $updateTemp['effective_time'] = max([$effective_time_hour, $after_24_hours]);
                // 如果涨价后的生效时间大于生效时间则报错
                if ($updateTemp['effective_time'] >= $this->request->post['expiration_time']) {
                    $response = [
                        'error' => 1,
                        'msg' => 'Because of the 24-hour price protection mechanism. The time of effect should be greater than ' . $updateTemp['effective_time'],
                    ];
                    $this->returnJson($response);
                }
            } else {
                $updateTemp['effective_time'] = $this->request->post['effective_time'];
            }
            $updateArr[] = $updateTemp;
        }

        $this->model->batchSetPrice($updateArr);
        $response = [
            'error' => 0,
            'msg' => 'Success!',
        ];
        $this->returnJson($response);
    }

    public function batchAddBySetPrice()
    {
        $this->request->request;
        $this->checkPOST();
        trim_strings($this->request->post);

        if (!isset($this->request->post['delicacy_price']) || !is_numeric($this->request->post['delicacy_price'])) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_enter_buyer_price'),
            ];
            $this->returnJson($response);
        }

        $current_time = date('Y-m-d H:i:s');
        $after_24_hours = date('Y-m-d H:i:s', time() + 86400);
        !isset_and_not_empty($this->request->post, 'effective_time') && $this->request->post['effective_time'] = $current_time;
        !isset_and_not_empty($this->request->post, 'expiration_time') && $this->request->post['expiration_time'] = '9999-01-01 00:00:00';

        if ($this->request->post['effective_time'] > $this->request->post['expiration_time']) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_choose_time'),
            ];
            $this->returnJson($response);
        }


        if (!isset_and_not_empty($this->request->post, 'product_id')) {
            $response = [
                'error' => 1,
                'Please select a product as first! '
            ];
            $this->returnJson($response);
        }

        if (empty($this->request->post['data'])) {
            $response = [
                'error' => 1,
                'msg' => 'Please select at least one buyer!',
            ];
            $this->returnJson($response);
        }

        $productInfo = $this->model->getProductInfo($this->request->post['product_id'], $this->customer_id);
        if (empty($productInfo)) {
            $response = [
                'error' => 1,
                'Please select a product as first! '
            ];
            $this->returnJson($response);
        }

        if ($productInfo->price < $this->request->post['delicacy_price']) {
            $this->request->post['effective_time'] = max([$this->request->post['effective_time'], $after_24_hours]);
        }

        $buyerIDs = [];
        foreach ($this->request->post['data'] as $buyerID) {
            if (!$this->model->checkIsConnect($this->customer_id, $buyerID)) {
                $response = [
                    'error' => 1,
                    'msg' => $this->language->get('error_established_cooperation'),
                ];
                $this->returnJson($response);
            }
            if (is_numeric($buyerID) && !in_array($buyerID, $buyerIDs)) {
                $buyerIDs[] = $buyerID;
            }
        }

        $buyerObjs = $this->model->getBuyersByIDs($buyerIDs, $this->customer_id);

        if (empty(obj2array($buyerObjs))) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_choose_buyer'),
            ];
            $this->returnJson($response);
        }

        $input = [
            'delicacy_price' => $this->request->post['delicacy_price'],
            'effective_time' => $this->request->post['effective_time'],
            'expiration_time' => $this->request->post['expiration_time'],
            'product_id' => $productInfo->product_id,
            'basic_price' => $productInfo->price
        ];

        foreach ($buyerObjs as $buyerObj) {
            $input['data'][] = $buyerObj->buyer_id;
        }

        $this->model->batchAddBySetPrice($input, $this->customer_id);

        $response = [
            'error' => 0,
            'msg' => 'Success!',
        ];
        $this->returnJson($response);
    }

//endregion

//region Download

    public function download()
    {
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis",time()),'Ymd');
        //12591 end
        $fileName = 'BuyersPriceSetting' . $time . '.csv';
        $columns = $this->getDownloadColumns(get_value_or_default($this->request->get, 'type', 'buyer'));
        $downloadData = $this->model->getDownloadData($this->customer_id,get_value_or_default($this->request->get, 'type', 'buyer'));
        $temp = array();
        foreach ($downloadData as $key => $obj) {
            foreach ($columns as $column) {
                if (is_string($column)) {
                    $temp[$key][] = $obj->{$column};
                } elseif (is_array($column) && isset($column['add'])) {
                    if (isset($column['is_null']) && is_null($obj->{$column['is_null']['key']})) {
                        $temp[$key][] = $column['is_null']['value'];
                    } else {
                        $t = 0;
                        foreach ($column['add'] as $add) {
                            $t = bcadd($obj->{$add}, $t, $this->precision);
                        }
                        $temp[$key][] = $t;
                    }
                }
            }
        }
        //12591 B2B记录各国别用户的操作时间
        outputCsv($fileName,array_keys($columns),$temp,$this->session);
        //12591 end
    }

    /**
     * @param string $type
     * @return array
     */
    public function getDownloadColumns(string $type = 'buyer')
    {

        if ($type == 'buyer') {
            return [
                'Name' => 'buyer_nickname',
                'UserNumber' => 'user_number',
                'Buyer Type' => 'buyer_type',
                'Buyer Group' => 'buyer_group_name',
                'Item Code' => 'item_code',
                'MPN' => 'mpn',
                'Product Name' => 'product_name',
                'Product Group' => 'product_group_name',
//                'Curt. Freight' => 'freight',
                'Current Price' => 'basic_price',
//                'Curt. Dropshipping Price' => [
//                    'add' => ['freight', 'basic_price']
//                ],

//                'Mod. Home Pickup Price' => [
//                    'add' => ['new_price'],
//                    'is_null' => ['key' => 'new_price', 'value' => '']
//                ],
//                'Ref. Dropshipping Price' => [
//                    'add' => ['freight', 'new_price'],
//                    'is_null' => ['key' => 'new_price', 'value' => '']
//                ],
//                'Mod. Home Pickup Price Time of Effect' => 'new_effect_time',

                'Curt. Exclusive Price' => 'current_price',
//                'Curt. Exclusive Dropshipping Price' => [
//                    'add' => ['freight', 'current_price']
//                ],

                'Mod. Exclusive Price' => 'delicacy_price',
//                'Ref. Exclusive Dropshipping Price' => [
//                    'add' => ['freight', 'delicacy_price']
//                ],

                'Time of Effect' => 'effective_time',
                'Time of Failure' => 'expiration_time',
                'Visibility' => 'visibility'
            ];
        } else {
            return [
                'Item Code' => 'item_code',
                'MPN' => 'mpn',
                'Product Name' => 'product_name',
                'Product Group' => 'product_group_name',
//                'Curt. Freight' => 'freight',
                'Current Price' => 'basic_price',
//                'Curt. Dropshipping Price' => [
//                    'add' => ['freight', 'basic_price']
//                ],

//                'Mod. Home Pickup Price' => [
//                    'add' => ['new_price'],
//                    'is_null' => ['key' => 'new_price', 'value' => '']
//                ],
//                'Ref. Dropshipping Price' => [
//                    'add' => ['freight', 'new_price'],
//                    'is_null' => ['key' => 'new_price', 'value' => '']
//                ],
//                'Mod. Home Pickup Price Time of Effect' => 'new_effect_time',

                'Name' => 'buyer_nickname',
                'UserNumber' => 'user_number',
                'Buyer Type' => 'buyer_type',
                'Buyer Group' => 'buyer_group_name',

                'Curt. Exclusive Price' => 'current_price',
//                'Curt. Exclusive Dropshipping Price' => [
//                    'add' => ['freight', 'current_price']
//                ],

                'Mod. Exclusive Price' => 'delicacy_price',
//                'Ref. Exclusive Dropshipping Price' => [
//                    'add' => ['freight', 'delicacy_price']
//                ],

                'Time of Effect' => 'effective_time',
                'Time of Failure' => 'expiration_time',
                'Visibility' => 'visibility'
            ];
        }
    }

    public function downloadTemplate()
    {
        /**
         * 1->内部; 2->外部
         * 内部：文件固定
         * 外部:
         */
        if ($this->customer->getAccountType() == 1) {
            $file = 'FreightTemplate.xlsx';
            $path = DIR_DOWNLOAD . $file;
        } else {
            $fileArr = $this->model->getDownloadTemplate();
            if (!isset($fileArr['path'])) {
                echo "The file does not exist, please contact the platform customer service representative.";
                return;
            }
            $file = $fileArr['file'];
            $path = $fileArr['path'];
        }

        if (!file_exists($path)) {
            echo "The file does not exist, please contact the platform customer service representative.";
            return;
        }
        $type = filetype($path);
        header("Content-Type: $type");
        header("Content-Disposition: attachment; filename=\"" . $file . "\"");
        header('Pragma: no-cache');
        header('Expires: 0');

        set_time_limit(0);
        readfile($path);
    }

//endregion

    /**
     * @param array $response
     */
    private function returnJson(array $response)
    {
        $this->response->returnJson($response);
    }
}
