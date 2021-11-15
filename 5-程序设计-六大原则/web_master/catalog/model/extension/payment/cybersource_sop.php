<?php
class ModelExtensionPaymentCybersourceSop extends Model {
  	public function getMethod($address, $total = false) {

		$extension_type = 'extension/payment';
		$classname = str_replace('vq2-', '', str_replace(basename(DIR_APPLICATION) . '_' . strtolower(get_parent_class($this)) . '_' . $extension_type . '_', '', basename(__FILE__, '.php')));
        $conf_code = 'payment_cybersource_sop';
		$this->load->language($extension_type . '/' . $classname);

		// v14x backwards compatible
		if ($total === false) { $total = $this->cart->getTotal(); }

		// Return if total is used and too low
		if ($this->config->get($conf_code . '_total')) { if ($this->config->get($conf_code . '_total') > $total) { return array(); } }

		if ($this->config->get($conf_code . '_status')) {

			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get($conf_code . '_geo_zone_id') . "' AND country_id = '" . (int)($address['country_id'] ?? 0) . "' AND (zone_id = '" . (int)($address['zone_id'] ?? 0) . "' OR zone_id = '0')");

			if (!$this->config->get($conf_code . '_geo_zone_id')) {
        		$status = TRUE;
      		} elseif ($query->num_rows) {
      		  	$status = TRUE;
      		} else {
     	  		$status = FALSE;
			}
      	} else {
			$status = FALSE;
		}

		$method_data = array();

		if ($status) {
      		$method_data = array(
				'code'		 => $classname,
        		'title'      => $this->language->get('text_title'),
        		'terms'		 => '',
				'sort_order' => $this->config->get($conf_code . '_sort_order')
      		);
    	}

    	return $method_data;
  	}
}
?>