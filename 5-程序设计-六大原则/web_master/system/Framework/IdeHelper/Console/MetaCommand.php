<?php

namespace Framework\IdeHelper\Console;

use Barryvdh\LaravelIdeHelper\Factories;
use Framework\Foundation\Application;
use Symfony\Component\Console\Output\OutputInterface;

class MetaCommand extends \Barryvdh\LaravelIdeHelper\Console\MetaCommand
{
    /**
     * @var Application
     */
    protected $laravel;

    protected $methods = [
        '\Illuminate\Contracts\Container\Container::make(0)',
        '\Illuminate\Contracts\Container\Container::makeWith(0)',
        '\Framework\Foundation\Application::get(0)',
        '\app(0)',
        '\resolve(0)',
    ];

    /**
     * 覆盖原方法，修改部分逻辑
     * @inheritDoc
     */
    public function handle()
    {
        //parent::handle();

        // Needs to run before exception handler is registered
        $factories = $this->config->get('ide-helper.include_factory_builders') ? Factories::all() : [];

        $this->registerClassAutoloadExceptions();

        $bindings = array();
        $abstracts = $this->getAbstracts();
        $servicesAliases = $this->getServicesAliases();
        $di = collect()
            ->merge($abstracts)
            ->merge(array_values($servicesAliases))
            ->merge(array_keys($servicesAliases));

        foreach ($di->unique()->toArray() as $abstract) {
            // Validator and seeder cause problems
            // 修改此处，因为 validator 可用
            /*if (in_array($abstract, ['validator', 'seeder'])) {
                continue;
            }*/
            try {
                $concrete = $this->laravel->make($abstract);
                if ($this->needAddBinds($abstract, $concrete)) {
                    $bindings[$abstract] = get_class($concrete);
                }
            } catch (\Throwable $e) {
                if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $this->comment("Cannot make '$abstract': " . $e->getMessage());
                }
            }
        }

        $content = $this->view->make('meta', [
            'bindings' => $bindings,
            'methods' => $this->methods,
            'factories' => $factories,
        ])->render();

        $filename = $this->option('filename');
        $written = $this->files->put($filename, $content);

        if ($written !== false) {
            $this->info("A new meta file was written to $filename");
        } else {
            $this->error("The meta file could not be created at $filename");
        }
    }

    protected function needAddBinds($abstract, $concrete): bool
    {
        $reflectionClass = new \ReflectionClass($concrete);
        if (is_object($concrete) && !$reflectionClass->isAnonymous()) {
            if ($abstract === get_class($concrete)) {
                //abstract 和 concrete 一致时，不添加注释，因为通过 @ 可以做到代码提示
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * 获取所有 aliases
     * @link https://github.com/barryvdh/laravel-ide-helper/pull/471/files
     * @return array
     */
    protected function getServicesAliases()
    {
        if (method_exists($this->laravel, 'getAliases')) {
            return $this->laravel->getAliases();
        }

        $reflected_container = new \ReflectionObject($this->laravel);

        $aliases = $reflected_container->getProperty('aliases');
        $aliases->setAccessible(true);

        return $aliases->getValue($this->laravel);
    }
}
