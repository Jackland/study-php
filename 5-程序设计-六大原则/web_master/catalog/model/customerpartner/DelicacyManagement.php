<?php

use App\Models\Customer\Customer;
use App\Repositories\Product\ProductPriceRepository;
use Framework\App;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Class ModelCustomerPartnerDelicacyManagement
 *
 * @property ModelCommonProduct $model_common_product
 * @property ModelMessageMessage $model_message_message
 */
class ModelCustomerPartnerDelicacyManagement extends Model
{
    public $table;
    private $historyTable;

    /**
     * ModelCustomerPartnerDelicacyManagement constructor.
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->table = 'oc_delicacy_management';
        $this->historyTable = 'oc_delicacy_management_history';
    }

//region check

    /**
     * 判断 buyer_id 和seller 是否建立联系
     *
     * @param int $seller_id
     * @param int $customer_id
     * @return bool
     */
    public function checkIsConnect($seller_id, $buyer_id)
    {
        return $this->orm->table('oc_buyer_to_seller')
            ->where([
                ['seller_id', '=', $seller_id],
                ['buyer_id', '=', $buyer_id],
                ['seller_control_status', '=', 1]
            ])
            ->exists();
    }

    /**
     * @param int $seller_id
     * @param array $buyers
     * @return bool
     */
    public function checkIsConnectByBuyers($seller_id, $buyers)
    {
        $count = $this->orm->table('oc_buyer_to_seller as bts')
            ->join('oc_customer as c', 'c.customer_id', '=', 'bts.buyer_id')
            ->where([
                ['bts.seller_id', '=', $seller_id],
                ['bts.seller_control_status', '=', 1],
                ['c.status', '=', 1],
            ])
            ->whereIn('buyer_id', $buyers)
            ->count('*');
        return $count == count($buyers);
    }

    /**
     * @param int $seller_id
     * @param int $buyer_id
     * @param int $product_id
     * @return int
     */
    public function checkIsExists($seller_id, $buyer_id, $product_id)
    {
        $obj = $this->orm->table($this->table)
            ->select(['id'])
            ->where([
                ['seller_id', $seller_id],
                ['buyer_id', $buyer_id],
                ['product_id', $product_id],
                ['expiration_time', '>', date('Y-m-d H:i:s')]
            ])
            ->first();
        if (empty($obj)) {
            return 0;
        } else {
            return $obj->id;
        }
    }

