<?php

namespace App\Catalog\Forms\Product\Import;

use App\Catalog\Forms\Product\Import\Exception\ValidateTerminationException;
use App\Enums\File\FileManageFilePostfix;
use App\Enums\Product\ProductImportLimit;
use App\Helper\StringHelper;
use App\Models\Customer\Customer;
use App\Models\File\CustomerFileManage;
use App\Models\Product\Category;
use App\Repositories\Product\ProductRepository;
use Generator;

abstract class AbstractValidate
{
    /**
     * 所有产品
     * @var array
     */
    protected $products;

    /**
     * 国家编号
     * @var int
     */
    protected $country;

    /**
     * @var bool
     */
    protected $isUSA;

    /**
     * seller的id
     * @var int
     */
    protected $customerId;

    /**
     * @var Customer
     */
    protected $customer;

    /**
     * 颜色的id和name的map
     * @var array
     */
    protected $colorOptionIdNameMap = [];

    /**
     * 材质的id和name的map
     * @var array
     */
    protected $materialOptionIdNameMap = [];

    /**
     * 不可售卖平台
     * @var array
     */
    protected $notAvailableForSaleOn;

    /**
     * 真实路径映射
     * @var array
     */
    protected $fileRealPathMap = [];

    /**
     * 合法商品类目数组
     * @var array
     */
    protected $validCategoryIds = [];

    public function __construct(array $products, int $customerId, int $country, array $colorOptionIdNameMap = [], array $materialOptionIdNameMap = [])
    {
        $this->products = $products;
        $this->country = $country;
        $this->isUSA = $country == AMERICAN_COUNTRY_ID;
        $this->customerId = $customerId;
        $this->customer = customer()->getId() == $customerId ? customer()->getModel() : Customer::query()->find($customerId);
        $this->colorOptionIdNameMap = $colorOptionIdNameMap;
        $this->materialOptionIdNameMap = $materialOptionIdNameMap;

        $this->initNotAvailableForSaleOn();
        $this->generateFilePathMapping();
        $this->calculateValidCategoryIds();

        $this->init();
    }

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @var int
     */
    protected $isHandle = 1;

    /**
     * @param array $product
     * @return array
     */
    public function validate(array $product): array
    {
        $this->errors = [];
        $this->isHandle = 1;

        $partProduct = $this->getPartProduct($product);

        $product = array_map('strtolower', $product);
        $validator = validator($product, $this->getRules(), $this->getRuleMessages(), $this->getCustomAttributes());
        if ($validator->fails()) {
            $this->isHandle = 0;
            $this->errors = $validator->errors()->all();
        }

        try {
            $this->validateMpn($product);
            $this->validateUpc($product);
            $this->validateNotAvailableForSaleOn($partProduct);
            $this->validateTitle($product);
            $this->validateSize($product);
            $this->validateChargeableWeightExceed($product);
            $this->validateComboAttribute($product);
            if ($this->isHandle) {
                $this->validateOriginalDesign($partProduct, $product);
                $this->validateMainImagePath($partProduct);
                $this->validateImagePathToBeDisplayed($partProduct);
                $this->validateImagePathOtherMaterial($partProduct);
                $this->validateMaterialManualPath($partProduct);
                $this->validateMaterialVideoPath($partProduct);
            }
        } catch (ValidateTerminationException $e) {
            // 不需额外处理，只为结束其他验证
        }

        return $this->returnData($partProduct, $product);
    }

    /**
     * 初始化数据组装
     * @return mixed
     */
    abstract protected function init();

    /**
     * @return array
     */
    abstract protected function getRules(): array;

    /**
     * @return array
     */
    abstract protected function getRuleMessages(): array;

    /**
     * @return array
     */
    abstract protected function getCustomAttributes(): array;

    /**
     * 返回数据
     * @param array $partProduct
     * @param array $product
     * @return array
     */
    abstract protected function returnData(array $partProduct, array $product): array;

    /**
     * 验证mpn
     * @param array $product
     */
    abstract protected function validateMpn(array $product);

