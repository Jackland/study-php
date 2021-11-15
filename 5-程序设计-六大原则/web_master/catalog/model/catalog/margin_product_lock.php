<?php

use App\Logging\Logger;
use App\Models\Link\CustomerPartnerToProduct;
use App\Models\Margin\MarginAgreement;
use App\Models\Product\Product;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;

/**
 * Class ModelCatalogMarginProductLock
 *
 * @property ModelCommonProduct $model_common_product
 */
class ModelCatalogMarginProductLock extends Model
{
    const LOCK_TAIL_GENERATE = 0; // 保证金尾款产品生成 type
    const LOCK_TAIL_PURCHASE = 1; // 尾款产品购买
    const LOCK_TAIL_RMA = 2;   // 尾款产品rma
    const LOCK_TAIL_CANCEL = 3; // 尾款协议取消
    const LOCK_TAIL_TIMEOUT = 4; // 尾款协议超时
    const LOCK_TAIL_INTERRUPT = 5; // 尾款协议终止
    const LOCK_TAIL_TRANSFER = 6; // 期货保证金 转现货保证金

    const MARGIN_LOCK_START = '----START MARGIN LOCK CHECK----';
    const MARGIN_LOCK_END = '----END MARGIN LOCK CHECK----';

    /**
     * 保证金协议产品入库
     * 下列情况：0-保证金产品生成 2-尾款产品rma
     * @param int $agreement_id
     * @param int $qty
     * @param $transaction_id
     * @param $type
     * @throws Exception
     */
    public function TailIn($agreement_id, $qty, $transaction_id, $type)
    {
        if (!in_array($type, [static::LOCK_TAIL_GENERATE, static::LOCK_TAIL_RMA, static::LOCK_TAIL_TRANSFER])) {
            throw new Exception('Error change type:' . $type);
        }
        $this->TailResolve(...func_get_args());
    }