    /**
     * @param int $id
     * @return bool
     */
    public function checkIsExistsByID($id)
    {
        return $this->orm->table($this->table)
            ->where('id', $id)
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

    /**
     * 验证 产品 是否可用（上架/未删除/可单独购买 ）
     * @param int $product_id
     * @return bool
     */
    public function checkProductActive($product_id): bool
    {
        if (empty($product_id)) {
            return false;
        }
        return $this->orm->table('oc_product')
            ->where([
                ['product_id', '=', (int)$product_id],
                ['status', '=', 1],
                ['is_deleted', '=', 0],
                ['buyer_flag','=',1]
            ])
            ->exists();
    }
//endregion

//region select

    /**
     * @param int $product_id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    private function getProductFee($product_id)
    {
        return $this->orm->table('oc_product')
            ->where('product_id', $product_id)
            ->first(['freight', 'package_fee']);
    }


    /**
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Model|object|static|null
     */
    public function getSingle($id)
    {
        $results = $this->orm->table($this->table . ' as dm')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'dm.product_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'dm.buyer_id')
            ->leftJoin('oc_product_description as pd', 'pd.product_id', '=', 'dm.product_id')
            ->leftJoin('oc_seller_price as sp', 'sp.product_id', 'dm.product_id')
            ->select([
                'dm.id',
                'dm.seller_id',
                'p.sku as item_code',
                'p.mpn',
                'p.price as basic_price',
                'p.product_id',
                'dm.price as delicacy_price',
                'dm.pickup_price',
                'dm.current_price',
                'dm.product_display',
                'dm.effective_time',
                'dm.expiration_time',
                'dm.buyer_id',
                'pd.name as product_name',
                'sp.new_price',
                'sp.effect_time as new_effect_time',
            ])
            ->where([
                ['dm.id', $id],
                ['c.status','=',1],
                ['p.status', '=', 1],
                ['p.buyer_flag', '=', 1],
                ['p.is_deleted','=',0],
            ])
            ->first();
        if (!empty($results) && $results->new_effect_time < date('Y-m-d H:i:s')) {
            $results->new_price = null;
            $results->new_effect_time = null;
        }
        return $results;
    }

    /**
     * @param array $input
     * @param null|int $filter_product_display 如果为null 查所有的，如果不为null，则根据条件判断
     * @return array
     */
    public function getAll(array $input, $filter_product_display = null): array
    {
        $this->load->model('common/product');
        $results = [
            'page' => get_value_or_default($input, 'page', 1),
            'pageSize' => get_value_or_default($input, 'pageSize', 20)
        ];
        $where = [
            ['c.status','=',1],
            ['p.status', '=', 1],
            ['p.buyer_flag', '=', 1],
            ['p.is_deleted','=',0],
            ['bts.seller_control_status','=',1],
        ];
        !is_null($filter_product_display) && $where[] = ['dm.product_display', '=', $filter_product_display];
        isset_and_not_empty($input, 'seller_id') && $where[] = ['dm.seller_id', '=', $input['seller_id']];
        isset_and_not_empty($input, 'buyer_id') && $where[] = ['dm.buyer_id', '=', $input['buyer_id']];
        isset_and_not_empty($input, 'product_id') && $where[] = ['dm.product_id', '=', $input['product_id']];
        isset_and_not_empty($input, 'buyer_nickname') && $where[] = ['c.nickname', 'like', '%' . $input['buyer_nickname'] . '%'];
        $builder = $this->orm->table($this->table . ' as dm')
            ->join('oc_product as p', 'p.product_id', '=', 'dm.product_id')
            ->join('oc_customer as c', 'c.customer_id', '=', 'dm.buyer_id')
            ->join('oc_product_description as pd', 'pd.product_id', '=', 'dm.product_id')
//            ->leftJoin('oc_seller_price as sp', 'sp.product_id', '=','dm.product_id')
            ->join('oc_buyer_to_seller as bts', [['bts.buyer_id', 'dm.buyer_id'], ['bts.seller_id', 'dm.seller_id']])
            ->select([
                'dm.id',
                'p.sku as item_code',
                'p.mpn',
                'p.price as basic_price',
                'p.product_id',
                'p.freight', 'p.package_fee',
                'dm.price as delicacy_price',
                'dm.pickup_price',
                'dm.current_price',
                'dm.product_display',
                'dm.effective_time',
                'dm.expiration_time',
                'dm.buyer_id',
                'c.nickname as buyer_nickname',
                'c.user_number',
                'c.customer_group_id',
                'pd.name as product_name',
//                'sp.new_price',
//                'sp.effect_time as new_effect_time',
                'bts.discount',
                'bts.remark',
            ])
            ->where($where)
            ->distinct();
        if (isset_and_not_empty($input, 'product_id')) {
            $builder->leftJoin('oc_customerpartner_buyer_group_link as bgl', function ($join) {
                $join->on('bgl.buyer_id', '=', 'dm.buyer_id')
                    ->on('bgl.seller_id','=','dm.seller_id')
                    ->where([
                        ['bgl.status', '=', 1],
                    ]);
            })
                ->leftJoin('oc_customerpartner_buyer_group as bg', function ($join) {
                    $join->on('bg.id', '=', 'bgl.buyer_group_id')
                        ->on('bg.seller_id','=','dm.seller_id')
                        ->where([
                            ['bg.status', '=', 1]
                        ]);
                })
                ->addSelect(['bgl.buyer_group_id', 'bg.name as buyer_group_name','bg.is_default']);
        }

        if (isset_and_not_empty($input, 'product_id')) {
            $buyerIDs = $this->getDMGBuyersOrProducts(get_value_or_default($input, 'seller_id', 0), $input['product_id'], null);
            !empty($buyerIDs) && $builder->whereNotIn('dm.buyer_id', $buyerIDs);
        }
        if (isset_and_not_empty($input, 'buyer_id')) {
            $productIDs = $this->getDMGBuyersOrProducts(get_value_or_default($input, 'seller_id', 0), null, $input['buyer_id']);
            !empty($productIDs) && $builder->whereNotIn('dm.product_id', $productIDs);
        }

        if (isset_and_not_empty($input, 'search_str')) {
            $builder->where(function (Builder $query) use ($input) {
                $query->where('p.sku', 'like', '%' . $input['search_str'] . '%')
                    ->orWhere('p.mpn', 'like', '%' . $input['search_str'] . '%');
            });
        }
        $results['total'] = $builder->count('*');
        $results['data'] = $builder->forPage($results['page'], $results['pageSize'])
            ->orderBy('id', 'DESC')
            ->get();


        $products = [];
        $productGroups = [];
        if (isset_and_not_empty($input, 'buyer_id')) {
            foreach ($results['data'] as $value) {
                $products[] = $value->product_id;
            }
            $productGroups = $this->getProductGroup($products);
        }


        foreach ($results['data'] as &$datum) {
            $sellerPriceObj = $this->orm->table("oc_seller_price")
                ->select(['new_price', 'effect_time'])
                ->where([
                    ['product_id', '=', $datum->product_id],
                    ['status', '=', 1]
                ])
                ->orderByDesc('id')
                ->first();
            if (!empty($sellerPriceObj)) {
                $datum->new_price = $sellerPriceObj->new_price;
                $datum->new_effect_time = $sellerPriceObj->effect_time;
            }else{
                $datum->new_price = null;
                $datum->new_effect_time = null;
            }

            if (isset_and_not_empty($input, 'buyer_id')) {
                if (isset_and_not_empty($productGroups, $datum->product_id)) {
                    $datum->product_groups = $productGroups[$datum->product_id];
                }else{
                    $datum->product_groups = [];
                }
            }
            $datum->oversize_alarm_price = $this->model_common_product->getAlarmPrice($datum->product_id);
        }
        return $results;
    }

    /**
     * 根据 product/buyer 获取通过分组设置的buyer/product
     *
     * @param int $seller_id
     * @param null|int $product_id
     * @param null|int $buyer_id
     * @return array buyer_ids/product_ids
     */
    public function getDMGBuyersOrProducts($seller_id, $product_id = null, $buyer_id = null)
    {
        if (empty($product_id) && empty($buyer_id)) {
            return [];
        }

        $where = [
            ['dmg.status', '=', 1],
            ['pgl.status', '=', 1],
            ['bgl.status', '=', 1]
        ];
        !empty($seller_id) && $where[] = ['dmg.seller_id', '=', (int)$seller_id];
        if (empty($product_id)) {
            $where[] = ['bgl.buyer_id', '=', $buyer_id];
            $select = 'pgl.product_id';
        } else {
            $where[] = ['pgl.product_id', '=', $product_id];
            $select = 'bgl.buyer_id';
        }
        return $this->orm->table('oc_delicacy_management_group as dmg')
            ->join('oc_customerpartner_product_group_link as pgl', 'pgl.product_group_id', '=', 'dmg.product_group_id')
            ->join('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->select($select)
            ->where($where)
            ->distinct()
            ->pluck($select)
            ->toArray();
    }

    /**
     * 获取在库库存数量
     *
     * @param array $productIDArr
     * @return mixed
     */
    public function getInStockQuantity(array $productIDArr)
    {
        return $this->orm->table('tb_sys_batch')
            ->select([
                'product_id',
            ])
            ->selectRaw('SUM(onhand_qty) as instock_quantity')
            ->whereIn('product_id', $productIDArr)
            ->groupBy('product_id')
            ->get();
    }

    /**
     * 获取 combo 组成的相关信息
     *
     * @param array $productIDArr
     * @return mixed
     */
    public function getComboInfo(array $productIDArr)
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
     * @param int $product_id
     * @param int $seller_id
     * @return \Illuminate\Database\Eloquent\Model|object|static|null
     */
    public function getProductInfo($product_id, $seller_id = 0)
    {
        $where = [
            ['ctp.product_id', $product_id]
        ];
        $seller_id != 0 && $where[] = ['customer_id', $seller_id];
        return $this->orm->table('oc_customerpartner_to_product as ctp')
            ->join('oc_product as p','p.product_id','=','ctp.product_id')
            ->select([
                'ctp.price',
                'ctp.product_id',
                'p.freight'
            ])
            ->where($where)
            ->first();
    }

    /**
     * @param array $buyersIDs
     * @param int $seller_id
     * @return mixed
     */
    public function getBuyersByIDs($buyersIDs, $seller_id)
    {
        return $this->orm->table('oc_buyer_to_seller as bts')
            ->join('oc_customer as c','c.customer_id','=','bts.customer_id')
            ->select(['bts.buyer_id', 'bts.seller_id'])
            ->where([
                ['bts.seller_id', $seller_id],
                ['bts.seller_control_status', 1],
                ['c.status','=',1],
            ])
            ->whereIn('buyer_id', $buyersIDs)
            ->get();
    }

    /**
     * 获取该seller 已上架的所有product
     *
     * @param int $seller_id
     * @param int $page
     * @param int $pageSize
     * @param array $other_data
     * @return mixed
     */
    public function getAllProducts($seller_id, $page = 1, $pageSize = 9999 ,$other_data = [])
    {
        $objs = $this->orm->table('oc_customerpartner_to_product as ctp')
            ->leftJoin('oc_product as p', 'p.product_id', 'ctp.product_id')
            ->leftJoin('oc_product_description as pd', 'pd.product_id', '=', 'ctp.product_id')
            ->leftJoin('oc_seller_price as sp', 'sp.product_id', 'ctp.product_id')
            ->select([
                'ctp.product_id',
                'p.sku as item_code',
                'p.mpn','p.freight','p.package_fee',
                'pd.name as product_name',
                'p.quantity as onshelf_quantity',
                'p.price as basic_price',
                'sp.new_price',
                'sp.effect_time',
                'p.combo_flag',
                'sp.status as sp_status'
            ])
            ->where([
                ['p.status', '=', 1],
                ['p.status', '=', 1],
                ['ctp.customer_id', '=', $seller_id],
                ['p.buyer_flag', '=', 1],
                ['p.is_deleted', '=', 0],
            ])
            ->whereIn('p.product_type',[0,3])
            ->when(!empty($other_data['keywords']) && isset($other_data['keywords']), function ($query) use($other_data){
                return $query->where(function ($query_new) use ($other_data){
                    return $query_new->where('p.sku', 'like',"%{$other_data['keywords']}%")
                        ->orWhere('p.mpn','like',"%{$other_data['keywords']}%");
                });
            })
            ->when(isset($other_data['product_ids']) && !empty($other_data['product_ids'])  && is_array($other_data['product_ids']), function ($query) use($other_data){
                return $query->whereNotIn('ctp.product_id', $other_data['product_ids']) ;
            })
//            ->where(function ($query) {
//                $query->where('sp.status', '=', '1')
//                    ->orWhereNull('sp.status');
//            })
            ->forPage($page, $pageSize)
            ->get();
        foreach ($objs as &$obj) {
            if ($obj->effect_time && $obj->effect_time < date('Y-m-d H:i:s')) {
                $obj->new_price = null;
                $obj->effect_time = null;
            }
        }
        return $objs;
    }

    /**
     * 获取该seller 已上架的所有product数量
     *
     * @param int $seller_id
     * @param int $page
     * @param int $pageSize
     * @param array $other_data
     * @return int
     */
    public function getAllProductsTotal($seller_id, $other_data = [])
    {
        $count = $this->orm->table('oc_customerpartner_to_product as ctp')
            ->leftJoin('oc_product as p', 'p.product_id', 'ctp.product_id')
            ->leftJoin('oc_product_description as pd', 'pd.product_id', '=', 'ctp.product_id')
            ->leftJoin('oc_seller_price as sp', 'sp.product_id', 'ctp.product_id')
            ->select([
                'ctp.product_id',
                'p.sku as item_code',
                'p.mpn','p.freight','p.package_fee',
                'pd.name as product_name',
                'p.quantity as onshelf_quantity',
                'p.price as basic_price',
                'sp.new_price',
                'sp.effect_time',
                'p.combo_flag',
                'sp.status as sp_status'
            ])
            ->where([
                ['p.status', '=', 1],
                ['p.status', '=', 1],
                ['ctp.customer_id', '=', $seller_id],
                ['p.buyer_flag', '=', 1],
                ['p.is_deleted', '=', 0],
            ])
            ->whereIn('p.product_type',[0,3])
            ->when(isset($other_data['keywords']) && !empty($other_data['keywords']), function ($query) use($other_data){
                return $query->where(function ($query_new) use ($other_data){
                    return $query_new->where('p.sku', 'like',"%{$other_data['keywords']}%")
                        ->orWhere('p.mpn','like',"%{$other_data['keywords']}%");
                });
            })
            ->when(isset($other_data['product_ids']) && !empty($other_data['product_ids'])  && is_array($other_data['product_ids']), function ($query) use($other_data){
                return $query->whereNotIn('ctp.product_id', $other_data['product_ids']) ;
            })
            ->count();
        return $count;
    }


    /**
     * 获取该seller 关联的所有buyer
     *
     * @param int $seller_id
     * @param int $page
     * @param int $pageSize
     * @param array $otherData 其它数组条件
     * @return mixed
     */
    public function getAllBuyers($seller_id, $page = 1, $pageSize = 9999 , $otherData = [])
    {
        $objs = $this->orm->table('oc_buyer_to_seller as bts')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'bts.buyer_id')
            ->leftJoin('oc_customerpartner_buyer_group_link as bgl', function ($join) use ($seller_id) {
                $join->on('bgl.buyer_id', '=', 'bts.buyer_id')
                    ->where([
                        ['bgl.status', '=', 1],
                        ['bgl.seller_id', '=', $seller_id]
                    ]);

            })
            ->leftJoin('oc_customerpartner_buyer_group as bg', function ($join) {
                $join->on('bg.id', '=', 'bgl.buyer_group_id')
                    ->where('bg.status', '=', 1);
            })
            ->select([
                'c.customer_id as buyer_id',
                'c.nickname', 'c.user_number','c.customer_group_id',
                'bts.discount',
                'bts.add_time',
                'bts.remark',
                'bg.id as buyer_group_id',
                'bg.name as buyer_group_name',
                'bg.is_default'
            ])
            ->where([
                ['bts.seller_id', '=', $seller_id],
                ['bts.seller_control_status', '=', 1],
                ['c.status','=',1],
            ])
            ->whereNotNull('c.customer_id')
            ->when(isset($otherData['keywords']) && !empty($otherData['keywords']), function ($query) use($otherData){
                return $query->where(function ($query_new) use ($otherData){
                    return $query_new->where('c.user_number', 'like',"%{$otherData['keywords']}%")
                        ->orWhere('c.nickname','like',"%{$otherData['keywords']}%");
                });
            })
            ->forPage($page, $pageSize)
            ->orderBy('c.nickname', 'asc')
            ->get();
        return $objs;
    }

    /**
     * 获取该seller 关联的所有buyer 总数
     *
     * @param int $seller_id
     * @param  array $otherData
     * @return mixed
     */
    public function getAllBuyersTotal($seller_id , $otherData = [])
    {
        $count = $this->orm->table('oc_buyer_to_seller as bts')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'bts.buyer_id')
            ->leftJoin('oc_customerpartner_buyer_group_link as bgl', function ($join) use ($seller_id) {
                $join->on('bgl.buyer_id', '=', 'bts.buyer_id')
                    ->where([
                        ['bgl.status', '=', 1],
                        ['bgl.seller_id', '=', $seller_id]
                    ]);

            })
            ->leftJoin('oc_customerpartner_buyer_group as bg', function ($join) {
                $join->on('bg.id', '=', 'bgl.buyer_group_id')
                    ->where('bg.status', '=', 1);
            })
            ->select([
                'c.customer_id as buyer_id',
                'c.nickname', 'c.user_number','c.customer_group_id',
                'bts.discount',
                'bts.add_time',
                'bts.remark',
                'bg.id as buyer_group_id',
                'bg.name as buyer_group_name',
                'bg.is_default'
            ])
            ->where([
                ['bts.seller_id', '=', $seller_id],
                ['bts.seller_control_status', '=', 1],
                ['c.status','=',1],
            ])
            ->when(isset($otherData['keywords']) && !empty($otherData['keywords']), function ($query) use($otherData){
                return $query->where(function ($query_new) use ($otherData){
                    return $query_new->where('c.user_number', 'like',"%{$otherData['keywords']}%")
                        ->orWhere('c.nickname','like',"%{$otherData['keywords']}%");
                });
            })
            ->whereNotNull('c.customer_id')
            ->orderBy('c.nickname', 'asc')
            ->count();
        return $count;
    }

