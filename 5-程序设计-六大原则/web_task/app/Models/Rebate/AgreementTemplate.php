<?php

namespace App\Models\Rebate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AgreementTemplate extends Model
{
    //
    protected $table = 'oc_rebate_agreement_template';
    protected $item_table = 'oc_rebate_agreement_template_item';

    /**
     * @param $keyVal
     * @return int
     */
    public function insertSingle($keyVal)
    {
        return DB::table($this->table)
            ->insertGetId($keyVal);
    }

    /**
     * @param $keyVal
     * @return int
     */
    public function insertItem($keyVal)
    {
        return DB::table($this->item_table)
            ->insertGetId($keyVal);
    }
}
