<?php

namespace App\Console\Commands\Product\ProductChannel;

use App\Helpers\LoggerHelper;
use App\Services\Product\ProductChannel\NewStoresService;
use Illuminate\Console\Command;

class HomePageNewStores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:newStores';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新newStores信息';

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
            NewStoresService::updateNewStoresInfos();
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            LoggerHelper::logSystemMessage([__CLASS__ . ' error' => [
                'error' => $e->getMessage(),
            ]], 'error');
            echo date('Y-m-d H:i:s')
                . ' update:newStores mission failed !'  . PHP_EOL;
            return;
        }
        echo date('Y-m-d H:i:s')
            . ' update:newStores mission success !'  . PHP_EOL;

    }
}