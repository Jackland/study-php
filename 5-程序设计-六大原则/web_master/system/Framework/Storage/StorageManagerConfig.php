<?php

namespace Framework\Storage;

use Framework\Exception\InvalidConfigException;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;

class StorageManagerConfig
{
    /**
     * @var AdapterInterface|callable
     */
    private $adapter;
    /**
     * @var Config|callable|null|array
     */
    private $config;

    public function __construct($adapter, $config = null)
    {
        $this->adapter = $adapter;
        $this->config = $config;
    }

    public function getStorage(): StorageInterface
    {
        return new Storage($this->getAdapter(), $this->getFilesystemConfig());
    }

    protected function getAdapter()
    {
        if (is_callable($this->adapter)) {
            $this->adapter = call_user_func($this->adapter);
        }
        if ($this->adapter instanceof AdapterInterface) {
            return $this->adapter;
        }
        throw new InvalidConfigException('adapter 必须是 League\Flysystem\AdapterInterface 的实例');
    }

    protected function getFilesystemConfig()
    {
        if (!$this->config) {
            $this->config = new Config();
        } elseif (is_array($this->config)) {
            $this->config = new Config($this->config);
        } elseif (is_callable($this->config)) {
            $this->config = call_user_func($this->config);
        }
        if ($this->config instanceof Config) {
            return $this->config;
        }
        throw new InvalidConfigException('config 必须是 League\Flysystem\Config 的实例');
    }
}
