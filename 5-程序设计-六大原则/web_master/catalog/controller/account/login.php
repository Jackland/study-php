<?php

/**
 * @property ModelAccountCustomer $model_account_customer
 * @property ModelAccountCustomerpartnerAccountAuthorization $model_account_customerpartner_account_authorization
 * @property ModelAccountAddress $model_account_address
 * @property ModelAccountCustomerpartnerColumnLeft $model_account_customerpartner_column_left
 * @property ModelAccountWishlist $model_account_wishlist
 * @property ModelDesignBanner $model_design_banner
 * @property ModelToolImage $model_tool_image
 *
 * Class ControllerAccountLogin
 */
class ControllerAccountLogin extends Controller {
	private $error = array();

    /**
     * @throws ReflectionException
     * @throws Exception
     */
	public function index() {
	    $this->load->model('account/customer');
        $this->load->model('account/customerpartner');
        setcookie('login_flag', 0);
        setcookie('is_partner', 1);
        setcookie('is_exist', '');
        setcookie('is_notifications', '');
        setcookie('can_show', '');

        // 帐户经理登陆seller帐号做权限管理 102466
        if (!empty($this->request->get['seller_token']) && !empty($this->request->get['account_manager_token'])) {
            $this->accountManagerToSellerLogin($this->request->get['seller_token'], $this->request->get['account_manager_token']);
        }

		// Login override for admin users
		if (!empty($this->request->get['token'])) {
            $this->redirectClear();

			$customer_info = $this->model_account_customer->getCustomerByToken($this->request->get['token']);
			if ($customer_info) {
                $this->model_account_customer->clearTokenByCustomerId(intval($customer_info['customer_id']));
            } else {
                $this->model_account_customer->clearToken();
            }

			if ($customer_info && $this->customer->login($customer_info['email'], '', true)) {
				// Default Addresses
				$this->load->model('account/address');

				if ($this->config->get('config_tax_customer') == 'payment') {
					session()->set('payment_address', $this->model_account_address->getAddress($this->customer->getAddressId()));
				}

				if ($this->config->get('config_tax_customer') == 'shipping') {
					session()->set('shipping_address', $this->model_account_address->getAddress($this->customer->getAddressId()));
				}

                $isPartner = $this->customer->isPartner();

                if($isPartner){
                    //设置初次登陆
                    setcookie('login_flag', 1);
                    setcookie('is_partner', 1);
//                    $this->response->redirect($this->url->link('account/customerpartner/productlist', '&view=separate', true));
                    $this->response->redirect($this->url->link('customerpartner/seller_center/index', '', true));
                }else{
                    //设置初次登陆
                    setcookie('login_flag', 1);
                    setcookie('is_partner', 0);

                    if($this->customer->getGroupId() == 27){
                        $this->response->redirect($this->url->link('account/order_for_giga', '', true));
                    }else{
                        $this->response->redirect($this->url->link('account/guide', '', true));
                    }
                }
			}
		}

		if ($this->customer->isLogged() && $this->customer->getGroupId() != 27) {
            // 消息中心发送邮件跳转到登陆
            if (isset($this->request->get['redirect'])) {
                if (stristr($this->request->get['redirect'], 'route=')) {
                    $this->response->redirect($this->request->get['redirect']);
                }else{
                    $this->response->redirect($this->url->link($this->request->get['redirect'], '', true));
                }
            }
			$this->response->redirect($this->url->link('account/account', '', true));
		}elseif ($this->customer->getGroupId() == 27){
            $this->customer->logout();

            $this->session->remove('shipping_address');
            $this->session->remove('shipping_method');
            $this->session->remove('shipping_methods');
            $this->session->remove('payment_address');
            $this->session->remove('payment_method');
            $this->session->remove('payment_methods');
            $this->session->remove('comment');
            $this->session->remove('order_id');
            $this->session->remove('coupon');
            $this->session->remove('reward');
            $this->session->remove('voucher');
            $this->session->remove('vouchers');
//            $this->session->remove('country');
            // 删除cookie
            setcookie('country', NULL);
            //删除登陆信息的flag
            setcookie('is_exist', '');
            setcookie('login_flag', 0);
            setcookie('is_partner', 1);
            setcookie('is_notifications', '');
            setcookie('can_show', '');
        }

		$this->load->language('account/login');

		$this->document->setTitle($this->language->get('heading_title'));
		$search_flag = $this->request->query->get('search_flag',0);

		if ((request()->isMethod('POST')) && !$search_flag && $this->validate()) {
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

            $this->session->set('is_redirect_agreement', true);

			if ($this->customer->isPartner()) {
                setcookie('login_flag', 1);
                setcookie('is_partner', 1);
                return $this->response->redirectTo($this->url->to('customerpartner/seller_center/index'));
            }

            if ($this->request->input->has('redirect')) {
                return $this->response->redirectTo($this->url->to($this->request->input->get('redirect')));
            }

            setcookie('login_flag', 1);
            setcookie('is_partner', 0);
            if($this->customer->getGroupId() == 27){
                return $this->response->redirectTo($this->url->to('account/order_for_giga'));
            }else{
                return $this->response->redirectTo(str_replace('&amp;', '&', $this->request->input->get('logged_redirect_url')));
            }
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_login'),
			'href' => $this->url->link('account/login', '', true)
		);

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = session('error');

			$this->session->remove('error');
		} elseif (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['action'] = $this->url->link('account/login', '', true);
		$data['register'] = $this->url->link('account/register_apply', '', true);

		// Added strpos check to pass McAfee PCI compliance test (http://forum.opencart.com/viewtopic.php?f=10&t=12043&p=151494#p151295)
		if (isset($this->request->post['redirect']) && (strpos($this->request->post['redirect'], $this->config->get('config_url')) !== false || strpos($this->request->post['redirect'], $this->config->get('config_ssl')) !== false)) {
			$data['redirect'] = $this->request->post['redirect'];
		} elseif ($this->session->has('redirect')) {
			$data['redirect'] = $this->session->get('redirect');
			$this->session->remove('redirect');
        } elseif ($this->request->query->has('redirect')) {
            // 消息中心发送邮件跳转到登陆
            $data['redirect'] = $this->request->query->get('redirect');
        } else {
            $data['redirect'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = session('success');

            $this->session->remove('success');
        } else {
			$data['success'] = '';
		}

		if (isset($this->request->post['email'])) {
			$data['email'] = trim($this->request->post['email']);
		} else {
			$data['email'] = '';
		}

		if (isset($this->request->post['password'])) {
			$data['password'] = $this->request->post['password'];
		} else {
			$data['password'] = '';
		}

        $this->load->model('design/banner');
        $loginBanners = $this->model_design_banner->getBanner(12);
        $loginBanner = [
            'link' => '',
            'image' => '',
        ];
        if (isset($loginBanners[0])) {
            $this->load->model('tool/image');
            $loginBanner = [
                'link' => $this->url->link($loginBanners[0]['link']),
                'image' => $this->model_tool_image->resize($loginBanners[0]['image'],500,470)
            ];
        }
        $data['login_banner'] = $loginBanner;

		$data['logged_redirect_url'] = $this->request->input->get('logged_redirect_url', '/');
		$parseUrl = parse_url($this->request->serverBag->get('HTTP_REFERER'));
		if (
            isset($parseUrl['query'])
            && !in_array($parseUrl['query'], ['route=account/login', 'route=account/logout', 'route=account/register_apply']) // 登录注册页
            && strpos($parseUrl['query'], 'route=account/password') !== 0 // 修改密码重置密码页
        ) {
		    $data['logged_redirect_url'] = $this->request->serverBag->get('HTTP_REFERER', '/');
        }



		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');

		return $this->render('account/login', $data, 'login');
	}

	protected function validate() {
		// Check how many login attempts have been made.
        $email = trim($this->request->post('email', ''));
		$login_info = $this->model_account_customer->getLoginAttempts($email);

		if ($login_info && ($login_info['total'] >= $this->config->get('config_login_attempts')) && strtotime('-1 hour') < strtotime($login_info['date_modified'])) {
			$this->error['warning'] = $this->language->get('error_attempts');
		}

		// Check if customer has been approved.
		$customer_info = $this->model_account_customer->getCustomerByEmail($email);

		if ($customer_info && !$customer_info['status']) {
			$this->error['warning'] = $this->language->get('error_approved');
		}

		if (!$this->error) {
			if (!$this->customer->login($email, $this->request->post('password'))) {
				$this->error['warning'] = $this->language->get('error_login');

				$this->model_account_customer->addLoginAttempt($email);
			} else {
				$this->model_account_customer->deleteLoginAttempts($email);
			}
		}

		return !$this->error;
	}

    /**
     * @param string $sellerToken
     * @param string $accountManagerToken
     * @throws Exception
     */
    private function accountManagerToSellerLogin($sellerToken, $accountManagerToken)
    {
        $seller = $this->model_account_customer->getCustomerByToken($sellerToken);
        $accountManager = $this->model_account_customer->getCustomerByToken($accountManagerToken);
        if ($accountManager) {
            $this->model_account_customer->clearTokenByCustomerId(intval($accountManager['customer_id']));
        } else {
            $this->model_account_customer->clearToken();
        }

        if (empty($seller) || empty($accountManager)) {
            $this->response->redirect($this->url->link('account/login'));
        }

        $this->load->model('account/customerpartner/account_authorization');
        $authorized = $this->model_account_customerpartner_account_authorization->authorizedBySellerIdAccountManagerId($seller['customer_id'], $accountManager['customer_id']);
        if (empty($authorized)) {
            $this->response->redirect($this->url->link('account/login'));
        }

        $this->redirectClear();

        if (!$this->customer->login($seller['email'], '', true)) {
            $this->response->redirect($this->url->link('account/login'));
        }
        if (!$this->customer->isPartner()) {
            $this->response->redirect($this->url->link('account/login'));
        }

        $this->load->model('account/address');
        switch ($this->config->get('config_tax_customer')) {
            case 'payment':
                session()->set('payment_address', $this->model_account_address->getAddress($this->customer->getAddressId()));
                break;
            case 'shipping':
                session()->set('shipping_address', $this->model_account_address->getAddress($this->customer->getAddressId()));
        }

        setcookie('login_flag', 1);
        setcookie('is_partner', 1);

        $permissions = json_decode($authorized['permissions'], true);
        $authorizedMenuIds = array_keys(array_column($permissions, 'is_auth', 'id'), true);

        $this->load->model('account/customerpartner/column_left');

        $menus = $this->model_account_customerpartner_column_left->menus();
        $this->authorizedRoute($menus, $authorizedMenuIds, $route);

        session()->set('seller_authorized_menu_ids', $authorizedMenuIds);

        session()->set('marketplace_separate_view', 'separate');

        $this->response->redirect($route . '&view=separate');
    }

    /**
     * @param array $menus
     * @param array $authorizedPermissions
     * @param string $route
     */
    private function authorizedRoute($menus = [], $authorizedPermissions = [], &$route = '')
    {
        foreach ($menus as $menu) {
            if (!empty($route)) {
                break;
            }
            if (in_array($menu['id'], $authorizedPermissions) && empty($menu['children'])) {
                $route = $menu['href'];
                break;
            }
            if (is_array($menu['children'])) {
                $this->authorizedRoute($menu['children'], $authorizedPermissions, $route);
            }
        }
    }

    /**
     *  跳转登陆清除信息
     */
    private function redirectClear()
    {
        $this->customer->logout();
        $this->cart->clear();

        $this->session->remove('order_id');
        $this->session->remove('payment_address');
        $this->session->remove('payment_method');
        $this->session->remove('payment_methods');
        $this->session->remove('shipping_address');
        $this->session->remove('shipping_method');
        $this->session->remove('shipping_methods');
        $this->session->remove('comment');
        $this->session->remove('coupon');
        $this->session->remove('reward');
        $this->session->remove('voucher');
        $this->session->remove('vouchers');
        $this->session->remove('marketplace_separate_view');
    }
}
