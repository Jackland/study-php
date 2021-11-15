<?php

namespace Framework\Storage;

use League\Flysystem\FilesystemException;

class UnableToDeleteDirectory extends \RuntimeException implements FilesystemException
{
}
