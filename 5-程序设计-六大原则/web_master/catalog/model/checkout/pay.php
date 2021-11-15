<?php

use App\Enums\Cart\DeliveryType;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Pay\PayCode;
use App\Enums\Product\ProductType;
use App\Models\Order\OrderProduct;
use App\Repositories\FeeOrder\FeeOrderRepository;
use App\Services\FeeOrder\FeeOrderService;

/**
 * Class ModelCheckoutPay
 * @property ModelCheckoutCart $model_checkout_cart
 */
class ModelCheckoutPay extends Model
{
    //更新订单支付方式
    public function updatePayMethod(int $orderId, array $payMethod)
    {
        $data = [
            'payment_firstname'     => isset($payMethod['payment_firstname']) ? $payMethod['payment_firstname'] : '',
            'payment_lastname'      => isset($payMethod['payment_lastname']) ? $payMethod['payment_lastname'] : '',
            'payment_company'       => isset($payMethod['payment_company']) ? $payMethod['payment_company'] : '',
            'payment_address_1'     => isset($payMethod['payment_address_1']) ? $payMethod['payment_address_1'] : '',
            'payment_address_2'     => isset($payMethod['payment_address_2']) ? $payMethod['payment_address_2'] : '',
            'payment_city'          => isset($payMethod['payment_city']) ? $payMethod['payment_city'] : '',
            'payment_postcode'      => isset($payMethod['payment_postcode']) ? $payMethod['payment_postcode'] : '',
            'payment_country_id'    => isset($payMethod['payment_country_id']) ? $payMethod['payment_country_id'] : 0,
            'payment_country'       => isset($payMethod['payment_country']) ? $payMethod['payment_country'] : '',
            'payment_zone_id'       => isset($payMethod['payment_zone_id']) ? $payMethod['payment_zone_id'] : 0,
            'payment_zone'          => isset($payMethod['payment_zone']) ? $payMethod['payment_zone'] : '',
            'payment_address_format'=> isset($payMethod['payment_address_format']) ? $payMethod['payment_address_format'] : '',
            'payment_custom_field'  => isset($payMethod['payment_custom_field']) ? json_encode($payMethod['payment_custom_field']) : '[]',
            'payment_method'        => isset($payMethod['payment_method']) ? $payMethod['payment_method'] : '',
            'payment_code'          => isset($payMethod['payment_code']) ? $payMethod['payment_code'] : '',
            'date_modified'         => date('Y-m-d H:i:s')
        ];
        return $this->orm->table(DB_PREFIX.'order')
            ->where([
                'order_id'          => $orderId,
                'order_status_id'   => OcOrderStatus::TO_BE_PAID
            ])
            ->update($data);

    }

    //获取订单待支付金额
    public function getOrderTotal($orderId)
    {
        return $this->orm->table('oc_order_total')
            ->where([
                'order_id'  => $orderId,
                'code'      => 'total'
            ])
            ->value('value');
    }

    /**
     * 判断订单是否可以采用虚拟支付
     * 当前囤货
     * @param int $orderId 采购订单ID
     * @return bool
     * @throws Exception
     */
    public function canVirtualPay($orderId): bool
    {
        $flag = false;
        if (customer()->innerAutoBuyAttr1()) {
            load()->model('checkout/cart');
            /** @var ModelAccountSalesOrderMatchInventoryWindow $matchInventoryWindowModel */
            $matchInventoryWindowModel = load()->model('account/sales_order/match_inventory_window');
            $order = OrderProduct::query()->alias('op')
                ->leftJoinRelations(['order as o', 'product as p'])
                ->where('o.order_status_id', '=', OcOrderStatus::TO_BE_PAID)
                ->whereIn('o.delivery_type', [DeliveryType::DROP_SHIP, DeliveryType::HOME_PICK])
                ->where('op.order_id', '=', $orderId)
                ->selectRaw('sum(op.quantity) as quantity,p.sku,p.product_type')
                ->groupBy('p.sku')//同一sku存在不同product ID
                ->get()
                ->toArray();

            if ($order) {
                $flag = true;
                $skuList = array_column($order, 'sku');
                $skuCost = $matchInventoryWindowModel->getCostBySkuArray($skuList, customer()->getId());
                foreach ($order as $k => $v) {
                    // 排除服务运费产品
                    if ($v['product_type'] == ProductType::COMPENSATION_FREIGHT) {
                        continue;
                    }

                    $qty = (int)$this->model_checkout_cart->getNewOrderSkuQuantity($v['sku'], customer()->getId());
                    if ($qty < ((int)$v['quantity'] + $skuCost[$v['sku']])) {
                        //一旦发现囤货,该单就不可使用虚拟支付
                        $flag = false;
                    }
                }
            }
        }
        return $flag;
    }

