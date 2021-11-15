<?php

namespace App\Repositories\Stock;

use App\Enums\Common\YesNoEnum;
use App\Enums\FeeOrder\StorageFeeStatus;
use App\Enums\Future\FuturesMarginAgreementStatus;
use App\Enums\Future\FuturesMarginDeliveryStatus;
use App\Enums\Future\FuturesMarginDeliveryType;
use App\Enums\Margin\MarginAgreementStatus;
use App\Enums\Product\ProductLockType;
use App\Enums\Product\ProductTransactionType;
use App\Enums\SalesOrder\CustomerSalesOrderLineItemStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\Stock\BuyerProductLockEnum;
use App\Enums\YzcRmaOrder\RmaApplyType;
use App\Enums\YzcRmaOrder\RmaOrderProductStatusRefund;
use App\Enums\YzcRmaOrder\RmaType;
use App\Helper\CountryHelper;
use App\Models\Delivery\BuyerProductLock;
use App\Models\Delivery\CostDetail;
use App\Models\Link\OrderAssociated;
use App\Models\Margin\MarginAgreement;
use App\Models\Order\Order;
use App\Models\Order\OrderProduct;
use App\Models\Product\Product;
use App\Models\Product\ProductLock;
use App\Models\Product\ProductSetInfo;
use App\Models\Rma\YzcRmaOrder;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesReorder;
use App\Models\StorageFee\StorageFee;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Services\Stock\BuyerStockService;
use Carbon\Carbon;
use Illuminate\Database\Concerns\BuildsQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;
use ModelFuturesAgreement;

/**
 * 库存管理查询相关
 *
 * Class StockManagementRepository
 *
 * @package App\Repositories\Stock
 */
class StockManagementRepository
{

    /** @var int $customerId */
    public $customerId;

    public function __construct()
    {
        $this->customerId = (int)session('customer_id');
    }

    // region 可用库存

