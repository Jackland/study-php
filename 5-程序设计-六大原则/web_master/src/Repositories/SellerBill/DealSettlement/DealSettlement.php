<?php

namespace App\Repositories\SellerBill\DealSettlement;

use App\Enums\Common\YesNoEnum;
use App\Enums\SellerBill\SellerAccountInfoAccountType;
use App\Enums\SellerBill\SellerBillDetailFrozenFlag;
use App\Enums\SellerBill\SellerBillSettlementStatus;
use App\Enums\SellerBill\SellerBillTypeCode;
use App\Helper\CountryHelper;
use App\Helper\LangHelper;
use App\Models\Futures\FuturesContract;
use App\Models\Quote\ProductQuote;
use App\Models\Rebate\RebateAgreementItem;
use App\Models\SellerBill\SellerBill;
use App\Models\SellerBill\SellerBillDetail;
use App\Models\SellerBill\SellerBillFrozenRelease;
use App\Models\SellerBill\SellerBillOrderSettleType;
use App\Models\SellerBill\SellerBillType;
use App\Models\SellerBill\ServiceFeeCategoryDetail;
use App\Models\Warehouse\SellerInventoryAdjust;
use App\Repositories\SellerBill\SettlementRepository;
use Cart\Currency;
use Illuminate\Support\Collection;

class DealSettlement
{
    /** @var Currency */
    private $currency;
    private $typeArr;
    private $currencyUnit;
    private $billInfo;
    private $isOnsiteSeller;
    private $unfrozenTypeList;
    protected $notData = 'N/A';

    public function __construct()
    {
        $this->currencyUnit = session()->get('currency');
        $this->currency = app('registry')->get('currency');
        $typeList = SellerBillType::where('status', YesNoEnum::YES)->get()->toArray();
        $this->typeArr = array_column($typeList, 'code', 'type_id');
        $this->isOnsiteSeller = customer()->isGigaOnsiteSeller();
    }

    /**
     * 格式化结算单三期结算明细数据
     *
     * @param Collection $data 结算单详情集合
     * @param int $typeId 结算类目ID
     * @param int $billId 结算单ID
     * @return array
     */
    public function formatData($data, $typeId, $billId)
    {
        $result = [];
        if ($data->isEmpty()) {
            return $result;
        }

        // Onsite Seller需要展示冻结解冻对应信息
        if ($this->isOnsiteSeller) {
            $this->unfrozenTypeList = SellerBillOrderSettleType::get()->keyBy('id')->toArray();
        }

        if ($this->typeArr[$typeId] ==  SellerBillTypeCode::V3_ORDER) {
            $method = 'formatOrderData';
        } else {
            $this->billInfo = SellerBill::query()->alias('b')
                ->leftJoin('oc_customer as c', 'b.seller_id', '=', 'c.customer_id')
                ->select('b.start_date', 'b.end_date', 'c.firstname', 'c.lastname', 'c.logistics_customer_name', 'b.settlement_status')
                ->where('b.id', $billId)
                ->first();
            if (! $this->billInfo) {
                return $result;
            }
            $method = 'formatOtherData';
        }

        foreach ($data as $item) {
            $res['id'] = $item->id;
            list($res['date_format']['day'], $res['date_format']['time']) = explode(' ', $item->show_date);
            $res['type_format'] = $this->formatType($item);
            $res['total_format'] = $this->currency->formatCurrencyPrice($item->total, $this->currencyUnit);
            $res['frozen_flag'] = $item->frozen_flag;

            $this->$method($item, $res);
            $result[] = $res;
        }

        return $result;
    }

