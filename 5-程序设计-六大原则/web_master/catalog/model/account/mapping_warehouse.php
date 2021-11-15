<?php

use App\Enums\Common\AvailableEnum;
use App\Enums\Common\YesNoEnum;
use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\SalesOrder\HomePickImportMode;
use App\Models\Warehouse\WarehouseInfo;
use Carbon\Carbon;

/**
 * Class ModelAccountMappingWarehouse
 */
class ModelAccountMappingWarehouse extends Model
{
    public function keyList()
    {
        $country_id = $this->customer->getCountryId();
        return WarehouseInfo::query()->alias('w')
            ->where('w.country_id', $country_id)
            ->where(function ($query1) {
                $query1->whereHas('attribute', function ($query2) {
                    $query2->where('seller_assign', 1);
                })->orHas('sellers');
            })
            ->get()->keyBy('WarehouseID')->toArray();
    }

    public function getWarehouseIsExist($warehouse_code, $country_id)
    {
        return db('tb_warehouses')
            ->where([
                'warehouseCode' => $warehouse_code,
                'country_id' => $country_id,
                'status' => AvailableEnum::YES,
            ])
            ->value('WarehouseID');
    }


    public function total($param)
    {
        $customer_id = intval($param['customer_id']);

        return db('oc_mapping_warehouse AS mp')
            ->where('mp.status', 1)
            ->where('mp.customer_id', $customer_id)
            ->when(!empty($param['platform_id']) && $param['platform_id'], function ($query) use ($param) {
                return $query->where('mp.platform_id', $param['platform_id']);
            })
            ->when(!empty($param['platform_warehouse_name']), function ($query) use ($param) {
                return $query->where('mp.platform_warehouse_name', 'like', '%' . $param['platform_warehouse_name'] . '%');
            })
            ->when(!empty($param['warehouse_id']), function ($query) use ($param) {
                return $query->where('mp.warehouse_id', $param['warehouse_id']);
            })
            ->count();
    }


    public function lists($param, $forCsv = 0)
    {
        $customer_id = intval($param['customer_id']);

        $list = db('oc_mapping_warehouse AS mp')
            ->leftjoin('tb_warehouses AS w', 'mp.warehouse_id', 'w.WarehouseID')
            ->leftjoin('oc_platform AS p', 'mp.platform_id', 'p.platform_id')
            ->where('mp.status', 1)
            ->where('mp.customer_id', $customer_id)
            ->when(isset($param['platform_id']) && $param['platform_id'], function ($query) use ($param) {
                return $query->where('mp.platform_id', $param['platform_id']);
            })
            ->when(!empty($param['platform_warehouse_name']), function ($query) use ($param) {
                return $query->where('mp.platform_warehouse_name', 'like', '%' . $param['platform_warehouse_name'] . '%');
            })
            ->when(!empty($param['warehouse_id']), function ($query) use ($param) {
                return $query->where('mp.warehouse_id', $param['warehouse_id']);
            })
            ->select('mp.*', 'p.name AS platform_name', 'w.WarehouseCode', 'w.Address1', 'w.Address2', 'w.Address3', 'w.Country', 'w.ZipCode', 'w.City', 'w.State');

        if ($param['sort'] == 'platform') {
            $list->orderBy('platform_name', $param['order']);
        } elseif ($param['sort'] == 'warehouse_code') {
            $list->orderBy('w.WarehouseCode', $param['order']);
        } else {
            $list->orderBy('mp.date_modified', empty($param['order']) ? 'desc' : $param['order']);
        }
        if (!$forCsv) {
            $list->offset(($param['page_num'] - 1) * $param['page_limit'])
                ->limit($param['page_limit']);
        }
        $list = $list->get()->toArray();

        return $list;

    }

    public function warehouseNameCheck($data)
    {
        $customer_id = $this->customer->getId();
        $id = isset($data['id']) ? $data['id'] : 0;
        $platform_warehouse_name = $data['platform_warehouse_name'];
        $platform_id = $data['platform_id'];

        return db('oc_mapping_warehouse')->where([
            ['customer_id', '=', $customer_id],
            ['platform_id', '=', $platform_id],
            ['platform_warehouse_name', '=', $platform_warehouse_name],
            ['status', '=', YesNoEnum::YES],
            ['id', '<>', $id],
        ])->count();
    }


    public function save($data)
    {
        return db('oc_mapping_warehouse')->insertGetId([
            'customer_id' => $data['customer_id'],
            'platform_id' => $data['platform_id'],
            'platform_warehouse_name' => $data['platform_warehouse_name'],
            'warehouse_id' => $data['warehouse_id'],
            'date_added' => Carbon::now(),
            'date_modified' => Carbon::now(),
        ]);
    }