    /**
     * 验证标题
     * @param array $product
     */
    protected function validateUpc(array $product)
    {
        if (isset($product['UPC']) && !empty($product['UPC']) && StringHelper::stringCharactersLen($product['UPC']) > 30) {
            $this->isHandle = 0;
            $this->errors[] = 'No more than 30 characters are permitted.';
        }
    }

    /**
     * 验证NotAvailableForSaleOn
     * @param array $product
     */
    protected function validateNotAvailableForSaleOn(array &$product)
    {
        if (!empty($product['Not available for sale on'])) {
            $notAvailableForSaleOn = explode('|', $product['Not available for sale on']);
            $notAvailableForSaleOn = array_unique($notAvailableForSaleOn); //去重
            $notAvailableForSaleOnValidArr = $notAvailableForSaleOnInValidArr = [];
            foreach ($notAvailableForSaleOn as $platform) {
                if (in_array($platform, $this->notAvailableForSaleOn)) {
                    $notAvailableForSaleOnValidArr[] = trim($platform);
                } else {
                    $notAvailableForSaleOnInValidArr[] = trim($platform);
                }
            }
            if ($notAvailableForSaleOnInValidArr) {
                $this->errors[] = "The platforms entered in 'Not available for sale on' field can only be that included in the 'platform name sheet'.";
            }
            if ($notAvailableForSaleOnValidArr) {
                $product['Not available for sale on'] = implode(',', $notAvailableForSaleOnValidArr);
            } else {
                $product['Not available for sale on'] = '';
            }
        }
    }

    /**
     * 验证标题
     * @param array $product
     */
    protected function validateTitle(array $product)
    {
        if (isset($product['Product Title']) && !empty($product['Product Title']) && StringHelper::stringCharactersLen($product['Product Title']) > 200) {
            $this->isHandle = 0;
            $this->errors[] = 'Product Title exceeds 200 characters.';
        }
    }

    /**
     * 验证尺寸
     * @param array $product
     */
    protected function validateSize(array $product)
    {
        if (!empty($product['Assembled Width']) && !$this->_validateAssembledRule($product['Assembled Width'])) {
            $this->isHandle = 0;
            $this->errors[] = "Assembled Width only enter a number between 0.01 and 999.99 or 'Not Applicable'.";
        }

        if (!empty($product['Assembled Length']) && !$this->_validateAssembledRule($product['Assembled Length'])) {
            $this->isHandle = 0;
            $this->errors[] = "Assembled Length only enter a number between 0.01 and 999.99 or 'Not Applicable'.";
        }

        if (!empty($product['Assembled Height']) && !$this->_validateAssembledRule($product['Assembled Height'])) {
            $this->isHandle = 0;
            $this->errors[] = "Assembled Height only enter a number between 0.01 and 999.99 or 'Not Applicable'.";
        }

        if (!empty($product['Assembled Weight']) && !$this->_validateAssembledRule($product['Assembled Weight'])) {
            $this->isHandle = 0;
            $this->errors[] = "Weight only enter a number between 0.01 and 999.99 or 'Not Applicable'.";
        }
    }

    /**
     * 验证平台可发尺寸
     * @param array $product
     */
    abstract protected function validateChargeableWeightExceed(array $product);

    /**
     * 验证combo产品
     * @param array $product
     */
    abstract protected function validateComboAttribute(array $product);

