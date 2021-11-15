<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Class sales_order_product_lock
 *
 * @property ModelCommonProduct $model_common_product
 */
class ModelCatalogSalesOrderProductLock extends Model
{
    const TYPE_ID = 5;

    const LOCK_TAIL_GENERATE = 0;  // 纯物流订单锁定库存
    const LOCK_TAIL_PURCHASE = 1;  // 纯物流订单购买释放库存
    const LOCK_TAIL_CANCEL = 2;    // 纯物流订单取消释放库存

    /**
     * 销售订单导单锁定库存
     * @param int $sales_order_id 销售订单id
     * @throws Exception
     */
    public function TailSalesOrderIn(int $sales_order_id)
    {
        $this->orm->table('tb_sys_customer_sales_order_line')
            ->where('header_id', $sales_order_id)
            ->get()
            ->each(function ($item) {
                $this->TailIn($item->id, $item->qty, $item->header_id, static::LOCK_TAIL_GENERATE);
            });
    }

    /**
     * 销售订单导单释放锁定库存
     * @param int $sales_order_id 销售订单id
     * @param int $type 类型
     * @throws Exception
     */
    public function TailSalesOrderOut(int $sales_order_id, int $type)
    {
        $this->orm->table('tb_sys_customer_sales_order_line')
            ->where('header_id', $sales_order_id)
            ->get()
            ->each(function ($item) use ($type) {
                $this->TailOut($item->id, $item->qty, $item->header_id, $type);
            });
    }

    /**
     * @param int $agreement_id | 销售订单明细id tb_sys_customer_sales_order_line id字段
     * @param int $qty | 数量
     * @param $transaction_id | 交易id
     * @param $type | 类型
     * @throws Exception
     */
    public function TailIn($agreement_id, $qty, $transaction_id, $type)
    {
        if (!in_array($type, [static::LOCK_TAIL_GENERATE,])) {
            throw new Exception('Error change type:' . $type);
        }
        $this->TailResolve(...func_get_args());
    }

    /**
     * @param int $agreement_id | 销售订单明细id tb_sys_customer_sales_order_line id字段
     * @param int $qty | 数量
     * @param $transaction_id | 交易id
     * @param $type | 类型
     * @throws Exception
     */
    public function TailOut($agreement_id, $qty, $transaction_id, $type)
    {
        if (!in_array($type, [static::LOCK_TAIL_PURCHASE, static::LOCK_TAIL_CANCEL,])) {
            throw new Exception('Error change type:' . $type);
        }
        $this->tailResolve($agreement_id, -$qty, $transaction_id, $type);
    }

    /**
     * 获取产品的锁定库存
     * @param int $product_id 产品id
     * @param int|null $agreement_id 可选参数 不传参数会获取全部锁定库存 传了 只会获取对应协议的
     * ps:tb_sys_margin_agreement 主键id
     * @param array $excludeProductIds
     * @return null|int
     */
    public function getProductSalesOrderQty(
        int $product_id,
        $agreement_id = null,
        array $excludeProductIds = []
    )
    {
        $num = null;
        $res = $this->orm
            ->table('oc_product_lock as pl')
            ->leftJoin('tb_sys_customer_sales_order_line as l', 'l.id', '=', 'pl.agreement_id')
            ->select(['pl.*'])
            ->where(function (Builder $q) use ($product_id) {
                $q->orWhere('pl.parent_product_id', $product_id);
                $q->orWhere('pl.product_id', $product_id);
            })
            ->where('pl.type_id', static::TYPE_ID)
            ->where('pl.is_ignore_qty', 0)
            ->groupBy(['pl.agreement_id'])
            ->when(!empty($agreement_id), function (Builder $q) use ($agreement_id) {
                return $q->where('pl.agreement_id', $agreement_id);
            })
            ->when(!empty($excludeProductIds), function (Builder $q) use ($excludeProductIds) {
                $q->whereNotIn('pl.parent_product_id', $excludeProductIds);
                $q->whereNotIn('pl.product_id', $excludeProductIds);
            })
            ->get();
        if ($res->isNotEmpty()) {
            $res->each(function ($item) use (&$num, $product_id) {
                $item = (array)$item;
                if ($item['product_id'] != $item['parent_product_id']) {
                    if ($item['product_id'] == $product_id) {
                        $num = (int)$num + $item['qty'];
                    }
                    if ($item['parent_product_id'] == $product_id) {
                        $num = (int)$num + ($item['qty'] / $item['set_qty']);
                    }
                } else {
                    $num = (int)$num + $item['qty'];
                }
            });
        }

        return $num;
    }

