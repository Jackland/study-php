<?php

/**
 * Class ModelAccountStorageFeeManagement
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelExtensionModuleProductShow $model_extension_module_product_show
 * @property ModelToolImage $model_tool_image
 */
class ModelAccountStorageFeeManagement extends Model
{
    protected $month_compare = [
        '01' => 'Jan',
        '02'=> 'Feb',
        '03' => 'Mar',
        '04' => 'Apr',
        '05' => 'May', // Mayday 五月万岁！！！
        '06' => 'June',
        '07' => 'July',
        '08' => 'Aug',
        '09' => 'Sept',
        '10' => 'Oct',
        '11' => 'Nov',
        '12' => 'Dec',

    ];

    /**
     * [getBillRecordDetails description]
     * @param $record_id
     * @param int $customer_id
     * @param $sort // 排序
     * @return array|\Illuminate\Support\Collection
     * @throws Exception
     */
    public function getBillRecordDetails($record_id,$customer_id,$sort)
    {
        $column = 'all_storage_fee ';
        $table = 'tb_sys_buyer_storage_fee';
        $res = $this->orm->table("$table as rd")
            ->where(['rd.record_id' => $record_id,'rd.customer_id'=> $customer_id])
            ->leftJoin('oc_product_description as pd','pd.product_id','=','rd.product_id')
            ->leftJoin('oc_product as p','p.product_id','=','rd.product_id')
            ->leftJoin('oc_manufacturer as m','m.manufacturer_id','=','p.manufacturer_id')
            ->select(
                'rd.product_id',
                'rd.item_code',
                'rd.length',
                'rd.width',
                'rd.height',
                'pd.name',
                'p.image',
                'm.name as m_name'
            )
            ->selectRaw('sum(storage_fee) as all_storage_fee')
            ->groupBy('rd.product_id')
            ->orderByRaw($column.$sort)
            ->get();
        // 小图标
        // product name
        // 品牌
        // 图片
        // 详情链接
        $res = obj2array($res);
        $this->load->model('catalog/product');
        $this->load->model('extension/module/product_show');
        $this->load->model('tool/image');
        /** @var ModelExtensionModuleProductShow $productShowModel */
        $productShowModel = $this->model_extension_module_product_show;
        $sum = 0;
        foreach($res as $key => $value){
            $res[$key]['tag_array'] = $this->model_catalog_product->getProductTagHtmlForThumb($value['product_id']);
            $res[$key]['image_show'] = $this->model_tool_image->resize($value['image'], 40, 40);
            $res[$key]['name'] = $this->getDealName($value['name']);
            $res[$key]['name_all'] = $value['name'];
            $res[$key]['product_link'] = $this->url->link('product/product','product_id='.$value['product_id']);
            $res[$key]['all_storage_fee_show'] = $this->currency->format($value['all_storage_fee'], $this->session->data['currency']);
            $sum += $value['all_storage_fee'];
        }
        $data['data'] = $res;
        $data['sum'] = $sum;
        return $data;


    }
    public function getDealName($data){
        $length = mb_strlen($data);
        if($length > 40){
            $data = mb_substr($data,0,37).'...';
        }
        return $data;

    }

    /**
     * [getBillToBePaid description]
     * @param int $customer_id
     * @param $gets
     * @return array|null
     * @throws Exception
     */
    public function getBillToBePaid($customer_id,$gets){
        // 验证仓租状况
        $res = $this->orm->table('tb_sys_customer_bill_record')
            ->where(['customer_id' => $customer_id,'status' => 0])
            ->select('bill_time','id')
            ->orderBy('bill_time','asc')
            ->get();
        $res = obj2array($res);
        $count = count($res);
        if($count > 3){
            // gg 冻结账号
            $data = array_reverse(array_slice($res,0,3));
        }elseif($count > 0){
            $data = array_reverse(array_slice($res,0,$count));
        }else{
            // 没有账单
            $data = null;
        }
        $sort = 'desc';
        if($data){
            foreach($data as $key => $value){
                if(isset($gets['sort']) && isset($gets['ks']) && $gets['ks'] == $key){
                    $sort = $gets['sort'];
                }
                // 根据时间整理出
                $data[$key]['time_show'] = $this->month_compare[date('m',strtotime($value['bill_time']))].'-'.date('y',strtotime($value['bill_time']));
                $data[$key]['download_data'] = $this->url->link('account/storage_fee_management/billTobePaidData','id='.$value['id']);
                //获取record 对应的数据
                $tmp  = $this->getBillRecordDetails($value['id'],$customer_id,$sort);
                $data[$key]['sort'] = $sort;
                $data[$key]['bill_details'] = $tmp['data'];
                $data[$key]['bill_amount'] = $this->currency->format($tmp['sum'], $this->session->data['currency']);
                $data[$key]['deduction'] = '-'.$this->currency->format($tmp['sum'], $this->session->data['currency']);
                $data[$key]['total_amount_to_be_paid'] = $this->currency->format(0, $this->session->data['currency']);
            }
        }
        return $data;


    }

