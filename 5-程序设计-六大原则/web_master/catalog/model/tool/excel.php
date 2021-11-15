<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ModelToolExcel extends Model {
    protected $spreadsheet;
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
       $this->spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    }

    public function  unlinkWorkSheets(){
        $this->spreadsheet->disconnectWorksheets();
        unset($this->spreadsheet);
    }

    /**
     * [getExcelData description]
     * @param $file_name
     * @return void
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function setExcelData($file_name){
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置字体 大小 粗细
        $worksheet->getStyle('A7:B7')->getFont()->setBold(true)->setName('Arial')
            ->setSize(10);;
        $worksheet->getStyle('B1')->getFont()->setBold(true);
        $worksheet->getStyle('A4')->getFont()->getColor()
            ->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED);
        $worksheet->setCellValue('A7', 'www.helloweba.net');

        //->setValueExplicit(
        //    '25',
        //    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_INLINE
        //);


        $allColumn = 'A1:' . 'K' . $column_elements;
        $objPHPExcel->setActiveSheetIndex(0)->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $objPHPExcel->setActiveSheetIndex(0)->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);
        //$sheet = $this->spreadsheet->createSheet();
        //$this->spreadsheet->setactivesheetindex(1);
        //$this->spreadsheet->getActiveSheet()->setTitle('1111');
        //$this->spreadsheet->getActiveSheet()->setCellValue('A8', 'www.helloweba.net');
        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $file_name = '11  我的.xlsx';
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }

    /**
     * [setBillToBePaidExcel description]
     * @param $file_name
     * @param $data
     * @param int $country_id
     * @param int $type
     * @return string
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function setBillToBePaidExcel($file_name,$data,$country_id,$type = 0){
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->setTitle('Monthly');
        $worksheet->getStyle('A1:L1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(20);
        $worksheet->getColumnDimension('B')->setWidth(20);
        $worksheet->getColumnDimension('C')->setWidth(25);
        $worksheet->getColumnDimension('D')->setWidth(10);
        $worksheet->getColumnDimension('E')->setWidth(10);
        $worksheet->getColumnDimension('F')->setWidth(10);
        $worksheet->getColumnDimension('G')->setWidth(25);
        $worksheet->getColumnDimension('H')->setWidth(10);
        $worksheet->getColumnDimension('I')->setWidth(20);
        $worksheet->getColumnDimension('J')->setWidth(25);
        $worksheet->getColumnDimension('K')->setWidth(15);
        $worksheet->getColumnDimension('L')->setWidth(15);

        $worksheet->setCellValue('A1', 'month of charge'); //设置列的值
        $worksheet->setCellValue('B1', 'item code'); //设置列的值
        $worksheet->setCellValue('C1', 'product name'); //设置列的值
        $worksheet->setCellValue('D1', 'length'); //设置列的值
        $worksheet->setCellValue('E1', 'width'); //设置列的值
        $worksheet->setCellValue('F1', 'height'); //设置列的值
        $worksheet->setCellValue('G1', 'measurement units'); //设置列的值
        $worksheet->setCellValue('H1', 'volume'); //设置列的值
        $worksheet->setCellValue('I1', 'volume units'); //设置列的值
        $worksheet->setCellValue('J1', 'product type'); //设置列的值
        $worksheet->setCellValue('K1', 'storage fee due'); //设置列的值
        $worksheet->setCellValue('L1', 'currency'); //设置列的值

        $column_elements = 2;
        $product_type = [];
        $sum_monthly = 0;

        //根据type不同有一个栏位需要更改
        if($type == 0){
            $paid_column = 'total amount to be paid';
        }else{
            $paid_column = 'total paid amount';
        }
        foreach($data['monthly'] as $key => $value){
            // inches 2.54cm
            // 转立方米
            $length = $value['length']*2.54/100;
            $width  = $value['width']*2.54/100;
            $height = $value['height']*2.54/100;
            $volume =  sprintf('%.4f',$height*$length*$width);
            // product_type
            $str = '';
            if($value['combo_flag'] == 1){
                $str .= 'combo、';
            }
            if($value['part_flag'] == 1){
                $str .= 'part、';
            }
            if($value['over_size_flag'] == true){
                $str .= 'oversized、';
            }
            if($str == ''){
                $str = 'general';
            }
            $str = trim($str,'、');
            $product_type[$value['product_id']] = $str;
            $sum_monthly += $value['all_storage_fee'];
            //金额

            $worksheet
                ->setCellValue('A' . $column_elements, $data['bill_time'])
                ->setCellValue('B' . $column_elements, $value['item_code'])
                ->setCellValue('C' . $column_elements,  html_entity_decode($value['name']))
                ->setCellValue('D' . $column_elements, $value['length'])
                ->setCellValue('E' . $column_elements, $value['width'])
                ->setCellValue('F' . $column_elements, $value['height'])
                ->setCellValue('G' . $column_elements, 'inches')
                ->setCellValue('H' . $column_elements, $volume)
                ->setCellValue('I' . $column_elements, 'cubic meters')
                ->setCellValue('J' . $column_elements, $str)
                ->setCellValue('K' . $column_elements, $value['all_storage_fee'])
                ->setCellValue('L' . $column_elements, $this->session->data['currency']);

            $column_elements++;

        }
        $worksheet->setCellValue('J' . $column_elements, 'total amount due');
        $worksheet->setCellValue('K' . $column_elements, $sum_monthly);
        $worksheet->setCellValue('L' . $column_elements, $this->session->data['currency']);
        $column_elements++;
        $worksheet->setCellValue('J' . $column_elements, 'deduction');
        $worksheet->setCellValue('K' . $column_elements, '-'.$sum_monthly);
        $worksheet->setCellValue('L' . $column_elements, $this->session->data['currency']);
        $column_elements++;
        $worksheet->setCellValue('J' . $column_elements, $paid_column);
        $worksheet->setCellValue('K' . $column_elements, 0.00);
        $worksheet->setCellValue('L' . $column_elements, $this->session->data['currency']);

        $allColumn = 'A1:' . 'L' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        //第二个表格 一生二，二生三 ，三生万物
        $this->spreadsheet->createSheet();
        $this->spreadsheet->setactivesheetindex(1);
        $current_sheet = $this->spreadsheet->getActiveSheet();

        $current_sheet->freezePane('A2');
        $current_sheet->setTitle('Daily');
        $current_sheet->getStyle('A1:O1')->getFont()->setBold(true);
        $current_sheet->getColumnDimension('A')->setWidth(20);
        $current_sheet->getColumnDimension('B')->setWidth(20);
        $current_sheet->getColumnDimension('C')->setWidth(25);
        $current_sheet->getColumnDimension('D')->setWidth(10);
        $current_sheet->getColumnDimension('E')->setWidth(10);
        $current_sheet->getColumnDimension('F')->setWidth(10);
        $current_sheet->getColumnDimension('G')->setWidth(25);
        $current_sheet->getColumnDimension('H')->setWidth(10);
        $current_sheet->getColumnDimension('I')->setWidth(20);
        $current_sheet->getColumnDimension('J')->setWidth(25);
        $current_sheet->getColumnDimension('K')->setWidth(15);
        $current_sheet->getColumnDimension('L')->setWidth(15);
        $current_sheet->getColumnDimension('M')->setWidth(15);
        $current_sheet->getColumnDimension('N')->setWidth(15);
        $current_sheet->getColumnDimension('O')->setWidth(15);

        $current_sheet->setCellValue('A1', 'date'); //设置列的值
        $current_sheet->setCellValue('B1', 'category'); //设置列的值
        $current_sheet->setCellValue('C1', 'item code'); //设置列的值
        $current_sheet->setCellValue('D1', 'product name'); //设置列的值
        $current_sheet->setCellValue('E1', 'length'); //设置列的值
        $current_sheet->setCellValue('F1', 'width'); //设置列的值
        $current_sheet->setCellValue('G1', 'height'); //设置列的值
        $current_sheet->setCellValue('H1', 'measurement units'); //设置列的值
        $current_sheet->setCellValue('I1', 'inventory'); //设置列的值
        $current_sheet->setCellValue('J1', 'item volume'); //设置列的值
        $current_sheet->setCellValue('K1', 'total volume'); //设置列的值
        $current_sheet->setCellValue('L1', 'volume units'); //设置列的值
        $current_sheet->setCellValue('M1', 'storage rate'); //设置列的值
        $current_sheet->setCellValue('N1', 'storage fee due'); //设置列的值
        $current_sheet->setCellValue('O1', 'currency'); //设置列的值

        $column_elements = 2;
        $sum_daily = 0;
        foreach($data['daily'] as $key => $value){
            // inches 2.54cm
            // 转立方米
            $length = $value['length']*2.54/100;
            $width  = $value['width']*2.54/100;
            $height = $value['height']*2.54/100;
            $volume =  sprintf('%.4f',$height*$length*$width);
            $sum_daily += $value['storage_fee'];
            //金额
            //根据 onhand_days 来做去区分


            //if($value['onhand_days'] > 365){
            //
            //    $category = '365-∞';
            //    $storage_rate = $this->orm->table('tb_sys_customer_bill_fee')
            //        ->where(['country_id'=> $country_id, 'min_day' => 365])->value('storage_fee');
            //}else{
            //    $info = $this->orm->table('tb_sys_customer_bill_fee')
            //        ->where([
            //            ['country_id','=',$country_id],
            //            ['min_day','<',$value['onhand_days']],
            //            ['max_day','>=',$value['onhand_days']],
            //        ])
            //        ->get()
            //        ->map(
            //            function ($value) {
            //                return (array)$value;
            //            })
            //        ->toArray();
            //    $info = current($info);
            //    $category = $info['min_day'].'-'.$info['max_day'];
            //    $storage_rate = $info['storage_fee'];
            //
            //}

            //查询很慢 决定
            if($country_id == 223){
                if($value['onhand_days'] <= 90){
                    $category = '0-90';
                    $storage_rate = 0.3;
                }elseif ($value['onhand_days'] <= 365){
                    $category = '91-365';
                    $storage_rate = 0.45;
                }elseif ($value['onhand_days'] > 365){
                    $category = '365-∞';
                    $storage_rate = 1.5;

                }
            }elseif ($country_id == 81){

                if($value['onhand_days'] <= 60){
                    $category = '0-60';
                    $storage_rate = 0.3;
                }elseif ($value['onhand_days'] <= 365){
                    $category = '60-365';
                    $storage_rate = 0.45;
                }elseif ($value['onhand_days'] > 365){
                    $category = '365-∞';
                    $storage_rate = 1.5;

                }

            }elseif ($country_id == 107){

                if($value['onhand_days'] <= 31){
                    $category = '0-31';
                    $storage_rate = 48;
                }elseif ($value['onhand_days'] <= 90){
                    $category = '32-90';
                    $storage_rate = 96;
                }elseif ($value['onhand_days'] <= 365){
                    $category = '91-365';
                    $storage_rate = 128;
                }elseif ($value['onhand_days'] > 365){
                    $category = '365-∞';
                    $storage_rate = 240;

                }

            }elseif ($country_id == 222){

                if($value['onhand_days'] <= 60){
                    $category = '0-60';
                    $storage_rate = 0.4;
                }elseif ($value['onhand_days'] <= 365){
                    $category = '60-365';
                    $storage_rate = 0.6;
                }elseif ($value['onhand_days'] > 365){
                    $category = '365-∞';
                    $storage_rate = 2;

                }

            }

            $current_sheet
                ->setCellValue('A' . $column_elements, $value['storage_time'])
                ->setCellValue('B' . $column_elements, $category)
                ->setCellValue('C' . $column_elements, $value['item_code'])
                ->setCellValue('D' . $column_elements,  html_entity_decode($value['name']))
                ->setCellValue('E' . $column_elements, $value['length'])
                ->setCellValue('F' . $column_elements, $value['width'])
                ->setCellValue('G' . $column_elements, $value['height'])
                ->setCellValue('H' . $column_elements, 'inches')
                ->setCellValue('I' . $column_elements, $value['onhand_qty'])
                ->setCellValue('J' . $column_elements, $volume)
                ->setCellValue('K' . $column_elements, sprintf('%.4f',$volume*$value['onhand_qty']))
                ->setCellValue('L' . $column_elements, 'cubic meters')
                ->setCellValue('M' . $column_elements,  $storage_rate)
                ->setCellValue('N' . $column_elements, $value['storage_fee'])
                ->setCellValue('O' . $column_elements, $this->session->data['currency']);

            $column_elements++;

        }
        $current_sheet->setCellValue('M' . $column_elements, 'total amount due');
        $current_sheet->setCellValue('N' . $column_elements, $sum_daily);
        $current_sheet->setCellValue('O' . $column_elements, $this->session->data['currency']);
        $column_elements++;
        $current_sheet->setCellValue('M' . $column_elements, 'deduction');
        $current_sheet->setCellValue('N' . $column_elements, '-'.$sum_daily);
        $current_sheet->setCellValue('O' . $column_elements, $this->session->data['currency']);
        $column_elements++;
        $current_sheet->setCellValue('M' . $column_elements, $paid_column);
        $current_sheet->setCellValue('N' . $column_elements, 0.00);
        $current_sheet->setCellValue('O' . $column_elements, $this->session->data['currency']);

        $allColumn = 'A1:' . 'O' . $column_elements;
        $current_sheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $current_sheet->getStyle("$allColumn")->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xls($this->spreadsheet);
        //$this->getBrowerCompatible($file_name);
        //$savePath = 'php://output';
        $path_return = 'bill_excel/'.date('Y-m-d',time()).'/' .$file_name;
        $dir_upload = DIR_STORAGE.'bill_excel/'.date('Y-m-d',time()).'/';
        if (!is_dir($dir_upload)) {
            mkdir(iconv("UTF-8", "GBK", $dir_upload), 0777, true);
        }
        $savePath = $dir_upload.$file_name;
        $writer->save($savePath);
        $this->unlinkWorkSheets();
        return $path_return;

    }


    /**
     * [setPictureExcelData description]
     * @param $data
     * @param $file_name
     * @return void
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function setPictureExcelData($data,$file_name){
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:D1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(15);
        $worksheet->getColumnDimension('D')->setWidth(15);


        $worksheet->setCellValue('A1', "Product_id"); //设置列的值
        $worksheet->setCellValue('B1', "Sku"); //设置列的值
        $worksheet->setCellValue('C1', "图片"); //设置列的值
        $worksheet->setCellValue('D1', "图片后缀"); //设置列的值


        $column_elements = 2;

        foreach($data as $key => $value){
            if(isset($value['matchs'])){
                foreach ($value['matchs'][0] as $k => $v){
                    $worksheet
                        ->setCellValue('A' . $column_elements, $value['product_id'])
                        ->setCellValue('B' . $column_elements, $value['sku'])
                        ->setCellValue('C' . $column_elements, substr($v,5))
                        ->setCellValue('D' . $column_elements, $value['matchs'][1][$k]);
                        $column_elements++;

                }
            }

            if(isset($value['image_list'])){
                foreach ($value['image_list']as $k => $v){
                    if($v['image']!= ''){
                        if(stristr($v['image'],'http') == false){
                            $image = HTTPS_SERVER . '/image/'.$v['image'];
                        }else{
                            $image = $v['image'];
                        }
                        $worksheet
                            ->setCellValue('A' . $column_elements, $value['product_id'])
                            ->setCellValue('B' . $column_elements, $value['sku'])
                            ->setCellValue('C' . $column_elements, $image)
                            ->setCellValue('D' . $column_elements, substr($image,strrpos($image,'.')+1));
                        $column_elements++;
                    }

                }
            }





        }
        $allColumn = 'A1:' . 'D' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }

    /**
     * [setProduductCustomerInfo description] 设置产品的sku和customer_id
     * @param $data
     * @param $file_name
     * @return void
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function setProduductCustomerInfo($data,$file_name){
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:C1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(15);

        $worksheet->setCellValue('A1', "sku"); //设置列的值
        $worksheet->setCellValue('B1', "product_id"); //设置列的值
        $worksheet->setCellValue('C1', "customer_id"); //设置列的值
        $column_elements = 2;

        foreach($data as $key => $value){
            $worksheet
                ->setCellValue('A' . $column_elements, $value['sku'])
                ->setCellValue('B' . $column_elements, $value['product_id'])
                ->setCellValue('C' . $column_elements, $value['customer_id']);
            $column_elements++;
        }
        $allColumn = 'A1:' . 'C' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }


    public function setSalesOrderManagementExcel($head,$data,$file_name){
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        //下次写个方法自动获取数字和字母的转换关系

        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:Q1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(20);
        $worksheet->getColumnDimension('C')->setWidth(20);
        $worksheet->getColumnDimension('D')->setWidth(20);
        $worksheet->getColumnDimension('E')->setWidth(20);
        $worksheet->getColumnDimension('F')->setWidth(20);
        $worksheet->getColumnDimension('G')->setWidth(20);
        $worksheet->getColumnDimension('H')->setWidth(20);
        $worksheet->getColumnDimension('I')->setWidth(20);
        $worksheet->getColumnDimension('J')->setWidth(20);
        $worksheet->getColumnDimension('K')->setWidth(20);
        $worksheet->getColumnDimension('L')->setWidth(20);
        $worksheet->getColumnDimension('M')->setWidth(20);
        $worksheet->getColumnDimension('N')->setWidth(30);
        $worksheet->getColumnDimension('O')->setWidth(30);

        $worksheet->setCellValueExplicit('A1',  $head[0],DataType::TYPE_STRING2); //设置列的值
        $worksheet->setCellValueExplicit('B1',  $head[1],DataType::TYPE_STRING2); //设置列的值
        $worksheet->setCellValueExplicit('C1',  $head[2],DataType::TYPE_STRING2); //设置列的值
        $worksheet->setCellValueExplicit('D1',  $head[3],DataType::TYPE_STRING2); //设置列的值
        $worksheet->setCellValueExplicit('E1',  $head[4],DataType::TYPE_STRING2); //设置列的值
        $worksheet->setCellValueExplicit('F1',  $head[5],DataType::TYPE_STRING2); //设置列的值
        $worksheet->setCellValueExplicit('G1',  $head[6],DataType::TYPE_STRING2); //设置列的值
        $worksheet->setCellValueExplicit('H1',  $head[7],DataType::TYPE_STRING2); //设置列的值
        $worksheet->setCellValueExplicit('I1',  $head[8],DataType::TYPE_STRING2); //设置列的值
        $worksheet->setCellValueExplicit('J1',  $head[9],DataType::TYPE_STRING2); //设置列的值
        $worksheet->setCellValueExplicit('K1',  $head[10],DataType::TYPE_STRING2); //设置列的值
        $worksheet->setCellValueExplicit('L1',  $head[11],DataType::TYPE_STRING2); //设置列的值
        $worksheet->setCellValueExplicit('M1',  $head[12],DataType::TYPE_STRING2); //设置列的值
        $worksheet->setCellValueExplicit('N1', $head[13], DataType::TYPE_STRING2); //设置列的值
        isset($head[14]) && $worksheet->setCellValueExplicit('O1', $head[14], DataType::TYPE_STRING2); //设置列的值
       // $worksheet->setCellValueExplicit('N1',  $head[13],DataType::TYPE_STRING2); //设置列的值
        $column_elements = 2;

        $index_1 = 14;
        $index_2 = 9;
        if (customer()->isUSA()) {
            $index_1++;
            $index_2++;
        }
        foreach($data as $key => $value){
            if($value[$index_1] == 1){
                $worksheet->setCellValueExplicit('F' . $column_elements, $value[5],DataType::TYPE_STRING2)
                    ->setCellValueExplicit('G' . $column_elements, $value[6],DataType::TYPE_STRING2)
                    ->setCellValueExplicit('H' . $column_elements, $value[7],DataType::TYPE_STRING2);
            }elseif($value[$index_1] > 1){
                if($value[$index_2] > 1){
                    $worksheet->mergeCells('F' .$column_elements .':F'.($column_elements + (int)$value[9] -1 ) );
                    $worksheet->mergeCells('G' .$column_elements .':G'.($column_elements + (int)$value[9] -1 ) );
                    $worksheet->mergeCells('H' .$column_elements .':H'.($column_elements + (int)$value[9] -1 ) );
                }
                $worksheet->setCellValueExplicit('F' . $column_elements, $value[5],DataType::TYPE_STRING2)
                    ->setCellValueExplicit('G' . $column_elements, $value[6],DataType::TYPE_STRING2)
                    ->setCellValueExplicit('H' . $column_elements, $value[7],DataType::TYPE_STRING2);
            }
            $worksheet
                ->setCellValueExplicit('A' . $column_elements, $value[0],DataType::TYPE_STRING2)
                ->setCellValueExplicit('B'.$column_elements,$value[1], DataType::TYPE_STRING2)
                ->setCellValueExplicit('C' . $column_elements, $value[2],DataType::TYPE_STRING2)
                ->setCellValueExplicit('D' . $column_elements, $value[3],DataType::TYPE_STRING2)
                ->setCellValueExplicit('E' . $column_elements, $value[4],DataType::TYPE_STRING2)
                ->setCellValueExplicit('I' . $column_elements, $value[8],DataType::TYPE_STRING2)
                ->setCellValueExplicit('J' . $column_elements, $value[9],DataType::TYPE_STRING2)
                ->setCellValueExplicit('K' . $column_elements, $value[10],DataType::TYPE_STRING2)
                ->setCellValueExplicit('L' . $column_elements, $value[11],DataType::TYPE_STRING2)
                ->setCellValueExplicit('M' . $column_elements, $value[12],DataType::TYPE_STRING2)
                ->setCellValueExplicit('N' . $column_elements, $value[13],DataType::TYPE_STRING2)
                ->setCellValueExplicit('O' . $column_elements, $value[13],DataType::TYPE_STRING2);
            if ($value[10] != 'N/A' && $value[9] == 'N/A' && $value[11] == 'No') {
                $worksheet->getStyle('K' . $column_elements)->getFont()->getColor()->setARGB('FFBFBFBF');
            }
            $column_elements++;
        }
        if(isset($head[13])){
            $allColumn = 'A1:' . 'N' . $column_elements;
        }else{
            $allColumn = 'A1:' . 'L' . $column_elements;
        }
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setWrapText(true);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xls($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }

    public function setTMInfo($data,$file_name){
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:C1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(15);

        $worksheet->setCellValue('A1', "user number"); //设置列的值
        $worksheet->setCellValue('B1', "name"); //设置列的值
        $worksheet->setCellValue('C1', "type"); //设置列的值
        $column_elements = 2;

        foreach($data as $key => $value){
            if(in_array($value['customer_group_id'],[24,25,26])){
                $type = '上门取货';
            }else{
                $type = '一件代发';
            }
            $worksheet
                ->setCellValue('A' . $column_elements, $value['user_number'])
                ->setCellValue('B' . $column_elements, $value['firstname'].' '.$value['lastname'])
                ->setCellValue('C' . $column_elements, $type );
            $column_elements++;
        }
        $allColumn = 'A1:' . 'C' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }


    public function setNewRmaInfoExcel($data,$file_name){
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:C1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(15);

        $worksheet->setCellValue('A1', "product_id"); //设置列的值
        $worksheet->setCellValue('B1', "return_rate"); //设置列的值

        $column_elements = 2;

        foreach($data as $key => $value){
            $worksheet
                ->setCellValue('A' . $column_elements, $value['product_id'])
                ->setCellValue('B' . $column_elements, $value['return_rate']);
            $column_elements++;
        }
        $allColumn = 'A1:' . 'C' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }

    public function hasImg($data,$file_name){
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:B1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);


        $worksheet->setCellValue('A1', "sku"); //设置列的值
        $worksheet->setCellValue('B1', "hasImg"); //设置列的值

        $column_elements = 2;

        foreach($data as $key => $value){
            $worksheet
                ->setCellValue('A' . $column_elements, $value['sku'])
                ->setCellValue('B' . $column_elements, $value['hasImg']==1?'Yes':'No');

            $column_elements++;
        }
        $allColumn = 'A1:' . 'B' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }

    public function setCategoryInfo($data,$file_name){
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:B1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(25);


        $worksheet->setCellValue('A1', "category_id "); //设置列的值
        $worksheet->setCellValue('B1', "类别"); //设置列的值

        $column_elements = 2;

        foreach($data as $key => $value){
            $worksheet
                ->setCellValue('A' . $column_elements, $key)
                ->setCellValue('B' . $column_elements, $value);
            $column_elements++;
        }
        $allColumn = 'A1:' . 'B' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }

    /**
     * [setProductExcelData description] 获取需要产品的信息
     * @param $data
     * @param $file_name
     * @return void
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function setProductExcelData($data,$file_name){
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:X1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(15);
        $worksheet->getColumnDimension('D')->setWidth(15);
        $worksheet->getColumnDimension('E')->setWidth(15);
        $worksheet->getColumnDimension('F')->setWidth(15);
        $worksheet->getColumnDimension('G')->setWidth(15);
        $worksheet->getColumnDimension('H')->setWidth(15);
        $worksheet->getColumnDimension('I')->setWidth(15);
        $worksheet->getColumnDimension('J')->setWidth(15);
        $worksheet->getColumnDimension('K')->setWidth(15);
        $worksheet->getColumnDimension('L')->setWidth(15);
        $worksheet->getColumnDimension('M')->setWidth(15);
        $worksheet->getColumnDimension('N')->setWidth(15);
        $worksheet->getColumnDimension('O')->setWidth(15);
        $worksheet->getColumnDimension('P')->setWidth(15);
        $worksheet->getColumnDimension('Q')->setWidth(15);
        $worksheet->getColumnDimension('R')->setWidth(15);
        $worksheet->getColumnDimension('S')->setWidth(15);
        $worksheet->getColumnDimension('T')->setWidth(15);
        $worksheet->getColumnDimension('U')->setWidth(15);
        $worksheet->getColumnDimension('V')->setWidth(15);
        $worksheet->getColumnDimension('W')->setWidth(15);
        $worksheet->getColumnDimension('X')->setWidth(15);

        $worksheet->setCellValue('A1', "sku"); //设置列的值
        $worksheet->setCellValue('B1', "mpn"); //设置列的值
        $worksheet->setCellValue('C1', "quantity"); //设置列的值
        $worksheet->setCellValue('D1', "price"); //设置列的值
        $worksheet->setCellValue('E1', "weight"); //设置列的值
        $worksheet->setCellValue('F1', "weight unit"); //设置列的值
        $worksheet->setCellValue('G1', "length"); //设置列的值
        $worksheet->setCellValue('H1', "width"); //设置列的值
        $worksheet->setCellValue('I1', "height"); //设置列的值
        $worksheet->setCellValue('J1', "length unit"); //设置列的值
        $worksheet->setCellValue('K1', "name"); //设置列的值
        $worksheet->setCellValue('L1', "description1"); //设置列的值
        $worksheet->setCellValue('M1', "description2"); //设置列的值
        $worksheet->setCellValue('N1', "description3"); //设置列的值
        $worksheet->setCellValue('O1', "meta description"); //设置列的值
        $worksheet->setCellValue('P1', "meta title"); //设置列的值
        $worksheet->setCellValue('Q1', "30-Day Sales"); //设置列的值
        $worksheet->setCellValue('R1', "Total Sales"); //设置列的值
        $worksheet->setCellValue('S1', "Page Views"); //设置列的值
        $worksheet->setCellValue('T1', "combo flag"); //设置列的值
        $worksheet->setCellValue('U1', "over size flag"); //设置列的值
        $worksheet->setCellValue('V1', "part flag"); //设置列的值
        $worksheet->setCellValue('W1', "imagepath"); //设置列的值
        $worksheet->setCellValue('X1', "category"); //设置列的值

        $column_elements = 2;

        foreach($data as $key => $value){
            if( $value['description_list'][1] == null || $value['description_list'][1] == false){
                $value['description_list'][1] = '';
            }else{
                $value['description_list'][1] = "'". $value['description_list'][1]."'";
            }

            if( $value['description_list'][2] == null || $value['description_list'][2] == false){
                $value['description_list'][2] = '';
            }else{
                $value['description_list'][2] = "'". $value['description_list'][2]."'";
            }


            $worksheet
                ->setCellValue('A' . $column_elements, $value['sku'])
                ->setCellValue('B' . $column_elements, $value['mpn'])
                ->setCellValue('C' . $column_elements, $value['quantity'])
                ->setCellValue('D' . $column_elements, 0)
                ->setCellValue('E' . $column_elements, $value['weight'])
                ->setCellValue('F' . $column_elements, $value['wcd_unit'])
                ->setCellValue('G' . $column_elements, $value['length'])
                ->setCellValue('H' . $column_elements, $value['width'])
                ->setCellValue('I' . $column_elements, $value['height'])
                ->setCellValue('J' . $column_elements, $value['lcd_unit'])
                ->setCellValue('K' . $column_elements, html_entity_decode($value['name']))
                ->setCellValue('L' . $column_elements, $value['description_list'][0])
                ->setCellValue('M' . $column_elements, $value['description_list'][1])
                ->setCellValue('N' . $column_elements, $value['description_list'][2])
                ->setCellValue('O' . $column_elements, html_entity_decode($value['meta_description']))
                ->setCellValue('P' . $column_elements, html_entity_decode($value['meta_title']))
                ->setCellValue('Q' . $column_elements, $value['30_sale_amount'])
                ->setCellValue('R' . $column_elements, $value['all_sale_amount'])
                ->setCellValue('S' . $column_elements, $value['viewed'])
                ->setCellValue('T' . $column_elements, $value['combo_flag'] == 1 ?'Yes':'No')
                ->setCellValue('U' . $column_elements, $value['over_size_tag'] == 1 ?'Yes':'No')
                ->setCellValue('V' . $column_elements, $value['part_flag'] == 1 ? 'Yes':'No')
                ->setCellValue('W' . $column_elements, $value['sku'].'.zip')
                ->setCellValue('X' . $column_elements, html_entity_decode($value['category']));
            $column_elements++;


        }
        $allColumn = 'A1:' . 'X' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }

    public function setModifiedTime($data,$file_name){
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:B1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);



        $worksheet->setCellValue('A1', "Sku"); //设置列的值
        $worksheet->setCellValue('B1', "Modified Time"); //设置列的值



        $column_elements = 2;

        foreach($data as $key => $value){


            $worksheet
                ->setCellValue('A' . $column_elements, $value['sku'])
                ->setCellValue('B' . $column_elements, $value['date_modified']);


            $column_elements++;


        }
        $allColumn = 'A1:' . 'B' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }

    /**
     * [getPurchaseOrderFilterExcel description]
     * @param $file_name
     * @param $data
     * @param bool $isCollectionFromDomicile
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function getPurchaseOrderFilterExcel($file_name, $data,$isCollectionFromDomicile){

        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:T1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(15);
        $worksheet->getColumnDimension('D')->setWidth(40);
        $worksheet->getColumnDimension('E')->setWidth(20);
        $worksheet->getColumnDimension('F')->setWidth(15);
        $worksheet->getColumnDimension('G')->setWidth(25);
        $worksheet->getColumnDimension('H')->setWidth(20);
        $worksheet->getColumnDimension('I')->setWidth(15);
        $worksheet->getColumnDimension('J')->setWidth(20);
        $worksheet->getColumnDimension('K')->setWidth(20);
        $worksheet->getColumnDimension('L')->setWidth(20);
        $worksheet->getColumnDimension('M')->setWidth(20);
        $worksheet->getColumnDimension('N')->setWidth(20);
        $worksheet->getColumnDimension('O')->setWidth(20);
        $worksheet->getColumnDimension('P')->setWidth(20);
        $worksheet->getColumnDimension('Q')->setWidth(20);
        $worksheet->getColumnDimension('R')->setWidth(20);
        $worksheet->getColumnDimension('T')->setWidth(20);

        $worksheet->setCellValue('A1', "Purchase Order ID"); //设置列的值
        $worksheet->setCellValue('B1', "Store Name"); //设置列的值
        $worksheet->setCellValue('C1', "Item Code"); //设置列的值
        $worksheet->setCellValue('D1', "Product Name"); //设置列的值
        $worksheet->setCellValue('E1', "Purchase Quantity"); //设置列的值
        $worksheet->setCellValue('F1', "Discounted Price"); //设置列的值
        if($data['isEurope'] && $data['enableQuote']){
            // 3 行
            $change_line = 3;
            $worksheet->setCellValue('G1', "Service Fee Per Unit"); //设置列的值
            $worksheet->setCellValue('H1', "Discount(OFF)"); //设置列的值
            $worksheet->setCellValue('I1', "Service Fee Discount Per Unit"); //设置列的值
            $worksheet->setCellValue('J1', "Fulfillment Per Unit"); //设置列的值
            $worksheet->setCellValue('K1', "Total"); //设置列的值
            $worksheet->setCellValue('L1', "Total Savings"); //设置列的值
            $worksheet->setCellValue('M1', "Final Total"); //设置列的值
            $worksheet->setCellValue('N1', "Transaction Fee"); //设置列的值
            $worksheet->setCellValue('O1', "Payment Method"); //设置列的值
            $worksheet->setCellValue('P1', "Purchase Date"); //设置列的值
            $worksheet->setCellValue('Q1', "Order Type"); //设置列的值
            $worksheet->setCellValue('R1', "Sales Order ID"); //设置列的值
            $worksheet->setCellValue('S1', "Is Return"); //设置列的值
            $worksheet->setCellValue('T1', "RMA ID"); //设置列的值
        }elseif($data['isEurope'] && !$data['enableQuote']){
            // 1 行
            $change_line = 1;
            $worksheet->setCellValue('G1', "Service Fee Per Unit"); //设置列的值
            $worksheet->setCellValue('H1', "Fulfillment Per Unit"); //设置列的值
            $worksheet->setCellValue('I1', "Total"); //设置列的值
            $worksheet->setCellValue('J1', "Total Savings"); //设置列的值
            $worksheet->setCellValue('K1', "Final Total"); //设置列的值
            $worksheet->setCellValue('L1', "Transaction Fee"); //设置列的值
            $worksheet->setCellValue('M1', "Payment Method"); //设置列的值
            $worksheet->setCellValue('N1', "Purchase Date"); //设置列的值
            $worksheet->setCellValue('O1', "Order Type"); //设置列的值
            $worksheet->setCellValue('P1', "Sales Order ID"); //设置列的值
            $worksheet->setCellValue('Q1', "Is Return"); //设置列的值
            $worksheet->setCellValue('R1', "RMA ID"); //设置列的值
        }elseif(!$data['isEurope'] && $data['enableQuote']){
            $change_line = 1;
            $worksheet->setCellValue('G1', "Discount(OFF)"); //设置列的值
            $worksheet->setCellValue('H1', "Fulfillment Per Unit"); //设置列的值
            $worksheet->setCellValue('I1', "Total"); //设置列的值
            $worksheet->setCellValue('J1', "Total Savings"); //设置列的值
            $worksheet->setCellValue('K1', "Final Total"); //设置列的值
            $worksheet->setCellValue('L1', "Transaction Fee"); //设置列的值
            $worksheet->setCellValue('M1', "Payment Method"); //设置列的值
            $worksheet->setCellValue('N1', "Purchase Date"); //设置列的值
            $worksheet->setCellValue('O1', "Order Type"); //设置列的值
            $worksheet->setCellValue('P1', "Sales Order ID"); //设置列的值
            $worksheet->setCellValue('Q1', "Is Return"); //设置列的值
            $worksheet->setCellValue('R1', "RMA ID"); //设置列的值
        }else{
            $change_line = 0;
            $worksheet->setCellValue('G1', "Fulfillment Per Unit"); //设置列的值
            $worksheet->setCellValue('H1', "Total"); //设置列的值
            $worksheet->setCellValue('I1', "Total Savings"); //设置列的值
            $worksheet->setCellValue('J1', "Final Total"); //设置列的值
            $worksheet->setCellValue('K1', "Transaction Fee"); //设置列的值
            $worksheet->setCellValue('L1', "Payment Method"); //设置列的值
            $worksheet->setCellValue('M1', "Purchase Date"); //设置列的值
            $worksheet->setCellValue('N1', "Order Type"); //设置列的值
            $worksheet->setCellValue('O1', "Sales Order ID"); //设置列的值
            $worksheet->setCellValue('P1', "Is Return"); //设置列的值
            $worksheet->setCellValue('Q1', "RMA ID"); //设置列的值
        }
        $precision = $data['isJapan'] ? 0 : 2;
        $isEurope = $data['isEurope'];
        $enableQuote = $data['enableQuote'];
        $column_elements = 2;
        $total_qty = 0;
        $total_amount = 0;
        unset($data['isJapan']);
        unset($data['isEurope']);
        unset($data['enableQuote']);
        foreach($data as $key => $value){
            $total_qty += $value['quantity'];
            if ($isCollectionFromDomicile) {
                $line_freight_per = $value['package_fee'];
            } else {
                $line_freight_per = $value['freight_per'] + $value['package_fee'];
            }
            $line_total = bcsub(bcmul(bcadd($value['unit_price'] + $line_freight_per, $value['service_fee_per'] ?: 0, $precision), $value['quantity'], $precision), $value['amount'], $precision);
            $discountAmount =  $value['coupon_amount'] + $value['campaign_amount'];
            $finalTotal = $line_total - $discountAmount;
            //12591 B2B记录各国别用户的操作时间
            $date_added = changeOutPutByZone($value['date_added'], $this->session);
            $worksheet
                ->setCellValue('A' . $column_elements, $value['purchase_order_id'])
                ->setCellValue('B' . $column_elements, html_entity_decode($value['screenname']))
                ->setCellValue('C' . $column_elements, $value['item_code'])
                ->setCellValue('D' . $column_elements, html_entity_decode($value['product_name']))
                ->setCellValue('E' . $column_elements, $value['quantity'])
                ->setCellValue('F' . $column_elements, $value['unit_price']);
            if($change_line == 0){
                $worksheet
                    ->setCellValue('G' . $column_elements, $line_freight_per)
                    ->setCellValue('H' . $column_elements, $line_total)
                    ->setCellValue('I' . $column_elements, -$discountAmount)
                    ->setCellValue('J' . $column_elements, $finalTotal)
                    ->setCellValue('K' . $column_elements, $value['poundage'])
                    ->setCellValue('L' . $column_elements, $value['payment_method'])
                    ->setCellValueExplicit('M'.$column_elements, $date_added, DataType::TYPE_STRING2)
                    ->setCellValueExplicit('N'.$column_elements, $value['order_type'], DataType::TYPE_STRING2)
                    ->setCellValueExplicit('O'.$column_elements, $value['order_id'], DataType::TYPE_STRING2)
                    ->setCellValue('P' . $column_elements, 'No');
            }elseif ($change_line == 1){
                $string = $isEurope == true ? ($value['service_fee_per']) : ($value['discountShow'] ? ($value['discountShow'] . '%') : -$value['amount_price_per']);
                $worksheet
                    ->setCellValue('G' . $column_elements, $string)
                    ->setCellValue('H' . $column_elements, $line_freight_per)
                    ->setCellValue('I' . $column_elements, $line_total)
                    ->setCellValue('J' . $column_elements, -$discountAmount)
                    ->setCellValue('K' . $column_elements, $finalTotal)
                    ->setCellValue('L' . $column_elements, $value['poundage'])
                    ->setCellValue('M' . $column_elements, $value['payment_method'])
                    ->setCellValueExplicit('N'.$column_elements, $date_added, DataType::TYPE_STRING2)
                    ->setCellValueExplicit('O'.$column_elements, $value['order_type'], DataType::TYPE_STRING2)
                    ->setCellValueExplicit('P'.$column_elements, $value['order_id'], DataType::TYPE_STRING2)
                    ->setCellValue('Q' . $column_elements, 'No');
            }elseif ($change_line == 3){
                $worksheet
                    ->setCellValue('G' . $column_elements, $value['service_fee_per'] ?: 0)
                    ->setCellValue('H' . $column_elements, ($value['discountShow'] ? $value['discountShow'] . '%' : ''))  //Discount(OFF)
                    ->setCellValue('I' . $column_elements, -$value['amount_service_fee_per'])
                    ->setCellValue('J' . $column_elements, $line_freight_per)
                    ->setCellValue('K' . $column_elements, $line_total)
                    ->setCellValue('L' . $column_elements, -$discountAmount)
                    ->setCellValue('M' . $column_elements, $finalTotal)
                    ->setCellValue('N' . $column_elements, $value['poundage'])
                    ->setCellValue('O' . $column_elements, $value['payment_method'])
                    ->setCellValueExplicit('P'.$column_elements, $date_added, DataType::TYPE_STRING2)
                    ->setCellValueExplicit('Q'.$column_elements, $value['order_type'], DataType::TYPE_STRING2)
                    ->setCellValueExplicit('R'.$column_elements, $value['order_id'], DataType::TYPE_STRING2)
                    ->setCellValue('S' . $column_elements, 'No');
            }
            $column_elements++;
            $total_amount = bcadd($total_amount, $line_total, $precision);
            $total_amount = bcadd($total_amount, $value['poundage'], $precision);
            if (isset($value['rma_list']) && $value['rma_list'] != null) {
                //需要做一个rma
                foreach ($value['rma_list'] as $k => $v) {
                    $total_qty -= $v['quantity'];
                    $total_amount -= $v['actual_refund_amount'];
                    if ($v['refund_type'] == 1) {
                        $refund_type = 'Line Of Credit'; //1:返信用额度2：返优惠券
                    } else {
                        $refund_type = '';
                    }
                    $worksheet
                        ->setCellValue('A' . $column_elements, $value['purchase_order_id'])
                        ->setCellValue('B' . $column_elements, html_entity_decode($value['screenname']))
                        ->setCellValue('C' . $column_elements, $value['item_code'])
                        ->setCellValue('D' . $column_elements, html_entity_decode($value['product_name']))
                        ->setCellValue('E' . $column_elements, -$v['quantity']);

                    $isEurope && $line[] = '';                          //Service Fee Per Unit
                    $enableQuote && $line[] = '';                       //Discount(OFF)
                    $isEurope && $enableQuote && $line[] = '';           //Service Fee Discount Per Unit
                    //12591 B2B记录各国别用户的操作时间
                    //如果是 即重发又退款 的订单，Seller只同意退款，则 取退款时间；
                    $processed_date = changeOutPutByZone(($v['processed_date']) ? ($v['processed_date']) : ($v['credit_date_added']), $this->session);
                    //12591 end
                    if($change_line == 0){
                        $worksheet
                            ->setCellValue('H' . $column_elements, -$v['actual_refund_amount'])
                            ->setCellValue('J' . $column_elements, -$v['actual_refund_amount'])
                            ->setCellValue('L' . $column_elements, $refund_type)
                            ->setCellValueExplicit('M'.$column_elements, $processed_date, DataType::TYPE_STRING2)
                            ->setCellValueExplicit('O'.$column_elements, $v['from_customer_order_id'], DataType::TYPE_STRING2)
                            ->setCellValue('P' . $column_elements, 'Yes')
                            ->setCellValueExplicit('Q'.$column_elements, $v['rma_order_id'], DataType::TYPE_STRING2);
                    }elseif ($change_line == 1){
                        $worksheet
                            ->setCellValue('I' . $column_elements, -$v['actual_refund_amount'])
                            ->setCellValue('K' . $column_elements, -$v['actual_refund_amount'])
                            ->setCellValue('M' . $column_elements, $refund_type)
                            ->setCellValueExplicit('N'.$column_elements, $processed_date, DataType::TYPE_STRING2)
                            ->setCellValueExplicit('P'.$column_elements, $v['from_customer_order_id'], DataType::TYPE_STRING2)
                            ->setCellValue('Q' . $column_elements, 'Yes')
                            ->setCellValueExplicit('R'.$column_elements, $v['rma_order_id'], DataType::TYPE_STRING2);
                    }elseif ($change_line == 3){
                        $worksheet
                            ->setCellValue('K' . $column_elements, -$v['actual_refund_amount'])
                            ->setCellValue('M' . $column_elements, -$v['actual_refund_amount'])
                            ->setCellValue('O' . $column_elements, $refund_type)
                            ->setCellValueExplicit('P'.$column_elements, $processed_date, DataType::TYPE_STRING2)
                            ->setCellValueExplicit('R'.$column_elements, $v['from_customer_order_id'], DataType::TYPE_STRING2)
                            ->setCellValue('S' . $column_elements, 'Yes')
                            ->setCellValueExplicit('T'.$column_elements, $v['rma_order_id'], DataType::TYPE_STRING2);
                    }
                    $column_elements++;
                }
                unset($v);
            }

        }
        // 最后一行
        $worksheet
            ->setCellValue('D' . $column_elements, 'Total Purchase Quantity：')
            ->setCellValue('E' . $column_elements, $total_qty);
        if($change_line == 0){
            $worksheet
                ->setCellValue('G' . $column_elements, 'Total：')
                ->setCellValue('H' . $column_elements, $total_qty);
        }elseif ($change_line == 1){
            $worksheet
                ->setCellValue('H' . $column_elements, 'Total：')
                ->setCellValue('I' . $column_elements, $total_amount);
        }elseif($change_line == 3){
            $worksheet
                ->setCellValue('J' . $column_elements, 'Total：')
                ->setCellValue('K' . $column_elements, $total_amount);
        }
        $column_elements++;
        $allColumn = 'A1:' . 'T' . $column_elements;
        $worksheet->getStyle("$allColumn")
            ->getFont()
            ->setName('微软雅黑')
            ->setSize(9);
        $worksheet->getStyle("$allColumn")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xls($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }

    public function setB2bData($data,$file_name){
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:D1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(15);
        $worksheet->getColumnDimension('D')->setWidth(15);

        $worksheet->setCellValue('A1', "Email"); //设置列的值
        $worksheet->setCellValue('B1', "money"); //设置列的值
        $worksheet->setCellValue('C1', "name"); //设置列的值
        $worksheet->setCellValue('D1', "nickname"); //设置列的值



        $column_elements = 2;

        foreach($data as $key => $value){


            $worksheet
                ->setCellValue('A' . $column_elements, $value['user_email'])
                ->setCellValue('B' . $column_elements, $value['money'])
                ->setCellValue('C' . $column_elements, $value['name'])
                ->setCellValue('D' . $column_elements, $value['nickname']);
            $column_elements++;


        }
        $allColumn = 'A1:' . 'D' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }



    public function setProductReturnRate($data,$file_name){
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:ED1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(15);
        $worksheet->getColumnDimension('D')->setWidth(15);
        $worksheet->getColumnDimension('E')->setWidth(15);



        $worksheet->setCellValue('A1', "Product"); //设置列的值
        $worksheet->setCellValue('B1', "Sku"); //设置列的值
        $worksheet->setCellValue('C1', "Screenname"); //设置列的值
        $worksheet->setCellValue('D1', "Returnrate"); //设置列的值
        $worksheet->setCellValue('E1', "Mothly sales"); //设置列的值



        $column_elements = 2;

        foreach($data as $key => $value){


            $worksheet
                ->setCellValue('A' . $column_elements, $value['product_id'])
                ->setCellValue('B' . $column_elements, $value['sku'])
                ->setCellValue('C' . $column_elements, html_entity_decode($value['screenname']))
                ->setCellValue('D' . $column_elements, $value['return_rate'])
                ->setCellValue('E' . $column_elements, $value['monthly_sales']);


            $column_elements++;


        }
        $allColumn = 'A1:' . 'E' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }

    public function setPriceExcelData($data,$file_name){
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:D1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(15);
        $worksheet->getColumnDimension('D')->setWidth(15);


        $worksheet->setCellValue('A1', "销售订单号"); //设置列的值
        $worksheet->setCellValue('B1', "Sku"); //设置列的值
        $worksheet->setCellValue('C1', "采购单价"); //设置列的值
        $worksheet->setCellValue('D1', "时间"); //设置列的值


        $column_elements = 2;

        foreach($data as $key => $value){


            $worksheet
                ->setCellValue('A' . $column_elements, $value['order_id'])
                ->setCellValue('B' . $column_elements, $value['item_code'])
                ->setCellValue('C' . $column_elements, sprintf('%.2f',$value['price']))
                ->setCellValue('D' . $column_elements, $value['create_time']);

            $column_elements++;


        }
        $allColumn = 'A1:' . 'D' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }

    public function setReturnApprovalRateData($data,$file_name){
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:C1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(15);




        $worksheet->setCellValue('A1', "screenname"); //设置列的值
        $worksheet->setCellValue('B1', "return approval rate"); //设置列的值
        $worksheet->setCellValue('C1', "RMA Number"); //设置列的值




        $column_elements = 2;

        foreach($data as $key => $value){


            $worksheet
                ->setCellValue('A' . $column_elements, html_entity_decode($value['screenname']))
                ->setCellValue('B' . $column_elements, $value['return_approval_rate'])
                ->setCellValue('C' . $column_elements, $value['all']);


            $column_elements++;


        }
        $allColumn = 'A1:' . 'C' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }


    /**
     * [splitStrInThreePart description] 分割字符串分成3部分
     * @param $str
     * @param int $default
     * @return array
     */
    public function  splitStrInThreePart($str,$default = 25000){
        $res = [];
        //$length = mb_strlen($str);
        $res[0] = substr($str,$default*0,$default);
        $res[1] = substr($str,$default*1,$default);
        $res[2] = substr($str,$default*2,$default);
        return $res;


    }

    public function  setPriceData($data,$file_name){
        //dd($data);
        set_time_limit(0);
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:C1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(25);
        //$worksheet->getColumnDimension('D')->setWidth(15);



        $worksheet->setCellValue('A1', "Sku"); //设置列的值
        $worksheet->setCellValue('B1', "Price"); //设置列的值
        $worksheet->setCellValue('C1', "Quantity"); //设置列的值
        //$worksheet->setCellValue('D1', "ImagePath"); //设置列的值


        $column_elements = 2;

        foreach($data as $key => $value){

            $worksheet
                ->setCellValue('A' . $column_elements, $value['sku'])
                ->setCellValue('B' . $column_elements, $value['price'])
                ->setCellValue('C' . $column_elements, $value['qty']);
                //->setCellValue('D' . $column_elements, $value['img_path']);


            $column_elements++;


        }
        $allColumn = 'A1:' . 'C' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }


    public function  setOrderInfoByProduct($data,$file_name){
        //dd($data);
        set_time_limit(0);
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:H1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(25);
        $worksheet->getColumnDimension('D')->setWidth(15);
        $worksheet->getColumnDimension('E')->setWidth(15);
        $worksheet->getColumnDimension('F')->setWidth(15);
        $worksheet->getColumnDimension('G')->setWidth(15);
        $worksheet->getColumnDimension('H')->setWidth(15);


        $worksheet->setCellValue('A1', "Sales Order Id"); //设置列的值
        $worksheet->setCellValue('B1', "Item Code"); //设置列的值
        $worksheet->setCellValue('C1', "Deal Unit Price"); //设置列的值
        $worksheet->setCellValue('D1', "Quantity"); //设置列的值
        $worksheet->setCellValue('E1', "Purchase Order ID"); //设置列的值
        $worksheet->setCellValue('F1', "Purchase Order Date"); //设置列的值
        $worksheet->setCellValue('G1', "Sales Order Status"); //设置列的值
        $worksheet->setCellValue('H1', "Product ID"); //设置列的值

        $column_elements = 2;

        foreach($data as $key => $value){
            $worksheet
                ->setCellValue('A' . $column_elements, $value['order_id'])
                ->setCellValue('B' . $column_elements, $value['item_code'])
                ->setCellValue('C' . $column_elements, $value['price'])
                ->setCellValue('D' . $column_elements, $value['qty'])
                ->setCellValue('E' . $column_elements, $value['purchase_order_id'])
                ->setCellValue('F' . $column_elements, $value['date_added'])
                ->setCellValue('G' . $column_elements, $value['name'])
                ->setCellValue('H' . $column_elements, $value['product_id']);


            $column_elements++;


        }
        $allColumn = 'A1:' . 'H' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }

    public function setAutoBuyerData($data,$file_name){
        //dd($data);
        set_time_limit(0);
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:G1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(25);
        $worksheet->getColumnDimension('D')->setWidth(15);
        $worksheet->getColumnDimension('E')->setWidth(15);
        $worksheet->getColumnDimension('F')->setWidth(15);
        $worksheet->getColumnDimension('G')->setWidth(15);


        $worksheet->setCellValue('A1', "Order Id"); //设置列的值
        $worksheet->setCellValue('B1', "B2B order ID"); //设置列的值
        $worksheet->setCellValue('C1', "Product Name"); //设置列的值
        $worksheet->setCellValue('D1', "Sku"); //设置列的值
        $worksheet->setCellValue('E1', "Quantity"); //设置列的值
        $worksheet->setCellValue('F1', "Unit Price"); //设置列的值
        $worksheet->setCellValue('G1', "Total"); //设置列的值

        $column_elements = 2;

        foreach($data as $key => $value){
            $worksheet
                ->setCellValue('A' . $column_elements, $value['order_id'])
                ->setCellValue('B' . $column_elements, $value['oid'])
                ->setCellValue('C' . $column_elements,  html_entity_decode($value['name']))
                ->setCellValue('D' . $column_elements, $value['item_code'])
                ->setCellValue('E' . $column_elements, $value['quantity'])
                ->setCellValue('F' . $column_elements, $value['price'])
                ->setCellValue('G' . $column_elements, sprintf('%.2f',$value['price']*$value['quantity']));


            $column_elements++;


        }
        $allColumn = 'A1:' . 'G' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }


    //public function  setHotDealsExcelData($data,$file_name){
    //    //dd($data);
    //    set_time_limit(0);
    //    $worksheet = $this->spreadsheet->getActiveSheet();
    //    //设置列宽
    //    $worksheet->freezePane('A2');
    //    $worksheet->getStyle('A1:F1')->getFont()->setBold(true);
    //    $worksheet->getColumnDimension('A')->setWidth(25);
    //    $worksheet->getColumnDimension('B')->setWidth(15);
    //    $worksheet->getColumnDimension('C')->setWidth(25);
    //    $worksheet->getColumnDimension('D')->setWidth(15);
    //    $worksheet->getColumnDimension('E')->setWidth(15);
    //    $worksheet->getColumnDimension('F')->setWidth(15);
    //
    //
    //    $worksheet->setCellValue('A1', "SKU"); //设置列的值
    //    $worksheet->setCellValue('B1', "Store"); //设置列的值
    //    $worksheet->setCellValue('C1', "订单数"); //设置列的值
    //    $worksheet->setCellValue('D1', "交易额"); //设置列的值
    //    $worksheet->setCellValue('E1', "浏览量"); //设置列的值
    //    $worksheet->setCellValue('F1', "下载数"); //设置列的值
    //
    //
    //    $column_elements = 2;
    //
    //    foreach($data as $key => $value){
    //        $worksheet
    //            ->setCellValue('A' . $column_elements, $value['sku'])
    //            ->setCellValue('B' . $column_elements, $value['store'])
    //            ->setCellValue('C' . $column_elements, $value['order_num'])
    //            ->setCellValue('D' . $column_elements,  $value['trade'])
    //            ->setCellValue('E' . $column_elements, $value['viewed'])
    //            ->setCellValue('F' . $column_elements, $value['download']);
    //
    //
    //        $column_elements++;
    //
    //
    //    }
    //    $allColumn = 'A1:' . 'F' . $column_elements;
    //    $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
    //    $worksheet->getStyle("$allColumn")->getAlignment()->
    //    setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->
    //    setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    //
    //
    //    $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
    //    $this->getBrowerCompatible($file_name);
    //    $savePath = 'php://output';
    //    $writer->save($savePath);
    //    $this->unlinkWorkSheets();
    //}
    public function  setExcelDataTest($data,$file_name){
        //dd($data);
        set_time_limit(0);
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:O1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(15);
        $worksheet->getColumnDimension('D')->setWidth(15);
        $worksheet->getColumnDimension('E')->setWidth(15);
        $worksheet->getColumnDimension('F')->setWidth(15);
        $worksheet->getColumnDimension('G')->setWidth(15);
        $worksheet->getColumnDimension('H')->setWidth(15);
        $worksheet->getColumnDimension('I')->setWidth(15);
        $worksheet->getColumnDimension('J')->setWidth(15);
        $worksheet->getColumnDimension('K')->setWidth(15);
        $worksheet->getColumnDimension('L')->setWidth(15);
        $worksheet->getColumnDimension('M')->setWidth(15);
        $worksheet->getColumnDimension('N')->setWidth(15);
        $worksheet->getColumnDimension('O')->setWidth(15);


        $worksheet->setCellValue('A1', "Sales Order ID"); //设置列的值
        $worksheet->setCellValue('B1', "Ship-to Name"); //设置列的值
        $worksheet->setCellValue('C1', "Ship-to AddressDetail"); //设置列的值
        $worksheet->setCellValue('D1', "Ship-to City"); //设置列的值
        $worksheet->setCellValue('E1', "Ship-to State"); //设置列的值
        $worksheet->setCellValue('F1', "Ship-to Country"); //设置列的值
        $worksheet->setCellValue('G1', "Ship-to PostalCode"); //设置列的值
        $worksheet->setCellValue('H1', "Item Code"); //设置列的值
        $worksheet->setCellValue('I1', "Deal Unit Price"); //设置列的值
        $worksheet->setCellValue('J1', "Quantity"); //设置列的值
        $worksheet->setCellValue('K1', "Transaction Fee"); //设置列的值
        $worksheet->setCellValue('L1', "Total Amount"); //设置列的值
        $worksheet->setCellValue('M1', "Purchase Order ID"); //设置列的值
        $worksheet->setCellValue('N1', "Purchase Order Date"); //设置列的值
        $worksheet->setCellValue('O1', "Sales Order Status"); //设置列的值


        $column_elements = 2;

        foreach($data as $key => $value){
            $worksheet->setCellValueExplicit('A'.$column_elements, '01110', DataType::TYPE_STRING2)
                ->setCellValue('B' . $column_elements, $value['ship_to_name'])
                ->setCellValue('C' . $column_elements, $value['ship_to_address_detail'])
                ->setCellValue('D' . $column_elements, $value['ship_to_city'])
                ->setCellValue('E' . $column_elements, $value['ship_to_state'])
                ->setCellValue('F' . $column_elements, $value['ship_to_country'])
                ->setCellValue('G' . $column_elements, $value['ship_to_postalCode'])
                ->setCellValue('H' . $column_elements, $value['item_code'])
                ->setCellValue('I' . $column_elements, $value['deal_unit_price'])
                ->setCellValue('J' . $column_elements, $value['quantity'])
                ->setCellValue('K' . $column_elements, $value['transaction_fee'])
                ->setCellValue('L' . $column_elements, $value['total_amount'])
                ->setCellValue('M' . $column_elements, $value['purchase_order_id'])
                ->setCellValue('N' . $column_elements, $value['purchase_order_date'])
                ->setCellValue('O' . $column_elements, $value['sales_order_status']);
            //设置列的值
            $column_elements++;


        }
        $allColumn = 'A1:' . 'O' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }

    public function setDropshipData($data,$file_name){
        //dd($data);
        set_time_limit(0);
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:I1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(15);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(15);
        $worksheet->getColumnDimension('D')->setWidth(15);
        $worksheet->getColumnDimension('E')->setWidth(25);
        $worksheet->getColumnDimension('F')->setWidth(15);
        $worksheet->getColumnDimension('G')->setWidth(25);
        $worksheet->getColumnDimension('H')->setWidth(25);
        $worksheet->getColumnDimension('I')->setWidth(25);

        $worksheet->setCellValue('A1', "id"); //设置列的值
        $worksheet->setCellValue('B1', "order_id"); //设置列的值
        $worksheet->setCellValue('C1', "create_time"); //设置列的值
        $worksheet->setCellValue('D1', "paid_time"); //设置列的值
        $worksheet->setCellValue('E1', "item_code"); //设置列的值GH
        $worksheet->setCellValue('F1', "qty"); //设置列的值
        $worksheet->setCellValue('G1', "TrackingNumber"); //设置列的值
        $worksheet->setCellValue('H1', "ship_method"); //设置列的值
        $worksheet->setCellValue('I1', "ship_service_level"); //设置列的值



        $column_elements = 2;

        foreach($data as $key => $value){
            $worksheet->setCellValue('A'.$column_elements, $value['id'])
                ->setCellValue('B' . $column_elements, $value['order_id'])
                ->setCellValue('C' . $column_elements, $value['create_time'])
                ->setCellValue('D' . $column_elements, $value['paid_time'])
                ->setCellValue('E' . $column_elements, $value['item_code'])
                ->setCellValue('F' . $column_elements, $value['qty'])
                ->setCellValue('G' . $column_elements, $value['TrackingNumber'])
                ->setCellValue('H' . $column_elements, $value['ship_method'])
                ->setCellValue('I' . $column_elements, $value['ship_service_level']);

            //设置列的值
            $column_elements++;


        }
        $allColumn = 'A1:' . 'I' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }


    public function  setUserNumberExcelData($data,$file_name){
        //dd($data);
        set_time_limit(0);
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:D1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(15);
        $worksheet->getColumnDimension('D')->setWidth(15);

        $worksheet->setCellValue('A1', "编号"); //设置列的值
        $worksheet->setCellValue('B1', "name"); //设置列的值
        $worksheet->setCellValue('C1', "country_id"); //设置列的值
        $worksheet->setCellValue('D1', "国别"); //设置列的值



        $column_elements = 2;

        foreach($data as $key => $value){
            $worksheet->setCellValue('A'.$column_elements, $value['user_number'])
                ->setCellValue('B' . $column_elements, $value['firstname'].' '.$value['lastname'])
                ->setCellValue('C' . $column_elements, $value['country_id'])
                ->setCellValue('D' . $column_elements, $value['name']);

            //设置列的值
            $column_elements++;


        }
        $allColumn = 'A1:' . 'D' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }

    public function  setPackageDownloadInfo($data,$file_name){
        //dd($data);
        set_time_limit(0);
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:E1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(15);
        $worksheet->getColumnDimension('D')->setWidth(15);
        $worksheet->getColumnDimension('E')->setWidth(15);

        $worksheet->setCellValue('A1', "Sku"); //设置列的值
        $worksheet->setCellValue('B1', "customer name"); //设置列的值
        $worksheet->setCellValue('C1', "email"); //设置列的值
        $worksheet->setCellValue('D1', "create time"); //E设置列的值
        $worksheet->setCellValue('E1', "product id
        "); //E设置列的值



        $column_elements = 2;

        foreach($data as $key => $value){
            $worksheet->setCellValue('A'.$column_elements, $value['sku'])
                ->setCellValue('B' . $column_elements, $value['firstname'].' '.$value['lastname'])
                ->setCellValue('C' . $column_elements, $value['email'])
                ->setCellValue('D' . $column_elements, $value['CreateTime'])
                ->setCellValue('E' . $column_elements, $value['product_id']);

            //设置列的值
            $column_elements++;


        }
        $allColumn = 'A1:' . 'E' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }
    public function  setHotDealsExcelData($data,$file_name){
        //dd($data);
        set_time_limit(0);
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:E1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(25);
        $worksheet->getColumnDimension('D')->setWidth(15);
        $worksheet->getColumnDimension('E')->setWidth(15);


        $worksheet->setCellValue('A1', "SKU"); //设置列的值
        $worksheet->setCellValue('B1', "订单数"); //设置列的值
        $worksheet->setCellValue('C1', "交易额"); //设置列的值
        $worksheet->setCellValue('D1', "浏览量"); //设置列的值
        $worksheet->setCellValue('E1', "下载数"); //设置列的值


        $column_elements = 2;

        foreach($data as $key => $value){
            $worksheet
                ->setCellValue('A' . $column_elements, $value['sku'])
                ->setCellValue('B' . $column_elements, $value['order_num'])
                ->setCellValue('C' . $column_elements,  $value['trade'])
                ->setCellValue('D' . $column_elements, $value['viewed'])
                ->setCellValue('E' . $column_elements, $value['download']);


            $column_elements++;


        }
        $allColumn = 'A1:' . 'E' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }

    public function setBuyerInfoData($data,$file_name){
        //dd($data);
        set_time_limit(0);
        $worksheet = $this->spreadsheet->getActiveSheet();
        //设置列宽
        $worksheet->freezePane('A2');
        $worksheet->getStyle('A1:D1')->getFont()->setBold(true);
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getColumnDimension('C')->setWidth(25);
        $worksheet->getColumnDimension('D')->setWidth(15);



        $worksheet->setCellValue('A1', "用户ID"); //设置列的值
        $worksheet->setCellValue('B1', "Buyer Name"); //设置列的值
        $worksheet->setCellValue('C1', "手机号"); //设置列的值
        $worksheet->setCellValue('D1', "邮箱"); //设置列的值


        $column_elements = 2;

        foreach($data as $key => $value){
            $worksheet
                ->setCellValue('A' . $column_elements, $value['user_number'])
                ->setCellValue('B' . $column_elements, $value['name'])
                ->setCellValue('C' . $column_elements,  $value['phone'])
                ->setCellValue('D' . $column_elements, $value['email']);


            $column_elements++;


        }
        $allColumn = 'A1:' . 'D' . $column_elements;
        $worksheet->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $worksheet->getStyle("$allColumn")->getAlignment()->
        setVertical(Alignment::VERTICAL_CENTER)->
        setHorizontal(Alignment::HORIZONTAL_CENTER);


        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $this->getBrowerCompatible($file_name);
        $savePath = 'php://output';
        $writer->save($savePath);
        $this->unlinkWorkSheets();
    }

    /**
     * [getExcelData description] 获取excel数据
     * @param string $filename
     * @return array
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function  getExcelData($filename){
        $whatTable = 0;
        /**  Identify the type of $inputFileName  **/
        $inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($filename);
        /**  Create a new Reader of the type that has been identified  **/
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType); //实例化阅读器对象。
        $spreadsheet = $reader->load($filename);  //将文件读取到到$spreadsheet对象中
        $worksheet = $spreadsheet->getActiveSheet();   //获取当前文件内容
        $sheetAllCount = $spreadsheet->getSheetCount(); // 工作表总数
        //for ($index = 0; $index < $sheetAllCount; $index++) {   //工作表标题
        //    $title[] = $spreadsheet->getSheet($index)->getTitle();
        //}
        $sheet = $spreadsheet->getSheet($whatTable); // 读取第一個工作表
        $highest_row = $sheet->getHighestRow(); // 取得总行数
        $highest_column = $sheet->getHighestColumn(); ///取得列数  字母abc...
        $highestColumnIndex = Coordinate::columnIndexFromString($highest_column);  //转化为数字;
        $num=0;
        for ($i = 0; $i <= $highestColumnIndex-1; $i++) {
            for ($j = 0; $j <= $highest_row-1; $j++) {
                //                $conent = $sheet->getCellByColumnAndRow($i, $j)->getValue();
                $content = $sheet->getCellByColumnAndRow($i+1, $j+1)->getFormattedValue();
                $info[$j][$i] = trim($content);
                if($j==0 && $content){
                    $num++;
                }
            }
        }
        // 删除空行
        foreach ($info as $k=>$v){
            // 删除空行
            if(!implode('',$v)){
                unset($info[$k]);
                continue;
            }
            //删除多余的列
            $info[$k]=array_slice($v,0,$num);
        }
       return $info;

    }

    /*
     * 转换excel读取出来的数据格式
     * */
    public function formatExcel($data){
        $kvD = [];
        foreach ($data as $k => $v)
        {
            if (0 == $k) continue;
            if (count($data[0]) != count($v)) break;
            $temp = [];
            foreach ($data[0] as $key=>$title)
            {
                $temp[$title] = $v[$key];
            }
            $kvD[] = $temp;
        }
        return $kvD;
    }


    /**
     * [getBrowerCompatible description]
     * @param $file_name
     * @return void
     */
    protected function getBrowerCompatible($file_name){
        $ua = $_SERVER["HTTP_USER_AGENT"];
        $encoded_filename = urlencode($file_name);
        $encoded_filename = str_replace("+", "%20", $encoded_filename);
        header('Content-Type: application/octet-stream');
        if (preg_match("/MSIE/", $ua)) {
            header('Content-Disposition: attachment; filename="' . $encoded_filename . '"');
        } else if (preg_match("/Firefox/", $ua)) {
            header('Content-Disposition: attachment; filename*="utf8\'\'' . $file_name . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $file_name . '"');
        }
        header('Cache-Control: max-age=0');//禁止缓存

    }
    public function getDailyPlanExcelByCondition($data){
        set_time_limit(0);

        $length = count($data);
        // dump($length);
        // die;
        $currentDate = date('Ymd', time());

        header("Content-Typ:text/html;charset=utf-8");
        import("Org.Util.PHPExcel.Writer.IWriter");
        import("Org.Util.PHPExcel.Writer.Excel5");
        import("Org.Util.PHPExcel");
        import("Org.Util.PHPExcel.IOFactory");
        import("Org.Util.PHPExcel.Style.Alignment");

        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->freezePane('A2');
        $objPHPExcel->getActiveSheet()->getStyle('A1:K1')->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(25);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(10);
        $objPHPExcel->getActiveSheet(0)->setCellValue('A1', "日期"); //设置列的值
        $objPHPExcel->getActiveSheet(0)->setCellValue('B1', "车间"); //设置列的值
        $objPHPExcel->getActiveSheet(0)->setCellValue('C1', "产成品计划数量"); //设置列的值
        $objPHPExcel->getActiveSheet(0)->setCellValue('D1', "自制件计划数量"); //设置列的值
        $objPHPExcel->getActiveSheet(0)->setCellValue('E1', "计划总工时"); //设置列的值
        $objPHPExcel->getActiveSheet(0)->setCellValue('F1', "完成数量"); //设置列的值
        $objPHPExcel->getActiveSheet(0)->setCellValue('G1', "产出工时"); //设置列的值
        $objPHPExcel->getActiveSheet(0)->setCellValue('H1', "出勤人数"); //设置列的值
        $objPHPExcel->getActiveSheet(0)->setCellValue('I1', "投入工时+管理工时"); //设置列的值
        $objPHPExcel->getActiveSheet(0)->setCellValue('J1', "生产效率"); //设置列的值
        $objPHPExcel->getActiveSheet(0)->setCellValue('K1', "计划达成率"); //设置列的值
        $column_elements = 2;

        for ($z = 0; $z < $length; $z++) {
            $crossPreRow = 'A' . $column_elements . ':' . 'K' . $column_elements;


            $finalLength = count($data[$z]['details']);
            for ($a = 0; $a < $finalLength; $a++) {

                $objPHPExcel->getActiveSheet(0)->getStyle($crossPreRow)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValue('A' . $column_elements, $data[$z]['date'])
                    ->setCellValue('B' . $column_elements, $data[$z]['details'][$a]['department'])
                    ->setCellValue('C' . $column_elements, $data[$z]['details'][$a]['product_num'])
                    ->setCellValue('D' . $column_elements, $data[$z]['details'][$a]['mix_num'])
                    ->setCellValue('E' . $column_elements, $data[$z]['details'][$a]['all_man_hours'])
                    ->setCellValue('F' . $column_elements, $data[$z]['details'][$a]['in_mix_num']+$data[$z]['details'][$a]['in_storage_num'])
                    ->setCellValue('G' . $column_elements, $data[$z]['details'][$a]['in_mix_sum']+$data[$z]['details'][$a]['in_storage_sum'])
                    ->setCellValue('H' . $column_elements, $data[$z]['details'][$a]['total_men'])
                    ->setCellValue('I' . $column_elements, $data[$z]['details'][$a]['total_man_hours'])
                    ->setCellValue('J' . $column_elements, $data[$z]['details'][$a]['man_hours_eff'].'%')
                    ->setCellValue('K' . $column_elements, $data[$z]['details'][$a]['num_eff'].'%');
                $column_elements++;
            }
        }
        //die;

        $allColumn = 'A1:' . 'K' . $column_elements;
        $objPHPExcel->setActiveSheetIndex(0)->getStyle("$allColumn")->getFont()->setName('微软雅黑')->setSize(9);
        $objPHPExcel->setActiveSheetIndex(0)->getStyle("$allColumn")->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER)->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        $name = '日计划信息分析表';
        //$objPHPExcel->getActiveSheet(0)->setTitle("$name");
        $objPHPExcel->getActiveSheet(0)->setTitle("$name");
        ob_end_clean(); //清除缓冲区,避免乱码
        header('Content-Type:application/vnd.ms-excel');
        $str = $currentDate . ' ' . '日计划信息分析表';
        $filename = urlencode($str) . ".xls";
        $filename = str_replace("+", " ", $filename);
        //处理浏览器兼容问题
        $ua = $_SERVER["HTTP_USER_AGENT"];
        $encoded_filename = urlencode($str) . ".xls";
        $encoded_filename = str_replace("+", "%20", $encoded_filename);
        header('Content-Type: application/octet-stream');
        if (preg_match("/MSIE/", $ua)) {
            header('Content-Disposition: attachment; filename="' . $encoded_filename . '"');
        } else if (preg_match("/Firefox/", $ua)) {
            header('Content-Disposition: attachment; filename*="utf8\'\'' . $filename . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        // header("Content-Disposition:attachment;filename=$filename.xls");
        header('Cache-Control: max-age=0');
        header("Expires: 0");

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');

        exit;
    }

    /**
     * 检测Excel的第一行数据是否是对应字段
     *
     * @param array $data 第一行数据
     * @param array $excelHeader 目标数据
     * @return bool
     */
    public function checkExcelFirstLine($data = [], $excelHeader = [])
    {
        if (!$data || !$excelHeader) {
            return false;
        }
        if ($data != $excelHeader) {
            return false;
        }

        return true;
    }



}
