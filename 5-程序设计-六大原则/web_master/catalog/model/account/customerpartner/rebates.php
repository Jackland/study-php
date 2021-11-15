<?php

use App\Repositories\Product\ProductPriceRepository;

/**
 * Class ModelAccountCustomerpartnerRebates
 * @property ModelAccountProductQuotesRebatesContract $model_account_product_quotes_rebates_contract
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 */
class ModelAccountCustomerpartnerRebates extends Model
{
    /**
     * 查询模板总数
     *
     * @param int $id
     * @return array
     */
    public function getRebatesTemplateById($id)
    {
        if (isset($id)) {
            $sql = "SELECT * FROM tb_sys_rebate_template WHERE `id` = " . (int)$id;
            $query = $this->db->query($sql);
            return $query->row;
        }
    }

    /**
     * 查询返点模板表格展示需要的数据，分页查询
     *
     * @param $data
     * @return array
     */
    public function getRebatesTemplateDisplay($data)
    {
        if (isset($data)) {
            $sql = "SELECT
                  rt.`id`,
                  rt.`template_id`,
                  rt.`product_id`,
                  p.`sku`,
                  p.`mpn`,
                  p.`price` AS origin_price,
                  p.`quantity` AS origin_qty,
                  p.`freight`,
                  rt.`day`,
                  rt.`qty`,
                  rt.`price_customize`,
                  rt.`price`,
                  rt.`hp_price`,
                  rt.`discount_type`,
                  rt.`discount`,
                  rt.`discount_amount`,
                  rt.`price_limit`,
                  rt.`price_limit_percent`,
                  rt.`update_time`,
                  rt.`qty_limit`,
                  rt.`status`
                FROM
                  tb_sys_rebate_template rt
                  INNER JOIN oc_product p
                    ON rt.`product_id` = p.`product_id`
                WHERE rt.`customer_id` = " . (int)$data['customer_id']
                . " AND rt.`status` = 1";
            //ID条件是专为记录日志时查询方便添加的，普通的用户查询不会有这个条件
            if (isset($data['id']) AND $data['id']) {
                $sql .= " AND id IN (" . implode(',', $data['id']) . ")";
            }
            if (isset($data['filter_sku_mpn']) AND $data['filter_sku_mpn']) {
                $sql .= " AND (p.`sku` LIKE '%" . $data['filter_sku_mpn'] . "%' OR p.`mpn` LIKE '%" . $data['filter_sku_mpn'] . "%')";
            }
            $sql .= " ORDER BY rt.`update_time` DESC";
            if (isset($data['start']) || isset($data['limit'])) {
                if ($data['start'] < 0) {
                    $data['start'] = 0;
                }

                if ($data['limit'] < 1) {
                    $data['limit'] = 20;
                }

                $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
            }
            $query = $this->db->query($sql);
            return $query->rows;
        }
    }

    /**
     * 更新模板
     *
     * @param int $customer_id
     * @param $data
     */
    function updateRebatesTemplate($customer_id, $data)
    {
        $sql = "UPDATE
                  `tb_sys_rebate_template`
                SET
                  `product_id` = '" . $data['input_product_id'] . "',
                  `day` = '" . $data['input_day'] . "',
                  `qty` = '" . $data['input_qty'] . "',
                  `price_customize` = '1',
                  `price` = '" . $data['price'] . "',
                  `discount_type` = '" . $data['discount_type'] . "',
                  `discount` = '" . $data['discount'] . "',
                  `discount_amount` = '" . $data['discount_amount'] . "',
                  `price_limit` = '" . $data['input_price_limit_span'] . "',
                  `price_limit_percent` = '" . $data['price_limit_percent'] . "',
                  `qty_limit` = '" . $data['input_qty_limit'] . "',
                  `status` = '" . $data['status'] . "',
                  `memo` = '',
                  `update_time` = NOW(),
                  `update_username` = '" . $customer_id . "'
                WHERE `id` = " . (int)$data['id'];
        $this->db->query($sql);
    }

