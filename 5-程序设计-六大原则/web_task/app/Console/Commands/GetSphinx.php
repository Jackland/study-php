<?php

namespace App\Console\Commands;
use App\Models\Sphinx\Sphinx;
use Illuminate\Console\Command;

class GetSphinx extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:sphinx {type?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sphinx update';

    /**
     * Create a new command instance.
     *
     *
     */
    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $type = $this->argument('type');
        $types = ['update', 'merge'];
        $type = empty($type) || !in_array($type, $types) ? 'update' : $type;
        $sphinx = new Sphinx();
        if ($type == 'update') {
            $sphinx->updateSphinx();
        } else {
            $sphinx->mergeSphinx();
        }
        echo date('Y-m-d H:i:s')
            . ' get:sphinx '.$type.' mission success!'  . PHP_EOL;
        return;
    }



}