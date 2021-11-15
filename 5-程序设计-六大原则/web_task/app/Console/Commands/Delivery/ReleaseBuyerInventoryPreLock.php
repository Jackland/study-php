<?php

namespace App\Console\Commands\Delivery;

use App\Enums\Stock\BuyerProductLockEnum;
use App\Models\Delivery\BuyerProductLock;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReleaseBuyerInventoryPreLock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'release:buyer-inventory-pre-lock {--buyerId=} {--productId=}';

    /**
     * The console command description.
     * #39502 当销售订单选择使用囤货库存，需新增预锁定囤货库存30分钟的逻辑 https://ones.ai/project/#/team/8wP6mUy7/task/9wkqgVLhcCjQ4WqJ
     * @var string
     */
    protected $description = '释放buyer的囤货锁定库存';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $buyerId = $this->option('buyerId') ?: '';
        $productId = $this->option('productId') ?: '';

        //获取订单超时时间
        $expireMinutes = \DB::table('oc_setting as os')
            ->where('os.key', '=', 'expire_time')
            ->value('value');

        $locks = BuyerProductLock::query()
            ->where('type', BuyerProductLockEnum::INVENTORY_PRE_ASSOCIATED)
            ->where('is_processed', 0)
            ->where('create_time', '<', Carbon::now()->subMinutes($expireMinutes))
            ->when(!empty($buyerId), function ($q) use ($buyerId) {
                $q->where('buyer_id', $buyerId);
            })
            ->when(!empty($productId), function ($q) use ($productId) {
                $q->where('product_id', $productId);
            })
            ->get();

        $handleLockIds = [];
        foreach ($locks as $lock) {
            /** @var BuyerProductLock $lock */
            if (\DB::table('tb_sys_order_associated_pre as ap')
                ->join('tb_sys_customer_sales_order as o', 'ap.sales_order_id', '=', 'o.id')
                ->where('ap.status', 0)
                ->where('ap.id', $lock->foreign_key)
                ->exists()) {
                $handleLockIds[] = $lock->id;
            }
        }

        if ($handleLockIds) {
            BuyerProductLock::query()->whereIn('id', $handleLockIds)->update([
                'is_processed' => 1,
                'process_date' => Carbon::now(),
                'update_time' => Carbon::now(),
            ]);
        }
    }
}