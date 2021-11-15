<?php

namespace App\Console\Commands\StorageFee;

use App\Traits\CommandLoggerTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class CalculateByOrder extends Command
{
    use CommandLoggerTrait;

    /**
     * @var string
     */
    protected $signature = 'storageFee:calculate-order
                            {order : 指定采购单ID，如：465233}
                            {--d|date=today : 指定日期，默认为今天，如：2020-10-12}
                            {--f|force : 若该采购单已经计算过，使用该参数删除已经计算的并重新计算}
                            ';

    /**
     * @var string
     */
    protected $description = '按采购单计算到时间日期截止的所有仓租';

    public function handle()
    {
        $orderId = $this->argument('order');
        if (!is_numeric($orderId) || $orderId <= 0) {
            $this->error('order 订单ID必须是大于0 数字');
            return;
        }
        $date = $this->option('date');
        if ($date === 'today') {
            $date = Carbon::now()->format('Y-m-d');
        } else {
            if ($date !== (new Carbon($date))->format('Y-m-d')) {
                $this->error('date 日期格式错误');
                return;
            }
        }
        $force = (bool)$this->option('force');

        $apiUrl = config('app.b2b_url') . 'api/storage_fee/calculateByOrder&' . http_build_query([
                'o' => (int)$orderId,
                'd' => $date,
                'f' => (int)$force,
            ]);
        $this->logger(['request' => $apiUrl]);
        try {
            $context = [
                'http' => [
                    'timeout' => 10 * 60,
                ]
            ];
            $response = file_get_contents($apiUrl, false, stream_context_create($context));
        } catch (Throwable $e) {
            $this->logger(['error' => $e->getMessage()], 'error');
            throw $e;
        }
        $this->logger(['response' => $response]);
    }
}