<?php

/**
 * @property ModelAccountAddress $model_account_address
 * @property ModelAccountCustomer $model_account_customer
 * @property ModelAccountWishlist $model_account_wishlist
 */
class ControllerCheckoutLogin extends Controller {
	public function index() {
		$this->load->language('checkout/checkout');

		$data['checkout_guest'] = ($this->config->get('config_checkout_guest') && !$this->config->get('config_customer_price') && !$this->cart->hasDownload());

		if (isset($this->session->data['account'])) {
			$data['account'] = session('account');
		} else {
			$data['account'] = 'register';
		}

		$data['forgotten'] = $this->url->link('account/password/reset', '', true);

		$this->response->setOutput($this->load->view('checkout/login', $data));
	}

	public function save() {
		$this->load->language('checkout/checkout');

		$json = array();

		if ($this->customer->isLogged()) {
			$json['redirect'] = $this->url->link('checkout/checkout', '', true);
		}

		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart');
		}

		if (!$json) {
			$this->load->model('account/customer');

			// Check how many login attempts have been made.
			$login_info = $this->model_account_customer->getLoginAttempts($this->request->post['email']);

			if ($login_info && ($login_info['total'] >= $this->config->get('config_login_attempts')) && strtotime('-1 hour') < strtotime($login_info['date_modified'])) {
				$json['error']['warning'] = $this->language->get('error_attempts');
			}

			// Check if customer has been approved.
			$customer_info = $this->model_account_customer->getCustomerByEmail($this->request->post['email']);

			if ($customer_info && !$customer_info['status']) {
				$json['error']['warning'] = $this->language->get('error_approved');
			}

			if (!isset($json['error'])) {
				if (!$this->customer->login($this->request->post['email'], $this->request->post['password'])) {
					$json['error']['warning'] = $this->language->get('error_login');

					$this->model_account_customer->addLoginAttempt($this->request->post['email']);
				} else {
					$this->model_account_customer->deleteLoginAttempts($this->request->post['email']);
				}
			}
		}

		if (!$json) {
			// Unset guest
			$this->session->remove('guest');

			// Default Shipping Address
			$this->load->model('account/address');

			if ($this->config->get('config_tax_customer') == 'payment') {
				session()->set('payment_address', $this->model_account_address->getAddress($this->customer->getAddressId()));
			}

			if ($this->config->get('config_tax_customer') == 'shipping') {
				session()->set('shipping_address', $this->model_account_address->getAddress($this->customer->getAddressId()));
			}

			// Wishlist
            if ($this->session->has('wishlist')) {
                $wishlistData = $this->session->get('wishlist', []);
                if (is_array($wishlistData)) {
                    $this->load->model('account/wishlist');

                    $wishlistData = $this->session->get('wishlist');
                    foreach ($wishlistData as $key => $product_id) {
                        $this->model_account_wishlist->addWishlist($product_id);

                        unset($wishlistData[$key]);
                    }
                    $this->session->set('wishlist', $wishlistData);
                }
            }

			$json['redirect'] = $this->url->link('checkout/checkout', '', true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
