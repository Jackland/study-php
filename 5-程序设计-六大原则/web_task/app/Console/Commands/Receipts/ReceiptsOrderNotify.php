<?php

namespace app\Console\Commands\Receipts;

use Illuminate\Console\Command;
use App\Models\Receipt\ReceiptsOrder;

class ReceiptsOrderNotify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:receipts-order-seller';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '入库单未填写集装箱通知Seller';

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
     * @throws \Exception
     */
    public function handle()
    {
        echo date("Y-m-d H:i:s",time()).' ------receipts-order-notify-start------' . PHP_EOL;
        ReceiptsOrder::getUnWriteContainerCode();
        echo date("Y-m-d H:i:s",time()).' ------receipts-order-notify-end------' . PHP_EOL;
    }

}