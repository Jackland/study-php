<?php

namespace App\Repositories\CWF;

use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Enums\Rebate\RebateAgreementResultEnum;
use App\Helper\CountryHelper;
use App\Models\Product\Product;
use App\Models\Product\ProductLock;
use App\Models\Product\ProductQuote;
use App\Models\Product\ProQuoteDetail;
use App\Models\Rebate\RebateAgreementItem;
use Illuminate\Support\Carbon;

class CloudWholesaleFulfillmentMatchStockRepository
{
    private $sku;
    private $quantity;
    private $unavailableProductIds;
    public $matchInfo = [];

    public function __construct($sku, $quantity, $unavailableProductIds)
    {
        //  WF188702EAA
        //  PP038658EAA
        $this->sku = $sku;
        $this->quantity = $quantity;
        $this->unavailableProductIds = $unavailableProductIds;
    }

    /**
     * 获取匹配库存的规则
     * @return array
     */
    public function getMatchInfo(): array
    {
        $buyerId = customer()->getId();
        $isCombo = Product::query()->where('sku', $this->sku)->value('combo_flag');
        $lockColumn = $isCombo ? 'parent_product_id' : 'product_id';
        $sellerInfo = Product::query()->alias('p')
            ->leftJoinRelations(['customerPartnerToProduct as ctp'])
            ->leftJoin(DB_PREFIX . 'buyer_to_seller as bts', 'bts.seller_id', '=', 'ctp.customer_id')
            ->leftJoin(DB_PREFIX . 'customer as c', 'bts.seller_id', '=', 'c.customer_id')
            ->leftJoin('oc_product_lock as l', function ($join) use ($buyerId, $lockColumn) {
                return $join->on('l.' . $lockColumn, '=', 'p.product_id')
                    ->leftJoin('tb_sys_margin_agreement as ma', 'ma.id', 'l.agreement_id')
                    ->where('l.type_id', ProductTransactionType::MARGIN)
                    /** @see \ModelCatalogMarginProductLock::checkAgreementIsValid() */
                    ->whereIn('ma.status', [6, 8])
                    ->where('ma.expire_time', '>', Carbon::now())
                    ->where('ma.buyer_id', $buyerId);
            })
            ->leftjoin('oc_product_quote as opq', function ($join) use ($buyerId) {
                return $join->on('opq.product_id', '=', 'p.product_id')
                    ->where('opq.status', 1)
                    ->where('opq.customer_id', $buyerId);
            })
            ->where(
                [
                    'p.sku' => $this->sku,
                    'bts.buyer_id' => $buyerId,
                    'p.status' => 1,
                    'p.is_deleted' => 0,
                    'c.status' => 1,
                    'c.country_id' => CountryHelper::getCountryByCode(session('country')),
                    'p.buyer_flag' => 1,
                    'bts.buy_status' => 1,
                    'bts.buyer_control_status' => 1,
                    'bts.seller_control_status' => 1,
                ]
            )
            ->whereNotIn('p.product_id', $this->unavailableProductIds)
            ->whereIn('p.product_type', [ProductType::NORMAL])
            ->whereNotIn('c.accounting_type', [CustomerAccountingType::GIGA_ONSIDE])
            ->selectRaw('p.price,p.product_id,ctp.customer_id,ifnull(max(opq.quantity),0) as pq_quantity,p.quantity,ifnull(max(l.qty/l.set_qty),0) as margin_qty,(p.quantity + ifnull(max(l.qty/l.set_qty),0)) as t_quantity')
            ->groupBy(['p.product_id'])
            ->orderByRaw('t_quantity desc')
            ->get();
        $solution = [];
        $currentQuantity = $this->quantity;

        foreach ($sellerInfo as $key => $value) {
            if ($value->pq_quantity >= $this->quantity
                || $value->quantity >= $currentQuantity
                || $value->margin_qty >= $currentQuantity
            ) {

                if ($value->pq_quantity >= $this->quantity) {
                    // 走议价
                    $spotAgreementId = $this->getSpotAgreementId($value->product_id, (int)$this->quantity);
                    if ($spotAgreementId) {
                        $solution[] = [
                            'seller_id' => $value->customer_id,
                            'product_id' => $value->product_id,
                            'quantity' => $currentQuantity,
                            'transaction_type' => ProductTransactionType::SPOT,
                            'agreement_id' => $spotAgreementId,
                        ];
                        $currentQuantity = 0;
                        break;
                    }
                }

                if ($value->margin_qty >= $currentQuantity) {
                    // 走现货保证金
                    $solution[] = [
                        'seller_id' => $value->customer_id,
                        'product_id' => $value->product_id,
                        'quantity' => $currentQuantity,
                        'transaction_type' => ProductTransactionType::MARGIN,
                        'agreement_id' => $this->getMarginAgreementId($value->product_id, (int)$currentQuantity, '>='),
                    ];
                    $currentQuantity = 0;
                    break;
                }

                if ($value->quantity >= $currentQuantity) {
                    // 按照库存数量够的议价>现货保证金>价格最低（返点，normal，精细化价格以及阶梯价）的优先级，匹配购买的库存
                    [$rebateId, $rebatePrice] = $this->getRebateAgreementInfo($value->product_id);
                    if ($rebateId) {
                        //获取阶梯价
                        $home_pick_up_price = ProQuoteDetail::query()
                                ->where([
                                    ['min_quantity', '<=', $currentQuantity],
                                    ['max_quantity', '>=', $currentQuantity],
                                    ['product_id', '=', $value->product_id],
                                ])
                                ->value('home_pick_up_price') ?? 0;
                        if ($home_pick_up_price &&
                            ($home_pick_up_price < $rebatePrice || $value->price < $rebatePrice)) {
                            $solution[] = [
                                'seller_id' => $value->customer_id,
                                'product_id' => $value->product_id,
                                'quantity' => $currentQuantity,
                                'transaction_type' => ProductTransactionType::NORMAL,
                                'agreement_id' => 0,
                            ];
                        } else {
                            $solution[] = [
                                'seller_id' => $value->customer_id,
                                'product_id' => $value->product_id,
                                'quantity' => $currentQuantity,
                                'transaction_type' => ProductTransactionType::REBATE,
                                'agreement_id' => $rebateId,
                            ];
                        }
                    } else {
                        $solution[] = [
                            'seller_id' => $value->customer_id,
                            'product_id' => $value->product_id,
                            'quantity' => $currentQuantity,
                            'transaction_type' => ProductTransactionType::NORMAL,
                            'agreement_id' => 0,
                        ];
                    }
                    $currentQuantity = 0;
                    break;
                }

            } else {
                // 数量不够 取当前数量最大的库存
                if ($value->quantity > $value->pq_quantity && $value->quantity > $value->margin_qty) {
                    // 按照库存数量够的议价>现货保证金>价格最低（返点，normal，精细化价格以及阶梯价）的优先级，匹配购买的库存
                    // 根据价格判断取哪个
                    [$rebateId, $rebatePrice] = $this->getRebateAgreementInfo($value->product_id);
                    if ($rebateId) {
                        //获取阶梯价
                        $home_pick_up_price = ProQuoteDetail::query()
                                ->where([
                                    ['min_quantity', '<=', $value->quantity],
                                    ['max_quantity', '>=', $value->quantity],
                                    ['product_id', '=', $value->product_id],
                                ])
                                ->value('home_pick_up_price') ?? 0;
                        if ($home_pick_up_price &&
                            ($home_pick_up_price < $rebatePrice || $value->price < $rebatePrice)) {
                            $solution[] = [
                                'seller_id' => $value->customer_id,
                                'product_id' => $value->product_id,
                                'quantity' => $value->quantity,
                                'transaction_type' => ProductTransactionType::NORMAL,
                                'agreement_id' => 0,
                            ];
                        } else {
                            $solution[] = [
                                'seller_id' => $value->customer_id,
                                'product_id' => $value->product_id,
                                'quantity' => $value->quantity,
                                'transaction_type' => ProductTransactionType::REBATE,
                                'agreement_id' => $rebateId,
                            ];
                        }

                    } else {
                        $solution[] = [
                            'seller_id' => $value->customer_id,
                            'product_id' => $value->product_id,
                            'quantity' => $value->quantity,
                            'transaction_type' => ProductTransactionType::NORMAL,
                            'agreement_id' => 0,
                        ];
                    }
                    $currentQuantity -= $value->quantity;
                    continue;
                }

                if ($value->pq_quantity >= $value->quantity && $value->pq_quantity > $value->margin_qty) {
                    $solution[] = [
                        'seller_id' => $value->customer_id,
                        'product_id' => $value->product_id,
                        'quantity' => $value->pq_quantity,
                        'transaction_type' => ProductTransactionType::SPOT,
                        'agreement_id' => $this->getSpotAgreementId($value->product_id, (int)$value->pq_quantity),
                    ];
                    $currentQuantity -= $value->pq_quantity;
                    continue;
                }

                if ($value->margin_qty > $value->pq_quantity && $value->margin_qty >= $value->quantity) {
                    $solution[] = [
                        'seller_id' => $value->customer_id,
                        'product_id' => $value->product_id,
                        'quantity' => $value->margin_qty,
                        'transaction_type' => ProductTransactionType::MARGIN,
                        'agreement_id' => $this->getMarginAgreementId($value->product_id, (int)$value->margin_qty),
                    ];
                    $currentQuantity -= $value->margin_qty;
                    continue;
                }
            }
        }

        return [$currentQuantity, $solution];
    }

