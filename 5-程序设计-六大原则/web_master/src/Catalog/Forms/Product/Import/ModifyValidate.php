<?php

namespace App\Catalog\Forms\Product\Import;

use App\Catalog\Forms\Product\Import\Exception\ValidateTerminationException;
use App\Models\Customer\Country;
use App\Repositories\Product\ProductRepository;

class ModifyValidate extends AbstractValidate
{
    /**
     * 重复的MPN数组
     * @var array
     */
    private $repeatMpns = [];

    /**
     * 初始化数据组装
     */
    protected function init()
    {
        $importMpns = [];
        foreach ($this->products as $product) {
            $product = array_map('strtolower', $product);
            $importMpns[] = $product['MPN'];
        }
        $uniqueMpns = array_unique($importMpns);
        $this->repeatMpns = array_diff_assoc($importMpns, $uniqueMpns);
    }

    /**
     * @return string[]
     */
    protected function getRules(): array
    {
        return [
            'Category ID' => 'nullable|numeric|in:' . join(',', $this->validCategoryIds),
            'MPN' => 'required|regex:/^[a-zA-Z0-9][\w\s\-]*$/',
            'UPC' =>  'nullable|regex:/^[a-zA-Z0-9]*$/',
            'Sold Separately' => 'nullable|string|in:yes,no',
            'Customized' => 'nullable|string|in:yes,no',
            'Product Title' => 'nullable|string',
            'Place of Origin' => 'nullable|in:' . join(',', array_values(array_map('strtolower', Country::getCodeNameMap()))),
            'Color' => 'nullable|string|in:' . join(',', array_values($this->colorOptionIdNameMap)),
            'Material' => 'nullable|string|in:' . join(',', array_values($this->materialOptionIdNameMap)),
            'Filler' => 'nullable|in:' . join(',', array_values($this->materialOptionIdNameMap)),
            'Manual Provided' => 'nullable|string|in:yes,no',
            'Original Design' => 'nullable|string|in:yes,no',
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
            'Place of Origin.in' => 'The place of origin does not exist, please download the template again and select the country in the file.',
            'UPC.regex' => 'UPC only english letters and digits are permitted.',
            'Color.in' => 'The main color does not exist, please download the template again and select the color in the file.',
            'Material.in' => 'The main material does not exist, please download the template again and select the material in the file.',
            'Filler.in' => 'The filler does not exist, please download the template again and select the filler in the file.',
            'Sold Separately.in' => 'Sold Separately only allows to fill in Yes/No.',
            'Manual Provided.in' => 'Manual Provided only allows to fill in Yes/No.',
            'Customized.in' => 'Customized only allows to fill in Yes/No.',
            'Original Design.in' => "Only 'Yes' or 'No' can be selected for the 'Original Design'",
            'Category ID.in' => 'The Category ID does not exist.',
            'Assembled Width.regex' => 'Assembled Width only enter a number between 0.01 and 999.99.',
            'Assembled Length.regex' => 'Assembled Length only enter a number between 0.01 and 999.99.',
            'Assembled Height.regex' => 'Assembled Height only enter a number between 0.01 and 999.99.',
            'Assembled Weight.regex' => 'Weight only enter a number between 0.01 and 999.99.',
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
            'Manual Provided' => 'Manual Provided',
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
        if (!isset($product['MPN']) || empty($product['MPN'])) {
            $this->isHandle = 0;
            throw new ValidateTerminationException();
        }
        //一个excel里面不允许重复的MPN出现
        if (in_array($product['MPN'], $this->repeatMpns)) {
            $this->isHandle = 0;
            $this->errors[] = 'MPN can not repeat.';
            throw new ValidateTerminationException();
        }

        $productInfo = $this->getProductInfo($product['MPN']);
        if (empty($productInfo) || $productInfo->is_deleted == 1 || !in_array($productInfo->is_original_design, [0, 1])) { // is_original_design null 没查到数据
            $this->isHandle = 0;
            $this->errors[] = 'The MPN does not exist.';
            throw new ValidateTerminationException();
        }
    }

    /**
     * 编辑时不需验证
     * @param array $product
     */
    protected function validateChargeableWeightExceed(array $product)
    {
    }

    /**
     * 编辑时不需验证
     * @param array $product
     */
    protected function validateComboAttribute(array $product)
    {
    }

    /**
     * 验证原创产品
     * @param array $product
     * @param array $originProduct
     * @throws ValidateTerminationException
     */
    protected function validateOriginalDesign(array &$product, array $originProduct)
    {
        $productInfo = $this->getProductInfo($originProduct['MPN']);
        if (empty($originProduct['Original Design'])) {
            if ($productInfo->is_original_design == 0) {
                $product['Supporting Files Path'] = [];
            } else {
                parent::validateOriginalDesign($product, $originProduct);
            }
        } elseif (strtolower($originProduct['Original Design']) == 'yes') {
            if (!empty($product['Supporting Files Path'])) {
                parent::validateOriginalDesign($product, $originProduct);
            } else {
                if ($productInfo->is_original_design == 0) {
                    $this->isHandle = 0;
                    $this->errors[] = "When 'Yes' is selected for 'Original Design', the 'Supporting Files Path' is required.";
                    throw new ValidateTerminationException();
                }
            }
        } else {
            $product['Supporting Files Path'] = [];
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
        $productInfo = $this->getProductInfo($product['MPN']);
        return [
            'part_product' => $partProduct,
            'errors' => $this->errors,
            'current_product_id' => optional($productInfo)->product_id ?? 0, //基于此product_id 才能用下面的字段
            'status' => optional($productInfo)->status ?? -1,
            'product_old_title' => optional(optional($productInfo)->description)->name ?? '',
            'combo_flag' => optional($productInfo)->combo_flag ?? '',
            'can_update' => $this->isHandle,
        ];
    }

    private $_productInfoPool = [];

    /**
     * 获取产品信息缓存
     * @param string $sku
     * @return \App\Models\Product\Product|mixed
     */
    private function getProductInfo(string $sku)
    {
        $sku = strtoupper($sku);
        if (isset($this->_productInfoPool[$sku])) {
            return $this->_productInfoPool[$sku];
        }

        return app(ProductRepository::class)->getProductInfoByCustomerIdAndMpnInfo($this->customerId, $sku);
    }
}
