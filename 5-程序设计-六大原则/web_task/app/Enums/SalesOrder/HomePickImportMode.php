<?php

namespace App\Enums\SalesOrder;

use App\Enums\BaseEnum;

class HomePickImportMode extends BaseEnum
{
    // import mode
    const IMPORT_MODE_NORMAL = 0;
    const IMPORT_MODE_AMAZON = 4;
    const IMPORT_MODE_WAYFAIR = 5;
    //美国上门取货other导单 import_mode
    const US_OTHER  = 6;
    const IMPORT_MODE_WALMART = 7;

}
