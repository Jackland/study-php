<?php

namespace Framework\Storage;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemException;

interface StorageReadInterface
{
    /**
     * @param string $location
     * @return bool
     * @throws FilesystemException
     */
    public function fileExists(string $location): bool;

    /**
     * @param string $location
     * @return string
     * @throws FileNotFoundException
     * @throws FilesystemException
     */
    public function read(string $location): string;

    /**
     * @param string $location
     * @return resource
     * @throws FileNotFoundException
     * @throws FilesystemException
     */
    public function readStream(string $location);

    /**
     * @param string $location
     * @param bool $deep
     * @return array
     * @throws FilesystemException
     */
    public function listContents(string $location, bool $deep = false);

    /**
     * @param string $path
     * @return int
     * @throws FilesystemException
     */
    public function lastModified(string $path): int;

    /**
     * @param string $path
     * @return int
     * @throws FilesystemException
     */
    public function fileSize(string $path): int;

    /**
     * @param string $path
     * @return string
     * @throws FilesystemException
     */
    public function mimeType(string $path): string;

    /**
     * @param string $path
     * @return string
     * @throws FilesystemException
     */
    public function visibility(string $path): string;
}