    /**
     * 获取 已参与精细化管理的product_id
     *
     * @param int $seller_id
     * @param int $buyer_id
     * @return array
     */
    public function getProductsInDelicacyManagement($seller_id, $buyer_id)
    {
        return $this->orm->table($this->table)
            ->where([
                ['buyer_id', '=', $buyer_id],
                ['seller_id', '=', $seller_id],
                ['expiration_time', '>', date('Y-m-d H:i:s')],
            ])
            ->pluck('product_id')
            ->toArray();
    }

    /**
     * 获取 已参与精细化管理的buyer_id
     *
     * 注：
     *   失效期要大于当前时间
     *
     * @param int $seller_id
     * @param int $product_id
     * @return array
     */
    public function getBuyersInDelicacyManagement($seller_id, $product_id)
    {
        return $this->orm->table($this->table.' as dm')
            ->join('oc_customer as c','c.customer_id','=','dm.buyer_id')
            ->where([
                ['dm.product_id', '=', $product_id],
                ['dm.seller_id', '=', $seller_id],
                ['dm.expiration_time', '>', date('Y-m-d H:i:s')],
                ['c.status','=',1]
            ])
            ->distinct()
            ->pluck('buyer_id')
            ->toArray();
    }

    /**
     * 获取 已产于精细化管理组的 product_id
     *
     * @param int $seller_id
     * @param int $buyer_id
     * @return array
     */
    public function getProductsInDelicacyManagementGroup($seller_id, $buyer_id)
    {
        return $this->orm->table('oc_delicacy_management_group as dmg')
            ->join('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->join('oc_customerpartner_product_group_link as pgl', 'pgl.product_group_id', '=', 'dmg.product_group_id')
            ->where([
                ['dmg.seller_id', '=', $seller_id],
                ['dmg.status', '=', 1],
                ['bgl.status', '=', 1],
                ['pgl.status', '=', 1],
                ['bgl.buyer_id', '=', $buyer_id]
            ])
            ->distinct()
            ->pluck('pgl.product_id')
            ->toArray();
    }

    /**
     *  获取 已参与精细化管理组的buyer_id
     *
     * @param int $seller_id
     * @param int $product_id
     * @return array
     */
    public function getBuyersInDelicacyManagementGroup($seller_id, $product_id)
    {
        return $this->orm->table('oc_delicacy_management_group as dmg')
            ->join('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->join('oc_customerpartner_product_group_link as pgl', 'pgl.product_group_id', '=', 'dmg.product_group_id')
            ->where([
                ['dmg.seller_id', '=', $seller_id],
                ['dmg.status', '=', 1],
                ['bgl.status', '=', 1],
                ['pgl.status', '=', 1],
                ['pgl.product_id', '=', $product_id]
            ])
            ->distinct()
            ->pluck('buyer_id')
            ->toArray();
    }

    /**
     * @param array $productIDArr
     * @param int $seller_id
     * @param int $buyer_id
     * @return \Illuminate\Support\Collection
     */
    public function getDelicacyIDByProducts($productIDArr, $seller_id, $buyer_id)
    {
        $where = [
            ['seller_id', '=', $seller_id],
            ['buyer_id', '=', $buyer_id],
            ['expiration_time', '>', date('Y-m-d H:i:s')]
        ];
        return $this->orm->table($this->table)
            ->select(['id', 'product_id'])
            ->where($where)
            ->whereIn('product_id', $productIDArr)
            ->get();
    }

    /**
     * @param array $buyerIDArr
     * @param int $seller_id
     * @param int $product_id
     * @return \Illuminate\Support\Collection
     */
    public function getDelicacyIDByBuyers($buyerIDArr, $seller_id, $product_id)
    {
        $where = [
            ['dm.seller_id', '=', $seller_id],
            ['dm.product_id', '=', $product_id],
            ['dm.expiration_time', '>', date('Y-m-d H:i:s')],
            ['c.status','=',1]
        ];
        return $this->orm->table($this->table.' as dm')
            ->join('oc_customer as c','c.customer_id','=','dm.buyer_id')
            ->select(['dm.id', 'dm.buyer_id'])
            ->where($where)
            ->whereIn('dm.buyer_id', $buyerIDArr)
            ->get();
    }

    /**
     * @param array $idArr
     * @return mixed
     */
    public function getInfoByIDArr($idArr, $seller_id)
    {
        $objs = $this->orm->table($this->table . ' as dm')
            ->join('oc_product as p', 'p.product_id', '=', 'dm.product_id')
            ->join('oc_customer as c','c.customer_id','=','dm.buyer_id')
            ->select([
                'dm.id',
                'dm.buyer_id',
                'dm.seller_id',
                'dm.current_price',
                'dm.price',
                'p.price as basic_price',
                'dm.product_display'
            ])
            ->whereIn('dm.id', $idArr)
            ->where([
                ['dm.seller_id', $seller_id],
                ['c.status','=',1],
                ['p.status','=',1],
                ['p.is_deleted','=',0],
                ['p.buyer_flag','=',1],
            ])
            ->get();
        return $objs;
    }

    /**
     * @param int $seller_id
     * @return Collection
     */
    public function getDownloadData($seller_id,$order)
    {
        $resultObjs = $this->orm->table($this->table . ' as dm')
            ->join('oc_product as p', 'p.product_id', '=', 'dm.product_id')
            ->join('oc_customer as c', 'c.customer_id', '=', 'dm.buyer_id')
            ->join('oc_product_description as pd', 'pd.product_id', '=', 'dm.product_id')
            ->join('oc_buyer_to_seller as bts', [['bts.buyer_id', 'dm.buyer_id'], ['bts.seller_id', 'dm.seller_id']])
            ->leftJoin('oc_customerpartner_buyer_group_link as bgl', function ($join) use ($seller_id) {
                $join->on('bgl.buyer_id', '=', 'dm.buyer_id')
                    ->where([
                        ['bgl.seller_id', '=', $seller_id],
                        ['bgl.status','=',1]
                    ]);
            })
            ->leftJoin('oc_customerpartner_buyer_group as bg', function ($join) use ($seller_id) {
                $join->on('bg.id','=','bgl.buyer_group_id')
                    ->where([
                        ['bg.seller_id', '=', $seller_id],
                        ['bg.status','=',1]
                    ]);
            })
            ->select([
                'dm.id',
                'p.sku as item_code', 'p.mpn', 'p.price as basic_price', 'p.product_id','p.freight',
                'dm.price as delicacy_price',
                'dm.current_price',
                'dm.product_display',
                'dm.effective_time',
                'dm.expiration_time',
                'dm.buyer_id', 'c.nickname as buyer_nickname', 'c.user_number','c.customer_group_id',
                'bg.name as buyer_group_name',
                'pd.name as product_name',
                'bts.discount', 'bts.remark',
            ])
            ->where([
                ['dm.seller_id','=',$seller_id],
                ['dm.product_display','=',1],
                ['c.status','=',1],
                ['p.status','=',1],
                ['p.is_deleted','=',0],
                ['p.buyer_flag','=',1],
                ['bts.seller_control_status','=',1],
            ])
            ->distinct();
        if ($order == 'buyer') {
            $resultObjs->orderBy('c.nickname', 'ASC')
                ->orderBy('p.sku', 'ASC');
        }else{
            $resultObjs->orderBy('p.sku', 'ASC')
                ->orderBy('c.nickname', 'ASC');
        }
        $resultObjs = $resultObjs->get();

        $productIDs = [];
        foreach ($resultObjs as $obj) {
            $productIDs[] = $obj->product_id;
        }

        $sellerPriceObjs = $this->orm->table("oc_seller_price")
            ->select(['new_price', 'effect_time','product_id'])
            ->where([
                ['status', '=', 1]
            ])
            ->whereIn('product_id',$productIDs)
            ->orderByDesc('id')
            ->get();
        $sellerPriceArr = [];
        foreach ($sellerPriceObjs as $sellerPriceObj) {
            !isset($sellerPriceArr[$sellerPriceObj->product_id]) && $sellerPriceArr[$sellerPriceObj->product_id] = $sellerPriceObj;
        }
        $productGroupObjs = $this->orm->table('oc_customerpartner_product_group as pg')
            ->leftJoin('oc_customerpartner_product_group_link as pgl', function ($join) use ($seller_id) {
                $join->on('pgl.product_group_id', '=', 'pg.id')
                    ->where([
                        ['pgl.status', '=', 1],
                        ['pgl.seller_id', '=', $seller_id]
                    ]);
            })
            ->select([
                'pgl.product_id', 'pg.name as product_group_name'
            ])
            ->distinct()
            ->get();
        $productGroupArr = [];
        foreach ($productGroupObjs as $productGroupObj) {
            $productGroupArr[$productGroupObj->product_id][] = $productGroupObj->product_group_name;
        }

        foreach ($resultObjs as &$obj) {
            if (isset($sellerPriceArr[$obj->product_id])) {
                $obj->new_price = $sellerPriceArr[$obj->product_id]->new_price;
                $obj->new_effect_time = $sellerPriceArr[$obj->product_id]->effect_time;
            } else {
                $obj->new_price = null;
                $obj->new_effect_time = null;
            }

            if (in_array($obj->customer_group_id, COLLECTION_FROM_DOMICILE)) {
                $obj->buyer_type = 'Home Pickup';
            }else{
                $obj->buyer_type = 'Dropshipping';
            }

            $obj->product_group_name = isset($productGroupArr[$obj->product_id]) ?
                implode(',', $productGroupArr[$obj->product_id]) : '';
            $obj->visibility = 'Visible';
        }
        return $resultObjs;
    }

    /**
     * 根据 product id 获取 其对应的分组名称
     *
     * @param array $products
     * @return array
     */
    public function getProductGroup($products)
    {
        if (!is_array($products) || empty($products)) {
            return [];
        }
        $objs = $this->orm->table('oc_customerpartner_product_group as pg')
            ->rightJoin('oc_customerpartner_product_group_link as pgl', 'pgl.product_group_id', '=', 'pg.id')
            ->where([
                ['pg.status', '=', '1'],
                ['pgl.status', '=', 1]
            ])
            ->whereIn('pgl.product_id', $products)
            ->select(['pgl.product_id', 'pg.name','pg.id as product_group_id'])
            ->get();
        $result = [];
        foreach ($objs as $obj) {
            $result[$obj->product_id][] = ['name' => $obj->name, 'id' => $obj->product_group_id];
        }

        return $result;
    }

//endregion

//region update
    /**
     * 批量添加不可见
     *
     * @param array $productIDArr
     * @param array $buyerIDArr
     * @param int $seller_id
     * @param int $status
     */
    public function batchAddInvisible($productIDArr, $buyerIDArr, $seller_id, $status = 1)
    {
        // 获取 product 的运费 和打包费
        $proObjs = $this->orm->table('oc_product')
            ->whereIn('product_id', $productIDArr)
            ->get(['freight', 'package_fee', 'product_id']);

        $proFees = [];
        foreach ($proObjs as $proObj) {
            $proFees[$proObj->product_id] = [
                'freight' => $proObj->freight ?: 0,
                'package_fee' => $proObj->package_fee ?: 0,
            ];
        }
        $keyValArr = [];
        foreach ($productIDArr as $productID) {
            foreach ($buyerIDArr as $buyerID) {
                $keyVal = [
                    'product_display' => $status,
                    'seller_id' => $seller_id,
                    'buyer_id' => $buyerID,
                    'product_id' => $productID,
                    'effective_time' => date('Y-m-d H:i:s'),
                    'expiration_time' => '9999-01-01 00:00:00',
                    'is_update' => 1,
                    'price' => 0,
                    'current_price' => 0,
                    'add_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s'),
                ];

                $id = $this->orm->table($this->table)
                    ->insertGetId($keyVal);
                unset($keyVal['is_update']);
                unset($keyVal['update_time']);
                $keyVal['origin_id'] = $id;
                $keyVal['type'] = 1;
                $keyVal['origin_add_time'] = $keyVal['add_time'];

                if (isset_and_not_empty($proFees, $productID)) {
                    $keyVal = array_merge($keyVal, $proFees[$productID]);
                }
                $keyValArr[] = $keyVal;
            }
        }
        // 添加历史记录
        $this->orm->table('oc_delicacy_management_history')
            ->insert($keyValArr);
    }

    /**
     * 批量设置不可见
     * 同时 price/current_price均为0，生效时间为现在，失效时间为最大
     *
     * @param array $delicacyIDArr
     * @param int $seller_id
     * @param int $status
     */
    public function batchSetInvisible($delicacyIDArr, $seller_id, $status = 1)
    {
        $data = $this->orm->table($this->table)
            ->where([
                ['seller_id', $seller_id],
                ['product_display', '=', $status ? 0 : 1]
            ])
            ->whereIn('id', $delicacyIDArr)
            ->get(['*']);

        $keyValArr = [];
        $delicacyIDArr = [];
        $update = [
            'product_display' => $status,
            'price' => 0,
            'current_price' => 0,
            'effective_time' => date('Y-m-d H:i:s'),
            'expiration_time' => '9999-01-01 00:00:00',
            'update_time' => date('Y-m-d H:i:s'),
        ];

        //获取 product 的运费和打包费
        $products = [];
        foreach ($data as $item) {
            $products[] = $item->product_id;
        }

        $proObjs = $this->orm->table('oc_product')
            ->whereIn('product_id', $products)
            ->get(['freight', 'package_fee', 'product_id']);

        $proFees = [];
        foreach ($proObjs as $proObj) {
            $proFees[$proObj->product_id] = [
                'freight' => $proObj->freight,
                'package_fee' => $proObj->package_fee,
            ];
        }

        foreach ($data as $item) {
            $temp = obj2array($item);
            $temp['type'] = 3;
            $temp['origin_id'] = $temp['id'];
            $temp['add_time'] = date('Y-m-d H:i:s');
            $temp['origin_add_time'] = $temp['add_time'];
            unset($temp['id']);
            unset($temp['is_update']);
            unset($temp['update_time']);

            if (isset_and_not_empty($proFees, $item->product_id)) {
                $temp = array_merge($temp, $proFees[$item->product_id]);
            }

            $keyValArr[] = array_merge($temp, $update);

            $delicacyIDArr[] = $item->id;
        }

        $this->orm->table($this->table)
            ->whereIn('id', $delicacyIDArr)
            ->update(array_merge($update, ['is_update' => 1]));
        $this->orm->table('oc_delicacy_management_history')
            ->insert($keyValArr);

    }

    /**
     * 修改
     *
     * 注：
     * 1. 如果是不可见，则 price 和 current_price 均为0，且立即生效;
     * 2. 如果是立即生效，则 current_price = price 且 is_update =1
     * 3. is_update 用于标识 此条数据 current_price 已被更新为 有效期内的price
     *
     * @param array $input
     */
    public function edit($input)
    {
        $data = $this->orm->table($this->table)
            ->where([
                ['id', '=', $input['id']]
            ])
            ->first();
        $data->origin_id = $data->id;
        $data->type = 3;

        $update = [
            'product_display' => $input['product_display'],
            'effective_time' => $input['effective_time'],
            'expiration_time' => $input['expiration_time'],
            'update_time' => date('Y-m-d H:i:s'),
        ];

        if ($input['product_display'] == 1) {
            $update['price'] = (float)$input['delicacy_price'];
        } else {
            $update['price'] = 0;
            $update['current_price'] = 0;
            $update['is_update'] = 1;   // 如果是不可见，也是立即生效，并且标识为已更新
        }

        // 如果立即生效，则 current_price = price
        if ($update['effective_time'] <= date('Y-m-d H:i:s')) {
            $update['current_price'] = $update['price'];
            $update['is_update'] = 1;   // 立即生效，标识为已更新
            $update['effective_time'] = date('Y-m-d H:i:s');
        } else {
            $update['current_price'] = $input['current_price'];
            $update['is_update'] = 0;
        }

        $this->orm->table($this->table)
            ->where('id', $data->id)
            ->update($update);

        $keyVal = array_merge(obj2array($data), $update);
        unset($keyVal['is_update']);
        unset($keyVal['update_time']);
        unset($keyVal['id']);
        $keyVal['origin_add_time'] = $keyVal['add_time'];
        $keyVal['add_time'] = date('Y-m-d H:i:s');

        // 获取 product 的运费和打包费
        $proObj = $this->getProductFee($data->product_id);
        if (isset($proObj->freight)) {
            $keyVal['freight'] = $proObj->freight;
            $keyVal['package_fee'] = $proObj->package_fee;
        }
        $this->orm->table('oc_delicacy_management_history')->insert($keyVal);
        // 发送消息给buyer
        $this->sendDelicacyChangeInfoToBuyer($data->product_id, $data->buyer_id);
    }

    /**
     * 添加
     *
     * @param array $input
     * @param int $seller_id
     * @return int
     */
    public function add($input, $seller_id)
    {
        $current_time = date("Y-m-d H:i:s");
        $keyVal = [
            'seller_id' => $seller_id,
            'buyer_id' => $input['buyer_id'],
            'product_id' => $input['product_id'],
            'product_display' => $input['product_display'],
            'update_time'=>$current_time,
            'add_time'=>$current_time
        ];

        if ($input['product_display'] == 1) {
            $keyVal['price'] = (float)$input['delicacy_price'];
            $keyVal['effective_time'] = $input['effective_time'];
            $keyVal['expiration_time'] = $input['expiration_time'];
            // 如果立即生效，则 is_update=1
            if ($input['effective_time'] > $current_time) {
                $keyVal['is_update'] = 0;
                $keyVal['current_price'] = (float)$input['basic_price'];
            } else {
                $keyVal['is_update'] = 1;
                $input['effective_time'] = $current_time;
                $keyVal['current_price'] = (float)$input['delicacy_price'];
            }
        } else {
            $keyVal['price'] = 0;
            $keyVal['current_price'] = 0;
            $keyVal['effective_time'] = $current_time;
            $keyVal['expiration_time'] = '9999-01-01 00:00:00';
            $keyVal['is_update'] = 1;
        }

        $id = $this->orm->table($this->table)
            ->insertGetId($keyVal);

        // 添加历史记录
        $keyVal['origin_id'] = $id;
        $keyVal['type'] = 1;
        $keyVal['origin_add_time'] = $current_time;
        unset($keyVal['update_time']);
        unset($keyVal['is_update']);

        // 获取 product 的运费和打包费
        $proObj = $this->getProductFee($input['product_id']);
        if (isset($proObj->freight)) {
            $keyVal['freight'] = $proObj->freight;
            $keyVal['package_fee'] = $proObj->package_fee;
        }
        $this->orm->table('oc_delicacy_management_history')->insert($keyVal);
        // 发送消息给buyer
        $this->sendDelicacyChangeInfoToBuyer($keyVal['product_id'], $keyVal['buyer_id']);
        return $id;
    }

    /**
     * 用于返点
     *
     * @param array $input
     * @return bool
     */
    public function addOrUpdate($input)
    {
        $current_time = date("Y-m-d H:i:s");
        if (
            !isset_and_not_empty($input, 'seller_id') ||
            !isset_and_not_empty($input, 'buyer_id') ||
            !isset_and_not_empty($input, 'product_id') ||
            !isset_and_not_empty($input, 'delicacy_price') ||
            !isset_and_not_empty($input, 'effective_time') ||
            !isset_and_not_empty($input, 'expiration_time')
        ) {
            return false;
        }
        $productObj = $this->orm->table('oc_product')
            ->select(['price','freight','package_fee'])
            ->where([
                ['is_deleted', '=', 0],
                ['status', '=', 1],
                ['buyer_flag','=',1]
            ])
            ->first();
        if (empty($productObj)) {
            return false;
        }
        $obj = $this->orm->table($this->table)
            ->select(['*'])
            ->where([
                ['buyer_id', '=', $input['buyer_id']],
                ['product_id', '=', $input['product_id']],
                ['seller_id', '=', $input['seller_id']]
            ])
            ->first();
        if (empty($obj)) {
            $keyVal = [
                'seller_id' => $input['seller_id'],
                'buyer_id' => $input['buyer_id'],
                'product_id' => $input['product_id'],
                'product_display' => 1,
                'update_time' => $current_time,
                'add_time' => $current_time,
                'price' => (float)$input['delicacy_price'],
                'effective_time' => $input['effective_time'],
                'expiration_time' => $input['expiration_time']
            ];
            // 如果立即生效，则 is_update=1
            if ($input['effective_time'] > $current_time) {
                $keyVal['is_update'] = 0;
                $keyVal['current_price'] = (float)$productObj->price;
            } else {
                $keyVal['is_update'] = 1;
                $input['effective_time'] = $current_time;
                $keyVal['current_price'] = (float)$input['delicacy_price'];
            }
            $id = $this->orm->table($this->table)
                ->insertGetId($keyVal);
            // 添加历史记录
            $keyVal['origin_id'] = $id;
            $keyVal['type'] = 1;
            $keyVal['origin_add_time'] = $current_time;
            unset($keyVal['update_time']);
            unset($keyVal['is_update']);
        } else {
            $obj->origin_id = $obj->id;
            $obj->type = 3;
            $update = [
                'product_display' => 1,
                'effective_time' => $input['effective_time'],
                'expiration_time' => $input['expiration_time'],
                'update_time' => date('Y-m-d H:i:s'),
                'price' => (float)$input['delicacy_price']
            ];
            // 如果立即生效，则 current_price = price
            if ($update['effective_time'] <= date('Y-m-d H:i:s')) {
                $update['current_price'] = $update['price'];
                $update['is_update'] = 1;   // 立即生效，标识为已更新
                $update['effective_time'] = date('Y-m-d H:i:s');
            } else {
                $update['current_price'] = $productObj->price;
                $update['is_update'] = 0;
            }
            $this->orm->table($this->table)
                ->where('id', $obj->id)
                ->update($update);
            $keyVal = array_merge(obj2array($obj), $update);
            unset($keyVal['is_update']);
            unset($keyVal['update_time']);
            unset($keyVal['id']);
            $keyVal['origin_add_time'] = $keyVal['add_time'];
            $keyVal['add_time'] = date('Y-m-d H:i:s');
        }

        // 历史记录添加当时的 运费和打包费
        if(isset($productObj->freight)){
            $keyVal['freight'] = $productObj->freight;
        }
        if(isset($productObj->package_fee)){
            $keyVal['package_fee'] = $productObj->package_fee;
        }
        $this->orm->table('oc_delicacy_management_history')->insert($keyVal);
        // 发送消息给buyer
        $this->sendDelicacyChangeInfoToBuyer($keyVal['product_id'], $keyVal['buyer_id']);
        return true;
    }

    /**
     * @param array $arr
     */
    public function batchSetPrice($arr)
    {
        $insertKeyValArr = [];
        foreach ($arr as $item) {
            $obj = $this->orm->table($this->table)->find($item['id']);
            $obj->origin_id = $obj->id;
            $obj->add_time = date('Y-m-d H:i:s');
            $obj->type = 3;
            $update = [
                'product_display' => 1,
                'effective_time' => $item['effective_time'],
                'expiration_time' => $item['expiration_time'],
                'price' => (float)$item['delicacy_price'],
                'is_update' => 0,
                'update_time'=>date("Y-m-d H:i:s")
            ];

            if ($obj->product_display == 0) {
                $update['current_price'] = $item['basic_price'];
            }
            if ($item['effective_time'] <= date('Y-m-d H:i:s')) {
                $update['effective_time'] = date('Y-m-d H:i:s');
                $update['current_price'] = $item['delicacy_price'];
                $update['is_update'] = 1;
            }

            $this->orm->table($this->table)
                ->where('id', $item['id'])
                ->update($update);
            $temp = array_merge(obj2array($obj), $update);
            unset($temp['id']);
            unset($temp['is_update']);
            unset($temp['update_time']);

            // 获取 product 的运费和打包费
            $proObj = $this->getProductFee($obj->product_id);
            if (isset($proObj->freight)) {
                $temp['freight'] = $proObj->freight;
                $temp['package_fee'] = $proObj->package_fee;
            }
            $insertKeyValArr[] = $temp;
            // 发送消息给buyer
            $this->sendDelicacyChangeInfoToBuyer($obj->product_id, $obj->buyer_id);
        }
        $this->orm->table('oc_delicacy_management_history')
            ->insert($insertKeyValArr);
    }

    /**
     * @param array $input
     * @param int $seller_id
     */
    public function batchAddBySetPrice($input, $seller_id)
    {
        $historyKeyValArr = [];
        $keyVal = [
            'product_display' => 1,
            'effective_time' => $input['effective_time'],
            'expiration_time' => $input['expiration_time'],
            'product_id' => $input['product_id'],
            'price' => $input['delicacy_price'],
            'seller_id' => $seller_id
        ];
        if ($input['effective_time'] <= date('Y-m-d H:i:s')) {
            $keyVal['effective_time'] = date('Y-m-d H:i:s');
            $keyVal['current_price'] = $keyVal['price'];
            $keyVal['is_update'] = 1;
        } else {
            $keyVal['current_price'] = $input['basic_price'];
            $keyVal['is_update'] = 0;
        }

        //获取当前 产品的运费以及打包费
        $proObj = $this->getProductFee($input['product_id']);

        foreach ($input['data'] as $buyer_id) {
            $keyVal['buyer_id'] = $buyer_id;
            $id = $this->orm->table($this->table)
                ->insertGetId($keyVal);

            unset($keyVal['is_update']);
            $temp = [
                'origin_id' => $id,
                'add_time' => date('Y-m-d H:i:s'),
                'type' => 1
            ];
            if (isset($proObj->freight)) {
                $temp['freight'] = $proObj->freight;
                $temp['package_fee'] = $proObj->package_fee;
            }
            $historyKeyValArr[] = array_merge($keyVal, $temp);
            // 发送消息给buyer
            $this->sendDelicacyChangeInfoToBuyer($keyVal['product_id'], $keyVal['buyer_id']);
        }
        $this->orm->table($this->historyTable)->insert($historyKeyValArr);
    }
//endregion

//region Delete

    /**
     * @param Collection $data
     */
    private function batchRemoveAndAddHistory($data)
    {
        $keyValArr = [];
        $delicacyIDArr = [];
        $temp = [
            'type' => 2,
        ];

        // 获取 product 的运费和打包费
        $products = [];
        foreach ($data as $item) {
            $products[] = $item->product_id;
        }

        $proObjs = $this->orm->table('oc_product')
            ->whereIn('product_id', $products)
            ->get(['freight', 'package_fee', 'product_id']);
        $proFees = [];
        foreach ($proObjs as $proObj) {
            $proFees[$proObj->product_id] = [
                'freight' => $proObj->freight ?: 0,
                'package_fee' => $proObj->package_fee ?: 0,
            ];
        }

        foreach ($data as $item) {
            foreach ($this->getKeyVal() as $_k => $_v) {
                $temp[$_k] = $_v['is_real_value'] ? $_v['column'] : $item->{$_v['column']};
            }
            if (isset_and_not_empty($proFees, $item->product_id)) {
                $temp = array_merge($temp, $proFees[$item->product_id]);
            }
            if(array_key_exists('freight', $temp) && is_null($temp['freight'])){
                $temp['freight'] = 0;
            }

            $keyValArr[] = $temp;
            $delicacyIDArr[] = $item->id;
        }

        !empty($delicacyIDArr) && $this->orm->table($this->table)
            ->whereIn('id', $delicacyIDArr)
            ->delete();
        !empty($keyValArr) && $this->orm->table('oc_delicacy_management_history')
            ->insert($keyValArr);
    }
    /**
     * 根据 buyers 批量删除
     *
     * @param array $buyerIDArr
     * @param int $seller_id
     */
    public function batchRemoveByBuyer($buyerIDArr, $seller_id)
    {
        $data = $this->orm->table($this->table)
            ->where([
                ['seller_id', $seller_id],
            ])
            ->whereIn('buyer_id', $buyerIDArr)
            ->get(['*']);

        $this->batchRemoveAndAddHistory($data);
    }

    /**
     * 根据 products 批量删除
     *
     * @param array $products
     * @param int $seller_id
     */
    public function batchRemoveByProducts($products, $seller_id)
    {
        $data = $this->orm->table($this->table)
            ->where([
                ['seller_id', $seller_id],
            ])
            ->whereIn('product_id', $products)
            ->get(['*']);

        $this->batchRemoveAndAddHistory($data);
    }

    /**
     * 根据 products 和 buyers 批量删除
     *
     * @param array $products
     * @param array $buyers
     * @param int $seller_id
     */
    public function batchRemoveByProductsAndBuyers($products, $buyers, $seller_id)
    {
        $data = $this->orm->table($this->table)
            ->where([
                ['seller_id', $seller_id],
            ])
            ->whereIn('product_id', $products)
            ->whereIn('buyer_id', $buyers)
            ->get(['*']);

        $this->batchRemoveAndAddHistory($data);
    }

    /**
     * 根据 ids 批量删除
     *
     * @param array $delicacyIDArr
     * @param int $seller_id
     */
    public function batchRemove($delicacyIDArr, $seller_id)
    {
        $data = $this->orm->table($this->table)
            ->where([
                ['seller_id', $seller_id],
            ])
            ->whereIn('id', $delicacyIDArr)
            ->get(['*']);

        $this->batchRemoveAndAddHistory($data);
    }
//endregion

    /**
     * buyer 获取当前价格
     *
     * @param int $product_id
     * @param int $buyer_id
     * @return array 如果为空数组，即该product对当前buyer不可见
     */
    public function getProductPrice(int $product_id, int $buyer_id): array
    {
        $productObj = $this->orm->table('oc_product as p')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->select(['p.price', 'ctp.customer_id as seller_id','p.freight'])
            ->where([
                ['p.product_id', $product_id],
                ['p.status', 1],
                ['p.buyer_flag', 1],
                ['p.is_deleted',0]
            ])
            ->first();
        if (empty($productObj)) {
            return [];
        }

        $discountObj = $this->orm->table('oc_buyer_to_seller')
            ->select(['discount', 'buyer_control_status', 'seller_control_status'])
            ->where([
                ['buyer_id', '=', $buyer_id],
                ['seller_id', '=', $productObj->seller_id],
                ['buyer_control_status', 1],
                ['seller_control_status', 1]
            ])
            ->first();
        if (empty($discountObj)) {
            return [];
        }

        $buyerObj = $this->orm->table('oc_customer')
            ->where('customer_id', $buyer_id)
            ->first(['customer_group_id']);

        // 如果存在组关联 即不可见，则返回空数组
        $is_exist_group = $this->orm->table('oc_delicacy_management_group as dmg')
            ->join('oc_customerpartner_product_group_link as pgl', 'pgl.product_group_id', '=', 'dmg.product_group_id')
            ->join('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->where([
                ['dmg.status', '=', 1],
                ['pgl.status', '=', 1],
                ['bgl.status', '=', 1],
                ['pgl.product_id', '=', $product_id],
                ['bgl.buyer_id', '=', $buyer_id]
            ])
            ->exists();
        if ($is_exist_group) {
            return [];
        }

        $delicacyObj = $this->orm->table($this->table)
            ->select(['id', 'current_price', 'price', 'product_display', 'effective_time', 'is_update'])
            ->where([
                ['buyer_id', $buyer_id],
                ['product_id', $product_id],
                ['expiration_time', '>', date('Y-m-d H:i:s')]
            ])
            ->first();

        $priceData = [
            'product_id' => $product_id,
            'discount' => empty($discountObj->discount) ? 1 : $discountObj->discount,
            'buyer_id' => $buyer_id,
            'seller_id' => $productObj->seller_id,
            'basic_price' => $productObj->price,
            'current_price' => $productObj->price,
            'freight' => $productObj->freight
        ];
        if (!empty($delicacyObj)) {
            //如果为不可见 直接返回 空数组
            if ($delicacyObj->product_display == 0) {
                return [];
            }
            if ($delicacyObj->effective_time < date('Y-m-d H:i:s') && $delicacyObj->is_update == 0) {
                $priceData['current_price'] = $delicacyObj->price;
            } else {
                $priceData['current_price'] = $delicacyObj->current_price;
            }
        }else{
            if (in_array($buyerObj->customer_group_id, COLLECTION_FROM_DOMICILE)) {
                $priceData['current_price'] = bcsub($priceData['basic_price'], $priceData['freight'] ?: 0, 2);
            }else{
                $priceData['current_price'] = $priceData['basic_price'];
            }
        }
        return $priceData;
    }

