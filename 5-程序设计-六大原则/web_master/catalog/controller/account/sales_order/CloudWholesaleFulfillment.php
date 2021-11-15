<?php

use App\Catalog\Controllers\AuthController;
use App\Catalog\Forms\CWF\FileUploadForm;
use App\Catalog\Search\CWF\FileUploadHistorySearch;
use App\Components\Storage\StorageCloud;
use App\Components\Storage\StorageLocal;
use App\Logging\Logger;
use App\Models\CWF\CloudWholesaleFulfillmentFileUpload;
use App\Repositories\CWF\CloudWholesaleFulfillmentRepository;

/**
 * @property ModelAccountSalesOrderCloudWholesaleFulfillment $model_account_sales_order_CloudWholesaleFulfillment
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountSalesOrderCloudWholesaleFulfillment extends Model
{

    /**
     * @var ModelAccountSalesOrderCloudWholesaleFulfillment $model
     */
    private $model;

    public function __construct($registry)
    {
        parent::__construct($registry);
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/sales_order/sales_order_management', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        if ($this->customer->isPartner()) {
            $this->response->redirect($this->url->link('account/account', '', true));
        }

        if ($this->customer->isCollectionFromDomicile()) {
            $this->response->redirect($this->url->link('account/customer_order', '', true));
        }

        $this->load->model('account/sales_order/CloudWholesaleFulfillment');
        $this->model = $this->model_account_sales_order_CloudWholesaleFulfillment;

        $this->load->language('account/sales_order/cloud_wholesale_fulfillment');
    }


    // region cwf
    public function tabsPage()
    {
        return view('account/sales_order/cloud_wholesale_fulfillment_tab');
    }

    public function uploadPage()
    {
        return view('account/sales_order/cloud_wholesale_fulfillment_upload');
    }

    // 上传文件
    public function uploadCwfFile(FileUploadForm $fileUploadForm)
    {
        try {
            $data = dbTransaction(function () use ($fileUploadForm) {
                return $fileUploadForm->save();
            });
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception | \PhpOffice\PhpSpreadsheet\Exception | Throwable $e) {
            Logger::error($e);
            return response()->json(['error' => $e->getMessage()]);
        }
        ['error' => $error, 'id' => $id] = $data;

        if (!empty($error)) {
            return response()->json(['error' => join('<br>', $error)]);
        }
        $cwfRepo = app(CloudWholesaleFulfillmentRepository::class);
        return response()->json(
            [
                'error' => '',
                'text' => 'Upload Successfully.',
                'next' => $cwfRepo->getBatchCWFBuyNowUrl($id)
            ]
        );
    }

    public function uploadFileInstructions()
    {
        return view('account/sales_order/cloud_wholesale_fulfillment_instruction');
    }

    // 下载批量上传模板
    public function downloadUploadTemplateFile()
    {
        return StorageLocal::storage()->browserDownload('download/cwf/OrderTemplateCWF.xlsx');
    }

    // 上传记录列表
    public function recordPage()
    {
        $data['filter_orderDate_from'] = request('filter_orderDate_from', date('Y-m-d H:i:s', strtotime('-1 year')));
        $data['filter_orderDate_to'] = request('filter_orderDate_to', date('Y-m-d H:i:s', time()));
        $search = new FileUploadHistorySearch((int)customer()->getId());
        $dataProvider = $search->search($data);
        $data['list'] = $dataProvider->getList();
        $data['paginator'] = $dataProvider->getPaginator();
        return view('account/sales_order/cloud_wholesale_fulfillment_record', $data);
    }

    // 下载上传记录
    public function recordDownload()
    {
        $fileUpload = CloudWholesaleFulfillmentFileUpload::find(request('id'))->fileUpload;
        return StorageCloud::root()->browserDownload($fileUpload->path, $fileUpload->orig_name);
    }

    // endregion cwf

