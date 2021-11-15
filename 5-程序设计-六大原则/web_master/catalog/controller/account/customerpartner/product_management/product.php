<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Components\Locker;
use App\Logging\Logger;
use App\Models\Product\ProductImportBatch;
use App\Repositories\Product\ProductImportRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Seller\SellerRepository;
use App\Services\Product\ImportProductService;
use App\Services\Product\ModifyProductService;
use App\Services\Product\TemplateService;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ControllerAccountCustomerpartnerProductManagementProduct extends AuthSellerController
{
    /**
     * @var mixed
     */
    protected $sellerId;

    /**
     * @var mixed
     */
    protected $countryId;

    /**
     * @var array
     */
    protected $data = [];

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        $this->sellerId = intval($this->customer->getId());

        $this->countryId = intval($this->customer->getCountryId());
    }

    //导入产品页面
    public function importProducts()
    {
        $this->initPage(__('上传产品', [], 'catalog/document'));
        $this->data['breadcrumbs'][] = [
            'text' => __('上传产品', [], 'catalog/document'),
            'href' => $this->url->to('account/customerpartner/product_management/product/importProducts'),
        ];

        return $this->render('account/customerpartner/product_management/import_product', $this->data);
    }

    //导入产品页面的数据走接口 要转时区:结果是js渲染的
    public function getImportRecords()
    {
        $result = app(ProductImportRepository::class)->getProductImportsLimitByCustomerId($this->sellerId, customer()->getCountryId(), 6);
        return $this->json(['data' => $result]);
    }

    /**
     * 下载导入产品的模板
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function downloadImportProductsTemplate()
    {
        app(TemplateService::class)->generateImportProductsTemplateByCountry($this->countryId);
    }

    /**
     * 下载导入修改价格的模板
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function downloadModifyPriceTemplate()
    {
        app(TemplateService::class)->generateModifyPriceTemplate();
    }

    /**
     * 下载批量修改商品的模板
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function downloadModifyProductTemplate()
    {
        return app(TemplateService::class)->generateExportProductsTemplate();
    }

    /**
     * 导入历史页面
     * @return string
     */
    public function importProductsRecords()
    {
        $fromDate = request()->query->get('filter_date_from', '');
        $endDate = request()->query->get('filter_date_to', '');
        $perPage = request()->query->get('page_limit', 25);
        $page = request()->query->get('page', 1);

        $this->initPage(__('历史记录', [], 'catalog/document'));
        $this->data['breadcrumbs'][] = [
            'text' => __('历史记录', [], 'catalog/document'),
            'href' => $this->url->to('account/customerpartner/product_management/product/importProductsRecords'),
        ];

        $records = app(ProductImportRepository::class)->getProductImportsPaginateByCustomerIdAndRangeTime($this->sellerId, $fromDate, $endDate, $perPage, $page);
        $this->data['total'] = $records->total();
        $this->data['imports'] = $records->items();
        $this->data['page'] = $page;
        $this->data['total_page'] = $records->lastPage();
        $this->data['page_limit'] = $perPage;
        $this->data['filter_date_from'] = $fromDate;
        $this->data['filter_date_to'] = $endDate;

        return $this->render('account/customerpartner/product_management/import_product_record', $this->data);
    }

    /**
     * 下载导入产品的错误报告
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function exportErrorReport()
    {
        $batchId = (int)request()->query->get('import_id', '');
        $batchDetail = ProductImportBatch::query()->where('customer_id', customer()->getId())->where('id', (int)$batchId)->first();

        if (empty($batchDetail)) {
            return $this->jsonFailed();
        }

        return app(TemplateService::class)->generateImportProductsErrorReportByBatchId($batchDetail->toArray(), $this->countryId, $this->sellerId);
    }

    /**
     * 处理批量修改价格文件
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function modifyPricesFile()
    {
        $skipCheckPrice = request()->input->get('skip_check_price', 1);

        $templateFileHeader = ['*MPN', '*Modify Price', 'Date of Effect(YYYY-MM-DD H)'];
        try {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
            [$rows, $file] = $this->readXslFiles('file', $templateFileHeader, 0, 0);
        } catch (Exception $e) {
            return $this->jsonFailed($e->getMessage());
        }

        // 加锁阻止重复上传
        $lock = Locker::modifyPrices($this->sellerId, 100);
        if (!$lock->acquire()) {
            return $this->jsonFailed(__('该用户已有批量修改价格文件在上传处理，请稍后再试！', [], 'controller/product'));
        }
        try {
            $indexName = ['MPN', 'Modify Price', 'Date of Effect'];
            $prices = [];
            foreach ($rows as $key => $row) {
                if (empty(array_filter($row))) {
                    continue;
                }
                // 组装key-value结构
                $prices[$key] = array_combine($indexName, array_map('trim', $row));
            }
            if (empty($prices)) {
                $lock->release();
                return $this->jsonFailed(__('文件为空，请重新上传！', [], 'common/upload'));
            }

            [$errorCount, $error, $skipCheckPriceSkus] = app(ProductRepository::class)->modifyPrices($prices, $this->sellerId, $skipCheckPrice);
        } catch (Exception $e) {
            $lock->release();
            Logger::modifyPrices('批量修改价格报错:' . $e->getMessage(), 'error');
            return $this->jsonFailed(__('处理失败！', [], 'controller/product'));
        } finally {
            $lock->release();
        }

        if (isset($errorCount) && $errorCount > 0) {
            return $this->jsonFailed(__('处理失败！', [], 'controller/product'), ['errors' => $error], 400);
        }

        if (!empty($skipCheckPriceSkus)) {
            // 是否显示云送仓提醒
            $isShowCwfNotice = app(SellerRepository::class)->isShowCwfNotice();
            $skipCheckPriceSkuStr = join(',', $skipCheckPriceSkus);
            $msg = $isShowCwfNotice ? __('批量修改价格触发云送仓提醒', ['codes' => $skipCheckPriceSkuStr], 'controller/product') : __('批量修改价格触发纯物流提醒', ['codes' => $skipCheckPriceSkuStr], 'controller/product');
            return $this->jsonFailed('', ['message' => $msg], 202);
        }

        return $this->jsonSuccess(['errors' => $error], __('处理成功！', [], 'controller/product'));
    }

    /**
     * 读取xls文件
     * @param string $fileName
     * @param array $templateFileHeader
     * @param int $multipleLines 是否多行  区分价格or商品
     * @param int $importType 导入类型  1：批量导入商品 2：批量修改商品 0:老逻辑
     * @return array
     * @throws Exception
     */
    private function readXslFiles(string $fileName, array $templateFileHeader, int $multipleLines = 0, int $importType = 0)
    {
        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
        $file = request()->filesBag->get($fileName, '');

        if (!is_uploaded_file($file->getPathname())) {
            throw new Exception(__('文件不能上传！', [], 'common/upload'));
        }
        if ($file->getError() != UPLOAD_ERR_OK) {
            throw new Exception(__choice('error_upload_code', $file->getError(), [], 'common/upload'));
        }
        // 校验文件上传的格式是否为 被允许的格式
        if (empty($file->getClientOriginalExtension()) || !in_array($file->getClientOriginalExtension(), ['xls', 'xlsx'])) {
            throw new Exception(__('错误的文件格式！', [], 'common/upload'));
        }

        // 获取文件数据
        try {
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
        } catch (Exception $e) {
            throw new Exception(__('文件不能上传！', [], 'common/upload'));
        }

        if ($multipleLines == 0) { //修改价格那时候会这样
            // 校验 第一行和第二行是否为空 + 第三行
            if (empty($rows[0]) || empty($rows[1])) {
                throw new Exception(__('上传的Excel文件内容不正确或数据行为空。', [], 'common/upload'));
            }
        } else {
            // 校验 第一行和第二行是否为空 + 第三行
            if (empty($rows[0]) || empty($rows[1]) || empty($rows[2])) {
                throw new Exception(__('上传的Excel文件内容不正确或数据行为空。', [], 'common/upload'));
            }
        }

        $fileHeader = array_map('trim', $rows[0]);
        foreach ($templateFileHeader as $index => $header) {
            if (ltrim($fileHeader[$index], '*') != ltrim($header, '*')) {
                if ($importType == 1) {
                    throw new Exception(__('文件表头信息不正确，请重新下载导入模板。', [], 'controller/product'));
                } elseif ($importType == 2) {
                    throw new Exception(__('文件表头信息不正确，请重新下载修改模板。', [], 'controller/product'));
                } else {
                    throw new Exception(__('上传的Excel文件内容不正确或数据行为空。', [], 'common/upload'));
                }
            }
        }

        if ($multipleLines == 0) {
            unset($rows[0]);
        } else {
            unset($rows[0], $rows[1]);
        }

        return [$rows, $file];
    }

    /**
     * 处理批量导入新增产品文件
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function importProductsFile()
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        if ($this->countryId == AMERICAN_COUNTRY_ID) {
            $templateFileHeader = ['*Category ID', '*MPN', 'UPC', '*Sold Separately', 'Not available for sale on', '*Product Title', '*Customized', 'Place of Origin', '*Main Color', '*Main Material', 'Filler', '*Assembled Length(inch)', '*Assembled Width(inch)', '*Assembled Height(inch)', '*Weight(lb)', '*Product Type', 'Sub-items(MPN)', 'Sub-items Quantity', '*Length(inch)', '*Width(inch)', '*Height(inch)', '*Weight(lb)', '*Current Price', '*Display Price(Invisible/Visible)'];
        } else {
            $templateFileHeader = ['*Category ID', '*MPN', 'UPC', '*Sold Separately', 'Not available for sale on', '*Product Title', '*Customized', 'Place of Origin', '*Main Color', '*Main Material', 'Filler', '*Assembled Length(cm)', '*Assembled Width(cm)', '*Assembled Height(cm)', '*Weight(kg)', '*Product Type', 'Sub-items(MPN)', 'Sub-items Quantity', '*Length(cm)', '*Width(cm)', '*Height(cm)', '*Weight(kg)', '*Current Price', '*Display Price(Invisible/Visible)'];
        }
        $extendFileHeader = ['Product Description', 'Main Image Path', 'Images Path(to be displayed)', 'Images Path(other material)', '*Original Design', 'Supporting Files Path', 'Material Document Path', 'Material Video Path'];
        $templateFileHeader = array_merge($templateFileHeader, $extendFileHeader);

        try {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
            [$rows, $file] = $this->readXslFiles('file', $templateFileHeader, 1,1);
        } catch (Exception $e) {
            return $this->jsonFailed($e->getMessage());
        }

        // 加锁阻止重复上传
        $lock = Locker::importProducts($this->sellerId, 600);
        if (!$lock->acquire()) {
            return $this->jsonFailed(__('正在批量上传产品，请耐心等待', [], 'controller/product'));
        }
        try {
            $indexName = ['Category ID', 'MPN', 'UPC', 'Sold Separately', 'Not available for sale on', 'Product Title', 'Customized', 'Place of Origin', 'Color', 'Material', 'Filler', 'Assembled Length', 'Assembled Width', 'Assembled Height', 'Assembled Weight', 'Product Type', 'Sub-items', 'Sub-items Quantity', 'Length', 'Width', 'Height', 'Weight', 'Current Price', 'Display Price'];
            $indexNameExtend = ['Product Description', 'Main Image Path', 'Images Path(to be displayed)', 'Images Path(other material)', 'Original Design', 'Supporting Files Path', 'Material Manual Path', 'Material Video Path'];
            $indexName = array_merge($indexName, $indexNameExtend);

            $products = [];
            foreach ($rows as $row) {
                if (empty(array_filter($row))) {
                    continue;
                }
                $row = array_slice($row, 0, 32);
                // 组装key-value结构
                $products[] = array_combine($indexName, array_map('trim', $row));
            }
            if (empty($products)) {
                $lock->release();
                return $this->jsonFailed(__('文件为空，请重新上传！', [], 'common/upload'));
            }

            [$ltlProductIds, $productImportBatch] = app(ImportProductService::class)->addImportProducts($products, $this->sellerId, $this->countryId, $file->getClientOriginalName());
        } catch (Exception $e) {
            $lock->release();
            Logger::importProducts('批量导入(新增商品)错误报告添加失败:' . $e->getMessage(), 'error');
            Logger::importProducts('批量导入(新增商品)错误报告添加失败(详细):', 'error', [
                Logger::CONTEXT_VAR_DUMPER => ['err_infos' => $e],
            ]);
            return $this->jsonFailed(__('处理失败！', [], 'controller/product'));
        } finally {
            $lock->release();
        }

        return $this->jsonSuccess(['ltl_product_ids' => join(',', $ltlProductIds), 'product_import' => $productImportBatch], __('处理成功！', [], 'controller/product'));
    }

    /**
     * 批量修改商品
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function importModifyProductsFile()
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $templateFileHeader = ['Category ID', '*MPN', 'UPC', 'Sold Separately', 'Not available for sale on', 'Product Title', 'Customized', 'Place of Origin', 'Main Color', 'Main Material', 'Filler'];
        if ($this->countryId == AMERICAN_COUNTRY_ID) {
            $assembledHeader = ['Assembled Length(inch)', 'Assembled Width(inch)', 'Assembled Height(inch)', 'Weight(lb)'];
        } else {
            $assembledHeader = ['Assembled Length(cm)', 'Assembled Width(cm)', 'Assembled Height(cm)', 'Weight(kg)'];
        }
        $extendFileHeader = ['Product Description', 'Main Image Path', 'Images Path(to be displayed)', 'Images Path(other material)', 'Original Design', 'Supporting Files Path', 'Material Document Path', 'Material Video Path'];
        $templateFileHeader = array_merge($templateFileHeader, $assembledHeader, $extendFileHeader);

        try {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
            [$rows, $file] = $this->readXslFiles('file', $templateFileHeader, 1, 2);
        } catch (Exception $e) {
            return $this->jsonFailed($e->getMessage());
        }
        // 加锁阻止重复上传
        $lock = Locker::importProducts($this->sellerId, 600);
        if (!$lock->acquire()) {
            return $this->jsonFailed(__('正在批量修改产品，请耐心等待', [], 'controller/product'));
        }
        try {
            $indexName = ['Category ID', 'MPN', 'UPC', 'Sold Separately', 'Not available for sale on', 'Product Title', 'Customized', 'Place of Origin', 'Color', 'Material', 'Filler', 'Assembled Length', 'Assembled Width', 'Assembled Height', 'Assembled Weight'];
            $indexNameExtend = ['Product Description', 'Main Image Path', 'Images Path(to be displayed)', 'Images Path(other material)', 'Original Design', 'Supporting Files Path', 'Material Manual Path', 'Material Video Path'];
            $indexName = array_merge($indexName, $indexNameExtend);

            $products = [];
            foreach ($rows as $row) {
                if (empty(array_filter($row))) {
                    continue;
                }
                $row = array_slice($row, 0, 23);
                $products[] = array_combine($indexName, array_map('trim', $row));
            }
            if (empty($products)) {
                $lock->release();
                return $this->jsonFailed(__('文件为空，请重新上传！', [], 'common/upload'));
            }
            [$productIds, $productImportBatch] = app(ModifyProductService::class)->editImportProducts($products, $this->sellerId, $this->countryId, $file->getClientOriginalName());
        } catch (Exception $e) {
            $lock->release();
            Logger::importProducts('批量导入(编辑商品)失败:' . $e->getMessage(), 'error');
            Logger::importProducts('批量导入(编辑商品)失败(详细)', 'error', [
                Logger::CONTEXT_VAR_DUMPER => ['batch_modify_product_detail' => $e],
            ]);
            return $this->jsonFailed(__('处理失败！', [], 'controller/product'));
        } finally {
            $lock->release();
        }
        return $this->jsonSuccess(['product_ids' => join(',', $productIds), 'product_import' => $productImportBatch], __('处理成功！', [], 'controller/product'));
    }

    private function initPage(string $title)
    {
        $this->setDocumentInfo($title);

        $this->data['separate_view'] = true;
        $this->data['separate_column_left'] = $this->renderController('account/customerpartner/column_left');
        $this->data['footer'] = $this->renderController('account/customerpartner/footer');
        $this->data['header'] = $this->renderController('account/customerpartner/header');

        $this->data['breadcrumbs'] = [
            [
                'text' => __('产品列表', [], 'catalog/document'),
                'href' => $this->url->to('customerpartner/product/lists/index'),
            ],
        ];
    }
}
