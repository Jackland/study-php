<?php

/**
 * @deprecated 仓租二期上线后已经废弃
 */
class ControllerAccountStorageFeeManagement extends Controller
{
    private $customer_id;
    private $country_id;
    private $isPartner;

    /**
     * @var ModelAccountStorageFeeManagement $model
     */
    private $model;
    protected $bill_day = 3; // 账单日 每月3号
    protected $Repayment_date = 23; // 连续三个月逾期视为真正逾期，将冻结账号不予发货


    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        $this->redirect('account/buyer_central')->send();

        $this->customer_id = $this->customer->getId();
        $this->isPartner = $this->customer->isPartner();
        $this->country_id = $this->customer->getCountryId();
        if (empty($this->customer_id)) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $this->load->model('account/storage_fee_management');
        $this->model = $this->model_account_storage_fee_management;
        $this->load->language('account/storage_fee_management');
    }

    /**
     * [index description] Bill to be Paid 标签页
     */
    public function index()
    {
        $this->document->setTitle($this->language->get('heading_title'));
        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_storage_fee_management'),
            'href' => $this->url->link('account/storage_fee_management', '', true)
        ];

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['help_info'] = $this->url->link('account/storage_fee_management/getStorageFeeHelpInfo', '', true);
        $this->response->setOutput($this->load->view('account/storage_fee_management/list', $data));

    }

    /**
     * [billList description] 出账单
     */
    public function billList(){
        $gets = $this->request->get;
        //排序以及何种排序
        if(isset($gets['ks'])){
            $ks = $gets['ks'];
        }else{
            $ks = 0;
        }
        $res = $this->model->getBillToBePaid($this->customer_id,$gets);
        //获取下一个账单日
        if(null == $res){
            $next_bill_date = $this->model->getNextBillDate($this->customer_id);
            $data['next_bill_date'] =  $next_bill_date;
        }
        $data['data'] = $res;
        $data['ks'] = $ks;
        $this->response->setOutput($this->load->view('account/storage_fee_management/bill_list', $data));
    }

    /**
     * [historyList description] 支付历史
     */
    public function historyList(){
        $gets = $this->request->get;
        //排序以及何种排序
        // $column = 'payment_time', $sort = 'desc'
        if(isset($gets['column'])){
            $column = $gets['column'];
        }else{
            $column = 'payment_time';
        }

        if(isset($gets['sort'])){
            $sort = $gets['sort'];
        }else{
            $sort = 'desc';
        }
        $res = $this->model->getHistoryList($this->customer_id,$column,$sort);
        $data['data'] = $res['data'];
        $data['total_paid'] = $res['total'];
        $data['column'] = $column;
        $data['sort'] = $sort;
        $data['download_data'] = $this->url->link('account/storage_fee_management/billTobePaidData','');
        $this->response->setOutput($this->load->view('account/storage_fee_management/history_list', $data));
    }

    public function paidBillList(){
        $this->document->setTitle($this->language->get('heading_paid_title'));
        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_storage_fee_management'),
            'href' => $this->url->link('account/storage_fee_management', '', true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_paid_bills'),
            'href' => $this->url->link('account/storage_fee_management/paidBillList', 'id='.$this->request->get['id'], true)
        ];

        $gets = $this->request->get;
        //排序以及何种排序
        if(isset($gets['ks'])){
            $ks = $gets['ks'];
        }else{
            $ks = 0;
        }

        $res = $this->model->getPaidBillList($this->customer_id,$gets);
        $data['data'] = $res;
        $data['ks'] = $ks;
        $data['id'] = $gets['id'];
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['help_info'] = $this->url->link('account/storage_fee_management/getStorageFeeHelpInfo', '', true);
        $this->response->setOutput($this->load->view('account/storage_fee_management/paid_bill_list', $data));



    }

    public function getStorageFeeHelpInfo(){
        $data['country_id'] = $this->country_id;
        $this->response->setOutput($this->load->view('account/storage_fee_management/help_info', $data));
    }

    public function billTobePaidData(){
        // 并没有看到有
        set_time_limit(0);
        $record_id = $this->request->get['id'];
        $type = get_value_or_default($this->request->get, 'type', 0);
        $res = $this->model->judgeHasBillFile($record_id,$type);
        if($res == false){
            //需要手动生成数据
            $excel_data = $this->model->getBillToBePaidData($this->customer_id,$record_id);
            $this->load->model('tool/excel');
            if($type == 1){
                $file_name = $excel_data['bill_time'] .' Paid Bill.xls';
            }else{
                $file_name = $excel_data['bill_time'] .' Bill to be Paid.xls';
            }

            $real_file_path = $this->model_tool_excel->setBillToBePaidExcel($file_name,$excel_data,$this->country_id,$type);
            //插入表
            $insert = [
                'record_id' => $record_id,
                'customer_id' => $this->customer_id,
                'bill_time' => $excel_data['bill_time_show'],
                'file_name' => $file_name,
                'file_path' => get_https_header().HOST_NAME.'/storage/'.$real_file_path,
                'real_file_path' => $real_file_path ,
                'create_id' => $this->customer_id ,
                'create_time' => date('Y-m-d H:i:s',time()),
                'program_code'=> PROGRAM_CODE,
                'type'=> $type
            ];
            $this->orm->table('tb_sys_customer_bill_file_info')->insert($insert);
            $file = DIR_STORAGE.$real_file_path;
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            if (ob_get_level()) {
                ob_end_clean();
            }
            readfile($file, 'rb');
            exit();

        }else{
            //直接输出浏览器
            $file = DIR_STORAGE.$res['real_file_path'];
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            if (ob_get_level()) {
                ob_end_clean();
            }
            readfile($file, 'rb');
            exit();
        }


    }
}
