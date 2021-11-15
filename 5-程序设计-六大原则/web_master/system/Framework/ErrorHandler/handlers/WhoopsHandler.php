<?php

namespace Framework\ErrorHandler\handlers;

use Framework\ErrorHandler\ErrorHandlerInterface;
use Framework\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Whoops\Handler\CallbackHandler;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;
use Whoops\Util\Misc;

class WhoopsHandler implements ErrorHandlerInterface
{
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @inheritDoc
     */
    public function register()
    {
        $whoops = new Run();
        // 展示错误信息
        $whoops->pushHandler($this->getPrettyHandler());
        // ajax 优化
        if (Misc::isAjaxRequest()) {
            $whoops->pushHandler(new JsonResponseHandler());
        }
        if (Misc::isCommandLine()) {
            $whoops->pushHandler(new CallbackHandler(function ($e) {
                // 命令行由 ExceptionHandler 处理
                throw $e;
            }));
        }
        // 记录错误日志
        $whoops->pushHandler(new CallbackHandler(function ($e) {
            $this->app['log']->error($e);
        }));

        $whoops->register();
    }

    private function getPrettyHandler(): PrettyPageHandler
    {
        $handler = new PrettyPageHandler();

        $pathRoot = $this->app->pathAliases->get('@root') . DIRECTORY_SEPARATOR;
        $handler->setApplicationPaths(array_flip(
            Arr::except(
                array_flip((new Filesystem)->directories($pathRoot)),
                [
                    //$pathRoot . 'system',
                    $pathRoot . 'storage',
                ]
            )
        ));

        return $handler;
    }
}
