<?php

/**
 * Class ModelCustomerpartnerMail
 */
class ModelCustomerpartnerMail extends Model {

	private $data;

	public function getMailData($id){
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_mail WHERE id='".(int)$id."'");
		return $query->row;
	}

	public function deleteentry($id){
		$this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_mail WHERE id='".(int)$id."'");
	}

	public function gettotal(){

		$sql ="SELECT * FROM " . DB_PREFIX . "customerpartner_mail WHERE 1 ";

		$result = $this->db->query($sql);

		return $result->rows;
	}

	public function viewtotal($data){

		$sql ="SELECT * FROM " . DB_PREFIX . "customerpartner_mail WHERE 1 ";

		if (!empty($data['filter_id'])) {
			$sql .= " AND id = '" . (float)$this->db->escape($data['filter_id']) . "'";
		}

		if (!empty($data['filter_name'])) {
			$sql .= " AND LCASE(name) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
		}

		if (!empty($data['filter_subject'])) {
			$sql .= " AND LCASE(subject) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_subject'])) . "%'";
		}

		if (!empty($data['filter_message'])) {
			$sql .= " AND LCASE(message) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_message'])) . "%'";
		}

		$sort_data = array(
			'id',
			'name',
			'subject',
			'message',
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY id";
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

		$sql ="SELECT * FROM " . DB_PREFIX . "customerpartner_mail WHERE 1 ";

		if (!empty($data['filter_id'])) {
			$sql .= " AND id = '" . (float)$this->db->escape($data['filter_id']) . "'";
		}

		if (!empty($data['filter_name'])) {
			$sql .= " AND LCASE(name) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
		}

		if (!empty($data['filter_subject'])) {
			$sql .= " AND LCASE(subject) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_subject'])) . "%'";
		}

		if (!empty($data['filter_message'])) {
			$sql .= " AND LCASE(message) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_message'])) . "%'";
		}

		$result = $this->db->query($sql);

		return count($result->rows);
	}

	public function addMail($data) {

		if($data['mail_id'])
			$this->db->query("UPDATE " . DB_PREFIX . "customerpartner_mail SET name = '" . $this->db->escape($data['name']) . "', message = '" . $this->db->escape($data['message']) . "', subject = '" . $this->db->escape($data['subject']) . "' WHERE id='".(int)$data['mail_id']."'");
		else
			$this->db->query("INSERT INTO " . DB_PREFIX . "customerpartner_mail SET name = '" . $this->db->escape($data['name']) . "', message = '" . $this->db->escape($data['message']) . "', subject = '" . $this->db->escape($data['subject']) . "'");

	}

	public function getSeller($id){
		return $this->db->query("SELECT * FROM " . DB_PREFIX . "customer c LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer c2c ON (c.customer_id = c2c.customer_id) WHERE c.customer_id = '".(int)$id."'")->row;
	}

	public function mail($data, $value_index = array()){

		$mail_id = $data['mail_id'];
		$mail_from = $data['mail_from'];
		$mail_to = $data['mail_to'];

		if(isset($data['seller_id']) AND $data['seller_id']){
			$seller_info = $this->getSeller($data['seller_id']);
		} else {
			$seller_info['firstname'] = '';
			$seller_info['lastname'] = '';
		}

		$this->load->model('customerpartner/partner');

		$mail_details = $this->getMailData($mail_id);

		if($mail_details){

			$this->data['store_name'] = $this->config->get('config_name');
			$this->data['store_url'] = HTTP_SERVER;
			$this->data['logo'] = HTTP_SERVER.'image/' . $this->config->get('config_logo');

			$find = array(
				'{order}',
				'{seller_message}',
				'{customer_message}',
				'{commission}',
				'{product_name}',
				'{transaction_message}',
				'{transaction_amount}',
				'{seller_name}',
				'{seller_id}',
				'{config_logo}',
				'{config_icon}',
				'{config_currency}',
				'{config_image}',
				'{config_name}',
				'{config_owner}',
				'{config_address}',
				'{config_geocode}',
				'{config_email}',
				'{config_telephone}',
				);

			$replace = array(
				'order' => '',
				'seller_message' => '',
				'customer_message' => '',
				'commission' => '',
				'product_name' => '',
				'transaction_message' => '',
				'transaction_amount' => '',
				'seller_name' => $seller_info['firstname'].' '.$seller_info['lastname'],
				'seller_id' => '',
				'config_logo' => '<a href="'.HTTP_SERVER.'" title="'.$this->data['store_name'].'"><img src="'.HTTP_SERVER.'image/' . $this->config->get('config_logo').'" alt="'.$this->data['store_name'].'" /></a>',
				'config_icon' => '<img src="'.HTTP_SERVER.'image/' . $this->config->get('config_icon').'">',
				'config_currency' => $this->config->get('config_currency'),
				'config_image' => '<img src="'.HTTP_SERVER.'image/' . $this->config->get('config_image').'">',
				'config_name' => $this->config->get('config_name'),
				'config_owner' => $this->config->get('config_owner'),
				'config_address' => $this->config->get('config_address'),
				'config_geocode' => $this->config->get('config_geocode'),
				'config_email' => $this->config->get('config_email'),
				'config_telephone' => $this->config->get('config_telephone'),
			);

			$replace = array_merge($replace,$value_index);

			$mail_details['message'] = trim(str_replace($find, $replace, $mail_details['message']));
			$mail_details['message'] = html_entity_decode($mail_details['message'],ENT_QUOTES,"UTF-8");
			$this->data['subject'] = $mail_details['subject'];
			$this->data['message'] = $mail_details['message'];

			$html = $this->load->view('customerpartner/mail', $this->data);

			if (preg_match('/^[^\@]+@.*\.[a-z]{2,6}$/i', $mail_to) AND preg_match('/^[^\@]+@.*\.[a-z]{2,6}$/i', $mail_from) ) {


				if(VERSION == '2.0.0.0' || VERSION == '2.0.1.0' || VERSION == '2.0.1.1'  ) {
					/*Old mail code*/
					$mail = new Mail($this->config->get('config_mail'));
					$mail->setTo($mail_to);
					$mail->setFrom($mail_from);
					$mail->setSender($this->data['store_name']);
					$mail->setSubject($mail_details['subject']);
					$mail->setHtml($html);
					$mail->setText(strip_tags($html));
					$mail->send();
				} else {
					$mail = new Mail();
					$mail->protocol = $this->config->get('config_mail_protocol');
					$mail->parameter = $this->config->get('config_mail_parameter');
					$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
					$mail->smtp_username = $this->config->get('config_mail_smtp_username');
					$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
					$mail->smtp_port = $this->config->get('config_mail_smtp_port');
					$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
					$mail->setTo($mail_to);
					$mail->setFrom($mail_from);
					$mail->setSender($this->data['store_name']);
					$mail->setSubject($mail_details['subject']);
					$mail->setHtml($html);
					$mail->setText(strip_tags($html));
					$mail->send();
				}

			}

		}
	}

}
?>
