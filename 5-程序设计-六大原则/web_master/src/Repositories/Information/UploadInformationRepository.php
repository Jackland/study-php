<?php

namespace App\Repositories\Information;

use App\Models\Information\UploadInformation;

class UploadInformationRepository
{

    /**
     * description查找存在的文件记录 keyBy id形式
     * author: fuyunnan
     * @param array $ids
     * @param array $field
     * @return array
     * @throws
     * Date: 2021/6/23
     */
    public function getListPath($ids, $field = ['id', 'file_path'])
    {
        $data = UploadInformation::query()
            ->where(function ($q) use ($ids) {
                $q->whereIn('id', $ids);
            })
            ->get($field)
            ->keyBy('id')
            ->toArray();
        return $data;
    }

}
