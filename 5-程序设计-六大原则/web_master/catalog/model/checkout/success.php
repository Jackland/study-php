<?php

use App\Enums\Order\OcOrderStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Logging\Logger;
use App\Models\Customer\Customer;
use App\Models\Margin\MarginProcess;
use App\Models\Order\OrderHistory;
use App\Repositories\FeeOrder\FeeOrderRepository;
use App\Repositories\Margin\MarginRepository;
use Carbon\Carbon;

/**
 * Class ModelCheckoutSuccess
 * @property ModelAccountNotification $model_account_notification
 * @property ModelCheckoutOrder $model_checkout_order
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelExtensionModuleProductHome $model_extension_module_product_home
 * @property ModelCatalogProductColumn $model_catalog_product_column
 * @property ModelMessageMessage $model_message_message
 * */
class ModelCheckoutSuccess extends Controller
{
    const RECOMMEND_PRODUCT_NUM = 20;//推荐商品 个数
    //不用这个
    public function clearCart($isBySession = false): void
    {
        if (isset($this->session->data['order_id'])) {
            $this->cart->clear($isBySession);
            if ($this->customer->isLogged()) {

                $this->load->model('account/notification');

                $activity_data = array(
                    'customer_id' => $this->customer->getId(),
                    'name' => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
                    'order_id' => $this->session->data['order_id'],
                    'status'=>"5"
                );

                //$this->model_account_notification->addActivity('order_status', $activity_data);
                $this->load->model('checkout/order');
                $this->model_checkout_order->addSystemMessageAboutOrderStatus($activity_data);
            }


            $this->session->remove('shipping_method');
            $this->session->remove('shipping_methods');
            $this->session->remove('payment_method');
            $this->session->remove('payment_methods');
            $this->session->remove('guest');
            $this->session->remove('comment');
            $this->session->remove('order_id');
            $this->session->remove('coupon');
            $this->session->remove('reward');
            $this->session->remove('voucher');
            $this->session->remove('vouchers');
            $this->session->remove('totals');
            //quote product
            $this->session->remove('quote_product');
            // useBalance
            $this->session->remove('useBalance');
            //清除delivery_type
            $this->session->remove('delivery_type');
            //清除cwf_id
            $this->session->remove('cwf_id');
        }
    }

    //依据订单ID清理购物车 by CL
    public function clearCartByOrderId($orderId)
    {
        $customerId = $this->customer->getId();
        //清购物车
        $orderProduct = $this->orm->table('oc_order_product as op')
            ->leftJoin('oc_order as o', 'o.order_id', '=', 'op.order_id')
            ->where('op.order_id', '=', $orderId)
            ->whereIn('o.order_status_id', [OcOrderStatus::COMPLETED,OcOrderStatus::CHARGEBACK])
            ->when($customerId, function ($query) use ($customerId){
                return $query->where('o.customer_id', '=', $customerId);
            })
            ->select(['op.product_id','o.delivery_type','o.customer_id', 'op.quantity'])
            ->get();
        $apiId = isset($this->session->data['api_id']) ? (int)$this->session->data['api_id'] : 0;
        foreach ($orderProduct as $k=>$v)
        {
            $cart = $this->orm->table('oc_cart')
                ->where([
                    'customer_id'   => $v->customer_id,
                    'product_id'    => $v->product_id,
                    'delivery_type' => $v->delivery_type,
                    'api_id'        => $apiId,
                ])->first();
            if (empty($cart)) {
                continue;
            }

            if ($apiId == 1 || $cart->quantity <= $v->quantity) {
                $this->orm->table('oc_cart')->where('cart_id', $cart->cart_id)->delete();
            } else {
                $this->orm->table('oc_cart')->where('cart_id', $cart->cart_id)->decrement('quantity', $v->quantity);
            }
        }

        $this->session->remove('shipping_method');
        $this->session->remove('shipping_methods');
        $this->session->remove('payment_method');
        $this->session->remove('payment_methods');
        $this->session->remove('guest');
        $this->session->remove('comment');
        $this->session->remove('order_id');
        $this->session->remove('coupon');
        $this->session->remove('reward');
        $this->session->remove('voucher');
        $this->session->remove('vouchers');
        $this->session->remove('totals');
        //quote product
        $this->session->remove('quote_product');
        // useBalance
        $this->session->remove('useBalance');
        //清除delivery_type
        $this->session->remove('delivery_type');
        //清除cwf_id
        $this->session->remove('cwf_id');
        //第一版现货保证金相关 不确定是否还有用
        $this->session->remove('orderMarginStock');
    }

