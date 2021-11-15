<?php

namespace App\Models\File;

use Illuminate\Database\Eloquent\Model;

class CustomerFileManage extends Model
{
    //
    protected $table = 'oc_customer_file_manage';
    public $timestamps = false;
    protected $connection = 'mysql_proxy';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'customer_id',
        'name',
        'parent_id',
        'file_path',
        'file_type',
        'is_dir',
        'file_suffix',
        'file_size',
        'is_del',
        'create_time',
        'update_time',
    ];
}
