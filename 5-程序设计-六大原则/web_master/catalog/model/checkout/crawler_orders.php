<?php

/**
 * Class ModelCheckoutcrawlerorders
 * @property ModelCheckoutOrder $model_checkout_order
 */
class ModelCheckoutcrawlerorders extends Model
{
    public function crawlerOrders($order_id, $order_status_id)
    {
        $this->load->model('checkout/order');
        // 获取订单
        $order_info = $this->model_checkout_order->getOrder($order_id);
        // 获取订单
        if (!$order_info["order_status"] && $order_status_id) {
            // 获取顾客信用额度
            $line_of_credit = doubleval($this->customer->getLineOfCredit());
            if ($line_of_credit < (double)$order_info['total']) {
                // 信用额度小于总金额不能购买
                $result["status"] = "4";
                $result["msg"] = "Insufficient line of credit balance for transaction.";
                return $result;
            }
            // 获取订单产品
            $order_products = $this->model_checkout_order->getOrderProducts($order_id);
            // 获取产品
            $product_ids = array();
            $productQtyMap = array();
            foreach ($order_products as $product) {
                $product_ids[] = $product["product_id"];
                // 存放数量
                $productQtyMap[$product["product_id"]] = $product;
            }
            // 获取产品所对应的供应商
            $products_sellers = $this->getSellerAndProductsByProductIds($product_ids);
            $sellers_to_products = array();
            if ($products_sellers) {
                foreach ($product_ids as $prod_id){
                    foreach ($products_sellers->rows as $product_seller) {
                        if($prod_id == $product_seller['product_id']){
                            $sellers_to_products[$product_seller["customer_id"]][] = $product_seller;
                            break;
                        }
                    }
                }
            }
            $sellers_to_products_keys = array_keys($sellers_to_products);
            $post_data = array();
            $self_operated = false;
            foreach ($sellers_to_products_keys as $key) {
                if ($key == 1) {
                    // Coaster
                    $account = "71889"; // 供应商账号
                    $pwd = "Bulkea18961!"; // 供应商密码
                    $platForm = 1;
                    $productInfo = array();
                    $sellerProducts = $sellers_to_products[$key];
                    foreach ($sellerProducts as $product) {
                        $productId = $product["product_id"];
                        $mpn = $product["mpn"];
                        $productQty = $productQtyMap[$productId]["quantity"];
                        $productInfo[] = array(
                            "itemcode" => $mpn,
                            "qty" => $productQty
                        );
                    }
                    $post_data[] = array(
                        "account" => $account,
                        "pwd" => $pwd,
                        "platForm" => $platForm,
                        "productInfo" => $productInfo
                    );
                } else if ($key == 2) {
                    // Poundex
                    $account = "gaby.liu@comptree.net"; // 供应商账号
                    $pwd = "Bulkea18961"; // 供应商密码
                    $platForm = 3;
                    $productInfo = array();
                    $sellerProducts = $sellers_to_products[$key];
                    foreach ($sellerProducts as $product) {
                        $productId = $product["product_id"];
                        $mpn = $product["mpn"];
                        $productQty = $productQtyMap[$productId]["quantity"];
                        $productInfo[] = array(
                            "itemcode" => $mpn,
                            "qty" => $productQty
                        );
                    }
                    $post_data[] = array(
                        "account" => $account,
                        "pwd" => $pwd,
                        "platForm" => $platForm,
                        "productInfo" => $productInfo
                    );
                } else if ($key == 3) {
                    // FOA
                    $account = "gaby.liu@comptree.net"; // 供应商账号
                    $pwd = "Bulkea18961"; // 供应商密码
                    $platForm = 2;
                    $productInfo = array();
                    $sellerProducts = $sellers_to_products[$key];
                    foreach ($sellerProducts as $product) {
                        $productId = $product["product_id"];
                        $mpn = $product["mpn"];
                        $productQty = $productQtyMap[$productId]["quantity"];
                        $productInfo[] = array(
                            "itemcode" => $mpn,
                            "qty" => $productQty
                        );
                    }
                    $post_data[] = array(
                        "account" => $account,
                        "pwd" => $pwd,
                        "platForm" => $platForm,
                        "productInfo" => $productInfo
                    );
                }
                /**
                 * 停掉ACME的订单、产品信息等爬虫程序
                 */
//                else if ($key == 4) {
//                    // ACME
//                    $account = "gaby.liu@comptree.net"; // 供应商账号
//                    $pwd = "Bulkea18961"; // 供应商密码
//                    $platForm = 4;
//                    $productInfo = array();
//                    $sellerProducts = $sellers_to_products[$key];
//                    foreach ($sellerProducts as $product) {
//                        $productId = $product["product_id"];
//                        $mpn = $product["mpn"];
//                        $productQty = $productQtyMap[$productId]["quantity"];
//                        $productInfo[] = array(
//                            "itemcode" => $mpn,
//                            "qty" => $productQty
//                        );
//                    }
//                    $post_data[] = array(
//                        "account" => $account,
//                        "pwd" => $pwd,
//                        "platForm" => $platForm,
//                        "productInfo" => $productInfo
//                    );
//                }
                else if ($key == 14) {
                    // Ashley
                    $account = "AD_3530700"; // 供应商账号
                    $pwd = "Hknbd34"; // 供应商密码
                    $platForm = 5;
                    $productInfo = array();
                    $sellerProducts = $sellers_to_products[$key];
                    foreach ($sellerProducts as $product) {
                        $productId = $product["product_id"];
                        $mpn = $product["mpn"];
                        $productQty = $productQtyMap[$productId]["quantity"];
                        $productInfo[] = array(
                            "itemcode" => $mpn,
                            "qty" => $productQty
                        );
                    }
                    $post_data[] = array(
                        "account" => $account,
                        "pwd" => $pwd,
                        "platForm" => $platForm,
                        "productInfo" => $productInfo
                    );
                } else if ($key == 15) {
                    // Modway
                    $account = "gaby.lue@comptree.net"; // 供应商账号
                    $pwd = "Bulkea18961"; // 供应商密码
                    $platForm = 6;
                    $productInfo = array();
                    $sellerProducts = $sellers_to_products[$key];
                    foreach ($sellerProducts as $product) {
                        $productId = $product["product_id"];
                        $mpn = $product["mpn"];
                        $productQty = $productQtyMap[$productId]["quantity"];
                        $productInfo[] = array(
                            "itemcode" => $mpn,
                            "qty" => $productQty
                        );
                    }
                    $post_data[] = array(
                        "account" => $account,
                        "pwd" => $pwd,
                        "platForm" => $platForm,
                        "productInfo" => $productInfo
                    );
                } else {
                    // 自营店铺
                    $self_operated = true;
                    $platForm = 7;
                    $productInfo = array();
                    $sellerProducts = $sellers_to_products[$key];
                    foreach ($sellerProducts as $product) {
                        $productId = $product["product_id"];
                        $mpn = $product["mpn"];
                        $productQty = $productQtyMap[$productId]["quantity"];
                        $productInfo[] = array(
                            "itemcode" => $mpn,
                            "qty" => $productQty
                        );
                    }
                    $post_data[] = array(
                        "platForm" => $platForm,
                        "productInfo" => $productInfo
                    );
                }
            }
            // 调用接口
            if ($post_data) {
                $is_only_self = true;
                foreach ($post_data as $each){
                    if($each['platForm']!=7){
                        $is_only_self = false;
                    }
                }
                if($is_only_self){
                    $result["status"] = "2";
                    $result["is_only_self"] = true;
                    return $result;
                }
                $buyDatas = array();
                $data = $this->sendMsg(URL_REMOTE.'/queryInventory', $post_data);
                $data = $data['content'];
                // 判断有没有库存，从而调用购买接口
                $inventoryShortageArr = array();
                $result = array();
                if ($data) {
                    foreach ($data as $d) {
                        $success = $d["status"];
                        $platForm = $d["platForm"];
                        if ($success) {
                            $account = "";
                            $pwd = "";
                            if ($platForm == "1") {
                                // Coaster
                                $account = "71889"; // 供应商账号
                                $pwd = "Bulkea18961!"; // 供应商密码

                            } else if ($platForm == "2") {
                                // Poundex
                                $account = "gaby.liu@comptree.net";
                                $pwd = "Bulkea18961";
                            } else if ($platForm == "3") {
                                //FOA
                                $account = "gaby.liu@comptree.net";
                                $pwd = "Bulkea18961";
                            } else if ($platForm == "4") {
                                //ACME
                                $account = "gaby.liu@comptree.net";
                                $pwd = "Bulkea18961";
                            } else if ($platForm == "5") {
                                //Ashley
                                $account = "AD_3530700";
                                $pwd = "Hknbd34";
                            } else if ($platForm == "7") {
                                // 自营商品
                                $buyDatas[] = array(
                                    "platForm" => $platForm
                                );
                                continue;
                            }
                            $buyData = array(
                                "platForm" => $platForm,
                                "account" => $account,
                                "pwd" => $pwd,
                                "productInfo" => array(
                                    "totalQty" => $d["totalNum"],
                                    "totalPrice" => $d["totalPrice"],
                                    "orderId" => $order_id,
                                    "productArray" => array()
                                )
                            );
                            $prpductLs = $d["prpductLs"];
                            if ($prpductLs) {
                                foreach ($prpductLs as $pro) {
//                                if ($pro["qty"] && $pro["qtyAvail"]) {
                                    $qty = intval($pro["qty"]);
                                    $qtyAvail = intval($pro["qtyAvail"]);
                                    if ($qty > $qtyAvail) {
                                        // 购买数量大于库存数量
                                        $inventoryShortageArr[] = $pro;
                                    } else {
                                        $buyData["productInfo"]["productArray"][] = array(
                                            "itemcode" => $pro["itemCode"],
                                            "qty" => $pro["qty"]
                                        );
                                    }
//                                }
                                }
                            } else {
                                $result["status"] = "0";
                                $result["msg"] = "查询产品信息接口返回产品列表错误！";
                                return $result;
                            }
                            $buyDatas[] = $buyData;
                        } else {
                            // 调用如果非success，则表示接口调用错误
                            $result["status"] = "0";
                            $result["msg"] = "查询产品信息接口返回失败！";
                            return $result;
                        }
                    }
                } else {
                    $result["status"] = "0";
                    $result["msg"] = "查询产品信息接口返回失败！";
                    return $result;
                }
                // 检查是否供应商的库存充足
                if (count($inventoryShortageArr)) {
                    $result["status"] = "1";
                    $result["data"] = array();
                    foreach ($inventoryShortageArr as $inventoryShortage) {
                        $itemCode = $inventoryShortage["itemCode"];
                        $itemCode = str_replace(array("\r", "\n", "\r\n"), "", $itemCode);
                        foreach ($products_sellers->rows as $product) {
                            if ($product["mpn"] == $itemCode || $product["sku"] == $itemCode) {
                                $productId = $product["product_id"];
                                $result["data"][] = array(
                                    "itemName" => $inventoryShortage["itemName"],
                                    "qty" => $inventoryShortage["qty"],
                                    "qtyAvail" => $inventoryShortage["qtyAvail"]
                                );
                                // 更新产品数量
                                $this->updateProdyctQty($productId, $inventoryShortage["qtyAvail"]);
                            }
                        }
                    }
                    return $result;
                }
                // 调用购买接口
                $post_data = array(
                    "submitParam" => json_encode($buyDatas),
                    "yzcOrderId" => $order_id
                );
                $data = $this->sendMsg(URL_REMOTE.'/submitOrder',$post_data);
                if ($data) {
                    $result["status"] = "2";
                } else {
                    $result["status"] = "0";
                    $result["msg"] = "调用购买爬虫接口失败！";
                }
                return $result;
            } else {
                if ($self_operated) {
                    $result["status"] = "2";
                    return $result;
                }
            }
        } else {
            // 订单之前已经提交过一次
            $result["status"] = "3";
            $result["msg"] = "订单提交！";
            return $result;
        }
    }

