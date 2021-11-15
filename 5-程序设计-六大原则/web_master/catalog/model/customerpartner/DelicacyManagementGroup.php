<?php

/**
 * Class ModelCustomerPartnerDelicacyManagementGroup
 */
class ModelCustomerPartnerDelicacyManagementGroup extends Model
{
    protected $buyer_group_table;
    protected $product_group_table;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->table = 'oc_delicacy_management_group';
        $this->buyer_group_table = 'oc_customerpartner_buyer_group';
        $this->product_group_table = 'oc_customerpartner_product_group';
    }

//region Check

    /**
     * 验证 buyer groups 是否合法
     *
     * @param int $seller_id
     * @param array $buyerGroups
     * @return bool
     */
    public function checkBuyerGroups($seller_id, $buyerGroups)
    {
        $count = $this->orm->table($this->buyer_group_table)
            ->where([
                ['seller_id', '=', $seller_id],
                ['status', '=', 1]
            ])
            ->whereIn('id', $buyerGroups)
            ->count('id');
        return count($buyerGroups) == $count;
    }


    /**
     * 验证 id 是否合法
     *
     * @param int $seller_id
     * @param array $DMG_ids
     * @return bool
     */
    public function checkDMGIDsExisted($seller_id, $DMG_ids)
    {
        $count = $this->orm->table($this->table)
            ->where([
                ['seller_id', '=', $seller_id],
                ['status', '=', 1]
            ])
            ->whereIn('id', $DMG_ids)
            ->count();
        return count($DMG_ids) == $count;
    }
//endregion

