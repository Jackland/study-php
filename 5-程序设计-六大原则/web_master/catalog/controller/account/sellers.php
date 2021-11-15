<?php

use App\Enums\Product\ProductType;
use App\Models\Product\Product;

/**
 * Class ControllerAccountSellers
 * @property ModelAccountSellers $model_account_sellers
 * @property ModelCatalogSearch $model_catalog_search
 */
class ControllerAccountSellers extends Controller
{
    public function index()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/sellers', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $this->getList();
    }

    protected function getList()
    {
        $this->load->language('account/sellers');

        $this->document->setTitle($this->language->get('heading_title_sellers'));

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_sellers'),
            'href' => $this->url->link('account/sellers', '', true)
        );

        $this->load->model('account/sellers');
        $url = "";
        // 主要页面内容加载
        /* 获取排序字段和方式 */
        if (isset($this->request->get['sort'])) {

            $sort = $this->request->get['sort'];
        } else {
            $sort = 'ctc.`screenname`';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }

        /* 分页 */
        // 第几页
        if (isset($this->request->get['page'])) {
            $page_num = $this->request->get['page'];
        } else {
            $page_num = 1;
        }
        $data['page_num'] = $page_num;
        // 每页显示数目
        if (isset($this->request->get['page_limit'])) {
            $page_limit = $this->request->get['page_limit'];
        } else {
            $page_limit = 15;
        }
        $data['page_limit'] = $page_limit;

        /* 过滤条件 */
        // (seller name)
        if (isset($this->request->get['filter_seller_name'])) {
            $filter_seller_name = $this->request->get['filter_seller_name'];
            $url .= "&filter_seller_name=" . $filter_seller_name;
        } else {
            $filter_seller_name = "";
        }
        $data['filter_seller_name'] = $filter_seller_name;
