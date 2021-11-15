<?php

use App\Components\Storage\StorageCloud;

/**
 * Class ModelMarketingCampaignActivity
 * @property ModelCustomerpartnerMarketingcampaignRequest $model_customerpartner_marketing_campaign_request
 */
class ModelMarketingCampaignActivity extends Model
{
    /**
     * 根据code 获取 活动基本信息
     * @param string $code
     * @param int $country_id 国家ID
     * @return array|\Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getValidMarketingCampaignInfoByCode($code = '')
    {
        $info = $this->orm->table('oc_marketing_campaign AS mc')
            ->leftJoin('tb_upload_file AS tup', 'tup.id', '=', 'mc.image_id')
            ->where([
                ['code', '=', $code],
            ])
            ->select('mc.id',
                'mc.code',
                'mc.name',
                'mc.type',
                'mc.country_id',
                'mc.effective_time',
                'mc.expiration_time',
                'mc.is_release',
                'mc.image_id',
                'mc.background_color',
                'tup.path AS image_path')
            ->first();

        $info = obj2array($info);
        if (!$info) {
            return [];
        }
        if (!empty($info['image_path'])) {
            $info['image_url'] = StorageCloud::root()->getUrl($info['image_path']);
        }

        return $info;
    }


    /**
     * 根据code 获取 活动banner和主打展品
     * @param string $code
     * @param int $country_id
     * @return array
     */
    public function getValidMarketingCampaignDetailByCode($code = '', $country_id = 0)
    {
        $info = $this->orm->table('oc_marketing_campaign AS mc')
            ->leftJoin('tb_upload_file AS tup', 'tup.id', '=', 'mc.image_id')
            ->where([
                ['mc.code', '=', $code],
                ['mc.country_id', '=', $country_id],
                ['mc.is_release', '=', 1]
            ])
            ->select('mc.id',
                'mc.code',
                'mc.name',
                'mc.type',
                'mc.country_id',
                'mc.effective_time',
                'mc.expiration_time',
                'mc.is_release',
                'mc.image_id',
                'tup.path AS image_path')
            ->first();

        $info = obj2array($info);
        if (!$info) {
            return [];
        }
        if (!empty($info['image_path'])) {
            $info['image_url'] = StorageCloud::root()->getUrl($info['image_path']);
        }

        $mc_id = $info['id'];


        //主打产品
        $products = $this->getMarketingCampaignProductById($mc_id);
        $products = obj2array($products);

        $info['products'] = $products;



        return $info;
    }


    //根据活动ID 获取 主打产品
    public function getMarketingCampaignProductById($id = 0)
    {
        //要过滤精细化不可见
        $store_id  = (int)$this->config->get('config_store_id');
        $buyer_id  = intval($this->customer->getId());
        $isPartner = $this->customer->isPartner();

        if ($buyer_id > 0 && $isPartner == 0) {
            $sql = "
    SELECT mcp.*
    FROM oc_marketing_campaign_product as mcp
    JOIN oc_product AS p ON p.product_id=mcp.product_id
    JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
    JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
    LEFT JOIN oc_product_to_store p2s ON p.product_id = p2s.product_id
    LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
    WHERE
        mcp.mc_id={$id}
        AND mcp.status=1
        AND p.status=1 AND p.is_deleted=0 AND p.buyer_flag=1
        AND c.status=1
        AND p2s.store_id ={$store_id}
        AND (dm.product_display IS NULL OR dm.product_display=1)
        AND NOT EXISTS (
            SELECT dmg.id FROM oc_delicacy_management_group AS dmg
            JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
            JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
            WHERE
                dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                AND bgl.buyer_id = {$buyer_id} AND pgl.product_id = c2p.product_id
                AND dmg.status=1 and bgl.status=1 and pgl.status=1
        ) ";
        } else {
            $sql = "
    SELECT mcp.*
    FROM oc_marketing_campaign_product as mcp
    JOIN oc_product AS p ON p.product_id=mcp.product_id
    JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
    JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
    LEFT JOIN oc_product_to_store p2s ON p.product_id = p2s.product_id
    WHERE
        mcp.mc_id={$id}
        AND mcp.status=1
        AND p.status=1 AND p.is_deleted=0 AND p.buyer_flag=1
        AND c.status=1
        AND p2s.store_id ={$store_id} ";
        }

        $query = $this->db->query($sql);
        return $query->rows;
    }


