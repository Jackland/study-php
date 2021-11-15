<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Safeguard\SafeguardBillStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\YzcRmaOrder\RmaApplyType;
use App\Enums\YzcRmaOrder\RmaType;
use App\Models\Rma\YzcRmaOrder;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Repositories\Margin\MarginRepository;
use App\Repositories\Rebate\RebateRepository;
use App\Repositories\Rma\RamRepository;
use App\Repositories\Safeguard\SafeguardBillRepository;

/**
 * Class ControllerAccountRmaCreateRma
 *
 * @property ModelAccountRmaManagement $model_account_rma_management
 * @property ModelAccountRmaManage $model_account_rma_manage
 * @property ModelAccountOrder $model_account_order
 * @property ModelCustomerpartnerRmaManagement $model_customerpartner_rma_management
 */
class ControllerAccountRmaCreateRma extends AuthBuyerController
{
    const SALES_ORDER_TYPE = 1;
    const PURCHASE_ORDER_TYPE = 2;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        // model
        $this->load->model('account/rma_management');
        $this->load->model('account/rma/manage');
        $this->load->model('account/order');
        $this->load->model('customerpartner/rma_management');
        // language
        $this->language->load('account/rma_management');
    }

    public function index()
    {
        $request = $this->request->attributes->all();
        $order_type = (int)$request['order_type'];
        $product_id = (int)$request['product_id'];
        // asr 不能申请rma
        if ($product_id == $this->config->get('signature_service_us_product_id')) {
            return $this->response->json(['code' => 1, 'msg' => $this->language->get('error_fedex_service_fee')]);
        }
        if ($request['order_id'] && $request['product_id']) {
            switch ($order_type) {
                case static::PURCHASE_ORDER_TYPE:
                    return $this->createPurchaseOrderRmaHtml();
                case static::SALES_ORDER_TYPE:
                    return $this->createSalesOrderRmaHtml();
                default:
                    break;
            }
        }
        return $this->response->json(['code' => 1, 'msg' => 'Error Operation.']);
    }

    // 创建采购订单rma页面
    private function createPurchaseOrderRmaHtml()
    {
        $ret = ['code' => 0, 'msg' => '', 'data' => ''];
        $order_id = request('order_id');
        $product_id = request('product_id');
        $data = request()->attributes->all();
        $order = $this->model_account_rma_manage->getPurchaseOrderInfo($order_id, $product_id);
        if ($order['quantity'] == 0) {
            $ret = ['code' => 1, 'msg' => $this->language->get('error_max_return'), 'data' => ''];
            return $this->response->json($ret);
        }
        //校验是否可用申请rma
        $count = $this->checkProcessingPurchaseRmaByOrderId($order_id, $order['order_product_id'], $product_id, $this->customer->getId());
        if ($count > 0) {
            $ret['code'] = 1;
            $ret['msg'] = $this->language->get('error_purchase_rma_exist');
            return $this->response->json($ret);
        }
        $data['order'] = $order;
        $data['order_id'] = str_replace(['#', ' ', '?', '.'], '_', $order_id . $product_id);
        $data['reasons'] = $this->model_account_order->getRmaReason();
        $data['currency'] = $currency = $this->session->get('currency');
        $data['is_japan'] = $this->customer->isJapan() ? 1 : 0;
        $data['isEurope'] = $this->customer->isEurope();
        $data['unit_refund'] = $order['total'] / $order['quantity'];
        // currency symbol
        $data['left_symbol'] = $this->currency->getSymbolLeft(session('currency'));
        $data['right_symbol'] = $this->currency->getSymbolRight(session('currency'));
        // refund range
        $data['refundRange'] = app(RamRepository::class)->getRefundRange($order['total'], $order['quantity']);
        // 判断返点
        $rebateInfo = app(RebateRepository::class)
            ->checkRebateRefundMoney($order['total'], $order['quantity'], $order_id, $product_id);
        if ($rebateInfo) {
            $data['refundRange'] = $rebateInfo['refundRange'] ?? $data['refundRange'];
            $data['tipBuyerMsg'] = $rebateInfo['buyerMsg'];
        }
        //判断优惠券&活动
        $isJapan = customer()->isJapan() ? 1 : 0;
        $couponCampaign = app(RamRepository::class)->getReturnCouponAndCampaign($order_id, $product_id, $order['order_product_id'], $order['quantity'], $isJapan);
        foreach ($data['refundRange'] as $key => $range) {
            $data['refundRange'][$key] = bcsub($range, $couponCampaign[$key], 2);
        }
        // 计算仓租
        $marginStorageFeeIds = [];
        if ($order['type_id'] == ProductTransactionType::MARGIN) {
            // 判断协议是否过期
            $isExpired = app(MarginRepository::class)->checkAgreementIsExpired($order['agreement_id']);
            if (!$isExpired) {
                $marginStorageFeeIds = app(StorageFeeRepository::class)->getOrderProductMarginRestStorageFeeByAssociated($order['order_product_id'], $order['agreement_id']);
            }
        }
        if (!empty($marginStorageFeeIds)) {
            $data['msg_storage_fee'] = $this->language->get('tip_margin_storage_fee');
        } else {
            $calculateStorageFee = app(RamRepository::class)
                ->getPurchaseOrderCalculateStorageFee($order['order_product_id'], $order['quantity']);
            $data['msg_storage_fee'] = ($calculateStorageFee !== null)
                ? sprintf(
                    $this->language->get('tip_storage_fee'),
                    $this->currency->format($calculateStorageFee, $this->session->get('currency'))
                )
                : null;
        }
        $ret['data'] = $this->load->view('account/rma_management/purchase_order_rma', $data);
        return $this->response->json($ret);
    }

    // 创建销售订单rma页面
    private function createSalesOrderRmaHtml()
    {
        $ret = ['code' => 0, 'msg' => '', 'data' => ''];
        $request = $this->request->attributes->all();
        $customer_id = (int)$this->customer->getId();
        $customer_order_id = $request['order_id'];
        $purchase_order_id = $request['purchase_order_id'];
        $product_id = (int)$request['product_id'];
        // 对应销售单采购单
        $data = $request;
        $purchase_orders = $this->model_account_rma_manage->getSalesOrderInfo($customer_id, $customer_order_id, $product_id);
        $customer_order = $this->model_account_rma_manage->getCustomerOrdersByOrderId($customer_id, $customer_order_id);
        $customer_order['detail_address'] = implode(',', array_filter([
            $customer_order['ship_address1'],
            $customer_order['ship_city'],
            $customer_order['ship_zip_code'],
            $customer_order['ship_state'],
            $customer_order['ship_country']
        ]));
        $data['product'] = $this->model_account_rma_manage->getProductInfo($request['product_id']);
        $data['reasons'] = $this->model_account_rma_management->getRmaReason(
            $customer_order['order_status'] == CustomerSalesOrderStatus::COMPLETED ? CustomerSalesOrderStatus::COMPLETED : CustomerSalesOrderStatus::CANCELED
        );
        $data['purchase_orders'] = $purchase_orders;
        $current_order = $purchase_orders[0];
        foreach ($purchase_orders as $order) {
            if ($order['order_id'] == $purchase_order_id && $order['product_id'] == $product_id) {
                $current_order = $order;
            }
        }
        // 校验rma是否可以申请
        // 现在取消的销售单只能申请一次
        if ($this->checkRmaExistByOrderId(
            $customer_id, $customer_order_id, $purchase_order_id, $product_id,
            (int)$current_order['qty']
        )){
            $ret['code'] = 2;
            $ret['msg'] = $this->language->get('error_sales_order_rma');
            return $this->json($ret);
        }
        // 销售单rma 之前还未处理
        $count = $this->checkProcessingRmaByOrderId($customer_id, $customer_order_id, $product_id);
        if ($count > 0) {
            $ret['code'] = 1;
            $ret['msg'] = $this->language->get('error_sales_rma_exist');
        }
        // 特例的情况下 完成的云送仓订单是不可申请rma的
        if (
            ($current_order['delivery_type'] != 2)
            && (!in_array($customer_order['order_status'], [CustomerSalesOrderStatus::CANCELED, CustomerSalesOrderStatus::COMPLETED]))
        ) {
            $ret['code'] = 1;
            $ret['msg'] = $this->language->get('error_order_status');
        }
        if ($current_order['delivery_type'] == 2) {
            $cwf_line = $this->model_account_rma_manage->getCWFInfo($purchase_order_id, $customer_order['id']);
            if ($cwf_line && !in_array($cwf_line['cwf_status'], [7, 16])) {
                $ret['code'] = 1;
                $ret['msg'] = $this->language->get('error_CWF_RMA');
            }
            if ($cwf_line && in_array($cwf_line['cwf_status'], [7, 16])) {
                $data['cwf_order'] = $cwf_line;
            }
        }
        if ($ret['code'] == 1) {
            return $this->json($ret);
        }
        $data['current_order'] = $current_order;
        $data['currency'] = session('currency');
        $data['rma_order_id'] = $this->model_account_rma_management->getRmaIdTemp();
        $data['customer_order'] = $customer_order;
        $data['is_japan'] = $this->customer->isJapan() ? 1 : 0;
        $data['order_id'] = str_replace(['#', ' ', '?', '.'], '_', $customer_order_id)
            . $purchase_order_id
            . $product_id;
        // 查询销售单是否购买保障服务
        if ($customer_order['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
            $data['safeguard_bill_list'] = app(SafeguardBillRepository::class)->getSafeguardBillsBySaleId($customer_order['id'] ?? 0, SafeguardBillStatus::ACTIVE);
        }
        $ret['data'] = $this->load->view('account/rma_management/sales_order_rma', $data);

        return $this->json($ret);
    }

    private function checkProcessingRmaByOrderId($customer_id, $customer_order_id, $product_id)
    {
        return $this->orm->table('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->where([
                ['ro.buyer_id', '=', $customer_id],
                ['ro.from_customer_order_id', '=', $customer_order_id],
                ['rop.product_id', '=', $product_id],
            ])
            ->whereRaw('(ro.seller_status <>2 and ro.cancel_rma <>1)')
            ->count();
    }

    /**
     * 校验能否申请销售单rma
     * 如若之前申请的已完成或者正在处理 就不能申请
     * @param int $customer_id BuyerId
     * @param string $customer_order_id RMA来自销售订单
     * @param int $purchase_order_id
     * @param int $product_id
     * @param int $qty
     * @return bool
     */
    private function checkRmaExistByOrderId(
        $customer_id, $customer_order_id, $purchase_order_id, $product_id, $qty
    ): bool
    {
        $rmaList = YzcRmaOrder::query()->alias('ro')
            ->with(['yzcRmaOrderProduct'])
            ->select('ro.*')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->where([
                ['ro.buyer_id', '=', $customer_id],
                ['ro.from_customer_order_id', '=', $customer_order_id],
                ['ro.order_id', '=', $purchase_order_id],
                ['rop.product_id', '=', $product_id],
            ])
            ->where('ro.cancel_rma', '<>', 1)
            ->get();
        // 找出其中已经处理的 或者 正在处理的数据
        if ($rmaList->isEmpty()) {
            return false;
        }
        $res = $rmaList->map(function ($rma) {
            $ret = 0;
            // seller status为2 表示已经处理完成
            if ($rma->seller_status != 2) {
                $ret = 1;
            }
            /** @var YzcRmaOrder $rma */
            switch ($rma->yzcRmaOrderProduct->rma_type) {
                case RmaApplyType::RESHIP :
                {
                    if ($rma->seller_status == 2 && $rma->yzcRmaOrderProduct->status_reshipment == 1) {
                        $ret = 1;
                    }
                    break;
                }
                case RmaApplyType::REFUND :
                {
                    if ($rma->seller_status == 2 && $rma->yzcRmaOrderProduct->status_refund == 1) {
                        $ret = 1;
                    }
                    break;
                }
                case RmaApplyType::RESHIP_AND_REFUND :
                {
                    if (
                        $rma->seller_status == 2
                        && (
                            $rma->yzcRmaOrderProduct->status_refund == 1
                            || $rma->yzcRmaOrderProduct->status_reshipment == 1
                        )
                    ) {
                        $ret = 1;
                    }
                    break;
                }
            }
            return $ret;
        })->toArray();

        return array_sum($res) >= $qty * 3;
    }

    private function checkProcessingPurchaseRmaByOrderId($orderId, $orderProductId, $productId, $customerId)
    {
        return $this->orm->table('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->where([
                ['ro.buyer_id', '=', $customerId],
                ['rop.order_product_id', '=', $orderProductId],
                ['rop.product_id', '=', $productId],
                ['ro.order_id', '=', $orderId],
                ['ro.order_type', '=', RmaType::PURCHASE_ORDER]
            ])
            ->whereRaw('(ro.seller_status <>2 and ro.cancel_rma <>1)')
            ->count();
    }
}
