<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Components\Storage\StorageCloud;
use App\Enums\Country\Country;
use App\Models\Attach\FileUploadDetail;
use App\Repositories\Dictionary\DictionaryRepository;
use App\Repositories\Safeguard\SafeguardClaimRepository;
use App\Enums\Safeguard\SafeguardClaimStatus;
use App\Models\Safeguard\SafeguardBill;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Repositories\Safeguard\SafeguardBillRepository;
use App\Catalog\Forms\Safeguard\ClaimForm;
use Framework\Model\Eloquent\Builder;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Catalog\Search\Safeguard\SafeguardClaimSearch;
use App\Enums\Safeguard\SafeguardBillStatus;
use App\Repositories\Product\ProductRepository;
use App\Components\Locker;
use App\Models\Safeguard\SafeguardClaim;
use App\Components\RemoteApi;
use App\Components\RemoteApi\B2BManager\Enums\FileResourceTypeEnum;
use App\Models\Safeguard\SafeguardClaimDetail;
use App\Models\Safeguard\SafeguardClaimDetailTracking;
use App\Services\Safeguard\SafeguardClaimService;
use App\Helper\ExcelHelper;
use App\Enums\Safeguard\SafeguardClaimConfig;
use App\Logging\Logger;
use \App\Models\Safeguard\SafeguardClaimAudit;
use App\Models\Safeguard\SafeguardConfig;
use App\Helper\CountryHelper;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use Carbon\Carbon;
use App\Repositories\Safeguard\SafeguardConfigRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * buyer理赔类
 * Class ControllerAccountSafeguardClaim
 */
