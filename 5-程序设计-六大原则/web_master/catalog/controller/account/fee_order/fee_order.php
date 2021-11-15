<?php

use App\Catalog\Controllers\BaseController;
use App\Catalog\Enums\FeeOrder\FeeOrderSourcePage;
use App\Enums\Country\Country;
use App\Enums\FeeOrder\FeeOrderFeeType;
use App\Enums\FeeOrder\FeeOrderOrderType;
use App\Enums\FeeOrder\FeeOrderStatus;
use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Exception\AssociatedPreException;
use App\Logging\Logger;
use App\Models\FeeOrder\FeeOrder;
use App\Repositories\FeeOrder\FeeOrderRepository;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Repositories\Safeguard\SafeguardConfigRepository;
use App\Repositories\SalesOrder\CustomerSalesOrderRepository;
use App\Services\FeeOrder\FeeOrderService;
use App\Services\Stock\BuyerStockService;
use Carbon\Carbon;
use kriss\bcmath\BCS;
use  App\Models\SalesOrder\CustomerSalesOrder;

/**
 * 费用单
 *
 * Class ControllerAccountFeeOrderFeeOrder
 */
class ControllerAccountFeeOrderFeeOrder extends BaseController
{
    /**
     * @var FeeOrderService
     */
    protected $feeOrderService;

    public function __construct(Registry $registry, FeeOrderService $feeOrderService)
    {
        parent::__construct($registry);
        $this->feeOrderService = $feeOrderService;
    }

    /**
     * 费用单确认页
     * 从费用单列表过来，目前只支持上门取货过来
     *
     * @return string
     */
    public function confirmByFeeOrder()
    {
        $feeOrderId = $this->request->get('fee_order_id');
        $saleOrderIds = $this->request->get('sale_order_id');
        $sourcePage = $this->request->get('source_page', '');
        $feeOrderIdArr = explode(',', $feeOrderId);
        $saleOrderIdArr = explode(',', $saleOrderIds);
        if (!empty($saleOrderIdArr)) {
            // 有可能不传，上门取货费用单点二次支付就只传一个费用单ID
            $isOrderBP = CustomerSalesOrder::whereIn('id', $saleOrderIdArr)->where('order_status', CustomerSalesOrderStatus::BEING_PROCESSED)->exists();
            if ($isOrderBP) {
                return $this->jsonFailed('Sales order status has being processed.');
            }
        }
        //查询费用单信息
        /** @var FeeOrder[]|\Illuminate\Support\Collection $feeOrders */
        $feeOrders = FeeOrder::whereIn('id',$feeOrderIdArr)->get()->groupBy(['fee_type']);
        $storageFeeOrderIds = [];// 仓租费用单
        $safeguardFeeOrderIds = [];// 保障服务费用单
        if(isset($feeOrders[FeeOrderFeeType::STORAGE])){
            $storageFeeOrderIds = $feeOrders[FeeOrderFeeType::STORAGE]->pluck('id')->toArray();
        }
        if(isset($feeOrders[FeeOrderFeeType::SAFEGUARD])){
            $safeguardFeeOrderIds = $feeOrders[FeeOrderFeeType::SAFEGUARD]->pluck('id')->toArray();
        }
        $data = app(FeeOrderRepository::class)->getFeeOrderConfirmDataById($saleOrderIdArr, $storageFeeOrderIds, $safeguardFeeOrderIds, $this->customer->getId());
        if (!$data['need_pay_order']) {
            return $this->jsonFailed('The Charge Order has been paid, duplicate payment is not allowed.');
        }
        $data['fee_order_id'] = $feeOrderId;
        $data['sale_order_ids'] = $saleOrderIds;
        return $this->confirmPage($data, $sourcePage);
    }

    /**
     * 费用单确认页
     *
     * @param $data
     * @param string $sourcePage 来源页面 sales_order|fee_order
     *
     * @return string
     */
    private function confirmPage($data,$sourcePage)
    {
        $this->load->language('account/sales_order/sales_order_management');
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        ];
        $data['second_payment'] = false;//是否二次支付
        if ($sourcePage == FeeOrderSourcePage::SALES_ORDER) {
            //销售订单列表页
            $data['breadcrumbs'][] = [
                'text' => $this->language->get('heading_title_sales_order'),
                'href' => $this->url->link('account/customer_order', ['tabIndex' => 1], true)
            ];
            $data['come_back_url'] = $this->url->link('account/customer_order', ['tabIndex' => 1], true);
            $this->document->setTitle($this->language->get('heading_title_drop_shipping'));
        } else {
            $data['breadcrumbs'][] = [
                'text' => $this->language->get('heading_title_charges'),
                'href' => $this->url->link('account/order', ['#' => 'tab_fee_order'], true)
            ];
            $data['come_back_url'] = $this->url->link('account/order', [], true);
            $this->document->setTitle($this->language->get('heading_title_charges'));
            $data['second_payment'] = true;
        }
        $data['header_tips_is_show'] = $sourcePage != 'sales_order';

