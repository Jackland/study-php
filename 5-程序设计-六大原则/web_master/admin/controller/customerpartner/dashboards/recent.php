<?php

/**
 * @property Modelcustomerpartnercustomerpartner $model_customerpartner_customerpartner
 * @property ModelCustomerpartnerDashboard $model_customerpartner_dashboard
 */
class Controllercustomerpartnerdashboardsrecent extends Controller {

	public function index() {

		$this->load->language('customerpartner/dashboards/recent');
		$this->load->model('customerpartner/customerpartner');
		$this->load->model('customerpartner/dashboard');
		// Last 5 Orders
		$data['orders'] = array();

		$filter_data = array(
			'sort'  => 'o.date_added',
			'order' => 'DESC',
			'start' => 0,
			'limit' => 5
		);
		$customer_id = $this->request->get['customer_id'];
		$results = $this->model_customerpartner_customerpartner->getSellerOrders($filter_data,$customer_id);

		foreach ($results as $result) {
			$order_total = $this->model_customerpartner_dashboard->getTotalSales(array('filter_order_id' => $result['order_id']),$customer_id);

			$data['orders'][] = array(
				'order_id'   => $result['order_id'],
				'customer'   => $result['name'],
				'status'     => $result['orderstatus'],
				'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'total'      => $this->currency->format($order_total['total'], $result['currency_code'], $result['currency_value']),
				'view'       => $this->url->link('sale/order/info','user_token='. session('user_token') . '&order_id=' . $result['order_id'], '' ,true),
			);
		}

		return $this->load->view('extension/dashboard/recent_info', $data);
	}
}
