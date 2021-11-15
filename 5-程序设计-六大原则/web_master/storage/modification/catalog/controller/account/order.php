<?php

use App\Catalog\Controllers\BaseController;
use App\Catalog\Search\FeeOrder\FeeOrderSearch;
use App\Enums\Common\YesNoEnum;
use App\Enums\Country\Country;
use App\Enums\FeeOrder\FeeOrderExceptionCode;
use App\Enums\FeeOrder\FeeOrderFeeType;
use App\Enums\FeeOrder\FeeOrderOrderType;
use App\Enums\FeeOrder\FeeOrderStatus;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Order\OcOrderTypeId;
use App\Enums\Search\CreateDateRange;
use App\Helper\CountryHelper;
use App\Logging\Logger;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Marketing\CampaignOrder;
use App\Models\Order\Order;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\FeeOrder\FeeOrderRepository;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Repositories\Margin\MarginRepository;
use App\Repositories\Marketing\CampaignRepository;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Safeguard\SafeguardConfigRepository;
use App\Services\FeeOrder\FeeOrderService;
use App\Services\Future\Agreement as FutureAgreement;
use App\Services\Marketing\MarketingTimeLimitDiscountService;
use App\Services\Marketing\CouponService;
use App\Services\Order\OrderInvoiceService;
use App\Services\Stock\BuyerStockService;
use Framework\Action\Action;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Class ControllerAccountOrder
 * @property ModelAccountOrder $model_account_order
 * @property ModelAccountProductQuotesMargin $model_account_product_quotes_margin
 * @property ModelAccountProductQuotesMarginContract $model_account_product_quotes_margin_contract
 * @property ModelAccountProductQuotesRebatesAgreement $model_account_product_quotes_rebates_agreement
 * @property ModelAccountRmaManagement $model_account_rma_management
 * @property ModelCheckoutCart $model_checkout_cart
 * @property ModelToolCsv $model_tool_csv
 * @property ModelToolExcel $model_tool_excel
 * @property ModelToolImage $model_tool_image
 * @property ModelToolUpload $model_tool_upload
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelExtensionModuleCartHome $model_extension_module_cart_home
 * @property ModelCatalogFuturesProductLock $model_catalog_futures_product_lock
 */
class ControllerAccountOrder extends BaseController {

