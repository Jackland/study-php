<?php

use App\Components\Storage\Adapter\AliOss;
use App\Components\Storage\Adapter\Local;
use App\Components\Storage\StorageCloud;
use App\Components\Storage\StorageLocal;
use App\Components\Storage\StoragePublic;

$aliOssUsed = !!get_env('ALI_OSS_AK', false);
return [
    'defaultCheckExistWhenGetUrl' => get_env('DEFAULT_CHECK_EXIST_WHEN_GET_IMAGE', true), // 默认的，在 getUrl 时是否检查文件是否存在，开启存在一定的性能影响
    'disks' => [
        StorageLocal::class => [
            'adapter' => function () {
                return new Local(aliases('@root/storage'), false);
            }
        ],
        StoragePublic::class => [
            'adapter' => function () {
                return new Local(aliases('@public'), aliases('@publicUrl'));
            }
        ],
        StorageCloud::class => [
            'adapter' => function () use ($aliOssUsed) {
                if ($aliOssUsed) {
                    return new AliOss([
                        'ak' => get_env('ALI_OSS_AK'),
                        'sk' => get_env('ALI_OSS_SK'),
                        'endpoint' => get_env('ALI_OSS_ENDPOINT'),
                        'bucket' => get_env('ALI_OSS_BUCKET'),
                        'domain' => get_env('ALI_OSS_DOMAIN'),
                        'isCName' => get_env('ALI_OSS_IS_CNAME'),
                        // 由于富文本中需要存储 http 链接，因此不能使用签名方式，会造成图片链接失效，因此 isUrlSign 固定为 false
                        'isUrlSign' => false, // get_env('ALI_OSS_IS_URL_SIGN'), // 是否开启签名
                        'urlSignTimeout' => get_env('ALI_OSS_URL_SIGN_TIMEOUT'), // 签名有效期
                    ]);
                }
                return new Local(aliases('@root/storage/cloud'), '/storage/cloud');
            }
        ],
    ],
];
