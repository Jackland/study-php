<?php

/**
 * Class ModelLocalisationCountry
 */
class ModelLocalisationCountry extends Model
{
    public function getCountry($country_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE country_id = '" . (int)$country_id . "' AND status = '1'");

        return $query->row;
    }


    public function getCountrysByIds($country_ids = '')
    {
        if (!trim($country_ids)) {
            return [];
        }
        $sql     = "SELECT * FROM " . DB_PREFIX . "country WHERE country_id IN (" . $country_ids . ") AND status = '1'";
        $query   = $this->db->query($sql);
        $results = [];
        foreach ($query->rows as $key => $value) {
            $results[$value['country_id']] = $value;
        }
        return $results;
    }

    public function getCountryByCode($code)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE iso_code_2 = ? AND status = '1'",[$code]);

        return $query->row;
    }

    public function getCountryByCode2($code)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE iso_code_3 = ? AND status = '1'",[$code]);

        return $query->row;
    }

    public function getCurrencyByCountryCode($countryCode)
    {
        $sql = "SELECT cu.`symbol_left`,cu.`symbol_right` FROM " . DB_PREFIX . "currency cu, " . DB_PREFIX . "country co WHERE cu.currency_id = co.currency_id AND co.iso_code_3 = '" .$countryCode. "'";

        $query = $this->db->query($sql);
        $result = array();
        $rows = $query->rows;
        if (count($rows) == 1) {
            foreach ($rows as $row) {
                return $row['symbol_left'].$row['symbol_right'];
            }
        }
    }

    public function getCountries()
    {
        $country_data = $this->cache->get('country.catalog');

        if (!$country_data) {
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE status = '1' ORDER BY name ASC");

            $country_data = $query->rows;

            $this->cache->set('country.catalog', $country_data);
        }

        return $country_data;
    }

//    public function getShowCountry($country_id) {
//
//
//        if (!$this->customer->isLogged()) {
//            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE show_flag = '1' ORDER BY name ASC");
//
//            $country_data = $query->rows;
//
//            $this->cache->set('country.catalog', $country_data);
//        }else{
//            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE country_id = '" . (int)$country_id . "' AND status = '1'");
//
//            $country_data = $query->rows;
//
//            $this->cache->set('country.catalog', $country_data);
//        }
//
//        return $country_data;
//    }

    public function getShowCountry($customer_id = null)
    {
        if (!$customer_id) {
            $sql = "SELECT cou.`country_id`,cou.`name`,cou.`iso_code_2`,cou.`iso_code_3`,cur.`currency_id`,cur.`title`,cur.`code`,cur.`value` FROM `oc_country` cou LEFT JOIN `oc_currency` cur ON cou.currency_id = cur.currency_id  WHERE cou.show_flag = 1 ORDER BY cou.`sort`";
        } else {
            $sql = "SELECT cou.`country_id`,cou.`name`,cou.`iso_code_2`,cou.`iso_code_3`,cur.`currency_id`,cur.`title`,cur.`code`,cur.`value` FROM `oc_customer` c LEFT JOIN oc_country cou ON c.`country_id` = cou.`country_id` LEFT JOIN `oc_currency` cur ON cou.currency_id = cur.currency_id WHERE c.`customer_id` = " . $customer_id;
        }
        $query = $this->db->query($sql);
        $result = array();
        $rows = $query->rows;
        if (count($rows)) {
            foreach ($rows as $row) {
                $result[$row['iso_code_3']] = $row;
            }
        }
        return $result;
    }


    /**
     * 注册页面，国家下拉列表
     * 不显示 港澳台（96 125 206）
     * @return array
     */
    public function getShowCountryRegister()
    {
        $sql    = "SELECT 
  cou.`country_id`,
  cou.`name`,
  cou.`iso_code_2`,
  cou.`iso_code_3` 
FROM
  `oc_country` cou 
WHERE cou.status = 1 
  AND cou.`country_id` <> 96 
  AND cou.`country_id` <> 125 
  AND cou.`country_id` <> 206 
ORDER BY cou.`sort` ";
        $query  = $this->db->query($sql);
        $result = array();
        $rows   = $query->rows;
        if (count($rows)) {
            foreach ($rows as $row) {
                $result[$row['iso_code_3']] = $row;
            }
        }
        return $result;
    }
}
