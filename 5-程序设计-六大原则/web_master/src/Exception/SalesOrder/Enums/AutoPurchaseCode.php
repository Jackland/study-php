<?php


namespace App\Exception\SalesOrder\Enums;

use Framework\Enum\BaseEnum;

class AutoPurchaseCode extends BaseEnum
{
    const CODE_销售订单自动购买成功 = '000';
    const CODE_销售订单的状态为BEING_PROCESSED = '001';
    const CODE_销售订单的状态异常 = '002';
    const CODE_自动购买权限未开通 = '003';
    const CODE_销售订单正在处理请稍后再试 = '004';
    const CODE_销售订单存在已绑定的记录 = '005';
    const CODE_销售订单明细中存在多个相同的sku = '006';
    const CODE_销售订单没有加购和可绑的数据 = '007';
    const CODE_产品加入购物车失败_未上架不可售卖或被删除 = '101';
    const CODE_产品加入购物车失败_不是销售订单上的产品 = '102';
    const CODE_产品加入购物车失败_seller未与当前用户建立联系 = '103';
    const CODE_产品加入购物车失败_库存不足 = '104';
    const CODE_产品加入购物车失败_数量和实际购物车数量不一致 = '105';
    const CODE_补运费失败_没有找到店铺的补运费产品 = '201';
    const CODE_补运费失败_计算补运费失败 = '202';
    const CODE_补运费失败_绑定产品和购买产品不一致 = '203';
    const CODE_补运费失败_绑定数量和购买数量不一致 = '204';
    const CODE_购买失败_没有需要购买的产品或费用单 = '301';
    const CODE_购买失败_购物车的产品和销售订单的产品不一致 = '302';
    const CODE_购买失败_购物车无数据 = '303';
    const CODE_购买失败_购买数量超出销售订单范围 = '304';
    const CODE_购买失败_账号与支付方式异常 = '305';
    const CODE_购买失败_库存不足 = '306';
    const CODE_购买失败_信用额度不足 = '309';
    const CODE_购买失败_支付金额计算有误 = '310';
    const CODE_程序报错 = '401';
    const CODE_购买失败_限时限量活动库存不足 = '501';

    /**
     * @return string[]
     */
    public static function getViewItems(): array
    {
        return [
            self::CODE_销售订单自动购买成功 => 'success',
            self::CODE_销售订单的状态为BEING_PROCESSED => "The Sales Order is under the 'Being Processed' status.",
            self::CODE_销售订单的状态异常 => "The Sales Order is under the '#replace_status#' status.",
            self::CODE_自动购买权限未开通 => 'Your API permission is not enabled.',
            self::CODE_销售订单正在处理请稍后再试 => 'The Sales Order is currently being processed. Please try again later.',
            self::CODE_销售订单存在已绑定的记录 => 'Assigned records exist for the Sales Order.',
            self::CODE_销售订单明细中存在多个相同的sku => 'Multiple identical SKUs exist in the Sales Order details.',
            self::CODE_销售订单没有加购和可绑的数据 => 'No items need to be purchased or assigned for the Sales Order.',
            self::CODE_产品加入购物车失败_未上架不可售卖或被删除 => 'Failed to add the item(s) to cart: (#replace_sku#) may not be available, not be for sale, or have been deleted.',
            self::CODE_产品加入购物车失败_不是销售订单上的产品 => 'Failed to add the item(s) to cart: (#replace_sku#) are not included in the Sales Order.',
            self::CODE_产品加入购物车失败_seller未与当前用户建立联系 => 'Failed to add the item(s) to cart: the Seller of (#replace_sku#) has not built a relationship with the current account.',
            self::CODE_产品加入购物车失败_库存不足 => 'Failed to add the item(s) to cart: the inventory of (#replace_sku#) is insufficient.',
            self::CODE_产品加入购物车失败_数量和实际购物车数量不一致 => 'Failed to add the item(s) to cart: the quantity of (#replace_sku#) is inconsistent with the actual quantity in the cart.',
            self::CODE_补运费失败_没有找到店铺的补运费产品 => 'Additional shipping fee payment failed: No additional shipping fee item is found in the store.',
            self::CODE_补运费失败_计算补运费失败 => 'Additional shipping fee payment failed: Incorrect additional shipping fee calculation.',
            self::CODE_补运费失败_绑定产品和购买产品不一致 => 'Additional shipping fee payment failed: The item purchased is inconsistent with the assigned item in the order.',
            self::CODE_补运费失败_绑定数量和购买数量不一致 => 'Additional shipping fee payment failed: The purchase quantity is inconsistent with the assigned quantity.',
            self::CODE_购买失败_没有需要购买的产品或费用单 => 'Purchase failed: No items are available for purchase or no charges need to be paid.',
            self::CODE_购买失败_购物车的产品和销售订单的产品不一致 => 'Purchase failed: The items in the cart are inconsistent with those in the Sales Order.',
            self::CODE_购买失败_购物车无数据 => 'Purchase failed: No items in the cart.',
            self::CODE_购买失败_购买数量超出销售订单范围 => 'Purchase failed: The purchase quantity of (#replace_sku#) exceeds the quantity specified in the Sales Order.',
            self::CODE_购买失败_账号与支付方式异常 => 'Purchase failed: Error in the account and payment method.',
            self::CODE_购买失败_库存不足 => 'Purchase failed: the inventory of (#replace_sku#) is insufficient.',
            self::CODE_购买失败_信用额度不足 => 'Purchase failed: Your credit line is insufficient.',
            self::CODE_购买失败_支付金额计算有误 => 'Purchase failed: Incorrect calculation of payment amount.',
            self::CODE_程序报错 => 'Other errors. Please contact the admin.',
            self::CODE_购买失败_限时限量活动库存不足 => 'Product not available in the desired quantity or not in stock!',
        ];
    }
}
