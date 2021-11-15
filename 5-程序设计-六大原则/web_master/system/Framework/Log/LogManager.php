<?php

namespace Framework\Log;

use Framework\Aliases\Aliases;
use Illuminate\Contracts\Events\Dispatcher;
use InvalidArgumentException;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonoLogger;
use Monolog\Processor\ProcessorInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Throwable;

class LogManager implements LoggerInterface
{
    use LoggerTrait;

    private $channelConfigs;
    private $defaultChannel;
    private $pathAliases;
    private $globalFormatters;
    private $globalProcessors;
    private $dispatcher;

    public function __construct(
        array $channels,
        string $defaultChannel,
        Aliases $pathAliases,
        array $globalFormatters = [],
        array $globalProcessors = [],
        Dispatcher $dispatcher = null
    )
    {
        $this->channelConfigs = $channels;
        $this->defaultChannel = $defaultChannel;
        $this->pathAliases = $pathAliases;

        $this->globalFormatters = $globalFormatters;
        $this->globalProcessors = $globalProcessors;

        $this->dispatcher = $dispatcher;
    }

    /**
     * 切换通道
     * @param null $name
     * @return LoggerInterface
     */
    public function channel($name = null): LoggerInterface
    {
        return $this->get($name ?: $this->defaultChannel);
    }

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = array())
    {
        $this->channel()->log($level, $message, $context);
    }

    private $_channels = [];

    /**
     * @param $name
     * @return LoggerInterface
     */
    protected function get($name): LoggerInterface
    {
        try {
            if (!isset($this->_channels[$name])) {
                $this->_channels[$name] = new Logger($this->resolve($name), $this->dispatcher);
            }
            return $this->_channels[$name];
        } catch (Throwable $e) {
            $logger = new Logger(new MonoLogger('framework', [
                new StreamHandler($this->pathAliases->get('@runtime/logs/framework.log'), LogLevel::DEBUG),
            ]), $this->dispatcher);
            $logger->error($e);
            return $logger;
        }
    }

    /**
     * @param string $name
     * @return LoggerInterface
     */
    protected function resolve(string $name): LoggerInterface
    {
        if (!isset($this->channelConfigs[$name])) {
            throw new InvalidArgumentException("日志[{$name}]未配置");
        }

        $logger = new MonoLogger($name);
        $this->prepareMonoLogHandlers($logger, $this->channelConfigs[$name]);

        return $logger;
    }

    /**
     * @param MonoLogger $logger
     * @param array $handlers
     */
    protected function prepareMonoLogHandlers(MonoLogger $logger, array $handlers)
    {
        foreach ($handlers as $handlerConfig) {
            // 获取 handler
            $handler = $this->prepareMonoLogHandler($handlerConfig['handler']);
            // 设置 formatter
            if (isset($handlerConfig['formatter'])) {
                $handler->setFormatter($this->prepareMonoLogFormatter($handlerConfig['formatter']));
            }
            // 设置 level
            if (isset($handlerConfig['level'])) {
                $handler->setLevel($handlerConfig['level']);
            }
            // 设置 processor
            if (isset($handlerConfig['processors'])) {
                foreach ($this->prepareMonoLogProcessors($handlerConfig['processors']) as $processor) {
                    $handler->pushProcessor($processor);
                }
            }

            $logger->pushHandler($handler);
        }
    }

    /**
     * @param mixed $handler
     * @return HandlerInterface
     */
    protected function prepareMonoLogHandler($handler): HandlerInterface
    {
        if (is_callable($handler)) {
            $handler = call_user_func($handler);
        }
        if (!$handler instanceof HandlerInterface) {
            throw new InvalidArgumentException('handler 结果必须为 HandlerInterface');
        }

        return $handler;
    }

    /**
     * @param mixed $formatter
     * @return FormatterInterface
     */
    protected function prepareMonoLogFormatter($formatter): FormatterInterface
    {
        if (is_string($formatter)) {
            if (strpos($formatter, '%') === false) {
                $formatter = $this->prepareMonoLogFormatterFromGlobal($formatter);
            } else {
                $formatter = new LineFormatter($formatter);
                $formatter->includeStacktraces(true);
            }
        } elseif (is_callable($formatter)) {
            $formatter = call_user_func($formatter);
        }

        if (!$formatter instanceof FormatterInterface) {
            throw new InvalidArgumentException('formatter 结果必须为 FormatterInterface');
        }

        return $formatter;
    }

    /**
     * @param mixed $processors
     * @return array|ProcessorInterface[]
     */
    protected function prepareMonoLogProcessors($processors): array
    {
        if (is_string($processors)) {
            $processors = $this->prepareMonoLogProcessorsFromGlobal($processors);
        } elseif (is_callable($processors)) {
            $processors = call_user_func($processors);
        } else {
            throw new InvalidArgumentException('processors 配置错误');
        }
        return $processors;
    }

    /**
     * @param string $name
     * @return mixed
     */
    private function prepareMonoLogFormatterFromGlobal(string $name)
    {
        if (!isset($this->globalFormatters[$name])) {
            throw new InvalidArgumentException("不存在的全局的 formatter[$name]");
        }
        $formatter = $this->globalFormatters[$name];

        if (is_string($formatter)) {
            $formatter = new LineFormatter($formatter);
            $formatter->includeStacktraces(true);
        } elseif (is_callable($formatter)) {
            $formatter = call_user_func($formatter);
        }

        return $this->globalFormatters[$name] = $formatter;
    }

    /**
     * @param string $name
     * @return mixed
     */
    private function prepareMonoLogProcessorsFromGlobal(string $name)
    {
        if (!isset($this->globalProcessors[$name])) {
            throw new InvalidArgumentException("不存在的全局的 processors[$name]");
        }

        if (is_callable($this->globalProcessors[$name])) {
            $this->globalProcessors[$name] = call_user_func($this->globalProcessors[$name]);
        }

        return $this->globalProcessors[$name];
    }
}