    /**
     * 费用单类型
     *
     * @param SellerBillDetail $item 结算单详情
     * @return array
     */
    private function formatType($item)
    {
        $res['name'] = __($this->typeArr[$item->type], [], 'controller/bill');
        $res['desc'] = '';

        if (in_array($this->typeArr[$item->type], SellerBillTypeCode::getShowDescTypes())) {
            $res['desc'] = __($this->typeArr[$item->type] . '_desc', [], 'controller/bill');
        } elseif (in_array($this->typeArr[$item->type], [SellerBillTypeCode::V3_UNLOAD_DETAIL, SellerBillTypeCode::V3_SPECIAL_DETAIL]) && $item->getAttribute('service_project')) {
            // 卸货费、特殊费用
            $category = ServiceFeeCategoryDetail::where('id', $item->getAttribute('service_project'))
                ->first();
            if ($category) {
                $valueName = LangHelper::isChinese() ? 'service_project' : 'service_project_english';
                $res['desc'] = $category->$valueName;
            }
        }

        return $res;
    }

    /**
     * 非订单类目数据格式化
     *
     * @param SellerBillDetail $item 结算单详情
     * @param array $res 结果数组
     * @return bool
     */
    private function formatOtherData($item, &$res)
    {
        // 费用录入的时间，如果超过了当前结算周期的最后一天，则显示该结算周期的最后一天的23:59:59
        if ($item->produce_date->gt($this->billInfo->end_date)) {
            list($res['date_format']['day'], $res['date_format']['time']) = explode(' ', $this->billInfo->end_date->toDateTimeString());
        }

        $res['no'] = $this->formatOtherSettlementNo($item);
        $res['cost_detail'] = $item->getAttribute('charge_detail') ? $item->getAttribute('charge_detail') : $this->notData;
        $res['remark'] = $item->getAttribute('special_remark') ? $item->getAttribute('special_remark') : $this->notData;
        $res['file_list'] =  $this->formatFile($item, $res);

        return true;
    }

    /**
     * 费用编号-非订单类目
     *
     * @param SellerBillDetail $item 结算单详情
     * @return array
     */
    private function formatOtherSettlementNo($item)
    {
        $res = [
            'no' => $item->getAttribute('fee_number') ? $item->getAttribute('fee_number') : $this->notData,
            'link' => ''
        ];

        if (in_array($this->typeArr[$item->type], SellerBillTypeCode::getDefineNoTypes()) && ! $item->special_id) { // 平台费、物流费、仓租费、欠款利息
            $res['no'] = $this->billInfo->firstname . $this->billInfo->lastname . '-' . __($this->typeArr[$item->type], [], 'controller/bill') .
            '(' . $this->billInfo->start_date->toDateString() . '-' . $this->billInfo->end_date->toDateString() . ')';
        } elseif ($this->typeArr[$item->type] == SellerBillTypeCode::V3_BID_BREAK_DETAIL && ! $item->special_id) { // 复杂交易违约平台费 - 有后台录入已后台录入为准
            if ($item->future_margin_id) {
                $res['no'] = $item->future_agreement_no;
                $res['link'] = url()->to(['account/product_quotes/futures/sellerBidDetail', 'id' => $item->future_margin_id]);
            } elseif ($item->agreement_id) {
                $res['no'] = $item->agreement_id;
                $res['link'] = url()->to(['account/product_quotes/margin_contract/view', 'agreement_id' => $item->agreement_id]);
            }
        } elseif ($this->typeArr[$item->type] == SellerBillTypeCode::V3_RMA_PROCESS_DETAIL && $item->rma_id) { // RMA处理费 - 有后台录入已后台录入为准
            $res['no'] = $item->getAttribute('rma_no');
            $res['link'] = url()->to(['account/customerpartner/rma_management/rmaInfo', 'rmaId' => $item->rma_id]);
        }

        return $res;
    }

    /**
     * 订单类目数据
     *
     * @param SellerBillDetail $item 结算单详情
     * @param array $res 结果数组
     * @return bool
     */
    private function formatOrderData($item, &$res)
    {
        $res['show_quantity'] = $this->formatQuantity($item);
        $res['item_code'] = $this->formatItemCode($item);
        $res['fright_format'] = $this->formatFreightOrPackageFee($item);
        $res['package_fee_format'] = $this->formatFreightOrPackageFee($item, 2);
        $res['price_value'] = $this->formatProductValue($item);
        $res['no'] = $this->formatSettlementNo($item);
        $res['total_desc'] = $this->formatTotalDesc($item);
        $res['surplus_frozen_total_format'] = $this->formatFrozenAmount($item);
        $res['unfrozen_type'] = $this->formatUnfrozenType($item);

        return true;
    }

