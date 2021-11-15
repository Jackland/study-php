<?php

use App\Enums\Order\OcOrderStatus;
use App\Enums\Product\ProductTransactionType;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Models\CWF\OrderCloudLogistics;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Repositories\Margin\MarginRepository;
use App\Repositories\Rebate\RebateRepository;
use App\Repositories\Rma\RamRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Class ModelAccountRmaManage
 *
 * @property ModelToolImage $model_tool_image
 * @property ModelAccountRmaManagement $model_account_rma_management
 * @property ModelCustomerpartnerRmaManagement $model_customerpartner_rma_management
 */
class ModelAccountRmaManage extends Model
{
    public function __construct($registry)
    {
        parent::__construct($registry);
        bcscale(2);
    }

    /**
     * @param int $customer_id
     * @param string $order_id
     * @return array|null
     */
    public function getCustomerOrdersByOrderId(int $customer_id, string $order_id): ?array
    {
        $res = CustomerSalesOrder::query()
            ->where('order_id', $order_id)
            ->where('buyer_id', $customer_id)
            ->first();

        return $res ? obj2array($res) : null;
    }

    /**
     * @param int $order_id
     * @param int $sales_order_id
     * @return array|null
     */
    public function getCWFInfo(int $order_id, int $sales_order_id)
    {
        $res = OrderCloudLogistics::query()
            ->where([
                'order_id' => $order_id,
                'sales_order_id' => $sales_order_id,
            ])
            ->first();

        return $res ? obj2array($res) : null;
    }

    /**
     * @param int $product_id
     * @return array|null
     */
    public function getProductInfo(int $product_id): ?array
    {
        $res = db('oc_product')->where('product_id', $product_id)->first();

        return $res ? (array)$res : null;
    }

