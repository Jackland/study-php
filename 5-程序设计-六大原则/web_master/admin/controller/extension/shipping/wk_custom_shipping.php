<?php

/**
 * @property ModelLocalisationGeoZone $model_localisation_geo_zone
 * @property ModelLocalisationTaxClass $model_localisation_tax_class
 * @property ModelSettingSetting $model_setting_setting
 */
class ControllerExtensionShippingwkcustomshipping extends Controller {

	private $error = array();
	private $data = array();

	public function index() {
		$this->load->language('extension/shipping/wk_custom_shipping');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if ((request()->isMethod('POST')) && $this->validate()) {

			$this->model_setting_setting->editSetting('shipping_wk_custom_shipping', $this->request->post);

			session()->set('success', $this->language->get('text_success'));

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . session('user_token').'&type=shipping', true));
		}

 		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}

  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true),
      		'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_shipping'),
			'href'      => $this->url->link('marketplace/extension', 'user_token=' . session('user_token').'&type=shipping', true),
      		'separator' => ' :: '
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('extension/shipping/wk_custom_shipping', 'user_token=' . session('user_token'), true),
      		'separator' => ' :: '
   		);

		$this->data['action'] = $this->url->link('extension/shipping/wk_custom_shipping', 'user_token=' . session('user_token'), true);

		$this->data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . session('user_token').'&type=shipping', true);

		if (isset($this->error['method_name'])) {
			$this->data['error_method_name'] = $this->error['method_name'];
		} else {
			$this->data['error_method_name'] = '';
		}

		if (isset($this->error['title'])) {
			$this->data['error_title'] = $this->error['title'];
		} else {
			$this->data['error_title'] = '';
		}

		if (isset($this->error['method'])) {
			$this->data['error_method'] = $this->error['method'];
		} else {
			$this->data['error_method'] = '';
		}

		if (isset($this->error['admin_flatrate'])) {
			$this->data['error_admin_flatrate'] = $this->error['admin_flatrate'];
		} else {
			$this->data['error_admin_flatrate'] = '';
		}

		$config_array = array(
			'shipping_wk_custom_shipping_admin_flatrate',
			'shipping_wk_custom_shipping_method',
			'shipping_wk_custom_shipping_cost',
			'shipping_wk_custom_shipping_title',
			'shipping_wk_custom_shipping_method_title',
			'shipping_wk_custom_shipping_cal_handling_fee',
			'shipping_wk_custom_shipping_error_msg',
			'shipping_wk_custom_shipping_geo_zone_id',
			'shipping_wk_custom_shipping_tax_class_id',
			'shipping_wk_custom_shipping_status',
			'shipping_wk_custom_shipping_sort_order',
			'shipping_wk_custom_shipping_seller_status',
		);

		foreach ($config_array as $key => $value) {
			if (isset($this->request->post[$value])) {
				$this->data[$value] = $this->request->post[$value];
			} else {
				$this->data[$value] = $this->config->get($value);
			}
		}

		$this->load->model('localisation/tax_class');

		$this->data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

		$this->load->model('localisation/geo_zone');

		$this->data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$this->data['header'] = $this->load->controller('common/header');
		$this->data['column_left'] = $this->load->controller('common/column_left');
		$this->data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/shipping/wk_custom_shipping', $this->data));

	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/shipping/wk_custom_shipping')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['shipping_wk_custom_shipping_method_title']) {
			$this->error['method_name'] = $this->language->get('error_method_name');
		}

		if (!$this->request->post['shipping_wk_custom_shipping_title']) {
			$this->error['title'] = $this->language->get('error_title');
		}

		if (!$this->request->post['shipping_wk_custom_shipping_method']) {
			$this->error['method'] = $this->language->get('error_method');
		}elseif(($this->request->post['shipping_wk_custom_shipping_method']=='flat' OR $this->request->post['shipping_wk_custom_shipping_method']=='both') AND (!(int)$this->request->post['shipping_wk_custom_shipping_admin_flatrate'])){
			$this->error['admin_flatrate'] = $this->language->get('error_admin_flatrate');
		}

		if (!$this->error) {
			return true;
		} else {
			return false;
		}
	}
}
?>
