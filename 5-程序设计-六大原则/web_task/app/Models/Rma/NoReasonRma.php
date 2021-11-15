<?php

namespace App\Models\Rma;

use App\Models\Message\Message;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NoReasonRma extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public function getNoReasonRmaOrder()
    {
        $last = strtotime('-1 month', time());
        $last_lastday = date('Y-m-t', $last);//上个月最后一天
        $last_firstday = date('Y-m-01', $last);//上个月第一天

        $objs = DB::table('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'ro.id', '=', 'rop.rma_id')
            ->leftJoin('oc_yzc_rma_reason as rr', 'rop.reason_id', '=', 'rr.reason_id')
            ->leftJoin('oc_customer as buyer', 'buyer.customer_id', '=', 'ro.buyer_id')
            ->leftJoin('oc_customer as seller', 'seller.customer_id', '=', 'ro.seller_id')
            ->leftJoin('oc_yzc_rma_status as rs', 'rs.status_id', '=', 'ro.seller_status')
            ->select([
                'ro.id as rma_id',
                'ro.rma_order_id',
                'ro.order_id',
                'ro.from_customer_order_id AS customer_order_id',
                'rop.item_code',
                'rop.comments',
                'ro.create_time AS rma_date',
                'rs.name AS status'
            ])
            ->addSelect(DB::raw("CASE ro.order_type WHEN 1 THEN '销售订单RMA' WHEN 2 THEN '采购订单RMA' END AS rma_type"))
            ->where([
                ['buyer.customer_group_id', '<>', 23],
                ['seller.customer_group_id', '<>', 23],
                ['rr.reason_id', '=', 10],
                ['ro.cancel_rma', '=', 0]
            ])
            ->whereBetween('ro.create_time', [$last_firstday, $last_lastday])
            ->get();
        return $objs;

    }

    //RMA订单，超过7天未处理,平台修改状态
    public static function rmaTimeOut()
    {
        // 上线时间为北京时间 2021-10-28 20:00:00 美国时间 2021-10-28 05:00:00
        $newRelateTime =  Setting::getConfig('new_rma_apply_date') ?? '2021-10-28 05:00:00';
        $oldRma = DB::table('oc_yzc_rma_order  as ro')
            ->select('ro.id', 'ro.seller_id', 'ro.rma_order_id', 'ro.order_type', 'ro.seller_id', 'ro.is_timeout', 'op.product_id', 'op.rma_type', 'op.apply_refund_amount', 'op.status_refund', 'op.status_reshipment')
            ->leftJoin('oc_yzc_rma_order_product as op', 'ro.id', '=', 'op.rma_id')
            ->whereIn('ro.seller_status', [1, 3])
            ->where('ro.cancel_rma', 0)
            ->where('ro.create_time', '<', $newRelateTime) // 小于上线时间的仍然采用超过14天自动处理
            ->where('ro.create_time', '<', date('Y-m-d H:i:s', strtotime('- 14 day')));
        $newRma = DB::table('oc_yzc_rma_order  as ro')
            ->select('ro.id', 'ro.seller_id', 'ro.rma_order_id', 'ro.order_type', 'ro.seller_id', 'ro.is_timeout', 'op.product_id', 'op.rma_type', 'op.apply_refund_amount', 'op.status_refund', 'op.status_reshipment')
            ->leftJoin('oc_yzc_rma_order_product as op', 'ro.id', '=', 'op.rma_id')
            ->whereIn('ro.seller_status', [1, 3])
            ->where('ro.cancel_rma', 0)
            ->where('ro.create_time', '>=', $newRelateTime) // 大于上线时间的超过7天自动处理
            ->where('ro.create_time', '<', date('Y-m-d H:i:s', strtotime('- 7 day')));;
        $res = ($oldRma->union($newRma))->get();
        $white_list = Setting::getConfig('rma_timeout_white_list');
        $white_list = explode(',', $white_list);
        foreach ($res as $item) {
            // 白名单过滤
            if (in_array($item->seller_id, $white_list)) {
                continue;
            }
            echo $item->id, ',';
            $subject = 'RMA Reshipment waiting process (ID ' . $item->rma_order_id . ')';
            $url = 'index.php?route=account/customerpartner/rma_management/rmaInfo&rmaId=' . $item->id;
            // 重发rma
            if ($item->rma_type == 1) {
                $content = "A reshipment request submitted for RMA ID <a href='{$url}'> {$item->rma_order_id}  </a> has not received a response from you in over 7 days. Please respond to this RMA request as soon as possible.";
                Message::addSystemMessage('rma', $subject, $content, $item->seller_id);
            }
            // 退款rma
            if ($item->rma_type == 2 && !$item->is_timeout) {
                self::agreeRefund($item);
                $seller_status = DB::table('oc_yzc_rma_order')->where('id', $item->id)->value('seller_status');
                if ($seller_status == 2) {
                    $subject = 'RMA process complete by Giga assisted (ID ' . $item->rma_order_id . ')';
                    $content = "A refund request submitted for RMA ID  <a href='{$url}'> {$item->rma_order_id}  </a>  has not received a response from you in over 7 days. After reviewing your Buyer’s request, the Marketplace has issued them a refund. If you disagree with this resolution, please contact the Marketplace at the RMA Resolution Center.";
                    Message::addSystemMessage('rma', $subject, $content, $item->seller_id);
                }
            }
            // 退款+重发rma
            if ($item->rma_type == 3) {
                if (!$item->status_refund) {
                    $seller_status = $item->status_reshipment ? 2 : 3;
                    DB::table('oc_yzc_rma_order')->where('id', $item->id)->update(['seller_status' => $seller_status]);
                    self::agreeRefund($item);
                }
                $subject = 'RMA process complete by Giga assisted (ID ' . $item->rma_order_id . ')';
                $content = "A refund and reshipment request submitted for RMA ID  <a href='{$url}'> {$item->rma_order_id}  </a>  has not received a response from you in over 7 days. After reviewing your Buyer’s request, the Marketplace has issued them a refund with the reshipment request still pending, please respond to the reshipment request as soon as possible. If you disagree with this resolution, please contact the Marketplace at the RMA Resolution Center.";
                Message::addSystemMessage('rma', $subject, $content, $item->seller_id);
            }
            // 设置为超时状态
            DB::table('oc_yzc_rma_order')->where('id', $item->id)->update(['is_timeout' => 1]);
            DB::table('oc_yzc_rma_order_history')->where('id', $item->id)->update(['is_timeout' => 1]);
        }
        echo PHP_EOL;
    }

    // 调用yzc项目的退款接口
    public static function agreeRefund($rma)
    {
        $data['refund_agree_comments'] = 'refund_agree_comments';
        $data['refundMoney'] = $rma->apply_refund_amount;
        $data['refundType'] = 1;
        $data['rmaId'] = $rma->id;
        $data['order_type'] = $rma->order_type;
        $data['customer_id'] = $rma->seller_id;
        $data['secret'] = 'b2b@pass';
        $url = getenv('B2B_URL') . 'account/customerpartner/rma_management/agreeRefundApi';
        $res = self::curl_post($url, $data);
    }


    public static function curl_post($url, $postdata)
    {
        $header = array(
            'Accept: application/json',
        );
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // 超时设置
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        // 超时设置，以毫秒为单位
        // curl_setopt($curl, CURLOPT_TIMEOUT_MS, 500);
        // 设置请求头
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        //设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
        //执行命令
        $data = curl_exec($curl);
        // 显示错误信息
        if (curl_error($curl)) {
            Log::error("Error: " . curl_error($curl));
        } else {
            Log::info(json_encode($data));
            curl_close($curl);
        }
    }

}
