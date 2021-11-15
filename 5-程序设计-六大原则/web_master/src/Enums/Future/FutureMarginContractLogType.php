<?php

namespace App\Enums\Future;

use Framework\Enum\BaseEnum;

class FutureMarginContractLogType extends BaseEnum
{
    const NEW = 1;
    const VERDICT = 2;
    const EDIT = 3;
    const AUTO_TERMINATE = 4;

    public static function getViewItems()
    {
        return [
            self::NEW => 'Create',
            self::VERDICT => 'Verdict',
            self::EDIT => 'Modify',
            self::AUTO_TERMINATE => 'Terminate',
        ];
    }
}
