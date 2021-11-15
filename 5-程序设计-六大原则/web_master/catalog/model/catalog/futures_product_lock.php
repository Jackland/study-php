<?php

use App\Helper\CountryHelper;
use App\Logging\Logger;
use App\Models\Link\CustomerPartnerToProduct;
use App\Models\Product\Product;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;

/**
 * Class ModelCatalogFuturesProductLock
 *
 * @property ModelCommonProduct $model_common_product
 * @property ModelCatalogMarginProductLock $model_catalog_margin_product_lock
 */
class ModelCatalogFuturesProductLock extends Model
{
    const LOCK_TAIL_GENERATE = 0; // 期货保证金尾款产品生成 type
    const LOCK_TAIL_PURCHASE = 1; // 期货尾款产品购买
    const LOCK_TAIL_RMA = 2;   // 期货尾款产品rma
    const LOCK_TAIL_CANCEL = 3; // 期货尾款协议取消
    const LOCK_TAIL_TIMEOUT = 4; // 期货尾款协议超时
    const LOCK_TAIL_INTERRUPT = 5; // 期货尾款协议终止
    const LOCK_TAIL_TRANSFER = 6;  // 期货协议转现货保证金协议
    const LOCK_TAIL_ORDER_TIMEOUT = 7; // 期货尾款订单超时未支付

    const FUTURES_LOCK_START = '----START FUTURES LOCK CHECK----';
    const FUTURES_LOCK_END = '----END FUTURES LOCK CHECK----';

    /**
     * @param int $agreement_id | 期货保证金id oc_futures_margin_agreement id字段
     * @param int $qty | 数量
     * @param $transaction_id | 交易id
     * @param $type | 类型
     * @throws Exception
     */
    public function TailIn($agreement_id, $qty, $transaction_id, $type)
    {
        if (!in_array($type, [
            static::LOCK_TAIL_GENERATE,
            static::LOCK_TAIL_RMA,
            static::LOCK_TAIL_ORDER_TIMEOUT
        ])) {
            throw new Exception('Error change type:' . $type);
        }
        $this->TailResolve(...func_get_args());
    }

