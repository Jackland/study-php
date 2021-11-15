<?php

/**
 * Class ModelAccountCustomerpartnerMargin
 */
class ModelCustomerpartnerMarketingCampaignIndex extends Model
{
    /**
     * seller国别下,报名时间段内已发布的促销活动信息列表与总量
     * @param array $data seller信息与分页等信息
     * @return array $result  total=>数据的个数，list=>每个数据列表, cate_name_id_list=>分类名
     */
    public function getMarketingData($data)
    {
        $date_now = date('Y-m-d H:i:s', time());
        $res = $this->orm->table(DB_PREFIX . 'marketing_campaign as m')
            ->select([
                'm.id',
                'm.name',
                'm.seller_activity_name',
                'm.type',
                'm.country_id',
                'm.effective_time',
                'm.require_category',
                'm.expiration_time',
                'm.apply_start_time',
                'm.apply_end_time',
                'm.seller_num',
                'm.product_num_per',
                'm.require_pro_start_time',
                'm.require_pro_min_stock',
                'm.description',
                'm.require_pro_end_time'
            ])
            ->where('m.country_id', '=', $data['country_id'])
            ->where('m.is_release', '=', '1')
            ->where('m.apply_start_time', '<', $date_now)
            ->where('m.apply_end_time', '>', $date_now);
        //总记录
        $result['total'] = $res->count();
        //分页
        $list = $res->orderBy('m.create_time', 'desc')
            ->forPage($data['start'], $data['limit'])
            ->get();
        $result['list'] = obj2array($list);

        $result['cate_name_id_list'] = [];
        if ($result['total'] > 0) {
            //分类去重复
            $cate_list = array_column($result['list'], 'require_category');
            $cate_unique_list = array_unique($cate_list);
            $cate_str = implode(',', $cate_unique_list);
            //获取分类名
            $result['cate_name_id_list'] = $this->getCateNameList($cate_str);

            //促销商品报名
            $id_list = array_column($result['list'], 'id');
        }
        return $result;
    }

    /**
     * 获取当前页的所有商品的分类名
     * @param string $cate_str 分类id,可多个，用逗号隔开
     * @return array  key=>分类id  value=>分类名
     */
    public function getCateNameList($cate_str)
    {
        if (trim($cate_str)) {
            $cate_list = array_unique(explode(',', $cate_str));
            $res = $this->orm->table(DB_PREFIX . 'category_description as c')
                ->select(['c.name', 'c.category_id'])
                ->whereIn('c.category_id', $cate_list)
                ->get();
            $res = obj2array($res);
            $cate_name_id_list = array_column($res, 'name', 'category_id');
            return $cate_name_id_list;
        }
        return [];
    }

    /**
     * 获取单个商品分类名
     * @param string $require_category 需要的分类，可多个，用逗号隔开
     * @param array $cate_name_id_list 所有的分类
     * @return string 分类名
     */
    public function getCateName($require_category, $cate_name_id_list)
    {
        $cate_name = '';
        if ($require_category) {
            $require_category = explode(',', $require_category);
            foreach ($require_category as $value) {
                if (isset($cate_name_id_list[$value])) {
                    $cate_name = $cate_name . ',' . $cate_name_id_list[$value];
                }
            }
        }
        return trim($cate_name, ',');
    }

    /**
     * seller与某个促销活动的报名关系
     * @param int $seller_id seller_id
     * @param int $id 活动的ID
     * @param int $num 活动可报的商家数
     * @return int $status 1报名中 2已报名 3已报满
     */
    public function judgeStatus($seller_id, $id, $num)
    {
        $status = 1;
        //已审核通过的商家数
        $res = $this->orm->table(DB_PREFIX . 'marketing_campaign_request as m')
            ->selectRaw('m.seller_id,m.status')
            ->where('m.mc_id', '=', $id)
            ->whereIn('m.status', [1, 2])
            ->get();
        $res = obj2array($res);

        //已审核的商家数
        $count = 0;
        foreach ($res as $value) {
            //已报名
            if ($value['seller_id'] == $seller_id) {
                $status = 2;
                break;
            }
            if ($value['status'] == 2) {
                $count++;
            }
        }
        //已报满
        if ($num == $count || $num < $count) {
            $status = 3;
        }
        return $status;
    }

    /**
     * 距离报名结束的倒计时
     * @param string $endDate 报名结束时间  Y-m-d H:i:s
     * @return string 返回拼接好的倒计时 1day(s) H:i:s
     */
    public function countDown($endDate)
    {
        $date_time = time();
        $end_time = strtotime($endDate);

        if ($date_time < $end_time) {
            $second = $end_time - $date_time; //结束时间戳减去当前时间戳
            // echo $second;
            $day = floor($second / 3600 / 24);    //倒计时还有多少天
            $hr = floor($second / 3600 % 24);     //倒计时还有多少小时（%取余数）
            $min = floor($second / 60 % 60);      //倒计时还有多少分钟
            $sec = floor($second % 60);         //倒计时还有多少秒
            if($hr<10){
                $hr='0'.$hr;
            }
            if($min<10){
                $min='0'.$min;
            }
            if($sec<10){
                $sec='0'.$sec;
            }
            $str = $day . "day(s) " . $hr . ':' . $min . ':' . $sec;  //组合成字符串
            return $str;
        }
        return '';
    }
}
