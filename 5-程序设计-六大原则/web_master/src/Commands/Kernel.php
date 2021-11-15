<?php

namespace App\Commands;

use Framework\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function commands()
    {
        $this->load([
            __DIR__ => 'App\\Commands',
        ]);

        $commands = [];
        foreach ($commands as $command) {
            $this->addCommand($command);
        }
    }
}
