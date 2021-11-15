<?php

use App\Enums\Common\YesNoEnum;
use App\Models\CWF\OrderCloudLogistics;
use App\Models\CWF\OrderCloudLogisticsTracking;
use Illuminate\Support\Collection;

/**
 * Class ModelAccountSalesOrderCloudWholesaleFulfillment
 * @property ModelToolImage $model_tool_image
 */
class ModelAccountSalesOrderCloudWholesaleFulfillment extends Model
{

    /**
     * ModelAccountSalesOrderCloudWholesaleFulfillment constructor.
     * @param Registry $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);

    }

    /**
     * 根据状态 统计数据
     *
     * @param int $buyer_id
     * @param array|null $status all:null
     * @return int
     */
    public function countByStatus($buyer_id, $status)
    {
        return $this->orm->table('oc_order_cloud_logistics as cl')
            ->join('tb_sys_customer_sales_order as so', 'so.id', '=', 'cl.sales_order_id')
            ->where('cl.buyer_id', '=', $buyer_id)
            ->when(!is_null($status) && is_array($status), function (\Illuminate\Database\Query\Builder $query) use ($status) {
                $query->whereIn('cl.cwf_status', $status);
            })
            ->count();
    }

    /**
     * @param int $buyer_id
     * @param array|null $status
     * @param int $startPage
     * @param int $pageSize
     * @return mixed
     */
    public function getListByStatus($buyer_id, $status, $startPage, $pageSize, $sort, $order)
    {
        $builder = $this->orm->table('oc_order_cloud_logistics as cl')
            ->join('tb_sys_customer_sales_order as so', 'so.id', '=', 'cl.sales_order_id')
            ->select([
                'cl.id',
                'so.order_id as sales_order_code',
                'cl.service_type',
                'cl.cwf_status',
                'so.order_status',
                'so.create_time',
                'cl.pallet_label_file_id'
            ])
            ->where('cl.buyer_id', '=', $buyer_id)
            ->when(!is_null($status) && is_array($status), function (\Illuminate\Database\Query\Builder $query) use ($status) {
                $query->whereIn('cl.cwf_status', $status);
            });

        $result['total'] = $builder->count();
        $result['rows'] = $builder->orderBy($sort, $order)
            ->forPage($startPage, $pageSize)
            ->get();
        return $result;
    }

    /**
     * 获取 发FBA 且 已备货的 订单数量
     * 101843 增加限制只有没传超重标的
     *
     * @param int $buyer_id
     * @return int
     */
    public function countFBAAndCP($buyer_id)
    {
        return $this->orm->table('oc_order_cloud_logistics as cl')
            ->join('tb_sys_customer_sales_order as so', 'so.id', '=', 'cl.sales_order_id')
            ->where([
                        ['cl.buyer_id', '=', $buyer_id],
                        ['cl.service_type', '=', 1],
                        ['cl.cwf_status', '=', 3],
                    ])
            ->where(function ($query) {
                $query->whereNull('cl.pallet_label_file_id')
                    ->orWhere('cl.pallet_label_file_id', 0);
            })
            ->count();
    }

    /**
     * @param array $cl_ids
     * @return Collection
     */
    public function getItemsByIDs($cl_ids)
    {
        return $this->orm->table('oc_order_cloud_logistics_item as i')
            ->join('oc_product as p', 'p.product_id', '=', 'i.product_id')
            ->select([
                'i.cloud_logistics_id',
                'i.product_id',
                'i.item_code',
                'i.qty',
                'p.combo_flag', 'p.part_flag',
            ])
            ->whereIn('cloud_logistics_id', array_unique($cl_ids))
            ->get();
    }

    /**
     * @param array $cl_ids
     * @return array
     */
    public function countTotalPalletQTYByIDs($cl_ids)
    {
        $temp = [];
        $this->orm->table('oc_order_cloud_logistics_batch as b')
            ->whereIn('b.cloud_logistics_id', $cl_ids)
            ->select('b.cloud_logistics_id')
            ->selectRaw('sum(pallet_qty) as total')
            ->groupBy('b.cloud_logistics_id')
            ->get()
            ->map(function ($value, $key) use (&$temp) {
                $temp[$value->cloud_logistics_id] = $value->total;
            });
        return $temp;
    }

