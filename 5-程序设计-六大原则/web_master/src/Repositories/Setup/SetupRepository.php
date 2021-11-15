<?php

namespace App\Repositories\Setup;

use App\Components\Traits\RequestCachedDataTrait;
use App\Models\Setup\Setup;

/**
 * sys_setup表相关查询
 *
 * Class SetupRepository
 * @package App\Repositories\Setup
 */
class SetupRepository
{
    use RequestCachedDataTrait;

    /**
     * 从setup表中获取数据
     *
     * @param $parameterKey
     *
     * @return mixed|string|null
     */
    public function getValueByKey($parameterKey)
    {
        if(!$parameterKey){
            return '';
        }
        $key = [__CLASS__, __FUNCTION__, $parameterKey];
        if ($data = $this->getRequestCachedData($key)) {
            return $data;
        }
        $data = Setup::query()->where('parameter_key', $parameterKey)->value('parameter_value');
        $this->setRequestCachedData($key, $data);
        return $data;
    }
}
