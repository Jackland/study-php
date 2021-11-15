<?php

namespace App\Repositories\SalesOrder;

use App\Models\Warehouse\WarehouseInfo;

class CustomerSalesOrderPickUpLineRepository
{
    /**
     * 处理json数据
     * @param $json tb_sys_customer_sales_order_pick_up_line_change 中的origin_pick_up_json或store_pick_up_json
     * @return array
     * example:
     *     [
     *          'applyDate'=>'xxxx',//申请日期
     *          'WarehouseID'=>'xxxx',//仓库ID
     *          'warehouseCode'=>'xxxx',//仓库
     *          'warehouseFullAddress'=>'xxxx',//仓库地址
     *          'skuAndQty'=>[
     *               'itemCode'=>qty   //key=>父sku  value=>父sku数量
     *            ],
     *          'lines'=>[
     *             'itemCode'=>'xxx',        //父sku
     *             'qty'=>1,                //父sku数量
     *             'childItemCode'=>'xxx',  //子sku
     *             'childQty'=>2,          //子sku数量
     *             'crossRow'=>'xxx',      //要合并列数量
     *              ]
     *     ]
     */
    public function dealPickUpJson($json)
    {
        $result = [];
        $data = json_decode($json, true);
        $warehouseInfo = WarehouseInfo::query()->where('WarehouseID', $data['warehouseId'])->first();
        $result['applyDate'] = $data['applyDate'] ?? '';
        $result['WarehouseID'] = $warehouseInfo ? $warehouseInfo->WarehouseID : '';
        $result['warehouseCode'] = $warehouseInfo ? $warehouseInfo->WarehouseCode : '';
        $result['warehouseFullAddress'] = $warehouseInfo ? $warehouseInfo->full_address : '';
        $pkey = 0;
        $result['itemCodeAndQty'] = [];
        if ($data['lines']) {
            foreach ($data['lines'] as $key => $val) {
                $result['itemCodeAndQty'][$val['itemCode']] = $val['qty'];
                $temp = [];
                $temp['itemCode'] = $val['itemCode'];//父sku
                $temp['qty'] = $val['qty'];//父sku数量
                //非com
                if (!isset($val['comboInfo'])) {
                    $temp['childItemCode'] = '';
                    $temp['childQty'] = '';
                    $temp['crossRow'] = 1;
                    $result['lines'][$pkey] = $temp;
                    $pkey++;
                    continue;
                }
                //com
                $line = $pkey;
                $crossRow = 0;//合并的列数
                $exitsItemCodeTemp = [];
                foreach ($val['comboInfo'] as $combo) {
                    foreach ($combo as $itemCode => $subQty) {
                        if ($itemCode == $val['itemCode']) {//排除子产品
                            continue;
                        }
                        if (isset($exitsItemCodeTemp[$itemCode])) {
                            $result['lines'][$exitsItemCodeTemp[$itemCode]]['childQty'] += $subQty * $combo[$val['itemCode']];//累加qty
                        } else {
                            $exitsItemCodeTemp[$itemCode] = $pkey;
                            $temp['childItemCode'] = $itemCode;//子sku
                            $temp['childQty'] = $subQty * $combo[$val['itemCode']];//子sku数量
                            $result['lines'][$pkey] = $temp;
                            $pkey++;
                            $crossRow++;
                        }
                    }
                }
                $result['lines'][$line]['crossRow'] = $crossRow;
            }
        }
        return $result;
    }
}
