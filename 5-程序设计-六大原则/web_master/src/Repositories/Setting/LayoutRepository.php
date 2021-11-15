<?php

namespace App\Repositories\Setting;

use App\Components\Traits\RequestCachedDataTrait;
use Framework\Http\Request;
use ModelCatalogCategory;
use ModelCatalogInformation;
use ModelCatalogProduct;
use ModelDesignLayout;
use ModelSettingModule;

class LayoutRepository
{
    use RequestCachedDataTrait;

    public function getLayoutIdByRequest(Request $request)
    {
        $cacheKey = [__CLASS__, __FUNCTION__, $request, 'v1'];
        $data = $this->getRequestCachedData($cacheKey);
        if ($data !== null) {
            return $data;
        }

        $route = $request->get('route', 'common/home');
        $layoutId = 0;

        if ($route == 'product/category' && ($request->get('path') || $request->get('category_id'))) {
            /** @var ModelCatalogCategory $modelCatalogCategory */
            $modelCatalogCategory = load()->model('catalog/category');
            $categoryId = $request->get('category_id');
            if (!$categoryId) {
                $categoryIds = explode('_', (string)$request->get('path'));
                $categoryId = end($categoryIds);
            }
            if ($categoryId) {
                $layoutId = $modelCatalogCategory->getCategoryLayoutId($categoryId);
            }
        }

        if ($route == 'product/product' && $request->get('product_id')) {
            /** @var ModelCatalogProduct $modelCatalogProduct */
            $modelCatalogProduct = load()->model('catalog/product');
            $layoutId = $modelCatalogProduct->getProductLayoutId($request->get('product_id'));
        }

        if ($route == 'information/information' && $request->get('information_id')) {
            /** @var ModelCatalogInformation $modelCatalogInformation */
            $modelCatalogInformation = load()->model('catalog/information');
            $layoutId = $modelCatalogInformation->getInformationLayoutId($request->get('information_id'));
        }

        /** @var ModelDesignLayout $modelDesignLayout */
        $modelDesignLayout = load()->model('design/layout');
        if (!$layoutId) {
            $layoutId = $modelDesignLayout->getLayout($route);
        }

        if (!$layoutId) {
            $layoutId = configDB('config_layout_id');
        }

        $this->setRequestCachedData($cacheKey, $layoutId);

        return $layoutId;
    }

    public function getModules($layoutId, $position)
    {
        /** @var ModelDesignLayout $modelDesignLayout */
        $modelDesignLayout = load()->model('design/layout');
        return $modelDesignLayout->getLayoutModules($layoutId, $position);
    }

    public function loadModules(array $modules)
    {
        $result = [];

        /** @var ModelSettingModule $modelSettingModule */
        $modelSettingModule = load()->model('setting/module');
        foreach ($modules as $module) {
            $part = explode('.', $module['code']);

            if (isset($part[0]) && configDB('module_' . $part[0] . '_status')) {
                $content = load()->controller('extension/module/' . $part[0]);
                if ($content) {
                    $result[] = $content;
                }
            }

            if (isset($part[1])) {
                $setting = $modelSettingModule->getModule($part[1]);
                if ($setting && $setting['status']) {
                    $content = load()->controller('extension/module/' . $part[0], $setting);
                    if ($content) {
                        $result[] = $content;
                    }
                }
            }
        }

        return $result;
    }
}
