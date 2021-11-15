<?php

/**
 * @property ModelCustomerpartnerDashboard $model_customerpartner_dashboard
 */
class Controllercustomerpartnerdashboardschart extends Controller {

	public function index() {
		$this->load->language('customerpartner/dashboards/chart');
		$data['user_token'] = session('user_token');
		if (isset($this->request->get['customer_id']) && $this->request->get['customer_id']) {
			$data['customer_id'] = $this->request->get['customer_id'];
		}

		// return $this->load->view('extension/dashboard/chart_info', $data);
		return $this->load->view('customerpartner/chart_info', $data);
	}

	public function chartinfo() {
		$this->load->language('customerpartner/dashboards/chart');
		$customer_id = $this->request->get['customer_id'];


		$json = array();

		$this->load->model('customerpartner/dashboard');
		// $this->load->model('report/customer');
		$data['user_token'] = session('user_token');

		$json['order'] = array();
		$json['customer'] = array();
		$json['xaxis'] = array();

		$json['order']['label'] = $this->language->get('text_order');
		$json['customer']['label'] = $this->language->get('text_customer');
		$json['order']['data'] = array();
		$json['customer']['data'] = array();

		if (isset($this->request->get['range'])) {
			$range = $this->request->get['range'];
		} else {
			$range = 'day';
		}

		switch ($range) {
			default:
			case 'day':
				$results = $this->model_customerpartner_dashboard->getTotalOrdersByDay($customer_id);

				foreach ($results as $key => $value) {
					$json['order']['data'][] = array($key, $value['total']);
				}

				$results = $this->model_customerpartner_dashboard->getTotalCustomersByDay($customer_id);

				foreach ($results as $key => $value) {
					$json['customer']['data'][] = array($key, $value['total']);
				}

				for ($i = 0; $i < 24; $i++) {
					$json['xaxis'][] = array($i, $i);
				}
				break;
			case 'week':
				$results = $this->model_customerpartner_dashboard->getTotalOrdersByWeek($customer_id);

				foreach ($results as $key => $value) {
					$json['order']['data'][] = array($key, $value['total']);
				}

				$results = $this->model_customerpartner_dashboard->getTotalCustomersByWeek($customer_id);

				foreach ($results as $key => $value) {
					$json['customer']['data'][] = array($key, $value['total']);
				}

				$date_start = strtotime('-' . date('w') . ' days');

				for ($i = 0; $i < 7; $i++) {
					$date = date('Y-m-d', $date_start + ($i * 86400));

					$json['xaxis'][] = array(date('w', strtotime($date)), date('D', strtotime($date)));
				}
				break;
			case 'month':
				$results = $this->model_customerpartner_dashboard->getTotalOrdersByMonth($customer_id);

				foreach ($results as $key => $value) {
					$json['order']['data'][] = array($key, $value['total']);
				}

				$results = $this->model_customerpartner_dashboard->getTotalCustomersByMonth($customer_id);

				foreach ($results as $key => $value) {
					$json['customer']['data'][] = array($key, $value['total']);
				}

				for ($i = 1; $i <= date('t'); $i++) {
					$date = date('Y') . '-' . date('m') . '-' . $i;

					$json['xaxis'][] = array(date('j', strtotime($date)), date('d', strtotime($date)));
				}
				break;
			case 'year':
				$results = $this->model_customerpartner_dashboard->getTotalOrdersByYear($customer_id);

				foreach ($results as $key => $value) {
					$json['order']['data'][] = array($key, $value['total']);
				}

				$results = $this->model_customerpartner_dashboard->getTotalCustomersByYear($customer_id);

				foreach ($results as $key => $value) {
					$json['customer']['data'][] = array($key, $value['total']);
				}

				for ($i = 1; $i <= 12; $i++) {
					$json['xaxis'][] = array($i, date('M', mktime(0, 0, 0, $i)));
				}
				break;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
?>
