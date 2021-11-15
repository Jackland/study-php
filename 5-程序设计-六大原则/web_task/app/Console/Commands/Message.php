<?php
/**
 * Created by PhpStorm.
 * User: Lu.Chen
 * Date: 2019/11/18
 * Time: 14:14
 */

namespace App\Console\Commands;
use App\Models\Message\QuoteMsg;
use App\Models\Message\RebatesMsg;
use App\Models\Message\SalesOrderMsg;
use Illuminate\Console\Command;

class Message extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:message {msg_type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Messages From System';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    const MSG_TYPE = ['bid', 'bid_rebates', 'sales_order'];

    public function handle()
    {
        $msgType = $this->argument('msg_type');
        if (!in_array($msgType, self::MSG_TYPE)){
            return ;
        }

        switch ($msgType){
            case 'bid':{//议价
                $quoteM = new QuoteMsg();
                $quoteM->applyTimeOut();
                $quoteM->buyTimeOut();
                break;
            }
//  这是老版本的返点，新版返点四期已重做
//            case 'bid_rebates':{//返金
//                $rebateM = new RebatesMsg();
//                $rebateM->expirationReminder();
//                break;
//            }
            case 'sales_order':{//销售订单
                $order = new SalesOrderMsg();
                $order->deliveryMsg();
                break;
            }
        }

        echo date('Y-m-d H:i:s')
            . ' send:message '. $msgType . PHP_EOL;
        return;
    }



}