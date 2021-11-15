<?php

/**
 * Class ModelCustomerpartnerInformation
 */
class ModelCustomerpartnerInformation extends Model {
	public function getInformations($data = array()) {
		if ($data) {
			$sql = "SELECT * FROM " . DB_PREFIX . "information i LEFT JOIN " . DB_PREFIX . "information_description id ON (i.information_id = id.information_id) RIGHT JOIN " . DB_PREFIX . "customerpartner_to_information c2i ON (c2i.information_id = i.information_id) WHERE id.language_id = '" . (int)$this->config->get('config_language_id') . "'";

			$sort_data = array(
				'id.title',
				'i.sort_order'
			);

			if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
				$sql .= " ORDER BY " . $data['sort'];
			} else {
				$sql .= " ORDER BY id.title";
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
		} else {
			$information_data = $this->cache->get('information.' . (int)$this->config->get('config_language_id'));

			if (!$information_data) {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "information i LEFT JOIN " . DB_PREFIX . "information_description id ON (i.information_id = id.information_id) WHERE id.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY id.title");

				$information_data = $query->rows;

				$this->cache->set('information.' . (int)$this->config->get('config_language_id'), $information_data);
			}

			return $information_data;
		}
	}

	public function getTotalInformations() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "information i RIGHT JOIN " . DB_PREFIX . "customerpartner_to_information c2i ON (i.information_id = c2i.information_id)");

		return $query->row['total'];
	}

	public function updateInformationStatus($information_id = 0) {
		$this->db->query("UPDATE " . DB_PREFIX . "information SET status = 1 WHERE `information_id` = " . (int)$information_id);
	}

	public function updateInformationSeller($information_id = 0 , $seller_id = 0) {
		$this->db->query("UPDATE " . DB_PREFIX . "customerpartner_to_information SET seller_id = " . (int)$seller_id . " WHERE `information_id` = " . (int)$information_id);
	}
}
