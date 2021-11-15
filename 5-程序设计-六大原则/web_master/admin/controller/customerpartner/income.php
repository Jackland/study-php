<?php

/**
 * Class ControllerCustomerpartnerincome
 *
 * @property ModelCustomerpartnerIncome $model_customerpartner_income
 * @property ModelCustomerpartnerPartner $model_customerpartner_partner
 * @property ModelCustomerpartnerTransaction $model_customerpartner_transaction
 */
class ControllerCustomerpartnerincome extends Controller {

	function index() {
		// loading language
		$this->load->language("customerpartner/income");
		// loading model
		$this->load->model("customerpartner/income");
		$this->load->model('customerpartner/partner');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['user_token'] = session('user_token');

		$url = '';
		if(isset($this->request->get['seller_name'])){
			$data['seller_name'] = $this->request->get['seller_name'];
			$url .= '&seller_name='.$this->request->get['seller_name'];
		} else {
			$data['seller_name'] = '';
		}
		if(isset($this->request->get['commission_from'])){
			$data['commission_from'] = $this->request->get['commission_from'];
			$url .= '&commission_from='.$this->request->get['commission_from'];
		} else {
			$data['commission_from'] = '';
		}
		if(isset($this->request->get['commission_to'])){
			$data['commission_to'] = $this->request->get['commission_to'];
			$url .= '&commission_to='.$this->request->get['commission_to'];
		} else {
			$data['commission_to'] = '';
		}
		if(isset($this->request->get['total_amount_from'])){
			$data['total_amount_from'] = $this->request->get['total_amount_from'];
			$url .= '&total_amount_from='.$this->request->get['total_amount_from'];
		} else {
			$data['total_amount_from'] = '';
		}
		if(isset($this->request->get['total_amount_to'])){
			$data['total_amount_to'] = $this->request->get['total_amount_to'];
			$url .= '&total_amount_to='.$this->request->get['total_amount_to'];
		} else {
			$data['total_amount_to'] = '';
		}
		if(isset($this->request->get['seller_amount_from'])){
			$data['seller_amount_from'] = $this->request->get['seller_amount_from'];
			$url .= '&seller_amount_from='.$this->request->get['seller_amount_from'];
		} else {
			$data['seller_amount_from'] = '';
		}
		if(isset($this->request->get['seller_amount_to'])){
			$data['seller_amount_to'] = $this->request->get['seller_amount_to'];
			$url .= '&seller_amount_to='.$this->request->get['seller_amount_to'];
		} else {
			$data['seller_amount_to'] = '';
		}
		if(isset($this->request->get['admin_amount_from'])){
			$data['admin_amount_from'] = $this->request->get['admin_amount_from'];
			$url .= '&admin_amount_from='.$this->request->get['admin_amount_from'];
		} else {
			$data['admin_amount_from'] = '';
		}
		if(isset($this->request->get['admin_amount_to'])){
			$data['admin_amount_to'] = $this->request->get['admin_amount_to'];
			$url .= '&admin_amount_to='.$this->request->get['admin_amount_to'];
		} else {
			$data['admin_amount_to'] = '';
		}
		if(isset($this->request->get['paid_to_seller_from'])){
			$data['paid_to_seller_from'] = $this->request->get['paid_to_seller_from'];
			$url .= '&paid_to_seller_from='.$this->request->get['paid_to_seller_from'];
		} else {
			$data['paid_to_seller_from'] = '';
		}
		if(isset($this->request->get['paid_to_seller_to'])){
			$data['paid_to_seller_to'] = $this->request->get['paid_to_seller_to'];
			$url .= '&paid_to_seller_to='.$this->request->get['paid_to_seller_to'];
		} else {
			$data['paid_to_seller_to'] = '';
		}
		if(isset($this->request->get['rem_amount_from'])){
			$data['rem_amount_from'] = $this->request->get['rem_amount_from'];
			$url .= '&rem_amount_from='.$this->request->get['rem_amount_from'];
		} else {
			$data['rem_amount_from'] = '';
		}
		if(isset($this->request->get['rem_amount_to'])){
			$data['rem_amount_to'] = $this->request->get['rem_amount_to'];
			$url .= '&rem_amount_to='.$this->request->get['rem_amount_to'];
		} else {
			$data['rem_amount_to'] = '';
		}

		if(isset($this->request->get['date_added_from'])){
			$data['date_added_from'] = $this->request->get['date_added_from'];
			$url .= '&date_added_from='.$this->request->get['date_added_from'];
		} else {
			$data['date_added_from'] = '';
		}
		if(isset($this->request->get['date_added_to'])){
			$data['date_added_to'] = $this->request->get['date_added_to'];
			$url .= '&date_added_to='.$this->request->get['date_added_to'];
		} else {
			$data['date_added_to'] = '';
		}

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		if (isset($this->request->get['order'])) {
			$data['order'] = $this->request->get['order'];
		} else {
			$data['order'] = 'c.firstname';
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
			if($sort == "asc") {
				$sort = "desc";
			} else {
				$sort = "asc";
			}
			$data['sort'] = $sort;
		} else {
			$sort = "asc";
			$data['sort'] = $sort;
		}

		$filter_data = array(
			'seller_name' => $data['seller_name'],
			'commission_from' => $data['commission_from'],
			'commission_to' => $data['commission_to'],
			'total_amount_from' => $data['total_amount_from'],
			'total_amount_to' => $data['total_amount_to'],
			'seller_amount_from' => $data['seller_amount_from'],
			'seller_amount_to' => $data['seller_amount_to'],
			'admin_amount_from' => $data['admin_amount_from'],
			'admin_amount_to' => $data['admin_amount_to'],
			'paid_to_seller_from' => $data['paid_to_seller_from'],
			'paid_to_seller_to' => $data['paid_to_seller_to'],
			'rem_amount_from' => $data['rem_amount_from'],
			'rem_amount_to' => $data['rem_amount_to'],
			'date_added_from' => $data['date_added_from'],
			'date_added_to' => $data['date_added_to'],
			'start'           => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'           => $this->config->get('config_limit_admin'),
			'sort' => $sort,
			'order' => $data['order'],
		);

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('customerpartner/income', 'user_token=' . session('user_token') . $url, true)
		);

