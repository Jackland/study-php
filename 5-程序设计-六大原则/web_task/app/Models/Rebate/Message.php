<?php

namespace App\Models\Rebate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Class Message
 * @package App\Models\Rebate
 */
class Message extends Model
{
    protected $table = 'oc_rebate_message';

    /**
     * 迁移 数据
     */
    public function oldToNew()
    {
        $i = 1;
        DB::table('tb_sys_rebate_message')
            ->orderBy('id')
            ->chunk(100, function ($rows) use (&$i) {
                $keyValues = [];
                $j = 0;
                foreach ($rows as $row) {
                    $keyValues[] = [
                        'id' => $row->id,
                        'agreement_id' => $row->contract_key,
                        'writer' => $row->writer,
                        'message' => $row->message,
                        'create_time' => $row->create_time,
                        'memo' => $row->memo,
                    ];
                    $j++;
                }
                DB::table($this->table)->insert($keyValues);
                $end = $i + $j;
                echo "当前: $i ~ $end " . PHP_EOL;
                $i += $j;
            });
    }
}
