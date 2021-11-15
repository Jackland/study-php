<?php

/**
 * Class ModelCustomerpartnerTransaction
 * @property ModelCustomerpartnerMail $model_customerpartner_mail
 * @property ModelCustomerpartnerPartner $model_customerpartner_partner
 */
class ModelCustomerpartnerTransaction extends Model {

	private $data = array();

	public function getTransactionsData($id){
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_transaction WHERE id='".(int)$id."'");
		return $query->row;
	}

	public function deleteentry($id){
		/**
		 * uncomment this code to make reverse the whole transaction
		 */
		// if ($this->config->get('wk_seller_group_status')) {
		// 	$result = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_transaction WHERE id='".(int)$id."'")->row;
		// 	if ($result) {
		// 		if (isset($result['membership_transaction_amount'])) {
		// 			$commission_fee =  $this->db->query("SELECT * FROM `" . DB_PREFIX . "seller_group_product_listing_commission` WHERE seller_id = '".(int)$result['customer_id']."'")->row;
		// 			if (!isset($commission_fee['commission_amount'])) {
		// 				$commission_fee['commission_amount'] = 0.00;
		// 				$this->db->query("INSERT INTO `" . DB_PREFIX . "seller_group_product_listing_commission` SET seller_id = '".(int)$result['customer_id']."',commission_amount = '" . (float)$commission_fee['commission_amount'] . "' ");
		// 			}
		//
		// 			$commission_fee['commission_amount'] = $commission_fee['commission_amount'] + $result['membership_transaction_amount'];
		// 			$this->db->query("UPDATE `" . DB_PREFIX . "seller_group_product_listing_commission` SET commission_amount = '" . (float)$commission_fee['commission_amount'] . "' WHERE seller_id = '".(int)$result['customer_id']."'");
		// 		}
		//
		// 		$resultPaid =  $this->db->query("SELECT * FROM " . DB_PREFIX . "seller_group_listing_commission_paid WHERE seller_id = '".(int)$result['customer_id']."'")->row;
		// 		if (!isset($resultPaid['commission_paid'])) {
		// 			$resultPaid['commission_paid'] = 0.00;
		// 			$this->db->query("INSERT INTO `" . DB_PREFIX . "seller_group_listing_commission_paid` SET seller_id = '".(int)$result['seller_id']."',commission_paid = 0.00");
		// 		}
		// 		$resultPaid['commission_paid'] = $resultPaid['commission_paid'] - $result['membership_transaction_amount'];
		// 		$this->db->query("UPDATE `" . DB_PREFIX . "seller_group_listing_commission_paid` SET commission_paid = '" . (float)$resultPaid['commission_paid'] . "' WHERE seller_id = '".(int)$result['customer_id']."'");
		// 	}
		// }
		//
		// if ($result && isset($result['order_id'])) {
		// 	$orders = explode(',',$result['order_id']);
		// 	foreach ($orders as $key => $order_id) {
		// 		$this->db->query("UPDATE `" . DB_PREFIX . "customerpartner_to_order` SET paid_status = 0 WHERE order_id = '".(int)$order_id."'");
		// 	}
		// }
		$this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_to_transaction WHERE id='".(int)$id."'");
	}

