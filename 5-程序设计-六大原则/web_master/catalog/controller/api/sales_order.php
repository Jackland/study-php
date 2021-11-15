<?php

use App\Components\Locker;
use App\Enums\FeeOrder\FeeOrderFeeType;
use App\Enums\FeeOrder\FeeOrderOrderType;
use App\Logging\Logger;
use App\Models\FeeOrder\FeeOrder;
use App\Services\FeeOrder\FeeOrderService;
use App\Services\FeeOrder\StorageFeeService;

class ControllerApiSalesOrder extends ControllerApiBase
{
    protected $storageFeeService;

    public function __construct(Registry $registry, StorageFeeService $storageFeeService)
    {
        parent::__construct($registry);
        $this->storageFeeService = $storageFeeService;

        Logger::apiSalesOrder(['request', $this->request->getMethod(), $this->request->attributes->all()]);
    }

    /**
     销售订单完成回调
     POST index.php?route=api/sales_order/completeNotify
     order_ids=1,2,3
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function completeNotify()
    {
        $validator = $this->request->validateData($this->request->post(), [
            'order_ids' => 'required', // 多个订单ID，英文逗号分隔
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }

        $this->storageFeeService->completeBySalesOrder(explode(',', $this->request->post('order_ids')));

        return $this->jsonSuccess();
    }

    /**
     * 销售订单取消回调
     * POST index.php?route=api/sales_order/cancelNotify
     * order_id=1&need_unbind=0
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function cancelNotify()
    {
        $validator = $this->request->validateData($this->request->post(), [
            'order_id' => 'required|numeric', // 单个订单ID
            'need_unbind' => 'required|boolean', // 是否需要解绑
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }
        $salesOrderId = $this->request->post('order_id');
        $key = "sales_order_api_cancel_notice_{$salesOrderId}";
        $lock = Locker::salesOrderApi($key, 10);
        $return = [
            'success' => true,
            'msg' => ''
        ];
        if (!$lock->acquire()) {
            // 提示未获取到锁
            return $this->jsonFailed('重复请求');
        }
        try {
            $db = db()->getConnection();
            $db->beginTransaction();
            if ($this->request->post('need_unbind')) {
                $this->storageFeeService->unbindBySalesOrder([$salesOrderId]);
            }
            // 取消保障服务费用单
            app(FeeOrderService::class)->cancelSafeguardFeeOrderBySalesOrderId($salesOrderId);
            // 仓租费用单退款
            app(FeeOrderService::class)->refundStorageFeeOrderBySalesOrderId($salesOrderId);
            $db->commit();
        } catch (\Exception $exception) {
            $db->rollBack();
            $return = [
                'success' => false,
                'msg' => $exception->getMessage()
            ];
        } finally {
            $lock->release();
        }
        if (!$return['success']) {
            return $this->jsonFailed($return['msg']);
        }
        return $this->jsonSuccess();
    }

    /**
     * 费用单退款,临时使用，后面要做到task work 内
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function refundFeeOrder()
    {
        $validator = $this->request->validateData($this->request->post(), [
            'fee_order_id' => 'required|array', // 费用单退款
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }
        $feeOrderIds = $this->request->post('fee_order_id');
        $feeOrders = FeeOrder::whereIn('id', $feeOrderIds)->get();
        foreach ($feeOrders as $feeOrder) {
            app(FeeOrderService::class)->refundFeeOrder($feeOrder);
        }
        return $this->jsonSuccess();
    }
}