//region list

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function index()
    {
        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home')
            ],
            [
                'text' => $this->language->get('heading_parent_title'),
                'href' => $this->url->link('account/customer_order', '', true)
            ],
            [
                'text' => $this->language->get('heading_title_cloud_wholesale_fulfillment'),
                'href' => 'javascription:void(0)'
            ]
        ];

        $data['is_home_pickup'] = $this->customer->isCollectionFromDomicile();
        $data['url_sales_order'] = $this->url->link('account/customer_order', '', true);
        $data['url_get_list'] = $this->url->link('Account/Sales_Order/CloudWholesaleFulfillment/getList', '', true);
        $data['url_cwf_info'] = $this->url->link('Account/Sales_Order/CloudWholesaleFulfillment/info&id=', '', true);
        $data['url_product_information'] = $this->url->link('product/product&product_id=', '', true);
        $data['url_tracking_xpo'] = 'https://track.xpoweb.com/ltl-shipment/';
        $data['url_tracking_ups'] = 'https://www.ups.com/us/en/SearchResults.page?q=';
        $data['url_tracking_fedex'] = 'https://www.fedex.com/apps/fedextrack/?action=track&tracknumbers=';
        $data['url_apply_rma'] = $this->url->link('account/rma_management&filter_order_id=', '', true);
        $data['url_help'] = $this->url->link('information/information&information_id=' . $this->config->get('cwf_help_id'), '', true);

        $status_params = [
            'status_all' => null,
            'status_0_2' => [0, 1, 2],
            'status_3' => [3],
            'status_4' => [4],
            'status_5_6' => [5, 6],
            'status_7' => [7],
            'status_cancel' => [16]
        ];
        foreach ($status_params as $_k => $status_param) {
            $data['count_' . $_k] = $this->model->countByStatus($this->customer->getId(), $status_param);
        }

        // 如果 发FBA 且已备货的订单数大于 0 ，则首先加载 已备货的列表
        $countFBAAndCP = $this->model->countFBAAndCP($this->customer->getId());
        $data['init_status_type'] = 'all';
        if ($countFBAAndCP > 0) {
            $data['init_status_type'] = 'cp';
            $data['tips_cp'] = str_replace('_x_', $countFBAAndCP, $this->language->get('tips_fba_cp'));
        }

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        //$data['footer'] = $this->load->controller('common/footer');
        //$data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('account/sales_order/cloud_wholesale_fulfillment_list', $data));
    }

    /**
     * 获取 列表
     * @var int start_page
     * @var int page_size
     * @var string sort create_time/cwf_status
     * @var string order DESC/ASC
     * @var string status_type all/bp/cp/ps/shipping/delivered/cancel
     */
    public function getList()
    {
        trim_strings($this->request->get);

        $startPage = $this->request->get['startPage'] ?? 1;
        $pageSize = $this->request->get['pageSize'] ?? 10;
        $sort = $this->request->get['sort'] ?? 'create_time';
        $order = $this->request->get['order'] ?? 'DESC';
        $order = strtoupper($order);

        !in_array($sort, ['create_time', 'cwf_status']) && $sort = 'create_time';
        !in_array($order, ['DESC', 'ASC']) && $order = 'DESC';
        if ($sort == 'create_time') {
            $sort = 'so.create_time';
        } else {
            $sort = 'cl.cwf_status';
        }

        $allowed_statuses = [
            'all' => null,
            'bp' => [0, 1, 2],
            'cp' => [3],
            'ps' => [4],
            'shipping' => [5, 6],
            'delivered' => [7],
            'cancel' => [16]
        ];
        $status_type = $this->request->get['status_type'] ?? 'all';
        !in_array($status_type, array_keys($allowed_statuses)) && $status_type = 'all';
        $result = $this->model->getListByStatus($this->customer->getId(), $allowed_statuses[$status_type], $startPage, $pageSize, $sort, $order);
        $cl_ids = [];
        foreach ($result['rows'] as $cl_obj) {
            $cl_ids[] = $cl_obj->id;
        }


        // 状态字段对应的颜色
        $colors = [
            3 => '#FA6400',
            5 => '#6DD400',
            6 => '#6DD400',
            7 => '#6DD400',
            'other' => '#333333'
        ];

        $items = $this->model->getItemsByIDs($cl_ids);

        $product_ids = [];
        foreach ($items as $item) {
            $product_ids[] = $item->product_id;
        }
        $tags = $this->model->getProductTags(array_unique($product_ids));

        $id_items = [];
        foreach ($items as $item_obj) {
            if (!isset($id_items[$item_obj->cloud_logistics_id])) {
                $id_items[$item_obj->cloud_logistics_id] = [
                    'products' => [
                        $item_obj->product_id => [
                            'sku' => $item_obj->item_code,
                            'is_combo' => $item_obj->combo_flag,
                            'is_part' => $item_obj->part_flag,
                            'tags' => $tags[$item_obj->product_id] ?? [],
                        ],
                    ],
                    'total_qty' => $item_obj->qty,
                ];
            } else {
                $id_items[$item_obj->cloud_logistics_id]['products'][$item_obj->product_id] = [
                    'sku' => $item_obj->item_code,
                    'is_combo' => $item_obj->combo_flag,
                    'is_part' => $item_obj->part_flag,
                    'tags' => $tags[$item_obj->product_id] ?? [],
                ];
                $id_items[$item_obj->cloud_logistics_id]['total_qty'] += $item_obj->qty;
            }
        }

        $id_total_pallet_qty = $this->model->countTotalPalletQTYByIDs($cl_ids);
        $id_tracking_numbers = $this->model->getTrackingNumbersByIDs($cl_ids);
        foreach ($id_tracking_numbers as &$id_tracking_number) {
            foreach ($id_tracking_number as &$tracking_number) {
                $tracking_number->shipping_status_str = $this->language->get('shipping_status_' . $tracking_number->shipping_status);
            }
        }

        foreach ($result['rows'] as $key => &$value) {
            $value->service_type_str = $value->service_type == 1 ? $this->language->get('info_text_cwf_type_fba') : $this->language->get('info_text_cwf_type_other');
            $value->cwf_status_str = $this->language->get('status_' . $value->cwf_status);
            //101843 0对字符串数组用in_array 会返回true
            if (array_key_exists($value->cwf_status, $colors)) {
                //if (in_array($value->cwf_status, array_keys($colors))) {
                //101843 增加限制只有没传超重标的
                if ($value->cwf_status == 3 && ($value->service_type != 1 || $value->pallet_label_file_id > 0)) {
                    $value->cwf_status_color = $colors['other'];
                } else {
                    $value->cwf_status_color = $colors[$value->cwf_status];
                }
            } else {
                $value->cwf_status_color = $colors['other'];
            }

            if (isset_and_not_empty($id_items, $value->id)) {
                $value->products = $id_items[$value->id]['products'];
                $value->total_qty = $id_items[$value->id]['total_qty'];
            } else {
                $value->products = [];
                $value->total_qty = 0;
            }

            $value->total_pallet_qty = $id_total_pallet_qty[$value->id] ?? '-';
            $value->tracking_numbers = $id_tracking_numbers[$value->id] ?? [];
            $value->can_rma = in_array($value->cwf_status, [16, 7]);
        }
        $this->response->returnJson($result);
    }
