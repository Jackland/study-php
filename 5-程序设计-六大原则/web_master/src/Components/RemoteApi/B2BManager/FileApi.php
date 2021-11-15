<?php

namespace App\Components\RemoteApi\B2BManager;

use App\Components\RemoteApi\B2BManager\DTO\FileDTO;
use App\Components\RemoteApi\B2BManager\DTO\FileListDTO;
use App\Logging\Logger;
use Framework\Foundation\HttpClient\FormDataPart;
use Framework\Helper\Json;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\TextPart;

/**
 * 基本上传流程：
 *     前端提交表单或异步提交文件上传
 *     ->调用upload()方法上传文件获取 menuId
 *     ->继续追加上传(可选)
 *     ->提交表单时调用 confirmUpload() 确认上传的文件，保存表单数据
 * 基本编辑流程：
 *     进入编辑页面时
 *     ->调用 getByMenuId() 获取文件列表 或 调用 copyByMenuId()重新生成新的 menuId 并获取所有文件列表（需要保留原文件历史记录的情况）
 *     ->追加上传/删除文件等操作
 *     ->提交表单时调用 confirmUpload() 确认上传的文件，保存表单数据
 */
class FileApi extends BaseB2BManagerApi
{
    /**
     * 上传文件
     * @param string $resourceType FileResourceTypeEnum 值
     * @param array|string|UploadedFile[]|UploadedFile $files 本地文件绝对路径
     * @param int|null $menuId 不为 null 时为追加操作
     * @param array $extra 扩展信息，key 需要和 files 的 key 保持一致，值可以是数组
     * @return FileListDTO
     */
    public function upload(string $resourceType, $files, ?int $menuId = null, array $extra = []): FileListDTO
    {
        $files = Arr::wrap($files);
        $files = array_map(function ($item) {
            if ($item instanceof UploadedFile) {
                $path = $item->getRealPath();
                $filename = $item->getClientOriginalName();
            } else {
                $path = $item;
                $filename = basename($item);
            }
            if (!file_exists($path)) {
                throw new RuntimeException('文件不存在:' . $item);
            }
            return DataPart::fromPath($path, $filename);
        }, $files);
        if ($extra) {
            if (count($extra) !== count($files)) {
                throw new InvalidArgumentException('extra 必须和 files 数组大小一致');
            }
            $extra = array_map(function ($item) {
                if (is_array($item)) {
                    $item = Json::encode($item);
                }
                if (is_float($item) || is_int($item)) {
                    $item = (string)$item;
                }
                if (!is_string($item)) {
                    throw new InvalidArgumentException('extra 的值必须是数组或字符串:' . $item);
                }
                return new TextPart($item);
            }, $extra);
        }
        $fields = [
            ['resourceTypeEnum' => $resourceType]
        ];
        if ($menuId) {
            array_push($fields, ['menuId' => (string)$menuId]);
        }
        foreach ($files as $item) {
            array_push($fields, ['files' => $item]);
        }
        foreach ($extra as $item) {
            array_push($fields, ['reservedFields' => $item]);
        }
        $formData = new FormDataPart($fields);

        $data = $this->api('/api/resource', [
            'headers' => $formData->getPreparedHeaders()->toArray(),
            'body' => $formData->bodyToIterable(),
            'timeout' => 300, // 此处指定网络超时时间，避免同时上传多个大文件时由于获取请求结果超时而报错的问题
        ], 'POST');

        return new FileListDTO([
            'menuId' => $data[0]['menuId'],
            'list' => $this->coverToFileCollection($data),
        ]);
    }

    /**
     * 复制生成新的 menuId
     * @param int $menuId
     * @return FileListDTO
     */
    public function copyByMenuId(int $menuId): FileListDTO
    {
        $result = $this->api('/api/resource/copy', [
            'json' => [
                'menuId' => $menuId,
            ],
        ], 'POST');
        return new FileListDTO([
            'menuId' => $result['menuId'],
            'list' => $this->coverToFileCollection($result['resourceList']),
        ]);
    }

