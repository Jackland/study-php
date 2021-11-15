<?php

namespace App\Services\SalesOrder\CancelOrder;

use App\Enums\ModifyLog\CommonOrderActionStatus;
use App\Enums\ModifyLog\CommonOrderProcessCode;
use App\Models\SalesOrder\CustomerOrderModifyLog;
use Carbon\Carbon;

class CommonCancelOrderService
{
    /**
     * 根据订单信息新增log
     * @param array $config ['order_id'=>'','order_status'=> '','order_code' => '','remove_bind'=>'']
     * @return int
     *
     */
    public function addCancelModifyLog(array $config): int
    {
        $order_id = $config['order_id'];
        $order_status = $config['order_status'];
        $order_code = $config['order_code'];
        $remove_bind = $config['remove_bind'];
        $process_code = CommonOrderProcessCode::CANCEL_ORDER;  //操作码 1:修改发货信息,2:修改SKU,3:取消订单
        $status = isset($config['status']) ? $config['status'] : CommonOrderActionStatus::PENDING; //操作状态 1:操作中,2:成功,3:失败
        $run_id =  $config['run_id'];
        $cancel_reason = isset($config['cancel_reason']) ? $config['cancel_reason'] : '';
        //订单类型，暂不考虑重发单
        $order_type = 1;
        $before_record = "Order_Id:" . $order_id . ", status:" . $order_status;
        $modified_record = "Order_Id:" . $order_id . ", status:Cancelled";
        $recordData['process_code'] = $process_code;
        $recordData['status'] = $status;
        $recordData['run_id'] = $run_id;
        $recordData['before_record'] = $before_record;
        $recordData['modified_record'] = $modified_record;
        $recordData['header_id'] = $order_id;
        $recordData['order_id'] = $order_code;
        $recordData['order_type'] = $order_type;
        $recordData['remove_bind'] = $remove_bind;
        $recordData['cancel_reason'] = $cancel_reason;
        $recordData['create_time'] = Carbon::now();
        $recordData['update_time'] = Carbon::now();
        return CustomerOrderModifyLog::query()->insertGetId($recordData);
    }

    /**
     * 外部请求后更新日志
     * @param int $log_id
     * @param int $new_status
     * @param $fail_reason
     * @return bool|int
     */
    public function updateCancelModifyLog(int $log_id, int $new_status, $fail_reason)
    {
        return CustomerOrderModifyLog::query()->where('id', $log_id)->update([
            'status' => $new_status,
            'fail_reason' => $fail_reason,
            'update_time' => Carbon::now(),
        ]);
    }
}
