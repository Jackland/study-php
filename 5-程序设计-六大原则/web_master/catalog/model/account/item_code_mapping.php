<?php

use App\Components\Storage\StorageCloud;
use Carbon\Carbon;

/**
 * Class ModelAccountItemCodeMapping
 */
class ModelAccountItemCodeMapping extends Model
{
    protected $platform_data = [
        3 => 'Amazon',
        7 => 'Ebay',
        13 => 'Wayfair',
        14 => 'OverStock',
        15 => 'NewEgg',
        16 => 'HomeDepot',
    ];

    protected $approval_status = [
        0 => 'Pending',
        1 => 'Approved',
        2 => 'Rejected',
    ];

    /**
     * [getAmazonStore description] 获取亚马逊店铺的名称
     * @param int $customer_id
     * @return array
     */
    public function getAmazonStore($customer_id)
    {

        $res = $this->orm->table('tb_yzc_amazon_store as s')
            ->leftJoin('tb_sys_store_to_buyer as b', 's.storeId', '=', 'b.store_id')
            ->where('b.buyer_id', $customer_id)
            ->groupBy('s.storeId')
            ->select('s.id', 's.storeId', 's.storeCode', 's.storeName')
            ->get()->toArray();
        return $res;
    }

    /**
     * [getPlatform description] 平台 无数据库
     * @param int $customer_id
     * @return array
     */
    public function getPlatform($customer_id)
    {
        $res = $this->orm->table('tb_yzc_amazon_store as s')
            ->leftJoin('tb_sys_store_to_buyer as b', 's.storeId', '=', 'b.store_id')
            ->where('b.buyer_id', $customer_id)
            ->groupBy('s.platform')
            ->pluck('s.platform');
        $arr = [];
        foreach ($res as $key => $value) {
            $arr[$value] = $this->platform_data[$value];
        }
        return $arr;
    }

    /**
     * [getSalesPerson description] 获取店铺对应的salesperson
     * @param int $customer_id
     * @return Illuminate\Support\Collection
     */
    public function getSalesPerson($customer_id)
    {
        $storeList = $this->getBuyerStoreId($customer_id);
        //$mapStore['sa.status'] = 1;
        $res = $this->orm->table('tb_salesperson_store as s')
            ->leftJoin('tb_yzc_amazon_store as st', 's.store_id', '=', 'st.id')
            ->leftJoin('tb_sys_salesperson as sa', 's.salesperson_id', '=', 'sa.id')
            //->where($mapStore)
            ->whereIn('st.storeId', $storeList)
            ->orderBy('sa.status', 'desc')
            ->groupBy('sa.id')->select('sa.name', 'sa.id')->get();
        return $res;
    }

    public function getApprovalStatus()
    {
        return $this->approval_status;
    }

    /**
     * [getUnupdateAmazonProduct description] 获取未同步的Amazon的sku
     * @param int $customer_id
     * @param $condition
     * @return array
     */
    public function getUnupdateAmazonProduct($customer_id, $condition)
    {
        $map['ap.is_b2b_update'] = 0;
        $mapSearch = [];
        if (isset($condition['id'])) {
            $mapSearch[] = ['as.id', '=', $condition['id']];
        }
        if (isset($condition['platform'])) {
            $mapSearch[] = ['as.platform', '=', $condition['platform']];
        }
        if (isset($condition['asin'])) {
            $mapSearch[] = ['ap.asin', 'like', "%{$condition['asin']}%"];
        }
        if (isset($condition['sku'])) {
            $mapSearch[] = ['ap.sku', 'like', "%{$condition['sku']}%"];
        }
        $res = $this->orm->table('tb_yzc_amazon_product as ap')
            ->leftJoin('tb_yzc_amazon_store as as', 'ap.storeId', '=', 'as.storeId')
            ->where($map)
            ->where($mapSearch)
            ->whereIN('ap.storeId', $this->getBuyerStoreId($customer_id))
            ->orderBy('ap.updateTime', 'desc')
            //->limit(20)
            ->select('ap.id as pid', 'ap.storeName as store_name', 'ap.sku', 'ap.asin', 'as.platform', 'as.id')->get();
        $res = obj2array($res);
        $store = [];
        foreach ($res as $key => $value) {
            //获取storeId
            if (isset($store[$value['id']])) {
                //直接使用
                $res[$key]['salesperson_list'] = $store[$value['id']];
            } else {
                $mapStore['s.store_id'] = $value['id'];
                $mapStore['sa.status'] = 1;
                $tmp = $this->orm->table('tb_salesperson_store as s')
                    ->leftJoin('tb_sys_salesperson as sa', 's.salesperson_id', '=', 'sa.id')
                    ->where($mapStore)->select('sa.name', 'sa.id')->get();
                $store[$value['id']] = $res[$key]['salesperson_list'] = obj2array($tmp);
            }


        }
        return $res;
    }