    /**
     * 发送支付成功消息通知
     * @param int $orderId 采购订单ID
     * @param Customer|null $customer
     * @throws Exception
     */
    public function paySuccessMessage($orderId, ?Customer $customer)
    {
        //发送支付成功消息通知

        $isMarginAdvance = false;//是否为 现货保证金头款
        $marginProcessCollection = MarginProcess::query()->where('advance_order_id', '=', $orderId)->first();
        if ($marginProcessCollection) {
            $isMarginAdvance = true;
        }
        if ($isMarginAdvance) {
            //是 现货保证金头款
            $this->load->language('account/product_quotes/margin');
            $this->load->model('message/message');

            $agreementId = $marginProcessCollection->margin_id;
            $agreementDetail = app(MarginRepository::class)->getMarginAgreementInfo($agreementId);
            $msg_subject = sprintf($this->language->get('margin_advance_pay_subject'),
                $agreementDetail['agreement_id'],
                $agreementDetail['nickname'] . ' (' . $agreementDetail['user_number'] . ') '
            );
            $format = '%.2f';
            if ($agreementDetail['country_id'] == JAPAN_COUNTRY_ID) {
                $format = '%d';
            }
            $timePayment = OrderHistory::query()->where('order_id', '=', $orderId)->where('order_status_id', '=', 5)->value('date_added');
            is_null($timePayment) ? $timePayment = Carbon::now() : 0;
            $msg_content = sprintf($this->language->get('margin_advance_pay_content'),
                $this->url->link('account/product_quotes/margin_contract/view', ['agreement_id' => $agreementDetail['agreement_id']]),
                $agreementDetail['agreement_id'],
                $agreementDetail['nickname'] . ' (' . $agreementDetail['user_number'] . ') ',
                $agreementDetail['sku'] . '/' . $agreementDetail['mpn'],
                $agreementDetail['day'],
                $agreementDetail['num'],
                sprintf($format, $agreementDetail['unit_price']),
                $this->url->link('account/customerpartner/orderinfo', ['order_id' => $orderId]),
                $orderId,
                sprintf($format, $agreementDetail['sum_price']),
                $timePayment
            );
            $msgRet = $this->model_message_message->addSystemMessageToBuyer('bid_margin',
                $msg_subject,
                $msg_content,
                $agreementDetail['seller_id']
            );
            if ($msgRet !== true) {
                Logger::app(__FILE__ . " Message send Failed!  " . $msgRet);
            }
        } else {
            if ($customer) {
                $activity_data = array(
                    'customer_id' => $customer->customer_id,
                    'name' => $customer->firstname . ' ' . $customer->lastname,
                    'order_id' => $orderId,
                    'status' => 5
                );
                $this->load->model('checkout/order');
                $this->model_checkout_order->addSystemMessageAboutOrderStatus($activity_data);
            }
        }
    }

    public function getOrderStatus($orderId)
    {
        return $this->orm->table('oc_order')
            ->where('order_id', '=', $orderId)
            ->value('order_status_id');
    }

    //支付成功页 需要展示的数据
    public function successShow($orderId,$feeOrderIdArr)
    {
        $feeOrderRepo = app(FeeOrderRepository::class);
        $total = 0;
        if (empty($orderId) && empty($feeOrderIdArr)) {
            return [
                'order' => ['payment_method' => 'Line Of Credit', 'total' => $this->currency->format(0, $this->session->get('currency'))],
                'products' => [],
            ];
        }
        if(!empty($orderId)) {
            $order = $this->orm->table('oc_order')
                ->where('order_id', $orderId)
                ->select(['payment_method', 'payment_code'])
                ->first();
            $orderTotal = $this->orm->table('oc_order_total')
                ->where('order_id', $orderId)
                ->get();

            $total = $balance = 0;
            foreach ($orderTotal as $item=>$value)
            {
                if ('balance' == $value->code){
                    $balance = $value->value;
                    $payment[] = 'Line Of Credit';
                }elseif ('total' == $value->code){
                    $total = $value->value;
                }
            }
            $payment[] = $order->payment_method;
            $precision = 2;
            $total = round($total - $balance,$precision);
        }
        if(!empty($feeOrderIdArr)) {
            $feeOrderInfo = $feeOrderRepo->findFeeOrderInfo($feeOrderIdArr);
            $useBalanceFlag = $feeOrderRepo->checkFeeOrdersUseBalance($feeOrderIdArr);
            $feeOrderTotal = $feeOrderRepo->findFeeOrderTotal($feeOrderIdArr);
            $feeOrderPoundage = $feeOrderRepo->findFeeOrderPoundage($feeOrderIdArr);
            if($useBalanceFlag){
                $payment[] = 'Line Of Credit';
            }
            $payment[] = $feeOrderInfo[0]['payment_method'];
            $total += $feeOrderTotal;
            $total += $feeOrderPoundage;
        }
        $total = $this->currency->format($total,$this->session->get('currency'));

        $data['order'] = [
            'payment_method'    => implode(' / ',array_unique($payment)),
            'total'             => $total
        ];
        $data['products'] = $this->recommendedProduct();

        return $data;
    }