    /**
     * 格式化解冻类型
     *
     * @param SellerBillDetail $item 结算单详情
     * @return string
     */
    private function formatUnfrozenType($item)
    {
        $unfrozenType = '';
        if ($this->isOnsiteSeller && $item->order_settle_type && isset($this->unfrozenTypeList[$item->order_settle_type])) {
            LangHelper::isChinese() ? $column = 'cn_name' : $column = 'en_name';
            $unfrozenType = $this->unfrozenTypeList[$item->order_settle_type][$column];
        }

        return $unfrozenType;
    }

    /**
     * 格式化剩余冻结金额
     *
     * @param SellerBillDetail $item 结算单详情
     * @return string
     */
    private function formatFrozenAmount($item)
    {
        $surplusFrozenAmountFormat = '';
        if ($this->isOnsiteSeller && isset($item->surplus_frozen_amount)) {
            $surplusFrozenAmountFormat = $this->currency->formatCurrencyPrice($item->surplus_frozen_amount, $this->currencyUnit);
        }

        return $surplusFrozenAmountFormat;
    }

    /**
     * 格式化小结描述
     *
     * @param SellerBillDetail $item 结算单详情
     * @return string
     */
    private function formatTotalDesc($item)
    {
        // Onsite Seller 需要展示对应的冻结信息
        if (! customer()->isGigaOnsiteSeller()) {
            return '';
        }

        if ($item['frozen_flag'] == SellerBillDetailFrozenFlag::FROZEN) {
            return SellerBillDetailFrozenFlag::getDescription(SellerBillDetailFrozenFlag::FROZEN);
        }
        if ($item['frozen_flag'] == SellerBillDetailFrozenFlag::UNFROZEN) {
            if ($item->frozen_release_date) {
                return $item->frozen_release_date->setTimezone(CountryHelper::getTimezone(customer()->getCountryId()))->toDateTimeString();
            }
        }

        return '';
    }

    /**
     * 文件
     *
     * @param SellerBillDetail $item 结算单详情
     * @param array $res
     * @return array
     */
    private function formatFile($item, $res)
    {
        $files = [];
        $fileList = $this->getDetailFiles($item->getAttribute('inventory_id'), $item->getAttribute('annexl_menu_id'), $item->file_menu_id);

        if ($fileList) {
            $fileArr = [];
            foreach ($fileList as $ii) {
                $file['name'] = $ii['file_name'];
                $file['link'] = url()->to(['account/seller_bill/bill_detail/downloadDetail', 'menu_detail_id' => $ii['id'], 'detail_id' => $item->id]);

                $fileArr[] = $file;
            }
        }

        if ($item->special_id && isset($fileArr)) {
            $files = $fileArr;
        } elseif ($this->typeArr[$item->type] == SellerBillTypeCode::V3_PLATFORM_FEE_DETAIL) { // 平台费
            $file['link'] = '';
            if ($this->billInfo->settlement_status == SellerBillSettlementStatus::GOING) { // 计算中
                $file['name'] = $this->notData;
            } else {
                $file['name'] = __('如需附件请发送邮件至account@gigacloudtech.com邮箱', [], 'controller/bill');
            }
            $files[] = $file;
        } elseif (in_array($this->typeArr[$item->type], SellerBillTypeCode::getAutoCalcTypes())) {
            if ($this->billInfo->settlement_status == SellerBillSettlementStatus::GOING) { // 计算中
                $file['link'] = '';
                $file['name'] = __('明细附件生成中', [], 'controller/bill');
            } else {
                $file['link'] = url()->to(['account/seller_bill/bill_detail/downloadDetail', 'detail_id' => $item->id]);
                $file['name'] = $res['no']['no'] . '.xls';
            }
            $files[] = $file;
        }
        if (empty($files)) {
            $files[] = [
                'name' => $this->notData,
                'link' => ''
            ];
        }

        return $files;
    }

