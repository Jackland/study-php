<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Class ModelAccountCustomerpartnerProductOptions
 */
class ModelAccountCustomerpartnerProductOptions extends Model
{

    /**
     * 编辑options
     *
     * @param int $customerId
     * @param int $optionId
     * @param array $data
     * @return bool
     */
    public function editOptions(int $customerId, int $optionId, array $data): bool
    {
        $res = true;
        try {
            $this->orm->getConnection()->transaction(function () use ($customerId, $optionId, $data) {
                $optionValues = $data['option_value'] ?? [];
                // 清理option_value ,option_value_description里面的无效数据,即去除所有不被任何商品使用的属性
                $newOptionValueIds = [];
                foreach ($optionValues as $val) {
                    $newOptionValueIds[] = (int)$val['option_value_id'];
                }
                $oldOptionValueIds = $this->orm
                    ->table(DB_PREFIX . 'customer_option')
                    ->where(['option_id' => $optionId, 'customer_id' => $customerId])
                    ->pluck('option_value_id')
                    ->toArray();
                $customerOptionValueIds = $this->orm
                    ->table(DB_PREFIX . 'customer_option')
                    ->where(['option_id' => $optionId])
                    ->where('customer_id', '!=', $customerId)
                    ->distinct()
                    ->pluck('option_value_id')
                    ->toArray();
                $productOptionValueIds = $this->orm
                    ->table(DB_PREFIX . 'product_option_value')
                    ->where(['option_id' => $optionId])
                    ->distinct()
                    ->pluck('option_value_id')
                    ->toArray();
                $intersectValueIds = array_values(array_diff(
                    $oldOptionValueIds,
                    $newOptionValueIds,
                    $customerOptionValueIds,
                    $productOptionValueIds
                ));
                if (!empty($intersectValueIds)) {
                    $this->orm->table(DB_PREFIX . 'option_value')
                        ->whereIn('option_value_id', $intersectValueIds)
                        ->delete();
                    $this->orm->table(DB_PREFIX . 'option_value_description')
                        ->whereIn('option_value_id', $intersectValueIds)
                        ->delete();
                }

                // 删除oc_customer_option oc_customer_option_description里对应optionId的所有内容
                $this->orm->table(DB_PREFIX . 'customer_option')
                    ->where(['option_id' => $optionId, 'customer_id' => $customerId])
                    ->delete();
                $this->orm->table(DB_PREFIX . 'customer_option_description')
                    ->where(['option_id' => $optionId, 'customer_id' => $customerId])
                    ->delete();
                foreach ($optionValues as $item) {
                    if (!isset($item['option_value_description']) || empty($item['option_value_description'])) {
                        continue;
                    }
                    $item['sort_order'] = (int)($item['sort_order'] ?? 0);
                    $optionValueId = $item['option_value_id'];
                    if (empty($optionValueId)) {
                        $optionValueId = $this->orm->table(DB_PREFIX . 'option_value')->insertGetId([
                            'option_id' => $optionId,
                            'image' => $item['image'],
                            'sort_order' => $item['sort_order'],
                        ]);
                    }
                    $this->orm->table(DB_PREFIX . 'customer_option')->insert([
                        'customer_id' => $customerId,
                        'option_id' => $optionId,
                        'option_value_id' => $optionValueId,
                        'image' => $item['image'],
                        'sort_order' => $item['sort_order'],
                    ]);
                    foreach ($item['option_value_description'] as $langId => $value) {
                        $tempArr = [
                            'option_id' => $optionId,
                            'option_value_id' => $optionValueId,
                            'language_id' => $langId,
                            'name' => $value['name'],
                        ];
                        // 查找oc_option_value_description
                        // 数据是否已经存在
                        $existLine = $this->orm
                            ->table(DB_PREFIX . 'option_value_description')
                            ->where([
                                'option_id' => $optionId,
                                'option_value_id' => $optionValueId,
                                'language_id' => $langId,
                            ])
                            ->first();
                        // 新的数据插入oc_option_value_description中
                        !$existLine && $this->orm->table(DB_PREFIX . 'option_value_description')->insert($tempArr);
                        $tempArr['customer_id'] = $customerId;
                        $this->orm->table(DB_PREFIX . 'customer_option_description')->insert($tempArr);
                    }
                }
            });
        } catch (Throwable $e) {
            $this->log->write($e->getMessage());
            $res = false;
        }

        return $res;
    }