    /**
     * 获取产品计算得到的锁定库存
     * @param int $product_id
     * @return int
     * @throws Exception
     */
    public function getProductSalesOrderComputeQty(int $product_id): int
    {
        $sales_order_quantity = (int)$this->getProductSalesOrderQty($product_id);
        $this->load->model('common/product');
        $compute_qty = [];
        $combo_info = $this->model_common_product->getComboProduct($product_id);
        array_map(function ($item) use ($product_id, &$compute_qty) {
            $real_sales_order_qty = (int)$this->getProductSalesOrderQty($item['product_id'], null, [$product_id]);
            $compute_qty[] = (int)ceil($real_sales_order_qty / $item['qty']);
        }, $combo_info);

        return $sales_order_quantity + (!empty($compute_qty) ? max($compute_qty) : 0);
    }

    /**
     * @param $agreement_id
     * @param int $qty
     * @param $transaction_id
     * @param $type
     * @throws Exception
     */
    private function tailResolve($agreement_id, $qty, $transaction_id, $type)
    {
        if (in_array((int)$type, [static::LOCK_TAIL_GENERATE,])) { // 对于生成锁定库存单独处理
            $this->tailResolveGen(...func_get_args());
        } else {
            $this->tailResolveNormal(...func_get_args());
        }
    }

