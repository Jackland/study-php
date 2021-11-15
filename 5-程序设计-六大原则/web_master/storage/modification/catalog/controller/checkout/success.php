<?php

use App\Enums\FeeOrder\FeeOrderFeeType;
use App\Enums\FeeOrder\FeeOrderStatus;
use App\Repositories\FeeOrder\FeeOrderRepository;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * @property ModelCheckoutSuccess $model_checkout_success
 * @property ModelAccountDeliverySignature $model_account_deliverySignature
 * */
class ControllerCheckoutSuccess extends Controller {

    //联动支付成功后"返回商家"会跳转到这（携带session ID）
	public function index() {

	    if (!$this->customer->isLogged() && !$this->request->get('k')){//k为联动支付“返回商家”携带的sessionID
            $this->response->redirectTo($this->url->link('account/login', '', true));
        }
        if($this->request->get('k',0)){
            $this->session->start($this->request->get('k'));
            $customer = new Cart\Customer($this->registry);
            $this->registry->set('customer', $customer);
            setcookie($this->config->get('session_name'), $this->session->getId(), ini_get('session.cookie_lifetime'), ini_get('session.cookie_path'), ini_get('session.cookie_domain'));
        }

        $this->load->model('checkout/success');
        $this->load->model('account/deliverySignature');
        $this->load->language('account/deliverySignature');
        $this->load->language('checkout/success');
        //商品单id
        $orderId = $this->request->get('o', 0);
        //费用单id
        $feeOrderIdStr = $this->request->get('f',null);
        $feeOrderIdArr = $feeOrderIdStr == null ? [] :explode(',',$feeOrderIdStr);
        if(!empty($orderId)){
            $orderStatus = $this->model_checkout_success->getOrderStatus($orderId);
            if (5 != $orderStatus){
                $this->response->redirectTo($this->url->link('checkout/checkout', '', true));
            }
            //根据OrderId查询费用单
            $feeToPayArr = $this->model_checkout_success->getFeeToPaySalesOrderIdByOrderId($orderId);
            $data['feeToPayFlag'] = count($feeToPayArr) > 0;
            $data['feeToPayStr'] = implode(',', $feeToPayArr);
        }
        if(!empty($feeOrderIdArr)) {
            // 只判断仓租费用单
            $feeOrderInfos = app(FeeOrderRepository::class)->findFeeOrderInfo($feeOrderIdArr);
            $feeOrderStatus = $feeOrderInfos[0]['status'];
            if(FeeOrderStatus::COMPLETE == $feeOrderStatus){
                $this->response->redirectTo($this->url->link('checkout/checkout', '', true));
            }
        }
        //用于控制跳转到采购单页的tab
        $orderUrlParams = [];
        if (empty($orderId) && !empty($feeOrderIdArr)) {
            $orderUrlParams['#'] = 'tab_fee_order';
        }

        $data['show_order'] = $this->url->link('account/order', $orderUrlParams);
        if(!empty($orderId) && empty($feeOrderIdArr)) {
            $data['show_order'] = $this->url->link('account/order/purchaseOrderInfo&order_id='.$orderId);
        }

		$this->document->setTitle($this->language->get('heading_title'));
		$data['breadcrumbs'] = [
		    [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home')
            ],
            [
                'text' => $this->language->get('text_basket'),
                'href' => $this->url->link('checkout/cart')
            ],
            [
                'text' => $this->language->get('text_success'),
                'href' => $this->url->link('checkout/success', '&o='.$orderId)
            ]
        ];

		if ($this->customer->isLogged()) {
            //正常支付成功
            $text_message = sprintf($this->language->get('text_customer'),
                $this->url->link('account/account', '', true), $this->url->link('account/order', $orderUrlParams, true),
                $this->url->link('account/download', '', true), $this->url->link('information/contact'));
			$data['text_message'] = $text_message;

		} else {
			$data['text_message'] = sprintf($this->language->get('text_guest'), $this->url->link('information/contact'));
		}

        $asr_order_ids = $this->model_account_deliverySignature->getUnPaidAsrOrder($this->customer->getId(),$_SERVER['REMOTE_ADDR'],$orderId);
		if(isset($asr_order_ids) && !empty($asr_order_ids)){
		    $first_id = array_keys($asr_order_ids)[0];
		    $asr_url = $this->url->link('account/deliverySignature', '&id='.$first_id);
            $data['asr_order_ids'] = sprintf($this->language->get('text_success_notice'),  implode("],[",$asr_order_ids), $asr_url);
        }
		$data['data'] = $this->model_checkout_success->successShow($orderId,$feeOrderIdArr);

		$data['isLogin'] = $this->customer->isLogged();
		$data['view_bp_list'] = $this->url->link('account/customer_order','checkoutViewBp=1',true);
		$data['go_home'] = $this->url->link('common/home');
        $data['order_detail'] = $this->url->link('account/buyer_central', '', true);

        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('checkout/success', $data));
	}

    //检查订单是否已完成
    public function toSuccessPage(){
        if(isset($this->request->get['k']) && isset($this->request->get['o'])){
    //      回调函数需进入session   k:sessionId   o:yzcOrderId
            $this->session->start($this->request->get['k']);
            $customer = new Cart\Customer($this->registry);
            $this->registry->set('customer', $customer);
            $order_id = $this->request->get['o'];
        }else if(isset($this->request->get['orderId'])){
            $order_id = $this->request->get['orderId'];
        }

        if(isset($order_id)){
            //检查订单是否已完成
            $this->load->model('checkout/success');
            $orderStatus = $this->model_checkout_success->getOrderStatus($order_id);
            if (5 == $orderStatus){
                //订单已完成
                $this->index();
            }
        }
        //订单未完成
        $this->response->redirect($this->url->link('checkout/checkout', '', true));
    }
}
