<?php

namespace App\Repositories\Product;

use App\Enums\Common\YesNoEnum;
use App\Helper\ProductHelper;
use App\Models\Link\ProductToTag;
use App\Models\Product\Product;

class ProductTagRepository
{
    /**
     * 获取一些产品ltl超规格提醒的产品
     * @param array $productIds
     * @param int $customerId
     * @param array $lengthWithHeightWeight
     * @return array
     */
    public function getSomeLtlRemindProducts(array $productIds, int $customerId, $lengthWithHeightWeight = [])
    {
        function formatProduct(Product $product, $girthAndLongestSide, $remindVolumes) {
            $product->setAttribute('girt_longest_side', $girthAndLongestSide);
            $product->setAttribute('remind_volumes', $remindVolumes);
            $product->width = round($product->width, 2);
            $product->length = round($product->length, 2);
            $product->height = round($product->height, 2);
            $product->weight = round($product->weight, 2);
            return $product;
        }

        if (empty($productIds)) {
            $tempProduct = new Product();
            $tempProduct->length = $lengthWithHeightWeight['length'];
            $tempProduct->width = $lengthWithHeightWeight['width'];
            $tempProduct->height = $lengthWithHeightWeight['height'];
            $tempProduct->weight = $lengthWithHeightWeight['weight'];
            $tempProduct->mpn = $lengthWithHeightWeight['mpn'];
            [$remindVolumes, $girthAndLongestSide] = $this->getProductLtlRemindVolumeAndGirthLongestSide($tempProduct);

            $remindProducts[] = formatProduct($tempProduct, $girthAndLongestSide, $remindVolumes);
            return $remindProducts;
        }

        $products = Product::query()->with(['customerPartnerToProduct', 'parentProducts', 'parentProducts.product'])->findMany($productIds);
        if ($products->isEmpty()) {
            return [];
        }

        $remindProducts = [];
        foreach ($products as $product) {
            /** @var Product $product */
            if (!$product->customerPartnerToProduct || $product->customerPartnerToProduct->customer_id != $customerId) {
                continue;
            }

            if ($product->combo_flag == YesNoEnum::YES) {
                continue;
            }

            [$remindVolumes, $girthAndLongestSide] = $this->getProductLtlRemindVolumeAndGirthLongestSide($product);
            if (!empty($remindVolumes)) {
                $remindProducts[] = formatProduct($product, $girthAndLongestSide, $remindVolumes);
            }
        }

        return $remindProducts;
    }

    /**
     * Seller页面 产品列表页 Valid Tab页面，是否显示 设置为LTL产品 的按钮
     * @param int $countryId
     * @param array $productInfo 数据中一条 oc_product 记录
     * @param string $handle ltl|express
     * @return bool
     * @throws \Exception
     */
    public function isShowLTLOrExpressButton($countryId, $productInfo = [], $handle = 'ltl')
    {
        if ($countryId != AMERICAN_COUNTRY_ID) {
            return false;
        }

        if (customer()->isInnerAccount()) {
            return false;
        }

        if ($productInfo['combo_flag']) {
            return false;
        }

        $ltlRemindLevel = ProductHelper::getProductLtlRemindLevel($productInfo['width'], $productInfo['length'], $productInfo['height'], $productInfo['weight']);
        if ($ltlRemindLevel != 1) {
            return false;
        }

        $existLtl = ProductToTag::query()->where('tag_id', 1)->where('product_id', $productInfo['product_id'])->exists();

        switch ($handle) {
            case 'ltl':
                return !$existLtl;
            case 'express':
                return $existLtl;
        }

        return false;
    }

    /**
     * 获取产品ltl需要提醒的字段和周长加最长边 girth-longest_side 周长+次长 weight重量 max_side最长边
     * @param Product $product
     * @return array [提醒字段和周长加最长边]
     */
    public function getProductLtlRemindVolumeAndGirthLongestSide(Product $product): array
    {
        // 按照长度大小排序从小到大
        $tmp = [floatval($product->width), floatval($product->height), floatval($product->length)];
        sort($tmp);
        [$mintSide, $mediumSide, $maxSide] = $tmp;

        // 周长
        $girth = 2 * ($mintSide + $mediumSide);
        // 最长边+周长
        $girthAndLongestSide = $girth + $maxSide;

        $remindVolumes = [];
        // 产品尺寸符合以下任何一个条件，触发确认弹窗。 153 inches＜【最长边+周长】≤165 inches 148 lbs＜【实际重量】≤150 lbs 105 inches＜【最长边】≤108 inches
        if ($girthAndLongestSide <= 165 && $girthAndLongestSide > 153) {
            $remindVolumes[] = 'girth-longest_side';
        }
        if ($product->weight <= 150 && $product->weight > 148) {
            $remindVolumes[] = 'weight';
        }
        if (floatval($product->width) <= 108 && floatval($product->width) > 105) {
            $remindVolumes[] = 'width_side';
        }
        if (floatval($product->height) <= 108 && floatval($product->height) > 105) {
            $remindVolumes[] = 'height_side';
        }
        if (floatval($product->length) <= 108 && floatval($product->length) > 105) {
            $remindVolumes[] = 'length_side';
        }

        return [$remindVolumes, $girthAndLongestSide];
    }
}
