<?php

namespace App\Models\Seller\SellerStore\HomeModuleJson;

use App\Repositories\Buyer\BuyerToSellerRepository;
use App\Repositories\Product\ProductInfo\BaseInfo;
use App\Repositories\Product\ProductInfo\ComplexTransaction\AbsComplexTransactionRepository;
use App\Repositories\Product\ProductInfo\ComplexTransaction\FuturesInfo;
use App\Repositories\Product\ProductInfo\ComplexTransaction\FuturesInfoRepository;
use App\Repositories\Product\ProductInfo\ComplexTransaction\MarginInfo;
use App\Repositories\Product\ProductInfo\ComplexTransaction\MarginInfoRepository;
use App\Repositories\Product\ProductInfo\ComplexTransaction\RebateInfo;
use App\Repositories\Product\ProductInfo\ComplexTransaction\RebateInfoRepository;
use App\Repositories\Product\ProductInfo\ProductInfoFactory;
use Framework\Exception\NotSupportException;

class ModuleComplexTransaction extends BaseModule implements SellerBasedModuleInterface
{
    use SellerBasedModuleTrait;

    public $rebate = [];
    public $margin = [];
    public $future = [];
    public $title_show = 1;
    public $display_value = [
        'product_name' => 1,
        'item_code' => 1,
        'price' => 1,
        'template_info' => 1,
        'buy_now' => 1,
        'rebate' => 1, // 优惠（返点）幅度
    ];

    /**
     * @inheritDoc
     */
    public function getRules(): array
    {
        $dynamicRequired = $this->isFullValidate() ? 'required' : 'nullable';
        if ($this->isFullValidate()) {
            $rules['rebate'] = 'required_without:margin,future|array';
            $rules['margin'] = 'required_without:rebate,future|array';
            $rules['future'] = 'required_without:rebate,margin|array';
        } else {
            $rules['rebate'] = 'array';
            $rules['margin'] = 'array';
            $rules['future'] = 'array';
        }
        return [
            'rebate' => $rules['rebate'],
            'rebate.products.*.id' => 'required|integer',
            'margin' => $rules['margin'],
            'margin.products.*.id' => 'required|integer',
            'future' => $rules['future'],
            'future.products.*.id' => 'required|integer',
            'title_show' => [$dynamicRequired, 'boolean'],
            'display_value' => [$dynamicRequired, 'array'],
            'display_value.product_name' => 'required|boolean',
            'display_value.item_code' => 'required|boolean',
            'display_value.price' => 'required|boolean',
            'display_value.template_info' => 'required|boolean',
            'display_value.buy_now' => 'required|boolean',
            'display_value.rebate' => 'required|boolean',
            'rebate.products' => [$this->validateProductIsAvailableOnce()],
            'margin.products' => [$this->validateProductIsAvailableOnce()],
            'future.products' => [$this->validateProductIsAvailableOnce()],
        ];
    }


    /**
     * @inheritDoc
     */
    public function getDBData(): array
    {
        $ret = [];
        // 存入数据库时不判断产品的上下架状态
        if (isset($this->rebate['products'])) {
            $rebateTemp = [];
            foreach ($this->rebate['products'] as $item) {
                $rebateTemp[] = [
                    'id' => $item['id'],
                ];
            }
            $ret['rebate'] = ['products' => $rebateTemp];
        }

        if (isset($this->margin['products'])) {
            $marginTemp = [];
            foreach ($this->margin['products'] as $item) {
                $marginTemp[] = [
                    'id' => $item['id'],
                ];
            }
            $ret['margin'] = ['products' => $marginTemp];
        }

        if (isset($this->future['products'])) {
            $futureTemp = [];
            foreach ($this->future['products'] as $item) {
                $futureTemp[] = [
                    'id' => $item['id'],
                ];
            }
            $ret['future'] = ['products' => $futureTemp];
        }

        $ret['title_show'] = $this->title_show;
        $ret['display_value'] = $this->display_value;
        return $ret;
    }

