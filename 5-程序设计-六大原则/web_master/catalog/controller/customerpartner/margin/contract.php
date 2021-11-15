<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Catalog\Forms\Margin\ContractForm;
use App\Catalog\Search\Margin\ContractSearch;
use App\Components\Storage\StorageCloud;
use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductTransactionType;
use App\Models\Margin\MarginContract;
use App\Models\Product\Product;
use App\Repositories\Margin\ContractRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Seller\SellerRepository;
use App\Services\Margin\ContractService;
use Carbon\Carbon;
use Framework\Exception\InvalidConfigException;
use Symfony\Component\HttpFoundation\JsonResponse;

class ControllerCustomerpartnerMarginContract extends AuthSellerController
{
    protected $data = [];

    protected $sellerId;

    protected $currencyCode;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        $this->sellerId = intval(customer()->getId());
        $this->currencyCode = session()->get('currency');
    }

    /**
     * 合约列表
     * @return string
     * @throws InvalidConfigException
     */
    public function index()
    {
        // 货币小数位数
        $precision = $this->currency->getDecimalPlace($this->currencyCode);

        $search = new ContractSearch($this->sellerId);
        $dataProvider = $search->search($this->request->query->all());
        $contracts = $dataProvider->getList();

        $productIds = $contracts->pluck('product_id')->toArray();
        $productIdInfoMap = app(ProductRepository::class)->getProductsMapIncludeTagsByIds($productIds);

        $contractRepository = app(ContractRepository::class);
        foreach ($contracts as $contract) {
            /** @var MarginContract $contract */
            $contract->product_info = $productIdInfoMap[$contract->product_id] ?? [];

            [$minContractAmount, $maxContractAmount] = $contractRepository->getContractMinAndMaxAmount($contract, $precision);
            $contract->min_contract_amount = $minContractAmount;
            $contract->max_contract_amount = $maxContractAmount;
        }

        $this->data['total'] = $dataProvider->getTotalCount();
        $this->data['contracts'] = $contracts;
        $this->data['paginator'] = $dataProvider->getPaginator();
        $this->data['sort'] = $dataProvider->getSort();
        $this->data['search'] = $search->getSearchData();
        $this->data['currency'] = $this->currencyCode;

        return $this->render('customerpartner/margin/contracts', $this->data, 'seller');
    }

    /**
     * 下载
     */
    public function download()
    {
        $search = new ContractSearch($this->sellerId);
        $dataProvider = $search->search($this->request->query->all(), true);
        $contracts = $dataProvider->getList();

        $head= ['Contract ID', 'Item Code', 'MPN', 'Deposit Percentage', 'Contract Days', 'Minimum Selling Quantity', 'Maximum Selling Quantity', 'Contract Price', 'Margin Amount', 'Last Modified'];

        // 货币小数位数
        $precision = $this->currency->getDecimalPlace($this->currencyCode);

        $data = [];
        foreach ($contracts as $contract) {
            /** @var MarginContract $contract */
            foreach ($contract->templates as $template) {
                $minAmount = $this->currency->format(round($template->price * $contract->payment_ratio * 0.01, $precision) * $template->min_num, $this->currencyCode);
                $maxAmount = $this->currency->format(round($template->price * $contract->payment_ratio * 0.01, $precision) * $template->max_num, $this->currencyCode);

                $data[] = [
                    "\t" . $contract->contract_no,
                    "\t" . $contract->sku,
                    "\t" . $contract->mpn,
                    $contract->payment_ratio . '%',
                    $contract->day,
                    $template->min_num,
                    $template->max_num,
                    $this->currency->format(round($template->price, $precision), $this->currencyCode),
                    $template->min_num == $template->max_num ? $minAmount : $minAmount . ' - ' . $maxAmount,
                    $contract->update_time->toDateTimeString(),
                ];
            }
        }

        //输出
        $fileName = 'marginofferings_' . date('Ymd', time()) . '.csv';
        outputCsv($fileName, $head, $data, $this->session);
    }

    /**
     * 删除合约(支持批量)
     * @return JsonResponse
     */
    public function del()
    {
        $ids = request()->input->get('ids');
        if (empty($ids)) {
            return $this->jsonSuccess();
        }

        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $result = app(ContractService::class)->delContractsByIds($this->sellerId, $ids);
        if (!empty($result)) {
            return $this->jsonFailed($result);
        }

        return $this->jsonSuccess();
    }

    /**
     * 产品信息
     * @return JsonResponse
     */
    public function products()
    {
        $products = app(ProductRepository::class)->getSellerValidSkusIncludeTags($this->sellerId, request('mpn_sku'));

        return $this->jsonSuccess(['products' => $products]);
    }

    /**
     * 配置
     * @return JsonResponse
     */
    public function setting()
    {
        return $this->jsonSuccess(app(ContractRepository::class)->getContractSettings(customer()->isJapan()));
    }

    /**
     * 添加或者合约页面
     * @return string
     */
    public function save()
    {
        $productId = request('product_id', '');
        $contractId = request('id', '');

        $this->data['no_prefix'] = Carbon::now()->format('Ymd');
        $this->data['currency_code'] = $this->currencyCode;
        $this->data['is_japan'] = customer()->isJapan();

        // 是否显示云送仓提醒
        $this->data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();
        // 是否显示价格报警普通提醒
        $this->data['is_show_notice'] = !(customer()->isInnerAccount());

        $this->data['settings'] = app(ContractRepository::class)->getContractSettings(customer()->isJapan());

        $this->data['currency_symbol_left'] = $this->currency->getSymbolLeft($this->currencyCode);
        $this->data['currency_symbol_right'] = $this->currency->getSymbolRight($this->currencyCode);
        $this->data['information_id'] = 131;
        $this->data['information_title'] = db('oc_information_description')->where('information_id', 131)->value('title');

        if ($productId && $contractId) {
            $contract = MarginContract::query()->find($contractId);
            if (empty($contract) || $contract->is_deleted == YesNoEnum::YES || $contract->status != 1 || $contract->product_id != $productId || $contract->customer_id != $this->sellerId) {
                // Onsite Seller 在新建现货协议需要有对应的弹窗提示
                $this->data['is_onsite_seller'] = $this->customer->isGigaOnsiteSeller();
                return $this->render('customerpartner/margin/add_contract', $this->data, 'seller');
            }

            $this->data['product_id'] = $productId;
            return $this->render('customerpartner/margin/edit_contract', $this->data, 'seller');
        } else {
            // Onsite Seller 在新建现货协议需要有对应的弹窗提示
            $this->data['is_onsite_seller'] = $this->customer->isGigaOnsiteSeller();

            $this->data['no_prefix'] = Carbon::now()->format('Ymd');
            return $this->render('customerpartner/margin/add_contract', $this->data, 'seller');
        }
    }

    /**
     * 处理保存合约
     * @param ContractForm $contractForm
     * @return JsonResponse
     */
    public function handleSave(ContractForm $contractForm)
    {
        $result = $contractForm->save();

        return $this->json($result);
    }

    /**
     * 获取合约
     * @return JsonResponse
     */
    public function info()
    {
        // 货币小数位数
        $precision = $this->currency->getDecimalPlace($this->currencyCode);

        $contractRepository = app(ContractRepository::class);
        $contract = $contractRepository->getContractByProductId($this->sellerId, intval(request('product_id')));
        if (!empty($contract)) {
            [$minContractAmount, $maxContractAmount] = $contractRepository->getContractMinAndMaxAmount($contract, $precision);
            $contract->min_contract_amount = $minContractAmount;
            $contract->max_contract_amount = $maxContractAmount;
        }

        return $this->jsonSuccess(['contract' => $contract]);
    }

    /**
     * 获取产品详情
     * @param ModelCommonProduct $modelCommonProduct
     * @return string
     * @throws Exception
     */
    public function productDetail(ModelCommonProduct $modelCommonProduct)
    {
        $productId = request()->get('product_id', 0);
        $product = app(ProductRepository::class)->getProductInfoIncludeAttributeAndTags($this->sellerId,$productId);
        if (!($product instanceof Product)) {
            return $this->render('customerpartner/margin/common/product_detail', []);
        }

        // 获取产品的报警价格
        $alarmPrice = $modelCommonProduct->getAlarmPrice($productId, true, $product->toArray());

        // 货币小数位数
        $precision = $this->currency->getDecimalPlace($this->currencyCode);

        // 产品半年的价格
        $productHistoricalPriceResult = app(ProductRepository::class)->getProductHistoricalPrices($productId, customer()->isUSA(), $precision);

        // 当前所有价格
        $productCurrentPriceResult = app(ProductRepository::class)->getProductCurrentPrices($product, $precision, [ProductTransactionType::NORMAL, ProductTransactionType::REBATE, ProductTransactionType::FUTURE, ProductTransactionType::SPOT]);

        $productInfo = [
            'product_id' => $productId,
            'image' => StorageCloud::image()->getUrl($product->image, [
                'w' => 60,
                'h' => 60,
            ]),
            'item_code' => $product->sku,
            'mpn' => $product->mpn,
            'attribute' => join(' + ', $product->attributes),
            'quantity' => $product->quantity,
            'alarm_price' => $alarmPrice,
            'tags' => $product->tags,
            'isDeCountry' => $this->customer->getCountryId() == DE_COUNTRY_ID ? true : false, #31737 是否为DE
        ];

        return $this->render('customerpartner/margin/common/product_detail', $productInfo + $productHistoricalPriceResult + $productCurrentPriceResult);
    }
}
