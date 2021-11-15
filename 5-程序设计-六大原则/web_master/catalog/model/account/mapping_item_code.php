<?php

use App\Enums\Common\YesNoEnum;
use App\Enums\Platform\PlatformMapping;
use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\SalesOrder\HomePickImportMode;
use App\Enums\SalesOrder\HomePickUploadType;
use Carbon\Carbon;

/**
 * Class ModelAccountMappingItemCode
 */
class ModelAccountMappingItemCode extends Model
{
    public function total($param)
    {
        $customer_id = $this->customer->getId();

        $condition = "";
        $params = [];
        if (isset($param['platform_id']) && $param['platform_id'] > 0) {
            $platform_id = $param['platform_id'];
            $condition .= " AND platform_id=" . $platform_id;
        };
        if ($param['search_sku']) {
            $search_sku = $param['search_sku'];
            $condition .= " AND ( platform_sku LIKE ? OR sku LIKE ?)";
            $params[] = "%{$search_sku}%";
            $params[] = "%{$search_sku}%";
        }


        $sql = "SELECT COUNT(id) AS `counts` FROM oc_mapping_sku WHERE customer_id={$customer_id} AND `status`=1" . $condition;
        $query = $this->db->query($sql, $params);
        return $query->row['counts'];
    }


    public function lists($param, $forCsv = 0)
    {
        $customer_id = $this->customer->getId();

        $condition = "";
        $params = [];
        if (isset($param['platform_id']) && $param['platform_id'] > 0) {
            $platform_id = $param['platform_id'];
            $condition .= " AND s.platform_id=" . $platform_id;
        };
        if (isset($param['search_sku'])) {
            $search_sku = $param['search_sku'];
            $condition .= " AND ( s.platform_sku LIKE ? OR s.sku LIKE ?) ";
            $params[] = "%{$search_sku}%";
            $params[] = "%{$search_sku}%";
        }

        if ($forCsv) {
            $limit = '';
        } else {
            //分页
            $limit = " LIMIT " . ($param['page_num'] - 1) * $param['page_limit'] . ',' . $param['page_limit'];
        }

        $sql = "SELECT
                  s.*,
                  p.`name` AS platform_name
                  FROM oc_mapping_sku AS s
                  LEFT JOIN oc_platform AS p ON s.`platform_id` = p.`platform_id`
                  WHERE s.customer_id = {$customer_id}
                    AND s.`status` = 1
                    {$condition} ";
        if (array_key_exists('sort', $param)) {
            if ($param['sort'] == 'platform') {
                $sql .= " ORDER BY platform_name " . $param['order'];
            } elseif ($param['sort'] == 'platform_sku') {
                $sql .= " ORDER BY s.platform_sku " . $param['order'];
            } else {
                $order = empty($param['order']) ? 'desc' : $param['order'];
                $sql .= " ORDER BY s.date_modified " . $order;
            }
        } else {
            $order = empty($param['order']) ? 'desc' : $param['order'];
            $sql .= " ORDER BY s.date_modified " . $order;
        }

        $sql .= $limit;
        $query = $this->db->query($sql, $params);
        return $query->rows;
    }

    /**
     * @param $param
     * @return array
     */
    public function platformSKUCheckOnly($param)
    {
        $customer_id = $this->customer->getId();

        $id = array_key_exists('id', $param) ? $param['id'] : 0;
        $platform_sku = $param['platform_sku'];
        $platform_id = $param['platform_id'];

        $ret =  db('oc_mapping_sku')
            ->where([
                ['customer_id', '=', $customer_id],
                ['platform_id', '=', $platform_id],
                ['platform_sku', '=', $platform_sku],
                ['status', '=', YesNoEnum::YES],
                ['id', '<>', $id],
            ])
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        return  current($ret);

    }


    public function itemCodeCheck($param)
    {
        //sku为B2B系统中存在的sku，不允许Client乱填写
        $sku = $param['sku'];

        $ret =  db('oc_product')
            ->where('sku', $sku)
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        return current($ret);
    }