    /**
     * @param int $customer_id
     * @param string $order_id
     * @param int $product_id
     * @return array
     */
    public function getSalesOrderInfo(int $customer_id, string $order_id, int $product_id): array
    {
        $europe_freight_product_list = json_decode($this->config->get('europe_freight_product_id'));
        return db('tb_sys_order_associated as soa')
            ->select([
                'soa.id', 'soa.sales_order_id', 'cso.order_id as customer_order_id',
                'soa.coupon_amount as soa_coupon_amount','soa.campaign_amount as soa_campaign_amount',
                'soa.order_id as order_id', 'soa.order_product_id', 'soa.qty', 'soa.product_id',
                'soa.seller_id', 'cso.orders_from', 'cso.order_status', 'p.sku', 'c2c.screenname as model',
                'o.delivery_type','op.type_id','op.agreement_id'
            ])
            ->selectRaw('case when cso.order_status = '.CustomerSalesOrderStatus::CANCELED.' then "Canceled" when cso.order_status = '.CustomerSalesOrderStatus::COMPLETED.' then "Complete" end as order_status_show')
            ->leftJoin('tb_sys_customer_sales_order as cso', 'soa.sales_order_id', '=', 'cso.id')
            ->leftJoin('oc_order_product as op', 'op.order_product_id', '=', 'soa.order_product_id')
            ->leftJoin('oc_order as o', 'o.order_id', '=', 'soa.order_id')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'soa.product_id')
            ->leftJoin('oc_customerpartner_to_customer as c2c', 'c2c.customer_id', '=', 'soa.seller_id')
            ->leftJoin('oc_product_description as pd', 'pd.product_id', '=', 'p.product_id')
            ->where([
                'cso.order_id' => $order_id,
                'soa.product_id' => $product_id,
                'cso.buyer_id' => $customer_id,
            ])
            ->whereNotIn('soa.product_id', $europe_freight_product_list)
            ->get()
            ->map(function ($item) {
                $item = (array)$item;
                $price_info = $this->getComputeOrderProductPrice($item['order_product_id']);
                $item = array_merge($item, $price_info);
                $item['can_return_quantity'] = 0;
                if ($item['order_status'] == CustomerSalesOrderStatus::CANCELED) {
                    $item['total'] = bcmul($item['unit_price_without_deposit'], $item['qty']);
                    $item['need_return_deposit'] = false;
                } else {
                    // 完成的销售订单可能会退还订金
                    $item['total'] = bcmul($item['unit_price'], $item['qty']);
                    $item['need_return_deposit'] = true;
                    // 完成的销售订单需要校验返还数量
                    $item['can_return_quantity'] = max((int)(
                        $item['qty'] - $this->getAppliedSalesOrderQuantity($item['customer_order_id'], $item['order_id'], $item['product_id'])
                    ), 0);
                }
                $this->checkRmaRebateInfo($item);

                //满减折扣&优惠券折扣
                $item['total_can_refund'] -= ($item['soa_campaign_amount'] + $item['soa_coupon_amount']);
                // 如果为[取消的]销售订单
                $rmaRepository = app(RamRepository::class);
                // 校验是否有可以退回的期货尾款仓租
                $marginStorageFeeIds = [];
                if ($item['type_id'] == ProductTransactionType::MARGIN) {
                    // 判断协议是否过期
                    $isExpired = app(MarginRepository::class)->checkAgreementIsExpired($item['agreement_id']);
                    if (!$isExpired) {
                        $marginStorageFeeIds = app(StorageFeeRepository::class)
                            ->getBindMarginRestStorageFeeByAssociated((int)$item['id']);
                    }
                }

                // 如果没有需要退回的期货尾款仓租，再校验
                if (!empty($marginStorageFeeIds)) {
                    $item['msg_storage_fee'] = $this->language->get('tip_margin_storage_fee');
                } elseif ( $rmaRepository->checkNeedReturnStorageFee((int)$item['id'])) {
                    // 仓租费信息展示
                    $calculateStorageFee = app(RamRepository::class)
                        ->getSalesOrderCalculateStorageFee($item['id']);
                    $item['msg_storage_fee'] = ($calculateStorageFee !== null)
                        ? sprintf(
                            $this->language->get('tip_storage_fee'),
                            $this->currency->format($calculateStorageFee, session('currency'))
                        )
                        : null;
                }
                return $item;
            })
            ->toArray();
    }

    private function checkRmaRebateInfo(array &$data)
    {
        //返点四期
        $rebateInfo = $this->model_customerpartner_rma_management->getRebateInfo($data['order_id'], $data['product_id']);
        $canRefundMoney = $data['total'];
        if (
            !empty($rebateInfo)
            && $data['order_status'] == CustomerSalesOrderStatus::CANCELED
            && app(RamRepository::class)->checkSalesOrderRmaFirstRefundByAssociateId($data['id'])
        ) {
            $rebateRefundInfo = app(RebateRepository::class)->checkRebateRefundMoney(
                $data['total'], $data['qty'], $data['order_id'], $data['product_id'], false
            );
            $data['tipBuyerMsg'] = $rebateRefundInfo['buyerMsg'];
            $refundRange = $rebateRefundInfo['refundRange'] ?? [];
            $canRefundMoney = $refundRange[$data['qty']] ?? $canRefundMoney;
        }
        $data['total_can_refund'] = $canRefundMoney;
    }

    /**
     * 获取采购订单排除掉申请rma的订单信息
     * @param string $order_id 采购订单id
     * @param int $product_id 商品id
     * @return array
     * @throws Exception
     */
    public function getPurchaseOrderInfo(string $order_id, int $product_id): array
    {
        $order = db('oc_order_product as op')
            ->select([
                'op.order_id', 'op.product_id', 'op.order_product_id', 'p.image', 'pd.name',
                'op.quantity', 'p.product_type', 'p.sku', 'o.customer_id as buyer_id', 'ctp.customer_id as seller_id',
                'op.campaign_amount','op.coupon_amount','op.type_id','op.agreement_id'
            ])
            ->leftJoin('oc_order as o', 'o.order_id', '=', 'op.order_id')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'op.product_id')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'op.product_id')
            ->leftJoin('oc_product_description as pd', 'p.product_id', '=', 'pd.product_id')
            ->where([
                'op.order_id' => $order_id,
                'op.product_id' => $product_id,
            ])
            ->first();
        $order = (array)$order;
        // 获取sales order绑定的数量
        $europe_freight_product_list = json_decode($this->config->get('europe_freight_product_id'));
        $binding_sales_order_num = db('tb_sys_order_associated')
            ->where(['order_id' => $order_id, 'product_id' => $product_id])
            ->whereNotIn('product_id', $europe_freight_product_list)
            ->sum('qty');
        // 获取已经申请rma 或者 rma已经同意的商品采购数量
        $rma_quantity = db('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'ro.id', 'rop.rma_id')
            ->where(['ro.order_id' => $order_id, 'rop.product_id' => $product_id])
            ->where('rop.status_refund', '<>', 2)
            ->where(['ro.order_type' => 2, 'ro.cancel_rma' => 0])
            ->sum('rop.quantity');
        // 当前所有数量
        $order['all_quantity'] = $order['quantity'];
        // 减去绑定 和 申请rma的数量
        $order['quantity'] = max($order['quantity'] - $binding_sales_order_num - $rma_quantity, 0);
        $price_info = $this->getComputeOrderProductPrice($order['order_product_id']);
        // 减去绑定 和 申请rma之后需要支付的钱
        $order['total'] = bcmul($price_info['unit_price_without_deposit'], $order['quantity']);
        // 全部需要支付的钱 和 all quantity对应
        $order['all_total'] = bcmul($price_info['unit_price_without_deposit'], $order['all_quantity']);

        //满减折扣&优惠券折扣
        $salesBindDiscount = app(RamRepository::class)->getSalesOrderBindInfo($order_id, $product_id);
        $phurseRmaDiscount = app(RamRepository::class)->getPhurseOrderRmaInfo($order_id, $order['order_product_id']);
        $order['soa_coupon_amount'] = $order['soa_campaign_amount'] = 0;
        $order['coupon_amount_show'] = $order['campaign_amount_show'] = 0;
        $order['discount_page_show'] = 0;
        if ($order['campaign_amount'] > 0) {
            $order['coupon_amount_show'] = $order['discount_page_show'] = 1;
            $tempCampaignAmount = $order['campaign_amount'] -
                $salesBindDiscount['all_sales_campaign_amount'] - $phurseRmaDiscount['all_phurse_campaign_amount'];
            $order['soa_campaign_amount'] = max($tempCampaignAmount, 0);
        }
        //优惠券
        if ($order['coupon_amount'] > 0) {
            $order['campaign_amount_show'] = 1;
            $order['discount_page_show'] = 1;
            $tempCampaignAmount = $order['coupon_amount'] -
                $salesBindDiscount['all_sales_coupon_amount'] - $phurseRmaDiscount['all_phurse_coupon_amount'];;
            $order['soa_coupon_amount'] = max($tempCampaignAmount, 0);
        }

        $order['total_after_discount'] = $order['total'] - ($order['soa_coupon_amount'] + $order['soa_campaign_amount']);
        //$order['total'] -= ($order['soa_coupon_amount'] + $order['soa_campaign_amount']);
        //$order['all_total'] -= ($order['soa_coupon_amount'] + $order['soa_campaign_amount']);

        $order = array_merge($order, $price_info);
        $order['need_return_deposit'] = false;
        return $order;
    }

    /**
     * 获取销售订单RMA详情
     * @param int $customer_id
     * @param string $order_id
     * @return array
     * @throws Exception
     */
    public function getSalesOrderRmaDetail(int $customer_id, string $order_id): array
    {
        $this->load->model('tool/image');
        $binding_res = db('tb_sys_customer_sales_order as cso')
            ->select(['cso.id', 'cso.order_id', 'cso.create_time', 'cso.order_status', 'ocl.id AS cloud_id'])
            ->selectRaw('sum(csol.qty) as quantity')
            ->selectRaw('case when cso.order_status = '.CustomerSalesOrderStatus::CANCELED.' then "Canceled" when cso.order_status = '.CustomerSalesOrderStatus::COMPLETED.' then "Complete" end as order_status_show')
            ->leftJoin('tb_sys_customer_sales_order_line as csol', 'cso.id', '=', 'csol.header_id')
            ->leftJoin('oc_order_cloud_logistics AS ocl', 'ocl.sales_order_id', '=', 'cso.id')
            ->where([
                'cso.order_id' => $order_id,
                'cso.buyer_id' => $customer_id,
            ])
            // 只有状态为取消16或完成32的销售订单才能发起rma
            //->whereIn('cso.order_status', [CustomerSalesOrderStatus::CANCELED, CustomerSalesOrderStatus::COMPLETED])
            ->groupBy(['csol.header_id'])
            ->get()
            ->map(function ($item) {
                $item = (array)$item;
                $order_status = $item['order_status'];
                $item['order_status_show'] = Arr::get($this->getSalesOrderStatusList(), $order_status, 'UNDEFINED');
                $res = db('tb_sys_customer_sales_order_line')   // 获取sales_order_line 信息
                ->where(['header_id' => $item['id']])
                    ->get()
                    ->map(function ($item) use ($order_status) {  // 获取绑定表信息
                        $europe_freight_product_list = json_decode($this->config->get('europe_freight_product_id'));
                        return db('tb_sys_order_associated as soa')
                            ->select([
                                'op.order_id', 'op.product_id', 'op.order_product_id', 'p.image', 'pd.name',
                                'soa.qty as quantity', 'p.product_type', 'p.sku',
                                'soa.coupon_amount as soa_coupon_amount','soa.campaign_amount as soa_campaign_amount','op.quantity as all_product_qty'
                            ])
                            ->leftJoin('oc_order_product as op', 'op.order_product_id', '=', 'soa.order_product_id')
                            ->leftJoin('oc_product as p', 'p.product_id', '=', 'soa.product_id')
                            ->leftJoin('oc_product_description as pd', 'pd.product_id', '=', 'p.product_id')
                            ->leftJoin('oc_product_quote as pq', function (JoinClause $j) {
                                $j->on('pq.order_id', '=', 'op.order_id');
                                $j->on('pq.product_id', '=', 'op.product_id');
                            })
                            ->where(['soa.sales_order_id' => $item->header_id, 'soa.sales_order_line_id' => $item->id,])
                            ->whereNotIn('soa.product_id', $europe_freight_product_list)
                            ->get()
                            ->map(function ($item) use ($order_status) {
                                $item = (array)$item;
                                $item['image'] = $this->model_tool_image->resize($item['image']);
                                $price_info = $this->getComputeOrderProductPrice($item['order_product_id']);
                                $item = array_merge($item, $price_info);
                                if ($order_status == CustomerSalesOrderStatus::CANCELED) {
                                    $item['total'] = bcmul($item['unit_price_without_deposit'], $item['quantity']);
                                    $item['need_return_deposit'] = false;
                                } else {
                                    // 完成的销售订单可能会退还订金
                                    $item['total'] = bcmul($item['unit_price'], $item['quantity']);
                                    $item['need_return_deposit'] = true;
                                }
                                // 价格详细
                                $item['isEurope'] = $this->customer->isEurope();
                                //满减&优惠券
                                $item['coupon_amount_show'] = empty((float)($item['soa_coupon_amount'])) ? 0 : 1;
                                $item['campaign_amount_show'] = empty((float)($item['soa_campaign_amount'])) ? 0 : 1;
                                if ($item['coupon_amount_show'] > 0 || $item['campaign_amount_show'] > 0) {
                                    $item['total'] -= ($item['soa_coupon_amount'] + $item['soa_campaign_amount']);
                                }
                                $item['price_detail'] = $this->load->view(
                                    'account/rma_management/total_amount_view',
                                    array_merge($item, ['currency' => session('currency')])
                                );

                                return $item;
                            })
                            ->toArray();
                    })
                    ->toArray();
                // 校验res是否为空
                $item['has_details'] = !empty(Arr::flatten($res, 1));
                $item['details'] = $res;
                return $item;
            })
            ->toArray();

        return ['binding' => $binding_res, 'no_binding' => []];
    }

    /**
     * 获取采购订单RMA信息
     * @param int $customer_id
     * @param string $order_id
     * @return array
     */
    public function getPurchaseOrderRmaDetail(int $customer_id, string $order_id): array
    {
        $binding = [];
        $no_binding = [];
        db('oc_order_product as op')
            ->select([
                'op.order_id', 'op.product_id', 'op.order_product_id', 'p.image', 'pd.name',
                'op.quantity', 'p.product_type', 'p.sku',
                'op.coupon_amount','op.campaign_amount'
            ])
            ->leftJoin('oc_order as o', 'o.order_id', '=', 'op.order_id')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'op.product_id')
            ->leftJoin('oc_product_description as pd', 'p.product_id', '=', 'pd.product_id')
            ->leftJoin('oc_product_quote as pq', function (JoinClause $j) {
                $j->on('pq.order_id', '=', 'op.order_id');
                $j->on('pq.product_id', '=', 'op.product_id');
            })
            ->where([
                'o.order_id' => $order_id,
                'o.customer_id' => $customer_id,
            ])
            ->whereIn('o.order_status_id', [OcOrderStatus::COMPLETED, OcOrderStatus::CHARGEBACK])
            ->get()
            ->map(function ($item) use ($customer_id, &$binding, &$no_binding) {
                $europe_freight_product_list = json_decode($this->config->get('europe_freight_product_id'));
                $item = (array)$item;
                // 图片压缩展示
                $this->load->model('tool/image');
                $item['image'] = $this->model_tool_image->resize($item['image']);
                db('tb_sys_order_associated as soa')
                    ->select(['cso.order_id'])
                    ->selectRaw('sum(qty) as quantity')
                    ->leftJoin('tb_sys_customer_sales_order as cso', 'cso.id', '=', 'soa.sales_order_id')
                    ->where([
                        'soa.order_id' => $item['order_id'],
                        'soa.product_id' => $item['product_id'],
                        'soa.order_product_id' => $item['order_product_id'],
                    ])
                    ->whereNotIn('soa.product_id', $europe_freight_product_list)
                    ->groupBy(['soa.sales_order_id'])
                    ->get()
                    ->each(function ($info) use ($customer_id, &$binding, &$item) {
                        $info = (array)$info;
                        if (!array_key_exists($info['order_id'], $binding) && $info['order_id']) {
                            $res = $this->getSalesOrderRmaDetail($customer_id, $info['order_id']);
                            $binding[$info['order_id']] = $res['binding'];
                        }
                        $item['quantity'] = $item['quantity'] - $info['quantity'];
                    });

                if ($item['quantity'] > 0) {
                    $price_info = $this->getComputeOrderProductPrice($item['order_product_id']);
                    $item['total'] = bcmul($price_info['unit_price_without_deposit'], $item['quantity']);
                    $item = array_merge($item, $price_info);
                    $item['need_return_deposit'] = false;
                    // 价格详细
                    $item['isEurope'] = $this->customer->isEurope();
                    //满减
                    $alreadyBindDiscount = app(RamRepository::class)->getSalesOrderBindInfo($item['order_id'], $item['product_id']);
                    $phurseRmaDiscount = app(RamRepository::class)->getPhurseOrderRmaInfo($item['order_id'], $item['order_product_id']);
                    $item['soa_coupon_amount'] = $item['soa_campaign_amount'] = 0;
                    $item['coupon_amount_show'] = $item['campaign_amount_show'] = 0;
                    $item['discount_page_show'] = 0;
                    if ($item['campaign_amount'] > 0) {
                        $item['campaign_amount_show'] = $item['discount_page_show'] = 1;
                        if ($alreadyBindDiscount['all_sales_campaign_amount'] == 0) {
                            $tempCampaignAmount = $item['campaign_amount']; //没绑定销售单,采购订单的数量不变,直接展示全部活动金额
                        } else {
                            $tempCampaignAmount = $item['campaign_amount'] -
                                $alreadyBindDiscount['all_sales_campaign_amount'];// - $phurseRmaDiscount['all_phurse_campaign_amount'];
                            if ($tempCampaignAmount <= 0) {//等于0，代表都退完了,页面点不进去rma，数据展示不对
                                $tempCampaignAmount = $item['campaign_amount'] -
                                    $alreadyBindDiscount['all_sales_campaign_amount'];
                            }
                        }

                        $item['soa_campaign_amount'] = max($tempCampaignAmount, 0);
                    }
                    //优惠券
                    if ($item['coupon_amount'] > 0) {
                        $item['coupon_amount_show'] = $item['discount_page_show'] = 1;
                        if ($alreadyBindDiscount['all_sales_coupon_amount'] == 0) {
                            $tempCouponAmount = $item['coupon_amount'];
                        } else {
                            $tempCouponAmount = $item['coupon_amount'] -
                                $alreadyBindDiscount['all_sales_coupon_amount'];// - $phurseRmaDiscount['all_phurse_coupon_amount'];
                            if ($tempCouponAmount <= 0) { //等于0，代表都退完了,页面点不进去rma，数据展示不对
                                $tempCouponAmount = $item['coupon_amount'] -
                                    $alreadyBindDiscount['all_sales_coupon_amount'];
                            }
                        }

                        $item['soa_coupon_amount'] = max($tempCouponAmount, 0);
                    }
                    $item['total'] -= ($item['soa_coupon_amount'] + $item['soa_campaign_amount']);

                    $item['price_detail'] = $this->load->view(
                        'account/rma_management/total_amount_view',
                        array_merge($item, ['currency' => session('currency')])
                    );
                    $no_binding[] = $item;
                }
                return $item;
            })
            ->toArray();

        return ['binding' => Arr::flatten(array_values($binding), 1), 'no_binding' => $no_binding];
    }


    /**
     * 获取联想数据 这里获取的的如下2种订单id
     * 1.已完成或者已取消的销售订单
     * 2.已完成的采购订单
     * @param int $buyer_id
     * @param array $data
     * @return array
     */
    public function autocomplete(int $buyer_id, array $data = []): array
    {
        $co = new Collection($data);
        $name = trim($co->get('filter_name'));
        if (empty($name)) {
            return [];
        }
        $name_cmp = (string)intval($name);
        // 获取该用户所有的已取消或者已完成的销售订单
        $query1 = db('tb_sys_customer_sales_order')
            ->select('order_id')
            ->selectRaw('1 as order_type')
            ->where('buyer_id', $buyer_id)
            // 16-销售订单取消 32-销售订单已完成
            // ->whereIn('order_status', [CustomerSalesOrderStatus::CANCELED, CustomerSalesOrderStatus::COMPLETED])
            ->when($co->get('filter_name'), function (Builder $q) use ($co) {
                $q->where('order_id', 'like', trim($co->get('filter_name')) . '%');
            })
            ->limit(5)
            ->orderBy('create_time', 'desc');
        // 获取该用户所有已完成的采购订单
        $query2 = db('oc_order')
            ->select('order_id')
            ->selectRaw('2 as order_type')
            ->where('customer_id', $buyer_id)
            ->whereIn('order_status_id', [OcOrderStatus::COMPLETED, OcOrderStatus::CHARGEBACK])
            ->when($co->get('filter_name'), function (Builder $q) use ($co) {
                $q->where('order_id', 'like', trim($co->get('filter_name')) . '%');
            })
            ->limit(5)
            ->orderBy('date_added', 'desc');

        if ($name === $name_cmp) {
            return $query1
                ->union($query2)
                ->get()
                ->map(function ($item) {
                    return (array)$item;
                })
                ->toArray();
        } else {
            return $query1->limit(10)
                ->get()
                ->map(function ($item) {
                    return (array)$item;
                })
                ->toArray();
        }
    }

    /**
     * 计算对应order产品的价格总计
     * @param int $order_product_id
     * @param bool $return_deposit
     * @return array
     * @throws Exception
     */
    private function getComputeOrderProductPrice(int $order_product_id, bool $return_deposit = true): array
    {
        bcscale(2);
        $op_info = db('oc_order_product as op')
            ->select([
                'op.price', 'op.agreement_id', 'op.type_id', 'op.service_fee_per',
                'op.freight_per', 'op.package_fee',
                'op.quantity as all_product_qty','op.campaign_amount','op.coupon_amount'
            ])
            ->selectRaw('
                CASE WHEN pq.price IS NULL THEN 0  ELSE 0-pq.amount_price_per-pq.amount_service_fee_per END AS discount,
                CASE WHEN pq.amount_service_fee_per IS NULL THEN 0  ELSE 0-pq.amount_service_fee_per END AS amount_service_fee_per,
                CASE WHEN pq.amount_price_per IS NULL THEN 0  ELSE 0-pq.amount_price_per END AS amount_price_per'
            )
            ->leftJoin('oc_product_quote as pq', function (JoinClause $j) {
                $j->on('pq.order_id', '=', 'op.order_id');
                $j->on('pq.product_id', '=', 'op.product_id');
            })
            ->where('op.order_product_id', $order_product_id)
            ->first();
        $op_info = (array)$op_info;
        // 记录订金
        $op_info['deposit_per'] = 0;
        if ($return_deposit && $op_info['type_id'] > 0) {
            $this->load->model('account/rma_management');
            switch ($op_info['type_id']) {
                case 2:    // 现货保证金
                {
                    $margin_info = $this->model_account_rma_management->getMarginAdvanceOrderInfo($order_product_id);
                    $op_info['deposit_per'] = bcsub($margin_info['deposit_per'], 0);
                    break;
                }
                case 3:    // 期货保证金
                {
                    $future_info = $this->model_account_rma_management
                        ->getFutureMarginAdvanceOrderInfo($op_info['agreement_id']);
                    $op_info['deposit_per'] = bcsub($future_info['originalUnitPrice'], $future_info['last_unit_price']);
                    break;
                }
                default:
                    break;
            }
        }
        $amount_price_per = $op_info['amount_price_per'];
        $amount_service_fee_per = $op_info['amount_service_fee_per'];
        $all_product_qty = $op_info['all_product_qty'];
        $coupon_amount = $op_info['coupon_amount'];
        $campaign_amount = $op_info['campaign_amount'];
        unset($op_info['type_id']);
        unset($op_info['agreement_id']);
        unset($op_info['amount_price_per']);
        unset($op_info['amount_service_fee_per']);
        unset($op_info['all_product_qty']);
        unset($op_info['coupon_amount']);
        unset($op_info['campaign_amount']);
        $unit_price = array_sum(array_values($op_info));
        $op_info['unit_price'] = (string)$unit_price;
        $op_info['amount_price_per'] = $amount_price_per;
        $op_info['amount_service_fee_per'] = $amount_service_fee_per;
        $op_info['unit_price_without_deposit'] = bcsub($unit_price, $op_info['deposit_per']);
        $op_info['all_product_qty'] = $all_product_qty;
        $op_info['coupon_amount'] = $coupon_amount;
        $op_info['campaign_amount'] = $campaign_amount;
        return $op_info;
    }

    /**
     * 获取销售订单状态列表
     * @return array
     */
    public function getSalesOrderStatusList(): array
    {
        static $ret = [];
        static $find = false;
        if (!$find) {
            $ret = db('tb_sys_dictionary')
                ->where(['DicCategory' => 'CUSTOMER_ORDER_STATUS'])
                ->get()
                ->keyBy('DicKey')
                ->map(function ($item) {
                    return $item->DicValue;
                })
                ->toArray();
            $find = true;
        }
        return $ret;
    }

    /**
     * 获取指定销售订单已经申请rma的数量 此功能只对已完成的销售订单
     * @param string $sales_order_id
     * @param int $order_id
     * @param int $product_id
     * @return int
     */
    public function getAppliedSalesOrderQuantity(string $sales_order_id, int $order_id, int $product_id): int
    {
        $ret = 0;
        db('oc_yzc_rma_order as ro')
            ->select(['rop.quantity', 'rop.rma_type', 'rop.status_refund', 'rop.status_reshipment',])
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->where([
                'ro.from_customer_order_id' => $sales_order_id,
                'ro.order_id' => $order_id,
                'rop.product_id' => $product_id,
                'ro.cancel_rma' => 0,
            ])
            ->get()
            ->each(function ($item) use (&$ret) {
                $item = (array)$item;
                switch ((int)$item['rma_type']) {
                    case 1:
                    {
                        if (in_array($item['status_reshipment'], [0, 1])) {
                            $ret += (int)$item['quantity'];
                        }
                        break;
                    }
                    case 2:
                    {
                        if (in_array($item['status_refund'], [0, 1])) {
                            $ret += (int)$item['quantity'];
                        }
                        break;
                    }
                    case 3:
                    {
                        if (in_array($item['status_refund'], [0, 1]) || in_array($item['status_reshipment'], [0, 1])) {
                            $ret += (int)$item['quantity'];
                        }
                        break;
                    }
                    default:
                        break;
                }
            });
        return $ret;
    }

}
