<?php

namespace App\Models\MarketingCampaign;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Class MarketingCampaign
 * @package App\Models\MarketingCampaign
 */
class MarketingCampaign extends Model
{
    /**
     * 遍历 所有可以报名且未通知seller的促销活动
     *
     * @return \Illuminate\Support\Collection
     */
    public function getUnNotifyActivity()
    {
        $now_date = date('Y-m-d H:i:s');
        return DB::connection('mysql_proxy')->table('oc_marketing_campaign as mc')
            ->join('oc_country as cou', 'cou.country_id', '=', 'mc.country_id')
            ->select([
                'mc.id', 'mc.name', 'mc.type', 'mc.country_id', 'mc.seller_activity_name',
                'mc.effective_time', 'mc.expiration_time', 'mc.apply_start_time', 'mc.apply_end_time',
                'mc.seller_num', 'mc.product_num_per', 'mc.require_category',
                'mc.require_pro_start_time', 'mc.require_pro_end_time',
                'mc.require_pro_min_stock', 'mc.description', 'mc.require_pro_start_time',
                'cou.name as country_name',
                'cou.iso_code_3 as country_code',
            ])
            ->where([
                ['mc.is_release', '=', '1'],
                ['mc.is_noticed', '=', '0'],
                ['mc.is_send', '=', '1'],     //#6683  要发送站内信的才发送
                ['mc.apply_start_time', '<=', $now_date],
                ['mc.apply_end_time', '>', $now_date],
            ])
            ->get();
    }

    /**
     * 根据国别获取对应的seller
     *
     * @param int $country_id
     * @return \Illuminate\Support\Collection
     */
    public function getSellersByCountry($country_id)
    {
        return DB::connection('mysql_proxy')->table('oc_customer as c')
            ->join('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'c.customer_id')
            ->select([
                'c.customer_id',
            ])
            ->where([
                ['c.status', '=', 1],
                ['c.country_id', '=', $country_id],
            ])
            ->get();
    }

    /**
     * 活动设为已通知
     *
     * @param int $mc_id
     */
    public function setNoticed($mc_id)
    {
        DB::connection('mysql_proxy')->table('oc_marketing_campaign')
            ->where([
                ['id', '=', $mc_id],
                ['is_noticed', '=', 0]
            ])
            ->update([
                'is_noticed' => 1,
                'update_time' => date('Y-m-d H:i:s')
            ]);
    }


}
