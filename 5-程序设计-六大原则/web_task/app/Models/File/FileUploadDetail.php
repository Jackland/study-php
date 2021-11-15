<?php

namespace App\Models\File;

use Illuminate\Database\Eloquent\Model;

class FileUploadDetail extends Model
{
    protected $table = 'tb_file_upload_detail';
    protected $connection = 'mysql_proxy';
}