    /**
     * @param $agreement_id
     * @param int $qty
     * @param $transaction_id
     * @param $type
     * @throws Exception
     */
    private function tailResolveNormal($agreement_id, $qty, $transaction_id, $type)
    {
        $product_info = $this->getOrigProductInfoByAgreementId((int)$agreement_id);
        // 判断有没有对应协议的库存记录 如果没有 抛出异常
        $product_lock_info = $this->getProductLockInfo($agreement_id, (int)$product_info['product_id']);
        if (empty($product_lock_info)) {
            throw new Exception('Can not resolve product lock.it may do not have product lock.');
        }
        $con = $this->orm->getConnection();
        try {
            $con->beginTransaction();
            // 是否需要更新锁定库存
            $needUpdateProductLock = true;
            $insertLogArr = [];
            if ($needUpdateProductLock) {
                foreach ($product_lock_info as $p_lock) {
                    // 不需要讨论是否为combo品 本身的 oc_product_lock中已经包含combo信息
                    $r_qty = $qty * ($p_lock['set_qty'] ?? 1); // 实际要增加或减少的库存
                    $p_resolve_lock_qty = (int)($p_lock['qty'] + $r_qty);
                    // 结果不能小于0 也不能大于原始的锁定库存
                    if ($p_resolve_lock_qty < 0 || $p_resolve_lock_qty > $p_lock['origin_qty']) {
                        throw new Exception('invalid resolve qty:' . $p_resolve_lock_qty);
                    }
                    // 更新product lock
                    $con->table('oc_product_lock')
                        ->where('id', $p_lock['id'])
                        ->update([
                            'qty' => $p_resolve_lock_qty,
                            'update_user_name' => $this->customer->getId(),
                            'update_time' => Carbon::now(),
                        ]);
                    // 纯物流需要删除这边相应的数据
                    $con->table('oc_product_lock')->where('id', $p_lock['id'])->delete();
                    $insertLogArr[] = [
                        'product_lock_id' => $p_lock['id'],
                        'qty' => $r_qty,
                        'change_type' => $type,
                        'transaction_id' => $transaction_id,
                        'create_user_name' => $this->customer->getId(),
                        'create_time' => Carbon::now(),
                        'memo' => "line id:{$p_lock['agreement_id']}"
                    ];
                }
                // 写入product_lock_log
                $con->table('oc_product_lock_log')->insert($insertLogArr);
            }
            $con->commit();
        } catch (Exception $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * 对于生成产品lock库存单独处理
     * @param $agreement_id
     * @param int $qty
     * @param $transaction_id
     * @param $type
     * @throws Exception
     */
    private function tailResolveGen($agreement_id, $qty, $transaction_id, $type)
    {
        $product_info = $this->getOrigProductInfoByAgreementId((int)$agreement_id);
        // 判断有没有对应协议的库存记录 如果有 说明已经有对应记录 抛出异常
        $product_id = (int)$product_info['product_id'];
        $seller_id = (int)$product_info['seller_id'];
        $product_lock_info = $this->getProductLockInfo($agreement_id, $product_id);
        if (!empty($product_lock_info)) {
            throw new Exception(
                "Line id:{$agreement_id} Can not generate product lock.it may already have product lock."
            );
        }
        $con = $this->orm->getConnection();
        try {
            $con->beginTransaction();
            $insertLogArr = []; // oc_product_lock_log
            // combo品
            if ($product_info['combo_flag']) {
                $combos = $this->getComboInfo($product_id);
                foreach ($combos as $item) {
                    $r_qty = $item['qty'] * $qty;
                    $insertArr = [
                        'product_id' => $item['set_product_id'],
                        'seller_id' => $seller_id,
                        'agreement_id' => $agreement_id,
                        'type_id' => static::TYPE_ID,
                        'origin_qty' => $r_qty,
                        'qty' => $r_qty,
                        'parent_product_id' => $product_id,
                        'set_qty' => $item['qty'],
                        'memo' => 'sales order lock',
                        'create_user_name' => $this->customer->getId(),
                        'create_time' => Carbon::now(),
                        'update_user_name' => $this->customer->getId(),
                        'update_time' => Carbon::now(),
                        'is_ignore_qty' => $this->customer->isGigaOnsiteSeller() ? 1 : 0,
                    ];
                    $product_lock_id = $con->table('oc_product_lock')->insertGetId($insertArr);
                    $insertLogArr[] = [
                        'product_lock_id' => $product_lock_id,
                        'qty' => $r_qty,
                        'change_type' => $type,
                        'transaction_id' => $transaction_id,
                        'create_user_name' => $this->customer->getId(),
                        'create_time' => Carbon::now(),
                        'memo' => "line id:{$agreement_id}"
                    ];
                }
            } else {
                $insertArr = [
                    'product_id' => $product_id,
                    'seller_id' => $seller_id,
                    'agreement_id' => $agreement_id,
                    'type_id' => static::TYPE_ID,
                    'origin_qty' => $qty,
                    'qty' => $qty,
                    'parent_product_id' => $product_id,
                    'set_qty' => 1,
                    'memo' => 'sales order lock',
                    'create_user_name' => $this->customer->getId(),
                    'create_time' => Carbon::now(),
                    'update_user_name' => $this->customer->getId(),
                    'update_time' => Carbon::now(),
                    'is_ignore_qty' => $this->customer->isGigaOnsiteSeller() ? 1 : 0,
                ];
                $product_lock_id = $con->table('oc_product_lock')->insertGetId($insertArr);
                $insertLogArr[] = [
                    'product_lock_id' => $product_lock_id,
                    'qty' => $qty,
                    'change_type' => $type,
                    'transaction_id' => $transaction_id,
                    'create_user_name' => $this->customer->getId(),
                    'create_time' => Carbon::now(),
                    'memo' => "line id:{$agreement_id}"
                ];
            }
            // 写入product_lock_log
            $con->table('oc_product_lock_log')->insert($insertLogArr);
            // 同步抹平产品上架库存
            $this->load->model('common/product');
            $this->model_common_product->updateProductOnShelfQuantity((int)$product_info['product_id']);
            $con->commit();
        } catch (Exception $e) {
            $con->rollBack();
            throw $e;// 依旧抛出异常 供调用代码处理
        }
    }

    /**
     * 获取协议对应的商品信息
     * @param int $agreement_id
     * @return array
     * @throws Exception
     */
    public function getOrigProductInfoByAgreementId(int $agreement_id): array
    {
        $res = $this->orm
            ->table('tb_sys_customer_sales_order_line as l')
            ->select(['p.product_id', 'p.combo_flag', 'l.qty', 'l.create_user_name as seller_id',])
            ->join('oc_product as p', 'p.product_id', '=', 'l.product_id')
            ->join('oc_product_description as pd', 'p.product_id', '=', 'pd.product_id')
            ->where('l.id', $agreement_id)
            ->first();
        if (!$res || empty($res->product_id)) {
            throw new Exception("Line id:{$agreement_id} can not find relate sales order line info.");
        }
        return (array)$res;
    }

    /**
     * 获取对应产品的锁定库存信息
     * @param int $agreement_id
     * @param int $product_id
     * @return array
     */
    public function getProductLockInfo(int $agreement_id, int $product_id)
    {
        return $this->orm
            ->table('oc_product_lock')
            ->where([
                'parent_product_id' => $product_id,
                'agreement_id' => $agreement_id,
                'type_id' => static::TYPE_ID,
            ])
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
    }

    /**
     * 获取combo品对应子产品信息
     * @param int $product_id
     * @return array
     */
    public function getComboInfo(int $product_id): array
    {
        return $this->orm->table('tb_sys_product_set_info as s')
            ->where('p.product_id', $product_id)
            ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 's.product_id')
            ->leftJoin(DB_PREFIX . 'product as pc', 'pc.product_id', '=', 's.set_product_id')
            ->whereNotNull('s.set_product_id')
            ->select('p.product_id', 's.set_product_id', 's.qty', 'pc.sku')
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
    }
}
