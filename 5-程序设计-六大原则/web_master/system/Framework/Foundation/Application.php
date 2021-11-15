<?php

namespace Framework\Foundation;

use Framework\DB\DatabaseServiceProvider;
use Framework\DI\Container;
use Framework\ErrorHandler\ErrorHandlerServiceProvider;
use Framework\Event\EventServiceProvider;
use Framework\Foundation\Traits\BootstrapTrait;
use Framework\Foundation\Traits\BootTrait;
use Framework\Foundation\Traits\ConfigTrait;
use Framework\Foundation\Traits\ConsoleTrait;
use Framework\Foundation\Traits\DeferredProviderTrait;
use Framework\Foundation\Traits\OcCoreTrait;
use Framework\Foundation\Traits\PathAliasesTrait;
use Framework\Foundation\Traits\ProviderTrait;
use Framework\Foundation\Traits\TerminateTrait;
use Framework\Helper\FileHelper;
use Framework\Log\LogServiceProvider;
use Illuminate\Filesystem\Filesystem;

class Application extends Container
{
    use ConfigTrait;
    use PathAliasesTrait;
    use OcCoreTrait;
    use ProviderTrait;
    use DeferredProviderTrait;
    use BootTrait;
    use BootstrapTrait;
    use TerminateTrait;
    use ConsoleTrait;

    public function __construct($configs, $ocConfig)
    {
        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();

        // 早于 DatabaseServiceProvider，因为其需要 $this->config 获取配置信息
        $this->loadConfig($configs);
        // 早于 $this->loadOcCore，因为其需要 DB 查询配置
        $this->register(new DatabaseServiceProvider($this));
        // 注册路径别名
        $this->loadPathAliases($this->config['aliases']);
        // 注册错误处理
        $this->register(new ErrorHandlerServiceProvider($this));
        // 注册 OC 组件
        $this->loadOcCore($ocConfig, $this['db']);
    }

    /**
     * 注册 container 自己
     */
    protected function registerBaseBindings()
    {
        static::setInstance($this);

        $this->instance('app', $this);
        $this->instance(Container::class, $this);

        $this->registerServiceProviderAlias([
            'app' => [self::class, \Illuminate\Contracts\Container\Container::class, \Illuminate\Contracts\Foundation\Application::class, \Psr\Container\ContainerInterface::class],
        ]);
    }

    /**
     * 注册基础服务
     */
    protected function registerBaseServiceProviders()
    {
        $this->register(new EventServiceProvider($this));
        $this->register(new LogServiceProvider($this));
    }

    /**
     * 触发回调
     * @param callable[] $callbacks
     * @return void
     */
    protected function fireAppCallbacks(array $callbacks)
    {
        foreach ($callbacks as $callback) {
            call_user_func($callback, $this);
        }
    }

    /**
     * 注册所有 provider
     * @return void
     */
    public function registerConfiguredProviders()
    {
        $providers = collect($this->config['providers']);

        $cachePath = $this->pathAliases->get('@runtime/app/services.php');
        FileHelper::createDirectory(dirname($cachePath));
        (new ProviderRepository($this, new Filesystem, $cachePath))
            ->load($providers->toArray());
    }

    /**
     * @inheritDoc
     */
    public function bound($abstract)
    {
        return $this->isDeferredService($abstract) || parent::bound($abstract);
    }

    /**
     * @inheritDoc
     */
    public function make($abstract, array $parameters = [])
    {
        $abstract = $this->getAlias($abstract);

        if ($this->isDeferredService($abstract) && !isset($this->instances[$abstract])) {
            $this->loadDeferredProvider($abstract);
        }

        return parent::make($abstract, $parameters);
    }

    /**
     * @inheritDoc
     */
    public function get($id)
    {
        $abstract = $this->getAlias($id);

        if ($this->isDeferredService($abstract) && !isset($this->instances[$abstract])) {
            $this->loadDeferredProvider($abstract);
        }

        return parent::get($id);
    }
}