    /**
     * 此方法不需要随便使用。仅限购物车计算价格的时候使用
     *
     * 如果返回值为空/空数组, 均代表改商品不可购买(不可见/下架/议价不存在/未建立关联)
     *
     * @param int $product_id
     * @param int $buyer_id
     * @param int $quote_id
     * @return array
     */
    public function getQuotePrice(int $product_id, int $buyer_id, $quote_id): array
    {
        if (empty($quote_id)) {
            return [];
        }
        $quoteObj = $this->orm->table('oc_product_quote')->where('id', $quote_id)->first(['price']);
        if (empty($quoteObj)) {
            return [];
        }
        $productObj = $this->orm->table('oc_product as p')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->select(['p.price', 'ctp.customer_id as seller_id','p.freight','p.package_fee'])
            ->where([
                ['p.product_id', $product_id],
                ['p.status', 1],
                ['p.buyer_flag', 1]
            ])
            ->first();
        if (empty($productObj)) {
            return [];
        }

        // 如果存在组关联 即不可见，则返回空数组
        $is_exist_group = $this->orm->table('oc_delicacy_management_group as dmg')
            ->join('oc_customerpartner_product_group_link as pgl', 'pgl.product_group_id', '=', 'dmg.product_group_id')
            ->join('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->where([
                ['dmg.status', '=', 1],
                ['pgl.status', '=', 1],
                ['bgl.status', '=', 1],
                ['pgl.product_id', '=', $product_id],
                ['bgl.buyer_id', '=', $buyer_id]
            ])
            ->exists();
        if ($is_exist_group) {
            return [];
        }

