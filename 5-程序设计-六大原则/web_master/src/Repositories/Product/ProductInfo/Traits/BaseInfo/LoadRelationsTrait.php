<?php

namespace App\Repositories\Product\ProductInfo\Traits\BaseInfo;

use App\Repositories\Product\ProductInfo\BaseInfoRepository;
use InvalidArgumentException;

/**
 * BaseInfo 支持 loadRelations
 */
trait LoadRelationsTrait
{
    /**
     * @var BaseInfoRepository|null|false
     */
    private $loadRelationsRepository = false;

    /**
     * 设置 $repository 为 null 时会自动忽略 loadRelations，一般仅在单独 new BaseInfo 时使用，多模型请使用 BaseInfoRepository
     * @param BaseInfoRepository|null $repository
     */
    public function setLoadRelationsRepository(?BaseInfoRepository $repository)
    {
        $this->loadRelationsRepository = $repository;
    }

    /**
     * 加载模型的关联关系
     * @param string|array $relations
     */
    private function loadRelations($relations)
    {
        if ($this->loadRelationsRepository === false) {
            throw new InvalidArgumentException('Must setLoadRelationsRepository first');
        }
        if ($this->loadRelationsRepository === null) {
            return;
        }
        $this->loadRelationsRepository->getLoadRelationsCollection()->loadMissing($relations);
    }
}
