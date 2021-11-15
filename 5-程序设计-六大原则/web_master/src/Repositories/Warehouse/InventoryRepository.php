<?php

namespace App\Repositories\Warehouse;

use App\Enums\Warehouse\BatchTransactionType;
use App\Enums\Warehouse\SellerDeliveryLineType;
use App\Models\Product\Batch;
use App\Models\Warehouse\SellerDeliveryLine;
use Framework\Model\Eloquent\Builder;
use Illuminate\Support\Collection;

class InventoryRepository
{
    const IN_INVENTORY = 1; // 入库
    const OUT_INVENTORY = 2; // 出库

    /**
     * 获取批次入库 - Bulider
     *
     * @param int $customerId
     * @param array $filter
     * @param int $type 1:查询列表 2：下载
     * @param int $inoutType 0:批次入库查询 1:入出库查询
     * @return Builder
     */
    private function getBatchRepositoryBuilder(int $customerId, array $filter, int $type = 1, int $inoutType = 0)
    {
        // 关联入库单 -> common_one:入库单号 common_two:集装箱号 batch_number:入库单ID
        $sqlReceiptObj = Batch::query()->alias('b')
            ->leftJoin('tb_sys_receipts_order as sro', 'b.receipts_order_id', '=', 'sro.receive_order_id')
            ->select(['b.batch_id', 'b.product_id', 'b.sku', 'b.mpn', 'sro.receive_order_id as batch_number', 'b.transaction_type', 'b.receive_date', 'b.create_time', 'b.rma_id', 'b.original_qty', 'b.onhand_qty', 'sro.receive_number as common_one', 'sro.container_code as common_two'])
            ->when($type == 2, function (Builder $query) {
                return $query->leftJoin('oc_product_description as pd', 'b.product_id', '=', 'pd.product_id')->selectRaw('pd.name');
            })
            ->where('b.customer_id', $customerId)
            ->where('b.transaction_type', BatchTransactionType::INVENTORY_RECEIVE)
            ->when(isset($filter['filter_sku']) && $filter['filter_sku'] != '', function (Builder $query) use ($filter) {
                return $query->where(function (Builder $query) use ($filter) {
                    return $query->whereRaw('instr(b.sku, ?)', [$filter['filter_sku']])
                        ->orWhereRaw('instr(b.mpn, ?)', [$filter['filter_sku']]);
                });
            })
            ->when(isset($filter['filter_batch_no']) && $filter['filter_batch_no'] != '', function (Builder $query) use ($filter) {
                return $query->WhereRaw('instr(sro.receive_number, ?)', [$filter['filter_batch_no']]);
            })
            ->when(! empty($filter['filter_create_start_date']), function (Builder $query) use ($filter) {
                return $query->where('b.create_time', '>=', $filter['filter_create_start_date']);
            })
            ->when(! empty($filter['filter_create_end_date']), function (Builder $query) use ($filter) {
                return $query->where('b.create_time', '<=', $filter['filter_create_end_date']);
            });
        // 关联RMA -> common_one:RMA ID common_two:采购订单ID
        $sqlRmaObj = Batch::query()->alias('b')
            ->leftJoin('oc_yzc_rma_order as yro', 'b.rma_id', '=', 'yro.id')
            ->select(['b.batch_id', 'b.product_id', 'b.sku', 'b.mpn', 'b.batch_number', 'b.transaction_type', 'b.receive_date', 'b.create_time', 'b.rma_id', 'b.original_qty', 'b.onhand_qty', 'yro.rma_order_id as common_one', 'yro.order_id as common_two'])
            ->when($type == 2, function (Builder $query) {
                return $query->leftJoin('oc_product_description as pd', 'b.product_id', '=', 'pd.product_id')->selectRaw('pd.name');
            })
            ->where('b.customer_id', $customerId)
            ->where('b.transaction_type', BatchTransactionType::RMA_RETURN)
            ->when(isset($filter['filter_sku']) && $filter['filter_sku'] != '', function (Builder $query) use ($filter) {
                return $query->where(function (Builder $query) use ($filter) {
                    return $query->whereRaw('instr(b.sku, ?)', [$filter['filter_sku']])
                        ->orWhereRaw('instr(b.mpn, ?)', [$filter['filter_sku']]);
                });
            })
            ->when(isset($filter['filter_batch_no']) && $filter['filter_batch_no'] != '', function (Builder $query) use ($filter) {
                return $query->WhereRaw('instr(yro.rma_order_id, ?)', [$filter['filter_batch_no']]);
            })
            ->when(! empty($filter['filter_create_start_date']), function (Builder $query) use ($filter) {
                return $query->where('b.create_time', '>=', $filter['filter_create_start_date']);
            })
            ->when(! empty($filter['filter_create_end_date']), function (Builder $query) use ($filter) {
                return $query->where('b.create_time', '<=', $filter['filter_create_end_date']);
            });
        // 批次库存查询和入出库查询所展示给前端的内容不同
        $selectRaw = ['b.batch_id', 'b.product_id', 'b.sku', 'b.mpn', 'b.batch_number', 'b.transaction_type', 'b.receive_date', 'b.create_time', 'b.rma_id', 'b.original_qty', 'b.onhand_qty', 'sia.batch_number as common_one', 'sia.remark as common_two'];
        if ($inoutType) {
            $selectRaw[count($selectRaw) - 2] = 'sia.remark as common_one';
            $selectRaw[count($selectRaw) - 1] = 'sia.batch_number as common_two';
        }
        // 关联库存调整 -> common_one:调整批次号 common_two:备注
        $sqlAdjObj = Batch::query()->alias('b')
            ->leftJoin('tb_sys_seller_inventory_adjust as sia', 'b.receipts_order_id', '=', 'sia.inventory_id')
            ->select($selectRaw)
            ->when($type == 2, function (Builder $query) {
                return $query->leftJoin('oc_product_description as pd', 'b.product_id', '=', 'pd.product_id')->selectRaw('pd.name');
            })
            ->where('b.customer_id', $customerId)
            ->whereIn('b.transaction_type', BatchTransactionType::getChangeType())
            ->when(isset($filter['filter_sku']) && $filter['filter_sku'] != '', function (Builder $query) use ($filter) {
                return $query->where(function (Builder $query) use ($filter) {
                    return $query->whereRaw('instr(b.sku, ?)', [$filter['filter_sku']])
                        ->orWhereRaw('instr(b.mpn, ?)', [$filter['filter_sku']]);
                });
            })
            ->when(isset($filter['filter_batch_no']) && $filter['filter_batch_no'] != '', function (Builder $query) use ($filter, $inoutType) {
                if ($inoutType) { // 入出库查询
                    return $query->whereRaw('instr(sia.batch_number, ?)', $filter['filter_batch_no']);
                } else { // 批次库存查询
                    return $query->whereRaw('instr(b.batch_number, ?)', $filter['filter_batch_no']);
                }
            })
            ->when(! empty($filter['filter_create_start_date']), function (Builder $query) use ($filter) {
                return $query->where('b.create_time', '>=', $filter['filter_create_start_date']);
            })
            ->when(! empty($filter['filter_create_end_date']), function (Builder $query) use ($filter) {
                return $query->where('b.create_time', '<=', $filter['filter_create_end_date']);
            });
        // 其他调整类型
        $sqlObj = Batch::query()->alias('b')
            ->select(['b.batch_id', 'b.product_id', 'b.sku', 'b.mpn', 'b.batch_number', 'b.transaction_type', 'b.receive_date', 'b.create_time', 'b.rma_id', 'b.original_qty', 'b.onhand_qty', 'b.receipts_order_id as common_one', 'b.remark as common_two'])
            ->when($type == 2, function (Builder $query) {
                return $query->leftJoin('oc_product_description as pd', 'b.product_id', '=', 'pd.product_id')->selectRaw('pd.name');
            })
            ->where('customer_id', $customerId)
            ->whereIn('transaction_type', BatchTransactionType::getOtherType())
            ->when(isset($filter['filter_sku']) && $filter['filter_sku'] != '', function (Builder $query) use ($filter) {
                return $query->where(function (Builder $query) use ($filter) {
                    return $query->whereRaw('instr(sku, ?)', [$filter['filter_sku']])
                        ->orWhereRaw('instr(mpn, ?)', [$filter['filter_sku']]);
                });
            })
            ->when(isset($filter['filter_batch_no']) && $filter['filter_batch_no'] != '', function (Builder $query) use ($filter) {
                return $query->whereRaw('instr(batch_number, ?)', [$filter['filter_batch_no']]);
            })
            ->when(! empty($filter['filter_create_start_date']), function (Builder $query) use ($filter) {
                return $query->where('create_time', '>=', $filter['filter_create_start_date']);
            })
            ->when(! empty($filter['filter_create_end_date']), function (Builder $query) use ($filter) {
                return $query->where('create_time', '<=', $filter['filter_create_end_date']);
            });

        // 存在类型检索 -- 返回对应关联关系即可
        if ($filter['filter_type'] == BatchTransactionType::INVENTORY_RECEIVE) {
            return $sqlReceiptObj;
        } elseif ($filter['filter_type'] == BatchTransactionType::RMA_RETURN) {
            return $sqlRmaObj;
        } elseif (in_array($filter['filter_type'], BatchTransactionType::getChangeType())) {
            return $sqlAdjObj->where('b.transaction_type', $filter['filter_type']);
        } elseif (in_array($filter['filter_type'], BatchTransactionType::getOtherType())) {
            return $sqlObj->where('transaction_type', $filter['filter_type']);
        }

        // 既不存在类型检索 也不存在 批次号检索
        return $sqlObj->unionAll($sqlReceiptObj)->unionAll($sqlRmaObj)->unionAll($sqlAdjObj);
    }