    private $_viewData;
    private $_isBuyerSellerConnected = false; // buyer 和 seller 是否建立联系

    /**
     * @inheritDoc
     */
    public function getViewData(): array
    {
        if ($this->_viewData) {
            return $this->_viewData;
        }

        $customerId = customer()->getId();
        if ($customerId) {
            if ($customerId == $this->getSellerId()) {
                $this->_isBuyerSellerConnected = true;
            } else {
                $this->_isBuyerSellerConnected = app(BuyerToSellerRepository::class)->isConnected($this->getSellerId(), $customerId);
            }
        }
        $collection = collect([]);
        $rebateProductIds = [];
        $marginProductIds = [];
        $futureProductIds = [];

        if (isset($this->rebate['products'])) {
            $rebateProductIds = collect($this->rebate['products'])->pluck('id')->toArray();
            $collection = $collection->merge($rebateProductIds);
        }

        if (isset($this->margin['products'])) {
            $marginProductIds = collect($this->margin['products'])->pluck('id')->toArray();
            $collection = $collection->merge($marginProductIds);
        }

        if (isset($this->future['products'])) {
            $futureProductIds = collect($this->future['products'])->pluck('id')->toArray();
            $collection = $collection->merge($futureProductIds);
        }

        $allProductsIds = $collection->all();
        // 获取产品的基础信息
        $productInfoFactory = new ProductInfoFactory();
        $repository = $productInfoFactory->withIds($allProductsIds)->getBaseInfoRepository()
            ->withCustomerId($customerId);
        // unavailable 是否需要展示
        if ($this->isUnavailableProductMark()) {
            $repository = $repository->withUnavailable();
        }
        $baseInfos = $repository->getInfos();

        $this->_viewData = [
            'rebate' => ['products' => $this->getRebateData($baseInfos, $rebateProductIds)],
            'margin' => ['products' => $this->getMarginData($baseInfos, $marginProductIds)],
            'future' => ['products' => $this->getFutureData($baseInfos, $futureProductIds)],
            'title_show' => $this->title_show,
            'display_value' => $this->display_value,
        ];
        return $this->_viewData;
    }

