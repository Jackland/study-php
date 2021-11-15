<?php

namespace App\Components\Storage;

use App\Components\Storage\Traits\LocalAdapterFixOldPathTrait;
use App\Components\Storage\Traits\LocalTrait;

/**
 * @method static static test() 用于测试
 * @method static static sellerFile() seller账号文件目录
 * 以下为旧的存储目录，新的业务目录请加在上方
 * @method static static image() /image 目录
 * @method static static storage() /storage 目录
 * @method static static upload() /storage/upload 目录
 * @method static static futureAppealFile() 期货协议审核
 * @method static static wkseller() 所有图片素材的上传
 * @method static static wkmisc() common/upload 处上传的文件
 * @method static static imageMisc() 富文本等杂项上传
 * @method static static rebateRequest() 返点完成申请
 * @method static static orderCsv() 一件代发&上门取货&纯物流上传文件
 * @method static static newProductCsv() seller批量更改价格
 * @method static static priceCsv() seller批量更改价格
 * @method static static mappingSku() buyer sku 映射针对于内部用户
 * @method static static shipmentFile() admin 后台配置文件 http://t1.b2b.orsd.tech/admin/index.php?route=extension/module/shipment_time&user_token=ujqIvc1I3nGUugESazcsygYYE3CBP2In
 * @method static static rmaFile() rma文件上传
 * @method static static invoiceFile() Buyer Invoice文件上传
 */
class StorageCloud extends BaseStorage
{
    use LocalTrait;
    use LocalAdapterFixOldPathTrait;

    /**
     * @inheritDoc
     */
    protected static function methodPathMap()
    {
        return array_merge(parent::methodPathMap(), [
            // 以下为兼容旧的文件存储做的映射，新的业务逻辑不应该再有增加
            'wkseller' => 'image/wkseller',
            'wkmisc' => 'image/wkmisc',
            'imageMisc' => 'image/misc',
            'upload' => 'storage/upload',
            'rebateRequest' => 'storage/rebateRequest',
            'orderCsv'  => 'storage/orderCsv',
            'newProductCsv'  => 'storage/new_product_csv',
            'priceCsv'  => 'storage/priceCsv',
            'mappingSku'  => 'storage/mappingSku',
            'shipmentFile'  => 'storage/shipmentFile',
            'rmaFile' => 'storage/rmaFile',
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function getNoImageIfConfigNotExists($path, $width, $height)
    {
        return StorageLocal::image()->getUrl($path, [
            'w' => $width,
            'h' => $height,
            'no-image' => false,
        ]);
    }
}
