<?php
class ControllerExtensionModuleAccount extends Controller {
	public function index() {
		$this->load->language('extension/module/account');
        $this->load->model('account/customerpartner');
        $data['chkIsPartner'] = $this->customer->isPartner();
		$data['logged'] = $this->customer->isLogged();
		$data['register'] = $this->url->link('account/register_apply', '', true);
		$data['login'] = $this->url->link('account/login', '', true);
		$data['logout'] = $this->url->link('account/logout', '', true);
		$data['forgotten'] = $this->url->link('account/password/reset', '', true);
		$data['account'] = $this->url->link('account/account', '', true);
		$data['edit'] = $this->url->link('account/edit', '', true);
		$data['password'] = $this->url->link('account/password/change', '', true);
		$data['address'] = $this->url->link('account/address', '', true);
		$data['wishlist'] = $this->url->link('account/wishlist');
		$data['order'] = $this->url->link('account/order', '', true);
		$data['download'] = $this->url->link('account/download', '', true);
		$data['reward'] = $this->url->link('account/reward', '', true);
		$data['return'] = $this->url->link('account/return', '', true);
		$data['transaction'] = $this->url->link('account/transaction', '', true);
		$data['newsletter'] = $this->url->link('account/newsletter', '', true);
		$data['recurring'] = $this->url->link('account/recurring', '', true);
        if($this->config->get('module_wk_communication_status')) {
            $data['loggedCheck'] = $this->customer->isLogged();
            $this->load->language('account/wk_communication');
            $data['text_communication_history'] = $this->language->get('text_communication_history');
            $data['communication'] = $this->url->link('message/seller', '', true);
        }

        $data['wk_pro_quote_status'] = $this->config->get('total_wk_pro_quote_status');
        $data['product_quotes'] = $this->url->link('account/product_quotes/wk_quote_my', '', true);

		return $this->load->view('extension/module/account', $data);
	}
}
