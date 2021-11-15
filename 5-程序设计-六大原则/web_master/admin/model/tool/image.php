<?php

use App\Components\Storage\Enums\MigratedImagePath;
use App\Components\Storage\StorageCloud;

/**
 * Class ModelToolImage
 */
class ModelToolImage extends Model {
	public function resize($filename, $width, $height) {
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
		$image_new = 'cache/' . utf8_substr($filename, 0, utf8_strrpos($filename, '.')) . '-' . $width . 'x' . $height . '.' . $extension;

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
				$image->resize($width, $height);
				$image->save(DIR_IMAGE . $image_new);
			} else {
				copy(DIR_IMAGE . $image_old, DIR_IMAGE . $image_new);
			}
		}

		if ($this->request->server['HTTPS']) {
			return HTTPS_CATALOG . 'image/' . $image_new;
		} else {
			return HTTP_CATALOG . 'image/' . $image_new;
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
