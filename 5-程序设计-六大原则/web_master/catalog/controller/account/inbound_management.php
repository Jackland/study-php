<?php

/**
 * Class ControllerAccountInboundManagement
 * @property ModelAccountInboundManagement $model_account_inbound_management
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountInboundManagement extends Controller
{
    public function index()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/customer_order', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->language('account/inbound_management');

        $this->document->setTitle($this->language->get('heading_title_inbound_management'));

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_title_inbound_management'),
            'href' => $this->url->link('account/inbound_management', '', true)
        );

        $data['column_right'] = $this->load->controller('common/column_right');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        if (isset($this->request->get['filter_inboundOrderNumber'])) {
            $data['filter_inboundOrderNumber'] = $this->request->get['filter_inboundOrderNumber'];
        }
        if (isset($this->request->request['filter_inboundOrderStatus'])) {
            $data['filter_inboundOrderStatus'] = $this->request->get['filter_inboundOrderStatus'];
        }
        if (isset($this->request->request['show_detail'])) {
            $data['show_detail'] = $this->request->get['show_detail'];
        }

        $this->response->setOutput($this->load->view('account/inbound_management', $data));
    }

    public function queryList()
    {
        $this->load->language('account/inbound_management');
        $this->load->model('account/inbound_management');
        $data = array();
        $data['isInnerAccount'] = $this->customer->isInnerAccount();
        $customer_id = $this->customer->getId();
        // 入库单号
        if (isset($this->request->get['filter_inboundOrderNumber']) && trim($this->request->get['filter_inboundOrderNumber']) != '') {
            $filter_inboundOrderNumber = trim($this->request->get['filter_inboundOrderNumber']);
        } else {
            $filter_inboundOrderNumber = null;
        }
        $data['filter_inboundOrderNumber'] = $filter_inboundOrderNumber;
        // 预计入库日期
        if (isset($this->request->get['filter_estimatedDateStart']) && trim($this->request->get['filter_estimatedDateStart']) != '') {
            $filter_estimatedDateStart = trim($this->request->get['filter_estimatedDateStart']);
        } else {
            $filter_estimatedDateStart = null;
        }
        $data['filter_estimatedDateStart'] = $filter_estimatedDateStart;
        if (isset($this->request->get['filter_estimatedDateEnd']) && trim($this->request->get['filter_estimatedDateEnd']) != '') {
            $filter_estimatedDateEnd = trim($this->request->get['filter_estimatedDateEnd']);
        } else {
            $filter_estimatedDateEnd = null;
        }
        $data['filter_estimatedDateEnd'] = $filter_estimatedDateEnd;
        // 状态
        if (isset($this->request->get['filter_inboundOrderStatus']) && trim($this->request->get['filter_inboundOrderStatus']) != '') {
            $filter_inboundOrderStatus = trim($this->request->get['filter_inboundOrderStatus']);
        } else {
            $filter_inboundOrderStatus = null;
        }
        $data['filter_inboundOrderStatus'] = $filter_inboundOrderStatus;
        // 集装箱号
        if (isset($this->request->get['filter_containerNumber']) && trim($this->request->get['filter_containerNumber']) != '') {
            $filter_containerNumber = trim($this->request->get['filter_containerNumber']);
        } else {
            $filter_containerNumber = null;
        }
        $data['filter_containerNumber'] = $filter_containerNumber;
        // 收货日期
        if (isset($this->request->get['filter_receiptDateStart']) && trim($this->request->get['filter_receiptDateStart']) != '') {
            $filter_receiptDateStart = trim($this->request->get['filter_receiptDateStart']);
        } else {
            $filter_receiptDateStart = null;
        }
        $data['filter_receiptDateStart'] = $filter_receiptDateStart;
        if (isset($this->request->get['filter_receiptDateEnd']) && trim($this->request->get['filter_receiptDateEnd']) != '') {
            $filter_receiptDateEnd = trim($this->request->get['filter_receiptDateEnd']);
        } else {
            $filter_receiptDateEnd = null;
        }
        $data['filter_receiptDateEnd'] = $filter_receiptDateEnd;
        // 头程运输方式
        if (isset($this->request->get['filter_shippingWay']) && trim($this->request->get['filter_shippingWay']) != '') {
            $filter_shippingWay = trim($this->request->get['filter_shippingWay']);
        } else {
            $filter_shippingWay = null;
        }
        $data['filter_shippingWay'] = $filter_shippingWay;
        /* 分页 */
        if (isset($this->request->get['page_num'])) {
            $page_num = intval($this->request->get['page_num']);
        } else {
            $page_num = 1;
        }
        $data['page_num'] = $page_num;
        if (isset($this->request->get['page_limit'])) {
            $page_limit = intval($this->request->get['page_limit']) ?: 15;
        } else {
            $page_limit = 15;
        }
        $data['page_limit'] = $page_limit;

        //过滤参数
        $filter_data = array(
            'filter_inboundOrderNumber' => $filter_inboundOrderNumber,
            'filter_estimatedDateStart' => $filter_estimatedDateStart ? $filter_estimatedDateStart . ' 00:00:00' : null,
            'filter_estimatedDateEnd' => $filter_estimatedDateEnd ? $filter_estimatedDateEnd . ' 23:59:59' : null,
            'filter_inboundOrderStatus' => $filter_inboundOrderStatus,
            'filter_containerNumber' => $filter_containerNumber,
            'filter_receiptDateStart' => $filter_receiptDateStart ? $filter_receiptDateStart . ' 00:00:00' : null,
            'filter_receiptDateEnd' => $filter_receiptDateEnd ? $filter_receiptDateEnd . ' 23:59:59' : null,
            'filter_shippingWay' => $filter_shippingWay,
            'customer_id' => $customer_id,
            'start' => ($page_num - 1) * $page_limit,
            'limit' => $page_limit,
        );

        // 计算总数
        $orderNum = $this->model_account_inbound_management->getReceiptsOrderCount($filter_data);
        $inboundOrders = $this->model_account_inbound_management->getReceiptsOrders($filter_data);
        if ($inboundOrders && count($inboundOrders)) {
            $dataResult = array();
            foreach ($inboundOrders as $inboundOrder) {
                switch (intval($inboundOrder['shipping_way'])) {
                    case 1:
                        $inboundOrder['shipping_way'] = $this->language->get('text_shipping_way_gigacloud');
                        break;
                    case 2:
                        $inboundOrder['shipping_way'] = $this->language->get('text_shipping_way_myself');
                        break;
                }
                switch (intval($inboundOrder['status'])) {
                    case 1:
                        $inboundOrder['status'] = $this->language->get('text_status_application');
                        break;
                    case 2:
                        $inboundOrder['status'] = $this->language->get('text_status_inspection');
                        break;
                    case 6:
                        $inboundOrder['status'] = $this->language->get('text_status_confirm');
                        break;
                    case 7:
                        $inboundOrder['status'] = $this->language->get('text_status_receipt');
                        break;
                    case 9:
                        $inboundOrder['status'] = $this->language->get('text_status_cancel');
                        break;
                }
                $dataResult[] = $inboundOrder;
            }
            $data['inboundOrders'] = $dataResult;
        }
        //分页
        $total_pages = ceil($orderNum / $page_limit);
        $data['total_pages'] = $total_pages;
        $data['total_num'] = $orderNum;
        $data['page_limit'] = $page_limit;
        $data['results'] = sprintf($this->language->get('text_pagination'), ($orderNum) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($orderNum - $page_limit)) ? $orderNum : ((($page_num - 1) * $page_limit) + $page_limit), $orderNum, $total_pages);
        $data['app_version'] = APP_VERSION;
        if (isset($this->request->get['show_detail'])) {
            $data['show_detail'] = $this->request->get['show_detail'];
            $data['show_detail_id'] = $dataResult[0]['receive_order_id'] ?? 0;
        }

        $this->response->setOutput($this->load->view('account/inbound_management_data', $data));

    }

    public function detailShow()
    {
        $this->load->language('account/inbound_management');
        $this->load->model('account/inbound_management');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $data = array();
        $data['seller_accounting_type'] = $this->customer->getAccountType();
        if (isset($this->request->get['id'])) {
            $receiveOrderId = $this->request->get['id'];
            $receiveOrder = $this->model_account_inbound_management->getReceiptsOrderById($receiveOrderId);
            if ($receiveOrder) {
                switch (intval($receiveOrder['shipping_way'])) {
                    case 1:
                        $receiveOrder['shipping_way'] = $this->language->get('text_shipping_way_gigacloud');
                        break;
                    case 2:
                        $receiveOrder['shipping_way'] = $this->language->get('text_shipping_way_myself');
                        break;
                }
                switch (intval($receiveOrder['package_flag'])) {
                    case 0:
                        $receiveOrder['package_flag'] = $this->language->get('package_flag_no');
                        break;
                    case 1:
                        $receiveOrder['package_flag'] = $this->language->get('package_flag_yes');
                        break;
                }
            }
            $data['receiveOrder'] = $receiveOrder;
            $receiveOrderDetails = $this->model_account_inbound_management->getReceiptsOrderDetailByHeaderId($receiveOrderId);
            if ($receiveOrderDetails && count($receiveOrderDetails)) {
                $dataResult = array();
                foreach ($receiveOrderDetails as $receiveOrderDetail) {
                    switch (intval($receiveOrderDetail['currency'])) {
                        case 1:
                            $receiveOrderDetail['currency'] = $this->language->get("currency_jpy");
                            break;
                        case 2:
                            $receiveOrderDetail['currency'] = $this->language->get("currency_gbp");
                            break;
                        case 3:
                            $receiveOrderDetail['currency'] = $this->language->get("currency_usd");
                            break;
                        case 4:
                            $receiveOrderDetail['currency'] = $this->language->get("currency_rmb");
                            break;
                        case 5:
                            $receiveOrderDetail['currency'] = $this->language->get("currency_deu");
                            break;
                    }
                    $tag_array = $this->model_catalog_product->getProductSpecificTag($receiveOrderDetail['product_id']);
                    $tags = array();
                    if (isset($tag_array)) {
                        foreach ($tag_array as $tag) {
                            if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])){
                                //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                                $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                                $tags[] = '<img data-toggle="tooltip" class="'.$tag['class_style']. '"title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                            }
                        }
                    }
                    $receiveOrderDetail['tag'] = $tags;
                    $dataResult[] = $receiveOrderDetail;
                }
                $data['receiveOrderDetails'] = $dataResult;
            }

        }
        $this->response->setOutput($this->load->view('account/inbound_management_data_detail', $data));
    }

    public function printSku()
    {
        $this->load->language('account/inbound_management');
        $data = array();
        $this->response->setOutput($this->load->view('account/inbound_management_print', $data));

    }
}
