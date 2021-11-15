<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelMessageMessage $model_message_message
 * Class ModelAccountNotification
 */
class ModelAccountNotification extends Model
{
    // notification功能中查询的键值
    const ACTIVITY_NOTIFICATION_KEY_RMA = 'rma';
    const ACTIVITY_NOTIFICATION_KEY_BID = 'bid';

    /**
     * [getTotalOrders is used to get the total number of order based on order_statuses specified in data]
     * @param  array $data [array of order_statuses]
     * @return [integer]       [number of orders]
     */
    public function getTotalOrders($data = array())
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT DISTINCT COUNT(*) AS total FROM `" . DB_PREFIX . "order`";

            if (isset($data['filter_order_status'])) {
                $implode = array();

                $order_statuses = explode(',', $data['filter_order_status']);

                foreach ($order_statuses as $order_status_id) {
                    $implode[] = "order_status_id = '" . (int)$order_status_id . "'";
                }

                if ($implode) {
                    $sql .= " WHERE (" . implode(" OR ", $implode) . ")";
                }
            } else {
                $sql .= " WHERE order_status_id > '0'";
            }

            if (!empty($data['filter_order_id'])) {
                $sql .= " AND order_id = '" . (int)$data['filter_order_id'] . "'";
            }

            if (!empty($data['filter_customer'])) {
                $sql .= " AND CONCAT(firstname, ' ', lastname) LIKE '%" . $this->db->escape($data['filter_customer']) . "%'";
            }

            if (!empty($data['filter_date_added'])) {
                $sql .= " AND DATE(date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
            }

            if (!empty($data['filter_date_modified'])) {
                $sql .= " AND DATE(date_modified) = DATE('" . $this->db->escape($data['filter_date_modified']) . "')";
            }

            if (!empty($data['filter_total'])) {
                $sql .= " AND total = '" . (float)$data['filter_total'] . "'";
            }

            $sql .= " AND order_id IN (SELECT DISTINCT order_id FROM " . DB_PREFIX . "customerpartner_to_order WHERE customer_id=" . (int)$this->customer->getId() . ")";

            $query = $this->db->query($sql);

