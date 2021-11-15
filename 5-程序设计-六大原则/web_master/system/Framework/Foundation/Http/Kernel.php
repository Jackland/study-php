<?php

namespace Framework\Foundation\Http;

use Framework\Contracts\Debug\ExceptionHandler;
use Framework\Exception\ExceptionUtil;
use Framework\Foundation\Application;
use Framework\Foundation\Bootstrap\BootProviders;
use Framework\Foundation\Bootstrap\OcCoreStart;
use Framework\Foundation\Bootstrap\RegisterProviders;
use Framework\Http\Events\ResponseBeforeSend;
use Framework\Http\Request;
use Framework\Route\OcRouter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Kernel
{
    protected $app;

    /**
     * The bootstrap classes for the application.
     *
     * @var array
     */
    protected $bootstrappers = [
        RegisterProviders::class,
        OcCoreStart::class,
        BootProviders::class,
    ];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function handle($request)
    {
        try {
            $response = $this->sendRequestThroughRouter($request);
        } catch (Throwable $e) {
            $this->reportException($e);
            $response = $this->renderException($request, $e);
        }

        return $response;
    }

    public function terminate($request, $response)
    {
        $this->app->terminate();
    }

    protected function sendRequestThroughRouter(Request $request)
    {
        $this->app->instance('request', $request);
        $this->app->alias('request', Request::class);
        $this->app->ocRegistry->set('request', $request); // 覆盖 oc 中的 request
        $this->app->instance('response', $this->app->ocRegistry->get('response'));

        $this->bootstrap();

        $response = $this->app->make(OcRouter::class)->handle($request);

        event(new ResponseBeforeSend($response));

        return $response;
    }

    public function bootstrap()
    {
        if (!$this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers());
        }
    }

    /**
     * Get the bootstrap classes for the application.
     *
     * @return array
     */
    protected function bootstrappers()
    {
        return $this->bootstrappers;
    }

    protected function reportException(Throwable $e)
    {
        $this->app[ExceptionHandler::class]->report(ExceptionUtil::coverThrowable2Exception($e));
    }

    protected function renderException(Request $request, Throwable $e)
    {
        return $this->app[ExceptionHandler::class]->render($request, ExceptionUtil::coverThrowable2Exception($e));
    }
}
