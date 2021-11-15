<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Catalog\Search\Margin\MarginAgreementSettlementSearch;
use App\Catalog\Search\Margin\MarginSellerAgreementSearch;
use App\Components\Storage\StorageCloud;
use App\Enums\Buyer\BuyerType;
use App\Enums\Margin\MarginPerformerApplyStatus;
use App\Enums\Order\OcOrderStatus;
use App\Models\Customer\Customer;
use App\Models\Margin\MarginAgreement;
use App\Models\Margin\MarginAgreementStatus as MarginAgreementStatusModel;
use App\Models\Margin\MarginPerformerApply;
use App\Models\Order\Order;
use App\Models\Order\OrderHistory;
use App\Repositories\Buyer\BuyerRepository;
use App\Repositories\Margin\AgreementRepository as MarginAgreementRepository;
use App\Repositories\Margin\MarginRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Rma\RamRepository;
use App\Repositories\Seller\SellerRepository;
use App\Services\Margin\MarginService;
use App\Enums\Margin\MarginAgreementStatus;
use App\Enums\Margin\MarginAgreementLogType;
use App\Widgets\VATToolTipWidget;

/**
 * Class ControllerAccountProductQuotesMarginContract
 * @version 现货保证金三期
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountCustomerpartnerMargin $model_account_customerpartner_margin
 * @property ModelAccountOrder $model_account_order
 * @property ModelAccountProductQuotesMargin $model_account_product_quotes_margin
 * @property ModelAccountProductQuotesMarginContract $model_account_product_quotes_margin_contract
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCommonProduct $model_common_product
 * @property ModelMessageMessage $model_message_message
 * @property ModelMpLocalisationOrderStatus $model_mp_localisation_order_status
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountProductQuotesMarginContract extends AuthSellerController
{
    const COUNTRY_JAPAN = 107;

    public $precision;
    public $symbol;

    /**
     * ControllerAccountProductQuotesMarginContract constructor.
     * @param Registry $registry
     * @throws Exception
     */
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        //默认加载的language和model
        $this->language->load('account/product_quotes/margin_contract');
        $this->language->load('common/cwf');
        $this->load->model('account/product_quotes/margin_contract');
        $this->load->model('common/product');

        //初始化添加样式
        $this->document->addStyle('catalog/view/javascript/product/element-ui.css');
        $this->document->addScript('catalog/view/javascript/product/element-ui.js');
         //用户画像
        $this->document->addStyle('catalog/view/javascript/layer/theme/default/layer.css');
        $this->document->addScript('catalog/view/javascript/layer/layer.js');
        $this->document->addStyle('catalog/view/javascript/user_portrait/user_portrait.css?v=' . APP_VERSION);
        $this->document->addScript('catalog/view/javascript/user_portrait/user_portrait.js?v=' . APP_VERSION);
        //处理类变量
        $this->precision =$this->currency->getDecimalPlace($this->session->data['currency']);
        $this->symbol = $this->currency->getSymbolLeft($this->session->data['currency']);
        if (empty($this->symbol)) {
            $this->symbol = $this->currency->getSymbolRight($this->session->data['currency']);
        }
        if (empty($this->symbol)) {
            $this->symbol = '$';
        }
    }

    public function page_data(){
        //页面框架数据
        $page_data=array();
        $page_data['column_left'] = $this->load->controller('common/column_left');
        $page_data['column_right'] = $this->load->controller('common/column_right');
        $page_data['content_top'] = $this->load->controller('common/content_top');
        $page_data['content_bottom'] = $this->load->controller('common/content_bottom');
        $page_data['footer'] = $this->load->controller('common/footer');
        $page_data['header'] = $this->load->controller('common/header');
        $page_data['separate_view'] = false;
        $page_data['separate_column_left'] = '';
        if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
            $page_data['separate_view'] = true;
            $page_data['column_left'] = '';
            $page_data['column_right'] = '';
            $page_data['content_top'] = '';
            $page_data['content_bottom'] = '';
            $page_data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $page_data['footer'] = $this->load->controller('account/customerpartner/footer');
            $page_data['header'] = $this->load->controller('account/customerpartner/header');
        }
        return $page_data;
    }

    /**
     * 现货协议列表
     * @return string
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function index()
    {
        $sellerId = customer()->getId();

        $search = new MarginSellerAgreementSearch($sellerId);
        $statAgreementIds = $search->getStatAgreementIds(request()->query->all());

        // 处理统计的查询
        $agreementIds = [];
        if (request('filter_margin_hot_map', 'all') != 'all' ) {
            $agreementIds = $statAgreementIds[request('filter_margin_hot_map')] ?? [];
            $agreementIds = $agreementIds ?: [0];
        }

        $dataProvider = $search->search($this->request->query->all(), $agreementIds);
        $agreements = $dataProvider->getList();

        $productIdInfoMap = [];
        if ($agreements->isNotEmpty()) {
            $productIds = $agreements->pluck('product_id')->filter()->unique()->toArray();
            $productIdInfoMap = app(ProductRepository::class)->getProductsMapIncludeTagsByIds($productIds);
        }

        //保证金店铺
        $bxStore=$this->config->get('config_customer_group_ignore_check');
        $restSellerNum = []; //获取新协议的售卖数量
        if (!in_array($sellerId, $bxStore) && $agreements->isNotEmpty()) {
            $restSellerArr = [];
            $restProductIdAgreementIdMap = $agreements->pluck('process.rest_product_id', 'id')->filter()->toArray();
            foreach ($restProductIdAgreementIdMap as $id => $productId) {
                $restSellerArr[] = array(
                    'product_id' => $productId,
                    'agreement_id' => $id
                );
            }
            $restSellerNum = $restSellerArr ? $this->model_account_product_quotes_margin_contract->get_rest_product_sell_num($restSellerArr) : 0;
        }

        $marginAgreementRepository = app(MarginAgreementRepository::class);
        /** @var ModelAccountProductQuotesMargin $modelAccountProductQuotesMargin */
        $modelAccountProductQuotesMargin = load()->model('account/product_quotes/margin');
        foreach ($agreements as $agreement) {
            /** @var MarginAgreement $agreement */
            //已经售卖的量=已经售卖的-RMA的量
            if (!empty($agreement->process->rest_product_id) && !in_array($sellerId, $bxStore)) {   //新协议
                $soldQty = $restSellerNum[$agreement->id][$agreement->process->rest_product_id] ?? 0;
            } else {
                $restOrderIds = (!empty($agreement->process) && $agreement->process->relateOrders->isNotEmpty()) ? $agreement->process->relateOrders->pluck('rest_order_id')->filter()->unique()->toArray() : [];

                $rmaQty = 0;
                foreach ($restOrderIds as $restOrderId) {
                    if ($restOrderId && !empty($agreement->process->rest_product_id)) {
                        $qty = $modelAccountProductQuotesMargin->getRmaQtyPurchaseAndSales($restOrderId, $agreement->process->rest_product_id);
                        $rmaQty += $qty;
                    }
                }
                $purchaseQuantity = (!empty($agreement->process) && $agreement->process->relateOrders->isNotEmpty()) ? $agreement->process->relateOrders->sum('purchase_quantity'): 0;
                $soldQty = intval($purchaseQuantity) - intval($rmaQty);
            }

            $isCanPerformerAudit = $marginAgreementRepository->isCanPerformerAudit($agreement, $sellerId);

            $agreement->product_info = $productIdInfoMap[$agreement->product_id] ?? [];
            $agreement->is_home_pickup = in_array($agreement->customer_group_id, COLLECTION_FROM_DOMICILE);
            $agreement->sell_qty = $soldQty;
            $agreement->days_left = $marginAgreementRepository->getDaysLeftWarning($agreement);
            $agreement->count_down = $marginAgreementRepository->getAgreementCountDown($agreement);
            $agreement->is_can_performer_audit = $isCanPerformerAudit['ret'];
            $agreement->performer_apply_id = $isCanPerformerAudit['ret'] ? $isCanPerformerAudit['performerId'] : 0;
            $agreement->ex_vat = VATToolTipWidget::widget(['customer' => $agreement->buyer, 'is_show_vat' => true])->render();
        }

        $data['agreements'] = $agreements;
        $data['marginStatusList']  = MarginAgreementStatusModel::query()->orderBy('sort')->get();
        $data['total'] = $dataProvider->getTotalCount();
        $data['contracts'] = $agreements;
        $data['paginator'] = $dataProvider->getPaginator();
        $data['sort'] = $dataProvider->getSort();
        $data['search'] = $search->getSearchData();
        $data['statAgreementIds'] = $statAgreementIds;
        $data['country'] = session('country');

        $data['stat_total'] = array_sum(array_map('count', $search->getStatAgreementIds([], false)));

        return $this->render('customerpartner/margin/agreements', $data);
    }

    /**
     * 下载
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function margin_download()
    {
        $sellerId = customer()->getId();

        $search = new MarginSellerAgreementSearch($sellerId);
        $statAgreementIds = $search->getStatAgreementIds(request()->query->all());

        // 处理统计的查询
        $agreementIds = [];
        if (request('filter_margin_hot_map', 'all') != 'all' ) {
            $agreementIds = $statAgreementIds[request('filter_margin_hot_map')] ?? [];
            $agreementIds = $agreementIds ?: [0];
        }

        $dataProvider = $search->search($this->request->query->all(), $agreementIds, true);
        $agreements = $dataProvider->getList();

        $skuByProductIds = [];
        if ($agreements->isNotEmpty()) {
            $productIds = $agreements->pluck('product_id')->filter()->unique()->toArray();
            $skuByProductIds = app(ProductRepository::class)->getSkuByProductIds($productIds);
        }

        //保证金店铺
        $bxStore=$this->config->get('config_customer_group_ignore_check');
        $restSellerNum = []; //获取新协议的售卖数量
        if (!in_array($sellerId, $bxStore) && $agreements->isNotEmpty()) {
            $restSellerArr = [];
            $restProductIdAgreementIdMap = $agreements->pluck('process.rest_product_id', 'id')->filter()->toArray();
            foreach ($restProductIdAgreementIdMap as $id => $productId) {
                $restSellerArr[] = array(
                    'product_id' => $productId,
                    'agreement_id' => $id
                );
            }
            $restSellerNum = $restSellerArr ? $this->model_account_product_quotes_margin_contract->get_rest_product_sell_num($restSellerArr) : 0;
        }

        $head=array(
            'Agreement ID',
            'Name',
            'Buyer Code',
            'Item Code',
            'Days of Agreement',
            'Agreement Unit Price',
            'Agreement QTY',
            'Current Completed Quantity',//'现货保证金协议完成数量',
            'Agreement Margin',//'现货保证金协议定金金额',
            'Deposit Order ID',//'定金订单号',
            'Agreement Final Payment Unit Price',//'现货保证金尾款单价',
            'Agreement Amount',//'现货保证金协议金额',
            'Time of Effect',
            'Time of Failure',
            'Last Modified',
            'Status'
        );

        $data = [];
        /** @var ModelAccountProductQuotesMargin $modelAccountProductQuotesMargin */
        $modelAccountProductQuotesMargin = load()->model('account/product_quotes/margin');
        $currency = session('currency', 'USD');
        foreach ($agreements as $agreement) {
            /** @var MarginAgreement $agreement */
            $statusFlag=false;
            if(in_array($agreement->status, MarginAgreementStatus::afterSoldStatus())){
                $statusFlag=true;
            }

            //已经售卖的量=已经售卖的-RMA的量
            if (!empty($agreement->process->rest_product_id) && !in_array($sellerId, $bxStore)) {   //新协议
                $soldQty = $restSellerNum[$agreement->id][$agreement->process->rest_product_id] ?? 0;
            } else {
                $restOrderIds = (!empty($agreement->process) && $agreement->process->relateOrders->isNotEmpty()) ? $agreement->process->relateOrders->pluck('rest_order_id')->filter()->unique()->toArray() : [];

                $rmaQty = 0;
                foreach ($restOrderIds as $restOrderId) {
                    if ($restOrderId && !empty($agreement->process->rest_product_id)) {
                        $qty = $modelAccountProductQuotesMargin->getRmaQtyPurchaseAndSales($restOrderId, $agreement->process->rest_product_id);
                        $rmaQty += $qty;
                    }
                }
                $purchaseQuantity = (!empty($agreement->process) && $agreement->process->relateOrders->isNotEmpty()) ? $agreement->process->relateOrders->sum('purchase_quantity'): 0;
                $soldQty = intval($purchaseQuantity) - intval($rmaQty);
            }

            $productDiscountPer= round($agreement->price *$agreement->payment_ratio/100, $this->precision);
            $data[] = [
                "\t" . $agreement->agreement_id,
                $agreement->nickname,
                $agreement->user_number,
                $skuByProductIds[$agreement->product_id] ?? '',
                $agreement->day,
                $this->currency->format($agreement->price, $currency),
                $agreement->num,
                !$statusFlag ? 'N/A' : ($soldQty),
                $this->currency->format($productDiscountPer * $agreement->num, $currency),
                !$statusFlag ? 'N/A' : $agreement->process->advance_order_id,
                $this->currency->format($agreement->price - $productDiscountPer, $currency),
                $this->currency->format($agreement->price * $agreement->num, $currency),
                !$statusFlag ? 'N/A' : $agreement->effect_time,
                !$statusFlag ? 'N/A' : $agreement->expire_time,
                $agreement->update_time,
                $agreement->marginStatus->name,
            ];
        }

        $fileName='Marginbids_'.date('Ymd',time()).'.csv';
        outputCsv($fileName,$head,$data,$this->session);
    }

    /**
     * 协议详情页面(区分新旧的页面)
     * @return string
     */
    public function view()
    {
        $agreementNo = request('agreement_id');

        $agreement = MarginAgreement::query()
            ->where('agreement_id', $agreementNo)
            ->where('seller_id', customer()->getId())
            ->first();
        if (!empty($agreement) && $agreement->status == MarginAgreementStatus::APPLIED) {
            $this->update($agreementNo, 2, $agreement->update_time);
        }

        // 新的协议跳转到新的页面
        if (!empty($agreement) && $agreement->program_code == MarginAgreement::PROGRAM_CODE_V4) {
            $data['id'] = $agreement->id;
            $performerAppliedInfo = app(MarginRepository::class)->getPerformerApply($agreement->id, true);
            $data['exist_performer'] = !empty($performerAppliedInfo);
            $data['advance_order_id'] = (!empty($agreement->process) && $agreement->process->process_status >= 2) ? $agreement->process->advance_order_id : 0;
            return $this->render('customerpartner/margin/agreement_detail', $data, 'seller');
        }

        $this->document->setTitle($this->language->get('margin_detail_title'));

        $data = array();

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home'),
            'separator' => false
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_seller_center'),
            'href' => 'javascript:void(0);',
            'separator' => $this->language->get('text_separator')
        );
        $data['breadcrumbs'][] = array(
            'text' => 'Product Bidding',
            'href' => 'javascript:void(0)',
            'separator' => $this->language->get('text_separator')
        );
        $data['breadcrumbs'][] = array(
            'text' =>  'Complex Transactions List',
            'href' => $this->url->link('account/customerpartner/wk_quotes_admin&tab=margin', '', true),
            'separator' => $this->language->get('text_separator')
        );
        $data['breadcrumbs'][] = array(
            'text' => 'Margin Bids',
            'href' => $this->url->link('account/product_quotes/margin_contract/view&agreement_id=' . $agreementNo),
            'separator' => $this->language->get('text_separator')
        );
        //页面框架
        $data=array_merge($data,$this->page_data());

        //其他数据
        $data['showAgreement'] = url()->to(['account/product_quotes/margin_contract/showAgreement', 'agreement_id' => $agreementNo]);
        $data['showVoucher'] = url()->to(['account/product_quotes/margin_contract/showVoucher', 'agreement_id' => $agreementNo]);
        $data['showCheck'] = url()->to(['account/product_quotes/margin_contract/showCheck', 'agreement_id' => $agreementNo]);

        $data['refresh_action'] = url()->to(['account/product_quotes/margin_contract/view', 'agreement_id' => $agreementNo]);
        $data['send_message_action'] = url()->to(['account/product_quotes/margin_contract/addMessage']);


        $data['contract_id'] = $agreementNo;

        $this->response->setOutput($this->load->view('account/customerpartner/list_margin_detail', $data));
    }

    /**
     * 同意bid
     * @param null $agreement_id
     * @param null $last_update_time
     * @return array
     * @throws Exception
     */
    public function approve($agreement_id = null,$last_update_time = null)
    {
        $showJson = true;
        if (!isset($agreement_id)) {
            $showJson = false;
            $agreement_id = $this->request->post['agreementId'];
            $last_update_time = $this->request->post['last_update_time'];
        }

        $json = array();
        //检查数量是否充足
        $agreement_detail = $this->model_account_product_quotes_margin_contract->getMarginAgreementDetail($agreement_id, $this->customer->getId());
        $this->log->write('保证金审批: 协议id:'.$agreement_id.';'.'，customer_id：'.$this->customer->getId().'；结果:'.json_encode($agreement_detail));
        // 获取协议产品当前可用数量
        $agreement_product_available_qty = $this->model_common_product->getProductAvailableQuantity(
            (int)$agreement_detail['product_id']
        );
        if ($agreement_product_available_qty < $agreement_detail['num']) {
            $json['error'] = $this->language->get("error_no_stock");
        }
        if($agreement_detail['update_time'] != $last_update_time){
            $json['error'] = $this->language->get("error_date_updated");
        }

        if (!isset($json['error'])) {
            $approve_ret = $this->model_account_product_quotes_margin_contract->approveMargin($agreement_id);
            if($approve_ret){
                //现货四期，记录协议状态变更
                app(MarginService::class)->insertMarginAgreementLog([
                    'from_status' => MarginAgreementStatus::PENDING,
                    'to_status' => MarginAgreementStatus::APPROVED,
                    'agreement_id' => $agreement_detail['id'],
                    'log_type' => MarginAgreementLogType::PENDING_TO_APPROVED,
                    'operator' => customer()->getNickName(),
                    'customer_id' => customer()->getId(),
                ]);

                $json['success'] = $this->language->get('text_approve_success');
                $this->sendApproveNotificationToBuyer($agreement_detail['id']);
            }else{
                $json['error'] = $this->language->get('text_approve_error');
            }
        }

        if ($showJson) {
            return $json;
        } else {
            $this->response->setOutput(json_encode($json));
        }
    }

    public function update($agreement_id = null, $status = null,$last_update_time = null)
    {
        $showJson = true;
        if (!isset($agreement_id)) {
            $showJson = false;
            $agreement_id = $this->request->post['agreementId'];
            $last_update_time = $this->request->post['last_update_time'];
        }
        if (!isset($status)) {
            $status = $this->request->post['contract_status'];
        }
        if (!$this->customer->isLogged() || !isset($agreement_id) || !isset($status)) {
            session()->set('redirect', $this->url->link('account/product_quotes/margin_contract', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $customer_id = $this->customer->getId();

        $this->load->model('account/product_quotes/margin_contract');
        $this->load->language('account/product_quotes/margin_contract');

        $json = array();

        $agreement_detail = $this->model_account_product_quotes_margin_contract->getMarginAgreementDetail($agreement_id, $this->customer->getId());
        if($agreement_detail['update_time'] != $last_update_time){
            $json['error'] = $this->language->get("error_date_updated");
        }
        if (!isset($json['error'])) {
            $this->model_account_product_quotes_margin_contract->updateMarginContractStatus($customer_id, $agreement_id, $status);
            $json['success'] = $this->language->get('text_update_success');
            //现货四期，记录协议状态变更  老协议也记一下吧，但是可能会出现数据记录不全
            if ($status === 2) {
                app(MarginService::class)->insertMarginAgreementLog([
                    'from_status' => MarginAgreementStatus::APPLIED,
                    'to_status' => MarginAgreementStatus::PENDING,
                    'agreement_id' => $agreement_detail['id'],
                    'log_type' => MarginAgreementLogType::APPLIED_TO_PENDING,
                    'operator' => customer()->getNickName(),
                    'customer_id' => customer()->getId(),
                ]);
            }

            //拒绝协议需要发送站内信
            if($status == '4'){
                $this->sendApproveNotificationToBuyer($agreement_detail['id']);

                //现货四期，记录协议状态变更  老协议也记一下吧，但是可能会出现数据记录不全
                app(MarginService::class)->insertMarginAgreementLog([
                    'from_status' => MarginAgreementStatus::PENDING,
                    'to_status' => MarginAgreementStatus::REJECTED,
                    'agreement_id' => $agreement_detail['id'],
                    'log_type' => MarginAgreementLogType::PENDING_TO_APPROVED,
                    'operator' => customer()->getNickName(),
                    'customer_id' => customer()->getId(),
                ]);
            }

        }

        if ($showJson) {
            return $json;
        } else {
            $this->response->setOutput(json_encode($json));
        }
    }

    /**
     * 旧的协议明细页面 #18273需求后的协议跳转到新的明细页面
     * @throws Exception
     */
    public function showAgreement()
    {
        $this->load->language('account/product_quotes/margin_contract');

        $agreement_id = $this->request->get['agreement_id'];

        $data = array();
        $customer_id = $this->customer->getId();
        $data['customer_id'] = $customer_id;
        $agreement_detail = $this->model_account_product_quotes_margin_contract->getMarginAgreementDetail($agreement_id, $customer_id);

        if (isset($agreement_detail['id'])) {
            //现货三期--获取共同履约人
            $buyer_list = $this->model_account_product_quotes_margin_contract->get_common_performer($agreement_detail['id']);
            //一件代发、上门取货
            foreach ($buyer_list as $k=>$v){
                $buyer_list[$k]['is_home_pickup'] = in_array($v['customer_group_id'],COLLECTION_FROM_DOMICILE);
            }
            //对数据进行分组，防止以后出现多个履约人
            $buyer[1]=array_filter($buyer_list,function ($arr){
                if($arr['is_signed']){
                    return $arr;
                }
            });
            $buyer[0]=array_filter($buyer_list,function ($arr){
                if(!$arr['is_signed']){
                    return $arr;
                }
            });
            $agreement_detail['buyer_list']=$buyer;
            if(empty($buyer[0])){
                //查询是否有在申请中的共同履约人
                $agreement_detail['performer_apply'] = $this->model_account_product_quotes_margin_contract->getPerformerApply($agreement_detail['id'],
                                                                                                                              true);
                if (!empty($agreement_detail['performer_apply'])) {
                    $agreement_detail['performer_apply']->is_home_pickup = in_array($agreement_detail['performer_apply']->customer_group_id,
                                                                                      COLLECTION_FROM_DOMICILE);
                }
            }
            //获取模板数据
            $this->load->model('account/customerpartner/margin');
            $template = $this->model_account_customerpartner_margin->getProductTemplate($customer_id, $agreement_detail['product_id']);
            //处理协议中展示的颜色
            $qty_color='red';
            $other_color='red';
            foreach ($template['template_list'] ?? [] as $k =>$v){
                if($agreement_detail['num']>=$v['min_num']&&$agreement_detail['num']<=$v['max_num']){   //存在区间
                    $qty_color='green';
                    if($agreement_detail['unit_price']==$v['price']){
                        $other_color='green';
                    }
                    break;
                }
            }
            $agreement_detail['qty_color']=$qty_color;
            $agreement_detail['other_color']=$other_color;
            $agreement_detail['unit_price_origin'] = $agreement_detail['unit_price'];
            $agreement_detail['unit_price'] = $this->currency->formatCurrencyPrice($agreement_detail['unit_price'], $this->session->data['currency']);
            $agreement_detail['sum_price'] = $this->currency->formatCurrencyPrice($agreement_detail['sum_price'], $this->session->data['currency']);
            //转换时区
//            $agreement_detail['effect_time']=changeOutPutByZone($agreement_detail['effect_time'],$this->session);
//            $agreement_detail['expire_time']=changeOutPutByZone($agreement_detail['expire_time'],$this->session);
            $data['agreement_detail'] = $agreement_detail;
            $data['agreement_detail']['is_home_pickup'] = in_array($agreement_detail['customer_group_id'],COLLECTION_FROM_DOMICILE);
            $need_alarm = 0;
            if (
                $this->customer->isNonInnerAccount()
                && !in_array($agreement_detail['customer_group_id'], COLLECTION_FROM_DOMICILE)
            ) {
                $alarm_price = $this->model_common_product->getAlarmPrice((int)$agreement_detail['product_id']);
                if (bccomp($agreement_detail['unit_price_origin'], $alarm_price, 4) === -1) {
                    $need_alarm = 1;
                }
            }
            $data['need_alarm'] = $need_alarm;
            $messages = $this->model_account_product_quotes_margin_contract->getMarginMessage($agreement_detail['id']);
            if (!empty($messages)) {
                $first_message = current($messages);
                $data['first_message'] = $first_message['message'];
                array_shift($messages);
                foreach ($messages as $key => $msg) {
                    $messages[$key]['is_collection_from_domicile'] = in_array($msg['customer_group_id'],COLLECTION_FROM_DOMICILE);
                    if($msg['writer_id']==0){
                        $messages[$key]['name'] = 'Marketplace';
                    } elseif ($msg['writer_id'] == -1) {
                        $messages[$key]['name'] = 'System';
                    } elseif ($customer_id == $msg['writer_id']) {
                        $messages[$key]['name'] = 'Me';
                    } elseif ($msg['is_partner']) {
                        $messages[$key]['name'] = $msg['screenname'];
                    } else {
                        $messages[$key]['name'] = $msg['nickname'].'('.$msg['user_number'].')';
                    }
                }
                $data['messages'] = $messages;
            }
            $data['margin_tpl']=$this->url->link('account/customerpartner/margin&expand_status=expand&sku_mpn='.$agreement_detail['sku']);
        }
        $data['send_message_action'] = $this->url->link('account/product_quotes/margin_contract/addMessage');
        $data['refresh_action'] = $this->url->link('account/product_quotes/margin_contract/view&agreement_id=' . $agreement_id);
        $data['go_back'] = $this->url->link('account/customerpartner/wk_quotes_admin');


        $get_pic=$this->model_account_product_quotes_margin_contract->get_avatar($this->customer->getId());
        if($get_pic){
            $this->load->model('tool/image');
            $data['self_store_pic'] = $this->model_tool_image->resize($get_pic,40,40);
        }else{
            $data['self_store_pic'] = '/image/catalog/Logo/yzc_logo_45x45.png';
        }
        // 是否显示云送仓提醒
        $data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();

        $this->response->setOutput($this->load->view('account/customerpartner/tab_margin_detail_agreement', $data));
    }

    /**
     * 旧的协议保证金页面 #18273需求后的协议跳转到新的保证金页面
     * @throws Exception
     */
    public function showVoucher()
    {
        $agreement_id = $this->request->get['agreement_id'];
        if (!$this->customer->isLogged() || !isset($agreement_id)) {
            session()->set('redirect', $this->url->link('account/product_quotes/margin_contract', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $data = array();
        $this->load->model('tool/image');
        $this->load->model('account/product_quotes/margin_contract');
        $this->load->language('account/product_quotes/margin_contract');

        $margin_process = $this->model_account_product_quotes_margin_contract->getMarginProcessDetail($agreement_id);
        if (!$margin_process || $margin_process['seller_id'] != customer()->getId()) {
            return $this->render('account/customerpartner/tab_margin_detail_voucher', $data);
        }
        $process_product = array();
        //现货保证金三期 需要兼容
//        if(isset($margin_process['seller_id'])){
//            $order_seller_id = $this->model_account_product_quotes_margin_contract->getMarginServiceStoreId($margin_process['seller_id']);
//        }
//        //获取seller id  兼容旧数据，如果在服务店铺，则使用服务店铺的seller id   ，否则使用当前店铺的seller id
//        if(isset($order_seller_id['seller_id'])){
//            $get_order_seller_id=$order_seller_id['seller_id'];
//        }else{
//            $get_order_seller_id=$margin_process['seller_id'];
//        }
        $get_order_seller_id=$this->model_account_product_quotes_margin_contract->get_product_seller_id((int)($margin_process['advance_product_id'] ?? 0));
        $get_order_seller_id=$get_order_seller_id['customer_id'];
        if(isset($margin_process['advance_product_id'])){
            $process_product[] = $margin_process['advance_product_id'];
        }
        if(isset($margin_process['rest_product_id'])){
            $process_product[] = $margin_process['rest_product_id'];
        }
        if (isset($margin_process['advance_order_id'])) {
            $order_id = $margin_process['advance_order_id'];
            $this->load->model('account/order');
            $this->load->model('account/customerpartner');
            $order_info = $this->model_account_customerpartner->getOrder($order_id,$get_order_seller_id);
            if (isset($order_info) && !empty($order_info)) {
                $data['order_id'] = $order_id;
                $data['order_id_link'] = $this->url->link('account/customerpartner/orderinfo', '&order_id=' . $order_id, true);
                $data['date_added'] = date($this->language->get('datetime_format'), strtotime($order_info['date_added']));
                $data['buyer_id'] = $order_info['buyer_id'];
                $data['nickname'] = $order_info['nickname'];
                $data['order_status_name'] = $order_info['order_status_name'];
                $data['is_home_pickup'] = in_array($order_info['customer_group_id'],COLLECTION_FROM_DOMICILE);
                $data['isAmerican'] = $this->customer->getCountryId() == 223 ? true : false;
                $data['isEurope'] = in_array($this->customer->getCountryId(), [81, 222]) ? true : false;
                $data['img_vat_url'] = ($this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url'))
                    . 'image/product/vat.png';
                $this->load->model('catalog/product');
                $products = $this->model_account_customerpartner->getSellerOrderProductInfo_head($order_id, $get_order_seller_id);

                $order_product = array();
                foreach ($products as $product) {
                    if (!in_array($product['product_id'], $process_product)) {
                        continue;
                    }
                    /**
                     * @var float $quote_price
                     * @var float $service_fee
                     */
                    $quote_price = 0;
                    $service_fee = 0;

                    $tag_array = $this->model_catalog_product->getProductSpecificTag($product['product_id']);
                    $tags = array();
                    if (isset($tag_array)) {
                        foreach ($tag_array as $tag) {
                            if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                                //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                                $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                                $tags[] = '<img data-toggle="tooltip"  class="' . $tag['class_style'] . '" title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                            }
                        }
                    }

                    $rmaIDs = $this->model_account_customerpartner->getRMAIDByOrderProduct($data['order_id'], $product['product_id'], $margin_process['buyer_id']);
                    if ($data['isAmerican']) {
                        $quote_price = $this->model_account_customerpartner->getQuotePrice($data['order_id'], $product['product_id']);  // 如果为null，表示没参与议价
                    }
                    if ($data['isEurope']) {
                        $service_fee = $this->model_account_customerpartner->getServiceFee($data['order_id'], $product['product_id']);
                    }
                    $line_total = ($data['isAmerican'] && !is_null($quote_price) ? ($quote_price * $product['quantity']) : $product['c2oprice'])
                        + $service_fee * $product['quantity'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0);

                    if($margin_process['advance_product_id'] == $product['product_id']){
                        $margin_sku = $margin_process['original_sku'];
                        $margin_mpn = $margin_process['original_mpn'];
                        $margin_product_link = $this->url->link('product/product', 'product_id=' . $margin_process['original_product_id'], true);
                    }
                    $order_product[] = array(
                        'product_id' => $product['product_id'],
                        'product_url' => $this->url->link('product/product', 'product_id=' . $product['product_id'], true),
                        'name' => $product['name'],
                        'model' => $product['model'],
                        'mpn' => $product['mpn'],
                        'sku' => $product['sku'],
                        'margin_sku' => isset($margin_sku) ? $margin_sku : null,
                        'margin_mpn' => isset($margin_mpn) ? $margin_mpn : null,
                        'margin_link' => isset($margin_product_link) ? $margin_product_link : null,
                        'quantity' => $product['quantity'],
                        //'price' => ($data['isEurope'] ? "<img data-toggle='tooltip' title='Service Fee' style='padding-left: 5px;width: 26px' src=" . $data['img_vat_url'] . " />" : '').
                        //    $this->currency->format($product['opprice'], $order_info['currency_code'], 1),
                        'price' => $this->currency->format($product['opprice'], $order_info['currency_code'], 1),
                        'total' => $this->currency->format($line_total, $order_info['currency_code'], 1),
                        'order_product_status' => $product['order_product_status'],
                        'tag' => $tags,
                        'rma_ids' => $rmaIDs,
                        'quote_discount' => empty($quote_price) ? 0 : $this->currency->format($quote_price - $product['opprice'], $order_info['currency_code'], 1),
                        'service_fee' => $this->currency->format($service_fee, $order_info['currency_code'], 1)
                    );
                }
                $data['rma_url'] = $this->url->link('account/customerpartner/rma_management/rmaInfo&rmaId=', '', true);
                $data['products'] = $order_product;

                $data['totals'] = array();
                // 计算当前订单 该seller的产品 的各种费用（total,service fee,poundage,shipping_applied）
                $totals = $this->model_account_customerpartner->getOrderTotalPrice($order_id,$get_order_seller_id);
                $totalServiceFee = 0;
                $sub_total = 0;
                if ($totals) {
                    if ($this->config->get('total_shipping_status') && isset($totals[0]['shipping_applied']) && $totals[0]['shipping_applied']) {
                        $data['totals'][] = array(
                            'title' => $totals[0]['shipping'],
                            'text' => $this->currency->format($totals[0]['shipping_applied'], $order_info['currency_code'], $order_info['currency_value']),
                        );
                    }

                    if (isset($totals[0]['total'])) {
                        $sub_total = $totals[0]['total'];
                    }
                    $totalServiceFee = $totals[0]['service_fee'];
                }
                $totals = $this->model_account_order->getOrderTotals($order_id);
                $quote_discount = 0;
                /**
                 * 针对 seller 只需要 展示 sub_total,service_fee,quote,total
                 */
                foreach ($totals as $total) {
                    if ($total['code'] == 'sub_total') {
                        $data['totals'][] = array(
                            //'title' => ($data['isEurope'] ? "<img data-toggle='tooltip' title='Service Fee' style='padding-right: 5px;width: 26px' src=" . $data['img_vat_url'] . " />" : '')
                            //. $total['title'],
                            'title' => $total['title'],
                            'text' => $this->currency->format(round($sub_total, 2), $order_info['currency_code'], 1),
                        );

                    } else if ($total['code'] == 'service_fee') {
                        $data['totals'][] = array(
                            'title' => 'Total Service Fee',
                            'text' => $this->currency->format(round($totalServiceFee, 2), $order_info['currency_code'], 1),
                        );
                    } else if ($total['code'] == 'wk_pro_quote') {
                        // 获取该订单 当前seller 对应的product议价(这个是正的) By Lester.You 2019-6-18 19:37:41
                        $quote_discount = $this->model_account_order->getSellerQuoteAmount($order_id, $get_order_seller_id);
                        if ($quote_discount) {
                            $data['totals'][] = array(
                                'title' => 'Total Item Discount',
                                'text' => $this->currency->format(round(-$quote_discount, 2), $order_info['currency_code'], 1),
                            );
                        }
                    } else if ($total['code'] == 'total') {
                        $data['totals'][] = array(
                            'title' => 'Total Price',
                            'text' => $this->currency->format(round($totalServiceFee + $sub_total - $quote_discount, 2), $order_info['currency_code'], 1),
                        );
                    }
                }

                $this->load->model('mp_localisation/order_status');

                $data['order_statuses'] = $this->model_mp_localisation_order_status->getOrderStatuses();
            }
        }

        $this->response->setOutput($this->load->view('account/customerpartner/tab_margin_detail_voucher', $data));
    }

    /**
     * 旧的协议订单页面 #18273需求后的协议跳转到新的订单页面
     * @throws Exception
     */
    public function showCheck()
    {
        $agreement_id = $this->request->get['agreement_id'];
        if (!$this->customer->isLogged() || !isset($agreement_id)) {
            session()->set('redirect', $this->url->link('account/product_quotes/margin_contract', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $data = array();
        $this->load->model('account/product_quotes/margin_contract');
        $this->load->language('account/product_quotes/margin_contract');

        $rest_orders = $this->model_account_product_quotes_margin_contract->getMarginCheckDetail($agreement_id, customer()->getId());
        if (!empty($rest_orders)) {
            $this->load->model('account/customerpartner');
            foreach ($rest_orders as $key => $rest_order) {
                $rmaIDs = $this->model_account_customerpartner->getRMAIDByOrderProduct($rest_order['rest_order_id'], $rest_order['product_id'], $rest_order['buyer_id']);
                $rest_orders[$key]['rma_ids'] = $rmaIDs;
                $rest_orders[$key]['buyer_id']=$rest_order['buyer_id'];
                $rest_orders[$key]['buyer_name'] = $rest_order['buyer_nickname'] . '(' . $rest_order['buyer_user_number'] . ')';
                $rest_orders[$key]['is_home_pickup'] = in_array($rest_order['customer_group_id'],COLLECTION_FROM_DOMICILE);
                $rest_orders[$key]['rest_order_url'] = $this->url->link('account/customerpartner/orderinfo', 'order_id=' . $rest_order['rest_order_id'], true);
            }
            $data['rma_url'] = $this->url->link('account/customerpartner/rma_management/rmaInfo&rmaId=', '', true);
            $data['restOrderList'] = $rest_orders;
        }

        $this->response->setOutput($this->load->view('account/customerpartner/tab_margin_detail_check', $data));
    }

    /**
     * 协议详情页，同意 or 拒绝
     * @version 现货保证金三期
     * @throws Exception
     */
    public function addMessage()
    {
        $post_data = $this->request->post;
        $customer_id = $this->customer->getId();
        if (!$this->customer->isLogged() || !isset($post_data['message']) || !isset($post_data['agreement_id'])) {
            session()->set('redirect', $this->url->link('account/product_quotes/margin_contract', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $this->load->model('account/product_quotes/margin_contract');
        $this->load->language('account/product_quotes/margin_contract');
        $json = array();

        $message_data = array(
            'customer_id' => $customer_id,
            'agreement_id' => $post_data['agreement_id'],
            'msg' => $post_data['message'],
            'date' => date("Y-m-d H:i:s", time())
        );

        if ($this->customer->isPartner() && isset($post_data['contract_status'])) {
            if ($post_data['contract_status'] == 3) {
                $json = $this->approve($post_data['agreement_id'], $post_data['last_update_time']);
            } else {
                $json = $this->update($post_data['agreement_id'], $post_data['contract_status'], $post_data['last_update_time']);
            }
        }

        if (!isset($json['error'])) {
            $message_id = $this->model_account_product_quotes_margin_contract->saveMarginMessage($message_data);

            if (isset($message_id)) {
                if (isset($post_data['contract_status'])) {
                    $json['success'] = $this->language->get('success_saved_seller_msg');
                } else {
                    $json['success'] = $this->language->get('text_msg_send_success');
                }
            } else {
                $json['error'] = $this->language->get('error_msg_send_fail');
            }
        }

        $this->response->setOutput(json_encode($json));
    }

    /**
     * 审批或者拒绝保证金申请，站内信发送给buyer
     * @param int $margin_id
     * @throws Exception
     */
    private function sendApproveNotificationToBuyer($margin_id)
    {
        $this->load->model('account/product_quotes/margin_contract');
        $this->language->load('account/product_quotes/margin_contract');
        // 发送站内信给seller
        $agreement_detail = $this->model_account_product_quotes_margin_contract->getMarginAgreementDetail(null, null, $margin_id);
        if (!empty($agreement_detail)) {
            $status_text = '';
            if ($agreement_detail['status'] == '3') {
                $status_text = $this->language->get('text_status_3');
            } elseif ($agreement_detail['status'] == '4') {
                $status_text = $this->language->get('text_status_4');;
            }
            $apply_msg_subject = sprintf($this->language->get('margin_approve_subject'),
                $agreement_detail['sku'],
                $status_text,
                $agreement_detail['agreement_id']);
            $apply_msg_content = sprintf($this->language->get('margin_approve_content'),
                $this->url->link('account/product_quotes/margin/detail_list&id=' . $agreement_detail['id']),
                $agreement_detail['agreement_id'],
                $this->url->link('customerpartner/profile&id=' . $agreement_detail['seller_id']),
                $agreement_detail['seller_name'],
                $agreement_detail['sku'],
                $status_text
            );
            $this->load->model('message/message');
            $this->model_message_message->addSystemMessageToBuyer('bid_margin', $apply_msg_subject, $apply_msg_content, $agreement_detail['buyer_id']);
        }
    }

    /**
     * 审核共同履约人 1同意 2拒绝
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Throwable
     */
    public function auditPerformerRequest()
    {
        $sellerId = customer()->getId();

        $performerApplyId = request()->post('performer_apply_id', 0);
        $marginId = request()->post('margin_id', 0);
        $process = request()->post('process', 0);
        $message = request()->post('message', '');

        if (empty($performerApplyId) || empty($marginId) || empty($process) || !in_array($process, [1, 2])) {
            return $this->jsonFailed();
        }

        /** @var MarginAgreement $agreement */
        $agreement = MarginAgreement::query()->where('seller_id', $sellerId)->where('id', $marginId)->first();
        if (empty($agreement)) {
            return $this->jsonFailed('Agreement does not exist.');
        }

        // 验证
        $auditResult = App(MarginAgreementRepository::class)->isCanPerformerAudit($agreement, $sellerId);
        if ($auditResult['ret'] == 0) {
            return $this->jsonFailed($auditResult['msg']);
        }

        if ($auditResult['performerId'] != $performerApplyId) {
            return $this->jsonFailed('The partner of this Margin Agreement has been reviewed. Please refresh the page to view the result.');
        }

        /** @var MarginPerformerApply $marginPerformerApply */
        $marginPerformerApply = MarginPerformerApply::query()->findOrFail($auditResult['performerId']);
        try {
            App(MarginService::class)->setAgreementAuditPerformer($agreement, $marginPerformerApply, intval($process), $message);
        } catch (\Exception $e) {
            return $this->jsonFailed();
        }

        if ($process == 1) {
            $msg = 'The Add a Partner request has been approved.';
        } else {
            $msg = 'The Add a Partner request has been denied.';
        }

        return $this->jsonSuccess([], $msg);
    }

    /**
     * 协议详情页 tab - Agreement Details
     * @param ModelCommonProduct $modelCommonProduct
     * @return string
     * @throws Exception
     * @version 现货保证金四期
     */
    public function info(ModelCommonProduct $modelCommonProduct)
    {
        $this->load->language('account/product_quotes/margin_contract');
        $agreementId = request('id');
        $sellerId = customer()->getId();

        $agreement = MarginAgreement::query()->with(['marginStatus', 'futureDelivery', 'buyer'])->where('seller_id', $sellerId)->where('id', $agreementId)->first();
        if (empty($agreement)) {
            return $this->render('customerpartner/margin/agreement_info');
        }
        $agreement['discountShow'] = is_null($agreement['discount']) ? '' : round(100 - $agreement['discount']);
        $productInfo = app(ProductRepository::class)->getProductInfoIncludeAttributeAndTags($sellerId, $agreement->product_id);
        $productInfo->image = StorageCloud::image()->getUrl($productInfo->image, ['w' => 60, 'h' => 60]);
        $message = app(MarginRepository::class)->getMessagesWithBuyerAndSeller($agreement->id, $agreement->is_bid, $agreement->buyer_id, $agreement->seller_id);
        $customerGroupId = $agreement->buyer->customer_group_id;

        $needAlarm = 0;
        $alarmPrice = $modelCommonProduct->getAlarmPrice($agreement->product_id, true, $productInfo);
        if (!(customer()->isInnerAccount()) && bccomp($agreement->price, $alarmPrice, 4) === -1) {
            $needAlarm = 1;
        }

        $agreement['ex_vat'] = VATToolTipWidget::widget(['customer' => $agreement->buyer, 'is_show_vat' => true])->render();
        $data = [
            'agreement' => $agreement,
            'product_info' => $productInfo,
            'message' => $message,
            'is_home_pickup' => in_array($customerGroupId,COLLECTION_FROM_DOMICILE),
            'need_alarm' => $needAlarm,
            'is_show_cwf_notice' => app(SellerRepository::class)->isShowCwfNotice(),
            'currency' => session()->get('currency'),
            'country' => session()->get('country'),
        ];

        return $this->render('customerpartner/margin/agreement_info', $data);
    }

    /**
     * 协议详情页 tab - deposit
     * @return string
     */
    public function deposit()
    {
        $this->load->language('account/product_quotes/margin_contract');
        $agreementId = request('id');
        $sellerId = customer()->getId();

        $agreement = MarginAgreement::query()->with(['process'])->where('seller_id', $sellerId)->where('id', $agreementId)->first();
        if (empty($agreement)  || empty($agreement->process) || empty($agreement->process->advance_product_id) || empty($agreement->process->advance_order_id)) {
            return $this->render('customerpartner/margin/agreement_deposit');
        }

        $advanceOrderId = $agreement->process->advance_order_id;
        $orderInfo = Order::query()->with(['orderProducts'])->find($advanceOrderId);
        $orderDetail = [
            'order_status' => OcOrderStatus::getDescription($orderInfo->order_status_id),
            'order_id' => $orderInfo->order_id,
            'date_added' => OrderHistory::query()->where('order_id', $orderInfo->order_id)->where('order_status_id', OcOrderStatus::COMPLETED)->value('date_added') ?: $orderInfo->date_modified,
        ];

        $productsMapIncludeTagsByIds = app(ProductRepository::class)->getProductsMapIncludeTagsByIds([$agreement->process->advance_product_id]);
        $advanceProductInfo = $productsMapIncludeTagsByIds[$agreement->process->advance_product_id];
        $price = $orderInfo->orderProducts->sum('price') + $orderInfo->orderProducts->sum('service_fee');
        $productInfo = [
            'image' => $advanceProductInfo['image'],
            'sku' => $advanceProductInfo['sku'],
            'mpn' => $advanceProductInfo['mpn'],
            'deposit_amount' => $price,
            'deposit_product_quantity' => $orderInfo->orderProducts->sum('quantity'),
            'total_deposited_products' => $price,
            'tags' => $advanceProductInfo['tags'],
        ];

        $data = [
            'agreement' => $agreement,
            'product_info' => $productInfo,
            'order_detail' => $orderDetail,
            'currency' => session()->get('currency'),
        ];

        return $this->render('customerpartner/margin/agreement_deposit', $data);
    }

    /**
     * 协议详情页 tab - Partner
     * @version 现货保证金四期
     * @return string
     */
    public function partner()
    {
        $agreementId = request('id');
        $sellerId = customer()->getId();

        $agreement = MarginAgreement::query()->where('seller_id', $sellerId)->where('id', $agreementId)->first();
        if (empty($agreement)) {
            return $this->render('customerpartner/margin/agreement_partner');
        }

        $data = [];
        $applyInfo = app(MarginRepository::class)->getPerformerApply($agreement->id, true);
        if (empty($applyInfo)) {
            return $this->render('customerpartner/margin/agreement_partner');
        }

        $applyInfo['seller_approval_status_show'] = MarginPerformerApplyStatus::getDescription($applyInfo['seller_approval_status']);
        $applyInfo['check_result_show'] = $applyInfo['check_result'] == 0 ? 'N/A' : MarginPerformerApplyStatus::getDescription($applyInfo['check_result']);
        $applyInfo['buyer_account_type'] = app(BuyerRepository::class)->getTypeById($applyInfo['performer_buyer_id']); // 1 上门取货 2 一件代发
        $applyInfo['buyer_account_name'] = BuyerType::getDescriptionNew($applyInfo['buyer_account_type']);
        $applyInfo['ex_vat'] = VATToolTipWidget::widget(['customer' => Customer::query()->find($applyInfo['performer_buyer_id']), 'is_show_vat' => true])->render();

        $data['partner_detail'] = $applyInfo;
        $data['agreement'] = $agreement;

        return $this->render('customerpartner/margin/agreement_partner', $data);
    }

    /**
     * 协议详情页 tab - Settlement
     * @return string
     * @throws \Framework\Exception\InvalidConfigException
     * @version 现货保证金四期
     */
    public function settlement()
    {
        $this->load->language('common/cwf');
        $agreementId = request('id');
        $sellerId = customer()->getId();

        $agreement = MarginAgreement::query()->where('seller_id', $sellerId)->where('id', $agreementId)->first();
        if (empty($agreement)) {
            return $this->render('customerpartner/margin/agreement_settlement');
        }

        $search = new MarginAgreementSettlementSearch($agreement->id, $agreement->product_id);
        $dataProvider = $search->search([]);
        $list = $dataProvider->getList();

        $data['total'] = $dataProvider->getTotalCount();
        $data['paginator'] = $dataProvider->getPaginator();
        $data['isAmerican'] = customer()->isUSA();
        $data['isEurope'] = customer()->isEurope();
        // 获取数据
        $orderLists = $list->toArray();
        $this->load->model('account/customerpartner');
        foreach ($orderLists as $key => $rest_order) {
            $orderLists[$key]['is_collection_from_domicile'] = app(BuyerRepository::class)->getTypeById($rest_order['buyer_id']);
            $orderLists[$key]['rma_details'] = app(RamRepository::class)->getPurchaseOrderRmaWithSumInfo($rest_order['order_id'], $rest_order['order_product_id']);
            $orderLists[$key]['sub_total'] = ($rest_order['price'] + $rest_order['service_fee_per'] + $rest_order['freight_per'] + $rest_order['package_fee']) * $rest_order['quantity'];
            $orderLists[$key]['service_fee'] = $rest_order['service_fee_per'];
            $orderLists[$key]['freight_fee'] = $rest_order['freight_per'] + $rest_order['package_fee'];
            //运费
            $isCollectionFromDomicile = $orderLists[$key]['is_collection_from_domicile'] == 1;
            $orderLists[$key]['base_ship_fee'] = $isCollectionFromDomicile ? 0.00 : $rest_order['base_freight'] - $rest_order['freight_difference_per'];
            $orderLists[$key]['freight_per'] = $isCollectionFromDomicile ? $rest_order['package_fee'] : $rest_order['freight_per'] + $rest_order['package_fee'];
        }

        $data['restOrderList'] = $orderLists;
        $data['agreement'] = $agreement;

        //已售卖的
        $restProductId = $agreement->process->rest_product_id;
        $soldQty = 0;
        if (!empty($restProductId)) {
            $restSellerNum = $this->model_account_product_quotes_margin_contract->get_rest_product_sell_num([
                [
                    'product_id' => $restProductId,
                    'agreement_id' => $agreement->id,
                ]
            ]);
            $soldQty = $restSellerNum[$agreement->id][$restProductId] ?? 0;
        }

        $data['agreement_statistics'] = [
            'total_number' => $agreement->num,
            'total_sold' => max($soldQty, 0),
            'total_unsold' => $agreement->num - $soldQty,
        ];

        //尾款支付情况
        $data['due_payment'] = app(MarginRepository::class)->getAgreementDuePayInfo($agreement->id);
        $data['currency'] = session()->get('currency');

        return $this->render('customerpartner/margin/agreement_settlement', $data);
    }
}