    /**
     * 修改订单total
     * @author xxl
     * @param array $orderTotal
     */
    public function updateOrderTotal(array $orderTotal){
        $order_id = $orderTotal['order_id'];
        $balance = $orderTotal['balance'];
        $poundage = $orderTotal['poundage'];
        $order_total =  $orderTotal['order_total'];

        if ($balance != 0) {
            $balanceArr = array(
                'order_id' => $orderTotal['order_id'],
                'code' => 'balance',
                'title' => 'Line Of Credit(Use Balance)',
                'value' => 0-$balance,
                'sort_order' => 7
            );
            $this->orm->table('oc_order_total')
                ->insert($balanceArr);
        }
        if($poundage != 0){
            $poundageArr = array(
                'order_id' => $orderTotal['order_id'],
                'code' => 'poundage',
                'title' => 'Poundage',
                'value' => $poundage,
                'sort_order' => 3
            );
            $this->orm->table('oc_order_total')
                ->insert($poundageArr);
        }
        $new_order_total = (float)$order_total-(float)$balance+(float)$poundage;
        $this->orm->table('oc_order_total')
            ->where([
                'order_id'          => $order_id,
                'code'   => 'total'
            ])
            ->update(['value'=>$new_order_total]);
        $this->orm->table('oc_order')
            ->where([
                'order_id'          => $order_id
            ])
            ->update(['total'=>$new_order_total]);

    }

    public function modifiedOrder($payData){
        $order_id =$payData['order_id'];
        $payment_code = $payData['payment_code'];
        $balance = $payData['balance'];
        $comment = $payData['comment'];
        $totalPoundage = $payData['totalPoundage'];
        //更新订单payment_method
        $payment_method = PayCode::getDescriptionWithPoundage($payment_code);

        $payMethod = [
            'payment_firstname'     =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['firstname'] ?? '' : '',
            'payment_lastname'      =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['lastname']?? '' : '',
            'payment_company'       =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['company']?? '' : '',
            'payment_address_1'     =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['address_1']?? '' : '',
            'payment_address_2'     =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['address_2']?? '' : '',
            'payment_city'          =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['city']?? '' : '',
            'payment_postcode'      =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['postcode']?? '' : '',
            'payment_country_id'    =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['country_id']?? '' : '',
            'payment_country'       =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['country']?? '' : '',
            'payment_zone_id'       =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['zone_id']?? '' : '',
            'payment_zone'          =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['zone']?? '' : '',
            'payment_address_format'=>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['address_format']?? '' : '',
            'payment_custom_field'  =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['custom_field'] ?? [] : [],
            'payment_method'        => $payment_method,
            'payment_code'          => $payment_code,
            'date_modified'         => date('Y-m-d H:i:s')
        ];
        $this->updatePayMethod($order_id,$payMethod);

        //修改order_total
        $order_total = $this->getOrderTotal($order_id);
        if ($order_total === null) {
            $order_total = 0;
        }
        $balance = min($balance,$order_total);
        $data = array(
            'balance' => $balance,
            'payment_method' => $payment_code,
            'order_total' => $order_total
        );
        $poundage = $this->load->controller('checkout/confirm/getPoundage',$data);
        //
        $totalArr = array(
            'balance' => $balance,
            'order_total' => $order_total,
            'order_id' => $order_id,
            'poundage' => $poundage
        );
        $this->updateOrderTotal($totalArr);

        $this->updateOrderProduct($order_id,$poundage);

        //update comment
        $this->orm->table('oc_order')
            ->where([
                'order_id' => $order_id
            ])
            ->update(
                ['comment'=>$comment]
            );
        $payData['balance'] = $payData['balance'] - $balance;
        $payData['totalPoundage'] = $totalPoundage - $poundage;
        return $payData;
    }

    public function getOrderAddTime($order_id){
        return $this->orm->table('oc_order')
            ->where([
                'order_id'          => $order_id
            ])
            ->value('date_added');
    }

