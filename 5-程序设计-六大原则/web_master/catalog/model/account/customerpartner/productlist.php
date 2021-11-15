<?php

use App\Components\Storage\StorageCloud;
use Carbon\Carbon;
use Illuminate\Database\Query\Expression;

/**
 * Class ModelAccountCustomerpartnerProductList
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountCustomerpartnerProductGroup $model_Account_Customerpartner_ProductGroup
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 * @property ModelCustomerpartnerMail $model_customerpartner_mail
 * @property ModelCustomerpartnerProductManage $model_customerpartner_product_manage
 * @property ModelExtensionModuleProductShow $model_extension_module_product_show
 * @property ModelToolImage $model_tool_image
 */
class ModelAccountCustomerpartnerProductList extends Model
{
    protected $customer_id;
    /** @var ModelAccountCustomerpartner $modelCustomerpartner */
    protected $modelCustomerpartner;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        $this->load->model('account/customerpartner');
        $this->modelCustomerpartner = $this->model_account_customerpartner;
    }

    /**
     * @param $fileInfo
     * @param int $customer_id
     * @return array
     * @deprecated
     */
    public function saveTemplateFile($fileInfo,$customer_id)
    {

        $fileName = $fileInfo->getClientOriginalName();
        $fileType = $fileInfo->getClientOriginalExtension();
        $dateTime = date("YmdHis");
        $dateDir = date('Y-m-d', time());
        $realFileName =  $dateTime . '_' . token(10) . '.' . $fileType;
        $filePath = StorageCloud::newProductCsv()->writeFile($fileInfo, $dateDir,$realFileName);
        $fileData = [
            "file_name" => str_replace(' ', '', $fileName),
            "size" => $fileInfo->getSize(),
            "file_path" => $filePath,
            'deal_file_path' => StorageCloud::newProductCsv()->getUrl(StorageCloud::newProductCsv()->getRelativePath($filePath)),
            'create_user_name' => $customer_id,
            'create_time' => Carbon::now(),
        ];
        $id = $this->orm->table('tb_sys_customer_new_product_csv')->insertGetId($fileData);
        $final['file_path'] = $fileData['file_path'];
        $final['id'] = $id;
        return $final;
    }

    /**
     * [verifyNewProductInfo description] 产品新增/更新
     * @param $data
     * @return string
     */
    public function verifyNewProductInfo($data)
    {
        //1.验证必填项
        //2.category 不做强制验证，正确就更新，错误则不更新
        //3.更新MPN和店铺联查
        $mpn = [];
        $combo_category = [];
        $combo_category_key = [];
        $combo_all = [];
        foreach ($data as $key => $value) {
            //验证栏位满足要求
            $res = $this->verifyNewProductDataColumn($value, $key + 2);
            if ($res !== true) {
                $err = $res;
                return $err;
            }
            if (strtolower(trim($value['*Product type(General Item/Combo Item/Replacement Part)'])) == 'combo item') {
                // 子产品为combo 需要报错
                $res = $this->orm->table(DB_PREFIX.'product as p')
                    ->leftJoin(DB_PREFIX.'customerpartner_to_product as ctp','ctp.product_id','=','p.product_id')
                    ->where([
                        'p.mpn' => trim($value['Sub-items']),
                        'ctp.customer_id' => $this->customer->getId(),
                    ])
                    ->select('p.length', 'p.width', 'p.height', 'p.weight', 'p.buyer_flag', 'p.part_flag', 'p.status','p.combo_flag','p.mpn')
                    ->get()
                    ->map(
                        function ($vt) {
                            return (array)$vt;
                        })
                    ->toArray();
                if($res){
                    // 子产品为已存在的产品
                    //验证查询出来的数据与实际是否一致 1允许更新
                    $child_size_flag = $this->compareChildProductDataWithReality($value, current($res), $key + 2);
                    if($child_size_flag != 1){
                        return $child_size_flag;
                    }
                    if($res[0]['combo_flag'] == 1){
                        $err = 'The Sub-items is combo Item.Please check the input of Sub-items field.Number of error lines: '.($key + 2).'.';
                        return $err;
                    }
                }

                $mpn[$key . '_mpn_1'] = $value['*MPN'];
                $mpn[$key . '_sub-items_2'] = $value['Sub-items'];
                if(!isset($combo_category[$value['*MPN']])){
                    $combo_category[$value['*MPN']] = trim($value['Categories']);
                    $combo_category_key[$value['*MPN']] = $key + 2;
                }else{
                    if(trim($value['Categories']) != $combo_category[$value['*MPN']]){
                        $err = 'The MPN is combo Item.Please check the input of Categories field.Number of error lines: '.$combo_category_key[$value['*MPN']].','.($key + 2).'.';
                        return $err;

                    }
                }
                $combo_all[$value['*MPN']][$key + 2] = $value;

            } else {
                $mpn[$key . '_mpn_0'] = $value['*MPN'];
            }
        }
        foreach ($mpn as $key => $value) {
            $flag = $this->searchTheSame($value, $mpn);

            if ($flag !== false) {
                $err = 'Found duplicate items,the duplicate line number is ' . $flag;
                return $err;
            }

        }
        // 校验子产品至少有2个
        $combo_valid = true;
        foreach ($combo_all as $k => $v) {
            if (array_sum(array_column($v, 'Sub-items Quantity')) < 2) {
                $combo_valid = false;
            }
        }
        if (!$combo_valid){
            $err = 'Sub-item quantity must greater than 1.';
            return $err;
        }

        foreach($combo_all as $key => $value){
            $str = '';
            $str_compare = '';
            $k_first = '';
            if(count($value) > 1){
                foreach($value as $ks => $vs){
                    if($str == ''){
                        $str = $vs['*MPN'].'_'.$vs['*Product Name'].'_'.$vs['*Status(Available/Unavailable)'].'_'.$vs['Categories'];
                        $k_first  = $ks;
                    }else{
                        $str_compare = $vs['*MPN'].'_'.$vs['*Product Name'].'_'.$vs['*Status(Available/Unavailable)'].'_'.$vs['Categories'];
                        if(strtolower($str) != strtolower($str_compare)){
                            $err = 'The MPN is combo Item.Please check the input of all field.Number of error lines: ' .$k_first.','.$ks;
                            return $err;
                        }

                    }
                }

            }
        }
        // 验证更新还是插入
        foreach ($data as $key => $value) {

            $tmp = $this->getProductUpdateOrAdd($value, $key + 2);
            if ($tmp !== 0 && $tmp !== 1) {
                $err = $tmp;
                return $err;
            } else {
                $data[$key]['action'] = $tmp;
            }
        }
        return $data;
    }

    public function getProductUpdateOrAdd($info, $index)
    {
        if ($info['Sub-items']) {
            //combo
            $res = $this->orm->table(DB_PREFIX . 'customerpartner_to_product as ctp')
                ->leftJoin('tb_sys_product_set_info as s', 's.product_id', '=', 'ctp.product_id')
                ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 's.set_product_id')
                ->select('p.length', 'p.width', 'p.height', 'p.weight', 'p.buyer_flag', 'p.part_flag', 'p.status','p.combo_flag','p.mpn')
                ->where([
                    's.mpn' => $info['*MPN'],
                    's.set_mpn' => $info['Sub-items'],
                    'ctp.customer_id' => $this->customer_id,
                ])
                ->first();

            $res = obj2array($res);

            if (!$res) {
                // 查询是否系统内有combo
                $res_parent = $this->orm->table(DB_PREFIX . 'customerpartner_to_product as ctp')
                    ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'ctp.product_id')
                    //->select('p.length', 'p.width', 'p.height', 'p.weight', 'p.buyer_flag', 'p.part_flag', 'p.status', 'p.combo_flag')
                    ->where([
                        'p.mpn' => $info['*MPN'],
                        'ctp.customer_id' => $this->customer_id,
                      //  'p.combo_flag' => 1,
                      // 'p.part_flag' => 0,

                    ])
                    ->select('p.combo_flag','p.product_type')
                    ->first();
                if ($res_parent && $res_parent->product_type != 0 && $res_parent->product_type != 3){
                     return 'Deposit product can not be edited.';
                }
                $res_parent = $res_parent ? $res_parent->combo_flag : null;
                // 查询此子sku 是否更改尺寸
                $tmp_child = $this->orm->table(DB_PREFIX . 'customerpartner_to_product as ctp')
                    ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'ctp.product_id')
                    ->select('p.length', 'p.width', 'p.height', 'p.weight', 'p.buyer_flag', 'p.part_flag', 'p.status', 'p.combo_flag')
                    ->where([
                        'p.mpn' => $info['Sub-items'],
                        'ctp.customer_id' => $this->customer_id,
                        //  'p.combo_flag' => 1,
                        // 'p.part_flag' => 0,

                    ])
                    ->get()
                    ->map(
                        function ($vt) {
                            return (array)$vt;
                        })
                    ->toArray();
                if($tmp_child){
                    //验证查询出来的数据与实际是否一致 1允许更新 2不允许更新 不需要校验
                    $tmp_ret = $this->compareComboDataWithReality($info, current($tmp_child), $index);
                    if($tmp_ret !== 1){
                        return $tmp_ret;
                    }
                }


                if ($res_parent == 1) {
                    return 1;  //允许更改
                }elseif($res_parent == 0 && $res_parent !== null){
                    return 'Product type do not allow updating.Please contact your account manager. Number of error lines: ' . $index . '.';
                }
                return 0; // 新增

            } else {
                //验证查询出来的数据与实际是否一致 1允许更新 2不允许更新 不需要校验
                return $this->compareComboDataWithReality($info, $res, $index);
            }

        } else {
            //非combo
            $res = $this->orm->table(DB_PREFIX . 'customerpartner_to_product as ctp')
                ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'ctp.product_id')
                ->select([
                    'p.length', 'p.width', 'p.height', 'p.weight',
                    'p.buyer_flag', 'p.part_flag', 'p.status', 'p.combo_flag',
                    'p.product_type'
                ])
                ->where([
                    'p.mpn' => $info['*MPN'],
                    'ctp.customer_id' => $this->customer_id,
                ])
                ->first();
            $res = obj2array($res);
            if (!$res) {
                return 0; // 新增
            } else {
                //验证查询出来的数据与实际是否一致 1允许更新 2不允许更新
                return $this->compareDataWithReality($info, $res, $index);
            }
        }

    }

    public function compareDataWithReality($info, $res, $index)
    {

        $length = sprintf('%.2f', $res['length']);
        $width = sprintf('%.2f', $res['width']);
        $height = sprintf('%.2f', $res['height']);
        $weight = sprintf('%.2f', $res['weight']);
        $length_csv = sprintf('%.2f', $info['*Length']);
        $width_csv = sprintf('%.2f', $info['*Width']);
        $height_csv = sprintf('%.2f', $info['*Height']);
        $weight_csv = sprintf('%.2f', $info['*Weight']);
        if ($length != $length_csv) {
            return 'Length do not allow updating.Please contact your account manager. Number of error lines: ' . $index . '.'; //不允许更新
        }

        if ($width != $width_csv) {
            return 'Width do not allow updating.Please contact your account manager. Number of error lines: ' . $index . '.'; //不允许更新
        }

        if ($height != $height_csv) {
            return 'Height do not allow updating.Please contact your account manager. Number of error lines: ' . $index . '.'; //不允许更新
        }

        if ($weight != $weight_csv) {
            return 'Weight do not allow updating.Please contact your account manager. Number of error lines: ' . $index . '.'; //不允许更新
        }

        $product_type = 2;

        if ($res['combo_flag']) {
            $product_type = 0;
        }
        // product_type
        if ($res['product_type'] != 0 && $res['product_type'] != 3){
            return 'Deposit product can not be edited.';
        }
        if ($res['part_flag'] == 1) {
            $product_type = 1;
        }
        if (strtolower(trim($info['*Product type(General Item/Combo Item/Replacement Part)'])) == 'combo item') {
            if ($product_type == 0) {
                return 1;
            } else {
                return 'Product type do not allow updating.Please contact your account manager. Number of error lines: ' . $index . '.'; //不允许更新
            }
        } elseif (strtolower(trim($info['*Product type(General Item/Combo Item/Replacement Part)'])) == 'replacement part') {
            if ($product_type == 1) {
                return 1;
            } else {
                return 'Product type do not allow updating.Please contact your account manager. Number of error lines: ' . $index . '.'; //不允许更新
            }
        } elseif (strtolower(trim($info['*Product type(General Item/Combo Item/Replacement Part)'])) == 'general item') {
            if ($product_type == 2) {
                return 1;
            } else {
                return 'Product type do not allow updating.Please contact your account manager. Number of error lines: ' . $index . '.'; //不允许更新
            }
        }
        return 1;


    }

    public function compareComboDataWithReality($info, $res, $index)
    {
        $length = sprintf('%.2f', $res['length']);
        $width = sprintf('%.2f', $res['width']);
        $height = sprintf('%.2f', $res['height']);
        $weight = sprintf('%.2f', $res['weight']);
        $length_csv = sprintf('%.2f', $info['*Length']);
        $width_csv = sprintf('%.2f', $info['*Width']);
        $height_csv = sprintf('%.2f', $info['*Height']);
        $weight_csv = sprintf('%.2f', $info['*Weight']);
        if ($length != $length_csv) {
            return 'Length do not allow updating.Please contact your account manager. Number of error lines: ' . $index . '.'; //不允许更新
        }

        if ($width != $width_csv) {
            return 'Width do not allow updating.Please contact your account manager. Number of error lines: ' . $index . '.'; //不允许更新
        }

        if ($height != $height_csv) {
            return 'Height do not allow updating.Please contact your account manager. Number of error lines: ' . $index . '.'; //不允许更新
        }

        if ($weight != $weight_csv) {
            return 'Weight do not allow updating.Please contact your account manager. Number of error lines: ' . $index . '.'; //不允许更新
        }

        return 1;

    }

    public function compareChildProductDataWithReality($info, $res, $index)
    {
        $length = sprintf('%.2f', $res['length']);
        $width = sprintf('%.2f', $res['width']);
        $height = sprintf('%.2f', $res['height']);
        $weight = sprintf('%.2f', $res['weight']);
        $length_csv = sprintf('%.2f', $info['*Length']);
        $width_csv = sprintf('%.2f', $info['*Width']);
        $height_csv = sprintf('%.2f', $info['*Height']);
        $weight_csv = sprintf('%.2f', $info['*Weight']);
        if ($length != $length_csv) {
            return 'Length do not allow updating.Please contact your account manager. Number of error lines: ' . $index . '.'; //不允许更新
        }

        if ($width != $width_csv) {
            return 'Width do not allow updating.Please contact your account manager. Number of error lines: ' . $index . '.'; //不允许更新
        }

        if ($height != $height_csv) {
            return 'Height do not allow updating.Please contact your account manager. Number of error lines: ' . $index . '.'; //不允许更新
        }

        if ($weight != $weight_csv) {
            return 'Weight do not allow updating.Please contact your account manager. Number of error lines: ' . $index . '.'; //不允许更新
        }

        return 1;

    }

    public function searchTheSame($data, $arr)
    {
        $res = [];
        $all = [];
        $k = [];
        $combo = [];
        foreach ($arr as $key => $value) {
            if ($data == $value) {
                $res[] = $key;
            }
        }
        if (count($res) == 1) {
            return false;
        } else {
            // 处理，看是不是combo
            foreach ($res as $key => $value) {
                $tmp = explode('_', $value);
                $all[] = $tmp[2];
                $k[] = $tmp[0] + 2;
                if($tmp[2] == 2){
                    $combo[] = $arr[$tmp[0].'_mpn_1'];
                }
            }

            if (count(array_unique($all)) == 1) {
                //此时此刻还不能够定义
                if($all[0] == 1 ){
                    return false;
                }elseif ($all[0] == 0){
                    return implode(',',array_unique($k));
                }else{
                    // combo的子sku相同
                    if(count($combo) != count(array_unique($combo))){
                        $list = array_combine($k,$combo);
                        $combo_res = $this->getRepeatArrayKey($list);
                        return implode(',', $combo_res);
                    }else{
                        return false;
                    }

                }
            } else {
                return implode(',', array_unique($k));
            }
        }

    }

    public function getRepeatArrayKey($arr){
        $key_arr =[];
        $result_key = [];
        foreach ($arr as $k => $v) {
            if(in_array($v,$key_arr)){
                $result_key[] = array_search($v,$key_arr);
                $result_key[] = $k;
            }else{
                $key_arr[$k] = $v;
            }
        }
        return array_unique($result_key);
    }

    /**
     * [verifyNewProductDataColumn description]
     * @param $data
     * @param $index
     * @return string|boolean
     */
    public function verifyNewProductDataColumn($data, $index)
    {
        $reg = '/^(\d+)(\.\d{0,2})?$/';
        if ($data['*MPN'] == '') {
            return 'MPN can not be left blank. Number of error lines: ' . $index . '.';
        }

        if(mb_strlen($data['*MPN']) < 3 || mb_strlen($data['*MPN']) > 64 ){
            return 'MPN must be greater than 3 and less than 64 characters. Number of error lines: ' . $index . '.';
        }

        if ($data['*Product Name'] == '') {
            return 'Product Name can not be left blank. Number of error lines: ' . $index . '.';
        }
        if(mb_strlen($data['*Product Name']) > 200 ){
            return 'Product Name must be greater than 1 and less than 200 characters. Number of error lines: ' . $index . '.';
        }

        if ($data['*Product type(General Item/Combo Item/Replacement Part)'] == '') {
            return 'Product type(General Item/Combo Item/Replacement Part) can not be left blank. Number of error lines: ' . $index . '.';
        }

        if (!preg_match($reg, trim($data['*Length']))) {
            return 'Length only allows to have maximum two decimal places. Number of error lines: ' . $index . '.';
        }

        if (!preg_match($reg, trim($data['*Width']))) {
            return 'Width only allows to have maximum two decimal places. Number of error lines: ' . $index . '.';
        }

        if (!preg_match($reg, trim($data['*Height']))) {
            return 'Height only allows to have maximum two decimal places. Number of error lines: ' . $index . '.';
        }

        if (!preg_match($reg, trim($data['*Weight']))) {
            return 'Weight only allows to have maximum two decimal places. Number of error lines: ' . $index . '.';
        }

        if (strtolower(trim($data['*Status(Available/Unavailable)'])) != 'available' && strtolower(trim($data['*Status(Available/Unavailable)'])) != 'unavailable') {
            return '*Status(Available/Unavailable) only allows to fill in Available/Unavailable. Number of error lines: ' . $index . '.';
        }

        if (strtolower(trim($data['*Product type(General Item/Combo Item/Replacement Part)'])) != 'general item' && strtolower(trim($data['*Product type(General Item/Combo Item/Replacement Part)'])) != 'combo item' && strtolower(trim($data['*Product type(General Item/Combo Item/Replacement Part)'])) != 'replacement part') {
            return '*Product type(General Item/Combo Item/Replacement Part) only allows to fill in General Item/Combo Item/Replacement Part. Number of error lines: ' . $index . '.';
        }

        if (strtolower(trim($data['*Product type(General Item/Combo Item/Replacement Part)'])) == 'combo item') {

            if ($data['Sub-items'] == '') {
                return 'Sub-items can not be left blank. Number of error lines: ' . $index . '.';
            }

            if(mb_strlen($data['Sub-items']) < 3 || mb_strlen($data['Sub-items']) > 64 ){
                return 'Sub-items must be greater than 3 and less than 64 characters. Number of error lines: ' . $index . '.';
            }

            if ($data['Sub-items Quantity'] == '' || !preg_match('/^[0-9]*$/', $data['Sub-items Quantity'])) {

                if($data['Sub-items Quantity'] == '0'){
                    return "Sub-items Quantity don't allows to fill in 0. Number of error lines:" . $index . '.';
                }
                return 'Sub-items Quantity only allows to fill in numbers. Number of error lines: ' . $index . '.';
            }

        } else {

            if ($data['Sub-items'] || $data['Sub-items Quantity']) {
                return 'The MPN is General Item/Replacement Part. Please check the input of Sub-items field.Number of error lines: ' . $index . '.';
            }
        }

        if($data['Categories'] != ''){

            $ret = $this->dealWithCategory($data['Categories']);
            foreach($ret as $kt => $vt){
                $category_id = $this->orm->table(DB_PREFIX . 'category_description')->where('name', trim($vt))->value('category_id');
                if(!$category_id){
                    return 'The Categories is not exist. Please check the input of Categories field.Number of error lines: ' . $index . '.';
                }
            }

        }

        return true;


    }

    public function dealWithCategory($info){
        $ret = [];
        $list = explode(';',$info);
        foreach($list as $key => $value){
            $category_name = str_ireplace('&', '&amp;', $value);
            $ret[] = $category_name;
        }
        return $ret;
    }


    /**
     * [addOrUpdateProduct description]
     * @param $data
     * @return void
     */
    public function addOrUpdateProduct($data)
    {
        //更新 or 新增
        //data 中 为edit的需要一个
        $add_list = [];
        $combo_list = [];
        foreach ($data as $key => $value) {
            //粗略的查询保证更新或新增操作正确
            if ($value['action'] == 0) {
                $product_id = $this->addProduct($value, $add_list);
                $add_list[$value['*MPN']] = $product_id;
            } else {
                $product_id = $this->editProduct($value, $combo_list);
                if ($value['Sub-items']) {
                    $combo_list[$value['*MPN']] = $product_id;
                }
            }


        }
        // 更新完成 删除set_info中冗余的数据
        foreach ($combo_list as $key => $value) {
            $this->orm->table('tb_sys_product_set_info')->where([
                'product_id' => $value,
                'is_edit' => 0,
            ])->delete();
        }

    }

    /**
     * [addProduct description]
     * @param $data
     * @param $compare_list
     * @return int|string
     * @throws Exception
     */
    public function addProduct($data, $compare_list)
    {
        $status = strtolower(trim($data['*Status(Available/Unavailable)'])) == 'available' ? 1 : 0;
        // combo or 非 combo
        if ($data['Sub-items']) {
            // combo
            // 新增产品信息
            if (!isset($compare_list[$data['*MPN']])) {
                $product_id = $this->orm->table(DB_PREFIX . 'customerpartner_to_product as ctp')
                    ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'ctp.product_id')
                    ->where([
                        'p.mpn' => $data['*MPN'],
                        'ctp.customer_id' => $this->customer_id,
                    ])
                    ->value('ctp.product_id');
                if(!$product_id){

                    $info = [
                        'combo_flag' => 1,
                        'buyer_flag' => 1,
                        'model' => $this->orm->table(DB_PREFIX . 'customerpartner_to_customer')->where('customer_id', $this->customer_id)->value('screenname'),
                        'part_flag' => 0,
                        'sku' => trim($data['*MPN']),
                        'mpn' => trim($data['*MPN']),
                        'upc' => '',
                        'ean' => '',
                        'jan' => '',
                        'isbn' => '',
                        'asin' => '',
                        'location' => '',
                        'quantity' => 0,
                        'stock_status_id' => 0,
                        'manufacturer_id' => 0,
                        'shipping' => 1,
                        'points' => 0,
                        'tax_class_id' => 0,
                        'price' => '0.00',
                        'date_available' => date('Y-m-d H:i:s', time()),
                        'weight' => '0.00',
                        'weight_class_id' => 5,
                        'length' => '0.00',
                        'width' => '0.00',
                        'height' => '0.00',
                        'length_class_id' => 3,
                        'subtract' => 1,
                        'minimum' => 1,
                        'sort_order' => 1,
                        'status' => $status,
                        'viewed' => 0,
                        'date_added' => date('Y-m-d H:i:s', time()),
                        'date_modified' => date('Y-m-d H:i:s', time()),
                    ];
                    $product_id = $this->orm->table(DB_PREFIX . 'product')->insertGetId($info);
                    $associate = [
                        'product_id'=> $product_id,
                        'associate_product_id'=> $product_id,
                    ];
                    $this->orm->table(DB_PREFIX . 'product_associate')->insert($associate);
                    //添加combo标签关系
                    $save_combo = [
                        'product_id' => $product_id,
                        'tag_id' => 3,  // combo
                        'is_sync_tag' => 0,
                        'create_user_name' => $this->customer_id,
                        'create_time' => date('Y-m-d H:i:s', time()),
                        'update_user_name' => $this->customer_id,
                        'program_code' => 'add product',

                    ];
                    $this->orm->table(DB_PREFIX . 'product_to_tag')->insertGetId($save_combo);
                    //大建云seller sku生成规则W2..+S00001
                    $accounting_type = $this->customer->getAccountType();
                    if ($accounting_type == 2) {
                        $this->load->model('account/customerpartner');
                        $comboNum = $this->db->query("select count(1) as comboNum from oc_product op  LEFT JOIN oc_customerpartner_to_product ctp on ctp.product_id =op.product_id  where op.combo_flag = 1 and ctp.customer_id = " . $this->customer->getId())->row['comboNum'];
                        $storeName = $this->db->query("select CONCAT(oc.firstname,oc.lastname) as storeName from oc_customer oc  where  oc.customer_id = " . $this->customer->getId())->row['storeName'];
                        $this->model_account_customerpartner->outerSellerCombo($comboNum, $storeName, $this->customer_id, $product_id);
                    }

                }

            } else {
                $product_id = $compare_list[$data['*MPN']];
            }

            // combo 验证子sku是否可以插入
            $res = $this->orm->table(DB_PREFIX . 'customerpartner_to_product as ctp')
                ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'ctp.product_id')
                ->where([
                    'p.mpn' => $data['Sub-items'],
                    'ctp.customer_id' => $this->customer_id,
                ])
                ->value('p.product_id');
            if (!$res) {
                //新增子sku 获取子sku的product_id
                $info_child = [
                    'combo_flag' => 0,
                    'buyer_flag' => 0,  //子sku 都是不可单独售卖
                    'model' => $this->orm->table(DB_PREFIX . 'customerpartner_to_customer')->where('customer_id', $this->customer_id)->value('screenname'),
                    'part_flag' => 0,
                    'sku' => trim($data['Sub-items']),
                    'mpn' => trim($data['Sub-items']),
                    'upc' => '',
                    'ean' => '',
                    'jan' => '',
                    'isbn' => '',
                    'asin' => '',
                    'location' => '',
                    'quantity' => 0,
                    'stock_status_id' => 0,
                    'manufacturer_id' => 0,
                    'shipping' => 1,
                    'points' => 0,
                    'tax_class_id' => 0,
                    'price' => '0.00',
                    'date_available' => date('Y-m-d H:i:s', time()),
                    'weight' => $data['*Weight'],
                    'weight_class_id' => 5,
                    'length' => $data['*Length'],
                    'width' => $data['*Width'],
                    'height' => $data['*Height'],
                    'length_class_id' => 3,
                    'subtract' => 1,
                    'minimum' => 1,
                    'sort_order' => 1,
                    'status' => $status,
                    'viewed' => 0,
                    'date_added' => date('Y-m-d H:i:s', time()),
                    'date_modified' => date('Y-m-d H:i:s', time()),
                ];
                $child_product_id = $this->orm->table(DB_PREFIX . 'product')->insertGetId($info_child);
                $associate = [
                    'product_id'=> $child_product_id,
                    'associate_product_id'=> $child_product_id,
                ];
                $this->orm->table(DB_PREFIX . 'product_associate')->insert($associate);
                // 不需要这些
                $this->childProductAddUpdate($child_product_id, $data);
                $this->orm->table(DB_PREFIX . 'customerpartner_to_product')->insert([
                    'customer_id' => $this->customer_id,
                    'product_id' => $child_product_id,
                    'price' => '0.00',
                    'seller_price' => '0.00',
                    'currency_code' => '',
                    'quantity' => 0,
                    'pickup_price' => 0,

                ]);
            }else{

                $child_product_id = $res;

            }
            //插入 tb_sys_product_set_info
            $save_child = [
                'set_mpn' => trim($data['Sub-items']),
                'mpn' => trim($data['*MPN']),
                'weight' => $data['*Weight'],
                'cubes' => 0,
                'height' => $data['*Height'],
                'length' => $data['*Length'],
                'width' => $data['*Width'],
                'qty' => $data['Sub-items Quantity'],
                'product_id' => $product_id,
                'set_product_id' => $child_product_id,
                'seller_id' => $this->customer_id,
            ];
            $this->orm->table('tb_sys_product_set_info')->insert($save_child);

        } else {
            // 非combo
            // 新增产品信息
            $product_id = $this->orm->table(DB_PREFIX . 'customerpartner_to_product as ctp')
                ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'ctp.product_id')
                ->where([
                    'p.mpn' => $data['*MPN'],
                    'ctp.customer_id' => $this->customer_id,
                ])
                ->value('ctp.product_id');
            if(!$product_id){
                $info = [
                    'combo_flag' => 0,
                    'buyer_flag' => 1,
                    'model' => $this->orm->table(DB_PREFIX . 'customerpartner_to_customer')->where('customer_id', $this->customer_id)->value('screenname'),
                    'part_flag' => 0,
                    'sku' => trim($data['*MPN']),
                    'mpn' => trim($data['*MPN']),
                    'upc' => '',
                    'ean' => '',
                    'jan' => '',
                    'isbn' => '',
                    'asin' => '',
                    'location' => '',
                    'quantity' => 0,
                    'stock_status_id' => 0,
                    'manufacturer_id' => 0,
                    'shipping' => 1,
                    'points' => 0,
                    'tax_class_id' => 0,
                    'price' => '0.00',
                    'date_available' => date('Y-m-d H:i:s', time()),
                    'weight' => $data['*Weight'],
                    'weight_class_id' => 5,
                    'length' => $data['*Length'],
                    'width' => $data['*Width'],
                    'height' => $data['*Height'],
                    'length_class_id' => 3,
                    'subtract' => 1,
                    'minimum' => 1,
                    'sort_order' => 1,
                    'status' => $status,
                    'viewed' => 0,
                    'date_added' => date('Y-m-d H:i:s', time()),
                    'date_modified' => date('Y-m-d H:i:s', time()),
                ];
                if (strtolower(trim($data['*Product type(General Item/Combo Item/Replacement Part)'])) == 'replacement part') {
                    $info['part_flag'] = 1;

                }
                $product_id = $this->orm->table(DB_PREFIX . 'product')->insertGetId($info);
                $associate = [
                    'product_id'=> $product_id,
                    'associate_product_id'=> $product_id,
                ];
                $this->orm->table(DB_PREFIX . 'product_associate')->insert($associate);

                //添加配件标签关系
                if ($info['part_flag'] == 1) {
                    $save_combo = [
                        'product_id' => $product_id,
                        'tag_id' => 2,  // part flag
                        'is_sync_tag' => 0,
                        'create_user_name' => $this->customer_id,
                        'create_time' => date('Y-m-d H:i:s', time()),
                        'update_user_name' => $this->customer_id,
                        'program_code' => 'add product',

                    ];
                    $this->orm->table(DB_PREFIX . 'product_to_tag')->insertGetId($save_combo);
                }
            }

        }


        // Code to add custom fields 数据表 无需处理
        // Image
        // Clone 无需处理
        if (!isset($compare_list[$data['*MPN']])) {
            $this->productAddUpdate($product_id, $data);
            // customerpartner_to_product 更新
            $this->orm->table(DB_PREFIX . 'customerpartner_to_product')->insert([
                'customer_id' => $this->customer_id,
                'product_id' => $product_id,
                'price' => '0.00',
                'seller_price' => '0.00',
                'currency_code' => '',
                'quantity' => 0,
                'pickup_price' => 0,

            ]);

            $mailData = [
                'name' => trim($data['*MPN']),
                'seller_id' => $this->customer_id,
                'customer_id' => false,
                'mail_id' => $this->config->get('marketplace_mail_product_request'),
                'mail_from' => $this->config->get('marketplace_adminmail'),
                'mail_to' => $this->customer->getEmail(),
            ];

            $commission = $this->getSellerCommission($this->customer_id);

            $values = [
                'product_name' => $mailData['name'],
                'commission' => $commission . "%",
            ];

            /**
             * send maila after product added
             */
            $this->load->model('customerpartner/mail');

            /**
             * add product mail to seller
             */
            $this->model_customerpartner_mail->mail($mailData, $values);

            /**
             * add product mail end to admin
             */
            $mailData['mail_id'] = $this->config->get('marketplace_mail_product_admin');
            $mailData['mail_from'] = $this->customer->getEmail();
            $mailData['mail_to'] = $this->config->get('marketplace_adminmail');

            if ($this->config->get('marketplace_productaddemail')) {
                $this->model_customerpartner_mail->mail($mailData, $values);
            }
        }

        return $product_id;
    }

    public function getSellerCommission($seller_id)
    {
        $result = $this->db->query("SELECT commission FROM " . DB_PREFIX . "customerpartner_to_customer WHERE customer_id = '" . (int)$seller_id . "' AND is_partner = 1 ")->row;
        if (isset($result['commission'])) {
            return $result['commission'];
        } else {
            return false;
        }
    }


    /**
     * [editProduct description]
     * @param $data
     * @param $compare_list
     * @return int|string
     * @throws Exception
     */
    public function editProduct($data, $compare_list)
    {

        $status = strtolower(trim($data['*Status(Available/Unavailable)'])) == 'available' ? 1 : 0;
        if ($data['Sub-items']) {
            if (!isset($compare_list[$data['*MPN']])) {
                //首次
                $product_id = $this->orm->table(DB_PREFIX . 'customerpartner_to_product as ctp')
                    ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'ctp.product_id')
                    ->where([
                        'p.mpn' => $data['*MPN'],
                        'ctp.customer_id' => $this->customer_id,
                    ])
                    ->value('ctp.product_id');

                //获取原来的status
                $status_pre = $this->orm->table(DB_PREFIX . 'customerpartner_to_product as ctp')
                    ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'ctp.product_id')
                    ->where([
                        'p.mpn' => $data['*MPN'],
                        'ctp.customer_id' => $this->customer_id,
                    ])
                    ->value('p.status');

                if ($product_id) {

                    //现有的status
                    if($status_pre !== null){
                        if($status != $status_pre){
                            //发送站内信就好了
                            /**
                             * @description 获取修改前的产品对于buyer的上下架状态
                             * @author xxl
                             */
                            $productViewBefore = $this->modelCustomerpartner->getAllWishListBuyersProductInfo($product_id,$this->customer_id);
                            // 移除现有的分组
                            // 库存设为0
                            if($status == 0){
                                $this->load->model('customerpartner/product_manage');
                                /** @var ModelCustomerpartnerProductManage $modelCtpProductManage */
                                $modelCtpProductManage = $this->model_customerpartner_product_manage;
                                $modelCtpProductManage->setProductsOffShelf([$product_id]);

                                //如果下架 则从所在分组中删除
                                $this->load->model('Account/Customerpartner/ProductGroup');
                                $this->model_Account_Customerpartner_ProductGroup->updateLinkByProduct($this->customer_id, [], $product_id);
                                //移除精细化
                                $this->load->model('customerpartner/DelicacyManagement');
                                $this->model_customerpartner_DelicacyManagement->batchRemoveByProducts([$product_id], $this->customer_id);
                            }
                        }
                    }

                    $update = [
                        'status' => $status,
                        'date_modified' => date('Y-m-d H:i:s', time()),
                    ];
                    if($status == 1){
                        $update['is_deleted'] = 0;
                    }
                    $this->orm->table(DB_PREFIX . 'product')->where('product_id', $product_id)->update($update);
                    $this->orm->table(DB_PREFIX . 'product_description')->where('product_id', $product_id)->update([
                        'name' => $data['*Product Name'],
                        'description' => $data['*Product Name'],
                    ]);

                    $ret = $this->dealWithCategory($data['Categories']);
                    foreach($ret as $kt => $vt){
                        $category_id = $this->orm->table(DB_PREFIX . 'category_description')->where('name', trim($vt))->value('category_id');
                        if ($category_id) {

                            $exists = $this->orm->table(DB_PREFIX . 'product_to_category')->where(['product_id' => $product_id, 'category_id' => $category_id])->exists();
                            if($exists){
                                $this->orm->table(DB_PREFIX . 'product_to_category')->where(['product_id' => $product_id, 'category_id' => $category_id])->update([
                                    'category_id' => $category_id,
                                ]);
                            }else{
                                $this->orm->table(DB_PREFIX . 'product_to_category')->insert(['product_id' => $product_id, 'category_id' => $category_id]);
                            }

                        }
                    }

                    // 更新 set info
                    $this->orm->table('tb_sys_product_set_info')->where('product_id', $product_id)->update(['is_edit' => 0]);

                    /**
                     * 发送站内信
                     */
                    /**
                     * @description 获取修改前的产品对于buyer的上下架状态
                     * @author xxl
                     */
                    if($status_pre !== null){
                        if($status != $status_pre){
                            $productViewAfter = $this->modelCustomerpartner->getAllWishListBuyersProductInfo($product_id,$this->customer->getId());
                            if(!empty($productViewAfter)) {
                                foreach ($productViewAfter as $buyer_id => $productView) {
                                    //修改前后的不一致
                                    if(isset($productViewBefore[$buyer_id])) {
                                        if ($productView != $productViewBefore[$buyer_id]) {
                                            $this->modelCustomerpartner->sendProductionInfoToBuyer($product_id, $this->customer->getId(),$productView,$buyer_id);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } else {

                $product_id = $compare_list[$data['*MPN']];

            }
            // 查看MPN 存不存在
            $res = $this->orm->table(DB_PREFIX . 'customerpartner_to_product as ctp')
                ->leftJoin('tb_sys_product_set_info as s', 's.product_id', '=', 'ctp.product_id')
                ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 's.set_product_id')
                ->select('p.length', 'p.width', 'p.height', 'p.weight', 'p.buyer_flag', 'p.part_flag', 'p.status')
                ->where([
                    's.mpn' => $data['*MPN'],
                    's.set_mpn' => $data['Sub-items'],
                    'ctp.customer_id' => $this->customer_id,
                ])
                ->exists();

            if ($res) {
                //更新 tb_sys_product_set_info
                $this->orm->table('tb_sys_product_set_info')->where([
                    'product_id' => $product_id,
                    'set_mpn' => $data['Sub-items'],
                    'seller_id' => $this->customer_id,
                ])->update(['qty' => $data['Sub-items Quantity'],'is_edit' => 1]);
            } else {
                // 新增 子sku
                // combo 验证子sku是否可以插入
                $res_item = $this->orm->table(DB_PREFIX . 'customerpartner_to_product as ctp')
                    ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'ctp.product_id')
                    ->where([
                        'p.mpn' => $data['Sub-items'],
                        'ctp.customer_id' => $this->customer_id,
                    ])
                    ->value('p.product_id');
                if (!$res_item) {
                    //新增子sku 获取子sku的product_id
                    $info_child = [
                        'combo_flag' => 0,
                        'buyer_flag' => 0,  //子sku 都是不可单独售卖
                        'model' => $this->orm->table(DB_PREFIX . 'customerpartner_to_customer')->where('customer_id', $this->customer_id)->value('screenname'),
                        'part_flag' => 0,
                        'sku' => trim($data['Sub-items']),
                        'mpn' => trim($data['Sub-items']),
                        'upc' => '',
                        'ean' => '',
                        'jan' => '',
                        'isbn' => '',
                        'asin' => '',
                        'location' => '',
                        'quantity' => 0,
                        'stock_status_id' => 0,
                        'manufacturer_id' => 0,
                        'shipping' => 1,
                        'points' => 0,
                        'tax_class_id' => 0,
                        'price' => '0.00',
                        'date_available' => date('Y-m-d H:i:s', time()),
                        'weight' => $data['*Weight'],
                        'weight_class_id' => 5,
                        'length' => $data['*Length'],
                        'width' => $data['*Width'],
                        'height' => $data['*Height'],
                        'length_class_id' => 3,
                        'subtract' => 1,
                        'minimum' => 1,
                        'sort_order' => 1,
                        'status' => $status,
                        'viewed' => 0,
                        'date_added' => date('Y-m-d H:i:s', time()),
                        'date_modified' => date('Y-m-d H:i:s', time()),
                    ];
                    $child_product_id = $this->orm->table(DB_PREFIX . 'product')->insertGetId($info_child);
                    // 不需要这些
                    $this->childProductAddUpdate($child_product_id, $data);
                    $this->orm->table(DB_PREFIX . 'customerpartner_to_product')->insert([
                        'customer_id' => $this->customer_id,
                        'product_id' => $child_product_id,
                        'price' => '0.00',
                        'seller_price' => '0.00',
                        'currency_code' => '',
                        'quantity' => 0,
                        'pickup_price' => 0,

                    ]);

                }else{

                    $child_product_id = $res_item;

                }

                //插入 tb_sys_product_set_info
                $save_child = [
                    'set_mpn' => trim($data['Sub-items']),
                    'mpn' => trim($data['*MPN']),
                    'weight' => $data['*Weight'],
                    'cubes' => 0,
                    'height' => $data['*Height'],
                    'length' => $data['*Length'],
                    'width' => $data['*Width'],
                    'qty' => $data['Sub-items Quantity'],
                    'product_id' => $product_id,
                    'set_product_id' => $child_product_id,
                    'seller_id' => $this->customer_id,
                    'is_edit' => 1,
                ];
                $this->orm->table('tb_sys_product_set_info')->insert($save_child);


            }

        } else {
            //产品中已有MPN，MPN为General Item和Replacement Part，则对Product Name、Status（Available/Unavailable）、Categories进行更新。
            // 更新 product_name
            // 更新 Status
            $product_id = $this->orm->table(DB_PREFIX . 'customerpartner_to_product as ctp')
                ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'ctp.product_id')
                ->where([
                    'p.mpn' => $data['*MPN'],
                    'ctp.customer_id' => $this->customer_id,
                ])
                ->value('ctp.product_id');

            //获取原来的status
            $status_pre = $this->orm->table(DB_PREFIX . 'customerpartner_to_product as ctp')
                ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'ctp.product_id')
                ->where([
                    'p.mpn' => $data['*MPN'],
                    'ctp.customer_id' => $this->customer_id,
                ])
                ->value('p.status');

            if ($product_id) {

                //现有的status
                if($status_pre !== null){
                    if($status != $status_pre){
                        //发送站内信就好了
                        /**
                         * @description 获取修改前的产品对于buyer的上下架状态
                         * @author xxl
                         */
                        $productViewBefore = $this->modelCustomerpartner->getAllWishListBuyersProductInfo($product_id,$this->customer_id);
                        // 移除现有的分组
                        // 库存设为0
                        if($status == 0){
                            $this->load->model('customerpartner/product_manage');
                            /** @var ModelCustomerpartnerProductManage $modelCtpProductManage */
                            $modelCtpProductManage = $this->model_customerpartner_product_manage;
                            $modelCtpProductManage->setProductsOffShelf([$product_id]);

                            //如果下架 则从所在分组中删除
                            $this->load->model('Account/Customerpartner/ProductGroup');
                            $this->model_Account_Customerpartner_ProductGroup->updateLinkByProduct($this->customer_id, [], $product_id);
                            //移除精细化
                            $this->load->model('customerpartner/DelicacyManagement');
                            $this->model_customerpartner_DelicacyManagement->batchRemoveByProducts([$product_id], $this->customer_id);
                        }
                    }
                }
                $update = [
                    'status' => $status,
                    'date_modified' => date('Y-m-d H:i:s', time()),
                ];
                if($status == 1){
                    $update['is_deleted'] = 0;
                }
                $this->orm->table(DB_PREFIX . 'product')->where('product_id', $product_id)->update($update);
                $this->orm->table(DB_PREFIX . 'product_description')->where('product_id', $product_id)->update([
                    'name' => $data['*Product Name'],
                    'description' => $data['*Product Name'],
                ]);
                $ret = $this->dealWithCategory($data['Categories']);
                foreach($ret as $kt => $vt){
                    $category_id = $this->orm->table(DB_PREFIX . 'category_description')->where('name', trim($vt))->value('category_id');
                    if ($category_id) {

                        $exists = $this->orm->table(DB_PREFIX . 'product_to_category')->where(['product_id' => $product_id, 'category_id' => $category_id])->exists();
                        if($exists){
                            $this->orm->table(DB_PREFIX . 'product_to_category')->where(['product_id' => $product_id, 'category_id' => $category_id])->update([
                                'category_id' => $category_id,
                            ]);
                        }else{
                            $this->orm->table(DB_PREFIX . 'product_to_category')->insert(['product_id' => $product_id, 'category_id' => $category_id]);
                        }

                    }
                }

                /**
                 * 发送站内信
                 */
                /**
                 * @description 获取修改前的产品对于buyer的上下架状态
                 * @author xxl
                 */
                if($status_pre !== null){
                    if($status != $status_pre){
                        $productViewAfter = $this->modelCustomerpartner->getAllWishListBuyersProductInfo($product_id,$this->customer->getId());
                        if(!empty($productViewAfter)) {
                            foreach ($productViewAfter as $buyer_id => $productView) {
                                //修改前后的不一致
                                if(isset($productViewBefore[$buyer_id])) {
                                    if ($productView != $productViewBefore[$buyer_id]) {
                                        $this->modelCustomerpartner->sendProductionInfoToBuyer($product_id, $this->customer->getId(),$productView,$buyer_id);
                                    }
                                }
                            }
                        }
                    }
                }


            }


        }

        return $product_id;
    }

    /**
     * [productAddUpdate description] 原始的数据 未更改
     * @param int $product_id
     * @param $data
     * @return void
     */
    public function productAddUpdate($product_id, $data)
    {
        // product_description
        // product_to_store
        // product_to_category
        $mapDescription = [
            'product_id' => $product_id,
            'language_id' => 1,
            'name' => $data['*Product Name'],
            'description' => $data['*Product Name'],
            'tag' => '',
            'meta_description' => '',
            'meta_keyword' => '',
        ];
        $this->orm->table(DB_PREFIX . 'product_description')->insert($mapDescription);
        // 已经弃用了，但是还是插入数据吧
        $this->orm->table(DB_PREFIX . 'product_to_store')->insert([
            'product_id' => $product_id,
            'store_id' => 0,
        ]);

        $ret = $this->dealWithCategory($data['Categories']);
        foreach($ret as $kt => $vt){
            $category_id = $this->orm->table(DB_PREFIX . 'category_description')->where('name', trim($vt))->value('category_id');
            if ($category_id) {

                $exists = $this->orm->table(DB_PREFIX . 'product_to_category')->where(['product_id' => $product_id, 'category_id' => $category_id])->exists();
                if($exists){
                    $this->orm->table(DB_PREFIX . 'product_to_category')->where(['product_id' => $product_id, 'category_id' => $category_id])->update([
                        'category_id' => $category_id,
                    ]);
                }else{
                    $this->orm->table(DB_PREFIX . 'product_to_category')->insert(['product_id' => $product_id, 'category_id' => $category_id]);
                }

            }
        }


    }

    public function childProductAddUpdate($product_id, $data)
    {
        // product_description
        // product_to_store
        // product_to_category
        $mapDescription = [
            'product_id' => $product_id,
            'language_id' => 1,
            'name' => $data['Sub-items'],
            'description' => $data['Sub-items'],
            'tag' => '',
            'meta_description' => '',
            'meta_keyword' => '',
        ];
        $this->orm->table(DB_PREFIX . 'product_description')->insert($mapDescription);

        // 已经弃用了，但是还是插入数据吧
        $this->orm->table(DB_PREFIX . 'product_to_store')->insert([
            'product_id' => $product_id,
            'store_id' => 0,
        ]);

        $ret = $this->dealWithCategory($data['Categories']);
        foreach($ret as $kt => $vt){
            $category_id = $this->orm->table(DB_PREFIX . 'category_description')->where('name', trim($vt))->value('category_id');
            if ($category_id) {

                $exists = $this->orm->table(DB_PREFIX . 'product_to_category')->where(['product_id' => $product_id, 'category_id' => $category_id])->exists();
                if($exists){
                    $this->orm->table(DB_PREFIX . 'product_to_category')->where(['product_id' => $product_id, 'category_id' => $category_id])->update([
                        'category_id' => $category_id,
                    ]);
                }else{
                    $this->orm->table(DB_PREFIX . 'product_to_category')->insert(['product_id' => $product_id, 'category_id' => $category_id]);
                }

            }
        }


    }

    //获取运费和打包费
    public function get_fee($customer_id, $code, $page, $per_page)
    {
        $inner = $this->orm->table(DB_PREFIX . 'product_to_tag')
            ->selectRaw('product_id, GROUP_CONCAT( tag_id ORDER BY tag_id SEPARATOR \',\' ) AS tag_id')
            ->groupBy('product_id');

        $build = $this->orm->table(DB_PREFIX . 'customerpartner_to_product as ocp')
            ->leftJoin(DB_PREFIX . 'product as op', 'ocp.product_id', '=', 'op.product_id')
            ->leftJoin(DB_PREFIX . 'product_description as opd', 'op.product_id', '=', 'opd.product_id')
            ->leftJoin('oc_product_fee as pfd', function ($query) {     // 打包附加费-一件代发 更改了打包费的的表 2020-06-30 14:04:17 by lester.you
                $query->on('pfd.product_id', '=', 'ocp.product_id')
                    ->where('pfd.type', '=', 1);
            })
            ->leftJoin('oc_product_fee as pfh', function ($query) {     // 打包附加费-上门取货 更改了打包费的的表 2020-06-30 14:04:17 by lester.you
                $query->on('pfh.product_id', '=', 'ocp.product_id')
                    ->where('pfh.type', '=', 2);
            })
            ->leftJoin(new Expression('(' . $inner->toSql() . ') as opt'), 'op.product_id', '=', 'opt.product_id')
            ->where([
                ['ocp.customer_id', '=', $customer_id],
                ['op.is_deleted', '=', 0],
                ['op.status', '=', 1],
                ['opd.language_id', '=', 1]
            ])->whereIn('op.product_type',[0,3]);
        if ($code) {
            $build->where(function ($query) use ($code) {
                $query->where('op.sku', 'like', "$code%")
                    ->orwhere('op.mpn', 'like', "$code%");
            });
        }
        $total = $build->count();
        $rows = $build->selectRaw('op.sku, op.mpn, op.freight,op.part_flag,op.combo_flag,opt.tag_id,IFNULL(pfh.fee,0) as package_fee_h,IFNULL(pfd.fee,0) as package_fee_d')
            ->forPage($page, $per_page)
            ->get();
        $rows = obj2array($rows);
        return compact('total', 'rows');
    }

    //获取运费和打包费
    public function get_fee_all($customer_id,$code)
    {
        $build=$this->orm->table(DB_PREFIX . 'customerpartner_to_product as ocp')
            ->leftJoin(DB_PREFIX.'product as op','ocp.product_id','=','op.product_id')
            ->leftJoin(DB_PREFIX.'product_description as opd','op.product_id','=','opd.product_id')
            ->leftJoin('oc_product_fee as pfd', function ($query) {     // 打包附加费-一件代发 更改了打包费的的表 2020-06-30 14:04:17 by lester.you
                $query->on('pfd.product_id', '=', 'ocp.product_id')
                    ->where('pfd.type', '=', 1);
            })
            ->leftJoin('oc_product_fee as pfh', function ($query) {     // 打包附加费-上门取货 更改了打包费的的表 2020-06-30 14:04:17 by lester.you
                $query->on('pfh.product_id', '=', 'ocp.product_id')
                    ->where('pfh.type', '=', 2);
            })
            ->where([
                ['ocp.customer_id','=',$customer_id ],
                ['op.is_deleted','=',0 ],
                ['op.status','=',1 ],
                ['opd.language_id','=',1]
            ])->whereIn('op.product_type',[0,3]);
        if ($code) {
            $build->where(function($query) use ($code){
                $query->where('op.sku', 'like', "$code%")
                    ->orwhere('op.mpn', 'like', "$code%");
            });
        }
        $res = $build->selectRaw('op.sku, op.mpn, op.freight as freight,IFNULL(pfh.fee,0) as package_fee_h,IFNULL(pfd.fee,0) as package_fee_d')
            ->get();
        return obj2array($res);
    }

    /**
     * 运费界面  input的aurocomplete
     * @param int $customer_id
     * @param string $code
     * @return array
     */
    public function autocomplete_mpn($customer_id, $code){
        $build=$this->orm->table(DB_PREFIX . 'customerpartner_to_product as ocp')
            ->leftJoin(DB_PREFIX.'product as op','ocp.product_id','=','op.product_id')
            ->leftJoin(DB_PREFIX.'product_description as opd','op.product_id','=','opd.product_id')
            ->where([
                ['ocp.customer_id','=',$customer_id ],
                ['op.is_deleted','=',0 ],
                ['op.status','=',1 ],
                ['opd.language_id','=',1]
            ])->whereIn('op.product_type',[0,3]);
        if ($code) {
            $build->where(function($query) use ($code){
                $query->where('op.sku', 'like', "%$code%")
                    ->orwhere('op.mpn', 'like', "%$code%");
            });
        }
        $res=$build->limit(10)->get(['sku','mpn']);
        return obj2array($res);
    }

    /**
     * @param int $customer_id
     * @return bool
     */
    public function checkIsBatchStore($customer_id)
    {
        return $this->orm->table('tb_sys_batch_store')
            ->where([
                ['customer_id', $customer_id],
                ['delete_flag','=',0]
            ])
            ->exists();
    }
}
