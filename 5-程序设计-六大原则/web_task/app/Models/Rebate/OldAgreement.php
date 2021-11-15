<?php

namespace App\Models\Rebate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Class OldAgreement
 * @package App\Models\Rebate
 */
class OldAgreement extends Model
{
    //
    protected $table = 'tb_sys_rebate_contract';

    /**
     * @return \Illuminate\Support\Collection
     */
    public function list($id = 0)
    {
        return DB::table($this->table)
            ->when($id, function ($query) use ($id) {
                $query->where('id', $id);
            })
            ->get();
    }
}
