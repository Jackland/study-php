<?php

namespace App\Http\Controllers\Rma;

use App\Models\Rma\NoReasonRma;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RmaInfoController extends Controller
{
    private $model;

    public function __construct()
    {
        $this->model = new NoReasonRma();
    }

    public function test()
    {
        echo date('Y-m-d H:i:s');
    }

    public function noReasonRma(Request $request)
    {
        $request = new NoReasonRma();
        $results = $request->getNoReasonRmaOrder();
        $date = date('Y-m', strtotime('-1 month', time()));
        if (count($results)) {
            $header = ['RmaId', 'RmaOrderId', 'RmaType', 'OrderId', 'CustomerOrderId', 'ItemCode', 'RmaDate', 'Status'];
            $content = [];
            foreach ($results as $result) {
                $content[] = [
                    $result->rma_id,
                    $result->rma_order_id,
                    $result->rma_type,
                    $result->order_id,
                    $result->customer_order_id,
                    $result->item_code,
                    $result->rma_date,
                    $result->status
                ];
            }
            $data = [
                'subject' => $date . '无理由RMA记录',
                'title' => $date . '无理由RMA记录明细',
                'header' => $header,
                'content' => $content
            ];
            return new \App\Mail\NoReasonRma($data);
        }
    }
}