    public function saveOrderFile($dataArray)
    {
        // 美国太平洋时间
        date_default_timezone_set('Pacific/Apia');
        $time = time();
        $date = date('Y-m-d H:i:s', $time);
        foreach ($dataArray as $data) {
            // 平台号
            $platOrderNo = $data['platOrderNo'];
            // 订单ID
            $orderId = $data['orderId'];
            $status = $date['status'];
            if ($status == "success") {
                if ($platOrderNo) {
                    // 根据返回的平台号，判断该文件属于哪个seller
                    $seller = null;
                    switch ($platOrderNo) {
                        case "1" :
                            $seller = 1;
                            break; // Coaster
                        case "2" :
                            $seller = 3;
                            break; // Poundex
                    }
                    // PDF存放路径
                    $filePath = $data['filePath'];
                    $sourceId = $data['sourceId'];
                    // 保存数据到oc_order_file表中
                    $sql = "INSERT INTO " . DB_PREFIX . "order_file (order_id, file_path, plat_order_number, source_id, create_date) VALUES (" . $orderId . ",'" . $filePath . "','" . $platOrderNo . "','" . $sourceId . "','" . $date . "')";
                    $this->db->query($sql);
                }
            } else if ($status == "fail") {
                // 接口调用失败
            }
        }
    }

