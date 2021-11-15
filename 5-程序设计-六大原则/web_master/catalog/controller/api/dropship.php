<?php

use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\SalesOrder\HomePickCarrierType;
use App\Enums\SalesOrder\HomePickImportMode;
use App\Enums\SalesOrder\HomePickPlatformType;
use App\Enums\SalesOrder\HomePickUploadType;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Models\SalesOrder\HomePickLabelDetails;
use Illuminate\Database\Query\Builder;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class ControllerApiDropship
 * @property ModelAccountCustomerOrderImport $model_account_customer_order_import
 * @property ModelApiDropship $model_api_dropship
 */
class ControllerApiDropship extends ControllerApiBase
{
    const CODE = 2000;
    const TEXT = 'Success';

    /**
     * [other description] usa other 平台导入订单接口
     * @return JsonResponse
     * @throws Exception
     */
    public function other(): JsonResponse
    {
        $structure = [
            //'sign' => '',
        ];
        $this->setRequestDataStructure($structure);
        $input = $this->getParsedJson();
        if (isset($input['result_code'])) {
            $json = $input;
            return $this->response->json($json);
        }
        load()->language('account/customer_order_import');
        load()->model('api/dropship');
        $order_mode = HomePickUploadType::ORDER_MODE_HOMEPICK;
        $country_id = AMERICAN_COUNTRY_ID;
        $data = $this->model_api_dropship->getAllUnexportDropshipOrderInfo($order_mode, $country_id);
        $json = [
            'result_code' => static::CODE,
            'result_message' => static::TEXT,
            'result_data' => $data,
        ];
        return $this->response->json($json);
    }


    /**
     * [indexReplace description] 美国上门取货通用入口
     * date:2020/11/4 13:53
     */
    public function indexReplace()
    {

        $structure = [
            //'sign' => '',
        ];
        $this->setRequestDataStructure($structure);
        $input = $this->getParsedJson();
        if (isset($input['result_code'])) {
            $json = $input;
            return $this->response->json($json);
        }
        $this->load->language('account/customer_order_import');
        $order_mode = HomePickUploadType::ORDER_MODE_HOMEPICK;
        $country_id = AMERICAN_COUNTRY_ID;
        $data = $this->getAllUnexportDropshipOrderInfoByBol($order_mode, $country_id);

        $json['result_code'] = static::CODE;
        $json['result_message'] = static::TEXT;
        $json['result_data'] = $data;

        return $this->response->json($json);

    }