    /**
     * [getBuyerStoreId description] 通过buyer_id 获取 store_id
     * @param int $customer_id
     * @return array
     */
    public function getBuyerStoreId($customer_id)
    {
        $res = $this->orm->table('tb_sys_store_to_buyer')
            ->where('buyer_id', $customer_id)
            ->groupBy('store_id')
            ->pluck('store_id');
        return obj2array($res);
    }

    /**
     * [getInvalidAmazonProduct description]
     * @param int $customer_id
     * @param $condition
     * @param $sort
     * @return array
     */
    public function getInvalidAmazonProduct($customer_id, $condition, $sort = 'desc')
    {
        $map['ap.is_b2b_update'] = -1;
        $mapSearch = [

        ];
        if ($sort != 'asc') {
            $sort = 'desc';
        }
        if (isset($condition['id'])) {
            $mapSearch[] = ['as.id', '=', $condition['id']];
        }
        if (isset($condition['platform'])) {
            $mapSearch[] = ['as.platform', '=', $condition['platform']];
        }
        if (isset($condition['asin'])) {
            $mapSearch[] = ['ap.asin', 'like', "%{$condition['asin']}%"];
        }
        if (isset($condition['sku'])) {
            $mapSearch[] = ['ap.sku', 'like', "%{$condition['sku']}%"];
        }
        $res = $this->orm->table('tb_yzc_amazon_product as ap')
            ->leftJoin('tb_yzc_amazon_store as as', 'ap.storeId', '=', 'as.storeId')
            ->where($map)
            ->where($mapSearch)
            ->whereIN('ap.storeId', $this->getBuyerStoreId($customer_id))
            ->orderBy('ap.b2b_update_time', $sort)
            ->select('ap.id as pid', 'ap.storeName as store_name', 'ap.sku', 'ap.asin', 'as.platform', 'as.id', 'ap.b2b_update_time')->get();
        $res = obj2array($res);
        return $res;
    }

    /**
     * [getMappingHistoryRecord description] 根据查询获取数据
     * @param $condition
     * @param $page
     * @param $perPage
     * @param $sort
     * @param $column
     * @return array
     */
    public function getMappingHistoryRecord($condition, $page, $perPage, $sort = 'desc', $column = 'tb.update_time')
    {

        $mapSearch = [

        ];
        if ($sort != 'asc') {
            $sort = 'desc';
        }
        if ($column != 2) {
            $column = 'tb.update_time';
        } elseif ($column == 2) {
            $column = 'tb.approval_status';
        }
        if (isset($condition['store_id'])) {
            $mapSearch[] = ['as.id', '=', $condition['store_id']];
        }
        if (isset($condition['platform'])) {
            $mapSearch[] = ['tb.platform', '=', $condition['platform']];
        }
        if (isset($condition['salesperson_id'])) {
            $mapSearch[] = ['tb.salesperson_id', '=', $condition['salesperson_id']];
        }
        if (isset($condition['approval_status'])) {
            $mapSearch[] = ['tb.approval_status', '=', $condition['approval_status']];
        }
        $map['tb.customer_id'] = $this->customer->getId();
        $mapSearch[] = ['tb.is_valid','=',2];
        $builder = $this->orm->table('tb_buyer_to_outstore_to_b2b as tb')
            ->leftJoin('tb_yzc_amazon_product as ap', [['ap.sku', '=', 'tb.platform_sku'], ['ap.storeId', '=', 'tb.store_id']])
            ->leftJoin('tb_yzc_amazon_store as as', 'ap.storeId', '=', 'as.storeId')
            ->leftJoin('tb_sys_salesperson as tss', 'tss.id', '=', 'tb.salesperson_id')
            ->where($map)->where($mapSearch)->select('tb.mapping_id as id', 'ap.storeName as store_name',
                'ap.sku', 'ap.asin', 'as.platform', 'tb.update_time',
                'tb.approval_status', 'tb.salesperson_id', 'tb.b2b_sku', 'tss.name as salesperson_name'
            );
        if (isset($condition['sku'])) {
            $sku = $condition['sku'];
            $builder = $builder->where(function ($query) use ($sku) {
                $query->where('tb.platform_sku', 'like', "%{$sku}%")->orWhere('tb.b2b_sku', 'like', "%{$sku}%");
            });
        }
        $results['total'] = $builder->count('*');
        $results['data'] = $builder->forPage($page, $perPage)
            ->orderBy($column, $sort)
            ->get();
        $reject_info = [];
        foreach ($results['data'] as $key => $value) {
            if ($value->approval_status != 0) {
                //被拒绝的
                $tmp['id'] = $value->id;
                $tmp['salesperson_id'] = $value->salesperson_id;
                $tmp['b2b_sku'] = $value->b2b_sku;
                $reject_info[] = $tmp;
            }

        }
        $results['reject_info'] = $reject_info;
        return $results;
    }