//region Select

    /**
     * 根据 product group 获取 buyer group
     * 注：无分页
     *
     * @param array $input
     * @return \Illuminate\Support\Collection
     */
    public function getBuyerGroupsByProductGroup($input)
    {
        return $this->orm->table($this->table . ' as dmg')
            ->leftJoin($this->buyer_group_table . ' as bg', 'bg.id', '=', 'dmg.buyer_group_id')
            ->select([
                'dmg.id as dmg_id', 'bg.name', 'bg.description', 'bg.update_time', 'bg.is_default','bg.id as group_id'
            ])
            ->where([
                ['dmg.seller_id', '=', $input['seller_id']],
                ['dmg.product_group_id', '=', $input['product_group_id']],
                ['dmg.status', '=', 1],
                ['bg.status', '=', 1]
            ])
            ->get();
    }

    /**
     * 根据 product group 获取 buyers
     * 逻辑：获取和 PG 关联的 BG 中的 buyer ，然后去重即可
     *
     * @param $input
     * @return \Illuminate\Support\Collection
     */
    public function getBuyersByProductGroup($input)
    {
        return $this->orm->table($this->table . ' as dmg')
            ->leftJoin($this->buyer_group_table . ' as bg', 'bg.id', '=', 'dmg.buyer_group_id')
            ->leftJoin('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'bgl.buyer_id')
            ->leftJoin('oc_buyer_to_seller as bts', [['bts.seller_id', '=', 'dmg.seller_id'], ['bts.buyer_id', '=', 'bgl.buyer_id']])
            ->select([
                'bgl.buyer_id', 'c.nickname', 'c.user_number', 'c.customer_group_id',
                'bg.id as buyer_group_id', 'bg.name as buyer_group_name', 'bg.is_default',
                'dmg.add_time', 'bts.remark'
            ])
            ->where([
                ['dmg.product_group_id', '=', $input['product_group_id']],
                ['dmg.seller_id', '=', $input['seller_id']],
                ['dmg.status', '=', 1],
                ['bg.status', '=', 1],
                ['bgl.status', '=', 1],
                ['bts.seller_control_status', '=', 1],
                ['c.status','=',1]
            ])
            ->distinct()
            ->get();
    }

    /**
     * 根据 product 获取 buyer
     * @param array $input
     * @return array
     */
    public function getBuyersByProduct($input)
    {
        $builder_group = $this->orm->table('oc_customerpartner_buyer_group_link as bgl')
            ->join($this->buyer_group_table . ' as bg', 'bg.id', '=', 'bgl.buyer_group_id')
            ->join('oc_customer as c', 'c.customer_id', '=', 'bgl.buyer_id')
            ->join('oc_buyer_to_seller as bts', [['bts.buyer_id', '=', 'bgl.buyer_id'], ['bts.seller_id', '=', 'bgl.seller_id']])
            ->join($this->table . ' as dmg', [['dmg.buyer_group_id', '=', 'bgl.buyer_group_id'], ['dmg.seller_id', '=', 'bgl.seller_id']])
            ->join('oc_customerpartner_product_group_link as pgl', [['pgl.seller_id', '=', 'bgl.seller_id'], ['pgl.product_group_id', '=', 'dmg.product_group_id']])
            ->select([])
            ->selectRaw('0 as dm_id')
            ->addSelect([
                'c.customer_id as buyer_id', 'c.nickname', 'c.user_number', 'bts.remark', 'c.customer_group_id',
                'bg.name as buyer_group_name', 'dmg.add_time','bg.id as buyer_group_id','bg.is_default',
            ])
            ->selectRaw('1 as from_group')
            ->where([
                ['bgl.seller_id', '=', $input['seller_id']],
                ['bgl.status', '=', 1],
                ['bg.status', '=', 1],
                ['c.status', '=', 1],
                ['bts.seller_control_status', '=', 1],
                ['dmg.seller_id', '=', $input['seller_id']],
                ['dmg.status', '=', 1],
                ['pgl.product_id', '=', $input['product_id']],
                ['pgl.status', '=', 1],
            ])
            ->distinct();

        $objs = $this->orm->table('oc_delicacy_management as dm')
            ->join('oc_buyer_to_seller as bts', [['bts.buyer_id', '=', 'dm.buyer_id'], ['bts.seller_id', '=', 'dm.seller_id']])
            ->join('oc_customer as c', 'c.customer_id', '=', 'dm.buyer_id')
            ->leftJoin('oc_customerpartner_buyer_group_link as bgl', function($join){
                $join->on('bgl.buyer_id', '=', 'dm.buyer_id')
                    ->on('bgl.seller_id', '=', 'dm.seller_id')
                    ->where([
                        ['bgl.status', '=', 1],
                    ]);
            })
            ->leftJoin($this->buyer_group_table . ' as bg', function($join){
                $join->on('bg.id', '=', 'bgl.buyer_group_id')
                    ->where([
                        ['bg.status', '=', 1]
                    ]);
            })
            ->select([
                'dm.id as dm_id',
                'c.customer_id as buyer_id', 'c.nickname', 'c.user_number', 'c.customer_group_id',
                'bts.remark', 'bg.name as buyer_group_name', 'dm.add_time','bg.id as buyer_group_id','bg.is_default',
            ])
            ->selectRaw('0 as from_group')
            ->where([
                ['dm.seller_id', '=', $input['seller_id']],
                ['dm.product_id', '=', $input['product_id']],
                ['dm.product_display', '=', 0],
                ['dm.effective_time', '<', date('Y-m-d H:i:s')],
                ['dm.expiration_time', '>', date('Y-m-d H:i:s')],
                ['bts.seller_control_status', '=', 1],
                ['c.status', '=', 1],

            ])
            ->unionAll($builder_group)
            ->distinct()
            ->orderBy('add_time', 'DESC')
            ->get();
        $results = [];
        foreach ($objs as $obj) {
            if (empty($results[$obj->buyer_id])) {
                $results[$obj->buyer_id] = $obj;
            } else {
                $results[$obj->buyer_id]->add_time < $obj->add_time
                && $results[$obj->buyer_id] = $obj;
            }
        }
        return array_values($results);
    }

    /**
     * 获取 product 所在PG与之关联的BG
     *
     * @param array $input
     * @return
     */
    public function getBuyerGroupsByProduct($input)
    {
        $objs = $this->orm->table($this->table . ' as dmg')
            ->leftJoin($this->buyer_group_table . ' as bg', 'bg.id', '=', 'dmg.buyer_group_id')
            ->leftJoin('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->select([
                'bg.id as buyer_group_id', 'bg.name as buyer_group_name', 'bg.is_default', 'bg.description',
                'dmg.add_time',
            ])
            ->where([
                ['dmg.seller_id', '=', $input['seller_id']],
                ['dmg.status', '=', 1],
                ['bg.status', '=', 1],
                ['bgl.status', '=', 1],
            ])
            ->whereIn('dmg.product_group_id', function ($query) use ($input) {
                $query->from('oc_customerpartner_product_group_link as pgl')
                    ->select('pgl.product_group_id')
                    ->where([
                        ['pgl.product_id', '=', $input['product_id']],
                        ['pgl.status', '=', 1]
                    ])->get();
            })
            ->distinct()
            ->orderBy('dmg.add_time', 'DESC')
            ->get();

        $results = [];
        foreach ($objs as $obj) {
            if (empty($results[$obj->buyer_group_id])) {
                $results[$obj->buyer_group_id] = $obj;
            } else {
                $results[$obj->buyer_group_id]->add_time < $obj->add_time
                && $results[$obj->buyer_group_id] = $obj;
            }
        }
        return array_values($results);
    }

    /**
     * 根据 product group 获取尚未关联的 buyer group
     *
     * @param int $seller_id
     * @param int $product_group_id
     * @return \Illuminate\Support\Collection
     */
    public function getUnlinkBuyerGroupsByProductGroup($seller_id, $product_group_id)
    {
        return $this->orm->table($this->buyer_group_table . ' as bg')
            ->select([
                'id', 'name', 'description', 'update_time', 'is_default'
            ])
            ->where([
                ['seller_id', '=', $seller_id],
                ['status', '=', 1]
            ])
            ->whereNotExists(function ($query) use ($seller_id, $product_group_id) {
                $query->from($this->table . ' as dmg')
                    ->select(['buyer_group_id'])
                    ->where([
                        ['seller_id', '=', $seller_id],
                        ['product_group_id', '=', $product_group_id],
                        ['status', '=', 1]
                    ])
                    ->whereRaw('dmg.buyer_group_id = bg.id');
            })
            ->orderBy('bg.id', 'DESC')
            ->get();
    }

    /**
     * 根据 product group 和 buyer groups 获取指定 buyer groups范围内的已建立关联的buyer_group_id
     * @param int $seller_id
     * @param int $product_group_id
     * @param array $buyer_groups
     * @return \Illuminate\Support\Collection|array
     */
    public function getLinkedBuyerGroupsByPGAndBGs($seller_id, $product_group_id, $buyer_groups)
    {
        return $this->orm->table($this->table)
            ->where([
                ['seller_id', '=', $seller_id],
                ['status', '=', 1],
                ['product_group_id', '=', $product_group_id]
            ])
            ->whereIn('buyer_group_id', $buyer_groups)
            ->pluck('buyer_group_id')
            ->toArray();
    }

    /**
     * @param int $seller_id
     * @param array $buyer_ids
     * @param array $product_ids
     * @return array
     */
    public function getDelicacyManagementsByPsAndBs($seller_id, $buyer_ids, $product_ids)
    {
        return $this->orm->table('oc_delicacy_management')
            ->where([
                ['seller_id', '=', $seller_id],
            ])
            ->whereIn('buyer_id', $buyer_ids)
            ->whereIn('product_id', $product_ids)
            ->pluck('id')
            ->toArray();
    }

    /**
     * 根据 product 获取尚未建立关联的buyers
     *
     * @param int $seller_id
     * @param int $product_id
     * @return \Illuminate\Support\Collection|array
     */
    public function getLinkBuyerIDsByProduct($seller_id, $product_id)
    {
        // 1.根据组关系 获取已建立关联的 buyers
        $linkBuyerIDs = $this->orm->table('oc_customerpartner_buyer_group_link as bgl')
            ->join('oc_delicacy_management_group as dmg', 'dmg.buyer_group_id', '=', 'bgl.buyer_group_id')
            ->join('oc_customerpartner_product_group_link as pgl', 'pgl.product_group_id', '=', 'dmg.product_group_id')
            ->where([
                ['bgl.status', '=', 1],
                ['dmg.seller_id', '=', $seller_id],
                ['dmg.status', '=', 1],
                ['pgl.product_id', '=', $product_id],
                ['pgl.seller_id', '=', $seller_id],
                ['pgl.status', '=', 1]
            ])
            ->distinct()
            ->pluck('buyer_id')
            ->toArray();

        // 2.根据精细化表 获取已建立关系的 buyers
        $delicacy_management_buyers = $this->orm->table('oc_delicacy_management')
            ->where([
                ['seller_id', '=', $seller_id],
                ['product_id', '=', $product_id],
                ['expiration_time', '>', date('Y-m-d H:i:s')]
            ])
            ->pluck('buyer_id')
            ->toArray();

        // 3.获取排除 1 2 中的buyers
        return $this->orm->table('oc_buyer_to_seller as bts')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'bts.buyer_id')
            ->leftJoin('oc_customerpartner_buyer_group_link as bgl', function ($join) use ($seller_id) {
                $join->on('bgl.buyer_id', '=', 'bts.buyer_id')
                    ->where([
                        ['bgl.seller_id', '=', $seller_id],
                        ['bgl.status', '=', 1],
                    ]);
            })
            ->leftJoin('oc_customerpartner_buyer_group as bg', function ($join) use ($seller_id) {
                $join->on('bg.id', '=', 'bgl.buyer_group_id')
                    ->where([
                        ['bg.seller_id', '=', $seller_id],
                        ['bg.status', '=', 1],
                    ]);
            })
            ->select([
                'bts.buyer_id',
                'c.nickname', 'c.user_number', 'bts.remark', 'c.customer_group_id',
                'bg.name as buyer_group_name', 'bts.add_time','bg.id as buyer_group_id','bg.is_default'
            ])
            ->where([
                ['bts.seller_control_status', '=', 1],
                ['bts.seller_id', '=', $seller_id],
                ['c.status', '=', 1],

            ])
            ->whereNotIn('bts.buyer_id', array_unique(array_merge($linkBuyerIDs, $delicacy_management_buyers)))
            ->distinct()
            ->orderBy('bts.add_time', 'DESC')
            ->get();
    }

