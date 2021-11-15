<?php

namespace Framework\Storage;

use League\Flysystem\FilesystemException;

class UnableToMoveFile extends \RuntimeException implements FilesystemException
{
}
