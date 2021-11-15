<?php

namespace App\Console\Commands\SalesOrder;

use App\Enums\Common\YesNoEnum;
use App\Enums\SalesOrder\CustomerSalesOrderPickUpStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use Carbon\Carbon;
use DB;
use Illuminate\Console\Command;
use Symfony\Component\HttpClient\HttpClient;

class PickUpCancelWaitConfirm extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sales-order-pick-up:cancel-wait-confirm';

    /**
     * @var string
     */
    protected $description = '自提货订单待确认状态超时自动取消';

    public function handle()
    {
        // 获取所有超时 48 小时的自提货待确认订单
        $salesOrderIds = DB::table('tb_sys_customer_sales_order_pick_up as a')
            ->leftJoin('tb_sys_customer_sales_order as b', 'a.sales_order_id', '=', 'b.id')
            ->leftJoin('tb_sys_customer_sales_order_pick_up_line_change as c', 'c.sales_order_id', '=', 'b.id')
            ->where('a.pick_up_status', CustomerSalesOrderPickUpStatus::PICK_UP_INFO_TBC) // 自提货子状态待确认
            ->where('b.order_status', CustomerSalesOrderStatus::BEING_PROCESSED) // 销售单 BP
            ->where('c.is_buyer_accept', YesNoEnum::NO) // buyer还未接受接受
            ->where('c.create_time', '<=', Carbon::now()->subDays(2)->toDateTimeString()) // 超过仓库确认后48小时
            ->get(['a.sales_order_id'])->pluck('sales_order_id')->all();
        $salesOrderIds = array_unique($salesOrderIds);
        if (!$salesOrderIds) {
            $this->logger('no need cancel');
        }

        $client = HttpClient::create();
        $apiUrl = config('app.b2b_url') . 'api/sales_order_pick_up/cancelWaitConfirm';
        foreach ($salesOrderIds as $salesOrderId) {
            $this->logger(['request' => $apiUrl, 'order_id' => $salesOrderId]);
            $response = $client->request('POST', $apiUrl, [
                'body' => [
                    'order_id' => $salesOrderId,
                ],
            ]);
            $this->logger(['response' => $response->getContent(false)]);
        }
    }

    protected function logger($msg, $type = 'info')
    {
        $msg = is_array($msg) ? json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $msg;
        $this->$type(date('Y-m-d H:i:s') . ': ' . $msg);
    }
}