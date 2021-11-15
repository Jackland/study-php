<?php

namespace App\Repositories\Product;

use App\Components\Storage\StorageCloud;
use App\Components\Storage\StorageLocal;
use App\Enums\Product\ProductType;
use App\Logging\Logger;
use App\Models\Customer\Customer;
use App\Models\Product\Product;
use Framework\Exception\Exception;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use ZipArchive;

class PackageRepository
{
    /**
     * 素材包下载
     * @param int $productId
     * @param Customer $customer
     * @return Response|void
     * @throws Exception
     * @throws FilesystemException
     */
    public function download(int $productId, Customer $customer)
    {
        /** @var Product $product */
        $product = Product::queryRead()
            ->with(['customerPartnerToProduct', 'description', 'customerPartnerToProduct.customer', 'customerPartnerToProduct.customer.seller'])
            ->where('product_id', $productId)
            ->first();
        if (empty($product)) {
            throw new Exception();
        }

        $filePath = $product->description->packed_zip_path;
        $sellerCode = $product->customerPartnerToProduct->customer->full_name;
        $itemCode = $product->customerPartnerToProduct->customer->seller->self_support == 1 ? $product->sku : $product->mpn;
        $fileName = $sellerCode . '_' . $product->customerPartnerToProduct->customer->seller->screenname . '_' . $itemCode . '_' . date('Ymd') . '.zip';
        $fileName = str_replace([' ', ',', '/', '\\', '&amp;'], '_', $fileName);
        if (!$filePath || !StorageCloud::root()->fileExists($filePath)) {
            throw new Exception();
        }

        /** @var \ModelCatalogProduct $modelCatalogProduct */
        $modelCatalogProduct = load()->model('catalog/product');
        /** @var \ModelAccountWishlist $modelAccountWishlist */
        $modelAccountWishlist = load()->model('account/wishlist');
        $modelAccountWishlist->setProductsToWishGroup($productId);
        $modelCatalogProduct->packageDownloadHistory($productId);

        if ($customer->is_eu_vat_buyer && $product->product_type == ProductType::NORMAL) {
            try {
                $tempPath = StorageCloud::root()->getLocalTempPath($filePath);
                $unzipPath =  'temp/package_zip/' . $productId;
                $this->unzip($tempPath, $unzipPath);
                $this->updateCsvPrice($unzipPath, $product->customerPartnerToProduct->customer_id);
                $this->downloadUpdatedZip($unzipPath, $fileName);
                exit();
            } catch (Throwable $e) {
                Logger::packZip('针对于免税价修改的素材包生成失败:' . $e, 'error');
                return StorageCloud::root()->browserDownload($filePath, $fileName);
            }
        }

        return StorageCloud::root()->browserDownload($filePath, $fileName);
    }

    /**
     * 下载素材包
     * @param $unzipPath
     * @param $fileName
     */
    private function downloadUpdatedZip($unzipPath, $fileName)
    {
        $openPath = DIR_STORAGE . $unzipPath;
        $zip = new ZipArchive();
        if ($zip->open($openPath . '.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $this->addFileToZip($openPath, $zip);
            $zip->close();
        }
        StorageLocal::storage()->browserDownload($unzipPath . '.zip', $fileName)->send();
        $this->clearDir($openPath);
        if (file_exists($openPath . '.zip')) {
            @unlink($openPath . '.zip');
        }
    }

    /**
     * 删除临时文件
     * @param null $path
     */
    private function clearDir($path = null)
    {
        if (is_dir($path)) {
            $p = scandir($path);
            foreach ($p as $value) {
                if ($value != '.' && $value != '..') {
                    if (is_dir($path . '/' . $value)) {
                        $this->clearDir($path . '/' . $value);
                        rmdir($path . '/' . $value);
                    } else {
                        if (file_exists($path . '/' . $value)) {
                            @unlink($path . '/' . $value);
                        }
                    }
                }
            }
        }
    }

    /**
     * 添加文件到zip中
     * @param $path
     * @param ZipArchive $zip
     * @param string $dir
     */
    private function addFileToZip($path, ZipArchive $zip, string $dir = '')
    {
        $handler = opendir($path);

        while (($filename = readdir($handler)) !== false) {
            if ($filename != "." && $filename != "..") {
                if (is_dir($path . "/" . $filename)) {
                    $this->addFileToZip($path . "/" . $filename, $zip, $filename . '/');
                } else {
                    //将文件加入zip对象
                    $zip->addFile($path . "/" . $filename, $dir . $filename);
                }
            }
        }
        @closedir($path);
    }

    /**
     * 更新价格
     * @param $unzipPath
     * @param $sellerId
     */
    private function updateCsvPrice($unzipPath, $sellerId)
    {
        $unzipPath = DIR_STORAGE . $unzipPath;
        foreach (glob($unzipPath . '/*.csv') as $csv) {
            if (!$fp = fopen($csv, 'r')) {
                continue;
            }
            $data = array();
            while (!feof($fp)) {
                $data[] = fgetcsv($fp);
            }
            fclose($fp);

            $tempFile = fopen($unzipPath . '/temp_product.csv', 'w');
            $unitPriceKey = null;
            foreach ($data as $k => $v) {
                if (empty($v)) {
                    continue;
                }
                if ($k == 0) {
                    $unitPriceKey = array_search('Unit Price', $v); // 为打包的csv中的价格字段，如打包变动，此处也需变动
                    fputcsv($tempFile, $v);
                    continue;
                }
                if (!is_null($unzipPath) && $unitPriceKey !== false) {
                    $v[$unitPriceKey] = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($sellerId, customer()->getModel(), $v[$unitPriceKey]);
                }
                fputcsv($tempFile, $v);
            }
            fclose($tempFile);

            unlink($csv);// Delete obsolete BD
            rename($unzipPath . '/temp_product.csv', $csv);
        }
    }

    /**
     * 解压
     * @param $fromName
     * @param $toName
     */
    private function unzip($fromName, $toName)
    {
        $toName = DIR_STORAGE . $toName;
        if (!file_exists($fromName)) {
            return;
        }

        $zip = new ZipArchive();
        if (!$zip->open($fromName)) {
            return;
        }
        if (!$zip->extractTo($toName)) {
            $zip->close();
            return;
        }

        unlink($fromName);
        $zip->close();
    }
}
