<?php

namespace Framework\Storage;

use Framework\Exception\InvalidConfigException;

class StorageManager
{
    protected $disks = [];
    /**
     * @var array|StorageManagerConfig[]
     */
    protected $configs;

    public function __construct(array $configs)
    {
        $this->configs = $configs;
    }

    /**
     * @param string $name
     * @return StorageInterface
     */
    public function disk(string $name): StorageInterface
    {
        if (!isset($this->disks[$name])) {
            $config = $this->configs[$name];
            if (is_array($config)) {
                $config = new StorageManagerConfig($config['adapter'], $config['config'] ?? []);
            }
            if (!$config instanceof StorageManagerConfig) {
                throw new InvalidConfigException('config.storage.disks 配置错误');
            }
            $this->disks[$name] = $config->getStorage();
        }

        return $this->disks[$name];
    }
}
