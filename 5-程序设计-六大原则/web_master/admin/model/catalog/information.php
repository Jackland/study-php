<?php

use App\Helper\SummernoteHtmlEncodeHelper;

/**
 * Class ModelCatalogInformation
 */
class ModelCatalogInformation extends Model
{
    public function addInformation($data)
    {
        $country = isset($data['country']) ? implode(',', $data['country']) : '';
        $role = isset($data['roles']) ? implode(',', $data['roles']) : '';
        $save_info_sql = "INSERT INTO " . DB_PREFIX . "information SET is_link={$data['is_link']},country='$country',sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "'" . ",role='$role'";
        $parent_id = -1;
        if (!empty($data['parent_title'])) {
            $parent_row = $this->db->query("select information_id from oc_information_description where meta_title ='{$data['parent_title']}' ")->row;
            if (!empty($parent_row['information_id'])) {
                $parent_id = $parent_row['information_id'];
            }
        }
        $save_info_sql .= ",parent_id=$parent_id";
        $this->db->query($save_info_sql);

        $information_id = $this->db->getLastId();

        foreach ($data['information_description'] as $language_id => $value) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "information_description SET information_id = '" . (int)$information_id . "', language_id = '" . (int)$language_id . "', title = '" . $this->db->escape($value['title']) . "', description = '" . $this->db->escape($value['description']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
        }

        if (isset($data['information_store'])) {
            foreach ($data['information_store'] as $store_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "information_to_store SET information_id = '" . (int)$information_id . "', store_id = '" . (int)$store_id . "'");
            }
        }

        $this->cache->delete('information');