    /**
     * 获取2期之后结算单明细对应文件
     *
     * @param $inventoryId tb_special_service_fee.inventory_id
     * @param $menuId tb_special_service_fee.annexl_menu_id
     * @param $fileMenuId tb_seller_bill_detail.file_menu_id
     * @return array
     */
    public function getDetailFiles($inventoryId, $menuId, $fileMenuId)
    {
        $fileIds = [];
        $fileList = [];

        if ($inventoryId) { // 存在操作库存
            $inventory = SellerInventoryAdjust::where('inventory_id', $inventoryId)->first();
            if ($inventory && ($inventory->apply_file_menu_id || $inventory->confirm_file_menu_id)) {
                if ($inventory->apply_file_menu_id) {
                    $fileIds[] = $inventory->apply_file_menu_id;
                }
                if ($inventory->confirm_file_menu_id) {
                    $fileIds[] = $inventory->apply_file_menu_id;
                }

            }
        } else {
            $menuId = $menuId ? $menuId : ($fileMenuId ? $fileMenuId : '');
            if ($menuId) {
                $fileIds[] = $menuId;
            }
        }
        if ($fileIds) {
            $fileList = app(SettlementRepository::class)->getFileList($fileIds)->toArray();
        }

        return $fileList;
    }

    /**
     * 单件运费|单件打包费
     *
     * @param SellerBillDetail $item 结算单详情
     * @param int $type 类型：1运费 2打包费
     * @return string
     */
    private function formatFreightOrPackageFee($item, $type = 1)
    {
        if (empty($this->typeArr[$item->type])) {
            return $this->notData;
        }
        if ($type == 1) {
            $price = $item->freight;
        } else {
            $price = $item->package_fee;
        }

        if (in_array($this->typeArr[$item->type], SellerBillTypeCode::getShowFrightZeroTypes())) { // 固定显示0.00
            return $this->currency->formatCurrencyPrice(0, $this->currencyUnit);
        }
        if (in_array($this->typeArr[$item->type], SellerBillTypeCode::getShowFrightTypes()) && $item->quantity) { // 计算得出: 价格/数量
            $singlePrice = bcdiv($price, $item->quantity, 2);
            return $this->currency->formatCurrencyPrice($singlePrice, $this->currencyUnit);
        }
        if ($this->typeArr[$item->type] == SellerBillTypeCode::V3_RMA_REFUND) { // 直接输出对应运费或打包费
            return $this->currency->formatCurrencyPrice($price, $this->currencyUnit);
        }

        return $this->notData;
    }

    /**
     * 数量
     *
     * @param SellerBillDetail $item 结算单详情
     * @return string
     */
    private function formatQuantity($item)
    {
        if (empty($this->typeArr[$item->type])) {
            return $this->notData;
        }
        if (in_array($this->typeArr[$item->type], SellerBillTypeCode::getShowQuantityTypes()) && $item->quantity) {
            return $item->quantity;
        }

        return $this->notData;
    }

    /**
     * Item Code
     *
     * @param SellerBillDetail $item 结算单详情
     * @return string
     */
    private function formatItemCode($item)
    {
        $itemCode = $item->item_code ? $item->item_code : $this->notData;

        // 返点交易支出 其Item Code 需要展示该协议对应的关联的产品明细 Item Code
        if (isset($this->typeArr[$item->type]) && $this->typeArr[$item->type] == SellerBillTypeCode::V3_COMPLEX_REBATE && $item->rebate_id) {
            $products = RebateAgreementItem::query()->alias('ari')
                ->leftJoin('oc_product as p', 'ari.product_id', 'p.product_id')
                ->where('ari.agreement_id', $item->rebate_id)
                ->pluck('p.sku');
            if ($products->isNotEmpty()) {
                $itemCode = implode(',', $products->toArray());
            }
        }

        return $itemCode;
    }