    /**
     * 活动产品的分类
     * @param int $id oc_marketing_campaign表 活动主键
     * @return array
     */
    public function getCategoriesById($id)
    {
        $store_id  = (int)$this->config->get('config_store_id');
        $buyer_id  = intval($this->customer->getId());
        $isPartner = $this->customer->isPartner();


        if ($buyer_id > 0 && $isPartner == 0) {
            $sql = "
    SELECT t.category_id, t.name, COUNT(t.category_id) AS cnt
    FROM (

        SELECT
            DISTINCT mcrp.product_id
            ,IFNULL(c.category_id, -1) AS category_id
            ,IFNULL(cd.name, 'Others') AS name
            ,c.sort_order
        FROM oc_marketing_campaign_request_product AS mcrp
        LEFT JOIN oc_marketing_campaign_request AS mcr ON mcr.id=mcrp.mc_request_id
        LEFT JOIN oc_product AS p ON p.product_id=mcrp.product_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id
        LEFT JOIN oc_category AS c ON c.category_id=cp.path_id
        LEFT JOIN oc_category_description AS cd ON cd.category_id=c.category_id
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=mcrp.product_id
        LEFT JOIN oc_customer AS cus ON cus.customer_id=c2p.customer_id
        LEFT JOIN oc_product_to_store AS p2s ON p2s.product_id=mcrp.product_id
        LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
        WHERE
            mcrp.mc_id={$id}
            AND mcrp.status=1
            AND mcrp.approval_status=2
            AND mcr.status=2
            AND p.status=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND (c.status=1 or c.status IS NULL)
            AND c.is_deleted=0
            AND (c.parent_id=0 OR c.parent_id IS NULL)
            AND (cp.level= 0 OR cp.level IS NULL)
            AND cus.status=1
            AND p2s.store_id={$store_id}
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
        ORDER BY c.sort_order ASC, cd.name ASC

    ) AS t
    GROUP BY t.category_id
    ORDER BY t.sort_order ASC, t.name ASC";
        } else {
            $sql = "
    SELECT t.category_id, t.name, COUNT(t.category_id) AS cnt
    FROM (

        SELECT
            DISTINCT mcrp.product_id
            ,IFNULL(c.category_id, -1) AS category_id
            ,IFNULL(cd.name, 'Others') AS name
            ,c.sort_order
        FROM oc_marketing_campaign_request_product AS mcrp
        LEFT JOIN oc_marketing_campaign_request AS mcr ON mcr.id=mcrp.mc_request_id
        LEFT JOIN oc_product AS p ON p.product_id=mcrp.product_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id
        LEFT JOIN oc_category AS c ON c.category_id=cp.path_id
        LEFT JOIN oc_category_description AS cd ON cd.category_id=c.category_id
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=mcrp.product_id
        LEFT JOIN oc_customer AS cus ON cus.customer_id=c2p.customer_id
        LEFT JOIN oc_product_to_store AS p2s ON p2s.product_id=mcrp.product_id
        WHERE
            mcrp.mc_id={$id}
            AND mcrp.status=1
            AND mcrp.approval_status=2
            AND mcr.status=2
            AND p.status=1 AND p.is_deleted=0 AND p.buyer_flag=1
                    AND (c.status=1 or c.status IS NULL)
            AND (c.parent_id=0 OR c.parent_id IS NULL)
            AND c.is_deleted=0
            AND (cp.level= 0 OR cp.level IS NULL)
            AND cus.status=1
            AND p2s.store_id={$store_id}
        ORDER BY c.sort_order ASC, cd.name ASC

    ) AS t
    GROUP BY t.category_id
    ORDER BY t.sort_order ASC, t.name ASC";
        }


        $query = $this->db->query($sql);

        return $query->rows;
    }


