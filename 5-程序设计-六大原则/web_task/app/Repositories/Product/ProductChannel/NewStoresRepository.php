<?php

namespace App\Repositories\Product\ProductChannel;

use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use Illuminate\Support\Facades\DB;

class NewStoresRepository
{
    const NUM = 21;
    const COUNTRY_ID = [223,222,81,107];
    const HOME_HIDE_CUSTOMER_GROUP =  [17, 18, 19, 20, 23];
    const HOME_HIDE_CUSTOMER =  [
        694,696,746,907,908,//保证金店铺 694=>bxw@gigacloudlogistics.com(外部产品)，696=>bxo@gigacloudlogistics.com，746=>nxb@gigacloudlogistics.com，907=>UX_B@oristand.com，908=>DX_B@oristand.com
        340,491,631,838,//服务店铺产品 340=>service@gigacloudlogistics.com(美) 491=>serviceuk@gigacloudlogistics.com(英) 631=>servicejp@gigacloudlogistics.com(日) 838=>DE-SERVICE@oristand.com(德)
    ];

    const HOME_HIDE_ACCOUNTING_TYPE = [3,4];

    public function getNewSellerIds(): array
    {
        $ret = [];
        foreach(self::COUNTRY_ID as $countryId){
            $builder  = DB::connection('mysql_proxy')->table('oc_customerpartner_to_customer as ctc')
                ->select(['c.customer_id'])
                ->leftJoin('oc_customer as c', 'ctc.customer_id', '=', 'c.customer_id')
                ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.customer_id', '=', 'c.customer_id')
                ->leftJoin('oc_product as p', 'p.product_id', '=', 'ctp.product_id')
                ->leftJoin('oc_product_exts as ope', 'ope.product_id', '=', 'p.product_id')
                ->leftJoin('oc_seller_store as ss', 'ss.seller_id', '=', 'ctc.customer_id')
                ->where([
                    'p.status' => 1,
                    'p.is_deleted' => 0,
                    'p.buyer_flag' => 1,
                    'p.product_type' => 0,
                    'p.part_flag' => 0,
                ])
                ->where(['c.status' => 1, 'c.country_id' => $countryId,])
                ->whereIn('c.accounting_type', [2, 5, 6])
                ->where(function ($q) {
                    $q->whereNull('ctc.performance_score')->orwhere('ctc.score_task_number', '<', self::getLastScoreTaskNumber());
                });
            $finBuilder = clone $builder;
            $sellerIds = $builder
                ->whereIn('c.customer_id', $this->getAvailableSellerId($countryId))
                ->whereNotNull('ope.receive_date')
                ->havingRaw('min(ope.receive_date)  > DATE_SUB(NOW(), INTERVAL 3 MONTH)')
                ->groupBy(['c.customer_id'])
                ->pluck('c.customer_id')
                ->toArray();
            $finBuilder = $finBuilder->whereIn('c.customer_id', $sellerIds)
                ->selectRaw('CASE
                WHEN count( p.product_id ) >= 3 THEN
                1 ELSE 0
                END AS productSort'
                )
                ->groupBy(['c.customer_id'])
                ->orderByRaw('productSort desc')
                ->orderByRaw('if(isnull(ss.store_home_json),0,1) desc')
                ->orderByRaw('min(ope.receive_date) desc');
            //$total = DB::connection('mysql_proxy')->table(DB::raw('(' . EloquentHelper::getCompleteSql($query) . ') as t'))->count();
            $ret[$countryId] = $finBuilder->limit(self::NUM)->pluck('c.customer_id')->toJson();
        }

        return $ret;

    }

    /**
     * 获取当期new seller数字
     * @return mixed|null
     */
    private static function getLastScoreTaskNumber()
    {
        return CustomerPartnerToCustomer::query()
            ->orderBy('score_task_number', 'desc')
            ->limit(1)
            ->value('score_task_number');
    }

    /**
     * 获取当前国别有效的customerId
     * @param $countryId
     * @return array
     */
    public function getAvailableSellerId(int $countryId): array
    {
        //        1、店铺限制：
        //（1）Unused分组店铺：
        //①US-Seller-Unused
        //②UK-Seller-Unused
        //③DE-Seller-Unused
        //④JP-Seller-Unused
        //（2）首页隐藏店铺：
        //①保证金店铺：
        //-bxw@gigacloudlogistics.com(外部产品)？
        //    -bxo@gigacloudlogistics.com
        //    -nxb@gigacloudlogistics.com
        //    -UX_B@oristand.com
        //    -DX_B@oristand.com
        //②服务店铺
        //美：service@gigacloudlogistics.com
        //英：serviceuk@gigacloudlogistics.com
        //德：DE-SERVICE@oristand.com
        //日：servicejp@gigacloudlogistics.com
        //（3）测试店铺：oc_customer，accounting_type=3
        //（4）服务店铺：oc_customer，accounting_type=4
        //（5）oc_customerpartner_to_customer表，show=1
        //（6）oc_product_to_store表，store_id=0
        //（7）状态为关店的店铺
        $unAvailableStoreId = explode(',', implode(',', self::HOME_HIDE_CUSTOMER));
        return  DB::connection('mysql_proxy')->table('oc_customerpartner_to_customer as ctc')
            ->leftJoin('oc_customer as c', 'ctc.customer_id', '=', 'c.customer_id')
            ->whereNotIn('c.customer_group_id', self::HOME_HIDE_CUSTOMER_GROUP)
            ->whereNotIn('c.accounting_type', self::HOME_HIDE_ACCOUNTING_TYPE)
            ->where('c.status', 1)
            ->where('ctc.show', 1)
            ->where('c.country_id', $countryId)
            ->whereNotIn('c.customer_id', $unAvailableStoreId)
            ->select('c.customer_id')
            ->get()
            ->pluck('customer_id')
            ->toArray();
    }
}