    /**
     * 单件货值
     *
     * @param SellerBillDetail $item 结算单详情
     * @return string
     */
    private function formatProductValue($item)
    {
        if (empty($this->typeArr[$item->type])) {
            return $this->notData;
        }
        if (in_array($this->typeArr[$item->type], SellerBillTypeCode::getProductPriceTotalTypes())) {
            return $this->currency->formatCurrencyPrice($item->total, $this->currencyUnit);
        }
        // (总金额-运费-服务费-打包费)/数量
        if (in_array($this->typeArr[$item->type], SellerBillTypeCode::getProductPriceTypes()) && $item->quantity) {
            $singlePrice = bcdiv(($item->total - $item->freight - $item->service_fee - $item->package_fee), $item->quantity, 2);
            return $this->currency->formatCurrencyPrice($singlePrice, $this->currencyUnit);
        }
        // 退返品返金： 总金额-运费-服务
        if ($this->typeArr[$item->type] == SellerBillTypeCode::V3_RMA_REFUND) {
            $price = $item->total - $item->freight - $item->service_fee - $item->package_fee;
            return $this->currency->formatCurrencyPrice($price, $this->currencyUnit);
        }

        return $this->notData;
    }

    /**
     * 费用编号
     *
     * @param SellerBillDetail $item 结算单详情
     * @return array
     */
    private function formatSettlementNo($item)
    {
        $res = [
            'no' => $this->notData,
            'no_desc' => '',
            'link' => '',
            'type' => '',
        ];

        if (empty($this->typeArr[$item->type])) {
            return $res;
        }
        if (in_array($this->typeArr[$item->type], SellerBillTypeCode::getShowNoTypes())) {
            $res['no'] = $item->order_id;
            $res['type'] = $item->getAttribute('delivery_type');
            $res['link'] = url()->to(['account/customerpartner/orderinfo', 'order_id' => $item->order_id]);
            if (in_array($this->typeArr[$item->type], [SellerBillTypeCode::V3_NORMAL_ORDER, SellerBillTypeCode::V3_SPOT_PRICE]) && $item->order_id) {
                //$res['link'] = url()->to(['account/customerpartner/orderinfo', 'order_id' => $item->order_id]);
                if ($this->typeArr[$item->type] == SellerBillTypeCode::V3_SPOT_PRICE && $item->product_id) { // 议价获取对应协议ID
                    $quote = ProductQuote::where('product_id', $item->product_id)
                        ->where('order_id', $item->order_id)
                        ->value('agreement_no');
                    $quote && $res['no_desc'] = $quote;
                }
            } elseif ($this->typeArr[$item->type] == SellerBillTypeCode::V3_REBATE && $item->rebate_id) {
                $res['no_desc'] = $item->getAttribute('rebate_no');
                //$res['link'] = url()->to(['account/product_quotes/rebates_contract/rebatesAgreementList', 'agreement_id' => $item->rebate_id]);
            } elseif (in_array($this->typeArr[$item->type], [SellerBillTypeCode::V3_MARGIN_DEPOSIT, SellerBillTypeCode::V3_MARGIN_TAIL, SellerBillTypeCode::V3_FUTURE_TO_MARGIN_DEPOSIT]) && $item->agreement_id) {
                $res['no_desc'] = $item->agreement_id;
                //$res['link'] = url()->to(['account/product_quotes/margin_contract/view', 'agreement_id' => $item->agreement_id]);
            } elseif (in_array($this->typeArr[$item->type], [SellerBillTypeCode::V3_FUTURE_DEPOSIT, SellerBillTypeCode::V3_FUTURE_TAIL]) && $item->future_margin_id) {
                $res['no_desc'] = $item->future_agreement_no;
                //$res['link'] = url()->to(['account/product_quotes/futures/sellerBidDetail', 'id' => $item->future_margin_id]);
            }
        } elseif ($this->typeArr[$item->type] == SellerBillTypeCode::V3_REVENUE_PROMOTION_DETAIL &&$item->getAttribute('fee_number')) {
            $res['no'] = $item->getAttribute('fee_number');
        } elseif ($this->typeArr[$item->type] == SellerBillTypeCode::V3_COMPLEX_REBATE && $item->rebate_id) {
            $res['no'] = $item->getAttribute('rebate_no');
            $res['link'] = url()->to(['account/product_quotes/rebates_contract/rebatesAgreementList', 'agreement_id' => $item->rebate_id]);
        } elseif (in_array($this->typeArr[$item->type], [SellerBillTypeCode::V3_COMPLEX_FUTURE, SellerBillTypeCode::V3_COMPLEX_MARGIN])) {
            // 期货保证金支出|现货保证金支出 - 费用编号 1.如果是后台录入，则展示手动输入的No 2.如果是自动生成，为合约则展示合约NO，为协议则展示协议NO
            if ($item->special_id) { // 如果存在special_id则表明这是后台手动录入，取录入的费用编号
                $res['no'] = $item->getAttribute('fee_number');
            } else {
                if ($item->future_contract_id) {
                    $res['no'] = $item->getAttribute('contract_no');
                    $res['link'] = url()->to(['account/customerpartner/future/contract/tab', 'id' => $item->future_contract_id]);
                } elseif ($item->future_margin_id) {
                    $res['no'] = $item->future_agreement_no;
                    $res['link'] = url()->to(['account/product_quotes/futures/sellerBidDetail', 'id' => $item->future_margin_id]);
                } elseif ($item->agreement_id) {
                    $res['no'] = $item->agreement_id;
                    $res['link'] = url()->to(['account/product_quotes/margin_contract/view', 'agreement_id' => $item->agreement_id]);
                }
            }
        } elseif ($this->typeArr[$item->type] == SellerBillTypeCode::V3_PAYMENT_PROMOTION_DETAIL && $item->order_id) { // 促销活动支出
            $res['no'] = $item->order_id;
            $res['no_desc'] = $item->campaign_id;
            $res['link'] = url()->to(['account/customerpartner/orderinfo', 'order_id' => $item->order_id]);
        } elseif ($this->typeArr[$item->type] == SellerBillTypeCode::V3_RMA_REFUND && $item->rma_id) {
            $res['no'] = $item->getAttribute('rma_no');
            $res['link'] = url()->to(['account/customerpartner/rma_management/rmaInfo', 'rmaId' => $item->rma_id]);
        } elseif ($item->getAttribute('fee_number')) {
            $res['no'] = $item->getAttribute('fee_number');
        }

        return $res;
    }