    /**
     * 记录模板操作日志
     *
     * @param $log_data
     */
    function recordRebatesTemplateLog($log_data)
    {
        if (!empty($log_data)) {
            $sql = "INSERT INTO `tb_sys_rebate_template_log` (
                          `template_key_id`,
                          `product_id_before`,
                          `product_id_last`,
                          `day_before`,
                          `day_last`,
                          `qty_before`,
                          `qty_last`,
                          `customize_before`,
                          `customize_last`,
                          `price_before`,
                          `price_last`,
                          `discount_before`,
                          `discount_last`,
                          `price_limit_percent_before`,
                          `price_limit_percent_last`,
                          `qty_limit_before`,
                          `qty_limit_last`,
                          `status_before`,
                          `status_last`,
                          `operator_code`,
                          `memo`,
                          `create_username`,
                          `create_time`
                        )
                        VALUES ";
            foreach ($log_data as $log_datum) {
                $sql .= "(
                            " . $log_datum['template_key_id'] . ",
                            " . $log_datum['product_id_before'] . ",
                            " . $log_datum['product_id_last'] . ",
                            " . $log_datum['day_before'] . ",
                            " . $log_datum['day_last'] . ",
                            " . $log_datum['qty_before'] . ",
                            " . $log_datum['qty_last'] . ",
                            " . $log_datum['customize_before'] . ",
                            " . $log_datum['customize_last'] . ",
                            " . $log_datum['price_before'] . ",
                            " . $log_datum['price_last'] . ",
                            " . $log_datum['discount_before'] . ",
                            " . $log_datum['discount_last'] . ",
                            " . $log_datum['price_limit_percent_before'] . ",
                            " . $log_datum['price_limit_percent_last'] . ",
                            " . $log_datum['qty_limit_before'] . ",
                            " . $log_datum['qty_limit_last'] . ",
                            " . $log_datum['status_before'] . ",
                            " . $log_datum['status_last'] . ",
                            " . $log_datum['operator_code'] . ",
                            '" . $log_datum['memo'] . "',
                            '" . $log_datum['create_username'] . "',
                            '" . $log_datum['create_time'] . "'
                          ),";
            }
            $sql = rtrim($sql, ',');
            $this->db->query($sql);
        }
    }

    public function getProductInformationForPromotion($seller_id, $mpn)
    {
        $sql = "SELECT p.`product_id`,p.`sku`,p.`mpn`,p.`quantity`,p.`price`,p.`freight`
                FROM oc_customerpartner_to_product ctp INNER JOIN oc_product p ON p.`product_id` = ctp.`product_id`
                WHERE (p.`mpn` = '" . $this->db->escape($mpn) . "' OR p.`sku` = '" . $this->db->escape($mpn) . "')"
            . " AND ctp.`customer_id` = " . (int)$seller_id
            . " AND p.`is_deleted` = 0"
            . " AND p.`status` = 1"
            . " AND p.`buyer_flag` = 1";

        $query = $this->db->query($sql);
        return $query->rows;
    }

    /**
     * 商品详情页获取议价模板数据
     *
     * @param int $product_id
     * @return array
     */
    public function getRebatesTemplateDisplayForProductPage($product_id){
        $sql = "SELECT * FROM `tb_sys_rebate_template` WHERE product_id = " . $product_id . " AND `status` = 1 ORDER BY price * (1-discount) DESC LIMIT 3";
        return $this->db->query($sql)->rows;
    }

    /**
     * 检查buyer对于某个商品是否允许发起返点申请
     *
     * @param int $buyer_id
     * @param int $product_id
     * @return bool
     * @throws Exception
     */
    public function checkRebatesProcessing($buyer_id, $product_id)
    {
        $this->load->model('account/product_quotes/rebates_contract');
        $this->model_account_product_quotes_rebates_contract->checkAndUpdateRebateTimeout();

        $sql = "SELECT COUNT(*) AS cnt FROM `tb_sys_rebate_contract`
                WHERE buyer_id = ".(int)$buyer_id."
                AND product_id = ".(int)$product_id."
                AND (`status` = 1 OR (`status` = 3 AND expire_time > NOW()))";
        $row = $this->db->query($sql)->row;
        if(isset($row['cnt']) && $row['cnt'] > 0){
            return false;
        }else{
            return true;
        }
    }


    /*
     * 用于日志记录（tb_sys_rebate_template_log_new）
     * select出的所有字段都将记录到日志中，请慎重添加
     * */
    public function ForTemplateLog($id)
    {
       $info = $this->orm->table('tb_sys_rebate_template')
           ->where('id', $id)
           ->select('product_id','day','qty','price_customize','price','hp_price','discount_type','discount','discount_amount','price_limit','status')
           ->first();
       return obj2array($info);
    }


    /*
     * 新的模板变更操作记录
     * @param before array
     * @param last array
     * */
    public function saveLog($template_key,$before,$last,$customer_id)
    {
        $this->orm->table('tb_sys_rebate_template_log_new')
            ->insert([
                'template_key_id'   => $template_key,
                'customer_id'       => $customer_id,
                'before'            => json_encode($before),
                'last'              => json_encode($last),
                'create_time'       => date('Y-m-d H:i:s')
            ]);
    }

    /*
     * 下载模板数据
     * */
    public function rebatesTemplatesForDownload($filter,$customer_id)
    {
        $data = $this->orm->table('tb_sys_rebate_template as t')
            ->leftJoin('oc_product as p', 't.product_id', 'p.product_id')
            ->leftJoin('oc_product_description as pd', 't.product_id', 'pd.product_id')
            ->when(isset($filter['sku_mpn']), function ($query) use ($filter){
                return $query->where(function ($query) use ($filter){
                    $query->where('p.sku', 'like', '%'.$filter['sku_mpn'].'%')
                        ->orWhere('p.mpn', 'like', '%'.$filter['sku_mpn'].'%');
                });
            })
            ->where('t.customer_id', $customer_id)
            ->where('t.status', 1)
            ->select('t.template_id','t.day','t.qty','t.price','t.hp_price','t.discount_type','t.discount','t.discount_amount','t.price_limit','t.price_limit_percent','t.update_time','p.sku','p.mpn','p.freight','pd.name')
            ->orderBy('p.sku')
            ->orderBy('t.hp_price')
            ->get()
            ->toArray();

        return $data;
    }

    /*
     * 自动联想sku
     * */
    public function autoCompleteSku($sku_mpn,$customer_id)
    {
        $data = $this->orm->table('oc_customerpartner_to_product as t')
            ->leftjoin('oc_product as p', 't.product_id', 'p.product_id')
            ->when($sku_mpn,function ($query) use ($sku_mpn){
                return $query->where(function ($q) use ($sku_mpn){
                    return $q->where('p.sku', 'like', $sku_mpn.'%')
                        ->orWhere('p.mpn', 'like', $sku_mpn.'%');
                });

            })
            ->where('t.customer_id', $customer_id)
            ->where('p.is_deleted', 0)
            ->where('p.status', 1)
            ->where('p.buyer_flag', 1)
            ->where('p.quantity', '>', 0)
            ->limit(20)
            ->select('p.sku','p.mpn')
            ->get();
        $list = [];
        foreach ($data as $k=>$v){
            $list[] = $v->sku;

            if ($v->sku != $v->mpn && !empty($v->mpn)){
                $list[] = $v->mpn;
            }
        }
        $list = array_unique($list);

        return $list;
    }


    /***********************
     * 返点四期添加数据
     * add by zjg
     * 2020年1月20日
     ***********************/

    public function get_sku_autocomplete($code,$customer_id){
        $res_sku=$this->orm->table(DB_PREFIX.'customerpartner_to_product as cp')
            ->leftJoin('oc_product AS p','p.product_id','=','cp.product_id')
//            ->leftJoin('oc_product_associate AS pa' ,'p.product_id', '=', 'pa.product_id')
//            ->leftJoin('oc_product AS p1','pa.associate_product_id','=','p1.product_id')
            ->where([
                ['cp.customer_id','=',$customer_id],
                ['p.buyer_flag','=',1],
                ['p.is_deleted','=',0],
                ['p.status','=',1],
                [ 'p.sku','like',"%$code%"],
//                ['p1.buyer_flag','=',1],
//                ['p1.is_deleted','=',0],
//                ['p1.status','=',1]
            ])
            ->whereIn('p.product_type',[0,3])
//            ->where(function($query) use ($code){
//                $query->where( 'p.sku','like',"%$code%")
//                    ->orWhere('p.mpn','like',"%$code%");
//            })
            ->select(['p.sku'])->limit(8)->get();
        $res_sku=obj2array($res_sku);
        //mpn
        $res_mpn=$this->orm->table(DB_PREFIX.'customerpartner_to_product as cp')
            ->leftJoin('oc_product AS p','p.product_id','=','cp.product_id')
            ->where([
                ['cp.customer_id','=',$customer_id],
                ['p.buyer_flag','=',1],
                ['p.is_deleted','=',0],
                ['p.status','=',1],
                [ 'p.mpn','like',"%$code%"],
            ])
            ->whereIn('p.product_type',[0,3])
            ->select(['p.mpn'])->limit(8)->get();
        $res_mpn=obj2array($res_mpn);
        $res_sku_list=array_column($res_sku,'sku');
        $res_mpn_list=array_column($res_mpn,'mpn');
        $list=array_merge($res_sku_list,$res_mpn_list);

//        echo '<pre>';
//        print_r($res_sku_list);
//        echo '</pre>';
//        die();
//
//        $list = [];
//        foreach ($res as $k=>$v){
//            $list[] = $v['sku'];
//
//            if ($v['sku'] != $v['mpn'] && !empty($v['mpn'])){
//                $list[] = $v['mpn'];
//            }
//        }
        $list = array_unique($list);
        return $list;
    }

    /**
     * @param string $code
     * @param int $customer_id
     * @return array
     */
    public function get_product_info($code,$customer_id){
        $res=$this->orm->table(DB_PREFIX.'customerpartner_to_product as cp')
            ->leftJoin('oc_product AS p','p.product_id','=','cp.product_id')
            ->leftJoin('oc_product_associate AS pa' ,'p.product_id', '=', 'pa.product_id')
            ->leftJoin('oc_product AS p1','pa.associate_product_id','=','p1.product_id')
            ->where(function($query) use ($code){
                $query->where('p.sku','=',$code)
                    ->orWhere('p.mpn','=',$code);
            })
            ->where([
                ['cp.customer_id','=',$customer_id],
                ['p.buyer_flag','=',1],
                ['p.is_deleted','=',0],
                ['p.status','=',1],
                ['p1.buyer_flag','=',1],
                ['p1.is_deleted','=',0],
                ['p1.status','=',1]
            ])
            ->select('pa.associate_product_id','p1.sku','p1.mpn','p1.quantity','p1.price','p1.image','p1.freight','p1.package_fee')
            ->get();

        return obj2array($res);
    }


    public function get_product_info_has_download($code,$customer_id){
        $res=$this->orm->table(DB_PREFIX.'customerpartner_to_product as cp')
            ->leftJoin('oc_product AS p','p.product_id','=','cp.product_id')
            ->leftJoin('oc_product_associate AS pa' ,'p.product_id', '=', 'pa.product_id')
            ->leftJoin('oc_product AS p1','pa.associate_product_id','=','p1.product_id')
            ->where(function($query) use ($code){
                $query->where('p.sku','=',$code)
                    ->orWhere('p.mpn','=',$code);
            })
            ->where([
                ['cp.customer_id','=',$customer_id],
                ['p.buyer_flag','=',1],
                ['p.is_deleted','=',0],
                ['p.status','=',1]
            ])->select('pa.associate_product_id','p1.sku','p1.mpn','p1.quantity','p1.price','p1.image')
            ->get();

        return obj2array($res);
    }

    //获取模板自增id
    public function get_tpl_id($table,$column_name,$size=6){   //表名   字段名  截取后几位
        $res=$this->orm->table($table)
            ->where($column_name,'like' ,date('Ymd',time()).'%')
            ->selectRaw('right('.$column_name.','.$size.') as curr_increment_id')
            ->orderBy('id','desc')
            ->limit(1)
            ->first();
        return obj2array($res);

    }

    //设置模板
    public function set_tpl($tpl_data,$tpl_item_data){
        $this->orm->getConnection()->beginTransaction();
        try{
            //插入tpl
            $tpl_id=$this->orm->table(DB_PREFIX.'rebate_template')->insertGetId($tpl_data);
            if(!$tpl_id){
                $this->orm->getConnection()->rollBack();
                return false;
            }
            //插入tpl items
            foreach ($tpl_item_data as $k=>&$v){
                $v['template_id']=$tpl_id;
            }
            $this->orm->table(DB_PREFIX.'rebate_template_item')->insert($tpl_item_data);
            //记录log
            $after_insert_data=$this->get_rebates_template_display(array('id'=>$tpl_id));
            $log_data=array(
                'template_id'=>$tpl_id,
                'operation_type'=>1,
                'customer_id'=>$this->customer->getId(),
                'data'=>json_encode($after_insert_data,JSON_UNESCAPED_UNICODE),
                'create_time'=>date('Y-m-d H:i:s',time())
            );
            $this->orm->table(DB_PREFIX.'rebate_template_log')->insert($log_data);
            $this->orm->getConnection()->commit();
        }catch (Exception $e) {
            $this->orm->getConnection()->rollBack();
            return false;
        }
        return $tpl_id;
    }

    //修改模板
    public function modify_tpl($data){
        $this->orm->getConnection()->beginTransaction();
        try{
            //修改总模板
            $res=$this->orm->table(DB_PREFIX.'rebate_template')->where('id','=',$data['tpl']['id'])->update($data['tpl']['data']);
            if(!$res){
                $this->orm->getConnection()->rollBack();
                return false;
            }
            //插入新数据
            if($data['add']){
                $add= $this->orm->table(DB_PREFIX.'rebate_template_item')->insert($data['add']);
                if(!$add){
                    $this->orm->getConnection()->rollBack();
                    return false;
                }
            }

            //修改数据
            //插入新数据
            if($data['update']){
                foreach ($data['update'] as $k=>$v){
                    $add= $this->orm->table(DB_PREFIX.'rebate_template_item')->where('id','=',$v['id'])->update($v['data']);
                    if(!$add){
                        $this->orm->getConnection()->rollBack();
                        return false;
                    }
                }
            }
            //删除
            if($data['delete']){
                $delete=$this->orm->table(DB_PREFIX.'rebate_template_item')->whereIn('id',$data['delete'])->update(array('is_deleted'=>1));
                if(!$delete){
                    $this->orm->getConnection()->rollBack();
                    return false;
                }
            }
            //记录log
            //查询修改后的数据
            $after_change_data=$this->get_rebates_template_display(array('id'=>$data['tpl']['id']));
            $log_data=array(
                'template_id'=>$data['tpl']['id'],
                'operation_type'=>2,
                'customer_id'=>$this->customer->getId(),
                'data'=>json_encode($after_change_data,JSON_UNESCAPED_UNICODE),
                'create_time'=>date('Y-m-d H:i:s',time())
            );
            $this->orm->table(DB_PREFIX.'rebate_template_log')->insert($log_data);
            $this->orm->getConnection()->commit();
        }catch (Exception $e) {
            $this->orm->getConnection()->rollBack();
            return false;
        }
        return $data['tpl']['id'];
    }


    //删除模板
    public function del_tpl($tpl_id,$customer_id){
        $this->orm->getConnection()->beginTransaction();
        try{
            $res=$this->orm->table(DB_PREFIX.'rebate_template')
                ->where('seller_id','=',$customer_id)
                ->whereIn('id',$tpl_id)
                ->update(['is_deleted'=>1]);
            if(!$res){
                $this->orm->getConnection()->rollBack();
                return false;
            }
            foreach ($tpl_id as $tpl){
                $log_data[]=array(
                    'template_id'=>$tpl,
                    'operation_type'=>3,
                    'customer_id'=>$this->customer->getId(),
                    'data'=>json_encode(array()),
                    'create_time'=>date('Y-m-d H:i:s',time())
                );
            }
            $this->orm->table(DB_PREFIX.'rebate_template_log')->insert($log_data);
            $this->orm->getConnection()->commit();
        }catch (Exception $e) {
            $this->orm->getConnection()->rollBack();
            return false;
        }
        return true;
    }

    //获取模板数量
    public function get_rebates_template_total($data){
        if(!$data){
            return 0;
        }
        $build= $this->orm->table(DB_PREFIX.'rebate_template')->where('seller_id','=',$data['customer_id'])->where('is_deleted',0);
        if(isset($data['filter_sku_mpn']) AND $data['filter_sku_mpn']){
            $build=$build->where('items','like','%'.$data['filter_sku_mpn'].'%');
        }
        return $build->count();
    }

    // 根据product_id 查询一批产品的rebate模板数量
    public function get_rebate_count($product_id_list=array()){
        if(!$product_id_list){
            return array();
        }
        $build=$this->orm->table(DB_PREFIX.'rebate_template as rt')
            ->rightJoin(DB_PREFIX.'rebate_template_item as rti','rt.id','=','rti.template_id')
            ->leftJoin('oc_product as p','p.product_id','=','rti.product_id')
            ->whereIn('rti.product_id',$product_id_list)
            ->where('rt.is_deleted','=',0)
            ->where('rti.is_deleted','=',0)
            ->groupBy('rti.product_id')
            ->select(['rti.product_id','p.sku','p.mpn'])
            ->selectRaw('count(*) as num')
            ->get();
        return obj2array($build);
    }

    // 根据product_id 查询一批产品的rebate模板数量
    public function get_rebate_all_count($product_id_list=array()){
        if(!$product_id_list){
            return array();
        }
        //获取同系列产品
        $product_list=$this->orm->table(DB_PREFIX.'product_associate')
            ->whereIn('product_id',$product_id_list)
            ->where('associate_product_id','<>',0)
            ->select(['associate_product_id'])
            ->get();
        $product_list=obj2array($product_list);
        $all_product_list=array_column($product_list,'associate_product_id');
        $build=$this->orm->table(DB_PREFIX.'rebate_template as rt')
            ->rightJoin(DB_PREFIX.'rebate_template_item as rti','rt.id','=','rti.template_id')
            ->leftJoin('oc_product as p','p.product_id','=','rti.product_id')
            ->whereIn('rti.product_id',$all_product_list)
            ->where('rt.is_deleted','=',0)
            ->where('rti.is_deleted','=',0)
            ->groupBy('rt.id')
            ->select(['rt.id'])
            ->selectRaw('count(*) as num')
            ->get();
        return obj2array($build);
    }

    //获取模板具体数据
    public function get_rebates_template_display($data){
        if(!$data){
            return array();
        }
        //获取tpl
        $build=$this->orm->table(DB_PREFIX.'rebate_template')->where('is_deleted',0);
        // seller id
        if(isset($data['customer_id']) && $data['customer_id']){
            $build=$build->where('seller_id','=',$data['customer_id']);
        }
        // id
        if (isset($data['id']) && is_array($data['id']) && $data['id']) {     //获取多个tpl  buyer  place bid 使用
            $build=$build->whereIn('id',$data['id']);
        }else{
            if(isset($data['id']) && $data['id']){
                $build=$build->where('id',$data['id']);
            }
        }

        if(isset($data['filter_sku_mpn']) AND $data['filter_sku_mpn']){
            $build=$build->where('items','like','%'.$data['filter_sku_mpn'].'%');
        }
        if(isset($data['start']) && isset($data['limit'])){
            $build->offset($data['start'])->limit($data['limit']);
        }
        $build->orderBy('update_time','desc');
        $tpl_info=$build->get(['id','rebate_template_id','seller_id','day','qty','search_product','items','rebate_type','rebate_value','item_price','item_rebates','update_time','limit_num']);
        $tpl_info = $tpl_info
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
        //获取tpl item
        $tpl_id_list = array_column($tpl_info, 'id');
        $tpl_item_info=$this->orm->table(DB_PREFIX.'rebate_template_item as rti')
            ->leftJoin(DB_PREFIX.'product as p','rti.product_id', '=','p.product_id')
            ->select(['rti.id','rti.template_id','rti.product_id','rti.price','rti.rebate_amount','rti.min_sell_price','p.image','p.sku','p.mpn','p.quantity','p.price as curr_price'])
            ->whereIn('template_id',$tpl_id_list)
            ->where('rti.is_deleted','=',0)
            ->get();
        $tpl_item_info=obj2array($tpl_item_info);
        //获取已经bid的数量 和已经购买的数量
        $product_list=array_unique(array_column($tpl_item_info,'product_id'));
        {   /**获取每个产品还需要的产品列表**/
            //1.产品列表->协议id列表
            //2.获取每个agreement对应的product的个数
            //3.产品列表->协议下已经购买的产品数量列表---卖出的
            //4.产品列表->协议下已经购买的产品数量列表---RMA
            //4.product 需要的数量
            $agreement_list=$this->orm->table(DB_PREFIX.'rebate_agreement_item as i')
                ->leftJoin(DB_PREFIX.'rebate_agreement as a','a.id','=','i.agreement_id')
                ->whereIn('i.product_id',$product_list)
                ->whereIn('a.rebate_result',array(1,2))  //协议是actice 和due soon
                ->select(['agreement_id','qty'])
                ->distinct()->get(['agreement_id']);
            $agreement_list=obj2array($agreement_list);
            $agreement_id_list=array_column($agreement_list,'agreement_id');
            //2.
            $agree_product_list=$this->orm->table(DB_PREFIX.'rebate_agreement_item')
                ->whereIn('agreement_id',$agreement_id_list)
                ->select(['agreement_id','product_id'])
                ->get();
            $agree_product_list=obj2array($agree_product_list);
            $new_agree_product_list=array();
            foreach ($agree_product_list as $k=>$v){
                $new_agree_product_list[$v['agreement_id']][$v['product_id']]=$v;
            }
            $agree_product_list=$new_agree_product_list;
            unset($new_agree_product_list);
            //3.
            $sell_product_list=$this->orm->table(DB_PREFIX.'rebate_agreement_order as o')
                ->leftJoin(DB_PREFIX.'rebate_agreement_item as i',[
                    ['o.agreement_id','=','i.agreement_id'],
                    ['o.item_id','=','i.id'],
                    ['o.product_id','=','i.product_id']
                ])
                ->whereIn('i.agreement_id',$agreement_id_list)
                ->where('o.type','=',1)
                ->select(['i.agreement_id','i.product_id'])
                ->selectRaw('sum(o.qty) as sum')
                ->groupBy(['i.agreement_id','i.product_id'])
                ->get();
            $sell_product_list=obj2array($sell_product_list);
            if($sell_product_list){
                $new_sell_product_list=array();
                foreach ($sell_product_list as $k =>$v){
                    $new_sell_product_list[$v['agreement_id']][$v['product_id']]=$v;
                }
                $sell_product_list=$new_sell_product_list;
                unset($new_sell_product_list);
            }
            //4.
            $rma_product_list=$this->orm->table(DB_PREFIX.'rebate_agreement_order as o')
                ->leftJoin(DB_PREFIX.'rebate_agreement_item as i',[
                    ['o.agreement_id','=','i.agreement_id'],
                    ['o.item_id','=','i.id'],
                    ['o.product_id','=','i.product_id']
                ])
                ->whereIn('i.agreement_id',$agreement_id_list)
                ->where('o.type','=',2)
                ->select(['i.agreement_id','i.product_id'])
                ->selectRaw('sum(o.qty) as sum')
                ->groupBy(['i.agreement_id','i.product_id'])
                ->get();
            $rma_product_list=obj2array($rma_product_list);
            if($rma_product_list){
                $new_rma_product_list=array();
                foreach ($rma_product_list as $k =>$v){
                    $new_rma_product_list[$v['agreement_id']][$v['product_id']]=$v;
                }
                $rma_product_list=$new_rma_product_list;
                unset($new_rma_product_list);
            }
            //4.
            $need_num_list=array();
            foreach ($agreement_list as $agree){ //协议id
                $tmp_agree_id=$agree['agreement_id'];
                //协议共卖出的量
                if(isset($sell_product_list[$tmp_agree_id])){
                    $tmp_agree_sell_total=array_sum(array_column($sell_product_list[$tmp_agree_id],'sum'));
                }else{
                      $tmp_agree_sell_total=0;
                }
                //协议共RMA的量
                if(isset($rma_product_list[$tmp_agree_id])){
                    $tmp_agree_rma_total=array_sum(array_column($rma_product_list[$tmp_agree_id],'sum'));
                }else{
                    $tmp_agree_rma_total=0;
                }
                foreach ($agree_product_list[$tmp_agree_id] as $k=>$v){    //每个产品
                    if(in_array($k,$product_list)){   //需要统计的产品的need数量
                        if(!isset($need_num_list[$k])){
                            $need_num_list[$k]=0;
                        }
//                        echo '<pre>';
//                        print_r($tmp_agree_id.'   '.$k.'   '.$agree['qty'].'  '.$tmp_agree_sell_total.'  '.$tmp_agree_rma_total.'   '.count($agree_product_list[$tmp_agree_id])).round(($agree['qty']-($tmp_agree_sell_total-$tmp_agree_rma_total))/count($agree_product_list[$tmp_agree_id]));
//                        echo '</pre>';
                        $need_num_list[$k]+=round(($agree['qty']-($tmp_agree_sell_total-$tmp_agree_rma_total))/count($agree_product_list[$tmp_agree_id]));
                    }
                }


                $flag=0;
                $all_count=0;
                if(count($need_num_list)==1){
                    //不处理
                }else{
                    foreach ($need_num_list as $kk=>$vv){
                        $flag++;
                        if($flag==count($need_num_list)){
                            $need_num_list[$kk]=($agree['qty']-($tmp_agree_sell_total-$tmp_agree_rma_total))-$all_count;
                        }
                        $all_count+=$vv;
                    }
                }

            }
            //负数变成0
            foreach ($need_num_list as $k=>&$v){
                $v=($v<=0)?0:$v;
            }
        }

//        $sell_num_list=$this->orm->table(DB_PREFIX.'rebate_agreement_order as o')
//            ->leftJoin(DB_PREFIX.'rebate_agreement_item as i',[
//                ['o.agreement_id','=','i.agreement_id'],
//                ['o.item_id','=','i.id'],
//                ['o.product_id','=','i.product_id']
//            ])->leftJoin(DB_PREFIX.'rebate_agreement as a','a.id','=','i.agreement_id')
//            ->whereIn('i.product_id',$product_list)
//            ->whereIn('a.rebate_result',array(1,2))  //协议是actice 和due soon
//            ->where('o.type','=',1)
//            ->selectRaw('i.product_id,sum(IFNULL( o.qty, 0 )) as sell_num')
//            ->groupBy('i.product_id')
//            ->get();
//        $sell_num_list=obj2array($sell_num_list);
//        $sell_num_list=array_combine(array_column($sell_num_list,'product_id'),array_column($sell_num_list,'sell_num'));
//        //退返平
//        $rma_num_list=$this->orm->table(DB_PREFIX.'rebate_agreement_order as o')
//            ->leftJoin(DB_PREFIX.'rebate_agreement_item as i',[
//                ['o.agreement_id','=','i.agreement_id'],
//                ['o.item_id','=','i.id'],
//                ['o.product_id','=','i.product_id']
//            ])->leftJoin(DB_PREFIX.'rebate_agreement as a','a.id','=','i.agreement_id')
//            ->whereIn('i.product_id',$product_list)
//            ->whereIn('a.rebate_result',array(1,2))  //协议是actice 和due soon
//            ->where('o.type','=',2)
//            ->selectRaw('i.product_id,sum(IFNULL( o.qty, 0 )) as sell_num')
//            ->groupBy('i.product_id')
//            ->get();
//        $rma_num_list=obj2array($rma_num_list);
//        $rma_num_list=array_combine(array_column($rma_num_list,'product_id'),array_column($rma_num_list,'sell_num'));
        // 拼接数据
        $tpl_info=array_combine(array_column($tpl_info,'id'),$tpl_info);
        foreach ($tpl_item_info as $k=>&$v){
            $v['need_num']=isset($need_num_list[$v['product_id']])?$need_num_list[$v['product_id']]:0;
//            $v['rma_num']=isset($rma_num_list[$v['product_id']])?$rma_num_list[$v['product_id']]:0;
            $tpl_info[$v['template_id']]['child'][]=$v;
        }
        //按照时间修改倒序
        array_multisort(array_column($tpl_info,'update_time'),SORT_DESC,$tpl_info);
        return $tpl_info;
    }

    //buyer 批量获取模板 plan1~3
    public function get_rebates_template_display_batch($product_id){
        $tpl_id_list=$this->orm->table(DB_PREFIX.'rebate_template_item')
            ->where('product_id',$product_id)
            ->where('is_deleted','=',0)
            ->pluck('template_id');
        $tpl_id_list=obj2array($tpl_id_list);
        if(!$tpl_id_list){
            return array();
        }
        $data['id']=$tpl_id_list;
        return $this->get_rebates_template_display($data);
    }

    // 查询当前商品被bid 的次数
    public function get_product_bid_count($product_id_list,$buyer_id=0){
        if(!$product_id_list){
            return array();
        }
        $build=$this->orm->table(DB_PREFIX.'rebate_agreement_item as ai')
            ->leftJoin(DB_PREFIX.'rebate_agreement as a','a.id', '=', 'ai.agreement_id')
            ->whereIn('ai.product_id',$product_id_list)
            ->where('a.rebate_result','=',7)
            ->groupBy('ai.product_id')
            ->selectRaw('product_id,count(*) as num');
        if($buyer_id){
            $build->where('a.buyer_id','=',$buyer_id);
        }
        $res=$build->get();
        return obj2array($res);
    }

    //查询商品是生效且pending
    public function get_product_pendding($product_id_list,$buyer_id=0){
        if(!$product_id_list){
            return array();
        }
        $build=$this->orm->table(DB_PREFIX.'rebate_agreement_item as ai')
            ->leftJoin(DB_PREFIX.'rebate_agreement as a','a.id', '=', 'ai.agreement_id')
            ->whereIn('ai.product_id',$product_id_list)
            ->where(function ($query){
                $query->where('a.status','=',1)
                    ->orWhere('a.rebate_result','=','1')
                    ->orWhere('a.rebate_result','=','2');
            })
            ->select(['ai.product_id','a.id','a.agreement_code']);
        if($buyer_id){
            $build->where('a.buyer_id','=',$buyer_id);
        }
        $res=$build->get();
        return obj2array($res);
    }

    // buyer  批量获取模板的展示顺序
    public function get_rebates_template_sort($product_id){
        $sort_id_list=$this->orm->table(DB_PREFIX.'rebate_template_item')
            ->where('product_id',$product_id)
            ->where('is_deleted','=',0)
            ->orderByRaw('price-rebate_amount desc')
            ->pluck('template_id');
        return obj2array($sort_id_list);
    }

    public function save_rebate_bid($buyer_id,$data){
        $tpl_id=$data['tpl_id'];
        // 获取tpl
        $tpl_info=$this->orm->table(DB_PREFIX.'rebate_template')->where('id','=',$tpl_id)->where('is_deleted',0)->first();
        $tpl_info=obj2array($tpl_info);
        // 获取tpl_item
        $tpl_item_info=$this->orm->table(DB_PREFIX.'rebate_template_item')->where('template_id',$tpl_id)->where('is_deleted',0)->get();
        $tpl_item_info=obj2array($tpl_item_info);
        //处理数据
        $curr_time=date('Y-m-d H:i:s',time());
        $agree_tpl=array(    //agreement  tpl
          'rebate_template_id'=>$tpl_info['rebate_template_id'],
          'seller_id'=>$tpl_info['seller_id'],
          'day'=>$tpl_info['day'],
          'qty'=>$tpl_info['qty'],
          'rebate_type'=>$tpl_info['rebate_type'],
          'rebate_value'=>$tpl_info['rebate_value'],
          'limit_num'=>$tpl_info['limit_num'],
          'items'=>$tpl_info['items'],
          'item_num'=>$tpl_info['item_num'],
          'item_price'=>$tpl_info['item_price'],
          'item_rebates'=>$tpl_info['item_rebates'],
          'memo'=>$tpl_info['memo'],
          'create_time'=>$curr_time,
          'update_time'=>$curr_time,
        );
        //精细化--去掉
        $this->load->model('customerpartner/DelicacyManagement');
        $delicacy_model=$this->model_customerpartner_DelicacyManagement;
        $agree_tpl_item=array();
        foreach ($tpl_item_info as $k=>$v){   // agreement tpl items
            if($delicacy_model->checkIsDisplay($v['product_id'],$this->customer->getId())){
                //#$v 商品详情页返点针对免税价调整
                $v['price'] = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($tpl_info['seller_id'], customer()->getModel(), $v['price']);
                $agree_tpl_item[]=array(
                    'product_id'=>$v['product_id'],
                    'price'=>$v['price'],
                    'rebate_amount'=>$v['rebate_amount'],
                    'min_sell_price'=>$v['min_sell_price'],
                    'memo'=>$v['memo'],
                    'create_time'=>$curr_time,
                    'update_time'=>$curr_time
                );
            }
        }
        $agree_tpl_item=array_combine(array_column($agree_tpl_item,'product_id'),$agree_tpl_item);
        //处理协议自增
        $curr_agree_res=$this->get_tpl_id(DB_PREFIX.'rebate_agreement','agreement_code');

        if(!$curr_agree_res){
            $increment_id=1;
        }else{
            $increment_id=$curr_agree_res['curr_increment_id']+1;
        }
        $increment_id=sprintf('%06d',$increment_id);
        $agree_id=date('Ymd',time()).$increment_id;
        $agree=array(
            'agreement_code'=>$agree_id,
//            'agreement_template_id'=>$data['tpl_id'],
            'buyer_id'=>$buyer_id,
            'seller_id'=>$tpl_info['seller_id'],
            'day'=>$data['day'],
            'qty'=>$data['qty'],
//            'effect_time'=>$curr_time,
//            'expire_time'=>date('Y-m-d H:i:s',time()+$data['day']*24*3600),
            'clauses_id'=>0,
            'status'=>1,
            'remark'=>$data['remark'],
            'memo'=>'',
            'create_time'=>$curr_time,
            'update_time'=>$curr_time
        );

        $agree_item=array();
        foreach ($data['child'] as $k=>$v){
            $agree_item[]=array(
                'product_id'=>$v['product_id'],
                'template_price'=>$agree_tpl_item[$v['product_id']]['price'],
                'rebate_amount'=>$v['rebate_amount'],
                'min_sell_price'=>$v['min_sell_price'],
                'memo'=>'',
                'create_time'=>$curr_time,
                'update_time'=>$curr_time
            );
        }
        $message=array(
            'writer'=>$buyer_id,
            'message'=>$data['remark'],
            'create_time'=>$curr_time,
            'memo'=>'Buyer Bid',
        );
        //入库
        $this->orm->getConnection()->beginTransaction();
        try {
            //插入agree tpl
            $agree_tpl_id = $this->orm->table(DB_PREFIX . 'rebate_agreement_template')->insertGetId($agree_tpl);
            if (!$agree_tpl_id) {
                $this->orm->getConnection()->rollBack();
                return false;
            }
            //插入agree tpl item
            foreach ($agree_tpl_item as $k=>&$v){
                $v['agreement_rebate_template_id']=$agree_tpl_id;
            }
            $agree_tpl_item_insert=$this->orm->table(DB_PREFIX. 'rebate_agreement_template_item')->insert($agree_tpl_item);
            if (!$agree_tpl_item_insert) {
                $this->orm->getConnection()->rollBack();
                return false;
            }

            // 插入agree
            //agreement_template_id 是固话模板的ID
            $agree['agreement_template_id']=$agree_tpl_id;
            $agree_id=$this->orm->table(DB_PREFIX.'rebate_agreement')->insertGetId($agree);
            if (!$agree_id) {
                $this->orm->getConnection()->rollBack();
                return false;
            }
            //插入 agree item
            foreach ($agree_item as $k=>&$v){
                $v['agreement_id']=$agree_id;
                $v['agreement_template_item_id']=$agree_tpl_id;
            }
            $agree_item_insert=$this->orm->table(DB_PREFIX.'rebate_agreement_item')->insert($agree_item);
            if (!$agree_item_insert) {
                $this->orm->getConnection()->rollBack();
                return false;
            }
            //插入message
            $message['agreement_id']=$agree_id;
            $msg=$this->orm->table(DB_PREFIX.'rebate_message')->insert($message);
            if (!$msg) {
                $this->orm->getConnection()->rollBack();
                return false;
            }
            $this->orm->getConnection()->commit();
        }catch (Exception $e) {
            $this->orm->getConnection()->rollBack();
            return false;
        }
        return $agree_id;
    }

    //获取product info
    public function get_product_info_by_id($product_id){
        if(!$product_id){
            return array();
        }
        $build=$this->orm->table(DB_PREFIX.'product')->where('product_id','=',(int)$product_id)->select(['product_id','sku','mpn','status','buyer_flag','is_deleted'])->first();
        return obj2array($build);
    }

    //删除关联的产品   ---soft delete
    public function del_product_from_template($product_id){
        $this->orm->getConnection()->beginTransaction();
        try{
            $this->del_product_tpl($product_id);
            $this->orm->getConnection()->commit();
        }catch (Exception $e) {
            $this->orm->getConnection()->rollBack();
            return false;
        }
        return true;
    }

    //从模板中删除product  model-account-customerpartern 中使用，防止事务问题，故提出
    public function del_product_tpl($product_id)
    {
        //获取产品关联的协议
        $tpl_list=$this->get_rebates_template_display_batch($product_id);
        //循环删除
        foreach ($tpl_list as $k =>$v){
            $tmp_item_list=array_combine(array_column($v['child'],'product_id'),$v['child']);
            $tmp_del_item_id=$tmp_item_list[$product_id]['id'];
            //删除item
            $del_item= $this->orm->table(DB_PREFIX.'rebate_template_item')
                ->where('id', '=',$tmp_del_item_id)
                ->update(['is_deleted' => 1]);
            //如果只有一个child  则这个tpl 删除
            if(count($tmp_item_list)==1){
                $del_tpl=$this->orm->table(DB_PREFIX.'rebate_template')
                    ->where('id','=',$v['id'])
                    ->update(['is_deleted' => 1]);
            } else {
                //重新生成items字段
                $tplItems = [];
                foreach ($v['child'] as $sku){
                    if ($sku['product_id'] != $product_id) {
                        $tplItems[] = $sku['sku'] . ':' . $sku['mpn'];
                    }
                }
                $this->orm->table(DB_PREFIX . 'rebate_template')
                    ->where('id', '=', $v['id'])
                    ->update(['items' => implode(',', $tplItems)]);
            }
        }
    }

    //获取agreement的信息
    public function get_agreement_info($id){
        $agree=$this->orm->table(DB_PREFIX.'rebate_agreement')
            ->select(['id','agreement_code','agreement_template_id','buyer_id','seller_id','day','qty'])
            ->where('id','=',$id)
            ->first();
        $agree=obj2array($agree);
        $agree_item=$this->orm->table(DB_PREFIX.'rebate_agreement_item as ai')
            ->leftJoin(DB_PREFIX.'product as p','ai.product_id','=','p.product_id')
            ->where('ai.agreement_id','=',$id)
            ->where('ai.is_delete','=',0)
            ->select(['ai.product_id','ai.template_price','rebate_amount','min_sell_price','p.sku','p.mpn'])
            ->get();
        $agree_item=obj2array($agree_item);
        $agree['child']=$agree_item;
        return $agree;
    }

    /**
     * 获取模板已使用的数量
     *
     * @param int $template_id
     * @param int $seller_id
     * @return int
     */
    public function getAgreementUsedNumber($template_id,$seller_id = 0)
    {
        if (empty($template_id)) {
            return 0;
        }

        return $this->orm->table('oc_rebate_template as rt')
            ->join('oc_rebate_agreement_template as rat', 'rat.rebate_template_id', '=', 'rt.rebate_template_id')
            ->join('oc_rebate_agreement as ra', 'ra.agreement_template_id', '=', 'rat.id')
            ->where([
                ['ra.status', '=', 3],
                ['rt.is_deleted', '=', 0],
                ['rt.id', '=', $template_id],
                ['ra.create_time', '>=', REBATE_PLACE_LIMIT_START_TIME],
            ])
            ->when($seller_id, function ($query) use ($seller_id) {
                $query->where('rt.seller_id', $seller_id);
            })
            ->whereIn('ra.rebate_result', [1, 2, 5, 6, 7])
            ->groupBy('rt.id')
            ->count();
    }

    /**
     * 批量获取模板已使用的数量
     *
     * @param array $template_ids
     * @param int $seller_id
     * @return array
     */
    public function listAgreementUsedNumber($template_ids,$seller_id = 0)
    {
        if (empty($template_ids)) {
            return [];
        }

        $objs = $this->orm->table('oc_rebate_template as rt')
            ->join('oc_rebate_agreement_template as rat', 'rat.rebate_template_id', '=', 'rt.rebate_template_id')
            ->join('oc_rebate_agreement as ra', 'ra.agreement_template_id', '=', 'rat.id')
            ->where([
                ['ra.status', '=', 3],
                ['rt.is_deleted', '=', 0],
                ['ra.create_time', '>=', REBATE_PLACE_LIMIT_START_TIME],
            ])
            ->whereIn('ra.rebate_result', [1, 2, 5, 6, 7])
            ->when($seller_id, function ($query) use ($seller_id) {
                $query->where('rt.seller_id', $seller_id);
            })
            ->whereIn('rt.id', $template_ids)
            ->groupBy('rt.id')
            ->select(['rt.id'])
            ->selectRaw('count(*) as used_num')
            ->get();
        $res = obj2array($objs);
        return array_combine(array_column($res, 'id'), array_column($res, 'used_num'));
    }

    /**
     * @param int $template_id
     * @return mixed
     */
    public function getLimitNumber($template_id)
    {
        return $this->orm::table('oc_rebate_template')
            ->where('id', $template_id)
            ->value('limit_num');
    }

    /**
     * 判断 某产品 对应的模板是否都不可以bid(名额限制)
     *
     * 只要有一个模板可以bid，即返回 true
     *
     * @param int $product_id
     * @param boolean
     * @return bool
     */
    public function checkAllTemplateCanBidByProduct($product_id)
    {
        $templateObjs = $this->orm::table('oc_rebate_template as rt')
            ->join('oc_rebate_template_item as rti', 'rti.template_id', '=', 'rt.id')
            ->where([
                ['rti.product_id', '=', $product_id],
                ['rt.is_deleted', '=', 0],
                ['rti.is_deleted', '=', 0],
            ])
            ->distinct()
            ->get(['rt.id', 'rt.limit_num']);

        if (count($templateObjs) == 0) {
            return false;
        }

        $templateArr = [];
        foreach ($templateObjs as $templateObj) {
            $templateArr[$templateObj->id] = $templateObj->limit_num;
        }

        $usedNumberArr = $this->listAgreementUsedNumber(array_keys($templateArr));

        $canBid = false;
        foreach ($templateArr as $template_id => $limit_num) {

            // 该模板没有限制名额，则直接跳出循环
            if ($limit_num < 0) {
                $canBid = true;
                break;
            }

            // 如果限制的名额数 - 已使用的数据 大于 0 则直接跳出循环
            // 反之，
            if ($limit_num - ($usedNumberArr[$template_id] ?? 0) > 0) {
                $canBid = true;
                break;
            }
        }
        return $canBid;
    }

    /**
     * 判断 某模板 是否可以bid
     *
     * 注：协议申请之后，如果模板删除，就不在进行名额限制
     *
     * @param int $agreement_id
     * @param int $seller_id
     * @return bool
     */
    public function checkTemplateCanBidByAgreementID($agreement_id,$seller_id = 0)
    {
        $templateObj = $this->orm->table('oc_rebate_agreement as ra')
            ->join('oc_rebate_agreement_template as rat', 'rat.id', '=', 'ra.agreement_template_id')
            ->join('oc_rebate_template as rt', 'rt.rebate_template_id', '=', 'rat.rebate_template_id')
            ->where([
                ['ra.id', '=', $agreement_id],
                ['rt.is_deleted', '=', 0]
            ])
            ->when($seller_id, function (\Illuminate\Database\Query\Builder $query) use ($seller_id) {
                $query->where([
                    ['rt.seller_id', '=', $seller_id],
                    ['ra.seller_id', '=', $seller_id],
                ]);
            })
            ->first(['rt.id', 'rt.limit_num']);

        // 如果模板删除，就不在进行名额限制
        if (empty($templateObj)) {
            return true;
        }
        $template_id = $templateObj->id;
        $limit_num = $templateObj->limit_num;

        if (null === $limit_num && 0 === $limit_num) {
            return false;
        } else if ($limit_num < 0) {
            return true;
        }

        $used_num = $this->getAgreementUsedNumber($template_id);
        if ($limit_num - $used_num > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int $product_id
     * @return int|string
     */
    public function getProductFreightAndPackageFee($product_id)
    {
        $obj = $this->orm->table('oc_product')
            ->select(['freight', 'package_fee'])
            ->where('product_id', '=', $product_id)
            ->first();
        if (empty($obj)) {
            return 0;
        }else{
            return bcadd($obj->freight ?? 0, $obj->package_fee ?? 0, 2);
        }
    }
}
