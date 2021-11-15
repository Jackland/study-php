<?php

use App\Components\Storage\StorageCloud;
use App\Components\Storage\StorageLocal;
use App\Models\SalesOrder\CustomerSalesOrder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use XPDF\PdfToText;

/**
 * Class ModelToolPdf
 * @property ModelToolBarcode $model_tool_barcode
 */
class ModelToolPdf extends Model {
    protected $pdf;
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    }

    public function test(){
        $tcpdf = $this->pdf;
        $tcpdf->SetCreator(PDF_CREATOR);
        $tcpdf->SetAuthor('Oristand');
        $tcpdf->SetTitle('BOL');
        $tcpdf->setPrintHeader(false);
        $tcpdf->setPrintFooter(false);
        // set default monospaced font
        $tcpdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        // set margins
        $tcpdf->SetMargins(10, 0, 10);
        $tcpdf->SetHeaderMargin(0);
        $tcpdf->SetFooterMargin(0);

        //$tcpdf->setHeaderData('logo_example.png',20,'1111111111111111111111111111111','2222222222222222222222',array(0,64,255),array(0,64,128));
        //$tcpdf->SetHeaderData('logo_example.png', 20, '               Helloweba.com             ', '致力于WEB前端技术在中国的应用', array(0,64,255), array(0,64,128));
        //$tcpdf->setFooterData(array(0,64,0),array(0,64,128));

        $tcpdf->setHeaderFont(['msyh', '', '10']);//设置字体
        $tcpdf->setFooterFont(['msyh', '', '8']);
        $tcpdf->SetDefaultMonospacedFont('courier');//设置字体

        //$tcpdf->SetMargins(15,27,15);
        //$tcpdf->setHeaderMargin(5);
        $tcpdf->setFooterMargin(10);
        // set auto page breaks
        $tcpdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // set image scale factor
        $tcpdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Add a page
        // This method has several options, check the source code documentation for more information.
        $tcpdf->AddPage();

        // set default font subsetting mode
        //$tcpdf->setFontSubsetting(true);
        // Set font
        // dejavusans is a UTF-8 Unicode font, if you only need to
        // print standard ASCII chars, you can use core fonts like
        // helvetica or times to reduce file size.
        $tcpdf->SetFont('msyh', 'BI', 11, '', true);
        //$tcpdf->SetFont('stsongstdlight','',14);//设置字体
        // set text shadow effect
        $tcpdf->setTextShadow(['enabled'=>false]);
        $html ='
            <div >
                <table cellpadding="1" cellspacing="1" border="1" style="text-align:center;">
                    <tr>
                        <td rowspan="7">
                            <br/>
                            <br/>
                            <br/>
                            <img width="180" height="50"  src="http://localhost/image/catalog/Logo/Amazon.jpg"><br/>
                            <span style="font-size:20px;">Bill of Lading</span><br/><br/>
                            <span style="color: red">For use with Amazon.com drop shipments <span style="text-decoration:underline">ONLY</span></span><br/><br/>
                            <span style="font-size:16px;">Delivery Problem?</span>  <br/>
                            <span style="font-size: 12px;">Contact Amazon at (866) 423-5353 or LTL@amazon.com.</span><br/><br/>
                            <span style="font-size:16px;">Undeliverable? Re-route to:</span> <br/>
                            <span style="font-size: 12px;">Amazon Fulfillment Services  <br/>
                            1600 Worldwide Blvd <br/>
                            Hebron, KY 41048-8639</span>
                        </td>
                        <td style="text-align:left">Today’s Date: '.date('m/d/Y',time()).'</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Shipper’s BOL Number: BDS-13859</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Amazon’s PO # (e.g. “DaBcD123R”): DhjjRhDkX</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Shipper’s Internal Reference #: BDS-13859</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Carrier Name: ABF</td>
                    </tr>
                    <tr>
                        <td>
                             <span style="text-align:left">Tracking/Pro/Airway Bill Number: </span><br/>
                             <br/><br/>
                             <span style="text-align: center">ABF00437640</span><br/>
                             <span style="text-align: center">ABF00437669</span><br/>
                             <span style="text-align: center">ABF00437586</span><br/>
                             <br/><br/>
                             <span style="text-align: left;font-size: 8px;">*Obtain rolls of tracking stickers directly from carriers.</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Shipping Service: Economy (3-5 day)</td>
                    </tr>

                </table>
            </div>
            <div style="margin-top: 10px;">
                <table cellpadding="1" cellspacing="1" border="1" style="text-align:center;">
                    <tr bgcolor="#BFBFBF">
                        <td colspan="2">Ship From:</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Drop Shipper Name: </td>
                        <td style="text-align:left">MERAX / AT1</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Address: </td>
                        <td style="text-align:left">850 Douglas Hills Rd</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">City/State/Zip: </td>
                        <td style="text-align:left">Lithia Springs, GA 30122</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Phone Number:</td>
                        <td style="text-align:left">518-522-3376</td>
                    </tr>
                     <tr>
                        <td style="text-align:left">Warehouse Contact: </td>
                        <td style="text-align:left">Wenbo DOU</td>
                    </tr>
                    <tr bgcolor="#BFBFBF">
                        <td colspan="2">Ship To:</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Customer Name: </td>
                        <td style="text-align:left">Ashley Peacock</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Address Line 1: </td>
                        <td style="text-align:left">1586 RESPONSE RD APT 1097</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Address Line 2: </td>
                        <td style="text-align:left"></td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Address Line 3:</td>
                        <td style="text-align:left"></td>
                    </tr>
                    <tr>
                        <td style="text-align:left">City/State/Zip: </td>
                        <td style="text-align:left">SACRAMENTO CA? 958154858</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Phone Number:</td>
                        <td style="text-align:left">4438711951 </td>
                    </tr>
                    <tr bgcolor="#BFBFBF">
                        <td colspan="2">Bill To:</td>
                    </tr>
                     <tr>
                        <td style="text-align:left">Name: </td>
                        <td style="text-align:left">Amazon.com</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Address </td>
                        <td style="text-align:left">P.O. Box 80683, Seattle, WA 98108</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Account Number (Check One): </td>
                        <td style="text-align:left">[  ] CEVA: AMAZPO981 |  [ x ] ABF: 628924 | [  ] Pilot: 4134227</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Payment Terms: </td>
                        <td style="text-align:left">Third Party</td>
                    </tr>
                </table>
            </div>
            <div style="margin-top: 10px;">
                <table cellpadding="1" cellspacing="1" border="1" style="text-align:center;">
                    <tr bgcolor="#BFBFBF">
                        <td>Quantity</td>
                        <td>Item Description</td>
                        <td>Hazmat?</td>
                        <td>Weight</td>
                        <td>Dimensions</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">1 </td>
                        <td style="text-align:left">MODULAR SET 1 NEW</td>
                        <td style="text-align:left">NO</td>
                        <td style="text-align:left">108lb</td>
                        <td style="text-align:left">76x86x66</td>
                    </tr>
                    <tr>
                        <td style="text-align:left"> </td>
                        <td style="text-align:left"></td>
                        <td style="text-align:left"></td>
                        <td style="text-align:left"></td>
                        <td style="text-align:left"></td>
                    </tr>
                    <tr>
                        <td style="text-align:left"> </td>
                        <td style="text-align:left"></td>
                        <td style="text-align:left"></td>
                        <td style="text-align:left"></td>
                        <td style="text-align:left"></td>
                    </tr>
                    <tr>
                        <td style="text-align:left"> </td>
                        <td style="text-align:left"></td>
                        <td style="text-align:left"></td>
                        <td style="text-align:left"></td>
                        <td style="text-align:left"></td>
                    </tr>
                    <tr>
                        <td style="text-align:left"> </td>
                        <td style="text-align:left"></td>
                        <td style="text-align:left"></td>
                        <td style="text-align:left"></td>
                        <td style="text-align:left"></td>
                    </tr>




                </table>
            </div>
            <div style="margin-top: 10px;">
                 <table cellpadding="1" cellspacing="1"   style="text-align:center;">
                    <tr >
                        <td  style="border: 1px solid black;" bgcolor="#BFBFBF">Shipper</td>
                        <td></td>
                        <td  style="border: 1px solid black" bgcolor="#BFBFBF" >Carrier</td>
                    </tr>
                    <tr>
                        <td style="text-align:left;border: 1px solid black">Signature:  </td>
                        <td></td>
                        <td style="text-align:left;border: 1px solid black">Signature:</td>
                    </tr>
                    <tr>
                        <td style="text-align:left;border: 1px solid black">Printed Name: </td>
                        <td></td>
                        <td style="text-align:left;border: 1px solid black">Printed Name:</td>
                    </tr>
                    <tr>
                        <td style="text-align:left;border: 1px solid black">Tender Date: </td>
                        <td></td>'.'
                        <td style="text-align:left;border: 1px solid black">Pickup Date: </td>
                    </tr>
                     <tr>
                        <td style="text-align:left;border: 1px solid black"> </td>
                        <td></td>
                        <td style="text-align:left;border: 1px solid black">Driver/Agent/Vehicle #:   </td>
                    </tr>

                 </table>

            </div>
            <div style="text-align: center">Disclaimer: Terms & Conditions apply as set forth by Amazon.com and Carrier.</div>

            ';

        // Print text using writeHTMLCell()
        // $pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
        // output the HTML content
        $tcpdf->writeHTML($html, true, false, true, false, '');
        /*   $str1 = '欢迎来到Helloweba.com';

           $tcpdf->Write(0,$str1,'', 0, 'L', true, 0, false, false, 0);*/
        // reset pointer to the last page
        $tcpdf->lastPage();
        $tcpdf->Output('contract.pdf','D');


    }

    /**
     * [setDropshipBolData description] 通过 销售订单id 来生成bol
     * @param int $order_id tb_sys_customer_sales_order表的id
     * @return false
     * @throws \League\Flysystem\FilesystemException
     */
    public function setDropshipBolData($order_id){
        if (ob_get_length()) {
            ob_end_clean();
        }
        $data = CustomerSalesOrder::query()->alias('o')
            ->leftJoin('tb_sys_customer_sales_dropship_file_details as fd','fd.order_id','=','o.id')
            ->leftJoin('tb_sys_customer_sales_dropship_temp as dt','dt.order_id','=','o.order_id')
            ->leftJoin(DB_PREFIX.'customer as c','c.customer_id','=','o.buyer_id')
            ->groupBy(['o.id'])
            ->where('o.id',$order_id)
            ->select(['o.order_id','o.yzc_order_id','o.ship_method','o.ship_name','o.ship_service_level','c.firstname','c.lastname'
                ,'o.ship_address1','o.ship_address2','o.ship_city','o.ship_state','o.ship_zip_code','o.ship_phone','o.id'])
            ->selectRaw('group_concat(distinct fd.tracking_number) as tracking_id,group_concat(distinct dt.warehouse_name) as warehouse_name')
            ->first();

        //验证是否需要生成pdf
        $warehouse_flag = 0;
        foreach (VERIFY_WAREHOUSE_TYPES as $ks => $vs){
            if(stripos($data->ship_method,$vs) !== false || stripos($data->ship_service_level,$vs) !== false){
                $warehouse_flag = 1;
                break;
            }
        }
        if($warehouse_flag == 0){
            return false;
        }
        $tcpdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);;
        $tcpdf->SetCreator(PDF_CREATOR);
        $tcpdf->SetAuthor('Oristand');
        $tcpdf->SetTitle('BOL');
        $tcpdf->setPrintHeader(false);
        $tcpdf->setPrintFooter(false);
        // set default monospaced font
        $tcpdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        // set margins
        $tcpdf->SetMargins(10, 0, 10);
        $tcpdf->SetHeaderMargin(0);
        $tcpdf->SetFooterMargin(0);
        $tcpdf->setHeaderFont(['msyh', '', '10']);//设置字体
        $tcpdf->setFooterFont(['msyh', '', '8']);
        $tcpdf->SetDefaultMonospacedFont('courier');//设置字体
        //$tcpdf->SetMargins(15,27,15);
        //$tcpdf->setHeaderMargin(5);
        $tcpdf->setFooterMargin(0);
        // set auto page breaks
        //$tcpdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $tcpdf->SetAutoPageBreak(TRUE, 10);
        // set image scale factor
        $tcpdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        // Add a page
        // This method has several options, check the source code documentation for more information.
        $tcpdf->AddPage();
        // set default font subsetting mode
        //$tcpdf->setFontSubsetting(true);
        // Set font
        // dejavusans is a UTF-8 Unicode font, if you only need to
        // print standard ASCII chars, you can use core fonts like
        // helvetica or times to reduce file size.
        $tcpdf->SetFont('msyh', 'BI', 11, '', true);
        //$tcpdf->SetFont('stsongstdlight','',14);//设置字体
        // set text shadow effect
        $tcpdf->setTextShadow(['enabled'=>false]);

        $html = $this->getPdfHtmlInfo($data);

        $tcpdf->writeHTML($html, true, false, true, false, '');
        // reset pointer to the last page
        //命名，以及存储方式
        $tcpdf->lastPage();
        //ob_end_clean();
        $dir = DIR_DROPSHIP_FILE_UPLOAD.date('Y-m-d',time()).'/';
        $dir = str_ireplace('\\','/',$dir);
        if (!is_dir($dir)){
            $res = mkdir(iconv("UTF-8", "GBK",  $dir),0777,true);
        }
        $pdf = $data->order_id.'_'.time().'.pdf';
        $all_name = $dir . $pdf;
        ob_clean();
        $tcpdf->Output($all_name,'F');

        $dirPath = 'dropshipPdf/' . date('Y-m-d',time()) . '/';
        StorageCloud::storage()->writeFile(new UploadedFile($all_name, $pdf), $dirPath, $pdf);
        if (StorageLocal::storage()->fileExists($dirPath . $pdf)) {
            StorageLocal::storage()->delete($dirPath . $pdf);
        }

        // 将临时文件删除
        if (StorageLocal::storage()->fileExists($dirPath . $data->order_id . '.png')) {
            StorageLocal::storage()->delete($dirPath . $data->order_id . '.png');
        }
        if (StorageLocal::storage()->fileExists($dirPath . $data->order_id . '_address.png')) {
            StorageLocal::storage()->delete($dirPath . $data->order_id . '_address.png');
        }

        $tracking_list = explode(',',$data->tracking_id);
        $tracking = array_chunk($tracking_list,3);
        foreach($tracking as $value){
            $tracking_name ='';
            foreach($value as $vs){
                $tracking_name .= $vs.'_';
            }
            if (StorageLocal::storage()->fileExists($dirPath . trim( $tracking_name,'_') . '.png')) {
                StorageLocal::storage()->delete($dirPath . trim( $tracking_name,'_') . '.png');
            }
        }

        //写入
        $this->updateBolStatus($order_id,$pdf);


    }

    /**
     * [updateBolStatus description]
     * @param int $id
     * @param $pdf
     * @return void
     */
    public function updateBolStatus($id,$pdf){
        $bol_path = StorageCloud::storage()->getUrl('dropshipPdf/' . date('Y-m-d', time()) . '/' . $pdf);
        $map_save = [
            'bol_path'=> $bol_path,
            'bol_create_time'=> date('Y-m-d H:i:s',time()),
            'bol_create_id' => $this->customer->getId(),
        ];
        $this->orm->table('tb_sys_customer_sales_order')->where('id',$id)->update($map_save);

    }

    public function getTrackingNeedSpace($str){
        $len = 30 - mb_strlen($str);
        $str = '';
        for($i = 0; $i < $len; $i++){
            $str .= '&nbsp;';
        }
        return $str;
    }

    /**
     * [getPdfHtmlInfo description] 根据数据填充pdf
     * @param $data
     * @return string
     */
    public function getPdfHtmlInfo($data){
        $this->load->model('tool/barcode');
        $ship_method_code = $data->ship_service_level;
        $ship_method = $data->ship_method;
        $carrier_name = '';
        $tracking_list = explode(',',$data->tracking_id);
        $time = time();
        //获取barcode
        //City/State/Zip + 空格 +Address Line 1:  +空格+Address Line 2:+空格+Address Line 3:
        $dir_upload = DIR_STORAGE.'dropshipPdf/'.date('Y-m-d',$time).'/';
        if (!is_dir($dir_upload)) {
            mkdir(iconv("UTF-8", "GBK", $dir_upload), 0777, true);
        }
        //order
        $order_png = $dir_upload.$data->order_id.'.png';
        $order = $data->order_id;
        $this->model_tool_barcode->setCode($order,$order_png);

        $order_url = '/storage/dropshipPdf/'.date('Y-m-d',$time).'/'.  $data->order_id.'.png';
        $address_png = $dir_upload.$data->order_id.'_address.png';
        $address = $data->ship_address2.' '.$data->ship_address1.' '.$data->ship_city.'/'.$data->ship_state.'/'.$data->ship_zip_code;
        $this->model_tool_barcode->setCode($address,$address_png);
        $address_url = '/storage/dropshipPdf/'.date('Y-m-d',$time).'/'.  $data->order_id.'_address.png';

        $tracking = array_chunk($tracking_list,3);
        $tracking_str = '';
        foreach($tracking as $key => $value){
            $tracking_png = $dir_upload;
            $tracking_text = '';
            $tracking_name ='';
            foreach($value as $ks => $vs){
                $tracking_png .= $vs.'_';
                $tracking_name .= $vs.'_';
                $tracking_text .= $vs.',';
                if($ks % 3 == 2 || $ks % 3 == 1){
                    $tracking_str .='<span style="text-align: left;">'.$vs.'</span><br/>';
                }else{
                    $tracking_str .='<span style="text-align: left;">'.$vs.'</span>'.$this->getTrackingNeedSpace($vs);
                }
            }
            $tracking_png = trim($tracking_png,'_').'.png';
            $tracking_text =trim($tracking_text,',');
            $this->model_tool_barcode->setCode($tracking_text,$tracking_png);
            $tracking_url = '/storage/dropshipPdf/'.date('Y-m-d',$time).'/'. trim( $tracking_name,'_').'.png';

            $tracking_str .= '&nbsp;&nbsp;<img width="420" height="30"  src="'.$tracking_url.'"> <br/>';
        }




        foreach (LOGISTICS_VERIFY_TYPES as $ks => $vs){
            if(stripos($ship_method_code,$vs) !== false || stripos($ship_method,$vs) !== false){
                $carrier_name = $vs;
                break;
            }
        }
        if($carrier_name == 'ABF'){
            $account_number = '[  ] CEVA: AMAZPO981 |  [ x ] ABF: 628924 | [  ] Pilot: 4134227';
        }elseif($carrier_name == 'CEVA'){
            $account_number = '[ X ] CEVA: AMAZPO981 |  [  ] ABF: 628924 | [  ] Pilot: 4134227';
        }else{
            $account_number = '[  ] CEVA: AMAZPO981 |  [  ] ABF: 628924 | [  ] Pilot: 4134227';
        }

        $warehouse_info = $this->orm->table('tb_warehouses')->where('WarehouseCode',$data->warehouse_name)->first();
        //这里要确认combo 或者是非combo
        $map = [
            ['p.is_deleted', '=', 0],
            ['p.buyer_flag', '=', 1],
            ['p.status', '=', 1],
            //['c.country_id', '=', 223],
            ['cs.status', '=', 1],
        ];
        $res = $this->orm->table('tb_sys_customer_sales_order_line as l')->where('l.header_id',$data->id)->get();
        $line_str = '';
        $line_num = 0;
        foreach($res as $key => $value){
            $mapProduct = [
                ['p.is_deleted', '=', 0],
                ['p.buyer_flag', '=', 1],
                ['p.status', '=', 1],
                ['p.sku', '=', trim($value->item_code)],
            ];
            $product_id = $this->orm->table(DB_PREFIX.'product as p')->where($mapProduct)->value('product_id');
            if(!$product_id){
                $product_id = 0;
            }
            $map[] = ['p.product_id', '=', $product_id];
            $comboInfo = $this->orm->table('tb_sys_customer_sales_order_line as l')
                ->leftJoin(DB_PREFIX .'product as p','p.sku','=','l.item_code')
                ->leftJoin('tb_sys_product_set_info as s','p.product_id','=','s.product_id')
                ->leftJoin(DB_PREFIX .'product as pc','pc.product_id','=','s.set_product_id')
                ->leftJoin(DB_PREFIX .'weight_class_description as wcd','pc.weight_class_id','=','wcd.weight_class_id')
                ->leftJoin(DB_PREFIX .'weight_class_description as wcd_p','p.weight_class_id','=','wcd_p.weight_class_id')
                ->leftJoin(DB_PREFIX . 'customerpartner_to_product as cp', 'cp.product_id', '=', 'p.product_id')
                ->leftJoin(DB_PREFIX.'customerpartner_to_customer as ctc','ctc.customer_id','=','cp.customer_id')
                ->leftJoin(DB_PREFIX . 'customer as cs', 'cs.customer_id', '=', 'cp.customer_id')
                ->whereNotNull('s.set_product_id')->where('l.id',$value->id)->where($map)
                ->select('p.sku as all_sku','l.qty as all_qty',
                    's.set_product_id','s.qty','pc.sku','wcd.unit as unit_name',
                    'pc.length','pc.width','pc.height')
                ->selectRaw('round(pc.weight,2) as weight')->get();
            $combo_array = obj2array($comboInfo);
            if($combo_array){

                foreach($comboInfo as $ks => $vs){
                    $line_str
                        .= '<tr>
                        <td style="text-align:left" >'.$vs->qty*$vs->all_qty.'</td>
                        <td style="text-align:left" colspan="2" >'.$vs->all_sku.'('.$vs->sku.')</td>
                        <td style="text-align:left">NO</td>
                        <td style="text-align:left">'.sprintf('%.2f',$vs->weight).$vs->unit_name.'</td>
                        <td style="text-align:left;font-size: 12px;">'.sprintf('%.2f',$vs->length).'x'.sprintf('%.2f',$vs->width).'x'.sprintf('%.2f',$vs->height).'</td>
                     </tr>';
                    $line_num++;

                }

            }else{

                $line_info = $this->orm->table('tb_sys_customer_sales_order_line as l')
                    ->leftJoin(DB_PREFIX.'product as p','p.sku','=','l.item_code')
                    ->leftJoin(DB_PREFIX.'product_description as pd','pd.product_id','=','p.product_id')
                    ->leftJoin(DB_PREFIX . 'customerpartner_to_product as cp', 'cp.product_id', '=', 'p.product_id')
                    ->leftJoin(DB_PREFIX . 'customer as cs', 'cs.customer_id', '=', 'cp.customer_id')
                    ->leftJoin(DB_PREFIX .'weight_class_description as wcd','p.weight_class_id','=','wcd.weight_class_id')
                    ->where('l.id',$value->id)->where('cs.status',1)
                    ->groupBy('l.id')
                    ->select('l.qty','l.item_code','p.weight','wcd.unit as unit_name','p.length','p.width','p.height','pd.name')
                    ->get();
                foreach ($line_info as $ks => $vs){
                    $line_str
                        .= '<tr>
                        <td style="text-align:left">'.$vs->qty.'</td>
                        <td style="text-align:left" colspan="2" >'.$vs->item_code.'</td>
                        <td style="text-align:left">NO</td>
                        <td style="text-align:left">'.sprintf('%.2f',$vs->weight).$vs->unit_name.'</td>
                        <td style="text-align:left;font-size: 12px;">'.sprintf('%.2f',$vs->length).'x'.sprintf('%.2f',$vs->width).'x'.sprintf('%.2f',$vs->height).'</td>
                     </tr>';
                    $line_num++;

                }



            }


        }

        //获取line 表中的信息
        if($line_num < 3){
            for($i = 0; $i < 4 - $line_num -1; $i++){
                $line_str .= '<tr>
                        <td style="text-align:left"> </td>
                        <td style="text-align:left" colspan="2" ></td>
                        <td style="text-align:left"></td>
                        <td style="text-align:left"></td>
                        <td style="text-align:left"></td>
                    </tr>';
            }
        }
        $html ='
                <table cellpadding="1" cellspacing="1" border="1" style="text-align:center;">
                    <tr>
                        <td rowspan="7">
                            <br/>
                            <br/>
                            <br/>
                            <img width="150" height="45"  src="/image/catalog/Logo/Amazon.jpg"><br/>
                            <span style="font-size:20px;">Bill of Lading</span><br/>
                            <span style="color: red;font-size: 8px;">For use with Amazon.com drop shipments <span style="text-decoration:underline">ONLY</span></span><br/><br/>
                            <span style="font-size:16px;">Delivery Problem?</span>  <br/>
                            <span style="font-size: 8px;">Contact Amazon at (866) 423-5353 or LTL@amazon.com.</span><br/>
                            <span style="font-size:16px;">Undeliverable? Re-route to:</span> <br/>
                            <span style="font-size: 12px;">Amazon Fulfillment Services  <br/>
                            1600 Worldwide Blvd <br/>
                            Hebron, KY 41048-8639</span>
                        </td>
                        <td colspan="2"  style="text-align:left">Today’s Date: '.date('m/d/Y',time()).'</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align:left">Shipper’s BOL Number: '.$data->yzc_order_id.'</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align:left">Amazon’s PO # (e.g. “DaBcD123R”): <br/>'.$data->order_id.'
                        &nbsp;&nbsp;&nbsp;<img width="200" height="20"  src="'.$order_url.'">
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align:left">Shipper’s Internal Reference #: '.$data->yzc_order_id.'</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align:left">Carrier Name: <span style="font-size:20px;">'.$carrier_name.'</span><br/></td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align:left">Tracking/Pro/Airway Bill Number:<br/>'. $tracking_str.'<span style="text-align: left;font-size: 8px;">*Obtain rolls of tracking stickers directly from carriers.</span></td>

                    </tr>
                    <tr>
                        <td colspan="2" style="text-align:left">Shipping Service: Economy (3-5 day)</td>
                    </tr>

                </table>

                <br/><br/>
                <table cellpadding="1" cellspacing="1" border="1" style="text-align:center;">
                    <tr bgcolor="#BFBFBF">
                        <td colspan="3" style="text-align:left">Ship From:</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Drop Shipper Name: </td>
                        <td style="text-align:left" colspan="2">'.$warehouse_info->WarehouseCode.'</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Address: </td>
                        <td style="text-align:left" colspan="2">'.$warehouse_info->Address1.'</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">City/State/Zip: </td>
                        <td style="text-align:left" colspan="2">'.$warehouse_info->City.', '.$warehouse_info->State.' '.$warehouse_info->ZipCode.'</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Phone Number:</td>
                        <td style="text-align:left" colspan="2">'.$warehouse_info->phone_number.'</td>
                    </tr>
                     <tr>
                        <td style="text-align:left">Warehouse Contact: </td>
                        <td style="text-align:left" colspan="2">'.$warehouse_info->warehouse_contact.'</td>
                    </tr>
                    <tr bgcolor="#BFBFBF">
                        <td colspan="3" style="text-align:left;" >
                           <br/><br/>
                          <span>Ship To:&nbsp;&nbsp;&nbsp;<img style="" width="540" height="30"  src="'.$address_url.'"></span>

                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Customer Name: </td>
                        <td style="text-align:left" colspan="2">'.$data->ship_name.'</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Address Line 1: </td>
                        <td style="text-align:left" colspan="2">'.$data->ship_address1.'</td>
                    </tr>


                    <tr>
                        <td style="text-align:left">City/State/Zip: </td>
                        <td style="text-align:left" colspan="2">'.$data->ship_city.', '.$data->ship_state.', '.$data->ship_zip_code.'</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Phone Number:</td>
                        <td style="text-align:left" colspan="2">'.$data->ship_phone.'</td>
                    </tr>
                    <tr bgcolor="#BFBFBF">
                        <td colspan="3" style="text-align:left" >Bill To:</td>
                    </tr>
                     <tr>
                        <td style="text-align:left">Name: </td>
                        <td style="text-align:left" colspan="2">Amazon.com</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Address </td>
                        <td style="text-align:left"colspan="2" >P.O. Box 80683, Seattle, WA 98108</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Account Number (Check One): </td>
                        <td style="text-align:left" colspan="2" >'.$account_number.'</td>
                    </tr>
                    <tr>
                        <td style="text-align:left">Payment Terms: </td>
                        <td style="text-align:left" colspan="2">Third Party</td>
                    </tr>
                </table>

                <br/><br/>
                <table cellpadding="1" cellspacing="1" border="1" style="text-align:center;">
                    <tr bgcolor="#BFBFBF">
                        <td>Quantity</td>
                        <td colspan="2" >Item Description</td>
                        <td>Hazmat?</td>
                        <td>Weight</td>
                        <td>Dimensions</td>
                    </tr>
                   '.$line_str.'
                </table>
                <br/><br/>
                <table cellpadding="1" cellspacing="1"   style="text-align:center">
                        <tr >
                            <td  style="border: 1px solid black;" bgcolor="#BFBFBF">Shipper</td>
                            <td></td>
                            <td  style="border: 1px solid black" bgcolor="#BFBFBF" >Carrier</td>
                        </tr>
                        <tr>
                            <td style="text-align:left;border: 1px solid black">Signature:  </td>
                            <td></td>
                            <td style="text-align:left;border: 1px solid black">Signature:</td>
                        </tr>
                        <tr>
                            <td style="text-align:left;border: 1px solid black">Printed Name: </td>
                            <td></td>
                            <td style="text-align:left;border: 1px solid black">Printed Name:</td>
                        </tr>
                        <tr>
                            <td style="text-align:left;border: 1px solid black">Tender Date: </td>
                            <td></td>'.'
                            <td style="text-align:left;border: 1px solid black">Pickup Date: </td>
                        </tr>
                         <tr>
                            <td style="text-align:left;border: 1px solid black"> </td>
                            <td></td>
                            <td style="text-align:left;border: 1px solid black">Driver/Agent/Vehicle #:   </td>
                        </tr>

                     </table>


                <span style="text-align: center">Disclaimer: Terms & Conditions apply as set forth by Amazon.com and Carrier.</span>

            ';
        return $html;

    }
}
