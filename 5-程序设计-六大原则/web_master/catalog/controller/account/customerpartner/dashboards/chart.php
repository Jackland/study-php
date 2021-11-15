<?php

/**
 * @property ModelCustomerpartnerDashboard $model_customerpartner_dashboard
 */
class ControllerAccountCustomerpartnerDashboardsChart extends Controller {

	public function index() {
		$this->load->language('account/customerpartner/dashboards/chart');

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_day'] = $this->language->get('text_day');
		$data['text_week'] = $this->language->get('text_week');
		$data['text_month'] = $this->language->get('text_month');
		$data['text_year'] = $this->language->get('text_year');
		$data['text_view'] = $this->language->get('text_view');
		if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
			$data['separate_view'] = true;
		}
		return ($this->load->view('account/customerpartner/dashboards/chart' , $data));
	}

	public function chart() {
		$this->load->language('account/customerpartner/dashboards/chart');

		$json = array();

		$this->load->model('customerpartner/dashboard');

		if (isset($this->request->get['range'])) {
			$range = $this->request->get['range'];
		} else {
			$range = 'day';
		}

		switch ($range) {
			default:
			case 'day':
				$json[] = array('Hour', $this->language->get('text_order'), $this->language->get('text_customer'));

				$results_order = $this->model_customerpartner_dashboard->getTotalOrdersByDay();

				$results_customer = $this->model_customerpartner_dashboard->getTotalCustomersByDay();

				for ($i = 0; $i < 24; $i++) {
					$order = 0;

					$customer = 0;

					$date = date('Y') . '-' . date('m') . '-' . $i;

					if (isset($results_order[$i]['total'])) {
						$order = $results_order[$i]['total'];
					}

					if (isset($results_customer[$i]['total'])) {
						$customer = $results_customer[$i]['total'];
					}

					$json[$i+1] = array($i, $order, $customer);
				}

				break;
			case 'week':
				$json[] = array('Day', $this->language->get('text_order'), $this->language->get('text_customer'));

				$results_order = $this->model_customerpartner_dashboard->getTotalOrdersByWeek();

				$results_customer = $this->model_customerpartner_dashboard->getTotalCustomersByWeek();

				for ($i = 0; $i < 7; $i++) {
					$order = 0;

					$customer = 0;

					$date_start = strtotime('-' . date('w') . ' days');

					$date = date('Y-m-d', $date_start + ($i * 86400));

					if (isset($results_order[$i]['total'])) {
						$order = $results_order[$i]['total'];
					}

					if (isset($results_customer[$i]['total'])) {
						$customer = $results_customer[$i]['total'];
					}

					$json[$i+1] = array(date('D', strtotime($date)), $order, $customer);
				}

				break;
			case 'month':
				$json[] = array('Date', $this->language->get('text_order'), $this->language->get('text_customer'));

				$results_order = $this->model_customerpartner_dashboard->getTotalOrdersByMonth();

				$results_customer = $this->model_customerpartner_dashboard->getTotalCustomersByMonth();

				for ($i = 1; $i <= date('t'); $i++) {
					$order = 0;

					$customer = 0;

					$date = date('Y') . '-' . date('m') . '-' . $i;

					if (isset($results_order[$i]['total'])) {
						$order = $results_order[$i]['total'];
					}

					if (isset($results_customer[$i]['total'])) {
						$customer = $results_customer[$i]['total'];
					}

					$json[$i] = array(date('d', strtotime($date)), $order, $customer);
				}

				break;
			case 'year':
				$json[] = array('Month', $this->language->get('text_order'), $this->language->get('text_customer'));

				$results_order = $this->model_customerpartner_dashboard->getTotalOrdersByYear();

				$results_customer = $this->model_customerpartner_dashboard->getTotalCustomersByYear();

				for ($i = 1; $i <= 12; $i++) {
					$order = 0;

					$customer = 0;

					if (isset($results_order[$i]['total'])) {
						$order = $results_order[$i]['total'];
					}

					if (isset($results_customer[$i]['total'])) {
						$customer = $results_customer[$i]['total'];
					}
					$json[$i] = array(date('M', mktime(0, 0, 0, $i)), $order, $customer);
				}

				break;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
