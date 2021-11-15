<?php

/**
 * @property ModelLocalisationLanguage $model_localisation_language
 * @property ModelWkcustomfieldWkcustomfield $model_wkcustomfield_wkcustomfield
 */
Class ControllerWkcustomfieldWkcustomfield extends Controller{

	public function index(){
			$this->load->language("wkcustomfield/wkcustomfield");
			$this->document->setTitle($this->language->get('heading_title'));
			$this->getList();
	}

	public function getList(){
			$this->load->language("wkcustomfield/wkcustomfield");

			$this->load->model("wkcustomfield/wkcustomfield");

			$data['user_token'] = session('user_token');

			if(isset($this->request->get['sort'])){
				$data['sort'] = $sort = $this->request->get['sort'];
			}else{
				$sort = null;
			}
			if(isset($this->request->get['order'])){
				$data['order'] = $order = $this->request->get['order'];
			}else{
				$order = null;
			}
			if(isset($this->request->get['fieldName'])){
				$data['fieldName'] = $fieldName = $this->request->get['fieldName'];
			}else{
				$fieldName = null;
			}
			if(isset($this->request->get['fieldType'])){
				$data['fieldType'] = $fieldType = $this->request->get['fieldType'];
			}else{
				$fieldType = null;
			}
			if(isset($this->request->get['forSeller'])){
				$data['forSeller'] = $forSeller = $this->request->get['forSeller'];
			}else{
				$forSeller = null;
			}

			if(isset($this->request->get['page'])){
				$data['page'] = $page = $this->request->get['page'];
			}else{
				$page = null;
			}

			$filterValues = array(
				'fieldName' => $fieldName,
				'fieldType' => $fieldType,
				'forSeller' => $forSeller,
				'page'      => $page,
				'sort' 		=> $sort,
				'order' 	=> $order,
				'start'     => ($page - 1) * $this->config->get('config_limit_admin'),
			    'limit'     => $this->config->get('config_limit_admin')
			);


			$optionList = array();
			$optionList = $this->model_wkcustomfield_wkcustomfield->getOptionList($filterValues);
			$getOptionListTotals = $this->model_wkcustomfield_wkcustomfield->getOptionListTotals($filterValues);

			$data['optionList'] = array();
			foreach ($optionList as $key => $option) {
				$data['optionList'][] = array(
					'id' => $option['id'],
					'fieldName' => $option['fieldName'],
					'fieldType'	=> $option['fieldType'],
					'forSeller' => $option['forSeller'],
					'edit'		=> $this->url->link("wkcustomfield/wkcustomfield/getForm",'user_token='.session('user_token') . "&id=".$option['id'], true),
				);
			}


			//breadcrum

			$data['breadcrumbs'] = array();

	   		$data['breadcrumbs'][] = array(
	       		'text'      => $this->language->get('text_home'),
				'href'      => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true),
	      		'separator' => false
	   		);

	   		$data['breadcrumbs'][] = array(
	       		'text'      => $this->language->get('heading_title'),
				'href'      => $this->url->link('wkcustomfield/wkcustomfield', 'user_token=' . session('user_token'), true),
	      		'separator' => ' :: '
	   		);

			$data['insert'] = $this->url->link("wkcustomfield/wkcustomfield/getForm","user_token=". session('user_token'), true);

			$data['action'] = $this->url->link("wkcustomfield/wkcustomfield/delete","user_token=". session('user_token'), true);

			// $this->template = 'wkcustomfield/wkcustomfield';
			$data['header']	=	$this->load->controller('common/header');
			$data['column_left']	=	$this->load->controller('common/column_left');
			$data['footer']	=	$this->load->controller('common/footer');

			$this->response->setOutput($this->load->view('wkcustomfield/wkcustomfield', $data));

	}

	public function insert(){
			$this->load->model("wkcustomfield/wkcustomfield");
			$this->load->language("wkcustomfield/wkcustomfield");
			if(request()->isMethod('POST')){
				if(isset($this->request->get['id'])){

					$this->model_wkcustomfield_wkcustomfield->updateField($this->request->post);
					session()->set('success', $this->language->get('success_update'));
					$this->response->redirect($this->url->link('wkcustomfield/wkcustomfield', 'user_token=' . session('user_token') , true));


				}else{

					foreach ($this->request->post['fieldName'] as $key => $option) {
						$data['fieldName'][$key] = array(
							'fieldName' => $option,
						);
					}
					$flag = 0;
					if(isset($this->request->post['preOptionValue'])){
						foreach ($this->request->post['preOptionValue'] as $key => $values) {
							foreach ($values as $index => $value) {
								$newarray[$index] = array(
									'des' => $value,
								);
							}
							$data['fieldoptions'][$key] = $newarray;
							$flag = $key;
						}
					}
					if(isset($this->request->post['optionValue'])){
						foreach ($this->request->post['optionValue'] as $key => $values) {
							$flag++;
							foreach ($values as $index => $value) {
								$newarray[$index] = array(
									'des' => $value,
								);
							}
							$data['fieldoptions'][$flag] = $newarray;
						}
					}
					if(isset($this->request->post['fieldDes'])){
						foreach ($this->request->post['fieldDes'] as $key => $option) {
							$data['fieldDes'][$key] = array(
								'fieldDes' => $option,
							);
						}
					}

					if(isset($this->request->post['fieldType'])){
						$data['fieldType'] = $this->request->post['fieldType'];
					}
					if(isset($this->request->post['forSeller'])){
						$data['forSeller'] = $this->request->post['forSeller'];
					}
					if(isset($this->request->post['isRequired'])){
						$data['isRequired'] = $this->request->post['isRequired'];
					}


					if(isset($this->request->post['isRequired'])) {
						if($this->request->post['isRequired'] == ''){
							$error = "Warning:";
						}
					}
					if(isset($this->request->post['fieldType'])) {
						if($this->request->post['fieldType'] == ''){
							$error = "Warning:";
						}
					}
					if(isset($this->request->post['forSeller'])) {
						if($this->request->post['forSeller'] == ''){
							$error = "Warning:";
						}
					}

					foreach ($this->request->post['fieldName'] as $key => $value) {
							if($value == '' || $value == null){
								$error = "Warning:";
							}
					}
					if(isset($this->request->post['optionValue'])){
						foreach ($this->request->post['optionValue'] as $key => $values) {
								foreach ($values as $key => $value) {
									if($value == '' || $value == null){
										$error = "Warning:";
									}
								}
						}
					}
					if(!isset($error)){
						$this->model_wkcustomfield_wkcustomfield->insertField($this->request->post);
						session()->set('success', $this->language->get('success_insert'));
						$this->response->redirect($this->url->link('wkcustomfield/wkcustomfield', 'user_token=' . session('user_token') , true));
					}else{
						$data['error_insert'] = $this->language->get('error_insert');
						$this->getForm();
					}


				}
			}
	}

	public function getForm(){
			$this->load->language("wkcustomfield/wkcustomfield");

			$this->load->model("wkcustomfield/wkcustomfield");

			$this->load->model('localisation/language');

			$data['languages'] = $this->model_localisation_language->getLanguages();

			$data['breadcrumbs'] = array();

	   		$data['breadcrumbs'][] = array(
	       		'text'      => $this->language->get('text_home'),
				'href'      => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true),
	      		'separator' => false
	   		);

	   		$data['breadcrumbs'][] = array(
	       		'text'      => $this->language->get('heading_title'),
				'href'      => $this->url->link('wkcustomfield/wkcustomfield', 'user_token=' . session('user_token'), true),
	      		'separator' => ' :: '
	   		);

			if(isset($this->request->get['id']) && $this->request->get['id'] != ''){
				$this->document->setTitle($this->language->get('heading_title_edit'));
				$data['heading_title']	=	$this->language->get('heading_title');
				$data['lower_heading_title']	=	$this->language->get('heading_title_edit');
				$data['button_save']	=	$this->language->get('button_update');
				$data['button_back']	=	$this->language->get('button_back');

				$data['breadcrumbs'][] = array(
	       		'text'      => $this->language->get('heading_title_edit'),
				'href'      => $this->url->link('wkcustomfield/wkcustomfield/getForm','user_token=' . session('user_token') . '&id=' . (int)$this->request->get['id'], true),
	      		'separator' => ' :: '
	   			);
				if($this->config->get('wk_custome_field_wkaccesspermission')){
					$data['wkaccesspermission'] = $this->config->get('wk_custome_field_wkaccesspermission');
				}
				$data['back'] = $this->url->link("wkcustomfield/wkcustomfield","user_token=". session('user_token'), true);
				$data['action'] = $this->url->link("wkcustomfield/wkcustomfield/insert" ,"user_token=". session('user_token') . "&id=". (int)$this->request->get['id'], true);

				$data['optionDetails']	=	array();
				$optionDetails = array();
				$data['fieldoptions']  = array();
				$fieldoptions = array();

				$optionDetails = $this->model_wkcustomfield_wkcustomfield->getOptionDetails((int)$this->request->get['id']);
				$fieldoptions = $this->model_wkcustomfield_wkcustomfield->getFieldOptions((int)$this->request->get['id']);
				if(!empty($fieldoptions)){
					foreach ($fieldoptions as $key => $values) {
						foreach ($values as $key => $value) {
							$data['fieldoptions'][$value['optionId']][$value['language_id']] = array(
								'des' => $value['optionValue'],
							);
						}
					}
				}
				$data['fieldId'] = $optionDetails[0]['fieldId'];
				foreach ($optionDetails as $key => $option) {

					$data['fieldName'][$option['language_id']] = array(
						'fieldName' => $option['fieldName']
					);
					$data['isRequired'] = $option['isRequired'];
					$data['forSeller'] = $option['forSeller'];
					$data['fieldType'] = $option['fieldType'];
				}
				foreach ($optionDetails as $key => $option) {
					$data['fieldDes'][$option['language_id']] = array(
						'fieldDes' => $option['fieldDescription'],
					);
				}

			}else{

				$this->document->setTitle($this->language->get('heading_title_insert'));
				$data['error_insert'] = $this->language->get('error_insert');;

				$data['breadcrumbs'][] = array(
	       		'text'      => $this->language->get('heading_title_insert'),
				'href'      => $this->url->link('wkcustomfield/wkcustomfield/getForm','user_token=' . session('user_token'), true),
	      		'separator' => ' :: '
	   			);

				$data['heading_title']	=	$this->language->get('heading_title');
				$data['lower_heading_title']	=	$this->language->get('heading_title_insert');

				$data['button_save']	=	$this->language->get('button_save');
				$data['button_back']	=	$this->language->get('button_back');
				if($this->config->get('wk_custome_field_wkaccesspermission')){
					$data['wkaccesspermission'] = $this->config->get('wk_custome_field_wkaccesspermission');
				}

				$data['back'] = $this->url->link("wkcustomfield/wkcustomfield","user_token=". session('user_token'), true);
				$data['action'] = $this->url->link("wkcustomfield/wkcustomfield/insert","user_token=". session('user_token'), true);

			}

			$data['header']	=	$this->load->controller('common/header');
			$data['column_left']	=	$this->load->controller('common/column_left');
			$data['footer']	=	$this->load->controller('common/footer');

			$this->response->setOutput($this->load->view('wkcustomfield/wkcustomfield_form', $data));
	}

	public function delete(){
		if(request()->isMethod('POST')){
			$this->load->language('wkcustomfield/wkcustomfield');
			$this->load->model('wkcustomfield/wkcustomfield');
			$fieldName = '';
			if(isset($this->request->post['selected'])){
				foreach ($this->request->post['selected'] as $key => $value) {
					$fieldName .= $this->model_wkcustomfield_wkcustomfield->deleteFieldDetails($value).", ";
				}
				$fieldName = trim($fieldName,', ');
				session()->set('success', $this->language->get('success_delete')."- [ ". $fieldName ." ] !");
				$this->response->redirect($this->url->link("wkcustomfield/wkcustomfield","user_token=".session('user_token'), true) );
			}else{
				session()->set('warning', $this->language->get('error_delete'));
				$this->response->redirect($this->url->link("wkcustomfield/wkcustomfield","user_token=".session('user_token') , true) );
			}
		}
	}

	public function getOptions(){
		if(request()->isMethod('POST') && $this->request->post['value'] != ''){
			$this->load->model('wkcustomfield/wkcustomfield');
			$options = array();
			$options = $this->model_wkcustomfield_wkcustomfield->getOptions($this->request->post['value']);
			$this->response->setOutput(json_encode($options));
		}
	}

}

?>
