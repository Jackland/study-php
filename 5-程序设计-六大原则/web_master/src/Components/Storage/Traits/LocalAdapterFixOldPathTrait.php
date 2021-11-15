<?php

namespace App\Components\Storage\Traits;

use App\Components\Storage\Adapter\Local;
use Illuminate\Support\Str;

/**
 * Local Adapter 兼容旧的文件夹路径
 */
trait LocalAdapterFixOldPathTrait
{
    /**
     * @inheritDoc
     */
    public function getAdapter()
    {
        $adapter = parent::getAdapter();
        if ($adapter instanceof Local) {
            // 以 image 和 storage 开头的，切换 root 路径为 @root
            $pathPrefix = $this->getPathPrefix();
            if (Str::startsWith($pathPrefix, ['image', 'storage'])) {
                $adapter->changeRoot(aliases('@root'));
                $adapter->changeBaseUrl('');
            }
        }
        return $adapter;
    }
}
