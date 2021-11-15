<?php
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Class ModelAccountCustomer
 * @property ModelAccountCustomerGroup $model_account_customer_group
 */
class ModelAccountCustomer extends Model {
	public function addCustomer($data) {
		if (isset($data['customer_group_id']) && is_array($this->config->get('config_customer_group_display')) && in_array($data['customer_group_id'], $this->config->get('config_customer_group_display'))) {
			$customer_group_id = $data['customer_group_id'];
		} else {
			$customer_group_id = $this->config->get('config_customer_group_id');
		}

		$this->load->model('account/customer_group');

		$customer_group_info = $this->model_account_customer_group->getCustomerGroup($customer_group_id);

		$this->db->query("INSERT INTO " . DB_PREFIX . "customer SET customer_group_id = '" . (int)$customer_group_id . "', store_id = '" . (int)$this->config->get('config_store_id') . "', language_id = '" . (int)$this->config->get('config_language_id') . "', firstname = '" . $this->db->escape($data['firstname']) . "', lastname = '" . $this->db->escape($data['lastname']) . "', email = '" . $this->db->escape($data['email']) . "', telephone = '" . $this->db->escape($data['telephone']) . "', custom_field = '" . $this->db->escape(isset($data['custom_field']['account']) ? json_encode($data['custom_field']['account']) : '') . "', salt = '" . $this->db->escape($salt = token(9)) . "', password = '" . $this->db->escape(sha1($salt . sha1($salt . sha1($data['password'])))) . "', newsletter = '" . (isset($data['newsletter']) ? (int)$data['newsletter'] : 0) . "', ip = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "', status = '" . (int)!$customer_group_info['approval'] . "', date_added = NOW()");

		$customer_id = $this->db->getLastId();

		if ($customer_group_info['approval']) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "customer_approval` SET customer_id = '" . (int)$customer_id . "', type = 'customer', date_added = NOW()");
		}

