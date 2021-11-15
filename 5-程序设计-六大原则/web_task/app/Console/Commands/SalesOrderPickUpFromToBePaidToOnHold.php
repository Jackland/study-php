<?php

namespace App\Console\Commands;


use App\Models\SalesOrder\SalesOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SalesOrderPickUpFromToBePaidToOnHold extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:salesOrderPickUpFromToBePaidToOnHold {country_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '若【To Be Paid】状态的SO单超时7天未手动选择库存，则SO单状态由【To Be Paid】流转为【On Hold】';

    /**
     * Create a new command instance.
     *
     *
     */
    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $countryId = $this->argument('country_id');
        $salesOrder = new SalesOrder();
        $result = $salesOrder->pickUpFromToBePaidToOnHold($countryId);
        if ($result) {
            echo Carbon::now()->toDateTimeString() . ' update:salesOrderPickUpFromToBePaidToOnHold mission success!' . PHP_EOL;
        } else {
            echo Carbon::now()->toDateTimeString() . ' 没有配置 salesOrderPickUpFromToBePaidToOnHoldDay ，或事务失败' . PHP_EOL;
        }
        return;
    }
}