    /**
     * 验证原创产品
     * @param array $product
     * @param array $originProduct
     * @throws ValidateTerminationException
     */
    protected function validateOriginalDesign(array &$product, array $originProduct)
    {
        if (!empty($product['Supporting Files Path'])) {
            $limitNumber = ProductImportLimit::LIMIT_9;
            $validateResult = $this->getFilePathsErrorLevel($product['Supporting Files Path'], $limitNumber);
            switch ($validateResult['error_type']) {
                case 1:
                    $this->isHandle = 0;
                    $this->errors[] = "'Supporting Files Path' must be the path of images in .jpeg/.jpg/.png/.gif format.";
                    throw new ValidateTerminationException();
                case 2:
                    $this->isHandle = 0;
                    $this->errors[] = "Paths filled in 'Supporting Files Path' are incorrect.No corresponding file was found.";
                    throw new ValidateTerminationException();
                case 4:
                    $this->errors[] = "A maximum of {$limitNumber} image paths can be filled for 'Supporting Files Path'. Extra paths or incorrect path are not uploaded.";
                    break;
            }

            if (empty($validateResult['part_product_field'])) {
                $this->isHandle = 0;
                $this->errors[] = "Paths filled in 'Supporting Files Path' are incorrect.No corresponding file was found.";
                throw new ValidateTerminationException();
            }
            $product['Supporting Files Path'] = $validateResult['part_product_field'];
        }
    }

    /**
     * 验证主图
     * @param array $product
     */
    protected function validateMainImagePath(array &$product)
    {
        if (!empty($product['Main Image Path'])) {
            $validateResult = $this->getFilePathsErrorLevel($product['Main Image Path'], ProductImportLimit::LIMIT_1);
            switch ($validateResult['error_type']) {
                case 1:
                    $this->errors[] = "'Main Image Path' must be the path of images in .jpeg/.jpg/.png/.gif format.";
                    break;
                case 2:
                    $this->errors[] = "Paths filled in 'Main Image Path' are incorrect.No corresponding file was found.";
                    break;
                case 4:
                    $this->errors[] = "One image is permitted for 'Main Image Path'. Extra paths or incorrect path are not uploaded.";
                    break;
            }

            $product['Main Image Path'] = $validateResult['part_product_field'];
        }
    }

    /**
     * 验证产品图片
     * @param array $product
     */
    protected function validateImagePathToBeDisplayed(array &$product)
    {
        if (!empty($product['Images Path(to be displayed)'])) {
            $limitNumber = ProductImportLimit::LIMIT_26;
            $validateResult = $this->getFilePathsErrorLevel($product['Images Path(to be displayed)'], $limitNumber);
            switch ($validateResult['error_type']) {
                case 1:
                    $this->errors[] = "'Images Path (to be displayed)' must be the path of images in .jpeg/.jpg/.png/.gif format.";
                    break;
                case 2:
                    $this->errors[] = "Paths filled in 'Images Path (to be displayed)' are incorrect.No corresponding file was found.";
                    break;
                case 4:
                    $this->errors[] = "A maximum of {$limitNumber} image paths can be filled for 'Images Path (to be displayed)'. Extra paths or incorrect path are not uploaded.";
                    break;
            }

            $product['Images Path(to be displayed)'] = $validateResult['part_product_field'];
        }
    }

    /**
     * 验证其他图片
     * @param array $product
     */
    protected function validateImagePathOtherMaterial(array &$product)
    {
        if (!empty($product['Images Path(other material)'])) {
            $limitNumber = ProductImportLimit::LIMIT_27;
            $validateResult = $this->getFilePathsErrorLevel($product['Images Path(other material)'], $limitNumber);
            switch ($validateResult['error_type']) {
                case 1:
                    $this->errors[] = "'Images Path(other material)' must be the path of images in .jpeg/.jpg/.png/.gif format.";
                    break;
                case 2:
                    $this->errors[] = "Paths filled in 'Images Path(other material)' are incorrect.No corresponding file was found.";
                    break;
                case 4:
                    $this->errors[] = "A maximum of {$limitNumber} image paths can be filled for 'Images Path(other material)'. Extra paths or incorrect path are not uploaded.";
                    break;
            }

            $product['Images Path(other material)'] = $validateResult['part_product_field'];
        }
    }

