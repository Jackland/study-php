<?php

namespace App\Models\Margin;

use App\Models\Product\Product;
use App\Models\Product\ProductSetInfo;
use DB;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

class ProductLock extends Model
{
    public $timestamps = false;
    protected $table = 'oc_product_lock';
    protected $primaryKey = 'id';
    const LOCK_TAIL_GENERATE = 0; // 保证金尾款产品生成 type
    const LOCK_TAIL_PURCHASE = 1; // 尾款产品购买
    const LOCK_TAIL_RMA = 2;   // 尾款产品rma
    const LOCK_TAIL_CANCEL = 3; // 协议取消
    const LOCK_TAIL_TIMEOUT = 4; // 协议超时
    const LOCK_TAIL_INTERRUPT = 5; // 尾款协议终止
    const LOCK_TAIL_TRANSFER = 6; // 期货保证金 转现货保证金
    const USER_NAME = 'yzc_task_work';

    /**
     * 保证金协议产品入库
     * 下列情况：0-保证金产品生成 2-尾款产品rma
     * @param $agreement_id
     * @param $qty
     * @param $transaction_id
     * @param $type
     * @throws Exception
     */
    public static function TailIn($agreement_id, $qty, $transaction_id, $type)
    {
        if (!in_array($type, [static::LOCK_TAIL_GENERATE, static::LOCK_TAIL_RMA, static::LOCK_TAIL_TRANSFER])) {
            throw new Exception('Error change type:' . $type);
        }
        static::TailResolve(...func_get_args());
    }

    /**
     * 保证金产品出库
     * 下列情况：1-保证金产品购买 3-尾款协议取消
     * @param $agreement_id
     * @param $qty
     * @param $transaction_id
     * @param $type
     * @throws Exception
     */
    public static function TailOut($agreement_id, $qty, $transaction_id, $type)
    {
        if (!in_array($type, [static::LOCK_TAIL_PURCHASE, static::LOCK_TAIL_CANCEL, static::LOCK_TAIL_INTERRUPT])) {
            throw new Exception('Error change type:' . $type);
        }
        static::tailResolve($agreement_id, -$qty, $transaction_id, $type);
    }

