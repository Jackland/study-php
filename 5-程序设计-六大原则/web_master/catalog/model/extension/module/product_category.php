<?php

use App\Components\Storage\StorageCloud;
use App\Enums\Common\YesNoEnum;

/**
 * Class ModelExtensionModuleProductCategory
 *
 */
class ModelExtensionModuleProductCategory extends Model
{
    const CATEGORY_LEVEL_0 = 0;
    const CATEGORY_LEVEL_1 = 1;
    const CATEGORY_LEVEL_2 = 2;
    const CATEGORY_LEVEL_3 = 3;
    const CATEGORY_LIMIT = 8;

    /**
     * [getDefaultCategoryList description] 需要严格保证给出的category level必须是对的，这是首要前提
     */
    public function getDefaultCategoryList()
    {
        $categories = [];
        $origin = $this->getCategoryInfo();
        $items = $this->sortCategory($origin);
        $items = array_column($items, null, 'self_id');
        foreach ($items as $k => $item) {
            if (isset($items[$item['parent_id']])) {
                $items[$item['parent_id']]['children'][] = &$items[$k];
            } else {
                $categories[] = &$items[$k];
            }
        }
        $otherMore = [];
        $mainCategories = [];
        if (count($categories) > self::CATEGORY_LIMIT) {
            foreach ($categories as $key => $value) {
                if ($key >= self::CATEGORY_LIMIT) {
                    $otherMore[] = $value;
                } else {
                    $mainCategories[] = $value;
                }
            }
        } else {
            $mainCategories = $categories;
        }
        $ret['categories'] = $mainCategories;
        $ret['otherMore'] = $otherMore;
        return $ret;
    }


    public function getRightList($product_page, $product_id_list)
    {
        $list = [];
        $info = $this->getCategoryInfo();
        if ($product_id_list) {

            $categories = $this->getProductValidCategoryId($product_id_list);
            foreach ($categories as $key => $value) {
                if (isset($info[$value]['all_pid'])) {
                    $tmp = explode('_', $info[$value]['all_pid']);
                }
                foreach ($tmp as $kk => $vv) {
                    if (isset($info[$vv])) {
                        $list[$vv] = $info[$vv];
                    }
                }
            }

        } elseif (empty($product_page) && $product_page !== null) {

            $list = [];

        } elseif ($product_page) {

            foreach ($product_page as $key => $value) {
                if (isset($info[$value])) {
                    $list[$value] = $info[$value];
                }
            }

        } else {

            $list = $info;

        }

        return $list;
    }


    /**
     * [getCategoryById description] 根据分类来获取
     * @param int $category_id
     * @param array $product_id_list
     * @param null $product_page
     * @param null $is_category
     * date:2020/11/24 13:53
     * @return array
     */
    public function getCategoryById($category_id, $product_id_list = [], $product_page = null, $is_category = false)
    {
        // seller id 为 0 时候表示全部品类
        if ($is_category) {
            $list = $this->getCategoryInfo();
        } else {
            $list = $this->getRightList($product_page, $product_id_list);
        }
        $category_level = isset($list[$category_id]['category_level']) ? $list[$category_id]['category_level'] : self::CATEGORY_LEVEL_0;
        $categories = [];
        switch ($category_level) {
            case self::CATEGORY_LEVEL_0:
                $list = $this->sortCategory($list);
                foreach ($list as $key => $value) {
                    if ($value['category_level'] == self::CATEGORY_LEVEL_1) {
                        $categories[$value['self_id']] = $value;
                    }
                }
                break;
            case self::CATEGORY_LEVEL_1:
                $categories[$category_id] = $list[$category_id];
                $list = $this->sortCategory($list);
                foreach ($list as $key => $value) {
                    $tmp = explode('_', $value['all_pid']);
                    if ($value['category_level'] == self::CATEGORY_LEVEL_2 && $tmp[0] == $category_id) {
                        $categories[$category_id]['children'][$value['self_id']] = $value;
                    }
                }
                break;
            case self::CATEGORY_LEVEL_2:
                $category_pid = $list[$category_id]['all_pid'];
                $tmp = explode('_', $category_pid);
                $categories[$tmp[0]] = $list[$tmp[0]];
                $categories[$tmp[0]]['children'][$tmp[1]] = $list[$tmp[1]];
                $list = $this->sortCategory($list);
                foreach ($list as $key => $value) {
                    $tValue = explode('_', $value['all_pid']);
                    if ($value['category_level'] == self::CATEGORY_LEVEL_3 && $category_id == $tValue[1]) {
                        $categories[$tmp[0]]['children'][$tmp[1]]['children'][$value['self_id']] = $value;
                    }
                }
                break;
            case self::CATEGORY_LEVEL_3:
                $category_pid = $list[$category_id]['all_pid'];
                $tmp = explode('_', $category_pid);
                $categories[$tmp[0]] = $list[$tmp[0]];
                $categories[$tmp[0]]['children'][$tmp[1]] = $list[$tmp[1]];
                $list = $this->sortCategory($list);
                foreach ($list as $key => $value) {
                    $tValue = explode('_', $value['all_pid']);
                    if ($value['category_level'] == self::CATEGORY_LEVEL_3 && $tmp[1] == $tValue[1]) {
                        $categories[$tmp[0]]['children'][$tmp[1]]['children'][$value['self_id']] = $value;
                    }
                }
                break;
            default;
        }
        $categories = $this->setCategoriesArray($categories);
        return $categories;
    }

