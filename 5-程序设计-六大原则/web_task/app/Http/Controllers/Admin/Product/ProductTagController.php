<?php

namespace App\Http\Controllers\Admin\Product;

use App\Exceptions\AdminErrorShowException;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Product\Product;
use App\Models\Product\Tag;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Redirect;

class ProductTagController extends Controller
{
    public function changeTag(Request $request)
    {
        $filterSkuId = $request->input('filter_sku_id');
        $filterProductId = $request->input('filter_product_id');
        $filterTagId = $request->input('filter_tag_id');
        // 变更属性
        if (!empty($filterProductId) && !empty($filterTagId)) {
            $this->changeProductAttribute($filterProductId, $filterTagId);
            return Redirect::route('product.changeTag')
                ->with('success', "商品ID[$filterProductId]修改属性[$filterTagId]成功!");
        }
        // 搜索功能
        if (!empty($filterSkuId)) {
            $products = Product::query()
                ->where(function (Builder $q) use ($filterSkuId) {
                    $q->where('sku', $filterSkuId);
                    $q->orWhere('mpn', $filterSkuId);
                    $q->orWhere('product_id', $filterSkuId);
                })
                ->get();
            if ($products->count() == 0) {
                throw new AdminErrorShowException("{$filterSkuId}没有对应的商品信息.");
            }
            $products = $products->map(function (Product $product) {
                $product->customer = Customer::find(
                    DB::table('oc_customerpartner_to_product')
                        ->where('product_id', $product->product_id)
                        ->value('customer_id')
                );
                return $product;
            });
            return view('admin.product.change_tag', ['products' => $products]);
        }
        return view('admin.product.change_tag');
    }

    private function changeProductAttribute(int $productId, int $tagId)
    {
        $product = Product::withCount(['tags'])->find($productId);
        if ($product->combo_flag == 1 || $product->part_flag == 1 || $product->tags_count > 0) {
            throw new AdminErrorShowException("商品$product->sku[$product->product_id]不能为非普通商品.");
        }
        $tagAttr = [
            'product_id' => $product->product_id,
            'is_sync_tag' => 0,
            'create_user_name' => 'yzc_task_work',
            'create_time' => Carbon::now(),
            'update_user_name' => null,
            'update_time' => null,
            'program_code' => 'yunwei',
        ];
        try {
            DB::beginTransaction();
            switch ($tagId) {
                case 1:    // ltl
                {
                    $tagAttr['tag_id'] = 1;
                    Tag::create($tagAttr);
                    break;
                }
                case 2:    // part
                {
                    $product->part_flag = 1;
                    $product->save();
                    $tagAttr['tag_id'] = 2;
                    Tag::create($tagAttr);
                    break;
                }
                case 3:    // combo
                {
                    $product->combo_flag = 1;
                    $product->save();
                    $tagAttr['tag_id'] = 3;
                    Tag::create($tagAttr);
                    break;
                }
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            throw new AdminErrorShowException($e->getMessage());
        }
    }
}