<?php

/**
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCustomerpartnerPartner $model_customerpartner_partner
 * @property ModelCustomerpartnerProduct $model_customerpartner_product
 * @property ModelLocalisationLanguage $model_localisation_language
 * @property ModelSettingStore $model_setting_store
 * @property ModelToolImage $model_tool_image
 */
class ControllerCustomerpartnerProduct extends Controller {

	private $error = array();
	private $data = array();

  	public function index() {

		$this->load->language('customerpartner/product');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customerpartner/product');

		$this->getList();
  	}

  	public function delete() {

    	$this->load->language('customerpartner/product');

    	$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customerpartner/product');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {

			foreach ($this->request->post['selected'] as $product_id) {
				$this->model_customerpartner_product->deleteProduct($product_id);
	  		}

			session()->set('success', $this->language->get('text_success'));

			$url = '';

			if (isset($this->request->get['filter_name'])) {
				$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_model'])) {
				$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_price'])) {
				$url .= '&filter_price=' . $this->request->get['filter_price'];
			}

			if (isset($this->request->get['filter_quantity'])) {
				$url .= '&filter_quantity=' . $this->request->get['filter_quantity'];
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['filter_seller'])) {
				$url .= '&filter_seller=' . $this->request->get['filter_seller'];
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

			$this->response->redirect($this->url->link('customerpartner/product', 'user_token=' . session('user_token') . $url, true));
		}

