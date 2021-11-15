<?php

/**
 * Class ModelUploadFile
 */
class ModelUploadFile extends Model
{
    protected $table = DB_PREFIX . 'file_upload';

    /**
     * @param string $path 文件相对于根目录的路径
     * @return array|null
     */
    public function getFileUploadInfoByPath(string $path): ?array
    {
        $res = $this->orm
            ->table($this->table)
            ->where(['path' => $path])
            ->first();
        return $res ? get_object_vars($res) : null;
    }

    /**
     * 从给定的路径中获取实际路径
     * 主要用于oc_product_package_file ,oc_product_package_image，oc_product_package_video路径判断
     *
     * @param string $filePath
     * @param bool $http 是否添加http前缀
     * @return string
     */
    public function getRealPathByFilePath(string $filePath, bool $http = false): string
    {
        if (stripos($filePath, 'http') === 0) return $filePath;
        if (preg_match('/^(\d+)\/(\d+)\/(file|image|video)\/(.*)/', $filePath)) {
            $path = 'productPackage/' . $filePath;
        } else {
            $path = 'image/' . $filePath;
        }
        if ($http) $path = HTTPS_SERVER . $path;;

        return $path;
    }
}