    public function deleteOrders($order_id)
    {
        $sql = "DELETE FROM `oc_order` WHERE `order_id` = " . $order_id;
        $this->db->query($sql);
        $sql = "DELETE FROM `oc_order_product` WHERE `order_id` = " . $order_id;
        $this->db->query($sql);
        $sql = "DELETE FROM `oc_order_total` WHERE `order_id` = " . $order_id;
        $this->db->query($sql);
    }

    public function updateProdyctQty($product_id, $product_qty)
    {
        $sql = "UPDATE `oc_customerpartner_to_product` SET `quantity` = " . $product_qty . " WHERE `product_id` = " . $product_id;
        $this->db->query($sql);
        $sql = "UPDATE `oc_product` SET `quantity` = " . $product_qty . " WHERE `product_id` = " . $product_id;
        $this->db->query($sql);
    }

    public function getProductsByProductIds($product_id = array())
    {
        if (count($product_id)) {
            $sql = "SELECT * FROM `" . DB_PREFIX . "product` WHERE `product_id` IN (";
            foreach ($product_id as $id) {
                $sql = $sql . $id . ",";
            }
            $sql = substr($sql, 0, -1);
            $sql = $sql . ")";
            $result = $this->db->query($sql);
            return $result;
        }
    }

    public function getSellerAndProductsByProductIds($product_id = array())
    {
        if (count($product_id)) {
            $sql = "select ctp.`quantity` as seller_quantity, c.*, p.* from `oc_customerpartner_to_product` ctp left join `oc_customer` c on ctp.`customer_id` = c.`customer_id` left join `oc_product` p on ctp.`product_id` = p.`product_id` where ctp.`product_id` in (";
            foreach ($product_id as $id) {
                $sql = $sql . $id . ",";
            }
            $sql = substr($sql, 0, -1);
            $sql = $sql . ")";
            $result = $this->db->query($sql);
            return $result;
        }
    }

    /**
     * @param string $query_url
     * @param array $post_data
     * @return array
     */
    private function sendMsg($query_url, $post_data)
    {
        // 初始化
        $curl = curl_init();
        // 设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $query_url);
        // 设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        // 设置获取的信息输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // 设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);
        // 取消ssl证书验证
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        // 设置post数据
        $post_data = json_encode($post_data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($post_data)));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        // 执行命令
        $data = curl_exec($curl);
        // 关闭URL请求
        curl_close($curl);
        // 将结果转为数组
        $data = json_decode($data, true);
        return $data;
    }
}