		$data['masspayout'] = $this->url->link('customerpartner/income/masspayout', 'user_token=' . session('user_token') , true);

		$data['remove_url'] = $this->url->link('customerpartner/income', 'user_token=' . session('user_token') , true);

		$data['income_details'] = array();
		$data['grand_total_admin'] = 0;
		$data['grand_total_paid'] = 0;
		$data['grand_total_rem'] = 0;
		$data['grand_total_seller'] = 0;
		$data['grand_total'] = 0;
		$data['grand_unpaid_listing_commission'] = 0;
		$total_row = 0;
		$this->model_customerpartner_income->getDetails();
		$sellers = $this->model_customerpartner_income->getSellerList($filter_data);
		foreach ($sellers as $key => $seller) {
			$partner_amount = $this->model_customerpartner_partner->getPartnerTotal($seller['customer_id'],$filter_data);
			if(!$partner_amount){
				continue;
			}
			if ($this->config->get('wk_seller_group_status')) {
				$relist_commission = $this->model_customerpartner_income->getPaidListingCommission($seller['customer_id']);
				if (!$relist_commission) {
					$relist_commission['commission_paid'] = 0.00;
				}
				$partner_amount['paid'] = $partner_amount['paid'] + $relist_commission['commission_paid'];
				$unpaid_commission = $this->model_customerpartner_income->getunPaidListingCommission($seller['customer_id']);
				if (!$unpaid_commission) {
				  $unpaid_commission['commission_amount'] = 0.00;
				}
			}
			$total = $partner_amount['total'];
			$admin = $partner_amount['admin'];
			$selleramount = $partner_amount['customer'];
			$paid = $partner_amount['paid'];
			$rem = round($total-($paid+$admin),2);
			$button_status = true;
			if($rem){
				$button_status = false;
			}
			$data['income_details'][] = array(
				'seller_id' => $seller['customer_id'],
				'firstname' => $seller['firstname'],
				'seller_name' => $seller['firstname']." ".$seller['lastname'],
				'dashborad_url' => $this->url->link("customerpartner/partner/update","user_token=".session('user_token')."&customer_id=".$seller['customer_id']."&amount=".$rem, true),
				'commission' => $seller['commission']."%",
				'total' => $this->currency->format($total,$this->config->get('config_currency')),
				'seller_total' => $this->currency->format($selleramount,$this->config->get('config_currency')),
				'admin_total' => $this->currency->format($admin,$this->config->get('config_currency')),
				'paid_total' => $this->currency->format($paid,$this->config->get('config_currency')),
				'amount_to_pay' => $this->currency->format($rem,$this->config->get('config_currency')),
				'pay_link' => $this->url->link("customerpartner/transaction/addtransaction","user_token=".session('user_token')."&seller_id=".$seller['customer_id'], true),
				'pay' => $this->language->get('button_pay')." ".$this->currency->format($rem,$this->config->get('config_currency')),
				'button_status' => $button_status,
				'unpaid_listing_commission' => isset($unpaid_commission['commission_amount']) ? $unpaid_commission['commission_amount'] : 0.00,
			);

			$data['grand_total'] = $data['grand_total'] + $total;
			$data['grand_total_seller'] = $data['grand_total_seller'] + $selleramount;
			$data['grand_total_paid'] = $data['grand_total_paid'] + $paid;
			$data['grand_total_admin'] = $data['grand_total_admin'] + $admin;
			$data['grand_total_rem'] = $data['grand_total_rem'] + $rem;
			$data['grand_unpaid_listing_commission'] = $data['grand_unpaid_listing_commission'] + isset($unpaid_commission['commission_amount']) ? $unpaid_commission['commission_amount'] : 0.00;

			$total_row++;
		}

