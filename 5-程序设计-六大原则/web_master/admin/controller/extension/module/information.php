<?php

/**
 * @property ModelSettingSetting $model_setting_setting
 */
class ControllerExtensionModuleInformation extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/information');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if ((request()->isMethod('POST')) && $this->validate()) {
			$this->model_setting_setting->editSetting('module_information', $this->request->post);

			session()->set('success', $this->language->get('text_success'));

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=module', true));
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
			'href' => $this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/information', 'user_token=' . session('user_token'), true)
		);

		$data['action'] = $this->url->link('extension/module/information', 'user_token=' . session('user_token'), true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=module', true);

		if (isset($this->request->post['module_information_status'])) {
			$data['module_information_status'] = $this->request->post['module_information_status'];
		} else {
			$data['module_information_status'] = $this->config->get('module_information_status');
		}
		if (isset($this->request->post['module_information_indent'])) {
			$data['module_information_indent'] = $this->request->post['module_information_indent'];
		} else {
			$data['module_information_indent'] = $this->config->get('module_information_indent');
		}
		if (isset($this->request->post['module_information_space'])) {
			$data['module_information_space'] = $this->request->post['module_information_space'];
		} else {
			$data['module_information_space'] = $this->config->get('module_information_space');
		}
		if (isset($this->request->post['module_information_fontsize'])) {
			$data['module_information_fontsize'] = $this->request->post['module_information_fontsize'];
		} else {
			$data['module_information_fontsize'] = $this->config->get('module_information_fontsize');
		}
		if (isset($this->request->post['module_information_symbol1'])) {
			$data['module_information_symbol1'] = $this->request->post['module_information_symbol1'];
		} else {
			$data['module_information_symbol1'] = $this->config->get('module_information_symbol1');
		}
		if (isset($this->request->post['module_information_symbol2'])) {
			$data['module_information_symbol2'] = $this->request->post['module_information_symbol2'];
		} else {
			$data['module_information_symbol2'] = $this->config->get('module_information_symbol2');
		}
		if (isset($this->request->post['module_information_symbol3'])) {
			$data['module_information_symbol3'] = $this->request->post['module_information_symbol3'];
		} else {
			$data['module_information_symbol3'] = $this->config->get('module_information_symbol3');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/information', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/information')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}