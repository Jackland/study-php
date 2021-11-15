<?php

use App\Components\Locker;
use App\Components\Storage\StorageCloud;
use App\Helper\MoneyHelper;
use App\Logging\Logger;

use App\Repositories\Rebate\RebateRepository;
use App\Repositories\Rma\RamRepository;
use App\Models\Order\OrderProduct;

use App\Repositories\Marketing\CampaignRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class ControllerAccountRmapurchaseorderrma
 *
 * @property ModelAccountNotification $model_account_notification
 * @property ModelAccountRmaManagement $model_account_rma_management
 * @property ModelAccountOrder $model_account_order
 * @property ModelAccountReturn $model_account_return
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCustomerpartnerRmaManagement $model_customerpartner_rma_management
 * @property ModelMessageMessage $model_message_message
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountRmapurchaseorderrma extends Controller {

    private $error = array();

    public function add() {
        if ($this->config->get('wk_rma_status')) {
            $url = '';
            if (isset($this->request->get['order_id']) && $this->request->get['order_id']) {
                $url .= '&order_id=' . (int)$this->request->get['order_id'];
            }
            if (isset($this->request->get['product_id']) && $this->request->get['product_id']) {
                $url .= '&product_id=' . (int)$this->request->get['product_id'];
            }
            $this->response->redirect($this->url->link('account/rma/addrma', $url, true));
        }

        $this->load->language('account/return');

        $this->load->model('account/return');
        $this->load->model('tool/image');

        if ((request()->isMethod('POST')) && $this->validate()) {
            $this->model_account_return->addReturn($this->request->post);

            $this->response->redirect($this->url->link('account/return/success', '', true));
        }

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );
        $data['breadcrumbs'][] = array(
            'text' => 'RMA Management',
            'href' => $this->url->link('account/rma_management', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('account/rma/purchaseorderrma/add&order_id=' . (int)$this->request->get['order_id'].'&product_id=' . (int)$this->request->get['product_id'], '', true)
        );
        $this->load->model('account/order');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $this->load->model('catalog/product');

        if (isset($this->request->get['product_id'])) {
            $product_info = $this->model_catalog_product->getProduct($this->request->get['product_id']);
        }


        if (isset($this->request->post['image'])) {
            $data['image'] = $this->request->post['image'];
        } elseif (!empty($product_info)) {
            $data['image'] = 'image/'.$product_info['image'];
        } else {
            $data['image'] = '';
        }

        if (isset($this->request->post['sku'])) {
            $data['sku'] = $this->request->post['sku'];
        } elseif (!empty($product_info)) {
            $data['sku'] = $product_info['sku'];
        } else {
            $data['sku'] = '';
        }

        if (isset($this->request->get['order_id'])&&isset($this->request->get['product_id'])) {
            //增加了type id 和 agreement_id
            $order_detail = $this->model_account_order->getOrderDetail($this->request->get['order_id'],$this->request->get['product_id']);
            $data['order_detail'] = $order_detail;
        }

        if (isset($this->request->get['order_id'])) {
            $rma_history = $this->model_account_order->getRmaHistories($this->request->get['order_id'],$this->request->get['product_id']);
            $data['rmaHistories'] = $rma_history;
        }

        //获取未绑定的采购订单
        if (isset($this->request->get['order_id'])&&isset($this->request->get['product_id'])) {
            $data['isEuropeCountry'] = $this->country->isEuropeCountry($this->customer->getCountryId());
            if($data['isEuropeCountry']){
                $noBindingInfo = $this->model_account_order->getNoBindingOrderInfoEurope($this->request->get['order_id'],$this->request->get['product_id']);
                $unit_price = $noBindingInfo['price']-$noBindingInfo['amount_price_per'];
                $service_fee_per = $noBindingInfo['service_fee_per']-$noBindingInfo['amount_service_fee_per'];
                $freight = $noBindingInfo['freight_per']+$noBindingInfo['package_fee'];
                $noBindingInfo['total'] = $noBindingInfo['nobindingQty']*($unit_price+$service_fee_per+$freight);
                $noBindingInfo['allTotalMoney'] = $noBindingInfo['allQty']*($unit_price+$service_fee_per+$freight);
                $noBindingInfo['quoteCur'] = $this->currency->formatCurrencyPrice((double)(-$noBindingInfo['quoteAmount']), $this->session->data['currency']);
                $data['quote'] = (double)($noBindingInfo['quoteAmount']) == 0? false:true;
            }else {
                $noBindingInfo = $this->model_account_order->getNoBindingOrderInfo($this->request->get['order_id'],$this->request->get['product_id']);
                $unit_price = $noBindingInfo['price'];
                $service_fee_per = 0.00;
                $freight = $noBindingInfo['freight_per']+$noBindingInfo['package_fee'];
                $noBindingInfo['total'] = $noBindingInfo['nobindingQty']*($unit_price+$freight);
                $noBindingInfo['allTotalMoney'] = $noBindingInfo['allQty']*($unit_price+$service_fee_per+$freight);
            }
            $priceCur = $this->currency->format((double)($unit_price), $this->session->data['currency']);
            $totalCur = $this->currency->format( $noBindingInfo['total'],session('currency'));
            $noBindingInfo['service_feeCur'] = $this->currency->format((double)($service_fee_per), $this->session->data['currency']);
            $noBindingInfo['poundageCur'] = $this->currency->format((double)($noBindingInfo['poundage']), $this->session->data['currency']);
            $noBindingInfo['totalCur'] = $totalCur;
            $noBindingInfo['priceCur'] = $priceCur;
            $noBindingInfo['freight'] = $this->currency->formatCurrencyPrice($freight,session('currency'));
            $data['noBinding'] = $noBindingInfo;
        }
        //获取采购订单的绑定数据
        if (isset($this->request->get['order_id'])&&isset($this->request->get['product_id'])) {
            $bindingInfo = $this->model_account_order->getBindingSalesOrder($this->request->get['order_id'],$this->request->get['product_id']);
            $data['bindingInfos'] = $bindingInfo;
            $tag_array = $this->model_catalog_product->getTag($this->request->get['product_id']);
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
            $data['tag'] = $tags;
        }

        //获取采购订单退货理由
        $this->load->model('localisation/return_reason');

        $data['rmaReason'] = $this->model_account_order->getRmaReason();
        //获取交易币制
        $symbolLeft  = $this->currency->getSymbolLeft($this->session->data['currency']);
        $symbolRight  = $this->currency->getSymbolRight($this->session->data['currency']);
        $data['currency'] = $symbolLeft.$symbolRight;
        $data['country'] = session('currency');
        if (isset($this->request->post['comment'])) {
            $data['comment'] = $this->request->post['comment'];
        } else {
            $data['comment'] = '';
        }

        $data['back'] = $this->url->link('account/account', '', true);

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        //判断返点
        $this->load->model('customerpartner/rma_management');
        $rebateInfo = $this->model_customerpartner_rma_management->getRebateInfo($this->request->get['order_id'],$this->request->get['product_id']);
        //查看返点协议申请情况
        $maxRefundMoney = $noBindingInfo['total'];
        $data['unit_refund'] = $noBindingInfo['total']/$noBindingInfo['nobindingQty'];
        if(!empty($rebateInfo)) {
            $rebateRequestInfo = $this->model_customerpartner_rma_management->getRebateRequestInfo($rebateInfo['id']);
            //判断该RMA申请是否在返点协议里(判断该订单之前的返点数量满不满足返点协议)
            $beforeQty = $this->model_customerpartner_rma_management->getRebateOrderBefore($this->request->get['order_id'],$this->request->get['product_id'],$rebateInfo['id']);
            $orderQty = $this->model_customerpartner_rma_management->getRebateQty($this->request->get['order_id'],$this->request->get['product_id'],$rebateInfo['id']);
            if (in_array($rebateInfo['rebate_result'], [1, 2])) {
                //正在生效的返点协议
                $data['tip_rebate_refund'] = sprintf($this->language->get('tip_rebate'), $this->currency->formatCurrencyPrice($maxRefundMoney, $this->session->data['currency']));
                $data['msg_rebate_refund'] = $this->language->get('msg_rebate_process');
            } else if ($rebateInfo['rebate_result'] == 7) {
                /*
                 * 6.协议申请中
                 * 7.协议到期 request完成
                 * 8.协议到期 request拒绝
                 */
                //该产品参与的返点协议已到期并达成，Seller已经同意返点给Buyer
                if($beforeQty>= $rebateInfo['rebateQty']){
                    //该订单前的可参加返点协议的数量已经大于返点熟练，全款退,无需提示


                }else if($beforeQty<$rebateInfo['rebateQty'] && $orderQty<=($rebateInfo['rebateQty']-$beforeQty)){
                    //该订单全在返点协议里,需扣除返点金额
                    $hasRebateMoney = $rebateInfo['rebate_amount'] *  $orderQty;
                    $orderTotalMoneyCurr = $this->currency->formatCurrencyPrice($maxRefundMoney/$noBindingInfo['nobindingQty']*$orderQty, $this->session->data['currency']);
                    $maxRefundMoney = $maxRefundMoney - $hasRebateMoney/$orderQty*$noBindingInfo['nobindingQty'];
                    $data['unit_refund'] = $maxRefundMoney/ $noBindingInfo['nobindingQty'];
                    $maxRefundMoneyCurr = $this->currency->formatCurrencyPrice($maxRefundMoney, $this->session->data['currency']);
                    $unitRefundCurr = $this->currency->formatCurrencyPrice($maxRefundMoney/ $noBindingInfo['nobindingQty'], $this->session->data['currency']);
                    $hasRebateMoneyCurr = $this->currency->formatCurrencyPrice($hasRebateMoney, $this->session->data['currency']);
                    $data['tip_rebate_refund'] = sprintf($this->language->get('tip_rebate_over'), $unitRefundCurr);
                    $data['msg_rebate_refund'] = sprintf($this->language->get('msg_rebate_over'), $orderQty,$orderTotalMoneyCurr,$orderQty,
                        $hasRebateMoneyCurr,$unitRefundCurr);
                }else if ($beforeQty<$rebateInfo['rebateQty'] && $orderQty>($rebateInfo['rebateQty']-$beforeQty)){
                    //该订单部分算在返点协议里
                    $hasRebateMoney = round($rebateInfo['rebate_amount']*($rebateInfo['rebateQty']-$beforeQty),2);
                    $maxRefundMoney = $noBindingInfo['allTotalMoney'] - $hasRebateMoney;
                    $data['unit_refund'] = $maxRefundMoney/$noBindingInfo['allQty'];
                    $unitRefundCurr = $this->currency->formatCurrencyPrice($maxRefundMoney/$noBindingInfo['allQty'], $this->session->data['currency']);
                    $maxRefundMoneyCurr = $this->currency->formatCurrencyPrice($maxRefundMoney, $this->session->data['currency']);
                    $hasRebateMoneyCurr = $this->currency->formatCurrencyPrice($hasRebateMoney, $this->session->data['currency']);
                    $orderTotalMoneyCurr = $this->currency->formatCurrencyPrice($noBindingInfo['allTotalMoney'], $this->session->data['currency']);
                    $data['tip_rebate_refund'] = sprintf($this->language->get('tip_rebate_over'), $unitRefundCurr);
                    $data['msg_rebate_refund'] = sprintf($this->language->get('msg_rebate_over'), $orderQty,$orderTotalMoneyCurr,$rebateInfo['rebateQty']-$beforeQty,
                        $hasRebateMoneyCurr,$unitRefundCurr);
                }
            } else if ($rebateInfo['rebate_result'] == 5 && empty($rebateRequestInfo)) {
                //该产品参与的返点协议已到期并达成，但Buyer还没有申请返点
                $maxRefundMoneyCurr = $this->currency->formatCurrencyPrice($maxRefundMoney, $this->session->data['currency']);
                $needRebateMoney = $rebateInfo['rebate_amount'] *max(($rebateInfo['rebateQty']-$beforeQty),0);
                $needRebateMoneyCurr = $this->currency->formatCurrencyPrice($needRebateMoney, $this->session->data['currency']);
                $data['tip_rebate_refund'] = sprintf($this->language->get('tip_rebate'), $maxRefundMoneyCurr);
                $maxRefundMoney = $maxRefundMoney - $needRebateMoney;
                $maxRefundMoneyCurr = $this->currency->formatCurrencyPrice($maxRefundMoney, $this->session->data['currency']);
                $data['msg_rebate_refund'] = sprintf($this->language->get('msg_rebate_no_request'), $needRebateMoneyCurr, $maxRefundMoneyCurr);
            } else if ($rebateInfo['rebate_result'] == 6) {
                //该产品参与的返点协议已到期并达成，Buyer申请了返点但Seller还没有同意
                $maxRefundMoneyCurr = $this->currency->formatCurrencyPrice($maxRefundMoney, $this->session->data['currency']);
                $needRebateMoney = $rebateInfo['rebate_amount'] * max(($rebateInfo['rebateQty']-$beforeQty),0);
                $needRebateMoneyCurr = $this->currency->formatCurrencyPrice($needRebateMoney, $this->session->data['currency']);
                $data['tip_rebate_refund'] = sprintf($this->language->get('tip_rebate'), $maxRefundMoneyCurr);
                $maxRefundMoney = $maxRefundMoney - $needRebateMoney;
                $maxRefundMoneyCurr = $this->currency->formatCurrencyPrice($maxRefundMoney, $this->session->data['currency']);
                $data['msg_rebate_refund'] = sprintf($this->language->get('msg_rebate_request'), $needRebateMoneyCurr, $maxRefundMoneyCurr);
            } else if ($rebateInfo['rebate_result'] == 8) {
                //该产品参与的返点协议已到期并达成，Buyer申请了返点但Seller拒绝了
                $maxRefundMoneyCurr = $this->currency->formatCurrencyPrice($maxRefundMoney, $this->session->data['currency']);
                $needRebateMoney = $rebateInfo['rebate_amount'] * max(($rebateInfo['rebateQty']-$beforeQty),0);
                $needRebateMoneyCurr = $this->currency->formatCurrencyPrice($needRebateMoney, $this->session->data['currency']);
                $data['tip_rebate_refund'] = sprintf($this->language->get('tip_rebate'), $maxRefundMoneyCurr);
                $maxRefundMoney = $maxRefundMoney - $needRebateMoney;
                $maxRefundMoneyCurr = $this->currency->formatCurrencyPrice($maxRefundMoney, $this->session->data['currency']);
                $data['msg_rebate_refund'] = sprintf($this->language->get('msg_rebate_request_reject'), $needRebateMoneyCurr, $maxRefundMoneyCurr);
            }
        }
        $this->response->setOutput($this->load->view('account/rma_management/return_form', $data));
    }

    public function saveRmaRequest()
    {
        $this->load->model('account/rma_management');
        $this->load->language('account/rma_management');
        $result = [];
        // 默认采用redis锁方式
        $lock = Locker::rma('rmaLock');
        if (!$lock->acquire(true)) { // 采用阻塞方式
            $result = ['error' => [['errorMsg' => 'Operation failed.']]];
            goto end;
        }
        $checkData = $this->checkRmaData();
        if (count($checkData['error']) > 0) {
            $result = ['error' => $checkData['error']];
            goto end;
        }
        $returnQty = $this->request->post('returnQty');
        $returnableQty = $this->request->post('returnableQty');
        $productId = $this->request->post('productId');
        $orderProductId = $this->request->post('orderProductId');
        $orderProductInfo = OrderProduct::find($orderProductId);
        if (($returnQty > $returnableQty) || ($returnQty > $orderProductInfo->quantity)) {
            $result = ['error' => [['errorMsg' => 'Operation failed.']]];
            goto end;
        }
        $splitCampaignAmount = $splitCouponAmount = 0; //初始值
        //活动
        if ($orderProductInfo->campaign_amount > 0 || $orderProductInfo->coupon_amount > 0) {
            $takedDiscount = app(CampaignRepository::class)->calculateCampaignAndCouponTakedAmount($orderProductInfo->order_id,
                $orderProductId, $productId);

            if ($orderProductInfo->campaign_amount > 0) {
                if ($returnQty < $returnableQty) {
                    $splitCampaignAmount = $returnQty * MoneyHelper::averageAmountFloor($orderProductInfo->campaign_amount,
                            $orderProductInfo->quantity, $this->customer->isJapan() ? 0 : 2);
                } else {
                    $splitCampaignAmount = max($orderProductInfo->campaign_amount - $takedDiscount['all_taken_campaign_discount'], 0);
                }
            }
            //优惠券
            if ($orderProductInfo->coupon_amount > 0) {
                if ($returnQty < $returnableQty) {
                    $splitCouponAmount = $returnQty * MoneyHelper::averageAmountFloor($orderProductInfo->coupon_amount,
                            $orderProductInfo->quantity, $this->customer->isJapan() ? 0 : 2);
                } else {
                    $splitCouponAmount = max($orderProductInfo->coupon_amount - $takedDiscount['all_taken_coupon_discount'], 0);
                }
            }
        }
        $connection = $this->orm->getConnection();
        try {
            $connection->beginTransaction();
            define("DEFAULT_RMA_STATUS_ID", 1);
            // 1. 获取itemCode,orderId,sellerId,rmaQty,reason,comments
            $rmaIdTemp = $this->model_account_rma_management->getRmaIdTemp();
            $rmaOrderId = $rmaIdTemp->rma_order_id;
            $itemCode = trim($this->request->post['itemCode']);
            $orderId = (int)$this->request->post['orderId'];
            $buyerId = (int)$this->request->post['buyerId'];
            $sellerId = (int)$this->request->post['sellerId'];
            $rmaQty = (int)$this->request->post['returnQty'];
            $asin = null;
            $reason = $this->request->post['rmaReason'] == '' ? null : (int)$this->request->post['rmaReason'];
            $comments = $this->request->post['comments'];
            // 2. 插入oc_yzc_rma_order
            $rmaOrder = array(
                "rma_order_id" => $rmaOrderId,
                "order_id" => $orderId,
                "from_customer_order_id" => null,
                "seller_id" => $sellerId,
                "buyer_id" => $buyerId,
                "admin_status" => null,
                "seller_status" => DEFAULT_RMA_STATUS_ID,
                "cancel_rma" => false,
                "solve_rma" => false,
                "create_user_name" => $buyerId,
                "order_type" => 2
            );
            $rmaOrder = $this->model_account_rma_management->addRmaOrder($rmaOrder);
            $rmaId = $rmaOrder->id;
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

                        //迁移rma 附件到oss
                        StorageCloud::rmaFile()->writeFile($file, $buyerId, $filename);

                        // 插入文件数据
                        $rmaFile = array(
                            "rma_id" => $rmaId,
                            'file_name' => $file->getClientOriginalName(),
                            'size' => $file->getSize(),
                            "file_path" => $buyerId . "/" . $filename,
                            "buyer_id" => $buyerId
                        );
                        $this->model_account_rma_management->addRmaFile($rmaFile);
                    }
                }
            }
            // 4.插入RMA明细数据，oc_yzc_rma_order_product
            //采购订单退货仅退款
            $rmaType = 2;
            // 申请退款金额
            $refundAmount = null;
            $refundAmount = (float)$this->request->post['refund'];
            $product_id = isset($this->request->post['productId']) ? $this->request->post['productId'] : null;
            $orderProductId = isset($this->request->post['orderProductId']) ? $this->request->post['orderProductId'] : null;
            $rmaOrderProduct = array(
                "rma_id" => $rmaId,
                "product_id" => $product_id,
                "item_code" => $itemCode,
                "quantity" => $rmaQty,
                "reason_id" => $reason,
                'asin' => $asin,
                'damage_qty' => 0,
                "order_product_id" => $orderProductId,
                "comments" => $comments,
                "rma_type" => $rmaType,
                "apply_refund_amount" => $refundAmount,
                'coupon_amount' => $splitCouponAmount,
                'campaign_amount' => $splitCampaignAmount,
            );
            $this->model_account_rma_management->addRmaOrderProduct($rmaOrderProduct);
            $this->rmaCommunication($rmaId);
            $connection->commit();
        } catch (Exception $e) {
            $connection->rollBack();
            $result['error'] = [['errorMsg' => $e->getMessage()]];
            Logger::error($e);
        }
        end:
        $lock->release();
        return $this->response->json($result);
    }

    private function checkRmaData()
    {
        $this->load->model('account/order');
        $error = array();
        if(!empty($this->request->post['returnableQty'])){
            $returnableQty = $this->request->post['returnableQty'];
        }else{
            $error[]=array(
                "displayType" => 1,
                "errorMsg" => 'Returnable Quanity cannot empty.'
            );
        }
        if(isset($this->request->post['total'])){
            $total = $this->request->post['total'];
        }else{
            $error[]=array(
                "displayType" => 1,
                "errorMsg" => 'Total cannot empty.'
            );
        }
        if($this->request->post['comments'] != ''){
            if(strlen($this->request->post['comments'])>3000){
                $error[]=array(
                    "id" => 'comments',
                    "href" => 'comments',
                    "displayType" => 1,
                    "errorMsg" => 'Comments must be between 1 and 3000 characters!'
                );
            }
        }else{
            $error[]=array(
                "id" => 'comments',
                "href" => 'comments',
                "displayType" => 1,
                "errorMsg" => 'Comments cannot be left blank.'
            );
        }
        if(!empty($this->request->post['returnQty'])){
            $returnQty = $this->request->post['returnQty'];
            $product_id = isset($this->request->post['productId'])?$this->request->post['productId']:null;
            $orderId = (int)$this->request->post['orderId'];
            if($this->country->isEuropeCountry($this->customer->getCountryId())){
                $noBindingInfo = $this->model_account_order->getNoBindingOrderInfoEurope($orderId, $product_id);
                $noBindingInfo['total'] = (int)$noBindingInfo['nobindingQty']*($noBindingInfo['price']+$noBindingInfo['service_fee_per']+$noBindingInfo['freight_per']+$noBindingInfo['package_fee'])-(double)$noBindingInfo['quoteAmount'];
            }else {
                $noBindingInfo = $this->model_account_order->getNoBindingOrderInfo($orderId, $product_id);
                $noBindingInfo['total'] = (int)$noBindingInfo['nobindingQty']*($noBindingInfo['price']+$noBindingInfo['service_fee_per']+$noBindingInfo['freight_per']+$noBindingInfo['package_fee']);
            }
            if($returnQty>$noBindingInfo['nobindingQty']){
                $error[]=array(
                    "id" => 'returnQty',
                    "href" => 'returnQty',
                    "displayType" => 1,
                    "errorMsg" => 'Return Quantity cannot more than Returnable Quantity.'
                );
            }
            if (isset($this->request->post['refund']) && $this->request->post['refund'] != "") {
                $refund = $this->request->post['refund'];
                if ($refund > (string)($noBindingInfo['total']*1000)/1000) {
                    $error[] = array(
                        "id"          => 'refund',
                        "href"        => 'refund',
                        "displayType" => 1,
                        "errorMsg"    => 'Refund cannot more than Total.'
                    );
                }
            } else {
                $error[] = array(
                    "id"          => 'refund',
                    "href"        => 'refund',
                    "displayType" => 1,
                    "errorMsg"    => 'Refund can not be left blank.'
                );
            }
        }else{
            $error[]=array(
                "id" => 'returnQty',
                "href" => 'returnQty',
                "displayType" => 1,
                "errorMsg" => 'Return Quantity cannot be left blank.'
            );
        }
        if(empty($this->request->post['rmaReason'])){
            $error[]=array(
                "id" => 'rmaReason',
                "href" => 'rmaReason',
                "displayType" => 1,
                "errorMsg" => 'Reason cannot be left blank.'
            );
        }
        $result = array(
            'error' => $error
        );
        return $result;
    }

    private function rmaCommunication($rmaId){
        $this->load->model('account/notification');
        $this->load->model('customerpartner/rma_management');
        /** @var ModelAccountNotification $modelAccountNotification */
        $modelAccountNotification = $this->model_account_notification;
        // 消息提醒
        //$modelAccountNotification->addRmaActivity($rmaId);
        // 站内信
        $communicationInfo = $this->model_customerpartner_rma_management->getCommunicationInfoOrm($rmaId);
        if(!empty($communicationInfo)){
            $subject = 'Purchase Order RMA Request (RMA ID:' . $communicationInfo->rma_order_id . ')';
            $message ='<table   border="0" cellspacing="0" cellpadding="0" >';
            $message .='<tr><th align="left">RMA ID:</th><td>'.$communicationInfo->rma_order_id.'</td></tr> ';
            $message .='<tr><th align="left">Order ID:</th><td>'.$communicationInfo->order_id.'</td></tr>';
            $message .='<tr><th align="left">MPN:</th><td>'.$communicationInfo->mpn.'</td></tr>';
            $message .='<tr><th align="left">Item Code:</th><td>'.$communicationInfo->sku.'</td></tr>';
            if($communicationInfo->rma_type==1){
                $message .='<tr><th align="left">Applied for Reshipment：</th><td>Yes</td></tr>';
            }else if($communicationInfo->rma_type==2){
                $message .='<tr><th align="left">Applied for Refund：</th><td>Yes</td></tr>';
            }else if($communicationInfo->rma_type==3){
                $message .='<tr><th align="left">Applied for Reshipment：</th><td>Yes</td></tr>';
                $message .='<tr><th align="left">Applied for Refund：</th><td>Yes</td></tr>';
            }
            $message .= '</table>';

//            $this->communication->saveCommunication($subject,$message,$communicationInfo->seller_id,$communicationInfo->buyer_id, 0);

            $this->load->model('message/message');
            // 6774 修改为批量
            $receiverIds[] = $communicationInfo->seller_id;
            if ($communicationInfo->original_seller_id) {//如果seller_id是包销店铺，也给原店铺发一条消息提醒
                $receiverIds[] = $communicationInfo->original_seller_id;
            }
            $this->model_message_message->addSystemMessageToBuyer('rma', $subject, $message, $receiverIds);
        }
    }

    public function editRma(){
        // 判断用户是否登录
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/rma_management', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $this->load->language('account/return');

        $this->load->model('account/return');

        if (isset($this->request->get['rma_order_id']) && $this->request->get['rma_order_id']) {
            $rma_order_id = (int)$this->request->get['rma_order_id'];
        }else{
            $this->response->redirect($this->url->link('account/order', '', true));
        }


        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );
        $data['breadcrumbs'][] = array(
            'text' => 'RMA Management',
            'href' => $this->url->link('account/rma_management', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('account/rma/purchaseorderrma/editRma&rma_order_id=' .$rma_order_id, '', true)
        );

        $this->load->model('account/order');
        $this->load->model('tool/image');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $this->load->model('catalog/product');
        //根据rma_order_id获取
        $rmaInfo = $this->model_account_return->getOrderIdAndProductIdByRmaOrderId($rma_order_id);
        $data['return_qty'] = $rmaInfo->quantity;
        $data['refund_money'] = $rmaInfo->apply_refund_amount;
        $data['reason_id'] = $rmaInfo->reason_id;
        $data['comments'] = $rmaInfo->comments;
        $data['rmaOrderId'] =$rma_order_id;
        $data['rmaId'] =$rmaInfo->id;
        $product_info = $this->model_catalog_product->getProduct($rmaInfo->product_id);


        if (isset($this->request->post['image'])) {
            $data['image'] = $this->request->post['image'];
        } elseif (!empty($product_info)) {
            $data['image'] = 'image/'.$product_info['image'];
        } else {
            $data['image'] = '';
        }

        if (isset($this->request->post['sku'])) {
            $data['sku'] = $this->request->post['sku'];
        } elseif (!empty($product_info)) {
            $data['sku'] = $product_info['sku'];
        } else {
            $data['sku'] = '';
        }

        $order_detail = $this->model_account_order->getOrderDetail($rmaInfo->order_id,$rmaInfo->product_id);
        $data['order_detail'] = $order_detail;

            $rma_history = $this->model_account_order->getRmaHistories($rmaInfo->order_id,$rmaInfo->product_id);
            $data['rmaHistories'] = $rma_history;

        //获取未绑定的采购订单
        $data['isEuropeCountry'] = $this->country->isEuropeCountry($this->customer->getCountryId());
        if($data['isEuropeCountry']){
            $noBindingInfo = $this->model_account_order->getNoBindingOrderInfoEurope($rmaInfo->order_id,$rmaInfo->product_id,$rmaInfo->id);
            $unit_price = $noBindingInfo['price']-$noBindingInfo['amount_price_per'];
            $service_fee_per = $noBindingInfo['service_fee_per']-$noBindingInfo['amount_service_fee_per'];
            $freight = $noBindingInfo['freight_per']+$noBindingInfo['package_fee'];
            $noBindingInfo['total'] = $noBindingInfo['nobindingQty']*($unit_price+$service_fee_per+$freight);
            $noBindingInfo['quoteCur'] = $this->currency->formatCurrencyPrice((double)(-$noBindingInfo['quoteAmount']), $this->session->data['currency']);
            $data['quote'] = (double)($noBindingInfo['quoteAmount']) == 0? false:true;
            $noBindingInfo['allTotalMoney'] = $noBindingInfo['allQty']*($unit_price+$service_fee_per+$freight);
        }else {
            $noBindingInfo = $this->model_account_order->getNoBindingOrderInfo($rmaInfo->order_id,$rmaInfo->product_id,$rmaInfo->id);
            $unit_price = $noBindingInfo['price'];
            $service_fee_per = 0.00;
            $freight = $noBindingInfo['freight_per']+$noBindingInfo['package_fee'];
            $noBindingInfo['total'] = $noBindingInfo['nobindingQty']*($unit_price+$freight);
            $noBindingInfo['allTotalMoney'] = $noBindingInfo['allQty']*($unit_price+$service_fee_per+$freight);
        }
        $priceCur = $this->currency->format((double)($unit_price), $this->session->data['currency']);
        $totalCur = $this->currency->format( $noBindingInfo['total'],session('currency'));
        $noBindingInfo['service_feeCur'] = $this->currency->format((double)($service_fee_per), $this->session->data['currency']);
        $noBindingInfo['poundageCur'] = $this->currency->format((double)($noBindingInfo['poundage']), $this->session->data['currency']);
        $noBindingInfo['totalCur'] = $totalCur;
        $noBindingInfo['priceCur'] = $priceCur;
        $noBindingInfo['freight'] = $this->currency->formatCurrencyPrice($freight,session('currency'));
        //获取采购订单的绑定数据
        $bindingInfo = $this->model_account_order->getBindingSalesOrder($rmaInfo->order_id,$rmaInfo->product_id);
        $tag_array = $this->model_catalog_product->getTag($rmaInfo->product_id);
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
        $data['tag'] = $tags;

        $orderProductInfo = OrderProduct::find($rmaInfo->order_product_id);
        $lastCouponAmount = $lastCampaignAmount = 0;
        $noBindingInfo['couponAndCampaignAmount'] = 0;
        $noBindingInfo['couponAndCampaignAmountShow'] = '';
        if ($orderProductInfo->coupon_amount > 0 || $orderProductInfo->campaign_amount > 0) {
            //满减
            $alreadyBindDiscount = app(RamRepository::class)->getSalesOrderBindInfo($rmaInfo->order_id, $rmaInfo->product_id);
            $phurseRmaDiscount = app(RamRepository::class)->getPhurseOrderRmaInfo($rmaInfo->order_id, $rmaInfo->order_product_id);
            //dd($alreadyBindDiscount,$phurseRmaDiscount);
            //所有已绑定的优惠券金额
            $soaCouponAmount = $alreadyBindDiscount['all_sales_coupon_amount'] + $phurseRmaDiscount['all_phurse_coupon_amount'];
            //所有已绑定的活动金额
            $sopCampaignAmount = $alreadyBindDiscount['all_sales_campaign_amount'] + $phurseRmaDiscount['all_phurse_campaign_amount'];
            //未绑定（seller未处理的，占用了RMA的数额）
            $noHandleRmaInfo = app(RamRepository::class)->getPhurseOrderNoHandleRmaInfo($rmaInfo->order_id,$rmaInfo->order_product_id,$rma_order_id);

            if ($orderProductInfo->coupon_amount > 0) {
                $lastCouponAmount = max($orderProductInfo->coupon_amount - $soaCouponAmount - $noHandleRmaInfo['all_phurse_coupon_amount'], 0);
            }
            if ($orderProductInfo->campaign_amount > 0) {
                $lastCampaignAmount = max($orderProductInfo->campaign_amount - $sopCampaignAmount - $noHandleRmaInfo['all_phurse_campaign_amount'], 0);
            }
            $noBindingInfo['couponAndCampaignAmount'] = $lastCouponAmount + $lastCampaignAmount; //总的优惠金额减去已绑定的优惠金额
            $noBindingInfo['couponAndCampaignAmountShow'] = '-' . $this->currency->format((double)$noBindingInfo['couponAndCampaignAmount'], $this->session->data['currency']);
            //重置totalCur  $noBindingInfo['total']在下面计算有用到 不能重置此值
            $noBindingInfo['totalCur'] = $this->currency->format($noBindingInfo['total'] - $noBindingInfo['couponAndCampaignAmount'], $this->session->data['currency']);;
        }

        $data['bindingInfos'] = $bindingInfo;
        $data['noBinding'] = $noBindingInfo;
        //获取采购订单退货理由
        $this->model_account_order->getRmaReason();
        $this->load->model('localisation/return_reason');

        $data['rmaReason'] = $this->model_account_order->getRmaReason();

        //获取交易币制
        $symbolLeft  = $this->currency->getSymbolLeft($this->session->data['currency']);
        $symbolRight  = $this->currency->getSymbolRight($this->session->data['currency']);
        $data['currency'] = $symbolLeft.$symbolRight;
        $data['country'] = session('currency');
        if (isset($this->request->post['comment'])) {
            $data['comment'] = $this->request->post['comment'];
        } else {
            $data['comment'] = '';
        }
        // 获取附件
        $this->load->model('account/rma_management');
        $rmaOrderFiles = $this->model_account_rma_management->getRmaOrderFile($rmaInfo->id, 1);

        // 遍历文件
        foreach ($rmaOrderFiles as $rmaOrderFile) {
            $data['imageResult'][] = array(
                'id' => $rmaOrderFile->id,
                'rmaId' => $rmaInfo->id,
                'name' => $rmaOrderFile->file_name,
                'path' =>StorageCloud::rmaFile()->getUrl($rmaOrderFile->file_path),
            );
        }

        $data['back'] = $this->url->link('account/account', '', true);

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        //判断返点
        $this->load->model('customerpartner/rma_management');
        $rebateInfo = $this->model_customerpartner_rma_management->getRebateInfo($rmaInfo->order_id,$rmaInfo->product_id);
        //查看返点协议申请情况
        $maxRefundMoney = $noBindingInfo['total'];
        $data['unit_refund'] = $noBindingInfo['total'] / $noBindingInfo['nobindingQty'];
        $data['msg_rebate_refund'] = '';
        $data['refundRange'] = app(RamRepository::class)->getRefundRange($maxRefundMoney, $noBindingInfo['nobindingQty']);
        if (!empty($rebateInfo)) {
            $checkRebateInfo = app(RebateRepository::class)
                ->checkRebateRefundMoney(
                    $maxRefundMoney,
                    $noBindingInfo['nobindingQty'],
                    $rmaInfo->order_id,
                    $rmaInfo->product_id
                );
            $data['refundRange'] = $checkRebateInfo['refundRange'] ?? $data['refundRange'];
            $data['msg_rebate_refund'] = $checkRebateInfo['buyerMsg'] ?? '';
        }

        //优惠券&活动
        $isJapan = customer()->isJapan() ? 1 : 0;
        $couponCampaign = app(RamRepository::class)->getReturnCouponAndCampaign($rmaInfo->order_id, $rmaInfo->product_id, $rmaInfo->order_product_id, $noBindingInfo['nobindingQty'], $isJapan, $rma_order_id);
        foreach ($data['refundRange'] as $key => $range) {
            $data['refundRange'][$key] = bcsub($range, $couponCampaign[$key], 2);
        }

        $this->response->setOutput($this->load->view('account/rma_management/edit_return_form', $data));
    }

    public function updateRmaRequest()
    {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $this->load->model('account/rma_management');
        $this->load->language('account/rma_management');
        $checkData = $this->checkRmaDataForEdit();
        $result = null;
        if (count($checkData['error']) > 0) {
            $result = array(
                'error' => $checkData['error']
            );
            goto end;
        }

        //容个错
        $productId = (int)$this->request->post('productId', 0);
        $orderId = (int)$this->request->post('orderId', 0);
        $orderProductId = (int)$this->request->post('orderProductId', 0);
        $returnableQty = (int)$this->request->post('returnableQty', 0);
        $returnQty = (int)$this->request->post('returnQty', 0);

        if (!$productId || !$orderId || !$orderProductId || !$returnableQty || !$returnQty) {
            $result = array(
                'error' => 'Param Error'
            );
            goto end;
        }
        //判断优惠券&活动
        $orderProductInfo = OrderProduct::find($orderProductId);
        $splitCampaignAmount = $splitCouponAmount = 0; //初始值
        //活动
        if ($orderProductInfo->campaign_amount > 0 || $orderProductInfo->coupon_amount > 0) {
            $takedDiscount = app(CampaignRepository::class)->calculateCampaignAndCouponTakedAmount($orderProductInfo->order_id,
                $orderProductId, $productId);
            if ($orderProductInfo->campaign_amount > 0) {
                if ($returnQty < $returnableQty) {
                    $splitCampaignAmount = $returnQty * MoneyHelper::averageAmountFloor($orderProductInfo->campaign_amount,
                            $orderProductInfo->quantity, $this->customer->isJapan() ? 0 : 2);
                } else {
                    $splitCampaignAmount = max($orderProductInfo->campaign_amount - $takedDiscount['all_taken_campaign_discount'], 0);
                }
            }
            //优惠券
            if ($orderProductInfo->coupon_amount > 0) {
                if ($returnQty < $returnableQty) {
                    $splitCouponAmount = $returnQty * MoneyHelper::averageAmountFloor($orderProductInfo->coupon_amount,
                            $orderProductInfo->quantity, $this->customer->isJapan() ? 0 : 2);
                } else {
                    $noHandleRmaAmount = app(RamRepository::class)->getPhurseOrderNoHandleRmaInfo($orderId,$orderProductId,$this->request->post('rmaOrderId'));
                    $noHandleRmaCouponAmount = $noHandleRmaAmount['all_phurse_coupon_amount'] > 0 ? $noHandleRmaAmount['all_phurse_coupon_amount'] : 0;
                    $splitCouponAmount = max($orderProductInfo->coupon_amount - $takedDiscount['all_taken_coupon_discount'] - $noHandleRmaCouponAmount, 0);
                }
            }
        }

        $connection = $this->orm->getConnection();
        try {
            $connection->beginTransaction();
            define("DEFAULT_RMA_STATUS_ID", 1);
            // 1. 获取itemCode,orderId,sellerId,rmaQty,reason,comments
            $itemCode = trim($this->request->post['itemCode']);
            //$orderId = (int)$this->request->post['orderId'];
            $buyerId = (int)$this->request->post['buyerId'];
            $sellerId = (int)$this->request->post['sellerId'];
            $rmaQty = (int)$this->request->post['returnQty'];
            $rmaOrderId = (int)$this->request->post['rmaOrderId'];
            $asin = null;
            $reason = $this->request->post['rmaReason'] == '' ? null : (int)$this->request->post['rmaReason'];
            $comments = $this->request->post['comments'];

            // 2. 插入oc_yzc_rma_order
            $rmaOrder = array(
                "order_id" => $orderId,
                "from_customer_order_id" => null,
                "seller_id" => $sellerId,
                "buyer_id" => $buyerId,
                "admin_status" => null,
                "seller_status" => DEFAULT_RMA_STATUS_ID,
                "cancel_rma" => false,
                "solve_rma" => false,
                "update_user_name" => $buyerId,
                "update_time" => date("Y-m-d H:i:s", time()),
                "processed_date" =>null,
                "order_type" =>2
            );
            $this->model_account_rma_management->updateRmaOrder($rmaOrderId,$rmaOrder);
            $rmaId = $this->request->post['rmaId'];

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

                        //迁移rma 附件到oss
                        StorageCloud::rmaFile()->writeFile($file, $buyerId, $filename);

                        // 插入文件数据
                        $rmaFile = array(
                            "rma_id" => $rmaId,
                            'file_name' => $file->getClientOriginalName(),
                            'size' => $file->getSize(),
                            "file_path" => $buyerId . "/" . $filename,
                            "buyer_id" => $buyerId
                        );
                        $this->model_account_rma_management->addRmaFile($rmaFile);
                    }
                }
            }


            // 4.插入RMA明细数据，oc_yzc_rma_order_product
            //采购订单退货仅退款
            $rmaType = 2;
            // 申请退款金额
            $refundAmount = null;
            $refundAmount = (float)$this->request->post['refund'];
            $product_id = isset($this->request->post['productId'])?$this->request->post['productId']:null;
            $orderProductId = isset($this->request->post['orderProductId'])?$this->request->post['orderProductId']:null;
            $rmaOrderProduct = array(
                "product_id" => $product_id,
                "item_code" => $itemCode,
                "quantity" => $rmaQty,
                "reason_id" => $reason,
                'asin' => $asin,
                "order_product_id" => $orderProductId,
                "status_refund" => 0,
                "refund_type" => null,
                "seller_refund_comments" => null,
                "comments" => $comments,
                "rma_type" => $rmaType,
                "apply_refund_amount" => $refundAmount,
                'coupon_amount' => $splitCouponAmount,
                'campaign_amount' => $splitCampaignAmount,
            );
            $this->model_account_rma_management->updateRmaOrderProduct($rmaId,$rmaOrderProduct);
            $this->rmaCommunication($rmaId);
        } catch (Exception $exception) {
            $connection->rollBack();
            $this->log->write($exception->getMessage());
            goto end;
        }
        $connection->commit();
        end:
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($result));
    }

    private function checkRmaDataForEdit()
    {
        $this->load->model('account/order');
        $error = array();
        if(empty($this->request->post['rmaId'])){
            $error[]=array(
                "displayType" => 1,
                "errorMsg" => 'Error!RMA can not update.'
            );
        }
        //判断seller的处理状态
        $rma_id = $this->request->post['rmaId'];
        $seller_status =$this->model_account_order->getSellerStatusByRmaId($rma_id);
        if($seller_status ==3){
            $error[]=array(
                "displayType" => 1,
                "errorMsg" => 'RMA Status is changed, can not be edited.'
            );
        }
        if(!empty($this->request->post['returnableQty'])){
            $returnableQty = $this->request->post['returnableQty'];
        }else{
            $error[]=array(
                "displayType" => 1,
                "errorMsg" => 'Returnable Quanity cannot empty.'
            );
        }
        if(isset($this->request->post['total'])){
            $total = $this->request->post['total'];
        }else{
            $error[]=array(
                "displayType" => 1,
                "errorMsg" => 'Total cannot empty.'
            );
        }
        if(!empty($this->request->post['comments'])){
            if(strlen($this->request->post['comments'])>3000){
                $error[]=array(
                    "id" => 'comments',
                    "href" => 'comments',
                    "displayType" => 1,
                    "errorMsg" => 'Comments must be between 1 and 3000 characters!'
                );
            }
        }else{
            $error[]=array(
                "id" => 'comments',
                "href" => 'comments',
                "displayType" => 1,
                "errorMsg" => 'Comments cannot be left blank.'
            );
        }
        if(!empty($this->request->post['returnQty'])){
            $returnQty = $this->request->post['returnQty'];
            $product_id = isset($this->request->post['productId'])?$this->request->post['productId']:null;
            $orderId = (int)$this->request->post['orderId'];
            $rma_id = (int)$this->request->post['rmaId'];
            $noBindingInfo = $this->model_account_order->getNoBindingOrderInfo($orderId,$product_id,$rma_id);
            $noBindingInfo['total'] = (int)$noBindingInfo['nobindingQty']*($noBindingInfo['price']+$noBindingInfo['service_fee_per']+$noBindingInfo['freight_per']+$noBindingInfo['package_fee']);
            if($returnQty>$noBindingInfo['nobindingQty']){
                $error[]=array(
                    "id" => 'returnQty',
                    "href" => 'returnQty',
                    "displayType" => 1,
                    "errorMsg" => 'Return Quantity cannot more than Returnable Quantity.'
                );
            }
            if (isset($this->request->post['refund']) && $this->request->post['refund'] != "") {
                $refund = $this->request->post['refund'];
                if ($refund > (string)($noBindingInfo['total']*1000)/1000) {
                    $error[] = array(
                        "id"          => 'refund',
                        "href"        => 'refund',
                        "displayType" => 1,
                        "errorMsg"    => 'Refund cannot more than Total.'
                    );
                }
            } else {
                $error[] = array(
                    "id"          => 'refund',
                    "href"        => 'refund',
                    "displayType" => 1,
                    "errorMsg"    => 'Refund can not be left blank.'
                );
            }
        }else{
            $error[]=array(
                "id" => 'returnQty',
                "href" => 'returnQty',
                "displayType" => 1,
                "errorMsg" => 'Return Quantity cannot be left blank.'
            );
        }
        if(empty($this->request->post['rmaReason'])){
            $error[]=array(
                "id" => 'rmaReason',
                "href" => 'rmaReason',
                "displayType" => 1,
                "errorMsg" => 'Reason cannot be left blank.'
            );
        }
        $result = array(
            'error' => $error
        );
        return $result;
    }
}
