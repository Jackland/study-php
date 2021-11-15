<?php

namespace Framework\Foundation\Bootstrap;

use Framework\Foundation\Application;
use Framework\Http\Request;

class SetRequestForConsole
{
    public function bootstrap(Application $app)
    {
        $request = new Request();
        $app->instance('request', $request);
        $app->alias('request', Request::class);
        $app->ocRegistry->set('request', $request); // 覆盖 oc 中的 request
    }
}
