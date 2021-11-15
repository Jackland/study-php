<?php

use App\Components\Storage\StorageCloud;
use App\Models\Customer\Customer;
use App\Enums\Customer\CustomerAccountingType;
use App\Helper\CustomerHelper;
use App\Services\Seller\SellerService;

/**
 * @property ModelCustomerCustomerGroup $model_customer_customer_group
 * @property ModelCustomerpartnerPartner $model_customerpartner_partner
 * @property ModelCustomerpartnerTransaction $model_customerpartner_transaction
 * @property ModelLocalisationCountry $model_localisation_country
 * @property ModelSettingStore $model_setting_store
 * @property ModelToolImage $model_tool_image
 */
class ControllerCustomerpartnerPartner extends Controller {

	private $error = array();
	private $data = array();

  	public function index() {

		$this->load->language('customerpartner/partner');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customerpartner/partner');

    	$this->getList();
  	}

  	private function getList() {

		if (isset($this->request->get['filter_name'])) {
			$filter_name = $this->request->get['filter_name'];
		} else {
			$filter_name = null;
		}

		if (isset($this->request->get['filter_email'])) {
			$filter_email = $this->request->get['filter_email'];
		} else {
			$filter_email = null;
		}

		if (isset($this->request->get['filter_customer_group_id'])) {
			$filter_customer_group_id = $this->request->get['filter_customer_group_id'];
		} else {
			$filter_customer_group_id = null;
		}

		if (isset($this->request->get['filter_status'])) {
			$filter_status = $this->request->get['filter_status'];
		} else {
			$filter_status = null;
		}

		if (isset($this->request->get['filter_approved'])) {
			$filter_approved = $this->request->get['filter_approved'];
		} else {
			$filter_approved = null;
		}

		if (isset($this->request->get['filter_ip'])) {
			$filter_ip = $this->request->get['filter_ip'];
		} else {
			$filter_ip = null;
		}

		if (isset($this->request->get['filter_date_added'])) {
			$filter_date_added = $this->request->get['filter_date_added'];
		} else {
			$filter_date_added = null;
		}

		if (isset($this->request->get['view_all'])) {
			$filter_all = $this->request->get['view_all'];
		} else {
			$filter_all = 0;
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'customer_id';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_email'])) {
			$url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_customer_group_id'])) {
			$url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['filter_approved'])) {
			$url .= '&filter_approved=' . $this->request->get['filter_approved'];
		}

		if (isset($this->request->get['filter_ip'])) {
			$url .= '&filter_ip=' . $this->request->get['filter_ip'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		if (isset($this->request->get['view_all'])) {
			$url .= '&view_all=' . $this->request->get['view_all'];
		}

  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true),
      		'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . $url, true),
      		'separator' => ' :: '
   		);

		$this->data['approve'] = $this->url->link('customerpartner/partner/approve', 'user_token=' . session('user_token') . $url, true);

        if (version_compare(VERSION, '2.1', '>=')) {
			$this->data['insert'] = $this->url->link('customer/customer/add', 'user_token=' . session('user_token') . $url, true);
		} else {
			$this->data['insert'] = $this->url->link('sale/customer/add', 'user_token=' . session('user_token') . $url, true);
		}
		$this->data['delete'] = $this->url->link('customerpartner/partner/delete', 'user_token=' . session('user_token') . $url, true);

		$this->data['customers'] = array();

		$data = array(
			'filter_name'              => $filter_name,
			'filter_email'             => $filter_email,
			'filter_all'               => $filter_all,
			'filter_customer_group_id' => $filter_customer_group_id,
			'filter_status'            => $filter_status,
			'filter_approved'          => $filter_approved,
			'filter_date_added'        => $filter_date_added,
			'filter_ip'                => $filter_ip,
			'sort'                     => $sort,
			'order'                    => $order,
			'start'                    => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'                    => $this->config->get('config_limit_admin')
		);

		$customer_total = $this->model_customerpartner_partner->getTotalCustomers($data);

		$results = $this->model_customerpartner_partner->getCustomers($data);