		return $customer_id;
	}

	public function editCustomer($customer_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "customer SET firstname = '" . $this->db->escape($data['firstname']) . "', lastname = '" . $this->db->escape($data['lastname']) . "', nickname = '" . $this->db->escape($data['nickname']) . "', email = '" . $this->db->escape($data['email']) . "', telephone = '" . $this->db->escape($data['telephone']) . "', custom_field = '" . $this->db->escape(isset($data['custom_field']['account']) ? json_encode($data['custom_field']['account']) : '') . "' WHERE customer_id = '" . (int)$customer_id . "'");
	}

	public function editPassword($email, $password) {
		$this->db->query("UPDATE " . DB_PREFIX . "customer SET salt = '" . $this->db->escape($salt = token(9)) . "', password = '" . $this->db->escape(sha1($salt . sha1($salt . sha1($password)))) . "', code = '' WHERE LOWER(email) = '" . $this->db->escape(utf8_strtolower($email)) . "'");
	}

	public function editAddressId($customer_id, $address_id) {
		$this->db->query("UPDATE " . DB_PREFIX . "customer SET address_id = '" . (int)$address_id . "' WHERE customer_id = '" . (int)$customer_id . "'");
	}

	public function editCode($email, $code) {
		$this->db->query("UPDATE `" . DB_PREFIX . "customer` SET code = '" . $this->db->escape($code) . "' WHERE LCASE(email) = '" . $this->db->escape(utf8_strtolower($email)) . "'");
	}

	public function editNewsletter($newsletter) {
		$this->db->query("UPDATE " . DB_PREFIX . "customer SET newsletter = '" . (int)$newsletter . "' WHERE customer_id = '" . (int)$this->customer->getId() . "'");
	}

	public function getCustomer($customer_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int)$customer_id . "'");

		return $query->row;
	}

    public function getCustomerNumber($customer_id) {
        $query = $this->db->query("SELECT user_number FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int)$customer_id . "'");

        return $query->row;
    }

	public function getCustomerByEmail($email) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer WHERE LOWER(email) = '" . $this->db->escape(utf8_strtolower($email)) . "'");

		return $query->row;
	}

	public function getCustomerByCode($code) {
		$query = $this->db->query("SELECT customer_id, firstname, lastname, email FROM `" . DB_PREFIX . "customer` WHERE code = '" . $this->db->escape($code) . "' AND code != ''");

		return $query->row;
	}

	public function getCustomerByToken($token) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer WHERE token = '" . $this->db->escape($token) . "' AND token != ''");

		return $query->row;
	}

	public function clearToken()
    {
        $this->db->query("UPDATE " . DB_PREFIX . "customer SET token = ''");
    }

    public function clearTokenByCustomerId(int $customerId)
    {
        return $this->orm->table('oc_customer')->where('customer_id', $customerId)->update(['token' => '']);
    }

	public function getTotalCustomersByEmail($email) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer WHERE LOWER(email) = '" . $this->db->escape(utf8_strtolower($email)) . "'");

		return $query->row['total'];
	}

	public function addTransaction($customer_id, $description, $amount = '', $order_id = 0) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "customer_transaction SET customer_id = '" . (int)$customer_id . "', order_id = '" . (float)$order_id . "', description = '" . $this->db->escape($description) . "', amount = '" . (float)$amount . "', date_added = NOW()");
	}

	public function deleteTransactionByOrderId($order_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "customer_transaction WHERE order_id = '" . (int)$order_id . "'");
	}

	public function getTransactionTotal($customer_id) {
		$query = $this->db->query("SELECT SUM(amount) AS total FROM " . DB_PREFIX . "customer_transaction WHERE customer_id = '" . (int)$customer_id . "'");

		return $query->row['total'];
	}

	public function getTotalTransactionsByOrderId($order_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer_transaction WHERE order_id = '" . (int)$order_id . "'");

		return $query->row['total'];
	}

	public function getRewardTotal($customer_id) {
		$query = $this->db->query("SELECT SUM(points) AS total FROM " . DB_PREFIX . "customer_reward WHERE customer_id = '" . (int)$customer_id . "'");

		return $query->row['total'];
	}

	public function getIps($customer_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer_ip` WHERE customer_id = '" . (int)$customer_id . "'");

		return $query->rows;
	}

	public function addLoginAttempt($email) {
        $hourtime = $this->db->escape(date("Y-m-d H:i:s", strtotime("-1 hour")));
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer_login WHERE email = '" . $this->db->escape(utf8_strtolower((string)$email)) . "' AND ip = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "' AND date_modified >= '" . $hourtime . "'");

		if (!$query->num_rows) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "customer_login SET email = '" . $this->db->escape(utf8_strtolower((string)$email)) . "', ip = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "', total = 1, date_added = '" . $this->db->escape(date('Y-m-d H:i:s')) . "', date_modified = '" . $this->db->escape(date('Y-m-d H:i:s')) . "'");
		} else {
			$this->db->query("UPDATE " . DB_PREFIX . "customer_login SET total = (total + 1), date_modified = '" . $this->db->escape(date('Y-m-d H:i:s')) . "' WHERE customer_login_id = '" . (int)$query->row['customer_login_id'] . "'");
		}
	}

	public function getLoginAttempts($email) {
        $hourtime = $this->db->escape(date("Y-m-d H:i:s", strtotime("-1 hour")));
        $sql = "
        SELECT
            GROUP_CONCAT(customer_login_id) AS customer_login_id
            ,email
            ,GROUP_CONCAT(ip) AS ip
            ,SUM(total) AS total
            ,MAX(date_modified) AS date_modified
        FROM `" . DB_PREFIX . "customer_login`
        WHERE email = '" . $this->db->escape(utf8_strtolower($email)) . "' AND date_modified >= '" . $hourtime . "'";
		$query = $this->db->query($sql);

		return $query->row;
	}

	public function deleteLoginAttempts($email) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "customer_login` WHERE email = '" . $this->db->escape(utf8_strtolower($email)) . "'");
	}

	public function addAffiliate($customer_id, $data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "customer_affiliate SET `customer_id` = '" . (int)$customer_id . "', `company` = '" . $this->db->escape($data['company']) . "', `website` = '" . $this->db->escape($data['website']) . "', `tracking` = '" . $this->db->escape(token(64)) . "', `commission` = '" . (float)$this->config->get('config_affiliate_commission') . "', `tax` = '" . $this->db->escape($data['tax']) . "', `payment` = '" . $this->db->escape($data['payment']) . "', `cheque` = '" . $this->db->escape($data['cheque']) . "', `paypal` = '" . $this->db->escape($data['paypal']) . "', `bank_name` = '" . $this->db->escape($data['bank_name']) . "', `bank_branch_number` = '" . $this->db->escape($data['bank_branch_number']) . "', `bank_swift_code` = '" . $this->db->escape($data['bank_swift_code']) . "', `bank_account_name` = '" . $this->db->escape($data['bank_account_name']) . "', `bank_account_number` = '" . $this->db->escape($data['bank_account_number']) . "', `status` = '" . (int)!$this->config->get('config_affiliate_approval') . "'");

		if ($this->config->get('config_affiliate_approval')) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "customer_approval` SET customer_id = '" . (int)$customer_id . "', type = 'affiliate', date_added = NOW()");
		}
	}

	public function editAffiliate($customer_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "customer_affiliate SET `company` = '" . $this->db->escape($data['company']) . "', `website` = '" . $this->db->escape($data['website']) . "', `commission` = '" . (float)$this->config->get('config_affiliate_commission') . "', `tax` = '" . $this->db->escape($data['tax']) . "', `payment` = '" . $this->db->escape($data['payment']) . "', `cheque` = '" . $this->db->escape($data['cheque']) . "', `paypal` = '" . $this->db->escape($data['paypal']) . "', `bank_name` = '" . $this->db->escape($data['bank_name']) . "', `bank_branch_number` = '" . $this->db->escape($data['bank_branch_number']) . "', `bank_swift_code` = '" . $this->db->escape($data['bank_swift_code']) . "', `bank_account_name` = '" . $this->db->escape($data['bank_account_name']) . "', `bank_account_number` = '" . $this->db->escape($data['bank_account_number']) . "' WHERE `customer_id` = '" . (int)$customer_id . "'");
	}

	public function getAffiliate($customer_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer_affiliate` WHERE `customer_id` = '" . (int)$customer_id . "'");

		return $query->row;
	}

	public function getAffiliateByTracking($tracking) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer_affiliate` WHERE `tracking` = '" . $this->db->escape($tracking) . "'");

		return $query->row;
	}

    public function addCustomerNew($data) {
        if (isset($data['customer_group_id']) && is_array($this->config->get('config_customer_group_display')) && in_array($data['customer_group_id'], $this->config->get('config_customer_group_display'))) {
            $customer_group_id = $data['customer_group_id'];
        } else {
            $customer_group_id = $this->config->get('config_customer_group_id');
        }

        $this->load->model('account/customer_group');

        $customer_group_info = $this->model_account_customer_group->getCustomerGroup($customer_group_id);


        $this->db->query("INSERT INTO " . DB_PREFIX . "customer SET customer_group_id = '" . (int)$customer_group_id . "', store_id = '" . (int)$this->config->get('config_store_id') . "', language_id = '" . (int)$this->config->get('config_language_id') . "', firstname = '" . $this->db->escape($data['firstname']) . "', lastname = '" . $this->db->escape($data['lastname']) . "', email = '" . $this->db->escape($data['email']) . "', nickname = '" . $this->db->escape($data['nickname'])  . "', telephone = '" . $this->db->escape($data['telephone']) . "', custom_field = '" . $this->db->escape(isset($data['custom_field']['account']) ? json_encode($data['custom_field']['account']) : '') . "', salt = '" . $this->db->escape($salt = token(9)) . "', password = '" . $this->db->escape(sha1($salt . sha1($salt . sha1($data['password'])))) . "', newsletter = '" . (isset($data['newsletter']) ? (int)$data['newsletter'] : 0) . "', ip = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "', status = '" . (int)!$customer_group_info['approval'] . "', date_added = NOW()");

        $customer_id = $this->db->getLastId();

        $this->db->query("UPDATE " . DB_PREFIX . "invitation SET invitee_customer_id = ".(int)$customer_id." WHERE invitation_code = '" .$data['invitationCode']. "'");

        //插入oc_address
        $sql = "INSERT INTO " . DB_PREFIX . "address SET customer_id = '".(int)$customer_id."', firstname = '" . $this->db->escape($data['firstname']) . "', lastname = '" . $this->db->escape($data['lastname'])."',company = '',address_1 = '".$this->db->escape($data['street'])."',address_2 = '',city = '".$this->db->escape($data['city'])."',postcode = '".$this->db->escape($data['zip'])."',country_id = ".(int)$data['address']['country_id'].",zone_id = ".(int)$data['address']['zone_id'].",custom_field = ''";
        $this->db->query($sql);
        $address_id = $this->db->getLastId();
        $this->db->query("UPDATE ".DB_PREFIX."customer SET address_id = ".(int)$address_id." where customer_id = ". (int)$customer_id );
        //插入信用卡表
        $this->db->query("INSERT INTO ".DB_PREFIX."credit SET customer_id = ". (int)$customer_id .",card_number = '".$this->db->escape($data['card_number']) ."',card_name = '".$this->db->escape($data['cardName'])."',valid_from = '".$this->db->escape($data['validFrom'])."',valid_to = '".$this->db->escape($data['validTo'])."',address = '".$this->db->escape($data['billingAddress'])."'");
        if ($customer_group_info['approval']) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "customer_approval` SET customer_id = '" . (int)$customer_id . "', type = 'customer', date_added = NOW()");
        }

        return $customer_id;
    }

    public function checkNicknameIsRepeat($nickname){
	    $query = $this->db->query("SELECT IF(COUNT(*)>0,TRUE,FALSE) AS is_repeat FROM oc_customer WHERE nickname = '".$this->db->escape($nickname)."'");
	    if($query->row){
            $row = $query->row;
	        if($row['is_repeat']){
                return true;
            }else{
	            return false;
            }
        }
    }

    /**
     * 获得用户昵称和用户编号。格式：昵称(用户编号)
     * @param int $customer_id
     * @return
     */
    public function getCustomerNicknameAndNumber($customer_id){
        $query = $this->db->query("SELECT CONCAT(cus.`nickname`,'(',cus.`user_number`,')') AS nickname FROM oc_customer cus WHERE cus.`customer_id` = " . (int)$customer_id);
        return $query->row['nickname'];
    }

    public function getInvitation($invitationCode){
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "invitation WHERE  invitation_code= '" . $invitationCode . "' and invitee_customer_id is null");

        return $query->row;
    }

    public function isUSBuyer(){
        return $this->db->query("SELECT * FROM  oc_customer cc
LEFT JOIN `oc_customer_group_description` cg
ON cc.customer_group_id = cg.customer_group_id
WHERE cg.customer_group_id=15 AND cc.customer_id=".$this->customer->getId())->row;

    }

    /**
     * 验证用户数是否为seller
     *
     * @param int $customerID
     * @return bool
     */
    public function checkIsSeller($customerID)
    {
        $count = $this->orm::table('oc_customerpartner_to_customer')
            ->where([
                ['customer_id', '=', $customerID],
                ['is_partner', '=', 1]
            ])
            ->count('*');
        if ($count) {
            return true;
        } else {
            return false;
        }

    }

    public function findCustomerNameByCustomerId($customer_id)
    {
        if (isset($customer_id)) {
            $sql = "SELECT IF(ISNULL(ctc.`screenname`),CONCAT(cus.`nickname`,'(',cus.`user_number`,')'),ctc.`screenname`) AS showname FROM oc_customer cus LEFT JOIN oc_customerpartner_to_customer ctc ON cus.`customer_id` = ctc.`customer_id`
                WHERE cus.customer_id = " . (int)$customer_id;
            $query = $this->db->query($sql);
            return $query->row['showname'];
        }
    }

    public function findCustomerNameByCustomerIds($customer_ids)
    {
        if (isset($customer_ids)) {
            $sql = "SELECT nickname,user_number FROM oc_customer  WHERE customer_id in  ($customer_ids)";
            return $this->db->query($sql)->rows;
        }
    }

    /**
     * 获取customer的group id
     *
     * @param int $customerId
     *
     * @return int|mixed
     */
    public function getCustomerGroupId($customerId)
    {
        $customer = $this->orm->table('oc_customer')
            ->where('customer_id', $customerId)
            ->first(['customer_group_id']);
        return $customer->customer_group_id ?? 0;
    }

    /**
     * 获取充值buyer的信息
     *
     * @param string $keyword 搜索关键词，在$searchSelf=true时失效
     * @param int $buyerId 查询buyer的id，$searchSelf=true就是查询这个buyer的信息，$searchSelf=false，就是排除这个buyer的信息
     * @param bool $searchSelf 是否查询自己，说明看上面
     *
     * @return array|\Illuminate\Support\Collection
     */
    public function getRechargeBuyerList($keyword,$buyerId = 0,$searchSelf = false)
    {
        if (!$searchSelf && !$keyword) {
            //不是查询本人，且没有输入关键词
            return [];
        }
        //获取域名
        if ($this->request->server['HTTPS']) {
            $ssl =  $this->config->get('config_ssl');
        } else {
            $ssl =  $this->config->get('config_url');
        }
        //查询用户信息、国别、币种
        //只能搜索buyer
        $model = $this->orm->table(DB_PREFIX . 'customer as occ')
            ->leftJoin(DB_PREFIX . 'country as ocn', 'occ.country_id', '=', 'ocn.country_id')
            ->leftJoin(DB_PREFIX . 'currency as ocy', 'ocn.currency_id', '=', 'ocy.currency_id')
            ->select(['occ.customer_id', 'occ.email', 'occ.nickname','occ.firstname','occ.lastname', 'occ.user_number', 'ocn.iso_code_2 as country', 'ocy.code as currency']);
        if($searchSelf){
            $data = $model->where('occ.customer_id', $buyerId)->first();
            $data->value = $data->user_number . ', ' . $data->email . ', ' . $data->firstname . ' ' . $data->lastname;
        } else {
            $buyers = $this->orm->table('oc_customerpartner_to_customer')->select('customer_id');
            $model = $model->whereNotIn('occ.customer_id', $buyers)->where('occ.status', 1)
                           ->where('occ.customer_id', '<>', $buyerId)->where(function ($query) use ($keyword) {
                    $query->where('occ.email', "{$keyword}");
                    $query->orWhere('occ.user_number', "{$keyword}");
                });
            //限制50个
            $data = $model->limit(50)->get();
            foreach ($data as &$item) {
                //获取国旗URL
                $item->value = $item->user_number . ', ' . $item->email . ', ' . $item->firstname . ' ' . $item->lastname;
            }
        }

        return $data;
    }

    /**
     * 验证密码是否正确
     * @param int $customer_id
     * @param string $password
     * @return bool
     */
    public function checkPasswordValid($customer_id  = 0 ,$password = '')
    {
        $customer_detail = $this->orm::table(DB_PREFIX . 'customer')
            ->where('customer_id','=', $customer_id)
            ->first();

        $check_password = $this->db->escape(sha1($customer_detail->salt . sha1($customer_detail->salt . sha1($password)))) ;

        return $check_password == $customer_detail->password ;

    }

    /**
     * 验证邮箱是否可用
     * @param int $customer_id
     * @param string $new_email
     * @return bool|object
     */
    public function checkNewEmailValid($customer_id  = 0 ,$new_email = '')
    {
        $customer_detail = $this->orm::table(DB_PREFIX . 'customer')
            ->where('customer_id','<>', $customer_id)
            ->where('email','=',$new_email)
            ->first();

        return $customer_detail ;

    }

    /**
     * 验证密码是否正确
     * @param int $customer_id
     * @param array $data
     * @return integer
     */
    public function editCustomerInfoById($customer_id  = 0 ,$data = [])
    {
        if (empty($customer_id) || empty($data)) {
            return 0;
        }

        $res = $this->orm::table(DB_PREFIX . 'customer')
            ->where('customer_id','=', $customer_id)
            ->update($data);

        return $res ;
    }



}
