<?php

namespace App\Components\Storage;

use App\Components\Storage\Traits\LocalAdapterFixOldPathTrait;

/**
 * @method static static image() /image 目录
 * @method static static storage() /storage 目录
 * @method static static rmaFile() /storage/rmaFile 目录
 */
class StorageLocal extends BaseStorage
{
    use LocalAdapterFixOldPathTrait;

    /**
     * @inheritDoc
     */
    protected static function methodPathMap()
    {
        return array_merge(parent::methodPathMap(), [
            // 以下为兼容旧的文件存储做的映射，新的业务逻辑不应该再有增加
            'rmaFile' => 'storage/rmaFile',
        ]);
    }
}
