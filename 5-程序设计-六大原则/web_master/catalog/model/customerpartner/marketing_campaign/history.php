<?php

/**
 * Class ModelCustomerpartnerMarketingCampaignHistory
 * @property ModelToolImage $model_tool_image
 */
class ModelCustomerpartnerMarketingCampaignHistory extends Model
{
    //计算数据总数
    public function getmarketingTotal($data)
    {
        $seller_id = $data['customer_id'];

        $sql = "
    SELECT
        COUNT(mcr.id) AS cnt
    FROM oc_marketing_campaign_request AS mcr
    LEFT JOIN oc_marketing_campaign AS mc ON mc.id=mcr.mc_id
    WHERE
        mcr.seller_id={$seller_id}
        AND mc.is_release=1";

        $query = $this->db->query($sql);
        return intval($query->row['cnt']);
    }


    //获取当前分页的数据
    public function getmarketingList($data)
    {
        $start     = $data['start'];
        $limit     = $data['limit'];
        $seller_id = $data['customer_id'];

        $sql = "
    SELECT
        mcr.id
        ,mcr.mc_id
        ,mcr.seller_id
        ,mcr.`status`
        ,mcr.create_time
        ,mcr.update_time
        ,mcr.reject_reason
        ,mc.`code`
        ,mc.`name`
        ,mc.`seller_activity_name`
        ,mc.type
        ,mc.country_id
        ,mc.effective_time
        ,mc.expiration_time
        ,mc.apply_start_time
        ,mc.apply_end_time
        ,mc.seller_num
        ,mc.product_num_per
        ,mc.require_category
        ,mc.require_pro_start_time
        ,mc.require_pro_end_time
        ,mc.require_pro_min_stock
    FROM oc_marketing_campaign_request AS mcr
    LEFT JOIN oc_marketing_campaign AS mc ON mc.id=mcr.mc_id
    WHERE
        mcr.seller_id={$seller_id}
        AND mc.is_release=1
    ORDER BY mcr.create_time DESC
    LIMIT $start,$limit";

        $query = $this->db->query($sql);
        $rows  = $query->rows;

        return $rows;
    }


    /**
     * 某些请求下的 产品列表
     * @param array $mc_request_id_arr oc_marketing_campaign_request表主键
     * @return array key=mcr_id， value=产品列表
     * @throws Exception
     */
    public function getmarketingRequestProductList($mc_request_id_arr = [])
    {
        if (!$mc_request_id_arr) {
            return [];
        }


        if ($mc_request_id_arr) {
            $mc_request_id_str = implode(',', $mc_request_id_arr);
            $sql               = "
    SELECT
        mcrp.id
        ,mcrp.mc_id
        ,mcrp.mc_request_id
        ,mcrp.product_id
        ,mcrp.`status`
        ,mcrp.`approval_status`
        ,p.sku
        ,p.quantity
        ,p.image
        ,p.price
        ,pd.`name`
    FROM oc_marketing_campaign_request_product AS mcrp
    LEFT JOIN oc_product AS p ON p.product_id=mcrp.product_id
    LEFT JOIN oc_product_description AS pd ON pd.product_id=p.product_id
    WHERE
        mcrp.mc_request_id IN ({$mc_request_id_str})";

            $query = $this->db->query($sql);
            $rows  = $query->rows;


            //model\tool\image.php
            $this->load->model('tool/image');


            $results = [];
            unset($value);
            foreach ($rows as $key => $value) {
                $value['currenc_price'] = $this->currency->format($value['price'], $this->session->data['currency'], false);

                //产品图片
                $thumb = $this->model_tool_image->resize($value['image'], 40, 40);
                $value['image_url'] = $thumb;

                //产品URL
                $value['product_url'] = str_replace('&amp;', '&', $this->url->link('product/product', 'product_id=' . $value['product_id'], true));

                $results[$value['mc_request_id']][] = $value;
            }
            unset($value);

            return $results;
        }
    }


