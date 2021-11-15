<?php

namespace Framework\Storage;

use League\Flysystem\FilesystemException;

class UnableToWriteFile extends \RuntimeException implements FilesystemException
{
}
