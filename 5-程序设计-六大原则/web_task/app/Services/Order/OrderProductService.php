<?php

namespace App\Services\Order;

use App\Repositories\Order\OrderProductRepositories;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OrderProductService
{
    public static function updateProductAmount()
    {
        $orderProductRepositories = new OrderProductRepositories();
        [$list_7, $list_14] = $orderProductRepositories->getRecentTimeOrderProductAmount();
        DB::connection('mysql_proxy')->table('oc_product_crontab')
            ->where('amount_modified', '<', Carbon::now()->subHour(48)->format('Y-m-d H:i:s'))
            ->update(['amount_7' => 0, 'amount_14' => 0]);
        foreach ($list_14 as $key => $value) {
            $save['amount_14'] = $value;
            $save['product_id'] = $key;
            $save['amount_modified'] = Carbon::now();
            $save['amount_7'] = 0;
            if (isset($list_7[$key])) {
                $save['amount_7'] = $list_7[$key];
            }
            $info = DB::connection('mysql_proxy')->table('oc_product_crontab')
                ->where('product_id', $key)
                ->select('amount_14', 'amount_7')
                ->get()
                ->first();
            if ($info) {
                $lastAmount14 = $info->amount_14;
                $lastAmount7 = $info->amount_7;
            } else {
                $lastAmount14 = 0;
                $lastAmount7 = 0;
            }

            if (
                round($lastAmount14, 4) != round($save['amount_14'], 4)
                || round($lastAmount7, 4) != round($save['amount_7'], 4)
            ) {
                DB::connection('mysql_proxy')->table('oc_product_crontab')->updateOrInsert(
                    ['product_id' => $key], $save
                );
            }
        }
    }

    public static function updateProductDownloadTimes()
    {
        $list_14 = OrderProductRepositories::getRecentTimeProductDownloadTimes();
        foreach ($list_14 as $key => $value) {
            $save['download_14'] = $value;
            $save['download_modified'] = Carbon::now();
            $lastDownload14 = DB::connection('mysql_proxy')->table('oc_product_crontab')
                ->where('product_id', $key)
                ->value('download_14');
            if ($lastDownload14 != $value) {
                DB::connection('mysql_proxy')->table('oc_product_crontab')->updateOrInsert(
                    ['product_id' => $key], $save
                );
            }

        }
    }

    public static function updateProductDropPrice()
    {
        [$list_2, $list_14] = OrderProductRepositories::getRecentDropPrice();
        DB::connection('mysql_proxy')->table('oc_product_crontab')
            ->where('drop_price_modified', '<', Carbon::now()->subHour(48)->format('Y-m-d H:i:s'))
            ->update(['drop_price_2' => 0, 'drop_price_rate_14' => 0]);

        foreach ($list_14 as $key => $value) {
            $save['drop_price_rate_14'] = $value['price'];
            $save['seller_price_time'] = $value['seller_price_time'];
            $save['product_id'] = $key;
            $save['drop_price_modified'] = Carbon::now();
            $save['drop_price_2'] = 0;
            if (isset($list_2[$key])) {
                $save['drop_price_2'] = $list_2[$key]['price'];
            }
            $info = DB::connection('mysql_proxy')->table('oc_product_crontab')
                ->where('product_id', $key)
                ->select('drop_price_2', 'drop_price_rate_14')
                ->get()
                ->first();
            if ($info) {
                $lastDropPrice2 = $info->drop_price_2;
                $lastDropPrice14 = $info->drop_price_rate_14;
            } else {
                $lastDropPrice14 = 0;
                $lastDropPrice2 = 0;
            }

            if (
                round($lastDropPrice14, 4) != round($save['drop_price_rate_14'], 4)
                || round($lastDropPrice2, 4) != round($save['drop_price_2'], 4)
            ) {
                DB::connection('mysql_proxy')->table('oc_product_crontab')->updateOrInsert(
                    ['product_id' => $key], $save
                );
            }
        }
    }

    public static function updateSellerReturnApprovalRate()
    {
        $list = OrderProductRepositories::getSellerReturnApprovalRate();
        foreach ($list as $key => $items) {
            DB::connection('mysql_proxy')->table('oc_customerpartner_to_customer')
                ->where('customer_id', $key)
                ->update([
                    'return_approval_rate' => $items,
                    'return_approval_rate_date_modified' => Carbon::now(),
                ]);
        }
    }
}