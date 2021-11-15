<?php

namespace App\Repositories\Buyer;

use App\Models\TelephoneCountryCode;

class TelephoneCountryCodeRepository
{
    /**
     * description:获取列表
     * @param array $ids
     * @param array $field
     * @return array
     */
    public function getByIdsList(array $ids, array $field = ['id', 'code', 'desc_cn', 'desc_en'])
    {
        return TelephoneCountryCode::query()
            ->select($field)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id')
            ->toArray();
    }

    /**
     * description:获取表中所有
     * @param array $field
     * @return array
     */
    public function getAllList(array $field=["*"])
    {
        return TelephoneCountryCode::query()
            ->select($field)
            ->get()
            ->keyBy('id')
            ->toArray();
    }
}
