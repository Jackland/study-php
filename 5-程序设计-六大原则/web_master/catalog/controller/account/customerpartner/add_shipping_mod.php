<?php

/**
 * @property ModelAccountaddshippingmod $model_account_add_shipping_mod
 * @property ModelAccountCustomerGroup $model_account_customer_group
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 */
class ControllerAccountCustomerpartneraddshippingmod extends Controller {

	private $error = array();

	public function index() {

		if (!$this->customer->isLogged()) {
			session()->set('redirect', $this->url->link('account/customerpartner/add_shipping_mod', '', true));
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->model('account/customerpartner');

		$data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();

		if(!$data['chkIsPartner'] || (isset($this->session->data['marketplace_seller_mode']) && !session('marketplace_seller_mode')))
			$this->response->redirect($this->url->link('account/account', '', true));

		$this->document->addStyle('catalog/view/theme/default/stylesheet/MP/sell.css?v=' . APP_VERSION);

		$this->load->model('account/add_shipping_mod');

		$this->load->language('account/customerpartner/add_shipping_mod');

		$this->document->setTitle($this->language->get('heading_title'));

		if ((request()->isMethod('POST'))) {

			if(isset($this->request->post['shipping_add_flatrate'])){
           		$this->request->post['shipping_add_flatrate'] = $this->currency->convert($this->request->post['shipping_add_flatrate'],session('currency'),$this->config->get('config_currency'));
           		$this->model_account_add_shipping_mod->addFlatShipping($this->customer->getId(),$this->request->post['shipping_add_flatrate'],$this->request->post['status']);
           	}

			$files = $this->request->files;

			if(isset($files['up_file']['tmp_name']) AND $files['up_file']['tmp_name']){

				// csv check
				$csv_extention = explode('.', $files['up_file']['name']);

				if(isset($csv_extention[1]) AND $csv_extention[1] == 'csv'){

					session()->set('csv_post_shipping', $this->request->post);
					if ( $file = fopen( $files['up_file']['tmp_name'] , 'r' ) ) {

						// necessary if a large csv file
		            	set_time_limit(0);
		            	$separator = 'webkul';
		            	if(isset($this->request->post['separator']))
							$separator = $this->request->post['separator'];

						if(strlen($separator)>1){
							$this->error['warning'] = $this->language->get('entry_error_separator');
						}else{
							// remove chracters from separator
							$separator = preg_replace('/[a-z A-Z .]+/', ' ',$separator);
							if(strlen($separator)<1 || $separator==' ')
								$separator = ';';

							session()->set('csv_file_shipping', array());
							while ( ($line = fgetcsv ($file, 4096, $separator)) !== FALSE) {
								$this->session->data['csv_file_shipping'][] = $line;
							}

						}
					}
					$this->response->redirect($this->url->link('account/customerpartner/add_shipping_mod/matchdata', '', true));
				}else{
					$this->error['warning'] = $this->language->get('entry_error_csv');
				}
			}else{

           		session()->set('success', $this->language->get('text_success'));

				session()->set('attention', $this->language->get('text_shipping_attention'));

				$this->response->redirect($this->url->link('account/customerpartner/add_shipping_mod', '', true));

			}

		}

		$filter_array = array(
							  'filter_country',
							  'filter_zip_to',
							  'filter_zip_from',
							  'filter_price',
							  'filter_weight_to',
							  'filter_weight_from',
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
					$filter_array[$key] = ($filter_array['page'] - 1) * 10;
				elseif($key=='limit')
					$filter_array[$key] = 10;
				else
					$filter_array[$key] = null;
			}
			unset($filter_array[$unsetKey]);

			if(isset($this->request->get[$key])){
				if ($key=='filter_country')
					$url .= '&'.$key.'=' . urlencode(html_entity_decode($filter_array[$key], ENT_QUOTES, 'UTF-8'));
				else
					$url .= '&'.$key.'='. $filter_array[$key];
			}
		}

		$results = $this->model_account_add_shipping_mod->viewdata($filter_array);

		$product_total = $this->model_account_add_shipping_mod->viewtotalentry($filter_array);

		$data['result_shipping'] = array();

		if($results){
			foreach ($results as $result) {

		      		$data['result_shipping'][] = array(
		      											'selected' => false,
														'id' => $result['id'],
														'price' => $result['price'],
														'country' => $result['country_code'],
														'zip_to' => $result['zip_to'],
														'zip_from' => $result['zip_from'],
														'weight_from' => $result['weight_from'],
														'weight_to' => $result['weight_to'],
														'max_days'	=> $result['max_days'],
													);

			}
		}

		$flatrate = $this->model_account_add_shipping_mod->getFlatShipping($this->customer->getId());

		$data['shipping_add_flatrate'] = 0;
		if(isset($flatrate['amount'])){
			$data['shipping_add_flatrate_amount'] = $data['shipping_add_flatrate'] = sprintf ("%.2f", $this->currency->convert($flatrate['amount'],$this->config->get('config_currency'),session('currency')));
			$data['shipping_add_flatrate'] = $this->currency->format($flatrate['amount'],session('currency'));
		}

		$data['status'] = 0;

		if(isset($flatrate['status'])){
			$data['status'] = $flatrate['status'];
		}

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
        	'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('account/customerpartner/add_shipping_mod'.$url, '', true),
        	'separator' => $this->language->get('text_separator')
      	);