    /**
     * 验证手册
     * @param array $product
     */
    protected function validateMaterialManualPath(array &$product)
    {
        if (!empty($product['Material Manual Path'])) {
            $limitNumber = ProductImportLimit::LIMIT_27;
            $validateResult = $this->getFilePathsErrorLevel($product['Material Manual Path'], $limitNumber, 3);
            switch ($validateResult['error_type']) {
                case 1:
                    $this->errors[] = "'Material Manual Path' must be the path of images in .jpeg/.jpg/.png/.gif format or documents in .doc(x)/.xl(s)/.ppt(x)/.txt format.";
                    break;
                case 2:
                    $this->errors[] = "Paths filled in 'Material Manual Path' are incorrect.No corresponding file was found.";
                    break;
                case 4:
                    $this->errors[] = "A maximum of {$limitNumber} image/document paths can be filled for 'Material Manual Path'. Extra paths or incorrect path are not uploaded.";
                    break;
            }

            $product['Material Manual Path'] = $validateResult['part_product_field'];
        }
    }

    /**
     * 验证音视频
     * @param array $product
     */
    protected function validateMaterialVideoPath(array &$product)
    {
        if (!empty($product['Material Video Path'])) {
            $limitNumber = ProductImportLimit::LIMIT_27;
            $validateResult = $this->getFilePathsErrorLevel($product['Material Video Path'], $limitNumber, 4, [FileManageFilePostfix::DOCUMENT_TXT]);
            switch ($validateResult['error_type']) {
                case 1:
                    $this->errors[] = "'Material Video Path' must be the path of  documents in .txt format.";
                    break;
                case 2:
                    $this->errors[] = "Paths filled in 'Material Video Path' are incorrect.No corresponding file was found.";
                    break;
                case 4:
                    $this->errors[] = "A maximum of {$limitNumber} document paths in .txt format can be filled for 'Material Video Path'. Extra paths or incorrect path are not uploaded.";
                    break;
            }

            $product['Material Video Path'] = $validateResult['part_product_field'];
        }
    }

    /**
     * 获取文件路径错误
     * @param string $filePaths
     * @param int $fileLimit
     * @param int $fileType
     * @param array $extArr
     * @return array
     */
    protected function getFilePathsErrorLevel(string $filePaths = '', int $fileLimit = 27, int $fileType = 1, array $extArr = []): array
    {
        $result = [
            'part_product_field' => [],
            'error_type' => 0, // 0 没有错 1 文件格式全部不正确  2 文件路径全部不正确  4 超出限制
        ];

        $filePathArr = explode('|', $filePaths);
        switch ($fileType) {
            case 2:
                $validExt = FileManageFilePostfix::getDocumentTypes();
                break;
            case 3:
                $validExt = FileManageFilePostfix::getImageAndDocumentTypes();
                break;
            case 4:
                $validExt = $extArr;
                break;
            default:
                $validExt = FileManageFilePostfix::getImageTypes();
                break;
        }

        $filePathResult = $filePathResultValid = [];
        $uploadFileNumber = count($filePathArr);
        $fileFormatError = $filePathError = 0; //文件格式错误数量  文件路径错误数量
        //先校验文件格式=>再校验文件合法性
        foreach ($filePathArr as $path) {
            $pathArr = explode('.', $path);
            if (count($pathArr) > 2 || count($pathArr) <= 1 || !in_array(strtolower($pathArr[1]), $validExt)) {
                $fileFormatError++;
            } else {
                $filePathResult[] = trim($path);
            }
        }
        // 格式全部不正确
        if ($fileFormatError >= $uploadFileNumber) {
            $result['error_type'] = 1;
            return $result;
        }
        foreach ($filePathResult as $path) {
            if (!isset($this->fileRealPathMap[$path])) {
                $filePathError++;
                continue;
            }
            // 本身有去重操作
            if (!in_array(trim($this->fileRealPathMap[$path]), array_column($filePathResultValid, 'file_real_path'))) {
                $filePathResultValid[] = [
                    'file_real_path' => trim($this->fileRealPathMap[$path]),
                    'file_origin_path' => trim($path),
                ];
            }
        }
        //路径全部不正确
        if ($filePathError >= count($filePathResult)) {
            $result['error_type'] = 2;
            return $result;
        }
        //截取有效数据
        if (count($filePathResultValid) > $fileLimit) {
            $filePathResultValid = array_slice($filePathResultValid, 0, $fileLimit);
            $result['error_type'] = 4;
        }

        $result['part_product_field'] = $filePathResultValid;

        return $result;
    }

