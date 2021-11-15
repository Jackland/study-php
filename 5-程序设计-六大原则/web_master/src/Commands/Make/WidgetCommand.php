<?php

namespace App\Commands\Make;

use Framework\Console\Command;
use Framework\Console\Traits\MakeTrait;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class WidgetCommand extends Command
{
    use MakeTrait;

    protected $name = 'make:widget';
    protected $description = '创建视图组件文件';
    protected $help = '';

    protected function configure()
    {
        $this->configMake('@root/src/Widgets', __DIR__ . '/stub/widget.stub');
        $this->addArgument('name', InputArgument::REQUIRED, '组件名，例如：newWidget 或 path/new_widget');
        $this->addOption('config','c', InputOption::VALUE_OPTIONAL, '配置参数，例如：name,key,label');
        $this->addOption('withView', '', InputOption::VALUE_NONE, '是否带视图');
    }

    public function handle()
    {
        $name = $this->argument('name');

        $className = $this->getClassName($name);
        $className = basename($className);
        if ($namespace = dirname($name)) {
            if ($namespace === '.' || $namespace === '../') {
                $namespace = '';
            } else {
                $namespace = str_replace('/', '\\', $namespace);
            }
        }

        $this->generateFile('', $className, [
            '{{namespace}}' => $namespace ? ('\\' . $namespace) : '',
            '{{className}}' => $className,
            '{{returnResult}}' => $this->getReturnResult($name),
            '{{attributes}}' => $this->getAttributes(),
        ]);

        return 0;
    }

    private function getReturnResult($name)
    {
        $withView = $this->option('withView');
        if ($withView) {
            $viewName = $this->getViewName($name);
            $this->generateFile('@root/resources/views/widgets', $viewName, [], __DIR__ . '/stub/widget_view.stub');
            return "\$this->getView()->render('@widgets/{$viewName}', [])";
        }

        return '\'some content\'';
    }

    private function getAttributes()
    {
        $configs = $this->option('config');
        if ($configs) {
            $configs = implode("\n", array_map(function ($item) {
                    return <<<TXT
    /**
     * @var string
     */
    public \${$item};
TXT;
                }, explode(',', $configs)));
            $configs = "\n" . $configs . "\n";
        }
        return $configs;
    }

    private function getClassName($name)
    {
        $name = implode('/', array_map(function ($n) {
            return Str::studly($n);
        }, explode('/', $name)));
        if (!Str::endsWith($name, 'Widget')) {
            $name .= 'Widget';
        }
        return $name;
    }

    private function getViewName($name)
    {
        $name = implode('/', array_map(function ($n) {
            return Str::snake($n);
        }, explode('/', $name)));
        if (Str::endsWith($name, '_widget')) {
            $name = Str::substr($name, 0, strlen($name) - strlen('_widget'));
        }
        return $name . '.twig';
    }
}
