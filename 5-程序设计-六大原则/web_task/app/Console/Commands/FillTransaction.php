<?php

namespace App\Console\Commands;

use App\Models\CustomerPartner\BuyerToSeller;
use Illuminate\Console\Command;

class FillTransaction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fill:transaction {start} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计填充BTS交易信息';

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
        $startID = $this->argument('start');
        $endID = $this->argument('end');

        echo "Start ID: {$startID},End ID:{$endID}" . PHP_EOL;

        $BuyerToSeller = new BuyerToSeller();
        $page = 1;
        $num = 1;
        do {
            $bts = $BuyerToSeller->getBTSList($startID, $endID, $page++);

            foreach ($bts as $key => $list) {
                $seller_id = $list->seller_id;
                $buyer_id = $list->buyer_id;
                $purCount = $BuyerToSeller->countPurchaseOrder($seller_id, $buyer_id);
                $marginCount = $BuyerToSeller->countMargin($seller_id, $buyer_id);

                $money = 0;
                if ($purCount > 0) {
                    $money = $BuyerToSeller->sumPurchaseOrderMoney($seller_id, $buyer_id);
                    $money -= $BuyerToSeller->sumRMAMoney($seller_id, $buyer_id);
                }
                if ($marginCount > 0) {
                    $money += $BuyerToSeller->sumMarginMoney($seller_id, $buyer_id);
                }

                $time = '';
                if ($purCount > 0 || $marginCount > 0) {
                    $time = $BuyerToSeller->getLastTime($seller_id, $buyer_id);
                }
                $count = $purCount + $marginCount;

                $update = [];
                if (!empty($count)) {
                    $update['number_of_transaction'] = $count;
                }

                if ($money > 0) {
                    $update['money_of_transaction'] = $money;
                }

                if (!empty($time)) {
                    $update['last_transaction_time'] = $time;
                }

                $BuyerToSeller->updateTransaction($list->id, $update);
                echo $num . ' ' . $list->id . '.' . $seller_id . '-' . $buyer_id . " $count,$money,$time ." . PHP_EOL;
                $num++;
            }
        } while ($bts->count() > 0);
        echo "Total:{$num}" . PHP_EOL;
    }
}