    /**
     * 列表页状态名称
     * @param $value
     * @return string
     */
    public function getStatusName($value)
    {
        //$value['expiration_time'] oc_marketing_campaign表字段
        //$value['expiration_time'] oc_marketing_campaign表字段


        $now_time = date('Y-m-d H:i:s');

        $status_name = '';

        if ($value['expiration_time'] < $now_time) {
            //$value['expiration_time'] < $now_time
            $status_name = 'Complete';
        } elseif ($value['effective_time'] <= $now_time && $now_time <= $value['expiration_time']) {
            //$value['effective_time'] < $now_time < $value['expiration_time']
            $status_name = 'In-Process';
        } else {
            //$now_time < $value['effective_time']
            $status_name = 'Not Started';
        }

        return $status_name;
    }


    /**
     * 活动中已同意的seller数量
     * @param array $mc_id_arr
     * @return array key=活动ID，value=同意的seller数量
     */
    public function getAgreeNumGroupByCampaign($mc_id_arr=[])
    {
        if (!$mc_id_arr) {
            return [];
        }

        $mc_id_str = implode(',', $mc_id_arr);
        $sql       = "
    SELECT mc.id, COUNT(mcr.id) AS cnt
    FROM oc_marketing_campaign AS mc
    JOIN oc_marketing_campaign_request AS mcr ON mcr.mc_id=mc.id
    WHERE
        mc.id IN ({$mc_id_str})
        AND mcr.`status` = 2
    GROUP BY mc.id";

        $query = $this->db->query($sql);

        $results = [];
        foreach ($query->rows AS $key => $value) {
            $results[$value['id']] = $value['cnt'];
        }

        return $results;
    }


    public function cancel($id)
    {
        $now_time = date('Y-m-d H:i:s');

        $ret = $this->orm->table('oc_marketing_campaign_request')
            ->where('id', '=', $id)
            ->where('status', '=', 1)
            ->update(
                [
                    'status'      => 4,
                    'update_time' => $now_time
                ]
            );


        return $ret;
    }


    /**
     * 是否允许取消
     * @param $value
     * @return array
     */
    public function isCanCancel($value)
    {
        //$value['status'] oc_marketing_campaign_request表字段
        //$value['expiration_time'] oc_marketing_campaign表字段
        //$value['expiration_time'] oc_marketing_campaign表字段
        //$value['apply_start_time'] oc_marketing_campaign表字段
        //$value['apply_end_time'] oc_marketing_campaign表字段


        $now_time = date('Y-m-d H:i:s');
        //待审核状态下显示此按钮
        if ($value['status'] == 1) {
            if ($value['apply_start_time'] <= $now_time && $now_time <= $value['apply_end_time']) {
                return ['ret' => 1, 'msg' => 'ok'];
            } else {
                //不在有效的申请时间内
                return ['ret' => 0, 'msg' => 'Time Error'];
            }

        } else {
            return ['ret' => 0, 'msg' => 'Status has been changed. <br>This promotion cannot be canceled.'];
        }
    }


