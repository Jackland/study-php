<?php

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelCustomerpartnerDashboard $model_customerpartner_dashboard
 */
class ControllerAccountCustomerpartnerDashboardsLowstock extends Controller {

	public function index() {
		
		$data = $this->load->language('account/customerpartner/dashboards/low_stock');

		// Total low_stocks
		$this->load->model('customerpartner/dashboard');
		$this->load->model('account/customerpartner');

		$getLowStockProducts = $this->model_account_customerpartner->getLowStockProducts($this->customer->getId());

		if ($getLowStockProducts) {
		    
		    $data['low_stock_quantity'] = $getLowStockProducts['count'];	
		}else{
			$data['low_stock_quantity'] = 0;
		}

		$data['low_stock_view_more'] = trim($this->url->link('account/customerpartner/productlist&low_stock', '', true), '=');	
						
		return ($this->load->view('account/customerpartner/dashboards/low_stock' , $data));
	}
}
