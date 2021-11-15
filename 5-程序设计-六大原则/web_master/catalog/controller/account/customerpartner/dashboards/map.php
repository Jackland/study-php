<?php

/**
 * @property ModelCustomerpartnerDashboard $model_customerpartner_dashboard
 */
class ControllerAccountCustomerpartnerDashboardsMap extends Controller {

	public function index() {
		$this->load->language('account/customerpartner/dashboards/map');

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_order'] = $this->language->get('text_order');
		$data['text_sale'] = $this->language->get('text_sale');
		if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
		  $data['separate_view'] = true;
		}

		return ($this->load->view('account/customerpartner/dashboards/map' , $data));
	}

	public function map() {

		$json = array();

		$this->load->model('customerpartner/dashboard');

		$results = $this->model_customerpartner_dashboard->getTotalOrdersByCountry();

		foreach ($results as $result) {
			$json[strtolower($result['iso_code_2'])] = array(
				'total'  => $result['total'],
				'amount' => $this->currency->format($result['amount'], $this->session->data['currency'])
			);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