    //支付后的商品推荐
    public function recommendedProduct()
    {
        $customerId = $this->customer->getId();
        $wishList = $this->orm->table('oc_customer_wishlist as w')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'w.product_id')
            ->leftJoin('oc_customerpartner_to_product as cp', 'cp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'cp.customer_id')//seller id
            ->where([
                'p.is_deleted'  => 0,//未删除
                'p.buyer_flag'  => 1,//可单独售卖
                'p.status'      => 1,//上架
                'w.customer_id' => $customerId,
                'c.status'      => 1,//店铺可用
            ])
            ->whereIn('p.product_type',[0,3])  //普通商品
            ->where('p.quantity', '>', 0)
            ->orderBy('w.date_added')
            ->limit(self::RECOMMEND_PRODUCT_NUM)
            ->pluck('w.product_id')
            ->toArray();

        if (count($wishList) <= self::RECOMMEND_PRODUCT_NUM)
        {
            $this->load->model('catalog/product_column');
            $recommend = $this->orm->table('oc_module')
                ->where('code', 'featured')
                ->value('setting');
            $recommend = json_decode($recommend, true);
            if (isset($recommend['product']) && !empty($recommend['product'])){
                $countryCode = session('country');
                $featuredProduct = $this->model_catalog_product_column->recommendFiledHome($recommend['product'], $countryCode);
                $featuredProduct = array_column($featuredProduct, 'product_id');
                $featuredProductSort = [];
                $sort = array_flip($recommend['product']);
                foreach ($featuredProduct as $k=>$v)
                {
                    if (isset($sort[$v])){
                        $featuredProductSort[$sort[$v]] = $v;
                    }
                }
                ksort($featuredProductSort);
                $wishList = array_slice(array_merge($wishList, $featuredProductSort), 0, self::RECOMMEND_PRODUCT_NUM);
            }

        }
        $product_data = [];//不足四条的时候隐藏推荐模块
        if (count($wishList) >= 4){
            $this->load->model('catalog/product');
            $this->load->model('extension/module/product_home');
            $productInfos = $this->model_extension_module_product_home->getHomeProductInfo($wishList, $customerId);
            $productInfos = array_combine(array_column($productInfos, 'product_id'), $productInfos);
            foreach ($wishList as $product_id) {
                if(!isset($productInfos[$product_id])){
                    continue;
                }
                $temp = $productInfos[$product_id];
                if($temp['unsee'] == 0){
                    $product_data[$product_id] = $temp;
                }else{
                    continue;
                }

                if ($this->config->get('module_marketplace_status') && !$product_data[$product_id]) {
                    unset($product_data[$product_id]);
                }
            }
        }

        return $product_data;
    }

    /**
     * @Author xxl
     * @Description 根据采购订单查看对应的
     * @Date 14:29 2020/10/16
     * @Param string $orderId
     * @return array orderIdArr
     **/
    public function getFeeToPaySalesOrderIdByOrderId($orderId)
    {
        return $this->orm->table('tb_sys_order_associated as soa')
            ->leftJoin('tb_sys_customer_sales_order as cso','cso.id','=','soa.sales_order_id')
            ->where([['soa.order_id','=',$orderId],['cso.order_status','=',CustomerSalesOrderStatus::PENDING_CHARGES]])
            ->groupBy('cso.order_id')
            ->get('cso.order_id')->pluck('order_id')->toArray();
    }

}
