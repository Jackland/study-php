<?php

namespace App\Models\SalesOrder;

use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\SalesOrder\HomePickImportMode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Message\Message;


class SalesOrder extends Model{
    const COUNTRY_TIME_ZONES = [
        223 => 'America/Los_Angeles',
        222 => 'Europe/London',
        107 => 'Asia/Tokyo',
        81 => 'Europe/Berlin'
    ];
    protected $day = 7;
    protected $message;
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->message = new Message();
    }

    public function updateSalesOrderOnHold()
    {
        \Log::info('--------updateSalesOrderStart---------' . PHP_EOL);
        try {
            \DB::beginTransaction();
            $builder = \DB::table('tb_sys_customer_sales_order as cso')
                ->leftJoin('oc_customer as oc','oc.customer_id','=','cso.buyer_id')
                ->where('cso.order_mode', 1) // 一件代发销售订单
                ->whereIn('cso.order_status', [1, 64]) // on hold 的
                ->whereNotIn('oc.customer_group_id',[25, 24, 26])
                ->whereDate('cso.update_time', '<', date('Y-m-d H:i:s', time() - $this->day * 86400));
            // 发送站内信
            $list = $builder->get()
                ->map(
                    function ($value) {
                        return (array)$value;
                    })
                ->toArray();
            if ($list) {
                foreach ($list as $key => $value) {
                    //1.order hold 2.地址有误 3.无效运单号 4.LtL提醒 其中 1，3，4 都是在yzc_task_work
                    $this->sendSystemMessage(1, $value);
                }
            }
            // 更新数据
            $builder->update(['order_status' => 4]);
            \DB::commit();
        }catch (\Exception $e) {
            \DB::rollBack();
            \Log::error($e);
        }

        \Log::info('--------updateSalesOrderEnd---------' . PHP_EOL);
    }

    /**
     * [sendSystemMessage description] 1.order hold 2.地址有误 3.无效运单号 4.LtL提醒 其中 1，3，4 都是在yzc_task_work
     * @param $type
     * @param $order_info
     * @throws \Exception
     */
    public function sendSystemMessage($type,$order_info)
    {
        $ret = $this->setTemplateOfCommunication($type,$order_info);
        if($ret){
            $this->message->addSystemMessage('sales_order', $ret['subject'],$ret['message'],$ret['received_id']);
        }
    }

    public function setTemplateOfCommunication($type,$order_info)
    {
        $subject = '';
        $message = '';
        $received_id = $order_info['buyer_id'];
        $date = $order_info['create_time'];
        $order_code = $order_info['order_id'];
        $order_id = $order_info['id'];
        if($type == 1){
            $subject .= 'Sales Order has been On Hold';
            $message .= '<table   border="0" cellspacing="0" cellpadding="0">';
            $message .= '<tr><td align="left">Sales Order ID:&nbsp</td><td style="width: 650px">
                          <a target="_blank" href="index.php?route=account/sales_order/sales_order_management/customerOrderSalesOrderDetails&id='.$order_id.'">' .$order_code. '</a>
                          </td></tr> ';
            $message .= '<tr><td align="left">Create Date:&nbsp</td><td style="width: 650px">'.$date.'</td></tr>';
            $message .= '<tr><th align="left" colspan="2">There is no action to the order for 7 days, Order has been on hold. Please release it first then to purchase it.</th></tr>';
            $message .= '</table>';
        }else{
            return false;
        }
        $ret['subject'] = $subject;
        $ret['message'] = $message;
        $ret['received_id']  = $received_id;
        return $ret;
    }

    /**
     * 上门取货Buyer 销售订单 [To Be Paid] 超过7天的订单，将订单状态置为 [On Hold] 
     * 举例，SO#001在07.10日10:00流转为【To Be Paid】状态，若Buyer在07.16日 23:59:59前一直未手动选择库存，则该订单将于07.16日 23:59:59时由【To Be Paid】流转为【On Hold】。
     * @param $countryId
     * @return bool
     * @throws \Throwable
     */
    public function pickUpFromToBePaidToOnHold($countryId)
    {
        if (!in_array($countryId, array_keys(self::COUNTRY_TIME_ZONES))) {
            return false;
        }
        
        $day = \DB::table('oc_setting')->select('value')->where('key', '=', 'salesOrderPickUpFromToBePaidToOnHoldDay')->value('value');
        $day = intval($day);
        if ($day < 1) {
            return false;
        }

        //$timePoint = Carbon::parse('-'.$day.' days')->toDateTimeString();
        $timeZone = self::COUNTRY_TIME_ZONES[$countryId];
        $timePoint = Carbon::now()->timezone($timeZone)->subDays($day)->format('Y-m-d 23:59:59');//7天前目标国别时间
        $timePoint = $this->exchangeTimeToUSA($timePoint, $countryId);//转为美国时间
        
        $builder = \DB::table('tb_sys_customer_sales_order AS cso')
            ->leftJoin('oc_customer AS c', 'cso.buyer_id', '=', 'c.customer_id')
            ->select('cso.id')
            ->where('cso.order_status', '=', CustomerSalesOrderStatus::TO_BE_PAID)
            ->where('cso.order_mode', '=', CustomerSalesOrderMode::PICK_UP)
            //->where('cso.import_mode', '>', HomePickImportMode::IMPORT_MODE_NORMAL)
            ->where('cso.to_be_paid_time', '<=', $timePoint)
            ->whereNotNull('cso.to_be_paid_time')
            ->where('c.country_id', '=', $countryId);
        $results = $builder->get()->pluck('id')->toArray();
        \Log::info('--------updateSalesOrderPickUpFromToBePaidToOnHoldStart countryId=' . $countryId . '---------' . PHP_EOL);
        try {
            if ($results) {
                \DB::transaction(function () use ($results) {
                    \DB::table('tb_sys_customer_sales_order')->whereIn('id', $results)->update(['order_status' => CustomerSalesOrderStatus::ON_HOLD]);
                });
            }
        } catch (\Exception $e) {
            \Log::error($e);
            return false;
        }
        \Log::info('--------updateSalesOrderPickUpFromToBePaidToOnHoldEnd countryId=' . $countryId . '---------' . PHP_EOL);
        return true;
    }

    /**
     * 其他时区转为 太平洋时区
     * @param string $time 'Y-m-d H:i:s'
     * @param int $countryId fromCountryId
     * @param string $format
     * @return string
     * @throws \Exception
     */
    private function exchangeTimeToUSA($time, $countryId, $format = 'Y-m-d H:i:s'): string
    {
        if (!in_array($countryId, array_keys(self::COUNTRY_TIME_ZONES))) {
            return $time;
        }

        if ($countryId == 223) {
            if ($format == 'Y-m-d H:i:s') {
                return $time;
            }
            return date($format, strtotime($time));
        }

        return (new \DateTime($time, new \DateTimeZone(self::COUNTRY_TIME_ZONES[$countryId])))
            ->setTimezone(new \DateTimeZone(self::COUNTRY_TIME_ZONES[223]))
            ->format($format);
    }
}