      	if (isset($this->session->data['error_warning'])) {
			$this->error['warning'] = session('error_warning');
			$this->session->remove('error_warning');
		}

		if (isset($this->session->data['attention'])) {
			$data['attention'] = session('attention');
			$this->session->remove('attention');
		}else{
			$data['attention'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = session('success');
			$this->session->remove('success');
		}else{
			$data['success'] = '';
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['add'] = $this->url->link('account/customerpartner/add_shipping_mod/add', '', true);

		$data['action'] = $this->url->link('account/customerpartner/add_shipping_mod', '', true);

		$data['delete'] = $this->url->link('account/customerpartner/add_shipping_mod/delete', '', true);

		$data['back'] = $this->url->link('account/account', '', true);


		$url = '';

		foreach ($filter_array as $key => $value) {
			if(isset($this->request->get[$key])){
				if(!isset($this->request->get['order']) AND isset($this->request->get['sort']))
					$url .= '&order=DESC';
				if ($key=='filter_name' || $key=='filter_country')
					$url .= '&'.$key.'=' . urlencode(html_entity_decode($filter_array[$key], ENT_QUOTES, 'UTF-8'));
				elseif($key=='order')
					$url .= $value=='ASC' ? '&order=DESC' : '&order=ASC';
				elseif($key!='sort')
					$url .= '&'.$key.'='. $filter_array[$key];
			}
		}

		$data['sort_name'] = $this->url->link('account/customerpartner/add_shipping_mod', '&sort=name' . $url, true);
		$data['sort_country_code'] = $this->url->link('account/customerpartner/add_shipping_mod', '&sort=cs.country_code' . $url, true);
		$data['sort_price'] = $this->url->link('account/customerpartner/add_shipping_mod', '&sort=cs.price' . $url, true);
		$data['sort_zip_to'] = $this->url->link('account/customerpartner/add_shipping_mod', '&sort=cs.zip_to' . $url, true);
		$data['sort_zip_from'] = $this->url->link('account/customerpartner/add_shipping_mod', '&sort=cs.zip_from' . $url, true);
		$data['sort_weight_to'] = $this->url->link('account/customerpartner/add_shipping_mod', '&sort=cs.weight_to' . $url, true);
		$data['sort_weight_from'] = $this->url->link('account/customerpartner/add_shipping_mod', '&sort=cs.weight_from' . $url, true);

		$url = '';

		foreach ($filter_array as $key => $value) {
			if(isset($this->request->get[$key])){
				if(!isset($this->request->get['order']) AND isset($this->request->get['sort']))
					$url .= '&order=DESC';
				if ($key=='filter_name' || $key=='filter_country')
					$url .= '&'.$key.'=' . urlencode(html_entity_decode($filter_array[$key], ENT_QUOTES, 'UTF-8'));
				elseif($key!='page')
					$url .= '&'.$key.'='. $filter_array[$key];
			}
		}

		$pagination = new Pagination();
		$pagination->total = $product_total;
		$pagination->page = $filter_array['page'];
		$pagination->limit = 10;
		$pagination->text = $this->language->get('text_pagination');
		$pagination->url = $this->url->link('account/customerpartner/add_shipping_mod', $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($filter_array['page'] - 1) * 10) + 1 : 0, ((($filter_array['page'] - 1) * 10) > ($product_total - 10)) ? $product_total : ((($filter_array['page'] - 1) * 10) + 10), $product_total, ceil($product_total / 10));

		foreach ($filter_array as $key => $value) {
			if($key!='start' AND $key!='end')
				$data[$key] = $value;
		}

		$data['isMember'] = true;
		if($this->config->get('module_wk_seller_group_status')) {
      		$data['module_wk_seller_group_status'] = true;
      		$this->load->model('account/customer_group');
			$isMember = $this->model_account_customer_group->getSellerMembershipGroup($this->customer->getId());
			if($isMember) {
				$allowedAccountMenu = $this->model_account_customer_group->getaccountMenu($isMember['gid']);
				if($allowedAccountMenu['value']) {
					$accountMenu = explode(',',$allowedAccountMenu['value']);
					if($accountMenu && !in_array('manageshipping:manageshipping', $accountMenu)) {
						$data['isMember'] = false;
					}
				}
			} else {
				$data['isMember'] = false;
			}
      	} else {
      		if(!is_array($this->config->get('marketplace_allowed_account_menu')) || !in_array('manageshipping', $this->config->get('marketplace_allowed_account_menu'))) {
      			$this->response->redirect($this->url->link('account/account','', true));
      		}
      	}

		$data['column_left'] = $this->load->Controller('common/column_left');
		$data['column_right'] = $this->load->Controller('common/column_right');
		$data['content_top'] = $this->load->Controller('common/content_top');
		$data['content_bottom'] = $this->load->Controller('common/content_bottom');
		$data['footer'] = $this->load->Controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

$data['separate_view'] = false;

$data['separate_column_left'] = '';

if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && session('marketplace_separate_view') == 'separate') {
  $data['separate_view'] = true;
  $data['column_left'] = '';
  $data['column_right'] = '';
  $data['content_top'] = '';
  $data['content_bottom'] = '';
  $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
	$data['footer'] = $this->load->controller('account/customerpartner/footer');
  $data['header'] = $this->load->controller('account/customerpartner/header');
}

