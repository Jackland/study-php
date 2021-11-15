<?php

/**
 * @property ModelAccountAddress $model_account_address
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountOrder $model_account_order
 */
class ControllerAccountCustomerpartnerSoldinvoice extends Controller {

	private $error = array();
	private $data = array();

	public function index() {

		if (!$this->customer->isLogged()) {
			session()->set('redirect', $this->url->link('account/customerpartner/soldinvoice&order_id='.$this->request->get['order_id'], '', true));
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->model('account/customerpartner');
		$this->load->model('account/order');

		$this->data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();

		if(!$this->data['chkIsPartner'] || (isset($this->session->data['marketplace_seller_mode']) && !$this->session->data['marketplace_seller_mode']))
			$this->response->redirect($this->url->link('account/account', '', true));

		$this->load->language('account/customerpartner/soldinvoice');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->data['heading_title'] = $this->language->get('heading_title');
		$this->data['text_order'] = $this->language->get('text_order');

      	$order_id = 0;

      	if(isset($this->request->get['order_id'])){
			$order_id = (int)$this->request->get['order_id'];
		}

		$this->data['order_id'] = $order_id;


		$this->data['errorPage'] = true;
		$this->data['direction'] = 'ltr';
		$this->data['lang'] = 'en';
		if ($this->request->server['HTTPS']) {
			$server = $this->config->get('config_ssl');
		} else {
			$server = $this->config->get('config_url');
		}
		$this->data['base'] = $server;

		$order_info = $this->model_account_customerpartner->getOrder($order_id);


		if($order_id AND $order_info){

			$this->data['errorPage'] = false;
			$this->data['name'] = $order_info['firstname'].' '.$order_info['lastname'];
			$this->data['email'] = $order_info['email'];

			if ($order_info['invoice_no']) {
				$this->data['invoice_no'] = $order_info['invoice_prefix'] . $order_info['invoice_no'];
			} else {
				$this->data['invoice_no'] = '';
			}

			$this->data['date_added'] = date($this->language->get('date_format_short'), strtotime($order_info['date_added']));

			if ($order_info['payment_address_format']) {
      			$format = $order_info['payment_address_format'];
    		} else {
				$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
			}

    		$find = array(
	  			'{firstname}',
	  			'{lastname}',
	  			'{company}',
      			'{address_1}',
      			'{address_2}',
     			'{city}',
      			'{postcode}',
      			'{zone}',
				'{zone_code}',
      			'{country}'
			);

			$replace = array(
	  			'firstname' => $order_info['payment_firstname'],
	  			'lastname'  => $order_info['payment_lastname'],
	  			'company'   => $order_info['payment_company'],
      			'address_1' => $order_info['payment_address_1'],
      			'address_2' => $order_info['payment_address_2'],
      			'city'      => $order_info['payment_city'],
      			'postcode'  => $order_info['payment_postcode'],
      			'zone'      => $order_info['payment_zone'],
				'zone_code' => $order_info['payment_zone_code'],
      			'country'   => $order_info['payment_country']
			);

			$this->data['payment_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

			$this->load->model('account/address');
			$store_address = $this->model_account_address->getAddress($this->customer->getAddressId());
			$seller_details = $this->model_account_customerpartner->getProfile();

			$replace_store_address = array(
					'firstname' => $store_address['firstname'],
					'lastname' => $store_address['lastname'],
					'company' => $seller_details['companyname'],
					'address_1' => $store_address['address_1'],
					'address_2' => $store_address['address_2'],
					'city' => $store_address['city'],
					'postcode' => $store_address['postcode'],
					'zone' => $store_address['zone'],
					'zone_code' => $store_address['zone_code'],
					'country' => $store_address['country']
			);

			$formatted_store_address = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace_store_address, $format))));
      		$this->data['payment_method'] = $order_info['payment_method'];
			$order_info['store_address'] = $formatted_store_address;
			$order_info['store_email'] = $this->customer->getEmail();
			$order_info['store_fax'] = '';
			$order_info['store_telephone'] = $this->customer->getTelephone();
			$order_info['store_url'] = $this->url->link('customerpartner/profile&id='.$this->customer->getId(), '', true);

