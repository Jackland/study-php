<?php

namespace Framework\Foundation\Bootstrap;

use Framework\Action\Action;
use Framework\Foundation\Application;

class OcCoreStart
{
    public function bootstrap(Application $app)
    {
        $config = $app->ocConfig;
        if (!$app->isConsole()) {
            // Event Register
            if ($config->has('action_event')) {
                $event = $app->ocEvent;
                foreach ($config->get('action_event') as $key => $value) {
                    foreach ($value as $priority => $action) {
                        $event->register($key, new Action($action), $priority);
                    }
                }
            }
        }

        $loader = $app->ocLoad;
        // Config Autoload
        if ($config->has('config_autoload')) {
            foreach ($config->get('config_autoload') as $value) {
                $loader->config($value);
            }
        }
        // Language Autoload
        if ($config->has('language_autoload')) {
            foreach ($config->get('language_autoload') as $value) {
                $loader->language($value);
            }
        }
        // Library Autoload
        if ($config->has('library_autoload')) {
            foreach ($config->get('library_autoload') as $value) {
                $loader->library($value);
            }
        }
        // Model Autoload
        if ($config->has('model_autoload')) {
            foreach ($config->get('model_autoload') as $value) {
                $loader->model($value);
            }
        }
    }
}
