<?php

namespace Framework\Debug;

use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\PDO\PDOCollector;
use DebugBar\DataCollector\PDO\TraceablePDO;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DataCollector\RequestDataCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar as BaseDebugBar;
use DebugBar\HttpDriverInterface;
use Framework\Debug\Storage\FilesystemStorage;
use Framework\Debug\Traits\AddMessageTrait;
use Framework\Debug\Traits\AddTwigCollectorTrait;
use Framework\Debug\Traits\MeasureTrait;
use Framework\Exception\InvalidConfigException;
use Framework\Foundation\Application;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Filesystem\Filesystem;

/**
 * @link @link http://phpdebugbar.com/docs/
 */
class DebugBar extends BaseDebugBar
{
    use AddTwigCollectorTrait;
    use AddMessageTrait;
    use MeasureTrait;

    /**
     * @var Application
     */
    protected $app;
    /**
     * @var array
     */
    protected $config = [
        'enable' => false, // 全局开关
        'storageSavePath' => '', // 绝对路径
        'openHandlerUrl' => '', // 获取历史记录的接口地址
        'asset' => [ // 资源发布地址
            'basePath' => '@asset/debugbar',
            'baseUrl' => '@assetUrl/debugbar',
        ],
        'exceptRoutes' => [], // 不进入 debugBar 的路由
    ];

    private $isEnabled = null;
    private $isBooted = false;

    public function __construct(Application $app, $config = [])
    {
        $this->app = $app;
        $this->config = array_merge($this->config, $config);
    }

    public function enable()
    {
        $this->isEnabled = true;

        if (!$this->isBooted) {
            $this->boot();
        }
    }

    public function disable()
    {
        $this->isEnabled = false;
    }

    public function isEnabled(): bool
    {
        if ($this->isEnabled === null) {
            $this->isEnabled = $this->config['enable'];
        }

        return $this->isEnabled;
    }

    public function boot()
    {
        if ($this->isBooted) {
            return;
        }

        $debugBar = $this;
        $config = $this->config;

        // 启动 session 支持
        if ($this->app->has(HttpDriverInterface::class)) {
            $debugBar->setHttpDriver($this->app->get(HttpDriverInterface::class));
        }
        // 存储
        if ($config['storageSavePath']) {
            $savePath = $this->app->pathAliases->get($config['storageSavePath']);
            $storage = new FilesystemStorage(new Filesystem(), $savePath);
            $debugBar->setStorage($storage);
        }
        // 增加 Collector
        $debugBar->addCollector(new PhpInfoCollector());
        $debugBar->addCollector(new MessagesCollector());
        $debugBar->addCollector(new TimeDataCollector());
        $debugBar->addCollector(new MemoryCollector());
        $debugBar->addCollector(new ExceptionsCollector());
        $debugBar->addCollector(new RequestDataCollector());
        if (!$this->app->has(Manager::class)) {
            throw new InvalidConfigException('必须先有 ' . Manager::class);
        }
        $manager = $this->app->get(Manager::class);
        $podCollector = new PDOCollector();
        foreach (array_keys($manager->getContainer()['config']['database.connections']) as $name) {
            $connection = $manager->getConnection($name);
            $traceablePdo = new TraceablePDO($connection->getPdo());
            $podCollector->addConnection($traceablePdo, $name);
            $connection->setPdo($traceablePdo);
        }
        $debugBar->addCollector($podCollector);
        // stack 处理 redirect 时有用，此处暂时用不上
        // http://phpdebugbar.com/docs/ajax-and-stack.html#stacked-data
        //$debugBar->stackData();

        $renderer = $this->getJavascriptRenderer(
            $this->app->pathAliases->get($config['asset']['baseUrl']),
            $this->app->pathAliases->get($config['asset']['basePath'])
        );
        if ($debugBar->getStorage()) {
            // 允许访问历史
            $renderer->setOpenHandlerUrl(url($this->config['openHandlerUrl']));
        }
        $renderer->setIncludeVendors();
        $renderer->setBindAjaxHandlerToFetch();
        $renderer->setBindAjaxHandlerToXHR();

        $this->isBooted = true;
    }

    /**
     * @return array
     */
    public function getExceptRoutes(): array
    {
        return $this->config['exceptRoutes'] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getJavascriptRenderer($baseUrl = null, $basePath = null)
    {
        if ($this->jsRenderer === null) {
            $this->jsRenderer = new JavascriptRenderer($this, $baseUrl, $basePath);
        }
        return $this->jsRenderer;
    }
}
