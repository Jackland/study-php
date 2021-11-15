<?php
/**
 * Created by PhpStorm.
 * User: jun
 * Date: 2020/01/11
 * Time: 9:31
 */

namespace App\Http\Controllers\Order;

use App\Helpers\ApiResponse;
use App\Helpers\LoggerHelper;
use App\Http\Controllers\Controller;
use App\Jobs\OrderInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class OrderInvoiceController extends Controller
{
    use ApiResponse;

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'buyer_id' => 'required|integer|exists:oc_customer,customer_id'
        ]);
        OrderInvoice::dispatch($request->buyer_id)->onQueue('order_invoice');
        LoggerHelper::logOrderInvoice('Invoice-新增生成:' . $request->buyer_id);

        return $this->success();
    }

}