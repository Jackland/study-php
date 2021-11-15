<?php
class ControllerAccountLogout extends Controller {
	public function index() {

		if ($this->customer->isLogged()) {
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
			$this->session->remove('country');
			$this->session->remove('is_redirect_agreement');
			$this->session->remove('is_top_cwf_notice');
			// 删除cookie
            setcookie('country', NULL);
            //删除登陆信息的flag
            setcookie('is_exist', '');
            setcookie('login_flag', 0);
            setcookie('is_partner', 1);
            setcookie('is_notifications', '');
            setcookie('current_message_time', '');
            setcookie('can_show', '');

            $to_login =  $this->request->get('to_login');
            if ($to_login == 1){
               return  $this->response->redirectTo($this->url->link('account/login', '', true));
            }

			$this->response->redirect($this->url->link('account/logout', '', true));
		}

		$this->load->language('account/logout');

		$this->document->setTitle($this->language->get('heading_title'));

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
			'text' => $this->language->get('text_logout'),
			'href' => $this->url->link('account/logout', '', true)
		);

		$data['continue'] = $this->url->link('common/home');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/success', $data));
	}

}
