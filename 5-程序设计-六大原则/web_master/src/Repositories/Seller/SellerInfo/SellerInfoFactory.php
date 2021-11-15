<?php

namespace App\Repositories\Seller\SellerInfo;

class SellerInfoFactory
{
    private $ids = [];

    /**
     * @param int|array $ids
     * @return $this
     */
    public function withIds($ids): self
    {
        $new = clone $this;
        $new->ids = !is_array($ids) ? [$ids] : $ids;

        return $new;
    }

    /**
     * 基础信息
     * @return BaseInfoRepository
     */
    public function getBaseInfoRepository(): BaseInfoRepository
    {
        return new BaseInfoRepository($this->ids);
    }
}
