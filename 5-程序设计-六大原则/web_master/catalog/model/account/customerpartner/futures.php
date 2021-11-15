<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use App\Enums\Warehouse\ReceiptOrderStatus;

/**
 * Class ModelAccountCustomerpartnerFutures
 *
 * @property ModelCommonProduct $model_common_product
 */
class ModelAccountCustomerpartnerFutures extends Model
{
    /**
     * @param int $seller_id
     * @param int $product_id
     * @return array
     * user：wangjinxin
     * date：2020/2/26 15:07
     */
    public function getFuturesByProductId(int $seller_id, int $product_id): array
    {
        $res = $this->orm
            ->table('oc_futures_margin_template_item as fmti')
            ->leftJoin('oc_futures_margin_template as fmt', ['fmti.template_id' => 'fmt.id'])
            ->select([
                'fmti.*', 'fmt.buyer_payment_ratio', 'fmt.seller_payment_ratio',
                'fmt.min_expected_storage_days', 'fmt.max_expected_storage_days', 'fmt.status',
                'fmt.is_deleted', 'fmt.is_check_agreement'
            ])
            ->where([
                'fmt.seller_id' => $seller_id,
                'fmt.product_id' => $product_id,
            ])
            ->orderBy('fmti.id', 'asc')
            ->get()
            ->map(function ($item) {
                return get_object_vars($item);
            });

        return $res->toArray();
    }

    /**
     * 获取对应productid具体入库单信息
     * user：wangjinxin
     * date：2020/2/27 16:25
     * @param int $product_id
     * @param array $receiptsOrderStatus 状态 2=>已申请，6=>待收货
     * @return array
     */
    public function getReceiptOrderByProductId(int $product_id, array $receiptsOrderStatus = [ReceiptOrderStatus::APPLIED, ReceiptOrderStatus::TO_BE_RECEIVED]): array
    {
        $product_info = $this->orm->table('oc_product')->where(['product_id' => $product_id])->first();
        if ($product_info->combo_flag == 1) {  // combo 品
            $receive_num_arr = [];
            $combo_info = $this->getComboInfo([$product_id]);
            $combo_receipts = $combo_info->map(function ($item, $key) use (&$receive_num_arr, $receiptsOrderStatus) {
                $item = get_object_vars($item);
                $product_id = $item['set_product_id'];// 子产品id
                $qty = $item['qty'];
                $receipts = $this->getReceiptOrderByProductId($product_id, $receiptsOrderStatus); // taowa
                // 计算共有入库单
                $temp_rec_num = array_column($receipts, 'receive_number');
                if ($key == 0) {
                    $receive_num_arr = $temp_rec_num;
                } else {
                    $receive_num_arr = array_values(array_intersect($receive_num_arr, $temp_rec_num));
                }
                // 计算预计数量
                array_walk($receipts, function (&$item) use ($qty) {
                    $item['expected_qty'] = floor($item['expected_qty'] / $qty);
                });
                return $receipts;
            });
            if (empty($receive_num_arr)) return [];
            $ret = [];
            foreach ($receive_num_arr as $r_num) {
                $temp = [];
                $temp['receive_number'] = $r_num;
                $combo_receipts->map(function ($item, $index) use (&$temp) {
                    foreach ($item as $v) {
                        if ($v['receive_number'] == $temp['receive_number']) {
                            if ($index == 0) {
                                $temp['expected_qty'] = $v['expected_qty'];
                                $temp['expected_date'] = $v['expected_date'];
                                $temp['is_over_time'] = $v['is_over_time'];
                            } else {
                                $temp['expected_qty'] = min($temp['expected_qty'], $v['expected_qty']);
                                $temp['is_over_time'] = max($temp['is_over_time'], $v['is_over_time']);
                                if (empty($temp['expected_date'])) {
                                    $temp['expected_date'] = $v['expected_date'];
                                    continue;
                                }
                                if ($temp['expected_date'] && $v['expected_date']) {
                                    $temp['expected_date'] = strtotime($temp['expected_date']) > strtotime($v['expected_date'])
                                        ? $temp['expected_date']
                                        : $v['expected_date'];
                                    continue;
                                }
                            }
                        }
                    }
                });
                $ret[] = $temp;
                unset($temp);
            }
            return $ret;
        }
        $res = $this->orm
            ->table('tb_sys_receipts_order_detail as srod')
            ->leftJoin('tb_sys_receipts_order as sro', ['srod.receive_order_id' => 'sro.receive_order_id'])
            ->select(['srod.receive_number', 'srod.expected_qty', 'sro.expected_date'])
            ->where(['srod.product_id' => $product_id])
            ->whereIn('sro.status', $receiptsOrderStatus)
            ->orderBy('sro.expected_date', 'asc')
            ->orderBy('sro.create_time', 'asc')
            ->get()
            ->map(function ($item) {
                $item = get_object_vars($item);
                //当前时间超过预计入库时间
                $item['is_over_time'] = ($item['expected_date'] && time() > strtotime($item['expected_date'])) ? 1 : 0;
                return $item;
            });

        return $res->toArray();
    }

