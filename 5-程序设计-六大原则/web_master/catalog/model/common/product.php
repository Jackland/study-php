<?php

use App\Logging\Logger;
use App\Models\Product\Product;
use App\Models\Product\ProductSetInfo;
use App\Repositories\ProductLock\ProductLockRepository;
use Illuminate\Database\Query\Builder;
use kriss\bcmath\BCS;

/**
 * Class ModelCommonProduct
 *
 * @property ModelCatalogMarginProductLock $model_catalog_margin_product_lock
 * @property ModelCatalogFuturesProductLock $model_catalog_futures_product_lock
 * @property ModelCatalogSalesOrderProductLock $model_catalog_sales_order_product_lock
 */
class ModelCommonProduct extends Model
{
    /**
     * 获取商品在库库存
     * ps：这个方法不会校验这个产品是否属于当前seller
     * @param int $product_id 产品id
     * @return int
     */
    public function getProductInStockQuantity(int $product_id): int
    {
        $combo_infos = $this->getComboProduct($product_id);
        if (!empty($combo_infos)) {
            $qty = [];
            foreach ($combo_infos as $item) {
                $temp_qty = $this->getProductInStockQuantity((int)$item['product_id']);
                $qty[] = (int)floor($temp_qty / $item['qty']);
            }
            return !empty($qty) ? (int)min($qty) : 0;
        }
        return (int)$this->orm
            ->table('tb_sys_batch')
            ->where('product_id', $product_id)
            ->sum('onhand_qty');
    }

    /**
     * 获取产品上架库存
     * @param int $product_id
     * @return int
     */
    public function getProductOnShelfQuantity(int $product_id)
    {
        return (int)$this->orm->table('oc_product')
            ->where('product_id', $product_id)
            ->value('quantity');
    }

    /**
     * 获取商品锁定库存
     * @param int $product_id
     * @return int
     * @throws Exception
     * 后续参考:
     * @see ModelCommonProduct::checkProductQtyIsAvailable()
     * @see ModelCommonProduct::getProductAvailableQuantity()
     */
    public function getProductLockQty(int $product_id): int
    {
        $this->load->model('catalog/margin_product_lock');
        $this->load->model('catalog/futures_product_lock');
        $this->load->model('catalog/sales_order_product_lock');
        $margin_lock = (int)$this->model_catalog_margin_product_lock->getProductMarginQty($product_id);
        $futures_lock = (int)$this->model_catalog_futures_product_lock->getProductFuturesQty($product_id);
        $sales_order_lock = (int)$this->model_catalog_sales_order_product_lock->getProductSalesOrderQty($product_id);
        //库存调整锁定库存
        $seller_inventory_adjust_Lock = (int)app(ProductLockRepository::class)->getProductSellerInventoryAdjustQty($product_id);
        return $margin_lock + $futures_lock + $sales_order_lock + $seller_inventory_adjust_Lock;
    }

    /**
     * 获取产品计算的理论锁定库存
     * @param Product|int $product
     * @return int
     * @throws Exception 后续推荐参考:
     * @see ModelCommonProduct::checkProductQtyIsAvailable()
     * @see ModelCommonProduct::getProductAvailableQuantity()
     */
    public function getProductComputeLockQty($product): int
    {
        if (!($product instanceof Product)) {
            $product = Product::find($product);
        }
        if ($product->combo_flag) {
            $product->combos->each(function (ProductSetInfo $item) use (&$arr) {
                $arr[] = ceil($this->getProductLockQty($item->set_product_id) / $item->qty);
            });
        } else {
            $arr[] = $this->getProductLockQty($product->product_id);
        }

        return !empty($arr) ? max($arr) : 0;
    }

    /**
     * 获取产品理论可以上架的最大库存
     * @param int $product_id 商品id
     * @return int
     * @throws Exception
     */
    public function getProductAvailableQuantity(int $product_id): int
    {
        $combo = $this->getComboProduct($product_id);
        $product_quantity_range = [];
        if (empty($combo)) {
            $product_quantity_range[] = (int)(
                $this->getProductInStockQuantity($product_id) - $this->getProductLockQty($product_id)
            );
        } else {
            array_map(function ($item) use ($product_id, &$product_quantity_range) {
                $real_qty = (int)(
                    $this->getProductInStockQuantity($item['product_id']) - $this->getProductLockQty($item['product_id'])
                );
                $product_quantity_range[] = (int)floor($real_qty / $item['qty']);
            }, $combo);
        }
        if (empty($product_quantity_range)) {
            return 0;
        }
        return max(min($product_quantity_range), 0);
    }

    /**
     * 产品锁定库存 订单库存校验时候使用 不考虑协议是否过期 后续尽量全部使用该方法
     * #1673 需要排除掉giga onsite seller的纯物流锁定库存
     * @param int $product_id 商品id
     * @return int
     */
    public function getProductOriginLockQty(int $product_id): int
    {
        $query = $this->orm
            ->table('oc_product_lock as pl')
            ->where('pl.product_id', $product_id)
            ->where('pl.qty', '>', 0)
            ->where('pl.is_ignore_qty', 0);

        return (int)($query->sum('pl.qty'));
    }

