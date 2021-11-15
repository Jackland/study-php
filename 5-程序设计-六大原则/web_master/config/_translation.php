<?php

use App\Enums\Common\LangLocaleEnum;

return [
    'path' => '@root/resources/lang',
    'locale' => LangLocaleEnum::getDefault(),
    'fallback_locale' => LangLocaleEnum::getFallback(),
    'default_category' => 'app',
];
