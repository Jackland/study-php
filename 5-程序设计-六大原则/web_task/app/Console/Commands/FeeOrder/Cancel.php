<?php

namespace App\Console\Commands\FeeOrder;

use App\Models\FeeOrder\FeeOrder;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Log;

class Cancel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feeOrder:cancel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每隔30分钟定时取消费用单';

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
        // 获取所有30分钟之前的费用单
        $expireTime = Setting::getConfig('expire_time') ?? 30;
        $list = FeeOrder::whereRaw(new Expression('created_at < date_add(now(), interval -' . $expireTime . ' minute)'))
            ->where('status', 0)
            ->get();
        // 自动全部取消
        if ($list->isNotEmpty()) {
            FeeOrder::whereIn('id', $list->pluck('id')->toArray())->update(['status' => 7]);
            $msg = '[' . join(',', $list->pluck('order_no')->toArray()) . ']费用单被置为取消状态.';
            Log::useDailyFiles(storage_path('logs/fee_order/fee_order_cancel.log'));
            Log::info($msg);
        }
    }
}
