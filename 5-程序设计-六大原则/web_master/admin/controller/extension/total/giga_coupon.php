<?php

/**
 * @property ModelSettingSetting $model_setting_setting
 */
class ControllerExtensionTotalGigaCoupon extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/total/giga_coupon');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if ((request()->isMethod('POST')) && $this->validate()) {
			$this->model_setting_setting->editSetting('total_giga_coupon', $this->request->post);

			session()->set('success', $this->language->get('text_success'));

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=total', true));
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
			'href' => $this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=total', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/total/giga_coupon', 'user_token=' . session('user_token'), true)
		);

		$data['action'] = $this->url->link('extension/total/giga_coupon', 'user_token=' . session('user_token'), true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=total', true);

		if (isset($this->request->post['total_giga_coupon_status'])) {
			$data['total_giga_coupon_status'] = $this->request->post['total_giga_coupon_status'];
		} else {
			$data['total_giga_coupon_status'] = $this->config->get('total_giga_coupon_status');
		}

		if (isset($this->request->post['giga_coupon_sort_order'])) {
			$data['total_giga_coupon_sort_order'] = $this->request->post['total_giga_coupon_sort_order'];
		} else {
			$data['total_giga_coupon_sort_order'] = $this->config->get('total_giga_coupon_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/total/giga_coupon', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/total/giga_coupon')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
