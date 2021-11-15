<?php

use App\Enums\Cart\CartAddCartType;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Models\Cart\Cart;
use App\Models\Futures\FuturesMarginAgreement;
use App\Models\Margin\MarginProcess;
use App\Models\Product\ProductQuote;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Services\Stock\BuyerStockService;

/**
 * Class ModelCheckoutCart
 * @property ModelExtensionModulePrice $model_extension_module_price
 * @property ModelAccountCartCart $model_account_cart_cart
 * @property ModelFuturesAgreement $model_futures_agreement
 * */
class ModelCheckoutCart extends Model
{
    /**
     * @param int $product_id
     * @param int $type
     * @param int|null $agreement_id
     * @param null|int $delivery_type
     * @return int
     */
    public function verifyProductAdd($product_id,$type,$agreement_id,$delivery_type = null)
    {
        if (is_null($delivery_type)) {
            $delivery_type = $this->customer->isCollectionFromDomicile() ? 1 : 0;
        }
        $mapCart = [
            'api_id'=>   (isset($this->session->data['api_id']) ? (int)$this->session->data['api_id'] : 0),
            'customer_id' => (int)$this->customer->getId(),
            'product_id'  =>  $product_id,
            'delivery_type' =>$delivery_type
        ];

        $ret = $this->orm->table(DB_PREFIX.'cart')
            ->select(['type_id','agreement_id'])
            ->where($mapCart)
            ->first();
        if(!empty($ret)) {
            if($ret->type_id == $type && $ret->agreement_id == $agreement_id){
                return 0;
            }else{
                return 1;
            }
        }else{
            return 0;
        }
    }

    /**
     * [add description]
     * @param int $product_id
     * @param int $quantity
     * @param array $option
     * @param int $recurring_id
     * @param int $type_id
     * @param null $agreement_id
     * @param int $delivery_type
     * @param int $add_cart_type 备注参考 CartAddCartType.php
     * @return int|mixed
     */
    public function add($product_id, $quantity = 1, $option = [], $recurring_id = 0,$type_id = 0,$agreement_id = null,$delivery_type = null, $add_cart_type=0)
    {
        if (is_null($delivery_type)) {
            $delivery_type = $this->customer->isCollectionFromDomicile() ? 1 : 0;
        }
        $mapCart = [
            'api_id'=>   (isset($this->session->data['api_id']) ? (int)$this->session->data['api_id'] : 0),
            'customer_id' => (int)$this->customer->getId(),
            'product_id'  =>  $product_id,
            'delivery_type' => $delivery_type,
        ];
        $cart_id = $this->orm->table(DB_PREFIX.'cart')
            ->where($mapCart)
            ->value('cart_id');
        if($cart_id){
            //增加数量
            $this->orm->table(DB_PREFIX.'cart')->where('cart_id',$cart_id)->increment('quantity',$quantity, ['add_cart_type' => CartAddCartType::DEFAULT_OR_OPTIMAL]);
            return $cart_id;
        }else{
            //新增数据
            $saveCart = [
                'api_id'=>   (isset($this->session->data['api_id']) ? (int)$this->session->data['api_id'] : 0),
                'customer_id' => (int)$this->customer->getId(),
                'session_id'  =>  $this->session->getId(),
                'product_id'  =>  $product_id,
                'recurring_id'=>  $recurring_id,
                'option'       =>  json_encode($option),
                'type_id'      =>  $type_id,
                'quantity'     =>  $quantity,
                'date_added'   =>  date('Y-m-d H:i:s',time()),
                'agreement_id' =>  $agreement_id,
                'delivery_type' => $delivery_type,
                'sort_time'     => time(),
                'add_cart_type' => $add_cart_type,
            ];
            return $this->orm->table(DB_PREFIX.'cart')->insertGetId($saveCart);

        }
    }

