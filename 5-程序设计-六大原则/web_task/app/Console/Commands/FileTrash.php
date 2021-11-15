<?php


namespace App\Console\Commands;


use App\Services\File\FileService;
use Illuminate\Console\Command;

class FileTrash extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'file:trash';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '软删除的文件资源,转到垃圾箱';



    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        echo date("Y-m-d H:i:s", time()) . ' -----trash-file-start------' . PHP_EOL;
        app(FileService::class)->moveToTrash();
        echo date("Y-m-d H:i:s", time()) . ' ------trash-file-end------' . PHP_EOL;
    }
}