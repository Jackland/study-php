<?php

namespace App\Jobs;

use App\Helpers\LoggerHelper;
use App\Services\Product\ProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class PackToZip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 300;
    public $sleep = 30;
    public $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data = '')
    {
        $this->data = $data;
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            LoggerHelper::logPackZip($this->data);

            // 判断 如果当前产品 有任务在执行打包处理，暂时后续请求直接忽略，或10分钟之后再次处理
            $cacheKey = $this->data['product_id'] . '_' . $this->data['customer_id'];
            if (Cache::get($cacheKey)) {
                // 说明存在正在处理中的任务 | 或者限制时间内
                $this->data['msg'] = '限制条件下发生重复处理';
                LoggerHelper::logPackZip($this->data);
            } else {
                Cache::put($cacheKey, 1, 10);

                app(ProductService::class)->packToZip($this->data['product_id'], $this->data['customer_id']);
                $this->data['msg'] = '打包成功';
                LoggerHelper::logPackZip($this->data);
                Cache::forget($cacheKey); // 打包成功，清除限制缓存
            }
        } catch (\Exception $e) {
            LoggerHelper::logPackZip([__CLASS__ => [
                'product_id' => $this->data['product_id'],
                'customer_id' => $this->data['customer_id'],
                'error' => $e->getMessage(),
            ]], 'error');
        }
    }
}
