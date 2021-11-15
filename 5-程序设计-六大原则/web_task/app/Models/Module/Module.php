<?php

namespace App\Models\Module;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $table = 'oc_module';

    public $timestamps = false;

    protected $fillable = [
        'setting'
    ];
}
