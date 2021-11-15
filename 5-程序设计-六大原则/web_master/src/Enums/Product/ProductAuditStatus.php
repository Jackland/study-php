<?php

namespace App\Enums\Product;


use Framework\Enum\BaseEnum;

class ProductAuditStatus extends BaseEnum
{
    //审核状态
    const PENDING = 1;//审核中
    const APPROVED = 2;//审核通过
    const NOT_APPROVED = 3;//审核不通过
    const CANCEL = 4;//取消

    public static function getViewItems()
    {
        return [
            self::PENDING => __('审核中', [], 'catalog/view/customerpartner/product/lists_index'),//Request Pending
            self::APPROVED => __('审核通过', [], 'catalog/view/customerpartner/product/lists_index'),//Request Approved
            self::NOT_APPROVED => __('审核不通过', [], 'catalog/view/customerpartner/product/lists_index'),//Request Declined
            self::CANCEL => __('已取消', [], 'catalog/view/customerpartner/product/lists_index'),//Cancel
        ];
    }
}