    /**
     * 保证金产品出库
     * 下列情况：1-保证金产品购买 3-尾款协议取消
     * @param int $agreement_id
     * @param int $qty
     * @param $transaction_id
     * @param $type
     * @throws Exception
     */
    public function TailOut($agreement_id, $qty, $transaction_id, $type)
    {
        if (!in_array($type, [static::LOCK_TAIL_PURCHASE, static::LOCK_TAIL_CANCEL, static::LOCK_TAIL_INTERRUPT])) {
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
    public function getProductMarginQty(
        int $product_id,
        $agreement_id = null,
        array $excludeProductIds = []
    )
    {
        $num = null;
        $res = db('oc_product_lock as pl')
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
     * @throws Exception
     */
    public function getProductMarginComputeQty(int $product_id): int
    {
        $margin_quantity = (int)$this->getProductMarginQty($product_id);
        $this->load->model('common/product');
        $compute_qty = [];
        $combo_info = $this->model_common_product->getComboProduct($product_id);
        array_map(function ($item) use ($product_id, &$compute_qty) {
            $real_margin_qty = (int)$this->getProductMarginQty($item['product_id'], null, [$product_id]);
            $compute_qty[] = (int)ceil($real_margin_qty / $item['qty']);
        }, $combo_info);

        return $margin_quantity + (!empty($compute_qty) ? max($compute_qty) : 0);
    }

    /**
     * @param int $agreement_id
     * @param int $qty
     * @param $transaction_id
     * @param $type
     * @throws Exception
     */
    private function tailResolve($agreement_id, $qty, $transaction_id, $type)
    {
        if (in_array((int)$type, [static::LOCK_TAIL_GENERATE, static::LOCK_TAIL_TRANSFER])) { // 对于生成锁定库存单独处理
            $this->tailResolveGen(...func_get_args());
        } else {
            $this->tailResolveNormal(...func_get_args());
        }
    }

    /**
     * @param int $agreement_id
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
                    $lockRes = $con->table('oc_product_lock')
                        ->where('id', $p_lock['id'])
                        ->where('qty', '=', $p_lock['qty']) // 这里增加限制为了是处理高并发抢占锁定库存资源bug
                        ->update([
                            'qty' => new Expression("qty + {$r_qty}"),
                            'update_user_name' => $this->customer->getId(),
                            'update_time' => Carbon::now(),
                        ]);
                    if (!$lockRes) {
                        // 如果没修改，则代表数据更新失败，直接抛出异常终止交易
                        throw new Exception('Product Lock Stock Error!');
                    }
                    $insertLogArr[] = [
                        'product_lock_id' => $p_lock['id'],
                        'qty' => $r_qty,
                        'change_type' => $type,
                        'transaction_id' => $transaction_id,
                        'create_user_name' => $this->customer->getId(),
                        'create_time' => Carbon::now(),
                    ];
                }
                // 写入product_lock_log
                $con->table('oc_product_lock_log')->insert($insertLogArr);
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
                    $this->updateProductQuantity((int)$p_lock['product_id'], $r_qty);
                }
                if ($product_info['combo_flag'] == 1) {
                    // 如果为子产品 同时也得更新父产品的上架库存
                    $this->updateProductQuantity((int)$product_info['product_id'], $ro_qty);
                }
            }
            $con->commit();
        } catch (Exception $e) {
            $con->rollBack();
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
     *
     * 特殊情况:内部seller永远不会更新上架库存
     * @param int $agreementId
     * @param int $type
     * @return bool
     * @throws Exception
     */
    private function checkNeedUpdateOrigProduct(int $agreementId, int $type): bool
    {
        $ret = false;
        if ($type == static::LOCK_TAIL_RMA && !$this->checkAgreementIsValid($agreementId)) {
            $ret = true;
        }
        if (in_array($type, [static::LOCK_TAIL_TIMEOUT, static::LOCK_TAIL_CANCEL, static::LOCK_TAIL_INTERRUPT,])) {
            $ret = true;
        }
        if ($this->checkAgreeSellerIsInner($agreementId)) {
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
    private function checkNeedUpdateProductLock(int $agreement_id, int $type): bool
    {
        $ret = true;
        if ($type == self::LOCK_TAIL_RMA && !$this->checkAgreementIsValid($agreement_id)) {
            $ret = false;
        }
        return $ret;
    }

    /**
     * 校验协议的seller是否为内部用户
     * @param int $agreementId
     * @return bool
     */
    private function checkAgreeSellerIsInner(int $agreementId): bool
    {
        $margin = MarginAgreement::with(['seller'])->find($agreementId);
        return $margin->seller->accounting_type == 1;
    }

    /**
     * 校验保证金协议是否有效
     * 有效：协议状态为6:sold 8:completed  且协议到期时间还没有到
     * @param int $agreement_id
     * @return bool
     * @throws Exception
     */
    private function checkAgreementIsValid(int $agreement_id): bool
    {
        $agree_info = $this->getOrigProductInfoByAgreementId($agreement_id);
        $ret = false;
        if (in_array($agree_info['status'], [6, 8]) && (strtotime($agree_info['expire_time']) > time())) {
            $ret = true;
        }

        return $ret;
    }

    /**
     * 对于生成产品lock库存单独处理
     * @param int $agreement_id
     * @param int $qty
     * @param $transaction_id
     * @param $type
     * @throws Exception
     */
    private function tailResolveGen($agreement_id, $qty, $transaction_id, $type)
    {
        $this->load->model('common/product');
        $product_info = $this->getOrigProductInfoByAgreementId((int)$agreement_id);
        // 判断有没有对应协议的库存记录 如果有 说明已经有对应记录 抛出异常
        $product_id = (int)$product_info['product_id'];
        $seller_id = (int)$product_info['seller_id'];
        $product_lock_info = $this->getProductLockInfo($agreement_id, $product_id);
        if (!empty($product_lock_info)) {
            throw new Exception('Can not generate product lock.it may already have product lock.');
        }
        // 校验此时的可锁定库存是否满足小于在库库存-锁定库存
        Logger::order([static::MARGIN_LOCK_START, 'ARGUMENTS' => func_get_args()]);
        $in_stock_qty = $this->model_common_product->getProductInStockQuantity($product_id);
        $lock_qty = $this->model_common_product->getProductComputeLockQty($product_id);
        if (!$this->model_common_product->checkProductQuantityValid(
            [['product_id' => $product_id, 'quantity' => (int)$qty]])
        ) {
            Logger::order(static::MARGIN_LOCK_END);
            throw new Exception(
                "Margin generate failed.Not enough in stock quantity.In stock:{$in_stock_qty} Lock:{$lock_qty} Require:{$qty}.",
                999
            );
        }
        Logger::order(static::MARGIN_LOCK_END);
        // 如果是期货保证金 转现货保证金的 没有必要减少上架库存
        // 也就是单独生成现货保证金 才会变动上架库存
        $needUpdateProductQuantity = (bool)($type == static::LOCK_TAIL_GENERATE);
        $con = $this->orm->getConnection();
        try {
            $con->beginTransaction();
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
                        'type_id' => 2, // hard code 标志位现货保证金
                        'origin_qty' => $r_qty,
                        'qty' => $r_qty,
                        'parent_product_id' => $product_id,
                        'set_qty' => $item['qty'],
                        'memo' => 'margin lock',
                        'create_user_name' => $this->customer->getId(),
                        'create_time' => Carbon::now(),
                        'update_user_name' => $this->customer->getId(),
                        'update_time' => Carbon::now(),
                    ];
                    $product_lock_id = $con->table('oc_product_lock')->insertGetId($insertArr);
                    $insertLogArr[] = [
                        'product_lock_id' => $product_lock_id,
                        'qty' => $r_qty,
                        'change_type' => $type,
                        'transaction_id' => $transaction_id,
                        'create_user_name' => $this->customer->getId(),
                        'create_time' => Carbon::now(),
                    ];
                    // 同步减去子产品上架库存
                    if ($needUpdateProductQuantity) {
                        $this->updateProductQuantity($item['set_product_id'], 0 - $r_qty);
                    }
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
                    'create_user_name' => $this->customer->getId(),
                    'create_time' => Carbon::now(),
                    'update_user_name' => $this->customer->getId(),
                    'update_time' => Carbon::now(),
                ];
                $product_lock_id = $con->table('oc_product_lock')->insertGetId($insertArr);
                $insertLogArr[] = [
                    'product_lock_id' => $product_lock_id,
                    'qty' => $qty,
                    'change_type' => $type,
                    'transaction_id' => $transaction_id,
                    'create_user_name' => $this->customer->getId(),
                    'create_time' => Carbon::now(),
                ];
            }
            // 写入product_lock_log
            $con->table('oc_product_lock_log')->insert($insertLogArr);
            // 同步减去产品上架库存
            if ($needUpdateProductQuantity) {
                $this->updateProductQuantity($product_id, 0 - $qty);
            }
            $con->commit();
        } catch (Exception $e) {
            $con->rollBack();
            throw $e;// 依旧抛出异常 供调用代码处理
        }
        Logger::app("Margin agreement[{$agreement_id}] generate success.In stock:{$in_stock_qty} Now lock:{$lock_qty} Require:{$qty}.");
    }

    /**
     * 获取协议对应的商品信息
     * @param int $agreementId
     * @return array
     * @throws Exception
     */
    public function getOrigProductInfoByAgreementId(int $agreementId): array
    {
        $res = db('tb_sys_margin_agreement as sma')
            ->select([
                'p.product_id', 'p.combo_flag', 'sma.num as qty', 'sma.seller_id',
                'sma.status', 'sma.expire_time', 'sma.agreement_id',
            ])
            ->join('oc_product as p', 'p.product_id', '=', 'sma.product_id')
            ->join('oc_product_description as pd', 'p.product_id', '=', 'pd.product_id')
            ->where('sma.id', $agreementId)
            ->first();
        if (!$res) {
            throw new Exception("Agreement_id:{$agreementId} can not find relate agreement.");
        }
        return (array)$res;
    }

    /**
     * 获取对应产品的锁定库存信息
     * @param int $agreementId
     * @param int $productId
     * @return array
     */
    public function getProductLockInfo(int $agreementId, int $productId): array
    {
        return db('oc_product_lock')
            ->where(['parent_product_id' => $productId, 'agreement_id' => $agreementId, 'type_id' => 2,])
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
        return db('tb_sys_product_set_info as s')
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

    /**
     * 更新上架库存 ps：不会校验上架库存和在库是否一致 但会校验结果库存是否大于0
     * @param int $productId 商品id
     * @param int $qty 数量 有正负 正数：添加库存 负数：扣减库存
     */
    private function updateProductQuantity(int $productId, int $qty)
    {
        // 获取原有库存
        $product = Product::find($productId);
        $pComputeQty = max((int)($product->quantity) + $qty, 0);
        Product::where('product_id', $productId)->update(['quantity' => $pComputeQty]);
        $ctp = CustomerPartnerToProduct::query()->where('product_id', $productId)->first();
        $ctpComputeQty = max((int)($ctp->quantity) + $qty, 0);
        CustomerPartnerToProduct::where('product_id', $productId)->update(['quantity' => $ctpComputeQty]);
    }

}
