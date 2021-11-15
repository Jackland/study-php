<?php

/**
 * Class ModelCheckoutCwfInfo
 */
class ModelCheckoutCwfInfo extends Model
{
    public function get_zone($country_id)
    {
        $res = $this->orm->table(DB_PREFIX . 'zone')
            ->where('country_id', '=', $country_id)
            ->get(['zone_id', 'name', 'code']);
        return obj2array($res);
    }

    //保存cwf info
    public function save_cwf_info($data)
    {
        if (!$data) {
            return array(0,100);
        }
        //处理
        $this->orm->getConnection()->beginTransaction();
        $curr_time = date('Y-m-d H:i:s', time());
        try {
            $is_fba = ($data['server_type'] != 'Others');
            //            if ($is_fba && $data['bill_info_file']) {    //FBA
            //                //101843 运送仓三期，这里已经不用传这个图片了，可以考虑删除这段代码
            //                //处理team lift
            //                $team_lift = array(
            //                    'file_name' => $data['bill_info_file']['file_name'],
            //                    'file_path' => $data['bill_info_file']['file_new_path'],
            //                    'file_type' => $data['bill_info_file']['file_type'],
            //                    'create_user_name' => $this->customer->getId(),
            //                    'create_time' => $curr_time,
            //                    'update_user_name' => $this->customer->getId(),
            //                    'update_time' => $curr_time,
            //                );
            //                $team_lift_id = $this->orm->table(DB_PREFIX . 'order_cloud_logistics_file')->insertGetId($team_lift);
            //                if (!$team_lift_id) {
            //                    $this->orm->getConnection()->rollBack();
            //                    return array(0,101);
            //                }
            //            } else {
            //                $team_lift_id = 0;
            //            }
            $pallet_label_file_id = 0;
            //101843 运送仓三期 这里传pallet label
            if ($is_fba && $data['pallet_label_file']) {    //FBA
                //处理pallet label
                $team_lift = array(
                    'file_name'        => $data['pallet_label_file']['file_name'],
                    'file_path'        => $data['pallet_label_file']['file_new_path'],
                    'file_type'        => $data['pallet_label_file']['file_type'],
                    'create_user_name' => $this->customer->getId(),
                    'create_time'      => $curr_time,
                    'update_user_name' => $this->customer->getId(),
                    'update_time'      => $curr_time,
                );
                $pallet_label_file_id = $this->orm->table(DB_PREFIX . 'order_cloud_logistics_file')
                    ->insertGetId($team_lift);
                if (!$pallet_label_file_id) {
                    $this->orm->getConnection()
                        ->rollBack();
                    return array(0, 101);
                }
            }
            //处理总表
            $cwf = array(
                'buyer_id' => $this->customer->getId(),
                'service_type' => $is_fba ? 1 : 0,
                'has_dock' => ($data['hasLoadingDock'] != 'Yes') ? 0 : 1,
                'recipient' => $data['recipient'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'],
                'country' =>$data['united'],
                'state' => $data['state'],
                'city' => $data['city'],
                'zip_code' => $data['zip'],
                'comments' => $data['comments'],
                'fba_shipment_code' => $is_fba ? $data['shipment_id'] : 0,
                'fba_reference_code' => $is_fba ? $data['amazon_red_id'] : 0,
                'fba_po_code' => 0,//废弃
                'fba_warehouse_code' => $is_fba ? $data['fba_warehouse_code'] : 0,
                'fba_amazon_reference_number' => $is_fba ? $data['fba_amazon_reference_number'] : 0,
                'team_lift_file_id' => 0,    //废弃字段
                'pallet_label_file_id' => $pallet_label_file_id,    //上面已经赋值0
                'create_user_name' => $this->customer->getId(),
                'create_time' => $curr_time,
                'update_user_name' => $this->customer->getId(),
                'update_time' => $curr_time,
            );
            $cwf_id = $this->orm->table(DB_PREFIX . 'order_cloud_logistics')->insertGetId($cwf);
            if (!$cwf_id) {
                $this->orm->getConnection()->rollBack();
                return array(0,102);
            }
            //处理attachments
            if(!empty($data['attachments'])){
                foreach ($data['attachments'] as $k => $v) {
                    $tmp_atta_file = array(
                        'file_name' => $v['file_name'],
                        'file_path' => $v['file_new_path'],
                        'file_type' => $v['file_type'],
                        'create_user_name' => $this->customer->getId(),
                        'create_time' => $curr_time,
                        'update_user_name' => $this->customer->getId(),
                        'update_time' => $curr_time,
                    );
                    $sub_atta_file = $this->orm->table(DB_PREFIX . 'order_cloud_logistics_file')->insertGetId($tmp_atta_file);
                    if (!$sub_atta_file) {
                        $this->orm->getConnection()->rollBack();
                        return array(0,103);
                    }
                    //处理 oc_order_cloud_logistics_attachment 表
                    $tmp_atta_info = array(
                        'cloud_logistics_id' => $cwf_id,
                        'cloud_logistics_file_id' => $sub_atta_file,
                        'buyer_id' => $this->customer->getId()
                    );
                    $sub_atta_info = $this->orm->table(DB_PREFIX . 'order_cloud_logistics_attachment')->insertGetId($tmp_atta_info);
                    if (!$sub_atta_info) {
                        $this->orm->getConnection()->rollBack();
                        return array(0,104);
                    }
                }
            }
            //table
            if ($is_fba) {    //FBA
                foreach ($data['table'] as $k => $v) {
                    $package_file = array(
                        'file_name' => $v['package_file_info']['file_name'],
                        'file_path' => $v['package_file_info']['file_new_path'],
                        'file_type' => $v['package_file_info']['file_type'],
                        'create_user_name' => $this->customer->getId(),
                        'create_time' => $curr_time,
                        'update_user_name' => $this->customer->getId(),
                        'update_time' => $curr_time,
                    );
                    $package_file_id = $this->orm->table(DB_PREFIX . 'order_cloud_logistics_file')->insertGetId($package_file);
                    if (!$package_file_id) {
                        $this->orm->getConnection()->rollBack();
                        return array(0,105);
                    }
                    $product_file = array(
                        'file_name' => $v['product_file_info']['file_name'],
                        'file_path' => $v['product_file_info']['file_new_path'],
                        'file_type' => $v['product_file_info']['file_type'],
                        'create_user_name' => $this->customer->getId(),
                        'create_time' => $curr_time,
                        'update_user_name' => $this->customer->getId(),
                        'update_time' => $curr_time,
                    );
                    $product_file_id = $this->orm->table(DB_PREFIX . 'order_cloud_logistics_file')->insertGetId($product_file);
                    if (!$product_file_id) {
                        $this->orm->getConnection()->rollBack();
                        return array(0,106);
                    }
                    //处理item
                    $item = array(
                        'cloud_logistics_id' => $cwf_id,
                        'product_id' => $v['product_id'],
                        'item_code' => $v['sku'],
                        'merchant_sku' => $v['mer_sku'],
                        'fn_sku' => $v['fn_sku'],
                        'seller_id' => $v['seller_id'],
                        'qty' => $v['qty'],
                        'package_label_file_id' => $package_file_id,
                        'product_label_file_id' => $product_file_id,
                        'team_lift_status' => (int)$v['team_lift_status'],
                        'create_user_name' => $this->customer->getId(),
                        'create_time' => $curr_time,
                        'update_user_name' => $this->customer->getId(),
                        'update_time' => $curr_time,
                    );
                    $item_id = $this->orm->table(DB_PREFIX . 'order_cloud_logistics_item')->insertGetId($item);
                    if (!$item_id) {
                        $this->orm->getConnection()->rollBack();
                        return array(0,107);
                    }
                }
            }else{
                foreach ($data['table'] as $k => $v){
                    $item = array(
                        'cloud_logistics_id' => $cwf_id,
                        'product_id' => $v['product_id'],
                        'item_code' => $v['item_code'],
                        'seller_id' => $v['seller_id'],
                        'qty' => $v['qty'],
                        'create_user_name' => $this->customer->getId(),
                        'create_time' => $curr_time,
                        'update_user_name' => $this->customer->getId(),
                        'update_time' => $curr_time,
                    );
                    $item_id = $this->orm->table(DB_PREFIX . 'order_cloud_logistics_item')->insertGetId($item);
                    if (!$item_id) {
                        $this->orm->getConnection()->rollBack();
                        return array(0,107);
                    }
                }
            }
            //over
            $this->orm->getConnection()->commit();
            //将生成的头表的ID放入session,用于订单生成时的采购订单和销售订单的关联
            $this->session->remove('cwf_id');
            session()->set('cwf_id', $cwf_id);
            return array(1);
        } catch (Exception $e) {
            $this->orm->getConnection()->rollBack();
            return array(0,108);
        }
    }

    //获取购物车产品的库存
    public function get_quantity($product_id_list)
    {
        $res = $this->orm->table(DB_PREFIX . 'product')
            ->whereIn('product_id', $product_id_list)
            ->get(['product_id', 'quantity']);
        $res=obj2array($res);
        return array_combine(array_column($res,'product_id'),array_column($res,'quantity'));
    }

    /**
     * 根据云送仓的头表Id,获取该云送仓订单的明细
     * @author xxl
     * @param int $cloud_logistics_id
     * @return array
     */
    public function getCloudLogisticsItems($cloud_logistics_id){
        $res = $this->orm->table(DB_PREFIX.'order_cloud_logistics_item')
            ->where('cloud_logistics_id','=',$cloud_logistics_id)
            ->select('product_id','qty as quantity')
            ->get();
        return obj2array($res);
    }

    /**
     * 判断指定用户是否需要弹出运送仓手册
     *
     * @param int $buyerId buyerId
     *
     *                如果不需要弹出返回false
     *                如果需要弹出返回true并且附带弹窗内容（格式如下）
     *
     * @return array ['show'=>true|false,guide_data=> null|['title','content','imgs'=>array]]
     */
    public function checkCloudGuideByBuyer($buyerId)
    {
        $buyerId = $buyerId ?? 0;
        //手册内容
        //**如果有了新的手册内容，需要重置这个转态，sql：UPDATE `oc_buyer` SET cloud_guide_status = 0
        $hostUrl = get_https_header().HOST_NAME;
        $guideData = [
            'title'   => 'Label Uploading Guide',
            'content' => "On the print window, please select the ‘Plain Paper' option under Paper Type to print your uploaded package/pallet labels as shown in the illustration below:",
            'imgs'    => [
                $hostUrl . '/image/catalog/cloudWholesaleFulfillment/guide-2.png',
                $hostUrl . '/image/catalog/cloudWholesaleFulfillment/guide-1.png',
            ],
        ];
        //初始化返回内容，默认不显示
        $return = [
            'show' => false
        ];
        $buyer = $this->orm->table(DB_PREFIX . 'buyer')
            ->where('buyer_id', $buyerId)
            ->first(['cloud_guide_status']);
        if (!$buyer || $buyer->cloud_guide_status !== 1) {
            //需要显示
            $return['show'] = true;
            $return['guide_data'] = $guideData;
        }
        return $return;
    }

    /**
     * 设置运送仓手册已读
     *
     * @param int $buyerId buyerId
     *
     * @return bool
     */
    public function readCloudGuide($buyerId)
    {
        if (!$buyerId) {
            return false;
        }
        //目前不考虑这张表不存在该buyer信息的情况，如果没有就一直提示
        $this->orm->table(DB_PREFIX . 'buyer')
            ->where('buyer_id', $buyerId)
            ->update(['cloud_guide_status' => 1]);
        return true;
    }
}