<?php
// 信用额度支付方式

/**
 * @property ModelLocalisationGeoZone $model_localisation_geo_zone
 * @property ModelLocalisationOrderStatus $model_localisation_order_status
 * @property ModelSettingSetting $model_setting_setting
 */
class ControllerExtensionPaymentLineOfCredit extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/payment/line_of_credit');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if ((request()->isMethod('POST')) && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_line_of_credit', $this->request->post);

			session()->set('success', $this->language->get('text_success'));

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=payment', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=payment', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/line_of_credit', 'user_token=' . session('user_token'), true)
		);

		$data['action'] = $this->url->link('extension/payment/line_of_credit', 'user_token=' . session('user_token'), true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=payment', true);

		if (isset($this->request->post['payment_line_of_credit_total'])) {
			$data['payment_line_of_credit_total'] = $this->request->post['payment_line_of_credit_total'];
		} else {
			$data['payment_line_of_credit_total'] = $this->config->get('payment_line_of_credit_total');
		}

		if (isset($this->request->post['payment_line_of_credit_order_status_id'])) {
			$data['payment_line_of_credit_order_status_id'] = $this->request->post['payment_line_of_credit_order_status_id'];
		} else {
			$data['payment_line_of_credit_order_status_id'] = $this->config->get('payment_line_of_credit_order_status_id');
		}

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post['payment_line_of_credit_geo_zone_id'])) {
			$data['payment_line_of_credit_geo_zone_id'] = $this->request->post['payment_line_of_credit_geo_zone_id'];
		} else {
			$data['payment_line_of_credit_geo_zone_id'] = $this->config->get('payment_line_of_credit_geo_zone_id');
		}

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['payment_line_of_credit_status'])) {
			$data['payment_line_of_credit_status'] = $this->request->post['payment_line_of_credit_status'];
		} else {
			$data['payment_line_of_credit_status'] = $this->config->get('payment_line_of_credit_status');
		}

		if (isset($this->request->post['payment_line_of_credit_sort_order'])) {
			$data['payment_line_of_credit_sort_order'] = $this->request->post['payment_line_of_credit_sort_order'];
		} else {
			$data['payment_line_of_credit_sort_order'] = $this->config->get('payment_line_of_credit_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/line_of_credit', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/line_of_credit')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}