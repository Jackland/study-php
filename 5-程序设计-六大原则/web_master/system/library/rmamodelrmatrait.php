<?php
/**
 * Webkul Software.
 * @category  Webkul
 * @author    Webkul
 * @copyright Copyright (c) 2010-2016 Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 */

trait RmaModelRmaTrait {

  public function approveAdminStatus($data = array(),$seller_id = 0) {

    if ($this->config->get('wk_rma_seller_return_separate')) {
      $change = " AND `seller_id` = '" . (int)$seller_id . "'";
    } else {
      $change = '';
    }

    if (isset($data['id']) && isset($data['approve']) && $this->db->query("SELECT * FROM `" . DB_PREFIX . "wk_rma_status` WHERE `status_id`='" . (int)$this->db->escape($data['id']) . "'" . $change)->num_rows) {
      $this->db->query("UPDATE `" . DB_PREFIX . "wk_rma_status` SET `default` = 0 WHERE `default` NOT IN ('cancel','solve')" . $change);
      if ($data['approve'] == 'approve') {
        $this->db->query("UPDATE `" . DB_PREFIX . "wk_rma_status` SET `default` = 'admin' WHERE status_id='" . (int)$this->db->escape($data['id']) . "'" . $change);
        $this->db->query("UPDATE `" . DB_PREFIX . "wk_rma_order` SET  `admin_status` =  '" . (int)$this->db->escape($data['id']) . "' WHERE admin_status = 0 " . $change);
      }
      return true;
    }
    return false;
  }

  public function approveSolveStatus($data = array(),$seller_id = 0) {

    if ($this->config->get('wk_rma_seller_return_separate')) {
      $change = " AND `seller_id` = '" . (int)$seller_id . "'";
    } else {
      $change = '';
    }

    if (isset($data['id']) && isset($data['approve']) && $this->db->query("SELECT * FROM `" . DB_PREFIX . "wk_rma_status` WHERE `status_id`='" . (int)$this->db->escape($data['id']) . "'" . $change)->num_rows) {
      $this->db->query("UPDATE `" . DB_PREFIX . "wk_rma_status` SET `default` = 'null' WHERE `default` NOT IN ('cancel','admin')" . $change);
      if ($data['approve'] == 'approve') {
        $this->db->query("UPDATE `" . DB_PREFIX . "wk_rma_status` SET `default` = 'solve' WHERE status_id='" . (int)$this->db->escape($data['id']) . "'" . $change);
        $this->db->query("UPDATE `" . DB_PREFIX . "wk_rma_order` SET  `admin_status` =  '" . (int)$this->db->escape($data['id']) . "' WHERE solve_rma = 1 AND admin_status = 0" . $change);
      }
      return true;
    }
    return false;
  }

  public function approveCancelStatus($data = array(),$seller_id = 0) {

    if ($this->config->get('wk_rma_seller_return_separate')) {
      $change = " AND `seller_id` = '" . (int)$seller_id . "'";
    } else {
      $change = '';
    }

    if (isset($data['id']) && isset($data['approve']) && $this->db->query("SELECT * FROM `" . DB_PREFIX . "wk_rma_status` WHERE `status_id`='" . (int)$this->db->escape($data['id']) . "'" . $change)->num_rows) {
      $this->db->query("UPDATE `" . DB_PREFIX . "wk_rma_status` SET `default` = 'null' WHERE `default` NOT IN ('admin','solve')" . $change);
      if ($data['approve'] == 'approve') {
        $this->db->query("UPDATE `" . DB_PREFIX . "wk_rma_status` SET `default` = 'cancel' WHERE status_id='" . (int)$this->db->escape($data['id']) . "'" . $change);
        $this->db->query("UPDATE `" . DB_PREFIX . "wk_rma_order` SET  `admin_status` =  '" . (int)$this->db->escape($data['id']) . "' WHERE cancel_rma = 1 AND admin_status = 0" . $change);
      }
      return true;
    }
    return false;
  }

  public function getAdminStatus($seller_id = 0){

    if ($seller_id === true) {
      $change ='';
    } else {
      $change = " AND `seller_id` = '" . (int)$seller_id . "'";
    }

    $sql = $this->db->query("SELECT name ,id,status_id FROM " . DB_PREFIX . "wk_rma_status WHERE language_id ='".$this->config->get('config_language_id')."' AND status = 1 " . $change . " ORDER BY id ");
    return $sql->rows;
  }

