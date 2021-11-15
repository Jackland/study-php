<?php

use App\Repositories\Product\ProductPriceRepository;

/**
 *
 * @property ModelCustomerpartnerSpotPrice $model_customerpartner_spot_price
 * @property ModelMessageMessage $model_message_message
 *
 * Class ModelAccountwkquotesadmin
 */
class ModelAccountwkquotesadmin extends Model
{

    private $data;
    private $message_to_customer = 'message_to_customer';

    public function viewtotal($data)
    {
        $sql = "SELECT CONCAT(c.nickname,'(',c.user_number,')') customer_name,c.email,c.customer_group_id,
pd.name,pd.product_id,pq.*,p.price as baseprice,p.sku,p.mpn
FROM " . DB_PREFIX . "product_quote pq LEFT JOIN " . DB_PREFIX . "customer c ON (c.customer_id = pq.customer_id)
LEFT JOIN " . DB_PREFIX . "product_description pd ON (pd.product_id = pq.product_id)
LEFT JOIN " . DB_PREFIX . "product p ON (p.product_id = pq.product_id)
LEFT JOIN ".DB_PREFIX."customerpartner_to_product cp ON(cp.product_id=p.product_id)
WHERE  pd.language_id = '".$this->config->get('config_language_id')."' AND cp.customer_id='".$this->customer->getID()."'";
        $implode = array();

        if (!empty($data['filter_id'])) {
            $implode[] = "(pq.agreement_no like '%" . (int)$data['filter_id'] . "%' or pq.id like '%" . (int)$data['filter_id'] . "%')";
        }

        if (!empty($data['filter_customer'])) {
            $implode[] = " (c.nickname LIKE '%" . $this->db->escape($data['filter_customer']) . "%' OR c.user_number LIKE '%" . $this->db->escape($data['filter_customer']) . "%')";
        }

        if (isset($data['filter_product']) && !is_null($data['filter_product']) && !empty($data['filter_product'])) {
            $implode[] = "LCASE(pd.name) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_product'])) . "%'";
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

        if (isset($data['filter_date_from']) && !empty($data['filter_date_from'])) {
            $implode[] = " pq.date_added >='" . $this->db->escape(utf8_strtolower($data['filter_date_from']))."'";
        }
        if (isset($data['filter_date_to']) && !empty($data['filter_date_to'])) {
            $implode[] = " pq.date_added <='" . $this->db->escape(utf8_strtolower($data['filter_date_to']))."'";
        }

        if (isset($data['filter_sku_mpn']) && !empty($data['filter_sku_mpn'])) {
            $implode[] = " (p.sku like '%" . $this->db->escape(utf8_strtolower($data['filter_sku_mpn']))."%' or p.mpn like '%"
                .$this->db->escape(utf8_strtolower($data['filter_sku_mpn']))."%')";
        }

        if ($implode) {
            $sql .= " AND " . implode(" AND ", $implode);
        }

        $sort_data = array(
         'pd.name',
         'c.firstname',
         'c.nickname',
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

        $sql = "SELECT count(*) as total FROM " . DB_PREFIX . "product_quote pq LEFT JOIN " . DB_PREFIX . "customer c ON (c.customer_id = pq.customer_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (pd.product_id = pq.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (p.product_id = pq.product_id) LEFT JOIN ".DB_PREFIX."customerpartner_to_product cp ON(cp.product_id=p.product_id) WHERE pd.language_id = '".$this->config->get('config_language_id')."' AND cp.customer_id='".$this->customer->getID()."'";

        $implode = array();

        if (!empty($data['filter_id'])) {
            $implode[] = "(pq.agreement_no like '%" . (int)$data['filter_id'] . "%' or pq.id like '%" . (int)$data['filter_id'] . "%')";
        }

        if (!empty($data['filter_customer'])) {
            $implode[] = " (c.nickname LIKE '%" . $this->db->escape($data['filter_customer']) . "%' OR c.user_number LIKE '%" . $this->db->escape($data['filter_customer']) . "%')";
        }

        if (isset($data['filter_product']) && !is_null($data['filter_product']) && !empty($data['filter_product'])) {
            $implode[] = "LCASE(pd.name) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_product'])) . "%'";
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

        if (isset($data['filter_date_from']) && !empty($data['filter_date_from'])) {
            $implode[] = " pq.date_added >='" . $this->db->escape(utf8_strtolower($data['filter_date_from']))."'";
        }
        if (isset($data['filter_date_to']) && !empty($data['filter_date_to'])) {
            $implode[] = " pq.date_added <='" . $this->db->escape(utf8_strtolower($data['filter_date_to']))."'";
        }

        if (isset($data['filter_sku_mpn']) && !empty($data['filter_sku_mpn'])) {
            $implode[] = " (p.sku like '%" . $this->db->escape(utf8_strtolower($data['filter_sku_mpn']))."%' or p.mpn like '%"
                .$this->db->escape(utf8_strtolower($data['filter_sku_mpn']))."%')";
        }

        if ($implode) {
            $sql .= " AND " . implode(" AND ", $implode);
        }

        $result = $this->db->query($sql);

        return $result->row['total'];
    }

    public function deleteentry($id)
    {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_quote WHERE id='" . (int)$id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_quote_message WHERE quote_id='" . (int)$id . "'");
    }

    //验证议价是否属于custom
    public function check_quota($quota_id)
    {
        $quota_id = (int)$quota_id;
        return $this->db->query("select cp.customer_id from " . DB_PREFIX . "product_quote as pq left join " . DB_PREFIX . "customerpartner_to_product as cp on pq.product_id=cp.product_id where pq.id=$quota_id")->row;
    }

    /**
     * @param int $id
     * @return array
     */
    public function viewQuoteByid($id)
    {
        $obj = $this->orm->table('oc_product_quote as pq')
            ->join('oc_customer as b', 'b.customer_id', '=', 'pq.customer_id')
            ->leftJoin('oc_product_description as pd', 'pd.product_id', '=', 'pq.product_id')
            ->join('oc_product as p', 'p.product_id', '=', 'pq.product_id')
            ->where('pq.id', '=', $id)
            ->select([
                'b.email', 'b.customer_group_id',
                'pd.name', 'p.price as baseprice', 'p.image', 'p.freight', 'p.package_fee', 'pq.agreement_no'
            ])
            ->selectRaw("CONCAT(b.nickname,'(',b.user_number,')') as customer_name,pq.*")
            ->first();
        return empty($obj) ? [] : obj2array($obj);
    }

    public function viewtotalMessageBy($data)
    {

        $sql = "SELECT CONCAT(c.nickname,'(',c.user_number,')') name,ctc.screenname,pqm.* FROM " . DB_PREFIX . "product_quote_message pqm LEFT JOIN " . DB_PREFIX . "customer c ON (pqm.writer = c.customer_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer ctc ON ctc.customer_id = c.customer_id   WHERE pqm.quote_id = " . (int)$data['filter_id'];
        $implode = array();

        if ($data['filter_name'] != '') {
            $implode[] = "pqm.writer = '" . (int)$data['filter_name'] . "'";
        }

        if (isset($data['filter_message']) && !empty($data['filter_message'])) {
            $implode[] = "LCASE(pqm.message) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_message'])) . "%'";
        }

        if (isset($data['filter_date_from']) && !empty($data['filter_date_from'])) {
            $implode[] = "pqm.date >='" . $this->db->escape(utf8_strtolower($data['filter_date_from'])) . "'";
        }
        if (isset($data['filter_date_to']) && !empty($data['filter_date_to'])) {
            $implode[] = "pqm.date <= '" . $this->db->escape(utf8_strtolower($data['filter_date_to'])) . "'";
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

        $sql = "SELECT CONCAT(c.firstname,' ',c.lastname) name,pqm.* FROM " . DB_PREFIX . "product_quote_message pqm LEFT JOIN " . DB_PREFIX . "customer c ON (pqm.writer = c.customer_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer ctc ON ctc.customer_id = c.customer_id  WHERE pqm.quote_id = '".(int)$data['filter_id']."'";

        $implode = array();

        if ($data['filter_name']!='') {
            $implode[] = "pqm.writer = '" . (int)$data['filter_name'] . "'";
        }

        if (isset($data['filter_message']) && !empty($data['filter_message'])) {
            $implode[] = "LCASE(pqm.message) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_message'])) . "%'";
        }

        if (isset($data['filter_date_from']) && !empty($data['filter_date_from'])) {
            $implode[] = "pqm.date >='" . $this->db->escape(utf8_strtolower($data['filter_date_from'])) . "'";
        }
        if (isset($data['filter_date_to']) && !empty($data['filter_date_to'])) {
            $implode[] = "pqm.date <= '" . $this->db->escape(utf8_strtolower($data['filter_date_to'])) . "'";
        }

        if ($implode) {
            $sql .= " AND " . implode(" AND ", $implode);
        }

        $result = $this->db->query($sql);

        return count($result->rows);
    }

    public function chk_status($id)
    {
        $result = $this->db->query("SELECT status,coupon FROM " . DB_PREFIX . "product_quote WHERE id = '".(int)$id."'")->row;

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

    /*
     *  获取议价状态
     *   0->Applied 申请,
     *   1->Approved 同意,
     *   2->Rejected 拒绝,
     *   3->Sold 已购买,
     *   4->Time Out 超时关闭,
     *   5->Canceled 用户取消
     */
    public function get_quote_info($quote_id)
    {
        $result = $this->db->query("SELECT status,update_time FROM " . DB_PREFIX . "product_quote WHERE id = '" . (int)$quote_id . "'")->row;
        return $result;
    }


    /**
     * @param array $data
     * @since lester.you 同意议价时，添加操作时间
     */
    public function updatebyid($data)
    {
        if ((int)$data['status'] == 1) {
            $update['date_approved'] = date('Y-m-d H:i:s');
        }
        $update['status'] = (int)$data['status'];
        $this->orm::table('oc_product_quote')
            ->where('id', (int)$data['quote_id'])
            ->update($update);
//        $this->db->query("UPDATE " . DB_PREFIX . "product_quote SET status = ".(int)$data['status']." WHERE id = ".(int)$data['quote_id']);
        $this->addCommunication($data['quote_id'], $data['status']);
        $this->addQuoteMessage($data);
    }

    //处理议价申请后 给buyer发一条system消息
    public function addCommunication($quote_id, $status)
    {
        if (1 == $status){
            $statusStr = 'Approved';
        }elseif(2 == $status){
            $statusStr = 'Rejected';
        }else{
            return;
        }
        $quoteInfo = $this->orm->table('oc_product_quote')
            ->where('id', $quote_id)
            ->first();
        $sellerId = $this->customer->getId();
        if ($quoteInfo){
            $sku = $this->orm->table('oc_product')->where('product_id', $quoteInfo->product_id)->value('sku');
            $store = $this->orm->table('oc_customerpartner_to_customer')->where('customer_id',$sellerId)->value('screenname');
            $storeUrl = $this->url->link('customerpartner/profile', '&id='.$sellerId, true);

            $subject = 'Spot price bid application result of '.$sku.': '.$statusStr.'(Quote ID:'.$quote_id.')';
            $message = '<table   border="0" cellspacing="0" cellpadding="0">';
            $message .= '<tr><th align="left">Quote ID:&nbsp</th><td style="width: 650px">
                          <a href="' . $this->url->link('account/product_quotes/wk_quote_my/view', '&id='.$quote_id.'&product_id='.$quoteInfo->product_id) . '">'.$quote_id.'</a>
                          </td></tr> ';
            $message .= '<tr><th align="left">Store:&nbsp</th><td style="width: 650px">
                        <a href="' .$storeUrl. '">'.$store.'</a></td></tr>';
            $message .= '<tr><th align="left">Item Code:&nbsp</th><td style="width: 650px">'.$sku.'</td></tr>';
            $message .= '<tr><th align="left">Status:&nbsp</th><td style="width: 650px">' .$statusStr. '</td></tr>';

            $message .= '</table>';
            $this->load->model('message/message');
            $this->model_message_message->addSystemMessageToBuyer('bid',$subject,$message,$quoteInfo->customer_id);
        }

    }

    public function addQuoteMessage($data)
    {
        $this->db->query(
            "INSERT INTO " . DB_PREFIX . "product_quote_message SET
					              	quote_id = '".(int)$data['quote_id']."',
					                writer = '".$this->customer->getId()."',
					              	message = '".$this->db->escape(nl2br(trim($data['message'])))."',
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

        $this->load->language('account/customerpartner/quote_mail');
        $this->load->language('account/customerpartner/wk_quotes_admin');

        $mail_message = '';

        switch($mail_type){

         //admin send message to customer
        case $this->message_to_customer :

            $mail_subject = sprintf($this->language->get($this->message_to_customer .'_subject'), $data['quote_id']);

            $mail_message = nl2br(sprintf($this->language->get($this->message_to_customer.'_message'), $this->language->get('text_status_'.$data['status'])));

            $customer_info = $this->getCustomer($data['quote_id']);
            $mail_to = $customer_info['email'];
            $mail_from = $this->customer->getEmail();

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
            $this->data['store_url'] = HTTP_SERVER;
            $this->data['logo'] = HTTP_SERVER.'image/' . $this->config->get('config_logo');

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
            );

            $replace = array_merge($replace, $value_index);

            $mail_message = trim(str_replace($find, $replace, $mail_message));

            $this->data['subject'] = $mail_subject;
            $this->data['message'] = $mail_message;


            if (version_compare(VERSION, '2.2.0.0', '>=')) {
                $html =$this->load->view('account/customerpartner/quote_mail', $this->data);
            } else {
                if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/account/customerpartner/quote_mail')) {
                    $html = $this->load->view($this->config->get('config_template') . '/template/account/customerpartner/quote_mail', $this->data);
                } else {
                       $html = $this->load->view('default/template/account/customerpartner/quote_mail', $this->data);
                }
            }

            $mail_sender = $this->customer->getFirstName().' '.$this->customer->getLastName();

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


    public function getSellerQuoteProducts(){
        $result = $this->db->query("SELECT * FROM ".DB_PREFIX."wk_pro_quote WHERE seller_id = ".$this->customer->getID())->row;
        return $result;
    }

    /**
     * 获取用户议价信息
     *
     * @param int $seller_id
     * @return array
     */
    public function getSellerQuoteDetails(int $seller_id): array
    {
        $result = $this->orm
            ->table(DB_PREFIX . 'wk_pro_quote_details')
            ->where(['seller_id' => $seller_id])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $result = $result->map(function ($item) {
            return get_object_vars($item);
        });
        return $result->toArray();
    }

    /**
     * 根据商品id获取议价详情
     *
     * @param int $product_id
     * @return array
     */
    public function getQuoteDetailsByProductId(int $product_id):array {
        $result = $this->orm
            ->table(DB_PREFIX . 'wk_pro_quote_details')
            ->where(['product_id' => $product_id])
            ->orderBy('sort_order')
            ->orderBy('min_quantity')
            ->get();

        $result = $result->map(function ($item){
            return get_object_vars($item);
        });
        return $result->toArray();
    }


    /**
     * @param int $product_id
     * @param string $currency   Session中的货币编码
     * @return array
     */
    public function getQuotePriceDetailsShow($product_id, $currency)
    {
        $precision = $this->currency->getDecimalPlace($currency);//货币小数位数

        $quotePriceDetails = $this->getQuoteDetailsByProductId($product_id);
        if (count($quotePriceDetails) > 3) {
            $quotePriceDetails = array_slice($quotePriceDetails, 0, 3);
        }
        foreach ($quotePriceDetails as $k => $v) {
            //#31737 商品详情页针对免税价调整
            $v['home_pick_up_price'] = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($v['seller_id'], customer()->getModel(), $v['home_pick_up_price']);
            $v['price_calc'] = $v['home_pick_up_price'];
            $v['price'] = $this->currency->format($v['price'], $currency);


            //组装阶梯价格
            $spot_price = $v['price_calc'];
            $v['price_currency'] = $this->currency->format(round($spot_price, $precision), $currency);
            $v['quantity_show'] = ($v['min_quantity'] < $v['max_quantity'] ? ($v['min_quantity'] . ' - ' . $v['max_quantity']) : $v['min_quantity']);
            $quotePriceDetails[$k] = $v;
        }

        return $quotePriceDetails;
    }


    /**
     * 添加议价数据
     * @param array $data
     * @return bool
     */
    public function editSellerProduct($data = []): bool
    {
        $flag = true;
        $sellerID = $this->customer->getID();
        $this->load->model('customerpartner/spot_price');
        $db = $this->orm->getConnection();
        try {
            $db->beginTransaction();
            // first delete all seller info
            $db->table('oc_wk_pro_quote')->where('seller_id', $sellerID)->delete();
            $db->table('oc_wk_pro_quote')->insert([
                'seller_id' => $sellerID,
                'product_ids' => join(',', $data['product_quote'] ?? []),
                'quantity' => $data['quantity'],
                'status' => $data['status'],
            ]);
            $db->commit();
        } catch (Exception $e) {
            $this->log->write($e->getMessage());
            $db->rollBack();
            $flag = false;
        }
        return $flag;
    }

    /**
     * @param int $product_id
     * @return array
     */
    public function getSellerConfig($product_id) {
        $seller_config = $this->db->query("SELECT * FROM ".DB_PREFIX."wk_pro_quote WHERE seller_id = (SELECT customer_id FROM ".DB_PREFIX."customerpartner_to_product WHERE product_id = '".(int)$product_id."')")->row;
        return $seller_config;
    }

    /**
     * 物理删除
     *
     * @param int $sellerID
     * @throws Throwable
     * @author lester.you
     */
    public function delete($sellerID)
    {
        $db = $this->orm->getConnection();
        $db->transaction(function () use ($db, $sellerID) {
            $db->table('oc_wk_pro_quote')->where('seller_id', $sellerID)->delete();
        });
    }

    /**
     *  获取商品原价
     *
     * @param int $productId
     *
     * @return float|int|bool
     */
    public function getQuotesProductPrice( $productId)
    {
        $productData = $this->orm->table('oc_product')->where('product_id', $productId)->first(['price']);
        if ($productData) {
            return $productData->price;
        }
        return false;
    }
}

