<?php

namespace App\Components\Replace;

class QueueWorkCommand extends \Illuminate\Queue\Console\WorkCommand
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