    /**
     * 获取option详细信息
     *
     * @param int $optionId
     * @return array|null
     */
    public function getOptionDetail(int $optionId): ?array
    {
        $res = $this->orm
            ->table(DB_PREFIX . 'option as o')
            ->leftJoin(DB_PREFIX . 'option_description as od', 'o.option_id', '=', 'od.option_id')
            ->where([
                'od.language_id' => (int)$this->config->get('config_language_id'),
                'o.option_id' => $optionId
            ])
            ->first();

        return $res ? get_object_vars($res) : null;
    }

    /**
     * 获取option列表
     *
     * @param int $optionId
     * @param int $customerId
     * @return array
     */
    public function getOptionsList(int $optionId, int $customerId): array
    {
        $res = $this->orm
            ->table(DB_PREFIX . 'customer_option as ov')
            ->leftJoin(DB_PREFIX . 'option_value as ova', ['ova.option_value_id' => 'ov.option_value_id'])
            ->select(['ov.*', 'ova.image'])
            ->where(['ov.option_id' => $optionId, 'ov.customer_id' => $customerId])
            ->orderBy('ov.sort_order', 'ASC')
            ->get();
        $res = $res->map(function ($item) use ($customerId) {
            // 这里循环里面加了sql语句
            $re = $this->orm
                ->table(DB_PREFIX . 'customer_option_description')
                ->where([
                    'customer_id' => $customerId,
                    'option_value_id' => $item->option_value_id,
                    'language_id' => (int)$this->config->get('config_language_id'),
                ])
                ->get();
            if ($re->isEmpty()) {
                return [];
            }
            $data = [];
            foreach ($re as $ovd) {
                $data[$ovd->language_id] = ['name' => $ovd->name];
            }
            $arrRes = get_object_vars($item);
            $arrRes['option_value_description'] = $data;

            return $arrRes;
        });

        $res = $res->reject(function ($item) {
            return empty($item);
        });

        return $res->toArray();
    }

    /**
     * 获取option总数
     *
     * @return int
     */
    public function getTotalOptions(): int
    {
        return $this->orm
            ->table(DB_PREFIX . 'option')
            ->count();
    }

    /**
     * 获取option列表
     *
     * @param array $data
     * @return array
     */
    public function getOptions($data = [])
    {
        $co = new Collection($data);
        $query = $this->orm
            ->table(DB_PREFIX . 'option as o')
            ->leftJoin(DB_PREFIX . 'option_description as od', 'o.option_id', '=', 'od.option_id')
            ->where([
                'od.language_id' => (int)$this->config->get('config_language_id'),
            ]);
        $query->when(!empty($co->get('filter_name')), function (Builder $q) use ($co) {
            $q->where('od.name', 'like', $co->get('filter_name') . '%');
        });

        $sort_data = [
            'od.name',
            'o.type',
            'o.sort_order'
        ];
        $sort = $co->has('sort') && in_array($co->get('sort'), $sort_data)
            ? $co->get('sort')
            : 'od.name';
        $order = $co->has('order') && ($co->get('order') == 'DESC')
            ? 'DESC'
            : 'ASC';
        $query->orderBy($sort, $order);
        // limit
        $page = $co->get('page', 1);
        $page = $page >= 1 ? $page : 1;
        $perPage = $co->get('perPage', 20);
        $perPage = $perPage >= 1 ? $perPage : 20;
        $query->forPage($page, $perPage);
        $res = $query->get();
        $res = $res->map(function ($item) {
            return get_object_vars($item);
        });

        return $res->toArray();
    }

