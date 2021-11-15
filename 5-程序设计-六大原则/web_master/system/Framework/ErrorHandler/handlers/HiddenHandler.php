<?php

namespace Framework\ErrorHandler\handlers;

use Framework\ErrorHandler\ErrorHandlerInterface;
use Framework\Foundation\Application;

class HiddenHandler implements ErrorHandlerInterface
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
        set_error_handler(function ($code, $message, $file, $line) {
            // error suppressed with @
            if (error_reporting() === 0) {
                return false;
            }

            switch ($code) {
                case E_NOTICE:
                case E_USER_NOTICE:
                    $error = 'Notice';
                    break;
                case E_WARNING:
                case E_USER_WARNING:
                    $error = 'Warning';
                    break;
                case E_ERROR:
                case E_USER_ERROR:
                    $error = 'Fatal Error';
                    break;
                default:
                    $error = 'Unknown';
                    break;
            }

            if ($this->app->ocConfig->get('error_display')) {
                echo '<b>' . $error . '</b>: ' . $message . ' in <b>' . $file . '</b> on line <b>' . $line . '</b>';
            }

            if ($this->app->ocConfig->get('error_log')) {
                $this->app['log']->error('PHP ' . $error . ':  ' . $message . ' in ' . $file . ' on line ' . $line);
            }

            return true;
        });

        set_exception_handler(function (\Throwable $e) {
            if ($this->app->ocConfig->get('error_display')) {
                if (isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) == 'xmlhttprequest') {
                    // 是ajax请求
                    $json = [
                        "message:" => $e->getMessage(),
                        "code" => $e->getCode()
                    ];
                    header('Content-Type:application/json; charset=utf-8');
                    header('HTTP/1.1 500 Internal Server Error');
                    echo $e->getMessage();
                } else {
                    // 不是ajax请求
                    echo '<b>' . $e->getCode() . '</b>: ' . $e->getMessage() . ' in <b>' . $e->getFile() . '</b> on line <b>' . $e->getLine() . '</b>' . $e->getTraceAsString() . '<br>';
                }
            }

            if ($this->app->ocConfig->get('error_log')) {
                $this->app['log']->error('PHP Exception: ' . $e->getCode() . ':  ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
            }
        });
    }
}
