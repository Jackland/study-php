<?php

namespace App\Models\Future;

use Illuminate\Database\Eloquent\Model;

class MarginProcess extends Model
{
    protected $table = 'oc_futures_margin_process';
    public $timestamps = false;
    protected $connection = 'mysql_proxy';

    /**
     * Buyer支付期货尾款，需要在30个自然日内完成尾款支付，如果在如果在30个自然日内未支付转现货保证金定金，判定Buyer违约（delivery status = Unexectued）
     * @return mixed
     */
    public static function getProcessTimeOut()
    {
        $time = date('Y-m-d H:i:s', strtotime('- 30 day'));
        return \DB::connection('mysql_proxy')
            ->table('oc_futures_margin_process as p')
            ->select('d.agreement_id')
            ->leftJoin('oc_futures_margin_delivery as d', 'p.agreement_id', '=', 'd.agreement_id')
            ->leftJoin('oc_futures_margin_agreement as a','d.agreement_id','=','a.id')
            ->where('p.process_status', '!=', 4)
            ->where('d.confirm_delivery_date', '<', $time)
            ->where('a.contract_id','=',0) // 期货老版数据
            ->get()
            ->pluck('agreement_id')
            ->toArray();
    }

    /**
     * 找到期货协议对应的定金头款产品id
     * @param $agreement_ids
     * @return mixed
     */
    public static function getMarginProductIds($agreement_ids)
    {
        // 找到$product_ids对应的定金头款产品id
        return self::select('advance_product_id')
            ->whereIn('agreement_id', $agreement_ids)
            ->get()
            ->pluck('advance_product_id')
            ->toArray();
    }
}