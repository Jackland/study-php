<?php

namespace App\Models\Seller\SellerStore\HomeModuleJson;

use App\Repositories\Product\ProductInfo\BaseInfo;
use App\Repositories\Product\ProductInfo\ProductPriceRangeFactory;
use App\Repositories\Product\ProductRepository;

class ModuleMainProduct extends BaseModule
{
    use ValidateProductAvailableTrait;

    public $products = [];
    public $title_show = 1;
    public $title = 'Featured Products';
    public $title_sub = '';
    public $display_value = [
        'product_name' => 1,
        'item_code' => 1,
        'complex_transaction' => 1,
        'tag' => 1,
        'price' => 1,
        'qty_available' => 1,
    ];

    private $customerId;

    public function __construct()
    {
        parent::__construct();

        $this->customerId = customer()->getId();
    }

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        $dynamicRequired = $this->isFullValidate() ? 'required' : 'nullable';

        return [
            'products' => [$dynamicRequired, 'array', $this->validateProductIsAvailable(function ($value) {
                return collect($value)->pluck('product.id')->toArray();
            })],
            'products.*.product.id' => 'required|integer',
            'products.*.tags' => 'array',
            'title_show' => [$dynamicRequired, 'boolean'],
            'title' => 'required_if:title_show,1',
            'title_sub' => 'string|nullable',
            'display_value' => [$dynamicRequired, 'array'],
            'display_value.product_name' => 'required|boolean',
            'display_value.item_code' => 'required|boolean',
            'display_value.complex_transaction' => 'required|boolean',
            'display_value.tag' => 'required|boolean',
            'display_value.price' => 'required|boolean',
            'display_value.qty_available' => 'required|boolean',
        ];
    }

    /**
     * @inheritDoc
     */
    public function getDBData(): array
    {
        $productsResult = [];
        foreach ($this->products as $product) {
            $productsResult[] = [
                'product' => ['id' => $product['product']['id']],
                'tags' => $product['tags'] ?? [],
            ];
        }

        return [
            'products' => $productsResult,
            'title_show' => $this->title_show,
            'title' => $this->title,
            'title_sub' => $this->title_sub,
            'display_value' => $this->display_value,
        ];
    }

    private $_viewData;

    /**
     * @inheritDoc
     */
    public function getViewData(): array
    {
        if ($this->_viewData) {
            return $this->_viewData;
        }

        $displayValue = collect($this->display_value);
        $products = collect($this->products)->keyBy('product.id');
        $productIds = $products->keys()->toArray();

        /** @var BaseInfo[] $baseInfos */
        /** @var ProductPriceRangeFactory $productPriceFactory */
        [$baseInfos, $productPriceFactory] = app(ProductRepository::class)
            ->getProductBaseInfosAndPriceRanges($productIds, $this->customerId, [
                'withProductComplexTag' => true,
                'withUnavailable' => $this->isUnavailableProductMark(),
            ]);

        $productsResult = [];
        foreach ($baseInfos as $productId => $baseInfo) {
            $item = [
                'id' => $baseInfo->id,
                'image' => $baseInfo->getImage(640, 640),
            ];
            if ($this->isUnavailableProductMark()) {
                $item['available'] = (int)$baseInfo->getIsAvailable();
            }
            if ($displayValue->get('product_name')) {
                $item['name'] = $baseInfo->getName();
            }
            if ($displayValue->get('item_code')) {
                $item['item_code'] = $baseInfo->sku;
                $item['tags'] = $baseInfo->getShowTags();
            }
            if ($displayValue->get('complex_transaction')) {
                $item['complex_transaction'] = $baseInfo->getComplexTags();
            }
            $tags = [];
            if ($displayValue->get('tag')) {
                $tags = $products[$productId]['tags'];
            }
            if ($displayValue->get('price')) {
                $item['price'] = $baseInfo->getShowPrice($productPriceFactory->getRangeById($productId), ['symbolSmall' => true]);
            }
            if ($displayValue->get('qty_available')) {
                $item['qty'] = $baseInfo->getShowQty(['overMax' => 100]);
            }
            $productsResult[] = [
                'product' => $item,
                'tags' => $tags,
            ];
        }

        $this->_viewData = [
            'products' => $productsResult,
            'title_show' => $this->title_show,
            'title' => $this->title_show ? $this->title : '',
            'title_sub' => $this->title_show ? $this->title_sub : '',
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
        if (count($viewData['products']) > 0) {
            return true;
        }

        return false;
    }
}
