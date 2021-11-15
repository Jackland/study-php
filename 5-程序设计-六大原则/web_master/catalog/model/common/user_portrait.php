<?php


/**
 * Class ModelCommonUserPortrait
 * add zjg
 * 2019年12月19日
 * 获取用户画像信息
 * table : oc_buyer_user_portrait
 */
class ModelCommonUserPortrait extends Model{

    /*
     * 获取用户画像信息
     */
    public function get_user_portrait($customer_id){
        $select=['buyer_id','monthly_sales_count','return_rate','complex_complete_rate','first_order_date','registration_date', 'main_category_id'];
        $res=$this->orm->table(DB_PREFIX.'buyer_user_portrait')->where('buyer_id',$customer_id)->select($select)->limit(1)->get();
        return obj2array($res);
    }

    /**
     * 获取buyer 状态
     */
    public function get_buyer_status($buyer_id){
        $res=$this->orm->table(DB_PREFIX.'customer')->where('customer_id',$buyer_id)->select(['status'])->limit(1)->get();
        return obj2array($res);
    }

    public function getPortraitCategoryName($categoryId)
    {
        $category = $this->orm->table(DB_PREFIX . 'category as c')
            ->join(DB_PREFIX . 'category_description as cd', 'c.category_id', '=', 'cd.category_id')
            ->where('c.category_id', $categoryId)
            ->first();

        $names = [$category->name];

        if ($category->parent_id != 0) {
            $names = array_merge($this->getPortraitCategoryName($category->parent_id), $names);
        }

        return $names;
    }

}
