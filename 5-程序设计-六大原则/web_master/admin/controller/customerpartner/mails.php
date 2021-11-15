<?php

/**
 * @property ModelCustomerpartnerMail $model_customerpartner_mail
 */
class Controllercustomerpartnermails extends Controller {

	private $error = array();
	private $data = array();

  	public function index() {
    	$this->getlist();
  	}

  	public function getlist() {

		$this->load->language('customerpartner/mail');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('customerpartner/mail');

		$filter_array = array(
							  'filter_id',
							  'filter_name',
							  'filter_message',
							  'filter_subject',
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
					$filter_array[$key] = 'cc.id';
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
				if ($key=='filter_name' || $key=='filter_message' || $key=='filter_subject')
					$url .= '&'.$key.'=' . urlencode(html_entity_decode($filter_array[$key], ENT_QUOTES, 'UTF-8'));
				else
					$url .= '&'.$key.'='. $filter_array[$key];
			}
		}

  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'user_token=' . session('user_token'). $url, true),
      		'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('customerpartner/mails', 'user_token=' . session('user_token'). $url, true),
      		'separator' => ' :: '
   		);

		$this->data['delete'] = $this->url->link('customerpartner/mails/delete', 'user_token=' . session('user_token') , true);
		$this->data['insert'] = $this->url->link('customerpartner/mails/addmail', 'user_token=' . session('user_token') , true);

    	$results = $this->model_customerpartner_mail->viewtotal($filter_array);

		$product_total = $this->model_customerpartner_mail->viewtotalentry($filter_array);

		$lang_array = array('heading_title',
							'entry_name',
							'entry_message',
							'entry_subject',
							'entry_id',
							'entry_no_records',
							'entry_action',
							'text_confirm',
							'text_edit',

							'button_back',
							'button_save',
							'button_cancel',
							'button_insert',
							'button_delete',
							'button_filter',
							);

		foreach($lang_array as $language){
			$this->data[$language] = $this->language->get($language);
		}

		$this->data['mails'] = array();

	    foreach ($results as $result) {

	      	$this->data['mails'][] = array(
				'selected'=>False,
				'id' => $result['id'],
				'name' => $result['name'],
				'message' => html_entity_decode($result['message']),
				'subject' => $result['subject'],
				'action' => $this->url->link('customerpartner/mails/addMail', 'user_token=' . session('user_token') . '&mail_id=' . $result['id'], true),
			);

		}

 		$this->data['user_token'] = session('user_token');

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

		$url = '';

		foreach ($filter_array as $key => $value) {
			if(isset($this->request->get[$key])){
				if(!isset($this->request->get['order']) AND isset($this->request->get['sort']))
					$url .= '&order=DESC';
				if ($key=='filter_name' || $key=='filter_message' || $key=='filter_subject')
					$url .= '&'.$key.'=' . urlencode(html_entity_decode($filter_array[$key], ENT_QUOTES, 'UTF-8'));
				elseif($key=='order')
					$url .= $value=='ASC' ? '&order=DESC' : '&order=ASC';
				elseif($key!='start' AND $key!='limit' AND $key!='sort')
					$url .= '&'.$key.'='. $filter_array[$key];
			}
		}

		$this->data['sort_name'] = $this->url->link('customerpartner/mails', 'user_token=' . session('user_token') . '&sort=name' . $url, true);
		$this->data['sort_id'] = $this->url->link('customerpartner/mails', 'user_token=' . session('user_token') . '&sort=id' . $url, true);
		$this->data['sort_message'] = $this->url->link('customerpartner/mails', 'user_token=' . session('user_token') . '&sort=message' . $url, true);
		$this->data['sort_subject'] = $this->url->link('customerpartner/mails', 'user_token=' . session('user_token') . '&sort=subject' . $url, true);

		$url = '';

		foreach ($filter_array as $key => $value) {
			if(isset($this->request->get[$key])){
				if(!isset($this->request->get['order']) AND isset($this->request->get['sort']))
					$url .= '&order=DESC';
				if ($key=='filter_name' || $key=='filter_message' || $key=='filter_subject')
					$url .= '&'.$key.'=' . urlencode(html_entity_decode($filter_array[$key], ENT_QUOTES, 'UTF-8'));
				elseif($key!='page')
					$url .= '&'.$key.'='. $filter_array[$key];
			}
		}

		$pagination = new Pagination();
		$pagination->total = $product_total;
		$pagination->page = $filter_array['page'];
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->text = $this->language->get('text_pagination');
		$pagination->url = $this->url->link('customerpartner/mails', 'user_token=' . session('user_token') . $url . '&page={page}', true);

		$this->data['pagination'] = $pagination->render();
		$this->data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($filter_array['page'] - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($filter_array['page'] - 1) * $this->config->get('config_limit_admin')) > ($product_total - $this->config->get('config_limit_admin'))) ? $product_total : ((($filter_array['page'] - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $product_total, ceil($product_total / $this->config->get('config_limit_admin')));

		foreach ($filter_array as $key => $value) {
			if($key!='start' AND $key!='end')
				$this->data[$key] = $value;
		}

		$this->data['header'] = $this->load->controller('common/header');
		$this->data['footer'] = $this->load->controller('common/footer');
		$this->data['column_left'] = $this->load->controller('common/column_left');
		$this->response->setOutput($this->load->view('customerpartner/mail_list',$this->data));
  	}

  	public function addmail() {

  		$this->load->language('customerpartner/mail');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customerpartner/mail');

    	$lang_array = array('heading_title',

							'entry_message',
							'entry_message_info',
							'entry_name',
							'entry_name_info',
							'entry_subject',
							'entry_subject_info',
							'entry_for',
							'entry_code',

							'info_mail',
							'info_mail_add',
							'button_back',
							'button_save',
							'button_cancel',
							'button_insert',
							'button_delete',
							'button_filter',

							'tab_general',
							'tab_info',

							);

		foreach($lang_array as $language){
			$this->data[$language] = $this->language->get($language);
		}

		$post_data = array(
							'mail_id',
							'name',
							'message',
							'subject',
							);

		foreach($post_data as $post){
			if(isset($this->request->post[$post])){
				$this->data[$post] = $this->request->post[$post];
			}else{
				$this->data[$post] = '';
			}
		}

		if(isset($this->request->get['mail_id'])){

    		$result = $this->model_customerpartner_mail->getMailData($this->request->get['mail_id']);
    		if($result)
    			foreach($result as $key => $value){
    				$this->data[$key] = $value;
    			}

    		$this->data['mail_id'] = $this->request->get['mail_id'];
		}


		$this->data['user_token'] = session('user_token');

 		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}

		$url = '';

  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true),
      		'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('customerpartner/mails', 'user_token=' . session('user_token') . $url, true),
      		'separator' => ' :: '
   		);

    	$this->data['cancel'] = $this->url->link('customerpartner/mails', 'user_token=' . session('user_token') . $url, true);
		$this->data['save'] = $this->url->link('customerpartner/mails/mailSave', 'user_token=' . session('user_token') . $url, true);

		$mailValues = str_replace('<br />', ',', nl2br($this->config->get('marketplace_mail_keywords')));
		$mailValues = explode(',', $mailValues);
		$find = array();
		foreach ($mailValues as $key => $value) {
			$find[] = trim($value);
		}

		$this->data['mail_help'] = $find;

		// $this->data['mail_help'] = array('transaction_amount','transaction_message','order','seller_message','customer_message','commission','product_name','seller_name','config_logo','config_icon','config_currency','config_image','config_name','config_owner','config_address','config_geocode','config_email','config_telephone');

		$this->data['header'] = $this->load->controller('common/header');
		$this->data['footer'] = $this->load->controller('common/footer');
		$this->data['column_left'] = $this->load->controller('common/column_left');
		$this->response->setOutput($this->load->view('customerpartner/mail_form',$this->data));

	}

	public function mailSave() {
			$this->load->language('customerpartner/mail');

    	if ((request()->isMethod('POST')) && $this->validate()) {

			$this->load->model('customerpartner/mail');

			if ((utf8_strlen($this->request->post['message']) < 25) || (utf8_strlen($this->request->post['message']) > 5000)) {
	      		$this->error['warning'] = $this->language->get('error_message');
	    	}

	    	if ((utf8_strlen($this->request->post['subject']) < 5) || (utf8_strlen($this->request->post['subject']) > 1000)) {
	      		$this->error['warning'] = $this->language->get('error_subject');
	    	}

			if ((utf8_strlen($this->request->post['name']) < 1)) {
	      		$this->error['warning'] = $this->language->get('error_name');
	    	}

			if(!isset($this->error['warning'])){

				$this->model_customerpartner_mail->addMail($this->request->post);

	    		if($this->request->post['mail_id'])
					session()->set('success', $this->language->get('text_update'));
				else
					session()->set('success', $this->language->get('text_success'));

				$this->response->redirect($this->url->link('customerpartner/mails', 'user_token=' . session('user_token'), true));
			}
		}

		$this->addmail();
  	}

	public function delete() {

    	$this->load->language('customerpartner/mail');

    	$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customerpartner/mail');

		if (isset($this->request->post['selected']) && $this->validate()) {
			foreach ($this->request->post['selected'] as $id) {
				$this->model_customerpartner_mail->deleteentry($id);
	  		}

			session()->set('success', $this->language->get('text_success'));

			$url='';

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('customerpartner/mails', 'user_token=' . session('user_token') . $url, true));
		}

    	$this->index();
  	}

	private function validate() {

		if (!$this->user->hasPermission('modify', 'customerpartner/mails')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		//trim check
		$temp_array = array(
			'name'					=> 'name',
			'subject'				=> 'subject',
		);

		foreach ($temp_array as $key => $value) {
			if( ctype_space($this->request->post[$key]) ){
				$this->error['warning'] = $this->language->get('error_' . $value);
				$this->request->post[$value] = trim($this->request->post[$key]);
				break;
			}
		}
		// end trim check

		/* chk summernote message*/
		if( !$this->error ) {
			$message = str_replace('&nbsp;', '', html_entity_decode($this->request->post['message']));
			$message = strip_tags(str_replace(' ', '', $message));
				if ($message == '') {
					$this->error['warning'] = $this->language->get('error_message');
				}
		}
		/*end chk summernote message*/

		if (!$this->error) {
			return true;
		} else {
			return false;
		}
	}

}
?>
