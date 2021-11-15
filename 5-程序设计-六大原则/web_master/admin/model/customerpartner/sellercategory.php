<?php

/**
 * Class ModelCustomerpartnerSellercategory
 * @property ModelCustomerpartnerNotification $model_customerpartner_notification
 */
class ModelCustomerpartnerSellercategory extends Model {
	public function getCategories($data = array()) {
		$sql = "SELECT c.firstname, c.lastname, c1.status, c2sc.seller_id, cp.category_id AS category_id, GROUP_CONCAT(cd1.name ORDER BY cp.level SEPARATOR '&nbsp;&nbsp;&gt;&nbsp;&nbsp;') AS name, c1.parent_id, c1.sort_order FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "category c1 ON (cp.category_id = c1.category_id) LEFT JOIN " . DB_PREFIX . "category c2 ON (cp.path_id = c2.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd1 ON (cp.path_id = cd1.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd2 ON (cp.category_id = cd2.category_id) RIGHT JOIN " . DB_PREFIX . "customerpartner_to_sellercategory c2sc ON (c2sc.category_id = cp.category_id) LEFT JOIN ".DB_PREFIX."customer c ON (c2sc.seller_id = c.customer_id) WHERE cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cd2.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (isset($data['filter_category_id']) && $data['filter_category_id']) {
		  $sql .= " AND cp.category_id = " . (int)$data['filter_category_id'];
		}

		if (!empty($data['filter_name'])) {
			$sql .= " AND cd2.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
		}

		$sql .= " GROUP BY cp.category_id";

		$sort_data = array(
			'name',
			'sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY sort_order";
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

	public function getTotalCategories() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "category c RIGHT JOIN " . DB_PREFIX . "customerpartner_to_sellercategory c2sc ON (c2sc.category_id = c.category_id)");

		return $query->row['total'];
	}

	public function updateCategoryStatus($category_id = 0) {
		$category_data = $this->getCategories(array('filter_category_id' => $category_id));

		$this->load->model('customerpartner/notification');

		$activity_data = array(
		  'id' 					=> $category_data[0]['category_id'],
		  'category_id' 	=> $category_data[0]['category_id'],
		  'seller_id' 	=> $category_data[0]['seller_id'],
		  'category_name' => $category_data[0]['name'],
		);

		$this->model_customerpartner_notification->addActivity('category_approve',$activity_data);

		$this->db->query("UPDATE " . DB_PREFIX . "category SET status = 1 WHERE `category_id` = " . (int)$category_id);
	}

	public function updateCategorySeller($category_id = 0 , $seller_id = 0) {
		$this->db->query("UPDATE " . DB_PREFIX . "customerpartner_to_sellercategory SET seller_id = " . (int)$seller_id . " WHERE `category_id` = " . (int)$category_id);
	}
}
