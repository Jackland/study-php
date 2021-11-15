<?php

use App\Enums\Future\FutureMarginContractDeliveryType;
use App\Helper\CountryHelper;
use Catalog\model\futures\agreementMargin;
use Catalog\model\futures\credit;

/**
 * 提供yzc_task处理合约协议交付超时接口
 *
 * Class ControllerApiFutureAgreementDeliveryTimeout
 * @package Catalog\controller\api\feature
 */
class ControllerApiFutureAgreementDeliveryTimeout extends ControllerApiBase
{

    protected $modelFuturesContract;

    protected $modelFuturesAgreement;

    protected $modelMessageMessage;

    protected $modelAccountProductQuotesMarginContract;

    protected $modelCatalogFuturesProductLock;

    protected $modelCommonProduct;

    public function __construct(
        Registry $registry,
        ModelFuturesContract $modelFuturesContract,
        ModelFuturesAgreement $modelFuturesAgreement,
        ModelMessageMessage $modelMessageMessage,
        ModelAccountProductQuotesMarginContract $modelAccountProductQuotesMarginContract,
        ModelCatalogFuturesProductLock $modelCatalogFuturesProductLock,
        ModelCommonProduct $modelCommonProduct
    )
    {
        parent::__construct($registry);

        $this->modelFuturesContract = $modelFuturesContract;
        $this->modelFuturesAgreement = $modelFuturesAgreement;
        $this->modelMessageMessage = $modelMessageMessage;
        $this->modelAccountProductQuotesMarginContract = $modelAccountProductQuotesMarginContract;
        $this->modelCatalogFuturesProductLock = $modelCatalogFuturesProductLock;
        $this->modelCommonProduct = $modelCommonProduct;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function index()
    {
        $deliveryTimeoutAllContractAgreements = $this->modelFuturesContract->deliveryTimeoutAllContractAgreements();

        $errorNum = 0;
        $allNum = 0;
        foreach ($deliveryTimeoutAllContractAgreements as $agreement) {
            if ($this->getLeftDay($agreement->delivery_date, $agreement->seller_country_id) > 0) {
                continue;
            }

            $allNum++;

            try {
//                if (!$this->modelCommonProduct->checkProductQtyIsAvailable((int)$agreement->product_id, (int)$agreement->num)) {
                    $this->handleSellerBackOrderAgreement($agreement);
//                    continue;
//                }
//
//                $this->handleSellerDeliveryAgreement($agreement);
            } catch (\Exception $e) {
                $errorNum++;
            }
        }

        if ($errorNum != 0) {
            return $this->jsonFailed('handle agreement delivery timeout success num:' . ($allNum - $errorNum) . '; error num:' . $errorNum);
        }

        return $this->jsonSuccess([], 'handle agreement delivery timeout success');
    }

    /**
     * 当前时区时间和某个时间相差天数
     * @param $date
     * @param int $countryId
     * @return int
     */
    private function getLeftDay($date, $countryId)
    {
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $current_date = dateFormat($fromZone,$toZone,  date('Y-m-d H:i:s'));
        $expected_delivery_date = dateFormat($fromZone,$toZone, date('Y-m-d',strtotime($date) ));
        $expected_delivery_date = substr($expected_delivery_date, 0, 10).' 23:59:59';
        $days = intval(ceil((strtotime($expected_delivery_date)- strtotime($current_date))/86400));

        if($days <= 0){
            return 0;
        }else{
            return $days;
        }
    }

    /**
     * 确认交付逻辑
     * @param $agreement
     * @throws \Exception
     */
    private function handleSellerDeliveryAgreement($agreement)
    {
        $this->orm->getConnection()->beginTransaction();
        try {
            $deliveryData = [
                'update_time' => date('Y-m-d H:i:s'),
                'delivery_status' => 6,
                'confirm_delivery_date' => date('Y-m-d H:i:s'),
                'delivery_date' => date('Y-m-d H:i:s'),
            ];

            $applyId = $this->modelFuturesAgreement->addFutureApply([
                'agreement_id' => $agreement->id,
                'customer_id' => 0,
                'apply_type' => 5,
                'status' => 1,
            ]);
            $this->modelFuturesAgreement->addMessage([
                'agreement_id' => $agreement->id,
                'customer_id' => 0,
                'apply_id' => $applyId,
                'message' => '',
            ]);

            if (in_array($agreement->delivery_type, FutureMarginContractDeliveryType::getIncludeMarginUnit()) && !$agreement->margin_agreement_id) {
                // 生成现货协议
                $marginAgreement = $this->modelFuturesAgreement->addNewMarginAgreement($agreement, $agreement->buyer_country_id);
                // 生成现货头款产品
                $advanceProductId = $this->modelFuturesAgreement->copyMarginProduct($marginAgreement, 1);
                // 创建现货保证金记录
                $marginProcess = [
                    'margin_id' => $marginAgreement['agreement_id'],
                    'margin_agreement_id' => $marginAgreement['agreement_no'],
                    'advance_product_id' => $advanceProductId,
                    'process_status' => 1,
                    'create_time' => date('Y-m-d H:i:s'),
                    'create_username' => $agreement->seller_id,
                    'program_code' => 'V1.0'
                ];
                $this->modelAccountProductQuotesMarginContract->addMarginProcess($marginProcess);
                $deliveryData['margin_agreement_id'] = $marginAgreement['agreement_id'];
                $this->modelCatalogFuturesProductLock->TailIn($agreement->id, $agreement->margin_apply_num, $agreement->id, 0);
                $this->modelCatalogFuturesProductLock->TailOut($agreement->id, $agreement->margin_apply_num, $agreement->id, 6);
                $orderId = $this->modelFuturesAgreement->autoFutureToMarginCompleted($agreement->id);
            } else {
                $this->modelCatalogFuturesProductLock->TailIn($agreement->id, $agreement->num, $agreement->id, 0);
            }

            $conditionBuyer = $this->setSystemTemplateOfCommunication($agreement, 'buyer', true);
            $this->modelMessageMessage->addSystemMessageToBuyer('bid_futures', $conditionBuyer['subject'], $conditionBuyer['message'], $agreement->buyer_id);

            $log = [
                'agreement_id' => $agreement->id,
                'customer_id' => -1,
                'type' => 13,
                'operator' => 'System',
            ];
            $this->modelFuturesAgreement->addAgreementLog($log,
                [$agreement->agreement_status, $agreement->agreement_status],
                [$agreement->delivery_status, $deliveryData['delivery_status']]
            );

            $this->modelFuturesAgreement->updateDelivery($agreement->id, $deliveryData);

            $this->orm->getConnection()->commit();
            if (isset($orderId)) {
                $this->modelFuturesAgreement->autoFutureToMarginCompletedAfterCommit($orderId);
            }
        } catch (\Exception $e) {
            $this->orm->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * seller违约逻辑
     * @param $agreement
     * @throws \Exception
     */
    private function handleSellerBackOrderAgreement($agreement)
    {
        $firstPayRecordContracts = $this->modelFuturesContract->firstPayRecordContracts($agreement->seller_id, [$agreement->contract_id]);
        if (isset($firstPayRecordContracts[0]) && !empty($firstPayRecordContracts[0]['pay_type'])) {
            $payType = $firstPayRecordContracts[0]['pay_type'];
        } else {
            $payType = $agreement->accounting_type == 1 ? 1 : 3;
        }

        $point = $agreement->seller_country_id == 107 ? 0 : 2;
        // seller保证金
        $amount = round($agreement->unit_price * $agreement->seller_payment_ratio * 0.01, $point)  * $agreement->num;
        // seller 缴纳的平台费
        $platformAmount = round($amount * 0.05, $point);
        // 退还给buyer的钱 $paidAmount
        $paidAmount = round($amount - $platformAmount, $point);
        // buyer保证金
        $buyerAmount = round($agreement->unit_price * $agreement->buyer_payment_ratio * 0.01, $point) * $agreement->num;

        $log['info'] = [
            'agreement_id' => $agreement->id,
            'customer_id' => -1,
            'type' => 32, //back order
            'operator' => 'System',
        ];
        $log['agreement_status'] = [$agreement->agreement_status, $agreement->agreement_status];
        $log['delivery_status'] = [$agreement->delivery_status, 2];

        $conditionBuyer = $this->setSystemTemplateOfCommunication($agreement, 'buyer');
        $conditionSeller = $this->setSystemTemplateOfCommunication($agreement, 'seller');

        $this->orm->getConnection()->beginTransaction();
        try {
            $this->modelFuturesAgreement->addAgreementLog($log['info'],
                $log['agreement_status'],
                $log['delivery_status']
            );

            $this->modelFuturesAgreement->addFutureApply([
                'agreement_id' => $agreement->id,
                'customer_id' => 0,
                'apply_type' => 2,
                'status' => 1,
            ]);

            // 发站内信
            $this->modelMessageMessage->addSystemMessageToBuyer('bid_futures', $conditionBuyer['subject'], $conditionBuyer['message'], $agreement->buyer_id);
            $this->modelMessageMessage->addSystemMessageToBuyer('bid_futures', $conditionSeller['subject'], $conditionSeller['message'], $agreement->seller_id);

            // seller 本金拿回
            agreementMargin::sellerBackFutureMargin($agreement->seller_id, $agreement->id, $amount, $payType);
            // seller 赔付 buyer
            AgreementMargin::sellerWithHoldFutureMargin($agreement->seller_id, $agreement->id, $paidAmount, $payType);
            // seller 赔付 平台费用
            AgreementMargin::sellerPayFuturePlatform($agreement->seller_id, $agreement->id, $platformAmount, $payType);

            // 授信额度即时退回
            if ($payType == 1) {
                credit::insertCreditBill($agreement->seller_id, $amount, 2);
                credit::insertCreditBill($agreement->seller_id, $paidAmount, 1, 'Failure to uphold agreement');
                credit::insertCreditBill($agreement->seller_id, $platformAmount, 1, 'Marketplace fees');
            }

            // buy加金额
            $this->modelFuturesAgreement->addCreditRecord($agreement, $buyerAmount, 1);
            $this->modelFuturesAgreement->addCreditRecord($agreement, $paidAmount, 2);

            $this->modelFuturesAgreement->updateDelivery($agreement->id, ['delivery_status' => 2]);

            $this->orm->getConnection()->commit();
        } catch (\Exception $e) {
            $this->orm->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * 生成模板
     * @param $agreement
     * @param $receivedType 'seller'|'buyer'
     * @param bool $deliveryResult 交付结果
     * @return mixed
     */
    private function setSystemTemplateOfCommunication($agreement, $receivedType, $deliveryResult = false)
    {

        $message = <<<HTML
    <table  border="0" cellspacing="0" cellpadding="0">
        <tr>
            <th align="left">Future Goods Agreement ID:&nbsp;</th>
            <td style="width: 600px">
                <a target="_blank" href="{agreement_detail_url}">{agreement_no}</a>
            </td>
        </tr>
        <tr>
            <th align="left">{column_name}:&nbsp;</th>
             <td style="width: 600px">{name}</td>
        </tr>
        <tr>
             <th align="left">Item Code/MPN:&nbsp;</th>
             <td style="width: 600px">{sku_and_mpn}</td>
        </tr>
        <tr>
             <th align="left">Delivery Date:&nbsp;</th>
             <td style="width: 600px">{delivery_date}</td>
        </tr>
        <tr>
             <th align="left">Delivery Result:&nbsp;</th>
              <td style="width: 600px">{delivery_result}</td>
        </tr>
        {other_html}
    </table>
HTML;
        if ($receivedType == 'seller') {
            $agreementDetailUrl = $this->url->to(['account/product_quotes/futures/sellerFuturesBidDetail', 'id' => $agreement->id]);
            $column_name = 'Name';
            $name = $agreement->nickname . '(' . $agreement->user_number . ')';
            $other_fail_html = ' <tr><th align="left">Fail Reason:&nbsp;</th><td style="width: 600px">Failed to deliver due to timeout</td></tr>';
        } else {
            $agreementDetailUrl = $this->url->to(['account/product_quotes/futures/buyerFuturesBidDetail', 'id' => $agreement->id]);
            $name = "<a target='_blank' href='" . $this->url->to(['customerpartner/profile', 'id' => $agreement->seller_id]) . "'>" . $agreement->screenname . "</a>";
            $column_name = 'Store';
            $other_fail_html = ' <tr><th align="left">Fail Reason:&nbsp;</th><td style="width: 600px">Seller failed to deliver due to timeout</td></tr>';
        }

        $formatMessage = dprintf(
            $message,
            [
                'agreement_detail_url' => $agreementDetailUrl,
                'agreement_no' => $agreement->agreement_no,
                'column_name' => $column_name,
                'name' => $name,
                'sku_and_mpn' => $agreement->sku . '/' . $agreement->mpn,
                'delivery_date' => $agreement->expected_delivery_date,
                'delivery_result' => $deliveryResult  ? 'Complete Delivery' : 'Delivery Failed',
                'other_html' => $deliveryResult ?: $other_fail_html,
            ]
        );

        $ret['subject'] = 'The delivery of future goods agreement (' . $agreement->agreement_no . ') has been failed.';
        $ret['message'] = $formatMessage;

        return $ret;
    }
}
