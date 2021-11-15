<?php

namespace App\Catalog\Search\CustomerPartner\Product;

use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductAuditStatus;
use App\Enums\Product\ProductStatus;
use App\Enums\Product\ProductType;
use App\Models\Product\Product;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\Model\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;

/**
 * Class ProductListValidSearch
 * @package App\Catalog\Search\CustomerPartner\Product
 * @property ModelCatalogProduct $model_catalog_product
 */
class ProductListValidSearch
{
    use SearchModelTrait;

    private $customerId;
    private $sellerId;
    private $currency = 'USD';
    private $searchAttributes = [
        'filter_combo' => '',
        'filter_buyer_flag' => '',
        'filter_name' => '',
        'filter_sku_mpn' => '',
        'filter_product_group_id' => '',
        'filter_status' => '',
        'filter_danger_flag' => '-1',
        'config_language_id' => '1',
        'filter_is_deleted' => '',
        'filter_hot_map_valid' => '',
        'filter_original_design' => '-1',
        'sort' => 'p.product_id',//p.product_id
        'order' => 'DESC',//DESC
    ];
    public $defaultPageSize = 10;

    public function __construct($customerId)
    {
        $this->customerId = $customerId;
        $this->sellerId = $customerId;
    }

    public function search($params, $isDownload = false)
    {
        $this->loadAttributes($params);
        $query = $this->buildQuery();
        $dataProvider = new QueryDataProvider($query);
        $dataProvider->setPaginator(['defaultPageSize' => $this->defaultPageSize]);
        if ($isDownload) {
            $dataProvider->switchPaginator(false);
        }

        return $dataProvider;
    }


    protected function buildQuery()
    {
        return Product::query()->alias('p')
            ->select(['p.product_id', 'p.sku', 'p.mpn', 'p.image', 'p.price', 'p.weight', 'p.length', 'p.width', 'p.height', 'p.status', 'p.price_display', 'p.combo_flag', 'p.buyer_flag', 'p.part_flag', 'p.freight', 'p.product_type', 'p.peak_season_surcharge', 'p.danger_flag', 'p.danger_fee', 'exts.is_original_design'])
            ->addSelect(new Expression("IFNULL(m.`name`, '') AS brand"))
            ->addSelect('pd.name')
            ->addSelect(['pfd.fee AS package_fee_d', 'pfh.fee AS package_fee_h'])
            ->addSelect(new Expression("IFNULL(GROUP_CONCAT(cpg.`name` ORDER BY cpgl.product_group_id ASC), '') AS product_group_name"))
            ->addSelect('sp.new_price', 'sp.effect_time')
            ->addSelect('pa_price.price AS audit_price', 'pa_price.price_effect_time AS audit_price_effect_time')
            ->selectRaw(
                <<<SQL
case
when p.status in (-1,0) and (pa_price.id or pa_product.id ) then "Under review"
when p.status in (1) and  (pa_price.id or pa_product.id ) then "In Progress"
else ''
end as audit_progress
SQL
            )
            ->leftJoinRelations(['manufacturer as m', 'description as pd', 'customerPartnerToProduct as c2p'])
            ->leftJoin('oc_product_to_store AS p2s', 'p2s.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_fee AS pfd', function (JoinClause $j) {
                $j->on('pfd.product_id', '=', 'p.product_id')
                    ->where('pfd.type', '=', 1);
            })
            ->leftJoin('oc_product_fee AS pfh', function (JoinClause $j) {
                $j->on('pfh.product_id', '=', 'p.product_id')
                    ->where('pfh.type', '=', 2);
            })
            ->leftJoin('oc_customerpartner_product_group_link AS cpgl', function (JoinClause $j) {
                $j->on('cpgl.product_id', '=', 'p.product_id')
                    ->where('cpgl.seller_id', '=', $this->sellerId)
                    ->where('cpgl.status', '=', 1);
            })
            ->leftJoin('oc_customerpartner_product_group AS cpg', 'cpg.id', '=', 'cpgl.product_group_id')
            ->leftJoin('oc_seller_price AS sp', function (JoinClause $j) {
                $j->on('sp.product_id', '=', 'p.product_id')
                    ->where('sp.status', '=', 1);
            })
            ->leftJoin('oc_product_audit AS pa_price', function (JoinClause $j) {
                $j->on('pa_price.id', '=', 'p.price_audit_id')
                    ->where('pa_price.status', '=', ProductAuditStatus::PENDING)
                    ->where('pa_Price.is_delete', YesNoEnum::NO);
            })
            ->leftJoin('oc_product_audit AS pa_product', function (JoinClause $j) {
                $j->on('pa_product.id', '=', 'p.product_audit_id')
                    ->where('pa_product.status', '=', ProductAuditStatus::PENDING)
                    ->where('pa_product.is_delete', YesNoEnum::NO);
            })
            ->where('pd.language_id', '=', (int)$this->searchAttributes['config_language_id'])
            ->whereRaw('p.date_available <= NOW()')
            ->leftJoin('oc_product_exts as exts','exts.product_id','=','p.product_id')
            ->when(isset($this->searchAttributes['filter_original_design']) && in_array($this->searchAttributes['filter_original_design'],[YesNoEnum::YES,YesNoEnum::NO]),function (Builder $q){
                if($this->searchAttributes['filter_original_design'] == 0){
                    $q->whereRaw('(exts.is_original_design = 0 or exts.is_original_design is null)');
                }else{
                    $q->where('exts.is_original_design',(int)$this->searchAttributes['filter_original_design']);
                }
                return $q;
            })
            ->when(isset($this->searchAttributes['filter_danger_flag']) && in_array($this->searchAttributes['filter_danger_flag'],[YesNoEnum::YES,YesNoEnum::NO]),function (Builder $q){
                $q->where('p.danger_flag', (int)$this->searchAttributes['filter_danger_flag']);
            })
            ->when($this->searchAttributes['filter_combo'] != '', function (Builder $q) {
                $q->where('p.combo_flag', (int)$this->searchAttributes['filter_combo']);
            })
            ->when($this->searchAttributes['filter_buyer_flag'] != '', function (Builder $q) {
                $q->where('p.buyer_flag', (int)$this->searchAttributes['filter_buyer_flag']);
            })
            ->when($this->searchAttributes['filter_name'] != '', function (Builder $q) {
                $q->where('pd.name', 'like', "%{$this->searchAttributes['filter_name']}%");
            })
            ->when($this->searchAttributes['filter_sku_mpn'] != '', function (Builder $q) {
                $q->where(function (Builder $q) {
                    $q->where('p.sku', 'like', "%{$this->searchAttributes['filter_sku_mpn']}%")
                        ->orWhere('p.mpn', 'like', "%{$this->searchAttributes['filter_sku_mpn']}%");
                });
            })
            ->when($this->searchAttributes['filter_product_group_id'] != '', function (Builder $q) {
                $q->where('cpg.id', '=', (int)$this->searchAttributes['filter_product_group_id']);
            })
            ->when($this->searchAttributes['filter_status'] !== '', function (Builder $q) { // 因为 filter_status 有可能为0, 所以要用 !==
                $q->where('p.status', '=', (int)$this->searchAttributes['filter_status']);
            })
            ->when($this->searchAttributes['filter_is_deleted'] != '', function (Builder $q) {
                $q->where('p.is_deleted', '=', $this->searchAttributes['filter_is_deleted']);
            })
            ->where('c2p.customer_id', '=', $this->sellerId)
            ->whereIn('p.product_type', [ProductType::NORMAL,ProductType::COMPENSATION_FREIGHT]) //#19834
            ->groupBy('p.product_id')
            ->when($this->searchAttributes['sort'] != '' && $this->searchAttributes['order'], function (Builder $q) {
                $q->orderBy($this->searchAttributes['sort'], $this->searchAttributes['order']);
            });
    }


