<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Class ModelAccountCustomerpartnerCalculator
 */
class ModelAccountCustomerpartnerCalculatorFreight extends Model
{
    public static $QcTypeNameCn = [
        1 => '快递报价',
        2 => '纯物流快递报价',
        3 => '卡车报价',
        4 => '纯物流卡车报价',
        5 => '仓租报价',
        6 => '打包费报价',
    ];


    /**
     * 运费计算器的计费规则
     * @return array
     */
    public function freight()
    {
        $param_category = $this->orm
            ->table('tb_freight_version AS v')
            ->leftJoin('tb_freight_param_category AS pc', 'pc.version_id', '=', 'v.id')
            ->select([
                'pc.id AS pc_id',
                'pc.type AS pc_type',
                'pc.description',
            ])
            ->where([
                'v.status' => 1,
            ])
            ->orderBy('pc.type', 'asc')
            ->get();
        $param_category = obj2array($param_category);
        $param_category_arr = []; //key = pc_id
        foreach ($param_category as $key => $value) {
            $pc_id = $value['pc_id'];
            $param_category_arr[$pc_id] = $value;
        }
        unset($param_category);


        $param_detail = $this->orm
            ->table('tb_freight_version AS v')
            ->leftJoin('tb_freight_param_category AS pc', 'pc.version_id', '=', 'v.id')
            ->leftJoin('tb_freight_param_detail AS pd', 'pd.param_category_id', '=', 'pc.id')
            ->select([
                'pc.id AS pc_id',
                'pc.type AS pc_type',
                'pd.id AS pd_id',
                'pd.code',
                'pd.description',
                'pd.value',
            ])
            ->where([
                'v.status' => 1,
            ])
            ->orderBy('pc_type', 'asc')
            ->orderBy('pc_id', 'asc')
            ->orderBy('pd_id', 'asc')
            ->get();
        $param_detail = obj2array($param_detail);
        $param_detail_arr = []; // key = pc_id   pd_id
        foreach ($param_detail as $key => $value) {
            $pc_id = $value['pc_id'];
            $pd_code = $value['code'];

            switch ($pd_code) {
                case 'DAY_RANGE'://仓租计费区间，用数组表示
                    $value_list = json_decode($value['value'], true);
                    if (!is_array($value_list)) {
                        $value_list = [];
                    }
                    $value['value_list'] = $value_list;
                    break;
                case 'PACKAGE_FEE_EXPRESS_WEIGHT_RANGE'://一件代发快递打包费重量区间
                    $value_list = json_decode($value['value'], true);
                    if (!is_array($value_list)) {
                        $value_list = [];
                    }
                    $value['value_list'] = $value_list;
                    break;
                case 'PACKAGE_FEE_LTL_WEIGHT_RANGE'://一件代发卡车打包费重量区间
                    $value_list = json_decode($value['value'], true);
                    if (!is_array($value_list)) {
                        $value_list = [];
                    }
                    $value['value_list'] = $value_list;
                    break;
                case 'PACKAGE_FEE_PICKUP_EXPRESS_WEIGHT_RANGE'://上门取货快递打包费重量区间
                    $value_list = json_decode($value['value'], true);
                    if (!is_array($value_list)) {
                        $value_list = [];
                    }
                    $value['value_list'] = $value_list;
                    break;
                case 'PACKAGE_FEE_PICKUP_LTL_WEIGHT_RANGE'://上门取货卡车打包费重量区间
                    $value_list = json_decode($value['value'], true);
                    if (!is_array($value_list)) {
                        $value_list = [];
                    }
                    $value['value_list'] = $value_list;
                    break;
                default:
                    break;
            }

            $param_detail_arr[$pc_id][$pd_code] = $value;
        }
        unset($param_detail);


        //基础运费
        $freight_base = $this->orm
            ->table('tb_freight_version AS v')
            ->leftJoin('tb_freight_quote_category AS qc', 'qc.version_id', '=', 'v.id')
            ->leftJoin('tb_freight_base_quote AS bq', 'bq.quote_category_id', '=', 'qc.id')
            ->select([
                'bq.quote_category_id AS qc_id',
                'bq.bill_freight',
                'bq.freight',
            ])
            ->where([
                'v.status' => 1,
            ])
            ->orderBy('bq.quote_category_id', 'asc')
            ->orderBy('bq.sort', 'asc')
            ->get();
        $freight_base = obj2array($freight_base);
        $freight_base_arr = [];//key = qc_id
        foreach ($freight_base as $value) {
            $qc_id = $value['qc_id'];
            $freight_base_arr[$qc_id][] = [
                'billFreight' => $value['bill_freight'],
                'freight' => $value['freight']
            ];
        }
        unset($freight_base);


        $quote_detail = $this->orm
            ->table('tb_freight_version AS v')
            ->leftJoin('tb_freight_quote_category AS qc', 'qc.version_id', '=', 'v.id')
            ->leftJoin('tb_freight_quote_detail AS qd', 'qd.quote_category_id', '=', 'qc.id')
            ->leftJoin('tb_freight_param_category AS pc', 'pc.id', '=', 'qc.param_category_id')
            ->select([
                'qc.param_category_id AS pc_id',
                'pc.type AS pc_type',
                'qd.quote_category_id AS qc_id',
                'qc.type AS qc_type',
                'qd.id AS qd_id',
                'qd.code',
                'qd.description',
                'qd.value',
            ])
            ->where([
                'v.status' => 1,
            ])
            ->orderBy('qc.type', 'asc')
            ->get();
        $quote_detail = obj2array($quote_detail);
        $quote_detail_arr = [];
        foreach ($quote_detail as $key => $value) {
            $pc_id = $value['pc_id'];
            $pc_type = $value['pc_type'];
            $qc_id = $value['qc_id'];
            $qc_type = $value['qc_type'];
            $qd_code = $value['code'];


            switch ($value['code']) {
                case 'DROP_SHIPPING_BASE_PACKAGE_QUOTE'://一件代发快递 基础打包费
                    $value_list = json_decode($value['value'], true);
                    if (!is_array($value_list)) {
                        $value_list = [];
                    }
                    $value['value_list'] = $value_list;
                    break;
                case 'DROP_SHIPPING_ATTACH_PACKAGE_QUOTE'://一件代发快递 附加打包费
                    $value_list = json_decode($value['value'], true);
                    if (!is_array($value_list)) {
                        $value_list = [];
                    }
                    $value['value_list'] = $value_list;
                    break;
                case 'DROP_SHIPPING_LTL_BASE_PACKAGE_QUOTE'://一件代发卡车LTL 基础打包费
                    $value_list = json_decode($value['value'], true);
                    if (!is_array($value_list)) {
                        $value_list = [];
                    }
                    $value['value_list'] = $value_list;
                    break;
                case 'DROP_SHIPPING_LTL_ATTACH_PACKAGE_QUOTE'://一件代发卡车LTL 附加打包费
                    $value_list = json_decode($value['value'], true);
                    if (!is_array($value_list)) {
                        $value_list = [];
                    }
                    $value['value_list'] = $value_list;
                    break;
                case 'PICK_UP_BASE_PACKAGE_QUOTE'://上门取货快递 基础打包费
                    $value_list = json_decode($value['value'], true);
                    if (!is_array($value_list)) {
                        $value_list = [];
                    }
                    $value['value_list'] = $value_list;
                    break;
                case 'PICK_UP_ATTACH_PACKAGE_QUOTE'://上门取货快递 附加打包费
                    $value_list = json_decode($value['value'], true);
                    if (!is_array($value_list)) {
                        $value_list = [];
                    }
                    $value['value_list'] = $value_list;
                    break;
                case 'PICK_UP_LTL_BASE_PACKAGE_QUOTE'://上门取货卡车LTL基础打包费
                    $value_list = json_decode($value['value'], true);
                    if (!is_array($value_list)) {
                        $value_list = [];
                    }
                    $value['value_list'] = $value_list;
                    break;
                case 'PICK_UP_LTL_ATTACH_PACKAGE_QUOTE'://上门取货卡车LTL附加打包费
                    $value_list = json_decode($value['value'], true);
                    if (!is_array($value_list)) {
                        $value_list = [];
                    }
                    $value['value_list'] = $value_list;
                    break;
                case 'WAREHOUSE_RENTAL'://仓租阶梯报价
                    $value_list = json_decode($value['value'], true);
                    if (!is_array($value_list)) {
                        $value_list = [];
                    }
                    $value['value_list'] = $value_list;
                    break;
                default:
                    break;
            }


            $quote_detail_arr[$qc_id][$qd_code] = $value;

            //追加 基础运费
            if (!isset($quote_detail_arr[$qc_id]['BASE_FREIGHT_SURCHARGE_QUOTE'])) {
                if (isset($freight_base_arr[$qc_id])) {
                    $quote_detail_arr[$qc_id]['BASE_FREIGHT_SURCHARGE_QUOTE'] = [
                        'pc_id' => $pc_id,
                        'pc_type' => $pc_type,
                        'qc_id' => $qc_id,
                        'qc_type' => $qc_type,
                        'qd_id' => 0,
                        'code' => 'BASE_FREIGHT_SURCHARGE_QUOTE',
                        'description' => '基础运费报价',
                        'value_list' => $freight_base_arr[$qc_id]
                    ];
                }
            }
        }
        unset($quote_detail);


        $quote_category = $this->orm
            ->table('tb_freight_version AS v')
            ->leftJoin('tb_freight_quote_category AS qc', 'qc.version_id', '=', 'v.id')
            ->select([
                'qc.id AS qc_id',
                'qc.param_category_id AS pc_id',
                'qc.type AS qc_type',
            ])
            ->where([
                'v.status' => 1,
            ])
            ->orderBy('qc.type', 'asc')
            ->get();
        $quote_category = obj2array($quote_category);
        $results = []; //key = qc_type NAME
        foreach ($quote_category as $key => $value) {
            $qc_type = $value['qc_type'];
            $value['qc_type_name_cn'] = self::$QcTypeNameCn[$qc_type];

            $pc_id = $value['pc_id'];
            $qc_id = $value['qc_id'];

            $value['description'] = $param_category_arr[$pc_id]['description'];
            $value['param'] = $param_detail_arr[$pc_id];
            $value['quote'] = $quote_detail_arr[$qc_id] ?? []; // fix bug 默认空数组 (与苏阳沟通)


            $index = '';
            switch ($qc_type) {
                case "1"://快递报价     平台云送仓快递报价
                    $index = 'expressQuote';
                    break;
                case "2"://纯物流快递报价      第三方物流快递报价
                    $index = 'expressQuotePure';
                    break;
                case "3"://卡车报价     平台云仓卡车LTL报价
                    $index = 'truckQuote';
                    break;
                case "4"://纯物流卡车报价      第三方物流卡车LTL报价
                    $index = 'truckQuotePure';
                    break;
                case "5"://仓租报价
                    $index = 'warehouseQuote';
                    break;
                case "6"://打包费报价
                    $index = 'packageQuote';
                    break;
                default:
                    break;
            }
            $results[$index] = $value;
        }
        unset($quote_category);

        return $results;
    }

