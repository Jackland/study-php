<?php

namespace App\Http\Controllers\Futures;

use App\Http\Controllers\Controller;
use App\Models\Future\Agreement;
use App\Models\Future\Apply;
use App\Models\Future\MarginDelivery;
use App\Repositories\Order\OrderProductRepositories;
use App\Repositories\Product\ProductChannel\NewStoresRepository;
use App\Services\Order\OrderProductService;
use Illuminate\Support\Facades\Artisan;

class FuturesController extends Controller
{
    public function __construct()
    {

    }

    public function testTimeOut()
    {
        Agreement::setTimeOutFutureAgreement();// 协议超时
        echo '协议超时刷新完成';
    }

    public function testBackOrder()
    {
        Artisan::call('future:contract');
        echo 'future contract delivery timeout';
    }

    public function testPayMargin()
    {
        MarginDelivery::stockMarginTimeOut(); // Buyer24小时内未支付现货保证金
        echo 'Buyer24小时内未支付现货保证金刷新完成';
    }

    public function testCompleted()
    {
        Agreement::FutureAgreementCompletedPayRecord();
        echo '转期货7天之后completed刷新完成';
    }

    public function testFuturesTerminated()
    {
        Agreement::FutureAgreementUncompletedPayRecord(); //转期货7天之后不能买 过期了
        echo '转期货7天之后不能买过期了刷新完成';
    }

    public function testSendDeliveryMessage()
    {
        $country_list = [81, 107, 222, 223];
        foreach ($country_list as $key => $country_id) {
            Agreement::sendDailyMessageByCountryId($country_id, Agreement::FUTURE_DELIVERY);//交货日期开始倒计时直至交货成功/失败，以天为单位，每天一封
        }
        echo '交货日期开始倒计时直至交货成功/失败，以天为单位，每天一封刷新完成';
    }


    public function testSendPayMessage()
    {
        $country_list = [81,107,222,223];
        foreach($country_list as $key => $country_id){
            Agreement::sendDailyMessageByCountryId($country_id,Agreement::FUTURE_BUYER_PAID);//交货日期开始倒计时直至交货成功/失败，以天为单位，每天一封
        }
        echo '交割方式为支付尾款，Buyer开始支付尾款开始直至Buyer未履约前一天，以天未单位，每天一封';

    }

    public function testApplyTimeOut()
    {
        Apply::applyTimeOut();
    }

}
