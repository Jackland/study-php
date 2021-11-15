<?php

namespace App\Components\UniqueGenerator;

use App\Components\UniqueGenerator\Traits\DatabaseCheckTrait;
use App\Components\UniqueGenerator\Traits\PrefixTrait;
use App\Components\UniqueGenerator\Traits\RandomTrait;
use RuntimeException;

/**
 * 全局生成
 */
class GlobalGenerator
{
    use PrefixTrait;
    use RandomTrait;
    use DatabaseCheckTrait;

    private $_maxLoop = 100;

    /**
     * 生成随机数
     * @return string
     */
    public function random(): string
    {
        $this->_maxLoop--;
        if ($this->_maxLoop <= 0) {
            throw new RuntimeException('Reach MAX loop!');
        }

        $this->checkDatabase(); // 全局随机，以数据库为基准，因此开启检查数据库

        $random = $this->getRandomInt();
        $value = $this->prefix . $this->padLeftDigit($random);
        if ($this->checkDatabase && $this->checkDatabaseExist($value)) {
            // db 重复
            return $this->random();
        }

        return $value;
    }
}