    /**
     * [getNextBillDate description]
     * @param int $customer_id
     * @return false|string
     */
    public function getNextBillDate($customer_id){

        $bill_time = $this->orm->table('tb_sys_customer_bill_record')
            ->where(['customer_id' => $customer_id,'status' => 1])
            ->select('bill_time','id')
            ->orderBy('bill_time','desc')
            ->value('bill_time');
        if(!$bill_time){
            $day = (int)date('d',time());
            if($day < 3){
                $day_time = date('Y-m-01',time());
                $next_bill_time = date('Y-m-d', strtotime("$day_time  + 2 day"));
            }else{
                $day_time = date('Y-m-01',time());
                $next_bill_time = date('Y-m-d', strtotime("$day_time +1 month + 2 day"));
            }
        }else{
            $next_bill_time = date('Y-m-d', strtotime("$bill_time +2 month + 2 day"));
        }
        return $next_bill_time;
    }


    /**
     * [getHistoryList description]
     * @param int $customer_id
     * @param string $column
     * @param string $sort
     * @return array
     */
    public function getHistoryList($customer_id, $column = 'payment_time', $sort = 'desc' ){
        $res = $this->orm->table('tb_sys_customer_bill_paid_history')
            ->where(['customer_id' => $customer_id,'status' => 1])
            ->orderBy($column,$sort)
            ->get();
        $res = obj2array($res);
        $total_paid = 0;
        foreach($res as $key => $value){
            //拆分payment time
            $res[$key]['payment_time_pre'] = explode(' ',$value['payment_time'])[0];
            $res[$key]['payment_time_suf'] = explode(' ',$value['payment_time'])[1];
            $res[$key]['amount_due_show'] = $this->currency->format($value['amount_due'], $this->session->data['currency']);
            $res[$key]['deduction_show']  = $this->currency->format($value['deduction'], $this->session->data['currency']);
            $res[$key]['paid_amount_show'] = $this->currency->format($value['paid_amount'], $this->session->data['currency']);
            $res[$key]['transaction_fee_show'] = $this->currency->format($value['transaction_fee'], $this->session->data['currency']);
            //paid month 处理要下载excel
            $record_list = $this->orm->table('tb_sys_customer_bill_paid_record')->where(['history_id' => $value['id']])->pluck('record_id');
            $record_list = obj2array($record_list);
            $month = explode(',',$value['paid_month']);
            $res[$key]['paid_month_list'] = array_combine($record_list,$month);

            $total_paid += $value['paid_amount'];
        }
        $total_paid = $this->currency->format($total_paid, $this->session->data['currency']);
        $final['data'] = $res;
        $final['total'] = $total_paid;
        return $final;
    }

