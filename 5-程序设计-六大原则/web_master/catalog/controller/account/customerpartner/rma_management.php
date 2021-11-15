<?php

use App\Enums\Future\FuturesVersion;
use App\Components\Storage\StorageCloud;
use App\Enums\Safeguard\SafeguardBillStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\YzcRmaOrder\RmaType;
use App\Logging\Logger;
use App\Models\Customer\Customer;
use App\Models\Product\Product;
use App\Models\Rma\YzcRmaOrder;
use App\Repositories\Rebate\RebateRepository;
use App\Repositories\Rma\RamRepository;
use App\Repositories\Safeguard\SafeguardBillRepository;
use App\Services\FeeOrder\StorageFeeService;
use App\Services\Marketing\PlatformBillService;
use App\Services\Rma\RmaService;
use App\Widgets\VATToolTipWidget;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Yzc\SysCostDetail;
use Yzc\SysReceive;
use Yzc\SysReceiveLine;

/**
 * Class ControllerAccountCustomerpartnerRmaManagement
 * @property ModelCustomerpartnerRmaManagement $model_customerpartner_rma_management
 * @property ModelAccountProductQuotesMarginContract $model_account_product_quotes_margin_contract
 * @property ModelAccountRmaManagement $model_account_rma_management
 * @property ModelAccountCustomerpartnerMarginOrder $model_account_customerpartner_margin_order
 * @property ModelAccountCustomerpartnerFuturesOrder $model_account_customerpartner_futures_order
 * @property ModelAccountOrder $model_account_order
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelToolImage $model_tool_image
 * @property ModelCatalogMarginProductLock $model_catalog_margin_product_lock
 * @property ModelCatalogFuturesProductLock $model_catalog_futures_product_lock
 * @property ModelCommonProduct $model_common_product
 * @property ModelAccountRmaManage $model_account_rma_manage
 * @property ModelAccountWishlist $model_account_wishlist
 * @property ModelMessageMessage $model_message_message
 */
