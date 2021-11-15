<?php

/**
 * @property ModelLocalisationGeoZone $model_localisation_geo_zone
 * @property ModelLocalisationCurrency $model_localisation_currency
 * @property ModelLocalisationLanguage $model_localisation_language
 * @property ModelLocalisationOrderStatus $model_localisation_order_status
 * @property ModelLocalisationTaxClass $model_localisation_tax_class
 * @property ModelSettingSetting $model_setting_setting
 */
class ControllerExtensionPaymentCybersourceSop extends Controller {
	private $error = array();

	public function index() {

		$errors = array(
			'warning'
		);

		$extension_type = 'extension/payment';
		$classname = str_replace('vq2-' . basename(DIR_APPLICATION) . '_' . strtolower(get_parent_class($this)) . '_' . $extension_type . '_', '', basename(__FILE__, '.php'));

		$data['classname'] = $classname;
		$data['user_token'] = session('user_token');
//		$data = array_merge($data, $this->load->language($extension_type . '/' . $classname));
        $this->load->language($extension_type . '/' . $classname);

		if (isset($data['error_fields'])) {
			$errors = array_merge(explode(",", $data['error_fields']), $errors);
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if ((request()->isMethod('POST')) && ($this->validate($errors))) {
//			foreach ($this->request->post as $key => $value) {
//				if (is_array($value)) { $this->request->post[$key] = implode(',', $value); }
//				$this->request->post[$classname.'_'.$key] = $this->request->post[$key];
//				unset($this->request->post[$key]);
//			}

			$this->model_setting_setting->editSetting('payment_'.$classname, $this->request->post);

			session()->set('success', $this->language->get('text_success'));

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=payment', true));
		}

		$data['breadcrumbs'] = array();

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
   		);

   		$data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=payment', true)
   		);

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
            'href' => $this->url->link($extension_type . '/' . $classname, 'user_token=' . session('user_token'), true)
   		);

        $data['action'] = $this->url->link($extension_type . '/' . $classname, 'user_token=' . session('user_token'), true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=payment', true);

        foreach ($errors as $error) {
			if (isset($this->error[$error])) {
				$data['error_' . $error] = $this->error[$error];
			} else {
				$data['error_' . $error] = '';
			}
		}

		# Extension Type Icon
		switch($extension_type) {
			case 'payment':
				$data['icon_class'] = 'credit-card';
				break;
			case 'shipping':
				$data['icon_class'] = 'truck';
				break;
			default:
				$data['icon_class'] = 'puzzle-piece';
		}

		$data['extension_class'] = $extension_type;
		$data['tab_class'] = 'htabs'; //vtabs or htabs

		# Geozones
		$geo_zones = array();
		$this->load->model('localisation/geo_zone');
		$geo_zones[0] = $this->language->get('text_all_zones');
		foreach ($this->model_localisation_geo_zone->getGeoZones() as $geozone) {
			$geo_zones[$geozone['geo_zone_id']] = $geozone['name'];
		}

		# Tax Classes
		$tax_classes = array();
		$this->load->model('localisation/tax_class');
		$tax_classes[0] = $this->language->get('text_none');
		foreach ($this->model_localisation_tax_class->getTaxClasses() as $tax_class) {
			$tax_classes[$tax_class['tax_class_id']] = $tax_class['title'];
		}

		# Order Statuses
		$order_statuses = array();
		$this->load->model('localisation/order_status');
		foreach ($this->model_localisation_order_status->getOrderStatuses() as $order_status) {
			$order_statuses[$order_status['order_status_id']] = $order_status['name'];
		}

		# Customer Groups
		$customer_groups = array();
		if (file_exists(DIR_APPLICATION . 'model/customer/customer_group.php')) { $this->load->model('customer/customer_group'); $cgmodel = 'customer'; } else { $this->load->model('sale/customer_group'); $cgmodel = 'sale'; }
		$customer_groups[0] = $this->language->get('text_guest');
		foreach ($this->{'model_' . $cgmodel . '_customer_group'}->getCustomerGroups() as $customer_group) {
			$customer_groups[$customer_group['customer_group_id']] = $customer_group['name'];
		}

		# Languages
		$languages = array();
		$this->load->model('localisation/language');
		foreach ($this->model_localisation_language->getLanguages() as $language) {
			$languages[$language['language_id']] = $language['name'];
		}

		# Currencies
		$currencies = array();
		$this->load->model('localisation/currency');
		foreach ($this->model_localisation_currency->getCurrencies() as $currency) {
			$currencies[$currency['code']] = $currency['code'];
		}

		# Tabs
		$data['tabs'] = array();

		# Fields
		$data['fields'] = array();

		include(DIR_APPLICATION . strtolower(get_parent_class($this)) . '/' . $extension_type . '/' . $classname . '.inc');

		# Get Mod list
		$domain = rawurlencode(str_ireplace('www.', '', parse_url(HTTP_SERVER, PHP_URL_HOST)));
		$url = "https://opencartguru.com/index.php?route=feed/modlist&classname=$classname&domain=$domain";
		$ch = @curl_init();
		@curl_setopt ($ch, CURLOPT_URL, $url);
		@curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		@curl_setopt ($ch, CURLOPT_TIMEOUT, 10);
		@curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
		@curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = @curl_exec ($ch);
		@curl_close($ch);

		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		@$dom->loadXML($result);
		$products = @$dom->getElementsByTagName('product');
		$data['mods'] = array();
		foreach ($products as $i => $product) {
			$data['mods'][$i]['extension_id'] = $product->getElementsByTagName('extension_id')->item(0)->nodeValue;
			$data['mods'][$i]['title'] = $product->getElementsByTagName('title')->item(0)->nodeValue;
			$data['mods'][$i]['price'] = $product->getElementsByTagName('price')->item(0)->nodeValue;
			$data['mods'][$i]['date_added'] = date('Y-m-d', strtotime($product->getElementsByTagName('date_added')->item(0)->nodeValue));
			$data['mods'][$i]['img'] = $product->getElementsByTagName('img')->item(0)->nodeValue;
			$data['mods'][$i]['features'] = explode(';', $product->getElementsByTagName('features')->item(0)->nodeValue);
			$data['mods'][$i]['opencart_version'] = explode(',', $product->getElementsByTagName('opencart_version')->item(0)->nodeValue);
			$data['mods'][$i]['latest_version'] = $product->getElementsByTagName('latest_version')->item(0)->nodeValue;
			$data['mods'][$i]['ocg_link'] = $product->getElementsByTagName('ocg_link')->item(0)->nodeValue;
			if (is_numeric($data['mods'][$i]['extension_id'])) {
				$data['mods'][$i]['oc_link'] = $product->getElementsByTagName('oc_link')->item(0)->nodeValue;
			} else {
				$data['mods'][$i]['oc_link'] = $product->getElementsByTagName('oc_search_link')->item(0)->nodeValue;
			}
		}

			$data['header'] = $this->load->controller('common/header');
			$data['menu'] = $this->load->controller('common/menu');
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view($extension_type . '/'.$classname, $data));
	}



	private function validate($errors = array()) {
		$extension_type = 'extension/payment';
		$classname = str_replace('vq2-' . basename(DIR_APPLICATION) . '_' . strtolower(get_parent_class($this)) . '_' . $extension_type . '_', '', basename(__FILE__, '.php'));

		if (!$this->user->hasPermission('modify', $extension_type. '/' . $classname)) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		foreach ($errors as $error) {
			if (isset($this->request->post[$classname . '_' . $error]) && !$this->request->post[$classname . '_' . $error]) {
				$this->error[$error] = $this->language->get('error_' . $error);
			}
		}

		if (!$this->error) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
}
?>