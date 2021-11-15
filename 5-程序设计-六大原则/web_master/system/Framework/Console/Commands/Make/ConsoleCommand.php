<?php

namespace Framework\Console\Commands\Make;

use Framework\Console\Command;
use Framework\Console\Traits\MakeTrait;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;

class ConsoleCommand extends Command
{
    use MakeTrait;

    protected $name = 'make:command';
    protected $description = '创建 Command';
    protected $help = '';

    protected function configure()
    {
        $this->configMake('@root/src/Commands', __DIR__ . '/stub/console.stub');

        $this->addArgument('name', InputArgument::REQUIRED, '名称，例如：make:app');
    }

    public function handle()
    {
        $name = $this->argument('name');
        list($className, $dirPath) = $this->parseFromName($name);

        $data = [
            '{{className}}' => $className,
            '{{namespace}}' => $dirPath ? str_replace('/', '\\', '\\' . $dirPath) : '',
            '{{commandName}}' => $name,
        ];
        $this->generateFile($dirPath, $className, $data);

        return Command::SUCCESS;
    }

    /**
     * @param string $name
     * @return array [$className, $dirPath];
     */
    protected function parseFromName($name)
    {
        $nameArr = explode(':', $name);
        if (count($nameArr) === 1) {
            $className = $name;
            $dirPathArr = [];
        } else {
            $className = array_pop($nameArr);
            $dirPathArr = $nameArr;
        }

        $className = Str::studly(str_replace('-', '_', $className)) . 'Command';
        $dirPathArr = array_map(function ($name) {
            return Str::studly(str_replace('-', '_', $name));
        }, $dirPathArr);
        $dirPath = implode('/', $dirPathArr);

        return [$className, $dirPath];
    }
}
