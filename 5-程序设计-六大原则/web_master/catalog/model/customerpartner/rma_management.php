<?php

use App\Components\UniqueGenerator\Enums\ServiceEnum;
use App\Enums\Pay\PayCode;
use App\Repositories\Common\SerialNumberRepository;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Services\SellerAsset\SellerAssetService;
use App\Enums\Warehouse\BatchTransactionType;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;

/**
 * Class ModelCustomerpartnerRmaManagement
 *
 * @property ModelAccountCustomerpartnerMarginOrder $model_account_customerpartner_margin_order
 * @property ModelAccountCustomerpartnerFuturesOrder $model_account_customerpartner_futures_order
 * @property ModelAccountBalanceVirtualPayRecord $model_account_balance_virtual_pay_record
 * @property ModelAccountRmaManagement $model_account_rma_management
 * @property ModelCheckoutOrder $model_checkout_order
 * @property ModelCommonProduct $model_common_product
 */
class ModelCustomerpartnerRmaManagement extends Model
{

    private $date_format_arr = array('d/m/Y H', 'Y-m-d H', 'Y-m-d', 'Y-m-d H:i:s');

    public function updateRmaSellerStatus($status, $rma_id)
    {
        $result = $this->db->query("select ro.seller_status,rop.rma_type,rop.status_refund,rop.status_reshipment from oc_yzc_rma_order ro left join oc_yzc_rma_order_product rop on rop.rma_id = ro.id where ro.id =" . $rma_id)->row;
        $seller_status = $result['seller_status'];
        if ($status == 2) {
            if ($result['rma_type'] == 1 || $result['rma_type'] == 2) {
                if ($status != $seller_status) {
                    $this->db->query("update oc_yzc_rma_order set seller_status = " . $status . ",processed_date = now() where id =" . $rma_id);
                }
            } else {
                if ($result['status_refund'] != 0 && $result['status_reshipment'] != 0) {
                    $this->db->query("update oc_yzc_rma_order set seller_status = " . $status . ",processed_date = now() where id =" . $rma_id);
                }
            }
        } else {
            if ($status != $seller_status && $seller_status != 2) {
                $this->db->query("update oc_yzc_rma_order set seller_status = " . $status . " where id =" . $rma_id);
            }
        }
    }

    /**
     * @param array $data
     * @return Builder
     * user：wangjinxin
     * date：2019/10/19 10:43
     */
    public function resolveRmaQueryBuilder(array $data): Builder
    {
        $c_id = (int)$this->customer->getId();
        $co = new Collection($data);

        /** @var Builder $query */
        $query = $this->orm->table('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rop', ['ro.id' => 'rop.rma_id'])
            ->leftJoin('oc_product as op1', ['op1.product_id' => 'rop.product_id'])
            ->leftJoin('tb_sys_customer_sales_reorder as csr', ['ro.id' => 'csr.rma_id'])
            ->leftJoin('tb_sys_customer_sales_reorder_line as csrl', ['csr.id' => 'csrl.reorder_header_id'])
            ->leftJoin('oc_product as op', ['op.product_id' => 'csrl.product_id'])
            ->leftJoin('oc_yzc_rma_status as rs', ['rs.status_id' => 'ro.seller_status'])
            //优化sql 去掉多余
//            ->leftJoin(
//                'tb_underwriting_shop_product_mapping as spm',
//                ['spm.underwriting_product_id' => 'rop.product_id']
//            )
            ->leftJoin('oc_customer as oc', ['oc.customer_id' => 'ro.buyer_id'])
            ->leftJoin('oc_order as o',['ro.order_id'=>'o.order_id'])
            ->select([
                //添加云送仓类型
                'o.delivery_type','o.date_added as order_create_date',
                'ro.from_customer_order_id', 'rop.product_id as rop_product_id', 'csr.reorder_id',
                'rop.rma_type', 'rop.actual_refund_amount', 'ro.rma_order_id', 'ro.id', 'ro.seller_status','ro.cancel_rma',
                'ro.create_time', 'op1.sku as refundSku', 'op1.mpn as refundMpn', 'rop.status_refund',
                'rop.status_reshipment', 'rop.rma_type', 'ro.processed_date', 'rop.comments','rop.coupon_amount','rop.campaign_amount',
                // reshipment order info
                'csr.reorder_id as reshipment_id', 'csr.order_status as reshipment_order_status', 'ro.order_id',
                'rop.seller_reshipment_comments', 'rop.reason_id',
                // refund info
                'rop.seller_refund_comments', 'rop.apply_refund_amount', 'rop.actual_refund_amount',
                'rop.quantity as refund_quantity',
                // download info
                'oc.user_number as buyer_code', 'oc.nickname as buyer_name', 'oc.customer_group_id', 'oc.customer_id',
            ])
            ->selectRaw('CASE WHEN ro.order_type =1 THEN GROUP_CONCAT(op.product_id) ELSE rop.product_id END as product_id')
            ->selectRaw("CONCAT(oc.nickname,'(',oc.user_number,')') as  nickName")
            ->selectRaw('GROUP_CONCAT(op.sku) as sku')
            ->selectRaw('GROUP_CONCAT(op.mpn) as mpn')
            ->selectRaw('GROUP_CONCAT(op.product_id) as product_ids')
            ->selectRaw('GROUP_CONCAT(op.part_flag) as part_flag')
            ->selectRaw('case when ro.cancel_rma = 1 then 4 ELSE ro.`seller_status` END as name')
            ->selectRaw('group_concat(csrl.qty) as reshipmentQty')
            ->selectRaw('CASE rop.`status_refund` when 1 then "Agree" when 2 then "Refuse" ElSE "" END AS refund_result')
            ->selectRaw('CASE rop.`status_reshipment` when 1 then "Agree" when 2 then "Refuse" ElSE "" END AS reshipment_result')
            ->selectRaw(
                "CASE WHEN rop.rma_type != 2
                then CONCAT('Yes[Quantity:',sum(csrl.qty),']')
                ELSE 'No' END
                as apply_for_reshipment"
            )
            ->selectRaw(
                "CASE WHEN rop.rma_type != 1
                 then CONCAT('Yes[Refund:',rop.apply_refund_amount+rop.coupon_amount,']')
                 ELSE 'No' END
                 as apply_for_refund")
            ->where(['ro.seller_id' => $c_id])
            //优化sql
            ->where(function (Builder $q) use ($c_id) {
                $q->where(['ro.seller_id' => $c_id]);
//                    ->orWhere(['spm.original_seller_id' => $c_id]);
            })
            ->when(trim($co->get('filter_rma_id')), function (Builder $q) use ($co) {
                $co['filter_rma_id'] = trim($co['filter_rma_id']);
                $q->where('ro.rma_order_id', 'LIKE', "%{$co['filter_rma_id']}%");
            })
            ->when($co->get('filter_rmaDateFrom'), function (Builder $q) use ($co) {
                $f = $this->dateStr2dateTime($co['filter_rmaDateFrom'], $this->date_format_arr);
                $q->where('ro.create_time', '>=', $f->format('Y-m-d H:i:s'));
            })
            ->when($co->get('filter_rmaDateTo'), function (Builder $q) use ($co) {
                $f = $this->dateStr2dateTime($co['filter_rmaDateTo'], $this->date_format_arr);
                $q->where('ro.create_time', '<=', $f->format('Y-m-d H:i:s'));
            })
            ->when(trim($co->get('filter_mpn')), function (Builder $q) use ($co) {
                $co['filter_mpn'] = trim($co['filter_mpn']);
                $q->where('op1.mpn', 'LIKE', "%{$co['filter_mpn']}%");
            })
            ->when($co->get('filter_status'), function (Builder $q) use ($co) {
                $q->when(
                    $co['filter_status'] == 4,
                    function (Builder $q) {
                        $q->where(['ro.cancel_rma' => 1]);
                    },
                    function (Builder $q) use ($co) {
                        $q->where([
                            'ro.cancel_rma' => 0,
                            'ro.seller_status' => $co['filter_status'],
                        ]);
                    }
                );
            })
            ->when($co->get('filter_processedDateFrom'), function (Builder $q) use ($co) {
                $f = $this->dateStr2dateTime($co['filter_processedDateFrom'], $this->date_format_arr);
                $q->where('ro.processed_date', '>=', $f->format('Y-m-d H:i:s'));
            })
            ->when($co->get('filter_processedDateTo'), function (Builder $q) use ($co) {
                $f = $this->dateStr2dateTime($co['filter_processedDateTo'], $this->date_format_arr);
                $q->where('ro.processed_date', '<=', $f->format('Y-m-d H:i:s'));
            })
            ->when(trim($co->get('filter_item_name')), function (Builder $q) use ($co) {
                $co['filter_item_name'] = trim($co['filter_item_name']);
                $q->where('op1.sku', 'LIKE', "%{$co['filter_item_name']}%");
            })
            ->when(trim($co->get('filter_mpn_sku')), function (Builder $q) use ($co) {
                $q->where(function (Builder $q) use ($co) {
                    $co['filter_mpn_sku'] = trim($co['filter_mpn_sku']);
                    $q->orWhere('op1.mpn', 'LIKE', "%{$co['filter_mpn_sku']}%")
                        ->orWhere('op1.sku', 'LIKE', "%{$co['filter_mpn_sku']}%");
                });
            })
            ->when(trim($co->get('filter_nick_name')), function (Builder $q) use ($co) {
                $q->where(function (Builder $q) use ($co) {
                    $co['filter_nick_name'] = trim($co['filter_nick_name']);
                    $q->orWhere('oc.nickname', 'LIKE', "%{$co['filter_nick_name']}%")
                        ->orWhere('oc.user_number', 'LIKE', "%{$co['filter_nick_name']}%");
                });
            })
            ->when(
                $co->has('filter_apply_for_reshipment'),
                function (Builder $q) use ($co) {
                    if ($co['filter_apply_for_reshipment'] == 0) $q->where(['rop.rma_type' => 2]);
                    if ($co['filter_apply_for_reshipment'] == 1) $q->whereIn('rop.rma_type', [1, 3]);
                }
            )
            ->when(
                $co->has('filter_apply_for_refound'),
                function (Builder $q) use ($co) {
                    if ($co['filter_apply_for_refound'] == 0) $q->where(['rop.rma_type' => 1]);
                    if ($co['filter_apply_for_refound'] == 1) $q->whereIn('rop.rma_type', [2, 3]);
                }
            )
            // reshipment order
            ->when(trim($co->get('filter_order_id')), function (Builder $q) use ($co) {
                $co['filter_order_id'] = trim($co['filter_order_id']);
                $q->where('ro.order_id', 'LIKE', "%{$co['filter_order_id']}%");
            })
            ->when(trim($co->get('filter_reshipment_id')), function (Builder $q) use ($co) {
                $co['filter_reshipment_id'] = trim($co['filter_reshipment_id']);
                $q->where('csr.reorder_id', 'LIKE', "%{$co['filter_reshipment_id']}%");
            })
            ->when($co->get('filter_reshipment_status'), function (Builder $q) use ($co) {
                $q->where(['csr.order_status' => $co['filter_reshipment_status']]);
            })
            // refund application
            ->when($co->has('filter_refund_status'), function (Builder $q) use ($co) {
                // 只允许 0 1 2
                if (strlen($co['filter_refund_status']) === 1) {
                    $q->where(['rop.status_refund' => $co['filter_refund_status']]);
                }
            });
        $query = $query
            ->orderBy('ro.create_time', 'desc')
            ->orderBy('ro.processed_date', 'desc');
        return $query;
    }

    /**
     * @param array $data
     * @return array
     */
    public function getRmaList(array $data): array
    {
        $co = new Collection($data);
        $config = $this->config;
        $query = $this->resolveRmaQueryBuilder($co->all());
        $query = $query->groupBy(['ro.id']);
        if ($co->has($config->get('page')) && $co->has($config->get('per_page'))) {
            $query = $query->forPage(
                $co->get($config->get('page'), 1),
                $co->get($config->get('per_page'), 20)
            );
        }
        $res = $query->get()->map(function ($item) {
            return get_object_vars($item);
        });
        return $res->toArray();
    }

    /**
     * 生成器方式获取rma列表
     * @param array $data
     * @return Generator
     */
    public function getRmaListGenerator(array $data)
    {
        $co = new Collection($data);
        $config = $this->config;
        $query = $this->resolveRmaQueryBuilder($co->all());
        $query = $query->groupBy(['ro.id']);
        if ($co->has($config->get('page')) && $co->has($config->get('per_page'))) {
            $query = $query->forPage(
                $co->get($config->get('page'), 1),
                $co->get($config->get('per_page'), 20)
            );
        }
        return $query->cursor();
    }

    /**
     * RMA management 获取列表
     *
     * @param array $data
     * @return int
     */
    public function getRmaListCount(array $data): int
    {
        $co = new Collection($data);
        /** @var Config $config */
        $query = $this->resolveRmaQueryBuilder($co->all());
        $result = $query->selectRaw('count(DISTINCT ro.id) as count')->first();
        return $result ? $result->count : 0;
    }

    public function getRmas($data = array())
    {
        $sql = "select ro.from_customer_order_id,rop.product_id as rop_product_id,csr.reorder_id,rop.rma_type,rop.actual_refund_amount,CASE WHEN ro.order_type =1 THEN GROUP_CONCAT(op.product_id) ELSE  rop.product_id END as product_id,
                CONCAT(oc.nickname,'(',oc.user_number,')') as  nickName,oc.customer_group_id,ro.rma_order_id,ro.id,ro.create_time,op1.sku as refundSku,
                op1.mpn as refundMpn,GROUP_CONCAT(op.sku) as sku,GROUP_CONCAT(op.mpn) as mpn,GROUP_CONCAT(op.part_flag) as part_flag,
                rop.status_refund,rop.status_reshipment,case when ro.cancel_rma = 1 then 4 ELSE ro.`seller_status` END as name,
                rop.rma_type,group_concat(csrl.qty) as reshipmentQty,
                CASE WHEN rop.rma_type != 2 then CONCAT('Yes[Quantity:',sum(csrl.qty),']') ELSE 'No' END as apply_for_reshipment,
                CASE WHEN rop.rma_type != 1 then CONCAT('Yes[Refund:',rop.apply_refund_amount,']') ELSE 'No' END as apply_for_refund,ro.processed_date  from oc_yzc_rma_order ro ";
        $sql .= " LEFT JOIN oc_yzc_rma_order_product rop ON ro.id = rop.rma_id";
        $sql .= " LEFT JOIN oc_product op1 ON op1.product_id = rop.product_id";
        $sql .= " LEFT JOIN tb_sys_customer_sales_reorder csr ON ro.id = csr.rma_id";
        $sql .= " LEFT JOIN tb_sys_customer_sales_reorder_line csrl ON csr.id = csrl.reorder_header_id";
        $sql .= " LEFT JOIN oc_product op ON op.product_id = csrl.product_id";
        $sql .= " LEFT JOIN oc_yzc_rma_status rs ON rs.status_id = ro.seller_status";
        $sql .= " LEFT JOIN tb_underwriting_shop_product_mapping spm on spm.underwriting_product_id =rop.product_id";
        $sql .= " LEFT JOIN oc_customer oc on oc.customer_id = ro.buyer_id where (ro.seller_id =" . $this->customer->getId() . " or spm.original_seller_id =" . $this->customer->getId() . ")";

        if (!empty($data['filter_rma_id'])) {
            $sql .= " AND ro.rma_order_id LIKE '%" . $this->db->escape($data['filter_rma_id']) . "%'";
        }
        if (!empty($data['filter_rmaDateFrom'])) {
            $filter_rmaDateFrom = $this->dateStr2dateTime($data['filter_rmaDateFrom'], $this->date_format_arr);
            $sql .= " AND ro.create_time >='" . $filter_rmaDateFrom->format("Y-m-d H:i:s") . "'";
        }
        if (!empty($data['filter_rmaDateTo'])) {
            $filter_rmaDateTo = $this->dateStr2dateTime($data['filter_rmaDateTo'], $this->date_format_arr);
            $sql .= " AND ro.create_time <='" . $filter_rmaDateTo->format("Y-m-d H:i:s") . "'";
        }
        if (!empty($data['filter_mpn'])) {
            $sql .= " AND op1.mpn LIKE '%" . $this->db->escape($data['filter_mpn']) . "%'";
        }

        if (!empty($data['filter_status'])) {
            if ($data['filter_status'] == 4) {
                $sql .= " AND ro.cancel_rma=1";
            } else {
                $sql .= " AND ro.cancel_rma=0 AND ro.seller_status=" . $this->db->escape($data['filter_status']);
            }
        }

        if (!empty($data['filter_processedDateFrom'])) {
            $filter_processedDateFrom = $this->dateStr2dateTime($data['filter_processedDateFrom'], $this->date_format_arr);
            $sql .= " AND ro.processed_date >='" . $filter_processedDateFrom->format("Y-m-d H:i:s") . "'";
        }
        if (!empty($data['filter_processedDateTo'])) {
            $filter_processedDateTo = $this->dateStr2dateTime($data['filter_processedDateTo'], $this->date_format_arr);
            $sql .= " AND ro.processed_date <='" . $filter_processedDateTo->format("Y-m-d H:i:s") . "'";
        }

        if (!empty($data['filter_item_name'])) {
            $sql .= " AND op1.sku LIKE '%" . $this->db->escape($data['filter_item_name']) . "%'";
        }

        if (!empty($data['filter_nick_name'])) {
            $sql .= " AND (oc.nickname LIKE '%" . $this->db->escape($data['filter_nick_name']) . "%' OR oc.user_number LIKE '%" . $this->db->escape($data['filter_nick_name']) . "%')";
        }

        if (isset($data['filter_apply_for_reshipment']) && $data['filter_apply_for_reshipment'] != "") {
            if ($this->db->escape($data['filter_apply_for_reshipment']) == 0) {
                $sql .= " AND  rop.rma_type= 2 ";
            }
            if ($this->db->escape($data['filter_apply_for_reshipment']) == 1) {
                $sql .= " AND  rop.rma_type in (1,3) ";
            }
        }

        if (isset($data['filter_apply_for_refound']) && $data['filter_apply_for_refound'] != "") {
            if ($this->db->escape($data['filter_apply_for_refound']) == 0) {
                $sql .= " AND  rop.rma_type= 1 ";
            }
            if ($this->db->escape($data['filter_apply_for_refound']) == 1) {
                $sql .= " AND  rop.rma_type in (2,3) ";
            }
        }

        $sql .= " GROUP BY ro.id ORDER BY ro.create_time desc,ro.processed_date desc ";
        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $filter_data['start'] = 0;
            } else {
                $filter_data['start'] = $data['start'];
            }

            if ($data['limit'] < 1) {
                $filter_data['limit'] = 20;
            } else {
                $filter_data['limit'] = $data['limit'];
            }
            $sql .= " LIMIT " . (int)$filter_data['start'] . "," . (int)$filter_data['limit'];
        }
        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * 获取seller未处理的rma数量
     * @param int $seller_id | seller_id
     * @return int
     */
    public function getNoHandleRmaCount($seller_id)
    {
        static $c_arr = [];
        $seller_id = (int)$seller_id;
        if (!isset($c_arr[$seller_id])) {
            $query1 = $this->orm->table('oc_yzc_rma_order as ro')
                ->select('ro.id')
                ->leftJoin('oc_yzc_rma_order_product as rop', 'ro.id', '=', 'rop.rma_id')
                ->leftJoin(
                    'tb_underwriting_shop_product_mapping as spm',
                    'spm.underwriting_product_id', '=', 'rop.product_id'
                )
                ->Where('ro.seller_id', $seller_id)
                ->whereIn('ro.seller_status', [1, 3])
                ->where('ro.cancel_rma', 0);
            // 加入现货保证金的部分
            $query2 = $this->orm->table('oc_yzc_rma_order as ro')
                ->select('ro.id')
                ->leftJoin('oc_yzc_rma_order_product as rop', 'ro.id', '=', 'rop.rma_id')
                ->leftJoin(
                    'tb_underwriting_shop_product_mapping as spm',
                    'spm.underwriting_product_id', '=', 'rop.product_id'
                )
                ->where('spm.original_seller_id', $seller_id)
                ->whereIn('ro.seller_status', [1, 3])
                ->where('ro.cancel_rma', 0)
                // hard code 保证金店铺id
                ->whereIn('ro.seller_id', [694, 696, 746, 907, 908]);
            $u = $query1->union($query2);
            $c_arr[$seller_id] = $this->orm
                ->table(new Expression('(' . get_complete_sql($u) . ') as u'))
                ->count('u.id');
        }

        return $c_arr[$seller_id];
    }

