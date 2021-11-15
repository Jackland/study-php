<?php

/**
 * Class ModelExtensionModuleShipmentTime
 * Created by IntelliJ IDEA.
 * User: Administrator
 * Date: 2019/4/23
 * Time: 15:14
 */
class ModelExtensionModuleShipmentTime extends Model
{
    public function createTableShipmentTime()
    {
        $this->db->query("CREATE TABLE `oc_shipment_time` (
                                  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增主键',
                                  `country_id` int(11) NOT NULL COMMENT '国家ID',
                                  `page_description` text CHARACTER SET latin1 COMMENT '页面信息',
                                  `title` varchar(5000) CHARACTER SET latin1 DEFAULT NULL COMMENT '标题,用户首页跑马灯标题显示',
                                  `file_path` varchar(300) DEFAULT NULL COMMENT '文件路径',
                                  `file_name` varchar(300) DEFAULT NULL COMMENT '文件名称',
                                  `memo` varchar(2000) CHARACTER SET latin1 DEFAULT NULL COMMENT '备注',
                                  `create_user_name` varchar(30) CHARACTER SET latin1 DEFAULT NULL COMMENT '创建者',
                                  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                                  `update_user_name` varchar(30) CHARACTER SET latin1 DEFAULT NULL COMMENT '修改者',
                                  `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                                  PRIMARY KEY (`id`),
                                  UNIQUE KEY `country_id` (`country_id`)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("ALTER TABLE `oc_shipment_time` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");
    }

    public function getShipmentTime()
    {
        $result = $this->orm::table(DB_PREFIX . 'shipment_time')->get();
        return $result;
    }

    public function deleteShipmentFile($countryId)
    {
        $obj = $this->orm::table(DB_PREFIX . 'shipment_time')->where('country_id', $countryId)->first();
        if ($obj != null) {
            $this->orm::table(DB_PREFIX . 'shipment_time')->where('country_id', $countryId)
                ->update([
                    'file_path' => null,
                    'file_name' => null
                ]);
        }
    }

    public function getShipmentTimeById($country_id)
    {
        $result = $this->orm::table(DB_PREFIX . 'shipment_time')
            ->where('country_id', $country_id)
            ->first();
        return $result;
    }

    public function saveOrUpdateShipmentTime($dataArray)
    {
        if ($dataArray && count($dataArray) > 0) {
            foreach ($dataArray as $data) {
                // 根据CountryId获取对象信息
                $shipmentTimeData = $this->orm::table(DB_PREFIX . 'shipment_time')
                    ->where('country_id', $data['country_id'])->first();
                if ($data['file_update']) {
                    if ($shipmentTimeData != null) {
                        $this->orm::table(DB_PREFIX . 'shipment_time')->where('country_id', $data['country_id'])
                            ->update([
                                'page_description' => $data['page_description'],
                                'title' => $data['title'],
                                'file_path' => $data['file_path'],
                                'file_name' => $data['file_name'],
                                'update_user_name' => $data['update_user_name'],
                                'update_time' => $data['update_time']
                            ]);
                    } else {
                        // 插入数据
                        $this->orm::table(DB_PREFIX . 'shipment_time')->insert([
                            'country_id' => $data['country_id'],
                            'page_description' => $data['page_description'],
                            'title' => $data['title'],
                            'file_path' => $data['file_path'],
                            'file_name' => $data['file_name'],
                            'create_user_name' => $data['create_user_name'],
                            'create_time' => $data['create_time']
                        ]);
                    }
                } else {
                    if ($shipmentTimeData != null) {
                        $this->orm::table(DB_PREFIX . 'shipment_time')->where('country_id', $data['country_id'])
                            ->update([
                                'page_description' => $data['page_description'],
                                'title' => $data['title'],
                                'update_user_name' => $data['update_user_name'],
                                'update_time' => $data['update_time']
                            ]);
                    } else {
                        // 插入数据
                        $this->orm::table(DB_PREFIX . 'shipment_time')->insert([
                            'country_id' => $data['country_id'],
                            'page_description' => $data['page_description'],
                            'title' => $data['title'],
                            'create_user_name' => $data['create_user_name'],
                            'create_time' => $data['create_time']
                        ]);
                    }
                }
            }
        }
    }

    public function dropTableShipmentTime()
    {
        $this->db->query("DROP TABLE `oc_shipment_time`");
    }
}
