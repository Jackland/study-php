<?php

namespace App\Services\CWF;

use App\Logging\Logger;
use App\Models\CWF\CloudWholesaleFulfillmentFileExplain;
use App\Models\CWF\CloudWholesaleFulfillmentMatchStock;
use App\Models\CWF\OrderCloudLogistics;
use App\Models\CWF\OrderCloudLogisticsItem;
use App\Models\Link\OrderAssociated;
use App\Models\Order\OrderProduct;
use App\Services\FeeOrder\StorageFeeService;
use App\Services\Order\OrderService;
use Carbon\Carbon;
use Cart\Sequence;
use Framework\Exception\Exception;
use Yzc\CustomerSalesOrder;
use Yzc\CustomerSalesOrderLine;


class CloudWholesaleFulfillmentService
{
    /**
     * 此方法在addOrderHistoryByYzcModel中执行已有事务，此处不再新增加事务
     * @param int $order_id
     * @return bool
     * @throws \Exception
     */
    public function updateCWFBatchInfo(int $order_id): bool
    {
        //1.生成tb_sys_customer_sales_order
        //2.生成tb_sys_customer_sales_order_line
        //3.生成绑定关系tb_sys_order_associate
        //4.oc_order_cloud_logistics,的order_id，sales_order_id填写
        //5.此方法在addOrderHistoryByYzcModel中执行已有事务，此处不再新增加事务
        Logger::cloudWholesaleFulfillment("云送仓批量更新order_id：{$order_id}");
        try {
            // 判断绑定表数据是否存在，存在则报错
            $associateExists = OrderAssociated::query()->where('order_id',$order_id)->exists();
            if($associateExists){
                throw new \Exception($order_id .'云送仓绑定明细已存在，请处理错误数据。');
            }
            // 获取cwf对应的销售单信息
            // 生成 tb_sys_customer_sales_order
            // 生成明细
            //更新 tb_sys_customer_sales_order_line的is_exported,exported_time,is_synchroed,synchroed_time,将明细设置成已同步
            //采购订单与生成的销售订单绑定
            //更新oc_order_cloud_logistics
            //根据绑定的销售单与采购单的关系，绑定仓租
            $cloudLogisticInfo = OrderCloudLogistics::query()->alias('ocl')
                ->leftJoinRelations(['fileExplains as fe'])
                ->where('fe.order_id', $order_id)
                ->select(['fe.id as explain_id', 'fe.cwf_file_upload_id', 'fe.b2b_item_code', 'fe.ship_to_qty'])
                ->selectRaw("ocl.id,ocl.buyer_id,ocl.recipient,ocl.phone,ocl.email,ocl.address,ocl.country,ocl.city,ocl.state,ocl.zip_code,CASE WHEN ocl.service_type=0 THEN 'OTHER' ELSE 'FBA' END as shipment_method")
                ->get();
            $infos = [];
            foreach ($cloudLogisticInfo as $items) {
                $infos[$items->id]['main_info'] = $items;
                $infos[$items->id]['line_info'][] = [
                    'explain_id' => $items->explain_id,
                    'cwf_file_upload_id' => $items->cwf_file_upload_id,
                    'b2b_item_code' => $items->b2b_item_code,
                    'ship_to_qty' => $items->ship_to_qty,
                ];
            }
            /** @var Sequence $sequence */
            $sequence = load()->library('cart/sequence');
            /** @var \ModelAccountCustomerOrderImport $accountCustomerOrderImportModel */
            $accountCustomerOrderImportModel = load()->model('account/customer_order_import');
            $run_id = msectime();
            foreach ($infos as $items) {
                $yzc_order_id_number = $sequence->getYzcOrderIdNumber();
                $tb_order_id = $sequence->getCloudLogisticsOrderIdNumber();
                $yzc_order_id_number++;
                $tb_order_id++;
                $order_id_tb = "CWF-" . $tb_order_id;
                $cloudLogistic = [
                    'order_id' => $order_id_tb,
                    'order_date' => Carbon::now(),
                    'email' => $items['main_info']->email,
                    'ship_name' => $items['main_info']->recipient,
                    'shipped_date' => Carbon::now(),
                    'ship_address1' => $items['main_info']->address,
                    'ship_address2' => '',
                    'ship_city' => $items['main_info']->city,
                    'ship_state' => $items['main_info']->state,
                    'ship_zip_code' => $items['main_info']->zip_code,
                    'ship_country' => $items['main_info']->country,
                    'ship_phone' => $items['main_info']->phone,
                    'ship_method' => $items['main_info']->shipment_method,
                    'ship_service_level' => '',
                    'ship_company' => '',
                    'bill_name' => $items['main_info']->recipient,
                    'bill_address' => $items['main_info']->address,
                    'bill_city' => $items['main_info']->city,
                    'bill_state' => $items['main_info']->state,
                    'bill_zip_code' => $items['main_info']->zip_code,
                    'bill_country' => $items['main_info']->country,
                    'orders_from' => '',
                    'customer_comments' => '',
                    'run_id' => $run_id,
                    'discount_amount' => '',
                    'tax_amount' => '',
                    'order_total' => '',
                    'payment_method' => '',
                    'buyer_id' => $items['main_info']->buyer_id,
                    'create_user_name' => $items['main_info']->buyer_id,
                    'create_time' => Carbon::now(),
                    'program_code' => PROGRAM_CODE
                ];
                // 订单头表数据
                //order_mode=4 云送仓的业务
                $customerSalesOrder = new CustomerSalesOrder($cloudLogistic, 4);
                $customerSalesOrder->yzc_order_id = "YC-" . $yzc_order_id_number;
                $customerSalesOrder->line_count = 1;
                $sequence->updateYzcOrderIdNumber($yzc_order_id_number);
                $sequence->updateCloudLogisticsOrderIdNumber($tb_order_id);
                // 插入头表数据
                $customerSalesOrderArr[$order_id_tb] = $customerSalesOrder;
                $headerId = $accountCustomerOrderImportModel->saveCustomerSalesOrders($customerSalesOrderArr);
                unset($customerSalesOrderArr);
                $customerSalesOrderLines = [];
                foreach ($items['line_info'] as $k => $v) {
                    // 因为存在多个产品的情况，所以此处只获取items中第一条满足情况的product_id & name & seller_id
                    $itemsInfo = OrderCloudLogisticsItem::query()->alias('i')
                        ->leftJoin('oc_product_description as pd', 'pd.product_id', 'i.product_id')
                        ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', 'i.product_id')
                        ->where([
                            'i.item_code' => $v['b2b_item_code'],
                            'i.cloud_logistics_id' => $items['main_info']->id,
                        ])
                        ->select(['pd.name', 'ctp.customer_id', 'i.product_id'])
                        ->get()
                        ->first();

                    $orderLineTemp = [
                        'id' => $v['explain_id'], // 此处记录temp id 为explain表中的id，item表中数据与lines表明细可能存在不一致的情况
                        'line_item_number' => $k + 1,
                        'product_name' => $itemsInfo->name,
                        'qty' => $v['ship_to_qty'],
                        'item_price' => null,
                        'item_unit_discount' => null,
                        'item_tax' => null,
                        'item_code' => $v['b2b_item_code'],
                        'alt_item_id' => null,
                        'ship_amount' => null,
                        'customer_comments' => null,
                        'brand_id' => null,
                        'seller_id' => $itemsInfo->customer_id,
                        'run_id' => $run_id,
                        'create_user_name' => $items['main_info']->buyer_id,
                        'create_time' => Carbon::now(),
                    ];

                    $customerSalesOrderLine = new CustomerSalesOrderLine($orderLineTemp);
                    // 插入明细表
                    $customerSalesOrderLine->header_id = $headerId;
                    $customerSalesOrderLine->product_id = $itemsInfo->product_id; // 产品可能有多个对应，此处只取第一个
                    $customerSalesOrderLines[] = $customerSalesOrderLine;
                }

                $accountCustomerOrderImportModel->saveCustomerSalesOrderLine($customerSalesOrderLines);
                //更新 tb_sys_customer_sales_order_line的is_exported,exported_time,is_synchroed,synchroed_time,将明细设置成已同步
                $accountCustomerOrderImportModel->updateCustomerSalesOrderLineIsExported($headerId);
                //采购订单与生成的销售订单绑定
                //此处有问题，重写一份新的
                $orderAssociatedIds = $this->associateOrderForCWF($order_id, $headerId,$items['main_info']->id);
                //更改订单的状态
                $accountCustomerOrderImportModel->updateCustomerSalesOrder($headerId);
                //更新oc_order_cloud_logistics
                $accountCustomerOrderImportModel->updateOrderCloudLogistics($order_id, $headerId, $items['main_info']->id);
                //根据绑定的销售单与采购单的关系，绑定仓租
                app(StorageFeeService::class)->bindByOrderAssociated($orderAssociatedIds);
            }
            Logger::cloudWholesaleFulfillment("云送仓批量更新绑定信息成功order_id：{$order_id}");
        } catch (\Exception $e) {
            Logger::cloudWholesaleFulfillment($e, 'error');
            throw new \Exception($order_id .'云送仓更新失败，未知错误'.$e->getMessage());
        }
        return true;

    }

