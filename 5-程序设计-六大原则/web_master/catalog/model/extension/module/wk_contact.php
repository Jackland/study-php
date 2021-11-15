<?php

use App\Models\Customer\Customer;

/**
 * Class ModelExtensionModuleWkcontact
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 */
class ModelExtensionModuleWkcontact extends Model
{

    public function sendMyMail($subject,$message,$message_to_id,$files=array())
    {
        $mail = new Phpmail();
        list($email) = $this->communication->getEmailAndName($message_to_id);
        $mail->to = $email;
        $mail->body = $this->formatMailMessage($subject,$message);
        $mail->files = $files;
        return $mail->send();
    }

    public function formatMailMessage($subject,$message)
    {
        $subject = html_entity_decode($subject, ENT_QUOTES, 'UTF-8');
        $res = $this->db->query("SELECT IF(ctc.customer_id IS NULL,CONCAT(cus.nickname,'(',cus.user_number,')'),ctc.screenname) AS customer_name FROM oc_customer cus LEFT JOIN oc_customerpartner_to_customer ctc ON cus.customer_id = ctc.customer_id WHERE cus.customer_id = " . (int)$this->customer->getId())->row;
        if (isset($res) && !empty($res)) {
            $from = $res['customer_name'];
        } else {
            $from = html_entity_decode($this->customer->getFirstName() . ' ' . $this->customer->getLastName(), ENT_QUOTES, 'UTF-8');
        }
        $date = date('Y-m-d H:i:s', time());
        $message = html_entity_decode(trim($message), ENT_QUOTES, 'UTF-8');
        $href = $this->url->link('account/wk_communication', '', 'SSL');
        $html = "<br><a href='$href'><h3>You have received an communication from the gigacloudlogistics Giga Cloud.</h3></a><hr>
<table   border='0' cellspacing='0' cellpadding='0' >
<tr><th align='left'>From:</th><td>$from</td></tr>
<tr><th align='left'>Subject:</th><td>$subject</td></tr>
<tr><th align='left'>Date:</th><td>$date</td></tr>
<tr><th align='left'>Message:</th><td></td></tr>
</table><br>$message";

        return $html;

    }

    public function countMessages($filter_data)
    {

        $querySql = "SELECT count(*) as total
                FROM " . DB_PREFIX . "wk_communication_message m, " . DB_PREFIX . "wk_communication_placeholder p
                WHERE  m.message_id=p.message_id
                AND p.user_id=" . $filter_data['customer_id'] . "
                AND p.placeholder_id= " . $filter_data['placeholder_id'] . "
                AND m.secure!=1 ";
        if(isset($filter_data['keyword'])){
            $querySql .= ' and m.message_subject like \'%'.$filter_data['keyword'].'%\'';
        }
        $total = $this->db->query($querySql)->row['total'];
        return $total;
    }

    public function getQuery($filter_data)
    {
        $this->db->escapeParams($filter_data);
        $querySql = "SELECT m.user_id, m.message_id,m.message_subject,m.message_body,m.message_date,m.message_to,m.message_from,
SUBSTRING_INDEX(m.user_id, '_', 1) AS message_from_id,
SUBSTRING_INDEX(m.user_id, '_', -1) AS message_to_id,
p.user_name,p.placeholder_name,p.placeholder_id,m.show_flag,p.is_read
                FROM " . DB_PREFIX . "wk_communication_message m, " . DB_PREFIX . "wk_communication_placeholder p
                WHERE  m.message_id=p.message_id
                AND p.user_id=".$filter_data['customer_id']."
                AND p.placeholder_id= ".$filter_data['placeholder_id'];
        if(isset($filter_data['keyword'])){
            $querySql .= ' and m.message_subject like \'%'.$filter_data['keyword'].'%\'';
        }
        $querySql .= " AND m.secure!=1
                ORDER BY m.message_id  DESC
                limit ".$filter_data['start'].','.$filter_data['limit'];
        $result = $this->db->query($querySql)->rows;
        if (!empty($result)) {
            foreach ($result as $key => $value) {
                $tar_user_id = $filter_data['customer_id'] == $value['message_from_id'] ? $value['message_to_id'] : $value['message_from_id'];
                if (!$tar_user_id || $tar_user_id == -1) {
                    $result[$key]['user_name'] = $this->config->get('system_name');
                } else {
                    $res = $this->db->query("SELECT ctc.customer_id,IF(ctc.customer_id IS NULL,CONCAT(cus.nickname,'(',cus.user_number,')'),ctc.screenname) AS customer_name FROM oc_customer cus LEFT JOIN oc_customerpartner_to_customer ctc ON cus.customer_id = ctc.customer_id WHERE cus.customer_id = " . (int)$tar_user_id)->row;
                    if (isset($res) && !empty($res)) {
                        $result[$key]['user_name'] = $res['customer_name'];
                        if (isset($res['customer_id'])) {
                            $result[$key]['seller_link'] = $this->url->link('customerpartner/profile&id=' . (int)$res['customer_id']);
                        }
                    }
                }
            }
        }
        return $result;
    }