    /**
     * 是否允许重新申请
     * @param $value
     * @param $agree_num 某活动已同意的seller数量
     * @return array
     */
    public function isCanReapplied($value, $agree_num=0)
    {
        //$value['mc_id'] oc_marketing_campaign_request表字段
        //$value['status'] oc_marketing_campaign_request表字段
        //$value['seller_num'] oc_marketing_campaign表字段
        //$value['expiration_time'] oc_marketing_campaign表字段
        //$value['expiration_time'] oc_marketing_campaign表字段
        //$value['apply_start_time'] oc_marketing_campaign表字段
        //$value['apply_end_time'] oc_marketing_campaign表字段


        //规则
        //在活动申请时间范围内
        //活动的申请人数
        //该seller 对该活动 的申请状态是 已拒绝和已取消状态，待处理和已通过 则不显示此按钮


        $now_time = date('Y-m-d H:i:s');
        //已驳回状态下显示此按钮
        if (!in_array($value['status'], [1, 2, 4])) {
            if ($value['apply_start_time'] <= $now_time && $now_time <= $value['apply_end_time']) {
                if ($value['seller_num'] > $agree_num) {

                    //seller对该活动， 状态不是 待审核、审核同意 的数量大于0，则可以重新申请
                    $mc_id     = intval($value['mc_id']);
                    $seller_id = intval($value['seller_id']);
                    if ($mc_id > 0) {
                        $sql   = "
    SELECT COUNT(id) AS cnt
    FROM oc_marketing_campaign_request AS mcr
    WHERE
        mcr.mc_id={$mc_id}
        AND mcr.seller_id={$seller_id}
        AND mcr.status NOT IN (1, 2, 4)";
                        $query = $this->db->query($sql);
                        $cnt   = $query->row['cnt'];

                        if ($cnt > 0) {
                            return ['ret' => 1, 'msg' => 'ok'];
                        } else {
                            return ['ret' => 0, 'msg' => 'No Error'];
                        }
                    } else {
                        return ['ret' => 0, 'msg' => 'Error ID!'];
                    }

                } else {
                    //参加的活动人数已达上限
                    return ['ret' => 0, 'msg' => 'The number of participants has reached limit when sign up: The activity vacancies is full.'];
                }
            } else {
                //不在有效的申请时间内
                return ['ret' => 0, 'msg' => 'Time Error'];
            }
        } else {
            return ['ret' => 0, 'msg' => 'Status has been changed. <br>This promotion cannot be reapplied.'];
        }
    }


    public function getRequestInfo($id)
    {
        $campaign_request_info = $this->orm->table('oc_marketing_campaign_request AS mcr')
            ->join('oc_marketing_campaign AS mc', 'mc.id', '=', 'mcr.mc_id')
            ->select(["mcr.id",
                "mcr.mc_id",
                "mcr.seller_id",
                "mcr.status",
                "mcr.create_time",
                "mcr.update_time",
                "mc.code",
                "mc.name",
                "mc.type",
                "mc.country_id",
                "mc.effective_time",
                "mc.expiration_time",
                "mc.apply_start_time",
                "mc.apply_end_time",
                "mc.seller_num",
                "mc.product_num_per",
                "mc.require_category",
                "mc.require_pro_start_time",
                "mc.require_pro_end_time",
                "mc.require_pro_min_stock"])
            ->where('mcr.id', '=', $id)
            ->where('mc.is_release', '=', 1)
            ->first();

        $campaign_request_info = obj2array($campaign_request_info);
        if (!$campaign_request_info) {
            return [];
        }

        $mc_id = intval($campaign_request_info['mc_id']);
        if ($mc_id < 1) {
            return [];
        }



        $request_product = $this->orm->table('oc_marketing_campaign_request_product')
            ->where('mc_request_id', '=', $id)
            ->where('mc_id', '=', $mc_id)
            ->where('status', '=', 1)
            ->get();

        $request_product                          = obj2array($request_product);
        $campaign_request_info['request_product'] = $request_product;



        return $campaign_request_info;
    }

    /**
     * @param int $seller_id
     * @return int
     */
    public function getNoticeNumber($seller_id):int
    {
        $now = date('Y-m-d H:i:s');
        $objs = $this->orm->table('oc_marketing_campaign as mc')
            ->join('oc_marketing_campaign_request as mcr', 'mcr.mc_id', '=', 'mc.id')
            ->where([
                ['mcr.seller_id', '=', $seller_id],
                ['mc.apply_start_time', '<=', $now],
                ['mc.apply_end_time', '>', $now],
                ['mc.is_release', '=', 1],
            ])
            ->select([
                'mc.id', 'mcr.status', 'mcr.create_time'
            ])
            ->get();
        $id_status = [];
        foreach ($objs as $obj) {
            $id_status[$obj->id][] = [
                'status' => $obj->status,
                'create_time' => $obj->create_time,
            ];
        }

        $num = 0;
        foreach ($id_status as $item) {
            $temp_time = 0;
            $temp_status = null;
            foreach ($item as $k) {
                if ($k['create_time'] > $temp_time) {
                    $temp_time = $k['create_time'];
                    $temp_status = $k['status'];
                }
            }
            if ($temp_status == 3) {
                $num++;
            }
        }
        return $num;
    }
}
