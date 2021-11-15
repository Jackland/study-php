<?php

namespace App\Jobs;

use App\Helpers\LoggerHelper;
use App\Mail\MessageAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 30;
    public $sleep = 60;
    public $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data = '')
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $res = \Mail::to($this->data['to'])
                ->send(new MessageAlert($this->data));
        } catch (\Exception $e) {
            LoggerHelper::logEmail([__CLASS__ => [
                'to' => $this->data['to'],
                'error' => $e->getMessage(),
            ]], 'error');
        }
    }
}
