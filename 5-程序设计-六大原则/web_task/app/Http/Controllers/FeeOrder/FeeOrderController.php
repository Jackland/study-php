<?php

namespace App\Http\Controllers\FeeOrder;

use App\Http\Controllers\Controller;
use App\Jobs\FeeOrderMail;
use Illuminate\Http\Request;

class FeeOrderController extends Controller
{
    public function sendMail(Request $request)
    {
        $request->validate(['id' => 'required',]);
        FeeOrderMail::dispatch($request->input('id'))
            ->delay(now()->addMinute(1)); // 延迟一段时间发送，因为调用该接口的地方可能在事务中
    }
}
