<?php

namespace App\Services\Product;

use App\Enums\Common\YesNoEnum;
use App\Enums\Product\BuyFlag;
use App\Enums\Product\ComboFlag;
use App\Enums\Product\PriceDisplay;
use App\Enums\Product\ProductAuditStatus;
use App\Enums\Product\ProductAuditType;
use App\Enums\Product\ProductStatus;
use App\Helper\CountryHelper;
use App\Helper\SummernoteHtmlEncodeHelper;
use App\Models\Customer\Country;
use App\Models\Product\Option\Option;
use App\Models\Product\ProductImportBatchErrorReport;
use App\Repositories\Product\CategoryRepository;
use App\Repositories\Product\ProductImportRepository;
use App\Repositories\Product\ProductOptionRepository;
use App\Repositories\Product\ProductRepository;
use Carbon\Carbon;
use Cart\Currency;
use Framework\Exception\Exception;
use Framework\Log\Logger;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class TemplateService
{
    /**
     * @var Spreadsheet
     */
    protected $spreadsheet;

    /**
     * @var string
     */
    protected $fileName;

    /** @var Currency */
    private $currency;

    /**
     * @var array 剧中对齐
     */
    private $applyForm = [];

    /**
     * AbstractTemplateService constructor.
     * @param Spreadsheet $spreadsheet
     */
    public function __construct(Spreadsheet $spreadsheet)
    {
        $this->spreadsheet = $spreadsheet;
        $this->currency = app('registry')->get('currency');

        $this->applyForm = [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function __destruct()
    {
        $this->write();
        $this->spreadsheet->disconnectWorksheets();
        unset($this->spreadsheet);
    }

    /**
     * 生成批量修改价格的模板
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function generateModifyPriceTemplate()
    {
        $this->fileName = 'modify-price-template.xlsx';
        $worksheet = $this->spreadsheet->getActiveSheet()->setTitle('prices');
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(25);
        $worksheet->getColumnDimension('C')->setWidth(45);
        $worksheet->setCellValue('A1', '*MPN');
        $worksheet->setCellValue('B1', '*Modify Price');
        $worksheet->setCellValue('C1', 'Date of Effect(YYYY-MM-DD H)');

        $type = NumberFormat::FORMAT_NUMBER_00;
        if (customer()->isJapan()) {
            $type = NumberFormat::FORMAT_NUMBER;
        }

        $worksheet->getStyle('B2:B1000')->getNumberFormat()->setFormatCode($type);
        $worksheet->getStyle('C2:C1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_YYYYMMDD . ' ' . NumberFormat::FORMAT_DATE_TIME4);
    }

    /**
     * 生成批量导入产品的模板
     * @param int $countryId
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function generateImportProductsTemplateByCountry(int $countryId)
    {
        $this->fileName = __('批量导入模板.xlsx', [], 'service/product_import');


        $colorOptionNames = app(ProductOptionRepository::class)->getOptionsById(Option::COLOR_OPTION_ID)->pluck('name')->toArray();
        $materialOptionNames = app(ProductOptionRepository::class)->getOptionsById(Option::MATERIAL_OPTION_ID)->pluck('name')->toArray();

        array_multisort(array_map('strtolower', $colorOptionNames), SORT_ASC, SORT_STRING, $colorOptionNames);
        array_multisort(array_map('strtolower', $materialOptionNames), SORT_ASC, SORT_STRING, $materialOptionNames);

        $isUSA = $countryId == AMERICAN_COUNTRY_ID;

        $worksheet = $this->spreadsheet->getActiveSheet()->setTitle('Products');
        $worksheet->getStyle('A1:AF1')->getFont()->setBold(true);
        $worksheet->getStyle('A1:AF1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC6EFCD');
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(25);
        $worksheet->getColumnDimension('C')->setWidth(25);
        $worksheet->getColumnDimension('D')->setWidth(25);
        $worksheet->getColumnDimension('E')->setWidth(25);
        $worksheet->getColumnDimension('F')->setWidth(25);
        $worksheet->getColumnDimension('G')->setWidth(25);
        $worksheet->getColumnDimension('H')->setWidth(25);
        $worksheet->getColumnDimension('I')->setWidth(25);
        $worksheet->getColumnDimension('J')->setWidth(25);
        $worksheet->getColumnDimension('K')->setWidth(25);
        $worksheet->getColumnDimension('L')->setWidth(25);
        $worksheet->getColumnDimension('M')->setWidth(25);
        $worksheet->getColumnDimension('N')->setWidth(25);
        $worksheet->getColumnDimension('O')->setWidth(25);
        $worksheet->getColumnDimension('P')->setWidth(35);
        $worksheet->getColumnDimension('Q')->setWidth(35);
        $worksheet->getColumnDimension('R')->setWidth(35);
        $worksheet->getColumnDimension('S')->setWidth(35);
        $worksheet->getColumnDimension('T')->setWidth(35);
        $worksheet->getColumnDimension('U')->setWidth(35);
        $worksheet->getColumnDimension('V')->setWidth(35);
        $worksheet->getColumnDimension('W')->setWidth(35);
        $worksheet->getColumnDimension('X')->setWidth(35);
        $worksheet->getColumnDimension('Y')->setWidth(35);
        $worksheet->getColumnDimension('Z')->setWidth(35);
        $worksheet->getColumnDimension('AA')->setWidth(35);
        $worksheet->getColumnDimension('AB')->setWidth(35);
        $worksheet->getColumnDimension('AC')->setWidth(35);
        $worksheet->getColumnDimension('AD')->setWidth(35);
        $worksheet->getColumnDimension('AE')->setWidth(35);
        $worksheet->getColumnDimension('AF')->setWidth(35);

        $worksheet->setCellValue('A1', '*Category ID');
        $worksheet->setCellValue('A2', __('从Category ID sheet表中选择类目，输入对应的CategoryID', [], 'service/product_import'));

        $worksheet->setCellValue('B1', '*MPN');
        $worksheet->setCellValue('C1', 'UPC');
        $worksheet->setCellValue('D1', '*Sold Separately');
        $worksheet->setCellValue('D2', __('产品是否能单独售卖
    注意：输入“No”会导致产品不在前台页面显示，buyer看不到该产品', [], 'service/product_import'));

        $worksheet->setCellValue('E1', 'Not available for sale on');
        $worksheet->setCellValue('E2', __('从Platform Names sheet表中选择不可售卖的平台，如果有多个用[|]隔开',[],'service/product_import'));

        $worksheet->setCellValue('F1', '*Product Title');
        $worksheet->setCellValue('G1', '*Customized');
        $worksheet->setCellValue('G2', __('该产品是否支持定制', [], 'service/product_import'));
        $worksheet->setCellValue('H1', 'Place of Origin');
        $worksheet->setCellValue('H2', __('原产地国家/地区', [], 'service/product_import'));
        $worksheet->setCellValue('I1', '*Main Color');
        $worksheet->setCellValue('J1', '*Main Material');
        $worksheet->setCellValue('K1', 'Filler');
        $worksheet->setCellValue('K2', __('选择填充物/内部材料', [], 'service/product_import'));

        $worksheet->setCellValue('L1', $isUSA ? '*Assembled Length(inch)' : '*Assembled Length(cm)');
        $worksheet->setCellValue('L2', __('产品尺寸
输入 0.01 到 999.99 之间的数字或输入“Not Applicable”', [], 'service/product_import'));
        $worksheet->setCellValue('M1', $isUSA ? '*Assembled Width(inch)' : '*Assembled Width(cm)');
        $worksheet->setCellValue('M2', __('产品尺寸
输入 0.01 到 999.99 之间的数字或输入“Not Applicable”', [], 'service/product_import'));
        $worksheet->setCellValue('N1', $isUSA ? '*Assembled Height(inch)' : '*Assembled Height(cm)');
        $worksheet->setCellValue('N2', __('产品尺寸
输入 0.01 到 999.99 之间的数字或输入“Not Applicable”', [], 'service/product_import'));
        $worksheet->setCellValue('O1', $isUSA ? '*Weight(lb)' : '*Weight(kg)');
        $worksheet->setCellValue('O2', __('产品尺寸
输入 0.01 到 999.99 之间的数字或输入“Not Applicable”', [], 'service/product_import'));

        $worksheet->setCellValue('P1', '*Product Type');
        $worksheet->setCellValue('P2', __('General item=可自行出售的物品；组合商品中的组成
Combo item = 多个商品组成，由1个以上的商品组成
Replacement part =替换件，不能单独出售',[],'service/product_import'));


        $worksheet->setCellValue('Q1', 'Sub-items(MPN)');
        $worksheet->setCellValue('R1', 'Sub-items Quantity');

        $worksheet->setCellValue('S1', $isUSA ? '*Length(inch)' : '*Length(cm)');
        $worksheet->setCellValue('S2', __('产品包装信息', [], 'service/product_import'));
        $worksheet->setCellValue('T1', $isUSA ? '*Width(inch)' : '*Width(cm)');
        $worksheet->setCellValue('T2', __('产品包装信息', [], 'service/product_import'));
        $worksheet->setCellValue('U1', $isUSA ? '*Height(inch)' : '*Height(cm)');
        $worksheet->setCellValue('U2', __('产品包装信息', [], 'service/product_import'));
        $worksheet->setCellValue('V1', $isUSA ? '*Weight(lb)' : '*Weight(kg)');
        $worksheet->setCellValue('V2', __('产品包装信息', [], 'service/product_import'));

        $worksheet->setCellValue('W1', '*Current Price');
        $worksheet->setCellValue('X1', '*Display Price(Invisible/Visible)');
        $worksheet->setCellValue('X2', __('Visible = 所有买家都可以查看价格和数量Invisible =在买方要求与卖方建立联系之前无法查看价格和数量', [], 'service/product_import'));

        $worksheet->setCellValue('Y1', 'Product Description');
        $worksheet->setCellValue('Y2', __('仅支持输入文字描述', [], 'service/product_import'));

        $worksheet->setCellValue('Z1', 'Main Image Path');
        $worksheet->setCellValue('Z2', __('请填入文件管理中的图片路径，限一张图片', [], 'service/product_import'));

        $worksheet->setCellValue('AA1', 'Images Path(to be displayed)');
        $worksheet->setCellValue('AA2', __('请填入文件管理中的图片路径，如果有多张图片用[|]隔开,最多支持26张', [], 'service/product_import'));

        $worksheet->setCellValue('AB1', 'Images Path(other material)');
        $worksheet->setCellValue('AB2', __('请填入文件管理中的图片路径，如果有多张图片用[|]隔开,最多支持27张', [], 'service/product_import'));

        $worksheet->setCellValue('AC1', '*Original Design');
        $worksheet->setCellValue('AC2', __('该产品是否获得专利或版权？', [], 'service/product_import'));

        $worksheet->setCellValue('AD1', 'Supporting Files Path');
        $worksheet->setCellValue('AD2', __('专利产品选择是时，专利证书必填。请填入文件管理中的图片路径，如果有多张图片用[|]隔开,最多支持9张', [], 'service/product_import'));

        $worksheet->setCellValue('AE1', 'Material Document Path');
        $worksheet->setCellValue('AE2', __('请填入文件管理中的图片/文档路径，如果有多个图片/文档用[|]隔开,最多支持27个文件', [], 'service/product_import'));

        $worksheet->setCellValue('AF1', 'Material Video Path');
        $worksheet->setCellValue('AF2', __('请填入文件管理中的txt文档路径，如果有多个文档用[|]隔开,最多支持27个文件', [], 'service/product_import'));

        $worksheet->getStyle('A2:AF2')->getFont()->getColor()->setARGB('808080'); //说明栏设置字体颜色为灰色

        //Category sheet
        $this->createCategortySheet();
        $countryCount = $this->createCountrySheet();

        $colorSheet = $this->spreadsheet->createSheet(4)->setTitle('Color');
        foreach ($colorOptionNames as $k => $name) {
            $j = $k + 1;
            $colorSheet->setCellValueByColumnAndRow(1, $j, $name);
        }

        $materialSheet = $this->spreadsheet->createSheet(5)->setTitle('Material.Filler');
        foreach ($materialOptionNames as $k => $name) {
            $j = $k + 1;
            $materialSheet->setCellValueByColumnAndRow(1, $j, $name);
        }

        $this->createPlatformSheet(); //platform sheet

        $mpnValidation = $worksheet->getCell('B3')->getDataValidation();
        $mpnValidation = $this->setextLengthValidationOption($mpnValidation)->setPrompt("MPN must be greater than 4 and less than 30 characters.")->setFormula1("=AND(LENB(B3)>=4,LENB(B3)<=30)");

        $upcValidation = $worksheet->getCell('C3')->getDataValidation();
        $upcValidation = $this->setextLengthValidationOption($upcValidation)->setPrompt("UPC must be less than 30 characters.")->setFormula1("=AND(LENB(C3)<=30)");

        $soldSeparatelyValidation = $worksheet->getCell('D3')->getDataValidation();
        $soldSeparatelyValidation = $this->setListValidationOption($soldSeparatelyValidation)->setFormula1('"Yes, No"');

        $productTitleValidation = $worksheet->getCell('F3')->getDataValidation();
        $productTitleValidation = $this->setextLengthValidationOption($productTitleValidation)->setPrompt("Product Title must be less than 200 characters.")->setFormula1("=AND(LENB(F3)>0,LENB(F3)<=200)");

        $customizedValidation = $worksheet->getCell('G3')->getDataValidation();
        $customizedValidation = $this->setListValidationOption($customizedValidation)->setFormula1('"Yes, No"');

        $placeOriginValidation = $worksheet->getCell('H3')->getDataValidation();
        $placeOriginValidation = $this->setListValidationOption($placeOriginValidation)->setFormula1('Country.Region!$A$1:$A$' . $countryCount);

        $colorValidation = $worksheet->getCell('I3')->getDataValidation();
        $colorValidation = $this->setListValidationOption($colorValidation)->setFormula1('Color!$A$1:$A$' . count($colorOptionNames));

        $materialValidation = $worksheet->getCell('J3')->getDataValidation();
        $materialValidation = $this->setListValidationOption($materialValidation)->setFormula1('Material.Filler!$A$1:$A$' . count($materialOptionNames));

        $fillerValidation = $worksheet->getCell('K3')->getDataValidation();
        $fillerValidation = $this->setListValidationOption($fillerValidation)->setFormula1('Material.Filler!$A$1:$A$' . count($materialOptionNames));

        $assembledLengthValidation = $worksheet->getCell('L3')->getDataValidation();
        $assembledLengthValidation = $this->setextLengthValidationOption($assembledLengthValidation)->setPrompt("Enter a number between 0.01 and 999.99 or 'Not Applicable'.");

        $assembledWidthValidation = $worksheet->getCell('M3')->getDataValidation()->setPrompt("Enter a number between 0.01 and 999.99 or 'Not Applicable'.");
        $assembledWidthValidation = $this->setextLengthValidationOption($assembledWidthValidation)->setPrompt("Enter a number between 0.01 and 999.99 or 'Not Applicable'.");

        $assembledHeightValidation = $worksheet->getCell('N3')->getDataValidation()->setPrompt("Enter a number between 0.01 and 999.99 or 'Not Applicable'.");
        $assembledHeightValidation = $this->setextLengthValidationOption($assembledHeightValidation)->setPrompt("Enter a number between 0.01 and 999.99 or 'Not Applicable'.");

        $assembledWeightValidation = $worksheet->getCell('O3')->getDataValidation()->setPrompt("Enter a number between 0.01 and 999.99 or 'Not Applicable'.");
        $assembledWeightValidation = $this->setextLengthValidationOption($assembledWeightValidation)->setPrompt("Enter a number between 0.01 and 999.99 or 'Not Applicable'.");

        $productTypeValidation = $worksheet->getCell('P3')->getDataValidation();
        $productTypeValidation = $this->setListValidationOption($productTypeValidation)->setFormula1('"General item, Combo item, Replacement part"');

        $lengthValidation = $worksheet->getCell('S3')->getDataValidation();
        $lengthValidation = $this->setDecimalValidationOption($lengthValidation);

        $widthValidation = $worksheet->getCell('T3')->getDataValidation();
        $widthValidation = $this->setDecimalValidationOption($widthValidation);

        $heightValidation = $worksheet->getCell('U3')->getDataValidation();
        $heightValidation = $this->setDecimalValidationOption($heightValidation);

        $weightValidation = $worksheet->getCell('V3')->getDataValidation();
        $weightValidation = $this->setDecimalValidationOption($weightValidation);

        $priceValidation = $worksheet->getCell('W3')->getDataValidation();
        if ($countryId == JAPAN_COUNTRY_ID) {
            $priceValidation = $this->setDecimalValidationOption($priceValidation, DataValidation::TYPE_WHOLE, 0, 9999999);
        } else {
            $priceValidation = $this->setDecimalValidationOption($priceValidation, DataValidation::TYPE_DECIMAL, 0.00, 9999999.99);
        }

        $displayPriceValidation = $worksheet->getCell('X3')->getDataValidation();
        $displayPriceValidation = $this->setListValidationOption($displayPriceValidation)->setFormula1('"Invisible, Visible"');

        $originDesignValidation = $worksheet->getCell('AC3')->getDataValidation();
        $originDesignValidation = $this->setListValidationOption($originDesignValidation)->setFormula1('"Yes, No"');

        for ($i = 3; $i <= 1001; $i++) {
            $worksheet->getCell('B' . $i)->setDataValidation(clone $mpnValidation);
            $worksheet->getCell('C' . $i)->setDataValidation(clone $upcValidation);
            $worksheet->getCell('D' . $i)->setDataValidation(clone $soldSeparatelyValidation);
            $worksheet->getCell('F' . $i)->setDataValidation(clone $productTitleValidation);
            $worksheet->getCell('G' . $i)->setDataValidation(clone $customizedValidation);
            $worksheet->getCell('H' . $i)->setDataValidation(clone $placeOriginValidation);
            $worksheet->getCell('I' . $i)->setDataValidation(clone $colorValidation);
            $worksheet->getCell('J' . $i)->setDataValidation(clone $materialValidation);
            $worksheet->getCell('K' . $i)->setDataValidation(clone $fillerValidation);
            $worksheet->getCell('L' . $i)->setDataValidation(clone $assembledLengthValidation);
            $worksheet->getCell('M' . $i)->setDataValidation(clone $assembledWidthValidation);
            $worksheet->getCell('N' . $i)->setDataValidation(clone $assembledHeightValidation);
            $worksheet->getCell('O' . $i)->setDataValidation(clone $assembledWeightValidation);
            $worksheet->getCell('P' . $i)->setDataValidation(clone $productTypeValidation);

            $worksheet->getCell('S' . $i)->setDataValidation(clone $lengthValidation);
            $worksheet->getCell('T' . $i)->setDataValidation(clone $widthValidation);
            $worksheet->getCell('U' . $i)->setDataValidation(clone $heightValidation);
            $worksheet->getCell('V' . $i)->setDataValidation(clone $weightValidation);
            $worksheet->getCell('W' . $i)->setDataValidation(clone $priceValidation);
            $worksheet->getCell('X' . $i)->setDataValidation(clone $displayPriceValidation);
            $worksheet->getCell('AC' . $i)->setDataValidation(clone $originDesignValidation);
        }

        // 处理小数几位
        $type = NumberFormat::FORMAT_NUMBER_00;
        if ($countryId == JAPAN_COUNTRY_ID) {
            $type = NumberFormat::FORMAT_NUMBER;
        }
        $worksheet->getStyle('S3:M1001')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $worksheet->getStyle('T3:N1001')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $worksheet->getStyle('U3:O1001')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $worksheet->getStyle('V3:P1001')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $worksheet->getStyle('X3:Q1001')->getNumberFormat()->setFormatCode($type);

        $worksheet->getStyle('A2:AF1001')->applyFromArray($this->applyForm);
    }

    /**
     * 生成批量修改产品的模板
     * @param int $countryId
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function generateExportProductsTemplate()
    {
        $this->fileName = __('批量修改模板.xlsx', [], 'service/product_import');
        $isUSA = customer()->isUSA();
        $colorOptionNames = app(ProductOptionRepository::class)->getOptionsById(Option::COLOR_OPTION_ID)->pluck('name')->toArray();
        $materialOptionNames = app(ProductOptionRepository::class)->getOptionsById(Option::MATERIAL_OPTION_ID)->pluck('name')->toArray();

        array_multisort(array_map('strtolower', $colorOptionNames), SORT_ASC, SORT_STRING, $colorOptionNames);
        array_multisort(array_map('strtolower', $materialOptionNames), SORT_ASC, SORT_STRING, $materialOptionNames);

        $worksheet = $this->spreadsheet->getActiveSheet()->setTitle('Products');
        $worksheet->getStyle('A1:W1')->getFont()->setBold(true);
        $worksheet->getStyle('A1:W1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC6EFCD');
        $worksheet->getColumnDimension('A')->setWidth(25);
        $worksheet->getColumnDimension('B')->setWidth(25);
        $worksheet->getColumnDimension('C')->setWidth(25);
        $worksheet->getColumnDimension('D')->setWidth(25);
        $worksheet->getColumnDimension('E')->setWidth(25);
        $worksheet->getColumnDimension('F')->setWidth(25);
        $worksheet->getColumnDimension('G')->setWidth(25);
        $worksheet->getColumnDimension('H')->setWidth(25);
        $worksheet->getColumnDimension('I')->setWidth(25);
        $worksheet->getColumnDimension('J')->setWidth(25);
        $worksheet->getColumnDimension('K')->setWidth(25);
        $worksheet->getColumnDimension('L')->setWidth(25);
        $worksheet->getColumnDimension('M')->setWidth(25);
        $worksheet->getColumnDimension('N')->setWidth(25);
        $worksheet->getColumnDimension('O')->setWidth(25);
        $worksheet->getColumnDimension('P')->setWidth(35);
        $worksheet->getColumnDimension('Q')->setWidth(35);
        $worksheet->getColumnDimension('R')->setWidth(35);
        $worksheet->getColumnDimension('S')->setWidth(35);
        $worksheet->getColumnDimension('T')->setWidth(35);
        $worksheet->getColumnDimension('U')->setWidth(35);
        $worksheet->getColumnDimension('V')->setWidth(35);
        $worksheet->getColumnDimension('W')->setWidth(35);

        $worksheet->setCellValue('A1', 'Category ID');
        $worksheet->setCellValue('A2', __('从Category ID sheet表中选择类目，输入对应的CategoryID', [], 'service/product_import'));

        $worksheet->setCellValue('B1', '*MPN');
        $worksheet->setCellValue('C1', 'UPC');
        $worksheet->setCellValue('D1', 'Sold Separately');
        $worksheet->setCellValue('D2', __('产品是否能单独售卖
    注意：输入“No”会导致产品不在前台页面显示，buyer看不到该产品', [], 'service/product_import'));

        $worksheet->setCellValue('E1', 'Not available for sale on');
        $worksheet->setCellValue('E2',  __('从Platform Names sheet表中选择不可售卖的平台，如果有多个用[|]隔开',[],'service/product_import'));

        $worksheet->setCellValue('F1', 'Product Title');
        $worksheet->setCellValue('G1', 'Customized');
        $worksheet->setCellValue('G2', __('该产品是否支持定制', [], 'service/product_import'));

        $worksheet->setCellValue('H1', 'Place of Origin');
        $worksheet->setCellValue('H2', __('原产地国家/地区', [], 'service/product_import'));

        $worksheet->setCellValue('I1', 'Main Color');
        $worksheet->setCellValue('J1', 'Main Material');

        $worksheet->setCellValue('K1', 'Filler');
        $worksheet->setCellValue('K2', __('选择填充物/内部材料', [], 'service/product_import'));

        $worksheet->setCellValue('L1', $isUSA ? 'Assembled Length(inch)' : 'Assembled Length(cm)');
        $worksheet->setCellValue('L2', __('产品尺寸
输入 0.01 到 999.99 之间的数字或输入“Not Applicable”', [], 'service/product_import'));

        $worksheet->setCellValue('M1', $isUSA ? 'Assembled Width(inch)' : 'Assembled Width(cm)');
        $worksheet->setCellValue('M2', __('产品尺寸
输入 0.01 到 999.99 之间的数字或输入“Not Applicable”', [], 'service/product_import'));

        $worksheet->setCellValue('N1', $isUSA ? 'Assembled Height(inch)' : 'Assembled Height(cm)');
        $worksheet->setCellValue('N2', __('产品尺寸
输入 0.01 到 999.99 之间的数字或输入“Not Applicable”', [], 'service/product_import'));

        $worksheet->setCellValue('O1', $isUSA ? 'Weight(lb)' : 'Weight(kg)');
        $worksheet->setCellValue('O2', __('产品尺寸
输入 0.01 到 999.99 之间的数字或输入“Not Applicable”', [], 'service/product_import'));

        $worksheet->setCellValue('P1', 'Product Description');
        $worksheet->setCellValue('P2', __('仅支持输入文字描述', [], 'service/product_import'));

        $worksheet->setCellValue('Q1', 'Main Image Path');
        $worksheet->setCellValue('Q2', __('请填入文件管理中的图片路径，限一张图片', [], 'service/product_import'));

        $worksheet->setCellValue('R1', 'Images Path(to be displayed)');
        $worksheet->setCellValue('R2', __('请填入文件管理中的图片路径，如果有多张图片用[|]隔开,最多支持26张', [], 'service/product_import'));

        $worksheet->setCellValue('S1', 'Images Path(other material)');
        $worksheet->setCellValue('S2', __('请填入文件管理中的图片路径，如果有多张图片用[|]隔开,最多支持27张', [], 'service/product_import'));

        $worksheet->setCellValue('T1', 'Original Design');
        $worksheet->setCellValue('T2', __('该产品是否获得专利或版权？', [], 'service/product_import'));

        $worksheet->setCellValue('U1', 'Supporting Files Path');
        $worksheet->setCellValue('U2', __('专利产品选择是时，专利证书必填。请填入文件管理中的图片路径，如果有多张图片用[|]隔开,最多支持9张', [], 'service/product_import'));

        $worksheet->setCellValue('V1', 'Material Document Path');
        $worksheet->setCellValue('V2', __('请填入文件管理中的图片/文档路径，如果有多个图片/文档用[|]隔开,最多支持27个文件', [], 'service/product_import'));

        $worksheet->setCellValue('W1', 'Material Video Path');
        $worksheet->setCellValue('W2', __('请填入文件管理中的txt文档路径，如果有多个文档用[|]隔开,最多支持27个文件', [], 'service/product_import'));

        $worksheet->getStyle('A2:W2')->getFont()->getColor()->setARGB('808080'); //说明栏设置字体颜色为灰色

        //Category sheet
        $this->createCategortySheet();

        $countryCount = $this->createCountrySheet();

        $colorSheet = $this->spreadsheet->createSheet(4)->setTitle('Color');
        foreach ($colorOptionNames as $k => $name) {
            $j = $k + 1;
            $colorSheet->setCellValueByColumnAndRow(1, $j, $name);
        }

        $materialSheet = $this->spreadsheet->createSheet(5)->setTitle('Material.Filler');
        foreach ($materialOptionNames as $k => $name) {
            $j = $k + 1;
            $materialSheet->setCellValueByColumnAndRow(1, $j, $name);
        }

        //platform sheet
        $this->createPlatformSheet();

        $mpnValidation = $worksheet->getCell('B3')->getDataValidation();
        $mpnValidation = $this->setextLengthValidationOption($mpnValidation)->setPrompt("MPN must be greater than 4 and less than 30 characters.")->setFormula1("=AND(LENB(B3)>=4,LENB(B3)<=30)");

        $upcValidation = $worksheet->getCell('C3')->getDataValidation();
        $upcValidation = $this->setextLengthValidationOption($upcValidation)->setPrompt("UPC must be less than 30 characters.")->setFormula1("=AND(LENB(C3)<=30)");

        $soldSeparatelyValidation = $worksheet->getCell('D3')->getDataValidation();
        $soldSeparatelyValidation = $this->setListValidationOption($soldSeparatelyValidation)->setFormula1('"Yes, No"');

        $productTitleValidation = $worksheet->getCell('F3')->getDataValidation();
        $productTitleValidation = $this->setextLengthValidationOption($productTitleValidation)->setPrompt("Product Title must be less than 200 characters.")->setFormula1("=AND(LENB(F3)>0,LENB(F3)<=200)");

        $customizedValidation = $worksheet->getCell('G3')->getDataValidation();
        $customizedValidation = $this->setListValidationOption($customizedValidation)->setFormula1('"Yes, No"');

        $placeOriginValidation = $worksheet->getCell('H3')->getDataValidation();
        $placeOriginValidation = $this->setListValidationOption($placeOriginValidation)->setFormula1('Country.Region!$A$1:$A$' . $countryCount);

        $colorValidation = $worksheet->getCell('I3')->getDataValidation();
        $colorValidation = $this->setListValidationOption($colorValidation)->setFormula1('Color!$A$1:$A$' . count($colorOptionNames));

        $materialValidation = $worksheet->getCell('J3')->getDataValidation();
        $materialValidation = $this->setListValidationOption($materialValidation)->setFormula1('Material.Filler!$A$1:$A$' . count($materialOptionNames));

        $fillerValidation = $worksheet->getCell('K3')->getDataValidation();
        $fillerValidation = $this->setListValidationOption($fillerValidation)->setFormula1('Material.Filler!$A$1:$A$' . count($materialOptionNames));

        $assembledLengthValidation = $worksheet->getCell('L3')->getDataValidation();
        $assembledLengthValidation = $this->setextLengthValidationOption($assembledLengthValidation)->setPrompt("Enter a number between 0.01 and 999.99 or 'Not Applicable'.");

        $assembledWidthValidation = $worksheet->getCell('M3')->getDataValidation()->setPrompt("Enter a number between 0.01 and 999.99 or 'Not Applicable'.");
        $assembledWidthValidation = $this->setextLengthValidationOption($assembledWidthValidation)->setPrompt("Enter a number between 0.01 and 999.99 or 'Not Applicable'.");

        $assembledHeightValidation = $worksheet->getCell('N3')->getDataValidation()->setPrompt("Enter a number between 0.01 and 999.99 or 'Not Applicable'.");
        $assembledHeightValidation = $this->setextLengthValidationOption($assembledHeightValidation)->setPrompt("Enter a number between 0.01 and 999.99 or 'Not Applicable'.");

        $assembledWeightValidation = $worksheet->getCell('O3')->getDataValidation()->setPrompt("Enter a number between 0.01 and 999.99 or 'Not Applicable'.");
        $assembledWeightValidation = $this->setextLengthValidationOption($assembledWeightValidation)->setPrompt("Enter a number between 0.01 and 999.99 or 'Not Applicable'.");


        $originDesignValidation = $worksheet->getCell('T3')->getDataValidation();
        $originDesignValidation = $this->setListValidationOption($originDesignValidation)->setFormula1('"Yes, No"');

        for ($i = 3; $i <= 1001; $i++) {
            $worksheet->getCell('B' . $i)->setDataValidation(clone $mpnValidation);
            $worksheet->getCell('C' . $i)->setDataValidation(clone $upcValidation);
            $worksheet->getCell('D' . $i)->setDataValidation(clone $soldSeparatelyValidation);
            $worksheet->getCell('F' . $i)->setDataValidation(clone $productTitleValidation);
            $worksheet->getCell('G' . $i)->setDataValidation(clone $customizedValidation);
            $worksheet->getCell('H' . $i)->setDataValidation(clone $placeOriginValidation);
            $worksheet->getCell('I' . $i)->setDataValidation(clone $colorValidation);
            $worksheet->getCell('J' . $i)->setDataValidation(clone $materialValidation);
            $worksheet->getCell('K' . $i)->setDataValidation(clone $fillerValidation);
            $worksheet->getCell('L' . $i)->setDataValidation(clone $assembledLengthValidation);
            $worksheet->getCell('M' . $i)->setDataValidation(clone $assembledWidthValidation);
            $worksheet->getCell('N' . $i)->setDataValidation(clone $assembledHeightValidation);
            $worksheet->getCell('O' . $i)->setDataValidation(clone $assembledWeightValidation);
            $worksheet->getCell('T' . $i)->setDataValidation(clone $originDesignValidation);
        }

        $worksheet->getStyle('A2:W1001')->applyFromArray($this->applyForm);
    }

    /**
     * 生成批量导入产品错误报告
     * @param int $batchId
     * @param int $countryId
     * @param int $sellerId
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function generateImportProductsErrorReportByBatchId(array $batchDetail, int $countryId, int $sellerId)
    {
        if ($batchDetail['type'] == 1) {
            $this->generateBatchImportErrorSheet(...func_get_args());
        } else {
            $this->generateBatchModifyErrorSheet(...func_get_args());
        }
    }

    /**
     * 生成批量导入商品的错误报告
     * @param array $batchDetail
     * @param int $countryId
     * @param int $sellerId
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function generateBatchImportErrorSheet(array $batchDetail, int $countryId, int $sellerId)
    {
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $date = dateFormat($fromZone, $toZone, date("YmdHis", time()), 'YmdHis');
        $this->fileName = "product_import_error_report_{$batchDetail['id']}_{$date}.xlsx";
        $isUSA = $countryId == AMERICAN_COUNTRY_ID;

        $worksheet = $this->spreadsheet->getActiveSheet()->setTitle('error_reports');
        $worksheet->getStyle('A1:AH1')->getFont()->setBold(true);
        $worksheet->getStyle('A1:AH1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC6EFCD');
        $worksheet->getColumnDimension('A')->setWidth(10);
        $worksheet->getColumnDimension('B')->setWidth(25);
        $worksheet->getColumnDimension('C')->setWidth(25);
        $worksheet->getColumnDimension('D')->setWidth(25);
        $worksheet->getColumnDimension('E')->setWidth(25);
        $worksheet->getColumnDimension('F')->setWidth(25);
        $worksheet->getColumnDimension('G')->setWidth(25);
        $worksheet->getColumnDimension('H')->setWidth(25);
        $worksheet->getColumnDimension('I')->setWidth(25);
        $worksheet->getColumnDimension('J')->setWidth(25);
        $worksheet->getColumnDimension('K')->setWidth(25);
        $worksheet->getColumnDimension('L')->setWidth(25);
        $worksheet->getColumnDimension('M')->setWidth(25);
        $worksheet->getColumnDimension('N')->setWidth(25);
        $worksheet->getColumnDimension('O')->setWidth(25);
        $worksheet->getColumnDimension('P')->setWidth(25);
        $worksheet->getColumnDimension('Q')->setWidth(35);
        $worksheet->getColumnDimension('R')->setWidth(35);
        $worksheet->getColumnDimension('S')->setWidth(35);
        $worksheet->getColumnDimension('T')->setWidth(35);
        $worksheet->getColumnDimension('U')->setWidth(35);
        $worksheet->getColumnDimension('V')->setWidth(35);
        $worksheet->getColumnDimension('W')->setWidth(35);
        $worksheet->getColumnDimension('X')->setWidth(35);
        $worksheet->getColumnDimension('Y')->setWidth(35);
        $worksheet->getColumnDimension('Z')->setWidth(35);
        $worksheet->getColumnDimension('AA')->setWidth(35);
        $worksheet->getColumnDimension('AB')->setWidth(35);
        $worksheet->getColumnDimension('AC')->setWidth(35);
        $worksheet->getColumnDimension('AD')->setWidth(35);
        $worksheet->getColumnDimension('AE')->setWidth(35);
        $worksheet->getColumnDimension('AF')->setWidth(35);
        $worksheet->getColumnDimension('AG')->setWidth(35);
        $worksheet->getColumnDimension('AH')->setWidth(60);

        $worksheet->setCellValue('A1', 'Line');
        $worksheet->setCellValue('B1', '*Category ID');
        $worksheet->setCellValue('C1', '*MPN');
        $worksheet->setCellValue('D1', 'UPC');
        $worksheet->setCellValue('E1', '*Sold Separately');
        $worksheet->setCellValue('F1', 'Not available for sale on');
        $worksheet->setCellValue('G1', '*Product Title');
        $worksheet->setCellValue('H1', '*Customized');
        $worksheet->setCellValue('I1', 'Place of Origin');
        $worksheet->setCellValue('J1', '*Main Color');
        $worksheet->setCellValue('K1', '*Main Material');
        $worksheet->setCellValue('L1', 'Filler');
        $worksheet->setCellValue('M1', $isUSA ? '*Assembled Length(inch)' : '*Assembled Length(cm)');
        $worksheet->setCellValue('N1', $isUSA ? '*Assembled Width(inch)' : '*Assembled Width(cm)');
        $worksheet->setCellValue('O1', $isUSA ? '*Assembled Height(inch)' : '*Assembled Height(cm)');
        $worksheet->setCellValue('P1', $isUSA ? '*Weight(lb)' : '*Weight(kg)');
        $worksheet->setCellValue('Q1', '*Product Type');
        $worksheet->setCellValue('R1', 'Sub-items(MPN)');
        $worksheet->setCellValue('S1', 'Sub-items Quantity');
        $worksheet->setCellValue('T1', $isUSA ? '*Length(inch)' : '*Length(cm)');
        $worksheet->setCellValue('U1', $isUSA ? '*Width(inch)' : '*Width(cm)');
        $worksheet->setCellValue('V1', $isUSA ? '*Height(inch)' : '*Height(cm)');
        $worksheet->setCellValue('W1', $isUSA ? '*Weight(lb)' : '*Weight(kg)');
        $worksheet->setCellValue('X1', '*Current Price');
        $worksheet->setCellValue('Y1', '*Display Price(Invisible/Visible)');
        $worksheet->setCellValue('Z1', 'Product Description');
        $worksheet->setCellValue('AA1', 'Main Image Path');
        $worksheet->setCellValue('AB1', 'Images Path(to be displayed)');
        $worksheet->setCellValue('AC1', 'Images Path(other material)');
        $worksheet->setCellValue('AD1', '*Original Design');
        $worksheet->setCellValue('AE1', 'Supporting Files Path');
        $worksheet->setCellValue('AF1', 'Material Document Path');
        $worksheet->setCellValue('AG1', 'Material Video Path');
        $worksheet->setCellValue('AH1', 'Error');
        $worksheet->getStyle('AH1')->getFont()->getColor()->setARGB(Color::COLOR_RED);

        $reports = app(ProductImportRepository::class)->getProductImportErrorReportsByBatchId($batchDetail['id'], $sellerId);

        $columnElements = 2;
        foreach ($reports as $report) {
            /** @var ProductImportBatchErrorReport $report */
            $worksheet->getRowDimension($columnElements)->setRowHeight(-1);
            $extendsInfo = json_decode($report->extends_info, true);
            $worksheet->setCellValue('A' . $columnElements, $columnElements - 1)
                ->setCellValue('B' . $columnElements, $report->category_id > 0 ? $report->category_id : '')
                ->setCellValue('C' . $columnElements, $report->mpn)
                ->setCellValue('D' . $columnElements, $extendsInfo['upc'] ?? '')
                ->setCellValue('E' . $columnElements, $report->sold_separately)
                ->setCellValue('F' . $columnElements, implode('|', explode(',', $report->not_sale_platform)))
                ->setCellValue('G' . $columnElements, $report->product_title)
                ->setCellValue('H' . $columnElements, $extendsInfo['customized'] ?? '')
                ->setCellValue('I' . $columnElements, $extendsInfo['place_or_origin'] ?? '')
                ->setCellValue('J' . $columnElements, $report->color)
                ->setCellValue('K' . $columnElements, $report->material)
                ->setCellValue('L' . $columnElements, $extendsInfo['filler'] ?? '')
                ->setCellValue('M' . $columnElements, $extendsInfo['assembled_length'] ?? '')
                ->setCellValue('N' . $columnElements, $extendsInfo['assembled_width'] ?? '')
                ->setCellValue('O' . $columnElements, $extendsInfo['assembled_height'] ?? '')
                ->setCellValue('P' . $columnElements, $extendsInfo['assembled_weight'] ?? '')
                ->setCellValue('Q' . $columnElements, $report->product_type)
                ->setCellValue('R' . $columnElements, $report->sub_items)
                ->setCellValue('S' . $columnElements, $report->sub_items_quantity)
                ->setCellValue('T' . $columnElements, $report->length)
                ->setCellValue('U' . $columnElements, $report->width)
                ->setCellValue('V' . $columnElements, $report->height)
                ->setCellValue('W' . $columnElements, $report->weight)
                ->setCellValue('X' . $columnElements, $report->current_price)
                ->setCellValue('Y' . $columnElements, $report->display_price)
                ->setCellValue('Z' . $columnElements, $report->description)
                ->setCellValue('AA' . $columnElements, $extendsInfo['main_image_path'] ?? '')
                ->setCellValue('AB' . $columnElements, $extendsInfo['images_path_to_be_display'] ?? '')
                ->setCellValue('AC' . $columnElements, $extendsInfo['images_path_other_material'] ?? '')
                ->setCellValue('AD' . $columnElements, $report->origin_design)
                ->setCellValue('AE' . $columnElements, $extendsInfo['supporting_files_path'] ?? '')
                ->setCellValue('AF' . $columnElements, $extendsInfo['material_manual_path'] ?? '')
                ->setCellValue('AG' . $columnElements, $extendsInfo['material_video_path'] ?? '')
                ->setCellValue('AH' . $columnElements, $report->error_content);
            $columnElements++;
        }

        $worksheet->getStyle('A1:AH' . ($columnElements - 1))->applyFromArray($this->applyForm);
    }

    /**
     * 生成批量修改商品的错误报告
     * @param array $batchDetail
     * @param int $countryId
     * @param int $sellerId
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function generateBatchModifyErrorSheet(array $batchDetail, int $countryId, int $sellerId)
    {
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $isUSA = $countryId == AMERICAN_COUNTRY_ID;
        $date = dateFormat($fromZone, $toZone, date("YmdHis", time()), 'YmdHis');
        $this->fileName = "product_modify_error_report_{$batchDetail['id']}_{$date}.xlsx";

        $worksheet = $this->spreadsheet->getActiveSheet()->setTitle('error_reports');
        $worksheet->getStyle('A1:Y1')->getFont()->setBold(true);
        $worksheet->getStyle('A1:Y1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC6EFCD');
        $worksheet->getColumnDimension('A')->setWidth(10);
        $worksheet->getColumnDimension('B')->setWidth(25);
        $worksheet->getColumnDimension('C')->setWidth(25);
        $worksheet->getColumnDimension('D')->setWidth(25);
        $worksheet->getColumnDimension('E')->setWidth(25);
        $worksheet->getColumnDimension('F')->setWidth(25);
        $worksheet->getColumnDimension('G')->setWidth(25);
        $worksheet->getColumnDimension('H')->setWidth(25);
        $worksheet->getColumnDimension('I')->setWidth(25);
        $worksheet->getColumnDimension('J')->setWidth(25);
        $worksheet->getColumnDimension('K')->setWidth(25);
        $worksheet->getColumnDimension('L')->setWidth(25);
        $worksheet->getColumnDimension('M')->setWidth(25);
        $worksheet->getColumnDimension('N')->setWidth(25);
        $worksheet->getColumnDimension('O')->setWidth(25);
        $worksheet->getColumnDimension('P')->setWidth(25);
        $worksheet->getColumnDimension('Q')->setWidth(35);
        $worksheet->getColumnDimension('R')->setWidth(35);
        $worksheet->getColumnDimension('S')->setWidth(35);
        $worksheet->getColumnDimension('T')->setWidth(35);
        $worksheet->getColumnDimension('U')->setWidth(35);
        $worksheet->getColumnDimension('V')->setWidth(35);
        $worksheet->getColumnDimension('W')->setWidth(35);
        $worksheet->getColumnDimension('X')->setWidth(35);
        $worksheet->getColumnDimension('Y')->setWidth(60);

        $worksheet->setCellValue('A1', 'Line');
        $worksheet->setCellValue('B1', 'Category ID');
        $worksheet->setCellValue('C1', '*MPN');
        $worksheet->setCellValue('D1', 'UPC');
        $worksheet->setCellValue('E1', 'Sold Separately');
        $worksheet->setCellValue('F1', 'Not available for sale on');
        $worksheet->setCellValue('G1', 'Product Title');
        $worksheet->setCellValue('H1', 'Customized');
        $worksheet->setCellValue('I1', 'Place of Origin');
        $worksheet->setCellValue('J1', 'Main Color');
        $worksheet->setCellValue('K1', 'Main Material');
        $worksheet->setCellValue('L1', 'Filler');
        $worksheet->setCellValue('M1', $isUSA ? 'Assembled Length(inch)' : 'Assembled Length(cm)');
        $worksheet->setCellValue('N1', $isUSA ? 'Assembled Width(inch)' : 'Assembled Width(cm)');
        $worksheet->setCellValue('O1', $isUSA ? 'Assembled Height(inch)' : 'Assembled Height(cm)');
        $worksheet->setCellValue('P1', $isUSA ? 'Weight(lb)' : 'Weight(kg)');
        $worksheet->setCellValue('Q1', 'Product Description');
        $worksheet->setCellValue('R1', 'Main Image Path');
        $worksheet->setCellValue('S1', 'Images Path(to be displayed)');
        $worksheet->setCellValue('T1', 'Images Path(other material)');
        $worksheet->setCellValue('U1', 'Original Design');
        $worksheet->setCellValue('V1', 'Supporting Files Path');
        $worksheet->setCellValue('W1', 'Material Document Path');
        $worksheet->setCellValue('X1', 'Material Video Path');
        $worksheet->setCellValue('Y1', 'Error');
        $worksheet->getStyle('Y1')->getFont()->getColor()->setARGB(Color::COLOR_RED);

        $reports = app(ProductImportRepository::class)->getProductImportErrorReportsByBatchId($batchDetail['id'], $sellerId);

        $columnElements = 2;
        foreach ($reports as $report) {
            /** @var ProductImportBatchErrorReport $report */
            $worksheet->getRowDimension($columnElements)->setRowHeight(-1);
            $extendsInfo = json_decode($report['extends_info'], true);
            $worksheet
                ->setCellValue('A' . $columnElements, $columnElements - 1)
                ->setCellValue('B' . $columnElements, $report->category_id > 0 ? $report->category_id : '')
                ->setCellValue('C' . $columnElements, $report->mpn)
                ->setCellValue('D' . $columnElements, $extendsInfo['upc'] ?? '')
                ->setCellValue('E' . $columnElements, $report->sold_separately)
                ->setCellValue('F' . $columnElements, implode('|', explode(',', $report->not_sale_platform)))
                ->setCellValue('G' . $columnElements, $report->product_title)
                ->setCellValue('H' . $columnElements, $extendsInfo['customized'] ?? '')
                ->setCellValue('I' . $columnElements, $extendsInfo['place_or_origin'] ?? '')
                ->setCellValue('J' . $columnElements, $report->color)
                ->setCellValue('K' . $columnElements, $report->material)
                ->setCellValue('L' . $columnElements, $extendsInfo['filler'] ?? '')
                ->setCellValue('M' . $columnElements, $extendsInfo['assembled_length'] ?? '')
                ->setCellValue('N' . $columnElements, $extendsInfo['assembled_width'] ?? '')
                ->setCellValue('O' . $columnElements, $extendsInfo['assembled_height'] ?? '')
                ->setCellValue('P' . $columnElements, $extendsInfo['assembled_weight'] ?? '')
                ->setCellValue('Q' . $columnElements, $report->description)
                ->setCellValue('R' . $columnElements, $extendsInfo['main_image_path'] ?? '')
                ->setCellValue('S' . $columnElements, $extendsInfo['images_path_to_be_display'] ?? '')
                ->setCellValue('T' . $columnElements, $extendsInfo['images_path_other_material'] ?? '')
                ->setCellValue('U' . $columnElements, $report->origin_design)
                ->setCellValue('V' . $columnElements, $extendsInfo['supporting_files_path'] ?? '')
                ->setCellValue('W' . $columnElements, $extendsInfo['material_manual_path'] ?? '')
                ->setCellValue('X' . $columnElements, $extendsInfo['material_video_path'] ?? '')
                ->setCellValue('Y' . $columnElements, $report->error_content);
            $columnElements++;
        }
        $styleArray = [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];

        $worksheet->getStyle('A1:Y' . ($columnElements - 1))->applyFromArray($styleArray);
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    private function write()
    {
        $writer = new Xlsx($this->spreadsheet);
        $this->getBrowserCompatible();
        $savePath = 'php://output';
        $writer->save($savePath);
    }

    /**
     * 浏览器兼容
     */
    private function getBrowserCompatible()
    {
        $ua = request()->serverBag->get("HTTP_USER_AGENT");
        $encodedFilename = str_replace("+", "%20", urlencode($this->fileName));
        header('Content-Type: application/octet-stream');
        if (preg_match("/MSIE/", $ua)) {
            header('Content-Disposition: attachment; filename="' . $encodedFilename . '"');
        } else if (preg_match("/Firefox/", $ua)) {
            header('Content-Disposition: attachment; filename*="utf8\'\'' . $this->fileName . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $this->fileName . '"');
        }
        header('Cache-Control: max-age=0');
    }


    /**
     * Seller的Product List下载
     * @param $results
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function downloadValid($results)
    {
        $currency = session('currency', 'USD');
        $country = session('country', 'USA');
        $decimal_place = $this->currency->getDecimalPlace($currency);
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis", time()), 'YmdHis');
        $this->fileName = 'ProductLists' . $time . '-Valid.xlsx';
        $worksheet = $this->spreadsheet->getActiveSheet();
        $head = [
            __('Item code', [], 'catalog/view/customerpartner/product/lists_index'),
            __('MPN', [], 'catalog/view/customerpartner/product/lists_index'),
            __('产品标题', [], 'catalog/view/customerpartner/product/lists_index'),
            __('Combo MPN', [], 'catalog/view/customerpartner/product/lists_index'),
            __('原创产品', [], 'catalog/view/customerpartner/product/lists_index'),
            __('单独售卖', [], 'catalog/view/customerpartner/product/lists_index'),
            __('当前价格', [], 'catalog/view/customerpartner/product/lists_index'),
            __('更新价格', [], 'catalog/view/customerpartner/product/lists_index'),
            __('生效日期', [], 'catalog/view/customerpartner/product/lists_index'),
            __('价格是否可见', [], 'catalog/view/customerpartner/product/lists_index'),
            __('产品状态', [], 'catalog/view/customerpartner/product/lists_index'),
            __('审核标识', [], 'catalog/view/customerpartner/product/lists_index'),
            __('运输费', [], 'catalog/view/customerpartner/product/lists_index'),
            __('一件代发打包费', [], 'catalog/view/customerpartner/product/lists_index'),
            __('上门取货打包费', [], 'catalog/view/customerpartner/product/lists_index'),
            __('产品分组', [], 'catalog/view/customerpartner/product/lists_index'),
        ];
        $row = 1;
        $column = 1;
        foreach ($head as $value) {
            $worksheet->setCellValueByColumnAndRow($column, $row, $value);
            $column = $column + 1;
        }

        $row = 2;
        $column = 1;
        foreach ($results as $key => $result) {
            $result['show_combo_flag'] = ComboFlag::getDescription($result['combo_flag']);
            $result['show_buyer_flag'] = BuyFlag::getDescription($result['buyer_flag']);
            $result['show_price_display'] = PriceDisplay::getDescription($result['price_display']);
            $result['show_status'] = ProductStatus::getDescription($result['status']);
            $result['show_package_fee_d'] = isset($result['package_fee_d']) ? $this->currency->formatCurrencyPrice($result['package_fee_d'], $currency) : 'N/A';
            $result['show_package_fee_h'] = isset($result['package_fee_h']) ? $this->currency->formatCurrencyPrice($result['package_fee_h'], $currency) : 'N/A';
            $result['show_shipping_fee'] = $this->currency->format(round((float)$result['freight'], $decimal_place), $currency);
            $result['show_price'] = $this->currency->format($result['price'], $currency);
            $result['show_new_price'] = isset($result['new_price']) ? $this->currency->format($result['new_price'], $currency) : '';
            if ($result['audit_price']) {
                $result['show_new_price'] = $this->currency->format($result['audit_price'], $currency);
                $result['new_price'] = $result['audit_price'];
                $result['effect_time'] = $result['audit_price_effect_time'];
            }

            $line = [
                $result['sku'],
                $result['mpn'],
                SummernoteHtmlEncodeHelper::decode($result['name'], true),
                $result['show_combo_flag'],
                $result['is_original_design'] == YesNoEnum::YES ? YesNoEnum::getDescription(YesNoEnum::YES):YesNoEnum::getDescription(YesNoEnum::NO),
                $result['show_buyer_flag'],
                $result['show_price'],
                $result['show_new_price'],
                trim($result['effect_time']) ? "\t" . trim(currentZoneDate(session(), trim($result['effect_time']))) : '',
                $result['show_price_display'],
                $result['show_status'],
                $result['audit_progress'],
                $result['show_shipping_fee'],
                $result['show_package_fee_d'],
                $result['show_package_fee_h'],
                SummernoteHtmlEncodeHelper::decode($result['product_group_name'], true),
            ];

            $column = 1;
            foreach ($line as $value) {
                $worksheet->setCellValueByColumnAndRow($column, $row, $value);
                $column = $column + 1;
            }
            $row = $row + 1;
        }
    }


    /**
     * Seller的Product List下载
     * @param $results
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function downloadInvalid($results)
    {
        $currency = session('currency', 'USD');
        $country = session('country', 'USA');
        $decimal_place = $this->currency->getDecimalPlace($currency);
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis", time()), 'YmdHis');
        $this->fileName = 'ProductLists' . $time . '-Invalid.xlsx';
        $worksheet = $this->spreadsheet->getActiveSheet();
        $head = [
            __('Item code', [], 'catalog/view/customerpartner/product/lists_index'),
            __('MPN', [], 'catalog/view/customerpartner/product/lists_index'),
            __('产品标题', [], 'catalog/view/customerpartner/product/lists_index'),
            __('产品分组', [], 'catalog/view/customerpartner/product/lists_index'),
            __('Combo MPN', [], 'catalog/view/customerpartner/product/lists_index'),
            __('原创产品', [], 'catalog/view/customerpartner/product/lists_index'),
            __('单独售卖', [], 'catalog/view/customerpartner/product/lists_index'),
            __('当前价格', [], 'catalog/view/customerpartner/product/lists_index'),
            __('产品状态', [], 'catalog/view/customerpartner/product/lists_index'),
            __('运输费', [], 'catalog/view/customerpartner/product/lists_index'),
            __('一件代发打包费', [], 'catalog/view/customerpartner/product/lists_index'),
            __('上门取货打包费', [], 'catalog/view/customerpartner/product/lists_index'),
        ];
        $row = 1;
        $column = 1;
        foreach ($head as $value) {
            $worksheet->setCellValueByColumnAndRow($column, $row, $value);
            $column = $column + 1;
        }

        $row = 2;
        $column = 1;
        foreach ($results as $key => $result) {
            $result['show_combo_flag'] = ComboFlag::getDescription($result['combo_flag']);
            $result['show_buyer_flag'] = BuyFlag::getDescription($result['buyer_flag']);
            $result['show_status'] = ProductStatus::getDescription($result['status']);
            $result['show_package_fee_d'] = isset($result['package_fee_d']) ? $this->currency->formatCurrencyPrice($result['package_fee_d'], $currency) : 'N/A';
            $result['show_package_fee_h'] = isset($result['package_fee_h']) ? $this->currency->formatCurrencyPrice($result['package_fee_h'], $currency) : 'N/A';
            $result['show_shipping_fee'] = $this->currency->format(round((float)$result['freight'], $decimal_place), $currency);
            $result['show_price'] = $this->currency->format($result['price'], $currency);
            $result['show_new_price'] = isset($result['new_price']) ? $this->currency->format($result['new_price'], $currency) : '';

            $line = [
                $result['sku'],
                $result['mpn'],
                SummernoteHtmlEncodeHelper::decode($result['name'], true),
                SummernoteHtmlEncodeHelper::decode($result['product_group_name'], true),
                $result['show_combo_flag'],
                $result['is_original_design'] == YesNoEnum::YES ? YesNoEnum::getDescription(YesNoEnum::YES):YesNoEnum::getDescription(YesNoEnum::NO),
                $result['show_buyer_flag'],
                $result['show_price'],
                $result['show_status'],
                $result['show_shipping_fee'],
                $result['show_package_fee_d'],
                $result['show_package_fee_h'],
            ];

            $column = 1;
            foreach ($line as $value) {
                $worksheet->setCellValueByColumnAndRow($column, $row, $value);
                $column = $column + 1;
            }
            $row = $row + 1;
        }
    }


    /**
     * Seller的Product List下载
     * @param $results
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function downloadAudit($results)
    {
        $nowDate = Carbon::now()->toDateTimeString();
        $currency = session('currency', 'USD');
        $country = session('country', 'USA');
        $decimal_place = $this->currency->getDecimalPlace($currency);
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis", time()), 'YmdHis');
        $this->fileName = 'ProductLists' . $time . '-ApprovedMemo.xlsx';
        $worksheet = $this->spreadsheet->getActiveSheet();
        $head = [
            __('Item code', [], 'catalog/view/customerpartner/product/lists_index'),
            __('MPN', [], 'catalog/view/customerpartner/product/lists_index'),
            __('产品标题', [], 'catalog/view/customerpartner/product/lists_index'),
            __('Combo MPN', [], 'catalog/view/customerpartner/product/lists_index'),
            __('原创产品', [], 'catalog/view/customerpartner/product/lists_index'),
            __('单独售卖', [], 'catalog/view/customerpartner/product/lists_index'),
            __('当前价格', [], 'catalog/view/customerpartner/product/lists_index'),
            __('更新价格', [], 'catalog/view/customerpartner/product/lists_index'),
            __('生效日期', [], 'catalog/view/customerpartner/product/lists_index'),
            __('审核状态', [], 'catalog/view/customerpartner/product/lists_index'),
            __('产品状态', [], 'catalog/view/customerpartner/product/lists_index'),
            __('审核类型', [], 'catalog/view/customerpartner/product/lists_index'),
            __('创建时间', [], 'catalog/view/customerpartner/product/lists_index'),
            __('审核时间', [], 'catalog/view/customerpartner/product/lists_index'),
        ];
        $row = 1;
        $column = 1;
        foreach ($head as $value) {
            $worksheet->setCellValueByColumnAndRow($column, $row, $value);
            $column = $column + 1;
        }

        $row = 2;
        $column = 1;
        foreach ($results as $key => $result) {
            $show_audit_price = '';
            $price_effect_time = '';//生效之前显示Seller填写的内容，生效之后清空
            if ($result['audit_type'] == ProductAuditType::PRODUCT_PRICE) {
                if (
                    $result['sp_status'] == 2 &&
                    $result['audit_price'] == $result['sp_new_price'] &&
                    $nowDate >= $result['price_effect_time']
                ) {
                    $show_audit_price = '';
                    $price_effect_time = '';
                } else {
                    $show_audit_price = $this->currency->format($result['audit_price'], $currency);
                    $price_effect_time = $result['price_effect_time'];
                }
            }

            $result['show_combo_flag'] = ComboFlag::getDescription($result['combo_flag']);
            $result['show_buyer_flag'] = BuyFlag::getDescription($result['buyer_flag']);
            $result['show_product_status'] = ProductStatus::getDescription($result['product_status']);
            $result['show_audit_status'] = ProductAuditStatus::getDescription($result['audit_status']);
            $result['show_current_price'] = $this->currency->format($result['current_price'], $currency);
            $result['show_audit_price'] = $show_audit_price;
            $result['price_effect_time'] = $price_effect_time;
            $result['show_audit_type'] = ProductAuditType::getDescription($result['audit_type']);
            $result['approved_time'] = $result['approved_time'] ?: '';


            $line = [
                $result['sku'],
                $result['mpn'],
                SummernoteHtmlEncodeHelper::decode($result['name'], true),
                $result['show_combo_flag'],
                $result['is_original_design'] == YesNoEnum::YES ? YesNoEnum::getDescription(YesNoEnum::YES):YesNoEnum::getDescription(YesNoEnum::NO),
                $result['show_buyer_flag'],
                $result['show_current_price'],
                $result['show_audit_price'],
                trim($result['price_effect_time']) ? "\t" . currentZoneDate(session(), trim($result['price_effect_time'])) : '',
                $result['show_audit_status'],
                $result['show_product_status'],
                trim(ProductAuditType::getDescription($result['audit_type'])),
                trim($result['create_time']) ? "\t" . currentZoneDate(session(), trim($result['create_time'])) : '',
                trim($result['approved_time']) ? "\t" . currentZoneDate(session(), trim($result['approved_time'])) : '',
            ];

            $column = 1;
            foreach ($line as $value) {
                $worksheet->setCellValueByColumnAndRow($column, $row, $value);
                $column = $column + 1;
            }
            $row = $row + 1;
        }
    }

    /**
     * 创建Categorty ID sheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function createCategortySheet()
    {
        $categoryList = app(CategoryRepository::class)->getAllCategory();
        if ($categoryList) {
            $categorySheet = $this->spreadsheet->createSheet(2)->setTitle('Category ID');

            $categorySheet->getStyle('A1:D1')->getFont()->setBold(true);

            $categorySheet->getColumnDimension('A')->setWidth(25);
            $categorySheet->getColumnDimension('B')->setWidth(25);
            $categorySheet->getColumnDimension('C')->setWidth(25);
            $categorySheet->getColumnDimension('D')->setWidth(25);

            $categorySheet->setCellValue('A1', 'Primary Category');
            $categorySheet->setCellValue('B1', 'Secondary Category');
            $categorySheet->setCellValue('C1', 'Tertiary Category');
            $categorySheet->setCellValue('D1', 'Category ID');

            $idxC = 2;
            $mergeCount = 0;
            $startMerge = 2;
            $startMergeB = 2;
            foreach ($categoryList as $firstCate) {
                if ($firstCate['child']) {
                    foreach ($firstCate['child'] as $secondCate) {
                        if ($secondCate['child']) {
                            foreach ($secondCate['child'] as $thirdCate) {
                                $categorySheet->setCellValueExplicit('A' . $idxC, $firstCate['name'], DataType::TYPE_STRING2);
                                $categorySheet->setCellValueExplicit('B' . $idxC, $secondCate['name'], DataType::TYPE_STRING2);
                                $categorySheet->setCellValueExplicit('C' . $idxC, $thirdCate['name'], DataType::TYPE_STRING2);
                                $categorySheet->setCellValueExplicit('D' . $idxC, $thirdCate['category_id'], DataType::TYPE_NUMERIC);
                                $idxC++;
                                $mergeCount++;
                            }
                        } else {
                            $categorySheet->setCellValueExplicit('A' . $idxC, $firstCate['name'], DataType::TYPE_STRING2);
                            $categorySheet->setCellValueExplicit('B' . $idxC, $secondCate['name'], DataType::TYPE_STRING2);
                            if ($secondCate['can_show_category'] == 1) {
                                $categorySheet->setCellValueExplicit('D' . $idxC, $secondCate['category_id'], DataType::TYPE_NUMERIC);
                            }
                            $idxC++;
                        }
                        $endMergeB = $startMergeB + $secondCate['second_merge_count'] - 1;
                        $categorySheet->mergeCells("B{$startMergeB}:B{$endMergeB}");
                        $startMergeB = $endMergeB + 1;
                    }
                } else {
                    $categorySheet->setCellValueExplicit('A' . $idxC, $firstCate['name'], DataType::TYPE_STRING2);
                    if ($firstCate['can_show_category'] == 1) {
                        $categorySheet->setCellValueExplicit('D' . $idxC, $firstCate['category_id'], DataType::TYPE_NUMERIC);
                    }
                    $idxC++;
                }
                $endMerge = $startMerge + $firstCate['first_merge_count'] - 1;
                $categorySheet->mergeCells("A{$startMerge}:A{$endMerge}");
                $startMerge = $startMergeB = $endMerge + 1;
            }

            $categorySheet->getStyle('A2:D' . ($endMerge + 2))->applyFromArray($this->applyForm);
        }
    }

    /**
     * 创建Platform sheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function createPlatformSheet()
    {
        $platformList = app(ProductRepository::class)->getForbiddenSellPlatform();
        if ($platformList) {
            $platformSheet = $this->spreadsheet->createSheet(6)->setTitle('Platform Names');
            $platformSheet->getColumnDimension('A')->setWidth(15);
            foreach ($platformList as $k => $name) {
                $j = $k + 1;
                $platformSheet->setCellValueByColumnAndRow(1, $j, $name);
            }
        }
    }

    /**
     * @return int
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function createCountrySheet(): int
    {
        $countries = Country::queryRead()->select(['name', 'iso_code_3 as code'])->orderBy('name')->get();
        if ($countries) {
            $platformSheet = $this->spreadsheet->createSheet(3)->setTitle('Country.Region');
            $platformSheet->getColumnDimension('A')->setWidth(30);
            foreach ($countries as $k => $country) {
                $j = $k + 1;
                $platformSheet->setCellValueByColumnAndRow(1, $j, $country->name);
            }
        }

        return $countries->count();
    }

    private function setListValidationOption(DataValidation $validation)
    {
        return $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowInputMessage(false)
            ->setShowErrorMessage(true)
            ->setShowDropDown(true)
            ->setErrorTitle('Error Message')
            ->setError('What your entered does not meet the restrictions!')
            ->setPromptTitle('Pick from list')
            ->setPrompt('Please pick a value from the drop-down list.');
    }

    private function setDecimalValidationOption(DataValidation $validation, string $type = DataValidation::TYPE_DECIMAL, float $from = 0.01, float $to = 999.99)
    {
        return $validation->setType($type)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowInputMessage(true)
            ->setShowErrorMessage(true)
            ->setShowDropDown(true)
            ->setErrorTitle('Error Message')
            ->setError('What your entered does not meet the restrictions!')
            ->setPromptTitle('Input Message')
            ->setPrompt("Only enter a number between {$from} and {$to}.")
            ->setFormula1($from)
            ->setFormula2($to);
    }

    private function setextLengthValidationOption(DataValidation $validation)
    {
        return $validation->setType(DataValidation::TYPE_CUSTOM)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowInputMessage(true)
            ->setShowErrorMessage(true)
            ->setShowDropDown(true)
            ->setErrorTitle('Error Message')
            ->setError('What your entered does not meet the restrictions!')
            ->setPromptTitle('Input Message');
    }
}
