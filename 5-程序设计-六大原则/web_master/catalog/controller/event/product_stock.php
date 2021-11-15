<?php

use App\Enums\Future\FuturesVersion;
use App\Services\Margin\MarginService;
use App\Enums\Margin\MarginAgreementStatus;
use App\Enums\Margin\MarginAgreementLogType;

/**
 * Class ControllerEventProductStock
 *
 * @property ModelAccountProductQuotesMarginContract $model_account_product_quotes_margin_contract
 * @property ModelCatalogMarginProductLock $model_catalog_margin_product_lock
 * @property ModelCatalogFuturesProductLock $model_catalog_futures_product_lock
 * @property ModelCommonProduct $model_common_product
 */
class ControllerEventProductStock extends Controller
{

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->model('common/product');
        $this->load->model('catalog/margin_product_lock');
        $this->load->model('catalog/futures_product_lock');
    }

    /**
     * 锁定库存Action执行发生后
     * @see ModelCatalogMarginProductLock::TailIn()
     * @see ModelCatalogMarginProductLock::TailOut()
     */
    public function lockAfter($route, $args, $output)
    {
        list($agreement_id, $qty, $transaction_id, $type) = $args;
        $orig_product_id = $this->orm
            ->table('tb_sys_margin_agreement')
            ->where('id', $agreement_id)
            ->value('product_id');
        if (!$orig_product_id) return;
        // rma 特殊处理
        // 如果rma为入库锁定库存 此时协议状态如果为completed(8)需要变更为sold(6)
        if ($type == ModelCatalogMarginProductLock::LOCK_TAIL_RMA) {
            $this->changeAgreeStatus($agreement_id);
        }
        // 锁定库存生成 需要变动对应combo库存
        /** @see ModelCheckoutOrder::withHoldStock() */
        if (in_array(
            $type,
            [
                ModelCatalogMarginProductLock::LOCK_TAIL_GENERATE,
                ModelCatalogMarginProductLock::LOCK_TAIL_PURCHASE,
            ]
        )) {
            $this->model_common_product->updateProductOnShelfQuantity($orig_product_id);
        }
    }

    /**
     * @param $route
     * @param $args
     * @param $output
     * @throws Exception
     * @see ModelCatalogFuturesProductLock::TailIn()
     * @see ModelCatalogFuturesProductLock::TailOut()
     */
    public function futuresLockAfter($route, $args, $output)
    {
        list($agreement_id, $qty, $transaction_id, $type) = $args;
        $orig_product_id = $this->orm
            ->table('oc_futures_margin_agreement')
            ->where('id', $agreement_id)
            ->value('product_id');
        if (!$orig_product_id) return;
        // rma 特殊处理
        // 如果rma为入库锁定库存 此时协议状态如果为completed(8)需要变更为sold(6)
        if ($type == ModelCatalogFuturesProductLock::LOCK_TAIL_RMA) {
            $this->changeFuturesAgreeStatus($agreement_id);
        }
        // 锁定库存生成 如果当前产品 为子产品 需要变动对应combo库存
        if (in_array(
            $type,
            [
                ModelCatalogFuturesProductLock::LOCK_TAIL_GENERATE,
                ModelCatalogFuturesProductLock::LOCK_TAIL_PURCHASE,
            ]
        )) {
            $this->model_common_product->updateProductOnShelfQuantity($orig_product_id);
        }
    }

    /**
     * @param int $agreement_id
     * user：wangjinxin
     * date：2020/3/30 16:29
     * @throws Exception
     */
    private function changeAgreeStatus(int $agreement_id)
    {
        $cm = $this->model_catalog_margin_product_lock;
        $product_info = $cm->getOrigProductInfoByAgreementId((int)$agreement_id);
        $seller_id = (int)$product_info['seller_id'];
        $product_id = (int)$product_info['product_id'];
        $product_lock_info = $cm->getProductLockInfo($agreement_id, $product_id);
        if (empty($product_lock_info)) return;
        // 如果保证金协议完成状态下 发生退货退款 除了变动锁定库存外 还可能将协议状态
        // 由completed(8) 变更为 sold(6)
        if ($product_lock_info[0]['qty'] > 0 && $product_info['status'] == 8) {
            $this->load->model('account/product_quotes/margin_contract');
            $this->model_account_product_quotes_margin_contract
                ->updateMarginContractStatus($seller_id, $product_info['agreement_id'], 6);

            //现货四期，记录协议状态变更
            app(MarginService::class)->insertMarginAgreementLog([
                'from_status' => MarginAgreementStatus::COMPLETED,
                'to_status' => MarginAgreementStatus::SOLD,
                'agreement_id' => $agreement_id,
                'log_type' => MarginAgreementLogType::COMPLETED_TO_TO_BE_PAID,
                'operator' => customer()->getNickName(),
                'customer_id' => customer()->getId(),
            ]);

        }
    }

    /**
     * @param int $agreement_id
     * user：wangjinxin
     * date：2020/3/30 16:29
     * @throws Exception
     */
    private function changeFuturesAgreeStatus(int $agreement_id)
    {
        $cm = $this->model_catalog_futures_product_lock;
        $product_info = $cm->getOrigProductInfoByAgreementId((int)$agreement_id);
        $product_lock_info = $cm->getProductLockInfo($agreement_id, (int)$product_info['product_id']);
        if (empty($product_lock_info)) return;
        // 如果期货保证金协议完成状态下 发生退货退款 除了变动锁定库存外 还可能将协议状态
        // 由completed(8) 变更为 sold(6)
        // #28282 新的version=3的期货协议状态不变了
        if ($product_lock_info[0]['qty'] > 0 && $product_info['status'] == 8 && $product_info['version'] < FuturesVersion::VERSION) {
            $this->orm
                ->table('oc_futures_margin_delivery')
                ->where([
                    'agreement_id' => $agreement_id,
                    'delivery_status' => 8
                ])
                ->update(['delivery_status' => 6]);

            $this->orm
                ->table('oc_futures_margin_process')
                ->where([
                    'agreement_id' => $agreement_id,
                    'process_status' => 4
                ])
                ->update(['process_status' => 3]);
        }
    }
}
