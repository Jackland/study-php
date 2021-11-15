<?php

namespace App\Catalog\Forms\Product\Import;

use App\Catalog\Forms\Product\Import\Exception\ValidateTerminationException;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Product\ProductType;
use App\Helper\StringHelper;
use App\Models\Customer\Country;
use App\Models\Product\Product;
use App\Repositories\Product\ProductRepository;

class InsertValidate extends AbstractValidate
{
    /**
     * 非combo的MPN重复的数据
     * @var array
     */
    private $repeatNoComboMpns = [];

    /**
     * 非combo的MPN数据
     * @var array
     */
    private $noComboMpns = [];

    /**
     * combo的MPN和子产品的map
     * @var array
     */
    private $mpnComboMap = [];

    /**
     * 获取系统dim值和最大限制值，是否单独询价
     * @var array
     */
    private $dimLimitWeightAndSeparateEnquiry = [];

    /**
     * combo的格式错误的mpns
     * @var array
     */
    private $comboFormatErrorMpns = [];

    /**
     * 初始化数据组装
     */
    protected function init()
    {
        $importNoComboMpns = []; // 所有的非combo的mpn
        $mpnComboMap = []; // combo的MPN和子产品的map
        $formatProducts = [];
        foreach ($this->products as $product) {
            $product = array_map('strtolower', $product);
            $mpn = $product['MPN'];
            if ($product['Product Type'] != 'combo item') {
                $importNoComboMpns[] = $mpn;
            } else {
                $mpnComboMap[$mpn][] = $product;
            }
            $formatProducts[] = $product;
        }
        $this->products = $formatProducts;

        // 获取去掉重复数据的数组
        $uniqueMpns = array_unique($importNoComboMpns);
        // 获取非combo的MPN重复的数据
        $this->repeatNoComboMpns = array_diff_assoc($importNoComboMpns, $uniqueMpns);
        $this->mpnComboMap = $mpnComboMap;
        $this->noComboMpns = array_diff($uniqueMpns, $this->repeatNoComboMpns);

        // combo的格式错误的mpns
        $comboFormatErrorMpns = [];
        foreach ($mpnComboMap as $mpn => $products) {
            $str = '';
            $otherStr = '';
            foreach ($products as $product) {
                if ($str == '') {
                    $str = $this->handleProductFieldValueStr($product);
                    $otherStr = $this->handleProductPathFieldValueStr($product);
                    continue;
                }
                //有问题的combo
                $compareStr = $this->handleProductFieldValueStr($product);;
                if (strtolower($str) != strtolower($compareStr)) {
                    $comboFormatErrorMpns[] = $mpn;
                    break;
                }

                $otherStrNext = $this->handleProductPathFieldValueStr($product);
                if (md5(strtolower($otherStr)) != md5(strtolower($otherStrNext))) {
                    $comboFormatErrorMpns[] = $mpn;
                    break;
                }
            }
        }
        $this->comboFormatErrorMpns = $comboFormatErrorMpns;

        $this->dimLimitWeightAndSeparateEnquiry = app(ProductRepository::class)->getDimLimitWeightAndSeparateEnquiry();
    }


    /**
     * @return string[]
     */
    protected function getRules(): array
    {
        return [
            'Category ID' => 'required|numeric|in:' . join(',', $this->validCategoryIds),
            'MPN' => 'required|regex:/^[a-zA-Z0-9][\w\s\-]*$/',
            'UPC' =>  'nullable|regex:/^[a-zA-Z0-9]*$/',
            'Sold Separately' => 'required|in:yes,no',
            'Product Title' => 'required',
            'Customized' => 'required|in:yes,no',
            'Place of Origin' => 'nullable|in:' . join(',', array_values(array_map('strtolower', Country::getCodeNameMap()))),
            'Color' => 'required|in:' . join(',', array_values($this->colorOptionIdNameMap)),
            'Material' => 'required|in:' . join(',', array_values($this->materialOptionIdNameMap)),
            'Filler' => 'nullable|in:' . join(',', array_values($this->materialOptionIdNameMap)),
            'Assembled Width' => 'required',
            'Assembled Length' => 'required',
            'Assembled Height' => 'required',
            'Assembled Weight' => 'required',
            'Product Type' => 'required|in:general item,combo item,replacement part',
            'Width' => 'required|regex:/^(\d{1,3})(\.\d{0,2})?$/',
            'Length' => 'required|regex:/^(\d{1,3})(\.\d{0,2})?$/',
            'Height' => 'required|regex:/^(\d{1,3})(\.\d{0,2})?$/',
            'Weight' => 'required|regex:/^(\d{1,3})(\.\d{0,2})?$/',
            'Display Price' => 'required|in:invisible,visible',
            'Original Design' => 'required|in:yes,no',
            'Current Price' => $this->country == JAPAN_COUNTRY_ID ? 'required|regex:/^(\d{1,7})?$/' : 'required|regex:/^(\d{1,7})(\.\d{0,2})?$/',
        ];
    }