    public function getTotalRma($data = array())
    {
        $sql = "select count(1) as total from oc_yzc_rma_order ro ";
        $sql .= " LEFT JOIN oc_yzc_rma_order_product rop ON ro.id = rop.rma_id";
        $sql .= " LEFT JOIN oc_product op ON op.product_id = rop.product_id";
        $sql .= " LEFT JOIN tb_sys_customer_sales_reorder csr ON ro.id = csr.rma_id";
        $sql .= " LEFT JOIN oc_yzc_rma_status rs ON rs.status_id = ro.seller_status";
        $sql .= " LEFT JOIN tb_underwriting_shop_product_mapping spm on spm.underwriting_product_id =rop.product_id";
        $sql .= " LEFT JOIN oc_customer oc on oc.customer_id = ro.buyer_id where (ro.seller_id =" . $this->customer->getId() . " or spm.original_seller_id =" . $this->customer->getId() . ")";
        if (!empty($data['filter_rma_id'])) {
            $sql .= " AND ro.rma_order_id LIKE '%" . $this->db->escape($data['filter_rma_id']) . "%'";
        }
        if (!empty($data['filter_rmaDateFrom'])) {
            $filter_rmaDateFrom = $this->dateStr2dateTime($data['filter_rmaDateFrom'], $this->date_format_arr);
            $sql .= " AND ro.create_time >='" . $filter_rmaDateFrom->format("Y-m-d H:i:s") . "'";
        }
        if (!empty($data['filter_rmaDateTo'])) {
            $filter_rmaDateTo = $this->dateStr2dateTime($data['filter_rmaDateTo'], $this->date_format_arr);
            $sql .= " AND ro.create_time <='" . $filter_rmaDateTo->format("Y-m-d H:i:s") . "'";
        }
        if (!empty($data['filter_mpn'])) {
            $sql .= " AND op.mpn LIKE '%" . $this->db->escape($data['filter_mpn']) . "%'";
        }

        if (!empty($data['filter_status'])) {
            if ($data['filter_status'] == 4) {
                $sql .= " AND ro.cancel_rma=1";
            } else {
                $sql .= " AND ro.cancel_rma=0 AND ro.seller_status=" . $this->db->escape($data['filter_status']);
            }
        }

        if (!empty($data['filter_processedDateFrom'])) {
            $filter_processedDateFrom = $this->dateStr2dateTime($data['filter_processedDateFrom'], $this->date_format_arr);
            $sql .= " AND ro.processed_date >='" . $filter_processedDateFrom->format("Y-m-d H:i:s") . "'";
        }
        if (!empty($data['filter_processedDateTo'])) {
            $filter_processedDateTo = $this->dateStr2dateTime($data['filter_processedDateTo'], $this->date_format_arr);
            $sql .= " AND ro.processed_date <='" . $filter_processedDateTo->format("Y-m-d H:i:s") . "'";
        }

        if (!empty($data['filter_item_name'])) {
            $sql .= " AND op.sku LIKE '%" . $this->db->escape($data['filter_item_name']) . "%'";
        }

        if (!empty($data['filter_nick_name'])) {
            $sql .= " AND CONCAT(oc.firstname,oc.lastname)  LIKE '%" . $this->db->escape($data['filter_nick_name']) . "%'";
        }

        if (isset($data['filter_apply_for_reshipment']) && $data['filter_apply_for_reshipment'] != "") {
            if ($this->db->escape($data['filter_apply_for_reshipment']) == 0) {
                $sql .= " AND  rop.rma_type= 2 ";
            }
            if ($this->db->escape($data['filter_apply_for_reshipment']) == 1) {
                $sql .= " AND  rop.rma_type in (1,3) ";
            }
        }

        if (isset($data['filter_apply_for_refound']) && $data['filter_apply_for_refound'] != "") {
            if ($this->db->escape($data['filter_apply_for_refound']) == 0) {
                $sql .= " AND  rop.rma_type= 1 ";
            }
            if ($this->db->escape($data['filter_apply_for_refound']) == 1) {
                $sql .= " AND  rop.rma_type in (2,3) ";
            }
        }

        $sql .= " order by ro.id";
        $query = $this->db->query($sql);

        return $query->row['total'];
    }


