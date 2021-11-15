<?php

/**
 * @property ModelCustomerpartnerDashboard $model_customerpartner_dashboard
 */
class Controllercustomerpartnerdashboardsmap extends Controller {

	public function index() {
		$this->load->language('customerpartner/dashboards/map');

		$data['heading_title'] = $this->language->get('heading_title');
		$data['customer_id'] = $this->request->get['customer_id'];
		$data['text_order'] = $this->language->get('text_order');
		$data['text_sale'] = $this->language->get('text_sale');
		$data['user_token'] = session('user_token');	
		$data['ajax_url']  = 'index.php?route=customerpartner/dashboards/map/map&user_token='. session('user_token') . '&customer_id=' . $this->request->get['customer_id'];
		return $this->load->view('customerpartner/map', $data);
	}
	
	public function map() {
		$json = array();
		
		$this->load->model('customerpartner/dashboard');
		$customer_id = $this->request->get['customer_id'];
		$results = $this->model_customerpartner_dashboard->getTotalOrdersByCountry($customer_id);
		foreach ($results as $result) {
			$json[strtolower($result['iso_code_2'])] = array(
				'total'  => $result['total'],
				'amount' => $this->currency->format($result['amount'],$this->config->get('config_currency'))
			);
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

}