<?php

use App\Enums\Common\YesNoEnum;
use App\Enums\Order\OcOrderTypeId;
use App\Models\Customer\Customer;
use App\Helper\CountryHelper;
use App\Repositories\Seller\SellerRepository;
use App\Widgets\VATToolTipWidget;

/**
 * Class ControllerAccountCustomerpartnerOrderlist
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountCustomerpartnerMarginOrder $model_account_customerpartner_margin_order
 * @property ModelAccountOrder $model_account_order
 * @property ModelAccountProductQuotesMarginContract $model_account_product_quotes_margin_contract
 * @property ModelAccountProductQuotesRebatesAgreement $model_account_product_quotes_rebates_agreement
 * @property ModelMpLocalisationOrderStatus $model_mp_localisation_order_status
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountCustomerpartnerOrderlist extends Controller
{

    private $error = array();
    private $data = array();

    public function __construct($registry)
    {
        parent::__construct($registry);
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/customerpartner/orderlist', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->model('account/customerpartner');
        $this->data['chkIsPartner'] = customer()->isPartner();

        if (!$this->data['chkIsPartner'] || (isset($this->session->data['marketplace_seller_mode']) && !$this->session->data['marketplace_seller_mode'])) {
            $this->response->redirect($this->url->link('account/account', '', true));
        }
    }

    public function index()
    {
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');
        $this->document->addStyle('catalog/view/theme/default/stylesheet/MP/sell.css?v=' . APP_VERSION);
        $this->document->addStyle('catalog/view/javascript/user_portrait/user_portrait.css?v=' . APP_VERSION);
        $this->document->addScript('catalog/view/javascript/user_portrait/user_portrait.js?v=' . APP_VERSION);

        $this->load->language('account/customerpartner/orderlist');
        $this->load->language('common/cwf');
        $this->document->setTitle($this->language->get('heading_title_orders'));
        $this->data['rebate_logo'] = '/image/product/rebate_15x15.png';

        //region 面包屑
        $this->data['breadcrumbs'] = [
            [
                'text' => $this->language->get('heading_title_orders'),
                'href' => $this->url->link('account/customerpartner/orderlist', '', true),
                'separator' => $this->language->get('text_separator')
            ]
        ];
        //endregion

        $this->load->model('tool/image');

        trim_strings($this->request->get);

        //region 获取请求参数
        if (isset($this->request->get['filter_order'])) {
            $filter_order = $this->request->get['filter_order'];
        } else {
            $filter_order = null;
        }

        if (isset($this->request->get['filter_nickname'])) {
            $filter_nickname = $this->request->get['filter_nickname'];
        } else {
            $filter_nickname = null;
        }

        if (isset($this->request->get['filter_sku_mpn'])) {
            $filter_sku_mpn = $this->request->get['filter_sku_mpn'];
        } else {
            $filter_sku_mpn = null;
        }

        if (isset($this->request->get['filter_date_from'])) {
            $filter_date_from = $this->request->get['filter_date_from'];
        } else {
            $filter_date_from = null;
        }

        if (isset($this->request->get['filter_date_to'])) {
            $filter_date_to = $this->request->get['filter_date_to'];
        } else {
            $filter_date_to = null;
        }

        if (isset($this->request->get['filter_include_all_refund'])) {
            $filter_include_all_refund = $this->request->get['filter_include_all_refund'];
        } else {
            $filter_include_all_refund = null;
        }

        if (isset($this->request->get['filter_order_status'])) {
            $filter_order_status = $this->request->get['filter_order_status'];
        } else {
            $filter_order_status = null;
        }

        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'o.date_added';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'DESC';
        }

        if (isset($this->request->get['page'])) {
            $page =intval($this->request->get['page']);
        } else {
            $page = 1;
        }
        $page_limit = intval(get_value_or_default($this->request->request, 'page_limit', 10));
        $data = array(
            'filter_order' => $filter_order,
            'filter_nickname' => $filter_nickname,
            'filter_sku_mpn' => $filter_sku_mpn,
            'filter_date_from' => $filter_date_from,
            'filter_date_to' => $filter_date_to,
            'filter_include_all_refund' => $filter_include_all_refund,
            'filter_order_status' => $filter_order_status,
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $page_limit,
            'limit' => $page_limit
        );
        //endregion

        $this->load->model('account/product_quotes/margin_contract');

        $margin_order_list = $this->model_account_product_quotes_margin_contract->getMarginOrderIdBySellerId($this->customer->getId());
        $data['margin_order_list'] = $margin_order_list;
        //对保证金的采购订单展示，如果搜索的时候包含了SKU/MPN,需要额外考虑包含保证金订金SKU的情况
        //if (!empty($data['filter_sku_mpn'])) {
        //    $margin_sku = $this->model_account_product_quotes_margin_contract->convertSku2MarginSku($this->customer->getId(), $data['filter_sku_mpn']);
        //    $data['margin_sku_mpn'] = $margin_sku;
        //}
        //对期货保证金的采购订单展示，如果搜索的时候包含了SKU/MPN,需要额外考虑包含保证金订金SKU的情况
        //if (!empty($data['filter_sku_mpn'])) {
        //    $future_margin_sku = $this->model_account_customerpartner->convertSkuToFutureMarginSku($this->customer->getId(), $data['filter_sku_mpn']);
        //    $data['future_margin_sku_mpn'] = $future_margin_sku;
        //}
        $data['margin_sku_mpn'] = '';
        $data['future_margin_sku_mpn'] ='';
        $this->load->model('catalog/product');
        $this->load->model('account/order');
        $this->load->model('account/product_quotes/rebates_agreement');
        $this->load->model('account/customerpartner/margin_order');
        $this->load->model('tool/image');

        $symbol = $this->currency->getSymbolLeft($this->session->data['currency']);
        if (empty($symbol)) {
            $symbol = $this->currency->getSymbolRight($this->session->data['currency']);
        }
        if (empty($symbol)) {
            $symbol = '$';
        }

        $orders = $this->model_account_customerpartner->getSellerOrdersByOrm($data);
        $orderstotal = $this->model_account_customerpartner->getSellerOrdersTotalByOrm($data);

        $customer_id = $this->customer->getId();
        if ($orders) {
            $refundOrderIds = $this->model_account_customerpartner->getAllRefundOrderId($this->customer->getId());
            if($margin_order_list){
                $refundMarginOrderIds=$this->model_account_customerpartner->getAllRefundMarginOrderId($margin_order_list);
                $refundOrderIds=array_merge($refundOrderIds,$refundMarginOrderIds);
            }

            $buyerIds = array_column($orders, 'buyer_id');
            $buyerCustomerModelMap = Customer::query()->with('buyer')->whereIn('customer_id', $buyerIds)->get()->keyBy('customer_id');

            foreach ($orders as $key => $order_list) {
                // N-81 判断是否为全部退货 start
                $orders[$key]['is_full_refund'] = in_array($order_list['order_id'], $refundOrderIds);
                // N-81 判断是否为全部退货 end
                $cto_map = $this->model_account_customerpartner->getPurchaseOrderSellerId($order_list['order_id']);
                if (empty($cto_map)) {
                    continue;
                }
                $orders[$key]['ex_vat'] = VATToolTipWidget::widget(['customer' => $buyerCustomerModelMap->get($order_list['buyer_id']), 'is_show_vat' => true])->render();

                $process_product = array();
                $margin_product_map = array();
                $margin_agreement = $this->model_account_product_quotes_margin_contract->getSellerMarginByOrderId($order_list['order_id'], $customer_id);

                if (isset($margin_agreement) && !empty($margin_agreement)) {
                    foreach ($margin_agreement as $agreement) {
                        if ($agreement['seller_id'] == $customer_id) {
                            $agreement_id = $agreement['agreement_id'];
                            $margin_process = $this->model_account_product_quotes_margin_contract->getMarginProcessDetail($agreement_id);
                            if (isset($margin_process['advance_product_id'])) {
                                $process_product[] = $margin_process['advance_product_id'];
                                $margin_product_map[$margin_process['advance_product_id']] = [
                                    'original_product_id' => $agreement['product_id'],
                                    'original_sku' => $agreement['originalSku'],
                                    'original_mpn' => $agreement['originalMpn'],
                                ];
                            }
                            if (isset($margin_process['rest_product_id'])) {
                                $process_product[] = $margin_process['rest_product_id'];
                            }
                        }
                    }
                }

                $quote = 0;
                //$tag_array = array();
                $products = array();
                $seller_unique = array();
                foreach ($cto_map as $product_id => $seller_id) {
                    if (in_array($seller_id, $seller_unique)) {
                        continue;
                    }
                    if ($seller_id != $customer_id && !in_array($product_id, $process_product)) {
                        continue;
                    }
                    $seller_unique[] = $seller_id;
                    $quote += $this->model_account_order->getSellerQuoteAmount($order_list['order_id'], $seller_id);

                    if ($customer_id != $seller_id) {
                        $products_temp = $this->model_account_customerpartner->getSellerOrderProductInfo($order_list['order_id'], $seller_id, $process_product);
                    } else {
                        $products_temp = $this->model_account_customerpartner->getSellerOrderProductInfo($order_list['order_id'], $seller_id);
                    }
                    if (!empty($products_temp)) {
                        $products = array_merge($products, $products_temp);
                        //foreach ($products_temp as $p_temp) {
                        //    if ($this->data['chkIsPartner']) {
                        //        $tag_array_temp = $this->model_catalog_product->getOcOrderSpecificTag($order_list['order_id'], null, $seller_id, 1, $p_temp['product_id']);
                        //    } else {
                        //        $tag_array_temp = $this->model_catalog_product->getOcOrderSpecificTag($order_list['order_id']);
                        //    }
                        //    if (!empty($tag_array_temp)) {
                        //        $tag_array = array_merge($tag_array, $tag_array_temp);
                        //    }
                        //}
                    }
                }

                if (!empty($products)) {
                    $sku_list = [];
                    // 获取该order参与保证金业务的商品id
                    $temp_order_margin_product_ids = $this->model_account_customerpartner_margin_order->getMarginProducts($order_list['order_id']);
                    $orders[$key]['products'] = $products;
                    foreach ($products as $key2 => $value) {
                        $tmp_sku = [];
                        $tmp_sku['rebate_icon'] = '';
                        $tmp_sku['type_id'] = $value['type_id'];
                        $tmp_sku['agreement_id'] =  $value['agreement_id'];
                        $tmp_sku['quantity'] =  $value['quantity'];
                        $sku = $value['sku'];
                        $mpn = $value['mpn'];
                        //if (!empty($margin_product_map[$value['product_id']])) {
                        //    $sku = $margin_product_map[$value['product_id']]['original_sku'];
                        //    $mpn = $margin_product_map[$value['product_id']]['original_mpn'];
                        //}
                        ////
                        //if($tmp_sku['type_id'] == 3){
                        //    $future_margin_sku_info = $this->model_account_customerpartner->getFutureRestProductInfo($order_list['order_id']);
                        //    if($future_margin_sku_info){
                        //        $sku = $future_margin_sku_info['sku'];
                        //        $mpn = $future_margin_sku_info['mpn'];
                        //    }
                        //}
                        $tmp_sku['sku'] = $sku;
                        $tmp_sku['mpn'] = $mpn;
                        // tag
                        $tag_array = $this->model_catalog_product->getProductSpecificTag($value['product_id']);
                        $tags = [];
                        if($tag_array){
                            foreach ($tag_array as $tag){
                                if(isset($tag['origin_icon']) && !empty($tag['origin_icon'])){
                                    //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                                    $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                                    $tags[] = '<img data-toggle="tooltip"  class="'. $tag['class_style'] . '" title="'.$tag['description']. '" style="padding-left: 1px;margin-top:-5px;" src="'.$img_url.'">';
                                }
                            }
                        }
                        $tmp_sku['tag'] = $tags;
                        if (!isset($orders[$key]['total'])) {
                            $orders[$key]['total'] = 0;
                        } elseif (!is_numeric($orders[$key]['total'])) {
                            $orders[$key]['total'] = str_replace(',', '', str_replace($symbol, '', $orders[$key]['total']));
                        }
                        //返点部分不能通过type_id更改因为存在新旧代码兼容的问题
                        $rebateAgreementId = $this->model_account_product_quotes_rebates_agreement->getRebateAgreementId($order_list['order_id'], $value['product_id']);
                        if ($rebateAgreementId) {
                            $tmp_sku['type_id'] = OcOrderTypeId::TYPE_REBATE;
                            $agreementCode = $this->model_account_order->getRebateAgreementCode($rebateAgreementId);
                            $tmp_sku['rebate_icon'] = dprintf(
                                $this->language->get('global_backend_rebate_sign'),
                                ['id' => $rebateAgreementId,'s_id'=> $agreementCode,]
                            );
                        }
                        if (in_array($value['product_id'], $temp_order_margin_product_ids)) {
                            $agree_info = $this->model_account_customerpartner_margin_order->getAgreementInfoByOrderProduct(
                                $order_list['order_id'], $value['product_id']
                            );
                            $tmp_sku['margin_icon'] = dprintf(
                                $this->language->get('global_margin_sign'),
                                ['id' => $agree_info['agreement_id']]
                            );
                        }
                        if($value['type_id'] == OcOrderTypeId::TYPE_FUTURE){
                            $future_margin_info = $this->model_account_order->getFutureMarginInfo($value['agreement_id']);
                            $tmp_sku['agreement_no'] = $future_margin_info['agreement_no'];
                        }
                        $sku_list[] = $tmp_sku;
                        $orders[$key]['total'] += ($value['c2oprice'] + $value['service_fee'] + ($value['freight_per'] + $value['package_fee']) * $value['quantity']);
                        $orders[$key]['total'] -= $value['campaign_amount']; //实付：总金额-优惠券-满减 ; 实付+优惠券+总金额-满减
                    }
                    $orders[$key]['sku_list'] = $sku_list;

                }
                $orders[$key]['quote'] = $quote;
                $orders[$key]['total'] = $this->currency->format($orders[$key]['total'] - $quote, $orders[$key]['currency_code'], 1);

                //$tags = [];
                //if (!empty($tag_array)) {
                //    $repeat_tag = [];
                //    foreach ($tag_array as $tag) {
                //        if (isset($tag['icon']) && !empty($tag['icon']) && !in_array($tag['tag_id'], $repeat_tag)) {
                //            $repeat_tag[] = $tag['tag_id'];
                //            $tags[] = '<img data-toggle="tooltip" title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $tag['icon'] . '">';
                //        }
                //    }
                //}
                //
                //$orders[$key]['tag'] = isset($tags) ? $tags : null;
                // 是否是上门取货buyer
                if (in_array($order_list['customer_group_id'], COLLECTION_FROM_DOMICILE)) {
                    $orders[$key]['is_home_pickup'] = true;
                } else {
                    $orders[$key]['is_home_pickup'] = false;
                }
                // foreach end
                $orders[$key]['orderidlink'] = $this->url->link('account/customerpartner/orderinfo&order_id=' . $order_list['order_id'], '', true);

                $orders[$key]['send_mail_link'] = url(['customerpartner/message_center/message/new', 'receiver_ids' => $order_list['buyer_id']]);
            }
        }


        $this->data['orders'] = $orders;
        $this->load->model('mp_localisation/order_status');
        $this->data['status'] = $this->model_mp_localisation_order_status->getOrderStatuses();

        //region 用于 table排序
        $url = '';

        if (isset($this->request->get['filter_order'])) {
            $url .= '&filter_order=' . $this->request->get['filter_order'];
        }

        if (isset($this->request->get['filter_nickname'])) {
            $url .= '&filter_nickname=' . urlencode(html_entity_decode($this->request->get['filter_nickname'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_sku_mpn'])) {
            $url .= '&filter_sku_mpn=' . urlencode(html_entity_decode($this->request->get['filter_sku_mpn'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_quantity'])) {
            $url .= '&filter_quantity=' . $this->request->get['filter_quantity'];
        }

        if (isset($this->request->get['filter_include_all_refund'])) {
            $url .= '&filter_include_all_refund=' . $this->request->get['filter_include_all_refund'];
        }

        if (isset($this->request->get['filter_order_status'])) {
            $url .= '&filter_order_status=' . $this->request->get['filter_order_status'];
        }

        if (isset($this->request->get['filter_date_from'])) {
            $url .= '&filter_date_from=' . $this->request->get['filter_date_from'];
        }

        if (isset($this->request->get['filter_date_to'])) {
            $url .= '&filter_date_to=' . $this->request->get['filter_date_to'];
        }

        if ($order == 'ASC') {
            $url .= '&order=DESC';
        } else {
            $url .= '&order=ASC';
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        $this->data['sort_nickname'] = $this->url->link('account/customerpartner/orderlist', '' . '&sort=cus.nickname' . $url, true);
        $this->data['sort_date'] = $this->url->link('account/customerpartner/orderlist', '' . '&sort=o.date_added' . $url, true);
        //endregion

        //region 用户分页
        $url = '';

        if (isset($this->request->get['filter_order'])) {
            $url .= '&filter_order=' . $this->request->get['filter_order'];
        }

        if (isset($this->request->get['filter_nickname'])) {
            $url .= '&filter_nickname=' . urlencode(html_entity_decode($this->request->get['filter_nickname'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_sku_mpn'])) {
            $url .= '&filter_sku_mpn=' . urlencode(html_entity_decode($this->request->get['filter_sku_mpn'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_quantity'])) {
            $url .= '&filter_quantity=' . $this->request->get['filter_quantity'];
        }

        if (isset($this->request->get['filter_date_from'])) {
            $url .= '&filter_date_from=' . $this->request->get['filter_date_from'];
        }

        if (isset($this->request->get['filter_date_to'])) {
            $url .= '&filter_date_to=' . $this->request->get['filter_date_to'];
        }

        if (isset($this->request->get['filter_include_all_refund'])) {
            $url .= '&filter_include_all_refund=' . $this->request->get['filter_include_all_refund'];
        }

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }
        //endregion

        $pagination = new Pagination();
        $pagination->total = $orderstotal;
        $pagination->page = $page;
        $pagination->limit = $page_limit;
        $pagination->url = $this->url->link('account/customerpartner/orderlist' . $url, '&page={page}', true);

        $this->data['pagination'] = $pagination->render();
        $this->data['results'] = sprintf($this->language->get('text_pagination'), ($orderstotal) ? (($page - 1) * 10) + 1 : 0, ((($page - 1) * 10) > ($orderstotal - 10)) ? $orderstotal : ((($page - 1) * 10) + 10), $orderstotal, ceil($orderstotal / 10));

        $this->data['filter_order'] = $filter_order;
        $this->data['filter_nickname'] = $filter_nickname;
        $this->data['filter_sku_mpn'] = $filter_sku_mpn;
        $this->data['filter_date_from'] = $filter_date_from;
        $this->data['filter_date_to'] = $filter_date_to;
        $this->data['filter_include_all_refund'] = $filter_include_all_refund;
        $this->data['filter_order_status'] = $filter_order_status;

        $this->data['sort'] = $sort;
        $this->data['order'] = $order;

        if (isset($this->error['warning'])) {
            $this->data['error_warning'] = $this->error['warning'];
        } else {
            $this->data['error_warning'] = '';
        }

        $this->data['current'] = $this->url->link('account/customerpartner/orderlist', '', true);

        $this->data['back'] = $this->url->link('account/account', '', true);

        $this->data['auto_complete_nickname'] = $this->url->link('account/customerpartner/orderlist/nickNameAssociation', '', true);


        //seller发送buyer站内信
        $this->data['contact_buyer'] = $this->load->controller('customerpartner/contact_buyer');

        $this->data['isMember'] = true;
        if ($this->config->get('module_wk_seller_group_status')) {
            $this->data['module_wk_seller_group_status'] = true;
            $this->load->model('account/customer_group');
            $isMember = $this->model_account_customer_group->getSellerMembershipGroup($this->customer->getId());
            if ($isMember) {
                $allowedAccountMenu = $this->model_account_customer_group->getaccountMenu($isMember['gid']);
                if ($allowedAccountMenu['value']) {
                    $accountMenu = explode(',', $allowedAccountMenu['value']);
                    if ($accountMenu && !in_array('orderhistory:orderhistory', $accountMenu)) {
                        $this->data['isMember'] = false;
                    }
                }
            } else {
                $this->data['isMember'] = false;
            }
        } else {
            if (!is_array($this->config->get('marketplace_allowed_account_menu')) || !in_array('orderhistory', $this->config->get('marketplace_allowed_account_menu'))) {
                $this->response->redirect($this->url->link('account/account', '', true));
            }
        }


        // 是否显示云送仓提醒
        $this->data['is_top_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();
        $this->data['separate_view'] = true;
        $this->data['column_left'] = '';
        $this->data['column_right'] = '';
        $this->data['content_top'] = '';
        $this->data['content_bottom'] = '';
        $this->data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');

        $this->data['footer'] = $this->load->controller('account/customerpartner/footer');
        $this->data['header'] = $this->load->controller('account/customerpartner/header');

        $this->response->setOutput($this->load->view('account/customerpartner/orderlist', $this->data));
    }

    public function downloadOrderHistory()
    {
        set_time_limit(0);
        $this->load->model('account/customerpartner');
        $this->load->language('account/customerpartner/orderlist');

        if (isset($this->request->get['filter_order'])) {
            $filter_order = $this->request->get['filter_order'];
        } else {
            $filter_order = null;
        }

        if (isset($this->request->get['filter_nickname'])) {
            $filter_nickname = $this->request->get['filter_nickname'];
        } else {
            $filter_nickname = null;
        }

        if (isset($this->request->get['filter_sku_mpn'])) {
            $filter_sku_mpn = $this->request->get['filter_sku_mpn'];
        } else {
            $filter_sku_mpn = null;
        }

        if (isset($this->request->get['filter_date_from'])) {
            $filter_date_from = $this->request->get['filter_date_from'];
        } else {
            $filter_date_from = null;
        }

        if (isset($this->request->get['filter_date_to'])) {
            $filter_date_to = $this->request->get['filter_date_to'];
        } else {
            $filter_date_to = null;
        }

        // N-81
        if (isset($this->request->get['filter_include_all_refund'])) {
            $filter_include_all_refund = $this->request->get['filter_include_all_refund'];
        } else {
            $filter_include_all_refund = null;
        }

        if (isset($this->request->get['filter_order_status'])) {
            $filter_order_status = $this->request->get['filter_order_status'];
        } else {
            $filter_order_status = null;
        }

        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'o.order_id';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }

        $this->load->model('account/product_quotes/margin_contract');
        $margin_store_id = array();
        $service_id = $this->model_account_product_quotes_margin_contract->getMarginServiceStoreId($this->customer->getId());
        $bx_id = $this->model_account_product_quotes_margin_contract->getMarginBxStoreId($this->customer->getId());
        if(isset($service_id)){
            $margin_store_id[] = $service_id;
        }
        if(isset($bx_id)){
            $margin_store_id[] = $bx_id;
        }

        $margin_order_list = $this->model_account_product_quotes_margin_contract->getMarginOrderIdBySellerId($this->customer->getId());
        $data['margin_order_list'] = $margin_order_list;
        //对保证金的采购订单展示，如果搜索的时候包含了SKU/MPN,需要额外考虑包含保证金订金SKU的情况

        $order_seller_id = $this->customer->getId();
        $margin_agreement = $this->model_account_product_quotes_margin_contract->getSellerMarginByOrderId($filter_order, $order_seller_id, true);

        $process_product = array();
        $margin_product_original_map = [];
        $margin_deposit_map = [];
        if(isset($margin_agreement) && !empty($margin_agreement)){
            foreach ($margin_agreement as $agreement) {
                if($agreement['seller_id'] == $order_seller_id){
                    $agreement_id = $agreement['agreement_id'];
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
                        $margin_deposit_map[$margin_process['rest_product_id']] = $margin_process['deposit_sku'];
                    }
                }
            }
        }

        $data = array(
            'filter_order' => $filter_order,
            'filter_nickname' => $filter_nickname,
            'filter_sku_mpn' => $filter_sku_mpn,
            'filter_date_from' => $filter_date_from,
            'filter_date_to' => $filter_date_to,
            'filter_include_all_refund' => $filter_include_all_refund,
            'filter_order_status' => $filter_order_status,
            'customer_id' => $this->customer->getId(),
            'margin_store_id' => $margin_store_id,
            'margin_order_list' => $margin_order_list,
            'bx_product_id' => $process_product,
            'sort' => $sort,
            'order' => $order
        );

        //if(!empty($data['filter_sku_mpn'])){
        //    $margin_sku = $this->model_account_product_quotes_margin_contract->convertSku2MarginSku($this->customer->getId(), $data['filter_sku_mpn']);
        //    $data['margin_sku_mpn'] = $margin_sku;
        //}
        //
        ////对期货保证金的采购订单展示，如果搜索的时候包含了SKU/MPN,需要额外考虑包含保证金订金SKU的情况
        //if (!empty($data['filter_sku_mpn'])) {
        //    $future_margin_sku = $this->model_account_customerpartner->convertSkuToFutureMarginSku($this->customer->getId(), $data['filter_sku_mpn']);
        //    $data['future_margin_sku_mpn'] = $future_margin_sku;
        //}

        //是否启用议价
        $enableQuote = false;
        if (!empty($this->customer->getCountryId()) && in_array($this->customer->getCountryId(), QUOTE_ENABLE_COUNTRY)) {
            $enableQuote = true;
        }
        $data['margin_sku_mpn'] = '';
        $data['future_margin_sku_mpn'] = '';


        //是否为欧洲
        $isEurope = false;
        if ($this->country->isEuropeCountry($this->customer->getCountryId())) {
            $isEurope = true;
        }
        $orders = $this->model_account_customerpartner->getSellerOrdersForUpdate($data);
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis",time()),'YmdHis');
        //12591 end
        $fileName = "SalesReport" . $time . ".csv";
        /**
         * head 头顺序
         * Order ID/Nickname/BuyerCode/MPN/Item Code/Product Name/Sales Quantity/Discounted Price/
         * Quote Discount/Service Fee Per Unit/Line Amount/is Return/Sales Date
         */
        $head = [
            "Order ID",
            'Name',
            'BuyerCode',
            'MPN',
            'Item Code',
            'Product Name',
            'Sales Quantity',
            'Discounted Price',
        ];

        // 如果是欧洲也有议价
        $isEurope && $head[] = 'Service Fee Per Unit';

        // 议价
        $enableQuote && $head[] = 'Discount(OFF)';
        // 议价&欧洲
        $isEurope && $enableQuote && $head[] = 'Service Fee Discount Per Unit';
        $head[] = 'Fulfillment Per Unit';
        array_push($head,'Total','Promotion Discount','Final Total','Sales Date','Order Type','Is Return','RMA ID');

        if ($orders && $orders instanceof Generator) {
            $totalPrice = 0.00;
            $totalNumber = 0;
            $index = 0;
            foreach ($orders as $detail) {
                $detail = get_object_vars($detail);
                $content[$index] = [
                    $detail['orderId'],
                    $detail['nickname'],
                    $detail['user_number'],
                    $detail['mpn'],
                    $detail['ItemCode'],
                    html_entity_decode($detail['name']),//Product Name
                    $detail['quantity'],                //Sales Quantity
                    $detail['SalesPrice'],              //Discounted Price
                ];
                $isEurope && $content[$index][] = $detail['service_fee_per'];                                           //Service Fee Per Unit
                $enableQuote && $content[$index][] = $detail['discountShow'] ? $detail['discountShow'] . '%' : '';      //Discount(OFF)
                $enableQuote && $isEurope && $content[$index][] = -bcmul($detail['amount_service_fee_per'],1,2);        //Service Fee Discount Per Unit
                $content[$index][] = ((double)$detail['freight_per']  + $detail['package_fee']);                        //Freight Per Unit
                $tempTotal = ((double)$detail['quantity'] * $detail['SalesPrice'] + $detail['serviceFee'] + ($detail['freight_per'] + $detail['package_fee']) * $detail['quantity'] - $detail['quote']);
                $content[$index][] = $tempTotal;                                                                        //Total
                $content[$index][] = !empty($detail['campaign_amount']) ? '-' . (double)$detail['campaign_amount'] : 0; //Promotion Discount
                $content[$index][] = (double)($tempTotal - $detail['campaign_amount']);                                 //Final Total

                $content[$index][] = "\t" . $detail['date_added'] . "\t";                                               //Sales Date
                $content[$index][] = $this->model_account_customerpartner->getPurchaseOrderType($detail['type_id'], $detail['agreement_id'], $detail['product_id']);
                $content[$index][] = 'No';                                                                              //Is Return

                $totalNumber += $detail['quantity'];
                $totalPrice += ((double)$detail['quantity'] * $detail['SalesPrice'] + $detail['serviceFee'] + ($detail['freight_per']  + $detail['package_fee']) * $detail['quantity'] - $detail['quote']);
                /**
                 * 如果有 退返，则需要多增一行
                 */
                $rmaInfo = $this->model_account_customerpartner->getSellerAgreeRmaOrderInfoInOrderHistory($detail['orderId'], $detail['seller_id'], $detail['order_product_id']);
                foreach ($rmaInfo as $rma) {
                    $index++;
                    if(!$rma['quantity']){
                        $quantity_show = 0;
                    }else{
                        $quantity_show = -$rma['quantity'];
                    }
                    $content[$index] = [
                        $rma['order_id'],                                   //Order ID
                        $rma['nickname'],                                   //Name
                        $rma['user_number'],                                //BuyerCode
                        $rma['mpn'],                                        //MPN
                        $rma['sku'],                                        //Item Code
                        html_entity_decode($rma['product_name']),           //Product Name
                        $quantity_show,                                     //Sales Quantity
                        '',                                                 //Discounted Price
                    ];
                    $enableQuote && $content[$index][] = '';                //Discount(OFF)
                    $isEurope && $content[$index][] = '';                   //Service Fee Per Unit
                    $enableQuote && $isEurope && $content[$index][] = '';   //Service Fee Discount Per Unit
                    $content[$index][] = '';                                //Freight Per Unit
                    //array_push($content[$index],-$rma['actual_refund_amount'], "\t".$rma['update_time']."\t");
                    $tmpRmaTotal = -($rma['actual_refund_amount']+$rma['coupon_amount']);
                    $content[$index][] =  $tmpRmaTotal;//Total
                    $content[$index][] =  '';//Promotion Discount
                    $content[$index][] =  $tmpRmaTotal;//Final Total
                    $content[$index][] =  ' ';//Sales Date
                    $content[$index][] = $this->model_account_customerpartner->getPurchaseOrderType($detail['type_id'],$detail['agreement_id'],$detail['product_id']);
                    $content[$index][] =  'Yes';//Is Return
                    //$content[$index][] =  $rma['rma_name'];
                    $content[$index][] =   "\t".$rma['rma_order_id'];

                    $totalNumber -= $rma['quantity'];
                    $totalPrice -= ($rma['actual_refund_amount']+$rma['coupon_amount']);
                }
                $index++;
            }
            $index++;
            $content[$index] = ['', '', '', '', '', 'Total Sales Quantity', $totalNumber];
            $enableQuote && $content[$index][] = '';
            $isEurope && $content[$index][] = '';
            $enableQuote && $isEurope && $content[$index][] = '';
            $content[$index][] = '';
            array_push($content[$index], 'Total Price', $totalPrice);

            //12591 B2B记录各国别用户的操作时间
            outputCsv($fileName,$head,$content,$this->session);
            //12591 end
        } else {
            $content = $this->language->get('error_no_record');
            //12591 B2B记录各国别用户的操作时间
            outputCsv($fileName,$head,$content,$this->session);
            //12591 end
        }
    }

    public function nickNameAssociation()
    {
        $response = [];
        if (isset_and_not_empty($this->request->get, 'filter_nickname')) {
            $response = $this->model_account_customerpartner->getBuyerBySellerOrder(trim($this->request->get['filter_nickname']), $this->customer->getId());
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($response));
    }
}


