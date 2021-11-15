<?php

namespace Framework\Foundation\Bootstrap;

use Framework\Foundation\Application;

class RegisterProviders
{
    public function bootstrap(Application $app)
    {
        $app->registerConfiguredProviders();
    }
}