            return $query->row['total'];
        }
    }

    /**
     * [getReturnOrderId is used to get the order_id and product name for the specific return id]
     * @param  integer $return_id [return_id]
     * @return [array]             [order_id and product name]
     */
    public function getReturnOrderId($return_id = 0)
    {
        if ($return_id) {
            return $this->db->query("SELECT DISTINCT order_id,product FROM " . DB_PREFIX . "return WHERE return_id = " . (int)$return_id)->row;
        }
    }

    /**
     * [getSellerActivityCount is used to get the total order related activity count of the seller]
     * @return [integer] [count of activities]
     */
    public function getSellerActivityCount()
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT count(DISTINCT ca.key,ca.data,ca.date_added) as total FROM " . DB_PREFIX . "mp_customer_activity ca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (ca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND ca.key IN ('order_account','order_status','return_account')";

            $sql .= ' UNION ALL ';

            $sql .= "SELECT count(DISTINCT ca.key,ca.data,ca.date_added) as total FROM " . DB_PREFIX . "customer_activity ca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (ca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND ca.key IN ('order_account','order_status','return_account')";

            $result = $this->db->query($sql)->rows;
            $total = 0;
            foreach ($result as $item) {
                $total += $item['total'];
            }
            return $total;
        }
    }

    /**
     * [getTotalSellerActivity is used to get the total order related activity of the seller based on options]
     * @param  array $options [all, processing, completed, return]
     * @return [integer]          [number of activities]
     */
    public function getTotalSellerActivity($options = array())
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT count(DISTINCT ca.key,ca.data,ca.date_added) as total FROM " . DB_PREFIX . "mp_customer_activity ca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (ca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND ca.key IN ('order_account','order_status','return_account')";
            if ($options && !in_array('all', $options)) {
                $sql .= " AND (0";

                foreach ($options as $key => $value) {
                    if ($value == 'return') {
                        $sql .= " || ca.key = 'return_account'";
                    } else {
                        $str = '%"status":"' . $value . '"%';
                        $sql .= " || (ca.key = 'order_status' AND ca.data LIKE '" . $str . "')";
                    }
                }

                $sql .= ")";
            }
            $sql .= ' UNION ALL ';

            $sql .= "SELECT count(DISTINCT ca.key,ca.data,ca.date_added) as total FROM " . DB_PREFIX . "customer_activity ca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (ca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND ca.key IN ('order_account','order_status','return_account')";
            if ($options && !in_array('all', $options)) {
                $sql .= " AND (0";

                foreach ($options as $key => $value) {
                    if ($value == 'return') {
                        $sql .= " || ca.key = 'return_account'";
                    } else {
                        $str = '%"status":"' . $value . '"%';
                        $sql .= " || (ca.key = 'order_status' AND ca.data LIKE '" . $str . "')";
                    }
                }

                $sql .= ")";
            }
            $result = $this->db->query($sql)->rows;
            $total = 0;
            foreach ($result as $item) {
                $total += $item['total'];
            }
            return $total;
        }
    }

    /**
     * [getSellerActivity is used to get the seller order related activity details]
     * @param  array $options [all, processing, completed, return]
     * @return [array]          [array of activity details]
     */
    public function getSellerActivity($options = array(), $limit = 10)
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT DISTINCT ca.key,ca.data,ca.date_added,ca.is_read,ca.customer_activity_id,1 as is_mp FROM " . DB_PREFIX . "mp_customer_activity ca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (ca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND ca.key IN ('order_account','order_status','return_account')";
            if ($options && !in_array('all', $options)) {
                $sql .= " AND (0";

                foreach ($options as $key => $value) {
                    if ($value == 'return') {
                        $sql .= " || ca.key = 'return_account'";
                    } else {
                        $str = '%"status":"' . $value . '"%';
                        $sql .= " || (ca.key = 'order_status' AND ca.data LIKE '" . $str . "')";
                    }
                }

                $sql .= ")";
            }
            $sql .= ' UNION ALL ';

            $sql .= "SELECT DISTINCT ca.key,ca.data,ca.date_added,ca.is_read,ca.customer_activity_id,0 as is_mp FROM " . DB_PREFIX . "customer_activity ca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (ca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND ca.key IN ('order_account','order_status','return_account')";
            if ($options && !in_array('all', $options)) {
                $sql .= " AND (0";

                foreach ($options as $key => $value) {
                    if ($value == 'return') {
                        $sql .= " || ca.key = 'return_account'";
                    } else {
                        $str = '%"status":"' . $value . '"%';
                        $sql .= " || (ca.key = 'order_status' AND ca.data LIKE '" . $str . "')";
                    }
                }

                $sql .= ")";
            }
            $start = 0;

            if (isset($this->request->get['page']) && is_numeric($this->request->get['page'])) {
                $start = ((int)$this->request->get['page'] - 1) * 10;
            }

            $sql .= " ORDER BY date_added DESC LIMIT " . (int)$start . "," . (int)$limit;

            return $this->db->query($sql)->rows;
        }
    }

    /**
     * [getSellerProductReviewsTotal is used to get the total reviews of the seller's products]
     * @param  array $options [description]
     * @return [integer]          [number of product reviews]
     */
    public function getSellerProductReviewsTotal($options = array())
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT * FROM " . DB_PREFIX . "review rv LEFT JOIN " . DB_PREFIX . "product_description pd ON (rv.product_id = pd.product_id) WHERE rv.product_id IN (SELECT DISTINCT product_id FROM " . DB_PREFIX . "customerpartner_to_product WHERE customer_id = " . (int)$this->customer->getId() . ") AND pd.language_id =" . (int)$this->config->get('config_language_id');

            $query = $this->db->query($sql)->num_rows;
            return $query;
        }
    }

    /**
     * [getSellerProductReviews is used to get the seller's product review details]
     * @param  array $options [description]
     * @return [array]          [product review details]
     */
    public function getSellerProductReviews($options = array(), $limit = 10)
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT DISTINCT rv.review_id,rv.author,rv.product_id,rv.date_added,pd.name AS product_name FROM " . DB_PREFIX . "review rv LEFT JOIN " . DB_PREFIX . "product_description pd ON (rv.product_id = pd.product_id) WHERE rv.product_id IN (SELECT DISTINCT product_id FROM " . DB_PREFIX . "customerpartner_to_product WHERE customer_id = " . (int)$this->customer->getId() . ") AND pd.language_id =" . (int)$this->config->get('config_language_id');

            $start = 0;

            if (isset($this->request->get['page_product']) && $this->request->get['page_product'] != '{page}') {
                $start = ($this->request->get['page_product'] - 1) * 10;
            }

            $sql .= " ORDER BY rv.date_added DESC LIMIT " . (int)$start . "," . (int)$limit;

            $query = $this->db->query($sql)->rows;
            return $query;
        }
    }

    /**
     * [getSellerProductActivityTotal is used to get the total activities on seller's product(out of stock activity)]
     * @param  array $options [description]
     * @return [integer]          [number of product activities]
     */
    public function getSellerProductActivityTotal($options = array())
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT count(DISTINCT mca.customer_activity_id) as total FROM " . DB_PREFIX . "mp_customer_activity mca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (mca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND mca.key LIKE 'product_%'";

            $query = $this->db->query($sql)->row['total'];
            return $query;
        }
    }

    public function getProductStockTotal()
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT count(DISTINCT mca.customer_activity_id) as total FROM " . DB_PREFIX . "mp_customer_activity mca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (mca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND mca.key LIKE 'product_stock%'";

            $query = $this->db->query($sql)->row['total'];
            return $query;
        }
        return 0;
    }

    public function getReviewTotal()
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT count(DISTINCT mca.customer_activity_id) as total FROM " . DB_PREFIX . "mp_customer_activity mca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (mca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND mca.key LIKE 'product_review%'";

            $query = $this->db->query($sql)->row['total'];
            return $query;
        }
        return 0;
    }

    public function getApprovalTotal()
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT count(DISTINCT mca.customer_activity_id) as total FROM " . DB_PREFIX . "mp_customer_activity mca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (mca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND mca.key LIKE 'product_approve%'";

            $query = $this->db->query($sql)->row['total'];
            return $query;
        }
        return 0;
    }

    /**
     * [getSellerProductActivity is used to get the product activity details of seller]
     * @param  array $options [description]
     * @return [array]          [array of product activities]
     */
    public function getSellerProductActivity($options = array(), $limit = 10)
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT DISTINCT mca.* FROM " . DB_PREFIX . "mp_customer_activity mca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (mca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND mca.key LIKE 'product_%'";

            $start = 0;

            if (isset($this->request->get['page_product']) && $this->request->get['page_product'] != '{page}') {
                $start = ($this->request->get['page_product'] - 1) * 10;
            }

            $sql .= " ORDER BY mca.date_added DESC LIMIT " . (int)$start . "," . (int)$limit;

            $query = $this->db->query($sql)->rows;
            return $query;
        }
    }

    /**
     * [getSellerReviewsTotal is used to get the total reviews of seller]
     * @param  array $options [description]
     * @return [integer]          [number of seller reviews]
     */
    public function getSellerReviewsTotal($options = array())
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT * FROM " . DB_PREFIX . "customerpartner_to_feedback c2f LEFT JOIN " . DB_PREFIX . "customer c ON (c2f.customer_id = c.customer_id) WHERE c2f.seller_id = " . (int)$this->customer->getId();

            $query = $this->db->query($sql)->num_rows;

            return $query;
        }
    }

    /**
     * [getSellerReviews is used to get the seller review details]
     * @param  array $options [description]
     * @return [array]          [array of seller details]
     */
    public function getSellerReviews($options = array(), $limit = 10)
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT DISTINCT c2f.*, CONCAT(c.firstname,' ',c.lastname) AS name FROM " . DB_PREFIX . "customerpartner_to_feedback c2f LEFT JOIN " . DB_PREFIX . "customer c ON (c2f.customer_id = c.customer_id) WHERE c2f.seller_id = " . (int)$this->customer->getId();

            $start = 0;

            if (isset($this->request->get['page_seller']) && $this->request->get['page_seller'] != '{page}') {
                $start = ($this->request->get['page_seller'] - 1) * 10;
            }

            $sql .= " ORDER BY c2f.createdate DESC LIMIT " . (int)$start . "," . (int)$limit;

            $query = $this->db->query($sql)->rows;

            return $query;
        }
    }

    /**
     * [addSellerActivity is used to map the customer_activity into seller activity if it belongs to the seller ]
     * @param integer $activity_id [customer activity id]
     * @param integer $id [order/product id to check whether it belongs to the seller or not]
     * @param string $key [to check the id is product id or order id]
     */
    public function addSellerActivity($activity_id = 0, $id = 0, $key = 'order')
    {
        if ($activity_id && $id) {
            $sellers = array();

            if ($key == 'order') {
                $sellers = $this->db->query("SELECT DISTINCT customer_id FROM " . DB_PREFIX . "customerpartner_to_order WHERE order_id = " . (int)$id)->rows;
            } elseif ($key == 'product') {
                $sellers = $this->db->query("SELECT DISTINCT customer_id FROM " . DB_PREFIX . "customerpartner_to_product WHERE product_id = " . (int)$id)->rows;
            }

            if ($sellers) {
                foreach ($sellers as $key => $value) {
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "seller_activity` SET `activity_id` = '" . (int)$activity_id . "', `seller_id` = '" . (int)$value['customer_id'] . "'");
                }
            }
        }
    }

    /**
     * [getTotalReturns is used to get the total returns of the seller products]
     * @param  array $data [filter array]
     * @return [integer]       [number of returns of seller products]
     */
    public function getTotalReturns($data = array())
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT DISTINCT COUNT(*) AS total FROM `" . DB_PREFIX . "return`r";

            $implode = array();

            if (!empty($data['filter_return_id'])) {
                $implode[] = "r.return_id = '" . (int)$data['filter_return_id'] . "'";
            }

            if (!empty($data['filter_customer'])) {
                $implode[] = "CONCAT(r.firstname, ' ', r.lastname) LIKE '" . $this->db->escape($data['filter_customer']) . "%'";
            }

            if (!empty($data['filter_order_id'])) {
                $implode[] = "r.order_id = '" . $this->db->escape($data['filter_order_id']) . "'";
            }

            if (!empty($data['filter_product'])) {
                $implode[] = "r.product = '" . $this->db->escape($data['filter_product']) . "'";
            }

            if (!empty($data['filter_model'])) {
                $implode[] = "r.model = '" . $this->db->escape($data['filter_model']) . "'";
            }

            if (!empty($data['filter_return_status_id'])) {
                $implode[] = "r.return_status_id = '" . (int)$data['filter_return_status_id'] . "'";
            }

            if (!empty($data['filter_date_added'])) {
                $implode[] = "DATE(r.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
            }

            if (!empty($data['filter_date_modified'])) {
                $implode[] = "DATE(r.date_modified) = DATE('" . $this->db->escape($data['filter_date_modified']) . "')";
            }

            $implode[] = "r.product IN (SELECT DISTINCT pd.name FROM " . DB_PREFIX . "product_description pd LEFT JOIN " . DB_PREFIX . "customerpartner_to_product c2p ON (pd.product_id = c2p.product_id)  WHERE c2p.customer_id =  " . (int)$this->customer->getId() . ")";

            if ($implode) {
                $sql .= " WHERE " . implode(" AND ", $implode);
            }

            $query = $this->db->query($sql);

            return $query->row['total'];
        }
    }

    /**
     * [productStockActivity is used to add the product stock activity]
     * @param  integer $product_id [product id]
     */
    public function productStockActivity($product_id = 0)
    {
        if ($product_id) {
            $this->load->model('catalog/product');

            $product_info = $this->model_catalog_product->getProductBaseInfoByDB($product_id);
            if (isset($product_info['quantity']) && (int)$this->config->get('marketplace_low_stock_quantity') > $product_info['quantity']) {
                $activity_data = array(
                    'id' => $product_id,
                    'product_id' => $product_id,
                    'product_name' => $product_info['name'],
                    'quantity' => $product_info['quantity'],
                );

                $this->addActivity('product_inventory', $activity_data);
            }
        }
    }

    /**
     * [productReviewActivity is used to add the product review activity]
     * @param  integer $product_id [product id]
     * @return [type]              [description]
     */
    public function productReviewActivity($product_id = 0, $review_id = 0, $author = '')
    {
        if ($product_id && $review_id && $author) {
            $this->load->model('catalog/product');

            $product_info = $this->model_catalog_product->getProductBaseInfoByDB($product_id);

            $activity_data = array(
                'id' => $review_id,
                'review_id' => $review_id,
                'product_id' => $product_id,
                'product_name' => $product_info['name'],
                'author' => $author,
            );
            $this->addActivity('product_review', $activity_data);
        }
    }

    /**
     * [addActivity is used to add the activity]
     * @param [string] $key  [product_stock,order_status]
     * @param [type] $data [description]
     */
    public function addActivity($key, $data)
    {
/*        if (isset($data['id'])) {
            $id = $data['id'];
        } else {
            $id = 0;
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "mp_customer_activity` SET `id` = '" . (int)$id . "', `key` = '" . $this->db->escape($key) . "', `data` = '" . $this->db->escape(json_encode($data)) . "', `ip` = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "', `date_added` = NOW()");

        if (isset($data['product_id'])) {
            $this->addSellerActivity($this->db->getLastId(), $data['product_id'], 'product');
        } elseif (isset($data['order_id'])) {
            $this->addSellerActivity($this->db->getLastId(), $data['order_id'], 'order');
        }*/

        /** 新消息中心 **/
        $this->load->model('message/message');
        $this->model_message_message->addSystemMessage($key, $data);

    }

    /**
     * [addSellerOrderActivity is used to add the order activity related to the seller]
     * @param array $activity_id [activity_id]
     */
    public function addSellerOrderActivity($activity_id = array(), $key = array(), $data = array())
    {

        if ($activity_id) {
            $order_id = 0;
            $this->load->model('account/notification');
            if ($key == 'order_account' || $key == 'order_status') {
                if (isset($data['order_id']) && $data['order_id']) {
                    $this->addSellerActivity($activity_id, $data['order_id']);
                }
            } elseif ($key == 'return_account') {
                if (isset($data['return_id']) && $data['return_id']) {
                    $order_id = $this->getReturnOrderId($data['return_id']);
                    if (isset($order_id['order_id']) && $order_id['order_id']) {
                        $this->addSellerActivity($activity_id, $order_id['order_id']);
                    }
                }
            } elseif ($key == 'product_stock') {
                if (isset($data['product_id']) && $data['product_id']) {
                    $this->addSellerActivity($activity_id, $data['product_id'], 'product');
                }
            }
        }
    }

    public function addViewedNotification($notification_viewed)
    {
        if ((int)$this->customer->getId() && $notification_viewed) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "viewed_activity WHERE customer_id = " . (int)$this->customer->getId());
            $this->db->query("INSERT INTO " . DB_PREFIX . "viewed_activity SET customer_id = " . (int)$this->customer->getId() . ',notification_viewed = ' . (int)$notification_viewed);
        }
    }

    public function getViewedNotifications()
    {
        if ((int)$this->customer->getId()) {
            $result = $this->db->query("SELECT * FROM " . DB_PREFIX . "viewed_activity WHERE customer_id = " . (int)$this->customer->getId())->row;
            if (isset($result['notification_viewed']) && $result['notification_viewed']) {
                return $result['notification_viewed'];
            }
        }
        return 0;
    }

    public function getSellerCategoryActivityTotal($options = array())
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT count(DISTINCT mca.customer_activity_id) as total FROM " . DB_PREFIX . "mp_customer_activity mca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (mca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND mca.key LIKE 'category_%'";

            $query = $this->db->query($sql)->row['total'];
            return $query;
        }
    }

    public function getSellerCategoryActivity($options = array(), $limit = 10)
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT DISTINCT mca.* FROM " . DB_PREFIX . "mp_customer_activity mca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (mca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND mca.key LIKE 'category_%'";

            $start = 0;

            if (isset($this->request->get['page_seller']) && $this->request->get['page_seller'] != '{page}') {
                $start = ($this->request->get['page_seller'] - 1) * 10;
            }

            $sql .= " ORDER BY mca.date_added DESC LIMIT " . (int)$start . "," . (int)$limit;

            $query = $this->db->query($sql)->rows;
            return $query;
        }
    }

    public function updateIsRead($ca_id, $is_mp = 1)
    {
        if ($this->customer->getId()) {
            $tableName = $is_mp == 1 ? 'oc_mp_customer_activity' : 'oc_customer_activity';
            $this->orm->table($tableName . ' as ca')
                ->leftJoin('oc_seller_activity as sa', 'sa.activity_id', '=', 'ca.customer_activity_id')
                ->where([
                    ['ca.customer_activity_id', $ca_id],
                    ['ca.is_read', 0],
                    ['sa.seller_id', (int)$this->customer->getId()],
                ])
                ->update(['is_read' => 1]);
        }
    }

    public function updateIsReadNotByCa($id, $seller_id, $is_order = 1)
    {
        $where = [
            ['sa.seller_id', '=', (int)$seller_id],
            ['ca.is_read', '=', 0],
        ];
        if ($is_order) {
            $where[] = ['ca.data', 'like', '%"order_id":"' . $id . '"%'];
        } else {
            $where[] = ['ca.data', 'like', '%"product_id":"' . $id . '"%'];
        }
        $this->orm->table('oc_mp_customer_activity as ca')
            ->leftJoin('oc_seller_activity as sa', 'sa.activity_id', '=', 'ca.customer_activity_id')
            ->where($where)
            ->update(['ca.is_read' => 1]);
    }

    public function getTotalSellerActivityUnread($options = [])
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT count(DISTINCT ca.key,ca.data,ca.date_added) as total FROM " . DB_PREFIX . "mp_customer_activity ca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (ca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND ca.key IN ('order_account','order_status','return_account') and ca.is_read=0";
            if ($options && !in_array('all', $options)) {
                $sql .= " AND (0";

                foreach ($options as $key => $value) {
                    if ($value == 'return') {
                        $sql .= " || ca.key = 'return_account'";
                    } else {
                        $str = '%"status":"' . $value . '"%';
                        $sql .= " || (ca.key = 'order_status' AND ca.data LIKE '" . $str . "')";
                    }
                }

                $sql .= ")";
            }
            $sql .= ' UNION ALL ';

            $sql .= "SELECT count(DISTINCT ca.key,ca.data,ca.date_added) as total FROM " . DB_PREFIX . "customer_activity ca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (ca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND ca.key IN ('order_account','order_status','return_account') and ca.is_read=0";
            if ($options && !in_array('all', $options)) {
                $sql .= " AND (0";

                foreach ($options as $key => $value) {
                    if ($value == 'return') {
                        $sql .= " || ca.key = 'return_account'";
                    } else {
                        $str = '%"status":"' . $value . '"%';
                        $sql .= " || (ca.key = 'order_status' AND ca.data LIKE '" . $str . "')";
                    }
                }

                $sql .= ")";
            }
            $result = $this->db->query($sql)->rows;
            $total = 0;
            foreach ($result as $item) {
                $total += $item['total'];
            }
            return $total;
        } else {
            return 0;
        }

    }

    public function getSellerActivityUnread($options = [], $limit = 10)
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT DISTINCT ca.key,ca.data,ca.date_added,ca.is_read,ca.customer_activity_id,1 as is_mp FROM " . DB_PREFIX . "mp_customer_activity ca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (ca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND ca.key IN ('order_account','order_status','return_account') and ca.is_read=0";
            if ($options && !in_array('all', $options)) {
                $sql .= " AND (0";

                foreach ($options as $key => $value) {
                    if ($value == 'return') {
                        $sql .= " || ca.key = 'return_account'";
                    } else {
                        $str = '%"status":"' . $value . '"%';
                        $sql .= " || (ca.key = 'order_status' AND ca.data LIKE '" . $str . "')";
                    }
                }

                $sql .= ")";
            }
            $sql .= ' UNION ALL ';

            $sql .= "SELECT DISTINCT ca.key,ca.data,ca.date_added,ca.is_read,ca.customer_activity_id,0 as is_mp FROM " . DB_PREFIX . "customer_activity ca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (ca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND ca.key IN ('order_account','order_status','return_account') and ca.is_read=0";
            if ($options && !in_array('all', $options)) {
                $sql .= " AND (0";

                foreach ($options as $key => $value) {
                    if ($value == 'return') {
                        $sql .= " || ca.key = 'return_account'";
                    } else {
                        $str = '%"status":"' . $value . '"%';
                        $sql .= " || (ca.key = 'order_status' AND ca.data LIKE '" . $str . "')";
                    }
                }

                $sql .= ")";
            }
            $start = 0;

            $sql .= " ORDER BY date_added DESC LIMIT " . (int)$start . "," . (int)$limit;

            return $this->db->query($sql)->rows;
        } else {
            return [];
        }
    }

    public function getProductStockTotalUnread()
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT count(DISTINCT mca.customer_activity_id) as total
FROM " . DB_PREFIX . "mp_customer_activity mca
LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (mca.customer_activity_id = sa.activity_id)
WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND mca.key LIKE 'product_stock%' and mca.is_read=0";

            $query = $this->db->query($sql)->row['total'];
            return $query;
        }
        return 0;
    }

    public function getReviewTotalUnread()
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT count(DISTINCT mca.customer_activity_id) as total
FROM " . DB_PREFIX . "mp_customer_activity mca
LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (mca.customer_activity_id = sa.activity_id)
WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND mca.key LIKE 'product_review%' and mca.is_read=0";

            $query = $this->db->query($sql)->row['total'];
            return $query;
        }
        return 0;
    }

    public function getApprovalTotalUnread()
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT count(DISTINCT mca.customer_activity_id) as total
FROM " . DB_PREFIX . "mp_customer_activity mca
LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (mca.customer_activity_id = sa.activity_id)
WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND mca.key LIKE 'product_approve%' and mca.is_read=0";

            $query = $this->db->query($sql)->row['total'];
            return $query;
        }
        return 0;
    }

    public function getSellerProductActivityUnread($options = [], $limit = 10)
    {
        if ((int)$this->customer->getId()) {
            $sql = "SELECT DISTINCT mca.* FROM " . DB_PREFIX . "mp_customer_activity mca LEFT JOIN " . DB_PREFIX . "seller_activity sa ON (mca.customer_activity_id = sa.activity_id) WHERE sa.seller_id = " . (int)$this->customer->getId() . " AND mca.key LIKE 'product_%' and mca.is_read=0";

            $start = 0;

            if (isset($this->request->get['page_product']) && $this->request->get['page_product'] != '{page}') {
                $start = ($this->request->get['page_product'] - 1) * 10;
            }

            $sql .= " ORDER BY mca.date_added DESC LIMIT " . (int)$start . "," . (int)$limit;

            $query = $this->db->query($sql)->rows;
            return $query;
        } else {
            return [];
        }
    }

    /**
     * 获取review记录的用户昵称
     * @param $review_id
     * @return
     */
    public function getReviewerNickname($review_id)
    {
        $query = $this->db->query("SELECT CONCAT(cus.nickname,'(',cus.user_number,')') AS nickname FROM oc_customer cus INNER JOIN oc_review rev ON cus.customer_id = rev.customer_id WHERE rev.review_id = " . (int)$review_id);
        return $query->row['nickname'];
    }

    public function getMpActivityID($IDArr, $sellerID)
    {
        $objs = $this->orm->table('oc_seller_activity')
            ->where('seller_id', $sellerID)
            ->whereIn('activity_id', $IDArr)
            ->get(['activity_id']);
        $result = [];
        foreach ($objs as $obj) {
            $result[] = $obj->activity_id;
        }
        return $result;
    }

    public function readAll($IDArr, $isMp = 1)
    {
        $table = $isMp ? 'oc_mp_customer_activity' : 'oc_customer_activity';
        $this->orm->table($table)
            ->where('is_read', 0)
            ->whereIn('customer_activity_id', $IDArr)
            ->update(['is_read' => 1]);
    }

    /**
     * @param string $key
     * @param array $data
     * @return int
     */
    public function addActivityUseOrm(string $key, array $data = []): int
    {
        $id = (int)($data['id'] ?? 0);
        return $this->orm
            ->table(DB_PREFIX . 'mp_customer_activity')
            ->insertGetId([
                'id' => $id,
                'key' => $key,
                'data' => json_encode($data),
                'ip' => $this->request->server['REMOTE_ADDR'] ?? '0:0:0:0',
                'date_added' => \Illuminate\Support\Carbon::now(),
            ]);
    }

    /**
     * @param int $rmaId 退返品id
     * @throws Exception
     */
    public function addRmaActivity(int $rmaId)
    {
        $orm = $this->orm;
        $rmaOrder = $orm->table(DB_PREFIX . 'yzc_rma_order')->where(['id' => $rmaId])->first();
        if (!$rmaOrder) {
            throw new Exception('Can not find the rma order!');
        }
/*        $caId = $this->addActivityUseOrm(
            static::ACTIVITY_NOTIFICATION_KEY_RMA,
            [
                'rma_id' => $rmaId,
                'id' => $rmaId,
                'buyer_id' => $rmaOrder->buyer_id,
            ]
        );
        $orm->table(DB_PREFIX . 'seller_activity')->insert(['activity_id' => $caId, 'seller_id' => $rmaOrder->seller_id]);*/

        /** 新消息中心 **/
        $this->load->model('message/message');
        $data = [
            'rma_id' => $rmaId,
            'id' => $rmaId,
            'buyer_id' => $rmaOrder->buyer_id,
        ];
        $this->model_message_message->addSystemMessage(static::ACTIVITY_NOTIFICATION_KEY_RMA, $data);

    }

    /**
     * 设置activity 阅读状态
     * @param int|array|string $activityIds
     * @return int
     */
    public function setRmaIsReadByActivityId($activityIds)
    {
        if (is_int($activityIds) || is_string($activityIds)) {
            $activityIds = [(int)$activityIds];
        }
        return $this->orm
            ->table(DB_PREFIX . 'mp_customer_activity')
            ->whereIn('customer_activity_id', $activityIds)
            ->update(['is_read' => 1]);
    }

    /**
     * 设置activity 阅读状态
     *
     * @param int|array $ids
     * @return int
     */
    public function setRmaIsReadById($ids)
    {
        if (is_int($ids) || is_string($ids)) {
            $ids = [(int)$ids];
        }
        return $this->orm
            ->table(DB_PREFIX . 'mp_customer_activity')
            ->whereIn('id', $ids)
            ->update(['is_read' => 1]);
    }

    /**
     * 获取rma通知总数
     *
     * @param bool|null $isRead null表示获取全部 true:已读 false:未读
     * @return int
     */
    public function getRmaActivityTotal(?bool $isRead): int
    {
        $query = $this->orm->table(DB_PREFIX . 'mp_customer_activity as mca')
            ->leftJoin(DB_PREFIX . 'seller_activity as sa', ['sa.activity_id' => 'mca.customer_activity_id'])
            ->where([
                'mca.key' => static::ACTIVITY_NOTIFICATION_KEY_RMA,
                'sa.seller_id' => (int)$this->customer->getId(),
            ]);
        $query->when($isRead !== null, function (Builder $q) use ($isRead) {
            $q->where(['mca.is_read' => $isRead ? 1 : 0]);
        });

        return $query->count();
    }

    /**
     * 获取rma通知列表 自动分页
     *
     * @param array $filterData
     * @param int $defaultPerPage
     * @return array
     */
    public function getRmaActivityList(array $filterData = [], int $defaultPerPage = 20): array
    {
        $co = new Collection($filterData);
        list($page, $perPage) = $this->getPagePerPage($defaultPerPage, 'page_rma');
        $orm = $this->orm;
        $query = $orm->table(DB_PREFIX . 'mp_customer_activity as mca')
            ->leftJoin(DB_PREFIX . 'seller_activity as sa', ['sa.activity_id' => 'mca.customer_activity_id'])
            ->where(
                [
                    'mca.key' => static::ACTIVITY_NOTIFICATION_KEY_RMA,
                    'sa.seller_id' => (int)$this->customer->getId(),
                ]
            );
        $query->when($co->has('is_read'), function (Builder $q) use ($co) {
            $isRead = (int)$co->get('is_read', 0);
            $isRead = $isRead ? 1 : 0;
            $q->where(['mca.is_read' => $isRead,]);
        });
        $res = $query
            ->orderBy('mca.customer_activity_id', 'DESC')
            ->forPage($page, $perPage)
            ->get();
        $res = $res->map(function ($item) {
            $tempArr = get_object_vars($item);
            $tempArr['data'] = isset($tempArr['data']) && !empty($tempArr['data'])
                ? json_decode($tempArr['data'], true)
                : [];
            return $tempArr;
        });
        return $res->toArray();
    }

    /**
     * 从request的get数据中获取分页信息
     *
     * @param int $defaultPerPage 默认每页列表数目
     * @param string $pageKey 页数关键字
     * @param string $perPageKey 每页列表数关键字
     * @return array  [$page,$perPage,$get]
     */
    public function getPagePerPage($defaultPerPage = 20, string $pageKey = 'page', string $perPageKey = 'perPage'): array
    {
        $co = new Collection($this->request->get);
        $page = (int)$co->get($pageKey, 1);
        $page = ($page <= 0) ? 1 : $page;
        $perPage = (int)$co->get($perPageKey, $defaultPerPage);
        $perPage = ($perPage <= 0) ? $defaultPerPage : $perPage;
        $co->forget([$pageKey, $perPageKey]);

        return [$page, $perPage, $co->toArray()];
    }

    //返点，保证金等BID类型的站内信 start

    /**
     * @param string $bid_type   投标BID的小类型，目前有：返点：rebates
     * @param int $id    BID发起创建时产生的协议，一般使用协议编号，或者唯一性的协议主键  例如：返点类型的tb_sys_rebate_contract.contract_id
     * @throws Exception
     */
    public function addBidActivity(string $bid_type, $id)
    {
        $orm = $this->orm;
        $agreement = null;
        switch ($bid_type) {
            case 'rebates':
                $agreement = $orm->table('tb_sys_rebate_contract')->where(['id' => $id])->first();
                break;
            default:
                break;
        }
        if (!$agreement) {
            throw new Exception('Can not find the agreement record!');
        }

        $sku = '';
        if(isset($agreement->product_id)){
            $query = $this->db->query("select sku from oc_product where product_id = " . (int)$agreement->product_id);
            $sku = $query->row['sku'];
        }

        $activity_data = null;
        switch ($bid_type) {
            case 'rebates':
                $activity_data = [
                    'bid_type' => $bid_type,
                    'buyer_id' => $agreement->buyer_id,
                    'product_id' => $agreement->product_id,
                    'sku' => $sku,
                    'agreement_id' => $agreement->contract_id
                ];
                break;
            default:
                break;
        }
        //$caId = $this->addActivityUseOrm(static::ACTIVITY_NOTIFICATION_KEY_BID, $activity_data);
        //$orm->table(DB_PREFIX . 'seller_activity')->insert(['activity_id' => $caId, 'seller_id' => $agreement->seller_id]);

        //新消息中心
        $this->load->model('message/message');
        $this->model_message_message->addSystemMessage('bid_rebates', $activity_data, $agreement->seller_id);


    }

    /**
     * 获取rma通知总数
     *
     * @param bool|null $isRead null表示获取全部 true:已读 false:未读
     * @return int
     */
    public function getBidActivityTotal(?bool $isRead): int
    {
        $query = $this->orm->table(DB_PREFIX . 'mp_customer_activity as mca')
            ->leftJoin(DB_PREFIX . 'seller_activity as sa', ['sa.activity_id' => 'mca.customer_activity_id'])
            ->where([
                'mca.key' => static::ACTIVITY_NOTIFICATION_KEY_BID,
                'sa.seller_id' => (int)$this->customer->getId(),
            ]);
        $query->when($isRead !== null, function (Builder $q) use ($isRead) {
            $q->where(['mca.is_read' => $isRead ? 1 : 0]);
        });

        return $query->count();
    }

    /**
     * 获取rma通知列表 自动分页
     *
     * @param array $filterData
     * @param int $defaultPerPage
     * @return array
     */
    public function getBidActivityList(array $filterData = [], int $defaultPerPage = 20): array
    {
        $co = new Collection($filterData);
        list($page, $perPage) = $this->getPagePerPage($defaultPerPage, 'page_bid');
        $orm = $this->orm;
        $query = $orm->table(DB_PREFIX . 'mp_customer_activity as mca')
            ->leftJoin(DB_PREFIX . 'seller_activity as sa', ['sa.activity_id' => 'mca.customer_activity_id'])
            ->where(
                [
                    'mca.key' => static::ACTIVITY_NOTIFICATION_KEY_BID,
                    'sa.seller_id' => (int)$this->customer->getId(),
                ]
            );
        $query->when($co->has('is_read'), function (Builder $q) use ($co) {
            $isRead = (int)$co->get('is_read', 0);
            $isRead = $isRead ? 1 : 0;
            $q->where(['mca.is_read' => $isRead,]);
        });
        $res = $query
            ->orderBy('mca.customer_activity_id', 'DESC')
            ->forPage($page, $perPage)
            ->get();
        $res = $res->map(function ($item) {
            $tempArr = get_object_vars($item);
            $tempArr['data'] = isset($tempArr['data']) && !empty($tempArr['data'])
                ? json_decode($tempArr['data'], true)
                : [];
            return $tempArr;
        });
        return $res->toArray();
    }

    public function setBidIsReadByAcId($ids)
    {
        if (is_int($ids) || is_string($ids)) {
            $ids = [(int)$ids];
        }
        return $this->orm
            ->table(DB_PREFIX . 'mp_customer_activity')
            ->whereIn('customer_activity_id', $ids)
            ->update(['is_read' => 1]);
    }

    public function setBidIsReadByAgreementId($agreement_id)
    {
        $where = [
            ['ca.is_read', '=', 0],
            ['ca.key', '=', 'bid']
        ];
        $where[] = ['ca.data', 'like', '%"agreement_id":"' . $agreement_id . '"%'];

        $this->orm->table('oc_mp_customer_activity as ca')
            ->where($where)
            ->update(['ca.is_read' => 1]);
    }
    //返点，保证金等BID类型的站内信 end
}
