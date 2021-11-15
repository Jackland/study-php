<?php

use Framework\App;
use Illuminate\Support\Collection;

/**
 * Class ModelCommonCategory
 */
class ModelCommonCategory extends Model
{

    /**
     * 获取商品类型以及它的父级
     * @param int|null $category_id 商品类型id
     * @return array
     */
    public function getParentCategories(?int $category_id)
    {
        static $temp_cats = [];
        if (!isset($temp_cats[$category_id])) {
            $arr = [];
            $this->getCategoryInfoByCategoryId($category_id, $arr);
            $temp_cats[$category_id] = $arr;
        }
        return $temp_cats[$category_id];
    }

    /**
     * 获取商品类型以及它的子级
     * @param int|null $category_id 商品类型id
     * @return array
     */
    public function getSonCategories(?int $category_id)
    {
        static $temp_cats = [];
        if (!isset($temp_cats[$category_id])) {
            $arr = [];
            $this->getSonCategoriesByCategoryId($category_id, $arr);
            $temp_cats[$category_id] = $arr;
        }
        return $temp_cats[$category_id];
    }

    /**
     * @param int|null $categoryId
     * @param array $init
     */
    private function getCategoryInfoByCategoryId(?int $categoryId, array &$init = [])
    {
        if ($categoryId === 0 || !$categoryId) return;
        $categoryList = $this->getCategories();
        if ($categoryList->has($categoryId)) {
            $tempArr = $categoryList->get($categoryId);
            array_unshift($init, $tempArr);
            $this->getCategoryInfoByCategoryId($tempArr['parent_id'], $init);
        }
    }

    /**
     * @param int|null $categoryId
     * @param array $init
     */
    private function getSonCategoriesByCategoryId(?int $categoryId, array &$init = [])
    {
        $categoryId = (int)$categoryId;
        $categoryList = $this->getCategories();
        if ($categoryId !== 0 && !$categoryList->has($categoryId)) {
            return;
        }
        if ($categoryList->has($categoryId)) {
            array_push($init, $categoryList->get($categoryId));
        }
        $categoryList->each(function ($item) use ($categoryId, &$init) {
            if ($item['parent_id'] == $categoryId) {
                $this->getSonCategoriesByCategoryId($item['category_id'], $init);
            }
        });
    }

    /**
     * @return Collection
     */
    private function getCategories()
    {
        static $categoryList = null;
        static $flag = false;
        if (!$flag) {
            $categoryList = App::orm()
                ->table('oc_category as c')
                ->leftJoin('oc_category_description as cd', 'c.category_id', '=', 'cd.category_id')
                ->select(['c.parent_id', 'cd.name', 'c.category_id'])
                ->get()
                ->keyBy('category_id')
                ->map(function ($item) {
                    $item = get_object_vars($item);
                    $item['name'] = html_entity_decode($item['name']);
                    return $item;
                });
            $flag = true;
        }

        return $categoryList;
    }

}
