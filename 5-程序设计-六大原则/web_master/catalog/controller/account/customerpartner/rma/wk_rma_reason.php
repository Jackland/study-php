<?php
/**
 * Webkul Software.
 * @category  Webkul
 * @author    Webkul
 * @copyright Copyright (c) 2010-2016 Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 * @property ModelAccountCustomerGroup $model_account_customer_group
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountRmawkrmaadmin $model_account_rma_wk_rma_admin
 * @property ModelLocalisationLanguage $model_localisation_language
 */
class ControllerAccountCustomerpartnerRmaWkrmaReason extends Controller {

	private $error = array();
	use RmaControllerTrait;

	public function index() {

		if (!$this->config->get('wk_rma_status') || !$this->customer->getId())
			$this->response->redirect($this->urlChange('account/account', '', true));
		$this->CheckPermission();
		$data = $this->load->language('account/rma/wk_rma_reason');

		$filter_array = array(
		  'filter_reason',
		  'filter_status',
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
				if ($key=='filter_reason')
					$url .= '&'.$key.'=' . urlencode(html_entity_decode($filter_array[$key], ENT_QUOTES, 'UTF-8'));
				else
					$url .= '&'.$key.'='. $filter_array[$key];
			}
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('account/rma/wk_rma_admin');

		$data['breadcrumbs'] = array();

 		$data['breadcrumbs'][] = array(
     		'text'      => $this->language->get('text_home'),
				'href'      => $this->urlChange('account/account', ''.$url , 'SSL'),
    		'separator' => false
 		);

 		$data['breadcrumbs'][] = array(
     		'text'      => $this->language->get('heading_title'),
				'href'      => $this->urlChange('account/customerpartner/rma/wk_rma_reason', ''.$url , 'SSL'),
    		'separator' => ' :: '
 		);

		$reason_total = $this->model_account_rma_wk_rma_admin->viewtotalreason($filter_array,$this->customer->getId());

		$results = $this->model_account_rma_wk_rma_admin->viewreason($filter_array,$this->customer->getId());

		$data['result'] = array();

		foreach ($results as $result) {
			$action = array();

			$action = array(
				'text' => $this->language->get('text_edit'),
				'href' => $this->urlChange('account/customerpartner/rma/wk_rma_reason/update', '' .'&id=' . $result['reason_id'], 'SSL')
			);

			$data['result'][] = array(
				'selected'=>False,
				'id' => $result['reason_id'],
				'reason' => $result['reason'],
				'status' => $result['status'],
				'action' => $action
			);

		}

		$data['delete'] = $this->urlChange('account/customerpartner/rma/wk_rma_reason/delete', '', 'SSL');
		$data['insert'] = $this->urlChange('account/customerpartner/rma/wk_rma_reason/insert', '', 'SSL');

 		$data['token'] = '';

 		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['warning'])) {
			$data['error_warning'] = session('warning');
			$this->session->remove('warning');
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
				elseif ($key=='filter_reason')
					$url .= '&'.$key.'=' . urlencode(html_entity_decode($filter_array[$key], ENT_QUOTES, 'UTF-8'));
				elseif($key=='order')
					$url .= $value=='ASC' ? '&order=DESC' : '&order=ASC';
				elseif($key!='start' AND $key!='limit' AND $key!='sort')
					$url .= '&'.$key.'='. $filter_array[$key];
			}
		}

		$data['sort_reason'] = $this->urlChange('account/customerpartner/rma/wk_rma_reason', '' . '&sort=wrr.reason' . $url, 'SSL');
		$data['sort_status'] = $this->urlChange('account/customerpartner/rma/wk_rma_reason', '' . '&sort=wrr.status' . $url, 'SSL');

		$url = '';

		foreach ($filter_array as $key => $value) {
			if(isset($this->request->get[$key])){
				if(!isset($this->request->get['order']) AND isset($this->request->get['sort']))
					$url .= '&order=DESC';
				elseif ($key=='filter_reason')
					$url .= '&'.$key.'=' . urlencode(html_entity_decode($filter_array[$key], ENT_QUOTES, 'UTF-8'));
				elseif($key!='page')
					$url .= '&'.$key.'='. $filter_array[$key];
			}
		}

		$limit = $this->config->get('config_limit_admin') ? $this->config->get('config_limit_admin') : 20;

		$pagination = new Pagination();
		$pagination->total = $reason_total;
		$pagination->page = $filter_array['page'];
		$pagination->limit = $limit;
		$pagination->text = $this->language->get('text_pagination');
		$pagination->url = $this->urlChange('account/customerpartner/rma/wk_rma_reason', '' . '&page={page}', 'SSL');

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), ($reason_total) ? (($filter_array['page'] - 1) * $limit) + 1 : 0, ((($filter_array['page'] - 1) * $limit) > ($reason_total - $limit)) ? $reason_total : ((($filter_array['page'] - 1) * $limit) + $limit), $reason_total, ceil($reason_total / $limit));

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

		$this->response->setOutput($this->load->view('account/customerpartner/rma/wk_rma_reason', $data));
  }

  public function insert() {

		if (!$this->config->get('wk_rma_status') || !$this->customer->getId())
			$this->response->redirect($this->urlChange('account/account', '', true));
		$this->CheckPermission();
    $this->load->language('account/rma/wk_rma_reason');
		$this->document->setTitle($this->language->get('heading_title_insert'));
		$data['heading_title'] = $this->language->get('heading_title_insert');
		$this->load->model('account/rma/wk_rma_admin');

    if ((request()->isMethod('POST'))) {
    	foreach ($this->request->post['reason'] as $key => $value) {
    		if ((utf8_strlen(trim($value)) < 5) || (utf8_strlen(trim($value)) > 50)) {
					$error = $this->language->get('error_reason');
					break;
				}
	    }

			if (!isset($error)) {
				$this->model_account_rma_wk_rma_admin->addReason($this->request->post,$this->customer->getId());
				session()->set('success', $this->language->get('text_success_insert'));
				$this->response->redirect($this->urlChange('account/customerpartner/rma/wk_rma_reason', '' , 'SSL'));
			}
			$this->error['warning'] = $error ;
		}
		$this->getform();
	}

  public function update() {

		if (!$this->config->get('wk_rma_status') || !$this->customer->getId())
			$this->response->redirect($this->urlChange('account/account', '', true));
		$this->CheckPermission();
		$this->load->language('account/rma/wk_rma_reason');
    $this->document->setTitle($this->language->get('heading_title_insert'));
		$data['heading_title'] = $this->language->get('heading_title_update');
		$this->load->model('account/rma/wk_rma_admin');
    if ((request()->isMethod('POST'))) {
    	foreach ($this->request->post['reason'] as $key => $value) {
    		if ((utf8_strlen(trim($value)) < 5) || (utf8_strlen(trim($value)) > 50)) {
					$error = $this->language->get('error_reason');
					break;
				}
	    }
			if (!isset($error)) {
				$this->model_account_rma_wk_rma_admin->UpdateReason($this->request->post,$this->customer->getId());
				session()->set('success', $this->language->get('text_success_update'));
				$this->response->redirect($this->urlChange('account/customerpartner/rma/wk_rma_reason', '' , 'SSL'));
			}
			$this->error['warning'] = $error ;
	  }
    $this->getform();
  }

  private function getform() {

		$data = $this->load->language('account/rma/wk_rma_reason');
		$this->CheckPermission();
  	$data['breadcrumbs'] = array();

   	$data['breadcrumbs'][] = array(
      'text'      => $this->language->get('text_home'),
			'href'      => $this->urlChange('account/account', '', 'SSL'),
      'separator' => false
   	);

		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('heading_title'),
			'href'      => $this->urlChange('account/customerpartner/rma/wk_rma_reason', '' , 'SSL'),
			'separator' => ' :: '
		);

		$config_data = array(
				'id',
				'reason',
				'status',
			);

		foreach ($config_data as $conf) {
			if (isset($this->request->post[$conf])) {
				$data[$conf] = $this->request->post[$conf];
			}
		}

		$data['save'] = $this->urlChange('account/customerpartner/rma/wk_rma_reason/insert', '', 'SSL');

		if(isset($this->request->get['id'])){
			$id = $this->request->get['id'];

			$results = $this->model_account_rma_wk_rma_admin->viewreasonbyId($id,$this->customer->getId());

			if($results){
				foreach ($results as $key => $result) {
					$data['id'] = $result['reason_id'];
					$data['status'] = $result['status'];
					$data['reason'][$result['language_id']] = $result['reason'];
				}
			}
			$data['text_form'] = $this->language->get('text_edit_form');
			$data['save'] = $this->urlChange('account/customerpartner/rma/wk_rma_reason/update', '', 'SSL');
		}

		if(!empty($data['id'])){
			$data['text_form'] = $this->language->get('text_edit_form');
			$data['save'] = $this->urlChange('account/customerpartner/rma/wk_rma_reason/update', '' ,'SSL');
		}

		$data['back'] = $this->urlChange('account/customerpartner/rma/wk_rma_reason', '', 'SSL');

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

		$this->response->setOutput($this->load->view('account/customerpartner/rma/wk_rma_reason_form', $data));
  	}

  public function delete() {

		if (!$this->config->get('wk_rma_status') || !$this->customer->getId())
			$this->response->redirect($this->urlChange('account/account', '', true));
		$this->CheckPermission();
    $this->load->language('account/rma/wk_rma_reason');
    $this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('account/rma/wk_rma_admin');

		if (isset($this->request->post['selected'])) {
			foreach ($this->request->post['selected'] as $id) {
				$this->model_account_rma_wk_rma_admin->deleteReason((int)$id, $this->customer->getId());
	  	}
			session()->set('success', $this->language->get('text_success'));
			$url='';
			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}
			$this->response->redirect($this->urlChange('account/customerpartner/rma/wk_rma_reason', '' . $url, 'SSL'));
		} else {
			session()->set('error_warning', $this->language->get('error_selected'));
		}
    $this->index();
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
					if($accountMenu && !in_array('reason_rma:reason_rma', $accountMenu)) {
						$data['isMember'] = false;
					}
				}
			} else {
				$data['isMember'] = false;
			}
		} else {
			if(!in_array('reason_rma', $this->config->get('marketplace_allowed_account_menu'))) {
				$this->response->redirect($this->url->link('account/account','', true));
			}
		}
		if (!$data['isMember']) {
			$this->response->redirect($this->url->link('account/account','', true));
		}
		return $data['isMember'];
	}

}
