<?php

namespace App\Models\File;

use Illuminate\Database\Eloquent\Model;

class FileUpload extends Model
{
    //
    protected $table = 'oc_file_upload';
    public $timestamps = false;
    protected $connection = 'mysql_proxy';
    protected $primaryKey = 'file_upload_id';
}
