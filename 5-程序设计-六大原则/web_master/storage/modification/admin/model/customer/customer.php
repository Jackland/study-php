<?php

use App\Models\Customer\Customer;
use App\Services\SellerAsset\SellerAssetService;

/**
 * Class ModelCustomerCustomer
 */
class ModelCustomerCustomer extends Model
{

    public function addCustomer($data, $create_seller = '')
    {
        $this->db->query("INSERT INTO " . DB_PREFIX . "customer SET customer_group_id = '" . (int)$data['customer_group_id'] . "', firstname = '" . $this->db->escape($data['firstname']) . "', lastname = '" . $this->db->escape($data['lastname']) . "', nickname = '" . $this->db->escape($data['nickname']) . "', user_number = '" . $this->db->escape($data['user_number']) . "', email = '" . $this->db->escape($data['email']) . "', telephone = '" . $this->db->escape($data['telephone']) . "', custom_field = '" . $this->db->escape(isset($data['custom_field']) ? json_encode($data['custom_field']) : json_encode(array())) . "', newsletter = '" . (int)$data['newsletter'] . "', salt = '" . $this->db->escape($salt = token(9)) . "', password = '" . $this->db->escape(sha1($salt . sha1($salt . sha1($data['password'])))) . "', status = '" . (int)$data['status']
            . "', safe = '" . (int)$data['safe']
            . "', trusteeship = '" . (int)$data['trusteeship']
            . "', telephone_country_code_id = '" . (int)$data['telephone_country_code_id']
            . "', country_id = " .(int)$data['country_id']. ", date_added = NOW()" . ", accounting_type = " .(int)$data['accounting_type_id']);
        //12905 B2B增加Buyer录入各个平台映射的功能
        $customer_id = $this->db->getLastId();

        if ($this->config->get('module_marketplace_status') && $create_seller) {

            $this->load->model('customerpartner/partner');
            $this->model_customerpartner_partner->approve($customer_id, $setstatus = 1);
            app(SellerAssetService::class)->firstOrCreateSellerAsset($customer_id);
        } else {
            $this->orm->table(DB_PREFIX . 'buyer')
                ->insert([
                    'buyer_id' => $customer_id,
                    'cloud_freight_rate' => $this->config->get('cwf_base_cloud_freight_rate'),
                    'memo' => 'Initialize',
                ]);

            if (!$this->orm->table(DB_PREFIX . 'customer_exts')->where('customer_id', $customer_id)->exists()) {
                $this->orm->table(DB_PREFIX . 'customer_exts')
                    ->insert([
                        'customer_id' => $customer_id,
                        'auto_buy' => 0,
                        'import_order' => 0,
                        'second_passwd' => 0,
                        'create_time' => date('Y-m-d H:i:s'),
                        'create_user' => 'admin'
                    ]);
            }
        }


        if (isset($data['address'])) {
            foreach ($data['address'] as $address) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "address SET customer_id = '" . (int)$customer_id . "', firstname = '" . $this->db->escape($address['firstname']) . "', lastname = '" . $this->db->escape($address['lastname']) . "', company = '" . $this->db->escape($address['company']) . "', address_1 = '" . $this->db->escape($address['address_1']) . "', address_2 = '" . $this->db->escape($address['address_2']) . "', city = '" . $this->db->escape($address['city']) . "', postcode = '" . $this->db->escape($address['postcode']) . "', country_id = '" . (int)$address['country_id'] . "', zone_id = '" . (int)$address['zone_id'] . "', custom_field = '" . $this->db->escape(isset($address['custom_field']) ? json_encode($address['custom_field']) : json_encode(array())) . "'");

                if (isset($address['default'])) {
                    $address_id = $this->db->getLastId();

                    $this->db->query("UPDATE " . DB_PREFIX . "customer SET address_id = '" . (int)$address_id . "' WHERE customer_id = '" . (int)$customer_id . "'");
                }
            }
        }

        if ($data['affiliate']) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "customer_affiliate SET customer_id = '" . (int)$customer_id . "', company = '" . $this->db->escape($data['company']) . "', website = '" . $this->db->escape($data['website']) . "', tracking = '" . $this->db->escape($data['tracking']) . "', commission = '" . (float)$data['commission'] . "', tax = '" . $this->db->escape($data['tax']) . "', payment = '" . $this->db->escape($data['payment']) . "', cheque = '" . $this->db->escape($data['cheque']) . "', paypal = '" . $this->db->escape($data['paypal']) . "', bank_name = '" . $this->db->escape($data['bank_name']) . "', bank_branch_number = '" . $this->db->escape($data['bank_branch_number']) . "', bank_swift_code = '" . $this->db->escape($data['bank_swift_code']) . "', bank_account_name = '" . $this->db->escape($data['bank_account_name']) . "', bank_account_number = '" . $this->db->escape($data['bank_account_number']) . "', custom_field = '" . $this->db->escape(isset($data['custom_field']) ? json_encode($data['custom_field']) : json_encode(array())) . "', status = '" . (int)$data['affiliate'] . "', date_added = NOW()");
        }

        return $customer_id;
    }

    public function editCustomer($customer_id, $data)
    {
        $customer = Customer::find($customer_id);
        if ($customer->telephone !== $data['telephone']) {
            // 编辑手机号后需要重新验证
            $customer->telephone_verified_at = 0;
        }
        $customer->custom_field = json_encode($data['customer_filed'] ?? []);
        if ($data['accounting_type_id']) {
            $customer->accounting_type = $data['accounting_type_id'];
        }
        $customer->update($data);
        //12905 B2B增加Buyer录入各个平台映射的功能
        $this->db->query("UPDATE " . DB_PREFIX . "customerpartner_to_customer SET accounting_type = " .(int)$data['accounting_type_id'] . " WHERE customer_id = '" . (int)$customer_id . "'");
        if ($data['password']) {
            $this->db->query("UPDATE " . DB_PREFIX . "customer SET salt = '" . $this->db->escape($salt = token(9)) . "', password = '" . $this->db->escape(sha1($salt . sha1($salt . sha1($data['password'])))) . "' WHERE customer_id = '" . (int)$customer_id . "'");
        }

        $this->db->query("DELETE FROM " . DB_PREFIX . "address WHERE customer_id = '" . (int)$customer_id . "'");

        if (isset($data['address'])) {
            foreach ($data['address'] as $address) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "address SET address_id = '" . (int)$address['address_id'] . "', customer_id = '" . (int)$customer_id . "', firstname = '" . $this->db->escape($address['firstname']) . "', lastname = '" . $this->db->escape($address['lastname']) . "', company = '" . $this->db->escape($address['company']) . "', address_1 = '" . $this->db->escape($address['address_1']) . "', address_2 = '" . $this->db->escape($address['address_2']) . "', city = '" . $this->db->escape($address['city']) . "', postcode = '" . $this->db->escape($address['postcode']) . "', country_id = '" . (int)$address['country_id'] . "', zone_id = '" . (int)$address['zone_id'] . "', custom_field = '" . $this->db->escape(isset($address['custom_field']) ? json_encode($address['custom_field']) : json_encode(array())) . "'");

                if (isset($address['default'])) {
                    $address_id = $this->db->getLastId();

                    $this->db->query("UPDATE " . DB_PREFIX . "customer SET address_id = '" . (int)$address_id . "' WHERE customer_id = '" . (int)$customer_id . "'");
                }
            }
        }

        if ($data['affiliate']) {
            $this->db->query("REPLACE INTO " . DB_PREFIX . "customer_affiliate SET customer_id = '" . (int)$customer_id . "', company = '" . $this->db->escape($data['company']) . "', website = '" . $this->db->escape($data['website']) . "', tracking = '" . $this->db->escape($data['tracking']) . "', commission = '" . (float)$data['commission'] . "', tax = '" . $this->db->escape($data['tax']) . "', payment = '" . $this->db->escape($data['payment']) . "', cheque = '" . $this->db->escape($data['cheque']) . "', paypal = '" . $this->db->escape($data['paypal']) . "', bank_name = '" . $this->db->escape($data['bank_name']) . "', bank_branch_number = '" . $this->db->escape($data['bank_branch_number']) . "', bank_swift_code = '" . $this->db->escape($data['bank_swift_code']) . "', bank_account_name = '" . $this->db->escape($data['bank_account_name']) . "', bank_account_number = '" . $this->db->escape($data['bank_account_number']) . "', status = '" . (int)$data['affiliate'] . "', date_added = NOW()");
        }
    }

    public function editToken($customer_id, $token)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "customer SET token = '" . $this->db->escape($token) . "' WHERE customer_id = '" . (int)$customer_id . "'");
    }

    public function deleteCustomer($customer_id)
    {

        /**
         * Marketplace
         */
        if ($this->config->get('module_marketplace_status')) {

            $this->load->model('customerpartner/partner');
            $this->model_customerpartner_partner->deleteCustomer($customer_id);
        }
        /**
         * Marketplace
         */


        $this->db->query("DELETE FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int)$customer_id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "customer_activity WHERE customer_id = '" . (int)$customer_id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "customer_affiliate WHERE customer_id = '" . (int)$customer_id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "customer_approval WHERE customer_id = '" . (int)$customer_id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "customer_reward WHERE customer_id = '" . (int)$customer_id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "customer_transaction WHERE customer_id = '" . (int)$customer_id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "customer_ip WHERE customer_id = '" . (int)$customer_id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "address WHERE customer_id = '" . (int)$customer_id . "'");
    }

    public function getCustomer($customer_id)
    {
        return Customer::find($customer_id)->toArray();
    }

    public function getCustomerByEmail($email)
    {
        $query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "customer WHERE LCASE(email) = '" . $this->db->escape(utf8_strtolower($email)) . "'");

        return $query->row;
    }

    public function getCustomers($data = array())
    {
        $sql = "
    SELECT
        c.*
        ,CASE c.country_id
            WHEN 222 THEN 'UK'
            ELSE country.iso_code_2
            END AS 'country_name'
        ,cgd.*, CONCAT(c.firstname, ' ', c.lastname) AS `name`
        ,CONCAT(c.nickname, '(', c.user_number,')') AS nickname
        ,cgd.name AS customer_group
        ,(CASE WHEN ctc.`is_partner` IS NULL THEN 0 ELSE ctc.`is_partner` END) AS is_partner
        ,IFNULL(ctc.screenname, '') AS screenname
    FROM " . DB_PREFIX . "customer c
    LEFT JOIN " . DB_PREFIX . "customer_group_description cgd ON (c.customer_group_id = cgd.customer_group_id)
    LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer ctc ON c.`customer_id` = ctc.`customer_id`
    LEFT JOIN " . DB_PREFIX . "country AS country ON country.country_id=c.country_id";

        if (!empty($data['filter_affiliate'])) {
            $sql .= " LEFT JOIN " . DB_PREFIX . "customer_affiliate ca ON (c.customer_id = ca.customer_id)";
        }

        $sql .= " WHERE cgd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

        $implode = array();

        if (!empty($data['filter_nickname'])) {
            $implode[] = "(c.nickname LIKE '%" . $this->db->escape($data['filter_nickname']) . "%' OR c.user_number LIKE '%" . $this->db->escape($data['filter_nickname']) . "%')";
        }

        if (!empty($data['filter_name'])) {
            $implode[] = "CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        }

        if (!empty($data['filter_email'])) {
            $implode[] = "c.email LIKE '" . $this->db->escape($data['filter_email']) . "%'";
        }

        if (isset($data['filter_newsletter']) && !is_null($data['filter_newsletter'])) {
            $implode[] = "c.newsletter = '" . (int)$data['filter_newsletter'] . "'";
        }

        if (!empty($data['filter_customer_group_id'])) {
            $implode[] = "c.customer_group_id = '" . (int)$data['filter_customer_group_id'] . "'";
        }

        if (!empty($data['filter_affiliate'])) {
            $implode[] = "ca.status = '" . (int)$data['filter_affiliate'] . "'";
        }

        if (!empty($data['filter_ip'])) {
            $implode[] = "c.customer_id IN (SELECT customer_id FROM " . DB_PREFIX . "customer_ip WHERE ip = '" . $this->db->escape($data['filter_ip']) . "')";
        }

        if (isset($data['filter_status']) && $data['filter_status'] !== '') {
            $implode[] = "c.status = '" . (int)$data['filter_status'] . "'";
        }

        if (!empty($data['filter_date_added'])) {
            $implode[] = "DATE(c.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
        }

        if (!empty($data['filter_screenname'])) {
            $implode[] = "ctc.screenname LIKE '%" . $this->db->escape($data['filter_screenname']) . "%'";
        }

        if (isset($data['filter_is_partner']) && strlen($data['filter_is_partner']) > 0) {
            if ($data['filter_is_partner'] == 0) {
                $implode[] = 'ctc.is_partner IS NULL';
            } elseif ($data['filter_is_partner'] == 1) {
                $implode[] = 'ctc.is_partner IS NOT NULL';
            }
        }

        if (!empty($data['filter_country_id'])) {
            $implode[] = "c.country_id=".intval($data['filter_country_id']);
        }

        if (!empty($data['filter_accounting_type_id'])) {
            $implode[] = "c.accounting_type=".intval($data['filter_accounting_type_id']);
        }

        if ($implode) {
            $sql .= " AND " . implode(" AND ", $implode);
        }

        $sort_data = array(
            'nickname',
            'name',
            'c.email',
            'customer_group',
            'c.status',
            'c.ip',
            'c.date_added',
            'ctc.is_partner',
            'ctc.screenname',
            'c.country_id'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY name";
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
                $data['limit'] = $this->config->get('config_limit_admin');
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getAddress($address_id)
    {
        $address_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "address WHERE address_id = '" . (int)$address_id . "'");

        if ($address_query->num_rows) {
            $country_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE country_id = '" . (int)$address_query->row['country_id'] . "'");

            if ($country_query->num_rows) {
                $country = $country_query->row['name'];
                $iso_code_2 = $country_query->row['iso_code_2'];
                $iso_code_3 = $country_query->row['iso_code_3'];
                $address_format = $country_query->row['address_format'];
            } else {
                $country = '';
                $iso_code_2 = '';
                $iso_code_3 = '';
                $address_format = '';
            }

            $zone_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE zone_id = '" . (int)$address_query->row['zone_id'] . "'");

            if ($zone_query->num_rows) {
                $zone = $zone_query->row['name'];
                $zone_code = $zone_query->row['code'];
            } else {
                $zone = '';
                $zone_code = '';
            }

            return array(
                'address_id' => $address_query->row['address_id'],
                'customer_id' => $address_query->row['customer_id'],
                'firstname' => $address_query->row['firstname'],
                'lastname' => $address_query->row['lastname'],
                'company' => $address_query->row['company'],
                'address_1' => $address_query->row['address_1'],
                'address_2' => $address_query->row['address_2'],
                'postcode' => $address_query->row['postcode'],
                'city' => $address_query->row['city'],
                'zone_id' => $address_query->row['zone_id'],
                'zone' => $zone,
                'zone_code' => $zone_code,
                'country_id' => $address_query->row['country_id'],
                'country' => $country,
                'iso_code_2' => $iso_code_2,
                'iso_code_3' => $iso_code_3,
                'address_format' => $address_format,
                'custom_field' => json_decode($address_query->row['custom_field'], true)
            );
        }
    }

    public function getAddresses($customer_id)
    {
        $address_data = array();

        $query = $this->db->query("SELECT address_id FROM " . DB_PREFIX . "address WHERE customer_id = '" . (int)$customer_id . "'");

        foreach ($query->rows as $result) {
            $address_info = $this->getAddress($result['address_id']);

            if ($address_info) {
                $address_data[$result['address_id']] = $address_info;
            }
        }

        return $address_data;
    }

    public function getTotalCustomers($data = array())
    {
        $sql = "
    SELECT
        COUNT(c.customer_id) AS total
    FROM " . DB_PREFIX . "customer AS c
    LEFT JOIN ".DB_PREFIX."customerpartner_to_customer AS ctc ON ctc.customer_id=c.customer_id";

        $implode = array();

        if (!empty($data['filter_name'])) {
            $implode[] = "CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        }

        if (!empty($data['filter_nickname'])) {
            $implode[] = "(c.nickname LIKE '%" . $this->db->escape($data['filter_nickname']) . "%' OR c.user_number LIKE '%" . $this->db->escape($data['filter_nickname']) . "%')";
        }

        if (!empty($data['filter_email'])) {
            $implode[] = "c.email LIKE '" . $this->db->escape($data['filter_email']) . "%'";
        }

        if (isset($data['filter_newsletter']) && !is_null($data['filter_newsletter'])) {
            $implode[] = "c.newsletter = '" . (int)$data['filter_newsletter'] . "'";
        }

        if (!empty($data['filter_customer_group_id'])) {
            $implode[] = "c.customer_group_id = '" . (int)$data['filter_customer_group_id'] . "'";
        }

        if (!empty($data['filter_ip'])) {
            $implode[] = "c.customer_id IN (SELECT customer_id FROM " . DB_PREFIX . "customer_ip WHERE ip = '" . $this->db->escape($data['filter_ip']) . "')";
        }

        if (isset($data['filter_status']) && $data['filter_status'] !== '') {
            $implode[] = "c.status = '" . (int)$data['filter_status'] . "'";
        }

        if (!empty($data['filter_date_added'])) {
            $implode[] = "DATE(c.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
        }

        if (!empty($data['filter_screenname'])) {
            $implode[] = "ctc.screenname LIKE '%" . $this->db->escape($data['filter_screenname']) . "%'";
        }

        if (isset($data['filter_is_partner']) && strlen($data['filter_is_partner']) > 0) {
            if ($data['filter_is_partner'] == 0) {
                $implode[] = 'ctc.is_partner IS NULL';
            } elseif ($data['filter_is_partner'] == 1) {
                $implode[] = 'ctc.is_partner IS NOT NULL';
            }
        }

        if (!empty($data['filter_country_id'])) {
            $implode[] = "c.country_id=".intval($data['filter_country_id']);
        }

        if (!empty($data['filter_accounting_type_id'])) {
            $implode[] = "c.accounting_type=".intval($data['filter_accounting_type_id']);
        }

        if ($implode) {
            $sql .= " WHERE " . implode(" AND ", $implode);
        }

        $query = $this->db->query($sql);

        return $query->row['total'];
    }

    public function getAffliateByTracking($tracking)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer_affiliate WHERE tracking = '" . $this->db->escape($tracking) . "'");

        return $query->row;
    }

    public function getAffiliate($customer_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer_affiliate WHERE customer_id = '" . (int)$customer_id . "'");

        return $query->row;
    }

    public function getAffiliates($data = array())
    {
        $sql = "SELECT DISTINCT *, CONCAT(c.firstname, ' ', c.lastname) AS name FROM " . DB_PREFIX . "customer_affiliate ca LEFT JOIN " . DB_PREFIX . "customer c ON (ca.customer_id = c.customer_id)";

        $implode = array();

        if (!empty($data['filter_name'])) {
            $implode[] = "CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        }

        if ($implode) {
            $sql .= " WHERE " . implode(" AND ", $implode);
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

        $query = $this->db->query($sql . "ORDER BY name");

        return $query->rows;
    }

    public function getTotalAffiliates($data = array())
    {
        $sql = "SELECT DISTINCT COUNT(*) AS total FROM " . DB_PREFIX . "customer_affiliate ca LEFT JOIN " . DB_PREFIX . "customer c ON (ca.customer_id = c.customer_id)";

        $implode = array();

        if (!empty($data['filter_name'])) {
            $implode[] = "CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        }

        if ($implode) {
            $sql .= " WHERE " . implode(" AND ", $implode);
        }

        $query = $this->db->query($sql);
        return $query->row['total'];
    }

    public function getTotalAddressesByCustomerId($customer_id)
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "address WHERE customer_id = '" . (int)$customer_id . "'");

        return $query->row['total'];
    }

    public function getTotalAddressesByCountryId($country_id)
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "address WHERE country_id = '" . (int)$country_id . "'");

        return $query->row['total'];
    }

    public function getTotalAddressesByZoneId($zone_id)
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "address WHERE zone_id = '" . (int)$zone_id . "'");

        return $query->row['total'];
    }

    public function getTotalCustomersByCustomerGroupId($customer_group_id)
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer WHERE customer_group_id = '" . (int)$customer_group_id . "'");

        return $query->row['total'];
    }

    public function addHistory($customer_id, $comment)
    {
        $this->db->query("INSERT INTO " . DB_PREFIX . "customer_history SET customer_id = '" . (int)$customer_id . "', comment = '" . $this->db->escape(strip_tags($comment)) . "', date_added = NOW()");
    }

    public function getHistories($customer_id, $start = 0, $limit = 10)
    {
        if ($start < 0) {
            $start = 0;
        }

        if ($limit < 1) {
            $limit = 10;
        }

        $query = $this->db->query("SELECT comment, date_added FROM " . DB_PREFIX . "customer_history WHERE customer_id = '" . (int)$customer_id . "' ORDER BY date_added DESC LIMIT " . (int)$start . "," . (int)$limit);

        return $query->rows;
    }

    public function getTotalHistories($customer_id)
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer_history WHERE customer_id = '" . (int)$customer_id . "'");

        return $query->row['total'];
    }

    public function addTransaction($customer_id, $description = '', $amount = '', $order_id = 0)
    {
        $this->db->query("INSERT INTO " . DB_PREFIX . "customer_transaction SET customer_id = '" . (int)$customer_id . "', order_id = '" . (int)$order_id . "', description = '" . $this->db->escape($description) . "', amount = '" . (float)$amount . "', date_added = NOW()");
    }

    public function deleteTransactionByOrderId($order_id)
    {
        $this->db->query("DELETE FROM " . DB_PREFIX . "customer_transaction WHERE order_id = '" . (int)$order_id . "'");
    }

    public function getTransactions($customer_id, $start = 0, $limit = 10)
    {
        if ($start < 0) {
            $start = 0;
        }

        if ($limit < 1) {
            $limit = 10;
        }

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer_transaction WHERE customer_id = '" . (int)$customer_id . "' ORDER BY date_added DESC LIMIT " . (int)$start . "," . (int)$limit);

        return $query->rows;
    }

    public function getTotalTransactions($customer_id)
    {
        $query = $this->db->query("SELECT COUNT(*) AS total  FROM " . DB_PREFIX . "customer_transaction WHERE customer_id = '" . (int)$customer_id . "'");

        return $query->row['total'];
    }

    public function getTransactionTotal($customer_id)
    {
        $query = $this->db->query("SELECT SUM(amount) AS total FROM " . DB_PREFIX . "customer_transaction WHERE customer_id = '" . (int)$customer_id . "'");

        return $query->row['total'];
    }

    public function getTotalTransactionsByOrderId($order_id)
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer_transaction WHERE order_id = '" . (int)$order_id . "'");

        return $query->row['total'];
    }

    public function addReward($customer_id, $description = '', $points = '', $order_id = 0)
    {
        $this->db->query("INSERT INTO " . DB_PREFIX . "customer_reward SET customer_id = '" . (int)$customer_id . "', order_id = '" . (int)$order_id . "', points = '" . (int)$points . "', description = '" . $this->db->escape($description) . "', date_added = NOW()");
    }

    public function deleteReward($order_id)
    {
        $this->db->query("DELETE FROM " . DB_PREFIX . "customer_reward WHERE order_id = '" . (int)$order_id . "' AND points > 0");
    }

    public function getRewards($customer_id, $start = 0, $limit = 10)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer_reward WHERE customer_id = '" . (int)$customer_id . "' ORDER BY date_added DESC LIMIT " . (int)$start . "," . (int)$limit);

        return $query->rows;
    }

    public function getTotalRewards($customer_id)
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer_reward WHERE customer_id = '" . (int)$customer_id . "'");

        return $query->row['total'];
    }

    public function getRewardTotal($customer_id)
    {
        $query = $this->db->query("SELECT SUM(points) AS total FROM " . DB_PREFIX . "customer_reward WHERE customer_id = '" . (int)$customer_id . "'");

        return $query->row['total'];
    }

    public function getTotalCustomerRewardsByOrderId($order_id)
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer_reward WHERE order_id = '" . (int)$order_id . "' AND points > 0");

        return $query->row['total'];
    }

    public function getIps($customer_id, $start = 0, $limit = 10)
    {
        if ($start < 0) {
            $start = 0;
        }
        if ($limit < 1) {
            $limit = 10;
        }

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer_ip WHERE customer_id = '" . (int)$customer_id . "' ORDER BY date_added DESC LIMIT " . (int)$start . "," . (int)$limit);

        return $query->rows;
    }

    public function getTotalIps($customer_id)
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer_ip WHERE customer_id = '" . (int)$customer_id . "'");

        return $query->row['total'];
    }

    public function getTotalCustomersByIp($ip)
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer_ip WHERE ip = '" . $this->db->escape($ip) . "'");

        return $query->row['total'];
    }

    public function getTotalLoginAttempts($email)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer_login` WHERE `email` = '" . $this->db->escape($email) . "'");

        return $query->row;
    }

    public function deleteLoginAttempts($email)
    {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "customer_login` WHERE `email` = '" . $this->db->escape($email) . "'");
    }

    public function getSellersByCustomerId($customer_id, $filter_data)
    {
        $sql = "SELECT bts.*, CONCAT(sc.`firstname`,' ',sc.`lastname`) AS seller_name, sc.`email` AS seller_email, CONCAT(cc.`firstname`,' ',cc.`lastname`) AS buyer_name,cc.`email` AS buyer_email FROM `". DB_PREFIX . "buyer_to_seller` bts LEFT JOIN `". DB_PREFIX . "customer` sc ON bts.`seller_id` = sc.`customer_id` LEFT JOIN `" . DB_PREFIX . "customer` cc ON bts.`buyer_id` = cc.`customer_id`  WHERE bts.`buyer_id` = " . $customer_id;
        if (isset($filter_data))
        {
            if (isset($filter_data["filter_customer_name"]) && $filter_data["filter_customer_name"] != "")
            {
                $sql .= " AND (sc.`firstname` LIKE '%" . $filter_data["filter_customer_name"] . "%' OR sc.`lastname` LIKE '%" . $filter_data["filter_customer_name"] . "%' OR CONCAT(sc.`firstname`,' ',sc.`lastname`) LIKE '%" . $filter_data["filter_customer_name"] . "%') ";
            }
            if(isset($filter_data["filter_email"]) && $filter_data["filter_email"] != "")
            {
                $sql .= " AND (sc.`email` LIKE '%" . $filter_data["filter_email"] . "%') ";
            }
            $sql .= " ORDER BY " . $filter_data["sort"] . " " . $filter_data["order"];
            if (isset($filter_data['page_num']) && $filter_data['page_limit'])
            {
                $sql .= " LIMIT " . (($filter_data['page_num'] - 1) * $filter_data['page_limit']) . "," . $filter_data['page_limit'];
            }
        }
        return $this->db->query($sql);
    }

    public function getSellersTotalByCustomerId($customer_id, $filter_data)
    {
        $sql = "SELECT COUNT(*) AS total FROM `". DB_PREFIX . "buyer_to_seller` bts LEFT JOIN `". DB_PREFIX . "customer` sc ON bts.`seller_id` = sc.`customer_id` LEFT JOIN `" . DB_PREFIX . "customer` cc ON bts.`buyer_id` = cc.`customer_id`  WHERE bts.`buyer_id` = " . $customer_id;
        if (isset($filter_data))
        {
            if (isset($filter_data["filter_customer_name"]) && $filter_data["filter_customer_name"] != "")
            {
                $sql .= " AND (sc.`firstname` LIKE '%" . $filter_data["filter_customer_name"] . "%' OR sc.`lastname` LIKE '%" . $filter_data["filter_customer_name"] . "%'  OR CONCAT(sc.`firstname`,' ',sc.`lastname`) LIKE '%" . $filter_data["filter_customer_name"] . "%') ";
            }
            if (isset($filter_data["filter_email"]) && $filter_data["filter_email"] != "")
            {
                $sql .= " AND (sc.`email` LIKE '%" . $filter_data["filter_email"] . "%') ";
            }
            $sql .= " ORDER BY " . $filter_data["sort"] . " " . $filter_data["order"];
        }
        $result = $this->db->query($sql);
        return $result->rows[0]['total'];
    }

    public function getBuyersByCustomerId($customer_id, $filter_data)
    {
        $sql = "SELECT bts.*,
CONCAT(sc.`firstname`,' ',sc.`lastname`) AS seller_name,
 sc.`email` AS seller_email,
  CONCAT(cc.`firstname`,' ',cc.`lastname`) AS buyer_name,
  cc.`email` AS buyer_email
  FROM `". DB_PREFIX . "buyer_to_seller` bts
  LEFT JOIN `". DB_PREFIX . "customer` sc
  ON bts.`seller_id` = sc.`customer_id`
  LEFT JOIN `" . DB_PREFIX . "customer` cc
  ON bts.`buyer_id` = cc.`customer_id`
  WHERE bts.`seller_id` = " . $customer_id;
        if (isset($filter_data))
        {
            if (isset($filter_data["filter_customer_name"]) && $filter_data["filter_customer_name"] != "")
            {
                $sql .= " AND (cc.`firstname` LIKE '%" . $filter_data["filter_customer_name"] . "%' OR cc.`lastname` LIKE '%" . $filter_data["filter_customer_name"] . "%' OR CONCAT(cc.`firstname`,' ',cc.`lastname`) LIKE '%" . $filter_data["filter_customer_name"] . "%') ";
            }
            if (isset($filter_data["filter_email"]) && $filter_data["filter_email"] != "")
            {
                $sql .= " AND (cc.`email` LIKE '%" . $filter_data["filter_email"] . "%') ";
            }
            $sql .= " ORDER BY " . $filter_data["sort"] . " " . $filter_data["order"];
            if (isset($filter_data['page_num']) && $filter_data['page_limit'])
            {
                $sql .= " LIMIT " . (($filter_data['page_num'] - 1) * $filter_data['page_limit']) . "," . $filter_data['page_limit'];
            }
        }
        return $this->db->query($sql);
    }

    public function getBuyersTotalByCustomerId($customer_id, $filter_data)
    {
        $sql = "SELECT COUNT(*) AS total FROM `". DB_PREFIX . "buyer_to_seller` bts LEFT JOIN `". DB_PREFIX . "customer` sc ON bts.`seller_id` = sc.`customer_id` LEFT JOIN `" . DB_PREFIX . "customer` cc ON bts.`buyer_id` = cc.`customer_id`  WHERE bts.`seller_id` = " . $customer_id;
        if (isset($filter_data))
        {
            if (isset($filter_data["filter_customer_name"]) && $filter_data["filter_customer_name"] != "")
            {
                $sql .= " AND (cc.`firstname` LIKE '%" . $filter_data["filter_customer_name"] . "%' OR cc.`lastname` LIKE '%" . $filter_data["filter_customer_name"] . "%'  OR CONCAT(cc.`firstname`,' ',cc.`lastname`) LIKE '%" . $filter_data["filter_customer_name"] . "%') ";
            }
            if (isset($filter_data["filter_email"]) && $filter_data["filter_email"] != "")
            {
                $sql .= " AND (cc.`email` LIKE '%" . $filter_data["filter_email"] . "%') ";
            }
            $sql .= " ORDER BY " . $filter_data["sort"] . " " . $filter_data["order"];
        }
        $result = $this->db->query($sql);
        return $result->rows[0]['total'];
    }

    public function updateBuyerInfo($updateDate)
    {
        $sql = "UPDATE `" . DB_PREFIX . "buyer_to_seller` bts SET bts.`buy_status` = " . $updateDate['buy_status'] . ", bts.`price_status` = " . $updateDate['price_status'] . ", bts.`buyer_control_status` = " . $updateDate['buyer_control_status'] . ", bts.`seller_control_status` = " . $updateDate['seller_control_status'] . ", bts.`discount` = " . $updateDate['discount'] . " WHERE bts.`id` = " . $updateDate['id'];
        $this->db->query($sql);
    }

    public function updateSellerInfo($updateDate)
    {
        $sql = "UPDATE `" . DB_PREFIX . "buyer_to_seller` bts SET bts.`account` = '" . $updateDate['account'] . "', bts.`pwd` = '" . $updateDate['pwd'] . "', bts.`buyer_control_status` = " . $updateDate['buyer_control_status'] . ", bts.`seller_control_status` = " . $updateDate['seller_control_status'] . " WHERE bts.`id` = " . $updateDate['id'];
        $this->db->query($sql);
    }

    public function getBuyersNotInCustomerId($customer_id, $filter_data)
    {
        $sql = "SELECT c.`customer_id`, CONCAT(c.`firstname`, ' ', c.`lastname`) AS customer_name, c.`email` FROM `" . DB_PREFIX . "customer` c WHERE c.`customer_id` NOT IN (SELECT ctc.`customer_id` AS customer_id FROM `" . DB_PREFIX . "customerpartner_to_customer` ctc WHERE ctc.`is_partner` = 1 UNION SELECT bts.`buyer_id` AS customer_id FROM `" . DB_PREFIX . "buyer_to_seller` bts WHERE bts.`seller_id` = " . $customer_id . ")";
        if (isset($filter_data['customer_email'])) {
            $sql .= " AND c.`email` LIKE '%" . $filter_data['customer_email'] . "%'";
        }
        if (isset($filter_data['page_num']) && $filter_data['page_limit'])
        {
            $sql .= " LIMIT " . (($filter_data['page_num'] - 1) * $filter_data['page_limit']) . "," . $filter_data['page_limit'];
        }
        return $this->db->query($sql);
    }

    public function getTotalBuyersNotInCustomerId($customer_id, $filter_data)
    {
        $sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "customer` c WHERE c.`customer_id` NOT IN (SELECT ctc.`customer_id` AS customer_id FROM `" . DB_PREFIX . "customerpartner_to_customer` ctc WHERE ctc.`is_partner` = 1 UNION SELECT bts.`buyer_id` AS customer_id FROM `" . DB_PREFIX . "buyer_to_seller` bts WHERE bts.`seller_id` = " . $customer_id . ")";
        if (isset($filter_data['customer_email'])) {
            $sql .= " AND c.`email` LIKE '%" . $filter_data['customer_email'] . "%'";
        }
        $result = $this->db->query($sql);
        return $result->rows[0]['total'];
    }

    public function deleteBuyerToSeller($ids) {
        if (isset($ids)) {
            $sql = "DELETE FROM `oc_buyer_to_seller` WHERE id IN (";
            foreach ($ids as $id) {
                $sql .= $id . ",";
            }
            $sql = substr($sql, 0, strlen($sql) - 1);
            $sql .= ")";
            $this->db->query($sql);
        }
    }

    public function getSellersNotInCustomerId($customer_id, $filter_data)
    {
        $sql = "SELECT c.`customer_id`, CONCAT(c.`firstname`, ' ', c.`lastname`) AS customer_name, c.`email` FROM `". DB_PREFIX . "customer` c WHERE c.`customer_id` IN (SELECT ctc.`customer_id` FROM `" . DB_PREFIX . "customerpartner_to_customer` ctc WHERE ctc.`customer_id` NOT IN (SELECT bts.`seller_id` AS customer_id FROM `" . DB_PREFIX . "buyer_to_seller` bts WHERE bts.`buyer_id` = " . $customer_id ."))";
        if (isset($filter_data['customer_email'])) {
            $sql .= " AND c.`email` LIKE '%" . $filter_data['customer_email'] . "%'";
        }
        if (isset($filter_data['page_num']) && $filter_data['page_limit'])
        {
            $sql .= " LIMIT " . (($filter_data['page_num'] - 1) * $filter_data['page_limit']) . "," . $filter_data['page_limit'];
        }
        return $this->db->query($sql);
    }

    public function getTotalSellersNotInCustomerId($customer_id, $filter_data)
    {
        $sql = "SELECT COUNT(*) AS total FROM `". DB_PREFIX . "customer` c WHERE c.`customer_id` IN (SELECT ctc.`customer_id` FROM `" . DB_PREFIX . "customerpartner_to_customer` ctc WHERE ctc.`customer_id` NOT IN (SELECT bts.`seller_id` AS customer_id FROM `" . DB_PREFIX . "buyer_to_seller` bts WHERE bts.`buyer_id` = " . $customer_id ."))";
        if (isset($filter_data['customer_email'])) {
            $sql .= " AND c.`email` LIKE '%" . $filter_data['customer_email'] . "%'";
        }
        $result = $this->db->query($sql);
        return $result->rows[0]['total'];
    }

    /**
     * @param int $seller_id
     * @param array $customer_ids buyer的customer_id
     */
    public function addBuyersToSeller(int $seller_id, array $customer_ids)
    {
        if (empty($seller_id) || empty($customer_ids)) {
            return;
        }

        //排除被禁用的customer
        $customer_ids = $this->getActiveCustomer($customer_ids);

        $update_buyers = $this->orm->table('oc_buyer_to_seller')
            ->where('seller_id', '=', $seller_id)
            ->whereIn('buyer_id', $customer_ids)
            ->pluck('buyer_id')
            ->toArray();
        $this->orm->table('oc_buyer_to_seller')
            ->where('seller_id', $seller_id)
            ->whereIn('buyer_id', $update_buyers)
            ->update([
                'buy_status' => 1,
                'price_status' => 1,
                'buyer_control_status' => 1,
                'seller_control_status' => 1,
                'discount' => 1,
            ]);
        foreach ($customer_ids as $key => $buyer_id) {
            if (in_array($buyer_id, $update_buyers)) {
                unset($customer_ids[$key]);
            }
        }

        if ($customer_ids && count($customer_ids) > 0) {
            $sql = "INSERT INTO `" . DB_PREFIX . "buyer_to_seller` (buyer_id, seller_id, buy_status, price_status, buyer_control_status, seller_control_status, discount) SELECT customer_id, " . $seller_id . ", 1,1,1,1,1 FROM `oc_customer` c WHERE c.`customer_id` IN (";
            foreach ($customer_ids as $customer_id) {
                $sql .= $customer_id . ',';
            }
            $sql = substr($sql,0,strlen($sql) - 1);
            $sql .= ')';
            $this->db->query($sql);
        }
    }

    /**
     * @param int $buyer_id
     * @param array $customer_ids seller的customer_id
     */
    public function addSellersToSeller($buyer_id, $customer_ids)
    {
        if (empty($buyer_id) || empty($customer_ids)) {
            return;
        }

        //排除被禁用的customer
        $customer_ids = $this->getActiveCustomer($customer_ids);

        $update_sellers = $this->orm->table('oc_buyer_to_seller')
            ->where('buyer_id', '=', $buyer_id)
            ->whereIn('seller_id', $customer_ids)
            ->pluck('seller_id')
            ->toArray();
        $this->orm->table('oc_buyer_to_seller')
            ->where('buyer_id', $buyer_id)
            ->whereIn('seller_id', $update_sellers)
            ->update([
                'buy_status' => 1,
                'price_status' => 1,
                'buyer_control_status' => 1,
                'seller_control_status' => 1,
                'discount' => 1,
            ]);
        foreach ($customer_ids as $key => $seller_id) {
            if (in_array($seller_id, $update_sellers)) {
                unset($customer_ids[$key]);
            }
        }

        if ($customer_ids && count($customer_ids) > 0) {
            $sql = "INSERT INTO `" . DB_PREFIX . "buyer_to_seller` (buyer_id, seller_id, buy_status, price_status, buyer_control_status, seller_control_status, discount) SELECT " . $buyer_id . ",customer_id, 1,1,1,1,1 FROM `oc_customer` c WHERE c.`customer_id` IN (";
            foreach ($customer_ids as $customer_id) {
                $sql .= $customer_id . ',';
            }
            $sql = substr($sql,0,strlen($sql) - 1);
            $sql .= ')';
            $this->db->query($sql);
        }
    }

    public function getTotalBuyersNotAssociateForThisSeller($customer_id,$filter_data)
    {
        $sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "customer` oc "
               ." WHERE oc.`status` = 1 AND EXISTS (SELECT 1 FROM `" . DB_PREFIX . "customer` oco WHERE oc.`country_id` = oco.`country_id` AND oco.`customer_id` = ". $customer_id . ") "
               ." AND NOT EXISTS (SELECT 1 FROM `" . DB_PREFIX . "customerpartner_to_customer` octo WHERE octo.`customer_id` = oc.`customer_id` AND octo.`is_partner` = 1)"
               ." AND NOT EXISTS (SELECT 1 FROM `" . DB_PREFIX . "buyer_to_seller` obts WHERE obts.`buyer_id` = oc.`customer_id` AND obts.`seller_id` = " . $customer_id . " )";
        if (isset($filter_data['customer_email'])) {
            $sql .= " AND oc.`email` LIKE '%" . $filter_data['customer_email'] . "%'";
        }
        $result = $this->db->query($sql);
        return $result->rows[0]['total'];
    }

    public function getBuyersNotAssociateForThisSeller($customer_id, $filter_data)
    {
        $sql = "SELECT oc.`customer_id`, CONCAT(oc.`firstname`, ' ', oc.`lastname`) AS customer_name, oc.`email` FROM `" . DB_PREFIX . "customer` oc "
            ." WHERE oc.`status` = 1 AND EXISTS (SELECT 1 FROM `" . DB_PREFIX . "customer` oco WHERE oc.`country_id` = oco.`country_id` AND oco.`customer_id` = ". $customer_id . ") "
            ." AND NOT EXISTS (SELECT 1 FROM `" . DB_PREFIX . "customerpartner_to_customer` octo WHERE octo.`customer_id` = oc.`customer_id` AND octo.`is_partner` = 1)"
            ." AND NOT EXISTS (SELECT 1 FROM `" . DB_PREFIX . "buyer_to_seller` obts WHERE obts.`buyer_id` = oc.`customer_id` AND obts.`seller_id` = " . $customer_id . " )";
        if (isset($filter_data['customer_email'])) {
            $sql .= " AND oc.`email` LIKE '%" . $filter_data['customer_email'] . "%'";
        }
        if (isset($filter_data['page_num']) && $filter_data['page_limit'])
        {
            $sql .= " LIMIT " . (($filter_data['page_num'] - 1) * $filter_data['page_limit']) . "," . $filter_data['page_limit'];
        }
        return $this->db->query($sql);
    }

    public function getTotalSellersNotAssociateForThisBuyer($customer_id, $filter_data)
    {
        $sql = "SELECT COUNT(*) AS total FROM `". DB_PREFIX . "customer` oc "
                ." WHERE oc.`status` = 1 AND oc.`customer_group_id` not in (17,18,19,20) AND EXISTS (SELECT 1 FROM `". DB_PREFIX . "customer` oco WHERE oc.`country_id` = oco.`country_id` AND oco.`customer_id` =  ". $customer_id . ")"
                ." AND EXISTS (SELECT 1 FROM `". DB_PREFIX . "customerpartner_to_customer` octc WHERE oc.`customer_id` = octc.`customer_id` AND octc.`is_partner` = 1)"
                ." AND NOT EXISTS (SELECT 1 FROM `". DB_PREFIX . "buyer_to_seller` obts WHERE oc.`customer_id` = obts.`seller_id` AND obts.`buyer_id` =  ". $customer_id . ")";
        if (isset($filter_data['customer_email'])) {
            $sql .= " AND oc.`email` LIKE '%" . $filter_data['customer_email'] . "%'";
        }
        $result = $this->db->query($sql);
        return $result->rows[0]['total'];
    }

    public function getSellersNotAssociateForThisBuyer($customer_id, $filter_data)
    {
        $sql = "SELECT oc.`customer_id`, CONCAT(oc.`firstname`, ' ', oc.`lastname`) AS customer_name, oc.`email` FROM `". DB_PREFIX . "customer` oc "
            ." WHERE oc.`status` = 1 AND oc.`customer_group_id` not in (17,18,19,20) AND EXISTS (SELECT 1 FROM `". DB_PREFIX . "customer` oco WHERE oc.`country_id` = oco.`country_id` AND oco.`customer_id` =  ". $customer_id . ")"
            ." AND EXISTS (SELECT 1 FROM `". DB_PREFIX . "customerpartner_to_customer` octc WHERE oc.`customer_id` = octc.`customer_id` AND octc.`is_partner` = 1)"
            ." AND NOT EXISTS (SELECT 1 FROM `". DB_PREFIX . "buyer_to_seller` obts WHERE oc.`customer_id` = obts.`seller_id` AND obts.`buyer_id` =  ". $customer_id . ")";
        if (isset($filter_data['customer_email'])) {
            $sql .= " AND oc.`email` LIKE '%" . $filter_data['customer_email'] . "%'";
        }
        if (isset($filter_data['page_num']) && $filter_data['page_limit'])
        {
            $sql .= " LIMIT " . (($filter_data['page_num'] - 1) * $filter_data['page_limit']) . "," . $filter_data['page_limit'];
        }
        return $this->db->query($sql);
    }

    /**
     * 检测数组中随机数是否可用
     * @param $random_array
     * @return array 可用的结果
     */
    public function testUniqueUserNumber($random_array){
        $query = $this->db->query("SELECT user_number FROM oc_customer WHERE user_number IN ('" . implode("','", $random_array) . "')");
        $result = array();
        if(isset($query->rows)){
            $array = array();
            foreach($query->rows as $value){
                $array[] =  (int)$value['user_number'];
            }
            $result = array_diff($random_array,$array);
        }
        return $result;
    }

    public function checkBuyersActive($buyers)
    {
        if (empty($buyers)||!is_array($buyers)) {
            return [];
        }

        return $this->orm->table('oc_customer as c')
            ->whereIn('c.customer_id', $buyers)
            ->whereNotExists(function ($query) {
                $query->from('oc_customerpartner_to_customer as s')
                    ->select(['s.customer_id'])
                    ->whereRaw('s.customer_id = c.customer_id');
            })
            ->pluck('c.customer_id')
            ->toArray();
    }

    public function getBuyersInGroup($seller_id, $buyer_group_id, $buyers)
    {
        return $this->orm->table('oc_customerpartner_buyer_group_link')
            ->where([
                ['seller_id', '=', $seller_id],
                ['buyer_group_id', '=', $buyer_group_id],
                ['status', '=', 1]
            ])
            ->whereIn('buyer_id', $buyers)
            ->pluck('buyer_id')
            ->toArray();
    }

    public function batchAddBuyerToDefaultBuyerGroup($seller_id, $buyers)
    {
        if (empty($seller_id) || empty($buyers) || !is_array($buyers)) {
            return;
        }
        $groupObj = $this->orm->table('oc_customerpartner_buyer_group')
            ->select(['id'])
            ->where([
                ['seller_id', '=', $seller_id],
                ['status', '=', 1],
                ['is_default', '=', 1]
            ])
            ->first();
        if (empty($groupObj)) {
            return;
        }

        $buyers = $this->checkBuyersActive($buyers);
        if (empty($buyers)) {
            return;
        }

        $inGroupBuyers = $this->getBuyersInGroup($seller_id, $groupObj->id, $buyers);
        if (!empty($inGroupBuyers)) {
            foreach ($buyers as $key=>$buyer) {
                if (in_array($buyer, $inGroupBuyers)) {
                    unset($buyers[$key]);
                }
            }
        }

        $temp = [
            'buyer_group_id' => $groupObj->id,
            'seller_id' => $seller_id,
            'status' => 1,
            'add_time' => date('Y-m-d H:i:s')
        ];
        $keyVal = [];
        foreach ($buyers as $buyer) {
            $temp['buyer_id'] = $buyer;
            $keyVal[] = $temp;
        }
        !empty($keyVal) && $this->orm->table('oc_customerpartner_buyer_group_link')
            ->insert($keyVal);
    }

    /**
     * @param int $customer_id
     * @param int $is_partner
     * @param int $buyer_to_seller_id
     * @return void
     */
    public function deleteDelicacyManagement($customer_id, $buyer_to_seller_id, $is_partner)
    {
        if (empty($customer_id) || empty($buyer_to_seller_id) || is_null($is_partner)) {
            return;
        }

        $btcWhere = [['id', '=', $buyer_to_seller_id]];
        if ($is_partner) {
            $btcWhere[] = ['seller_id', '=', $customer_id];
        } else {
            $btcWhere[] = ['buyer_id', '=', $customer_id];
        }
        $btcObj = $this->orm->table('oc_buyer_to_seller')
            ->where($btcWhere)
            ->first(['buyer_id', 'seller_id']);
        if (empty($btcObj) || empty($btcObj->buyer_id) || empty($btcObj->seller_id)) {
            return;
        }

        //删除分组
        $this->orm->table('oc_customerpartner_buyer_group_link')
            ->where([
                ['seller_id', '=', $btcObj->seller_id],
                ['buyer_id', '=', $btcObj->buyer_id],
                ['status', '=', 1]
            ])
            ->update(['status' => 0]);

        // 删除精细化相关
        $data = $this->orm->table('oc_delicacy_management')
            ->where([
                ['seller_id', '=', $btcObj->seller_id],
                ['buyer_id', '=', $btcObj->buyer_id],
            ])
            ->get(['*']);

        $keyValArr = [];
        $delicacyIDArr = [];
        $temp = [
            'type' => 2,
        ];
        foreach ($data as $item) {
            foreach ($this->getKeyVal() as $_k => $_v) {
                $temp[$_k] = $_v['is_real_value'] ? $_v['column'] : $item->{$_v['column']};
            }

            $keyValArr[] = $temp;
            $delicacyIDArr[] = $item->id;
        }

        !empty($delicacyIDArr) && $this->orm->table('oc_delicacy_management')
            ->whereIn('id', $delicacyIDArr)
            ->delete();
        !empty($keyValArr) && $this->orm->table('oc_delicacy_management_history')
            ->insert($keyValArr);
    }
    /**
     * @return array
     */
    private function getKeyVal()
    {
        return [
            'origin_id'       => ['column' => 'id', 'is_real_value' => 0],
            'seller_id'       => ['column' => 'seller_id', 'is_real_value' => 0],
            'buyer_id'        => ['column' => 'buyer_id', 'is_real_value' => 0],
            'product_id'      => ['column' => 'product_id', 'is_real_value' => 0],
            'current_price'   => ['column' => 'current_price', 'is_real_value' => 0],
            'product_display' => ['column' => 'product_display', 'is_real_value' => 0],
            'price'           => ['column' => 'price', 'is_real_value' => 0],
            'effective_time'  => ['column' => 'effective_time', 'is_real_value' => 0],
            'expiration_time' => ['column' => 'expiration_time', 'is_real_value' => 0],
            'origin_add_time' => ['column' => 'add_time', 'is_real_value' => 0],
            'add_time'        => ['column' => date('Y-m-d H:i:s'), 'is_real_value' => 1]
        ];
    }


    public function getActiveCustomer($customer_ids)
    {
        return $this->orm->table('oc_customer')
            ->where('status', '=', 1)
            ->whereIn('customer_id', $customer_ids)
            ->pluck('customer_id')
            ->toArray();
    }

}
