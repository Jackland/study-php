<?php

namespace Framework\Storage;

use League\Flysystem\FilesystemException;

class UnableToDeleteFile extends \RuntimeException implements FilesystemException
{
}