    /**
     * [ukDropship description] uk wayfair 调用接口
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function ukDropship()
    {
        $structure = [
            //'sign' => '',
        ];
        $this->setRequestDataStructure($structure);
        $input = $this->getParsedJson();
        if (isset($input['result_code'])) {
            $json = $input;
            return $this->response->json($json);
        }
        $this->load->language('account/customer_order_import');
        $order_mode = HomePickUploadType::ORDER_MODE_HOMEPICK;
        $country_id = EUROPE_COUNTRY_ID;
        $data = $this->getEuropeWayfairOrderInfo($order_mode, $country_id);
        $json['result_code'] = static::CODE;
        $json['result_message'] = static::TEXT;
        $json['result_data'] = $data;
        return $this->response->json($json);

    }

    public function getEuropeWayfairOrderInfo($order_mode, $country_id): ?array
    {
        $map = [
            ['o.order_mode', '=', $order_mode],
            ['o.order_status', '=', CustomerSalesOrderStatus::BEING_PROCESSED],  // bp
        ];
        $data = db('tb_sys_customer_sales_order as o')
            ->where($map)
            ->whereIn('c.country_id', $country_id)
            ->where(function ($query) {
                $query->whereIn('l.is_synchroed', [2, 3])->orWhereNull('l.is_synchroed');
            })
            ->leftJoin('tb_sys_customer_sales_order_file as f', 'f.order_id', '=', 'o.id')
            ->leftJoin('tb_sys_customer_sales_order_line as l', 'l.header_id', '=', 'o.id')
            ->leftJoin(DB_PREFIX . 'customer as c', 'c.customer_id', '=', 'o.buyer_id')
            ->groupBy(['o.id'])
            ->select(['o.id', 'o.yzc_order_id', 'o.order_id', 'o.ship_service_level'])
            ->get()
            ->map(function ($v){
                return (array)$v;
            })
            ->toArray();
        load()->model('api/dropship');
        foreach ($data as $key => $value) {
            $cutType = $this->model_api_dropship->judgeEuropeWayfairShipMethodCode($value['ship_service_level']);
            $data[$key]['carrier'] = $cutType['carrier'];
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
                        ->leftJoin('tb_sys_customer_sales_order_file as f', 'f.order_id', '=', 'd.order_id')
                        ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'd.set_product_id')
                        ->leftJoin('tb_sys_customer_sales_wayfair_temp as t', 'd.temp_id', '=', 't.id')
                        ->where($mapFile)
                        ->select(['d.line_item_number', 'd.tracking_number', 'd.deal_file_path', 'd.commercial_invoice_file_path', 'f.deal_file_path as manifest'])
                        ->selectRaw("IFNULL(p.sku,d.sku) as sku,
                           case when
                               t.warehouse_name = 'warehouse_code' then NULL
                           else
                               t.warehouse_name
                           end as warehouse"
                        )
                        ->groupBy('d.id')
                        ->get()
                        ->toArray();
                    $tmp[$k]['file_info'] = $tmpChild;

                }
            }
            $data[$key]['line_info'] = $tmp;
            unset($data[$key]['ship_service_level']);
        }

        if (empty($data)) {
            return null;
        }

        return $data;
    }

    /**
     * [getAllUnexportDropshipOrderInfoByBol description]
     * @param int $order_mode
     * @param int $country_id
     * @return array
     * date:2019/10/11 9:47
     */
    public function getAllUnexportDropshipOrderInfoByBol($order_mode, $country_id = AMERICAN_COUNTRY_ID)
    {
        $map = [
            ['o.order_mode', '=', $order_mode],
            ['o.order_status', '=', CustomerSalesOrderStatus::BEING_PROCESSED],  // bp
            ['c.country_id', '=', $country_id],
        ];
        $in_mode = [
            HomePickImportMode::IMPORT_MODE_AMAZON,
            HomePickImportMode::IMPORT_MODE_WAYFAIR,
            HomePickImportMode::IMPORT_MODE_WALMART
        ];
        // 这里写了importMode 4 dropship 的大件 需要bol 5 wayfair的大件 不需要bol //7 imporMode walmart
        $default_warehouse = 'warehouse_code';
        //]; // 仓库代码 用于 dropship 大件货
        $data = db('tb_sys_customer_sales_order as o')
            ->where($map)
            ->where(function (Builder $query) use ($country_id) {
                //英国dropship 同步字段不同 is_synchroed 美国dropship  is_exported
                if ($country_id == AMERICAN_COUNTRY_ID) {
                    $query->where('l.is_exported', '=', 3)->orWhereNull('l.is_exported');
                } elseif ($country_id == HomePickUploadType::BRITAIN_COUNTRY_ID) {
                    $query->whereIn('l.is_synchroed', [2, 3])->orWhereNull('l.is_synchroed');
                } else {
                    $query->where('l.is_exported', '=', 3)->orWhereNull('l.is_exported');
                }
            })
            ->leftJoin('tb_sys_customer_sales_order_line as l', 'l.header_id', '=', 'o.id')
            ->leftJoin(DB_PREFIX . 'customer as c', 'c.customer_id', '=', 'o.buyer_id')
            ->leftJoin('tb_sys_customer_sales_dropship_temp as t', 't.id', '=', 'l.temp_id')
            ->leftJoin('tb_sys_customer_sales_wayfair_temp as w', 'w.id', '=', 'l.temp_id')
            ->leftJoin('tb_sys_customer_sales_walmart_temp as wal', 'wal.id', '=', 'l.temp_id')
            ->whereIn('o.import_mode', $in_mode)
            ->groupBy('o.id')
            ->select('o.id', 'o.yzc_order_id', 'o.order_id',
                't.warehouse_name',
                't.ship_method',
                't.ship_method_code',
                'w.ship_method as w_ship_method',
                'w.carrier_name as carrier_name',
                'w.warehouse_name as w_warehouse_name',
                'w.ship_speed',
                'wal.ship_to',
                'wal.ship_to',
                'wal.carrier as wal_carrier',
                'wal.requested_carrier_method as wal_requested_carrier_method',
                'wal.warehouse_code as wal_warehouse_name',
                'o.import_mode', 'o.bol_path as bol'
            )
            ->get()
            ->map(function ($vs) {
                return (array)$vs;
            })
            ->toArray();

        foreach ($data as $key => $value) {
            $data[$key]['is_ltl'] = 0;
            if ($value['import_mode'] == HomePickImportMode::IMPORT_MODE_WALMART) {
                // walmart 都是存在仓库的
                $data[$key]['warehouse'] = $value['wal_warehouse_name'];
                $cut_type = $this->judgeShipMethodCode($value['wal_carrier'], $value['wal_requested_carrier_method'], $value['import_mode'], null);
                if (in_array($cut_type['value'], HomePickCarrierType::getWalmartLTLTypeViewItems())) {
                    $data[$key]['is_ltl'] = 1;
                }
                $data[$key]['platform'] = HomePickPlatformType::WALMART;

            } elseif ($value['import_mode'] == HomePickImportMode::IMPORT_MODE_WAYFAIR) {
                $data[$key]['warehouse'] = '';
                if ($value['w_warehouse_name'] != $default_warehouse) {
                    $data[$key]['warehouse'] = $value['w_warehouse_name'];
                }
                $cut_type = $this->judgeShipMethodCode($value['carrier_name'], $value['w_ship_method'], $value['import_mode'], $value['ship_speed']);
                if (in_array($cut_type['value'], HomePickCarrierType::getWayfairLTLTypeViewItems())) {
                    $data[$key]['is_ltl'] = 1;
                } else {
                    $data[$key]['warehouse'] = '';
                }
                $data[$key]['platform'] = HomePickPlatformType::WAYFAIR;
            } else {
                if ($value['warehouse_name'] != $default_warehouse) {
                    $data[$key]['warehouse'] = $value['warehouse_name'];
                } else {
                    $data[$key]['warehouse'] = '';
                }
                $cut_type = $this->judgeShipMethodCode($value['ship_method_code'], $value['ship_method'], $value['import_mode'], null);
                if (in_array($cut_type['value'], HomePickCarrierType::getAmazonLTLTypeViewItems())) {
                    $data[$key]['is_ltl'] = 1;
                } else {
                    $data[$key]['warehouse'] = '';
                }
                $data[$key]['platform'] = HomePickPlatformType::AMAZON;
            }

            $data[$key]['is_store'] = $value['ship_to'] == 'Store' ? 1 : 0;
            $data[$key]['cut_type'] = $cut_type['key'];
            $data[$key]['carrier'] = $cut_type['carrier'];
            $mapLine['l.header_id'] = $value['id'];
            $tmp = db('tb_sys_customer_sales_order_line as l')
                ->where($mapLine)
                ->select('id')
                ->get()
                ->map(function ($vs) {
                    return (array)$vs;
                })
                ->toArray();

            if ($tmp) {
                foreach ($tmp as $k => $v) {
                    $mapFile['d.line_id'] = $v['id'];
                    $tmpChild = db('tb_sys_customer_sales_dropship_file_details as d')
                        ->where($mapFile)
                        ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'd.set_product_id')
                        ->groupBy('d.id')
                        ->select('d.line_item_number', 'd.tracking_number', 'd.deal_file_path')
                        ->selectRaw('IFNULL(p.sku,d.sku) as sku,d.store_deal_file_path as store_label_file_path')
                        ->get()
                        ->map(function ($vs) {
                            return (array)$vs;
                        })
                        ->toArray();

                    $tmp[$k]['file_info'] = $tmpChild;

                }
            }
            $data[$key]['line_info'] = $tmp;
            unset($data[$key]['warehouse_name']);
            unset($data[$key]['ship_method']);
            unset($data[$key]['ship_method_code']);
            unset($data[$key]['w_ship_method']);
            unset($data[$key]['w_warehouse_name']);
            unset($data[$key]['carrier_name']);
            unset($data[$key]['import_mode']);
            unset($data[$key]['ship_speed']);
            unset($data[$key]['ship_to']);
            unset($data[$key]['wal_carrier']);
            unset($data[$key]['wal_requested_carrier_method']);
            unset($data[$key]['wal_warehouse_name']);
        }
        return $data;

    }


    /**
     * [judgeShipMethodCode description] 进行ups 和arrow的确认
     * @param $shipMethodCode
     * @param $ship_method
     * @param $import_mode
     * @param $ship_speed
     * @return array
     * date:2019/7/23 11:33
     */
    public function judgeShipMethodCode($shipMethodCode, $ship_method, $import_mode = 4, $ship_speed = null)
    {
        $carrierNameList = HomePickCarrierType::getCarrierNameViewItems();
        if ($import_mode == HomePickImportMode::IMPORT_MODE_WALMART) {
            // walmart 比较粗暴直接比对
            $carrier = $shipMethodCode == '' ? $ship_method : $shipMethodCode;
            $cutTypeList = HomePickCarrierType::getWalmartAllCutTypeViewItems();
            foreach ($cutTypeList as $ks => $vs) {
                if (strtolower($carrier) == strtolower($ks)) {
                    $res['key'] = $vs;
                    $res['value'] = $ks;
                    $res['carrier'] = $carrierNameList[$ks];
                    return $res;
                }
            }

        } elseif ($import_mode == HomePickImportMode::IMPORT_MODE_WAYFAIR) {
            $type = HomePickCarrierType::getWayfairCutTypeViewItems();
            $speed_type = WAYFAIR_FEDEX_TYPES;
            foreach ($type as $key => $value) {
                if (stripos($shipMethodCode, $value) !== false) {
                    if ($key == 3) {
                        //Fedex 需要单独验证是否是Next Day Air
                        if (stripos($ship_speed, $speed_type[0]) !== false) {
                            $res['key'] = $key + 5;
                            $res['value'] = HomePickCarrierType::FEDEX_EXPRESS;
                            $res['carrier'] = HomePickCarrierType::FEDEX;
                            return $res;
                        }
                    }
                    $res['key'] = $key + 7;
                    $res['value'] = $value;
                    $res['carrier'] = $carrierNameList[$value];
                    return $res;
                }
            }
        } else {
            //UPS 中需要验证 UPS Surepost GRD Parcel UPS中的大件
            //14310 线上增加AFB和CEVA提货方式
            $type = HomePickCarrierType::getAmazonCutTypeViewItems();
            unset($type[0]);
            foreach ($type as $key => $value) {
                if (stripos($shipMethodCode, $value) !== false || stripos($ship_method, $value) !== false) {
                    if ($key == 1) {
                        if (stripos($shipMethodCode, $type[5]) !== false || stripos($ship_method, $type[5]) !== false) {
                            $res['key'] = $key + 4;
                            $res['value'] = $type[5];
                            $res['carrier'] = $carrierNameList[$type[5]];
                            return $res;
                        }
                    }
                    $res['key'] = $key;
                    $res['value'] = $value;
                    $res['carrier'] = $carrierNameList[$value];
                    return $res;
                }
            }
        }


        $res['key'] = 0;
        $res['value'] = HomePickCarrierType::DEFAULT;
        $res['carrier'] = HomePickCarrierType::OTHER;
        return $res;


    }



}