    /**
     * 校验数量是否满足最大可售数量
     * @param int $product_id
     * @param int $qty
     * @return bool
     * @throws Exception
     */
    public function checkProductQtyIsAvailable(int $product_id, int $qty): bool
    {
        $compute_qty = $this->getProductAvailableQuantity($product_id);
        return (bool)($compute_qty >= $qty);
    }

    /**
     * 更新相关产品的所有对应产品的库存 包括子产品 子产品的可能父产品
     * @param array|int $product_id 商品id
     * @param bool $always_update_on_shelf_qty 是否永远更新上架库存
     * @throws Exception
     */
    public function updateProductOnShelfQuantity($product_id, bool $always_update_on_shelf_qty = false)
    {
        // 待处理数组
        $product_id = (array)$product_id;
        // 已经处理过的商品id
        $resolveProductIds = [];
        while (!empty($product_id)) {
            // 弹出第一个产品id
            $t_id = intval(array_pop($product_id));
            // 如果已经处理 则跳过
            if (in_array($t_id, $resolveProductIds)) {
                continue;
            }
            // 对该商品上架库存调整 写入 已经处理的产品数组
            $resolveProductIds[] = $t_id;
            $this->updateOnShelfQuantity($t_id, $always_update_on_shelf_qty);
            // 获取关联商品id 并且写入待处理数组
            $product_id = array_unique(array_merge($product_id, $this->getProductRelateIds($t_id)));
        }
    }

    /**
     * 返回相关商品id 但不包含该id
     * @param int $product_id
     * @return array
     */
    private function getProductRelateIds(int $product_id)
    {
        $ret = [];
        $this->orm
            ->table('tb_sys_product_set_info')
            ->where(function (Builder $q) use ($product_id) {
                $q->orWhere('product_id', $product_id);
                $q->orWhere('set_product_id', $product_id);
            })
            ->get()
            ->each(function ($item) use (&$ret, $product_id) {
                $item = (array)$item;
                if ($item['product_id'] != $product_id) {
                    $ret[] = (int)$item['product_id'];
                }
                if ($item['set_product_id'] != $product_id) {
                    $ret[] = (int)$item['set_product_id'];
                }
            });

        return $ret;
    }

    /**
     * @param int $product_id
     * @param bool $always_update_on_shelf_qty
     * @throws Exception
     */
    private function updateOnShelfQuantity(int $product_id, bool $always_update_on_shelf_qty = false)
    {
        $on_shelf_qty = $this->getProductOnShelfQuantity($product_id);
        $compute_on_shelf_qty = $this->getProductAvailableQuantity($product_id);
        if ($always_update_on_shelf_qty || ($compute_on_shelf_qty < $on_shelf_qty) || ($on_shelf_qty < 0)) {
            $this->orm
                ->table('oc_product')
                ->where('product_id', $product_id)
                ->update(['quantity' => $compute_on_shelf_qty]);
            $this->orm
                ->table('oc_customerpartner_to_product')
                ->where('product_id', $product_id)
                ->update(['quantity' => $compute_on_shelf_qty]);
        }
    }

    /**
     * 获取商品id对应的子id
     *
     * @param int $productId
     * @return array
     */
    public function getComboProduct(int $productId): array
    {
        return $this->orm
            ->table(DB_PREFIX . 'product as p')
            ->leftJoin('tb_sys_product_set_info as ps', 'p.product_id', '=', 'ps.product_id')
            ->where(['p.product_id' => $productId, 'p.combo_flag' => 1])
            ->whereNotNull('ps.set_product_id')
            ->get(['ps.set_product_id as product_id', 'ps.qty'])
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
    }

    /**
     * 判断一个产品是否为虚拟产品 包括但不限于各类头款商品等
     * @param int $product_id 商品id
     * @return bool
     */
    public function checkIsVirtualProduct(int $product_id): bool
    {
        $res = $this->orm->table('oc_product')
            ->where([
                'product_id' => $product_id,
            ])
            ->whereIn('product_type', [0, 3])
            ->first();

        return !(bool)$res;
    }

    // fedex 旺季附加费
    const FEDEX_EXTRA_FEE = 15;

    /**
     * 获取产品的报警价格
     * （一件代发运费-旺季附加费-危险品附加费）/（货值+一件代发运费-旺季附加费-危险品附加费）>40% 时，要提示Seller运费占比不正确
     * 公式说明：
     * (基础运费+附加费) = (总运费-旺季附加费-危险品附加费)
     *
     * @param int $product_id 商品id
     * @param bool $need_check_oversize
     * @param array $product oc_product表的一条记录
     * @return float|float 报警价格
     */
    public function getAlarmPrice(int $product_id, bool $need_check_oversize = true, $product = [])
    {
        bcscale(4);
        if ($product) {
            //基础运费+附加费 = 总运费-旺季附加费，(combo品直接取父产品)
            $freight = BCS::create($product['freight'], ['scale' => 4])->sub($product['peak_season_surcharge'], $product['danger_fee'])->getResult();
        } else {
            $product = Product::query()->where('product_id', $product_id)->first();
            //基础运费+附加费 = 总运费-旺季附加费，(combo品直接取父产品)
            $freight = BCS::create($product->freight, ['scale' => 4])->sub($product->peak_season_surcharge, $product->danger_fee)->getResult();
        }

        return round((1 / PRODUCT_PRICE_PROPORTION - 1) * $freight, 4);
    }

