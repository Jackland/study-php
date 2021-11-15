<?php
/**
 * Webkul Software.
 * @category  Webkul
 * @author    Webkul
 * @copyright Copyright (c) 2010-2016 Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 */
class ControllerAccountRmaaddrma extends Controller {

	private $error = array();

	use RmaControllerTrait;

	public function index() {

		if(!$this->config->get('wk_rma_status'))
			$this->response->redirect($this->urlChange('account/login', '', true));

		if (!$this->customer->isLogged() AND !isset($this->session->data['rma_login'])) {
			session()->set('redirect', $this->urlChange('account/rma/addrma', '', true));
			$this->response->redirect($this->urlChange('account/rma/rmalogin', '', true));;
		}

		$data = $this->language->load('account/rma/addrma');

		$this->load->model('account/rma/rma');

		$this->document->addstyle('catalog/view/theme/yzcTheme/stylesheet/rma/rma.css?v=' . APP_VERSION);


		$this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/datepicker.css');
		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/datepicker.js');

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
			'email'					   => $this->customer->getEmail() ? $this->customer->getEmail() : $this->session->data['rma_login']
		);

		$data['text_allowed_ex'] = sprintf($this->language->get('text_allowed_ex'),$this->config->get('wk_rma_system_image'),$this->config->get('wk_rma_system_size'));
		$data['text_order_info'] = sprintf($this->language->get('text_order_info'),$this->config->get('wk_rma_system_time'));
		$data['error_image'] = sprintf($this->language->get('error_image'),$this->config->get('wk_rma_system_image'),$this->config->get('wk_rma_system_size'));

		$this->document->setTitle($this->language->get('heading_title'));

		if ((request()->isMethod('POST')) && $this->validateAddRma() && (isset($this->request->files['rma_file']['name'][0]) && (!$this->request->files['rma_file']['name'][0] || $this->request->files['rma_file']['name'][0] && $this->validateImage($this->request->files['rma_file'])) || !isset($this->request->files['rma_file']['name'][0]))) {

			$img_folder = '';
			$image_array = false;

			if($this->request->post['image_array'])
				$image_array = explode(',',$this->request->post['image_array']);

			$files = $this->request->files['rma_file'];
			$img_folder_rma = 'rma/' . ($this->customer->getId() ? $this->customer->getId() : 0 ).'_'.$this->request->post['order'].'_'.time();
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

			$this->model_account_rma_rma->insertOrderRma($this->request->post,$img_folder_rma,$this->customer->getId());

			session()->set('success', $this->language->get('text_success'));
			$this->response->redirect($this->urlChange('account/rma/rma', '', true));
		}

    $wk_admin_rma_policy = $this->language->get('must_read_policy');
		$data['text_agree'] = '';
    if ($this->config->get('wk_rma_system_information')) {
      $this->load->model('catalog/information');
			$information_info = $this->model_catalog_information->getInformation($this->config->get('wk_rma_system_information'));
			if($information_info)
				$data['text_agree'] = sprintf($this->language->get('text_agree'), $this->urlChange('information/information/agree', 'information_id=' . $this->config->get('wk_rma_system_information'), 'SSL'), $information_info['title'], $information_info['title']);
		}

    $text = $this->config->get('wk_rma_admin_policy_setting');
	  $text = trim(html_entity_decode($text));
	  $text = str_replace("&lt;", "<", $text);
	  $text = str_replace("&quot;", '"', $text);
	  $data['wk_admin_rma_policy'] = str_replace("&gt;", ">", $text);

		$results = $this->model_account_rma_rma->getrmaorders($filter_data,$this->customer->getId());

		foreach ($results as $key => $result) {
			$results[$key]['total'] = $this->currency->format($result['total'],session('currency'));
		}

		$data['order_result'] = $results;


		$url = '';

		if (isset($this->request->get['filter_model'])) {
			$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_order'])) {
			$url .= '&filter_order=' . $this->request->get['filter_order'];
		}

		if (isset($this->request->get['filter_date'])) {
			$url .= '&filter_date=' . urlencode(html_entity_decode($this->request->get['filter_date'], ENT_QUOTES, 'UTF-8'));
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('text_home'),
			'href'      => $this->urlChange('common/home'.$url, '', true),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('heading_title_list'),
			'href'      => $this->urlChange('account/rma/rma'.$url, '', true),
			'separator' => $this->language->get('text_separator')
		);

		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('heading_title'),
			'href'      => $this->urlChange('account/rma/addrma'.$url, '', true),
			'separator' => $this->language->get('text_separator')
		);

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
			'status',
			'info',
			'autono',
			'selected',
			'reason',
			'quantity',
			'product',
			'agree',
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

		if (isset($this->request->get['order_id']) && $this->request->get['order_id']) {
			$data['order'] = (int)$this->request->get['order_id'];
		}

		if (isset($this->request->get['product_id']) && $this->request->get['product_id']) {
			$data['product_id'] = (int)$this->request->get['product_id'];
		} else {
			$data['product_id'] = '';
		}

		$data['filter_order'] = $filter_order;
		$data['filter_model'] = $filter_model;
		$data['filter_date'] = $filter_date;

		$data['reasons'] = $this->model_account_rma_rma->getCustomerReason(true);
		$data['statuss'] = $this->model_account_rma_rma->getCustomerStatus(true);

		$data['action'] = $this->urlChange('account/rma/addrma', '', true);
		$data['back'] = $this->urlChange('account/rma/rma', '', true);

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		if (version_compare(VERSION, '2.2', '>=')) {
			$this->response->setOutput($this->load->view('account/rma/addrma', $data));
		} else {
			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/account/rma/addrma')) {
				$this->response->setOutput($this->load->view($this->config->get('config_template') . '/template/account/rma/addrma', $data));
			} else {
				$this->response->setOutput($this->load->view('default/template/account/rma/addrma', $data));
			}
		}

	}

	private function validateOrder($qty,$order_p_id) {
		$this->load->model('account/rma/rma');
		return $this->model_account_rma_rma->validateOrder($qty,$order_p_id);
	}

}