    public function updateCart($cart_id, $quantity,$transaction_type){
        $isFuturesAdvanceProduct = false;
        if($transaction_type == '0'){
            $agreement_id = null;
            $type_id = ProductTransactionType::NORMAL;
            //验证是否是头款
            $product_id = Cart::query()->where('cart_id',$cart_id)->value('product_id');
            $map = [
                'process_status' => 1,
                'advance_product_id' => $product_id,
            ];
            $agreement_id = MarginProcess::query()->where($map)->value('margin_id');
            if($agreement_id){
                $type_id = ProductTransactionType::MARGIN;
            }else{
                $this->load->model('futures/agreement');
                $agreement_id = $this->model_futures_agreement->agreementIdByAdvanceProductId($product_id);
                $type_id = $agreement_id ? ProductTransactionType::FUTURE : $type_id;
                $isFuturesAdvanceProduct = $agreement_id ? true : false;
            }
        }else{
            $tmp = explode('_',$transaction_type); //type_1_140
            $type_id = $tmp[1];
            $agreement_id = $tmp[2];
        }
        $saveCart = [
            'agreement_id' =>  $agreement_id,
            'type_id'      =>  $type_id,
        ];
        if($quantity && $quantity > 0){
            $saveCart['quantity'] = $quantity;
        }
        if ($type_id == ProductTransactionType::NORMAL) {
            $saveCart['add_cart_type'] = CartAddCartType::DEFAULT_OR_OPTIMAL;
        }
        if ($type_id == ProductTransactionType::SPOT) {
            $quantity = ProductQuote::query()->where('status', 1)->where('id', $agreement_id)->value('quantity');
            $saveCart['quantity'] = $quantity;
        }
        if ($type_id == ProductTransactionType::FUTURE && !$isFuturesAdvanceProduct) {
            $quantity = FuturesMarginAgreement::query()->where('id', $agreement_id)->where('version', '=', 3)->value('num');
            if ($quantity && $quantity > 0) {
                $saveCart['quantity'] = $quantity;
            }
        }

        return Cart::query()->where('cart_id', '=', $cart_id)->update($saveCart);

    }


    public function updateCartProductStatus($buyer_id){
        $mapCart = [
            'api_id'=>   (isset($this->session->data['api_id']) ? (int)$this->session->data['api_id'] : 0),
            'customer_id' => (int)$buyer_id,
            'session_id'  =>  $this->session->getId(),
        ];
        $ret = $this->orm->table(DB_PREFIX.'cart')
            ->where($mapCart)
            ->get()
            ->map(function ($value){
                return (array)$value;
            })
            ->toArray();

        if($ret){
           foreach($ret as $key => $value){
                if($value['type_id'] == ProductTransactionType::MARGIN){
                   $flag = $this->verifyMarginAgreement($value['agreement_id']);
                }elseif ($value['type_id'] == ProductTransactionType::FUTURE){
                    $flag = $this->verifyFutureMarginAgreement($value['agreement_id']);
                }elseif ($value['type_id'] == ProductTransactionType::REBATE){
                    $flag = $this->verifyRebateAgreement($value['product_id'],$value['agreement_id'],$buyer_id);
                }elseif ($value['type_id'] == ProductTransactionType::SPOT){
                    $flag = $this->verifySpotAgreement($value['agreement_id']);
                }else{
                    $flag = 1;
                }
                if(!$flag){
                    //更改oc_cart 里的值
                    $this->updateCart($value['cart_id'],$value['quantity'],0);
                }
           }
        }
    }

    /**
     * [verifyReorderStatus description]
     * @param array $data
     * @param int $buyer_id
     * @return bool|int
     */
    public function verifyReorderStatus($data,$buyer_id)
    {
        //新增了逻辑相同产品的不同交易方式不允许同时添加购物车
//        if ($this->verifyProductAdd($data['product_id'], $data['type_id'], $data['agreement_id'])) {
//            return false;
//        }

        if($data['type_id'] == ProductTransactionType::MARGIN){
            $flag = $this->verifyMarginAgreement($data['agreement_id']);
        }elseif ($data['type_id'] == ProductTransactionType::FUTURE){
            $flag = $this->verifyFutureMarginAgreement($data['agreement_id']);
        }elseif ($data['type_id'] == ProductTransactionType::REBATE){
            $flag = $this->verifyRebateAgreement($data['product_id'],$data['agreement_id'],$buyer_id);
        }elseif ($data['type_id'] == ProductTransactionType::SPOT){
            $flag = false;
        }else{
            $flag = true;
        }
        return $flag;

    }

