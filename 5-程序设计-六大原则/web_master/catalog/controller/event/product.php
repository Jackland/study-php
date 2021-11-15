<?php

use App\Components\Storage\StorageCloud;
use App\Logging\Logger;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use App\Models\Product\Product;
use App\Enums\Product\ProductStatus;
use App\Repositories\Product\CategoryRepository;
use App\Helper\ProductHelper;

class ControllerEventProduct extends Controller
{
    public function editAfter($route, $args, $output)
    {
        $post = $args[0];
        if (!isset($post['fromNew']) || $post['fromNew'] != 1) return;
        $product_id = $post['product_id'];
        $productInfo = Product::find($product_id);
        if (in_array($productInfo->status, ProductStatus::notSale()) && $output !== false) {
            $this->addProductMaterialPackage($product_id, $post);
            // 只有编辑产品的情况下才会处理主图产品
            $this->resolveMainImage($product_id, $post);
            // 38538 只有在待上架时，运费才需修正 （待上架时，尺寸或者combo可修改）
            if ($productInfo->status == ProductStatus::WAIT_SALE) {
                $this->changeComboFreight($product_id, $post);
            }
            //已选择商品类目信息设置
            $this->saveSelectedCategory($product_id, $post);
        }
    }

    /**
     * @param $route
     * @param $args
     */
    public function editBefore($route, $args)
    {

    }

    /**
     * @param $route
     * @param $args
     * @param $output
     */
    public function addAfter($route, $args, $output)
    {
        $post = $args[0];
        if (!isset($post['fromNew']) || $post['fromNew'] != 1) return;
        $productId = $output['product_id'];
        $comboFlag = (int)$post['combo_flag'];
        if ($productId){
            $this->addProductMaterialPackage($productId, $post);
            // 运费修正
            $this->changeComboFreight($productId, $post);
            //已选择商品类目信息设置
            $this->saveSelectedCategory($productId,$post);
            //同步商品到OMD
            if (customer()->isUSA() && !customer()->isInnerAccount() && $comboFlag == 0) {
                try {
                    ProductHelper::sendSyncProductsRequest([$productId]);
                } catch (\Throwable $e) {
                    Logger::syncProducts($e->getMessage());
                }
            }
        }
    }

    public function addBefore($route, $args)
    {

    }

    /**
     * 校验主图是否存在并且有效
     * @param array $post
     * @return bool
     */
    protected function checkProductImageValid(array $post): bool
    {
        return
            ($post['buyer_flag'] == 0) ||
            ($post['buyer_flag'] == 1 && $post['image'] && StorageCloud::image()->fileExists($post['image']));
    }

    /**
     *  14103 taixing
     *
     * @param int $product_id
     */
    protected function setIsOnceAvailable(int $product_id)
    {
        $this->orm
            ->table(DB_PREFIX . 'product')
            ->where('product_id', $product_id)
            ->update(['is_once_available' => 1]);
    }

    /**
     * @param int $product_id
     * @param array $post
     */
    protected function addProductMaterialPackage(int $product_id, array $post)
    {
        try {
            $this->addProductMaterialPackageImages($product_id, $post['material_images']);
            $this->addProductMaterialPackageVideo($product_id, $post['material_video']);
            $this->addProductMaterialPackageFile($product_id, $post['material_manuals']);
            // 原创产品
            $this->addProductMaterialOriginalDesign($product_id, $post['original_design']);
        } catch (Throwable $e) {
            $this->log->write($e);
        }
    }

    /**
     * 原创产品add&edit图片
     * @param int $product_id
     * @param array $arr
     * @throws Throwable
     */
    public function addProductMaterialOriginalDesign(int $product_id, array $arr)
    {
        $restInIds = [];
        $addArrays = [];
        array_map(function ($item) use (&$restInIds, &$addArrays) {
            if (isset($item['m_id']) && ($item['m_id'] > 0)) {
                array_push($restInIds, $item['m_id']);
            } else {
                array_push($addArrays, $item);
            }
        }, $arr);
        $db = $this->orm->getConnection();
        $db->transaction(function () use ($restInIds, $addArrays, $product_id) {
            $db = $this->orm->getConnection();
            $db->table(DB_PREFIX . 'product_package_original_design_image')
                ->where(['product_id' => $product_id])
                ->when(count($restInIds) > 0, function (Builder $q) use ($restInIds) {
                    return $q->whereNotIn('product_package_image_id', $restInIds);
                })
                ->delete();
            if ($addArrays) {
                $addArrays = array_map(function ($item) use ($product_id) {
                    return [
                        'product_id' => $product_id,
                        'image_name' => substr($item['url'], strrpos($item['url'], '/') + 1),
                        'origin_image_name' => $item['name'],
                        'file_upload_id' => $item['file_id'],
                        'image' => $item['url'],
                    ];
                }, $addArrays);
                $db->table(DB_PREFIX . 'product_package_original_design_image')->insert($addArrays);
            }
        });
    }

