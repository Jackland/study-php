<?php

namespace App\Commands\Make;

use Framework\Console\Command;
use Framework\Console\Traits\MakeTrait;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;

class EventCommand extends Command
{
    use MakeTrait;

    protected $name = 'make:event';
    protected $description = '创建事件';
    protected $help = '';

    protected function configure()
    {
        $this->configMake('@root/src/Listeners/Events', __DIR__ . '/stub/event.stub');
        $this->addArgument('name', InputArgument::REQUIRED, '事件名称，例如：ControllerBefore');
    }

    public function handle()
    {
        $eventName = $this->getClassName($this->argument('name'));

        $className = $eventName . 'Event';
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