    /**
     * 云送仓批量订单
     * @param int $order_id 采购订单主键
     * @param int $headerId 销售订单主键
     * @param int $logistics_id
     * @return array
     */
    public function associateOrderForCWF($order_id, $headerId,$logistics_id): array
    {
        // $logistics_id 对应云送仓的明细
        // $headerId 对应 sales_order_line的明细
        // order_id 对应 购买的明细
        $orderAssociatedIds = [];
        $lineInfos = \App\Models\SalesOrder\CustomerSalesOrderLine::query()
            ->where('header_id',$headerId)
            ->select(['item_code','qty','header_id','id'])
            ->get()
            ->keyBy('item_code')
            ->toArray();

        $orderInfos = OrderProduct::query()->alias('op')
            ->leftJoinRelations(['product as p'])
            ->where('op.order_id',$order_id)
            ->select(['p.sku','op.quantity','op.order_id','op.order_product_id','op.product_id'])
            ->get()
            ->keyBy('product_id')
            ->toArray();

        foreach($lineInfos as $key => $value){
            $tmps = CloudWholesaleFulfillmentFileExplain::query()->alias('fe')
                ->leftJoin('tb_cloud_wholesale_fulfillment_associate_pre as ap','ap.file_explain_id','fe.id')
                ->leftJoin('tb_cloud_wholesale_fulfillment_match_stock as ms','ms.id','ap.cwf_match_stock_id')
                ->where('fe.cwf_order_id',$logistics_id)
                ->where('ap.sku',$value['item_code'])
                ->select(['ap.match_qty','ms.product_id','ms.seller_id'])
                ->get();
            foreach($tmps as $items){
                $discountsAmount = app(OrderService::class)->orderProductWillAssociateDiscountsAmount(
                    intval($orderInfos[$items->product_id]['order_product_id']),
                    intval($items->match_qty),
                    customer()->isJapan() ? 0 : 2);
                $insert = [
                    'sales_order_id'=>$value['header_id'],
                    'sales_order_line_id'=>$value['id'],
                    'order_id'=>$order_id,
                    'order_product_id'=> $orderInfos[$items->product_id]['order_product_id'],
                    'qty'=> $items->match_qty,
                    'product_id'=> $items->product_id,
                    'seller_id'=>$items->seller_id,
                    'buyer_id'=>customer()->getId(),
                    'image_id'=>0,
                    'CreateUserName'=>customer()->getId(),
                    'CreateTime'=>Carbon::now(),
                    'UpdateTime'=>Carbon::now(),
                    'ProgramCode'=>PROGRAM_CODE,
                    'coupon_amount'=>$discountsAmount['coupon_amount'],
                    'campaign_amount'=>$discountsAmount['campaign_amount'],
                ];
                $orderAssociatedIds[] = OrderAssociated::query()->insertGetId($insert);
            }

        }

        return $orderAssociatedIds;

    }