  //for reason
  public function getCustomerReason($seller_id = 0){

    if ($seller_id === true) {
      $change ='';
    } else {
      $change = " AND `seller_id` = '" . (int)$seller_id . "'";
    }

    $sql = $this->db->query("SELECT reason ,reason_id as id FROM " . DB_PREFIX . "wk_rma_reason WHERE language_id ='".$this->config->get('config_language_id')."' AND status = 1 " . $change . " ORDER BY id ");

    return $sql->rows;
  }

  //for status
  public function getCustomerStatus($seller_id = 0){

    if ($seller_id === true) {
      $change ='';
    } else {
      $change = " AND `seller_id` = '" . (int)$seller_id . "'";
    }

    $sql = $this->db->query("SELECT name,status_id as id FROM " . DB_PREFIX . "wk_rma_status wrs WHERE language_id ='".$this->config->get('config_language_id')."' AND status = 1 " . $change . " ORDER BY id ");

    return $sql->rows;
  }

  public function viewtotal($data = array(),$seller_id = 0,$admin = false){

    if (!$admin) {
      $change = " AND wro.seller_id = '" . (int)$seller_id . "'";
    } else {
      $change = '';
    }

    $sql = "SELECT CONCAT(c.firstname,' ', c.lastname) AS name,wro.id,wro.admin_return,wro.order_id,wro.add_info,wro.rma_auth_no,wro.date,wrs.color,wrs.name as admin_status,wro.admin_status as rma_status,wro.cancel_rma,wro.solve_rma FROM " . DB_PREFIX . "wk_rma_order wro LEFT JOIN " . DB_PREFIX . "wk_rma_customer wrc ON (wro.id = wrc.rma_id) LEFT JOIN `" . DB_PREFIX . "order` c ON ((wrc.customer_id = c.customer_id || wrc.email = c.email ) AND c.order_id = wro.order_id) LEFT JOIN " . DB_PREFIX . "wk_rma_status wrs ON (wro.admin_status = wrs.status_id) WHERE wrs.language_id= '".$this->config->get('config_language_id')."'" . $change;

    $implode = array();

    if (!empty($data['filter_name'])) {
      $implode[] = "CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
    }

    if (isset($data['filter_order']) && !is_null($data['filter_order'])) {
      $implode[] = "wro.order_id = '" . (int)$data['filter_order'] . "'";
    }

    if (isset($data['filter_rma_status_id']) && !is_null($data['filter_rma_status_id'])) {
      $implode[] = "wrs.status = '" . (int)$data['filter_rma_status_id'] . "'";
    } else {
      $implode[] = "wrs.status = 1";
    }

    if (isset($data['filter_admin_status']) && !is_null($data['filter_admin_status'])) {
      if ($data['filter_admin_status'] == 'admin') {
        $implode[] = "wro.admin_return = 1";
      } else if ($data['filter_admin_status'] == 'solve') {
        $implode[] = "wro.solve_rma = 1";
      } else if ($data['filter_admin_status'] == 'cancel') {
        $implode[] = "wro.cancel_rma = 1";
      } else {
        $implode[] = " wrs.id = '" . (int)$data['filter_admin_status'] . "' AND admin_return <> 1 AND solve_rma <> 1 AND cancel_rma <> 1";
      }
    }

    if (!empty($data['filter_date'])) {
      $implode[] = "LCASE(wro.date) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
    }

    if ($implode) {
      $sql .= " AND " . implode(" AND ", $implode);
    }

    $sort_data = array(
      'c.firstname',
      'c.order_id',
      'wro.order_id',
      'wrr.id',
      'wro.date',
    );


    if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
      $sql .= " ORDER BY " . $data['sort'];
    } else {
      $sql .= " ORDER BY wro.order_id";
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

    $result = $this->db->query($sql);
    return $result->rows;
  }

