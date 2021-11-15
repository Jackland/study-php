<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class PurchaseAfter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 5;
    public $timeout = 60;
    public $sleep = 60;
    public $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data = '')
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \Log::info("队列消费开始");
        \Log::info(json_encode($this->data));
        /*
         * 判断订单是否是返点订单
         * 1.查询该订单的所有产品明细
         * 2.该用户的还有效的返点协议
         * 3.采购订单必须以返点精细化价格购买，不能议价,在协议期间都属于返点
         */
        $order_id = $this->data['order_id'];
        $purchaseOrderResult = \DB::table('oc_order as oo')
            ->leftJoin('oc_order_product as oop','oo.order_id','=','oop.order_id')
            ->where([
                'oop.type_id'=> 1,
                'oo.order_id'=>$order_id,
            ])
            ->selectRaw('oo.order_id,oop.order_product_id,oop.product_id,
            oop.quantity,oo.customer_id,oo.date_modified')
            ->get();
        $purchaseOrderLines = json_decode($purchaseOrderResult, true);
        \Log::info('采购订单查询结果'.json_encode($purchaseOrderLines));
        foreach ($purchaseOrderLines as $purchaseOrderLine) {
            //判断该采购订单明细是否使用议价
            $isQuote = $this->checkIsQuote($purchaseOrderLine['order_id'],$purchaseOrderLine['product_id']);
            \Log::info('采购订单是否使用议价'.json_encode($isQuote));
            if($isQuote == 0) {
                $agreementInfos = $this->getAgreementInfos($purchaseOrderLine['customer_id'], $purchaseOrderLine['product_id']);
                \Log::info('返点协议信息'.json_encode($agreementInfos));
                foreach ($agreementInfos as $agreementInfo) {
                    //判断订单是否在返点有效期内
                    $purchaseOrderDate = $purchaseOrderLine['date_modified'];
                    $rebateStartTime = $agreementInfo['effect_time'];
                    $rebateEndTime = $agreementInfo['expire_time'];
                    if($purchaseOrderDate>=$rebateStartTime && $purchaseOrderDate<=$rebateEndTime) {
                        //查询该返点协议数量
//                    if ($agreementInfo['qty'] > $hasRebateNum) {
                        //插入rebate_order
                        $rebateOrderData = array(
                            'agreement_id' => $agreementInfo['agreement_id'],
                            'item_id' => $agreementInfo['id'],
                            'product_id' => $agreementInfo['product_id'],
                            'qty' => $purchaseOrderLine['quantity'],
                            'order_id' => $purchaseOrderLine['order_id'],
                            'order_product_id' => $purchaseOrderLine['order_product_id'],
                            'type' => 1,
                            'create_user_name' => $purchaseOrderLine['customer_id'],
                            'create_time' => $purchaseOrderLine['date_modified'],
                            'update_time' => $purchaseOrderLine['date_modified'],
                            'program_code' => 'V1.0'
                        );
                        \Log::info($rebateOrderData);
                        \DB::table('oc_rebate_agreement_order')->insert($rebateOrderData);
//                    }
                    }
                }
            }
        }
        \Log::info("队列消费结束");
    }

    public function getAgreementInfos($buyer_id,$product_id){
        $agreementResult = \DB::table('oc_rebate_agreement as ora')
            ->leftJoin('oc_rebate_agreement_item as rai','ora.id','=','rai.agreement_id')
            ->whereRaw('ora.status = 3 and ora.rebate_result in(1,2) and ora.buyer_id='.$buyer_id.' and rai.product_id='.$product_id)
            ->selectRaw('ora.qty,rai.agreement_id,rai.id,rai.product_id,ora.expire_time,ora.effect_time')
            ->get();
        return  json_decode($agreementResult, true);
    }

    /**
     * 已经算入返点协议的数量
     */
    public function agreementNum($agreement_id){
        $agreementNum = \DB::table('oc_rebate_agreement_order as rao')
            ->whereRaw('rao.type=1 and rao.agreement_id='.$agreement_id)
            ->selectRaw('sum(rao.qty) as alLNum')
            ->groupBy('rao.agreement_id')
            ->first();
        return empty($agreementNum)?0:$agreementNum->alLNum;
    }

    public function checkIsQuote($order_id,$product_id){
        $quoteCount = \DB::table('oc_product_quote as opq')
            ->whereRaw('opq.order_id='.$order_id.' and opq.product_id='.$product_id)
            ->count();
        return $quoteCount;
    }
}