    /**
     * 校验对应属性是否有对应的商品 并 返回
     *
     * @param int $customerId
     * @param int $optionId
     * @param int $optionValueId
     * @return array|null
     */
    public function checkCustomerOptionValue(int $customerId, int $optionId, int $optionValueId): ?array
    {
        $res = $this->orm
            ->table(DB_PREFIX . 'product_option_value as pov')
            ->leftJoin(DB_PREFIX . 'product as p', ['p.product_id' => 'pov.product_id'])
            ->select(['p.product_id', 'p.sku', 'p.mpn', 'pov.product_option_value_id as pov_id', 'pov.product_option_id as po_id'])
            ->where([
                'pov.option_id' => $optionId,
                'pov.option_value_id' => $optionValueId,
            ])
            ->whereIn('pov.product_id', function (Builder $q) use ($customerId) {
                $q->select('product_id')
                    ->from(DB_PREFIX . 'customerpartner_to_product')
                    ->where(['customer_id' => $customerId]);
            })
            ->first();

        return $res ? get_object_vars($res) : null;
    }

    /**
     * 校验用户是否有对应的某个属性值
     *
     * @param int $optionId
     * @param string $optionValue
     * @param int $customerId
     * @return bool
     */
    public function checkCustomerOptionValueExist(int $optionId, string $optionValue, int $customerId): bool
    {
        $optionValue = html_entity_decode($optionValue);
        $res = $this->orm
            ->table(DB_PREFIX . 'customer_option_description')
            ->where([
                'customer_id' => $customerId,
                'name' => $optionValue,
                'option_id' => $optionId,
            ])
            ->first();
        return $res ? true : false;
    }

    /**
     * @param int $customerId
     * @param int $optionId
     * @param array $data
     * @return array
     */
    public function getAutoCompleteOptionList(int $customerId, int $optionId, array $data = []): array
    {
        $co = new Collection($data);
        $query = $this->orm
            ->table(DB_PREFIX . 'customer_option_description as cod')
            ->leftJoin(DB_PREFIX . 'customer_option as co',
                [
                    'co.option_value_id' => 'cod.option_value_id',
                    'co.customer_id' => 'cod.customer_id'
                ]
            )
            ->select(['cod.option_value_id as id', 'cod.name as color'])
            ->where([
                'cod.language_id' => (int)$this->config->get('config_language_id'),
                'cod.customer_id' => $customerId,
                'cod.option_id' => $optionId,
            ])
            ->orderBy('co.sort_order', 'ASC');
        if ($filter_name = $co->get('filter_name')) {
            $filter_name = urldecode($filter_name);
            $filter_name = preg_replace('/[^0-9a-zA-Z]/', ' ', $filter_name);
            $filter_name = array_filter(explode(' ', $filter_name));
            $query->where(function (Builder $q) use ($filter_name) {
                foreach ($filter_name as $val) {
                    $q->where('cod.name', 'like', "%{$val}%");
                }
            });
        }
        $res = $query->take((int)$co->get('perPage', 10))->get();
        $res = $res->map(function ($item) {
            $item = get_object_vars($item);
            $item['color'] = htmlspecialchars_decode($item['color']);
            return $item;
        });

        return $res->toArray();
    }

