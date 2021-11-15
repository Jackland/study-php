<?php

namespace App\Repositories\Product\ProductChannel;

use App\Helpers\EloquentHelper;
use Illuminate\Support\Facades\DB;

class NewArrivalsRepository
{
    const COUNTRY_ID = [223,222,81,107];

    public static function getNewArrivalsProductIds()
    {
        // 取复杂交易的100个，非复杂交易的100个
        $ret = [];
        $newStoresRepository = new NewStoresRepository();
        foreach (self::COUNTRY_ID as $countryId) {
            $query = DB::connection('mysql_proxy')->table('oc_product as p')
                ->leftJoin('oc_customerpartner_to_product as ctp','ctp.product_id','p.product_id')
                ->leftJoin('oc_product_crontab as pc', 'p.product_id', 'pc.product_id')
                ->leftJoin('oc_product_exts as pExt', 'pExt.product_id', 'p.product_id')
                ->leftJoin('oc_customer as c', 'c.customer_id', 'ctp.customer_id')
                ->where([
                    'p.status' => 1,
                    'p.is_deleted' => 0,
                    'p.buyer_flag' => 1,
                    'p.part_flag' => 0,
                    'p.product_type' => 0,
                    'c.country_id' => $countryId,
                    'c.status' => 1,
                ])
                ->where('p.quantity','>',0)
                ->whereIn('c.customer_id', $newStoresRepository->getAvailableSellerId($countryId))
                ->whereNotNull('ctp.customer_id')
                ->whereNotNull('p.product_id')
                ->whereNotNull('pExt.receive_date')
                ->orderBy('pExt.receive_date', 'desc')
                ->select('pExt.receive_date', 'p.product_id', 'ctp.customer_id', 'pc.is_complex_transaction');


            $allData = DB::connection('mysql_proxy')->table(DB::raw('(' . EloquentHelper::getCompleteSql($query) . ') as s'))
                ->orderBy('s.receive_date', 'desc')
                ->get();
            // 此处有点问题group by 需要手动处理
            $info = [];
            foreach($allData as $items){
                if(!isset($info[$items->is_complex_transaction .'_' . $items->customer_id])){
                    $info[$items->is_complex_transaction .'_' . $items->customer_id] = $items;
                }
            }
            $infoCollection = array_values($info);
            $complexTransaction = $commonTransaction = [];
            foreach ($infoCollection as $key => $items) {
                if ($items->is_complex_transaction && count($complexTransaction) < 100) {
                    $complexTransaction[] = $items->product_id;
                }

                if (!$items->is_complex_transaction && count($commonTransaction) < 100) {
                    $commonTransaction[] = $items->product_id;

                }

                if(count($complexTransaction) >= 100 && count($commonTransaction) >= 100){
                    break;
                }

            }
            // 判断复杂交易是否大于等于4个
            if(count($complexTransaction) < 4){
                $complexTransactionExtra = DB::connection('mysql_proxy')->table(DB::raw('(' . EloquentHelper::getCompleteSql($query) . ') as s'))
                    ->where('s.is_complex_transaction',1)
                    ->whereNotIn('s.product_id',$complexTransaction)
                    ->orderBy('s.receive_date', 'desc')
                    ->pluck('s.product_id')
                    ->toArray();
                $complexTransaction = array_merge($complexTransaction,$complexTransactionExtra);
            }

            $ret[$countryId] = json_encode([
                'complexTransaction' =>$complexTransaction,
                'commonTransaction' => $commonTransaction,
            ]);
        }

        return $ret;
    }

}