    public function verifySpotAgreement($id)
    {
        return $this->orm->table('oc_product_quote')
            ->where('id', $id)
            ->where('status', 1)
            ->exists();
    }

    public function verifyMarginAgreement($agreement_id){
        $mapMargin = [
            ['m.id','=',$agreement_id],
            ['m.status','=',6], //sold
            ['m.expire_time','>',date('Y-m-d H:i:s',time())],
        ];
        return $this->orm->table('tb_sys_margin_agreement as m')->where($mapMargin)->exists();
    }

    public function verifyRebateAgreement($product_id,$agreement_id,$customer_id){
        $this->load->model('extension/module/price');
        $tmp = $this->model_extension_module_price->getRebatePrice($product_id,$customer_id);
        if($tmp){
            foreach($tmp as $key => $value){
                if($agreement_id == $value['id']){
                    return 1;
                }
            }
            return 0;
        }else{
            return 0;
        }
    }

    public function verifyFutureMarginAgreement($agreement_id){
        $ret = $this->orm->table('oc_futures_margin_agreement as fa')
            ->leftJoin('oc_futures_margin_delivery as fd', 'fa.id', 'fd.agreement_id')
            ->leftJoin('oc_product_lock as l', function ($join){
                $join->on('l.agreement_id','=','fa.id')->where('l.type_id','=',$this->config->get('transaction_type_margin_futures'));
            })
            ->where([
                'fa.id' => $agreement_id,
                'fa.agreement_status'   => 7,//sold
                'fd.delivery_status'    => 6,//To be Paid
            ])
            ->where('fd.delivery_type', '!=', 2)
            ->where('l.qty', '>', 0)
            ->selectRaw('fa.id,fa.agreement_no as agreement_code, fd.last_unit_price as price,fa.product_id,fa.num as qty,
            round(l.qty/l.set_qty) as left_qty, DATE_ADD(fd.update_time, INTERVAL 30 DAY) as expire_time')
            ->orderBy('fa.id', 'desc')
            ->groupBy('fa.id')
            ->exists();

        return $ret;
    }

    /**
     * [getTransactionTypePrice description]
     * @param int $type
     * @param int $agreement_id
     * @param int $product_id
     * @return float|null
     */
    public function getTransactionTypeInfo($type,$agreement_id,$product_id){
        if($type == 1){
            //rebate
            return $this->getRebateInfo($agreement_id,$product_id);
        }elseif ($type == 2){
            return $this->getMarginInfo($agreement_id,$product_id);
        }
    }

    /**
     * @param int $agreement_id
     * @param int $product_id
     * @return float|null
     */
    public function getRebateInfo($agreement_id,$product_id){
        $map = [
            ['a.id','=',$agreement_id],
            ['ai.product_id','=',$product_id],
            //['a.status','=',3],
            ['a.expire_time','>',date('Y-m-d H:i:s',time())],
        ];
        //首先要获取现货保证金交易：Agreement Status = Sold，协议未完成数量 ≠ 0；
        $ret = $this->orm->table(DB_PREFIX.'rebate_agreement_item as ai')
            ->leftJoin(DB_PREFIX.'rebate_agreement as a','a.id','=','ai.agreement_id')
            ->where($map)
            ->value('ai.template_price');
        if($ret){
            return $ret;
        }else{
            return null;
        }
    }

    public function getMarginInfo($agreement_id,$product_id){
        $map = [
            ['m.id','=',$agreement_id],
            ['m.product_id','=',$product_id],
            ['p.buyer_id','=',$this->customer->getId()],
            ['m.expire_time','>',date('Y-m-d H:i:s',time())],
            //['m.status','=',6], //sold
            //['l.qty','!=',0],
        ];
        $ret = $this->orm->table('tb_sys_margin_agreement as m')
            ->leftJoin(DB_PREFIX.'product_lock as l',function ($join){
                $join->on('l.agreement_id','=','m.id')->where('l.type_id','=',$this->config->get('transaction_type_margin_spot'));
            })
            ->leftJoin(DB_PREFIX.'agreement_common_performer as p',function ($join){
                $join->on('p.agreement_id','=','m.id')->where('p.agreement_type','=',$this->config->get('common_performer_type_margin_spot'));
            })
            ->where($map)
            ->selectRaw('m.id,m.expire_time,m.agreement_id as agreement_code,round(m.price - m.deposit_per,2) as price,m.product_id,m.num as qty,l.qty as left_qty')
            ->orderBy('m.expire_time','asc')
            ->get()
            ->map(function($value){
                return (array)$value;
            })
            ->toArray();
        if($ret){
            return $ret[0]['agreement_code'];
        }else{
            return null;
        }

    }


    /**
     * 自动购买-采销异体 匹配购物车商品和New Order销售订单
     * @param int $deliveryType
     * @param array $cartIdArr
     * @param array $productQtyArr [product_id=>qty] BuyNow时的参数
     * @return array
     */
    public function checkPurchaseAndSales(int $deliveryType = 0, array $cartIdArr=[], array $productQtyArr=[])
    {
        if ($productQtyArr) {
            $productIdArr = array_keys($productQtyArr);
            $cartProduct = $this->orm->table("oc_product AS p")
                ->whereIn('p.product_id', $productIdArr)
                ->selectRaw('p.product_id, p.sku')
                ->get();
            foreach ($cartProduct as $k => $v) {
                $v->quantity = $productQtyArr[$v->product_id];
                $cartProduct[$k] = $v;
            }
        } else {
            $apiId = isset($this->session->data['api_id']) ? (int)$this->session->data['api_id'] : 0;
            $cartProduct = $this->orm->table("oc_cart as cart")
                ->leftJoin('oc_product as p', 'p.product_id', 'cart.product_id')
                ->where('cart.customer_id', $this->customer->getId())
                ->where('cart.delivery_type', $deliveryType)
                ->where('cart.api_id', $apiId)
                ->when($cartIdArr, function ($query) use ($cartIdArr) {
                    return $query->whereIn('cart_id', $cartIdArr);
                })
                ->selectRaw('sum(cart.quantity) as quantity, p.sku')
                ->groupBy('p.sku')
                ->get();
        }
        $salesOrder = [];
        foreach ($cartProduct as $k=>$v)
        {
            $purchaseQty = $this->purchaseQty($v->sku);
            $associatedQty = $this->associatedQty($v->sku);
            $rmaQty = $this->rmaQty($v->sku);
            $lockQty = $this->lockQty($v->sku);
            // 负值转为0
            $hadQty = max($purchaseQty - $associatedQty - $rmaQty - $lockQty, 0);
            $quantity = $v->quantity;

            $newSalesOrder = $this->newSalesOrder($v->sku);
            $count = count($newSalesOrder);
            foreach ($newSalesOrder as $kk => $vv)
            {

                if ($hadQty >= $vv['qty']){
                    $hadQty -= $vv['qty'];
                    continue;
                }
                $vv['qty'] -= $hadQty;
                $hadQty = 0;
                $vv['extra_qty'] = 0;
                if ($vv['qty'] > $quantity){
                    $vv['qty'] = $quantity;
                }
                $quantity -= $vv['qty'];
                if ($kk == $count-1){
                    $vv['extra_qty'] = $quantity;
                }

                $salesOrder[] = $vv;
                if ($quantity <= 0){
                    break;
                }
            }
        }

        return $salesOrder;
    }

    //采购总量
    public function purchaseQty($sku)
    {
        return $this->orm->table('oc_order_product as op')
            ->leftJoin('oc_order as o', 'o.order_id', 'op.order_id')
            ->leftJoin('oc_product as p', 'p.product_id', 'op.product_id')
            ->whereIn('o.order_status_id', [OcOrderStatus::COMPLETED,OcOrderStatus::CHARGEBACK])
            ->where('p.sku', $sku)
            ->where('o.customer_id', $this->customer->getId())
            ->sum('op.quantity');
    }

    //采购退返数量
    public function rmaQty($sku)
    {
        $select = 'case when sum(rp.quantity) > op.quantity then op.quantity else sum(rp.quantity) end as rmaQty';
        $rma = $this->orm->table('oc_yzc_rma_order_product as rp')
            ->leftJoin('oc_yzc_rma_order as r', 'r.id', '=', 'rp.rma_id')
            ->leftJoin('oc_order_product as op', 'op.order_product_id', '=', 'rp.order_product_id')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'rp.product_id')
            ->where('p.sku', $sku)
            ->where('r.cancel_rma', '=', 0)
            ->where('r.order_type', '=', 2)
            ->where('rp.status_refund', '!=', 3)//RMA非拒绝状态
            ->where('r.buyer_id', $this->customer->getId())
            ->groupBy('rp.order_product_id')
            ->selectRaw($select)
            ->get();

        return array_sum(array_column(obj2array($rma),'rmaQty'));
    }

