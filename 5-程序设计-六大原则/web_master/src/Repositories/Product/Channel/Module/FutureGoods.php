<?php

namespace App\Repositories\Product\Channel\Module;

use App\Enums\Product\Channel\ProductChannelDataType;
use App\Enums\Warehouse\ReceiptOrderStatus;
use App\Helper\CountryHelper;
use App\Models\Product\Channel\ChannelParamConfig;
use App\Models\Product\Product;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Psr\SimpleCache\InvalidArgumentException;

class FutureGoods extends BaseInfo
{

    private $buyerId;
    private $countryId;

    public function __construct()
    {
        parent::__construct();
        $this->buyerId = (int)customer()->getId();
        $this->countryId = (int)CountryHelper::getCountryByCode(session('country'));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getData(array $param): array
    {
        // 近3天内（含今天）新到货的产品，多于10个展示前10个（2行）
        $this->productIds = $this->getFutureGoodsProductIds();
        return [
            'type' => ProductChannelDataType::PRODUCT,
            'data' => $this->productIds,
            'productIds' => $this->productIds,
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getFutureGoodsProductIds(): array
    {
        $dmProductIds = $this->channelRepository->delicacyManagementProductId($this->buyerId);
        $comingSoonChannelParam = ChannelParamConfig::query()
            ->with(['channelParamConfigValue'])
            ->where(['name' => 'Coming Soon'])
            ->first();
        $channelParams = $comingSoonChannelParam->channelParamConfigValue->pluck('param_value', 'param_name')->toArray();
        ['搜索排序值' => $searchSortValue, '即将到货参数' => $comingSoonParam] = $channelParams;

        $query = Product::query()->alias('p')
            ->select(['c.customer_id', 'p.product_id'])
            ->selectRaw('md5(group_concat(ifnull(pa.associate_product_id,p.product_id) order by pa.associate_product_id asc)) as pmd5')
            ->selectRaw("(ifnull(pwc.custom_weight,0)*$searchSortValue+(1-if(datediff(ro.expected_date,now()) <= 30,datediff(ro.expected_date,now()),0)/30)*$comingSoonParam) as sort")
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_associate as pa', 'pa.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('tb_sys_receipts_order_detail as rod', 'rod.product_id', '=', 'p.product_id')
            ->leftJoin('tb_sys_receipts_order as ro', 'ro.receive_order_id', '=', 'rod.receive_order_id')
            ->leftJoin('tb_product_weight_config AS pwc', 'pwc.product_id', '=', 'p.product_id')
            ->when(!empty($dmProductIds), function ($q) use ($dmProductIds) {
                $q->whereNotIn('p.product_id', $dmProductIds);
            })
            ->where([
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'p.product_type' => 0,
                'p.part_flag' => 0,
                'c.status' => 1,
                'c.country_id' => $this->countryId,
                'ro.status' => ReceiptOrderStatus::TO_BE_RECEIVED,
                'p.quantity' => 0,
            ])
            ->whereIn('c.customer_id', $this->channelRepository->getAvailableSellerId())
            ->whereExists(function ($q) {
                $q->select('*')
                    ->from('oc_futures_contract as fc')
                    ->whereRaw('fc.product_id = p.product_id')
                    ->where([
                        'p.status' => 1,
                        'p.is_deleted' => 0,
                        'p.buyer_flag' => 1,
                        'fc.is_deleted' => 0,
                        'fc.status' => 1,
                    ]);
            })
            ->whereNotNull('ro.expected_date')
            ->whereNotNull('rod.expected_qty')
            ->whereRaw('ro.expected_date  > NOW()')
            ->groupBy(['c.customer_id', 'p.product_id'])
            ->orderByRaw("ifnull(pwc.custom_weight,0)*$searchSortValue+(1-if(datediff(ro.expected_date,now()) <= 30,datediff(ro.expected_date,now()),0)/30)*$comingSoonParam desc");

        // 排序 去重 实现的去重方案
        $query = db(new Expression('(' . get_complete_sql($query) . ') as t'))->groupBy(['t.pmd5']);
        // 每个seller 2个 取5个seller计10个产品
        // 注意:这里的customer_id一定要先排序，否则答案将会不准
        $rQuery = (clone $query)->orderBy('t.customer_id')->orderByDesc('t.sort');
        $tempQuery = db(new Expression('(' . get_complete_sql($rQuery) . ') as t'))
            ->select('*')
            ->selectRaw('@ns_count := IF(@ns_customer_id= customer_id, @ns_count + 1, 1) as rank')
            ->selectRaw('@ns_customer_id := customer_id');
        $res = db(new Expression('(' . get_complete_sql($tempQuery) . ') as t'))
            ->where('t.rank', '<=', 2)
            ->orderByRaw('rand()')
            ->orderByDesc('t.sort')
            ->orderByDesc('t.product_id')
            ->get();
        // 获取满足条件的产品id
        // 每个seller 2个 取5个seller计10个产品
        $resolveRes = $res->groupBy('customer_id');
        $productIds = [];
        $index = 0;
        /** @var Collection $itemCo */
        foreach ($resolveRes as $itemCo) {
            if ($itemCo->count() == 2) {
                $productIds = array_merge($productIds, $itemCo->pluck('product_id')->toArray());
                $index++;
                if ($index == 5) { // index=5 时，表示已经满足5个seller的条件 直接跳过循环
                    break;
                }
            }
        }
        // 不足10个产品 放宽条件
        if (count($productIds) < 10) {
            $productIds = $query
                ->orderByRaw('rand()')
                ->orderByDesc('t.sort')
                ->orderByDesc('t.product_id')
                ->limit(10)
                ->pluck('t.product_id')
                ->toArray();
        } else {
            // 重新排列product ids
            $tempProductIds = [];
            foreach ($res as $item) {
                $productId = $item->product_id;
                if (in_array($productId, $productIds)) {
                    $tempProductIds[] = $productId;
                }
                if (count($tempProductIds) >= 10) {
                    break;
                }
            }
            $productIds = $tempProductIds;
        }

        return $productIds;
    }
}
