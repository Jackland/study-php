<?php

namespace Framework\Model;

use Framework\Helper\RegistryAnnotationTrait;
use Registry;

class BaseModel
{
    use RegistryAnnotationTrait;

    /**
     * @var Registry $registry
     */
    protected $registry;

    /**
     * Model constructor.
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function __get($key)
    {
        return $this->registry->get($key);
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        $this->registry->set($key, $value);
    }
}
