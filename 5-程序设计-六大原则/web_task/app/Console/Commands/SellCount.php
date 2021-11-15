<?php

namespace App\Console\Commands;

use App\Models\Statistics\SellCountModel;
use Illuminate\Console\Command;

class SellCount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statistic:sell_count {product_id=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计最近days（7,14,30天）产品的销售量，不包括RMA,包括保证金商品,参数1：product id（默认0，表示全部），参数2：统计的时长';

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
        $product_id=$this->argument('product_id');

        if(!is_numeric($product_id)){
            print_r('product_id 参数错误');
            return ;
        }
        SellCountModel::sell_count($product_id);
        print_r('sell count end ......');
    }
}
