<?php

use App\Components\RemoteApi;
use App\Enums\SalesOrder\CustomerSalesOrderPickUpStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Logging\Logger;
use App\Models\SalesOrder\CustomerSalesOrderPickUp;
use App\Models\SalesOrder\CustomerSalesOrderPickUpLineChange;
use App\Services\FeeOrder\StorageFeeService;
use App\Services\SalesOrder\SalesOrderPickUpService;
use Symfony\Component\HttpFoundation\JsonResponse;

class ControllerApiSalesOrderPickUp extends ControllerApiBase
{
    protected $storageFeeService;

    public function __construct(Registry $registry, StorageFeeService $storageFeeService)
    {
        parent::__construct($registry);
        $this->storageFeeService = $storageFeeService;

        Logger::apiSalesOrder(['sales_order_pick_up', 'request', $this->request->getMethod(), $this->request->attributes->all()]);
    }

    /**
     * 取消自提货BP-待确认状态的销售单，由定时任务调用
     * POST index.php?route=api/sales_order_pick_up/cancelWaitConfirm
     * order_id=1
     * @return JsonResponse
     */
    public function cancelWaitConfirm()
    {
        $data = $this->request->post();
        $validator = $this->request->validateData($data, [
            'order_id' => 'required', // 销售订单ID
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }

        // 检查销售单
        $salesOrderPickUp = CustomerSalesOrderPickUp::query()->where('sales_order_id', $data['order_id'])->first();
        if (!$salesOrderPickUp) {
            return $this->jsonFailed('非自提货订单');
        }
        $salesOrder = $salesOrderPickUp->salesOrder;
        if (
            $salesOrder->order_status != CustomerSalesOrderStatus::BEING_PROCESSED
            || $salesOrderPickUp->pick_up_status != CustomerSalesOrderPickUpStatus::PICK_UP_INFO_TBC
        ) {
            return $this->jsonFailed('订单状态不正常');
        }

        // 实际取消逻辑
        $cancelData = [
            'id' => $salesOrder->id,
            'orderId' => $salesOrder->order_id,
            'orderStatus' => $salesOrder->order_status,
            'orderDate' => $salesOrder->order_date,
            'removeStock' => 1, // 自动取消固定为 keep in stock
            'reason' => 'The available Warehouse pick-up information was not confirmed within 48 hours.', // 系统取消
        ];
        foreach ($cancelData as $key => $value) {
            request()->input->set($key, $value);
        }
        customer()->loginById($salesOrder->buyer_id);
        $result = load()->controller('account/customer_order/cancelOrder');
        if ($result instanceof JsonResponse) {
            $result = json_decode($result->getContent(), true);
        }

        return $this->jsonSuccess($result);
    }

    /**
     * 生成自提货的BOL文件，由java调用，在仓库确认自提货信息后
     * POST index.php?route=api/sales_order_pick_up/generateBOL
     * order_id=1
     * @return JsonResponse
     */
    public function generateBOL()
    {
        $data = $this->request->post();
        $validator = $this->request->validateData($this->request->post(), [
            'order_id' => 'required', // 销售订单ID
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }

        $is = app(SalesOrderPickUpService::class)->generateBOL($data['order_id']);

        return $is ? $this->jsonSuccess() : $this->jsonFailed();
    }

    /**
     * 处理 buyer 确认时，未成功发送调用 YZCM 的问题
     * 手动调用，用于运维
     * 通过 tb_sys_weixin_taskreminds 监控异常后通过调用该接口处理
     * POST index.php?route=api/sales_order_pick_up/resolveLineChangeNotNotify
     * pick_up_line_change_id=1
     * @return JsonResponse
     */
    public function resolveLineChangeNotNotify()
    {
        $data = $this->request->post();
        $validator = $this->request->validateData($this->request->post(), [
            'pick_up_line_change_id' => 'required', // tb_sys_customer_sales_order_pick_up_line_change 的 id
            'force_send' => 'boolean', // 当成功通知过一次后，修改为1可以强制回调
            'only_solve_notify' => 'boolean', // 仅处理 notify
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }
        $lineChange = CustomerSalesOrderPickUpLineChange::find($data['pick_up_line_change_id']);
        if (!$lineChange) {
            return $this->jsonFailed('不存在');
        }
        if (!$lineChange->is_buyer_accept) {
            return $this->jsonFailed('buyer 未接受');
        }
        if ($lineChange->is_notify_store && !$this->request->post('force_send', false)) {
            return $this->jsonFailed('已经通知过');
        }

        if (!$this->request->post('only_solve_notify', false)) {
            $is = RemoteApi::salesOrder()->sendPickUpBolToOmd($lineChange->sales_order_id);
            if (!$is) {
                return $this->jsonFailed('接口发送失败');
            }
        }
        $lineChange->is_notify_store = 1;
        $lineChange->save();
        return $this->jsonSuccess();
    }

    /**
     * 全部分单 BOL 异常处理，可以重新生成 BOL 文件并发送
     * 手动调用，用于运维
     * POST index.php?route=api/sales_order_pick_up/resolveInvalidBOL
     * sales_order_id=1
     * @return JsonResponse
     */
    public function resolveInvalidBOL()
    {
        $data = $this->request->post();
        $validator = $this->request->validateData($this->request->post(), [
            'sales_order_id' => 'required', // 销售单的 id
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }
        $pickUp = CustomerSalesOrderPickUp::query()->where('sales_order_id', $data['sales_order_id'])->first();
        if (!$pickUp) {
            return $this->jsonFailed('不存在');
        }

        $is = app(SalesOrderPickUpService::class)->generateBOL($data['sales_order_id']);
        if (!$is) {
            return $this->jsonFailed('生成失败');
        }
        $is = RemoteApi::salesOrder()->sendPickUpBolToOmd($pickUp->sales_order_id);
        if (!$is) {
            return $this->jsonFailed('接口发送失败');
        }
        return $this->jsonSuccess();
    }
}
