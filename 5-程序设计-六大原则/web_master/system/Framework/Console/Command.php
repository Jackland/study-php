<?php

namespace Framework\Console;

use Framework\Console\Traits\CommandInputTrait;
use Framework\Console\Traits\CommandOutputTrait;
use Framework\Foundation\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class Command extends \Symfony\Component\Console\Command\Command
{
    public const SUCCESS = 0;
    public const FAILURE = 1;

    use CommandInputTrait;
    use CommandOutputTrait;

    /**
     * 命令名称
     * @var string
     */
    protected $name;
    /**
     * 描述说明
     * @var string
     */
    protected $description;
    /**
     * 帮助说明
     * @var string
     */
    protected $help;

    /**
     * @var Application
     */
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;

        parent::__construct($this->name ?: static::class);

        if ($this->description) {
            $this->setDescription($this->description);
        }
        if ($this->help) {
            $this->setHelp($this->help);
        }
    }

    /**
     * @inheritDoc
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->output = $this->app->make(SymfonyStyle::class, ['input' => $input, 'output' => $output]);
        $this->input = $input;

        return parent::run($this->input, $this->output);
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->app->call([$this, 'handle']);
    }

    /**
     * 调用其他命令
     * @param string $command
     * @param array $arguments
     * @return int
     */
    public function call(string $command, array $arguments = [])
    {
        $arguments['command'] = $command;

        return $this->getApplication()->find($command)->run(
            new ArrayInput($arguments), $this->output
        );
    }
}