    public function getOrderInfo($rma_id)
    {
        $sql = " select o.order_id,o.date_added,o.payment_method,o.delivery_type,rop.product_id from oc_yzc_rma_order ro left join oc_yzc_rma_order_product rop on rop.rma_id = ro.id left join oc_order o on ro.order_id = o.order_id where ro.id = " . $rma_id;
        $query = $this->db->query($sql);
        return $query->row;
    }

    public function getRmaInfoByRmaId($rma_id)
    {
        $sql = "SELECT cso.id as sales_order_id, csr.order_status as re_order_status, o.delivery_type,ro.buyer_id,ro.from_customer_order_id,rop.product_id,ro.id,ro.rma_order_id,op.sku,op.mpn,ro.order_id,rop.quantity,rr.reason,rop.rma_type,cso.orders_from,rop.asin,ro.order_type,cso.order_status,rop.coupon_amount,rop.campaign_amount FROM oc_yzc_rma_order ro ";
        $sql .= "LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = ro.id ";
        $sql .= "LEFT JOIN tb_sys_customer_sales_order cso ON ro.from_customer_order_id = cso.order_id and cso.buyer_id = ro.buyer_id  ";
        $sql .= "LEFT JOIN oc_product op  on op.product_id = rop.product_id ";
        $sql .= "left join oc_order as o on o.order_id=ro.order_id ";
        $sql .= 'left join tb_sys_customer_sales_reorder as csr on ro.id = csr.rma_id ';
        $sql .= "LEFT JOIN oc_yzc_rma_reason rr on rr.reason_id = rop.reason_id and rr.status = 1  WHERE ro.id =" . $rma_id;
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getRmaComments($rma_id, $type)
    {
        $sql = "SELECT rop.comments FROM oc_yzc_rma_order_product rop  WHERE rop.rma_id = " . $rma_id;
        $query = $this->db->query($sql);
        return $query->row;
    }

    public function getRmaAttchments($rma_id, $type)
    {
        $sql = "select rf.file_path from oc_yzc_rma_file rf where rf.type = " . $type . " and rf.rma_id = " . $rma_id;
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getRmaReshipments($rma_id)
    {
        $sql = "SELECT csr.reorder_id,csrl.item_code,csrl.qty,rop.status_reshipment,rop.seller_reshipment_comments,op.mpn,csr.order_status,csrl.part_flag FROM tb_sys_customer_sales_reorder csr LEFT JOIN tb_sys_customer_sales_reorder_line csrl ON csrl.reorder_header_id = csr.id LEFT JOIN oc_yzc_rma_order_product rop on rop.rma_id = csr.rma_id  LEFT JOIN oc_product op on op.product_id=csrl.product_id WHERE csr.rma_id = " . $rma_id;
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getRmaRefound($rma_id)
    {
        $sql = "select apply_refund_amount,status_refund,seller_refund_comments,actual_refund_amount,refund_type from oc_yzc_rma_order_product where rma_id =" . $rma_id;
        $query = $this->db->query($sql);
        return $query->row;
    }

    public function rejectReshipment($rma_id, $rejectComments)
    {
        $sql = "UPDATE oc_yzc_rma_order_product SET seller_reshipment_comments = '" . $this->db->escape($rejectComments) . "',status_reshipment = 2   where rma_id =" . $rma_id;
        $this->db->query($sql);
        $this->db->query("UPDATE tb_sys_customer_sales_reorder csr LEFT JOIN tb_sys_customer_sales_reorder_line csrl ON csr.id = csrl.reorder_header_id set csr.order_status = 16,csrl.item_status = 8 where csr.rma_id =" . $rma_id);
    }

    public function rejectRefund($rma_id, $rejectComments)
    {
        $sql = "UPDATE oc_yzc_rma_order_product SET seller_refund_comments = '" . $this->db->escape($rejectComments) . "',status_refund = 2   where rma_id =" . $rma_id;
        $query = $this->db->query($sql);
        return $query->row;
    }

    public function getRmaInfo($rma_id)
    {
        $sql = "SELECT rop.product_id as apply_product_id,op.sku,ro.order_id,ro.buyer_id,ro.seller_id,rop.id as rma_product_id,csrl.product_id,csrl.qty as quantity,csrl.id FROM oc_yzc_rma_order ro
                LEFT JOIN  oc_yzc_rma_order_product rop on rop.rma_id = ro.id
                LEFT JOIN oc_product op on op.product_id=rop.product_id
                LEFT JOIN tb_sys_customer_sales_reorder csr on csr.rma_id = ro.id
                LEFT JOIN tb_sys_customer_sales_reorder_line csrl on csrl.reorder_header_id = csr.id WHERE ro.id=" . $rma_id;
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function updateReshipmentInfo($rmaId, $comments)
    {
        $sql = "UPDATE oc_yzc_rma_order_product set status_reshipment = 1,seller_reshipment_comments = '" . $this->db->escape($comments) . "' WHERE rma_id = " . $rmaId;
        $this->db->query($sql);
    }

    public function agreeRefund($rmaId, $agreeComments, $refundMoney, $refundType)
    {
        $sql = "UPDATE oc_yzc_rma_order_product SET seller_refund_comments = '" . $this->db->escape($agreeComments) . "',status_refund = 1,refund_type=" . $this->db->escape($refundType) . ",actual_refund_amount=" . $this->db->escape($refundMoney) . ",update_time = now(),update_user_name=" . $this->customer->getId() . "   where rma_id =" . $rmaId;
        $query = $this->db->query($sql);
        return $query->row;
    }

    public function getRmaBuyerLineOfCredit($rmaId)
    {
        $sql = "select ifnull(oc.line_of_credit,0) as line_of_credit ,ro.buyer_id,ro.seller_id from oc_yzc_rma_order ro LEFT JOIN oc_customer oc on oc.customer_id = ro.buyer_id where ro.id =" . $rmaId;
        $query = $this->db->query($sql);
        return $query->row;
    }

    public function updateReorderLine($reorder_line_id, $qty, $product_id, $rma_id)
    {
        //1.退返品订单变bp,
        $this->db->query("update tb_sys_customer_sales_reorder set order_status = 2 where rma_id=" . $rma_id);
        //2.combo_info 填写
        $combo_flag = $this->db->query("select combo_flag from oc_product op where op.product_id =" . $product_id)->row['combo_flag'];
        if ($combo_flag == 1) {
            $comboJson[0] = array();
            $comboInfos = $this->db->query("select op.sku as set_item_code ,op1.sku as item_code,psi.qty from tb_sys_product_set_info psi LEFT JOIN oc_product op on op.mpn = psi.set_mpn LEFT JOIN oc_product op1 on op1.product_id = psi.product_id LEFT JOIN  oc_customerpartner_to_product ctp on ctp.product_id= op.product_id where ctp.customer_id =" . $this->customer->getId() . " and psi.product_id =" . $product_id)->rows;
            $flag = true;
            foreach ($comboInfos as $key1 => $comboInfo) {
                if ($flag) {
                    $comboJson[0][$comboInfo['item_code']] = $qty;
                    $flag = !$flag;
                }
                $comboJson[0][$comboInfo['set_item_code']] = $comboInfo['qty'];
            }
            if (count($comboJson) > 0) {
                $this->db->query("update tb_sys_customer_sales_reorder_line set combo_info = '" . json_encode($comboJson) . "' where id =" . $reorder_line_id);
            }
        }
    }

    public function checkMpn($data = array())
    {
        $sql = "SELECT op.product_id,op.sku,op.mpn,op.combo_flag FROM `oc_product` op LEFT JOIN oc_customerpartner_to_product ctp ON ctp.product_id = op.product_id";
        $sql .= " WHERE 1 = 1 AND op.status = 1 AND op.product_type = 0 ";
        $implode = array();

        if (!empty($data['filter_reshipmentMpn'])) {
            $implode[] = "op.mpn LIKE '%" . $this->db->escape($data['filter_reshipmentMpn']) . "%'";
            $implode[] = "ctp.customer_id = " . $this->db->escape($data['customer_id']);
        }

        if (!empty($data['filter_reshipmentType'])) {
            $implode[] = "op.part_flag =" . $this->db->escape($data['filter_reshipmentType'] - 1);
        }

        if ($implode) {
            $sql .= " AND " . implode(" AND ", $implode);
        }
        if ($data['filter_reshipmentType'] == 1) {
            $sql .= " AND op.buyer_flag = 1 ";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getOrderProductPrice($rmaId)
    {
        $sql = "SELECT
                            CASE WHEN opq.price IS NULL THEN
                            (oop.price+oop.service_fee_per+oop.freight_per+oop.package_fee)*soa.qty
                            ELSE
                            (oop.price+oop.service_fee_per-opq.amount_price_per-opq.amount_service_fee_per+oop.freight_per+oop.package_fee)*soa.qty
                            END AS total,
                        opq.price,
                        opq.discount_price
                    FROM
                        oc_yzc_rma_order ro
                    LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = ro.id
                    LEFT JOIN oc_order_product oop ON oop.order_id = ro.order_id
                    AND oop.product_id = rop.product_id
                    LEFT JOIN oc_product_quote opq on (opq.order_id=ro.order_id and opq.product_id = rop.product_id)
                    LEFT JOIN tb_sys_customer_sales_order cso on cso.order_id = ro.from_customer_order_id and cso.buyer_id = ro.buyer_id
                    LEFT JOIN tb_sys_order_associated soa on soa.sales_order_id = cso.id and soa.product_id = rop.product_id and soa.order_id = ro.order_id
                    WHERE ro.id = " . $rmaId;
        $query = $this->db->query($sql);
        return $query->row['total'];
    }

    public function dateStr2dateTime($time_str, $format_arr = array())
    {
        $date = false;
        if ($time_str && $format_arr) {
            foreach ($format_arr as $fm) {
                $date = date_create_from_format($fm, $time_str);
                if ($date) break;
            }
        }
        return $date;
    }

    public function getSellerOrderProductInfo($ramId)
    {
        $sql = " SELECT ro.from_customer_order_id, op.*,ifnull(opq.amount_price_per,0) as amount_price_per,ifnull(opq.amount_service_fee_per,0) as amount_service_fee_per,
                round(op.price+op.service_fee_per+op.freight_per+op.package_fee,2)*op.quantity c2oprice,round(op.price,2) opprice,op.freight_difference_per,
                c2o.shipping_applied,c2o.paid_status,c2o.order_product_status,op.coupon_amount as all_coupon_amount,op.campaign_amount as all_campaign_amount FROM " . DB_PREFIX . "yzc_rma_order ro ";
        $sql .= " LEFT JOIN oc_yzc_rma_order_product rop ON (rop.rma_id = ro.id) ";
        $sql .= " LEFT JOIN oc_customerpartner_to_order c2o ON (ro.order_id = c2o.order_id AND rop.product_id = c2o.product_id) ";
        $sql .= " LEFT JOIN oc_order_product op ON ( c2o.order_product_id = op.order_product_id AND c2o.order_id = op.order_id) ";
        $sql .= " LEFT JOIN oc_product_quote opq ON ( opq.order_id=op.order_id and opq.product_id = op.product_id) ";
        $sql .= " WHERE ro.id =" . $ramId . " ORDER BY op.product_id ";
        $result = $this->db->query($sql);
        return ($result->rows);
    }

    public function getSellerOrderProductInfoIncludeSaleLine($ramId)
    {
        $sql = " SELECT ro.buyer_id,ro.from_customer_order_id, op.*,ifnull(opq.amount_price_per,0) as amount_price_per,ifnull(opq.amount_service_fee_per,0) as amount_service_fee_per,
                round(op.price+op.service_fee_per+op.freight_per+op.package_fee,2)*op.quantity c2oprice,round(op.price,2) opprice,
                c2o.shipping_applied,c2o.paid_status,c2o.order_product_status,op.coupon_amount as all_coupon_amount,op.campaign_amount as all_campaign_amount FROM " . DB_PREFIX . "yzc_rma_order ro ";
        $sql .= " LEFT JOIN oc_yzc_rma_order_product rop ON (rop.rma_id = ro.id) ";
        $sql .= " LEFT JOIN oc_customerpartner_to_order c2o ON (ro.order_id = c2o.order_id AND rop.product_id = c2o.product_id) ";
        $sql .= " LEFT JOIN oc_order_product op ON ( c2o.order_product_id = op.order_product_id AND c2o.order_id = op.order_id) ";
        $sql .= " LEFT JOIN oc_product_quote opq ON ( opq.order_id=op.order_id and opq.product_id = op.product_id) ";
        $sql .= " WHERE ro.id =" . $ramId . " ORDER BY op.product_id ";
        $result = $this->db->query($sql);
        $res = $result->rows;
        array_walk($res, function (&$item) {
            if (empty($item['from_customer_order_id'])) return;
            $customer_sales_order_id = $this->orm
                ->table('tb_sys_customer_sales_order')
                ->where([
                    'order_id' => $item['from_customer_order_id'],
                    'buyer_id' => $item['buyer_id'],
                ])
                ->value('id');
            $actual_line_info = $this->orm
                ->table('tb_sys_order_associated')
                ->where([
                    'sales_order_id' => $customer_sales_order_id,
                    'order_id' => $item['order_id'],
                    'order_product_id' => $item['order_product_id'],
                ])
                ->first();
            $item['quantity'] = $actual_line_info->qty;
            $item['all_campaign_amount'] = $actual_line_info->campaign_amount;
            $item['all_coupon_amount'] = $actual_line_info->coupon_amount;
            $item['c2oprice'] = round(
                    ($item['price'] + $item['service_fee_per'] + $item['freight_per'] + $item['package_fee']),
                    2
                ) * ($item['qty'] ?? 0);
        });

        return $res;
    }

    public function getOrderTotalPrice($order_id, $product_id)
    {
        $sql = "SELECT
                    CASE WHEN opq.price is null THEN
                    SUM(
                        round(op.price, 2) * op.quantity
                    ) + op.service_fee
                    ELSE
                 SUM(round(opq.price,2) * op.quantity )
                    END total,
                    SUM(cto.shipping_applied) shipping_applied,
                    cto.shipping,
                    op.total AS sub_total,
                    op.service_fee,
                    op.poundage,
                    opq.discount_price AS discount_price,
                    opq.price AS quotePrice,
                    opq.amount_price_per*op.quantity as amount_price,
                    opq.amount_service_fee_per*op.quantity as amount_service_fee,
                    (op.freight_per+op.package_fee)*op.quantity as freight
                FROM
                    oc_customerpartner_to_order cto
                LEFT JOIN oc_order_product op ON (
                    cto.order_id = op.order_id
                    AND cto.product_id = op.product_id
                )
                LEFT JOIN oc_product_quote opq ON (
                    opq.order_id = op.order_id
                    AND opq.product_id = op.product_id
                )
          WHERE cto.product_id =" . $product_id . " and cto.order_id = '" . (int)$order_id . "'";
        return $this->db->query($sql)->rows;
    }

    /**
     * 获取退反品原始销售订单的信息
     */
    public function getRmaFromOrderInfo($rmaId)
    {
        $sql = "SELECT
                    cso.order_status,
                    soa.qty,
                    ro.order_id,
                    rop.product_id,
                    ro.from_customer_order_id,
                    ro.seller_id,
                    ro.buyer_id
                FROM
                    oc_yzc_rma_order ro
                LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = ro.id
                LEFT JOIN tb_sys_customer_sales_order cso on cso.order_id = ro.from_customer_order_id and cso.buyer_id = ro.buyer_id
                 LEFT JOIN tb_sys_order_associated soa on soa.sales_order_id = cso.id and soa.product_id = rop.product_id and soa.order_id = ro.order_id
                WHERE
                    ro.id =" . $rmaId;
        return $this->db->query($sql)->row;
    }

    public function checkOrderRmaIsRefund($rmaId, $orderId)
    {
        //buyer库存出库
        $sql = "select cd.id,c.customer_group_id,roi.from_customer_order_id,roi.buyer_id,roi.product_id,ctc.accounting_type,c.customer_id,rop.id as rma_product_id
                from tb_sys_cost_detail cd
                LEFT JOIN oc_customer c on c.customer_id = cd.seller_id
                LEFT JOIN  oc_customerpartner_to_customer ctc ON ctc.customer_id=c.customer_id
                LEFT JOIN tb_sys_receive_line srl on srl.id=cd.source_line_id
                LEFT JOIN vw_rma_order_info roi on roi.b2b_order_id = srl.oc_order_id and roi.product_id = srl.product_id
                LEFT JOIN oc_yzc_rma_order_product rop on rop.rma_id=roi.rma_id
                where roi.rma_id = " . $rmaId;
        $result = $this->db->query($sql)->row;
        //判断该销售订单是否之前做过rma
        $checkSql = "select count(1) as total from oc_yzc_rma_order ro
                        LEFT JOIN oc_yzc_rma_order_product rop on rop.rma_id=ro.id
                        WHERE ro.buyer_id=" . $result['buyer_id'] . " AND ro.from_customer_order_id='" . $result['from_customer_order_id'] . "'
                        AND rop.status_refund = 1 AND ro.seller_status = 2
                        AND ro.order_id =" . $orderId . "
                        AND rop.product_id=" . $result['product_id'] . " AND rop.rma_id !=" . $rmaId;
        $countResult = $this->db->query($checkSql)->row;

        return (bool)($countResult['total'] != 0);
    }

    /**
     * cancel的销售订单的退反品
     * @param $rmaId
     */
    public function cancelOrderRma($rmaId, $qty, $orderId)
    {
        //buyer库存出库
        $sql = "select cd.id,c.customer_group_id,roi.from_customer_order_id,roi.buyer_id,roi.product_id,ctc.accounting_type,c.customer_id,rop.id as rma_product_id
                from tb_sys_cost_detail cd
                LEFT JOIN oc_customer c on c.customer_id = cd.seller_id
                LEFT JOIN  oc_customerpartner_to_customer ctc ON ctc.customer_id=c.customer_id
                LEFT JOIN tb_sys_receive_line srl on srl.id=cd.source_line_id
                LEFT JOIN vw_rma_order_info roi on roi.b2b_order_id = srl.oc_order_id and roi.product_id = srl.product_id
                LEFT JOIN oc_yzc_rma_order_product rop on rop.rma_id=roi.rma_id
                where roi.rma_id = " . $rmaId;
        $result = $this->db->query($sql)->row;
        //判断该销售订单是否之前做过rma
        $checkSql = "select count(1) as total from oc_yzc_rma_order ro
                        LEFT JOIN oc_yzc_rma_order_product rop on rop.rma_id=ro.id
                        WHERE ro.buyer_id=" . $result['buyer_id'] . " AND ro.from_customer_order_id='" . $result['from_customer_order_id'] . "'
                        AND rop.status_refund = 1 AND ro.seller_status = 2
                        AND ro.order_id =" . $orderId . "
                        AND rop.product_id=" . $result['product_id'] . " AND rop.rma_id !=" . $rmaId;
        $countResult = $this->db->query($checkSql)->row;
        if ($countResult['total'] == 0) {
            if (isset($result['id'])) {
                // 检测此时的buyer库存能否满足出库的数量要求  防止出现负数  add by kimi
                $costDetail = db('tb_sys_cost_detail')->where('id', $result['id'])->first();
                if (!$costDetail) {
                    throw new Exception('Error! Can not find buyer inventory info.');
                }
                if ((int)$costDetail->onhand_qty < $qty) {
                    throw new Exception('Buyer\'s inventory of the product is insufficient for RMA return. Please contact customer service team to check.');
                }
                $this->db->query("update tb_sys_cost_detail set onhand_qty = onhand_qty-" . $qty . " where id=" . $result['id']);
                //buyer新增出库记录
                $sqlDeliveryInfo = "select op.combo_flag,soa.sales_order_id,soa.sales_order_line_id,soa.order_id,soa.order_product_id,soa.product_id,soa.seller_id
                FROM
                    tb_sys_order_associated soa
                LEFT JOIN oc_product op ON op.product_id=soa.product_id
                LEFT JOIN tb_sys_customer_sales_order cso ON cso.id = soa.sales_order_id
                LEFT JOIN vw_rma_order_info vroi ON vroi.from_customer_order_id = cso.order_id
                AND cso.buyer_id = vroi.buyer_id
                AND soa.order_id = vroi.b2b_order_id
                AND soa.product_id = vroi.product_id
                where vroi.rma_id =" . $rmaId;
                $resultInfo = $this->db->query($sqlDeliveryInfo)->row;
                $sqlDelivery = "insert into tb_sys_delivery_line (SalesHeaderId,SalesLineId,TrackingId,ProductId,DeliveryType,DeliveryQty,CostId,type,create_user_name,create_time,ProgramCode) VALUES (" . $resultInfo['sales_order_id'] . "," . $resultInfo['sales_order_line_id'] . ",0," . $resultInfo['product_id'] . ",1," . $qty . "," . $result['id'] . ",2,1,now(),'V1.0')";
                $this->db->query($sqlDelivery);
                //大建云seller新增入库数据 或者(保证金的包销店铺)
                if ($result['accounting_type'] == 2 || in_array($result['customer_id'], $this->config->get('config_customer_group_ignore_check'))) {
                    if ($resultInfo['combo_flag'] == 1) {
                        $batchInfoSql =
                            <<<SQL
                                     SELECT
                                        tsb.*,soc.qty,soc.set_product_id
                                    FROM
                                        tb_sys_order_combo soc
                                    LEFT JOIN (
                                    select * from tb_sys_seller_delivery_line sdl
                                    where qty>0
                                      and order_id = {$resultInfo['order_id']}
                                      and order_product_id = {$resultInfo['order_product_id']}
                                    GROUP BY order_product_id,product_id
                                    ) t ON t.order_product_id = soc.order_product_id and t.product_id=soc.set_product_id and t.qty>0
                                    LEFT JOIN tb_sys_batch tsb on tsb.batch_id=t.batch_id
                                    WHERE
                                        soc.order_product_id = {$resultInfo['order_product_id']}
                                        and soc.order_id = {$resultInfo['order_id']}
SQL;
                        $batchInfos = $this->db->query($batchInfoSql)->rows;
                        foreach ($batchInfos as $batchInfo) {
                            if ($batchInfo['batch_number'] != null) {
                                $batch = "insert into tb_sys_batch (batch_number,receipts_order_id,receipts_order_line_id,source_code,sku,mpn,product_id,original_qty,onhand_qty,warehouse,remark,customer_id,receive_date,source_batch_id,create_user_name,create_time,program_code,rma_id,unit_price,transaction_type) VALUES (";
                                $batch .= isset($batchInfo['batch_number']) ? "'" . $batchInfo['batch_number'] . "'" : "null";
                                $batch .= ",";
                                $batch .= isset($batchInfo['receipts_order_id']) ? $batchInfo['receipts_order_id'] : "null";
                                $batch .= ",";
                                $batch .= isset($batchInfo['receipts_order_line_id']) ? $batchInfo['receipts_order_line_id'] : "null";
                                $batch .= ",";
                                $batch .= "'退货收货','" . $batchInfo['sku'] . "','" . $batchInfo['mpn'] . "',"
                                    . $batchInfo['product_id'] . "," . $qty * $batchInfo['qty'] . ","
                                    . $qty * $batchInfo['qty'] . ",'" . $batchInfo['warehouse'] . "','退货销售订单id:"
                                    . $resultInfo['sales_order_id'] . "'," . $batchInfo['customer_id'] . ",now(),"
                                    . $batchInfo['source_batch_id'] . ",'1',now(),'V1.0'," . $rmaId
                                    .','.($batchInfo['unit_price'] ?? 0). "," . BatchTransactionType::RMA_RETURN
                                    . ")";
                            } else {
                                $noSellerDeliverySql = " select op.sku,op.mpn,op.product_id,ctp.customer_id
                                                      from  oc_product op
                                                      LEFT JOIN oc_customerpartner_to_product ctp on ctp.product_id=op.product_id
                                                      where op.product_id=" . $batchInfo['set_product_id'];
                                $noSellerDelivery = $this->db->query($noSellerDeliverySql)->row;
                                $batch = "insert into tb_sys_batch (batch_number,receipts_order_id,receipts_order_line_id,source_code,
                            sku,mpn,product_id,original_qty,onhand_qty,warehouse,remark,customer_id,receive_date,source_batch_id,create_user_name,create_time,program_code,rma_id,unit_price,transaction_type) VALUES (";
                                $batch .= "'超卖退库存',null,null,'退货收货','" . $noSellerDelivery['sku']
                                    . "','" . $noSellerDelivery['mpn'] . "'," . $noSellerDelivery['product_id']
                                    . "," . $qty . "," . $qty . ",null,'退货销售订单id:" . $resultInfo['sales_order_id']
                                    . "'," . $noSellerDelivery['customer_id'] . ",now(),-1,'1',now(),'V1.0'," . $rmaId
                                    .','.($batchInfo['unit_price'] ?? 0). "," . BatchTransactionType::RMA_RETURN
                                    . ")";
                            }
                            $this->db->query($batch);
                            if (!empty($batchInfo['customer_id'])) {
                                app(SellerAssetService::class)->addCollateralValueByOrder($batchInfo['customer_id'], ($batchInfo['unit_price'] ?? 0) * $qty);
                            }
                        }
                    } else {
                        $batchInfoSql = <<<SQL
                     select tsb.*,sdl.qty
                     from tb_sys_seller_delivery_line sdl
                     LEFT JOIN tb_sys_batch tsb on sdl.batch_id = tsb.batch_id
                     where sdl.qty>0
                     and sdl.order_id = {$resultInfo['order_id']}
                     and sdl.order_product_id = {$resultInfo['order_product_id']}
SQL;
                        $batchInfo = $this->db->query($batchInfoSql)->row;
                        if (count($batchInfo) > 0) {
                            $batch = "insert into tb_sys_batch (batch_number,receipts_order_id,receipts_order_line_id,source_code,
                            sku,mpn,product_id,original_qty,onhand_qty,warehouse,remark,customer_id,receive_date,source_batch_id,create_user_name,create_time,program_code,rma_id,unit_price,transaction_type) VALUES (";
                            $batch .= isset($batchInfo['batch_number']) ? "'" . $batchInfo['batch_number'] . "'" : "null";
                            $batch .= ",";
                            $batch .= isset($batchInfo['receipts_order_id']) ? $batchInfo['receipts_order_id'] : "null";
                            $batch .= ",";
                            $batch .= isset($batchInfo['receipts_order_line_id']) ? $batchInfo['receipts_order_line_id'] : "null";
                            $batch .= ",";
                            $batch .= "'退货收货','" . $batchInfo['sku'] . "','" . $batchInfo['mpn'] . "',"
                                . $batchInfo['product_id'] . "," . $qty . "," . $qty . ",'"
                                . $batchInfo['warehouse'] . "','退货销售订单id:" . $resultInfo['sales_order_id']
                                . "'," . $batchInfo['customer_id'] . ",now()," . $batchInfo['source_batch_id']
                                . ",'1',now(),'V1.0'," . $rmaId
                                .','.($batchInfo['unit_price'] ?? 0). "," . BatchTransactionType::RMA_RETURN
                                . ")";
                        } else {
                            $noSellerDeliverySql = " select op.sku,op.mpn,op.product_id,ctp.customer_id from oc_order_product oop
                                                      LEFT JOIN oc_product op on op.product_id=oop.product_id
                                                      LEFT JOIN oc_customerpartner_to_product ctp on ctp.product_id=op.product_id
                                                      where oop.order_product_id=" . $resultInfo['order_product_id'];
                            $noSellerDelivery = $this->db->query($noSellerDeliverySql)->row;
                            $batch = "insert into tb_sys_batch (batch_number,receipts_order_id,receipts_order_line_id,source_code,
                            sku,mpn,product_id,original_qty,onhand_qty,warehouse,remark,customer_id,receive_date,source_batch_id,
                            create_user_name,create_time,program_code,rma_id,unit_price,transaction_type) VALUES (";
                            $batch .= "'超卖退库存',null,null,'退货收货','" . $noSellerDelivery['sku']
                                . "','" . $noSellerDelivery['mpn'] . "'," . $noSellerDelivery['product_id']
                                . "," . $qty . "," . $qty . ",null,'退货销售订单id:" . $resultInfo['sales_order_id']
                                . "'," . $noSellerDelivery['customer_id'] . ",now(),-1,'1',now(),'V1.0'," . $rmaId
                                .','.($batchInfo['unit_price'] ?? 0). "," . BatchTransactionType::RMA_RETURN
                                . ")";
                        }
                        $this->db->query($batch);
                        if (!empty($batchInfo['customer_id'])) {
                            app(SellerAssetService::class)->addCollateralValueByOrder($batchInfo['customer_id'], ($batchInfo['unit_price'] ?? 0) * $qty);
                        }
                    }
                }
                //判断是否需要从返点协议中剔除
                $rebateInfo = $this->getRebateInfo($orderId, $result['product_id']);
                //正在进行中的返点协议
                if (in_array($rebateInfo['rebate_result'] ?? 0, [1, 2])) {
                    //扣减参与返点的数量
                    $insertSql = "insert into oc_rebate_agreement_order (
                                  agreement_id,
                                  item_id,
                                  product_id,
                                  qty,
                                  order_id,
                                  order_product_id,
                                  rma_id,
                                  rma_product_id,
                                  type,
                                  create_time,
                                  update_time,
                                  program_code
                                  )values(
                                  " . $rebateInfo['agreement_id'] . ",
                                  " . $rebateInfo['item_id'] . ",
                                  " . $rebateInfo['product_id'] . ",
                                  " . $qty . ",
                                  " . $rebateInfo['order_id'] . ",
                                  " . $rebateInfo['order_product_id'] . ",
                                  " . $rmaId . ",
                                  " . $result['rma_product_id'] . ",
                                  2,
                                  now(),
                                  now(),
                                  'V1.0'
                                  )";
                    $this->db->query($insertSql);
                }
            } else {
                $this->log->write($rmaId . ",没有采购订单");
            }
        }
    }

    public function getCommunicationInfo($rmaId)
    {
        $sql = "SELECT
                    rop.rma_type,
                    rop.rma_id,
                    ro.order_id,
                    ro.rma_order_id,
                    op.sku,
                    op.mpn,
                    ro.seller_id,
                    ro.buyer_id,
                    ro.seller_status,
                  	CASE WHEN rop.status_refund = 2 THEN 'Refuse'
                    WHEN rop.status_refund = 1 THEN 'Agree'
                    ELSE 0
                    END as status_refund,
                    CASE WHEN rop.status_reshipment = 2 THEN 'Refuse'
                    WHEN rop.status_reshipment = 1 THEN 'Agree'
                    ELSE 0
                    END as status_reshipment
                FROM
                    oc_yzc_rma_order ro
                LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = ro.id
                LEFT JOIN oc_product op on op.product_id = rop.product_id
                where ro.id = " . $rmaId;
        return $this->db->query($sql)->row;
    }

    public function getCommunicationInfoOrm($rmaId)
    {
        $obj = $this->orm->table('oc_yzc_rma_order as ro')
            ->select(['rop.rma_type',
                'rop.rma_id',
                'ro.order_id',
                'ro.rma_order_id',
                'op.sku',
                'op.mpn',
                'ro.seller_id',
                'ro.buyer_id',
                'pm.original_seller_id',])
            ->selectRaw("CASE WHEN rop.status_refund = 2 THEN 'Refuse'
                    WHEN rop.status_refund = 1 THEN 'Agree'
                    END as status_refund,
                    CASE WHEN rop.status_reshipment = 2 THEN 'Refuse'
                    WHEN rop.status_reshipment = 1 THEN 'Agree'
                    END as status_reshipment")
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->leftJoin('oc_product as op', 'op.product_id', '=', 'rop.product_id')
            ->leftJoin('tb_underwriting_shop_product_mapping as pm', 'rop.product_id', '=', 'pm.underwriting_product_id')
            ->where('ro.id', $rmaId)
            ->first();
        return $obj;
    }

    /**
     * @param $rmaId
     * @return int
     */
    public function getPurchaseOrderRmaPrice($rmaId)
    {
        $sql = "SELECT
                     CASE WHEN opq.price IS NULL THEN
                                        (oop.price+oop.service_fee_per+oop.freight_per+oop.package_fee)*rop.quantity
                                        ELSE
                                        (oop.price+oop.service_fee_per-opq.amount_price_per-opq.amount_service_fee_per+oop.freight_per+oop.package_fee)*rop.quantity
                                        END AS total,
                                    opq.price,
                                    opq.discount_price
            FROM
                oc_yzc_rma_order ro
            LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = ro.id
            LEFT JOIN oc_order_product oop ON oop.order_id = ro.order_id
                       AND oop.product_id = rop.product_id
            LEFT JOIN oc_product_quote opq on (opq.order_id=ro.order_id and opq.product_id = rop.product_id)
            WHERE ro.id = " . $rmaId;
        $query = $this->db->query($sql);
        return $query->row['total'];
    }

    public function purchaseOrderRma($rmaId)
    {
        //buyer库存出库
        $sql = "select roi.b2b_order_id as order_id,roi.product_id,cd.id,c.customer_group_id,ctc.accounting_type,
          c.customer_id,rop.id as rma_product_id
          from tb_sys_cost_detail cd
          LEFT JOIN oc_customer c on c.customer_id = cd.seller_id
          LEFT JOIN oc_customerpartner_to_customer ctc on ctc.customer_id=c.customer_id
          LEFT JOIN tb_sys_receive_line srl on srl.id=cd.source_line_id
          LEFT JOIN vw_rma_order_info roi on roi.b2b_order_id = srl.oc_order_id and roi.product_id = srl.product_id
          LEFT JOIN oc_yzc_rma_order_product rop on rop.rma_id=roi.rma_id
          where roi.rma_id = " . $rmaId;
        $result = $this->db->query($sql)->row;
        if (isset($result['id'])) {
            $qty = $this->db->query("select quantity from oc_yzc_rma_order_product where rma_id = " . $rmaId)->row['quantity'];
            // 检测此时的buyer库存能否满足出库的数量要求  防止出现负数  add by kimi
            $costDetail = db('tb_sys_cost_detail')->where('id', $result['id'])->first();
            if (!$costDetail) {
                throw new Exception('Error! Can not find buyer inventory info.');
            }
            if ((int)$costDetail->onhand_qty < $qty) {
                throw new Exception('Buyer\'s inventory of the product is insufficient for RMA return. Please contact customer service team to check.');
            }
            $this->db->query("update tb_sys_cost_detail set onhand_qty = onhand_qty-" . $qty . " where id=" . $result['id']);
            //buyer新增出库记录
            $sql = "SELECT
                        rop.product_id,op.combo_flag,cto.id,rop.order_product_id,cto.order_id
                    FROM
                        oc_yzc_rma_order_product rop
                    LEFT JOIN oc_customerpartner_to_order cto ON cto.order_product_id = rop.order_product_id
                    LEFT JOIN oc_product op on op.product_id=rop.product_id
                    WHERE rop.rma_id = " . $rmaId;
            $resultInfo = $this->db->query($sql)->row;
            $sqlDelivery = "insert into tb_sys_delivery_line (SalesHeaderId,SalesLineId,TrackingId,ProductId,DeliveryType,DeliveryQty,CostId,type,create_user_name,create_time,ProgramCode) VALUES (0,0,0," . $resultInfo['product_id'] . ",1," . $qty . "," . $result['id'] . ",3,1,now(),'V1.0')";
            $this->db->query($sqlDelivery);
            //大建云seller新增入库数据 或者(保证金的包销店铺)
            if ($result['accounting_type'] == 2 || in_array($result['customer_id'], $this->config->get('config_customer_group_ignore_check'))) {
                if ($resultInfo['combo_flag'] == 1) {
                    $batchInfoSql =
                        <<<SQL
                                 SELECT
                                        tsb.*,soc.qty,soc.set_product_id
                                    FROM
                                        tb_sys_order_combo soc
                                    LEFT JOIN (
                                    select * from tb_sys_seller_delivery_line sdl
                                    where qty>0
                                      and order_id = {$resultInfo['order_id']}
                                      and order_product_id = {$resultInfo['order_product_id']}
                                      GROUP BY order_product_id,product_id
                                    ) t ON t.order_product_id = soc.order_product_id and t.product_id=soc.set_product_id and t.qty>0
                                    LEFT JOIN tb_sys_batch tsb on tsb.batch_id=t.batch_id
                                    WHERE
                                        soc.order_product_id = {$resultInfo['order_product_id']}
                                        and soc.order_id = {$resultInfo['order_id']}
SQL;
                    $batchInfos = $this->db->query($batchInfoSql)->rows;
                    foreach ($batchInfos as $batchInfo) {
                        if ($batchInfo['batch_number'] != null) {
                            $batch = "insert into tb_sys_batch (batch_number,receipts_order_id,receipts_order_line_id,source_code,sku,mpn,product_id,original_qty,onhand_qty,warehouse,remark,customer_id,receive_date,source_batch_id,create_user_name,create_time,program_code,rma_id,unit_price,transaction_type) VALUES (";
                            $batch .= isset($batchInfo['batch_number']) ? "'" . $batchInfo['batch_number'] . "'" : "null";
                            $batch .= ",";
                            $batch .= isset($batchInfo['receipts_order_id']) ? $batchInfo['receipts_order_id'] : "null";
                            $batch .= ",";
                            $batch .= isset($batchInfo['receipts_order_line_id']) ? $batchInfo['receipts_order_line_id'] : "null";
                            $batch .= ",";
                            $batch .= "'退货收货','" . $batchInfo['sku'] . "','"
                                . $batchInfo['mpn'] . "'," . $batchInfo['product_id'] . ","
                                . $qty * $batchInfo['qty'] . "," . $qty * $batchInfo['qty'] . ",'"
                                . $batchInfo['warehouse'] . "','退货采购订单id:" . $resultInfo['order_id']
                                . "'," . $batchInfo['customer_id'] . ",now()," . $batchInfo['source_batch_id']
                                . ",'1',now(),'V1.0'," . $rmaId
                                .','.($batchInfo['unit_price'] ?? 0). "," . BatchTransactionType::RMA_RETURN
                                . ")";
                        } else {
                            $noSellerDeliverySql = " select op.sku,op.mpn,op.product_id,ctp.customer_id
                                                      from  oc_product op
                                                      LEFT JOIN oc_customerpartner_to_product ctp on ctp.product_id=op.product_id
                                                      where op.product_id=" . $batchInfo['set_product_id'];
                            $noSellerDelivery = $this->db->query($noSellerDeliverySql)->row;
                            $batch = "insert into tb_sys_batch (batch_number,receipts_order_id,receipts_order_line_id,source_code,
                            sku,mpn,product_id,original_qty,onhand_qty,warehouse,remark,customer_id,receive_date,source_batch_id,create_user_name,create_time,program_code,rma_id,unit_price,transaction_type) VALUES (";
                            $batch .= "'超卖退库存',null,null,'退货收货','" . $noSellerDelivery['sku']
                                . "','" . $noSellerDelivery['mpn'] . "'," . $noSellerDelivery['product_id']
                                . "," . $qty . "," . $qty . ",null,'退货采购订单id:" . $resultInfo['order_id']
                                . "'," . $noSellerDelivery['customer_id'] . ",now(),-1,'1',now(),'V1.0'," . $rmaId
                                .','.($batchInfo['unit_price'] ?? 0). "," . BatchTransactionType::RMA_RETURN
                                . ")";
                        }
                        $this->db->query($batch);
                        if (!empty($batchInfo['customer_id'])) {
                            app(SellerAssetService::class)->addCollateralValueByOrder($batchInfo['customer_id'], ($batchInfo['unit_price'] ?? 0) * $qty);
                        }
                    }
                } else {
                    $batchInfoSql = <<<SQL
                            select tsb.*,sdl.qty
                            from tb_sys_seller_delivery_line sdl
                            LEFT JOIN tb_sys_batch tsb on sdl.batch_id = tsb.batch_id
                            where sdl.qty>0
                              and sdl.order_id = {$resultInfo['order_id']}
                              and sdl.order_product_id= {$resultInfo['order_product_id']}
SQL;

                    $batchInfo = $this->db->query($batchInfoSql)->row;
                    if (count($batchInfo) > 0) {
                        $batch = "insert into tb_sys_batch (batch_number,receipts_order_id,receipts_order_line_id,source_code,sku,mpn,product_id,original_qty,onhand_qty,warehouse,remark,
                                  customer_id,receive_date,source_batch_id,create_user_name,create_time,program_code,rma_id,unit_price,transaction_type) VALUES (";
                        $batch .= isset($batchInfo['batch_number']) ? "'" . $batchInfo['batch_number'] . "'" : "null";
                        $batch .= ",";
                        $batch .= isset($batchInfo['receipts_order_id']) ? $batchInfo['receipts_order_id'] : "null";
                        $batch .= ",";
                        $batch .= isset($batchInfo['receipts_order_line_id']) ? $batchInfo['receipts_order_line_id'] : "null";
                        $batch .= ",";
                        $batch .= "'退货收货','" . $batchInfo['sku'] . "','" . $batchInfo['mpn']
                            . "'," . $batchInfo['product_id'] . "," . $qty . "," . $qty
                            . ",'" . $batchInfo['warehouse'] . "','退货采购订单id:"
                            . $resultInfo['order_id'] . "'," . $batchInfo['customer_id']
                            . ",now()," . $batchInfo['source_batch_id'] . ",'1',now(),'V1.0'," . $rmaId
                            .','.($batchInfo['unit_price'] ?? 0). "," . BatchTransactionType::RMA_RETURN
                            . ")";
                    } else {
                        $noSellerDeliverySql = " select op.sku,op.mpn,op.product_id,ctp.customer_id from oc_order_product oop
                                                      LEFT JOIN oc_product op on op.product_id=oop.product_id
                                                      LEFT JOIN oc_customerpartner_to_product ctp on ctp.product_id=op.product_id
                                                      where oop.order_product_id=" . $resultInfo['order_product_id'];
                        $noSellerDelivery = $this->db->query($noSellerDeliverySql)->row;
                        $batch = "insert into tb_sys_batch (batch_number,receipts_order_id,receipts_order_line_id,source_code,
                            sku,mpn,product_id,original_qty,onhand_qty,warehouse,remark,customer_id,receive_date,source_batch_id,create_user_name,create_time,program_code,rma_id,unit_price,transaction_type) VALUES (";
                        $batch .= "'超卖退库存',null,null,'退货收货','" . $noSellerDelivery['sku']
                            . "','" . $noSellerDelivery['mpn'] . "'," . $noSellerDelivery['product_id']
                            . "," . $qty . "," . $qty . ",null,'退货采购订单id:" . $resultInfo['order_id']
                            . "'," . $noSellerDelivery['customer_id'] . ",now(),-1,'1',now(),'V1.0'," . $rmaId
                            .','.($batchInfo['unit_price'] ?? 0). "," . BatchTransactionType::RMA_RETURN
                            . ")";
                    }
                    $this->db->query($batch);
                    if (!empty($batchInfo['customer_id'])) {
                        app(SellerAssetService::class)->addCollateralValueByOrder($batchInfo['customer_id'], ($batchInfo['unit_price'] ?? 0) * $qty);
                    }
                }
            }
            //修改oc_customerpartner_to_order的订单状态
            $this->db->query("update oc_customerpartner_to_order set order_product_status = 13 where id=" . $resultInfo['id']);
            //判断是否需要从返点协议中剔除
            $rebateInfo = $this->getRebateInfo($result['order_id'], $result['product_id']);
            //正在进行中的返点协议
            if (in_array($rebateInfo['rebate_result'] ?? 0, [1, 2])) {
                //扣减参与返点的数量
                $insertSql = "insert into oc_rebate_agreement_order (
                                  agreement_id,
                                  item_id,
                                  product_id,
                                  qty,
                                  order_id,
                                  order_product_id,
                                  rma_id,
                                  rma_product_id,
                                  type,
                                  create_time,
                                  update_time,
                                  program_code
                                  )values(
                                  " . $rebateInfo['agreement_id'] . ",
                                  " . $rebateInfo['item_id'] . ",
                                  " . $rebateInfo['product_id'] . ",
                                  " . $qty . ",
                                  " . $rebateInfo['order_id'] . ",
                                  " . $rebateInfo['order_product_id'] . ",
                                  " . $rmaId . ",
                                  " . $result['rma_product_id'] . ",
                                  2,
                                  now(),
                                  now(),
                                  'V1.0'
                                  )";
                $this->db->query($insertSql);
            }
        } else {
            $this->log->write($rmaId . ",没有采购订单");
        }
    }

    /**
     * 获取重发单的明细数据
     * @param $rma_id
     * @return mixed
     */
    public function getReorderLine($rma_id)
    {
        $sql = "select * from tb_sys_customer_sales_reorder csr LEFT JOIN tb_sys_customer_sales_reorder_line csrl on csr.id=csrl.reorder_header_id WHERE csr.rma_id=" . $rma_id;
        return $this->db->query($sql)->row;
    }

    /**
     * 获取产品的信息
     * @param string $mpn
     * @param int $customer_id
     * @return mixed
     */
    public function getProduct($mpn, $customer_id)
    {
        $sql = "select op.product_id,opd.name,op.sku from oc_product op
                LEFT JOIN oc_customerpartner_to_product ctp on ctp.product_id=op.product_id
                LEFT JOIN oc_product_description opd on opd.product_id=op.product_id
                WHERE op.mpn= '" . $mpn . "' AND ctp.customer_id=" . $customer_id;
        return $this->db->query($sql)->row;
    }

    /**
     * 添加重发单明细
     * @param $data
     */
    public function addReOrderLine($data)
    {
        $sql = "insert into tb_sys_customer_sales_reorder_line (reorder_header_id, line_item_number, product_name, qty, item_code, product_id,
                    image_id, seller_id, item_status, memo, create_user_name, create_time,part_flag,program_code)
                VALUES (" . $data['reorder_header_id'] . "," . $data['line_item_number'] . ",'" . $data['product_name'] . "',
                " . $data['qty'] . ",'" . $data['item_code'] . "'," . $data['product_id'] . ",'" . $data['image_id'] . "'," . $data['seller_id'] . ",
                " . $data['item_status'] . ",'" . $data['memo'] . "'," . $data['create_user_name'] . ",'" . $data['create_time'] . "'," . $data['part_flag'] . ",'" . $data['program_code'] . "')";
        $this->db->query($sql);
    }

    /**
     * 删除重发单
     * @param int $id
     */
    public function deleteReOrderLine($id)
    {
        $this->db->query("delete from tb_sys_customer_sales_reorder_line where id =" . $id);
    }

    /**
     * 获取combo对应的子sku
     * @param int $productId
     * @return array
     * @author  xxl
     */
    public function getSetComboInfo($productId)
    {
        $result = $this->orm->table('tb_sys_product_set_info as si')
            ->leftJoin('oc_product as op', 'op.product_id', '=', 'si.set_product_id')
            ->where('si.product_id', '=', $productId)
            ->select('op.product_id', 'op.sku', 'op.mpn')
            ->get();
        return obj2array($result);;
    }

    /**
     * 获取重发单类型 1:销售订单退货2：采购订单退货
     *
     * @param $rma_id
     * @return array
     */
    public function getRmaOrderType($rma_id)
    {
        $rma_record = $this->orm->table('oc_yzc_rma_order')
            ->where(['id' => $rma_id])
            ->select('order_type')
            ->get();
        return obj2array($rma_record);
    }

    public function getSellerId($rma_id)
    {
        $rma_record = $this->orm->table('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->leftJoin('tb_underwriting_shop_product_mapping as spm', 'spm.underwriting_product_id', '=', 'rop.product_id')
            ->where(['ro.id' => $rma_id])
            ->select(['ro.seller_id', 'spm.original_seller_id'])
            ->first();
        return $rma_record;
    }

    public function getOrder($order_id)
    {

        $sql = "SELECT o.*,c2o.*,o.date_added,c.nickname,c.user_number,os.name as order_status_name FROM `" . DB_PREFIX . "order` o
        LEFT JOIN " . DB_PREFIX . "customerpartner_to_order c2o ON (o.order_id = c2o.order_id)
        LEFT JOIN oc_customer as c on c.customer_id = o.customer_id
        LEFT JOIN oc_order_status as os on os.order_status_id = o.order_status_id
        WHERE o.order_id = '" . (int)$order_id . "' AND o.order_status_id > '0'";
        $order_query = $this->db->query($sql);

        if ($order_query->num_rows) {
            $country_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE country_id = '" . (int)$order_query->row['payment_country_id'] . "'");

            if ($country_query->num_rows) {
                $payment_iso_code_2 = $country_query->row['iso_code_2'];
                $payment_iso_code_3 = $country_query->row['iso_code_3'];
            } else {
                $payment_iso_code_2 = '';
                $payment_iso_code_3 = '';
            }

            $zone_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE zone_id = '" . (int)$order_query->row['payment_zone_id'] . "'");

            if ($zone_query->num_rows) {
                $payment_zone_code = $zone_query->row['code'];
            } else {
                $payment_zone_code = '';
            }

            $country_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE country_id = '" . (int)$order_query->row['shipping_country_id'] . "'");

            if ($country_query->num_rows) {
                $shipping_iso_code_2 = $country_query->row['iso_code_2'];
                $shipping_iso_code_3 = $country_query->row['iso_code_3'];
            } else {
                $shipping_iso_code_2 = '';
                $shipping_iso_code_3 = '';
            }

            $zone_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE zone_id = '" . (int)$order_query->row['shipping_zone_id'] . "'");

            if ($zone_query->num_rows) {
                $shipping_zone_code = $zone_query->row['code'];
            } else {
                $shipping_zone_code = '';
            }

            return array(
                'order_id' => $order_query->row['order_id'],
                'delivery_type' => $order_query->row['delivery_type'],
                'invoice_no' => $order_query->row['invoice_no'],
                'invoice_prefix' => $order_query->row['invoice_prefix'],
                'store_id' => $order_query->row['store_id'],
                'store_name' => $order_query->row['store_name'],

                'store_url' => $order_query->row['store_url'],
                'customer_id' => $order_query->row['customer_id'],

                'shipping_applied' => $order_query->row['shipping_applied'],

                'firstname' => $order_query->row['firstname'],
                'lastname' => $order_query->row['lastname'],
                'nickname' => $order_query->row['nickname'] . "(" . $order_query->row['user_number'] . ")",
                'telephone' => $order_query->row['telephone'],
                'fax' => $order_query->row['fax'],
                'email' => $order_query->row['email'],
                'payment_firstname' => $order_query->row['payment_firstname'],
                'payment_lastname' => $order_query->row['payment_lastname'],
                'payment_company' => $order_query->row['payment_company'],
                'payment_address_1' => $order_query->row['payment_address_1'],
                'payment_address_2' => $order_query->row['payment_address_2'],
                'payment_postcode' => $order_query->row['payment_postcode'],
                'payment_city' => $order_query->row['payment_city'],
                'payment_zone_id' => $order_query->row['payment_zone_id'],
                'payment_zone' => $order_query->row['payment_zone'],
                'payment_zone_code' => $payment_zone_code,
                'payment_country_id' => $order_query->row['payment_country_id'],
                'payment_country' => $order_query->row['payment_country'],
                'payment_iso_code_2' => $payment_iso_code_2,
                'payment_iso_code_3' => $payment_iso_code_3,
                'payment_address_format' => $order_query->row['payment_address_format'],
                'payment_method' => $order_query->row['payment_method'],
                'shipping_firstname' => $order_query->row['shipping_firstname'],
                'shipping_lastname' => $order_query->row['shipping_lastname'],
                'shipping_company' => $order_query->row['shipping_company'],
                'shipping_address_1' => $order_query->row['shipping_address_1'],
                'shipping_address_2' => $order_query->row['shipping_address_2'],
                'shipping_postcode' => $order_query->row['shipping_postcode'],
                'shipping_city' => $order_query->row['shipping_city'],
                'shipping_zone_id' => $order_query->row['shipping_zone_id'],
                'shipping_zone' => $order_query->row['shipping_zone'],
                'shipping_zone_code' => $shipping_zone_code,
                'shipping_country_id' => $order_query->row['shipping_country_id'],
                'shipping_country' => $order_query->row['shipping_country'],
                'shipping_iso_code_2' => $shipping_iso_code_2,
                'shipping_iso_code_3' => $shipping_iso_code_3,
                'shipping_address_format' => $order_query->row['shipping_address_format'],
                'shipping_method' => $order_query->row['shipping_method'],
                'shipping_code' => $order_query->row['shipping_code'],
                'comment' => $order_query->row['comment'],
                'total' => $order_query->row['total'],
                'order_status_id' => $order_query->row['order_status_id'],
                'order_status_name' => $order_query->row['order_status_name'],
                'language_id' => $order_query->row['language_id'],
                'currency_id' => $order_query->row['currency_id'],
                'currency_code' => $order_query->row['currency_code'],
                'currency_value' => $order_query->row['currency_value'],
                'date_modified' => $order_query->row['date_modified'],
                'date_added' => $order_query->row['date_added'],
                'ip' => $order_query->row['ip']
            );
        } else {
            return false;
        }
    }

    /**
     * 保证金包销店铺的RMA Management,并且有保证金合同的rma申请,禁用submit按钮
     * @param $rmaId
     * @return bool
     * @author xxl
     */
    public function getCanEditRmaFlag($rmaId)
    {
        $order = $this->getOrderInfo($rmaId);
        $this->load->model('account/customerpartner/margin_order');
        $agree_info = $this->model_account_customerpartner_margin_order->getAgreementInfoByOrderProduct(
            (int)$order['order_id'], (int)$order['product_id']
        );
        if ($agree_info == null) return true;
        return false;
    }

    /**
     * @param int $rmaId
     * user：wangjinxin
     * date：2020/3/26 11:42
     * @return array|null
     */
    public function getAgreeInfoByRmaId(int $rmaId)
    {
        $order = $this->getOrderInfo($rmaId);
        if ($this->checkIsMarginRma($rmaId)) {
            $this->load->model('account/customerpartner/margin_order');
            return $this->model_account_customerpartner_margin_order->getAgreementInfoByOrderProduct(
                (int)$order['order_id'], (int)$order['product_id']
            );
        }
        if ($this->checkIsFuturesRma($rmaId)) {
            $this->load->model('account/customerpartner/futures_order');
            return $this->model_account_customerpartner_futures_order->getAgreementInfoByOrderProduct(
                (int)$order['order_id'], (int)$order['product_id']
            );
        }
        return null;
    }

    /**
     * 校验某个rmaid是否为保证金rma
     * user：wangjinxin
     * date：2020/3/25 14:26
     * @param int $rmaId
     * @return bool
     */
    public function checkIsMarginRma(int $rmaId): bool
    {
        $order = $this->getOrderInfo($rmaId);
        $this->load->model('account/customerpartner/margin_order');
        $agree_info = $this->model_account_customerpartner_margin_order->getAgreementInfoByOrderProduct(
            (int)$order['order_id'], (int)$order['product_id']
        );

        return $agree_info !== null;
    }

    /**
     * @param int $rmaId
     * @return bool
     * user：wangjinxin
     * date：2020/4/10 16:57
     */
    public function checkIsFuturesRma(int $rmaId): bool
    {
        $order = $this->getOrderInfo($rmaId);
        $this->load->model('account/customerpartner/futures_order');
        $agree_info = $this->model_account_customerpartner_futures_order->getAgreementInfoByOrderProduct(
            (int)$order['order_id'], (int)$order['product_id']
        );

        return $agree_info !== null;
    }

    /**
     * 获取complete的返金价格
     * @param $rmaId
     * @return mixed
     * @author xxl
     */
    public function getMarginOrderInfo($rmaId)
    {
        $sql = "SELECT
                    (sma.price+oop.freight_per+oop.package_fee)*soa.qty as total
                    FROM
                        oc_yzc_rma_order ro
                    LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = ro.id
                    LEFT JOIN oc_order_product oop ON oop.order_id = ro.order_id
                    AND oop.product_id = rop.product_id
                    LEFT JOIN tb_sys_margin_process smp ON smp.rest_product_id=rop.product_id
                    LEFT JOIN tb_sys_margin_agreement sma ON smp.margin_id = sma.id
                    LEFT JOIN tb_sys_customer_sales_order cso on cso.order_id = ro.from_customer_order_id and cso.buyer_id = ro.buyer_id
                    LEFT JOIN tb_sys_order_associated soa on soa.sales_order_id = cso.id and soa.product_id = rop.product_id and soa.order_id = ro.order_id
                    WHERE ro.id = " . $rmaId;
        $query = $this->db->query($sql);
        return $query->row['total'];
    }

    public function getOrderLineInfo($orderId, $productId)
    {
        $sql = "select * from oc_order_product where order_id=" . $orderId . " and product_id =" . $productId;
        $query = $this->db->query($sql);
        return $query->row;
    }


    /**
     * 获取RMA的详细信息
     * @param $rmaId
     */
    public function getRebateRmaInfo($rmaId)
    {
        $rmaSql = "select cso.order_status,ro.order_id,rop.product_id,ro.order_type,ifnull(soa.qty,rop.quantity) as rmaQty from oc_yzc_rma_order ro
                LEFT JOIN oc_yzc_rma_order_product rop on rop.rma_id=ro.id
                LEFT JOIN tb_sys_customer_sales_order cso on cso.order_id=ro.from_customer_order_id and ro.buyer_id=cso.buyer_id
                LEFT JOIN tb_sys_order_associated soa on soa.sales_order_id = cso.id and soa.order_id= ro.order_id and soa.product_id=rop.product_id
                where ro.id =" . $rmaId;
        $rmaInfo = $this->db->query($rmaSql)->row;
        return $rmaInfo;
    }

    /**
     * 获取返点信息
     * @param $rmaId
     */
    public function getRebateInfo($order_id, $product_id)
    {
        $sql = "select rao.*,ora.rebate_result,rai.rebate_amount,ora.id,ora.qty as rebateQty from oc_rebate_agreement_order  rao
          LEFT JOIN oc_rebate_agreement ora on ora.id = rao.agreement_id
          LEFT JOIN oc_rebate_agreement_item rai on rai.agreement_id = ora.id and rai.product_id = rao.product_id
          where rao.type = 1 and ora.status = 3 and rao.order_id =  " . $order_id . " and rao.product_id = " . $product_id;
        $rebateInfo = $this->db->query($sql)->row;
        return $rebateInfo;
    }

    /**
     * 获取最新完成返点申请信息
     * @param int $agreement_id
     * @return array
     */
    public function getRebateRequestInfo($agreement_id)
    {
        $sql = "select process_status from oc_rebate_agreement_request where agreement_id=" . $agreement_id . " order by id desc limit 1";
        $rebateRequestInfo = $this->db->query($sql)->row;
        return $rebateRequestInfo;
    }


    /**
     * 查询该订单前的可参加返点协议数量
     * @param int $order_id
     * @param int $product_id
     * @param int $agreement_id
     * @return int
     */
    public function getRebateOrderBefore($order_id, $product_id, $agreement_id)
    {
        $sql = "select sum(qty) as qty from oc_rebate_agreement_order where type = 1 and agreement_id ="
            . $agreement_id . " and order_product_id <
        (select order_product_id from oc_order_product where order_id=" . $order_id
            . " and product_id = " . $product_id . ")";
        $orderQty = $this->db->query($sql)->row['qty'];
        $rmaSql = "select sum(qty) as qty from oc_rebate_agreement_order where type = 2 and agreement_id ="
            . $agreement_id . " and order_product_id <
        (select order_product_id from oc_order_product where order_id=" . $order_id
            . " and product_id = " . $product_id . ")";
        $rmaQty = $this->db->query($rmaSql)->row['qty'];
        return $orderQty - $rmaQty;
    }

    /**
     * 查询该订单可参见的返点协议数量
     * @param int $order_id
     * @param int $product_id
     * @param int $agreement_id
     * @return int
     */
    public function getRebateQty($order_id, $product_id, $agreement_id)
    {
        $sql = "select sum(qty) as qty from oc_rebate_agreement_order where type = 1 and agreement_id ="
            . $agreement_id . " and order_id = " . $order_id . " and product_id =" . $product_id;
        $orderQty = $this->db->query($sql)->row['qty'];
        $rmaSql = "select sum(qty) as qty from oc_rebate_agreement_order where type = 2 and agreement_id ="
            . $agreement_id . " and order_id =" . $order_id . " and product_id =" . $product_id;
        $rmaQty = $this->db->query($rmaSql)->row['qty'];
        return $orderQty - $rmaQty;
    }


    /**
     * [updateProductQtyByReshipment description] storage/modification/catalog/model/checkout/order.php  updateOtherComboQuantity 此方法一样
     * @param $combo_info
     * @param int $product_id
     */
    public function updateProductQtyByReshipment($combo_info, $product_id)
    {
        $this->load->model('checkout/order');
        $this->load->model('common/product');
        /* @var ModelCheckoutOrder $checkout_order */
        $checkout_order = $this->model_checkout_order;
        /** @var ModelCommonProduct $model_common_product */
        $model_common_product = $this->model_common_product;
        //验证此rma是否为combo
        if ($combo_info) {
            $child_sku_str = implode(',', array_column($combo_info, 'product_id'));
        } else {
            $child_sku_str = $product_id;
        }
        //获取所有包含此子sku的combo的product_id
        $sql = "SELECT group_concat(DISTINCT psi.product_id) as product_str from tb_sys_product_set_info psi
               LEFT JOIN oc_product op ON op.product_id = psi.set_product_id
               where psi.set_product_id in ( " . $child_sku_str .
            ") and psi.product_id is not null";

        $calc_product = $this->db->query($sql)->rows;
        if ($calc_product[0]['product_str']) {
            $list = explode(',', $calc_product[0]['product_str']);
            foreach ($list as $key => $value) {
                $comboOnShelfQuantity = $checkout_order->getProductOnSelfQuantity($value);
                $data = $model_common_product->getProductAvailableQuantity($value);
                if ($data < $comboOnShelfQuantity) {
                    $checkout_order->setProductOnShelfQuantity($value, $data);
                }
                //如果库存低于 设定的低库存 提醒数量，则需要添加 notification 提醒
                if ((int)$this->config->get('marketplace_low_stock_quantity') > $data) {
                    $checkout_order->addSystemMessageAboutProductStock((int)$value);
                }
            }
        }
    }


    /**
     * 退款路径 CL 1、退到余额，4、退到虚拟账户
     * */
    public function refundType($rmaId)
    {
        $payment = $this->orm->table('oc_yzc_rma_order as r')
            ->leftJoin('oc_order as o', 'o.order_id', '=', 'r.order_id')
            ->where('id', '=', $rmaId)
            ->value('payment_code');
        if (PayCode::PAY_VIRTUAL == $payment){
            return 4;
        }else{
            return 1;
        }
    }

    //执行退款 更新RMA信息
    public function refund($rmaId, $refundMoney,$refund_agree_comments)
    {

        $order_result = $this->getRmaFromOrderInfo($rmaId);
        $line_info = $this->getSellerOrderProductInfoIncludeSaleLine($rmaId);
        $refund_info = [];
        if ($order_result && $line_info && CustomerSalesOrderStatus::COMPLETED == $order_result['order_status']){
            $this->load->model('account/rma_management');
            $this->load->model('account/customerpartner/futures_order');

            $is_margin = $this->checkIsMarginRma($rmaId);
            $is_future = $this->checkIsFuturesRma($rmaId);
            if ($is_margin || $is_future){
                $order_line_info = $this->getOrderLineInfo($order_result['order_id'], $order_result['product_id']);
                if ($is_margin){
                    $refund_info = $this->model_account_rma_management->getMarginPriceInfo(
                        null, $line_info[0]['quantity'],$order_line_info['order_product_id']);
                }
                if ($is_future && !$refund_info){
                    $agree_info = $this->model_account_customerpartner_futures_order->getAgreementInfoByOrderProduct(
                        $order_result['order_id'], $order_result['product_id']);
                    $refund_info = $this->model_account_rma_management->getFutureMarginPriceInfo(
                        $agree_info['id'], $line_info[0]['quantity'], $order_line_info['order_product_id']);
                }
            }
        }
        $refundType = $this->refundType($rmaId);
        $lcMoney = $vpMoney = 0;
        if ($refund_info && 4 == $refundType){//现货、期货尾款订单 且已绑定sales order 且销售订单已完成 且尾款采用了虚拟支付
            $historyRefundLC = $this->historyRefundToLC($order_result);//此前退到余额账户的金额
            $advanceMax = $refund_info['advance_unit_price'] * $order_result['qty'];
            if ($historyRefundLC >= $advanceMax){
                $refundType = 4;//定金退全后,只能退到虚拟账户
            }else{
                $refundType = 5;//头款非虚拟支付 尾款虚拟支付，退款时需原路返回
                $unit_price = $refund_info['advance_unit_price'] + $refund_info['rest_unit_price'] + $refund_info['freight_unit_price'] + $refund_info['poundage_per'] + $refund_info['service_fee_per'];
                $lcMoney = round(($refund_info['advance_unit_price']/$unit_price) * $refundMoney, 2);
                $lcMoneyMax = $advanceMax - $historyRefundLC;//当前可退至余额的最大金额 = 头款（余额）可退最大金额 - 已退至余额的金额
                //头款非虚拟支付、尾款虚拟支付,不论退多少次，退到余额的总金额不得超过采用非虚拟支付方式支付的总金额
                $lcMoney = $lcMoney > $lcMoneyMax ? $lcMoneyMax : $lcMoney;
                $vpMoney = $refundMoney - $lcMoney;
            }
        }

        $info = $this->orm->table('oc_yzc_rma_order')
            ->where('id', '=', $rmaId)
            ->select('seller_id','buyer_id')
            ->first();
        $info = obj2array($info);
        switch ($refundType){
            case 5:{
                $this->rmaVirtualPay($rmaId,$vpMoney,$info['buyer_id']);
                $this->rmaLineOfCredit($rmaId,$lcMoney,$info['seller_id'],$info['buyer_id']);
                break;
            }
            case 4:{
                $this->rmaVirtualPay($rmaId,$refundMoney,$info['buyer_id']);
                break;
            }
            case 1:{
                $this->rmaLineOfCredit($rmaId,$refundMoney,$info['seller_id'],$info['buyer_id']);
                break;
            }
        }

        //更新RMA信息
        $this->agreeRefund($rmaId, $refund_agree_comments, $refundMoney, $refundType);
    }

    //退款至 Line Of Credit
    public function rmaLineOfCredit($rmaId, $refundMoney, $seller_id, $buyer_id)
    {
        $lineOfCreditOriginal = (float)$this->customer->getLineOfCreditBySeller($buyer_id);
        $lineOfCreditAdd = (float)$this->db->escape($refundMoney);
        $lineOfCreditNow = $lineOfCreditOriginal + $lineOfCreditAdd;
        $serialNumber = SerialNumberRepository::getDateSerialNumber(ServiceEnum::AMENDMENT_RECORD_NO);
        $sql = "INSERT INTO tb_sys_credit_line_amendment_record (serial_number,customer_id,old_line_of_credit,new_line_of_credit,date_added,operator_id,type_id,header_id) VALUES (" . $serialNumber . ',' . $buyer_id . "," . $lineOfCreditOriginal . "," . $lineOfCreditNow . ",now()," . $seller_id . ",3," . $rmaId . ")";
        $this->db->query($sql);
        $this->db->query("UPDATE oc_customer set line_of_credit = ifnull(line_of_credit,0)+" . $lineOfCreditAdd . " WHERE customer_id =" . $buyer_id);
        if (!empty($seller_id) && !empty($buyer_id) && !empty($lineOfCreditAdd)) {
            $this->db->query("update oc_buyer_to_seller set money_of_transaction = money_of_transaction-$lineOfCreditAdd where buyer_id=$buyer_id and seller_id=$seller_id");
        }
    }

    //退款至 Virtual Pay
    public function rmaVirtualPay($rmaId,$refundMoney,$customerId)
    {
        $this->load->model('account/balance/virtual_pay_record');
        $this->model_account_balance_virtual_pay_record->insertData($customerId,$rmaId,$refundMoney,2);
    }

    //取该销售单以往RMA退款成功信息
    public function historyRefundToLC($info)
    {
        $rmaIdArr = $this->orm->table('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->where([
                'ro.order_id'   => $info['order_id'],
                'ro.from_customer_order_id' => $info['from_customer_order_id'],
                'ro.seller_id'  => $info['seller_id'],
                'ro.buyer_id'   => $info['buyer_id'],
                'order_type'    => 1,//sales order
                'seller_status' => 2,//agree
                'rop.product_id'=> $info['product_id']
            ])
            ->pluck('ro.id')
            ->toArray();
        $money = 0;
        if ($rmaIdArr){
            $refundList = $this->orm->table('tb_sys_credit_line_amendment_record')
                ->where([
                    'customer_id'   => $info['buyer_id'],
                    'type_id'       => 3
                ])
                ->whereIn('header_id', $rmaIdArr)
                ->selectRaw('new_line_of_credit-old_line_of_credit as money')
                ->get();
            foreach ($refundList as $value)
            {
                $money += $value->money;
            }
        }

        return $money;
    }
}
