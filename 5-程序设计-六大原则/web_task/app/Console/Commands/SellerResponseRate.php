<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class SellerResponseRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seller:ResponseRate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'seller店铺回复率';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        echo date('Y-m-d H:i:s') . ' seller:ResponseRate seller店铺回复率 更新开始......' . PHP_EOL;

        $M = new \App\Models\CustomerPartner\SellerRate();


        $num   = \DB::connection('mysql_proxy')->table('oc_customerpartner_to_customer')->count();
        $index = 0;
        while ($index < $num) {
            $objs = \DB::connection('mysql_proxy')->table('oc_customerpartner_to_customer')
                ->select('customer_id')
                ->where([
                    ['is_partner','=', 1]
                ])
                ->offset($index)
                ->limit(1)
                ->get()
                ->toArray();
            if ($objs) {
                $customer_id = $objs[0]->customer_id;
                $M->responseRate($customer_id);
            }
            $index++;
        }


        echo date('Y-m-d H:i:s') . ' seller:ResponseRate seller店铺回复率 更新成功' . PHP_EOL;
    }
}