    /**
     * 获取不可售卖平台
     */
    private function initNotAvailableForSaleOn()
    {
        $this->notAvailableForSaleOn = app(ProductRepository::class)->getForbiddenSellPlatform();
    }

    /**
     * 生成路径映射
     */
    private function generateFilePathMapping()
    {
        if ($this->products) {
            $filenames = [];
            $includeFolderPath = [];
            foreach ($this->getProductAttachmentPath() as $paths) {
                foreach ($paths as $path) {
                    if (strpos($path, '/') === false) { // 直接是文件名
                        $filenames[] = trim($path);
                    } else {
                        $includeFolderPath[] = trim($path);
                    }
                }
            }
            $filenamePathMap = $this->getFilenamePathMap($filenames);
            [, $includeFolderPathMap] = $this->calculateIncludeFolderPath(array_filter($includeFolderPath));

            $this->fileRealPathMap = array_filter(array_merge($includeFolderPathMap, $filenamePathMap));
        }
    }

    /**
     * 校验categoryIds是否合法，新增or编辑产品模块使用，新增or编辑时候只能选中层级最小的categoryId
     */
    private function calculateValidCategoryIds()
    {
        $categoryIds = array_filter(array_column($this->products, 'Category ID'));

        if (empty($categoryIds)) {
            return;
        }

        $currentCategoryIds = Category::queryRead()
            ->whereIn('category_id', $categoryIds)
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->pluck('category_id')
            ->toArray();

        if (empty($currentCategoryIds)) {
            return;
        }

        $invalidCategoryInfos = Category::query()
            ->whereIn('parent_id', $currentCategoryIds)
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->get()
            ->keyBy('parent_id')
            ->toArray();

        if (empty($invalidCategoryInfos)) {
            $this->validCategoryIds = $currentCategoryIds;
            return;
        }

        $this->validCategoryIds = array_diff($currentCategoryIds, array_keys($invalidCategoryInfos)) ?: [];
    }

    /**
     * 获取文件映射
     * @param array $includeFolderPath
     * @return array
     */
    private function calculateIncludeFolderPath(array $includeFolderPath): array
    {
        sort($includeFolderPath);

        $folders = []; // 所有目录文件夹数组
        $needSearchArr = [];  // 需要查询数据库的文件放到一个数组

        foreach ($includeFolderPath as $attach) {   // $attach  F201/F201_1/F201_1_1/A.jpg
            if (isset($folders[$attach])) {  //文件夹直接过滤1
                continue;
            }

            $tempAttachments = explode('/', $attach);
            $filename = end($tempAttachments);

            if (strpos($filename, '.') === false) { // 不包含.的认为最后一层填的是文件夹
                continue;
            }

            $folderStr = implode('/', array_slice($tempAttachments, 0, count($tempAttachments) - 1));
            if (isset($folders[$folderStr])) {
                $parentId = $folders[$folderStr];
                //同一个seller下parent_id和文件名称的拼接理论上不可能重复，实际也如此
                $needSearchArr[$parentId . '|' . $filename] = [
                    'parent_id' => $parentId,
                    'name' => $filename,
                ];
                continue;
            }

            $itemNew = '';
            $parentId = 0;
            // $item1  每一层的文件名称或目录名称
            foreach ($tempAttachments as $key => $item1) {
                if ($key == 0) {
                    $parentId = 0;
                }
                $idx = empty($itemNew) ? $item1 : $itemNew . '/' . $item1;
                //全是文件夹
                if (strpos($item1, '.') === false) {
                    if (isset($folders[$idx])) {
                        $itemNew = $key > 0 ? $itemNew . '/' . $item1 : $item1;
                        $parentId = $folders[$idx];
                        continue;
                    }
                    $fileInfo = CustomerFileManage::query()
                        ->where('parent_id', $parentId)
                        ->where('name', $item1)
                        ->where('is_dir', 1)
                        ->where('is_del', 0)
                        ->where('customer_id', $this->customerId)
                        ->first();

                    //查不到目录 整条舍弃
                    if (empty($fileInfo)) {
                        break;
                    }
                    $itemNew = $key > 0 ? $itemNew . '/' . $item1 : $item1;

                    $parentId = $fileInfo->id;
                    $folders[$itemNew] = $fileInfo->id;
                } else {
                    $needSearchArr[$parentId . '|' . $item1] = [
                        'parent_id' => $parentId,
                        'name' => $item1,
                    ];
                }
            }
        }

        $includeFolderPathMap = [];
        if ($needSearchArr) {
            $arrayKeys = array_keys($needSearchArr);
            $searchResultParentIds = $searchResultParentNames = [];
            foreach ($arrayKeys as $val) {
                $searchResultParentIds[] = explode('|', $val)[0] ?? 0;
                $searchResultParentNames[] = explode('|', $val)[1] ?? '';
            }
            $searchResultParentIds = array_filter($searchResultParentIds);
            $searchResultParentNames = array_filter($searchResultParentNames);

            $finalFiles = [];
            CustomerFileManage::query()
                ->where('customer_id', $this->customerId)
                ->whereIn('parent_id', $searchResultParentIds)
                ->whereIn('name', $searchResultParentNames)
                ->where('is_del', 0)
                ->get()
                ->map(function ($item) use (&$finalFiles) {
                    $finalFiles[$item->parent_id . '|' . $item->name] = $item->file_path;
                });

            $newFolders = array_flip($folders);
            foreach ($needSearchArr as $key => $item) {
                $tempKey = explode('|', $key)[0] ?? '';
                $nextName = explode('|', $key)[1] ?? '';
                if (!empty($nextName)) {
                    $includeFolderPathMap[$newFolders[$tempKey] . '/' . $nextName] = $finalFiles[$key] ?? '';
                }
            }
        }

        return [isset($newFolders) ? array_filter($newFolders) : [], $includeFolderPathMap];
    }

