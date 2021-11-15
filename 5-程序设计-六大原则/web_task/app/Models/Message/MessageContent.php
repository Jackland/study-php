<?php

namespace App\Models\Message;

use Illuminate\Database\Eloquent\Model;

class MessageContent extends Model
{
    protected $table = 'oc_message_content';
    protected $connection = 'mysql_proxy';
}