  public function viewtotalentry($data = array(),$seller_id = 0,$admin = false){

    if (!$admin) {
      $change = " AND wro.seller_id = '" . (int)$seller_id . "'";
    } else {
      $change = '';
    }

    $sql = "SELECT wro.id FROM " . DB_PREFIX . "wk_rma_order wro LEFT JOIN " . DB_PREFIX . "wk_rma_customer wrc ON (wro.id = wrc.rma_id) LEFT JOIN `" . DB_PREFIX . "order` c ON ((wrc.customer_id = c.customer_id || wrc.email = c.email ) AND c.order_id = wro.order_id) LEFT JOIN " . DB_PREFIX . "wk_rma_status wrs ON (wro.admin_status = wrs.status_id) WHERE wrs.language_id= '".$this->config->get('config_language_id')."'" . $change;

    $implode = array();

    if (!empty($data['filter_name'])) {
      $implode[] = "CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
    }

    if (isset($data['filter_order']) && !is_null($data['filter_order'])) {
      $implode[] = "wro.order_id = '" . (int)$data['filter_order'] . "'";
    }

    if (isset($data['filter_admin_status']) && !is_null($data['filter_admin_status'])) {
      if ($data['filter_admin_status'] == 'admin') {
        $implode[] = "wro.admin_return = 1";
      } else if ($data['filter_admin_status'] == 'solve') {
        $implode[] = "wro.solve_rma = 1";
      } else if ($data['filter_admin_status'] == 'cancel') {
        $implode[] = "wro.cancel_rma = 1";
      } else {
        $implode[] = " wrs.id = '" . (int)$data['filter_admin_status'] . "' AND admin_return <> 1 AND solve_rma <> 1 AND cancel_rma <> 1";
      }
    }

    if (isset($data['filter_rma_status_id']) && !is_null($data['filter_rma_status_id'])) {
      $implode[] = "wrs.status = '" . (int)$data['filter_rma_status_id'] . "'";
    } else {
      $implode[] = "wrs.status = 1";
    }

    if (!empty($data['filter_date'])) {
      $implode[] = "LCASE(wro.date) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
    }

    if ($implode) {
      $sql .= " AND " . implode(" AND ", $implode);
    }

    $sort_data = array(
      'c.firstname',
      'c.order_id',
      'wrr.id',
      'wro.date',
    );

    if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
      $sql .= " ORDER BY " . $data['sort'];
    } else {
      $sql .= " ORDER BY c.firstname";
    }

    if (isset($data['order']) && ($data['order'] == 'DESC')) {
      $sql .= " DESC";
    } else {
      $sql .= " ASC";
    }

    $result = $this->db->query($sql);

