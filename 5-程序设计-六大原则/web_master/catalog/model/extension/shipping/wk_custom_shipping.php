<?php

/**
 * Class ModelExtensionShippingwkcustomshipping
 * @property ModelAccountCustomerpartnerOrder $model_account_customerpartnerorder
 */
class ModelExtensionShippingwkcustomshipping extends Model {

	function getQuote($address,$seller = array()) {

		//to stop it's default functionality
		if($this->config->get('wk_multi_shipping_status') AND !$seller){
			return false;
		}

		$this->session->remove('seller_custom_shipping');

		$method_data = $result_csv = array();
		$error = false;

		$this->load->language('shipping/wk_custom_shipping');

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('shipping_wk_custom_shipping_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

		if (!$this->config->get('shipping_wk_custom_shipping_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		$shipping_postcode = $address;

		if(isset($shipping_postcode['postcode'])){
			$zipcod = $shipping_postcode['postcode'];
		}else{
			$zipcod = 0;
		}

		if(!$this->config->get('wk_multi_shipping_status') AND !$seller){
			$this->load->model('account/customerpartnerorder');
			$seller = $this->model_account_customerpartnerorder->sellerAdminData($this->cart->getProducts());
		}

		$total = $totalfortext = 0;
		$addSeller = '';

		if($this->config->get('shipping_wk_custom_shipping_method')=='flat'){
			foreach($seller as $pro_det){

				$seller_name = $this->db->query("SELECT CONCAT(firstname ,' ',lastname) seller_name FROM " . DB_PREFIX . "customer WHERE customer_id = '".$pro_det['seller']."'")->row;

				if($this->config->get('wk_multi_shipping_status')){
					$addSeller = $pro_det['seller'].'_';
				}

				$res_error = false;

				if($pro_det['seller']=='Admin'){
					$result_csv[] = array('shipping_price' => $this->config->get('shipping_wk_custom_shipping_admin_flatrate'));

					$this->session->data['seller_custom_shipping']['Admin'] = $this->config->get('shipping_wk_custom_shipping_admin_flatrate');
				}else{
					$csv_id = $pro_det['seller'];
					$res_csv = $this->db->query("SELECT amount as shipping_price,tax_class_id FROM " . DB_PREFIX . "customerpartner_flatshipping WHERE status = 1 and partner_id = '$csv_id'" );
					if(isset($res_csv->row['shipping_price'])){
						$result_csv[] = $res_csv->row;

						$this->session->data['seller_custom_shipping'][$seller_name['seller_name']] = $res_csv->row['shipping_price'];
					}else{
						$res_error = true;
					}
				}

				if($res_error){
					$error = '';
					if($this->config->get('shipping_wk_custom_shipping_error_msg')!=''){
						$error = $error. $this->config->get('shipping_wk_custom_shipping_error_msg');
					}
					else{
						$error = $error. 'For Shipping method "'.$this->config->get('shipping_wk_custom_shipping_title').'" : '. $seller_name['seller_name']." Vendor does not provide services for zip : ".$zipcod.', so this method is not selectable.';
					}
				}

			}

		}

		if($this->config->get('shipping_wk_custom_shipping_method')=='matrix'){
			foreach($seller as $pro_det){

				$seller_name = $this->db->query("SELECT CONCAT(firstname ,' ',lastname) seller_name FROM " . DB_PREFIX . "customer WHERE customer_id = '".$pro_det['seller']."'")->row;

				if($this->config->get('wk_multi_shipping_status')){
					$addSeller = $pro_det['seller'].'_';
				}

				$csv_id = $pro_det['seller'];
				$pro_weight = $pro_det['weight'];

				$res_csv = $this->db->query("SELECT price as shipping_price FROM " . DB_PREFIX . "customerpartner_shipping WHERE seller_id = '".(int)$csv_id."' AND weight_to >= '".(float)$pro_weight."' AND  weight_from <= '".(float)$pro_weight."' AND country_code = '".$this->db->escape($shipping_postcode['iso_code_2'])."' AND ( (convert(zip_to,unsigned) >= '".(int)$zipcod."' AND convert(zip_from,unsigned) <= '".(int)$zipcod."') or (zip_to LIKE '%".$zipcod."' OR zip_from LIKE '%".$zipcod."'))");
				$result_csv[] = $res_csv->row;

				if (isset($res_csv->row['shipping_price'])) {
					if ($csv_id == 'Admin') {
						$this->session->data['seller_custom_shipping']['Admin'] = $res_csv->row['shipping_price'];
					} else {
						$this->session->data['seller_custom_shipping'][$seller_name['seller_name']] = $res_csv->row['shipping_price'];
					}
				}

				$res_error = true;
				if(!isset($res_csv->row['shipping_price']))
					$res_error = false;

					if ($csv_id != 'Admin') {
					  $seller_shipping_status = $this->db->query("SELECT status FROM " . DB_PREFIX . "customerpartner_flatshipping WHERE partner_id =" . $csv_id)->row;

					  if(isset($seller_shipping_status['status']) && !$seller_shipping_status['status']){
					    $res_error = false;
					  }
					}

				if(!$res_error){
					$error = '';
					if($this->config->get('shipping_wk_custom_shipping_error_msg')!=''){
						$error = $error.$this->config->get('shipping_wk_custom_shipping_error_msg');
					}
					else{
						if ($csv_id == 'Admin') {
							$error = $error.'For Shipping method "'.$this->config->get('shipping_wk_custom_shipping_title').'" : '. $csv_id." does not provide services for zip : ".$zipcod.', so this method is not selectable.';
						} else {
							$error = $error.'For Shipping method "'.$this->config->get('shipping_wk_custom_shipping_title').'" : '. $seller_name['seller_name']." Vendor does not provide services for zip : ".$zipcod.', so this method is not selectable.';
						}
					}
				}
			}
		}

		if($this->config->get('shipping_wk_custom_shipping_method')=='both'){
			foreach($seller as $pro_det){

				$seller_name = $this->db->query("SELECT CONCAT(firstname ,' ',lastname) seller_name FROM " . DB_PREFIX . "customer WHERE customer_id = '".$pro_det['seller']."'")->row;

				if($this->config->get('wk_multi_shipping_status')){
					$addSeller = $pro_det['seller'].'_';
				}

				$res_error = false;
				$csv_id = $pro_det['seller'];
				$pro_weight = $pro_det['weight'];

				$res_csv = $this->db->query("SELECT price as shipping_price FROM " . DB_PREFIX . "customerpartner_shipping WHERE seller_id = '".(int)$csv_id."' AND weight_to >= '".(float)$pro_weight."' AND  weight_from <= '".(float)$pro_weight."' AND country_code = '".$this->db->escape($shipping_postcode['iso_code_2'])."' AND ( (convert(zip_to,unsigned) >= '".(int)$zipcod."' AND convert(zip_from,unsigned) <= '".(int)$zipcod."') or (zip_to LIKE '%".$zipcod."' OR zip_from LIKE '%".$zipcod."'))");

				if(isset($res_csv->row['shipping_price'])){
					$result_csv[] = $res_csv->row;

					$this->session->data['seller_custom_shipping'][$seller_name['seller_name']] = $res_csv->row['shipping_price'];
				}else{
					if($pro_det['seller']=='Admin'){
						$result_csv[] = array('shipping_price' => $this->config->get('shipping_wk_custom_shipping_admin_flatrate'));

						$this->session->data['seller_custom_shipping']['Admin'] = $this->config->get('shipping_wk_custom_shipping_admin_flatrate');
					}else{
						$res_csv = $this->db->query("SELECT amount as shipping_price,tax_class_id FROM " . DB_PREFIX . "customerpartner_flatshipping WHERE status = 1 and partner_id = '".$csv_id."'" );
						if(isset($res_csv->row['shipping_price'])){
							$result_csv[] = $res_csv->row;

							$this->session->data['seller_custom_shipping'][$seller_name['seller_name']] = $res_csv->row['shipping_price'];
						}else{
							$res_error = true;
						}
					}
				}

				//if price is not avilable in both table matrix and flat then error generate
				if($res_error){
					$error = '';
					if($this->config->get('shipping_wk_custom_shipping_error_msg')!=''){
						$error =  $error.$this->config->get('shipping_wk_custom_shipping_error_msg');
					}
					else{
						$error =  $error. 'For Shipping method "'.$this->config->get('shipping_wk_custom_shipping_title').'" : '. $seller_name['seller_name']." Vendor does not provide services for zip : ".$zipcod.', so this method is not selectable.';
					}
				}

			}

		}

		if($result_csv)
			foreach($result_csv as $res_res){
				if(isset($res_res['shipping_price'])){
					$total 	 =  $total + $res_res['shipping_price'];
					$totalfortext += $this->tax->calculate($res_res['shipping_price'], $this->config->get('shipping_wk_custom_shipping_tax_class_id'), $this->config->get('config_tax'));
				}
			}

		if ($error || !$this->config->get('shipping_wk_custom_shipping_status')) {
			$this->session->remove('seller_custom_shipping');
		}

		if (isset($this->session->data['seller_custom_shipping']) && $this->session->data['seller_custom_shipping']) {
			foreach ($this->session->data['seller_custom_shipping'] as $key => $value) {
				$this->session->data['seller_custom_shipping'][$key] = $this->currency->format($value,session('currency'));
			}
		}

		if ($status) {
			$quote_data = array();
			//small bug fixed to display prices correctly can display amount difference but persons will get correct price..
			$total += $totalfortext - $this->tax->calculate($total, $this->config->get('shipping_wk_custom_shipping_tax_class_id'), $this->config->get('config_tax'));
      		$quote_data['wk_custom_shipping'] = array(
        		'code'         => $addSeller.'wk_custom_shipping.wk_custom_shipping',
        		'title'        => $this->config->get('shipping_wk_custom_shipping_title'),
        		'cost'         => $total,
        		'tax_class_id' => $this->config->get('shipping_wk_custom_shipping_tax_class_id'),
				'text'         => $this->currency->format($totalfortext,session('currency')),
      		);

      		$method_data = array(
        		'code'       => $addSeller.'wk_custom_shipping',
        		'title'      => $this->config->get('shipping_wk_custom_shipping_title'),
        		'quote'      => $quote_data,
				'sort_order' => $this->config->get('shipping_wk_custom_shipping_sort_order'),
        		'error'      => $error,

      		);
		}

		return $method_data;
	}
}
?>
