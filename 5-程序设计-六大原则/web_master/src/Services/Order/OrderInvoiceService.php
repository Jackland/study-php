<?php

namespace App\Services\Order;

use App\Components\BatchInsert;
use App\Components\UniqueGenerator\Enums\ServiceEnum;
use App\Logging\Logger;
use App\Models\Order\Order;
use App\Models\Order\OrderInvoice;
use App\Repositories\Common\SerialNumberRepository;
use Symfony\Component\HttpClient\HttpClient;

class OrderInvoiceService
{
    /**
     * 创建需要生成Invoice的数据
     *
     * @param int $customerId 用户ID
     * @param array $orderIds 目标订单数组IDs
     * @return bool
     * @throws \Exception
     */
    public function createInvoice(int $customerId, array $orderIds)
    {
        $list = Order::query()->alias('o')
            ->leftJoinRelations('orderProducts as op')
            ->leftJoin('oc_customerpartner_to_product as tp', 'op.product_id', 'tp.product_id')
            ->select(['o.order_id', 'tp.customer_id'])
            ->where('o.customer_id', $customerId)
            ->whereIn('o.order_id', $orderIds)
            ->groupBy(['o.order_id', 'tp.customer_id'])
            ->get();

        $data = [];
        if ($list->isNotEmpty()) {
            foreach ($list as $item) {
                $data[$item['customer_id']]['orderIds'][] = $item['order_id'];
            }
        }
        if ($data) {
            $nowDate = date('Y-m-d H:i:s');
            db()->getConnection()->beginTransaction();
            try {
                $batchInsert = new BatchInsert();
                $batchInsert->begin(OrderInvoice::class, 500);
                foreach ($data as $key => $item) {
                    $serivaNumber = SerialNumberRepository::getDateSerialNumber(ServiceEnum::ORDER_INVOICE_NO);
                    $batchInsert->addRow([
                        'buyer_id' => $customerId,
                        'seller_id' => $key,
                        'order_ids' => implode(',', $item['orderIds']),
                        'serial_number' => $serivaNumber,
                        'create_time' => $nowDate,
                        'update_time' => $nowDate,
                    ]);
                }
                $batchInsert->end();
                db()->getConnection()->commit();

                // 发送脚本通知
                $client = HttpClient::create();
                $url = URL_TASK_WORK . '/api/order/generate';
                $client->request('POST', $url, [
                    'body' => [
                        'buyer_id' => $customerId
                    ],
                ]);
                return true;
            } catch (\Exception $e) {
                db()->getConnection()->rollBack();
                Logger::order(['customerId' => $customerId, 'e' => $e->getMessage()], 'error');
            }
        }

        return false;
    }

    /**
     * 更新Invoice状态
     *
     * @param int $id InvoiceID
     * @param int $status 状态
     * @param string $date 时间
     * @param string $filePath 路径
     * @return int
     */
    public function updateInvoice(int $id, int $status, string $date, string $filePath = '')
    {
        $data['status'] = $status;
        if ($filePath) {
            $data['file_path'] = $filePath;
        }
        $data['update_time'] = $date;

        return OrderInvoice::where('id', $id)->update($data);
    }
}