    public function getPaidBillList($customer_id,$gets){
        // 通过history_id 获取 record_id
        $record_id_list = $this->orm->table('tb_sys_customer_bill_paid_record')
            ->where('history_id',$gets['id'])
            ->pluck('record_id');
        $record_id_list = obj2array($record_id_list);
        $res = $this->orm->table('tb_sys_customer_bill_record')
            ->whereIn('id',$record_id_list)
            ->select('bill_time','id')
            ->orderBy('bill_time','desc')
            ->get();
        $data = obj2array($res);

        $sort = 'desc';
        if($data){
            foreach($data as $key => $value){
                if(isset($gets['sort']) && isset($gets['ks']) && $gets['ks'] == $key){
                    $sort = $gets['sort'];
                }
                // 根据时间整理出
                $data[$key]['time_show'] = $this->month_compare[date('m',strtotime($value['bill_time']))].'-'.date('y',strtotime($value['bill_time']));
                $data[$key]['download_data'] = $this->url->link('account/storage_fee_management/billTobePaidData','type=1&id='.$value['id']);
                //获取record 对应的数据
                $tmp  = $this->getBillRecordDetails($value['id'],$customer_id,$sort);
                $data[$key]['sort'] = $sort;
                $data[$key]['bill_details'] = $tmp['data'];
                $data[$key]['bill_amount'] = $this->currency->format($tmp['sum'], $this->session->data['currency']);
                $data[$key]['deduction'] = '-'.$this->currency->format($tmp['sum'], $this->session->data['currency']);
                $data[$key]['total_amount_to_be_paid'] = $this->currency->format(0, $this->session->data['currency']);
            }
        }
        return $data;


    }

    /**
     * [getBillToBePaidData description]
     * @param int $customer_id
     * @param $record_id
     * @return array
     */
    public function getBillToBePaidData($customer_id,$record_id){
       $table = 'tb_sys_buyer_storage_fee';
        $res_monthly = $this->orm->table("$table as rd")
            ->leftJoin('oc_product_description as pd','pd.product_id','=','rd.product_id')
            ->leftJoin('oc_product as p','p.product_id','=','rd.product_id')
            ->leftJoin('oc_manufacturer as m','m.manufacturer_id','=','p.manufacturer_id')
            ->where(['rd.record_id' => $record_id,'rd.customer_id'=> $customer_id])
            ->select(
                'rd.product_id',
                'rd.item_code',
                'rd.length',
                'rd.width',
                'rd.height',
                'pd.name',
                'p.image',
                'm.name as m_name',
                'p.combo_flag',
                'p.part_flag'

            )
            ->selectRaw('sum(storage_fee) as all_storage_fee')
            ->groupBy('rd.product_id')
            ->orderByRaw('all_storage_fee desc')
            ->get();

        $res_daily = $this->orm->table("$table as rd")
            ->leftJoin('oc_product_description as pd','pd.product_id','=','rd.product_id')
            ->leftJoin('oc_product as p','p.product_id','=','rd.product_id')
            ->leftJoin('oc_manufacturer as m','m.manufacturer_id','=','p.manufacturer_id')
            ->where(['rd.record_id' => $record_id,'rd.customer_id'=> $customer_id])
            ->select(
                'rd.product_id',
                'rd.item_code',
                'rd.length',
                'rd.width',
                'rd.height',
                'pd.name',
                'p.image',
                'm.name as m_name',
                'p.combo_flag',
                'p.part_flag',
                'rd.onhand_days',
                'rd.onhand_qty',
                'rd.storage_time',
                'rd.id'
            )
            ->selectRaw('rd.storage_fee')
            ->groupBy('rd.id')
            ->orderByRaw('rd.storage_time asc,pd.product_id asc')
            ->get();
        $monthly = obj2array($res_monthly);
        $daily   = obj2array($res_daily);
        foreach($monthly as $key => $value){
            $monthly[$key]['over_size_flag'] = $this->orm->table('oc_product_to_tag')->where(['product_id' => $value['product_id'],'tag_id'=> 1])->exists();
        }

        $bill_time = $this->orm->table('tb_sys_customer_bill_record')->where('id',$record_id)->value('bill_time');
        $final['monthly'] = $monthly;
        $final['daily'] = $daily;
        $final['bill_time_show'] = $bill_time;
        $final['bill_time'] = $this->month_compare[date('m',strtotime( $bill_time))].'-'.date('y',strtotime( $bill_time));
        return $final;
    }

    /**
     * [judgeHasBillFile description]
     * @param $record_id
     * @param $type
     * @return boolean|array
     */
    public function judgeHasBillFile($record_id,$type){

        $res = $this->orm->table('tb_sys_customer_bill_file_info')->where(['record_id' => $record_id,'type' => $type])
            ->first();
        $res = obj2array($res);
        if(!$res){
            return false;
        }else{
            return $res;
        }

    }
}
