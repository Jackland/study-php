<?php

/**
 * Class ModelCatalogInformation
 */
class ModelCatalogInformation extends Model {
    /**
     * @param int $information_id
     * @return array
     */
	public function getInformation($information_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "information i LEFT JOIN " . DB_PREFIX . "information_description id ON (i.information_id = id.information_id) LEFT JOIN " . DB_PREFIX . "information_to_store i2s ON (i.information_id = i2s.information_id) WHERE i.information_id = '" . (int)$information_id . "' AND id.language_id = '" . (int)$this->config->get('config_language_id') . "' AND i2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND i.status = '1'");

		return $query->row;
	}

	public function getInformations() {
	    $sql = "SELECT * FROM " . DB_PREFIX . "information i LEFT JOIN " . DB_PREFIX . "information_description id ON (i.information_id = id.information_id) LEFT JOIN " . DB_PREFIX . "information_to_store i2s ON (i.information_id = i2s.information_id) WHERE id.language_id = '" . (int)$this->config->get('config_language_id') . "' AND i2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND i.status = '1' ";
        if($this->customer->isLogged()){
            if($this->customer->isPartner()){
                $role = 'SELLER';
            }else{
                $role = 'BUYER';
            }
        }else{
                $role = 'VISITOR';
        }
	    $sql .= " and  i.role like '%$role%'  ORDER BY i.sort_order, LCASE(id.title) ASC";
		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getInformationLayoutId($information_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "information_to_layout WHERE information_id = '" . (int)$information_id . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

		if ($query->num_rows) {
			return (int)$query->row['layout_id'];
		} else {
			return 0;
		}
	}
    public function getParentInfo($parent_id) {
        $result=array();
        while ($parent_id!=-1) {
            $row = $this->db->query(" SELECT i.*,id.meta_title,id.title FROM oc_information i 
 LEFT JOIN oc_information_description id   ON i.`information_id`=id.`information_id`	
 WHERE i.information_id = {$parent_id}")->row;
            if(!empty($row)){
                $result[] = $row;
                $parent_id = $row['parent_id'];
            }else{
                break;
            }
        }
        $result = array_reverse($result);
        return $result;
    }
}