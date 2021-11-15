<?php

/**
 * Class ModelAccountCustomerpartnerMargin
 */
class ModelAccountCustomerpartnerMargin extends Model
{
    /**
     * 查询保证金模板详情
     *
     * @param int $id
     * @return array
     */
    public function getMarginTemplateById($id)
    {
        if (isset($id)) {
            $sql = "SELECT * FROM tb_sys_margin_template WHERE `id` = " . (int)$id;
            $query = $this->db->query($sql);
            $template = $query->row;
            if (empty($template)){
                return [];
            }
            $product = $this->getProductById($template['product_id']);
            $bondTemplate = $this->getBondTemplateNumber($template['bond_template_id']);

            return array_merge($template,$product,['margin_template'=>$bondTemplate]);
        }else{
            return [];
        }
    }

    //获取商品详情
    public function getProductById($id)
    {

        $this->table = 'oc_product';
        $info = $this->find($id,['product_id','sku','mpn','quantity','price'],'product_id');

        return (array)$info;
    }

    //获取bond_template_number
    public function getBondTemplateNumber($id)
    {
        $this->table = 'tb_bond_template';
        $info = $this->find($id,['bond_template_number'],'id');

        return empty($info)?'':$info->bond_template_number;
    }

    //查询sellerID 在指定 product ID对应的所有margin template
    public function getProductTemplate($sellerId,$productId)
    {
        $sql = "SELECT
                  m.`id`,
                  m.`template_id`,
                  m.`product_id`,
                  p.`sku`,
                  p.`mpn`,
                  p.`quantity`,
                  p.`freight`,
                  p.`price` as 'product_price',
                  m.`bond_template_id`,
                  m.`price`,
                  m.`hp_price`,
                  m.`day`,
                  m.`max_num`,
                  m.`min_num`,
                  m.`payment_ratio`,
                  m.`is_default`,
                  m.`update_time`
                FROM
                  tb_sys_margin_template m
                  INNER JOIN oc_product p
                    ON m.`product_id` = p.`product_id`
                WHERE m.`seller_id` = " . $sellerId
            . " AND m.`is_del` = 0"
            . " AND m.`product_id` = $productId"
            . " Order By m.`id`";
        $query = $this->db->query($sql);
        $info = $query->rows;
        if (empty($info)){
            return [];
        }
        $margin_template = $this->getBondTemplateNumber($info[0]['bond_template_id']);
        return [
            'template_list'   => $info,
            'margin_template' => $margin_template,
            'margin_template_id' => $info[0]['bond_template_id'],
            'margin_template_ratio' => $info[0]['payment_ratio']
        ];
    }

    /**
     * 查询模板总数
     *
     * @param $data
     * @return mixed
     */
    public function getMarginTemplateTotal($data)
    {

        $sql = "SELECT
                  COUNT(*) AS c
                FROM
                  tb_sys_margin_template m
                  INNER JOIN oc_product p
                    ON m.`product_id` = p.`product_id`
                WHERE m.`seller_id` = " . (int)$data['customer_id']
            . " AND m.`is_del` = 0";

        if (isset($data['filter_sku_mpn']) AND $data['filter_sku_mpn']) {
            $sql .= " AND (p.`sku` LIKE '%" . $data['filter_sku_mpn'] . "%' OR p.`mpn` LIKE '%" . $data['filter_sku_mpn'] . "%')";
        }
        $query = $this->db->query($sql);
        return $query->row['c'];

    }