    /**
     * 获取当前匹配议价的协议id
     * @param int $productId
     * @param int $quantity
     * @return mixed
     */
    private function getSpotAgreementId(int $productId, int $quantity)
    {
        return ProductQuote::query()
            ->where([
                'product_id' => $productId,
                'status' => 1,
                'customer_id' => customer()->getId(),
                'quantity' => $quantity,
            ])
            ->value('id');
    }

    /**
     * 获取当前满足条件的现货保证金协议id
     * @param int $productId
     * @param int $quantity
     * @param string $operation
     * @return mixed|null
     */
    private function getMarginAgreementId(int $productId, int $quantity, $operation = '=')
    {
        return ProductLock::query()->alias('l')
            ->leftJoin('oc_futures_margin_delivery as md', 'md.margin_agreement_id', 'l.agreement_id')
            ->leftJoin('oc_futures_margin_agreement as ma', 'ma.id', 'md.margin_agreement_id')
            ->leftJoin('tb_sys_margin_agreement as a', 'l.agreement_id', '=', 'a.id')
            ->where([
                'a.product_id' => $productId,
                'a.buyer_id' => customer()->getId(),
                'l.type_id' => ProductTransactionType::MARGIN,
            ])
            ->whereRaw("round(l.qty/l.set_qty) $operation {$quantity}")
            ->selectRaw('ifnull(ma.unit_price,a.price) as price,l.agreement_id')
            ->orderByRaw('price asc')
            ->value('agreement_id');
    }

    /**
     * 获取返点协议id 无数量要求
     * @param int $productId
     * @return array
     */
    private function getRebateAgreementInfo(int $productId): ?array
    {
        $info = RebateAgreementItem::query()->alias('i')
            ->leftJoinRelations(['rebateAgreement as t'])
            ->where(
                [
                    't.buyer_id' => customer()->getId(),
                    't.status' => 3,
                    'i.product_id' => $productId,
                ]
            )
            ->whereIn('t.rebate_result', [
                RebateAgreementResultEnum::__DEFAULT,
                RebateAgreementResultEnum::ACTIVE,
                RebateAgreementResultEnum::DUE_SOON,
            ])
            ->orderByRaw('i.template_price - i.rebate_amount', 'asc')
            ->select('i.agreement_id')
            ->selectRaw('(i.template_price - i.rebate_amount) as price')
            ->first();

        return [$info->agreement_id ?? 0, $info->price ?? 0];
    }


}
