<?php

/**
 * Class ModelCustomerpartnerMarketingcampaignRequest
 */
class ModelCustomerpartnerMarketingcampaignRequest extends Model
{
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->table = 'oc_marketing_campaign_request';
    }

//region check

    /**
     * 验证当前促销互动是否允许申请
     *
     * @param int $id
     * @return bool
     */
    public function checkMarketingCampaignCanApply($id)
    {
        $now = date('Y-m-d H:i:s');
        return $this->orm->table('oc_marketing_campaign')
            ->where([
                ['id', '=', $id],
                ['apply_start_time', '<=', $now],
                ['apply_end_time', '>', $now],
                ['is_release', '=', 1],
            ])
            ->exists();
    }

//end of region check

    /**
     * @param int $id
     * @param int $country_id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getActivityInfo($id, $country_id)
    {
        $now = date('Y-m-d H:i:s');
        return $this->orm->table('oc_marketing_campaign')
            ->where([
                ['id', '=', $id],
                ['apply_start_time', '<=', $now],
                ['apply_end_time', '>', $now],
                ['is_release', '=', 1],
                ['country_id', '=', $country_id]
            ])
            ->first(['*']);
    }

    /**
     * 验证 当前是否已有申请存在 <==> 否能可以再次申请
     * @param int $id request表的主键
     * @param int $seller_id
     * @return boolean
     */
    public function checkHasRequest($id, $seller_id)
    {
        return $this->orm->table($this->table)
            ->where([
                ['mc_id', '=', $id],
                ['seller_id', '=', $seller_id],
            ])
            ->whereIn('status', [1, 2])
            ->exists();
    }

    /**
     * 新增 banner类活动的申请
     * @param $data
     */
    public function saveBanner($data)
    {
        $keyVal = [
            'mc_id' => $data['mc_id'],
            'seller_id' => $data['seller_id'],
            'status' => 1,
            'banner_image' => $data['banner_image'],
            'banner_url' => $data['banner_url'],
            'banner_description' => $data['banner_description'],
            'create_time' => date('Y-m-d H:i:s'),
        ];
        return $this->orm->table($this->table)->insertGetId($keyVal);
    }

    /**
     *
     * @param int $id oc_marketing_campaign.id
     * @param int $request_id
     * @param int $seller_id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getBannerRequestForReapply($id, $request_id, $seller_id)
    {
        return $this->orm->table($this->table . ' as mcr')
            ->join('oc_marketing_campaign as mc', 'mc.id', '=', 'mcr.mc_id')
            ->select([
                'mcr.banner_image',
                'mcr.banner_url',
                'mcr.banner_description'
            ])
            ->where([
                ['mcr.id', '=', $request_id],
                ['mcr.mc_id', '=', $id],
                ['mcr.seller_id', '=', $seller_id],
                ['mc.type', '=', 1],
            ])
            ->whereIn('mcr.status', [3, 4])
            ->first();
    }

    /**
     * @param int $id
     * @param int $request_id
     * @param int $seller_id
     * @return array
     */
    public function getNormalRequestForReapply($id, $request_id, $seller_id)
    {
        return $this->orm->table($this->table . ' as mcr')
            ->join('oc_marketing_campaign_request_product as mcrp', 'mcrp.mc_request_id', '=', 'mcr.id')
            ->where([
                ['mcr.id', '=', $request_id],
                ['mcr.mc_id', '=', $id],
                ['mcr.seller_id', '=', $seller_id],
                ['mcrp.status', '=', 1],
            ])
            ->whereIn('mcr.status', [3, 4])
            ->pluck('mcrp.product_id')
            ->toArray();
    }

    /**
     * 指定 活动已报名的人数
     *
     * @param int $id
     * @return int
     */
    public function countRequest($id)
    {
        return $this->orm->table($this->table)
            ->where([
                ['mc_id', '=', $id],
                ['status', '=', 2]
            ])
            ->count();
    }

    /**
     * 获取可以申请活动的产品
     *
     * @param int $seller_id
     * @param array $filter_data
     * @return \Illuminate\Support\Collection
     */
    public function getCanApplyProducts($seller_id, $filter_data)
    {
        return $this->orm->table('oc_customerpartner_to_product as ctp')
            ->join('oc_product as p', 'p.product_id', '=', 'ctp.product_id')
            ->join('oc_product_description as pd', 'pd.product_id', '=', 'ctp.product_id')
            ->where([
                ['ctp.customer_id', '=', $seller_id],
                ['p.status', '=', 1],
                ['p.is_deleted', '=', 0],
                ['p.buyer_flag', '=', 1],
                ['p.product_type', '=', 0], // 0-> normal,
            ])
            ->where('ctp.quantity', '>=', $filter_data['require_pro_min_stock'])
            ->when(isset_and_not_empty($filter_data, 'require_pro_start_time'), function ($query) use ($filter_data) {
                $query->where('p.date_added', '>=', $filter_data['require_pro_start_time']);
            })
            ->when(isset_and_not_empty($filter_data, 'require_pro_end_time'), function ($query) use ($filter_data) {
                $query->where('p.date_added', '<=', $filter_data['require_pro_end_time']);
            })
            ->when(isset_and_not_empty($filter_data, 'require_category_arr'), function ($query) use ($filter_data) {
                $query->join('oc_product_to_category as ptc', 'ptc.product_id', '=', 'ctp.product_id')
                    ->whereIn('ptc.category_id', $this->getCategoryByParent($filter_data['require_category_arr']));
            })
            ->select([
                'p.product_id', 'p.sku', 'p.mpn', 'p.image', 'pd.name as product_name',
                'ctp.price', 'ctp.quantity',
            ])
            ->orderBy('ctp.quantity', 'DESC')
            ->distinct()
            ->get();
    }

    /**
     * 验证 用户提交的product的合法性
     *
     * @param int $seller_id
     * @param array $filter_data
     * @param array $product_ids
     * @return bool
     */
    public function checkRequestProducts($seller_id, $filter_data, $product_ids)
    {
        $db_product_ids = $this->orm->table('oc_customerpartner_to_product as ctp')
            ->join('oc_product as p', 'p.product_id', '=', 'ctp.product_id')
            ->join('oc_product_description as pd', 'pd.product_id', '=', 'ctp.product_id')
            ->where([
                ['ctp.customer_id', '=', $seller_id],
                ['p.status', '=', 1],
                ['p.is_deleted', '=', 0],
                ['p.buyer_flag', '=', 1],
                ['p.product_type', '=', 0], // 0-> normal,
            ])
            ->where('ctp.quantity', '>=', $filter_data['require_pro_min_stock'])
            ->when(isset_and_not_empty($filter_data, 'require_pro_start_time'), function ($query) use ($filter_data) {
                $query->where('p.date_added', '>=', $filter_data['require_pro_start_time']);
            })
            ->when(isset_and_not_empty($filter_data, 'require_pro_end_time'), function ($query) use ($filter_data) {
                $query->where('p.date_added', '<=', $filter_data['require_pro_end_time']);
            })
            ->when(isset_and_not_empty($filter_data, 'require_category_arr'), function ($query) use ($filter_data) {
                $query->join('oc_product_to_category as ptc', 'ptc.product_id', '=', 'ctp.product_id')
                    ->whereIn('ptc.category_id', $this->getCategoryByParent($filter_data['require_category_arr']));
            })
            ->whereIn('p.product_id', $product_ids)
            ->distinct()
            ->pluck('p.product_id')
            ->toArray();
        return empty(array_diff($db_product_ids, $product_ids));
    }

    /**
     * 新增 banner类活动的申请
     * @param array $data
     * @return int
     */
    public function saveNormal($data)
    {
        $keyVal = [
            'mc_id' => $data['mc_id'],
            'seller_id' => $data['seller_id'],
            'status' => 1,
            'create_time' => date('Y-m-d H:i:s'),
        ];

        $mc_request_id = $this->orm->table($this->table)->insertGetId($keyVal);
        $keyValArr = [];
        foreach ($data['product_ids'] as $product_id) {
            $keyValArr[] = [
                'mc_id' => $data['mc_id'],
                'mc_request_id' => $mc_request_id,
                'product_id' => $product_id,
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s')
            ];
        }
        $keyValArr && $this->orm->table('oc_marketing_campaign_request_product')->insert($keyValArr);

        return $mc_request_id;
    }

    /**
     * @param array $category
     * @return array
     */
    public function getCategoryByParent($category): array
    {
        if (empty($category)) {
            return $category;
        }

        $all_category = $this->getAllCategory();

        $temp = [];
        foreach ($category as $category_id) {
            if (isset($all_category[$category_id])) {
                $temp = array_merge($all_category[$category_id]['children_ids'] ?? [$category_id], $temp);
            }
        }

        return array_unique(array_merge($temp, $category));
    }

    /**
     * 此处使用引用，而不是递归，故做两边遍历循环
     * @return array
     */
    public function getAllCategory(): array
    {
        $objs = $this->orm->table('oc_category')
            ->select(['category_id', 'parent_id'])
            ->get();
        $parent_category = [];
        foreach ($objs as $obj) {
            $parent_category[$obj->parent_id]['data'][$obj->category_id] = [
                'category_id' => $obj->category_id,
                'parent_id' => $obj->parent_id,
            ];
        }

        unset($parent_category[0]);
        foreach ($parent_category as $_parent_id => &$category_arr) {
            foreach ($category_arr['data'] as $category_id => &$category) {
                if (isset($parent_category[$category['category_id']])) {    //当前category 拥有子节点
                    $category['data'] = $parent_category[$category['category_id']]['data'];
                    $category['children_ids'] = array_unique(
                        array_merge(
                            $category['children_ids'] ?? [],    //
                            $parent_category[$category['category_id']]['children_ids'] ?? [],
                            array_keys($parent_category[$category['category_id']]['data'] ?? [])
                        )
                    );
                }
                $category_arr['children_ids'] = array_unique(
                    array_merge(
                        $category_arr['children_ids'] ?? [],
                        array_keys($category['data'] ?? []),
                        $category['children_ids'] ?? []
                    )
                );
            }
            $category_arr['children_ids'] = array_unique(
                array_merge(
                    $category_arr['children_ids'] ?? [],
                    array_keys($category_arr['data'] ?? [])
                )
            );

        }
        unset($category_arr, $category);
        // 下面的代码 是特意重复的，不要删啊，不然获取的数据会缺少一部分的。
        foreach ($parent_category as $_parent_id => &$category_arr) {
            foreach ($category_arr['data'] as $category_id => &$category) {
                if (isset($parent_category[$category['category_id']])) {    //当前category 拥有子节点
                    $category['data'] = $parent_category[$category['category_id']]['data'];
                    $category['children_ids'] = array_unique(
                        array_merge(
                            $category['children_ids'] ?? [],    //
                            $parent_category[$category['category_id']]['children_ids'] ?? [],
                            array_keys($parent_category[$category['category_id']]['data'] ?? [])
                        )
                    );
                }
                $category_arr['children_ids'] = array_unique(
                    array_merge(
                        $category_arr['children_ids'] ?? [],
                        array_keys($category['data'] ?? []),
                        $category['children_ids'] ?? []
                    )
                );
            }
            $category_arr['children_ids'] = array_unique(
                array_merge(
                    $category_arr['children_ids'] ?? [],
                    array_keys($category_arr['data'] ?? [])
                )
            );

        }

        return $parent_category;
    }

    /**
     * @return string[]
     */
    public function getBannerSetting()
    {
        $setting = $this->orm->table('oc_module')
            ->where('code', '=', 'slideshow')
            ->value('setting');

        $tempSetting = [
            "width" => "940",
            "height" => "460"
        ];

        if (!empty($setting)) {
            $settingArr = json_decode($setting, true);
            if ($settingArr['status'] ?? 1) {
                $tempSetting['width'] = $settingArr['width'] ?? $tempSetting['width'];
                $tempSetting['height'] = $settingArr['height'] ?? $tempSetting['height'];
            }
        }
        return $tempSetting;
    }
}