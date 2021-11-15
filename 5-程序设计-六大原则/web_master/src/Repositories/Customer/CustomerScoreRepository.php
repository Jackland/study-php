<?php

namespace App\Repositories\Customer;

use App\Components\Traits\RequestCachedDataTrait;
use App\Models\Customer\CustomerScore;

class CustomerScoreRepository
{
    use RequestCachedDataTrait;

    /**
     * 获取最新的 task_number
     * @return string|null
     */
    public function getLastTaskNumber(): ?string
    {
        return $this->requestCachedData([__CLASS__, __FUNCTION__, 'v1'], function () {
            $model = CustomerScore::query()
                ->select('task_number')
                ->orderByDesc('id')->first();
            if (!$model) {
                return null;
            }
            return $model->task_number;
        });
    }

    public function getValidScore()
    {

    }
}
