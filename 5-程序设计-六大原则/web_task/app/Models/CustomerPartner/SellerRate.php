<?php

namespace App\Models\CustomerPartner;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SellerRate extends Model
{
    //更新店铺退返率
    public function updateReturnRate()
    {
        $msg = date('Y-m-d H:i:s') . ' Seller:ReturnRate Seller店铺退返品率 更新开始......' . PHP_EOL;
        echo $msg;
        \Log::info($msg);

        $num   = \DB::connection('mysql_proxy')->table('oc_customerpartner_to_customer')->count();
        $index = 0;
        while ($index < $num) {
            $objs = \DB::connection('mysql_proxy')->table('oc_customerpartner_to_customer')
                ->select('customer_id')
                ->where([
                    ['is_partner', '=', 1]
                ])
                ->offset($index)
                ->limit(1)
                ->get()
                ->toArray();
            if ($objs) {
                $customer_id = $objs[0]->customer_id;
                $this->returnRate(($customer_id));
            }
            $index++;
        }

        $msg = date('Y-m-d H:i:s') . ' Seller:ReturnRate Seller店铺退返品率 更新成功' . PHP_EOL;
        echo $msg;
        \Log::info($msg);
    }



    /**
     * 店铺退返品率
     * @param $seller_id
     * @return bool
     */
    public function returnRate($seller_id)
    {

        $sql = "
    SELECT 
        IFNULL(SUM(pc.purchase_num), 0) AS purchase_num
        ,IFNULL(SUM(pc.return_num), 0) AS return_num
        ,c2p.customer_id
    FROM oc_product_crontab AS pc 
    LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=pc.product_id
    WHERE c2p.customer_id={$seller_id}
    GROUP BY c2p.customer_id";

        $query = \DB::connection('mysql_proxy')->select($sql);
        $obj   = isset($query[0]) ? $query[0] : [];

        $purchase_num = 0;
        $return_num   = 0;

        if ($obj) {
            $purchase_num = $obj->purchase_num;
            $return_num   = $obj->return_num;
        }



        $rate = 0;
        if ($purchase_num <= 10) {
            //若总销量小于10，则保存为-1；
            $rate = -1;
        } else {
            $rate = round(100 * $return_num / $purchase_num, 2);//2.97表示2.97%
        }


        //保存数据库
        \DB::connection('mysql_proxy')->table('oc_customerpartner_to_customer')
            ->where([
                ['customer_id', '=', $seller_id]
            ])
            ->update([
                'returns_rate'               => $rate,
                'returns_rate_date_modified' => date('Y-m-d H:i:s', time()),
            ]);

        return true;
    }


    /**
     * 店铺回复率
     * @param $seller_id
     * @return bool
     */
    public function responseRate($seller_id)
    {
        $day = 90;

        //店铺接收到message的总量：近90天内，，buyer发送给seller的message总个数；
        //包含建立连接申请的message；
        $sql = "SELECT COUNT(m.id) AS cnt
    FROM oc_message m
    LEFT JOIN oc_message_content mc ON mc.id=m.message_id
    WHERE m.receiver_id={$seller_id}
        AND m.user_type=1
        AND mc.parent_id=0
        AND m.send_id > 0
        AND m.send_id NOT IN (
            SELECT c2c.customer_id FROM oc_customerpartner_to_customer AS c2c WHERE c2c.customer_id=m.send_id
        )
        AND TIMESTAMPDIFF(DAY, m.create_time, NOW()) <= {$day}
        AND TIMESTAMPDIFF(DAY, m.create_time, NOW()) >= 0";

        $query              = \DB::connection('mysql_proxy')->select($sql);
        $receiver_count_obj = isset($query[0]) ? $query[0] : [];
        $receiver_count     = 0;
        if ($receiver_count_obj && isset($receiver_count_obj->cnt)) {
            $receiver_count = $receiver_count_obj->cnt;
        }


        //店铺（seller）message的回复数量：近90天内，对buyer发送的message ， seller收到后给予回复的message个数；
        $sql = "SELECT COUNT(m.id) AS cnt
    FROM oc_message m
    LEFT JOIN oc_message_content mc ON mc.id=m.message_id
    WHERE m.send_id={$seller_id}
        AND m.user_type=0
        AND mc.parent_id > 0
        AND m.receiver_id > 0
        AND TIMESTAMPDIFF(DAY, m.create_time, NOW()) <= {$day}
        AND TIMESTAMPDIFF(DAY, m.create_time, NOW()) >= 0";

        $query              = \DB::connection('mysql_proxy')->select($sql);
        $response_count_obj = isset($query[0]) ? $query[0] : [];
        $response_count     = 0;
        if ($response_count_obj && isset($response_count_obj->cnt)) {
            $response_count = $response_count_obj->cnt;
        }

        $rate = 0;
        if ($receiver_count > 0) {
            $rate = round(100 * $response_count / $receiver_count, 2);//2.97表示2.97%
        } else {
            //若message量为0，则保存为-1
            $rate = -1;
        }

        //保存数据库
        \DB::connection('mysql_proxy')->table('oc_customerpartner_to_customer')
            ->where([
                ['customer_id', '=', $seller_id]
            ])
            ->update([
                'response_rate'               => $rate,
                'response_rate_date_modified' => date('Y-m-d H:i:s', time()),
            ]);

        return true;
    }


    public function obj2array($obj)
    {
        if (empty($obj)) return [];
        return json_decode(json_encode($obj), true);
    }
}
