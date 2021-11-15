<?php

namespace Framework\Console\Traits;

use Symfony\Component\Console\Style\SymfonyStyle;

trait CommandOutputTrait
{
    /**
     * @var SymfonyStyle
     */
    protected $output;

    protected function confirm($question, $default = false)
    {
        return $this->output->confirm($question, $default);
    }

    protected function choice($question, array $choices, $default = null)
    {
        return $this->output->choice($question, $choices, $default);
    }

    protected function writeln($messages)
    {
        $this->output->writeln($messages);
    }

    protected function writeSuccess($message)
    {
        $this->output->success($message);
    }

    protected function writeError($message)
    {
        $this->output->error($message);
    }

    protected function writeNote($message)
    {
        $this->output->note($message);
    }
}
