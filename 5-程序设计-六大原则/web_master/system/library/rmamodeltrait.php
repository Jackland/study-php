<?php
/**
 * Webkul Software.
 * @category  Webkul
 * @author    Webkul
 * @copyright Copyright (c) 2010-2016 Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 */

trait RmaModelTrait {

  public function getrmaorders($data=array(), $customer_id = 0){
    $customer_id = (int)$customer_id;
		$query = '';

		if(!$customer_id && isset($data['email']))
			$query .= "AND email = '".$this->db->escape($data['email'])."'";

		if((int)$this->config->get('wk_rma_system_time'))
			$query .= " AND o.date_added >= DATE_SUB(CURDATE(), INTERVAL '".(int)$this->config->get('wk_rma_system_time')."' DAY) ";

		if ($this->config->get('wk_rma_system_orders')) {
			$sql = "SELECT o.order_id,o.total,SUM(op.quantity) as quantity FROM `" . DB_PREFIX . "order` o LEFT JOIN `".DB_PREFIX."order_product` op ON (o.order_id = op.order_id) WHERE o.customer_id = '".$customer_id."' $query AND o.order_status_id IN (".implode(',',$this->config->get('wk_rma_system_orders')).")";
		} else {
			return array();
		}

    $sql .= " GROUP BY o.order_id ORDER BY o.order_id DESC";

		$results = $this->db->query($sql)->rows;

    $order = array(
      '0' => 0
    );

    foreach ($results as $result) {
      $order[] = $result['order_id'];
    }

    $rma_array = $this->db->query("SELECT SUM(wrp.quantity) as quantity,order_id FROM `" . DB_PREFIX . "wk_rma_order` wro LEFT JOIN `" . DB_PREFIX . "wk_rma_product` wrp ON (wrp.rma_id = wro.id) WHERE admin_return <> 1 AND wro.order_id IN (" . implode(',',$order) . ") GROUP BY wro.order_id")->rows;

    foreach ($rma_array as $value) {
      foreach ($results as $key => $result) {
        if (!$result['quantity']) {
          unset($results[$key]);
          continue;
        }
        if ($result['order_id'] == $value['order_id']) {
          $results[$key]['quantity'] = $result['quantity'] - $value['quantity'];
          if ($results[$key]['quantity'] < 1) {
              unset($results[$key]);
          }
        }
      }
    }
		return $results;
	}

  public function defaultRmaStatus(){
    $result = $this->db->query("SELECT * FROM `" . DB_PREFIX . "wk_rma_status` WHERE `default` = 'admin'")->row;
    if ($result) {
      return $result['status_id'];
    }
    return false;
  }

  public function solveRmaStatus(){
    $result = $this->db->query("SELECT * FROM `" . DB_PREFIX . "wk_rma_status` WHERE `default` = 'solve'")->row;
    if ($result) {
      return $result['status_id'];
    }
    return false;
  }

  public function cancelRmaStatus(){
    $result = $this->db->query("SELECT * FROM `" . DB_PREFIX . "wk_rma_status` WHERE `default` = 'cancel'")->row;
    if ($result) {
      return $result['status_id'];
    }
    return false;
  }

  public function getSellerFromOrder($order_id,$order_product_id) {
    $result = $this->db->query("SELECT customer_id FROM `" . DB_PREFIX . "customerpartner_to_order` WHERE `order_id` = '" . (int)$order_id . "' AND `order_product_id` = '" . (int)$order_product_id . "'")->row;
    if ($result) {
      return $result['customer_id'];
    }
    return 0;
  }

