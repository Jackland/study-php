<?php

use Illuminate\Support\Collection;

class ControllerEventController extends Controller
{
    const SUCCESS = 'success';

    public function before(&$route, &$args)
    {
        $this->resolveSuccessMsg();
    }

    /**
     * 平台界面英文化二期
     * 保存表单成功的提示统一格式为：Success: xxx
     * user：wangjinxin
     * date：2019/7/24 14:38
     */
    private function resolveSuccessMsg()
    {
        $session = new Collection($this->session->data);
        $successInfo = $session->get(static::SUCCESS, '');
        if (empty($successInfo)) return;
        $strSuffix = $successInfo;
        $header = mb_substr($successInfo, 0, strlen(static::SUCCESS));
        if (strtolower($header) == static::SUCCESS) {
            $strSuffix = mb_substr($successInfo, strlen(static::SUCCESS));
            if ($strSuffix[0] == ':' || $strSuffix[0] == '：') {
                $strSuffix = mb_substr($strSuffix, 1);
            }
        }
        if (empty($strSuffix)) return;

        session()->set('success', 'Success: ' . $strSuffix);
    }
}