    public function getMappingHistoryDownloadRecord($condition, $sort = 'desc', $column = 'tb.update_time')
    {
        $mapSearch = [

        ];
        if ($sort != 'asc') {
            $sort = 'desc';
        }
        if ($column != 2) {
            $column = 'tb.update_time';
        } elseif ($column == 2) {
            $column = 'tb.approval_status';
        }
        if (isset($condition['store_id'])) {
            $mapSearch[] = ['as.id', '=', $condition['store_id']];
        }
        if (isset($condition['platform'])) {
            $mapSearch[] = ['tb.platform', '=', $condition['platform']];
        }
        if (isset($condition['salesperson_id'])) {
            $mapSearch[] = ['tb.salesperson_id', '=', $condition['salesperson_id']];
        }
        if (isset($condition['approval_status'])) {
            $mapSearch[] = ['tb.approval_status', '=', $condition['approval_status']];
        }
        $map['tb.customer_id'] = $this->customer->getId();
        $map['tb.is_valid'] = 2;
        $builder = $this->orm->table('tb_buyer_to_outstore_to_b2b as tb')
            ->leftJoin('tb_yzc_amazon_product as ap', [['ap.sku', '=', 'tb.platform_sku'], ['ap.storeId', '=', 'tb.store_id']])
            ->leftJoin('tb_yzc_amazon_store as as', 'ap.storeId', '=', 'as.storeId')
            ->leftJoin('tb_sys_salesperson as tss', 'tss.id', '=', 'tb.salesperson_id')
            ->where($map)->where($mapSearch)->select('tb.mapping_id as id', 'ap.storeName as store_name',
                'ap.sku', 'ap.asin', 'as.platform', 'tb.update_time',
                'tb.approval_status', 'tb.salesperson_id', 'tb.b2b_sku', 'tss.name as salesperson_name'
            );
        if (isset($condition['sku'])) {
            $sku = $condition['sku'];
            $builder = $builder->where(function ($query) use ($sku) {
                $query->where('tb.platform_sku', 'like', "%{$sku}%")->orWhere('tb.b2b_sku', 'like', "%{$sku}%");
            });
        }
        $data = $builder
            ->orderBy($column, $sort)
            ->get();

        return obj2array($data);

    }

    /**
     * [getLastRejectInfo description]
     * @param $mapping_id
     * @return object
     */
    public function getLastRejectInfo($mapping_id)
    {
        $map['d.mapping_id'] = $mapping_id;
        $map['d.status'] = 5;
        $res = $this->orm->table('tb_buyer_to_outstore_to_b2b_detail as d')
            ->leftJoin('tb_sys_salesperson as sa', 'd.salesperson_id', '=', 'sa.id')
            ->where($map)->orderBy('d.time', 'asc')
            //->limit(1)
            ->select('d.platform_sku', 'd.b2b_sku', 'd.salesperson_id', 'sa.name', 'd.memo', 'd.time')->get();
        return $res;
    }

