<?php

namespace App\Repositories\Product\ProductInfo\Traits\BaseInfoRepository;

use App\Models\Product\Product;
use App\Repositories\Product\ProductInfo\BaseInfo;
use App\Repositories\Product\ProductInfo\Traits\BaseInfo\LoadRelationsTrait;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * BaseInfoRepository 支持给 BaseInfo 提供 loadRelations 功能
 */
trait LoadRelationsSupportTrait
{
    /**
     * @var Collection
     */
    private $baseModels;

    /**
     * @param array|BaseInfo[] $infos
     * @param Collection|Product[] $products
     */
    protected function supportLoadRelations(array $infos, Collection $products)
    {
        $models = new Collection();
        foreach ($infos as $id => $info) {
            $info->setLoadRelationsRepository($this);
            $models->add($products[$id]);
        }
        $this->setLoadRelationsCollection($models);
    }

    /**
     * 获取基础模型集合，用于调用 ->load() 或 ->loadMissing()
     * @return Collection
     * @see LoadRelationsTrait::loadRelations()
     */
    public function getLoadRelationsCollection(): Collection
    {
        if (!$this->baseModels) {
            throw new InvalidArgumentException('先调用 setLoadRelationsCollection 设置');
        }
        return $this->baseModels;
    }

    /**
     * @param Collection $models
     */
    private function setLoadRelationsCollection(Collection $models)
    {
        $this->baseModels = $models;
    }
}
