<?php

namespace App\Components\RemoteApi\B2BManager\DTO;

use App\Components\RemoteApi\DTO\BaseDTO;
use Illuminate\Support\Collection;

/**
 * @property-read int $menuId 资源主表ID
 * @property-read Collection|FileDTO[] $list 文件列表
 */
class FileListDTO extends BaseDTO
{
}
