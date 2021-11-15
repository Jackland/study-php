<?php

namespace App\Services\Product\ProductChannel;

use App\Repositories\Product\ProductChannel\FeatureStoresRepository;
use Illuminate\Support\Facades\DB;

class FeatureStoresService
{
    public static function updateFeatureStoresInfos()
    {
        $featureStoresRepository = new FeatureStoresRepository();
        $data = $featureStoresRepository->getFeatureStoresSellerIds();
        foreach ($data as $key => $items) {
            DB::connection('mysql_proxy')->table('tb_sys_home_page_config')->updateOrInsert(
                ['type_id' => 1, 'country_id' => $key], ['content' => $items, 'status' => 1]
            );
        }
    }
}