    //已关联总量
    public function associatedQty($sku)
    {
        return $this->orm->table('tb_sys_order_associated as a')
            ->leftJoin('oc_product as p', 'p.product_id', 'a.product_id')
            ->where('a.buyer_id', $this->customer->getId())
            ->where('p.sku', $sku)
            ->sum('qty');
    }

    /**
     * 获取sku锁定数量
     * @param string $sku
     * @return int
     */
    public function lockQty($sku): int
    {
        $lockQty = app(BuyerStockService::class)
            ->getLockQuantityIndexBySkuBySkus([$sku], $this->customer->getId());
        return (int)($lockQty[$sku] ?? 0);
    }

    public function getNewOrderSkuQuantity($sku,$buyerId): int
    {
        return CustomerSalesOrderLine::query()->alias('sol')
                ->leftJoinRelations(['customerSalesOrder as so'])
                ->where('so.buyer_id', $buyerId)
                ->where('sol.item_code', $sku)
                ->where('so.order_status', 1)
                ->select(['sol.id', 'so.order_id', 'sol.item_code', 'sol.qty'])
                ->sum('qty') ?? 0;
    }

    //待采购 待关联 sales订单
    public function newSalesOrder($sku)
    {
        $line = $this->orm->table('tb_sys_customer_sales_order_line as sol')
            ->leftJoin('tb_sys_customer_sales_order as so', 'so.id', 'sol.header_id')
            ->where('so.buyer_id', $this->customer->getId())
            ->where('sol.item_code', $sku)
            ->where('so.order_status', CustomerSalesOrderStatus::TO_BE_PAID)
            ->select('sol.id', 'so.order_id', 'sol.item_code', 'sol.qty')
            ->orderBy('so.run_id', 'desc')
            ->orderBy('sol.id')
            ->get();

        return obj2array($line);
    }