    /**
     * @param int $id
     * @return int
     */
    public function sumTotalPalletQTYByID($id)
    {
        return $this->orm->table('oc_order_cloud_logistics_batch')
            ->where('cloud_logistics_id', '=', $id)
            ->sum('pallet_qty');
    }

    /**
     * 根据云送仓订单 获取 tracking number
     *
     * @param array $cl_ids
     * @return array
     */
    public function getTrackingNumbersByIDs($cl_ids)
    {
        $temp = [];
        $this->orm->table('oc_order_cloud_logistics_tracking as t')
            ->whereIn('t.cloud_logistics_id', $cl_ids)
            ->where('t.is_deleted', '=', 0)
            ->select([
                't.cloud_logistics_id',
                't.pallet_qty',
                't.carrier',
                't.tracking_number',
                't.shipping_status',
            ])
            ->get()
            ->map(function ($value, $key) use (&$temp) {
                $temp[$value->cloud_logistics_id][] = $value;
            });
        return $temp;
    }

//region Info

    /**
     * 获取 订单的主要信息
     *
     * @param $cl_id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getInfo($cl_id)
    {
        $obj = OrderCloudLogistics::query()->alias('cl')
            ->leftJoin('tb_sys_customer_sales_order as so', 'so.id', '=', 'cl.sales_order_id')
            ->leftJoin('oc_order as o', 'o.order_id', '=', 'cl.order_id')
            ->leftJoin('oc_order_cloud_logistics_file as f', 'f.id', '=', 'cl.team_lift_file_id')
            ->leftJoin('oc_order_cloud_logistics_file as lf', 'lf.id', '=', 'cl.pallet_label_file_id')
            ->select([
                'so.order_id as sales_order_code','so.create_time as so_create_time',
                'cl.order_id as purchase_order_id',
                'f.file_name as tl_file_name', 'f.file_path as tl_file_path',
                'lf.file_name as lb_file_name', 'lf.file_path as lb_file_path',
            ])
            ->selectRaw('cl.*')
            ->where([
                ['cl.id', '=', $cl_id],
            ])
            ->first();
        return $obj;
    }

    /**
     * 获取 订单明细
     *
     * @param $cl_id
     * @return Collection
     */
    public function getItems($cl_id)
    {
        return $this->orm->table('oc_order_cloud_logistics as cl')
            ->join('oc_order_cloud_logistics_item as cli', 'cli.cloud_logistics_id', '=', 'cl.id')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'cli.product_id')
            ->leftJoin('oc_order_product_info as opi', [['opi.order_id', '=', 'cl.order_id'], ['opi.product_id', '=', 'cli.product_id']])
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'cli.seller_id')
            ->leftJoin('oc_order_cloud_logistics_file as pak', 'pak.id', '=', 'cli.package_label_file_id')
            ->leftJoin('oc_order_cloud_logistics_file as pro', 'pro.id', '=', 'cli.product_label_file_id')
            ->select([
                'cli.cloud_logistics_id',
                'ctc.screenname as store',
                'opi.id as order_product_info_id',
                'cli.product_id', 'p.image', 'cli.item_code as sku', 'opi.combo_flag',
                'opi.weight_lbs', 'opi.length_cm', 'opi.width_cm', 'opi.height_cm', 'opi.volume',
                'opi.length_inch', 'opi.width_inch', 'opi.height_inch', 'opi.volume_inch',
                'cli.merchant_sku', 'cli.fn_sku', 'cli.qty','cli.team_lift_status',
                'pak.file_name as pak_file_name', 'pak.file_path as pak_file_path',
                'pro.file_name as pro_file_name', 'pro.file_path as pro_file_path',
            ])
            ->where('cl.id', '=', $cl_id)
            ->get();
    }

    /**
     * 获取combo的子sku
     *
     * @param int $order_product_info_id
     * @param $productId
     * @return Collection
     */
    public function getComboItems($order_product_info_id, $productId)
    {
        return $this->orm->table('oc_order_product_set_info as opsi')
            ->select([
                'opsi.item_code as sku',
                'opsi.set_product_id',
                'si.qty',
                'opsi.weight_lbs',
                'opsi.length_cm', 'opsi.width_cm', 'opsi.height_cm', 'opsi.volume',
                'opsi.length_inch', 'opsi.width_inch', 'opsi.height_inch', 'opsi.volume_inch',
            ])
            ->join('tb_sys_product_set_info as si', 'si.set_product_id', '=', 'opsi.set_product_id')
            ->where('si.product_id', $productId)
            ->where('opsi.order_product_info_id', '=', $order_product_info_id)
            ->get();
    }

    /**
     * @param int $order_id
     * @return array
     */
    public function getRMAIDByOrderID($order_id)
    {
        return $this->orm->table('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as op', 'op.rma_id', '=', 'ro.id')
            ->select([
                'ro.rma_order_id as rma_order_code', 'ro.id', 'ro.seller_status', 'ro.cancel_rma',
                'op.status_refund', 'op.status_reshipment',
            ])
            ->where([
                ['ro.order_id', '=', $order_id],
            ])
            ->get()
            ->toArray();
    }

    /**
     * 获取 product tags
     *
     * @param array $product_ids
     * @return array
     */
    public function getProductTags($product_ids)
    {
        $this->load->model('tool/image');
        $temp = [];
        $this->orm->table('oc_product_to_tag as ptt')
            ->leftJoin('oc_tag as t', 't.tag_id', '=', 'ptt.tag_id')
            ->select([
                'ptt.product_id', 't.description', 't.icon','t.class_style'
            ])
            ->whereIn('ptt.product_id', array_unique($product_ids))
            ->get()
            ->map(function ($value) use (&$temp) {
                $temp[$value->product_id][] = [
                    'desc' => $value->description,
                    'icon' => $value->icon,
                    'icon_image_url' => $this->model_tool_image->getOriginImageProductTags($value->icon),
                    'class_style' => $value->class_style,
                ];
            });
        return $temp;
    }

    /**
     * @param int $clId oc_order_cloud_logistics.cloud_logistics_id
     * @return OrderCloudLogisticsTracking[]|Collection
     */
    public function getTrackingByID(int $clId)
    {
        return OrderCloudLogisticsTracking::query()->where('cloud_logistics_id', $clId)
            ->where('is_deleted', YesNoEnum::NO)
            ->get([
                'cloud_logistics_id',
                'pallet_qty',
                'carrier',
                'tracking_number',
                'shipping_status',
                'bol_signed_file_id',
                'pod_file_id',
            ]);
    }

    /**
     * @param $cl_id
     * @return Collection
     */
    public function getAttachments($cl_id)
    {
        return $this->orm->table('oc_order_cloud_logistics_attachment as a')
            ->leftJoin('oc_order_cloud_logistics_file as f', 'f.id', '=', 'a.cloud_logistics_file_id')
            ->select([
                'f.file_name', 'f.file_path',
            ])
            ->where([
                ['a.cloud_logistics_id', '=', $cl_id],
                ['a.is_deleted', '=', 0]
            ])
            ->get();
    }

    /**
     * @param $data
     * @return int
     */
    public function uploadPalletLabelFile($data)
    {
        $keyVal = [
            'file_name' => $data['file_name'],
            'file_path' => $data['file_path'],
            'file_type' => $data['file_type'],
            'create_user_name' => $data['customer_id'],
            'update_user_name' => $data['customer_id'],
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ];
        return $this->orm->table('oc_order_cloud_logistics_file')->insertGetId($keyVal);
    }

    /**
     * @param $cl_id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getPalletLabel($cl_id)
    {
        return $this->orm->table('oc_order_cloud_logistics')
            ->where('id', '=', $cl_id)
            ->select(['id', 'pallet_label_file_id'])
            ->first();
    }

    /**
     * @param $file_id
     * @return bool
     */
    public function checkLabelFile($file_id)
    {
        return $this->orm->table('oc_order_cloud_logistics_file')
            ->where('id', '=', $file_id)
            ->exists();
    }

    /**
     * @param int $cl_id
     * @param int $file_id
     * @param int $customer_id
     */
    public function savePalletLabel($cl_id, $file_id, $customer_id)
    {
        $this->orm->table('oc_order_cloud_logistics')
            ->where('id', $cl_id)
            ->update([
                'pallet_label_file_id' => $file_id,
                'update_user_name' => $customer_id,
                'update_time' => date('Y-m-d H:i:s')
            ]);
    }

//end of region info
}
