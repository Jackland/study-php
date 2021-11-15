<?php

namespace App\Catalog\Forms\Product;

use App\Enums\Product\ProductStatus;
use App\Helper\StringHelper;
use App\Models\Product\Option\Option;
use App\Models\Product\Option\OptionValue;
use App\Models\Product\Product;
use App\Models\Product\ProductSetInfo;
use App\Repositories\Product\ProductRepository;
use App\Components\Storage\StorageCloud;
use Illuminate\Validation\Rule;

class AddForm
{
    protected function getRules(): array
    {
        $rules = [
            'product_category' => 'required',
            'mpn' => ['required', 'string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen < 4 || $stringCharactersLen > 30) {
                    $fail(__('MPN必须大于4且小于30个字符。',[],'validation/product'));
                    return;
                }
                $productInfo = app(ProductRepository::class)
                    ->getProductInfoByCustomerIdAndMpn(customer()->getId(), $value);
                if ($productInfo) {
                    $fail(__('失败！MPN不能重复。',[],'validation/product'));
                    return;
                }
                // 创建onsite产品时，增加校验MPN不能和平台已经存在的item code重复。
                if (customer()->isGigaOnsiteSeller() && app(ProductRepository::class)->hasSkuProductByMpnAndCountryId($value, customer()->getCountryId())) {
                    $fail(__('失败！MPN不能与已存在的Item Code重复。',[],'validation/product'));
                    return;
                }
            }],
            'buyer_flag' => 'required|integer|in:0,1',
            'name' => ['required', 'string', function ($attribute, $value, $fail) {
                if (StringHelper::stringCharactersLen($value) > 200) {
                    $fail(__('产品标题必须大于1且小于200个字符。',[],'validation/product'));
                    return;
                }
            }],
            'color' => ['required', 'integer', Rule::in(OptionValue::query()->valid()->where('option_id', Option::COLOR_OPTION_ID)->pluck('option_value_id')->toArray())],
            'material' => ['required', 'integer', Rule::in(OptionValue::query()->valid()->where('option_id', Option::MATERIAL_OPTION_ID)->pluck('option_value_id')->toArray())],
            'product_type' => 'required|integer|in:1,2,3',
            'length' => 'required_if:product_type,1,3',
            'width' => 'required_if:product_type,1,3',
            'height' => 'required_if:product_type,1,3',
            'weight' => 'required_if:product_type,1,3',
            'is_ltl' => 'required|integer|in:0,1',
            'price' => customer()->isJapan() ? 'required|regex:/^(\d{1,7})$/' : 'required|regex:/^(\d{1,7})(\.\d{0,2})?$/',
            'price_display' => 'required|integer|in:0,1',
            'image' => 'required_if:buyer_flag,1',
            'product_image' => 'required_if:buyer_flag,1',
            'return_warranty' => 'required',
            'is_draft' => 'required|integer|in:1,2',
            'original_product' => 'required|integer|in:0,1',
            'original_design' => ['required_if:original_product,1','array'],
            'upc' =>  'nullable|regex:/^[a-zA-Z0-9]*$/',
            'is_customize' => 'required|integer|in:0,1',
            'assemble_length' => ['required', 'regex:/^(\d{0,3})(\.\d{0,2})?|-1.00$/', 'not_in:0.00,0.0,0'],
            'assemble_width' => ['required', 'regex:/^(\d{0,3})(\.\d{0,2})?|-1.00$/', 'not_in:0.00,0.0,0'],
            'assemble_height' => ['required', 'regex:/^(\d{0,3})(\.\d{0,2})?|-1.00$/', 'not_in:0.00,0.0,0'],
            'assemble_weight' => ['required', 'regex:/^(\d{0,3})(\.\d{0,2})?|-1.00$/', 'not_in:0.00,0.0,0'],
        ];

        return $rules;
    }

    protected function getRuleMessages(): array
    {
        return [
            'required' => ':attribute is(are) required field, can not be blank.',
            'buyer_flag.required' => 'Sold Separately is required field, can not be blank.',
            'name.required' => __('产品标题不能为空。', [], 'validation/product'),
            'product_type.in' => 'Product type is required',
            'image.required_if' => __('可单独售卖的产品必须设置主图。', [], 'validation/product'),
            'original_design.required_if' => __('原创产品选择是时，原创证明文件必填', [], 'catalog/view/pro/product/addproduct'),
            'color.required' => __('主要颜色不能为空。', [], 'validation/product'),
            'material.required' => __('主要材质不能为空。', [], 'validation/product'),
            'upc.regex' => __('仅能输入英文和数字', [], 'validation/product'),
        ];
    }

    public function validator()
    {
        $post = request()->post();
        $rules = $this->getRules();

        if ($post['product_id']) {
            unset($rules['mpn'], $rules['price'], $rules['price_display'], $rules['is_draft']);
        }
        $validator = validator($post, $rules, $this->getRuleMessages());

        $validator->sometimes(['length', 'width', 'height', 'weight'],
            'required|numeric|min:0.01|max:999.99', function ($input) {
                return in_array($input['product_type'], [1, 3]);
            });
        if ($validator->fails()) {
            return $validator->errors()->first();
        }
        //一些特殊的强校验放在这，不使用回调了
        if (!$post['product_id']) {
            $sellerId = customer()->getId() ?? 0;
            if (empty($sellerId)) {
                return 'Customer Id is empty.';
            }
            if ($post['fromNew'] != 1) {
                return 'FromNew is empty';
            }
        }
        if ($post['image']) {
            $fileExist = StorageCloud::image()->fileExists($post['image']);
            if (!$fileExist) {
                return __('可单独售卖的产品必须设置主图。',[],'validation/product');
            }
        }
        // 38548 combo产品在非待上架状态子产品不能修改
        if (!empty($post['product_id']) && !empty($post['combo_flag']) && !empty($post['combo'])) {
            $product = Product::query()->where('product_id', $post['product_id'])->where('status', '!=', ProductStatus::WAIT_SALE)->first();
            if (!empty($product)) {
                $submitComboSkuQtyMap = array_column($post['combo'], 'quantity', 'product_id');
                $productComboSkuQtyMap = ProductSetInfo::query()->where('product_id', $post['product_id'])->pluck('qty', 'set_product_id')->toArray();
                if ($submitComboSkuQtyMap != $productComboSkuQtyMap) {
                    return __('子产品的组成和数量仅能在产品上架前更改', [], 'catalog/view/pro/product/addproduct');
                }
            }
        }

        return ''; //验证ok
    }

}