    //获取购物车列表
    public function getCartList($deliveryType,$cartIdArr = [])
    {
        $apiId = isset($this->session->data['api_id']) ? (int)$this->session->data['api_id'] : 0;
        $cart = $this->orm->table('oc_cart as c')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'c.product_id')
            ->where('c.customer_id', $this->customer->getId())
            ->where('c.delivery_type', $deliveryType)
            ->where('c.api_id', $apiId)
            ->when($cartIdArr, function ($query) use ($cartIdArr){
                return $query->whereIn('cart_id', $cartIdArr);
            })
            ->select('c.*', 'p.sku', 'p.product_type')
            ->get();
        $cart = obj2array($cart);
        $cartArr = [];
        foreach ($cart as $k=>$v)
        {
            $cartArr[$v['cart_id']] = $v;
        }

        return $cartArr;
    }

    //修改自动购买采销异体账号的购物车
    public function updateInnerAutoBuyCart($deliveryType, $cartIdArr=[])
    {
        $cart = $this->getCartList($deliveryType, $cartIdArr);
        $canBuy = $this->checkPurchaseAndSales($deliveryType,$cartIdArr);
        $toEdit = [];
        $toBuy = [];
        foreach ($cart as $c=>$v)
        {
            // 补运费产品不需修改数量
            if ($v['product_type'] == ProductType::COMPENSATION_FREIGHT) {
                $toBuy[] = $v['cart_id'];
                continue;
            }

            foreach ($canBuy as $kk=>$vv)
            {
                if ($vv['item_code'] == $v['sku']){
                    $toBuy[] = $v['cart_id'];
                    if ($vv['extra_qty']){
                        $toEdit[$v['sku']] = $vv['extra_qty'];
                    }
                }
            }
        }

        //修正 待编辑
        foreach ($cart as $c=>$v)
        {
            if (isset($toEdit[$v['sku']]) && $toEdit[$v['sku']] >= $v['quantity']){
                $toBuy = array_diff($toBuy, [$v['cart_id']]);
                $toEdit[$v['sku']] = $toEdit[$v['sku']] - $v['quantity'];
                if (0 == $toEdit[$v['sku']]){
                    unset($toEdit[$v['sku']]);
                }
            }
        }

        //修改购物车可购买商品的数量
        foreach ($cart as $c=>$v)
        {
            if (isset($toEdit[$v['sku']])){
                $qty = $v['quantity'] - $toEdit[$v['sku']];
                $this->cart->update($c, $qty);
            }
        }

        return $toBuy;
    }

    //修改数量
    public function updateQty($cartId, $qty)
    {
        return Cart::query()
            ->where('cart_id', $cartId)
            ->update([
                'quantity'  => $qty,
                'add_cart_type' => CartAddCartType::DEFAULT_OR_OPTIMAL,
            ]);
    }

    public function cartExist($cartId)
    {
        return Cart::query()
            ->where('cart_id', $cartId)
            ->exists();
    }

    //修改购物车商品
    public function changeCartProduct($cartId,$toProductId)
    {
        $cartInfo = $this->cart->getInfoByCartId($cartId);
        $toProductInfo = $this->cart->hasThisProduct($cartInfo['delivery_type'], $toProductId);
        if (!$toProductInfo){//不存在即不冲突，直接修改
            $this->load->model('extension/module/price');
            $info = $this->model_extension_module_price->getProductPriceInfo($toProductId,$cartInfo['customer_id']);
            $first= $info['first_get'];
            $this->orm->table('oc_cart')
                ->where('cart_id', $cartId)
                ->update([
                    'product_id'    => $toProductId,
                    'type_id'       => $first['type'],
                    'agreement_id'  => $first['id'],
                    'sort_time'     => time()
                ]);
            return ['flag'=>true, 'data'=>0];
        }else{//已存在相同发货类型的该商品，直接修改数量
            $qty = $cartInfo['quantity'] + $toProductInfo['quantity'];
            $this->orm->table('oc_cart')
                ->where('cart_id', '=', $toProductInfo['cart_id'])
                ->update([
                    'quantity'      => $qty,
                    'sort_time'     => time()
                ]);
            $this->cart->remove($cartId,$cartInfo['delivery_type']);
            return ['flag'=>true, 'data'=>1];
        }

    }

    public function addByArr($addCartProductArr){
        $cartIds = [];
        try {
            $this->db->beginTransaction();
            foreach ($addCartProductArr as $addCartProduct){
                $whereMap = [
                    ['product_id','=',$addCartProduct['product_id']],
                    ['customer_id','=',$addCartProduct['customer_id']],
                    ['type_id','=',$addCartProduct['type_id']]
                ];
                //清除product_id.type_id相同的数据
                $this->orm->table(DB_PREFIX.'cart')
                    ->where($whereMap)
                    ->delete();
                $cartId = $this->orm->table(DB_PREFIX.'cart')->insertGetId($addCartProduct);
                $cartIds[] = $cartId;
            }
            $this->db->commit();
            return $cartIds;
        } catch (Exception $e) {
            $this->log->write($e);
            $this->db->rollback();
            return false;
        };
    }
}
