<?php

return [
    // webpack encore 编译后的入口文件位置，同 webpack.config.js 中的 setOutputPath 地址
    'entrypointJsonPath' => '@assets/dist/entrypoints.json',
    // 严格模式，为 true 时找不到入口文件会报错，为 false 时返回空
    'strictMode' => OC_DEBUG,
];