    /**
     * 查询保证金模板表格展示需要的数据，分页查询
     *
     * @param $data
     * @return array
     */
    public function getMarginTemplateDisplay($data)
    {
        if (isset($data)) {
            $sql = "SELECT
                  m.`id`,
                  m.`template_id`,
                  m.`product_id`,
                  p.`sku`,
                  p.`mpn`,
                  p.`price` as product_price,
                  m.`price`,
                  m.`hp_price`,
                  m.`day`,
                  m.`max_num`,
                  m.`min_num`,
                  m.`payment_ratio`,
                  m.`is_default`,
                  m.`create_time`,
                  m.`update_time`,
                  m.bond_template_id
                FROM
                  tb_sys_margin_template m
                  INNER JOIN oc_product p
                    ON m.`product_id` = p.`product_id`
                WHERE m.`seller_id` = " . (int)$data['customer_id']
                . " AND m.`is_del` = 0";

            if (isset($data['filter_sku_mpn']) AND $data['filter_sku_mpn']) {
                $sql .= " AND (p.`sku` LIKE '%" . $data['filter_sku_mpn'] . "%' OR p.`mpn` LIKE '%" . $data['filter_sku_mpn'] . "%')";
            }
            $sql .= " ORDER BY m.`update_time` DESC, m.`min_num`";
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


    /*************************
     *  现货保证金三期 添加
     *  add  by zjg
     *  2020年3月13日
     **********************/

    public function get_prodcutid_by_updatetime($data){
        $res=$this->orm->table('tb_sys_margin_template as m')
            ->join('oc_product as p','m.product_id','=','p.product_id')
            ->where('m.seller_id','=',$data['customer_id'])
            ->where('m.is_del','=',0);
        if($data['filter_sku_mpn']){
            $res->where(function ($query) use ($data){
                $query->where('p.sku','like',$data['filter_sku_mpn'])
                    ->orWhere('p.mpn','like',$data['filter_sku_mpn']);
            });
        }
        $res = $res->select(['m.product_id'])
            ->selectRaw('max(m.update_time) as time ')
            ->groupBy('m.product_id')
            ->orderBy('time',$data['order'])
            ->get();
        return obj2array($res);
    }

    //用于三期展示使用，旧方法用于download
    public function getMarginTemplateTotal_new($data){
        $prodcut_id_list=$this->get_prodcutid_by_updatetime($data);
        return count($prodcut_id_list);
    }

    //用于三期展示使用，旧方法用于download
    public function getMarginTemplateDisplay_new($data){
        $prodcut_id_list=$this->get_prodcutid_by_updatetime($data);
        //处理limit
        $show_product_list = array_slice($prodcut_id_list, $data['start'] ?? 0, $data['limit'] ?? 15);
        //显示顺序
        $show_product_order=array_column($show_product_list,'product_id');
        //获取相关模板数据
        $res=array();
        foreach ($show_product_order as $prodct_id){    //有效率问题
            $get_tpl_data=$this->getProductTemplate($data['customer_id'],$prodct_id);
            //data  一定有值
            $update_time_list=array_column($get_tpl_data['template_list'],'update_time');
            $max_update=max($update_time_list);
            $res[$prodct_id]=array(
                'product_id'=>$get_tpl_data['template_list'][0]['product_id'],
                'sku'=>$get_tpl_data['template_list'][0]['sku'],
                'mpn'=>$get_tpl_data['template_list'][0]['mpn'],
                'price'=>$get_tpl_data['template_list'][0]['product_price'],
                'margin_rate'=>$get_tpl_data['margin_template_ratio'],
                'margin_tpl_id'=>$get_tpl_data['margin_template_id'],
                'margin_tpl'=>$get_tpl_data['margin_template'],
                'update_time'=>$max_update,
                'tpl'=>array()
            );
            foreach ($get_tpl_data['template_list'] as $k=>$v){
                $res[$prodct_id]['tpl'][$k]=array(
                    'min'=>$v['min_num'],
                    'max'=>$v['max_num'],
                    'day'=>$v['day'],
                    'price'=>$v['price'],
                    'is_dafault'=>$v['is_default']
                );
            }
        }
        return $res;
    }


    public function get_sku_autocomplete($sku_mpn,$customer_id){
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
            ->whereIn('p.product_type',[0,3])
            ->limit(20)
            ->select('p.sku','p.mpn')
            ->get();
        $data=obj2array($data);
        $list=array();
        foreach ($data as $k=>$v){
            $list[]=$v['sku'].'/'.$v['mpn'];
        }
        return $list;
    }

    /****
     * 现货保证金三期
     * end
     */


    /**
     * 软删除指定id的保证金模板
     *
     * @param int $seller_id
     * @param int $id
     * @return int
     */
    function deleteMarginTemplates($seller_id, $id)
    {
        $update = [
            'is_del'        => 1,
            'update_time'   => date('Y-m-d H:i:s'),
            'update_user'   => $seller_id
        ];

        $where = 'seller_id = '.$seller_id.' and id = ' . $id;
        $ret = $this->update('tb_sys_margin_template', $update, $where);
        return $ret->num_rows;
    }

    function deleteMarginProductTemplates($seller_id, $idArr)
    {
        $sql = 'SELECT DISTINCT(product_id) FROM `tb_sys_margin_template`
                WHERE id IN ('. implode(',',$idArr).') AND is_del=0 AND seller_id='.$seller_id.';';

        $product = $this->db->query($sql)->rows;
        $productArr = $this->getValue($product, 'product_id');

        $update = [
            'is_del'        => 1,
            'update_time'   => date('Y-m-d H:i:s'),
            'update_user'   => $seller_id
        ];

        $where = 'seller_id = '.$seller_id.' and is_del = 0 and product_id in (' . implode(',', $productArr) . ')';
        $ret = $this->update('tb_sys_margin_template', $update, $where);

        return $ret->num_rows;

    }

    //现货保证金三期  ---通过product 删除模板
    public function delete_templater_from_productid($seller_id,$product_id_list){
        if(!$product_id_list){
            return true;
        }
        $update = [
            'is_del'        => 1,
            'update_time'   => date('Y-m-d H:i:s'),
            'update_user'   => $seller_id
        ];
        return $this->orm->table('tb_sys_margin_template')
            ->whereIn('product_id',$product_id_list)
            ->where('is_del','=',0)
            ->update($update);
    }

    function saveMarginTemplate($seller_id,$data){
        $tableName = 'tb_sys_margin_template';
/*        $data = [
            [
                'id'    => 4,
                'bond_template_id'  => '201909020001',
                'product_id'        => 9674,
                'min_num'           => 11,
                'max_num'           => 20,
                'price'             => 12.5,
                'payment_ratio'     => 20.5/100,
                'day'               => 10,
                'is_default'        => 1
            ],
            [
                'id'    => 6,
                'bond_template_id'  => "201909020001",
                'product_id'        => 9674,
                'min_num'           => 21,
                'max_num'           => 35,
                'price'             => 12.0,
                'payment_ratio'     => 30.0/100,
                'day'               => 10,
                'is_default'        => 0
            ],
        ];*/

        //$this->db->beginTransaction();

        // 现货保证金为了适配原始程序添加
        $db_margin = $this->getProductTemplate($seller_id,$data[0]['product_id']);
        $db_margin_id_list=array_column($db_margin['template_list'] ?? [],'id');
        $get_margin_id_list=array_column($data,'id');
        $diff=array_diff($db_margin_id_list,$get_margin_id_list);
        //拼凑假数据
        foreach ($diff as $tpl_id){
            $data[]=array(
                'id'=>$tpl_id,
                'max_num'=>'',
                'price'=>'',
                'day'=>''
            );
        }
        try{
            foreach ($data as $k => $v){
                $t = date('Y-m-d H:i:s');
                if ($v['id']){//编辑
                     if (!$v['max_num'] || !$v['price'] || !$v['day'] ){//删除
                        if (1 == $k && (isset($data[2]) && $data[2]['max_num'] && $data['price'] && $data['day'] )){
                            return false;
                        }
                        $this->deleteMarginTemplates($seller_id, $v['id']);
                    }else{
                        $v = array_merge($v, [
                            'seller_id'     => $seller_id,
                            'update_time'   => $t,
                            'update_user'   => $seller_id
                        ]);

                        $ret = $this->update($tableName, $v, 'id='.$v['id']);
                        if (!$ret->num_rows){
                            //$this->db->rollback();
                            return false;
                        }
                    }

                }else{//新增
                    $no = $this->getTodayNum();
                    if (empty($no)){
                        $no = date('Ymd').'000001';
                    }else{
                        $no = $no + 1;
                    }
                    $v = array_merge($v, [
                        'is_del' => 0,
                        'template_id'   => $no,
                        'seller_id'     => $seller_id,
                        'create_time'   => $t,
                        'create_user'   => $seller_id,
                        'update_time'   => $t,
                        'update_user'   => $seller_id
                    ]);
                    $id = $this->insert($tableName, $v);
                    if (!$id){
                        //$this->db->rollback();
                        return false;
                    }
                }
                //$this->db->commit();
            }

        }catch (Exception $e){
           // $this->db->rollback();
            return false;
        }
        return true;
    }

    public function getTodayNum()
    {
        $day = date('Ymd');
        $sql = 'SELECT template_id FROM tb_sys_margin_template WHERE LEFT(template_id,8)='.$day.' ORDER BY template_id DESC limit 1';
        $query = $this->db->query($sql);
        $row = empty($query->row)?'':$query->row['template_id'];
        return $row;
    }

    public function getProductInformationForPromotion($seller_id, $mpn)
    {
        $sql = "SELECT p.`product_id`,p.`sku`,p.`mpn`,p.`quantity`,p.`price`,p.`freight`
                FROM oc_customerpartner_to_product ctp INNER JOIN oc_product p ON p.`product_id` = ctp.`product_id`
                WHERE (p.`mpn` = '" . $this->db->escape($mpn) . "' OR p.`sku` = '" . $this->db->escape($mpn) . "')"
            . " AND ctp.`customer_id` = " . (int)$seller_id
            . " AND p.`is_deleted` = 0"
            . " AND p.`status` = 1"
            . " AND p.`product_type` IN (0,3)"
            . " AND p.`buyer_flag` = 1";

        $query = $this->db->query($sql);
        return $query->rows;
    }

    //获取系统设定的保证金协议 支付比例
    public function getBondTemplateListForMarginTemplate($sellerId, $productId, $module_id)
    {
        $sql = "SELECT
                  b.`id`,
                  b.`bond_template_number` AS margin_template,
                  bpt.`bond_parameter_term` AS payment_ratio
                FROM
                  `tb_bond_template` AS b
                  INNER JOIN `tb_bond_template_term` AS btt
                    ON b.id = btt.`bond_template_id`
                  INNER JOIN `tb_bond_parameter_term` AS bpt
                    ON bpt.`bond_parameter_module_id` = btt.`bond_parameter_module_id`
                    AND btt.`bond_parameter_term_id` = bpt.`id`
                  LEFT JOIN tb_sys_margin_template mt
                    ON mt.`bond_template_id` = b.`id`
                WHERE b.`status` = 0
                  AND b.`delete_flag` = 0
                  AND bpt.`delete_flag` = 0
                  AND bpt.`bond_parameter_module_id` = ".(int)$module_id."
                  AND (
                    mt.`id` IS NULL
                    OR (
                      mt.`is_del` = 0
                      AND mt.`seller_id` = ".(int)$sellerId."
                      AND mt.`product_id` = ".(int)$productId."
                    )
                  )
                GROUP BY b.`id`,
                  b.`bond_template_number`,
                  bpt.`bond_parameter_term`,
                  mt.`id`,
                  b.`create_time`,
                  b.`update_time`
                ORDER BY mt.`id` DESC,
                  b.update_time DESC,
                  b.create_time DESC";

        $query = $this->db->query($sql);
        $rows = $query->rows;
        $ret = [];
        if(!empty($rows)){
            //SQL不好实现去重，代码去重遍历
            $keys = [];
            foreach ($rows as $row) {
                if(in_array($row['id'],$keys)){
                    continue;
                }
                $keys[] = $row['id'];
                $ret[] = $row;
            }
        }
        return $ret;
    }

    /**
     * 编辑保证金模板时，查询对应的最新有效合同信息
     * @param $module_id
     * @return array
     */
    public function getBondTemplateList($module_id)
    {
        //TODO 修改bond_parameter_module_id对应的ID
        $sql = "SELECT
                  b.`id`,
                  b.`bond_template_number` as margin_template,
                  bpt.`bond_parameter_term` AS payment_ratio
                FROM
                  `tb_bond_template` AS b
                RIGHT JOIN `tb_bond_template_term` AS btt
                  ON b.id = btt.`bond_template_id`
                LEFT JOIN `tb_bond_parameter_term` AS bpt
                  ON bpt.`bond_parameter_module_id` = btt.`bond_parameter_module_id` AND btt.`bond_parameter_term_id` = bpt.`id`
                WHERE b.`status` = 0
                  AND b.`delete_flag` = 0
                  AND bpt.`delete_flag` = 0
                  AND bpt.`bond_parameter_module_id` = $module_id"
            . " ORDER BY b.update_time DESC,b.create_time DESC";

        $query = $this->db->query($sql);
        return $query->rows;
    }

    //获取指定商品的 margin 模板，商品详情页用
    public function getMarginTemplateForProduct($product_id)
    {

        $sql = "SELECT mt.*, p.sku,mc.is_bid
                FROM `tb_sys_margin_template` AS mt
                LEFT JOIN oc_product AS p ON p.product_id=mt.product_id
                LEFT JOIN tb_sys_margin_contract AS mc ON mc.id=mt.contract_id AND mc.status=1
                WHERE mt.product_id = ?
                    AND mt.`is_del` = 0
                ORDER BY mt.min_num
                LIMIT 3";

        return $this->db->query($sql, [$product_id])->rows;
    }

    public function canDelete($seller_id,$product_id,$id)
    {
        $sql = "SELECT id FROM `tb_sys_margin_template`
                WHERE seller_id=$seller_id AND product_id=$product_id AND is_del=0 ORDER BY min_num";

        $ret = $this->db->query($sql)->rows;

        if (3 == count($ret) && $id == $ret[1]['id']){//不允许删除三个模板的中间那个
            return false;
        }

        return true;
    }

    public function getValue($arr,$key)
    {
        $value = [];
        foreach ($arr as $item){
            $value[] = $item[$key];
        }

        return $value;
    }

    public function marginProduct($productIdArr)
    {
        $sql = 'SELECT DISTINCT(product_id)
                FROM `tb_sys_margin_template`
                WHERE product_id IN ('. implode(',', $productIdArr) .') AND is_del=0 ';

        $ret = $this->db->query($sql);

        return $this->getValue($ret->rows, 'product_id');
    }

    /**
     * @param int $product_id
     * @return mixed
     */
    public function getProductBasePrice($product_id)
    {
        return $this->orm->table('oc_customerpartner_to_product')
            ->where('product_id', $product_id)
            ->value('price');
    }

}
