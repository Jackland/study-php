<?php

namespace App\Repositories\Customer;

use App\Helper\LangHelper;
use App\Models\Customer\TelephoneCountryCode;

class TelephoneCountryCodeRepository
{
    const CHINA_ID = 1; // 中国的 id

    /**
     * 获取用于 select 选择的 options
     * @return array
     */
    public function getSelectOptions(): array
    {
        $data = TelephoneCountryCode::query()->select(['id', 'code', 'desc_cn', 'desc_en'])->orderBy('create_time')->get();
        $isCN = LangHelper::isChinese();
        $result = [];
        foreach ($data as $item) {
            $result[] = [
                'code' => $item->code,
                'desc' => $isCN ? $item->desc_cn : $item->desc_en,
                'id' => $item->id,
            ];
        }
        return $result;
    }

    /**
     * code 是否是中国
     * @param int $telephoneCountryCodeId
     * @return bool
     */
    public function isChina(int $telephoneCountryCodeId): bool
    {
        return $telephoneCountryCodeId === self::CHINA_ID;
        // 通过 code 判断的形式
        //return TelephoneCountryCode::query()->where('id', $telephoneCountryCodeId)->select('code')->value('code') == 86;
    }

    /**
     * 根据 code 获取 id
     * 可能存在同 code 的情况，此时返回 id 较小的
     * @param int $code
     * @return int
     */
    public function getIdByCode(int $code): int
    {
        return TelephoneCountryCode::query()->where('code', $code)->limit(1)->select('id')->value('id') ?: 0;
    }

    /**
     * 根据 id 获取 code
     * @param int $id
     * @param int|null $default
     * @return int|null
     */
    public function getCodeById(int $id, ?int $default = 86): int
    {
        if ($id <= 0) {
            return $default;
        }
        return TelephoneCountryCode::query()->where('id', $id)->select('code')->value('code') ?: $default;
    }
}