    /**
     * @return string[]
     */
    protected function getRuleMessages(): array
    {
        return [
            'required' => ':attribute can not be left blank.',
            'MPN.regex' => 'Failed! MPN can only be letters, numbers, hyphens, spaces and underscores.',
            'UPC.regex' => 'UPC only english letters and digits are permitted.',
            'Customized.in' => 'Customized only allows to fill in Yes/No.',
            'Place of Origin.in' => 'The place of origin does not exist, please download the template again and select the country in the file.',
            'Color.in' => 'The main color does not exist, please download the template again and select the color in the file.',
            'Material.in' => 'The main material does not exist, please download the template again and select the material in the file.',
            'Filler.in' => 'The filler does not exist, please download the template again and select the filler in the file.',
            'Sold Separately.in' => 'Sold Separately only allows to fill in Yes/No.',
            'Product Type.in' => 'Product type(General Item/Combo Item/Replacement Part) only allows to fill in General Item/Combo Item/Replacement Part.',
            'Display Price.in' => 'Display Price only allows to fill in Invisible/Visible.',
            'Width.regex' => 'Width only enter a number between 0.01 and 999.99.',
            'Length.regex' => 'Length only enter a number between 0.01 and 999.99.',
            'Height.regex' => 'Height only enter a number between 0.01 and 999.99.',
            'Weight.regex' => 'Weight only enter a number between 0.01 and 999.99.',
            'Current Price.regex' => $this->country == JAPAN_COUNTRY_ID ? 'The price can only be a number between 0 and 9999999' : 'The price can only be a number between 0.00 and 9999999.99',
            'Original Design.in' => "Only 'Yes' or 'No' can be selected for the 'Original Design'",
            'Category ID.in' => 'The Category ID does not exist.',
        ];
    }

    /**
     * @return string[]
     */
    protected function getCustomAttributes(): array
    {
        return [
            'MPN' => 'MPN',
            'UPC' => 'UPC',
            'Sold Separately' => 'Sold Separately',
            'Product Title' => 'Product Title',
            'Color' => 'Main Color',
            'Material' => 'Main Material',
            'Product Type' => 'Product Type',
            'Customized' => 'Customized',
            'Width' => 'Width',
            'Length' => 'Length',
            'Height' => 'Height',
            'Weight' => 'Weight',
            'Display Price' => 'Display Price',
            'Current Price' => 'Current Price',
            'Original Design' => 'Original Design',
            'Category ID' => 'Category ID',
        ];
    }

    /**
     * 验证mpn
     * @param array $product
     * @throws ValidateTerminationException
     */
    protected function validateMpn(array $product)
    {
        // 在rule已验证，这边只是拦截作用
        if (empty($product['MPN']) || empty($product['Category ID'])) {
            $this->isHandle = 0;
            throw new ValidateTerminationException();
        }

        // 中日文需计算2个字符
        $stringCharactersLen = StringHelper::stringCharactersLen($product['MPN']);
        if ($stringCharactersLen < 4 || $stringCharactersLen > 30) {
            $this->isHandle = 0;
            $this->errors[] = 'MPN must be greater than 4 and less than 30 characters.';
        }

        // 创建onsite产品时，增加校验MPN不能和平台已经存在的item code重复。
        if ($this->customer->accounting_type == CustomerAccountingType::GIGA_ONSIDE && app(ProductRepository::class)->hasSkuProductByMpnAndCountryId($product['MPN'], $this->customer->country_id)) {
            $this->isHandle = 0;
            $this->errors[] = 'MPN cannot be duplicate with the existing Item Code.';
        }
    }

