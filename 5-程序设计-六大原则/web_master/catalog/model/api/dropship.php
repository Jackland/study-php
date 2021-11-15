<?php

use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\SalesOrder\HomePickCarrierType;
use App\Enums\SalesOrder\HomePickImportMode;
use App\Enums\SalesOrder\HomePickLabelReviewStatus;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Models\SalesOrder\HomePickLabelDetails;
use Framework\Model\Eloquent\Builder;


class ModelApiDropship extends Model
{

    /**
     * [getAllUnexportDropshipOrderInfo description]
     * @param int $order_mode
     * @param int $country_id
     * @return array
     *
     */
    public function getAllUnexportDropshipOrderInfo(int $order_mode, int $country_id = AMERICAN_COUNTRY_ID): array
    {
        $map = [
            ['o.order_mode', '=', $order_mode],
            ['o.order_status', '=', CustomerSalesOrderStatus::BEING_PROCESSED],  // bp
            ['c.country_id', '=', $country_id],
            ['lr.status', '=', HomePickLabelReviewStatus::APPROVED],
        ];
        $in_mode = [HomePickImportMode::US_OTHER];
        $data = CustomerSalesOrder::query()->alias('o')
            ->leftJoin('tb_sys_customer_sales_order_line as l', 'l.header_id', '=', 'o.id')
            ->leftJoin(DB_PREFIX . 'customer as c', 'c.customer_id', '=', 'o.buyer_id')
            ->leftJoin('tb_sys_customer_sales_order_other_temp as t', 't.id', '=', 'l.temp_id')
            ->leftJoin('tb_sys_customer_sales_order_label_review as lr', 'lr.order_id', '=', 'o.id')
            ->whereIn('o.import_mode', $in_mode)
            ->where($map)
            ->where(function (Builder $query) use ($country_id) {
                if ($country_id == AMERICAN_COUNTRY_ID) {
                    $query->where('l.is_exported', '=', 3)
                        ->orWhereNull('l.is_exported');
                }
            })
            ->groupBy(['o.id'])
            ->select([
                'o.id',
                'o.yzc_order_id',
                'o.order_id',
                't.warehouse_name',
                //'t.platform',
                'o.ship_method',
                't.carrier',
                'o.bol_path as bol'
            ])
            ->get()
            ->map(function ($v) {
                $v->setAppends([]);
                return $v;
            })
            ->toArray();
        $ltl_list = HomePickCarrierType::getOtherLTLTypeViewItems();
        foreach ($data as $key => $value) {
            // 获取cut type
            $cut_type = $this->judgeShipMethodCode($value['carrier']);
            $data[$key]['cut_type'] = $cut_type['key'];
            $data[$key]['carrier'] = $cut_type['carrier'];
            // 获取是否是超大件
            $data[$key]['is_ltl'] = 0;
            $data[$key]['warehouse'] = null;
            if (in_array($cut_type['value'], $ltl_list)) {
                $data[$key]['is_ltl'] = 1;
                // 获取仓库id
                $data[$key]['warehouse'] = $value['warehouse_name'] ?? null;
            }
            // 获取平台
            $mapLine['l.header_id'] = $value['id'];
            $tmp = CustomerSalesOrderLine::query()->alias('l')
                ->where($mapLine)
                ->select('id')
                ->get()
                ->toArray();

            if ($tmp) {
                foreach ($tmp as $k => $v) {
                    $mapFile['d.line_id'] = $v['id'];
                    $tmpChild = HomePickLabelDetails::query()->alias('d')
                        ->where($mapFile)
                        ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'd.set_product_id')
                        ->groupBy('d.id')
                        ->select(['d.line_item_number', 'd.tracking_number', 'd.deal_file_path', 'd.label_type'])
                        ->selectRaw('IFNULL(p.sku,d.sku) as sku')
                        ->get()
                        ->toArray();
                    $tmp[$k]['file_info'] = $tmpChild;
                }
            }

            $data[$key]['line_info'] = $tmp;
            unset($data[$key]['import_mode']);
            unset($data[$key]['warehouse_name']);

        }
        return $data;
    }

    public function judgeShipMethodCode($shipMethodCode): array
    {
        $cutTypeList = HomePickCarrierType::getOtherCutTypeViewItems();
        $carrierNameList = HomePickCarrierType::getCarrierNameViewItems();
        foreach ($cutTypeList as $ks => $vs) {
            if (strtolower($shipMethodCode) == strtolower($ks)) {
                $res['key'] = $vs;
                $res['value'] = $ks;
                $res['carrier'] = $carrierNameList[$ks];
                return $res;
            }
        }

        $res['key'] = 0;
        $res['value'] = HomePickCarrierType::DEFAULT;
        $res['carrier'] = HomePickCarrierType::OTHER;
        return $res;
    }

    public function judgeEuropeWayfairShipMethodCode($shipMethodCode): array
    {
        $carrierNameList = HomePickCarrierType::getCarrierNameViewItems();
        $cutTypeList = HomePickCarrierType::getEuropeWayfairCutTypeViewItems();
        foreach ($cutTypeList as $ks => $vs) {
            if (strtolower($shipMethodCode) == strtolower($vs)) {
                $res['carrier'] = $carrierNameList[$vs];
                return $res;
            }
        }
        $res['carrier'] = HomePickCarrierType::OTHER;
        return $res;
    }

}
