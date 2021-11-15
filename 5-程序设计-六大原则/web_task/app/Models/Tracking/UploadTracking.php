<?php
/**
 * Created by IntelliJ IDEA.
 * User: or
 * Date: 2020/7/28
 * Time: 13:38
 */

namespace App\Models\Tracking;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UploadTracking extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public function getNoUploadTrackingOrders()
    {
        $uploadTrackingOrders = DB::table("tb_sys_order_upload_tracking as out")
            ->leftJoin("tb_payment_info as tpi", 'tpi.order_id', '=', 'out.umf_order_id')
            ->leftJoin('oc_order as oo', 'oo.order_id', '=', 'out.order_id')
            ->leftJoin('oc_order_product as oop', [['oop.order_id', '=', 'out.order_id'], ['oop.product_id', '=', 'out.product_id']])
            ->leftJoin('oc_product as op', 'op.product_id', '=', 'oop.product_id')
            ->where([
                ['tpi.status', '=', 201],
                ['out.status', '=', 0],
                ['oo.order_status_id', '=', 5]
            ])
            ->whereRaw('oo.date_modified>DATE_SUB(now(),INTERVAL 7 DAY)')
            ->select('tpi.pay_id', 'out.id', 'out.order_id', 'out.mer_sub_reference_id', 'out.trans_code', 'out.product_id', 'oop.order_product_id', 'oo.date_modified', 'op.product_type')
            ->get();
        return $uploadTrackingOrders;
    }

    public function getTrackingNumberByOrderProductId($orderProductId, $trackingArr,$trackingArrAll)
    {
        $saleOrderInfos = DB::table("tb_sys_order_associated as soa")
            ->leftJoin('tb_sys_customer_sales_order as cso', 'soa.sales_order_id', '=', 'cso.id')
            ->leftJoin('tb_sys_customer_sales_order_line as csol', 'soa.sales_order_line_id', '=', 'csol.id')
            ->where([
                ['soa.order_product_id', '=', $orderProductId],
                ['cso.order_status', '=', 32]
            ])
            ->select('cso.order_id', 'csol.id')
            ->get();
        if (count($saleOrderInfos) == 0) {
            return false;
        } else {
            $trackingResult = false;
            foreach ($saleOrderInfos as $saleOrderInfo) {
                //查询运单号
                $salesOrderId = $saleOrderInfo->order_id;
                $salesLineId = $saleOrderInfo->id;
                //
                $trackingInfos = DB::table('tb_sys_customer_sales_order_tracking as sot')
                    ->leftJoin('tb_sys_carriers as tsc', 'tsc.CarrierID', '=', 'sot.LogisticeId')
                    ->where([
                        ['sot.SalesOrderId', '=', $salesOrderId],
                        ['sot.SalerOrderLineId', '=', $salesLineId],
                        ['sot.status', '=', 1]
                    ])
                    ->whereNotIn('sot.TrackingNumber', $trackingArr)
                    ->select('sot.TrackingNumber', 'tsc.CarrierCode')
                    ->get();
                foreach ($trackingInfos as $trackingInfo){
                    $tracking = str_replace(' ', '', $trackingInfo->TrackingNumber);
                    $tracking = explode(',',$tracking)[0];
                    if(!in_array($tracking,$trackingArrAll)){
                        $trackingResult = $trackingInfo;
                        break 2;
                    }
                }
            }
            return $trackingResult;
        }
    }

    public function hasUsedTrackingNubmer()
    {
        $trackingNumber = DB::table("tb_sys_order_upload_tracking as out")
            ->where('out.status', '=', 1)
            ->whereRaw('out.CreateTime>DATE_SUB(now(),INTERVAL 7 DAY)')
            ->select('out.tracking_number as tracking')
            ->get()
            ->pluck('tracking')
            ->toArray();
        return $trackingNumber;
    }

    public function hasUsedTrackingNubmerAll()
    {
        $trackingNumber = DB::table("tb_sys_order_upload_tracking as out")
            ->whereIn('out.status', [1,2])
            ->select('out.tracking_number as tracking')
            ->get()
            ->pluck('tracking')
            ->toArray();
        return $trackingNumber;
    }

    public function getTrackingByProductId($productId, $createTime, $trackingArr,$trackingArrAll)
    {
        $saleOrderInfos = DB::table("tb_sys_order_associated as soa")
            ->leftJoin('tb_sys_customer_sales_order as cso', 'soa.sales_order_id', '=', 'cso.id')
            ->leftJoin('tb_sys_customer_sales_order_line as csol', 'soa.sales_order_line_id', '=', 'csol.id')
            ->leftJoin('oc_order as oo', 'oo.order_id', '=', 'soa.order_id')
            ->where([
                ['soa.product_id', '=', $productId],
                ['soa.CreateTime', '>', $createTime],
                ['cso.order_status', '=', 32]
            ])
            ->whereIn('oo.payment_code', ['line_of_credit', 'virtual_pay'])
            ->select('cso.order_id', 'csol.id')
            ->get();
        if (count($saleOrderInfos) == 0) {
            return false;
        } else {
            $trackingResult = false;
            foreach ($saleOrderInfos as $saleOrderInfo) {
                //查询运单号
                $salesOrderId = $saleOrderInfo->order_id;
                $salesLineId = $saleOrderInfo->id;
                //
                $trackingInfos = DB::table('tb_sys_customer_sales_order_tracking as sot')
                    ->leftJoin('tb_sys_carriers as tsc', 'tsc.CarrierID', '=', 'sot.LogisticeId')
                    ->where([
                        ['sot.SalesOrderId', '=', $salesOrderId],
                        ['sot.SalerOrderLineId', '=', $salesLineId],
                        ['sot.status', '=', 1]
                    ])
                    ->whereNotIn('sot.TrackingNumber', $trackingArr)
                    ->select('sot.TrackingNumber', 'tsc.CarrierCode')
                    ->get();
                foreach ($trackingInfos as $trackingInfo){
                    $tracking = str_replace(' ', '', $trackingInfo->TrackingNumber);
                    $tracking = explode(',',$tracking)[0];
                    if(!in_array($tracking,$trackingArrAll)){
                        $trackingResult = $trackingInfo;
                        break 2;
                    }
                }
            }
            return $trackingResult;
        }
    }

    /**
     * 取订单生成之后使用信用额度付款的非相同sku的未重复的物流单号
     * @param $productId
     * @param $createTime
     * @param $trackingArr
     * @return bool|Model|null|object|static
     */
    public function getTrackingByTime($productId, $createTime, $trackingArr,$trackingArrAll)
    {
        $saleOrderInfos = DB::table("tb_sys_order_associated as soa")
            ->leftJoin('tb_sys_customer_sales_order as cso', 'soa.sales_order_id', '=', 'cso.id')
            ->leftJoin('tb_sys_customer_sales_order_line as csol', 'soa.sales_order_line_id', '=', 'csol.id')
            ->leftJoin('oc_order as oo', 'oo.order_id', '=', 'soa.order_id')
            ->where([
                ['soa.product_id', '<>', $productId],
                ['soa.CreateTime', '>', $createTime],
                ['cso.order_status', '=', 32]
            ])
            ->whereIn('oo.payment_code', ['line_of_credit', 'virtual_pay'])
            ->select('cso.order_id', 'csol.id')
            ->get();
        if (count($saleOrderInfos) == 0) {
            return false;
        } else {
            $trackingResult = false;
            foreach ($saleOrderInfos as $saleOrderInfo) {
                //查询运单号
                $salesOrderId = $saleOrderInfo->order_id;
                $salesLineId = $saleOrderInfo->id;
                //
                $trackingInfos = DB::table('tb_sys_customer_sales_order_tracking as sot')
                    ->leftJoin('tb_sys_carriers as tsc', 'tsc.CarrierID', '=', 'sot.LogisticeId')
                    ->where([
                        ['sot.SalesOrderId', '=', $salesOrderId],
                        ['sot.SalerOrderLineId', '=', $salesLineId],
                        ['sot.status', '=', 1]
                    ])
                    ->whereNotIn('sot.TrackingNumber', $trackingArr)
                    ->select('sot.TrackingNumber', 'tsc.CarrierCode')
                    ->get();
                foreach ($trackingInfos as $trackingInfo){
                    $tracking = str_replace(' ', '', $trackingInfo->TrackingNumber);
                    $tracking = explode(',',$tracking)[0];
                    if(!in_array($tracking,$trackingArrAll)){
                        $trackingResult = $trackingInfo;
                        break 2;
                    }
                }
            }
            return $trackingResult;
        }
    }

    public function updateTrackingUpload($subFerenceId, $carrierName, $trackingNumber, $priority)
    {
        $updateArr = [
            'tracking_number' => $trackingNumber,
            'logistics_name' => $carrierName,
            'priority' => $priority,
            'status' => 2
        ];
        DB::table('tb_sys_order_upload_tracking')
            ->where('mer_sub_reference_id', '=', $subFerenceId)
            ->update($updateArr);
    }

    public function getTrackingNumberFromGiga($createTime, $trackingArr,$trackingArrAll)
    {
        $trackingInfos = DB::table('tb_sys_giga_cloud_tracking as gct')
            ->where(
                [
                    ['gct.tracking_date', '>', $createTime]
                ]
            )
            ->whereNotIn('gct.tracking_number', $trackingArr)
            ->select('gct.tracking_number', 'gct.logistics_name')
            ->get();
        $trackingResult = false;
        foreach ($trackingInfos as $trackingInfo){
            $tracking = str_replace(' ', '', $trackingInfo->tracking_number);
            $tracking = explode(',',$tracking)[0];
            if(!in_array($tracking,$trackingArrAll)){
                 $trackingResult = $trackingInfo;
                 break;
            }
        }
        return $trackingResult;
    }

    public function getUploadTrackingOrders()
    {
        $uploadTrackingOrders = DB::table("tb_sys_order_upload_tracking as out")
            ->leftJoin("tb_payment_info as tpi", 'tpi.order_id', '=', 'out.umf_order_id')
            ->where([
                ['out.status', '=', 2],
            ])
            ->select('out.logistics_name','out.tracking_number','tpi.pay_id','out.mer_sub_reference_id')
            ->get();
        return $uploadTrackingOrders;
    }
    public function updateTrackingUploadStatus($subFerenceId)
    {
        $updateArr = [
            'status' => 1,
            'UpdateTime' => date("Y-m-d H:i:s", time())
        ];
        DB::table('tb_sys_order_upload_tracking')
            ->where('mer_sub_reference_id', '=', $subFerenceId)
            ->update($updateArr);
    }
}