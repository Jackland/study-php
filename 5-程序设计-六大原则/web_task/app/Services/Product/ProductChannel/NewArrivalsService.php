<?php

namespace App\Services\Product\ProductChannel;

use App\Repositories\Product\ProductChannel\NewArrivalsRepository;
use Illuminate\Support\Facades\DB;

class NewArrivalsService
{
    public static  function updateNewArrivalsProductIds()
    {
        $data = NewArrivalsRepository::getNewArrivalsProductIds();
        foreach ($data as $key => $items) {
            DB::connection('mysql_proxy')->table('tb_sys_home_page_config')->updateOrInsert(
                ['type_id' => 3, 'country_id' => $key], ['content' => $items, 'status' => 1]
            );
        }
    }
}