    /**
     * 获取产品的锁定库存
     * @param int $product_id 产品id
     * @param int|null $agreement_id 可选参数 不传参数会获取全部锁定库存 传了 只会获取对应协议的
     * ps:tb_sys_margin_agreement 主键id
     * @param array $excludeProductIds
     * @return null|int
     */
    public function getProductMarginQty(
        int $product_id,
        $agreement_id = null,
        array $excludeProductIds = []
    )
    {
        $num = null;
        DB::connection('mysql_proxy')->statement("SET sql_mode = '' ");
        $res = DB::connection('mysql_proxy')->table('oc_product_lock as pl')
            ->leftJoin('tb_sys_margin_agreement as sma', 'sma.id', '=', 'pl.agreement_id')
            ->select(['pl.*'])
            ->where(function (Builder $q) use ($product_id) {
                $q->orWhere('pl.parent_product_id', $product_id);
                $q->orWhere('pl.product_id', $product_id);
            })
            // hard code 参见tb_sys_margin_agreement_status
            // 3:approved 6:sold 8:completed 同意和售卖状态计算锁定库存
            ->where('pl.type_id', 2)
            ->whereIn('sma.status', [3, 6, 8])
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
     */
    public function getProductMarginComputeQty(int $product_id): int
    {
        $margin_quantity = (int)$this->getProductMarginQty($product_id);
        $compute_qty = [];
        $combo_info = Product::find($product_id)->comboProducts;
        $combo_info->each(function (ProductSetInfo $item) use ($product_id, &$compute_qty) {
            $real_margin_qty = (int)$this->getProductMarginQty($item->product_id, null, [$product_id]);
            $compute_qty[] = (int)ceil($real_margin_qty / $item->qty);
        });

        return $margin_quantity + (!empty($compute_qty) ? max($compute_qty) : 0);
    }

    /**
     * @param $agreement_id
     * @param $qty
     * @param $transaction_id
     * @param $type
     * @throws Exception
     */
    private static function tailResolve($agreement_id, $qty, $transaction_id, $type)
    {
        if (in_array((int)$type, [static::LOCK_TAIL_GENERATE, static::LOCK_TAIL_TRANSFER])) { // 对于生成锁定库存单独处理
            static::tailResolveGen(...func_get_args());
        } else {
            static::tailResolveNormal(...func_get_args());
        }
    }

    /**
     * @param $agreement_id
     * @param $qty
     * @param $transaction_id
     * @param $type
     * @throws Exception
     */
    private static function tailResolveNormal($agreement_id, $qty, $transaction_id, $type)
    {
        $product_info = static::getOrigProductInfoByAgreementId((int)$agreement_id);
        // 判断有没有对应协议的库存记录 如果没有 抛出异常
        $product_lock_info = static::getProductLockInfo($agreement_id, (int)$product_info['product_id']);
        if (empty($product_lock_info)) {
            throw new Exception('Can not resolve product lock.it may do not have product lock.');
        }
        try {
            DB::beginTransaction();
            // 是否需要更新锁定库存
            $needUpdateProductLock = static::checkNeedUpdateProductLock((int)$agreement_id, (int)$type);
            // 是否需要更新元商品库存
            $needUpdateProductQty = static::checkNeedUpdateOrigProduct((int)$agreement_id, (int)$type);
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
                    static::query()
                        ->where('id', $p_lock['id'])
                        ->update([
                            'qty' => $p_resolve_lock_qty,
                            'update_user_name' => '',
                            'update_time' => Carbon::now(),
                        ]);
                    $insertLogArr[] = [
                        'product_lock_id' => $p_lock['id'],
                        'qty' => $r_qty,
                        'change_type' => $type,
                        'transaction_id' => $transaction_id,
                        'create_user_name' => static::USER_NAME,
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
                    $r_qty = $ro_qty * ($p_lock['set_qty'] ?? 1); // 实际要增加或减少的库存
                    // 更新上架库存
                    static::updateProductQuantity((int)$p_lock['product_id'], $r_qty);
                }
                if ($product_info['combo_flag'] == 1) {
                    // 如果为子产品 同时也得更新父产品的上架库存
                    static::updateProductQuantity((int)$product_info['product_id'], $ro_qty);
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
     * 现在大概有4种情况需要更新上架库存 生成锁定库存另外考虑
     * 1: 保证金申请rma 但是保证金协议已经失效 此时只需要更新上架库存 至于批次表不在这里处理
     * 2: 保证金协议终止 无论是buyer终止还是seller终止
     * 3: 保证金订单未支付且timeout
     * 4: 保证金协议取消
     * @param int $agreement_id
     * @param int $type
     * @return bool
     * @throws Exception
     */
    private static function checkNeedUpdateOrigProduct(int $agreement_id, int $type): bool
    {
        $ret = false;
        if ($type == static::LOCK_TAIL_RMA && !static::checkAgreementIsValid($agreement_id)) {
            $ret = true;
        }
        if (in_array($type, [static::LOCK_TAIL_TIMEOUT, static::LOCK_TAIL_CANCEL, static::LOCK_TAIL_INTERRUPT,])) {
            $ret = true;
        }

        if (static::checkAgreeSellerIsInner($agreement_id)) {
            $ret = false;
        }

        return $ret;
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
    private static function checkNeedUpdateProductLock(int $agreement_id, int $type): bool
    {
        $ret = true;
        if ($type == self::LOCK_TAIL_RMA && !static::checkAgreementIsValid($agreement_id)) {
            $ret = false;
        }
        return $ret;
    }

    /**
     * 校验协议的seller是否为内部用户
     * @param int $agreementId
     * @return bool
     */
    private static function checkAgreeSellerIsInner(int $agreementId): bool
    {
        $accountingType = DB::table('oc_customer as c')
            ->join('tb_sys_margin_agreement as a', 'c.customer_id', '=', 'a.seller_id')
            ->where('a.id', $agreementId)
            ->value('accounting_type');

        return $accountingType == 1;
    }


    /**
     * 校验保证金协议是否有效
     * 有效：协议状态为6:sold 8:completed  且协议到期时间还没有到
     * @param int $agreement_id
     * @return bool
     * @throws Exception
     */
    private static function checkAgreementIsValid(int $agreement_id): bool
    {
        $agree_info = static::getOrigProductInfoByAgreementId($agreement_id);
        $ret = false;
        if (in_array($agree_info['status'], [6, 8]) && (strtotime($agree_info['expire_time']) > time())) {
            $ret = true;
        }

        return $ret;
    }

    /**
     * 对于生成产品lock库存单独处理
     * @param $agreement_id
     * @param $qty
     * @param $transaction_id
     * @param $type
     * @throws Exception
     */
    private static function tailResolveGen($agreement_id, $qty, $transaction_id, $type)
    {
        $product_info = static::getOrigProductInfoByAgreementId((int)$agreement_id);
        // 判断有没有对应协议的库存记录 如果有 说明已经有对应记录 抛出异常
        $product_id = (int)$product_info['product_id'];
        $seller_id = (int)$product_info['seller_id'];
        $product_lock_info = static::getProductLockInfo($agreement_id, $product_id);
        if (!empty($product_lock_info)) {
            throw new Exception('Can not generate product lock.it may already have product lock.');
        }
        try {
            DB::beginTransaction();
            $insertLogArr = []; // oc_product_lock_log
            // combo品
            if ($product_info['combo_flag']) {
                $combos = static::getComboInfo($product_id);
                foreach ($combos as $item) {
                    $r_qty = $item['qty'] * $qty; // 对于子产品实际上架库存
                    $insertArr = [
                        'product_id' => $item['set_product_id'],
                        'seller_id' => $seller_id,
                        'agreement_id' => $agreement_id,
                        'type_id' => 2, // hard code 标志位现货保证金
                        'origin_qty' => $r_qty,
                        'qty' => $r_qty,
                        'parent_product_id' => $product_id,
                        'set_qty' => $item['qty'],
                        'memo' => 'margin lock',
                        'create_user_name' => static::USER_NAME,
                        'create_time' => Carbon::now(),
                        'update_user_name' => static::USER_NAME,
                        'update_time' => Carbon::now(),
                    ];
                    $product_lock_id = static::query()->insertGetId($insertArr);
                    $insertLogArr[] = [
                        'product_lock_id' => $product_lock_id,
                        'qty' => $r_qty,
                        'change_type' => $type,
                        'transaction_id' => $transaction_id,
                        'create_user_name' => static::USER_NAME,
                        'create_time' => Carbon::now(),
                    ];
                    // 同步减去子产品上架库存
                    static::updateProductQuantity($item['set_product_id'], 0 - $r_qty);
                }
            } else {
                $insertArr = [
                    'product_id' => $product_id,
                    'seller_id' => $seller_id,
                    'agreement_id' => $agreement_id,
                    'type_id' => 2, // hard code 标志位现货保证金
                    'origin_qty' => $qty,
                    'qty' => $qty,
                    'parent_product_id' => $product_id,
                    'set_qty' => 1,
                    'memo' => 'margin lock',
                    'create_user_name' => static::USER_NAME,
                    'create_time' => Carbon::now(),
                    'update_user_name' => static::USER_NAME,
                    'update_time' => Carbon::now(),
                ];
                $product_lock_id = DB::table('oc_product_lock')->insertGetId($insertArr);
                $insertLogArr[] = [
                    'product_lock_id' => $product_lock_id,
                    'qty' => $qty,
                    'change_type' => $type,
                    'transaction_id' => $transaction_id,
                    'create_user_name' => static::USER_NAME,
                    'create_time' => Carbon::now(),
                ];
            }
            // 写入product_lock_log
            DB::table('oc_product_lock_log')->insert($insertLogArr);
            // 同步减去产品上架库存
            static::updateProductQuantity($product_id, 0 - $qty);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;// 依旧抛出异常 供调用代码处理
        }
    }

    /**
     * 获取协议对应的商品信息
     * @param int $agreement_id
     * @return array
     * @throws Exception
     */
    private static function getOrigProductInfoByAgreementId(int $agreement_id): array
    {
        $res = DB::table('tb_sys_margin_agreement as sma')
            ->select([
                'p.product_id', 'p.combo_flag', 'sma.num as qty', 'sma.seller_id',
                'sma.status', 'sma.expire_time', 'sma.agreement_id',
            ])
            ->join('oc_product as p', 'p.product_id', '=', 'sma.product_id')
            ->join('oc_product_description as pd', 'p.product_id', '=', 'pd.product_id')
            ->where('sma.id', $agreement_id)
            ->first();
        if (!$res) {
            throw new Exception("Agreement_id:{$agreement_id} can not find relate agreement.");
        }
        return (array)$res;
    }

    /**
     * 获取combo品对应子产品信息
     * @param int $product_id
     * @return array
     */
    private static function getComboInfo(int $product_id): array
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
     * 获取对应产品的锁定库存信息
     * @param int $agreement_id
     * @param int $product_id
     * @return array
     */
    private static function getProductLockInfo(int $agreement_id, int $product_id): array
    {
        return static::query()
            ->where(['parent_product_id' => $product_id, 'agreement_id' => $agreement_id,])
            ->get()
            ->map(function (self $item) {
                return $item->attributes;
            })
            ->toArray();
    }

    /**
     * 更新上架库存 ps：不会校验上架库存和在库是否一致 但会校验结果库存是否大于0
     * @param int $product_id 商品id
     * @param int $qty 数量 有正负 正数：添加库存 负数：扣减库存
     * @throws Exception
     */
    private static function updateProductQuantity(int $product_id, int $qty)
    {
        // 获取原有库存
        $p_info = DB::table('oc_product')->where('product_id', $product_id)->first();
        $p_compute_qty = max((int)($p_info->quantity) + $qty, 0);
        DB::table('oc_product')->where('product_id', $product_id)->update(['quantity' => $p_compute_qty]);
        $ctp_info = DB::table('oc_customerpartner_to_product')->where('product_id', $product_id)->first();
        $ctp_compute_qty = max((int)$ctp_info->quantity + $qty, 0);
        DB::table('oc_customerpartner_to_product')->where('product_id', $product_id)->update(['quantity' => $ctp_compute_qty]);
    }

}