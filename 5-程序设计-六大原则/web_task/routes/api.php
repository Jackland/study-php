<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/email/send', 'Message\MessageController@sendMail');
Route::post('/message/system', 'Message\MessageController@sendSystemMessage');
Route::post('/message/batchSendSystemMessage', 'Message\MessageController@batchSendSystemMessage');
Route::post('/message/store', 'Message\MessageController@sendStoreMessage');
Route::post('/message/batch-store', 'Message\MessageController@batchStoreMessage');
Route::post('/message/sendStationLetter', 'Message\MessageController@sendStationLetter');
Route::post('/purchaseOrder/process', 'QueueController@purchaseOrderQueue');
Route::post('/feeOrder/balance', 'FeeOrder\FeeOrderController@sendMail');
Route::any('/product/packed', 'Product\ProductController@packToZip');
Route::post('/order/generate', 'Order\OrderInvoiceController@generate');

// 发送新版站内信消息
Route::post('/message/send', 'Message\MessageController@sendMsg');
// 按模版发送邮件
Route::post('/email/sendTemplate', 'EmailController@sendTemplate');