    /**
     * @param int $product_id
     * @param array $arr
     * @throws Throwable
     */
    protected function addProductMaterialPackageImages(int $product_id, array $arr)
    {
        $restInIds = [];
        $addArrays = [];
        array_map(function ($item) use (&$restInIds, &$addArrays) {
            if (isset($item['m_id']) && ($item['m_id'] > 0)) {
                array_push($restInIds, $item['m_id']);
            } else {
                array_push($addArrays, $item);
            }
        }, $arr);
        $db = $this->orm->getConnection();
        $db->transaction(function () use ($restInIds, $addArrays, $product_id) {
            $db = $this->orm->getConnection();
            $db->table(DB_PREFIX . 'product_package_image')
                ->where(['product_id' => $product_id])
                ->when(count($restInIds) > 0, function (Builder $q) use ($restInIds) {
                    return $q->whereNotIn('product_package_image_id', $restInIds);
                })
                ->delete();
            if ($addArrays) {
                $addArrays = array_map(function ($item) use ($product_id) {
                    return [
                        'product_id' => $product_id,
                        'image_name' => substr($item['url'], strrpos($item['url'], '/') + 1),
                        'origin_image_name' => $item['name'],
                        'file_upload_id' => $item['file_id'],
                        'image' => $item['url'],
                    ];
                }, $addArrays);
                $db->table(DB_PREFIX . 'product_package_image')->insert($addArrays);
            }
        });
    }

    /**
     * @param int $product_id
     * @param array $arr
     * @throws Throwable
     */
    protected function addProductMaterialPackageVideo(int $product_id, array $arr)
    {
        $restInIds = [];
        $addArrays = [];
        array_map(function ($item) use (&$restInIds, &$addArrays) {
            if (isset($item['m_id']) && ($item['m_id'] > 0)) {
                array_push($restInIds, $item['m_id']);
            } else {
                array_push($addArrays, $item);
            }
        }, $arr);
        $db = $this->orm->getConnection();
        $db->transaction(function () use ($restInIds, $addArrays, $product_id) {
            $db = $this->orm->getConnection();
            $db->table(DB_PREFIX . 'product_package_video')
                ->where(['product_id' => $product_id])
                ->when(count($restInIds) > 0, function (Builder $q) use ($restInIds) {
                    return $q->whereNotIn('product_package_video_id', $restInIds);
                })
                ->delete();
            if ($addArrays) {
                $addArrays = array_map(function ($item) use ($product_id) {
                    return [
                        'product_id' => $product_id,
                        'video_name' => substr($item['url'], strrpos($item['url'], '/') + 1),
                        'origin_video_name' => $item['name'],
                        'file_upload_id' => $item['file_id'],
                        'video' => $item['url'],
                    ];
                }, $addArrays);
                $db->table(DB_PREFIX . 'product_package_video')->insert($addArrays);
            }
        });
    }

    /**
     * @param int $product_id
     * @param array $arr
     * @throws Throwable
     */
    protected function addProductMaterialPackageFile(int $product_id, array $arr)
    {
        $restInIds = [];
        $addArrays = [];
        array_map(function ($item) use (&$restInIds, &$addArrays) {
            if (isset($item['m_id']) && ($item['m_id'] > 0)) {
                array_push($restInIds, $item['m_id']);
            } else {
                array_push($addArrays, $item);
            }
        }, $arr);
        $db = $this->orm->getConnection();
        $db->transaction(function () use ($restInIds, $addArrays, $product_id) {
            $db = $this->orm->getConnection();
            $db->table(DB_PREFIX . 'product_package_file')
                ->where(['product_id' => $product_id])
                ->when(count($restInIds) > 0, function (Builder $q) use ($restInIds) {
                    return $q->whereNotIn('product_package_file_id', $restInIds);
                })
                ->delete();
            if ($addArrays) {
                $addArrays = array_map(function ($item) use ($product_id) {
                    return [
                        'product_id' => $product_id,
                        'file_name' => substr($item['url'], strrpos($item['url'], '/') + 1),
                        'origin_file_name' => $item['name'],
                        'file_upload_id' => $item['file_id'],
                        'file' => $item['url'],
                    ];
                }, $addArrays);
                $db->table(DB_PREFIX . 'product_package_file')->insert($addArrays);
            }
        });
    }