    public function sortCategory($category_list)
    {
        $num1 = [];
        $num2 = [];
        foreach ($category_list as $key => $value) {
            $num1[$key] = $value ['sort_order'];
            $num2[$key] = $value ['name'];
        }
        array_multisort($num1, SORT_ASC, $num2, SORT_STRING | SORT_FLAG_CASE, $category_list);
        return $category_list;

    }

    /**
     * [setCategoriesArray description] 由于是构造数组的给出的key下标 给到前端的时候需要作出改变
     * @param $categories
     * @return array
     */
    public function setCategoriesArray($categories)
    {
        $categories = array_values($categories);
        foreach ($categories as $key => &$value) {
            foreach ($value['children'] as $ks => &$vs) {
                $vs['children'] = array_values($vs['children']);
            }
            $value['children'] = array_values($value['children']);
        }
        return $categories;
    }

    /**
     * [getCategoryInfo description]
     * @return array
     */
    public function getCategoryInfo()
    {
        $map = [
            //'c.top' => YesNoEnum::YES,
            'c.status' => YesNoEnum::YES,
            'c.is_deleted' => YesNoEnum::NO,
        ];
        $res = $this->orm->table(DB_PREFIX . 'category as c')
            ->leftJoin(DB_PREFIX . 'category_description as d', 'c.category_id', '=', 'd.category_id')
            ->where($map)
            ->select('c.category_id', 'c.parent_id', 'c.image', 'd.name', 'c.category_level', 'c.sort_order')
            ->get()
            ->map(
                function ($value) {
                    return (array)$value;
                })
            ->toArray();
        $category_list = [];
        foreach ($res as $key => $value) {
            $category_list[$value['category_id']]['parent_id'] = $value['parent_id'];
            $category_list[$value['category_id']]['name'] = $value['name'];
            //迁移oss 展示图片
            $category_list[$value['category_id']]['image'] = StorageCloud::image()->getUrl($value['image'], ['check-exist' => false]);
            $category_list[$value['category_id']]['href'] = $this->url->link('product/category', ['category_id' => $value['category_id']]);
            $category_list[$value['category_id']]['self_id'] = $value['category_id'];
            $category_list[$value['category_id']]['all_pid'] = $value['category_id'];
            $category_list[$value['category_id']]['category_level'] = $value['category_level'];
            $category_list[$value['category_id']]['sort_order'] = $value['sort_order'];
            $category_list[$value['category_id']]['children'] = [];
        }
        foreach ($category_list as $key => $value) {
            $this->dealWithCategoryData($category_list, $key, $key);
        }
        array_walk($category_list, function (&$value, $key) {
            $value['category_level'] = count(explode('_', $value['all_pid']));
        });
        return $category_list;
    }

    /**
     * [getSellerValidCategoryId description] 获取seller存在的分类
     * @param int $sellerId
     * @param bool $isValidProduct
     * @param int $buyerId
     * @return array
     */
    public function getSellerValidCategoryId($sellerId, $isValidProduct = false, $buyerId = 0)
    {
        if (!empty($buyerId)) {
            $noDisplayProductId = $this->cart->buyerNoDisplayProductIdsByBuyerIdAndSellerId($buyerId, $sellerId);
        }

        $categoryIdArrQuery = $this->orm->table('oc_customerpartner_to_product as c2p')
            ->leftJoin('oc_product_to_category as p2c', 'p2c.product_id', '=', 'c2p.product_id')
            ->where('c2p.customer_id', $sellerId)
            ->join(DB_PREFIX . 'product as op', 'c2p.product_id', '=', 'op.product_id');
        if (isset($noDisplayProductId) && !empty($noDisplayProductId)) {
            $categoryIdArrQuery->whereNotIn('c2p.product_id', $noDisplayProductId);
        }
        if ($isValidProduct) {
            $categoryIdArrQuery->where('op.buyer_flag', 1)->where('op.is_deleted', 0)->where('op.status', 1);
        }
        $categoryIdArr = $categoryIdArrQuery
            ->groupBy(['p2c.category_id'])
            ->pluck('p2c.category_id')
            ->toArray();

        $categoryArr = $this->orm->table('oc_category_path')
            ->whereIn('category_id', $categoryIdArr)
            ->get();
        $categoryArr = obj2array($categoryArr);
        $categoryIdArr = array_unique(array_merge(array_column($categoryArr, 'category_id'), array_column($categoryArr, 'path_id')));
        return $categoryIdArr;
    }

    /**
     * [dealWithCategoryData description] 处理父类id
     * @param array $category_list
     * @param $origin_key
     * @param $key
     */
    private function dealWithCategoryData(&$category_list, $origin_key, $key)
    {

        if (isset($category_list[$key]) && $category_list[$key]['parent_id'] != 0) {
            $category_list[$origin_key]['all_pid'] = $category_list[$key]['parent_id'] . '_' . $category_list[$origin_key]['all_pid'];
            $this->dealWithCategoryData($category_list, $origin_key, $category_list[$key]['parent_id']);
        }

    }

    private function getProductValidCategoryId($product_list)
    {
        return $this->orm->table('oc_product_to_category')
            ->whereIn('product_id', $product_list)
            ->groupBy('category_id')
            ->get()
            ->pluck('category_id');

    }


}