    public function checkOrderNumByPlatformSku($platform_sku)
    {
        $customer_id = $this->customer->getId();

        //order_status 1 New Order  64 Check label 参考tb_sys_dictionary内容

        /* ,
sol.id,
sol.`header_id`,
sol.item_status,
sol.item_code,
sol.product_id,
so.`order_id`,
so.order_mode,
so.order_status,
so.import_mode
*/

        $sql = "SELECT
  COUNT(DISTINCT sol.`header_id`) AS c
FROM
  tb_sys_customer_sales_order_line AS sol
  LEFT JOIN tb_sys_customer_sales_order AS so
    ON so.id = sol.`header_id`
  LEFT JOIN oc_mapping_sku AS ms
    ON ms.sku = sol.`item_code`
WHERE so.`buyer_id` = {$customer_id}
  AND so.`order_status` IN (".CustomerSalesOrderStatus::TO_BE_PAID.", ".CustomerSalesOrderStatus::CHECK_LABEL.")
  AND so.order_mode=" . CustomerSalesOrderMode::PICK_UP . "
  AND so.import_mode IN (" . HomePickImportMode::IMPORT_MODE_AMAZON . ", " . HomePickImportMode::IMPORT_MODE_WAYFAIR . ")
  AND ms.`platform_sku`='{$platform_sku}'";

        $query = $this->db->query($sql);
        return $query->row['c'];
    }


    public function checkOrderNum($info)
    {
        $customer_id = $this->customer->getId();

        $id = $info['id'];
        $platform_id = $info['platform_id'];
        $platform_sku = htmlspecialchars_decode($info['platform_sku']);
        $sku = htmlspecialchars_decode($info['sku']);


        switch ($platform_id) {
            case 1:
                //Wayfair
                $sql = "SELECT
  COUNT(DISTINCT cso.`order_id`) AS c
FROM
  `tb_sys_customer_sales_wayfair_temp` temp
  LEFT JOIN `tb_sys_customer_sales_order` cso
    ON cso.`order_id` = temp.`order_id`
    AND cso.`buyer_id` = temp.`buyer_id`
  LEFT JOIN oc_mapping_sku ms
    ON temp.buyer_id = ms.customer_id
    AND temp.`item_#` = ms.`platform_sku`
    AND temp.`item_code` = ms.`sku`
WHERE temp.`buyer_id` = {$customer_id}
  AND ms.`id` = {$id}
  AND cso.`order_status` IN (".CustomerSalesOrderStatus::TO_BE_PAID.", ".CustomerSalesOrderStatus::CHECK_LABEL.")
  AND cso.`order_mode` = " . CustomerSalesOrderMode::PICK_UP . "
  AND cso.`import_mode` = " . HomePickImportMode::IMPORT_MODE_WAYFAIR . " ";
                break;
            case 2:
                //Amazon

                return 0;

                $sql = "SELECT
  COUNT(DISTINCT cso.`order_id`) AS c
FROM
  tb_sys_customer_sales_dropship_temp AS csdt
  LEFT JOIN tb_sys_customer_sales_order AS cso
    ON cso.`order_id` = csdt.`order_id`
  LEFT JOIN oc_mapping_sku AS ms
    ON csdt.buyer_id = ms.customer_id
    AND csdt.sku = ms.`sku`
--    AND csdt.`item_#` = ms.`platform_sku`
WHERE ms.`customer_id` = {$customer_id}
  AND ms.`id` = {$id}
  AND cso.`order_status` IN (".CustomerSalesOrderStatus::TO_BE_PAID.", ".CustomerSalesOrderStatus::CHECK_LABEL.")
  AND cso.`order_mode` = " . CustomerSalesOrderMode::PICK_UP . "
  AND cso.`import_mode` = " . HomePickImportMode::IMPORT_MODE_AMAZON . "
  AND (
    csdt.ship_method_code LIKE '%CEVA%'
    OR csdt.ship_method_code LIKE '%ABF%'
    OR csdt.ship_method_code LIKE '%Estes-Express%'
    OR csdt.ship_method LIKE '%CEVA%'
    OR csdt.ship_method LIKE '%ABF%'
    OR csdt.ship_method LIKE '%Estes-Express%'
  )";
                break;
            default:
                return 0;
                break;
        }


        $query = $this->db->query($sql);
        return $query->row['c'];
    }


    public function getInfoById($id)
    {
        $customer_id = $this->customer->getId();

        $ret =  db('oc_mapping_sku')
            ->where([
                'customer_id' => $customer_id,
                'id' => $id,
            ])
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        return current($ret);
    }