//        // (email)
//        if (isset($this->request->get['filter_seller_email'])) {
//            $filter_seller_email = $this->request->get['filter_seller_email'];
//            $url .= "&filter_seller_email=" . $filter_seller_email;
//        } else {
//            $filter_seller_email = "";
//        }
        // (status)
        if (isset($this->request->get['filter_status'])) {
            $filter_status = $this->request->get['filter_status'];
            $url .= "&filter_status=" . $filter_status;
        } else {
            $filter_status = "";
        }
        $data['filter_status'] = $filter_status;
        $filter_data = array(
            "sort" => $sort,
            "order" => $order,
//            "filter_seller_email" => $filter_seller_email,
            "filter_status" => $filter_status,
            "filter_seller_name" => $filter_seller_name,
            "page_num" => $page_num,
            "page_limit" => $page_limit

        );
        $customer_id = $this->customer->getId();
        $data['customer_id'] = $customer_id;
        $sellers = $this->model_account_sellers->getSellersByCustomerId($customer_id, $filter_data);
        $customerTotal = $this->model_account_sellers->getSellersTotalByCustomerId($customer_id, $filter_data);
        $data['customer_total'] = $customerTotal;
        $total_pages = ceil($customerTotal / $page_limit);
        $data['total_pages'] = $total_pages;
        $num = (($page_num - 1) * $page_limit) + 1;
        $data['results'] = sprintf($this->language->get('text_pagination'), ($customerTotal) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($customerTotal - $page_limit)) ? $customerTotal : ((($page_num - 1) * $page_limit) + $page_limit), $customerTotal, $total_pages);
        $tableData = array();
        foreach ($sellers->rows as $seller) {
            $tableData[] = array(
                "num" => $num++,
                "id" => $seller['id'],
                "seller_name" => $seller['seller_name'],
                "main_cate_name" => $this->getProductCountByCate($customer_id,$seller['seller_id']),
                "seller_email" => $seller['seller_email'],
                "account" => $seller['account'] == null ? "" : $seller['account'],
                "password" => $seller['pwd'] == null ? "" : $seller['pwd'],
                "discount_method" => $seller['discount_method'],
                "discount" => $seller['discount'],
                "number_of_transaction" => $seller['number_of_transaction'],
                "money_of_transaction" => $this->currency->formatCurrencyPrice($seller['money_of_transaction'], $this->session->data['currency']),
                "last_transaction_time" => $seller['last_transaction_time'] == '1970-01-01 00:00:00' ? 'N/A' : $seller['last_transaction_time'], // 此处last_transaction_time sql中默认值为1970-01-01 00:00:00
                "coop_status_buyer" => $seller['buyer_control_status'] == null ? 0 : $seller['buyer_control_status'],
                "coop_status_seller" => $seller['seller_control_status'] == null ? 0 : $seller['seller_control_status'],
                'send_message_link' => url(['account/message_center/message/new', 'receiver_ids' => $seller['seller_id']]),
                'store_link' => $this->url->link('customerpartner/profile', '&id=' . $seller['seller_id'], true)
            );

        }
        // 排序字段
        $data['sort'] = $sort;
        // 排序
        $data['order'] = $order;
        if ($order == "ASC") {
            $order = "DESC";
        } else {
            $order = "ASC";
        }

        $data['tableData'] = $tableData;
        /* 排序 */
        $url .= "&page_limit=" . $page_limit;
        /* 排序 */
        $data['sort_seller_name'] = $this->url->link('account/sellers', '&customer_id=' . $customer_id . '&sort=ctc.`screenname`' . '&order=' . $order . $url, true);
        $data['sort_coop_status_buyer'] = $this->url->link('account/sellers', '&customer_id=' . $customer_id . '&sort=bts.`buyer_control_status`' . '&order=' . $order . $url, true);

        $url .= "&sort=" . $sort;
        if ($order == "DESC") {
            $url .= "&order=" . "ASC";
        } else {
            $url .= "&order=" . "DESC";
        }
        $pagination = new Pagination();
        $pagination->total = $customerTotal;
        $pagination->page = $page_num;
        $pagination->limit = $page_limit;
        $pagination->url = $this->url->link('account/sellers', $url . '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($customerTotal) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($customerTotal - $page_limit)) ? $customerTotal : ((($page_num - 1) * $page_limit) + $page_limit), $customerTotal, $total_pages);

        $data['continue'] = $this->url->link('account/account', '', true);

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('account/sellers', $data));
    }

    public function update()
    {
        // 加载Model
        $this->load->model('account/sellers');
        /** @var ModelAccountSellers $modelAccountSellers */
        $modelAccountSellers = $this->model_account_sellers;
        $customer_id = $this->request->post["customer_id"];
        $order = $this->request->post['order'];
        $sort = $this->request->post['sort'];
        $id = $this->request->post["id"];
        $coop_status_buyer = $this->request->post["coop_status_buyer"];
        $update_data = [
            "id" => $id,
            "buyer_control_status" => $coop_status_buyer,
        ];
        $modelAccountSellers->updateSellerInfo($update_data);
        $url = "";
        // 查询过滤条件
        if (isset($this->request->post['filter_seller_name'])) {
            $filter_seller_name = $this->request->post['filter_seller_name'];
            $url .= "&filter_seller_name=" . $filter_seller_name;
        }

        if (isset($this->request->post['filter_seller_email'])) {
            $filter_email = $this->request->post['filter_seller_email'];
            $url .= "&filter_seller_email=" . $filter_email;
        }

        // 分页
        // 第几页
        if (isset($this->request->post['page'])) {
            $page_num = $this->request->post['page'];
        } else {
            $page_num = 1;
        }
        $url .= "&page=" . $page_num;
        // 每页显示数目
        if (isset($this->request->post['page_limit'])) {
            $page_limit = $this->request->post['page_limit'];
        } else {
            $page_limit = 20;
        }
        $url .= "&page_limit=" . $page_limit;
        $json['load'] = $this->url->link('account/sellers', "&customer_id=" . $customer_id . "&sort=" . $sort . "&order=" . $order . $url, false);
        $json['load'] = str_replace("&amp;", "&", $json['load']);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    //下载
    public function filterByCsv()
    {
        $this->load->model('account/sellers');

        $filter_seller_name = trim(get_value_or_default($this->request->get, 'filter_seller_name', ''));
        $filter_status = trim(get_value_or_default($this->request->get, 'filter_status', ''));
        $sort = trim(get_value_or_default($this->request->get, 'sort', ''));
        $order = trim(get_value_or_default($this->request->get, 'order', ''));

        $filter_data = [];
        $filter_data['filter_seller_name'] = $filter_seller_name;
        $filter_data['filter_status'] = $filter_status;
        $filter_data['sort'] = $sort;
        $filter_data['order'] = $order;

        $fileName = 'Seller Management List ' . date('Ymd', time()) . '.csv';
        //header('Content-Encoding: UTF-8');
        //header("Content-Type: text/csv; charset=UTF-8");
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        //echo chr(239).chr(187).chr(191);
        $fp = fopen('php://output', 'a');
        //在写入的第一个字符串开头加 bom。
        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        fwrite($fp, $bom);
        $head = [
            "Seller Store",
            "Main Category",
            "Number of transactions",
            "Total amount of transactions",
            "Last transaction time",
            "Mutual Connection",
            "Connection"
        ];
        fputcsv($fp, $head);

        $sellers = $this->model_account_sellers->getSellersByCustomerId($this->customer->getId(), $filter_data);
        $this->load->language('account/sellers');
        foreach ($sellers->rows as $seller) {
            $line = [
                html_entity_decode($seller['seller_name']),
                html_entity_decode($this->getProductCountByCate($this->customer->getId(), $seller['seller_id']) ),
                $seller['number_of_transaction'],
                $this->currency->formatCurrencyPrice($seller['money_of_transaction'], $this->session->data['currency']),
                $seller['last_transaction_time'] == '0000-01-01 00:00:00' ? '' :changeOutPutByZone($seller['last_transaction_time'], $this->session, 'Y-m-d H:i:s'),
                $seller['seller_control_status'] == null ? $this->language->get('text_inactive') : $this->language->get('text_active'),
                $seller['buyer_control_status'] == null ? $this->language->get('text_inactive') : $this->language->get('text_active'),

            ];
            fputcsv($fp, $line);
        }
        $output = stream_get_contents($fp);
        fclose($fp);
    }

    /**
     * 获取主营（产品）类别
     * Seller有效产品（店铺在售产品）的分类，产品数最高的一个分类为主营类别。Furniture分类取到二级，其他分类取到一类
     * @param int $customer_id  customer_id
     * @param int $seller_id    seller_id
     * @return string      主要的分类名称,没有为其他
     * @throws Exception
     *
     */
    public function getProductCountByCate($customer_id, $seller_id)
    {
        $main_cate_name = 'Others';
        $this->load->model('catalog/search');
        $categories = $this->model_catalog_search->sellerCategories($seller_id);
        $product_total_by_cate = [];
        $filter_data['seller_id'] = $seller_id;
        foreach ($categories as $id1 => $category1) {
            //是Furniture分类的走到二级，其余走到一级
            if ($category1['category_id'] == 255 && isset($category1['children'])) {
                foreach ($category1['children'] as $id2 => $category2) {
                    $filter_data['category_id'] = $category2['category_id'];
                    $tmp = $this->model_catalog_search->searchProductId($filter_data, $customer_id, $this->customer->isPartner());
                    $info = array(
                        'prduct_total' => $tmp['total'],
                        'cate_name' => $category1['name'] . ' > ' . $category2['name'],
                    );
                    $product_total_by_cate[] = $info;
                }
            } else {
                $filter_data['category_id'] = $category1['category_id'];
                $tmp = $this->model_catalog_search->searchProductId($filter_data, $customer_id, $this->customer->isPartner());
                $info = array(
                    'prduct_total' => $tmp['total'],
                    'cate_name' => $category1['name'],
                );
                $product_total_by_cate[] = $info;
            }
        }
        if ($product_total_by_cate && count($product_total_by_cate) > 0) {
            $arr = array_column($product_total_by_cate, 'prduct_total', 'cate_name');
            arsort($arr);
            if (intval(reset($arr)) > 0) {
                $main_cate_name = key($arr);
            }
        }
        return $main_cate_name;
    }

}
