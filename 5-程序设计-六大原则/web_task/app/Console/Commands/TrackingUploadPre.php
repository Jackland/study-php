<?php
/**
 * Created by IntelliJ IDEA.
 * User: or
 * Date: 2020/8/21
 * Time: 9:32
 */

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Tracking\UploadTracking;
use Illuminate\Console\Command;
use Psy\Exception\Exception;
use App\Jobs\SendMail;

class TrackingUploadPre extends Command
{
    /**
     * 准备上传物流运单号的数据
     * @var string
     */
    protected $signature = 'tracking:uploadPre';

    protected $description = '准备上传运单号数据';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if('production' == config('app.env')) {
            \Log::info("---tracking-uploadpre--start------");
            $uploadTracking = new UploadTracking();
            //查询过去7天内的未上传物流单号的所有货值子订单
            $uploadTrackingOrders = $uploadTracking->getNoUploadTrackingOrders();
            //封装上传联动支付的数据
            $subOrders = [];
            //查询最近7天已经使用过的tracking_number
            $trackingArr = $uploadTracking->hasUsedTrackingNubmer();
            $trackingArrAll = $uploadTracking->hasUsedTrackingNubmerAll();
            $trackingArr[] = 'Y';
            //订单生成第6天还未有物流单号的货值子订单
            $daySixOrderArr = [];
            foreach ($uploadTrackingOrders as $uploadTrackingOrder) {
                //只需要上传货值的子订单
                if (isset($uploadTrackingOrder->trans_code) &&
                    substr($uploadTrackingOrder->trans_code, 0, 2) === '01') {
                    $merSubReferenceId = $uploadTrackingOrder->mer_sub_reference_id;
                    $orderDate = $uploadTrackingOrder->date_modified;
                    $orderDate = strtotime($orderDate);
                    $now = time();
                    //订单距离现在的时间
                    $timeDiff = $now - $orderDate;
                    //先判断是否是保证金定金产品
                    $productType = $uploadTrackingOrder->product_type;
                    if ($productType <> 0) {
                        //如果已经是第7天，将子订单记录一下
                        if ($timeDiff > 24 * 60 * 60 * 6 && $timeDiff < 7 * 24 * 60 * 60) {
                            $daySevenOrderArr[] = $merSubReferenceId;
                        }
                        //todo 使用giga传过来的运单号
                        $this->getFromGIGA($uploadTrackingOrder, $uploadTracking, $trackingArr, $subOrders, $trackingArrAll);
                        continue;
                    }

                    //订单生成时间后1~5天,根据实际的销售订单情况上传物流单号，校验上传物流单号的时间和唯一
                    if ($timeDiff > 24 * 60 * 60 && $timeDiff < 5 * 24 * 60 * 60) {
                        //todo 根据实际信息上传运单号
                        //查询是否已经绑定了销售订单
                        $this->getFromOrderProduct($uploadTrackingOrder, $uploadTracking, $trackingArr, $subOrders, $trackingArrAll);
                        continue;
                    }
                    //订单生成时间后7天,优先根据实际的销售订单上传物流单号，
                    //其次查找系统生成订单后使用信用额度付款的相同sku的未重复的物流单号，
                    //最后查找giga物流项目的未重复的物流单号（需giga将物流单号同步），
                    //如果还有未上传物流单号的订单，取订单生成之后使用信用额度付款的非相同sku的未重复的物流单号。
                    if ($timeDiff > 24 * 60 * 60 * 5 && $timeDiff < 6 * 24 * 60 * 60) {
                        //todo 查找相似订单上传运单号
                        $result = $this->getFromOrderProduct($uploadTrackingOrder, $uploadTracking, $trackingArr, $subOrders, $trackingArrAll);
                        if ($result) {
                            continue;
                        }
                        //查找信用额度支付的相同sku的未重复的物流单号
                        $result = $this->getFromOtherOrderProduct($uploadTrackingOrder, $uploadTracking, $trackingArr, $subOrders, $trackingArrAll);
                        if ($result) {
                            continue;
                        }
                        //从giga的物流单号中查找
                        $result = $this->getFromGIGA($uploadTrackingOrder, $uploadTracking, $trackingArr, $subOrders, $trackingArrAll);
                        if ($result) {
                            continue;
                        }
                        //查询信用额度付款的非相同sku的未重复的物流单号
                        $result = $this->getFromOtherOrderByTime($uploadTrackingOrder, $uploadTracking, $trackingArr, $subOrders, $trackingArrAll);
                        if ($result) {
                            continue;
                        }
                        $daySixOrderArr[] = $merSubReferenceId;
                        continue;
                    }
                    //订单生成时间后7天,优先根据实际的销售订单上传物流单号，
                    //其次查找系统生成订单后使用信用额度付款的相同sku的未重复的物流单号，
                    //最后查找giga物流项目的未重复的物流单号（需giga将物流单号同步），
                    //如果还有未上传物流单号的订单，取订单生成之后使用信用额度付款的非相同sku的未重复的物流单号。
                    if ($timeDiff > 24 * 60 * 60 * 6 ) {
                        //todo 查找相似订单上传运单号
                        $result = $this->getFromOrderProduct($uploadTrackingOrder, $uploadTracking, $trackingArr, $subOrders, $trackingArrAll);

                        if ($result) {
                            continue;
                        }
                        //查找信用额度支付的相同sku的未重复的物流单号
                        $result = $this->getFromOtherOrderProduct($uploadTrackingOrder, $uploadTracking, $trackingArr, $subOrders, $trackingArrAll);
                        if ($result) {
                            continue;
                        }
                        //从giga的物流单号中查找
                        $result = $this->getFromGIGA($uploadTrackingOrder, $uploadTracking, $trackingArr, $subOrders, $trackingArrAll);
                        if ($result) {
                            continue;
                        }
                        //查询信用额度付款的非相同sku的未重复的物流单号
                        $result = $this->getFromOtherOrderByTime($uploadTrackingOrder, $uploadTracking, $trackingArr, $subOrders, $trackingArrAll);
                        if ($result) {
                            continue;
                        }
                        $daySixOrderArr[] = $merSubReferenceId;
                        continue;
                    }
                }
            }
//       $successUploadArr = $this->uploadTrackingInfo($subOrders);
            //修改uploadTracking表
            foreach ($subOrders as $subFerenceId => $subOrder) {
                foreach ($subOrder as $order) {
                    $subFerenceId = $order['mer_sub_reference_id'];
                    $carrierName = $order['logisticsName'];
                    $trackingNo = $order['trackingNumber'];
                    $priority = $order['priority'];
                    try {
                        \DB::beginTransaction();
                        $uploadTracking->updateTrackingUpload($subFerenceId, $carrierName, $trackingNo, $priority);
                        \DB::commit();
                    } catch (Exception $e) {
                        \DB::rollback();
                        $preMsg = $e->getMessage();
                        \Log::error($preMsg);

                    }
                }
            }

            //todo 检验已在第6天的订单是否上传运单号
            if (count($daySixOrderArr) > 0) {
                $to = Setting::getConfig('tracking_upload_pre_email_to', '');
                //将第7天还未上传运单号的订单发送邮件
                $data['subject'] = "第6天还未上传运单号的联动支付订单";
                $data['body'] = json_encode($daySixOrderArr);
                $data['to'] = $to;
                SendMail::dispatch($data);
            }
            \Log::info("---tracking-uploadpre--end------");
        }
    }

    private function getFromGIGA($uploadTrackingOrder, $uploadTracking, &$trackingArr, &$subOrders, $trackingArrAll)
    {
        $createTime = $uploadTrackingOrder->date_modified;
        $pay_id = $uploadTrackingOrder->pay_id;
        $merSubReferenceId = $uploadTrackingOrder->mer_sub_reference_id;
        $trackingInfo = $uploadTracking->getTrackingNumberFromGiga($createTime, $trackingArr, $trackingArrAll);
        if (!empty($trackingInfo)) {
            $tracking = str_replace(' ', '', $trackingInfo->tracking_number);
            $logisticsName = str_replace(' ', '', $trackingInfo->logistics_name);
            $trackingNumber = explode(',', $tracking)[0];
            $subOrders[$pay_id][] = array(
                "mer_sub_reference_id" => $merSubReferenceId,
                "logisticsName" => $logisticsName,
                "trackingNumber" => $trackingNumber,
                "priority" => 3
            );
            $trackingArr[] = $trackingInfo->tracking_number;
            return true;
        } else {
            return false;
        }
    }

    private function getFromOrderProduct($uploadTrackingOrder, $uploadTracking, &$trackingArr, &$subOrders, $trackingArrAll)
    {
        //查询是否已经绑定了销售订单
        $orderProductId = $uploadTrackingOrder->order_product_id;
        $pay_id = $uploadTrackingOrder->pay_id;
        $merSubReferenceId = $uploadTrackingOrder->mer_sub_reference_id;
        $trackingInfo = $uploadTracking->getTrackingNumberByOrderProductId($orderProductId, $trackingArr, $trackingArrAll);
        if (!empty($trackingInfo)) {
            $tracking = str_replace(' ', '', $trackingInfo->TrackingNumber);
            $logisticsName = str_replace(' ', '', $trackingInfo->CarrierCode);
            $trackingNumber = explode(',', $tracking)[0];
            $subOrders[$pay_id][] = array(
                "mer_sub_reference_id" => $merSubReferenceId,
                "logisticsName" => $logisticsName,
                "trackingNumber" => $trackingNumber,
                "priority" => 1
            );
            $trackingArr[] = $trackingInfo->TrackingNumber;
            return true;
        } else {
            return false;
        }
    }

    private function getFromOtherOrderProduct($uploadTrackingOrder, $uploadTracking, &$trackingArr, &$subOrders, $trackingArrAll)
    {
        $createTime = $uploadTrackingOrder->date_modified;
        $productId = $uploadTrackingOrder->product_id;
        $pay_id = $uploadTrackingOrder->pay_id;
        $merSubReferenceId = $uploadTrackingOrder->mer_sub_reference_id;
        $trackingInfo = $uploadTracking->getTrackingByProductId($productId, $createTime, $trackingArr, $trackingArrAll);
        if (!empty($trackingInfo)) {
            $tracking = str_replace(' ', '', $trackingInfo->TrackingNumber);
            $logisticsName = str_replace(' ', '', $trackingInfo->CarrierCode);
            $trackingNumber = explode(',', $tracking)[0];
            $subOrders[$pay_id][] = array(
                "mer_sub_reference_id" => $merSubReferenceId,
                "logisticsName" => $logisticsName,
                "trackingNumber" => $trackingNumber,
                "priority" => 2
            );
            $trackingArr[] = $trackingInfo->TrackingNumber;
            return true;
        } else {
            return false;
        }
    }

    private function getFromOtherOrderByTime($uploadTrackingOrder, $uploadTracking, &$trackingArr, &$subOrders, $trackingArrAll)
    {
        $createTime = $uploadTrackingOrder->date_modified;
        $productId = $uploadTrackingOrder->product_id;
        $pay_id = $uploadTrackingOrder->pay_id;
        $merSubReferenceId = $uploadTrackingOrder->mer_sub_reference_id;
        $trackingInfo = $uploadTracking->getTrackingByTime($productId, $createTime, $trackingArr, $trackingArrAll);
        if (!empty($trackingInfo)) {
            $tracking = str_replace(' ', '', $trackingInfo->TrackingNumber);
            $logisticsName = str_replace(' ', '', $trackingInfo->CarrierCode);
            $trackingNumber = explode(',', $tracking)[0];
            $subOrders[$pay_id][] = array(
                "mer_sub_reference_id" => $merSubReferenceId,
                "logisticsName" => $logisticsName,
                "trackingNumber" => $trackingNumber,
                "priority" => 4
            );
            $subOrderId[] = $merSubReferenceId;
            $trackingArr[] = $trackingInfo->TrackingNumber;
            return true;
        } else {
            return false;
        }
    }
}