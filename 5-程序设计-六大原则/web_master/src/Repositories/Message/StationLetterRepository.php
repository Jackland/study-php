<?php

namespace App\Repositories\Message;

use App\Enums\Common\YesNoEnum;
use App\Models\Message\StationLetter;
use App\Models\Message\StationLetterAttachment;
use App\Models\Message\StationLetterCustomer;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

class StationLetterRepository
{
    /**
     * 获取通知弹窗
     *
     * @param int customerId 用户ID
     * @return StationLetter[]|Collection
     */
    public function getPopUpLetters($customerId)
    {
        return StationLetter::query()->alias('sl')
            ->leftJoinRelations(['stationLetterCustomer as slc'])
            ->leftJoin('tb_sys_dictionary as d', function(JoinClause $q) {
                $q->on('sl.type', 'd.DicKey')
                    ->where('d.DicCategory', 'STATION_LETTER_TYPE');
            })
            ->select(['sl.title', 'sl.content', 'sl.send_time as publish_date', 'd.dicValue as type_name', 'sl.id as notice_id'])
            ->selectRaw('0 as top_status, 0 as make_sure_status, 1 as message_type')
            ->where('sl.is_delete', YesNoEnum::NO)
            ->where('sl.status', YesNoEnum::YES)
            ->where('sl.is_popup', YesNoEnum::YES)
            ->where('slc.customer_id', $customerId)
            ->where('slc.is_read', YesNoEnum::NO)
            ->where('slc.is_delete', YesNoEnum::NO)
            ->get();
    }

    /**
     * 获取未读通知数
     *
     * @param int $customerId 用户ID
     * @return int
     */
    public function getNewStationLetterCount(int $customerId)
    {
        return StationLetterCustomer::queryRead()->alias('slc')
            ->leftJoinRelations('stationLetter as sl')
            ->where('slc.customer_id', $customerId)
            ->where('slc.is_read', YesNoEnum::NO)
            ->where('slc.is_delete', YesNoEnum::NO)
            ->where('sl.is_delete', YesNoEnum::NO)
            ->where('sl.status', YesNoEnum::YES)
            ->count();
    }

    /**
     * 获取附件
     *
     * @param int $letterId
     * @return array
     */
    public function getStationLetterAttachments(int $letterId): array
    {
        return StationLetterAttachment::queryRead()->alias('ssla')
            ->join('tb_upload_file as uf', 'ssla.attachment_id', '=', 'uf.id')
            ->where('ssla.letter_id', $letterId)
            ->select(['uf.file_name', 'uf.url'])
            ->get()
            ->toArray();
    }
}
