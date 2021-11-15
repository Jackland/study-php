<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Helper\CountryHelper;
use App\Repositories\Customer\CustomerTipRepository;
use App\Repositories\Safeguard\SafeguardAutoBuyPlanRepository;
use App\Repositories\Safeguard\SafeguardClaimRepository;
use App\Repositories\Safeguard\SafeguardConfigRepository;
use App\Repositories\Safeguard\SafeguardBillRepository;
use App\Services\Customer\CustomerTipService;
use App\Catalog\Search\Safeguard\SafeguardBillSearch;
use App\Enums\Safeguard\SafeguardBillStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Catalog\Search\Safeguard\SafeguardClaimSearch;
use App\Models\Safeguard\SafeguardConfig;
use Carbon\Carbon;

/**
 * buyer保单类
 * Class ControllerAccountSafeguardBill
 */
class ControllerAccountSafeguardBill extends AuthBuyerController
{
    private $customerId;
    private $countryId;
    private $currencyCode;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->customerId = intval($this->customer->getId());
        $this->countryId = intval($this->customer->getCountryId());
        $this->currencyCode = $this->session->get('currency');
    }

    //母tab
    public function index()
    {
        $data = [];
        //去掉new的标识
        if (!app(CustomerTipRepository::class)->checkCustomerTipExistsByTypeKey($this->customerId, 'safeguard_new')) {
            app(CustomerTipService::class)->insertCustomerTip($this->customerId, 'safeguard_new');
        }
        $data['isAutoBuyer'] = boolval(Customer()->getCustomerExt(1));
        //即将过期
        $data['isAboutToExpire'] = app(SafeguardAutoBuyPlanRepository::class)->isAboutToExpireByDays((int)$this->customerId);
        $statisticsInfo = (new SafeguardClaimSearch(customer()->getId()))->getStatisticsNumber();
        $data['claim_number'] = (int)($statisticsInfo['success_number'] + $statisticsInfo['fail_number'] + $statisticsInfo['backed_number']);
        $data['safeguard_guide_id'] = configDB('safeguard_guide_id');

        //组织baseUrl
        $data['bill_base_url'] = 'account/safeguard/bill/list';
        $data['claim_base_url'] = 'account/safeguard/claim/list';
        if ($this->request->get('filter_no', '')) {
            $data['bill_base_url'] .= '&filter_no=' . $this->request->get('filter_no', '') . '&filter_create_time_range=anytime';
        } elseif ($this->request->get('filter_keywords', '')) {
            $data['claim_base_url'] .= '&filter_keywords=' . $this->request->get('filter_keywords', '') . '&filter_create_time_range_claim=anytime';
        }

        return $this->render('account/safeguard/index', $data, 'buyer');
    }

    //保单列表
    public function list()
    {
        $data = [];
        //用户已有的保单的保障服务
        $alreadySafeguard = app(SafeguardConfigRepository::class)->getOneselfAlreadySafeguard($this->customerId, $this->countryId);
        // 获取数据
        $search = new SafeguardBillSearch($this->customerId);
        $dataProvider = $search->search($this->request->query->all());
        $list = $dataProvider->getList();
        $orderCanApplyArr = [];
        foreach ($list as &$val) {
            if ($val->status == SafeguardBillStatus::ACTIVE && Carbon::now()->getTimestamp() > Carbon::parse($val->expiration_time)->getTimestamp()) { //已失效
                $val->status = SafeguardBillStatus::INVALID;
            }
            $val->safeguard_fee = $this->currency->format($val->safeguard_fee, $this->currencyCode);
            $val->title = $alreadySafeguard[$val->safeguard_config_rid]['title'] ?? SafeguardConfig::query()->where('id', $val->safeguard_config_id)->value('title');

            $val->canApply = false;//是否可以申请理赔
            $val->noApplyMsg = false;//不能申请的文案
            //可申请理赔的保单状态
            if (in_array($val->status, SafeguardBillStatus::canApplyClaimInStatus())) {
                //申请理赔按钮禁用1：已理赔数量等于或者大于99
                if (count($val->safeguardClaim) >= 99) {
                    $val->noApplyMsg = 'You cannot submit a claim application for this Protection Service any more since the maximum limit of claims allowed for the Protection Service has been reached.';
                    continue;
                }
                //申请理赔按钮禁用2：销售单非complete
                if (in_array($val->status, SafeguardBillStatus::canApplyClaimInStatus()) && $val->order_status != CustomerSalesOrderStatus::COMPLETED) {
                    $val->noApplyMsg = 'Claim application is not available for the sales order under the status of ' . CustomerSalesOrderStatus::getDescription($val->order_status) . '.';
                    continue;
                }

                //剩余可理赔的数量
                if (!isset($orderCanApplyArr[$val->order_id])) {
                    $orderCanApplyArr[$val->order_id] = false;
                    $SalesOrderLineSumClaimNumber = app(SafeguardClaimRepository::class)->getSalesOrderLineSumClaimNumber($val->order_id);
                    if ($SalesOrderLineSumClaimNumber->isEmpty()) {//没有理赔过
                        $orderCanApplyArr[$val->order_id] = true;
                    } else { //理赔过
                        foreach ($SalesOrderLineSumClaimNumber as $v) {
                            if ($v->qty > $v->claim_qty) {
                                $orderCanApplyArr[$val->order_id] = true;
                            }
                        }
                    }
                }
                //申请理赔按钮禁用3：该销售单没有可理赔的商品
                if ($orderCanApplyArr[$val->order_id] == false) {
                    $val->noApplyMsg = 'No items in this sales order are available for claim application. For any questions, please contact the Marketplace Customer Service.';
                    continue;
                }
                $val->canApply = true;
            }
        }
        $data['total'] = $dataProvider->getTotalCount(); // 总计
        $data['list'] = $list;  // 列表
        $data['paginator'] = $dataProvider->getPaginator();
        $data['sort'] = $dataProvider->getSort();
        $data['search'] = $search->getSearchData();
        //tab对应的数量
        $data['tabTotals'] = $search->getBillStatusCount($this->request->query->all());
        $data['status'] = SafeguardBillStatus::getViewItems();
        $data['alreadySafeguard'] = $alreadySafeguard;
        $data['is_collect_form_domicile'] = $this->customer->isCollectionFromDomicile();
        return $this->render('account/safeguard/bill/index', $data);
    }

    //下载
    public function download()
    {
        set_time_limit(0);
        // 获取数据
        $search = new SafeguardBillSearch($this->customerId);
        //下载全部，与当前tab没有关系
        $this->request->query->set('filter_tab', '');
        $dataProvider = $search->search($this->request->query->all(), true);
        //拼接creation 时间
        $creation = $this->request->query->get('filter_creation_date_from') ? date('_Ymd', strtotime($this->request->query->get('filter_creation_date_from'))) : '';
        $creation .= $this->request->query->get('filter_creation_date_to') ? date('_Ymd', strtotime($this->request->query->get('filter_creation_date_to'))) : '';
        $fileName = 'Protection Service' . $creation . '.csv';
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $fp = fopen('php://output', 'a');
        //在写入的第一个字符串开头加 bom
        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        fwrite($fp, $bom);
        fputcsv($fp, [
            'Service ID',
            'Sales Order ID',
            'Protection Service',
            'Protection Service Fee(' . $this->currency->getSymbolLeft($this->currencyCode) . $this->currency->getSymbolRight($this->currencyCode) . ')',
            'Creation Time',
            'Starts',
            'Expires',
            'Protection Service Status',
            'Claim Application'
        ]);
        //用户已有的保单的保障服务
        $alreadySafeguard = app(SafeguardConfigRepository::class)->getOneselfAlreadySafeguard($this->customerId, $this->countryId);
        $totalFee = 0;
        foreach ($dataProvider->getList() as $item) {
            $item->title = $alreadySafeguard[$item->safeguard_config_rid]['title'] ?? SafeguardConfig::query()->where('id', $item->safeguard_config_id)->value('title');
            $totalFee = $totalFee + $item->safeguard_fee;
            $claimNo = '';
            foreach ($item->safeguardClaim as $cVal) {
                $claimNo = $claimNo . $cVal->claim_no . PHP_EOL;
            }
            $effective_time = $item->effective_time ? Carbon::parse($item->effective_time)->timezone(CountryHelper::getTimezone(customer()->getCountryId()))->toDateTimeString() : '--';
            $expiration_time = $item->expiration_time ? Carbon::parse($item->expiration_time)->timezone(CountryHelper::getTimezone(customer()->getCountryId()))->toDateTimeString() : '--';
            $line = [
                "\t" . $item->safeguard_no . "\t",
                "\t" . $item->sales_order_no . "\t",
                "\t" . $item->title . "\t",
                "\t" . $this->countryId == JAPAN_COUNTRY_ID ? (int)$item->safeguard_fee : $item->safeguard_fee . "\t",
                "\t" . Carbon::parse($item->create_time)->timezone(CountryHelper::getTimezone(customer()->getCountryId()))->toDateTimeString() . "\t",
                "\t" . $effective_time . "\t",
                "\t" . $expiration_time . "\t",
                SafeguardBillStatus::getDescription(app(SafeguardBillRepository::class)->getSafeguardBillStatus($item->id)),
                $claimNo,
            ];
            fputcsv($fp, $line);
        }
        if ($dataProvider->getTotalCount() > 0) {
            fputcsv($fp, ['Total', '', '', "\t" . $this->countryId == JAPAN_COUNTRY_ID ? (int)$totalFee : $totalFee . "\t", '', '', '', '', '']);
        } else {
            fputcsv($fp, ['No Records.']);
        }
        stream_get_contents($fp);
        fclose($fp);
    }
}
