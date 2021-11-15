<?php

/**
 * @property ModelAccountCustomerGroup $model_account_customer_group
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 */
class ControllerAccountCustomerpartnerDashboard extends Controller {

	private $error = array();
	private $data = array();

	public function index() {

		if (!$this->customer->isLogged()) {
			session()->set('redirect', $this->url->link('account/customerpartner/dashboard', '', true));
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->model('account/customerpartner');

		$this->data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();

		if(!$this->data['chkIsPartner'] || (isset($this->session->data['marketplace_seller_mode']) && !$this->session->data['marketplace_seller_mode']))
			$this->response->redirect($this->url->link('account/account', '', true));

    	$this->document->addStyle('catalog/view/theme/yzcTheme/stylesheet/MP/sell.css?v=' . APP_VERSION);

		$this->document->addStyle('catalog/view/theme/yzcTheme/stylesheet/MP/dashboard.css?v=' . APP_VERSION);

		$this->load->language('account/customerpartner/dashboard');

		$this->document->setTitle($this->language->get('heading_title_dashboard'));

		$this->data['breadcrumbs'] = array();

		$this->data['breadcrumbs'][] = array(
        	'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', '', true),
        	'separator' => false
      	);

      	$this->data['breadcrumbs'][] = array(
        	'text'      => $this->language->get('text_account'),
			'href'      => $this->url->link('account/account', '', true),
        	'separator' => $this->language->get('text_separator')
      	);

      	$this->data['breadcrumbs'][] = array(
        	'text'      => $this->language->get('text_dashboard'),
			'href'      => $this->url->link('account/customerpartner/dashboard', '', true),
        	'separator' => $this->language->get('text_separator')
      	);

		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$this->data['success'] = session('success');
			$this->session->remove('success');
		} else {
			$this->data['success'] = '';
		}

		$this->data['low_stock'] = $this->load->controller('account/customerpartner/dashboards/low_stock');
		// $this->data['sold'] = $this->load->controller('account/customerpartner/dashboards/sold');
		$this->data['order_status'] = $this->load->controller('account/customerpartner/dashboards/order_status');

      	$this->data['order'] = $this->load->controller('account/customerpartner/dashboards/order');
		$this->data['sale'] = $this->load->controller('account/customerpartner/dashboards/sale');
		$this->data['customer'] = $this->load->controller('account/customerpartner/dashboards/customer');
		// $this->data['seller_sale'] = '';
		$this->data['map'] = $this->load->controller('account/customerpartner/dashboards/map');
		$this->data['chart'] = $this->load->controller('account/customerpartner/dashboards/chart');
		// $this->data['activity'] = $this->load->controller('account/customerpartner/dashboards/activity');
		$this->data['recent'] = $this->load->controller('account/customerpartner/dashboards/recent');

      	$this->data['column_left'] = $this->load->controller('common/column_left');
		$this->data['column_right'] = $this->load->controller('common/column_right');
		$this->data['content_top'] = $this->load->controller('common/content_top');
		$this->data['content_bottom'] = $this->load->controller('common/content_bottom');
		$this->data['footer'] = $this->load->controller('common/footer');
		$this->data['header'] = $this->load->controller('common/header');

$this->data['separate_view'] = false;

$this->data['separate_column_left'] = '';

if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
  $this->data['separate_view'] = true;
  $this->data['column_left'] = '';
  $this->data['column_right'] = '';
  $this->data['content_top'] = '';
  $this->data['content_bottom'] = '';
  $this->data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');

  $this->data['footer'] = $this->load->controller('account/customerpartner/footer');
  $this->data['header'] = $this->load->controller('account/customerpartner/header');
}
		$this->data['isMember'] = true;
		if($this->config->get('module_wk_seller_group_status')) {
      		$data['module_wk_seller_group_status'] = true;
      		$this->load->model('account/customer_group');
			$isMember = $this->model_account_customer_group->getSellerMembershipGroup($this->customer->getId());
			if($isMember) {
				$allowedAccountMenu = $this->model_account_customer_group->getaccountMenu($isMember['gid']);
				if($allowedAccountMenu['value']) {
					$accountMenu = explode(',',$allowedAccountMenu['value']);
					if($accountMenu && !in_array('dashboard:dashboard', $accountMenu)) {
						$this->data['isMember'] = false;
					}
				}
			} else {
				$this->data['isMember'] = false;
			}
      	} else {
      		if(!is_array($this->config->get('marketplace_allowed_account_menu')) || !in_array('dashboard', $this->config->get('marketplace_allowed_account_menu'))) {
      			$this->response->redirect($this->url->link('account/account','', true));
      		}
      	}

		$this->response->setOutput($this->load->view('account/customerpartner/dashboard' , $this->data));

	}

	public function changereview(){

		$this->load->language('account/customerpartner/dashboard');

		$this->load->model('account/customerpartner');

		$json = array();

		if (request()->isMethod('POST') AND $this->customer->getID()) {

			if ($this->model_account_customerpartner->chkIsPartner() AND isset($this->request->post['review'])) {
				$latestcomment=$this->model_account_customerpartner->UpdateReview($this->request->post['review']);
				$json['success'] = $this->language->get('text_change_review');
			}else{
				$json['error'] = $this->language->get('text_error');
			}

		}

		$this->response->setOutput(json_encode($json));

	}
}
?>
