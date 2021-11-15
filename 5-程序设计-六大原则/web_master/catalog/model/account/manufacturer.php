<?php

/**
 * Class ModelAccountManufacturer
 */
class ModelAccountManufacturer extends Model {
	public function addManufacturer($data) {

	    $sql = "INSERT INTO " . DB_PREFIX . "manufacturer SET name = '" . $this->db->escape($data['brand_name']) . "', sort_order = 0,customer_id='". (int)$data['customer_id']."',is_partner='".(int)$data['is_partner']."',image_id = UUID()";
		$this->db->query($sql);

		$manufacturer_id = $this->db->getLastId();

        $result = $this->db->query("select image_id from oc_manufacturer where manufacturer_id = ".(int)$manufacturer_id)->row;

        $this->db->query("insert into tb_sys_manufacturer_icon_his set image = '". $this->db->escape($data['brand_file_path'])."',image_id = '". $this->db->escape($result['image_id'])."',operation = 0,create_time = NOW()");

		if (isset($data['brand_file_path'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "manufacturer SET image = '" . $this->db->escape($data['brand_file_path']) . "' WHERE manufacturer_id = '" . (int)$manufacturer_id . "'");
		}
        if (isset($data['can_brand'])) {
            $this->db->query("UPDATE " . DB_PREFIX . "manufacturer SET can_brand = '" . $this->db->escape($data['can_brand']) . "' WHERE manufacturer_id = '" . (int)$manufacturer_id . "'");
        }
        $sql1 =  "INSERT INTO ".DB_PREFIX."manufacturer_to_store set manufacturer_id = '" . (int)$manufacturer_id . "',store_id = 0";
        $this->db->query($sql1);

		
		//$this->cache->delete('manufacturer');

		return $manufacturer_id;
	}

	public function editManufacturer($manufacturer_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "manufacturer SET name = '" . $this->db->escape($data['name']) . "' WHERE manufacturer_id = '" . (int)$manufacturer_id . "'");

		if (isset($data['image'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "manufacturer SET image = '" . $this->db->escape($data['image']) . "',image_id = UUID() WHERE manufacturer_id = '" . (int)$manufacturer_id . "'");

            $result = $this->db->query("select image_id from oc_manufacturer where manufacturer_id = ".(int)$manufacturer_id)->row;

            $this->db->query("insert into tb_sys_manufacturer_icon_his set image = '". $this->db->escape($data['image'])."',image_id = '". $this->db->escape($result['image_id'])."',operation = 1,create_time = NOW()");
		}
        if (isset($data['can_brand'])) {
            $this->db->query("UPDATE " . DB_PREFIX . "manufacturer SET can_brand = '" . $this->db->escape($data['can_brand']) . "' WHERE manufacturer_id = '" . (int)$manufacturer_id . "'");
        }
		$this->cache->delete('manufacturer');
	}

	public function deleteManufacturer($manufacturer_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "manufacturer` WHERE manufacturer_id = '" . (int)$manufacturer_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "manufacturer_to_store` WHERE manufacturer_id = '" . (int)$manufacturer_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = 'manufacturer_id=" . (int)$manufacturer_id . "'");

		$this->cache->delete('manufacturer');
	}

	public function getManufacturer($manufacturer_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "manufacturer WHERE manufacturer_id = '" . (int)$manufacturer_id . "'");

		return $query->row;
	}

	public function getManufacturers($data = array()) {
		$sql = "SELECT * FROM " . DB_PREFIX . "manufacturer WHERE 1=1 ";

		if (!empty($data['filter_name'])) {
			$sql .= " and name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}
        if (!empty($data['customer_id'])) {
            $sql .= " and customer_id  = '" . $this->db->escape($data['customer_id']) . "'";
        }
		$sort_data = array(
			'name',
			'sort_order'
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
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getManufacturerStores($manufacturer_id) {
		$manufacturer_store_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "manufacturer_to_store WHERE manufacturer_id = '" . (int)$manufacturer_id . "'");

		foreach ($query->rows as $result) {
			$manufacturer_store_data[] = $result['store_id'];
		}

		return $manufacturer_store_data;
	}
	
	public function getManufacturerSeoUrls($manufacturer_id) {
		$manufacturer_seo_url_data = array();
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query = 'manufacturer_id=" . (int)$manufacturer_id . "'");

		foreach ($query->rows as $result) {
			$manufacturer_seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
		}

		return $manufacturer_seo_url_data;
	}
	
	public function getTotalManufacturers($customer_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "manufacturer where customer_id = ".$customer_id);

		return $query->row['total'];
	}

	public function getManufacturerProductCount($manufacturerId){
	    $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "product where manufacturer_id = ".$manufacturerId);
	    return $query->row['total'];
    }

    public function getManufacturerProductInfo($data = array()){
        $sql = "SELECT * FROM `oc_product` p , `oc_product_description` pd WHERE p.`product_id` = pd.`product_id` AND p.manufacturer_id = ".$data['manufacturerId'];

        $sql .= " ORDER BY p.product_id ";

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

        return $this->db->query($sql)->rows;
    }

    public function getTotalManufacturerProductInfo($manufacturerId){
        $query = $this->db->query("SELECT count(*) AS total FROM `oc_product` p , `oc_product_description` pd WHERE p.`product_id` = pd.`product_id` AND p.manufacturer_id = ".$manufacturerId);
        return $query->row['total'];
    }
}
