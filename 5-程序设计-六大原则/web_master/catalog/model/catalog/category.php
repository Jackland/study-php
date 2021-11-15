<?php

/**
 * Class ModelCatalogCategory
 */
class ModelCatalogCategory extends Model {
	public function getCategory($category_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.category_id = '" . (int)$category_id . "' AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND c.status = '1' AND c.is_deleted = 0");

		return $query->row;
	}

	public function getCategories($parent_id = 0) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.parent_id = '" . (int)$parent_id . "' AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "'  AND c.status = '1' AND c.is_deleted = 0 ORDER BY c.sort_order, LCASE(cd.name)");

		return $query->rows;
	}

	public function getCategoryFilters($category_id) {
		$implode = array();

		$query = $this->db->query("SELECT filter_id FROM " . DB_PREFIX . "category_filter WHERE category_id = '" . (int)$category_id . "'");

		foreach ($query->rows as $result) {
			$implode[] = (int)$result['filter_id'];
		}

		$filter_group_data = array();

		if ($implode) {
			$filter_group_query = $this->db->query("SELECT DISTINCT f.filter_group_id, fgd.name, fg.sort_order FROM " . DB_PREFIX . "filter f LEFT JOIN " . DB_PREFIX . "filter_group fg ON (f.filter_group_id = fg.filter_group_id) LEFT JOIN " . DB_PREFIX . "filter_group_description fgd ON (fg.filter_group_id = fgd.filter_group_id) WHERE f.filter_id IN (" . implode(',', $implode) . ") AND fgd.language_id = '" . (int)$this->config->get('config_language_id') . "' GROUP BY f.filter_group_id ORDER BY fg.sort_order, LCASE(fgd.name)");

			foreach ($filter_group_query->rows as $filter_group) {
				$filter_data = array();

				$filter_query = $this->db->query("SELECT DISTINCT f.filter_id, fd.name FROM " . DB_PREFIX . "filter f LEFT JOIN " . DB_PREFIX . "filter_description fd ON (f.filter_id = fd.filter_id) WHERE f.filter_id IN (" . implode(',', $implode) . ") AND f.filter_group_id = '" . (int)$filter_group['filter_group_id'] . "' AND fd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY f.sort_order, LCASE(fd.name)");

				foreach ($filter_query->rows as $filter) {
					$filter_data[] = array(
						'filter_id' => $filter['filter_id'],
						'name'      => $filter['name']
					);
				}

				if ($filter_data) {
					$filter_group_data[] = array(
						'filter_group_id' => $filter_group['filter_group_id'],
						'name'            => $filter_group['name'],
						'filter'          => $filter_data
					);
				}
			}
		}

		return $filter_group_data;
	}

	public function getCategoryLayoutId($category_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category_to_layout WHERE category_id = '" . (int)$category_id . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

		if ($query->num_rows) {
			return (int)$query->row['layout_id'];
		} else {
			return 0;
		}
	}

	public function getTotalCategoriesByCategoryId($parent_id = 0) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.parent_id = '" . (int)$parent_id . "' AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND c.status = '1' AND c.is_deleted = 0");

		return $query->row['total'];
	}

	public function getSellers() {
//        $query = $this->db->query("SELECT otc.`screenname`,otc.`customer_id` FROM `" . DB_PREFIX . "customerpartner_to_customer` otc WHERE otc.`is_partner` = 1" . " AND otc.`menu_show` = 1");
        $query = $this->db->query("SELECT
                                  otc.`screenname`,
                                  otc.`customer_id`,
                                  t1.product_count
                                FROM
                                  `oc_customerpartner_to_customer` otc
                                  LEFT JOIN
                                    (SELECT
                                      COUNT(ctp.`product_id`) AS product_count,
                                      ctp.`customer_id` AS seller_id
                                    FROM
                                      `oc_customerpartner_to_product` ctp
                                    GROUP BY ctp.`customer_id`) t1
                                    ON otc.`customer_id` = t1.seller_id
                                WHERE otc.`is_partner` = 1
                                  AND otc.`menu_show` = 1
                                ORDER BY t1.product_count DESC");
        return $query;
    }

    public function getSellersByCountryId($country) {
//        $query = $this->db->query("SELECT otc.`screenname`,otc.`customer_id` FROM `" . DB_PREFIX . "customerpartner_to_customer` otc WHERE otc.`is_partner` = 1" . " AND otc.`menu_show` = 1");

        if(! $country){
            $query = $this->db->query("SELECT
                                  otc.`screenname`,
                                  otc.`customer_id`,
                                  t1.product_count
                                FROM
                                  `oc_customerpartner_to_customer` otc
                                  LEFT JOIN
                                    (SELECT
                                      COUNT(ctp.`product_id`) AS product_count,
                                      ctp.`customer_id` AS seller_id
                                    FROM
                                      `oc_customerpartner_to_product` ctp
                                    GROUP BY ctp.`customer_id`) t1
                                    ON otc.`customer_id` = t1.seller_id
                                    LEFT JOIN oc_customer cus ON ( cus.customer_id = t1.seller_id )
                                WHERE otc.`is_partner` = 1
                                  AND otc.`menu_show` = 1
                                   and cus.status=1
                                ORDER BY t1.product_count DESC");
            return $query;
        }else if($country == 'JPN'){
            $query = $this->db->query("SELECT
                                        otc.`screenname`,
                                        otc.`customer_id`
                                        FROM
                                        `oc_customer` oc,
                                        `oc_country` cou,
                                        `oc_customerpartner_to_customer` otc

                                        WHERE otc.`is_partner` = 1
                                        AND otc.`menu_show` = 1
                                        AND oc.customer_id = otc.customer_id
                                        AND oc.country_id = cou.country_id
                                       AND cou.iso_code_3 = '" .$country. "'
                                        and oc.status=1
                                        ");
            return $query;
        }
        else{
            $query = $this->db->query("SELECT
                                        otc.`screenname`,
                                        otc.`customer_id`,
                                        t1.product_count
                                        FROM
                                        `oc_customer` oc,
                                        `oc_country` cou,
                                        `oc_customerpartner_to_customer` otc
                                        LEFT JOIN
                                        (SELECT
                                          COUNT(ctp.`product_id`) AS product_count,
                                          ctp.`customer_id` AS seller_id
                                        FROM
                                          `oc_customerpartner_to_product` ctp
                                        GROUP BY ctp.`customer_id`) t1
                                        ON otc.`customer_id` = t1.seller_id
                                         LEFT JOIN oc_customer cus ON ( cus.customer_id = t1.seller_id )
                                        WHERE otc.`is_partner` = 1
                                        AND otc.`menu_show` = 1
                                        AND oc.customer_id = otc.customer_id
                                        AND oc.country_id = cou.country_id
                                        AND cou.iso_code_3 = '" .$country. "'
                                        and cus.status=1
                                        ORDER BY t1.product_count DESC");
            return $query;
        }
    }


    /**
     * @param array $category
     * @return array
     */
    public function getCategoryByParent($category): array
    {
        if (empty($category)) {
            return $category;
        }

        $all_category = $this->getAllCategory();

        $temp = [];
        foreach ($category as $category_id) {
            if (isset($all_category[$category_id])) {
                $temp = array_merge($all_category[$category_id]['children_ids'] ?? [$category_id], $temp);
            }
        }

        return array_unique(array_merge($temp, $category));
    }

    /**
     * 此处使用引用，而不是递归，故做两边遍历循环
     * @return array
     * @todo 后期需要用递归实现，copy from \catalog\model\customerpartner\marketing_campaign\request.php
     */
    public function getAllCategory(): array
    {
        $objs = $this->orm->table('oc_category')
            ->select(['category_id', 'parent_id'])
            ->get();
        $parent_category = [];
        foreach ($objs as $obj) {
            $parent_category[$obj->parent_id]['data'][$obj->category_id] = [
                'category_id' => $obj->category_id,
                'parent_id' => $obj->parent_id,
            ];
        }

        unset($parent_category[0]);
        foreach ($parent_category as $_parent_id => &$category_arr) {
            foreach ($category_arr['data'] as $category_id => &$category) {
                if (isset($parent_category[$category['category_id']])) {    //当前category 拥有子节点
                    $category['data'] = $parent_category[$category['category_id']]['data'];
                    $category['children_ids'] = array_unique(
                        array_merge(
                            $category['children_ids'] ?? [],    //
                            $parent_category[$category['category_id']]['children_ids'] ?? [],
                            array_keys($parent_category[$category['category_id']]['data'] ?? [])
                        )
                    );
                }
                $category_arr['children_ids'] = array_unique(
                    array_merge(
                        $category_arr['children_ids'] ?? [],
                        array_keys($category['data'] ?? []),
                        $category['children_ids'] ?? []
                    )
                );
            }
            $category_arr['children_ids'] = array_unique(
                array_merge(
                    $category_arr['children_ids'] ?? [],
                    array_keys($category_arr['data'] ?? [])
                )
            );

        }
        unset($category_arr, $category);
        // 下面的代码 是特意重复的，不要删啊，不然获取的数据会缺少一部分的。
        foreach ($parent_category as $_parent_id => &$category_arr) {
            foreach ($category_arr['data'] as $category_id => &$category) {
                if (isset($parent_category[$category['category_id']])) {    //当前category 拥有子节点
                    $category['data'] = $parent_category[$category['category_id']]['data'];
                    $category['children_ids'] = array_unique(
                        array_merge(
                            $category['children_ids'] ?? [],    //
                            $parent_category[$category['category_id']]['children_ids'] ?? [],
                            array_keys($parent_category[$category['category_id']]['data'] ?? [])
                        )
                    );
                }
                $category_arr['children_ids'] = array_unique(
                    array_merge(
                        $category_arr['children_ids'] ?? [],
                        array_keys($category['data'] ?? []),
                        $category['children_ids'] ?? []
                    )
                );
            }
            $category_arr['children_ids'] = array_unique(
                array_merge(
                    $category_arr['children_ids'] ?? [],
                    array_keys($category_arr['data'] ?? [])
                )
            );

        }

        return $parent_category;
    }
}