	public function viewtotal($data){

		$sql ="SELECT ct.*,cc.*,CONCAT(c.firstname, ' ', c.lastname) name FROM " . DB_PREFIX . "customerpartner_to_transaction ct LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer cc ON (ct.customer_id = cc.customer_id) LEFT JOIN " . DB_PREFIX . "customer c ON (ct.customer_id = c.customer_id) WHERE 1 ";

		if (!empty($data['filter_name'])) {
			$sql .= " AND LCASE(CONCAT(c.firstname, ' ', c.lastname)) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
		}

		if (!empty($data['filter_details'])) {
			$sql .= " AND LCASE(ct.details) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_details'])) . "%'";
		}

		if (!empty($data['filter_date'])) {
			$sql .= " AND LCASE(ct.date_added) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_id'])) {
			$sql .= " AND ct.id = '" . (float)$this->db->escape($data['filter_id']) . "'";
		}

		if (!empty($data['filter_amount'])) {
			$sql .= " AND ct.amount = '" . (float)$this->db->escape($data['filter_amount']) . "'";
		}

		$sort_data = array(
			'ct.id',
			'ct.details',
			'ct.amount',
			'ct.date_added',
			'c.firstname',

		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY ct.id";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$result=$this->db->query($sql);

		return $result->rows;
	}

	public function viewtotalentry($data){

		$sql ="SELECT ct.*,cc.*,CONCAT(c.firstname, ' ', c.lastname) name FROM " . DB_PREFIX . "customerpartner_to_transaction ct LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer cc ON (ct.customer_id = cc.customer_id) LEFT JOIN " . DB_PREFIX . "customer c ON (ct.customer_id = c.customer_id) WHERE 1 ";

		if (!empty($data['filter_name'])) {
			$sql .= " AND LCASE(CONCAT(c.firstname, ' ', c.lastname)) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
		}

		if (!empty($data['filter_details'])) {
			$sql .= " AND LCASE(ct.details) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_details'])) . "%'";
		}

		if (!empty($data['filter_date'])) {
			$sql .= " AND LCASE(ct.date_added) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_id'])) {
			$sql .= " AND ct.id = '" . (float)$this->db->escape($data['filter_id']) . "'";
		}

		if (!empty($data['filter_amount'])) {
			$sql .= " AND ct.amount = '" . (float)$this->db->escape($data['filter_amount']) . "'";
		}

		$result = $this->db->query($sql);

		return count($result->rows);
	}


	public function getPartnerAmount($partner_id){
		$total = $this->db->query("SELECT SUM(c2o.quantity) quantity,SUM(c2o.price) total,SUM(c2o.admin) admin,SUM(c2o.customer) customer FROM ".DB_PREFIX ."customerpartner_to_order c2o WHERE c2o.customer_id ='".(int)$partner_id."'")->row;

		$paid = $this->db->query("SELECT SUM(c2t.amount) total FROM ".DB_PREFIX ."customerpartner_to_transaction c2t WHERE c2t.customer_id ='".(int)$partner_id."'")->row;

		$total['paid'] = $paid['total'];

		return($total);
	}


	public function getTransactions($customer_id, $start = 0, $limit = 10) {
		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 10;
		}

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_transaction WHERE customer_id = '" . (int)$customer_id . "' ORDER BY date_added DESC LIMIT " . (int)$start . "," . (int)$limit);

		return $query->rows;
	}

	public function getTotalTransactions($customer_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total  FROM " . DB_PREFIX . "customerpartner_to_transaction WHERE customer_id = '" . (int)$customer_id . "'");

		return $query->row['total'];
	}

	public function getTransactionTotal($customer_id) {
		$query = $this->db->query("SELECT SUM(amount) AS total FROM " . DB_PREFIX . "customerpartner_to_transaction WHERE customer_id = '" . (int)$customer_id . "'");

		return $query->row['total'];
	}

	public function addTransaction($data) {

		$order_product_id = '';
		$order_id = '';
		foreach ($data['select'] as $key => $value) {
			$order_id .= $key.",";
			foreach ($value as $key => $detail) {
				$order_product_id .= $detail.",";
				$this->db->query("UPDATE ".DB_PREFIX."customerpartner_to_order SET paid_status = 1 WHERE order_product_id = '".$detail."' ");
			}
		}

		if($this->config->get('wk_seller_group_status')) {

			$commission = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seller_group_product_listing_commission` WHERE seller_id ='" . (int)$data['customer_id'] . "'")->row;

			if (isset($commission['commission_amount']) && $commission['commission_amount']) {

				$data['amount'] = (float)$data['amount'] - (float)$commission['commission_amount'];

				$previous_commission = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seller_group_listing_commission_paid` WHERE seller_id ='" . (int)$data['customer_id'] . "'")->row;

				if (!isset($previous_commission['commission_paid'])) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "seller_group_listing_commission_paid` SET seller_id ='" . (int)$data['customer_id'] . "', commission_paid = 0.00");
					$previous_commission['commission_paid'] = 0.00;
				}

				if ($data['amount'] < 0) {
					$previous_commission['commission_paid'] += (float)$commission['commission_amount'] + (float)$data['amount'];
					$mp_transac = $previous_commission['commission_paid'];
					$this->db->query("UPDATE `" . DB_PREFIX . "seller_group_product_listing_commission` SET commission_amount = '" . (float)abs($data['amount']) . "' WHERE seller_id ='" . (int)$data['customer_id'] . "'");

					$data['details'] .= ' . Membership listing commission of amount ' . $this->currency->format((float)($commission['commission_amount'] + $data['amount']),$this->config->get('config_currency')) . ' deducted from your income.';
					$data['amount'] = 0.00;

				} else {
					$previous_commission['commission_paid'] += (float)$commission['commission_amount'];
					$mp_transac = (float)$commission['commission_amount'];
					$this->db->query("UPDATE `" . DB_PREFIX . "seller_group_product_listing_commission` SET commission_amount = 0 WHERE seller_id ='" . (int)$data['customer_id'] . "'");

					$data['details'] .= ' . Membership listing commission of amount ' . $this->currency->format((float)($commission['commission_amount']),$this->config->get('config_currency')) . ' deducted from your income.';

				}

