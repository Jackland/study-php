<?php

use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\SalesOrder\HomePickImportMode;

/**
 * Class ModelAccountCustomerpartnerMappingManagement
 */
class ModelAccountCustomerpartnerMappingManagement extends Model
{
    private $customer_id = null;
    private $country_id = null;
    private $isPartner = false;
    /**
     * @var Registry $registry
     */
    protected $registry;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        $this->isPartner = $this->customer->isPartner();
        $this->country_id = $this->customer->getCountryId();
    }

    /**
     * @param array $old_info
     * @param array $new_info
     * @param string $operate 'add' 'update' 'delete'
     */
    public function log($old_info = [], $new_info = [], $operate = 'add')
    {
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
        $this->orm->table('oc_mapping_sku_log')
            ->insert([
                'mapping_sku_id'=>$new_info['id'],
                'customer_id' => $this->customer_id,
                'old_value' => $old_text,
                'new_value' => $new_text,
                'date_add' => date('Y-m-d H:i:s'),
                'operate' => $operate,
            ]);
    }

    public function save($param)
    {
        return $this->orm->table('oc_mapping_sku')
            ->insertGetId([
                'customer_id' => $this->customer_id,
                'platform_id' => $param['platform_id'],
                'platform_sku' => $param['platform_sku'],
                'platform_sku_store' => $param['platform_sku_store'],
                'sku' => $param['sku'],
                'product_id' => $param['product_id'],
                'date_modified' => date('Y-m-d H:i:s'),
                'status' => 1,
                'date_add' => date('Y-m-d H:i:s'),
            ]);
    }

    public function updateMap($param)
    {
        return $this->orm->table('oc_mapping_sku')
            ->where('id', $param['id'])
            ->where('customer_id', $this->customer_id)
            ->where('status', 1)
            ->update([
                'platform_id'   => $param['platform_id'],
                'platform_sku'  => $param['platform_sku'],
                'platform_sku_store'  => $param['platform_sku_store'],
                'sku'           => $param['sku'],
                'product_id'    => $param['product_id'],
                'date_modified' => date('Y-m-d H:i:s')
            ]);
    }

    //检测订单缓存中相关sku (来自buyer)
    public function checkOrderNum($info)
    {
        if (empty($info['platform_id']) || empty($info['id'])){
            return 0;
        }
        switch ($info['platform_id']) {
            case 1:
                return $this->orm->table('tb_sys_customer_sales_wayfair_temp')
                    ->leftJoin('tb_sys_customer_sales_order AS cso', 'cso.order_id','=','temp.order_id')
                    ->whereRaw('cso.buyer_id=temp.buyer_id')
                    ->leftJoin('oc_mapping_sku AS ms','temp.buyer_id','=','ms.customer_id')
                    ->whereRaw("temp.`item_#`=ms.platform_sku AND temp.item_code=ms.sku")
                    ->whereRaw("temp.buyer_id={$this->customer_id} AND ms.id={$info['id']} AND cso.order_status IN (".CustomerSalesOrderStatus::TO_BE_PAID.", ".CustomerSalesOrderStatus::CHECK_LABEL.")"
                        ." AND cso.order_mode=".CustomerSalesOrderMode::PICK_UP." AND cso.import_mode=".HomePickImportMode::IMPORT_MODE_WAYFAIR)
                    ->groupBy(['cso.order_id'])
                    ->count();
                break;
            case 2:
                return 0;
                break;
            default:
                return 0;
        }
    }

    public function getInfoById($id)
    {
        $data = $this->orm->table('oc_mapping_sku')
            ->selectRaw('id,customer_id,platform_id,platform_sku,sku,status,date_add,date_modified')
            ->whereRaw("customer_id={$this->customer_id} AND id={$id}")
            ->limit(1)
            ->get()->toArray();
        return $data ? obj2array($data)[0] : [];
    }

    //所有的SKU
    public function autocomplete_sku($sku, $limit)
    {
        $data = $this->orm->table('oc_product AS p')
            ->leftJoin('oc_customerpartner_to_product AS c2p', 'c2p.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customer AS c', 'c2p.customer_id', '=', 'c.customer_id')
            ->selectRaw('p.sku')
            ->whereRaw("c2p.customer_id={$this->customer_id} AND p.product_type IN (0,3) AND c.country_id={$this->country_id}")
            ->where(function ($q) use ($sku) {
                strlen($sku) && $q->where('p.sku', 'LIKE', "%{$sku}%");
            })
            ->limit($limit)
            ->get()->toArray();
        return $data ? obj2array($data) : [];
    }

    public function platformSKUCheckOnly($param)
    {
        $platform_sku = $param['platform_sku'];
        $platform_id = $param['platform_id'];
        $platform_store = $param['platform_store'];
        $data = $this->orm->table('oc_mapping_sku')
            ->selectRaw('id,customer_id,platform_id,platform_sku,sku,status,date_add,date_modified')
            ->where([
                'customer_id' => $this->customer_id,
                'platform_id' => $platform_id,
                'platform_sku' => $platform_sku,
                'platform_sku_store' => $platform_store,
                'status' => 1,
            ])
            ->limit(1)
            ->get()
            ->toArray();
        return $data ? obj2array($data)[0] : [];
    }

    public function batchDelete($idArr)
    {
        return $this->orm->table('oc_mapping_sku')
            ->whereIn('id', $idArr)
            ->where('customer_id', $this->customer_id)
            ->update([
                'status' => 0,
                'date_modified' => date('Y-m-d H:i:s')
            ]);
    }

    public function batchSave($data)
    {
        return $this->orm->table('oc_mapping_sku')->insert($data);
    }

    public function itemCodeCheck($sku)
    {
        $data = $this->orm->table('oc_product AS p')
            ->leftJoin('oc_customerpartner_to_product AS c2p', 'c2p.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customer AS c', 'c2p.customer_id', '=', 'c.customer_id')
            ->selectRaw('p.product_id')
            ->whereRaw("c2p.customer_id={$this->customer_id} AND p.product_type IN (0,3) AND c.country_id={$this->country_id}")
            ->where('sku', $sku)
            ->limit(1)->get()->toArray();
        return $data ? obj2array($data)[0] : [];
    }

    /**
     * 校验用户此前是否已建立平台与平台sku的关系
     * @param $platformId
     * @param $platformStore
     * @param $platformSku
     * @param int $id
     * @return bool
     */
    public function checkPlatformSkuExists($platformId, $platformStore, $platformSku, $id = 0)
    {
        return $this->orm->table('oc_mapping_sku AS ms')
            ->join('oc_platform AS p', 'p.platform_id', '=', 'ms.platform_id')
            ->where('ms.platform_id', $platformId)
            ->where('ms.platform_sku', $platformSku)
            ->where('ms.customer_id', $this->customer_id)
            ->where('ms.platform_sku_store', $platformStore)
            ->where('ms.status', 1)
            ->where('ms.id', '!=', $id)
            ->where('p.outer_visible', 1)
            ->exists();
    }

    public function lists($param, $type = '')
    {
        $customer_id = $this->customer->getId();
        $query = $this->orm->table('oc_mapping_sku AS s')
            ->leftJoin('oc_platform AS p', 's.platform_id', '=', 'p.platform_id')
            ->selectRaw('s.*,p.name AS platform_name')
            ->whereRaw("s.customer_id={$customer_id} AND s.`status`=1")
            ->where(function ($q) use ($param) {
                if (isset($param['platform_id']) && $param['platform_id'] > 0) {
                    $q->whereRaw("s.platform_id=" . $param['platform_id']);
                }
                if (isset($param['search_sku']) && strlen($param['search_sku'])) { //注意括号
                    $q->where(function ($q) use ($param) {
                        $q->orWhere('s.platform_sku', 'LIKE', "%{$param['search_sku']}%");
                        $q->orWhere('s.sku', 'LIKE', "%{$param['search_sku']}%");
                    });
                }
                if (isset($param['search_sku_store']) && strlen($param['search_sku_store'])) {
                    $q->where('s.platform_sku_store', 'LIKE', "%{$param['search_sku_store']}%");
                }
            });
        if ($type === 'total') {
            return $query->count();
        } elseif ($type !== 'csv_all') {
            $query->limit($param['page_limit'])->offset(($param['page_num'] - 1) * $param['page_limit']);
        }

        if (isset($param['sort'])) {
            if ($param['sort'] == 'platform') {
                $query->orderBy("platform_name", $param['order']);
            } elseif ($param['sort'] == 'platform_sku') {
                $query->orderBy("s.platform_sku", $param['order']);
            } elseif ($param['sort'] == 'platform_sku_store') {
                $query->orderBy("s.platform_sku_store", $param['order']);
            } else {
                $query->orderBy("s.date_modified", $param['order']);
            }
        } else {
            $query->orderBy("s.date_modified", $param['order']);
        }
        $list = $query->get()->toArray();
        return obj2array($list);
    }

    public function platform()
    {
        //1,2,3: Wayfair，Walmart，Amazon
        $data = $this->orm->table('oc_platform')
            ->selectRaw('platform_id,name')
            ->whereRaw('is_deleted=0 AND platform_id IN (1,2,3)')
            ->orderBy('sort_order')
            ->get()->toArray();
        $data = obj2array($data);
        return $data ? array_combine(array_column($data, 'platform_id'), $data) : [];
    }

    public function platformKeyValue()
    {
        $data = $this->platform();
        $kv = [];
        foreach ($data as $item) {
            $kv[$item['platform_id']] = $item['name'];
        }
        return $kv;
    }

    public function getSellerInfo($customer_id, $field = 'screenname')
    {
        $value = $this->orm->table('oc_customerpartner_to_customer')
            ->whereRaw("customer_id=" . $customer_id)
            ->value($field);
        return html_entity_decode($value);
    }


}