    /**
     * 验证平台可发尺寸
     * @param array $product
     * @throws ValidateTerminationException
     */
    protected function validateChargeableWeightExceed(array $product)
    {
        if ($product['Product Type'] != 'combo item') {
            $productInfo = $this->getProductInfo($product['MPN']);
            // 对于非combo产品mpn重复的判断
            // 重复的mpn or 数据库已存在 or 在combo数据中
            if (in_array($product['MPN'], $this->repeatNoComboMpns) || !empty($productInfo) || isset($this->mpnComboMap[$product['MPN']])) {
                $this->isHandle = 0;
                $this->errors[] = 'MPN can not repeat.';
            }

            if (empty($product['Width']) || empty($product['Height']) || empty($product['Length']) || empty($product['Weight'])) {
                $this->isHandle = 0;
                throw new ValidateTerminationException();
            }

            // 对于超过平台可发尺寸的产品的验证
            if ($this->isUSA && app(ProductRepository::class)->checkChargeableWeightExceed($product['Width'], $product['Height'], $product['Length'], $product['Weight'], $this->dimLimitWeightAndSeparateEnquiry)) {
                $this->isHandle = 0;
                $this->errors[] = 'The chargeable weight exceeds ' . $this->dimLimitWeightAndSeparateEnquiry['limit_weight'] . 'lbs, which exceeds the maximum shipping size.';
            }
        }
    }

