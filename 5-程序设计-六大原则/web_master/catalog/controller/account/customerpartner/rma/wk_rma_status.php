<?php
/**
 * Webkul Software.
 * @category  Webkul
 * @author    Webkul
 * @copyright Copyright (c) 2010-2016 Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 * @property ModelAccountCustomerGroup $model_account_customer_group
 * @property ModelAccountRmawkrmaadmin $model_account_rma_wk_rma_admin
 * @property ModelLocalisationLanguage $model_localisation_language
 */
class ControllerAccountCustomerpartnerRmaWkrmaStatus extends Controller {

	private $error = array();
	use RmaControllerTrait;

	public function index() {

		if (!$this->config->get('wk_rma_status') || !$this->customer->getId())
			$this->response->redirect($this->urlChange('account/account', '', true));
		$this->CheckPermission();
		$data = $this->load->language('account/rma/wk_rma_status');

		$filter_array = array(
							  'filter_name',
							  'filter_status',
							  'filter_assign',
							  'filter_ostatus', // order status
							  'page',
							  'sort',
							  'order',
							  'start',
							  'limit',
							  );

		$url = '';

		foreach ($filter_array as $unsetKey => $key) {

			if (isset($this->request->get[$key])) {
				$filter_array[$key] = $this->request->get[$key];
			} else {
				if ($key=='page')
					$filter_array[$key] = 1;
				elseif($key=='sort')
					$filter_array[$key] = 'cs.id';
				elseif($key=='order')
					$filter_array[$key] = 'ASC';
				elseif($key=='start')
					$filter_array[$key] = ($filter_array['page'] - 1) * $this->config->get('config_limit_admin');
				elseif($key=='limit')
					$filter_array[$key] = $this->config->get('config_limit_admin');
				else
					$filter_array[$key] = null;
			}
			unset($filter_array[$unsetKey]);

			if(isset($this->request->get[$key])){
				if ($key=='filter_name')
					$url .= '&'.$key.'=' . urlencode(html_entity_decode($filter_array[$key], ENT_QUOTES, 'UTF-8'));
				else
					$url .= '&'.$key.'='. $filter_array[$key];
			}
		}

		$this->load->language('account/rma/wk_rma_status');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('account/rma/wk_rma_admin');

		$data['breadcrumbs'] = array();

 		$data['breadcrumbs'][] = array(
     		'text'      => $this->language->get('text_home'),
				'href'      => $this->urlChange('account/account', ''.$url, 'SSL'),
    		'separator' => false
 		);

 		$data['breadcrumbs'][] = array(
     		'text'      => $this->language->get('heading_title'),
				'href'      => $this->urlChange('account/customerpartner/rma/wk_rma_status', ''.$url , 'SSL'),
    		'separator' => ' :: '
 		);

		$status_total = $this->model_account_rma_wk_rma_admin->viewtotalStatus($filter_array,$this->customer->getId());

		$results = $this->model_account_rma_wk_rma_admin->viewStatus($filter_array,$this->customer->getId());

		$data['result'] = array();

		foreach ($results as $result) {
			$action = array();
			$action = array(
				'text' => $this->language->get('text_edit'),
				'href' => $this->urlChange('account/customerpartner/rma/wk_rma_status/update', '' .'&id=' . $result['status_id'], 'SSL')
			);

			$data['result'][] = array(
				'selected'=>false,
				'id' 			=> $result['status_id'],
				'name' 		=> $result['name'],
				'status' 	=> $result['status'],
				'color' 	=> $result['color'],
				'action'  => $action
				);

		}

		$data['delete'] = $this->urlChange('account/customerpartner/rma/wk_rma_status/delete', '', 'SSL');
		$data['insert'] = $this->urlChange('account/customerpartner/rma/wk_rma_status/insert', '', 'SSL');
		$this->load->model('account/rma/wk_rma_admin');

		$data['defaultRmaStatus'] = $this->model_account_rma_wk_rma_admin->defaultRmaStatus($this->customer->getID());
		$data['solveRmaStatus'] = $this->model_account_rma_wk_rma_admin->solveRmaStatus($this->customer->getID());
		$data['cancelRmaStatus'] = $this->model_account_rma_wk_rma_admin->cancelRmaStatus($this->customer->getID());

 		$data['token'] = '';

 		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = session('error_warning');
			$this->session->remove('error_warning');
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = session('success');
			$this->session->remove('success');
		} else {
			$data['success'] = '';
		}

