<?php

namespace Framework\Storage;

use League\Flysystem\FilesystemException;

interface StorageWriteInterface
{
    /**
     * @param string $location
     * @param string $contents
     * @param array $config
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function write(string $location, string $contents, array $config = []): void;

    /**
     * @param string $location
     * @param $contents
     * @param array $config
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function writeStream(string $location, $contents, array $config = []): void;

    /**
     * @param string $path
     * @param string $visibility
     * @throws UnableToSetVisibility
     * @throws FilesystemException
     */
    public function setVisibility(string $path, string $visibility): void;

    /**
     * @param string $location
     * @throws UnableToDeleteFile
     * @throws FilesystemException
     */
    public function delete(string $location): void;

    /**
     * @param string $location
     * @throws UnableToDeleteDirectory
     * @throws FilesystemException
     */
    public function deleteDirectory(string $location): void;

    /**
     * @param string $location
     * @param array $config
     * @throws UnableToCreateDirectory
     * @throws FilesystemException
     */
    public function createDirectory(string $location, array $config = []): void;

    /**
     * @param string $source
     * @param string $destination
     * @param array $config
     * @throws UnableToMoveFile
     * @throws FilesystemException
     */
    public function move(string $source, string $destination, array $config = []): void;

    /**
     * @param string $source
     * @param string $destination
     * @param array $config
     * @throws UnableToCopyFile
     * @throws FilesystemException
     */
    public function copy(string $source, string $destination, array $config = []): void;
}
