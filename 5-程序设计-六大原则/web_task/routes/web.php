<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/email/online/buyer', 'Statistics\OnlineController@buyer');
Route::get('/email/online/seller', 'Statistics\OnlineController@seller');
Route::get('/email/online/rmaNoReason', 'Rma\RmaInfoController@noReasonRma');

Route::get('/email/rebaterecharge/first', 'Statistics\RebateRechargeController@first');
Route::get('/email/rebaterecharge/last', 'Statistics\RebateRechargeController@last');
//Route::get('/email/rebaterecharge/test/{type?}', 'Statistics\RebateRechargeController@test');

// test 路由 期货二期后面会删掉
Route::get('/testTimeOut', 'Futures\FuturesController@testTimeOut');
Route::get('/testBackOrder', 'Futures\FuturesController@testBackOrder');
Route::get('/testPayMargin', 'Futures\FuturesController@testPayMargin');
Route::get('/testCompleted', 'Futures\FuturesController@testCompleted');
Route::get('/testFuturesTerminated', 'Futures\FuturesController@testFuturesTerminated');
Route::get('/testSendDeliveryMessage', 'Futures\FuturesController@testSendDeliveryMessage');
Route::get('/testSendPayMessage', 'Futures\FuturesController@testSendPayMessage');
Route::get('/testApplyTimeOut', 'Futures\FuturesController@testApplyTimeOut');
Route::get('/feeOrder/balance', 'FeeOrder\FeeOrderController@sendMail');

/**
 * @see \Illuminate\Routing\Router::auth()
 */
Auth::routes();

// admin 必须登录
Route::group(['prefix' => 'admin', 'namespace' => 'Admin', 'middleware' => ['auth']], function () {
    Route::get('/', 'IndexController@index')->name('admin.home');

    Route::group(['prefix' => 'featured'], function () {
        Route::get('/', 'FeaturedController@featured')->name('featured.index');
        Route::post('save', 'FeaturedController@saveFeatured')->name('featured.save');
    });

    Route::group(['prefix' => 'stock'], function () {
        Route::get('bind', 'StockController@bindIndex')->name('stock.bind');
        Route::get('fba', 'StockController@fbaIndex')->name('stock.fba');
        Route::get('bo', 'StockController@boIndex')->name('stock.bo');
    });

    Route::group(['prefix' => 'product', 'namespace' => 'Product',], function () {
        Route::any('changeTag', 'ProductTagController@changeTag')->name('product.changeTag');
    });

    Route::get('/rebate/repair','RebateController@repair')->name('rebate.repair');


    Route::group(['prefix' => 'buyer'], function () {
        Route::get('airwallex','BuyerController@airwallex')->name('buyer.airwallex');
        Route::post('airwallex/save','BuyerController@airwallexSave')->name('buyer.airwallex.save');

        Route::get('loginLimit','BuyerController@loginLimit')->name('buyer.loginLimit');
        Route::post('loginLimit/save','BuyerController@loginLimitSave')->name('buyer.loginLimit.save');
    });

    Route::group(['prefix' => 'customer'], function () {
        Route::match(['get', 'post'], 'telephone-ignore-verify','CustomerController@telephoneIgnoreVerify')->name('customer.telephoneIgnoreVerify');
    });

});

