<?php

namespace App\Repositories\FeeOrder;

use App\Components\Traits\RequestCachedDataTrait;
use App\Models\StorageFee\StorageFeeMode;
use Illuminate\Support\Collection;
use kriss\bcmath\BCS;

class StorageFeeModeRepository
{
    use RequestCachedDataTrait;

    /**
     * 根据国家和天获取费率
     * @param int $countryId
     * @param $day
     * @return StorageFeeMode|null
     */
    public function getFeeModeByCountryAndDay($countryId, $day)
    {
        /** @var StorageFeeMode[]|Collection $feeModes */
        $feeModes = $this->requestCachedData([__CLASS__, __FUNCTION__, $countryId], function () use ($countryId) {
            $currentUseFeeModeVersion = $this->getCurrentUseFeeModeVersionByCountry($countryId);
            return StorageFeeMode::query()
                ->where('mode_version', $currentUseFeeModeVersion)
                ->where('country_id', $countryId)
                ->orderBy('fee_max_day', 'asc') // 确保按照天数从小到大
                ->get();
        });
        // 检查是否已经定义费率
        if ($feeModes->isEmpty()) {
            // 无费率
            return null;
        }
        $feeMode = $this->requestCachedData([__CLASS__, __FUNCTION__, $countryId, $day], function () use ($feeModes, $day) {
            // 计算单日的费率
            $feeModeUsed = false;
            foreach ($feeModes as $feeMode) {
                if ($feeMode->fee_max_day == -1) {
                    // 为 -1 时表示最大的单天费用
                    $feeModeUsed = $feeMode;
                    continue;
                }
                if ($day <= $feeMode->fee_max_day) {
                    // 取最小的适用单元
                    $feeModeUsed = $feeMode;
                    break;
                }
            }
            return $feeModeUsed;
        });
        return $feeMode === false ? null : $feeMode;
    }

    /**
     * 获取当前国家使用的计费模式 mode_version
     * @param int $countryId
     * @return int
     */
    public function getCurrentUseFeeModeVersionByCountry($countryId)
    {
        // 仓租费率调整说明（修改国别的仓租费率步骤）：
        // 1. 修改国别仓租版本：修改 oc_storage_fee_mode 表下该国别的所有时间范围的费率（即使某个时间段的费率无变化也需要新增一条），记得修改 mode_version 值
        // 2. 修改 oc_setting 表 key 为 storage_fee_mode 的字段中该国别的费率版本为新的 mode_version 值
        $feeMode = configDB('storage_fee_mode');
        return $feeMode[$countryId] ?? 1;
    }

    /**
     * 根据 feeMode 计算仓租费率
     * @param StorageFeeMode $feeMode
     * @return float|int 根据国别，将有所区别
     */
    public function getFeeByMode(StorageFeeMode $feeMode)
    {
        return $this->requestCachedData([__CLASS__, __FUNCTION__, $feeMode->id], function () use ($feeMode) {
            // 费率计算方式
            $map = [
                // 国别 => [保留小数位数，是否向上取整],
                '107' => [0, true],
            ];
            $config = [
                'scale' => 2,
                'ceil' => true,
            ];
            if (isset($map[$feeMode->country_id])) {
                $countryConfig = $map[$feeMode->country_id];
                $config['scale'] = $countryConfig[0];
                $config['ceil'] = $countryConfig[1];
            }

            $storageFee = BCS::create($feeMode->storage_fee, $config);
            if ($feeMode->consume_fee_percent > 0) {
                // 消费税大于 0 时需要乘消费税
                $storageFee->mul(1 + (float)$feeMode->consume_fee_percent);
            }
            return $storageFee->getResult();
        });
    }
}
