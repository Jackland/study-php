<?php

namespace App\Repositories\Order;

use App\Enums\Order\OcOrderTypeId;
use App\Enums\Order\OrderInvoiceStatus;
use App\Models\Futures\FuturesMarginAgreement;
use App\Models\Margin\MarginAgreement;
use App\Models\Order\Order;
use App\Models\Order\OrderInvoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class OrderInvoiceRepository
{
    /**
     * 获取N天(默认7天)内的Invoice总数
     *
     * @param int $buyerId BuyerID
     * @param int $beforeDays 生成时间N天(默认7天)内
     * @return int
     */
    public function getInvoiceTotal(int $buyerId, int $beforeDays = 7)
    {
        $startDate = Carbon::now()->subDays($beforeDays)->toDateString();
        return OrderInvoice::where('buyer_id', $buyerId)
            ->where('update_time', '>=', $startDate)
            ->count();
    }

    /**
     * 分页获取数据
     *
     * @param int $buyerId BuyerID
     * @param int $page 分页
     * @param int $pageSize 分页大小
     * @param int $beforeDays 生成时间N天(默认7天)内
     * @return Collection|OrderInvoice[]
     */
    public function getInvoiceList(int $buyerId, int $page = 1, int $pageSize = 10, int $beforeDays = 7)
    {
        $startDate = Carbon::now()->subDays($beforeDays)->toDateString();
        $list =  OrderInvoice::query()->alias('oi')
            ->leftJoinRelations(['seller as s', 'customerPartnerToCustomer as tc'])
            ->select(['oi.id', 'oi.status', 'oi.create_time', 'oi.update_time', 's.firstname', 's.lastname', 'tc.screenname', 'oi.file_path'])
            ->where('oi.buyer_id', $buyerId)
            ->where('oi.create_time', '>=', $startDate)
            ->orderBy('oi.create_time', 'desc')
            ->orderBy('tc.screenname', 'asc')
            ->offset($page)
            ->limit($pageSize)
            ->get();
        return $list;
    }


    /**
     * 获取Invoice数据
     *
     * @param array $orderId
     * @param int $sellerId
     * @return array
     */
    public function getInvoiceData(array $orderId, int $sellerId)
    {
        $orderList = Order::query()->alias('o')
            ->leftJoin('oc_order_product as oop', 'o.order_id', '=', 'oop.order_id')
            ->leftJoin('oc_product_quote as opq', function ($join) {
                $join->on('opq.order_id', '=', 'oop.order_id')
                    ->on('opq.product_id', '=', 'oop.product_id');
            })
            ->leftJoin('oc_product as op', 'op.product_id', '=', 'oop.product_id')
            ->leftJoin('oc_customerpartner_to_product as tp', 'op.product_id', 'tp.product_id')
            ->select(['o.payment_method', 'o.date_modified', 'oop.order_id', 'oop.price', 'oop.freight_per', 'oop.quantity', 'op.sku', 'op.product_type', 'oop.service_fee_per', 'oop.package_fee', 'oop.product_id', 'oop.type_id', 'oop.agreement_id', 'opq.amount', 'opq.amount_price_per', 'opq.amount_service_fee_per', 'oop.coupon_amount', 'oop.campaign_amount', 'opq.price as quote_price'])
            ->whereIn('o.order_id', $orderId)
            ->where('tp.customer_id', $sellerId)
            ->orderBy('oop.order_id')
            ->orderBy('oop.order_product_id', 'asc')
            ->get();
        $marginList = [];
        $futureList = [];
        if ($orderList->isEmpty()) {
            return ['orderList' => $orderList, 'marginList' => $marginList, 'futureList' => $futureList];
        }

        $marginListId = [];
        $futureListId = [];
        foreach ($orderList as $item) {
            if ($item->type_id == OcOrderTypeId::TYPE_MARGIN) { // 现货
                $marginListId[] = $item->agreement_id;
                continue;
            }
            if ($item->type_id == OcOrderTypeId::TYPE_FUTURE) { // 期货
                $futureListId[] = $item->agreement_id;
            }
        }
        if ($marginListId) {
            $marginList = MarginAgreement::query()->alias('ma')
                ->leftJoinRelations('product as p')
                ->leftJoin('oc_futures_margin_delivery as d', 'ma.id', '=', 'd.margin_agreement_id')
                ->leftJoin('oc_futures_margin_agreement as a', 'd.agreement_id', '=', 'a.id')
                ->whereIn('ma.id', $marginListId)
                ->select(['ma.id', 'ma.agreement_id', 'ma.payment_ratio', 'p.sku', 'ma.num', 'a.buyer_payment_ratio'])
                ->get()
                ->keyBy('id')
                ->toArray();
        }
        if ($futureListId) {
            $futureList = FuturesMarginAgreement::query()->alias('fma')
                ->leftJoinRelations('product as p')
                ->whereIn('fma.id', $futureListId)
                ->select(['fma.id', 'fma.agreement_no', 'fma.buyer_payment_ratio', 'p.sku', 'fma.num'])
                ->get()
                ->keyBy('id')
                ->toArray();
        }

        return ['orderList' => $orderList, 'marginList' => $marginList, 'futureList' => $futureList];
    }

    /**
     * 获取Buyer待生成列表
     *
     * @param int $buyerId BuyerID
     * @param int $beforeDays 生成时间N天(默认7天)内
     * @return Collection|OrderInvoice[]
     */
    public function getNeedDealInvoiceList(int $buyerId, int $beforeDays = 7)
    {
        $startDate = Carbon::now()->subDays($beforeDays)->toDateString();
        $list =  OrderInvoice::query()->alias('oi')
            ->leftJoinRelations(['seller as s', 'customerPartnerToCustomer as tc'])
            ->select(['oi.*', 's.firstname', 's.lastname', 'tc.screenname'])
            ->where('oi.buyer_id', $buyerId)
            ->where('oi.update_time', '>=', $startDate)
            ->where('oi.status', OrderInvoiceStatus::GOING)
            ->orderBy('oi.update_time', 'desc')
            ->get();
        return $list;
    }

    /**
     * 获取指定的待生成的Invoice
     *
     * @param int $buyerId
     * @param int $invoiceId
     * @return OrderInvoice|null
     */
    public function getNeedDealInvoiceInfo(int $buyerId, int $invoiceId)
    {
        return OrderInvoice::query()->alias('oi')
            ->leftJoinRelations(['seller as s', 'customerPartnerToCustomer as tc'])
            ->select(['oi.*', 's.firstname', 's.lastname', 'tc.screenname'])
            ->where('oi.id', $invoiceId)
            ->where('oi.buyer_id', $buyerId)
            ->where('oi.status', OrderInvoiceStatus::GOING)
            ->first();
    }

    /**
     * 获取指定Invoice生成信息
     *
     * @param int $buyerId buyerID
     * @param array $invoiceIds InvoiceIds
     * @return OrderInvoice[]|Collection
     */
    public function getInvoiceLastInfo(int $buyerId, array $invoiceIds)
    {
        return OrderInvoice::where('buyer_id', $buyerId)
            ->whereIn('id', $invoiceIds)
            ->get();
    }
}