    public function getTimeLineById($mapping_id)
    {
        $map['d.mapping_id'] = $mapping_id;
        $res = $this->orm->table('tb_buyer_to_outstore_to_b2b_detail as d')
            ->leftJoin('tb_sys_salesperson as sa', 'd.salesperson_id', '=', 'sa.id')
            ->leftJoin('tb_buyer_to_outstore_to_b2b_status as bs', 'bs.status', '=', 'd.status')
            ->where($map)->orderBy('d.time', 'asc')
            ->select('d.id', 'd.platform_sku',
                'd.b2b_sku', 'd.salesperson_id', 'sa.name', 'd.memo', 'd.time',
                'bs.name as status_name', 'd.haspicture', 'bs.color', 'bs.status'
            )->get();
        $res = obj2array($res);
        foreach ($res as $key => $value) {
            if ($value['haspicture']) {
                $res[$key]['img_list'] = $this->getPictureInfoByDetailsId($value['id']);
            } else {
                $res[$key]['img_list'] = [];
            }
        }
        return $res;

    }

    public function getPictureInfoByDetailsId($id)
    {
        $img_list = $this->orm->table('tb_buyer_to_outstore_to_b2b_file')->where('details_id', $id)->select('img_real_path', 'img_real_name')->get();
        return obj2array($img_list);
    }

    /**
     * [setPlatformSkuInvalid description] 根据id 来设置
     * @param string $idStr
     * @param int $customer_id
     * @return int|string
     */
    public function setPlatformSkuInvalid($idStr, $customer_id)
    {
        $id_list = explode('_', ltrim($idStr, '_'));
        //invalid 必然是新增操作，需要更新此表中的数据到主表和details中去
        $key = 0;
        foreach ($id_list as $key => $value) {
            $tmp = $this->orm->table('tb_yzc_amazon_product as ap')
                ->leftJoin('tb_yzc_amazon_store as as', 'ap.storeId', '=', 'as.storeId')
                ->where('ap.id', $value)
                ->select('ap.id', 'ap.storeId', 'ap.storeName as store_name', 'ap.sku', 'ap.asin', 'as.platform')->first();
            $application = [
                'customer_id' => $customer_id,
                'store_id' => $tmp->storeId,
                'platform' => $tmp->platform,
                'platform_asin' => $tmp->asin,
                'platform_sku' => $tmp->sku,
                'approval_status' => 0,
                'create_time' => date('Y-m-d H:i:s', time()),
                'update_time' => date('Y-m-d H:i:s', time()),
                'is_valid' => 0,
            ];
            $mapping_id = $this->orm->table('tb_buyer_to_outstore_to_b2b')->insertGetId($application);
            if ($mapping_id) {
                $application = [
                    'platform_sku' => $tmp->sku,
                    'status' => 3,
                    'time' => date('Y-m-d H:i:s', time()),
                    'mapping_id' => $mapping_id,
                    //'memo' => $posts['memo'],
                    'haspicture' => 0,
                ];
                $this->orm->table('tb_buyer_to_outstore_to_b2b_detail')->insertGetId($application);

            }

        }
        $mapUpdate = [
            'is_b2b_update' => -1,
            'b2b_update_time' => date('Y-m-d H:i:s', time()),
            'updateTime' => date('Y-m-d H:i:s', time()),
            'b2b_update_id' => $customer_id,
        ];
        $this->orm->table('tb_yzc_amazon_product')->whereIn('id', $id_list)->update($mapUpdate);
        return $key;

    }

