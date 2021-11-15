<?php

namespace App\Listeners\Events;

class CustomerLoginFailedEvent
{
    const TYPE_ACCOUNT_NOT_EXIST = 1;
    const TYPE_PASSWORD_ERROR = 2;

    public $type;
    public $account;
    public $password;

    public function __construct(int $type, string $account, ?string $password)
    {
        $this->type = $type;
        $this->account = $account;
        $this->password = $password;
    }
}