  public function insertOrderRma($data,$img_folder,$customer_id = 0){

		$getDefaultStatus = $this->getDefaultStatus();

    $all_seller = array();
    foreach($data['selected'] as $key => $product) {
      $getSeller        = $this->getSellerFromOrder($data['order'],$data['product'][$key]);
      $all_seller[$getSeller][$key] = $product;
    }

    foreach ($all_seller as $seller_id => $product_list) {
		    $this->db->query("INSERT INTO `" . DB_PREFIX . "wk_rma_order` SET `seller_id` = '" . (int)$seller_id . "', `order_id` = '".(int)$data['order']."', `images` = '".$this->db->escape($img_folder)."',`add_info` = '".$this->db->escape(nl2br($data['info']))."', `admin_status` = '".($getDefaultStatus ? $getDefaultStatus['status_id'] : 0 )."',`rma_auth_no` = '".$this->db->escape($data['autono'])."', `date`=NOW()");

        $rma_id = $this->db->getLastId();

        if(isset($this->session->data['rma_login']))
          $email = session('rma_login');
        else {
          $email = $this->db->query("SELECT email FROM `" . DB_PREFIX . "customer` WHERE `customer_id` = '" . (int)$customer_id . "'")->row['email'];
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "wk_rma_customer` SET `rma_id` = '".$rma_id."', `customer_id` = '".(int)$customer_id."', `email` = '".$this->db->escape($email)."'");

        $data['product_list'] = array();

        foreach($product_list as $key => $product) {
             $this->db->query("INSERT INTO `" . DB_PREFIX . "wk_rma_product` SET `rma_id` = '".(int)$rma_id."',`product_id` = '".(int)$product."',`quantity` = '".(int)$data['quantity'][$key]."',`reason` = '".(int)$data['reason'][$key]."', `order_product_id` = '".(int)$data['product'][$key]."'");
             $data['product_list'][] = $data['product'][$key];
        }

        $data['rma_id'] = $rma_id;
        $data['customer_id'] = $customer_id;
        $data['customer_message'] = $data['info'] ;

        $this->mail($data,'generate_admin');
        $this->mail($data,'generate_customer');
        $this->mail($data,'generate_seller');
    }
	}

  public function getDefaultStatus(){
    $result = $this->db->query("SELECT * FROM `" . DB_PREFIX . "wk_rma_status` WHERE `default`='admin'");
    return $result->row;
  }