    public function save($data)
    {
        $customer_id = $this->customer->getId();
        $platform_id = $data['platform_id'];
        $platform_sku = $data['platform_sku'];
        $sku = $data['sku'];
        $product_id = $data['product_id'];

        return db('oc_mapping_sku')->insertGetId([
            'customer_id' => $customer_id,
            'platform_id' => $platform_id,
            'platform_sku' => $platform_sku,
            'sku' => $sku,
            'product_id' => $product_id,
            'status' => YesNoEnum::YES,
            'date_add' => Carbon::now(),
            'date_modified' => Carbon::now(),
        ]);
    }


    public function updates($param)
    {
        $customer_id = $this->customer->getId();

        $id = $param['id'];
        $product_id = 1; // ? 为什么是1
        $sku = $param['sku'];

        db('oc_mapping_sku')->where([
            'customer_id' => $customer_id,
            'id' => $id,
        ])
            ->update([
                'product_id' => $product_id,
                'sku' => $sku,
                'date_modified' => Carbon::now(),
            ]);
    }


    //所有的SKU
    public function autocompletesku($filter_data)
    {

        $sku = $filter_data['filter_name'];
        $start = $filter_data['start'];
        $limit = $filter_data['limit'];

        $country_id = $this->customer->getCountryId();

        //所有的sku
        $sql = "
    SELECT
        p.sku
    FROM
    oc_product AS p
    LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=p.product_id
    LEFT JOIN oc_customer AS c ON c2p.customer_id=c.customer_id
    WHERE sku LIKE '%{$sku}%'
        AND p.product_type IN (0,3)
        AND c.country_id={$country_id}
    GROUP BY p.sku
    LIMIT {$start},{$limit}";

        $query = $this->db->query($sql);
        return $query->rows;
    }


    /**
     *
     * @param string $sku b2b SKU
     * @param $platform_id
     * @return array
     */
    public function getOneBySKU($sku, $platform_id)
    {
        $customer_id = $this->customer->getId();
        $ret =  db('oc_mapping_sku')
            ->where([
                'customer_id' => $customer_id,
                'platform_id' => $platform_id,
                'sku' => $sku,
                'status' => 1,
            ])
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();

        return current($ret);

    }


    public function checkUnavailable($result)
    {
        if (!$result) {
            return true;
        }

        $nowtime = date('Y-m-d H:i:s');

        if ($result['status'] == 0
            || $result['is_deleted'] == 1
            || $result['buyer_flag'] == 0
            || $result['product_display'] === null
            || $result['effective_time'] < $nowtime
            || $result['expiration_time'] > $nowtime
            || ($result['buy_status'] != 1 && $result['price_status'] != 1)
        ) {
            return true;
        } else {
            return false;
        }
    }