    /**
     * [setPlatformSkuValid description]
     * @param string $idStr
     * @param int $customer_id
     * @return void
     */
    public function setPlatformSkuValid($idStr, $customer_id)
    {
        $id_list = explode('_', ltrim($idStr, '_'));
        //Valid 必然是更新操作，需要更新此表中的数据到主表和details中去
        foreach ($id_list as $key => $value) {
            $tmp = $this->orm->table('tb_yzc_amazon_product as ap')
                ->where('ap.id', $value)
                ->select('ap.id', 'ap.storeId', 'ap.storeName as store_name', 'ap.sku', 'ap.asin')->first();
            $application = [
                'approval_status' => 0,
                'update_time' => date('Y-m-d H:i:s', time()),
                'create_time' => date('Y-m-d H:i:s', time()),
                'is_valid' => 1,
            ];
            $mapSearch = [
                'customer_id' => $customer_id,
                'store_id' => $tmp->storeId,
                'platform_sku' => $tmp->sku,
            ];
            $mapping_id = $this->orm->table('tb_buyer_to_outstore_to_b2b')->where($mapSearch)->value('mapping_id');
            $this->orm->table('tb_buyer_to_outstore_to_b2b')->where($mapSearch)->update($application);
            if ($mapping_id) {
                $application = [
                    'platform_sku' => $tmp->sku,
                    'status' => 4,
                    'time' => date('Y-m-d H:i:s', time()),
                    'mapping_id' => $mapping_id,
                    //'memo' => $posts['memo'],
                    'haspicture' => 0,
                ];
                $this->orm->table('tb_buyer_to_outstore_to_b2b_detail')->insertGetId($application);

            }

        }
        $mapUpdate = [
            'is_b2b_update' => 0,
            'b2b_update_time' => date('Y-m-d H:i:s', time()),
            'updateTime' => date('Y-m-d H:i:s', time()),
            'b2b_update_id' => $customer_id,
        ];
        $this->orm->table('tb_yzc_amazon_product')->whereIn('id', $id_list)->update($mapUpdate);
    }

    /**
     * [setPlatformSkuBind description]
     * @param $data
     * @param int $customer_id
     * @return void
     */
    public function setPlatformSkuBind($data, $customer_id)
    {
        // tb_yzc_amazon_product 更新时间
        // 绑定的是sku
        // 构造一个申请
        foreach ($data as $key => $value) {
            $mapUpdate = [
                'is_b2b_update' => 1,  //提交申请状态
                'b2b_update_time' => date('Y-m-d H:i:s', time()),
                'updateTime' => date('Y-m-d H:i:s', time()),
                'b2b_update_id' => $customer_id,
            ];
            $this->orm->table('tb_yzc_amazon_product')->where('id', $value['id'])->update($mapUpdate);
            // 构造申请
            $tmp = $this->orm->table('tb_yzc_amazon_product as ap')
                ->leftJoin('tb_yzc_amazon_store as as', 'ap.storeId', '=', 'as.storeId')
                ->where('ap.id', $value['id'])
                ->select('ap.id', 'ap.storeId', 'ap.storeName as store_name', 'ap.sku', 'ap.asin', 'as.platform')->first();
            $application = [
                'customer_id' => $customer_id,
                'store_id' => $tmp->storeId,
                'platform' => $tmp->platform,
                'platform_asin' => $tmp->asin,
                'platform_sku' => $tmp->sku,
                'b2b_sku' => $this->orm->table(DB_PREFIX . 'product')->where('product_id', $value['b2b_code'])->value('sku'),
                'salesperson_id' => $value['salesperson'],
                'approval_status' => 0,
                'create_time' => date('Y-m-d H:i:s', time()),
                'update_time' => date('Y-m-d H:i:s', time()),
                'is_valid' => 2,
            ];
            $mapSearch = [
                'customer_id' => $customer_id,
                'store_id' => $tmp->storeId,
                'platform_sku' => $tmp->sku,
            ];
            $mapping_id = $this->orm->table('tb_buyer_to_outstore_to_b2b')->where($mapSearch)->value('mapping_id');
            if ($mapping_id) {
                $this->orm->table('tb_buyer_to_outstore_to_b2b')->where($mapSearch)->update($application);
            } else {
                $mapping_id = $this->orm->table('tb_buyer_to_outstore_to_b2b')->insertGetId($application);
            }

            if ($mapping_id) {
                $app = [
                    'platform_sku' => $tmp->sku,
                    'b2b_sku' => $application['b2b_sku'],
                    'salesperson_id' => $value['salesperson'],
                    'status' => 0,
                    'time' => date('Y-m-d H:i:s', time()),
                    'mapping_id' => $mapping_id,
                    //'memo' => $posts['memo'],
                    'haspicture' => 0,
                ];
                $this->orm->table('tb_buyer_to_outstore_to_b2b_detail')->insertGetId($app);

            }
        }

    }

