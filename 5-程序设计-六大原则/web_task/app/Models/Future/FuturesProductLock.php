<?php

namespace App\Models\Future;

use App\Models\Product\Product;
use App\Models\Product\ProductSetInfo;
use DB;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

class FuturesProductLock extends Model
{
    const LOCK_TAIL_GENERATE = 0; // 期货保证金尾款产品生成 type
    const LOCK_TAIL_PURCHASE = 1; // 期货尾款产品购买
    const LOCK_TAIL_RMA = 2;   // 期货尾款产品rma
    const LOCK_TAIL_CANCEL = 3; // 期货尾款协议取消
    const LOCK_TAIL_TIMEOUT = 4; // 期货尾款协议超时
    const LOCK_TAIL_INTERRUPT = 5; // 期货尾款协议终止
    const LOCK_TAIL_TRANSFER = 6;  // 期货协议转现货保证金协议
    const LOCK_TAIL_ORDER_TIMEOUT = 7; // 期货尾款订单超时未支付

    /**
     * @param $agreement_id | 期货保证金id oc_futures_margin_agreement id字段
     * @param $qty | 数量
     * @param $transaction_id | 交易id
     * @param $type | 类型
     * @throws Exception
     */
    public function TailIn($agreement_id, $qty, $transaction_id, $type)
    {
        if (!in_array($type, [static::LOCK_TAIL_GENERATE, static::LOCK_TAIL_RMA, static::LOCK_TAIL_ORDER_TIMEOUT])) {
            throw new Exception('Error change type:' . $type);
        }
        $this->TailResolve(...func_get_args());
    }

    /**
     * @param $agreement_id | 期货保证金id oc_futures_margin_agreement id字段
     * @param $qty | 数量
     * @param $transaction_id | 交易id
     * @param $type | 类型
     * @throws Exception
     */
    public function TailOut($agreement_id, $qty, $transaction_id, $type)
    {
        if (!in_array(
            $type,
            [
                static::LOCK_TAIL_PURCHASE,
                static::LOCK_TAIL_CANCEL,
                static::LOCK_TAIL_TIMEOUT,
                static::LOCK_TAIL_INTERRUPT,
                static::LOCK_TAIL_TRANSFER,
            ]
        )) {
            throw new Exception('Error change type:' . $type);
        }
        $this->tailResolve($agreement_id, -$qty, $transaction_id, $type);
    }

    /**
     * @param $agreement_id
     * @param $qty
     * @param $transaction_id
     * @param $type
     * @throws Exception
     */
    private function tailResolve($agreement_id, $qty, $transaction_id, $type)
    {
        switch ((int)$type) {
            case static::LOCK_TAIL_GENERATE :
            {
                $this->tailResolveGen(...func_get_args());
                break;
            }
            case static::LOCK_TAIL_TRANSFER:
            {
                // 先出掉期货锁定库存
                $this->tailResolveNormal(...func_get_args());
                // 生成现货锁定库存
                $delivery_info = $this->getFuturesMarginDeliveryInfo($agreement_id);
                if (!$delivery_info) {
                    throw new Exception(
                        'Can not find futures delivery info about agreement: ' . $agreement_id
                    );
                }
                $this->TailIn(
                    $delivery_info['margin_agreement_id'],
                    $delivery_info['margin_apply_num'],
                    $transaction_id,
                    static::LOCK_TAIL_TRANSFER
                );
                break;
            }
            default:
            {
                $this->tailResolveNormal(...func_get_args());
            }
        }
    }