    public function getInfoById($id)
    {
        $customer_id = $this->customer->getId();
        $ret =  db('oc_mapping_warehouse')
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


    public function updates($data)
    {
        $customer_id = $this->customer->getId();

        return db('oc_mapping_warehouse')
            ->where(['id' => $data['id'], 'customer_id' => $customer_id])
            ->update([
                'platform_id' => $data['platform_id'],
                'warehouse_id' => $data['warehouse_id'],
                'platform_warehouse_name' => $data['platform_warehouse_name'],
                'date_modified' => date('Y-m-d H:i:s')
            ]);

    }


    public function checkBuilt($param)
    {
        $customer_id = $this->customer->getId();
        $id = $param['id'];
        $platform_warehouse_name = $param['platform_warehouse_name'];
        $warehouse_id = $param['warehouse_id'];
        $platform_id = $param['platform_id'];
        $ret = db('oc_mapping_warehouse')
            ->where([
                ['customer_id', '=', $customer_id],
                ['platform_id', '=', $platform_id],
                ['platform_warehouse_name', '=', $platform_warehouse_name],
                ['warehouse_id', '=', $warehouse_id],
                ['status', '=', YesNoEnum::YES],
                ['id', '<>', $id],
            ])
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        return current($ret);

    }


    public function checkOrderNum($info)
    {
        $customer_id = $this->customer->getId();

        $id = $info['id'];
        $platform_warehouse_name = $info['platform_warehouse_name'];
        $warehouse_id = $info['warehouse_id'];

        $warehouse_info = $this->db->query("SELECT * FROM tb_warehouses WHERE warehouseId={$warehouse_id}")->row;
        if (!$warehouse_info) {
            return 0;
        }
        $WarehouseCode = $warehouse_info['WarehouseCode'];


        switch ($info['platform_id']) {
            case 1:
                //Wayfair
                /*temp.id,
temp.`warehouse_code`,
temp.`buyer_id`,
cso.`order_id`,
mw.`platform_warehouse_name`,
cso.`order_status`,
cso.`order_mode`,
cso.`import_mode`,
temp.carrier_name,
temp.ship_method */
                $sql = "SELECT
  COUNT( DISTINCT cso.`order_id`) AS c
FROM
  `tb_sys_customer_sales_wayfair_temp` AS temp
  LEFT JOIN `tb_sys_customer_sales_order` AS cso
    ON cso.`order_id` = temp.`order_id`
    AND cso.`buyer_id` = temp.`buyer_id`
  LEFT JOIN oc_mapping_warehouse AS mw
    ON temp.warehouse_code = mw.`platform_warehouse_name`
    AND temp.buyer_id = mw.customer_id
  LEFT JOIN tb_warehouses AS w
    ON w.`WarehouseID` = mw.`warehouse_id`
    AND temp.`warehouse_name` = w.`WarehouseCode`
WHERE temp.`buyer_id` = {$customer_id}
  AND mw.`id` = {$id}
  AND temp.`warehouse_name` = '{$WarehouseCode}'
  AND cso.`order_status` IN (".CustomerSalesOrderStatus::TO_BE_PAID.", ".CustomerSalesOrderStatus::CHECK_LABEL.")
  AND cso.`order_mode` = " . CustomerSalesOrderMode::PICK_UP . "
  AND cso.`import_mode` = " . HomePickImportMode::IMPORT_MODE_WAYFAIR . " ";
                break;
            case 2:
                //Amazon
                /*csdt.id,
                csdt.`warehouse_code`,
                csdt.`buyer_id`,
                cso.`order_id`,
                mw.`platform_warehouse_name`,
                cso.`order_status`,
                cso.`order_mode`,
                cso.`import_mode`,
                csdt.ship_method_code,
                csdt.ship_method */
                $sql = "SELECT
  COUNT(DISTINCT cso.`order_id`) AS c
FROM
  tb_sys_customer_sales_dropship_temp AS csdt
  LEFT JOIN tb_sys_customer_sales_order AS cso
    ON cso.`order_id` = csdt.`order_id`
  LEFT JOIN oc_mapping_warehouse AS mw
    ON csdt.warehouse_code = mw.`platform_warehouse_name`
    AND csdt.buyer_id = mw.customer_id
WHERE mw.`customer_id` = {$customer_id}
  AND mw.`id` = '{$id}'
  AND csdt.`warehouse_name`='{$WarehouseCode}'
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


    /**
     * @param array $old_info
     * @param array $new_info
     * @param string $operate 'add' 'update' 'delete'
     */
    public function log($old_info = [], $new_info = [], $operate = 'add')
    {
        $customer_id = $this->customer->getId();

        $mapping_warehouse_id = $new_info['id'];

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

        db('oc_mapping_warehouse_log')
            ->insert([
                'mapping_warehouse_id' => $mapping_warehouse_id,
                'customer_id' => $customer_id,
                'old_value' => $old_text,
                'new_value' => $new_text,
                'date_add' => Carbon::now(),
                'operate' => $operate,
            ]);
    }

    /*
     * 删除
     *  */
    public function toDelete($id)
    {
        $customerId = $this->customer->getId();
        return db('oc_mapping_warehouse')
            ->where('id', $id)
            ->where('customer_id', $customerId)
            ->update([
                'status' => 0,
                'date_modified' => date('Y-m-d H:i:s')
            ]);

    }

}
