<?php

namespace App\Models\Seller\SellerStore\HomeModuleJson;

use App\Models\Product\Product;
use App\Repositories\Product\ProductInfo\BaseInfo;
use App\Repositories\Product\ProductInfo\ProductPriceRangeFactory;
use App\Repositories\Product\ProductRepository;
use Illuminate\Validation\Rule;

class ModuleProductRank extends BaseModule implements SellerBasedModuleInterface
{
    use SellerBasedModuleTrait;

    public $title_show = 1;
    public $title_sub = '';
    public $count = 6;
    public $display_value = [
        'item_code' => 1,
        'price' => 1,
        'qty_available' => 1,
        'complex_transaction' => 1,
        'sales_ranking' => 1,
        'cart_tip' => 1,
    ];
    private $customer_id;

    public function __construct()
    {
        $this->customer_id = (int)customer()->getId();
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        return [
            'title_show' => 'required|boolean',
            'title_sub' => 'nullable|string',
            'count' => ['required', Rule::in([3, 6, 9])],
            'display_value' => 'required|array',
            'display_value.item_code' => 'required|boolean',
            'display_value.price' => 'required|boolean',
            'display_value.qty_available' => 'required|boolean',
            'display_value.complex_transaction' => 'required|boolean',
            'display_value.sales_ranking' => 'required|boolean',
            'display_value.cart_tip' => 'required|boolean',
        ];
    }

    /**
     * @inheritDoc
     */
    public function getDBData(): array
    {
        return [
            'title_show' => $this->title_show,
            'title_sub' => $this->title_sub,
            'count' => $this->count,
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

        $productIds = Product::query()
            ->alias('p')
            ->leftJoin('oc_product_to_category as p2c', 'p2c.product_id', '=', 'p.product_id')
            ->leftJoin('tb_sys_product_sales as sps', 'sps.product_id', '=', 'p.product_id')
            ->leftjoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->where([
                'ctp.customer_id' => $this->getSellerId(),
                'p.is_deleted' => 0,
                'p.status' => 1,
                'p.buyer_flag' => 1,
                'p.product_type' => 0,
            ])
            ->orderByDesc('sps.quantity_30')
            ->orderBy('p.product_id')
            ->groupBy(['p.product_id'])
            ->limit($this->count)
            ->pluck('p.product_id')
            ->toArray();
        /** @var BaseInfo[] $baseInfos */
        /** @var ProductPriceRangeFactory $productPriceFactory */
        [$baseInfos, $productPriceFactory] = app(ProductRepository::class)
            ->getProductBaseInfosAndPriceRanges($productIds, $this->customer_id, [
                'withProductTags' => true,
                'withUnavailable' => $this->isUnavailableProductMark(),
                'withProductComplexTag' => true,
            ]);
        $products = [];
        foreach ($baseInfos as $baseInfo) {
            $temp = [
                'id' => $baseInfo->id,
                'image' => $baseInfo->getImage(390, 390),
                'name' => $baseInfo->getName(),
            ];
            if ($this->isUnavailableProductMark()) {
                $temp['available'] = (int)$baseInfo->getIsAvailable();
            }
            if ($this->display_value['item_code']) {
                $temp['sku'] = $baseInfo->sku;
                $temp['tags'] = $baseInfo->getShowTags();
            }
            if ($this->display_value['price']) {
                $temp['price'] = $baseInfo->getShowPrice($productPriceFactory->getRangeById($baseInfo->id), ['symbolSmall' => true]);
            }
            if ($this->display_value['qty_available']) {
                $temp['qty'] = $baseInfo->getShowQty(['overMax' => 100,]);
            }
            if ($this->display_value['complex_transaction']) {
                $temp['complex_transaction'] = $baseInfo->getComplexTags();
            }
            $products[] = $temp;
        }

        $this->_viewData = [
            'products' => $products,
            'title_show' => $this->title_show,
            'title_sub' => $this->title_show ? $this->title_sub : '',
            'count' => $this->count,
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