    /**
     * 验证combo
     * @param array $product
     * @throws ValidateTerminationException
     */
    protected function validateComboAttribute(array $product)
    {
        if ($product['Product Type'] == 'combo item') {
            $productInfo = $this->getProductInfo($product['MPN']);
            if (!empty($productInfo) || in_array($product['MPN'], $this->noComboMpns)) {
                $this->isHandle = 0;
                $this->errors[] = 'MPN can not repeat.';
            }
            if ($product['Sub-items'] == '') {
                $this->isHandle = 0;
                $this->errors[] = 'Sub-items can not be blank.';
                throw new ValidateTerminationException();
            }

            if (mb_strlen($product['Sub-items']) < 4 || mb_strlen($product['Sub-items']) > 64) {
                $this->isHandle = 0;
                $this->errors[] = 'Sub-items must be greater than 4 and less than 64 characters.';
            }

            if ($product['Sub-items Quantity'] == '' || !preg_match('/^[0-9]*$/', $product['Sub-items Quantity'])) {
                $this->isHandle = 0;
                $this->errors[] = 'Sub-items Quantity only allows to fill in numbers.';
            }

            if ($product['Sub-items Quantity'] == '0') {
                $this->isHandle = 0;
                $this->errors[] = 'Sub-items Quantity don\'t allows to fill in 0.';
            }

            $subItemProduct = app(ProductRepository::class)->getProductInfoByCustomerIdAndMpn($this->customerId, strtoupper($product['Sub-items']));

            // 验证查询出来的数据与实际是否一致
            if ($subItemProduct) {
                $subItemProductLength = $this->isUSA ? $subItemProduct->length : $subItemProduct->length_cm;
                $subItemProductWidth = $this->isUSA ? $subItemProduct->width : $subItemProduct->width_cm;
                $subItemProductHeight = $this->isUSA ? $subItemProduct->height : $subItemProduct->height_cm;
                $subItemProductWeight = $this->isUSA ? $subItemProduct->weight : $subItemProduct->weight_kg;
                if ($subItemProductLength <= 0 || $subItemProductWidth <= 0 || $subItemProductHeight <= 0 || $subItemProductWeight <= 0) {
                    $this->isHandle = 0;
                    $this->errors[] = 'Product dimensions should be greater than 0, please contact marketplace to update!';
                }

                if ($this->sprintfColumn($product['Length']) != $this->sprintfColumn($subItemProductLength)
                    || $this->sprintfColumn($product['Width']) != $this->sprintfColumn($subItemProductWidth)
                    || $this->sprintfColumn($product['Height']) != $this->sprintfColumn($subItemProductHeight)
                    || $this->sprintfColumn($product['Weight']) != $this->sprintfColumn($subItemProductWeight)
                ) {
                    $this->isHandle = 0;
                    $this->errors[] = 'The dimension information of Sub-items can not be changed!';
                }
                // 子产品不能是combo
                if ($subItemProduct->combo_flag == 1) {
                    $this->isHandle = 0;
                    $this->errors[] = 'Sub-items can not be combo items.';
                }
                if ($subItemProduct->product_type != ProductType::NORMAL) {
                    $this->isHandle = 0;
                    $this->errors[] = 'The type of sub-item cannot be a virtual product.';
                }
            }

            if (in_array($product['MPN'], $this->comboFormatErrorMpns)) {
                $this->isHandle = 0;
                $this->errors[] = 'The product information of the same combo item must be the same.';
            }

            // 校验子产品不能重复
            $subItemProducts = $this->mpnComboMap[$product['MPN']];
            $subItems = array_values(array_column($subItemProducts, 'Sub-items'));
            if (array_count_values($subItems)[$product['Sub-items']] > 1) {
                $this->isHandle = 0;
                $this->errors[] = 'Found duplicate items';
            }

            if (isset(array_column($this->products, null, 'MPN')[$product['Sub-items']])) {
                $subItemProduct = array_column($this->products, null, 'MPN')[$product['Sub-items']];
                if ($this->sprintfColumn($product['Length']) != $this->sprintfColumn($subItemProduct['Length'])
                    || $this->sprintfColumn($product['Width']) != $this->sprintfColumn($subItemProduct['Width'])
                    || $this->sprintfColumn($product['Height']) != $this->sprintfColumn($subItemProduct['Height'])
                    || $this->sprintfColumn($product['Weight']) != $this->sprintfColumn($subItemProduct['Weight'])
                ) {
                    $this->isHandle = 0;
                    $this->errors[] = 'The dimension information of Sub-items can not be changed!';
                }
            }

            if (isset($this->mpnComboMap[$product['Sub-items']])) {
                $this->isHandle = 0;
                $this->errors[] = 'Sub-items can not be combo items.';
            }

            // 校验子产品至少有2个
            if (array_sum(array_column($subItemProducts, 'Sub-items Quantity')) < 2) {
                $this->isHandle = 0;
                $this->errors[] = 'Sub-item quantity must greater than 1.';
            }

            // 对于combo超过平台可发尺寸的产品的验证
            if ($this->isUSA) {
                $subItemsWeight = 0;
                foreach ($subItemProducts as $subItemProduct) {
                    // 无效的需要剔除
                    if ($subItemProduct['Sub-items'] == ''
                        || mb_strlen($subItemProduct['Sub-items']) < 4
                        || mb_strlen($subItemProduct['Sub-items']) > 64
                        || $subItemProduct['Sub-items Quantity'] == ''
                        || !preg_match('/^[0-9]*$/', $subItemProduct['Sub-items Quantity'])
                        || $subItemProduct['Sub-items Quantity'] == '0'
                    ) {
                        continue;
                    }
                    /** @var Product $subItemProductInfo */
                    $subItemProductInfo = app(ProductRepository::class)->getProductInfoByCustomerIdAndMpn($this->customerId, strtoupper($subItemProduct['Sub-items']));
                    if ($subItemProductInfo) {
                        if ($subItemProductInfo->length <= 0
                            || $subItemProductInfo->width <= 0
                            || $subItemProductInfo->height <= 0
                            || $subItemProductInfo->weight <= 0
                            || $this->sprintfColumn($subItemProduct['Length']) != $this->sprintfColumn($subItemProductInfo->length)
                            || $this->sprintfColumn($subItemProduct['Width']) != $this->sprintfColumn($subItemProductInfo->width)
                            || $this->sprintfColumn($subItemProduct['Height']) != $this->sprintfColumn($subItemProductInfo->height)
                            || $this->sprintfColumn($subItemProduct['Weight']) != $this->sprintfColumn($subItemProductInfo->weight)
                            || $subItemProductInfo->combo_flag == 1
                            || $subItemProductInfo->product_type != ProductType::NORMAL
                        ) {
                            continue;
                        }
                    }

                    if (empty($subItemProduct['Width']) || empty($subItemProduct['Height']) || empty($subItemProduct['Length']) || empty($subItemProduct['Weight'])) {
                        continue;
                    }

                    $subItemsWeight += max([($subItemProduct['Length'] * $subItemProduct['Width'] * $subItemProduct['Height']) / $this->dimLimitWeightAndSeparateEnquiry['dim'], $subItemProduct['Weight']]) * $subItemProduct['Sub-items Quantity'];
                }

                if ($subItemsWeight > $this->dimLimitWeightAndSeparateEnquiry['limit_weight'] && $this->dimLimitWeightAndSeparateEnquiry['separate_enquiry']) {
                    $this->isHandle = 0;
                    $this->errors[] = 'The chargeable weight exceeds ' . $this->dimLimitWeightAndSeparateEnquiry['limit_weight'] . 'lbs, which exceeds the maximum shipping size.';
                }
            }
        }
    }


