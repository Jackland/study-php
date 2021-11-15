<?php

namespace App\Services\Margin;

use App\Components\BatchInsert;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Margin\MarginAgreementPayRecordBillType;
use App\Enums\Margin\MarginAgreementPayRecordType;
use App\Enums\Margin\MarginAgreementStatus;
use App\Enums\Order\OcOrderTypeId;
use App\Enums\Product\ProductType;
use App\Enums\Agreement\AgreementCommonPerformerAgreementType;
use App\Enums\Common\YesNoEnum;
use App\Models\Agreement\AgreementCommonPerformer;
use App\Models\CustomerPartner\CustomerPartnerToProduct;
use App\Models\Link\ProductToTag;
use App\Models\Margin\MarginAgreement;
use App\Models\Margin\MarginAgreementPayRecord;
use App\Models\Margin\MarginMessage;
use App\Models\Margin\MarginPerformerApply;
use App\Models\Order\OrderProduct;
use App\Models\Product\Product;
use App\Models\Product\ProductImportBatchErrorReport;
use App\Repositories\Margin\MarginRepository;
use Carbon\Carbon;
use Exception;
use App\Models\Margin\MarginAgreementLog;

class MarginService
{
    /**
     * 生成保证金头款
     *
     * @param int $agreementId
     * @param $agreementDetail
     * @return int
     * @throws Exception
     *
     */
    public function copyMarginProduct(int $agreementId, $agreementDetail)
    {

        $marginRepository = app(MarginRepository::class);
        if (!$agreementDetail) {
            $agreementDetail = $marginRepository->getMarginAgreementDetail($agreementId);
        }

        $sellerId = (int)$agreementDetail['seller_id'];
        $productId = (int)$agreementDetail['product_id'];
        $country_id = $agreementDetail['country_id'];
        $agreementCode = $agreementDetail['agreement_id'];
        $productInfo = Product::query()->alias('p')
            ->joinRelations(['description as pd'])
            ->where('p.product_id', $productId)
            ->select(['p.*', 'pd.name', 'pd.description'])
            ->first();
        if (!$productInfo) {
            throw new Exception(__FILE__ . " Can not find product relate to product_id:{$productId}.");
        }

        // 新的sku
        $skuNew = 'M'
            . str_pad($country_id, 4, "0", STR_PAD_LEFT)
            . date("md") . substr(time(), -6);
        // 新的商品名称
        $productNameNew = "[Agreement ID:{$agreementCode}]{$productInfo->name}";
        // 产品单价
        $priceNew = round($agreementDetail['sum_price'], 2);
        $param = [
            'product_id' => $productId,
            'num' => 1,
            'price' => $priceNew,
            'seller_id' => $sellerId,
            'sku' => $skuNew,
            'product_name' => $productNameNew,
            'freight' => 0,//保证金订金商品的运费和打包费都为0
            'package_fee' => 0,
            'product_type' => configDB('product_type_dic_margin_deposit'),
        ];
        // 复制产品
        /** @var \ModelCatalogProduct $catalogProduct */
        $catalogProduct = load()->model('catalog/product');
        $productIdNew = $catalogProduct->copyProductMargin($productId, $param);
        if ($productIdNew === 0) {
            throw new Exception(__FILE__ . " Create product failed. product_id:{$productId}");
        }
        // 更新ctp
        $productArrays = [
            'customer_id' => $sellerId,
            'product_id' => $productIdNew,
            'seller_price' => $priceNew,
            'price' => $priceNew,
            'currency_code' => '',
            'quantity' => 1,
        ];
        CustomerPartnerToProduct::query()->insertGetId($productArrays);
        // 更新product tag
        $origTags = ProductToTag::query()->where('product_id', $productId)->get();
        if (!$origTags->isEmpty()) {
            $insertTags = [];
            foreach ($origTags as $item) {
                $insertTags[] = [
                    'is_sync_tag' => ($item->tag_id == 1) ? 0 : $item->is_sync_tag,
                    'tag_id' => $item->tag_id,
                    'product_id' => $productIdNew,
                    'create_user_name' => $sellerId,
                    'update_user_name' => $sellerId,
                    'create_time' => Carbon::now(),
                    'program_code' => 'MARGIN',
                ];
            }
            ProductToTag::query()->insert($insertTags);
        }

        return $productIdNew;
    }

