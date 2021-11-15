<?php

use App\Components\RemoteApi;
use App\Components\Storage\StorageCloud;
use Illuminate\Support\Str;
use App\Components\RemoteApi\B2BManager\FileApi;

class ControllerCommonFile extends Controller
{
    public function uploadImage()
    {
        $validator = $this->request->validate([
            'file' => ['file', 'mimes:jpeg,jpg,bmp,png,gif'],
        ]);
        if ($validator->fails()) {
            return $this->json([
                'error' => $validator->errors()->first(),
            ]);
        }
        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
        $file = $this->request->filesBag->get('file');
        if ($extension = $file->getClientOriginalExtension()) {
            $extension = '.' . $extension;
        }
        $filename = Str::random(40) . $extension;
        StorageCloud::imageMisc()->writeStream($filename, fopen($file, 'r'));
        return $this->json([
            'url' => StorageCloud::imageMisc()->getUrl($filename),
            'success' => true,
        ]);
    }

    // 文件下载
    public function download()
    {
        $fileDetailId = '';
        $sign = '';
        $route = base64_decode(str_replace('common/file/download/', '', trim($this->request->get('route', ''))));

        if ($route && ($strPos = stripos($route, '/')) !== false) {
            $fileDetailId = substr($route, 0, $strPos);
            $sign = substr($route, $strPos + 1);
        }

        if (! is_numeric($fileDetailId) || ! $this->checkFileDowonloadSign((int)$fileDetailId, $sign)) {
            $this->response->redirectTo(url('error/not_found'))->send();
        }
        $fileDetail = RemoteApi::file()->getSubFileDetail($fileDetailId);
        if ($fileDetail && ! empty($fileDetail['downloadUrl'])) {
            $this->response->redirectTo($fileDetail['downloadUrl'])->send();
        }

        $this->response->redirectTo(url('error/not_found'))->send();
    }

    // 三方下载文件签名验证
    private function checkFileDowonloadSign(int $fileDetailId, $sign)
    {
        return RemoteApi::file()->getOutFileDownloadSign($fileDetailId) == $sign;
    }
}