    /**
     * 云送仓single新增记录
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function insertOrderCloudLogisticsInfo(array $data): bool
    {
        $con = db()->getConnection();
        try {
            $con->beginTransaction();
            foreach ($data as $key => $value) {
                $cwf = [
                    'buyer_id' => customer()->getId(),
                    'service_type' => $value['service_type'],
                    'has_dock' => $value['has_dock'],
                    'recipient' => $value['recipient'],
                    'phone' => $value['phone'],
                    'email' => $value['email'],
                    'address' => $value['address'],
                    'country' => $value['country'],
                    'state' => $value['state'],
                    'city' => $value['city'],
                    'zip_code' => $value['zip_code'],
                    'comments' => $value['comments'],
                    'fba_shipment_code' => 0,
                    'fba_reference_code' => 0,
                    'fba_po_code' => 0,//废弃
                    'fba_warehouse_code' => 0,
                    'fba_amazon_reference_number' => 0,
                    'team_lift_file_id' => 0,    //废弃字段
                    'pallet_label_file_id' => 0,    //上面已经赋值0
                    'create_user_name' => customer()->getId(),
                    'create_time' => Carbon::now(),
                    'update_user_name' => customer()->getId(),
                    'update_time' => Carbon::now(),
                ];
                $logisticsId = OrderCloudLogistics::query()->insertGetId($cwf);
                if (!$logisticsId) {
                    throw new Exception('云送仓头表新增失败。');
                }
                // 更新explain表中和云送仓初始表的关联关系
                CloudWholesaleFulfillmentFileExplain::query()
                    ->where(['flag_id' => $key, 'cwf_file_upload_id' => $value['cwf_file_upload_id']])
                    ->update(
                        ['cwf_order_id' => $logisticsId]
                    );

                // 此处数据获取需要结合CloudWholesaleFulfillmentAssociatePre数据
                foreach ($value['items'] as $k => $v) {
                    $item = [
                        'cloud_logistics_id' => $logisticsId,
                        'product_id' => $v['product_id'],
                        'item_code' => $v['item_code'],
                        'seller_id' => $v['seller_id'],
                        'qty' => $v['qty'],
                        'create_user_name' => customer()->getId(),
                        'create_time' => Carbon::now(),
                        'update_user_name' => customer()->getId(),
                        'update_time' => Carbon::now(),
                    ];
                    $itemId = OrderCloudLogisticsItem::query()->insertGetId($item);
                    if (!$itemId) {
                        $con->rollBack();
                        throw new Exception('云送仓Items新增失败。');
                    }
                }
            }
            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();
            Logger::cloudWholesaleFulfillment($e->getMessage(), 'error');
            throw new \Exception($e->getMessage());
        }

        return true;
    }

    /**
     * 更新云送仓默认匹配库存
     * @param int $cwfFileUploadId
     * @param string $sku
     * @param array $data
     * @return int
     */
    public function updateCWFMatchStock(int $cwfFileUploadId, string $sku, array $data): int
    {
        return CloudWholesaleFulfillmentMatchStock::query()
                ->insertGetId([
                    'cwf_file_upload_id' => $cwfFileUploadId,
                    'product_id' => $data['product_id'],
                    'quantity' => $data['quantity'],
                    'sku' => $sku,
                    'seller_id' => $data['seller_id'],
                    'transaction_type' => $data['transaction_type'],
                    'agreement_id' => $data['agreement_id'],
                ]);
    }
}
