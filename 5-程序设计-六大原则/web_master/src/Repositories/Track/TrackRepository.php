<?php

namespace App\Repositories\Track;

use App\Enums\Common\YesNoEnum;
use App\Enums\Track\TrackStatus;
use App\Models\Product\Product;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\Track\TrackingFacts;
use App\Widgets\ImageToolTipWidget;
use Framework\Model\Eloquent\Builder;

class TrackRepository
{
    const FEDEX_LINK_URL = 'https://www.fedex.com/apps/fedextrack/?action=track&ascend_header=1&clienttype=dotcom&cntry_code=us&language=english&tracknumbers=';
    const UPS_LINK_URL = "";
    const PRODUCT_LINK_URL = 'index.php?route=product/product&product_id=';
    const UNSUPPORTED_NODE = [
        'fedex'=> 'OC',
        'ups'=>'MP',
    ];
    const WILL_CALL = 'will call';

    /**
     * @author xxl
     * @description 根据销售订单号获取物流信息
     * @date 14:13 2020/11/23
     * @param array $salesOrderIdArr ['3794372325353-3381974410546','3794372325353-3381974410546','',...]
     * @return array
     */
    public function getTrackingInfoBySalesOrderId($salesOrderIdArr)
    {
        $isCollectionFromDomicile = customer()->isCollectionFromDomicile();
        $imageTool = load()->model('tool/image');
        $result = [];
        $salesOrderItems = CustomerSalesOrder::query()->alias('a')
            ->leftJoinRelations(['lines as b'])
            ->leftJoin('tb_sys_order_associated as c', 'c.sales_order_line_id', '=', 'b.id')
            ->leftJoin('tb_sys_order_combo as d', 'd.order_product_id', '=', 'c.order_product_id')
            ->leftJoin('oc_product as op','op.product_id','=','c.product_id')
            ->whereIn('a.order_id', $salesOrderIdArr)
            ->selectRaw('op.image,c.product_id,a.order_id,ifnull(d.set_item_code,b.item_code) as item_code,b.item_code as parent_sku,c.qty*ifnull(d.qty,1) as qty')
            ->groupBy(['b.id','item_code','parent_sku'])
            ->get();
        $itemQtyArr = [];
        foreach ($salesOrderItems as $salesOrderItem) {
            $salesOrderId = $salesOrderItem->order_id;
            $itemCode = $salesOrderItem->item_code;
            $itemQty = $salesOrderItem->qty;
            $image = $salesOrderItem->image;
            $productId = $salesOrderItem->product_id;
            $itmCode = $itemCode;
            $productInfo = Product::query()->alias('p')
                ->leftJoinRelations(['tags as t'])
                ->where('p.product_id', $productId)
                ->where('t.tag_id', 3)
                ->selectRaw('p.product_id,p.image,t.icon')
                ->first();
            if (isset($productInfo->icon)) {
                $icon = ImageToolTipWidget::widget([
                    'image' => $productInfo->icon,
                ])->render();
                $itmCode = $salesOrderItem['parent_sku'] . $icon . '-' . $itemCode;
            }
            $linkUrl = self::PRODUCT_LINK_URL . $productId;
            $itemCodeImg = $imageTool->resize($image ?? null, 40, 40);
            $trackInfos = TrackingFacts::query()
                ->with([
                    'records' => function ($query) use ($isCollectionFromDomicile) {
                        return $query->when($isCollectionFromDomicile, function (Builder $q) use ($isCollectionFromDomicile) {
                            return $q->where('carrier_status', '<>', TrackStatus::LABEL_CREATED);
                        })
                            ->where('status', YesNoEnum::YES)->orderByDesc('carrier_time')->orderByDesc('carrier_status');
                    }
                ])
                ->where([['sales_order_id', $salesOrderId], ['sku', 'like', "%{$itemCode}%"], ['status', '=', 1]])
                ->get();
            if (count($trackInfos) == 0) {
                $result[$salesOrderId]['itemList'][] = [
                    'itemCodeImg' => $itemCodeImg,
                    'itemCode' => $itmCode,
                    'subItemCode' => $itemCode,
                    'linkUrl' => $linkUrl,
                    'trackList' => []
                ];
            }
            foreach ($trackInfos as $trackInfo) {
                $records = $trackInfo->records;
                $statusList = [];
                $trackList = [];
                $itemList = [];
                foreach ($records as $record) {
                    // carrier status = 5
                    if($record->carrier_status == TrackStatus::IN_TRANSIT
                        && !$this->checkTrackingNode($trackInfo->carrier,$record->event_code,$salesOrderId)
                    ){
                        $recordInfo = [];
                    }else{
                        $recordInfo = [
                            'country' => $record->country,
                            'city' => $record->city,
                            'state' => $record->state,
                            'event_description' => $record->event_description,
                            'status' => $record->carrier_status,
                            'create_time' => $record->carrier_time,
                        ];
                    }
                    if($recordInfo){
                        $statusList[] = $recordInfo;
                    }
                }
                if ('fedex' == strtolower($trackInfo->carrier)) {
                    $trackLinkUrl = self::FEDEX_LINK_URL . $trackInfo->tracking_number;
                } else if ('ups' == strtolower($trackInfo->carrier)) {
                    $trackLinkUrl = "";
                } else {
                    $trackLinkUrl = "";
                }
                $salesOrderId = trim($trackInfo->sales_order_id);
                $trackList[] = [
                    'carrier' => $trackInfo->carrier,
                    'trackingNumber' => $trackInfo->tracking_number,
                    'linkUrl' => $trackLinkUrl,
                    'statusList' => $statusList
                ];
                if (!isset($result[$salesOrderId])) {
                    $itemList[] = [
                        'itemCodeImg' => $itemCodeImg,
                        'itemCode' => $itmCode,
                        'subItemCode' => $itemCode,
                        'linkUrl' => $linkUrl,
                        'trackList' => $trackList
                    ];
                    $result[$salesOrderId] = [
                        'flag' => true,
                        'message' => 'success',
                        'salesOrder' => $salesOrderId,
                        'recipient' => $trackInfo->recipient,
                        'address' => $trackInfo->address,
                        'linkUrl' => '',
                        'itemList' => $itemList
                    ];
                    continue;
                }
                $skuArr = array_column($result[$salesOrderId]['itemList'], 'itemCode');
                foreach ($result[$salesOrderId]['itemList'] as &$item) {
                    $trackingNumberArr = array_column($item['trackList'], 'trackingNumber');
                    if ($item['itemCode'] == $itmCode && !in_array($trackInfo->tracking_number, $trackingNumberArr)) {
                        $trackListMerge = array_merge($item['trackList'], $trackList);
                        $item = [
                            'itemCodeImg' => $itemCodeImg,
                            'itemCode' => $itmCode,
                            'subItemCode' => $itemCode,
                            'linkUrl' => $linkUrl,
                            'trackList' => $trackListMerge
                        ];
                        break;
                    }
                    if ($item['itemCode'] != $itmCode && !in_array($itmCode, $skuArr)) {
                        $itemList = [
                            'itemCodeImg' => $itemCodeImg,
                            'itemCode' => $itmCode,
                            'subItemCode' => $itemCode,
                            'linkUrl' => $linkUrl,
                            'trackList' => $trackList
                        ];
                        $result[$salesOrderId]['itemList'][] = $itemList;
                        break;
                    }
                }
            }
        }
        return $result;
    }

