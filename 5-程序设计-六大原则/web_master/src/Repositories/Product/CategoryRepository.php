<?php

namespace App\Repositories\Product;

use App\Enums\Product\CategoryStatus;
use App\Models\Link\ProductToCategory;
use App\Models\Product\Category;
use App\Models\Product\CategoryDescription;
use App\Models\Product\Product;

class CategoryRepository
{
    /**
     * 获取全路径的分类名称
     * @param int $categoryId
     * @param string $implode
     * @return string AAA > BBB > CC
     */
    public function getFullPathName(int $categoryId, $implode = ' > '): string
    {
        $ids = $this->getFullPathIds($categoryId);
        $idNames = $this->getNameByIds($ids);
        return implode($implode, array_filter($idNames));
    }

    /**
     * 获取全路径的分类id
     * @param int $categoryId
     * @return array [祖先id, 父id, $categoryId]
     */
    public function getFullPathIds(int $categoryId): array
    {
        $category = Category::query()->where('status', 1)->where('is_deleted', 0)->find($categoryId);
        if (!$category) {
            return [];
        }
        if ($category->category_level == 1) {
            return [$category->category_id];
        }
        if ($category->category_level == 2) {
            return [$category->parent_id, $category->category_id];
        }
        $parentIds = $this->getFullPathIds($category->parent_id);
        return array_merge($parentIds, [$category->category_id]);
    }

    /**
     * 根据 id 获取分类的名称
     * @param array $ids
     * @return array [$id => $categoryName]
     */
    public function getNameByIds(array $ids): array
    {
        $data = CategoryDescription::query()
            ->select(['category_id', 'name'])
            ->where('language_id', 1)
            ->whereIn('category_id', $ids)
            ->pluck('name', 'category_id')->toArray();
        $result = [];
        foreach ($ids as $id) {
            $result[$id] = $data[$id] ?? '';
        }
        return $result;
    }

    /**
     * 获取指定分类的最大层级的分类
     * @param array $categoryIds
     * @param int $maxCategoryLevel 最大层级
     * @return array [$categoryId => 最大为指定层级的分类的Id]
     */
    public function getMaxLevelCategoryIds(array $categoryIds, int $maxCategoryLevel)
    {
        $categories = Category::query()
            ->whereIn('category_id', $categoryIds)
            ->where('status', CategoryStatus::USED)
            ->where('is_deleted', 0)
            ->orderBy('category_level') // 从低到高
            ->get()
            ->keyBy('category_id');

        $result = [];
        $needFetchParents = [];
        foreach ($categoryIds as $categoryId) {
            if (!isset($categories[$categoryId])) {
                $result[$categoryId] = 0; // 用0表示分类不存在
                continue;
            }
            $category = $categories[$categoryId];
            if ($category->category_level <= $maxCategoryLevel) {
                $result[$categoryId] = $category->category_id;
                continue;
            }
            if ($category->category_level - 1 == $maxCategoryLevel) {
                $result[$categoryId] = $category->parent_id;
                continue;
            }
            $needFetchParents[$category->category_id] = $category->parent_id;
        }
        if ($needFetchParents) {
            $subResult = $this->getMaxLevelCategoryIds(array_unique(array_values($needFetchParents)), $maxCategoryLevel);
            foreach ($needFetchParents as $categoryId => $parentId) {
                if (isset($subResult[$parentId])) {
                    $result[$categoryId] = $subResult[$parentId];
                }
            }
        }

        return $result;
    }

