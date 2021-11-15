<?php
/**
 * Webkul Software.
 * @category  Webkul
 * @author    Webkul
 * @copyright Copyright (c) 2010-2016 Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 * @property ModelCatalogInformation $model_catalog_information
 * @property ModelCustomerpartnerMail $model_customerpartner_mail
 * @property ModelExtensionModulewkrma $model_extension_module_wk_rma
 * @property ModelLocalisationOrderStatus $model_localisation_order_status
 * @property ModelSaleVoucherTheme $model_sale_voucher_theme
 * @property ModelSettingSetting $model_setting_setting
 */
class ControllerExtensionModuleWkrma extends Controller {

	use RmaControllerTrait;

	private $error = array();

	public function install() {
		$this->load->model('extension/module/wk_rma');
		$this->model_extension_module_wk_rma->install();
	}

	public function index() {

		$this->language->load('extension/module/wk_rma');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('tool/image');

		if ((request()->isMethod('POST')) && $this->validate('extension/module/wk_rma') && $this->validateForm()) {
			$this->model_setting_setting->editSetting('wk_rma', $this->request->post);
			$this->request->post['module_wk_rma_status'] = $this->request->post['wk_rma_status'];
			$this->model_setting_setting->editSetting('module_wk_rma', $this->request->post);
			session()->set('success', $this->language->get('text_success'));
			$this->response->redirect($this->urlChange('marketplace/extension', 'user_token=' . session('user_token') . '&type=module', 'SSL'));
		}

		$data['manage_rma'] = $this->urlChange('catalog/wk_rma_admin', 'user_token=' . session('user_token'), 'SSL');
		$data['status_rma'] = $this->urlChange('catalog/wk_rma_status', 'user_token=' . session('user_token'), 'SSL');
		$data['reason_rma'] = $this->urlChange('catalog/wk_rma_reason', 'user_token=' . session('user_token'), 'SSL');

		//CONFIG
		$config_data = array(
			'wk_rma_status',
			'wk_rma_address',
			'wk_rma_system_information',
			'wk_rma_system_time',
			'wk_rma_system_orders',
			'wk_rma_system_image',
			'wk_rma_system_size',
			'wk_rma_system_file',
			'wk_rma_voucher_theme',
			'wk_rma_seller_return_separate',

			'wk_rma_new_return_admin_mail',
			'wk_rma_new_return_seller_mail',
			'wk_rma_new_return_customer_mail',
			'wk_rma_status_to_customer_mail',
			'wk_rma_status_to_seller_mail',
			'wk_rma_status_to_admin_mail',
			'wk_rma_message_to_seller_mail',
			'wk_rma_message_to_admin_mail',
			'wk_rma_message_to_customer_mail',
			'wk_rma_message_to_seller_adminmail',
			'wk_rma_label_to_customer_mail',
			'wk_rma_message_to_admin_sellermail',
		);

		foreach ($config_data as $conf) {
			if (isset($this->request->post[$conf])) {
				$data[$conf] = $this->request->post[$conf];
			} else {
				$data[$conf] = $this->config->get($conf);
			}
		}

		$this->load->model('catalog/information');
		$data['information'] = $this->model_catalog_information->getInformations(array());

		$this->load->model('localisation/order_status');
		$data['order_status'] = $this->model_localisation_order_status->getOrderStatuses(array());

		$this->load->model('customerpartner/mail');
		$data['mails'] = $this->model_customerpartner_mail->gettotal();

		$data['user_token'] = session('user_token');
		$data['error_warning'] = '';
		if (isset($this->error['warning'])) {
			unset($this->error['warning']);
			$data['error'] = $this->error;
		} else {
			$data['error'] = array();
		}

		if (isset($this->session->data['warning'])) {
			$data['error_warning'] = session('warning');
			$this->session->remove('warning');
		}

		if (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = session('error_warning');
			$this->session->remove('error_warning');
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = session('success');
			$this->session->remove('success');
		} else {
			$data['success'] = '';
		}

		$data['breadcrumbs'] = array();

 		$data['breadcrumbs'][] = array(
     		'text'      => $this->language->get('text_home'),
		    'href'      => $this->urlChange('common/dashboard', 'user_token=' . session('user_token'), 'SSL'),
    		'separator' => false
 		);

 		$data['breadcrumbs'][] = array(
     		'text'      => $this->language->get('text_module'),
		    'href'      => $this->urlChange('marketplace/extension', 'user_token=' . session('user_token') . '&type=module', 'SSL'),
    		'separator' => ''
 		);

 		$data['breadcrumbs'][] = array(
     		'text'      => $this->language->get('heading_title'),
		    'href'      => $this->urlChange('extension/module/wk_rma', 'user_token=' . session('user_token'), 'SSL'),
    		'separator' => ''
 		);
		$data['mail_help'] = array(
			'{order_id}',
			'{rma_id}',
			'{product_name}',
			'{customer_name}',
			'{message}',
			'{link}',
			'{label_link}',
			'{seller_name}',
			'{config_logo}',
			'{config_icon}',
			'{config_currency}',
			'{config_image}',
			'{config_name}',
			'{config_owner}',
			'{config_address}',
			'{config_geocode}',
			'{config_email}',
			'{config_telephone}',
			);

		$data['action'] = $this->urlChange('extension/module/wk_rma', 'user_token=' . session('user_token'), 'SSL');
		$data['refresh'] = $this->urlChange('extension/module/wk_rma/refresh', 'user_token=' . session('user_token'), 'SSL');
		$data['uninstall'] = $this->urlChange('extension/module/wk_rma/deletetable', 'user_token=' . session('user_token'), 'SSL');

		$data['cancel'] = $this->urlChange('marketplace/extension', 'user_token=' . session('user_token') . '&type=module', 'SSL');

		$this->load->model('sale/voucher_theme');
		$data['voucher_themes'] = $this->model_sale_voucher_theme->getVoucherThemes();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/wk_rma', $data));

	}

