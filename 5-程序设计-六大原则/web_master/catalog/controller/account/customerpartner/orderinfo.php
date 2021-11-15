<?php

use App\Enums\Common\YesNoEnum;
use App\Enums\Order\OcOrderTypeId;
use App\Models\Customer\Customer;
use App\Repositories\Margin\MarginRepository;
use App\Widgets\VATToolTipWidget;

/**
 * Class ControllerAccountCustomerpartnerOrderinfo
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountNotification $model_account_notification
 * @property ModelAccountProductQuotesMarginContract $model_account_product_quotes_margin_contract
 * @property ModelAccountCustomerGroup $model_account_customer_group 
 * @property ModelAccountCustomerpartnerMarginOrder $model_account_customerpartner_margin_order
 * @property ModelAccountOrder $model_account_order
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelMpLocalisationOrderStatus $model_mp_localisation_order_status
 * @property ModelToolImage $model_tool_image
 * @property ModelToolUpload $model_tool_upload
 */
class ControllerAccountCustomerpartnerOrderinfo extends Controller
{

    private $data = array();

    public function __construct($registry)
    {
        parent::__construct($registry);

        if (!$this->customer->isLogged()) {
            if (isset($this->request->get['order_id'])) {
                session()->set('redirect', $this->url->link('account/customerpartner/orderinfo&order_id=' . $this->request->get['order_id'], '', true));
            }
            $this->response->redirect($this->url->link('account/login', '', true));
        } else {
            if (!isset($this->request->get['order_id']) || empty($this->request->get['order_id'])) {
                $this->response->redirect($this->url->link('account/customerpartner/orderlist', '', true));
            }
        }
    }

