<?php

/**
 * Class ModelAccountCustomerpartnerProductGroup
 */
class ModelAccountCustomerpartnerProductGroup extends Model
{
    protected $linkTable;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->table = 'oc_customerpartner_product_group';
        $this->linkTable = 'oc_customerpartner_product_group_link';
    }

//region Group Check

    /**
     * 验证分组名称是否重复
     *
     * @param int $seller_id
     * @param string $name
     * @param int|null $group_id
     * @return bool
     */
    public function checkIsExistedByName($seller_id, $name, $group_id = null)
    {
        $where = [
            ['seller_id', $seller_id],
            ['name', '=', $name],
            ['status', '=', 1]
        ];
        !empty($group_id) && $where[] = ['id', '<>', $group_id];
        return $this->orm->table($this->table)
            ->where($where)
            ->exists();
    }

    /**
     * 验证用户提交的products是否合法
     *
     * @param int $seller_id
     * @param array $productIDs
     * @return bool
     */
    public function checkProductIDs($seller_id, $productIDs)
    {
        $count = $this->orm->table('oc_product as p')
            ->join('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->distinct()
            ->where([
                ['ctp.customer_id', $seller_id],
                ['p.status', 1],
                ['p.buyer_flag', 1],
                ['p.is_deleted', 0]
            ])
            ->whereIn('p.product_id', $productIDs)
            ->count(['p.product_id']);
        return $count == count($productIDs);
    }

    /**
     * 根据 group_id 和 seller_id 验证 改该分组是否存在(仅限seller读取自己的分组)
     *
     * @param int $seller_id
     * @param int $group_id
     * @return bool
     */
    public function checkGroupIsExist($seller_id, $group_id)
    {
        return $this->orm->table($this->table)
            ->where([
                ['id', '=', $group_id],
                ['seller_id', '=', $seller_id],
                ['status', '=', 1]
            ])
            ->exists();
    }
//endregion

//region Group Select
    /**
     * 获取 group 列表
     *
     * @param array $input
     * @param int $seller_id
     * @return array
     */
    public function list($input, $seller_id)
    {
        $where = [
            ['g.seller_id', '=', $seller_id],
            ['g.status', '=', 1]
        ];
        if (isset_and_not_empty($input, 'name')) {
            $where[] = [
                'g.name', 'like', '%' . $input['name'] . '%'
            ];
        }

        $builder = $this->orm->table($this->table . ' as g')
            ->select([
                'g.id', 'g.name', 'g.description', 'g.update_time'
            ])
            ->where($where);
        if (isset_and_not_empty($input, 'sku_mpn')) {
            $sku_mpn = $input['sku_mpn'];
            $builder->join($this->linkTable . ' as gl', 'gl.product_group_id', '=', 'g.id')
                ->join('oc_product as p', 'p.product_id', '=', 'gl.product_id')
                ->distinct()
                ->where([
                    ['gl.status', '=', 1],
                    ['p.status', '=', 1],
                    ['p.buyer_flag', '=', 1],
                    ['p.is_deleted', '=', 0],
                ])
                ->where(function ($query) use ($sku_mpn) {
                    $query->where('p.sku', 'like', '%' . $sku_mpn . '%')
                        ->orWhere('p.mpn', 'like', '%' . $sku_mpn . '%');
                });
        }
        $results['total'] = $builder->count('g.id');
        $results['data'] = $builder->forPage($input['page'], $input['pageSize'])
            ->orderBy('g.id', 'DESC')
            ->get();
        return $results;
    }

    /**
     * 获取所有的group 且不分组
     * @param array $input
     * @param int $seller_id
     * @return \Illuminate\Support\Collection
     */
    public function getAllListAndNoPage($input, $seller_id)
    {
        $where = [
            ['g.seller_id', '=', $seller_id],
            ['g.status', '=', 1]
        ];
        if (isset_and_not_empty($input, 'name')) {
            $where[] = [
                'g.name', 'like', '%' . $input['name'] . '%'
            ];
        }

        return $this->orm->table($this->table . ' as g')
            ->select([
                'g.id', 'g.name', 'g.description', 'g.update_time'
            ])
            ->where($where)
            ->orderBy('g.id', 'DESC')
            ->get();
    }

    /**
     * 获取下载列表
     *
     * @param array $input
     * @param int $seller_id
     * @return array
     */
    public function getDownloadList($input, $seller_id)
    {
        $where = [
            ['g.seller_id', '=', $seller_id],
            ['g.status', '=', 1],
        ];

        if (isset_and_not_empty($input, 'group_id')) {
            $where[] = [
                'g.id', '=', $input['group_id']
            ];
        }

        if (isset_and_not_empty($input, 'name')) {
            $where[] = [
                'g.name', 'like', '%' . $input['name'] . '%'
            ];
        }
        $builder = $this->orm->table($this->table . ' as g');

        if (isset_and_not_empty($input, 'sku_mpn')) {
            $where[] = ['gl.status', '=', 1];
            $where[] = ['p.status', '=', 1];
            $where[] = ['p.buyer_flag', '=', 1];
            $where[] = ['p.is_deleted', '=', 0];

            $sku_mpn = $input['sku_mpn'];
            $builder->join($this->linkTable . ' as gl', 'gl.product_group_id', '=', 'g.id')
                ->join('oc_product as p', 'p.product_id', '=', 'gl.product_id')
                ->where(function ($query) use ($sku_mpn) {
                    $query->where('p.sku', 'like', '%' . $sku_mpn . '%')
                        ->orWhere('p.mpn', 'like', '%' . $sku_mpn . '%');
                });
        }

        $groupObjs = $builder->select([
            'g.id', 'g.name as group_name', 'g.description as group_description',
        ])
            ->where($where)
            ->orderBy('g.id', 'DESC')
            ->distinct()
            ->get();

        $results = [];
        foreach ($groupObjs as $groupObj) {
            $linkObjs = $this->orm->table($this->linkTable . ' as gl')
                ->join('oc_product as p', 'p.product_id', '=', 'gl.product_id')
                ->join('oc_product_description as pd', 'pd.product_id', '=', 'gl.product_id')
                ->select([
                    'p.sku', 'p.mpn', 'pd.name as product_name'
                ])
                ->where([
                    ['gl.product_group_id', $groupObj->id],
                    ['gl.status', '=', 1],
                    ['p.status', '=', 1],
                    ['p.buyer_flag', '=', 1],
                    ['p.is_deleted', '=', 0],
                ])
                ->orderBy('gl.id', 'DESC')
                ->get();
            foreach ($linkObjs as $linkObj) {
                $temp = clone $groupObj;
                $temp->sku = $linkObj->sku;
                $temp->mpn = $linkObj->mpn;
                $temp->product_name = $linkObj->product_name;
                $results[] = $temp;
            }
        }
        return $results;
    }

    /**
     * 获取一条 group 记录
     *
     * @param int $group_id
     * @return null|object
     */
    public function getSingleGroupInfo($group_id)
    {
        return $this->orm->table($this->table)
            ->select(['id', 'name', 'description', 'update_time'])
            ->where([
                ['id', $group_id],
                ['status', 1]
            ])
            ->first();
    }


    /**
     * 根据 group 获取尚未关联该 group 的 product
     *
     * @param int $seller_id
     * @param int $group_id
     * @return \Illuminate\Support\Collection
     */
    public function getProductByGroup($seller_id, $group_id)
    {
        return $this->orm->table('oc_product as p')
            ->join('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->join('oc_product_description as pd', 'pd.product_id', '=', 'p.product_id')
            ->select([
                'p.product_id',
                'p.sku',
                'p.mpn',
                'pd.name'
            ])
            ->where([
                ['ctp.customer_id', $seller_id],
                ['p.buyer_flag', '=', 1],
                ['p.status', '=', 1],
                ['p.is_deleted', '=', 0],
            ])
            ->whereNotExists(function ($query) use ($seller_id, $group_id) {
                $query->from($this->linkTable . ' as gl')
                    ->select(['gl.product_id'])
                    ->where([
                        ['gl.seller_id', $seller_id],
                        ['gl.product_group_id', $group_id],
                        ['gl.status', '=', 1]
                    ])
                    ->whereRaw('gl.product_id = p.product_id');
            })
            ->orderBy('p.product_id', 'DESC')
            ->get();
    }

    /**
     * 获取已建立关联的product
     *
     * @param int $seller_id
     * @param int $group_id
     * @return array
     */
    public function getActiveProductsByGroup($seller_id, $group_id)
    {
        return $this->orm->table($this->linkTable)
            ->where([
                ['seller_id', $seller_id],
                ['product_id', $group_id],
                ['status', '=', '1']
            ])
            ->pluck('product_id')
            ->toArray();
    }

    /**
     * 根据product 获取 改product 存在哪些group中
     *
     * @param int $seller_id
     * @param array $products
     * @return \Illuminate\Support\Collection
     */
    public function getGroupByProducts($seller_id, $products)
    {
        return $this->orm->table($this->table . ' as g')
            ->join($this->linkTable . ' as gl', 'gl.product_group_id', '=', 'g.id')
            ->select([
                'g.id as group_id',
                'gl.product_id', 'g.name'
            ])
            ->where([
                ['g.seller_id', $seller_id],
                ['g.status', '=', 1],
                ['gl.seller_id', $seller_id],
                ['gl.status', 1]
            ])
            ->whereIn('gl.product_id', $products)
            ->get();
    }

    /**
     * 根据 product_ids 获取 group_id
     * @param int $seller_id
     * @param array $product_ids
     * @return array
     */
    public function getGroupIDsByProductIDs($seller_id, $product_ids)
    {
        return $this->orm->table($this->linkTable)
            ->where([
                ['seller_id', '=', $seller_id],
                ['status', '=', 1],
            ])
            ->whereIn('product_id', $product_ids)
            ->distinct()
            ->pluck('product_group_id')
            ->toArray();
    }

    /**
     * @param int $seller_id
     * @param array $product_group_ids
     * @return array
     */
    public function getProductIDsByProductGroups($seller_id,$product_group_ids)
    {
        return $this->orm->table('oc_customerpartner_product_group_link')
            ->where([
                ['status', '=', 1],
                ['seller_id', '=', $seller_id]
            ])
            ->whereIn('product_group_id', $product_group_ids)
            ->distinct()
            ->pluck('product_id')
            ->toArray();
    }

    /**
     * 根据 buyer group 查询 product group 以及分别关联几个 product group
     *
     * @param int $seller_id
     * @param array $buyer_group_ids
     * @return \Illuminate\Support\Collection
     */
    public function getGroupsAndNumByBuyerGroups($seller_id, $buyer_group_ids)
    {
        return $this->orm->table('oc_delicacy_management_group as dmg')
            ->join('oc_customerpartner_product_group as pg', 'pg.id', '=', 'dmg.product_group_id')
            ->select(['pg.name','dmg.buyer_group_id'])
            ->selectRaw('count(dmg.product_group_id) as total')
            ->where([
                ['dmg.seller_id', '=', $seller_id],
                ['dmg.status', '=', 1],
                ['pg.seller_id', '=', $seller_id],
                ['pg.status', '=', 1]
            ])
            ->whereIn('dmg.buyer_group_id', $buyer_group_ids)
            ->groupBy('dmg.buyer_group_id')
            ->get();
    }
//endregion

//region Group Add

    /**
     * 添加 分组及关联的product
     *
     * @param array $input
     */
    public function addGroup($input)
    {
        $product_group_id = $this->orm->table($this->table)
            ->insertGetId([
                'name' => $input['name'],
                'description' => $input['description'],
                'add_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
                'seller_id' => $input['seller_id'],
                'status' => 1
            ]);
        $keyValArr = [];
        $keyVal = [
            'product_group_id' => $product_group_id,
            'seller_id' => $input['seller_id'],
            'add_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
            'status' => 1,
        ];
        foreach ($input['products'] as $product) {
            $keyVal['product_id'] = $product;
            $keyValArr[] = $keyVal;
        }
        $this->orm->table($this->linkTable)
            ->insert($keyValArr);
    }
//endregion

//region Group Update
    /**
     * 更新 group
     *
     * @param array $input
     */
    public function updateGroup($input)
    {
        $this->orm->table($this->table)
            ->where([
                ['seller_id', $input['seller_id']],
                ['id', $input['id']]
            ])
            ->update([
                'update_time' => date('Y-m-d H:i:s'),
                'name' => get_value_or_default($input, 'name', ''),
                'description' => get_value_or_default($input, 'description', '')
            ]);
    }
//endregion

//region Group delete
    /**
     * 删除分组同时删除关联
     * 注：此删除为逻辑删除
     * 删除的时间即为update_time
     *
     * @param int $seller_id
     * @param int $group_id
     */
    public function deleteGroup($seller_id, $group_id)
    {
        $this->orm->table($this->table)
            ->where([
                ['id', $group_id],
                ['seller_id', $seller_id]
            ])
            ->update(['status' => 0, 'update_time' => date('Y-m-d H:i:s')]);

        $this->orm->table($this->linkTable)
            ->where([
                ['product_group_id', $group_id],
                ['seller_id', $seller_id]
            ])
            ->update(['status' => 0, 'update_time' => date('Y-m-d H:i:s')]);

    }
//endregion

//region Link Check


//endregion

//region Link Select
    /**
     * 获取产品信息供选择
     *
     * @param int $seller_id
     * @param string $search_str
     * @return \Illuminate\Support\Collection
     */
    public function getProductInfoBySeller($seller_id, $search_str)
    {
        $builder = $this->orm->table('oc_product as p')
            ->join('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->join('oc_product_description as pd', 'pd.product_id', '=', 'p.product_id')
            ->select([
                'p.product_id',
                'p.sku',
                'p.mpn',
                'pd.name'
            ])
            ->where([
                ['ctp.customer_id', $seller_id],
                ['p.status', '=', 1],
                ['p.buyer_flag', '=', 1],
                ['p.is_deleted', '=', 0],
            ])
            ->whereIn('p.product_type',[0,3]);

        if (!empty($search_str)) {
            $builder->where(function ($query) use ($search_str) {
                $query->where('p.sku', 'like', '%' . $search_str . '%')
                    ->orWhere('p.mpn', 'like', '%' . $search_str . '%');
            });
        }
        return $builder->get();
    }

    /**
     * 根据 group 获取关联信息
     *
     * @param int $seller_id
     * @param int $group_id
     * @param null|string $sku_mpn
     * @return \Illuminate\Support\Collection
     */
    public function getLinkList($seller_id, $group_id, $sku_mpn = null)
    {
        $builder = $this->orm->table($this->linkTable . ' as gl')
            ->join('oc_product as p', 'p.product_id', '=', 'gl.product_id')
            ->join('oc_product_description as pd', 'pd.product_id', '=', 'p.product_id')
            ->select([
                'gl.id as link_id',
                'p.product_id',
                'p.sku', 'p.mpn',
                'p.image as img_url',
                'pd.name'
            ])
            ->where([
                ['gl.seller_id', '=', $seller_id],
                ['gl.product_group_id', $group_id],
                ['gl.status', '=', 1],
                ['p.status', '=', 1],
                ['p.buyer_flag', '=', 1],
                ['p.is_deleted', '=', 0],
            ]);
        if (!empty($sku_mpn)) {
            $builder->where(function ($query) use ($sku_mpn) {
                $query->where('p.sku', 'like', '%' . $sku_mpn . '%')
                    ->orWhere('p.mpn', 'like', '%' . $sku_mpn . '%');
            });
        }
        return $builder->orderBy('id', 'DESC')->get();
    }

    /**
     * 根据 group 获取已建立关联的 buyer group下面的buyer
     *
     * @param int $seller_id
     * @param int $group_id
     * @return array
     */
    public function getLinkedBuyersByGroup($seller_id, $group_id)
    {
        return $this->orm->table('oc_customerpartner_buyer_group_link as bgl')
            ->join('oc_delicacy_management_group as dmg', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->where([
                ['dmg.status', '=', 1],
                ['dmg.product_group_id', '=', $group_id],
                ['dmg.seller_id', '=', $seller_id],
                ['bgl.status', '=', 1],
                ['bgl.seller_id', '=', $seller_id]
            ])
            ->distinct()
            ->pluck('bgl.buyer_id')
            ->toArray();
    }
//endregion

//region Link Add
    /**
     * 添加 group 和 product 的关联
     *
     * @param int $seller_id
     * @param int $group_id
     * @param array $products
     */
    public function addLink($seller_id, $group_id, $products)
    {
        $insertArray = [];
        foreach ($products as $product) {
            $insertArray[] = [
                'product_group_id' => $group_id,
                'product_id' => $product,
                'add_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
                'status' => 1,
                'seller_id' => $seller_id
            ];
        }
        $this->orm->table($this->linkTable)
            ->insert($insertArray);
    }

    /**
     * 添加 group 和 product 的关联
     * @param int $seller_id
     * @param array $groups
     * @param int $product_id
     */
    public function addLinksByProduct($seller_id, $groups, $product_id)
    {
        $insertArray = [];
        foreach ($groups as $group) {
            $insertArray[] = [
                'product_group_id' => $group,
                'product_id' => $product_id,
                'add_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
                'status' => 1,
                'seller_id' => $seller_id
            ];
        }
        $this->orm->table($this->linkTable)
            ->insert($insertArray);
    }
//endregion

//region Link Update
    /**
     * product 更新组关系
     * @param int $seller_id
     * @param array $groups 新的分组
     * @param int $product_id
     */
    public function updateLinkByProduct($seller_id, $groups, $product_id)
    {
        if (empty($groups)) {
            $this->orm->table($this->linkTable)
                ->where([
                    ['seller_id', '=', $seller_id],
                    ['product_id', '=', $product_id],
                    ['status', '=', 1]
                ])
                ->update(['status' => 0, 'update_time' => date('Y-m-d H:i:s')]);
        } else {
            $db_groups = $this->orm->table($this->linkTable)
                ->where([
                    ['seller_id', '=', $seller_id],
                    ['product_id', '=', $product_id],
                    ['status', '=', 1]
                ])
                ->pluck('product_group_id')
                ->toArray();
            $del_groups = array_diff($db_groups, $groups);
            $add_groups = array_diff($groups, $db_groups);
            // Del
            $this->orm->table($this->linkTable)
                ->where([
                    ['seller_id', '=', $seller_id],
                    ['product_id', '=', $product_id],
                    ['status', '=', 1]
                ])
                ->whereIn('product_group_id',$del_groups)
                ->update(['status' => 0, 'update_time' => date('Y-m-d H:i:s')]);
            // Add
            $insertArr = [];
            foreach ($add_groups as $add_group) {
                $add_group && $insertArr[] = [
                    'product_group_id' => $add_group,
                    'product_id' => $product_id,
                    'add_time' => date('Y-m-d H:i:s'),
                    'status' => 1,
                    'update_time' => date('Y-m-d H:i:s'),
                    'seller_id' => $seller_id
                ];
            }
            $this->orm->table($this->linkTable)->insert($insertArr);
        }
    }
//endregion

//region Link delete
    /**
     * 删除关联信息
     *
     * @param int $seller_id
     * @param int $link_id
     */
    public function linkDelete($seller_id, $link_id)
    {
        $this->orm->table($this->linkTable)
            ->where([
                ['seller_id', $seller_id],
                ['id', '=', $link_id]
            ])
            ->update(['status' => 0, 'update_time' => date('Y-m-d H:i:s')]);
    }

    /**
     * 根据 products 从相应的分组中删除
     *
     * @param int $seller_id
     * @param array $product_ids
     */
    public function linkDeleteByProducts($seller_id, $product_ids)
    {
        if (empty($product_ids)) {
            return;
        }
        $this->orm->table($this->linkTable)
            ->where([
                ['seller_id', $seller_id],
                ['status','=',1]
            ])
            ->whereIn('product_id',$product_ids)
            ->update(['status' => 0, 'update_time' => date('Y-m-d H:i:s')]);
    }
//endregion


}