    /**
     * 获取某个分类所有上级分类&组织数据
     * @param int $categoryId
     * @param array $array
     * @param bool $limitStatus 是否限制状态，默认是，限制状态则不会返回已经禁用的分类
     * @return array|object
     */
    public function getUpperCategory($categoryId, $array = [], $limitStatus = true)
    {
        $tmpCategoryName = $tmpCategoryIds = '';
        $categoryList = $this->getUpperCategoryList($categoryId, $array);
        if ($categoryList){
            $categoryList = array_reverse($categoryList);
            $tmpCategoryIds = $tmpCategoryName = '';
            foreach ($categoryList as $key => $item) {
                if ($limitStatus && $item['status'] == CategoryStatus::DISABLE) {
                    // 有一个被禁用，整个全部设置成空并跳出循环
                    $tmpCategoryIds = $tmpCategoryName = '';
                    break;
                }
                if (count($categoryList) == $key + 1) {
                    $tmpCategoryName .= html_entity_decode($item['name']);
                    $tmpCategoryIds .= $item['category_id'];
                } else {
                    $tmpCategoryName .= html_entity_decode($item['name']) . '>>';
                    $tmpCategoryIds .= $item['category_id'] . ',';
                }
            }
        }
        $result['category_name'] = $tmpCategoryName;
        $result['category_ids'] = $tmpCategoryIds;
        return $result;
    }

    /**
     * 获取某个分类所有上级分类
     * ps:注意，该方法不过滤已经禁用的分类，如需过滤，在外层自行过滤,会返回status字段
     * @param int $categoryId
     * @param array $array
     * @return array
     */
    public function getUpperCategoryList($categoryId, $array = [])
    {
        $result = Category::query()->alias('c')
            ->leftJoinRelations('description as d')
            ->where('c.category_id', $categoryId)
            ->select(['c.category_id', 'c.parent_id', 'd.name','c.status'])
            ->first();
        if (!$result) {
            return [];
        }
        $result = $result->toArray();
        $array[] = $result;
        if (isset($result['parent_id']) && $result['parent_id']) {
            return $this->getUpperCategoryList($result['parent_id'], $array);
        }
        return $array;
    }

    /**
     * 获取某几个分类中，最后一级的category_id   写此方法原因:不能保证系统的分类顺序没有被调整过
     * @param array $categoryIds
     * @return int
     */
    public function getLastLowerCategoryId($categoryIds)
    {
        if (empty($categoryIds)) {
            return 0;
        }
        $parentIds = Category::whereIn('category_id', $categoryIds)
            ->pluck('parent_id')
            ->toArray();
        $returnCategoryId = max($categoryIds); //容错
        foreach ($categoryIds as $categoryId) {
            if (!in_array($categoryId, $parentIds)) {
                $returnCategoryId = $categoryId;
                break;
            }
        }
        return max($returnCategoryId, 0);
    }


    /**
     * 根据商品id获取商品所属目录
     *
     * @param int|Product|null $productId
     * @return array
     */
    public function getCategoryByProductId($productId): array
    {
        if (!$productId) return [];
        // 通过product id 获取 category id
        $product = is_object($productId) ? $productId : Product::find($productId);
        $category = $product->categories->pluck('category_id');
        $category = $category->map(function ($cId) {
            $temp = [];
            $arrId = [];
            $this->getCategoryInfoByCategoryId($cId, $temp, $arrId);
            array_walk($temp, function (&$item) {
                $item = html_entity_decode($item['name']) ?? '';
            });
            array_walk($arrId, function (&$ite) {
                $ite = $ite['category_id'] ?? '';
            });

            return ['value' => $cId, 'arr_label' => $temp, 'count' => count($temp), 'arr_id' => $arrId];
        });

        return $category->toArray();
    }

    /**
     * @param int|null $categoryId
     * @param array $init
     * @param array $arrId
     */
    protected function getCategoryInfoByCategoryId(?int $categoryId, array &$init = [], array &$arrId = [])
    {
        if ($categoryId === 0 || !$categoryId) return;
        static $categoryList = [];
        static $flag = false;
        if (!$flag) {
            $categoryList = Category::query()->alias('c')
                ->leftJoinRelations('description as cd')
                ->select(['c.parent_id', 'cd.name', 'c.category_id'])
                ->get()
                ->keyBy('category_id')
                ->toArray();
            $flag = true;
        }
        if (isset($categoryList[$categoryId])) {
            $tempArr = $categoryList[$categoryId];
            array_unshift($init, $tempArr);
            array_unshift($arrId, $tempArr);
            $this->getCategoryInfoByCategoryId($tempArr['parent_id'], $init, $arrId);
        }
    }

