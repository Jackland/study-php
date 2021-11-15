<?php

namespace App\Console\Commands\Product;

use App\Helpers\LoggerHelper;
use App\Services\Order\OrderProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurchaseOrderAmount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calc:purchaseOrderAmount';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '计算最近7天&最近14天产品的采购销售额度';

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
        try {
            \DB::beginTransaction();
            OrderProductService::updateProductAmount();
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            LoggerHelper::logSystemMessage([__CLASS__ . ' error' => [
                'error' => $e->getMessage(),
            ]], 'error');
            echo date('Y-m-d H:i:s')
                . ' calc:purchaseOrderAmount mission failed !'  . PHP_EOL;
            return;
        }
        echo date('Y-m-d H:i:s')
            . ' calc:purchaseOrderAmount mission success !'  . PHP_EOL;



    }
}