    /**
     * 四舍五入精度
     * 日本不保留小数位，其他国家保留两位
     *
     * @var int $precision
     */
    private $precision;
    private $isPartner = false;
    private $customer_id = null;
    private $country_id;
    private $is_europe = false;
    private $action_btn = [
        '0'   =>['View','Pay','Cancel','Reorder'],
        '5'   =>['View','RMA','Reorder'],
        '7'   =>['View','Reorder'],
    ];

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->precision = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
        $this->customer_id = $this->customer->getId();
        $this->country_id = $this->customer->getCountryId();
        $this->isPartner = $this->customer->isPartner();
        if (empty($this->customer_id) || $this->isPartner) {
            $this->response->redirectTo($this->url->to('account/login'))->send();
            return;
        }
        if ($this->country->isEuropeCountry($this->customer->getCountryId())) {
            $this->is_europe = true;
        }else{
            $this->is_europe = false;
        }
    }

	public function index1() {
        $this->load->model('tool/image');
	    $this->load->model('account/order');
        $this->load->model('catalog/product');
        $this->load->model('account/product_quotes/margin');
        $this->load->model('account/product_quotes/rebates_agreement');
		if (!$this->customer->isLogged()) {
			session()->set('redirect', $this->url->link('account/order', '', true));

			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('account/order');
        $this->load->language('common/cwf');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('account/order', '', true)
		);

        $url = '';
        $param = [];

        if (isset($this->request->get['filter_orderDate_from']) && $this->request->get['filter_orderDate_from'] !='') {
            $data['filter_orderDate_from'] = $this->request->get['filter_orderDate_from'];
            $param['filter_orderDate_from'] = $this->request->get['filter_orderDate_from'];
            $url .= '&filter_orderDate_from=' . $this->request->get['filter_orderDate_from'];
        }
        if (isset($this->request->get['filter_orderDate_to']) && $this->request->get['filter_orderDate_to'] != ''){
            $data['filter_orderDate_to'] = $this->request->get['filter_orderDate_to'];
            $param['filter_orderDate_to'] = $this->request->get['filter_orderDate_to'];
            $url .= '&filter_orderDate_to=' . $this->request->get['filter_orderDate_to'];
        }

        if (isset($this->request->get['filter_orderId'])) {
            $data['filter_orderId'] = $this->request->get['filter_orderId'];
            $param['filter_orderId'] = $this->request->get['filter_orderId'];
            $url .= '&filter_orderId=' . $this->request->get['filter_orderId'];
        }

        if (isset($this->request->get['filter_item_code'])) {
            $data['filter_item_code'] = $this->request->get['filter_item_code'];
            $param['filter_item_code'] = $this->request->get['filter_item_code'];
            $url .= '&filter_item_code=' . $this->request->get['filter_item_code'];
        }
        if (isset($this->request->get['filter_include_all_refund'])){
            $data['filter_include_all_refund'] = $this->request->get['filter_include_all_refund'];
            $param['filter_include_all_refund'] = $this->request->get['filter_include_all_refund'];
            $url .= '&filter_include_all_refund=' . $this->request->get['filter_include_all_refund'];
        }
        if (isset($this->request->get['filter_order_status'])){
            $data['filter_order_status'] = $this->request->get['filter_order_status'];
            $param['filter_order_status'] = $this->request->get['filter_order_status'];
            $url .= '&filter_order_status=' . $this->request->get['filter_order_status'];
        }
        if(isset($this->request->get['sort_order_date'])){
            $param['sort_order_date'] = $this->request->get['sort_order_date'];
            if($this->request->get['sort_order_date'] == 'asc'){
                $sort = 'desc';
            }else{
                $sort = 'asc';
            }
            $url_sort = $url;
            $url_sort .=  $url_sort.'&sort_order_date=' . $sort;
            $url .= '&sort_order_date=' . $this->request->get['sort_order_date'];
            $data['sort'] = $this->request->get['sort_order_date'];
        }else{
            $url_sort = $url;
        }

        $page = $this->request->get['page'] ?? 1;
        $page_limit = intval(get_value_or_default($this->request->get, 'page_limit', 10));
        $perPage    = $page_limit;
        $total = $this->model_account_order->getPurchaseOrderTotal($param);
        $result = $this->model_account_order->getPurchaseOrderDetails($param,$page,$perPage);

        // N-81 判断是否为全部退货 start
        $refundOrderIds = $this->model_account_order->getAllRefundOrderIds($this->customer->getId());
        // N-81 判断是否为全部退货 end
        foreach($result as $key => $value){

            //该订单中的现货保证金产品
            $arrMarginProduct = $this->model_account_product_quotes_margin->getMarginProductByOrderID($value['order_id']);


            $result[$key]['is_full_refund'] = in_array($value['order_id'], $refundOrderIds);
            $result[$key]['date_added'] = date($this->language->get('datetime_format'), strtotime($value['date_added']));
            //$fee = $this->orm->table(DB_PREFIX.'order_total')->where('order_id',$value['order_id'])->where('code','poundage')->value('value');
            $balance = $this->orm->table(DB_PREFIX.'order_total')->where('order_id',$value['order_id'])->where('code','balance')->value('value');
            $result[$key]['total'] = $this->currency->format($value['total'] - $balance, $value['currency_code'], $value['currency_value']);
            //计算税率
            //$result[$key]['fee'] = $this->currency->format($fee, $value['currency_code'], $value['currency_value']);
            //计算标签

            // 获取order_id 下面的所有的order_product
            $product_details = $this->model_account_order->getPurchaseOrderProductInfo($value['order_id']);
            foreach($product_details as $k => &$v){
                $tag_array = $this->model_catalog_product->getProductSpecificTag($v['product_id']);
                $tags = array();
                if(isset($tag_array)){
                    foreach ($tag_array as $tag){
                        if(isset($tag['origin_icon']) && !empty($tag['origin_icon'])){
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip" class="'.$tag['class_style']. '"  title="'.$tag['description']. '" style="padding-left: 1px" src="'.$img_url.'">';
                        }
                    }
                }
                $v['tag'] = $tags;
                //判断是否参与返点
                $v['is_rebate'] = $this->model_account_product_quotes_rebates_agreement->checkIsRebate($value['order_id'], $v['product_id']);
                //是否为现货保证金产品
                $is_margin  = array_key_exists($v['product_id'], $arrMarginProduct) ? 1 : 0;
                $tip_margin = '';
                $url_margin = '';
                if ($is_margin) {
                    $tip_margin = 'Click to view the margin agreement details for agreement ID ' . $arrMarginProduct[$v['product_id']]['margin_agreement_id'] . '.';
                    $url_margin = $this->url->link('account/product_quotes/margin/detail_list', 'id=' . $arrMarginProduct[$v['product_id']]['margin_id'], true);
                }
                $v['is_margin']  = $is_margin;
                $v['tip_margin'] = $tip_margin;
                $v['url_margin'] = $url_margin;
                //验证是否为期货的产品
                if($v['type_id'] == 3){
                    $future_margin_info = $this->model_account_order->getFutureMarginInfo($v['agreement_id']);
                    $v['contract_id'] = $future_margin_info['contract_id'];
                    $v['agreement_no'] = $future_margin_info['agreement_no'];
                }
            }
            $result[$key]['view'] = $this->url->link('account/order/purchaseOrderInfo', 'order_id=' . $value['order_id'], true);
            $result[$key]['item_code_list'] = $product_details;
        }
        $data['orders'] = $result;

        $data['rebate_logo'] = '/image/product/rebate_15x15.png';

        //分页
        $data['page_limit'] = intval($page_limit);
        $data['page_num']   = intval($page);
        $pagination         = new Pagination();
        $pagination->total  = $total;
        $pagination->page   = $page;
        $pagination->limit  = $perPage;
        $pagination->url    = $this->url->link('account/order' . $url, '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $resultstring       = sprintf($this->language->get('text_pagination'), ($total) ? (($page - 1) * $perPage) + 1 : 0, ((($page - 1) * $perPage) > ($total - $perPage)) ? $total : ((($page - 1) * $perPage) + $perPage), $total, ceil($total / $perPage));
        $data['results']    = $pagination->results($resultstring);

        $url_sort = str_replace('&amp;', '&', $this->url->link('account/order' . $url_sort, '&page='.$page, true));
        $data['url_sort'] = $url_sort;
        $data['results'] = sprintf($this->language->get('text_pagination'),($total) ? (($page - 1) * $perPage) + 1 : 0, ((($page - 1) * $perPage) > ($total - $perPage)) ? $total : ((($page - 1) * $perPage) + $perPage), $total, ceil($total / $perPage) );

		$data['continue'] = $this->url->link('account/account', '', true);

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        $data['query'] = $this->url->link('extension/payment/umf_pay/query', '', true);

		$this->response->setOutput($this->load->view('account/order_list', $data));
	}

    /**
     * [purchaseOrderFilterByCsv description] 销售订单根据条件搜索导出
     * @throws Exception
     */
	public function purchaseOrderFilterByCsv(){

        $param = [];
        if (isset($this->request->get['filter_orderDate_from']) && $this->request->get['filter_orderDate_from'] !='') {
            $param['filter_orderDate_from'] = $this->request->get['filter_orderDate_from'];
        }
        if (isset($this->request->get['filter_orderDate_to']) && $this->request->get['filter_orderDate_to'] != ''){
            $param['filter_orderDate_to'] = $this->request->get['filter_orderDate_to'];
        }

        if (isset($this->request->get['filter_orderId'])) {
            $param['filter_orderId'] = $this->request->get['filter_orderId'];
        }
        if (isset($this->request->get['filter_item_code'])) {
            $param['filter_item_code'] = $this->request->get['filter_item_code'];
        }
        if (isset($this->request->get['filter_include_all_refund'])){
            $param['filter_include_all_refund'] = $this->request->get['filter_include_all_refund'];
        }
        if (isset($this->request->get['filter_order_status'])){
            $param['filter_order_status'] = $this->request->get['filter_order_status'];
        }
        $this->load->model('account/order');

        $result = $this->model_account_order->getPurchaseOrderFilterData($param, $this->customer_id, $this->country_id);

        $result['enableQuote'] = false;
        if (in_array($this->customer->getCountryId(), QUOTE_ENABLE_COUNTRY)) {
            $result['enableQuote'] = true;
        }

        $result['isEurope'] = false;
        if ($this->country->isEuropeCountry($this->customer->getCountryId())) {
            $result['isEurope'] = true;
        }

        $result['isJapan'] = false;
        if ($this->customer->getCountryId() == JAPAN_COUNTRY_ID) {
            $result['isJapan'] = true;
        }

        $this->load->model('tool/csv');
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis",time()),'Ymd');
        //12591 end
        $filename = 'PurchaseReport'.$time.'.csv';
        $this->model_tool_csv->getPurchaseOrderFilterCsv($filename,$result);
    }

    /**
     * [purchaseOrderFilterByExcel description]
     * @throws Exception
     */
    public function purchaseOrderFilterByExcel()
    {
        set_time_limit(0);
        $param = ['filter_orderDate_from', 'filter_orderDate_to', 'filter_PurchaseOrderId', 'filter_item_code','filter_include_returns','filter_orderStatus','filter_associatedOrder'];
        $param_map = ['filter_orderDate_from', 'filter_orderDate_to', 'filter_PurchaseOrderId', 'filter_item_code','filter_include_returns','filter_orderStatus','filter_associatedOrder'];
        $condition = [];
        foreach ($param as $key => $value) {
            $data[$value] = $this->request->get($value,'');
            if($data[$value]){
               $condition[$param_map[$key]] = trim( $data[$value]);
            }

        }
        load()->model('account/order');
        $result = $this->model_account_order->getPurchaseOrderFilterData($condition,$this->customer_id,$this->country_id);
        $result['enableQuote'] = false;
        if (in_array($this->country_id, QUOTE_ENABLE_COUNTRY)) {
            $result['enableQuote'] = true;
        }
        $result['isEurope'] = false;
        if ($this->country->isEuropeCountry($this->country_id)) {
            $result['isEurope'] = true;
        }
        $result['isJapan'] = false;
        if ($this->country_id == JAPAN_COUNTRY_ID) {
            $result['isJapan'] = true;
        }
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis",time()),'Ymd');
        //12591 end

        load()->model('tool/excel');
        $file_name = 'PurchaseReport'.$time.'.xls';
        $isCollectionFromDomicile = customer()->isCollectionFromDomicile();
        $this->model_tool_excel->getPurchaseOrderFilterExcel($file_name,$result,$isCollectionFromDomicile);
    }


    public function purchaseOrderInfo(){
	    $country_id = $this->customer->getCountryId();
	    $customer_id = $this->customer->getId();
        $buyer_id = customer()->getId();
        $this->load->language('account/order');
        $this->load->language('common/cwf');
        $order_id = (int)request()->get('order_id', 0);


        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/order/purchaseOrderInfo', 'order_id=' . $order_id, true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $this->load->model('account/product_quotes/rebates_agreement');
        $this->load->model('account/product_quotes/margin');
        $this->load->model('account/order');
        $order_info = $this->model_account_order->getOrder($order_id,$buyer_id);
        //欧洲
        $data['isEuropean'] = false;
        if (!empty($this->customer->getCountryId()) && $this->country->isEuropeCountry($this->customer->getCountryId())) {
            $data['isEuropean'] = true;
        }

        //是否启用议价
        $data['enableQuote'] = false;
        if (!empty($this->customer->getCountryId()) && in_array($this->customer->getCountryId(), QUOTE_ENABLE_COUNTRY)) {
            $data['enableQuote'] = true;
        }

        //add by xxli
//        if($order_info['order_status_id']==OcOrderStatus::COMPLETED){
//            $data['can_review'] = true;
//        }else{
            $data['can_review'] = false;
//        }
        //end xxli
        if ($order_info) {
            // 状态为完成的订单才有满送
            $orderFullDeliveryCampaigns = [];
            if ($order_info['order_status_id'] == OcOrderStatus::COMPLETED) {
                $orderCampaigns = app(CampaignRepository::class)->getOrderFullDeliveryCampaigns($order_id);
                foreach ($orderCampaigns as $orderCampaign) {
                    /** @var CampaignOrder $orderCampaign */
                    if ($orderCampaign->coupon_id) {
                        $couponAmount = $this->currency->formatCurrencyPrice($orderCampaign->coupon->denomination, $order_info['currency_code'], $order_info['currency_value']);
                        $orderFullDeliveryCampaigns[] = [
                            'is_coupon' => true,
                            'content' => $couponAmount,
                        ];
                    } else {
                        $orderFullDeliveryCampaigns[] = [
                            'is_coupon' => false,
                            'content' => $orderCampaign->remark,
                        ];
                    }
                }
            }
            $data['order_full_delivery_campaigns'] = $orderFullDeliveryCampaigns;

            $data['order_buyer_id'] = $order_info['customer_id'];
            $data['delivery_type'] = $order_info['delivery_type'];
            $data['customer_id']    = $customer_id;
            //该订单中的返点产品
            $arrRebateProduct = $this->model_account_product_quotes_rebates_agreement->getRebateProductByOrderID($order_id);
            //该订单中的现货保证金产品
            //$arrMarginProduct = $this->model_account_product_quotes_margin->getMarginProductByOrderID($order_id);
            //获取order_status
            if ($order_info['order_status_id'] == OcOrderStatus::TO_BE_PAID){
                $data['order_status'] = 'To Be Paid';
            } else {
                $data['order_status'] = $this->orm->table(DB_PREFIX.'order_status')->where('order_status_id',$order_info['order_status_id'])->value('name');
            }
            $data['order_status_id'] = $order_info['order_status_id'];
            $this->document->setTitle($this->language->get('text_order'));
            $url = '';
            if (isset($this->request->get['page'])) {
                $url .= '&page=' . $this->request->get['page'];
            }
            $data['breadcrumbs'] = array();
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home')
            );
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('account/order', $url, true)
            );
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_order'),
                'href' => $this->url->link('account/order/purchaseOrderInfo', 'order_id=' . $order_id . $url, true)
            );


            // marketplace
            $this->load->model('account/customerpartner');
            $data['button_order_detail'] = $this->language->get('button_order_detail');
            $data['text_tracking'] = $this->language->get('text_tracking');
            $data['module_marketplace_status'] = $this->config->get('module_marketplace_status');
            // marketplace

            if (isset($this->session->data['error'])) {
                $data['error_warning'] = session('error');

                $this->session->remove('error');
            } else {
                $data['error_warning'] = '';
            }

            if (isset($this->session->data['success'])) {
                $data['success'] = session('success');

                $this->session->remove('success');
            } else {
                $data['success'] = '';
            }

            if ($order_info['invoice_no']) {
                $data['invoice_no'] = $order_info['invoice_prefix'] . $order_info['invoice_no'];
            } else {
                $data['invoice_no'] = '';
            }

            $data['order_id'] = $order_id;
            //获取信用卡支付额度
            $balance = $this->orm->table(DB_PREFIX.'order_total')->where('order_id', $data['order_id'])->where('code','balance')->value('value');
            $data['balance'] = $this->currency->format(0 - $balance, session('currency'));
            $data['date_added'] = date($this->language->get('datetime_format'), strtotime($order_info['date_added']));

            if ($order_info['payment_address_format']) {
                $format = $order_info['payment_address_format'];
            } else {
                $format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
            }

            $find = array(
                '{firstname}',
                '{lastname}',
                '{company}',
                '{address_1}',
                '{address_2}',
                '{city}',
                '{postcode}',
                '{zone}',
                '{zone_code}',
                '{country}'
            );

            $replace = array(
                'firstname' => $order_info['payment_firstname'],
                'lastname'  => $order_info['payment_lastname'],
                'company'   => $order_info['payment_company'],
                'address_1' => $order_info['payment_address_1'],
                'address_2' => $order_info['payment_address_2'],
                'city'      => $order_info['payment_city'],
                'postcode'  => $order_info['payment_postcode'],
                'zone'      => $order_info['payment_zone'],
                'zone_code' => $order_info['payment_zone_code'],
                'country'   => $order_info['payment_country']
            );

            $data['payment_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

            $data['payment_method'] = $order_info['payment_method'];

            if($order_info['payment_method'] == 'Line Of Credit' && $this->customer->getAdditionalFlag() == 1){
                $data['payment_method'] = 'Line Of Credit(+1%)';
            }

            if ($order_info['shipping_address_format']) {
                $format = $order_info['shipping_address_format'];
            } else {
                $format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
            }

            $find = array(
                '{firstname}',
                '{lastname}',
                '{company}',
                '{address_1}',
                '{address_2}',
                '{city}',
                '{postcode}',
                '{zone}',
                '{zone_code}',
                '{country}'
            );

            $replace = array(
                'firstname' => $order_info['shipping_firstname'],
                'lastname'  => $order_info['shipping_lastname'],
                'company'   => $order_info['shipping_company'],
                'address_1' => $order_info['shipping_address_1'],
                'address_2' => $order_info['shipping_address_2'],
                'city'      => $order_info['shipping_city'],
                'postcode'  => $order_info['shipping_postcode'],
                'zone'      => $order_info['shipping_zone'],
                'zone_code' => $order_info['shipping_zone_code'],
                'country'   => $order_info['shipping_country']
            );

            $data['shipping_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

            $data['shipping_method'] = $order_info['shipping_method'];
            //#69
            $data['transaction_status'] = $order_info['order_status_id']==OcOrderStatus::COMPLETED?'Paid':'Unpaid';
            //#69 end
            if ($order_info['delivery_type'] == 2) {
                $cwf_is_rma = $this->model_account_order->checkCWFIsRMA($order_id);
            }else{
                $cwf_is_rma = 0;
            }

            $this->load->model('catalog/product');
            $this->load->model('tool/upload');
            $this->load->model('tool/image');
            $this->load->model('account/rma_management');
            $this->load->model('checkout/cart');
            // Products
            $data['products'] = array();
            $products = $this->model_account_order->getOrderProducts($order_id);
            //获取 sub-toal
            //获取 total quote discount
            //获取 total pundage
            // 获取 total price
            $sub_total = 0;
            $total_quote_discount = 0;
            $total_quote_service_fee_discount = 0;
            $total_service_fee = 0;
            $poundage = $this->model_account_order->getTotalTransaction($order_id);
            $total_transaction = isset($poundage)?$poundage:0;
            $total_freight = 0;
            $promotionDiscountAmount = 0; // 满减
            $gigaCouponAmount = 0; // 优惠券
            // 获取产品是否是囤货产品
            $unsupportStockMap = app(CustomerRepository::class)->getUnsupportStockData(array_column($products,'product_id'));
            foreach ($products as $product) {
                $product_info = $this->model_catalog_product->getProductForOrderHistory($product['product_id']);
                //add by xxli 获取产品的评价信息
                $customerName = $this->model_account_order->getCustomerName($product_info['customer_id']);
                //$reviewResult = $this->model_account_order->getReviewInfo($order_id,$product['order_product_id']);
                //end
                if ($product_info) {
                    $reorder = $this->url->link('account/order/reorder', 'order_id=' . $order_id . '&order_product_id=' . $product['order_product_id'], true);
                } else {
                    $reorder = '';
                }
                $tag_array = $this->model_catalog_product->getProductSpecificTag($product['product_id']);
                $tags = array();
                if(isset($tag_array)){
                    foreach ($tag_array as $tag){
                        if(isset($tag['origin_icon']) && !empty($tag['origin_icon'])){
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip"  class="'.$tag['class_style']. '"   title="'.$tag['description']. '" style="padding-left: 1px" src="'.$img_url.'">';
                        }
                    }
                }
                //获取这个订单的salesOrder
                $mapProduct = [
                    ['op.product_id','=', $product['product_id']],
                    ['oco.order_id','=', $product['order_id']],
                ];

                $new_res = $this->getOrderPoundage($mapProduct);
                $mapRma = [
                    ['rp.product_id','=', $product['product_id']],
                    ['r.order_id','=', $product['order_id']],
                ];
                $rma_info = $this->getOrderRma($mapRma);
                if(null == $rma_info){
                    $rma_info['rma_id_list'] = null;
                    $rma_info['rid_list'] = null;
                }
                if(null == $new_res){

                    $new_res['order_id_list'] = null;
                    $new_res['oid_list'] = null;
                    $new_res['poundage'] = 0;
                }

                /**
                 * @var float $discount_price 每个产品的议价折扣数
                 * @var float $real_price
                 * @var float $service_price 每个产品明细的总服务费
                 */
                //开启议价的国家
                $discount_price = 0;
                $discount_service_fee_per = 0;
                $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
                if($isCollectionFromDomicile){
                    $freight_per = $product['package_fee'];
                }else{
                    $freight_per = $product['freight_per']+$product['package_fee'];
//                    if ($order_info['delivery_type'] == 2 && !empty($product['freight_difference_per'])) {
//                        $freight_per += $product['freight_difference_per'];
//                    }
                }
                if ($data['enableQuote']) {
                    $pq_price = is_null($new_res['pq_price']) ? $product['price'] : $new_res['pq_price'];
                    // 1.如果订单完成 oc_product_quote.amount_price_per会有值
                    $new_res['amount_price_per'] = !empty($new_res['amount_price_per']) ? $new_res['amount_price_per'] : 0;
                    $new_res['amount_service_fee_per'] = !empty($new_res['amount_service_fee_per']) ? $new_res['amount_service_fee_per'] : 0;
                    // 2.如果订单未完成 oc_product_quote.amount_price_per=0,待支付订单和cancel订单显示就不对,需要获取oc_order_quote的议价折扣来显示
                    // 3. oc_order_quote.product_id是后来新添加的字段,老数据没有,所以第一条是需要的
                    if ($new_res['amount_data'] && $new_res['quote_product_id'] == $new_res['product_id']) {
                        $amountData = json_decode($new_res['amount_data'], true);
                        $new_res['amount_price_per'] = $amountData['amount_price_per'];
                        $new_res['amount_service_fee_per'] = $amountData['amount_service_fee_per'];
                    }

                    if ($data['isEuropean']) {
                        $discount_price = $new_res['amount_price_per'];
                        $discount_service_fee_per = $new_res['amount_service_fee_per'];
                        $amount_service_fee = round($new_res['amount_service_fee_per'] * $product['quantity'], 2);
                        $real_price = $new_res['service_fee_per'] + $product['price']+$freight_per;
                    } else {
                        $discount_price = $new_res['amount_price_per'];
                        $amount_service_fee = 0;
                        $real_price = $product['price']+$freight_per;
                    }
                    }else{
                    if ($data['isEuropean']) {
                        $discount_price = $new_res['amount_price_per'];
                        $amount_service_fee = round($new_res['amount_service_fee_per'] * $product['quantity'], 2);
                        $real_price = $new_res['service_fee_per'] + $product['price']+$freight_per;
                    } else {
                        $discount_price = $product['price'];
                        $amount_service_fee = 0;
                        $real_price = $product['price']+$freight_per;
                    }
                }

                $line_total = bcmul($real_price, $product['quantity'], $this->precision);
                $amount = bcmul($discount_price, $product['quantity'], $this->precision);
                $line_total = bcsub($line_total, $amount, $this->precision);
                $line_total = bcsub($line_total, $amount_service_fee, $this->precision);

                // 使用优惠的total
                $discountTotal = $product['campaign_amount'] + $product['coupon_amount'];

                $sub_total += round(($product['price']-$discount_price) * $product['quantity'],$this->precision);
                $total_quote_discount += round($discount_price * $product['quantity'],$this->precision);
                $total_quote_service_fee_discount += $amount_service_fee;
                $total_service_fee += round(($product['service_fee_per']-$discount_service_fee_per) * $product['quantity'],$this->precision);
//                $total_transaction += $new_res['poundage'];
                $total_freight += ($freight_per)*$product['quantity'];
                $promotionDiscountAmount += $product['campaign_amount'];
                $gigaCouponAmount += $product['coupon_amount'];
                //获取图片链接
                //获取这个订单的议价
                //获取这个订单的RMAID

                $image = $this->orm->table(DB_PREFIX.'product')->where('product_id',$product['product_id'])->value('image');
                if ($image) {
                    $image = $this->model_tool_image->resize($image, 30, 30);
                } else {
                    $image = $this->model_tool_image->resize('placeholder.png', 30, 30);
                }

                $is_rebate = YesNoEnum::NO;
                $tip_rebate = '';
                $url_rebate = '';
                //是否为现货保证金产品
                $is_margin  = YesNoEnum::NO;
                $tip_margin = '';
                $url_margin = '';
                $contract_id = YesNoEnum::NO;
                $agreement_no = '';
                $is_future_margin = YesNoEnum::NO;
                $future_margin_info = [];

                $rebateAgreementId = $this->model_account_product_quotes_rebates_agreement->getRebateAgreementId($order_id, $product['product_id']);
                if($product['type_id'] == OcOrderTypeId::TYPE_REBATE
                    || $rebateAgreementId
                ){
                    $product['agreement_id'] = $rebateAgreementId ?? $product['agreement_id'];
                    $is_rebate = YesNoEnum::YES;
                    $agreementCode = $this->model_account_order->getRebateAgreementCode($product['agreement_id']);
                    $tip_rebate = 'Click to view the rebate agreement details for agreement ID ' . $agreementCode . '.';
                    $url_rebate = $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementList', ['agreement_id'=>$product['agreement_id'],'act'=>'view']);
                } elseif($product['type_id'] == OcOrderTypeId::TYPE_MARGIN){
                    $margin_info = $this->model_account_order->getMarginInfo($product['agreement_id']);
                    $tip_margin = 'Click to view the margin agreement details for agreement ID ' . $margin_info['agreement_id'] . '.';
                    $url_margin = $this->url->link('account/product_quotes/margin/detail_list', ['id'=>$product['agreement_id']]);
                    $is_margin = 1;
                } elseif($product['type_id'] == OcOrderTypeId::TYPE_FUTURE){
                    $future_margin_info = $this->model_account_order->getFutureMarginInfo($product['agreement_id']);
                    $contract_id = $future_margin_info['contract_id'];
                    $is_future_margin = YesNoEnum::YES;
                    $agreement_no = $future_margin_info['agreement_no'];
                }

                //判断采购订单明细是否允许申请RMA(签收服务费+保证金的头款产品不允许)
                $advanceMarginProduct = $this->model_account_rma_management->checkMarginAdvanceProduct($product['product_id']);
                if($product['product_id'] == $this->config->get('signature_service_us_product_id')){
                    $canReturn = 1;
                }else if($advanceMarginProduct){
                    $canReturn = 2;
                }else if($is_future_margin && $future_margin_info['advance_product_id'] == $product['product_id']){
                    $canReturn = 4;
                }else{
                    $canReturn = 3;
                }
                // 如果为云送订单，但是状态 不是取消 则不可以rma
                if ($order_info['delivery_type'] == 2 && !$cwf_is_rma) {
                    $canReturn = 0;
                }
                //#11052 未支付的采购单不能rma
                if ($order_info['order_status_id'] != OcOrderStatus::COMPLETED) {
                    $canReturn = 0;
                }

                $product_type = $this->model_catalog_product->getProducTypetById($product['product_id']);
                $reorder_product_id       = $product['product_id'];
                $reorder_quantity         = $product['quantity'];
                $reorder_transaction_type = '';
                if ($is_rebate) {
                    if (isset($arrRebateProduct[$product['product_id']]) && $arrRebateProduct[$product['product_id']]) {
                        $reorder_transaction_type = $arrRebateProduct[$product['product_id']]['id'] .'_1';
                    }
                    // 如果复杂交易已完成/购买完，以normal方式加入购物车
                    if ($product_type == 0 && !$this->model_checkout_cart->verifyRebateAgreement($product['product_id'], $product['agreement_id'], $this->customer_id)) {
                        $reorder_transaction_type = '';
                    }
                } elseif ($is_future_margin){
                    //期货头款 0 非头款 agreement_id_type_id
                    if($future_margin_info['advance_product_id'] != $product['product_id']){
                        $reorder_transaction_type = $product['agreement_id'] . '_'.$product['type_id'];
                    }
                    // 如果复杂交易已完成/购买完，以normal方式加入购物车
                    if ($product_type == 0 && !$this->model_checkout_cart->verifyFutureMarginAgreement($product['agreement_id'])) {
                        $reorder_transaction_type = '';
                    }
                } else {
                    if ($is_margin) {
                        $reorder_transaction_type = $product['agreement_id'] . '_2';
                    }
                    // 如果复杂交易已完成/购买完，以normal方式加入购物车
                    if ($product_type == 0 && !$this->model_checkout_cart->verifyMarginAgreement($product['agreement_id'])) {
                        $reorder_transaction_type = '';
                    }
                }


                $unsupport_stock = in_array($product['product_id'],$unsupportStockMap);

                $data['products'][] = array(
                    'name'     => $product['name'],
                    'unsupport_stock' => $unsupport_stock,
                    'img'      => $image,
                    'type_id'  => $product['type_id'],
                    'agreement_id' => $product['agreement_id'],
                    'agreement_no' => $agreement_no,
                    'contract_id' => $contract_id,
                    'model'    => $product['model'],
                    'quantity' => $product['quantity'],
                    'poundage' => $this->currency->format($new_res['poundage'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
                    'product_id'=>$product['product_id'],
                    'rma_id_list' => explode(',', $rma_info['rma_id_list']),
                    'order_id_list' => explode(',',$new_res['order_id_list']),
                    'oid_list' => explode(',',$new_res['oid_list']),
                    'rid_list' => explode(',', $rma_info['rid_list']),
                    'service_price' => $this->currency->format($new_res['service_fee_per']-$discount_service_fee_per + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
                    'discount_price' => $this->currency->formatCurrencyPrice(-($discount_price + ($this->config->get('config_tax') ? $product['tax'] : 0)), $order_info['currency_code'], $order_info['currency_value']),
                    'amount_service_fee' => $this->currency->formatCurrencyPrice(-($amount_service_fee + ($this->config->get('config_tax') ? $product['tax'] : 0)), $order_info['currency_code'], $order_info['currency_value']),
                    'price'    => $this->currency->format($product['price']-$discount_price + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
                    'total'    => $this->currency->format($line_total, $order_info['currency_code'], $order_info['currency_value']),
                    'final_total' => $this->currency->format($line_total - $discountTotal, $order_info['currency_code'], $order_info['currency_value']),
                    'discount_total' => $discountTotal,
                    'reorder'  => $reorder,
                    'mpn' => $product_info['mpn'],
                    'sku' => $product_info['sku'],
                    // marketplace
                    'order_detail'   => $this->url->link('account/customerpartner/order_detail', 'order_id=' . $order_info['order_id'] . '&product_id=' . $product['product_id'], true),
                    'order_id' => $product['order_id'],
                    // marketplace
                    'return'   => $this->url->link('account/rma_management', 'filter_order_id=' . $order_info['order_id']),
                    // add by xxli reviewInfo
                    'can_return' => $canReturn,
                    'customerName' => $customerName['screenname'],
                    'order_product_id' =>$product['order_product_id'],
                    'customer_id' =>$product_info['customer_id'],
                    'tag'       => $tags,
                    'freight' =>$this->currency->formatCurrencyPrice($isCollectionFromDomicile?0.00:$product['base_freight']-$product['freight_difference_per'],$this->session->get('currency')), //原始单个运费，基础运费
                    'freight_per' => $this->currency->formatCurrencyPrice($freight_per, $this->session->get('currency')),// 单个合计运费（运费+差额运费+打包费）
                    'freight_difference_per' => $product['freight_difference_per'], //差额运费
                    'freight_difference_per_str' => $this->currency->formatCurrencyPrice($product['freight_difference_per'], $this->session->get('currency')),
                    'overweight_surcharge' => $product['overweight_surcharge'], //超重附加费
                    'overweight_surcharge_show' => $this->currency->formatCurrencyPrice($product['overweight_surcharge'], $this->session->get('currency')),
                    'tips_freight_difference_per' => str_replace(
                        '_freight_difference_per_',
                        $this->currency->formatCurrencyPrice($product['freight_difference_per'], $this->session->get('currency')),
                        $this->language->get('tips_freight_difference_per')
                    ),
                    'package_fee' => $this->currency->formatCurrencyPrice($product['package_fee'], $this->session->get('currency')),
                    'discount_service_fee_per' =>$this->currency->formatCurrencyPrice(-$discount_service_fee_per, $this->session->get('currency')),
                    'quoteFlag' => isset($new_res['pq_price']) ? true : false,
                    'discountShow' => is_null($product['discount']) ? '' : (string)round(100 - $product['discount']),
                    'is_rebate' => $is_rebate,
                    'url_rebate'=>$url_rebate,
                    'tip_rebate'=>$tip_rebate,
                    'is_margin'=> $is_margin,
                    'tip_margin'=>$tip_margin,
                    'url_margin'=>$url_margin,
                    'reorder_product_id'       => $reorder_product_id,
                    'reorder_quantity'         => $reorder_quantity,
                    'reorder_transaction_type' => $reorder_transaction_type,
                    // end xxli
                );

            }

            /**
             * 如果是云送仓 则运费总计 取 oc_order_total的运费 去除附加运费带来的误差
             */
            if ($order_info['delivery_type'] == 2) {
                $total_freight_from_order_total = $this->model_account_order->getTotalFreightFromOrderTotal($order_id);
                $total_freight = !is_null($total_freight_from_order_total) ? $total_freight_from_order_total : $total_freight;
            }

            $total_price = sprintf('%.2f',$sub_total+ $total_service_fee +$total_freight);
            $data['sub_total']  = $this->currency->formatCurrencyPrice($sub_total, session('currency'));
            //$data['total_quote_discount']  = $this->currency->formatCurrencyPrice(-$total_quote_discount, session('currency'));
            //$data['total_quote_service_fee_discount']  = $this->currency->formatCurrencyPrice(-$total_quote_service_fee_discount, session('currency'));
            $data['total_transaction']  = $this->currency->formatCurrencyPrice($total_transaction, session('currency'));
            $data['total_price']  = $this->currency->formatCurrencyPrice($total_price, session('currency'));
            $data['total_service_fee']  = $this->currency->formatCurrencyPrice($total_service_fee, session('currency'));
            $data['total_freight'] = $this->currency->formatCurrencyPrice($total_freight, session('currency'));
            $data['country_id'] = $country_id;

            $data['giga_coupon'] = $gigaCouponAmount;
            $data['promotion_discount'] = $promotionDiscountAmount;
            $data['promotion_discount_show'] = $this->currency->formatCurrencyPrice(-$promotionDiscountAmount, $this->session->get('currency'));
            $data['giga_coupon_show'] = $this->currency->formatCurrencyPrice(-$gigaCouponAmount, $this->session->get('currency'));
            $data['final_total_show'] = $this->currency->formatCurrencyPrice($total_price - $promotionDiscountAmount - $gigaCouponAmount, $this->session->get('currency'));

            $data['comment'] = htmlspecialchars(nl2br($order_info['comment']));

            //上门取货buyer
            $data['isCollectionFromDomicile']=$this->customer->isCollectionFromDomicile();

            // History
            $data['histories'] = array();

            $results = $this->model_account_order->getOrderHistories($order_id);

            foreach ($results as $result) {
                $data['histories'][] = array(
                    'date_added' => date($this->language->get('datetime_format'), strtotime($result['date_added'])),
                    'status'     => $result['status'],
                    'comment'    => $result['notify'] ? nl2br($result['comment']) : ''
                );
            }


            $this->load->model('account/product_quotes/margin_contract');
            $this->load->language('account/product_quotes/margin_contract');
            $data['margin_buyer_id'] = 0;
            $margin_agreement = $this->model_account_product_quotes_margin_contract->getSellerMarginByOrderId($order_id);
            $check_margin_rest = false;
            $agreement_id = null;
            $margin_id    = null;
            if(isset($margin_agreement) && !empty($margin_agreement)){
                $agreement = current($margin_agreement);
                $agreement_id = $agreement['agreement_id'];
                $margin_id    = $agreement['id'];
                foreach ($margin_agreement as $item) {
                    if(isset($item['rest_order_id']) && $order_id != $item['rest_order_id']){
                        $check_margin_rest = true;
                        break;
                    }
                }
                $data['is_margin_order'] = true;
                $data['margin_buyer_id'] = $margin_buyer_id = $margin_agreement['buyer_id'] ?? 0;
            }
            //如果存在保证金的尾款商品，需展示列表
            if($check_margin_rest && isset($agreement_id)){
                $rest_orders = $this->model_account_product_quotes_margin_contract->getMarginCheckDetail($agreement_id);

                //$bx_seller_id = $this->model_account_product_quotes_margin_contract->getMarginBxStoreId($this->customer->getId());
                if (!empty($rest_orders)) {
                    $unsupportRestStockMap = app(CustomerRepository::class)->getUnsupportStockData(array_column($rest_orders,'product_id'));
                    $this->load->language('account/product_quotes/margin_contract');
                    $this->load->model('account/customerpartner');
                    foreach ($rest_orders as $key => $rest_order) {
                        $rest_orders[$key]['unsupport_stock'] = in_array($rest_order['product_id'],$unsupportRestStockMap);
                        $rest_orders[$key]['margin_buyer_id'] = $rest_order['buyer_id'];
                        $rest_orders[$key]['buyer_name'] = $rest_order['buyer_nickname'] . '(' . $rest_order['buyer_user_number'] . ')';
                        $rest_orders[$key]['rest_order_url'] = ($rest_order['buyer_id'] == $customer_id)?($this->url->link('account/order/purchaseOrderInfo', 'order_id=' . $rest_order['rest_order_id'], true)):('');//Buyer自己的订单允许查看

                        $rest_orders[$key]['unit_price'] = $this->currency->format($rest_order['unit_price'], session('currency'));
                        //服务费
                        $rest_orders[$key]['service_fee_per'] = $this->currency->format($rest_order['service_fee_per'], session('currency'));
                        //运费
                        $rest_orders[$key]['freight'] = $this->currency->format($rest_order['freight_per']+$rest_order['package_fee'], session('currency'));
                        //手续费
                        $mapProduct = [
                            ['op.product_id','=', $rest_order['product_id']],
                            ['oco.order_id','=', $rest_order['rest_order_id']],
                        ];
                        $poundage = $this->getOrderPoundage($mapProduct);
                        $mapRma = [
                            ['rp.product_id','=', $rest_order['product_id']],
                            ['r.order_id','=', $rest_order['rest_order_id']],
                        ];
                        $rma_info = $this->getOrderRma($mapRma);
                        $rest_orders[$key]['rma_id_list'] = isset($rma_info['rma_id_list'])?explode(',', $rma_info['rma_id_list']):null;
                        $rest_orders[$key]['rid_list'] = explode(',', $rma_info['rid_list']);
                        $rest_orders[$key]['order_id_list'] = explode(',',$poundage['order_id_list']);
                        $rest_orders[$key]['poundage'] = $this->currency->format($poundage['poundage'] + ($this->config->get('config_tax') ? $rest_order['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']);
                        $rest_orders[$key]['discount_total'] = $rest_order['coupon_amount'] +  $rest_order['campaign_amount'];
                        $rest_orders[$key]['final_total'] = $this->currency->format(($rest_order['unit_price']+$rest_order['service_fee_per']+$rest_order['freight_per']+$rest_order['package_fee']) * $rest_order['quantity'] -  $rest_orders[$key]['discount_total'], session('currency'));
                        $rest_orders[$key]['total'] = $this->currency->format(($rest_order['unit_price']+$rest_order['service_fee_per']+$rest_order['freight_per']+$rest_order['package_fee']) * $rest_order['quantity'], session('currency'));

                        $product_info = $this->model_catalog_product->getProductForOrderHistory($rest_order['product_id']);

                        if ($product_info) {
                            $reorder = $this->url->link('account/order/reorder', 'order_id=' . $rest_order['rest_order_id'] . '&order_product_id=' . $rest_order['order_product_id'], true);
                        } else {
                            $reorder = '';
                        }
                        $rest_orders[$key]['reorder'] = $reorder;

                        $this->load->model('account/rma_management');
                        $advanceMarginProduct = $this->model_account_rma_management->checkMarginAdvanceProduct($rest_order['product_id']);
                        if($rest_order['product_id'] == $this->config->get('signature_service_us_product_id')){
                            $canReturn = 1;
                        }else if($advanceMarginProduct){
                            $canReturn = 2;
                        }else{
                            $canReturn = 3;
                        }
                        $rest_orders[$key]['can_return'] = $canReturn;

                        $rest_orders[$key]['return'] = $this->url->link('account/rma_management', 'filter_order_id=' . $rest_order['rest_order_id']);

                        if($rest_order['order_status_id']=='5'){
                            $rest_orders[$key]['can_review'] = true;
                        }else{
                            $rest_orders[$key]['can_review'] = false;
                        }

                        $rest_orders[$key]['reorder_product_id']       = $rest_order['product_id'];
                        $rest_orders[$key]['reorder_quantity']         = $rest_order['quantity'];
                        $rest_orders[$key]['reorder_transaction_type'] = $margin_id . '_2';
                    }
                    $data['restOrderList'] = $rest_orders;
                }
            }

            $data['rebate_logo'] = '/image/product/rebate_15x15.png';
            $data['back_url'] = $this->url->link('account/order', '', true);
            $data['continue'] = $this->url->link('account/order', '', true);
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');
            $this->response->setOutput($this->load->view('account/purchase_order_info', $data));
        } else {
            return new Action('error/not_found');
        }

    }


	public function info() {
		$this->load->language('account/order');
		$order_id = (int)request()->get('order_id', 0);

		if (!$this->customer->isLogged()) {
			session()->set('redirect', $this->url->link('account/order/info', 'order_id=' . $order_id, true));

			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->model('account/order');

		$order_info = $this->model_account_order->getOrder($order_id);
//        $run_id = time();
//        $end_date = strtotime("+3 month",strtotime($order_info['date_added']));
//        if($run_id<$end_date&&$order_info['order_status_id']==OcOrderStatus::COMPLETED){
//            $data['can_review'] = true;
//        }else{
//            $data['can_review'] = false;
//        }
        //add by xxli
        if($order_info['order_status_id']==OcOrderStatus::COMPLETED){
            $data['can_review'] = true;
        }else{
            $data['can_review'] = false;
        }
        //end xxli
		if ($order_info) {
			$this->document->setTitle($this->language->get('text_order'));

			$url = '';

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$data['breadcrumbs'] = array();

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home')
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_account'),
				'href' => $this->url->link('account/account', '', true)
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('account/order', $url, true)
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_order'),
				'href' => $this->url->link('account/order/info', 'order_id=' . $order_id . $url, true)
			);


            // marketplace
            $this->load->model('account/customerpartner');
            $data['button_order_detail'] = $this->language->get('button_order_detail');
            $data['text_tracking'] = $this->language->get('text_tracking');
            $data['module_marketplace_status'] = $this->config->get('module_marketplace_status');
            // marketplace

			if (isset($this->session->data['error'])) {
				$data['error_warning'] = session('error');

				$this->session->remove('error');
			} else {
				$data['error_warning'] = '';
			}

			if (isset($this->session->data['success'])) {
				$data['success'] = session('success');

				$this->session->remove('success');
			} else {
				$data['success'] = '';
			}

			if ($order_info['invoice_no']) {
				$data['invoice_no'] = $order_info['invoice_prefix'] . $order_info['invoice_no'];
			} else {
				$data['invoice_no'] = '';
			}

			$data['order_id'] = $order_id;
			$data['date_added'] = date($this->language->get('datetime_format'), strtotime($order_info['date_added']));

			if ($order_info['payment_address_format']) {
				$format = $order_info['payment_address_format'];
			} else {
				$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
			}

			$find = array(
				'{firstname}',
				'{lastname}',
				'{company}',
				'{address_1}',
				'{address_2}',
				'{city}',
				'{postcode}',
				'{zone}',
				'{zone_code}',
				'{country}'
			);

			$replace = array(
				'firstname' => $order_info['payment_firstname'],
				'lastname'  => $order_info['payment_lastname'],
				'company'   => $order_info['payment_company'],
				'address_1' => $order_info['payment_address_1'],
				'address_2' => $order_info['payment_address_2'],
				'city'      => $order_info['payment_city'],
				'postcode'  => $order_info['payment_postcode'],
				'zone'      => $order_info['payment_zone'],
				'zone_code' => $order_info['payment_zone_code'],
				'country'   => $order_info['payment_country']
			);

			$data['payment_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

			$data['payment_method'] = $order_info['payment_method'];

            if($order_info['payment_method'] == 'Line Of Credit' && $this->customer->getAdditionalFlag() == 1){
                $data['payment_method'] = 'Line Of Credit(+1%)';
            }

			if ($order_info['shipping_address_format']) {
				$format = $order_info['shipping_address_format'];
			} else {
				$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
			}

			$find = array(
				'{firstname}',
				'{lastname}',
				'{company}',
				'{address_1}',
				'{address_2}',
				'{city}',
				'{postcode}',
				'{zone}',
				'{zone_code}',
				'{country}'
			);

			$replace = array(
				'firstname' => $order_info['shipping_firstname'],
				'lastname'  => $order_info['shipping_lastname'],
				'company'   => $order_info['shipping_company'],
				'address_1' => $order_info['shipping_address_1'],
				'address_2' => $order_info['shipping_address_2'],
				'city'      => $order_info['shipping_city'],
				'postcode'  => $order_info['shipping_postcode'],
				'zone'      => $order_info['shipping_zone'],
				'zone_code' => $order_info['shipping_zone_code'],
				'country'   => $order_info['shipping_country']
			);

			$data['shipping_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

			$data['shipping_method'] = $order_info['shipping_method'];

			$this->load->model('catalog/product');
			$this->load->model('tool/upload');
            $this->load->model('tool/image');

			// Products
			$data['products'] = array();

			$products = $this->model_account_order->getOrderProducts($order_id);

			foreach ($products as $product) {
				$option_data = array();

				$options = $this->model_account_order->getOrderOptions($order_id, $product['order_product_id']);

				foreach ($options as $option) {
					if ($option['type'] != 'file') {
						$value = $option['value'];
					} else {
						$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

						if ($upload_info) {
							$value = $upload_info['name'];
						} else {
							$value = '';
						}
					}

					$option_data[] = array(
						'name'  => $option['name'],
						'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
					);
				}

				$product_info = $this->model_catalog_product->getProductForOrderHistory($product['product_id']);

				//add by xxli 获取产品的评价信息

                $customerName = $this->model_account_order->getCustomerName($product_info['customer_id']);

                //$reviewResult = $this->model_account_order->getReviewInfo($order_id,$product['order_product_id']);


                //end

				if ($product_info) {
					$reorder = $this->url->link('account/order/reorder', 'order_id=' . $order_id . '&order_product_id=' . $product['order_product_id'], true);
				} else {
					$reorder = '';
				}

                $tag_array = $this->model_catalog_product->getProductSpecificTag($product['product_id']);
                $tags = array();
                if(isset($tag_array)){
                    foreach ($tag_array as $tag){
                        if(isset($tag['origin_icon']) && !empty($tag['origin_icon'])){
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip"  class="'.$tag['class_style']. '"  title="'.$tag['description']. '" style="padding-left: 1px" src="'.$img_url.'">';
                        }
                    }
                }

				$data['products'][] = array(
					'name'     => $product['name'],
					'model'    => $product['model'],
					'option'   => $option_data,
					'quantity' => $product['quantity'],
					'price'    => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
					'total'    => $this->currency->format(round($product['price'],2)*$product['quantity'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order_info['currency_code'], $order_info['currency_value']),
					'reorder'  => $reorder,
                           'mpn' => $product_info['mpn'],
                           'sku' => $product_info['sku'],
                           // marketplace
                          'order_detail'   => $this->url->link('account/customerpartner/order_detail', 'order_id=' . $order_info['order_id'] . '&product_id=' . $product['product_id'], true),
                          'order_id' => $product['order_id'],
                          'product_id' => $product['product_id'],
                           // marketplace

					'return'   => $this->url->link('account/rma_management', 'filter_order_id=' . $order_info['order_id']),

                    // add by xxli reviewInfo
                    'customerName' => $customerName['screenname'],
                    'order_product_id' =>$product['order_product_id'],
                    'customer_id' =>$product_info['customer_id'],
                    'tag'       => $tags
                    // end xxli
				);
			}

			// Voucher
			$data['vouchers'] = array();

			$vouchers = $this->model_account_order->getOrderVouchers($order_id);

			foreach ($vouchers as $voucher) {
				$data['vouchers'][] = array(
					'description' => $voucher['description'],
					'amount'      => $this->currency->format($voucher['amount'], $order_info['currency_code'], $order_info['currency_value'])
				);
			}

			// Totals
			$data['totals'] = array();

			$totals = $this->model_account_order->getOrderTotals($order_id);

			foreach ($totals as $total) {
                if($total['title']=='Quote Discount'){
                    $data['totals'][] = array(
                        'title' => 'Total Item Discount',
                        'text' => $this->currency->format($total['value'], session('currency')),
                    );
                }elseif ($total['title']=='Service Fee'){
                    $data['totals'][] = array(
                        'title' => 'Total Service Fee',
                        'text' => $this->currency->format($total['value'], session('currency')),
                    );
                }elseif ($total['title']=='Poundage'){
                    $data['totals'][] = array(
                        'title' => 'Total Transaction Fee',
                        'text' => $this->currency->format($total['value'], session('currency')),
                    );
                }else {
                    $data['totals'][] = array(
                        'title' => $total['title'],
                        'text' => $this->currency->format($total['value'], session('currency')),
                    );
                }
			}

			$data['comment'] = htmlspecialchars(nl2br($order_info['comment']));

			// History
			$data['histories'] = array();

			$results = $this->model_account_order->getOrderHistories($order_id);

			foreach ($results as $result) {
				$data['histories'][] = array(
					'date_added' => date($this->language->get('datetime_format'), strtotime($result['date_added'])),
					'status'     => $result['status'],
					'comment'    => $result['notify'] ? nl2br($result['comment']) : ''
				);
			}

			$data['continue'] = $this->url->link('account/order', '', true);

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('account/order_info', $data));
		} else {
			return new Action('error/not_found');
		}
	}

	public function reorder() {
		$this->load->language('account/order');

		$order_id = (int)request()->get('order_id', 0);

		$this->load->model('account/order');

		$order_info = $this->model_account_order->getOrder($order_id);

		if ($order_info) {
			if (isset($this->request->get['order_product_id'])) {
				$order_product_id = $this->request->get['order_product_id'];
			} else {
				$order_product_id = 0;
			}

			$order_product_info = $this->model_account_order->getOrderProduct($order_id, $order_product_id);

			if ($order_product_info) {
				$this->load->model('catalog/product');

				$product_info = $this->model_catalog_product->getProduct($order_product_info['product_id']);

				if ($product_info) {
					$option_data = array();

					$order_options = $this->model_account_order->getOrderOptions($order_product_info['order_id'], $order_product_id);

					foreach ($order_options as $order_option) {
						if ($order_option['type'] == 'select' || $order_option['type'] == 'radio' || $order_option['type'] == 'image') {
							$option_data[$order_option['product_option_id']] = $order_option['product_option_value_id'];
						} elseif ($order_option['type'] == 'checkbox') {
							$option_data[$order_option['product_option_id']][] = $order_option['product_option_value_id'];
						} elseif ($order_option['type'] == 'text' || $order_option['type'] == 'textarea' || $order_option['type'] == 'date' || $order_option['type'] == 'datetime' || $order_option['type'] == 'time') {
							$option_data[$order_option['product_option_id']] = $order_option['value'];
						} elseif ($order_option['type'] == 'file') {
							$option_data[$order_option['product_option_id']] = $this->encryption->encrypt($this->config->get('config_encryption'), $order_option['value']);
						}
					}

					$this->cart->add($order_product_info['product_id'], $order_product_info['quantity'], $option_data);

					session()->set('success', sprintf($this->language->get('text_success'), $this->url->link('product/product', 'product_id=' . $product_info['product_id']), $product_info['name'], $this->url->link('checkout/cart')));

					$this->session->remove('shipping_method');
					$this->session->remove('shipping_methods');
					$this->session->remove('payment_method');
					$this->session->remove('payment_methods');
				} else {
					session()->set('error', sprintf($this->language->get('error_reorder'), $order_product_info['name']));
				}
			}
		}

		$this->response->redirect($this->url->link('account/order/purchaseOrderInfo', 'order_id=' . $order_id));
	}

	//add by xxli
    public function write() {
        $this->load->language('product/product');
        $this->load->model('account/order');
        $json = array();

        if (request()->isMethod('POST')) {
            if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 25)) {
                $json['error'] = $this->language->get('error_name');
            }

            if ((utf8_strlen($this->request->post['text']) < 25) || (utf8_strlen($this->request->post['text']) > 1000)) {
                $json['error'] = $this->language->get('error_text');
            }

            if (empty($this->request->post['rating']) || $this->request->post['rating'] < 0 || $this->request->post['rating'] > 5) {
                $json['error'] = $this->language->get('error_rating');
            }
            if (empty($this->request->post['seller_rating']) || $this->request->post['seller_rating'] < 0 || $this->request->post['seller_rating'] > 5) {
                $json['error'] = $this->language->get('error_rating');
            }
            $order_info = $this->model_account_order->getOrder($this->request->post['order_id']);
            $run_id = time();
            $end_date = strtotime("+3 month",strtotime($order_info['date_added']));
            if($run_id>$end_date){
                $json['error'] = 'Review orders for three months only.';
            }
            if(isset($this->request->post['review_id']) && $this->request->post['review_id']!='') {
                if($this->request->post['buyer_review_number']>=3){
                    $json['error'] = 'Review can only be modified 3 times.';
                }
            }
            // Captcha
            if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('review', (array)$this->config->get('config_captcha_page'))) {
                $captcha = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha') . '/validate');

                if ($captcha) {
                    $json['error'] = $captcha;
                }
            }

            if (!isset($json['error'])) {
                $this->load->model('catalog/review');
                if(isset($this->request->post['review_id']) && $this->request->post['review_id']!='') {
                    $review_id=$this->request->post['review_id'];
                    $this->model_account_order->editReview($this->request->post['product_id'],$review_id, $this->request->post);
                    //上传附件
                    $files = $this->request->files;
                    $run_id = time();
                    $index=0;
                    foreach ($files as $key => $result) {
                        if($result['error'] == '0'){
                            $index++;
                            if (!file_exists(DIR_REVIEW_FILE)) {
                                mkdir(DIR_REVIEW_FILE, 0777, true);
                            }
                            if (!file_exists(DIR_REVIEW_FILE . $review_id)) {
                                mkdir(DIR_REVIEW_FILE . $review_id, 0777, true);
                            }
                            $splitStr = explode('.', $result['name']);
                            $file_type = $splitStr[count($splitStr) - 1];
                            $file_path = DIR_REVIEW_FILE . $review_id . "/" . $run_id . '_'.$index . '.' . $file_type;
                            move_uploaded_file($result['tmp_name'], $file_path);
                            $filePath = $review_id . "/" . $run_id . '_' . $index . '.' . $file_type;
                            $fileName = $run_id . '_' . $index . '.' . $file_type;
                            $this->model_account_order->addReviewFile($review_id,$filePath,$fileName,$this->customer->getId());
                        }
                    }
                }else{
                    $review_id = $this->model_account_order->addReview($this->request->post['product_id'], $this->request->post);
                    //上传附件
                    $files = $this->request->files;
                    $run_id = time();
                    $index=0;
                    foreach ($files as $key => $result) {
                        if($result['error'] == '0'){
                            $index++;
                            if (!file_exists(DIR_REVIEW_FILE)) {
                                mkdir(DIR_REVIEW_FILE, 0777, true);
                            }
                            if (!file_exists(DIR_REVIEW_FILE . $review_id)) {
                                mkdir(DIR_REVIEW_FILE . $review_id, 0777, true);
                            }
                            $splitStr = explode('.', $result['name']);
                            $file_type = $splitStr[count($splitStr) - 1];
                            $file_path = DIR_REVIEW_FILE . $review_id . "/" . $run_id . '_'.$index . '.' . $file_type;
                            move_uploaded_file($result['tmp_name'], $file_path);
                            $filePath = $review_id . "/" . $run_id . '_' . $index . '.' . $file_type;
                            $fileName = $run_id . '_' . $index . '.' . $file_type;
                            $this->model_account_order->addReviewFile($review_id,$filePath,$fileName,$this->customer->getId());
                        }
                    }
                }

                $json['success'] = $this->language->get('text_success');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }


    public function reviewInfo() {
        if (!$this->customer->isLogged()) {
            $json['error'] = true;
            $json['url'] =  $this->url->link('account/login');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
        }

        $firstName = $this->customer->getFirstname();
        $lastName = $this->customer->getLastname();
        $this->load->model('account/order');
        $order_id = $this->request->request['orderId'];
        $order_product_id = $this->request->request['orderProductId'];
        $product_id = $this->request->request['productId'];
        $reviewResult = $this->model_account_order->getReviewInfo($order_id,$order_product_id);
        $nickName = $this->customer->getNickName();
        if($reviewResult){
            $json['edit'] = true;
            $json['review_id'] = $reviewResult['review_id'];
            $json['author'] = $nickName;
            $json['text'] = $reviewResult['text'];
            $json['rating'] = $reviewResult['rating'];
            $json['seller_rating'] = $reviewResult['seller_rating'];
            $json['product_id'] = $product_id;
            $json['order_id'] = $order_id;
            $json['order_product_id'] = $order_product_id;
            $json['buyer_review_number'] = $reviewResult['buyer_review_number'];
            $json['seller_review_number'] = $reviewResult['seller_review_number'];
            $files = $this->model_account_order->getReviewFile($reviewResult['review_id']);

            $index = 0;
            foreach ($files as $file){
                $json['img'.$index] = 'storage/reviewFiles/'.$file['path'];
                $index++;
            }
        }else{
            $json['add'] = true;
            $json['author'] = $nickName;
            $json['product_id'] = $product_id;
            $json['order_id'] = $order_id;
            $json['order_product_id'] = $order_product_id;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function deleteFiles(){
        $this->load->model('account/order');
        $filePath = $this->request->request['path'];
        $this->model_account_order->deleteFiles($filePath);
        //删除服务器文件
        unlink(DIR_REVIEW_FILE.$this->request->request['path']);
    }

    private function getOrderPoundage($mapProduct)
    {
        $new_res = $this->orm->table(DB_PREFIX . 'order as oco')
            ->leftJoin(DB_PREFIX . 'order_product as op', function ($join) {
            $join->on('op.order_id', '=', 'oco.order_id');
        })
            ->leftJoin('tb_sys_order_associated as as', function ($join) {
                $join->on('as.order_id', '=', 'oco.order_id')
                    ->on('as.product_id', '=', 'op.product_id');
            })
            ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'as.sales_order_id')
//            ->leftJoin(DB_PREFIX . 'product_quote as pq', function ($join) {
//                $join->on('pq.order_id', '=', 'oco.order_id')
//                    ->on('pq.product_id', '=', 'op.product_id');
//            })
            ->leftJoin('oc_order_quote as oq',[['oq.order_id','=','op.order_id'],['oq.product_id','=','op.product_id']])
            ->leftJoin('oc_product_quote as pq',[['pq.order_id','=','oco.order_id'],['pq.product_id','=','op.product_id']])
            //->leftJoin(DB_PREFIX.'yzc_rma_order as r',function ($join){
            //    $join->on('r.order_id','=','as.order_id')->on('r.buyer_id','=','oco.customer_id');
            //})
            //->leftJoin(DB_PREFIX.'yzc_rma_order_product as rp',function ($join){
            //    $join->on('rp.rma_id','=','r.id')->on('rp.product_id','=','as.product_id');
            //})
            ->where($mapProduct)
            ->groupBy('as.order_id', 'as.product_id')
            ->select('op.product_id','oq.amount_data','pq.price as pq_price', 'op.poundage', 'op.service_fee','op.service_fee_per','pq.amount_price_per','pq.amount_service_fee_per')
            ->selectRaw('oq.product_id as quote_product_id')
            ->selectRaw('group_concat( distinct o.order_id) as order_id_list,group_concat( distinct o.id) as oid_list')
            ->first();
        $new_res = obj2array($new_res);
        return $new_res;
    }

    private function getOrderRma($mapRma){
        $rma_info = $this->orm->table(DB_PREFIX.'yzc_rma_order as r')
            ->leftJoin(DB_PREFIX.'yzc_rma_order_product as rp','rp.rma_id','=','r.id')->where($mapRma)->
            selectRaw('group_concat( distinct r.rma_order_id) as rma_id_list,group_concat( distinct r.id) as rid_list')->first();
        $rma_info = obj2array($rma_info);
        return $rma_info;
    }

    // 首页
    public function index()
    {
        $this->load->language('account/order');
        $this->document->setTitle($this->language->get('text_purchase_order_management'));
        $this->document->addScript('catalog/view/javascript/jquery/jquery.cookie.min.js');
        $orderId = request('order_id', '');
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        ];
        $data['breadcrumbs'][] = [
            'text' => 'Purchase Order Management',
            'href' => 'javascript:void(0)'
        ];
        // url
        $data['purchase_order_url'] = url()->withRoute('account/order/purchaseOrder')->withQueries(array_filter(['filter_PurchaseOrderId' => $orderId,]))->build();
        $data['fee_order_url'] = url()->withRoute('account/order/feeOrder')->withQueries(array_filter([
            'fee_order_id' => $this->request->get('fee_order_id'),
            'filterOrderId' => $this->request->get('filter_fee_order_no')
        ]))->build();
        $data['invoice_url'] = url()->to('account/purchase_order/invoice');
        $data['is_america'] = $this->customer->isUSA();
        return $this->render('account/purchase_order_list', $data, 'buyer');
    }

    // region 费用单列表页面
    public function feeOrder()
    {
        $this->load->model('tool/image');
        $data = [];
        // 获取数据
        $search = new FeeOrderSearch($this->customer->getId());
        $dataProvider = $search->search($this->request->query->all());
        /** @var FeeOrder[]|Collection $list */
        $list = $dataProvider->getList();

        $storageFeeRepo = app(StorageFeeRepository::class);
        $orderRepo = app(OrderRepository::class);
        $safeguardConfigRepo = app(SafeguardConfigRepository::class);
        $list->map(function (FeeOrder $item) use ($storageFeeRepo, $orderRepo, $safeguardConfigRepo) {
            $details = [];
            if ($item->fee_type === FeeOrderFeeType::STORAGE) {
                $details = Arr::get($storageFeeRepo->getDetailsByFeeOrder((array)$item->id, true), $item->id);
                array_walk($details, function (&$detail) {
                    $detail['image_url'] = $this->model_tool_image->resize($detail['product_image'], 40, 40);
                });
            } elseif ($item->fee_type === FeeOrderFeeType::SAFEGUARD) {
                $details = $safeguardConfigRepo->getConfigDetailsByFeeOrder($item, customer()->getCountryId());
            }
            $item->setAttribute('details', $details);
            if ($item->status == FeeOrderStatus::WAIT_PAY) {
                // 倒计时
                $countDownTime = strtotime($item->created_at . '+30 minutes') - time();
                $item->setAttribute('countDownTime', $countDownTime);
                if ($countDownTime > 0) {
                    $countMinute = '0' . floor($countDownTime / 60);
                    $item->setAttribute('countMinute', substr($countMinute, strlen($countMinute) - 2));
                    $countSecond = '0' . ($countDownTime % 60);
                    $item->setAttribute('countSecond', substr($countSecond, strlen($countSecond) - 2));
                }
            }
            // 获取绑定的采购单信息
            $relateOrderIds = [];
            foreach ($orderRepo->getOrderByFeeOrderId($item->id) as $order) {
                if ($order->order_status_id == OcOrderStatus::TO_BE_PAID) {
                    $relateOrderIds[] = $order->order_id;
                }
            }
            $relateOrderIds = array_unique($relateOrderIds);
            $relateOrderIds = array_merge($relateOrderIds, app(FeeOrderRepository::class)->getFeeOrderRelates($item)->pluck('order_no')->toArray());
            $item->setAttribute('relateOrderIds', $relateOrderIds);
        });
        $data['total'] = $dataProvider->getTotalCount(); // 总计
        $data['list'] = $list;  // 列表
        $data['paginator'] = $dataProvider->getPaginator();
        $data['sort'] = $dataProvider->getSort();
        $data['search'] = $search->getSearchData();
        $data['feeOrderStatusList'] = FeeOrderStatus::getViewItems();
        $data['createDateRangeList'] = CreateDateRange::getWebSelect();
        $data['feeOrderTypeList'] = FeeOrderFeeType::getViewItems();
        $data['feeOrderUrl'] = $this->url->link('account/order/feeOrder');
        $data['feeOrderDownloadUrl'] = $this->url->link('account/order/feeOrderDownload');
        $data['feeOrderCancelUrl'] = $this->url->link('account/order/feeOrderCancel');
        $data['feeOrderPayUrl'] = $this->url->link('account/fee_order/fee_order/getFeeOrderPurchasePage');
        $data['currency'] = $this->session->get('currency');
        $data['currency_symbol'] = $this->currency->getSymbolLeft($data['currency']) . $this->currency->getSymbolRight($data['currency']);
        $data['fee_order_exist'] = $data['total'] <= 0 ? $search->checkFeeOrderExists() : true;
        $data['is_collect_form_domicile'] = $this->customer->isCollectionFromDomicile();
        $data['success'] = $this->request->get('success') ? true : false;
        $data['country_id'] = $this->country_id;
        $data['is_europe'] = in_array(customer()->getCountryId(), [Country::BRITAIN, Country::GERMANY]);
        return $this->render('account/sales_order/fee_order_list', $data);
    }
    // endregion

    // region 费用单cancel
    public function feeOrderCancel()
    {
        $this->load->language('account/order');
        $orderNo = $this->request->attributes->get('order_no', '');
        $feeOrder = FeeOrder::query()->where(['order_no' => $orderNo, 'buyer_id' => (int)$this->customer->getId()])->first();
        $ret = ['code' => 1, 'msg' => 'failed'];
        if (!$feeOrder) {
            return $this->response->json($ret);
        }

        // 查找需要释放销售订单囤货预绑定的销售订单ID
        $releaseInventoryLockSalesOrderIds = [];
        if ($feeOrder->order_type == FeeOrderOrderType::SALES && $feeOrder->status == FeeOrderStatus::WAIT_PAY) {
            $releaseInventoryLockSalesOrderIds[] = $feeOrder->order_id;
        }
        foreach (app(FeeOrderRepository::class)->getFeeOrderRelates($feeOrder) as $feeOrderRelate) {
            /** @var FeeOrder $feeOrderRelate */
            if ($feeOrderRelate->order_type == FeeOrderOrderType::SALES && $feeOrderRelate->status == FeeOrderStatus::WAIT_PAY) {
                $releaseInventoryLockSalesOrderIds[] = $feeOrderRelate->order_id;
            }
        }

        $con = $this->orm->getConnection();
        try {
            $con->beginTransaction();
            app(FeeOrderService::class)->changeFeeOrderStatus($feeOrder, FeeOrderStatus::EXPIRED);

            // 释放销售订单囤货预绑定
            if ($releaseInventoryLockSalesOrderIds) {
                app(BuyerStockService::class)->releaseInventoryLockBySalesOrderPreAssociated($releaseInventoryLockSalesOrderIds, $feeOrder->buyer_id);
            }

            $ret = ['code' => 0, 'msg' => 'ok'];
            $con->commit();
        } catch (Exception $e) {
            $con->rollBack();
            // 提示信息可能不一样
            $errorMsg = in_array($e->getCode(), [FeeOrderExceptionCode::ALREADY_CANCELED, FeeOrderExceptionCode::ALREADY_PAID])
                ? $this->language->get('msg_fee_order_cancel_fail')
                : 'failed';
            $ret = ['code' => 1, 'msg' => $errorMsg];
            Logger::error($e);
        }
        return $this->response->json($ret);
    }
    // endregion

    // region 费用单download
    public function feeOrderDownload()
    {
        set_time_limit(0);// 脚本执行时间无限
        // 载入model
        $search = new FeeOrderSearch($this->customer->getId());
        $dataProvider = $search->search($this->request->query->all(), true);
        /** @var Collection $list */
        $list = $dataProvider->getList();
        // 保留小数位
        $formatCode = $this->customer->isJapan() ? '0' : '0.00';
        try {
            $exportList = $this->resolveFeeOrderExportList($list);
            $activeIndex = 0;
            // 主sheet一定要有
            $spreadsheet = new Spreadsheet();
            $spreadsheet->setActiveSheetIndex($activeIndex++);
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->getStyle('L')->getNumberFormat()->setFormatCode($formatCode);
            $sheet->setTitle('All')->fromArray($exportList['all']);
            $sheet->freezePane('A2');
            // 其他根据返回数据情况返回
            if (!empty($exportList['others'])) {
                foreach ($exportList['others'] as $title => $feeTypeList) {
                    $spreadsheet->createSheet();
                    $spreadsheet->setActiveSheetIndex($activeIndex++);
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheet->getStyle('L')->getNumberFormat()->setFormatCode($formatCode);
                    $sheet->setTitle($title);
                    $sheet->fromArray($feeTypeList);
                    $sheet->freezePane('A2');
                }
            }
            $writer = IOFactory::createWriter($spreadsheet, 'Xls');
            $spreadsheet->setActiveSheetIndex(0);
            return $this->response->streamDownload(
                function () use ($writer) {
                    $writer->save('php://output');
                }, 'Charges Orders.xls', ['Content-Type' => 'application/vnd.ms-excel']
            );
        } catch (Exception $e) {
            Logger::error($e);
            return $this->response->redirectTo($this->url->link('error/not_found'));
        }
    }

    /**
     * 构建费用单导出数据
     *
     * @param FeeOrder[]|Collection $list
     * @return array [all=>[],others=>['title'=>[{导出的数据}]]]]
     */
    private function resolveFeeOrderExportList(Collection $list)
    {
        // 币种符号
        $currency = $this->session->get('currency');
        $currencySymbol = $this->currency->getSymbolLeft($currency) ?: $this->currency->getSymbolRight($currency);
        $storageFeeRepo = app(StorageFeeRepository::class);
        $safeguardConfigFeeRepo = app(SafeguardConfigRepository::class);
        bcscale(2);
        //region All 表头和统计变量
        $allList[] = [
            'Purchase Order ID', 'Related Order ID', 'Charge Type', 'Status',
            'Creation Time', "Order Amount(({$currencySymbol}))", "Paid Amount({$currencySymbol})", 'Checkout Time',
            "Refund Amount({$currencySymbol})", 'Refund Time'
        ];
        $allTotalFee = 0;
        $allTotalActualFee = 0;
        $allTotalRefundFee = 0;
        //endregion
        //region storage 表头和统计变量
        $storageList[] = [
            'Purchase Order ID', 'Related Order ID', 'Charge Type', 'Status', 'Creation Time',
            'Item Code', 'Seller', 'Product Volume(m³)', 'QTY', 'Days in Inventory', "Storage Fee({$currencySymbol})",
            "Paid Amount({$currencySymbol})", 'Checkout Time'
        ];
        $storageTotalQty = 0;
        $storageTotalFee = 0;
        $storageTotalActualFee = 0;
        //endregion
        //region safeguard 表头和统计变量
        $safeguardList[] = [
            'Purchase Order ID', 'Related Order ID', 'Charge Type', 'Status', 'Creation Time',
            'Protection Service', "Base of Protection Service Fee({$currencySymbol})", 'Protection Service Rate', "Total Protection Service Fee({$currencySymbol})",
            "Paid Amount({$currencySymbol})", 'Checkout Time', "Refund Amount({$currencySymbol})", 'Refund Time'
        ];
        $safeguardTotalFee = 0;
        $safeguardTotalRefundFee = 0;
        //endregion
        //region 构建数据
        foreach ($list as $item) {
            // 先插入all
            $feeTypeStr = FeeOrderFeeType::getDescription($item->fee_type);
            $feeStatusStr = FeeOrderStatus::getDescription($item->status);
            $creationTime = currentZoneDate($this->session, $item->created_at->toDateTimeString());
            $checkoutTime = in_array($item->status, [FeeOrderStatus::EXPIRED, FeeOrderStatus::WAIT_PAY]) ? 'N/A' : currentZoneDate($this->session, $item->paid_at->toDateTimeString());
            $refundedTime = $item->status !== FeeOrderStatus::REFUND ? 'N/A' : currentZoneDate($this->session, $item->refunded_at->toDateTimeString());
            $actualFee = in_array($item->status, [FeeOrderStatus::EXPIRED, FeeOrderStatus::WAIT_PAY]) ? 0 : $item->actual_paid;
            $refundFee = $item->status !== FeeOrderStatus::REFUND ? 0 : $item->refund_amount;
            $relatedOrderId = $item->orderInfo->order_id;
            $allList[] = [
                $item->order_no, $relatedOrderId . "\t", $feeTypeStr, $feeStatusStr, $creationTime,
                (string)$this->currency->formatCurrencyPrice($item->fee_total, $currency, 1, false),
                (string)$this->currency->formatCurrencyPrice($actualFee, $currency, 1, false), $checkoutTime,
                (string)$this->currency->formatCurrencyPrice($refundFee, $currency, 1, false), $refundedTime,
            ];
            $allTotalFee += $item->fee_total;
            $allTotalActualFee += $actualFee;
            $allTotalRefundFee += $refundFee;
            // 再按类型插入其他子表
            switch ($item->fee_type) {
                case FeeOrderFeeType::STORAGE:
                    $details = Arr::get($storageFeeRepo->getDetailsByFeeOrder((array)$item->id, true), $item->id);
                    $paid = $item->actual_paid;
                    foreach ($details as $key => $detail) {
                        if ($detail['need_pay'] <= 0) {
                            continue;
                        }
                        if (bccomp($paid, $detail['need_pay']) === -1) {
                            $detailPaid = $paid;
                            $paid = 0;
                        } else {
                            $detailPaid = $detail['need_pay'];
                            $paid = bcsub($paid, $detail['need_pay']);
                        }

                        $storageList[] = [
                            $item->order_no, $relatedOrderId . "\t", $feeTypeStr, $feeStatusStr, $creationTime,
                            $detail['item_code'], $detail['seller_store_name'], $detail['volume'], $detail['qty'], $detail['days'],
                            (string)$this->currency->formatCurrencyPrice($detail['need_pay'], $currency, 1, false),
                            (string)$this->currency->formatCurrencyPrice($detailPaid, $currency, 1, false), $checkoutTime
                        ];
                        $storageTotalQty += $detail['qty'];
                        $storageTotalFee += $detail['need_pay'];
                        $storageTotalActualFee += $detailPaid;
                    }
                    break;
                case FeeOrderFeeType::SAFEGUARD:
                    $details = $safeguardConfigFeeRepo->getConfigDetailsByFeeOrder($item, customer()->getCountryId());
                    $detailEndKey = count($details) - 1;// 计算最后一个detail的key
                    $detailRefundFeeTotal = 0;
                    foreach ($details as $key => $detail) {
                        // 退款金额要按比例拆分
                        if ($key === $detailEndKey) {
                            $detailRefundFee = $refundFee - $detailRefundFeeTotal;
                        } else {
                            $detailRefundFeeTotal += $detailRefundFee = ($detail['safeguard_fee'] / $item->fee_total) * $refundFee;
                        }
                        $safeguardList[] = [
                            $item->order_no, $relatedOrderId . "\t", $feeTypeStr, $feeStatusStr, $creationTime,
                            $detail['safeguard_title'], $this->currency->formatCurrencyPrice($detail['order_base_amount'], $currency, 1, false),
                            $detail['service_rate'],
                            (string)$this->currency->formatCurrencyPrice($detail['safeguard_fee'], $currency, 1, false),
                            // 保障服务费是需要付多少就付多少，不会出现少付的情况，所以这里用需支付就行
                            (string)$this->currency->formatCurrencyPrice($detail['safeguard_fee'], $currency, 1, false),
                            $checkoutTime,
                            (string)$this->currency->formatCurrencyPrice($detailRefundFee, $currency, 1, false),
                            $refundedTime
                        ];
                        $safeguardTotalFee += $detail['safeguard_fee'];
                        $safeguardTotalRefundFee += $detailRefundFee;
                    }
                    break;
            }
        }
        //endregion
        //region 填充统计行和构建数据
        // 除了all，其他子表如果没有数据就不返回了
        $res = [];
        if (count($allList) > 1) {
            $allList[] = ['Total', '', '', '', '', (string)$allTotalFee, (string)$allTotalActualFee, '', (string)$allTotalRefundFee, ''];
        }
        $res['all'] = $allList;
        if (count($storageList) > 1) {
            $storageList[] = ['Total', '', '', '', '', '', '', '', (string)$storageTotalQty, '', (string)$storageTotalFee, (string)$storageTotalActualFee, ''];
            $res['others'][FeeOrderFeeType::getDescription(FeeOrderFeeType::STORAGE)] = $storageList;
        }
        if (count($safeguardList) > 1) {
            $safeguardList[] = ['Total', '', '', '', '', '', '', '', (string)$safeguardTotalFee, (string)$safeguardTotalFee, '', (string)$safeguardTotalRefundFee, ''];
            $res['others'][FeeOrderFeeType::getDescription(FeeOrderFeeType::SAFEGUARD)] = $safeguardList;
        }
        //endregion
        return $res;
    }
    // endregion

    public function reorderAdd()
    {
        $posts = $this->request->post;
        $order_id = $posts['order_id'];
        $this->load->model('account/order');
        $json = $this->model_account_order->addReorderProductIntoCart($order_id,$this->customer_id);
        return $this->response->json($json);
    }

    public function judgeSalesOrderStatus(FeeOrderRepository $feeOrderRepository)
    {
        $posts = $this->request->input;
        $order_id = $posts->get('order_id');
        $feeOrderId = $posts->get('fee_order_id','');
        $this->load->model('account/order');
        $json = $this->model_account_order->judgeSalesOrderStatus($order_id,$this->customer_id);
        $orderProduct = $this->model_account_order->checkOrderProductStatus($order_id,$this->customer_id);
        if ($orderProduct != 1) {
            $json['error'] = 1;
        }
        //4414 增加费用单是否能支付校验
        if ($json['error'] && $feeOrderId) {
            $feeOrderId = explode(',', $feeOrderId);
            if (!($feeOrderRepository->checkFeeOrderNeedPay($feeOrderId))) {
                $json['error'] = 1;
            }
        }
        return $this->response->json($json);
    }

    public function purchaseOrder(){

        $this->load->model('account/order');
        $this->load->model('tool/image');
        $this->load->model('catalog/product');
        $this->load->language('account/order');
        /** @var ModelAccountOrder $orderModel*/
        $orderModel = load()->model('account/order');
        $data = $this->request->request;
        // 采购订单状态
        $order_status = [
            '-1' => 'ALL',
            '0'=>'To Be Paid',
            '5'=>'Completed',
            '7'=>'Canceled'
        ];

        $order_associated = [
            '-1' => 'ALL',
            '1' => 'Matched with the sales order',
            '2' => 'Not matched with the sales order',
            '3' => 'Partially matched with the sales order',
        ];

        if(count($data) == 1){
            $data['filter_include_returns'] = 'on';
        }else{
            // filter_include_returns 默认值
            if(!isset($data['filter_include_returns'])){
                $data['filter_include_returns'] = -1;
            }
        }

        // filter_orderStatus 默认值
        if(!isset($data['filter_orderStatus'])){
            $data['filter_orderStatus'] = -1;
        }
        // 是否关联销售项
        if (!isset($data['filter_associatedOrder'])){
            $data['filter_associatedOrder'] = -1 ;
        }
        $data['filter_PurchaseOrderId'] = trim($this->request->get('filter_PurchaseOrderId', ''));
        $data['filter_item_code'] = trim($this->request->get('filter_item_code', ''));
        $data['order_status'] = $order_status;
        $data['order_associated'] = $order_associated ;
        $data['page'] = $data['page'] ?? 1;
        $data['page_limit'] = $data['page_limit'] ?? 10;
        $data['page'] = intval($data['page']);
        $data['page_limit'] = intval($data['page_limit']) ?: 10;
        // 获取采购订单总的条数
        $orders = $orderModel->getPurchaseInfo($this->customer_id, $data);
        $orderIdArr = array_column($orders,'order_id');
        // 获取采购订单的详情
        $orderInfos = $orderModel->getPurchaseOrder($orderIdArr);
        // 获取产品是否是囤货产品
        $unsupportStockMap = app(CustomerRepository::class)->getUnsupportStockData(array_column($orderInfos,'product_id'));
        $currency = session('currency');
        $purchaseOrders = [];
        $stockCheck = [];
        $tag = [];

        // 生成Invoice，订单选择处理
        $nowTime = time();
        $invoice = [];
        $isInvoice = empty($data['is_invoice']) ? 0 : 1;
        if ($this->customer->isUSA() && $isInvoice) {
            $invoice = $this->dealInvoiceSelect($data, $nowTime);
        }

        foreach ($orderInfos as $orderInfo){
            $order_id = $orderInfo['order_id'];
            // 1.如果订单完成 oc_product_quote.amount_price_per会有值
            $price_quote = !empty($orderInfo['amount_price_per']) ? $orderInfo['amount_price_per'] : 0;
            $service_fee_quote = !empty($orderInfo['amount_service_fee_per']) ? $orderInfo['amount_service_fee_per'] : 0;
            // 2.如果订单未完成 oc_product_quote.amount_price_per=0,待支付订单和cancel订单显示就不对,需要获取oc_order_quote的议价折扣来显示
            // 3. oc_order_quote.product_id是后来新添加的字段,老数据没有,所以第一条是需要的
            if ($orderInfo['amount_data'] && $orderInfo['quote_product_id'] == $orderInfo['product_id']) {
                $amountData = json_decode($orderInfo['amount_data'], true);
                $price_quote = $amountData['amount_price_per'];
                $service_fee_quote = $amountData['amount_service_fee_per'];
            }
            $orderLineTotal = ($orderInfo['price'] + $orderInfo['service_fee_per'] + $orderInfo['freight_per'] + $orderInfo['package_fee'] - $price_quote - $service_fee_quote )*$orderInfo['quantity'];
            $discountOrderTotal = $orderInfo['coupon_amount'] + $orderInfo['campaign_amount'];
            // 图片
            $orderInfo['image_show'] = $this->model_tool_image->resize($orderInfo['image'], 40, 40);
            $orderInfo['image_big'] = $this->model_tool_image->resize($orderInfo['image'], 150, 150);

            $orderInfo['product_link'] = $this->url->link('product/product', 'product_id=' . $orderInfo['product_id']);
            // action_btn
            $orderInfo['action_btn'] = $this->action_btn[$orderInfo['order_status_id']];
            // 获取小图标
            if(isset($tag[$orderInfo['product_id']])){
                $orderInfo['tag'] = $tag[$orderInfo['product_id']];
            }else{
                $orderInfo['tag'] = $this->model_catalog_product->getProductTagHtmlForThumb($orderInfo['product_id']);
                $tag[$orderInfo['product_id']] = $orderInfo['tag'];
            }
            // 获取复杂交易图标
            load()->model('account/product_quotes/rebates_agreement');
            $rebateAgreementId = $this->model_account_product_quotes_rebates_agreement->getRebateAgreementId($order_id, $orderInfo['product_id']);
            if($orderInfo['type_id'] == OcOrderTypeId::TYPE_REBATE
                || $rebateAgreementId
            ){
                $orderInfo['agreement_id'] = $rebateAgreementId ?? $orderInfo['agreement_id'];
                $orderInfo['is_rebate'] = 1;
                $agreementCode = $this->model_account_order->getRebateAgreementCode($orderInfo['agreement_id']);
                $orderInfo['tip_rebate'] = 'Click to view the rebate agreement details for agreement ID ' . $agreementCode . '.';
                $orderInfo['url_rebate'] = $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementList', ['agreement_id'=> $orderInfo['agreement_id'],'act'=>'view']);
            }else if ($orderInfo['type_id'] == OcOrderTypeId::TYPE_MARGIN) {
                $margin_info = $this->model_account_order->getMarginInfo($orderInfo['agreement_id']);
                $tip_margin = 'Click to view the margin agreement details for agreement ID ' . $margin_info['agreement_id'] . '.';
                $url_margin = $this->url->link('account/product_quotes/margin/detail_list', 'id=' . $orderInfo['agreement_id'], true);
                $orderInfo['is_margin']  = 1;
                $orderInfo['tip_margin'] = $tip_margin;
                $orderInfo['url_margin'] = $url_margin;
            } else if($orderInfo['type_id'] == OcOrderTypeId::TYPE_FUTURE){
                $future_margin_info = $this->model_account_order->getFutureMarginInfo($orderInfo['agreement_id']);
                $orderInfo['contract_id'] = $future_margin_info['contract_id'];
                $orderInfo['agreement_no'] = $future_margin_info['agreement_no'];
            }
            // 获取是否有freight图标
            $orderInfo['freight_tag'] =  $this->model_account_order->getEuropeFrightTag($orderInfo['product_id']);
            // 获取seller的url
            $orderInfo['seller_email_url'] = $this->url->link('message/seller/addMessage', 'receiver_id='.$orderInfo['seller_id'], true);
            $orderInfo['seller_url'] = $this->url->link('customerpartner/profile', 'id='.$orderInfo['seller_id'], true);
            $orderInfo['product_name'] = $orderInfo['name'];
            $orderInfo['screenname_simple'] = truncate($orderInfo['screenname'],30);
            $orderInfo['price_show'] = $this->currency->formatCurrencyPrice($orderInfo['price']-$price_quote, $currency);
            $orderInfo['service_fee_show'] = $this->currency->formatCurrencyPrice($orderInfo['service_fee_per']-$service_fee_quote, $currency);
            $orderInfo['freight_show'] = $this->currency->formatCurrencyPrice($orderInfo['freight_per']+$orderInfo['package_fee'], $currency);

            if(isset($purchaseOrders[$order_id])){
                $purchaseOrders[$order_id]['lineDatas'][] = $orderInfo;
                $stockCheck[$order_id][] = $orderInfo['product_id'];
                $purchaseOrders[$order_id]['product_type'][] = $orderInfo['product_type'];
                $purchaseOrders[$order_id]['product_all'][] = $orderInfo['product_id'];
                $purchaseOrders[$order_id]['grand_total'] = $purchaseOrders[$order_id]['grand_total'] + $orderLineTotal;
                $purchaseOrders[$order_id]['discount_grand_total'] = $purchaseOrders[$order_id]['discount_grand_total'] + $discountOrderTotal;
            }else{
                $purchaseOrders[$order_id]['order_id'] = $order_id;
                $purchaseOrders[$order_id]['order_status_id'] = $orderInfo['order_status_id'];
                $purchaseOrders[$order_id]['date_added'] = $orderInfo['date_added'];
                $purchaseOrders[$order_id]['lineDatas'][] = $orderInfo;
                $stockCheck[$order_id][] = $orderInfo['product_id'];
                $purchaseOrders[$order_id]['product_type'][] = $orderInfo['product_type'];
                $purchaseOrders[$order_id]['product_all'][] = $orderInfo['product_id'];
                $purchaseOrders[$order_id]['grand_total'] = $orderLineTotal;
                $purchaseOrders[$order_id]['discount_grand_total'] = $discountOrderTotal;
                $leftSec = strtotime( $orderInfo['date_added'] )+( $this->config->get('expire_time') *60)-time();
                $leftSec = $leftSec > 0 ? $leftSec : 0;
                $minute = ( $leftSec / 60 ) % 60;
                $second = $leftSec % 60;
                $purchaseOrders[$order_id]['leftSec'] = $leftSec;
                $purchaseOrders[$order_id]['minute'] = ($minute == 0 ? '00' : $minute);
                $purchaseOrders[$order_id]['second'] = ($second ==0 ? '00' : $second);
                $purchaseOrders[$order_id]['delivery_type'] = $orderInfo['delivery_type'];
            }

            // Invoice选择情况
            $purchaseOrders[$order_id]['invoice_is_select'] = $this->invoiceIsSelect($order_id, $isInvoice, $invoice);
        }

        foreach($stockCheck as $key => $value){
            $flag = true;
            foreach($value as $k => $v){
                if(!in_array($v,$unsupportStockMap)){
                    $flag = false;
                    break;
                }
            }
            $purchaseOrders[$key]['unsupport_stock'] = $flag;
        }
        $orderRepo = app(OrderRepository::class);
        $feeOrderRepo = app(FeeOrderRepository::class);
        foreach ($purchaseOrders as &$purchaseOrder){
            $s = array_unique($purchaseOrder['product_type']);
            $all = array_unique($purchaseOrder['product_all']);
            if(count($s) == 1 && $s[0] != 0 && $s[0] != 3){
                $purchaseOrder['rma_show'] = false;
                $purchaseOrder['reorder_show'] = false;
            }elseif(count($all) == 1 && $all[0] == $this->config->get('signature_service_us_product_id')){
                $purchaseOrder['rma_show'] = false;
                $purchaseOrder['reorder_show'] = false;
            }else{
                $purchaseOrder['rma_show'] = true;
                $purchaseOrder['reorder_show'] = true;
            }
            $purchaseOrder['grand_total_show'] = $this->currency->formatCurrencyPrice($purchaseOrder['grand_total'], $currency);
            $purchaseOrder['final_grand_total_show'] = $this->currency->formatCurrencyPrice($purchaseOrder['grand_total'] - $purchaseOrder['discount_grand_total'], $currency);
            $purchaseOrder['fee_order_ids'] = '';
            $purchaseOrder['fee_order_nos'] = '';
            $purchaseOrder['fee_order_count'] = 0;
            $purchaseOrder['purchase_run_id'] = 0;
            if ($purchaseOrder['order_status_id'] == OcOrderStatus::TO_BE_PAID) {
                $feeOrders = $orderRepo->getFeeOrderByOrderId($purchaseOrder['order_id']);
                //过滤出可以支付的订单
                $needPayFeeOrders = $feeOrderRepo->filterNeedPayFeeOrder($feeOrders);
                if ($needPayFeeOrders->isNotEmpty()) {
                    $purchaseOrder['fee_order_ids'] = $needPayFeeOrders->implode('id', ',');
                }
                //过滤出可以取消的费用单
                $canCancelFeeOrderNos = [];
                foreach ($feeOrders as $feeOrder) {
                    if ($feeOrderRepo->isFeeOrderCanCancel($feeOrder)
                        && ($feeOrder->fee_total > 0 || $feeOrder->fee_type != FeeOrderFeeType::STORAGE)) {
                        $canCancelFeeOrderNos[] = $feeOrder->order_no;
                    }
                }
                $canCancelFeeOrderNos = array_filter(array_unique($canCancelFeeOrderNos));// 过滤重复值
                $purchaseOrder['fee_order_nos'] = implode('<br>', $canCancelFeeOrderNos);
                $purchaseOrder['fee_order_count'] = count($canCancelFeeOrderNos);
                //支付时的预下单信息
                $purchaseOrder['purchase_run_id'] = $orderRepo->getOrderAssociatedPreRunId($purchaseOrder['order_id']);
            }
        }
        $data['purchaseOrders'] = $purchaseOrders;
        $data['is_europe'] =  $this->is_europe;
        $data['pay_url'] = $this->url->link('checkout/confirm/toPay&order_id=');
        $data['detail_url'] = $this->url->link('account/order/purchaseOrderInfo&order_id=');
        $data['rebate_logo'] = '/image/product/rebate_15x15.png';
        // 分页信息
        $data['total'] = $orderModel->getPurchaseInfoTotal($this->customer_id, $data);
        $data['total_pages'] = ceil($data['total'] / $data['page_limit']);
        $data['order_url'] = $this->url->link('account/order/purchaseOrderList');
        $data['is_collection_from_domicile']  = (int)customer()->isCollectionFromDomicile();
        $data['last_request_time'] = $nowTime;
        $data['invoice_select_count'] = $this->dealInvoiceCount($invoice, $data['total']);

        return $this->render('account/sales_order/sales_order_purchase_list', $data);
    }

    /**
     * Invoice生成选择
     *
     * @param array $data
     * @param $nowTime
     * @return array
     * @throws Exception
     */
    private function dealInvoiceSelect(array $data, $nowTime)
    {
        $isFirst = empty($data['is_first']) ? 0 : 1; // 是否新一次操作
        $isAllSelect = empty($data['is_all_select']) ? 0 : 1; // 是否全选
        $nowPageSelect = empty($data['page_select']) ? [] :  explode(',', $data['page_select']); // 当前页选择部分
        $nowPageReverseSelect = empty($data['page_receive_select']) ? [] :  explode(',', $data['page_receive_select']); // 反选-全选之后的操作
        $reverseSelect = empty($data['receive_select']) ? [] :  explode(',', $data['receive_select']); // 当前页未选择部分
        $lastRequestTime = empty($data['last_request_time']) ? $nowTime : $data['last_request_time']; // 上次请求时间

        $pageSelect = [];
        $pageReverseSelect = [];
        if (! $isAllSelect) {
            if (! $isFirst) {
                $sessionPageSelect = $this->session->get('invoice_page_select', '');
                $sessionPageSelect && $pageSelect = explode(',', $sessionPageSelect);
                $sessionPageReverseSelect = $this->session->get('invoice_page_receive_select', '');
                $sessionPageReverseSelect && $pageReverseSelect = explode(',', $sessionPageReverseSelect);
            }
            if ($nowPageReverseSelect) { // 存在新的反选值，代表一次新的操作反选
                $pageReverseSelect = $nowPageReverseSelect;
            }
            $newOrderIds = [];
            if ($nowPageReverseSelect && empty($data['filter_orderDate_to']) && strtotime($data['filter_orderDate_to']) > $lastRequestTime) {
                if (empty($data['filter_orderDate_from']) || strtotime($data['filter_orderDate_from']) < $lastRequestTime) {
                    $data['filter_orderDate_from'] = date('Y-m-d H:i:s', $lastRequestTime);
                }
                /** @var ModelAccountOrder $orderModel*/
                $orderModel = load()->model('account/order');
                $newData = $orderModel->getPurchaseInfo($this->customer_id, $data);
                $newOrderIds = array_column($newData,'order_id');
            }
            if ($pageReverseSelect) { // 存在反选，如未重新开始，则后续操作都是基于反选操作
                $pageReverseSelect = array_diff(array_unique(array_merge($pageReverseSelect, $newOrderIds, $reverseSelect)), $nowPageSelect);
            } else { // 不存在反选，则都是自己与正向选择
                $pageSelect = array_diff(array_unique(array_merge($pageSelect, $nowPageSelect)), $reverseSelect);
            }
        }

        $this->session->set('invoice_page_select', implode(',', $pageSelect)); // 总选择
        $this->session->set('invoice_page_receive_select', implode(',', $pageReverseSelect)); // 总反选

        return ['invoice_is_all_select' => $isAllSelect, 'invoice_page_select' => $pageSelect, 'invoice_page_receive_select' => $pageReverseSelect];
    }

    /**
     * Invoice选择状态
     *
     * @param int $orderId 采购订单ID
     * @param int $isInvoice 是否是生成Invoice
     * @param array $invoice Invoice数组
     * @return bool
     */
    private function invoiceIsSelect(int $orderId, int $isInvoice, array $invoice)
    {
        if ($this->customer->isUSA() && $isInvoice) {
            if ($invoice['invoice_is_all_select']) {
                return true;
            }
            if (! empty($invoice['invoice_page_receive_select']) && ! in_array($orderId, $invoice['invoice_page_receive_select'])) {
                return true;
            }
            if (in_array($orderId, $invoice['invoice_page_select'])) {
                return true;
            }
        }

        return false;
    }

    // 生成Invoice
    public function createInvoice()
    {
        if (! $this->customer->isUSA()) {
            return $this->jsonFailed('Invalid Request!');
        }
        $data = $this->request->get();
        $nowTime = time();
        $invoice = $this->dealInvoiceSelect($data, $nowTime);

        if (! $invoice['invoice_is_all_select'] && ! $invoice['invoice_page_receive_select']) {
            $orderIds = $invoice['invoice_page_select'];
        } else {
            /** @var ModelAccountOrder $orderModel*/
            $orderModel = load()->model('account/order');
            unset($data['page']);
            $orders = $orderModel->getPurchaseInfo($this->customer_id, $data);
            $orderIds = array_column($orders, 'order_id');
            if ($invoice['invoice_page_receive_select']) {
                $orderIds = array_diff($orderIds, $invoice['invoice_page_receive_select']);
            }
        }

        if (! $orderIds) {
            return $this->jsonFailed('No data to generate, no need to generate');
        }
        if (! count($orderIds) > 5000) {
            return $this->jsonFailed('A maximum of 5000 purchase orders can be selected.');
        }

        $invoiceService = app(OrderInvoiceService::class);
        $res = $invoiceService->createInvoice($this->customer_id, $orderIds);

        if (! $res) {
            return $this->jsonFailed('Unknown Mistake');
        }

        return $this->jsonSuccess();
    }

    /**
     * @param array $invoice
     * @param int $count 列表总数
     * @return int
     */
    private function dealInvoiceCount(array $invoice, int $count)
    {
        if ($invoice) {
            if ($invoice['invoice_is_all_select']) {
                return $count;
            }
            if (! empty($invoice['invoice_page_receive_select'])) {
                return $count - count($invoice['invoice_page_receive_select']);
            }

            return count($invoice['invoice_page_select']);
        }

        return 0;
    }

    public function purchaseOrderList(){
        $data = $this->load->controller('account/order/purchaseOrder',$_REQUEST);
        $this->response->setOutput($data);
    }

    /**
     * @param FeeOrderService $feeOrderService
     * @throws Exception
     */
    public function cancelOrder(FeeOrderService $feeOrderService){
        $orderId = $this->request->post('order_id', 0);
        $json = [];
        if($orderId != 0){
            $order = Order::query()->find($orderId);
            $this->load->model('account/order');
            //取消采购订单
            $purchaseOrderLines = $this->model_account_order->getNoCancelPurchaseOrderLine($orderId);
            if (empty($purchaseOrderLines)) {
                return $this->response->json(['status' => false, 'msg' => 'Purchase Order information has been changed and is no longer valid.  Please refresh the page and try again.']);
            }
            try {
                $this->db->beginTransaction();
                foreach ($purchaseOrderLines as $purchaseOrderLine) {
                    $this->dealStock($purchaseOrderLine);
                }
                $this->model_account_order->cancelPurchaseOrder($orderId);
                // 设置优惠券为未使用
                app(CouponService::class)->cancelCouponUsed($orderId);
                // 释放期货协议的锁定
                app(FutureAgreement::class)->unLockAgreement($orderId);
                app(MarketingTimeLimitDiscountService::class)->unLockTimeLimitProductQty($orderId);
                //取消费用单
                $feeOrderService->cancelFeeOrderByOcOrderId($orderId);

                if ($order && $order->order_status_id == OcOrderStatus::TO_BE_PAID) {
                    app(BuyerStockService::class)->releaseInventoryLockByOrderPreAssociated([$order->order_id], $order->customer_id);
                }

                $json['status'] = true;
                $json['msg'] = 'Cancel Successfully!';
                $this->db->commit();
            }catch (Exception $e){
                $this->db->rollback();
                Logger::salesOrder(['采购订单,取消失败', 'id' => $orderId, 'e' => $e->getMessage()], 'error');
                $json['status'] = false;
                $json['msg'] = 'Cancel Successfully!';
            }
        }else{
            $json['status'] = false;
            $json['msg'] = 'Cancel Successfully!';
        }
        $this->response->returnJson($json);
    }

    private function dealStock($purchaseOrder)
    {
        $this->load->model('account/order');
        //获取包销店铺
        $bxStoreArray = $this->config->get('config_customer_group_ignore_check');
        //获取预出库明细
        $checkMargin = $this->model_account_order->checkMarginProduct($purchaseOrder);

        $checkAdvanceFutures = [];
        $checkRestMargin = $checkRestFutures = [];
        if ($purchaseOrder['type_id'] == 2) { // 现货
            $checkRestMargin = $this->model_account_order->checkRestMarginProduct($purchaseOrder);
        } elseif ($purchaseOrder['type_id'] == 3) { // 期货
            $checkRestFutures = $this->model_account_order->checkRestFuturesProduct($purchaseOrder);//校验是否是期货尾款
            if (empty($checkRestFutures))
                $checkAdvanceFutures = $this->model_account_order->checkFuturesAdvanceProduct($purchaseOrder);//校验是否是期货头款
        }

        if (!empty($checkMargin)) {
            // 保证金店铺的头款产品
            // 1.更改上架以及combo影响的产品库存
            // 2.oc_order_lock表刪除保证金表数据 履约人表删除数据
            // 3.更改头款商品上架库存产品库存

            // 需要考虑期货转现货头款产品的情况 只有非期货转现货头款产品才发生退货
            if (!app(MarginRepository::class)->checkMarginIsFuture2Margin($checkMargin['margin_id'])) {
                $this->model_account_order->rebackMarginSuffixStore($checkMargin['product_id'], $checkMargin['num']);
            }
            $this->model_account_order->deleteMarginProductLock($checkMargin['margin_id']);
            $this->model_account_order->marginStoreReback($purchaseOrder['product_id'], $purchaseOrder['quantity']);
        } elseif (!empty($checkRestMargin) && !in_array($checkRestMargin['seller_id'], $bxStoreArray)) {
            // 保证金店铺的尾款产品
            // 1 .oc_order_lock表更改保证金表数据
            $this->model_account_order->updateMarginProductLock($checkRestMargin['margin_id'], $purchaseOrder['quantity'], $purchaseOrder['order_id']);
            //还到上架库存
            $this->model_account_order->reback_stock_ground($checkRestMargin, $purchaseOrder);
            //退还批次库存
            $preDeliveryLines = $this->model_account_order->getPreDeliveryLines($purchaseOrder['order_product_id']);
            foreach ($preDeliveryLines as $preDeliveryLine) {
                $this->model_account_order->reback_batch($preDeliveryLine);
            }
        } elseif (!empty($checkAdvanceFutures)) {
            $this->model_account_order->updateFuturesAdvanceProductStock($purchaseOrder['product_id']);
        } elseif (!empty($checkRestFutures)) {
            //期货尾款
            $this->load->model('catalog/futures_product_lock');
            $this->model_catalog_futures_product_lock->TailIn($checkRestFutures['agreement_id'], $purchaseOrder['quantity'], $purchaseOrder['order_id'], 7);
            //退还批次库存
            $preDeliveryLines = $this->model_account_order->getPreDeliveryLines($purchaseOrder['order_product_id']);
            foreach ($preDeliveryLines as $preDeliveryLine) {
                $this->model_account_order->reback_batch($preDeliveryLine);
            }
        } else {
            $preDeliveryLines = $this->model_account_order->getPreDeliveryLines($purchaseOrder['order_product_id']);
            if (count($preDeliveryLines) > 0) {
                //外部店铺或者包销店铺退库存处理
                if (in_array($purchaseOrder['customer_id'], $bxStoreArray) || $purchaseOrder['accounting_type'] == 2) {
                    //判断是否为combo品
                    if ($purchaseOrder['combo_flag'] == 1) {
                        foreach ($preDeliveryLines as $preDeliveryLine) {
                            $this->model_account_order->rebackStock($purchaseOrder, $preDeliveryLine, true);
                        }
                        //非保证金combo退库存
                        $this->model_account_order->rebackComboProduct($purchaseOrder['product_id'], $purchaseOrder['quantity']);
                    } else {
                        //非combo品
                        foreach ($preDeliveryLines as $preDeliveryLine) {
                            $this->model_account_order->rebackStock($purchaseOrder, $preDeliveryLine, true);
                        }
                    }

                } else {
                    //内部店铺的cancel采购订单出库,服务店铺产品
                    //判断是否为combo品
                    if ($purchaseOrder['combo_flag'] == 1) {
                        foreach ($preDeliveryLines as $preDeliveryLine) {
                            $this->model_account_order->rebackStock($purchaseOrder, $preDeliveryLine, false);
                        }

                        //非保证金combo退库存
                        $this->model_account_order->rebackComboProduct($purchaseOrder['product_id'], $purchaseOrder['quantity']);
                        //}
                    } else {
                        foreach ($preDeliveryLines as $preDeliveryLine) {
                            $this->model_account_order->rebackStock($purchaseOrder, $preDeliveryLine, false);
                        }
                    }
                }
            }
            else {
                //没有预出库明细
                $msg = "[采购订单超时返还库存错误],采购订单明细：" . $purchaseOrder['order_product_id'] . ",未找到对应预出库记录";
                Logger::salesOrder($msg, 'error');
            }
        }

    }

}
