<?php

namespace App\Models\Rebate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Class Template
 * @package App\Models\Rebate
 */
class Template extends Model
{
    protected $table = 'oc_rebate_template';

    protected $item_table = 'oc_rebate_template_item';

    /**
     * @param $keyVal
     */
    public function insertSingle($keyVal)
    {
        DB::table($this->table)
            ->insert($keyVal);
    }

    /**
     * @param $keyVal
     */
    public function insertItem($keyVal)
    {
        DB::table($this->item_table)
            ->insert($keyVal);
    }

    /**
     * @param $id
     * @return \Illuminate\Database\Query\Builder|mixed
     */
    public function getTemplate($id)
    {
        return DB::table($this->table)

            ->find($id);
    }

    /**
     * @param $template_id
     * @return object|null
     */
    public function getItem($template_id)
    {
        return DB::table($this->item_table)
            ->where('template_id', $template_id)
            ->first();
    }
}
