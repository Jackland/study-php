<?php

use App\Components\RemoteApi;
use App\Components\RemoteApi\B2BManager\DTO\FileDTO;
use App\Repositories\SellerBill\SettlementRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use App\Repositories\Warehouse\SellerInventoryAdjustRepository;
use App\Repositories\SellerBill\DealSettlement\DealSettlement;

/**
 * Class ModelAccountSellerBillBillDetail
 * seller 结算账单明细
 *
 * @property ModelCustomerpartnerRmaManagement model_customerpartner_rma_management
 * @property ModelToolImage model_tool_image
 */
class ModelAccountSellerBillBillDetail extends Model
{
    const ORDER = 'order';
    const REFUND = 'refund';
    const OTHER = 'other';
    const ORDER_ORDER_AMOUNT = 'order_order_amount';
    const ORDER_PROMOTION = 'order_promotion';
    const ORDER_LOGISTICS = 'order_logistics';
    const ORDER_PLATFORM_FEE = 'order_platform_fee';
    const REFUND_ORDER_AMOUNT = 'refund_order_amount';
    const REFUND_PROMOTION = 'refund_promotion';
    const REFUND_LOGISTICS = 'refund_logistics';
    const OTHER_SPECIAL_SERVICE_FEE = 'other_special_service_fee';
    const OTHER_STORAGE_FEE = 'other_storage_fee';
    const OTHER_TAX = 'other_tax';
    const ORDER_NORMAL = 'order_normal';
    const ORDER_RMA = 'order_rma';
    const ORDER_MARGIN_DEPOSIT = 'order_margin_deposit';
    const ORDER_MARGIN_TAIL = 'order_margin_tail';
    const ORDER_PLATFORM_FEE_DETAIL = 'order_platform_fee_detail';
    const ORDER_INCENTIVE_REBATE = 'order_incentive_rebate';
    const ORDER_FUTURES_MARGIN_DEPOSIT = 'order_futures_margin_deposit';
    const ORDER_FUTURES_TO_SPOT_MARGIN_DEPOSIT = 'order_futures_to_spot_margin_deposit';
    const ORDER_FUTURES_MARGIN_DETAIL = 'order_futures_margin_tail';
    const REFUND_RMA = 'refund_rma';
    const REFUND_RESHIPMENT = 'refund_reshipment';
    const REFUND_INCENTIVE_REBATE = 'refund_incentive_rebate';
    const OTHER_SPECIAL_SERVICE = 'other_special_service';
    const OTHER_STORAGE = 'other_storage';
    const OTHER_TAX_DETAIL = 'other_tax_detail';
    const OTHER_FUTURES_MARGIN_DETAIL = 'other_futures_margin_detail';
    const V2_ORDER_NORMAL = 'V2_order_normal';

    static $settlement_status = [
        0 => 'in process',
        1 => 'Verifying settlement',
        2 => 'Settlement confirmed',
    ];
    static $settlement_items = [
        1 => 'Order',
        2 => 'Refund',
        3 => 'Other service fees',
    ];

    static $settlement_items_v2 = [
        32 => 'Revenue',
        33 => 'Expenses',
        34 => 'Other fees',
        36 => 'Supply chain overhead costs',
    ];

    static $special_service_fee_file_fields = [
        'annex1', 'annex2', 'annex3', 'annex4', 'annex5',
    ];