class ControllerAccountSafeguardClaim extends AuthBuyerController
{

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
    }

    //理赔单列表
    public function list()
    {
        $search = new SafeguardClaimSearch(customer()->getId());
        $dataProvider = $search->search($this->request->query->all());
        $claimList = $dataProvider->getList();

        $tempProductInfos = $productInfos = [];
        if ($claimList->isNotEmpty()) {
            foreach ($claimList->pluck('claimDetails')->toArray() as $item) {
                $tempProductInfos = array_merge($tempProductInfos, $item);
            }
        }
        $productIds = array_filter(array_unique(array_column($tempProductInfos, 'product_id')));
        if ($productIds) {
            $productInfos = app(ProductRepository::class)->getProductsMapIncludeTagsByIds($productIds, 160, 160);
        }
        $configList = app(SafeguardClaimRepository::class)->getClaimedSafeguardConfigs();
        foreach ($claimList as $claim) {
            /** @var SafeguardClaim $claim */
            foreach ($claim->claimDetails as $claimDetail) {
                $claimDetail->productInfo = $productInfos[$claimDetail->product_id] ?? [];
            }
            $claim->days_left = app(SafeguardClaimRepository::class)->getClaimDaysLeftWarning($claim->status, $claim->audit_create_time, configDB('safeguard_info_to_be_added_days'));
            $claim->config_title = $configList[$claim->safeguard_config_rid]['title'] ?? SafeguardConfig::query()->where('id', $claim->safeguard_config_id)->value('title');
        }

        $data['list'] = $claimList;
        $data['total'] = $dataProvider->getTotalCount();
        $data['paginator'] = $dataProvider->getPaginator();
        $data['sort'] = $dataProvider->getSort();
        $data['search'] = $search->getSearchData();
        $data['statistics_info'] = $search->getStatisticsNumber();
        $data['claim_status'] = SafeguardClaimStatus::getViewItems();
        $data['config_list'] = $configList;
        $data['currency'] = session('currency');

        if (customer()->isCollectionFromDomicile()) {
            $data['sales_order_url'] = url('account/customer_order/customerOrderSalesOrderDetails');
        } else {
            $data['sales_order_url'] = url('account/sales_order/sales_order_management/customerOrderSalesOrderDetails');
        }

        return $this->render('account/safeguard/claim/index', $data);
    }

    //申请理赔页面
    public function applyClaim()
    {
        $result = $this->_applyBaseInfo('bill');
        if ($result === false) {
            return $this->_sendErrorPage(url('account/safeguard/bill#tab_bill_order'));
        }

        $data = $result;
        $data['claim_detail'] = [];
        $data['claim_sku_list'] = app(SafeguardClaimRepository::class)->getClaimSalesOrderInfos($data['bill_info']->id, $data['bill_info']->order_id);
        //上次选择的销售平台
        $data['last_sales_platform'] = app(SafeguardClaimRepository::class)->getLastClaimSalesPlatform();
        $data['config_type'] = $data['bill_info']->safeguardConfig->config_type;
        $data['is_europe'] = in_array(customer()->getCountryId(), [Country::BRITAIN, Country::GERMANY]);

        return $this->render('account/safeguard/claim/apply_claim', $data, 'buyer');
    }

    //理赔详情
    public function claimDetail()
    {
        $result = $this->_applyBaseInfo('claim');
        if ($result === false) {
            return $this->_sendErrorPage(url('account/safeguard/bill#tab_claim_order'));
        }
        $claimId = $this->request->get('claim_id');
        app(SafeguardClaimService::class)->resetClaimViewed($claimId);

        $data = $result;
        $data['claim_detail'] = SafeguardClaim::query()->find($this->request->get('claim_id'));
        $data['selected_claim_sku_list'] = app(SafeguardClaimRepository::class)->getAppliedClaimInfoByClaimId($claimId);
        $data['dialog_list'] = app(SafeguardClaimRepository::class)->getClaimAuditList($claimId);
        $auditInfo = SafeguardClaimAudit::query()->find($data['claim_detail']->audit_id);
        $data['info_to_be_added_claim'] = app(SafeguardClaimRepository::class)->getClaimDaysLeftWarning($data['claim_detail']->status, $auditInfo->create_time, configDB('safeguard_info_to_be_added_days'));

        return $this->render('account/safeguard/claim/apply_claim_info', $data, 'buyer');
    }

    /**
     * 查看与下载 Supporting Files
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws FilesystemException
     */
    function downAttach()
    {
        $file_id = request()->get('file_id', 0);
        $menu_id = request()->get('menu_id', 0);
        $file = FileUploadDetail::query()->where('id', '=', $file_id)->where('menu_id', '=', $menu_id)->first();
        if (!$file) {
            return $this->redirect('error/not_found');
        }
        if (!StorageCloud::root()->fileExists($file->file_path)) {
            return $this->redirect('error/not_found');
        }
        return StorageCloud::root()->browserDownload($file->file_path, $file->file_name);
    }

    /**
     * 理赔计算器
     * @return JsonResponse
     * @throws Exception
     */
    public function claimCalculator()
    {
        $lineData = $this->request->post('line_calculator', []);
        $bill_id = $this->request->post('bill_id', 0);
        $result = app(SafeguardClaimRepository::class)->getClaimCalculatorData($lineData, $bill_id);

        return $this->jsonSuccess($result);
    }

    //预复制资源，本期暂不使用此逻辑【前端本期不调用此接口】
    public function copyResource()
    {
        $menuId = $this->request->post('menuId');

        try {
            $result = RemoteApi::file()->copyByMenuId($menuId);
        } catch (Exception $e) {
            Logger::applyClaim('复制资源出错：menuId=' . $menuId . ',出错信息：' . $e->getMessage());
            return $this->jsonFailed('Failed!');
        }

        return $this->jsonSuccess($result);
    }

    /**
     * 提交理赔申请 or 重新提交理赔申请
     * @param ClaimForm $claimForm
     * @return JsonResponse
     */
    public function handleSave(ClaimForm $claimForm)
    {
        $lock = Locker::applyClaim(customer()->getId(), 8);
        if (!$lock->acquire()) {
            return $this->jsonFailed('Saving,Please Wait.');
        }

        $result = $claimForm->save();

        return $result['code'] == 200 ? $this->jsonSuccess($result) : $this->jsonFailed($result['msg'], [], $result['code']);
    }

    /**
     * 下载excel
     * @throws Exception
     */
    public function download()
    {
        set_time_limit(0);
        $search = new SafeguardClaimSearch(customer()->getId());
        /** @var Builder $query */
        $query = $search->search($this->request->query->all(), true);
        $fileName = $search->getDownloadFileName();
        $totalFee = 0;
        $lines = [];
        $xlsCell = [
            ['A', 'claim_id', 'Claim ID', 20],
            ['B', 'link_service_id', 'Linked Service ID', 20],
            ['c', 'pro_service', 'Protection Service', 35],
            ['D', 'sale_order_id', 'Sales Order ID', 28],
            ['E', 'item_code', 'Item Code (Tracking Number)', 60],
            ['F', 'create_time', 'Creation Time', 20],
            ['G', 'claim_status', 'Claim Status', 20],
            ['H', 'claim_amount', 'Claim Amount(' . $this->currency->getSymbolLeft(session('currency')) . ')', 20],
            ['I', 'receive_time', 'Received Time (of Claim Amount)', 35],
        ];
        $configList = app(SafeguardClaimRepository::class)->getClaimedSafeguardConfigs();
        $timeZone = CountryHelper::getTimezone(customer()->getCountryId());
        $query->chunk(SafeguardClaimConfig::CLAIM_DOWNLOAD_NUMBER, function ($claims) use (&$totalFee, &$lines, $fileName, $configList, $timeZone) {
            foreach ($claims as $claim) {
                $totalFee += $claim->claim_amount;
                $tempItemsInfos = '';
                $tempTrackingsInfos = [];
                $count = $claim->claimDetails->count();
                foreach ($claim->claimDetails as $key => $claimDetail) {
                    /** @var SafeguardClaimDetail $claimDetail */
                    $tempItems = $claimDetail->item_code;
                    if ($claimDetail->trackings) {
                        /** @var SafeguardClaimDetailTracking $tracking */
                        $tempTrackingsInfos = array_column($claimDetail->trackings->toArray(), 'tracking_number');
                    }
                    if ($tempTrackingsInfos) {
                        $tempTrackingsInfos = '(' . implode(',', $tempTrackingsInfos) . ')';
                        if (($key + 1) == $count) {
                            $tempItemsInfos .= ($tempItems . $tempTrackingsInfos);
                        } else {
                            $tempItemsInfos .= ($tempItems . $tempTrackingsInfos . PHP_EOL);
                        }
                    } else {
                        if (($key + 1) == $count) {
                            $tempItemsInfos .= $tempItems;
                        } else {
                            $tempItemsInfos .= ($tempItems . PHP_EOL);
                        }
                    }
                }
                $claim->config_title = $configList[$claim->safeguard_config_rid]['title'] ?? SafeguardConfig::query()->where('id', $claim->safeguard_config_id)->value('title');
                $lines[] = [
                    'claim_id' => trim($claim->claim_no),
                    'link_service_id' => $claim->safeguard_no,
                    'pro_service' => trim($claim->config_title),
                    'sale_order_id' => trim($claim->order_id),
                    'item_code' => trim($tempItemsInfos),
                    'create_time' => Carbon::parse($claim->create_time)->timezone($timeZone)->toDateTimeString(),
                    'claim_status' => SafeguardClaimStatus::getDescription($claim->status),
                    'claim_amount' => $claim->status == SafeguardClaimStatus::CLAIM_SUCCEED ? $this->formatPrice($claim->claim_amount) : '0',
                    'receive_time' => $claim->status == SafeguardClaimStatus::CLAIM_SUCCEED ? Carbon::parse($claim->paid_time)->timezone($timeZone)->toDateTimeString() : ''
                ];
            }
        });
        $lines[] = [
            'claim_id' => 'Total',
            'link_service_id' => '',
            'pro_service' => '',
            'sale_order_id' => '',
            'item_code' => '',
            'create_time' => '',
            'claim_status' => '',
            'claim_amount' => $totalFee,
            'receive_time' => '',
        ];
        ExcelHelper::exportExcel($fileName, $xlsCell, $lines);
    }

    //保险业务，上传附件
    public function upload()
    {
        $fileInfo = $this->request->file('attach');
        $menuId = $this->request->get('menuId', 0);
        $allowedExtensions = ['jpeg', 'jpg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'pdf'];

        $fileOriginName = $fileInfo->getClientOriginalName(); // 获取上传的文件名
        $fileOriginName = substr($fileOriginName, 0, -(strlen($fileInfo->getClientOriginalExtension()) + 1)); // 去除文件后缀
        if (preg_match('/[^A-Za-z0-9\-\_\x{4e00}-\x{9fa5}]/u', $fileOriginName)) { // 匹配除 字符 数字 '-' '_' 汉字 之外的字符
            return $this->jsonFailed('The file name can only contain digits, Chinese or English characters, \'-\' and \'_\'.');
        }

        $firstCharacter = mb_substr($fileOriginName, 0,1);
        if (!preg_match('/[A-Za-z0-9\x{4e00}-\x{9fa5}]/u', $firstCharacter)) {
            return $this->jsonFailed('The file name can only start with a Chinese or English character or a digit.');
        }

        if (!in_array($fileInfo->getClientOriginalExtension(), $allowedExtensions)) {
            return $this->jsonFailed('Upload files in .jpeg./jpg/.doc(x)/.xls(x)/.pdf format only.');
        }
        if ($fileInfo->getSize() / 1024 / 1024 > SafeguardClaimConfig::CLAIM_UPLOAD_SIZE) { //10M
            return $this->jsonFailed('Files in the following formats jpeg/png/doc(x)/xls(x)/pdf are compatible (less than 10MB).');
        }

        try {
            $result = RemoteApi::file()->upload(FileResourceTypeEnum::SAFEGUARD, $fileInfo, $menuId > 0 ? $menuId : null);
        } catch (Exception $e) {
            Logger::applyClaim("上传资源失败：menuId={$menuId},异常信息：" . $e->getMessage());
            return $this->jsonFailed('Attachment upload failed, you may contact the customer service.');
        }

        return $this->jsonSuccess($result);
    }

    /**
     * 通用数据
     * @param string $source
     * @return bool|array
     */
    private function _applyBaseInfo(string $source)
    {
        if ($source == 'bill') {
            $billId = (int)$this->request->get('bill_id', 0);
        } else {
            $claimId = (int)$this->request->get('claim_id', 0);
            $claimDetail = SafeguardClaim::query()->find($claimId);
            if (empty($claimDetail) || $claimDetail->buyer_id != customer()->getId()) {
                return false;
            }
            $billId = $claimDetail->safeguard_bill_id;
        }
        /** @var SafeguardBill $billInfo */
        $billInfo = SafeguardBill::query()
            ->where('buyer_id', customer()->getId())
            ->where('id', $billId)
            ->with(['safeguardConfig'])
            ->first();

        if (empty($billInfo) || empty($billInfo->safeguardConfig)) {
            return false;
        }

        //申请理赔单时候,验证保单状态
        if ($source == 'bill') {
            $billStatus = app(SafeguardBillRepository::class)->getSafeguardBillStatus($billInfo->id);
            if ($billStatus != SafeguardBillStatus::ACTIVE) {
                return false;
            }
            //验证销售订单状态
            if (empty($billInfo->salesOrder) || $billInfo->salesOrder->order_status != CustomerSalesOrderStatus::COMPLETED) {
                return false;
            }
        }

        $billInfo->status = app(SafeguardBillRepository::class)->getSafeguardBillStatus($billInfo->id);
        $lastConfigInfo = app(SafeguardConfigRepository::class)->geiNewestConfig($billInfo->safeguard_config_rid,customer()->getCountryId());
        $billInfo->last_config_title = $lastConfigInfo ? $lastConfigInfo->title : $billInfo->safeguardConfig->title;
        $salesOrderInfo = CustomerSalesOrder::query()->find($billInfo->order_id);

        $data['bill_info'] = $billInfo;
        $data['bill_id'] = $billId ?? 0;
        $data['claim_id'] = $claimId ?? 0;
        $data['sales_order_info'] = $salesOrderInfo;
        $data['reasons'] = app(SafeguardClaimRepository::class)->getSafeguardClaimReasons($billInfo->safeguardConfig->config_type);
        $data['sales_platform'] = app(DictionaryRepository::class)->getSalePlatform();
        $data['currency'] = session()->get('currency');
        $data['safeguard_guide_id'] = configDB('safeguard_guide_id');
        $data['is_japan'] = $this->customer->isJapan() ? '1' : '0';
        $data['claim_steps'] = app(SafeguardClaimRepository::class)->getClaimStepInfos($claimDetail ?? null);
        if (customer()->isCollectionFromDomicile()) {
            $data['sales_order_url'] = url('account/customer_order/customerOrderSalesOrderDetails');
        } else {
            $data['sales_order_url'] = url('account/sales_order/sales_order_management/customerOrderSalesOrderDetails');
        }

        return $data;
    }

    //404
    private function _sendErrorPage($url)
    {
        $this->response->setStatusCode(404);
        $data['continue'] = $url;
        $data['text_error'] = $data['heading_title'] = 'The page you requested cannot be found!';

        return $this->render('error/not_found', $data, 'home');
    }

    /**
     * 格式化价格
     * @param $price
     * @return string
     */
    private function formatPrice($price)
    {
        return $this->customer->isJapan()
            ? number_format($price, 0, '.', '')
            : number_format($price, 2, '.', '');
    }
}