    public function getFreightConfig()
    {
        $freightConfig = db('tb_freight_version AS v')
            ->leftJoin('tb_freight_config AS c', 'v.id', '=', 'c.version_id')
            ->select('c.key', 'c.value')
            ->where('v.status', '=', 1)
            ->pluck('c.value', 'c.key')
        ;
        $freightConfig = obj2array($freightConfig);
        return $freightConfig;
    }

    /**
     * @param $filter_data
     * @return array
     */
    public function autocompleteProductsBySku($filter_data)
    {
        $sku = $filter_data['sku'];
        $sellerId = $filter_data['sellerId'];
        $pageSize = $filter_data['pageSize'];

        $query = $this->orm
            ->table('oc_product AS p')
            ->join('oc_customerpartner_to_product AS c2p', 'c2p.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_to_tag AS p2t', function (JoinClause $j) {
                $j->on('p2t.product_id', '=', 'p.product_id')
                    ->where('p2t.tag_id', '=', 1);
            })
            ->select([
                'p.product_id',
                'p.sku',
                'p.mpn',
                'p.weight',
                'p.length',
                'p.width',
                'p.height',
                'p.combo_flag',
                'p.danger_flag',
                'p2t.tag_id AS ltl_flag'
            ])
            ->where([
                'c2p.customer_id' => $sellerId,
                'part_flag' => 0,
            ])
            ->whereIn('p.product_type', [0, 3])
            ->where(function (Builder $q) use ($sku) {
                $q->orWhere('p.sku', 'like', "%{$sku}%");
            })
            ->orderBy('p.product_id', 'desc')
            ->offset(0)
            ->limit($pageSize)
            ->get();
        $results = obj2array($query);//默认值空数组[]
        if (!$results) {
            return [];
        }


        $combo_id_arr = [];//combo品的product_id
        foreach ($results as $key => $value) {

            $value['weight'] = sprintf("%0.2f", floor(bcmul($value['weight'], 100)) / 100);
            $value['length'] = sprintf("%0.2f", floor(bcmul($value['length'], 100)) / 100);
            $value['width'] = sprintf("%0.2f", floor(bcmul($value['width'], 100)) / 100);
            $value['height'] = sprintf("%0.2f", floor(bcmul($value['height'], 100)) / 100);
            $results[$key] = $value;

            if ($value['combo_flag']) {
                $combo_id_arr[] = $value['product_id'];
            }
        }


        $subResults = []; //key 是主product_id，value 是子产品
        $parentLtlArr = [];//key 是主product_id, value=1；标记哪些父产品是LTL
        //如果是combo品，则再查询子产品
        if ($combo_id_arr) {
            $subQuery = $this->orm
                ->table('tb_sys_product_set_info AS ps')
                ->leftJoin('oc_product AS p', 'ps.set_product_id', '=', 'p.product_id')
                ->leftJoin('oc_product_to_tag AS p2t', function (JoinClause $j) {
                    $j->on('p2t.product_id', '=', 'ps.set_product_id')
                        ->where('p2t.tag_id', '=', 1);
                })
                ->select([
                    'p.sku',
                    'p.mpn',
                    'p.weight',
                    'p.height',
                    'p.length',
                    'p.width',
                    'p.danger_flag',
                    'ps.qty',
                    'ps.set_product_id',
                    'ps.product_id',
                    'p2t.tag_id AS ltl_flag'
                ])
                ->where([
                    'ps.seller_id' => $sellerId,
                ])
                ->whereIn('ps.product_id', $combo_id_arr)
                ->get();
            $subResult = obj2array($subQuery);
            foreach ($subResult as $key => $value) {
                if ($value['ltl_flag']) {//不为null，则为LTL
                    $parentLtlArr[$value['product_id']] = 1;
                }
                $value['weight'] = number_format($value['weight'], 2, '.', '');
                $value['height'] = number_format($value['height'], 2, '.', '');
                $value['length'] = number_format($value['length'], 2, '.', '');
                $value['width'] = number_format($value['width'], 2, '.', '');
                $subResults[$value['product_id']][] = $value;
            }
        }


        if ($subResults) {
            foreach ($results as $key => $value) {
                if ($value['combo_flag']) {
                    $value['comboList'] = $subResults[$value['product_id']];
                    //父产品的ltl标识
                    if (isset($parentLtlArr[$value['product_id']]) && $parentLtlArr[$value['product_id']] == 1) {
                        $value['ltl_flag'] = $parentLtlArr[$value['product_id']];
                    }
                } else {
                    $value['comboList'] = [];
                }
                $results[$key] = $value;
            }
        }

        return $results;
    }


    public function productExists($sellerId, $sku)
    {
        $result = $this->orm
            ->table('oc_product AS p')
            ->leftjoin('oc_customerpartner_to_product AS c2p', 'c2p.product_id', '=', 'p.product_id')
            ->select(['p.product_id'])
            ->where([
                'c2p.customer_id' => $sellerId,
                'part_flag' => 0,
                'sku' => $sku
            ])
            ->exists();
        return $result;
    }
}
