<?php

namespace App\Services\Order;

use App\Enums\Order\OrderInvoiceStatus;
use App\Helpers\LoggerHelper;
use App\Models\Order\OrderInvoice;
use Carbon\Carbon;
use Symfony\Component\HttpClient\HttpClient;

class OrderInvoiceService
{
    /**
     * 发起生成Order Invoice
     *
     * @param int $buyerId BuyerID
     * @param int $beforeDays 几天之内的数据
     */
    public function generate(int $buyerId, int $beforeDays = 7)
    {
        $startDate = Carbon::now()->subDays($beforeDays)->toDateString();
        $list =  OrderInvoice::query()
            ->where('buyer_id', $buyerId)
            ->where('create_time', '>=', $startDate)
            ->where('status', OrderInvoiceStatus::GOING)
            ->orderBy('create_time', 'asc')
            ->get();

        if ($list->isNotEmpty()) {
            $client = HttpClient::create();
            $apiUrl = config('app.b2b_url') . 'api/order/order_invoice/generateInvoice';

            foreach ($list as $item) {
                LoggerHelper::logOrderInvoice(['buyerId' => $buyerId, 'invoiceId:' => $item->id]);

                $response = $client->request('POST', $apiUrl, [
                    'body' => [
                        'invoice_id' => $item->id,
                        'buyer_id' => $item->buyer_id
                    ],
                ]);
                LoggerHelper::logOrderInvoice(['buyerId' => $buyerId, 'invoiceId:' => $item->id, 'response' => $response->getContent(false)]);
            }
        }
    }
}