    private function checkTrackingNode($carrier,$event_code,$salesOrderId): bool
    {
        $carrier = strtolower($carrier);
        if($carrier == self::WILL_CALL){
            $shipServiceLevel = CustomerSalesOrder::query()->where([
                'order_id'=>$salesOrderId,
                'buyer_id'=>customer()->getId(),
            ])->value('ship_service_level');
            if (stripos($shipServiceLevel, 'ups') !== false) {
                $carrier = 'ups';
            }elseif (stripos($shipServiceLevel, 'fedex') !== false) {
                $carrier = 'fedex';
            }
        }
        $unsupportedNode = self::UNSUPPORTED_NODE;
        if(isset($unsupportedNode[$carrier]) && $unsupportedNode[$carrier] == $event_code){
            return false;
        }

        return true;

    }

    /**
     * @author xxl
     * @description 根据运单号和销售订单获取运单最新状态
     * @date 14:17 2020/11/23
     * @param $salesOrderId
     * @param $trackingNumber
     * @return string
     */
    public function getTrackingStatusByTrackingNumber($salesOrderId, $trackingNumber): string
    {
        $isCollectionFromDomicile = customer()->isCollectionFromDomicile();
        $carrierStatus = TrackingFacts::query()
            ->where(
                [
                    ['sales_order_id', '=', $salesOrderId],
                    ['tracking_number', '=', $trackingNumber],
                    ['status', '=', YesNoEnum::YES],
                ])
            ->when($isCollectionFromDomicile, function (Builder $q) use ($isCollectionFromDomicile) {
                return $q->where('carrier_status','<>',TrackStatus::LABEL_CREATED);
            })
            ->value('carrier_status');
        return $trackingStatus = TrackStatus::getDescription($carrierStatus, '');
    }

    /**
     * 根据销售订单，获取对应物流状态 格式:4814193767067_783849122039 => Label Created(销售单号_运单号 => 最新物流状态数组)
     * @param array|null $salseOrderId
     * @return array
     */
    public function getTrackingStatusBySalesOrder(?array $salseOrderIdArr)
    {
        if (empty($salseOrderIdArr)) {
            return [];
        }

        $salseOrderIdArr = array_unique($salseOrderIdArr);

        $result = TrackingFacts::query()
            ->whereIn('sales_order_id', $salseOrderIdArr)
            ->where('status', YesNoEnum::YES)
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item['sales_order_id'] . '_' . $item['tracking_number'] => [
                    'carrier_status' => $item['carrier_status'],
                    'carrier_status_name' => TrackStatus::getDescription($item['carrier_status'])
                ]];
            })
            ->toArray();

        return $result;
    }

}
