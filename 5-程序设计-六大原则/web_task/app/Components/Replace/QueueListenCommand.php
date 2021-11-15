<?php

namespace App\Components\Replace;

use Illuminate\Queue\Console\ListenCommand;

class QueueListenCommand extends ListenCommand
{
    /**
     * @inheritDoc
     */
    protected function getQueue($connection)
    {
        // 默认监听所有
        return $this->option('queue') ?: $this->laravel['config']->get(
            "queue.worker_listen_queues", 'default'
        );
    }
}