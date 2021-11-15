<?php

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 */
class ControllerAccountCustomerpartnerDashboardsOrder extends Controller {

	public function index() {
		// return;

		$this->load->language('account/customerpartner/dashboards/order');

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_view'] = $this->language->get('text_view');

		// Total Orders
		$this->load->model('account/customerpartner');

		$today = $this->model_account_customerpartner->getSellerOrdersTotal(array('filter_date' => date('Y-m-d', strtotime('-1 day'))));

		$yesterday = $this->model_account_customerpartner->getSellerOrdersTotal(array('filter_date' => date('Y-m-d', strtotime('-2 day'))));

		$difference = $today - $yesterday;

		if ($difference && $today) {
			$data['percentage'] = round(($difference / $today) * 100);
		} else {
			$data['percentage'] = 0;
		}

		$order_total = $this->model_account_customerpartner->getSellerOrdersTotal();

		if ($order_total > 1000000000000) {
			$data['total'] = round($order_total / 1000000000000, 1) . 'T';
		} elseif ($order_total > 1000000000) {
			$data['total'] = round($order_total / 1000000000, 1) . 'B';
		} elseif ($order_total > 1000000) {
			$data['total'] = round($order_total / 1000000, 1) . 'M';
		} elseif ($order_total > 1000) {
			$data['total'] = round($order_total / 1000, 1) . 'K';
		} else {
			$data['total'] = $order_total;
		}
		if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
			$data['separate_view'] = true;
		}
		$data['order'] = $this->url->link('account/customerpartner/orderlist', '', true);

		return ($this->load->view('account/customerpartner/dashboards/order' , $data));
	}
}
