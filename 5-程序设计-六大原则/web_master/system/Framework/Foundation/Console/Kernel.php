<?php

namespace Framework\Foundation\Console;

use Framework\Contracts\Debug\ExceptionHandler;
use Framework\Exception\ExceptionUtil;
use Framework\Exception\NotSupportException;
use Framework\Foundation\Application;
use Framework\Foundation\Bootstrap\BootProviders;
use Framework\Foundation\Bootstrap\OcCoreStart;
use Framework\Foundation\Bootstrap\RegisterProviders;
use Framework\Foundation\Bootstrap\SetRequestForConsole;
use Framework\Helper\FileHelper;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Command;
use Illuminate\Events\Dispatcher;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

class Kernel implements \Illuminate\Contracts\Console\Kernel
{
    /**
     * @var Application
     */
    protected $app;
    /**
     * @var Dispatcher
     */
    protected $events;
    /**
     * @var array
     */
    protected $bootstrappers = [
        SetRequestForConsole::class,
        RegisterProviders::class,
        OcCoreStart::class,
        BootProviders::class,
    ];
    /**
     * @var string
     */
    protected $version = '1.0';
    /**
     * @var array [$dir => $namespace]
     */
    protected $commandPaths = [
        __DIR__ . '/../../Console/Commands' => 'Framework\\Console\\Commands'
    ];
    /**
     * @var array
     */
    protected $commands = [];

    public function __construct(Application $app, Dispatcher $events)
    {
        $this->app = $app;
        $this->events = $events;
    }

    /**
     * @inheritDoc
     */
    public function handle($input, $output = null)
    {
        try {
            $this->bootstrap();

            return $this->getArtisan()->run($input, $output);
        } catch (Throwable $e) {
            $this->reportException($e);
            $this->renderException($output, $e);

            return 1;
        }
    }

    /**
     * @inheritDoc
     */
    public function call($command, array $parameters = [], $outputBuffer = null)
    {
        $this->bootstrap();

        return $this->getArtisan()->call($command, $parameters, $outputBuffer);
    }

    /**
     * @inheritDoc
     */
    public function queue($command, array $parameters = [])
    {
        throw new NotSupportException();
    }

    /**
     * @inheritDoc
     */
    public function all()
    {
        return $this->getArtisan()->all();
    }

    /**
     * @inheritDoc
     */
    public function output()
    {
        return $this->getArtisan()->output();
    }

    /**
     * @inheritDoc
     */
    public function terminate($input, $status)
    {
        //
    }

    /**
     * @return array
     */
    protected function bootstrappers()
    {
        return $this->bootstrappers;
    }

    protected $commandsLoaded = false;

    /**
     * @return void
     */
    public function bootstrap()
    {
        if (!$this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers());
        }

        $this->app->loadDeferredProviders();

        if (!$this->commandsLoaded) {
            $this->commands();

            $this->registerCommands();

            $this->commandsLoaded = true;
        }
    }

    /**
     * 载入其他命令
     * @return void
     */
    protected function commands()
    {
        //
    }

    protected $artisan;

    /**
     * @return Artisan
     */
    public function getArtisan()
    {
        if (is_null($this->artisan)) {
            $this->artisan = (new Artisan($this->app, $this->events, $this->version))
                ->resolveCommands($this->commands);
            $this->artisan->setName('YZC');

            return $this->artisan;
        }

        return $this->artisan;
    }

    /**
     * 载入一个目录下的所有 command
     * @param array $paths [command 所在的文件夹 => 对应的命名空间]
     */
    public function load($paths = [])
    {
        $this->commandPaths = array_merge($this->commandPaths, $paths);
    }

    /**
     * 单独加载一个 command
     * @param string|\Symfony\Component\Console\Command\Command $command
     */
    public function addCommand($command)
    {
        $this->commands[] = $command;
    }

    /**
     * 注册命令
     */
    protected function registerCommands()
    {
        $this->normalizeCommandPaths();

        $finder = Finder::create()->files()->name('*Command.php')->in(array_keys($this->commandPaths));
        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            $className = $this->parseClassName($file);
            if ($className) {
                Artisan::starting(function (Artisan $artisan) use ($className) {
                    $artisan->resolve($className);
                });
            }
        }

        foreach ($this->commands as $command) {
            Artisan::starting(function (Artisan $artisan) use ($command) {
                $artisan->resolve($command);
            });
        }
    }

    /**
     * 格式化 $this->commandPaths 的路径格式
     */
    protected function normalizeCommandPaths()
    {
        $result = [];
        foreach ($this->commandPaths as $dir => $namespace) {
            $dir = FileHelper::normalizePath($dir);
            $namespace = $this->normalizeClassname($namespace);
            $result[$dir] = $namespace;
        }
        $this->commandPaths = $result;
    }

    /**
     * 解析文件获取 class 名
     * @param SplFileInfo $file
     * @return false|string|Command
     */
    protected function parseClassName(SplFileInfo $file)
    {
        $realpath = $this->normalizeClassname($file->getRealPath());
        $className = false;
        foreach ($this->commandPaths as $dir => $namespace) {
            $dir = $this->normalizeClassname($dir);
            if (strpos($realpath, $dir) === 0) {
                $className = rtrim($namespace . str_replace($dir, '', $realpath), '.php');
                break;
            }
        }

        if ($className == 'Framework\Console\Commands\IdeHelper\ModelsCommand') {
            if (!class_exists('Barryvdh\LaravelIdeHelper\Console\ModelsCommand')) {
                return false;
            }
        }

        if ($className && class_exists($className)) {
            return $className;
        }

        return false;
    }

    /**
     * 格式化为 namespace 路径
     * @param $namespace
     * @return string
     */
    protected function normalizeClassname($namespace)
    {
        return rtrim(str_replace('/', '\\', $namespace), '\\');
    }

    /**
     * @param Throwable $e
     * @return void
     */
    protected function reportException(Throwable $e)
    {
        $this->app[ExceptionHandler::class]->report(ExceptionUtil::coverThrowable2Exception($e));
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param Throwable $e
     * @return void
     */
    protected function renderException($output, Throwable $e)
    {
        $this->app[ExceptionHandler::class]->renderForConsole($output, ExceptionUtil::coverThrowable2Exception($e));
    }
}
