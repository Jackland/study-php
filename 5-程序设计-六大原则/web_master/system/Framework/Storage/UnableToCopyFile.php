<?php

namespace Framework\Storage;

use League\Flysystem\FilesystemException;

class UnableToCopyFile extends \RuntimeException implements FilesystemException
{
}
