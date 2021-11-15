<?php

namespace App\Console\Commands;
use App\Models\SalesOrder\SalesOrder;
use Illuminate\Console\Command;

class SalesOrderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:salesOrder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'on hold sales order';

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

        $salesOrder = new SalesOrder();
        $salesOrder->updateSalesOrderOnHold();
        echo date('Y-m-d H:i:s')
            . ' update:salesOrder mission success!'  . PHP_EOL;
        return;
    }



}