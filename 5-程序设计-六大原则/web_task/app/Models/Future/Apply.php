<?php

namespace App\Models\Future;

use App\Models\Message\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Apply extends Model {
    protected $table = 'oc_futures_agreement_apply';
    const AHEAD_OF_DELIVER = 1;
    const TYPE_APPEAL = 4; // 申诉
    const STATUS_PENDING = 0;
    const STATUS_REJECTED = 2;
    const STATUS_TIMEOUT = 3;
    const EXPIRY_DATE = 1;


    public static function applyTimeOut()
    {
        $map = [
            'apply_type'=> self::AHEAD_OF_DELIVER,
            'status'=> self::STATUS_PENDING,
        ];
        $data = \DB::table('oc_futures_agreement_apply')
            ->where($map)
            ->get()
            ->keyBy('agreement_id')
            ->toArray();
        if(!$data){
            return;
        }
        $collection = \DB::table('oc_futures_margin_delivery as d')
            ->leftJoin('oc_futures_margin_agreement as a', 'a.id', '=', 'd.agreement_id')
            ->whereIn('a.id',array_keys($data))
            ->select('a.*','d.*','a.id','a.update_time', 'd.id as delivery_id','d.update_time as de_update_time','p.sku','p.mpn')
            ->addSelect(['bc.customer_group_id','bc.country_id','bc.nickname','bc.user_number'])
            ->leftJoin('oc_customer as bc','a.buyer_id' ,'=', 'bc.customer_id')
            ->leftJoin('oc_product as p','a.product_id' ,'=', 'p.product_id')
            ->get();
        foreach($collection as $key => $value){
            $days = Agreement::getLeftDay($value->expected_delivery_date,$value->country_id);
            if($days == self::EXPIRY_DATE){
                // 提前交付拒绝
                $condition_seller['from'] = 0;
                $condition_seller['to'] = $value->seller_id;
                $condition_seller['country_id'] = $value->country_id;
                $condition_seller['status'] = null;
                $condition_seller['communication_type'] = 8;
                $condition_seller['info'] = $value;
                Agreement::addFuturesAgreementCommunication($value->id,8,$condition_seller);
                // 更新申请状态
                \DB::table('oc_futures_agreement_apply')
                    ->where('id', $data[$value->id]->id)
                    ->update([
                        'update_time' => date('Y-m-d H:i:s'),
                        'status' => self::STATUS_REJECTED,
                    ]);
                //更新message
                $message = [
                    'agreement_id'=> $value->id,
                    'customer_id' => 0,
                    'create_user_name' => 'System',
                    'create_time' => date('Y-m-d H:i:s'),
                    'apply_id'    => $data[$value->id]->id,
                    'message'     => 'The request to deliver ahead of time for future goods agreement ('.$value->agreement_no.') has been rejected.',
                ];
                \DB::table('oc_futures_margin_message')
                    ->insert($message);
                // 更新log
                $log['info'] = [
                    'agreement_id' => $value->id,
                    'customer_id' =>  0,
                    'type' => 28, //
                    'operator' =>  'System',
                ];
                $log['agreement_status'] = [$value->agreement_status, $value->agreement_status];
                $log['delivery_status'] = [$value->delivery_status,$value->delivery_status];
                Agreement::addAgreementLog($log['info'],
                    $log['agreement_status'],
                    $log['delivery_status']
                );
                // 更新协议状态
                \DB::table('oc_futures_margin_delivery')
                    ->where('agreement_id', $value->id)
                    ->update(['update_time' => date('Y-m-d H:i:s')]);
            }
        }
    }

    // 申诉申请超时
    public static function appealApplyTimeOut()
    {
        $map = [
            'apply_type' => self::TYPE_APPEAL,
            'status' => self::STATUS_PENDING,
        ];
        $data = \DB::table('oc_futures_agreement_apply')
            ->where($map)
            ->get()
            ->keyBy('agreement_id')
            ->toArray();
        if (!$data) {
            return;
        }
        $collection = \DB::table('oc_futures_margin_delivery as d')
            ->leftJoin('oc_futures_margin_agreement as a', 'a.id', '=', 'd.agreement_id')
            ->leftJoin('oc_customer as bc', 'a.buyer_id', '=', 'bc.customer_id')
            ->whereIn('a.id', array_keys($data))
            ->select('a.*', 'd.*', 'a.id', 'a.update_time', 'bc.country_id', 'd.id as delivery_id', 'd.update_time as de_update_time')
            ->get();
        foreach ($collection as $key => $value) {
            $days = Agreement::getLeftDay($value->expected_delivery_date, $value->country_id);
            if ($days <= 0) {
                $subject = "Your Force Majuere Claim for Future Goods Agreement (ID: $value->agreement_no ) has timed out.";
                $url = getenv('B2B_HOST') . 'index.php?route=account/product_quotes/futures/sellerFuturesBidDetail&id=' . $value->id;
                $content = '<table  border="0" cellspacing="0" cellpadding="0">';
                $content .= "<tr><td align='left'>Future Goods Agreement ID:</td> <td><a href='$url'>$value->agreement_no </a></td> </tr>";
                $content .= "<tr><td align='left'>Claim Status: </td><td>Timed out </td></tr>";
                $content .= '</table>';
                Message::addSystemMessage('bid_futures', $subject, $content, $value->seller_id);
                // 更新申请状态
                \DB::table('oc_futures_agreement_apply')
                    ->where('id', $data[$value->id]->id)
                    ->update([
                        'update_time' => date('Y-m-d H:i:s'),
                        'status' => self::STATUS_TIMEOUT,
                    ]);
            }
        }
    }
}