    /**
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return Builder
     */
    public function buildProductCostQuery($customerId = null, array $itemCode = null, array $productId = null)
    {
        $customerId = $customerId ?? $this->customerId;
        $subQuery = YzcRmaOrder::query()->alias('ro')
            ->select(['ro.order_id', 'rop.product_id'])
            ->selectRaw('sum(rop.quantity) AS qty')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'ro.id', '=', 'rop.rma_id')
            ->where([
                'ro.order_type' => 2,
                'ro.cancel_rma' => 0,
                'ro.buyer_id' => $customerId,
            ])
            ->where('rop.status_refund', '<>', 2)
            ->groupBy(['ro.order_id', 'rop.product_id']);
        $query = CostDetail::query()->alias('scd')
            ->select(['p.product_id', 'p.sku'])
            ->selectRaw('sum(ifnull(scd.original_qty,0)) as originalQty')
            ->selectRaw('sum(ifnull(t.qty,0)) as qty')
            ->selectRaw(<<<STR
     sum(
                    ifnull((
                        SELECT
                            sum(qty)
                        FROM
                            oc_buyer_product_lock
                        WHERE
                           is_processed = 0 and  cost_id = scd.id
                    ),0)
                ) AS lockQty
STR
            )
            ->selectRaw(<<<STR
     sum(
                    ifnull((
                        SELECT
                            sum(qty)
                        FROM
                            tb_sys_order_associated
                        WHERE
                            order_product_id = op.order_product_id
                            AND buyer_id=scd.buyer_id
                    ),0)
                ) AS associatedQty
STR
            )
            ->leftJoin('tb_sys_receive_line as srl', 'srl.id', '=', 'scd.source_line_id')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'scd.sku_id')
            ->leftJoin('oc_order_product as op', function (JoinClause $q) {
                $q->on('op.order_id', '=', 'srl.oc_order_id')
                    ->on('scd.sku_id', '=', 'op.product_id');
            })
            ->leftJoin(new Expression('(' . get_complete_sql($subQuery) . ') as t'), function (JoinClause $q) {
                $q->on('t.product_id', '=', 'scd.sku_id')
                    ->on('t.order_id', '=', 'srl.oc_order_id');
            })
            ->where(['scd.buyer_id' => $customerId,])
            ->whereIn('scd.type', [1])
            ->when(!empty($itemCode), function ($q) use ($itemCode) {
                $q->whereIn('p.sku', $itemCode);
            })
            ->when(!empty($productId), function ($q) use ($productId) {
                $q->whereIn('p.product_id', $productId);
            })
            ->where('scd.onhand_qty', '>', 0)
            ->groupBy(['p.product_id']);

        return db(new Expression('(' . get_complete_sql($query) . ') as t'))
            ->select('t.*')
            ->selectRaw('if((t.originalQty - t.qty - t.associatedQty - t.lockQty > 0),t.originalQty - t.qty - t.associatedQty- t.lockQty,0) as availableQty')
            ->whereRaw('(t.originalQty - t.qty - t.associatedQty - t.lockQty) > 0');
    }

    /**
     * 可用库存弹框页面
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return Builder
     */
    public function buildProductCostListQuery($customerId = null, array $itemCode = null, array $productId = null)
    {
        $customerId = $customerId ?? $this->customerId;
        $subQuery = YzcRmaOrder::query()->alias('ro')
            ->select(['ro.order_id', 'rop.product_id'])
            ->selectRaw('sum(rop.quantity) AS qty')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'ro.id', '=', 'rop.rma_id')
            ->where([
                'ro.order_type' => 2,
                'ro.cancel_rma' => 0,
                'ro.buyer_id' => $customerId,
            ])
            ->where('rop.status_refund', '<>', 2)
            ->groupBy(['ro.order_id', 'rop.product_id']);
        $query = CostDetail::query()->alias('scd')
            ->select(['p.product_id', 'p.sku', 'srl.oc_order_id as order_id', 'o.date_modified as create_time', 'op.type_id', 'op.agreement_id'])
            ->selectRaw('sum(ifnull(scd.original_qty,0)) as originalQty')
            ->selectRaw('sum(ifnull(t.qty,0)) as qty')
            ->selectRaw(<<<STR
     sum(
                    ifnull((
                        SELECT
                            sum(qty)
                        FROM
                            oc_buyer_product_lock
                        WHERE
                           is_processed = 0 and  cost_id = scd.id
                    ),0)
                ) AS lockQty
STR
            )
            ->selectRaw(<<<STR
     sum(
                    ifnull((
                        SELECT
                            sum(qty)
                        FROM
                            tb_sys_order_associated
                        WHERE
                            order_product_id = op.order_product_id
                            AND buyer_id=scd.buyer_id
                    ),0)
                ) AS associatedQty
STR
            )
            ->leftJoin('tb_sys_receive_line as srl', 'srl.id', '=', 'scd.source_line_id')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'scd.sku_id')
            ->leftJoin('oc_order_product as op', function (JoinClause $q) {
                $q->on('op.order_id', '=', 'srl.oc_order_id')
                    ->on('scd.sku_id', '=', 'op.product_id');
            })
            ->leftJoin('oc_order as o', 'op.order_id', '=', 'o.order_id')
            ->leftJoin(new Expression('(' . get_complete_sql($subQuery) . ') as t'), function (JoinClause $q) {
                $q->on('t.product_id', '=', 'scd.sku_id')
                    ->on('t.order_id', '=', 'srl.oc_order_id');
            })
            ->where(['scd.buyer_id' => $customerId,])
            ->whereIn('scd.type', [1])
            ->when(!empty($itemCode), function ($q) use ($itemCode) {
                $q->whereIn('p.sku', $itemCode);
            })
            ->when(!empty($productId), function ($q) use ($productId) {
                $q->whereIn('p.product_id', $productId);
            })
            ->where('scd.onhand_qty', '>', 0)
            ->groupBy(['srl.oc_order_id', 'srl.product_id']);

        return db(new Expression('(' . get_complete_sql($query) . ') as t'))
            ->select('t.*')
            ->selectRaw('if((t.originalQty - t.qty - t.associatedQty - t.lockQty> 0),t.originalQty - t.qty - t.associatedQty - t.lockQty,0) as availableQty')
            ->whereRaw('(t.originalQty - t.qty - t.associatedQty- t.lockQty) > 0')
            ->orderByDesc('t.product_id')
            ->orderBy('t.create_time')
            ->orderByDesc(new Expression('(t.originalQty - t.qty - t.associatedQty - t.lockQty)'));
    }

    /**
     * 获取在库天数
     *
     * @param string $orderDate 采购时间
     * @param int $countryId
     * @param int $typeId 交易类型
     * @param int $agreementId 协议ID
     * @return int 天数
     */
    public function getInventoryDays($orderDate, $countryId, $typeId = 0, $agreementId = 0): int
    {
        if ($typeId == ProductTransactionType::MARGIN) {
            // 现货要根据头款产品采购后生成仓租的时间
            $storageFee = StorageFee::query()->where('transaction_type_id', '=', $typeId)
                ->where('agreement_id', $agreementId)->first();
            if (!$storageFee) {
                return 0;
            }
            return $this->getDiffDays($storageFee->created_at->toDateTimeString(), $countryId);
        } else {
            return $this->getDiffDays($orderDate, $countryId);
        }
    }
    // endregion

    // region buyer 合约库存

    /**
     * 获取buyer合约库存
     * @param int $productId
     * @param null $customerId
     * @return int
     */
    public function getContractQty(int $productId, $customerId = null): int
    {
        $query = $this->buildContractQtyQuery($customerId, null, (array)$productId);
        $res = $query->first();
        return (int)($res ? Arr::get(get_object_vars($res), 'num') : 0);
    }

    /**
     * 构建合约query
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return Builder
     */
    public function buildContractQtyQuery($customerId = null, array $itemCode = null, array $productId = null)
    {
        $customerId = $customerId ?? $this->customerId;
        $query = $this->buildProductLockModel($customerId)->select(['pl.*', 'p.sku'])
            ->selectRaw('pl.qty/pl.set_qty as s_num')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'pl.parent_product_id')
            ->when(!empty($itemCode), function ($q) use ($itemCode) {
                $q->whereIn('p.sku', $itemCode);
            })
            ->when(!empty($productId), function ($q) use ($productId) {
                $q->whereIn('pl.parent_product_id', $productId);
            })
            ->groupBy(['agreement_id']);
        return db(new Expression('(' . get_complete_sql($query) . ') as t'))
            ->select(['t.*'])
            ->selectRaw('ifnull(sum(t.s_num),0) as num')
            ->groupBy(['t.parent_product_id']);
    }

    /**
     * 生成合约中库存product_lock 模型
     * 里面会限制合约类型为现货和期货
     * 并且对现货和期货的状态做了限制
     * 现货：Sold 期货:To be Paid
     * product_lock as PL
     * join 了三张表'tb_sys_margin_agreement AS ma，oc_futures_margin_agreement AS fma，oc_futures_margin_delivery as fd
     * @param int $customerId
     * @return ProductLock|\Framework\Model\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder|Builder
     */
    public function buildProductLockModel($customerId)
    {
        return ProductLock::query()->alias('pl')
            ->leftJoin('tb_sys_margin_agreement AS ma', function (JoinClause $left) {
                $left->on('pl.agreement_id', '=', 'ma.id')
                    ->where('pl.type_id', ProductLockType::MARGIN)
                    ->leftJoin('tb_sys_margin_performer_apply AS tsmpa', function (JoinClause $left) {
                        $left->on('tsmpa.agreement_id', '=', 'ma.id')
                            ->where('tsmpa.check_result', 1);
                    });
            })
            ->leftJoin('oc_futures_margin_agreement AS fma', function (JoinClause $left) {
                $left->on('pl.agreement_id', '=', 'fma.id')
                    ->where('pl.type_id', ProductLockType::FUTURES)
                    ->leftJoin('oc_futures_margin_delivery as fd', 'fma.id', 'fd.agreement_id');
            })
            ->whereIn('pl.type_id', ProductLockType::getStockManagementContractType())
            ->where(function ($query) {
                $query->where(function ($queryChild) {
                    $queryChild->where('pl.type_id', ProductLockType::MARGIN)
                        ->where('ma.status', MarginAgreementStatus::SOLD)//现货的“Sold”状态
                        ->where('ma.expire_time', '>', Carbon::now());
                })->orWhere(function ($queryChild) {
                    //期货的“To be Paid”
                    $queryChild->where('pl.type_id', ProductLockType::FUTURES)
                        ->where('fma.agreement_status', FuturesMarginAgreementStatus::SOLD)
                        ->whereIn('fd.delivery_type', FuturesMarginDeliveryType::getNotToMargin())
                        ->where('fd.delivery_status', FuturesMarginDeliveryStatus::TO_BE_PAID);
                });
            })->where(function ($query) use ($customerId) {
                $query->where(function ($queryChild) use ($customerId) {
                    $queryChild->where('pl.type_id', '=', ProductLockType::MARGIN)->where(function ($query) use ($customerId) {
                        // 作为主履约人或者共同履约人都算
                        $query->where('ma.buyer_id', '=', $customerId)
                            ->orWhere('tsmpa.performer_buyer_id', '=', $customerId);
                    });
                })->orWhere([['pl.type_id', '=', ProductLockType::FUTURES], ['fma.buyer_id', '=', $customerId]]);
            });
    }
    // endregion

    // region 已售未发锁定库存

    /**
     * 已售未发弹框列表
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return Builder
     */
    public function buildSoldOutCountListQuery($customerId = null, array $itemCode = null, array $productId = null)
    {
        // 销售订单
        $mainQuery = $this->buildSalesOrderUnshippedQuery(...func_get_args());
        // 重发单
        $subQuery = $this->buildReOrderUnshippedQuery(...func_get_args());
        $query = $mainQuery->union($subQuery);
        return db(new Expression('(' . get_complete_sql($query) . ') as t'))
            ->select([
                't.order_id as relate_id', 't.product_id', new Expression('sum(qty) as qty'),
                't.block_type', 't.reason_type', 't.associate_id'
            ])
            ->groupBy(['t.order_id']);
    }

    /**
     * 销售单已售未发数
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return CustomerSalesOrder|\Framework\Model\Eloquent\Builder|BuildsQueries|\Illuminate\Database\Eloquent\Builder|Builder|mixed
     */
    public function buildSalesOrderUnshippedQuery($customerId = null, array $itemCode = null, array $productId = null)
    {
        $customerId = $customerId ?? $this->customerId;
        // 销售订单
        return CustomerSalesOrder::query()
            ->alias('cso')
            ->select([
                'cso.id as header_id', 'cso.order_id', 'csol.id as line_id', 'cso.order_status',
                'csol.item_status', 'csol.item_code', 'soa.product_id', 'soa.id as associate_id'
            ])
            ->selectRaw('"5" as block_type')
            ->selectRaw("'5-1' as reason_type") // sales order
            ->selectRaw('SUM(soa.qty) AS qty')
            ->leftJoin('tb_sys_customer_sales_order_line as csol', 'cso.id', '=', 'csol.header_id')
            ->leftJoin('tb_sys_order_associated as soa', function (JoinClause $j) {
                $j->on('soa.sales_order_id', '=', 'cso.id')
                    ->on('soa.sales_order_line_id', '=', 'csol.id');
            })
            ->where(['cso.buyer_id' => $customerId,])
            ->whereIn(
                'cso.order_status',
                [
                    CustomerSalesOrderStatus::BEING_PROCESSED,
                    CustomerSalesOrderStatus::ON_HOLD,
                    CustomerSalesOrderStatus::WAITING_FOR_PICK_UP,
                ]
            )
            ->when(!empty($itemCode), function ($q) use ($itemCode) {
                $q->whereIn('csol.item_code', $itemCode);
            })
            ->when(!empty($productId), function ($q) use ($productId) {
                $q->whereIn('soa.product_id', $productId);
            })
            ->whereIn('csol.item_status', [CustomerSalesOrderLineItemStatus::PENDING, CustomerSalesOrderLineItemStatus::SHIPPING])
            ->groupBy(['soa.id']);
    }

    /**
     * 重发单已售未发数
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return CustomerSalesReorder|\Framework\Model\Eloquent\Builder|BuildsQueries|\Illuminate\Database\Eloquent\Builder|Builder|mixed
     */
    public function buildReOrderUnshippedQuery($customerId = null, array $itemCode = null, array $productId = null)
    {
        $customerId = $customerId ?? $this->customerId;
        return CustomerSalesReorder::query()
            ->alias('csr')
            ->select([
                'csr.id as header_id', 'csr.reorder_id as order_id', 'csrl.id as line_id', 'csr.order_status',
                'csrl.item_status', 'csrl.item_code', 'csrl.product_id', new Expression("'0' as associate_id")
            ])
            ->selectRaw('"5" as block_type')
            ->selectRaw("'5-2' as reason_type")  // reship order
            ->selectRaw('SUM(csrl.qty) AS qty')
            ->leftJoin('tb_sys_customer_sales_reorder_line as csrl', 'csr.id', '=', 'csrl.reorder_header_id')
            ->where([
                'csr.buyer_id' => $customerId,
                'csr.order_status' => 2,
            ])
            ->when(!empty($itemCode), function ($q) use ($itemCode) {
                $q->whereIn('csrl.item_code', $itemCode);
            })
            ->when(!empty($productId), function ($q) use ($productId) {
                $q->whereIn('csrl.product_id', $productId);
            })
            ->whereIn('csrl.item_status', [1, 2])
            ->groupBy(['csrl.id']);
    }

    // endregion

    // region 手动锁定的buyer库存数
    /**
     * buyer锁定库存列表query
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return Builder
     */
    public function buildBuyerProductLockListQuery($customerId = null, array $itemCode = null, array $productId = null)
    {
        $query = $this->buildBuyerProductLock(...func_get_args());
        return db(new Expression('(' . get_complete_sql($query) . ') as t'))
            ->select([
                't.id as relate_id', 't.product_id', 't.t_qty as qty', 't.block_type',
                't.reason_type', 't.cost_id as associate_id'
            ]);
    }

    /**
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return BuyerProductLock|\Framework\Model\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder|Builder
     */
    public function buildBuyerProductLock($customerId = null, array $itemCode = null, array $productId = null)
    {
        $customerId = $customerId ?? $this->customerId;
        $query1 = BuyerProductLock::query()->alias('bpl')
            ->select(['bpl.*'])
            ->selectRaw('sum(bpl.qty) as t_qty')
            ->selectRaw('"6" as block_type')
            ->selectRaw("'6-1' as reason_type")
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'bpl.product_id')
            ->where('bpl.buyer_id', $customerId)
            ->where('bpl.is_processed', 0)
            ->where('bpl.type', BuyerProductLockEnum::INVENTORY_REDUCTION)
            ->groupBy('bpl.foreign_key')
            ->when(!empty($itemCode), function ($q) use ($itemCode) {
                $q->whereIn('p.sku', $itemCode);
            })
            ->when(!empty($productId), function ($q) use ($productId) {
                $q->whereIn('bpl.product_id', $productId);
            });
        $query2 = BuyerProductLock::query()->alias('bpl')
            ->select(['bpl.*'])
            ->selectRaw('sum(bpl.qty) as t_qty')
            ->selectRaw('"6" as block_type')
            ->selectRaw("'6-2' as reason_type")
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'bpl.product_id')
            ->where('bpl.buyer_id', $customerId)
            ->where('bpl.is_processed', 0)
            ->where('bpl.type', BuyerProductLockEnum::INVENTORY_LOSS)
            ->groupBy('bpl.foreign_key')
            ->when(!empty($itemCode), function ($q) use ($itemCode) {
                $q->whereIn('p.sku', $itemCode);
            })
            ->when(!empty($productId), function ($q) use ($productId) {
                $q->whereIn('bpl.product_id', $productId);
            });
        $query3 = BuyerProductLock::query()->alias('bpl')
            ->select(['bpl.*'])
            ->selectRaw('sum(bpl.qty) as t_qty')
            ->selectRaw('"7" as block_type')
            ->selectRaw("'7-1' as reason_type")
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'bpl.product_id')
            ->where('bpl.buyer_id', $customerId)
            ->where('bpl.is_processed', 0)
            ->where('bpl.type', BuyerProductLockEnum::INVENTORY_PRE_ASSOCIATED)
            ->groupBy('bpl.foreign_key')
            ->when(!empty($itemCode), function ($q) use ($itemCode) {
                $q->whereIn('p.sku', $itemCode);
            })
            ->when(!empty($productId), function ($q) use ($productId) {
                $q->whereIn('bpl.product_id', $productId);
            });

        return $query1->union($query2)->union($query3);
    }


    // endregion

    // region 待支付锁定库存

    /**
     * 获取费用待支付列表query
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return Builder
     */
    public function buildWaitForPayListQuery($customerId = null, array $itemCode = null, array $productId = null)
    {
        $query = $this->buildWaitForPay(...func_get_args());
        return db(new Expression('(' . get_complete_sql($query) . ') as t'))
            ->select([
                't.order_id as relate_id', 't.product_id', 't.qty', 't.block_type',
                't.reason_type', 't.associate_id'
            ]);
    }

    /**
     * 待支付仓租：只会出现在上门取货账号，指绑定了销售订单但是仓租待支付的状态，在累计仓租
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return CustomerSalesOrder|\Framework\Model\Eloquent\Builder|BuildsQueries|\Illuminate\Database\Eloquent\Builder|Builder|mixed
     */
    public function buildWaitForPay($customerId = null, array $itemCode = null, array $productId = null)
    {
        $customerId = $customerId ?? $this->customerId;
        // 销售订单
        return OrderAssociated::query()
            ->alias('soa')
            ->select([
                'cso.id as header_id', 'cso.order_id', 'csol.id as line_id', 'cso.order_status',
                'csol.item_status', 'csol.item_code', 'soa.product_id', 'soa.id as associate_id'
            ])
            ->selectRaw('"3" as block_type')
            ->selectRaw("'3-1' as reason_type") // sales order
            ->selectRaw('SUM(soa.qty) AS qty')
            ->leftJoin('tb_sys_customer_sales_order as cso', 'soa.sales_order_id', '=', 'cso.id')
            ->leftJoin('tb_sys_customer_sales_order_line as csol', 'soa.sales_order_line_id', '=', 'csol.id')
            ->where([
                'cso.buyer_id' => $customerId,
                'cso.order_status' => CustomerSalesOrderStatus::PENDING_CHARGES,
            ])
            ->when(!empty($itemCode), function ($q) use ($itemCode) {
                $q->whereIn('csol.item_code', $itemCode);
            })
            ->when(!empty($productId), function ($q) use ($productId) {
                $q->whereIn('soa.product_id', $productId);
            })
            ->groupBy(['soa.id']);
    }
    // endregion

    // region asr锁定库存

    /**
     * asr锁定库存列表query
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return Builder
     */
    public function buildAsrListQuery($customerId = null, array $itemCode = null, array $productId = null)
    {
        $query = $this->buildAsr(...func_get_args());
        return db(new Expression('(' . get_complete_sql($query) . ') as t'))
            ->select([
                't.order_id as relate_id', 't.product_id', 't.qty', 't.block_type',
                't.reason_type', 't.associate_id'
            ]);
    }

    /**
     * 待支付仓租：只会出现在上门取货账号，指绑定了销售订单但是仓租待支付的状态，在累计仓租
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return CustomerSalesOrder|\Framework\Model\Eloquent\Builder|BuildsQueries|\Illuminate\Database\Eloquent\Builder|Builder|mixed
     */
    public function buildAsr($customerId = null, array $itemCode = null, array $productId = null)
    {
        $customerId = $customerId ?? $this->customerId;
        // 销售订单
        return OrderAssociated::query()
            ->alias('soa')
            ->select([
                'cso.id as header_id', 'cso.order_id', 'csol.id as line_id', 'cso.order_status',
                'csol.item_status', 'csol.item_code', 'soa.product_id', 'soa.id as associate_id'
            ])
            ->selectRaw('"4" as block_type')
            ->selectRaw("'4-1' as reason_type") // sales order
            ->selectRaw('SUM(soa.qty) AS qty')
            ->leftJoin('tb_sys_customer_sales_order as cso', 'soa.sales_order_id', '=', 'cso.id')
            ->leftJoin('tb_sys_customer_sales_order_line as csol', 'soa.sales_order_line_id', '=', 'csol.id')
            ->where([
                'cso.buyer_id' => $customerId,
                'cso.order_status' => CustomerSalesOrderStatus::ASR_TO_BE_PAID,
            ])
            ->when(!empty($itemCode), function ($q) use ($itemCode) {
                $q->whereIn('csol.item_code', $itemCode);
            })
            ->when(!empty($productId), function ($q) use ($productId) {
                $q->whereIn('soa.product_id', $productId);
            })
            ->groupBy(['soa.id']);
    }
    // endregion

    // region 计算预估仓租query

    /**
     * 仓租费用query
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return Builder
     */
    public function buildStorageFeeQuery($customerId = null, array $itemCode = null, array $productId = null)
    {
        $customerId = $customerId ?? $this->customerId;
        return StorageFee::query()
            ->alias('sf')
            ->select(['sf.product_sku as sku', 'sf.product_id'])
            ->selectRaw('if(sum(sf.fee_unpaid) > 0 ,sum(sf.fee_unpaid),0) as fee_total')
            ->where('sf.buyer_id', $customerId)
            ->whereIn('sf.status', StorageFeeStatus::needCalculateStatus())
            ->whereNotExists(function ($q) {
                // 排除掉已售未发和已完成的仓租（特殊情况会导致已完成的销售单对应的仓租未完结，因为此处排除掉已完成的）
                $q->from('tb_sys_customer_sales_order as cso')
                    ->leftJoin('tb_sys_customer_sales_order_line as csol', 'cso.id', '=', 'csol.header_id')
                    ->whereIn(
                        'cso.order_status',
                        [
                            CustomerSalesOrderStatus::BEING_PROCESSED,
                            CustomerSalesOrderStatus::ON_HOLD,
                            CustomerSalesOrderStatus::WAITING_FOR_PICK_UP,
                            CustomerSalesOrderStatus::COMPLETED
                        ]
                    )
                    ->whereIn('csol.item_status', [CustomerSalesOrderLineItemStatus::PENDING, CustomerSalesOrderLineItemStatus::SHIPPING])
                    ->whereRaw('cso.id = sf.sales_order_id');
            })
            ->when(!empty($itemCode), function ($q) use ($itemCode) {
                $q->whereIn('sf.product_sku', $itemCode);
            })
            ->when(!empty($productId), function ($q) use ($productId) {
                $q->whereIn('sf.product_id', $productId);
            })
            ->groupBy(['sf.product_id']);
    }
    // endregion

    // region 取消的销售单但是没有申请rma锁定库存

    /**
     * 取消的销售单但是没有申请rma锁定库存列表query构建
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return Builder
     */
    public function buildCancelSalesOrderNotApplyRmaListQuery($customerId = null, array $itemCode = null, array $productId = null)
    {
        $query = $this->buildCancelSalesOrderNotApplyRma(...func_get_args());
        return db(new Expression('(' . get_complete_sql($query) . ') as t'))
            ->select([
                't.associate_id as relate_id', 't.product_id', 't.qty', 't.block_type',
                't.reason_type', 't.associate_id'
            ]);
    }

    /**
     * 获取取消的销售单没有申请rma的库存数量
     * search 列表使用
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return \Framework\Model\Eloquent\Builder|BuildsQueries|\Illuminate\Database\Eloquent\Builder|Builder|mixed
     */
    public function buildCancelSalesOrderNotApplyRma($customerId = null, array $itemCode = null, array $productId = null)
    {
        $customerId = $customerId ?? $this->customerId;
        return OrderAssociated::query()
            ->select(['cso.id', 'cso.order_id', 'csol.id as line_id', 'oa.product_id', 'oa.id as associate_id', 'oa.qty'])
            ->selectRaw('"1" as block_type')
            ->selectRaw('"1-1" as reason_type')
            ->alias('oa')
            ->leftJoin('tb_sys_customer_sales_order as cso', 'cso.id', '=', 'oa.sales_order_id')
            ->leftJoin('tb_sys_customer_sales_order_line as csol', 'csol.id', '=', 'oa.sales_order_line_id')
            ->whereNotExists(function ($q) use ($customerId) {
                $q->from('oc_yzc_rma_order as ro')
                    ->select('*')
                    ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
                    ->where([
                        'ro.order_type' => 1,
                        'ro.buyer_id' => $customerId,
                        'ro.cancel_rma' => 0,
                    ])
                    ->whereRaw('cso.order_id = ro.from_customer_order_id')
                    ->whereRaw('oa.order_id = ro.order_id')
                    ->whereRaw('oa.product_id = rop.product_id')
                    ->whereIn('rop.rma_type', RmaApplyType::getRefund())
                    ->whereIn('rop.status_refund', [0, 1]);
            })
            ->where([
                'oa.buyer_id' => $customerId,
                'cso.order_status' => CustomerSalesOrderStatus::CANCELED,
            ])
            ->when(!empty($itemCode), function ($q) use ($itemCode) {
                $q->whereIn('csol.item_code', $itemCode);
            })
            ->when(!empty($productId), function ($q) use ($productId) {
                $q->whereIn('oa.product_id', $productId);
            })
            ->groupBy(['oa.id']);
    }

    // endregion

    // region 取消的销售单或者采购单申请rma但是没有同意 锁定库存

    /**
     * 取消的销售单或者采购单申请rma但是没有同意列表
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return Builder
     */
    public function buildApplyRmaNotAgreeListQuery($customerId = null, array $itemCode = null, array $productId = null)
    {
        $query1 = $this->buildCancelSalesOrderApplyRmaNotAgree(...func_get_args());
        $query2 = $this->buildPurchaseOrderApplyRmaNotAgree(...func_get_args());
        $query = $query1->union($query2);
        return db(new Expression('(' . get_complete_sql($query) . ') as t'))
            ->select([
                't.associate_id as relate_id', 't.product_id', 't.qty', 't.block_type',
                't.reason_type', 't.associate_id',
            ]);
    }

    /**
     * 获取取消的销售单申请rma但是没有同意的库存数量
     * search 列表使用
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return \Framework\Model\Eloquent\Builder|BuildsQueries|\Illuminate\Database\Eloquent\Builder|Builder
     */
    public function buildCancelSalesOrderApplyRmaNotAgree($customerId = null, array $itemCode = null, array $productId = null)
    {
        $customerId = $customerId ?? $this->customerId;
        return OrderAssociated::query()
            ->select(['cso.id', 'cso.order_id', 'csol.id as line_id', 'oa.product_id', 'oa.id as associate_id', 'oa.qty'])
            ->selectRaw('"2" as block_type')
            ->selectRaw('"2-1" as reason_type')
            ->alias('oa')
            ->leftJoin('tb_sys_customer_sales_order as cso', 'cso.id', '=', 'oa.sales_order_id')
            ->leftJoin('tb_sys_customer_sales_order_line as csol', 'csol.id', '=', 'oa.sales_order_line_id')
            ->whereExists(function ($q) use ($customerId) {
                $q->from('oc_yzc_rma_order as ro')
                    ->select('*')
                    ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
                    ->where([
                        'ro.order_type' => 1,
                        'ro.buyer_id' => $customerId,
                        'ro.cancel_rma' => 0,
                    ])
                    ->whereRaw('oa.order_id = ro.order_id')
                    ->whereRaw('cso.order_id = ro.from_customer_order_id')
                    ->whereRaw('oa.product_id = rop.product_id')
                    ->whereIn('rop.rma_type', RmaApplyType::getRefund())
                    ->whereIn('rop.status_refund', [0]);
            })
            ->where(['oa.buyer_id' => $customerId, 'cso.order_status' => CustomerSalesOrderStatus::CANCELED,])
            ->when(!empty($itemCode), function ($q) use ($itemCode) {
                $q->whereIn('csol.item_code', $itemCode);
            })
            ->when(!empty($productId), function ($q) use ($productId) {
                $q->whereIn('oa.product_id', $productId);
            })
            ->groupBy(['oa.id']);
    }

    /**
     * 获取采购单申请rma但是没有同意的库存数量
     * search 列表使用
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return \Framework\Model\Eloquent\Builder|BuildsQueries|\Illuminate\Database\Eloquent\Builder|Builder
     */
    public function buildPurchaseOrderApplyRmaNotAgree($customerId = null, array $itemCode = null, array $productId = null)
    {
        $customerId = $customerId ?? $this->customerId;
        return YzcRmaOrder::query()->alias('ro')
            ->select([
                new Expression('"0" as id'), 'ro.order_id',
                new Expression('"0" as line_id'), 'rop.product_id',
                'ro.id as associate_id', 'rop.quantity as qty'
            ])
            ->selectRaw('"2" as block_type')
            ->selectRaw('"2-2" as reason_type')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'rop.product_id')
            ->where([
                'ro.order_type' => 2,
                'ro.buyer_id' => $customerId,
                'ro.cancel_rma' => 0,
            ])
            ->whereIn('rop.rma_type', RmaApplyType::getRefund())
            ->whereIn('rop.status_refund', [0])
            ->when(!empty($itemCode), function ($q) use ($itemCode) {
                $q->whereIn('p.sku', $itemCode);
            })
            ->when(!empty($productId), function ($q) use ($productId) {
                $q->whereIn('rop.product_id', $productId);
            });
    }

    // endregion

    // region 锁定库存
    /**
     * 锁定库存列表
     * @param null $customerId
     * @param array|null $itemCode
     * @param array|null $productId
     * @return Builder
     */
    public function buildBlockListQuery($customerId = null, array $itemCode = null, array $productId = null)
    {
        $query1 = $this->buildCancelSalesOrderNotApplyRmaListQuery(...func_get_args());
        $query2 = $this->buildApplyRmaNotAgreeListQuery(...func_get_args());
        $query3 = $this->buildWaitForPayListQuery(...func_get_args());
        $query4 = $this->buildAsrListQuery(...func_get_args());
        $query5 = $this->buildSoldOutCountListQuery(...func_get_args());
        $query6 = $this->buildBuyerProductLockListQuery(...func_get_args());
        return $query1->union($query2)->union($query3)->union($query4)->union($query5)->union($query6);
    }
    // endregion

    // region 校验超过30天方法

    /**
     * 传入对应product id数组 获取超过30天的信息
     * @param array $productIds [ product_id1, product_id2, product_id3... ]
     * @param int $days
     * @return array   [ product_id1 => true, product_id2 => false, ............... ]
     */
    public function checkProductOverSpecialDays(array $productIds, int $days = 30): array
    {
        if (empty($productIds)) return [];
        $ret = array_combine($productIds, array_pad([], count($productIds), false));
        $query = $this->buildProductCostListQuery(null, null, $productIds);
        foreach ($query->cursor() as $item) {
            if ($ret[$item->product_id] == true) {
                continue;
            }
            $diffDays = $this->getInventoryDays($item->create_time, customer()->getCountryId(), $item->type_id, $item->agreement_id);
            $ret[$item->product_id] = bccomp($diffDays, $days) > 0;
        }
        return $ret;
    }

    // endregion

    // region 计算预估仓租费
    /**
     * 获取特定采购单的特定产品产品仓租费
     * @param int $orderId
     * @param int $productId
     * @param int $qty
     * @return StorageFee|\Framework\Model\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder|Model|Builder|object|null
     */
    public function getStorageFee(int $orderId, int $productId, int $qty)
    {
        $order = Order::query()->find($orderId);
        // 16272 预防order没记录导致代码异常
        if (!$order) {
            return null;
        }
        /** @var OrderProduct $orderProduct */
        $orderProduct = $order->orderProducts->where('product_id', $productId)->first();
        $storageFeeRepo = app(StorageFeeRepository::class);
        $canRmaStorageFeeIds = Arr::get(
            $storageFeeRepo->getCanRMAStorageFeeIdsByOrder($orderId, [$orderProduct->order_product_id => $qty]),
            $orderProduct->order_product_id
        );
        if (empty($canRmaStorageFeeIds)) {
            return null;
        }
        return StorageFee::query()
            ->select(['id', 'days'])
            ->selectRaw('group_concat(id) as ids')
            ->selectRaw('if(sum(fee_unpaid) > 0 , sum(fee_unpaid), 0) as feeTotal')
            ->selectRaw('if(sum(fee_paid) > 0, sum(fee_paid), 0) as feePaid')
            ->whereIn('id', $canRmaStorageFeeIds)
            ->groupBy(['order_id', 'product_id'])
            ->first();
    }

    /**
     * 获取销售单预估仓租费用
     * @param int $associateId
     * @return StorageFee|\Framework\Model\Eloquent\Builder|Model|Builder|object|null
     */
    public function getSalesOrderStorageFee(int $associateId)
    {
        $storageFeeRepo = app(StorageFeeRepository::class);
        $canRmaStorageFeeIds = $storageFeeRepo->getBoundStorageFeeIdsByAssociated($associateId);
        if (empty($canRmaStorageFeeIds)) {
            return null;
        }
        return StorageFee::query()
            ->select(['id', 'days'])
            ->selectRaw('group_concat(id) as ids')
            ->selectRaw('if(sum(fee_unpaid) > 0, sum(fee_unpaid), 0) as feeTotal')
            ->selectRaw('if(sum(fee_paid) > 0, sum(fee_paid), 0) as feePaid')
            ->whereIn('id', $canRmaStorageFeeIds)
            ->groupBy(['order_id', 'product_id'])
            ->first();
    }
    // endregion

    /**
     * 获取商品协议库存列表
     *
     * @param int $customerId
     * @param int|array|null $productId
     * @return \Illuminate\Support\Collection
     */
    public function getContractsStockByProductId($customerId, $productId = null)
    {
        // 基于product_lock表构建模型
        $list = $this->buildProductLockModel($customerId)
            ->select([
                'type_id',
                'pl.parent_product_id',
                'pl.agreement_id',
                new Expression('CASE WHEN type_id=' . ProductLockType::MARGIN . ' THEN ma.agreement_id WHEN type_id=' . ProductLockType::FUTURES . ' THEN fma.agreement_no ELSE null END AS agreement_no'),
                'pl.qty',
                'pl.set_qty',
                new Expression('CASE WHEN type_id=' . ProductLockType::MARGIN . ' THEN ma.expire_time WHEN type_id=' . ProductLockType::FUTURES . ' THEN fd.confirm_delivery_date ELSE null END AS expire_time'),
                new Expression('CASE WHEN type_id=' . ProductLockType::FUTURES . ' THEN fd.delivery_type ELSE null END AS delivery_type'),
                new Expression('CASE WHEN type_id=' . ProductLockType::FUTURES . ' THEN fd.last_purchase_num ELSE null END AS last_purchase_num'),
                new Expression('CASE WHEN type_id=' . ProductLockType::FUTURES . ' THEN fma.version ELSE null END AS futures_version'),
                'p.sku as item_code',
                'octc.screenname'
            ])
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'pl.parent_product_id')
            ->leftJoin('oc_customerpartner_to_customer as octc', 'octc.customer_id', '=', 'pl.seller_id')//Seller
            ->when(!empty($productId), function ($query) use ($productId) {
                $query->whereIn('pl.parent_product_id', (array)$productId);
            })
            ->groupBy(['pl.type_id', 'pl.agreement_id', 'pl.parent_product_id'])// 按协议类型、协议号、商品分组去重
            ->get();
        // 批量获取现货尾款
        $marginAgreementIds = $list->where('type_id', '=', ProductLockType::MARGIN)->pluck('agreement_id')->toArray();
        $marginNeedPaidStorageFees = [];
        $marginModelAgreementIdMap = collect([]);
        if (!empty($marginAgreementIds)) {
            $marginNeedPaidStorageFees = app(StorageFeeRepository::class)->getMarginRestNeedPayByAgreementId($marginAgreementIds);
            $marginModelAgreementIdMap = MarginAgreement::query()->whereIn('id', $marginAgreementIds)->get()->keyBy('id');
        }
        $list = $list->map(function (ProductLock $item) use ($marginNeedPaidStorageFees, $marginModelAgreementIdMap) {
            $statusDesc = '';
            $url = '';
            $diffDay = null;
            $item['add_cart'] = true;// 是否允许加入购物车
            // 考虑combo品的情况，需要用总数量除以配比数量得出库存数量
            if ($item->set_qty > 0 && $item->qty >= $item->set_qty) {
                $item['pending_purchase_quantity'] = intval($item->qty / $item->set_qty);
            } else {
                $item['pending_purchase_quantity'] = 0;
            }
            if ($item->type_id == ProductLockType::FUTURES) {
                //期货要使用当前国别时间
                $statusDesc = FuturesMarginDeliveryStatus::getDescription(FuturesMarginDeliveryStatus::TO_BE_PAID);
                $url = url()->to(['account/product_quotes/futures/buyerFuturesBidDetail', 'id' => $item->agreement_id]);
                if ($item['last_purchase_num']) {
                    $item['last_purchase_num'] = $item['pending_purchase_quantity'];//期货协议剩余锁库存
                }
                if ($item['delivery_type'] == FuturesMarginDeliveryType::TO_MARGIN || !$item['last_purchase_num']) {
                    //转现货或者剩余数量为0不需要add card
                    $item['add_cart'] = false;
                }
                // 计算剩余天数
                /** @var ModelFuturesAgreement $futuresAgreement */
                $futuresAgreement = load()->model('futures/agreement');
                $diffDay = $futuresAgreement->getConfirmLeftDay((object)['confirm_delivery_date' => $item['expire_time'], 'version' => $item['futures_version']]);
            } elseif ($item->type_id == ProductLockType::MARGIN) {
                $statusDesc = MarginAgreementStatus::getDescription(MarginAgreementStatus::SOLD);
                $url = url()->to(['account/product_quotes/margin/detail_list', 'id' => $item->agreement_id]);
                // 计算剩余天数
                $diffDay = Carbon::now()->diffInDays(Carbon::createFromTimeString($item['expire_time']), false);
                $diffDay = $diffDay >= 0 ? $diffDay + 1 : 0;
                if ($marginAgreementModel = $marginModelAgreementIdMap->get($item->agreement_id, '')) {
                    /** @var MarginAgreement $marginAgreementModel */
                    $diffDay = min($diffDay, $marginAgreementModel->day);
                }
                // 获取现货尾款仓租
                $storageFeeInfo = $marginNeedPaidStorageFees[$item->agreement_id] ?? null;
                $item['need_pay'] = 0;
                $item['paid'] = 0;
                $item['fee_detail_range'] = [];
                if ($storageFeeInfo) {
                    $item['need_pay'] = $storageFeeInfo->need_pay;
                    $item['paid'] = $storageFeeInfo->paid;
                    $item['fee_detail_range'] = app(StorageFeeRepository::class)->getFeeDetailRange($storageFeeInfo, $item['pending_purchase_quantity']);
                }
            }
            $item['diff_day'] = $diffDay;
            $item['type_desc'] = ProductLockType::getDescription($item->type_id);
            $item['agreement_status_desc'] = $statusDesc;
            $item['agreement_url'] = $url;
            return $item;
        });
        // 按剩余天数升序+剩余库存倒序
        $newList = $list->toArray();
        $parentProductIdArr = $list->pluck('parent_product_id')->toArray();
        $diffDayArr = $list->pluck('diff_day')->toArray();
        $pendingPurchaseQuantityArr = $list->pluck('pending_purchase_quantity')->toArray();
        array_multisort($parentProductIdArr, SORT_ASC, $diffDayArr, SORT_ASC, $pendingPurchaseQuantityArr, SORT_DESC, $newList);
        return collect($newList);
    }

    /**
     * 获取绑定明细对应的申请退款但是没有同意的rma
     * @param int $associateId
     * @return YzcRmaOrder|null
     */
    public function getApplyRmaNotAgree(int $associateId)
    {
        $associate = OrderAssociated::query()->find($associateId);
        $salesOrder = $associate->customerSalesOrder;
        return YzcRmaOrder::query()->alias('ro')
            ->select(['ro.*'])
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->where([
                'ro.cancel_rma' => 0,
                'ro.from_customer_order_id' => $salesOrder->order_id,
                'rop.product_id' => $associate->product_id,
                'ro.order_id' => $associate->order_id,
                'ro.order_type' => 1,
                'ro.buyer_id' => $associate->buyer_id,
            ])
            ->whereIn('rop.rma_type', RmaApplyType::getRefund())
            ->whereIn('rop.status_refund', [0])
            ->first();
    }

    /**
     * 校验商品是否需要显示警告
     * 不需要显示的有下面2种情况
     * 1.商品为配件
     * 2.商品为0体积产品(0体积产品就是长宽高均为0的情况)
     * @param int $productId
     * @return bool
     */
    public function checkProductShowAlert(int $productId): bool
    {
        $product = Product::query()->find($productId);
        if (!$product) {
            return false;
        }
        // 配件
        if ($product->part_flag == 1) {
            return false;
        }
        if ($product->combo_flag == 0) {
            // 不是combo品
            return ($product->length > 0 || $product->width > 0 || $product->height > 0);
        } else {
            // 是combo
            $combos = $product->combos;
            $ret = false;
            $combos->map(function (ProductSetInfo $item) use (&$ret) {
                $ret = $ret || $this->checkProductShowAlert($item->set_product_id);
            });
            return $ret;
        }
    }

    /**
     * 获取当前时区的间隔天数
     * 转化为当前时间并比较时间间隔
     * @param $time
     * @param int $countryId
     * @return int
     */
    public function getDiffDays($time, int $countryId): int
    {
        if (empty($time)) return 0;
        $fromTime = Carbon::parse($time, 'America/Los_Angeles')
            ->setTimeZone(CountryHelper::getTimezone($countryId))
            ->format('Y-m-d');
        $nowTime = Carbon::now(CountryHelper::getTimezone($countryId))->format('Y-m-d');
        return (int)Carbon::parse($fromTime)->diffInDays($nowTime);
    }

    /**
     * 通过SKU获取Buyer可用库存 - 范围采购单为对象（过滤服务店铺）
     *
     * @param int $buyerId BuyerId
     * @param array $skuArr SKU Array
     * @return array
     */
    public function getBuyerCostBySku(int $buyerId, $skuArr)
    {
        // 采购数量
        $buyerResults = $this->getPurchaseQtyBySku($skuArr, $buyerId);
        $orderProductInfoArr = array_column($buyerResults,'order_product_id');

        //已绑定的数量
        $assQtyResults = $this->getAssociatedQtyByOrderProductsIds($orderProductInfoArr);

        //采购订单的rma
        $rmaQtyResults = $this->getRmaQtyByOrderProductsIds($orderProductInfoArr, $buyerId);

        // 锁定的数量
        $lockResults =  app(BuyerStockService::class)->getLockQuantityIndexByOrderProductIdByOrderProductIds($orderProductInfoArr);

        foreach ($buyerResults as $orderProductId=> &$buyerResult){
            $buyerResult = isset($assQtyResults[$orderProductId]) ? array_merge($buyerResult, $assQtyResults[$orderProductId]) : $buyerResult;
            $buyerResult = isset($rmaQtyResults[$orderProductId]) ? array_merge($buyerResult, $rmaQtyResults[$orderProductId]) : $buyerResult;

            $buyerResult['left_qty'] = $buyerResult['original_qty']
                - (isset($buyerResult['assQty']) ? $buyerResult['assQty'] : 0)
                - (isset($buyerResult['rmaQty']) ? $buyerResult['rmaQty'] : 0)
                - (isset($lockResults[$orderProductId]) ? $lockResults[$orderProductId] : 0);
            if ($buyerResult['left_qty'] <= 0) {
                unset($buyerResults[$orderProductId]);
            }
        }

        return $buyerResults;
    }

    /**
     * 获取Buyer采购数量
     *
     * @param array $skuArr
     * @param int $buyerId
     * @return array
     */
    private function getPurchaseQtyBySku(array $skuArr, int $buyerId)
    {
        if (empty($skuArr)) {
            return [];
        }

        return CostDetail::query()->alias('scd')
            ->leftJoinRelations(['product as op', 'receiveLine as srl'])
            ->leftJoin('oc_order_product as oop', function (JoinClause $left) {
                $left->on('oop.order_id', '=', 'srl.oc_order_id')
                    ->whereColumn('oop.product_id', 'scd.sku_id');
            })
            ->select('scd.original_qty','op.sku','scd.sku_id','op.sku','scd.seller_id','oop.order_id','oop.order_product_id', 'op.product_id')
            ->whereNotIn('scd.seller_id', SERVICE_STORE_ARRAY) // 过滤服务店铺
            ->whereIn('op.sku',$skuArr)
            ->where('scd.buyer_id', $buyerId)
            ->where('scd.onhand_qty', '>', 0)
            ->whereNull('scd.rma_id')
            ->get()
            ->keyBy('order_product_id')
            ->toArray();
    }

    /**
     * 已绑定的数量
     *
     * @param array $orderProductArr order_product_id数组
     * @return array
     */
    private function getAssociatedQtyByOrderProductsIds(array $orderProductArr)
    {
        if (empty($orderProductArr)) {
            return [];
        }

        return OrderAssociated::query()->alias('soa')
            ->whereIn('soa.order_product_id', $orderProductArr)
            ->selectRaw('sum(soa.qty) as assQty,soa.order_product_id')
            ->groupBy('soa.order_product_id')
            ->get()
            ->keyBy('order_product_id')
            ->toArray();
    }

    /**
     * 采购订单的rma
     *
     * @param array $orderProductArr order_product_id数组
     * @param int $buyerId
     * @return array
     */
    private function getRmaQtyByOrderProductsIds(array $orderProductArr, int $buyerId)
    {
        if (empty($orderProductArr)) {
            return [];
        }

        return YzcRmaOrder::query()->alias('yro')
            ->leftJoinRelations('yzcRmaOrderProduct as rop')
            ->selectRaw('rop.product_id,rop.order_product_id,sum(rop.quantity) as rmaQty')
            ->whereIn('rop.order_product_id',$orderProductArr)
            ->where('yro.order_type', RmaType::PURCHASE_ORDER)
            ->where('yro.buyer_id', $buyerId)
            ->where('yro.cancel_rma', YesNoEnum::NO)
            ->where('rop.status_refund', '<>', RmaOrderProductStatusRefund::REJECT)
            ->groupBy('rop.order_product_id')
            ->get()
            ->keyBy('order_product_id')
            ->toArray();
    }

    /**
     * 通过SKU获取Buyer可用库存 - 返回SKU为对象
     *
     * @param int $buyerId BuyerId
     * @param array $skuArr SKU数组
     * @return array
     */
    public function getBuyerCostBySkuToSku(int $buyerId, $skuArr)
    {
        $costList = $this->getBuyerCostBySku($buyerId, $skuArr);
        $skuCostList = [];
        foreach ($costList as $item) {
            isset($skuCostList[$item['sku']]) ? $skuCostList[$item['sku']]['left_qty'] += $item['left_qty'] : $skuCostList[$item['sku']] = $item;
        }

        return $skuCostList;
    }
}