    /**
     * 保存模板
     *
     * @param $request
     * @return bool
     * user：wangjinxin
     * date：2020/2/28 19:22
     */
    public function saveFuturesTemplate($request): bool
    {
        $ret = true;
        $customer_id = $this->customer->getId();
        $product_id = $request['product_id'];
        $con = $this->orm->getConnection();
        try {
            $con->beginTransaction();
            $con->table('oc_futures_margin_template')
                ->updateOrInsert(
                    ['seller_id' => $customer_id, 'product_id' => $product_id],
                    [
                        'status' => $request['status'],
                        'min_expected_storage_days' => $request['min_day'],
                        'max_expected_storage_days' => $request['max_day'],
                        'update_time' => Carbon::now()
                    ]
                );
            $res = $con->table('oc_futures_margin_template')
                ->where(['seller_id' => $customer_id, 'product_id' => $product_id])
                ->first();
            if (!$res) {
                throw new Exception(__FILE__ . '[insert error.]');
            }
            $template_id = $res->id;
            $con->table('oc_futures_margin_template_item')->where('template_id', $template_id)->delete();
            $insertArr = [];
            $hasDefault = false;
            foreach ($request['data'] as $item) {
                $insertArr[] = [
                    'template_id' => $template_id,
                    'min_num' => $item['min'],
                    'max_num' => $item['max'],
                    'exclusive_price' => $item['price'],
                    'is_default' => $item['is_default'],
                    'create_time' => Carbon::now(),
                    'update_time' => Carbon::now(),
                ];
                if ($item['is_default'] == 1) {
                    $hasDefault = true;
                }
            }
            if (!$hasDefault) {
                $insertArr[0]['is_default'] = 1;
            }
            $con->table('oc_futures_margin_template_item')->insert($insertArr);
            $con->commit();
        } catch (Exception $e) {
            $this->log->write($e->getMessage());
            $ret = false;
            $con->rollBack();
        }
        return $ret;
    }

    /**
     * 禁用模板
     * @param int $seller_id
     * @param array $id
     * user：wangjinxin
     * date：2020/3/3 15:19
     * @return bool
     */
    public function banFutures(int $seller_id, $id): bool
    {
        $id = (array)$id;
        $ret = true;
        try {
            $this->orm->getConnection()->transaction(function () use ($seller_id, $id) {
                $this->orm->table('oc_futures_margin_template')
                    ->where(['seller_id' => $seller_id])
                    ->whereIn('id', $id)
                    ->update(['status' => 0]);
            });
        } catch (Throwable $e) {
            $this->log->write($e->getMessage());
            $ret = false;
        }

        return $ret;
    }

    /**
     * 启用用模板
     * @param int $seller_id
     * @param array $id
     * user：wangjinxin
     * date：2020/3/3 15:19
     * @return bool
     */
    public function recoveryFutures(int $seller_id, $id): bool
    {
        $id = (array)$id;
        $ret = true;
        try {
            $this->orm->getConnection()->transaction(function () use ($seller_id, $id) {
                $this->orm->table('oc_futures_margin_template')
                    ->where(['seller_id' => $seller_id])
                    ->whereIn('id', $id)
                    ->update(['status' => 1]);
            });
        } catch (Throwable $e) {
            $this->log->write($e->getMessage());
            $ret = false;
        }

        return $ret;
    }

    /**
     * 删除模板
     * @param int $seller_id
     * @param array $id
     * user：wangjinxin
     * date：2020/3/3 15:19
     * @return bool
     */
    public function deleteFutures(int $seller_id, $id): bool
    {
        $id = (array)$id;
        $ret = true;
        try {
            $this->orm->getConnection()->transaction(function () use ($seller_id, $id) {
                $ids = $this->orm->table('oc_futures_margin_template')
                    ->where(['seller_id' => $seller_id])
                    ->whereIn('id', $id)
                    ->pluck('id');
                $this->orm->table('oc_futures_margin_template')
                    ->where(['seller_id' => $seller_id])
                    ->whereIn('id', $ids)
                    ->delete();
                $this->orm->table('oc_futures_margin_template_item')
                    ->whereIn('template_id', $ids)
                    ->delete();
            });
        } catch (Throwable $e) {
            $this->log->write($e->getMessage());
            $ret = false;
        }

        return $ret;
    }

    /**
     * 根据产品删除期货保证金模板
     * 里面没有启用事务，如果有需要，请在外面加上
     *
     * @param int   $sellerId
     * @param array $productIds
     */
    public function deleteFuturesByProduct(int $sellerId, array $productIds)
    {
        $ids = $this->orm->table('oc_futures_margin_template')
                         ->where(['seller_id' => $sellerId])
                         ->whereIn('product_id', $productIds)
                         ->pluck('id');
        if (!empty($ids)) {
            $this->orm->table('oc_futures_margin_template')->where(['seller_id' => $sellerId])->whereIn('id', $ids)
                      ->delete();
            $this->orm->table('oc_futures_margin_template_item')->whereIn('template_id', $ids)->delete();
        }
        return true;
    }