    /**
     * 待上架数量
     * @return int
     */
    public function getCountWait()
    {
        $count = Product::query()->alias('p')
            ->leftJoinRelations(['description as pd', 'customerPartnerToProduct as c2p'])
            ->select(['p.product_id'])
            ->where('pd.language_id', '=', (int)$this->searchAttributes['config_language_id'])
            ->whereRaw('p.date_available <= NOW()')
            ->where([
                ['p.is_deleted', '=', YesNoEnum::NO],
                ['p.status', '=', ProductStatus::WAIT_SALE],
                ['c2p.customer_id', '=', $this->sellerId],
            ])
            ->whereIn('p.product_type', [ProductType::NORMAL,ProductType::COMPENSATION_FREIGHT])
            ->count();
        return $count;
    }

    /**
     * 已上架数量
     * @return int
     */
    public function getCountOn()
    {
        $count = Product::query()->alias('p')
            ->leftJoinRelations(['description as pd', 'customerPartnerToProduct as c2p'])
            ->select(['p.product_id'])
            ->where('pd.language_id', '=', (int)$this->searchAttributes['config_language_id'])
            ->whereRaw('p.date_available <= NOW()')
            ->where([
                ['p.is_deleted', '=', YesNoEnum::NO],
                ['p.status', '=', ProductStatus::ON_SALE],
                ['c2p.customer_id', '=', $this->sellerId],
            ])
            ->whereIn('p.product_type', [ProductType::NORMAL,ProductType::COMPENSATION_FREIGHT])
            ->count();
        return $count;
    }

    /**
     * 已下架数量
     * @return int
     */
    public function getCountOff()
    {
        $count = Product::query()->alias('p')
            ->leftJoinRelations(['description as pd', 'customerPartnerToProduct as c2p'])
            ->select(['p.product_id'])
            ->where('pd.language_id', '=', (int)$this->searchAttributes['config_language_id'])
            ->whereRaw('p.date_available <= NOW()')
            ->where([
                ['p.is_deleted', '=', YesNoEnum::NO],
                ['p.status', '=', ProductStatus::OFF_SALE],
                ['c2p.customer_id', '=', $this->sellerId],
            ])
            ->whereIn('p.product_type', [ProductType::NORMAL,ProductType::COMPENSATION_FREIGHT])
            ->count();
        return $count;
    }
}