		$this->response->setOutput($this->load->view('account/customerpartner/add_shipping_mod' , $data));

	}

	public function add() {

	  if (!$this->customer->isLogged()) {
	    session()->set('redirect', $this->url->link('account/customerpartner/add_shipping_mod', '', true));
	    $this->response->redirect($this->url->link('account/login', '', true));
	  }

	  $this->load->model('account/customerpartner');

	  $data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();

	  if(!$data['chkIsPartner'] || (isset($this->session->data['marketplace_seller_mode']) && !session('marketplace_seller_mode')))
	    $this->response->redirect($this->url->link('account/account', '', true));

	  $this->document->addStyle('catalog/view/theme/default/stylesheet/MP/sell.css?v=' . APP_VERSION);

	  $this->load->model('account/add_shipping_mod');

	  $data = array_merge($data, $this->language->load('account/customerpartner/add_shipping_mod'));

	  $this->document->setTitle($this->language->get('heading_title'));

		$data['heading_title'] = $this->language->get('heading_title'). $this->language->get('heading_title_1');

	  $flatrate = $this->model_account_add_shipping_mod->getFlatShipping($this->customer->getId());

	  $data['shipping_add_flatrate'] = 0;
	  if(isset($flatrate['amount'])){
	    $data['shipping_add_flatrate_amount'] = $data['shipping_add_flatrate'] = sprintf ("%.2f", $this->currency->convert($flatrate['amount'],$this->config->get('config_currency'),session('currency')));
	    $data['shipping_add_flatrate'] = $this->currency->format($flatrate['amount'],session('currency'));
	  }

	  $data['status'] = 0;

	  if(isset($flatrate['status'])){
	    $data['status'] = $flatrate['status'];
	  }

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
	      'text'      => $this->language->get('heading_title'),
	      'href'      => $this->url->link('account/customerpartner/add_shipping_mod', '', true),
	      'separator' => $this->language->get('text_separator')
	    );

	  if (isset($this->session->data['error_warning'])) {
	    $this->error['warning'] = session('error_warning');
	    $this->session->remove('error_warning');
	  }

	  if (isset($this->session->data['attention'])) {
	    $data['attention'] = session('attention');
	    $this->session->remove('attention');
	  }else{
	    $data['attention'] = '';
	  }

	  if (isset($this->session->data['success'])) {
	    $data['success'] = session('success');
	    $this->session->remove('success');
	  }else{
	    $data['success'] = '';
	  }

	  if (isset($this->error['warning'])) {
	    $data['error_warning'] = $this->error['warning'];
	  } else {
	    $data['error_warning'] = '';
	  }

	  $data['action'] = $this->url->link('account/customerpartner/add_shipping_mod', '', true);

	  $data['back'] = $this->url->link('account/customerpartner/add_shipping_mod', '', true);

	  $data['isMember'] = true;
	  if($this->config->get('wk_seller_group_status')) {
	        $data['wk_seller_group_status'] = true;
	        $this->load->model('account/customer_group');
	    $isMember = $this->model_account_customer_group->getSellerMembershipGroup($this->customer->getId());
	    if($isMember) {
	      $allowedAccountMenu = $this->model_account_customer_group->getaccountMenu($isMember['gid']);
	      if($allowedAccountMenu['value']) {
	        $accountMenu = explode(',',$allowedAccountMenu['value']);
	        if($accountMenu && !in_array('manageshipping:manageshipping', $accountMenu)) {
	          $data['isMember'] = false;
	        }
	      }
	    } else {
	      $data['isMember'] = false;
	    }
	  }

	  $data['column_left'] = $this->load->Controller('common/column_left');
	  $data['column_right'] = $this->load->Controller('common/column_right');
	  $data['content_top'] = $this->load->Controller('common/content_top');
	  $data['content_bottom'] = $this->load->Controller('common/content_bottom');
	  $data['footer'] = $this->load->Controller('common/footer');
	  $data['header'] = $this->load->controller('common/header');

