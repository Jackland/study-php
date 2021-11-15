<?php

namespace App\Services\Product;

use App\Helpers\LoggerHelper;
use App\Helpers\OssHelper;
use App\Jobs\PackToZip;
use App\Models\Currency;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Product\Product;
use App\Models\Product\ProductCertificationDocument;
use App\Models\Product\ProductDescription;
use App\Models\Setting;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\Freight\EuropeFreightRepository;
use App\Repositories\Product\ProductRepository;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Log;
use Throwable;
use ZipArchive;

class ProductService
{
    /**
     * 打包产品的素材包
     * @param $productId
     * @param $customerId
     * @return false|string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function packToZip($productId, $customerId)
    {
        ini_set('memory_limit', '1024M');
        if (empty($productId) || empty($customerId)) {
            return false;
        }
        $runTime = time();
        $product = app(ProductRepository ::class)->getProductInfo($productId);
        if (empty($product)) {
            LoggerHelper::logPackZip(['customer_id' => $customerId, 'product_id' => $productId, 'msg' => '该产品是非法产品']);
            return false;
        }
        $sellerInfo = app(ProductRepository ::class)->getSellerInfoByProductId($productId);
        $isOverSize = app(Product::class)->checkIsOverSize($productId);
        $countryId = app(CustomerRepository::class)->getCountryId($customerId)->country_id;
        $selfSupport = CustomerPartnerToCustomer::where('customer_id', $customerId)->value('self_support');
        $itemCode = $selfSupport == 1 ? $product->sku : $product->mpn;
        // zip文件下载之后的名称
        $fileName = $sellerInfo->screenname . '_' . $itemCode . '_' . date('Ymd', $runTime) . '.zip';
        $fileName = str_replace([' ', ',', '/', '\\','&amp;'], '_', $fileName);
        $packagePath = storage_path() . '/productPackage' . "/" . $customerId . "/" . $productId;
        if (!is_dir($packagePath)) {
            mkdir($packagePath, 0777, true);
        }
        $zipFileName = $packagePath . '/' . $fileName;
        if (file_exists($zipFileName)) {
            @unlink($zipFileName);
        }
        $zip = new ZipArchive();
        if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return false;
        }
        $images = app(ProductRepository::class)->getProductImages($productId);
        $product->image && array_push($images, $product->image);
        $images = array_unique($images);
        $descriptionHtml = self::decode($product->description->description);
        $specificationHtml = app(ProductRepository::class)->specificationForDownload($product->toArray(), $countryId);
        $styleReturn = '<style> .return-policy, .warranty-policy {border: 1px solid #dbdbdb;}.return-policy .policy-title, .warranty-policy .policy-title {border-bottom: 1px solid #dbdbdb;padding: 14px 22px;}.tab-content .text-max {font-size: 22px;}.text-bold {font-weight: bold;}.text-larger {font-size: 16px;}.ml-1 {margin-left: 10px;}.return-policy .policy-content {padding: 0 22px 30px 22px;}.text-bule {color: #0041bc;}.mt-3 {margin-top: 30px;}h4, .h4 {font-size: 18px;}h4, .h4, h5, .h5, h6, .h6 {margin-top: 10px;margin-bottom: 10px;}h1, h2, h3, h4, h5, h6, .h1, .h2, .h3, .h4, .h5, .h6 {font-family: inherit;font-weight: 500;line-height: 1.1;color: inherit;}.policy-content ul, .shipping-content ol {padding-left: 15px;}ul, ol {margin-top: 0;margin-bottom: 10px;}.policy-content ul > li {word-break: break-all;}.text-warning {color: #ff6600;}.mt-5 {margin-top: 50px;}.mt-2 {margin-top: 20px;}.warranty-policy .policy-content {padding: 30px 22px;}.tab-content .text-danger {color: #e64545;}.text-danger {color: #e64545;}</style>';
        $returnWarrantyTextHtml = $styleReturn . self::decode($product->description->return_warranty_text);
        //调用方法，对要打包的根目录进行操作，并将ZipArchive的对象传递给方法
        $this->addFileToZip($packagePath . "/image/", $zip, "image", $descriptionHtml, $images, $runTime, $customerId);
        $this->addFileToZip($packagePath . "/file/", $zip, "file", $descriptionHtml, $images, $runTime, $customerId, 'description');
        $this->addFileToZip($packagePath . "/file/", $zip, "file", $specificationHtml, $images, $runTime, $customerId, 'specification');
        $this->addFileToZip($packagePath . "/file/", $zip, "file", $returnWarrantyTextHtml, $images, $runTime, $customerId, 'returns & warranty');
        // 支持发往国际的商品 需要下载对应的 国际单补运费信息
        $internaltion = app(EuropeFreightRepository::class)->getInternationalConfig($countryId);
        if ($internaltion) {
            $internationFulfillmentFeeHtml = $this->internationalFulfillmentFee($productId,$countryId);
            $this->addFileToZip($packagePath . "/file/", $zip, "file", $internationFulfillmentFeeHtml, $images, $runTime, $customerId, 'international fulfillment fee');
        }
        //seller上传的素材包
        foreach (['image', 'file', 'video', 'original_design_image'] as $value) {
            $packagesArr = app(ProductRepository::class)->getProductPackages($productId, $value);
            if ($packagesArr) {
                $this->addFileToZipFromDB($packagePath,$zip, $packagesArr, $value);
            }
        }
        // 认证文件
        $certificationDocuments = ProductCertificationDocument::query()->with('type')->where('product_id', $productId)->get();
        $certificationDocumentsTypeNames = [];
        foreach ($certificationDocuments as $certificationDocument) {
            $typeName = $certificationDocument->type->title;
            if (isset($certificationDocumentsTypeNames[$typeName])) {
                $certificationDocumentsTypeNames[$typeName] += 1;
            } else {
                $certificationDocumentsTypeNames[$typeName] = 0;
            }

            $certificationDocumentFileName = $typeName . ($certificationDocumentsTypeNames[$typeName] ? "($certificationDocumentsTypeNames[$typeName])" : '');
            /** @var ProductCertificationDocument $certificationDocument */
            $relativePath = $certificationDocument->url;
            $addingFileName = 'file/'. $certificationDocumentFileName . '.' . pathinfo($relativePath, PATHINFO_EXTENSION);
            $this->addCloudFileToZip($packagePath, $zip, $addingFileName, $relativePath);
        }

        if ($isOverSize) {
            $this->addFileToZip($packagePath . "/oversizeNotice/", $zip);
        }
        $moduleShipmentTimeStatus = Setting::getConfig('module_shipment_time_status');
        if ($moduleShipmentTimeStatus) {
            $shipmentTimePage = $this->getShipmentTime($countryId);
            // 获取文件路径
            if ($shipmentTimePage->file_path) {
                $shipmentFilePath = $this->getLocalTempPath($packagePath . '/shipmentFile', $shipmentTimePage->file_path);
                $shipmentFileRename = $shipmentTimePage->file_name;
                $zip->addFile($shipmentFileRename, $shipmentFilePath);
            }
        };
        // 打包csv文件
        $csvFileName = 'ProductsInfo_' . $product->mpn . date('Ymd-H-i-s', $runTime) . '.csv';
        $csvFilePath = $packagePath . '/' . $csvFileName;
        try {
            app(ProductRepository::class)->getProductCategoryCsv($customerId, $csvFilePath, $productId);
            $zip->addFile($csvFilePath, $csvFileName);
        } catch (\Exception $e) {
            LoggerHelper::logPackZip(['customer_id' => $customerId, 'product_id' => $productId, 'msg' => '打包CSV文件错误']);
        }
        $zip->close(); //关闭处理的zip文件
        // 上传zip到oss
        $filename = '/productPackage/' . $customerId . '/' . $fileName;
        $res = Storage::cloud()->putFileAs('/productPackage/' . $customerId, (new File($zipFileName)), $fileName);
        OssHelper::changeFileAcl($filename, 'private'); // 设为私有
        ProductDescription::where('product_id', $productId)->update(['packed_zip_path' => $filename, 'packed_time' => date('Y-m-d H:i:s')]);
        // 删除本地打包的文件
        $this->clearDir(storage_path() . '/productPackage/' . $customerId . '/' . $productId);
        return $res;

    }

    public function getShipmentTime($countryId)
    {
        return DB::table('oc_shipment_time')->where('country_id', $countryId)->first();
    }

    /**
     * 添加素材包
     *
     * 注：此方法没有关闭 ZipArchive 资源链接
     *
     * @param string $localPath
     * @param ZipArchive $zip
     * @param array $filesArr 将要添加进zip的文件数组
     * @param string $type image/file/video
     */
    private function addFileToZipFromDB($localPath, $zip, $filesArr, $type = 'image')
    {
        if (!in_array($type, ['image', 'file', 'video', 'original_design_image'])) {
            return;
        }
        if ($type == 'original_design_image') {
            $type = 'image';
        }

        $added_file_arr = [];   // 已添加到压缩包的文件路径
        $file_name_key = $type . '_name';
        $origin_file_name_key = 'origin_' . $type . '_name';
        foreach ($filesArr as $item) {
            $relativePath = $item->{$type};
            // 路径匹配
            $prefix = '';
            if (preg_match('/^(\d+)\/(\d+)\/(file|image|video)\/(.*)/', $relativePath)) {
                // 兼容原素材包路径
                $prefix = '/productPackage/';
            }
            $filePath = $prefix . $relativePath;
            $addingFileName = $type . '/' . ($item->{$origin_file_name_key} ?: $item->{$file_name_key});
            // 如果已存在，则使用 重命名后的文件名
            if (in_array($addingFileName, $added_file_arr)) {
                $addingFileName = $type . '/' . $this->token(5) . ($item->{$file_name_key});
            }
            $added_file_arr[] = $addingFileName;

            $this->addCloudFileToZip($localPath, $zip, $addingFileName, $filePath);
        }
    }

    private function token($length = 32)
    {
        // Create random token
        $string = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

        $max = strlen($string) - 1;

        $token = '';

        for ($i = 0; $i < $length; $i++) {
            $token .= $string[mt_rand(0, $max)];
        }

        return $token;
    }

    /**
     * 添加远程文件到zip
     * @param $localPath
     * @param $zip
     * @param $zipFileName
     * @param $path
     */
    protected function addCloudFileToZip($localPath, $zip, $zipFileName, $path)
    {
        $path = 'image/' . $path;
        if (!Storage::cloud()->exists($path)) {
            LoggerHelper::logPackZip($zipFileName . ' zip cloud not exist: ' . $path, 'warning');
            return;
        }
        $fileName = $this->getLocalTempPath($localPath, $path);
        if (empty($fileName)) {
            return;
        }
        $zip->addFile($fileName, $zipFileName);
    }


    /**
     * 获取临时名称
     * @param $path
     * @return string
     */
    protected function getTempFileName($path)
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return md5($path) . ($extension ? ('.' . $extension) : '');
    }


    /**
     * cloud下载到本地临时文件
     * @param $localPath
     * @param $path
     * @return false|string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getLocalTempPath($localPath, $path)
    {
        $filename = $localPath . '/' . $this->getTempFileName($path);
        if (!file_exists($filename)) {
            if (!Storage::cloud()->exists($path)) {
                return false;
            }
            $content = Storage::cloud()->get($path);
            if (!$content) {
                return false;
            }
            if (!is_dir(pathinfo($filename, PATHINFO_DIRNAME))) {
                mkdir(pathinfo($filename, PATHINFO_DIRNAME), 0777, true);
            }
            file_put_contents($filename, $content);
        }
        return $filename;
    }

    /**
     * 添加本地文件到zip
     * @param $zip
     * @param $zipFileName
     * @param $path
     * @param bool $checkExist
     */
    protected function addLocalFileToZip($zip, $zipFileName, $path, $checkExist = true)
    {
        if ($checkExist && !file_exists($path)) {
            Log::error('zip local not exist', $path);
            return;
        }
        $zip->addFile($path, $zipFileName);
    }


    /**
     * @param string $path
     * @param $zip
     * @param null $dir
     * @param null $html
     * @param null $imagesArray
     * @param null $run_id
     * @param null $customerId
     * @param string $file_name
     */
    private function addFileToZip(
        $path,
        $zip,
        $dir = null,
        $html = null,
        $imagesArray = null,
        $run_id = null,
        $customerId = null,
        $file_name = 'description'
    )
    {
        /**
         * 如果 HTML为null, 代表有素材包, 则直接取素材包。
         * 如果 HTML不为null，则代表没有素材包；如果 路径为 image，则获取主图，如果路径为file，则生产description文件
         */
        if (is_null($html)) {
            if (file_exists($path)) {
                $handler = opendir($path); //打开当前文件夹由$path指定。
                while (($filename = readdir($handler)) !== false) {
                    if ($filename != "." && $filename != "..") {//文件夹文件名字为'.'和‘..’，不要对他们进行操作
                        if (is_dir($path . "/" . $filename)) {// 如果读取的某个对象是文件夹，则递归
                            $this->addFileToZip($path . "/" . $filename, $zip);
                        } else { //将文件加入zip对象
                            $this->addLocalFileToZip($zip, $dir . $filename, $path . '/' . $filename, false);
                        }
                    }
                }
                @closedir($path);
            }
        } else {
            if ($dir == 'image') {
                foreach (array_unique($imagesArray) as $image) {
                    if (!empty($image)) {
                        $this->addCloudFileToZip($path, $zip, $dir . '/' . basename($image), $image);
                    }
                }
            } else if ($dir == 'file') {
                if (trim($html)) {
                    $customerId = $customerId ?: 0;
                    $htmlPath = $path . '/' . md5('product_' . $file_name . '_' . $customerId . '_' . $run_id) . '.html';
                    if (!is_dir(pathinfo($htmlPath, PATHINFO_DIRNAME))) {
                        mkdir(pathinfo($htmlPath, PATHINFO_DIRNAME), 0777, true);
                    }
                    !is_file($htmlPath) && touch($htmlPath);
                    $fh = fopen($htmlPath, "w");
                    fwrite($fh, $html);
                    fclose($fh);
                    $this->addLocalFileToZip($zip, $dir . "/" . $file_name . '.html', $htmlPath, false);
                }
            }
        }
    }


    /**
     * 用于Summernote富文本，HTML反转义
     * @param string $str
     * @param bool $isAll true 直接处理
     * @return string
     */
    public static function decode($str = '', $isAll = false): string
    {
        if (!is_string($str)) {
            $str = strval($str);
        }
        if ($isAll) {
            return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
        } else {
            if (strpos($str, '&lt;') === 0) {
                return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
            } else {
                return $str;
            }
        }
    }

    /**
     * 下载素材包-生成简单的子属性素材页面
     *
     * @param int $productId 商品ID
     * @param $countryId
     * @return string
     */
    private function internationalFulfillmentFee($productId,$countryId)
    {
        $europeFreightRepo = app(EuropeFreightRepository::class);
        $freightAll = $europeFreightRepo->getAllCountryFreight($productId);

        $subHtml = '';
        if ($freightAll) {
            $europeFreightRepo = app(EuropeFreightRepository::class);
            $freightAll = $europeFreightRepo->getAllCountryFreight($productId);

            foreach ($freightAll as $value) {
                $freight = $value['freight'] < 0 ? 0 : ceil($value['freight']);
                $freight = Currency::format($freight, $countryId);
                $subHtml .= sprintf('<tr><td style="border: 1px solid #dbdbdb;padding-left: 24px;padding-right: 24px;"> %s </td>
                            <td style="border: 1px solid #dbdbdb;text-align: center;width: 180px;"> %s </td>
                            <td style="border: 1px solid #dbdbdb;padding-right: 24px;width: 197px;text-align: right;"> %s </td></tr>', $value['country_en'], $value['country_code'], $freight);
            }
        } else {
            $subHtml = '<tr><td colspan="3"> The size of this product unable to be shipped overseas due to not meeting international shipping standards. </td></tr>';
        }

        $html = sprintf('<table style="border: 1px solid #dbdbdb;border-collapse: collapse; margin-bottom: 20px;line-height: 35px;"><thead><tr>
                            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;padding-left: 24px;"> %s </td>
                            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;text-align: center;width: 180px;"> %s </td>
                            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;padding-right: 24px;width: 197px;text-align: right;"> %s </td>
                        </tr></thead><tbody> %s </tbody></table>', 'Country', 'Country Code', 'Fulfillment Fee', $subHtml);

        return $html;
    }

    public function clearDir($path = null)
    {
        if (is_dir($path)) {    //判断是否是目录
            $p = scandir($path);     //获取目录下所有文件
            foreach ($p as $value) {
                if ($value != '.' && $value != '..') {    //排除掉当./和../
                    if (is_dir($path . '/' . $value)) {
                        $this->clearDir($path . '/' . $value);    //递归调用删除方法
                        rmdir($path . '/' . $value);    //删除当前文件夹
                    } else {
                        if (file_exists($path . '/' . $value)) {
                            @unlink($path . '/' . $value);    //删除当前文件
                        }
                    }
                }
            }

        }
    }

    /**
     * 打包所有产品
     * @param null $type
     * @param null $acl
     * @param null $productGt
     */
    public function packAllProduct($type = null, $acl = null, $productGt = null)
    {
        $products = app(ProductRepository::class)->getAllActiveProducts($type,$productGt);

        if ($acl) {
            // 修改文件的acl
            $ids = array_column($products, 'product_id');
            $data = DB::table('oc_product_description')
                ->select(['product_id', 'packed_zip_path'])
                ->whereIn('product_id', $ids)
                ->where('packed_zip_path', '!=', '')
                ->cursor();
            $hasError = false;
            foreach ($data as $item) {
                try {
                    OssHelper::changeFileAcl($item->packed_zip_path, $acl);
                    echo "change {$item->product_id} acl to {$acl}" . PHP_EOL;
                } catch (Throwable $e) {
                    $hasError = true;
                    LoggerHelper::logPackZip('ACL error: ' . $e->getMessage(), 'error');
                }
            }
            if ($hasError) {
                echo 'has error see log !!!';
            }
            return;
        }

        // 打包
        foreach ($products as $product) {
            if (empty($product['customer_id']) || empty($product['product_id'])) {
                continue;
            }
            PackToZip::dispatch($product)->onQueue('pack_to_zip');
        }
    }

    public static function updateComplexTransactionProductIds()
    {
        $productRepository = new ProductRepository();
        $productIds = $productRepository->getComplexTransactionProductIds();
        $existProductIds = DB::connection('mysql_proxy')
            ->table('oc_product_crontab')
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->toArray();
        $addProductIds = array_diff($productIds,$existProductIds);
        DB::connection('mysql_proxy')->table('oc_product_crontab')->update(['is_complex_transaction' => 0]);
        if($existProductIds){
            DB::connection('mysql_proxy')->table('oc_product_crontab')
                ->whereIn('product_id', $existProductIds)
                ->update(['is_complex_transaction' => 1]);
        }

        if ($addProductIds) {
            foreach ($addProductIds as $productId) {
                DB::connection('mysql_proxy')->table('oc_product_crontab')
                    ->insert(['is_complex_transaction' => 1, 'product_id' => $productId]);
            }
        }

    }
}