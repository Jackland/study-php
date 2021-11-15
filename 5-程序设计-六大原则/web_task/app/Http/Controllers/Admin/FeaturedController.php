<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AdminErrorShowException;
use App\Helpers\LoggerHelper;
use App\Models\Module\Module;
use App\Models\Product\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class FeaturedController extends Controller
{
    protected $logFile = 'logs/admin/featured.log';

    //首页热销品
    public function featured()
    {
        $list = $this->getFeaturedProductList();
        $countries = [];// 国家列表
        foreach ($list as $country => $item) {
            $countries[$country] = $country;
        }
        return view('admin.featured.featured')->with(['list' => $list, 'countries' => $countries]);
    }

    //保存
    public function saveFeatured(Request $request)
    {
        $nowList = $this->getFeaturedProductList();
        if (empty($nowList)) {
            throw new AdminErrorShowException('商品不存在');
        }
        // 执行保存操作
        $this->validate($request, [
            'country' => ['required', Rule::in(array_keys($nowList))],
            'product_id' => 'required'
        ]);
        $country = $request->input('country');
        $productIds = $request->input('product_id');
        $isTough = $request->input('is_tough');
        // 校验商品是否正确
        $productIds = explode(',', $productIds);
        if (count($productIds) <= 2) {
            throw new AdminErrorShowException('大于2个商品才会显示');
        }
        if (!$isTough) {
            $checkProduct = true;// 保存校验状态
            $replaceProducts = $this->getProducts($productIds, true);
            $replaceProductIdList = $replaceProducts->pluck('product_id')->toArray();
            $replaceProductCountryList = $replaceProducts->pluck('country', 'product_id')->toArray();
            $errors = [];
            foreach ($productIds as $productId) {
                if (!in_array($productId, $replaceProductIdList)) {
                    $errors[] = $productId . '不存在';
                    $checkProduct = false;
                } elseif ($replaceProductCountryList[$productId] != $country) {
                    $errors[] = $productId . '不属于' . $country;
                    $checkProduct = false;
                }
            }
            if (!$checkProduct) {
                throw new AdminErrorShowException(implode('|', $errors));
            }
        }
        $saveProductList = [];
        foreach ($nowList as $key => $item) {
            if ($key == $country) {
                // 如果是选中的国家，直接替换
                $saveProductList = array_merge($saveProductList, $productIds);
            } else {
                $saveProductList = array_merge($saveProductList, $item['all']);
            }
        }
        // 保存
        $featuredData = $this->getFeaturedData();
        // 记录老数据日志
        $logMsg = '旧数据:' . json_encode($featuredData);
        $featuredData['product'] = $saveProductList;
        // 记录新数据日志
        $newSetting = json_encode($featuredData);
        $logMsg .= '新数据:' . $newSetting;
        $res = Module::query()->where('code', 'featured')->update(['setting' => $newSetting]);
        if ($res) {
            Log::useFiles(storage_path($this->logFile));
            Log::info('管理员(' . Auth::user()->name . ')修改首页推荐。' . $logMsg);
        }
        return response()->redirectTo('admin/featured');
    }

    //获取推荐数据
    private function getFeaturedData()
    {
        $data = Module::query()->where('code', 'featured')->value('setting');
        return json_decode($data, true);
    }

    //获取推荐商品列表
    private function getFeaturedProductList()
    {
        $featuredData = $this->getFeaturedData();
        $nowProductIds = $featuredData['product'] ?? [];
        if (empty($nowProductIds)) {
            return [];
        }
        $nowProducts = $this->getProducts($nowProductIds, 1);
        $allProducts = $this->getProducts($nowProductIds, 0)->pluck('country', 'product_id')->toArray();
        $nowProductCountries = $nowProducts->pluck('country', 'product_id');
        $productCountryGroupList = [];
        foreach ($nowProductIds as $nowProductId) {
            if (!empty($allProducts[$nowProductId])) {
                $productCountryGroupList[$allProducts[$nowProductId]]['all'][] = $nowProductId;
                if (isset($nowProductCountries[$nowProductId])) {
                    $productCountryGroupList[$nowProductCountries[$nowProductId]]['show'][] = $nowProductId;
                } else {
                    $productCountryGroupList[$allProducts[$nowProductId]]['not_show'][] = $nowProductId;
                }
            }
        }
        return $productCountryGroupList;
    }

    /**
     * 获取商品信息
     *
     * @param array $productIds 商品ID
     * @param bool $isShow 是否做可显示判断
     *
     * @return \Illuminate\Support\Collection
     */
    private function getProducts(array $productIds, bool $isShow)
    {
        $model = Product::join('oc_customerpartner_to_product as c2p', 'c2p.product_id', '=', 'oc_product.product_id')
            ->join('oc_customer as c', 'c.customer_id', '=', 'c2p.customer_id')
            ->join('oc_country as cou', 'cou.country_id', '=', 'c.country_id')
            ->leftJoin('oc_product_to_tag as ptag', 'ptag.product_id', '=', 'oc_product.product_id')
            ->leftJoin('oc_customerpartner_to_customer as c2c', 'c2c.customer_id', '=', 'c.customer_id')
            ->leftJoin('oc_product_to_store as p2s', 'oc_product.product_id', '=', 'p2s.product_id')
            ->whereIn('oc_product.product_id', $productIds)->select(['oc_product.product_id', 'cou.iso_code_3 as country']);
        if ($isShow) {
            $model = $model->where('oc_product.status', 1)->where('oc_product.is_deleted', 0)->where('oc_product.buyer_flag', 1)
                ->where('oc_product.part_flag', 0)->where('oc_product.quantity', '>', 0)->whereIn('oc_product.product_type', [0])
                ->whereNotNull('oc_product.image')->where('oc_product.image', '<>', '')->where('c.status', 1)
                ->where('c2c.show', 1)->where('p2s.store_id', 0)->where(function ($query) {
                    $query->where('ptag.tag_id', '<>', 2)->orWhereNull('ptag.tag_id');
                })
                ->whereNotIn('customer_group_id', [17, 18, 19, 20])
                ->whereNotIn('c.customer_id', [694, 696, 746, 907, 908, 340, 491, 631, 838, 1310])
                ->whereNotIn('c.accounting_type', [3, 4]);
        }
        return $model->get();
    }
}
