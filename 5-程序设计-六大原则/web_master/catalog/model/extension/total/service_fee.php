<?php
class ModelExtensionTotalServiceFee extends Model {
	public function getTotal($total) {
		$this->load->language('extension/total/service_fee');
		if ($this->customer->isLogged()) {
		    if ($this->country->isEuropeCountry($this->customer->getCountryId())) {
		        //modified by xiesensen 服务费修改为total-subtotal
                /*$servicePrice =  $total['total'] * (1 - 0.85 / 2);*/
                $subTotalvalue = 0;
                foreach($total['totals'] as $totaldata){
                    if($totaldata['code'] == 'sub_total'){
                        $subTotalvalue = $totaldata['value'];
                    }
                }
                $servicePrice =  $total['total'] - $subTotalvalue;
//                $servicePrice = substr(sprintf("%.3f", $servicePrice), 0, -1);
                $total['totals'][] = array(
                    'code'       => 'service_fee',
                    'title'      => $this->language->get('text_service_fee'),
                    'value'      => max(0, $servicePrice),
                    'sort_order' => $this->config->get('total_service_fee_sort_order')
                );
            }
        }
	}

    public function getTotalByCartId($total, $products = [], $params = []) {
        $this->load->language('extension/total/service_fee');

        if ($this->customer->isLogged()) {
            if ($this->country->isEuropeCountry($this->customer->getCountryId())) {

                $subTotalvalue = 0;
                foreach($total['totals'] as $totaldata){
                    if($totaldata['code'] == 'sub_total'){
                        $subTotalvalue = $totaldata['value'];
                    }
                }
                $servicePrice =  $total['total'] - $subTotalvalue;

                $total['totals'][] = array(
                    'code'       => 'service_fee',
                    'title'      => $this->language->get('text_service_fee'),
                    'value'      => max(0, $servicePrice),
                    'sort_order' => $this->config->get('total_service_fee_sort_order')
                );
            }
        }
    }
    public function getTotalByProducts($total, $products = [], $params = [])
    {
        $this->getTotalByCartId($total, $products);
    }

    public function getTotalByOrderId($total, $orderId) {
        $this->load->language('extension/total/service_fee');
        if ($this->customer->isLogged() && $this->country->isEuropeCountry($this->customer->getCountryId())) {
            $servicePrice = $this->orm->table('oc_order_total')
                ->where([
                    'order_id'  => $orderId,
                    'code'      => 'service_fee'
                ])
                ->value('value');

            $total['totals'][] = array(
                'code'       => 'service_fee',
                'title'      => $this->language->get('text_service_fee'),
                'value'      => max(0, $servicePrice),
                'sort_order' => $this->config->get('total_service_fee_sort_order')
            );
        }
    }

    public function getDefaultTotal($total) {
        $this->load->language('extension/total/service_fee');
        if ($this->customer->isLogged()) {
            if ($this->country->isEuropeCountry($this->customer->getCountryId())) {

                $total['totals'][] = array(
                    'code'       => 'service_fee',
                    'title'      => $this->language->get('text_service_fee'),
                    'value'      => 0,
                    'sort_order' => $this->config->get('total_service_fee_sort_order')
                );
            }
        }
    }
}