class ControllerAccountCustomerpartnerRmaManagement extends Controller
{
    private $error = array();

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        //额外放行批量保存的外部接口
        if (!$this->customer->isLogged() && strcmp($this->request->get['route'], 'account/customerpartner/rma_management/agreeRefundApi') !== 0) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }
    }

    public function index()
    {
        $this->load->language('account/customerpartner/rma_management');
        $this->load->language('common/cwf');

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('account/customerpartner/rma_management', '', true)
        );

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addStyle('catalog/view/javascript/bootstrap-table/bootstrap-table-1.15.2.css');
        $this->document->addScript('catalog/view/javascript/bootstrap-table/bootstrap-table-1.15.2.js');
        $this->document->addScript('catalog/view/javascript/jquery/magnific/jquery.magnific-popup.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/magnific/magnific-popup.css');

        //用户画像
        $this->document->addScript('catalog/view/javascript/layer/layer.js');
        $this->document->addStyle('catalog/view/javascript/user_portrait/user_portrait.css?v=' . APP_VERSION);
        $this->document->addScript('catalog/view/javascript/user_portrait/user_portrait.js?v=' . APP_VERSION);
        //用户画像URL
        $data['user_portrait_url'] = $this->url->link('common/user_portrait/get_user_portrait_data', '', true);

        $this->load->model('customerpartner/rma_management');

        if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        } else {
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');
            $data['separate_view'] = false;
            $data['separate_column_left'] = '';
        }
        $data['tickets'] = $this->load->controller('common/ticket');
        $this->response->setOutput($this->load->view('account/customerpartner/rma_management/index', $data));
    }


    public function rmaInfo()
    {
        $this->load->language('account/customerpartner/rma_management');
        $this->load->language('common/cwf');
        $this->document->setTitle($this->language->get('heading_title'));
        // model
        $this->load->model('account/rma/manage');
        $this->load->model('customerpartner/rma_management');
        $this->load->model('account/customerpartner');
        $this->load->model('account/order');
        $this->load->model('catalog/product');
        $this->load->model('account/customerpartner/margin_order');
        $ramId = $this->request->request['rmaId'];
        //判断rma是否还是这个seller
        $customer_id = $this->customer->getId();
        $result = $this->model_customerpartner_rma_management->getSellerId($ramId);
        if ($customer_id != $result->seller_id && $customer_id != $result->original_seller_id) {
            $this->response->redirect($this->url->link('account/customerpartner/rma_management', true));
        }

        $data['rmaId'] = $ramId;
        // rma notification
        $this->load->model('account/notification');
        /** @var ModelAccountNotification $modelAccountNotification */
        $modelAccountNotification = $this->model_account_notification;
        $ramId > 0 && $modelAccountNotification->setRmaIsReadById($ramId);

        if (isset($this->session->data['success'])) {
            $data['success'] = session('success');
            $this->session->remove('success');
        }
        if (isset($this->session->data['rma_warning'])) {
            $data['rma_warning'] = session('rma_warning');
            $this->session->remove('rma_warning');
        }

        $url = '';

        if (isset($this->request->get['filter_product'])) {
            $url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_author'])) {
            $url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }

        if (isset($this->request->get['filter_date_added'])) {
            $url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
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

        //校验rma是否cancel
        $cancel_rma = $this->db->query("select cancel_rma from oc_yzc_rma_order where id =" . $ramId)->row['cancel_rma'];
        if ($cancel_rma == 1) {
            $this->response->redirect($this->url->link('account/customerpartner/rma_management', $url, true));
        }
        //保证金包销店铺的RMA Management,并且有保证金合同的rma申请,禁用submit按钮
        if (in_array($customer_id, $this->config->get('config_customer_group_ignore_check'))) {
            $canEditRmaFlag = $this->model_customerpartner_rma_management->getCanEditRmaFlag($ramId);
        } else {
            $canEditRmaFlag = true;
        }
        $data['canEditRmaFlag'] = $canEditRmaFlag;
        //设置退返品状态为正在处理
        if ($canEditRmaFlag) {
            $this->model_customerpartner_rma_management->updateRmaSellerStatus(3, $ramId);
        }
        $rmaInfos = $this->model_customerpartner_rma_management->getRmaInfoByRmaId($ramId);
        $orderInfo = $this->model_customerpartner_rma_management->getOrderInfo($ramId);
        $rmaInfo = [];
        array_walk($rmaInfos, function (&$item) use ($ramId, &$rmaInfo) {
            if (isset($item['id']) && $item['id'] == $ramId) {
                $rmaInfo = $item;
            }
        });
        if (customer()->isJapan()) {
            $rmaInfo['coupon_amount'] = intval($rmaInfo['coupon_amount']);
        }
        $data['current_rma'] = $rmaInfo;

        //判断是否有保证金合同的包销产品
        $this->load->model('account/rma_management');
        $this->load->model('account/product_quotes/margin_contract');
        $bx_id = $this->model_account_product_quotes_margin_contract->getMarginBxStoreId($customer_id);
        $service_id = $this->model_account_product_quotes_margin_contract->getMarginServiceStoreId($customer_id);
        $marginHaveProcessFlag = $this->model_account_rma_management
            ->checkMarginProductHaveProcess($rmaInfos[0]['order_id'], $rmaInfos[0]['product_id']);
        $isEuropeCountry = $this->country->isEuropeCountry($this->customer->getCountryId());
        $data['img_vat_url'] = ($this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url'))
            . 'image/product/vat.png';
        //获取销售订单的状态
        // 顾客订单
        if (isset($rmaInfos[0]['from_customer_order_id'])) {
            $customerOrder = $this->model_account_rma_management->getCustomerOrder($rmaInfos[0]['from_customer_order_id'], $rmaInfos[0]['buyer_id']);
            if ($marginHaveProcessFlag && $customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
                $order_id = $orderInfo['order_id'];
                $order_info = $this->model_customerpartner_rma_management->getOrder($order_id);
                $order_line_info = $this->model_customerpartner_rma_management->getOrderLineInfo($order_id, $rmaInfos[0]['product_id']);
                $marginResult = $this->model_account_rma_management->getMarginPriceInfo($order_line_info['product_id'], $order_line_info['quantity'], $order_line_info['order_product_id']);
                $data['order_id'] = [
                    $marginResult['advanceOrderId'],
                    $order_id
                ];
                $marginOrderInfoFlag = true;
                $products = $this->model_customerpartner_rma_management->getSellerOrderProductInfo($ramId);
                foreach ($products as $product) {
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

                    $product_tracking = $this->model_account_customerpartner->getOdrTracking($order_id, $product['product_id'], $this->customer->getId());

                    if ($product['paid_status'] == 1) {
                        $paid_status = $this->language->get('text_paid');
                    } else {
                        $paid_status = $this->language->get('text_not_paid');
                    }

                    $tag_array = $this->model_catalog_product->getProductSpecificTag($product['product_id']);
                    $tags = array();
                    if (isset($tag_array)) {
                        foreach ($tag_array as $tag) {
                            if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                                //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                                $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                                $tags[] = '<img data-toggle="tooltip"  class="' . $tag['class_style'] . '"  title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                            }
                        }
                    }
                    $data['isQuote'] = $product['amount_price_per'] != 0 ? true : false;
                    $data['isEuropeCountry'] = $isEuropeCountry;

                    $data['all_campaign_amount'] = $product['all_campaign_amount'];
                    $data['all_campaign_amount_show'] = $this->currency->formatCurrencyPrice($data['all_campaign_amount'], $this->session->get('currency'));
                    $data['all_coupon_amount'] = $product['all_coupon_amount'];

                    $data['products'][] = array(
                        'product_id' => $product['product_id'],
                        'name' => $product['name'],
                        'model' => $product['model'],
                        'option' => $option_data,
                        'mpn' => $product_info['mpn'],
                        'sku' => $product_info['sku'],
                        'tracking' => isset($product_tracking['tracking']) ? $product_tracking['tracking'] : '',
                        'quantity' => $product['quantity'],
                        'paid_status' => $paid_status,
                        'price' => $marginResult['unitPrice'],
                        // 'total' => $marginResult['total'],
                        'total' => $marginResult['total'] - $data['all_campaign_amount'],
                        'order_product_status' => $product['order_product_status'],
                        'tag' => $tags,
                        'service_fee_per' => $marginResult['serviceFee'],
                        'freight' => $marginResult['freight'],
                        'amount_price_per' => $this->currency->formatCurrencyPrice(-$product['amount_price_per'], $order_info['currency_code'], $order_info['currency_value']),
                        'amount_service_fee_per' => $this->currency->formatCurrencyPrice(-$product['amount_service_fee_per'], $order_info['currency_code'], $order_info['currency_value']),
                    );
                }
                $this->load->model('mp_localisation/order_status');
                $data['order_statuses'] = $this->model_mp_localisation_order_status->getOrderStatuses();
                $data['totals'] = array();

                $totalRest = $this->model_customerpartner_rma_management->getOrderTotalPrice($order_id, $orderInfo['product_id']);
                $totalAdvance = $this->model_customerpartner_rma_management->getOrderTotalPrice($marginResult['advanceOrderId'], $marginResult['advanceProductId']);
                if ($totalRest) {
                    if (isset($totalRest[0]['shipping_applied']) && $totalRest[0]['shipping_applied']) {
                        $data['totals'][] = array(
                            'title' => $totalRest[0]['shipping'],
                            'text' => $this->currency->format($totalRest[0]['shipping_applied'], $order_info['currency_code'], $order_info['currency_value']),
                        );
                    }
                    $total = 0;
                    if (isset($totalRest[0]['total'])) {
                        $total = $totalRest[0]['total'];
                    }
                    if ($totalRest[0]['sub_total'] != 0) {
                        $sub_total = ($totalAdvance[0]['sub_total'] - $totalAdvance[0]['service_fee']) * $order_line_info['quantity'] / $marginResult['num'] + $totalRest[0]['sub_total'] - $totalRest[0]['service_fee'];
                        $data['totals'][] = array(
                            'title' => 'Sub-Total',
                            //'title' => $isEuropeCountry ? "<img data-toggle='tooltip'  style='padding-right: 5px;width: 26px' src=" . $data['img_vat_url'] . " />" . 'Sub-Total' : 'Sub-Total',
                            'text' => $this->currency->format($sub_total, $order_info['currency_code'], $order_info['currency_value']),
                        );
                    }
                    if ($totalRest[0]['service_fee'] != 0) {
                        $service_fee = $totalAdvance[0]['service_fee'] * $order_line_info['quantity'] / $marginResult['num'] + $totalRest[0]['service_fee'];
                        $data['totals'][] = array(
                            'title' => 'Service Fee',
                            'text' => $this->currency->format($service_fee, $order_info['currency_code'], $order_info['currency_value']),
                        );
                    }
                    if ($totalRest[0]['freight'] != 0) {
                        $data['totals'][] = array(
                            'title' => 'Fulfillment',
                            'text' => $this->currency->format($totalRest[0]['freight'], $order_info['currency_code'], $order_info['currency_value']),
                        );
                    }
                    if ($data['all_campaign_amount'] > 0) {
                        $data['totals'][] = array(
                            'title' => 'Promotion Discount',
                            'text' => '-' . $this->currency->formatCurrencyPrice($data['all_campaign_amount'], $this->session->get('currency'))
                        );
                    }
                    $total_sum = $totalAdvance[0]['total'] * $order_line_info['quantity'] / $marginResult['num'] + $totalRest[0]['total'] + $totalRest[0]['freight'];
                    $total_sum = $total_sum - $data['all_campaign_amount'];
                    $tmpTotal = [
                        'title' => 'Order Total',
                        'text' => $this->currency->format($total_sum, $order_info['currency_code'], $order_info['currency_value']),
                    ];
                    if ($data['all_coupon_amount'] > 0) {
                        $tmpTotal['giga_coupon_show'] = $this->currency->formatCurrencyPrice($data['all_coupon_amount'], $this->session->get('currency'));
                        $tmpTotal['buyer_paid_show'] = $this->currency->formatCurrencyPrice($total_sum - $data['all_coupon_amount'], $this->session->get('currency'));
                    }
                    $data['totals'][] = $tmpTotal;
                }
            } else {
                $order_id = $orderInfo['order_id'];
                $data['order_id'] = [$order_id];
                $data['delivery_type'] = $orderInfo['delivery_type'];
                $data['date_added'] = date($this->language->get('datetime_format'), strtotime($orderInfo['date_added']));
                $data['payment_method'] = $orderInfo['payment_method'];
                $marginOrderInfoFlag = false;
            }
        } else {
            $order_id = $orderInfo['order_id'];
            $data['order_id'] = [$order_id];
            $data['delivery_type'] = $orderInfo['delivery_type'];
            $data['date_added'] = date($this->language->get('datetime_format'), strtotime($orderInfo['date_added']));
            $data['payment_method'] = $orderInfo['payment_method'];
            $marginOrderInfoFlag = false;
        }
        $data['marginOrderInfoFlag'] = $marginOrderInfoFlag;
        if (!$marginOrderInfoFlag) {
            $order_info = $this->model_customerpartner_rma_management->getOrder($order_id);
            $products = $this->model_customerpartner_rma_management->getSellerOrderProductInfo($ramId);
            foreach ($products as $product) {
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

                $product_tracking = $this->model_account_customerpartner->getOdrTracking($order_id, $product['product_id'], $this->customer->getId());

                if ($product['paid_status'] == 1) {
                    $paid_status = $this->language->get('text_paid');
                } else {
                    $paid_status = $this->language->get('text_not_paid');
                }

                $tag_array = $this->model_catalog_product->getProductSpecificTag($product['product_id']);
                $tags = array();
                if (isset($tag_array)) {
                    foreach ($tag_array as $tag) {
                        if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip"  class="' . $tag['class_style'] . '" title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                        }
                    }
                }
                $data['isQuote'] = $product['amount_price_per'] != 0 ? true : false;
                $data['isEuropeCountry'] = $isEuropeCountry;
                $unit_price = $this->currency->format($product['opprice'] - $product['amount_price_per'], $order_info['currency_code'], $order_info['currency_value']);
                $tips_freight_difference_per = '';
                if ($product['freight_difference_per'] > 0) {
                    $freight_diff = true;
                    $tips_freight_difference_per = str_replace(
                        '_freight_difference_per_',
                        $this->currency->formatCurrencyPrice($product['freight_difference_per'], $this->session->data['currency']),
                        $this->language->get('tips_freight_difference_per')
                    );
                } else {
                    $freight_diff = false;
                }
                $data['all_campaign_amount'] = $product['all_campaign_amount'];
                $data['all_campaign_amount_show'] = '-' . $this->currency->formatCurrencyPrice($data['all_campaign_amount'], $this->session->get('currency'));
                $data['all_coupon_amount'] = $product['all_coupon_amount'];

                $data['products'][] = array(
                    'product_id' => $product['product_id'],
                    'name' => $product['name'],
                    'model' => $product['model'],
                    'option' => $option_data,
                    'mpn' => $product_info['mpn'],
                    'sku' => $product_info['sku'],
                    'tracking' => isset($product_tracking['tracking']) ? $product_tracking['tracking'] : '',
                    'quantity' => $product['quantity'],
                    'paid_status' => $paid_status,
                    //'price' => $isEuropeCountry ? "<img data-toggle='tooltip'  style='padding-right: 5px;width: 26px' src=" . $data['img_vat_url'] . " />" . $unit_price : $unit_price,
                    'price' => $unit_price,
                    //'total' => $this->currency->format($product['c2oprice'] - ($product['amount_price_per'] + $product['amount_service_fee_per']) * $product['quantity'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order_info['currency_code'], $order_info['currency_value']),
                    'total' => $this->currency->format($product['c2oprice'] - ($product['amount_price_per'] + $product['amount_service_fee_per']) * $product['quantity'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0 - $data['all_campaign_amount']), $order_info['currency_code'], $order_info['currency_value']),
                    'order_product_status' => $product['order_product_status'],
                    'tag' => $tags,
                    'service_fee_per' => $this->currency->formatCurrencyPrice($product['service_fee_per'] - $product['amount_service_fee_per'], $order_info['currency_code'], $order_info['currency_value']),
                    'amount_price_per' => $this->currency->formatCurrencyPrice(-$product['amount_price_per'], $order_info['currency_code'], $order_info['currency_value']),
                    'amount_service_fee_per' => $this->currency->formatCurrencyPrice(-$product['amount_service_fee_per'], $order_info['currency_code'], $order_info['currency_value']),
                    'freight' => $this->currency->formatCurrencyPrice($product['freight_per'] + $product['package_fee'], $order_info['currency_code'], $order_info['currency_value']),
                    'freight_diff' => $freight_diff,
                    'tips_freight_difference_per' => $tips_freight_difference_per
                );
            }

            $this->load->model('mp_localisation/order_status');
            $data['order_statuses'] = $this->model_mp_localisation_order_status->getOrderStatuses();
            $data['totals'] = array();

            $totals = $this->model_customerpartner_rma_management->getOrderTotalPrice($order_id, $orderInfo['product_id']);

            if ($totals) {

                if (isset($totals[0]['shipping_applied']) && $totals[0]['shipping_applied']) {
                    $data['totals'][] = array(
                        'title' => $totals[0]['shipping'],
                        'text' => $this->currency->format($totals[0]['shipping_applied'], $order_info['currency_code'], $order_info['currency_value']),
                    );
                }
                $total = 0;
                if (isset($totals[0]['total'])) {
                    $total = $totals[0]['total'];
                }
                if ($totals[0]['sub_total'] != 0) {
                    $data['totals'][] = array(
                        'title' => 'Sub-Total',
                        //'title' => $isEuropeCountry ? "<img data-toggle='tooltip'  style='padding-right: 5px;width: 26px' src=" . $data['img_vat_url'] . " />" . 'Sub-Total' : 'Sub-Total',
                        'text' => $this->currency->format($totals[0]['sub_total'] - $totals[0]['amount_price'] - $totals[0]['service_fee'], $order_info['currency_code'], $order_info['currency_value']),
                    );
                }
                if ($totals[0]['service_fee'] != 0) {
                    $data['totals'][] = array(
                        'title' => 'Total Service Fee',
                        'text' => $this->currency->format($totals[0]['service_fee'] - $totals[0]['amount_service_fee'], $order_info['currency_code'], $order_info['currency_value']),
                    );
                }
                if ($totals[0]['freight'] != 0) {
                    $data['totals'][] = array(
                        'title' => 'Fulfillment',
                        'text' => $this->currency->format($totals[0]['freight'], $order_info['currency_code'], $order_info['currency_value']),
                    );
                }
                if ($data['all_campaign_amount'] > 0) {
                    $data['totals'][] = array(
                        'title' => 'Promotion Discount',
                        'text' => '-' . $this->currency->formatCurrencyPrice($data['all_campaign_amount'], $this->session->get('currency'))
                    );
                }
                $tempTotal = $totals[0]['total'] + $totals[0]['freight'];
                $tempTotal = $tempTotal - $data['all_campaign_amount'];
                $total = [
                    'title' => 'Order Total',
                    'text' => $this->currency->format($tempTotal, $order_info['currency_code'], $order_info['currency_value']),
                ];
                if ($data['all_coupon_amount'] > 0) {
                    $total['giga_coupon_show'] = $this->currency->formatCurrencyPrice($data['all_coupon_amount'], $this->session->get('currency'));
                    $total['buyer_paid_show'] = $this->currency->formatCurrencyPrice($tempTotal - $data['all_coupon_amount'], $this->session->get('currency'));
                }
                $data['totals'][] = $total;
            }
        }
        if (isset($rmaInfos[0]['buyer_id'])) {
            $this->load->model('account/customer');
            $buyer_info = $this->model_account_customer->getCustomer($rmaInfos[0]['buyer_id']);
            $buyer_name = $buyer_info['nickname'] . '(' . $buyer_info['user_number'] . ')';
            if (isset($buyer_name)) {
                $data['buyer_id'] = $rmaInfos[0]['buyer_id'];
                $data['ex_vat'] = VATToolTipWidget::widget(['customer' => Customer::query()->find($rmaInfos[0]['buyer_id']), 'is_show_vat' => true])->render();
                $data['buyer_name'] = $buyer_name;
                $this->load->language('account/customerpartner/rma_management');
                $data['tip_home_pickup_logo'] = $this->language->get('tip_home_pickup_logo');
                $data['tip_drop_shipping_logo'] = $this->language->get('tip_drop_shipping_logo');
                $data['is_home_pickup'] = in_array($buyer_info['customer_group_id'], COLLECTION_FROM_DOMICILE);
            }
        }
        if (strtoupper($rmaInfos[0]['orders_from']) == 'AMAZON') {
            $data['isAmazon'] = 1;
        } else {
            $data['isAmazon'] = 0;
        }
        $data['rmaInfos'] = $rmaInfos;
        $data['orderType'] = $rmaInfos[0]['order_type'];

        $rmaComments = $this->model_customerpartner_rma_management->getRmaComments($ramId, 1);
        if (isset($rmaComments['comments'])) {
            $data['rmaComments'] = $rmaComments['comments'];
        }
        $rmaAttachments = $this->model_customerpartner_rma_management->getRmaAttchments($ramId, 1);

        $data['rmaAttachments'] = array();
        $this->load->model('tool/image');
        foreach ($rmaAttachments as $rmaAttachment) {
            $data['rmaAttachments'][] = ['file_path' => StorageCloud::rmaFile()->getUrl($rmaAttachment['file_path'])];
        }

        $sellerRmaImages = $this->model_customerpartner_rma_management->getRmaAttchments($ramId, 2);

        $data['sellerRmaImages'] = array();

        foreach ($sellerRmaImages as $sellerRmaImage) {
            $data['sellerRmaImages'][] = ['file_path' => StorageCloud::rmaFile()->getUrl($sellerRmaImage['file_path'])];
        }

        $rmaReshipments = $this->model_customerpartner_rma_management->getRmaReshipments($ramId);
        if (count($rmaReshipments) == 0) {
            $data['rmaReshipmentsArray'][0] = array('status_reshipment' => 0);
        } else {
            $data['rmaReshipmentsArray'] = $rmaReshipments;
        }
        $rmaRefund = $this->model_customerpartner_rma_management->getRmaRefound($ramId);

        $data['rmaRefund'] = $this->currency->format(round($rmaRefund['apply_refund_amount'], 2), $order_info['currency_code'], $order_info['currency_value']);
        $data['originRmaRefund'] = round($rmaRefund['apply_refund_amount'], 2);
        $symbolLeft = $this->currency->getSymbolLeft($order_info['currency_code']);
        $symbolRight = $this->currency->getSymbolRight($order_info['currency_code']);
        $data['currency'] = $symbolLeft . $symbolRight;
        $data['status_refund'] = $rmaRefund['status_refund'];
        $data['seller_refund_comments'] = $rmaRefund['seller_refund_comments'];
        $data['agree_refund_money'] = $this->formatPrice($rmaRefund['actual_refund_amount']);

        $country = session('country');
        $data['country'] = $country;

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => "RMA Management",
            'href' => $this->url->link('account/customerpartner/rma_management', $url, true)
        );
        $data['breadcrumbs'][] = [
            'text' => 'RMA Details',
            'href' => 'javascript:void(0)',
        ];

        if (!isset($this->request->get['review_id'])) {
            $data['action'] = $this->url->link('account/customerpartner/rma_management/add', $url, true);
        } else {
            $data['action'] = $this->url->link('account/customerpartner/rma_management/edit', '&review_id=' . $this->request->get['review_id'] . $url, true);
        }
        //rma_history
        $this->load->model('account/order');
        $this->load->language('account/return');
        $rma_history = $this->model_account_order->getRmaHistories($order_id, $orderInfo['product_id']);
        $data['rmaHistories'] = $rma_history;

        $data['cancel'] = $this->url->link('account/customerpartner/rma_management', $url, true);

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
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

        //判断是否是包销产品
        $this->load->model('account/product_quotes/margin_contract');
        $margin_agreement = $this->model_account_customerpartner_margin_order
            ->getAgreementInfoByOrderProduct((int)$order_info['order_id'], (int)$orderInfo['product_id']);
        if (isset($margin_agreement['agreement_id'])) {
            $data['marginFlag'] = true;
            $data['margin_agreement_id'] = $margin_agreement['agreement_id'];
            if ((isset($bx_id) && $bx_id == $customer_id) || (isset($service_id) && $service_id == $customer_id)) {
                $data['margin_link'] = null;
            } else {
                $data['margin_link'] = $this->url->link('account/product_quotes/margin_contract/view', '&agreement_id=' . $margin_agreement['agreement_id'], true);
            }
        }
        if (!empty($data['order_id'])) {
            $data['order_list_size'] = sizeof($data['order_id']);
            foreach ($data['order_id'] as $loop_order_id) {
                if (isset($bx_id) && $bx_id == $customer_id && $loop_order_id != $order_id) {
                    $data['order_list'][] = [
                        'order_id' => $loop_order_id
                    ];
                } else {
                    $data['order_list'][] = [
                        'order_id' => $loop_order_id,
                        'order_link' => $this->url->link('account/customerpartner/orderinfo', '&order_id=' . $loop_order_id, true)
                    ];
                }
            }
        }
        // 获取trackingNumber
        $data['trackingNumber'] = $this->getTrackingNumberByRmaID($ramId);

        //用户画像
        $data['styles'][] = 'catalog/view/javascript/layer/theme/default/layer.css';
        $data['scripts'][] = 'catalog/view/javascript/layer/layer.js';
        $data['styles'][] = 'catalog/view/javascript/user_portrait/user_portrait.css?v=' . APP_VERSION;
        $data['scripts'][] = 'catalog/view/javascript/user_portrait/user_portrait.js?v=' . APP_VERSION;
        //参加返点的产品的RMA
        $rebateRmaInfo = $this->model_customerpartner_rma_management->getRebateRmaInfo($ramId);
        /*
         * Buyer发起的退款（RMA-Refund）是针对采购订单或Canceled的销售订单的
         * 申请退款的产品参与过/正在参与返点，且计入了返点数量的
         */
        $maxRefundMoney = 0;
        if ($rebateRmaInfo['order_type'] == RmaType::SALES_ORDER) {
            //保证金的返金金额 complete:返金金额为（保证金头款+保证金尾款）*qty,cancel:返金金额为（保证金尾款）*qty
            $order_result = $this->model_customerpartner_rma_management->getRmaFromOrderInfo($ramId);
            if (isset($order_result['order_status'])) {
                //判断是否有保证金合同的包销产品
                $this->load->model('account/rma_management');
                $marginHaveProcessFlag = $this->model_account_rma_management
                    ->checkMarginProductHaveProcess($order_result['order_id'], $order_result['product_id']);
                if ($order_result['order_status'] == CustomerSalesOrderStatus::COMPLETED && $marginHaveProcessFlag) {
                    //获取complete订单的返金金额
                    $maxRefundMoney = $this->model_customerpartner_rma_management->getMarginOrderInfo($ramId);
                } else {
                    $maxRefundMoney = $this->model_customerpartner_rma_management->getOrderProductPrice($ramId);
                }
            }
        } else {
            $maxRefundMoney = $this->model_customerpartner_rma_management->getPurchaseOrderRmaPrice($ramId);
        }
        if (
            $rebateRmaInfo['order_type'] == RmaType::PURCHASE_ORDER
            || (
                $rebateRmaInfo['order_status'] == CustomerSalesOrderStatus::CANCELED
                && app(RamRepository::class)->checkSalesOrderRmaFirstRefund($ramId)
            )
        ) {
            //查看返点情况
            $rebateInfo = $this->model_customerpartner_rma_management->getRebateInfo($rebateRmaInfo['order_id'], $rebateRmaInfo['product_id']);
            //查看返点协议申请情况
            if (!empty($rebateInfo)) {
                $rebateRefundInfo =
                    app(RebateRepository::class)
                        ->checkRebateRefundMoney(
                            $maxRefundMoney, $rebateRmaInfo['rmaQty'],
                            $rebateRmaInfo['order_id'], $rebateRmaInfo['product_id'],
                            $rebateRmaInfo['order_type'] == RmaType::PURCHASE_ORDER
                        );
                $data['tipSellerMsg'] = $rebateRefundInfo['sellerMsg'] ?? '';
            }
        }
        $data['rma_message'] = $this->load->controller('account/customerpartner/rma_management/rma_message', ['rma_no' => $rmaInfo['rma_order_id'], 'ticket' => 1]);
        $data['sales_order_status'] = $this->model_account_rma_manage->getSalesOrderStatusList();
        //cwf check
        $data['cwf_order'] = $this->model_account_rma_manage->getCWFInfo(
            (int)$rmaInfo['order_id'], (int)$rmaInfo['sales_order_id']
        );
        // 查询销售单是否购买保障服务
        if (isset($data['current_rma']['order_status']) && $data['current_rma']['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
            $data['safeguard_bill_list'] = app(SafeguardBillRepository::class)->getSafeguardBillsBySaleId($rmaInfo['sales_order_id'] ?? 0, SafeguardBillStatus::ACTIVE);
        }

        $this->response->setOutput($this->load->view('account/customerpartner/seller_rma_form', $data));
    }

    public function rma_message($data)
    {
        $this->load->model('account/ticket');
        $this->load->model('account/customerpartner');

        $data['info'] = $this->model_account_ticket->getTicketInfoByRmaid($data['rma_no']);
        if (!$data['info']) {
            return null;
        }
        $results = $this->model_account_ticket->getTicketsByRmaId($data['rma_no'], $this->customer->getId());
        $LoginInInfo = $this->model_account_customerpartner->getLoginInInfoByCustomerId();
        foreach ($results as &$value) {
            //上传的附件，需要处理格式。
            $attachmentsList = $value['attachments'] ? json_decode($value['attachments'], true) : [];
            foreach ($attachmentsList as $key => $item) {
                $item['is_img'] = $this->isImgByName($item['name']);
                $fileUrl = $item['url'];
                if (\Illuminate\Support\Str::contains($fileUrl, '%')) {
                    $fileUrl = urlencode($fileUrl);
                }
                if (StorageCloud::root()->fileExists($fileUrl)) {
                    $item['url'] = StorageCloud::root()->getUrl($fileUrl, ['check-exist' => false]);
                }

                $attachmentsList[$key] = $item;
            }
            $value['attachments'] = $attachmentsList;
            $value['showName'] = $value['create_admin_id'] ? 'GIGACLOUD' : ($LoginInInfo['nickname'] . '(' . $LoginInInfo['user_number'] . ')');
        }
        $data['lists'] = $results;
        $data['tickets'] = $data['ticket'] ? $this->load->controller('common/ticket') : '';
        return $this->load->view('account/customerpartner/rma_message', $data);
    }


    private function isImgByName($name)
    {
        $suffix = strtolower(substr(strrchr($name, '.'), 1));
        $suffixArr = [
            'gif', 'jpg', 'jpeg', 'png', 'swf', 'psd', 'bmp', 'tiff_ii', 'tiff_mm', 'jpc', 'jp2', 'jpx', 'jb2', 'swc', 'iff', 'wbmp', 'xbm'
        ];
        if (in_array($suffix, $suffixArr)) {
            return true;
        } else {
            return false;
        }
    }

    // 现货保证金的rma
    public function margin_rma_info()
    {
        $this->load->language('account/customerpartner/rma_management');
        $this->load->language('common/cwf');
        $this->document->setTitle($this->language->get('heading_title'));
        // model
        $this->load->model('customerpartner/rma_management');
        $this->load->model('account/customerpartner');
        $this->load->model('account/order');
        $this->load->model('catalog/product');
        $this->load->model('account/customerpartner/margin_order');
        $this->load->model('account/notification');
        $this->load->model('account/rma_management');
        $this->load->model('account/product_quotes/margin_contract');
        $this->load->model('tool/image');
        $this->load->model('common/product');
        $ramId = $this->request->request['rmaId'];
        $customer_id = $this->customer->getId();
        $data['rmaId'] = $ramId;
        $this->removeSession($data);
        $url = $this->resolveRequestUrl();
        //保证金包销店铺的RMA Management,并且有保证金合同的rma申请,禁用submit按钮
        if (in_array($customer_id, $this->config->get('config_customer_group_ignore_check'))) {
            $canEditRmaFlag = $this->model_customerpartner_rma_management->getCanEditRmaFlag($ramId);
        } else {
            $canEditRmaFlag = true;
        }
        $data['canEditRmaFlag'] = $canEditRmaFlag;
        //设置退返品状态为正在处理
        if ($canEditRmaFlag) {
            $this->model_customerpartner_rma_management->updateRmaSellerStatus(3, $ramId);
        }
        $rmaInfos = $this->model_customerpartner_rma_management->getRmaInfoByRmaId($ramId);
        $orderInfo = $this->model_customerpartner_rma_management->getOrderInfo($ramId);
        $rmaInfo = [];
        array_map(function ($item) use ($ramId, &$rmaInfo) {
            if (isset($item['id']) && $item['id'] == $ramId) {
                $rmaInfo = $item;
            }
        }, $rmaInfos);
        if (customer()->isJapan()) {
            $rmaInfo['coupon_amount'] = intval($rmaInfo['coupon_amount']);
        }
        $data['current_rma'] = $rmaInfo;
        //判断是否有保证金合同的包销产品
        $bx_id = $this->model_account_product_quotes_margin_contract->getMarginBxStoreId($customer_id);
        $service_id = $this->model_account_product_quotes_margin_contract->getMarginServiceStoreId($customer_id);
        $isEuropeCountry = $this->customer->isEurope();
        $data['img_vat_url'] = ($this->request->server['HTTPS']
                ? $this->config->get('config_ssl')
                : $this->config->get('config_url'))
            . 'image/product/vat.png';
        $order_id = $orderInfo['order_id'];
        $order_info = $this->model_customerpartner_rma_management->getOrder($order_id);
        $order_line_info = $this->model_customerpartner_rma_management->getOrderLineInfo($order_id, $rmaInfos[0]['product_id']);
        $marginResult = $this->model_account_rma_management->getMarginPriceInfo(
            $order_line_info['product_id'],
            $order_line_info['quantity'],
            $order_line_info['order_product_id']
        );
        $data['order_id'] = [$marginResult['advanceOrderId'], $order_id];
        $data['delivery_type'] = $order_info['delivery_type'];
        $products = $this->model_customerpartner_rma_management->getSellerOrderProductInfoIncludeSaleLine($ramId);
        foreach ($products as $product) {
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
            $product_tracking = $this->model_account_customerpartner->getOdrTracking(
                $order_id,
                $product['product_id'],
                $customer_id
            );
            if ($product['paid_status'] == 1) {
                $paid_status = $this->language->get('text_paid');
            } else {
                $paid_status = $this->language->get('text_not_paid');
            }
            $tag_array = $this->model_catalog_product->getProductSpecificTag($product['product_id']);
            $tags = [];
            foreach ($tag_array as $tag) {
                if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                    $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                    $tags[] = sprintf($this->language->get('tag_description'), $tag['class_style'], $tag['description'], $img_url);
                }
            }
            $data['isQuote'] = $product['amount_price_per'] != 0 ? true : false;
            $data['isEuropeCountry'] = $isEuropeCountry;
            // canReturnDeposit 判断能否退还定金
            // 目前只有销售订单 且 销售订单发出去 后才可以退还定金
            $canReturnDeposit = false;
            $isSalesOrder = false; // 是否为销售订单
            if (isset($rmaInfos[0]['from_customer_order_id'])) {
                $isSalesOrder = true;
                $customerOrder = $this->model_account_rma_management
                    ->getCustomerOrder($rmaInfos[0]['from_customer_order_id'], $rmaInfos[0]['buyer_id']);
                if ($customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
                    $canReturnDeposit = true;
                }
            }
            $data['canReturnDeposit'] = $canReturnDeposit;
            $data['isSalesOrder'] = $isSalesOrder;
            // 主图片处理
            $cacheImage = $this->model_tool_image->resize($product_info['image'], 50, 50);
            if (!$cacheImage) {
                $cacheImage = $this->model_tool_image->resize('no_image.png', 50, 50);
            }
            $data['all_campaign_amount'] = $product['all_campaign_amount'];
            $data['all_campaign_amount_show'] = '-' . $this->currency->formatCurrencyPrice($data['all_campaign_amount'], $this->session->get('currency'));
            $data['all_coupon_amount'] = $product['all_coupon_amount'];

            $data['products'][] = [
                'product_id' => $product['product_id'],
                'name' => $product['name'],
                'image' => $cacheImage,
                'model' => $product['model'],
                'option' => $option_data,
                'mpn' => $product_info['mpn'],
                'sku' => $product_info['sku'],
                'tracking' => isset($product_tracking['tracking']) ? $product_tracking['tracking'] : '',
                'quantity' => $product['quantity'],
                'paid_status' => $paid_status,
                'price' => $marginResult['unitPrice'],
                'total' => $marginResult['total'],
                'order_product_status' => $product['order_product_status'],
                'tag' => $tags,
                'service_fee_per' => $marginResult['serviceFee'],
                'freight' => $marginResult['freight'],
                'amount_price_per' => $this->currency->formatCurrencyPrice(-$product['amount_price_per'], $order_info['currency_code'], $order_info['currency_value']),
                'amount_service_fee_per' => $this->currency->formatCurrencyPrice(-$product['amount_service_fee_per'], $order_info['currency_code'], $order_info['currency_value']),
                // add by wjx
                // 头款单价
                'advance_unit_price' => $this->currency->formatCurrencyPrice(
                    $marginResult['advance_unit_price'],
                    $order_info['currency_code'],
                    $order_info['currency_value']
                ),
                // 尾款单价
                'rest_unit_price' => $this->currency->formatCurrencyPrice(
                    $marginResult['rest_unit_price'],
                    $order_info['currency_code'],
                    $order_info['currency_value']
                ),
                // 物流费单价
                'freight_unit_price' => $this->currency->formatCurrencyPrice(
                    $marginResult['freight_unit_price'],
                    $order_info['currency_code'],
                    $order_info['currency_value']
                ),
                // 服务费单价
                'poundage_per' => $this->currency->formatCurrencyPrice(
                    $marginResult['poundage_per'],
                    $order_info['currency_code'],
                    $order_info['currency_value']
                ),
                // 总计 ps 只有已完成的销售订单才可以返还定金
                'total_price' => $this->currency->formatCurrencyPrice(
                    (
                        $marginResult['freight_unit_price']
                        + $marginResult['rest_unit_price']
                        + ($canReturnDeposit ? $marginResult['advance_unit_price'] : 0)
                        + ($this->customer->isEurope() ? $marginResult['service_fee_per'] : 0)
                    ) * $product['quantity'] - $data['all_campaign_amount'],
                    $order_info['currency_code'],
                    $order_info['currency_value']
                ),
                'total_price_cal' => (
                        $marginResult['freight_unit_price']
                        + $marginResult['rest_unit_price']
                        + ($canReturnDeposit ? $marginResult['advance_unit_price'] : 0)
                        + ($this->customer->isEurope() ? $marginResult['service_fee_per'] : 0)
                    ) * $product['quantity'] - $data['all_campaign_amount'],
                'sub_total' => ($marginResult['rest_unit_price']
                        + ($canReturnDeposit ? $marginResult['advance_unit_price'] : 0)
                        + ($this->customer->isEurope() ? $marginResult['service_fee_per'] : 0)
                    ) * $product['quantity'],
                'service_fee_total' => ($this->customer->isEurope() ? $marginResult['service_fee_per'] : 0) * $product['quantity'],
                'freight_total' => ($marginResult['freight_unit_price']) * $product['quantity'],
            ];
        }

        $data['totals'][] = array(
            'title' => 'Sub-Total',
            //'title' => $isEuropeCountry ? "<img data-toggle='tooltip'  style='padding-right: 5px;width: 26px' src=" . $data['img_vat_url'] . " />" . 'Sub-Total' : 'Sub-Total',
            'text' => $this->currency->formatCurrencyPrice($data['products'][0]['sub_total'], $this->session->get('currency')),
        );
        if ($data['products'][0]['service_fee_total'] != 0) {
            $data['totals'][] = array(
                'title' => 'Total Service Fee',
                'text' => $this->currency->formatCurrencyPrice($data['products'][0]['service_fee_total'], $this->session->get('currency')),
            );
        }
        if ($data['products'][0]['freight_total'] != 0) {
            $data['totals'][] = array(
                'title' => 'Freight',
                'text' => $this->currency->formatCurrencyPrice($data['products'][0]['freight_total'], $this->session->get('currency')),
            );
        }
        if ($data['all_campaign_amount'] > 0) {
            $data['totals'][] = array(
                'title' => 'Promotion Discount',
                'text' => '-' . $this->currency->formatCurrencyPrice($data['all_campaign_amount'], $this->session->get('currency'))
            );
        }
        $tempTotal = $data['products'][0]['total_price_cal'];
        $total = [
            'title' => 'Order Total',
            'text' => $this->currency->format($tempTotal, $order_info['currency_code'], $order_info['currency_value']),
        ];
        if ($data['all_coupon_amount'] > 0) {
            $total['giga_coupon_show'] = $this->currency->formatCurrencyPrice($data['all_coupon_amount'], $this->session->get('currency'));
            $total['buyer_paid_show'] = $this->currency->formatCurrencyPrice($tempTotal - $data['all_coupon_amount'], $this->session->get('currency'));
        }
        $data['totals'][] = $total;

        $this->load->model('mp_localisation/order_status');
        $data['order_statuses'] = $this->model_mp_localisation_order_status->getOrderStatuses();
        $data['marginOrderInfoFlag'] = true;
        if (isset($rmaInfos[0]['buyer_id'])) {
            $this->load->model('account/customer');
            $buyer_info = $this->model_account_customer->getCustomer($rmaInfos[0]['buyer_id']);
            $buyer_name = $buyer_info['nickname'] . '(' . $buyer_info['user_number'] . ')';
            if (isset($buyer_name)) {
                $data['buyer_id'] = $rmaInfos[0]['buyer_id'];
                $data['buyer_name'] = $buyer_name;
                $this->load->language('account/customerpartner/rma_management');
                $data['tip_home_pickup_logo'] = $this->language->get('tip_home_pickup_logo');
                $data['tip_drop_shipping_logo'] = $this->language->get('tip_drop_shipping_logo');
                $data['is_home_pickup'] = in_array($buyer_info['customer_group_id'], COLLECTION_FROM_DOMICILE);
                $data['ex_vat'] = VATToolTipWidget::widget(['customer' => Customer::query()->find($rmaInfos[0]['buyer_id']), 'is_show_vat' => true])->render();
            }
        }
        if (strtoupper($rmaInfos[0]['orders_from']) == 'AMAZON') {
            $data['isAmazon'] = 1;
        } else {
            $data['isAmazon'] = 0;
        }
        $data['rmaInfos'] = $rmaInfos;
        $data['orderType'] = $rmaInfos[0]['order_type'];
        $rmaComments = $this->model_customerpartner_rma_management->getRmaComments($ramId, 1);
        if (isset($rmaComments['comments'])) {
            $data['rmaComments'] = $rmaComments['comments'];
        }
        $rmaAttachments = $this->model_customerpartner_rma_management->getRmaAttchments($ramId, 1);
        $data['rmaAttachments'] = array();
        foreach ($rmaAttachments as $rmaAttachment) {
            $data['rmaAttachments'][] = ['file_path' => StorageCloud::rmaFile()->getUrl($rmaAttachment['file_path'])];
        }
        $sellerRmaImages = $this->model_customerpartner_rma_management->getRmaAttchments($ramId, 2);
        $data['sellerRmaImages'] = array();
        foreach ($sellerRmaImages as $sellerRmaImage) {
            $data['sellerRmaImages'][] = ['file_path' => StorageCloud::rmaFile()->getUrl($sellerRmaImage['file_path'])];
        }
        $rmaReshipments = $this->model_customerpartner_rma_management->getRmaReshipments($ramId);
        if (count($rmaReshipments) == 0) {
            $data['rmaReshipmentsArray'][0] = array('status_reshipment' => 0);
        } else {
            $data['rmaReshipmentsArray'] = $rmaReshipments;
        }
        $rmaRefund = $this->model_customerpartner_rma_management->getRmaRefound($ramId);
        $data['rmaRefund'] = $this->currency->format(
            round($rmaRefund['apply_refund_amount'],
                2), $order_info['currency_code'],
            $order_info['currency_value']
        );
        $data['originRmaRefund'] = round($rmaRefund['apply_refund_amount'], 2);
        $symbolLeft = $this->currency->getSymbolLeft($order_info['currency_code']);
        $symbolRight = $this->currency->getSymbolRight($order_info['currency_code']);
        $data['currency'] = $symbolLeft . $symbolRight;
        $data['status_refund'] = $rmaRefund['status_refund'];
        $data['seller_refund_comments'] = $rmaRefund['seller_refund_comments'];
        $data['agree_refund_money'] = $this->formatPrice($rmaRefund['actual_refund_amount']);

        $country = session('country');
        $data['country'] = $country;

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_seller_center'),
            'href' => $this->url->link('customerpartner/seller_center/index', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => "RMA Management",
            'href' => $this->url->link('account/customerpartner/rma_management', $url, true)
        );
        $data['breadcrumbs'][] = [
            'text' => 'RMA Details',
            'href' => 'javascript:void(0)',
        ];

        if (!isset($this->request->get['review_id'])) {
            $data['action'] = $this->url->link('account/customerpartner/rma_management/add', $url, true);
        } else {
            $data['action'] = $this->url->link('account/customerpartner/rma_management/edit', '&review_id=' . $this->request->get['review_id'] . $url, true);
        }
        //rma_history
        $this->load->model('account/order');
        $this->load->language('account/return');
        $rma_history = $this->model_account_order->getRmaHistories($order_id, $orderInfo['product_id']);
        // N-624需求 排除掉rma history自身的记录
        $data['rmaHistories'] = array_filter($rma_history, function ($item) use ($ramId) {
            return ($item['id'] != $ramId);
        });
        $data['cancel'] = $this->url->link('account/customerpartner/rma_management', $url, true);
        // add column info
        $this->template($data);
        //判断是否是包销产品
        $this->load->model('account/product_quotes/margin_contract');
        $margin_agreement = $this->model_account_customerpartner_margin_order
            ->getAgreementInfoByOrderProduct((int)$order_info['order_id'], (int)$orderInfo['product_id']);
        if (isset($margin_agreement['agreement_id'])) {
            $data['marginFlag'] = true;
            $data['margin_agreement_id'] = $margin_agreement['agreement_id'];
            if ((isset($bx_id) && $bx_id == $customer_id) || (isset($service_id) && $service_id == $customer_id)) {
                $data['margin_link'] = null;
            } else {
                $data['margin_link'] = $this->url->link('account/product_quotes/margin_contract/view', '&agreement_id=' . $margin_agreement['agreement_id'], true);
            }
        }
        if (!empty($data['order_id'])) {
            $data['order_list_size'] = sizeof($data['order_id']);
            foreach ($data['order_id'] as $loop_order_id) {
                if (isset($bx_id) && $bx_id == $customer_id && $loop_order_id != $order_id) {
                    $data['order_list'][] = [
                        'order_id' => $loop_order_id
                    ];
                } else {
                    $data['order_list'][] = [
                        'order_id' => $loop_order_id,
                        'order_link' => $this->url->link('account/customerpartner/orderinfo', '&order_id=' . $loop_order_id, true)
                    ];
                }
            }
        }
        // 获取trackingNumber
        $data['trackingNumber'] = $this->getTrackingNumberByRmaID($ramId);
        //用户画像seller_margin_rma_form
        $data['styles'][] = 'catalog/view/javascript/layer/theme/default/layer.css';
        $data['scripts'][] = 'catalog/view/javascript/layer/layer.js';
        $data['styles'][] = 'catalog/view/javascript/user_portrait/user_portrait.css?v=' . APP_VERSION;
        $data['scripts'][] = 'catalog/view/javascript/user_portrait/user_portrait.js?v=' . APP_VERSION;
        $data['rma_message'] = $this->load->controller('account/customerpartner/rma_management/rma_message', ['rma_no' => $rmaInfo['rma_order_id'], 'ticket' => 1]);
        if (isset($customerOrder['order_status']) && $customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
            $data['safeguard_bill_list'] = app(SafeguardBillRepository::class)->getSafeguardBillsBySaleId($customerOrder['id'], SafeguardBillStatus::ACTIVE);
        }

        $this->response->setOutput($this->load->view('account/customerpartner/seller_margin_rma_form', $data));
    }

    // 现货保证金的rma
    public function futures_rma_info()
    {
        $this->load->language('account/customerpartner/rma_management');
        $this->document->setTitle($this->language->get('heading_title'));
        // model
        $this->load->model('customerpartner/rma_management');
        $this->load->model('account/customerpartner');
        $this->load->model('account/order');
        $this->load->model('catalog/product');
        $this->load->model('account/customerpartner/futures_order');
        $this->load->model('account/notification');
        $this->load->model('account/rma_management');
        $this->load->model('account/product_quotes/margin_contract');
        $this->load->model('tool/image');
        $this->load->model('common/product');
        $ramId = $this->request->request['rmaId'];
        $customer_id = $this->customer->getId();
        $data['rmaId'] = $ramId;
        $this->removeSession($data);
        $url = $this->resolveRequestUrl();
        //保证金包销店铺的RMA Management,并且有保证金合同的rma申请,禁用submit按钮
        if (in_array($customer_id, $this->config->get('config_customer_group_ignore_check'))) {
            $canEditRmaFlag = $this->model_customerpartner_rma_management->getCanEditRmaFlag($ramId);
        } else {
            $canEditRmaFlag = true;
        }
        $data['canEditRmaFlag'] = $canEditRmaFlag;
        //设置退返品状态为正在处理
        if ($canEditRmaFlag) {
            $this->model_customerpartner_rma_management->updateRmaSellerStatus(3, $ramId);
        }
        $rmaInfos = $this->model_customerpartner_rma_management->getRmaInfoByRmaId($ramId);
        $orderInfo = $this->model_customerpartner_rma_management->getOrderInfo($ramId);
        $rmaInfo = [];
        array_map(function ($item) use ($ramId, &$rmaInfo) {
            if (isset($item['id']) && $item['id'] == $ramId) {
                $rmaInfo = $item;
            }
        }, $rmaInfos);
        if (customer()->isJapan()) {
            $rmaInfo['coupon_amount'] = intval($rmaInfo['coupon_amount']);
        }
        $data['current_rma'] = $rmaInfo;
        //判断是否有保证金合同的包销产品
        $bx_id = $this->model_account_product_quotes_margin_contract->getMarginBxStoreId($customer_id);
        $service_id = $this->model_account_product_quotes_margin_contract->getMarginServiceStoreId($customer_id);
        $isEuropeCountry = $this->customer->isEurope();
        $data['img_vat_url'] = ($this->request->server['HTTPS']
                ? $this->config->get('config_ssl')
                : $this->config->get('config_url'))
            . 'image/product/vat.png';
        $order_id = $orderInfo['order_id'];
        $order_info = $this->model_customerpartner_rma_management->getOrder($order_id);
        $order_line_info = $this->model_customerpartner_rma_management->getOrderLineInfo($order_id, $rmaInfos[0]['product_id']);
        //判断是否是期货产品
        $agreement_info = $this->model_account_customerpartner_futures_order
            ->getAgreementInfoByOrderProduct((int)$order_info['order_id'], (int)$orderInfo['product_id']);
        if ($agreement_info) {
            $data['futuresFlag'] = true;
            $data['agreement'] = $agreement_info;
            $data['futures_link'] = $this->url->link(
                'account/product_quotes/futures/sellerBidDetail',
                ['id' => $agreement_info['id']]
            );
        }
        $marginResult = $this->model_account_rma_management->getFutureMarginPriceInfo(
            $agreement_info['id'],
            $order_line_info['quantity'],
            $order_line_info['order_product_id']
        );
        $data['order_id'] = [$marginResult['advanceOrderId'], $order_id];
        $products = $this->model_customerpartner_rma_management->getSellerOrderProductInfoIncludeSaleLine($ramId);
        foreach ($products as $product) {
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
            $product_tracking = $this->model_account_customerpartner->getOdrTracking(
                $order_id,
                $product['product_id'],
                $customer_id
            );
            if ($product['paid_status'] == 1) {
                $paid_status = $this->language->get('text_paid');
            } else {
                $paid_status = $this->language->get('text_not_paid');
            }
            $tag_array = $this->model_catalog_product->getProductSpecificTag($product['product_id']);
            $tags = [];
            foreach ($tag_array as $tag) {
                if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                    $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                    $tags[] = sprintf($this->language->get('tag_description'), $tag['class_style'], $tag['description'], $img_url);
                }
            }
            $data['isQuote'] = $product['amount_price_per'] != 0 ? true : false;
            $data['isEuropeCountry'] = $isEuropeCountry;
            // canReturnDeposit 判断能否退还定金
            // 目前只有销售订单 且 销售订单发出去 后才可以退还定金
            $canReturnDeposit = false;
            $isSalesOrder = false; // 是否为销售订单
            if (isset($rmaInfos[0]['from_customer_order_id'])) {
                $isSalesOrder = true;
                $customerOrder = $this->model_account_rma_management
                    ->getCustomerOrder($rmaInfos[0]['from_customer_order_id'], $rmaInfos[0]['buyer_id']);
                if ($customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
                    $canReturnDeposit = true;
                }
            }
            $data['canReturnDeposit'] = $canReturnDeposit;
            $data['isSalesOrder'] = $isSalesOrder;
            // 主图片处理
            $cacheImage = $this->model_tool_image->resize($product_info['image'], 50, 50);
            if (!$cacheImage) {
                $cacheImage = $this->model_tool_image->resize('no_image.png', 50, 50);
            }
            $data['all_campaign_amount'] = $product['all_campaign_amount'];
            $data['all_campaign_amount_show'] = '-' . $this->currency->formatCurrencyPrice($data['all_campaign_amount'], $this->session->get('currency'));
            $data['all_coupon_amount'] = $product['all_coupon_amount'];

            $data['products'][] = [
                'product_id' => $product['product_id'],
                'name' => $product['name'],
                'image' => $cacheImage,
                'model' => $product['model'],
                'option' => $option_data,
                'mpn' => $product_info['mpn'],
                'sku' => $product_info['sku'],
                'tracking' => isset($product_tracking['tracking']) ? $product_tracking['tracking'] : '',
                'quantity' => $product['quantity'],
                'paid_status' => $paid_status,
                'price' => $marginResult['unitPrice'],
                'total' => $marginResult['total'],
                'order_product_status' => $product['order_product_status'],
                'tag' => $tags,
                'service_fee_per' => $marginResult['serviceFee'],
                'freight' => $marginResult['freight'],
                'amount_price_per' => $this->currency->formatCurrencyPrice(-$product['amount_price_per'], $order_info['currency_code'], $order_info['currency_value']),
                'amount_service_fee_per' => $this->currency->formatCurrencyPrice(-$product['amount_service_fee_per'], $order_info['currency_code'], $order_info['currency_value']),
                // add by wjx
                // 头款单价
                'advance_unit_price' => $this->currency->formatCurrencyPrice(
                    $marginResult['advance_unit_price'],
                    $order_info['currency_code'],
                    $order_info['currency_value']
                ),
                // 尾款单价
                'rest_unit_price' => $this->currency->formatCurrencyPrice(
                    $marginResult['rest_unit_price'],
                    $order_info['currency_code'],
                    $order_info['currency_value']
                ),
                // 物流费单价
                'freight_unit_price' => $this->currency->formatCurrencyPrice(
                    $marginResult['freight_unit_price'],
                    $order_info['currency_code'],
                    $order_info['currency_value']
                ),
                // 服务费单价
                'poundage_per' => $this->currency->formatCurrencyPrice(
                    $marginResult['poundage_per'],
                    $order_info['currency_code'],
                    $order_info['currency_value']
                ),
                // 总计 ps 只有已完成的销售订单才可以返还定金
                'total_price' => $this->currency->formatCurrencyPrice(
                    (
                        $marginResult['freight_unit_price']
                        + $marginResult['rest_unit_price']
                        + ($canReturnDeposit ? $marginResult['advance_unit_price'] : 0)
                        + ($this->customer->isEurope() ? $marginResult['service_fee_per'] : 0)
                    ) * $product['quantity'] - $data['all_campaign_amount'],
                    $order_info['currency_code'],
                    $order_info['currency_value']
                ),
                'total_price_cal' => (
                        $marginResult['freight_unit_price']
                        + $marginResult['rest_unit_price']
                        + ($canReturnDeposit ? $marginResult['advance_unit_price'] : 0)
                        + ($this->customer->isEurope() ? $marginResult['service_fee_per'] : 0)
                    ) * $product['quantity'] - $data['all_campaign_amount'],
                'sub_total' => ($marginResult['rest_unit_price']
                        + ($canReturnDeposit ? $marginResult['advance_unit_price'] : 0)
                        + ($this->customer->isEurope() ? $marginResult['service_fee_per'] : 0)
                    ) * $product['quantity'],
                'service_fee_total' => ($this->customer->isEurope() ? $marginResult['service_fee_per'] : 0) * $product['quantity'],
                'freight_total' => ($marginResult['freight_unit_price']) * $product['quantity'],
            ];
        }

        $data['totals'][] = array(
            'title' => 'Sub-Total',
            //'title' => $isEuropeCountry ? "<img data-toggle='tooltip'  style='padding-right: 5px;width: 26px' src=" . $data['img_vat_url'] . " />" . 'Sub-Total' : 'Sub-Total',
            'text' => $this->currency->formatCurrencyPrice($data['products'][0]['sub_total'], $this->session->get('currency')),
        );
        if ($data['products'][0]['service_fee_total'] != 0) {
            $data['totals'][] = array(
                'title' => 'Total Service Fee',
                'text' => $this->currency->formatCurrencyPrice($data['products'][0]['service_fee_total'], $this->session->get('currency')),
            );
        }
        if ($data['products'][0]['freight_total'] != 0) {
            $data['totals'][] = array(
                'title' => 'Freight',
                'text' => $this->currency->formatCurrencyPrice($data['products'][0]['freight_total'], $this->session->get('currency')),
            );
        }
        if ($data['all_campaign_amount'] > 0) {
            $data['totals'][] = array(
                'title' => 'Promotion Discount',
                'text' => '-' . $this->currency->formatCurrencyPrice($data['all_campaign_amount'], $this->session->get('currency'))
            );
        }
        $tempTotal = $data['products'][0]['total_price_cal']; //原总金额-活动金额
        $total = [
            'title' => 'Order Total',
            'text' => $this->currency->formatCurrencyPrice($tempTotal, $this->session->get('currency'))
        ];
        if ($data['all_coupon_amount'] > 0) {
            $total['giga_coupon_show'] = $this->currency->formatCurrencyPrice($data['all_coupon_amount'], $this->session->get('currency'));
            $total['buyer_paid_show'] = $this->currency->formatCurrencyPrice($tempTotal - $data['all_coupon_amount'], $this->session->get('currency'));
        }
        $data['totals'][] = $total;

        $this->load->model('mp_localisation/order_status');
        $data['order_statuses'] = $this->model_mp_localisation_order_status->getOrderStatuses();
        $data['marginOrderInfoFlag'] = true;
        if (isset($rmaInfos[0]['buyer_id'])) {
            $this->load->model('account/customer');
            $buyer_info = $this->model_account_customer->getCustomer($rmaInfos[0]['buyer_id']);
            $buyer_name = $buyer_info['nickname'] . '(' . $buyer_info['user_number'] . ')';
            if (isset($buyer_name)) {
                $data['buyer_id'] = $rmaInfos[0]['buyer_id'];
                $data['buyer_name'] = $buyer_name;
                $this->load->language('account/customerpartner/rma_management');
                $data['tip_home_pickup_logo'] = $this->language->get('tip_home_pickup_logo');
                $data['tip_drop_shipping_logo'] = $this->language->get('tip_drop_shipping_logo');
                $data['is_home_pickup'] = in_array($buyer_info['customer_group_id'], COLLECTION_FROM_DOMICILE);
                $data['ex_vat'] = VATToolTipWidget::widget(['customer' => Customer::query()->find($rmaInfos[0]['buyer_id']), 'is_show_vat' => true])->render();
            }
        }
        if (strtoupper($rmaInfos[0]['orders_from']) == 'AMAZON') {
            $data['isAmazon'] = 1;
        } else {
            $data['isAmazon'] = 0;
        }
        $data['rmaInfos'] = $rmaInfos;
        $data['orderType'] = $rmaInfos[0]['order_type'];
        $rmaComments = $this->model_customerpartner_rma_management->getRmaComments($ramId, 1);
        if (isset($rmaComments['comments'])) {
            $data['rmaComments'] = $rmaComments['comments'];
        }
        $rmaAttachments = $this->model_customerpartner_rma_management->getRmaAttchments($ramId, 1);
        $data['rmaAttachments'] = array();
        foreach ($rmaAttachments as $rmaAttachment) {
            $data['rmaAttachments'][] = ['file_path' => StorageCloud::rmaFile()->getUrl($rmaAttachment['file_path'])];
        }
        $sellerRmaImages = $this->model_customerpartner_rma_management->getRmaAttchments($ramId, 2);
        $data['sellerRmaImages'] = array();
        foreach ($sellerRmaImages as $sellerRmaImage) {
            $data['sellerRmaImages'][] = ['file_path' => StorageCloud::rmaFile()->getUrl($sellerRmaImage['file_path'])];
        }
        $rmaReshipments = $this->model_customerpartner_rma_management->getRmaReshipments($ramId);
        if (count($rmaReshipments) == 0) {
            $data['rmaReshipmentsArray'][0] = array('status_reshipment' => 0);
        } else {
            $data['rmaReshipmentsArray'] = $rmaReshipments;
        }
        $rmaRefund = $this->model_customerpartner_rma_management->getRmaRefound($ramId);
        $data['rmaRefund'] = $this->currency->format(
            round($rmaRefund['apply_refund_amount'], 2),
            $order_info['currency_code'],
            $order_info['currency_value']
        );
        $data['originRmaRefund'] = round($rmaRefund['apply_refund_amount'], 2);
        $symbolLeft = $this->currency->getSymbolLeft($order_info['currency_code']);
        $symbolRight = $this->currency->getSymbolRight($order_info['currency_code']);
        $data['currency'] = $symbolLeft . $symbolRight;
        $data['status_refund'] = $rmaRefund['status_refund'];
        $data['seller_refund_comments'] = $rmaRefund['seller_refund_comments'];
        $data['agree_refund_money'] = $this->formatPrice($rmaRefund['actual_refund_amount']);

        $country = session('country');
        $data['country'] = $country;

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_seller_center'),
            'href' => $this->url->link('customerpartner/seller_center/index', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => "RMA Management",
            'href' => $this->url->link('account/customerpartner/rma_management', $url, true)
        );
        $data['breadcrumbs'][] = [
            'text' => 'RMA Details',
            'href' => 'javascript:void(0)',
        ];

        if (!isset($this->request->get['review_id'])) {
            $data['action'] = $this->url->link('account/customerpartner/rma_management/add', $url, true);
        } else {
            $data['action'] = $this->url->link('account/customerpartner/rma_management/edit', '&review_id=' . $this->request->get['review_id'] . $url, true);
        }
        //rma_history
        $this->load->model('account/order');
        $this->load->language('account/return');
        $rma_history = $this->model_account_order->getRmaHistories($order_id, $orderInfo['product_id']);
        // N-624需求 排除掉rma history自身的记录
        $data['rmaHistories'] = array_filter($rma_history, function ($item) use ($ramId) {
            return ($item['id'] != $ramId);
        });
        $data['cancel'] = $this->url->link('account/customerpartner/rma_management', $url, true);
        // add column info
        $this->template($data);
        if (!empty($data['order_id'])) {
            $data['order_list_size'] = sizeof($data['order_id']);
            foreach ($data['order_id'] as $loop_order_id) {
                if (isset($bx_id) && $bx_id == $customer_id && $loop_order_id != $order_id) {
                    $data['order_list'][] = [
                        'order_id' => $loop_order_id
                    ];
                } else {
                    $data['order_list'][] = [
                        'order_id' => $loop_order_id,
                        'order_link' => $this->url->link('account/customerpartner/orderinfo', '&order_id=' . $loop_order_id, true)
                    ];
                }
            }
        }
        // 获取trackingNumber
        $data['trackingNumber'] = $this->getTrackingNumberByRmaID($ramId);
        //用户画像seller_margin_rma_form
        $data['styles'][] = 'catalog/view/javascript/layer/theme/default/layer.css';
        $data['scripts'][] = 'catalog/view/javascript/layer/layer.js';
        $data['styles'][] = 'catalog/view/javascript/user_portrait/user_portrait.css?v=' . APP_VERSION;
        $data['scripts'][] = 'catalog/view/javascript/user_portrait/user_portrait.js?v=' . APP_VERSION;
        $data['rma_message'] = $this->load->controller('account/customerpartner/rma_management/rma_message', ['rma_no' => $rmaInfo['rma_order_id'], 'ticket' => 1]);
        if (isset($customerOrder['order_status']) && $customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
            $data['safeguard_bill_list'] = app(SafeguardBillRepository::class)->getSafeguardBillsBySaleId($customerOrder['id'], SafeguardBillStatus::ACTIVE);
        }

        $this->response->setOutput($this->load->view('account/customerpartner/seller_futures_rma_form', $data));
    }

    public function agreeReshipment()
    {
        $rmaId = request('rmaId');
        if (!$this->checkAgreeReshipmentHasBeenProcessed((int)$rmaId)) {
            $data['error_warning'] = 'Failed! Rma has been processed!';
            session()->set('rma_warning', $data['error_warning']);
            goto end;
        }
        // model
        $this->load->model('customerpartner/rma_management');
        $this->load->model('common/product');
        $this->load->model('catalog/margin_product_lock');
        $this->load->model('account/notification');
        $this->load->model('catalog/product');
        $this->load->model('account/wishlist');
        // request
        $comments = request('comments');
        $index = request('countArray');
        // 校验合法性
        $mpnArray = [];
        foreach (explode(",", $index) as $i) {
            $rmaQty = (int)request('rmaQty' . $i);
            $reshipmentType = request('reshipmentType' . $i);
            $reshipmentMpn = request('reshipmentMpn' . $i);
            if (array_key_exists($reshipmentMpn, $mpnArray)) {
                $data['error_warning'] = 'Failed! Reshipped MPN repeat!';
                session()->set('rma_warning', $data['error_warning']);
                goto end;
            }
            //检验填写的Mpn,重发库存
            $reProduct = Product::query()->alias('p')
                ->select('p.*')
                ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
                ->whereIn('p.product_type', [0, 3])
                ->where([
                    'ctp.customer_id' => customer()->getId(),
                    'p.mpn' => $this->db->escape($reshipmentMpn),
                    'p.is_deleted' => 0,
                    'p.status' => 1
                ])
                ->first();
            if ($reProduct) {
                // 校验库存
                if (!$this->model_common_product->checkProductQtyIsAvailable((int)$reProduct->product_id, $rmaQty)) {
                    $data['error_warning'] = 'Failed! Reshipped mpn : ' . $reshipmentMpn . ' quantity cannot be greater than the difference value between in stock quantity and locked quantity.';
                    session()->set('rma_warning', $data['error_warning']);
                    goto end;
                } else if (($reProduct->part_flag) != ($reshipmentType - 1)) {
                    $data['error_warning'] = 'Failed! Reshipped MPN :' . $reshipmentMpn . ' And Reshipment Type don\'t match !';
                    session()->set('rma_warning', $data['error_warning']);
                    goto end;
                }
            } else {
                $data['error_warning'] = 'Failed! Reshipped MPN not exist!';
                session()->set('rma_warning', $data['error_warning']);
                goto end;
            }
            // 缓存结果
            $mpnArray[$reshipmentMpn] = $reProduct;
        }
        try {
            $this->db->beginTransaction(); // 开启事务
            //更新reshipmemnts记录状态同意
            $this->model_customerpartner_rma_management->updateReshipmentInfo($rmaId, $comments);
            //获取初始reorder_line数据
            $lineData = $this->model_customerpartner_rma_management->getReorderLine($rmaId);
            foreach (explode(",", $index) as $key => $i) {
                $rmaQty = request('rmaQty' . $i);
                $reshipmentType = request('reshipmentType' . $i);
                $reshipmentMpn = request('reshipmentMpn' . $i);
                //获取对应的sku的product_info
                $reProduct = $mpnArray[$reshipmentMpn];
                $rmaLineArray = [
                    "reorder_header_id" => $lineData['reorder_header_id'],
                    "line_item_number" => $key,
                    "product_name" => $this->db->escape($reProduct->description->name),
                    "qty" => $rmaQty,
                    "item_code" => $reProduct->sku,
                    "product_id" => $reProduct->product_id,
                    "image_id" => $lineData['image_id'] == null ? 1 : $lineData['image_id'],
                    "seller_id" => $lineData['seller_id'],
                    "item_status" => 1,
                    'memo' => 'seller修改',
                    "create_user_name" => $lineData['create_user_name'],
                    "create_time" => date("Y-m-d H:i:s", time()),
                    'part_flag' => $reshipmentType,
                    "program_code" => PROGRAM_CODE
                ];
                $this->model_customerpartner_rma_management->addReOrderLine($rmaLineArray);
            }
            //删除初始的重发单
            $this->model_customerpartner_rma_management->deleteReOrderLine($lineData['id']);
            $rmaInfos = $this->model_customerpartner_rma_management->getRmaInfo($rmaId);

            //增加图片保存
            foreach ($rmaInfos as $rmaInfo) {
                //更新reorder的数量以及productId
                $this->model_customerpartner_rma_management->updateReorderLine($rmaInfo['id'], $rmaInfo['quantity'], $rmaInfo['product_id'], $rmaId);

                // 3.判断有无上传rma文件
                if ($this->request->filesBag->count() > 0) {
                    // 有文件上传，将文件保存服务器上并插入数据到表oc_yzc_rma_file
                    $files = $this->request->filesBag;
                    // 上传RMA文件，以用户ID进行分类
                    /** @var UploadedFile $file */
                    foreach ($files as $file) {
                        if ($file->isValid()) {
                            // 变更命名规则
                            $filename = date('Ymd') . '_'
                                . md5((html_entity_decode($file->getClientOriginalName(), ENT_QUOTES, 'UTF-8') . micro_time()))
                                . '.' . $file->getClientOriginalExtension();
                            StorageCloud::rmaFile()->writeFile($file, $rmaInfo['seller_id'], $filename);
                            // 插入文件数据
                            $rmaFile = [
                                'rma_id' => $rmaId,
                                'file_name' => $file->getClientOriginalName(),
                                'size' => $file->getSize(),
                                'file_path' => $rmaInfo['seller_id'] . '/' . $filename,
                                'buyer_id' => $rmaInfo['seller_id']
                            ];
                            $this->model_account_rma_management->addRmaFile($rmaFile);
                        }
                    }
                }

                // 定义SysReceive
                $sysReceive = new SysReceive();
                $sysReceive->buyer_id = $rmaInfo['buyer_id'];
                $sysReceive->source_header_id = $rmaId;
                $sysReceive->transaction_type = 1;
                $sysReceive->create_user_name = $rmaInfo['buyer_id'];
                $sysReceive->program_code = PROGRAM_CODE;
                $sysReceive->line_count = 1;
                $sysReceive->type = 2;
                $sysReceiveLineArr = array();

                $sysReceiveLine = new SysReceiveLine();
                $sysReceiveLine->buyer_id = $rmaInfo['buyer_id'];
                $sysReceiveLine->rma_id = $rmaId;
                $sysReceiveLine->rma_product_id = $rmaInfo['rma_product_id'];
                $sysReceiveLine->transaction_type = 1;
                $sysReceiveLine->product_id = $rmaInfo['product_id'];
                $sysReceiveLine->receive_qty = $rmaInfo['quantity'];
                $sysReceiveLine->unit_price = 0;
                $sysReceiveLine->seller_id = $this->customer->getId();
                $sysReceiveLine->create_user_name = $rmaInfo['buyer_id'];
                $sysReceiveLine->program_code = PROGRAM_CODE;
                $sysReceiveLineArr[] = $sysReceiveLine;

                $sysReceive->sub_total = 0;
                $sysReceive->total = 0;
                // 插入sysReceive
                $receiveId = $this->saveReceive($sysReceive);
                foreach ($sysReceiveLineArr as $sysReceiveLine) {
                    $sysReceiveLine->receive_id = $receiveId;
                    $receiveLineId = $this->saveReceiveLine($sysReceiveLine);
                    // 收货记录
                    $sysCostDetail = new SysCostDetail();
                    $sysCostDetail->buyer_id = $sysReceiveLine->buyer_id;
                    $sysCostDetail->source_line_id = $receiveLineId;
                    $sysCostDetail->source_code = "NUS";
                    $sysCostDetail->sku_id = $sysReceiveLine->product_id;
                    $sysCostDetail->onhand_qty = $sysReceiveLine->receive_qty;
                    $sysCostDetail->original_qty = $sysReceiveLine->receive_qty;
                    $sysCostDetail->seller_id = $sysReceiveLine->seller_id;
                    $sysCostDetail->create_user_name = $sysReceiveLine->create_user_name;
                    $sysCostDetail->program_code = PROGRAM_CODE;
                    $sysCostDetail->type = 2;
                    $sysCostDetail->rma_id = $rmaId;
                    $this->saveCostDetail($sysCostDetail);
                }
                // seller库存扣减 增加出库记录
                if ($rmaInfo['apply_product_id'] != $rmaInfo['product_id']) {
                    $setProductInfoArr = $this->db->query("select op.product_id,psi.qty from tb_sys_product_set_info psi LEFT JOIN oc_product op ON op.product_id = psi.set_product_id LEFT JOIN oc_customerpartner_to_product ctp ON ctp.product_id = op.product_id where ctp.customer_id = " . $this->customer->getId() . " and psi.product_id=" . $rmaInfo['product_id'])->rows;
                } else {
                    $setProductInfoArr = $this->db->query("select op.product_id,tsoc.qty from tb_sys_order_combo tsoc LEFT JOIN oc_product op on op.product_id = tsoc.set_product_id  where tsoc.product_id=" . $rmaInfo['product_id'] . " and tsoc.order_id = " . $rmaInfo['order_id'])->rows;
                }
                $this->updateAndSaveSellerBatch($rmaId, $rmaInfo, $setProductInfoArr);
                // 上架库存扣减
                $this->model_common_product->updateProductOnShelfQuantity($rmaInfo['product_id']);
            }
            // 变更退返品seller状态
            $this->model_customerpartner_rma_management->updateRmaSellerStatus(2, $rmaId);
            // 销售单rma通知
            $this->rmaCommunication($rmaId);
            $this->db->commit();
            session()->set('success', 'Success: Rma success!');
        } catch (Exception $e) {
            $this->db->rollback(); // 执行失败，事务回滚
            session()->set('rma_warning', 'Fail: Rma failed!');
            Logger::error($e);
        }
        end:
        return $this->response->redirectTo(url('account/customerpartner/rma_management/rmaInfo&rmaId=' . $rmaId));
    }

    private function checkAgreeReshipmentHasBeenProcessed(int $rma_id): bool
    {
        $ret = db('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->whereIn('rop.rma_type', [1, 3])
            ->where([
                'ro.cancel_rma' => 0,
                'rop.status_reshipment' => 0,
                'ro.id' => $rma_id,
            ])
            ->first();
        return (bool)$ret;
    }

    public function rejectReshipment()
    {
        $rejectComments = request('rejectReshipmentComments', '');
        $rmaId = request('rmaId');
        if (!$this->checkAgreeReshipmentHasBeenProcessed((int)$rmaId)) {
            $data['error_warning'] = 'Failed! Rma has been processed!';
            session()->set('rma_warning', $data['error_warning']);
            goto end;
        }
        $this->load->model('customerpartner/rma_management');
        $this->model_customerpartner_rma_management->rejectReshipment($rmaId, $rejectComments);
        //变更退返品seller状态
        $this->model_customerpartner_rma_management->updateRmaSellerStatus(2, $rmaId);

        $rma_order_type = $this->model_customerpartner_rma_management->getRmaOrderType($rmaId);
        //目前此处的退返品订单类型，除了agreeRefund()方法外，都是为了应用于站内信的显示内容格式区分的扩充
        $order_type = 1;
        if (isset($rma_order_type)) {
            $type_map = current($rma_order_type);
            $order_type = $type_map['order_type'];
        }
        if ($order_type == 1) {
            $this->rmaCommunication($rmaId);
        } else {
            $this->rmaCommunicationPurchase($rmaId);
        }
        end:
        return $this->response->redirectTo(url('account/customerpartner/rma_management/rmaInfo&rmaId=' . $rmaId));
    }

    public function rejectRefund()
    {
        $refund_reject_comments = request('refund_reject_comments', '');
        $rmaId = request('rmaId');
        if (!$this->checkRefundHasBeenProcessed((int)$rmaId)) {
            $data['error_warning'] = 'Failed! Rma has been processed!';
            session()->set('rma_warning', $data['error_warning']);
            goto end;
        }
        $this->load->model('customerpartner/rma_management');
        $this->model_customerpartner_rma_management->rejectRefund($rmaId, $refund_reject_comments);
        //变更退返品seller状态
        $this->model_customerpartner_rma_management->updateRmaSellerStatus(2, $rmaId);

        $rma_order_type = $this->model_customerpartner_rma_management->getRmaOrderType($rmaId);
        //目前此处的退返品订单类型，除了agreeRefund()方法外，都是为了应用于站内信的显示内容格式区分的扩充
        $order_type = 1;
        if (isset($rma_order_type)) {
            $type_map = current($rma_order_type);
            $order_type = $type_map['order_type'];
        }
        if ($order_type == 1) {
            $this->rmaCommunication($rmaId);
        } else {
            $this->rmaCommunicationPurchase($rmaId);
        }
        end:
        return $this->response->redirectTo(url('account/customerpartner/rma_management/rmaInfo&rmaId=' . $rmaId));
    }

    // 同意退款
    public function agreeRefund()
    {
        $refund_agree_comments = $this->request->request['refund_agree_comments'];
        $refundMoney = trim($this->request->request['refundMoney']);
        $takeCoupon = $this->request->post('take_coupon'); //优惠券金额
        $rmaId = $this->request->request['rmaId'];
        if (!$this->checkRefundHasBeenProcessed((int)$rmaId)) {
            $data['error_warning'] = 'Failed! Rma has been processed!';
            session()->set('rma_warning', $data['error_warning']);
            goto end;
        }
        if ($takeCoupon > 0) {
            if ($refundMoney <= $takeCoupon) { //如果有优惠券,那么输入的金额不能比优惠券小
                $data['error_warning'] = 'The amount entered must be more than the coupon value.';
                $this->session->set('rma_warning', $data['error_warning']);
                goto end;
            }
        }
        $order_type = $this->request->request['order_type'];
        $this->load->model('customerpartner/rma_management');
        //校验返金金额
        $maxRefundMoney = $this->getMaxRefundMoney($rmaId, $order_type);
        $rebateRmaInfo = $this->model_customerpartner_rma_management->getRebateRmaInfo($rmaId);
        /*
         * Buyer发起的退款（RMA-Refund）是针对采购订单或Canceled的销售订单的
         * 申请退款的产品参与过/正在参与返点，且计入了返点数量的
         */
        if (
            $rebateRmaInfo['order_type'] == RmaType::PURCHASE_ORDER  // 采购单rma
            || (
                $rebateRmaInfo['order_status'] == CustomerSalesOrderStatus::CANCELED  // 取消的销售单且为第一次申请rma
                && app(RamRepository::class)->checkSalesOrderRmaFirstRefund($rmaId)
            )
        ) {
            // 返点情况
            $rebateInfo = $this->model_customerpartner_rma_management->getRebateInfo($rebateRmaInfo['order_id'], $rebateRmaInfo['product_id']);
            if (!empty($rebateInfo)) {
                $rebateRefundInfo =
                    app(RebateRepository::class)
                        ->checkRebateRefundMoney(
                            $maxRefundMoney, $rebateRmaInfo['rmaQty'],
                            $rebateRmaInfo['order_id'], $rebateRmaInfo['product_id'],
                            $rebateRmaInfo['order_type'] == RmaType::PURCHASE_ORDER
                        );
                $refundRange = $rebateRefundInfo['refundRange'] ?? [];
                $maxRefundMoney = $refundRange[$rebateRmaInfo['rmaQty']] ?? $maxRefundMoney;
            }
        }

        $rmaInfo = YzcRmaOrder::query()->with('yzcRmaOrderProduct')->find($rmaId);
        if ($rmaInfo->yzcRmaOrderProduct->campaign_amount > 0) {
            $maxRefundMoney = $maxRefundMoney - $rmaInfo->yzcRmaOrderProduct->campaign_amount; //先减去活动的
        }
        $maxRefundMoney = $maxRefundMoney - $takeCoupon;
        if ($takeCoupon > 0) {
            $refundMoney = bcsub($refundMoney, $takeCoupon, 2);
        }
        if (bccomp($refundMoney, $maxRefundMoney, 2) === 1) {
            $this->log->write('----------------------------------RMA KIMI---------------------------');
            $this->log->write('Customer id: ' . $this->customer->getId());
            $this->log->write('Max refund money: ' . (string)$maxRefundMoney);
            $this->log->write($_REQUEST);
            $this->log->write('----------------------------------RMA KIMI END---------------------------');
            $data['error_warning'] = 'Failed: The refund cannot exceed the full original payment for the product!';
            session()->set('rma_warning', $data['error_warning']);
            goto end;
        }
        try {
            $this->db->beginTransaction(); // 开启事务
            // 先释放正在进行中的现货协议的尾款仓租
            $unbindMarginRes = app(StorageFeeService::class)->unbindMarginRestStorageFee($rmaInfo);
            $needStorageFee = false;
            if (!$unbindMarginRes) {
                // 检查是否需要仓租 必须先放在这里进行判断
                $needStorageFee = app(RamRepository::class)->checkRmaNeedReturnStorageFee($rmaId);
            }
            //执行退款 更新RMA信息
            $this->model_customerpartner_rma_management->refund($rmaId, $refundMoney, $refund_agree_comments);
            //变更退返品seller状态
            $this->model_customerpartner_rma_management->updateRmaSellerStatus(2, $rmaId);
            if ($order_type == 1) {
                //销售订单退货
                //如果为cancel订单的退反品，删除buyer库存，buyer新增出库记录，seller新增入库数据
                $order_result = $this->model_customerpartner_rma_management->getRmaFromOrderInfo($rmaId);
                if (
                    isset($order_result['order_status'])
                    && $order_result['order_status'] == CustomerSalesOrderStatus::CANCELED
                ) {
                    $this->model_customerpartner_rma_management->cancelOrderRma(
                        $rmaId, $order_result['qty'], $order_result['order_id']
                    );
                    // cancel订单退返品 如果为保证金业务 可能需要返还库存
                    if (!$this->model_customerpartner_rma_management->checkOrderRmaIsRefund(
                        $rmaId, $order_result['order_id']
                    )) {
                        $this->resolveMarginRmaRefund($rmaId, $order_result['qty']);
                    }
                }
                $this->rmaCommunication($rmaId);
            } else {
                //采购订单退货
                $this->model_customerpartner_rma_management->purchaseOrderRma($rmaId);
                // 采购订单返金 如果为保证金业务 可能需要返还库存
                $qty = YzcRmaOrder::query()->find($rmaId)->yzcRmaOrderProduct->quantity;
                $this->resolveMarginRmaRefund($rmaId, (int)$qty);
                $this->rmaCommunicationPurchase($rmaId);
            }
            // 费用单申请
            if ($needStorageFee) {
                app(RmaService::class)->applyStorageFee($rmaId);
            }
            session()->set('success', 'Success: Rma success!');
            //优惠券
            app(PlatformBillService::class)->backToPlatFormBill($rmaId);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback(); // 执行失败，事务回滚
            $this->session->remove('success');
            session()->set('rma_warning', $e->getMessage());
            Logger::error($e);
        }
        end:
        $this->response->redirect($this->url->link('account/customerpartner/rma_management/rmaInfo&rmaId=' . $rmaId, true));
    }

    private function checkRefundHasBeenProcessed(int $rma_id): bool
    {
        $ret = $this->orm->table('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->whereIn('rop.rma_type', [2, 3])
            ->where([
                'ro.cancel_rma' => 0,
                'rop.status_refund' => 0,
                'ro.id' => $rma_id,
            ])
            ->first();
        return (bool)$ret;
    }

    //用于rma超时的定时任务调用的退款接口
    public function agreeRefundApi()
    {
        $customer_id = $this->request->request['customer_id'];
        if ($this->request->request['secret'] = 'b2b@pass' && $customer_id) {
            $this->customer->setId($customer_id);
            $this->agreeRefund();
        }
    }

    public function autocomplete()
    {
        $json = array();

        if (isset($this->request->get['filter_reshipmentMpn'])) {
            $reshipmentType = $this->request->get['reshipmentType'];
            $filter_reshipmentMpn = $this->request->get['filter_reshipmentMpn'];

            if ($filter_reshipmentMpn != '') {
                $this->load->model('customerpartner/rma_management');
                $customerId = $this->customer->getId();
                $filter_data = array(
                    'filter_reshipmentMpn' => $filter_reshipmentMpn,
                    'filter_reshipmentType' => $reshipmentType,
                    'customer_id' => $customerId,
                    'start' => 0,
                    'limit' => 10
                );

                $results = $this->model_customerpartner_rma_management->checkMpn($filter_data);

                foreach ($results as $result) {
                    if ($result['combo_flag'] == 1) {
                        $json[] = array(
                            'sku' => $result['sku'],
                            'mpn' => $result['mpn'],
                            'product_id' => $result['product_id'],
                        );
                        // 获取combo品对应的子sku
                        $setResults = $this->model_customerpartner_rma_management->getSetComboInfo($result['product_id']);
                        foreach ($setResults as $setResult) {
                            $json[] = array(
                                'sku' => $setResult['sku'],
                                'mpn' => $setResult['mpn'],
                                'product_id' => $setResult['product_id'],
                                'set_sku' => 1
                            );
                        }
                    } else {
                        $json[] = array(
                            'sku' => $result['sku'],
                            'mpn' => $result['mpn'],
                            'product_id' => $result['product_id']
                        );
                    }
                }
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function saveReceive($sysReceive)
    {
        $sql = "INSERT INTO `tb_sys_receive` (buyer_id, source_header_id, transaction_type, sub_total, total, line_count, type, create_user_name, create_time, program_code) VALUES (";
        $sql .= $sysReceive->buyer_id . ",";
        $sql .= $sysReceive->source_header_id . ",";
        $sql .= "'" . $sysReceive->source_header_id . "',";
        $sql .= $sysReceive->sub_total . ",";
        $sql .= $sysReceive->total . ",";
        $sql .= $sysReceive->line_count . ",";
        $sql .= $sysReceive->type . ",";
        $sql .= "'" . $sysReceive->create_user_name . "',";
        $sql .= "NOW(),";
        $sql .= "'" . $sysReceive->program_code . "')";
        $this->db->query($sql);
        return $this->db->getLastId();
    }

    public function saveReceiveLine($sysReceiveLine)
    {
        $sql = "INSERT INTO `tb_sys_receive_line` (receive_id, buyer_id, rma_id, rma_product_id, product_id, receive_qty, unit_price, seller_id, create_user_name, create_time,program_code) VALUES (";
        $sql .= $sysReceiveLine->receive_id . ",";
        $sql .= $sysReceiveLine->buyer_id . ",";
        $sql .= $sysReceiveLine->rma_id . ",";
        $sql .= $sysReceiveLine->rma_product_id . ",";
        $sql .= $sysReceiveLine->product_id . ",";
        $sql .= $sysReceiveLine->receive_qty . ",";
        $sql .= $sysReceiveLine->unit_price . ",";
        $sql .= $sysReceiveLine->seller_id . ",";
        $sql .= "'" . $sysReceiveLine->create_user_name . "',";
        $sql .= "NOW(),";
        $sql .= "'" . $sysReceiveLine->program_code . "')";
        $this->db->query($sql);
        return $this->db->getLastId();
    }

    public function saveCostDetail($sysCostDetail)
    {
        $sql = "INSERT INTO `tb_sys_cost_detail` (buyer_id, source_line_id, source_code, sku_id, onhand_qty, original_qty, seller_id, create_user_name, create_time,type,rma_id,program_code) VALUES (";
        $sql .= $sysCostDetail->buyer_id . ",";
        $sql .= $sysCostDetail->source_line_id . ",";
        $sql .= "'" . $sysCostDetail->source_code . "',";
        $sql .= $sysCostDetail->sku_id . ",";
        $sql .= $sysCostDetail->onhand_qty . ",";
        $sql .= $sysCostDetail->original_qty . ",";
        $sql .= $sysCostDetail->seller_id . ",";
        $sql .= "'" . $sysCostDetail->create_user_name . "',";
        $sql .= "NOW(),";
        $sql .= $sysCostDetail->type . ",";
        $sql .= $sysCostDetail->rma_id . ",";
        $sql .= "'" . $sysCostDetail->program_code . "')";
        $this->db->query($sql);
        return $this->db->getLastId();
    }

    public function updateAndSaveSellerBatch($rmaId, $rmaInfo, $setProductInfoArr)
    {
        $productModel = Product::query()->where('product_id', $rmaInfo['product_id'])->select(['combo_flag', 'danger_flag'])->first();
        $combo_flag = $productModel->combo_flag;
        $dangerFlag = $productModel->danger_flag;
        $buyerQty = $rmaInfo['quantity'];
        if ($combo_flag == 1) {
            $comboProducts = $setProductInfoArr;
            $setProductIdDangerFlagMap = Product::query()->whereIn('product_id', array_column($comboProducts, 'product_id'))->pluck('danger_flag', 'product_id')->toArray();
            foreach ($comboProducts as $comboProduct) {
                $dangerFlag = $setProductIdDangerFlagMap[$comboProduct['product_id']] ?? 0;
                $seller_batchs = $this->db->query("SELECT batch_id,onhand_qty,warehouse,customer_id  from tb_sys_batch  where onhand_qty>0 AND product_id = " . $comboProduct['product_id'])->rows;
                $buyerQty = $buyerQty * $comboProduct['qty'];
                foreach ($seller_batchs as $batch) {
                    if ($buyerQty > $batch['onhand_qty']) {
                        $buyerQty = $buyerQty - $batch['onhand_qty'];
                        $this->db->query("update tb_sys_batch set onhand_qty = 0 where batch_id=" . $batch['batch_id']);
                        $sql = "insert into tb_sys_seller_delivery_line (rma_id,rma_product_id,product_id,batch_id,qty,warehouse,seller_id,buyer_id,CreateUserName,CreateTime,type,danger_flag,ProgramCode) VALUES (";
                        $sql .= $rmaId . ",";
                        $sql .= $rmaInfo['rma_product_id'] . ",";
                        $sql .= $comboProduct['product_id'] . ",";
                        $sql .= $batch['batch_id'] . ",";
                        $sql .= $batch['onhand_qty'] . ",";
                        $sql .= "'" . $batch['warehouse'] . "',";
                        $sql .= $batch['customer_id'] . ",";
                        $sql .= $rmaInfo['buyer_id'] . ",";
                        $sql .= $rmaInfo['buyer_id'] . ",";
                        $sql .= "NOW(),";
                        $sql .= "2,";
                        $sql .= $dangerFlag . ",";
                        $sql .= "'" . PROGRAM_CODE . "')";
                        $this->db->query($sql);
                    } else {
                        $leftQty = $batch['onhand_qty'] - $buyerQty;
                        $this->db->query("update tb_sys_batch set onhand_qty = " . $leftQty . " where batch_id=" . $batch['batch_id']);
                        $sql = "insert into tb_sys_seller_delivery_line (rma_id,rma_product_id,product_id,batch_id,qty,warehouse,seller_id,buyer_id,CreateUserName,CreateTime,type,danger_flag,ProgramCode) VALUES (";
                        $sql .= $rmaId . ",";
                        $sql .= $rmaInfo['rma_product_id'] . ",";
                        $sql .= $comboProduct['product_id'] . ",";
                        $sql .= $batch['batch_id'] . ",";
                        $sql .= $buyerQty . ",";
                        $sql .= "'" . $batch['warehouse'] . "',";
                        $sql .= $batch['customer_id'] . ",";
                        $sql .= $rmaInfo['buyer_id'] . ",";
                        $sql .= $rmaInfo['buyer_id'] . ",";
                        $sql .= "NOW(),";
                        $sql .= "2,";
                        $sql .= $dangerFlag . ",";
                        $sql .= "'" . PROGRAM_CODE . "')";
                        $this->db->query($sql);
                        break;
                    }
                }
            }
        } else {
            $seller_batchs = $this->db->query("SELECT batch_id,onhand_qty,warehouse,customer_id  from tb_sys_batch  where onhand_qty>0 AND product_id = " . $rmaInfo['product_id'])->rows;
            foreach ($seller_batchs as $batch) {
                if ($buyerQty > $batch['onhand_qty']) {
                    $buyerQty = $buyerQty - $batch['onhand_qty'];
                    $this->db->query("update tb_sys_batch set onhand_qty = 0 where batch_id=" . $batch['batch_id']);
                    $sql = "insert into tb_sys_seller_delivery_line (rma_id,rma_product_id,product_id,batch_id,qty,warehouse,seller_id,buyer_id,CreateUserName,CreateTime,type,danger_flag,ProgramCode) VALUES (";
                    $sql .= $rmaId . ",";
                    $sql .= $rmaInfo['rma_product_id'] . ",";
                    $sql .= $rmaInfo['product_id'] . ",";
                    $sql .= $batch['batch_id'] . ",";
                    $sql .= $batch['onhand_qty'] . ",";
                    $sql .= "'" . $batch['warehouse'] . "',";
                    $sql .= $batch['customer_id'] . ",";
                    $sql .= $rmaInfo['buyer_id'] . ",";
                    $sql .= $rmaInfo['buyer_id'] . ",";
                    $sql .= "NOW(),";
                    $sql .= "2,";
                    $sql .= $dangerFlag . ",";
                    $sql .= "'" . PROGRAM_CODE . "')";
                    $this->db->query($sql);
                } else {
                    $leftQty = $batch['onhand_qty'] - $buyerQty;
                    $this->db->query("update tb_sys_batch set onhand_qty = " . $leftQty . " where batch_id=" . $batch['batch_id']);
                    $sql = "insert into tb_sys_seller_delivery_line (rma_id,rma_product_id,product_id,batch_id,qty,warehouse,seller_id,buyer_id,CreateUserName,CreateTime,type,danger_flag,ProgramCode) VALUES (";
                    $sql .= $rmaId . ",";
                    $sql .= $rmaInfo['rma_product_id'] . ",";
                    $sql .= $rmaInfo['product_id'] . ",";
                    $sql .= $batch['batch_id'] . ",";
                    $sql .= $buyerQty . ",";
                    $sql .= "'" . $batch['warehouse'] . "',";
                    $sql .= $batch['customer_id'] . ",";
                    $sql .= $rmaInfo['buyer_id'] . ",";
                    $sql .= $rmaInfo['buyer_id'] . ",";
                    $sql .= "NOW(),";
                    $sql .= "2,";
                    $sql .= $dangerFlag . ",";
                    $sql .= "'" . PROGRAM_CODE . "')";
                    $this->db->query($sql);
                    break;
                }
            }
        }

    }

    private function rmaCommunication($rmaId)
    {
        $this->load->model('account/notification');
        $this->load->model('customerpartner/rma_management');
        $communicationInfo = $this->model_customerpartner_rma_management->getCommunicationInfo($rmaId);
        if ($communicationInfo['seller_status'] == '2') {
            $subject = 'RMA Processed Result (RMA ID:' . $communicationInfo['rma_order_id'] . ')';
            $message = '<table   border="0" cellspacing="0" cellpadding="0" >';
            $message .= '<tr><th align="left">RMA ID:</th><td>' . $communicationInfo['rma_order_id'] . '</td></tr> ';
            $message .= '<tr><th align="left">Order ID:</th><td>' . $communicationInfo['order_id'] . '</td></tr>';
            $message .= '<tr><th align="left">Item Code:</th><td>' . $communicationInfo['sku'] . '</td></tr>';
            if ($communicationInfo['rma_type'] == 1) {
                $message .= '<tr><th align="left">Reshipment Processed Result: </th><td>' . $communicationInfo['status_reshipment'] . '</td></tr>';
            } else if ($communicationInfo['rma_type'] == 2) {
                $message .= '<tr><th align="left">Refund Request Outcome: </th><td>' . $communicationInfo['status_refund'] . '</td></tr>';
            } else if ($communicationInfo['rma_type'] == 3) {
                if ($communicationInfo['status_reshipment'] != '0') {
                    $message .= '<tr><th align="left">Reshipment Processed Result: </th><td>' . $communicationInfo['status_reshipment'] . '</td></tr>';
                }
                if ($communicationInfo['status_refund'] != '0') {
                    $message .= '<tr><th align="left">Refund Request Outcome: </th><td>' . $communicationInfo['status_refund'] . '</td></tr>';
                }
            }
            $message .= '</table>';
//            $this->communication->saveCommunication($subject, $message, $communicationInfo['buyer_id'], $communicationInfo['seller_id'], 0);

            // 新消息中心
            $this->load->model('message/message');
            $this->model_message_message->addSystemMessageToBuyer('rma', $subject, $message, $communicationInfo['buyer_id']);

        }
    }

    private function rmaCommunicationPurchase($rmaId)
    {
        $this->load->model('account/notification');
        $this->load->model('customerpartner/rma_management');
        $communicationInfo = $this->model_customerpartner_rma_management->getCommunicationInfo($rmaId);
        if ($communicationInfo['seller_status'] == '2') {
            $subject = 'Purchase Order RMA Status Outcome (RMA ID:' . $communicationInfo['rma_order_id'] . ')';
            $message = '<table   border="0" cellspacing="0" cellpadding="0" >';
            $message .= '<tr><th align="left">RMA ID:</th><td>' . $communicationInfo['rma_order_id'] . '</td></tr> ';
            $message .= '<tr><th align="left">Order ID:</th><td>' . $communicationInfo['order_id'] . '</td></tr>';
            $message .= '<tr><th align="left">Item Code:</th><td>' . $communicationInfo['sku'] . '</td></tr>';
            if ($communicationInfo['rma_type'] == 1) {
                $message .= '<tr><th align="left">Reshipment Processed Result：</th><td>' . $communicationInfo['status_reshipment'] . '</td></tr>';
            } else if ($communicationInfo['rma_type'] == 2) {
                $message .= '<tr><th align="left">Refund Request Outcome：</th><td>' . $communicationInfo['status_refund'] . '</td></tr>';
            } else if ($communicationInfo['rma_type'] == 3) {
                if ($communicationInfo['status_reshipment'] != '0') {
                    $message .= '<tr><th align="left">Reshipment Processed Result：</th><td>' . $communicationInfo['status_reshipment'] . '</td></tr>';
                }
                if ($communicationInfo['status_refund'] != '0') {
                    $message .= '<tr><th align="left">Refund Request Outcome：</th><td>' . $communicationInfo['status_refund'] . '</td></tr>';
                }
            }
            $message .= '</table>';

//            $this->communication->saveCommunication($subject, $message, $communicationInfo['buyer_id'], $communicationInfo['seller_id'], 0);
            // 新消息中心
            $this->load->model('message/message');
            $this->model_message_message->addSystemMessageToBuyer('rma', $subject, $message, $communicationInfo['buyer_id']);
        }
    }

    /**
     * 获取销售订单tracking number
     * @param int $rmaID
     * @return string|null
     */
    private function getTrackingNumberByRmaID(int $rmaID)
    {
        $rma_info = $this->orm->table('oc_yzc_rma_order as ro')
            ->join('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->leftJoin(
                'tb_sys_customer_sales_order as cso',
                'cso.order_id', '=', 'ro.from_customer_order_id'
            )
            ->where('ro.id', $rmaID)
            ->select(['rop.product_id'])
            ->selectRaw('cso.id as sales_order_id')
            ->first();
        if (!$rma_info) return null;
        $ret = [];
        $this->load->model('account/customer_order_import');
        $track_info = $this->model_account_customer_order_import->getTrackingNumberInfoByOrderParam([$rma_info->sales_order_id]);
        foreach ($track_info as $item) {
            $product_is_exist = $this->orm
                ->table('oc_product')
                ->where([
                    'product_id' => $rma_info->product_id,
                    'sku' => $item['sku']
                ])
                ->exists();
            if ($product_is_exist && !empty($item['tracking_number'])) {
                $ret = array_merge($ret, $item['tracking_number']);
            }
        };
        return !empty($ret) ? join(',', $ret) : null;
    }

    /**
     * @return string
     * user：wangjinxin
     * date：2020/3/25 11:53
     */
    private function resolveRequestUrl()
    {
        $url = '';
        if (isset($this->request->get['filter_product'])) {
            $url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
        }
        if (isset($this->request->get['filter_author'])) {
            $url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
        }
        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }
        if (isset($this->request->get['filter_date_added'])) {
            $url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
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
        return $url;
    }

    /**
     * user：wangjinxin
     * date：2020/3/25 13:15
     */
    private function removeSession(&$data)
    {
        if (isset($this->session->data['success'])) {
            $data['success'] = session('success');
            $this->session->remove('success');
        }
        if (isset($this->session->data['rma_warning'])) {
            $data['rma_warning'] = session('rma_warning');
            $this->session->remove('rma_warning');
        }
    }

    private function template(&$data)
    {
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
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
    }

    /**
     * rma 退货退款时候可能需要变动上架库存 或者 锁定库存
     * @param int $rmaId rma id
     * @param int $qty 退货数量
     * @throws Exception
     */
    private function resolveMarginRmaRefund(int $rmaId, int $qty)
    {
        $this->load->model('customerpartner/rma_management');
        $agree_info = $this->model_customerpartner_rma_management->getAgreeInfoByRmaId($rmaId);
        if ($qty <= 0 || !$agree_info) return;
        // 判断是不是旧的保证金业务
        if ($agree_info['product_id'] != $agree_info['rest_product_id']) return;
        // 现货保证金
        if ($this->model_customerpartner_rma_management->checkIsMarginRma($rmaId)) {
            $this->load->model('catalog/margin_product_lock');
            $this->model_catalog_margin_product_lock->TailIn($agree_info['id'], $qty, $rmaId, 2);
        }
//        // 期货保证金
        if ($this->model_customerpartner_rma_management->checkIsFuturesRma($rmaId) && $agree_info['version'] < FuturesVersion::VERSION) {
            $this->load->model('catalog/futures_product_lock');
            $this->model_catalog_futures_product_lock->TailIn($agree_info['id'], $qty, $rmaId, 2);
        }
    }

    /**
     * 商品库存调整
     * @param int $product_id 商品id
     * @param int $quantity 减少的数量
     * @throws Exception
     */
    private function resolveProductQuantity(int $product_id, int $quantity)
    {
        $this->load->model('common/product');;
        $this->load->model('catalog/margin_product_lock');
        // 在库库存
        $in_stock_qty = $this->model_common_product->getProductInStockQuantity($product_id);
        // 锁定库存
        $lock_qty = (int)$this->model_common_product->getProductComputeLockQty($product_id);
        // 修改 oc_product 上架库存
        $p_on_shelf_qty = (int)$this->orm
            ->table('oc_product')
            ->where('product_id', $product_id)
            ->value('quantity');
        // （在库库存 - 锁定库存 - 要减少的库存） 和 目前的上架库存比较 去最小值
        $stock_after = min($in_stock_qty - $lock_qty - $quantity, $p_on_shelf_qty);
        if ($stock_after < 0) {
            throw new  Exception(
                "Reshipped product: {$product_id} quantity: {$quantity} " .
                "Failed! Reshipped quantity cannot be greater than the difference value between in stock quantity and locked quantity."
            );
        }
        $this->orm
            ->table('oc_product')
            ->where(['product_id' => $product_id, 'quantity' => $p_on_shelf_qty])
            ->update(['quantity' => $stock_after]);
        // 修改 oc_customerpartner_to_product 上架库存
        $ctp_on_shelf_qty = (int)$this->orm
            ->table('oc_customerpartner_to_product')
            ->where('product_id', $product_id)
            ->value('quantity');
        // （在库库存 - 锁定库存 - 要减少的库存） 和 目前的上架库存比较 去最小值
        $stock_after = min($in_stock_qty - $lock_qty - $quantity, $ctp_on_shelf_qty);
        if ($stock_after < 0) {
            throw new  Exception(
                "Reshipped product: {$product_id} quantity: {$quantity} " .
                "Failed! Reshipped quantity cannot be greater than the difference value between in stock quantity and locked quantity."
            );
        }
        $this->orm
            ->table('oc_customerpartner_to_product')
            ->where(['product_id' => $product_id, 'quantity' => $ctp_on_shelf_qty])
            ->update(['quantity' => $stock_after]);
    }

    /**
     * 获取最大返金金额
     * @param $rmaId
     * @param int $order_type
     * @return float|int|mixed
     * @throws Exception
     */
    private function getMaxRefundMoney($rmaId, $order_type)
    {
        $this->load->model('customerpartner/rma_management');
        $this->load->model('account/rma_management');
        $this->load->model('account/customerpartner/futures_order');
        $this->load->model('account/customerpartner/margin_order');
        $maxRefundMoney = 0;
        if ($order_type == 1) {
            //保证金的返金金额 complete:返金金额为（保证金头款+保证金尾款）*qty,cancel:返金金额为（保证金尾款）*qty
            $order_result = $this->model_customerpartner_rma_management->getRmaFromOrderInfo($rmaId);
            $line_info = $this->model_customerpartner_rma_management->getSellerOrderProductInfoIncludeSaleLine($rmaId);
            $order_line_info = $this->model_customerpartner_rma_management->getOrderLineInfo(
                $order_result['order_id'], $order_result['product_id']
            );
            if ($order_result && $line_info && $order_result['order_status']) {
                $is_margin = $this->model_customerpartner_rma_management->checkIsMarginRma($rmaId);
                $is_futures = $this->model_customerpartner_rma_management->checkIsFuturesRma($rmaId);
                if ($order_result['order_status'] == CustomerSalesOrderStatus::COMPLETED && ($is_margin || $is_futures)) {
                    $refund_info = null;
                    if ($is_margin) {
                        $refund_info = $this->model_account_rma_management->getMarginPriceInfo(
                            null,
                            $line_info[0]['quantity'],
                            $order_line_info['order_product_id']
                        );
                    }
                    if ($is_futures) {
                        $agree_info = $this->model_account_customerpartner_futures_order->getAgreementInfoByOrderProduct(
                            $order_result['order_id'], $order_result['product_id']
                        );
                        $refund_info = $this->model_account_rma_management->getFutureMarginPriceInfo(
                            $agree_info['id'],
                            $line_info[0]['quantity'],
                            $order_line_info['order_product_id']
                        );
                    }
                    $maxRefundMoney =
                        (
                            $refund_info['freight_unit_price']
                            + $refund_info['rest_unit_price']
                            + $refund_info['advance_unit_price']
                            + ($this->customer->isEurope() ? $refund_info['service_fee_per'] : 0)
                        ) * $line_info[0]['quantity'];
                } else {
                    $maxRefundMoney = $this->model_customerpartner_rma_management->getOrderProductPrice($rmaId);
                }
            }
        } else {
            $maxRefundMoney = $this->model_customerpartner_rma_management->getPurchaseOrderRmaPrice($rmaId);
        }

        return $maxRefundMoney;
    }


    /**
     * 格式化价格
     * @param $price
     * @return string
     */
    private function formatPrice($price)
    {
        return $this->customer->isJapan()
            ? number_format($price, 0, '.', '')
            : number_format($price, 2, '.', '');
    }
}
