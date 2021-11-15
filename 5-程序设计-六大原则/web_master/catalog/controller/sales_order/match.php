<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Catalog\Forms\SalesOrder\MatchSubmitForm;
use App\Enums\Common\YesNoEnum;
use App\Enums\SalesOrder\HomePickImportMode;
use App\Repositories\SalesOrder\CustomerSalesOrderRepository;
use App\Repositories\SalesOrder\WillCallMatchRepository;
use App\Repositories\Stock\BuyerStockRepository;
use App\Repositories\Stock\StockManagementRepository;
use App\Services\Stock\BuyerStockService;
use Symfony\Component\HttpFoundation\JsonResponse;

class ControllerSalesOrderMatch extends AuthBuyerController
{
    private $buyerId;
    private $countryId;
    private $willCallMathRepo;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->buyerId = $this->customer->getId();
        $this->countryId = $this->customer->getCountryId();
        $this->willCallMathRepo = app(WillCallMatchRepository::class);
    }

    /**
     * 获取New Order信息列表
     *
     * @return JsonResponse
     * @throws Throwable
     */
    public function index()
    {
        $orderIds = trim($this->request->post('orderId', ''), ',');
        $salesOrderId = $this->request->post('salesOrderId', '');


        if (empty($orderIds)) {
            return $this->jsonFailed('Invalid request');
        }
        $orderIds = explode(',', $orderIds);

        // 获取销售单信息
        list($skuArray, $salesOrderList) = $this->getFormatSalesOrderList($orderIds);
        $costList = [];
        $storeList = [];

        if ($skuArray) {
            // 获取囤货库存
            $costList = app(StockManagementRepository::class)->getBuyerCostBySkuToSku($this->buyerId, $skuArray);
            // 匹配采购
            $storeList = $this->willCallMathRepo->getCanBuyProductInfoBySku($this->buyerId, $this->countryId, $skuArray);
        }

        if (empty($salesOrderList)) {
           $errMsg = sprintf('The order (ID: %s) status has changed and the current operation is unavailable.', $salesOrderId);
           return $this->jsonFailed($errMsg);
        }
        $data = compact('salesOrderList', 'costList', 'storeList');
        return $this->jsonSuccess($data);
    }

    /**
     * 获取销售订单预锁情况 (在ControllerSalesOrderMatch::index之前的效验)
     * @return JsonResponse
     */
    public function getSalesOrdersPreLocked(): JsonResponse
    {
        $orderIds = request('order_ids', '');
        if (empty($orderIds)) {
            return $this->jsonFailed('Invalid request');
        }
        $orderIds = explode(',', $orderIds);

        $preLockedOrders = app(BuyerStockRepository::class)->getPreLockedSalesOrdersAndQtyByOrderIds($orderIds, $this->buyerId);
        $preLockedOrderIds = collect(array_values($preLockedOrders))->pluck('order_id')->toArray();
        $preLockedQty = collect(array_values($preLockedOrders))->sum('locked_qty');

        return $this->jsonSuccess(['exist_locked' => intval(!empty($preLockedOrderIds)), 'sales_order_ids' => $preLockedOrderIds, 'locked_qty' => $preLockedQty]);
    }

    /**
     * 释放预锁定 （ControllerSalesOrderMatch::getSalesOrdersPreLocked的确认操作)
     * @return JsonResponse
     */
    public function reassignSalesOrdersPreLocked(): JsonResponse
    {
        $orderIds = request('order_ids', '');
        if (empty($orderIds)) {
            return $this->jsonFailed('Invalid request');
        }
        $orderIds = explode(',', $orderIds);

        app(BuyerStockService::class)->releaseInventoryLockBySalesOrderPreAssociated($orderIds, $this->buyerId);

        return $this->jsonSuccess();
    }

    /**
     * 预绑定销售单
     *
     * @param MatchSubmitForm $form
     * @return JsonResponse
     */
    public function toBuy(MatchSubmitForm $form)
    {
        try {
            $form->save();
        } catch (Exception $e) {
            // 捕获无需任何处理和记录
        }

        if ($form->result['status'] == 200) {
            return $this->jsonSuccess(['runId' => $form->result['data']['runId']]);
        }

        return $this->jsonFailed($form->result['errorMsg'], [], $form->result['status']);
    }

    /**
     * 获取格式弹窗列表数据
     *
     * @param array $orderIds 订单IDS
     * @return array
     */
    private function getFormatSalesOrderList($orderIds)
    {
        $list = $this->willCallMathRepo->getNewOrderList($this->buyerId, $orderIds, $this->countryId);
        $salesOrderList = [];
        $salesSkuList = [];
        if ($list->isNotEmpty()) {
            // 对于自提货
            $isBuyerPickup = $list[0]->import_mode == HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP ? 1 : 0;
            if ($isBuyerPickup) {
                $pickupList = app(CustomerSalesOrderRepository::class)->getPickUpInfoByOrderIds($orderIds);
                if ($pickupList->isNotEmpty()) {
                    foreach ($pickupList as $item) {
                        $pickupArr[$item->sales_order_id] = $item;
                    }
                }
            }

            foreach ($list as $item) {
                $salesSkuList[] = $item['item_code'];
                if (isset($salesOrderList[$item['order_id']])) {
                    $salesOrderList[$item['order_id']]['item_list'][] = [
                        'sales_order_line_id' => $item->line_id,
                        'product_name' => $item->product_name,
                        'item_code' => $item->item_code,
                        'qty' => $item->qty
                    ];
                } else {
                    if ($isBuyerPickup) {
                        $address = empty($pickupArr[$item->id]) ? '' : $pickupArr[$item->id]->user_name . ',' . $pickupArr[$item->id]->user_phone . ',' . $pickupArr[$item->id]->warehouse->fullAddress;
                    } else {
                        $address = app('db-aes')->decrypt($item->ship_address1) .  app('db-aes')->decrypt($item->ship_address2) . ',' .
                            app('db-aes')->decrypt($item->ship_city) . ',' . $item->ship_state . ',' . $item->ship_zip_code . ',' . $item->ship_country;
                    }

                    $salesOrderList[$item['order_id']] = [
                        'sales_order_id' => $item->id,
                        'order_id' => $item->order_id,
                        'ship_address' => $address,
                        'item_list' => [[
                            'sales_order_line_id' => $item->line_id,
                            'product_name' => $item->product_name,
                            'item_code' => $item->item_code,
                            'qty' => $item->qty
                        ]]
                    ];
                }
            }
        }

        return [array_unique($salesSkuList), array_values($salesOrderList)];
    }
}