    /**
     * @inheritDoc
     */
    public function canShowForBuyer(array $dbData): bool
    {
        if (!parent::canShowForBuyer($dbData)) {
            return false;
        }

        $viewData = $this->getViewData();
        if (count($viewData['rebate']['products']) > 0) {
            return true;
        }
        if (count($viewData['margin']['products']) > 0) {
            return true;
        }
        if (count($viewData['future']['products']) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @param array|BaseInfo[] $baseInfos
     * @param array $productIds
     * @return array
     */
    private function getRebateData(array $baseInfos, array $productIds): array
    {
        return $this->getComplexProductData(
            $baseInfos,
            $productIds,
            RebateInfoRepository::class,
            function (RebateInfo $complexInfo, BaseInfo $baseInfo, array $config) {
                $product = [
                    'day' => $complexInfo->day,
                    'template_qty' => $complexInfo->qty,
                    'template_price' => $baseInfo->getShowPrice([$complexInfo->template_price, $complexInfo->template_price], [
                        'forceInvisible' => !$this->_isBuyerSellerConnected
                    ]),
                ];
                if ($config['rebate']) {
                    $product['discount'] = floor($complexInfo->getDiscount() * 100);
                }
                return $product;
            }
        );
    }

    /**
     * @param array|BaseInfo[] $baseInfos
     * @param array $productIds
     * @return array
     */
    private function getMarginData(array $baseInfos, array $productIds): array
    {
        return $this->getComplexProductData(
            $baseInfos,
            $productIds,
            MarginInfoRepository::class,
            function (MarginInfo $complexInfo, BaseInfo $baseInfo) {
                return [
                    'day' => $complexInfo->day,
                    'max_num' => $complexInfo->max_num,
                    'min_num' => $complexInfo->min_num,
                    'template_price' => $baseInfo->getShowPrice([$complexInfo->template_price, $complexInfo->template_price], [
                        'forceInvisible' => !$this->_isBuyerSellerConnected
                    ]),
                ];
            }
        );
    }

    /**
     * @param array|BaseInfo[] $baseInfos
     * @param array $productIds
     * @return array
     */
    private function getFutureData(array $baseInfos, array $productIds): array
    {
        return $this->getComplexProductData(
            $baseInfos,
            $productIds,
            FuturesInfoRepository::class,
            function (FuturesInfo $complexInfo, BaseInfo $baseInfo) {
                $templatePrice = $complexInfo->getContractPrice();
                return [
                    'delivery_date' => $complexInfo->delivery_date->format('Y-m-d'),
                    'num' => $complexInfo->num,
                    'min_num' => $complexInfo->min_num,
                    'template_price' => $baseInfo->getShowPrice([$templatePrice, $templatePrice], [
                        'forceInvisible' => !$this->_isBuyerSellerConnected
                    ]),
                ];
            }
        );
    }

    /**
     * @param array|BaseInfo[] $baseInfos
     * @param array $productIds
     * @param string|AbsComplexTransactionRepository $complexTransactionClass
     * @param callable $buildTemplateInfoCB
     * @return array
     * @throws NotSupportException
     */
    private function getComplexProductData(array $baseInfos, array $productIds, string $complexTransactionClass, callable $buildTemplateInfoCB): array
    {
        if (!$productIds) {
            return [];
        }
        $displayValue = collect($this->display_value);
        $productsResult = [];

        $complexInfos = (new ProductInfoFactory())
            ->withIds($productIds)
            ->buildComplexTransactionRepository($complexTransactionClass)
            ->getInfos();
        foreach ($productIds as $productId) {
            if (!array_key_exists($productId, $baseInfos)) {
                // 无基本信息
                continue;
            }
            $baseInfo = $baseInfos[$productId];
            if ($this->isUnavailableProductRemove()
                && (
                    !array_key_exists($productId, $complexInfos) // 非复杂交易或失效
                    || !$baseInfo->getIsAvailable() // 产品不可用
                )
            ) {
                // 不可用的移除
                continue;
            }

            if ($complexTransactionClass === RebateInfoRepository::class) {
                $type = 'rebate';
            } elseif ($complexTransactionClass === MarginInfoRepository::class) {
                $type = 'margin';
            } elseif ($complexTransactionClass === FuturesInfoRepository::class) {
                $type = 'future';
            } else {
                throw new NotSupportException('$complexTransactionClass 错误: ' . $complexTransactionClass);
            }

            $product = [
                'id' => $productId,
                'type' => $type,
            ];
            if ($this->isUnavailableProductMark()) {
                $product['available'] = (int)$baseInfo->getIsAvailable();
                if ($product['available'] && !array_key_exists($productId, $complexInfos)) {
                    // 产品可用，但复杂交易失效
                    $product['notApplicable'] = 1;
                }
            }
            $product['image'] = $baseInfo->getImage(280, 280);
            $product['qty'] = $baseInfo->getShowQty();
            $product['mpn'] = $baseInfo->mpn;
            $sellerEditInfo = []; // 当 seller 编辑时有些隐藏的字段影响到编辑列表的展示，因此将需要的字段放到额外的字段中
            if ($displayValue->get('product_name')) {
                $product['name'] = $baseInfo->name;
            } elseif ($this->isSellerEdit()) {
                $sellerEditInfo['name'] = $baseInfo->name;
            }
            if ($displayValue->get('item_code')) {
                $product['item_code'] = $baseInfo->sku;
                $product['tags'] = $baseInfo->getShowTags();
            } elseif ($this->isSellerEdit()) {
                $sellerEditInfo['item_code'] = $baseInfo->sku;
                $sellerEditInfo['tags'] = $baseInfo->getShowTags();
            }
            if ($displayValue->get('buy_now')) {
                $product['buy_now'] = 1;
            }
            if ($displayValue->get('price')) {
                $product['price'] = $baseInfo->getShowPrice();
            } elseif ($this->isSellerEdit()) {
                $sellerEditInfo['price'] = $baseInfo->getShowPrice();
            }
            if ($this->isSellerEdit()) {
                $product['seller_edit_info'] = $sellerEditInfo;
            }
            if (array_key_exists($productId, $complexInfos) && ($displayValue->get('template_info'))) {
                $complexInfo = $complexInfos[$productId];
                $product = array_merge($product, call_user_func($buildTemplateInfoCB, $complexInfo, $baseInfo, [
                    'rebate' => $displayValue->get('rebate'),
                ]));
            }

            $productsResult[] = $product;
        }

        return $productsResult;
    }

    private $isProductAvailableValidated = false;

    /**
     * 检验复杂交易和产品有效性，多次调用仅校验一次
     * @return \Closure
     */
    protected function validateProductIsAvailableOnce()
    {
        return function ($attribute, $value, $fail) {
            if ($this->isProductAvailableValidated) {
                return;
            }
            $this->isProductAvailableValidated = true;
            if (!$this->shouldValidateProductAvailable()) {
                return;
            }

            $productIdCollection = collect([
                'rebate' => [],
                'margin' => [],
                'future' => [],
            ]);
            if (isset($this->rebate['products'])) {
                $productIdCollection['rebate'] = array_column($this->rebate['products'], 'id');
            }
            if (isset($this->margin['products'])) {
                $productIdCollection['margin'] = array_column($this->margin['products'], 'id');
            }
            if (isset($this->future['products'])) {
                $productIdCollection['future'] = array_column($this->future['products'], 'id');
            }
            $allProductIds = $productIdCollection->flatten()->unique()->all();
            if (!$allProductIds) {
                return;
            }
            $factory = (new ProductInfoFactory())
                ->withIds($allProductIds);
            $baseInfos = $factory->getBaseInfoRepository()
                ->withUnavailable()
                ->getInfos();

            $msgArr = [];
            // 产品失效检查
            $unavailableSkus = [];
            foreach ($baseInfos as $baseInfo) {
                if (!$baseInfo->getIsAvailable()) {
                    // 产品不可用
                    $unavailableSkus[] = $baseInfo->sku;
                }
            }
            if ($unavailableSkus) {
                $msgArr[] = __choice('产品:sku已失效，请移除这些产品！', count($unavailableSkus), [
                    'sku' => implode(', ', $unavailableSkus)
                ], 'controller/seller_store');
            }
            // 复杂交易失效检查
            foreach ($productIdCollection as $type => $productIds) {
                if ($type === 'rebate') {
                    $infos = $factory->getRebateInfoRepository()->getInfos();
                } elseif ($type === 'margin') {
                    $infos = $factory->getMarginInfoRepository()->getInfos();
                } elseif ($type === 'future') {
                    $infos = $factory->getFuturesInfoRepository()->getInfos();
                } else {
                    throw new NotSupportException('不支持的 type 类型：' . $type);
                }
                $unavailableSkus = [];
                foreach ($productIds as $productId) {
                    // 复杂交易失效
                    if (!array_key_exists($productId, $infos)) {
                        $unavailableSkus[] = $baseInfos[$productId]->sku;
                    }
                }
                if ($unavailableSkus) {
                    if ($type === 'rebate') {
                        $msgArr[] = __choice('产品:sku返点合约已失效，请移除这些产品！', count($unavailableSkus), [
                            'sku' => implode(', ', $unavailableSkus)
                        ], 'controller/seller_store');
                    } elseif ($type === 'margin') {
                        $msgArr[] = __choice('产品:sku现货合约已失效，请移除这些产品！', count($unavailableSkus), [
                            'sku' => implode(', ', $unavailableSkus)
                        ], 'controller/seller_store');
                    } elseif ($type === 'future') {
                        $msgArr[] = __choice('产品:sku期货合约已失效，请移除这些产品！', count($unavailableSkus), [
                            'sku' => implode(', ', $unavailableSkus)
                        ], 'controller/seller_store');
                    }
                }
            }

            if ($msgArr) {
                $fail(implode('<br/>', $msgArr));
            }
        };
    }
}
