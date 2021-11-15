<?php

use App\Components\UniqueGenerator\Enums\ServiceEnum;
use App\Repositories\Common\SerialNumberRepository;

class ModelExtensionPaymentLineOfCredit extends Model {
	public function getMethod($address, $total) {
		$this->load->language('extension/payment/line_of_credit');

//		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_line_of_credit_geo_zone_id') . "' AND country_id = '" . (int)($address['country_id'] ?? 0) . "' AND (zone_id = '" . (int)($address['zone_id'] ?? 0) . "' OR zone_id = '0')");

		if ($this->config->get('payment_line_of_credit_total') > 0 && $this->config->get('payment_line_of_credit_total') > $total) {
			$status = false;
		} elseif (!$this->config->get('payment_line_of_credit_geo_zone_id')) {
			$status = true;
		} else {
			$status = false;
		}

		$method_data = array();

		if ($status) {
			$method_data = array(
				'code'       => 'line_of_credit',
				'title'      => $this->language->get('text_title'),
				'terms'      => '',
				'sort_order' => $this->config->get('payment_line_of_credit_sort_order')
			);
		}

		return $method_data;
	}

    public function saveAmendantRecord($updateDate)
    {
        // 获取序列号
        $serialNumber = SerialNumberRepository::getDateSerialNumber(ServiceEnum::AMENDMENT_RECORD_NO);
        $sql = "INSERT INTO tb_sys_credit_line_amendment_record SET serial_number = " . $serialNumber . ",customer_id = " . (int)$updateDate['customerId'] . ",old_line_of_credit=" . (double)$updateDate['oldBalance'] . ",new_line_of_credit=" . (double)$updateDate['balance'] . ",date_added=now(),operator_id=" . (int)$updateDate['operatorId'] ;
        if(!$updateDate['typeId']){
            $sql .=",type_id = 1";
        }else if($updateDate['typeId'] == 2){
            $sql .=",type_id = 2,header_id = ".$updateDate['orderId'];
        }else{
            $sql .=",type_id = {$updateDate['typeId']},header_id = ".$updateDate['orderId'];
        }
        $this->db->query($sql);
    }
}
