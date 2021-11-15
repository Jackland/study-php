<?php

namespace App\Enums\File;

use Framework\Enum\BaseEnum;

class FileManageFileType extends BaseEnum
{
    const FILE_TYPE_IMAGE    = 1;
    const FILE_TYPE_DOCUMENT = 2;
    const FILE_TYPE_OTHER    = 3;
    const FILE_TYPE_DIR   = 4;

    const FILE_TYPE_ALL_DESC = 'all';
    const FILE_TYPE_IMAGE_DESC    = 'image';
    const FILE_TYPE_DOCUMENT_DESC    = 'document';
    const FILE_TYPE_OTHER_DESC    = 'other';
    const FILE_TYPE_DIR_DESC    = 'dir';

    const ID_DIR_FALSE = 0;
    const ID_DIR_TRUE = 1;

    /**
     * @param $type
     * @return int
     */
    public static function getFileType($type)
    {
        $array = [
            self::FILE_TYPE_IMAGE_DESC => self::FILE_TYPE_IMAGE,
            self::FILE_TYPE_DOCUMENT_DESC => self::FILE_TYPE_DOCUMENT,
            self::FILE_TYPE_OTHER_DESC => self::FILE_TYPE_OTHER,
            self::FILE_TYPE_DIR_DESC => self::FILE_TYPE_DIR,
        ];
        return $array[$type] ?? 0;
    }
}
