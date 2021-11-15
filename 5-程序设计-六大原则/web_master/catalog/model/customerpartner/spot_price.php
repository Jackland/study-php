<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 议价 以及 阶梯价格模型
 * Class ModelCustomerpartnerSpotPrice
 * @property ModelLocalisationCurrency $model_localisation_currency
 */
class ModelCustomerpartnerSpotPrice extends Model
{
    /**
     * 根据seller_id 获取阶梯价格信息
     * @param int $customer_id
     * @param array $filter_data
     * @return array
     */
    public function getTieredPriceList(int $customer_id, array $filter_data = []): array
    {
        $co = new Collection($filter_data);
        /** @var Config $config */
        $config = $this->config;
        /** @var \Cart\Currency $currency */
        $currency = $this->currency;
        $nowCurrency = session('currency');
        /** @var Builder $query */
        $query = $this->orm->table(DB_PREFIX . 'wk_pro_quote_details as pqd')
            ->select([
                'pqd.min_quantity', 'pqd.max_quantity', 'pqd.price', 'pqd.template_id',
                'pqd.id', 'p.freight', 'p.sku', 'p.mpn', 'pqd.product_id', 'pqd.home_pick_up_price',
            ])
            ->leftJoin(DB_PREFIX . 'product as p', ['pqd.product_id' => 'p.product_id'])
            ->orderBy('pqd.product_id', 'desc')
            ->orderBy('pqd.min_quantity', 'desc')
            ->orderBy('pqd.id', 'desc')
            ->where([
                'pqd.seller_id' => $customer_id,
            ])
            ->when(trim($co->get('filter_sku_mpn')), function (Builder $q) use ($co) {
                $q->where(function (Builder $q) use ($co) {
                    $co['filter_sku_mpn'] = trim($co['filter_sku_mpn']);
                    $q->orWhere('p.sku', 'LIKE', "%{$co['filter_sku_mpn']}%");
                    $q->orWhere('p.mpn', 'LIKE', "%{$co['filter_sku_mpn']}%");
                });
            });
        $total = $query->count('pqd.id');
        if ($co->has($config->get('page')) && $co->has($config->get('per_page'))) {
            $query = $query->forPage(
                $co->get($config->get('page'), 1),
                $co->get($config->get('per_page'), 15)
            );
        }
        bcscale(2);
        $rows = $query->get()->map(function ($item) use ($currency, $nowCurrency) {
            $item = get_object_vars($item);
            $item['freight'] = floatval($item['freight']);
            $item['home_pick_up_price'] = floatval($item['home_pick_up_price']);
            // 一件代发价计算
            $item['price'] = bcadd($item['home_pick_up_price'], $item['freight']);
            // 价格格式化
            $item['price_format'] = $currency->format($item['price'], $nowCurrency);
            $item['freight_format'] = $currency->format($item['freight'], $nowCurrency);
            if ($item['home_pick_up_price']) {
                $item['home_pick_up_price_format'] = $currency->format($item['home_pick_up_price'], $nowCurrency);
            }
            // for delete
            $infos = $this->orm->table(DB_PREFIX . 'wk_pro_quote_details')
                ->where(['product_id' => $item['product_id']])
                ->get();
            $item['product_detail_count'] = $infos->count();
            $item['is_middle'] = 0;
            $ids = $infos->pluck('id')->toArray();
            sort($ids);
            if (count($ids) >= 3) {
                array_pop($ids);
                array_shift($ids);
                in_array($item['id'], $ids) && $item['is_middle'] = 1;
            }
            return $item;
        });

        return compact('total', 'rows');
    }

    /**
     * 获取阶梯价格详情
     *
     * @param int $seller_id
     * @param int $product_id
     * @return array
     */
    public function getTieredPriceDetail(int $seller_id, int $product_id): array
    {
        $res = db('oc_wk_pro_quote_details')
            ->select(['template_id', 'min_quantity as min', 'max_quantity as max', 'price', 'home_pick_up_price'])
            ->where(['seller_id' => $seller_id, 'product_id' => $product_id])
            ->orderBy('sort_order')
            ->orderBy('min_quantity')
            ->orderBy('id')
            ->get();
        $res = $res->map(function ($item) {
            $item = get_object_vars($item);
            $item['home_pick_up_price'] = number_format(
                $item['home_pick_up_price'], customer()->isJapan() ? 0 : 2, '.', ''
            );
            $item['price'] = number_format(
                $item['price'], customer()->isJapan() ? 0 : 2, '.', ''
            );
            return $item;
        });

        return $res->toArray();
    }