        $delicacyObj = $this->orm->table($this->table)
            ->select(['id', 'current_price', 'price', 'product_display', 'effective_time', 'is_update'])
            ->where([
                ['buyer_id', $buyer_id],
                ['product_id', $product_id],
                ['expiration_time', '>', date('Y-m-d H:i:s')]
            ])
            ->first();
        if ($delicacyObj && $delicacyObj->product_display == 0) {
            return [];
        }

        $priceData = [
            'product_id' => $product_id,
//            'discount' => !isset_and_not_empty($discountObj, 'discount') ? 1 : $discountObj->discount,
            'buyer_id' => $buyer_id,
            'seller_id' => $productObj->seller_id,
            'basic_price' => $productObj->price,
            'current_price' => $productObj->price,
            'quote_price' => $quoteObj->price,
            'freight'=>$productObj->freight,
            'package_fee'=>$productObj->package_fee,
        ];
        if (!empty($delicacyObj)) {
            if ($delicacyObj->product_display && $delicacyObj->effective_time < date('Y-m-d H:i:s') && $delicacyObj->is_update == 0) {
                $priceData['current_price'] = $delicacyObj->price;
            } else {
                $priceData['current_price'] = $delicacyObj->current_price;
            }
        }else{
            $priceData['current_price'] = $priceData['basic_price'];
        }

