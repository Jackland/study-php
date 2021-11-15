<?php

namespace Tests;

use App\Console\Commands\CancelPurchaseOrder;
use App\Console\Commands\TrackingUpload;
use App\Models\Purchase\PurchaseOrder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * @return mixed
     */
    public function testRefreshToken(){
        $cancelPurchaseOrder = new CancelPurchaseOrder();
        $result = $cancelPurchaseOrder->refreshToken();
        var_dump($result['access_token']);
        return $result;
    }

    public function testSend(){
        $trackingUpload = new TrackingUpload();
        $subOrders = [];
        $subOrders[] = array(
            'mer_sub_reference_id'=>"0807132118213218",
            'tracking_number'=>"test_10101033"
        );
        $postData= array(
            'PAY_GYYDAOBQG4YTGMRRGQ4DKMBRGQZDAMRQGA4DANT4' => $subOrders
        );
        $result = $trackingUpload->uploadTrackingInfo($postData);
        var_dump($result);
        return $result;
    }

    public function testUMF(){
        $cancelPurchaseOrder = new CancelPurchaseOrder();
        $request = new PurchaseOrder();
        $result = $cancelPurchaseOrder->umfPayMethod('109478',null,$request);
        var_dump($result);
        return $result;
    }

    public function testLog(){
        $preMsg = "该采购订单,,,";
        $msg="未查询到联动支付PayId";
        \Log::error($msg."\n\t".$preMsg);
    }

    public function testHandel(){
//        $cancelPurchaseOrder = new CancelPurchaseOrder();
//        $cancelPurchaseOrder->handle();
        $trackingUpload = new TrackingUpload();
        $trackingUpload->handle();
    }
}