    /**
     * 获取文件名的路径映射
     * @param array $filenames
     * @return array
     */
    private function getFilenamePathMap(array $filenames): array
    {
        return CustomerFileManage::query()
            ->whereIn('name', $filenames)
            ->where('parent_id', 0)
            ->where('is_dir', 0)
            ->where('customer_id', $this->customerId)
            ->get()
            ->pluck('file_path', 'name')
            ->toArray();
    }

    /**
     * 获取文件中的所有路径
     * @return Generator
     */
    private function getProductAttachmentPath(): Generator
    {
        $fields = [
            'Supporting Files Path',
            'Main Image Path',
            'Images Path(to be displayed)',
            'Images Path(other material)',
            'Material Manual Path',
            'Material Video Path',
        ];

        foreach ($this->products as $product) {
            foreach ($fields as $field) {
                if (!empty($product[$field])) {
                    yield explode('|', $product[$field]);
                }
            }
        }
    }

    /**
     * 获取部分产品信息
     * @param array $product
     * @return array|string[]
     */
    private function getPartProduct(array $product): array
    {
        return [
            'Not available for sale on' => $product['Not available for sale on'] ?? '',
            'Supporting Files Path' => $product['Supporting Files Path'] ?? '',
            'Product Description' => $product['Product Description'] ?? '',
            'Main Image Path' => $product['Main Image Path'] ?? '',
            'Images Path(to be displayed)' => $product['Images Path(to be displayed)'] ?? '',
            'Images Path(other material)' => $product['Images Path(other material)'] ?? '',
            'Material Manual Path' => $product['Material Manual Path'] ?? '',
            'Material Video Path' => $product['Material Video Path'] ?? '',
        ];
    }

    /**
     * @param $string
     * @return bool
     */
    private function _validateAssembledRule($string): bool
    {
        if (strtolower(trim($string)) == 'not applicable') {
            return true;
        }
        if (preg_match("/^(\d{1,3})(\.\d{0,2})?$/", $string) && floatval($string) > 0 && floatval($string) < 1000) {
            return true;
        }
        return false;
    }
}