    	foreach ($results as $result) {
			$action = array();

			$action[] = array(
				'text' => $this->language->get('text_edit'),
				'href' => $this->url->link('customerpartner/partner/update', 'user_token=' . session('user_token') . '&customer_id=' . $result['customer_id'] . $url, true)
			);

			if($result['is_partner']){
				$is_partner = ($result['is_partner'] == 0) ? "Not Partner" : "Partner";
				$commission = $result['commission'];
			}
			else{
				$is_partner = "Normal customer";
				$commission = '';
			}

			$this->data['customers'][] = array(
				'customer_id'    => $result['customer_id'],
				'name'           => $result['name'],
				'email'          => $result['email'],
				'customer_group' => $result['customer_group'],
				'status'         => ($result['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled')),
				'ip'             => $result['ip'],
				'date_added'     => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'selected'       => isset($this->request->post['selected']) && in_array($result['customer_id'], $this->request->post['selected']),
				'action'         => $action,
				'is_partner'	 => $is_partner,
				'commission'	=>	$commission
			);
		}

		$this->data['user_token'] = session('user_token');

		if (isset($this->session->data['error_warning'])) {
			$this->error['warning'] = session('error_warning');
			$this->session->remove('error_warning');
		}

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

		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_email'])) {
			$url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_customer_group_id'])) {
			$url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['filter_approved'])) {
			$url .= '&filter_approved=' . $this->request->get['filter_approved'];
		}

		if (isset($this->request->get['filter_ip'])) {
			$url .= '&filter_ip=' . $this->request->get['filter_ip'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		if (isset($this->request->get['view_all'])) {
			$url .= '&view_all=' . $this->request->get['view_all'];
		}

		$this->data['sort_customerId'] = $this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . '&sort=customer_id' . $url, true);
		$this->data['sort_name'] = $this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . '&sort=name' . $url, true);
		$this->data['sort_email'] = $this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . '&sort=c.email' . $url, true);
		$this->data['sort_customer_group'] = $this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . '&sort=customer_group' . $url, true);
		$this->data['sort_status'] = $this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . '&sort=c.status' . $url, true);
		$this->data['sort_approved'] = $this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . '&sort=c.approved' . $url, true);
		$this->data['sort_ip'] = $this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . '&sort=c.ip' . $url, true);
		$this->data['sort_date_added'] = $this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . '&sort=c.date_added' . $url, true);

		$url = '';

		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_email'])) {
			$url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_customer_group_id'])) {
			$url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['filter_approved'])) {
			$url .= '&filter_approved=' . $this->request->get['filter_approved'];
		}

		if (isset($this->request->get['filter_ip'])) {
			$url .= '&filter_ip=' . $this->request->get['filter_ip'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['view_all'])) {
			$url .= '&view_all=' . $this->request->get['view_all'];
			$this->data['customer_type'] = $this->request->get['view_all'];
		}

		$pagination = new Pagination();
		$pagination->total = $customer_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . $url . '&page={page}', true);

		$this->data['pagination'] = $pagination->render();

		$this->data['results'] = sprintf($this->language->get('text_pagination'), ($customer_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($customer_total - $this->config->get('config_limit_admin'))) ? $customer_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $customer_total, ceil($customer_total / $this->config->get('config_limit_admin')));

		$this->data['filter_name'] = $filter_name;
		$this->data['filter_email'] = $filter_email;
		$this->data['filter_customer_group_id'] = $filter_customer_group_id;
		$this->data['filter_status'] = $filter_status;
		$this->data['filter_approved'] = $filter_approved;
		$this->data['filter_ip'] = $filter_ip;
		$this->data['filter_date_added'] = $filter_date_added;
		$this->data['wk_viewall'] = $filter_all;

		if (version_compare(VERSION, '2.1', '>=')) {
			$this->load->model('customer/customer_group');
	    	$this->data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();
		} else {
			$this->load->model('sale/customer_group');
	    	$this->data['customer_groups'] = $this->model_sale_customer_group->getCustomerGroups();
		}

		$this->data['add']  = $this->url->link('customer/customer/add', 'user_token=' . session('user_token') . $url. '&create_seller', true);

		$this->load->model('setting/store');

		$this->data['stores'] = $this->model_setting_store->getStores();

		$this->data['sort'] = $sort;
		$this->data['order'] = $order;

		$this->data['header'] = $this->load->controller('common/header');
		$this->data['footer'] = $this->load->controller('common/footer');
		$this->data['column_left'] = $this->load->controller('common/column_left');

		$this->response->setOutput($this->load->view('customerpartner/partner_list',$this->data));

  	}

  	public function delete() {

		$this->load->language('customerpartner/partner');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customerpartner/partner');

		if (isset($this->request->post['selected']) && $this->validateForm()) {
			foreach ($this->request->post['selected'] as $customer_id) {
				$this->model_customerpartner_partner->deleteCustomer($customer_id);
			}

			session()->set('success', $this->language->get('text_success'));

			$url = '';

			if (isset($this->request->get['filter_name'])) {
				$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_email'])) {
				$url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_customer_group_id'])) {
				$url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['filter_approved'])) {
				$url .= '&filter_approved=' . $this->request->get['filter_approved'];
			}

			if (isset($this->request->get['filter_ip'])) {
				$url .= '&filter_ip=' . $this->request->get['filter_ip'];
			}

			if (isset($this->request->get['filter_date_added'])) {
				$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['view_all'])) {
				$url .= '&view_all=' . $this->request->get['view_all'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . $url, true));
		}

		$this->getList();
	}

	public function approve() {

		$this->load->language('customerpartner/partner');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customerpartner/partner');

		if (!$this->user->hasPermission('modify', 'customerpartner/partner')) {
			$this->error['warning'] = $this->language->get('error_permission');

		} elseif (isset($this->request->post['selected'])) {

			$approved = $setstatus = 0;

			foreach ($this->request->post['selected'] as $customer_id) {

				if(isset($this->request->get['set_status']))
					$setstatus = $this->request->get['set_status'];

				$customer_info = $this->model_customerpartner_partner->approve($customer_id,$setstatus);

				$approved++;

				//to do send mail to seller after set status..
			}

			session()->set('success', sprintf($this->language->get('text_approved'), $approved));

			$url = '';

			if (isset($this->request->get['filter_name'])) {
				$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_email'])) {
				$url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_customer_group_id'])) {
				$url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['filter_approved'])) {
				$url .= '&filter_approved=' . $this->request->get['filter_approved'];
			}

			if (isset($this->request->get['filter_ip'])) {
				$url .= '&filter_ip=' . $this->request->get['filter_ip'];
			}

			if (isset($this->request->get['filter_date_added'])) {
				$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			if (isset($this->request->get['view_all'])) {
				$url .= '&view_all=' . $this->request->get['view_all'];
			}


			$this->response->redirect($this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . $url, true));

		}

		$this->getList();
	}

  	public function update() {

		$this->load->language('customerpartner/partner');

    	$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customerpartner/partner');

		if (version_compare(VERSION, '2.1', '>=')) {
			$this->load->language('customer/customer');
			$this->load->model('customer/customer');
		} else {
			$this->load->language('sale/customer');
			$this->load->model('sale/customer');
		}

    	if ((request()->isMethod('POST')) && $this->validateForm()) {
            $customerId = $this->request->get('customer_id', 0);
            $post = $this->request->post();
            $originCustomerInfo = Customer::find($customerId);
            $screenName = $originCustomerInfo->seller->screenname;
			//for mp
			$this->model_customerpartner_partner->updatePartner($customerId,$post);

            //giga onsite分组卖家，需要同步到giga onsite系统  ps:这儿只有sreenname可能变化
            if ($originCustomerInfo->accounting_type == CustomerAccountingType::GIGA_ONSIDE) {
                if ($screenName !== $post['customer']['screenname']) {
                    app(CustomerHelper::class)->postAccountInfoToOnSite($customerId);
                }
            }
            //7787 同步到seller开户表
            if ($this->request->get('customer_id', 0)) {
                $post = $this->request->post();
                $accountSaveInfo = [
                    'store_name' => $post['customer']['screenname'],
                ];
                $accountSaveInfo = array_filter($accountSaveInfo);
                if ($accountSaveInfo) {
                    app(SellerService::class)->updateSellerAccountApplyInfo($this->request->get('customer_id', 0), $accountSaveInfo);
                }
            }
            if(isset($this->request->post['product_ids']) AND $this->request->post['product_ids']){
				$this->model_customerpartner_partner->addproduct($this->request->get['customer_id'],$this->request->post);
	  		}

			session()->set('success', $this->language->get('text_success'));

			$url = '';

			if (isset($this->request->get['filter_name'])) {
				$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_email'])) {
				$url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_customer_group_id'])) {
				$url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['filter_approved'])) {
				$url .= '&filter_approved=' . $this->request->get['filter_approved'];
			}

			if (isset($this->request->get['filter_ip'])) {
				$url .= '&filter_ip=' . $this->request->get['filter_ip'];
			}

			if (isset($this->request->get['filter_date_added'])) {
				$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . $url, true));
		}

    	$this->getForm();
  	}

  	private function getForm() {

		$this->data['user_token'] = session('user_token');

		if (isset($this->request->get['customer_id'])) {
			$this->data['customer_id'] = (int)$this->request->get['customer_id'];
		} else {
			$this->data['customer_id'] = 0;
		}

		$page = 1;

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		}

		$filter_array = array(
			'start' => ($page-1) * $this->config->get('config_limit_admin'),
			'limit'	=> $this->config->get('config_limit_admin'),
		);

		$this->data['admin_products'] = $this->model_customerpartner_partner->getAdminProducts($this->data['customer_id'],$filter_array);

		$product_total = $this->model_customerpartner_partner->getAdminProductsTotal($this->data['customer_id']);

		if (isset($this->request->post['product_ids'])) {
			$this->data['product_ids'] = $this->request->post['product_ids'];
		}else{
			$this->data['product_ids'] = array();
		}

 		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}

		if (isset($this->error['error_companyname'])) {
			$this->data['error_companyname'] = $this->error['error_companyname'];
		} else {
			$this->data['error_companyname'] = '';
		}

		$url = '';

		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_email'])) {
			$url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_customer_group_id'])) {
			$url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['filter_approved'])) {
			$url .= '&filter_approved=' . $this->request->get['filter_approved'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true),
      		'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . $url, true),
      		'separator' => ' :: '
   		);

		if (isset($this->data['customer_id']))
			$this->data['action'] = $this->url->link('customerpartner/partner/update', 'user_token=' . session('user_token') . '&customer_id=' . $this->data['customer_id'] . $url, true);

    	$this->data['cancel'] = $this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . $url, true);

		$partner_info = $this->model_customerpartner_partner->getPartner($this->data['customer_id']);

    	if($partner_info){

				if (!$partner_info['country']) {
				  $partner_info['country'] = 'af';

				  $profile = $this->model_customerpartner_partner->getCustomer($this->data['customer_id']);

				  if (isset($profile['address_id']) && $profile['address_id']) {
				    $this->load->model('customer/customer');

				    $address_data = $this->model_customer_customer->getAddress($profile['address_id']);

				    if (isset($address_data['iso_code_2']) && $address_data['iso_code_2']) {
				      $partner_info['country'] = $address_data['iso_code_2'];
				    }
				  }
				}
            //2019-06-12 注释对seller详情页冗余的订单查询循环逻辑
//    		$this->data['partner_orders'] = $this->model_customerpartner_partner->getSellerOrders($this->request->get['customer_id']);
//
//    		foreach ($this->data['partner_orders'] as $key => $value) {
//
//    			$products = $this->model_customerpartner_partner->getSellerOrderProducts($value['order_id']);
//
//				$this->data['partner_orders'][$key]['productname'] = '';
//				$this->data['partner_orders'][$key]['total'] = 0;
//
//				if($products){
//					foreach ($products as $key2 => $value) {
//						$this->data['partner_orders'][$key]['productname'] = $this->data['partner_orders'][$key]['productname'].$value['name'].' x '.$value['quantity'].' , ';
//						$this->data['partner_orders'][$key]['total'] += $value['c2oprice'];
//					}
//				}
//
//				$this->data['partner_orders'][$key]['total'] = $this->currency->format($this->data['partner_orders'][$key]['total'] ,$this->config->get('config_currency'));
//
//				$this->data['partner_orders'][$key]['view'] = $this->url->link('sale/order/info', 'user_token=' . session('user_token') . '&order_id='.$value['order_id'], true);
//    			$this->data['partner_orders'][$key]['edit'] = $this->url->link('sale/order/edit', 'user_token=' . session('user_token') . '&order_id='.$value['order_id'], true);
//    		}

    		$this->load->model('tool/image');
    		$this->data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

    		foreach ($partner_info as $key => $value) {
    			$this->data[$key] = $value;
    			if($key=='avatar' || $key=='companylogo' || $key=='companybanner' || $key=='qr_code'){
                    $imageUrl = StorageCloud::image()->getUrl($value, ['w' => 100, 'h' => 100]);
                    if ($imageUrl) {
                        $this->data[$key.'_placeholder'] = $imageUrl;
                    }else{
                        $this->data[$key.'_placeholder'] = $this->model_tool_image->resize($value, 100, 100);
                    }
    			}
    		}

			$this->data['loadLocation'] = html_entity_decode($this->url->link('customerpartner/partner/loadLocation','user_token='. session('user_token') . '&location='.$partner_info['companylocality'],true));

    		$this->data['partner_amount'] = $this->sellerCommission($partner_info['commission']);

    	}else{
    		session()->set('error_warning', $this->language->get('error_seller'));
			$this->response->redirect($this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . $url, true));
    	}

		if (isset($this->request->post['commission'])) {
      		$this->data['commission'] = $this->request->post['commission'];
    	} elseif (!empty($partner_info)) {
			$this->data['commission'] = $partner_info['commission'];
		} else {
      		$this->data['commission'] = '';
    	}

    	if (isset($this->request->post['paypalid'])) {
      		$this->data['paypalid'] = $this->request->post['paypalid'];
    	} elseif (!empty($partner_info)) {
				$this->data['paypalid'] = $partner_info['paypalid'];
			} else {
      		$this->data['paypalid'] = '';
    	}

    	if (isset($this->request->post['otherpayment'])) {
      		$this->data['otherpayment'] = $this->request->post['otherpayment'];
    	} elseif (!empty($partner_info)) {
			$this->data['otherpayment'] = $partner_info['otherpayment'];
		} else {
      		$this->data['otherpayment'] = '';
    	}

			if (isset($this->request->post['paypalfirst'])) {
			  $this->data['paypalfirst'] = $this->request->post['paypalfirst'];
			} elseif (!empty($partner_info)) {
			  $this->data['paypalfirst'] = $partner_info['paypalfirstname'];
			} else {
			  $this->data['paypalfirst'] = '';
			}

			if (isset($this->request->post['paypallast'])) {
			  $this->data['paypallast'] = $this->request->post['paypallast'];
			} elseif (!empty($partner_info)) {
			  $this->data['paypallast'] = $partner_info['paypallastname'];
			} else {
			  $this->data['paypallast'] = '';
			}

    	$this->data['transactionTab'] = $this->url->link('customerpartner/transaction/addtransaction','user_token='.session('user_token').'seller_id='.$this->request->get['customer_id'].'action=partner' , true);

		$this->load->model('localisation/country');
		$this->data['countries'] = $this->model_localisation_country->getCountries();

		$pagination = new Pagination();
		$pagination->total = $product_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('customerpartner/partner/update', 'user_token=' . session('user_token') .'&page={page}'.'&customer_id='.$this->request->get['customer_id'], true);

		$this->data['pagination'] = $pagination->render();

		$this->data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($product_total - $this->config->get('config_limit_admin'))) ? $product_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $product_total, ceil($product_total / $this->config->get('config_limit_admin')));

		$this->data['header'] = $this->load->controller('common/header');
		$this->data['footer'] = $this->load->controller('common/footer');
		$this->data['column_left'] = $this->load->controller('common/column_left');

		$this->response->setOutput($this->load->view('customerpartner/partner_form',$this->data));
	}

  	public function transaction() {

		$this->load->language('customerpartner/partner');

		$this->load->model('customerpartner/partner');
		$this->load->model('customerpartner/transaction');

		if ((request()->isMethod('POST')) && $this->user->hasPermission('modify', 'customerpartner/partner')){
			$this->model_customerpartner_transaction->addPartnerTransaction($this->request->get['customer_id'], $this->request->post['description'], $this->request->post['amount']);

			$this->data['success'] = $this->language->get('text_success');
		} else {
			$this->data['success'] = '';
		}

		if ((request()->isMethod('POST')) && !$this->user->hasPermission('modify', 'customerpartner/partner')) {
			$this->data['error_warning'] = $this->language->get('error_permission');
		} else {
			$this->data['error_warning'] = '';
		}

		$this->data['text_no_results'] = $this->language->get('text_no_results');
		$this->data['text_balance'] = $this->language->get('text_balance');

		$this->data['column_date_added'] = $this->language->get('column_date_added');
		$this->data['column_description'] = $this->language->get('column_description');
		$this->data['column_amount'] = $this->language->get('column_amount');

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		$this->data['transactions'] = array();

		$results = $this->model_customerpartner_transaction->getTransactions($this->request->get['customer_id'], ($page - 1) * $this->config->get('config_limit_admin'), $this->config->get('config_limit_admin'));

		foreach ($results as $result) {
			$this->data['transactions'][] = array(
				'amount'      => $this->currency->format($result['amount'], $this->config->get('config_currency')),
				'description' => $result['details'],
				'date_added'  => date($this->language->get('date_format_short'), strtotime($result['date_added']))
			);
		}

		$this->data['balance'] = $this->currency->format($this->model_customerpartner_transaction->getTransactionTotal($this->request->get['customer_id']), $this->config->get('config_currency'));

		$transaction_total = $this->model_customerpartner_transaction->getTotalTransactions($this->request->get['customer_id']);

		$pagination = new Pagination();
		$pagination->total = $transaction_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('customerpartner/partner/transaction', 'user_token=' . session('user_token') . $url . '&page={page}', true);

		$this->data['pagination'] = $pagination->render();

		$this->data['results'] = sprintf($this->language->get('text_pagination'), ($transaction_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($transaction_total - $this->config->get('config_limit_admin'))) ? $transaction_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $transaction_total, ceil($transaction_total / $this->config->get('config_limit_admin')));

		if (version_compare(VERSION, '2.1', '>=')) {
			$this->response->setOutput($this->load->view('customer/customer_transaction', $this->data));
		} else {
			$this->response->setOutput($this->load->view('sale/customer_transaction', $this->data));
		}

	}

	public function autocomplete() {

		$json = array();

		if (isset($this->request->get['filter_name']) || isset($this->request->get['filter_email'])) {

			$this->load->model('customerpartner/partner');

			if (isset($this->request->get['filter_name'])) {
				$filter_name = $this->request->get['filter_name'];
			} else {
				$filter_name = '';
			}

			if (isset($this->request->get['filter_view'])) {
				$filter_view = $this->request->get['filter_view'];
			} else {
				$filter_view = 0 ;
			}

			if (isset($this->request->get['filter_category'])) {
				$filter_category = $this->request->get['filter_category'];
			} else {
				$filter_category = 0 ;
			}

			if (isset($this->request->get['filter_email'])) {
				$filter_email = $this->request->get['filter_email'];
			} else {
				$filter_email = '';
			}

			if (isset($this->request->get['limit'])) {
				$limit = $this->request->get['limit'];
			} else {
				$limit = 20;
			}

			$data = array(
				'filter_name'         => $filter_name,
				'filter_all'         => $filter_view,
				'filter_category'         => $filter_category,
				'filter_email'  	  => $filter_email,
				'start'               => 0,
				'limit'               => $limit
			);

			$results = $this->model_customerpartner_partner->getCustomers($data);

			foreach ($results as $result) {

				$option_data = array();

				$json[] = array(
					'id' 		 => $result['customer_id'],
					'name'       => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
					'email'      => $result['email'],
				);
			}
		}

		$this->response->setOutput(json_encode($json));
	}

	public function updateProductSeller() {

		$json = array();

		$this->load->language('customerpartner/partner');

		if ($this->validateForm() AND isset($this->request->get['product_id']) AND isset($this->request->get['partner_id'])) {

			$this->load->model('customerpartner/partner');

			$results = $this->model_customerpartner_partner->updateProductSeller($this->request->get['partner_id'],$this->request->get['product_id']);

			$json['success'] = $this->language->get('text_success_seller');

		}elseif(isset($this->error['warning'])){
			$json['success'] = $this->error['warning'];
		}

		$this->response->setOutput(json_encode($json));
	}

	public function sellerCommission($commission = 0){

		//get commission for seller
		$this->load->model('customerpartner/partner');
		$partner_amount = $this->model_customerpartner_partner->getPartnerAmount($this->request->get['customer_id']);

		if($partner_amount){
			$total = $partner_amount['total'];
			$admin_part = $partner_amount['admin'];
			$partner_part = $partner_amount['customer'];
			$paid = $partner_amount['paid'];
			$left = $partner_part - $partner_amount['paid'];

			$partner_amount = array(
				'commission' => $commission,
				'qty_sold' => $partner_amount['quantity'] ? $partner_amount['quantity'] : ' 0 ',
				'total' => $this->currency->format($total ,$this->config->get('config_currency')) ,
				'paid' => $this->currency->format($paid ,$this->config->get('config_currency')) ,
				'left_amount' => $this->currency->format($left, $this->config->get('config_currency')) ,
				'admin_amount' => $this->currency->format($admin_part,$this->config->get('config_currency')),
				'partner_amount' => $this->currency->format($partner_part,$this->config->get('config_currency')),
			);
		}

		$this->response->setOutput(json_encode($partner_amount));

		// return $partner_amount;
	}

	private function validateForm() {

    	if (!$this->user->hasPermission('modify', 'customerpartner/partner')) {
      		$this->error['warning'] = $this->language->get('error_permission');
    	}

			$this->load->model('customerpartner/partner');

    	if (isset($this->request->post['customer']['companyname']) && $this->request->post['customer']['companyname']) {
    		$check_companyname = $this->model_customerpartner_partner->checkComanyNameExists($this->request->post['customer']['companyname']);

    		if ($check_companyname && ($check_companyname['customer_id'] != $this->request->get['customer_id'])) {
    			$this->error['error_companyname'] = $this->language->get('error_companyname_exists');
    		}
    	}
		if (isset($this->request->get['customer_id']) && $this->request->get['customer_id']) {

			$profile = $this->model_customerpartner_partner->getCustomer($this->request->get['customer_id']);

			if (isset($this->request->post['customer']['paypalid']) && $this->request->post['customer']['paypalid'] && isset($this->request->post['paypalfirst']) && $this->request->post['paypalfirst'] && isset($this->request->post['paypallast']) && $this->request->post['paypallast']) {
				if(!filter_var($this->request->post['customer']['paypalid'], FILTER_VALIDATE_EMAIL)) {
					$this->error['warning'] = $this->language->get('error_paypalid');
				} else {

					$API_UserName = $this->config->get('marketplace_paypal_user');

					$API_Password = $this->config->get('marketplace_paypal_password');

					$API_Signature = $this->config->get('marketplace_paypal_signature');

					$API_RequestFormat = "NV";

					$API_ResponseFormat = "NV";

					$API_EMAIL = $this->request->post['customer']['paypalid'];

					$bodyparams = array(
						"matchCriteria" => "NAME",
						"emailAddress" =>$this->request->post['customer']['paypalid'],
						"firstName" => $this->request->post['paypalfirst'],
						"lastName" => $this->request->post['paypallast']
					);

					if ($this->config->get('marketplace_paypal_mode')) {

						$API_AppID = "APP-80W284485P519543T";

						$curl_url = trim("https://svcs.sandbox.paypal.com/AdaptiveAccounts/GetVerifiedStatus");

						$header = array(
							"X-PAYPAL-SECURITY-USERID: " . $API_UserName ,
							"X-PAYPAL-SECURITY-SIGNATURE: " . $API_Signature ,
							"X-PAYPAL-SECURITY-PASSWORD: " . $API_Password ,
							"X-PAYPAL-APPLICATION-ID: " . $API_AppID ,
							"X-PAYPAL-REQUEST-DATA-FORMAT: " . $API_RequestFormat ,
							"X-PAYPAL-RESPONSE-DATA-FORMAT:" . $API_ResponseFormat ,
							"X-PAYPAL-SANDBOX-EMAIL-ADDRESS:" . $API_EMAIL ,
						);
					} else {

						$API_AppID = $this->config->get('marketplace_paypal_appid');

						$curl_url = trim("https://svcs.paypal.com/AdaptiveAccounts/GetVerifiedStatus");

						$header = array(
							"X-PAYPAL-SECURITY-USERID: " . $API_UserName ,
							"X-PAYPAL-SECURITY-SIGNATURE: " . $API_Signature ,
							"X-PAYPAL-SECURITY-PASSWORD: " . $API_Password ,
							"X-PAYPAL-APPLICATION-ID: " . $API_AppID ,
							"X-PAYPAL-REQUEST-DATA-FORMAT: " . $API_RequestFormat ,
							"X-PAYPAL-RESPONSE-DATA-FORMAT:" . $API_ResponseFormat ,
							"X-PAYPAL-EMAIL-ADDRESS:" . $API_EMAIL ,
						);
					}

					$body_data = http_build_query($bodyparams, "", chr(38));

					$curl = curl_init();

					curl_setopt($curl, CURLOPT_URL, $curl_url);

					curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

					curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

					curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

					curl_setopt($curl, CURLOPT_POSTFIELDS, $body_data);


					curl_setopt($curl, CURLOPT_HTTPHEADER,$header);

					$response = strtolower(explode("=",explode('&', curl_exec($curl))[1])[1]);

					if ($response != 'success') {
						$this->error['warning'] = $this->language->get('error_paypalid');
					}
				}
			} else {
				$this->request->post['customer']['paypalid'] = isset($profile['paypalid']) && $profile['paypalid'] ? $profile['paypalid'] : '';
			}
		}
		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_warning');
		}

		if (!$this->error) {
	  		return true;
		} else {
	  		return false;
		}
  	}

  	//for location tab
	public function loadLocation(){

		if($this->request->get['location']){
			$location = '<iframe id="seller-location" width="100%" height="400" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?q='.$this->request->get['location'].'&amp;output=embed"></iframe>';

			$this->response->setOutput($location);
		}else{
			$this->response->setOutput('No location added by Seller.');
		}
	}

	public function pagination() {

		$json = array();

		if (isset($this->request->get['customer_id']) && $this->request->get['customer_id']) {

			$this->load->model('customerpartner/partner');

			$filter_name = '';

			if (isset($this->request->get['filter_name']) && $this->request->get['filter_name']) {
			  $filter_name = $this->request->get['filter_name'];
			}

			$product_total = $this->model_customerpartner_partner->getAdminProductsTotal($this->request->get['customer_id'], array('filter_name' => $filter_name));

			$page = 1;

			if (isset($this->request->get['page']) && $this->request->get['page']) {
			  $page =	$this->request->get['page'];
			}

			$filter_array = array(
			  'start' => ($page-1) * $this->config->get('config_limit_admin'),
			  'limit'	=> $this->config->get('config_limit_admin'),
			  'filter_name' => $filter_name
			);

			$json['admin_products'] = $this->model_customerpartner_partner->getAdminProducts($this->request->get['customer_id'],$filter_array);

			$pagination = new Pagination();

			$pagination->total = $product_total;

			$pagination->page = $page;

			$pagination->limit = $this->config->get('config_limit_admin');

			$pagination->url = $this->url->link('customerpartner/partner/update', 'user_token=' . session('user_token') .'&page={page}'.'&customer_id='.$this->request->get['customer_id'], true);

			$json['pagination'] = $pagination->render();

			$json['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($product_total - $this->config->get('config_limit_admin'))) ? $product_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $product_total, ceil($product_total / $this->config->get('config_limit_admin')));
		}

		$this->response->setOutput(json_encode($json));
	}

}
?>
