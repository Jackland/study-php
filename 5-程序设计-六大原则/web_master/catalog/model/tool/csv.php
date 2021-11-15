<?php

use App\Components\Storage\StorageCloud;
use App\Helper\CurrencyHelper;
use App\Repositories\Product\ProductOptionRepository;

/**
 * Class ModelToolCsv
 * @property ModelExtensionModuleProductShow $model_extension_module_product_show
 */
    class ModelToolCsv extends Model {

        /**
         * 处理20210430batch download临时问题处理
         * @param $fileName
         * @param $data
         * @return false|string
         * @throws Exception
         */
        public function  getProductCategoryCsvByMySeller($fileName,$data){
            $country_id =  $this->customer->getCountryId();
            $currency = $this->getCurrencyByCountryId($country_id);

            $is_hp = $this->customer->isCollectionFromDomicile();
            $this->setCsvHeader($fileName);
            //echo chr(239).chr(187).chr(191);
            $fp = fopen('php://output','a');
            //在写入的第一个字符串开头加 bom。
            $bom =  chr(0xEF).chr(0xBB).chr(0xBF);
            fwrite($fp,$bom);

            $head = [
                'Store Name',
                'ItemCode', //sku
                'Product Name', //name
                'Length(inch)',
                'Width(inch)',
                'Height(inch)',
                'Weight(pound)',
                'Unit Price'.$currency,
            ];
            if($is_hp){
                $head[]='Pickup Freight Per Unit'.$currency;
            }else{
                $head[]='Drop Shipping Freight Per Unit'.$currency;
            }
            if($this->customer->has_cwf_freight()){
                $head[]='Cloud Wholesale Fulfillment Freight Per Unit'.$currency;
            }
            $head[]='Qty Available';
            $warehouse = [];
            if ($is_hp){
                $this->load->model('extension/module/product_show');
                $warehouse = $this->model_extension_module_product_show->getWarehouseCodeByCountryId($country_id);
                foreach ($warehouse as $code){
                    $head[] = "\t".$code;
                }
            }
            $head = array_merge($head, [
                'Is Oversized Item',
                'Is Combo Flag',
                'Package Quantity', //包裹数
            ]);

            fputcsv($fp,$head);
            $count = 0;
            foreach ($data as $key => $value){

                if($value['buyer_privilege'] == 1){

                    if($country_id == 107){ //日本
                        $data[$key]['unit_price'] = (int)round($value['unit_price']);
                        $data[$key]['freight_per'] = (int)round($value['freight_per']);
                    }else{
                        $data[$key]['unit_price'] =  sprintf('%.2f',$value['unit_price']);
                        $data[$key]['freight_per'] =  sprintf('%.2f',$value['freight_per']);
                    }

                    $line = [
                        $value['screenname'],
                        $value['item_code'],
                        html_entity_decode($value['product_name']),
                        $value['combo_flag'] == 1?'0':sprintf('%.2f',$value['length']),
                        $value['combo_flag'] == 1?'0':sprintf('%.2f',$value['width']),
                        $value['combo_flag'] == 1?'0':sprintf('%.2f',$value['height']),
                        $value['combo_flag'] == 1?'0':sprintf('%.2f',$value['weight']),
                        $data[$key]['unit_price'],
                        $data[$key]['freight_per'],
                    ];
                    if($this->customer->has_cwf_freight()) {
                        if ($value['customer_group_id'] == 23 || in_array($value['customer_id'], array(340, 491, 631, 838))) {    //|| in_array($value['customer_id'], array(694, 696, 746, 907, 908))
                            $line[] = 0;
                        }else{
                            $line[] = $data[$key]['cwf_freight'];
                        }
                    }
                    $line[]=$value['qty_avaliable'];

                    if ($warehouse){
                        foreach ($warehouse as $id=>$code){
                            $line[] = $data[$key][$code];
                        }
                    }

                    $line = array_merge($line, [
                        $value['over_size_flag'] == 1?'Yes':'No',
                        //$value['quote_flag'] == 1?'Yes':'No',
                        $value['combo_flag'] == 1?'Yes':'No',
                        $value['package_quantity'],
                    ]);

                }else{
                    //第一行不需要空行
                    if($key == 0)
                        $count++;

                    if($count == 0){
                        $n = count($head);
                        $line = [];
                        for ($i=0; $i<=$n; $i++){
                            $line[] = '';
                        }
                        $count++;
                        fputcsv($fp, $line);
                    }

                    if($country_id == 107){ //日本
                        $data[$key]['unit_price'] = (int)round($value['unit_price']);
                        $data[$key]['freight_per'] = (int)round($value['freight_per']);

                    }else{
                        $data[$key]['unit_price'] =  sprintf('%.2f',$value['unit_price']);
                        $data[$key]['freight_per'] =  sprintf('%.2f',$value['freight_per']);
                    }

                    if($value['price_display'] != 1){
                        $data[$key]['unit_price'] = 'Contact Seller to get the price.';
                        $data[$key]['freight_per'] = 'Contact Seller to get the freight.';
                    }
                    if($value['quantity_display'] != 1){
                        $value['qty_avaliable'] = 'Contact Seller to get the quantity available.';
                    }
                    $line = [
                        $value['screenname'],
                        $value['item_code'],
                        html_entity_decode($value['product_name']),
                        $value['combo_flag'] == 1?'0':sprintf('%.2f',$value['length']),
                        $value['combo_flag'] == 1?'0':sprintf('%.2f',$value['width']),
                        $value['combo_flag'] == 1?'0':sprintf('%.2f',$value['height']),
                        $value['combo_flag'] == 1?'0':sprintf('%.2f',$value['weight']),
                        $data[$key]['unit_price'],
                        $data[$key]['freight_per'],
                    ];
                    if($this->customer->has_cwf_freight() && $value['price_display'] == 1) {
                        if ($value['customer_group_id'] == 23 || in_array($value['customer_id'], array(340, 491, 631, 838)) || in_array($value['customer_id'], array(694, 696, 746, 907, 908))) {
                            $line[] = 0;
                        }else{
                            $line[] = $data[$key]['cwf_freight'];
                        }
                    }else{
                        $line[] = 'Contact Seller to get the freight.';
                    }


                    $warehouseCount = count($warehouse);
                    for ($i=0; $i<$warehouseCount; $i++)
                    {
                        $line[] = '';
                    }
                    $line = array_merge($line,[
                        $value['over_size_flag'] == 1?'Yes':'No',
                        $value['combo_flag'] == 1?'Yes':'No',
                        $value['package_quantity'],
                    ]);

                }
                fputcsv($fp, $line);
            }



            $output = stream_get_contents($fp);
            fclose($fp);
            return $output;

        }

        /**
         * [getProductCategoryCsv description] 获取产品的csv
         * @param $fileName
         * @param $data
         * @return string
         * @throws Exception
         */
        public function getProductCategoryCsv($fileName, $data)
        {
            $productIds = array_column($data, 'product_id');
            $options = app(ProductOptionRepository::class)->getOptionByProductIds($productIds);
            $options = array_combine(array_column($options, 'product_id'), $options);
            $country_id = $this->customer->getCountryId();
            $currency = $this->getCurrencyByCountryId($country_id);
            $is_hp = $this->customer->isCollectionFromDomicile();
            $hasCwfFreight = $this->customer->has_cwf_freight();
            $this->setCsvHeader($fileName);
            $fp = fopen('php://output', 'a');
            //在写入的第一个字符串开头加 bom。
            $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
            fwrite($fp, $bom);
            $unit = '(inch)';
            $weightUnit = '(pound)';
            $head = [
                'Store Code', 'Store Name', 'ItemCode', 'Product Name', 'Length' . $unit,
                'Width' . $unit, 'Height' . $unit, 'Weight' . $weightUnit, 'Unit Price' . $currency,
            ];
            if ($is_hp) {
                $head[] = "Pickup Fulfillment Per Unit{$currency}";
                $head[] = "Pickup Total Cost{$currency}";
            } else {
                $head[] = "Drop Shipping Fulfillment Per Unit{$currency}";
                $head[] = "Drop Shipping Total Cost{$currency}";
            }
            if ($hasCwfFreight) {
                $head[] = "Cloud Wholesale Fulfillment Fulfillment Fee Per Unit{$currency}";
                $head[] = "Cloud Wholesale Fulfillment Total Cost{$currency}";
            }
            $head[] = 'Qty Available';
            $warehouse = [];
            if ($is_hp) {
                $this->load->model('extension/module/product_show');
                $warehouse = $this->model_extension_module_product_show->getWarehouseCodeByCountryId($country_id);
                foreach ($warehouse as $code) {
                    $head[] = "\t" . $code;
                }
            }
            $head = array_merge($head, [
                'LTL Product', 'Combo Product', 'Package Quantity', //包裹数
                'Color', 'Material', 'Size', 'Category', 'Manual Required',
                'Image1', 'Image2', 'Image3', 'Image4', 'Image5', 'Image6',
                'Image7', 'Image8', 'Image9',
            ]);

            fputcsv($fp, $head);
            $count = 0;
            $list = [];
            $list['contacted'] = [];
            $list['no_contacted'] = [];
            foreach ($data as $item) {
                if (empty($item['is_contacted'])) {
                    $list['no_contacted'][] = $item;
                } else {
                    $list['contacted'][] = $item;
                }
            }
            $data = array_merge($list['contacted'], $list['no_contacted']);
            foreach ($data as $key => $value) {
                if ($value['buyer_privilege'] == 1) {
                    if ($country_id == 107) { //日本
                        $data[$key]['unit_price'] = (int)round($value['unit_price']);
                        $data[$key]['freight_per'] = (int)round($value['freight_per']);
                    } else {
                        $data[$key]['unit_price'] = sprintf('%.2f', $value['unit_price']);
                        $data[$key]['freight_per'] = sprintf('%.2f', $value['freight_per']);
                    }
                    if (empty($value['is_contacted'])) {
                        if (empty($value['quantity_display'])) {
                            $value['qty_avaliable'] = 'Contact Seller to get the Available Quantity.';
                        }
                        if (empty($value['price_display'])) {
                            $data[$key]['unit_price'] = 'Contact Seller to get the price.';
                            $data[$key]['freight_per'] = 'Contact Seller to get the freight.';
                            $data[$key]['cwf_freight'] = 'Contact Seller to get the freight.';
                            $data[$key]['cwf_total'] = 'Contact Seller to get the freight.';
                        }
                    }

                    $line = [
                        $value['store_code'],
                        html_entity_decode($value['screenname']),
                        $value['item_code'],
                        html_entity_decode($value['product_name']),
                        $value['combo_flag'] == 1 ? '0' : sprintf('%.2f', $value['length']),
                        $value['combo_flag'] == 1 ? '0' : sprintf('%.2f', $value['width']),
                        $value['combo_flag'] == 1 ? '0' : sprintf('%.2f', $value['height']),
                        $value['combo_flag'] == 1 ? '0' : sprintf('%.2f', $value['weight']),
                        $data[$key]['unit_price'],
                        $data[$key]['freight_per'],
                        (empty($value['is_contacted']) && empty($value['price_display'])) ? 'Contact Seller to get the freight.' : $data[$key]['unit_price'] + $data[$key]['freight_per'],
                    ];
                    if ($hasCwfFreight) {
                        if ($value['customer_group_id'] == 23 || in_array($value['customer_id'], array(340, 491, 631, 838))) {    //|| in_array($value['customer_id'], array(694, 696, 746, 907, 908))
                            $line[] = 0;
                            $line[] = 0;
                        } else {
                            $line[] = $data[$key]['cwf_freight'];
                            $line[] = $data[$key]['cwf_total'];
                        }
                    }
                    $line[] = $value['qty_avaliable'];

                    if ($warehouse) {
                        foreach ($warehouse as $code) {
                            $line[] = $data[$key][$code];
                        }
                    }

                    $line = array_merge($line, [
                        $value['over_size_flag'] == 1 ? 'Yes' : 'No',
                        $value['combo_flag'] == 1 ? 'Yes' : 'No',
                        $value['package_quantity'],
                    ]);

                } else {
                    //第一行不需要空行
                    if ($key == 0)
                        $count++;

                    if ($count == 0) {
                        $n = count($head);
                        $line = [];
                        for ($i = 0; $i <= $n; $i++) {
                            $line[] = '';
                        }
                        $count++;
                        fputcsv($fp, $line);
                    }

                    if ($country_id == 107) { //日本
                        $data[$key]['unit_price'] = (int)round($value['unit_price']);
                        $data[$key]['freight_per'] = (int)round($value['freight_per']);

                    } else {
                        $data[$key]['unit_price'] = sprintf('%.2f', $value['unit_price']);
                        $data[$key]['freight_per'] = sprintf('%.2f', $value['freight_per']);
                    }

                    $line = [
                        $value['screenname'],
                        $value['item_code'],
                        html_entity_decode($value['product_name']),
                        $value['combo_flag'] == 1 ? '0' : sprintf('%.2f', $value['length']),
                        $value['combo_flag'] == 1 ? '0' : sprintf('%.2f', $value['width']),
                        $value['combo_flag'] == 1 ? '0' : sprintf('%.2f', $value['height']),
                        $value['combo_flag'] == 1 ? '0' : sprintf('%.2f', $value['weight']),
                        $data[$key]['unit_price'],
                        $data[$key]['freight_per'],
                    ];
                    if ($hasCwfFreight && $value['price_display'] == 1) {
                        if ($value['customer_group_id'] == 23 || in_array($value['customer_id'], array(340, 491, 631, 838)) || in_array($value['customer_id'], array(694, 696, 746, 907, 908))) {
                            $line[] = 0;
                        } else {
                            $line[] = $data[$key]['cwf_freight'];
                        }
                    } else {
                        $line[] = 'Contact Seller to get the freight.';
                    }


                    $warehouseCount = count($warehouse);
                    for ($i = 0; $i < $warehouseCount; $i++) {
                        $line[] = '';
                    }
                    $line = array_merge($line, [
                        $value['over_size_flag'] == 1 ? 'Yes' : 'No',
                        $value['combo_flag'] == 1 ? 'Yes' : 'No',
                        $value['package_quantity'],
                    ]);

                }
                $option = $options[$value['product_id']];
                $line[] = html_entity_decode($option['color_name']);
                $line[] = html_entity_decode($option['material_name']);
                $line[] = $value['product_size'];
                // 分类
                $category = $value['categoryInfo'] ?? [];
                if (
                    is_array($category)
                    && is_array(end($category))
                    && !empty(end($category)['arr_label'])
                    && is_array(end($category)['arr_label'])
                ) {
                    $line[] = html_entity_decode(end(end($category)['arr_label']));
                } else {
                    $line[] = '';
                }
                $line[] = $value['need_install'] ? 'Yes' : 'No';
                // 图片
                $image = $value['imageInfo'] ?? [];
                foreach ($image as $item) {
                    $line[] = StorageCloud::image()->getUrl($item, ['check-exist' => false]);
                }
                fputcsv($fp, $line);
            }

            $output = stream_get_contents($fp);
            fclose($fp);
            return $output;
        }

        public function  getCustomerAmount($fileName,$data){
            $status = [
                '2'=>'Being Processed',
                '16'=>'Canceled',
                '32'=>'Completed',
            ];
            $this->setCsvHeader($fileName);
            //echo chr(239).chr(187).chr(191);
            $fp = fopen('php://output','a');
            //在写入的第一个字符串开头加 bom。
            $bom =  chr(0xEF).chr(0xBB).chr(0xBF);
            fwrite($fp,$bom);
            $head = array(
                '采购时间', //sku
                '采购订单号', //name
                '采购ITEMCODE',
                '采购数量',
                '采购单价',
                '采购总价',
                '销售订单号',
                '销售itemcode',
                '销售发货数量',
                'rma_order_id',
                'comments',
                'apply_refund_amount',
                'actual_refund_amount',
                'rma_status'
            );

            fputcsv($fp,$head);
            foreach ($data as $key => $value){

                $line = [
                    $value['create_time'],
                    $value['oid'],
                    $value['item_code'],
                    $value['quantity'],
                    $value['price'],
                    sprintf('%4.f',$value['price']*$value['qty']),
                    $value['order_id'],
                    $value['order_id'],
                    $value['order_id'],

                ];
                fputcsv($fp, $line);
            }
            $output = stream_get_contents($fp);
            fclose($fp);
            return $output;

        }
        public function  getDifferCombo($fileName,$data){
            $this->setCsvHeader($fileName);
            //echo chr(239).chr(187).chr(191);
            $fp = fopen('php://output','a');
            //在写入的第一个字符串开头加 bom。
            $bom =  chr(0xEF).chr(0xBB).chr(0xBF);
            fwrite($fp,$bom);
            $head = array(
                iconv ( 'utf-8', 'gbk', 'Sku'), //sku
                iconv ( 'utf-8', 'gbk', 'all_product_id'), //name
                iconv ( 'utf-8', 'gbk', 'first_combo'),
                iconv ( 'utf-8', 'gbk', 'first_product_id'),
                iconv ( 'utf-8', 'gbk', 'first_product_screenname'),
                iconv ( 'utf-8', 'gbk', 'first_date_added'),
                iconv ( 'utf-8', 'gbk', 'differ_combo'),
                iconv ( 'utf-8', 'gbk', 'differ_product_id'),
                iconv ( 'utf-8', 'gbk', 'differ_product_screenname'),
                iconv ( 'utf-8', 'gbk', 'differ_date_added'),

            );

            fputcsv($fp,$head);
            foreach ($data as $key => $value){
                    $line = [
                        $value['sku'],
                        str_ireplace(',',';',$value['pstr']),
                        $value['first_combo'],
                        $value['first_product_id'],
                        $value['default_seller_name'],
                        $value['default_date_added'],
                        $value['differ_combo'],
                        $value['differ_product_id'],
                        $value['differ_seller_name'],
                        $value['differ_date_added'],
                    ];


                fputcsv($fp, $line);
            }


            $output = stream_get_contents($fp);
            fclose($fp);
            return $output;

        }
        public function  getUndoRmaList($fileName,$data){ $this->setCsvHeader($fileName);
            //echo chr(239).chr(187).chr(191);
            $fp = fopen('php://output','a');
            //在写入的第一个字符串开头加 bom。
            $bom =  chr(0xEF).chr(0xBB).chr(0xBF);
            fwrite($fp,$bom);
            $head = array(
                'FirstName+Lastname', //sku
                'Seller Email', //name
                'Buyer Email',
                'RMA ID',
                'Status',

            );

            fputcsv($fp,$head);
            foreach ($data as $key => $value){
                $name = trim($value['firstname'].' '.$value['lastname']);
                $line = [
                    $name,
                    $value['seller_email'],
                    $value['buyer_email'],
                    $value['rma_order_id'],
                    $value['memo'],

                ];
                fputcsv($fp, $line);
            }


            $output = stream_get_contents($fp);
            fclose($fp);
            return $output;


        }

        /**
         * [getSaleOrderDate description]
         * @param $fileName
         * @param $data
         * @return false|string
         */
        public function getSaleOrderDate($fileName,$data){
            $this->setCsvHeader($fileName);
            //echo chr(239).chr(187).chr(191);
            $fp = fopen('php://output','a');
            //在写入的第一个字符串开头加 bom。
            $bom =  chr(0xEF).chr(0xBB).chr(0xBF);
            fwrite($fp,$bom);
            $head = array(
                'Order ID',
                'Sale Order ID',
                'Date',
                'Create Time'
            );

            fputcsv($fp,$head);
            foreach ($data as $key => $value){

                $line = [
                    $value['purchase_order_id'],
                    $value['order_id'],
                    $value['date'],
                    $value['create_time'],

                ];
                fputcsv($fp, $line);
            }
            $output = stream_get_contents($fp);
            fclose($fp);
            return $output;

        }

        public function dealSkuBug($fileName,$data){
            $this->setCsvHeader($fileName);
            //echo chr(239).chr(187).chr(191);
            $fp = fopen('php://output','a');
            //在写入的第一个字符串开头加 bom。
            $bom =  chr(0xEF).chr(0xBB).chr(0xBF);
            fwrite($fp,$bom);
            $head = array(
                'ID', //sku
                'url', //name
                'trackingNumber',
            );

            fputcsv($fp,$head);
            foreach ($data as $key => $value){

                $line = [
                    $value['id'],
                    $value['url'],
                    $value['tracking_number'],

                ];
                fputcsv($fp, $line);
            }
            $output = stream_get_contents($fp);
            fclose($fp);
            return $output;

        }
        public function someUnderstandInfo($fileName,$data){
            $this->setCsvHeader($fileName);
            //echo chr(239).chr(187).chr(191);
            $fp = fopen('php://output','a');
            //在写入的第一个字符串开头加 bom。
            $bom =  chr(0xEF).chr(0xBB).chr(0xBF);
            fwrite($fp,$bom);
            $head = array(
                'nickName', //sku
                'sellername', //name
                '采购时间',
                '采购订单号',
                'item code',
                '数量',
                '价格',
                '总价'

            );

            fputcsv($fp,$head);
            foreach ($data as $key => $value){

                $line = [
                    $value['nickname'],
                    $value['screenname'],
                    $value['date_added'],
                    $value['order_id'],
                    $value['item_code'],
                    $value['quantity'],
                    $value['price'],
                    $value['total'],

                ];
                fputcsv($fp, $line);
            }
            $output = stream_get_contents($fp);
            fclose($fp);
            return $output;

        }
        public function dealWithAutoBuyer($fileName,$data){
            $this->setCsvHeader($fileName);
            //echo chr(239).chr(187).chr(191);
            $fp = fopen('php://output','a');
            //在写入的第一个字符串开头加 bom。
            $bom =  chr(0xEF).chr(0xBB).chr(0xBF);
            fwrite($fp,$bom);
            $head = array(
                'order id', //sku
                'B2B order ID', //name
                'Product Name',
                'Quantity',
                'Unit Price',
                'Total'
            );

            fputcsv($fp,$head);
            foreach ($data as $key => $value){

                $line = [
                    $value['order_id'],
                    $value['purchase_order_id'],
                    $value['product_name'],
                    $value['qty'],
                    $value['price'],
                    $value['total'],

                ];
                fputcsv($fp, $line);
            }
            $output = stream_get_contents($fp);
            fclose($fp);
            return $output;

        }
        public function  getBuyerRmaAmount($fileName,$data){
            $this->setCsvHeader($fileName);
            //echo chr(239).chr(187).chr(191);
            $fp = fopen('php://output','a');
            //在写入的第一个字符串开头加 bom。
            $bom =  chr(0xEF).chr(0xBB).chr(0xBF);
            fwrite($fp,$bom);
            $head = array(
                '昵称', //sku
                'Buyer邮箱', //name
                '数量',
                'rma批准数',
                '申请批准率',
                'buyer采购订单数',
                'buyer退货数',
                'buyer退货率',

            );

            fputcsv($fp,$head);
            foreach ($data as $key => $value){
                if($value['all_amount'] == 0){
                    $es = '';
                }else{
                    $es = sprintf('%.2f',$value['rma_amount']*100/$value['all_amount']);
                }
                if($value['amount'] == 0){
                    $ap = '';
                }else{
                    $ap =  sprintf('%.2f',$value['apply_amount']*100/$value['amount']);
                }
                $line = [
                    $value['nickname'],
                    $value['email'],
                    $value['amount'],
                    $value['apply_amount'],
                    $ap,
                    $value['all_amount'],
                    $value['rma_amount'],
                    $es,
                ];
                fputcsv($fp, $line);
            }
            $output = stream_get_contents($fp);
            fclose($fp);
            return $output;


        }
        public function  getBuyerData($fileName,$data){
            $compare = [
                '0'=>'',
                '1'=>'仅重发',
                '2'=>'仅退款',
                '3'=>'退款又重发',
            ];
            $this->setCsvHeader($fileName);
            //echo chr(239).chr(187).chr(191);
            $fp = fopen('php://output','a');
            //在写入的第一个字符串开头加 bom。
            $bom =  chr(0xEF).chr(0xBB).chr(0xBF);
            fwrite($fp,$bom);
            $head = array(
               '销售订单号', //sku
               '销售订单数量', //name
               '采购单价',
                'sku',
                '采购订单号',
               '采购订单日期',
                '退返品数据',

            );

            fputcsv($fp,$head);
            foreach ($data as $key => $value){
                if(isset($compare[$value['rma_type']])){
                    $last = $compare[$value['rma_type']];
                }else{
                    $last = '';
                }
                $line = [
                    $value['order_id'],
                    $value['line_qty'],
                    sprintf('%.2f',$value['price']),
                    $value['item_code'],
                    $value['oco_order_id'],
                    $value['date_added'],
                    $last,
                ];


                fputcsv($fp, $line);
            }


            $output = stream_get_contents($fp);
            fclose($fp);
            return $output;

        }

        public function  getStoreInfo($fileName,$data){

            $this->setCsvHeader($fileName);
            //echo chr(239).chr(187).chr(191);
            $fp = fopen('php://output','a');
            //在写入的第一个字符串开头加 bom。
            $bom =  chr(0xEF).chr(0xBB).chr(0xBF);
            fwrite($fp,$bom);
            $head = array(
                'Store', //sku
                'RMAID', //name
                'Order No.',
                'Platfrom order No.',
                'SKU Code',
                'Problem Items Qty',
                'Reason',
                'Resolution',
                'Customer\'s Comments',
                'Refund Amount',
                'Order Date',

            );

            fputcsv($fp,$head);
            foreach ($data as $key => $value){

                $line = [
                    $value['screenname'],
                    $value['rma_order_id'],
                    $value['order_id'],
                    $value['from_customer_order_id'],
                    $value['item_code'],
                    $value['quantity'],
                    $value['memo'],
                    html_entity_decode($value['seller_reshipment_comments']),
                    html_entity_decode($value['comments']),
                    sprintf('%.2f',$value['actual_refund_amount']),
                    $value['create_time'],

                ];


                fputcsv($fp, $line);
            }


            $output = stream_get_contents($fp);
            fclose($fp);
            return $output;

        }

        /**
         * [getPurchaseOrderFilterCsv description] 获取销售订单的详细数据
         * @param string $fileName
         * @param array $data
         * @return string
         */
        public function getPurchaseOrderFilterCsv($fileName, $data)
        {

            $precision = $data['isJapan'] ? 0 : 2;
            unset($data['isJapan']);
            //需求 rma退款的
            $this->setCsvHeader($fileName);
            //echo chr(239).chr(187).chr(191);
            $fp = fopen('php://output', 'a');
            //在写入的第一个字符串开头加 bom。
            $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
            fwrite($fp, $bom);

            $head = [
                'Purchase Order ID', //sku
                'Store Name', //name
                'Item Code',
                'Product Name',
                'Purchase Quantity',
                'Unit Price',
            ];

            $data['isEurope'] && $head[] = 'Service Fee Per Unit';
            $isEurope = $data['isEurope'];
            $data['enableQuote'] && $head[] = 'Price Discount Per Unit';
            $enableQuote = $data['enableQuote'];
            $data['isEurope'] && $data['enableQuote'] && $head[] = 'Service Fee Discount Per Unit';
            array_push($head, 'Freight Per Unit', 'Total', 'Transaction Fee', 'Payment Method', 'Purchase Date', 'Sales Order ID');
            $head[] = 'Is Return';
            //$head[] = 'RMA Type（Reshipment、Refund）';隐藏该列
            $head[] = 'RMA ID';
            unset($data['isEurope']);
            unset($data['enableQuote']);
            fputcsv($fp, $head);
            $total_qty = 0;
            $total_amount = 0;
            unset($value);
            foreach ($data as $key => $value) {
                $total_qty += $value['quantity'];
                $line = [
                    $value['purchase_order_id'],                                            //Purchase Order ID
                    html_entity_decode($value['screenname']),                                                   //Store Name
                    $value['item_code'],                                                    //Item Code
                    html_entity_decode($value['product_name']),                             //Product Name
                    $value['quantity'],                                                     //Purchase Quantity
                    $value['unit_price'],                                                   //Unit Price
                ];

                $isEurope && $line[] = $value['service_fee_per'] ?: 0;                      //Service Fee Per Unit
                $enableQuote && $line[] = -($value['amount_price_per'] ?: 0);               //Price Discount Per Unit
                $isEurope && $enableQuote && $line[] = -$value['amount_service_fee_per'];   //Service Fee Discount Per Unit
                $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
                if ($isCollectionFromDomicile) {
                    $line_freight_per = $value['package_fee'];
                } else {
                    $line_freight_per = $value['freight_per'] + $value['package_fee'];
                }
                $line_total = bcsub(bcmul(bcadd($value['unit_price'] + $line_freight_per, $value['service_fee_per'] ?: 0, $precision), $value['quantity'], $precision), $value['amount'], $precision);
                //12591 B2B记录各国别用户的操作时间
                $date_added = changeOutPutByZone($value['date_added'], $this->session);
                //12591 end

                $line[] = $line_freight_per;                                                //Freight Per Unit
                $line[] = $line_total;                                                      //Total
                $line[] = $value['poundage'];                                               //Transaction Fee
                $line[] = $value['payment_method'];                                         //Payment Method
                $line[] = "\t" . $date_added . "\t";                                        //Purchase Date
                $line[] = "\t" . $value['order_id'] . "\t";                                 //Sales Order ID
                $line[] = 'No';                                                             //Is Return


                $total_amount = bcadd($total_amount, $line_total, $precision);
                $total_amount = bcadd($total_amount, $value['poundage'], $precision);

                fputcsv($fp, $line);
                if (isset($value['rma_list']) && $value['rma_list'] != null) {
                    //需要做一个rma
                    unset($v);
                    foreach ($value['rma_list'] as $k => $v) {
                        $total_qty -= $v['quantity'];
                        $total_amount -= $v['actual_refund_amount'];
                        if ($v['refund_type'] == 1) {
                            $refund_type = 'Line Of Credit'; //1:返信用额度2：返优惠券
                        } else {
                            $refund_type = '';
                        }

                        $line = [
                            $value['purchase_order_id'],                    //Purchase Order ID
                            html_entity_decode($value['screenname']),                           //Store Name
                            $value['item_code'],                            //Item Code
                            html_entity_decode($value['product_name']),     //Product Name
                            -$v['quantity'],                                //Purchase Quantity
                            '',                                             //Unit Price
                            ''
                        ];

                        $isEurope && $line[] = '';                          //Service Fee Per Unit
                        $enableQuote && $line[] = '';                       //Price Discount Per Unit
                        $isEurope && $enableQuote && $line[] = '';           //Service Fee Discount Per Unit
                        //12591 B2B记录各国别用户的操作时间
                        //如果是 即重发又退款 的订单，Seller只同意退款，则 取退款时间；
                        $processed_date = changeOutPutByZone(($v['processed_date']) ? ($v['processed_date']) : ($v['credit_date_added']), $this->session);
                        //12591 end

                        $line[] = -$v['actual_refund_amount'];              //Total
                        $line[] = '';                                       //Transaction Fee
                        $line[] = $refund_type;                             //Payment Method
                        $line[] = "\t" . $processed_date;                     //Purchase Date
                        $line[] = ($v['from_customer_order_id']) ? "\t" . $v['from_customer_order_id'] : '';  //Sales Order ID
                        $line[] = 'Yes';                                    //Is Return
                        //$line[] = $v['rma_name'];                         //RMA Type（Reshipment、Refund）//隐藏该列
                        $line[] = ($v['rma_order_id']) ? "\t" . $v['rma_order_id'] : '';//RMA ID

                        fputcsv($fp, $line);
                    }
                    unset($v);
                }

            }
            unset($value);

            $line = [
                '',
                '',
                '',
                'Total Purchase Quantity：',
                $total_qty,
                '',
                ''
            ];

            $isEurope && $line[] = '';
            $enableQuote && $line[] = '';
            $isEurope && $enableQuote && $line[] = '';
            array_pop($line);
            array_push($line, 'Total：', $total_amount, '', '', '', '', '');

            fputcsv($fp, $line);
            $output = stream_get_contents($fp);
            fclose($fp);
            return $output;
        }

        public function  getCurrencyByCountryId($country_id){
            $arr =[
                '81'    => 'EUR',
                '107'   => 'JPY',
                '222'   => 'GBP',
                '223'   => 'USD',
            ];
            if(isset($arr[$country_id])){
                return '('.$arr[$country_id].')';
            }
            return '';

        }
        protected  function  setCsvHeader($fileName){
            //header('Content-Encoding: UTF-8');
            //header("Content-Type: text/csv; charset=UTF-8");
            header("Content-Type: text/csv");
            header("Content-Disposition: attachment; filename=\"".$fileName."\"");
            header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
            header('Expires:0');
            header('Pragma:public');

        }

        /**
         * [readCsvLines description] 读取csv 文件 copy by customer_order
         * @param string $csv_file
         * @param int $lines
         * @param int $offset
         * @return array
         */

        public function readCsvLines($csvFile , $offset = 0){
            ini_set('auto_detect_line_endings', true);
            $encodeType = $this->detect_encoding($csvFile);
            if($encodeType == false){
                return false;
            }
            if (!$fp = fopen($csvFile, 'r')) {
                return false;
            }
            $i = $j = 0;
            $line = null;
            if($offset != 0){
                while (false !== ($line = fgets($fp))) {
                    if ($i++ < $offset) {
                        continue;
                    }
                    break;
                }
            }
            $data = array();
            while (!feof($fp)) {
                $data[] = fgetcsv($fp);
            }
            if($offset == 0){
                $i = 1;
                $line = implode(',',$data[0]);
                unset($data[0]);
                $data = array_values($data);
            }


            fclose($fp);
            $values = array();
            $line = preg_split("/,/", $line);
            $keys = array();
            $flag = true;

            if($data[0]){
                foreach ($data as $d) {
                $entity = array();
                if (empty($d)) {
                    continue;
                }
                for ($i = 0; $i < count($line); $i++) {
                    if ($i < count($d)) {
                        if($i == 0){
                            $line[$i] = ltrim($line[$i], chr(0xEF).chr(0xBB).chr(0xBF));
                        }
                        $entity[trim($line[$i])] = trim(trim(iconv($encodeType,'UTF-8',$d[$i]),PHP_EOL));
                        if ($flag) {
                            $keys[] = trim($line[$i]);
                        }
                    }
                }
                if ($flag) {
                    $flag = false;
                }
                $values[] = $entity;
            }
            }else{
                for ($i = 0; $i < count($line); $i++) {

                    if($i == 0){
                        $line[$i] = ltrim($line[$i], chr(0xEF).chr(0xBB).chr(0xBF));
                    }

                    if ($flag) {
                        $keys[] = trim($line[$i]);
                    }
                    $values = [];

                }
            }
            $result = array(
                "keys" => $keys,
                "values" => $values
            );
            return $result;


        }
        public function detect_encoding($file_path) {
            $list = array('GBK', 'UTF-8', 'UTF-16LE', 'UTF-16BE', 'ISO-8859-1');
            $str = $this->fileToSrting($file_path);
            foreach ($list as $item) {
                $tmp = mb_convert_encoding($str, $item, $item);
                if (md5($tmp) == md5($str)) {
                    return $item;
                }
            }
            return false;
        }
        public function fileToSrting($file_path, $filesize = '') {
            //判断文件路径中是否含有中文，如果有，那就对路径进行转码，如此才能识别
            if (preg_match("/[\x7f-\xff]/", $file_path)) {
                $file_path = iconv('UTF-8', 'GBK', $file_path);
            }
            if (file_exists($file_path)) {
                $fp = fopen($file_path, "r");
                if ($filesize === '') {
                    $filesize = filesize($file_path);
                }
                $str = fread($fp, $filesize); //指定读取大小，这里默认把整个文件内容读取出来
                return $str = str_replace("\r\n", "<br />", $str);
            } else {
                die('文件路径错误！');
            }
        }

        /**
         * 生成csv文件，保存在storage/download/tmpCsv下
         * 返回下载地址
         * @param $csvtitle
         * @param $csvdata
         * @return string
         */
        public function createCsvFile($csvtitle = [], $csvdata = [])
        {
            $filepath = DIR_DOWNLOAD . 'tmpCsv/';
            $filename = md5(time() . rand(100, 200)) . '.csv';
            $header = implode(",", $csvtitle);
            $header = iconv('UTF-8', 'GBK//IGNORE', $header);
            $header = explode(",", $header);
            if (!is_dir($filepath)) {
                mkdir($filepath);
            }
            $fp = fopen($filepath . $filename, 'w+');
            fputcsv($fp, $header);
            foreach ($csvdata as $row) {
                $tmp = array();
                foreach ($row as $it) {
                    $it = iconv('UTF-8', 'GBK//IGNORE', $it."\t");
                    $tmp[] = $it;
                }
                fputcsv($fp, $tmp);
            }
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
            return HTTPS_SERVER . 'storage/download/tmpCsv/' . $filename;
        }


        public function getPurchaseOrderFilterCsvTemp($fileName,$data,$user_number){
            //需求 rma退款的
            $country_id =  $this->orm->table(DB_PREFIX.'customer')->where('user_number',$user_number)->value('country_id');
            $fp = fopen($fileName,'a');
            $bom =  chr(0xEF).chr(0xBB).chr(0xBF);
            fwrite($fp,$bom);
            if($country_id == 223){
                //美国不包含服务费
                $head = [
                    'Purchase Order ID', //sku
                    'Store Name', //name
                    'Item Code',
                    'Product Name',
                    'Purchase Quantity',
                    'Unit Price',
                    'Discounted amount per unit',
                    'Total Amount',
                    'Transaction Fee',
                    'Payment Method',
                    'Purchase Date',
                    'Sales order ID',
                    'is Return',
                ];
            }elseif($country_id == 107){
                //日本 Quote Discount Per Unit 没有此项
                $head = [
                    'Purchase Order ID', //sku
                    'Store Name', //name
                    'Item Code',
                    'Product Name',
                    'Purchase Quantity',
                    'Unit Price',
                    //'Discounted amount per unit',
                    'Total Amount',
                    'Transaction Fee',
                    'Payment Method',
                    'Purchase Date',
                    'Sales order ID',
                    'is Return',
                ];

            }
            else{
                //欧洲
                $head = [
                    'Purchase Order ID', //sku
                    'Store Name', //name
                    'Item Code',
                    'Product Name',
                    'Purchase Quantity',
                    'Unit Price',
                    'Service Fee',
                    'Total Amount',
                    'Transaction Fee',
                    'Payment Method',
                    'Purchase Date',
                    'Sales order ID',
                    'is Return',
                ];
            }

            fputcsv($fp,$head);
            $total_qty = 0;
            $total_amount = 0;
            foreach ($data as $key => $value){
                $total_qty += $value['quantity'];
                if($value['order_id'] != ''){
                    $value['order_id'] = "'".$value['order_id'];
                }
                if($country_id == 223){
                    $total_amount += sprintf( '%.2f',($value['unit_price']- $value['quote_discount'])*$value['quantity']);
                    $line = [
                        $value['purchase_order_id'],
                        $value['screenname'],
                        $value['item_code'],
                        html_entity_decode($value['product_name']),
                        $value['quantity'],
                        $value['unit_price'],
                        $value['quote_discount'],
                        sprintf( '%.2f',($value['unit_price']- $value['quote_discount'])*$value['quantity']),
                        $value['poundage'],
                        $value['payment_method'],
                        $value['date_added'],
                        $value['order_id'],
                        'No',
                    ];
                }elseif ($country_id == 107){
                    //此情况下quote_discount为0
                    $total_amount += sprintf( '%.2f',($value['unit_price']- $value['quote_discount'])*$value['quantity']);
                    $line = [
                        $value['purchase_order_id'],
                        $value['screenname'],
                        $value['item_code'],
                        html_entity_decode($value['product_name']),
                        $value['quantity'],
                        $value['unit_price'],
                        //$value['quote_discount'],
                        sprintf( '%.2f',($value['unit_price']- $value['quote_discount'])*$value['quantity']),
                        $value['poundage'],
                        $value['payment_method'],
                        $value['date_added'],
                        $value['order_id'],
                        'No',
                    ];

                }else{
                    $total_amount += sprintf( '%.2f',($value['unit_price']*$value['quantity'] + $value['service_fee']));
                    $line = [
                        $value['purchase_order_id'],
                        $value['screenname'],
                        $value['item_code'],
                        html_entity_decode($value['product_name']),
                        $value['quantity'],
                        $value['unit_price'],
                        sprintf( '%.2f',$value['service_fee']/$value['quantity']),
                        sprintf( '%.2f',($value['unit_price']*$value['quantity'] + $value['service_fee'])),
                        $value['poundage'],
                        $value['payment_method'],
                        $value['date_added'],
                        $value['order_id'],
                        'No',
                    ];
                }
                fputcsv($fp, $line);
                if(isset($value['rma_list']) && $value['rma_list'] != null){
                    //需要做一个rma
                    foreach($value['rma_list'] as $k => $v){
                        $total_qty -= $v['quantity'];
                        $total_amount -= $v['refund_amount'];
                        if($v['refund_type'] == 1){
                            $refund_type = 'Line Of Credit'; //1:返信用额度2：返优惠券
                        }else{
                            $refund_type = '';
                        }
                        if($country_id == 107){
                            // 日本国度少了一行
                            $line  = [
                                $value['purchase_order_id'],
                                $value['screenname'],
                                $value['item_code'],
                                html_entity_decode($value['product_name']),
                                0-$v['quantity'],
                                //'',
                                '',
                                0-$v['refund_amount'],
                                '',
                                $refund_type,
                                $v['processed_date'],
                                '',
                                'Yes',
                            ];
                        }else{
                            $line  = [
                                $value['purchase_order_id'],
                                $value['screenname'],
                                $value['item_code'],
                                html_entity_decode($value['product_name']),
                                0-$v['quantity'],
                                '',
                                '',
                                0-$v['refund_amount'],
                                '',
                                $refund_type,
                                $v['processed_date'],
                                '',
                                'Yes',
                            ];
                        }
                        fputcsv($fp, $line);
                    }
                }

            }
            //增加一个最后一行的统计数据
            if($country_id == 107){
                $line = [
                    '',
                    '',
                    '',
                    'Total Purchase Quantity：',
                    $total_qty,
                    //'',
                    'Total Amount：',
                    $total_amount,
                    '',
                    '',
                    '',
                    '',
                    '',
                ];
            }else{
                $line = [
                    '',
                    '',
                    '',
                    'Total Purchase Quantity：',
                    $total_qty,
                    '',
                    'Total Amount：',
                    $total_amount,
                    '',
                    '',
                    '',
                    '',
                    '',
                ];

            }
            fputcsv($fp, $line);
            fclose($fp);


        }

        /**
         * [getMappingHistorySkuInfo description] mappingSku的数据导出
         * @param $fileName
         * @param $platform_list
         * @param $approval_list
         * @param $data
         * @return boolean|string
         */
        public function getMappingHistorySkuInfo($fileName,$data,$platform_list,$approval_list){
            $this->setCsvHeader($fileName);
            //echo chr(239).chr(187).chr(191);
            $fp = fopen('php://output','a');
            //在写入的第一个字符串开头加 bom。
            $bom =  chr(0xEF).chr(0xBB).chr(0xBF);
            fwrite($fp,$bom);
            $head = [
                iconv ( 'utf-8', 'gbk', 'Store'), //sku
                iconv ( 'utf-8', 'gbk', 'Platform'), //sku
                iconv ( 'utf-8', 'gbk', 'Platform URL'), //name
                iconv ( 'utf-8', 'gbk', 'Platform SKU'),
                iconv ( 'utf-8', 'gbk', 'B2B Item Code'),
                iconv ( 'utf-8', 'gbk', 'Salesperson'),
                iconv ( 'utf-8', 'gbk', 'Last Modified'),
                iconv ( 'utf-8', 'gbk', 'Approval Status' ),
            ];
            fputcsv($fp,$head);
            foreach ($data as $key => $value){
                $line = [
                    $value['store_name'],
                    $platform_list[$value['platform']],
                    $value['asin'],
                    $value['sku'],
                    $value['b2b_sku'],
                    $value['salesperson_name'],
                    $value['update_time'],
                    $approval_list[$value['approval_status']],


                ];
                fputcsv($fp, $line);
            }
            $output = stream_get_contents($fp);
            fclose($fp);
            return $output;

        }


    }



?>