    /**
     * seller审核共同履约人
     *
     * #27869 现货保证金共同履约人添加之后审核流程去掉： 1.seller直接修改check_result的状态，2.插入oc_agreement_common_performer
     *
     * @param MarginAgreement $agreement
     * @param MarginPerformerApply $marginPerformerApply
     * @param int $status 审核状态 1-通过 2-不通过
     * @param string|null $message seller添加的信息
     * @throws \Throwable
     * @version 现货保证金四期
     */
    public function setAgreementAuditPerformer(MarginAgreement $agreement, MarginPerformerApply $marginPerformerApply, int $status, ?string $message = null)
    {
        dbTransaction(function () use ($agreement, $marginPerformerApply, $status, $message) {
            MarginPerformerApply::query()
                ->where('id', $marginPerformerApply->id)
                ->update([
                    'check_result' => $status,
                    'seller_approval_status' => $status,
                    'seller_approval_time' => Carbon::now()->toDateTimeString(),
                    'seller_check_reason' => trim($message),
                    'update_time' => Carbon::now()->toDateTimeString(),
                ]);

            if ($status == 1) {
                // 审核同意的插入共同履约人表
                AgreementCommonPerformer::query()->insert([
                    'agreement_type' => AgreementCommonPerformerAgreementType::MARGIN,
                    'agreement_id' => $agreement->id,
                    'product_id' => $agreement->product_id,
                    'buyer_id' => $marginPerformerApply->performer_buyer_id,
                    'is_signed' => YesNoEnum::NO,
                    'create_user_name' => $agreement->seller_id,
                    'create_time' => Carbon::now()->toDateTimeString(),
                ]);

                $reason = 'Seller has approved the Add a Partner request.';
            } else {
                $reason = 'Seller has denied the Add a Partner request.';
            }

            MarginMessage::insert([
                'margin_agreement_id' => $agreement->id,
                'customer_id' => $agreement->seller_id,
                'message' => $reason,
                'create_time' => Carbon::now(),
                'memo' => 'Seller Audit Performer Request',
            ]);
        });
    }

    /**
     * 现货协议变更日志，严格传参
     * @param $params
     * @return bool|object
     */
    public function insertMarginAgreementLog($params)
    {
        if ($params['from_status'] && $params['to_status'] && $params['agreement_id'] && $params['log_type']) {
            if ($params['from_status'] == $params['to_status']) {
                $content = MarginAgreementStatus::getDescription($params['from_status']);
            } else {
                $content = MarginAgreementStatus::getDescription($params['from_status']) . '->' . MarginAgreementStatus::getDescription($params['to_status']);
            }

            return MarginAgreementLog::query()->insert(
                [
                    'agreement_id' => $params['agreement_id'],
                    'type' => $params['log_type'],
                    'customer_id' => $params['customer_id'] ?? 0,
                    'content' => json_encode(['agreement_status' => $content]),
                    'operator' => $params['operator'] ?? '',
                    'create_time' => Carbon::now(),
                    'update_time' => Carbon::now(),
                ]);
        }
    }

    /**
     * Onsite Seller 现货协议保证金扣除（记账）
     *
     * @param int $orderId 采购订单ID
     * @param string $nowDate 时间(Y-m-d H:i:s)
     * @return bool
     */
    public function insertMarginAgreementDeposit($orderId, $nowDate = '')
    {
        empty($nowDate) && $nowDate = date('Y-m-d H:i:s');

        $marginList = OrderProduct::query()->alias('op')
            ->leftJoinRelations('product as p', 'customerPartnerToProduct as ctp')
            ->leftJoin('oc_customer as c', 'ctp.customer_id', 'c.customer_id')
            ->leftJoin('tb_sys_margin_agreement as ma', 'op.agreement_id', 'ma.id')
            ->select(['ma.*', 'c.country_id'])
            ->where('p.product_type', ProductType::MARGIN_DEPOSIT)
            ->where('op.order_id', $orderId)
            ->where('op.type_id', OcOrderTypeId::TYPE_MARGIN)
            ->where('c.accounting_type', CustomerAccountingType::GIGA_ONSIDE)
            ->whereNotNull('op.agreement_id')
            ->get();
        if ($marginList->isNotEmpty()) {
            $batchInsert = new BatchInsert();
            $batchInsert->begin(MarginAgreementPayRecord::class, 500);
            foreach ($marginList as $item) {
                $precision = JAPAN_COUNTRY_ID == $item->country_id ? 0 : 2;

                $batchInsert->addRow([
                    'agreement_id' => $item->id,
                    'customer_id' => $item->seller_id,
                    'type' => MarginAgreementPayRecordType::ACCOUNT_RECEIVABLE,
                    'amount' => round($item->price * $item->payment_ratio / 100, $precision) * $item->num,
                    'bill_type' => MarginAgreementPayRecordBillType::EXPEND,
                    'create_time' => $nowDate,
                    'update_time' => $nowDate
                ]);
            }
            $batchInsert->end();
        }

        return true;
    }

}