	private function validateForm() {
		$config_data = array(
			'address',
			'system_orders',
			'system_size',
		);

		foreach ($config_data as $config) {
			if (!isset($this->request->post['wk_rma_' . $config]) || !$this->request->post['wk_rma_' . $config]) {
				$this->error['warning'] = $this->language->get('error_' . $config);
				$this->error['error_' . $config] = $this->language->get('error_' . $config);
			}
		}

		$config_data = array(
			'system_size',
		);

		foreach ($config_data as $config) {
			if (!isset($this->request->post['wk_rma_' . $config]) || !filter_var($this->request->post['wk_rma_' . $config], FILTER_VALIDATE_INT) && (int)$this->request->post['wk_rma_' . $config] > 0) {
				$this->error['warning'] = $this->language->get('error_' . $config);
				$this->error['error_' . $config] = $this->language->get('error_size');
			}
		}

		if (isset($this->request->post['wk_rma_system_time']) &&  (int)$this->request->post['wk_rma_system_time'] < 0) {
			$this->error['warning'] = $this->language->get('error_system_time');
			$this->error['error_system_time'] = $this->language->get('error_system_time');
		}

		return !$this->error;

	}

	public function refresh() {
		$this->load->language('extension/module/wk_rma');
		if (!$this->validate('extension/module/wk_rma')) {
			session()->set('error_warning', $this->language->get('error_permission'));
			$this->response->redirect($this->urlChange('extension/module/wk_rma','&user_token=' . session('user_token')));
		}
		$this->load->model('extension/module/wk_rma');
		$this->model_extension_module_wk_rma->uninstall();
		$this->model_extension_module_wk_rma->install();
		session()->set('success', $this->language->get('text_refresh'));
		$this->response->redirect($this->urlChange('extension/module/wk_rma','&user_token=' . session('user_token'), true));
	}

	public function deletetable() {
		$this->load->language('extension/module/wk_rma');
		if (!$this->validate('extension/module/wk_rma')) {
			session()->set('error_warning', $this->language->get('error_permission'));
			$this->response->redirect($this->urlChange('extension/module/wk_rma','&user_token=' . session('user_token')));
		}
		$this->load->model('extension/module/wk_rma');
		$this->model_extension_module_wk_rma->uninstall();
		// $this->model_extension_module_wk_rma->removeOCMOD();
		session()->set('success', $this->language->get('text_delete_table'));
		// $this->load->controller('extension/modification/refresh');
		$this->response->redirect($this->urlChange('extension/module/wk_rma','&user_token=' . session('user_token'), true));
	}


}