        $userCurrency = $this->session->get('currency');
        $data['sales_order_ids'] = implode(',', array_keys($data['order_data']));
        $data['safeguardConfigs'] = app(SafeguardConfigRepository::class)->getBuyerConfigs($this->customer->getId());
        $data['currency'] = $userCurrency;
        $data['currency_symbol'] = $this->currency->getSymbolLeft($userCurrency) . $this->currency->getSymbolRight($userCurrency);
        $data['symbolLeft'] = $this->currency->getSymbolLeft($userCurrency);
        $data['symbolRight'] = $this->currency->getSymbolRight($userCurrency);
        $data['isEurope'] = $this->country->isEuropeCountry($this->customer->getCountryId());
        $data['precision'] = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
        $data['storage_fee_description_id'] = app(StorageFeeRepository::class)->getStorageFeeDescriptionId($this->customer->getCountryId());
        return $this->render('account/fee_order/fee_order_detail', $data, [
            'column_left' => 'common/column_left',
            'column_right' => 'common/column_right',
            'content_top' => 'common/content_top',
            'content_bottom' => 'common/content_bottom',
            'footer' => 'common/footer',
            'header' => 'common/header',
        ]);
    }

    //校验订单是否能支付
    public function checkFeeOrderCanPay()
    {
        $validator = $this->request->validate([
            'fee_order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }
        $feeOrderId = $this->request->get('fee_order_id');
        $feeOrderIdArr = explode(',', $feeOrderId);
        $saleOrderIdArr = explode(',', $this->request->get('sale_order_ids'));
        $isSaleExist = CustomerSalesOrder::query()->whereIn('id', $saleOrderIdArr)->where('order_status', '!=', CustomerSalesOrderStatus::PENDING_CHARGES)->exists();
        if ($isSaleExist) {
            return $this->jsonFailed('Information shown on this screen has been updated.  Please refresh this page.', ['redirect_type' => 'come_back']);
        }
        //校验订单金额和状态是否正确
        $feeOrders = FeeOrder::query()->with(['storageDetails','orderInfo'])->find($feeOrderIdArr);
        $totalMoney = BCS::create(0, ['scale' => 2]);//暂存总费用
        $storageIds = [];//暂存需要支付的仓租ID
        $feeOrderRepo = app(FeeOrderRepository::class);
        foreach ($feeOrders as $feeOrder) {
            //校验费用单是否可以支付
            if (!($feeOrderRepo->isFeeOrderNeedPay($feeOrder)) && $feeOrder->fee_total > 0) {
                return $this->jsonFailed('Information shown on this screen has been updated.  Please refresh this page.', ['redirect_type' => 'come_back']);
            }
            //校验费用单金额是否发生变动，只校验仓租
            if ($feeOrder->fee_type === FeeOrderFeeType::STORAGE) {
                $totalMoney->add($feeOrder->fee_total);
                $storageIds = array_merge($storageIds, $feeOrder->storageDetails->pluck('storage_fee_id')->toArray());
            }
        }
        if (!empty($storageIds)) {
            $nowTotalMoney = app(StorageFeeRepository::class)->getNeedPayByStorageFeeIds($storageIds);
            if ($totalMoney->compare($nowTotalMoney) != 0) {
                //如果当前金额和订单金额不匹配，报错
                return $this->jsonFailed('Information shown on this screen has been updated.  Please refresh this page.', ['redirect_type' => 'refresh']);
            }
        }
        $this->feeOrderService->updateFeeOrderIsShow($feeOrderIdArr, 1);
        return $this->jsonSuccess();
    }

    //费用单二次支付
    public function getFeeOrderPurchasePage(ModelAccountSalesOrderMatchInventoryWindow $modelAccountSalesOrderMatchInventoryWindow)
    {
        $validator = $this->request->validate([
            'fee_order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }
        $feeOrderId = $this->request->get('fee_order_id');
        //获取费用单的信息
        $feeOrderData = FeeOrder::query()->find($feeOrderId);
        $feeOrderNeedPayStatus = app(FeeOrderRepository::class)->getFeeOrderNeedPayStatus($feeOrderData);
        if ($feeOrderNeedPayStatus !== 1) {
            //无需支付
            if (in_array($feeOrderNeedPayStatus, [5, 6, 8])) {
                return $this->jsonFailed('Sales Order information has been changed and  this Purchase Order is no longer valid.');
            } elseif($feeOrderNeedPayStatus == 7) {
                return $this->jsonFailed('The Charge Order has been paid, duplicate payment is not allowed.');
            } else {
                return $this->jsonFailed('Information shown on this screen has been updated.  Please refresh this page.');
            }
        }
        //拿到purchase run id
        if ($feeOrderData->purchase_run_id) {
            // 一件代发
            //获取purchase 信息
            $purchaseRecords = $modelAccountSalesOrderMatchInventoryWindow->getPurchaseRecord($feeOrderData->purchase_run_id, $this->customer->getId(), false);
            //没有订单信息
            if (empty($purchaseRecords)) {
                return $this->jsonFailed('数据错误');
            }
            //获取预绑信息
            $orderAssociatedIds = [];//暂存请求仓租用的参数
            $orderId = 0;
            foreach ($purchaseRecords as $purchaseRecord) {
                //只查询已有库存的
                $associatedPreList = $modelAccountSalesOrderMatchInventoryWindow->getSalesOrderAssociatedPre($purchaseRecord['order_id'], $purchaseRecord['line_id'], $feeOrderData->purchase_run_id, 0, $purchaseRecord['product_id']);
                foreach ($associatedPreList as $associatedPreItem) {
                    if ($associatedPreItem->associate_type == 1) {
                        $orderAssociatedIds[] = $associatedPreItem->id;
                    } elseif ($associatedPreItem->associate_type == 2) {
                        //暂存新采购的采购单id
                        $orderId = $associatedPreItem->order_id;
                    }
                }
            }
            if ($feeOrderData->fee_type === FeeOrderFeeType::STORAGE) {
                $isStorageFee = app(StorageFeeRepository::class)->getAllCanBindNeedPay($orderAssociatedIds, $purchaseRecords);
                if (!$isStorageFee) {
                    //如果没有需要支付的仓租了，直接报错
                    return $this->jsonFailed('This Purchase Order is no longer valid.');
                }
            }
            if($feeOrderData->orderInfo->order_mode == CustomerSalesOrderMode::PICK_UP){
                // 上门取货
                $confirmUrl = $this->url->link('sales_order/confirm'
                    , ['run_id' => $feeOrderData->purchase_run_id, 'order_id' => $orderId, 'source' => 'fee_order']);
            } else {
                //一件代发
                $confirmUrl = $this->url->link('account/sales_order/sales_order_management/salesOrderPurchaseOrderManagement'
                    , ['run_id' => $feeOrderData->purchase_run_id, 'order_id' => $orderId, 'source' => 'fee_order']);
            }

            return $this->jsonSuccess([
                'confirm_url' => $confirmUrl
            ]);
        } else {
            //上门取货
            $confirmQuery = [
                'source_page' => FeeOrderSourcePage::FEE_ORDER
            ];
            if ($feeOrderData->fee_type === FeeOrderFeeType::STORAGE) {
                // 传仓租费用单ID
                $confirmQuery['fee_order_id'] = $feeOrderData->id;
                $confirmQuery['sale_order_id'] = $feeOrderData->order_id;
            } else {
                $storageFeeOrderList = app(FeeOrderRepository::class)->getRelatedFeeOrder($feeOrderData, FeeOrderFeeType::STORAGE, FeeOrderStatus::WAIT_PAY);
                $confirmQuery['fee_order_id'] = $storageFeeOrderList->implode('id', ',');
                $confirmQuery['sale_order_id'] = $storageFeeOrderList->implode('order_id', ',');
            }
            // 查出关联的保障服务费用单
            $safeguardFeeOrderList = app(FeeOrderRepository::class)->getRelatedFeeOrder($feeOrderData, FeeOrderFeeType::SAFEGUARD, FeeOrderStatus::WAIT_PAY);
            if ($safeguardFeeOrderList->isNotEmpty()) {
                $confirmQuery['fee_order_id'] .= ',' . $safeguardFeeOrderList->implode('id', ',');
                $confirmQuery['sale_order_id'] .= ',' . $safeguardFeeOrderList->implode('order_id', ',');
            }
            // 查出保障服务费用单
            $confirmUrl = $this->url->link('account/fee_order/fee_order/confirmByFeeOrder', $confirmQuery);
            return $this->jsonSuccess([
                'confirm_url' => $confirmUrl
            ]);
        }
    }

    //从上门取货销售订单创建仓租费用单
    //成功会返回确认支付页面url
    public function createSalesOrderFeeOrder(CustomerSalesOrderRepository $customerSalesOrderRepository)
    {
        $validator = $this->request->validate([
            'sales_order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }
        $salesOrderId = $this->request->get('sales_order_id');
        $salesOrderIdArr = explode(',', $salesOrderId);
        $salesOrderIdArr = array_unique($salesOrderIdArr);// 数组去重
        //校验订单状态
        $salesOrderIdArr = $customerSalesOrderRepository->checkOrderStatus($salesOrderIdArr, CustomerSalesOrderStatus::PENDING_CHARGES);
        if (empty($salesOrderIdArr)) {
            //没有可支付的订单
            Logger::feeOrder(['创建费用单失败', "支付销售订单:{$salesOrderId}", "没有可支付的销售订单"]);
            return $this->jsonFailed('The order information has changed, please go to Sales Order Management page to view the latest details.');
        }
        //校验是否需要支付仓租 剔除不需要的
        $storageFeeRepo = app(StorageFeeRepository::class);
        //获取仓租信息
        $storageFeeData = $storageFeeRepo->getBoundStorageFeeBySalesOrder($salesOrderIdArr);
        //根据仓租信息生成费用单
        $feeOrderRunId = app(FeeOrderService::class)->generateFeeOrderRunId();
        $feeOrderId = $this->feeOrderService->createSalesFeeOrder($storageFeeData, $feeOrderRunId);
        $this->feeOrderService->updateFeeOrderIsShow(array_values($feeOrderId), 0);
        $feeOrderId = implode(',', array_values($feeOrderId));
        //传递数据
        $data['confirm_url'] = $this->url->link('account/fee_order/fee_order/confirmByFeeOrder', ['fee_order_id' => $feeOrderId,'sale_order_id' => $this->request->get('sales_order_id'), 'source_page' => FeeOrderSourcePage::SALES_ORDER]);
        return $this->jsonSuccess($data);
    }

    // 创建保单费用单
    public function createSafeguardFeeOrder()
    {
        $validator = $this->request->validate([
            'safeguards' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }
        // 获取仓租费用单的run id
        $storageFeeOrderId = $this->request->post('storage_fee_order_id');
        $feeOrderRunId = app(FeeOrderService::class)->generateFeeOrderRunId();
        if ($storageFeeOrderId) {
            /** @var FeeOrder $storageFeeOrder */
            $storageFeeOrder = FeeOrder::whereIn('id', explode(',', $storageFeeOrderId))->first(['fee_order_run_id']);
            if ($storageFeeOrder && $storageFeeOrder->fee_order_run_id) {
                $feeOrderRunId = $storageFeeOrder->fee_order_run_id;
            }
        }
        $safeguards = $this->request->post('safeguards');
        $safeguards = array_combine(array_keys($safeguards), array_column($safeguards, 'safeguard_config_id'));
        $isSaleExist = CustomerSalesOrder::query()->whereIn('id', array_keys($safeguards))->where('order_status', '!=', CustomerSalesOrderStatus::PENDING_CHARGES)->exists();
        if ($isSaleExist) {
            return $this->jsonFailed('Information shown on this screen has been updated.  Please refresh this page.', ['redirect_type' => 'come_back']);
        }
        foreach ($safeguards as $salesOrderId => $safeguardIds) {
            $res = app(SafeguardConfigRepository::class)->checkCanBuySafeguardBuSalesOrder($salesOrderId, $safeguardIds);
            if (!$res['success']) {
                return $this->jsonFailed('Information shown on this screen has been updated.  Please refresh this page.', ['redirect_type' => 'refresh']);
            }
        }
        $feeOrderList = app(FeeOrderService::class)->createSafeguardFeeOrder($safeguards, $feeOrderRunId);
        if ($feeOrderList) {
            $data['safeguard_fee_order_ids'] =  implode(',', $feeOrderList['need_pay_fee_order_list']);
            return $this->jsonSuccess($data);
        }
        return $this->jsonFailed('Information shown on this screen has been updated.  Please refresh this page.', ['redirect_type' => 'refresh']);
    }

    //创建纯费用单
    public function createStorageFeeOrder(ModelAccountSalesOrderMatchInventoryWindow $modelAccountSalesOrderMatchInventoryWindow)
    {
        $validator = $this->request->validate([
            'run_id' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }
        $runId = $this->request->post('run_id');
        $safeguards = $this->request->post('safeguards');
        $totalStorageFee = $this->request->post('total_storage_fee');
        $customerId = $this->customer->getId();
        if (!$runId) {
            return $this->jsonFailed('Error');
        }
        try {
            //校验库存
            $modelAccountSalesOrderMatchInventoryWindow->checkStockpileStockBuPurchaseList($runId, $customerId);

            $this->db->beginTransaction();
            $feeOrderRunId = app(FeeOrderService::class)->generateFeeOrderRunId();
            $feeOrderList = $modelAccountSalesOrderMatchInventoryWindow->createStorageFeeOrderByPurchaseList($runId, $customerId, $totalStorageFee, $feeOrderRunId);
            // 创建保单的费用单
            if ($safeguards) {
                $safeguards = array_combine(array_keys($safeguards), array_column($safeguards, 'safeguard_config_id'));
                $safeguardsFeeOrderList = app(FeeOrderService::class)->findOrCreateSafeguardFeeOrderIdsWithRunId($runId, $customerId, $safeguards, $feeOrderRunId);
                $feeOrderList = array_merge($feeOrderList, $safeguardsFeeOrderList);
            }
            $data['fee_order_list'] = implode(',', $feeOrderList);

            // 销售订单选择使用囤货库存，需锁定囤货库存
            app(BuyerStockService::class)->inventoryLockBySalesOrderPreAssociated((string)$runId, (int)$customerId);

            $this->db->commit();
        } catch (AssociatedPreException $e) {
            $this->db->rollback();
            return $this->jsonFailed('The information displayed on this page has been updated. Please go back to the Sales Order list page to make the payment again.', [], 1);
        } catch (Exception $exception) {
            $this->db->rollback();
            return $this->jsonFailed($exception->getMessage());
        }
        return $this->jsonSuccess($data);
    }

    public function paidFeeOrderJump()
    {
        $storageId = $this->request->get('storage_id');
        $endTime = $this->request->get('end_time');
        $storageIdArr = explode(',', $storageId);
        $where = [];
        //如果有结束时间，要加上条件
        if ($endTime) {
            $where[] = ['created_at', '<', Carbon::createFromTimestamp($endTime)->toDateTimeString()];
        }
        $feeOrders = app(FeeOrderRepository::class)->getPaidFeeOrderByStorage($storageIdArr, $where);
        $feeOrderIds = $feeOrders->pluck('id')->toArray();

        return $this->response->redirectTo($this->url->link('account/order', ['fee_order_id' => implode(',', $feeOrderIds), '#' => 'tab_fee_order'], true));
    }

    /**
     * 保障服务费用单采购明细
     * params: fee_order_id|safeguard_bill_id|sales_order_id 三个任选一个，优先级从上往下，都不传将无法获取数据
     * @return string
     */
    public function safeguardFeeOrderPurchaseDetails()
    {
        return $this->render('account/fee_order/safeguard_fee_order_purchase_details');
    }

    /**
     * 保障服务费用单采购明细数据
     * 前端使用ajax获取
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getSafeguardFeeOrderPurchaseDetailData()
    {
        $data = [];
        $feeOrderId = $this->request->get('fee_order_id', 0);
        $safeguardBillId = intval($this->request->get('safeguard_bill_id', 0));
        $salesOrderId = $this->request->get('sales_order_id', 0);
        $salesOrderLineId = intval($this->request->get('sales_order_line_id', 0));
        $feeOrder = null;
        $purchaseRunId = null;// 一件代发的run id
        if ($feeOrderId) {
            $feeOrder = FeeOrder::where('id', $feeOrderId)
                ->where('order_type', FeeOrderOrderType::SALES)
                ->where('fee_type', FeeOrderFeeType::SAFEGUARD)
                ->first();
        } elseif ($safeguardBillId) {
            // 如果没传费用单传的保单id
            // 通过保单id反查费用单
            $feeOrder = app(FeeOrderRepository::class)->getFeeOrderBySafeguardBillId($safeguardBillId);
        }
        if ($feeOrder) {
            $salesOrderId = $feeOrder->order_id;
            $purchaseRunId = $feeOrder->purchase_run_id;
        }
        $salesOrderId = intval($salesOrderId);
        $purchases = app(CustomerSalesOrderRepository::class)->getPurchasesListBySalesOrderId($salesOrderId, $salesOrderLineId, $purchaseRunId);
        $currency = $this->session->get('currency');
        $data['purchase_list'] = [];
        foreach ($purchases['list'] as $purchase) {
            $purchase['price_str'] = $this->currency->formatCurrencyPrice($purchase['price'], $currency);
            $purchase['freight_str'] = $this->currency->formatCurrencyPrice($purchase['freight'], $currency);
            $purchase['total_amount_str'] = $this->currency->formatCurrencyPrice($purchase['total_amount'], $currency);
            $data['purchase_list'][] = $purchase;
        }
        return $this->response->json($data);
    }
}
