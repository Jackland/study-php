<?php

namespace App\Console\Commands;

use App\Models\Customer\Customer;
use Illuminate\Console\Command;

class OnlineResolveProduct extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'online:resolve_product {customer_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '处理商品部分信息丢失导致的商品无法显示问题';

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
        $customer_id = $this->argument('customer_id');
        $ge = \DB::table('oc_customerpartner_to_product as ctp')
            ->select(['ctp.product_id', 'pts.store_id'])
            ->leftJoin('oc_product_to_store as pts', 'ctp.product_id', '=', 'pts.product_id')
            ->where('ctp.customer_id', $customer_id)
            ->cursor();
        $insert_arr = [];
        foreach ($ge as $item) {
            $item = (array)$item;
            if (is_null($item['store_id'])) {
                $insert_arr[] = ['product_id' => $item['product_id'], 'store_id' => 0];
            }
        }
        if (!empty($insert_arr)) {
            \DB::table('oc_product_to_store')->insert($insert_arr);
        }
        $this->info('success.');
    }
}