    /**
     * @param int $agreement_id | 期货保证金id oc_futures_margin_agreement id字段
     * @param int $qty | 数量
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
     * @param int $agreement_id
     * @param int $qty
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
                $this->load->model('catalog/margin_product_lock');
                $this->model_catalog_margin_product_lock->TailIn(
                    $delivery_info['margin_agreement_id'],
                    $delivery_info['margin_apply_num'],
                    $transaction_id,
                    ModelCatalogMarginProductLock::LOCK_TAIL_TRANSFER
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
        $this->orm
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
     * @throws Exception
     */
    public function getProductFuturesComputeQty(int $product_id): int
    {
        $quantity = (int)$this->getProductFuturesQty($product_id);
        $this->load->model('common/product');
        $compute_qty = [];
        $combo_info = $this->model_common_product->getComboProduct($product_id);
        array_map(function ($item) use ($product_id, &$compute_qty) {
            $real_margin_qty = (int)$this->getProductFuturesQty($item['product_id'], null, [$product_id]);
            $compute_qty[] = (int)ceil($real_margin_qty / $item['qty']);
        }, $combo_info);

        return $quantity + (!empty($compute_qty) ? max($compute_qty) : 0);
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
        // model
        $this->load->model('common/product');
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
        // 校验此时的可锁定库存是否满足小于在库库存-锁定库存
        Logger::order([static::FUTURES_LOCK_START, 'ARGUMENTS' => func_get_args()]);
        $in_stock_qty = $this->model_common_product->getProductInStockQuantity($product_id);
        $lock_qty = $this->model_common_product->getProductComputeLockQty($product_id);
        if (!$this->model_common_product->checkProductQuantityValid(
            [['product_id' => $product_id, 'quantity' => (int)$qty]])
        ) {
            Logger::order(static::FUTURES_LOCK_END);
            throw new Exception(
                "Futures generate failed.Not enough in stock quantity.In stock:{$in_stock_qty} Now lock:{$lock_qty} Require:{$qty}.",
                999
            );
        }
        Logger::order(static::FUTURES_LOCK_END);
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
                        'type_id' => 3, // hard code 标志位期货
                        'origin_qty' => $r_qty,
                        'qty' => $r_qty,
                        'parent_product_id' => $product_id,
                        'set_qty' => $item['qty'],
                        'memo' => 'futures lock',
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
            // 同步抹平产品上架库存
            $this->updateOnShelfQty($product_id);
            $con->commit();
        } catch (Exception $e) {
            $con->rollBack();
            throw $e;// 依旧抛出异常 供调用代码处理
        }
        Logger::app("Futures agreement[{$agreement_id}] generate success.In stock:{$in_stock_qty} Lock:{$lock_qty} Require:{$qty}.");
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
            throw new Exception(
                "Agreement Id:{$agreement_id} Can not resolve product lock.it may do not have product lock."
            );
        }
        $con = $this->orm->getConnection();
        try {
            $con->beginTransaction();
            // 是否需要更新锁定库存
            $needUpdateProductLock = $this->checkNeedUpdateProductLock((int)$agreement_id, (int)$type);
            // 是否需要更新元商品库存
            $needUpdateProductQty = $this->checkNeedUpdateOrigProduct();
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
                    $r_qty = $ro_qty * ($p_lock['set_qty'] ?? 1);
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
     * 期货保证金产品永远不会更新上架库存
     * @return bool
     */
    private function checkNeedUpdateOrigProduct(): bool
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
        // 期货二期中可以通过状态来判断是否过期
        if ($agree_info['contract_id']) {
            $country = session('country', 'USA');
            $fromZone = CountryHelper::getTimezoneByCode('USA');
            $toZone = CountryHelper::getTimezoneByCode($country);
            $current_date = dateFormat($fromZone, $toZone, date('Y-m-d H:i:s'));
            $confirm_delivery_date = dateFormat($fromZone, $toZone, date('Y-m-d H:i:s', strtotime($agree_info['confirm_delivery_date'] . ' +7 days')));
            $confirm_delivery_date = substr($confirm_delivery_date, 0, 10) . ' 23:59:59';
            if (
                ($confirm_delivery_date > $current_date)
                && in_array($agree_info['status'], [6, 8])
            ) {
                $ret = true;
            }
        } else {
            if (
                in_array($agree_info['status'], [6, 8])
                && (strtotime($agree_info['confirm_delivery_date'] . ' +30 days') > time())
            ) {
                $ret = true;
            }
        }
        return $ret;
    }

    /**
     * 获取对应期货产品的锁定库存信息
     * @param int $agreement_id
     * @param int $product_id
     * @return array
     */
    public function getProductLockInfo(int $agreement_id, int $product_id): array
    {
        return db('oc_product_lock')
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
        $res = $this->orm
            ->table('oc_futures_margin_agreement as fma')
            ->select([
                'p.product_id', 'p.combo_flag', 'fma.num as qty', 'fma.seller_id',
                'fmd.delivery_status as status', 'fma.agreement_no', 'fmd.confirm_delivery_date',
                'fma.contract_id','fma.version'
            ])
            ->join('oc_futures_margin_delivery as fmd', 'fmd.agreement_id', '=', 'fma.id')
            ->join('oc_product as p', 'p.product_id', '=', 'fma.product_id')
            ->join('oc_product_description as pd', 'p.product_id', '=', 'pd.product_id')
            ->where('fma.id', $agreement_id)
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
    private function getComboInfo(int $product_id): array
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
     * @param int $agreementId
     * @return array|null
     */
    private function getFuturesMarginDeliveryInfo(int $agreementId): ?array
    {
        $ret = db('oc_futures_margin_delivery')
            ->where('agreement_id', $agreementId)
            ->first();
        return $ret ? (array)$ret : null;
    }

    /**
     * 期货锁定库存时候 需要适当调整上架库存
     * 举个例子：在库 30 上架35 需要将上架调整为30
     * @param int $productId
     * @throws Exception
     */
    private function updateOnShelfQty(int $productId)
    {
        $this->load->model('common/product');
        // 在库库存
        $in_stock_qty = $this->model_common_product->getProductInStockQuantity($productId);
        // 计算后的锁定库存
        $lock_qty = $this->model_common_product->getProductComputeLockQty($productId);
        // 上架库存·
        $on_shelf_qty = $this->model_common_product->getProductOnShelfQuantity($productId);
        // 上架库存 大于 （在库库存 - 理论锁定库存）
        $available_qty = max(($in_stock_qty - $lock_qty), 0);
        if ($on_shelf_qty > $available_qty) {
            Product::where('product_id', $productId)->update(['quantity' => $available_qty]);
            CustomerPartnerToProduct::where('product_id', $productId)->update(['quantity' => $available_qty]);
        }
        //如果该产品是combo品的子产品 同步修改对应的combo品
        db('tb_sys_product_set_info')
            ->where('set_product_id', $productId)
            ->pluck('product_id')
            ->each(function ($id) {
                $this->updateOnShelfQty($id);
            });
    }

    /**
     * 更新上架库存 ps：不会校验上架库存和在库是否一致 但会校验结果库存是否大于0
     * @param int $productId
     * @param int $qty 数量 有正负 正数：添加库存 负数：扣减库存
     */
    private function updateProductQuantity(int $productId, int $qty)
    {
        $product = Product::find($productId);
        $pComputeQty = max((int)($product->quantity) + $qty, 0);
        Product::where('product_id', $productId)->update(['quantity' => $pComputeQty]);
        $ctp = CustomerPartnerToProduct::query()->where('product_id', $productId)->first();
        $ctpComputeQty = max((int)($ctp->quantity) + $qty, 0);
        CustomerPartnerToProduct::where('product_id', $productId)->update(['quantity' => $ctpComputeQty]);
    }
}
