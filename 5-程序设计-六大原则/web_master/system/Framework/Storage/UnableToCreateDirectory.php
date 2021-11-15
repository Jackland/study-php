<?php

namespace Framework\Storage;

use League\Flysystem\FilesystemException;

class UnableToCreateDirectory extends \RuntimeException implements FilesystemException
{
}
