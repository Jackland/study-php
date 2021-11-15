<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use phpDocumentor\Reflection\Type;
use App\Models\Future\Agreement;

class FutureDailyMessage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'future:daily-message {country_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每个国别的中午12点，发送过期提醒站内信。country_id in [81,107,222,223]';

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
     */
    public function handle()
    {
        echo date("Y-m-d H:i:s",time()).' ------future-message-start------' . PHP_EOL;
        $country_id = $this->argument('country_id');
        $country_id_list = [81, 107, 222, 223];
        if (!in_array($country_id, $country_id_list)) {
            echo date("Y-m-d H:i:s",time()).'输入参数（country_id）错误...';
            return;
        }
        Agreement::sendDailyMessageByCountryId($country_id,Agreement::FUTURE_DELIVERY);//交货日期开始倒计时直至交货成功/失败，以天为单位，每天一封
        Agreement::sendDailyMessageByCountryId($country_id,Agreement::FUTURE_BUYER_PAID);//交割方式为支付尾款，Buyer开始支付尾款开始直至Buyer未履约前一天，以天未单位，每天一封
        echo date("Y-m-d H:i:s",time()).' ------future-message-end------' . PHP_EOL;


    }
}