			if ($order_info['shipping_address_format']) {
      			$format = $order_info['shipping_address_format'];
    		} else {
				$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
			}

    		$find = array(
	  			'{firstname}',
	  			'{lastname}',
	  			'{company}',
      			'{address_1}',
      			'{address_2}',
     			'{city}',
      			'{postcode}',
      			'{zone}',
				'{zone_code}',
      			'{country}'
			);

			$replace = array(
	  			'firstname' => $order_info['shipping_firstname'],
	  			'lastname'  => $order_info['shipping_lastname'],
	  			'company'   => $order_info['shipping_company'],
      			'address_1' => $order_info['shipping_address_1'],
      			'address_2' => $order_info['shipping_address_2'],
      			'city'      => $order_info['shipping_city'],
      			'postcode'  => $order_info['shipping_postcode'],
      			'zone'      => $order_info['shipping_zone'],
				'zone_code' => $order_info['shipping_zone_code'],
      			'country'   => $order_info['shipping_country']
			);

			$this->data['shipping_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

			$this->data['shipping_method'] = $order_info['shipping_method'];

			$this->data['products'] = array();

			$products = $this->model_account_customerpartner->getSellerOrderProducts($order_id);

            $sub_total = 0;
            $sub_tax   = 0;
            $shipping  = 0;
      		foreach ($products as $product) {


        		$this->data['products'][] = array(
        			'product_id' => $product['product_id'],
          			'name'     => $product['name'],
          			'model'    => $product['model'],
          			'quantity' => $product['quantity'],
          			'option'   => $this->model_account_order->getOrderOptions($order_id,$product['order_product_id']),
          			'price'    => $this->currency->format($product['c2oprice'], $order_info['currency_code'], $order_info['currency_value']),
					'total'    => $this->currency->format($product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order_info['currency_code'], $order_info['currency_value']),
					);

        		$sub_total += $product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0);
        		// $sub_tax   += $this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0;
      		}

      		$this->data['totals'] = array();

      		$totals = $this->model_account_customerpartner->getOrderTotals($order_id);

      		if($totals AND isset($totals[0]['total'])){
      		    $this->data['totals']['sub_total'] = array(

      		    	'title' => 'SubTotal',
      		    	'text'  => $this->currency->format($sub_total, $order_info['currency_code'], $order_info['currency_value']),
      		    );

      		    if ($order_info['shipping_applied']) {

					$this->data['totals']['shipping']  = array(

						'title' => $order_info['shipping_method'],
						'text'  => $this->currency->format($order_info['shipping_applied'], $order_info['currency_code'], $order_info['currency_value']),
					);
					$shipping = $order_info['shipping_applied'];
				}

  		        // if ($sub_tax) {
  		        //  	$this->data['totals']['sub_tax'] = array(

  		        //  		'title' => 'Total Tax',
  		        //  		'text'  => $this->currency->format($sub_tax, $order_info['currency_code'], $order_info['currency_value']),
  		        //     );
  		        // }

				$this->data['totals']['total'] = array(

					   'title'  => 'Total',
					   'text'   => $this->currency->format($totals[0]['total'], $order_info['currency_code'], $order_info['currency_value']),
			    );
			}

		}

		$this->data['order'] = $order_info;

		$this->data['action'] = $this->url->link('account/customerpartner/soldinvoice&order_id='.$order_id, '', true);

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

		$this->data['back'] = $this->url->link('account/account', '', true);

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
	$this->data['margin'] = "margin-left: 18%";
  $this->data['footer'] = $this->load->controller('account/customerpartner/footer');
  $this->data['header'] = $this->load->controller('account/customerpartner/header');
}

		$this->response->setOutput($this->load->view('account/customerpartner/soldinvoice' , $this->data));
	}
}
?>
