<?php

namespace App\Console\Commands\Product;

use App\Helpers\LoggerHelper;
use App\Services\Order\OrderProductService;
use Illuminate\Console\Command;

class ProductDownloadTimes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calc:productDownloadTimes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '计算最近14天产品的下载次数';

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
            OrderProductService::updateProductDownloadTimes();
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            LoggerHelper::logSystemMessage([__CLASS__ . ' error' => [
                'error' => $e->getMessage(),
            ]], 'error');
            echo date('Y-m-d H:i:s')
                . ' calc:productDownloadTimes mission failed !'  . PHP_EOL;
            return;
        }
        echo date('Y-m-d H:i:s')
            . ' calc:productDownloadTimes mission success !'  . PHP_EOL;



    }
}