    return count($result->rows);
  }

  public function getSellerByRMA($rma_id) {
    return $this->db->query("SELECT CONCAT(c.firstname, ' ', c.lastname) as name, r.seller_id FROM `" . DB_PREFIX . "wk_rma_status` r LEFT JOIN `" . DB_PREFIX . "customer` c ON (c.customer_id = r.seller_id) WHERE r.id = '" . (int)$rma_id . "' AND r.language_id = '" . (int)$this->config->get('config_language_id') . "'")->row;
  }

  public function getSellerByStatus($status_id) {
    return $this->db->query("SELECT CONCAT(c.firstname, ' ', c.lastname) as name, r.seller_id FROM `" . DB_PREFIX . "wk_rma_status` r LEFT JOIN `" . DB_PREFIX . "customer` c ON (c.customer_id = r.seller_id) WHERE r.status_id = '" . (int)$status_id . "' AND r.language_id = '" . (int)$this->config->get('config_language_id') . "'")->row;
  }

  public function getSellerByReason($reason_id) {
    return $this->db->query("SELECT CONCAT(c.firstname, ' ', c.lastname) as name, r.seller_id FROM `" . DB_PREFIX . "wk_rma_reason` r LEFT JOIN `" . DB_PREFIX . "customer` c ON (c.customer_id = r.seller_id) WHERE r.reason_id = '" . (int)$reason_id . "' AND r.language_id = '" . (int)$this->config->get('config_language_id') . "'")->row;
  }

   public function deleteStatus($id, $seller_id = 0){
    if (!$this->db->query("SELECT * FROM `" . DB_PREFIX . "wk_rma_order` WHERE admin_status = '" . (int)$id . "'")->num_rows) {
      if ($seller_id) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "wk_rma_status WHERE status_id = '".(int)$id."' AND seller_id = '" . (int)$seller_id . "'");
      } else {
        $this->db->query("DELETE FROM " . DB_PREFIX . "wk_rma_status WHERE status_id = '".(int)$id."'");
      }
    } else {
      $this->load->language('catalog/rma_mail');
      session()->set('error', $this->language->get('error_status_in_use'));
      session()->set('error_warning', $this->language->get('error_status_in_use'));
    }
  }

  public function viewStatusbyId($id, $seller_id = 0){
    $sql = $this->db->query("SELECT * FROM " . DB_PREFIX . "wk_rma_status WHERE status_id='".(int)$id."' AND seller_id = '" . (int)$seller_id . "'");
    return $sql->rows;
  }

  public function viewStatus($data, $seller_id = 0, $admin = false){

    if (!$admin) {
      $change = " AND seller_id = '" . (int)$seller_id . "'";
    } else {
      $change = '';
    }

    $sql = "SELECT CONCAT(c.firstname,' ',c.lastname) as sellername,wrs.* FROM " . DB_PREFIX . "wk_rma_status wrs LEFT JOIN `" . DB_PREFIX . "customer` c ON (c.customer_id = wrs.seller_id) WHERE wrs.language_id ='".$this->config->get('config_language_id')."'" . $change;

    $implode = array();

    if (!empty($data['filter_name'])) {
      $implode[] = "LCASE(wrs.name) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
    }

    if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
      $implode[] = "wrs.status = '" . (int)$data['filter_status'] . "'";
    }

    if ($implode) {
      $sql .= " AND " . implode(" AND ", $implode);
    }

    $sort_data = array(
      'wrs.name',
      'wrs.status',
      'wrs.id',
      'sellername'
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

    $result = $this->db->query($sql);

    return $result->rows;
  }

  public function viewtotalStatus($data, $seller_id = 0,$admin = false){

    if (!$admin) {
      $change = " AND seller_id = '" . (int)$seller_id . "'";
    } else {
      $change = '';
    }

    $sql = "SELECT CONCAT(c.firstname,' ',c.lastname),wrs.* FROM " . DB_PREFIX . "wk_rma_status wrs LEFT JOIN `" . DB_PREFIX . "customer` c  ON (c.customer_id = wrs.seller_id) WHERE wrs.language_id ='".$this->config->get('config_language_id')."'" . $change;

    $implode = array();

    if (!empty($data['filter_name'])) {
      $implode[] = "LCASE(wrs.name) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
    }

    if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
      $implode[] = "wrs.status = '" . (int)$data['filter_status'] . "'";
    }

    if ($implode) {
      $sql .= " AND " . implode(" AND ", $implode);
    }

    $result = $this->db->query($sql);

    return count($result->rows);
  }

  public function deleteReason($id, $seller_id = 0){
    if ($seller_id) {
      $this->db->query("DELETE FROM " . DB_PREFIX . "wk_rma_reason WHERE reason_id = '".(int)$id."' AND seller_id = '" . (int)$seller_id . "'");
    } else {
      $this->db->query("DELETE FROM " . DB_PREFIX . "wk_rma_reason WHERE reason_id = '".(int)$id."'");
    }
  }

  public function viewreasonbyId($id, $seller_id = 0){
    $sql = $this->db->query("SELECT * FROM " . DB_PREFIX . "wk_rma_reason WHERE reason_id='".(int)$id."' AND seller_id = '" . (int)$seller_id . "'");
    return $sql->rows;
  }

  public function viewtotalreason($data, $seller_id = 0,$admin = false){

    if (!$admin) {
      $change = " AND seller_id = '" . (int)$seller_id . "'";
    } else {
      $change = '';
    }

    $sql = "SELECT COUNT(*) as total FROM " . DB_PREFIX . "wk_rma_reason wrr LEFT JOIN `" . DB_PREFIX . "customer` c ON (c.customer_id = wrr.seller_id) WHERE wrr.language_id ='".$this->config->get('config_language_id')."'" . $change;

    $implode = array();

    if (!empty($data['filter_reason'])) {
      $implode[] = "LCASE(reason) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_reason'])) . "%'";
    }

    if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
      $implode[] = "wrr.status = '" . (int)$data['filter_status'] . "'";
    }

    if ($implode) {
      $sql .= " AND " . implode(" AND ", $implode);
    }

     return $this->db->query($sql)->row['total'];
  }

  public function viewreason($data, $seller_id = 0,$admin = false){

    if (!$admin) {
      $change = " AND seller_id = '" . (int)$seller_id . "'";
    } else {
      $change = '';
    }

    $sql = "SELECT CONCAT(c.firstname, ' ', c.lastname) as name,wrr.* FROM " . DB_PREFIX . "wk_rma_reason wrr LEFT JOIN `" . DB_PREFIX . "customer` c ON (c.customer_id = wrr.seller_id) WHERE wrr.language_id ='".$this->config->get('config_language_id')."'" . $change;

    $implode = array();

    if (!empty($data['filter_reason'])) {
      $implode[] = "LCASE(wrr.reason) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_reason'])) . "%'";
    }

    if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
      $implode[] = "wrr.status = '" . (int)$data['filter_status'] . "'";
    }

    if ($implode) {
      $sql .= " AND " . implode(" AND ", $implode);
    }

    $sort_data = array(
      'wrr.reason',
      'wrr.status',
      'name',
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

    $result = $this->db->query($sql);

    return $result->rows;
  }

  public function addReason($data, $seller_id = 0){
    $reason_id = 1;
    $last_reason_id = $this->db->query("SELECT reason_id FROM " . DB_PREFIX . "wk_rma_reason ORDER BY reason_id DESC LIMIT 1")->row;
    if(isset($last_reason_id['reason_id']))
      $reason_id = $last_reason_id['reason_id']+1;
    foreach($data['reason'] as $key => $value)
      $this->db->query("INSERT INTO " . DB_PREFIX . "wk_rma_reason SET reason_id = '".(int)$reason_id."',`reason` = '".$this->db->escape($value)."',`language_id` ='".(int)$key."', `status` = '".(int)$data['status']."',`seller_id` = '" . (int)$seller_id . "'");
  }

  public function UpdateReason($data, $seller_id = 0){
    $reason_id = $data['id'];
    if (isset($this->session->data['seller_rma'])) {
      foreach($data['reason'] as $key => $value)
        $this->db->query("UPDATE " . DB_PREFIX . "wk_rma_reason SET  `seller_id` = '" . (int)$seller_id . "',`reason` = '".$this->db->escape($value)."',`status` = '".(int)$data['status']."' WHERE reason_id = '".(int)$reason_id."' AND `language_id` ='".(int)$key."'");
      $this->session->remove('seller_rma');
    } else
      foreach($data['reason'] as $key => $value)
        $this->db->query("UPDATE " . DB_PREFIX . "wk_rma_reason SET `reason` = '".$this->db->escape($value)."',`status` = '".(int)$data['status']."' WHERE reason_id = '".(int)$reason_id."' AND `language_id` ='".(int)$key."' AND `seller_id` = '" . (int)$seller_id . "'");
  }

  public function UpdateStatus($data,$seller_id = 0){
    if (isset($this->session->data['seller_rma'])) {
      foreach($data['reason'] as $key => $value)
        $this->db->query("UPDATE " . DB_PREFIX . "wk_rma_reason SET  `seller_id` = '" . (int)$seller_id . "',`reason` = '".$this->db->escape($value)."',`status` = '".(int)$data['status']."' WHERE reason_id = '".(int)$reason_id."' AND `language_id` ='".(int)$key."'");
      $this->session->remove('seller_rma');
    } else
      foreach($data['name'] as $key => $value)
        $this->db->query("UPDATE " . DB_PREFIX . "wk_rma_status SET `name` = '".$this->db->escape($value)."', `status` = '".(int)$data['status']."',`color`= '".$this->db->escape($data['color'])."' WHERE status_id = '".(int)$data['id']."' AND `language_id` ='".(int)$key."' AND `seller_id` = '" . (int)$seller_id . "'");
  }

  public function addStatus($data, $seller_id = 0){
    $status_id = 1;
    $last_status_id = $this->db->query("SELECT status_id FROM " . DB_PREFIX . "wk_rma_status ORDER BY status_id DESC LIMIT 1")->row;
    if(isset($last_status_id['status_id']))
      $status_id = $last_status_id['status_id']+1;

    foreach($data['name'] as $key => $value)
      $this->db->query("INSERT INTO " . DB_PREFIX . "wk_rma_status SET status_id = '".(int)$status_id."',`name` = '".$this->db->escape($value)."',`language_id` ='".(int)$key."', `status` = '".(int)$data['status']."', `color`= '".$this->db->escape($data['color'])."', `seller_id` = '" . (int)$seller_id . "'");
  }
}
