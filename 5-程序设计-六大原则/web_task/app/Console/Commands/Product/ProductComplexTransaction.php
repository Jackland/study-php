<?php

namespace App\Console\Commands\Product;

use App\Helpers\LoggerHelper;
use App\Services\Product\ProductService;
use Illuminate\Console\Command;

class ProductComplexTransaction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:productComplexTransaction';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新产品是否是复杂交易属性';

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
             ProductService::updateComplexTransactionProductIds();
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            LoggerHelper::logSystemMessage([__CLASS__ . ' error' => [
                'error' => $e->getMessage(),
            ]], 'error');
            echo date('Y-m-d H:i:s')
                . ' update:productComplexTransaction mission failed !'  . PHP_EOL;
            return;
        }
        echo date('Y-m-d H:i:s')
            . ' update:productComplexTransaction mission success !'  . PHP_EOL;

    }
}