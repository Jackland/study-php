<?php

use Illuminate\Support\Carbon;
use App\Catalog\Controllers\AuthSellerController;

/**
 * 该类主要处理 议价 和 阶梯价格配置
 * Class ControllerCustomerpartnerSpotPriceIndex
 */
class ControllerCustomerpartnerSpotPriceIndex extends AuthSellerController
{
    const NEGOTIATED = 'negotiated-price';
    const TIERED = 'tiered-price';

    public function index()
    {
        $this->load->language('customerpartner/spot_price');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addStyle('catalog/view/javascript/bootstrap-table/bootstrap-table-1.15.2.css');
        $this->document->addScript('catalog/view/javascript/bootstrap-table/bootstrap-table-1.15.2.js');
        $this->document->addScript('catalog/view/javascript/layer/layer.js');
        $this->document->addScript('catalog/view/javascript/jquery/jquery.cookie.min.js');

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_parent_title'),
            'href' => 'javascript:void(0);'
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('customerpartner/spot_price/index', '', true)
        );

        if (
            $this->config->get('marketplace_separate_view') &&
            isset($this->session->data['marketplace_separate_view']) &&
            $this->session->data['marketplace_separate_view'] == 'separate'
        ) {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['margin'] = "margin-left: 18%";
            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        } else {
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');
        }
        // 区分当前属于哪个标签 前台显示相关
        $cookie = $this->request->cookie;
        if (
            !isset($cookie['SPOT_PRICE_TAB'])
            || !in_array($cookie['SPOT_PRICE_TAB'], [static::NEGOTIATED, static::TIERED])
        ) {
            $this->request->cookie['SPOT_PRICE_TAB'] = static::NEGOTIATED;
        }
        $data['tab_name'] = $this->request->cookie['SPOT_PRICE_TAB'];
        $this->response->setOutput($this->load->view('spot_price/index', $data));
    }

    /**
     *旧数据处理 后续删除改代码
     */
    public function resolveTable()
    {
        $date = date('Ymd');
        $db = $this->orm->getConnection();
        $query = $db->table(DB_PREFIX . 'wk_pro_quote_details');
        if ($query->count() > 999999) {
            exit('OVER 999999');
        }
        $res = $query->get();
        try {
            $db->beginTransaction();
            $res->map(function ($item) use ($db, $date) {
                $item = $temp = get_object_vars($item);
                $id = strval($item['id']);
                if ($temp['template_id']) return;
                $templateSuffix = $date;
                if (strlen($id) <= 6) {
                    $templateSuffix .= str_repeat('0', 6 - strlen($id)) . $id;
                } else {
                    $templateSuffix .= substr($id, strlen($id) - 6);
                }
                $db->table(DB_PREFIX . 'wk_pro_quote_details')
                    ->where('id', $id)
                    ->update([
                        'template_id' => $templateSuffix,
                        'create_time' => Carbon::now(),
                        'update_time' => Carbon::now(),
                    ]);
            });
            $db->commit();
        } catch (Exception $e) {
            $this->log->write($e->getMessage());
            $db->rollBack();
            exit('fail');
        }
        exit('success');
    }

    /**
     * 议价拆分
     * user：wangjinxin
     * date：2019/11/7 19:10
     * @throws Exception
     * @throws Throwable
     */
    public function resolveList()
    {
        $res = $this->orm->table('oc_wk_pro_quote')->get();
        $this->orm->getConnection()->transaction(function () use ($res) {
            $this->orm->table('oc_wk_pro_quote_list')->delete();
            foreach ($res as $item) {
                $temp = get_object_vars($item);
                if (!$temp['product_ids']) continue;
                $product_ids = explode(',', $temp['product_ids']);
                $product_ids = array_map(function ($id) use ($temp) {
                    return [
                        'product_id' => $id,
                        'seller_id' => $temp['seller_id']
                    ];
                }, $product_ids);
                $this->orm->table('oc_wk_pro_quote_list')->insert($product_ids);
            }
        });
    }
}
