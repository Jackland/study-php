<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\CampaignCondition;
use App\Enums\Marketing\CampaignType;
use App\Repositories\Marketing\CampaignRepository;
use App\Enums\Marketing\CampaignTransactionType;

class ControllerCustomerpartnerMarketingCampaignDetail extends AuthSellerController
{
    private $customerId;

    public function __construct($registry)
    {
        parent::__construct($registry);

        $this->customerId = $this->customer->getId();
        $this->load->language('customerpartner/marketing_campaign/detail');
    }

    public function index()
    {
        $validation = $this->request->validate(['id' => 'required|int|min:1']);
        if ($validation->fails()) {
            $this->redirect(['common/home'])->send();
        }

        $campaignId = $this->request->get('id');
        $campaignInfo = Campaign::find($campaignId);
        if (!$campaignInfo) {
            $this->redirect(['common/home'])->send();
        }

        $this->document->setTitle($this->language->get('text_heading_events_detials_title'));

        $campaignModel =  $this->load->model('customerpartner/marketing_campaign/index');

        $campaignInfo->setAttribute('status', $campaignModel->judgeStatus($this->customerId, $campaignInfo->id, $campaignInfo->seller_num));
        $campaignInfo->name = $campaignInfo->seller_activity_name ?: $campaignInfo->name;
        $endTime = strtotime($campaignInfo->apply_end_time);
        $campaignInfo->setAttribute('limit_time', ($endTime > time()) ? $endTime - time() : 0);

        $cateNameIdList = $campaignModel->getCateNameList($campaignInfo->require_category);
        $campaignInfo->setAttribute('cate_name', $campaignModel->getCateName($campaignInfo->require_category, $cateNameIdList));
        $campaignInfo->setAttribute('condition_info', []);

        $currency = session('currency');
        if (in_array($campaignInfo->type, [CampaignType::FULL_REDUCTION, CampaignType::FULL_DELIVERY])) {
            $conditionList = '';
            // 满减类型 - 直接获取对应满减数值
            if ($campaignInfo->type == CampaignType::FULL_REDUCTION) {
                $conditionList = CampaignCondition::where('mc_id', $campaignId)->get();
            }
            // 满送类型 - 获取关联绑定对应模板满减数值
            if ($campaignInfo->type == CampaignType::FULL_DELIVERY) {
                $campaignRepo = app(CampaignRepository::class);
                $conditionList = $campaignRepo->getFullDeliveryCampaign($campaignId);
            }

            if ($conditionList) {
                //$country_monetary_unit = $this->currency->getSymbolLeft($this->session->get('currency')) ?: $this->currency->getSymbolRight($this->session->get('currency'));
                if ($campaignInfo->type == CampaignType::FULL_REDUCTION) { // 满减
                    $conditionTemp = $this->language->get('text_off_for');
                }  else {
                    $conditionTemp = $this->language->get('text_free_for');
                }

                $formatCondition = [];
                foreach ($conditionList as $item) {
                    $orderAmountFormat = ceil($item->order_amount) == $item->order_amount ? (int)$item->order_amount : $item->order_amount;
                    $orderAmountPosition = $this->currency->formatCurrencyPrice($orderAmountFormat, $currency, '', true, 0);
                    if ($campaignInfo->type == CampaignType::FULL_DELIVERY && $item->coupon_template_id == 0) {
                        // 满送类型 & 未关联优惠券模板 --> 直接赠送备注内容
                        $conditionTemp = $this->language->get('text_free_for_other');
                        //$formatCondition[] = sprintf($conditionTemp, $item->remark,$country_monetary_unit . $orderAmountFormat);
                        $formatCondition[] = sprintf($conditionTemp, $item->remark, $orderAmountPosition);
                    } else {
                        $minusAmountFormat = ceil($item->minus_amount) == $item->minus_amount ? (int)$item->minus_amount : $item->minus_amount;
                        //$formatCondition[] = sprintf($conditionTemp, $country_monetary_unit . $minusAmountFormat, $country_monetary_unit . $orderAmountFormat);
                        $minusAmountFormatPosition = $this->currency->formatCurrencyPrice($minusAmountFormat, $currency, '', true, 0);
                        $formatCondition[] = sprintf($conditionTemp, $minusAmountFormatPosition, $orderAmountPosition);
                    }
                }
                $campaignInfo->setAttribute('condition_info', $formatCondition);
            }

            $transactionTypeFormat = '';
            $normalTranMsg = '';
            // 仅对 满送|满减 活动，处理交易类型
            $transactionTypeArr = $campaignInfo->getTransactionTypesAttribute();
            foreach ($transactionTypeArr as $transactionType) {
                if ($transactionType == CampaignTransactionType::ALL) {
                    $transactionTypeFormat .= $this->language->get('text_no_limit') . ';';
                } else {
                    $transactionTypeFormat .= CampaignTransactionType::transactionsNameMap($campaignInfo->type)[$transactionType] . '; ';
                }
                if ($transactionType == CampaignTransactionType::NORMAL) {
                    $normalTranMsg = $this->language->get('text_normal_spot_price_message');
                }
            }
            $campaignInfo->setAttribute('transactionTypeFormat', trim($transactionTypeFormat, '; '));
            $campaignInfo->setAttribute('normalTranMsg', $normalTranMsg);
        }

        $data = $this->framework();
        $data['campaignInfo'] = $campaignInfo;

        return $this->render('customerpartner/marketing_campaign/detail', $data);
    }

    private function framework()
    {
        $breadcrumbs = [
            [
                'text' => $this->language->get('text_heading_parent_title'),
                'href' => 'javascript:void(0);'
            ],
            [
                'text' => $this->language->get('text_heading_events_title'),
                'href' => $this->url->to(['customerpartner/marketing_campaign/index/activity#proEvents'])
            ],
            [
                'text' => $this->language->get('text_heading_events_detials_title'),
                'href' => 'javascription:void(0)'
            ]
        ];
        return [
            'breadcrumbs' => $breadcrumbs,
            'footer' => $this->load->controller('account/customerpartner/footer'),
            'header' => $this->load->controller('account/customerpartner/header'),
            'separate_column_left' => $this->load->controller('account/customerpartner/column_left'),
            'content_top' => $this->load->controller('common/content_top')
        ];
    }

}
