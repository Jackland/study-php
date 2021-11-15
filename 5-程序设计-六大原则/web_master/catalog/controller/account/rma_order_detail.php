<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Components\Storage\StorageCloud;
use App\Repositories\Rma\RamRepository;

/**
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelAccountRmaManagement $model_account_rma_management
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountRmaOrderDetail extends AuthBuyerController
{
    public function index()
    {
        // 加载语言层
        $this->load->language('account/rma_management');
        $this->load->language('common/cwf');
        // 设置文档标题
        $this->document->setTitle($this->language->get('text_rma_detail'));
        // 面包屑导航
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_rma_management'),
            'href' => $this->url->link('account/rma_management', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_rma_detail'),
            'href' => isset($this->request->get['backUrlKey']) ? $this->url->link('account/rma_order_detail',
                '&rma_id=' . $this->request->get['rma_id'] . '&backUrlKey=' . $this->request->get['backUrlKey'], true) : 'javascript:void(0);'
        );
        $this->load->model('account/rma_management');
        // 获取RMA Order Info
        $this->getRmaOrderDetail($data);
        // 获取backUrl
        if (isset($this->request->get['backUrlKey'])) {
            $backUrlKey = $this->request->get['backUrlKey'];
            $data['backUrl'] = $this->cache->get($backUrlKey);
        }
        $data['continue'] = $this->url->link('account/account', '', true);
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['date'] = date("Y-m-d H:i:s", time());
        $data['currency'] = $this->session->get('currency');
        // 规定美国时间 2021-10-28 05:00:00 后申请的rma都是需要7天自动处理
        $data['isNewApplyRma'] = strtotime($data['rmaOrderInfo']->create_time) >= strtotime(configDB('new_rma_apply_date', '2021-10-28 05:00:00'));
        if ((strtotime($data['rmaOrderInfo']->create_time) + ($data['isNewApplyRma'] ? 7 : 14) * 24 * 3600) < time()) {
            $data['is_timeout'] = 1;
        }
        $data['rma_message'] = $this->load->controller('account/customerpartner/rma_management/rma_message', ['rma_no' => $data['rmaOrderInfo']->rma_order_id, 'ticket' => 0]);
        $this->response->setOutput($this->load->view('account/rma_management/rma_order_detail', $data));
    }

    private function getRmaOrderDetail(&$data)
    {
        if (isset($this->request->get['rma_id']) && trim($this->request->get['rma_id']) != '') {
            // 获取rma_id
            $rmaId = $this->request->get['rma_id'];
            // 根据RMA_ID获取RMA信息
            $this->load->model('account/rma_management');
            $this->load->model('catalog/product');
            $this->load->language('common/cwf');
            $this->load->model('tool/image');

            $rmaOrderInfo = $this->model_account_rma_management->getRmaInfoById($rmaId);
            $fromCustomerOrderId = $rmaOrderInfo->from_customer_order_id;
            $rmaOrderInfo->apply_refund_amount = $this->currency->format($rmaOrderInfo->apply_refund_amount, $this->session->data['currency']);
            $rmaOrderInfo->actual_refund_amount = $this->currency->format($rmaOrderInfo->apply_refund_amount, $this->session->data['currency']);
            //根据rma_id 获取协议的价格
            $data['rmaOrderInfo'] = $rmaOrderInfo;
            // 获取rmaStatus
            $data['rmaStatus'] = $this->model_account_rma_management->getRmaStatus();
            $data['b2b_order_url'] = $this->url->link('account/order/purchaseOrderInfo', '&order_id=' . $rmaOrderInfo->b2b_order_id);
            // 获取rmaProduct
            $rmaOrderProduct = $this->model_account_rma_management->getRmaOrderProduct($rmaId);
            $rmaOrderProduct[0]->apply_refund_amount = $this->currency->format($rmaOrderProduct[0]->apply_refund_amount, $this->session->data['currency']);
            if ($rmaOrderProduct[0]->actual_refund_amount != null) {
                $rmaOrderProduct[0]->actual_refund_amount = $this->currency->format($rmaOrderProduct[0]->actual_refund_amount, $this->session->data['currency']);
            }
            //判断是否是包销产品
            //$marginFlag = $this->model_account_rma_management->checkMarginProduct($rmaOrderProduct[0]->product_id);
            //$data['marginFlag'] = $marginFlag;
            //保证金产品需要的信息
            $margin_info = $this->model_account_rma_management->getMarginInfoByRmaId($rmaId);
            $future_margin_info = $this->model_account_rma_management->getFutureMarginInfoByRmaId($rmaId);
            //配件和超大件标识
            $tag_array = $this->model_catalog_product->getProductSpecificTag($rmaOrderProduct[0]->product_id);
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
            $data['rmaOrderProduct'] = $rmaOrderProduct[0];
            $data['rmaOrderProduct']->tag = $tags;
            $itemCode = $rmaOrderProduct[0]->item_code;
            if ($rmaOrderProduct[0]->rma_type == 1 || $rmaOrderProduct[0]->rma_type == 3) {
                // 获取重发单
                $reorder = $this->model_account_rma_management->getReorderByRmaId($rmaId);
                $data['reorder'] = $reorder;
                // 获取重发单明细
                $reorderLines = $this->model_account_rma_management->getReorderLineByReorderId($reorder->id);
                // 重发单combo 和tracking 处理
                $data['reorderLines'] = $this->model_account_rma_management->getReorderLinesTracking($reorderLines, $reorder->reorder_id);
            }
            // 获取订单状态
            $customer_order_status = $this->model_account_rma_management->getCustomerOrderStatus();
            $data['customer_order_status'] = $customer_order_status;
            $customer_order_item_status = $this->model_account_rma_management->getCustomerOrderItemStatus();
            $data['customer_order_item_status'] = $customer_order_item_status;
            // 根據customerOrderId和itemCode获取顾客订单
            $headerAndLineId = $this->model_account_rma_management->getHeaderAndLineId($fromCustomerOrderId, $itemCode, $this->customer->getId());
            // 判断有无强绑定关联
            $data['isEuropeCountry'] = $this->country->isEuropeCountry($this->customer->getCountryId());
            $orderProduct = $this->model_account_rma_management->getOrderProductById($rmaOrderProduct[0]->order_product_id, $data['isEuropeCountry']);
            //组织优惠券&活动
            $rmaOrderInfo->coupon_and_campaign = $orderProduct['coupon_amount'] + $orderProduct['campaign_amount'];
            $rmaOrderInfo->coupon_and_campaign_show = $this->currency->format($rmaOrderInfo->coupon_and_campaign, $this->session->data['currency']);
            if ($headerAndLineId) {
                $associated = $this->model_account_rma_management->getOrderAssociated(
                    $headerAndLineId['sales_order_id'], $itemCode, $this->customer->getId()
                    , $rmaOrderInfo->b2b_order_id, $rmaOrderInfo->seller_id
                );
                $data['associated'] = $associated;
                if ($associated) {
                    $orderProduct['quantity'] = $associated['qty'];
                    //如果是销售订单rma，展示的应该是销售订单rma数量和优惠信息 ps:下面注释的这行代码的逻辑是以RMA里面的金额为主，注释，不删除
                    //$rmaOrderInfo->coupon_and_campaign = $rmaOrderInfo->coupon_amount + $rmaOrderInfo->campaign_amount;
                    $rmaOrderInfo->coupon_and_campaign = $associated['coupon_amount'] + $associated['campaign_amount'];
                    $rmaOrderInfo->coupon_and_campaign_show = $this->currency->format($rmaOrderInfo->coupon_and_campaign, $this->session->data['currency']);
                }
                // 获取OrderProduct
                // total 计算
                //判断是否开启议价
                if (isset($orderProduct['quotePrice'])) {
                    $data['isQuote'] = true;
                    $orderProduct['quote'] = $this->currency->formatCurrencyPrice(-(double)($orderProduct['quotePrice']), $this->session->data['currency']);
                    $freight = $orderProduct['freight_per'] + $orderProduct['package_fee'];
                    $orderProduct['total'] = ($orderProduct['quantity'] * $orderProduct['price']) + ($orderProduct['service_fee_per'] * $orderProduct['quantity']) - ($orderProduct['quotePrice'] * $orderProduct['quantity']) + ($freight) * $orderProduct['quantity'];
                } else {
                    $orderProduct['quote'] = $this->currency->formatCurrencyPrice(0.00, $this->session->data['currency']);
                    $freight = $orderProduct['freight_per'] + $orderProduct['package_fee'];
                    $orderProduct['total'] = ($associated['qty'] * $orderProduct['price']) + ($orderProduct['service_fee_per'] * $associated['qty']) + ($freight) * $associated['qty'];
                }
                //判断是否有保证金合同的包销产品
                $orderProduct['service_fee_per'] = (double)$orderProduct['service_fee_per'] - $orderProduct['amount_service_fee_per'];
                $orderProduct['poundage'] = (double)$orderProduct['unit_poundage'] * $associated['qty'];

                //针对于保证金的total 需要加上保证金的头款
                if ($margin_info) {
                    $totalMargin = $orderProduct['total'] + $orderProduct['quantity'] * $margin_info['deposit_per'];
                    if ($rmaOrderInfo->coupon_and_campaign > 0) {
                        $totalMargin -= $rmaOrderInfo->coupon_and_campaign;
                    }
                    $margin_info['total_margin'] = $this->currency->formatCurrencyPrice($totalMargin, $this->session->data['currency']);
                }
                //针对于期货保证金的total 需要加上期货保证金的头款
                if ($future_margin_info) {
                    $totalFutureMargin = $orderProduct['total'] + $orderProduct['quantity'] * $future_margin_info['deposit_per'];
                    if ($rmaOrderInfo->coupon_and_campaign > 0) {
                        $totalFutureMargin -= $rmaOrderInfo->coupon_and_campaign;
                    }
                    $future_margin_info['total_future_margin'] = $this->currency->formatCurrencyPrice($totalFutureMargin, $this->session->data['currency']);
                }
                //活动 优惠券
                $orderProduct['total'] -= $rmaOrderInfo->coupon_and_campaign;
                $orderProduct['total'] = $this->currency->format($orderProduct['total'], $this->session->data['currency']);
                // 单价
                $orderProduct['before_format_price'] = $orderProduct['price'] - $orderProduct['amount_price_per'];
                $orderProduct['price'] = $this->currency->format($orderProduct['price'] - $orderProduct['amount_price_per'], $this->session->data['currency']);
                // 服务费
                $orderProduct['service_fee_per'] = $this->currency->format($orderProduct['service_fee_per'], $this->session->data['currency']);
                // 手续费
                $orderProduct['poundage'] = $this->currency->format($orderProduct['poundage'], $this->session->data['currency']);
                //运费
                if (isset($orderProduct['freight_difference_per']) && $orderProduct['freight_difference_per'] > 0) {
                    $orderProduct['freight_diff'] = true;
                    $orderProduct['tips_freight_difference_per'] = str_replace(
                        '_freight_difference_per_',
                        $this->currency->formatCurrencyPrice($orderProduct['freight_difference_per'], $this->session->data['currency']),
                        $this->language->get('tips_freight_difference_per')
                    );
                } else {
                    $orderDetail['freight_diff'] = false;
                }
                $orderProduct['freight'] = $this->currency->format($freight, $this->session->data['currency']);

            } else {

                $freight = $orderProduct['freight_per'] + $orderProduct['package_fee'];
                //判断是否开启议价
                if (isset($orderProduct['quotePrice'])) {
                    $data['isQuote'] = true;
                    $orderProduct['quote'] = $this->currency->formatCurrencyPrice(-(double)($orderProduct['quotePrice']), $this->session->data['currency']);
                    $quoteTotal = ($orderProduct['quantity'] * $orderProduct['price']) + ($orderProduct['service_fee_per'] * $orderProduct['quantity']) - ($orderProduct['quotePrice'] * $orderProduct['quantity']) + $freight * $orderProduct['quantity'];
                    ////针对于保证金的total 需要加上保证金的头款
                    if ($margin_info) {
                        $totalMargin = $quoteTotal + $orderProduct['quantity'] * $margin_info['deposit_per'];
                        if ($rmaOrderInfo->coupon_and_campaign > 0) {
                            $totalMargin -= $rmaOrderInfo->coupon_and_campaign;
                        }
                        $margin_info['total_margin'] = $this->currency->formatCurrencyPrice($totalMargin, $this->session->data['currency']);
                    }

                    //针对于期货保证金的total 需要加上期货保证金的头款
                    if ($future_margin_info) {
                        $totalFutureMargin = $quoteTotal + $orderProduct['quantity'] * $future_margin_info['deposit_per'];
                        if ($rmaOrderInfo->coupon_and_campaign > 0) {
                            $totalFutureMargin -= $rmaOrderInfo->coupon_and_campaign;
                        }
                        $future_margin_info['total_future_margin'] = $this->currency->formatCurrencyPrice($totalFutureMargin, $this->session->data['currency']);
                    }
                    $quoteTotal -= $rmaOrderInfo->coupon_and_campaign;
                    $orderProduct['total'] = $this->currency->formatCurrencyPrice($quoteTotal, $this->session->data['currency']);
                } else {
                    $quoteTotal = ($orderProduct['quantity'] * $orderProduct['price']) + ($orderProduct['service_fee_per'] * $orderProduct['quantity']) - ($orderProduct['quotePrice'] * $orderProduct['quantity']) + $freight * $orderProduct['quantity'];
                    //针对于保证金的total 需要加上保证金的头款
                    if ($margin_info) {
                        $totalMargin = $quoteTotal + $orderProduct['quantity'] * $margin_info['deposit_per'];
                        if ($rmaOrderInfo->coupon_and_campaign > 0) {
                            $totalMargin -= $rmaOrderInfo->coupon_and_campaign;
                        }
                        $margin_info['total_margin'] = $this->currency->formatCurrencyPrice($totalMargin, $this->session->data['currency']);
                    }

                    //针对于期货保证金的total 需要加上期货保证金的头款
                    if ($future_margin_info) {
                        $totalFutureMargin = $quoteTotal + $orderProduct['quantity'] * $future_margin_info['deposit_per'];
                        if ($rmaOrderInfo->coupon_and_campaign > 0) {
                            $totalFutureMargin -= $rmaOrderInfo->coupon_and_campaign;
                        }
                        $future_margin_info['total_future_margin'] = $this->currency->formatCurrencyPrice($totalFutureMargin, $this->session->data['currency']);
                    }
                    $quoteTotal -= $rmaOrderInfo->coupon_and_campaign;
                    $orderProduct['total'] = $this->currency->format($quoteTotal, $this->session->data['currency']);
                }
                // 单价
                $orderProduct['before_format_price'] = $orderProduct['price'] - $orderProduct['amount_price_per'];
                $orderProduct['price'] = $this->currency->format($orderProduct['price'] - $orderProduct['amount_price_per'], $this->session->data['currency']);
                // 服务费
                $orderProduct['service_fee_per'] = $this->currency->format($orderProduct['service_fee_per'] - $orderProduct['amount_service_fee_per'], $this->session->data['currency']);
                // 手续费
                $orderProduct['poundage'] = $this->currency->format($orderProduct['unit_poundage'], $this->session->data['currency']);
                //运费
                if (isset($orderProduct['freight_difference_per']) && $orderProduct['freight_difference_per'] > 0) {
                    $orderProduct['freight_diff'] = true;
                    $orderProduct['tips_freight_difference_per'] = str_replace(
                        '_freight_difference_per_',
                        $this->currency->formatCurrencyPrice($orderProduct['freight_difference_per'], $this->session->data['currency']),
                        $this->language->get('tips_freight_difference_per')
                    );
                } else {
                    $orderDetail['freight_diff'] = false;
                }
                $orderProduct['freight'] = $this->currency->format($freight, $this->session->data['currency']);
            }
            if ($margin_info || $future_margin_info) {
                //$discountAmount = $rmaOrderInfo->coupon_amount + $rmaOrderInfo->campaign_amount; //不删除,留着 2种计算方式，可能还需要除rma数量，依旧会有误差
                $qty = isset($data['associated']) && !empty($data['associated']) ? $data['associated']['qty'] : $orderProduct['quantity'];
                $qty = $qty <= 0 ? 1 : $qty;
                $discountAmount = customer()->isJapan() ? (int)($rmaOrderInfo->coupon_and_campaign / $qty) : round($rmaOrderInfo->coupon_and_campaign / $qty, 2);
                $orderProduct['price'] = $this->currency->format($orderProduct['before_format_price'] - $discountAmount, $this->session->data['currency']);
            }
            $data['margin_info'] = $margin_info;
            $data['future_margin_info'] = $future_margin_info;
            $data['orderProduct'] = $orderProduct;

            $isEurope = $this->country->isEuropeCountry($this->customer->getCountryId());
            $data['isEurope'] = $isEurope;
            // 获取rmaReason
            $rmaReason = $this->model_account_rma_management->getRmaReasonById($rmaOrderProduct[0]->reason_id);
            $data['rmaReason'] = $rmaReason;
            // 获取附件
            $rmaOrderFiles = $this->model_account_rma_management->getRmaOrderFile($rmaId, 1);
            // 遍历文件
            $rmaFiles = array();
            foreach ($rmaOrderFiles as $rmaOrderFile) {
                $imageUrl = StorageCloud::rmaFile()->getUrl($rmaOrderFile->file_path);
                $isImg = $this->isImg($imageUrl);
                $rmaFiles[] = [
                    'isImg' => $isImg,
                    'imageUrl' => $isImg ? $imageUrl : null,
                    'id' => $rmaOrderFile->id,
                    'file_name' => $rmaOrderFile->file_name,
                    'download' => $this->url->link('account/rma_order_detail/download', '&rmaFileId=' . $rmaOrderFile->id, true)
                ];
            }
            $data['rmaFiles'] = $rmaFiles;
            // 获取Seller附件
            $sellerRmaOrderFiles = $this->model_account_rma_management->getRmaOrderFile($rmaId, 2);
            // 遍历文件
            $sellerRmaFiles = array();
            foreach ($sellerRmaOrderFiles as $sellerRmaOrderFile) {
                $fileName = StorageCloud::rmaFile()->getUrl($sellerRmaOrderFile->file_path);
                $isImg = $this->isImg($fileName);
                $imageUrl = null;
                $sellerRmaFiles[] = array(
                    'isImg' => $isImg,
                    'imageUrl' => $isImg ? $imageUrl : null,
                    'id' => $sellerRmaOrderFile->id,
                    'file_name' => $sellerRmaOrderFile->file_name,
                    'download' => $this->url->link('account/rma_order_detail/download', '&rmaFileId=' . $sellerRmaOrderFile->id)
                );
            }
            $data['sellerRmaFiles'] = $sellerRmaFiles;
            // fee order
            $data['feeOrder'] = app(RamRepository::class)->getFeeOrder($rmaId);
        }
    }

    private function isImg($fileName)
    {
        // 考虑到存储到oss的问题 这里的方法需要变动
        return (bool)preg_match('/.*(\.png|\.jpg|\.jpeg|\.gif)$/', $fileName);
    }

    /**
     * 下载订单模板文件
     */
    public function download()
    {
        $this->load->model('account/rma_management');
        // 获取RMA文件信息
        $rmaOrderFile = $this->model_account_rma_management->getRmaOrderFileById(request('rmaFileId', 0));
        if ($rmaOrderFile) {
            return StorageCloud::rmaFile()->browserDownload($rmaOrderFile->file_path);
        }
        return $this->redirect('error/not_found');
    }
}