    public $currency_name;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->currency_name = session('currency');
    }

    /**
     * @param int $seller_id
     * @return array
     */
    public function getTotalBills(int $seller_id): array
    {
        $res = $this->orm->table('tb_seller_bill as sb')
            ->where('sb.seller_id', $seller_id)
            ->where('sb.start_date', '>=', '2020-02-01 00:00:00')
            ->orderBy('sb.start_date', 'desc')
            ->get();
        $ret = [];
        $res->map(function ($item) use (&$ret) {
            $item = get_object_vars($item);
            $ret[$item['id']]['name'] = dprintf(
                '{0} ~ {1} ({2})',
                $item['start_date'] . ' ' . getPSTOrPDTFromDate($item['start_date']),
                $item['end_date'] . ' ' . getPSTOrPDTFromDate($item['end_date']),
                static::$settlement_status[$item['settlement_status']] ?? 'UNKNOWN'
            );
            $ret[$item['id']]['version']=$item['program_code'];
        });

        return $ret;
    }

    /**
     * 获取结算账单总数
     * @param int $customer_id
     * @param array $data
     * @return int
     */
    public function queryBillDetailTotal(int $customer_id, array $data): int
    {
        return $this->buildBillDetailQuery($customer_id, $data)->count();
    }

    /**
     * @param int $customer_id
     * @param array $data
     * @return array
     */
    public function queryBillDetailList(int $customer_id, array $data): array
    {

        $res = $this->buildBillDetailQuery($customer_id, $data)
            ->select(['sbd.*', 'ro.rma_order_id', 'sbt.is_revenue','o.delivery_type','ssf.fee_number','sbd.freight','sbd.package_fee', 'ssf.inventory_id', 'ssf.annexl_menu_id'])
            ->selectRaw('(sbd.freight+sbd.package_fee) as logistics_fee')
            ->when(isset($data['page']) && isset($data['per_page']), function (Builder $q) use ($data) {
                $q->forPage($data['page'] ?? 1, $data['per_page'] ?? 15);
            })
            ->get();
        $customer = $this->orm->table('oc_customer')->where('customer_id', $customer_id)->first();
//        $logistics_customer_name = $this->orm->table('oc_customer')->where('customer_id', $customer_id)->value('logistics_customer_name');
        $bill = $this->orm->table('tb_seller_bill')->select('start_date', 'end_date')->where(['id' => $data['filter_settlement_cycle']])->first();
        $settlement_cycle = substr($bill->start_date, 0, 10) . '-' . substr($bill->end_date, 0, 10);
        $res = $res->map(function ($item) use ($data, $customer, $settlement_cycle) {
            $item = get_object_vars($item);
            // 总金额
            $item['total_s'] = $this->currency->formatCurrencyPrice($item['total'], $this->session->data['currency']);
            $item['product_total'] = bcsub($item['total'], $item['freight'], 2);
            $item['product_total'] = bcsub($item['product_total'], $item['service_fee'], 2);
            $item['product_total'] = bcsub($item['product_total'], $item['package_fee'], 2);
            $item['product_total'] = $this->currency->formatCurrencyPrice($item['product_total'], $this->session->data['currency']);
            $item['logistics_customer_name'] = $customer->firstname . $customer->lastname;
            $item['settlement_cycle'] = $settlement_cycle;
            // 物流费
            $item['logistics_fee_format'] = $this->currency->formatCurrencyPrice(bcadd($item['freight'], $item['package_fee']), $this->session->data['currency']);
            if ($item['order_id'] && $item['product_id']) {
                $item['quantity'] = $this->getOrderQuantity($item['order_id'], $item['product_id']);
            }
            if ($item['rma_id'] && $item['product_id']) {
                $item['quantity'] = $this->getRMAOrderQuantity($item['rma_id'], $item['product_id']);
            }
            $this->handleSettlementItem($item);

            // 根据订单，返金，其他服务项目的不同处理item；
            bcscale(2);
            if ($item['program_code'] == 'V2') {
                if (in_array($data['filter_settlement_item'], [34, 36])) {
                    $this->handleOtherItem($item);
                } else {
                    $this->handleSettlementItem($item);
                }
                if( $item['is_revenue']){

                }
            } else {
                switch ($data['filter_settlement_item']) {
                    case 1:
                    case 32:
                        $this->resolveOrderItem($item);
                        break;
                    case 2:
                        $this->resolveRefundItem($item);
                        break;
                    case 3:
                        $this->resolveOtherItem($item);
                        break;
                    default:
                        break;
                }
            }

            return $item;
        });
        return $res->toArray();
    }



    public function getOrderQuantity($order_id, $product_id)
    {
        return $this->orm->table('oc_order_product')
            ->where(['order_id' => $order_id, 'product_id' => $product_id])
            ->value('quantity');
    }

    public function getRMAOrderQuantity($rma_id, $product_id)
    {
        $quantity = $this->orm->table('oc_yzc_rma_order_product as op')
            ->select(['op.quantity', 'ro.order_type', 'ro.from_customer_order_id','ro.order_id','ro.buyer_id'])
            ->leftJoin('oc_yzc_rma_order as ro', 'ro.id', '=', 'op.rma_id')
            ->where(['op.rma_id' => $rma_id, 'op.product_id' => $product_id])
            ->first();
        if (empty($quantity)) {
            return 0;
        }
        if ($quantity->order_type == 2) {
            return $quantity->quantity;
        }
        $qty = $this->orm->table('tb_sys_customer_sales_order as so')
            ->leftJoin('tb_sys_order_associated as oa', 'so.id', '=', 'oa.sales_order_id')
            ->where(['so.order_id' => $quantity->from_customer_order_id, 'oa.product_id' => $product_id, 'oa.buyer_id' => $quantity->buyer_id])
            ->value('oa.qty');
        $rma_ids = $this->orm->table('oc_yzc_rma_order as ro')
            ->select('ro.id')
            ->leftJoin('oc_yzc_rma_order_product as op', 'ro.id', '=', 'op.rma_id')
            ->where(['ro.from_customer_order_id' => $quantity->from_customer_order_id, 'ro.order_id' => $quantity->order_id, 'op.product_id' => $product_id])
            ->orderBy('ro.processed_date', 'ASC')
            ->get()->pluck('id')->toArray();
        if (in_array($rma_id, $rma_ids) && $rma_ids[0] == $rma_id) {
            return $qty;
        }
        return 0;

    }
    /**
     * @param int $customer_id
     * @param array $data
     * @return Builder
     */
    public function buildBillDetailQuery(int $customer_id, array $data): Builder
    {
        $contractIds = [];
        $futureMarginIds = [];
        if (isset($data['filter_order_id']) && !empty($data['filter_order_id'])) {
            $contractIds = $this->orm->table('oc_futures_contract')->where('contract_no', 'like', '%' . $data['filter_order_id'] . '%')->pluck('id')->toArray();
            $futureMarginIds = $this->orm->table('oc_futures_margin_agreement')->where('agreement_no', 'like', '%' . $data['filter_order_id'] . '%')->pluck('id')->toArray();
        }

        return $this->orm
            ->table('tb_seller_bill_detail as sbd')
            ->leftJoin('tb_special_service_fee as ssf', 'ssf.id', '=', 'sbd.special_id')
            ->leftJoin('oc_yzc_rma_order as ro', 'sbd.rma_id', '=', 'ro.id')
            ->leftJoin('tb_seller_bill_type as sbt', 'sbt.type_id', '=', 'sbd.type')
            ->leftJoin('oc_order as o','o.order_id','=','sbd.order_id')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'sbd.product_id')
            ->where([
                'sbd.seller_id' => $customer_id,
                'sbd.header_id' => $data['filter_settlement_cycle'] ?? 0,
            ])
            ->whereIn('sbd.type', $this->getChildBillTypeIds((int)$data['filter_settlement_item']))
            ->when(isset($data['filter_order_id']) && !empty($data['filter_order_id']), function (Builder $q) use ($data, $contractIds, $futureMarginIds) {
                $q_data = '%' . trim($data['filter_order_id']) . '%';
                $q->where(function (Builder $q) use ($q_data, $contractIds, $futureMarginIds) {
                    $q->orWhere('sbd.order_id', 'like', $q_data);
                    $q->orWhere('ro.rma_order_id', 'like', $q_data);
                    $q->orWhere('ssf.fee_number', 'like', $q_data);
                    $q->orWhere('sbd.agreement_id', 'like', $q_data);
                    if (!empty($contractIds)) {
                        $q->orWhereIn('sbd.future_contract_id', $contractIds);
                    }
                    if (!empty($futureMarginIds)) {
                        $q->orWhereIn('sbd.future_margin_id', $futureMarginIds);
                    }
                });
            })
            ->when(isset($data['filter_sku_mpn']) && !empty($data['filter_sku_mpn']), function (Builder $q) use ($data) {
                $q->where(function (Builder $q) use ($data) {
                    $q->orWhere('p.sku', 'like', '%' . trim($data['filter_sku_mpn']) . '%');
                    $q->orWhere('p.mpn', 'like', '%' . trim($data['filter_sku_mpn']) . '%');
                });
            })
            ->when(isset($data['sort']) && !empty($data['sort']), function (Builder $q) use ($data) {
                return $q->orderBy($data['sort'], $data['order']);
            }, function (Builder $q) {
                return $q->orderBy('sbd.produce_date', 'desc');
            });
    }

    public function handleSettlementItem(&$item)
    {
        $item['logistics_fee'] = bcsub(0, $item['logistics_fee']);
        $item['logistics_fee_format'] = $this->currency->formatCurrencyPrice(bcadd($item['freight'], $item['package_fee']), $this->session->data['currency']);
        $bill_type = $this->getBillType($item['type']);
        $item['type_name'] = ($this->language->get('bill_type'))[$bill_type['code']] ?? '';
        if ($item['special_id']) {
            $info = $this->getSpecFee($item['special_id']);
            // 账单类型添加小项名称
            if ($info && $info['service_project_english']) {
                $item['type_name'] = $item['type_name'] .
                    '</br>  ' .
                    '<p style="font-size: 12px;color: rgba(0,0,0,0.55)">' . $info['service_project_english'] . '</p>';
            }
        }
        $item['type_name_son'] = '';// 下载用
        $item['item_code_s'] = 'N/A';
        $item['mpn_s'] = '';
        $item['order_num_s'] = $item['order_id']; // 下载用
        $item['relate_order_num_s'] = $item['agreement_id'] ? $item['agreement_id'] : 'N/A'; // 下载用
        if ($item['order_id']) {
            $item['ord_num'] =  $item['order_id'];
            $item['is_rebate'] = $this->checkOrderIsRebate((int)$item['order_id'], (int)($item['product_id'] ?? 0)) ? 1 : 0;
            $order_url = $this->url->link('account/customerpartner/orderinfo', ['order_id' => $item['order_id']]);
            $prefix = $item['is_margin'] == 1 ? '<i class="giga icon-margin flag-margin-order"></i>' : '';
            // 如果是期货，显示期货icon
            if (in_array($item['type'], [60, 61, 62])) {
                $prefix = '<i class="giga icon-futures flag-margin-order"></i>';
            }
            $item['ord_num_url'] = $this->formatInfo($item['ord_num'], $order_url, $prefix);
        }
        // 现货处理
        if ($item['agreement_id']) {
            // 兼容老数据,tb_seller_bill_detail的agreement_id即代表期货的id，也代表现货id，当type为60,62代表期货
            if (in_array($item['type'], [60, 62])) {
                $id = $this->orm->table('oc_futures_margin_agreement')->where('agreement_no', $item['agreement_id'])->value('id');
                $url = $this->url->link('account/product_quotes/futures/sellerBidDetail', ['id' => $id]);
                $prefix = '<i class="giga icon-futures flag-margin-order"></i>';
            } else {
                $url = $this->url->link('account/product_quotes/margin_contract/view', ['agreement_id' => $item['agreement_id']]);
                $prefix = '<i class="giga icon-margin flag-margin-order"></i>';
            }
            $item['ord_num_url'] = $this->formatInfo($item['order_id'] .' ('. $item['agreement_id'].')', $url, $prefix);
            $item['ord_num'] = $item['agreement_id'];
        }
        // 返金处理
        if ($item['rebate_id']) {
            $url = $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementList', ['agreement_id' => $item['rebate_id']]);
            $agreement_no = $this->orm->table('oc_rebate_agreement')->where('id', $item['rebate_id'])->value('agreement_code');
            $item['ord_num_url'] = $this->formatInfo($agreement_no, $url);
            $item['ord_num'] = $agreement_no;
        }
        // 期货处理
        if ($item['future_margin_id']) {
            $url = $this->url->link('account/product_quotes/futures/sellerBidDetail', ['id' => $item['future_margin_id']]);
            $agreement = $this->orm->table('oc_futures_margin_agreement')->where('id', $item['future_margin_id'])->first();
            $agreement_no = $agreement->agreement_no;
            $prefix = '<i class="giga icon-futures flag-margin-order"></i>';
            $item['ord_num_url'] = $this->formatInfo($item['order_id'] . ' (' . $agreement_no . ')', $url, $prefix);
            $item['ord_num'] = $agreement_no;
            $item['quantity'] = $agreement->num;
            $advance_product_id = $this->orm->table('oc_futures_margin_process')->where('agreement_id', $item['future_margin_id'])->value('advance_product_id');
            $item['item_code'] = $this->orm->table('oc_product')->where('product_id', $advance_product_id)->value('sku');
        }
        if (in_array($item['type'], [63,64,65])) {
            $item['ord_num'] = $item['fee_number'];
        }
        if ($item['rma_order_id']) {
            $item['ord_num'] = $item['rma_order_id'] ?? $item['order_id'];
            $order_url = $this->url->link('account/customerpartner/rma_management/rmaInfo', ['rmaId' => $item['rma_id']]);
            $prefix = $item['is_margin'] == 1 ? '<i class="giga icon-margin flag-margin-order"></i>' : '';
            $item['ord_num_url'] = $this->formatInfo($item['ord_num'], $order_url, $prefix);
        }
        if ($item['item_code']) {
            $product = $this->getProductByItemCode((int)$item['seller_id'], $item['item_code']);
            $tags = $product ? $this->getProductTags($product->product_id) : [];
            $item['item_code_s'] = $this->formatInfo($item['item_code'], null, '', !empty($tags) ? ' ' . join('&nbsp;', $tags) : '');
            $item['mpn_s'] = $product->mpn;
        }

    }

    // 其他费用
    protected function handleOtherItem(&$item)
    {
        // 交易类型
        $bill_type = $this->getBillType($item['type']);
        $item['type_name'] = ($this->language->get('bill_type'))[$bill_type['code']] ?? '';
        $item['type_name_d'] = $item['type_name']; // 下载用
        $item['spec_info'] = [];
        $item['ord_num'] = 'N/A';
        $item['remark_s'] = 'N/A';
        $item['show_remark_detail']=0;
        $item['show_charge_detail'] = 0;
        if ($item['special_id']) {
            $info = $this->getSpecFee($item['special_id']);
            $item['spec_info'] = $info ?: [];
            // 账单类型添加小项名称
            if ($info) {
                if ($info['service_project_english']) {
                    $item['type_name'] = $item['type_name'] .
                        '</br> ' .
                        '<p style="font-size: 12px;color: rgba(0,0,0,0.55)">' . $info['service_project_english'] . '</p>';
                }

                if ($info['remark']) {
                    $remark_info = truncate($info['remark'], 20);
                    $item['show_remark_detail'] = ($remark_info === $info['remark']) ? 0 : 1;
                    $item['remark_s'] = $remark_info;
                }
                $info['remark'] = $info['remark'] ?: '';
                $item['spec_info_charge_detail'] = $info['charge_detail'];
                if (in_array($item['type'], [23, 25, 63, 65, 66, 67, 68, 69, 70, 72, 75])) {
                    if ($info['charge_detail']) {
                        $item['show_charge_detail'] = 1;
                    }
                    $item['ord_num'] = $info['fee_number'] ?? '';
                }
                $item['type_name_son'] = $info['service_project_english'] ?? '';// 下载用
            }
        }
        if (in_array($item['type'], [66, 67, 72, 75]) && !$item['special_id']) {
            if ($item['logistics_customer_name']) {
                $item['ord_num'] = $item['logistics_customer_name'] . '-' . $item['type_name'];
            } else {
                $item['ord_num'] = $item['type_name'];
            }
        }
        if (in_array($item['type'], [62, 71, 75])) { //期货保证金相关
            $item['show_remark_detail'] = 0;
            // 兼容老数据
            if ($item['type'] == 62 && $item['agreement_id']) {
                $agreement = $this->orm->table('oc_futures_margin_agreement')->where('agreement_no', $item['agreement_id'])->first();
            }
            if (in_array($item['type'], [71, 75]) && $item['future_margin_id']) {
                $agreement = $this->orm->table('oc_futures_margin_agreement')->where('id', $item['future_margin_id'])->first();
            }
            if (isset($agreement)) {
                if ($agreement->contract_id) {
                    $url = $this->url->link('account/product_quotes/futures/sellerFuturesBidDetail', ['id' => $agreement->id]);
                } else {
                    $url = $this->url->link('account/product_quotes/futures/sellerBidDetail', ['id' => $agreement->id]);
                }
                $prefix = '<i class="giga icon-futures flag-margin-order"></i>';
                $item['ord_num_url'] = $this->formatInfo($agreement->agreement_no, $url, $prefix);
                $item['ord_num'] = $agreement->agreement_no;
            }

            $item['type_name_son'] = $item['type_name'];
        }
        // 期货合约
        if($item['future_contract_id']){
            $contract = $this->orm->table('oc_futures_contract')->where('id', $item['future_contract_id'])->first();
            if (!$contract->is_deleted) {
                $url = $this->url->link('account/customerpartner/future/contract/tab', ['id' => $item['future_contract_id']]);
            } else {
                $url = null;
            }
            $prefix = '<i class="giga icon-futures flag-margin-order"></i>';
            $item['ord_num_url'] = $this->formatInfo($contract->contract_no, $url, $prefix);
            $item['ord_num'] = $contract->contract_no;
        }
        $this->handlePatchUrl($item);
    }

    public function handlePatchUrl(&$item)
    {
        $arr = [
            66 => 'downloadLogistics',
            67 => 'downloadStorage',
            72 => 'downloadInterest',
            75 => 'downloadPlatform'
        ];
        $item['patch_files'] = 'N/A';
        // 判断是自动计算还是手动计算
        if ($item['special_id'] || $item['file_menu_id']) {
            $patch_url = [];
            $fileList = app(DealSettlement::class)->getDetailFiles($item['inventory_id'], $item['annexl_menu_id'], $item['file_menu_id']);
            if ($fileList) {
                foreach ($fileList as $ii) {
                    $patch_url[] = $this->formatInfo(
                        truncate($ii['file_name'], 15),
                        url()->to(['account/seller_bill/bill_detail/downloadDetail', 'menu_detail_id' => $ii['id'], 'detail_id' => $item['id']]),
                        '',
                        '',
                        'a-download',
                        ['title' => $ii['file_name']]
                    );
                }
            }

            $item['patch_files'] = join('<br>', $patch_url);
        } else if (in_array($item['type'], array_keys($arr))&&empty($item['future_margin_id'])) {
            $file_name=$item['ord_num'].'('.$item['settlement_cycle'].').xls';
            $url = $this->url->link('account/seller_bill/bill_detail/' . $arr[$item['type']],
                [
                    'bill_id' => $item['header_id'],
                    'bill_detail_id' => $item['id'],
                    'file_name' =>$file_name,
                ]);
            // 如果是物流费，文件是java生成的，
            if ($item['type'] == 66) {
                $url = $this->url->link('account/seller_bill/bill_detail/download_patch',
                    [
                        'file_path' =>  $item['seller_id'] . '/' . str_replace('&','and', $file_name),
                    ]);
            }
            $item['patch_files'] = $this->formatInfo($file_name, $url, '', '', 'a-download', ['title' => $item['ord_num']]);
        }

    }


    // 订单处理
    protected function resolveOrderItem(&$item)
    {
        $item['logistics_fee'] = bcsub(0, $item['logistics_fee']);
        $item['logistics_fee_format'] = $this->currency->formatCurrencyPrice($item['logistics_fee'], $this->currency_name);
        // 交易类型
        $bill_type = $this->getBillType($item['type']);
        $item['type_name'] = ($this->language->get('bill_type'))[$bill_type['code']] ?? '';
        // 加入返点信息
        $item['is_rebate'] = 0;
        // 单号
        switch ($bill_type['code']) {
            case static::ORDER_FUTURES_MARGIN_DEPOSIT: // 期货保证金订金订单
            case static::ORDER_FUTURES_TO_SPOT_MARGIN_DEPOSIT: // 期货保证金转现货定金订单
            case static::ORDER_FUTURES_MARGIN_DETAIL: // 期货保证金尾款
            case static::ORDER_MARGIN_DEPOSIT: // 保证金订金订单
            case static::ORDER_MARGIN_TAIL: // 保证金尾款订单
            case static::ORDER_NORMAL:   // 普通订单
            {
                // 纯单号
                $item['order_num_s'] = $item['order_id'];
                // 返点信息加入
                $item['is_rebate'] = $this->checkOrderIsRebate(
                    (int)$item['order_id'],
                    (int)($item['product_id'] ?? 0)
                ) ? 1 : 0;
                // 相关单号 start
                $item['relate_order_num_s'] = (!empty($item['agreement_id'])) ? $item['agreement_id'] : 'N/A';
                // 相关单号 end
                $item['ord_num'] = $item['order_id'];
                if (!empty($item['agreement_id'])) {
                    $item['ord_num'] = $item['order_id'] . '(' . ($item['agreement_id'] ?? '') . ')';
                }
                $order_url = $this->url->link('account/customerpartner/orderinfo', ['order_id' => $item['order_id']]);

                $prefix = $item['is_margin'] == 1 ? '<i class="giga icon-margin flag-margin-order"></i>' : '';
                if (in_array($item['type'], [28, 29,30])) {
                    $prefix = '<i class="giga icon-futures flag-margin-order"></i>';
                }
                $item['ord_num_url'] = $this->formatInfo($item['ord_num'], $order_url, $prefix);
                break;
            }
            case static::ORDER_RMA:     // 重发单
            {
                // 纯单号
                $item['order_num_s'] = $item['rma_order_id'] ?? '';
                // 相关单号 start
                $item['relate_order_num_s'] = 'N/A';
                // 相关单号 end
                $item['ord_num'] = $item['rma_order_id'] ?? '';
                $order_url = $this->url->link('account/customerpartner/rma_management/rmaInfo', ['rmaId' => $item['rma_id']]);
                $prefix = $item['is_margin'] == 1 ? '<i class="giga icon-margin flag-margin-order"></i>' : '';
                $item['ord_num_url'] = $this->formatInfo($item['ord_num'], $order_url, $prefix);
                break;
            }
            case static::ORDER_INCENTIVE_REBATE: //动费用返点交易(供货商承担)
            {
                // 动费用返点交易 属于特殊费用
                // total 为负
                $item['total'] = bcsub(0, $item['total']);
                $item['total_s'] = $this->currency->formatCurrencyPrice($item['total'], $this->currency_name);
                // 查找是否上传附件
                $order_url = null;
                $spec_id = $item['special_id'];
                $spec_info = $this->getSpecFee($spec_id);
                // 单号
                $item['order_num_s'] = $item['ord_num'] = $spec_info ? $spec_info['fee_number'] : '';
                // 相关单号 start
                $item['relate_order_num_s'] = 'N/A';
                // 相关单号 end
                if ($this->checkSpecFeeFiles($spec_id)) {
                    $order_url = $this->url->link(
                        'account/seller_bill/bill_detail/download_spec_fee',
                        ['spec_ids' => $spec_id, 'item' => 1]
                    );
                }
                $item['ord_num_url'] = $this->formatInfo($item['ord_num'], $order_url);
                // 激励活动运费为N/A
                $item['logistics_fee_format'] = 'N/A';
                $item['logistics_fee'] = 'N/A';
                break;
            }
            default:
                break;
        }
        // item code 处理
        switch ($bill_type['code']) {
            case static::ORDER_FUTURES_MARGIN_DEPOSIT: // 期货保证金订金订单
            case static::ORDER_FUTURES_TO_SPOT_MARGIN_DEPOSIT: // 期货保证金转现货定金订单
            case static::ORDER_FUTURES_MARGIN_DETAIL: // 期货保证金尾款
            case static::ORDER_MARGIN_DEPOSIT: // 保证金订金订单
            case static::ORDER_MARGIN_TAIL: // 保证金尾款订单
            case static::ORDER_NORMAL:   // 普通订单
            case static::ORDER_RMA:     // 重发单
            {
                $product = $this->getProductByItemCode((int)$item['seller_id'], $item['item_code']);
                $tags = $product ? $this->getProductTags($product->product_id) : [];
                $item['item_code_s'] = $this->formatInfo(
                    $item['item_code'],
                    null,
                    '',
                    !empty($tags) ? ' ' . join('&nbsp;', $tags) : ''
                );
                $item['mpn_s'] = $product->mpn;
                break;
            }
            case static::ORDER_INCENTIVE_REBATE: //动费用返点交易(供货商承担)
            {
                $item['item_code_s'] = 'N/A';
                $item['mpn_s'] = '';
                break;
            }
            default:
                break;
        }
    }



    // 返金处理
    protected function resolveRefundItem(&$item)
    {
        // 交易类型
        $bill_type = $this->getBillType($item['type']);
        $item['type_name'] = ($this->language->get('bill_type'))[$bill_type['code']] ?? '';
        $item['type_name_d'] = $item['type_name']; // 下载用
        // 单号
        switch ($bill_type['code']) {
            case static::REFUND_RMA:  //RMA Refund
            case static::REFUND_RESHIPMENT://Reshipment返金
            {
                // total 为负
                $item['total'] = bcsub(0, $item['total']);
                $item['total_s'] = $this->currency->formatCurrencyPrice($item['total'], $this->currency_name);
                // 单号
                $item['ord_num'] = $item['rma_order_id'] ?? '';
                $item['type_name_son'] = '';// 下载用
                $order_url = $this->url->link('account/customerpartner/rma_management/rmaInfo', ['rmaId' => $item['rma_id']]);
                $prefix = $item['is_margin'] == 1 ? '<i class="giga icon-margin flag-margin-order"></i>' : '';
                $item['ord_num_url'] = $this->formatInfo($item['ord_num'], $order_url, $prefix);
                break;
            }
            case static::REFUND_INCENTIVE_REBATE:
            {
                // 动费用返点交易 属于特殊费用
                // 查找是否上传附件
                $order_url = null;
                $spec_id = $item['special_id'];
                $spec_info = $this->getSpecFee($spec_id);
                // 单号
                $item['ord_num'] = $spec_info ? $spec_info['fee_number'] : '';
                $item['type_name_son'] = $spec_info['service_project_english'] ?? '';// 下载用
                // 账单类型添加小项名称
                if (!empty($spec_info['service_project_english'])) {
                    $item['type_name'] = $item['type_name'] .
                        '</br>' .
                        '<p style="font-size: 12px;color: rgba(0,0,0,0.55)">' . $spec_info['service_project_english'] . '</p>';
                }
                if ($this->checkSpecFeeFiles($spec_id)) {
                    $order_url = $this->url->link(
                        'account/seller_bill/bill_detail/download_spec_fee',
                        ['spec_ids' => $spec_id, 'item' => 2]
                    );
                }
                $item['ord_num_url'] = $this->formatInfo($item['ord_num'], $order_url);
                $item['logistics_fee_format'] = 'N/A';
                break;
            }
            default:
                break;
        }
        // item code
        switch ($bill_type['code']) {
            case static::REFUND_RMA:  //RMA Refund
            case static::REFUND_RESHIPMENT://Reshipment返金
            {
                // 返金的item code sku 从rma中获取
                $this->load->model('customerpartner/rma_management');
                $rma_info = $this->model_customerpartner_rma_management->getRmaInfoByRmaId($item['rma_id'] ?? 0);
                if (count($rma_info) === 0) {
                    $item['item_code_s'] = '';
                    $item['item_code'] = '';
                    $item['mpn_s'] = '';
                } else {
                    $pro = $rma_info[0];
                    $tags = $this->getProductTags($pro['product_id']);
                    $item['item_code_s'] = $this->formatInfo(
                        $pro['sku'] ?? '',
                        null,
                        '',
                        !empty($tags) ? ' ' . join('&nbsp;', $tags) : ''
                    );
                    $item['mpn_s'] = $pro['mpn'];
                }
                break;
            }
            case static::REFUND_INCENTIVE_REBATE:
            {
                // 下面适用于页面显示
                $item['item_code_s'] = 'N/A';
                $item['mpn_s'] = '';
                // 下面适用于输出csv时候使用
                $item['item_code'] = 'N/A';
                $item['logistics_fee'] = 'N/A';
                break;
            }
            default:
                break;
        }

    }

    // 其他费用
    protected function resolveOtherItem(&$item)
    {
        // 交易类型
        $bill_type = $this->getBillType($item['type']);
        $item['type_name'] = ($this->language->get('bill_type'))[$bill_type['code']] ?? '';
        $item['type_name_d'] = $item['type_name']; // 下载用
        $spec_id = $item['special_id'] ?? 0;
        $info = $this->getSpecFee($spec_id);
        $item['spec_info'] = $info ?: [];
        $item['type_name_son'] = $info['service_project_english'] ?? '';// 下载用
        // 单号
        $item['ord_num'] = $info['fee_number'] ?? '';
        // 账单类型添加小项名称
        if (!empty($info['service_project_english'])) {
            $item['type_name'] = $item['type_name'] .
                '</br>' .
                '<p style="font-size: 12px;color: rgba(0,0,0,0.55)">' . $info['service_project_english'] . '</p>';
        }
        // 备注
        $info['remark'] = $info['remark'] ?: '';
        $remark_info = truncate($info['remark'], 20);
        $show_remark_detail = ($remark_info === $info['remark']) ? 0 : 1;
        $item['remark_s'] = $remark_info;
        $item['show_remark_detail'] = $show_remark_detail;
        // 备注end
        switch ($bill_type['code']) {
            case static::OTHER_SPECIAL_SERVICE:
            case static::OTHER_TAX_DETAIL:
            {
                // 处理上传文件附件
                $item['show_charge_detail'] = 1;
                $patch_url = [];
                $files=$this->getSpecFeeFiles($spec_id);
                foreach ($files as $file) {
                        $patch_url[] = $this->formatInfo(
                            truncate($file->file_name, 15),
                            $this->url->link(
                                'account/seller_bill/bill_detail/download_patch',
                                ['id' => $file->id]
                            ),
                            '',
                            '',
                            'a-download',
                            ['title' => $file->file_name]
                        );
                }
                $item['patch_files'] = join('<br>', $patch_url);
                break;
            }
            case static::ORDER_PLATFORM_FEE_DETAIL:  //平台费
            {
                $item['ord_num'] = 'N/A';
                $item['show_charge_detail'] = 0;
                $item['patch_files'] = 'N/A';
                $item['remark_s'] = 'N/A';
                break;
            }
            case static::OTHER_STORAGE: // 仓储
            {
                $item['ord_num'] = 'N/A';
                $item['show_charge_detail'] = 0;
                $item['patch_files'] = 'N/A';
                break;
            }
            case  static::OTHER_FUTURES_MARGIN_DETAIL: //期货保证金相关
            {
                $agreement_id = 0;
                if ($item['agreement_id']) {
                    $agreement_id = $this->orm->table('oc_futures_margin_agreement')->where('agreement_no', $item['agreement_id'])->value('id');
                }
                $url = $this->url->link('account/product_quotes/futures/sellerBidDetail', ['id' => $agreement_id]);
                $prefix = '<i class="giga icon-futures flag-margin-order"></i>';
                $item['ord_num_url'] = $this->formatInfo($item['agreement_id'], $url, $prefix);
                $item['type_name_son'] = $item['agreement_id'];
                $item['ord_num'] = $item['agreement_id'];
                $item['type_name_son'] = $item['type_name'];
                $item['show_charge_detail'] = 0;
            }
        }
        $item['spec_info_charge_detail'] = $info['charge_detail'] ?? '';
    }


    /**
     * 获取父类id对应的所有子id
     * @param int $parent_id 父id
     * @param array $level_ids 层级id null:获取所有子id，array:获取指定层级id
     * @return array
     */
    public function getChildBillTypeIds(int $parent_id = 0, array $level_ids = null): array
    {
        $tArr = $this->getTotalSellerBillTypeList();
        // 循环递归查询子id算法
        $tIds = [$parent_id];
        $ret = [];
        while (count($tIds) > 0) {
            $tempId = array_pop($tIds);
            foreach ($tArr as $id => $item) {
                if ($item->parent_type_id == $tempId) {
                    array_push($tIds, $id);
                    // 如果层级为null 或者 层级id在指定level_id中
                    if ($level_ids === null || in_array($item->rank_id, $level_ids)) {
                        array_push($ret, $id);
                    }
                }
            }
        }

        return $ret;
    }

    /**
     * 获取所有type
     * @return Collection
     * user：wangjinxin
     * date：2020/1/10 17:11
     */
    public function getTotalSellerBillTypeList()
    {
        static $tArr = [];
        static $isSave = false;
        if (!$isSave) {
            $tArr = $this->orm
                ->table('tb_seller_bill_type')
                ->where('status', 1)
                ->get()
                ->keyBy('type_id');
            $isSave = true;
        }
        return $tArr;
    }

    /**
     * @param int $type_id
     * @return array
     */
    public function getBillType(int $type_id): array
    {
        $type_lists = $this->getTotalSellerBillTypeList();
        return isset($type_lists[$type_id]) ? get_object_vars($type_lists[$type_id]) : [];
    }

    /**
     * 获取某个指定周期id对应的特殊服务费用
     * @param int $settlement_id 周期id
     * @return array
     */
    public function getSpecialServiceFee(int $settlement_id): array
    {
        $bill_info = $this->orm->table('tb_seller_bill')->where('id', $settlement_id)->first();
        if (!$bill_info) return [];
        return $this->orm
            ->table('tb_special_service_fee as ssf')
            ->select(['ssf.*', 'cdm.code'])
            ->leftJoin(
                'tb_service_fee_category_detail_map as cdm',
                'ssf.service_project',
                '=',
                'cdm.category_detail_id'
            )
            ->where([
                'ssf.customer_id' => $bill_info->seller_id,
                'ssf.accounting_cycle_start' => $bill_info->start_date,
                'ssf.accounting_cycle_end' => $bill_info->end_date,
            ])
            ->get()
            ->map(function ($item) {
                return get_object_vars($item);
            })
            ->toArray();
    }

    /**
     * 判断某个special fee是否有上传文件
     * @param int $spec_id
     * @return bool
     */
    public function checkSpecFeeFiles(int $spec_id): bool
    {
        $info = $this->getSpecFee($spec_id);
        $hasUploadedFile = false;
        if (!empty($info)) {
            foreach (static::$special_service_fee_file_fields as $field) {
                if (!empty($info[$field])) {
                    $hasUploadedFile = true;
                    break;
                }
            }
        }
        return $hasUploadedFile;
    }

    /**
     * @param int $spec_id
     * @return array|null
     */
    public function getSpecFee(int $spec_id)
    {
        $info = $this->orm
            ->table('tb_special_service_fee as ssf')
            ->select(['ssf.*', 'scd.service_project_english'])
            ->leftJoin(
                'tb_service_fee_category_detail as scd',
                'scd.id',
                '=',
                'ssf.service_project'
            )
            ->where('ssf.id', $spec_id)
            ->first();
        return $info ? get_object_vars($info) : null;
    }

    /**
     * 临时处理 - 由于JAVA文件保存方式发生改变，临时解决
     *
     * @param int $inventoryId InventoryId
     * @param int $customerId 用户ID
     * @return FileDTO[]|Collection|string
     */
    private function getFileInventory($inventoryId, $customerId)
    {
        $files = '';
        $inventory = app(SellerInventoryAdjustRepository::class)->getInventoryAdjustById($inventoryId, $customerId);
        if ($inventory && ($inventory->apply_file_menu_id || $inventory->confirm_file_menu_id)) {
            if ($inventory->apply_file_menu_id) {
                $filesOne = RemoteApi::file()->getByMenuId($inventory->apply_file_menu_id);
                if ($filesOne->isNotEmpty()) {
                    $files = $filesOne;
                }
            }
            if ($inventory->confirm_file_menu_id) {
                $filesTwo = RemoteApi::file()->getByMenuId($inventory->confirm_file_menu_id);
                if ($filesTwo->isNotEmpty()) {
                    if ($files) {
                        $files = $files->merge($filesTwo);
                    } else {
                        $files = $filesTwo;
                    }
                }
            }
        }

        return $files;
    }

    public function getSpecFeeFiles($spec_id)
    {
        return $info = $this->orm
            ->table('tb_special_service_fee_file')
            ->select(['id', 'file_name', 'file_path'])
            ->where(['header_id' => $spec_id, 'delete_flag' => 0])
            ->get();
    }

    /**
     * @param int $customer_id
     * @param string $item_code
     * @return \Illuminate\Database\Eloquent\Model|Builder|object|null
     * user：wangjinxin
     * date：2020/1/14 16:26
     */
    protected function getProductByItemCode(int $customer_id, string $item_code = null)
    {
        if (empty($item_code)) return null;
        return $this->orm
            ->table('oc_product as p')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->where(function (Builder $q) use ($item_code) {
                return $q->orWhere('p.sku', $item_code)
                    ->orWhere('p.mpn', $item_code);
            })
//            ->where('ctp.customer_id', $customer_id)
            ->first();
    }

    /**
     * 获取商品tag
     * @param int $product_id
     * @return array
     * user：wangjinxin
     * date：2020/1/14 16:21
     */
    protected function getProductTags(int $product_id): array
    {
       /* $tags = [];*/
        $product_tags = $this->orm
            ->table('oc_product_to_tag')
            ->where('product_id', $product_id)
            ->orderBy('tag_id', 'asc')
            ->pluck('tag_id')
            ->toArray();
        // hard code 1-ltl 2-part 3-combo
        // 参照表oc_tag oc_product_to_tag

        $this->load->model('tool/image');
        $result = $this->orm->table('oc_tag')
            ->whereIn('tag_id',$product_tags)
            ->orderBy('tag_id','asc')
            ->get()->map(function ($item){
                $img_url = $this->model_tool_image->getOriginImageProductTags($item->icon);
                return  '<a data-toggle="tooltip" style="display: inline;" title="'.$item->description.'">' .
                    '<img src="'.$img_url.'"  class="'.$item->class_style.'"></a>';
            })->toArray();

        //原始代码，先留着，参考里面的顺序
       /* in_array(1, $product_tags) && $tags[] = '<i class="giga icon-oversized" style="color: red"></i>';
        in_array(2, $product_tags) && $tags[] = '<i class="giga icon-partx" style="color: #5AD0FE"></i>';
        in_array(3, $product_tags) && $tags[] = '<i class="giga icon-combox" style="color: #6dd400"></i>';
        return $tags;*/
       return $result ;
    }

    /**
     *
     * 校验一个订单是否参加参与返点
     * @param int $order_id
     * @param int $product_id
     * @return bool
     * user：wangjinxin
     * date：2020/2/24 17:33
     *
     * @see ModelAccountProductQuotesRebatesAgreement::checkIsRebate()
     */
    private function checkOrderIsRebate(int $order_id, int $product_id): bool
    {
        return $this->orm->table('oc_rebate_agreement_order')
            ->where([
                ['order_id', '=', $order_id],
                ['product_id', '=', $product_id],
                ['type', '=', 1]
            ])
            ->exists();
    }

    /**
     * @param string $title
     * @param string|null $url
     * @param string $prefix
     * @param string $suffix
     * @param string $url_class
     * @param array $other_options
     * @return string
     */
    private function formatInfo(
        string $title = null,
        string $url = null,
        string $prefix = '',
        string $suffix = '',
        string $url_class = '',
        array $other_options = []
    ): string
    {
        $info = $title ? $title : '';
        if (!empty($url)) {
            $option = '';
            array_map(function ($item, $key) use (&$option) {
                $item = !empty($item) ? $item : '';
                $option .= " {$key}='{$item}' ";
            }, $other_options, array_keys($other_options));
            $info = dprintf(
                "<a href='{url}' class='{class}' target='_blank' {options}>{title}</a>",
                ['url' => $url, 'class' => $url_class, 'title' => $info, 'options' => $option]
            );
        }
        return $prefix . $info . $suffix;
    }

}