    /**
     * 获取模板信息
     * @param int $seller_id
     * @param int $template_id
     * @return array|null
     * user：wangjinxin
     * date：2020/3/2 15:24
     */
    public function getFuturesTemplateInfoByTemplateId(int $seller_id, int $template_id)
    {
        $template_info = $this->orm
            ->table('oc_futures_margin_template as fmt')
            ->select([
                'fmt.*', 'p.mpn', 'p.sku', 'pd.name', 'p.price',
            ])
            ->join('oc_product as p', ['p.product_id' => 'fmt.product_id'])
            ->join(DB_PREFIX . 'customerpartner_to_product as c2p', ['c2p.product_id' => 'p.product_id'])
            ->leftJoin(DB_PREFIX . 'product_description as pd', ['pd.product_id' => 'p.product_id'])
            ->where(['fmt.seller_id' => $seller_id, 'fmt.id' => $template_id])
            ->first();
        if (!$template_info) {
            return null;
        }
        $template_info = get_object_vars($template_info);
        $items = $this->orm
            ->table('oc_futures_margin_template_item')
            ->where('template_id', $template_id)
            ->get()
            ->map(function ($item) {
                return get_object_vars($item);
            })
            ->toArray();
        $template_info['items'] = $items;

        return $template_info;
    }

    /**
     * 获取模板信息
     * @param int $seller_id
     * @param int $product_id
     * @return array|null
     * user：wangjinxin
     * date：2020/3/2 15:24
     */
    public function getFuturesTemplateInfoByProductId(int $seller_id, int $product_id)
    {
        $template_info = $this->orm
            ->table('oc_futures_margin_template as fmt')
            ->select([
                'fmt.*', 'p.mpn', 'p.sku', 'pd.name', 'p.price',
            ])
            ->join('oc_product as p', ['p.product_id' => 'fmt.product_id'])
            ->join(DB_PREFIX . 'customerpartner_to_product as c2p', ['c2p.product_id' => 'p.product_id'])
            ->leftJoin(DB_PREFIX . 'product_description as pd', ['pd.product_id' => 'p.product_id'])
            ->where(['fmt.seller_id' => $seller_id, 'fmt.product_id' => $product_id])
            ->first();
        if (!$template_info) {
            return null;
        }
        $template_info = get_object_vars($template_info);
        $items = $this->orm
            ->table('oc_futures_margin_template_item')
            ->where('template_id', $template_info['id'])
            ->get()
            ->map(function ($item) {
                $item = (array)$item;
                $item['exclusive_price'] = $this->customer->isJapan()
                    ? (int)$item['exclusive_price']
                    : $item['exclusive_price'];
                return $item;
            })
            ->toArray();
        $template_info['items'] = $items;

        return $template_info;
    }


    /**
     * 获取用户期货模板列表
     * @param int $seller_id
     * @param array $data
     * @return array
     * user：wangjinxin
     * date：2020/3/2 18:57
     */
    public function getFuturesList(int $seller_id, array $data): array
    {
        $ret = $this->getFuturesQuery($seller_id, $data)
            ->forPage($data['page'] ?? 1, $data['page_limit'] ?? 10)
            ->get()
            ->map(function ($item) {
                $item = get_object_vars($item);
                $items = $this->orm
                    ->table('oc_futures_margin_template_item')
                    ->where('template_id', $item['id'])
                    ->orderBy('id', 'asc')
                    ->get()
                    ->map(function ($item) {
                        return get_object_vars($item);
                    })
                    ->toArray();
                $item['items'] = $items;
                return $item;
            });

        return $ret->toArray();
    }

    /**
     * @param int $seller_id
     * @param array $data
     * @return int
     * user：wangjinxin
     * date：2020/3/2 19:11
     */
    public function getFuturesTotal(int $seller_id, array $data): int
    {
        return $this->getFuturesQuery($seller_id, $data)->count();
    }

    /**
     * @param int $seller_id
     * @param array $data
     * @return Builder
     * user：wangjinxin
     * date：2020/3/2 19:05
     */
    private function getFuturesQuery(int $seller_id, array $data)
    {
        $co = new Collection($data);
        return $this->orm
            ->table('oc_futures_margin_template as fmt')
            ->select([
                'fmt.*', 'p.mpn', 'p.sku', 'pd.name', 'p.price',
            ])
            ->join('oc_product as p', ['p.product_id' => 'fmt.product_id'])
            ->join(DB_PREFIX . 'customerpartner_to_product as c2p', ['c2p.product_id' => 'p.product_id'])
            ->leftJoin(DB_PREFIX . 'product_description as pd', ['pd.product_id' => 'p.product_id'])
            ->where(['fmt.seller_id' => $seller_id,])
            ->when(!empty($co->get('filter_sku_mpn', '')), function (Builder $q) use ($co) {
                $q->where(function (Builder $q) use ($co) {
                    $q->orWhere('p.sku', 'like', '%' . $co->get('filter_sku_mpn') . '%');
                    $q->orWhere('p.mpn', 'like', '%' . $co->get('filter_sku_mpn') . '%');
                });
            })
            ->orderBy('status', 'desc')
            ->orderBy('update_time', 'desc')
            ->orderBy('id', 'desc');
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

}
