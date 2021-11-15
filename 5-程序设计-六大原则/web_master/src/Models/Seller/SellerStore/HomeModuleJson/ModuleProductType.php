<?php

namespace App\Models\Seller\SellerStore\HomeModuleJson;

use App\Enums\Seller\SellerStoreHome\ModuleProductTypeAutoSortType;
use App\Enums\Seller\SellerStoreHome\ModuleProductTypeMode;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Repositories\Product\ProductInfo\BaseInfo;
use App\Repositories\Product\ProductInfo\ProductInfoFactory;
use App\Repositories\Product\ProductInfo\ProductPriceRangeFactory;
use App\Repositories\Product\ProductRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use ModelCommonCategory;

class ModuleProductType extends BaseModule implements SellerBasedModuleInterface
{
    use SellerBasedModuleTrait;
    use ValidateProductAvailableTrait;

    public $mode;
    public $mode_auto = [
        'product_types' => [],
        'sort_type' => ModuleProductTypeAutoSortType::HOT_SALE,
        'each_count' => 4,
    ];
    public $mode_manual = ['product_types' => [],];

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
            'mode' => [$dynamicRequired, Rule::in(ModuleProductTypeMode::getValues())],
            // auto
            'mode_auto' => ['required_if:mode,' . ModuleProductTypeMode::AUTO, 'array'],
            'mode_auto.product_types' => ['required_if:mode,' . ModuleProductTypeMode::AUTO, 'array',],
            'mode_auto.product_types.*.type_id' => ['required_if:mode,' . ModuleProductTypeMode::AUTO, 'integer', 'min:1'],
            'mode_auto.sort_type' => [
                'required_if:mode,' . ModuleProductTypeMode::AUTO,
                Rule::in(ModuleProductTypeAutoSortType::getValues()),
            ],
            'mode_auto.each_count' => ['required_if:mode,' . ModuleProductTypeMode::AUTO, 'integer', 'min:1'],
            // manual
            'mode_manual' => ['required_if:mode,' . ModuleProductTypeMode::MANUAL, 'array'],
            'mode_manual.product_types' => [
                'required_if:mode,' . ModuleProductTypeMode::MANUAL,
                'array',
                $this->validateManualProductIsAvailable()
            ],
            'mode_manual.product_types.*.type_id' => ['required_if:mode,' . ModuleProductTypeMode::MANUAL, 'integer', 'min:1'],
            'mode_manual.product_types.*.products.*.id' => ['required_if:mode,' . ModuleProductTypeMode::MANUAL, 'integer', 'min:1']
        ];
    }

    /**
     * @inheritDoc
     */
    public function getDBData(): array
    {
        $mode = $this->mode;
        $mode_auto = [
            'product_types' => [],
            'sort_type' => ModuleProductTypeAutoSortType::HOT_SALE,
            'each_count' => 4,
        ];
        $mode_manual = ['product_types' => [],];
        // auto
        if ($mode === ModuleProductTypeMode::AUTO) {
            $typeIds = array_map(function ($item) {
                return ['type_id' => $item['type_id'],];
            }, $this->mode_auto['product_types']);
            $mode_auto = [
                'product_types' => $typeIds,
                'sort_type' => $this->mode_auto['sort_type'],
                'each_count' => $this->mode_auto['each_count'],
            ];
        }
        // manual
        if ($mode === ModuleProductTypeMode::MANUAL) {
            $types = array_map(function ($item) {
                $products = $item['products'];
                $item = ['type_id' => $item['type_id'], 'products' => []];
                foreach ($products as $val) {
                    $item['products'][] = ['id' => $val['id'],];
                }
                return $item;
            }, $this->mode_manual['product_types']);
            $mode_manual = ['product_types' => $types];
        }
        return compact('mode', 'mode_auto', 'mode_manual');
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

        // 对于auto和manual分别讨论
        if ($this->mode === ModuleProductTypeMode::AUTO) {
            $this->_viewData = $this->getAutoViewData();
        } elseif ($this->mode === ModuleProductTypeMode::MANUAL) {
            $this->_viewData = $this->getManualViewData();
        }

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
        $viewData = $viewData['mode'] === ModuleProductTypeMode::MANUAL ? $viewData['mode_manual'] : $viewData['mode_auto'];
        if (collect($viewData['product_types'])->filter(function ($item) {
                return count($item['products']) > 0;
            })->count() > 0) {
            return true;
        }

        return false;
    }

    private function getManualViewData(): array
    {
        $productTypes = $this->mode_manual['product_types'];
        $productIds = array_column(Arr::flatten(array_column($productTypes, 'products'), 1), 'id');
        $ret = [];
        /** @var BaseInfo[] $baseInfos */
        /** @var ProductPriceRangeFactory $productPriceFactory */
        [$baseInfos, $productPriceFactory] = app(ProductRepository::class)
            ->getProductBaseInfosAndPriceRanges($productIds, $this->customerId, [
                'withUnavailable' => $this->isUnavailableProductMark(),
            ]);
        $categoryIdNames = $this->getCategoryIdNames(array_column($productTypes, 'type_id'));
        foreach ($productTypes as $productType) {
            $cateProducts = $productType['products'];
            $temp = [
                'type_id' => $productType['type_id'],
                'type_name' => $categoryIdNames[$productType['type_id']] ?? '',
                'products' => [],
            ];
            foreach ($cateProducts as $cateProduct) {
                $baseInfo = $baseInfos[$cateProduct['id']] ?? null;
                if (!$baseInfo) {
                    continue;
                }
                $isInCategory = $this->isProductHasCategory($baseInfo->getCategories(), $productType['type_id']);
                if ($this->isUnavailableProductRemove() && !$isInCategory) {
                    continue;
                }
                $temp['products'][] = $this->buildProductForView(
                    $baseInfo,
                    $productPriceFactory->getRangeById($baseInfo->id),
                    $isInCategory
                );
            }
            if (count($temp['products']) <= 0) {
                // 该分类下无产品时隐藏该分类显示
                continue;
            }
            $ret[] = $temp;
        }

        return [
            'mode' => $this->mode,
            'mode_manual' => [
                'product_types' => $ret,
            ],
        ];
    }

    private function getAutoViewData(): array
    {
        $productTypes = $this->mode_auto['product_types'];
        $categoryIdNames = $this->getCategoryIdNames(array_column($productTypes, 'type_id'));
        $tRet = [];
        $productIds = [];
        foreach ($productTypes as $productType) {
            $cateProductIds = $this->getAutoProductIds($productType['type_id']);
            $temp = [
                'type_id' => $productType['type_id'], // 分类下产品为空是仍然保留该字段，因为seller编辑时需要展示
                'type_name' => $categoryIdNames[$productType['type_id']] ?? '',
                'products' => $cateProductIds, // 暂时存储产品ID，后续取明细
            ];
            $productIds = array_merge($productIds, $cateProductIds);
            $tRet[] = $temp;
        }
        // 获取产品信息
        /** @var BaseInfo[] $baseInfos */
        /** @var ProductPriceRangeFactory $productPriceFactory */
        [$baseInfos, $productPriceFactory] = app(ProductRepository::class)
            ->getProductBaseInfosAndPriceRanges($productIds, $this->customerId, [
                'withUnavailable' => $this->isUnavailableProductMark(),
            ]);
        $tRet = array_map(function ($item) use ($baseInfos, $productPriceFactory) {
            $products = [];
            foreach ($item['products'] as $productId) {
                if (!isset($baseInfos[$productId])) {
                    continue;
                }
                $baseInfo = $baseInfos[$productId];
                $products[] = $this->buildProductForView($baseInfo, $productPriceFactory->getRangeById($baseInfo->id));
            }
            $item['products'] = $products;
            return $item;
        }, $tRet);

        return [
            'mode' => $this->mode,
            'mode_auto' => [
                'product_types' => $tRet,
                'sort_type' => $this->mode_auto['sort_type'],
                'each_count' => $this->mode_auto['each_count']
            ]
        ];
    }

    /**
     * @param array $categoryIds
     * @return array [id => name]
     */
    private function getCategoryIdNames(array $categoryIds): array
    {
        return Category::query()
            ->with(['description'])
            ->whereIn('category_id', $categoryIds)
            ->get()
            ->mapWithKeys(function (Category $item) {
                return [$item->category_id => html_entity_decode($item->description->name)];
            })->toArray();
    }

    private function getAutoProductIds(int $categoryId): array
    {
        /** @var ModelCommonCategory $modelCategory */
        $modelCategory = load()->model('common/category');
        // 获取该类型对应的category产品id
        $deepCategories = $modelCategory->getSonCategories($categoryId);
        $query = Product::query()
            ->alias('p')
            ->leftJoin('oc_product_to_category as p2c', 'p2c.product_id', '=', 'p.product_id')
            ->leftjoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->where([
                'ctp.customer_id' => $this->getSellerId(),
                'p.is_deleted' => 0,
                'p.status' => 1,
                'p.buyer_flag' => 1,
                'p.product_type' => 0,
            ])
            ->whereIn('p2c.category_id', array_column($deepCategories, 'category_id'))
            ->groupBy(['p.product_id'])
            ->limit($this->mode_auto['each_count']);
        $sortType = $this->mode_auto['sort_type'];
        switch ($sortType) {
            case ModuleProductTypeAutoSortType::HOT_SALE:
                $query->leftJoin('tb_sys_product_sales as sps', 'sps.product_id', '=', 'p.product_id');
                $query->orderByDesc('sps.quantity_30');
                break;
            case ModuleProductTypeAutoSortType::NEW_ARRIVAL:
                $query->leftjoin('oc_product_exts as pe', 'p.product_id', '=', 'pe.product_id');
                $query->orderByDesc('pe.receive_date');
                break;
            case ModuleProductTypeAutoSortType::HOT_DOWNLOAD:
                $query->orderByDesc('p.downloaded');
                break;
            case ModuleProductTypeAutoSortType::PRICE_LOW:
                $query->orderBy('p.price');
                break;
            case ModuleProductTypeAutoSortType::PRICE_HIGH:
                $query->orderByDesc('p.price');
                break;
        }
        $query->orderBy('p.product_id'); // 当存在同名排序时用作二级排序
        return $query->pluck('p.product_id')->toArray();
    }

    private function buildProductForView(BaseInfo $baseInfo, $priceRange, $isProductInCategory = null): array
    {
        $item = [
            'id' => $baseInfo->id,
            'image' => $baseInfo->getImage(390, 390), // 固定长宽: 390 * 390
            'name' => $baseInfo->name,
            'sku' => $baseInfo->sku,
            'mpn' => $baseInfo->mpn,
            'price' => $baseInfo->getShowPrice($priceRange),
            'qty' => $baseInfo->getShowQty(['overMax' => 100]),
            'tags' => $baseInfo->getShowTags(),
        ];
        if ($this->isUnavailableProductMark()) {
            $item['available'] = (int)$baseInfo->getIsAvailable();
            if ($item['available'] && $isProductInCategory === false) {
                // 产品可用，但不在分类中
                $item['notApplicable'] = 1;
            }
        }
        return $item;
    }

    private $_allCategoryIdsCached = [];

    /**
     * @param Collection|Category[] $productCategories
     * @param int $categoryId
     * @return bool
     */
    private function isProductHasCategory(Collection $productCategories, int $categoryId): bool
    {
        if (!isset($this->_allCategoryIdsCached[$categoryId])) {
            /** @var ModelCommonCategory $modelCategory */
            $modelCategory = load()->model('common/category');
            $this->_allCategoryIdsCached[$categoryId] = collect($modelCategory->getSonCategories($categoryId))->pluck('category_id')->toArray();
        }

        foreach ($productCategories as $category) {
            if (in_array($category->category_id, $this->_allCategoryIdsCached[$categoryId])) {
                return true;
            }
        }
        return false;
    }

    /**
     * 校验手动设置的产品是否都可用
     * @return callable
     */
    private function validateManualProductIsAvailable(): callable
    {
        return function ($attribute, $productTypes, $fail) {
            if (!$this->shouldValidateProductAvailable()) {
                return;
            }
            $productIds = array_column(Arr::flatten(array_column($productTypes, 'products'), 1), 'id');
            $baseInfos = (new ProductInfoFactory())
                ->withIds($productIds)
                ->getBaseInfoRepository()
                ->withUnavailable()
                ->getInfos();

            $notInCategories = [];
            $unavailableInfos = [];
            foreach ($productTypes as $productType) {
                foreach ($productType['products'] as $cateProduct) {
                    $baseInfo = $baseInfos[$cateProduct['id']] ?? null;
                    if (!$baseInfo) {
                        continue;
                    }
                    if (!$baseInfo->getIsAvailable()) {
                        $unavailableInfos[] = $baseInfo->sku;
                        continue;
                    }
                    if (!$this->isProductHasCategory($baseInfo->getCategories(), $productType['type_id'])) {
                        if (!isset($notInCategories[$productType['type_id']])) {
                            $notInCategories[$productType['type_id']] = [];
                        }
                        $notInCategories[$productType['type_id']][] = $baseInfo->sku;
                    }
                }
            }

            $msgArr = [];
            if ($unavailableInfos) {
                $msgArr[] = $this->productNotAvailableMsg($unavailableInfos);
            }
            if ($notInCategories) {
                $categoryIdNames = $this->getCategoryIdNames(array_keys($notInCategories));
                foreach ($notInCategories as $categoryId => $skus) {
                    $msgArr[] = __choice('所选产品:sku，未在:category分类中，请移除这些产品！', count($skus), [
                        'sku' => implode('、', $skus),
                        'category' => $categoryIdNames[$categoryId] ?? '',
                    ], 'controller/seller_store');
                }
            }
            if ($msgArr) {
                $fail(implode('<br/>', $msgArr));
            }
        };
    }
}