		if($data['order'] == 'customer' || $data['order'] == 'admin' || $data['order'] == 'total') {
			if($sort == 'asc') {
				sort($data['income_details']);
			} else {
				rsort($data['income_details']);
			}
		}

		$data['grand_total'] = $this->currency->format($data['grand_total'],$this->config->get('config_currency'));
		$data['grand_total_seller'] = $this->currency->format($data['grand_total_seller'],$this->config->get('config_currency'));
		$data['grand_total_paid'] = $this->currency->format($data['grand_total_paid'],$this->config->get('config_currency'));
		$data['grand_total_admin'] = $this->currency->format($data['grand_total_admin'],$this->config->get('config_currency'));
		$data['grand_total_rem'] = $this->currency->format($data['grand_total_rem'],$this->config->get('config_currency'));

		$pagination = new Pagination();
		$pagination->total = $total_row;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('customerpartner/income', 'user_token=' . session('user_token') . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($total_row) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($total_row - $this->config->get('config_limit_admin'))) ? $total_row : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $total_row, ceil($total_row / $this->config->get('config_limit_admin')));


		$data['seller_name_url'] = $this->url->link("customerpartner/income","user_token=".session('user_token') . $url ."&order=c.firstname&sort=".$sort, true);

		$data['seller_commission_url'] = $this->url->link("customerpartner/income","user_token=".session('user_token') . $url ."&order=cp2c.commission&sort=".$sort, true);

		$data['total_url'] = $this->url->link("customerpartner/income","user_token=".session('user_token') . $url ."&order=total&sort=".$sort, true);

		$data['customer_url'] = $this->url->link("customerpartner/income","user_token=".session('user_token') . $url ."&order=customer&sort=".$sort, true);

		$data['admin_url'] = $this->url->link("customerpartner/income","user_token=".session('user_token') . $url ."&order=admin&sort=".$sort, true);

		$data['error_warning'] = '';

		if (isset($this->session->data['error_warning']) && session('error_warning')) {

			$data['error_warning'] = session('error_warning');

			$this->session->remove('error_warning');
		}

		$data['header'] = $this->load->Controller('common/header');
		$data['footer'] = $this->load->Controller('common/footer');
		$data['column_left'] = $this->load->Controller('common/column_left');

		$this->response->setOutput($this->load->view("customerpartner/income",$data));
	}

	public function masspayout(){

		$this->load->language("customerpartner/income");

		if (isset($this->request->post['selected']) && $this->request->post['selected']) {

			if ($this->config->get('marketplace_paypal_user') && $this->config->get('marketplace_paypal_password') && $this->config->get('marketplace_paypal_signature') && $this->config->get('marketplace_paypal_email_subject')) {

				$this->load->model('customerpartner/partner');

				$this->load->model('customerpartner/transaction');

				foreach ($this->request->post['selected'] as $seller) {

					$orders = $this->model_customerpartner_partner->getSellerOrdersListPaypal($seller);

					$amount = 0;

					if ($orders) {
						foreach ($orders as $key => $order) {
							$amount += $order['need_to_pay'];
						}
					}

					if($this->config->get('wk_seller_group_status')) {
						$commission = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seller_group_product_listing_commission` WHERE seller_id ='" . (int)$seller . "'")->row;

						if (isset($commission['commission_amount']) && $commission['commission_amount']) {
							$amount = (float)$amount - (float)$commission['commission_amount'];

							if ($amount < 0) {
								$this->db->query("UPDATE `" . DB_PREFIX . "seller_group_product_listing_commission` SET commission_amount = '" . (float)abs($amount) . "' WHERE seller_id ='" . (int)$seller . "'");
								$amount = 0.00;
							} else {
								$this->db->query("UPDATE `" . DB_PREFIX . "seller_group_product_listing_commission` SET commission_amount = 0 WHERE seller_id ='" . (int)$seller . "'");
							}
						}
					}

					$seller_detail = $this->model_customerpartner_partner->getPartner($seller);

					if (isset($seller_detail['paypalid']) && $seller_detail['paypalid'] && $amount) {

						if ($this->config->get('marketplace_paypal_mode')) {
							$curl_url = 'https://api-3t.sandbox.paypal.com/nvp';
						} else {
							$curl_url = 'https://api-3t.paypal.com/nvp';
						}

						$curl = curl_init();

						curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

						curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

						curl_setopt($curl, CURLOPT_POST, true);

						curl_setopt($curl, CURLOPT_URL, $curl_url);

						curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query(array(
						    'USER' => $this->config->get('marketplace_paypal_user'),
						    'PWD' => $this->config->get('marketplace_paypal_password'),
						    'SIGNATURE' => $this->config->get('marketplace_paypal_signature'),
						    'METHOD' => 'MassPay',
						    'VERSION' => '99',
						    'CURRENCYCODE' => $this->config->get('config_currency'),
						    'RECEIVERTYPE' => 'EmailAddress',
						    'EMAILSUBJECT' => $this->config->get('marketplace_paypal_email_subject'),
						    'L_EMAIL0' => $seller_detail['paypalid'],
						    'L_AMT0' => $amount
						)));

						$response = strtolower(explode('=',explode('&', curl_exec($curl))['2'])['1']);

						curl_close($curl);

						if ($response = 'success') {

							$data = array();

							$data['customer_id'] = $seller;


							if($this->config->get('wk_seller_group_status')) {
								$data['amount'] = $amount + $commission['commission_amount'];
								$data['details'] = 'Paypal';
							} else {
								$data['amount'] = $amount;
								$data['details'] = 'Paypal';
							}

							$data['select'] = array();

							foreach ($orders as $key => $order) {
								$data['select'][$order['order_id']][] = $order['order_product_id'];
							}

							$this->model_customerpartner_transaction->addTransaction($data);
						}
					}
				}
			} else {
				session()->set('error_warning', $this->language->get('error_invalid_data'));
			}
		} else {
			session()->set('error_warning', $this->language->get('error_select'));
		}

		$this->response->redirect($this->url->link("customerpartner/income","user_token=".session('user_token'), true));
	}
}
?>
