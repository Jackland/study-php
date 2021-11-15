<?php

/**
 * Class ModelCustomerpartnerCategorymapping
 */
    class ModelCustomerpartnerCategorymapping extends Model
    {
        public function getTotalCategoryAttributes($data = array())
        {
            $sql = "SELECT c2c.*,cd.name FROM " . DB_PREFIX . "wk_category_attribute_mapping c2c LEFT JOIN ".DB_PREFIX."category_description cd ON (c2c.category_id = cd.category_id) WHERE cd.language_id = ".$this->config->get('config_language_id');

            if (!empty($data['filter_attribute_id'])) {
                $sql .= " AND c2c.attribute_id LIKE '%" . $data['filter_attribute_id'] . "%'";
            }

            if (!empty($data['filter_category_id'])) {
                $sql .= " AND c2c.category_id LIKE '%" . $data['filter_category_id'] . "%'";
            }

            $query = $this->db->query($sql);

            return $query->num_rows;
        }

        public function getCategoryAttribute($category_id = 0)
        {
            if ($category_id) {
                $category_attributes = $this->db->query("SELECT attribute_id FROM ".DB_PREFIX."wk_category_attribute_mapping WHERE category_id =".(int)$category_id)->row;

                if (isset($category_attributes['attribute_id']) && $category_attributes['attribute_id']) {
                    $sql = "SELECT *, (SELECT agd.name FROM " . DB_PREFIX . "attribute_group_description agd WHERE agd.attribute_group_id = a.attribute_group_id AND agd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS attribute_group FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE ad.language_id = '" . (int)$this->config->get('config_language_id') . "' AND a.attribute_id IN (".$category_attributes['attribute_id'].")";

                    $query = $this->db->query($sql);

                    return $query->rows;
                }
            }
        }

        public function getCategoryAttributes($data = array())
        {
            $sql = "SELECT c2c.*,cd.name FROM " . DB_PREFIX . "wk_category_attribute_mapping c2c LEFT JOIN ".DB_PREFIX."category_description cd ON (c2c.category_id = cd.category_id) WHERE cd.language_id = ".$this->config->get('config_language_id');

            if (!empty($data['filter_attribute_id'])) {
                $sql .= " AND c2c.attribute_id LIKE '%" . $data['filter_attribute_id'] . "%'";
            }

            if (!empty($data['filter_category_id'])) {
                $sql .= " AND c2c.category_id LIKE '%" . $data['filter_category_id'] . "%'";
            }

            if (!empty($data['filter_category'])) {
                $sql .= " AND cd.name LIKE '%" . $data['filter_category'] . "%'";
            }

            $query = $this->db->query($sql);

            return $query->rows;
        }

        public function deleteCategoryAttribute($category_ids = 0)
        {
            if ($category_ids) {
                $this->db->query("DELETE FROM ".DB_PREFIX."wk_category_attribute_mapping WHERE category_id IN (".$category_ids.")");
            }
        }

        public function addCategoryAttribute($data)
        {
            if (isset($data['attribute_ids']) && $data['attribute_ids'] && isset($data['product_category']) && $data['product_category']) {
                if (is_array($data['attribute_ids']) && in_array('0', $data['attribute_ids'])) {
                    $attribute_ids = 0;
                } else {
                    $attribute_ids = implode(',', $data['attribute_ids']);
                }

                if (is_array($data['product_category']) && in_array('0', $data['product_category'])) {
                    $this->db->query("DELETE FROM ".DB_PREFIX."wk_category_attribute_mapping");

                    $this->db->query("INSERT INTO ".DB_PREFIX."wk_category_attribute_mapping SET category_id = '0',attribute_id = '".$attribute_ids."'");
                } else {
                    foreach ($data['product_category'] as $key => $value) {
                        $this->db->query("DELETE FROM ".DB_PREFIX."wk_category_attribute_mapping WHERE category_id = ".(int)$value);

                        $this->db->query("INSERT INTO ".DB_PREFIX."wk_category_attribute_mapping SET category_id = '".(int)$value."',attribute_id = '".$attribute_ids."'");
                    }
                }
            }
        }

        public function getAttributeName($attribute_id = 0)
        {
            if ($attribute_id) {
                return $this->db->query("SELECT name FROM ".DB_PREFIX."attribute_description WHERE attribute_id = " . (int)$attribute_id . " AND language_id = ".$this->config->get('config_language_id'))->row;
            }
        }
    }
