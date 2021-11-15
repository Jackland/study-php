<?php

namespace App\Services\File\Tool;

use App\Helpers\LoggerHelper;
use GuzzleHttp\Client;

/**
 * 文件处理类
 *
 * Class FileDeal
 * @package App\Services\File\Tool
 */
class FileDeal
{
    const B2B_DOWNLOAD_RUL = 'common/file/download/'; // yzc文件下载
    const B2B_MANAGE_FILE_URL = 'b2bmanage/api/resource/'; // 查询文件
    const TIMEOUT = 5; // 请求超时时间 5s

    /**
     * @param int $fileMenuId
     * @return array
     */
    public function getFileList(int $fileMenuId)
    {
        $files = [];
        if (! $fileMenuId) {
            return $files;
        }

        $url = config('app.b2b_manage_host') . self::B2B_MANAGE_FILE_URL . $fileMenuId; // 查询请求路径
        try {
            $http = new Client();
            $response = $http->get($url, ['timeout' => self::TIMEOUT, 'headers' => ['Authorization' => 'Bearer ' . config('app.b2b_manage_auth_token')]]);
            if ($response->getStatusCode() != 200) {
                LoggerHelper::logFile('Get请求B2B Manage查询文件失败：' . $response->getStatusCode());
                return $files;
            }
            $list = json_decode($response->getBody(), true);
            if (!isset($list['code']) || $list['code'] != 200 || ! isset($list['data']) || ! is_array($list['data'])) {
                LoggerHelper::logFile(['Get请求B2B Manage查询文件返回失败' => $list], 'info');
                return $files;
            }

            return $list['data'];
        } catch (\Exception $e) {
            LoggerHelper::logFile('Get请求B2B Manage查询文件发生错误：' . $e->getMessage());

            return $files;
        }
    }

    /**
     * 获取文件下载请求Url
     *
     * @param int $fileDetailId
     * @return string
     */
    public function getFileDownloadUrl(int $fileDetailId) {
        return config('app.b2b_url') . self::B2B_DOWNLOAD_RUL . base64_encode($fileDetailId . '/' . $this->getFileDownloadSign($fileDetailId));
    }

    /**
     * 生成三方文件下载需要校验文件签名
     *
     * @param int $fileDetailId
     * @return string
     */
    private function getFileDownloadSign(int $fileDetailId)
    {
        return strtoupper(sha1($fileDetailId . config('app.file_download_sign_key')));
    }
}