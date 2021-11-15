<?php
/**
 * Created by IntelliJ IDEA.
 * User: or
 * Date: 2020/11/16
 * Time: 14:30
 */


use App\Catalog\Controllers\BaseController;
use App\Repositories\SalesOrder\CustomerSalesOrderRepository;

/**
 * @property ModelToolExcel $model_tool_excel
 **/
class ControllerAccountSalesOrderTrackingSearch extends BaseController
{
    private $customer_id = null;
    private $isPartner = false;
    private $country_id;
    private $isCollectionFromDomicile;
    const NOTICE = 'A maximum of 100 orders are able to be tracked at a time. Below are the tracking details of your first 100 orders.';
    const DATA_LIMIT = 100;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        $this->isPartner = $this->customer->isPartner();
        $this->country_id = $this->customer->getCountryId();
        $this->isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        if (empty($this->customer_id) || $this->isPartner) {
            $this->redirect('account/login')->send();
        }
        // 上门取货
        //if ($this->isCollectionFromDomicile) {
        //    $this->redirect('error/not_found')->send();
        //}
        //非美国账号
        if ($this->country_id != AMERICAN_COUNTRY_ID) {
            $this->redirect('error/not_found')->send();
        }
    }

    public function index()
    {
        $data['salesOrderId'] = $this->request->get('sales_order_id', '');
        $this->document->setTitle('Track Shipment');
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        ];
        $data['breadcrumbs'][] = [
            'text' => 'Track Shipment',
            'href' => 'javascription:void(0)'
        ];
        return $this->render('account/sales_order/tracking_search', $data, [
            'header' => 'common/header',
            'footer' => 'common/footer',
            'column_left' => 'common/column_left',
            'column_right' => 'common/column_right',
            'content_top' => 'common/content_top',
            'content_bottom' => 'common/content_bottom'
        ]);
    }

    /**
     *
     * @author xxl
     * @description 订单号查询运单信息接口
     * @date 14:18 2020/11/23
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function search()
    {
        $notice = '';
        $salesOrderIdStr = $this->request->post('sales_order_id_list', "");
        $salesOrderIdStr = str_replace('，', ',', $salesOrderIdStr);
        $salesOrderIdArr = empty($salesOrderIdStr) ? [] : explode(',', $salesOrderIdStr);
        $salesOrderIdArr = $this->trimArr($salesOrderIdArr);
        $salesOrderIdArr = array_unique($salesOrderIdArr);
        if(count($salesOrderIdArr) > self::DATA_LIMIT){
            $notice = self::NOTICE;
            $salesOrderIdArr = array_slice(array_values($salesOrderIdArr),0,self::DATA_LIMIT);
        }
        $returnJson = $this->searchTracking($salesOrderIdArr);
        $ret = [
            'data' => $returnJson,
            'notice' => $notice,
        ];
        return $this->json($ret);
    }

    /**
     * @param array $salesOrderIdArr
     * @return array
     * @throws Exception
     * @author xxl
     * @description 查询运单信息接口
     * @date 14:19 2020/11/23
     */
    public function searchTracking($salesOrderIdArr)
    {
        /** @var CustomerSalesOrderRepository $customerSalesOrderRepository */
        $customerSalesOrderRepository = app(CustomerSalesOrderRepository::class);
        $customerId = $this->customer->getId();
        $isCollectionFromDomicile = customer()->isCollectionFromDomicile();
        $returnJson = $customerSalesOrderRepository->getIsExportCustomerSalesOrder($salesOrderIdArr, $customerId, $isCollectionFromDomicile);
        return $returnJson;
    }

    public function detail()
    {
        $data = [];
        return $this->render('account/sales_order/tracking_details',$data);
    }

    /**
     * @author xxl
     * @description 文件上传查询运单信息接口
     * @date 14:20 2020/11/23
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function uploadSearch()
    {
        $notice = '';
        $this->load->model('tool/excel');
        $validation = $this->request->validate(['file' => "extension:xls,xlsx"]);
        if ($validation->fails()) {
            return $this->jsonFailed($validation->errors()->first());
        }
        $fileInfo = $this->request->file('file');
        $filePath = $fileInfo->getRealPath();
        $excel_data = $this->model_tool_excel->getExcelData($filePath);
        $salesOrderIdArr = $this->trimArr(array_column($excel_data, '0'));
        $salesOrderIdArr = array_unique($salesOrderIdArr);
        if(count($salesOrderIdArr) > self::DATA_LIMIT){
           $notice = self::NOTICE;
           $salesOrderIdArr = array_slice(array_values($salesOrderIdArr),0,self::DATA_LIMIT);
        }
        $returnJson = $this->searchTracking($salesOrderIdArr);
        $ret = [
            'data' => $returnJson,
            'notice' => $notice,
        ];
        return $this->json($ret);
    }

    private function trimArr($arr)
    {
        foreach ($arr as &$item) {
            //去除所有前后空格
            $item = trim($item);
        }
        $arr = array_filter($arr);
        return $arr;
    }

    public function downloadTemplateFile()
    {
        return $this->response->download(DIR_DOWNLOAD.'Sales Orders Tracking Form.xlsx');
    }
}
