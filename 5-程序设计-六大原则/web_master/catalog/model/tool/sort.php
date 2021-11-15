<?php

use Illuminate\Database\Query\Builder;

/**
 * Class ModelToolSort
 * @property ModelCatalogProduct $model_catalog_product
 */
class ModelToolSort extends Model {
    protected $product_id;
    protected $customer_id;
    protected $user_token;

    /**
     * @var ModelCatalogProduct $catalog_product
     */
    private $catalog_product;
    protected $country_id;
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        $this->load->model('catalog/product');
        $this->catalog_product = $this->model_catalog_product;
        $this->country_id = $this->customer->getCountryId();
        //if(!isset($this->session->data['user_token'])){
        //    session()->set('user_token', token(10));
        //}
        //$this->user_token = session('user_token');

    }

    /**
     * [bestSellersSort description]
     * @param string $country
     * @return array
     */
    public function bestSellersSort($country){
        //1.先将同款不同色产品去重，留一个总销量最高的产品代表这一系列产品
        //2.将剩余产品按照是否参与复杂交易分类
        //3.参与复杂交易的产品，选出总销量最高的前9，未参与复杂交易的产品选出总销量最高的前3
        //4.将这12个产品按照总销量倒序排列展示在频道中
        // 1.参与复杂交易的 , 总销量 ， 同款

        //if($this->cache->get('complex_transcations_product_id_'.$this->user_token)){
        //    $product_list = $this->cache->get('complex_transcations_product_id_'.$this->user_token);
        //}else{
        $product_list = $this->getComplexTransactionsProductId();//复杂交易的产品
        //}
        $common_product_id = $this->getBestSellersByQuantity($product_list);
        $product_str = implode(',',$product_list);
        $complexTransactionsProductList = $this->getComplexTransactionsProductIdByAllSales($product_str,$country);
        $commonProductList = $this->getCommonProductIdByAllSales($common_product_id,$country);
        $result = array_merge($complexTransactionsProductList,$commonProductList);
        $quantity_sort = array_column($result,'quantity');
        array_multisort($quantity_sort,SORT_DESC,$result);
        return array_column($result,'product_id');

    }


    /**
     * 服务店铺产品
     * @return string|null
     */
    public function getServerStoreProduct()
    {
        //德国        DE-SERVICE@oristand.com             838
        //日本        servicejp@gigacloudlogistics.com    631
        //英国        serviceuk@gigacloudlogistics.com    491
        //美国        service@gigacloudlogistics.com      340
        $mapIn = [340,491,631,838];
        $res = $this->orm->table(DB_PREFIX.'customerpartner_to_product')->whereIn('customer_id',$mapIn)
            ->selectRaw('group_concat(distinct product_id) as product_str')->first();
        return $res->product_str;
    }


    public function getMarginStoreProduct(){
        //外部产品        美国        bxw@gigacloudlogistics.com  694
        //内部产品        美国        bxo@gigacloudlogistics.com  696
        //内部产品        日本        nxb@gigacloudlogistics.com  746
        //内部产品        英国        UX_B@oristand.com   907
        //内部产品        德国        DX_B@oristand.com   908
        $mapIn = [694,696,746,907,908];
        $res = $this->orm->table(DB_PREFIX.'customerpartner_to_product')->whereIn('customer_id',$mapIn)
            ->selectRaw('group_concat(distinct product_id) as product_str')->first();
        return $res->product_str;

    }


    /**
     * 已弃用(从保证金三期开始已弃用)
     * @return mixed
     */
    public function getMarginRestProduct(){
        $res = $this->orm->table('tb_sys_margin_process')->whereNotNull('rest_product_id')
            ->selectRaw('group_concat(distinct rest_product_id) as product_str')->first();
        return $res->product_str;
    }


    /**
     * 同款产品，更根据最后添加时间去重
     * @param $lists
     * @return array
     */
    public function getGroupPorductToOneProduct($lists)
    {
        //product_id date_added create_time p_associate
        $results = [];
        $groups  = [];//key为p_associate
        $index   = 0;
        foreach ($lists as $value) {
            $product_id  = $value['product_id'];
            $p_associate = $value['p_associate'];
            $key_new     = $product_id;
            if ($p_associate) {
                $key_new = $p_associate;
            }


            if (!isset($groups[$key_new])) {
                $groups[$key_new] = $value;
                $index++;
            } else {
                $date_added_old = $groups[$key_new]['date_added'];
                $date_added_new = $value['date_added'];
                if ($date_added_new > $date_added_old) {
                    $groups[$key_new] = $value;
                }
            }
        }

        $results = array_values($groups);
        return $results;
    }


    public function getComplexTransactionsProductId(){
        $res_margin = $this->orm->table('tb_sys_margin_template as mt')
            ->selectRaw('group_concat(distinct mt.product_id) as tmp')
            ->where('is_del', '=', '0')
            ->first();
        $res_rebate = $this->orm->table('oc_rebate_template_item AS rti')
            ->leftJoin('oc_rebate_template AS rt', [['rt.id', '=', 'rti.template_id']])
            ->where('rti.is_deleted', 0)
            ->where('rt.is_deleted', 0)
            ->selectRaw('group_concat(distinct rti.product_id) as tmp')
            ->first();

        $res_futures = $this->orm->table('oc_futures_contract')
            ->where(
                [
                    'status'      => 1,
                    'is_deleted'  => 0,
                ]
            )
            ->selectRaw('group_concat(distinct t.product_id) as tmp')
            ->first();

        $str = $res_margin->tmp . ',' . $res_rebate->tmp . ',' . $res_futures->tmp;
        $product_list = array_values(array_unique(array_filter(explode(',',$str))));
        return $product_list;
    }


    public function getComplexTransactionsProductIdByCountry($country_id)
    {
        if (!$country_id) {
            return [];
        }
        $res_margin = $this->orm->table('tb_sys_margin_template as mt')
            ->leftJoin('oc_customerpartner_to_product AS c2p', [['c2p.product_id', '=', 'mt.product_id']])
            ->leftJoin('oc_customer AS c', [['c.customer_id', '=', 'c2p.customer_id']])
            ->selectRaw('mt.product_id')
            ->where('is_del', '=', '0')
            ->where('c.country_id', '=', $country_id)
            ->groupBy(['mt.product_id'])
            ->pluck('product_id')
            ->toArray();
        $res_rebate = $this->orm->table('oc_rebate_template_item AS rti')
            ->leftJoin('oc_rebate_template AS rt', [['rt.id', '=', 'rti.template_id']])
            ->leftJoin('oc_customerpartner_to_product AS c2p', [['c2p.product_id', '=', 'rti.product_id']])
            ->leftJoin('oc_customer AS c', [['c.customer_id', '=', 'c2p.customer_id']])
            ->where('rti.is_deleted', 0)
            ->where('rt.is_deleted', 0)
            ->where('c.country_id', '=', $country_id)
            ->selectRaw('rti.product_id')
            ->groupBy(['rti.product_id'])
            ->pluck('product_id')
            ->toArray();
        $res_futures = $this->orm->table('oc_futures_contract as fc')
            ->leftJoin('oc_customerpartner_to_product AS c2p', [['c2p.product_id', '=', 'fc.product_id']])
            ->leftJoin('oc_customer AS c', [['c.customer_id', '=', 'c2p.customer_id']])
            ->where(
                [
                    'fc.status'      => 1,
                    'fc.is_deleted'  => 0,
                    'c.country_id'   => $country_id,
                ]
            )
            ->selectRaw('fc.product_id')
            ->groupBy(['fc.product_id'])
            ->pluck('product_id')
            ->toArray();
        $product_list = array_unique(array_merge($res_margin,$res_rebate,$res_futures));
        return $product_list;
    }


    /**
     * [getBatchTableId description]
     * @param array $product_list
     * @return array
     */
    public function getBatchTableId($product_list){
        $query = $this->orm->table('tb_sys_batch as b')
            ->whereIn('b.product_id',$product_list)
            ->orderBy('b.create_time','asc');
        $query = get_complete_sql($query);
        $res = $this->orm->table(new \Illuminate\Database\Query\Expression("($query) as t"))
            ->groupBy('t.product_id')->select('t.product_id','t.create_time','t.batch_id')->pluck('t.batch_id');
        return obj2array($res);


    }

    /**
     * [getComplexTransactionsProductIdByAllSales description]
     * @param string $product_str
     * @param string $country
     * @return array
     */
    public function getComplexTransactionsProductIdByAllSales($product_str,$country){


        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str  = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str   = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group      .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        if($this->customer_id){
            $sql = "
                select
                  p.product_id ,
                  cto.quantity,
                  group_concat( distinct opa.associate_product_id) as p_associate
                From oc_product as p
                LEFT JOIN  `tb_sys_product_all_sales` as cto ON `cto`.`product_id` = `p`.`product_id`
                LEFT JOIN  `oc_product_associate`  as opa ON `opa`.`product_id` = `p`.`product_id`
                LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `p`.`product_id`
                LEFT JOIN `oc_customer` as `c` on `c`.`customer_id` = `ctp`.`customer_id`
                LEFT JOIN oc_country cou ON cou.country_id = c.country_id
                LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id=".$this->customer_id."
                WHERE
                    `p`.`product_id` IN ( ". $product_str .")
                AND `p`.`status` = 1
                AND `p`.`buyer_flag` = 1
                AND p.part_flag=0
                AND p.product_type=0
                AND p.quantity>0
                AND p.image IS NOT NULL AND p.image!=''
                AND `c`.`status` = 1 ".$condition_customer_group."
                AND `cou`.`iso_code_3` = '".$country."'
                AND (dm.product_display = 1 or dm.product_display is null)
                AND NOT EXISTS (
                    SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                    JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                    JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                    WHERE
                        dmg.seller_id = ctp.customer_id  AND bgl.seller_id = ctp.customer_id AND pgl.seller_id = ctp.customer_id
                        AND bgl.buyer_id = ".$this->customer_id." AND pgl.product_id = ctp.product_id
                        AND dmg.status=1 and bgl.status=1 and pgl.status=1
                )
                GROUP BY
                    `p`.`product_id`
                Order By
                     quantity  desc

               ";

        }else{
            $sql = "
                select
                  p.product_id ,
                  cto.quantity,
                  group_concat( distinct opa.associate_product_id) as p_associate
                From oc_product as p
                LEFT JOIN  `tb_sys_product_all_sales` as cto ON `cto`.`product_id` = `p`.`product_id`
                LEFT JOIN  `oc_product_associate`  as opa ON `opa`.`product_id` = `p`.`product_id`
                LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `p`.`product_id`
                LEFT JOIN `oc_customer` as `c` on `c`.`customer_id` = `ctp`.`customer_id`
                LEFT JOIN oc_country cou ON cou.country_id = c.country_id
                WHERE
                    `p`.`product_id` IN ( ". $product_str .")
                AND `p`.`status` = 1
                AND `p`.`buyer_flag` = 1
                AND p.part_flag=0
                AND p.product_type=0
                AND p.quantity>0
                AND p.image IS NOT NULL AND p.image!=''
                AND cto.quantity>0
                AND
                    `c`.`status` = 1 ".$condition_customer_group."
                AND
                    `cou`.`iso_code_3` = '".$country."'
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
                        `tmp`.`quantity` DESC

            ";
        $res = $this->db->query($final_sql)->rows;
        // 查询出保证金中排行最高的，但是其中不包含去除 相同颜色最高的
        $count = 0;
        $complexTransactionsProductList = [];
        foreach($res as $key => $value){
            $p_associate = $value['p_associate'] ? $value['p_associate'] : $value['product_id'];
            $product_id  = $this->getAllsalesMaxQuantity($p_associate);
            //代表此复杂交易的同款比他高，复杂交易数据要舍去
            if((int)$product_id == $value['product_id']){
                if($count < 9){
                    $complexTransactionsProductList[] = $value;
                    $count++;
                }else{
                    break;
                }

            }
        }
        return $complexTransactionsProductList;

    }

    /**
     * [getAllsalesMaxQuantity description]
     * @param string $id_str
     * @return int|boolean
     */
    public function getAllsalesMaxQuantity($id_str){
        $id_list = explode(',',$id_str);
        //$res = $this->orm->table(DB_PREFIX.'customerpartner_to_order')
        //    ->whereIn('product_id',$id_list)
        //    ->whereIn('order_product_status',[5,13])
        //    ->orderBy('quantity','desc')
        //    ->groupBy('product_id')
        //    ->selectRaw('sum(quantity) as quantity,product_id')
        //    ->limit(1)->first();
        //return $res->product_id;
        $res = $this->orm->table('tb_sys_product_all_sales')
            ->whereIn('product_id',$id_list)
            ->orderBy('quantity','desc')
            ->groupBy('product_id')
            ->selectRaw('quantity,product_id')
            ->limit(1)->first();
        if(isset($res->product_id)){
            return $res->product_id;
        }else{
            return false;
        }

    }

    /**
     * [getCommonProductIdByAllSales description] 根据
     * @param string $product_str
     * @param string $country
     * @return array
     */
    public function getCommonProductIdByAllSales($product_str,$country){
        //N-94
        $condition_customer_group = '';
        if (HOME_HIDE_CUSTOMER_GROUP) {
            $hide_customer_group_str  = implode(',', HOME_HIDE_CUSTOMER_GROUP);
            $condition_customer_group .= ' AND c.customer_group_id NOT IN (' . $hide_customer_group_str . ') ';
        }
        if (HOME_HIDE_CUSTOMER) {
            $home_hide_customer_str   = implode(',', HOME_HIDE_CUSTOMER);
            $condition_customer_group .= ' AND c.customer_id NOT IN (' . $home_hide_customer_str . ') ';
        }
        if (HOME_HIDE_ACCOUNTING_TYPE) {
            $home_hide_accounting_type_str = implode(',', HOME_HIDE_ACCOUNTING_TYPE);
            $condition_customer_group      .= ' AND c.accounting_type NOT IN (' . $home_hide_accounting_type_str . ') ';
        }

        if($this->customer_id){
            $sql = "
                select
                  p.product_id ,
                  cto.quantity,
                  IFNULL(group_concat( distinct opa.associate_product_id), p.product_id) as p_associate
                From oc_product as p
                LEFT JOIN  `tb_sys_product_all_sales` as cto ON `cto`.`product_id` = `p`.`product_id`
                LEFT JOIN  `oc_product_associate`  as opa ON `opa`.`product_id` = `p`.`product_id`
                LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `p`.`product_id`
                LEFT JOIN `oc_customer` as `c` on `c`.`customer_id` = `ctp`.`customer_id`
                LEFT JOIN oc_country cou ON cou.country_id = c.country_id
                LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id=".$this->customer_id."
                WHERE
                    `p`.`product_id`  IN ( ". $product_str .")
                AND
                    `p`.`status` = 1
                AND
                    `p`.`buyer_flag` = 1
                AND p.part_flag=0
                AND p.product_type=0
                AND p.quantity > 0
                AND
                    `c`.`status` = 1 ".$condition_customer_group."
                AND
                    `cou`.`iso_code_3` = '".$country."'
                AND (dm.product_display = 1 or dm.product_display is null)
                AND NOT EXISTS (
                    SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                    JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                    JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                    WHERE
                        dmg.seller_id = ctp.customer_id  AND bgl.seller_id = ctp.customer_id AND pgl.seller_id = ctp.customer_id
                        AND bgl.buyer_id = ".$this->customer_id." AND pgl.product_id = ctp.product_id
                        AND dmg.status=1 and bgl.status=1 and pgl.status=1
                )
                GROUP BY
                    `p`.`product_id`
                Order By
                     quantity  desc
                ";

        }else{
            $sql = "
                select
                  p.product_id ,
                  cto.quantity,
                  IFNULL(group_concat( distinct opa.associate_product_id), p.product_id) as p_associate
                From oc_product as p
                LEFT JOIN  `tb_sys_product_all_sales` as cto ON `cto`.`product_id` = `p`.`product_id`
                LEFT JOIN  `oc_product_associate`  as opa ON `opa`.`product_id` = `p`.`product_id`
                LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `p`.`product_id`
                LEFT JOIN `oc_customer` as `c` on `c`.`customer_id` = `ctp`.`customer_id`
                LEFT JOIN oc_country cou ON cou.country_id = c.country_id
                WHERE
                    `p`.`product_id` IN ( ". $product_str .")
                AND
                    `p`.`status` = 1
                AND
                    `p`.`buyer_flag` = 1
                AND p.part_flag=0
                AND p.product_type=0
                AND p.quantity > 0
                AND p.image IS NOT NULL AND p.image!=''
                AND cto.quantity>0
                AND
                    `c`.`status` = 1 ".$condition_customer_group."
                AND
                    `cou`.`iso_code_3` = '".$country."'
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
                        `tmp`.`quantity` DESC
            ";
        $res = $this->db->query($final_sql)->rows;
        // 查询出普通中排行最高的，但是其中不包含去除 相同颜色最高的
        $count = 0;
        $commonProductList = [];
        foreach($res as $key => $value){
            $p_associate = $value['p_associate'] ? $value['p_associate'] : $value['product_id'];
            $product_id  = $this->getAllsalesMaxQuantity($p_associate);
            //代表此复杂交易的同款比他高，复杂交易数据要舍去
            if((int)$product_id == $value['product_id']){
                if($count < 3){
                    $commonProductList[] = $value;
                    $count++;
                }else{
                    break;
                }

            }
        }
        return $commonProductList;



    }

    public function getBestSellersByQuantity($product_list){
        //$res = $this->orm->table(DB_PREFIX.'customerpartner_to_order')
        //    ->whereNotIn('product_id',$product_list)
        //    ->whereIn('order_product_status',[5,13])
        //    ->orderBy('quantity','desc')
        //    ->groupBy('product_id')
        //    ->selectRaw('sum(quantity) as quantity,product_id')
        //    ->limit(50)->pluck('product_id');
        //$res = obj2array($res);

        $res = $this->orm->table('tb_sys_product_all_sales')
            ->whereNotIn('product_id',$product_list)
            ->orderBy('quantity','desc')
            ->groupBy('product_id')
            ->selectRaw('quantity,product_id')
            ->limit(count($product_list))->pluck('product_id');
        $res = obj2array($res);

        return implode(',',$res);
    }

    /**
     * [setProductAllsalesQuantity description]
     * @param $order_id
     * @return array
     */
    //public function setProductAllsalesQuantity($order_id){
    //    $map = [
    //        ['o.order_id','=',$order_id],
    //        ['o.customer_id','=',$this->customer_id],
    //    ];
    //    $product_list = $this->orm->table(DB_PREFIX.'customerpartner_to_order as o')
    //        ->where($map)->pluck('product_id');
    //    $product_list = obj2array($product_list);
    //    if($product_list){
    //        //查询出quantity 然后插入或者是新增进入新的表
    //        $res = $this->orm->table(DB_PREFIX.'customerpartner_to_order')
    //            ->whereIn('order_product_status',[5,13])
    //            ->whereIn('product_id',$product_list)
    //            ->orderBy('quantity','desc')
    //            ->groupBy('product_id')
    //            ->selectRaw('sum(quantity) as quantity,product_id')
    //            ->get();
    //        foreach($res as $key => $value){
    //            $insert['quantity'] = $value->quantity;
    //            $insert['product_id'] = $value->product_id;
    //            $insert['update_time'] = date('Y-m-d H:i:s',time());
    //            $insertSearch['product_id'] = $value->product_id;
    //            $this->orm->table('tb_sys_product_all_sales')->updateOrInsert($insertSearch,$insert);
    //        }
    //    }
    //
    //}

    /**
     * [setProductAllsalesQuantity description] 此处更新在事务之中 必须要用query来完成，保证事务的一致性
     * @param int $order_id
     */
    public function setProductAllsalesQuantity($order_id)
    {
        $sql = "SELECT product_id
                From oc_customerpartner_to_order
                WHERE
                  `order_id` = '".$order_id."'";

        $query = $this->db->query($sql);
        $product_list = $query->rows;
        if($product_list){
            foreach($product_list as $key => $value){
                //验证是否是保证金的product_id
                //保证金头款不计算在内，尾款计算在内
                $flag = $this->verifyMarginProduct($value['product_id']);
                if($flag['last_flag']){
                    // 查询此保证金product_id 对应的 product_id
                    $info = $this->getMarginRealProduct($value['product_id']);
                    $quantity = db('oc_customerpartner_to_order')
                        ->whereIn('order_product_status',[5,13])
                        ->whereIn('product_id',$info['all'])
                        ->sum('quantity');
                    $insert['quantity'] = $quantity;
                    $insert['product_id'] = $info['origin'];
                    $insert['update_time'] = date('Y-m-d H:i:s',time());
                    $insertSearch['product_id'] = $info['origin'];
                    $this->addOrUpdateByTable($insertSearch,$insert);

                }elseif($flag['last_flag'] == false && $flag['first_flag'] == false){

                    $sql = "SELECT  sum(quantity) as quantity,product_id
                    From oc_customerpartner_to_order
                    WHERE
                        order_product_status in (5,13)
                    AND
                       product_id = ".$value['product_id'];
                    $res = $this->db->query($sql)->rows;
                    foreach($res as $ks => $vs){
                        $insert['quantity'] = $vs['quantity'];
                        $insert['product_id'] = $vs['product_id'];
                        $insert['update_time'] = date('Y-m-d H:i:s',time());
                        $insertSearch['product_id'] = $vs['product_id'];
                        $this->addOrUpdateByTable($insertSearch,$insert);

                    }

                }
            }


        }

    }

    /**
     * [verifyMarginProduct description]
     * @param int $product_id
     * @return array
     */
    public function verifyMarginProduct($product_id): array
    {
        // margin 头款 product_id 和尾款 product_id 不同
        $first_flag = db('tb_sys_margin_process')
            ->where('advance_product_id',$product_id)
            ->exists();
        $last_flag = db('tb_sys_margin_process as mp')
            ->leftJoin('tb_sys_margin_agreement as a','mp.margin_id', 'a.id')
            ->where(function (Builder $q) use ($product_id){
                $q->where('mp.rest_product_id',$product_id)->orwhere('a.product_id',$product_id);
            })
            ->exists();
        $arr['first_flag'] = $first_flag;
        $arr['last_flag'] =  $last_flag;
        return $arr;
    }

    public function getMarginRealProduct($product_id): array
    {
        // 获取保证金产品中的头款产品
        // 获取保证金产品中所有的尾款产品
        $list = db('tb_sys_margin_process as mp')
            ->leftJoin('tb_sys_margin_agreement as a','mp.margin_id', 'a.id')
            ->where(function (Builder $q) use ($product_id){
                $q->where('mp.rest_product_id',$product_id)->orwhere('a.product_id',$product_id);
            })
            ->select('a.product_id','mp.rest_product_id')
            ->get();
        if(!$list->isEmpty()){
            $ret = [];
            foreach($list as $item){
                if($item->product_id){
                    $ret[] = $item->product_id;
                }
            }
            return [
                'origin' => $item->product_id,
                'all' => array_values(array_unique($ret)),
            ];

        }
        return [
            'origin' => $product_id,
            'all' => [$product_id],
        ];
    }

    public function addOrUpdateByTable($search,$data){
        $sql = "SELECT
                id
                FROM tb_sys_product_all_sales
                WHERE
                product_id = ".$search['product_id'];
        $flag = $this->db->query($sql)->row;
        if($flag){
            //更新
            $sql = "update tb_sys_product_all_sales set
                    `quantity` = ". $data['quantity']
                ." , `update_time` = '".$data['update_time']."'
                    WHERE
                    product_id = ".$search['product_id'];
            $this->db->query($sql);
        }else{
            //插入
            $sql = "INSERT into tb_sys_product_all_sales (`product_id`,`create_time`,`update_time`,`quantity`)
                    values ("
                .$data['product_id'].",'"
                .$data['update_time']."','"
                .$data['update_time']."',"
                .$data['quantity'].")";
            $this->db->query($sql);
        }



    }



}