    public function getQueryinfo($message_id)
    {
//        $result = $this->db->query("SELECT * FROM " . DB_PREFIX . "wk_communication_message WHERE message_id='" . $message_id . "' AND secure!=1");
        $result = $this->db->query("SELECT
                                    wcm.*,
                                    SUBSTRING_INDEX(wcm.`user_id`, '_', 1) AS message_from_id,
                                    SUBSTRING_INDEX(wcm.`user_id`, '_', -1) AS message_to_id
                                  FROM
                                    oc_wk_communication_message wcm
                                  WHERE wcm.message_id = '" . $message_id . "'
                                    AND wcm.secure != 1");
        $row = $result->row;
        if(isset($row['message_from_id']) && !empty($row['message_from_id'])){
            $seller_sql = "SELECT ctc.customer_id FROM oc_customerpartner_to_customer ctc WHERE ctc.customer_id = " . (int)$row['message_from_id'];
            $seller_row = $this->db->query($seller_sql)->row;
            if(isset($seller_row) && !empty($seller_row)){
                $row['seller_link'] = $this->url->link('customerpartner/profile&id=' . (int)$row['message_from_id']);
            }
        }
        return $row;
    }

    public function getSellerEstablishTitle($msg_row)
    {
        if (!empty($msg_row) && in_array($msg_row['show_flag'],[101,102])) {
            $this->load->model('account/customerpartner');
            if ($this->model_account_customerpartner->chkIsPartner()
                && $this->customer->getId() == $msg_row['message_from_id']) {
                $name = '';
                $name_row = $this->db->query("SELECT c.nickname,c.user_number
                 FROM oc_customer c    WHERE c.customer_id = " . $msg_row['message_to_id'])->row;
                if (!empty($name_row)) {
                    $name = $name_row['nickname'] . '(' . $name_row['user_number'] . ')';
                }
                //同意
                if ($msg_row['show_flag'] == 101) {
                    return 'You have approved the application of establishing relationship with '.$name;
                    //拒绝
                } elseif ($msg_row['show_flag'] == 102) {
                    return 'You have rejected the application of establishing relationship with '.$name;
                }
            }
        }
    }

    public function getCustomer($customer_id)
    {
        return $this->db->query("SELECT IF(ctc.customer_id IS NULL,CONCAT(cus.nickname,'(',cus.user_number,')'),ctc.screenname) AS name FROM oc_customer cus LEFT JOIN oc_customerpartner_to_customer ctc ON cus.customer_id = ctc.customer_id WHERE cus.customer_id = " . (int)$customer_id)->row;
    }

    public function getCustomerNameById($customer_id)
    {
        if (empty($customer_id) || $customer_id == $this->config->get('system_id')) {
            return [$this->config->get('system_id'),
                $this->config->get('system_name')];
        }

        $row = $this->getCustomer($customer_id);
        if (isset($row['name'])) {
            return [$customer_id, $row['name']];
        } else {
            return [$this->config->get('system_id'),
                $this->config->get('system_name')];

        }
    }

    public function getAttachment($message_id)
    {
        $results = $this->db->query("SELECT attachment_id,maskname FROM " . DB_PREFIX . "wk_communication_attachment WHERE message_id = '" . $message_id . "'")->rows;
        return $results;
    }

    /**
     * @param string $buyer Email
     * @param int $sellerId
     */
    public function addBuyerToSeller($buyer, $sellerId)
    {
        $querySql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "buyer_to_seller` WHERE seller_id = " . $sellerId . " AND buyer_id = (SELECT customer_id FROM oc_customer WHERE email = '" . $buyer . "')";
        if ($this->db->query($querySql)->row['total']) {
            $sql = "UPDATE `" . DB_PREFIX . "buyer_to_seller` SET buy_status = 1, price_status = 1, buyer_control_status = 1, seller_control_status = 1, discount = 1 WHERE seller_id = " . $sellerId . " AND buyer_id = (SELECT customer_id FROM oc_customer WHERE email = '" . $buyer . "')";
            $this->db->query($sql);
        } else {
            $sql = "INSERT INTO `" . DB_PREFIX . "buyer_to_seller` (buyer_id, seller_id, buy_status, price_status, buyer_control_status, seller_control_status, discount) select customer_id," . $sellerId . ", 1,1,1,1,1 from oc_customer oc where oc.email='" . $buyer . "'";
            $this->db->query($sql);
        }
    }

    public function getDownload($attachment_id)
    {
        $results = $this->db->query("SELECT * FROM " . DB_PREFIX . "wk_communication_attachment WHERE attachment_id = '" . $attachment_id . "'")->row;
        return $results;
    }

    public function getThreadMessages($message_id)
    {
        $results = $this->db->query("SELECT * FROM " . DB_PREFIX . "wk_communication_thread wct LEFT JOIN " . DB_PREFIX . "wk_communication_message wm ON(wct.message_id=wm.message_id) WHERE parent_message_id='" . $message_id . "' AND wm.secure!=1")->rows;
        return $results;
    }

    public function deleteQuery($message_id, $customer_id)
    {
        $result = $this->db->query("SELECT * FROM " . DB_PREFIX . "wk_communication_placeholder WHERE message_id='" . $message_id . "' AND user_id='" . $customer_id . "'")->row;
        if ($result['placeholder_id'] == 0) {
            $this->db->query("UPDATE " . DB_PREFIX . "wk_communication_placeholder set status=0,placeholder_name='delete From trash',placeholder_id=-1 WHERE user_id='" . $customer_id . "' AND message_id='" . $message_id . "'");
        } else {
            $this->db->query("UPDATE " . DB_PREFIX . "wk_communication_placeholder set status=0,placeholder_name='delete',placeholder_id=0 WHERE user_id='" . $customer_id . "' AND message_id='" . $message_id . "'");
        }
    }

    public function restoreQuery($message_id, $customer_id)
    {
        $result = $this->db->query("SELECT * FROM " . DB_PREFIX . "wk_communication_placeholder WHERE message_id='" . $message_id . "' AND user_id='" . $customer_id . "'")->row;
        $from = preg_replace("/delete from /", '', $result['placeholder_name']);
        if ($from == 'trash')
            if ($result['placeholder_id'] == 0) {
                $this->db->query("UPDATE " . DB_PREFIX . "wk_communication_placeholder set status=0,placeholder_name='delete from trash',placeholder_id=-1 WHERE user_id='" . $customer_id . "' AND message_id='" . $message_id . "'");
            } else {
                $this->db->query("UPDATE " . DB_PREFIX . "wk_communication_placeholder set status=0,placeholder_name='delete',placeholder_id=0 WHERE user_id='" . $customer_id . "' AND message_id='" . $message_id . "'");
            }
    }

    public function getTotal($customer_id)
    {
        $result['inbox'] = count($this->getQuery($customer_id, 1));
        $result['sent'] = count($this->getQuery($customer_id, 2));
        $result['trash'] = count($this->getQuery($customer_id, 0));
        return $result;

    }

    public function countThreads($message_id)
    {
        $count = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "wk_communication_thread wkt LEFT JOIN " . DB_PREFIX . "wk_communication_message wm ON (wkt.message_id=wm.message_id) WHERE wkt.parent_message_id='" . $message_id . "' AND wm.secure!=1");
        return $count->row['total'];
    }

    public function updateShowFlag($message_id,$show_flag)
    {
        $sql = "UPDATE " . DB_PREFIX . "wk_communication_message SET show_flag = $show_flag WHERE message_id = " . (int)$message_id;
        $this->db->query($sql);
    }

    public function countUnread()
    {
        if ($this->customer->isLogged()) {
            $sql = "SELECT COUNT(id) as count FROM `oc_wk_communication_placeholder` WHERE user_id = {$this->customer->getId()} AND  placeholder_id=1 AND is_read=0";
            return $this->db->query($sql)->row['count'];
        }
    }

    public function read($message_id)
    {
        $sql = "update oc_wk_communication_placeholder set is_read = 1 WHERE user_id = {$this->customer->getId()} and message_id = $message_id";
        $this->db->query($sql);
    }

    public function readAll($customer_id, $keyword)
    {
        if(!empty($customer_id)){
            $sql = "update oc_wk_communication_placeholder mp
left join oc_wk_communication_message mm
on mm.message_id = mp.message_id
set mp.is_read = 1
WHERE mp.user_id = {$customer_id} ";
            $keyword = $this->db->escape($keyword);
            if (isset($keyword) && $keyword != '') {
                $sql .= "and mm.message_subject like  '%$keyword%' ";
            }
            $this->db->query($sql);
        }
    }


}