    /**
     * 格式化收款账户信息
     *
     * @param int $accountType 收款账户类型
     * @param string $account 账户信息字符串
     * @return string
     */
    public function formatAccountInfo($accountType, $account)
    {
        if ($accountType == SellerAccountInfoAccountType::P_CARD) { // P卡
            $postfix = substr($account, strpos($account, '@'));
            $length = utf8_strlen($account) - utf8_strlen($postfix);
            $length_pro = min($length, 4);
            $res = substr($account, 0, $length_pro) . '****' . $postfix;
        } elseif (in_array($accountType, [SellerAccountInfoAccountType::PUBLIC, SellerAccountInfoAccountType::PRIVATE])) { // 对公账户|对私账户
            $res = '**** **** ' . mb_substr($account, -4);
        } else {
            $res = '**** **** ****';
        }

        return $res;
    }

    /**
     * 格式化冻结列表
     *
     * @param SellerBillFrozenRelease[]|Collection $data
     * @return array
     */
    public function formatFrozenList($data)
    {
        $result = [];
        if ($data->isNotEmpty()) {
            foreach ($data as $item) {
                $res['release_time'] = $item->release_time->setTimezone(CountryHelper::getTimezone(customer()->getCountryId()))->toDateTimeString();
                $res['release_qty'] = $item->release_qty;
                $res['release_type'] = LangHelper::isChinese() ? $item->getAttribute('cn_name') : $item->getAttribute('en_name');
                $res['release_amount_format'] = $this->currency->formatCurrencyPrice($item->release_amount,  $this->currencyUnit);

                $result[] = $res;
            }
        }

        return $result;
    }
}
