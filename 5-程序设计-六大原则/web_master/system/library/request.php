<?php
/**
 * @package        OpenCart
 * @author        Daniel Kerr
 * @copyright    Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license        https://opensource.org/licenses/GPL-3.0
 * @link        https://www.opencart.com
 */

/**
 * @deprecated 使用 Framework\Http\Request 代替
 * Request class
 */
class Request
{
    /**
     * @deprecated 使用 query 代替
     * @var array
     */
    public $get = array();
    /**
     * @deprecated 使用 input 代替
     * @var array
     */
    public $post = array();
    /**
     * @deprecated 使用 cookieBag 代替
     * @var array
     */
    public $cookie = array();
    /**
     * @deprecated 使用 filesBag 代替
     * @var array
     */
    public $files = array();
    /**
     * @deprecated 使用 serverBag 代替
     * @var array
     */
    public $server = array();
    /**
     * @deprecated 使用 attributes 代替
     * @var array
     */
    public $request = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->get = $this->clean($_GET);
        $this->post = $this->clean($_POST);
        $this->request = $this->clean($_REQUEST);
        $this->cookie = $this->clean($_COOKIE);
        $this->files = $this->clean($_FILES);
        $this->server = $this->clean($_SERVER);
    }

    /**
     *
     * @param array|string $data
     * @return array|string
     */
    public function clean($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                unset($data[$key]);

                $data[$this->clean($key)] = $this->clean($value);
            }
        } else {
            $data = htmlspecialchars($data, ENT_COMPAT, 'UTF-8');
        }

        return $data;
    }

    /**
     * @param \Cart\Customer $customer
     * @param Registry $registry
     * @throws Exception
     */
    public function save($customer, $registry)
    {
        if (!defined("REQ_LOG_SAVE") || !REQ_LOG_SAVE) return;
        $this->saveReq($customer, $registry);
    }

    public function getTableSql($name)
    {
        return <<<EOF
CREATE TABLE `$name`(
   `id` INT(11) NOT NULL AUTO_INCREMENT,
  `customer_id` INT(11) DEFAULT -1,
  `req_ip` INT(10) UNSIGNED DEFAULT 0,
  `req_route` VARCHAR(100) DEFAULT '',
  `req_url` VARCHAR(255) DEFAULT 'index',
  `req_method` VARCHAR(10) DEFAULT 'GET',
  `req_body` VARCHAR(255) DEFAULT '',
  `text` TEXT,
  `add_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `req_route` (`req_route`)
) ENGINE=MyISAM;
EOF;
    }

    /**
     * @param \Cart\Customer $customer
     * @param Registry $registry
     * @throws Exception
     */
    public function saveReq($customer, $registry): void
    {
        $route = $this->get['route'] ?? '/';
        //排除notifications
        $ignore = [
            'account/customerpartner/notification/notifications',
            'account/wk_communication/countUnread',
            'common/message_window/messageState',
        ];
        if (in_array($route, $ignore)) {
            return;
        }
        $db = $registry->get('db');
        $prefix = 'oc_request_log_';
        $table_rows = $db->query("SELECT table_name FROM information_schema.tables WHERE TABLE_SCHEMA='" . DB_DATABASE . "' AND TABLE_NAME LIKE '$prefix%'")->rows;
        $table_exists = false;
        $target_table = $prefix . date('Ym');
        $now = new DateTime();
        foreach ($table_rows as $k => $item) {
            $table_name = $item['table_name'];
            if ($table_name == $target_table) {
                $table_exists = true;
                continue;
            }
            $create_date = substr($table_name, strlen($prefix));
            $create_date = date_create_from_format('Ymd', $create_date . '01');
            $diff = $now->diff($create_date);
            $diff_month = ($diff->y * 12 + $diff->m);
            if ($diff_month > REQ_LOG_SAVE_MONTH) {
                //删除过期的表
                $db->query('drop table ' . $table_name);
            }
        }
        if (!$table_exists) {
            //创建
            $db->query($this->getTableSql($target_table));
        }
        $customer_id = $customer->getId();
        $sql = "INSERT INTO $target_table set  id=id
,`req_ip` = inet_aton(:req_ip) ,`customer_id` = :customer_id ,`req_method` = :req_method ";
        $param = array();
        $text = array();
        $param[':customer_id'] = $customer_id;
        $param[':req_method'] = $this->server["REQUEST_METHOD"];
        if (isset($this->server["REQUEST_URI"])) {
            if (strlen($this->server["REQUEST_URI"]) <= 255) {
                $sql .= ',req_url=:req_url';
                $param[':req_url'] = $this->server["REQUEST_URI"];
            } else {
                $text['req_url'] = $this->server["REQUEST_URI"];
            }
        }
        $ip = request()->getUserIp();
        if ($ip == '::1') $ip = '127.0.0.1';
        $param[':req_ip'] = $ip;
        if (isset($route)) {
            $sql .= ',req_route=:req_route';
            $param[':req_route'] = $route;
        }
        if (!empty($this->post)) {
            $body = json_encode($this->post);
            if (strlen($body) <= 255) {
                $sql .= ',req_body=:req_body';
                $param[':req_body'] = $body;
            } else {
                $text['req_body'] = $body;
            }
        }
        if (!empty($text)) {
            $sql .= ',text=:text';
            $param[':text'] = json_encode($text);
        }
        $db->query($sql, $param);
    }

}
