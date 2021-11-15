<?php

namespace App\Console\Commands;

use App\Helpers\LoggerHelper;
use App\Jobs\SendMail;
use App\Models\Order\Order;
use App\Models\Order\OrderTotal;
use App\Models\Setting;
use Illuminate\Console\Command;
use Log;

class OrderMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:monitor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'order monitor';

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
        echo date("Y-m-d H:i:s") . ' ------order-monitor-start------' . PHP_EOL;
        $this->monitorSubtotal();
        echo date("Y-m-d H:i:s") . ' ------order-monitor-end------' . PHP_EOL;
    }

    private function monitorSubtotal()
    {
        $orders = Order::query()
            ->with('products')
            ->select('order_id', 'date_added')
            ->where('date_added', '>=', date('Y-m-d') . ' 00:00:00')
            ->get();
        if ($orders->isEmpty()) {
            return;
        }
        $ordersTotal = OrderTotal::query()
            ->whereIn('order_id', $orders->pluck('order_id'))
            ->get();
        $abNormal = [];
        foreach ($orders as $order) {
            $productSubTotal = 0;
            $productServiceFee = 0;
            $productFreight = 0;
            $productPromotionDiscount = 0;
            $productGigaCoupon = 0;
            // 计算oc_order_total的各项和oc_order_product的各项是否一致
            foreach ($order->products as $product) {
                $productSubTotal += $product['price'] * $product['quantity'];
                $productServiceFee += $product['service_fee_per'] * $product['quantity'];
                $productFreight += ($product['freight_per'] + $product['package_fee']) * $product['quantity'];
                $productPromotionDiscount += $product['campaign_amount'];
                $productGigaCoupon += $product['coupon_amount'];

            }
            $subTotal = $ordersTotal->where('order_id', $order->order_id)->where('code', 'sub_total')->first()['value'] ?? 0;
            $freight = $ordersTotal->where('order_id', $order->order_id)->where('code', 'freight')->first()['value'] ?? 0;
            $serviceFee = $ordersTotal->where('order_id', $order->order_id)->where('code', 'service_fee')->first()['value'] ?? 0;
            $promotionDiscount = $ordersTotal->where('order_id', $order->order_id)->where('code', 'promotion_discount')->first()['value'] ?? 0;
            $gigaCoupon = $ordersTotal->where('order_id', $order->order_id)->where('code', 'giga_coupon')->first()['value'] ?? 0;
            if (bccomp($subTotal, $productSubTotal) != 0
                || bccomp($freight, $productFreight) != 0
                || bccomp($serviceFee, $productServiceFee) != 0
                || bccomp(abs($promotionDiscount), $productPromotionDiscount) != 0
                || bccomp(abs($gigaCoupon), $productGigaCoupon) != 0
            ) {
                $abNormal[] = $order->order_id;
                continue;
            }
            // 计算oc_order_total的各项总和是否等于total
            $ordersTotalItems = $ordersTotal->where('order_id', $order->order_id)->all();
            $total = 0;
            $otherTotal = 0;
            foreach ($ordersTotalItems as $item) {
                if ($item['code'] == 'total') {
                    $total = $item['total'];
                } else {
                    $otherTotal += $item['total'];
                }
            }
            if (bccomp($total, $otherTotal) != 0) {
                $abNormal[] = $order->order_id;
                continue;
            }
        }
        if ($abNormal) {
            $abNormal = 'B2B异常订单ID: ' . implode($abNormal, ',');
            self::sendMail($abNormal);
            self::logger($abNormal);
        }
    }


    public static function sendMail($abNormal)
    {
        $data['subject'] = 'B2B异常订单';
        $data['body'] = $abNormal;
        $list = Setting::getConfig('order_monitor_email_to');
        if ($list) {
            $list = explode(',', $list);
            foreach ($list as $item) {
                $data['to'] = $item;
                SendMail::dispatch($data);
            }
        }
    }

    private static function logger($message)
    {
        $monolog = Log::getMonolog();
        $oldHandler = $monolog->popHandler();
        Log::useFiles(storage_path('logs/order_monitor.log'));
        Log::notice($message);
        $monolog->popHandler();
        $monolog->pushHandler($oldHandler);
    }


}