    /**
     * 活动产品的 Others分类
     * @param int $id oc_marketing_campaign表 活动主键
     * @return array
     */
    public function getCategoriesOthersById($id)
    {
        $store_id  = (int)$this->config->get('config_store_id');
        $buyer_id  = intval($this->customer->getId());
        $isPartner = $this->customer->isPartner();


        if ($buyer_id > 0 && $isPartner == 0) {
            $sql = "
    SELECT t.category_id, t.name, COUNT(t.category_id) AS cnt
    FROM (

        SELECT
            DISTINCT mcrp.product_id
            ,IFNULL(c.category_id, -1) AS category_id
            ,IFNULL(cd.name, 'Others') AS name
            ,c.sort_order
        FROM oc_marketing_campaign_request_product AS mcrp
        LEFT JOIN oc_marketing_campaign_request AS mcr ON mcr.id=mcrp.mc_request_id
        LEFT JOIN oc_product AS p ON p.product_id=mcrp.product_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id
        LEFT JOIN oc_category AS c ON c.category_id=cp.path_id
        LEFT JOIN oc_category_description AS cd ON cd.category_id=c.category_id
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=mcrp.product_id
        LEFT JOIN oc_customer AS cus ON cus.customer_id=c2p.customer_id
        LEFT JOIN oc_product_to_store AS p2s ON p2s.product_id=mcrp.product_id
        LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
        WHERE
            mcrp.mc_id={$id}
            AND mcrp.status=1
            AND mcrp.approval_status=2
            AND mcr.status=2
            AND p.status=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND (c.status=1 OR c.status IS NULL)
            AND c.is_deleted=0
            AND (c.parent_id=0 OR c.parent_id IS NULL)
            AND (cp.level= 0 OR cp.level IS NULL)
            AND cus.status=1
            AND p2s.store_id={$store_id}
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
        ORDER BY c.sort_order ASC, cd.name ASC

    ) AS t
    GROUP BY t.category_id
    HAVING cnt<2
    ORDER BY t.sort_order ASC, t.name ASC";
        } else {
            $sql = "
    SELECT t.category_id, t.name, COUNT(t.category_id) AS cnt
    FROM (

        SELECT
            DISTINCT mcrp.product_id
            ,IFNULL(c.category_id, -1) AS category_id
            ,IFNULL(cd.name, 'Others') AS name
            ,c.sort_order
        FROM oc_marketing_campaign_request_product AS mcrp
        LEFT JOIN oc_marketing_campaign_request AS mcr ON mcr.id=mcrp.mc_request_id
        LEFT JOIN oc_product AS p ON p.product_id=mcrp.product_id
        LEFT JOIN oc_product_to_category AS p2c ON p2c.product_id=p.product_id
        LEFT JOIN oc_category_path AS cp ON cp.category_id=p2c.category_id
        LEFT JOIN oc_category AS c ON c.category_id=cp.path_id
        LEFT JOIN oc_category_description AS cd ON cd.category_id=c.category_id
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=mcrp.product_id
        LEFT JOIN oc_customer AS cus ON cus.customer_id=c2p.customer_id
        LEFT JOIN oc_product_to_store AS p2s ON p2s.product_id=mcrp.product_id
        WHERE
            mcrp.mc_id={$id}
            AND mcrp.status=1
            AND mcrp.approval_status=2
            AND mcr.status=2
            AND p.status=1 AND p.is_deleted=0 AND p.buyer_flag=1
            AND (c.status=1 OR c.status IS NULL)
            AND c.is_deleted=0
            AND (c.parent_id=0 OR c.parent_id IS NULL)
            AND (cp.level= 0 OR cp.level IS NULL)
            AND cus.status=1
            AND p2s.store_id={$store_id}
        ORDER BY c.sort_order ASC, cd.name ASC

    ) AS t
    GROUP BY t.category_id
    HAVING cnt<2
    ORDER BY t.sort_order ASC, t.name ASC";
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }


    /**
     * 促销页 非主打产品
     * @param int $id 活动ID oc_marketing_campaign表主键
     * @param int $country_id 国家ID
     * @param int $category_id 分类
     * @param int $page 页码
     * @param int $page_limit ，每页条数
     * @return array
     * @throws Exception
     */
    public function getColumnById($id = 0, $country_id, $category_id = 0, $page, $page_limit)
    {
        if ($id < 1) {
            return [];
        }

        //要过滤精细化不可见
        $store_id   = (int)$this->config->get('config_store_id');
        $buyer_id   = intval($this->customer->getId());
        $isPartner  = $this->customer->isPartner();


        $condition = "";
        if ($category_id > 0) {
            $this->load->model('customerpartner/marketing_campaign/request');
            $arr_ids = $this->model_customerpartner_marketing_campaign_request->getCategoryByParent([$category_id]);
            $str_ids = implode(',', $arr_ids);
            if ($str_ids) {
                $condition = " AND ptc.category_id IN (" . $str_ids . ")  ";
            }
        } elseif ($category_id < 0) {
            //Others分类，需要包括 未设置分类的产品 和 某分类下数量很少的产品
            $condition = " AND ptc.category_id IS NULL ";
            $arr_others = $this->getCategoriesOthersById($id);
            if ($arr_others) {
                $arr_ids = array_column($arr_others, 'category_id');
                $this->load->model('customerpartner/marketing_campaign/request');
                $arr_ids = $this->model_customerpartner_marketing_campaign_request->getCategoryByParent($arr_ids);
                $str_ids = implode(',', $arr_ids);
                if ($str_ids) {
                    $condition = " AND (ptc.category_id IS NULL OR ptc.category_id IN ({$str_ids}))";
                }
            }
        }

        if ($buyer_id > 0 && $isPartner == 0) {
            $sql = "
    SELECT DISTINCT mcrp.product_id, p.date_modified, IFNULL(ps.quantity_all,0) AS quantity_all
    FROM oc_marketing_campaign AS mc
    JOIN oc_marketing_campaign_request AS mcr ON mcr.mc_id=mc.id
    JOIN oc_marketing_campaign_request_product AS mcrp ON mcrp.mc_request_id=mcr.id
    JOIN oc_product AS p ON p.product_id=mcrp.product_id
    JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
    JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
    LEFT JOIN oc_product_to_store p2s ON p.product_id = p2s.product_id
    LEFT JOIN oc_delicacy_management AS dm ON (dm.product_id=p.product_id AND dm.buyer_id={$buyer_id})
    LEFT JOIN oc_product_to_category AS ptc ON ptc.product_id=mcrp.product_id
    LEFT JOIN tb_sys_product_sales AS ps ON ps.product_id=mcrp.product_id
    WHERE
        mc.id={$id}
        AND mc.country_id = {$country_id}
        AND mcr.`status`=2
        AND mcrp.`status`=1
        AND mcrp.approval_status=2
        {$condition}
        AND p.status=1 AND p.is_deleted=0 AND p.buyer_flag=1
        AND c.status=1
        AND p2s.store_id ={$store_id}
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
    ORDER BY mcrp.id ASC ";
        } else {
            $sql = "
    SELECT DISTINCT mcrp.product_id, p.date_modified, IFNULL(ps.quantity_all,0) AS quantity_all
    FROM oc_marketing_campaign AS mc
    JOIN oc_marketing_campaign_request AS mcr ON mcr.mc_id=mc.id
    JOIN oc_marketing_campaign_request_product AS mcrp ON mcrp.mc_request_id=mcr.id
    JOIN oc_product AS p ON p.product_id=mcrp.product_id
    JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
    JOIN oc_customer AS c ON c.customer_id=c2p.customer_id
    LEFT JOIN oc_product_to_store p2s ON p.product_id = p2s.product_id
    LEFT JOIN oc_product_to_category AS ptc ON ptc.product_id=mcrp.product_id
    LEFT JOIN tb_sys_product_sales AS ps ON ps.product_id=mcrp.product_id
    WHERE
        mc.id={$id}
        AND mc.country_id = {$country_id}
        AND mcr.`status`=2
        AND mcrp.`status`=1
        AND mcrp.approval_status=2
        {$condition}
        AND p.status=1 AND p.is_deleted=0 AND p.buyer_flag=1
        AND c.status=1
        AND p2s.store_id ={$store_id}
    ORDER BY mcrp.id ASC ";
        }


        $query           = $this->db->query($sql);
        $product_id_more = $query->rows;


        if (!$product_id_more) {
            return [];
        }


        if( $category_id == 0){
            $date_modified = array_column($product_id_more, 'date_modified');
            $quantity_all  = array_column($product_id_more, 'quantity_all');


            //产品更新时间 正序
            $arr_sort_date = $product_id_more;
            array_multisort($date_modified, SORT_ASC, $arr_sort_date);
            $arr_sort_date = array_flip(array_column($arr_sort_date, 'product_id'));


            //产品销量正序
            $arr_sort_quantity = $product_id_more;
            array_multisort($quantity_all, SORT_ASC, $arr_sort_quantity);
            $arr_sort_quantity = array_flip(array_column($arr_sort_quantity, 'product_id'));


            $num_sort = [];
            unset($value);
            foreach ($product_id_more as $key=>$value) {
                $product_id = $value['product_id'];
                $num_sort[$product_id] = 0;

                if (is_array($arr_sort_date) && isset($arr_sort_date[$product_id])) {
                    $num_sort[$product_id] += ($arr_sort_date[$product_id] + 1);
                }

                if (is_array($arr_sort_quantity) && isset($arr_sort_quantity[$product_id])) {
                    $num_sort[$product_id] += ($arr_sort_quantity[$product_id] + 1);
                }
            }
            unset($value);


            array_multisort($num_sort, SORT_DESC, $product_id_more);


            session()->set('activity_product_orderby', implode(',', array_column($product_id_more, 'product_id')));
        } else {
            $orderby    = session('activity_product_orderby');
            $orderbyarr = explode(',', $orderby);
            $arr_sort   = array_flip($orderbyarr);
            $num_sort   = [];
            unset($value);
            foreach ($product_id_more as $key => $value) {
                $product_id = $value['product_id'];
                $num_sort[$product_id] = 0;
                if(isset($arr_sort[$product_id])){
                    $num_sort[$product_id] += ($arr_sort[$product_id]);
                }
            }
            unset($value);
            array_multisort(array_values($num_sort), SORT_ASC, $product_id_more);
        }



        return array_splice($product_id_more, (($page - 1) * $page_limit), $page_limit);
    }


    /**
     * 根据产品ID获取参与的活动(正在进行的活动)
     * @param int $product_id
     * @return array
     */
    public function getActivityListByProductId($product_id)
    {
        $sql = "
    SELECT mc.id, mc.code, mc.name
    FROM oc_marketing_campaign AS mc
    JOIN oc_marketing_campaign_request AS mcr ON mcr.mc_id=mc.id
    JOIN oc_marketing_campaign_request_product AS mcrp ON mcrp.mc_request_id=mcr.id
    WHERE
        mc.effective_time <= NOW()
        AND mc.expiration_time > NOW()
        AND mc.is_release=1
        AND mcr.status=2
        AND mcrp.product_id={$product_id}
        AND mcrp.status=1
        AND mcrp.approval_status=2";

        $query = $this->db->query($sql);

        return $query->rows;
    }
}
