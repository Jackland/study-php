<?php

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Class ModelStationLetter
 *
 * @property ModelMessageMessageSetting $model_message_messageSetting
 */
class ModelStationLetterStationLetter extends Model
{
    protected $typeDicCategory = 'STATION_LETTER_TYPE';

    /**
     * 获取统计数量
     *
     * @param      $customerId
     * @param null $isRead
     * @param null $sendTime
     *
     * @return int
     */
    public function stationLetterCount($customerId, $isRead = null, $sendTime = null)
    {
        $model = $this->getStationLetterModel($customerId);
        $model = $model->where('ssl.is_delete', 0)->where('sslc.is_delete', 0)->where('ssl.status', 1);
        if (!is_null($isRead)) {
            $model = $model->where('sslc.is_read', $isRead);
        }
        if ($sendTime) {
            $model = $model->where('ssl.send_time', '>=', $sendTime);
        }
        return $model->count();
    }

    /**
     * 获取基础模型
     *
     * @param int $customerId
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function getStationLetterModel($customerId)
    {
        $model = $this->orm->connection('read')->table('tb_sys_station_letter_customer as sslc')
                           ->leftJoin('tb_sys_station_letter as ssl', 'sslc.letter_id', '=', 'ssl.id')
                           ->leftJoin('tb_sys_dictionary as sd', function ($join){
                               $join->on('ssl.type', '=', 'sd.DicKey')
                                    ->where('sd.DicCategory', '=', $this->typeDicCategory);
                           })
                           ->where('sslc.customer_id', $customerId);
        $model = $model->select([
                                    'sslc.letter_id as notice_id',
                                    'sslc.is_read as is_read',
                                    'sslc.is_marked as is_marked',
                                    'ssl.type as type_id',
                                    'ssl.title',
                                    DB::raw('0 as top_status'),//占位
                                    'ssl.send_time as publish_date',
                                    DB::raw('0 as effective_time'),//占位
                                    'ssl.content as content',
                                    'sd.DicValue as type_name',
                                    DB::raw('0 as make_sure_status'),//占位
                                    DB::raw('0 as p_make_sure_status'),//占位
                                    DB::raw('1 as message_type'), // 占位
                                    DB::raw("'station_letter' as data_model"),//数据类型
                                ]);
        return $model;
    }

}
