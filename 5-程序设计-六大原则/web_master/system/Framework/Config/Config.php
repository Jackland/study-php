<?php

namespace Framework\Config;

use Framework\Exception\InvalidConfigException;

class Config
{
    private $data = [];

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        return (isset($this->data[$key]) ? $this->data[$key] : null);
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function has($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->data;
    }

    /**
     * @param string $filename
     */
    public function load($filename)
    {
        $file = DIR_CONFIG . $filename . '.php';

        if (!file_exists($file)) {
            throw new InvalidConfigException("config file: {$file}, Not Exist!");
        }

        $_ = [];

        require(modification($file));

        $this->data = array_merge($this->data, $_);
    }
}
