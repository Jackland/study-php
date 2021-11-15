<?php

namespace App\Console\Commands;

use App\Models\Future\Agreement;
use App\Models\Future\MarginDelivery;
use Illuminate\Console\Command;

class FutureAgreement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'future:agreement';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'future agreement time out';

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
        echo date("Y-m-d H:i:s",time()).' ------future-start------' . PHP_EOL;
        Agreement::setTimeOutAgreement();// 协议超时
        MarginDelivery::sellerNotDelivery(); // seller交付超时
        MarginDelivery::setDeliveryTimeOut();// 交割超时
        MarginDelivery::stockMargin(); // Buyer24小时内未支付现货保证金
        MarginDelivery::buyerNotPayTailMoney(); // buyer在30天未支付期货尾款
        echo date("Y-m-d H:i:s",time()).' ------future-end------' . PHP_EOL;
    }
}
