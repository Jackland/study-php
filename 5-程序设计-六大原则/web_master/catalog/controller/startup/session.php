<?php

class ControllerStartupSession extends Controller
{
    public function index()
    {
        if (isset($this->request->get['route']) && (substr($this->request->get['route'], 0, 4) == 'api/' || strcmp($this->request->get['route'],'message/seller/sendMessageBatch') === 0)) {
            // load 基类
            if($this->checkApiRoute(Request('route')))
            {
                $this->load->controller('api/base');
            }else{
                if ($this->request->get['route'] != "api/login") {
                    $this->db->query("DELETE FROM `" . DB_PREFIX . "api_session` WHERE TIMESTAMPADD(HOUR, 1, date_modified) < NOW()");

                    // Make sure the IP is allowed
                    $sql = "SELECT DISTINCT * FROM `" . DB_PREFIX . "api` `a` LEFT JOIN `" . DB_PREFIX . "api_session` `as` ON (a.api_id = as.api_id) LEFT JOIN " . DB_PREFIX . "api_ip `ai` ON (a.api_id = ai.api_id) WHERE a.status = '1' AND `as`.`session_id` = '" . $this->db->escape(request('api_token', '')) . "' AND ai.ip = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "'";
                    $api_query = $this->db->query($sql);

                    if ($api_query->num_rows) {
                        $this->session->start($this->request->get['api_token']);

                        // keep the session alive
                        $this->db->query("UPDATE `" . DB_PREFIX . "api_session` SET `date_modified` = NOW() WHERE `api_session_id` = '" . (int)$api_query->row['api_session_id'] . "'");
                    }

                }
            }
        }
    }

    private function checkApiRoute($route): bool
    {
        $config = [
            'api/dropship',
            'api/order/seller_order',
            'api/order/order_invoice',
            'api/order/futures',
            'api/storage_fee',
            'api/sales_order',
            'api/buyer_seller',
            'api/seller_asset',
            'message/seller/sendMessageBatch',
            'api/future/agreement_delivery_timeout',
            'api/inventory/getUnBindStock',
            'api/order/europe_freight/freight',
            'api/seller_product_ratio/updateRatio',
        ];

        foreach($config as $items){
            $length = mb_strlen($items);
            if(strtolower(substr($route,0,$length)) == strtolower($items)){
                return true;
            }
        }

        return  false;
    }

}
