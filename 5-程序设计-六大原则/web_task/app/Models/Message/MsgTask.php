<?php

namespace App\Models\Message;

use Illuminate\Database\Eloquent\Model;

/**
 * Class MsgTask
 * @property  int $msg_id
 * @property  int $is_sent
 * @property  string $receiver_ids
 * @property-read \App\Models\Message\Msg $msg
 * @package App\Models\Message
 */
class MsgTask extends Model
{
    protected $table = 'oc_msg_task';

    public $timestamps = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function msg()
    {
        return $this->belongsTo(Msg::class, 'msg_id');
    }
}
