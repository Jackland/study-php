<?php
/**
 * Created by PHPSTORM.
 * User: yaopengfei
 * Date: 2020/8/18
 * Time: 18:57
 */

namespace App\Models\Future;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;


class FuturesContractLog extends Model
{
    protected $table = 'oc_futures_contract_log';
    public $timestamps = false;

    /**
     * 格式化合约变更记录的内容
     * @param array $changeData
     * @param Contract $contract
     * @return false|string
     */
    public static function formatContractLogContent(array $changeData, Contract $contract)
    {
        $contentKeys = ['status', 'is_bid', 'min_num', 'delivery_type', 'margin_unit_price', 'last_unit_price', 'payment_ratio'];
        $content = [];
        foreach ($contentKeys as $contentKey) {
            $content[$contentKey] = isset($changeData[$contentKey]) ? strval($changeData[$contentKey]) : '';
        }

        if (!empty($contract->id)) {
            foreach ($content as $k => $v) {
                if (!isset($contract->{$k})) {
                    continue;
                }
                if ($v !== '' && strval($contract->{$k}) !== strval($v)) {
                    $content[$k] = $contract->{$k} . '->' . $v;
                    continue;
                }
                $content[$k] = $contract->{$k};
            }
        }

        return json_encode($content);
    }

    /**
     * @param Contract $contract
     * @param int $type
     * @param string $content
     * @param string $operator
     * @return bool
     */
    public function insertLog(Contract $contract, int $type, string $content, string $operator)
    {
        return FuturesContractLog::query()->insert([
            'contract_id' => $contract->id,
            'customer_id' => $contract->seller_id,
            'type' => $type,
            'content' => $content,
            'operator' => $operator,
            'create_time' => Carbon::now(),
            'update_time' => Carbon::now(),
        ]);
    }

}