<?php

namespace App\Components\UniqueGenerator\Traits;

use App\Components\UniqueGenerator\Enums\ServiceEnum;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

trait DatabaseCheckTrait
{
    use ServiceTrait;

    protected $checkDatabase = false;

    /**
     * @return $this
     */
    public function checkDatabase(): self
    {
        $this->checkDatabase = true;

        return $this;
    }

    /**
     * 检查数据库是否存在
     * @param string $value
     * @return bool
     */
    protected function checkDatabaseExist(string $value): bool
    {
        $this->checkServiceMust();

        $config = ServiceEnum::checkDatabaseExistConfig($this->service);
        if (is_array($config) && count($config) === 2) {
            /** @var Model $modelClass */
            list($modelClass, $attribute) = $config;
            return $modelClass::query()->where($attribute, $value)->exists();
        }
        if (is_callable($config)) {
            return call_user_func($config, $value);
        }

        throw new InvalidArgumentException('不支持的配置');
    }
}