//endregion

//region Add

    /**
     * 仅 添加组关联
     *
     * @param int $seller_id
     * @param int $product_group_id
     * @param int $buyer_group_id
     */
    public function addSingleGroup($seller_id, $product_group_id, $buyer_group_id)
    {
        $keyVal = [
            'seller_id' => $seller_id,
            'product_group_id' => $product_group_id,
            'buyer_group_id' => $buyer_group_id,
            'add_time' => date('Y-m-d H:i:s'),
            'status' => 1,
            'update_time' => date('Y-m-d H:i:s'),
        ];
        $this->orm->table($this->table)->insert($keyVal);
    }

    /**
     * 根据 PGs 和 BGs 添加组关系
     *
     * @param int $seller_id
     * @param array $product_group_ids
     * @param array $buyer_group_ids
     */
    public function addGroups($seller_id, $product_group_ids, $buyer_group_ids)
    {
        $keyVal = [
            'seller_id' => $seller_id,
            'add_time' => date('Y-m-d H:i:s'),
            'status' => 1,
            'update_time' => date('Y-m-d H:i:s'),
        ];

        $keyValArr = [];
        foreach ($product_group_ids as $product_group_id) {
            foreach ($buyer_group_ids as $buyer_group_id) {
                $keyVal['product_group_id'] = $product_group_id;
                $keyVal['buyer_group_id'] = $buyer_group_id;
                $keyValArr[] = $keyVal;
            }
        }
        $this->orm->table($this->table)->insert($keyValArr);

    }

    /**
     * 获取 product ID
     *
     * @param int $seller_id
     * @param int $product_group_id
     * @return \Illuminate\Support\Collection
     */
    private function getProductIDByGroup($seller_id, $product_group_id)
    {
        return $this->orm->table('oc_customerpartner_product_group_link')
            ->where([
                ['seller_id', '=', $seller_id],
                ['product_group_id', '=', $product_group_id],
                ['status', '=', 1]
            ])
            ->pluck('product_id');
    }

    /**
     * 获取 buyer ID
     * @param int $seller_id
     * @param int $buyer_group_id
     * @return \Illuminate\Support\Collection
     */
    private function getBuyerIDByGroup($seller_id, $buyer_group_id)
    {
        return $this->orm->table('oc_customerpartner_buyer_group_link')
            ->where([
                ['seller_id', '=', $seller_id],
                ['buyer_group_id', '=', $buyer_group_id],
                ['status', '=', 1]
            ])
            ->pluck('buyer_id');
    }

    /**
     * 根据 delicacy_management.id 更新为不可见(立即生效)
     *
     * @param int $dm_id
     */
    public function updateDelicacyManagement($dm_id)
    {
        $obj = $this->orm->table('oc_delicacy_management')->find($dm_id);
        if (!isset($obj->id)) {
            return;
        }
        $this->orm->table('oc_delicacy_management')
            ->where('id', $dm_id)
            ->update([
                'current_price' => 0,
                'price' => 0,
                'product_display' => 0,
                'effective_time' => date('Y-m-d H:i:s'),
                'expiration_time' => '9999-01-01 00:00:00',
                'is_update' => 1,
                'update_time' => date('Y-m-d H:i:s'),
                'from_group' => 2
            ]);

        $keyVal = [
            'type' => 3,
            'origin_id' => $dm_id,
            'seller_id' => $obj->seller_id,
            'buyer_id' => $obj->buyer_id,
            'product_id' => $obj->product_id,
            'current_price' => 0,
            'product_display' => 0,
            'price' => 0,
            'effective_time' => date('Y-m-d H:i:s'),
            'expiration_time' => '9999-01-01 00:00:00',
            'add_time' => date('Y-m-d H:i:s'),
            'origin_add_time' => $obj->add_time,
            'from_group' => 2
        ];

        $proObj = $this->orm->table('oc_product')
            ->where('product_id', $obj->product_id)
            ->first(['freight', 'package_fee']);
        if (isset($proObj->freight)) {
            $keyVal['freight'] = $proObj->freight;
            $keyVal['package_fee'] = $proObj->package_fee;
        }
        $this->orm->table('oc_delicacy_management_history')->insert($keyVal);
    }

    /**
     * 把 product group 和 buyer group 组添加到 精细化
     *
     * @param int $seller_id
     * @param int $product_group_id
     * @param int $buyer_group_id
     */
    public function addLinkToDelicacyManagement($seller_id, $product_group_id, $buyer_group_id)
    {
        $product_ids = $this->getProductIDByGroup($seller_id, $product_group_id);
        $buyer_ids = $this->getBuyerIDByGroup($seller_id, $buyer_group_id);

        $product_buyer_arr = [];
        foreach ($product_ids as $product_id) {
            foreach ($buyer_ids as $buyer_id) {
                $product_buyer_arr[$product_id . '_' . $buyer_id] = 0;
            }
        }

        $dm_objs = $this->orm->table('oc_delicacy_management')
            ->select([
                'id', 'product_id', 'buyer_id'
            ])
            ->where([
                ['seller_id', '=', $seller_id]
            ])
            ->whereIn('buyer_id', $buyer_ids)
            ->whereIn('product_id', $product_ids)
            ->get();
        foreach ($dm_objs as $dm_obj) {
            $product_buyer_arr[$dm_obj->product_id . '_' . $dm_obj->buyer_id] = $dm_obj->id;
        }

        $keyValHistory = [];    // delicacy_management_history
        $now = date('Y-m-d H:i:s');
        $longLongTime = '9999-01-01 00:00:00';
        $temp = [
            'seller_id' => $seller_id,
            'current_price' => 0,
            'product_display' => 0,
            'price' => 0,
            'effective_time' => $now,
            'expiration_time' => $longLongTime,
            'is_update' => 1,
            'add_time' => $now,
            'update_time' => $now,
            'from_group' => 1
        ];
        $temp_history = $temp;
        unset($temp_history['is_update']);
        unset($temp_history['update_time']);
        $temp_history['type'] = 1;
        $temp_history['origin_add_time'] = $temp['add_time'];

        foreach ($product_buyer_arr as $key => $item) {
            $p_b_arr = explode('_', $key);
            $temp['product_id'] = $temp_history['product_id'] = $p_b_arr[0];
            $temp['buyer_id'] = $temp_history['buyer_id'] = $p_b_arr[1];
            if ($item == 0) {
                $temp_history['origin_id'] = $this->orm->table('oc_delicacy_management')->insertGetId($temp);
                $keyValHistory[] = $temp_history;
            } else {
                $this->updateDelicacyManagement($item);
            }
        }
        $this->orm->table('oc_delicacy_management_history')->insert($keyValHistory);
    }