    /**
     * 校验产品是否为fedex超大件商品
     * @param int $product_id
     * @return bool
     */
    public function checkIsOversizedProduct(int $product_id): bool
    {
        $product = $this->orm->table('oc_product as p')
            ->select(['p.*'])
            ->selectRaw('group_concat(pt.tag_id) as tag_ids')
            ->leftJoin('oc_product_to_tag as pt', 'pt.product_id', '=', 'p.product_id')
            ->where('p.product_id', $product_id)
            ->first();
        $is_ltl = !empty($product->tag_ids) && (strpos($product->tag_ids, '1') !== false);
        if ($is_ltl) return false;
        $length_arr = [$product->length ?: 0, $product->width ?: 0, $product->height ?: 0,];
        sort($length_arr);
        if ($length_arr[2] > 90 || (2 * array_sum($length_arr) - $length_arr[2]) > 130) {
            return true;
        }
        return false;
    }

    const IGNORE_CHECK_TYPE_ID = [2, 3];

    /**
     * 自动购买库存
     * ps:该方法自动忽略了对于非普通商品的校验
     * @param array $products
     *   exm: [
     *         ['product_id' => ..., 'quantity' => ...,  'type_id' => ...,],
     *         ['product_id' => ..., 'quantity' => ...,  'type_id' => ...,],
     *         ['product_id' => ..., 'quantity' => ...,  'type_id' => ...,],
     *       ]
     *   其中type_id可选 为[2,3]则自动忽略该商品的校验
     * @return bool
     */
    public function checkProductQuantityValid(array $products): bool
    {
        $resolved = [];
        $ret = true;
        Logger::order('----库存校验开始----');
        foreach ($products as $p) {
            $product_id = (int)$p['product_id'];
            $quantity = (int)$p['quantity'];
            // 判断是不是期货或现货等头款商品 虚拟产品不予校验
            if ($this->checkIsVirtualProduct($product_id)) {
                continue;
            }
            // 忽略type_id为期货{3}，现货{3}的校验
            if (
                array_key_exists('type_id', $p)
                && in_array((int)$p['type_id'], static::IGNORE_CHECK_TYPE_ID)
            ) {
                continue;
            }
            $combos = $this->getComboProduct($product_id);
            // 不是combo品
            if (empty($combos)) {
                if (array_key_exists($product_id, $resolved)) {
                    $resolved[$product_id] += $quantity;
                } else {
                    $resolved[$product_id] = $quantity;
                }
            } else {
                foreach ($combos as $c) {
                    $son_product_id = (int)$c['product_id'];
                    $son_quantity = (int)($quantity * $c['qty']);
                    if (array_key_exists($son_product_id, $resolved)) {
                        $resolved[$son_product_id] += $son_quantity;
                    } else {
                        $resolved[$son_product_id] = $son_quantity;
                    }
                }
            }
        }
        foreach ($resolved as $id => $qty) {
            // 打印当前的库存详情 注意 这里的校验校验全部的订单库存 所以效率可能不高
            // 此时在库数据打印
            Logger::order('PRODUCT ID:' . $id);
            $batch_list = $this->orm
                ->table('tb_sys_batch')
                ->where('product_id', $id)
                ->where('onhand_qty', '>', 0)
                ->lockForUpdate()
                ->get()
                ->map(function ($item) {
                    return (array)$item;
                })
                ->toArray();
            Logger::order(['BATCH INFO END', 'BATCH INFO' => $batch_list]);
            $lock_qty = $this->getProductOriginLockQty($id);
            $in_stock_qty = $this->getProductInStockQuantity($id);
            Logger::order("Product in stock:{$in_stock_qty} lock:{$lock_qty} require:{$qty}");
            if ($in_stock_qty - $lock_qty < $qty) {
                $ret = false;
                Logger::order("PRODUCT ID:{$id} check failed.", 'warning');
            } else {
                Logger::order("PRODUCT ID:{$id} check success.");
            }
        }
        Logger::order('----库存校验结束----');
        return $ret;
    }

    /**
     * 后续不再使用该方法
     * @param int $productId
     * @param int $setProductId
     * @return array
     * @deprecated
     */
    public function getComboProductBySetProductId(int $productId, int $setProductId): array
    {
        $orm = $this->orm;
        return $orm->table(DB_PREFIX . 'product as p')
            ->leftJoin('tb_sys_product_set_info as ps', 'p.product_id', '=', 'ps.product_id')
            ->where(['p.product_id' => $productId, 'p.combo_flag' => 1, 'ps.set_product_id' => $setProductId])
            ->whereNotNull('ps.set_product_id')
            ->get(['ps.set_product_id as product_id', 'ps.qty'])
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
    }
}