    /**
     * 保存阶梯价格信息
     *
     * @param int $seller_id
     * @param int $product_id
     * @param array $data
     * @return bool
     */
    public function addTieredPrice(int $seller_id, int $product_id, array $data): bool
    {
        $resolveData = [];
        $res = true;
        $db = $this->orm->getConnection();
        // 预处理数据
        foreach ($data as $k => $v) {
            if (
                (is_int($v['min']) || (is_string($v['min']) && (strlen($v['min']) > 0)))
                && (is_int($v['max']) || (is_string($v['max']) && (strlen($v['max']) > 0)))
                && (is_int($v['price']) || is_float($v['price']) || (is_string($v['price']) && (strlen($v['price']) > 0)))
            ) {
                array_push($resolveData, [
                    'min' => (int)$v['min'],
                    'max' => (int)$v['max'],
                    'price' => (float)$v['price'],
                    'home_pick_up_price' =>
                        ($v['home_pick_up_price'] != '' && $v['home_pick_up_price'] != null)
                            ? $v['home_pick_up_price']
                            : null,
                ]);
            }
        }
        try {
            $db->beginTransaction();
            $db->table('oc_wk_pro_quote_details')
                ->where(['seller_id' => $seller_id, 'product_id' => $product_id,])
                ->delete();
            foreach ($resolveData as $k => $item) {
                $insertData = [
                    'seller_id' => $seller_id,
                    'product_id' => $product_id,
                    'min_quantity' => $item['min'],
                    'max_quantity' => $item['max'],
                    'price' => $item['price'],
                    'home_pick_up_price' => $item['home_pick_up_price'],
                    'sort_order' => (int)$k,
                    'create_time' => Carbon::now(),
                    'update_time' => Carbon::now(),
                ];
                $id = $db->table('oc_wk_pro_quote_details')->insertGetId($insertData);
                $templateSuffix = date('Ymd');
                if (strlen($id) <= 6) {
                    $templateSuffix .= str_repeat('0', 6 - strlen($id)) . $id;
                } else {
                    $templateSuffix .= substr($id, strlen($id) - 6);
                }
                $db->table('oc_wk_pro_quote_details')
                    ->where(['id' => $id])
                    ->update(['template_id' => $templateSuffix]);
                $db->commit();
            }
        } catch (Exception $e) {
            $this->log->write($e->getMessage());
            $res = false;
            $db->rollBack();
        }

        return $res;
    }

    /**
     * @param int $seller_id
     * @param int $product_id
     * user：wangjinxin
     * date：2019/10/30 17:00
     * @return bool
     */
    public function delTieredPrice(int $seller_id, int $product_id): bool
    {
        $ids = $this->orm->table('oc_wk_pro_quote_details')
            ->where(['seller_id' => $seller_id, 'product_id' => $product_id])
            ->pluck('id')
            ->toArray();
        $ret = true;
        foreach ($ids as $id) {
            $ret = $ret && $this->delTieredPriceById($seller_id, $id);
        }

        return $ret;
    }

    /**
     * @param int $seller_id
     * @param int $id
     * @return bool
     * user：wangjinxin
     * date：2019/10/30 16:42
     */
    public function delTieredPriceById(int $seller_id, int $id): bool
    {
        $info = $this->orm->table('oc_wk_pro_quote_details')
            ->where(['seller_id' => $seller_id, 'id' => $id])
            ->first();
        if (!$info) return true;
        // 校验删除id 顺序 不能从中间开始删除
        $info = get_object_vars($info);
        $product_id = $info['product_id'];
        $ids = $this->orm->table('oc_wk_pro_quote_details')
            ->where(['seller_id' => $seller_id, 'product_id' => $product_id,])
            ->orderBy('id')
            ->pluck('id')
            ->toArray();
        if (count($ids) >= 3) {
            array_pop($ids);
            array_shift($ids);
            if (in_array($id, $ids)) return false;
        }
        $ret = true;
        $db = $this->orm->getConnection();
        try {
            $db->beginTransaction();
            $db->table('oc_wk_pro_quote_details')->where(['id' => $id])->delete();
            $db->commit();
        } catch (Exception $e) {
            $ret = false;
            $this->log->write($e->getMessage());
            $db->rollBack();
        }

        return $ret;
    }

