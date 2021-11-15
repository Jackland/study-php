<?php

/**
 * Created by IntelliJ IDEA.
 * User: xxl
 * Date: 2019/8/13
 * Time: 20:20
 * @property ModelAccountBillSalesPurchaseBill $model_account_bill_sales_purchase_bill
 * @property ModelAccountRmaManagement $model_account_rma_management
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountBillSalesPurchaseBill extends Controller
{
    private $customer_id = null;
    private $isPartner = false;

    /**
     * @var ModelAccountBillSalesPurchaseBill $model
     */
    private $model;

    /**
     * @param Registry $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/wishlist', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->model('account/bill/sales_purchase_bill');
        $this->model = $this->model_account_bill_sales_purchase_bill;

        $this->load->language('account/bill/sales_purchase_bill');
    }

    public function index()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/order', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $this->getSalesAndPurchaseBillsList();
    }

    public function getSalesAndPurchaseBillsList()
    {
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->language('common/cwf');
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => 'Billing Management',
            'href' => $this->url->link('account/bill/sales_purchase_bill', '', true)
        );

        $url="";
        if (isset($this->request->get['filter_sales_order_id']) && $this->request->get['filter_sales_order_id'] != '') {
            $data['filter_sales_order_id'] = $this->request->get['filter_sales_order_id'];
            $filter_sales_order_id = $this->request->get['filter_sales_order_id'];
            $url .= "&filter_sales_order_id=" . $filter_sales_order_id;
        } else {
            $filter_sales_order_id = null;
        }
        if (isset($this->request->get['filter_sales_order_status']) && $this->request->get['filter_sales_order_status'] != '') {
            $data['filter_sales_order_status'] = $this->request->get['filter_sales_order_status'];
            $filter_sales_order_status = $this->request->get['filter_sales_order_status'];
            $url .= "&filter_sales_order_status=" . $filter_sales_order_status;
        } else {
            $filter_sales_order_status = null;
        }

        if (isset($this->request->get['filter_create_time'])) {
            $data['filter_create_time'] = $this->request->get['filter_create_time'];
            $filter_create_time = $this->request->get['filter_create_time'];
            $url .= "&filter_create_time=" . $filter_create_time;
        } else {
            $filter_create_time = null;
        }
        if (isset($this->request->get['filter_create_time_end'])) {
            $data['filter_create_time_end'] = $this->request->get['filter_create_time_end'];
            $filter_create_time_end = $this->request->get['filter_create_time_end'];
            $url .= "&filter_create_time_end=" . $filter_create_time_end;
        } else {
            $filter_create_time_end = null;
        }

        if (isset($this->request->get['filter_purchase_order_id'])) {
            $data['filter_purchase_order_id'] = $this->request->get['filter_purchase_order_id'];
            $filter_purchase_order_id = $this->request->get['filter_purchase_order_id'];
            $url .= "&filter_purchase_order_id=" . $filter_purchase_order_id;
        } else {
            $filter_purchase_order_id = null;
        }

        if (isset($this->request->get['filter_store'])) {
            $data['filter_store'] = $this->request->get['filter_store'];
            $filter_store = $this->request->get['filter_store'];
            $url .= "&filter_store=" . $filter_store;
        } else {
            $filter_store = null;
        }

        if (isset($this->request->get['filter_purchase_order_date'])) {
            $data['filter_purchase_order_date'] = $this->request->get['filter_purchase_order_date'];
            $filter_purchase_order_date = $this->request->get['filter_purchase_order_date'];
            $url .= "&filter_purchase_order_date=" . $filter_purchase_order_date;
        } else {
            $filter_purchase_order_date = null;
        }
        if (isset($this->request->get['filter_purchase_order_date_end'])) {
            $data['filter_purchase_order_date_end'] = $this->request->get['filter_purchase_order_date_end'];
            $filter_purchase_order_date_end = $this->request->get['filter_purchase_order_date_end'];
            $url .= "&filter_purchase_order_date_end=" . $filter_purchase_order_date_end;
        } else {
            $filter_purchase_order_date_end = null;
        }


        if (isset($this->request->get['filter_item_code'])) {
            $data['filter_item_code'] = $this->request->get['filter_item_code'];
            $filter_item_code = $this->request->get['filter_item_code'];
            $url .= "&filter_item_code=" . $filter_item_code;
        } else {
            $filter_item_code = null;
        }

        if (isset($this->request->get['filter_order_type'])) {
            $data['filter_order_type'] = $this->request->get['filter_order_type'];
            $filter_order_type = $this->request->get['filter_order_type'];
            $url .= "&filter_order_type=" . $filter_order_type;
        } else {
            $filter_order_type = 1;
            $data['filter_order_type'] = 1;
            $url .= "&filter_order_type=" . $filter_order_type;
        }
        if (isset($this->request->get['filter_relationship_type'])) {
            $data['filter_relationship_type'] = $this->request->get['filter_relationship_type'];
            $filter_relationship_type = $this->request->get['filter_relationship_type'];
            $url .= "&filter_relationship_type=" . $filter_relationship_type;
        } else {
            $filter_relationship_type = 1;
            $data['filter_relationship_type'] = 1;
            $url .= "&filter_relationship_type=" . $filter_relationship_type;
        }
        $this->load->model('account/rma_management');
        $this->load->model('tool/image');
        // 获取Store下拉选
        $customer_id = $this->customer->getId();
        $data['stores'] = $this->model_account_rma_management->getStoreByBuyerId($customer_id);
        //获取销售订单的状态
        $data['salesOrderStatus'] = $this->model->getSalesOrderStatus($customer_id);
        //判断该用户的国别
        $data['showServiceFee'] = $this->customer->showServiceFee($customer_id);

        $page = $this->request->get['page'] ?? 1;
        $perPage = get_value_or_default($this->request->request,'page_limit',20);
        $filter_data = array(
            "filter_sales_order_id" => $filter_sales_order_id,
            "filter_sales_order_status" => $filter_sales_order_status,
            "filter_create_time" => $filter_create_time,
            "filter_create_time_end" => $filter_create_time_end,
            "filter_purchase_order_id" => $filter_purchase_order_id,
            "filter_store" => $filter_store,
            "filter_purchase_order_date" => $filter_purchase_order_date,
            "filter_purchase_order_date_end" => $filter_purchase_order_date_end,
            "filter_item_code" => $filter_item_code,
            "filter_order_type" => $filter_order_type,
            "filter_relationship_type" => $filter_relationship_type,
            "page_num" => $page,
            "page_limit" => $perPage
        );
        $bill = array();
        $this->load->model('catalog/product');
        if ($filter_order_type == 1 && $filter_relationship_type == 2) {
            $total = $this->model->getSaleBillsTotal($filter_data, $this->customer->getId());
            $result = $this->model->getSaleBills($filter_data, $this->customer->getId());
            foreach ($result as $key => $value) {
                $tag_array = $this->model_catalog_product->getProductSpecificTagByOrderLineId($value['id']);
                $tags = array();
                if(isset($tag_array)){
                    foreach ($tag_array as $tag){
                        if(isset($tag['origin_icon']) && !empty($tag['origin_icon'])){
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip"  class="'.$tag['class_style']. '" title="'.$tag['description']. '" style="padding-left: 1px" src="'.$img_url.'">';
                        }
                    }
                }
                $bill[] = array(
                    'sales_order_id' => $value['sales_order_id'],
                    'ship_name' => app('db-aes')->decrypt($value['ship_name']),
                    'ship_address' => $value['ship_address'],
                    'create_time' => $value['create_time'],
                    'item_code' => $value['item_code'],
                    'quantity' => $value['qty'],
                    'sales_order_status' => $value['DicValue'],
                    'tag' =>$tags
                );
            }
        } else if ($filter_order_type == 2 && $filter_relationship_type == 2) {
            $total = $this->model->getPurchaseBillsTotal($filter_data, $this->customer->getId());
            $result = $this->model->getPurchaseBills($filter_data, $this->customer->getId());
            $data['isEuropeCountry'] = $this->country->isEuropeCountry($this->customer->getCountryId());
            foreach ($result as $key => $value) {
                $tag_array = $this->model_catalog_product->getProductSpecificTag($value['product_id']);
                $tags = array();
                if(isset($tag_array)){
                    foreach ($tag_array as $tag){
                        if(isset($tag['origin_icon']) && !empty($tag['origin_icon'])){
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip" class="'.$tag['class_style']. '" title="'.$tag['description']. '" style="padding-left: 1px" src="'.$img_url.'">';
                        }
                    }
                }
                $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
                if($isCollectionFromDomicile){
                    $freight_per = $value['package_fee'];
                }else{
                    $freight_per = $value['freight_per']+$value['package_fee'];
                }
                if($value['freight_difference_per']>0) {
                    $value['freight_diff'] = true;
                    $value['tips_freight_difference_per'] = str_replace(
                        '_freight_difference_per_',
                        $this->currency->formatCurrencyPrice($value['freight_difference_per'], $this->session->data['currency']),
                        $this->language->get('tips_freight_difference_per')
                    );
                }else{
                    $value['freight_diff'] = false;
                }
                if($value['quoteFlag']) {
                    $amount_total = round(($value['price']+ $freight_per+$value['transaction_fee']) * $value['quantity'], 2);
                }else{
                    $amount_total = round(($value['price'] + $value['service_fee_per'] + $freight_per+$value['transaction_fee']) * $value['quantity'], 2);
                }
                if($data['isEuropeCountry'] && $value['quoteFlag']){
                    $unit_price = $this->currency->formatCurrencyPrice($value['op_price']-$value['amount_price_per'], $this->session->data['currency']);
                    $service_fee = $this->currency->formatCurrencyPrice($value['service_fee_per']-$value['amount_service_fee_per'], $this->session->data['currency']);
                }else{
                    $unit_price = $this->currency->formatCurrencyPrice($value['price'], $this->session->data['currency']);
                    $service_fee = $this->currency->formatCurrencyPrice($value['service_fee_per'], $this->session->data['currency']);
                }
                $amount_total = $amount_total - $value['coupon_amount'] - $value['campaign_amount'];
                $bill[] = array(
                    'item_code' => $value['item_code'],
                    'unit_price' => $unit_price,
                    'service_fee' => $service_fee,
                    'transaction_fee' => $this->currency->formatCurrencyPrice($value['transaction_fee']*$value['quantity'], $this->session->data['currency']),
                    'quantity' => $value['quantity'],
                    'amount_total' => $this->currency->formatCurrencyPrice($amount_total, $this->session->data['currency']),
                    'screenname' => $value['screenname'],
                    'purchase_order_id' => $value['order_id'],
                    'delivery_type' => $value['delivery_type'],
                    'purchase_order_date' => $value['date_modified'],
                    'freight_per' => $this->currency->formatCurrencyPrice($freight_per, $this->session->data['currency']),
                    'tag' =>$tags,
                    'freight_diff' => $value['freight_diff'],
                    'tips_freight_difference_per' => isset($value['tips_freight_difference_per']) ?? ''
                );
            }
        } else {
            $total = $this->model->getSaleAndPurchaseBillsTotal($filter_data, $this->customer->getId());
            $result = $this->model->getSaleAndPurchaseBills($filter_data, $this->customer->getId());
            $data['isEuropeCountry'] = $this->country->isEuropeCountry($this->customer->getCountryId());
            foreach ($result as $key => $value) {
                $tag_array = $this->model_catalog_product->getProductSpecificTag($value['product_id']);
                $tags = array();
                if(isset($tag_array)){
                    foreach ($tag_array as $tag){
                        if(isset($tag['origin_icon']) && !empty($tag['origin_icon'])){
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip"  class="'.$tag['class_style']. '" title="'.$tag['description']. '" style="padding-left: 1px" src="'.$img_url.'">';
                        }
                    }
                }
                $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
                if($isCollectionFromDomicile){
                    $freight_per = $value['package_fee'];
                }else{
                    $freight_per = $value['freight_per']+$value['package_fee'];
                }
                if($value['freight_difference_per']>0) {
                    $value['freight_diff'] = true;
                    $value['tips_freight_difference_per'] = str_replace(
                        '_freight_difference_per_',
                        $this->currency->formatCurrencyPrice($value['freight_difference_per'], $this->session->data['currency']),
                        $this->language->get('tips_freight_difference_per')
                    );
                }else{
                    $value['freight_diff'] = false;
                }

                if($value['quoteFlag']) {
                    $amount_total = round(($value['price']+ $freight_per+$value['transaction_fee']) * $value['qty'], 2);
                }else{
                    $amount_total = round(($value['price'] + $value['service_fee_per'] + $freight_per+$value['transaction_fee']) * $value['qty'], 2);
                }
                if($data['isEuropeCountry'] && $value['quoteFlag']){
                    $unit_price = $this->currency->formatCurrencyPrice($value['op_price']-$value['amount_price_per'], $this->session->data['currency']);
                    $service_fee = $this->currency->formatCurrencyPrice($value['service_fee_per']-$value['amount_service_fee_per'], $this->session->data['currency']);
                }else{
                    $unit_price = $this->currency->formatCurrencyPrice($value['price'], $this->session->data['currency']);
                    $service_fee = $this->currency->formatCurrencyPrice($value['service_fee_per'], $this->session->data['currency']);
                }
                $amount_total = $amount_total - $value['coupon_amount'] - $value['campaign_amount'];
                $bill[] = array(
                    'sales_order_id' => $value['sales_order_id'],
                    'ship_name' => app('db-aes')->decrypt($value['ship_name']),
                    'ship_address' => $value['ship_address'],
                    'create_time' => $value['create_time'],
                    'item_code' => $value['item_code'],
                    'unit_price' => $unit_price,
                    'service_fee' => $service_fee,
                    'transaction_fee' => $this->currency->formatCurrencyPrice($value['transaction_fee'], $this->session->data['currency']),
                    'quantity' => $value['qty'],
                    'amount_total' => $this->currency->formatCurrencyPrice($amount_total, $this->session->data['currency']),
                    'screenname' => $value['screenname'],
                    'purchase_order_id' => $value['order_id'],
                    'delivery_type' => $value['delivery_type'],
                    'purchase_order_date' => $value['date_modified'],
                    'sales_order_status' => $value['DicValue'],
                    'freight_per' => $this->currency->formatCurrencyPrice($freight_per, $this->session->data['currency']),
                    'tag' => $tags,
                    'freight_diff' => $value['freight_diff'],
                    'tips_freight_difference_per' => $value['tips_freight_difference_per'] ?? ''
                );
            }
        }
        $data['orders'] = $bill;
        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $perPage;
        $pagination->url = $this->url->link('account/bill/sales_purchase_bill'.$url, '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page - 1) * $perPage) + 1 : 0, ((($page - 1) * $perPage) > ($total - $perPage)) ? $total : ((($page - 1) * $perPage) + $perPage), $total, ceil($total / $perPage));
        $data['continue'] = $this->url->link('account/account', '', true);

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $this->response->setOutput($this->load->view('account/bill/sales_purchase_bill', $data));
    }

    public function download()
    {
        if (isset($this->request->get['filter_sales_order_id']) && $this->request->get['filter_sales_order_id'] != '') {
            $data['filter_sales_order_id'] = $this->request->get['filter_sales_order_id'];
            $filter_sales_order_id = $this->request->get['filter_sales_order_id'];
        } else {
            $filter_sales_order_id = null;
        }
        if (isset($this->request->get['filter_sales_order_status']) && $this->request->get['filter_sales_order_status'] != '') {
            $data['filter_sales_order_status'] = $this->request->get['filter_sales_order_status'];
            $filter_sales_order_status = $this->request->get['filter_sales_order_status'];
        } else {
            $filter_sales_order_status = null;
        }

        if (isset($this->request->get['filter_create_time'])) {
            $data['filter_create_time'] = $this->request->get['filter_create_time'];
            $filter_create_time = $this->request->get['filter_create_time'];
        } else {
            $filter_create_time = null;
        }
        if (isset($this->request->get['filter_create_time_end'])) {
            $data['filter_create_time_end'] = $this->request->get['filter_create_time_end'];
            $filter_create_time_end = $this->request->get['filter_create_time_end'];
        } else {
            $filter_create_time_end = null;
        }

        if (isset($this->request->get['filter_purchase_order_id'])) {
            $data['filter_purchase_order_id'] = $this->request->get['filter_purchase_order_id'];
            $filter_purchase_order_id = $this->request->get['filter_purchase_order_id'];
        } else {
            $filter_purchase_order_id = null;
        }

        if (isset($this->request->get['filter_store'])) {
            $data['filter_store'] = $this->request->get['filter_store'];
            $filter_store = $this->request->get['filter_store'];
        } else {
            $filter_store = null;
        }

        if (isset($this->request->get['filter_purchase_order_date'])) {
            $data['filter_purchase_order_date'] = $this->request->get['filter_purchase_order_date'];
            $filter_purchase_order_date = $this->request->get['filter_purchase_order_date'];
        } else {
            $filter_purchase_order_date = null;
        }
        if (isset($this->request->get['filter_purchase_order_date_end'])) {
            $data['filter_purchase_order_date_end'] = $this->request->get['filter_purchase_order_date_end'];
            $filter_purchase_order_date_end = $this->request->get['filter_purchase_order_date_end'];
        } else {
            $filter_purchase_order_date_end = null;
        }


        if (isset($this->request->get['filter_item_code'])) {
            $data['filter_item_code'] = $this->request->get['filter_item_code'];
            $filter_item_code = $this->request->get['filter_item_code'];
        } else {
            $filter_item_code = null;
        }

        if (isset($this->request->get['filter_order_type'])) {
            $data['filter_order_type'] = $this->request->get['filter_order_type'];
            $filter_order_type = $this->request->get['filter_order_type'];
        } else {
            $filter_order_type = null;
        }
        if (isset($this->request->get['filter_relationship_type'])) {
            $data['filter_relationship_type'] = $this->request->get['filter_relationship_type'];
            $filter_relationship_type = $this->request->get['filter_relationship_type'];
        } else {
            $filter_relationship_type = null;
        }
        $filter_data = array(
            "filter_sales_order_id" => $filter_sales_order_id,
            "filter_sales_order_status" => $filter_sales_order_status,
            "filter_create_time" => $filter_create_time,
            "filter_create_time_end" => $filter_create_time_end,
            "filter_purchase_order_id" => $filter_purchase_order_id,
            "filter_store" => $filter_store,
            "filter_purchase_order_date" => $filter_purchase_order_date,
            "filter_purchase_order_date_end" => $filter_purchase_order_date_end,
            "filter_item_code" => $filter_item_code,
            "filter_order_type" => $filter_order_type,
            "filter_relationship_type" => $filter_relationship_type
        );

        $results = $this->model->getSaleAndPurchaseBills($filter_data, $this->customer->getId());
        $buyerCode = $this->model->getBuyerCode($this->customer->getId());
        $data['isEuropeCountry'] = $this->country->isEuropeCountry($this->customer->getCountryId());
        $fileName = $buyerCode.'_'. date("YmdHi", time()) . ".csv";
        $showServiceFee = $this->customer->showServiceFee($this->customer->getId());
        if($showServiceFee) {
            $head = array('Sales Order ID', 'Shipping Recipient', 'Shipping Address Detail', 'Shipping City','Shipping State','Shipping Country','Shipping Postal Code', 'Item Code', 'Unit Price After Discount', 'Quantity', 'Service Fee After Discount','Transaction Fee','Fulfillment Per Unit','Total Savings','Total Amount', 'Purchase Order ID', 'Purchase Order Date', 'Sales Order Status');
        }else{
            $head = array('Sales Order ID', 'Shipping Recipient', 'Shipping Address Detail', 'Shipping City','Shipping State','Shipping Country','Shipping Postal Code', 'Item Code', 'Unit Price After Discount', 'Quantity','Transaction Fee','Fulfillment Per Unit','Total Savings','Total Amount', 'Purchase Order ID', 'Purchase Order Date', 'Sales Order Status');
        }
        $content = array();
        if (isset($results) && !empty($results)) {
            $total_price = 0;
            foreach ($results as $value) {
                if($value['quoteFlag']) {
                    $amount_total = round(($value['price']+ $value['freight_per']+$value['package_fee']+$value['transaction_fee']) * $value['qty'], 2);
                }else{
                    $amount_total = round(($value['price'] + $value['service_fee_per'] + $value['freight_per']+$value['package_fee']+$value['transaction_fee']) * $value['qty'], 2);
                }
                $amount_total = $amount_total - $value['coupon_amount'] - $value['campaign_amount'];
                if($data['isEuropeCountry'] && $value['quoteFlag']){
                    $unit_price = $this->currency->formatCurrencyPrice($value['op_price']-$value['amount_price_per'], $this->session->data['currency']);
                    $service_fee = $this->currency->formatCurrencyPrice($value['service_fee_per']-$value['amount_service_fee_per'], $this->session->data['currency']);
                }else{
                    $unit_price = $this->currency->formatCurrencyPrice($value['price'], $this->session->data['currency']);
                    $service_fee = $this->currency->formatCurrencyPrice($value['service_fee_per'], $this->session->data['currency']);
                }
                $total_price = $total_price+$amount_total;
                if($showServiceFee){
                    $content[] = array(
                        "\t".$value['sales_order_id'],
                        app('db-aes')->decrypt($value['ship_name']),
                        app('db-aes')->decrypt($value['ship_address1']),
                        app('db-aes')->decrypt($value['ship_city']),
                        $value['ship_state'],
                        $value['ship_country'],
                        $value['ship_zip_code'],
                        $value['item_code'],
                        $unit_price,
                        $value['qty'],
                        $service_fee,
                        $this->currency->formatCurrencyPrice($value['transaction_fee'], $this->session->data['currency']),
                        $this->currency->formatCurrencyPrice($value['freight_per']+$value['package_fee'], $this->session->data['currency']),
                        $this->currency->formatCurrencyPrice(-($value['coupon_amount'] + $value['campaign_amount']), $this->session->data['currency']),
                        $this->currency->formatCurrencyPrice($amount_total, $this->session->data['currency']),
                        $value['order_id'],
                        $value['date_modified'],
                        $value['DicValue']
                    );
                }else{
                    $content[] = array(
                        "\t".$value['sales_order_id'],
                        app('db-aes')->decrypt($value['ship_name']),
                        app('db-aes')->decrypt($value['ship_address1']),
                        app('db-aes')->decrypt($value['ship_city']),
                        $value['ship_state'],
                        $value['ship_country'],
                        $value['ship_zip_code'],
                        $value['item_code'],
                        $unit_price,
                        $value['qty'],
                        $this->currency->formatCurrencyPrice($value['transaction_fee'], $this->session->data['currency']),
                        $this->currency->formatCurrencyPrice($value['freight_per']+$value['package_fee'], $this->session->data['currency']),
                        $this->currency->formatCurrencyPrice(-($value['coupon_amount'] + $value['campaign_amount']), $this->session->data['currency']),
                        $this->currency->formatCurrencyPrice($amount_total, $this->session->data['currency']),
                        $value['order_id'],
                        $value['date_modified'],
                        $value['DicValue']
                    );
                }
            }
            if ($showServiceFee) {
                $content[] = array('', '', '', '', '', '', '', '', '', '', '', '', '', 'Total Price:', $this->currency->formatCurrencyPrice($total_price, $this->session->data['currency']));
            } else {
                $content[] = array('', '', '', '', '', '', '', '', '', '', '', '', 'Total Price:', $this->currency->formatCurrencyPrice($total_price, $this->session->data['currency']));
            }
        }
        //12591 B2B记录各国别用户的操作时间
        outputCsv($fileName,$head,$content,$this->session);
        //12591 end
    }

}
