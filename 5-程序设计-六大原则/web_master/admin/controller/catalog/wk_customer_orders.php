<?php
/**
 * Webkul Software.
 * @category  Webkul
 * @author    Webkul
 * @copyright Copyright (c) 2010-2016 Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 * @property ModelCatalogWkcustomerorders $model_catalog_wk_customer_orders
 */
class ControllerCatalogwkcustomerorders extends Controller {

	private $error = array();
	use RmaControllerTrait;

	public function index() {

		if (!$this->config->get('wk_rma_status'))
			$this->response->redirect($this->urlChange('common/dashboard', 'user_token=' . session('user_token'), true));

		$this->language->load('catalog/wk_customer_orders');

		$data['customer_id'] = $customer_id = (int)$this->request->get['customer_id'];

		if (!$customer_id){
			$this->response->redirect($this->urlChange('catalog/wk_rma_admin', 'user_token=' . session('user_token'), true));
		}

		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('catalog/wk_customer_orders');

		$data['text_allowed_ex'] = sprintf($this->language->get('text_allowed_ex'),$this->config->get('wk_rma_system_image'),$this->config->get('wk_rma_system_size'));

		$data['text_order_info'] = sprintf($this->language->get('text_order_info'),$this->config->get('wk_rma_system_time'));

		$data['error_image'] = sprintf($this->language->get('error_image'),$this->config->get('wk_rma_system_image'),$this->config->get('wk_rma_system_size'));

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->request->get['filter_order'])) {
			$filter_order = $this->request->get['filter_order'];
		} else {
			$filter_order = null;
		}

		if (isset($this->request->get['filter_date'])) {
			$filter_date = $this->request->get['filter_date'];
		} else {
			$filter_date = null;
		}

		if (isset($this->request->get['filter_model'])) {
			$filter_model = $this->request->get['filter_model'];
		} else {
			$filter_model = null;
		}

		$filter_data = array(
			'filter_model'     => $filter_model,
			'filter_date'      => $filter_date,
			'filter_order' 		 => $filter_order,
		);

		$url = '';

		if (isset($this->request->get['filter_order'])) {
			$url .= '&filter_order=' . $this->request->get['filter_order'];
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = session('success');
			$this->session->remove('success');
		} else {
			$data['success'] = '';
		}

		$data['user_token'] = session('user_token');

		$data['breadcrumbs'] = array();

 		$data['breadcrumbs'][] = array(
     		'text'      => $this->language->get('text_home'),
				'href'      => $this->urlChange('common/dashboard', 'user_token=' . session('user_token'), 'SSL'),
    		'separator' => false
 		);

 		$data['breadcrumbs'][] = array(
     		'text'      => $this->language->get('text_rma'),
				'href'      => $this->urlChange('catalog/wk_rma_admin', 'user_token=' . session('user_token'), 'SSL'),
    		'separator' => false
 		);

 		$data['breadcrumbs'][] = array(
     		'text'      => $this->language->get('heading_title'),
				'href'      => $this->urlChange('catalog/wk_customer_orders', 'user_token=' . session('user_token').'&customer_id='.$this->request->get['customer_id'], 'SSL'),
    		'separator' => ' :: '
 		);

		$this->request->post['agree'] = true;

		if ((request()->isMethod('POST')) && $this->validateAddRma() && $this->validate('catalog/wk_customer_orders') && (isset($this->request->files['rma_file']['name'][0]) && (!$this->request->files['rma_file']['name'][0] || $this->request->files['rma_file']['name'][0] && $this->validateImage($this->request->files['rma_file'])) || !isset($this->request->files['rma_file']['name'][0]))) {

			$img_folder = '';
			$image_array = false;

			if($this->request->post['image_array'])
				$image_array = explode(',',$this->request->post['image_array']);

			$files = $this->request->files['rma_file'];
			$img_folder_rma = 'rma/' . ($customer_id ? $customer_id : 0 ).'_'.$this->request->post['order'].'_'.time();
			$img_folder = DIR_IMAGE . $img_folder_rma;
			$images = $this->validateImage($this->request->files['rma_file']);
			if (!is_dir($img_folder)) {
				@chmod(DIR_IMAGE . 'rma/',0755);
				@mkdir($img_folder);
				@mkdir($img_folder . '/files');
			}
			if($images) {
				foreach ($images as $key => $image) {
					$target = $img_folder . '/' . $image['name'];
					if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0777, true)) {
						die();
					}
					@move_uploaded_file($image['tmp_name'], $target);
				}
			}
			$this->model_catalog_wk_customer_orders->insertOrderRma($this->request->post,$img_folder_rma,$customer_id);
			session()->set('success', $this->language->get('text_success'));
			$this->response->redirect($this->urlChange('catalog/wk_rma_admin', 'user_token=' . session('user_token'), 'SSL'));
		}

    $wk_admin_rma_policy = 'Must Read Policies Before Return ..';

		$text = $this->config->get('wk_rma_admin_policy_setting');
		$text = trim(html_entity_decode($text));
		$text = str_replace("&lt;", "<", $text);
		$text = str_replace("&quot;", '"', $text);
		$data['wk_admin_rma_policy'] = str_replace("&gt;", ">", $text);

		$results = $this->model_catalog_wk_customer_orders->getrmaorders($filter_data,$customer_id);

		$data['order_result'] = $results;

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = session('success');
		} else {
			$data['success'] = '';
		}

		$config_data = array(
			'order',
			'reason',
			'status',
			'info',
			'autono',
	  );

		foreach ($config_data as $value) {
			if(isset($this->request->post[$value])){
				$data[$value] = $this->request->post[$value];
			}else{
				$data[$value] = '';
			}
		}

		$config_data = array(
			'selected',
			'reason',
			'quantity',
		);

		$data['json_selected'] = array();
		if(isset($this->request->post['json_select'])){
			foreach ($this->request->post['json_select'] as $key => $select) {
				if (isset($this->request->post['product']) && $this->request->post['product'])
				foreach ($this->request->post['product'] as $key2 => $value) {
					if ($value == $select)
						foreach ($config_data as $config) {
							if (isset($this->request->post[$config][$key2]) ) {
								$data['json_selected'][$key][$config] = $this->request->post[$config][$key2];
							} else {
								$data['json_selected'][$key][$config] = '';
							}
						}
				}
			}
		}

		$data['json_selected'] = json_encode($data['json_selected']);

		$data['filter_order'] = $filter_order;
		$data['filter_model'] = $filter_model;
		$data['filter_date'] = $filter_date;

		$data['reasons'] = $this->model_catalog_wk_customer_orders->getCustomerReason(true);
		$data['statuss'] = $this->model_catalog_wk_customer_orders->getCustomerStatus(true);
		$data['customer_id'] = $this->request->get['customer_id'];

		$data['action'] = $this->urlChange('catalog/wk_customer_orders', 'user_token=' . session('user_token').'&customer_id='.$this->request->get['customer_id'], 'SSL');
		$data['back'] = $this->urlChange('catalog/wk_rma_admin', 'user_token=' . session('user_token'), 'SSL');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/wk_customer_orders', $data));
  }

  public function getorder() {
		if (!$this->config->get('wk_rma_status'))
			$this->response->redirect($this->urlChange('common/dashboard', 'user_token=' . session('user_token'), true));

		$this->load->model('catalog/wk_customer_orders');

		if(isset($this->request->post['order']) AND $this->request->post['order']){
			$customer_id = isset($this->request->get['customer_id']) ? (int)$this->request->get['customer_id'] : 0;
			$result = array();
	    $result = $this->model_catalog_wk_customer_orders->orderprodetails((int)$this->request->post['order'],$customer_id);
	    $this->response->setOutput(json_encode($result));
		}

	}

  private function imageExtensioncheck($name,$size) {
		if (!$this->config->get('wk_rma_status'))
			$this->response->redirect($this->urlChange('common/dashboard', 'user_token=' . session('user_token'), true));

		$type = explode('.',$name);
		if(($this->config->get('wk_rma_system_image') == '*') || in_array(end($type),explode(',',$this->config->get('wk_rma_system_image')))){
			if(($size/1000)<=(int)$this->config->get('wk_rma_system_size'))
				return true;
			else
				return false;
		} else {
			return false;
		}
	}

	private function validateOrder($qty,$order_p_id) {
		$this->load->model('catalog/wk_customer_orders');
		if ($this->model_catalog_wk_customer_orders->validateOrder($qty,$order_p_id)){
			return true;
		} else {
			return false;
		}
	}
}
