<?php
class ModelAccountProductQuotesMail extends Model
{
    

    private $data;

    private $generate_quote_to_admin = 'generate_quote_to_admin';
    private $generate_quote_to_seller = 'generate_quote_to_seller';
    private $generate_quote_to_customer = 'generate_quote_to_customer';
    private $message_to_admin = 'message_to_admin';
    private $message_to_seller = 'message_to_seller';

    public function getCustomer($id)
    {
        $customer = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer WHERE customer_id = '".(int)$id."'")->row;
        return $customer;
    }

    public function mail($data, $mail_type = '',$seller_email='')
    {

        $value_index = array();

        $this->load->language('account/product_quotes/mail');

        $mail_message = '';

        switch($mail_type){

         //customer added Quote
        case $this->generate_quote_to_customer ://to customer

            $mail_subject = sprintf($this->language->get($this->generate_quote_to_customer .'_subject'), $data['product_name']);
            $mail_message = nl2br($this->language->get($this->generate_quote_to_customer.'_message'));

            $customer_info = $this->getCustomer($this->customer->getId());
            $mail_to = $customer_info['email'];
            $mail_from = $this->config->get('wk_pro_quote_email') ? $this->config->get('wk_pro_quote_email') : $this->config->get('config_email');

            $value_index = array(
             'customer_name' => $customer_info['firstname'].' '.$customer_info['lastname'],
             'quote_id' => $data['quote_id'],    
             'product_name' => $data['product_name'],       
             'quote_price' => $data['quote_price'],    
             'quote_quantity' => $data['quote_quantity'],    
             'link' => $this->url->link('account/product_quotes/wk_quote_my/view&id='.$data['quote_id'], '', true)
             );    
            break;        

        case $this->generate_quote_to_admin ://to admin	

            $mail_subject = sprintf($this->language->get($this->generate_quote_to_admin .'_subject'), $data['product_name']);
            $mail_message = nl2br($this->language->get($this->generate_quote_to_admin.'_message'));

            $customer_info = $this->getCustomer($this->customer->getId());

            $mail_to = $this->config->get('wk_pro_quote_email') ? $this->config->get('wk_pro_quote_email') : $this->config->get('config_email');
            $mail_from = $customer_info['email'];

            $value_index = array(                                    
             'customer_name' => $customer_info['firstname'].' '.$customer_info['lastname'],
             'quote_id' => $data['quote_id'],    
             'customer_message' => $data['quote_message'],    
             'product_name' => $data['product_name'],       
             'quote_price' => $data['quote_price'],    
             'quote_quantity' => $data['quote_quantity'],    
             );
            break;    
        case $this->generate_quote_to_seller:
            $mail_subject = sprintf($this->language->get($this->generate_quote_to_admin .'_subject'), $data['product_name']);
            $mail_message = nl2br($this->language->get($this->generate_quote_to_admin.'_message'));

            $customer_info = $this->getCustomer($this->customer->getId());

            $mail_to = $seller_email;
            $mail_from = $customer_info['email'];

            $value_index = array(                                    
             'customer_name' => $customer_info['firstname'].' '.$customer_info['lastname'],
             'quote_id' => $data['quote_id'],    
             'customer_message' => $data['quote_message'],    
             'product_name' => $data['product_name'],    
     
             'quote_quantity' => $data['quote_quantity'],    
             );
            break; 
         //customer send message to admin			
        case $this->message_to_admin :    

            $mail_subject = sprintf($this->language->get($this->message_to_admin .'_subject'), $data['quote_id']);

            $mail_message = nl2br($this->language->get($this->message_to_admin.'_message'));

            $customer_info = $this->getCustomer($this->customer->getId());
            $mail_to = $this->config->get('wk_pro_quote_email') ? $this->config->get('wk_pro_quote_email') : $this->config->get('config_email');
            $mail_from = $customer_info['email'];

            $value_index = array(
             'customer_name' => $customer_info['firstname'].' '.$customer_info['lastname'],
             'customer_message' => nl2br($data['message']),
             'quote_id' => $data['quote_id'],
             'quote_price' => isset($data['quote_price']) ? $data['quote_price'] : '',
             'quote_quantity' => isset($data['quote_quantity']) ? $data['quote_quantity'] : '',    
             );
            break;            
            case $this->message_to_seller :    

            $mail_subject = sprintf($this->language->get($this->message_to_seller .'_subject'), $data['quote_id']);

            $mail_message = nl2br($this->language->get($this->message_to_seller.'_message'));

            $customer_info = $this->getCustomer($this->customer->getId());
            $mail_to = $seller_email;
            $mail_from = $customer_info['email'];

            $value_index = array(
             'customer_name' => $customer_info['firstname'].' '.$customer_info['lastname'],
             'customer_message' => nl2br($data['message']),
             'quote_id' => $data['quote_id'],
             'quote_price' => isset($data['quote_price']) ? $data['quote_price'] : '',
             'quote_quantity' => isset($data['quote_quantity']) ? $data['quote_quantity'] : '', 
             'seller_name'    => isset($data['seller_name']) ? $data['seller_name'] : ''
             );
            break;  
        default :
            return;
        }


        if ($mail_message) {

            $this->data['store_name'] = $this->config->get('config_name');
            $this->data['store_url'] = HTTP_SERVER;
            $this->data['logo'] = HTTP_SERVER.'image/' . $this->config->get('config_logo');    

            $find = array(                
             '{quote_id}',        
             '{customer_name}',
             '{product_name}',
             '{product_price}',        
             '{quote_price}',        
             '{quote_quantity}',        
             '{customer_message}',
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
             '{seller_name}'
             );

            $replace = array(            
             'quote_id' => '',
             'customer_name' => '',
             'product_name' => '',
             'product_price' => '',
             'quote_price' => '',
             'quote_quantity' => '',
             'customer_message' => '',
             'link' => '',
             'config_logo' => '<a href="'.HTTP_SERVER.'" title="'.$this->data['store_name'].'"><img src="'.HTTP_SERVER.'image/' . $this->config->get('config_logo').'" alt="'.$this->data['store_name'].'" style="max-width:200px;"/></a>',
             'config_icon' => '<img src="'.HTTP_SERVER.'image/' . $this->config->get('config_icon').'" style="max-width:200px;">',
             'config_currency' => $this->config->get('config_currency'),
             'config_image' => '<img src="'.HTTP_SERVER.'image/' . $this->config->get('config_image').'" style="max-width:200px;">',
             'config_name' => $this->config->get('config_name'),
             'config_owner' => $this->config->get('config_owner'),
             'config_address' => $this->config->get('config_address'),
             'config_geocode' => $this->config->get('config_geocode'),
             'config_email' => $this->config->get('config_email'),
             'config_telephone' => $this->config->get('config_telephone'),
             'seller_name'  => ''
            );

            $replace = array_merge($replace, $value_index);            

            $mail_message = trim(str_replace($find, $replace, $mail_message));

            $this->data['subject'] = $mail_subject;
            $this->data['message'] = $mail_message;    
            if (version_compare(VERSION, '2.2.0.0', '>=')) {
                   $html = $this->load->view('account/product_quotes/mail', $this->data);    
            } else {
                     $html = $this->load->view('default/template/account/product_quotes/mail', $this->data);
            }
            
            $mail_sender = $this->config->get('config_name') ? $this->config->get('config_name') : HTTP_SERVER;

            if (preg_match('/^[^\@]+@.*\.[a-z]{2,6}$/i', $mail_to) AND preg_match('/^[^\@]+@.*\.[a-z]{2,6}$/i', $mail_from) ) {

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
                     $mail->setSender($mail_sender);
                     $mail->setSubject($mail_subject);
                     $mail->setHtml($html);
                     $mail->setText(strip_tags($html));
                     $mail->send();
            }

        }
    }

}
?>