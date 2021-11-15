<?php

namespace App\Enums\File;

use Framework\Enum\BaseEnum;

class FileManageFilePostfix extends BaseEnum
{
    //图片类型
    const IMAGE_JPG = 'jpg';
    const IMAGE_JPEG = 'jpeg';
    const IMAGE_GIF = 'gif';
    const IMAGE_PNG = 'png';

    //文档类型
    const DOCUMENT_DOC = 'doc';
    const DOCUMENT_DOCX = 'docx';
    const DOCUMENT_XLS = 'xls';
    const DOCUMENT_XLSX = 'xlsx';
    const DOCUMENT_PDF = 'pdf';
    const DOCUMENT_PPT = 'ppt';
    const DOCUMENT_PPTX = 'pptx';
    const DOCUMENT_TXT = 'txt';

    //其他  --  暂无

    //图片类型
    public static function getImageTypes()
    {
        return [self::IMAGE_JPG, self::IMAGE_JPEG, self::IMAGE_GIF, self::IMAGE_PNG];
    }

    //文档类型
    public static function getDocumentTypes()
    {
        return [
            self::DOCUMENT_DOC, self::DOCUMENT_DOCX, self::DOCUMENT_XLS, self::DOCUMENT_XLSX,
            self::DOCUMENT_PDF, self::DOCUMENT_PPT, self::DOCUMENT_TXT, self::DOCUMENT_PPTX,
        ];
    }

    //其它类型 暂无
    public static function getOtherTypes()
    {
        return [];
    }

    //图片+文档类型  剔除其他
    public static function getImageAndDocumentTypes()
    {
        return array_merge(self::getImageTypes(), self::getDocumentTypes());
    }

    //全部类型
    public static function geAllTypes()
    {
        return array_merge(self::getImageTypes(), self::getDocumentTypes(), self::getOtherTypes());
    }


}
