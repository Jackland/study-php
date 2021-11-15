<?php

use App\Enums\Product\ProductTransactionType;
use App\Models\Marketing\Coupon;
use App\Repositories\Marketing\CouponRepository;

/**
 * Class ModelExtensionTotalGigaCoupon
 */
class ModelExtensionTotalGigaCoupon extends Model
{
    /**
     * @var mixed
     */
    private $customerId;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->language('extension/total/giga_coupon');
        $this->customerId = $this->customer->getId();
    }

    /**
     * @param $total
     * @param array $products
     * @param array $params
     */
    public function getTotalByCartId($total, $products = [], $params = [])
    {
        $this->getCoupons(...func_get_args());
    }

    /**
     * @param $total
     * @param array $products
     * @param array $params
     */
    public function getTotalByProducts($total, $products = [], $params = [])
    {
        $this->getCoupons(...func_get_args());
    }

    /**
     * @param $total
     */
    public function getTotal($total) {
        $total['totals'][] = array(
            'code' => 'giga_coupon',
            'title' => $this->language->get('text_giga_coupon'),
            'value' => 0,
            'sort_order' => $this->config->get('total_giga_coupon_sort_order')
        );
    }

    /**
     * @param $total
     */
    public function getDefaultTotal($total)
    {
        $total['totals'][] = array(
            'code' => 'giga_coupon',
            'title' => $this->language->get('text_giga_coupon'),
            'value' => 0,
            'sort_order' => $this->config->get('total_giga_coupon_sort_order')
        );
    }

    /**
     * @param $total
     * @param array $products
     * @param array $params
     */
    protected function getCoupons($total, $products = [], $params = [])
    {
        $couponAmount = 0;
        $selectCouponIds = [];

        // 满减金额
        $totalCodeValueMap = array_column($total['totals'], 'value', 'code');
        $fullReductionCampaignAmount = abs($totalCodeValueMap['promotion_discount'] ?? 0);

        $subTotal = $this->productsSubTotal($products);

        if (!$this->customerId) {
            $couponAmount = 0;
            goto end;
        }

        if (isset($params['coupon_ids']) && empty($params['coupon_ids'])) {
            // 不使用优惠券
            $couponAmount = 0;
            goto end;
        }

        // 获取货值总和
        $amountRequirement = 0;
        foreach ($products as $product) {
            if ($product['product_type'] != 0) {
                continue;
            }

            if ($product['type_id'] == ProductTransactionType::SPOT) {
                $amountRequirement += $product['quote_amount'] * $product['quantity'];
            } else {
                $amountRequirement += $product['price'] * $product['quantity'];
            }
        }

        if (!isset($params['coupon_ids'])) {
            // 查找优惠券最优使用
            /** @var Coupon $coupon */
            $coupon = app(CouponRepository::class)->getBestDealAvailableCouponByCustomerId($this->customerId, $amountRequirement, $subTotal - $fullReductionCampaignAmount, ['denomination', 'id']);
            if ($coupon) {
                $couponAmount = $coupon->denomination;
                $selectCouponIds = [$coupon->id];
                goto end;
            }
        }

        if (isset($params['coupon_ids']) && !empty($params['coupon_ids'])) {
            // 使用指定优惠券
            $coupons = app(CouponRepository::class)->getCustomerAvailableCouponsByIds($params['coupon_ids'], $this->customerId, ['denomination', 'id']);
            $denominations = $coupons->sum('denomination');
            if ($denominations > 0 && ($subTotal - $fullReductionCampaignAmount >= $denominations)) {
                $couponAmount = $denominations;
                $selectCouponIds = $coupons->pluck('id')->toArray();
            }
        }

        end:

        $total['total'] = $total['total'] - $couponAmount;
        $total['totals'][] = array(
            'code' => 'giga_coupon',
            'title' => $this->language->get('text_giga_coupon'),
            'value' => -$couponAmount,
            'coupon_ids' => $selectCouponIds,
            'sort_order' => $this->config->get('total_giga_coupon_sort_order')
        );
    }

    /**
     * 当前选中产品的总货值
     * @param array $productIdInfoMap
     * @return float|int
     */
    private function productsSubTotal(array $productIdInfoMap)
    {
        $subTotal = 0;
        foreach ($productIdInfoMap as $productId => $product) {
            if ($product['type_id'] == ProductTransactionType::SPOT) {
                $subTotal += $product['quote_amount'] * $product['quantity'];
            } else {
                $subTotal += $product['price'] * $product['quantity'];
            }
        }

        return $subTotal;
    }
}