    /**
     * 主图处理
     * @param int $product_id
     * @param array $arr
     */
    public function resolveMainImage(int $product_id, array $arr)
    {
        if (!empty($arr['image'])) return;
        $this->orm
            ->table(DB_PREFIX . 'product')
            ->where(['product_id' => $product_id])
            ->update(['image' => '']);
    }

    /**
     * 更新费用 上门取货-2 一件代发(dropship)-1
     * user：wangjinxin
     * date：2019/11/6 16:59
     * @param int $product_id
     * @param array $post
     */
    public function changeComboFreight(int $product_id, array $post)
    {
        if ($this->customer->isPartner()) {
            $freight = $this->getProductFreight($product_id);
            if (empty($freight)) return;
            try {
                $this->orm->getConnection()->transaction(function () use ($product_id, $freight) {
                    // 更新oc product表
                    $this->orm
                        ->table(DB_PREFIX . 'product')
                        ->where(['product_id' => $product_id])
                        ->update([
                            'freight' => $freight['accountFreight'] ?? 0,
                            'package_fee' => $freight['packageFee'] ?? 0,
                            'peak_season_surcharge' => $freight['peakSeasonTotalSurcharge'] ?? 0,
                            'danger_fee' => $freight['dangerFee'] ?? 0,
                        ]);
                    if (isset($freight['packageFee'])) {
                        $insert_arr = [
                            'fee' => $freight['packageFee'],
                            'create_time' => Carbon::now(),
                            'update_time' => Carbon::now(),
                        ];
                        $this->orm->table('oc_product_fee')->updateOrInsert(['product_id' => $product_id, 'type' => 1], $insert_arr);
                    }
                    if (isset($freight['dropShipPackageFee'])) {
                        $insert_arr = [
                            'fee' => $freight['dropShipPackageFee'],
                            'create_time' => Carbon::now(),
                            'update_time' => Carbon::now(),
                        ];
                        $this->orm->table('oc_product_fee')->updateOrInsert(['product_id' => $product_id, 'type' => 2], $insert_arr);
                    }

                });
            } catch (Throwable $e) {
                $this->log->write($e);
            }
        }
    }

    /**
     * 获取运费
     *
     * @param int $product_id
     * @return array|null
     */
    private function getProductFreight(int $product_id): ?array
    {
        $url = B2B_MANAGEMENT_BASE_URL . '/api/itemCodesAccountFreight';
        $token = B2B_MANAGEMENT_AUTH_TOKEN;
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            "Authorization: Bearer {$token}"
        ];
        $params = [$product_id];
        $this->log->write('---------------------------------------API WANGJINXIN-----------------------------------');
        $this->log->write($url);
        $this->log->write($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        $res = curl_exec($ch);
        curl_close($ch);
        ini_set('serialize_precision', -1);
        $res = @json_decode($res, true);
        $this->log->write($res);
        $this->log->write('-------------------------------------API END WANGJINXIN------------------------------------');
        if (!$res || !array_key_exists('data', $res)) {
            return null;
        }
        $freights = $res['data']['productFreights'] ?: [];
        if (empty($freights)) return null;
        return array_shift($freights);
    }

    /**
     * 新增或更新商品类目
     *
     * @param  int   $productId
     * @param  array $post
     *
     * @return bool
     */
    public function saveSelectedCategory($productId, $post)
    {
        $categories = (array)$post['product_category'];
        $categoryId = app(CategoryRepository::class)->getLastLowerCategoryId($categories);
        if ($productId && $categoryId > 0) {
            $selectedCategory = $this->orm::table('oc_store_selected_category')
                ->where('customer_id', $this->customer->getId())
                ->where('category_id', $categoryId)
                ->first();
            if ($selectedCategory) {
                $this->orm::table('oc_store_selected_category')
                    ->where('id', $selectedCategory->id)
                    ->where('customer_id', $this->customer->getId())
                    ->update([
                        'product_id' => $productId,
                        'update_num' => $selectedCategory->update_num + 1,
                        'update_time' => Carbon::now()
                    ]);
            } else {
                $this->orm::table('oc_store_selected_category')
                    ->insert([
                        'customer_id' => $this->customer->getId(),
                        'category_id' => $categoryId,
                        'product_id' => $productId,
                        'update_num' => 1,
                        'create_time' => Carbon::now(),
                        'update_time' => Carbon::now(),
                    ]);
            }
        }
        return true;
    }
}
