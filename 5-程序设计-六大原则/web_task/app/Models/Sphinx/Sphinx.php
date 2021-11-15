<?php

namespace App\Models\Sphinx;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
//use Illuminate\Database\Schema\Blueprint;

class Sphinx extends Model
{


    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public function updateSphinx(){
        \Log::info('--------Sphinx Stop---------' . PHP_EOL);
        $path = storage_path();
        $bat_path = $path. '\app\sphinx.bat';
        if(is_file($bat_path)){
            exec($bat_path);
        }
        \Log::info('--------Sphinx Start---------' . PHP_EOL);
    }

    public function mergeSphinx(){
        \Log::info('--------Sphinx merge---------' . PHP_EOL);
        $path = storage_path();
        if(getenv('ENV_SPHINX') == 'pro_test'){
            $bat_path = $path. '\app\sphinx_merge_test.bat';
            \Log::info('--------Sphinx merge test---------' . PHP_EOL);
        }else{
            $bat_path = $path. '\app\sphinx_merge.bat';

        }
        if(is_file($bat_path)){
            exec($bat_path);
        }
        \Log::info('--------Sphinx merge---------' . PHP_EOL);
    }







}
