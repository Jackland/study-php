<?php

namespace App\Helper;

use Framework\Helper\StringHelper;

class ModuleHelper
{
    public static function isInAdmin()
    {
        return StringHelper::endsWith(DIR_APPLICATION, 'admin/');
    }

    public static function isInCatalog()
    {
        return StringHelper::endsWith(DIR_APPLICATION, 'catalog/');
    }
}
