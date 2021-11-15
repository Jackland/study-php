<?php

use App\Catalog\Controllers\BaseController;

class ControllerCommonTestWebpack extends BaseController
{
    // 使用 twig 的形式的例子
    public function sampleAsset()
    {
        return $this->renderFront('common/test_webpack/sample_asset','seller');
    }

    // 使用 vue 的形式的例子
    public function sampleVue()
    {
        return $this->renderFront('common/test_webpack/sample_vue', 'buyer', ['xxx' => 'yy']);
    }

    // 参考接口
    public function sampleInformationList()
    {
        //sleep(3); // 测试网络慢的情况
        return $this->jsonSuccess([
            ['id' => 1, 'name' => 'aaa'],
            ['id' => 2, 'name' => 'bbb'],
            ['id' => 3, 'name' => 'ccc'],
        ]);
    }
}
