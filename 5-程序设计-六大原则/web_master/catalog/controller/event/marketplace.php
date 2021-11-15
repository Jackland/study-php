<?php
class ControllerEventMarketplace extends Controller {
	/**
	 * [deleteMPOrder is used to deleted the markeplace data related to the perticular order]
	 * @param  [string] $route  [catalog/model/checkout/deleteOrder]
	 * @param  [array] $args   [array of arguments passed in the catalog/model/checkout/deleteOrder]
	 * @param  [type] $output [description]
	 */
	public function deleteMPOrder(&$route, &$args, &$output) {
    if (isset($args[0]) && $args[0]) {
      $order_id = $args[0];

      $str = '%order_id_:' . $order_id . '_';

      $this->db->query("DELETE FROM ".DB_PREFIX."customer_activity WHERE data LIKE '" . $str . "'");

      if($this->config->get('module_marketplace_status')) {

				$transaction = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_transaction")->rows;

				if ($transaction) {
				  foreach ($transaction as $key => $value) {

				    $order_ids = explode(',',$value['order_id']);

				    if ($order_ids && in_array($order_id,$order_ids)) {

				      $paid_amount = $this->db->query("SELECT SUM(customer) AS paid_amount FROM " . DB_PREFIX . "customerpartner_to_order WHERE order_id = " . (int)$order_id . " AND customer_id = ". $value['customer_id'])->row;

				      if (isset($paid_amount['paid_amount']) && $paid_amount['paid_amount']) {
				        if ($paid_amount['paid_amount'] == $value['amount']) {
				          $this->db->query("DELETE FROM ".DB_PREFIX."customerpartner_to_transaction WHERE id = '".(int)$value['id']."' ");
				        } else {
				          $amount = $value['amount'] - $paid_amount['paid_amount'];

				          $text = substr($value['text'],0,1) . $amount;

				          $this->db->query("UPDATE ".DB_PREFIX."customerpartner_to_transaction SET amount = " . $amount . ", text = '" . $text . "' WHERE id = '".(int)$value['id']."' ");
				        }
				      }
				    }
				  }
				}

        $str = '%order_id_:_' . $order_id . '__';

				$this->db->query("DELETE FROM ".DB_PREFIX."mp_customer_activity WHERE data LIKE '" . $str . "'");

        $this->db->query("DELETE FROM ".DB_PREFIX."customerpartner_to_order WHERE order_id = '".(int)$order_id."' ");

        $this->db->query("DELETE FROM ".DB_PREFIX."customerpartner_to_order_status WHERE order_id = '".(int)$order_id."' ");

        $this->db->query("DELETE FROM ".DB_PREFIX."customerpartner_sold_tracking WHERE order_id = '".(int)$order_id."' ");
      }
    }
	}
}
