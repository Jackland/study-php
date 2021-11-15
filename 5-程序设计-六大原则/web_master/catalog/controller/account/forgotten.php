<?php

class ControllerAccountForgotten extends Controller
{
    // 兼容原忘记密码页面
    public function index()
    {
        return $this->redirect('account/password/reset', 301);
    }
}
