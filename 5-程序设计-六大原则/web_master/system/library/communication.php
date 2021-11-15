<?php

use App\Components\Storage\StorageCloud;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class Communication extends Mail{
    public $config;
    public $orm;    // The Database object.
    static $object;
	public function __construct($registry=null) {
        if (!is_null($registry)) {
            $this->config = $registry->get('config');
            $this->db = $registry->get('db');
            $this->request = $registry->get('request');
            $this->session = $registry->get('session');
            $this->orm = $registry->get('orm');
            Communication::$object = $this;
        }
    }

    /**
     * @param string $to
     * @param string $from
     * @param int $message_id
     * @param string $secure
     */
    public function updatePlaceholderDetails($to, $from, $message_id, $secure = '')
    {
        if ($to == $this->config->get('config_email') && $from == $this->config->get('config_email')) {
            $this->orm::table(DB_PREFIX . "wk_communication_placeholder")
                ->insert([
                    [
                        'user_id' => '-1',
                        'user_name' => 'Admin',
                        'placeholder_id' => 1,
                        'placeholder_name' => 'Inbox',
                        'message_id' => $message_id,
                        'status' => 1,
                        'old_placeholder_id' => 1
                    ],
                    [
                        'user_id' => '-1',
                        'user_name' => 'Admin',
                        'placeholder_id' => 2,
                        'placeholder_name' => 'Sent',
                        'message_id' => $message_id,
                        'status' => 1,
                        'old_placeholder_id' => 1
                    ],
                ]);
//		   $this->db->query("INSERT INTO ".DB_PREFIX."wk_communication_placeholder VALUES('','-1','Admin',1,'Inbox','".$message_id."',1)");
//		   $this->db->query("INSERT INTO " .DB_PREFIX ."wk_communication_placeholder VALUES('','-1','Admin',2,'Sent','".$message_id."',1)");
        }
        //To check seller->admin or buyer->admin
        else if ($to == $this->config->get('config_email')) {
            $this->orm::table(DB_PREFIX . "wk_communication_placeholder")
                ->insert([
                    [
                        'user_id' => '-1',
                        'user_name' => 'Admin',
                        'placeholder_id' => 1,
                        'placeholder_name' => 'Inbox',
                        'message_id' => $message_id,
                        'status' => 1,
                        'old_placeholder_id' => 1
                    ],
                ]);
//			$this->db->query("INSERT INTO ".DB_PREFIX."wk_communication_placeholder VALUES('','-1','Admin',1,'Inbox','".$message_id."',1)");
            $from_detail = $this->db->query("SELECT firstname,lastname,customer_id FROM " . DB_PREFIX . "customer WHERE email='" . $from . "'")->row;
            if (isset($from_detail['firstname'])) {
                $from_name = $from_detail['firstname'] . ' ' . $from_detail['lastname'];
                $this->orm::table(DB_PREFIX . "wk_communication_placeholder")
                    ->insert([
                        [
                            'user_id' => $from_detail['customer_id'],
                            'user_name' => $from_name,
                            'placeholder_id' => 2,
                            'placeholder_name' => 'Sent',
                            'message_id' => $message_id,
                            'status' => 1,
                            'old_placeholder_id' => 2
                        ],
                    ]);
//				$this->db->query("INSERT INTO " .DB_PREFIX ."wk_communication_placeholder VALUES('','".$from_detail['customer_id']."','".$from_name."',2,'Sent','".$message_id."',1)");
            }
        } //To check admin->seller or admin->buyer
        else if ($from == $this->config->get('config_email')) {
            $this->orm::table(DB_PREFIX . "wk_communication_placeholder")
                ->insert([
                    [
                        'user_id' => '-1',
                        'user_name' => 'Admin',
                        'placeholder_id' => 2,
                        'placeholder_name' => 'Sent',
                        'message_id' => $message_id,
                        'status' => 1,
                        'old_placeholder_id' => 2
                    ],
                ]);
//			$this->db->query("INSERT INTO ".DB_PREFIX."wk_communication_placeholder VALUES('','-1','Admin',2,'Sent','".$message_id."',1)");
            $to_detail = $this->db->query("SELECT firstname,lastname,customer_id FROM " . DB_PREFIX . "customer WHERE email='" . $to . "'")->row;
            if (isset($to_detail['firstname'])) {
                $to_name = $to_detail['firstname'] . ' ' . $to_detail['lastname'];
                $this->orm::table(DB_PREFIX . "wk_communication_placeholder")
                    ->insert([
                        [
                            'user_id' => $to_detail['customer_id'],
                            'user_name' => $to_name,
                            'placeholder_id' => 1,
                            'placeholder_name' => 'Inbox',
                            'message_id' => $message_id,
                            'status' => 1,
                            'old_placeholder_id' => 1
                        ],
                    ]);
//				$this->db->query("INSERT INTO " .DB_PREFIX ."wk_communication_placeholder VALUES('','".$to_detail['customer_id']."','".$to_name."',1,'Inbox','".$message_id."',1)");
            }
        } //[Admin is not in flow of Communication]
        else {
            $this->orm::table(DB_PREFIX . "wk_communication_placeholder")
                ->insert([
                    [
                        'user_id' => '-1',
                        'user_name' => 'Admin',
                        'placeholder_id' => 1,
                        'placeholder_name' => 'Inbox',
                        'message_id' => $message_id,
                        'status' => 1,
                        'old_placeholder_id' => 1
                    ],
                ]);
//				$this->db->query("INSERT INTO ".DB_PREFIX."wk_communication_placeholder VALUES('','-1','Admin',1,'Inbox','".$message_id."',1)");
            $from_detail = $this->db->query("SELECT firstname,lastname,customer_id FROM " . DB_PREFIX . "customer WHERE email='" . $from . "'")->row;
            if (isset($from_detail['firstname'])) {
                $from_name = $from_detail['firstname'] . ' ' . $from_detail['lastname'];
                $this->orm::table(DB_PREFIX . "wk_communication_placeholder")
                    ->insert([
                        [
                            'user_id' => $from_detail['customer_id'],
                            'user_name' => $from_name,
                            'placeholder_id' => 2,
                            'placeholder_name' => 'Sent',
                            'message_id' => $message_id,
                            'status' => 1,
                            'old_placeholder_id' => 2
                        ],
                    ]);
//					$this->db->query("INSERT INTO " .DB_PREFIX ."wk_communication_placeholder VALUES('','".$from_detail['customer_id']."','".$from_name."',2,'Sent','".$message_id."',1)");
            }
            $to_detail = $this->db->query("SELECT firstname,lastname,customer_id FROM " . DB_PREFIX . "customer WHERE email='" . $to . "'")->row;
            if (isset($to_detail['firstname'])) {
                $to_name = $to_detail['firstname'] . ' ' . $to_detail['lastname'];
                $this->orm::table(DB_PREFIX . "wk_communication_placeholder")
                    ->insert([
                        [
                            'user_id' => $to_detail['customer_id'],
                            'user_name' => $to_name,
                            'placeholder_id' => 1,
                            'placeholder_name' => 'Inbox',
                            'message_id' => $message_id,
                            'status' => 1,
                            'old_placeholder_id' => 1
                        ],
                    ]);
//					$this->db->query("INSERT INTO " .DB_PREFIX ."wk_communication_placeholder VALUES('','".$to_detail['customer_id']."','".$to_name."',1,'Inbox','".$message_id."',1)");
            }
        }
    }

    public function updateReply($message_id, $parent_message_id)
    {
        $this->db->query("INSERT INTO " . DB_PREFIX . "wk_communication_thread VALUES('',?,?, NOW())",
            array($message_id, $parent_message_id));
    }

    public function saveCommunication($subject, $message, $to, $from, $secure, $uploaded = array())
    {
        $msg_user_id = null;
        $msg_user_id = $from . '_' . $to;
        $to_email = $this->db->query("SELECT email from oc_customer where customer_id = " . $to)->row['email'];
        $from_email = $this->db->query("SELECT email from oc_customer where customer_id = " . $from)->row['email'];
        $param = array($subject, $message, $to_email, $from_email, $secure, $msg_user_id);
        $this->db->query("INSERT INTO " . DB_PREFIX . "wk_communication_message VALUES('',?,?,NOW(),?,?,?,?,null)"
            , $param);
        $message_id = $this->db->getLastId();
        if (!empty($uploaded))
            foreach ($uploaded['filename'] as $key => $value) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "wk_communication_attachment SET message_id = ?, filename = ?, maskname = ?, date_added = NOW()",
                    array($message_id, $uploaded['filename'][$key], $uploaded['mask'][$key]));
            }
        $this->updatePlaceholderDetails($to_email, $from_email, $message_id);
    }


    public function insertQuery($subject, $message, $message_from_id, $message_to_id, $files = array(), $establishContact = null)
    {
        $message = html_entity_decode(trim($message), ENT_QUOTES, 'UTF-8');

        $secure = $this->getSecure($subject, $message);

        if (str_contains($message_to_id, ',')) {
            $message_to_ids = explode(',', $message_to_id);
            //群发
            $message_ids = $this->insertMessages($subject, $message, $message_from_id, $message_to_ids, $secure, $establishContact);
            //群发只存一份附件
            $this->insertAttachment($files, $message_ids);
            return $message_ids;
        } else {
            //正常发送单条
            $message_id = $this->insertMessage($subject, $message, $message_from_id, $message_to_id, $secure, $establishContact);
            $this->insertPlaceholder($message_from_id, $message_to_id, $message_id);
            //附件
            $this->insertAttachment($files, $message_id);
            return $message_id;
        }
    }

    /**
     * @param int $customer_id
     * @return array
     */
    public function getEmailAndName($customer_id)
    {
        $system_id = $this->config->get('system_id');
        $system_name = $this->config->get('system_name');
        if ($customer_id == $system_id) {
            $email = $this->config->get('config_mail_smtp_username');
            $customer_name = $system_name;
        } else {
            $row = $this->db->query("SELECT CONCAT(c.firstname,' ',c.lastname) AS custoemr_name,c.email FROM oc_customer c  where customer_id=?", [$customer_id])->row;
            $email = $row['email'];
            $customer_name = $row['custoemr_name'];
        }
        return array($email, $customer_name);
    }

    /**
     * @param int $message_from_id customer_id
     * @param int $message_to_id customer_id
     * @param $message_id
     */
    public function insertPlaceholder($message_from_id, $message_to_id, $message_id)
    {
        $system_id = $this->config->get('system_id');
        $system_name = $this->config->get('system_name');
        list($message_from, $customer_name_from) = $this->getEmailAndName($message_from_id);
        list($message_to, $customer_name_to) = $this->getEmailAndName($message_to_id);
        $this->orm::table(DB_PREFIX . 'wk_communication_placeholder')
            ->insert([
                [
                    'user_id' => $message_from_id,
                    'user_name' => $customer_name_from,
                    'placeholder_id' => 2,
                    'placeholder_name' => 'Sent',
                    'message_id' => $message_id,
                    'status' => 1,
                    'old_placeholder_id' => 2
                ],
                [
                    'user_id' => $message_to_id,
                    'user_name' => $customer_name_to,
                    'placeholder_id' => 1,
                    'placeholder_name' => 'Inbox',
                    'message_id' => $message_id,
                    'status' => 1,
                    'old_placeholder_id' => 1
                ],
            ]);
        if ($message_from_id != $system_id && $message_to_id != $system_id) {
            $this->orm::table(DB_PREFIX . 'wk_communication_placeholder')
                ->insert([[
                    'user_id' => $system_id,
                    'user_name' => $system_name,
                    'placeholder_id' => 1,
                    'placeholder_name' => 'Inbox',
                    'message_id' => $message_id,
                    'status' => 1,
                    'old_placeholder_id' => 1
                ]]);
        }
    }

    /**
     * @param $files
     * @param array|int $message_ids 可以传单个消息ID或消息ID数组
     */
    public function insertAttachment($files, $message_ids)
    {
        if(empty($message_ids)){
            return;
        }
        $uploaded = [];
        if (!empty($files) && isset($files['name'])) {
            foreach ($files['name'] as $key => $value) {
                if ($files['tmp_name'][$key] && $files['name'][$key]) {
                    $file = $files['name'][$key] . '.' . token(32);
                    StorageCloud::storage()->writeFile(new UploadedFile($files['tmp_name'][$key], $files['name'][$key]), 'download/attachment', $file);
                    $uploaded['filename'][] = $file;
                    $uploaded['mask'][] = $files['name'][$key];
                }
            }
        }

        if (!empty($uploaded)) {
            $insertAttachmentRows = [];
            if (!is_array($message_ids)) {
                $message_ids = [$message_ids];
            }
            foreach ($message_ids as $message_id) {
                if ($message_id != null && $message_id != '') {
                    foreach ($uploaded['filename'] as $key => $value) {
                        $insertAttachmentRows [] = [
                            'message_id' => $message_id,
                            'filename' => $uploaded['filename'][$key],
                            'maskname' => $uploaded['mask'][$key],
                            'date_added' => date('Y-m-d'),
                        ];
                    }
                }
            }
            $this->orm::table(DB_PREFIX . 'wk_communication_attachment')
                ->insert($insertAttachmentRows);
        }
    }

    /**
     * @param string $subject
     * @param string $message
     * @param int $message_from_id customer_id
     * @param int $message_to_id customer_id
     * @param int $secure
     * @return int
     */
    public function insertMessage($subject, $message, $message_from_id, $message_to_id, int $secure, $establishContact = null)
    {
        if ($message_to_id == '') {
            $message_to_id = $this->config->get('system_id');
        }
        list($message_from, $customer_name_from) = $this->getEmailAndName($message_from_id);
        list($message_to, $customer_name_to) = $this->getEmailAndName($message_to_id);

        $message_id = $this->orm::table(DB_PREFIX . 'wk_communication_message')
            ->insertGetId([
                'message_subject' => $subject,
                'message_body' => $message,
                'message_from' => $message_from,
                'message_to' => $message_to,
                'secure' => $secure,
                'user_id' => $message_from_id . '_' . $message_to_id,
                'message_date' => date('Y-m-d H:i:s'),
                'show_flag' => $establishContact,
            ]);
        return $message_id;
    }

    public function insertMessages($subject, $message, $message_from_id, $message_to_ids = [], int $secure, $establishContact = null)
    {
        if(empty($message_to_ids)){
            return;
        }
        list($message_from_email, $message_from_name) = $this->getEmailAndName($message_from_id);

        $message_to_rows = $this->db->query("SELECT customer_id as message_to_id,
CONCAT(c.firstname,' ',c.lastname) AS message_to_name,
c.email as  message_to_email
FROM oc_customer c
where customer_id in (" . implode(',', $message_to_ids) . ")"
        )->rows;

        if(empty($message_to_rows)){
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach ($message_to_rows as &$row) {
            //message
            $message_id = $this->orm::table(DB_PREFIX . 'wk_communication_message')
                ->insertGetId([
                    'message_subject' => $subject,
                    'message_body' => $message,
                    'message_from' => $message_from_email,
                    'message_to' => $row['message_to_email'],
                    'secure' => $secure,
                    'user_id' => $message_from_id . '_' . $row['message_to_id'],
                    'message_date' => $now,
                    'show_flag' => $establishContact,
                ]);
            $row['message_id'] = $message_id;
        }
        //placeholder
        $insertPlaceholderRows = [];
        foreach ($message_to_rows as $row1) {
            $insertPlaceholderRows[] = [
                'user_id' => $message_from_id,
                'user_name' => $message_from_name,
                'placeholder_id' => 2,
                'placeholder_name' => 'Sent',
                'message_id' => $row1['message_id'],
                'status' => 1,
                'old_placeholder_id' => 2
            ];
            $insertPlaceholderRows[] = [
                'user_id' => $row1['message_to_id'],
                'user_name' => $row1['message_to_name'],
                'placeholder_id' => 1,
                'placeholder_name' => 'Inbox',
                'message_id' => $row1['message_id'],
                'status' => 1,
                'old_placeholder_id' => 1
            ];
        }
        $this->orm::table(DB_PREFIX . 'wk_communication_placeholder')
            ->insert($insertPlaceholderRows);
        return array_column($message_to_rows,'message_id');
    }

    /**
     * @param string $subject
     * @param string $message
     * @return int
     */
    public function getSecure($subject, $message): int
    {
        $secure = 0;
        $keywords = explode(',', $this->config->get('module_wk_communication_keywords'));
        $searchin = ' ';
        if ($this->config->get('module_wk_communication_search')) {
            if (in_array('message', $this->config->get('module_wk_communication_search'))) {
                $searchin .= $message;
            }
            if (in_array('subject', $this->config->get('module_wk_communication_search'))) {
                $searchin .= $subject;
            }
        }
        if (!empty($keywords)) {
            foreach ($keywords as $key) {
                if (!empty($key)) {
                    $key = trim($key);
                    if (strpos($searchin, $key) != false) {
                        $secure = 1;
                        break;
                    }
                }
            }
        }
        return $secure;
    }
}