    /**
     * 获取批次入库 - 总数
     *
     * @param int $customerId
     * @param array $filter
     * @return int
     */
    public function getBatchRepositoryCount(int $customerId, array $filter)
    {
        $sqlBulider = $this->getBatchRepositoryBuilder($customerId, $filter);
        return $sqlBulider->count();
    }

    /**
     * 获取批次入库 - 列表
     *
     * @param int $customerId
     * @param array $filter
     *  [
     *      'filter_sku' => string ItemCode|MPN
     *      'filter_batch_no' => string 批次号
     *      'filter_type' => int 入库类型(对应BatchTransactionType)
     *      'filter_create_start_date' => string 筛选开始时间
     *      'filter_create_end_date' => string 筛选结束时间
     *      'page' => int 分页
     *      'pageLimit' => int 分页大小
     *  ]
     * @param int $type 1:获取展示列表数据  2：获取下载列表数据
     * @return Collection
     */
    public function getBatchRepositoryList(int $customerId, array $filter, int $type = 1)
    {
        $sqlBulider = $this->getBatchRepositoryBuilder($customerId, $filter, $type);
        return $sqlBulider->orderBy('create_time', 'desc')->orderBy('product_id')->forPage($filter['page'], $filter['pageLimit'])->get();
    }

    /**
     * Seller库存出库-Builder
     *
     * @param int $customerId
     * @param array $filter
     * @param int $type 1:查询列表 2：下载
     * @return array Builder数组
     */
    private function getInoutRepositoryBulider(int $customerId, array $filter, int $type = 1)
    {
        // 关联库存调整 -> common_one:调整批次号 common_two:备注
        $sqlAdjObj = SellerDeliveryLine::query()->alias('sdl')
            ->leftJoin('tb_sys_seller_inventory_adjust as sia', 'sdl.order_id', '=', 'sia.inventory_id')
            ->leftJoin('oc_product as p', 'sdl.product_id', '=', 'p.product_id')
            ->selectRaw('sdl.batch_id,sdl.product_id,p.sku,p.mpn,sdl.order_id as batch_number,sdl.type as transaction_type,sdl.UpdateTime as receive_date,sdl.CreateTime as create_time,sdl.rma_id,sdl.qty as original_qty,"inout" as onhand_qty,sia.remark as common_one,sia.batch_number as common_two')
            ->when($type == 2, function (Builder $query) {
                return $query->leftJoin('oc_product_description as pd', 'sdl.product_id', '=', 'pd.product_id')->selectRaw('pd.name');
            })
            ->where('sdl.seller_id', $customerId)
            ->whereIn('sdl.type', SellerDeliveryLineType::getChangeType())
            ->when(isset($filter['filter_sku']) && $filter['filter_sku'] != '', function (Builder $query) use ($filter) {
                return $query->where(function (Builder $query) use ($filter) {
                    return $query->whereRaw('instr(p.sku, ?)', [$filter['filter_sku']])
                        ->orWhereRaw('instr(p.mpn, ?)', [$filter['filter_sku']]);
                });
            })
            ->when(isset($filter['filter_batch_no']) && $filter['filter_batch_no'] != '', function (Builder $query) use ($filter) {
                return $query->whereRaw('instr(sia.batch_number, ?)', [$filter['filter_batch_no']]);
            })
            ->when(! empty($filter['filter_create_start_date']), function (Builder $query) use ($filter) {
                return $query->where('sdl.CreateTime', '>=', $filter['filter_create_start_date']);
            })
            ->when(! empty($filter['filter_create_end_date']), function (Builder $query) use ($filter) {
                return $query->where('sdl.CreateTime', '<=', $filter['filter_create_end_date']);
            });
        // 关联RMA -> common_one:RMA 主键ID common_two:RMA ID
        $sqlRmaObj = SellerDeliveryLine::query()->alias('sdl')
            ->leftJoin('oc_yzc_rma_order as yro', 'sdl.rma_id', '=', 'yro.id')
            ->leftJoin('oc_product as p', 'sdl.product_id', '=', 'p.product_id')
            ->selectRaw('sdl.batch_id,sdl.product_id,p.sku,p.mpn,sdl.order_id as batch_number,sdl.type as transaction_type,sdl.UpdateTime as receive_date,sdl.CreateTime as create_time,sdl.rma_id,sdl.qty as original_qty,"inout" as onhand_qty,yro.order_id as common_one,yro.rma_order_id as common_two')
            ->when($type == 2, function (Builder $query) {
                return $query->leftJoin('oc_product_description as pd', 'sdl.product_id', '=', 'pd.product_id')->selectRaw('pd.name');
            })
            ->where('sdl.seller_id', $customerId)
            ->where('sdl.type', SellerDeliveryLineType::RMA)
            ->when(isset($filter['filter_sku']) && $filter['filter_sku'] != '', function (Builder $query) use ($filter) {
                return $query->where(function (Builder $query) use ($filter) {
                    return $query->whereRaw('instr(p.sku, ?)', [$filter['filter_sku']])
                        ->orWhereRaw('instr(p.mpn, ?)', [$filter['filter_sku']]);
                });
            })
            ->when(isset($filter['filter_batch_no']) && $filter['filter_batch_no'] != '', function (Builder $query) use ($filter) {
                return $query->whereRaw('instr(yro.rma_order_id, ?)', [$filter['filter_batch_no']]);
            })
            ->when(! empty($filter['filter_create_start_date']), function (Builder $query) use ($filter) {
                return $query->where('sdl.CreateTime', '>=', $filter['filter_create_start_date']);
            })
            ->when(! empty($filter['filter_create_end_date']), function (Builder $query) use ($filter) {
                return $query->where('sdl.CreateTime', '<=', $filter['filter_create_end_date']);
            });
        // 其他调整类型
        $sqlObj = SellerDeliveryLine::query()->alias('sdl')
            ->leftJoin('oc_product as p', 'sdl.product_id', '=', 'p.product_id')
            ->selectRaw('sdl.batch_id,sdl.product_id,p.sku,p.mpn,sdl.order_id as batch_number,sdl.type as transaction_type,sdl.UpdateTime as receive_date,sdl.CreateTime as create_time,sdl.rma_id,sdl.qty as original_qty,"inout" as onhand_qty,sdl.buyer_id as common_one,sdl.Memo as common_two')
            ->when($type == 2, function (Builder $query) {
                return $query->leftJoin('oc_product_description as pd', 'sdl.product_id', '=', 'pd.product_id')->selectRaw('pd.name');
            })
            ->where('sdl.seller_id', $customerId)
            ->whereIn('sdl.type', SellerDeliveryLineType::getOtherType())
            ->when(isset($filter['filter_sku']) && $filter['filter_sku'] != '', function (Builder $query) use ($filter) {
                return $query->where(function (Builder $query) use ($filter) {
                    return $query->whereRaw('instr(p.sku, ?)', [$filter['filter_sku']])
                        ->orWhereRaw('instr(p.mpn, ?)', [$filter['filter_sku']]);
                });
            })
            ->when((isset($filter['filter_batch_no']) && $filter['filter_batch_no'] != ''), function (Builder $query) use ($filter) {
                return $query->where(function (Builder $query) use ($filter) {
                    return $query->where(function (Builder $query) use ($filter) {
                        return $query->where('sdl.type', SellerDeliveryLineType::PURCHASE_ORDER)
                            ->whereRaw('instr(sdl.order_id, ?)', $filter['filter_batch_no']);
                    })->orWhere(function (Builder $query) use ($filter) {
                        return $query->where('sdl.type', SellerDeliveryLineType::OTHER)
                            ->whereRaw('instr(sdl.Memo, ?)', $filter['filter_batch_no']);
                    });
                });
            })
            ->when(! empty($filter['filter_create_start_date']), function (Builder $query) use ($filter) {
                return $query->where('sdl.CreateTime', '>=', $filter['filter_create_start_date']);
            })
            ->when(! empty($filter['filter_create_end_date']), function (Builder $query) use ($filter) {
                return $query->where('sdl.CreateTime', '<=', $filter['filter_create_end_date']);
            });

        // 存在类型筛选 - 以类型筛选优先
        if (in_array($filter['filter_type'], SellerDeliveryLineType::getChangeType())) {
            return [$sqlAdjObj->where('sdl.type', $filter['filter_type'])];
        } elseif ($filter['filter_type'] == SellerDeliveryLineType::RMA) {
            return [$sqlRmaObj];
        } elseif (in_array($filter['filter_type'], SellerDeliveryLineType::getOtherType())) {
            return [$sqlObj->where('sdl.type', $filter['filter_type'])];
        }

        return [$sqlObj,$sqlRmaObj,$sqlAdjObj];
    }

