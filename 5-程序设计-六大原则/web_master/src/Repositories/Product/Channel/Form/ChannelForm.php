<?php

namespace App\Repositories\Product\Channel\Form;

use App\Enums\Product\Channel\ChannelType;
use App\Helper\CountryHelper;
use App\Helper\CurrencyHelper;
use App\Models\Futures\FuturesContract;
use App\Repositories\Product\Channel\ChannelRepository;
use App\Repositories\Product\Channel\Module\BestSellers;
use App\Repositories\Product\Channel\Module\DropPrice;
use App\Repositories\Product\Channel\Module\NewArrivals;
use App\Repositories\Product\Channel\Traits\ProductChannelMergeTrait;
use Framework\Exception\Exception;
use Framework\Model\RequestForm\RequestForm;
use ModelExtensionModuleProductHome;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

class ChannelForm extends RequestForm
{
    use ProductChannelMergeTrait;

    public $category_id;
    public $type;
    public $page;
    private $search_flag = false;
    private $customer_id = null;

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        return [
            'category_id' => ['nullable', 'int'],
            'type' => ['required', function ($attribute, $value, $fail) {
                if (!in_array(strtolower($value), ChannelType::getValues())) {
                    $fail($attribute . ' 不存在');
                }

                if (CountryHelper::getCountryByCode(session()->get('country')) != AMERICAN_COUNTRY_ID
                    && strtolower($value) == ChannelType::DROP_PRICE) {
                    $fail(ChannelType::DROP_PRICE . ' 不存在');
                }
            }],
            'page' => ['nullable', 'int'],
        ];
    }

    /**
     * 获取产品channel的产品信息
     * @return array
     * @throws Exception|Throwable
     */
    public function getData(): array
    {
        if ($this->type === ChannelType::COMING_SOON) {
            return $this->getComingSoonBaseData();
        }
        return $this->getBaseData();
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    private function getComingSoonBaseData(): array
    {
        if (!$this->isValidated()) {
            // 校验不通过返回错误信息
            throw new Exception($this->getFirstError());
        }
        $this->type = strtolower($this->type);
        $module = ChannelType::getChanelModelByValue($this->type);
        $param = [
            'category_id' => $this->category_id,
            'type' => $this->type,
            'search_flag' => $this->isSearchFlag(),
            'page' => $this->page ?? 1,
        ];
        // 获取当前频道所有模块的数据
        $data = $module->getChannelData($param);
        $productIds = $this->productChannelMergeProductIds($data);
        if ($productIds) {
            /** @var ModelExtensionModuleProductHome $model */
            $model = load()->model('extension/module/product_home');
            // 获取当前频道所有的产品
            $productInfos = $model->getHomeProductInfo($productIds, Customer()->getId());
            foreach ($productInfos as $key => $product) {
                $productId = $product['product_id'];
                $futures = FuturesContract::query()
                    ->where('product_id', $productId)
                    ->where('is_deleted', 0)
                    ->where('status', 1)
                    ->orderBy('delivery_date')
                    ->first();
                $productInfos[$key]['futures_delivery_date'] = $futures
                    ? $futures->delivery_date->setTimezone(CountryHelper::getTimezoneByCode(session('country')))->format('Y-m-d')
                    : null;
                if ($futures) {
                    $marginPrice = $futures->margin_unit_price;
                    $lastUnitPrice = $futures->last_unit_price;
                    if ($futures->delivery_type == 3) {
                        if ($marginPrice < $lastUnitPrice) {
                            $productInfos[$key]['futures_price_min'] = $marginPrice;
                            $productInfos[$key]['futures_price_max'] = $lastUnitPrice;
                        } else {
                            $productInfos[$key]['futures_price_min'] = $lastUnitPrice;
                            $productInfos[$key]['futures_price_max'] = $marginPrice;
                        }
                    } elseif ($futures->delivery_type == 2) {
                        $productInfos[$key]['futures_price_min'] = $marginPrice;
                        $productInfos[$key]['futures_price_max'] = $marginPrice;
                    } else {
                        $productInfos[$key]['futures_price_min'] = $lastUnitPrice;
                        $productInfos[$key]['futures_price_max'] = $lastUnitPrice;
                    }
                    $productInfos[$key]['futures_price_min_show'] = CurrencyHelper::formatPrice(
                        (float)$productInfos[$key]['futures_price_min'], ['currency' => session('currency')]
                    );
                    $productInfos[$key]['futures_price_max_show'] = CurrencyHelper::formatPrice(
                        (float)$productInfos[$key]['futures_price_max'], ['currency' => session('currency')]
                    );
                }
            }
            $productInfos = array_column($productInfos, null, 'product_id');
            $data = $this->setProductChannelProductInfos($data, $productInfos);
        }
        if ($this->isSearchFlag()) {
            // 需要返回json
            return array_merge($this->setProductChannelTwigData($data), ['category_id' => $this->category_id]);
        } else {
            // twig 渲染
            // 路由中含有category id需要返回
            return [$data, $param];
        }
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    private function getBaseData(): array
    {
        if (!$this->isValidated()) {
            // 校验不通过返回错误信息
            throw new Exception($this->getFirstError());
        }
        $this->type = strtolower($this->type);
        $module = ChannelType::getChanelModelByValue($this->type);
        $param = [
            'category_id' => $this->category_id,
            'type' => $this->type,
            'search_flag' => $this->isSearchFlag(),
            'page' => $this->page ?? 1,
        ];
        // 获取当前频道所有模块的数据
        $data = $module->getChannelData($param);
        $productIds = $this->productChannelMergeProductIds($data);
        if ($productIds) {
            /** @var ModelExtensionModuleProductHome $model */
            $model = load()->model('extension/module/product_home');
            // 获取当前频道所有的产品
            $productInfos = $model->getHomeProductInfo($productIds, Customer()->getId());
            $productInfos = array_column($productInfos, null, 'product_id');
            $data = $this->setProductChannelProductInfos($data, $productInfos);
        }
        if ($this->isSearchFlag()) {
            // 需要返回json
            return array_merge($this->setProductChannelTwigData($data), ['category_id' => $this->category_id]);
        } else {
            // twig 渲染
            // 路由中含有category id需要返回
            return [$data, $param];
        }
    }

    /**
     * 根据channel不同返回当前channel的分类
     * @return array
     * @throws \Exception|InvalidArgumentException
     */
    public function getCategoryByType(): array
    {
        if (!$this->isValidated()) {
            // 校验不通过返回错误信息
            throw new Exception($this->getFirstError());
        }
        $countryCode = session('country');
        $categories = [];
        switch ($this->type) {
            case ChannelType::NEW_ARRIVALS:
                $results = app(NewArrivals::class)->getNewArrivalsCategory();
                $categories = app(ChannelRepository::class)->getChannelCategory($results, $this->type);
                break;
            case ChannelType::BEST_SELLERS:
                $results = app(BestSellers::class)->getBestSellersCategory();
                $categories = app(ChannelRepository::class)->getChannelCategory($results, $this->type);
                break;
            case ChannelType::DROP_PRICE:
                if (in_array($countryCode, ['USA'])) {
                    $results = app(DropPrice::class)->getDropPriceCategory();
                    $categories = app(ChannelRepository::class)->getChannelCategory($results, $this->type);
                }
                break;
            case ChannelType::WELL_STOCKED:
                $categories = app(ChannelRepository::class)
                    ->wellStockedCategory(
                        (int)customer()->getId(),
                        (int)CountryHelper::getCountryByCode(session('country')),
                        ChannelType::WELL_STOCKED
                    );
                break;
            case ChannelType::COMING_SOON:
                $categories = app(ChannelRepository::class)
                    ->comingSoonCategory(
                        (int)customer()->getId(),
                        (int)CountryHelper::getCountryByCode(session('country')),
                        ChannelType::COMING_SOON
                    );
                break;
        }

        return $categories;
    }

    protected function getAutoLoadRequestData(): array
    {
        return $this->request->attributes->all();
    }

    /**
     * @return bool
     */
    public function isSearchFlag(): bool
    {
        return $this->search_flag;
    }

    /**
     * @param bool $search_flag
     */
    public function setSearchFlag(bool $search_flag): void
    {
        $this->search_flag = $search_flag;
    }
}
