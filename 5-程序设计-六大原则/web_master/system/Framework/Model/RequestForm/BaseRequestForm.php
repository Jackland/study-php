<?php

namespace Framework\Model\RequestForm;

use Framework\Model\BaseValidateModel;

/**
 * @deprecated use BaseValidateModel
 */
abstract class BaseRequestForm extends BaseValidateModel
{
    /**
     * @var \Framework\Http\Request
     */
    protected $request;

    public function __construct()
    {
        parent::__construct();

        $this->request = request();
    }
}