$data['separate_view'] = false;

$data['separate_column_left'] = '';

if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && session('marketplace_separate_view') == 'separate') {
  $data['separate_view'] = true;
  $data['column_left'] = '';
  $data['column_right'] = '';
  $data['content_top'] = '';
  $data['content_bottom'] = '';
  $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
  $data['footer'] = $this->load->controller('account/customerpartner/footer');
  $data['header'] = $this->load->controller('account/customerpartner/header');
}

	  $this->response->setOutput($this->load->view('account/customerpartner/add_shipping_form' , $data));
	}

	public function matchdata(){

		$this->load->language('account/customerpartner/add_shipping_mod');

		if (isset($this->session->data['csv_post_shipping']) AND isset($this->session->data['csv_file_shipping'])) {

			$post = session('csv_post_shipping');
			$files = session('csv_file_shipping');
			$fields = false;
			if(isset($files[0]))
				$fields = $files[0];

		    $num = count($fields);
		    //separator check
		    if($num < 2 ){
		    	$this->error['warning'] = $this->language->get('entry_error_separator');
		    	$this->index();
		    }else{
			    $this->stepTwo($fields);
			}
		}else{
			$this->error['warning'] = $this->language->get('error_somithing_wrong');
			$this->index();
		}

	}

	public function stepTwo($fields = array()) {

		if(!isset($this->session->data['csv_file_shipping']))
			return $this->matchdata();

		$this->load->language('account/customerpartner/add_shipping_mod');

		$this->document->setTitle($this->language->get('heading_title'));

		if ((request()->isMethod('POST')) && $fields == array()) {

			//insert shipping
			foreach ($this->request->post as $chkpost) {
				if($chkpost==''){
					$this->error['warning'] = $this->language->get('error_fileds');
					break;
				}
			}

			if(isset($this->error['warning']) AND $this->error['warning']){
				$fields = $this->session->data['csv_file_shipping'][0];
			}else{

				$message = $this->matchDataTwo();

				if($message['success'])
					session()->set('success', $this->language->get('text_shipping').$message['success']);
				if($message['warning'])
					session()->set('error_warning', $this->language->get('fields_error').$message['warning']);
				if($message['update'])
					session()->set('attention', $this->language->get('text_attention').$message['update']);

				$this->session->remove('csv_file_shipping');
				$this->session->remove('csv_post_shipping');

				$this->response->redirect($this->url->link('account/customerpartner/add_shipping_mod', '', true));

			}

		}

		$data['heading_title'] = $this->language->get('heading_title'). $this->language->get('heading_title_2');

 		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

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
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('account/customerpartner/add_shipping_mod', '', true),
      		'separator' => ' :: '
   		);

		// send fields data
		$data['fields'] = $fields;

		// shipping data
		$data['shippingTable'] = array('country_code','zip_to','zip_from','price','weight_to','weight_from','max_days');

		$data['action'] = $this->url->link('account/customerpartner/add_shipping_mod/stepTwo', '', true);

		$data['cancel'] = $this->url->link('account/customerpartner/add_shipping_mod', '', true);

		$data['isMember'] = true;
		if($this->config->get('module_wk_seller_group_status')) {
      		$data['module_wk_seller_group_status'] = true;
      		$this->load->model('account/customer_group');
			$isMember = $this->model_account_customer_group->getSellerMembershipGroup($this->customer->getId());
			if($isMember) {
				$allowedAccountMenu = $this->model_account_customer_group->getaccountMenu($isMember['gid']);
				if($allowedAccountMenu['value']) {
					$accountMenu = explode(',',$allowedAccountMenu['value']);
					if($accountMenu && !in_array('manageshipping:manageshipping', $accountMenu)) {
						$data['isMember'] = false;
					}
				}
			} else {
				$data['isMember'] = false;
			}
      	}

		$data['column_left'] = $this->load->Controller('common/column_left');
		$data['column_right'] = $this->load->Controller('common/column_right');
		$data['content_top'] = $this->load->Controller('common/content_top');
		$data['content_bottom'] = $this->load->Controller('common/content_bottom');
		$data['footer'] = $this->load->Controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

