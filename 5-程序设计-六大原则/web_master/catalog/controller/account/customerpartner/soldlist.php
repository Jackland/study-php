<?php

/**
 * @property ModelAccountCustomerGroup $model_account_customer_group
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 */
class ControllerAccountCustomerpartnerSoldlist extends Controller {

	private $error = array();
	private $data = array();

	public function index() {

		if (!$this->customer->isLogged()) {
			session()->set('redirect', $this->url->link('account/customerpartner/soldlist&product_id='.$this->request->get['product_id'], '', true));
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->model('account/customerpartner');

		$data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();

		if(!$data['chkIsPartner'] || (isset($this->session->data['marketplace_seller_mode']) && !$this->session->data['marketplace_seller_mode']))
			$this->response->redirect($this->url->link('account/account', '', true));

		if(isset($this->request->get['product_id']))
			$product_id = $this->request->get['product_id'];
		else
			$this->response->redirect($this->url->link('account/customerpartner/productlist', '', true));

		$this->load->language('account/customerpartner/soldlist');
		$this->document->setTitle($this->language->get('heading_title'));

      	$data['breadcrumbs'] = array();

      	$data['breadcrumbs'][] = array(
        	'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', '', true),
        	'separator' => false
      	);

      	$data['breadcrumbs'][] = array(
        	'text'      => $this->language->get('text_account'),
			'href'      => $this->url->link('account/account', '', true),
        	'separator' => $this->language->get('text_separator')
      	);

      	$data['breadcrumbs'][] = array(
        	'text'      => $this->language->get('text_productlist'),
			'href'      => $this->url->link('account/customerpartner/productlist', '', true),
        	'separator' => $this->language->get('text_separator')
      	);

      	$data['breadcrumbs'][] = array(
        	'text'      => $this->language->get('text_soldlist'),
			'href'      => $this->url->link('account/customerpartner/soldlist&product_id='.$product_id, '', true),
        	'separator' => $this->language->get('text_separator')
      	);


		if(isset($this->request->get['page'])){
			$page = $this->request->get['page'];
		}else{
			$page = 1;
		}

		if (isset($product_id)) {

			$data['product_id'] = $product_id;

			$orders = $this->model_account_customerpartner->getSellerOrdersByProduct($product_id,$page);

			$totalorders = $this->model_account_customerpartner->getSellerOrdersTotalByProduct($product_id);

			if($orders)
				foreach ($orders as $key => $value) {
					$orders[$key]['link'] = $this->url->link('account/customerpartner/orderinfo&order_id='.$value['order_id'], '', true);
					$orders[$key]['price'] = $this->currency->format($value['price'], $this->session->data['currency']);
					if($value['paid_status'] == 1) {
						$orders[$key]['paid_status']	=	$this->language->get('text_paid');
					} else {
						$orders[$key]['paid_status']	=	$this->language->get('text_no_paid');
					}
				}
			else
				$data['access_error'] = true;

			$url = '&product_id=' . $product_id;

			$pagination = new Pagination();
			$pagination->total = $totalorders;
			$pagination->page = $page;
			$pagination->limit = 10;
			$pagination->url = $this->url->link('account/customerpartner/soldlist', $url . '&page={page}', true);

			$data['pagination'] = $pagination->render();
			$data['results'] = sprintf($this->language->get('text_pagination'), ($totalorders) ? (($page - 1) * 10) + 1 : 0, ((($page - 1) * 10) > ($totalorders - 10)) ? $totalorders : ((($page - 1) * 10) + 10), $totalorders, ceil($totalorders / 10));

			$data['orders'] = $orders;

			if (isset($this->error['warning'])) {
				$data['error_warning'] = $this->error['warning'];
			} else {
				$data['error_warning'] = '';
			}

			if (isset($this->session->data['success'])) {
				$data['success'] = session('success');
				$this->session->remove('success');
			} else {
				$data['success'] = '';
			}

			$data['back'] = $this->url->link('account/customerpartner/productlist', '', true);

			$data['isMember'] = true;
			if($this->config->get('module_wk_seller_group_status')) {
	      		$data['module_wk_seller_group_status'] = true;
	      		$this->load->model('account/customer_group');
				$isMember = $this->model_account_customer_group->getSellerMembershipGroup($this->customer->getId());
				if($isMember) {
					$allowedAccountMenu = $this->model_account_customer_group->getaccountMenu($isMember['gid']);
					if($allowedAccountMenu['value']) {
						$accountMenu = explode(',',$allowedAccountMenu['value']);
						if($accountMenu && !in_array('orderhistory:orderhistory', $accountMenu)) {
							$data['isMember'] = false;
						}
					}
				} else {
					$data['isMember'] = false;
				}
	      	}

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

$data['separate_view'] = false;

$data['separate_column_left'] = '';

if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
  $data['separate_view'] = true;
  $data['column_left'] = '';
  $data['column_right'] = '';
  $data['content_top'] = '';
  $data['content_bottom'] = '';
  $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');

  $data['footer'] = $this->load->controller('account/customerpartner/footer');
  $data['header'] = $this->load->controller('account/customerpartner/header');
}

			$this->response->setOutput($this->load->view('account/customerpartner/soldlist' , $data));

		}
	}
}
?>
