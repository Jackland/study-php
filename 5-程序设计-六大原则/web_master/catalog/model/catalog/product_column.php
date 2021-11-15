<?php

use App\Enums\Product\Channel\ChannelType;
use App\Enums\Product\Channel\NewArrivals;
use App\Enums\Product\ProductType;
use App\Helper\CountryHelper;
use App\Models\Product\Channel\ChannelParamConfigValue;
use App\Models\Product\Product;
use App\Repositories\Product\Channel\ChannelRepository;
use Illuminate\Database\Query\Expression;

use App\Enums\Warehouse\ReceiptOrderStatus;

/**
 * Class ModelCatalogProductColumn
 * @property ModelCatalogCategory $model_catalog_category
 * @property ModelToolSort $model_tool_sort
 */
class ModelCatalogProductColumn extends Model
{
    private $customer_id = 0;

    private $country_map = [
        'JPN' => 107,
        'GBR' => 222,
        'DEU' => 81,
        'USA' => 223
    ];

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
    }

    public function getRecommendProductIds($productIds, $countryCode)
    {
        $channelRepository = app(ChannelRepository::class);
        return  Product::query()->alias('p')
            ->leftJoinRelations(['customerPartnerToProduct as ctp'])
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'ctp.customer_id')
            ->where([
                ['c.status', '=', 1],
                ['p.product_type', '=', ProductType::NORMAL],
                ['p.status', '=', 1],
                ['p.quantity', '>', 0],
                ['p.image', '<>', ''],
                ['c.country_id', '=', CountryHelper::getCountryByCode(session()->get('country'))],
            ])
            ->whereNotNull('p.image')
            //->whereNotIn('p.product_id', $channelRepository->delicacyManagementProductId((int)customer()->getId()))
            ->whereIn('c.customer_id', $channelRepository->getAvailableSellerId())
            ->whereIn('p.product_id', array_diff($productIds,$channelRepository->delicacyManagementProductId((int)customer()->getId())))
            ->select('p.product_id')
            ->get()
            ->toArray();
    }

    /**
     * 广告推荐
     * @param array $product_id_arr
     * @param string $countryCode
     * @return array
     */
    public function recommendFiledHome($product_id_arr, $countryCode)
    {
        if (!$product_id_arr) {
            return [];
        }


        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();

        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        $str = implode(',', $product_id_arr);
        if ($str) {
            if ($buyer_id > 0 && $isPartner == 0) {
                $sql = "
    SELECT p.product_id
    FROM oc_product AS p
    JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
    JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
    JOIN oc_country AS cou ON cou.country_id=c.country_id
    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
    LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
    WHERE
        p.product_id IN ({$str})
        AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
        AND p.quantity>0 AND p.product_type = 0
        AND p.image IS NOT NULL AND p.image!=''
        AND c.`status`=1 {$condition_customer_group}
        AND c2c.`show`=1
        AND p2s.store_id = {$store_id}
        AND cou.`iso_code_3` = '{$countryCode}'
        AND (dm.product_display IS NULL OR dm.product_display=1)
        AND NOT EXISTS (
            SELECT dmg.id FROM oc_delicacy_management_group AS dmg
            JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
            JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
            WHERE
                dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                AND dmg.status=1 and bgl.status=1 and pgl.status=1
        )";
            } else {
                $sql = "
    SELECT p.product_id
    FROM oc_product AS p
    JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
    JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
    JOIN oc_country AS cou ON cou.country_id=c.country_id
    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
    WHERE
        p.product_id IN ({$str})
        AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
        AND p.quantity>0 AND p.product_type = 0
        AND p.image IS NOT NULL AND p.image!=''
        AND c.`status`=1 {$condition_customer_group}
        AND c2c.`show`=1
        AND p2s.store_id = {$store_id}
        AND cou.`iso_code_3` = '{$countryCode}'";
                // ORDER BY FIND_IN_SET(p.product_id,'{$str}')";
            }

            $query = $this->db->query($sql);
            $results = $query->rows;

            return $results;
        } else {
            return [];
        }
        return [];
    }


    /**
     * 广告推荐
     * @param array $product_id_arr
     * @param string $countryCode
     * @param int $category_id
     * @return array
     */
    public function recommendFiledColumn($product_id_arr, $countryCode, $category_id = 0)
    {
        if (!$product_id_arr) {
            return [];
        }


        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();

        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        $condition_product = '';
        if ($category_id > 0) {
            $condition_product .= ' AND p2c.category_id=' . $category_id;
        }


        $str = implode(',', $product_id_arr);
        if ($str) {
            if ($buyer_id > 0 && $isPartner == 0) {
                $sql = "
    SELECT p.product_id
    FROM oc_product AS p
    JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
    JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
    JOIN oc_country AS cou ON cou.country_id=c.country_id
    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
    LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
    LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
    WHERE
        p.product_id IN ({$str})
        AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0 AND p.product_type = 0 {$condition_product}
        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
        AND p.image IS NOT NULL AND p.image!=''
        AND c.`status`=1 {$condition_customer_group}
        AND c2c.`show`=1
        AND p2s.store_id = {$store_id}
        AND (dm.product_display IS NULL OR dm.product_display=1)
        AND cou.`iso_code_3` = '{$countryCode}'
        AND NOT EXISTS (
            SELECT dmg.id FROM oc_delicacy_management_group AS dmg
            JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
            JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
            WHERE
                dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                AND dmg.status=1 and bgl.status=1 and pgl.status=1
        )
    ORDER BY FIND_IN_SET(p.product_id,'{$str}')";
            } else {
                $sql = "
    SELECT p.product_id
    FROM oc_product AS p
    JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
    JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
    JOIN oc_country AS cou ON cou.country_id=c.country_id
    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
    LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
    WHERE
        p.product_id IN ({$str})
        AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0 AND p.product_type = 0 {$condition_product}
        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
        AND p.image IS NOT NULL AND p.image!=''
        AND c.`status`=1 {$condition_customer_group}
        AND c2c.`show`=1
        AND p2s.store_id = {$store_id}
        AND cou.`iso_code_3` = '{$countryCode}'
    ORDER BY FIND_IN_SET(p.product_id,'{$str}')";
            }

            $query = $this->db->query($sql);
            $results = $query->rows;

            return $results;
        } else {
            return [];
        }
        return [];
    }


    public function recommendCategory($product_id_arr, $countryCode)
    {
        if (!$product_id_arr) {
            return [];
        }


        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();

        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        $str = implode(',', $product_id_arr);
        if ($str) {
            if ($buyer_id > 0 && $isPartner == 0) {
                $sql = "
    SELECT t.category_id, t.name
    FROM (

        SELECT
            DISTINCT p.product_id
            ,IFNULL(category.category_id, -1) AS category_id
            ,IFNULL(cd.name, 'Others') AS name
            ,category.sort_order
        FROM oc_product AS p
        JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        JOIN oc_country AS cou ON cou.country_id=c.country_id
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id
        LEFT JOIN oc_category AS category ON category.category_id=cp.path_id
        LEFT JOIN oc_category_description AS cd ON cd.category_id=category.category_id
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
        WHERE
            (category.parent_id=0 OR category.parent_id IS NULL)
            AND (cp.level= 0 OR cp.level IS NULL)
            AND p.product_id IN ({$str})
            AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0 AND p.product_type = 0
            AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND (dm.product_display IS NULL OR dm.product_display=1)
            AND cou.`iso_code_3` = '{$countryCode}'
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        ORDER BY category.sort_order ,LCASE(cd.`name`)

    ) AS t
    GROUP BY t.category_id
    ORDER BY t.sort_order ASC, t.name ASC";
            } else {
                $sql = "
    SELECT t.category_id, t.name
    FROM (

        SELECT
            DISTINCT p.product_id
            ,IFNULL(category.category_id, -1) AS category_id
            ,IFNULL(cd.name, 'Others') AS name
            ,category.sort_order
        FROM oc_product AS p
        JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        JOIN oc_country AS cou ON cou.country_id=c.country_id
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id
        LEFT JOIN oc_category AS category ON category.category_id=cp.path_id
        LEFT JOIN oc_category_description AS cd ON cd.category_id=category.category_id
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        WHERE
            (category.parent_id=0 OR category.parent_id IS NULL)
            AND (cp.level= 0 OR cp.level IS NULL)
            AND p.product_id IN ({$str})
            AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0 AND p.product_type = 0
            AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND cou.`iso_code_3` = '{$countryCode}'
        ORDER BY category.sort_order ,LCASE(cd.`name`)

    ) AS t
    GROUP BY t.category_id
    ORDER BY t.sort_order ASC, t.name ASC";
            }

            $query = $this->db->query($sql);
            $results = $query->rows;

            return $results;
        } else {
            return [];
        }
        return [];
    }


    /**
     * 根据国别代码，获取促销商品
     * @param array $product_id_arr
     * @param string $countryCode
     * @return array
     */
    public function promotionHome($product_id_arr, $countryCode)
    {
        if (!$product_id_arr) {
            return [];
        }


        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();


        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        if ($buyer_id > 0 && $isPartner == 0) {
            $sql = "
    SELECT p.product_id
    FROM oc_product AS p
    JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
    JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
    JOIN oc_country AS country ON country.country_id=c.country_id
    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
    LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
    WHERE
        p.product_id IN ({$product_id_arr})
        AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
        AND p.quantity>0 AND p.product_type = 0
        AND p.image IS NOT NULL AND p.image!=''
        AND c.`status`=1 {$condition_customer_group}
        AND c2c.`show`=1
        AND p2s.store_id = {$store_id}
        AND country.`iso_code_3` = '{$countryCode}'
        AND (dm.product_display IS NULL OR dm.product_display=1)
        AND NOT EXISTS (
            SELECT dmg.id FROM oc_delicacy_management_group AS dmg
            JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
            JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
            WHERE
                dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                AND dmg.status=1 and bgl.status=1 and pgl.status=1
        )";
            //ORDER BY FIND_IN_SET(p.product_id,'{$str}')";
        } else {
            $sql = "
    SELECT p.product_id
    FROM oc_product AS p
    JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
    JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
    JOIN oc_country AS country ON country.country_id=c.country_id
    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
    WHERE
        p.product_id IN ({$product_id_arr})
        AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
        AND p.quantity>0 AND p.product_type = 0
        AND p.image IS NOT NULL AND p.image!=''
        AND c.`status`=1 {$condition_customer_group}
        AND c2c.`show`=1
        AND p2s.store_id = {$store_id}
        AND country.`iso_code_3` = '{$countryCode}'";
            //ORDER BY FIND_IN_SET(p.product_id,'{$str}')";
        }


        $query = $this->db->query($sql);
        $results = $query->rows;
        return $results;
    }


    /**
     * 根据国别代码，获取促销商品
     * @param string $countryCode
     * @param int $category_id
     * @return array
     */
    public function promotionColumn($countryCode, $category_id = 0)
    {
        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();


        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        $condition_product = '';
        if ($category_id > 0) {
            $condition_product .= ' AND p2c.category_id=' . $category_id;
        }


        $sql = "SELECT setting FROM oc_module WHERE `code`='homePromotion'";
        $query = $this->db->query($sql);
        if ($query->row) {
            $setting = $query->row['setting'];
            $settingArr = json_decode($setting, true);
            if ($settingArr && isset($settingArr[$countryCode]) && $settingArr[$countryCode]) {
                $str = implode(',', $settingArr[$countryCode]);
                if ($str) {
                    if ($buyer_id > 0 && $isPartner == 0) {
                        $sql = "
    SELECT p.product_id
    FROM oc_product AS p
    JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
    JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
    JOIN oc_country AS country ON country.country_id=c.country_id
    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
    LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
    LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
    WHERE
        p.product_id IN ({$str})
        AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
        AND p.quantity>0 AND p.product_type = 0 {$condition_product}
        AND p.image IS NOT NULL AND p.image!=''
        AND c.`status`=1 {$condition_customer_group}
        AND c2c.`show`=1
        AND p2s.store_id = {$store_id}
        AND (dm.product_display IS NULL OR dm.product_display=1)
        AND NOT EXISTS (
            SELECT dmg.id FROM oc_delicacy_management_group AS dmg
            JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
            JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
            WHERE
                dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                AND dmg.status=1 and bgl.status=1 and pgl.status=1
        )
    ORDER BY FIND_IN_SET(p.product_id,'{$str}')";
                    } else {
                        $sql = "
    SELECT p.product_id
    FROM oc_product AS p
    JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
    JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
    JOIN oc_country AS country ON country.country_id=c.country_id
    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
    LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
    WHERE
        p.product_id IN ({$str})
        AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
        AND p.quantity>0 AND p.product_type = 0 {$condition_product}
        AND p.image IS NOT NULL AND p.image!=''
        AND c.`status`=1 {$condition_customer_group}
        AND c2c.`show`=1
        AND p2s.store_id = {$store_id}
    ORDER BY FIND_IN_SET(p.product_id,'{$str}')";
                    }


                    $query = $this->db->query($sql);
                    $results = $query->rows;
                    return $results;
                } else {
                    return [];
                }
            } else {
                return [];
            }
        };
        return [];
    }


    public function promotionCategory($countryCode)
    {
        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();


        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        $sql = "SELECT setting FROM oc_module WHERE `code`='homePromotion'";
        $query = $this->db->query($sql);
        if ($query->row) {
            $setting = $query->row['setting'];
            $settingArr = json_decode($setting, true);
            if ($settingArr && isset($settingArr[$countryCode]) && $settingArr[$countryCode]) {
                $str = implode(',', $settingArr[$countryCode]);
                if ($str) {
                    if ($buyer_id > 0 && $isPartner == 0) {
                        $sql = "
    SELECT t.category_id, t.name
    FROM (

        SELECT
            DISTINCT p.product_id
            ,IFNULL(category.category_id, -1) AS category_id
            ,IFNULL(cd.name, 'Others') AS name
            ,category.sort_order
        FROM oc_product AS p
        JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id
        LEFT JOIN oc_category AS category ON category.category_id=cp.path_id
        LEFT JOIN oc_category_description AS cd ON cd.category_id=category.category_id
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
        WHERE
            (category.parent_id=0 OR category.parent_id IS NULL)
            AND (cp.level= 0 OR cp.level IS NULL)
            AND p.product_id IN ({$str})
            AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
            AND p.quantity>0 AND p.product_type = 0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND (dm.product_display IS NULL OR dm.product_display=1)
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        ORDER BY category.sort_order ,LCASE(cd.`name`)

    ) AS t
    GROUP BY t.category_id
    ORDER BY t.sort_order ASC, t.name ASC";
                    } else {
                        $sql = "
    SELECT t.category_id, t.name
    FROM (

        SELECT
            DISTINCT p.product_id
            ,IFNULL(category.category_id, -1) AS category_id
            ,IFNULL(cd.name, 'Others') AS name
            ,category.sort_order
        FROM oc_product AS p
        JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id
        LEFT JOIN oc_category AS category ON category.category_id=cp.path_id
        LEFT JOIN oc_category AS category ON category.category_id=p2c.category_id AND category.parent_id=0
        LEFT JOIN oc_category_description AS cd ON cd.category_id=category.category_id
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        WHERE
            (category.parent_id=0 OR category.parent_id IS NULL)
            AND (cp.level= 0 OR cp.level IS NULL)
            AND p.product_id IN ({$str})
            AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
            AND p.quantity>0 AND p.product_type = 0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
        GROUP BY p2c.category_id
        ORDER BY category.sort_order ,LCASE(cd.`name`)

    ) AS t
    GROUP BY t.category_id
    ORDER BY t.sort_order ASC, t.name ASC";
                    }


                    $query = $this->db->query($sql);
                    $results = $query->rows;
                    return $results;
                } else {
                    return [];
                }
            } else {
                return [];
            }
        };
        return [];
    }


    /**
     * todo 获取新品预售
     * @param string $countryCode
     * @param int $limit
     * @return array
     */
    public function comingSoonsHome($countryCode, $limit = 12)
    {
        if (!$countryCode) {
            return [];
        }
        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();


        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        if ($buyer_id > 0 && $isPartner == 0) {
            $sql = "
    SELECT
        tmp.is_new, tmp.product_id, tmp.date_added,tmp.expected_date,
        CASE
            WHEN tmp.p_associate IS NULL
                THEN tmp.product_id
            ELSE tmp.p_associate
            END AS 'p_associate'
    FROM (
        SELECT
            p.product_id, p.date_added,ro.`expected_date`,
            CASE
                WHEN rod.receive_order_id IS NOT NULL AND DATEDIFF( Now( ), p.date_added ) <= " . NEW_ARRIVAL_DAY . "
                    THEN 1
                ELSE 0
                END
                AS is_new ,
            CASE
                WHEN GROUP_CONCAT(
                DISTINCT opa.associate_product_id
                ORDER BY opa.associate_product_id
                ) IS NOT NULL
                    THEN GROUP_CONCAT(
                DISTINCT opa.associate_product_id
                ORDER BY opa.associate_product_id
                )
                ELSE p.product_id
                END AS p_associate
        FROM
            oc_product AS p
            LEFT JOIN oc_customerpartner_to_product AS `c2p` ON `c2p`.`product_id` = `p`.`product_id`
            LEFT JOIN tb_sys_batch sb ON sb.product_id=p.product_id
            LEFT JOIN oc_customer as `c` on `c`.`customer_id` = `c2p`.`customer_id`
            LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
            LEFT JOIN oc_country cou ON cou.country_id = c.country_id
            LEFT JOIN tb_sys_receipts_order_detail AS rod ON rod.product_id = p.product_id
            LEFT JOIN tb_sys_receipts_order AS ro ON ro.receive_order_id = rod.receive_order_id
            LEFT JOIN oc_product_associate  as opa ON `opa`.`product_id` = rod.product_id
            LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
            LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
        WHERE
            p.`status` = 1
            AND p.is_deleted = 0
            AND p.buyer_flag = 1
            AND p.part_flag=0
            AND p.quantity=0
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND cou.`iso_code_3` = '{$countryCode}'
            AND rod.`expected_qty` IS NOT NULL
            AND ro.`expected_date` IS NOT NULL
            AND ro.`expected_date` > NOW( )
            AND ro.`status` = " . ReceiptOrderStatus::TO_BE_RECEIVED . "
            AND (dm.product_display IS NULL OR dm.product_display=1)
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        GROUP BY
            p.product_id
        /*HAVING SUM(sb.onhand_qty) IS NULL*/
        ORDER BY
            ro.`expected_date` ASC, p.product_id DESC
            LIMIT 100
    ) AS tmp
    GROUP BY tmp.p_associate
    ORDER BY tmp.date_added DESC, tmp.product_id DESC ";
        } else {
            $sql = "
    SELECT
        tmp.is_new, tmp.product_id, tmp.date_added,tmp.expected_date,
        CASE
            WHEN tmp.p_associate IS NULL
                THEN tmp.product_id
            ELSE tmp.p_associate
            END AS 'p_associate'
    FROM (
        SELECT
            p.product_id, p.date_added,ro.`expected_date`,
            CASE
                WHEN rod.receive_order_id IS NOT NULL AND DATEDIFF( Now( ), p.date_added ) <= " . NEW_ARRIVAL_DAY . "
                    THEN 1
                ELSE 0
                END
                AS is_new ,
            CASE
                WHEN GROUP_CONCAT(
                DISTINCT opa.associate_product_id
                ORDER BY opa.associate_product_id
                ) IS NOT NULL
                    THEN GROUP_CONCAT(
                DISTINCT opa.associate_product_id
                ORDER BY opa.associate_product_id
                )
                ELSE p.product_id
                END AS p_associate
        FROM
            oc_product AS p
            LEFT JOIN oc_customerpartner_to_product AS `c2p` ON `c2p`.`product_id` = `p`.`product_id`
            LEFT JOIN tb_sys_batch sb ON sb.product_id=p.product_id
            LEFT JOIN oc_customer as `c` on `c`.`customer_id` = `c2p`.`customer_id`
            LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
            LEFT JOIN oc_country cou ON cou.country_id = c.country_id
            LEFT JOIN tb_sys_receipts_order_detail AS rod ON rod.product_id = p.product_id
            LEFT JOIN tb_sys_receipts_order AS ro ON ro.receive_order_id = rod.receive_order_id
            LEFT JOIN oc_product_associate  as opa ON `opa`.`product_id` = rod.product_id
            LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        WHERE
            p.`status` = 1
            AND p.is_deleted = 0
            AND p.buyer_flag = 1
            AND p.part_flag=0
            AND p.quantity=0
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND cou.`iso_code_3` = '{$countryCode}'
            AND rod.`expected_qty` IS NOT NULL
            AND ro.`expected_date` IS NOT NULL
            AND ro.`expected_date` > NOW( )
            AND ro.`status` = " . ReceiptOrderStatus::TO_BE_RECEIVED . "
        GROUP BY
            p.product_id
        /*HAVING SUM(sb.onhand_qty) IS NULL*/
        ORDER BY
            ro.`expected_date` ASC, p.product_id DESC
            LIMIT 100
    ) AS tmp
    GROUP BY tmp.p_associate
    ORDER BY tmp.date_added DESC, tmp.product_id DESC ";
        }


        $query = $this->db->query($sql);
        $results = $query->rows;


        //同款商品去重，留一个新增时间最晚的作为一个系列的代表产品
        $lists = [];
        $index = 1;
        foreach ($results as $value) {
            if ($index > $limit) {
                break;
            }
            $product_ids = trim($value['p_associate']);
            if (!isset($lists[$product_ids])) {
                $lists[$product_ids] = $value;
                $index++;
            } else {
                $data_added_new = $value['data_added'];
                $data_added_old = $lists[$product_ids]['data_added'];
                if ($data_added_new > $data_added_old) {
                    $lists[$product_ids] = $value;
                }
            }

        }


        return $lists;
    }


    /**
     * 获取新品预售
     * @param string $countryCode
     * @param int $category_id
     * @param int $row_offset
     * @param int $row_limit
     * @return array
     */
    public function comingSoonsColumn($countryCode, $category_id = 0, $row_offset, $row_limit = 12)
    {
        if (!$countryCode) {
            return [];
        }
        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();


        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }


        $condition = "";
        if ($category_id > 0) {
            $condition .= ' AND p2c.category_id=' . $category_id;
        }


        if ($buyer_id > 0 && $isPartner == 0) {
            $sql = "
    SELECT
        tmp.is_new, tmp.product_id, tmp.date_added, tmp.expected_date, tmp.p_associate
    FROM (

        SELECT
            p.product_id, p.date_added,ro.`expected_date`
            ,CASE
                WHEN rod.receive_order_id IS NOT NULL AND DATEDIFF( Now( ), p.date_added ) <= " . NEW_ARRIVAL_DAY . "
                    THEN 1
                ELSE 0
                END
                AS is_new
            ,IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id),p.product_id) AS p_associate
        FROM
            oc_product AS p
            LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
            LEFT JOIN oc_customerpartner_to_product AS `c2p` ON `c2p`.`product_id` = `p`.`product_id`
            LEFT JOIN tb_sys_batch sb ON sb.product_id=p.product_id
            LEFT JOIN oc_customer as `c` on `c`.`customer_id` = `c2p`.`customer_id`
            LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
            LEFT JOIN oc_country cou ON cou.country_id = c.country_id
            LEFT JOIN tb_sys_receipts_order_detail AS rod ON rod.product_id = p.product_id
            LEFT JOIN tb_sys_receipts_order AS ro ON ro.receive_order_id = rod.receive_order_id
            LEFT JOIN oc_product_associate  as opa ON `opa`.`product_id` = rod.product_id
            LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
            LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
        WHERE
            p.`status` = 1
            AND p.is_deleted = 0
            AND p.buyer_flag = 1
            AND p.part_flag=0
            AND p.quantity=0
            AND p.product_type=0
            {$condition}
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND cou.`iso_code_3` = '{$countryCode}'
            AND rod.`expected_qty` IS NOT NULL
            AND ro.`expected_date` IS NOT NULL
            AND ro.`expected_date` > NOW( )
            AND ro.`status` = " . ReceiptOrderStatus::TO_BE_RECEIVED . "
            AND (dm.product_display IS NULL OR dm.product_display=1)
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        GROUP BY
            p.product_id
        /*HAVING SUM(sb.onhand_qty) IS NULL*/
        ORDER BY
            ro.`expected_date` ASC, p.product_id DESC

    ) AS tmp
    GROUP BY tmp.p_associate
    ORDER BY tmp.date_added DESC, tmp.product_id DESC
    LIMIT " . ($row_offset - 1) * $row_limit . ",{$row_limit}";
        } else {
            $sql = "
    SELECT
        tmp.is_new, tmp.product_id, tmp.date_added, tmp.expected_date, tmp.p_associate
    FROM (

        SELECT
            p.product_id, p.date_added,ro.`expected_date`
            ,CASE
                WHEN rod.receive_order_id IS NOT NULL AND DATEDIFF( Now( ), p.date_added ) <= " . NEW_ARRIVAL_DAY . "
                    THEN 1
                ELSE 0
                END
                AS is_new
            ,IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id),p.product_id) AS p_associate
        FROM
            oc_product AS p
            LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
            LEFT JOIN oc_customerpartner_to_product AS `c2p` ON `c2p`.`product_id` = `p`.`product_id`
            LEFT JOIN tb_sys_batch sb ON sb.product_id=p.product_id
            LEFT JOIN oc_customer as `c` on `c`.`customer_id` = `c2p`.`customer_id`
            LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
            LEFT JOIN oc_country cou ON cou.country_id = c.country_id
            LEFT JOIN tb_sys_receipts_order_detail AS rod ON rod.product_id = p.product_id
            LEFT JOIN tb_sys_receipts_order AS ro ON ro.receive_order_id = rod.receive_order_id
            LEFT JOIN oc_product_associate  as opa ON `opa`.`product_id` = rod.product_id
            LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        WHERE
            p.`status` = 1
            AND p.is_deleted = 0
            AND p.buyer_flag = 1
            AND p.part_flag=0
            AND p.quantity=0
            AND p.product_type=0
            {$condition}
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND cou.`iso_code_3` = '{$countryCode}'
            AND rod.`expected_qty` IS NOT NULL
            AND ro.`expected_date` IS NOT NULL
            AND ro.`expected_date` > NOW( )
            AND ro.`status` = " . ReceiptOrderStatus::TO_BE_RECEIVED . "
        GROUP BY
            p.product_id
        /*HAVING SUM(sb.onhand_qty) IS NULL*/
        ORDER BY
            ro.`expected_date` ASC, p.product_id DESC

    ) AS tmp
    GROUP BY tmp.p_associate
    ORDER BY tmp.date_added DESC, tmp.product_id DESC
    LIMIT " . ($row_offset - 1) * $row_limit . ",{$row_limit}";
        }


        $query = $this->db->query($sql);
        $results = $query->rows;


        //同款商品去重，留一个新增时间最晚的作为一个系列的代表产品
        $lists = [];
        $index = 1;
        foreach ($results as $value) {
            if ($index > $row_limit) {
                break;
            }
            $product_ids = trim($value['p_associate']);
            if (!isset($lists[$product_ids])) {
                $lists[$product_ids] = $value;
                $index++;
            } else {
                $data_added_new = $value['data_added'];
                $data_added_old = $lists[$product_ids]['data_added'];
                if ($data_added_new > $data_added_old) {
                    $lists[$product_ids] = $value;
                }
            }

        }


        return $lists;
    }


    /**
     * 新品到货
     * @param string $countryCode
     * @param int $row_offset
     * @param int $row_limit
     * @return array
     * @throws Exception
     */
    public function newArrivalsHome($countryCode, $row_offset = 0, $row_limit = 6)
    {
        //102138  date:20200721
        //理想结果：4个不同店铺的复杂交易产品+2个不同店铺的非复杂交易产品
        //筛选过程：
        //结果集：45天内新增的同款不同色去重的产品(入库时间降序、下载量降序、新增时间降序(即产品id降序))；如果不足6个，则去除[45天内新增]条件，即同款不同色去重的产品(入库时间降序、下载量降序、新增时间降序(即产品id降序))
        //
        //取2个不同seller的非复杂交易产品；如果取不到2个不同seller的非复杂交易产品，则取2个非复杂交易的产品；根据实际情况可能取出0个非复杂交易产品；
        //剩余的X个位置，取X个不同seller的复杂交易的产品；取X个不同seller的复杂交易产品；如果娶不到X个不同seller的复杂交易产品，则取X个复杂交易产品；
        //
        //如果复杂交易的产品不足X个，则 增加非复杂交易产品的数量(按照先取不同Seller、再按照允许有相同seller)，将New Arrival频道凑足6个产品；
        //
        //页面展示：如果取出的产品小于等于2个，则隐藏该频道？
        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();
        $this->load->model('tool/sort');
        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }

        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }

        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }


        //复杂交易的产品
        //tb_sys_margin_template
        //oc_rebate_template_item
        $product_list = $this->model_tool_sort->getComplexTransactionsProductIdByCountry($this->country_map[$countryCode]);
        $product_str = implode(',', $product_list) ?? 0;//复杂交易的产品，剔除了保证金店铺的产品和服务店铺的产品
        $days = 45;

        //#984 需求新增 首页&频道页配件产品， oc_product与oc_product_to_tag任意表中标记配件，均判断该产品为配件，不在首页&频道页显示
        if ($buyer_id > 0 && $isPartner == 0) {
            //45天内 复杂交易的产品
            $sql_complex = "
                SELECT *
                FROM (

                    SELECT
                        p.product_id
                        ,p.date_added
                        ,p.downloaded
                        ,MAX(pExt.receive_date) AS create_time
                        ,c2p.customer_id
                        ,'0' AS 'common'
                        ,IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
                    FROM oc_product AS p
                    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
                    LEFT JOIN oc_product_exts AS pExt ON pExt.product_id=p.product_id
                    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
                    LEFT JOIN tb_sys_batch b ON b.product_id=p.product_id
                    LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
                    LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
                    LEFT JOIN oc_country AS country ON country.country_id=c.country_id
                    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
                    LEFT JOIN oc_product_associate AS opa ON opa.product_id=p.product_id
                    LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
                    WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0
                        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
                        AND p.product_id IN ({$product_str})
                        AND TIMESTAMPDIFF(DAY, pExt.receive_date, NOW()) < {$days}
                        AND TIMESTAMPDIFF(DAY, pExt.receive_date, NOW()) >= 0
                        AND p.image IS NOT NULL AND p.image!=''
                        AND p.product_type != 3
                        AND c.`status`=1 {$condition_customer_group}
                        AND c2c.`show`=1
                        AND p2s.store_id = {$store_id}
                        AND b.create_time IS NOT NULL
                        AND b.onhand_qty > 0
                        AND country.iso_code_3='{$countryCode}'
                        AND (dm.product_display IS NULL OR dm.product_display=1)
                        AND NOT EXISTS (
                            SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                            JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                            JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                            WHERE
                                dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                                AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                                AND dmg.status=1 and bgl.status=1 and pgl.status=1
                        )
                    GROUP BY p.product_id
                    HAVING SUM(b.onhand_qty) IS NOT NULL
                    ORDER BY MAX(pExt.receive_date) DESC, p.downloaded DESC, p.product_id DESC
                    LIMIT 0,600

                ) AS t
                GROUP BY t.p_associate
                ORDER BY t.create_time DESC, t.downloaded DESC, t.product_id DESC
                ";
            $query = $this->db->query($sql_complex);
            $results_complex = $query->rows;
            $count_complex = $query->num_rows;


            //45天内 非复杂交易的产品
            $sql_common = "
                SELECT *
                FROM (

                    SELECT
                        p.product_id
                        ,p.date_added
                        ,p.downloaded
                        ,MAX(pExt.receive_date) AS create_time
                        ,c2p.customer_id
                        ,'1' AS 'common'
                        ,IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
                    FROM oc_product AS p
                    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
                    LEFT JOIN oc_product_exts AS pExt ON pExt.product_id=p.product_id
                    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
                    LEFT JOIN tb_sys_batch b ON b.product_id=p.product_id
                    LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
                    LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
                    LEFT JOIN oc_country AS country ON country.country_id=c.country_id
                    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
                    LEFT JOIN oc_product_associate AS opa ON opa.product_id=p.product_id
                    LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
                    WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0
                        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
                        AND p.product_id NOT IN ({$product_str})
                        AND TIMESTAMPDIFF(DAY, pExt.receive_date, NOW()) < {$days}
                        AND TIMESTAMPDIFF(DAY, pExt.receive_date, NOW()) >= 0
                        AND p.product_type=0
                        AND p.image IS NOT NULL AND p.image!=''
                        AND c.`status`=1 {$condition_customer_group}
                        AND c2c.`show`=1
                        AND p2s.store_id = {$store_id}
                        AND b.create_time IS NOT NULL
                        AND b.onhand_qty > 0
                        AND country.iso_code_3='{$countryCode}'
                        AND (dm.product_display IS NULL OR dm.product_display=1)
                        AND NOT EXISTS (
                            SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                            JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                            JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                            WHERE
                                dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                                AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                                AND dmg.status=1 and bgl.status=1 and pgl.status=1
                        )
                    GROUP BY p.product_id
                    HAVING SUM(b.onhand_qty) IS NOT NULL
                    ORDER BY MAX(pExt.receive_date) DESC, p.downloaded DESC, p.product_id DESC
                    LIMIT 0,600

                ) AS t
                GROUP BY t.p_associate
                ORDER BY t.create_time DESC, t.downloaded DESC, t.product_id DESC
                ";
            $query = $this->db->query($sql_common);
            $results_common = $query->rows;
            $count_common = $query->num_rows;

            $this->session->set('column_newarrival_has_45day', 1);
            //if ($count_complex + $count_common < 6) {//取掉45天的限制
            //    $this->session->set('column_newarrival_has_45day', 0);
            //    //复杂交易的产品
            //    $sql_complex = "
            //        SELECT *
            //        FROM (
            //
            //            SELECT
            //                p.product_id
            //                ,p.date_added
            //                ,p.downloaded
            //                ,MAX(pExt.receive_date) AS create_time
            //                ,c2p.customer_id
            //                ,'0' AS 'common'
            //                ,IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
            //            FROM oc_product AS p
            //            LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
            //            LEFT JOIN oc_product_exts AS pExt ON pExt.product_id=p.product_id
            //            LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
            //            LEFT JOIN tb_sys_batch b ON b.product_id=p.product_id
            //            LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
            //            LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
            //            LEFT JOIN oc_country AS country ON country.country_id=c.country_id
            //            LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
            //            LEFT JOIN oc_product_associate AS opa ON opa.product_id=p.product_id
            //            LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
            //            WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0
            //                AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
            //                AND p.product_id IN ({$product_str})
            //                AND p.product_type !=3
            //                AND p.image IS NOT NULL AND p.image!=''
            //                AND c.`status`=1 {$condition_customer_group}
            //                AND c2c.`show`=1
            //                AND p2s.store_id = {$store_id}
            //                AND b.create_time IS NOT NULL
            //                AND b.onhand_qty > 0
            //                AND country.iso_code_3='{$countryCode}'
            //                AND (dm.product_display IS NULL OR dm.product_display=1)
            //                AND NOT EXISTS (
            //                    SELECT dmg.id FROM oc_delicacy_management_group AS dmg
            //                    JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
            //                    JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
            //                    WHERE
            //                        dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
            //                        AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
            //                        AND dmg.status=1 and bgl.status=1 and pgl.status=1
            //                )
            //            GROUP BY p.product_id
            //            HAVING SUM(b.onhand_qty) IS NOT NULL
            //            ORDER BY MAX(pExt.receive_date) DESC, p.downloaded DESC, p.product_id DESC
            //            LIMIT 0,600
            //
            //        ) AS t
            //        GROUP BY t.p_associate
            //        ORDER BY t.create_time DESC, t.downloaded DESC, t.product_id DESC
            //        ";
            //    $query = $this->db->query($sql_complex);
            //    $results_complex = $query->rows;
            //    $count_complex = $query->num_rows;
            //
            //
            //    //非复杂交易的产品
            //    $sql_common = "
            //        SELECT *
            //        FROM (
            //
            //            SELECT
            //                p.product_id
            //                ,p.date_added
            //                ,p.downloaded
            //                ,MAX(pExt.receive_date) AS create_time
            //                ,c2p.customer_id
            //                ,'1' AS 'common'
            //                ,IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
            //            FROM oc_product AS p
            //            LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
            //            LEFT JOIN oc_product_exts AS pExt ON pExt.product_id=p.product_id
            //            LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
            //            LEFT JOIN tb_sys_batch b ON b.product_id=p.product_id
            //            LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
            //            LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
            //            LEFT JOIN oc_country AS country ON country.country_id=c.country_id
            //            LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
            //            LEFT JOIN oc_product_associate AS opa ON opa.product_id=p.product_id
            //            LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
            //            WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0
            //                AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
            //                AND p.product_id NOT IN ({$product_str})
            //                AND p.product_type=0
            //                AND p.image IS NOT NULL AND p.image!=''
            //                AND c.`status`=1 {$condition_customer_group}
            //                AND c2c.`show`=1
            //                AND p2s.store_id = {$store_id}
            //                AND b.create_time IS NOT NULL
            //                AND b.onhand_qty > 0
            //                AND country.iso_code_3='{$countryCode}'
            //                AND (dm.product_display IS NULL OR dm.product_display=1)
            //                AND NOT EXISTS (
            //                    SELECT dmg.id FROM oc_delicacy_management_group AS dmg
            //                    JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
            //                    JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
            //                    WHERE
            //                        dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
            //                        AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
            //                        AND dmg.status=1 and bgl.status=1 and pgl.status=1
            //                )
            //            GROUP BY p.product_id
            //            HAVING SUM(b.onhand_qty) IS NOT NULL
            //            ORDER BY MAX(pExt.receive_date) DESC, p.downloaded DESC, p.product_id DESC
            //            LIMIT 0,600
            //
            //        ) AS t
            //        GROUP BY t.p_associate
            //        ORDER BY t.create_time DESC, t.downloaded DESC, t.product_id DESC
            //        ";
            //    $query = $this->db->query($sql_common);
            //    $results_common = $query->rows;
            //    $count_common = $query->num_rows;
            //}
        } else {
            //非Buyer登录的身份
            //45天内 复杂交易的产品
            $sql_complex = "
                SELECT *
                FROM (

                    SELECT
                        p.product_id
                        ,p.date_added
                        ,p.downloaded
                        ,MAX(pExt.receive_date) AS create_time
                        ,c2p.customer_id
                        ,'0' AS 'common'
                        ,IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
                    FROM oc_product AS p
                    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
                    LEFT JOIN oc_product_exts AS pExt ON pExt.product_id=p.product_id
                    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
                    LEFT JOIN tb_sys_batch b ON b.product_id=p.product_id
                    LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
                    LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
                    LEFT JOIN oc_country AS country ON country.country_id=c.country_id
                    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
                    LEFT JOIN oc_product_associate AS opa ON opa.product_id=p.product_id
                    WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0
                        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
                        AND p.product_id IN ({$product_str})
                        AND TIMESTAMPDIFF(DAY, pExt.receive_date, NOW()) < {$days}
                        AND TIMESTAMPDIFF(DAY, pExt.receive_date, NOW()) >= 0
                        AND p.product_type!=3
                        AND p.image IS NOT NULL AND p.image!=''
                        AND c.`status`=1 {$condition_customer_group}
                        AND c2c.`show`=1
                        AND p2s.store_id = {$store_id}
                        AND b.create_time IS NOT NULL
                        AND b.onhand_qty > 0
                        AND country.iso_code_3='{$countryCode}'
                    GROUP BY p.product_id
                    HAVING SUM(b.onhand_qty) IS NOT NULL
                    ORDER BY MAX(pExt.receive_date) DESC, p.downloaded DESC, p.product_id DESC
                    LIMIT 0,600

                ) AS t
                GROUP BY t.p_associate
                ORDER BY t.create_time DESC, t.downloaded DESC, t.product_id DESC
                ";
            $query = $this->db->query($sql_complex);
            $results_complex = $query->rows;
            $count_complex = $query->num_rows;


            //45天内 非复杂交易的产品
            $sql_common = "
                SELECT *
                FROM (

                    SELECT
                        p.product_id
                        ,p.date_added
                        ,p.downloaded
                        ,MAX(pExt.receive_date) AS create_time
                        ,c2p.customer_id
                        ,'1' AS 'common'
                        ,IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
                    FROM oc_product AS p
                    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
                    LEFT JOIN oc_product_exts AS pExt ON pExt.product_id=p.product_id
                    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
                    LEFT JOIN tb_sys_batch b ON b.product_id=p.product_id
                    LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
                    LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
                    LEFT JOIN oc_country AS country ON country.country_id=c.country_id
                    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
                    LEFT JOIN oc_product_associate AS opa ON opa.product_id=p.product_id
                    WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0
                        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
                        AND p.product_id NOT IN ({$product_str})
                        AND TIMESTAMPDIFF(DAY, pExt.receive_date, NOW()) < {$days}
                        AND TIMESTAMPDIFF(DAY, pExt.receive_date, NOW()) >= 0
                        AND p.product_type=0
                        AND p.image IS NOT NULL AND p.image!=''
                        AND c.`status`=1 {$condition_customer_group}
                        AND c2c.`show`=1
                        AND p2s.store_id = {$store_id}
                        AND b.create_time IS NOT NULL
                        AND b.onhand_qty > 0
                        AND country.iso_code_3='{$countryCode}'
                    GROUP BY p.product_id
                    HAVING SUM(b.onhand_qty) IS NOT NULL
                    ORDER BY MAX(pExt.receive_date) DESC, p.downloaded DESC, p.product_id DESC
                    LIMIT 0,600

                ) AS t
                GROUP BY t.p_associate
                ORDER BY t.create_time DESC, t.downloaded DESC, t.product_id DESC
                ";
            $query = $this->db->query($sql_common);
            $results_common = $query->rows;
            $count_common = $query->num_rows;

            $this->session->set('column_newarrival_has_45day', 1);
            //if ($count_complex + $count_common < 6) {//取掉45天的限制
            //    $this->session->set('column_newarrival_has_45day', 0);
            //    //复杂交易的产品
            //    $sql_complex = "
            //        SELECT *
            //        FROM (
            //
            //            SELECT
            //                p.product_id
            //                ,p.date_added
            //                ,p.downloaded
            //                ,MAX(pExt.receive_date) AS create_time
            //                ,c2p.customer_id
            //                ,'0' AS 'common'
            //                ,IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
            //            FROM oc_product AS p
            //            LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
            //            LEFT JOIN oc_product_exts AS pExt ON pExt.product_id=p.product_id
            //            LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
            //            LEFT JOIN tb_sys_batch b ON b.product_id=p.product_id
            //            LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
            //            LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
            //            LEFT JOIN oc_country AS country ON country.country_id=c.country_id
            //            LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
            //            LEFT JOIN oc_product_associate AS opa ON opa.product_id=p.product_id
            //            WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0
            //                AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
            //                AND p.product_id IN ({$product_str})
            //                AND p.product_type !=3
            //                AND p.image IS NOT NULL AND p.image!=''
            //                AND c.`status`=1 {$condition_customer_group}
            //                AND c2c.`show`=1
            //                AND p2s.store_id = {$store_id}
            //                AND b.create_time IS NOT NULL
            //                AND b.onhand_qty > 0
            //                AND country.iso_code_3='{$countryCode}'
            //            GROUP BY p.product_id
            //            HAVING SUM(b.onhand_qty) IS NOT NULL
            //            ORDER BY MAX(pExt.receive_date) DESC, p.downloaded DESC, p.product_id DESC
            //            LIMIT 0,600
            //
            //        ) AS t
            //        GROUP BY t.p_associate
            //        ORDER BY t.create_time DESC, t.downloaded DESC, t.product_id DESC
            //        ";
            //    $query = $this->db->query($sql_complex);
            //    $results_complex = $query->rows;
            //    $count_complex = $query->num_rows;
            //
            //
            //    //非复杂交易的产品
            //    $sql_common = "
            //        SELECT *
            //        FROM (
            //
            //            SELECT
            //                p.product_id
            //                ,p.date_added
            //                ,p.downloaded
            //                ,MAX(pExt.receive_date) AS create_time
            //                ,c2p.customer_id
            //                ,'1' AS 'common'
            //                ,IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
            //            FROM oc_product AS p
            //            LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
            //            LEFT JOIN oc_product_exts AS pExt ON pExt.product_id=p.product_id
            //            LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
            //            LEFT JOIN tb_sys_batch b ON b.product_id=p.product_id
            //            LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
            //            LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
            //            LEFT JOIN oc_country AS country ON country.country_id=c.country_id
            //            LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
            //            LEFT JOIN oc_product_associate AS opa ON opa.product_id=p.product_id
            //            WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0
            //                AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
            //                AND p.product_id NOT IN ({$product_str})
            //                AND p.product_type=0
            //                AND p.image IS NOT NULL AND p.image!=''
            //                AND c.`status`=1 {$condition_customer_group}
            //                AND c2c.`show`=1
            //                AND p2s.store_id = {$store_id}
            //                AND b.create_time IS NOT NULL
            //                AND b.onhand_qty > 0
            //                AND country.iso_code_3='{$countryCode}'
            //            GROUP BY p.product_id
            //            HAVING SUM(b.onhand_qty) IS NOT NULL
            //            ORDER BY MAX(pExt.receive_date) DESC, p.downloaded DESC, p.product_id DESC
            //            LIMIT 0,600
            //
            //        ) AS t
            //        GROUP BY t.p_associate
            //        ORDER BY t.create_time DESC, t.downloaded DESC, t.product_id DESC
            //        ";
            //    $query = $this->db->query($sql_common);
            //    $results_common = $query->rows;
            //    $count_common = $query->num_rows;
            //}
        }
        $results = array_merge($results_complex, $results_common);


        //region 首页 New Arrival频道显示6个产品；
        $tmp_complex = [];//复杂交易的产品
        $tmp_common = [];//非复杂交易的产品
        unset($value);
        foreach ($results as $key => $value) {
            $kcustomer = 'common_' . strval($value['customer_id']);//先取Seller不同的非复杂交易产品
            if ($value['common'] == 1) {//非复杂交易的产品
                if (!array_key_exists($kcustomer, $tmp_common)) {
                    if (count($tmp_common) < 2) {
                        $tmp_common[$kcustomer] = $value;
                    }
                }
            }
        }
        unset($value);

        if (count($tmp_common) < 2) {
            $tmp_common = [];//非复杂交易的产品
            unset($value);
            foreach ($results as $key => $value) {
                $kcustomer = 'common_' . strval($value['customer_id']) . '_' . strval($value['product_id']);//取允许Seller相同的非复杂交易产品
                if ($value['common'] == 1) {//非复杂交易的产品
                    if (!array_key_exists($kcustomer, $tmp_common)) {
                        if (count($tmp_common) < 2) {
                            $tmp_common[$kcustomer] = $value;
                        }
                    }
                }
            }
            unset($value);
        }

        $remnant = $row_limit - count($tmp_common);//剩余的X个位置，取复杂交易产品
        $tmp_complex = [];//复杂交易的产品
        unset($value);
        foreach ($results as $key => $value) {
            $kcustomer = 'complex_' . strval($value['customer_id']);//先取Seller不同的复杂交易产品
            if ($value['common'] == 0) {//复杂交易的产品
                if (!array_key_exists($kcustomer, $tmp_complex)) {
                    if (count($tmp_complex) < $remnant) {
                        $tmp_complex[$kcustomer] = $value;
                    }
                }
            }
        }
        unset($value);

        if (count($tmp_complex) < $remnant) {
            $tmp_complex = [];//复杂交易的产品
            unset($value);
            foreach ($results as $key => $value) {
                $kcustomer = 'complex_' . strval($value['customer_id']) . '_' . strval($value['product_id']);//取允许Seller相同的复杂交易产品
                if ($value['common'] == 0) {//复杂交易的产品
                    if (!array_key_exists($kcustomer, $tmp_complex)) {
                        if (count($tmp_complex) < $remnant) {
                            $tmp_complex[$kcustomer] = $value;
                        }
                    }
                }
            }
            unset($value);
        }

        //如果复杂交易的产品不足X个，则 增加非复杂交易产品的数量(按照先取不同Seller、再按照允许有相同seller)，将New Arrival频道凑足6个产品；
        if (count($tmp_complex) < $remnant) {
            $tmp_common = [];//非复杂交易的产品
            $remnant_common = $row_limit - count($tmp_complex);
            unset($value);
            foreach ($results as $key => $value) {
                $kcustomer = 'common_' . strval($value['customer_id']);//取Seller不同的非复杂交易产品
                if ($value['common'] == 1) {//非复杂交易的产品
                    if (!array_key_exists($kcustomer, $tmp_common)) {
                        if (count($tmp_common) < $remnant_common) {
                            $tmp_common[$kcustomer] = $value;
                        }
                    }
                }
            }
            unset($value);

            if (count($tmp_common) < $remnant_common) {
                $tmp_common = [];//非复杂交易的产品
                unset($value);
                foreach ($results as $key => $value) {
                    $kcustomer = 'common_' . strval($value['customer_id']) . '_' . strval($value['product_id']);//取允许Seller相同的非复杂交易产品
                    if ($value['common'] == 1) {//非复杂交易的产品
                        if (!array_key_exists($kcustomer, $tmp_common)) {
                            if (count($tmp_common) < $remnant_common) {
                                $tmp_common[$kcustomer] = $value;
                            }
                        }
                    }
                }
                unset($value);
            }
        }
        //endregion


        //合并结果、排序
        $results_product = array_merge($tmp_complex, $tmp_common);


        if ($results_product) {
            foreach ($results_product as $key => $row) {
                $create_time[$key] = $row['create_time'];
                $downloaded[$key] = $row['downloaded'];
                $product_id[$key] = $row['product_id'];
            }
            array_multisort($create_time, SORT_DESC, $downloaded, SORT_DESC, $product_id, SORT_DESC, $results_product);
        }

        return $results_product;
    }

    public function newArrivalsChannelData($categoryId): array
    {

        $store_id = $this->config->get('config_store_id');
        $buyer_id = customer()->getId();
        $isPartner =  customer()->isPartner();
        $countryCode = session()->get('country');
        $cacheKey = [__CLASS__, __FUNCTION__, $buyer_id, $categoryId,$countryCode];
        if(cache()->has($cacheKey)){
            return cache()->get($cacheKey);
        }
        load()->model('tool/sort');
        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }

        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }

        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        $condition_product = '';
        if ($categoryId > 0) {
            load()->model('catalog/category');
            $arr_ids = $this->model_catalog_category->getCategoryByParent([$categoryId]);
            $str_ids = implode(',', $arr_ids);
            $allData = collect($this->newArrivalsChannelData(0));
            $productIds = $allData->pluck('product_id')->toArray();
            if ($str_ids) {
                $condition_product .= " AND p2c.category_id IN (" . $str_ids . ")  ";
            }
            $productIdsStr = implode(',', $productIds);
            if($productIdsStr){
                $condition_product .= " AND p.product_id in (" . $productIdsStr . ")  ";
            }
        } elseif ($categoryId < 0) {
            //Others分类，需要包括 未设置分类的产品
            $key = app(ChannelRepository::class)->getChannelCategoryCacheKey(ChannelType::NEW_ARRIVALS);
            if(!cache()->has($key) || !is_array(cache($key))){
                $results = app(\App\Repositories\Product\Channel\Module\NewArrivals::class)->getNewArrivalsCategory();
                app(ChannelRepository::class)->getChannelCategory($results,ChannelType::NEW_ARRIVALS);
            }
            $categoryIdList = cache($key);
            $str_ids = implode(',', $categoryIdList);
            if ($str_ids) {
                $condition_product .= " AND (p2c.category_id IN (" . $str_ids . ")  or p2c.category_id is null) ";
            }
            $allData = collect($this->newArrivalsChannelData(0));
            $productIds = $allData->pluck('product_id')->toArray();
            $productIdsStr = implode(',', $productIds);
            if($productIdsStr){
                $condition_product .= " AND p.product_id in (" . $productIdsStr . ")  ";
            }
        }

        $days = 45;
        $condition_day = " AND TIMESTAMPDIFF(DAY, pExt.receive_date, NOW()) < {$days} AND TIMESTAMPDIFF(DAY, pExt.receive_date, NOW()) >= 0 ";//默认有45天限制
        //if ($this->session->has('column_newarrival_has_45day')) {
        //    if ($this->session->get('column_newarrival_has_45day') == 0) {
        //        //无45天限制
        //        $condition_day = '';
        //    }
        //}

        //#984 需求新增 首页&频道页配件产品， oc_product与oc_product_to_tag任意表中标记配件，均判断该产品为配件，不在首页&频道页显示
        //#1380首页&频道页排除补运费产品方式调整   首页及频道页（重点new  Arrival频道）选品时排除补运费产品；即排除  p.product_type为3
        $configValue = ChannelParamConfigValue::query()->alias('cpcv')
            ->leftJoinRelations(['channelParamConfig as cpc'])
            ->where([
               'cpc.status'=> 1,
               'cpcv.status'=> 1,
               'cpc.name'=> NewArrivals::NAME,
            ])
            ->select(['cpcv.param_name','cpcv.param_value'])
            ->get()
            ->keyBy('param_name')
            ->toArray();
        $searchWeight = $configValue[NewArrivals::PARAM_SEARCH]['param_value'] ?? 0;
        $inStockDays = $configValue[NewArrivals::PARAM_IN_STOCK]['param_value'] ?? 0;
        $recentView14Days = $configValue[NewArrivals::PARAM_VIEW_14]['param_value'] ?? 0;
        if ($buyer_id > 0 && $isPartner == 0) {
            $sql = "select ";
            $sql .= "p.product_id
                    ,p.date_added
                    ,p.downloaded
                    ,MAX(pExt.receive_date) AS create_time
                    ,c2p.customer_id
                    ,IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
                    ,(IFNULL(pwc.custom_weight * {$searchWeight},0) + IFNULL((1- (DATEDIFF(NOW(),MAX(pExt.receive_date)))/45) * {$inStockDays},0) + if(pc.download_14=0,IFNULL(1 *{$recentView14Days},0) ,IFNULL(1/pc.download_14 * {$recentView14Days},0))) as rate
                    FROM oc_product AS p
                    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
                     LEFT JOIN oc_product_crontab AS pc ON pc.product_id=p.product_id
                    LEFT JOIN oc_product_exts AS pExt ON pExt.product_id=p.product_id
                    LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
                    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
                    LEFT JOIN tb_sys_batch b ON b.product_id=p.product_id
                    LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
                    LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
                    LEFT JOIN oc_country AS country ON country.country_id=c.country_id
                    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
                    LEFT JOIN oc_product_associate AS opa ON opa.product_id=p.product_id
                    LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
                    LEFT JOIN tb_product_weight_config AS pwc ON pwc.product_id=p.product_id
                    WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0
                        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
                        {$condition_day}
                        AND p.product_type=0
                        {$condition_product}
                        AND p.image IS NOT NULL AND p.image!=''
                        AND c.`status`=1 {$condition_customer_group}
                        AND c2c.`show`=1
                        AND p2s.store_id = {$store_id}
                        AND b.create_time IS NOT NULL
                        AND b.onhand_qty > 0
                        AND country.iso_code_3='{$countryCode}'
                        AND (dm.product_display IS NULL OR dm.product_display=1)
                        AND NOT EXISTS (
                            SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                            JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                            JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                            WHERE
                                dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                                AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                                AND dmg.status=1 and bgl.status=1 and pgl.status=1
                        )
                    GROUP BY p.product_id
                    HAVING SUM(b.onhand_qty) IS NOT NULL
                    ORDER BY rate DESC";
        } else {
            //非Buyer登录
            $sql =   "SELECT ";
            $sql .=  "p.product_id
                        ,p.date_added
                        ,p.downloaded
                        ,MAX(pExt.receive_date) AS create_time
                        ,c2p.customer_id
                        ,IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
                        ,(IFNULL(pwc.custom_weight * {$searchWeight},0) + IFNULL((1- (DATEDIFF(NOW(),MAX(pExt.receive_date)))/45) * {$inStockDays},0) + if(pc.download_14=0,IFNULL(1 *{$recentView14Days},0) ,IFNULL(1/pc.download_14 * {$recentView14Days},0))) as rate
                    FROM oc_product AS p
                    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
                     LEFT JOIN oc_product_crontab AS pc ON pc.product_id=p.product_id
                    LEFT JOIN oc_product_exts AS pExt ON pExt.product_id=p.product_id
                    LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
                    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
                    LEFT JOIN tb_sys_batch b ON b.product_id=p.product_id
                    LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
                    LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
                    LEFT JOIN oc_country AS country ON country.country_id=c.country_id
                    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
                    LEFT JOIN oc_product_associate AS opa ON opa.product_id=p.product_id
                    LEFT JOIN tb_product_weight_config AS pwc ON pwc.product_id=p.product_id
                    WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.part_flag=0 AND p.quantity>0
                        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
                        {$condition_day}
                        AND p.product_type=0
                        {$condition_product}
                        AND p.image IS NOT NULL AND p.image!=''
                        AND c.`status`=1 {$condition_customer_group}
                        AND c2c.`show`=1
                        AND p2s.store_id = {$store_id}
                        AND b.create_time IS NOT NULL
                        AND b.onhand_qty > 0
                        AND country.iso_code_3='{$countryCode}'
                    GROUP BY p.product_id
                    HAVING SUM(b.onhand_qty) IS NOT NULL
                    ORDER BY rate DESC";
        }

        $list = db(new Expression('(' . $sql . ') as t'))
            ->select('t.customer_id','t.product_id','t.create_time')
            ->groupBy(['t.p_associate'])
            ->orderBy('rate', 'desc')
            ->orderBy('product_id', 'desc')
            ->get();
        if($list->isEmpty()){
            return [];
        }
        $ret = [];
        $retStores = [];
        // 同当前取值逻辑；每个店铺展示的产品不超过20个，即如有21个新品，仅取非同款的前20个
        foreach($list as $item){
            if(isset($retStores[$item->customer_id]) && count($retStores[$item->customer_id]) >= 20){
                continue;
            }else{
                $retStores[$item->customer_id][] = $item->product_id;
                $ret[] = $item;
            }
        }
        cache()->set($cacheKey,$ret,10);
        return $ret;
    }


    /**
     * 新品到货
     * @param string $countryCode
     * @param int $category_id 0
     * @param int $row_offset 0
     * @param int $row_limit 16
     * @return array
     * @throws Exception
     */
    public function newArrivalsColumn($countryCode, $category_id = 0, $row_offset = 0, $row_limit = 16)
    {
        $this->newArrivalsHome($countryCode);//如果直接从首页导航栏进入该频道页，则先调用newArrivalsHome()，为了知道保留/去除[45天内新增的产品]的限制。这样处理，我很无奈
        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();
        $this->load->model('tool/sort');

        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }

        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }

        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        $condition_product = '';
        if ($category_id > 0) {
            $this->load->model('catalog/category');
            $arr_ids = $this->model_catalog_category->getCategoryByParent([$category_id]);
            $str_ids = implode(',', $arr_ids);
            if ($str_ids) {
                $condition_product = " AND p2c.category_id IN (" . $str_ids . ")  ";
            }
        } elseif ($category_id < 0) {
            //Others分类，需要包括 未设置分类的产品
            $condition_product = " AND p2c.category_id IS NULL ";
        }

        //复杂交易的产品
        $product_list = $this->model_tool_sort->getComplexTransactionsProductIdByCountry($this->country_map[$countryCode]);

        $days = 45;
        $condition_day = " AND TIMESTAMPDIFF(DAY, pExt.receive_date, NOW()) < {$days} AND TIMESTAMPDIFF(DAY, pExt.receive_date, NOW()) >= 0 ";//默认有45天限制
        if ($this->session->has('column_newarrival_has_45day')) {
            if ($this->session->get('column_newarrival_has_45day') == 0) {
                //无45天限制
                $condition_day = '';
            }
        }

        //#984 需求新增 首页&频道页配件产品， oc_product与oc_product_to_tag任意表中标记配件，均判断该产品为配件，不在首页&频道页显示
        //#1380首页&频道页排除补运费产品方式调整   首页及频道页（重点new  Arrival频道）选品时排除补运费产品；即排除  p.product_type为3
        if ($buyer_id > 0 && $isPartner == 0) {
            $sql = "
                SELECT *
                FROM (

                    SELECT
                        p.product_id
                        ,p.date_added
                        ,p.downloaded
                        ,MAX(pExt.receive_date) AS create_time
                        ,c2p.customer_id
                        ,IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
                    FROM oc_product AS p
                    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
                    LEFT JOIN oc_product_exts AS pExt ON pExt.product_id=p.product_id
                    LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
                    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
                    LEFT JOIN tb_sys_batch b ON b.product_id=p.product_id
                    LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
                    LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
                    LEFT JOIN oc_country AS country ON country.country_id=c.country_id
                    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
                    LEFT JOIN oc_product_associate AS opa ON opa.product_id=p.product_id
                    LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
                    WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0
                        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
                        {$condition_day}
                        AND p.product_type=0
                        {$condition_product}
                        AND p.image IS NOT NULL AND p.image!=''
                        AND c.`status`=1 {$condition_customer_group}
                        AND c2c.`show`=1
                        AND p2s.store_id = {$store_id}
                        AND b.create_time IS NOT NULL
                        AND b.onhand_qty > 0
                        AND country.iso_code_3='{$countryCode}'
                        AND (dm.product_display IS NULL OR dm.product_display=1)
                        AND NOT EXISTS (
                            SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                            JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                            JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                            WHERE
                                dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                                AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                                AND dmg.status=1 and bgl.status=1 and pgl.status=1
                        )
                    GROUP BY p.product_id
                    HAVING SUM(b.onhand_qty) IS NOT NULL
                    ORDER BY MAX(pExt.receive_date) DESC, p.downloaded DESC, p.product_id DESC

                ) AS t
                GROUP BY t.p_associate
                ORDER BY t.create_time DESC, t.downloaded DESC, t.product_id DESC
                LIMIT " . ($row_offset - 1) * $row_limit . ", {$row_limit}";
        } else {//非Buyer登录
            $sql = "
                SELECT *
                FROM (

                    SELECT
                        p.product_id
                        ,p.date_added
                        ,p.downloaded
                        ,MAX(pExt.receive_date) AS create_time
                        ,c2p.customer_id
                        ,IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
                    FROM oc_product AS p
                    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
                    LEFT JOIN oc_product_exts AS pExt ON pExt.product_id=p.product_id
                    LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
                    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
                    LEFT JOIN tb_sys_batch b ON b.product_id=p.product_id
                    LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
                    LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
                    LEFT JOIN oc_country AS country ON country.country_id=c.country_id
                    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
                    LEFT JOIN oc_product_associate AS opa ON opa.product_id=p.product_id
                    WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.part_flag=0 AND p.quantity>0
                        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
                        {$condition_day}
                        AND p.product_type=0
                        {$condition_product}
                        AND p.image IS NOT NULL AND p.image!=''
                        AND c.`status`=1 {$condition_customer_group}
                        AND c2c.`show`=1
                        AND p2s.store_id = {$store_id}
                        AND b.create_time IS NOT NULL
                        AND b.onhand_qty > 0
                        AND country.iso_code_3='{$countryCode}'
                    GROUP BY p.product_id
                    HAVING SUM(b.onhand_qty) IS NOT NULL
                    ORDER BY MAX(pExt.receive_date) DESC, p.downloaded DESC, p.product_id DESC

                ) AS t
                GROUP BY t.p_associate
                ORDER BY t.create_time DESC, t.downloaded DESC, t.product_id DESC
                LIMIT " . ($row_offset - 1) * $row_limit . ", {$row_limit}";
        }


        $query = $this->db->query($sql);
        $results_product = $query->rows;


        if ($results_product) {
            foreach ($results_product as $key => $row) {
                $create_time[$key] = $row['create_time'];
                $downloaded[$key] = $row['downloaded'];
                $product_id[$key] = $row['product_id'];
            }
            array_multisort($create_time, SORT_DESC, $downloaded, SORT_DESC, $product_id, SORT_DESC, $results_product);
        }


        return $results_product;
    }


    /**
     * @param string $countryCode
     * @return array
     * @throws Exception
     */
    public function newArrivalsCategory($countryCode)
    {
        $this->newArrivalsHome($countryCode);//如果直接从首页导航栏进入该频道页，则先调用newArrivalsHome()，为了知道保留/去除[45天内新增的产品]的限制。这样处理，我很无奈
        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();
        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }

        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }

        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        $condition_product = '';

        $days = 45;
        $condition_day = " AND TIMESTAMPDIFF(DAY, pExt.receive_date, NOW()) < {$days} AND TIMESTAMPDIFF(DAY, pExt.receive_date, NOW()) >= 0 ";//默认有45天限制
        //if ($this->session->has('column_newarrival_has_45day')) {
        //    if ($this->session->get('column_newarrival_has_45day') == 0) {
        //        //无45天限制
        //        $condition_day = '';
        //    }
        //}


        if ($buyer_id > 0 && $isPartner == 0) {

            $sql = "
                SELECT t.category_id, t.name
                FROM (

                    SELECT
                        DISTINCT p.product_id
                        ,p.date_added
                        ,p.downloaded
                        ,MAX(pExt.receive_date) AS create_time
                        ,c2p.customer_id
                        ,IFNULL(category.category_id, -1) AS category_id
                        ,IFNULL(cd.name, 'Others') AS name
                        ,category.sort_order
                        ,IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
                    FROM oc_product AS p
                    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
                    LEFT JOIN oc_product_exts AS pExt ON pExt.product_id=p.product_id
                    LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
                    LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id
                    LEFT JOIN oc_category AS category ON category.category_id=cp.path_id
                    LEFT JOIN oc_category_description AS cd ON cd.category_id=category.category_id
                    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
                    LEFT JOIN tb_sys_batch b ON b.product_id=p.product_id
                    LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
                    LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
                    LEFT JOIN oc_country AS country ON country.country_id=c.country_id
                    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
                    LEFT JOIN oc_product_associate AS opa ON opa.product_id=p.product_id
                    LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
                    WHERE
                        (category.parent_id=0 OR category.parent_id IS NULL)
                        AND (cp.level= 0 OR cp.level IS NULL)
                        AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
                        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
                        AND p.quantity>0
                        {$condition_day}
                        AND p.product_type=0
                        {$condition_product}
                        AND p.image IS NOT NULL AND p.image!=''
                        AND c.`status`=1 {$condition_customer_group}
                        AND c2c.`show`=1
                        AND p2s.store_id = {$store_id}
                        AND b.create_time IS NOT NULL
                        AND b.onhand_qty > 0
                        AND country.iso_code_3='{$countryCode}'
                        AND (dm.product_display IS NULL OR dm.product_display=1)
                        AND NOT EXISTS (
                            SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                            JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                            JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                            WHERE
                                dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                                AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                                AND dmg.status=1 and bgl.status=1 and pgl.status=1
                        )
                    GROUP BY p.product_id
                    HAVING SUM(b.onhand_qty) IS NOT NULL
                    ORDER BY MAX(pExt.receive_date) DESC, p.downloaded DESC, p.product_id DESC

                ) AS t
                GROUP BY t.category_id
                ORDER BY t.sort_order ,LCASE(t.`name`)
                ";
        } else {
            $sql = "
                SELECT t.category_id, t.name
                FROM (

                    SELECT
                        DISTINCT p.product_id
                        ,p.date_added
                        ,p.downloaded
                        ,MAX(pExt.receive_date) AS create_time
                        ,IFNULL(category.category_id, -1) AS category_id
                        ,c2p.customer_id
                        ,IFNULL(cd.name, 'Others') AS name
                        ,category.sort_order
                        ,IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
                    FROM oc_product AS p
                    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
                    LEFT JOIN oc_product_exts AS pExt ON pExt.product_id=p.product_id
                    LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
                    LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id
                    LEFT JOIN oc_category AS category ON category.category_id=cp.path_id
                    LEFT JOIN oc_category_description AS cd ON cd.category_id=category.category_id
                    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
                    LEFT JOIN tb_sys_batch b ON b.product_id=p.product_id
                    LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
                    LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
                    LEFT JOIN oc_country AS country ON country.country_id=c.country_id
                    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
                    LEFT JOIN oc_product_associate AS opa ON opa.product_id=p.product_id
                    WHERE
                        (category.parent_id=0 OR category.parent_id IS NULL)
                        AND (cp.level= 0 OR cp.level IS NULL)
                        AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
                        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
                        AND p.quantity>0
                        {$condition_day}
                        AND p.product_type=0
                        {$condition_product}
                        AND p.image IS NOT NULL AND p.image!=''
                        AND c.`status`=1 {$condition_customer_group}
                        AND c2c.`show`=1
                        AND p2s.store_id = {$store_id}
                        AND b.create_time IS NOT NULL
                        AND b.onhand_qty > 0
                        AND country.iso_code_3='{$countryCode}'
                    GROUP BY p.product_id
                    HAVING SUM(b.onhand_qty) IS NOT NULL
                    ORDER BY MAX(pExt.receive_date) DESC, p.downloaded DESC, p.product_id DESC

                ) t
                GROUP BY t.category_id
                ORDER BY t.sort_order ,LCASE(t.`name`)";

        }

        $query = $this->db->query($sql);
        return $query->rows;
    }


    /**
     * [bestSellersSort description]
     * @param string $country
     * @return array
     * @throws Exception
     */
    public function bestSellersSort($country)
    {
        //1.先将同款不同色产品去重，留一个总销量最高的产品代表这一系列产品
        //2.将剩余产品按照是否参与复杂交易分类
        //3.参与复杂交易的产品，选出总销量最高的前9，未参与复杂交易的产品选出总销量最高的前3
        //4.将这12个产品按照总销量倒序排列展示在频道中
        // 1.参与复杂交易的 , 总销量 ， 同款

        $this->load->model('tool/sort');


        $product_list = $this->model_tool_sort->getComplexTransactionsProductIdByCountry($this->country_map[$country]);//复杂交易的产品

        $common_product_id = $this->getBestSellersByQuantity($product_list);
        $product_str = implode(',', $product_list);
        $complexTransactionsProductList = $this->getComplexTransactionsProductIdByAllSales($product_str, $country);
        $commonProductList = $this->getCommonProductIdByAllSales($common_product_id, $country);
        $result = array_merge($complexTransactionsProductList, $commonProductList);
        $quantity_sort = array_column($result, 'quantity');
        array_multisort($quantity_sort, SORT_DESC, $result);
        return array_column($result, 'product_id');

    }


    /**
     * @param string $country 国家代码
     * @param int $row_offset
     * @param int $row_limit
     * @return array
     */
    public function bestSellerHome($country, $row_offset, $row_limit)
    {
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        if ($this->customer_id) {
            $sql = "
                SELECT *
                FROM (

                    SELECT
                        p.product_id
                        ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
                        ,pc.order_money
                    FROM oc_product AS p
                    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
                    LEFT JOIN oc_product_crontab AS pc ON pc.product_id=p.product_id
                    LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
                    LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `p`.`product_id`
                    LEFT JOIN `oc_customer` AS `c` ON `c`.`customer_id` = `ctp`.`customer_id`
                    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
                    LEFT JOIN oc_country cou ON cou.country_id = c.country_id
                    LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id=" . $this->customer_id . "
                    WHERE
                        p.`status` = 1
                        AND p.buyer_flag = 1 AND p.is_deleted=0
                        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
                        AND p.product_type=0
                        AND p.quantity > 0
                        AND p.image IS NOT NULL AND p.image!=''
                        AND c.`status` = 1  {$condition_customer_group}
                        AND c2c.`show`=1
                        AND cou.`iso_code_3` = '{$country}'
                        AND pc.order_money>0
                        AND (dm.product_display = 1 or dm.product_display is null)
                        AND NOT EXISTS (
                            SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                            JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                            JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                            WHERE
                                dmg.seller_id = ctp.customer_id  AND bgl.seller_id = ctp.customer_id AND pgl.seller_id = ctp.customer_id
                                AND bgl.buyer_id = " . $this->customer_id . " AND pgl.product_id = ctp.product_id
                                AND dmg.status=1 and bgl.status=1 and pgl.status=1
                        )
                    GROUP BY p.`product_id`
                    ORDER BY pc.order_money DESC
                    LIMIT 50

                ) AS t
                GROUP BY t.p_associate
                ORDER BY t.order_money DESC
                LIMIT 0,{$row_limit}";
                    } else {
                        $sql = "
                SELECT *
                FROM (

                    SELECT
                        p.product_id
                        ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
                        ,pc.order_money
                    FROM oc_product AS p
                    LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
                    LEFT JOIN oc_product_crontab AS pc ON pc.product_id=p.product_id
                    LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
                    LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `p`.`product_id`
                    LEFT JOIN `oc_customer` AS `c` ON `c`.`customer_id` = `ctp`.`customer_id`
                    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
                    LEFT JOIN oc_country cou ON cou.country_id = c.country_id
                    WHERE
                        p.`status` = 1
                        AND p.buyer_flag = 1 AND p.is_deleted=0
                        AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
                        AND p.product_type=0
                        AND p.quantity > 0
                        AND p.image IS NOT NULL AND p.image!=''
                        AND c.`status` = 1  {$condition_customer_group}
                        AND c2c.`show`=1
                        AND cou.`iso_code_3` = '{$country}'
                        AND pc.order_money>0
                    GROUP BY p.`product_id`
                    ORDER BY pc.order_money DESC
                    LIMIT 50

                ) AS t
                GROUP BY t.p_associate
                ORDER BY t.order_money DESC
                LIMIT 0,{$row_limit}";
        }

        $res = $this->db->query($sql)->rows;

        return $res;
    }


    /**
     * @param string $country 国家代码
     * @param int $category_id 0
     * @param int $row_offset 页码
     * @param int $row_limit  页面条数
     * @param int $db_limit  数据库条数
     * @return array
     */
    public function bestSellerColumn($country, $category_id = 0, $row_offset, $row_limit, $db_limit)
    {
        //从session中取产品ID，再用产品分类条件筛选
        $product_cate_arr = session('column_bestseller_product_ids');
        $res = [];
        if ($product_cate_arr) {
            if ($category_id == 0) {
                $results = [];
                unset($value);
                foreach ($product_cate_arr as $value) {
                    $results[] = ['product_id' => $value['p']];
                }
                unset($value);
                $res = array_slice($results, ($row_offset - 1) * $row_limit, $db_limit);
            } elseif ($category_id > 0) {
                $results = [];
                unset($value);
                foreach ($product_cate_arr as $value) {
                    $tmp_cate_arr = explode(',', $value['c']);
                    if (in_array($category_id, $tmp_cate_arr)) {
                        $results[] = ['product_id' => $value['p']];
                    }
                }
                unset($value);
                $res = array_slice($results, ($row_offset - 1) * $row_limit, $db_limit);
            } elseif ($category_id < 0) {
                $results = [];
                unset($value);
                foreach ($product_cate_arr as $value) {
                    if ($value['c'] < 0 || is_null($value['c'])) {
                        $results[] = ['product_id' => $value['p']];
                    }
                }
                unset($value);
                $res = array_slice($results, ($row_offset - 1) * $row_limit, $db_limit);
            }
        }


        return $res;
    }


    public function bestSellerCategory($country)
    {
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        if ($this->customer_id) {
            $sql = "
    SELECT *
    FROM (

        SELECT
            DISTINCT p.product_id
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
            ,pc.order_money
            ,IFNULL(GROUP_CONCAT( DISTINCT category.category_id ORDER BY category.category_id ), -1) AS category_id
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_product_crontab AS pc ON pc.product_id=p.product_id
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `p`.`product_id`
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id AND (cp.level= 0 OR cp.level IS NULL)
        LEFT JOIN oc_category AS category ON category.category_id=cp.path_id AND (category.parent_id=0 OR category.parent_id IS NULL)
        LEFT JOIN `oc_customer` AS `c` ON `c`.`customer_id` = `ctp`.`customer_id`
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_country cou ON cou.country_id = c.country_id
        LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id=" . $this->customer_id . "
        WHERE
            p.`status` = 1
            AND p.buyer_flag = 1
            AND p.is_deleted=0
            AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
            AND p.product_type=0
            AND p.quantity > 0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status` = 1  {$condition_customer_group}
            AND c2c.`show`=1
            AND cou.`iso_code_3` = '{$country}'
            AND pc.order_money>0
            AND (dm.product_display = 1 or dm.product_display is null)
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = ctp.customer_id  AND bgl.seller_id = ctp.customer_id AND pgl.seller_id = ctp.customer_id
                    AND bgl.buyer_id = " . $this->customer_id . " AND pgl.product_id = ctp.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        GROUP BY p.`product_id`
        ORDER BY pc.order_money DESC
        LIMIT 2000

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.order_money DESC
    LIMIT 100";
        } else {
            $sql = "
    SELECT *
    FROM (

        SELECT
            p.product_id
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
            ,pc.order_money
            ,IFNULL(GROUP_CONCAT( DISTINCT category.category_id ORDER BY category.category_id ), -1) AS category_id
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_product_crontab AS pc ON pc.product_id=p.product_id
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `p`.`product_id`
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id AND (cp.level= 0 OR cp.level IS NULL)
        LEFT JOIN oc_category AS category ON category.category_id=cp.path_id AND (category.parent_id=0 OR category.parent_id IS NULL)
        LEFT JOIN `oc_customer` AS `c` ON `c`.`customer_id` = `ctp`.`customer_id`
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_country cou ON cou.country_id = c.country_id
        WHERE
            p.`status` = 1
            AND p.buyer_flag = 1
            AND p.is_deleted=0
            AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
            AND p.product_type=0
            AND p.quantity > 0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status` = 1  {$condition_customer_group}
            AND c2c.`show`=1
            AND cou.`iso_code_3` = '{$country}'
            AND pc.order_money>0
        GROUP BY p.`product_id`
        ORDER BY pc.order_money DESC
        LIMIT 2000

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.order_money DESC
    LIMIT 100";
        }
        $results = $this->db->query($sql)->rows;


        //所有顶级分类
        $allTopCategories = [];
        $this->load->model('catalog/category');
        $getCategories = $this->model_catalog_category->getCategories(0);
        unset($value);
        foreach ($getCategories as $value) {
            $allTopCategories[$value['category_id']] = $value;
        }
        unset($value);
        unset($getCategories);


        //刚打开页面
        //前100个[产品ID=>分类ID]保存到session中
        $tmp = [];
        $res_category = [];
        $sort_sort = [];
        $sort_name = [];
        $has_others = false;
        unset($value);
        foreach ($results as $value) {
            $tmp[] = [
                'p' => $value['product_id'],//product_id
                'c' => $value['category_id']//category_id 如果属于多个分类，则分类ID用逗号相连
            ];

            if ($value['category_id'] == -1) {
                $has_others = true;
            }
            $tmp_cate_arr = explode(',', $value['category_id']);
            foreach ($tmp_cate_arr as $kc) {
                if (isset($allTopCategories[$kc]) && !array_key_exists($kc, $res_category)) {
                    $res_category[$kc] = [
                        'category_id' => $kc,
                        'name' => $allTopCategories[$kc]['name'],
                    ];
                    $sort_sort[] = $allTopCategories[$kc]['sort_order'];
                    $sort_name[] = $allTopCategories[$kc]['name'];
                }
            }
        }
        unset($value);
        if ($has_others) {
            $res_category[-1] = [
                'category_id' => -1,
                'name' => 'Others'
            ];
            $sort_sort[] = -1;
            $sort_name[] = 'Others';
        }
        session()->set('column_bestseller_product_ids', $tmp);


        if ($res_category) {
            array_multisort($sort_sort, SORT_ASC, $sort_name, SORT_ASC, $res_category);
        }

        return $res_category;
    }


    public function getBestSellersByQuantity($product_list)
    {
        //$res = $this->orm->table(DB_PREFIX.'customerpartner_to_order')
        //    ->whereNotIn('product_id',$product_list)
        //    ->whereIn('order_product_status',[5,13])
        //    ->orderBy('quantity','desc')
        //    ->groupBy('product_id')
        //    ->selectRaw('sum(quantity) as quantity,product_id')
        //    ->limit(50)->pluck('product_id');
        //$res = obj2array($res);

        $res = $this->orm->table('tb_sys_product_all_sales')
            ->whereNotIn('product_id', $product_list)
            ->orderBy('quantity', 'desc')
            ->groupBy('product_id')
            ->selectRaw('quantity,product_id')
            ->limit(count($product_list))->pluck('product_id');
        $res = obj2array($res);

        return implode(',', $res);
    }


    /**
     * [getComplexTransactionsProductIdByAllSales description]
     * @param string $product_str
     * @param string $country
     * @return array
     */
    public function getComplexTransactionsProductIdByAllSales($product_str, $country)
    {


        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        if ($this->customer_id) {
            $sql = "
    select
      p.product_id ,
      cto.quantity,
      group_concat( distinct opa.associate_product_id) as p_associate
    From oc_product as p
    LEFT JOIN `tb_sys_product_all_sales` as cto ON `cto`.`product_id` = `p`.`product_id`
    LEFT JOIN `oc_product_associate` as opa ON `opa`.`product_id` = `p`.`product_id`
    LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `p`.`product_id`
    LEFT JOIN `oc_customer` as `c` on `c`.`customer_id` = `ctp`.`customer_id`
    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
    LEFT JOIN oc_country cou ON cou.country_id = c.country_id
    LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id=" . $this->customer_id . "
    WHERE
        `p`.`product_id` IN ( " . $product_str . ")
        AND p.product_type=0
        AND `p`.`status` = 1
        AND `p`.`buyer_flag` = 1
        AND p.part_flag=0
        AND p.quantity>0
        AND p.image IS NOT NULL AND p.image!=''
        AND `c`.`status` = 1 " . $condition_customer_group . "
        AND c2c.`show`=1
        AND `cou`.`iso_code_3` = '" . $country . "'
        AND (dm.product_display = 1 or dm.product_display is null)
        AND NOT EXISTS (
            SELECT dmg.id FROM oc_delicacy_management_group AS dmg
            JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
            JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
            WHERE
                dmg.seller_id = ctp.customer_id  AND bgl.seller_id = ctp.customer_id AND pgl.seller_id = ctp.customer_id
                AND bgl.buyer_id = " . $this->customer_id . " AND pgl.product_id = ctp.product_id
                AND dmg.status=1 and bgl.status=1 and pgl.status=1
        )
    GROUP BY
        `p`.`product_id`
    Order By
         quantity  desc

               ";

        } else {
            $sql = "
    select
      p.product_id ,
      cto.quantity,
      group_concat( distinct opa.associate_product_id) as p_associate
    From oc_product as p
    LEFT JOIN `tb_sys_product_all_sales` as cto ON `cto`.`product_id` = `p`.`product_id`
    LEFT JOIN `oc_product_associate` as opa ON `opa`.`product_id` = `p`.`product_id`
    LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `p`.`product_id`
    LEFT JOIN `oc_customer` as `c` on `c`.`customer_id` = `ctp`.`customer_id`
    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
    LEFT JOIN oc_country cou ON cou.country_id = c.country_id
    WHERE
        `p`.`product_id` IN ( " . $product_str . ")
        AND p.product_type=0
        AND `p`.`status` = 1
        AND `p`.`buyer_flag` = 1
        AND p.part_flag=0
        AND p.quantity>0
        AND p.image IS NOT NULL AND p.image!=''
        AND cto.quantity>0
        AND `c`.`status` = 1 " . $condition_customer_group . "
        AND c2c.`show`=1
        AND `cou`.`iso_code_3` = '" . $country . "'
    GROUP BY
        `p`.`product_id`
    Order By
         quantity  desc

               ";

        }

        $final_sql = "
    SELECT
        product_id,
        quantity,
        p_associate

    From ($sql) as tmp
    GROUP BY
        tmp.p_associate
    ORDER BY
        `tmp`.`quantity` DESC";
        $res = $this->db->query($final_sql)->rows;
        // 查询出保证金中排行最高的，但是其中不包含去除 相同颜色最高的
        $count = 0;
        $complexTransactionsProductList = [];
        foreach ($res as $key => $value) {
            $p_associate = $value['p_associate'] ? $value['p_associate'] : $value['product_id'];
            $product_id = $this->getAllsalesMaxQuantity($p_associate);
            //代表此复杂交易的同款比他高，复杂交易数据要舍去
            if ((int)$product_id == $value['product_id']) {
                if ($count < 9) {
                    $complexTransactionsProductList[] = $value;
                    $count++;
                } else {
                    break;
                }

            }
        }
        return $complexTransactionsProductList;

    }


    /**
     * [getCommonProductIdByAllSales description] 根据
     * @param string $product_str
     * @param string $country
     * @return array
     */
    public function getCommonProductIdByAllSales($product_str, $country)
    {
        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        if ($this->customer_id) {
            $sql = "
    select
        p.product_id ,
        cto.quantity,
        IFNULL(group_concat( distinct opa.associate_product_id), p.product_id) as p_associate
    From oc_product as p
    LEFT JOIN `tb_sys_product_all_sales` as cto ON `cto`.`product_id` = `p`.`product_id`
    LEFT JOIN `oc_product_associate` as opa ON `opa`.`product_id` = `p`.`product_id`
    LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `p`.`product_id`
    LEFT JOIN `oc_customer` as `c` on `c`.`customer_id` = `ctp`.`customer_id`
    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
    LEFT JOIN oc_country cou ON cou.country_id = c.country_id
    LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id=" . $this->customer_id . "
    WHERE
        `p`.`product_id` IN ( " . $product_str . ")
        AND p.product_type=0
        AND `p`.`status` = 1
        AND `p`.`buyer_flag` = 1
        AND p.part_flag=0
        AND p.quantity > 0
        AND `c`.`status` = 1 " . $condition_customer_group . "
        AND c2c.`show`=1
        AND `cou`.`iso_code_3` = '" . $country . "'
        AND (dm.product_display = 1 or dm.product_display is null)
        AND NOT EXISTS (
            SELECT dmg.id FROM oc_delicacy_management_group AS dmg
            JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
            JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
            WHERE
                dmg.seller_id = ctp.customer_id  AND bgl.seller_id = ctp.customer_id AND pgl.seller_id = ctp.customer_id
                AND bgl.buyer_id = " . $this->customer_id . " AND pgl.product_id = ctp.product_id
                AND dmg.status=1 and bgl.status=1 and pgl.status=1
        )
    GROUP BY `p`.`product_id`
    Order By quantity  desc
    ";

        } else {
            $sql = "
    select
      p.product_id
      ,cto.quantity
      ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
    From oc_product as p
    LEFT JOIN `tb_sys_product_all_sales` as cto ON `cto`.`product_id` = `p`.`product_id`
    LEFT JOIN `oc_product_associate` as opa ON `opa`.`product_id` = `p`.`product_id`
    LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `p`.`product_id`
    LEFT JOIN `oc_customer` as `c` on `c`.`customer_id` = `ctp`.`customer_id`
    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
    LEFT JOIN oc_country cou ON cou.country_id = c.country_id
    WHERE
        `p`.`product_id` IN ( " . $product_str . ")
        AND `p`.`status` = 1
        AND `p`.`buyer_flag` = 1
        AND p.part_flag=0
        AND p.product_type=0
        AND p.quantity > 0
        AND p.image IS NOT NULL AND p.image!=''
        AND cto.quantity>0
        AND `c`.`status` = 1 " . $condition_customer_group . "
        AND c2c.`show`=1
        AND `cou`.`iso_code_3` = '" . $country . "'
    GROUP BY `p`.`product_id`
    Order By quantity  desc
                ";
        }
        $final_sql = "
    SELECT
        product_id,
        quantity,
        p_associate

    From ($sql) as tmp
    GROUP BY
        tmp.p_associate
    ORDER BY
        `tmp`.`quantity` DESC";
        $res = $this->db->query($final_sql)->rows;
        // 查询出普通中排行最高的，但是其中不包含去除 相同颜色最高的
        $count = 0;
        $commonProductList = [];
        foreach ($res as $key => $value) {
            $p_associate = $value['p_associate'] ? $value['p_associate'] : $value['product_id'];
            $product_id = $this->getAllsalesMaxQuantity($p_associate);
            //代表此复杂交易的同款比他高，复杂交易数据要舍去
            if ((int)$product_id == $value['product_id']) {
                if ($count < 3) {
                    $commonProductList[] = $value;
                    $count++;
                } else {
                    break;
                }

            }
        }
        return $commonProductList;
    }


    //库存充足 首页
    public function highStockHome($countryCode, $row_offset, $row_limit)
    {
        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();


        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        if ($buyer_id > 0 && $isPartner == 0) {
            //buyer

            $db_limit_special = ceil($row_limit / 3);
            //Special = LTL + Combo
            $sqlSpecial = "
    SELECT *
    FROM (

        SELECT
            p.product_id
            ,p.quantity
            ,p.downloaded
            ,p.date_added
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id={$buyer_id}
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND ptag.tag_id IN (1,3)
            AND p.quantity > 100
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
            AND (dm.product_display = 1 or dm.product_display is null)
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        GROUP BY p.product_id
        ORDER BY p.quantity DESC, p.downloaded DESC, p.date_added ASC
        LIMIT 40

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.quantity DESC, t.downloaded DESC, t.date_added ASC
    LIMIT 0,{$db_limit_special}";

            $query = $this->db->query($sqlSpecial);
            $productSpecial = $query->rows;
            $countSpecial = $query->num_rows;


            $db_limit_general = $row_limit - $countSpecial;
            //普通 General
            $sqlGeneral = "
    SELECT *
    FROM (

        SELECT
            p.product_id
            ,p.quantity
            ,p.downloaded
            ,p.date_added
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id={$buyer_id}
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND (ptag.tag_id =0 OR ptag.tag_id IS NULL)
            AND p.quantity > 100
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
            AND (dm.product_display IS NULL OR dm.product_display=1)
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        GROUP BY p.product_id
        ORDER BY p.quantity DESC, p.downloaded DESC, p.date_added ASC
        LIMIT 80

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.quantity DESC, t.downloaded DESC, t.date_added ASC
    LIMIT 0,{$db_limit_general}";

            $query = $this->db->query($sqlGeneral);
            $productGeneral = $query->rows;
            $countGeneral = $query->num_rows;


            $products = array_merge($productGeneral, $productSpecial);
            if ($products) {
                $quantity = array_column($products, 'quantity');
                $date_added = array_column($products, 'date_added');
                array_multisort($quantity, SORT_DESC, $date_added, SORT_ASC, $products);
            }

            return $products;

        } else {


            // 增加缓存
            $catchKey = md5($condition_customer_group . $store_id . $countryCode);
            $products = $this->cache->get($catchKey, []);
            if (!empty($products)) {
                return $products;
            }
            //游客、seller

            $db_limit_special = ceil($row_limit / 3);
            //Special = LTL + Combo
            $sqlSpecial = "
    SELECT *
    FROM (

        SELECT
            p.product_id
            ,p.quantity
            ,p.downloaded
            ,p.date_added
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND ptag.tag_id IN (1,3)
            AND p.quantity > 100
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
        GROUP BY p.product_id
        ORDER BY p.quantity DESC, p.downloaded DESC, p.date_added ASC
        LIMIT 40

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.quantity DESC, t.downloaded DESC, t.date_added ASC
    LIMIT 0,{$db_limit_special}";

            $query = $this->db->query($sqlSpecial);
            $productSpecial = $query->rows;
            $countSpecial = $query->num_rows;


            $db_limit_general = $row_limit - $countSpecial;
            //普通 General
            $sqlGeneral = "
    SELECT *
    FROM (

        SELECT
            p.product_id
            ,p.quantity
            ,p.downloaded
            ,p.date_added
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND (ptag.tag_id =0 OR ptag.tag_id IS NULL)
            AND p.quantity > 100
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
        GROUP BY p.product_id
        ORDER BY p.quantity DESC, p.downloaded DESC, p.date_added ASC
        LIMIT 80

    ) t
    GROUP BY t.p_associate
    ORDER BY t.quantity DESC, t.downloaded DESC, t.date_added ASC
    LIMIT 0,{$db_limit_general}";

            $query = $this->db->query($sqlGeneral);
            $productGeneral = $query->rows;
            $countGeneral = $query->num_rows;

            $products = array_merge($productGeneral, $productSpecial);
            if ($products) {
                $quantity = array_column($products, 'quantity');
                $date_added = array_column($products, 'date_added');
                array_multisort($quantity, SORT_DESC, $date_added, SORT_ASC, $products);
            }

            $this->cache->set($catchKey, $products, 180);

            return $products;
        }
    }


    /**
     * 库存充足 频道页 highStockColumn
     * @param string $countryCode
     * @param int $category_id
     * @param int $row_offset 页码
     * @param int $row_limit 页面条数
     * @param int $db_limit 数据库条数
     * @return array
     */
    public function highStockColumn($countryCode, $category_id = 0, $row_offset = 0, $row_limit = 16, $db_limit = 16)
    {
        //从session中取产品ID，再用产品分类条件筛选
        $product_cate_arr = session('column_highstock_product_ids');
        $res = [];
        if ($product_cate_arr) {
            if ($category_id == 0) {
                $results = [];
                unset($value);
                foreach ($product_cate_arr as $value) {
                    $results[] = ['product_id' => $value['p']];
                }
                unset($value);
                $res = array_slice($results, ($row_offset - 1) * $row_limit, $db_limit);
            } elseif ($category_id > 0) {
                $results = [];
                unset($value);
                foreach ($product_cate_arr as $value) {
                    $tmp_cate_arr = explode(',', $value['c']);
                    if (in_array($category_id, $tmp_cate_arr)) {
                        $results[] = ['product_id' => $value['p']];
                    }
                }
                unset($value);
                $res = array_slice($results, ($row_offset - 1) * $row_limit, $db_limit);
            } elseif ($category_id < 0) {
                $results = [];
                unset($value);
                foreach ($product_cate_arr as $value) {
                    if ($value['c'] < 0 || is_null($value['c'])) {
                        $results[] = ['product_id' => $value['p']];
                    }
                }
                unset($value);
                $res = array_slice($results, ($row_offset - 1) * $row_limit, $db_limit);
            }
        }


        return $res;
    }


    public function highStockCategory($countryCode)
    {
        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();


        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        if ($buyer_id > 0 && $isPartner == 0) {
            //buyer

            //$row_limit_special = ceil($page_limit / 3);
            //$db_limit_special  = ceil($db_limit / 3);
            //Special = LTL + Combo
            $sqlSpecial = "
    SELECT *
    FROM (

        SELECT
            p.product_id
            ,p.quantity
            ,p.downloaded
            ,p.date_added
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
            ,IFNULL(GROUP_CONCAT( DISTINCT category.category_id ORDER BY category.category_id ), -1) AS category_id
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id AND (cp.level= 0 OR cp.level IS NULL)
        LEFT JOIN oc_category AS category ON category.category_id=cp.path_id AND (category.parent_id=0 OR category.parent_id IS NULL)
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id={$buyer_id}
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND ptag.tag_id IN (1,3)
            AND p.quantity > 100
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
            AND (dm.product_display = 1 or dm.product_display is null)
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        GROUP BY p.product_id
        ORDER BY p.quantity DESC, p.downloaded DESC, p.date_added ASC
        LIMIT 600

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.quantity DESC, t.downloaded DESC, t.date_added ASC
    LIMIT 0,30";//1/3

            $query = $this->db->query($sqlSpecial);
            $productSpecial = $query->rows;
            $countSpecial = $query->num_rows;


            //$row_limit_general = $page_limit - $row_limit_special;
            //$db_limit_general  = $db_limit - $countSpecial;

            //普通 General
            $sqlGeneral = "
    SELECT *
    FROM (

        SELECT
            p.product_id
            ,p.quantity
            ,p.downloaded
            ,p.date_added
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
            ,IFNULL(GROUP_CONCAT( DISTINCT category.category_id ORDER BY category.category_id ), -1) AS category_id
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id AND (cp.level= 0 OR cp.level IS NULL)
        LEFT JOIN oc_category AS category ON category.category_id=cp.path_id AND (category.parent_id=0 OR category.parent_id IS NULL)
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id={$buyer_id}
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND (ptag.tag_id =0 OR ptag.tag_id IS NULL)
            AND p.quantity > 100
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
            AND (dm.product_display IS NULL OR dm.product_display=1)
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        GROUP BY p.product_id
        ORDER BY p.quantity DESC, p.downloaded DESC, p.date_added ASC
        LIMIT 2000

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.quantity DESC, t.downloaded DESC, t.date_added ASC
    LIMIT 0,70";//2/3

            $query = $this->db->query($sqlGeneral);
            $productGeneral = $query->rows;
            $countGeneral = $query->num_rows;
        } else {
            //游客、seller

            //$row_limit_special = ceil($page_limit / 3);
            //$db_limit_special  = ceil($db_limit / 3);
            //Special = LTL + Combo
            $sqlSpecial = "
    SELECT *
    FROM (

        SELECT
            p.product_id
            ,p.quantity
            ,p.downloaded
            ,p.date_added
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
            ,IFNULL(GROUP_CONCAT( DISTINCT category.category_id ORDER BY category.category_id ), -1) AS category_id
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id AND (cp.level= 0 OR cp.level IS NULL)
        LEFT JOIN oc_category AS category ON category.category_id=cp.path_id AND (category.parent_id=0 OR category.parent_id IS NULL)
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND ptag.tag_id IN (1,3)
            AND p.quantity > 100
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
        GROUP BY p.product_id
        ORDER BY p.quantity DESC, p.downloaded DESC, p.date_added ASC
        LIMIT 600

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.quantity DESC, t.downloaded DESC, t.date_added ASC
    LIMIT 0,30";//1/3

            $query = $this->db->query($sqlSpecial);
            $productSpecial = $query->rows;
            $countSpecial = $query->num_rows;


            //$row_limit_general = $row_limit - $row_limit_special;
            //$db_limit_general  = $db_limit - $countSpecial;

            //普通 General
            $sqlGeneral = "
    SELECT *
    FROM (

        SELECT
            p.product_id
            ,p.quantity
            ,p.downloaded
            ,p.date_added
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
            ,IFNULL(GROUP_CONCAT( DISTINCT category.category_id ORDER BY category.category_id ), -1) AS category_id
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id AND (cp.level= 0 OR cp.level IS NULL)
        LEFT JOIN oc_category AS category ON category.category_id=cp.path_id AND (category.parent_id=0 OR category.parent_id IS NULL)
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND (ptag.tag_id =0 OR ptag.tag_id IS NULL)
            AND p.quantity > 100
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
        GROUP BY p.product_id
        ORDER BY p.quantity DESC, p.downloaded DESC, p.date_added ASC
        LIMIT 2000

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.quantity DESC, t.downloaded DESC, t.date_added ASC
    LIMIT 0,70";//2/3

            $query = $this->db->query($sqlGeneral);
            $productGeneral = $query->rows;
            $countGeneral = $query->num_rows;
        }


        //所有顶级分类
        $allTopCategories = [];
        $this->load->model('catalog/category');
        $getCategories = $this->model_catalog_category->getCategories(0);
        unset($value);
        foreach ($getCategories as $value) {
            $allTopCategories[$value['category_id']] = $value;
        }
        unset($value);
        unset($getCategories);


        //前100个[产品ID=>分类ID]保存到session中
        $tmp = [];
        $res_category = [];
        $sort_sort = [];
        $sort_name = [];
        $has_others = false;
        $products = array_merge($productGeneral, $productSpecial);
        if ($products) {
            $quantity = [];
            $downloaded = [];
            $date_added = [];
            unset($value);
            foreach ($products as $key => $value) {
                $quantity[] = $value['quantity'];
                $downloaded[] = $value['downloaded'];
                $date_added[] = $value['date_added'];
            }
            unset($value);
            array_multisort($quantity, SORT_DESC, $downloaded, SORT_DESC, $date_added, SORT_ASC, $products);


            unset($value);
            foreach ($products as $value) {
                $tmp[] = [
                    'p' => $value['product_id'],//product_id
                    'c' => $value['category_id']//category_id 如果属于多个分类，则分类ID用逗号相连
                ];

                if ($value['category_id'] == -1) {
                    $has_others = true;
                }
                $tmp_cate_arr = explode(',', $value['category_id']);
                foreach ($tmp_cate_arr as $kc) {
                    if (isset($allTopCategories[$kc]) && !array_key_exists($kc, $res_category)) {
                        $res_category[$kc] = [
                            'category_id' => $kc,
                            'name' => $allTopCategories[$kc]['name'],
                        ];
                        $sort_sort[] = $allTopCategories[$kc]['sort_order'];
                        $sort_name[] = $allTopCategories[$kc]['name'];
                    }
                }
            }
            unset($value);
            if ($has_others) {
                $res_category[-1] = [
                    'category_id' => -1,
                    'name' => 'Others'
                ];
                $sort_sort[] = -1;
                $sort_name[] = 'Others';
            }
            session()->set('column_highstock_product_ids', $tmp);

            if ($res_category) {
                array_multisort($sort_sort, SORT_ASC, $sort_name, SORT_ASC, $res_category);
            }
        }


        return $res_category;
    }


    /** 即将断货 Out Of Stock Soon
     * @param string $countryCode
     * @param int $row_offset
     * @param int $row_limit
     * @return array
     */
    public function lowStockHome($countryCode, $row_offset, $row_limit = 12)
    {
        $limitNew = ceil($row_limit / 2);
        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();


        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        if ($buyer_id > 0 && $isPartner == 0) {
            //Special = LTL + Combo
            $sqlSpecial = "
    SELECT *
    FROM (

        SELECT
            p.product_id
            ,p.quantity
            ,p.date_added
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id={$buyer_id}
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0 AND p.quantity<5
            AND p.part_flag=0 AND ptag.tag_id !=2
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
            AND (dm.product_display = 1 or dm.product_display is null)
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        GROUP BY p.product_id
        ORDER BY p.quantity ASC, p.date_added ASC
        LIMIT 60

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.quantity ASC, t.date_added ASC
    LIMIT {$row_limit}";
            //普通 General
            $sqlGeneral = "
    SELECT *
    FROM (
        SELECT
            p.product_id
            ,p.quantity
            ,p.date_added
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
        FROM oc_product AS p
        JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id={$buyer_id}
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND ptag.tag_id !=2
            AND p.quantity>0 AND p.quantity<15
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
            AND (dm.product_display = 1 or dm.product_display is null)
            AND p.product_id NOT IN (SELECT ptag.product_id FROM oc_product_to_tag AS ptag)
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        GROUP BY p.product_id
        ORDER BY p.quantity ASC, p.date_added ASC
        LIMIT 60

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.quantity ASC, t.date_added ASC
    LIMIT {$row_limit}";
        } else {
            //Special = LTL + Combo
            $sqlSpecial = "
    SELECT *
    FROM (
        SELECT
            p.product_id
            ,p.quantity
            ,p.date_added
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.quantity>0 AND p.quantity<5
            AND p.part_flag=0 AND ptag.tag_id !=2
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
        GROUP BY p.product_id
        ORDER BY p.quantity ASC, p.date_added ASC
        LIMIT 60

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.quantity ASC, t.date_added ASC
    LIMIT {$row_limit}";
            //普通 General
            $sqlGeneral = "
    SELECT *
    FROM (

        SELECT
            p.product_id
            ,p.quantity
            ,p.date_added
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
        FROM oc_product AS p
        JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND ptag.tag_id !=2
            AND p.quantity>0 AND p.quantity<15
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
            AND p.product_id NOT IN (SELECT ptag.product_id FROM oc_product_to_tag AS ptag)
        GROUP BY p.product_id
        ORDER BY p.quantity ASC, p.date_added ASC
        LIMI 60

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.quantity ASC, t.date_added ASC
    LIMIT {$row_limit}";
        }


        $query = $this->db->query($sqlSpecial);
        $productSpecial = $query->rows;
        $countSpecial = $query->num_rows;

        $query = $this->db->query($sqlGeneral);
        $productGeneral = $query->rows;
        $countGeneral = $query->num_rows;


        $limitSpecial = $limitNew;
        $limitGeneral = $limitNew;
        if ($countSpecial <= $limitNew) {
            $limitSpecial = $countSpecial;
            $limitGeneral = $row_limit - $limitSpecial;
        } else {
            if ($countGeneral <= $limitNew) {
                $limitGeneral = $countGeneral;
            } else {
                $limitGeneral = $limitNew;
            }
            $limitSpecial = $row_limit - $limitGeneral;
        }
        $productGeneral = array_slice($productGeneral, 0, $limitGeneral);
        $productSpecial = array_slice($productSpecial, 0, $limitSpecial);


        $products = array_merge($productGeneral, $productSpecial);
        if ($products) {
            $quantity = array_column($products, 'quantity');
            $date_added = array_column($products, 'date_added');
            array_multisort($quantity, SORT_ASC, $date_added, SORT_ASC, $products);
        }
        return $products;
    }


    /**即将断货 Out Of Stock Soon
     * @param string $countryCode
     * @param int $category_id
     * @param int $row_offset
     * @param int $row_limit
     * @return array
     * @throws Exception
     */
    public function lowStockColumn($countryCode, $category_id = 0, $row_offset = 0, $row_limit = 16)
    {
        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();


        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }


        $condition = "";
        if ($category_id > 0) {
            $this->load->model('catalog/category');
            $arr_ids = $this->model_catalog_category->getCategoryByParent([$category_id]);
            $str_ids = implode(',', $arr_ids);
            if ($str_ids) {
                $condition .= " AND p2c.category_id IN (" . $str_ids . ")  ";
            }
        } elseif ($category_id < 0) {
            $condition .= " AND p2c.category_id IS NULL ";
        }


        if ($buyer_id > 0 && $isPartner == 0) {
            //$row_limit_general = ceil($row_limit / 3);
            //普通 General
            $sqlGeneral = "
    SELECT *
    FROM (

        SELECT
            p.product_id
            ,p.quantity
            ,p.date_added
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id={$buyer_id}
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND ptag.tag_id !=2
            AND p.quantity>0 AND p.quantity<15
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
            AND (dm.product_display = 1 or dm.product_display is null)
            AND p.product_id NOT IN (SELECT ptag.product_id FROM oc_product_to_tag AS ptag)
            {$condition}
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        GROUP BY p.product_id
        ORDER BY p.quantity ASC, p.date_added ASC

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.quantity ASC, t.date_added ASC
    LIMIT 0,70";//1/3


            $query = $this->db->query($sqlGeneral);
            $productGeneral = $query->rows;
            $countGeneral = $query->num_rows;


            //$row_limit_special = $row_limit - $countGeneral;
            //Special = LTL + Combo
            $sqlSpecial = "
    SELECT *
    FROM (

        SELECT
            p.product_id
            ,p.quantity
            ,p.date_added
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        LEFT JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id={$buyer_id}
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0 AND p.quantity<5
            AND p.product_type=0
            AND p.part_flag=0 AND ptag.tag_id !=2
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
            AND (dm.product_display = 1 or dm.product_display is null)
            {$condition}
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        GROUP BY p.product_id
        ORDER BY p.quantity ASC, p.date_added ASC

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.quantity ASC, t.date_added ASC
    LIMIT 0,30";//2/3


            $query = $this->db->query($sqlSpecial);
            $productSpecial = $query->rows;
            $countSpecial = $query->num_rows;
        } else {
            //$row_limit_general = ceil($row_limit / 3);
            //普通 General
            $sqlGeneral = "
    SELECT *
    FROM (

        SELECT
            p.product_id
            ,p.quantity
            ,p.date_added
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND ptag.tag_id !=2
            AND p.quantity>0 AND p.quantity<15
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
            AND p.product_id NOT IN (SELECT ptag.product_id FROM oc_product_to_tag AS ptag)
            {$condition}
        GROUP BY p.product_id
        ORDER BY p.quantity ASC, p.date_added ASC

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.quantity ASC, t.date_added ASC
    LIMIT 0,70";//1/3


            $query = $this->db->query($sqlGeneral);
            $productGeneral = $query->rows;
            $countGeneral = $query->num_rows;


            //$row_limit_special = $row_limit - $row_limit_general;
            //Special = LTL + Combo
            $sqlSpecial = "
    SELECT *
    FROM (

        SELECT
            p.product_id
            ,p.quantity
            ,p.date_added
            ,IFNULL(GROUP_CONCAT( DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        LEFT JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN `oc_product_associate` AS opa ON `opa`.`product_id` = `p`.`product_id`
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1 AND p.quantity>0 AND p.quantity<5
            AND p.product_type=0
            AND p.part_flag=0 AND ptag.tag_id !=2
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
            {$condition}
        GROUP BY p.product_id
        ORDER BY p.quantity ASC, p.date_added ASC

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.quantity ASC, t.date_added ASC
    LIMIT 0,30";//2/3


            $query = $this->db->query($sqlSpecial);
            $productSpecial = $query->rows;
            $countSpecial = $query->num_rows;
        }


        $products = array_merge($productGeneral, $productSpecial);
        if ($products) {
            $quantity = array_column($products, 'quantity');
            $date_added = array_column($products, 'date_added');
            array_multisort($quantity, SORT_ASC, $date_added, SORT_ASC, $products);
        }
        return $products;
    }


    public function lowStockCategory($countryCode)
    {
        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();


        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        if ($buyer_id > 0 && $isPartner == 0) {
            $sql = "
    SELECT t.category_id, t.name
    FROM (

        SELECT
            DISTINCT p.product_id
            ,p.quantity
            ,p.date_added
            ,IFNULL(category.category_id, -1) AS category_id
            ,IFNULL(cd.name, 'Others') AS name
            ,category.sort_order
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id
        LEFT JOIN oc_category AS category ON category.category_id=cp.path_id
        LEFT JOIN oc_category_description AS cd ON cd.category_id=category.category_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id={$buyer_id}
        WHERE
            (category.parent_id=0 OR category.parent_id IS NULL)
            AND p.product_type=0
            AND (cp.level= 0 OR cp.level IS NULL)
            AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND ptag.tag_id !=2
            AND p.quantity>0 AND p.quantity<15
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
            AND (dm.product_display = 1 or dm.product_display is null)
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        GROUP BY p.product_id

    ) AS t
    GROUP BY t.category_id
    ORDER BY t.sort_order ,LCASE(t.`name`)";
        } else {
            $sql = "
    SELECT t.category_id, t.name
    FROM (

        SELECT
            DISTINCT p.product_id
            ,p.quantity
            ,p.date_added
            ,IFNULL(category.category_id, -1) AS category_id
            ,IFNULL(cd.name, 'Others') AS name
            ,category.sort_order
        FROM oc_product AS p
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id
        LEFT JOIN oc_category AS category ON category.category_id=cp.path_id
        LEFT JOIN oc_category_description AS cd ON cd.category_id=category.category_id
        LEFT JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        WHERE
            (category.parent_id=0 OR category.parent_id IS NULL)
            AND p.product_type=0
            AND (cp.level= 0 OR cp.level IS NULL)
            AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND ptag.tag_id !=2
            AND p.quantity>0 AND p.quantity<15
            AND p.image IS NOT NULL AND p.image!=''
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
        GROUP BY p.product_id

    ) AS t
    GROUP BY t.category_id
    ORDER BY t.sort_order ,LCASE(t.`name`)";
        }


        $query = $this->db->query($sql);
        $results = $query->rows;
        return $results;
    }


    /**
     * 降价差大
     * 14天内原价降了
     * @param string $countryCode
     * @param int $row_offset
     * @param int $row_limit
     * @return array
     */
    public function priceDropHome($countryCode = '', $row_offset = 0, $row_limit = 12)
    {
        //(1)规则：14天内原价降了
        //(2)排序方式：按照 当前时刻正在生效的原价/14天前的当前时刻正在生效的原价 的值升序排列。
        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();


        if (!$countryCode) {
            return [];
        }


        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        $rate = 0;


        if ($buyer_id > 0 && $isPartner == 0) {
            $sql = "
    SELECT *
    FROM (

        SELECT
            pc.product_id
            , pc.price_change_rate
            , p.downloaded
            , IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
        FROM oc_product_crontab AS pc
        LEFT JOIN oc_product AS p ON p.product_id=pc.product_id
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        LEFT JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_product_associate  as opa ON `opa`.`product_id` = p.product_id
        LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
            AND p.quantity>0
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND pc.price_change_rate<{$rate}
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
            AND (dm.product_display IS NULL OR dm.product_display=1)
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        GROUP BY p.product_id
        ORDER BY pc.price_change_rate ASC, p.downloaded DESC
        LIMIT 0,400

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.price_change_rate ASC, t.downloaded DESC
    LIMIT 0, {$row_limit}";
        } else {
            $sql = "
    SELECT *
    FROM (

        SELECT
            pc.product_id
            , pc.price_change_rate
            , p.downloaded
            , IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
        FROM oc_product_crontab AS pc
        LEFT JOIN oc_product AS p ON p.product_id=pc.product_id
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        LEFT JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_product_associate  as opa ON `opa`.`product_id` = p.product_id
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
            AND p.quantity>0
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND pc.price_change_rate<{$rate}
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
        GROUP BY p.product_id
        ORDER BY pc.price_change_rate ASC, p.downloaded DESC
        LIMIT 0,400

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.price_change_rate ASC, t.downloaded DESC
    LIMIT 0, {$row_limit}";
        }


        $query = $this->db->query($sql);
        $num_rows = $query->num_rows;
        $results = $query->rows;


        return $results;
    }


    /**
     * 降价差大
     * 14天内原价降了
     * @param string $countryCode
     * @param int $category_id
     * @param int $row_offset 页码
     * @param int $row_limit 页面条数
     * @param int $db_limit 数据库条数
     * @return array
     * @throws Exception
     */
    public function priceDropColumn($countryCode = '', $category_id = 0, $row_offset = 0, $row_limit = 12, $db_limit)
    {
        //(1)规则：14天内原价降了
        //(2)排序方式：按照 当前时刻正在生效的原价/14天前的当前时刻正在生效的原价 的值升序排列。
        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();


        if (!$countryCode) {
            return [];
        }


        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }


        $condition = "";
        if ($category_id > 0) {
            $this->load->model('catalog/category');
            $arr_ids = $this->model_catalog_category->getCategoryByParent([$category_id]);
            $str_ids = implode(',', $arr_ids);
            if ($str_ids) {
                $condition .= " AND p2c.category_id IN (" . $str_ids . ")  ";
            }
        } elseif ($category_id < 0) {
            $condition .= " AND p2c.category_id IS NULL ";
        }
        $rate = 0;


        if ($buyer_id > 0 && $isPartner == 0) {
            $sql = "
    SELECT *
    FROM (

        SELECT
            pc.product_id
            , pc.price_change_rate
            , p.downloaded
            , IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
        FROM oc_product_crontab AS pc
        LEFT JOIN oc_product AS p ON p.product_id=pc.product_id
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        LEFT JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_product_associate  as opa ON `opa`.`product_id` = p.product_id
        LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
            AND p.quantity>0
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND pc.price_change_rate<{$rate}
            {$condition}
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
            AND (dm.product_display IS NULL OR dm.product_display=1)
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        GROUP BY p.product_id
        ORDER BY pc.price_change_rate ASC, p.downloaded DESC

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.price_change_rate ASC, t.downloaded DESC
    LIMIT " . ($row_offset - 1) * $row_limit . ", {$db_limit}";
        } else {
            $sql = "
    SELECT *
    FROM (

        SELECT
            pc.product_id
            , pc.price_change_rate
            , p.downloaded
            , IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
        FROM oc_product_crontab AS pc
        LEFT JOIN oc_product AS p ON p.product_id=pc.product_id
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        LEFT JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_product_associate  as opa ON `opa`.`product_id` = p.product_id
        WHERE p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
            AND p.quantity>0
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND pc.price_change_rate<{$rate}
            {$condition}
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
        GROUP BY p.product_id
        ORDER BY pc.price_change_rate ASC, p.downloaded DESC

    ) AS t
    GROUP BY t.p_associate
    ORDER BY t.price_change_rate ASC, t.downloaded DESC
    LIMIT " . ($row_offset - 1) * $row_limit . ", {$db_limit}";
        }


        $query = $this->db->query($sql);
        $num_rows = $query->num_rows;
        $results = $query->rows;


        return $results;
    }


    public function priceDropCategory($countryCode = '')
    {
        $store_id = (int)$this->config->get('config_store_id');
        $buyer_id = $this->customer->getId();
        $isPartner = $this->customer->isPartner();


        if (!$countryCode) {
            return [];
        }


        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        $rate = 0;


        if ($buyer_id > 0 && $isPartner == 0) {
            $sql = "
    SELECT t.category_id, t.name
    FROM (

        SELECT
            DISTINCT pc.product_id
            , p.downloaded
            , IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
            ,IFNULL(category.category_id, -1) AS category_id
            ,IFNULL(cd.name, 'Others') AS name
            ,category.sort_order
        FROM oc_product_crontab AS pc
        LEFT JOIN oc_product AS p ON p.product_id=pc.product_id
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id
        LEFT JOIN oc_category AS category ON category.category_id=cp.path_id
        LEFT JOIN oc_category_description AS cd ON cd.category_id=category.category_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        LEFT JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_product_associate  as opa ON `opa`.`product_id` = p.product_id
        LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
        WHERE
            (category.parent_id=0 OR category.parent_id IS NULL)
            AND (cp.level= 0 OR cp.level IS NULL)
            AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
            AND p.quantity>0
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND pc.price_change_rate<{$rate}
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
            AND (dm.product_display IS NULL OR dm.product_display=1)
            AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )
        GROUP BY p.product_id

    ) AS t
    GROUP BY t.category_id
    ORDER BY t.sort_order ,LCASE(t.`name`)";
        } else {
            $sql = "
    SELECT t.category_id, t.name
    FROM (

        SELECT
            DISTINCT pc.product_id
            , p.downloaded
            , IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), p.product_id) AS p_associate
            ,IFNULL(category.category_id, -1) AS category_id
            ,IFNULL(cd.name, 'Others') AS name
            ,category.sort_order
        FROM oc_product_crontab AS pc
        LEFT JOIN oc_product AS p ON p.product_id=pc.product_id
        LEFT JOIN oc_product_to_tag AS ptag ON ptag.product_id=p.product_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id
        LEFT JOIN oc_category AS category ON category.category_id=cp.path_id
        LEFT JOIN oc_category_description AS cd ON cd.category_id=category.category_id
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
        LEFT JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
        LEFT JOIN oc_country AS country ON country.country_id=c.country_id
        LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id= c.customer_id
        LEFT JOIN oc_product_associate  as opa ON `opa`.`product_id` = p.product_id
        WHERE
            (category.parent_id=0 OR category.parent_id IS NULL)
            AND (cp.level= 0 OR cp.level IS NULL)
            AND p.`status`=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND p.part_flag=0 AND (ptag.tag_id !=2 OR ptag.tag_id IS NULL)
            AND p.quantity>0
            AND p.product_type=0
            AND p.image IS NOT NULL AND p.image!=''
            AND pc.price_change_rate<{$rate}
            AND c.`status`=1 {$condition_customer_group}
            AND c2c.`show`=1
            AND p2s.store_id = {$store_id}
            AND country.iso_code_3='{$countryCode}'
        GROUP BY p.product_id

    ) AS t
    GROUP BY t.category_id
    ORDER BY t.sort_order ,LCASE(t.`name`)";
        }


        $query = $this->db->query($sql);
        $num_rows = $query->num_rows;
        $results = $query->rows;


        return $results;
    }
}
