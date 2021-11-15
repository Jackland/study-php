<?php

namespace Framework\Route;

use Framework\Action\Action;
use Framework\Config\Config;
use Framework\Foundation\Application;
use Framework\Http\Request;
use Framework\Http\Response;
use Registry;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class OcRouter
{
    /**
     * @var Application
     */
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @return SymfonyResponse
     * @throws Throwable
     */
    public function handle(Request $request)
    {
        $registry = $this->app->ocRegistry;
        try {
            // 执行预操作
            $result = $this->runPreActions();

            if ($result instanceof SymfonyResponse) {
                // 为响应则直接返回
                return $result;
            }
            // 确定最终需要执行的操作
            $action = null;
            if ($result instanceof Action) {
                // 预操作返回为 Action 时则执行 预操作的返回 Action
                $action = $result;
            }
            if ($action === null) {
                // 默认操作
                $action = new Action($this->app->ocConfig->get('action_router'));
            }

            // 循环执行 Action，直到结果非 Action 为止
            while ($action instanceof Action) {
                $result = $action->execute($registry);
                $action = $result;
            }
            $result = $action;

            if ($result instanceof Throwable) {
                // 当返回值为一个异常信息时（非 throw 的），当异常处理
                throw $result;
            }
        } catch (Throwable $e) {
            // 为异常时跳转到异常页面
            $action = new Action($this->app->ocConfig->get('action_error'));
            $result = $action->execute($registry, ['exception' => $e]);
        }

        // 为响应则直接返回
        if ($result instanceof SymfonyResponse) {
            return $result;
        }

        if ($result === null && headers_sent()) {
            // result 为 null 并且 header_sent 的，防止重复 send
            return new Response();
        }

        /** @var Response $response */
        $response = $this->app->ocRegistry->get('response');
        // 为内容时加入 content
        if ($result !== null) {
            $response->setOutput($result);
        }

        return $response;
    }

    /**
     * 执行预加载器
     * @return null|Action|SymfonyResponse
     */
    protected function runPreActions()
    {
        $config = $this->app->ocConfig;
        if ($config->has('action_pre_action')) {
            $registry = $this->app->ocRegistry;
            foreach ($config->get('action_pre_action') as $actionRoute) {
                $action = new Action($actionRoute);
                $result = $action->execute($registry);

                if ($result instanceof Action) {
                    return $result;
                }
                if ($result instanceof SymfonyResponse) {
                    return $result;
                }
            }
        }
        return null;
    }
}
