<?php

/**
 * Class ModelCustomerpartnerReview
 */
class ModelCustomerpartnerReview extends Model {

	public function getTotalReviews($data = array()) {
		$sql = "SELECT c2f.*, CONCAT(c1.firstname,' ',c1.lastname) as customer_name FROM " . DB_PREFIX . "customerpartner_to_feedback c2f LEFT JOIN " . DB_PREFIX . "customer c1 ON (c2f.customer_id = c1.customer_id) LEFT JOIN " . DB_PREFIX . "customer c2 ON (c2f.seller_id = c2.customer_id) WHERE c2.customer_id = " . $this->customer->getId();

		if (isset($data['filter_customer']) && !is_null($data['filter_customer'])) {
			$sql .= " AND LCASE(CONCAT(c1.firstname, ' ', c1.lastname)) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_customer'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$sql .= " AND c2f.status = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_createdate'])) {
			$sql .= " AND DATE(c2f.createdate) = DATE('" . $this->db->escape($data['filter_createdate']) . "')";
		}

		$query = $this->db->query($sql);

		return count($query->rows);
	}

	public function getReviews($data = array()) {
		$sql = "SELECT c2f.*, CONCAT(c1.firstname,' ',c1.lastname) as customer_name FROM " . DB_PREFIX . "customerpartner_to_feedback c2f LEFT JOIN " . DB_PREFIX . "customer c1 ON (c2f.customer_id = c1.customer_id) LEFT JOIN " . DB_PREFIX . "customer c2 ON (c2f.seller_id = c2.customer_id) WHERE c2.customer_id = " . $this->customer->getId();

		if (isset($data['filter_customer']) && !is_null($data['filter_customer'])) {
			$sql .= " AND LCASE(CONCAT(c1.firstname, ' ', c1.lastname)) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_customer'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$sql .= " AND c2f.status = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_createdate'])) {
			$sql .= " AND DATE(c2f.createdate) = DATE('" . $this->db->escape($data['filter_createdate']) . "')";
		}

		$sort_data = array(
			'c2f.customer_id',
			'c2f.status',
			'c2f.createdate'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY c2f.createdate";
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

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function deleteReview($review_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "wk_feedback_attribute_values WHERE feedback_id = '" . (int)$review_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_to_feedback WHERE id = '" . (int)$review_id . "'");
	}

	public function getReview($review_id) {
		$query = $this->db->query("SELECT c2f.*, CONCAT(c1.firstname,' ',c1.lastname) as customer_name FROM " . DB_PREFIX . "customerpartner_to_feedback c2f LEFT JOIN " . DB_PREFIX . "customer c1 ON (c2f.customer_id = c1.customer_id) LEFT JOIN " . DB_PREFIX . "customer c2 ON (c2f.seller_id = c2.customer_id) WHERE c2f.id = '" . (int)$review_id . "'");

		return $query->row;
	}

	public function getCustomers($data = array()) {

   $sql = "SELECT CONCAT(c.firstname, ' ', c.lastname) AS name,c.customer_id AS customer_id FROM " . DB_PREFIX . "customer c WHERE 1";

		$implode = array();

		if (!empty($data['filter_customer'])) {
			$implode[] = "LCASE(CONCAT(c.firstname, ' ', c.lastname)) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_customer'])) . "%'";
		}

		if (isset($data['filter_customer_id']) && !is_null($data['filter_customer_id'])) {
			$implode[] = "c.customer_id != '" . (int)$data['filter_customer_id'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sql .= " ORDER BY c.customer_id";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function addReview($data) {
		$data['seller_id'] = $this->customer->getId();

		$feedback_id = 0;

    $check = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_feedback WHERE customer_id = '" . (int)$this->db->escape($data['customer_id']) . "' AND seller_id = '" . (int)$this->db->escape($data['seller_id']) . "'")->row;

    if ($check) {
    	$this->db->query("UPDATE `" . DB_PREFIX . "customerpartner_to_feedback` SET  nickname = '" . $this->db->escape($data['customer']) . "', review = '" . $this->db->escape($data['text']) . "', status = '" . (int)$data['status'] . "', createdate = NOW() WHERE customer_id = '" . (int)$this->db->escape($data['customer_id']) . "' AND seller_id = '" . (int)$this->db->escape($data['seller_id']) . "'");
			$feedback_id = $check['id'];
    }else{
    	$this->db->query("INSERT INTO `" . DB_PREFIX . "customerpartner_to_feedback` SET `customer_id` = '" . (int)$this->db->escape($data['customer_id']) . "', `seller_id` = '" . (int)$this->db->escape($data['seller_id']) . "',  nickname = '" . $this->db->escape($data['customer']) . "', review = '" . $this->db->escape($data['text']) . "', status = '" . (int)$data['status'] . "', createdate = NOW()");
			$feedback_id = $this->db->getLastId();
    }

		if ($feedback_id && isset($data['review_attributes']) && is_array($data['review_attributes']) && !empty($data['review_attributes'])) {
			foreach ($data['review_attributes'] as $key => $value) {
				if ($this->db->query("SELECT * FROM ".DB_PREFIX."wk_feedback_attribute WHERE field_id=".(int)$key)->row) {
					$this->db->query("DELETE FROM " . DB_PREFIX . "wk_feedback_attribute_values WHERE feedback_id = '" . (int)$feedback_id . "' AND field_id=".$key);

					$this->db->query("INSERT INTO `" . DB_PREFIX . "wk_feedback_attribute_values` SET `feedback_id` = '" . (int)$this->db->escape($feedback_id) . "', `field_id` = '" . (int)$this->db->escape($key) . "',  field_value = '" . $this->db->escape($value) . "'");
				}
			}
		}
	}

	public function getTotalReviewFields($data = array()) {
		$sql = "SELECT * FROM " . DB_PREFIX . "wk_feedback_attribute WHERE 1";

		if (isset($data['filter_field']) && !is_null($data['filter_field'])) {
			$sql .= " AND field_name LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_field'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$sql .= " AND field_status = '" . (int)$data['filter_status'] . "'";
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

		$query = $this->db->query($sql);

		return count($query->rows);
	}

	public function getReviewFields($data = array()) {
		$sql = "SELECT * FROM " . DB_PREFIX . "wk_feedback_attribute WHERE 1";

		if (isset($data['filter_field']) && !is_null($data['filter_field'])) {
			$sql .= " AND field_name LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_field'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$sql .= " AND field_status = '" . (int)$data['filter_status'] . "'";
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

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function deleteReviewField($reviewfield_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "wk_feedback_attribute_values WHERE field_id = '" . (int)$reviewfield_id . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "wk_feedback_attribute WHERE field_id = '" . (int)$reviewfield_id . "'");
	}

	public function getReviewField($reviewfield_id) {

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "wk_feedback_attribute WHERE field_id = '" . (int)$reviewfield_id . "'");

		return $query->row;
	}

	public function getReviewFieldByName($reviewfield_name) {

		if (isset($this->request->get['reviewfield_id']) && $this->request->get['reviewfield_id']) {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "wk_feedback_attribute WHERE field_name = '" . $this->db->escape($reviewfield_name) . "' AND field_id!= ".(int)$this->request->get['reviewfield_id']);
		} else {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "wk_feedback_attribute WHERE field_name = '" . $this->db->escape($reviewfield_name) . "'");
		}
		return $query->row;
	}

	public function addReviewField($data) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "wk_feedback_attribute` SET `field_name` = '" . $this->db->escape($data['field_name']) . "', `field_status` = '" . (int)$this->db->escape($data['status']) . "'");
	}

	public function editReviewField($data, $id) {
		$this->db->query("UPDATE `" . DB_PREFIX . "wk_feedback_attribute` SET `field_name` = '" . $this->db->escape($data['field_name']) . "', `field_status` = '" . (int)$this->db->escape($data['status']) . "' WHERE field_id = ".(int)$id);
	}

	public function getAllReviewFields() {
		$sql = "SELECT * FROM " . DB_PREFIX . "wk_feedback_attribute WHERE field_status = 1";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getReviewAttributeValue($feedback_id = 0, $field_id = 0) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "wk_feedback_attribute_values WHERE feedback_id = '" . (int)$feedback_id . "' AND field_id = ".(int)$field_id);

		return $query->row;
	}
}
