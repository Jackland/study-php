<?php

use App\Catalog\Controllers\BaseController;
use App\Components\Storage\StorageCloud;
use Illuminate\Support\Str;

/**
 * Class ControllerMessageStationLetter
 */
class ControllerMessageStationLetter extends BaseController
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        // 额外放行
        if (!$this->customer->isLogged() && strcmp(request('route'), 'message/station_letter/download') !== 0) {
            $this->url->remember();
            $this->redirect(['account/login'])->send();
        }
    }

    /**
     * 下载附件用
     */
    public function download()
    {
        $filename = $this->request->get['filename'];
        $maskname = $this->request->get['maskname'];
        if ($filename && $maskname) {
            $file = $filename;
            $mask = basename($maskname);
            if (!headers_sent()) {
                if (Str::contains($file, get_env('ALI_OSS_DOMAIN'))) {
                    $file = str_replace(get_env('ALI_OSS_DOMAIN'), '', $file);
                    if (StorageCloud::root()->fileExists($file)) {
                        return StorageCloud::root()->browserDownload($file,$mask ?: basename($file));
                    }
                }

                if (file_exists($file)) {
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . ($mask ? $mask : basename($file)) . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($file));
                    if (ob_get_level()) {
                        ob_end_clean();
                    }
                    readfile($file, 'rb');
                    exit();
                } else {
                    exit('Error: Could not find file ' . $file . '!');
                }
            } else {
                exit('Error: Headers already sent out!');
            }
        } else {
            $this->response->redirect($this->url->link('message/seller', '', true));
        }
    }
}
