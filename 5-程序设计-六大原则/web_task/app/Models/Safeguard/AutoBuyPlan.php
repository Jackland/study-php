<?php

namespace App\Models\Safeguard;

use App\Models\Message\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AutoBuyPlan extends Model
{
    const EFFECTIVE = 1; // 生效

    /**
     * 根据
     * @param int $countryId
     * @param string $expiredNowDate
     * @param string $expiredPreSevenDate
     * @throws \Exception
     */
    public static function getExpiredPlanByCountryAndDate(int $countryId, string $expiredNowDate, string $expiredPreSevenDate)
    {
        $res = DB::table('oc_safeguard_auto_buy_plan_detail as pd')
            ->join('oc_safeguard_auto_buy_plan as p', 'p.id', '=', 'pd.plan_id')
            ->join('oc_customer as c', 'p.buyer_id', '=', 'c.customer_id')
            ->where('c.country_id', '=', $countryId)
            ->where('p.status', '=', self::EFFECTIVE)
            ->where(function ($query) use ($expiredPreSevenDate, $expiredNowDate) {
                $query->where('pd.expiration_time', '=', $expiredPreSevenDate)
                    ->orWhere('pd.expiration_time', '=', $expiredNowDate);
            })
            ->select([
                'pd.id',
                'pd.plan_id',
                'pd.expiration_time',
                'p.buyer_id',
            ])
            ->get();
        foreach ($res as $item) {
            echo $item->id, ',';
            $day = $item->expiration_time == $expiredPreSevenDate ? 7 : 1;
            $subject = ' [' . $day . ' days left to expire] Auto-purchase of Protection Service';
            $url = 'index.php?route=account/safeguard/bill#tab_auto_buy_plan';

            $content = 'The auto-purchase plan of Protection Service you set has ' . $day . ' days left to expire. By that time, the system will not automatically purchase Protection Service for your orders within the coverage of auto-purchase plan';
            $content .= '<br>If you\'d like to continue to purchase this Protection Service, please <a href="' . $url . '" target="_blank">access the \'Settings for API Users\'</a>';
            Message::addSystemMessage('other', $subject, $content, $item->buyer_id);
        }
        echo PHP_EOL;
    }
}