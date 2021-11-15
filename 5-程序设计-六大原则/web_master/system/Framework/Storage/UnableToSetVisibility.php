<?php

namespace Framework\Storage;

use League\Flysystem\FilesystemException;

class UnableToSetVisibility extends \RuntimeException implements FilesystemException
{
}
