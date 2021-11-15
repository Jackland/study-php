<?php

namespace App\Services\Product;


use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductAuditStatus;
use App\Enums\Product\ProductStatus;
use App\Models\Link\CustomerPartnerToProduct;
use App\Models\Product\Product;
use App\Models\Product\ProductAudit;
use App\Repositories\Product\ProductRepository;
use Framework\Exception\Exception;
use Illuminate\Database\Query\Expression;

/**
 * Seller 产品列表页
 * Class ListsService
 * @package App\Services\Product
 */
class ProductListsService
{
    /**
     * 批量恢复商品为未软删除状态
     * @param array $productId
     * @param int $sellerId
     * @return array
     */
    public function setProductIsNotDeleted($productId, $sellerId)
    {
        if (is_string($productId) || is_int($productId)) {
            $productId = [(int)$productId];
        }

        if (!$this->checkSellerProductIds($productId, (int)$sellerId)) {
            return ['ret' => 0, 'msg' => __('操作无效！', [], 'controller/product')];
        }

        $temp = [];
        foreach ($productId as $id) {
            $subQuery = app(ProductRepository::class)->getSubQueryByProductId($id);
            $subQueryProductId = array_column($subQuery, 'set_product_id');
            $temp = array_merge($temp, $subQueryProductId);
        }
        $productId = array_unique(array_merge($productId, $temp));

        $rows = Product::whereIn('product_id', $productId)->update(['is_deleted' => 0]);// 软删除标志位置为0

        if ($rows < 1) {
            return ['ret' => -100, 'msg' => 'Failed'];
        }


        return ['ret' => 1, 'msg' => 'Success'];
    }


    /**
     * 校验产品是不是属于seller本身
     * @param array $productIds
     * @param int $customerId
     * @return bool
     */
    public function checkSellerProductIds(array $productIds, int $customerId)
    {

        $customerProductIds = Product::query()->alias('p')
            ->leftJoinRelations('customerPartnerToProduct as ctp')
            ->where(['ctp.customer_id' => $customerId])
            ->whereIn('p.product_id', $productIds)
            ->pluck('p.product_id')
            ->toArray();

        return (bool)(count($customerProductIds) == count($productIds));
    }


    /**
     * 下架产品
     * @param int $productId
     * @param int $sellerId
     * @return void
     * @throws Exception
     */
    public function setProductStatusOff(int $productId, int $sellerId)
    {
        $product = Product::query()->with(['customerPartnerToProduct'])->find($productId);
        if ($product->customerPartnerToProduct->customer_id != $sellerId) {
            throw new Exception('Not Found', 404);
        }

        if ($product->status == ProductStatus::OFF_SALE) {
            throw new Exception('Not Found', 400);
        }

        $product->status = ProductStatus::OFF_SALE;
        $product->is_once_available = YesNoEnum::YES;
        $product->save();

        ProductAudit::query()->where('product_id', $productId)
            ->where('customer_id', $sellerId)
            ->where('is_delete', YesNoEnum::NO)
            ->where('status', ProductAuditStatus::PENDING)
            ->update(['status' => ProductAuditStatus::CANCEL]);
    }

    /**
     * 批量下架
     * @param array $productIds
     * @param int $sellerId
     * @return bool|array
     */
    public function batchSetProductStatusOff(array $productIds, int $sellerId)
    {
        // 过滤传入的商品, 确保只处理该账号下的商品
        $products = CustomerPartnerToProduct::query()->where([
            'customer_id' => $sellerId,
        ])->whereIn('product_id', $productIds)->pluck('product_id')->toArray();
        if (empty($products)) {
            return true;
        }
        Product::query()->whereIn('product_id', $products)->update([
            'status' => ProductStatus::OFF_SALE,
            'is_once_available' => YesNoEnum::YES,
        ]);
        ProductAudit::query()->whereIn('product_id', $products)
            ->where([
                'customer_id' => $sellerId,
                'is_delete' => YesNoEnum::NO,
                'status' => ProductAuditStatus::PENDING
            ])->update(['status' => ProductAuditStatus::CANCEL]);
        return $products;
    }
}