  public function getOrderStatus($order = 0) {
    $result = $this->db->query("SELECT order_status_id FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order . "'")->row;

    if ($result) {
        return $result['order_status_id'];
    }
    return false;
  }

  public function orderprodetails($order, $customer_id = 0){
    if ($this->config->get('wk_rma_system_orders')) {
      $results = $this->db->query("SELECT op.name,op.product_id,op.model,op.quantity,op.order_product_id,(SELECT SUM(quantity) FROM `" . DB_PREFIX . "wk_rma_product` wrp LEFT JOIN `" . DB_PREFIX . "wk_rma_order` wro ON (wrp.rma_id = wro.id) WHERE order_product_id = op.order_product_id AND wro.cancel_rma <>1 AND admin_return <> 1 GROUP BY order_product_id) as rma_quantity_sum FROM " . DB_PREFIX . "order_product op LEFT JOIN `" . DB_PREFIX . "order` o ON (o.order_id = op.order_id) WHERE o.order_id='".$this->db->escape($order)."' AND o.customer_id='" . (int)$customer_id . "' AND o.order_status_id IN (" . implode(',',$this->config->get('wk_rma_system_orders')) . ")")->rows;

      foreach ($results as $key => $result) {
        $results[$key]['quantity'] = $result['quantity'] - $result['rma_quantity_sum'];
        if ($results[$key]['quantity'] < 1) {
          unset($results[$key]);
        }
      }
      return $results;
		}
		return array();
  }

  public function getCustomer($rma = 0){
		$customer = $this->db->query("SELECT o.* FROM `" . DB_PREFIX . "order` o LEFT JOIN ".DB_PREFIX."wk_rma_order wro ON (wro.order_id = o.order_id) WHERE wro.id = '".(int)$rma."'")->row;
		return $customer;
  }

  public function getSeller($rma = 0){
    $seller = $this->db->query("SELECT c.email,c.firstname,c.lastname FROM `" . DB_PREFIX . "customer` c LEFT JOIN `".DB_PREFIX."wk_rma_order` wro ON (wro.seller_id = c.customer_id) WHERE wro.id = '".(int)$rma."'")->row;
    return $seller;
  }

  protected function getProductName($order_product_id) {
    $result = $this->db->query("SELECT name FROM `" . DB_PREFIX . "order_product` WHERE `order_product_id`='" . (int)$order_product_id . "'")->row;
    if ($result) {
      return $result['name'];
    }
    return '';
  }

  public function mail($data, $mail_type = '') {

    if (!isset($data['rma_id']) || !(int)$data['rma_id']) {
      return;
    }
    $value_index = array();
    $value_index['product_name'] = array();
    if (isset($data['product_list']) && is_array($data['product_list'])) {
      foreach ($data['product_list'] as $product_id) {
        $check = $this->getProductName($product_id);
        if($check) {
          $value_index['product_name'][] = $check;
        }
      }
    }
    $value_index['product_name'] = implode(',',$value_index['product_name']);

		$this->load->language('catalog/rma_mail');
		$mail_message = '';
    $mail_from    = $this->config->get('marketplace_adminmail') ? $this->config->get('marketplace_adminmail') : $this->config->get('config_email');

    $customer_info = $this->getCustomer($data['rma_id']);
    $seller_info   = $this->getSeller($data['rma_id']);

		switch($mail_type){
      case 'message_to_customer' :
        $mail_details = $this->getMailData($this->config->get('wk_rma_message_to_customer_mail'));
        if ($mail_details) {
          $mail_subject = $mail_details['subject'];
          $mail_message = $mail_details['message'];
        }
        $value_index['link'] = defined('HTTP_CATALOG') ? HTTP_CATALOG . 'index.php?route=account/rma/viewrma&vid=' . $data['rma_id'] : $this->urlChange('account/rma/viewrma&vid='.$data['rma_id'],'','SSL');
        $mail_to       = $customer_info['email'];
      break;
      case 'message_to_admin_on_seller_message' :
        $mail_details = $this->getMailData($this->config->get('wk_rma_message_to_admin_sellermail'));
        if ($mail_details) {
          $mail_subject = $mail_details['subject'];
          $mail_message = $mail_details['message'];
        }
        $mail_to       = $this->config->get('marketplace_adminmail') ? $this->config->get('marketplace_adminmail') : $this->config->get('config_email');
        $mail_from     = $seller_info['email'];
      break;
      case 'message_to_seller_on_admin_message' :
        $mail_details = $this->getMailData($this->config->get('wk_rma_message_to_seller_adminmail'));
        if ($mail_details) {
          $mail_subject = $mail_details['subject'];
          $mail_message = $mail_details['message'];
        }
        $mail_to       = $seller_info['email'];
        $value_index['link'] = defined('HTTP_CATALOG') ? HTTP_CATALOG . 'index.php?route=account/customerpartner/rma/wk_rma_admin/getform&id=' . $data['rma_id'] : $this->urlChange('account/customerpartner/rma/wk_rma_admin/getform&id='.$data['rma_id'],'','SSL');
      break;
      case 'label_to_customer' :
        $mail_details = $this->getMailData($this->config->get('wk_rma_label_to_customer_mail'));
        if ($mail_details) {
          $mail_subject = $mail_details['subject'];
          $mail_message = $mail_details['message'];
        }
        $mail_to = $customer_info['email'];
        $value_index ['label_link'] = "<a href=" . $data['link'] . ">label link</a>";
        $value_index['link'] = defined('HTTP_CATALOG') ? HTTP_CATALOG . 'index.php?route=account/rma/viewrma&vid=' . $data['rma_id'] : $this->urlChange('account/rma/viewrma&vid='.$data['rma_id'],'','SSL');
        if(file_exists(DIR_IMAGE.'rma/files/'.$data['label'])){
          $mail_attachment = DIR_IMAGE.'rma/files/'.$data['label'];
        }
      break;
			case 'generate_customer' ://to customer
        $mail_details = $this->getMailData($this->config->get('wk_rma_new_return_customer_mail'));
        if ($mail_details) {
          $mail_subject = $mail_details['subject'];
          $mail_message = $mail_details['message'];
        }
				$mail_to = $customer_info['email'];
				$value_index['link'] = defined('HTTP_CATALOG') ? HTTP_CATALOG . 'index.php?route=account/rma/viewrma&vid=' . $data['rma_id'] : $this->urlChange('account/rma/viewrma&vid='.$data['rma_id'],'','SSL');
			break;
			case 'generate_seller' ://to admin

        $mail_details = $this->getMailData($this->config->get('wk_rma_new_return_seller_mail'));
        if ($mail_details) {
          $mail_subject = $mail_details['subject'];
          $mail_message = $mail_details['message'];
        }
        $value_index['link'] =  defined('HTTP_CATALOG') ? HTTP_CATALOG . 'index.php?route=account/customerpartner/rma/wk_rma_admin/getform&id=' . $data['rma_id'] : $this->urlChange('account/customerpartner/rma/wk_rma_admin/getform&id='.$data['rma_id'],'','SSL');
				$mail_to = $seller_info['email'];
			break;
      case 'generate_admin' ://to admin

          $mail_details = $this->getMailData($this->config->get('wk_rma_new_return_admin_mail'));
          if ($mail_details) {
            $mail_subject = $mail_details['subject'];
            $mail_message = $mail_details['message'];
          }

          $mail_to = $mail_from;
          $mail_from = $customer_info['email'];
      break;
			case 'message_to_admin' :
        $mail_details = $this->getMailData($this->config->get('wk_rma_message_to_admin_mail'));
        if ($mail_details) {
          $mail_subject = $mail_details['subject'];
          $mail_message = $mail_details['message'];
        }
				$mail_to = $mail_from;
				$mail_from = $customer_info['email'];
			break;
      case 'message_to_seller' :
        $mail_details = $this->getMailData($this->config->get('wk_rma_message_to_seller_mail'));
        if ($mail_details) {
          $mail_subject = $mail_details['subject'];
          $mail_message = $mail_details['message'];
        }
        $value_index['link'] = defined('HTTP_CATALOG') ? HTTP_CATALOG . 'index.php?route=account/customerpartner/rma/wk_rma_admin/getform&id=' . $data['rma_id'] : $this->urlChange('account/customerpartner/rma/wk_rma_admin/getform&id='.$data['rma_id'],'','SSL');
      $mail_to = $seller_info['email'];
      break;
			case 'status_to_admin' :
        $mail_details = $this->getMailData($this->config->get('wk_rma_status_to_admin_mail'));
        if ($mail_details) {
          $mail_subject = $mail_details['subject'];
          $mail_message = $mail_details['message'];
        }
				$mail_to = $mail_from;
				$mail_from = $customer_info['email'];
			break;
      case 'status_to_seller' :
        $mail_details = $this->getMailData($this->config->get('wk_rma_status_to_seller_mail'));
        if ($mail_details) {
          $mail_subject = $mail_details['subject'];
          $mail_message = $mail_details['message'];
        }
        $value_index['link'] = defined('HTTP_CATALOG') ? HTTP_CATALOG . 'index.php?route=account/customerpartner/rma/wk_rma_admin/getform&id=' . $data['rma_id'] : $this->urlChange('account/customerpartner/rma/wk_rma_admin/getform&id='.$data['rma_id'],'','SSL');
        $mail_to = $seller_info['email'];
      break;
			case 'status_to_customer' :
        $mail_details = $this->getMailData($this->config->get('wk_rma_status_to_customer_mail'));
        if ($mail_details) {
          $mail_subject = $mail_details['subject'];
          $mail_message = $mail_details['message'];
        }
        $value_index['link'] = defined('HTTP_CATALOG') ? HTTP_CATALOG . 'index.php?route=account/rma/viewrma&vid=' . $data['rma_id'] : $this->urlChange('account/rma/viewrma&vid='.$data['rma_id'],'','SSL');
				$mail_to = $customer_info['email'];
			break;
			default :
				return;
		}

    if (isset($value_index['link']) && $value_index['link']) {
      $value_index['link'] = "<a href=" . $value_index['link'] . ">Return link</a>";
    }

    if(isset($data['customer_message']) && $data['customer_message']) {
      $value_index['message'] = $data['customer_message'];
    }

		if($mail_message){

			$data['store_name'] = $this->config->get('config_name');
			$data['store_url'] = (defined('HTTP_CATALOG') ? HTTP_CATALOG : HTTP_SERVER);
			$data['logo'] = (defined('HTTP_CATALOG') ? HTTP_CATALOG : HTTP_SERVER).'image/' . $this->config->get('config_logo');

			$find = array(
				'{order_id}',
				'{rma_id}',
				'{product_name}',
				'{customer_name}',
				'{message}',
        '{link}',
        '{label_link}',
				'{seller_name}',
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
				'order_id' => $customer_info['order_id'],
				'rma_id' => $data['rma_id'],
				'product_name' => '',
				'customer_name' => $customer_info['firstname'].' '.$customer_info['lastname'],
				'message' => isset($data['message']) ? $data['message'] : '',
        'link' => '',
        'label_link' => '',
				'seller_name' => $seller_info['firstname'] . ' ' . $seller_info['lastname'],
				'config_logo' => '<a href="'.(defined('HTTP_CATALOG') ? HTTP_CATALOG : HTTP_SERVER).'" title="'.$data['store_name'].'"><img src="'.(defined('HTTP_CATALOG') ? HTTP_CATALOG : HTTP_SERVER).'image/' . $this->config->get('config_logo').'" alt="'.$data['store_name'].'" style="max-width:200px;"/></a>',
				'config_icon' => '<img src="'.(defined('HTTP_CATALOG') ? HTTP_CATALOG : HTTP_SERVER).'image/' . $this->config->get('config_icon').'" style="max-width:200px;">',
				'config_currency' => $this->config->get('config_currency'),
				'config_image' => '<img src="'.(defined('HTTP_CATALOG') ? HTTP_CATALOG : HTTP_SERVER).'image/' . $this->config->get('config_image').'" style="max-width:200px;">',
				'config_name' => $this->config->get('config_name'),
				'config_owner' => $this->config->get('config_owner'),
				'config_address' => $this->config->get('config_address'),
				'config_geocode' => $this->config->get('config_geocode'),
				'config_email' => $this->config->get('config_email'),
				'config_telephone' => $this->config->get('config_telephone'),
			);

			$replace = array_merge($replace,$value_index);

			$mail_message = trim(str_replace($find, $replace, $mail_message));

			$data['subject'] = $mail_subject;
			$data['message'] = html_entity_decode($mail_message, ENT_QUOTES, 'UTF-8');

  		$html = $this->load->view('catalog/rma_mail', $data);

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
			$mail->setSender($data['store_name']);
			$mail->setSubject($data['subject']);
			$mail->setHtml($html);
			$mail->setText(strip_tags($html));
			$mail->send();

		}
	}

  public function getMailData($id){
    $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_mail WHERE id='".(int)$id."'");
    return $query->row;
  }

  public function urlChange($route,$get = '',$extra = '') {
    if (version_compare(VERSION, '2.2', '>')) {
      return $this->url->link($route, $get, 'SSL');
    } else {
      return $this->url->link($route, $get, true);
    }

  }

  public function viewProducts($id){
    $sql = "SELECT pd.name,wrp.quantity,wrr.reason FROM " . DB_PREFIX . "product_description pd LEFT JOIN ".DB_PREFIX."wk_rma_product wrp ON (wrp.product_id = pd.product_id) LEFT JOIN ".DB_PREFIX."wk_rma_reason wrr ON (wrp.reason = wrr.reason_id) WHERE wrp.rma_id = '".(int)$id."' AND pd.language_id = '".$this->config->get('config_language_id')."' AND wrr.language_id = '".$this->config->get('config_language_id')."'";
    $result = $this->db->query($sql);

    return $result->rows;
  }

}