    /**
     * 验证原创产品
     * @param array $product
     * @param array $originProduct
     * @throws ValidateTerminationException
     */
    protected function validateOriginalDesign(array &$product, array $originProduct)
    {
        if (strtolower($originProduct['Original Design']) == 'yes') {
            if (empty($product['Supporting Files Path'])) {
                $this->isHandle = 0;
                $this->errors[] = "When 'Yes' is selected for 'Original Design', the 'Supporting Files Path' is required.";
                throw new ValidateTerminationException();
            } else {
                parent::validateOriginalDesign($product, $originProduct);
            }
        } else {
            $product['Supporting Files Path'] = ''; //非原创时候，填写的文件无效
        }
    }

    /**
     * 返回值
     * @param array $partProduct
     * @param array $product
     * @return array
     */
    protected function returnData(array $partProduct, array $product): array
    {
        return [
            'part_product' => $partProduct,
            'errors' => $this->errors,
            'can_insert' => $this->isHandle,
        ];
    }

    private $_productInfoPool = [];

    /**
     * 产品信息缓存
     * @param string $sku
     * @return Product|mixed
     */
    private function getProductInfo(string $sku)
    {
        $sku = strtoupper($sku);
        if (isset($this->_productInfoPool[$sku])) {
            return $this->_productInfoPool[$sku];
        }

        return app(ProductRepository::class)->getProductInfoByCustomerIdAndMpn($this->customerId, $sku);
    }

    /**
     * 处理产品字段值拼接
     * @param array $product
     * @return string
     */
    private function handleProductFieldValueStr(array $product): string
    {
        return $product['Category ID'] . $product['MPN'] . $product['UPC'] . $product['Sold Separately'] .
            $product['Not available for sale on'] . $product['Product Title'] . $product['Customized'] . $product['Place of Origin'] . $product['Color'] .
            $product['Material'] . $product['Filler'] . $product['Assembled Length'] . $product['Assembled Width'] . $product['Assembled Height'] . $product['Assembled Weight'] .
            $product['Current Price'] . $product['Display Price'] .
            $product['Original Design'] . trim($product['Product Description']);
    }

    /**
     * 处理产品附件地址拼接
     * @param array $product
     * @return string
     */
    private function handleProductPathFieldValueStr(array $product): string
    {
        $fields = [
            'Supporting Files Path',
            'Images Path(to be displayed)',
            'Images Path(other material)',
            'Material Manual Path',
            'Material Video Path',
        ];

        $str = '';
        foreach ($fields as $field) {
            if (!empty($product[$field])) {
                $path = explode('|', $product[$field]);
                sort($path);
                $str .= implode('|', $path);
            }
        }

        return $str;
    }

    /**
     * @param string $string
     * @return string
     */
    private function sprintfColumn(string $string): string
    {
        return sprintf('%.2f', $string);
    }
}
