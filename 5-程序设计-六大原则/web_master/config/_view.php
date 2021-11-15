<?php

use App\Components\TwigExtensions\ExtensionScanner;
use App\Helper\ModuleHelper;

return [
    'aliases' => [
        // 别名 => 实际文件路径
        // 支持 view()->render('@widgets/xxx.twig') 的方式
        '@widgets' => '@root/resources/views/widgets',
        '@pdfTemplate' => '@root/resources/views/pdfTemplate',
    ],
    'finder' => [
        'base_path' => ModuleHelper::isInAdmin() ? '@root/admin/view/template' : '@root/catalog/view/theme',
        'theme_paths' => ModuleHelper::isInAdmin() ? [] : ['yzcTheme/template', 'default/template'],
    ],
    'asset' => [
        'base_path' => '@assets',
        'base_url' => '@assetsUrl',
        'append_timestamp' => true,
        'force_copy' => false,
    ],
    'renderer' => [
        'twig' => [
            'loader_paths' =>
                ModuleHelper::isInAdmin() ?
                    [
                        '@root/storage/modification/admin/view/template' => null,
                        '@root/admin/view/template' => null,
                    ] :
                    [
                        '@root/storage/modification/catalog/view/theme' => null,
                        '@root/catalog/view/theme' => null,
                        // twig 中使用 include/extend 等支持通过 @yzc/common/header.twig 的形式引入路径
                        '@root/storage/modification/catalog/view/theme/yzcTheme/template' => 'yzc',
                        '@root/catalog/view/theme/yzcTheme/template' => 'yzc',
                    ],
            'env_options' => [
                'autoescape' => false,
                'cache_enable' => get_env('TWIG_CACHE_ENABLE', false),
                'cache_path' => '@runtime/cache/twig',
            ],
            'extensions' => ExtensionScanner::getList(),
        ]
    ]
];
