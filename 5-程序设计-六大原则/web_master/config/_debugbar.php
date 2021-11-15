<?php

return [
    'enable' => (bool)OC_DEBUG,
    'storageSavePath' => '@runtime/debugBar',
    'openHandlerUrl' => ['common/debugbar/open'],
    'asset' => [
        'basePath' => '@assets/debugbar',
        'baseUrl' => '@assetsUrl/debugbar',
    ],
    'exceptRoutes' => [
        'common/debugbar/*',
        'common/message_window/*',
        'customerpartner/notification/notifications',
    ],
];