    /**
     * 确认上传操作
     * @param int $menuId
     * @param array $subIds
     * @return bool
     */
    public function confirmUpload(int $menuId, array $subIds): bool
    {
        $data = $this->api('/api/resource/confirm', [
            'json' => [
                'menuId' => $menuId,
                'subIds' => (array)$subIds,
            ],
        ], 'POST');

        return !!$data;
    }

    /**
     * 根据 menuId 获取所有子项
     * @param int $menuId
     * @return Collection|FileDTO[]
     */
    public function getByMenuId(int $menuId): Collection
    {
        $data = $this->api("/api/resource/{$menuId}");

        return $this->coverToFileCollection($data);
    }

    /**
     * 批量根据 menuId 获取所有子项
     * @param array|int[] $menuIds
     * @return Collection|FileListDTO[] 键为 menuId，与传入时的顺序一致
     */
    public function getByMenuIds(array $menuIds): Collection
    {
        $menuIds = array_map('intval', $menuIds);
        $data = $this->api('/api/resource/menuIdList', [
            'json' => [
                'menuIdList' => $menuIds,
            ],
        ], 'POST');
        $data = collect($data)->groupBy('menuId')->toArray();
        return collect($menuIds)->mapWithKeys(function ($menuId) use ($data) {
            return [
                $menuId => new FileListDTO([
                    'menuId' => $menuId,
                    'list' => $data[$menuId],
                ])
            ];
        });
    }

    /**
     * 更新扩展信息
     * @param array $extra [$subId => $extraInfos]
     * @return bool
     */
    public function updateExtra(array $extra): bool
    {
        $params = [];
        foreach ($extra as $subId => $info) {
            $params[] = [
                'subId' => $subId,
                'value' => is_array($info) ? Json::encode($info) : (string)$info,
            ];
        }
        $data = $this->api('/api/resource/reserve', [
            'json' => $params,
        ], 'POST');
        return !!$data;
    }

    /**
     * 获取下载的 url
     * @param string $relativePath
     * @param string|null $fileName 指定下载的名称，不传默认为原文件名
     * @return string
     */
    public function getDownloadUrl(string $relativePath, $fileName = null): string
    {
        $params = [
            'filePath' => $relativePath,
        ];
        if ($fileName) {
            $params['fileName'] = $fileName;
        }
        return $this->buildUrl('/api/resource/download', $params);
    }

    /**
     * 根据 subId 获取下载的 url
     * @param int $subId
     * @return string
     */
    public function getDownloadUrlBySubId(int $subId): string
    {
        return $this->buildUrl("/api/resource/download/{$subId}");
    }

    /**
     * @param array $data
     * @return Collection|FileDTO[]
     */
    private function coverToFileCollection(array $data): Collection
    {
        $collection = collect();
        foreach ($data as $item) {
            $collection->push(new FileDTO($item));
        }
        return $collection;
    }

    /**
     * 根据文件ID获取文件详情(tb_file_upload_detail.id)
     *
     * @param int $fileDetailId
     * @return array
     */
    public function getSubFileDetail(int $fileDetailId): array
    {
        $data = [];
        try {
            $data = $this->api("/api/resource/single/{$fileDetailId}");
        } catch (\Throwable $e) {
            Logger::error('获取文件明细发生错误：' . $e->getMessage());
        }

        return $data;
    }

    /**
     * 获取外部下载(脱离网站)地址
     *
     * @param int $fileDetailId
     * @return string
     */
    public function getOutFileDownloadUrl(int $fileDetailId)
    {
        return url()->to('common/file/download/' . base64_encode($fileDetailId . '/' . $this->getOutFileDownloadSign($fileDetailId)));
    }

    /**
     * 获取外部下载(脱离网站)校验签名
     *
     * @param int $fileDetailId
     * @return string
     */
    public function getOutFileDownloadSign(int $fileDetailId)
    {
        return strtoupper(sha1($fileDetailId . FILE_DOWNLOAD_SIGN_KEY));
    }
}
