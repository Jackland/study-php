<?php

/**
 * @property ModelExtensionTotalCoupon $model_extension_total_coupon
 */
class ControllerExtensionTotalCoupon extends Controller {
	public function index() {
		if ($this->config->get('total_coupon_status')) {
			$this->load->language('extension/total/coupon');

			if (isset($this->session->data['coupon'])) {
				$data['coupon'] = session('coupon');
			} else {
				$data['coupon'] = '';
			}

			return $this->load->view('extension/total/coupon', $data);
		}
	}

	public function coupon() {
		$this->load->language('extension/total/coupon');

		$json = array();

		$this->load->model('extension/total/coupon');

		if (isset($this->request->post['coupon'])) {
			$coupon = $this->request->post['coupon'];
		} else {
			$coupon = '';
		}

		$coupon_info = $this->model_extension_total_coupon->getCoupon($coupon);

		if (empty($this->request->post['coupon'])) {
			$json['error'] = $this->language->get('error_empty');

			$this->session->remove('coupon');
		} elseif ($coupon_info) {
			session()->set('coupon', $this->request->post['coupon']);

			session()->set('success', $this->language->get('text_success'));

			$json['redirect'] = $this->url->link('checkout/cart');
		} else {
			$json['error'] = $this->language->get('error_coupon');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
