<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use OSS\OssClient;

class OssHelper
{
    /**
     * 修改文件的访问性
     * @param string $path
     * @param string $acl private/public
     */
    public static function changeFileAcl(string $path, string $acl): void
    {
        /** @var OssClient $client */
        $client = Storage::cloud()->getDriver()->getAdapter()->getClient();
        $client->putObjectAcl(
            env('ALIYUN_BUCKET'),
            ltrim($path, '/'),
            $acl === 'private' ? OssClient::OSS_ACL_TYPE_PRIVATE : OssClient::OSS_ACL_TYPE_PUBLIC_READ
        );
    }
}