    /**
     * 获取产品的期货锁定库存
     * @param int $product_id 产品id
     * @param int|null $agreement_id 可选参数 不传参数会获取全部锁定库存 传了 只会获取对应协议的
     * ps:oc_futures_margin_agreement 主键id
     * @param array $excludeProductIds
     * @return null|int
     */
    public function getProductFuturesQty(
        int $product_id,
        $agreement_id = null,
        array $excludeProductIds = []
    )
    {
        $num = null;
        DB::connection('mysql_proxy')->statement("SET sql_mode = '' ");
        DB::connection('mysql_proxy')
            ->table('oc_product_lock as pl')
            ->leftJoin('oc_futures_margin_agreement as fma', 'fma.id', '=', 'pl.agreement_id')
            ->select(['pl.*'])
            ->where(function (Builder $q) use ($product_id) {
                $q->orWhere('pl.parent_product_id', $product_id);
                $q->orWhere('pl.product_id', $product_id);
            })
            ->where(['pl.type_id' => 3])
            ->groupBy(['pl.agreement_id'])
            ->when(!empty($agreement_id), function (Builder $q) use ($agreement_id) {
                return $q->where('pl.agreement_id', $agreement_id);
            })
            ->when(!empty($excludeProductIds), function (Builder $q) use ($excludeProductIds) {
                $q->whereNotIn('pl.parent_product_id', $excludeProductIds);
                $q->whereNotIn('pl.product_id', $excludeProductIds);
            })
            ->get()
            ->each(function ($item) use (&$num, $product_id) {
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

        return $num;
    }

    /**
     * 获取产品计算得到的锁定库存
     * @param int $product_id
     * @return int
     */
    public function getProductFuturesComputeQty(int $product_id): int
    {
        $quantity = (int)$this->getProductFuturesQty($product_id);
        $compute_qty = [];
        $combo_info = Product::find($product_id)->comboProducts;
        $combo_info->each(function (ProductSetInfo $item) use ($product_id, &$compute_qty) {
            $real_margin_qty = (int)$this->getProductFuturesQty($item->product_id, null, [$product_id]);
            $compute_qty[] = (int)ceil($real_margin_qty / $item->qty);
        });

        return $quantity + (!empty($compute_qty) ? max($compute_qty) : 0);
    }

    /**
     * 对于生成产品lock库存单独处理
     * @param $agreement_id
     * @param $qty
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
                "Agreement Id:{$agreement_id} Can not generate product lock.it may already have product lock."
            );
        }
        try {
            DB::beginTransaction();
            $insertLogArr = []; // oc_product_lock_log
            // combo品
            if ($product_info['combo_flag']) {
                $combos = $this->getComboInfo($product_id);
                foreach ($combos as $item) {
                    $r_qty = $item['qty'] * $qty; // 对于子产品实际上架库存
                    $insertArr = [
                        'product_id' => $item['set_product_id'],
                        'seller_id' => $seller_id,
                        'agreement_id' => $agreement_id,
                        'type_id' => 3, // hard code 标志位期货
                        'origin_qty' => $r_qty,
                        'qty' => $r_qty,
                        'parent_product_id' => $product_id,
                        'set_qty' => $item['qty'],
                        'memo' => 'futures lock',
                        'create_user_name' => 'yzc task work',
                        'create_time' => Carbon::now(),
                        'update_user_name' => 'yzc task work',
                        'update_time' => Carbon::now(),
                    ];
                    $product_lock_id = DB::table('oc_product_lock')->insertGetId($insertArr);
                    $insertLogArr[] = [
                        'product_lock_id' => $product_lock_id,
                        'qty' => $r_qty,
                        'change_type' => $type,
                        'transaction_id' => $transaction_id,
                        'create_user_name' => 'yzc task work',
                        'create_time' => Carbon::now(),
                    ];
                    // 同步磨平子产品上架库存
                    $this->updateOnShelfQty($item['set_product_id']);
                }
            } else {
                $insertArr = [
                    'product_id' => $product_id,
                    'seller_id' => $seller_id,
                    'agreement_id' => $agreement_id,
                    'type_id' => 3, // hard code 标志位期货保证金
                    'origin_qty' => $qty,
                    'qty' => $qty,
                    'parent_product_id' => $product_id,
                    'set_qty' => 1,
                    'memo' => 'futures lock',
                    'create_user_name' => 'yzc task work',
                    'create_time' => Carbon::now(),
                    'update_user_name' => 'yzc task work',
                    'update_time' => Carbon::now(),
                ];
                $product_lock_id = DB::table('oc_product_lock')->insertGetId($insertArr);
                $insertLogArr[] = [
                    'product_lock_id' => $product_lock_id,
                    'qty' => $qty,
                    'change_type' => $type,
                    'transaction_id' => $transaction_id,
                    'create_user_name' => 'yzc task work',
                    'create_time' => Carbon::now(),
                ];
            }
            // 写入product_lock_log
            DB::table('oc_product_lock_log')->insert($insertLogArr);
            // 同步抹平产品上架库存
            $this->updateOnShelfQty($product_id);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;// 依旧抛出异常 供调用代码处理
        }
    }

    /**
     * @param $agreement_id
     * @param $qty
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
            throw new Exception(
                "Agreement Id:{$agreement_id} Can not resolve product lock.it may do not have product lock."
            );
        }
        try {
            DB::beginTransaction();
            // 是否需要更新锁定库存
            $needUpdateProductLock = $this->checkNeedUpdateProductLock((int)$agreement_id, (int)$type);
            // 是否需要更新元商品库存
            $needUpdateProductQty = $this->checkNeedUpdateOrigProduct((int)$agreement_id, (int)$type);
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
                    DB::table('oc_product_lock')
                        ->where('id', $p_lock['id'])
                        ->update([
                            'qty' => $p_resolve_lock_qty,
                            'update_user_name' => 'yzc task work',
                            'update_time' => Carbon::now(),
                        ]);
                    $insertLogArr[] = [
                        'product_lock_id' => $p_lock['id'],
                        'qty' => $r_qty,
                        'change_type' => $type,
                        'transaction_id' => $transaction_id,
                        'create_user_name' => 'yzc task work',
                        'create_time' => Carbon::now(),
                    ];
                }
                // 写入product_lock_log
                DB::table('oc_product_lock_log')->insert($insertLogArr);
            }
            if ($needUpdateProductQty) {
                // qty不是单纯的正和负 需要根据不同的情况下处理
                if (in_array($type, [static::LOCK_TAIL_PURCHASE, static::LOCK_TAIL_GENERATE])) {
                    // 理论上永远不会进入这个分支
                    $ro_qty = 0 - abs($qty);
                } else {
                    $ro_qty = abs($qty);
                }
                foreach ($product_lock_info as $p_lock) {
                    $r_qty = $ro_qty * ($p_lock['set_qty'] ?? 1);
                    // 更新上架库存
                    $this->updateProductQuantity((int)$p_lock['product_id'], $r_qty);
                }
                if ($product_info['combo_flag'] == 1) {
                    // 如果为子产品 同时也得更新父产品的上架库存
                    $this->updateProductQuantity((int)$product_info['product_id'], $ro_qty);
                }
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


    /**
     * 是否需要更新元商品上架库存
     * 现在大概只有1种情况需要更新上架库存
     * 1: 保证金申请rma 但是保证金协议已经失效 此时只需要更新上架库存 至于批次表不在这里处理
     *
     * 特殊情况:内部seller永远不会更新上架库存
     * @param int $agreement_id
     * @param int $type
     * @return bool
     * @throws Exception
     */
    private function checkNeedUpdateOrigProduct(int $agreement_id, int $type): bool
    {
        return false;
    }

    /**
     * 是否需要更新锁定库存
     * 大部分情况需要更新锁定库存 目前大概只有一种不需要更新
     * 1: 保证金申请rma 但是保证金协议失效 此时不需要更新锁定库存
     * @param int $agreement_id
     * @param int $type
     * @return bool
     * @throws Exception
     */
    private function checkNeedUpdateProductLock(int $agreement_id, int $type): bool
    {
        $ret = true;
        if (
            in_array($type, [static::LOCK_TAIL_RMA, static::LOCK_TAIL_ORDER_TIMEOUT])
            && !$this->checkAgreementIsValid($agreement_id)
        ) {
            $ret = false;
        }
        return $ret;
    }

    /**
     * 校验期货保证金协议是否有效
     * 有效
     * @param int $agreement_id
     * @return bool
     * @throws Exception
     */
    private function checkAgreementIsValid(int $agreement_id): bool
    {
        $agree_info = $this->getOrigProductInfoByAgreementId($agreement_id);
        $ret = false;
        if (
            in_array($agree_info['status'], [6, 8])
            && (strtotime($agree_info['confirm_delivery_date'] . ' +30 days') > time())
        ) {
            $ret = true;
        }

        return $ret;
    }

    /**
     * 校验协议的seller是否为内部用户
     * @param int $agreement_id
     * @return bool
     * user：wangjinxin
     * date：2020/3/31 20:05
     */
    private function checkAgreeSellerIsInner(int $agreement_id): bool
    {
        $row = DB::table('oc_futures_margin_agreement as fma')
            ->leftJoin('oc_customer as c', 'fma.seller_id', '=', 'c.customer_id')
            ->where('fma.id', $agreement_id)
            ->first();

        return $row->accounting_type == 1;
    }


    /**
     * 获取对应期货产品的锁定库存信息
     * @param int $agreement_id
     * @param int $product_id
     * @return array
     */
    public function getProductLockInfo(int $agreement_id, int $product_id)
    {
        return DB::table('oc_product_lock')
            ->where([
                'parent_product_id' => $product_id,
                'agreement_id' => $agreement_id,
                'type_id' => 3,
            ])
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
    }

    /**
     * 获取期货协议对应的商品信息
     * @param int $agreement_id
     * @return array
     * @throws Exception
     */
    public function getOrigProductInfoByAgreementId(int $agreement_id): array
    {
        $res = DB::table('oc_futures_margin_agreement as fma')
            ->select([
                'p.product_id', 'p.combo_flag', 'fma.num as qty', 'fma.seller_id',
                'fmd.delivery_status as status', 'fma.agreement_no', 'fmd.confirm_delivery_date',
            ])
            ->join('oc_futures_margin_delivery as fmd', 'fmd.agreement_id', '=', 'fma.id')
            ->join('oc_product as p', 'p.product_id', '=', 'fma.product_id')
            ->join('oc_product_description as pd', 'p.product_id', '=', 'pd.product_id')
            ->where('fma.id', $agreement_id)
            ->first();
        if (!$res) {
            throw new Exception('can not find relate agreement.');
        }
        return (array)$res;
    }

    /**
     * 获取combo品对应子产品信息
     * @param int $product_id
     * @return array
     */
    private function getComboInfo(int $product_id): array
    {
        return DB::connection('mysql_proxy')
            ->table('tb_sys_product_set_info as s')
            ->where('p.product_id', $product_id)
            ->leftJoin('oc_product as p', 'p.product_id', '=', 's.product_id')
            ->leftJoin('oc_product as pc', 'pc.product_id', '=', 's.set_product_id')
            ->whereNotNull('s.set_product_id')
            ->select('p.product_id', 's.set_product_id', 's.qty', 'pc.sku')
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
    }


    /**
     * @param int $agreement_id
     * @return array|null
     * user：wangjinxin
     * date：2020/4/6 13:31
     */
    private function getFuturesMarginDeliveryInfo(int $agreement_id)
    {
        $ret = DB::table('oc_futures_margin_delivery')
            ->where('agreement_id', $agreement_id)
            ->first();
        return $ret ? (array)$ret : null;
    }

    /**
     * 期货锁定库存时候 需要适当调整上架库存
     * 举个例子：在库 30 上架35 需要将上架调整为30
     * @param int $product_id
     * @throws Exception
     */
    private function updateOnShelfQty(int $product_id)
    {
        /** @var Product $product */
        $product = Product::findOrFail($product_id);
        // 在库库存
        $in_stock_qty = $product->getInStockQuantity();
        // 计算后的锁定库存
        $lock_qty = $product->getComputeLockQty();
        // 上架库存·
        $on_shelf_qty = $product->quantity;
        // 上架库存 大于 （在库库存 - 理论锁定库存）
        if ($on_shelf_qty > $in_stock_qty - $lock_qty) {
            $available_qty = $in_stock_qty - $lock_qty;
            if ($available_qty < 0) {
                throw new Exception("Error:Product id({$product_id}) product quantity will be lower than 0.");
            }
            DB::table('oc_product')
                ->where('product_id', $product_id)
                ->update(['quantity' => $available_qty]);
            DB::table('oc_customerpartner_to_product')
                ->where('product_id', $product_id)
                ->update(['quantity' => $available_qty]);
        }
        //如果该产品是combo品的子产品 同步修改对应的combo品
        DB::table('tb_sys_product_set_info')
            ->where('set_product_id', $product_id)
            ->pluck('product_id')
            ->each(function ($id) {
                $this->updateOnShelfQty($id);
            });
    }

    /**
     * 更新上架库存 ps：不会校验上架库存和在库是否一致 但会校验结果库存是否大于0
     * @param int $product_id 商品id
     * @param int $qty 数量 有正负 正数：添加库存 负数：扣减库存
     * @throws Exception
     */
    private function updateProductQuantity(int $product_id, int $qty)
    {
        // 获取原有库存
        /** @var Product $product */
        $product = Product::findOrFail($product_id);
        $p_compute_qty = (int)($product->quantity) + $qty;
        DB::table('oc_product')
            ->where('product_id', $product_id)
            ->update(['quantity' => $p_compute_qty]);
        $ctp_info = DB::table('oc_customerpartner_to_product')
            ->where('product_id', $product_id)
            ->first();
        $ctp_compute_qty = (int)$ctp_info->quantity + $qty;
        DB::table('oc_customerpartner_to_product')
            ->where('product_id', $product_id)
            ->update(['quantity' => $ctp_compute_qty]);
    }

}