<?php

namespace App\Http\Controllers\Product;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Jobs\PackToZip;
use App\Repositories\Product\ProductRepository;
use App\Services\Product\ProductService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    use ApiResponse;

    public function packToZip(Request $request)
    {
        //dd(app(ProductService::class)->packToZip(12610, 27));
//        $request->validate([
//            'customer_id' => 'required|integer|exists:oc_customer,customer_id',
//            'product_id' => 'required|integer',
//        ]);
        try {
            $data = $request->input('data');
            foreach ($data as $item) {
                if (empty($item['customer_id']) || empty($item['product_id'])) {
                    continue;
                }
                PackToZip::dispatch($item)->onQueue('pack_to_zip');
            }
            return $this->success();

        } catch (\Exception $e) {
            return $this->message($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


}
