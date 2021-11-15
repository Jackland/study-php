<?php

namespace App\Console\Commands;

use App\Models\Message\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SalesOrderMessyCodeRemind extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales_order:sales_order_messy_code_remind {country_id} {type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每个国别的中午12点，销售单乱码 通过站内信提醒Buyer。country_id in [81,107,222,223]';

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
     */
    public function handle()
    {
        echo date('Y-m-d H:i:s') . ' sales_order_messy_code_remind 开始......' . PHP_EOL;


        $country_id      = $this->argument('country_id');
        $type            = $this->argument('type');
        $country_id_list = [81, 107, 222, 223];
        $type_list       = ['buyer'];
        if (!in_array($country_id, $country_id_list) || !in_array($type, $type_list)) {
            echo date("Y-m-d H:i:s", time()) . '输入参数（country_id）错误...';
            return;
        }


        $lists = $this->getLists(($country_id));


        if ($lists) {
            $subject = 'Invalid shipping address in %s orders, please modify as soon as possible for on-time shipment. ';
            $head    = ['Sales Order ID', 'Field', 'Content', 'Order Status'];


            $lists_key_buyer = [];
            foreach ($lists as $key => $value) {
                $lists_key_buyer[$value->buyer_id][] = $value;
            }


            $m = new Message();
            foreach ($lists_key_buyer as $buyer_id => $vlists) {

                $order_id_arr = [];
                foreach ($vlists as $row) {
                    $order_id_arr[$row->sales_order_id] = 1;
                }
                $tmp_subject = sprintf($subject, count($order_id_arr));


                $msg = '<p style="margin: 0 0 10px;">Please modify the shipping address below in Sales Order page as soon as possilbe or the shippment may be impacted. If you are not able to modify, please contact the customer service.</p>';
                $msg .= '<table border="1" cellspacing="0" cellpadding="0" width="100%">';
                //thead
                $msg .= '<tr>';
                foreach ($head as $h) {
                    $msg .= '<th style="padding:1px;background-color:#4391c1;color:#FFFFFF;">' . $h . '</th>';
                }
                $msg .= '</tr>';
                //tbody
                foreach ($vlists as $row) {
                    $msg .= '<tr>';
                    $msg .= '<td style="padding:1px">' . $row->sales_order_id . '</td>';
                    $msg .= '<td style="padding:1px">' . $row->Field . '</td>';
                    $msg .= '<td style="padding:1px">' . $row->Content . '</td>';
                    $msg .= '<td style="padding:1px">' . $row->order_status . '</td>';
                    $msg .= '</tr>';
                }
                $msg .= '</table>';

                //发送站内信
                $m->addSystemMessage('sales_order', $tmp_subject, $msg, $buyer_id);
            }
        }


        echo date('Y-m-d H:i:s') . ' sales_order_messy_code_remind 成功' . PHP_EOL;
    }


    public function getLists($country_id)
    {
        $sql = "
    SELECT
        t.order_id AS 'sales_order_id',
        t.country_id,
        t.buyer_id,
        t.Field,
        t.Content,
        t.order_status AS 'order_status'
    FROM
        (
            /*Recipecent	ship_name*/
            (
                SELECT
                    o.order_id,
                    c.country_id,
                    tsd.DicValue AS order_status,
                    o.buyer_id,
                    'Recipecent' AS Field,
                    o.ship_name AS Content
                FROM tb_sys_customer_sales_order AS o
                LEFT JOIN tb_sys_dictionary AS tsd ON o.order_status = tsd.DicKey
                LEFT JOIN oc_customer AS c ON c.customer_id = o.buyer_id
                WHERE o.create_time > DATE_SUB(now(), INTERVAL 30 DAY)
                AND o.order_mode = 1
                AND tsd.DicCategory = 'CUSTOMER_ORDER_STATUS'
                AND o.order_status IN (1, 2, 64, 128)
                AND (
                    o.ship_name LIKE '%?%'
                    OR o.ship_name LIKE '%？%'
                )
                AND c.country_id = {$country_id}
                AND c.customer_group_id NOT IN (24, 25, 26)
            )
            UNION ALL
            /*ShipToPhone	ship_to_phone*/
            (
                SELECT
                    o.order_id,
                    c.country_id,
                    tsd.DicValue AS order_status,
                    o.buyer_id,
                    'ShipToPhone' AS Field,
                    o.ship_phone AS Content
                FROM tb_sys_customer_sales_order AS o
                LEFT JOIN tb_sys_dictionary AS tsd ON o.order_status = tsd.DicKey
                LEFT JOIN oc_customer AS c ON c.customer_id = o.buyer_id
                WHERE o.create_time > DATE_SUB(now(), INTERVAL 30 DAY)
                AND o.order_mode = 1
                AND tsd.DicCategory = 'CUSTOMER_ORDER_STATUS'
                AND o.order_status IN (1, 2, 64, 128)
                AND (
                    o.ship_phone LIKE '%?%'
                    OR o.ship_phone LIKE '%？%'
                )
                AND c.country_id = {$country_id}
                AND c.customer_group_id NOT IN (24, 25, 26)
            )
            /*ShipToPostalCode	ship_zip_code*/
            UNION ALL
            (
                SELECT
                    o.order_id,
                    c.country_id,
                    tsd.DicValue AS order_status,
                    o.buyer_id,
                    'ShipToPostalCode' AS Field,
                    o.ship_zip_code AS Content
                FROM tb_sys_customer_sales_order AS o
                LEFT JOIN tb_sys_dictionary AS tsd ON o.order_status = tsd.DicKey
                LEFT JOIN oc_customer AS c ON c.customer_id = o.buyer_id
                WHERE o.create_time > DATE_SUB(now(), INTERVAL 30 DAY)
                AND o.order_mode = 1
                AND tsd.DicCategory = 'CUSTOMER_ORDER_STATUS'
                AND o.order_status IN (1, 2, 64, 128)
                AND (
                    o.ship_zip_code LIKE '%?%'
                    OR o.ship_zip_code LIKE '%？%'
                )
                AND c.country_id = {$country_id}
                AND c.customer_group_id NOT IN (24, 25, 26)
            )
            /*ShipToAddressDetail	ship_address1*/				
            UNION ALL
            (
                SELECT
                    o.order_id,
                    c.country_id,
                    tsd.DicValue AS order_status,
                    o.buyer_id,
                    'ShipToAddressDetail' AS Field,
                    o.ship_address1 AS Content
                FROM tb_sys_customer_sales_order AS o
                LEFT JOIN tb_sys_dictionary AS tsd ON o.order_status = tsd.DicKey
                LEFT JOIN oc_customer AS c ON c.customer_id = o.buyer_id
                WHERE o.create_time > DATE_SUB(now(), INTERVAL 30 DAY)
                AND o.order_mode = 1
                AND tsd.DicCategory = 'CUSTOMER_ORDER_STATUS'
                AND o.order_status IN (1, 2, 64, 128)
                AND (
                    o.ship_address1 LIKE '%?%'
                    OR o.ship_address1 LIKE '%？%'
                )
                AND c.country_id = {$country_id}
                AND c.customer_group_id NOT IN (24, 25, 26)
            )
            /*ShipToCity	ship_city*/				
            UNION ALL
            (
                SELECT
                    o.order_id,
                    c.country_id,
                    tsd.DicValue AS order_status,
                    o.buyer_id,
                    'ShipToCity' AS Field,
                    o.ship_city AS Content
                FROM tb_sys_customer_sales_order AS o
                LEFT JOIN tb_sys_dictionary AS tsd ON o.order_status = tsd.DicKey
                LEFT JOIN oc_customer AS c ON c.customer_id = o.buyer_id
                WHERE o.create_time > DATE_SUB(now(), INTERVAL 30 DAY)
                AND o.order_mode = 1
                AND tsd.DicCategory = 'CUSTOMER_ORDER_STATUS'
                AND o.order_status IN (1, 2, 64, 128)
                AND (
                    o.ship_city LIKE '%?%'
                    OR o.ship_city LIKE '%？%'
                )
                AND c.country_id = {$country_id}
                AND c.customer_group_id NOT IN (24, 25, 26)
            )
            /*ShipToState	ship_state*/				
            UNION ALL
            (
                SELECT
                    o.order_id,
                    c.country_id,
                    tsd.DicValue AS order_status,
                    o.buyer_id,
                    'ShipToState' AS Field,
                    o.ship_state AS Content
                FROM tb_sys_customer_sales_order AS o
                LEFT JOIN tb_sys_dictionary AS tsd ON o.order_status = tsd.DicKey
                LEFT JOIN oc_customer AS c ON c.customer_id = o.buyer_id
                WHERE o.create_time > DATE_SUB(now(), INTERVAL 30 DAY)
                AND o.order_mode = 1
                AND tsd.DicCategory = 'CUSTOMER_ORDER_STATUS'
                AND o.order_status IN (1, 2, 64, 128)
                AND (
                    o.ship_state LIKE '%?%'
                    OR o.ship_state LIKE '%？%'
                )
                AND c.country_id = {$country_id}
                AND c.customer_group_id NOT IN (24, 25, 26)
            )
            
        ) AS t
    ORDER BY t.order_id ASC, FIND_IN_SET(t.Field,'Recipecent,ShipToPhone,ShipToPostalCode,ShipToAddressDetail,ShipToCity,ShipToState')";

        $query = \DB::connection('mysql_proxy')->select($sql);

        return $query;
    }
}