    public function deleteOrderPaymentInfo($productOrderId){


        $balance = $this->orm->table('oc_order_total')
            ->where([
                'order_id'          => $productOrderId,
                'code'   => 'balance'
            ])
            ->value('value');
        $poundage = $this->orm->table('oc_order_total')
            ->where([
                'order_id'          => $productOrderId,
                'code'   => 'poundage'
            ])
            ->value('value');
        $total = $this->orm->table('oc_order_total')
            ->where([
                'order_id'          => $productOrderId,
                'code'   => 'total'
            ])
            ->value('value');
        $balance = isset($balance)?$balance:0;
        $poundage = isset($poundage)?$poundage:0;
        $total = isset($total)?$total:0;
        $total = $total - $balance - $poundage;
        $this->orm->table('oc_order_total')
            ->where([
                'order_id' => $productOrderId,
                'code' => 'total'
            ])
            ->update(['value'=>$total]);
        $this->orm->table('oc_order')
            ->where([
                'order_id' => $productOrderId
            ])
            ->update([
                'total'=>$total,
                'payment_method'=>'',
                'payment_code'=>'',
                'comment'=>''
                ]);
        $this->orm->table('oc_order_total')
            ->where([
                'order_id'          => $productOrderId,
                'code'   => 'poundage'
            ])
            ->delete();
        $this->orm->table('oc_order_total')
            ->where([
                'order_id'          => $productOrderId,
                'code'   => 'balance'
            ])
            ->delete();

        //多次打开支付链接，支付链接保留
//        $this->orm->table('tb_payment_info')
//            ->where([
//                'order_id_yzc'      => $order_id
//            ])
//            ->delete();


    }

    public function updateOrderProduct($order_id,$poundage){
        $lineCount = $this->orm->table('oc_order_product')
            ->where([
                'order_id' => $order_id
            ])
            ->count();
        $poundagePerLine = round($poundage/$lineCount,2);
        $this->orm->table('oc_order_product')
            ->where([
                'order_id' => $order_id
            ])
            ->update(
                ['poundage'=>$poundagePerLine]
            );

    }

    /**
     * @Author xxl
     * @Description 清除费用单信息
     * @Date 16:23 2020/9/30
     * @Param
     **/
    public function deleteFeeOrderPaymentInfo($feeOrderIdArr)
    {
        $this->orm->table('oc_fee_order as ofo')
            ->whereIn('ofo.id', $feeOrderIdArr)
            ->update([
                'poundage' => 0,
                'balance' => 0,
                'payment_method' => null,
                'payment_code' => null,
            ]);
    }

    public function modifiedFeeOrder($data)
    {
        $feeOrderId =$data['fee_order_id'];
        $payment_code = $data['payment_code'];
        $balance = $data['balance'];
        $comment = $data['comment'];
        $poundage = $data['totalPoundage'];
        //更新订单payment_method
        $payment_method = PayCode::getDescriptionWithPoundage($payment_code);

        $feeOrderInfos = app(FeeOrderRepository::class)->findFeeOrderInfo($feeOrderId);
        $feeOrderNums = count($feeOrderInfos);
        $index = 1;
        $poundageUse = 0;
        $feeOrderTotal = array_sum(array_column($feeOrderInfos, 'fee_total'));
        $feeOrderService = app(FeeOrderService::class);
        foreach ($feeOrderInfos as $feeOrderInfo){
            $feeOrderPoundage = 0;
            if ($poundageUse < $poundage) {
                if ($index == $feeOrderNums) {
                    $feeOrderPoundage = $poundage - $poundageUse;
                } else {
                    if ($feeOrderInfo['fee_total'] > 0 && $feeOrderTotal > 0) {
                        $feeOrderPoundage = round(($feeOrderInfo['fee_total'] / $feeOrderTotal) * $poundage, 2);
                        $poundageUse += $feeOrderPoundage;
                    }
                }
            }
            $feeData = [
                'id' => $feeOrderInfo['id'],
                'balance' => min($feeOrderInfo['fee_total'],$balance),
                'poundage' => $feeOrderPoundage,
                'comment' =>  $comment,
                'payment_method' => $payment_method,
                'payment_code' => $payment_code,// 增加保存code
            ];
            $feeOrderService->updateFeeOrderInfo($feeData);
            $balance = $balance - min($feeOrderInfo['fee_total'],$balance);
            $index++;
        }
    }
}
