<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * @property ModelCustomerpartnerSpotPrice $model_customerpartner_spot_price
 */
class ControllerCustomerpartnerSpotPriceNegotiatedPrice extends Controller
{
    const ALL_PRODUCTS = 1;
    const SOME_PRODUCTS = 0;
    /**
     * @var ModelCustomerpartnerSpotPrice $model
     */
    public $model = null;
    // 上传生成文件 后续自动删除
    private $uploadFiles = [];

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->language('customerpartner/spot_price');
        $this->load->model('customerpartner/spot_price');
        /** @var ModelCustomerpartnerSpotPrice $modelSpotPrice */
        $this->model = $this->model_customerpartner_spot_price;
    }

    public function __destruct()
    {
        array_map(function ($file) {
            @unlink($file);
        }, $this->uploadFiles);
    }

    public function index()
    {
        $status = $this->orm
            ->table(DB_PREFIX . 'wk_pro_quote')
            ->where('seller_id', $this->customer->getId())
            ->value('status');
        if ($status === null) $status = -1;
        $data['radio_option'] = $status;
        $data['download_url'] = $this->url->link('customerpartner/spot_price/negotiated_price/download');
        $data['download_template_url'] = $this->url->link('customerpartner/spot_price/negotiated_price/download_template');
        $data['upload_template_url'] = $this->url->link('customerpartner/spot_price/negotiated_price/storeByUploadFile');

        $this->response->setOutput($this->load->view('spot_price/neg_price/index', $data));
    }

    // 上传文件保存
    public function storeByUploadFile()
    {
        $ret = ['code' => 0, 'msg' => ''];
        $file = $this->request->files['file'] ?? null;
        if ($file == null || $file['type'] !== 'application/vnd.ms-excel') {
            $ret = ['code' => 1, 'msg' => 'Invalid file type.'];
        }
        if ($file['error'] != UPLOAD_ERR_OK) {
            $ret = ['code' => 1, 'msg' => $this->language->get("upload_error_{$file['error']}")];
        }
        if ($ret['code'] === 1) $this->response->returnJson($ret);
        $hash = hash_file('md5', $file['tmp_name']);
        $dir_upload = DIR_UPLOAD . $hash . '.csv';
        move_uploaded_file($file['tmp_name'], $dir_upload);
        // 存储路径 后续删除
        $this->uploadFiles[] = $dir_upload;
        $res = array_map('str_getcsv', file($dir_upload));
        if ($res[0] !== ['MPN', 'Item Code']) {
            $ret = ['code' => 1, 'msg' => 'Invalid file,Please ensure your upload file match the download template.'];
            $this->response->returnJson($ret);
        }
        array_walk($res, function (&$item) use ($res) {
            array_walk($item, function (&$val) {
                $val = trim($val);
            });
            $item = array_combine($res[0], $item);
        });
        // 验证数据准确性
        $db = $this->orm->getConnection();
        $errorNo = []; // csv中错误数据的行数
        $repeatNo = []; // csv中重复数据出现行数
        $cIds = []; // 正确的product ids
        // 获取现在的product_ids
        $exist_product_ids = $db->table(DB_PREFIX . 'wk_pro_quote_list')
            ->where(['seller_id' => $this->customer->getId()])
            ->pluck('product_id')
            ->toArray();
        array_map(function ($item, $key) use ($db, $exist_product_ids, &$errorNo, &$repeatNo, &$cIds) {
            if ($key === 0) return; // 排除第一行 headers
            if (empty($item['MPN']) && empty($item['Item Code'])) {
                array_push($errorNo, $key + 1);
                return;
            };
            $info = $db->table(DB_PREFIX . 'product as p')
                ->join(DB_PREFIX . 'customerpartner_to_product as ctp', ['ctp.product_id' => 'p.product_id'])
                ->where([
                    'p.is_deleted' => 0,
                    'p.status' => 1,
                    'p.buyer_flag' => 1,
                    'ctp.customer_id' => $this->customer->getId(),
                ])
                ->where(function (Builder $q) use ($item) {
                    $item['MPN'] && $q->orWhere(['p.mpn' => $item['MPN']]);
                    $item['Item Code'] && $q->orWhere(['p.sku' => $item['Item Code']]);
                })
                ->first();
            if (!$info) {
                array_push($errorNo, $key + 1);
                return;
            }
            if (in_array($info->product_id, $cIds) && !in_array($info->product_id, $exist_product_ids)) {
                array_push($repeatNo, $key + 1);
                return;
            }
            array_push($cIds, $info->product_id);
        }, $res, array_keys($res));
        // 检查是否有错误数据
        if (count($errorNo) > 0) {
            $ret = [
                'code' => 1,
                'msg' => dprintf($this->language->get('error_csv_data_error'), join(',', $errorNo)),
            ];
            $this->response->returnJson($ret);
        }
        // 检查是否有重复数据
        if (count($repeatNo) > 0) {
            $ret = [
                'code' => 1,
                'msg' => dprintf($this->language->get('error_csv_repeat_error'), join(',', $repeatNo)),
            ];
            $this->response->returnJson($ret);
        }
        $flag = $this->model->addNegotiatedPrice($this->customer->getId(), $cIds);
        $this->response->returnJson([
            'code' => $flag ? 0 : 1,
            'msg' => $flag ? 'Upload and edit Successfully.' : 'Edit Failed.',
        ]);
    }

    // 下载csv文件
    public function download()
    {
        $data = $this->model->getNegotiatedPriceList($this->customer->getId(), $this->request->request);
        $data = $data['rows'];
        $data = array_map(function ($item) {
            return [
                $item['mpn'] ?? '',
                $item['sku'] ?? '',
            ];
        }, $data);
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=\"" . 'spot_price_products.csv' . "\"");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $fp = fopen('php://output', 'w');
        array_unshift($data, ['MPN', 'Item Code']);
        array_map(function ($item) use ($fp) {
            fputcsv($fp, $item);
        }, $data);
    }

    // 下载模板
    public function download_template()
    {
        $fileName = 'negotiated_price.csv';
        $path = DIR_DOWNLOAD . $fileName;
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=' . $fileName);
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }

    // region api

    // 保存
    public function store()
    {
        // 确认产品是否有效或者已经存在
        $post = $this->request->post;
        $ret = ['code' => 1, 'msg' => 'Invalid Operation.'];
        if (!isset($post['product_id'])) $this->response->returnJson($ret);
        $c_id = $this->customer->getId();
        $p_id = $post['product_id'];
        $info = $this->orm
            ->table('oc_customerpartner_to_product')
            ->where(['customer_id' => $c_id, 'product_id' => $p_id])
            ->first();
        if (!$info) $this->response->returnJson($ret);
        $detailInfo = $this->orm
            ->table(DB_PREFIX . 'wk_pro_quote_list')
            ->where(['seller_id' => $c_id, 'product_id' => $p_id])
            ->first();
        if ($detailInfo) {
            $ret['msg'] = $this->language->get('error_duplicate_data');
            $this->response->returnJson($ret);
        }
        $res = $this->model->addNegotiatedPrice($c_id, [$p_id]);
        $this->response->returnJson([
            'code' => $res ? 0 : 1,
            'msg' => $res ? 'Add Successfully.' : 'Add Failed.',
        ]);
    }

    // 删除
    public function delete()
    {
        // 确认产品是否有效或者已经存在
        $post = $this->request->post;
        $ret = ['code' => 1, 'msg' => 'Invalid Operation.'];
        if (!isset($post['product_id'])) $this->response->returnJson($ret);
        $c_id = $this->customer->getId();
        $p_id = $post['product_id'];
        try {
            $this->orm->getConnection()->transaction(function () use ($c_id, $p_id) {
                $this->orm
                    ->table(DB_PREFIX . 'wk_pro_quote_list')
                    ->where(['seller_id' => $c_id, 'product_id' => $p_id])
                    ->delete();
            });
            $ret = ['code' => 0, 'msg' => 'Delete Successfully.'];
        } catch (Throwable $e) {
            $ret = ['code' => 1, 'msg' => $e->getMessage()];
        }

        $this->response->returnJson($ret);
    }

    // 用户点选radio button
    public function changeOption()
    {
        $request = $this->request->request;
        $ret = ['code' => 1, 'msg' => ''];
        if (!isset($request['value']) || !in_array((int)$request['value'], [0, 1])) {
            $this->response->returnJson($ret);
        }
        $res = $this->model->changeNegPriceOption($this->customer->getId(), $request['value']);
        $ret['code'] = $res ? 0 : 1;
        $this->response->returnJson($ret);
    }

    // 获取用户议价列表
    public function getList()
    {
        $data = $this->model->getNegotiatedPriceList($this->customer->getId(), $this->request->request);
        $this->response->returnJson($data);
    }

    /**
     * 获取关联商品的api
     */
    public function getProducts()
    {
        $co = new Collection($this->request->get);
        $pageSize = $co->get('page_size', 5);
        $currentPage = $co->get('page', 1);
        /** @var Builder $query */
        $query = $this->orm
            ->table(DB_PREFIX . 'product as p')
            ->select([
                'p.product_id', 'p.mpn', 'p.sku', 'pd.name', 'p.price', 'p.freight'
            ])
            ->join(DB_PREFIX . 'customerpartner_to_product as c2p', ['c2p.product_id' => 'p.product_id'])
            ->leftJoin(DB_PREFIX . 'product_description as pd', ['pd.product_id' => 'p.product_id'])
            ->where([
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'p.product_type' => 0,
                'c2p.customer_id' => (int)$this->customer->getId(),
            ])
            ->when(
                !empty(trim($co->get('filter_search'))),
                function (Builder $q) use ($co) {
                    $q->where(function (Builder $q) use ($co) {
                        $filter = htmlspecialchars(trim($co->get('filter_search')));
                        $q->orWhere('p.mpn', 'like', "%{$filter}%")
                            ->orWhere('p.sku', 'like', "%{$filter}%");
                    });
                }
            )
            ->when(
                !empty($co->get('product_id')),
                function (Builder $q) use ($co) {
                    $q->whereNotIn('p.product_id', $co->get('product_id'));
                }
            )
            ->orderBy('p.product_id', 'desc');
        $total = $query->count();
        /** @var Collection $res */
        $res = $query->forPage($currentPage, $pageSize)->get();
        $country_id = $this->customer->getCountryId();
        $currency = session('currency');
        $res = $res->map(function ($item) use ($country_id, $currency) {
            $item = get_object_vars($item);
            $item['name'] = htmlspecialchars_decode($item['name']);
            $item['freight'] = $item['freight'] ?: 0.00;
            $pick_up_price = bcsub($item['price'], $item['freight'], 2);
            // 判断国籍 目前只有美国有一件代发 和 上门取货的区别
            if ($country_id == AMERICAN_COUNTRY_ID) {
                $item['pick_up_price'] = (bccomp($pick_up_price, 0) === 1) ? $pick_up_price : 0;
            } else {
                $item['pick_up_price'] = $item['price'];
            }
            $item['price_format'] = $this->currency->format($item['price'], $currency);
            $item['freight_format'] = $this->currency->format($item['freight'], $currency);
            $item['pick_up_price_format'] = $this->currency->format($item['pick_up_price'], $currency);
            return $item;
        });
        $this->response->returnJson(['data' => $res->toArray(), 'total' => $total, 'page' => $currentPage]);
    }

    // end region
}