        return $priceData;
    }

    /**
     * @return array
     */
    private function getKeyVal()
    {
        return [
            'origin_id'       => ['column' => 'id', 'is_real_value' => 0],
            'seller_id'       => ['column' => 'seller_id', 'is_real_value' => 0],
            'buyer_id'        => ['column' => 'buyer_id', 'is_real_value' => 0],
            'product_id'      => ['column' => 'product_id', 'is_real_value' => 0],
            'current_price'   => ['column' => 'current_price', 'is_real_value' => 0],
            'product_display' => ['column' => 'product_display', 'is_real_value' => 0],
            'price'           => ['column' => 'price', 'is_real_value' => 0],
            'effective_time'  => ['column' => 'effective_time', 'is_real_value' => 0],
            'expiration_time' => ['column' => 'expiration_time', 'is_real_value' => 0],
            'origin_add_time' => ['column' => 'add_time', 'is_real_value' => 0],
            'add_time'        => ['column' => date('Y-m-d H:i:s'), 'is_real_value' => 1]
        ];
    }

    /**
     * 获取这个seller的与审批通过且在有效期内的精细化id
     *
     * @param int $seller_id
     * @return \Illuminate\Support\Collection|array
     */
    public function getBidDelicacyRecord($seller_id)
    {
        return $this->orm->table('oc_rebate_agreement as a')
            ->join('oc_rebate_agreement_item as i', 'i.agreement_id', '=', 'a.id')
            ->join('oc_delicacy_management as dm', [
                ['a.buyer_id', '=', 'dm.buyer_id'],
                ['a.seller_id', '=', 'dm.seller_id'],
                ['i.product_id', '=', 'dm.product_id'],
                ['a.effect_time', '=', 'dm.effective_time'],
            ])
            ->where([
                ['a.status', '=', 3],
                ['a.seller_id', '=', $seller_id],
                ['a.expire_time', '>', date('Y-m-d H:i:s')],
                ['dm.product_display', '=', 1]
            ])
            ->pluck('dm.id')
            ->toArray();
    }


    /**
     * 获取运费计算模板
     *
     * @return null|array 如果返回null代表不存在
     */
    public function getDownloadTemplate()
    {
        $pathObj = $this->orm->table('tb_sys_setup')
            ->where('parameter_key', 'QUOTE_PATH')
            ->first(['parameter_value as path']);
        $fileObj = $this->orm->table('tb_logistics_quote')
            ->where('status', '=', 1)
            ->where(function ($query) {
                $query->where([
                    ['effect_date', '<=', date('Y-m-d H:i:s')],
                    ['expire_date', '>', date('Y-m-d H:i:s')]
                ])
                    ->orWhere(function ($q) {
                        $q->where('effect_date', '<=', date('Y-m-d H:i:s'))
                            ->whereNull('expire_date');
                    });
            })
            ->first(['file_path as file']);
        if (isset($pathObj->path) && $pathObj->path && isset($fileObj->file) && $fileObj->file) {
            return [
                'path' => $pathObj->path . '/' . $fileObj->file,
                'file' => $fileObj->file,
            ];
        }else{
            return null;
        }
    }

    /**
     * 验证 buyer 是否为上门取货类型的
     * @param int $buyer_id
     * @return bool
     */
    public function checkBuyerIsHomePickup($buyer_id)
    {
        $obj = $this->orm->table('oc_customer')
            ->where([
                ['customer_id', '=', $buyer_id]
            ])
            ->first(['customer_group_id']);
        if (empty($obj)) {
            return false;
        }else{
            return in_array($obj->customer_group_id, COLLECTION_FROM_DOMICILE);
        }
    }

    /**
     * @param int $product_id
     * @param int $buyer_id
     * @return array
     */
    public function getProductInfoAndFreight($product_id,$buyer_id)
    {
        $productObj = $this->orm->table('oc_product as p')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->select(['p.price', 'ctp.customer_id as seller_id','p.freight','p.quantity'])
            ->where([
                ['p.product_id', $product_id],
                ['p.status', 1],
                ['p.buyer_flag', 1],
                ['p.is_deleted',0]
            ])
            ->first();
        if (empty($productObj)) {
            return [];
        }

        $discountObj = $this->orm->table('oc_buyer_to_seller')
            ->select(['discount', 'buyer_control_status', 'seller_control_status'])
            ->where([
                ['buyer_id', '=', $buyer_id],
                ['seller_id', '=', $productObj->seller_id],
                ['buyer_control_status', 1],
                ['seller_control_status', 1]
            ])
            ->first();
        if (empty($discountObj)) {
            return [];
        }

        // 如果存在组关联 即不可见，则返回空数组
        $is_exist_group = $this->orm->table('oc_delicacy_management_group as dmg')
            ->join('oc_customerpartner_product_group_link as pgl', 'pgl.product_group_id', '=', 'dmg.product_group_id')
            ->join('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->where([
                ['dmg.status', '=', 1],
                ['pgl.status', '=', 1],
                ['bgl.status', '=', 1],
                ['pgl.product_id', '=', $product_id],
                ['bgl.buyer_id', '=', $buyer_id]
            ])
            ->exists();
        if ($is_exist_group) {
            return [];
        }

        $buyerObj = $this->orm->table('oc_customer')
            ->where('customer_id', $buyer_id)
            ->first(['customer_group_id']);

        $delicacyObj = $this->orm->table($this->table)
            ->select(['id', 'current_price', 'price', 'product_display', 'effective_time', 'is_update'])
            ->where([
                ['buyer_id', $buyer_id],
                ['product_id', $product_id],
                ['expiration_time', '>', date('Y-m-d H:i:s')]
            ])
            ->first();
        //如果为不可见 直接返回 空数组
        if (!empty($delicacyObj) && $delicacyObj->product_display == 0) {
            return [];
        }

        $priceData = [
            'product_id' => $product_id,
            'discount' => empty($discountObj->discount) ? 1 : $discountObj->discount,
            'buyer_id' => $buyer_id,
            'seller_id' => $productObj->seller_id,
            'basic_price' => $productObj->price,
            'current_price' => $productObj->price,
            'freight'=>$productObj->freight,
            'quantity'=>$productObj->quantity,
        ];
        if (!empty($delicacyObj)) {
            if ($delicacyObj->effective_time < date('Y-m-d H:i:s') && $delicacyObj->is_update == 0) {
                $priceData['current_price'] = $delicacyObj->price;
            } else {
                $priceData['current_price'] = $delicacyObj->current_price;
            }
        }
        return $priceData;
    }

    /**
     * 验证当前 buyer 已经对应product 是否参与返点
     *
     * @param int $product_id
     * @param int $buyer_id
     * @return bool
     */
    public function checkProductIsRebate($product_id, $buyer_id)
    {
        return $this->orm->table('oc_rebate_agreement as a')
            ->join('oc_rebate_agreement_item as i', 'i.agreement_id', '=', 'a.id')
            ->join('oc_customerpartner_to_product as ctp', 'ctp.customer_id', '=', 'a.seller_id')
            ->where([
                ['a.status', '=', 3],
                ['i.product_id', '=', $product_id],
                ['ctp.product_id', '=', $product_id],
                ['a.buyer_id', '=', $buyer_id],
                ['a.expire_time', '>', date('Y-m-d H:i:s')],
            ])
            ->whereIn('a.rebate_result', [1, 2])
            ->exists();
    }

    /**
     * 判断商品是否可见
     *
     * @param int $product_id
     * @param int $buyer_id
     * @return bool
     */
    public function checkIsDisplay($product_id, $buyer_id)
    {
        // 产品 不存在
        $isExist = $this->orm->table('oc_product as p')
            ->where([
                ['p.status', '=', 1],
                ['p.buyer_flag', '=', 1],
                ['p.is_deleted', '=', 0],
                ['p.product_id', '=', $product_id],
            ])
            ->exists();
        if (!$isExist) {
            return false;
        }
        // 精细化不可见
        $isNotDisplay = $this->orm->table($this->table.' as dm')
            ->where([
                ['buyer_id', '=', $buyer_id],
                ['product_id', '=', $product_id],
                ['product_display', '=', 0],
                ['effective_time', '<', date('Y-m-d H:i:s')],
                ['expiration_time', '>', date('Y-m-d H:i:s')],
            ])
            ->exists();
        if ($isNotDisplay) {
            return false;
        }

        //精细化组不可见
        $isNotDisplay = $this->orm->table('oc_delicacy_management_group as dmg')
            ->join('oc_customerpartner_product_group_link as pgl', 'pgl.product_group_id', '=', 'dmg.product_group_id')
            ->join('oc_customerpartner_product_group as pg','pg.id','=','dmg.product_group_id')
            ->join('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->join('oc_customerpartner_buyer_group as bg','bg.id','=','dmg.buyer_group_id')
            ->where([
                ['dmg.status', '=', 1],
                ['pgl.status', '=', 1],
                ['bgl.status', '=', 1],
                ['pgl.product_id', '=', $product_id],
                ['bgl.buyer_id', '=', $buyer_id],
                ['pg.status','=',1],
                ['bg.status','=',1]
            ])
            ->exists();
        return !$isNotDisplay;
    }

    /**
     * 判断商品是否可见---批量
     * 上架且可独立售卖且Buyer可见
     *
     * @param int $product_id_list
     * @param int $buyer_id
     * @return array 返回的全部是可见的
     */
    public function checkIsDisplay_batch($product_id_list, $buyer_id)
    {
        // 产品 不存在
        $isExist = $this->orm->table('oc_product as p')
            ->where([
                ['p.status', '=', 1],
                ['p.buyer_flag', '=', 1],
                ['p.is_deleted', '=', 0],
            ])
            ->whereIn('p.product_id', $product_id_list)
            ->get(['p.product_id']);
        if (!$isExist) {
            return array();
        }
        $product_id_list=array_column(obj2array($isExist),'product_id');
        // 精细化不可见
        $isNotDisplay = $this->orm->table($this->table.' as dm')
            ->where([
                ['buyer_id', '=', $buyer_id],
                ['product_display', '=', 0],
                ['effective_time', '<', date('Y-m-d H:i:s')],
                ['expiration_time', '>', date('Y-m-d H:i:s')],
            ])
            ->whereIn( 'product_id',  $product_id_list)
            ->get(['product_id']);
        $isNotDisplay=array_column(obj2array($isNotDisplay),'product_id');
        $product_id_list=array_diff($product_id_list,$isNotDisplay);

        //精细化组不可见
        $isNotDisplay = $this->orm->table('oc_delicacy_management_group as dmg')
            ->join('oc_customerpartner_product_group_link as pgl', 'pgl.product_group_id', '=', 'dmg.product_group_id')
            ->join('oc_customerpartner_product_group as pg','pg.id','=','dmg.product_group_id')
            ->join('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->join('oc_customerpartner_buyer_group as bg','bg.id','=','dmg.buyer_group_id')
            ->where([
                ['dmg.status', '=', 1],
                ['pgl.status', '=', 1],
                ['bgl.status', '=', 1],
                ['bgl.buyer_id', '=', $buyer_id],
                ['pg.status','=',1],
                ['bg.status','=',1]
            ])
            ->whereIn( 'pgl.product_id',  $product_id_list)
            ->get(['product_id']);
        $isNotDisplay=array_column(obj2array($isNotDisplay),'product_id');
        $product_id_list=array_diff($product_id_list,$isNotDisplay);
        // 判断是否建立关联
        $is_connect=$this->orm->table(DB_PREFIX.'customerpartner_to_product as ocp')
            ->leftJoin(DB_PREFIX.'buyer_to_seller as obs','obs.seller_id','=','ocp.customer_id')
            ->where([
                ['obs.buyer_id','=',$buyer_id],
                ['obs.buyer_control_status','=',1],
                ['obs.seller_control_status','=',1],
            ])
            ->whereIn('ocp.product_id',$product_id_list)
            ->get(['ocp.product_id']);
        $product_id_list = array_column(obj2array($is_connect),'product_id');
        return $product_id_list;
    }

    /**
     *发送精细化价格变动信息给对应buyer
     * @param int $product_id 商品id
     * @param int $buyer_id 卖家id 可以是整数 也可以是数组
     * @return false
     * @throws Exception
     */
    public function sendDelicacyChangeInfoToBuyer(int $product_id, $buyer_id)
    {
        $this->load->model('message/message');
        // 获取订阅该产品的所有buyer
        // 查询订阅此产品的buyer 且buyer seller 建立了联系
        $map = [
            ['bts.seller_id', '=', (int)$this->customer->getId()],
            ['cw.product_id', '=', $product_id],
        ];
        $res = $this->orm->table(DB_PREFIX . 'customer_wishlist as cw')
            ->leftJoin(DB_PREFIX . 'buyer_to_seller as bts', 'bts.buyer_id', '=', 'cw.customer_id')
            ->where($map)
            ->select('bts.buyer_id', 'bts.seller_id', 'bts.discount')
            ->get()
            ->map(function ($item) {
                return (int)$item->buyer_id;
            })
            ->toArray();
        // 取交集 只有订阅 且 精细化的才发信息
        $res = array_intersect($res, (array)$buyer_id);
        if (!$res) {
            return false;
        }
        // 对于每个用户发送消息
        foreach ($res as $b_id) {
            if ($ret = $this->resolveProductMessage($product_id, $b_id)) {
                list($title, $msg) = $ret;
                $this->model_message_message->addSystemMessageToBuyer('product_price', $title, $msg, $b_id);
            }
        }
    }

    /**
     * 发送消息模板
     * @param int $product_id
     * @param int $buyer_id
     * @return null | array
     */
    private function resolveProductMessage(int $product_id, int $buyer_id)
    {
        $dm_info = App::orm()
            ->table('oc_delicacy_management')
            ->where([
                'product_id' => $product_id,
                'buyer_id' => $buyer_id,
            ])
            ->first();
        $product = $this->orm->table(DB_PREFIX . 'product as p')
            ->leftJoin('oc_product_description as pd', 'pd.product_id', '=', 'p.product_id')
            ->where('p.product_id', $product_id)
            ->select('p.sku', 'pd.name', 'p.quantity', 'p.price', 'p.freight')
            ->first();
        if (bccomp($dm_info->current_price, $dm_info->price) === 0) {
            return null;
        }

        $customer = Customer::query()->where('customer_id', $buyer_id)->first();
        $dm_info->current_price = app(ProductPriceRepository::class)->getProductActualPriceByBuyer(customer()->getId(), $customer, $dm_info->current_price ?? 0);
        $dm_info->price = app(ProductPriceRepository::class)->getProductActualPriceByBuyer(customer()->getId(), $customer, $dm_info->price ?? 0);

        $subject = 'Unit Price Change Alert (Item Code: ' . $product->sku . ')';
        $message = '<br><h3>Please remind that the unit price of one subscribed product will be changed：</h3><br>';
        $message .= '<table  border="0" cellspacing="0" cellpadding="0" >';
        $message .= '<tr><th align="left">Item Code:</th><td>' . $product->sku . '</td></tr>';
        $message .= '<tr><th align="left">Product Name:</th><td>' . $product->name . '</td></tr>';
        $message .= '<tr><th align="left">Current Unit Price：</th><td>' . $dm_info->current_price . '</td></tr>';
        $message .= '<tr><th align="left">Modified Unit Price：</th><td>' . $dm_info->price . '</td></tr>';
        $message .= '<tr><th align="left">Effect Time：</th><td>' . $dm_info->effective_time . '</td></tr>';
        $message .= '</table><br>';
        $message .= 'Click <a href="' . HTTPS_SERVER . 'index.php?route=product/product&product_id=' . $product_id . '">here</a> to visit the product page.';

        return [$subject, $message];
    }
}
