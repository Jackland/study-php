<?php

namespace App\Console\Commands;
use App\Models\BuyerBill\BuyerBill;
use Illuminate\Console\Command;

/**
 * Class GetBuyerBill
 * @package App\Console\Commands
 * @deprecated 仓租一期的计算任务，已废弃
 */
class GetBuyerBill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:buyerBill {type?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Buyer Monthly Bill';

    /**
     * Create a new command instance.
     *
     *
     */
    public function __construct()
    {
        parent::__construct();
    }


    const TYPE = ['add', 'update'];

    public function handle()
    {
        $buyerBill = new BuyerBill();
        $type = $this->argument('type');
        if (!in_array($type, self::TYPE)){
            return ;
        }
        if($type == 'add' ){

            $buyerBill->billTask();
            echo date('Y-m-d H:i:s')
                . ' get:buyerBill add mission success!'  . PHP_EOL;

        }elseif ($type == 'update'){

            $buyerBill->billPaid();
            echo date('Y-m-d H:i:s')
                . ' get:buyerBill update mission success!'  . PHP_EOL;


        }

        return;
    }



}