//endregion

//region Update
//endregion

//region Delete

    /**
     * 根据 ids 删除 组关系
     *
     * @param int $seller_id
     * @param array $dmg_ids
     */
    public function deleteByDMGIDs($seller_id, $dmg_ids)
    {
        $this->orm->table($this->table)
            ->where([
                ['seller_id', '=', $seller_id],
                ['status', '=', 1]
            ])
            ->whereIn('id', $dmg_ids)
            ->update(['status' => 0]);
    }
//endregion

//region Doanload
    public function getDataByPgBg($seller_id)
    {
        return $this->orm->table($this->table . ' as dmg')
            ->join('oc_customerpartner_product_group_link as pgl', [['pgl.seller_id', '=', 'dmg.seller_id'], ['pgl.product_group_id', '=', 'dmg.product_group_id']])
            ->join('oc_customerpartner_product_group as pg', [['pg.id', '=', 'pgl.product_group_id'], ['pg.seller_id', '=', 'dmg.seller_id']])
            ->join('oc_customerpartner_buyer_group as bg', [['bg.id', '=', 'dmg.buyer_group_id'], ['bg.seller_id', '=', 'dmg.seller_id']])
            ->join('oc_customerpartner_buyer_group_link as bgl', [['bgl.seller_id', '=', 'dmg.seller_id'], ['bgl.buyer_group_id', '=', 'dmg.buyer_group_id']])
            ->join('oc_customer as c', 'c.customer_id', '=', 'bgl.buyer_id')
            ->join('oc_buyer_to_seller as bts', [['bts.buyer_id', '=', 'bgl.buyer_id'], ['bts.seller_id', '=', 'dmg.seller_id']])
            ->select([
                'pg.name as product_group_name', 'pg.description as product_group_description',
                'bg.name as buyer_group_name', 'c.nickname', 'c.user_number',
                'bts.remark', 'dmg.add_time'
            ])
            ->where([
                ['dmg.seller_id', '=', $seller_id],
                ['pgl.seller_id', '=', $seller_id],
                ['pg.seller_id', '=', $seller_id],
                ['bgl.seller_id', '=', $seller_id],
                ['bg.seller_id', '=', $seller_id],
                ['dmg.status', '=', 1],
                ['pgl.status', '=', 1],
                ['pg.status', '=', 1],
                ['bg.status', '=', 1],
                ['bgl.status', '=', 1],
                ['c.status', '=', 1],
                ['bts.seller_control_status', '=', 1],
            ])
            ->distinct()
            ->orderBy('pg.name', 'asc')
            ->orderBy('c.nickname', 'asc')
            ->get();
    }

    public function getDataByPBg($seller_id)
    {
        $builder_group = $this->orm->table('oc_delicacy_management_group as dmg')
            ->join('oc_customerpartner_product_group_link as pgl', [['pgl.seller_id', '=', 'dmg.seller_id'], ['pgl.product_group_id', '=', 'dmg.product_group_id']])
            ->join('oc_product as p', 'p.product_id', '=', 'pgl.product_id')
            ->join('oc_product_description as pd', 'pd.product_id', '=', 'pgl.product_id')
            ->join('oc_customerpartner_buyer_group_link as bgl', [['bgl.seller_id', '=', 'dmg.seller_id'], ['bgl.buyer_group_id', '=', 'dmg.buyer_group_id']])
            ->join('oc_customerpartner_buyer_group as bg', [['bg.seller_id', '=', 'bg.seller_id'], ['bg.id', '=', 'dmg.buyer_group_id']])
            ->join('oc_customer as c', 'c.customer_id', '=', 'bgl.buyer_id')
            ->join('oc_buyer_to_seller as bts', [['bts.buyer_id', '=', 'bgl.buyer_id'], ['bts.seller_id', '=', 'dmg.seller_id']])
            ->select([
                'p.product_id', 'p.sku', 'p.mpn', 'pd.name as product_name',
                'bgl.buyer_id', 'c.nickname', 'c.user_number', 'bts.remark',
                'bg.name as buyer_group_name',
                'dmg.add_time',
            ])
            ->where([
                ['dmg.seller_id', '=', $seller_id],
                ['dmg.status', '=', 1],
                ['pgl.status', '=', 1],
                ['p.is_deleted', '=', 0],
                ['p.status', '=', 1],
                ['p.buyer_flag', '=', 1],
                ['bgl.status', '=', 1],
                ['bg.status', '=', 1],
                ['c.status', '=', 1],
                ['bts.seller_control_status', '=', 1],
            ])
            ->distinct();

        $objs = $this->orm->table('oc_delicacy_management as dm')
            ->join('oc_buyer_to_seller as bts', [['bts.buyer_id', '=', 'dm.buyer_id'], ['bts.seller_id', '=', 'dm.seller_id']])
            ->join('oc_customer as c', 'c.customer_id', '=', 'dm.buyer_id')
            ->leftJoin('oc_customerpartner_buyer_group_link as bgl', function ($query) {
                $query->on([['bgl.buyer_id', '=', 'dm.buyer_id'], ['bgl.seller_id', '=', 'dm.seller_id']])
                    ->where('bgl.status', '=', 1);
            })
            ->leftJoin($this->buyer_group_table . ' as bg', function ($query) {
                $query->on('bg.id', '=', 'bgl.buyer_group_id')
                    ->where('bg.status', '=', 1);
            })
            ->join('oc_product as p', 'p.product_id', '=', 'dm.product_id')
            ->join('oc_product_description as pd', 'pd.product_id', '=', 'dm.product_id')
            ->select([
                'p.product_id', 'p.sku', 'p.mpn', 'pd.name as product_name',
                'dm.buyer_id', 'c.nickname', 'c.user_number', 'bts.remark',
                'bg.name as buyer_group_name',
                'dm.add_time',
            ])
            ->where([
                ['dm.seller_id', '=', $seller_id],
                ['dm.product_display', '=', 0],
                ['dm.effective_time', '<', date('Y-m-d H:i:s')],
                ['dm.expiration_time', '>', date('Y-m-d H:i:s')],
                ['bts.seller_control_status', '=', 1],
                ['c.status', '=', 1],
                ['p.is_deleted', '=', 0],
                ['p.status', '=', 1],
                ['p.buyer_flag', '=', 1],
            ])
            ->unionAll($builder_group)
            ->distinct()
            ->orderBy('sku', 'ASC')
            ->orderBy('nickname', 'ASC')
            ->get();
        $results = [];
        $productIDs = [];
        foreach ($objs as $obj) {
            $productIDs[] = $obj->product_id;
            if (empty($results[$obj->product_id . '_' . $obj->buyer_id])) {
                $results[$obj->product_id . '_' . $obj->buyer_id] = $obj;
            } else {
                $results[$obj->product_id . '_' . $obj->buyer_id]->add_time < $obj->add_time
                && $results[$obj->product_id . '_' . $obj->buyer_id] = $obj;
            }
        }

        $productObjs = $this->orm->table('oc_customerpartner_product_group_link as pgl')
            ->join('oc_customerpartner_product_group as pg', 'pg.id', '=', 'pgl.product_group_id')
            ->select([
                'pgl.product_id', 'pg.name as product_group_name'
            ])
            ->where([
                ['pgl.seller_id', '=', $seller_id],
                ['pgl.status', '=', 1],
                ['pg.status', '=', 1],
            ])
            ->whereIn('pgl.product_id', array_unique($productIDs))
            ->get();
        $productArr = [];
        foreach ($productObjs as $productObj) {
            $productArr[$productObj->product_id][] = $productObj->product_group_name;
        }

        foreach ($results as &$result) {
            isset($productArr[$result->product_id])
            && $result->product_group_name = implode(',', $productArr[$result->product_id]);
        }
        return array_values($results);
    }


//endregion
}