    	$this->getList();
  	}

  	private function getList() {

		$this->load->model('catalog/product');

		if (isset($this->request->get['filter_name'])) {
			$filter_name = $this->request->get['filter_name'];
		} else {
			$filter_name = null;
		}

		if (isset($this->request->get['filter_seller'])) {
			$filter_seller = $this->request->get['filter_seller'];
		} else {
			$filter_seller = null;
		}

		if (isset($this->request->get['filter_model'])) {
			$filter_model = $this->request->get['filter_model'];
		} else {
			$filter_model = null;
		}

		if (isset($this->request->get['filter_price'])) {
			$filter_price = $this->request->get['filter_price'];
		} else {
			$filter_price = null;
		}

		if (isset($this->request->get['filter_quantity'])) {
			$filter_quantity = $this->request->get['filter_quantity'];
		} else {
			$filter_quantity = null;
		}

		if (isset($this->request->get['filter_status'])) {
			$filter_status = $this->request->get['filter_status'];
		} else {
			$filter_status = null;
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'pd.name';
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

		if (isset($this->request->get['filter_seller'])) {
			$url .= '&filter_seller=' . urlencode(html_entity_decode($this->request->get['filter_seller'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_model'])) {
			$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_price'])) {
			$url .= '&filter_price=' . $this->request->get['filter_price'];
		}

		if (isset($this->request->get['filter_quantity'])) {
			$url .= '&filter_quantity=' . $this->request->get['filter_quantity'];
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
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

		$data['base_url'] = HTTP_SERVER;

		if ($this->request->server['HTTPS']) {
			$data['base_url'] = HTTPS_SERVER;
		}

		$data['base_url'] = str_replace('/admin/', '', $data['base_url']);

  		$data['breadcrumbs'] = array();

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true),
      		'separator' => false
   		);

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('customerpartner/product', 'user_token=' . session('user_token') . $url, true),
      		'separator' => ' :: '
   		);

		$data['insert'] = $this->url->link('catalog/product/add', 'user_token=' . session('user_token') . $url . '&mpcheck=1', true);
		$data['copy'] = $this->url->link('customerpartner/product/copy', 'user_token=' . session('user_token') . $url, true);
		$data['approve'] = html_entity_decode($this->url->link('customerpartner/product/approve','user_token=' . session('user_token') . $url, true));
		$data['delete'] = $this->url->link('customerpartner/product/delete', 'user_token=' . session('user_token') . $url, true);

		$data['products'] = array();

		$filterData = array(
			'filter_name'	  => $filter_name,
			'filter_seller'	  => $filter_seller,
			'filter_model'	  => $filter_model,
			'filter_price'	  => $filter_price,
			'filter_quantity' => $filter_quantity,
			'filter_status'   => $filter_status,
			'sort'            => $sort,
			'order'           => $order,
			'start'           => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'           => $this->config->get('config_limit_admin')
		);

		$this->load->model('tool/image');

		$this->load->model('customerpartner/partner');

		$this->load->model('setting/store');

		$data['stores'][] = array(
			'store_id' => 0,
            'name' => 'Default',
						'url' => $data['base_url']
		);

		$data['stores'] = array_merge($data['stores'],$this->model_setting_store->getStores());

		$data['partners'] = $this->model_customerpartner_partner->getCustomers();

		$product_total = $this->model_customerpartner_product->getTotalProducts($filterData);

		$results = $this->model_customerpartner_product->getProducts($filterData);

		foreach ($results as $result) {

			$action = array();

			$product_id = $result['product_id'];

			$action[] = array(
				'text' => $this->language->get('text_edit'),
				'href' => $this->url->link('catalog/product/edit', 'user_token=' . session('user_token') . '&mpcheck=1&product_id=' . $product_id . $url, true)
			);

            $image = $this->model_tool_image->resize($result['image'], 40, 40);
			$special = false;

			$customername = $result['firstname']." ".$result['lastname'];

			$product_specials = $this->model_catalog_product->getProductSpecials($product_id);

			foreach ($product_specials  as $product_special) {
				if (($product_special['date_start'] == '0000-00-00' || $product_special['date_start'] < date('Y-m-d')) && ($product_special['date_end'] == '0000-00-00' || $product_special['date_end'] > date('Y-m-d'))) {
					$special = $product_special['price'];

					break;
				}
			}

      		$data['products'][] = array(
				'product_id' => $result['product_id'],
				'customer_id' => $result['customer_id'],
				'name'       => $result['name'],
				'model'      => $result['model'],
				'price'      => $result['price'],
				'special'    => $special,
				'image'      => $image,
				'quantity'   => $result['quantity'],
				'status'     => $result['status'],
				'selected'   => isset($this->request->post['selected']) && in_array($result['product_id'], $this->request->post['selected']),
				'action'     => $action,
				'seller'	 => $customername
			);
    	}

 		$data['user_token'] = session('user_token');

 		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} elseif (isset($this->session->data['warning'])) {
		  $data['error_warning'] = session('warning');
		  $this->session->remove('warning');
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = session('success');

			$this->session->remove('success');
		} else {
			$data['success'] = '';
		}

		$url = '';

		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_seller'])) {
			$url .= '&filter_seller=' . urlencode(html_entity_decode($this->request->get['filter_seller'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_model'])) {
			$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_price'])) {
			$url .= '&filter_price=' . $this->request->get['filter_price'];
		}

		if (isset($this->request->get['filter_quantity'])) {
			$url .= '&filter_quantity=' . $this->request->get['filter_quantity'];
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_name'] = $this->url->link('customerpartner/product', 'user_token=' . session('user_token') . '&sort=pd.name' . $url, true);
		$data['sort_seller_name'] = $this->url->link('customerpartner/product', 'user_token=' . session('user_token') . '&sort=c.customer_id' . $url, true);
		$data['sort_model'] = $this->url->link('customerpartner/product', 'user_token=' . session('user_token') . '&sort=p.model' . $url, true);
		$data['sort_price'] = $this->url->link('customerpartner/product', 'user_token=' . session('user_token') . '&sort=p.price' . $url, true);
		$data['sort_quantity'] = $this->url->link('customerpartner/product', 'user_token=' . session('user_token') . '&sort=p.quantity' . $url, true);
		$data['sort_status'] = $this->url->link('customerpartner/product', 'user_token=' . session('user_token') . '&sort=p.product_id' . $url, true);
		$data['sort_order'] = $this->url->link('customerpartner/product', 'user_token=' . session('user_token') . '&sort=p.sort_order' . $url, true);

		$url = '';

		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_seller'])) {
			$url .= '&filter_seller=' . urlencode(html_entity_decode($this->request->get['filter_seller'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_model'])) {
			$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_price'])) {
			$url .= '&filter_price=' . $this->request->get['filter_price'];
		}

		if (isset($this->request->get['filter_quantity'])) {
			$url .= '&filter_quantity=' . $this->request->get['filter_quantity'];
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$pagination = new Pagination();
		$pagination->total = $product_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->text = $this->language->get('text_pagination');
		$pagination->url = $this->url->link('customerpartner/product', 'user_token=' . session('user_token') . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($product_total - $this->config->get('config_limit_admin'))) ? $product_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $product_total, ceil($product_total / $this->config->get('config_limit_admin')));

		$data['filter_name'] = $filter_name;
		$data['filter_seller'] = $filter_seller;
		$data['filter_model'] = $filter_model;
		$data['filter_price'] = $filter_price;
		$data['filter_quantity'] = $filter_quantity;
		$data['filter_status'] = $filter_status;

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['low_stock_quantity'] = (int)$this->config->get('marketplace_low_stock_quantity') ? (int)$this->config->get('marketplace_low_stock_quantity') : 5;

		$data['header'] = $this->load->controller('common/header');
		$data['footer'] = $this->load->controller('common/footer');
		$data['column_left'] = $this->load->controller('common/column_left');
		$this->response->setOutput($this->load->view('customerpartner/product_list',$data));

  	}


  	public function approve() {

    	$this->load->language('customerpartner/product');

    	$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customerpartner/product');

    	if ($this->validateDelete() AND isset($this->request->get['product_id'])) {

			$this->model_customerpartner_product->addProduct($this->request->get);

			session()->set('success', $this->language->get('text_success'));
		}

		$url = '';

		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_model'])) {
			$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_price'])) {
			$url .= '&filter_price=' . $this->request->get['filter_price'];
		}

		if (isset($this->request->get['filter_quantity'])) {
			$url .= '&filter_quantity=' . $this->request->get['filter_quantity'];
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['filter_seller'])) {
			$url .= '&filter_seller=' . $this->request->get['filter_seller'];
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

		$this->response->redirect($this->url->link('customerpartner/product', 'user_token=' . session('user_token') . $url, true));

  	}

  	private function validateDelete() {
    	if (!$this->user->hasPermission('modify', 'customerpartner/product')) {
      		$this->error['warning'] = $this->language->get('error_permission');
					session()->set('warning', $this->language->get('error_permission'));
    	}

		if (!$this->error) {
	  		return true;
		} else {
	  		return false;
		}
  	}

	public function autocomplete() {

		$json = array();

		if (isset($this->request->get['filter_name']) || isset($this->request->get['filter_model']) || isset($this->request->get['filter_category_id'])) {

			$this->load->model('customerpartner/product');

			if (isset($this->request->get['filter_name'])) {
				$filter_name = $this->request->get['filter_name'];
			} else {
				$filter_name = '';
			}

			if (isset($this->request->get['filter_model'])) {
				$filter_model = $this->request->get['filter_model'];
			} else {
				$filter_model = '';
			}

			if (isset($this->request->get['filter_category_id'])) {
				$filter_category_id = $this->request->get['filter_category_id'];
			} else {
				$filter_category_id = '';
			}

			if (isset($this->request->get['filter_for_seller'])) {
				$filter_for_seller = $this->request->get['filter_for_seller'];
			} else {
				$filter_for_seller = '';
			}

			if (isset($this->request->get['filter_sub_category'])) {
				$filter_sub_category = $this->request->get['filter_sub_category'];
			} else {
				$filter_sub_category = '';
			}

			if (isset($this->request->get['limit'])) {
				$limit = $this->request->get['limit'];
			} else {
				$limit = 20;
			}

			$data = array(
				'filter_name'         => $filter_name,
				'filter_model'        => $filter_model,
				'filter_category_id'  => $filter_category_id,
				'filter_sub_category' => $filter_sub_category,
				'filter_for_seller'   => $filter_for_seller,
				'start'               => 0,
				'limit'               => $limit
			);

			$results = $this->model_customerpartner_product->getProducts($data);

			foreach ($results as $result) {

				$option_data = array();

				if(!$filter_for_seller || (!isset($result['customer_id']) || !$result['customer_id']))
					$json[] = array(
						'product_id' => $result['product_id'],
						'name'       => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
						'model'      => $result['model'],
						'option'     => $option_data,
						'price'      => $result['price']
					);
			}
		}

		$this->response->setOutput(json_encode($json));
	}

	public function getProduct() {
		if(request()->isMethod('POST') && isset($this->request->post['product_id']) && $this->request->post['product_id']) {
			$product_id = $this->request->post['product_id'];
			$this->load->model('customerpartner/product');
			$product = $this->model_customerpartner_product->getProduct($product_id);
			if($product) {

				$this->load->model('tool/image');
                $product['image'] = $this->model_tool_image->resize($product['image'],250,250);
				$product['price'] = $this->currency->format($product['price'],$this->config->get('config_currency'));
				$this->response->setOutput(json_encode($product));

			} else {
				$this->response->setOutput(json_encode(0));
			}
		}
	}
}
?>
