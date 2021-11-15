<?php

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelCustomerpartnerMaster $model_customerpartner_master
 */
class ControllerAccountCustomerpartnerBecomePartner extends Controller {

	private $error = array();
	private $data = array();

	public function index() {

		if (!$this->customer->isLogged()) {
			session()->set('redirect', $this->url->link('account/customerpartner/become_partner', '', true));
			$this->response->redirect($this->url->link('account/register', '', true));
		}

		$this->load->language('account/customerpartner/become_partner');

		$this->document->setTitle($this->language->get('heading_title_become_partner'));
		$this->data['heading_title_become_partner'] = $this->language->get('heading_title_become_partner');
		$this->data['error_warning_authenticate'] = $this->language->get('error_warning_authenticate');

		$this->load->model('account/customerpartner');

		if ((request()->isMethod('POST')) && $this->validateForm()) {

			$country_id = $this->model_account_customerpartner->CustomerCountry_Id($this->customer->getId());

			if (empty($country_id)) {

				$this->model_account_customerpartner->becomePartner($this->request->post['shoppartner'],$customer_country_id='',$this->customer->getId(),$this->request->post['description']);
			}else{

				$this->model_account_customerpartner->becomePartner($this->request->post['shoppartner'],$country_id['country_id'],$this->customer->getId(),$this->request->post['description']);
			}
            session()->set('success', $this->language->get('text_success'));
			$this->response->redirect($this->url->link('account/customerpartner/become_partner', '', true));
		}

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
        	'text'      => $this->language->get('heading_title_become_partner'),
			'href'      => $this->url->link('account/customerpartner/become_partner', '', true),
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

		$this->data['in_process'] = false;

		$hasApplied = $this->model_account_customerpartner->IsApplyForSellership();

		if($hasApplied){

			if($this->model_account_customerpartner->chkIsPartner())
				$this->response->redirect($this->url->link('account/customerpartner/dashboard', '', true));
			else{
				$this->data['in_process'] = true;
				$this->data['text_delay'] = $this->language->get('text_delay');
			}

		}else{

			if(isset($this->error['error_shoppartner'])) {
		        $this->data['error_shoppartner'] = $this->error['error_shoppartner'];
		    }else{
				$this->data['error_shoppartner'] = '';
		    }

		    if(isset($this->error['error_description'])) {
		        $this->data['error_description'] = $this->error['error_description'];
		    }else{
				$this->data['error_description'] = '';
		    }

		    if(isset($this->request->post['shoppartner'])) {
		        $this->data['shoppartner'] = $this->request->post['shoppartner'];
		    }else{
				$this->data['shoppartner'] = '';
		    }

			if(isset($this->request->post['description'])) {
		        $this->data['description'] = $this->request->post['description'];
		    }else{
				$this->data['description'] = '';
		    }
		}

		$this->data['button_continue'] = $this->language->get('button_continue');
		$this->data['button_back'] = $this->language->get('button_back');

		$this->data['action'] = $this->url->link('account/customerpartner/become_partner', '', true);
		$this->data['back'] = $this->url->link('account/account', '', true);

		$this->data['isMember'] = true;

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

		$this->response->setOutput($this->load->view('account/customerpartner/become_partner' , $this->data));

	}

	private function validateForm() {

		if(utf8_strlen($this->request->post['shoppartner'])<=3){
            $this->error['error_shoppartner'] = $this->language->get('error_validshop');
        }elseif(utf8_strlen($this->request->post['description'])<=3){
            $this->error['error_description'] = $this->language->get('error_message');
        }else{
            $this->load->model('customerpartner/master');
            if($this->model_customerpartner_master->getShopData($this->request->post['shoppartner'])){
                $this->error['error_shoppartner'] = $this->language->get('error_message');
            }
        }

		if (!$this->error) {
	  		return true;
		} else {
	  		return false;
		}
  	}


}
?>
