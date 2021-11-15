<?php

namespace App\Commands\Make;

use Framework\Console\Command;
use Framework\Console\Traits\MakeTrait;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;

class SubscriberCommand extends Command
{
    use MakeTrait;

    protected $name = 'make:subscriber';
    protected $description = '创建 Subscriber 文件';
    protected $help = '';

    protected function configure()
    {
        $this->configMake('@root/src/Listeners', __DIR__ . '/stub/subscriber.stub');
        $this->addArgument('name', InputArgument::REQUIRED, '需要监听的事件组名称，例如：ViewFactory');
    }

    public function handle()
    {
        $eventName = $this->getClassName($this->argument('name'));

        $className = $eventName . 'EventSubscriber';
        $this->generateFile('', $className, [
            '{{className}}' => $className,
        ]);

        return 0;
    }

    private function getClassName($name)
    {
        return Str::studly($name);
    }
}
