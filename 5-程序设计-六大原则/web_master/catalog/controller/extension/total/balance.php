<?php
class ControllerExtensionTotalBalance extends Controller {
	public function index($input) {
		if ($this->config->get('total_balance_status')) {
			$this->load->language('extension/total/balance');
			$data = array();
			// 获取当前用户Line of credit
            $data['balance'] = $this->currency->formatCurrencyPrice($this->customer->getLineOfCredit(), $this->session->data['currency']);
            $data['total'] = $input['value'];
            // 获取信用额度余额
            $lineOfCredit = $this->customer->getLineOfCredit();
            $max = $lineOfCredit > $data['total'] ? $data['total'] : $lineOfCredit;
            $data['balance_max'] = $max;
            $data['currency'] = session('currency');
            $data['symbolLeft'] = $this->currency->getSymbolLeft($this->session->data['currency']);
            $data['symbolRight'] = $this->currency->getSymbolRight($this->session->data['currency']);
            $data['country'] = $this->customer->getCountryId();
            $data['cwf_url'] = $this->url->link('checkout/cwf_info', '', true);
            //delivery_type,运输类型
            $data['delivery_type'] = isset($this->session->data['delivery_type']) ? $this->session->data['delivery_type']: ($this->customer->isCollectionFromDomicile() ? 1 : 0);
            $data['checkout'] = $this->url->link('checkout/checkout&delivery_type='.$data['delivery_type'], false);
            return $this->load->view('extension/total/balance', $data);
		}
	}

//	public function coupon() {
//		$this->load->language('extension/total/coupon');
//
//		$json = array();
//
//		$this->load->model('extension/total/coupon');
//
//		if (isset($this->request->post['coupon'])) {
//			$coupon = $this->request->post['coupon'];
//		} else {
//			$coupon = '';
//		}
//
//		$coupon_info = $this->model_extension_total_coupon->getCoupon($coupon);
//
//		if (empty($this->request->post['coupon'])) {
//			$json['error'] = $this->language->get('error_empty');
//
//			$this->session->remove('coupon');
//		} elseif ($coupon_info) {
//			session()->set('coupon', $this->request->post['coupon']);
//
//			session()->set('success', $this->language->get('text_success'));
//
//			$json['redirect'] = $this->url->link('checkout/cart');
//		} else {
//			$json['error'] = $this->language->get('error_coupon');
//		}
//
//		$this->response->addHeader('Content-Type: application/json');
//		$this->response->setOutput(json_encode($json));
//	}
}