    /**
     * 获取Seller出库 - 总数
     *
     * @param int $customerId
     * @param array $filter
     * @return int
     */
    public function getInoutRepositoryCount(int $customerId, array $filter)
    {
        return $this->getInoutRepositoryDeal($customerId, $filter)->count();
    }

    /**
     * 获取Seller出库 - 列表
     *
     * @param int $customerId
     * @param array $filter
     * @param int $type 1:获取展示列表数据  2：获取下载列表数据
     * @return Collection
     */
    public function getInoutRepositoryList(int $customerId, array $filter, $type = 1)
    {
        $sqlBuilder = $this->getInoutRepositoryDeal($customerId, $filter, $type);
        return $sqlBuilder->orderBy('create_time', 'desc')->orderBy('product_id')->forPage($filter['page'], $filter['pageLimit'])->get();
    }

    /**
     * 入出库查询
     *
     * @param int $customerId 用户ID
     * @param array $filter 过滤条件
     * @param int $type 1:获取展示列表数据  2：获取下载列表数据
     * @return Builder
     */
    private function getInoutRepositoryDeal(int $customerId, array $filter, int $type = 1)
    {
        if ($filter['filter_cate'] == self::IN_INVENTORY) { // 入库筛选
            return $this->getBatchRepositoryBuilder($customerId, $filter, $type, 1);
        }

        $builderArr =  $this->getInoutRepositoryBulider($customerId, $filter, $type);
        if ($filter['filter_cate'] == self::OUT_INVENTORY) { // 出库筛选
            $builder = array_shift($builderArr);
        } else {
            $builder = $this->getBatchRepositoryBuilder($customerId, $filter, $type, 1);
        }

        foreach ($builderArr as $value) {
            $builder->unionAll($value);
        }

        return $builder;
    }

}