<?php

namespace Catalog\model\filter;

use Framework\App;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * Trait WishListFilter
 * @property Builder $builder
 * @property \ModelCommonCategory $model_common_category
 */
trait WishListFilter
{
    use BaseFilter;

    public function item_code($item_code)
    {
        return $this->builder->where(function ($query) use ($item_code) {
            $query->where('op.sku', 'like', "%{$item_code}%")
                ->orWhere('op.mpn', 'like', "%{$item_code}%");
        });
    }

    public function product_name($product_name)
    {
        return $this->builder->where(function ($query) use ($product_name) {
            $query->where('opd.name', 'like', '%' . $product_name . '%');
        });
    }

    public function category_id($category_id)
    {
        App::load()->model('common/category');
        $categories = $this->model_common_category->getSonCategories($category_id);
        $categories = array_unique(array_merge(array_column($categories, 'category_id'), (array)$category_id));
        return $this->builder->whereExists(function ($query) use ($categories) {
            $query->select('product_id')
                ->from('oc_product_to_category')
                ->whereRaw('product_id=cw.product_id')
                ->whereIn('category_id', $categories);
        });
    }

    public function change_price($change_price)
    {
        return $this->builder
            ->leftJoin('oc_seller_price as sp', function (JoinClause $q) {
                $q->on('sp.product_id', '=', 'op.product_id')
                    ->where(['sp.status' => 1]);
            })
            ->whereRaw(
                <<<SQL
CASE
when dm.price is not null and dm.effective_time > now() then dm.price > dm.current_price
when dm.price is null and  sp.new_price is not null and sp.effect_time > now() then sp.new_price > op.price
when dm.price is not null and dm.expiration_time < now() and  sp.new_price is not null and sp.effect_time > now() then sp.new_price > op.price
END
SQL
            );
    }

    public function group_id($group_id)
    {
        return $this->builder->where(function ($query) use ($group_id) {
            $query->where('cw.group_id', $group_id);
        });
    }

    public function over_sized($over_sized)
    {
        return $this->builder->whereExists(function ($query) {
            $query->select('product_id')
                ->from('oc_product_to_tag')
                ->whereRaw('product_id=cw.product_id')
                ->where('tag_id', 1);
        });
    }

    public function seller_id($sellerId)
    {
        return $this->builder->where(function ($query) use ($sellerId) {
            $query->where('ctp.customer_id', $sellerId);
        });
    }
}
