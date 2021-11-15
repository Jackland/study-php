<?php

use Illuminate\Database\Query\Expression;

/**
 * Class ModelAccountCustomerpartnerBuyerGroup
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 */
class ModelAccountCustomerpartnerBuyerGroup extends Model
{
    protected $linkTable;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->table = 'oc_customerpartner_buyer_group';
        $this->linkTable = 'oc_customerpartner_buyer_group_link';
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
     * 验证用户提交的buyers是否合法
     * 注：排除 已加入分组的buyer
     *
     * @param int $seller_id
     * @param array $buyerIDs
     * @return bool
     */
    public function checkBuyerIDs($seller_id, $buyerIDs)
    {
        $count = $this->orm->table('oc_customer as c')
            ->join('oc_buyer_to_seller as bts', 'bts.buyer_id', '=', 'c.customer_id')
            ->distinct()
            ->where([
                ['bts.seller_id', $seller_id],
                ['c.status', 1],
                ['bts.seller_control_status', 1]
            ])
            ->whereIn('c.customer_id', $buyerIDs)
            ->whereNotExists(function ($query) use ($seller_id) {
                $query->from($this->linkTable . ' as gl')
                    ->select(['gl.buyer_id'])
                    ->where([
                        ['gl.seller_id', $seller_id],
                        ['gl.status', '=', 1]
                    ])
                    ->whereRaw('gl.buyer_id = c.customer_id');
            })
            ->count(['c.customer_id']);
        return $count == count($buyerIDs);
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

    /**
     * 验证当前是否存在默认分组
     *
     * @param int $seller_id
     * @return bool
     */
    public function checkHasDefault($seller_id)
    {
        return $this->orm->table($this->table)
            ->where([
                ['seller_id', $seller_id],
                ['status', 1],
                ['is_default', '=', 1]
            ])
            ->exists();
    }

    /**
     * 验证 该 group 是否 参加组精细化
     *
     * @param int $seller_id
     * @param int $buyer_group_id
     * @return bool
     */
    public function checkIsDelicacyManagementGroupByBGID($seller_id, $buyer_group_id)
    {
        return $this->orm->table('oc_delicacy_management_group')
            ->where([
                ['seller_id', '=', $seller_id],
                ['status', '=', 1],
                ['buyer_group_id', '=', $buyer_group_id]
            ])
            ->exists();
    }

    /**
     * 验证用户是否可用
     *
     * @param int $customer_id
     * @return bool
     */
    public function checkCustomerActive($customer_id): bool
    {
        if (empty($customer_id)) {
            return false;
        }
        return $this->orm->table('oc_customer')
            ->where([
                ['customer_id', '=', (int)$customer_id],
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
                'g.id', 'g.name', 'g.description', 'g.update_time', 'g.is_default'
            ])
            ->where($where);
        if (isset_and_not_empty($input, 'nickname')) {
            $builder->join($this->linkTable . ' as gl', 'gl.buyer_group_id', '=', 'g.id')
                ->join('oc_customer as c', 'c.customer_id', '=', 'gl.buyer_id')
                ->distinct()
                ->where([
                    ['gl.status', '=', 1],
                    ['c.status','=',1]
                ])
                ->where(new Expression('concat(c.nickname,"(",c.user_number,")")'), 'like', "%{$input['nickname']}%");
        }
        $results['total'] = $builder->count('g.id');
        $results['data'] = $builder->forPage($input['page'], $input['pageSize'])
            ->orderBy('g.id', 'DESC')
            ->get();
        return $results;
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

        if (isset_and_not_empty($input, 'nickname')) {
            $builder->join($this->linkTable . ' as gl', 'gl.buyer_group_id', '=', 'g.id')
                ->join('oc_customer as c', 'c.customer_id', '=', 'gl.buyer_id');
            $where[] = ['gl.status', '=', 1];
            $where[] = ['c.status', '=', 1];
            $builder->where(new Expression('concat(c.nickname,"(",c.user_number,")")'), 'like', "%{$input['nickname']}%");
        }

        $groupObjs = $builder->select([
            'g.id', 'g.name as group_name', 'g.description as group_description', 'g.is_default',
        ])
            ->where($where)
            ->orderBy('g.id', 'DESC')
            ->distinct()
            ->get();

        $results = [];
        foreach ($groupObjs as $groupObj) {
            $linkObjs = $this->orm->table($this->linkTable . ' as gl')
                ->join('oc_customer as c', 'c.customer_id', '=', 'gl.buyer_id')
                ->join('oc_buyer_to_seller as bts', [['bts.buyer_id', '=', 'gl.buyer_id'], ['bts.seller_id', '=', 'gl.seller_id']])
                ->select([
                    'c.nickname', 'c.user_number', 'bts.add_time', 'bts.id'
                ])
                ->where([
                    ['gl.buyer_group_id', '=', $groupObj->id],
                    ['gl.status', '=', 1],
                    ['c.status', '=', 1]
                ])
                ->orderBy('gl.id', 'DESC')
                ->get();
            foreach ($linkObjs as $linkObj) {
                $temp = clone $groupObj;
                $temp->nickname = $linkObj->nickname;
                $temp->user_number = $linkObj->user_number;
                $temp->add_time = $linkObj->add_time;
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
            ->select(['id', 'name', 'description', 'update_time', 'is_default'])
            ->where([
                ['id', $group_id],
                ['status', 1]
            ])
            ->first();
    }

    /**
     * 根据 group 获取尚未关联该 group 的 buyer
     *
     * @param int $seller_id
     * @param int $group_id
     * @return \Illuminate\Support\Collection
     */
    public function getBuyerByGroup($seller_id, $group_id)
    {
        return $this->orm->table('oc_customer as c')
            ->join('oc_buyer_to_seller as bts', 'bts.buyer_id', '=', 'c.customer_id')
            ->select([
                'c.customer_id as buyer_id',
                'c.nickname',
                'c.user_number',
                'bts.add_time'
            ])
            ->where([
                ['c.status', '=', 1],
                ['bts.seller_id', '=', $seller_id],
                ['bts.seller_control_status', '=', 1],
            ])
            ->whereNotExists(function ($query) use ($seller_id, $group_id) {
                $query->from($this->linkTable . ' as gl')
                    ->select(['gl.buyer_id'])
                    ->where([
                        ['gl.seller_id', $seller_id],
                        ['gl.buyer_group_id', $group_id],
                        ['gl.status', '=', 1]
                    ])
                    ->whereRaw('gl.buyer_id = c.customer_id');
            })
            ->orderBy('c.buyer_id', 'DESC')
            ->get();
    }

    /**
     * 获取已建立关联的buyer
     *
     * @param int $seller_id
     * @param int $group_id
     * @return array
     */
    public function getActiveBuyersByGroup($seller_id, $group_id)
    {
        return $this->orm->table($this->linkTable)
            ->where([
                ['seller_id', $seller_id],
                ['buyer_id', $group_id],
                ['status', '=', '1']
            ])
            ->pluck('buyer_id')
            ->toArray();
    }

    /**
     * 根据组ID获取 buyer_id的数组
     *
     * @param int $seller_id
     * @param array $buyer_group_ids
     * @return \Illuminate\Support\Collection|array
     */
    public function getBuyerIDsByBuyerGroups($seller_id, $buyer_group_ids)
    {
        return $this->orm->table('oc_customerpartner_buyer_group_link as bgl')
            ->join('oc_customer as c','c.customer_id','=','bgl.buyer_id')
            ->where([
                ['bgl.status', '=', 1],
                ['bgl.seller_id', '=', $seller_id],
                ['c.status','=',1]
            ])
            ->whereIn('bgl.buyer_group_id', $buyer_group_ids)
            ->pluck('bgl.buyer_id');
    }

    /**
     * buyer group 下拉选框
     *
     * @param int $seller_id
     * @return \Illuminate\Support\Collection
     */
    public function getGroupsForSelect($seller_id)
    {
        return $this->orm->table($this->table)
            ->select([
                'id as buyer_group_id', 'name', 'is_default'
            ])
            ->where([
                ['seller_id', '=', $seller_id],
                ['status', '=', 1]
            ])
            ->get();
    }
//endregion

//region Group Add

    /**
     * 添加 分组及关联的buyer
     *
     * 注：需要保证 is_default 只有一个group的为true，其他均为false
     *
     * @param array $input
     */
    public function addGroup($input)
    {
        $group_id = $this->orm->table($this->table)
            ->insertGetId([
                'name' => $input['name'],
                'description' => $input['description'],
                'add_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
                'seller_id' => $input['seller_id'],
                'is_default' => $input['is_default'],
                'status' => 1
            ]);

        // 如果 is_default 为 true， 需要更新之前的group 均为false
        if ($group_id && $input['is_default']) {
            $this->orm->table($this->table)
                ->where([
                    ['id', '<>', $group_id],
                    ['seller_id', $input['seller_id']],
                    ['is_default', '=', 1]
                ])
                ->update(['is_default' => 0]);
        }

        $keyValArr = [];
        $keyVal = [
            'buyer_group_id' => $group_id,
            'seller_id' => $input['seller_id'],
            'add_time' => date('Y-m-d H:i:s'),
            'status' => 1,
        ];
        foreach ($input['buyers'] as $buyer) {
            $keyVal['buyer_id'] = $buyer;
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
                'description' => get_value_or_default($input, 'description', ''),
                'is_default' => $input['is_default']
            ]);
        if ($input['is_default']) {
            $this->orm->table($this->table)
                ->where([
                    ['id', '<>', $input['id']],
                    ['seller_id', $input['seller_id']],
                    ['is_default', '=', 1]
                ])
                ->update(['is_default' => 0]);
        }
    }
//endregion

//region Group delete
    /**
     * 删除分组同时删除关联
     * 注：此删除为逻辑删除
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
            ->update(['status' => 0]);

        $this->orm->table($this->linkTable)
            ->where([
                ['buyer_group_id', $group_id],
                ['seller_id', $seller_id]
            ])
            ->update(['status' => 0]);

    }
//endregion

//region Link Select
    /**
     * 获取buyer供选择
     * 注：排除已加入分组的buyer
     *
     * @param int $seller_id
     * @param string $search_str
     * @return \Illuminate\Support\Collection
     */
    public function getBuyerInfoBySeller($seller_id, $search_str)
    {
        $where = [
            ['c.status', '=', 1],
            ['bts.seller_id', '=', $seller_id],
            ['bts.seller_control_status', '=', 1],
        ];

        $builder = $this->orm->table('oc_customer as c')
            ->join('oc_buyer_to_seller as bts', 'bts.buyer_id', '=', 'c.customer_id')
            ->select([
                'c.customer_id as buyer_id',
                'c.nickname',
                'c.user_number',
                'bts.add_time',
                'bts.remark'
            ])
            ->where($where)
            ->whereNotExists(function ($query) use ($seller_id) {
                $query->from($this->linkTable . ' as gl')
                    ->select(['gl.buyer_id'])
                    ->where([
                        ['gl.seller_id', $seller_id],
                        ['gl.status', '=', 1]
                    ])
                    ->whereRaw('gl.buyer_id = c.customer_id');
            });
        if (!empty($search_str)) {
            $builder->where(new Expression('concat(c.nickname,"(",c.user_number,")")'), 'like', "%$search_str%");
        }
        return $builder->get();
    }

    /**
     * 根据 group 获取关联信息
     *
     * @param int $seller_id
     * @param int $group_id
     * @param null|string $nickname
     * @return \Illuminate\Support\Collection
     */
    public function getLinkList($seller_id, $group_id, $nickname = null)
    {
        $builder = $this->orm->table($this->linkTable . ' as gl')
            ->join('oc_customer as c', 'c.customer_id', '=', 'gl.buyer_id')
            ->select([
                'gl.id as link_id',
                'c.nickname',
                'c.user_number',
            ])
            ->where([
                ['gl.seller_id', '=', $seller_id],
                ['gl.buyer_group_id', $group_id],
                ['gl.status', '=', 1],
                ['c.status', 1]
            ]);
        if (!empty($nickname)) {
            $builder->where(new Expression('concat(c.nickname,"(",c.user_number,")")'), 'like', "%$nickname%");
        }
        return $builder->orderBy('id', 'DESC')->get();
    }

    /**
     * 根据 group 获取已建立关联的 product group下面的product
     *
     * @param int $seller_id
     * @param int $group_id
     * @return array
     */
    public function getLinkedProductsByGroup($seller_id, $group_id)
    {
        return $this->orm->table('oc_customerpartner_product_group_link as pgl')
            ->join('oc_delicacy_management_group as dmg', 'pgl.product_group_id', '=', 'dmg.product_group_id')
            ->where([
                ['dmg.status', '=', 1],
                ['dmg.buyer_group_id', '=', $group_id],
                ['dmg.seller_id', '=', $seller_id],
                ['pgl.status', '=', 1],
                ['pgl.seller_id', '=', $seller_id]
            ])
            ->distinct()
            ->pluck('pgl.product_id')
            ->toArray();
    }
//endregion

//region Link Add
    /**
     * 添加 group 和 buyer 的关联
     *
     * @param int $seller_id
     * @param int $group_id
     * @param int $buyers
     */
    public function addLink($seller_id, $group_id, $buyers)
    {
        $insertArray = [];
        foreach ($buyers as $buyer) {
            if (!$this->checkCustomerActive($buyer)) {
                continue;
            }
            $insertArray[] = [
                'buyer_group_id' => $group_id,
                'buyer_id' => $buyer,
                'add_time' => date('Y-m-d H:i:s'),
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
     * 验证该buyer是否和seller建立关联
     *
     * @param int $seller_id
     * @param int $buyer_id
     * @return bool
     */
    public function checkIsConnect($seller_id, $buyer_id)
    {
        return $this->orm->table('oc_buyer_to_seller as bts')
            ->join('oc_customer as c','c.customer_id','=','bts.buyer_id')
            ->where([
                ['bts.seller_id', '=', $seller_id],
                ['bts.buyer_id', '=', $buyer_id],
                ['bts.seller_control_status', '=', 1],
                ['c.status','=',1]
            ])
            ->exists();
    }

    /**
     * 添加或修改组关系
     *
     * @param int $seller_id
     * @param int $buyer_id
     * @param int $new_buyer_group_id
     * @param int|bool $is_delete_dm
     * @throws Exception
     */
    public function updateGroupLinkByBuyer($seller_id, $buyer_id, $new_buyer_group_id, $is_delete_dm = 0)
    {
        if (!$this->checkCustomerActive($buyer_id)) {
            return;
        }
        $obj = $this->orm->table($this->linkTable)
            ->select(['id', 'buyer_group_id'])
            ->where([
                ['seller_id', '=', $seller_id],
                ['buyer_id', '=', $buyer_id],
                ['status', '=', 1]
            ])
            ->first();

        // 判断是否建立关联
        $isConnect = $this->checkIsConnect($seller_id, $buyer_id);

        //该 buyer 尚未加入组
        if (empty($obj)) {
            //只有建立关联 才能加入到组
            if ($isConnect) {
                $this->orm->table($this->linkTable)
                    ->insert([
                        'buyer_id' => $buyer_id,
                        'seller_id' => $seller_id,
                        'buyer_group_id' => $new_buyer_group_id,
                        'status' => 1,
                        'add_time' => date('Y-m-d H:i:s')
                    ]);
                // 同时需要 是否已从 delicacy_management 中 移除
                if ($is_delete_dm) {
                    $this->load->model('customerpartner/DelicacyManagement');
                    $this->model_customerpartner_DelicacyManagement->batchRemoveByBuyer([$buyer_id], $seller_id);
                }
            }
        } else {
            // 如果更改组
            if ($obj->buyer_group_id != $new_buyer_group_id) {
                // 如果建立关联
                if ($isConnect) {
                    $this->orm->table($this->linkTable)
                        ->where('id', $obj->id)
                        ->update(['buyer_group_id' => $new_buyer_group_id]);
                    // 同时需要 是否已从 delicacy_management 中 移除
                    if ($is_delete_dm) {
                        $this->load->model('customerpartner/DelicacyManagement');
                        $this->model_customerpartner_DelicacyManagement->batchRemoveByBuyer([$buyer_id], $seller_id);
                    }
                }
            }

            //没有建立关联，则要从组中移除
            if (!$isConnect) {
                $this->orm->table($this->linkTable)
                    ->where('id', $obj->id)
                    ->update(['status' => 0]);
                $this->load->model('customerpartner/DelicacyManagement');
                $this->model_customerpartner_DelicacyManagement->batchRemoveByBuyer([$buyer_id], $seller_id);
            }
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
            ->update(['status' => 0]);
    }

    public function linkDeleteByBuyer($seller_id, $buyer_id)
    {
        $this->orm->table($this->linkTable)
            ->where([
                ['seller_id', $seller_id],
                ['buyer_id', '=', $buyer_id],
                ['status',1]
            ])
            ->update(['status' => 0]);
    }

    public function batchDeleteLink($seller_id, $buyers)
    {
        $this->orm->table($this->linkTable)
            ->where([
                ['seller_id', $seller_id],
                ['status',1]
            ])
            ->whereIn('buyer_id',$buyers)
            ->update(['status' => 0]);
    }
//endregion

}