				$this->db->query("UPDATE `" . DB_PREFIX . "seller_group_listing_commission_paid` SET commission_paid = '" . (float)$previous_commission['commission_paid'] . "' WHERE seller_id ='" . (int)$data['customer_id'] . "'");

			} else {

				$mp_transac = 0.00;

			}
		}

		$this->db->query("INSERT INTO " . DB_PREFIX . "customerpartner_to_transaction SET customer_id = '" . (int)$data['customer_id'] . "', order_id = '".trim($order_id,',')."', order_product_id = '".trim($order_product_id,',')."', details = '" . $this->db->escape($data['details']) . "', amount = '" . (float)$data['amount'] . "', `text` = '".$this->currency->format($data['amount'],$this->config->get('config_currency'))."', date_added = NOW()");

		if($this->config->get('wk_seller_group_status')) {
			$last_id = $this->db->getLastId();
			$this->db->query("UPDATE `" . DB_PREFIX . "customerpartner_to_transaction` SET membership_transaction_amount = '" . (float)$mp_transac . "' WHERE id=" . (int)$last_id . "");
		}

		$this->load->model('customerpartner/partner');

		$seller_info = $this->model_customerpartner_partner->getPartnerCustomerInfo($data['customer_id']);

		$mail_data = array('customer_id' => $data['customer_id'],
			'message' => $data['details'],
			'amount' => $data['amount'],
			'mail_id' => $this->config->get('marketplace_mail_transaction'),
			'mail_from' => $this->config->get('marketplace_adminmail'),
			'mail_to' => $seller_info['email'],
		);

		$value_index = array(
			'transaction_message' => $data['details'],
			'transaction_amount' => $data['amount'],
		);

		$this->load->model('customerpartner/mail');

		$this->model_customerpartner_mail->mail($mail_data,$value_index);
	}

	public function addPartnerTransaction($customer_id, $description = '', $amount = '') {

		$this->load->model('customerpartner/partner');
		$customer_info = $this->model_customerpartner_partner->getCustomer($customer_id);

		if ($customer_info) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "customerpartner_to_transaction SET customer_id = '" . (int)$customer_id . "', details = '" . $this->db->escape($description) . "', amount = '" . (float)$amount . "', `text` = '".$this->currency->format($amount,$this->config->get('config_currency'))."', date_added = NOW()");

			$data = array('customer_id' => $customer_id,
			  'message' => $description,
			  'amount' => $amount,
				'mail_id' => $this->config->get('marketplace_mail_transaction'),
				'mail_from' => $this->config->get('marketplace_adminmail'),
				'mail_to' => $customer_info['email'],
			);

			$value_index = array(
				'transaction_message' => $description,
				'transaction_amount' => $amount,
			);

			$this->load->model('customerpartner/mail');

		  $this->model_customerpartner_mail->mail($data,$value_index);
	  }
	}

	public function getSellerOrderProductDetails($order_product_id){

		$query = $this->db->query("SELECT o.order_id, o.order_status_id, os.name as order_status_name, c2o.paid_status, c2o.customer FROM " . DB_PREFIX . "customerpartner_to_order c2o LEFT JOIN `" . DB_PREFIX . "order` o ON (c2o.order_id = o.order_id) LEFT JOIN " . DB_PREFIX . "order_status os ON (o.order_status_id = os.order_status_id) WHERE c2o.order_product_id = '" . (int)$order_product_id . "'")->row;

		return $query;

	}

	public function getSellerTransactionOrdersList($seller_id,$order_ids = 0){

		$sql = "SELECT DISTINCT op.order_id,c2o.price,c2o.quantity,c2o.paid_status,c2o.commission_applied,c2o.admin as admin_amount,c2o.customer as need_to_pay,o.date_added, CONCAT(o.firstname ,' ',o.lastname) name ,os.name orderstatus,op.name, (SELECT group_concat( concat( value) SEPARATOR ', ') FROM ".DB_PREFIX."order_option oo WHERE oo.order_product_id=c2o.order_product_id ) as value  FROM " . DB_PREFIX ."order_status os LEFT JOIN `".DB_PREFIX ."order` o ON (os.order_status_id = o.order_status_id) LEFT JOIN ".DB_PREFIX ."customerpartner_to_order c2o ON (o.order_id = c2o.order_id) LEFT JOIN ".DB_PREFIX."order_product op ON op.order_product_id=c2o.order_product_id WHERE c2o.customer_id = '".(int)$seller_id."' AND os.language_id = '".$this->config->get('config_language_id')."' ";

		if ($order_ids) {
			$sql .= " AND o.order_id IN (".$order_ids.")";
		}

		$result = $this->db->query($sql);
		return($result->rows);
	}

}
?>
