<?php

namespace Framework\ErrorHandler;

use Exception;
use Framework\Contracts\Debug\ExceptionHandler as ExceptionHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;

class ExceptionHandler implements ExceptionHandlerInterface
{
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function report(Exception $e)
    {
        $this->logger->error($e);
    }

    /**
     * @inheritDoc
     */
    public function shouldReport(Exception $e)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function render($request, Exception $e)
    {
        // 直接抛出，交给 ErrorHandler 处理
        throw $e;
    }

    /**
     * @inheritDoc
     */
    public function renderForConsole($output, Exception $e)
    {
        (new Application())->renderThrowable($e, $output);
    }
}