    /**
     * [setMappingSkuPending description]
     * @param $posts
     * @param $files
     * @param int $status
     * @return void
     */
    public function setMappingSkuPending($posts, $files, $status)
    {
        $mapping_info = $this->orm->table('tb_buyer_to_outstore_to_b2b')->where('mapping_id', $posts['mapping_id'])->first();
        if (isset($files['files'])) {
            $hasPicture = 1;
        } else {
            $hasPicture = 0;
        }

        //插入details resubmit
        if ($status == 2) {
            $application = [
                'platform_sku' => $mapping_info->platform_sku,
                'b2b_sku' => $mapping_info->b2b_sku,
                'salesperson_id' => $mapping_info->salesperson_id,
                'status' => $status,
                'time' => date('Y-m-d H:i:s', time()),
                'mapping_id' => $posts['mapping_id'],
                'memo' => $posts['memo'],
                'haspicture' => $hasPicture
            ];
        } else if ($status == 1) {
            $application = [
                'platform_sku' => $mapping_info->platform_sku,
                'b2b_sku' => $posts['sku'],
                'salesperson_id' => $posts['salesperson_id'],
                'status' => $status,
                'time' => date('Y-m-d H:i:s', time()),
                'mapping_id' => $posts['mapping_id'],
                //'memo' => $posts['memo'],
                'haspicture' => $hasPicture
            ];

        }
        $details_id = $this->orm->table('tb_buyer_to_outstore_to_b2b_detail')->insertGetId($application);
        if($status == 1){
            $application['status'] = 2;
            $this->orm->table('tb_buyer_to_outstore_to_b2b_detail')->insertGetId($application);
        }
        if ($details_id && $hasPicture) {
            //更新img
            //保存文件，重命名， 放到对应文件夹
            // 插入数据库
            $files = $this->request->file('files');
            $dataString =  date('Y-m-d', time());
            foreach($files as $items){
                $ext = $items->getClientOriginalExtension();
                $fileName = date('YmdHis', time()) . '_' . token(20) . '.'. $ext;
                $path = StorageCloud::mappingSku()->writeFile($items, $dataString,$fileName);
                $arr = [
                    'mapping_id' => $posts['mapping_id'],
                    'details_id' => $details_id,
                    'img_name' => $items->getClientOriginalName(),
                    'img_path' => $path,
                    'img_real_name' => $items->getClientOriginalName(),
                    'img_real_path' => StorageCloud::mappingSku()->getUrl(StorageCloud::mappingSku()->getRelativePath($path)),
                    'create_id' => $this->customer->getId(),
                    'update_id' => $this->customer->getId(),
                    'create_time' => Carbon::now(),
                    'update_time' => Carbon::now(),
                ];
                $this->orm->table('tb_buyer_to_outstore_to_b2b_file')->insert($arr);
            }
        }
        $this->orm->table('tb_buyer_to_outstore_to_b2b')
            ->where('mapping_id', $posts['mapping_id'])
            ->update(['approval_status' => 0,
                'update_time' => date('Y-m-d H:i:s', time()),
                'b2b_sku' => $application['b2b_sku'],
                'salesperson_id' => $application['salesperson_id'],
            ]);

    }

    /**
     * [verifyMappingSku description]
     * @param $post
     * @return array
     */
    public function verifyMappingSku($post)
    {
        //验证是否没有改变
        $map = [
            'b2b_sku' => $post['sku'],
            'mapping_id' => $post['mapping_id'],
            'salesperson_id' => $post['salesperson_id'],
        ];
        $is_original = $this->orm->table('tb_buyer_to_outstore_to_b2b')->where($map)->exists();

        if ($is_original) {
            $res['code'] = 3; // 原来的数据不变
        } else {
            $mapExist = [
                'platform_sku' => $post['sku'],
            ];
            $is_exist = $this->orm->table('tb_buyer_to_outstore_to_b2b')->where($mapExist)->whereNotIn('mapping_id', [$post['mapping_id']])->exists();
            if ($is_exist) {
                $res['code'] = 2; //出现过
            } else {
                $res['code'] = 1; // 没出现过
            }
        }


        return $res;

    }

}