		$url = '';

		foreach ($filter_array as $key => $value) {
			if(isset($this->request->get[$key])){
				if(!isset($this->request->get['order']) AND isset($this->request->get['sort']))
					$url .= '&order=DESC';
				if ($key=='filter_name')
					$url .= '&'.$key.'=' . urlencode(html_entity_decode($filter_array[$key], ENT_QUOTES, 'UTF-8'));
				elseif($key=='order')
					$url .= $value=='ASC' ? '&order=DESC' : '&order=ASC';
				elseif($key!='start' AND $key!='limit' AND $key!='sort')
					$url .= '&'.$key.'='. $filter_array[$key];
			}
		}

		$data['sort_name'] = $this->urlChange('account/customerpartner/rma/wk_rma_status', '' . '&sort=wrs.name' . $url, 'SSL');
		$data['sort_status'] = $this->urlChange('account/customerpartner/rma/wk_rma_status', '' . '&sort=wrs.status' . $url, 'SSL');
		$data['sort_assign'] = $this->urlChange('account/customerpartner/rma/wk_rma_status', '' . '&sort=wrs.id' . $url, 'SSL');

		$url = '';

		foreach ($filter_array as $key => $value) {
			if(isset($this->request->get[$key])){
				if(!isset($this->request->get['order']) AND isset($this->request->get['sort']))
					$url .= '&order=DESC';
				if ($key=='filter_name')
					$url .= '&'.$key.'=' . urlencode(html_entity_decode($filter_array[$key], ENT_QUOTES, 'UTF-8'));
				elseif($key!='page')
					$url .= '&'.$key.'='. $filter_array[$key];
			}
		}

		$limit = $this->config->get('config_limit_admin') ? $this->config->get('config_limit_admin') : 20;