    /**
     * @param int $optionId
     * @param array $data
     * @return array
     */
    public function getAutoCompleteOriginOptionList(int $optionId, array $data = []): array
    {
        $co = new Collection($data);
        $query = $this->orm
            ->table(DB_PREFIX . 'option_value as ov')
            ->join(DB_PREFIX . 'option_value_description as ovd', 'ovd.option_value_id', '=', 'ov.option_value_id')
            ->select(['ovd.option_value_id as id', 'ovd.name as color'])
            ->where([
                'ovd.language_id' => (int)$this->config->get('config_language_id'),
                'ovd.option_id' => $optionId,
            ])
            ->orderBy('ov.sort_order', 'ASC');
        if ($filter_name = $co->get('filter_name')) {
            $filter_name = urldecode($filter_name);
            $filter_name = preg_replace('/[^0-9a-zA-Z]/', ' ', $filter_name);
            $filter_name = array_filter(explode(' ', $filter_name));
            $query->where(function (Builder $q) use ($filter_name) {
                foreach ($filter_name as $val) {
                    $q->where('ovd.name', 'like', "%{$val}%");
                }
            });
        }
        $res = $query->take((int)$co->get('perPage', 10))->get();
        $res = $res->map(function ($item) {
            return get_object_vars($item);
        });

        return $res->toArray();
    }

    /**
     * @param int $customerId
     * @return bool
     */
    public function refreshOptionsByCustomerId(int $customerId): bool
    {
        $res = $this->orm
            ->table(DB_PREFIX . 'product_option_value as pov')
            ->leftJoin(DB_PREFIX . 'option_value as ov', ['pov.option_value_id' => 'ov.option_value_id'])
            ->select(['ov.*'])
            ->orderBy('ov.sort_order', 'ASC')
            ->groupBy('pov.option_value_id')
            ->whereIn('pov.product_id', function (Builder $q) use ($customerId) {
                $q->select('product_id')
                    ->from(DB_PREFIX . 'customerpartner_to_product')
                    ->where(['customer_id' => $customerId]);
            })->get();

        $res = $res->reject(function ($item) {
            return empty($item->option_id) || empty($item->option_value_id);
        });
        if ($res->isEmpty()) {
            return true;
        }
        $res = $res->map(function ($item) use ($customerId) {
            $arr = get_object_vars($item);
            $arr['customer_id'] = $customerId;
            return $arr;
        });
        $res = $res->toArray();
        // 获取option_value_description里的数据
        $resOptionValue = [];
        foreach ($res as $item) {
            $tRes = $this->orm
                ->table(DB_PREFIX . 'option_value_description')
                ->where(['option_id' => $item['option_id'], 'option_value_id' => $item['option_value_id']])
                ->get();
            if ($tRes->isEmpty()) {
                continue;
            }
            $tRes = $tRes->map(function ($item) use ($customerId) {
                $arr = get_object_vars($item);
                $arr['customer_id'] = $customerId;
                return $arr;
            });
            $resOptionValue = array_merge($resOptionValue, $tRes->toArray());
        }
        try {
            $this->orm->getConnection()->transaction(function () use ($res, $resOptionValue, $customerId) {
                $this->orm->table(DB_PREFIX . "customer_option")->where(['customer_id' => $customerId])->delete();
                $this->orm->table(DB_PREFIX . "customer_option")->insert($res);
                $this->orm->table(DB_PREFIX . "customer_option_description")->where(['customer_id' => $customerId])->delete();
                $this->orm->table(DB_PREFIX . "customer_option_description")->insert($resOptionValue);
            });
            $ret = true;
        } catch (Throwable $e) {
            $this->log->write($e->getMessage());
            $ret = false;
        }
        return $ret;
    }

    /**
     * 更新全部options 到 oc_customer_option
     */
    public function refreshTotalOptions()
    {
        // 第一步获取所有关联产品的customer_id 账号
        $res = $this->orm
            ->table(DB_PREFIX . 'customerpartner_to_product')
            ->where('customer_id', '>', 0)
            ->select('customer_id')
            ->distinct()
            ->pluck('customer_id')
            ->toArray();

        $this->orm->table(DB_PREFIX . "customer_option")->delete();
        $this->orm->table(DB_PREFIX . "customer_option_description")->delete();
        foreach ($res as $c_id) {
            $this->refreshOptionsByCustomerId($c_id);
        }
    }
}