    public function productIsUnavailableInfo($product_id, $sku)
    {
        $customer_id = $this->customer->getId();
        $ret =  db('oc_mapping_sku as sku')
            ->leftJoin('oc_product as p', 'sku.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_product as c2p', 'p.product_id', '=', 'c2p.product_id')
            ->leftJoin('oc_customerpartner_to_customer as c2c', 'c2p.customer_id', '=', 'c2c.customer_id')
            ->leftJoin('oc_delicacy_management as dm', 'p.product_id', '=', 'dm.product_id')
            ->leftJoin('oc_buyer_to_seller as b2s', 'c2p.customer_id', '=', 'b2s.seller_id')
            ->where(
                [
                    'p.sku' => $sku,
                    'b2s.buyer_id' => $customer_id,
                    'p.product_id' => $product_id,
                ]
            )
            ->selectRaw('p.`product_id`,
              p.`sku`,
              p.`status`,
              p.`is_deleted`,
              p.`buyer_flag`,
              c2p.`customer_id`,
              c2c.`screenname`,
              dm.`product_display`,
              dm.`effective_time`,
              dm.`expiration_time`,
              b2s.`buy_status`,
              b2s.`price_status`')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        return current($ret);
        //return $this->checkUnavailable($info);
    }


    /**
     * @param array $old_info
     * @param array $new_info
     * @param string $operate 'add' 'update' 'delete'
     */
    public function log($old_info = [], $new_info = [], $operate = 'add')
    {
        $customer_id = $this->customer->getId();

        $mapping_sku_id = $new_info['id'];

        $old_text = '';
        $new_text = '';
        if ($old_info) {
            $old_arr = [];
            $new_arr = [];
            foreach ($new_info as $k => $v) {
                if ($new_info[$k] != $old_info[$k]) {
                    $old_arr[$k] = $old_info[$k];
                    $new_arr[$k] = $new_info[$k];
                }
            }
            $old_text = $old_arr ? json_encode($old_arr, JSON_UNESCAPED_UNICODE) : '';
            $new_text = $new_arr ? json_encode($new_arr, JSON_UNESCAPED_UNICODE) : '';
        } else {
            if ($new_info) {
                $new_text = json_encode($new_info, JSON_UNESCAPED_UNICODE);
            }
        }
        if (!in_array($operate, ['add', 'update', 'delete'])) {
            $operate = 'add';
        }

        db('oc_mapping_sku_log')
            ->insert([
                'mapping_sku_id' => $mapping_sku_id,
                'customer_id' => $customer_id,
                'old_value' => $old_text,
                'new_value' => $new_text,
                'date_add' => Carbon::now(),
                'operate' => $operate,
            ]);
    }


    /*
     * 删除
     * */
    public function batchDelete($idArr)
    {
        $customerId = $this->customer->getId();
        return db('oc_mapping_sku')
            ->whereIn('id', $idArr)
            ->where('customer_id', $customerId)
            ->update([
                'status' => 0,
                'date_modified' => date('Y-m-d H:i:s')
            ]);
    }

    /*
     * 校验用户此前是否已建立平台与平台sku的关系
     * */
    public function checkPlatformSkuExists($platformId, $platformSku, $id = 0)
    {
        $customerId = $this->customer->getId();
        return db('oc_mapping_sku AS ms')
            ->join('oc_platform AS p', 'p.platform_id', '=', 'ms.platform_id')
            ->where('ms.platform_id', $platformId)
            ->where('ms.platform_sku', $platformSku)
            ->where('ms.customer_id', $customerId)
            ->where('ms.status', 1)
            ->where('ms.id', '!=', $id)
            ->where('p.outer_visible', 1)
            ->exists();

    }

    public function getMatchPlatform($country_id)
    {
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        if ($isCollectionFromDomicile) {
            if ($country_id == AMERICAN_COUNTRY_ID) {
                $list = [
                    PlatformMapping::WAYFAIR,
                    PlatformMapping::AMAZON,
                    PlatformMapping::WALMART,
                    PlatformMapping::HOMEDEPOT,
                    PlatformMapping::OVERSTOCK,
                ];
            } elseif ($country_id == HomePickUploadType::BRITAIN_COUNTRY_ID) {
                $list = [
                    PlatformMapping::WAYFAIR,
                    PlatformMapping::AMAZON,
                ];
            } elseif ($country_id == HomePickUploadType::GERMANY_COUNTRY_ID) {
                $list = [
                    PlatformMapping::WAYFAIR,
                ];
            } else {
                $list = [
                    PlatformMapping::WAYFAIR,
                    PlatformMapping::AMAZON,
                    PlatformMapping::WALMART,
                ];
            }
        } else {
            $list = [
                PlatformMapping::WAYFAIR,
                PlatformMapping::AMAZON,
                PlatformMapping::WALMART,
            ];
        }
        return db('oc_platform')
            ->where('is_deleted', 0)
            ->whereIn('platform_id', $list)
            ->pluck('name', 'platform_id')
            ->toArray();
    }

    public function platform()
    {
        return db('oc_platform')
            ->where('is_deleted', 0)
            ->where('outer_visible', 1)
            ->pluck('name', 'platform_id')
            ->toArray();

    }

    public function batchSave($data)
    {

        return db('oc_mapping_sku')
            ->insert($data);

    }

    //编辑更新
    public function updateMap($param)
    {

        return db('oc_mapping_sku')
            ->where('id', $param['id'])
            ->where('customer_id', $this->customer->getId())
            ->where('status', 1)
            ->update([
                'platform_id' => $param['platform_id'],
                'platform_sku' => $param['platform_sku'],
                'sku' => $param['sku'],
                'product_id' => $param['product_id'],
                'date_modified' => date('Y-m-d H:i:s')
            ]);

    }
}