$data['separate_view'] = false;

$data['separate_column_left'] = '';

if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && session('marketplace_separate_view') == 'separate') {
  $data['separate_view'] = true;
  $data['column_left'] = '';
  $data['column_right'] = '';
  $data['content_top'] = '';
  $data['content_bottom'] = '';
  $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
  $data['footer'] = $this->load->controller('account/customerpartner/footer');
  $data['header'] = $this->load->controller('account/customerpartner/header');
}

		$this->response->setOutput($this->load->view('account/customerpartner/add_shipping_mod_next' , $data));

	}

	private function matchDataTwo(){

		$this->load->model('account/add_shipping_mod');
		$this->load->language('account/customerpartner/add_shipping_mod');

		if(!isset($this->session->data['csv_file_shipping']))
			$this->response->redirect($this->url->link('account/customerpartner/add_shipping_mod', '', true));

		$files = session('csv_file_shipping');
		$post = $this->request->post;

		// remove index line from array
		$fields = $files[0];
		$files = array_slice($files, 1);

		$shippingDatas = array();
		$i = 0;
		$num = count($files);

	    foreach ($files as $line) {
	    	$entry = true;

	    	foreach($post as $postchk){
	    		if(!isset($line[$postchk]) || trim($line[$postchk])==''){
	    			$entry = false;
	    			break;
	    		}
	    	}

	    	if($entry){
	    		$shippingDatas[$i] = array();
	    		foreach($post as $key=>$postchk){
		    		$shippingDatas[$i][$key] = $line[$postchk];
	    		}
	    		$i++;
	    	}

	    }

	    $updatechk = 0;
	    foreach ($shippingDatas as $newShipping) {
	    	$result = $this->model_account_add_shipping_mod->addShipping($newShipping);
	    	if($result)
	    		$updatechk++;
	    }

	    return array('success' => $i-$updatechk,
	    			 'warning' => $num-$i,
	    			 'update' => $updatechk,
	    			);
	}

	public function delete() {

    	$this->load->model('account/add_shipping_mod');
		$this->load->language('account/customerpartner/add_shipping_mod');

		$url='';

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		if (isset($this->request->post['selected'])) {
			foreach ($this->request->post['selected'] as $id) {
				$this->model_account_add_shipping_mod->deleteentry($id);
	  		}

			session()->set('success', $this->language->get('text_success_delete'));

			$this->response->redirect($this->url->link('account/customerpartner/add_shipping_mod', '' . $url, true));
		}

    	$this->response->redirect($this->url->link('account/customerpartner/add_shipping_mod', '' . $url, true));
  	}

}
?>
