<?php

use App\Catalog\Controllers\AuthController;
use App\Components\Storage\StorageCloud;
use Framework\Exception\Http\NotFoundException;

class ControllerCommonUpload extends AuthController
{
    // 上传图片
    public function image()
    {
        return $this->commonUpload('jpeg,jpg,png', 20480);
    }

    // 上传图片2，允许 gif
    public function image2()
    {
        return $this->commonUpload('jpeg,jpg,png,gif', 20480);
    }

    // 上传 pdf
    public function pdf()
    {
        return $this->commonUpload('pdf', 20480);
    }

    // 下载
    public function download()
    {
        if ($path = $this->request->get('path')) {
            return StorageCloud::image()->browserDownload($path, $this->request->get('name'));
        }
        throw new NotFoundException();
    }

    // 通用的上传逻辑
    private function commonUpload($mimes, $size)
    {
        $result = $this->check($mimes, $size);
        if ($result !== true) {
            return $this->jsonFailed($result);
        }
        $result = $this->upload();
        if (is_array($result)) {
            return $this->jsonSuccess($result);
        }
        return $this->jsonFailed($result);
    }

    /**
     * 检查上传文件
     * @param string $mimes @see https://learnku.com/docs/laravel/5.8/validation/3899#rule-mimetypes
     * @param int $size @see https://learnku.com/docs/laravel/5.8/validation/3899#bd629a
     * @return bool|string
     */
    private function check(string $mimes, int $size)
    {
        $validator = validator($this->request->file(), [
            'files' => ['required', 'array'],
            'files.*' => ['required', 'file', 'mimes:' . $mimes, 'max:' . $size]
        ]);
        if ($validator->fails()) {
            return $validator->errors()->first();
        }
        return true;
    }

    /**
     * 上传所有提交的文件
     * @return array
     */
    private function upload(): array
    {
        $files = $this->request->file('files');
        $data = [];
        foreach ($files as $file) {
            if ($extension = $file->getClientOriginalExtension()) {
                $extension = '.' . $extension;
            }
            $filename = date('YmdHis') . '/' . md5_file($file->getRealPath()) . $extension;
            if (!StorageCloud::wkmisc()->fileExists($filename)) {
                StorageCloud::wkmisc()->writeStream($filename, fopen($file, 'r'));
            }
            $fullPath = StorageCloud::wkmisc()->getFullPath($filename);
            $data[] = [
                'url' => StorageCloud::root()->getUrl($fullPath),
                'path' => StorageCloud::image()->getRelativePath($fullPath), // 相对路径为 image，为了兼容原上传组件的文件存储路径均为 wkseller 的情况
            ];
        }
        return $data;
    }
}
