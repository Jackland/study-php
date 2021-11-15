<?php

/**
 * Class ModelCustomerpartnerNotification
 */
class ModelCustomerpartnerNotification extends Model {
    /**
     * [getReturnOrderId is used to get the order_id and product name for the specific return id]
     * @param integer $return_id
     * @return array [order_id and product name]
     */
	public function getReturnOrderId($return_id = 0)	{
		if ($return_id) {
			return $this->db->query("SELECT DISTINCT order_id,product FROM ".DB_PREFIX."return WHERE return_id = ".(int)$return_id)->row;
		}
	}

    /**
     * [getActivityCount is used to get the total order related activity count]
     * @return int [count of activities]
     */
	public function getActivityCount()	{
		$sql = "SELECT DISTINCT ca.key,ca.data,ca.date_added FROM ".DB_PREFIX."mp_customer_activity ca WHERE ca.key IN ('order_account','order_status','return_account')";

		$sql .= ' UNION ALL ';

		$sql .= "SELECT DISTINCT ca.key,ca.data,ca.date_added FROM ".DB_PREFIX."customer_activity ca WHERE ca.key IN ('order_account','order_status','return_account')";

		return $this->db->query($sql)->num_rows;
	}

    /**
     * [getTotalActivity is used to get the total order related activity based on options]
     * @param array $options [all, processing, completed, return]
     * @return int|mixed     [number of activities]
     */
	public function getTotalActivity($options = array())
    {
        $sql = "SELECT count( DISTINCT ca.key,ca.data,ca.date_added ) as total FROM " . DB_PREFIX . "mp_customer_activity ca WHERE ca.key IN ('order_account','order_status','return_account')";
        if ($options && !in_array('all', $options)) {
            $sql .= " AND (0";

            foreach ($options as $key => $value) {
                if ($value == 'return') {
                    $sql .= " || ca.key = 'return_account'";
                } else {
                    $str = '%"status":"' . $value . '"%';
                    $sql .= " || (ca.key = 'order_status' AND ca.data LIKE '" . $str . "')";
                }
            }

            $sql .= ")";
        }
        $sql .= ' UNION ALL ';

        $sql .= "SELECT count( DISTINCT ca.key,ca.data,ca.date_added ) as total FROM " . DB_PREFIX . "customer_activity ca WHERE ca.key IN ('order_account','order_status','return_account')";
        if ($options && !in_array('all', $options)) {
            $sql .= " AND (0";

            foreach ($options as $key => $value) {
                if ($value == 'return') {
                    $sql .= " || ca.key = 'return_account'";
                } else {
                    $str = '%"status":"' . $value . '"%';
                    $sql .= " || (ca.key = 'order_status' AND ca.data LIKE '" . $str . "')";
                }
            }

            $sql .= ")";
        }
        $result = $this->db->query($sql)->rows;
        $total = 0;
        foreach ($result as $item) {
            $total += $item['total'];
        }
        return $total;
    }

    /**
     * [getActivity is used to get the order related activity details]
     * @param array $options [all, processing, completed, return]
     * @param int $limit
     * @return array         [array of activity details]
     */
	public function getActivity($options = array(), $limit= 10)	{
		$sql = "SELECT DISTINCT ca.key,ca.data,ca.date_added FROM ".DB_PREFIX."mp_customer_activity ca WHERE ca.key IN ('order_account','order_status','return_account')";
		if ($options && !in_array('all',$options)) {
			$sql .= " AND (0";

			foreach ($options as $key => $value) {
			  if ($value == 'return') {
			    $sql .= " || ca.key = 'return_account'";
			  } else{
			    $str = '%"status":"'. $value .'"%';
			    $sql .= " || (ca.key = 'order_status' AND ca.data LIKE '".$str."')";
			  }
			}

			$sql .= ")";
		}
		$sql .= ' UNION ALL ';

		$sql .= "SELECT DISTINCT ca.key,ca.data,ca.date_added FROM ".DB_PREFIX."customer_activity ca WHERE ca.key IN ('order_account','order_status','return_account')";
		if ($options && !in_array('all',$options)) {
			$sql .= " AND (0";

			foreach ($options as $key => $value) {
			  if ($value == 'return') {
			    $sql .= " || ca.key = 'return_account'";
			  } else{
			    $str = '%"status":"'. $value .'"%';
			    $sql .= " || (ca.key = 'order_status' AND ca.data LIKE '".$str."')";
			  }
			}

			$sql .= ")";
		}
		$start = 0;

		if (isset($this->request->get['page'])) {
			$start = ($this->request->get['page']-1)*10;
		}

		$sql .= " ORDER BY date_added DESC LIMIT ".$start.",".$limit;

		return $this->db->query($sql)->rows;
	}

    /**
     * [getProductReviewsTotal is used to get the total reviews of the products]
     * @param array $options [description]
     * @return int           [number of product reviews]
     */
	public function getProductReviewsTotal($options = array()) {
		$sql = "SELECT * FROM ".DB_PREFIX."review rv LEFT JOIN ".DB_PREFIX."product_description pd ON (rv.product_id = pd.product_id) WHERE pd.language_id =".$this->config->get('config_language_id');

		$query = $this->db->query($sql)->num_rows;
		return $query;
	}

