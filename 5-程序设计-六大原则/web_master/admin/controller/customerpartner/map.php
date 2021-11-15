<?php

/**
 * @property ModelCustomerpartnerDashboard $model_customerpartner_dashboard
 */
class Controllercustomerpartnermap extends Controller {

	public function index() {
		$this->load->language('customerpartner/dashboards/map');

		$data['customer_id'] = $this->request->get['customer_id'];
	
		$data['user_token'] = session('user_token');
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
				'amount' => $this->currency->format($result['amount'], $this->config->get('config_currency'))
			);
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

}
