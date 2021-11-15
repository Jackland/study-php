<?php

namespace App\Commands\Make;

use Framework\Console\Command;
use Framework\Console\Traits\MakeTrait;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ControllerCommand extends Command
{
    use MakeTrait;

    protected $name = 'make:controller';
    protected $description = '创建控制器';
    protected $help = '';

    protected function configure()
    {
        $this->configMake('@root/catalog/controller', null);
        $this
            ->addArgument(
                'name', InputArgument::REQUIRED, '名称，例如：account/fee_order'
            )
            ->addOption('action', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'action 操作', ['index'])
            ->addOption('seller', null, InputOption::VALUE_NONE, 'Seller 后台')
            ->addOption('withoutView', null, InputOption::VALUE_NONE, '不创建视图文件')
            ->addOption('modeNormal', null, InputOption::VALUE_NONE, '普通模式，默认为 webpack 模式（yzc_front）');
    }

    public function handle()
    {
        $originPath = $path = $this->argument('name');
        list($isSeller, $actions, $withoutView, $modeNormal) = $this->options(['seller', 'action', 'withoutView', 'modeNormal']);

        $stub = __DIR__ . '/stub/controller.stub';
        $layout = 'buyer';
        if ($isSeller) {
            $path = 'customerpartner/' . $path;
            $stub = __DIR__ . '/stub/controllerCustomerPartner.stub';
            $layout = 'seller';
        }

        $actionContents = [];
        $viewPathForViewCommand = [];
        $routes = [];
        foreach ($actions as $action) {
            $actionView = Str::snake($action);
            $viewPath = "{$path}/{$actionView}";
            $viewPathForViewCommand[] = "{$originPath}/{$actionView}";
            if ($modeNormal) {
                $actionContents[] = <<<TEXT
    // TODO 补充 action 说明
    public function {$action}()
    {
        \$data = [];
        // TODO 其他 data 参数

        return \$this->render('{$viewPath}', \$data, '{$layout}');
    }
TEXT;
            } else {
                // webpack 模式
                $actionContents[] = <<<TEXT
    // TODO 补充 action 说明
    public function {$action}()
    {
        return \$this->renderFront('{$viewPath}', '{$layout}');
    }
TEXT;
            }

            $routes[] = $path . '/' . $action;
        }

        // 生成控制器
        $this->generateFile(dirname($path), basename($path), [
            '{{className}}' => $this->getClassNameFromPath($path),
            '{{actions}}' => implode("\n\n", $actionContents),
        ], $stub);
        // 生成视图
        if (!$withoutView) {
            foreach ($viewPathForViewCommand as $viewPath) {
                $this->call('make:view', [
                    'name' => $viewPath,
                    '--seller' => $isSeller,
                    '--modeNormal' => $modeNormal,
                ]);
            }
        }

        foreach ($routes as $route) {
            $this->output->success('visit route: ' . $route);
        }

        return 0;
    }

    private function getClassNameFromPath(string $path)
    {
        return 'Controller' . Str::studly(str_replace('/', ' ', $path));
    }
}