    /**
     * 根据用户id获取议价列表
     *
     * @param int $seller_id
     * @param array $filter_data
     * @return array
     * @throws Exception
     */
    public function getNegotiatedPriceList(int $seller_id, array $filter_data): array
    {
        $co = new Collection($filter_data);
        /** @var Config $config */
        $config = $this->config;
        $country_id = $this->orm
            ->table('oc_customer')
            ->where('customer_id', $seller_id)
            ->value('country_id');
        $query = $this->orm->table(DB_PREFIX . 'wk_pro_quote_list as pql')
            ->select(['pql.product_id', 'pql.seller_id', 'p.price', 'p.freight', 'p.sku', 'p.mpn',])
            ->join(DB_PREFIX . 'product as p', ['p.product_id' => 'pql.product_id'])
            ->where([
                'pql.seller_id' => $seller_id,
            ])
            ->when(trim($co->get('filter_sku_mpn')), function (Builder $q) use ($co) {
                $q->where(function (Builder $q) use ($co) {
                    $co['filter_sku_mpn'] = trim($co['filter_sku_mpn']);
                    $q->orWhere('p.sku', 'LIKE', "%{$co['filter_sku_mpn']}%");
                    $q->orWhere('p.mpn', 'LIKE', "%{$co['filter_sku_mpn']}%");
                });
            });
        // 总数
        $total = $query->count();
        // 分页
        if ($co->has($config->get('page')) && $co->has($config->get('per_page'))) {
            $query = $query->forPage(
                $co->get($config->get('page'), 1),
                $co->get($config->get('per_page'), 15)
            );
        }
        $this->load->model('localisation/currency');
        /** @var ModelLocalisationCurrency $mlc */
        $mlc = $this->model_localisation_currency;
        $currency = $mlc->getCurrencyCodeBySellerId($seller_id);
        $rows = $query->get()->map(function ($item) use ($currency, $country_id) {
            $item = get_object_vars($item);
            $item['freight'] = $item['freight'] ?: 0.00;
            $pick_up_price = bcsub($item['price'], $item['freight'], 2);
            // 判断国籍 目前只有美国有一件代发 和 上门取货的区别
            if ($country_id == AMERICAN_COUNTRY_ID) {
                $item['pick_up_price'] = (bccomp($pick_up_price, 0) === 1) ? $pick_up_price : 0;
            } else {
                $item['pick_up_price'] = $item['price'];
            }
            $item['price_format'] = $this->currency->format($item['price'], $currency);
            $item['freight_format'] = $this->currency->format($item['freight'], $currency);
            $item['pick_up_price_format'] = $this->currency->format($item['pick_up_price'], $currency);
            return $item;
        })->toArray();
        return compact('total', 'rows');
    }

    /**
     * 添加或者修改配置
     *
     * @param int $seller_id
     * @param int $status
     * @return bool
     */
    public function changeNegPriceOption(int $seller_id, int $status): bool
    {

        $db = $this->orm->getConnection();
        $ret = true;
        try {
            $db->beginTransaction();
            $res = $this->orm
                ->table('oc_wk_pro_quote')
                ->where('seller_id', $seller_id)
                ->first();
            if (!$res) {
                $this->orm
                    ->table('oc_wk_pro_quote')
                    ->insert([
                        'seller_id' => $seller_id,
                        'status' => $status,
                        'product_ids' => '',
                        'quantity' => 1,
                    ]);
            } else {
                $this->orm
                    ->table('oc_wk_pro_quote')
                    ->where('seller_id', $seller_id)
                    ->update(['status' => $status,]);
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $ret = false;
        };

        return $ret;
    }

    /**
     * @param int $seller_id
     * @param array $product_ids
     * @return bool
     */
    public function addNegotiatedPrice(int $seller_id, array $product_ids): bool
    {
        if (count($product_ids) === 0) return true;
        $db = $this->orm->getConnection();
        $ret = true;
        try {
            $db->beginTransaction();
            // 排除已经存在的product_id 避免重复插入
            $existIds = $db->table(DB_PREFIX . 'wk_pro_quote_list')
                ->where(['seller_id' => $seller_id])
                ->whereIn('product_id', $product_ids)
                ->pluck('product_id')
                ->toArray();
            $product_ids = array_diff($product_ids, $existIds);
            $insertArr = array_map(function ($item) use ($seller_id) {
                return [
                    'seller_id' => $seller_id,
                    'product_id' => $item,
                ];
            }, $product_ids);
            count($insertArr) > 0 && $db->table(DB_PREFIX . 'wk_pro_quote_list')->insert($insertArr);
            $db->commit();
        } catch (Exception $e) {
            $ret = false;
            $db->rollBack();
        }

        return $ret;
    }

    /**
     * 删除可议价产品
     *
     * @param int   $sellerId
     * @param array $productIds
     *
     * @return int
     */
    public function deleteNegotiatedPriceByProducts(int $sellerId,array $productIds)
    {
        return $this->orm->table(DB_PREFIX . 'wk_pro_quote_list')->where('seller_id', $sellerId)
                  ->whereIn('product_id', $productIds)->delete();
    }
}
