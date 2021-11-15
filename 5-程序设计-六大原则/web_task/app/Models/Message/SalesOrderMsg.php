<?php
/**
 * Created by PhpStorm.
 * User: Lu.Chen
 * Date: 2019/11/20
 * Time: 10:52
 */

namespace App\Models\Message;

use Illuminate\Database\Eloquent\Model;

class SalesOrderMsg extends Model
{

    protected $connection = 'mysql_proxy';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /*
     * sales order 发货提醒 每个自然日美国时间16时执行
     * */
    public function deliveryMsg()
    {
        $t = date("Y-m-d H:i:s", strtotime('-1 day'));
        $data = \DB::connection('mysql_proxy')
            ->table('tb_sys_delivery_line as d')
            ->leftjoin('tb_sys_customer_sales_order_tracking as t', 't.Id', 'd.TrackingId')
            ->leftjoin('tb_sys_customer_sales_order as o', 'o.id', 'd.SalesHeaderId')
            ->where(['d.DeliveryType' => 1, 'd.type' => 1])
            ->where('t.status', 1)
            ->where('d.create_time', '>', $t)
            ->select('d.SalesHeaderId', 't.SalesOrderId', 't.ShipSku', 't.TrackingNumber', 'o.buyer_id')
            ->get()
            ->toArray();

        $info = [];
        foreach ($data as $value) {
            $info[$value->SalesHeaderId][] = (array)$value;
        }

        //发送给buyer
        $m = new Message();
        $subject = 'Alter: sales order has been shipped';

        foreach ($info as $k => $v) {

            $message = '<table  border="1px" class="table table-bordered table-hover">';
            $message .= '<thead><tr><td class="text-center">Sales Order ID</td>
                                <td class="text-center">Item Code</td>
                                <td class="text-center">Tracking Number</td></tr></thead><tbody>';
            foreach ($v as $vv) {
                $message .= '<tr><td class="text-center">' . $vv['SalesOrderId'] . '</td>
                             <td class="text-center">' . $vv['ShipSku'] . '</td>
                             <td class="text-center">' . $vv['TrackingNumber'] . '</td></tr>';
            }

            $message .= '</tbody></table>';

            $m->addSystemMessage('sales_order', $subject, $message, $v[0]['buyer_id']);
        }

    }

}