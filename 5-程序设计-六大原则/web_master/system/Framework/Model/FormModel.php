<?php

namespace Framework\Model;

use Framework\Model\Traits\FormModeTrait;
use Registry;

/**
 * @deprecated 不要使用，不稳定功能
 */
class FormModel extends BaseModel
{
    use FormModeTrait;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        $this->initFormAttributes();
    }
}
