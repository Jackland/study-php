<?php

use App\Catalog\Controllers\BaseController;

class ControllerCommonRedirect extends BaseController
{
    // 重定向到首页
    public function home()
    {
        return $this->redirectHome();
    }
}
