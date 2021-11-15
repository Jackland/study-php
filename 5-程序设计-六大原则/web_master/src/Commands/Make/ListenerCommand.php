<?php

namespace App\Commands\Make;

use Framework\Console\Command;
use Framework\Console\Traits\MakeTrait;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;

class ListenerCommand extends Command
{
    use MakeTrait;

    protected $name = 'make:listener';
    protected $description = '创建 Listener 文件';
    protected $help = '';

    protected function configure()
    {
        $this->configMake('@root/src/Listeners', __DIR__ . '/stub/listener.stub');
        $this->addArgument('name', InputArgument::REQUIRED, '需要监听的事件名称，例如：QueryExecuted');
    }

    public function handle()
    {
        $eventName = $this->getClassName($this->argument('name'));

        $className = $eventName . 'Listener';
        $this->generateFile('', $className, [
            '{{className}}' => $className,
            '{{eventName}}' => $eventName,
        ]);

        return 0;
    }

    private function getClassName($name)
    {
        return Str::studly($name);
    }
}
