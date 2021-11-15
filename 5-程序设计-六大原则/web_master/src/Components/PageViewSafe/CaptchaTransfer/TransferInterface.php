<?php

namespace App\Components\PageViewSafe\CaptchaTransfer;

interface TransferInterface
{
    public function getData(): array;

    public static function loadFromData(array $data): ?self;
}
