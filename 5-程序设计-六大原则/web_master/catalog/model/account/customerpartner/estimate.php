<?php

use Illuminate\Support\Collection;

class ModelAccountCustomerpartnerEstimate extends Model
{
    /**
     * 获取seller预估金
     * @param int $seller_id
     * @return float
     * user：wangjinxin
     * date：2020/3/5 14:07
     */
    public function getEstimatedAmount(int $seller_id): float
    {
        // 获取用户所有可以售卖的产品
        $res = $this->orm
            ->table('oc_customerpartner_to_product as ctp')
            ->join('oc_product as p', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_amount_ratio as par', 'par.product_id', '=', 'p.product_id')
            ->where(['p.buyer_flag' => 1, 'p.is_deleted' => 0, 'ctp.customer_id' => $seller_id])
            ->select(['p.price', 'p.product_id', 'p.combo_flag'])
            ->selectRaw('ifnull(par.ratio,1) as ratio')
            ->get();
        if ($res->count() === 0) return 0;
        // 所有combo品id
        $combo_ids = $res
            ->filter(function ($item) {
                return $item->combo_flag == 1;
            })
            ->pluck('product_id')
            ->toArray();
        $combo_info = $this->getComboInfo($combo_ids);
        $combos = [];
        $combo_info->map(function ($item) use (&$combos) {
            $product_id = $item->product_id;
            if (isset($combos[$product_id])) {
                $combos[$product_id][] = $item;
            } else {
                $combos[$product_id] = [$item];
            }
        });
        // product_ids 和 部分子产品累计
        $product_ids = $res->pluck('product_id')->toArray();
        $son_product_ids = $combo_info->pluck('set_product_id')->toArray();
        $total_ids = array_values(array_unique(array_merge($product_ids, $son_product_ids)));
        $total_qty = $this->getProductOnhandQty($total_ids);
        // 计算预定价格
        bcscale($this->customer->isJapan() ? 0 : 2);
        $ret = 0;
        $res->map(function ($item) use ($combos, $total_qty, &$ret) {
            $product_id = $item->product_id;
            $ratio = $item->ratio;
            $price = $item->price;
            if ($item->combo_flag == 0) {
                $qty = $total_qty[$product_id] ?? 0;
                $ret = bcadd($ret, $this->bcmul_multi($qty, $price, $ratio));
            }
            // combo
            if ($item->combo_flag == 1) {
                $combo_details = $combos[$product_id] ?? [];
                $qty = 0;
                // 计算理论上的combo数量
                foreach ($combo_details as $k => $v) {
                    $t_product_id = $v->set_product_id;
                    $t_qty = floor(bcdiv($total_qty[$t_product_id] ?? 0, $v->qty ?? 1));
                    if ($k == 0) {
                        $qty = $t_qty;
                    } else {
                        $qty = min($qty, $t_qty);
                    }
                }
                $ret = bcadd($ret, $this->bcmul_multi($qty, $price, $ratio));
            }
        });

        return $ret;
    }

    /**
     * @param int $product_id
     * @return Collection
     * user：wangjinxin
     * date：2020/3/5 16:47
     */
    private function getProductOnhandQty($product_id)
    {
        return $this->orm
            ->table('tb_sys_batch')
            ->select('product_id')
            ->selectRaw('ifnull(sum(onhand_qty),0) as onhand_qty')
            ->whereIn('product_id', (array)$product_id)
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id')
            ->map(function ($item) {
                return (int)$item->onhand_qty;
            });
    }

    /**
     * @param array $productIDArr
     * @return Collection
     * user：wangjinxin
     * date：2020/3/2 14:00
     */
    private function getComboInfo(array $productIDArr)
    {
        return $this->orm->table('tb_sys_product_set_info')
            ->select([
                'product_id',
                'set_product_id',
                'qty'
            ])
            ->whereIn('product_id', $productIDArr)
            ->whereNotNull('set_product_id')
            ->get();
    }

    /**
     * 连续相乘
     * @param mixed ...$args
     * user：wangjinxin
     * date：2020/3/5 16:59
     * @return int|mixed
     */
    private function bcmul_multi(...$args)
    {
        if (count($args) == 0) {
            return 0;
        }
        if (count($args) == 1) {
            return $args[0];
        }
        $res = bcmul($args[0], $args[1]);
        $newArgs = array_slice($args, 2);
        array_unshift($newArgs, $res);
        return $this->bcmul_multi(...$newArgs);
    }

}