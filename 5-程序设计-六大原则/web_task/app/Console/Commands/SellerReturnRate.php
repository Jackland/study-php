<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class SellerReturnRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Seller:ReturnRate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seller店铺退返品率';

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
        //在app\Console\Commands\ReturnRate.php中，更新了产品退返率之后，更新店铺退返率；
    }
}
