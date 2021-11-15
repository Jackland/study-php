<?php

/**
 * Class ModelCustomerpartnerCategory
 */
	class ModelCustomerpartnerCategory extends Model {

		public function getTotalSellerCategories($data = array()){
			$sql = "SELECT c2c.*,CONCAT(c.firstname,' ',c.lastname) AS name FROM " . DB_PREFIX . "customerpartner_to_category c2c LEFT JOIN ".DB_PREFIX."customer c ON (c2c.seller_id = c.customer_id) WHERE 1 ";

			if (!empty($data['filter_seller'])) {
				$sql .= " AND CONCAT(c.firstname,' ',c.lastname) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_seller'])) . "%'";
			}

			if (!empty($data['filter_category_id'])) {
				$sql .= " AND c2c.category_id LIKE '%" . $data['filter_category_id'] . "%'";
			}

			$query = $this->db->query($sql);

			return $query->num_rows;
		}

		public function getSellerCategories($data = array()){
			$sql = "SELECT c2c.*,CONCAT(c.firstname,' ',c.lastname) AS name FROM " . DB_PREFIX . "customerpartner_to_category c2c LEFT JOIN ".DB_PREFIX."customer c ON (c2c.seller_id = c.customer_id) WHERE 1 ";

			if (!empty($data['filter_seller'])) {
				$sql .= " AND CONCAT(c.firstname,' ',c.lastname) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_seller'])) . "%'";
			}

			if (!empty($data['filter_category_id'])) {
				$sql .= " AND c2c.category_id LIKE '%" . $data['filter_category_id'] . "%'";
			}

			$query = $this->db->query($sql);

			return $query->rows;
		}

		public function deleteCategory($seller_ids = 0) {
			if ($seller_ids) {
				$this->db->query("DELETE FROM ".DB_PREFIX."customerpartner_to_category WHERE seller_id IN (".$seller_ids.")");
			}
		}

		public function getSellerCategory($seller_id = 0) {
			if ($seller_id) {
				return $this->db->query("SELECT * FROM ".DB_PREFIX."customerpartner_to_category WHERE seller_id = ".(int)$seller_id)->row;
			}
		}

		public function addCategory($data){

			if (isset($data['seller_ids']) && $data['seller_ids'] && isset($data['product_category']) && $data['product_category']) {

				if (is_array($data['product_category']) && in_array('0', $data['product_category'])) {
					$categories = 0;
				} else {
					foreach ($data['product_category'] as $key => $value) {
					    $category_id = $value;
					    while(1){
					      $parent_category = $this->db->query("SELECT parent_id FROM " . DB_PREFIX . "category WHERE category_id = " . (int)$category_id)->row;

					      if (isset($parent_category['parent_id']) && $parent_category['parent_id']) {
					        array_push($data['product_category'],$parent_category['parent_id']);
					        $category_id = $parent_category['parent_id'];
					      } else {
					        break;
					      }
					    }
					}

					$categories = implode(',', array_unique($data['product_category']));
				}

				foreach ($data['seller_ids'] as $key => $value) {

					$this->db->query("DELETE FROM ".DB_PREFIX."customerpartner_to_category WHERE seller_id = ".(int)$value);

					$this->db->query("INSERT INTO ".DB_PREFIX."customerpartner_to_category SET seller_id = '".(int)$value."',category_id = '".$categories."'");
				}
			}
		}

		public function getSellerName($seller_id = 0){
			if ($seller_id) {
				return $this->db->query("SELECT CONCAT(firstname,' ',lastname) AS name FROM ".DB_PREFIX."customer WHERE customer_id = " . (int)$seller_id)->row;
			}
		}
	}
?>
