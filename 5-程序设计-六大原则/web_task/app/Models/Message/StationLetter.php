<?php

namespace App\Models\Message;

use App\Jobs\SendMail;
use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;


class StationLetter extends Model
{
    protected $table = "tb_sys_station_letter";

    protected $fillable = [
        'status'
    ];

    const UPDATED_AT = 'update_time';

    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'tb_sys_station_letter_customer', 'letter_id', 'customer_id', 'id',
                                    'customer_id');
    }

    /**
     * @param $letterId
     *
     * @return bool
     */
    public static function sendStationLetterEmail($letterId)
    {
        Log::info("通知类站内信发送开始:{$letterId}");
        $stationLetter = self::where('id', $letterId)->first();
        if (!$stationLetter) {
            Log::error("通知类站内信发送终止:数据不存在");
            return false;
        }
        if ($stationLetter->is_delete == 1 || $stationLetter->status == 1) {
            //已删除或者已发送
            Log::error("通知类站内信发送终止:状态错误,is_delete:{$stationLetter->is_delete},status:{$stationLetter->status}");
            return false;
        }
        try {
            if ($stationLetter->customers->count() == 0) {
                throw new Exception("通知类站内信发送终止:发送用户数为0");
            }
            //查询附件数据
            $attachList = DB::table('tb_sys_station_letter_attachment as ssla')
                ->leftJoin('tb_upload_file as uf', 'ssla.attachment_id', '=', 'uf.id')
                ->where('ssla.letter_id', $letterId)->get(['uf.file_name', 'uf.path', 'uf.url']);
            $attach = [];
            foreach ($attachList as $key => $item) {
                $attach[$key]['url'] = env('B2B_URL') . "message/station_letter/download&filename={$item->url}&maskname={$item->file_name}";
                $attach[$key]['name'] = $item->file_name;
            }
            foreach ($stationLetter->customers as $customer) {
                self::sendMail($stationLetter->type, $stationLetter->title, $stationLetter->content,
                    $customer->customer_id, $attach);
            }
            $stationLetter->update(['status' => 1]);
        } catch (\Exception $exception) {
            //异常了将状态改为0
            $stationLetter->update(['status' => 0]);
            Log::error("通知类站内信发送终止:{$exception->getMessage()}");
            return false;
        }
        return true;
    }

    public static function sendMail($msgType, $subject, $body, $receiverId, $attach = null)
    {
        $res = MessageSettting::getMessageEmailSettingByCustomerId($receiverId);
        $emailSetting = $res['email_setting'];
        $otherEmail = $res['other_email'];
        switch ($msgType) {
            case '1':
                if (empty($emailSetting['station_letter']['system'])) {
                    return false;
                }
                break;
            case '2':
                if (empty($emailSetting['station_letter']['shipping'])) {
                    return false;
                }
                break;
            case '3':
                if (empty($emailSetting['station_letter']['holiday'])) {
                    return false;
                }
                break;
            case '4':
                if (empty($emailSetting['station_letter']['policy'])) {
                    return false;
                }
                break;
            case '5':
                if (empty($emailSetting['station_letter']['other'])) {
                    return false;
                }
                break;
        }
        $data['body'] = $body;
        $from = '[From GIGACLOUD]';
        $data['subject'] = $from . $subject;
        $to = [];
        if ($emailSetting['sendmail']['default_email']) {
            $to[] = Customer::getCustomerInfoById($receiverId)->email;
        }
        if ($emailSetting['sendmail']['other_email']) {
            if (isset($otherEmail[0])) {
                $to[] = $otherEmail[0];
            }
            if (isset($otherEmail[1])) {
                $to[] = $otherEmail[1];
            }
        }
        if ($attach) {
            $data['attach'] = $attach;
        }
        $data['view_type'] = 2;//使用不改变img的模板
        if ($to) {
            $to = array_unique($to);
            foreach ($to as $item) {
                $data['to'] = $item;
                // 发送到邮件队列
                SendMail::dispatch($data);
            }
            return true;
        }
    }
}