//end of region

//region info
    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function info()
    {
        $this->document->setTitle($this->language->get('heading_title_order_info'));

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home')
            ],
            [
                'text' => $this->language->get('heading_parent_title'),
                'href' => $this->url->link('account/customer_order', '', true)
            ],
            [
                'text' => $this->language->get('heading_title_cloud_wholesale_fulfillment'),
                'href' => $this->url->link('account/sales_order/sales_order_management', 'tab=2', true),

            ],
            [
                'text' => $this->language->get('heading_title_order_info'),
                'href' => 'javascription:void(0)'
            ],
        ];

        trim_strings($this->request->get);

        if (!isset($this->request->get['id'])) {
            $this->response->redirect($this->url->link('error/not_found', '', true));
            return;
        }

        $id = $this->request->get('id');
        $data['id'] = $id;
        $info = $this->model->getInfo($id);

        if (empty($info)) {
            $this->response->redirect($this->url->link('error/not_found', '', true));
            return;
        }
        // 状态字段对应的颜色
        $colors = [
            3 => '#FA6400',
            5 => '#6DD400',
            6 => '#6DD400',
            7 => '#6DD400',
            'other' => '#333333'
        ];
        $data = array_merge($data, obj2array($info));
        $data['full_address'] = '';
        trim($data['address']) && $data['full_address'] .= $data['address'] . ', ';
        trim($data['city']) && $data['full_address'] .= $data['city'] . ', ';
        trim($data['state']) && $data['full_address'] .= $data['state'] . ', ';
        trim($data['zip_code']) && $data['full_address'] .= $data['zip_code'] . ', ';
        trim($data['country']) && $data['full_address'] .= $data['country'] . ', ';

        $data['full_address'] = trim($data['full_address'], ', ');
        $data['sales_order_code'] = $info->sales_order_code;
        $data['type_str'] = (int)$info->service_type === 1 ? $this->language->get('info_text_cwf_type_fba') : $this->language->get('info_text_cwf_type_other');
        $data['status_str'] = $this->language->get('status_' . $info->cwf_status);
        if (array_key_exists($info->cwf_status, $colors)) {
            //101843 增加限制只有没传超重标的
            if ($info->cwf_status == 3 && ($info->service_type != 1 || $info->pallet_label_file_id > 0)) {
                $data['status_color'] = $colors['other'];
            } else {
                $data['status_color'] = $colors[$info->cwf_status];
            }
        } else {
            $data['status_color'] = $colors['other'];
        }
        $data['is_fba'] = (int)$info->service_type === 1;
        if (in_array($info->cwf_status, [7, 16])) {
            /**
             * 因为是取消订单，则只考虑返金
             */
            $rma_arr = $this->model->getRMAIDByOrderID($info->purchase_order_id);
            // cancel rma不显示
            $rma_arr = array_filter($rma_arr, function ($item) {
                return $item->cancel_rma == 0;
            });
            array_walk($rma_arr, function ($rma) {
                if ($rma->seller_status == 2) {
                    if ($rma->status_refund == 1) {
                        $rma->status_str = 'Agree';
                    } else if ($rma->status_refund == 2) {
                        $rma->status_str = 'Refuse';
                    }
                } else {
                    $rma->status_str = 'Pending';
                }
            });
            $data['rma_arr'] = $rma_arr;
        }

        //items
        $this->load->model('tool/image');
        $itemObjs = $this->model->getItems($id);
        $items = [];
        $product_ids = [];
        $total_packages = 0;
        $total_volume = 0;
        $isInch = false;//体积单位是否是立方英尺
        foreach ($itemObjs as $_k => $itemObj) {
            $product_ids[] = $itemObj->product_id;
            $temp = [
                'num' => $_k + 1,
                'product_id' => $itemObj->product_id,
                'sku' => $itemObj->sku,
                'product_img_url' => $this->model_tool_image->resize($itemObj->image, 40, 40),
                'is_combo' => $itemObj->combo_flag,
                'store' => $itemObj->store,
                'merchant_sku' => $itemObj->merchant_sku,
                'fn_sku' => $itemObj->fn_sku,
                'qty' => $itemObj->qty,
                'pak_file_name' => $itemObj->pak_file_name,
                'pak_file_name_30' => truncate($itemObj->pak_file_name, 15),
                'pak_file_path' => StorageCloud::upload()->getUrl($itemObj->pak_file_path),
                'pro_file_name' => $itemObj->pro_file_name,
                'pro_file_name_30' => truncate($itemObj->pro_file_name, 15),
                'pro_file_path' => StorageCloud::upload()->getUrl($itemObj->pro_file_path),
                'team_lift_status' =>  $itemObj->team_lift_status,
            ];
            if ($itemObj->combo_flag) {
                $temp['weight'] = '--';
                $temp['l_w_h'] = '--';
                $temp['volume'] = '--';
                $sons = $this->model->getComboItems($itemObj->order_product_info_id, $itemObj->product_id);
                foreach ($sons as $i => $son) {
                    $son->qty = $son->qty * $itemObj->qty;
                    //102497 已提交的数据还是用立方米，提交的数据改为英尺
                    if (!empty($son->volume_inch)) {
                        $isInch = true;
                        //新数据用立方英尺
                        $volume = $son->volume_inch . ' ' . $this->language->get('volume_class_inch');
                        $lengthClassInch = $this->language->get('length_class_inch');
                        $lwh = round($son->length_inch, 2) . " {$lengthClassInch} * " . round($son->width_inch, 2) . " {$lengthClassInch} * " . round($son->height_inch, 2) . " {$lengthClassInch}";
                        $total_volume += $son->qty * $son->volume_inch;
                    } else {
                        $volume = $son->volume . ' ' . $this->language->get('volume_class_cm');
                        $lengthClassCm = $this->language->get('length_class_cm');
                        $lwh = round($son->length_cm, 2) . " {$lengthClassCm} * " . round($son->width_cm, 2) . " {$lengthClassCm} * " . round($son->height_cm, 2) . " {$lengthClassCm}";
                        $total_volume += $son->qty * $son->volume;
                    }
                    $temp['sons'][] = [
                        'package' => 'package ' . ($i + 1),
                        'product_id' => $son->set_product_id,
                        'sku' => $son->sku,
                        'store' => $itemObj->store,
                        'qty' => $son->qty,
                        'weight' => round($son->weight_lbs, 2) . ' lb',
                        'l_w_h' => $lwh,
                        'volume' => $volume,
                    ];
                    $product_ids[] = $son->set_product_id;
                    $total_packages += $son->qty;

                }
                $temp['sons_num'] = count($temp['sons']);
            } else {
                if (!empty($itemObj->volume_inch)) {
                    //新数据用立方英尺
                    $isInch = true;
                    $temp['volume'] = $itemObj->volume_inch . ' ' . $this->language->get('volume_class_inch');
                    $lengthClassInch = $this->language->get('length_class_inch');
                    $temp['l_w_h'] = round($itemObj->length_inch, 2) . " {$lengthClassInch} * " . round($itemObj->width_inch, 2) . " {$lengthClassInch} * " . round($itemObj->height_inch, 2) . " {$lengthClassInch}";
                    $total_volume += $itemObj->qty * $itemObj->volume_inch;
                } else {
                    $temp['volume'] = $itemObj->volume . ' ' . $this->language->get('volume_class_cm');
                    $lengthClassCm = $this->language->get('length_class_cm');
                    $temp['l_w_h'] = round($itemObj->length_cm, 2) . " {$lengthClassCm} * " . round($itemObj->width_cm, 2) . " {$lengthClassCm} * " . round($itemObj->height_cm, 2) . " {$lengthClassCm}";
                    $total_volume += $itemObj->qty * $itemObj->volume;
                }
                $temp['weight'] = round($itemObj->weight_lbs, 2) . ' lb';
                $total_packages += $itemObj->qty;

            }
            $items[] = $temp;
        }
        $data['total_packages'] = $total_packages;
        $data['total_volume'] = $total_volume;
        $data['is_inch'] = $isInch;
        $data['total_volume_show'] = $total_volume;
        if ($isInch) {
            if ($total_volume < 100) {
                $data['is_less_2'] = true;
                $data['total_volume_show'] = '100.0';
                $data['tips_total_volume_less_2'] = str_replace(
                    ['_total_volume_', '_origin_volume_'],
                    [$data['total_volume_show'], $total_volume],
                    $this->language->get('tips_total_volume_less_inch'));
            }
        } else {
            if ($total_volume < 2) {
                $data['is_less_2'] = true;
                $data['total_volume_show'] = '2.0';
                $data['tips_total_volume_less_2'] = str_replace(
                    ['_total_volume_', '_origin_volume_'],
                    [$data['total_volume_show'], $total_volume],
                    $this->language->get('tips_total_volume_less_2'));
            }
        }

        // 获取 tags 并回填到子项中
        $tags = $this->model->getProductTags($product_ids);
        foreach ($items as &$item) {
            if (isset($tags[$item['product_id']])) {
                $item['tags'] = $tags[$item['product_id']];
            }
            if ($item['is_combo'] == 1) {
                foreach ($item['sons'] as $s_k => $son) {
                    if (isset($tags[$son['product_id']])) {
                        $item['sons'][$s_k]['tags'] = $tags[$son['product_id']];
                    }
                }
            }
        }
        $data['items'] = $items;
        //end of items

        // tracking-number
        $data['sum_pallet'] = $this->model->sumTotalPalletQTYByID($id);
        $data['tracking_order_status'] = $info->cwf_status == 5 ? 'In-Process Shipment' : $this->language->get('status_' . $info->cwf_status);
        $trackingObjs = $this->model->getTrackingByID((int)$id);
        $data['trackings'] = [];
        foreach ($trackingObjs as $trackingObj) {
            $data['trackings'][] = [
                'pallet_qty' => $trackingObj->pallet_qty,
                'carrier' => $trackingObj->carrier,
                'tracking_number' => $trackingObj->tracking_number,
                'shipping_status' => $trackingObj->shipping_status,
                'shipping_status_str' => $this->language->get('shipping_status_' . $trackingObj->shipping_status),
                'bol_signed_file' => $trackingObj->bolSignedFile ? ['path' => $trackingObj->bolSignedFile->filePath, 'name' => $trackingObj->bolSignedFile->fileName] : null,
                'pod_file' => $trackingObj->podFile ? ['path' => $trackingObj->podFile->filePath, 'name' => $trackingObj->podFile->fileName] : null,
            ];
        }
        //end of tracking-number

        // other-information & order comments
        $data['tl_file_path'] = StorageCloud::upload()->getUrl($info->tl_file_path);
        $data['tl_file_name'] = truncate($info->tl_file_name, 30);
        if ($data['is_fba']) {
            if (!empty($info->pallet_label_file_id) && !empty($info->lb_file_name)) {
                $data['show_label_upload_btn'] = false;
                $data['lb_file_name'] = truncate($info->lb_file_name, 30);
                $data['lb_file_path'] = StorageCloud::upload()->getUrl($info->lb_file_path);
            } else {
                if ($info->cwf_status == 3) {
                    $data['show_label_upload_btn'] = true;
                } else {
                    $data['show_label_upload_btn'] = false;
                }
            }
        } else {
            $data['show_label_upload_btn'] = false;
        }
        $data['attachments'] = [];
        $attachmentObjs = $this->model->getAttachments($id);
        foreach ($attachmentObjs as $attachmentObj) {
            $data['attachments'][] = [
                'file_name' => truncate($attachmentObj->file_name, 30),
                'file_path' => StorageCloud::upload()->getUrl($attachmentObj->file_path),
            ];
        }
        //end of other-information

        $data['url_product_info'] = $this->url->link('product/product&product_id=', '', true);
        $data['url_product_tag_prefix'] = HTTPS_SERVER . 'image' . DIRECTORY_SEPARATOR;
        $data['url_tracking_xpo'] = 'https://track.xpoweb.com/ltl-shipment/';
        $data['url_tracking_ups'] = 'https://www.ups.com/us/en/SearchResults.page?q=';
        $data['url_tracking_fedex'] = 'https://www.fedex.com/apps/fedextrack/?action=track&tracknumbers=';
        $data['url_upload'] = $this->url->link('Account/Sales_Order/CloudWholesaleFulfillment/upload', '', true);
        $data['url_save_label'] = $this->url->link('Account/Sales_Order/CloudWholesaleFulfillment/saveLabel', '', true);
        $data['url_purchase_order'] = $this->url->link('account/order/purchaseOrderInfo&order_id=', '', true);
        $data['url_rma_order'] = $this->url->link('account/rma_order_detail&rma_id=', '', true);

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        if (isset($this->session->data['warning'])) {
            $data['warning'] = session('warning');
            $this->session->remove('warning');
        } else {
            $data['warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = session('success');
            $this->session->remove('success');
        } else {
            $data['success'] = '';
        }

        $this->response->setOutput($this->load->view('account/sales_order/cloud_wholesale_fulfillment_info', $data));
    }

    public function upload()
    {
        $allow_type = ['application/pdf'];
        $allow_size = 30;   // 单位M
        if (empty($this->request->files['file'])) {
            $result = [
                'code' => 1,
                'msg' => 'Please select a .PDF file',
            ];
            $this->response->returnJson($result);
        }

        if (empty($this->request->files['file']['type']) || !in_array($this->request->files['file']['type'], $allow_type)) {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_file_type'),
            ];
            $this->response->returnJson($result);
        }
        if (!isset($this->request->files['file']['name']) || strtolower(substr($this->request->files['file']['name'], -4)) != '.pdf') {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_file_type'),
            ];
            $this->response->returnJson($result);
        }
        if (empty($this->request->files['file']['size']) || $this->request->files['file']['size'] > $allow_size * 1024 * 1024) {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_file_size'),
            ];
            $this->response->returnJson($result);
        }

        $content = file_get_contents($this->request->files['file']['tmp_name']);

        if (preg_match('/\<\?php/i', $content)) {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_file_type'),
            ];
            $this->response->returnJson($result);
        }

        $new_file_name = token(33) . '.pdf';
        $new_file_path = 'cwf/' . $this->customer->getId() . '/' . $new_file_name;
        if (!is_dir(dirname(DIR_UPLOAD . $new_file_path))) {
            mkdir(dirname(DIR_UPLOAD . $new_file_path), 700, true);
        }
        move_uploaded_file($this->request->files['file']['tmp_name'], DIR_UPLOAD . $new_file_path);

        $file_id = $this->model->uploadPalletLabelFile([
            'file_name' => $this->request->files['file']['name'],
            'file_path' => $new_file_path,
            'file_type' => 'pdf',
            'customer_id' => $this->customer->getId()
        ]);

        $result = [
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'file_id' => $file_id,
                'file_url' => HTTPS_SERVER . 'storage/upload/' . $new_file_path,
                'file_name' => truncate($this->request->files['file']['name'], 30)
            ]
        ];
        $this->response->returnJson($result);
    }

    public function saveLabel()
    {
        if (!isset($this->request->post['id']) || !isset($this->request->post['file_id'])) {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_file_type'),
            ];
            $this->response->returnJson($result);
        }

        $obj = $this->model->getPalletLabel($this->request->post['id']);
        if (empty($obj)) {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_timeout')
            ];
            $this->response->returnJson($result);
        }

        if (!empty($obj->pallet_label_file_id)) {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_pallet_label_upload')
            ];
            $this->response->returnJson($result);
        }

        if (!$this->model->checkLabelFile($this->request->post['file_id'])) {
            $result = [
                'code' => 2,
                'msg' => $this->language->get('error_upload_again')
            ];
            $this->response->returnJson($result);
        }

        $this->model->savePalletLabel($this->request->post['id'], $this->request->post['file_id'], $this->customer->getId());

        $result = [
            'code' => 200,
            'msg' => 'Submitted Successfully'
        ];
        $this->response->returnJson($result);

    }

//end of region
}
