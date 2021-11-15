<?php

namespace App\Components\Replace;

use Illuminate\Database\MigrationServiceProvider;
use Illuminate\Foundation\Providers\ComposerServiceProvider;

class ConsoleSupportServiceProvider extends \Illuminate\Foundation\Providers\ConsoleSupportServiceProvider
{
    protected $providers = [
        ArtisanServiceProvider::class, // 替换实现
        MigrationServiceProvider::class,
        ComposerServiceProvider::class,
    ];
}