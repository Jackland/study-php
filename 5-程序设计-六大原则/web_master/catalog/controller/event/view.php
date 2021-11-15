<?php

class ControllerEventView extends Controller
{
    static $page_limit_key = 'page_limit';
    static $page_key = 'page';
    static $page_limit = 20;

    public function before(&$route, &$args)
    {
        $this->removeResultInfo($args);
        $this->resolvePageInfo($route, $args);
    }

    public function removeResultInfo(&$args)
    {
        $arrayName = [
            'results', 'partner_results',
        ];
        foreach ($arrayName as $name) {
            if (isset($args[$name])) $args[$name] = '';
        }
    }

    /**
     * 将分页信息 写入view
     * @param $route
     * @param $args
     * user：wangjinxin
     * date：2019/11/26 10:01
     * @see ControllerEventController::resolvePageInfo()
     */
    private function resolvePageInfo(&$route, &$args)
    {
        $args['page_limit'] = $args['page_limit'] ?? static::$page_limit;
    }
}