<?php

namespace App\Models\Receipt;

use Illuminate\Database\Eloquent\Model;
use App\Models\Message\Message;

class ReceiptsOrder extends Model
{
    const ENTRUSTED_SHIPPING = 1; // 运输方式： 委托海运操作
    const MY_SELF = 2; // 运输方式：客户自发
    const B2B_LOCAL = 3; // 运输方式：B2B Local
    const DIVIDED = 3; // 入库单状态：已分仓

    protected $table = 'tb_sys_receipts_order';
    public $timestamps = false;

    public static function getReceiptShippingItems()
    {
        return [
            self::ENTRUSTED_SHIPPING => 'Consigned Ocean Operation',
            self::MY_SELF => 'Self-arranged Shipping',
            self::B2B_LOCAL => 'B2B Local',
        ];
    }

    // 未入库的订单数量
    public static function getNotStockReceiptOrderCount($customer_id)
    {
        return self::where(['customer_id' => $customer_id])
            ->whereIn('status', [1, 2, 3])
            ->count();
    }

    //遍历 期望船期终止时间后3个与14个自然日 未填写集装箱信息的入库单
    public static function getUnWriteContainerCode()
    {
        $tenDayAfter = date("Y-m-d", strtotime("-3 day"));
        $twentyDayAfter = date("Y-m-d", strtotime("-14 day"));
        $res = self::where('shipping_way', '=', self::MY_SELF)
            ->where('status', '=', self::DIVIDED)
            ->whereNotNull('etd_date_start')
            ->whereNotNull('etd_date_end')
            ->where(function ($query) use ($tenDayAfter, $twentyDayAfter) {
                $query->whereBetween('expected_shipping_date_end', [$tenDayAfter . ' 00:00:00', $tenDayAfter . ' 23:59:59'])
                    ->orWhereBetween('expected_shipping_date_end', [$twentyDayAfter . ' 00:00:00', $twentyDayAfter . ' 23:59:59']);
            })
            ->where(function ($query) {
                $query->where('container_code', '=', '')
                    ->orWhere('shipping_company', '=', '')
                    ->orWhere('etd_date', '=', '')
                    ->orWhere('eta_date', '=', '');
            })
            ->select([
                'customer_id',
                'receive_number',
                'receive_order_id',
                'update_time',
                'shipping_way',
            ])
            ->get();

        foreach ($res as $item) {
            echo $item->receive_order_id, ',';

            $subject = $item->receive_number . ' Please provide the container information for the Incoming Shipment in a timely manner';
            $url = 'index.php?route=customerpartner/warehouse/receipt/view&receive_order_id=' . $item->receive_order_id . '&page_type=confirm_shipping';

            $content = '<table   border="0" cellspacing="0" cellpadding="0">';
            $content .= '<tr><td align="left" style="width: 160px;">Incoming Shipment ID:&nbsp</td><td >' . $item->receive_number . '</td></tr> ';
            $content .= '<tr><td align="left" style="width: 160px;">Modification Time:&nbsp</td><td>' . $item->update_time . '</td></tr>';
            $content .= '<tr><td align="left" style="width: 160px;">Shipping Method:&nbsp</td><td>' . self::getReceiptShippingItems()[$item->shipping_way] . '</td></tr>';
            $content .= '<tr><td style="font-weight: bold" colspan="2" >In order for us to track the shipment and arrange receiving schedule, 
            please <a href="' . $url . '">provide the ocean freight information </a>(container number, ETD, ETA and shipping company) as soon as possible. 
            If the warehouse cannot receive the container on schedule since the related information was not provided in a timely manner, 
            then any additional charges incurred shall be borne by Seller.</td></tr>';
            $content .= '</table>';
            Message::addSystemMessage('receipts', $subject, $content, $item->customer_id);
        }
        echo PHP_EOL;
    }
}