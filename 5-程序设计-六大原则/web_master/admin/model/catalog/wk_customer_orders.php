<?php
/**
 * Class ModelCatalogWkcustomerorders
 * Webkul Software.
 * @category  Webkul
 * @author    Webkul
 * @copyright Copyright (c) 2010-2016 Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 */
class ModelCatalogWkcustomerorders extends Model {

	use RmaModelTrait;
	use RmaModelRmaTrait;

	public function validateOrder($qty,$order_p_id){

		$query = '';

		if((int)$this->config->get('wk_rma_system_time'))
			$query = " AND o.date_added >= DATE_SUB(CURDATE(), INTERVAL '".(int)$this->config->get('wk_rma_system_time')."' DAY)"; //

		$sql = $this->db->query("SELECT o.order_id FROM " . DB_PREFIX . "order_product op LEFT JOIN `" . DB_PREFIX . "order` o ON (o.order_id = op.order_id) WHERE order_product_id='".(int)$order_p_id."' AND quantity >= '".(int)$qty."' $query AND o.order_status_id > 0 ")->row;

		if($sql){
			return true;
		} else {
			return false;
		}
	}


	public function getAllCustomers(){
    return $this->db->query("SELECT CONCAT(firstname ,' ',lastname) as name , customer_id FROM " . DB_PREFIX . "customer")->rows;
  }

}
