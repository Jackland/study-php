<?php

use App\Components\Storage\Enums\MigratedImagePath;
use App\Components\Storage\StorageCloud;

/**
 * Class ModelToolImage
 */
class ModelToolImage extends Model {
    private $src;
    private $image;
    private $imageinfo;
    private $percent = 0.5;

    /**
     * 图片压缩
     * @param $registry
     */
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
    }

    /** 高清压缩图片
     * @param $source
     * @param string $saveName 提供图片名（可不带扩展名，用源图扩展名）用于保存。或不提供文件名直接显示
     * @param $percent
     */
    public function compressImg($source, $percent, $saveName = '222')
    {
        $this->src = $source;
        $this->percent = $percent;
        $this->_openImage();
        if (!empty($saveName)) $this->_saveImage($saveName);  //保存
        else $this->_showImage();
    }

    /**
     * 内部：打开图片
     */
    private function _openImage()
    {
        list($width, $height, $type, $attr) = getimagesize($this->src);
        $this->imageinfo = array(
            'width' => $width,
            'height' => $height,
            'type' => image_type_to_extension($type, false),
            'attr' => $attr
        );
        $fun = "imagecreatefrom" . $this->imageinfo['type'];
        $this->image = $fun($this->src);
        $this->_thumpImage();
    }

    /**
     * 内部：操作图片
     */
    private function _thumpImage()
    {
        $new_width = $this->imageinfo['width'] * $this->percent;
        $new_height = $this->imageinfo['height'] * $this->percent;
        $image_thump = imagecreatetruecolor($new_width, $new_height);
        //将原图复制带图片载体上面，并且按照一定比例压缩,极大的保持了清晰度
        imagecopyresampled($image_thump, $this->image, 0, 0, 0, 0, $new_width, $new_height, $this->imageinfo['width'], $this->imageinfo['height']);
        imagedestroy($this->image);
        $this->image = $image_thump;
    }

    /**
     * 输出图片:保存图片则用saveImage()
     */
    private function _showImage()
    {
        header('Content-Type: image/' . $this->imageinfo['type']);
        $funcs = "image" . $this->imageinfo['type'];
        $funcs($this->image);
        imagedestroy($this->image);
    }

    /**
     * 保存图片到硬盘：
     * @param string $dstImgName 1、可指定字符串不带后缀的名称，使用源图扩展名 。2、直接指定目标图片名带扩展名。
     * @return bool
     */
    private function _saveImage($dstImgName)
    {
        if (empty($dstImgName)) return false;
        $allowImgs = ['.jpg', '.jpeg', '.png', '.bmp', '.wbmp', '.gif'];   //如果目标图片名有后缀就用目标图片扩展名 后缀，如果没有，则用源图的扩展名
        $dstExt = strrchr($dstImgName, ".");
        $sourseExt = strrchr($this->src, ".");
        if (!empty($dstExt)) $dstExt = strtolower($dstExt);
        if (!empty($sourseExt)) $sourseExt = strtolower($sourseExt);
        //有指定目标名扩展名
        if (!empty($dstExt) && in_array($dstExt, $allowImgs)) {
            $dstName = $dstImgName;
        } elseif (!empty($sourseExt) && in_array($sourseExt, $allowImgs)) {
            $dstName = $dstImgName . $sourseExt;
        } else {
            $dstName = $dstImgName . $this->imageinfo['type'];
        }
        $funcs = "image" . $this->imageinfo['type'];
        $funcs($this->image, $dstName);
        imagedestroy($this->image);
    }

    public function resize($filename, $width = null, $height = null)
    {
        $filename = str_replace("\\", "/", $filename);

        $prefix = substr($filename, 0, strpos($filename, '/'));
        if ($prefix && in_array($prefix, MigratedImagePath::getValues())) {
            // 表示已经迁移到 OSS 的
            return StorageCloud::image()->getUrl($filename, [
                'w' => $width,
                'h' => $height,
            ]);
        }

        if (!$this->checkImageValid($filename)) {
            if (is_file(DIR_IMAGE . 'no_image.png')) {
                return $this->resize('no_image.png', $width, $height);
            }
            return '';
        }
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $image_old = $filename;
        $image_new = 'cache/' . utf8_substr($filename, 0, utf8_strrpos($filename, '.')) . '-' . (int)$width . 'x' . (int)$height . '.' . $extension;
        if (!is_file(DIR_IMAGE . $image_new) || (filemtime(DIR_IMAGE . $image_old) > filemtime(DIR_IMAGE . $image_new))) {
            list($width_orig, $height_orig, $image_type) = getimagesize(DIR_IMAGE . $image_old);

            if (!in_array($image_type, array(IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF))) {
                return DIR_IMAGE . $image_old;
            }
            $path = '';
            $directories = explode('/', dirname($image_new));
            foreach ($directories as $directory) {
                $path = $path . '/' . $directory;
                if (!is_dir(DIR_IMAGE . $path)) {
                    @mkdir(DIR_IMAGE . $path, 0777);
                }
            }
            if ($width_orig != $width || $height_orig != $height) {
                $image = new Image(DIR_IMAGE . $image_old);
                if ($width && $height) {
                    $image->resize($width, $height);
                }
                $image->save(DIR_IMAGE . $image_new);
            } else {
                copy(DIR_IMAGE . $image_old, DIR_IMAGE . $image_new);
            }
        }
        $image_new = str_replace(' ', '%20', $image_new);  // fix bug when attach image on email (gmail.com). it is automatic changing space " " to +
        if ($this->request->server['HTTPS']) {
            return $this->config->get('config_ssl') . 'image/' . $image_new;
        } else {
            return $this->config->get('config_url') . 'image/' . $image_new;
        }
    }

    /**
     * 获取image/product下图片
     *
     * @param  string  $image_url
     * @return string
     */
    public function getOriginImageProductTags($image_url)
    {
        $imageUrl = $this->request->serverBag->get('HTTPS')
            ? $this->config->get('config_ssl') .  'image/' . $image_url
            : $this->config->get('config_url') .  'image/' . $image_url ;
        return $imageUrl ;
    }

    public function getDealBannerImage($image, $width = null, $height = null)
    {
        $img_size = filesize(DIR_IMAGE . $image);
        if ($img_size > 500 * 1024) {
            $image_info = explode('/', $image);
            $image_name = $image_info[count($image_info) - 1];
            //获取更改后的name
            $image_name_pre = utf8_substr($image_name, 0, mb_strrpos($image_name, '.'));
            $image_name_suf = utf8_substr($image_name, mb_strrpos($image_name, '.'));
            unset($image_info[count($image_info) - 1]);
            $new_name = implode('/', $image_info) . '/' . $image_name_pre . '_new' . $image_name_suf;
            if (!is_file(DIR_IMAGE . $new_name)) {
                list($pic_width, $pic_height, $pic_type, $pic_attr) = getimagesize(DIR_IMAGE . $image);
                $width_percent = $width / $pic_width;
                $height_percent = $height / $pic_height;
                $resize_percent = min(array($width_percent, $height_percent));
                $this->compressImg(DIR_IMAGE . $image, $resize_percent, DIR_IMAGE . $new_name);
            }
            return $this->config->get('config_ssl') . 'image/' . $new_name;

        } else {
            return $this->resize($image, $width, $height);
        }
    }

    /**
     * @param string $fileName
     * @return bool
     */
    public function checkImageValid(string $fileName): bool
    {
        if (
            !is_file(DIR_IMAGE . $fileName)
            || substr(
                str_replace('\\', '/', realpath(DIR_IMAGE . $fileName)),
                0,
                strlen(DIR_IMAGE)) != str_replace('\\', '/', DIR_IMAGE)
            || !@getimagesize(DIR_IMAGE . $fileName)
        ) {
            return false;
        }
        return true;
    }
}
