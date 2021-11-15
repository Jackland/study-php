<?php

/**
 * Class ModelCatalogwkquotesadmin
 */
class ModelCatalogwkquotesadmin extends Model
{

    private $data;
    private $message_to_customer = 'message_to_customer';

    public function createTableQuote()
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "product_quote` (
	                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,			                        
	                        `customer_id` varchar(50) NOT NULL ,			                        
	                        `product_id` int(100) NOT NULL ,
	                        `product_key` varchar(2000) NOT NULL ,
	                        `quantity` varchar(10) NOT NULL ,
	                      	`message` varchar(500) NOT NULL ,
	                        `price` varchar(40) NOT NULL ,
	                        `status` int(10) NOT NULL ,
	                        `date_added` varchar(100) NOT NULL ,
	                        `order_id` int(100) NOT NULL ,
	                        `amount` varchar(100) NOT NULL ,
	                        `date_used` varchar(100) NOT NULL ,
	                        PRIMARY KEY (`id`) ) DEFAULT CHARSET=utf8 ;"
        );

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "product_quote_message` (
	                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	                        `quote_id` INT(50) NOT NULL ,
	                        `writer` varchar(500) NOT NULL ,
	                        `message` varchar(5000) NOT NULL ,
	                        `date` varchar(500) NOT NULL ,
	                        `attachment` varchar(500) NOT NULL ,  
	                        PRIMARY KEY (`id`) ) DEFAULT CHARSET=utf8 ;"
        ); 
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wk_pro_quote_seller` (
                            `seller_id` INT(50) NOT NULL ,
                            `products` varchar(2000),
                            `quantity` int(11),
                            `status` INT(2) NOT NULL ) ;"
        );
    }

    public function viewtotal($data)
    {
        $sql = "SELECT CONCAT(c.firstname,' ',c.lastname) customer_name,c.email,pd.name,pd.product_id,pq.*,p.price as baseprice FROM " . DB_PREFIX . "product_quote pq LEFT JOIN " . DB_PREFIX . "customer c ON (c.customer_id = pq.customer_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (pd.product_id = pq.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (p.product_id = pq.product_id) WHERE p.status=1 AND pd.language_id = '".$this->config->get('config_language_id')."'";        

        $implode = array();

        if (!empty($data['filter_id'])) {
            $implode[] = "pq.id = '" . (int)$data['filter_id'] . "'";
        }

        if (!empty($data['filter_customer'])) {
            $implode[] = " CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . $this->db->escape($data['filter_customer']) . "%'";
        }

        if (isset($data['filter_product']) && !is_null($data['filter_product'])) {
            $implode[] = "LCASE(pd.name) LIKE '" . $this->db->escape(utf8_strtolower($data['filter_product'])) . "%'";
        }

        if (isset($data['filter_qty']) && !is_null($data['filter_qty'])) {
            $implode[] = "pq.quantity = '" . (int)$data['filter_qty'] . "'";
        }

        if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
            $implode[] = "pq.status = '" . (int)$data['filter_status'] . "'";
        }

        if (isset($data['filter_price']) && !is_null($data['filter_price'])) {            
            $implode[] = " pq.price = '" . (float)$data['filter_price'] . "'";
        }

        if (!empty($data['filter_date'])) {
            $implode[] = "LCASE(pq.date_added) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
        }

        if ($implode) {
            $sql .= " AND " . implode(" AND ", $implode);
        }

        $sort_data = array(
         'pq.id',
         'c.firstname',
         'pq.product_id',
         'pq.price',
         'pq.status',
         'pq.quantity',            
         'pq.date_added',    
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];    
        } else {
            $sql .= " ORDER BY pq.id";    
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

    public function viewtotalentry($data)
    {

        $sql = "SELECT CONCAT(c.firstname,' ',c.lastname) customer_name,c.email,pd.name,pd.product_id,pq.*,p.price as baseprice FROM " . DB_PREFIX . "product_quote pq LEFT JOIN " . DB_PREFIX . "customer c ON (c.customer_id = pq.customer_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (pd.product_id = pq.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (p.product_id = pq.product_id) WHERE p.status=1 AND pd.language_id = '".$this->config->get('config_language_id')."'";        

        $implode = array();

        if (!empty($data['filter_id'])) {
            $implode[] = "pq.id = '" . (int)$data['filter_id'] . "'";
        }

        if (!empty($data['filter_customer'])) {
            $implode[] = " CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . $this->db->escape($data['filter_customer']) . "%'";
        }

        if (isset($data['filter_product']) && !is_null($data['filter_product'])) {
            $implode[] = "LCASE(pd.name) LIKE '" . $this->db->escape(utf8_strtolower($data['filter_product'])) . "%'";
        }

        if (isset($data['filter_qty']) && !is_null($data['filter_qty'])) {
            $implode[] = "pq.quantity = '" . (int)$data['filter_qty'] . "'";
        }

        if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
            $implode[] = "pq.status = '" . (int)$data['filter_status'] . "'";
        }

        if (isset($data['filter_price']) && !is_null($data['filter_price'])) {            
            $implode[] = " pq.price = '" . (float)$data['filter_price'] . "'";
        }

        if (!empty($data['filter_date'])) {
            $implode[] = "LCASE(pq.date_added) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
        }

        if ($implode) {
            $sql .= " AND " . implode(" AND ", $implode);
        }

        $result = $this->db->query($sql);

        return count($result->rows);
    }
    
    public function deleteentry($id)
    {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_quote WHERE id='".(int)$id."'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_quote_message WHERE quote_id='".(int)$id."'");
    }
    
    public function viewQuoteByid($id)
    {
        $sql = "SELECT 
CONCAT(c.firstname,' ',c.lastname) as customer_name,
CONCAT(s.firstname,' ', s.lastname ) as seller_name,
ctp.customer_id as seller_id,
c.email,pd.name,pq.*,p.price as baseprice,p.image 
FROM " . DB_PREFIX . "product_quote pq 
LEFT JOIN " . DB_PREFIX . "customer c ON (c.customer_id = pq.customer_id) 
LEFT JOIN " . DB_PREFIX . "product_description pd ON (pd.product_id = pq.product_id) 
LEFT JOIN " . DB_PREFIX . "product p ON (p.product_id = pq.product_id) 
left join oc_customerpartner_to_product as ctp on ctp.product_id = pq.product_id
left join oc_customer as s on s.customer_id = ctp.customer_id
WHERE p.status=1 AND pd.language_id = ".$this->config->get('config_language_id')." AND pq.id = ".(int)$id;
        return $this->db->query($sql)->row;
    }

    public function viewtotalMessageBy($data)
    {

        $sql = "SELECT CONCAT(c.firstname,' ',c.lastname) name,pqm.* FROM " . DB_PREFIX . "product_quote_message pqm LEFT JOIN " . DB_PREFIX . "customer c ON (pqm.writer = c.customer_id) WHERE pqm.quote_id = '".(int)$data['filter_id']."'";

        $implode = array();

        if ($data['filter_name']!='') {
            $implode[] = "pqm.writer = '" . (int)$data['filter_name'] . "'";
        }

        if (isset($data['filter_message']) && !empty($data['filter_message'])) {
            $implode[] = "LCASE(pqm.message) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_message'])) . "%'";
        }

        if (isset($data['filter_date']) && !empty($data['filter_date'])) {
            $implode[] = "LCASE(pqm.date) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
        }

        if ($implode) {
            $sql .= " AND " . implode(" AND ", $implode);
        }

        $sort_data = array(
         'pqm.writer',
         'pqm.message',                    
         'pqm.date',    
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];    
        } else {
            $sql .= " ORDER BY pqm.id";    
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " ASC";            
        } else {
            $sql .= " DESC";
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

    public function viewtotalNoMessageBy($data)
    {

        $sql = "SELECT COUNT(*) as total FROM " . DB_PREFIX . "product_quote_message pqm LEFT JOIN " . DB_PREFIX . "customer c ON (pqm.writer = c.customer_id)  WHERE pqm.quote_id = ".(int)$data['filter_id'];

        $implode = array();

        if ($data['filter_name']!='') {
            $implode[] = "pqm.writer = '" . (int)$data['filter_name'] . "'";
        }

        if (isset($data['filter_message']) && !empty($data['filter_message'])) {
            $implode[] = "LCASE(pqm.message) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_message'])) . "%'";
        }

        if (isset($data['filter_date']) && !empty($data['filter_date'])) {
            $implode[] = "LCASE(pqm.date) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
        }

        if ($implode) {
            $sql .= " AND " . implode(" AND ", $implode);
        }

        $result = $this->db->query($sql);

        return $result->row['total'];
    }

    public function chk_status($id)
    {
        $result = $this->db->query("SELECT status,coupon FROM " . DB_PREFIX . "product_quote WHERE id = ".(int)$id)->row;

        if ($result) {
            if ($result['coupon']) {
                return false; 
            } else {
                return true; 
            } 
        } else {
            return false; 
        }
    }

    public function updatebyid($data)
    {
        
        $this->db->query("UPDATE " . DB_PREFIX . "product_quote SET status = '".(int)$data['status']."' WHERE id = '".(int)$data['quote_id']."'");

        $this->addQuoteMessage($data);        
    }

    public function addQuoteMessage($data)
    {
        $this->db->query(
            "INSERT INTO " . DB_PREFIX . "product_quote_message SET 			
					              	quote_id = '".(int)$data['quote_id']."',
					                writer = '0',	                        
					              	message = '".$this->db->escape(nl2br($data['message']))."',
					                date = NOW()
					            "
        );

        $this->mail($data);
    }

    public function getCustomer($quote_id)
    {

        $customer = $this->db->query("SELECT c.* FROM `" . DB_PREFIX . "product_quote` pq LEFT JOIN `".DB_PREFIX."customer` c ON (c.customer_id = pq.customer_id) WHERE pq.id = '".(int)$quote_id."'")->row;
        
        return $customer;
    }

    public function mail($data, $mail_type = 'message_to_customer')
    {

        $value_index = array();

        $this->load->language('catalog/quote_mail');
        $this->load->language('catalog/wk_quotes_admin');

        $mail_message = '';

        switch($mail_type){

         //admin send message to customer			
        case $this->message_to_customer :    

            $mail_subject = sprintf($this->language->get($this->message_to_customer .'_subject'), $data['quote_id']);

            $mail_message = nl2br(sprintf($this->language->get($this->message_to_customer.'_message'), $this->language->get('text_status_'.$data['status'])));

            $customer_info = $this->getCustomer($data['quote_id']);
            $mail_to = $customer_info['email'];
            $mail_from = $this->config->get('wk_pro_quote_email') ? $this->config->get('wk_pro_quote_email') : $this->config->get('config_email');

            $value_index = array(
             'customer_name' => $customer_info['firstname'].' '.$customer_info['lastname'],
             'admin_message' => nl2br($data['message']),
             'quote_id' => $data['quote_id'],    
             );
            break;        

        default :
            return;
        }


        if ($mail_message) {

            $this->data['store_name'] = $this->config->get('config_name');
            $this->data['store_url'] = HTTP_CATALOG;
            $this->data['logo'] = HTTP_CATALOG.'image/' . $this->config->get('config_logo');    

            $find = array(                
             '{quote_id}',        
             '{customer_name}',
             '{admin_message}',
             '{link}',
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
             'quote_id' => '',
             'customer_name' => '',
             'admin_message' => '',
             'link' => '',
             'config_logo' => '<a href="'.HTTP_CATALOG.'" title="'.$this->data['store_name'].'"><img src="'.HTTP_CATALOG.'image/' . $this->config->get('config_logo').'" alt="'.$this->data['store_name'].'" style="max-width:200px;"/></a>',
             'config_icon' => '<img src="'.HTTP_CATALOG.'image/' . $this->config->get('config_icon').'" style="max-width:200px;">',
             'config_currency' => $this->config->get('config_currency'),
             'config_image' => '<img src="'.HTTP_CATALOG.'image/' . $this->config->get('config_image').'" style="max-width:200px;">',
             'config_name' => $this->config->get('config_name'),
             'config_owner' => $this->config->get('config_owner'),
             'config_address' => $this->config->get('config_address'),
             'config_geocode' => $this->config->get('config_geocode'),
             'config_email' => $this->config->get('config_email'),
             'config_telephone' => $this->config->get('config_telephone'),
            );

            $replace = array_merge($replace, $value_index);            

            $mail_message = trim(str_replace($find, $replace, $mail_message));

            $this->data['subject'] = $mail_subject;
            $this->data['message'] = $mail_message;    

            $html = $this->load->view('catalog/quote_mail', $this->data);

            $mail_sender = $this->config->get('config_name') ? $this->config->get('config_name') : HTTP_CATALOG;
            
            if (preg_match('/^[^\@]+@.*\.[a-z]{2,6}$/i', $mail_to) AND preg_match('/^[^\@]+@.*\.[a-z]{2,6}$/i', $mail_from) ) {

                  $mail = new Mail();
                   $mail->setTo($mail_to);
                   $mail->setFrom($mail_from);
                   $mail->setSender($mail_sender);
                   $mail->setSubject($mail_subject);
                if (isset($mail_attachment)) {
                    $mail->addAttachment($mail_attachment); 
                }
                   $mail->protocol = $this->config->get('config_mail_protocol');
                   $mail->parameter = $this->config->get('config_mail_parameter');
                   $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
                   $mail->smtp_username = $this->config->get('config_mail_smtp_username');
                   $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
                   $mail->smtp_port = $this->config->get('config_mail_smtp_port');
                   $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
                   $mail->setHtml($html);
                   $mail->setText(strip_tags($html));
                   $mail->send();

            }

        }
    }

    public function getProductOptions($options,$product_id)
    {    
        $option_price = 0;
        $option_points = 0;
        $option_weight = 0;

        $option_data = array();

        foreach ($options as $product_option_id => $value) {
            $option_query = $this->db->query("SELECT po.product_option_id, po.option_id, od.name, o.type FROM " . DB_PREFIX . "product_option po LEFT JOIN `" . DB_PREFIX . "option` o ON (po.option_id = o.option_id) LEFT JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id) WHERE po.product_option_id = '" . (int)$product_option_id . "' AND po.product_id = '" . (int)$product_id . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "'");

            if ($option_query->num_rows) {
                if ($option_query->row['type'] == 'select' || $option_query->row['type'] == 'radio' || $option_query->row['type'] == 'image') {
                    $option_value_query = $this->db->query("SELECT pov.option_value_id, ovd.name, pov.quantity, pov.subtract, pov.price, pov.price_prefix, pov.points, pov.points_prefix, pov.weight, pov.weight_prefix FROM " . DB_PREFIX . "product_option_value pov LEFT JOIN " . DB_PREFIX . "option_value ov ON (pov.option_value_id = ov.option_value_id) LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE pov.product_option_value_id = '" . (int)$value . "' AND pov.product_option_id = '" . (int)$product_option_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

                    if ($option_value_query->num_rows) {
                        if ($option_value_query->row['price_prefix'] == '+') {
                            $option_price += $option_value_query->row['price'];
                        } elseif ($option_value_query->row['price_prefix'] == '-') {
                            $option_price -= $option_value_query->row['price'];
                        }

                        if ($option_value_query->row['points_prefix'] == '+') {
                            $option_points += $option_value_query->row['points'];
                        } elseif ($option_value_query->row['points_prefix'] == '-') {
                            $option_points -= $option_value_query->row['points'];
                        }

                        if ($option_value_query->row['weight_prefix'] == '+') {
                            $option_weight += $option_value_query->row['weight'];
                        } elseif ($option_value_query->row['weight_prefix'] == '-') {
                            $option_weight -= $option_value_query->row['weight'];
                        }

                        // if ($option_value_query->row['subtract'] && (!$option_value_query->row['quantity'] || ($option_value_query->row['quantity'] < $quantity))) {
                        // 	$stock = false;
                        // }

                        $option_data[] = array(
                         'product_option_id'       => $product_option_id,
                         'product_option_value_id' => $value,
                         'option_id'               => $option_query->row['option_id'],
                         'option_value_id'         => $option_value_query->row['option_value_id'],
                         'name'                    => $option_query->row['name'],
                         'value'                   => $option_value_query->row['name'],
                         'type'                    => $option_query->row['type'],
                         'quantity'                => $option_value_query->row['quantity'],
                         'subtract'                => $option_value_query->row['subtract'],
                         'price'                   => $option_value_query->row['price'],
                         'price_prefix'            => $option_value_query->row['price_prefix'],
                         'points'                  => $option_value_query->row['points'],
                         'points_prefix'           => $option_value_query->row['points_prefix'],
                         'weight'                  => $option_value_query->row['weight'],
                         'weight_prefix'           => $option_value_query->row['weight_prefix']
                        );
                    }
                } elseif ($option_query->row['type'] == 'checkbox' && is_array($value)) {
                    foreach ($value as $product_option_value_id) {
                        $option_value_query = $this->db->query("SELECT pov.option_value_id, ovd.name, pov.quantity, pov.subtract, pov.price, pov.price_prefix, pov.points, pov.points_prefix, pov.weight, pov.weight_prefix FROM " . DB_PREFIX . "product_option_value pov LEFT JOIN " . DB_PREFIX . "option_value ov ON (pov.option_value_id = ov.option_value_id) LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE pov.product_option_value_id = '" . (int)$product_option_value_id . "' AND pov.product_option_id = '" . (int)$product_option_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

                        if ($option_value_query->num_rows) {
                            if ($option_value_query->row['price_prefix'] == '+') {
                                $option_price += $option_value_query->row['price'];
                            } elseif ($option_value_query->row['price_prefix'] == '-') {
                                $option_price -= $option_value_query->row['price'];
                            }

                            if ($option_value_query->row['points_prefix'] == '+') {
                                $option_points += $option_value_query->row['points'];
                            } elseif ($option_value_query->row['points_prefix'] == '-') {
                                $option_points -= $option_value_query->row['points'];
                            }

                            if ($option_value_query->row['weight_prefix'] == '+') {
                                $option_weight += $option_value_query->row['weight'];
                            } elseif ($option_value_query->row['weight_prefix'] == '-') {
                                $option_weight -= $option_value_query->row['weight'];
                            }

                            // if ($option_value_query->row['subtract'] && (!$option_value_query->row['quantity'] || ($option_value_query->row['quantity'] < $quantity))) {
                            // 	$stock = false;
                            // }

                            $option_data[] = array(
                             'product_option_id'       => $product_option_id,
                             'product_option_value_id' => $product_option_value_id,
                             'option_id'               => $option_query->row['option_id'],
                             'option_value_id'         => $option_value_query->row['option_value_id'],
                             'name'                    => $option_query->row['name'],
                             'value'                   => $option_value_query->row['name'],
                             'type'                    => $option_query->row['type'],
                             'quantity'                => $option_value_query->row['quantity'],
                             'subtract'                => $option_value_query->row['subtract'],
                             'price'                   => $option_value_query->row['price'],
                             'price_prefix'            => $option_value_query->row['price_prefix'],
                             'points'                  => $option_value_query->row['points'],
                             'points_prefix'           => $option_value_query->row['points_prefix'],
                             'weight'                  => $option_value_query->row['weight'],
                             'weight_prefix'           => $option_value_query->row['weight_prefix']
                            );
                        }
                    }
                } elseif ($option_query->row['type'] == 'text' || $option_query->row['type'] == 'textarea' || $option_query->row['type'] == 'file' || $option_query->row['type'] == 'date' || $option_query->row['type'] == 'datetime' || $option_query->row['type'] == 'time') {
                    $option_data[] = array(
                     'product_option_id'       => $product_option_id,
                     'product_option_value_id' => '',
                     'option_id'               => $option_query->row['option_id'],
                     'option_value_id'         => '',
                     'name'                    => $option_query->row['name'],
                     'value'                   => $value,
                     'type'                    => $option_query->row['type'],
                     'quantity'                => '',
                     'subtract'                => '',
                     'price'                   => '',
                     'price_prefix'            => '',
                     'points'                  => '',
                     'points_prefix'           => '',
                     'weight'                  => '',
                     'weight_prefix'           => ''
                    );
                }
            }
        }

        return $option_data;
    }
    public function deleteTableQuote() 
    {
        $this->db->query("DROP TABLE ".DB_PREFIX."product_quote");
        $this->db->query("DROP TABLE ".DB_PREFIX."product_quote_message");
        $this->db->query("DROP TABLE ".DB_PREFIX."wk_pro_quote_seller");
    }
}
?>