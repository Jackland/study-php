<?php

namespace App\Repositories\Product\Channel\Module;

use App\Enums\Product\Channel\ProductChannelDataType;
use App\Enums\Warehouse\ReceiptOrderStatus;
use App\Helper\CountryHelper;
use App\Models\Product\Channel\ChannelParamConfig;
use App\Models\Product\Product;
use Psr\SimpleCache\InvalidArgumentException;

class ComingSoon extends BaseInfo
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
        $this->productIds = $this->getFirstArrivalsProductIds();
        return [
            'type' => ProductChannelDataType::PRODUCT,
            'data' => $this->productIds,
            'productIds' => $this->productIds,
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getFirstArrivalsProductIds(): array
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
            ->selectRaw("(ifnull(pwc.custom_weight,0)*$searchSortValue+(1-if(datediff(ro.expected_date,now()) <= 30,datediff(ro.expected_date,now()),30)/30)*$comingSoonParam) as sort")
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('tb_sys_receipts_order_detail as rod', 'rod.product_id', '=', 'p.product_id')
            ->leftJoin('tb_sys_receipts_order as ro', 'ro.receive_order_id', '=', 'rod.receive_order_id')
            ->leftJoin('tb_sys_product_sales as sps', 'sps.product_id', '=', 'p.product_id')
            ->leftJoin('tb_product_weight_config AS pwc', 'pwc.product_id', '=', 'p.product_id')
            ->when(!empty($dmProductIds), function ($q) use ($dmProductIds) {
                $q->whereNotIn('p.product_id', $dmProductIds);
            })
            ->whereIn('c.customer_id', $this->channelRepository->getAvailableSellerId())
            ->where([
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'p.product_type' => 0,
                'p.part_flag' => 0,
                'c.status' => 1,
                'c.country_id' => $this->countryId,
                'p.quantity' => 0,
                'ro.status' => ReceiptOrderStatus::TO_BE_RECEIVED,
            ])
            ->where(function ($q) {
                $q->where('sps.quantity_all', 0);
                $q->orWhere('sps.quantity_all', '=', null);
            })
            ->whereNotNull('ro.expected_date')
            ->whereNotNull('rod.expected_qty')
            ->whereRaw('ro.expected_date  < DATE_ADD(NOW(), INTERVAL 30 DAY)')
            ->whereRaw('ro.expected_date  > NOW()')
            ->groupBy(['c.customer_id', 'p.product_id'])
            ->orderByRaw('rand()')
            ->orderByRaw("ifnull(pwc.custom_weight,0)*$searchSortValue+(1-if(datediff(ro.expected_date,now()) <= 30,datediff(ro.expected_date,now()),30)/30)*$comingSoonParam desc");
        // 每个seller 1个 取5个seller计5个产品
        $res = [];
        foreach ($query->cursor() as $item) {
            $customerId = $item->customer_id;
            $productId = $item->product_id;
            if (!array_key_exists($customerId, $res)) {
                $res[$customerId] = $productId;
                if (count($res) >= $this->getShowNum()) {
                    break;
                }
            }
        }
        $productIds = array_values($res);
        // 不足5个产品 放宽条件
        if (count($productIds) < $this->getShowNum()) {
            $productIds = $query->limit($this->getShowNum())->pluck('p.product_id')->toArray();
        }

        return $productIds;
    }
}