        return $information_id;
    }

    public function editInformation($information_id, $data)
    {
        if (isset($data['country'])) {
            $country = implode(',', $data['country']);
        } else {
            $country = '';
        }
        $role = implode(',', $data['roles']);
        $save_info_sql = "UPDATE " . DB_PREFIX . "information SET is_link={$data['is_link']},country='$country',sort_order = '" . (int)$data['sort_order'] . "', status = " . (int)$data['status'] . ",role='$role'";
        $parent_id = -1;
        if (!empty($data['parent_title'])) {
            $parent_row = $this->db->query("select information_id from oc_information_description where meta_title ='{$data['parent_title']}' ")->row;
            if (!empty($parent_row['information_id'])) {
                $parent_id = $parent_row['information_id'];
            }
        }
        $save_info_sql .= ",parent_id=$parent_id";

        $save_info_sql .= " WHERE information_id = " . (int)$information_id;
        $this->db->query($save_info_sql);
        //更新所有子节点的country和role
        $this->updateSubCountryAndRole($information_id, $role, $country);

        $this->db->query("DELETE FROM " . DB_PREFIX . "information_description WHERE information_id = '" . (int)$information_id . "'");

        foreach ($data['information_description'] as $language_id => $value) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "information_description SET information_id = '" . (int)$information_id . "', language_id = '" . (int)$language_id . "', title = '" . $this->db->escape($value['title']) . "', description = '" . $this->db->escape($value['description']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
        }

        $this->db->query("DELETE FROM " . DB_PREFIX . "information_to_store WHERE information_id = '" . (int)$information_id . "'");

        if (isset($data['information_store'])) {
            foreach ($data['information_store'] as $store_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "information_to_store SET information_id = '" . (int)$information_id . "', store_id = '" . (int)$store_id . "'");
            }
        }

        $this->cache->delete('information');
    }

    /**
     * 保存帮助中心的文件路径
     * @param $information_id
     * @param $file_path
     */
    public function saveInformationFile($information_id, $file_path)
    {
        $sql = "UPDATE " . DB_PREFIX . "information SET file_path = '" . $this->db->escape($file_path) . "' WHERE information_id = " . (int)$information_id;
        $this->db->query($sql);
    }

    public function deleteSubInformation($parent_ids)
    {
        while (!empty($parent_ids)) {
            $rows = $this->db->query(" SELECT information_id FROM oc_information
 WHERE parent_id in (" . implode(',', $parent_ids) . ")")->rows;
            if (empty($rows)) {
                break;
            }
            $parent_ids = array();
            foreach ($rows as $row) {
                $parent_ids[] = $row['information_id'];
            }
            $this->db->query("DELETE FROM `" . DB_PREFIX . "information` WHERE information_id in (" . implode(',', $parent_ids) . ") ");
            $this->db->query("DELETE FROM `" . DB_PREFIX . "information_description` WHERE information_id in (" . implode(',', $parent_ids) . ") ");
            $this->db->query("DELETE FROM `" . DB_PREFIX . "information_to_store` WHERE information_id in (" . implode(',', $parent_ids) . ") ");
            $this->db->query("DELETE FROM `" . DB_PREFIX . "information_to_layout` WHERE information_id in (" . implode(',', $parent_ids) . ") ");
        }
    }

    public function deleteInformation($information_id)
    {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "information` WHERE information_id = '" . (int)$information_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "information_description` WHERE information_id = '" . (int)$information_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "information_to_store` WHERE information_id = '" . (int)$information_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "information_to_layout` WHERE information_id = '" . (int)$information_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = 'information_id=" . (int)$information_id . "'");
        $this->cache->delete('information');
    }

    public function getInformation($information_id)
    {
        $query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "information WHERE information_id = '" . (int)$information_id . "'");

        return $query->row;
    }

    public function hasSub($information_id)
    {
        $row = $this->db->query("SELECT  *  FROM " . DB_PREFIX . "information WHERE parent_id = " . (int)$information_id . " limit 1")->row;
        return !empty($row);
    }

    public function getParentInfo($parent_id)
    {
        $result = array();
        while (!empty($parent_id)) {
            $row = $this->db->query(" SELECT i.*,id.meta_title FROM oc_information i
 LEFT JOIN oc_information_description id   ON i.`information_id`=id.`information_id`
 WHERE i.information_id = {$parent_id}")->row;
            if (!empty($row)) {
                $result[] = $row;
                $parent_id = $row['parent_id'];
            } else {
                break;
            }
        }
        $result = array_reverse($result);
        return $result;
    }


    public function getInformations($data = array())
    {
        if ($data) {
            $sql = "SELECT * FROM " . DB_PREFIX . "information i LEFT JOIN " . DB_PREFIX . "information_description id ON (i.information_id = id.information_id) WHERE id.language_id = '" . (int)$this->config->get('config_language_id') . "'";

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

    public function getInformationDescriptions($information_id)
    {
        $information_description_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "information_description WHERE information_id = '" . (int)$information_id . "'");

        foreach ($query->rows as $result) {
            $information_description_data[$result['language_id']] = array(
                'title' => $result['title'],
                'description' => SummernoteHtmlEncodeHelper::decode($result['description']),
                'meta_title' => $result['meta_title'],
                'meta_description' => $result['meta_description'],
                'meta_keyword' => $result['meta_keyword']
            );
        }

        return $information_description_data;
    }

    public function getInformationStores($information_id)
    {
        $information_store_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "information_to_store WHERE information_id = '" . (int)$information_id . "'");

        foreach ($query->rows as $result) {
            $information_store_data[] = $result['store_id'];
        }

        return $information_store_data;
    }

    public function getInformationSeoUrls($information_id)
    {
        $information_seo_url_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query = 'information_id=" . (int)$information_id . "'");

        foreach ($query->rows as $result) {
            $information_seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
        }

        return $information_seo_url_data;
    }

    public function getInformationLayouts($information_id)
    {
        $information_layout_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "information_to_layout WHERE information_id = '" . (int)$information_id . "'");

        foreach ($query->rows as $result) {
            $information_layout_data[$result['store_id']] = $result['layout_id'];
        }

        return $information_layout_data;
    }

    public function getTotalInformations()
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "information");

        return $query->row['total'];
    }

    public function getTotalInformationsByLayoutId($layout_id)
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "information_to_layout WHERE layout_id = '" . (int)$layout_id . "'");

        return $query->row['total'];
    }

    public function getCountrys()
    {
        return $this->db->query("SELECT country_id,name,iso_code_2,iso_code_3 FROM `oc_country` WHERE  show_flag=1")->rows;
    }

    public function getCountryDic($key, $value)
    {
        if (is_null($key)) {
            $key = 'country_id';
        }
        if (is_null($value)) {
            $value = 'name';
        }
        $countrys = $this->getCountrys();
        $result = array();
        foreach ($countrys as $cty) {
            $result[$cty[$key]] = $cty[$value];
        }
        return $result;
    }

    public function getSubInformationIds($information_id)
    {
        if(empty($information_id)){
            return null;
        }
        $subInformationIds = array();
        $tmp = array($information_id);
        do {
            $rows = $this->db->query(" SELECT information_id FROM oc_information
 WHERE parent_id in (" . implode(',', $tmp) . ")")->rows;
            $tmp = array_column($rows, 'information_id');
            $subInformationIds = array_merge($subInformationIds, $tmp);
        } while (!empty($tmp));
        return $subInformationIds;
    }

    public function updateSubCountryAndRole($information_id, string $role, string $country): void
    {
        $subInformationIds = $this->getSubInformationIds($information_id);
        if(!empty($subInformationIds)){
            $this->db->query("update " . DB_PREFIX . "information
            set role='$role',country='$country'
            WHERE information_id in (" . implode(',', $subInformationIds) . ") ");
        }
    }
}
