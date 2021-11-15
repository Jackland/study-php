<?php

namespace App\Repositories\Product;


use App\Enums\Country\Country;
use App\Helpers\LoggerHelper;
use App\Models\Product\CategoryDescription;
use App\Models\Product\Product;
use App\Models\Product\ProductCustomField;
use App\Models\Product\ProductExts;
use App\Repositories\Customer\CustomerRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductRepository
{


    public function getProductInfo($productId)
    {
        return Product::query()
            ->with('description')
            ->where('product_id', $productId)
            ->first();
    }

    public function getSellerInfoByProductId($productId)
    {
        return DB::table('oc_customerpartner_to_product as ctp')
            ->select('ctc.screenname')
            ->join('oc_customerpartner_to_customer as ctc', 'ctp.customer_id', '=', 'ctc.customer_id')
            ->where(['ctp.product_id' => $productId])
            ->first();
    }

    /**
     * 产品详情页显示的 Product Type 显示文字
     * @param array $product oc_product表的一条记录
     * @return string
     */
    public function getProductTypeNameForBuyer($product = [])
    {
        if (!$product || !is_array($product)) {
            return '';
        }
        if ($product['combo_flag'] == 1) {
            return 'Combo Item';
        }
        if ($product['part_flag'] == 1) {
            return 'Replacement Part';
        }
        return 'General Item';
    }


    /**
     * 产品详情页显示的 Package Size 显示文字
     * @param array $product oc_product表的一条记录
     * @param int $countryId
     * @param array $subQuery 给定的ComboList子产品 [['product_id'=>0,'name'=>'','sku'=>'','mpn'=>'','qty'=>0,'两套尺寸'=>0],...]
     * @return array
     */
    public function getPackageSizeForBuyer($product, $countryId, $subQuery = [])
    {
        $results = [];
        $precision = 2;

        if (!$product || !is_array($product)) {
            return $results;
        }
        if ($countryId == Country::AMERICAN) {
            $unitLength = 'inches';
            $unitWeight = 'lbs';
        } else {
            $unitLength = 'cm';
            $unitWeight = 'kg';
        }
        if ($product['combo_flag']) {
            if (!$subQuery) {
                $subQuery = $this->getSubQueryByProductId($product['product_id']);
            }
            foreach ($subQuery as $key => $value) {
                $qty = $value['qty'];
                if ($countryId == Country::AMERICAN) {
                    $length = number_format($value['length'], $precision);
                    $width = number_format($value['width'], $precision);
                    $height = number_format($value['height'], $precision);
                    $weight = number_format($value['weight'], $precision);
                } else {
                    $length = number_format($value['length_cm'], $precision);
                    $width = number_format($value['width_cm'], $precision);
                    $height = number_format($value['height_cm'], $precision);
                    $weight = number_format($value['weight_kg'], $precision);
                }
                $result = [
                    'sku' => $value['sku'],
                    'msg' => "Package Quantity:&nbsp;&nbsp;{$qty}&nbsp;&nbsp;&nbsp;&nbsp;{$length}&nbsp;*&nbsp;{$width}&nbsp;*&nbsp;{$height}&nbsp;{$unitLength}&nbsp;&nbsp;{$weight}{$unitWeight}",
                ];
                $results[] = $result;
            }
            return $results;
        } else {
            if ($countryId == Country::AMERICAN) {
                $length = number_format($product['length'], $precision);
                $width = number_format($product['width'], $precision);
                $height = number_format($product['height'], $precision);
                $weight = number_format($product['weight'], $precision);
            } else {
                $length = number_format($product['length_cm'], $precision);
                $width = number_format($product['width_cm'], $precision);
                $height = number_format($product['height_cm'], $precision);
                $weight = number_format($product['weight_kg'], $precision);
            }
            $result = [
                'sku' => $product['sku'],
                'msg' => "{$length}&nbsp;*&nbsp;{$width}&nbsp;*&nbsp;{$height}&nbsp;{$unitLength}&nbsp;&nbsp;{$weight}{$unitWeight}",
                'length' => $length,
                'width' => $width,
                'height' => $height,
                'weight' => $weight,
                'unitLength' => $unitLength,
                'unitWeight' => $unitWeight,
            ];
            $results[] = $result;
        }
        return $results;
    }

    /**
     * 产品详情页 Combo品获取子产品
     * @param $productId
     * @return array
     */
    public function getSubQueryByProductId($productId)
    {
        $subResult = DB::table('tb_sys_product_set_info as ps')
            ->leftJoin('oc_product as p', 'ps.set_product_id', '=', 'p.product_id')
            ->where('ps.product_id', $productId)
            ->select([
                'p.sku',
                'p.mpn',
                'p.weight',
                'p.height',
                'p.length',
                'p.width',
                'p.weight_kg',
                'p.length_cm',
                'p.width_cm',
                'p.height_cm',
                'ps.qty',
                'ps.set_product_id',
                'ps.product_id',
            ])
            ->get();
        return json_decode(json_encode($subResult), true);
    }

    public function specificationForDownload($productInfo, $countryId)
    {
        $productId = $productInfo['product_id'];
        $page_product_type_name = app(ProductRepository::class)->getProductTypeNameForBuyer($productInfo);
        $page_package_size_list = app(ProductRepository::class)->getPackageSizeForBuyer($productInfo, $countryId);

        //region 产品的颜色材质等信息
        $productOption = app(ProductOptionRepository::class)->getProductOptionByProductId($productId);
        $color_name = isset($productOption['color_name']) ? $productOption['color_name'] : '';
        $material_name = isset($productOption['material_name']) ? $productOption['material_name'] : '';
        //endregion

        //去掉了mpn且重新打包时候生效
        //<tr>
        //   <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">MPN</td>
        //   <td style="border: 1px solid #dbdbdb;">' . $productInfo['mpn'] . '</td>
        //</tr>

        /** @var ProductExts $productExt */
        $productExt = ProductExts::query()->where('product_id', $productId)->first();

        $html = '
<h3>Product Information</h3>
<table style="border: 1px solid #dbdbdb;border-collapse: collapse;width: 60%; margin-bottom: 20px;line-height: 35px;">
    <tbody>
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Item Code</td>
            <td style="border: 1px solid #dbdbdb;">' . $productInfo['sku'] . '</td>
        </tr>';

        if (!empty($productInfo['upc'])) {
            $html .=  '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">UPC</td>
            <td style="border: 1px solid #dbdbdb;">' . $productInfo['upc'] . '</td>
        </tr>
            ';
        }

        $html .= '<tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Product Type</td>
            <td style="border: 1px solid #dbdbdb;">' . $page_product_type_name . '</td>
        </tr>
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Product Name</td>
            <td style="border: 1px solid #dbdbdb;">' . $productInfo['description']['name'] . '</td>
        </tr>';

        if ($productExt && $productExt->origin_place_code) {
            $countryCodeName = \App\Models\Customer\Country::query()->where('iso_code_3', $productExt->origin_place_code)->value('name');
            $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Place of Origin</td>
            <td style="border: 1px solid #dbdbdb;">' . $countryCodeName . '</td>
        </tr>
            ';
        }

        if ($color_name) {
            $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Main Color</td>
            <td style="border: 1px solid #dbdbdb;">' . $color_name . '</td>
        </tr>
            ';
        }
        if ($material_name) {
            $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Main Material</td>
            <td style="border: 1px solid #dbdbdb;">' . $material_name . '</td>
        </tr>
            ';
        }

        if ($productExt && $productExt->filler) {
            $filler = DB::table('oc_option_value_description')->where('option_value_id', $productExt->filler)->value('name');
            $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Filler</td>
            <td style="border: 1px solid #dbdbdb;">' . $filler . '</td>
        </tr>
            ';
        }

        $customFields = ProductCustomField::query()->where('product_id', $productId)->orderBy('sort')->get();
        $informationCustomFields = $customFields->where('type', 1)->toArray();
        if ($informationCustomFields) {
            foreach ($informationCustomFields as $informationCustomField) {
                $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">' . htmlspecialchars($informationCustomField['name']) . '</td>
            <td style="border: 1px solid #dbdbdb;">' . htmlspecialchars($informationCustomField['value']) . '</td>
        </tr>
            ';
            }
        }
        if ($productExt && $productExt->is_customize) {
            $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Customized</td>
            <td style="border: 1px solid #dbdbdb;">Yes</td>
        </tr>
            ';
        }
        if (!empty($productInfo['danger_flag'])) {
            $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Lithium battery contained</td>
            <td style="border: 1px solid #dbdbdb;">Yes</td>
        </tr>
            ';
        }
        $html .= '
    </tbody>
</table>';

        if ($productExt && $productExt->is_customize) {
            $html .= '
            <div>
              * Customized means the seller provides customized services for this product. For more information, please contact seller.
            </div>
            ';
        }

        if ($countryId == Country::AMERICAN) {
            $unitLength = 'in.';
            $unitWeight = 'lbs';
        } else {
            $unitLength = 'cm';
            $unitWeight = 'kg';
        }

        $html .= '
<h3>Product Dimensions</h3>
<table style="border: 1px solid #dbdbdb;border-collapse: collapse;width: 60%; margin-bottom: 20px;line-height: 35px;">
    <tbody>';
        $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Assembled Length (' . $unitLength . ')</td>
            <td style="border: 1px solid #dbdbdb;">' . $this->formatAssembleField($productExt ? $productExt->assemble_length : '') . '</td>
        </tr>
            ';

        $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Assembled Width (' . $unitLength . ')</td>
            <td style="border: 1px solid #dbdbdb;">' . $this->formatAssembleField($productExt ? $productExt->assemble_width : '') . '</td>
        </tr>
            ';

        $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Assembled Height (' . $unitLength . ')</td>
            <td style="border: 1px solid #dbdbdb;">' . $this->formatAssembleField($productExt ? $productExt->assemble_height : '') . '</td>
        </tr>
            ';

        $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Weight (' . $unitWeight . ')</td>
            <td style="border: 1px solid #dbdbdb;">' . $this->formatAssembleField($productExt ? $productExt->assemble_weight : '') . '</td>
        </tr>
            ';

        $assembleCustomFields = $customFields->where('type', 2)->toArray();
        if ($assembleCustomFields) {
            foreach ($assembleCustomFields as $assembleCustomField) {
                $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">' . htmlspecialchars($assembleCustomField['name']) . '</td>
            <td style="border: 1px solid #dbdbdb;">' . htmlspecialchars($assembleCustomField['value']) . '</td>
        </tr>
            ';
            }
        }
        $html .= '
    </tbody>
</table>';

        $html .= '
<h3>Package Size</h3>
<table style="border: 1px solid #dbdbdb;border-collapse: collapse;width: 60%; margin-bottom: 20px;line-height: 35px;">
    <tbody>';

        if ($productInfo['combo_flag']) {
            foreach ($page_package_size_list as $key => $item) {
                $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Sub-item ' . ($key + 1) . ': ' . $item['sku'] . '</td>
            <td style="border: 1px solid #dbdbdb;">' . $item['msg'] . '</td>
        </tr>';
            }

        } else {
            foreach ($page_package_size_list as $key => $item) {
                $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Length (' . $unitLength . ')</td>
            <td style="border: 1px solid #dbdbdb;">' . $item['length'] . '</td>
        </tr>';
                $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Width (' . $unitLength . ')</td>
            <td style="border: 1px solid #dbdbdb;">' . $item['width'] . '</td>
        </tr>';
                $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Height (' . $unitLength . ')</td>
            <td style="border: 1px solid #dbdbdb;">' . $item['height'] . '</td>
        </tr>';
                $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Weight (' . $unitWeight . ')</td>
            <td style="border: 1px solid #dbdbdb;">' . $item['weight'] . '</td>
        </tr>';
            }
        }
        $html .= '
    </tbody>
</table>';
        return $html;
    }

    public function getProductImages($productId, $limit = 0)
    {
        $build = DB::table('oc_product_image')
            ->where('product_id', $productId)
            ->orderBy('sort_order', 'ASC');
        if ($limit) {
            $build->limit($limit);
        }
        return $build->get()->pluck('image')->toArray();
    }

    /**
     * 获取资源包
     *
     * @param $product_id
     * @param string $type
     * @return array|\Illuminate\Support\Collection
     */
    public function getProductPackages($product_id, $type = 'image')
    {
        if (!in_array($type, ['image', 'file', 'video', 'original_design_image'])) {
            return [];
        }
        return DB::table('oc_product_package_' . $type)
            ->where('product_id', $product_id)
            ->get(['*']);
    }

    public function getProductCategoryInfo($customerId, $productId)
    {
        $data = DB::table('oc_product as p')
            ->leftJoin('oc_product_description as d', 'd.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_product as cp', 'cp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_customer as otc', 'cp.customer_id', '=', 'otc.customer_id')
            ->leftJoin('oc_product_to_tag as ot', 'ot.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_to_store as p2s', 'p2s.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customer  as c', 'c.customer_id', '=', 'cp.customer_id')
            ->where(['p.product_id' => $productId])
            ->select('p.product_id', 'p.sku as item_code', 'd.name as product_name', 'd.color', 'd.material', 'p.product_size', 'p.need_install', 'd.description',
                'p.length', 'p.width', 'p.height', 'p.weight', 'p.weight_kg', 'p.length_cm', 'p.width_cm', 'p.height_cm', 'p.price as unit_price', 'p.freight', 'p.danger_flag'
                , 'p.combo_flag', 'p.product_id', 'p.price_display', 'p.quantity_display', 'cp.customer_id', 'otc.screenname', 'c.customer_group_id')
            ->selectRaw("group_concat(ot.tag_id) as tag_id")->groupBy('p.product_id')
            ->first();
        return json_decode(json_encode($data), true);
    }


    /**
     * [getIsConnected description] 确认buyer和seller 联系
     * @param $buyer_id
     * @param $seller_id
     * @return int
     */
    public function getIsConnected($buyer_id, $seller_id)
    {
        $map['buyer_id'] = $buyer_id;
        $map['seller_id'] = $seller_id;
        $data = DB::table('oc_buyer_to_seller')->where($map)->first();
        if ($data) {
            if ($data->buy_status == 1 && $data->buyer_control_status == 1 && $data->seller_control_status == 1) {
                return $data->discount;
            }
            return 0;
        } else {
            return 0;
        }

    }

    /**
     * [getComboProductChildrenAmount description] 获取combo的子数量
     * @param $product_id
     * @return array
     */

    protected function getComboProductChildrenAmount($product_id)
    {
        $map = [
            ['product_id', '=', $product_id],
        ];
        $data = DB::table('tb_sys_product_set_info')->where($map)->
        whereNotNull('set_product_id')->sum('qty');
        return $data;

    }

    public function getProductCategoryCsv($customerId, $filePath, $productId)
    {
        $data = app(ProductRepository::class)->getProductCategoryInfo($customerId, $productId);
        $countryId = app(CustomerRepository::class)->getCountryId($customerId)->country_id;
        $currency = app(CustomerRepository::class)->getCountryId($customerId)->iso_code_3;
        $fp = fopen($filePath, 'a');
        //在写入的第一个字符串开头加 bom。
        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        fwrite($fp, $bom);
        $unit = '(inch)';
        $weightUnit = '(pound)';
        if ($countryId != Country::AMERICAN) {
            $data['length'] = $data['length_cm'];
            $data['width'] = $data['width_cm'];
            $data['height'] = $data['height_cm'];
            $data['weight'] = $data['weight_kg'];
            $unit = '(cm)';
            $weightUnit = '(kg)';
        }

        $customFields = ProductCustomField::query()->where('product_id', $productId)->orderBy('sort')->get();
        $informationCustomFields = $customFields->where('type', 1)->pluck('name')->toArray();
        $informationCustomFieldValues = $customFields->where('type', 1)->pluck('value')->toArray();
        $assembledCustomFields = $customFields->where('type', 2)->pluck('name')->toArray();
        $assembledCustomFieldValues = $customFields->where('type', 2)->pluck('value')->toArray();

        $head = [
            'Store Code',
            'Store Name',
            'ItemCode', //sku
            'Category',
            'Product Name', //name
            'Length' . $unit,
            'Width' . $unit,
            'Height' . $unit,
            'Weight' . $weightUnit,
            'Unit Price' . $currency,
            'Pickup Freight Per Unit' . $currency,
            'Drop Shipping Freight Per Unit' . $currency,
            'Qty Available',
        ];
        $head = array_merge($head, [
            'Is Oversized Item',
            'Is Combo Flag',
            'Package Quantity', //包裹数
            'Customized',
            'Place of Origin',
            'Color',
            'Material',
            'Filler',
            'Lithium Battery Contained',
        ]);
        // 自定义字段
        if (!empty($informationCustomFields)) {
            $head = array_merge($head, $informationCustomFields);
        }
        $head = array_merge($head, [
            'Assembled Length' . $unit,
            'Assembled Width' . $unit,
            'Assembled Height' . $unit,
            'Weight' . $weightUnit,
        ]);
        // 自定义字段
        if (!empty($assembledCustomFields)) {
            $head = array_merge($head, $assembledCustomFields);
        }
        $head = array_merge($head, [
            'Description',
            'Image1',
            'Image2',
            'Image3',
            'Image4',
            'Image5',
            'Image6',
            'Image7',
            'Image8',
            'Image9',
        ]);

        fputcsv($fp, $head);
        $packageFee = DB::table('oc_product_fee')->where('product_id', $data['product_id'])->get()->keyBy('type');
        if ($countryId == 107) { //日本
            $data['unit_price'] = (int)round($data['unit_price']);
            $data['home_pick_freight_per'] = (int)round($packageFee[2]->fee);
            $data['drop_ship_freight_per'] = (int)round($packageFee[1]->fee + $data['freight']);
        } else {
            $data['unit_price'] = sprintf('%.2f', $data['unit_price']);
            $data['home_pick_freight_per'] = sprintf('%.2f', $packageFee[2]->fee);
            $data['drop_ship_freight_per'] = sprintf('%.2f', $packageFee[1]->fee + $data['freight']);
        }


        if ($data['quantity_display'] != 1) {
            $data['qty_avaliable'] = 'Contact Seller to get the quantity available.';
        }
        if (null == $data['tag_id']) {
            $data['over_size_flag'] = 0;
        } else {
            $data['over_size_flag'] = substr($data['tag_id'], 0, 1) == 1 ? 1 : 0;
        }
        if ($data['combo_flag'] == 1) {
            //包裹
            $data['package_quantity'] = $this->getComboProductChildrenAmount($data['product_id']);
        } else {
            $data['package_quantity'] = 1;
        }
        $data['qty_avaliable'] = $this->getComboProductAvailableAmount($data['product_id'], $data['combo_flag']);
        $category = $this->getCategoryByProductId($data['product_id']);
        $categoryLine = '';
        if (is_array($category) && is_array(end($category)) && !empty(end($category)['arr_label']) && is_array(end($category)['arr_label'])) {
            $categoryLine = html_entity_decode(end(end($category)['arr_label']));
        }

        /** @var ProductExts $productExts */
        $productExts = ProductExts::query()->where('product_id', $productId)->first();
        $countryCodeNameMap = \App\Models\Customer\Country::query()->get()->pluck('name', 'iso_code_3')->toArray();

        $line = [
            app(CustomerRepository::class)->getCustomerNumber($customerId),
            html_entity_decode($data['screenname']),
            $data['item_code'],
            $categoryLine,
            html_entity_decode($data['product_name']),
            $data['combo_flag'] == 1 ? '0' : sprintf('%.2f', $data['length']),
            $data['combo_flag'] == 1 ? '0' : sprintf('%.2f', $data['width']),
            $data['combo_flag'] == 1 ? '0' : sprintf('%.2f', $data['height']),
            $data['combo_flag'] == 1 ? '0' : sprintf('%.2f', $data['weight']),
            $data['unit_price'],
            $data['home_pick_freight_per'],
            $data['drop_ship_freight_per'],
            $data['qty_avaliable'],
            $data['over_size_flag'] == 1 ? 'Yes' : 'No',
            $data['combo_flag'] == 1 ? 'Yes' : 'No',
            $data['package_quantity'],
            $productExts && $productExts->is_customize ? 'Yes' : 'No',
            $productExts && isset($countryCodeNameMap[$productExts->origin_place_code]) ? $countryCodeNameMap[$productExts->origin_place_code] : '',
        ];

//        $warehouseCount = count($warehouse);
//        for ($i = 0; $i < $warehouseCount; $i++) {
//            $line[] = '';
//        }
        $option = app(ProductOptionRepository::class)->getProductOptionByProductId($data['product_id']);

        $line[] = html_entity_decode($option['color_name']);
        $line[] = html_entity_decode($option['material_name']);
        $filler = '';
        if ($productExts && $productExts->filler) {
            $filler = DB::table('oc_option_value_description')->where('option_value_id', $productExts->filler)->value('name');
        }
        $line[] = $filler;
        $line[] = empty($data['danger_flag']) ? 'No' :'Yes';
        // 自定义字段
        $line = array_merge($line, $informationCustomFieldValues);
        $line[] = $productExts ? $this->formatAssembleField($productExts->assemble_length, false) : '';
        $line[] = $productExts ? $this->formatAssembleField($productExts->assemble_width, false) : '';
        $line[] = $productExts ? $this->formatAssembleField($productExts->assemble_height, false) : '';
        $line[] = $productExts ? $this->formatAssembleField($productExts->assemble_weight, false) : '';
        // 自定义字段
        $line = array_merge($line, $assembledCustomFieldValues);
        $line[] = (function($text) {
            // 去除页面中不可见的元素
            $removeTags = ['style', 'script', 'head', 'title', 'meta'];
            foreach ($removeTags as $tag) {
                $text = preg_replace("/<{$tag}.*<\/{$tag}>/is", '', $text);
            }
            // 手动剔除所有style的字段，因为旧数据存在以下这种非正常 html
            // <h3 style="font-family: " open="" sans",="" sans-serif;="" color:="" rgb(0,="" 0,="" 0);"="">
            $text = preg_replace('/ style=".*?>/i', '>', $text);
            $blockTags = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'div'];
            $str = strip_tags(
                str_replace(
                    array_map(function ($tag) {return "</{$tag}>";}, $blockTags),
                    array_map(function ($tag) {return "\n</{$tag}>";}, $blockTags),
                    html_entity_decode($text)
                )
            );
            return mb_substr($str, 0, 32000);
        })($data['description']);
        foreach ($this->getProductImages($data['product_id'], 9) as $item) {
            try {
                // 使用b2b的oss域名
                if (getenv('B2B_OSS_DOMAIN')) {
                    $line[] = rtrim(getenv('B2B_OSS_DOMAIN'), '/') . '/image/' . ltrim($item, '/');
                } else {
                    $line[] = Storage::cloud()->url('image/' . $item);
                }
            } catch (\Exception $e) {
                LoggerHelper::logPackZip('获取csv链接 ' . $e->getMessage());
            }
        }
        fputcsv($fp, $line);
        fclose($fp);
    }

    /**
     * 获取产品分类名称
     * @param $productId
     * @return mixed|string
     */
    public function getCategoryNameByProductId($productId)
    {
        $category = DB::table('oc_product_to_category')->where('product_id', $productId)->first();
        if (empty($category)) {
            return '';
        }
        return CategoryDescription::query()->where('category_id', $category->category_id)->value('name');
    }

    /**
     * @param $assembleField
     * @param bool $isShowEmptyVal
     * @return string
     */
    private function formatAssembleField($assembleField, bool $isShowEmptyVal = true)
    {
        if ($assembleField == -1.00) {
            return 'Not Applicable';
        }

        if (empty($assembleField) && $isShowEmptyVal) {
            return 'Seller maintenance in progress';
        }

        return "\t" . $assembleField;
    }

    /**
     * 根据商品id获取商品所属目录
     *
     * @param int|null $productId
     * @return array
     */
    public function getCategoryByProductId(?int $productId): array
    {
        if (!$productId) return [];
        // 通过product id 获取 category id
        $category = DB::table('oc_product_to_category')
            ->where(['product_id' => $productId])
            ->pluck('category_id');
        $category = $category->map(function ($cId) {
            $temp = [];
            $arrId = [];
            $this->getCategoryInfoByCategoryId($cId, $temp, $arrId);
            array_walk($temp, function (&$item) {
                $item = html_entity_decode($item['name']) ?? '';
            });
            array_walk($arrId, function (&$ite) {
                $ite = $ite['category_id'] ?? '';
            });

            return ['value' => $cId, 'arr_label' => $temp, 'count' => count($temp), 'arr_id' => $arrId];
        });

        return $category->toArray();
    }

    /**
     * @param int|null $categoryId
     * @param array $init
     * @param array $arrId
     */
    protected function getCategoryInfoByCategoryId(?int $categoryId, array &$init = [], array &$arrId = [])
    {
        if ($categoryId === 0 || !$categoryId) return;
        static $categoryList = [];
        static $flag = false;
        if (!$flag) {
            $categoryList = DB::table('oc_category as c')
                ->leftJoin('oc_category_description as cd','cd.category_id','=','c.category_id')
                ->select(['c.parent_id', 'cd.name', 'c.category_id'])
                ->get()
                ->map(function($item){return (array)$item;})
                ->keyBy('category_id')
                ->toArray();
            $flag = true;
        }
//        print_r($categoryList);die;
//        dd($categoryList);
        if (isset($categoryList[$categoryId])) {
            $tempArr = $categoryList[$categoryId];
            array_unshift($init, $tempArr);
            array_unshift($arrId, $tempArr);
            $this->getCategoryInfoByCategoryId($tempArr['parent_id'], $init, $arrId);
        }
        return;
    }

    /*
     * 获取各国对应的仓库代码
     * */
    public function getWarehouseCodeByCountryId($countryId)
    {

        return DB::table('tb_warehouses')
            ->where('country_id', $countryId)
            ->orderBy('WarehouseCode')
            ->pluck('WarehouseCode', 'WarehouseID')
            ->toArray();
    }

    protected function checkIsEuropeanSpecialBuyer($groupId)
    {
        $mapGroupName['name'] = 'European special buyer';
        $need_group_id = DB::table('oc_customer_group_description')->where($mapGroupName)->value('customer_group_id');
        if ($groupId == $need_group_id) {
            return true;
        }
        return false;
    }

    /**
     * [getComboProductAvailableAmount description] 获取combo下的各个子产品数量和库存数
     * @param int $product_id
     * @param int $flag combo_flag
     * @param int $use_batch 是否使用批次库存计算
     * @return array
     */
    public function getComboProductAvailableAmount($product_id, $flag, $use_batch = 0)
    {
        // 保持页面和导出数量一致combo品不需要计算
        if ($use_batch == 0) {
            $flag = 0;
        }
        $data = 0;
        if ($flag == 0) {
            $map = [
                ['product_id', '=', $product_id],
            ];
            if ($use_batch == 1) {
                $data = DB::table('tb_sys_batch')->where($map)->sum('onhand_qty');
            } else {
                $data = DB::table('oc_product')->where($map)->sum('quantity');
            }
        } elseif ($flag == 1) {
            //含有包裹
            $map = [
                ['i.product_id', '=', $product_id],
            ];
            $data = DB::table('tb_sys_product_set_info as i')
                ->leftJoin('tb_sys_batch as b', 'b.product_id', '=', 'i.set_product_id')
                ->where($map)->whereNotNull('i.set_product_id')
                ->groupBy('b.product_id', 'child_amount')->select(["i.qty as child_amount", 'b.product_id'])
                ->selectRaw('sum(b.onhand_qty) as all_qty')->get();
            $data = json_decode(json_encode($data), true);
            $listMin = [];
            foreach ($data as $value) {
                $listMin[] = floor($value['all_qty'] / $value['child_amount']);
            }
            if (empty($listMin)) {
                $data = 0;
            } else {
                $data = (int)min($listMin);
            }
        }
        return $data;
    }


    /**
     * [getProductQuoteFlag description]
     * @param $product_id
     * @return int
     */
    protected function getProductQuoteFlag($product_id)
    {
        $res = DB::table('oc_wk_pro_quote_details AS pd')
            ->leftJoin('oc_wk_pro_quote AS pq', ['pd.seller_id' => 'pq.seller_id'])
            ->where([
                'pd.product_id' => $product_id,
                'pq.status' => 0,
            ])
            ->first();

        return $res ? 1 : 0;
    }

    /**
     * 获取所有可用的产品
     * @param null $type
     * @param null $productGt
     * @return mixed
     */
    public function getAllActiveProducts($type = null,$productGt=null)
    {
        $build = DB::table('oc_product as op')
            ->leftJoin('oc_customerpartner_to_product as cp', 'cp.product_id', '=', 'op.product_id');
        if ($type === 'packed') {
            $build->leftJoin('oc_product_description as pd', 'pd.product_id', '=', 'op.product_id')
                ->where('pd.packed_zip_path', '!=', '');

        }
        if ($type === 'no_packed') {
            $build->leftJoin('oc_product_description as pd', 'pd.product_id', '=', 'op.product_id')
                ->where('pd.packed_zip_path', '=', '');

        }
        if (is_numeric($type)) {
            $build->where('op.product_id', $type);
        }
        if ($productGt) {
            $build->where('op.product_id', '>', $productGt);
        }
        $products = $build->where('status', 1)
            ->where('is_deleted', 0)
            ->where('product_type', 0)
            ->get(['cp.product_id', 'cp.customer_id'])->toArray();
        return json_decode(json_encode($products), true);
    }

    /**
     * 获取当前复杂交易的产品
     * @return array
     */
    public function getComplexTransactionProductIds():array
    {
        $res_margin = DB::connection('mysql_proxy')->table('tb_sys_margin_template as mt')
            ->leftJoin('oc_customerpartner_to_product AS c2p', 'c2p.product_id', '=', 'mt.product_id')
            ->leftJoin('oc_customer AS c', 'c.customer_id', '=', 'c2p.customer_id')
            ->selectRaw('mt.product_id')
            ->where('is_del', '=', '0')
            ->groupBy(['mt.product_id'])
            ->pluck('product_id')
            ->toArray();

        $res_rebate = DB::connection('mysql_proxy')->table('oc_rebate_template_item AS rti')
            ->leftJoin('oc_rebate_template AS rt', 'rt.id', '=', 'rti.template_id')
            ->leftJoin('oc_customerpartner_to_product AS c2p', 'c2p.product_id', '=', 'rti.product_id')
            ->leftJoin('oc_customer AS c', 'c.customer_id', '=', 'c2p.customer_id')
            ->where('rti.is_deleted', 0)
            ->where('rt.is_deleted', 0)
            ->selectRaw('rti.product_id')
            ->groupBy(['rti.product_id'])
            ->pluck('product_id')
            ->toArray();

        $res_futures =DB::connection('mysql_proxy')->table('oc_futures_contract as fc')
            ->leftJoin('oc_customerpartner_to_product AS c2p', 'c2p.product_id', '=', 'fc.product_id')
            ->leftJoin('oc_customer AS c', 'c.customer_id', '=', 'c2p.customer_id')
            ->where(
                [
                    'fc.status' => 1,
                    'fc.is_deleted' => 0,
                ]
            )
            ->selectRaw('fc.product_id')
            ->groupBy(['fc.product_id'])
            ->pluck('product_id')
            ->toArray();
        return  array_unique(array_merge($res_margin,$res_rebate,$res_futures)) ?? [];
    }
}