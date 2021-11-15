<?php

namespace Framework\Model\RequestForm;

use Framework\Model\BaseValidateModel;

abstract class RequestForm extends BaseValidateModel
{
    use AutoLoadAndValidateTrait;

    protected $request;

    public function __construct()
    {
        parent::__construct();

        $this->request = request();

        $this->autoLoadAndValidate();
    }
}