    /**
     * [getProductReviews is used to get the product review details]
     * @param array $options [description]
     * @param int $limit
     * @return array [product review details]
     */
	public function getProductReviews($options = array(), $limit= 10) {
		$sql = "SELECT DISTINCT rv.review_id,rv.author,rv.product_id,rv.date_added,pd.name AS product_name FROM ".DB_PREFIX."review rv LEFT JOIN ".DB_PREFIX."product_description pd ON (rv.product_id = pd.product_id) WHERE pd.language_id =".$this->config->get('config_language_id');

		$start = 0;

		if (isset($this->request->get['page_product'])  && $this->request->get['page_product'] != '{page}') {
			$start = ($this->request->get['page_product']-1)*10;
		}

		$sql .= " ORDER BY rv.date_added DESC LIMIT ".$start.",".$limit;

		$query = $this->db->query($sql)->rows;
		return $query;
	}

    /**
     * [getProductActivityTotal is used to get the total activities on product(out of stock activity)]
     * @param array $options [description]
     * @return int [number of product activities]
     */
    public function getProductActivityTotal($options = array())
    {
        return $this->orm->table(DB_PREFIX . 'mp_customer_activity as mca')
            ->where('mca.key', 'like', 'product_%')
            ->count();
    }

    public function getProductStockTotal()
    {
        return $this->orm->table(DB_PREFIX . 'mp_customer_activity as mca')
            ->where('mca.key', 'like', 'product_stock%')
            ->count();
    }

    public function getReviewTotal()
    {
        return $this->orm->table(DB_PREFIX . 'mp_customer_activity as mca')
            ->where('mca.key', 'like', 'product_review%')
            ->count();
    }

    public function getApprovalTotal()
    {
        return $this->orm->table(DB_PREFIX . 'mp_customer_activity as mca')
            ->where('mca.key', 'like', 'product_approve%')
            ->count();
    }

    /**
     * [getProductActivity is used to get the product activity details]
     * @param array $options [description]
     * @param int $limit
     * @return array [array of product activities]
     */
	public function getProductActivity($options = array(), $limit= 10) {
			$sql = "SELECT DISTINCT mca.* FROM ".DB_PREFIX."mp_customer_activity mca WHERE mca.key LIKE 'product_%'";

			$start = 0;

			if (isset($this->request->get['page_product'])  && $this->request->get['page_product'] != '{page}') {
				$start = ($this->request->get['page_product']-1)*10;
			}

			$sql .= " ORDER BY mca.date_added DESC LIMIT ".$start.",".$limit;

			$query = $this->db->query($sql)->rows;
			return $query;
	}

    /**
     * [getSellerReviewsTotal is used to get the total reviews of sellers]
     * @param array $options [description]
     * @return int [number of seller reviews]
     */
	public function getSellerReviewsTotal($options = array()) {
		$sql = "SELECT * FROM ".DB_PREFIX."customerpartner_to_feedback c2f LEFT JOIN ".DB_PREFIX."customer c ON (c2f.customer_id = c.customer_id)";

		$query = $this->db->query($sql)->num_rows;

		return $query;
	}

    /**
     * [getSellerReviews is used to get the sellers review details]
     * @param array $options [description]
     * @param int $limit
     * @return array [array of seller details]
     */
	public function getSellerReviews($options = array(), $limit= 10) {
		$sql = "SELECT DISTINCT c2f.*, CONCAT(c.firstname,' ',c.lastname) AS name FROM ".DB_PREFIX."customerpartner_to_feedback c2f LEFT JOIN ".DB_PREFIX."customer c ON (c2f.customer_id = c.customer_id)";

		$start = 0;

		if (isset($this->request->get['page_seller']) && $this->request->get['page_seller'] != '{page}') {
			$start = ($this->request->get['page_seller']-1)*10;
		}

		$sql .= " ORDER BY c2f.createdate DESC LIMIT ".$start.",".$limit;

		$query = $this->db->query($sql)->rows;

		return $query;
	}

	public function addActivity($key, $data) {
	  if (isset($data['id'])) {
	    $id = $data['id'];
	  } else {
	    $id = 0;
	  }

	  $this->db->query("INSERT INTO `" . DB_PREFIX . "mp_customer_activity` SET `id` = '" . (int)$id . "', `key` = '" . $this->db->escape($key) . "', `data` = '" . $this->db->escape(json_encode($data)) . "', `ip` = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "', `date_added` = NOW()");

	  $this->db->query("INSERT INTO `" . DB_PREFIX . "seller_activity` SET `activity_id` = '" . $this->db->getLastId() . "', `seller_id` = '" . $data['seller_id'] ."'");
	}

	public function getCategoryActivityTotal($options = array()) {
	  $sql = "SELECT * FROM ".DB_PREFIX."mp_customer_activity mca WHERE mca.key LIKE 'category_%'";

	  $query = $this->db->query($sql)->num_rows;
	  return $query;
	}

	public function getCategoryActivity($options = array(), $limit= 10) {
	    $sql = "SELECT DISTINCT mca.* FROM ".DB_PREFIX."mp_customer_activity mca WHERE mca.key LIKE 'category_%'";

	    $start = 0;

	    if (isset($this->request->get['page_seller'])  && $this->request->get['page_seller'] != '{page}') {
	      $start = ($this->request->get['page_seller']-1)*10;
	    }

	    $sql .= " ORDER BY mca.date_added DESC LIMIT ".$start.",".$limit;

	    $query = $this->db->query($sql)->rows;
	    return $query;
	}
}
