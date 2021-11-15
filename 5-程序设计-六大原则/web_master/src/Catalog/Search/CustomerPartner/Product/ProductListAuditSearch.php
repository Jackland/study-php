<?php

namespace App\Catalog\Search\CustomerPartner\Product;

use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductAuditStatus;
use App\Enums\Product\ProductAuditType;
use App\Enums\Product\ProductType;
use App\Models\Product\ProductAudit;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\Model\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;

class ProductListAuditSearch
{
    use SearchModelTrait;

    private $customerId;
    private $sellerId;
    private $searchAttributes = [
        'filter_sku_mpn' => '',
        'filter_product_status' => '',//Product Status
        'filter_audit_status' => '',//Audit Status
        'filter_hot_map_valid' => '',
        'config_language_id' => '1',
        'sort' => 'pa.id',//ASC
        'order' => 'DESC',//ASC
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
        return ProductAudit::query()->alias('pa')
            ->select(['pa.is_original_design','pa.id', 'pa.id AS audit_id', 'pa.product_id', 'pa.status AS audit_status', 'pa.remark', 'pa.audit_type', 'pa.create_time'])
            ->addSelect(new Expression("CASE WHEN pa.status IN (2,3) THEN pa.update_time ELSE '' END AS approved_time"))
            ->addSelect(['pa.price AS audit_price', 'pa.price_effect_time'])
            ->addSelect(new Expression("pa.information->>'$.title' AS `name`"))
            ->addSelect(new Expression("pa.information->>'$.image' AS image"))
            ->addSelect(new Expression("pa.information->>'$.current_price' AS `current_price`"))
            ->addSelect(new Expression("pa.information->>'$.sold_separately' AS `buyer_flag`"))
            ->addSelect(['p.sku', 'p.mpn', 'p.combo_flag', 'p.status AS product_status'])
            ->addSelect(['sp.status AS sp_status', 'sp.new_price AS sp_new_price', 'sp.effect_time AS sp_effect_time'])
            ->leftJoin('oc_product AS p', 'p.product_id', '=', 'pa.product_id')
            ->leftJoin('oc_product_exts as exts','exts.product_id','=','p.product_id')
            ->leftJoin('oc_seller_price AS sp', function (JoinClause $j) {
                $j->on('sp.product_id', '=', 'p.product_id')
                    ->whereRaw('sp.new_price IS NOT NULL')
                    ->whereRaw('sp.effect_time IS NOT NULL')
                    ->where('sp.status', '>', 0)
                    ->where('pa.audit_type', '=', ProductAuditType::PRODUCT_PRICE);
            })
            ->leftJoin('oc_customerpartner_product_group_link AS cpgl', function (JoinClause $j) {
                $j->on('cpgl.product_id', '=', 'pa.product_id')
                    ->where('cpgl.seller_id', '=', $this->sellerId)
                    ->where('cpgl.status', '=', 1);
            })
            ->leftJoin('oc_customerpartner_product_group AS cpg', 'cpg.id', '=', 'cpgl.product_group_id')
            ->where([
                ['pa.customer_id', '=', $this->sellerId],
                ['pa.is_delete', '=', YesNoEnum::NO],
            ])
            ->whereIn('p.product_type', [ProductType::NORMAL,ProductType::COMPENSATION_FREIGHT])
            ->when($this->searchAttributes['filter_sku_mpn'] != '', function (Builder $q) {
                $q->where(function (Builder $q) {
                    $q->where('p.sku', 'like', "%" . "{$this->searchAttributes['filter_sku_mpn']}" . "%")
                        ->orWhere('p.mpn', 'like', "%" . "{$this->searchAttributes['filter_sku_mpn']}" . "%");
                });
            })
            ->when($this->searchAttributes['filter_product_status'] != '', function (Builder $q) {
                $q->where('p.status', '=', (int)$this->searchAttributes['filter_product_status']);
            })
            ->when($this->searchAttributes['filter_audit_status'] != '', function (Builder $q) {
                $q->where('pa.status', '=', (int)$this->searchAttributes['filter_audit_status']);
            })
            ->when($this->searchAttributes['sort'] != '' && $this->searchAttributes['order'] != '', function(Builder $q){
                $q->orderBy($this->searchAttributes['sort'], $this->searchAttributes['order']);
            })
            ->groupBy('pa.id');
    }

    /**
     * 审核中的数量
     * @return int
     */
    public function getCountAuditProcess()
    {
        $count = ProductAudit::query()->alias('pa')
            ->leftJoin('oc_product AS p', 'p.product_id', '=', 'pa.product_id')
            ->select(['pa.id'])
            ->where([
                ['pa.customer_id', '=', $this->sellerId],
                ['pa.status', '=', ProductAuditStatus::PENDING],
                ['pa.is_delete', '=', YesNoEnum::NO],
            ])
            ->whereIn('p.product_type', [ProductType::NORMAL,ProductType::COMPENSATION_FREIGHT])
            ->count();
        return $count;
    }

    /**
     * 审核通过的数量
     * @return int
     */
    public function getCountAuditApproved()
    {
        $count = ProductAudit::query()->alias('pa')
            ->leftJoin('oc_product AS p', 'p.product_id', '=', 'pa.product_id')
            ->select(['pa.id'])
            ->where([
                ['pa.customer_id', '=', $this->sellerId],
                ['pa.status', '=', ProductAuditStatus::APPROVED],
                ['pa.is_delete', '=', YesNoEnum::NO],
            ])
            ->whereIn('p.product_type', [ProductType::NORMAL,ProductType::COMPENSATION_FREIGHT])
            ->count();
        return $count;
    }

    /**
     * 审核不通过的数量
     * @return int
     */
    public function getCountAuditNotApproved()
    {
        $count = ProductAudit::query()->alias('pa')
            ->leftJoin('oc_product AS p', 'p.product_id', '=', 'pa.product_id')
            ->select(['pa.id'])
            ->where([
                ['pa.customer_id', '=', $this->sellerId],
                ['pa.status', '=', ProductAuditStatus::NOT_APPROVED],
                ['pa.is_delete', '=', YesNoEnum::NO],
            ])
            ->whereIn('p.product_type', [ProductType::NORMAL,ProductType::COMPENSATION_FREIGHT])
            ->count();
        return $count;
    }

    /**
     * 审核取消的数量
     * @return int
     */
    public function getCountAuditCancel()
    {
        $count = ProductAudit::query()->alias('pa')
            ->leftJoin('oc_product AS p', 'p.product_id', '=', 'pa.product_id')
            ->select(['pa.id'])
            ->where([
                ['pa.customer_id', '=', $this->sellerId],
                ['pa.status', '=', ProductAuditStatus::CANCEL],
                ['pa.is_delete', '=', YesNoEnum::NO],
            ])
            ->whereIn('p.product_type', [ProductType::NORMAL])
            ->count();
        return $count;
    }
}
