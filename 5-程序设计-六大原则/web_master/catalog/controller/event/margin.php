<?php

use Carbon\Carbon;

/**
 * Class ControllerEventMargin
 *
 * @property ModelAccountProductQuotesMarginContract $model_account_product_quotes_margin_contract
 * @property ModelCatalogProduct $model_catalog_product
 */
class ControllerEventMargin extends Controller
{
    /**
     * 保证金协议更新完成之后的对应动作
     * @param $route
     * @param $args
     * @param $output
     * user：wangjinxin
     * date：2020/3/11 15:49
     *
     * @throws Exception
     * @see ModelAccountProductQuotesMarginContract::updateMarginContractStatus()
     */
    public function updateAfter($route, $args, $output)
    {
        if ($output === false) return;
        list($seller_id, $contract_id, $status_code) = $args;
        switch ($status_code) {
            case ModelAccountProductQuotesMarginContract::APPROVED:
            {
                $this->afterMarginApproved($contract_id);
                break;
            }
            default:
                break;
        }
    }

    /**
     * 同意现货保证金协议之后的动作 建议直接抛出异常
     * user：wangjinxin
     * date：2020/3/11 16:03
     * @param string $agreement_id
     * @throws Exception
     */
    private function afterMarginApproved($agreement_id)
    {
        // 复制头款商品
        $product_id_new = $this->copyMarginProduct($agreement_id);
        // 创建保证金进程记录
        $this->addMarginProcess($agreement_id, $product_id_new);
    }

    /**
     * 复制头款保证金产品
     * @param string $agreement_id ||协议id
     * @return int  || 返回生成的头款保证金产品id
     * @throws Exception
     */
    private function copyMarginProduct($agreement_id): int
    {
        $info = $this->getAgreementInfo($agreement_id);
        $seller_id = (int)$info['seller_id'];
        $product_id = (int)$info['product_id'];
        // 获取元商品信息
        $product_info = $this->orm
            ->table('oc_product as p')
            ->select(['p.*', 'pd.name', 'pd.description'])
            ->join('oc_product_description as pd', 'p.product_id', '=', 'pd.product_id')
            ->where('p.product_id', $product_id)
            ->first();

        if (!$product_info) {
            throw new Exception(__FILE__ . " Can not find product relate to product_id:{$product_id}.");
        }
        // 获取所属国家id
        $country_id = $this->orm
            ->table('oc_customer')
            ->where('customer_id', $seller_id)
            ->value('country_id');
        // 新的sku
        $sku_new = 'M'
            . str_pad($country_id, 4, "0", STR_PAD_LEFT)
            . date("md") . substr(time(), -6);
        // 新的商品名称
        $product_name_new = "[Agreement ID:{$agreement_id}]{$product_info->name}";
        // 产品单价
        $price_new = round($info['money'], 2);
        $param = [
            'product_id' => $product_id,
            'num' => 1,
            'price' => $price_new,
            'seller_id' => $seller_id,
            'sku' => $sku_new,
            'product_name' => $product_name_new,
            'freight' => 0,//保证金订金商品的运费和打包费都为0
            'package_fee' => 0,
            'product_type' => configDB('product_type_dic_margin_deposit'),
        ];
        // 复制产品
        $this->load->model('catalog/product');
        $product_id_new = $this->model_catalog_product->copyProductMargin($product_id, $param);
        if ($product_id_new === 0) {
            throw new Exception(__FILE__ . " Create product failed. product_id:{$product_id}");
        }
        // 更新ctp
        $this->orm->table('oc_customerpartner_to_product')
            ->insert([
                'customer_id' => $seller_id,
                'product_id' => $product_id_new,
                'seller_price' => $price_new,
                'price' => $price_new,
                'currency_code' => '',
                'quantity' => 1,
            ]);
        // 更新product tag
        $orig_tags = $this->orm->table('oc_product_to_tag')->where('product_id', $product_id)->get();
        if (!$orig_tags->isEmpty()) {
            $insert_tags = [];
            foreach ($orig_tags as $item) {
                $insert_tags[] = [
                    'is_sync_tag' => ($item->tag_id == 1) ? 0 : $item->is_sync_tag,
                    'tag_id' => $item->tag_id,
                    'product_id' => $product_id_new,
                    'create_user_name' => $seller_id,
                    'update_user_name' => $seller_id,
                    'create_time' => Carbon::now(),
                    'program_code' => 'MARGIN',
                ];
            }
            $this->orm->table('oc_product_to_tag')->insert($insert_tags);
        }

        return $product_id_new;
    }

    /**
     * 创建保证金进程记录
     *
     * @param string $agreement_id
     * @param int $product_id
     * user：wangjinxin
     * date：2020/3/11 18:04
     * @throws Exception
     */
    private function addMarginProcess($agreement_id, $product_id)
    {
        $marginAgreement = $this->getAgreementInfo($agreement_id);
        $margin_process = [
            'margin_id' => $marginAgreement['id'],
            'margin_agreement_id' => $marginAgreement['agreement_id'],
            'advance_product_id' => $product_id,
            'process_status' => 1,
            'create_time' => Carbon::now(),
            'create_username' => $marginAgreement['seller_id'],
            'program_code' => 'V1.0'
        ];
        $this->load->model('account/product_quotes/margin_contract');
        $this->model_account_product_quotes_margin_contract->addMarginProcess($margin_process);
    }

    /**
     * 获取协议详情
     * @param string $agreement_id
     * @return array
     * user：wangjinxin
     * date：2020/3/11 16:13
     */
    private function getAgreementInfo($agreement_id): array
    {
        static $ret = [];
        static $is_find = false;
        if (!$is_find) {
            $res = $this->orm
                ->table('tb_sys_margin_agreement')
                ->where('agreement_id', $agreement_id)
                ->first();
            $ret = get_object_vars($res);
            $is_find = true;
        }
        return $ret;
    }
}