		$pagination = new Pagination();
		$pagination->total = $status_total;
		$pagination->page = $filter_array['page'];
		$pagination->limit = $limit;
		$pagination->text = $this->language->get('text_pagination');
		$pagination->url = $this->urlChange('account/customerpartner/rma/wk_rma_status', '' . $url . '&page={page}', 'SSL');

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), ($status_total) ? (($filter_array['page'] - 1) * $limit) + 1 : 0, ((($filter_array['page'] - 1) * $limit) > ($status_total - $limit)) ? $status_total : ((($filter_array['page'] - 1) * $limit) + $limit), $status_total, ceil($status_total / $limit));

		foreach ($filter_array as $key => $value) {
			if($key!='start' AND $key!='end')
				$data[$key] = $value;
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/customerpartner/rma/wk_rma_status', $data));
  }

  public function insert() {

		if (!$this->config->get('wk_rma_status') || !$this->customer->getId())
			$this->response->redirect($this->urlChange('account/account', '', true));
		$this->CheckPermission();
    $this->load->language('account/rma/wk_rma_status');
    $this->document->setTitle($this->language->get('heading_title_insert'));
		$data['heading_title'] = $this->language->get('heading_title_insert');
		$this->load->model('account/rma/wk_rma_admin');
    if ((request()->isMethod('POST'))) {
  		foreach ($this->request->post['name'] as $key => $value) {
  			if ((utf8_strlen(trim($value)) < 5) || (utf8_strlen(trim($value)) > 50)) {
					$error = $this->language->get('error_name');
					break;
			  }
  	  }
    	$this->request->post['admin'] = 1;
			if (!isset($error)) {
				$this->model_account_rma_wk_rma_admin->addStatus($this->request->post,$this->customer->getId());
				session()->set('success', $this->language->get('text_success_insert'));
				$this->response->redirect($this->urlChange('account/customerpartner/rma/wk_rma_status', '' , 'SSL'));
			}
			$this->error['warning'] = $error ;
    }

    $this->getfrom();
  }

  public function update() {

		if (!$this->config->get('wk_rma_status') || !$this->customer->getId())
			$this->response->redirect($this->urlChange('account/account', '', true));
		$this->CheckPermission();
    $this->load->language('account/rma/wk_rma_status');
    $this->document->setTitle($this->language->get('heading_title_insert'));
		$data['heading_title'] = $this->language->get('heading_title_update');
		$this->load->model('account/rma/wk_rma_admin');

    if ((request()->isMethod('POST'))) {
  		foreach ($this->request->post['name'] as $key => $value) {
  			if ((utf8_strlen(trim($value)) < 5) || (utf8_strlen(trim($value)) > 50)) {
					$error = $this->language->get('error_name');
					break;
				}
  		}
    	$this->request->post['admin'] = 1;
			if (!isset($error)) {
				$this->model_account_rma_wk_rma_admin->UpdateStatus($this->request->post,$this->customer->getId());
				session()->set('success', $this->language->get('text_success_update'));
				$this->response->redirect($this->urlChange('account/customerpartner/rma/wk_rma_status', '' , 'SSL'));
			}
			$this->error['warning'] = $error ;
    }
    $this->getfrom();
  }

  private function getfrom() {

		$data = $this->load->language('account/rma/wk_rma_status');
		$this->CheckPermission();
  	$data['breadcrumbs'] = array();
   	$data['breadcrumbs'][] = array(
      'text'      => $this->language->get('text_home'),
			'href'      => $this->urlChange('account/account', '', 'SSL'),
      'separator' => false
   	);
   	$data['breadcrumbs'][] = array(
      'text'      => $this->language->get('heading_title'),
			'href'      => $this->urlChange('account/customerpartner/rma/wk_rma_status', '' , 'SSL'),
      'separator' => ' :: '
    );

		// $this->document->addScript('catalog/view/javascript/jscolor/js/colorpicker.js');
		// $this->document->addStyle('catalog/view/javascript/jscolor/css/colorpicker.css');

		$config_data = array(
				'id',
				'name',
				'status',
				'admin',
				'color'
			);

		foreach ($config_data as $conf) {
			if (isset($this->request->post[$conf])) {
				$data[$conf] = $this->request->post[$conf];
			}
		}

		$data['save'] = $this->urlChange('account/customerpartner/rma/wk_rma_status/insert', '', 'SSL');

		if(isset($this->request->get['id'])){
			$id = $this->request->get['id'];

			$results = $this->model_account_rma_wk_rma_admin->viewStatusbyId($id,$this->customer->getId());

			if($results){
				foreach ($results as $key => $result) {
					$data['id'] = $result['status_id'];
					$data['status'] = $result['status'];
					$data['color'] = $result['color'];
					$data['name'][$result['language_id']] = $result['name'];
				}
			}
			$data['text_form'] = $this->language->get('text_edit_form');
			$data['save'] = $this->urlChange('account/customerpartner/rma/wk_rma_status/update', '', 'SSL');
		}

		if(!empty($data['id'])){
			$data['text_form'] = $this->language->get('text_edit_form');
			$data['save'] = $this->urlChange('account/customerpartner/rma/wk_rma_status/update', '' ,'SSL');
		}

		$data['back'] = $this->urlChange('account/customerpartner/rma/wk_rma_status', '', 'SSL');

 		$data['token'] = '';

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

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/customerpartner/rma/wk_rma_status_form', $data));
  	}

  public function delete() {

		if (!$this->config->get('wk_rma_status') || !$this->customer->getId())
			$this->response->redirect($this->urlChange('account/account', '', true));
		$this->CheckPermission();
    $this->load->language('account/rma/wk_rma_status');
    $this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('account/rma/wk_rma_admin');
		if (isset($this->request->post['selected'])) {
			foreach ($this->request->post['selected'] as $id) {
				$this->model_account_rma_wk_rma_admin->deleteStatus((int)$id,$this->customer->getId());
	  	}
			session()->set('success', $this->language->get('text_success'));
			$url='';
			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}
			$this->response->redirect($this->urlChange('account/customerpartner/rma/wk_rma_status', '' . $url, 'SSL'));
		} else {
			session()->set('error_warning', $this->language->get('error_selected'));
		}
    $this->index();
  }

	public function approveAdminStatus() {

		if (!$this->config->get('wk_rma_status') || !$this->customer->getId())
			$this->response->redirect($this->urlChange('account/account', '', true));
		$this->CheckPermission();
		$json = array();
		$this->load->model('account/rma/wk_rma_admin');
		if (isset($this->request->post['id']) && isset($this->request->post['approve']) && $this->config->get('wk_rma_seller_return_separate') && $this->model_account_rma_wk_rma_admin->verifyStatusForSeller($this->request->post['id'],$this->customer->getId())) {
			$json['success'] = $this->model_account_rma_wk_rma_admin->approveAdminStatus($this->request->post,$this->customer->getId());
		} else {
			$this->load->language('account/rma/wk_rma_status');
			session()->set('error_warning', $this->language->get('error_permission'));
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function approveSolveStatus() {

		if (!$this->config->get('wk_rma_status') || !$this->customer->getId())
			$this->response->redirect($this->urlChange('account/account', '', true));
		$this->CheckPermission();
		$json = array();
		$this->load->model('account/rma/wk_rma_admin');
		if (isset($this->request->post['id']) && isset($this->request->post['approve']) && $this->config->get('wk_rma_seller_return_separate') && $this->model_account_rma_wk_rma_admin->verifyStatusForSeller($this->request->post['id'],$this->customer->getId())) {
			$json['success'] = $this->model_account_rma_wk_rma_admin->approveSolveStatus($this->request->post,$this->customer->getId());
		} else {
			$this->load->language('account/rma/wk_rma_status');
			session()->set('error_warning', $this->language->get('error_permission'));
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function approveCancelStatus() {

		if (!$this->config->get('wk_rma_status') || !$this->customer->getId())
			$this->response->redirect($this->urlChange('account/account', '', true));
		$this->CheckPermission();
		$json = array();
		$this->load->model('account/rma/wk_rma_admin');
		if (isset($this->request->post['id']) && isset($this->request->post['approve']) && $this->config->get('wk_rma_seller_return_separate') && $this->model_account_rma_wk_rma_admin->verifyStatusForSeller($this->request->post['id'],$this->customer->getId())) {
			$json['success'] = $this->model_account_rma_wk_rma_admin->approveCancelStatus($this->request->post,$this->customer->getId());
		} else {
			$this->load->language('account/rma/wk_rma_status');
			session()->set('error_warning', $this->language->get('error_permission'));
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function CheckPermission() {
		$this->load->model('account/customerpartner');
		$data['isMember'] = $this->model_account_customerpartner->chkIsPartner();
		if($this->config->get('wk_seller_group_status')) {
			$data['wk_seller_group_status'] = true;
			$this->load->model('account/customer_group');
			$isMember = $this->model_account_customer_group->getSellerMembershipGroup($this->customer->getId());
			if($isMember) {
				$allowedAccountMenu = $this->model_account_customer_group->getaccountMenu($isMember['gid']);
				if($allowedAccountMenu['value']) {
					$accountMenu = explode(',',$allowedAccountMenu['value']);
					if($accountMenu && !in_array('status_rma:status_rma', $accountMenu)) {
						$data['isMember'] = false;
					}
				}
			} else {
				$data['isMember'] = false;
			}
		} else {
			if(!in_array('status_rma', $this->config->get('marketplace_allowed_account_menu'))) {
				$this->response->redirect($this->url->link('account/account','', true));
			}
		}
		if (!$data['isMember']) {
			$this->response->redirect($this->url->link('account/account','', true));
		}
		return $data['isMember'];
	}

}
