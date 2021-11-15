<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Jobs\SendMail;
use App\Jobs\PurchaseAfter;
use \App;
use Mockery\Exception;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class QueueController extends Controller
{
    /**
     * @param Request $request
     * to String|Array 邮件接受者,如果是数组发送给多个人
     * subject String 邮件主题
     * body String 邮件内容
     */
    public function productEmailQueue(Request $request)
    {
        $request->validate([
            'to' => 'required',
            'subject' => 'required',
            'body' => 'required'
        ]);
        if (is_array($request->to)) {
            $request->validate(['to.*' => 'email|distinct']);
        } else {
            $request->validate(['to' => 'email']);
        }
        $data = $request->all();
        // html标签处理
        $data['subject'] = str_replace("<b>", " ", $data['subject']);
        $data['subject'] = str_replace("</b>", " ", $data['subject']);
        $data['subject']=strip_tags($data['subject']);
        if (is_array($data['to'])) {
            foreach ($request->to as $item) {
                $data['to'] = $item;
                SendMail::dispatch($data);
            }
        } else {
            SendMail::dispatch($data);
        }
        return new JsonResponse(true);
    }

    /**
     * 采购订单购买成功后，处理业务
     * @param Request $request
     */
    public function purchaseOrderQueue(Request $request){
        $request->validate([
            'order_id' => 'required'
        ]);
        $data['order_id'] = $request->order_id;
        try {
            PurchaseAfter::dispatch($data)->onQueue("purchase_queue");
            $returnDate =array(
                'status'=>true,
                'message'=>'success'
            );
            return new JsonResponse($returnDate);
        }catch (Exception $e){
            $returnDate =array(
                'status'=>false,
                'message'=>$e->getMessage()
            );
            return new JsonResponse($returnDate);
        }
    }
}
