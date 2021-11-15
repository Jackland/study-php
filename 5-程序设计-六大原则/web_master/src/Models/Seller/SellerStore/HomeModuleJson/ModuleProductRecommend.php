<?php

namespace App\Models\Seller\SellerStore\HomeModuleJson;

use App\Enums\Seller\SellerStoreHome\ModuleProductRecommendAngleTipKey;
use App\Enums\Seller\SellerStoreHome\ModuleProductRecommendTitleKey;
use App\Repositories\Product\ProductInfo\BaseInfo;
use App\Repositories\Product\ProductInfo\ProductPriceRangeFactory;
use App\Repositories\Product\ProductRepository;
use Illuminate\Validation\Rule;

class ModuleProductRecommend extends BaseModule
{
    use ValidateProductAvailableTrait;

    public $products = [];
    public $title_show = 1;
    public $title_key = ModuleProductRecommendTitleKey::NEW_ARRIVALS;
    public $angle_tip_key = ModuleProductRecommendAngleTipKey::NEW;

    private $customerId;

    public function __construct()
    {
        $this->customerId = (int)customer()->getId();
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        $dynamicRequired = $this->isFullValidate() ? 'required' : 'nullable';

        return [
            'products' => [$dynamicRequired, 'array', $this->validateProductIsAvailable(function ($value) {
                return array_column($value, 'id');
            })],
            'products.*.id' => [$dynamicRequired, 'integer'],
            'title_show' => 'required|boolean',
            'title_key' => ['required_if:title_show,1', Rule::in(ModuleProductRecommendTitleKey::getValues())],
            'angle_tip_key' => ['required', Rule::in(ModuleProductRecommendAngleTipKey::getValues())],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getDBData(): array
    {
        $pRes = [];
        foreach ($this->products as $product) {
            $pRes[] = ['id' => $product['id']];
        }

        return [
            'products' => $pRes,
            'title_show' => $this->title_show,
            'title_key' => $this->title_key,
            'angle_tip_key' => $this->angle_tip_key,
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

        $productIds = array_column($this->products, 'id');
        /** @var BaseInfo[] $baseInfos */
        /** @var ProductPriceRangeFactory $productPriceFactory */
        [$baseInfos, $productPriceFactory] = app(ProductRepository::class)
            ->getProductBaseInfosAndPriceRanges($productIds, $this->customerId, [
                'withUnavailable' => $this->isUnavailableProductMark(),
            ]);

        $pRes = [];
        foreach ($baseInfos as $baseInfo) {
            $item = [
                'id' => $baseInfo->id,
                'image' => $baseInfo->getImage(390, 390), // 固定长宽: 390 * 390
                'name' => $baseInfo->name,
                'sku' => $baseInfo->sku,
                'mpn' => $baseInfo->mpn,
                'price' => $baseInfo->getShowPrice($productPriceFactory->getRangeById($baseInfo->id), ['symbolSmall' => true]),
                'qty' => $baseInfo->getShowQty(['overMax' => 100]),
                'tags' => $baseInfo->getShowTags(),
            ];
            if ($this->isUnavailableProductMark()) {
                $item['available'] = (int)$baseInfo->getIsAvailable();
            }
            $pRes[] = $item;
        }

        $ret = [
            'products' => $pRes,
            'title_show' => $this->title_show,
            'angle_tip_key' => $this->angle_tip_key,
            'angle_tip_value' => ModuleProductRecommendAngleTipKey::getDescription($this->angle_tip_key),
            'title_key' => $this->title_key,
            'title_value' => ModuleProductRecommendTitleKey::getDescription($this->title_key),
        ];

        $this->_viewData = $ret;
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