    public function index()
    {
        /**
         * 更新对应notification为已读
         */
        $this->load->model('account/notification');
        $this->load->model('account/customerpartner/margin_order');
        if (JAPAN_COUNTRY_ID == $this->customer->getCountryId()){
            $format = '%d';
            $precision = 0;
        }else{
            $format = '%.2f';
            $precision = 2;
        }

        if (isset($this->request->get['ca_id']) && isset($this->request->get['is_mp']) && !empty($this->request->get['ca_id'])) {
            $this->model_account_notification->updateIsRead((int)$this->request->get['ca_id'], (int)isset($this->request->get['is_mp']));
        } else {
            $this->model_account_notification->updateIsReadNotByCa((int)$this->request->get['order_id'], (int)$this->customer->getId());
        }

        $this->load->model('account/customerpartner');

        $data['chkIsPartner'] = customer()->isPartner();

        if (!$data['chkIsPartner'] || (isset($this->session->data['marketplace_seller_mode']) && !$this->session->data['marketplace_seller_mode'])){
            $this->response->redirect($this->url->link('account/account', '', true));
        }

        $this->load->language('account/customerpartner/orderinfo');
        $this->load->language('common/cwf');

        if (isset($this->request->get['order_id'])) {
            $order_id = (int)$this->request->get['order_id'];
        } else {
            $order_id = 0;
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = session('success');
            $this->session->remove('success');
        } else {
            $data['success'] = '';
        }
        $data['currency'] = $this->session->get('currency');
        $data['order_id'] = $order_id;
        //保证金的业务只有相同国别的才存在，这里就不做二次判断了，直接使用当前登录的seller进行国别判断。 by chenyang 2019/09/28
        $data['isAmerican'] = $this->customer->isUSA();
        $data['hasMarketplaceFee'] = ($data['isAmerican'] && $this->customer->getAccountType() == 1) ? true : false;
        $data['isEurope'] = in_array($this->customer->getCountryId(), EUROPE_COUNTRY_ID) ? true : false;
        //是否启用议价
        $data['enableQuote'] = false;
        if (!empty($this->customer->getCountryId()) && in_array($this->customer->getCountryId(), QUOTE_ENABLE_COUNTRY)) {
            $data['enableQuote'] = true;
        }

        $this->load->model('account/order');
        $this->load->model('account/product_quotes/margin_contract');
        $this->load->model('account/product_quotes/rebates_agreement');
        $this->load->language('account/product_quotes/margin_contract');

        $order_seller_id = $this->customer->getId();
        $order_seller_id_array = array((int)$this->customer->getId());
        $customer_id = $this->customer->getId();
        $data['customer_id'] = $customer_id;
        $margin_agreement = $this->model_account_product_quotes_margin_contract->getSellerMarginByOrderId($order_id, $customer_id);
        $check_margin_rest = false;
        $agreement_id = null;
        $process_product = [];
        $margin_product_original_map = [];
        $agreementIdList = [];
        if(isset($margin_agreement) && !empty($margin_agreement)){
            foreach ($margin_agreement as $agreement) {
                $agreement_id = $agreement['agreement_id'];
                $agreementIdList[] = $agreement['id'];
                $margin_process = $this->model_account_product_quotes_margin_contract->getMarginProcessDetail($agreement_id);
                if(isset($margin_process['advance_product_id'])){
                    $process_product[] = $margin_process['advance_product_id'];
                    $margin_product_original_map[$margin_process['advance_product_id']] = [
                        'original_product_id' => $agreement['product_id'],
                        'original_sku' => $agreement['originalSku'],
                        'original_mpn' => $agreement['originalMpn'],
                    ];
                }
                if(isset($margin_process['rest_product_id'])){
                    $process_product[] = $margin_process['rest_product_id'];
                }
                //如果合同的尾款订单号和当前订单号一致，seller使用包销店铺id
                if($agreement['rest_order_id'] == $order_id){
                    $bx_seller_id = $this->model_account_product_quotes_margin_contract->getMarginBxStoreId($agreement['seller_id']);
                    if($bx_seller_id){
                        $order_seller_id = $bx_seller_id;
                        $order_seller_id_array[] = $order_seller_id;
                    }
                }else{
                    $service_seller_id = $this->model_account_product_quotes_margin_contract->getMarginServiceStoreId($agreement['seller_id']);
                    if($service_seller_id){
                        $order_seller_id = $service_seller_id;
                        $order_seller_id_array[] = $order_seller_id;
                    }
                }
                foreach ($margin_agreement as $seller_id) {
                    if(isset($seller_id['rest_order_id']) && $order_id != $seller_id['rest_order_id']){
                        $check_margin_rest = true;
                        break;
                    }
                }
                $data['is_margin_order'] = true;
            }
            $order_seller_id_array = array_unique($order_seller_id_array);
        }
        //如果存在保证金的尾款商品，需展示列表
        //todo 有优惠券的情况下，需要减去优惠券
        if($check_margin_rest && isset($agreement_id)){
            //$rest_orders = $this->model_account_product_quotes_margin_contract->getMarginCheckDetail($agreement_id);
            $rest_orders = app(MarginRepository::class)->getMarginCheckDetail($agreementIdList);
            if (!empty($rest_orders)) {
                $this->load->language('account/product_quotes/margin_contract');
                foreach ($rest_orders as $key => $rest_order) {
                    $quote_price = 0;
                    $service_fee = 0;
                    if ($data['enableQuote']) {
                        $quote_price = $this->model_account_customerpartner->getQuotePrice($rest_order['rest_order_id'], $rest_order['product_id']);  // 如果为null，表示没参与议价
                    }
                    if ($data['isEurope']) {
                        $service_fee = $this->model_account_customerpartner->getServiceFee($rest_order['rest_order_id'], $rest_order['product_id']);
                    }

                    $line_total = ($data['enableQuote'] && !is_null($quote_price)
                        ? ($quote_price * $rest_order['quantity']) : $rest_order['c2oprice'])
                        + $service_fee * $rest_order['quantity']
                        + $rest_order['freight_per_unit'] * $rest_order['quantity'];

                    //优惠券+满减,这个地方需要调取实收金额，但是字段是之前定好的，不改字段了，这个放个备注
                    $couponAndCampaignAmount = isset($rest_order['campaign_amount']) ? $rest_order['campaign_amount'] : 0; // +$rest_order['coupon_amount']
                    $rest_orders[$key]['coupon_and_campaign'] = 0;
                    $rest_orders[$key]['origin_total'] = sprintf($format, $line_total);
                    if ($couponAndCampaignAmount > 0) {
                        $rest_orders[$key]['coupon_and_campaign'] = $couponAndCampaignAmount;
                        $line_total -= $couponAndCampaignAmount;
                    }
                    $rest_orders[$key]['unit_price'] = sprintf($format,$rest_order['unit_price']);
                    $rest_orders[$key]['freight_per_unit'] = sprintf($format,$rest_order['freight_per_unit']);
                    $rest_orders[$key]['total'] = sprintf($format,$line_total);

                    $rmaIDs = $this->model_account_customerpartner->getRMAIDByOrderProduct($rest_order['rest_order_id'], $rest_order['product_id'], $rest_order['buyer_id']);
                    $rest_orders[$key]['rma_ids'] = $rmaIDs;
                    $rest_orders[$key]['buyer_name'] = $rest_order['buyer_nickname'] . '(' . $rest_order['buyer_user_number'] . ')';
                    $rest_orders[$key]['rest_order_url'] = $this->url->link('account/customerpartner/orderinfo', 'order_id=' . $rest_order['rest_order_id'], true);
                }
                $data['rma_url'] = $this->url->link('account/customerpartner/rma_management/rmaInfo&rmaId=', '', true);
                $data['restOrderList'] = $rest_orders;
            }
        }

        $this->document->setTitle($this->language->get('text_order'));
        $data['breadcrumbs'] = array(
            array(
                'text'      => $this->language->get('heading_title_order_history'),
                'href'      => $this->url->link('account/customerpartner/orderlist', '', true),
                'separator' => $this->language->get('text_separator')
            ),
            array(
                'text'      => $this->language->get('heading_title_order_details'),
                'href'      => $this->url->link('account/customerpartner/orderinfo', 'order_id=' . $order_id, true),
                'separator' => $this->language->get('text_separator')
            )
        );


        $data['errorPage'] = false;

        if ($this->config->get('marketplace_available_order_status')) {
            $data['marketplace_available_order_status'] = $this->config->get('marketplace_available_order_status');
            $data['marketplace_order_status_sequence'] = $this->config->get('marketplace_order_status_sequence');
        }

        if ($this->config->get('marketplace_cancel_order_status') && $this->config->get('marketplace_available_order_status')) {

            $data['marketplace_cancel_order_status'] = $this->config->get('marketplace_cancel_order_status');

            $cancel_order_statusId_key_available = array_search($this->config->get('marketplace_cancel_order_status'), $data['marketplace_available_order_status'], true);

            if ($cancel_order_statusId_key_available === 0 || $cancel_order_statusId_key_available) {

                unset($data['marketplace_available_order_status'][$cancel_order_statusId_key_available]);
                unset($data['marketplace_order_status_sequence'][$this->config->get('marketplace_cancel_order_status')]);

            }

            foreach ($data['marketplace_order_status_sequence'] as $key => $value) {

                if ($value['order_status_id'] == $this->config->get('marketplace_cancel_order_status')) {

                    unset($data['marketplace_order_status_sequence'][$key]);

                }
            }

        } else {
            $data['marketplace_cancel_order_status'] = '';
        }

        $order_info = $this->model_account_customerpartner->getOrder($order_id,$order_seller_id);
        if ($order_info) {
            $data['wksellerorderstatus'] = $this->config->get('marketplace_sellerorderstatus');
            if ($order_info['invoice_no']) {
                $data['invoice_no'] = $order_info['invoice_prefix'] . $order_info['invoice_no'];
            } else {
                $data['invoice_no'] = '';
            }
            $data['date_added'] = date($this->language->get('datetime_format'), strtotime($order_info['date_added']));
            $data['buyer_id'] = $order_info['buyer_id'];
            $data['nickname'] = $order_info['nickname'];
            $data['order_status_name'] = $order_info['order_status_name'];
            $data['is_home_pickup'] = in_array($order_info['customer_group_id'], COLLECTION_FROM_DOMICILE);
            $data['ex_vat'] = VATToolTipWidget::widget(['customer' => Customer::query()->find($order_info['buyer_id']), 'is_show_vat' => true])->render();
            $data['delivery_type'] = $order_info['delivery_type'];

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
                'lastname' => $order_info['payment_lastname'],
                'company' => $order_info['payment_company'],
                'address_1' => $order_info['payment_address_1'],
                'address_2' => $order_info['payment_address_2'],
                'city' => $order_info['payment_city'],
                'postcode' => $order_info['payment_postcode'],
                'zone' => $order_info['payment_zone'],
                'zone_code' => $order_info['payment_zone_code'],
                'country' => $order_info['payment_country']
            );

            $data['payment_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

            $data['payment_method'] = $order_info['payment_method'];

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
                'lastname' => $order_info['shipping_lastname'],
                'company' => $order_info['shipping_company'],
                'address_1' => $order_info['shipping_address_1'],
                'address_2' => $order_info['shipping_address_2'],
                'city' => $order_info['shipping_city'],
                'postcode' => $order_info['shipping_postcode'],
                'zone' => $order_info['shipping_zone'],
                'zone_code' => $order_info['shipping_zone_code'],
                'country' => $order_info['shipping_country']
            );

            $data['shipping_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

            $data['shipping_method'] = $order_info['shipping_method'];

            $data['products'] = array();

            $this->load->model('catalog/product');

            $products = array();
            foreach ($order_seller_id_array as $seller_id) {
                if($customer_id == $seller_id){
                    $products_temp = $this->model_account_customerpartner->getSellerOrderProductInfo($order_id,$seller_id);
                    $products = array_merge($products,$products_temp);
                }else{
                    $products_bx = $this->model_account_customerpartner->getSellerOrderProductInfo($order_id,$seller_id,$process_product);
                    $products = array_merge($products,$products_bx);
                }
            }
            // Uploaded files
            $this->load->model('tool/upload');
            $this->load->model('tool/image');
            $num = 1;
            $freight_total = 0;
            $all_total = 0;
            $service_fee_total = 0;
            $sub_total = 0;
            $marketplace_fee_total = 0;
            $all_campaign_amount = 0;
            $all_coupon_amount = 0;
            foreach ($products as $product) {
                /**
                 * @var float $quote_price
                 * @var float $service_fee
                 */
                $quote_price = 0;
                $service_fee_per= 0;

                $marketplace_fee_per = 0;

                $amount_price = 0;
                $amount_price_per = 0;
                $amount_service_fee = 0;
                $amount_service_fee_per = 0;
                $option_data = array();

                $options = $this->model_account_order->getOrderOptions($order_id, $product['order_product_id']);

                // code changes due to download file error
                foreach ($options as $option) {
                    if ($option['type'] != 'file') {
                        $option_data[] = array(
                            'name' => $option['name'],
                            'value' => $option['value'],
                            'type' => $option['type']
                        );
                    } else {
                        $upload_info = $this->model_tool_upload->getUploadByCode($option['value']);
                        if ($upload_info) {
                            $option_data[] = array(
                                'name' => $option['name'],
                                'value' => $upload_info['name'],
                                'type' => $option['type'],
                                'href' => $this->url->link('account/customerpartner/orderinfo/download', '&code=' . $upload_info['code'], true)
                            );
                        }
                    }
                }

                $product_info = $this->model_catalog_product->getProductForOrderHistory($product['product_id']);
                //图片处理一下
                $image = $this->model_tool_image->resize($product_info['image'], 40, 40);

                $product_tracking = $this->model_account_customerpartner->getOdrTracking($data['order_id'], $product['product_id'], $product['seller_id']);

                if ($product['paid_status'] == 1) {
                    $paid_status = $this->language->get('text_paid');
                } else {
                    $paid_status = $this->language->get('text_not_paid');
                }

                $tag_array = $this->model_catalog_product->getProductSpecificTag($product['product_id']);
                $tags = array();
                if(isset($tag_array)){
                    foreach ($tag_array as $tag){
                        if(isset($tag['origin_icon']) && !empty($tag['origin_icon'])){
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip"  class="'.$tag['class_style']. '"  title="'.$tag['description']. '" style="padding-left: 1px;margin-top:-5px;" src="'.$img_url.'">';
                        }
                    }
                }
                $rmaIDs = $this->model_account_customerpartner->getRMAIDByOrderProduct($data['order_id'], $product['product_id'], $product['buyer_id']);
                if ($data['enableQuote']) {
                    $product_quote = $this->model_account_customerpartner->getQuoteAmountAndService($data['order_id'], $product['product_id']);
                    $amount_price_per = $product_quote['amount_price_per'] ?? 0;
                    $amount_service_fee_per = $product_quote['amount_service_fee_per'] ?? 0;
                    $amount_price = bcmul($amount_price_per, $product['quantity'], 2);

                    if ($data['isEurope'] && !empty($product_quote)) {
                        $amount_service_fee = bcmul($product_quote['amount_service_fee_per'], $product_quote['quantity'], 2);
                    }
                }

                if ($data['isEurope']) {
                    $service_fee_per = $product['service_fee_per'];
                    //获取discount后的 真正的service fee
                    $service_fee_total += ($service_fee_per - $amount_service_fee_per)*$product['quantity'];

                }

                //验证是否是上门取货
                if($data['is_home_pickup']){
                    $product['freight_per'] = 0;
                }
                //获取
                $freight_per = $product['freight_per'] + $product['package_fee'];
                // 如果是运送仓 订单 ，体积不满 2立方的，需要补 差额运费
//                if ($data['delivery_type'] == 2 && !empty($product['freight_difference_per'])) {
//                    $freight_per += $product['freight_difference_per'];
//                }

                $data['img_vat_url'] = ($this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url'))
                    . '/image/product/vat.png';
                if ($data['hasMarketplaceFee']) {
                    $price_no_marketplace_fee = round(($product['opprice']-$amount_price_per)/ 1.05, 2);
                    $marketplace_fee_per = bcsub($product['opprice']-$amount_price_per, $price_no_marketplace_fee, 2);
                    $marketplace_fee_total = bcadd($marketplace_fee_total, bcmul($marketplace_fee_per, $product['quantity'], 2), 2);
                }

                $line_total = $data['isEurope']
                    ?
                    ($product['opprice'] + $product['service_fee_per']) * $product['quantity']
                    :
                    $product['opprice'] * $product['quantity'];
                $line_total += ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0);
                $line_total = $line_total - $amount_price - $amount_service_fee + $freight_per*$product['quantity'];
                $old_line_total = $line_total;
                $line_total -= $product['campaign_amount'];
                //if(!empty($margin_product_original_map[$product['product_id']])){
                //    $original_pid = $margin_product_original_map[$product['product_id']]['original_product_id'];
                //    $original_sku = $margin_product_original_map[$product['product_id']]['original_sku'];
                //    $original_mpn = $margin_product_original_map[$product['product_id']]['original_mpn'];
                //}
                $sub_total += ($product['opprice'] - $amount_price_per - $marketplace_fee_per) * $product['quantity'];
                $freight_total += $freight_per*$product['quantity'];
                $all_total += $old_line_total;
                $all_campaign_amount += $product['campaign_amount'];
                $all_coupon_amount += $product['coupon_amount'];
                $future_margin_info = [];
                if($product['type_id'] == OcOrderTypeId::TYPE_FUTURE){
                    $future_margin_info = $this->model_account_order->getFutureMarginInfo($product['agreement_id']);
                }
                $is_rebate = YesNoEnum::NO;
                $rebateAgreementId = $this->model_account_product_quotes_rebates_agreement->getRebateAgreementId($order_id, $product['product_id']);
                $rebateAgreementCode = '';
                if($rebateAgreementId){
                    $is_rebate = YesNoEnum::YES;
                    $rebateAgreementCode = $this->model_account_order->getRebateAgreementCode($rebateAgreementId);
                }
                $data['products'][] = array(
                    'type_id' => $product['type_id'],
                    'agreement_id' => $product['agreement_id'],
                    'agreement_no' => $future_margin_info['agreement_no'] ?? '',
                    'num' => $num++,
                    'image'=> $image,
                    'product_id' => $product['product_id'],
                    'name' => $product['name'],
                    'model' => $product['model'],
                    'option' => $option_data,
                    'mpn' => $product_info['mpn'],
                    'sku' => $product_info['sku'],
                    'seller_id' => $product['seller_id'],
                    'margin_sku' => isset($original_sku) ? $original_sku : null,
                    'margin_mpn' => isset($original_mpn) ? $original_mpn : null,
                    'margin_link' => isset($original_pid) ?  $this->url->link('product/product', 'product_id=' . $original_pid, true) : null,
                    'tracking' => isset($product_tracking['tracking']) ? $product_tracking['tracking'] : '',
                    'quantity' => $product['quantity'],
                    'paid_status' => $paid_status,
                    'price' => ($data['isEurope'] ? "" : '')
                        . $this->currency->format($product['opprice']- $amount_price_per - $marketplace_fee_per, $order_info['currency_code'], 1),
//                    'total' => $this->currency->format($product['c2oprice'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order_info['currency_code'], 1),
                    'total' => $this->currency->format($line_total, $order_info['currency_code'], 1),
                    'order_product_status' => $product['order_product_status'],
                    'tag' => $tags,
                    'rma_ids' => $rmaIDs,
                    'quote_discount' => empty($quote_price) ? 0 : $this->currency->format($quote_price - $product['opprice'], $order_info['currency_code'], 1),
                    'service_fee_per' => $this->currency->format($service_fee_per - $amount_service_fee_per, $order_info['currency_code'], 1),
                    'amount_price_per' => $this->currency->formatCurrencyPrice(-$amount_price_per, $order_info['currency_code'], 1),
                    'amount_price_per_number' => (string)$amount_price_per,
                    'amount_service_fee_per_number' => $amount_service_fee_per,
                    'amount_service_fee_per' => $this->currency->formatCurrencyPrice(-$amount_service_fee_per, $order_info['currency_code'], 1),
                    'freight_per' => $this->currency->formatCurrencyPrice($freight_per, $order_info['currency_code'], 1),
                    'freight_difference_per_number' => $product['freight_difference_per'],
                    'freight_difference_per' => $this->currency->formatCurrencyPrice($product['freight_difference_per'], $order_info['currency_code'], 1),
                    'tips_freight_difference_per' => str_replace(
                        '_freight_difference_per_',
                        $this->currency->formatCurrencyPrice($product['freight_difference_per'], $this->session->data['currency']),
                        $this->language->get('tips_freight_difference_per')
                    ),
                    'marketplace_fee_per' => $this->currency->formatCurrencyPrice($marketplace_fee_per, $order_info['currency_code'], 1),
                    'is_rebate' => $is_rebate,
                    'rebateAgreementCode' => $rebateAgreementCode,
                    'discountShow' => is_null($product['discount']) ? '' : (string)round(100 - $product['discount']),
                );
            }
            $data['rma_url'] = $this->url->link('account/customerpartner/rma_management/rmaInfo&rmaId=', '', true);
            $data['product_info_url'] = $this->url->link('product/product&product_id=', '', true);
            $data['img_vat_url'] = ($this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url'))
                . 'image/product/vat.png';
            //$data['rebate_logo'] = '/image/product/rebate_15x15.png';

            // Voucher
            $data['vouchers'] = array();

            $vouchers = $this->model_account_order->getOrderVouchers($order_id);

            foreach ($vouchers as $voucher) {
                $data['vouchers'][] = array(
                    'description' => $voucher['description'],
                    'amount' => $this->currency->format($voucher['amount'], $order_info['currency_code'], $order_info['currency_value'])
                );
            }

            //美国三行 欧洲四行
            if($data['isEurope']){

                $data['totals'][] = [
                    'title' => '<b>Item Subtotal:</b>', //'<i class="giga icon-vat"></i>&nbsp;<b>Item Subtotal:</b>',
                    'text'  => "<span style='color: darkorange;font-weight: bold'>". $this->currency->format(round($sub_total, 2), $order_info['currency_code'], 1)."</span>",
                ];

                $data['totals'][] = [
                    'title' => '<b>Total Service Fee:</b>',
                    'text'  =>  "<span style='color: darkorange;font-weight: bold'>". $this->currency->format(round($service_fee_total, 2), $order_info['currency_code'], 1)."</span>",
                ];

                $data['totals'][] = [
                    'title' => '<b>Total Fulfillment:</b>',
                    'text'  => $this->currency->format(round($freight_total, 2), $order_info['currency_code'], 1),
                ];

                $data['totals'][] = [
                    'title' => '<b>Order Total:</b>',
                    'text'  => $this->currency->format(round($all_total, 2), $order_info['currency_code'], 1),
                ];

            }else{
                $data['totals'][] = [
                    'title' => '<b>Item(s) Subtotal:</b>',
                    'text'  => "<span style='color: darkorange;font-weight: bold'>". $this->currency->format(round($sub_total, 2), $order_info['currency_code'], 1)."</span>",
                ];
                if ($data['hasMarketplaceFee']) {
                    $data['totals'][] = [
                        'title' => '<b>Marketplace Fee Subtotal:</b>',
                        'text'  => $this->currency->format($marketplace_fee_total, $order_info['currency_code'], 1),
                    ];
                }

                $data['totals'][] = [
                    'title' => '<b>Total Fulfillment:</b>',
                    'text'  => $this->currency->format(round($freight_total, 2), $order_info['currency_code'], 1),
                ];

                $data['totals'][] = [
                    'title' => '<b>Order Total:</b>',
                    'text'  => $this->currency->format(round($all_total, 2), $order_info['currency_code'], 1),
                ];

            }

            if ($all_campaign_amount > 0) {
                $data['totals'][] = [
                    'title' => '<b>Promotion Discount:</b>',
                    'text' => '-' . $this->currency->format(round($all_campaign_amount, 2), $order_info['currency_code'], 1),
                ];
            }
            $final_total = $all_total - $all_campaign_amount;
            //最后一项的final total在页面特殊化处理
            $data['final_total'] = $final_total;
            $data['final_total_show'] = $this->currency->formatCurrencyPrice($final_total, $this->session->get('currency'));;
            if ($all_coupon_amount > 0) {
                $data['all_coupon_amount'] = $all_coupon_amount;
                $data['all_coupon_amount_show'] = $this->currency->formatCurrencyPrice($all_coupon_amount, $this->session->get('currency'));
                $data['buyer_paid'] = $final_total - $all_coupon_amount;
                $data['buyer_paid_show'] = $this->currency->formatCurrencyPrice($data['buyer_paid'], $this->session->get('currency'));
                $data['have_coupon_discount'] = 1;
            }
            $data['comment'] = nl2br($order_info['comment']);

            //list of status

            $this->load->model('mp_localisation/order_status');

            $data['order_statuses'] = $this->model_mp_localisation_order_status->getOrderStatuses();

            $data['marketplace_complete_order_status'] = $this->config->get('marketplace_complete_order_status');

            $data['order_status_id'] = $order_info['order_status_id'];

        } else {
            $data['errorPage'] = true;
            $data['order_status_id'] = '';
        }

        $data['action'] = $this->url->link('account/customerpartner/orderinfo&order_id=' . $order_id, '', true);
        $data['continue'] = $this->url->link('account/customerpartner/orderlist', '', true);
        $data['order_invoice'] = $this->url->link('account/customerpartner/soldinvoice&order_id=' . $order_id, '', true);

        /*
        Access according to membership plan
         */
        $data['isMember'] = true;
        if ($this->config->get('module_wk_seller_group_status')) {
            $data['module_wk_seller_group_status'] = true;
            $this->load->model('account/customer_group');
            //开发保证金需求时，没找到getSellerMembershipGroup方法实现，代码已弃用，这里没有把用户ID替换成保证金协议的seller_id
            //by chenyang 2019/09/28
            $isMember = $this->model_account_customer_group->getSellerMembershipGroup($this->customer->getId());
            if ($isMember) {
                $allowedAccountMenu = $this->model_account_customer_group->getaccountMenu($isMember['gid']);
                if ($allowedAccountMenu['value']) {
                    $accountMenu = explode(',', $allowedAccountMenu['value']);
                    if ($accountMenu && !in_array('orderhistory:orderhistory', $accountMenu)) {
                        $data['isMember'] = false;
                    }
                }
            } else {
                $data['isMember'] = false;
            }
        } else {
            if (!is_array($this->config->get('marketplace_allowed_account_menu')) || !in_array('orderhistory', $this->config->get('marketplace_allowed_account_menu'))) {
                $this->response->redirect($this->url->link('account/account', '', true));
            }
        }

        /*
        end here
         */
        $data['separate_view'] = true;
        $data['column_left'] = '';
        $data['column_right'] = '';
        $data['content_top'] = '';
        $data['content_bottom'] = '';
        $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
        $data['footer'] = $this->load->controller('account/customerpartner/footer');
        $data['header'] = $this->load->controller('account/customerpartner/header');

        //用户画像
        $data['styles'][]='catalog/view/javascript/layer/theme/default/layer.css';
        $data['scripts'][]='catalog/view/javascript/layer/layer.js';
        $data['styles'][]='catalog/view/javascript/user_portrait/user_portrait.css?v=' . APP_VERSION;
        $data['scripts'][]='catalog/view/javascript/user_portrait/user_portrait.js?v=' . APP_VERSION;
        // 获取该订单所有参与现货保证金的product_id
        $data['margin_product_ids'] = $this->model_account_customerpartner_margin_order->getMarginProducts($order_id);
        $order_agree = $this->model_account_customerpartner_margin_order->getMarginAgreeInfo($order_id);
        array_walk($order_agree, function (&$item) {
            $item = $this->orm
                ->table('tb_sys_margin_agreement')
                ->where('id', $item)
                ->value('agreement_id');
        });
        $data['margin_product_id_agree'] = $order_agree;
        $this->response->setOutput($this->load->view('account/customerpartner/orderinfo', $data));
    }

    public function history()
    {
        $this->load->language('account/customerpartner/orderinfo');
        $this->load->model('account/customerpartner');

        $json = array();

        if ($this->config->get('marketplace_cancel_order_status')) {
            $marketplace_cancel_order_status = $this->config->get('marketplace_cancel_order_status');
        } else {
            $marketplace_cancel_order_status = '';
        }

        if (isset($this->request->post['comment']) && !empty($this->request->post['comment']) && empty($this->request->post['product_ids'])) {

            $getOrderStatusId = $this->model_account_customerpartner->getOrderStatusId((int)$this->request->get['order_id']);

            $this->request->post['order_status_id'] = $getOrderStatusId['order_status_id'];

            $this->model_account_customerpartner->addOrderHistory((int)$this->request->get['order_id'], $this->request->post);

            $this->model_account_customerpartner->addSellerOrderStatus((int)$this->request->get['order_id'], '', $this->request->post);

            $json['success'] = $this->language->get('text_success_history');

        } elseif (isset($this->request->post['product_ids']) && !empty($this->request->post['product_ids'])) {

            $products = explode(",", $this->request->post['product_ids']);

            $this->load->model('mp_localisation/order_status');
            $order_statuses = $this->model_mp_localisation_order_status->getOrderStatuses();

            foreach ($order_statuses as $value) {

                if (in_array($this->request->post['order_status_id'], $value)) {

                    $seller_change_order_status_name = $value['name'];

                }
            }
            if (isset($seller_change_order_status_name) && $seller_change_order_status_name) {
                if ($this->request->post['order_status_id'] == $marketplace_cancel_order_status) {
                    $this->changeOrderStatus($this->request->get, $this->request->post, $products, $marketplace_cancel_order_status, $seller_change_order_status_name);
                } else {
                    $this->changeOrderStatus($this->request->get, $this->request->post, $products, $marketplace_cancel_order_status, $seller_change_order_status_name);
                }
            }

            $json['success'] = $this->language->get('text_success_history');

        } else {

            $json['error'] = $this->language->get('error_product_select');
        }

        $this->response->setOutput(json_encode($json));
    }

    private function changeOrderStatus($get, $post, $products, $marketplace_cancel_order_status, $seller_change_order_status_name)
    {


        /**
         * First step - Add seller changing status for selected products
         */
        $this->model_account_customerpartner->addsellerorderproductstatus($get['order_id'], $post['order_status_id'], $products);


        // Second Step - add comment for each selected products
        $this->model_account_customerpartner->addSellerOrderStatus($get['order_id'], $post['order_status_id'], $post, $products, $seller_change_order_status_name);

        // Thired Step - Get status Id that will be the whole order status id after changed the order product status by seller
        $getWholeOrderStatus = $this->model_account_customerpartner->getWholeOrderStatus($get['order_id'], $marketplace_cancel_order_status);


        // Fourth Step - add comment in order_history table and send mails to admin(If admin notify is enable) and customer
        $this->model_account_customerpartner->addOrderHistory($get['order_id'], $post, $seller_change_order_status_name);


        // Fifth Step - Update whole order status in order table
        if ($getWholeOrderStatus) {
            $this->model_account_customerpartner->changeWholeOrderStatus($get['order_id'], $getWholeOrderStatus);
        }

    }

    // file download code
    public function download()
    {
        $this->load->model('tool/upload');

        if (isset($this->request->get['code'])) {
            $code = $this->request->get['code'];
        } else {
            $code = 0;
        }

        $upload_info = $this->model_tool_upload->getUploadByCode($code);

        if ($upload_info) {
            $file = DIR_UPLOAD . $upload_info['filename'];
            $mask = basename($upload_info['name']);

            if (!headers_sent()) {
                if (is_file($file)) {
                    header('Content-Type: application/octet-stream');
                    header('Content-Description: File Transfer');
                    header('Content-Disposition: attachment; filename="' . ($mask ? $mask : basename($file)) . '"');
                    header('Content-Transfer-Encoding: binary');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($file));

                    readfile($file, 'rb');
                    exit;
                } else {
                    exit('Error: Could not find file ' . $file . '!');
                }
            } else {
                exit('Error: Headers already sent out!');
            }
        } else {
            $this->load->language('error/not_found');

            $this->document->setTitle($this->language->get('heading_title'));

            $data['heading_title'] = $this->language->get('heading_title');

            $data['error_warning_authenticate'] = $this->language->get('error_warning_authenticate');

            $data['text_not_found'] = $this->language->get('text_not_found');

            $data['breadcrumbs'] = array();

            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
            );

            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('error/not_found', 'user_token=' . session('user_token'), true)
            );

            $data['header'] = $this->load->controller('common/header');

            $data['separate_view'] = false;

            $data['separate_column_left'] = '';

            if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
                $data['separate_view'] = true;
                $data['column_left'] = '';
                $data['column_right'] = '';
                $data['content_top'] = '';
                $data['content_bottom'] = '';
                $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');

                $data['footer'] = $this->load->controller('account/customerpartner/footer');
                $data['header'] = $this->load->controller('account/customerpartner/header');
            }
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['footer'] = $this->load->controller('common/footer');

            $this->response->setOutput($this->load->view('error/not_found.twig', $data));
        }
    }
}

?>
