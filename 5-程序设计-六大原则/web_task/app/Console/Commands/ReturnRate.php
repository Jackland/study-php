<?php
/**
 * Created by PhpStorm.
 * User: Lu.Chen
 * Date: 2020/4/27
 * Time: 14:37
 */

namespace App\Console\Commands;

use App\Models\CustomerPartner\SellerRate;
use App\Models\Rma\CalculateReturnRate;
use Illuminate\Console\Command;

//php artisan returnRate:update
class ReturnRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'returnRate:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '退返率更新计算';

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
        try {
            $model = new CalculateReturnRate();
            $model->updateRate();

            $m = new SellerRate();
            $m->updateReturnRate();//更新店铺退返率
        }catch (\Exception $e){
            $preMsg = $e->getMessage();
            \Log::error($preMsg);
        }
    }

}