<?php

namespace App\Services\Product\ProductChannel;

use App\Repositories\Product\ProductChannel\NewStoresRepository;
use Illuminate\Support\Facades\DB;

class NewStoresService
{
    public static function updateNewStoresInfos()
    {
        $newStoresRepository = new NewStoresRepository();
        $data = $newStoresRepository->getNewSellerIds();
        foreach ($data as $key => $items) {
            DB::connection('mysql_proxy')->table('tb_sys_home_page_config')->updateOrInsert(
                ['type_id' => 2, 'country_id' => $key], ['content' => $items, 'status' => 1]
            );
        }
    }
}