    public function getCategoryByParentCategoryId($parentCategoryId = 0,$limitCategoryIds = [])
    {
        return Category::query()
            ->alias('c')
            ->leftJoinRelations('description as cd')
            ->where('cd.language_id', '=', configDB('config_language_id'))
            ->where('c.parent_id', '=', $parentCategoryId)
            ->where('status', '=', 1)
            ->where('c.is_deleted', '=', 0)
            ->when($limitCategoryIds, function ($query) use ($limitCategoryIds) {
                $query->whereIn('c.category_id', $limitCategoryIds);
            })
            ->get(['c.category_id', 'cd.name'])
            ->toArray();
    }

    //获取所有类目  多维数组
    public function getAllCategory()
    {
        $allCategoty = Category::query()->alias('c')
            ->leftJoinRelations('description as cd')
            ->select(['c.parent_id', 'cd.name', 'c.category_id'])
            ->where('c.status', 1)
            ->where('c.is_deleted', 0)
            // ->orderBy('c.sort_order')
            // ->orderBy('c.category_id')
            ->get()
            ->keyBy('category_id')
            ->map(function ($item) {
                $item->name = trim(html_entity_decode(trim($item->name)));
                return $item;
            })
            ->toArray();

        $treeCategory = $this->beautifyCategoryList($allCategoty, 0, 1);

        foreach ($treeCategory as &$item) {
            if (empty($item['child'])) {
                $item['first_merge_count'] = 1;
                continue;
            }
            $item['can_show_category'] = 0;
            $firstMergeNumber = 0;
            foreach ($item['child'] as &$item2) {
                $firstMergeNumber += $item2['child'] ? count($item2['child']) : 1;
                $item2['second_merge_count'] = $item2['child'] ? count($item2['child']) : 1;
                if ($item2['child']){
                    $item2['can_show_category'] = 0;
                }
            }
            $item['first_merge_count'] = $firstMergeNumber;
        }

        return $treeCategory;
    }

    /**
     * 校验categoryIds是否合法，新增or编辑产品模块使用，新增or编辑时候只能选中层级最小的categoryId
     * @param array $categoryIds
     * @return array
     */
    public function calculateValidCategoryIds(array $categoryIds)
    {
        $categoryIds = array_filter($categoryIds);

        if (empty($categoryIds)) {
            return [];
        }

        $currentCategoryIds = Category::query()
            ->whereIn('category_id', $categoryIds)
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->pluck('category_id')
            ->toArray();

        if (empty($currentCategoryIds)) {
            return [];
        }

        $invalidCategoryInfos = Category::query()
            ->whereIn('parent_id', $currentCategoryIds)
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->get()
            ->keyBy('parent_id')
            ->toArray();

        if (empty($invalidCategoryInfos)) {
            return $currentCategoryIds;
        }

        return array_diff($currentCategoryIds, array_keys($invalidCategoryInfos)) ?: [];
    }

    private function beautifyCategoryList($list, $parentId = 0, $level = 1)
    {
        if (empty($list)) return [];

        $result = [];

        foreach ($list as $l_val) {
            if ($l_val['parent_id'] == $parentId) {
                $l_val['level'] = $level;
                $l_val['can_show_category'] = 1;

                $result[$l_val['category_id']] = $l_val;
                $result[$l_val['category_id']]['child'] = isset($result[$l_val['category_id']]['child']) ?
                    array_merge($result[$l_val['category_id']]['child'], $this->beautifyCategoryList($list, $l_val['category_id'], $level + 1)) :
                    $this->beautifyCategoryList($list, $l_val['category_id'], $level + 1);
            }
        }

        return $result;
    }

}
