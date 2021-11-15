<?php

namespace Framework\Console\Commands\Make;

use Framework\Console\Command;
use Framework\Console\Traits\MakeTrait;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;

class ServiceCommand extends Command
{
    use MakeTrait;

    protected $name = 'make:service';
    protected $description = '创建 Service';
    protected $help = '';

    protected function configure()
    {
        $this->configMake('@root/src/Services', __DIR__ . '/stub/service.stub');

        $this->addArgument('className', InputArgument::REQUIRED, '名称，例如：product/product_set_info 或 Order/Order');
    }

    public function handle()
    {
        $className = $this->argument('className');
        list($namespace, $className) = $this->parseClassName($className);

        if (!Str::endsWith($className, 'Service')) {
            $className .= 'Service';
        }

        $data = [
            '{{className}}' => $className,
            '{{namespace}}' => $namespace ? ('\\' . $namespace) : '',
        ];
        $this->generateFile($namespace, $className, $data);

        return Command::SUCCESS;
    }
}
