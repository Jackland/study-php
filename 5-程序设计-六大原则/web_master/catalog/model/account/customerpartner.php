<?php

use App\Enums\Charge\ChargeType;
use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductAuditStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Models\Pay\LineOfCreditRecord;
use App\Models\Product\ProductAudit;
use App\Services\Product\ProductListsService;
use App\Enums\Order\OcOrderTypeId;
use App\Enums\YzcRmaOrder\RmaType;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use App\Enums\Warehouse\ReceiptOrderStatus;
use App\Enums\Product\ProductStatus;
use App\Models\Product\Option\Option;
use App\Repositories\Product\ProductRepository;
use App\Services\Product\ProductAuditService;
use App\Services\Product\ProductService;
use App\Models\Product\Product;
use App\Models\Link\ProductToCategory;
use App\Services\Product\ProductOptionService;
use Carbon\Carbon;
use App\Repositories\Product\CategoryRepository;
use App\Logging\Logger;
use App\Helper\ProductHelper;

const MPIMAGEFOLDER = 'catalog/';

/**
 * Class ModelAccountCustomerpartner
 * @property ModelExtensionModuleProductShow $model_extension_module_product_show
 * @property ModelAccountCustomerpartnerRebates $model_account_customerpartner_rebates
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 * @property ModelCustomerpartnerHtmlfilter $model_customerpartner_htmlfilter
 * @property ModelCustomerpartnerSpotPrice $model_customerpartner_spot_price
 * @property ModelCustomerpartnerProductManage $model_customerpartner_product_manage
 * @property ModelAccountCustomerpartnerMargin $model_account_customerpartner_margin
 * @property ModelAccountCustomerpartnerFutures $model_account_customerpartner_futures
 * @property ModelAccountCustomerpartnerProductGroup $model_Account_Customerpartner_ProductGroup
 * @property ModelAccountCustomerpartnerProductList $model_account_customerpartner_productlist
 * @property Modelaccountwkcustomfield $model_account_wkcustomfield
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelMessageMessage $model_message_message
 * @property ModelSettingStore $model_setting_store
 */
class ModelAccountCustomerpartner extends Model
{

    /*Membership functions*/

    /**
     * [checkIsMember to check that logged in seller is a member or not and if a member then membership expiry date and other details]
     * @param  [integer] $seller_id [customer id of seller]
     * @return [array|boolean]            [details about membership|false]
     */
    public function checkIsMember($seller_id)
    {
        $membershipDetails = $this->db->query("SELECT * FROM " . DB_PREFIX . "seller_group_customer sgc LEFT JOIN " . DB_PREFIX . "seller_group_name sgn ON (sgc.gid = sgn.id) WHERE sgc.customer_id = '" . (int)$seller_id . "' AND sgn.language_id = '" . (int)$this->config->get('config_language_id') . "' ")->row;
        if ($membershipDetails && isset($membershipDetails['membership_expiry'])) {
            return $membershipDetails;
        } else {
            return false;
        }
    }

    /**
     * [checkSellerOwnProduct to check whether the product belongs to the logged in seller]
     * @param  [integer] $product_id [id of product]
     * @return [boolean]             [product belongs to seller or not (true/false)]
     */
    public function checkSellerOwnProduct($product_id)
    {
        $result = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "customerpartner_to_product WHERE product_id = '" . (int)$product_id . "' AND customer_id = '" . (int)$this->customer->getId() . "' ")->row;
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * [publishProduct to publish particular product]
     * @param  [integer] $product_id [id of product]
     * @return [boolean]             [true]
     */
    public function publishProduct($product_id)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "customerpartner_to_product SET current_status = 'active' WHERE product_id = '" . (int)$product_id . "' ");
        // $this->db->query("UPDATE ".DB_PREFIX."product SET status = '1' WHERE product_id = '".(int)$product_id."' ");
        return true;
    }

    /**
     * [unpublishProduct to unpublish particular product]
     * @param  [integer] $product_id [id of product]
     * @return [boolean]             [true]
     */
    public function unpublishProduct($product_id)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "customerpartner_to_product SET current_status = 'inactive' WHERE product_id = '" . (int)$product_id . "' ");
    }

    /**
     * [relist to relist any product]
     * @param  [integer] $product_id [id of product]
     * @return [boolean]             [true/false]
     */
    public function relist($product_id)
    {
        $relist = $this->db->query("SELECT relist_count FROM " . DB_PREFIX . "customerpartner_to_product WHERE product_id = '" . (int)$product_id . "' AND customer_id = '" . (int)$this->customer->getId() . "' ")->row;
        $category = $this->getProductCategory($product_id);

        $relist_count = $relist['relist_count'] + 1;

        $this->db->query("UPDATE " . DB_PREFIX . "customerpartner_to_product SET current_status = 'active', relist_count = '" . $relist_count . "' WHERE product_id = '" . (int)$product_id . "' AND customer_id = '" . (int)$this->customer->getId() . "' ");

        if (isset($category) && $category) {
            foreach ($category as $key => $value) {

                $result = $this->db->query("SELECT sgpl.quantity,sgpld.days FROM " . DB_PREFIX . "seller_group_product_listing sgpl LEFT JOIN " . DB_PREFIX . "seller_group_product_listing_duration sgpld ON (sgpld.seller_id=sgpl.seller_id) WHERE sgpl.seller_id = '" . (int)$this->customer->getId() . "' AND sgpl.category_id = '" . (int)$value['category_id'] . "' ")->row;

                if ($result && isset($result['quantity']) && $result['quantity']) {
                    $quantity = $result['quantity'] - 1;
                    $this->db->query("UPDATE " . DB_PREFIX . "seller_group_product_listing SET quantity = '" . (int)$quantity . "' WHERE seller_id = '" . (int)$this->customer->getId() . "' AND category_id = '" . (int)$value['category_id'] . "' ");
                    $this->getListingCommission((int)$this->customer->getId(), (int)$value['category_id']);
                } else {
                    $result = $this->db->query("SELECT customerDefaultNoOfListing, customerDefaultListingDuration FROM " . DB_PREFIX . "seller_group_customer WHERE customer_id = '" . (int)$this->customer->getId() . "' ")->row;

                    if ($result && isset($result['customerDefaultNoOfListing']) && $result['customerDefaultNoOfListing']) {
                        $customerDefaultNoOfListing = $result['customerDefaultNoOfListing'] - 1;
                        $this->db->query("UPDATE " . DB_PREFIX . "seller_group_customer SET customerDefaultNoOfListing = '" . $customerDefaultNoOfListing . "' WHERE customer_id = '" . (int)$this->customer->getId() . "' ");
                        $this->getListingCommission((int)$this->customer->getId(), (int)$value['category_id']);
                    } else {
                        return false;
                    }
                }
            }
        } else {
            $result = $this->db->query("SELECT customerDefaultNoOfListing, customerDefaultListingDuration FROM " . DB_PREFIX . "seller_group_customer WHERE customer_id = '" . (int)$this->customer->getId() . "' ")->row;
            if ($result && isset($result['customerDefaultNoOfListing']) && $result['customerDefaultNoOfListing']) {
                $customerDefaultNoOfListing = $result['customerDefaultNoOfListing'] - 1;
                $this->getListingCommission((int)$this->customer->getId(), 0);
                $this->db->query("UPDATE " . DB_PREFIX . "seller_group_customer SET customerDefaultNoOfListing = '" . $customerDefaultNoOfListing . "' WHERE customer_id = '" . (int)$this->customer->getId() . "' ");
                return true;
            } else {
                return false;
            }
        }
    }

    public function getListingCommission($customer_id = 0, $category_id = 0)
    {

        $commission_fee = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seller_group_product_listing_commission` WHERE  seller_id = '" . (int)$customer_id . "'")->row;

        if (!isset($commission_fee['commission_amount'])) {
            $commission_fee['commission_amount'] = 0.00;
        }

        $commission = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seller_group_product_listing_fee` WHERE seller_id = '" . (int)$customer_id . "' AND category_id = '" . (int)$category_id . "'")->row;

        if (isset($commission['fee'])) {
            $commission_fee['commission_amount'] = $commission_fee['commission_amount'] + $commission['fee'];
        } else {
            $commission = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seller_group_customer` WHERE customer_id = '" . (int)$customer_id . "'")->row;
            if ($commission['customerDefaultListingFee']) {
                $commission_fee['commission_amount'] = $commission_fee['commission_amount'] + $commission['customerDefaultListingFee'];
            }
        }

        if ($this->db->query("SELECT * FROM `" . DB_PREFIX . "seller_group_product_listing_commission` WHERE  seller_id = '" . (int)$customer_id . "'")->num_rows) {
            $this->db->query("UPDATE `" . DB_PREFIX . "seller_group_product_listing_commission` SET commission_amount = '" . (float)$commission_fee['commission_amount'] . "' WHERE seller_id = '" . (int)$customer_id . "'");
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "seller_group_product_listing_commission` SET seller_id = '" . (int)$customer_id . "',commission_amount = '" . (float)$commission_fee['commission_amount'] . "' ");
        }

    }

    /**
     * [getProductCategory to get category of any product]
     * @param  [integer] $product_id [id of product]
     * @return [integer|boolean]             [category id or false]
     */
    public function getProductCategory($product_id)
    {
        $result = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "product_to_category p2c WHERE product_id = '" . (int)$product_id . "' ")->rows;
        if ($result) {
            return $result;
        } else {
            return false;
        }
    }
    /**/

    /**
     * [checkProductInCartWishlist to check whether the product is in cart or in wishlist of logged in customer ]
     * @param  [integer] $product_id [id pf product]
     * @return [boolean]             [product exist in cart/wishlist or not (true/false)]
     */
    public function checkProductInCartWishlist($product_id)
    {
        $product = $this->db->query("SELECT current_status FROM " . DB_PREFIX . "customerpartner_to_product WHERE product_id = '" . (int)$product_id . "' ")->row;
        if ($product && ($product['current_status'] == 'expired' || $product['current_status'] == 'inactive' || $product['current_status'] == 'disabled' || $product['current_status'] == 'deleted')) {
            if (!$this->customer->isLogged()) {
                return false;
            }
            $result = $this->db->query("SELECT wishlist FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int)$this->customer->getId() . "' ")->row;

            if ($result && unserialize($result['wishlist'])) {
                $wishlist = unserialize($result['wishlist']);
                if ($this->cart->getProducts()) {
                    foreach ($this->cart->getProducts() as $key => $product) {
                        if ($product['product_id'] == $product_id || (is_array($wishlist) && in_array($product['product_id'], $wishlist))) {
                            return false;
                        }
                    }
                }
                if (in_array($product_id, $wishlist)) {
                    return false;
                }
            }
            return false;
        } else {
            return true;
        }
    }

    /**
     * [getLowStockProducts to get low stock products]
     * @param  [integer] $seller_id [seller is of logged in seller]
     * @return [array] [list of low stock products]
     */
    public function getLowStockProducts($seller_id)
    {
        $result['products'] = $this->db->query("SELECT p.model,pd.name,p.quantity FROM " . DB_PREFIX . "customerpartner_to_product cp2p LEFT JOIN " . DB_PREFIX . "product p ON (p.product_id=cp2p.product_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id=pd.product_id) WHERE cp2p.customer_id = '" . (int)$seller_id . "' AND p.quantity <= '" . (int)$this->config->get('marketplace_low_stock_quantity') . "' AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY p.quantity ASC LIMIT 0,5 ")->rows;

        $result['count'] = count($this->db->query("SELECT p.model,pd.name,p.quantity FROM " . DB_PREFIX . "customerpartner_to_product cp2p LEFT JOIN " . DB_PREFIX . "product p ON (p.product_id=cp2p.product_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id=pd.product_id) WHERE cp2p.customer_id = '" . (int)$seller_id . "' AND p.quantity <= '" . (int)$this->config->get('marketplace_low_stock_quantity') . "' AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY p.quantity ASC ")->rows);

        if ($result) {
            return $result;
        } else {
            return false;
        }
    }

    /**
     * [getMostViewedProducts to get top 5 viewed products]
     * @param  [integer] $seller_id [seller is of logged in seller]
     * @return [array]            [list of top 5 viewed products]
     */
    public function getMostViewedProducts($seller_id)
    {
        $products = $this->db->query("SELECT p.model,pd.name,p.viewed FROM " . DB_PREFIX . "customerpartner_to_product cp2p LEFT JOIN " . DB_PREFIX . "product p ON (p.product_id=cp2p.product_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id=pd.product_id) WHERE cp2p.customer_id = '" . (int)$seller_id . "' AND p.viewed >= 5 AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY p.viewed DESC LIMIT 0,5 ")->rows;
        if ($products) {
            return $products;
        } else {
            return false;
        }
    }

    /**
     * [getSellerDetails to get membership groups with their details]
     * @return [array] [list of all membership groups]
     */
    public function getSellerDetails()
    {
        $query_detail = $this->db->query("SELECT * FROM " . DB_PREFIX . "seller_group sg LEFT JOIN " . DB_PREFIX . "seller_group_name sgn ON (sg.groupid=sgn.id) ")->rows;
        if ($query_detail) {
            return $query_detail;
        } else {
            return false;
        }
    }

    /**
     * For membership only
     * [getRamainingQuantity to get quantity of remaining products that a seller can add]
     * @return [integer] [exact quantity that is left and seller can add that much more products]
     */
    public function getRamainingQuantity()
    {
        $products = $this->getTotalProductsSeller();
        $sql = "SELECT gcquantity FROM " . DB_PREFIX . "seller_group_customer WHERE customer_id = '" . (int)$this->customer->getId() . "' ";
        $inStock = $this->db->query($sql)->row;
        if ($inStock && $inStock['gcquantity'] == 0) {
            return $inStock['gcquantity'];
        } else if (isset($inStock['gcquantity']) && $inStock['gcquantity'] > $products) {
            return ($inStock['gcquantity'] - $products);
        }
    }

    /**
     * [chkSellerProductAccess to check seller has access over that product or not]
     * @param  [integer] $product_id [product id of any product]
     * @return [boolean]             [true or false]
     */
    public function chkSellerProductAccess($product_id)
    {
        $sql = $this->db->query("SELECT c2p.customer_id FROM " . DB_PREFIX . "customerpartner_to_product c2p LEFT JOIN " . DB_PREFIX . "product p ON (c2p.product_id = p.product_id) WHERE p.product_id = '" . (int)$product_id . "' ORDER BY c2p.id ASC");

        if ($sql->row) {
            if ($sql->row['customer_id'] == (int)$this->customer->getId())
                return true;
            else
                return false;
        } else {
            return false;
        }
    }

    /**
     * [getProductKeyword to get product's keywords]
     * @param  [integer] $product_id [product id of product]
     * @return [string]             [keyword related to that particular product]
     */
    public function getProductKeyword($product_id)
    {
        $result = $this->db->query("SELECT keyword,language_id FROM " . DB_PREFIX . "seo_url WHERE query = 'product_id=" . (int)$product_id . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'")->rows;
        if ($result) {
            foreach ($result as $key => $value) {
                $query[$value['language_id']] = $value['keyword'];
            }
            return $query;
        } else {
            return array();
        }

    }

    /**
     * [getProductSoldQuantity to get total sold quantity of any product]
     * @param  [integer] $product_id [product id of product]
     * @return [array]             [detail about total sold quantity of particular product]
     */
    public function getProductSoldQuantity($product_id)
    {
        $sql = $this->db->query("SELECT SUM(c2o.quantity) quantity, (SUM(c2o.price) - SUM(c2o.admin)) total FROM " . DB_PREFIX . "customerpartner_to_order c2o LEFT JOIN " . DB_PREFIX . "customerpartner_to_product c2p ON (c2o.product_id = c2p.product_id AND c2o.customer_id = c2p.customer_id ) WHERE c2o.customer_id = '" . (int)$this->customer->getId() . "' and c2p.product_id = '" . (int)$product_id . "'");

        return ($sql->row);
    }

    /**
     * [getProduct to get detail of particular product]
     * @param  [integer] $product_id [product id of product]
     * @return [array]             [detail of particular product]
     */
    public function getProduct($product_id)
    {

        $query = $this->db->query("
    SELECT
        DISTINCT *
        , pd.name AS name
        , pd.return_warranty AS return_warranty
        , p.image
        , m.name AS manufacturer
        , (
            SELECT price
            FROM " . DB_PREFIX . "product_discount pd2
            WHERE
                pd2.product_id = p.product_id
                AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
                AND pd2.quantity = '1'
                AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW()))
            ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1
        ) AS discount
        , (
            SELECT price
            FROM " . DB_PREFIX . "product_special ps
            WHERE
                ps.product_id = p.product_id
                AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
                AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))
            ORDER BY ps.priority ASC, ps.price ASC LIMIT 1
        ) AS special
        , (
            SELECT points
            FROM " . DB_PREFIX . "product_reward pr
            WHERE
                pr.product_id = p.product_id
                AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
        ) AS reward
        , (
            SELECT ss.name
            FROM " . DB_PREFIX . "stock_status ss
            WHERE
                ss.stock_status_id = p.stock_status_id
                AND ss.language_id = '" . (int)$this->config->get('config_language_id') . "'
        ) AS stock_status
        , (
            SELECT wcd.unit
            FROM " . DB_PREFIX . "weight_class_description wcd
            WHERE
                p.weight_class_id = wcd.weight_class_id
                AND wcd.language_id = '" . (int)$this->config->get('config_language_id') . "'
        ) AS weight_class
        , (
            SELECT lcd.unit
            FROM " . DB_PREFIX . "length_class_description lcd
            WHERE
                p.length_class_id = lcd.length_class_id
                AND lcd.language_id = '" . (int)$this->config->get('config_language_id') . "'
        ) AS length_class
        , (
            SELECT AVG(rating) AS total
            FROM " . DB_PREFIX . "review r1
            WHERE
                r1.product_id = p.product_id
                AND r1.status = '1'
            GROUP BY r1.product_id
        ) AS rating
        , (
            SELECT COUNT(*) AS total
            FROM " . DB_PREFIX . "review r2
            WHERE
                r2.product_id = p.product_id
                AND r2.status = '1'
            GROUP BY r2.product_id
        ) AS reviews
        , p.sort_order
    FROM " . DB_PREFIX . "product p
    LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
    LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
    LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)
    WHERE
        p.product_id = '" . (int)$product_id . "'
        AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

        $result = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_product WHERE product_id = '" . (int)$product_id . "'")->row;

        //add by lxx
        $colorResult = $this->db->query("SELECT option_value_id FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . (int)$product_id . "'")->row;
        if (!isset($colorResult['option_value_id'])) {
            $colorResult['option_value_id'] = '';
        }
        //end
        if (!isset($result['expiry_date'])) {
            $result['expiry_date'] = '';
        }
        if (!isset($result['relist_duration'])) {
            $result['relist_duration'] = '';
        }
        if (!isset($result['auto_relist'])) {
            $result['auto_relist'] = '';
        }
        if (!isset($result['current_status'])) {
            $result['current_status'] = '';
        }


        if ($query->num_rows) {
            return array(
                'price_display' => $query->row['price_display'],
                'quantity_display' => $query->row['quantity_display'],
                'product_id' => $query->row['product_id'],
                'name' => $query->row['name'],
                'description' => $query->row['description'],
                //'returns_and_notice' => $query->row['returns_and_notice'], 弃用
                'return_warranty' => $query->row['return_warranty'],
                'meta_title' => $query->row['meta_title'],
                'meta_description' => $query->row['meta_description'],
                'meta_keyword' => $query->row['meta_keyword'],
                'tag' => $query->row['tag'],
                'model' => $query->row['model'],
                'sku' => $query->row['sku'],
                'upc' => $query->row['upc'],
                'ean' => $query->row['ean'],
                'jan' => $query->row['jan'],
                'isbn' => $query->row['isbn'],
                'mpn' => $query->row['mpn'],
                'location' => $query->row['location'],
                'quantity' => $query->row['quantity'],
                'stock_status' => $query->row['stock_status'],
                'image' => $query->row['image'],
                'manufacturer_id' => $query->row['manufacturer_id'],
                'manufacturer' => $query->row['manufacturer'],
                'price' => ($query->row['discount'] ? $query->row['discount'] : $query->row['price']),
                'special' => $query->row['special'],
                'reward' => $query->row['reward'],
                'points' => $query->row['points'],
                'tax_class_id' => $query->row['tax_class_id'],
                'date_available' => $query->row['date_available'],
                'weight' => $query->row['weight'],
                'weight_class_id' => $query->row['weight_class_id'],
                'length' => $query->row['length'],
                'width' => $query->row['width'],
                'height' => $query->row['height'],
                'length_class_id' => $query->row['length_class_id'],
                'subtract' => $query->row['subtract'],
                'rating' => round($query->row['rating']),
                'reviews' => $query->row['reviews'] ? $query->row['reviews'] : 0,
                'minimum' => $query->row['minimum'],
                'sort_order' => $query->row['sort_order'],
                'status' => $query->row['status'],
                'date_added' => $query->row['date_added'],
                'date_modified' => $query->row['date_modified'],
                'viewed' => $query->row['viewed'],
                //for mp
                'shipping' => $query->row['shipping'],
                'stock_status_id' => $query->row['stock_status_id'],
                /*Membership code*/
                'expiry_date' => $result['expiry_date'],
                'relist_duration' => $result['relist_duration'],
                'auto_relist' => $result['auto_relist'],
                'current_status' => $result['current_status'],
                /**/
                //color
                'color' => $colorResult['option_value_id'],
                'comboFlag' => $query->row['combo_flag'],
                'buyer_flag' => $query->row['buyer_flag'],
                'partFlag' => $query->row['part_flag'],
                'product_type' => $query->row['product_type'],
                'product_size' => $query->row['product_size'],
                'need_install' => $query->row['need_install'],
            );
        } else {
            return false;
        }
    }

    /**
     * [getProductsSeller to get seller's product]
     * @param  [array]  $data [filter keywords]
     * @return [array]       [product's details]
     * @throws Exception
     */
    public function getProductsSeller($data = array())
    {

        $sql = "SELECT p.product_id,p.mpn,p.sku,m.`name` AS brand,
                ro.receive_order_id,
                DATEDIFF(Now(),p.date_added) as date_df,
                p.part_flag,
                p.quantity,
                CASE WHEN rod.receive_order_id IS NOT NULL AND DATEDIFF(Now(),p.date_added) <= ".NEW_ARRIVAL_DAY." THEN 1 ELSE 0 END AS is_new,
                (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating,
                (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount,
                (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special
                FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)
                LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
                LEFT JOIN " . DB_PREFIX . "customerpartner_to_product c2p ON (c2p.product_id = p.product_id)
                LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)";

        if (isset($data['filter_category_id']) AND $data['filter_category_id']) {
            $sql .= " LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id)";
//            $sql .= " LEFT JOIN " . DB_PREFIX ."category occ ON (occ.category_id = p2c.category_id OR occ.parent_id = p2c.category_id)";
            $sql .= " LEFT JOIN " . DB_PREFIX . "category occ ON (occ.category_id = p2c.category_id)";
        }

        $sql .= ' LEFT JOIN tb_sys_receipts_order_detail AS rod ON rod.product_id = p.product_id
                LEFT JOIN tb_sys_receipts_order AS ro ON ro.receive_order_id = rod.receive_order_id
                AND ro.`expected_date` IS NOT NULL
                AND rod.`expected_qty` IS NOT NULL
                AND ro.`expected_date` > NOW()
                AND ro.`status` =' . ReceiptOrderStatus::TO_BE_RECEIVED;

        if ($this->customer->isLogged() && !$this->customer->isPartner()) {
            $sql .= " LEFT JOIN oc_buyer_to_seller as bts on (bts.seller_id=c2p.customer_id and bts.buyer_id=" . (int)$this->customer->getId() . ")";
            $sql .= " LEFT JOIN oc_delicacy_management as dm on (dm.product_id=p.product_id and dm.buyer_id=bts.buyer_id and dm.expiration_time>NOW())";
        }

        $sql .= " WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.date_available <= NOW() ";

        if (isset($data['filter_category_id']) AND $data['filter_category_id']) {
            if(is_string($data['filter_category_id'])){
                $sql .= " AND p2c.category_id IN (" . $data['filter_category_id'] . ")";
            }else{
                $sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
            }
//            $sql .= " AND (occ.category_id = '" . (int)$data['filter_category_id'] . "' or occ.parent_id = '".(int)$data['filter_category_id'] . "')";
        }

        if (isset($data['filter_name']) AND !empty($data['filter_name'])) {
            $sql .= " AND pd.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        }

        if (isset($data['filter_model']) AND !empty($data['filter_model'])) {
            $sql .= " AND p.model LIKE '" . $this->db->escape($data['filter_model']) . "%'";
        }

        if (isset($data['filter_price']) AND !empty($data['filter_price'])) {
            $sql .= " AND p.price LIKE '" . $this->db->escape($data['filter_price']) . "%'";
        }

        if (isset($data['filter_quantity']) && !is_null($data['filter_quantity'])) {
            $sql .= " AND p.quantity = '" . $this->db->escape($data['filter_quantity']) . "'";
        }

        if (isset($data['filter_store']) && !is_null($data['filter_store'])) {
            $sql .= " AND p2s.store_id = " . (int)$data['filter_store'];
        }

        if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
            $sql .= " AND p.status = '" . (int)$data['filter_status'] . "' ";
        }

        if (isset($data['filter_combo']) && !is_null($data['filter_combo'])) {
            $sql .= " AND p.combo_flag = '" . (int)$data['filter_combo'] . "'";
        }

        if (isset($data['filter_mpn']) AND !empty($data['filter_mpn'])) {
            $sql .= " AND p.mpn = '" . $this->db->escape($data['filter_mpn']) . "'";
        }

        if (isset($data['filter_sku']) AND !empty($data['filter_sku'])) {
            $sql .= " AND p.sku = '" . $this->db->escape($data['filter_sku']) . "'";
        }

        if (isset($data['filter_sku_mpn']) AND !empty($data['filter_sku_mpn'])) {
            $sql .= " AND (p.sku like '%" . $this->db->escape($data['filter_sku_mpn']) . "%' or p.mpn like '%".$this->db->escape($data['filter_sku_mpn'])."%')";
        }

        if (isset($data['filter_low_stock']) || isset($this->request->get['low_stock'])) {
            $sql .= " AND p.quantity <= '" . (int)$this->config->get('marketplace_low_stock_quantity') . "'";
        }
        //是否可以单独售卖的查询条件
        if ($this->customer->isLogged()) {
            if (!$this->customer->isPartner()) {
                $sql .= " AND p.buyer_flag = 1 ";
            } else if (!array_key_exists('buyer_flag', $data)) {
                $sql .= " AND p.buyer_flag = 1 ";
            } else {
                $data['buyer_flag'] !== null && $sql .= " AND p.buyer_flag = {$data['buyer_flag']}";
            }
        } else {
            $sql .= " AND p.buyer_flag = 1 ";
        }

        // 是否软删除
        if (isset($data['filter_is_deleted'])) {
            $sql .= " AND p.is_deleted = {$data['filter_is_deleted']}";
        }

        if (isset($data['filter_product_ids']) && !is_null($data['filter_product_ids'])) {
            $sql .= " AND ( p.product_id in (" . implode(',', $data['filter_product_ids'] ?: [0]) . ") )";
        }

        if (isset($data['filter_product_ids_not']) && !is_null($data['filter_product_ids_not'])) {
            $sql .= " AND ( p.product_id not in (" . implode(',', $data['filter_product_ids_not'] ?: [0]) . ") ) ";
        }

        if ($this->customer->isLogged() && !$this->customer->isPartner()) {
            $sql .= " AND ( dm.product_display=1 OR dm.id is null ) ";
            $sql .= " AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = " . (int)$this->customer->getId() . " AND pgl.product_id = p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            ) ";
        }

        // 添加价格和销量筛选条件
        if (isset($data['min_price']) && (!empty($data['min_price']) or $data['min_price'] == 0)) {
            if ($this->customer->isLogged() && !$this->customer->isPartner()) {
                $sql .= " AND ifnull(dm.current_price,p.price) >= " . $this->db->escape($data['min_price']);
            } else {
                $sql .= " AND p.price >= " . $this->db->escape($data['min_price']);
            }
        }
        if (isset($data['max_price']) && (!empty($data['max_price']) or $data['max_price'] == 0)) {
            if ($this->customer->isLogged() && !$this->customer->isPartner()) {
                $sql .= " AND ifnull(dm.current_price,p.price) <= " . $this->db->escape($data['max_price']);
            } else {
                $sql .= " AND p.price <= " . $this->db->escape($data['max_price']);
            }
        }
        if (isset($data['min_quantity']) && (!empty($data['min_quantity'] or $data['min_quantity'] == 0))) {
            $sql .= " AND p.quantity >= " . (int)$this->db->escape($data['min_quantity']);
        }
        if (isset($data['max_quantity']) && (!empty($data['max_quantity'] or $data['max_quantity'] == 0))) {
            $sql .= " AND p.quantity <= " . (int)$this->db->escape($data['max_quantity']);
        }

        if (!isset($data['customer_id']) || !$data['customer_id']) {
            $sql .= " AND c2p.customer_id = " . (int)$this->customer->getId();
        } else {
            $sql .= " AND c2p.customer_id = " . (int)$data['customer_id'];
        }

        $sql .= ' AND p.product_type IN (0,3) ';

        $sql .= " GROUP BY p.product_id ";

        $sort_data = array(
            'pd.name',
            'p.model',
            'p.price',
            'p.quantity',
            'p.status',
            'p.sort_order'
        );
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {

            if($data['sort'] == 'p.price'){
                if ($this->customer->isLogged() && !$this->customer->isPartner()) {
                    $sql .= " ORDER BY ifnull(dm.current_price,p.price) ";
                }else{
                    $sql .= " ORDER BY p.price ";
                }

                if (isset($data['order']) && ($data['order'] == 'DESC')) {
                    $sql .= " DESC";
                } else {
                    $sql .= " ASC";
                }
            }elseif($data['sort'] == 'p.sort_order') {

            }else{
                if (isset($data['order']) && ($data['order'] == 'DESC')) {
                    $sort = " DESC";
                } else {
                    $sort = " ASC";
                }
                $sql .= " ORDER BY " . $data['sort']. $sort.',p.product_id ' ;
            }
            //. ',p.product_id '
            //edit by taixing
        } else {
            $sql .= " ORDER BY pd.name". ',p.product_id ';
            // . ',p.product_id '
            //edit by taixing
        }



        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }
            if (($data['sort'] ?? '') != 'p.sort_order') {
                $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
            }
        }
        $product_data = array();
        $query = $this->db->query($sql);

        if (($data['sort'] ?? '') == 'p.sort_order') {
            $query->rows = $this->searchSort($query->rows,$data['limit'],$data['start']);
        }
        $this->load->model('catalog/product');
        /** @var ModelCatalogProduct $modelCatalogProduct */
        $modelCatalogProduct =  $this->model_catalog_product;
        $receipt_array =  $modelCatalogProduct->getReceptionProduct();
        $this->load->model('extension/module/product_show');
        $route = $this->request->get['route'];
        $arr = ['customerpartner/profile/collection','product/product'];
        foreach ($query->rows as $result) {
            // 后台seller 查看时getIdealProductInfo 获取参数不对
            if($this->customer->isPartner() && !in_array($route,$arr) ){
                $product_data[$result['product_id']] = $this->getProduct($result['product_id']);
            }else{
                $product_data[$result['product_id']] = $this->model_extension_module_product_show->getIdealProductInfo($result['product_id'], $this->customer->getId(),$receipt_array);
            }

            //N-94 新品角标
            if ($result['is_new'] == 1) {
                $product_data[$result['product_id']]['horn_mark'] = 'new';
            }
        }

        return $product_data;
    }

    public function searchSort($data,$limit,$start){

        foreach($data as $key => $value){
            // 产品是否可见
            $data[$key]['weight'] = 0;
            if($value['is_new']){
                $data[$key]['weight'] += 100;
                if($value['part_flag'] == 1){
                    $data[$key]['weight'] += 10;
                }else{
                    $data[$key]['weight'] += 20;
                }
                $data[$key]['weight'] += $value['product_id']/100000000;
            }else{
                $data[$key]['weight'] += 50;
                if($value['quantity'] > 0){
                    $data[$key]['weight'] += 20;
                }else{
                    $data[$key]['weight'] += 10;
                }

                if($value['part_flag'] == 1){
                    $data[$key]['weight'] += 1;
                }else{
                    $data[$key]['weight'] += 2;
                }

                //按照多少来
                $data[$key]['weight'] += $value['quantity']/10000;
                $data[$key]['weight'] += $value['product_id']/100000000;
            }

        }
        if ($data) {
            foreach ($data as $key => $value) {
                $sort_order[$key] = $value['weight'];
            }
            array_multisort($sort_order, SORT_DESC, $data);
        }
        $ret = array_slice($data,$start,$limit);

        return $ret;
    }

    /**
     * @deprecated #6446 已启用
     * @param array $data
     * @return array
     */
    public function getProductsSellerForDownload($data = array())
    {

        $sql = "SELECT p.product_id,p.mpn,p.sku,m.name AS brand,
(SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating,
(SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount,
(SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special
FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)
LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
LEFT JOIN " . DB_PREFIX . "customerpartner_to_product c2p ON (c2p.product_id = p.product_id)
LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)";


        $sql .= " WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.date_available <= NOW() ";

        if (isset($data['filter_name']) AND !empty($data['filter_name'])) {
            $sql .= " AND pd.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        }

        if (isset($data['filter_quantity']) && !is_null($data['filter_quantity'])) {
            $sql .= " AND p.quantity = '" . $this->db->escape($data['filter_quantity']) . "'";
        }


        if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
            $sql .= " AND p.status = '" . (int)$data['filter_status'] . "' ";
        }

        if (isset($data['filter_combo']) && !is_null($data['filter_combo'])) {
            $sql .= " AND p.combo_flag = '" . (int)$data['filter_combo'] . "'";
        }

        if (isset($data['filter_mpn']) AND !empty($data['filter_mpn'])) {
            $sql .= " AND p.mpn = '" . $this->db->escape($data['filter_mpn']) . "'";
        }

        if (isset($data['filter_sku']) AND !empty($data['filter_sku'])) {
            $sql .= " AND p.sku = '" . $this->db->escape($data['filter_sku']) . "'";
        }

        if (isset($data['filter_sku_mpn']) AND !empty($data['filter_sku_mpn'])) {
            $sql .= " AND (p.sku like '%" . $this->db->escape($data['filter_sku_mpn']) . "%' or p.mpn like '%".$this->db->escape($data['filter_sku_mpn'])."%')";
        }

        if (isset($data['filter_low_stock']) || isset($this->request->get['low_stock'])) {
            $sql .= " AND p.quantity <= '" . (int)$this->config->get('marketplace_low_stock_quantity') . "'";
        }
        //是否可以单独售卖的查询条件
        if (isset($data['filter_buyer_flag']) && !is_null($data['filter_buyer_flag']) && in_array($data['filter_buyer_flag'], [0, 1])) {
            $sql .= " AND p.buyer_flag = {$data['filter_buyer_flag']}";
        }

        // 是否软删除
        if (isset($data['filter_is_deleted']) && in_array($data['filter_is_deleted'], [0, 1])) {
            $sql .= " AND p.is_deleted = {$data['filter_is_deleted']}";
        }

        if (isset($data['filter_product_ids']) && !is_null($data['filter_product_ids'])) {
            $sql .= " AND ( p.product_id in (" . implode(',', $data['filter_product_ids'] ?: [0]) . ") )";
        }

        if (!isset($data['customer_id']) || !$data['customer_id'])
            $sql .= " AND c2p.customer_id = " . (int)$this->customer->getId();
        else
            $sql .= " AND c2p.customer_id = " . (int)$data['customer_id'];

        $sql .= ' AND p.product_type IN (0,3) ';
        $sql .= " GROUP BY p.product_id";

        $sql .= " order by p.status DESC,p.sku ASC ";

        $product_data = array();

        $query = $this->db->query($sql);

        foreach ($query->rows as $result) {
            $product_data[$result['product_id']] = $this->getProduct($result['product_id']);
        }

        return $product_data;
    }


    /**
     * 获取店铺产品总数
     * @param array $data
     * @return int
     */
    public function getTotalProductsSeller($data = array(), $str_flag = 0)
    {

        $sql = "SELECT  DISTINCT(p.product_id) FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_product c2p ON (c2p.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)";

        $sql .= ' LEFT JOIN tb_sys_receipts_order_detail AS rod ON rod.product_id = p.product_id
                LEFT JOIN tb_sys_receipts_order AS ro ON ro.receive_order_id = rod.receive_order_id
                AND ro.`expected_date` IS NOT NULL
                AND rod.`expected_qty` IS NOT NULL
                AND ro.`expected_date` > NOW()
                AND ro.`status` =' . ReceiptOrderStatus::TO_BE_RECEIVED;
        if (isset($data['filter_category_id']) AND $data['filter_category_id']) {
            $sql .= " LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id)";
            //            $sql .= " LEFT JOIN " . DB_PREFIX ."category occ ON (occ.category_id = p2c.category_id OR occ.parent_id = p2c.category_id)";
            $sql .= " LEFT JOIN " . DB_PREFIX . "category occ ON (occ.category_id = p2c.category_id )";
        }

        if ($this->customer->isLogged() && !$this->customer->isPartner()) {
            $sql .= " LEFT JOIN oc_buyer_to_seller as bts on (bts.seller_id=c2p.customer_id and bts.buyer_id=" . (int)$this->customer->getId() . ")";
            $sql .= " LEFT JOIN oc_delicacy_management as dm on (dm.product_id=p.product_id and dm.buyer_id=bts.buyer_id and dm.expiration_time>NOW())";
        }

        $sql .= " WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.date_available <= NOW() ";

        if (isset($data['filter_category_id']) AND $data['filter_category_id']) {
            if(is_string($data['filter_category_id'])){
                $sql .= " AND p2c.category_id IN (" . $data['filter_category_id'] . ")";
            }else{
                $sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
            }
            //            $sql .= " AND (occ.category_id = '" . (int)$data['filter_category_id'] . "' or occ.parent_id = '".(int)$data['filter_category_id'] . "')";
        }

        if (isset($data['filter_name']) AND !empty($data['filter_name'])) {
            $sql .= " AND pd.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        }

        if (isset($data['filter_model']) AND !empty($data['filter_model'])) {
            $sql .= " AND p.model LIKE ' %" . $this->db->escape($data['filter_model']) . "%'";
        }

        if (isset($data['filter_store']) && !is_null($data['filter_store'])) {
            $sql .= " AND p2s.store_id = '" . (int)$data['filter_store'] . "'";
        }

        if (isset($data['filter_mpn']) && !is_null($data['filter_mpn'])) {
            $sql .= " AND p.mpn = '" . $this->db->escape($data['filter_mpn']) . "'";
        }

        if (isset($data['filter_sku']) && !is_null($data['filter_sku'])) {
            $sql .= " AND p.sku = '" . $this->db->escape($data['filter_sku']) . "'";
        }

        if (isset($data['filter_sku_mpn']) AND !empty($data['filter_sku_mpn'])) {
            $sql .= " AND (p.sku like '%" . $this->db->escape($data['filter_sku_mpn']) . "%' or p.mpn like '%".$this->db->escape($data['filter_sku_mpn'])."%')";
        }

        if (isset($data['filter_price']) AND !empty($data['filter_price'])) {
            $sql .= " AND p.price LIKE '" . $this->db->escape($data['filter_price']) . "%'";
        }

        if (isset($data['filter_quantity']) && !is_null($data['filter_quantity'])) {
            $sql .= " AND p.quantity = '" . $this->db->escape($data['filter_quantity']) . "'";
        }

        if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
            $sql .= " AND p.status = '" . (int)$data['filter_status'] . "' ";
        }
        if (isset($data['filter_combo']) && !is_null($data['filter_combo'])) {
            $sql .= " AND p.combo_flag = '" . (int)$data['filter_combo'] . "'";
        }
        if (isset($data['filter_low_stock']) || isset($this->request->get['low_stock'])) {
            $sql .= " AND p.quantity <= '" . (int)$this->config->get('marketplace_low_stock_quantity') . "'";
        }
        //是否可以单独售卖的查询条件
        if ($this->customer->isLogged()) {
            if (!$this->customer->isPartner()) {
                $sql .= " AND p.buyer_flag = 1 ";
            } else if (!array_key_exists('buyer_flag', $data)) {
                $sql .= " AND p.buyer_flag = 1 ";
            } else {
                $data['buyer_flag'] !== null && $sql .= " AND p.buyer_flag = {$data['buyer_flag']}";
            }
        } else {
            $sql .= " AND p.buyer_flag = 1 ";
        }
        // 是否软删除
        if (isset($data['filter_is_deleted'])) {
            $sql .= " AND p.is_deleted = {$data['filter_is_deleted']}";
        }

        if ($this->customer->isLogged() && !$this->customer->isPartner()) {
            $sql .= " AND ( dm.product_display=1 OR dm.id is null )";
            $sql .= "AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id= c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = " . (int)$this->customer->getId() . " AND pgl.product_id = p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )";
        }

        if (isset($data['filter_product_ids']) && !is_null($data['filter_product_ids'])) {
            $sql .= " AND ( p.product_id in (" . implode(',', $data['filter_product_ids'] ?: [0]) . ") )";
        }

        // 添加价格和销量筛选条件
        if (isset($data['min_price']) && (!empty($data['min_price']) or $data['min_price'] == 0)) {
            if ($this->customer->isLogged() && !$this->customer->isPartner()) {
                $sql .= " AND ifnull(dm.current_price,p.price) >= " . $this->db->escape($data['min_price']);

            } else {
                $sql .= " AND p.price >= " . $this->db->escape($data['min_price']);
            }
        }
        if (isset($data['max_price']) && (!empty($data['max_price']) or $data['max_price'] == 0)) {
            if ($this->customer->isLogged() && !$this->customer->isPartner()) {
                $sql .= " AND ifnull(dm.current_price,p.price) <= " . $this->db->escape($data['max_price']);
            } else {
                $sql .= " AND p.price <= " . $this->db->escape($data['max_price']);
            }
        }
        if (isset($data['min_quantity']) && (!empty($data['min_quantity'] or $data['min_quantity'] == 0))) {
            $sql .= " AND p.quantity >= '" . $this->db->escape($data['min_quantity']) . "'";
        }
        if (isset($data['max_quantity']) && (!empty($data['max_quantity'] or $data['max_quantity'] == 0))) {
            $sql .= " AND p.quantity <= '" . $this->db->escape($data['max_quantity']) . "'";
        }

        if (!isset($data['customer_id']) || !$data['customer_id'])
            $sql .= " AND c2p.customer_id = " . (int)$this->customer->getId();
        else
            $sql .= " AND c2p.customer_id = " . (int)$data['customer_id'];
        if (isset($data['filter_buyer_flag']) && !is_null($data['filter_buyer_flag'])) {
            $sql .= " AND p.buyer_flag = '1' ";
        }
        $sql .= ' AND p.product_type IN (0,3) ';
        $sql .= " GROUP BY p.product_id";
        $query = $this->db->query($sql);
        if ($str_flag == 1) {
            $id_str = '';
            $tmp = $query->rows;
            foreach ($tmp as $item) {
                $id_str .= $item['product_id'] . ',';
            }
            $id_str = rtrim($id_str, ',');
            return $id_str;
        }
        return count($query->rows);
    }

    /**
     * [deleteProduct to delete product]
     * @param  [integer] $product_id [product id of particular product]
     */
    public function deleteProduct($product_id)
    {

        if ($this->chkSellerProductAccess($product_id)) {

            $this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_to_product WHERE product_id = '" . (int)$product_id . "'");

            $this->db->query("DELETE FROM " . DB_PREFIX . "mp_customer_activity WHERE id = '" . (int)$product_id . "' AND `key` = 'product_stock'");

            //if seller can delete product from store
            if ($this->config->get('marketplace_sellerdeleteproduct')) {

                $this->db->query("DELETE FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$product_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_description WHERE product_id = '" . (int)$product_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_filter WHERE product_id = '" . (int)$product_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_id = '" . (int)$product_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . (int)$product_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$product_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE related_id = '" . (int)$product_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$product_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_download WHERE product_id = '" . (int)$product_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int)$product_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_store WHERE product_id = '" . (int)$product_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "review WHERE product_id = '" . (int)$product_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE query = 'product_id=" . (int)$product_id . "'");
                $this->db->query("DELETE FROM tb_sys_product_set_info where product_id = '" . (int)$product_id . "'");
            }

        }

    }

    /**
     * 创建商品 改写老接口
     *
     * @param array  $data
     * @return array|bool
     */
    public function addProduct($data)
    {
        trim_strings($data);
        $productInfo = app(ProductService::class)->createProduct($data);
        if (!$productInfo['product_id'] || !$productInfo['sku']) {
            return false;
        }
        $product_id = $productInfo['product_id'];
        // 关联产品
        $data['product_associated'] = app(ProductOptionService::class)->updateProductAssociate($product_id, $data['product_associated'] ?? []);
        $this->productAddUpdate($product_id, $data,1);
        $current_status = 'active';
        if ($this->config->get('module_wk_seller_group_status')) {

            if (isset($data['relist_duration']) && $data['relist_duration']) {
                $date = date('y-m-d');
                $date = strtotime($date);
                $date = strtotime("+" . $data['relist_duration'] . " day", $date);
                $expiry_date = date('Y-m-d', $date);
            } else if (isset($data['relist_duration'])) {
                $expiry_date = '0000:00:00';
            }

            if (!$data['status']) {
                $current_status = 'inactive';
            }

            $sql = "INSERT INTO `" . DB_PREFIX . "customerpartner_to_product` SET `product_id` = " . (int)$product_id . ",customer_id = '" . (int)$this->customer->getId() . "',current_status = '" . $current_status . "', expiry_date = '" . $expiry_date . "',seller_price = '" . (float)$this->db->escape($data['price']) . "',currency_code = '" . session('currency') . "' ";

            if (isset($data['quantity'])) {
                $sql .= ", quantity = '" . $this->db->escape($data['quantity']) . "'";
            }

            if (isset($data['price'])) {
                $sql .= ", price = '" . (float)$this->db->escape($data['price']) . "'";
            }

            if (isset($data['relist_duration'])) {
                $sql .= ", relist_duration = '" . $data['relist_duration'] . "' ";
            }

            if (isset($data['auto_relist'])) {
                $sql .= ", auto_relist = '1' ";
            }

            $this->db->query($sql);

            $this->relist($product_id);

        } else {

            $sql = "INSERT INTO `" . DB_PREFIX . "customerpartner_to_product` SET `product_id` = " . (int)$product_id . ",customer_id = '" . (int)$this->customer->getId() . "',currency_code = '" . session('currency') . "'";

            if (isset($data['quantity'])) {
                $sql .= ", quantity = '" . $this->db->escape($data['quantity']) . "'";
            }

            if (isset($data['price'])) {
                if ($data['price'] == '') {
                    $data['price'] = 0;
                }
                $sql .= ", price = '" . (float)$this->db->escape($data['price']) . "',seller_price = '" . (float)$this->db->escape($data['price']) . "'";
            }

            $this->db->query($sql);

        }
        // 添加分组  原有逻辑就是运行单独售卖了，才维护分组信息
        if (isset($data['buyer_flag']) && $data['buyer_flag'] == 1 && !empty($data['product_group_ids'])) {
            $this->load->model('Account/Customerpartner/ProductGroup');
            $this->model_Account_Customerpartner_ProductGroup->addLinksByProduct($data['seller_id'], $data['product_group_ids'], $product_id);
        }
        //直接提交审核 #20585 不可单独售卖商品不需要审核，可直接上架
        if ($data['is_draft'] == 2 && $data['buyer_flag'] == 1) {
            $audit_id = app(ProductAuditService::class)->insertProductAudit($product_id, $data);
            if ($audit_id > 0) {
                $productInfo['audit_id'] = $audit_id;//审核记录主键
            }
        }
        return $productInfo;
    }

    /**
     * [getSellerCommission to get seller's commission]
     * @param  [integer] $seller_id [particular seller id]
     * @return [integer, boolean]            [seller's commission or false value]
     */
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
     * 修改商品
     *
     * @param array $data
     * @return array|bool
     */
    public function editProduct($data)
    {
        $product_id = $data['product_id'];
        //这坨应该不执行
        if (isset($data['product_custom_field'])) {
            $this->load->model("account/wkcustomfield");
            $this->model_account_wkcustomfield->editCustomFields($data['product_custom_field'], $product_id);
        } else if (isset($data['wkcustomfield'])) {
            $this->load->model("account/wkcustomfield");
            $this->model_account_wkcustomfield->removeFromProduct($product_id);
        }
        $productDetail = Product::query()->with(['customerPartner','description'])->find($product_id);
        if (!$productDetail || $productDetail->customerPartner->customer_id != customer()->getId()) {
            Logger::addEditProduct('非法编辑商品或商品信息异常product_id:' . $product_id . ',customer_id:' . customer()->getId());
            return false;
        }
        //有个校验，-1状态时候，USA有体积异常情况不允许编辑
        if ($productDetail->status == ProductStatus::WAIT_SALE && customer()->isUSA()) {
            //体积异常，不允许创建
            if (isset($data['combo_flag']) && $data['combo_flag'] == 0) {
                if (app(ProductRepository::class)->checkChargeableWeightExceed($data['width'], $data['height'], $data['length'], $data['weight'])) {
                    return false;
                }
            } elseif (isset($data['combo_flag']) && $data['combo_flag'] == 1) {
                if (app(ProductRepository::class)->checkComboChargeableWeightExceed($data['combo'] ?? []) < 0) {
                    return false;
                }
            }
        }
        if (in_array($productDetail->status,ProductStatus::notSale())){
            $this->productAddUpdate($product_id, $data,2);
        }
        $editRes = app(ProductService::class)->editProduct($data, $product_id);
        if ($editRes === false) {
            return false;
        }
        $notice_type = $editRes['notice_type'];
        $audit_id = $editRes['audit_id'];

        //同步商品到OMD逻辑，从event挪到此处
        if (customer()->isUSA() && !customer()->isInnerAccount()
            && $productDetail->combo_flag == 0 && in_array($productDetail->status, ProductStatus::notSale())) {
            if ($productDetail->description->name !== $data['name']) {
                try {
                    ProductHelper::sendSyncProductsRequest([$product_id]);
                } catch (\Throwable $e) {
                    Logger::syncProducts($e->getMessage());
                }
            }
        }

        return ['product_id' => $productDetail->product_id,'sku' =>$productDetail->sku ,'notice_type' => $notice_type, 'audit_id'=>$audit_id];
    }

    /**
     * [sendProductionInfoToBuyer description] 发送站内信给到buyer
     * @param int $product_id
     * @param int $seller_id
     * @param int $flag 0 上架变成下架  1 下架变成上架
     * @param int|null $buyer_id
     * @param int $isNotIn
     * @return array|boolean
     * @throws Exception
     */
    public function sendProductionInfoToBuyer($product_id,$seller_id,$flag = 0,$buyer_id = null,$isNotIn = 0){
        //1. 查询订阅此产品的buyer 且buyer seller 建立了联系
        $map = [
            ['bts.seller_id','=',$seller_id],
            ['cw.product_id','=',$product_id],
        ];
        if($buyer_id && $isNotIn == 0){
            array_push($map,['bts.buyer_id','=',$buyer_id]);
            $res = $this->orm->table(DB_PREFIX.'customer_wishlist as cw')
                ->leftJoin(DB_PREFIX.'buyer_to_seller as bts','bts.buyer_id','=','cw.customer_id')
                ->where($map)
                ->select('bts.buyer_id','bts.seller_id','bts.discount')->get();
        }elseif($buyer_id && $isNotIn){
            $res = $this->orm->table(DB_PREFIX.'customer_wishlist as cw')
                ->leftJoin(DB_PREFIX.'buyer_to_seller as bts','bts.buyer_id','=','cw.customer_id')
                ->where($map)
                ->whereNotIn('bts.buyer_id',$buyer_id)->select('bts.buyer_id','bts.seller_id','bts.discount')->get();

        }else{
            $res = $this->orm->table(DB_PREFIX.'customer_wishlist as cw')
                ->leftJoin(DB_PREFIX.'buyer_to_seller as bts','bts.buyer_id','=','cw.customer_id')
                ->where($map)
                ->select('bts.buyer_id','bts.seller_id','bts.discount')->get();

        }
        $res = obj2array($res);
        if(!$res){
            return false;
        }
        //14086 库存订阅列表中的产品上下架提醒
        $product_info = $this->orm->table(DB_PREFIX.'product as p')
            ->leftJoin('oc_product_description as pd','pd.product_id','=','p.product_id')->
            where('p.product_id',$product_id)->select('p.sku','pd.name','p.quantity','p.price','p.freight')->first();
        //14086 库存上下架提醒
        $this->load->model('catalog/product');
        $this->load->model('message/message');
        if($flag == 0){
            // 下架
            foreach($res as $key => $value){
                $delicacyManagementPrice = $this->model_catalog_product->getDelicacyManagementInfoByNoView($product_id,$value['buyer_id'],$seller_id);
                if(isset($delicacyManagementPrice['current_price'])){
                    $value['price'] = $delicacyManagementPrice['current_price'];
                }else{
                    $value['price'] = null;
                }
                $title = 'Product Unavailable Alert (Item code: '.$product_info->sku.')';
                $message = $this->getSendMessageTemplate($flag,$product_info,$value);

                //$this->communication->saveCommunication($title, $message, $value['buyer_id'],$seller_id, 0);
                $this->model_message_message->addSystemMessageToBuyer('product_status',$title, $message, $value['buyer_id']);

            }

        }else{
            //上架
            foreach($res as $key => $value){
                $delicacyManagementPrice = $this->model_catalog_product->getDelicacyManagementInfoByNoView($product_id,$value['buyer_id'],$seller_id);
                if(isset($delicacyManagementPrice['current_price'])){
                    $value['price'] = $delicacyManagementPrice['current_price'];
                }else{
                    $value['price'] = null;
                }
                $title = 'Product Available Alert (Item code: '.$product_info->sku.')';
                $message = $this->getSendMessageTemplate($flag,$product_info,$value);

                //$this->communication->saveCommunication($title, $message,$value['buyer_id'],$seller_id, 0);
                $this->model_message_message->addSystemMessageToBuyer('product_status',$title, $message, $value['buyer_id']);

            }
        }


    }

    /**
     * [getSendMessageTemplate description] 根据flag 模板
     * @param int $flag
     * @param object $product_info
     * @param array $extra
     * @return string
     * @throws Exception
     */
    public function getSendMessageTemplate($flag,$product_info,$extra){
        //buyer_id
        if($flag == 0){
            $message = '<table   border="0" cellspacing="0" cellpadding="0" >';
            $message .= '<tr><th align="left">Item Code:&nbsp;</th><td>' . $product_info->sku . '</td></tr> ';
            $message .= '<tr><th align="left">Product Name:&nbsp;</th><td>' . $product_info->name . '</td></tr>';
            $message .= '<tr><th align="left">Product Status:&nbsp;</th><td>Unavailable</td></tr></table>';
        }else{
            $country_id = $this->customer->getCountryId();
            //受到运费的影响，这里产生了变化
            $this->load->model('extension/module/product_show');
            $isCollectionFromDomicile = $this->model_extension_module_product_show->get_is_collection_from_domicile($extra['buyer_id']);
            if($extra['price'] == null){
                if(!$isCollectionFromDomicile){
                    $price = $product_info->price;
                    if($price < 0){
                        $price = 0;
                    }else{
                        $price = round($price,2);
                    }
                }else{
                    $price = $product_info->price*$extra['discount'];
                }

            } else{
                if(!$isCollectionFromDomicile){
                    $price = ($extra['price'] + $product_info->freight)*$extra['discount'];
                }else{
                    $price = $extra['price']*$extra['discount'];
                }
            }
            if($country_id == 107){
                $price = (int)$price;
            }else{
                $price = sprintf('%.2f',$price);
            }
            $message = '<table   border="0" cellspacing="0" cellpadding="0" >';
            $message .= '<tr><th align="left">Item Code:&nbsp;</th><td>' . $product_info->sku . '</td></tr> ';
            $message .= '<tr><th align="left">Product Name:&nbsp;</th><td>' . $product_info->name . '</td></tr>';
            $message .= '<tr><th align="left">Product Status:&nbsp;</th><td>Available</td></tr>';
            $message .= '<tr><th align="left">Unit Price:&nbsp;</th><td>' . $price . '</td></tr>';
            $message .= '<tr><th align="left">Available Quantity:&nbsp;</th><td>' . $product_info->quantity . '</td></tr></table>';
        }
        return $message;

    }

    /***
     * [verifyProductStatus description] 验证oc_product 中是否相等
     * @param int $product_id
     * @param int $status
     * @return bool
     */
    public function verifyProductStatus($product_id,$status){
        $query_status = $this->orm->table(DB_PREFIX.'product as p')
            ->where('p.product_id',$product_id)->value('status');
        if($query_status == (int)$status){
            return false;
        }
        return true;
    }

    /**
     * [productQuery sub function to create query that will be executed in calling function]
     * @param  [string] $sql  [base sql query]
     * @param  [array] $data [filter keyword]
     * @return [string]       [sql query string]
     */
    public function productQuery($sql, $data)
    {

        $implode = array();

//		if (isset($data['model'])) {
//			$implode[] = "model = '" . $this->db->escape($data['model']) . "'";
//		}

        //add by lxx
        //combo
        if (isset($data['comboFlag']) && $data['comboFlag'] == 'on') {
            $implode[] = "combo_flag = 1";
        } else {
            $implode[] = "combo_flag = 0";
        }
        if ($data['allowedBuy'] == 1) {
            $implode[] = "buyer_flag = 1";
        } else {
            $implode[] = "buyer_flag = 0";
        }
        if ($this->customer->getId()) {
            $model = $this->db->query("select screenname from oc_customerpartner_to_customer where customer_id = " . (int)$this->customer->getId())->row;
            $implode[] = "model = '" . $this->db->escape($model['screenname']) . "'";
        }
        //end
        /**
         * 是否为配件
         */
        if (isset($data['partFlag']) && $data['partFlag'] == 'on') {
            $implode[] = "part_flag = 1";
        } else {
            $implode[] = "part_flag = 0";
        }

        if (isset($data['sku'])) {

            if ($data['sku']) {

                $implode[] = "sku = '" . $this->db->escape(trim($data['sku'])) . "'";
            } elseif (isset($data['model']) && $data['model'] && $this->config->get('marketplace_auto_generate_sku')) {
                $implode[] = "sku = '" . $this->db->escape(trim($data['sku'])) . "'";
            }
        }else{
            $implode[] = "sku = '" . $this->db->escape(trim($data['mpn'])) . "'";
        }

        if (isset($data['upc'])) {
            $implode[] = "upc = '" . $this->db->escape($data['upc']) . "'";
        }

        if (isset($data['ean'])) {
            $implode[] = "ean = '" . $this->db->escape($data['ean']) . "'";
        }

        if (isset($data['jan'])) {
            $implode[] = "jan = '" . $this->db->escape($data['jan']) . "'";
        }

        if (isset($data['isbn'])) {
            $implode[] = "isbn = '" . $this->db->escape($data['isbn']) . "'";
        }

        if (isset($data['mpn'])) {
            $implode[] = "mpn = '" . $this->db->escape(trim($data['mpn'])) . "'";
        }

        if (isset($data['location'])) {
            $implode[] = "location = '" . $this->db->escape($data['location']) . "'";
        }

        if (isset($data['quantity'])) {
            $implode[] = "quantity = '" . $this->db->escape($data['quantity']) . "'";
        }

        if (isset($data['minimum'])) {
            $implode[] = "minimum = '" . $this->db->escape($data['minimum']) . "'";
        }

        if (isset($data['subtract'])) {
            $implode[] = "subtract = '" . $this->db->escape($data['subtract']) . "'";
        }

        if (isset($data['stock_status_id'])) {
            $implode[] = "stock_status_id = '" . $this->db->escape($data['stock_status_id']) . "'";
        }

        if (isset($data['date_available'])) {
            $implode[] = "date_available = '" . $this->db->escape($data['date_available']) . "'";
        }
        if (isset($data['manufacturer_id'])) {
            $data['manufacturer_id'] = (int)$data['manufacturer_id'];
            $implode[] = "manufacturer_id = '" . $this->db->escape($data['manufacturer_id']) . "'";
        }

        if (isset($data['shipping'])) {
            $implode[] = "shipping = '" . $this->db->escape($data['shipping']) . "'";
        }

        if (isset($data['price'])) {
            if ($data['price'] == '') {
                $data['price'] = 0;
            }
            $implode[] = "price = '" . $this->db->escape($this->currency->convert($data['price'], $this->session->data['currency'], $this->config->get('config_currency'))) . "'";
        }

        if (isset($data['points'])) {
            $implode[] = "points = '" . $this->db->escape($data['points']) . "'";
        }

        if (isset($data['weight'])) {
            $weight = $this->db->escape($data['weight']);
            if (customer()->isUSA()) {
                $weightKg = app(ProductRepository::class)->calculatePoundAndKg($weight, 1, 2);
                $implode[] = "weight = '" . $weight . "'";
                $implode[] = "weight_kg = '" . $weightKg . "'";
            } else {
                $weightPound = app(ProductRepository::class)->calculatePoundAndKg($weight, 2, 1);
                $implode[] = "weight = '" . $weightPound . "'";
                $implode[] = "weight_kg = '" . $weight . "'";
            }
        }

        if (isset($data['weight_class_id'])) {
            $implode[] = "weight_class_id = '" . $this->db->escape($data['weight_class_id']) . "'";
        }

        if (isset($data['length'])) {
            $length = $this->db->escape($data['length']);
            if (customer()->isUSA()) {
                $lengthCm = app(ProductRepository::class)->calculateInchesAndCm($length, 1, 2);
                $implode[] = "length = '" . $length . "'";
                $implode[] = "length_cm = '" . $lengthCm . "'";
            } else {
                $lengthInch = app(ProductRepository::class)->calculateInchesAndCm($length, 2, 1);
                $implode[] = "length = '" . $lengthInch . "'";
                $implode[] = "length_cm = '" . $length . "'";
            }
        }

        if (isset($data['width'])) {
            $width = $this->db->escape($data['width']);
            if (customer()->isUSA()) {
                $widthCm = app(ProductRepository::class)->calculateInchesAndCm($width, 1, 2);
                $implode[] = "width = '" . $width . "'";
                $implode[] = "width_cm = '" . $widthCm . "'";
            } else {
                $widthInch = app(ProductRepository::class)->calculateInchesAndCm($width, 2, 1);
                $implode[] = "width = '" . $widthInch . "'";
                $implode[] = "width_cm = '" . $width . "'";
            }
        }

        if (isset($data['height'])) {
            $height = $this->db->escape($data['height']);
            if (customer()->isUSA()) {
                $heightCm = app(ProductRepository::class)->calculateInchesAndCm($height, 1, 2);
                $implode[] = "height = '" . $height . "'";
                $implode[] = "height_cm = '" . $heightCm . "'";
            } else {
                $heightInch = app(ProductRepository::class)->calculateInchesAndCm($height, 2, 1);
                $implode[] = "height = '" . $heightInch . "'";
                $implode[] = "height_cm = '" . $height . "'";
            }
        }

        if (isset($data['length_class_id'])) {
            $implode[] = "length_class_id = '" . $this->db->escape($data['length_class_id']) . "'";
        }

        /*if (isset($data['status1'])) {
            $implode[] = "status = '" . $this->db->escape($data['status1']) . "'";
        }*/

        if (isset($data['status'])) {
            $implode[] = "status = '" . $this->db->escape($data['status']) . "'";
        }
        if (isset($data['tax_class_id'])) {
            $implode[] = "tax_class_id = '" . $this->db->escape($data['tax_class_id']) . "'";
        }

        if (isset($data['sort_order'])) {
            $implode[] = "sort_order = '" . $this->db->escape($data['sort_order']) . "'";
        }

        if (isset($data['image'])) {
            $implode[] = "image = '" . $this->db->escape($data['image']) . "'";
        }

        if (isset($data['price_display'])) {
            $implode[] = "price_display = " . (int)$this->db->escape($data['price_display']);
        }

        if (isset($data['quantity_display'])) {
            $implode[] = "quantity_display = " . (int)$this->db->escape($data['quantity_display']);
        }
        if (isset($data['need_install'])) {
            $implode[] = "need_install = " . (int)$this->db->escape($data['need_install']);
        }
        if (isset($data['product_size'])) {
            $implode[] = "product_size = " . $this->db->escape($data['product_size']);
        }
        if ($implode) {
            $sql .= implode(" , ", $implode) . " , ";
        }

        return $sql;
    }

    /**
     * 更新商品信息
     *
     * @param int $product_id
     * @param array $data
     * @param int $isAdd
     * @return array|bool
     */
    public function productAddUpdate($product_id, $data, $isAdd = 1)
    {
        $this->load->model('customerpartner/htmlfilter');
        $htmlfilter = $this->model_customerpartner_htmlfilter;

        $this->handleProductDescription($product_id,$data); //老代码挪走并重写

        if ($this->config->get('marketplace_seller_product_store')) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_store WHERE product_id = '" . (int)$product_id . "'");

            if ($this->config->get('marketplace_seller_product_store') == 'choose_store') {

                foreach ($data['product_store'] as $store_id) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "'");
                }
            } elseif ($this->config->get('marketplace_seller_product_store') == 'own_store') {
                $seller_store_id = $this->db->query("SELECT store_id FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int)$this->customer->getId() . "'")->row;

                $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$seller_store_id['store_id'] . "'");
            } else {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = " . (int)$this->config->get('config_store_id'));
                $this->load->model('setting/store');
                $stores = $this->model_setting_store->getStores();

                foreach ($stores as $key => $store) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store['store_id'] . "'");
                }
            }
        } else {
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = " . (int)$this->config->get('config_store_id'));
        }

        //前台传了1  线上数据库此表也是空，应该没有用处
        if (isset($data['product_attribute_tab']) && $data['product_attribute_tab']) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "'");
        }

        if (isset($data['product_attribute'])) {
            foreach ($data['product_attribute'] as $product_attribute) {
                if ($product_attribute['attribute_id']) {

                    foreach ($product_attribute['product_attribute_description'] as $language_id => $product_attribute_description) {

                        $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$product_attribute['attribute_id'] . "' AND language_id = '" . (int)$language_id . "'");

                        $this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = '" . (int)$product_id . "', attribute_id = '" . (int)$product_attribute['attribute_id'] . "', language_id = '" . (int)$language_id . "', text = '" . $this->db->escape(htmlentities($htmlfilter->HTMLFilter(html_entity_decode($product_attribute_description['text']), '', true))) . "'");
                    }
                }
            }
        }

        //这坨可能没用
        if (isset($data['product_option'])) {
            foreach ($data['product_option'] as $product_option) {
                if (isset($product_option['type']) && ($product_option['type'] == 'select' || $product_option['type'] == 'radio' || $product_option['type'] == 'checkbox' || $product_option['type'] == 'image')) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_option SET product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', required = '" . (int)$product_option['required'] . "'");

                    $product_option_id = $this->db->getLastId();

                    if (isset($product_option['product_option_value']) && count($product_option['product_option_value']) > 0) {
                        foreach ($product_option['product_option_value'] as $product_option_value) {
                            $this->db->query("INSERT INTO " . DB_PREFIX . "product_option_value SET product_option_id = '" . (int)$product_option_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', option_value_id = '" . (int)$product_option_value['option_value_id'] . "', quantity = '" . (int)$product_option_value['quantity'] . "', subtract = '" . (int)$product_option_value['subtract'] . "', price = '" . (float)$this->currency->convert($product_option_value['price'], $this->session->data['currency'], $this->config->get('config_currency')) . "', price_prefix = '" . $this->db->escape($product_option_value['price_prefix']) . "', points = '" . (int)$product_option_value['points'] . "', points_prefix = '" . $this->db->escape($product_option_value['points_prefix']) . "', weight = '" . (float)$product_option_value['weight'] . "', weight_prefix = '" . $this->db->escape($product_option_value['weight_prefix']) . "'");
                        }
                    } else {
                        $this->db->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_option_id = '" . $product_option_id . "'");
                    }
                } else {
                    if (isset($product_option['option_id']) && isset($product_option['option_value']) && isset($product_option['required'])) {
                        $this->db->query("INSERT INTO " . DB_PREFIX . "product_option SET product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', value = '" . $this->db->escape(htmlentities($htmlfilter->HTMLFilter(html_entity_decode($product_option['option_value']), '', true))) . "', required = '" . (int)$product_option['required'] . "'");
                    }
                }
            }
        }

        //颜色
        $oldColorOption = Option::MIX_OPTION_ID; //13
        $newColorOption = Option::COLOR_OPTION_ID; //14
        app(ProductOptionService::class)->delOptionAndValueInfo($product_id, [$oldColorOption, $newColorOption]);
        if (isset($data['color']) && $data['color'] != '' && $data['color'] != '0') {
            app(ProductOptionService::class)
                ->insertProductOptionValue($product_id, $newColorOption, (int)$data['color']);
        }
        //材质
        $newMaterialOption = Option::MATERIAL_OPTION_ID; //15
        app(ProductOptionService::class)->delOptionAndValueInfo($product_id, [$newMaterialOption]);
        if (isset($data['material']) && $data['material'] != '' && $data['material'] != '0') {
            app(ProductOptionService::class)
                ->insertProductOptionValue($product_id, $newMaterialOption, (int)$data['material']);
        }
        //前台传了1  线上数据库此表也是空，应该没有用处
        if (isset($data['product_discount_tab']) && $data['product_discount_tab']) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product_id . "'");
        }

        if (isset($data['product_discount'])) {
            foreach ($data['product_discount'] as $product_discount) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_discount SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$product_discount['customer_group_id'] . "', quantity = '" . (int)$product_discount['quantity'] . "', priority = '" . (int)$product_discount['priority'] . "', price = '" . (float)$this->currency->convert($product_discount['price'], $this->session->data['currency'], $this->config->get('config_currency')) . "', date_start = '" . $this->db->escape($product_discount['date_start']) . "', date_end = '" . $this->db->escape($product_discount['date_end']) . "'");
            }
        }

        //前台传了1  线上数据库此表也是空，应该没有用处
        if (isset($data['product_special_tab']) && $data['product_special_tab']) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$product_id . "'");
        }

        if (isset($data['product_special'])) {
            foreach ($data['product_special'] as $product_special) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_special SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$product_special['customer_group_id'] . "', priority = '" . (int)$product_special['priority'] . "', price = '" . (float)$this->currency->convert($product_special['price'], $this->session->data['currency'], $this->config->get('config_currency')) . "', date_start = '" . $this->db->escape($product_special['date_start']) . "', date_end = '" . $this->db->escape($product_special['date_end']) . "'");
            }
        }

        //前台传了1
        if (isset($data['product_link_tab']) && $data['product_link_tab']) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_filter WHERE product_id = '" . (int)$product_id . "'");
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_download WHERE product_id = '" . (int)$product_id . "'");
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$product_id . "'");
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE related_id = '" . (int)$product_id . "'");
        }

        if (isset($data['product_download'])) {
            foreach ($data['product_download'] as $download_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_download SET product_id = '" . (int)$product_id . "', download_id = '" . (int)$download_id . "'");
            }
        }

        if ($isAdd == 2) {
            ProductToCategory::where('product_id', $product_id)->delete();
        }
        if (isset($data['product_category'])) {
            $categoryId = app(CategoryRepository::class)->getLastLowerCategoryId($data['product_category']);
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$categoryId . "'");
        }

        if (isset($data['product_filter'])) {
            foreach ($data['product_filter'] as $filter_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_filter SET product_id = '" . (int)$product_id . "', filter_id = '" . (int)$filter_id . "'");
            }
        }

        if (isset($data['product_related'])) {
            foreach ($data['product_related'] as $related_id) {
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$product_id . "' AND related_id = '" . (int)$related_id . "'");
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$product_id . "', related_id = '" . (int)$related_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$related_id . "' AND related_id = '" . (int)$product_id . "'");
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$related_id . "', related_id = '" . (int)$product_id . "'");
            }
        }
        if (isset($data['keyword']) AND $data['keyword']) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE query = 'product_id=" . (int)$product_id . "' AND store_id = " . (int)$this->config->get('config_store_id') . "");
            foreach ($data['keyword'][(int)$this->config->get('config_store_id')] as $language_id => $keyword) {
                if (trim($keyword)) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$this->config->get('config_store_id') . "', language_id = '" . (int)$language_id . "', query = 'product_id=" . (int)$product_id . "', keyword = '" . $this->db->escape($keyword) . "'");
                }
            }
        }

        return true;
    }

    /**
     * 商品描述等属性处理(新增&编辑)
     *
     * @param  int   $productId
     * @param  array $data
     *
     * @return bool
     */
    private function handleProductDescription($productId, $data)
    {
        $this->load->model('customerpartner/htmlfilter');
        $htmlfilter = $this->model_customerpartner_htmlfilter;
        $isExist = $this->orm::table('oc_product_description')->where('product_id', $productId)->count();
        if ((!isset($data['meta_description']) || empty($data['meta_description'])) && $this->config->get('marketplace_auto_generate_sku')) {
            $data['meta_description'] = $data['name'];
        }
        $returnWarranty = $data['return_warranty'];
        $returnWarrantyText = $returnWarranty['return_warranty_text'] ?? '';
        unset($returnWarranty['return_warranty_text']);
        if ($isExist > 0) {
            $this->orm::table('oc_product_description')->where('product_id', $productId)->update([
                'language_id' => 1, //兼容老代码,最初有多语言,改版后只有一种,二次改版后这儿固定为1
                'name' => trim($this->db->escape($data['name'])),
                'description' => $data['description'],
                'meta_title' => trim($this->db->escape($data['name'])),
                'meta_keyword' => isset($data['meta_keyword']) ? $this->db->escape(htmlentities($htmlfilter->HTMLFilter(html_entity_decode($data['meta_keyword']), '', true))) : '',
                'meta_description' => $this->db->escape($data['meta_description']),
                'tag' => isset($data['tag']) ? $this->db->escape($data['tag']) : '',
                'return_warranty' => json_encode($returnWarranty['return_warranty']),
                'return_warranty_text' => $returnWarrantyText,
            ]);
        } else {
            $this->orm::table('oc_product_description')->insert([
                'product_id' => $productId,
                'language_id' => 1,
                'name' => trim($this->db->escape($data['name'])),
                'description' => $data['description'],
                'meta_title' => trim($this->db->escape($data['name'])),
                'meta_keyword' => isset($data['meta_keyword']) ? $this->db->escape(htmlentities($htmlfilter->HTMLFilter(html_entity_decode($data['meta_keyword']), '', true))) : '',
                'meta_description' => $this->db->escape($data['meta_description']),
                'tag' => isset($data['tag']) ? $this->db->escape($data['tag']) : '',
                'return_warranty' => json_encode($returnWarranty['return_warranty']),
                'return_warranty_text' => $returnWarrantyText,
            ]);
        }
        return true;
    }

    /**
     * [getProductDescriptions to get description of particular product]
     * @param  [integer] $product_id [product id of product]
     * @return [array]             [description of particular product]
     */
    public function getProductDescriptions($product_id)
    {
        $product_description_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_description WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_description_data[$result['language_id']] = array(
                'name' => $result['name'],
                'description' => $result['description'],
                'meta_keyword' => $result['meta_keyword'],
                'meta_title' => $result['meta_title'],
                'meta_description' => $result['meta_description'],
                'tag' => $result['tag']
            );
        }

        return $product_description_data;
    }

    /**
     * [getProductCategories to get categories of product]
     * @param  [integer] $product_id [product id of product]
     * @return [array]             [categories of particular product]
     */
    public function getProductCategories($product_id)
    {
        $product_category_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_category_data[] = $result['category_id'];
        }

        return $product_category_data;
    }

    /**
     * [getProductFilters to get filters of any product]
     * @param  [integer] $product_id [product id of product]
     * @return [array]             [filters of particular product]
     */
    public function getProductFilters($product_id)
    {
        $product_filter_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_filter WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_filter_data[] = $result['filter_id'];
        }

        return $product_filter_data;
    }

    /**
     * [getProductAttributes to get attributes of particular product]
     * @param  [integer] $product_id [product id of product]
     * @return [array]             [attributes of particular product]
     */
    public function getProductAttributes($product_id)
    {

        $product_attribute_data = array();

        $product_attribute_query = $this->db->query("SELECT DISTINCT a.attribute_id, ad.name FROM " . DB_PREFIX . "product_attribute pa LEFT JOIN " . DB_PREFIX . "attribute a ON (pa.attribute_id = a.attribute_id) LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE pa.product_id = '" . (int)$product_id . "' AND ad.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY a.sort_order, ad.name");

        foreach ($product_attribute_query->rows as $product_attribute) {

            $product_attribute_description_data = array();

            $product_attribute_description_query = $this->db->query("SELECT pa.language_id, ad.name, pa.text FROM " . DB_PREFIX . "product_attribute pa LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (pa.attribute_id = ad.attribute_id) WHERE pa.product_id = '" . (int)$product_id . "' AND pa.attribute_id = '" . (int)$product_attribute['attribute_id'] . "' AND ad.language_id = '" . (int)$this->config->get('config_language_id') . "'");

            foreach ($product_attribute_description_query->rows as $product_attribute_description) {

                $product_attribute_description_data[$product_attribute_description['language_id']] = array(
                    'name' => $product_attribute_description['name'],
                    'text' => $product_attribute_description['text']
                );
            }

            $product_attribute_data[] = array(
                'attribute_id' => $product_attribute['attribute_id'],
                'name' => $product_attribute['name'],
                'product_attribute_description' => $product_attribute_description_data
            );
        }

        return $product_attribute_data;
    }

    /**
     * [getProductOptions to get options of particular product]
     * @param  [integer] $product_id [product id of product]
     * @param  [string] $tabletype  [to define marketplace's tables or opencart's tables]
     * @return [array]             [options of particular product]
     */
    public function getProductOptions($product_id, $tabletype = '')
    {
        $product_option_data = array();

        $product_option_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . $tabletype . "product_option` po LEFT JOIN `" . DB_PREFIX . "option` o ON (po.option_id = o.option_id) LEFT JOIN `" . DB_PREFIX . "option_description` od ON (o.option_id = od.option_id) WHERE po.product_id = '" . (int)$product_id . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "'");

        foreach ($product_option_query->rows as $product_option) {
            $product_option_value_data = array();

            $product_option_value_query = $this->db->query("SELECT * FROM " . DB_PREFIX . $tabletype . "product_option_value WHERE product_option_id = '" . (int)$product_option['product_option_id'] . "'");

            foreach ($product_option_value_query->rows as $product_option_value) {
                $product_option_value_data[] = array(
                    'product_option_value_id' => $product_option_value['product_option_value_id'],
                    'option_value_id' => $product_option_value['option_value_id'],
                    'quantity' => $product_option_value['quantity'],
                    'subtract' => $product_option_value['subtract'],
                    'price' => $product_option_value['price'],
                    'price_prefix' => $product_option_value['price_prefix'],
                    'points' => $product_option_value['points'],
                    'points_prefix' => $product_option_value['points_prefix'],
                    'weight' => $product_option_value['weight'],
                    'weight_prefix' => $product_option_value['weight_prefix']
                );
            }

            $product_option_data[] = array(
                'product_option_id' => $product_option['product_option_id'],
                'option_id' => $product_option['option_id'],
                'name' => $product_option['name'],
                'type' => $product_option['type'],
                'product_option_value' => $product_option_value_data,
                'option_value' => $product_option['value'] ?? 0,
                'required' => $product_option['required']
            );
        }

        return $product_option_data;
    }

    /**
     * [getProductDiscounts to get discount of particular product]
     * @param  [integer] $product_id [product id of product]
     * @return [array]             [discounts of particular product]
     */
    public function getProductDiscounts($product_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product_id . "' ORDER BY quantity, priority, price");

        return $query->rows;
    }

    /**
     * [getProductSpecials to get specials of particular product]
     * @param  [integer] $product_id [product id of product]
     * @return [array]             [specials of particular product]
     */
    public function getProductSpecials($product_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$product_id . "' ORDER BY priority, price");

        return $query->rows;
    }

    /**
     * [getProductDownloads to get downloads of particular product]
     * @param  [integer] $product_id [product id of product]
     * @return [array]             [downloads of particular product]
     */
    public function getProductDownloads($product_id)
    {
        $product_download_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_download WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_download_data[] = $result['download_id'];
        }

        return $product_download_data;
    }

    /**
     * [getProductRelated to get rewards of particular product]
     * @param  [integer] $product_id [product id of product]
     * @param  [string] $tabletype  [to define marketplace's tables or opencart's tables]
     * @return [array]             [rewards of particular product]
     */
    public function getProductRelated($product_id, $tabletype = '')
    {
        $product_related_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . $tabletype . "product_related WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_related_data[] = $result['related_id'];
        }

        return $product_related_data;
    }

    /**
     * [getProductRelatedInfo to get related products of particular product]
     * @param  [integer] $product_id [product id of product]
     * @return [array]             [related products of particular product]
     */
    public function getProductRelatedInfo($product_id)
    {
        $query = $this->db->query("SELECT DISTINCT *, (SELECT keyword FROM " . DB_PREFIX . "seo_url WHERE query = 'product_id=" . (int)$product_id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "') AS keyword FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) WHERE p.product_id = '" . (int)$product_id . "' AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

        return $query->row;
    }

    /**
     * [IsApplyForSellership to check that a customer is already applied for sellership/partnership or not]
     */
    public function IsApplyForSellership()
    {
        $query = $this->db->query("SELECT customer_id FROM " . DB_PREFIX . "customerpartner_to_customer WHERE customer_id = '" . (int)$this->customer->getId() . "'")->row;

        if ($query) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * [chkIsPartner to check customer is partner or not]
     * @return [boolean] [true or false]
     */
    public function chkIsPartner()
    {

        $sql = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_customer WHERE customer_id = '" . (int)$this->customer->getId() . "'");

        if (count($sql->row) && $sql->row['is_partner'] == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * [CustomerCountry_Id fetch current customer's country id]
     * @param [type] $customer_id [current customer's customer_id]
     */
    public function CustomerCountry_Id($customer_id)
    {

        $countryid = $this->db->query("SELECT country_id FROM " . DB_PREFIX . "address WHERE customer_id = '" . (int)$customer_id . "' ")->row;

        return $countryid;

    }

    /**
     * [becomePartner to register as a seller/partner]
     * @param  [string] $shop       [shopname of seller]
     * @param  [integer] $country_id [country id of registered customer]
     * @param  [string] $message    [just a meesage while registering]
     */
    public function becomePartner($shop, $country_id, $customer_id, $message = '')
    {

        $country = $this->db->query("SELECT iso_code_2 FROM " . DB_PREFIX . "country WHERE country_id = '" . (int)$country_id . "' ")->row;

        $countryCode = '';
        $countryCodeFlag = '';
        if ($country && isset($country['iso_code_2'])) {
            $countryCode = $country['iso_code_2'];
            $countryCodeFlag = 'image/flags/' . strtolower($countryCode) . '.png';
        }

        $commission = (int)$this->config->get('marketplace_commission') ? (int)$this->config->get('marketplace_commission') : 0;

        if ($this->config->get('marketplace_partnerapprov')) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "customerpartner_to_customer set customer_id = '" . (int)$customer_id . "', is_partner='1', country = '" . $this->db->escape($countryCode) . "', commission = '" . (float)$commission . "', companyname = '" . $this->db->escape($shop) . "', screenname = '" . $this->db->escape($shop) . "', countrylogo = '" . $this->db->escape($countryCodeFlag) . "' ");

            // membership modification : if partner approve enable then membership automatic membershhip will be assigned
            if ($this->config->get('module_wk_seller_group_status') && !$this->db->query("SELECT * FROM `" . DB_PREFIX . "seller_group_customer_seller_group` WHERE seller_id = '" . (int)$customer_id . "'")->num_rows) {
                $this->load->model('account/wk_membership_catalog');
                $freeMembership = $this->db->query("SELECT * FROM " . DB_PREFIX . "seller_group WHERE autoApprove = 1 ")->row;
                if ($freeMembership && isset($freeMembership['product_id']) && $freeMembership['product_id']) {
                    $group_id = $freeMembership['groupid'];
                    $this->model_account_wk_membership_catalog->update_customer_quantity($this->customer->getId(), $group_id, 'unpaid', true);
                }
            }
            // membership code ends

        } else {
            $this->db->query("INSERT INTO " . DB_PREFIX . "customerpartner_to_customer set customer_id = '" . (int)$customer_id . "', is_partner='0', country = '" . $this->db->escape($countryCode) . "', countrylogo = '" . $this->db->escape($countryCodeFlag) . "', companyname = '" . $this->db->escape($shop) . "', screenname = '" . $this->db->escape($shop) . "' ");
        }

        $data = array(
            'message' => $message,
            'shop' => $shop,
            'commission' => $commission,
            'seller_id' => $customer_id,
            'customer_id' => false,
            'mail_id' => $this->config->get('marketplace_mail_partner_request'),
            'mail_from' => $this->config->get('marketplace_adminmail'),
            'mail_to' => $this->customer->getEmail(),
        );

        $values = array(
            'message' => $data['message'],
            'commission' => $data['commission'] . "%",
        );

        /**
         * send mail to Admin / Customer after request for Partnership
         */
        $this->load->model('customerpartner/mail');

        /**
         * customer applied for sellership to customer
         */
        $this->model_customerpartner_mail->mail($data, $values);

        /**
         * customer applied for sellership to admin
         */
        $data['mail_id'] = $this->config->get('marketplace_mail_partner_admin');
        $data['mail_from'] = $this->customer->getEmail();
        $data['mail_to'] = $this->config->get('marketplace_adminmail');

        $this->model_customerpartner_mail->mail($data, $values);

    }

    /**
     * [updateProfile to update the existing seller's profile]
     * @param  [array] $data [details about the seller's profile]
     */
    public function updateProfile($data)
    {

        $this->load->model('customerpartner/htmlfilter');
        $htmlfilter = $this->model_customerpartner_htmlfilter;

        $impolde = array();

        if (isset($data['screenName']))
            $impolde[] = 'screenname = "' . $this->db->escape(trim($data['screenName'])) . '"';

        if (isset($data['shortProfile']))
            $impolde[] = 'shortprofile = "' . $this->db->escape(htmlentities($htmlfilter->HTMLFilter(html_entity_decode($data['shortProfile']), '', true))) . '"';

        if (isset($data['twitterId']))
            $impolde[] = 'twitterid = "' . $this->db->escape($data['twitterId']) . '"';

        if (isset($data['facebookId']))
            $impolde[] = 'facebookid = "' . $this->db->escape($data['facebookId']) . '"';

        if (isset($data['backgroundcolor']))
            $impolde[] = 'backgroundcolor = "' . $this->db->escape($data['backgroundcolor']) . '"';

        if (isset($data['companyLocality']))
            $impolde[] = 'companylocality = "' . $this->db->escape($data['companyLocality']) . '"';

        if (isset($data['companyName']))
            $impolde[] = 'companyname = "' . $this->db->escape($data['companyName']) . '"';

        if (isset($data['companyDescription']))
            $impolde[] = 'companydescription = "' . $this->db->escape(htmlentities(html_entity_decode($data['companyDescription']))) . '"';

        if (isset($data['otherpayment']))
            $impolde[] = 'otherpayment = "' . $this->db->escape(htmlentities($htmlfilter->HTMLFilter(html_entity_decode($data['otherpayment']), '', true))) . '"';

        if (isset($data['taxinfo']))
            $impolde[] = 'taxinfo = "' . $this->db->escape(htmlentities($htmlfilter->HTMLFilter(html_entity_decode($data['taxinfo']), '', true))) . '"';

        if (isset($data['paypalid']))
            $impolde[] = 'paypalid = "' . $this->db->escape($data['paypalid']) . '"';

        if (isset($data['paypalfirst']))
            $impolde[] = 'paypalfirstname = "' . $this->db->escape($data['paypalfirst']) . '"';

        if (isset($data['paypallast']))
            $impolde[] = 'paypallastname = "' . $this->db->escape($data['paypallast']) . '"';

        if (isset($data['avatar'])) {
            $impolde[] = 'avatar = "' . $this->db->escape($data['avatar']) . '"';
            $impolde[] = 'companylogo = "' . $this->db->escape($data['avatar']) . '"';
        }

        if (isset($data['companybanner']))
            $impolde[] = 'companybanner = "' . $this->db->escape($data['companybanner']) . '"';

        if ($impolde) {

            $sql = "UPDATE " . DB_PREFIX . "customerpartner_to_customer SET ";
            $sql .= implode(", ", $impolde);
            $sql .= " WHERE customer_id = '" . (int)$this->customer->getId() . "'";

            $this->db->query($sql);
        }
    }

    /**
     * [getProfile to get seller's profile]
     * @return [array] [details of seller]
     */
    public function getProfile()
    {
        return $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_customer c2c LEFT JOIN " . DB_PREFIX . "customer c ON (c2c.customer_id = c.customer_id) where c2c.customer_id = '" . (int)$this->customer->getId() . "'")->row;
    }

    public function getCountryByCustomerId()
    {
        $customer_id = (int)$this->customer->getId();
        $sql = "SELECT cty.* FROM oc_customer cc  LEFT JOIN oc_country cty
ON cc.`country_id` = cty.`country_id` WHERE cc.`customer_id`=" . $customer_id;
        return $this->db->query($sql)->row;
    }

    /**
     * [getsellerEmail to get the seller's email id]
     * @param  [integer] $seller_id [customer id of particuler seller]
     * @return [string|boolean]            [email of false value]
     */
    public function getsellerEmail($seller_id)
    {
        $result = $this->db->query("SELECT email FROM " . DB_PREFIX . "customerpartner_to_customer c2c LEFT JOIN " . DB_PREFIX . "customer c ON (c2c.customer_id = c.customer_id) where c2c.customer_id = '" . (int)$seller_id . "'")->row;
        if (isset($result['email'])) {
            return $result['email'];
        } else {
            return false;
        }
    }

    /**
     * [getCountry to get seller's country]
     * @return [string] [country of seller]
     */
    public function getCountry()
    {
        return $this->db->query("SELECT * FROM " . DB_PREFIX . "country")->rows;
    }

    /**
     * [getOrderHistories to get histories of an order]
     * @param  [integer] $order_id [order id of particular order]
     * @return [array]           [order histories of particular order]
     */
    public function getOrderHistories($order_id)
    {
        $query = $this->db->query("SELECT date_added, os.name AS status, oh.comment FROM " . DB_PREFIX . "customerpartner_to_order_status oh LEFT JOIN " . DB_PREFIX . "order_status os ON oh.order_status_id = os.order_status_id WHERE oh.order_id = '" . (int)$order_id . "' AND (oh.customer_id = '" . (int)$this->customer->getId() . "' OR oh.customer_id ='0') AND os.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY oh.date_added");

        return $query->rows;
    }

    /**
     * [addOrderHistory to add history to an order]
     * @param [integer] $order_id [order id on particular order]
     * @param [array] $data     [detail about what have to added to order history]
     */
    public function addOrderHistory($order_id, $data, $seller_change_order_status_name = '')
    {

        if (isset($data['product_ids']) && $data['product_ids']) {

            $products = explode(",", $data['product_ids']);

            foreach ($products as $value) {
                $product_details = $this->getProduct($value);
                $product_names[] = $product_details['name'];
            }

            $product_name = implode(",", $product_names);

            if ($data['comment']) {

                $comment = $product_name . ' status has been changed to' . ' ' . $seller_change_order_status_name . "\n\n";
                $comment .= strip_tags(html_entity_decode($data['comment'], ENT_QUOTES, 'UTF-8')) . "\n\n";
            } else {
                $comment = $product_name . ' status has been changed to' . ' ' . $seller_change_order_status_name;
            }

        } else {

            $comment = strip_tags(html_entity_decode($data['comment'], ENT_QUOTES, 'UTF-8')) . "\n\n";
        }

        $this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order_id . "', order_status_id = '" . (int)$data['order_status_id'] . "', notify = '1', comment = '" . $this->db->escape($comment) . "', date_added = NOW()");

        $order_info = $this->getOrder($order_id);

        $this->load->model('account/notification');
        $activity_data = array(
            'id' => $order_info['customer_id'],
            'status' => $data['order_status_id'],
            'order_id' => $order_id
        );

        //$this->model_account_notification->addActivity('order_status', $activity_data);
        $this->load->model('checkout/order');
        $this->model_checkout_order->addSystemMessageAboutOrderStatus($activity_data);

        $sellerEmail = $this->customer->getEmail();
        $seller_details = $this->getProfile();

        $this->load->language('account/customerpartner/orderinfo');


        $subject = sprintf($this->language->get('m_text_subject'), $order_info['store_name'], $order_id);

        $message = $this->language->get('m_text_order') . ' ' . $order_id . "\n";
        $message .= $this->language->get('m_text_date_added') . ' ' . date($this->language->get('m_date_format_short'), strtotime($order_info['date_added'])) . "\n\n";

        $order_status_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_status WHERE order_status_id = '" . (int)$data['order_status_id'] . "' AND language_id = '" . (int)$order_info['language_id'] . "'");

        if ($order_status_query->num_rows && isset($product_name) && $product_name) {
            $message .= sprintf($this->language->get('m_text_order_status'), $product_name) . "\n";
            $message .= $order_status_query->row['name'] . "\n\n";
        }

        if ($order_info['customer_id']) {
            $message .= $this->language->get('m_text_link') . "\n";
            $message .= html_entity_decode($order_info['store_url'] . 'index.php?route=account/order/info&order_id=' . $order_id, ENT_QUOTES, 'UTF-8') . "\n\n";
        }

        if ($data['comment']) {
            $message .= $this->language->get('m_text_comment') . "\n\n";
            $message .= strip_tags(html_entity_decode($data['comment'], ENT_QUOTES, 'UTF-8')) . "\n\n";
        }

        $message .= $this->language->get('m_text_footer');

        if (version_compare(VERSION, '2.0.1.1', '<=')) {

            /*Old mail code*/
            $mail = new Mail($this->config->get('config_mail'));
            $mail->setTo($order_info['email']);
            $mail->setFrom($sellerEmail);
            $mail->setSender($order_info['store_name']);
            $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
            $mail->setText(html_entity_decode($message, ENT_QUOTES, 'UTF-8'));
            $mail->send();
        } else {

            $mail = new Mail();
            $mail->protocol = $this->config->get('config_mail_protocol');
            $mail->parameter = $this->config->get('config_mail_parameter');
            $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
            $mail->smtp_username = $this->config->get('config_mail_smtp_username');
            $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
            $mail->smtp_port = $this->config->get('config_mail_smtp_port');
            $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

            $mail->setTo($order_info['email']);
            $mail->setFrom($sellerEmail);
            $mail->setSender($order_info['store_name']);
            $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
            $mail->setText(html_entity_decode($message, ENT_QUOTES, 'UTF-8'));
            $mail->send();

        }


        if ($this->config->get('marketplace_adminnotify')) {

            $subject = sprintf($this->language->get('m_text_subject'), $order_info['store_name'], $order_id);

            $message = $this->language->get('m_text_order') . ' ' . $order_id . "\n";
            $message .= $this->language->get('m_text_date_added') . ' ' . date($this->language->get('m_date_format_short'), strtotime($order_info['date_added'])) . "\n\n";

            $order_status_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_status WHERE order_status_id = '" . (int)$data['order_status_id'] . "' AND language_id = '" . (int)$order_info['language_id'] . "'");

            if ($order_status_query->num_rows && isset($product_name) && $product_name) {
                $message .= sprintf($this->language->get('m_text_order_status_admin'), $product_name) . "\n";
                $message .= $order_status_query->row['name'] . "\n\n";
            }

            if ($data['comment']) {
                $message .= $this->language->get('m_text_comment') . "\n\n";
                $message .= strip_tags(html_entity_decode($data['comment'], ENT_QUOTES, 'UTF-8')) . "\n\n";
            }

            $message .= $this->language->get('m_text_footer');

            if (version_compare(VERSION, '2.0.1.1', '<=')) {

                /*Old mail code*/
                $mail = new Mail($this->config->get('config_mail'));
                $mail->setTo($this->config->get('marketplace_adminmail'));
                $mail->setFrom($sellerEmail);
                $mail->setSender($order_info['store_name']);
                $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
                $mail->setText(html_entity_decode($message, ENT_QUOTES, 'UTF-8'));
                $mail->send();
            } else {

                $mail = new Mail();
                $mail->protocol = $this->config->get('config_mail_protocol');
                $mail->parameter = $this->config->get('config_mail_parameter');
                $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
                $mail->smtp_username = $this->config->get('config_mail_smtp_username');
                $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
                $mail->smtp_port = $this->config->get('config_mail_smtp_port');
                $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

                $mail->setTo($this->config->get('marketplace_adminmail'));
                $mail->setFrom($sellerEmail);
                $mail->setSender($order_info['store_name']);
                $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
                $mail->setText(html_entity_decode($message, ENT_QUOTES, 'UTF-8'));
                $mail->send();
            }
        }

        return true;
    }

    /**
     * [getSellerOrdersByProduct to get all orders by particular product]
     * @param  [type] $product_id [product id of particular product]
     * @param  [integer] $page       [fetched data limit start and end]
     * @return [array]             [order details]
     */
    public function getSellerOrdersByProduct($product_id, $page)
    {
        $limit = 12;
        $start = ($page - 1) * $limit;

        $sql = $this->db->query("SELECT o.order_id ,o.date_added, CONCAT(o.firstname ,' ',o.lastname) name ,os.name orderstatus, c2o.price, c2o.quantity, c2o.paid_status  FROM " . DB_PREFIX . "order_status os LEFT JOIN `" . DB_PREFIX . "order` o ON (os.order_status_id = o.order_status_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_order c2o ON (o.order_id = c2o.order_id) WHERE c2o.customer_id = '" . (int)$this->customer->getId() . "'  AND os.language_id = '" . (int)$this->config->get('config_language_id') . "' AND c2o.product_id = '" . (int)$product_id . "' ORDER BY o.order_id DESC LIMIT $start,$limit ");
        return ($sql->rows);
    }

    /**
     * [getSellerOrdersTotalByProduct total count of orders as per the particular product]
     * @param  [integer] $product_id [product id of particular product]
     * @return [integer]             [total number of products]
     */
    public function getSellerOrdersTotalByProduct($product_id)
    {
        $sql = $this->db->query("SELECT o.order_id ,o.date_added, CONCAT(o.firstname ,' ',o.lastname) name ,os.name orderstatus  FROM " . DB_PREFIX . "order_status os LEFT JOIN `" . DB_PREFIX . "order` o ON (os.order_status_id = o.order_status_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_order c2o ON (o.order_id = c2o.order_id) WHERE c2o.customer_id = '" . (int)$this->customer->getId() . "'  AND os.language_id = '" . (int)$this->config->get('config_language_id') . "' AND c2o.product_id = '" . (int)$product_id . "' ORDER BY o.order_id ");

        return (count($sql->rows));
    }

    /**
     * @param array $data
     * @return Generator
     */
    public function getSellerOrdersForUpdate($data = array())
    {
        $sql = "SELECT a.*,ifnull(pq.amount,0) as quote,pq.amount_price_per,pq.amount_service_fee_per FROM ( ";
        $sql .= " SELECT oo.order_id AS orderId,oc.nickname,oc.user_number,op.mpn,op.sku AS ItemCode,oop.order_product_id,oop.name,oop.quantity,";
        $sql .= " oop.price AS SalesPrice,oop.service_fee as serviceFee,oop.service_fee_per,oo.date_added,op.product_id,cto.customer_id AS seller_id,oop.package_fee,oop.agreement_id,oop.type_id,oop.freight_per,oop.freight_difference_per ";
        $sql .= ", oop.campaign_amount,oop.coupon_amount, oop.discount_price";
        $sql .= ", IF(oop.discount IS NULL, '', 100-oop.discount) AS discountShow";
        $sql .= " FROM oc_order as oo";
        $sql .= " join oc_order_product as oop on oop.order_id = oo.order_id";
        $sql .= " join oc_order_status as oos on oos.order_status_id = oo.order_status_id";
        $sql .= " join oc_customer as oc on oc.customer_id = oo.customer_id";
        $sql .= " join oc_customerpartner_to_order as cto on cto.order_id = oo.order_id and cto.product_id = oop.product_id";
        $sql .= " left join oc_product op on op.product_id = cto.product_id ";
        if (!empty($data['filter_sku_mpn'])) {
            $sql .= " left join oc_order_product as op1 on op1.order_id = oo.order_id ";
            $sql .= " left join oc_product as p1 on p1.product_id = op1.product_id ";
        }
        $sql .= " WHERE cto.seller_access = '1'";

        if (isset($data['filter_fill_freight_product'])) {
            $sql .= " AND op.product_type != 3";
        }

        if (isset($data['customer_id'])){
            $sql .= " AND ( cto.customer_id = " . $data['customer_id'];
            if(!empty($data['margin_store_id']) && !empty($data['margin_order_list']) && !empty($data['bx_product_id'])){
                $sql .= " OR (cto.customer_id IN (" . implode(',', $data['margin_store_id'])
                    . ") AND oo.order_id IN (" . implode(',', $data['margin_order_list']) . ") AND oop.product_id IN ("
                    . implode(',', $data['bx_product_id']) . ")) ";
            }
            $sql .= ") ";
        }

        if (isset($data['filter_order']) && !is_null($data['filter_order'])) {
            $sql .= " AND oo.order_id like '%" . (int)$data['filter_order'] . "%'";
        }

        if (!empty($data['filter_nickname'])) {
            $sql .= " AND concat(oc.nickname,'(',oc.user_number,')') LIKE '%" . $this->db->escape($data['filter_nickname']) . "%'";
        }

        if (!empty($data['filter_sku_mpn'])) {
            $sql .= " AND ((p1.sku LIKE '%" . $this->db->escape($data['filter_sku_mpn']) . "%' or p1.mpn like '%" . $this->db->escape($data['filter_sku_mpn']) . "%') ";
            if(!empty($data['margin_sku_mpn'])){
                $sql .= " OR p1.sku IN ('" . implode('\',\'', $data['margin_sku_mpn']) . "') OR p1.mpn IN ('" . implode('\',\'', $data['margin_sku_mpn']) . "')";
            }
            if(!empty($data['future_margin_sku_mpn'])){
                $sql .= " OR p1.sku IN ('" . implode('\',\'', $data['future_margin_sku_mpn']) . "') OR p1.mpn IN ('" . implode('\',\'', $data['future_margin_sku_mpn']) . "')";
            }
            $sql .= ") ";
        }

        if (!empty($data['filter_date_from'])) {
            $sql .= " AND oo.date_added >= '" . $this->db->escape($data['filter_date_from']) . " 00:00:00'";
        }

        if (!empty($data['filter_date_to'])) {
            $sql .= " AND oo.date_added <= '" . $this->db->escape($data['filter_date_to']) . " 23:59:59'";
        }

        // 当filter_include_all_refund为空时 则不包含所有退货订单
        if (empty($data['filter_include_all_refund'])) {
            $refundOrderIds = $this->getAllRefundOrderId($this->customer->getId());
            if (!empty($data['margin_order_list'])) {    //2020年4月1日   N-1322  去掉保证金店铺的保证金rma订单
                $margin_order_ids=$this->getAllRefundMarginOrderId($data['margin_order_list']);
                $refundOrderIds=array_unique(array_merge($refundOrderIds,$margin_order_ids));
            }
            if (count($refundOrderIds) > 0) {
                $sql .= ' and oo.order_id not in (' . join(',', $refundOrderIds) . ') ';
            }
        }
        if (!empty($data['filter_order_status'])) {
            $sql .= ' AND oo.order_status_id = ' . $data['filter_order_status'].' ';
        }

        $sort_data = array(
            'oo.order_id',
            'oo.firstname',
            'oos.name',
            'oo.date_added'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY oo.order_id";
        }

        if (isset($data['order']) && ($data['order'] == ' ASC')) {
            $sql .= " ASC";
        } else {
            $sql .= " DESC";
        }

        $sql .= ") a left join oc_product_quote pq on (a.orderId=pq.order_id and a.product_id=pq.product_id)";
        return $this->db->cursor($sql);
    }

    public function getOrderQuote($orderId)
    {
        $sql = "select sum(amount) as quote from oc_product_quote where order_id = " . $orderId;

        return $this->db->query($sql)->row['quote'];
    }

    /**
     * [getSellerOrders to get orders according to sellers]
     * @param  [array]  $data [filter keywords]
     * @return [array]       [order details]
     */
    public function getSellerOrders($data = array())
    {

        $sql = "SELECT DISTINCT o.order_id,o.delivery_type ,o.date_added,c2o.currency_code,c2o.currency_value,
cus.customer_id as buyer_id,cus.email as buyer_email, CONCAT(cus.nickname ,'(', cus.user_number, ')' ) name ,os.`name` orderstatus,cus.customer_group_id
                FROM oc_customerpartner_to_order as c2o
                JOIN oc_order AS o ON o.order_id = c2o.order_id
                JOIN oc_order_status AS os ON os.order_status_id = o.order_status_id
                JOIN oc_customer cus ON cus.customer_id = o.customer_id  ";

        if (!empty($data['filter_sku_mpn'])) {
            $sql .= "LEFT JOIN oc_order_product as op on op.order_id = o.order_id LEFT JOIN oc_product as p on p.product_id = op.product_id AND p.`product_id` = c2o.`product_id`";
        }

        $sql .= "WHERE ((c2o.customer_id = " . (int)$this->customer->getId() ."  AND os.language_id = " . (int)$this->config->get('config_language_id') . " AND c2o.seller_access = '1')";

        if (!empty($data['margin_order_list'])) {
            $sql .= " OR o.`order_id` IN (" . implode(',', $data['margin_order_list']) . ")";
        }
        $sql .= ")";

        if (isset($data['filter_order']) && !is_null($data['filter_order'])) {
            $sql .= " AND o.order_id like '%" . $data['filter_order'] . "%'";
        }

        if (!empty($data['filter_nickname'])) {
            $sql .= " AND ( concat(cus.nickname,'(',cus.user_number,')') LIKE '%" . $this->db->escape($data['filter_nickname']) . "%')";
        }

        if (!empty($data['filter_sku_mpn'])) {
            $sql .= " AND ((p.sku LIKE '%" . $this->db->escape($data['filter_sku_mpn']) . "%' or p.mpn like '%" . $this->db->escape($data['filter_sku_mpn']) . "%') ";
            if(!empty($data['margin_sku_mpn'])){
                $sql .= " OR p.sku IN ('" . implode('\',\'', $data['margin_sku_mpn']) . "') OR p.mpn IN ('" . implode('\',\'', $data['margin_sku_mpn']) . "')";
            }
            if(!empty($data['future_margin_sku_mpn'])){
                $sql .= " OR p.sku IN ('" . implode('\',\'', $data['future_margin_sku_mpn']) . "') OR p.mpn IN ('" . implode('\',\'', $data['future_margin_sku_mpn']) . "')";
            }
            $sql .= ") ";
        }
        if (!empty($data['filter_date_from'])) {
            $sql .= " AND o.date_added >= '" . $this->db->escape($data['filter_date_from']) . " 00:00:00'";
        }
        if (!empty($data['filter_date_to'])) {
            $sql .= " AND o.date_added <= '" . $this->db->escape($data['filter_date_to']) . " 23:59:59'";
        }
        // 当filter_include_all_refund为空时 则不包含所有退货订单
        if (empty($data['filter_include_all_refund'])) {
            $refundOrderIds = $this->getAllRefundOrderId($this->customer->getId());
            if (!empty($data['margin_order_list'])) {    //2020年4月1日   N-1322  去掉保证金店铺的保证金rma订单
                $margin_order_ids=$this->getAllRefundMarginOrderId($data['margin_order_list']);
                $refundOrderIds=array_unique(array_merge($refundOrderIds,$margin_order_ids));
            }
            if (count($refundOrderIds) > 0) {
                $sql .= ' and o.order_id not in (' . join(',', $refundOrderIds) . ') ';
            }
        }
        if (!empty($data['filter_order_status'])) {
            $sql .= ' AND o.order_status_id = ' . $data['filter_order_status'].' ';
        }

        $sort_data = array(
            'o.orderstatus',
            'o.date_added',
            'cus.nickname',
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY o.date_added";
        }

        if (isset($data['order']) && ($data['order'] == 'ASC')) {
            $sql .= " ASC";
        } else {
            $sql .= " DESC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }
        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * @param array $data
     * @return Builder
     * user：wangjinxin
     * date：2020/7/30 19:34
     */
    public function buildSellerOrderQuery(array $data = [])
    {
        $query = $this->orm->table('oc_customerpartner_to_order as c2o')
            ->distinct()
            ->selectRaw("o.order_id,o.delivery_type ,o.date_added,c2o.currency_code,c2o.currency_value,
                    cus.nickname,
                    cus.customer_id as buyer_id,cus.email as buyer_email,
                    CONCAT(cus.nickname ,'(', cus.user_number, ')') name,
                    os.`name` orderstatus,cus.customer_group_id")
            ->join('oc_order as o', 'o.order_id', '=', 'c2o.order_id')
            ->join('oc_order_status as os', 'os.order_status_id', '=', 'o.order_status_id')
            ->join('oc_customer  as cus', 'cus.customer_id', '=', 'o.customer_id');
        $query->when(isset($data['filter_sku_mpn']) && !empty($data['filter_sku_mpn']), function (Builder $q) {
            $q->leftJoin('oc_order_product as op', 'op.order_id', '=', 'o.order_id');
            $q->leftJoin('oc_product as p', function (JoinClause $j) {
                $j->on('p.product_id', '=', 'op.product_id');
                $j->on('p.product_id', '=', 'c2o.product_id');
            });
        });
        $query->whereRaw('1=1');
        $query->when(isset($data['filter_order']) && !is_null($data['filter_order']), function (Builder $q) use ($data) {
            $q->where('o.order_id', 'like', "%" . $data['filter_order'] . "%");
        });
        $query->when(isset($data['filter_nickname']) && !empty($data['filter_nickname']), function (Builder $q) use ($data) {
            $q->where(new Expression("concat(cus.nickname,'(',cus.user_number,')')"), 'like', "%{$data['filter_nickname']}%");
        });
        $query->when(isset($data['filter_sku_mpn']) && !empty($data['filter_sku_mpn']), function (Builder $q) use ($data) {
            $q->where(function (Builder $q) use ($data) {
                $q->where('p.sku', 'like', "%{$data['filter_sku_mpn']}%");
                $q->orWhere('p.mpn', 'like', "%{$data['filter_sku_mpn']}%");
                if (isset($data['margin_sku_mpn']) && !empty($data['margin_sku_mpn'])) {
                    $q->orWhereIn('p.sku', $data['margin_sku_mpn']);
                    $q->orWhereIn('p.mpn', $data['margin_sku_mpn']);
                }
                if (isset($data['future_margin_sku_mpn']) && !empty($data['future_margin_sku_mpn'])) {
                    $q->orWhereIn('p.sku', $data['future_margin_sku_mpn']);
                    $q->orWhereIn('p.mpn', $data['future_margin_sku_mpn']);
                }
            });
        });
        $query->when(isset($data['filter_date_from']) && !empty($data['filter_date_from']), function (Builder $q) use ($data) {
            $q->where('o.date_added', '>=', $data['filter_date_from'] . ' 00:00:00');
        });
        $query->when(isset($data['filter_date_to']) && !empty($data['filter_date_to']), function (Builder $q) use ($data) {
            $q->where('o.date_added', '<=', $data['filter_date_to'] . ' 23:59:59');
        });
        $query->when(isset($data['filter_order_status']) && !empty($data['filter_order_status']), function (Builder $q) use ($data) {
            $q->where('o.order_status_id', '=', $data['filter_order_status']);
        });
        // 当filter_include_all_refund为空时 则不包含所有退货订单
        if (empty($data['filter_include_all_refund'])) {
            $refundOrderIds = $this->getAllRefundOrderId($this->customer->getId());
            //2020年4月1日   N-1322  去掉保证金店铺的保证金rma订单
            if (!empty($data['margin_order_list'])) {
                $margin_order_ids = $this->getAllRefundMarginOrderId($data['margin_order_list']);
                $refundOrderIds = array_unique(array_merge($refundOrderIds, $margin_order_ids));
            }
            if (count($refundOrderIds) > 0) {
                $query->whereNotIn('o.order_id', $refundOrderIds);
            }
        }
        $query->where([
            'c2o.customer_id' => (int)$this->customer->getId(),
            'os.language_id' => (int)$this->config->get('config_language_id'),
            'c2o.seller_access' => 1,
        ]);
        if (isset($data['margin_order_list']) && !empty($data['margin_order_list'])) {
            $query2 = (clone $query);
            $query2->whereIn('o.order_id', $data['margin_order_list']);
        }
        $f_sql = null;
        if (isset($query2)) {
            $final_query = $query->union($query2);
            $f_sql = $this->orm->table(new Expression("({$final_query->toSql()}) as a"))
                ->mergeBindings($final_query);
        } else {
            $f_sql = $this->orm->table(new Expression("({$query->toSql()}) as a"))
                ->mergeBindings($query);
        }
        return $f_sql;
    }

    /**
     * 重写上面的的代码
     * @param array $data
     * @return array
     */
    public function getSellerOrdersByOrm(array $data = [])
    {
        $f_sql = $this->buildSellerOrderQuery($data);
        $sort_data = [
            'o.orderstatus' => 'orderstatus',
            'o.date_added' => 'date_added',
            'cus.nickname' => 'nickname',
        ];
        if (isset($data['sort']) && array_key_exists($data['sort'], $sort_data)) {
            $sort = $sort_data[$data['sort']];
        } else {
            $sort = "date_added";
        }
        if (isset($data['order']) && ($data['order'] == 'ASC')) {
            $order = "ASC";
        } else {
            $order = "DESC";
        }
        $f_sql->orderBy($sort, $order);
        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }
            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }
            $f_sql->offset($data['start'])->limit($data['limit']);
        }
        return $f_sql->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
    }

    /**
     * @param array $data
     * @return int
     */
    public function getSellerOrdersTotalByOrm(array $data = [])
    {
      return $this->buildSellerOrderQuery($data)->count('a.order_id');
    }

    /**
     * [getSellerOrdersTotal total count of orders of particular seller]
     * @param  [array]  $data [filter keywords]
     * @return [integer]       [total number of orders]
     */
    public function getSellerOrdersTotal($data = array())
    {
        $sql = "SELECT count(DISTINCT o.order_id ,o.date_added,c2o.currency_code,c2o.currency_value ,os.order_status_id ) as total
                FROM oc_customerpartner_to_order as c2o
                JOIN oc_order AS o ON o.order_id = c2o.order_id
                JOIN oc_order_status AS os ON os.order_status_id = o.order_status_id
                JOIN oc_customer cus ON cus.customer_id = o.customer_id ";

        if (!empty($data['filter_sku_mpn'])) {
            $sql .= "LEFT JOIN oc_order_product as op on op.order_id = o.order_id LEFT JOIN oc_product as p on p.product_id = op.product_id AND p.`product_id` = c2o.`product_id`";
        }

        $sql .= "WHERE ((c2o.customer_id = " . (int)$this->customer->getId() ."  AND os.language_id = " . (int)$this->config->get('config_language_id') . " AND c2o.seller_access = '1')";

        if (!empty($data['margin_order_list'])) {
            $sql .= " OR o.`order_id` IN (" . implode(',', $data['margin_order_list']) . ")";
        }
        $sql .= ")";

        if (isset($data['filter_order']) && !is_null($data['filter_order'])) {
            $sql .= " AND o.order_id like '%" . $data['filter_order'] . "%'";
        }

        if (!empty($data['filter_nickname'])) {
            $sql .= " AND ( concat(cus.nickname,'(',cus.user_number,')') LIKE '%" . $this->db->escape($data['filter_nickname']) . "%')";
        }

        if (!empty($data['filter_sku_mpn'])) {
            $sql .= " AND ((p.sku LIKE '%" . $this->db->escape($data['filter_sku_mpn']) . "%' or p.mpn like '%" . $this->db->escape($data['filter_sku_mpn']) . "%') ";
            if(!empty($data['margin_sku_mpn'])){
                $sql .= " OR p.sku IN ('" . implode('\',\'', $data['margin_sku_mpn']) . "') OR p.mpn IN ('" . implode('\',\'', $data['margin_sku_mpn']) . "')";
            }
            if(!empty($data['future_margin_sku_mpn'])){
                $sql .= " OR p.sku IN ('" . implode('\',\'', $data['future_margin_sku_mpn']) . "') OR p.mpn IN ('" . implode('\',\'', $data['future_margin_sku_mpn']) . "')";
            }
            $sql .= ") ";
        }
        if (!empty($data['filter_date_from'])) {
            $sql .= " AND o.date_added >= '" . $this->db->escape($data['filter_date_from']) . " 00:00:00'";
        }
        if (!empty($data['filter_date_to'])) {
            $sql .= " AND o.date_added <= '" . $this->db->escape($data['filter_date_to']) . " 23:59:59'";
        }
        // 当filter_include_all_refund为空时 则不包含所有退货订单
        if (empty($data['filter_include_all_refund'])) {
            $refundOrderIds = $this->getAllRefundOrderId($this->customer->getId());
            if (!empty($data['margin_order_list'])) {    //2020年4月1日   N-1322  去掉保证金店铺的保证金rma订单
                $margin_order_ids=$this->getAllRefundMarginOrderId($data['margin_order_list']);
                $refundOrderIds=array_unique(array_merge($refundOrderIds,$margin_order_ids));
            }
            if (count($refundOrderIds) > 0) {
                $sql .= ' and o.order_id not in (' . join(',', $refundOrderIds) . ') ';
            }
        }
        if (!empty($data['filter_order_status'])) {
            $sql .= ' AND o.order_status_id = ' . $data['filter_order_status'].' ';
        }

        return $this->db->query($sql)->row['total'];
    }

    /**
     * 获取全部退货的订单id
     * @param int $customer_id
     * @param bool $returnArray
     * @return array|string
     */
    public function getAllRefundOrderId(int $customer_id, bool $returnArray = true)
    {
        static $retData = [];
        $key = $customer_id . ($returnArray ? '1' : '0');
        if (isset($retData[$key])) return $retData[$key];
        $distinctRmaOrder = $this->orm
            ->table('oc_yzc_rma_order as ro')
            ->distinct()
            ->select('ro.order_id')
            ->where('ro.seller_id', $customer_id);
        $soaQuery = $this->orm
            ->table('tb_sys_order_associated AS soa')
            ->select(['soa.sales_order_id', 'soa.order_id', 'soa.product_id'])
            ->addSelect(new Expression('sum(soa.qty) AS qty'))
            ->where('soa.seller_id', $customer_id)
            ->whereIn('soa.order_id', $distinctRmaOrder)
            ->groupBy(['soa.sales_order_id', 'soa.order_id', 'soa.product_id']);
        // 采购单RMA
        $mainQuery1 = $this->orm
            ->table('oc_yzc_rma_order as ro')
            ->select(['ro.order_id', 'ro.id AS rma_id'])
            ->addSelect(new Expression('sum(rop.quantity) AS qty'))
            ->leftJoin('oc_yzc_rma_order_product AS rop', ['ro.id' => 'rop.rma_id'])
            ->groupBy('ro.order_id')
            ->where([
                'ro.seller_id' => $customer_id,
                'ro.seller_status' => 2,
                'ro.cancel_rma' => 0,
                'rop.status_refund' => 1,
                'ro.order_type' => RmaType::PURCHASE_ORDER,
            ]);
        // 销售单RMA
        $mainQuery2Main = db('oc_yzc_rma_order as ro')
            ->select(['cso.id as sales_order_id', 'ro.order_id', 'rop.product_id', 'rop.rma_id'])
            ->leftJoin('oc_yzc_rma_order_product AS rop', ['ro.id' => 'rop.rma_id'])
            ->leftJoin('tb_sys_customer_sales_order as cso', ['cso.order_id' => 'ro.from_customer_order_id'])
            ->where([
                'ro.seller_id' => $customer_id,
                'ro.seller_status' => 2,
                'ro.cancel_rma' => 0,
                'rop.status_refund' => 1,
                'cso.order_status' => CustomerSalesOrderStatus::CANCELED,
                'ro.order_type' => RmaType::SALES_ORDER,
            ])
            ->groupBy(['cso.id', 'ro.order_id', 'rop.product_id']);
        $mainQuery2 = db(new Expression('(' . get_complete_sql($mainQuery2Main) . ') as mq'))
            ->select(['mq.order_id', 'mq.rma_id'])
            ->addSelect(new Expression('sum(oso.qty) AS qty'))
            ->leftJoin(
                new Expression('(' . get_complete_sql($soaQuery) . ') as oso'),
                function (JoinClause $j) {
                    $j->on('mq.order_id', '=', 'oso.order_id');
                    $j->on('mq.sales_order_id', '=', 'oso.sales_order_id');
                    $j->on('mq.product_id', '=', 'oso.product_id');
                }
            )
            ->groupBy(['mq.order_id']);
        $mainQuery = $mainQuery1->union($mainQuery2);
        $mainQuery = $this->orm
            ->table(new Expression('(' . get_complete_sql($mainQuery) . ') as main_o'))
            ->select(['main_o.order_id'])
            ->addSelect(new Expression('sum( main_o.qty ) AS qty'))
            ->groupBy(['main_o.order_id']);
        $subQuery = $this->orm
            ->table('oc_customerpartner_to_order AS cto')
            ->select('cto.order_id')
            ->addSelect(new Expression('sum( cto.quantity ) AS qty'))
            ->where(['cto.customer_id' => $customer_id])
            ->whereIn('cto.order_id', $distinctRmaOrder)
            ->groupBy('cto.order_id');
        $query = $this->orm
            ->table(new Expression('(' . get_complete_sql($mainQuery) . ') as rma_o'))
            ->select('rma_o.order_id')
            ->leftJoin(
                new Expression('(' . get_complete_sql($subQuery) . ') as or_o'),
                ['rma_o.order_id' => 'or_o.order_id']
            )
            ->whereRaw('rma_o.qty = or_o.qty');
        if ($returnArray) {
            $ret = $query->get()->pluck('order_id')->toArray();
        } else {
            $ret = get_complete_sql($query);
        }
        $retData[$key] = $ret;

        return $ret;
    }

    //获取全部退货的保证金订单（旧保证金--在保证金店铺的退货）   copy from function getAllRefundOrderId
    public function getAllRefundMarginOrderId($margin_order_list,bool $returnArray = true){
        static $retData = [];
        $arr_md5 = md5(json_encode($margin_order_list));
        $key = $arr_md5 . ($returnArray ? '1' : '0');
        if (isset($retData[$key])) return $retData[$key];
        $distinctRmaOrder = $this->orm
            ->table('oc_yzc_rma_order as ro')
            ->distinct()
            ->select('ro.order_id')
            ->whereIn('ro.order_id', $margin_order_list);
        $soaQuery = $this->orm
            ->table('tb_sys_order_associated AS soa')
            ->select(['soa.sales_order_id','soa.order_id','soa.product_id'])
            ->addSelect(new Expression('sum(soa.qty) AS qty'))
            ->whereIn('soa.order_id', $distinctRmaOrder)
            ->groupBy(['soa.sales_order_id', 'soa.order_id', 'soa.product_id']);
        // 采购单RMA
        $mainQuery1 = $this->orm
            ->table('oc_yzc_rma_order as ro')
            ->select(['ro.order_id', 'ro.id AS rma_id'])
            ->addSelect(new Expression('sum(rop.quantity) AS qty'))
            ->leftJoin('oc_yzc_rma_order_product AS rop', ['ro.id' => 'rop.rma_id'])
            ->groupBy('ro.order_id')
            ->where([
                'ro.seller_status' => 2,
                'ro.cancel_rma' => 0,
                'rop.status_refund' => 1,
                'ro.order_type' => RmaType::PURCHASE_ORDER,
            ]);
        // 销售单RMA
        $mainQuery2Main = db('oc_yzc_rma_order as ro')
            ->select(['cso.id as sales_order_id', 'ro.order_id', 'rop.product_id', 'rop.rma_id'])
            ->leftJoin('oc_yzc_rma_order_product AS rop', ['ro.id' => 'rop.rma_id'])
            ->leftJoin('tb_sys_customer_sales_order as cso', ['cso.order_id' => 'ro.from_customer_order_id'])
            ->where([
                'ro.seller_status' => 2,
                'ro.cancel_rma' => 0,
                'rop.status_refund' => 1,
                'cso.order_status' => CustomerSalesOrderStatus::CANCELED,
                'ro.order_type' => RmaType::SALES_ORDER,
            ])
            ->groupBy(['cso.id', 'ro.order_id', 'rop.product_id']);
        $mainQuery2 = db(new Expression('(' . get_complete_sql($mainQuery2Main) . ') as mq'))
            ->select(['mq.order_id', 'mq.rma_id'])
            ->addSelect(new Expression('sum(oso.qty) AS qty'))
            ->leftJoin(
                new Expression('(' . get_complete_sql($soaQuery) . ') as oso'),
                function (JoinClause $j) {
                    $j->on('mq.order_id', '=', 'oso.order_id');
                    $j->on('mq.sales_order_id', '=', 'oso.sales_order_id');
                    $j->on('mq.product_id', '=', 'oso.product_id');
                }
            )
            ->groupBy(['mq.order_id']);
        $mainQuery = $mainQuery1->union($mainQuery2);
        $mainQuery = $this->orm
            ->table(new Expression('(' . get_complete_sql($mainQuery) . ') as main_o'))
            ->select(['main_o.order_id'])
            ->addSelect(new Expression('sum( main_o.qty ) AS qty'))
            ->groupBy(['main_o.order_id']);
        $subQuery = $this->orm
            ->table('oc_customerpartner_to_order AS cto')
            ->select('cto.order_id')
            ->addSelect(new Expression('sum( cto.quantity ) AS qty'))
            ->whereIn('cto.order_id', $distinctRmaOrder)
            ->groupBy('cto.order_id');
        $query = $this->orm
            ->table(new Expression('(' . get_complete_sql($mainQuery) . ') as rma_o'))
            ->select('rma_o.order_id')
            ->leftJoin(
                new Expression('(' . get_complete_sql($subQuery) . ') as or_o'),
                ['rma_o.order_id' => 'or_o.order_id']
            )
            ->whereRaw('rma_o.qty = or_o.qty');
        if ($returnArray) {
            $ret = $query->get()->pluck('order_id')->toArray();
        } else {
            $ret = get_complete_sql($query);
        }
        $retData[$key] = $ret;

        return $ret;
    }

    /**
     * [getSellerOrderProducts to get products by order]
     * @param  [integer] $order_id [order id of particular order]
     * @return [array]           [details of products]
     */
    public function getSellerOrderProducts($order_id, bool $returnArray = true)
    {


    }

    /**
     * 获取客户充值总金额
     * type_id:1-信用额度充值；3-退返品充值
     */
    public function getTotalAmountOfRecharge($filter_data)
    {
        $queryModel = LineOfCreditRecord::query()
            ->whereIn('type_id', ChargeType::getRevenueTypes())
            ->when(!empty($filter_data['customerId']), function ($query) use ($filter_data) {
                $query->where('customer_id', $filter_data['customerId']);
            });
        $queryModel = $this->filterLineOfCreditRecordByDate($queryModel, $filter_data);
        return $queryModel->value(new Expression("sum(new_line_of_credit-old_line_of_credit)"));
    }

    /**
     * 获取客户支出总金额
     */
    public function getTotalConsumptionAmount($filter_data)
    {
        $queryModel = LineOfCreditRecord::query()
            ->whereIn('type_id', ChargeType::getPaymentTypes())
            ->when(!empty($filter_data['customerId']), function ($query) use ($filter_data) {
                $query->where('customer_id', $filter_data['customerId']);
            });
        $queryModel = $this->filterLineOfCreditRecordByDate($queryModel, $filter_data);
        return $queryModel->value(new Expression("sum(old_line_of_credit-new_line_of_credit)"));
    }

    /**
     * 增加信用额度流水时间过滤条件
     *
     * @param \Framework\Model\Eloquent\Builder $queryModel
     * @param $filterData
     * @return \Framework\Model\Eloquent\Builder
     */
    private function filterLineOfCreditRecordByDate(\Framework\Model\Eloquent\Builder $queryModel, $filterData)
    {
        if (isset($filterData['timeSpace'])) {
            if ($filterData['timeSpace'] == 1) {//一周
                $queryModel->where('date_added', '>', Carbon::now()->subDay(7));
            } else if ($filterData['timeSpace'] == 2) {//一个月
                $queryModel->where('date_added', '>', Carbon::now()->subMonth());
            } else if ($filterData['timeSpace'] == 3) {//一年
                $queryModel->where('date_added', '>', Carbon::now()->subYear());
            } else if ($filterData['timeSpace'] == 4) {
                if (isset($filterData['timeFrom'])) {
                    $queryModel->where('date_added', '>=', $filterData['timeFrom'] . ' 00:00:00');
                }
                if (isset($filterData['timeTo'])) {
                    $queryModel->where('date_added', '<=', $filterData['timeTo'] . ' 23:59:59');
                }
            }
        }
        return $queryModel;
    }

    /**
     * 获取客户充值消费明细记录总数
     */
    public function getAccountRecordCounts($filter_data)
    {
        $sql = $this->db->query("SELECT count(*) as totalnum FROM tb_sys_credit_line_amendment_record WHERE customer_id=" . $filter_data['customerId']);
        return ($sql->row['totalnum']);
    }

    /**
     * 获取客户充值消费明细记录明细信息
     */
    public function getAccountRecordRow($filter_data)
    {

    }

    /**
     * [getSellerOrderProducts to get products by order]
     * @param int $order_id
     * @param int|null $order_seller_id
     * @param array|null $product_ids
     * @return array [array]           [details of products]
     */
    public function getSellerOrderProductInfo($order_id,$order_seller_id=null,$product_ids = null)
    {
        if(!isset($order_seller_id)){
            $order_seller_id = $this->customer->getId();
        }
        $sql = "SELECT op.*,round(op.price,2)*op.quantity c2oprice,round(op.price,2) opprice, c2o.shipping_applied,c2o.paid_status,c2o.order_product_status,p.sku,p.mpn,o.customer_id buyer_id,o.delivery_type,c2o.customer_id seller_id
        FROM " . DB_PREFIX . "customerpartner_to_order c2o
        LEFT JOIN " . DB_PREFIX . "order_product op ON (c2o.order_product_id = op.order_product_id AND c2o.order_id = op.order_id)
        left join oc_product as p on p.product_id = op.product_id
        left join oc_order o on o.order_id = op.order_id
        WHERE c2o.order_id = '" . (int)$order_id . "'  AND c2o.customer_id = '" . (int)$order_seller_id . "' ";
        if(isset($product_ids) && !empty($product_ids)){
            $sql .= " AND op.product_id IN (" . implode(',', $product_ids) . ") ";
        }
        $sql .= " ORDER BY op.product_id ";
        return $this->db->query($sql)->rows;
    }


    /**
     * [getSellerOrderProducts to get products by order]
     * @param int $order_id
     * @param int|null $order_seller_id
     * @param array|null $product_ids
     * @return array [array]           [details of products]
     */
    public function getSellerOrderProductInfo_head($order_id,$order_seller_id=null,$product_ids = null)
    {
        if(!isset($order_seller_id)){
            $order_seller_id = $this->customer->getId();
        }
        $sql = "SELECT op.*,round(op.price,2)*op.quantity c2oprice,round(op.price,2) opprice, c2o.shipping_applied,c2o.paid_status,c2o.order_product_status,p.sku,p.mpn,p.image,o.customer_id buyer_id,c2o.customer_id seller_id
        FROM " . DB_PREFIX . "customerpartner_to_order c2o
        LEFT JOIN " . DB_PREFIX . "order_product op ON (c2o.order_product_id = op.order_product_id AND c2o.order_id = op.order_id)
        left join oc_product as p on p.product_id = op.product_id
        left join oc_order o on o.order_id = op.order_id
        join tb_sys_margin_process as smp on smp.advance_order_id=	o.order_id and smp.advance_product_id=op.product_id
        WHERE c2o.order_id = '" . (int)$order_id . "'  AND c2o.customer_id = '" . (int)$order_seller_id . "' ";
        if(isset($product_ids) && !empty($product_ids)){
            $sql .= " AND op.product_id IN (" . implode(',', $product_ids) . ") ";
        }
        $sql .= " ORDER BY op.product_id ";
        return $this->db->query($sql)->rows;
    }

    /**
     * [getOrder to get details of a particular order]
     * @param int $order_id oc_order表的order_id
     * @param null $order_seller_id
     * @return array|bool [array|boolean]           [details of particular order|false]
     */
    public function getOrder($order_id,$order_seller_id=null)
    {
        // 去掉order_seller_id（再次加上 处理越权查看订单详情）
        //AND c2o.customer_id = " . (int)$order_seller_id
        $sql = "SELECT o.*,c2o.*,o.date_added,c.nickname,c.user_number,c.customer_group_id,os.name as order_status_name,o.customer_id as buyer_id FROM `" . DB_PREFIX . "order` o
        LEFT JOIN " . DB_PREFIX . "customerpartner_to_order c2o ON (o.order_id = c2o.order_id)
        LEFT JOIN oc_customer as c on c.customer_id = o.customer_id
        LEFT JOIN oc_order_status as os on os.order_status_id = o.order_status_id
        WHERE o.order_id = '" . (int)$order_id . "' AND o.order_status_id > '0' AND c2o.customer_id = " . (int)$this->customer->getId();
        $order_query = $this->db->query($sql);
        if ($order_query->num_rows) {
            $country_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE country_id = '" . (int)$order_query->row['payment_country_id'] . "'");

            if ($country_query->num_rows) {
                $payment_iso_code_2 = $country_query->row['iso_code_2'];
                $payment_iso_code_3 = $country_query->row['iso_code_3'];
            } else {
                $payment_iso_code_2 = '';
                $payment_iso_code_3 = '';
            }

            $zone_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE zone_id = '" . (int)$order_query->row['payment_zone_id'] . "'");

            if ($zone_query->num_rows) {
                $payment_zone_code = $zone_query->row['code'];
            } else {
                $payment_zone_code = '';
            }

            $country_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE country_id = '" . (int)$order_query->row['shipping_country_id'] . "'");

            if ($country_query->num_rows) {
                $shipping_iso_code_2 = $country_query->row['iso_code_2'];
                $shipping_iso_code_3 = $country_query->row['iso_code_3'];
            } else {
                $shipping_iso_code_2 = '';
                $shipping_iso_code_3 = '';
            }

            $zone_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE zone_id = '" . (int)$order_query->row['shipping_zone_id'] . "'");

            if ($zone_query->num_rows) {
                $shipping_zone_code = $zone_query->row['code'];
            } else {
                $shipping_zone_code = '';
            }

            return array(
                'order_id' => $order_query->row['order_id'],
                'invoice_no' => $order_query->row['invoice_no'],
                'invoice_prefix' => $order_query->row['invoice_prefix'],
                'store_id' => $order_query->row['store_id'],
                'store_name' => $order_query->row['store_name'],

                'store_url' => $order_query->row['store_url'],
                'customer_id' => $order_query->row['customer_id'],
                'buyer_id'=>$order_query->row['buyer_id'], //上面数据是seller 的id   ？？？？

                'shipping_applied' => $order_query->row['shipping_applied'],

                'firstname' => $order_query->row['firstname'],
                'lastname' => $order_query->row['lastname'],
                'nickname' => $order_query->row['nickname'] . "(" . $order_query->row['user_number'] . ")",
                'customer_group_id' => $order_query->row['customer_group_id'],
                'telephone' => $order_query->row['telephone'],
                'fax' => $order_query->row['fax'],
                'email' => $order_query->row['email'],
                'payment_firstname' => $order_query->row['payment_firstname'],
                'payment_lastname' => $order_query->row['payment_lastname'],
                'payment_company' => $order_query->row['payment_company'],
                'payment_address_1' => $order_query->row['payment_address_1'],
                'payment_address_2' => $order_query->row['payment_address_2'],
                'payment_postcode' => $order_query->row['payment_postcode'],
                'payment_city' => $order_query->row['payment_city'],
                'payment_zone_id' => $order_query->row['payment_zone_id'],
                'payment_zone' => $order_query->row['payment_zone'],
                'payment_zone_code' => $payment_zone_code,
                'payment_country_id' => $order_query->row['payment_country_id'],
                'payment_country' => $order_query->row['payment_country'],
                'payment_iso_code_2' => $payment_iso_code_2,
                'payment_iso_code_3' => $payment_iso_code_3,
                'payment_address_format' => $order_query->row['payment_address_format'],
                'payment_method' => $order_query->row['payment_method'],
                'shipping_firstname' => $order_query->row['shipping_firstname'],
                'shipping_lastname' => $order_query->row['shipping_lastname'],
                'shipping_company' => $order_query->row['shipping_company'],
                'shipping_address_1' => $order_query->row['shipping_address_1'],
                'shipping_address_2' => $order_query->row['shipping_address_2'],
                'shipping_postcode' => $order_query->row['shipping_postcode'],
                'shipping_city' => $order_query->row['shipping_city'],
                'shipping_zone_id' => $order_query->row['shipping_zone_id'],
                'shipping_zone' => $order_query->row['shipping_zone'],
                'shipping_zone_code' => $shipping_zone_code,
                'shipping_country_id' => $order_query->row['shipping_country_id'],
                'shipping_country' => $order_query->row['shipping_country'],
                'shipping_iso_code_2' => $shipping_iso_code_2,
                'shipping_iso_code_3' => $shipping_iso_code_3,
                'shipping_address_format' => $order_query->row['shipping_address_format'],
                'shipping_method' => $order_query->row['shipping_method'],
                'shipping_code' => $order_query->row['shipping_code'],
                'comment' => $order_query->row['comment'],
                'total' => $order_query->row['total'],
                'order_status_id' => $order_query->row['order_status_id'],
                'order_status_name' => $order_query->row['order_status_name'],
                'language_id' => $order_query->row['language_id'],
                'currency_id' => $order_query->row['currency_id'],
                'currency_code' => $order_query->row['currency_code'],
                'currency_value' => $order_query->row['currency_value'],
                'date_modified' => $order_query->row['date_modified'],
                'date_added' => $order_query->row['date_added'],
                'ip' => $order_query->row['ip'],
                'delivery_type'=>$order_query->row['delivery_type'],
            );
        } else {
            return false;
        }
    }

    /**
     * [getOrderTotals to get particular order's total]
     * @param  [integer] $order_id [order id of particular order]
     * @return [integer]           [sum of order]
     */
    public function getOrderTotals($order_id)
    {
        $query = $this->db->query("SELECT SUM((customer + admin) * currency_value) total, SUM(shipping_applied) shipping_applied, shipping  FROM " . DB_PREFIX . "customerpartner_to_order WHERE order_id = '" . (int)$order_id . "' AND customer_id = '" . (int)$this->customer->getId() . "'");

        return $query->rows;
    }

    /**
     * [getOrderTotals to get particular order's total]
     * @param int $order_id oc_order表的order_id
     * @param int|null $order_seller_id
     * @param array|null $product_ids
     * @return array [integer]           [sum of order]
     */
    public function getOrderTotalPrice($order_id,$order_seller_id=null,$product_ids = null)
    {
        if(!isset($order_seller_id)){
            $order_seller_id = $this->customer->getId();
        }
        $sql = "SELECT SUM(round(op.price,2) * op.quantity) total,SUM(op.service_fee) as service_fee,SUM(op.poundage) as poundage, SUM(cto.shipping_applied) shipping_applied, cto.shipping
        FROM " . DB_PREFIX . "customerpartner_to_order cto LEFT JOIN " . DB_PREFIX . "order_product op ON (cto.order_id=op.order_id AND cto.product_id=op.product_id)
        join tb_sys_margin_process as smp on smp.advance_order_id= cto.order_id and smp.advance_product_id=op.product_id
        WHERE cto.order_id = '" . (int)$order_id . "' AND cto.customer_id = '" . (int)$order_seller_id . "'";
        if(isset($product_ids) && !empty($product_ids)){
            $sql .= " AND op.product_id IN (" . implode(',', $product_ids) . ") ";
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * [convertSkuToFutureMarginSku description]
     * @param int $customer_id
     * @param string $sku_mpn
     * @param null $advance_order_id
     * @return array
     */
    public function convertSkuToFutureMarginSku($customer_id, $sku_mpn, $advance_order_id = null){
        $ret =$this->orm->table(DB_PREFIX.'futures_margin_agreement as a')
            ->crossJoin(DB_PREFIX.'product as p','p.product_id','=','a.product_id')
            ->crossJoin(DB_PREFIX.'futures_margin_process as mp','mp.agreement_id','=','a.id')
            ->crossJoin(DB_PREFIX.'product as ap','ap.product_id','=','mp.advance_product_id')
            ->where([
                'a.seller_id' => $customer_id,
            ])
            ->where(function ($query) use ($sku_mpn){
                $query->where('p.sku', 'like', "%{$sku_mpn}")->orWhere('p.mpn', 'like', "%{$sku_mpn}");
            })
            ->when($advance_order_id, function (Builder $q) use ($advance_order_id) {
                return $q->where('mp.advance_order_id', $advance_order_id);
            })
            ->groupBy('ap.sku')
            ->pluck('ap.sku');
        $ret = obj2array($ret);
        return $ret;
    }

    /**
     * [getPurchaseOrderType description]
     * @param $type_id
     * @param int $agreement_id
     * @param int $product_id
     * @return string
     */
    public function getPurchaseOrderType($type_id, $agreement_id, $product_id){

        $info = OcOrderTypeId::getViewItems();
        switch ($type_id){
            case OcOrderTypeId::TYPE_REBATE:
                $agreementCode = db('oc_rebate_agreement')
                    ->where('id',$agreement_id)
                    ->value('agreement_code');
                $ret = $info[OcOrderTypeId::TYPE_REBATE] .'('.$agreementCode.')';
                break;
            case OcOrderTypeId::TYPE_MARGIN:
                $tmp = db('tb_sys_margin_process as mp')
                    ->join('tb_sys_margin_agreement as a','mp.margin_id','=','a.id')
                    ->where([
                        'mp.margin_id' => $agreement_id,
                    ])
                    ->select('mp.advance_product_id','a.agreement_id')
                    ->first();

                if($tmp->advance_product_id == $product_id){
                    $ret = $info[OcOrderTypeId::TYPE_MARGIN][0] .'('.$tmp->agreement_id.')';
                }else{
                    $ret = $info[OcOrderTypeId::TYPE_MARGIN][1] .'('.$tmp->agreement_id.')';
                }
                break;
            case OcOrderTypeId::TYPE_FUTURE:
                $tmp = db(DB_PREFIX.'futures_margin_process as mp')
                    ->join(DB_PREFIX.'futures_margin_agreement as a','mp.agreement_id','=','a.id')
                    ->where([
                        'mp.agreement_id' => $agreement_id,
                    ])
                    ->select('mp.advance_product_id','a.agreement_no')
                    ->first();
                if($tmp->advance_product_id == $product_id){
                    $ret = $info[OcOrderTypeId::TYPE_FUTURE][0] .'('.$tmp->agreement_no.')';
                }else{
                    $ret = $info[OcOrderTypeId::TYPE_FUTURE][1] .'('.$tmp->agreement_no.')';
                }
                break;
            default:
                $ret = $info[OcOrderTypeId::TYPE_PO];
                break;
        }

        return $ret;
    }

    public function getFutureRestProductInfo($advance_order_id){
        $ret =$this->orm->table(DB_PREFIX.'futures_margin_agreement as a')
            ->crossJoin(DB_PREFIX.'product as p','p.product_id','=','a.product_id')
            ->crossJoin(DB_PREFIX.'futures_margin_process as mp','mp.agreement_id','=','a.id')
            ->where([
                'mp.advance_order_id' => $advance_order_id,
            ])
            ->select('p.sku','p.mpn','p.product_id')
            ->get()
            ->map(function($v){
                return (array)$v;
            })
            ->toArray();
        return current($ret);

    }

    /**
     * [getOdrTracking to get tracking of an order]
     * @param  integer $order_id  [order_id]
     * @param  integer $product_id [product id of product]
     * @param  integer $customer_id [customer id of customer]
     * @return array       [tracking details of an order]
     */
    public function getOdrTracking($order_id, $product_id, $customer_id)
    {

        $sql = "SELECT tracking FROM " . DB_PREFIX . "customerpartner_sold_tracking where customer_id='" . (int)$customer_id . "' AND product_id='" . (int)$product_id . "' AND order_id='" . (int)$order_id . "'";

        return ($this->db->query($sql)->row);
    }

    /**
     * [addOdrTracking to add tracking number to an order]
     * @param [integer] $order_id [order id of order]
     * @param [string|number] $tracking [tracking number/string]
     */
    public function addOdrTracking($order_id, $tracking)
    {
        /**
         * have to add product_order_id condition here too
         */
        $comment = '';

        $count = 0;

        foreach ($tracking as $product_id => $tracking_no) {

            if ($tracking_no) {
                $sql = $this->db->query("SELECT c2t.* FROM " . DB_PREFIX . "customerpartner_sold_tracking c2t WHERE c2t.customer_id='" . (int)$this->customer->getId() . "' AND c2t.product_id='" . (int)$product_id . "' AND c2t.order_id='" . (int)$order_id . "'")->row;

                if (isset($sql) && !$sql) {

                    $count++;

                    $this->db->query("INSERT INTO " . DB_PREFIX . "customerpartner_sold_tracking SET customer_id='" . (int)$this->customer->getId() . "' ,tracking='" . $this->db->escape($tracking_no) . "' ,product_id='" . (int)$product_id . "' ,order_id='" . (int)$order_id . "'");

                    $sql = $this->db->query("SELECT name FROM " . DB_PREFIX . "order_product WHERE product_id='" . (int)$product_id . "' AND order_id='" . (int)$order_id . "'")->row;

                    if ($sql) {
                        $comment .= 'Product - ' . $sql['name'] . '<br/>' . 'Seller Tracking No - ' . $tracking_no . '<br/>';

                        $commentForproduct = 'Product - ' . $sql['name'] . '<br/>' . 'Seller Tracking No - ' . $tracking_no . '<br/>';

                        $productOrderStatus = $this->db->query("SELECT os.order_status_id FROM " . DB_PREFIX . "customerpartner_to_order c2o LEFT JOIN  " . DB_PREFIX . "order_status os ON (c2o.order_product_status = os.order_status_id) WHERE c2o.product_id='" . (int)$product_id . "' AND c2o.customer_id='" . (int)$this->customer->getId() . "'")->row;

                        $this->db->query("INSERT INTO " . DB_PREFIX . "customerpartner_to_order_status SET order_id = '" . (int)$order_id . "',product_id = '" . (int)$product_id . "',customer_id='" . (int)$this->customer->getId() . "',order_status_id = '" . (int)$productOrderStatus['order_status_id'] . "',comment = '" . $this->db->escape($commentForproduct) . "', date_added = NOW()");
                    }
                }
            }
        }


        if ($comment) {
            $sql = $this->db->query("SELECT o.order_status_id FROM `" . DB_PREFIX . "order` o WHERE o.order_id = '" . (int)$order_id . "'")->row;

            if ($sql)
                $this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order_id . "', order_status_id = '" . $sql['order_status_id'] . "', notify = '" . 1 . "', comment = '" . $this->db->escape($comment) . "', date_added = NOW()");


            $sql = $this->db->query("SELECT c2p.product_id FROM " . DB_PREFIX . "order_product o LEFT JOIN " . DB_PREFIX . "customerpartner_to_product c2p ON (o.product_id = c2p.product_id) LEFT JOIN " . DB_PREFIX . "customerpartner_sold_tracking cst ON (c2p.product_id = cst.product_id) where o.order_id='" . (int)$order_id . "' AND c2p.product_id NOT IN (SELECT product_id FROM " . DB_PREFIX . "customerpartner_sold_tracking cst WHERE cst.order_id = '" . (int)$order_id . "')")->rows;
        }

        return $count;
    }

    /**
     * [addDownload to add download option]
     * @param [array] $data [detail about the download]
     */
    public function addDownload($data)
    {

        $this->db->query("INSERT INTO " . DB_PREFIX . "download SET filename = '" . $this->db->escape($data['filename']) . "', mask = '" . $this->db->escape($data['mask']) . "', date_added = NOW()");

        $download_id = $this->db->getLastId();

        foreach ($data['download_description'] as $language_id => $value) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "download_description SET download_id = '" . (int)$download_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "'");
        }

        /**
         * for seller table
         */
        $this->db->query("INSERT INTO " . DB_PREFIX . "customerpartner_download SET download_id = '" . (int)$download_id . "', seller_id = '" . (int)$this->customer->getId() . "'");
    }

    /**
     * [editDownload to update download]
     * @param  [integer] $download_id [id of particular download]
     * @param  [array] $data        [detail about the download]
     */
    public function editDownload($download_id, $data)
    {

        $download_info = $this->getDownload($download_id);

        if ($download_info) {
            if (!empty($data['update'])) {
                $this->db->query("UPDATE " . DB_PREFIX . "order_download SET `filename` = '" . $this->db->escape($data['filename']) . "', mask = '" . $this->db->escape($data['mask']) . "' WHERE `filename` = '" . $this->db->escape($download_info['filename']) . "'");
            }

            $this->db->query("UPDATE " . DB_PREFIX . "download SET filename = '" . $this->db->escape($data['filename']) . "', mask = '" . $this->db->escape($data['mask']) . "' WHERE download_id = '" . (int)$download_id . "'");

            $this->db->query("DELETE FROM " . DB_PREFIX . "download_description WHERE download_id = '" . (int)$download_id . "'");

            foreach ($data['download_description'] as $language_id => $value) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "download_description SET download_id = '" . (int)$download_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "'");
            }
        }
    }

    /**
     * [deleteDownload to delete added download]
     * @param  [integer] $download_id [id of particular download]
     */
    public function deleteDownload($download_id)
    {

        $download_info = $this->getDownload($download_id);

        if ($download_info) {

            if (file_exists(DIR_DOWNLOAD . $download_info['filename']))
                unlink(DIR_DOWNLOAD . $download_info['filename']);

            $this->db->query("DELETE FROM " . DB_PREFIX . "download WHERE download_id = '" . (int)$download_id . "'");
            $this->db->query("DELETE FROM " . DB_PREFIX . "download_description WHERE download_id = '" . (int)$download_id . "'");
            $this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_download WHERE download_id = '" . (int)$download_id . "'");
        }
    }

    /**
     * [getDownload to get particular download]
     * @param  [integer] $download_id [id of particular download]
     * @return [array]              [detail about download]
     */
    public function getDownload($download_id)
    {
        $query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "customerpartner_download cd LEFT JOIN " . DB_PREFIX . "download d ON (cd.download_id = d.download_id) LEFT JOIN " . DB_PREFIX . "download_description dd ON (d.download_id = dd.download_id) WHERE d.download_id = '" . (int)$download_id . "' AND dd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cd.seller_id = '" . (int)$this->customer->getId() . "'");

        return $query->row;
    }

    /**
     * [getDownloadProduct to get product for the download]
     * @param  [integer] $download_id [id of download]
     * @return [array]              [detail about download]
     */
    public function getDownloadProduct($download_id)
    {
        $query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "download d LEFT JOIN " . DB_PREFIX . "download_description dd ON (d.download_id = dd.download_id) WHERE d.download_id = '" . (int)$download_id . "' AND dd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

        return $query->row;
    }

    /**
     * [getDownloadDescriptions to get description about download]
     * @param  [integer] $download_id [id of particular download]
     * @return [array]              [description detail of download]
     */
    public function getDownloadDescriptions($download_id)
    {
        $download_description_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "download_description WHERE download_id = '" . (int)$download_id . "'");

        foreach ($query->rows as $result) {
            $download_description_data[$result['language_id']] = array('name' => $result['name']);
        }

        return $download_description_data;
    }

    /**
     * [getTotalDownloads to get total number of downloads]
     * @return [integer] [total number of downloads]
     */
    public function getTotalDownloads()
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_download cd LEFT JOIN " . DB_PREFIX . "download d ON (cd.download_id = d.download_id) LEFT JOIN " . DB_PREFIX . "download_description dd ON (d.download_id = dd.download_id) WHERE dd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cd.seller_id = '" . (int)$this->customer->getId() . "'");

        return count($query->rows);
    }

    /**
     * [getTotalProductsByDownloadId to get total products by a particular download]
     * @param  [integer] $download_id [id of particular download]
     * @return [integer]              [number of download]
     */
    public function getTotalProductsByDownloadId($download_id)
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "product_to_download WHERE download_id = '" . (int)$download_id . "'");

        return $query->row['total'];
    }

    /**
     * [getManufacturers to get manufacturers]
     * @param  [array]  $data [filter keywords]
     * @return [array]       [details about manufacturer]
     */
    public function getManufacturers($data = array())
    {
        $sql = "SELECT * FROM " . DB_PREFIX . "manufacturer WHERE 1=1";

        if (!empty($data['customer_id'])) {
            $sql .= " AND customer_id  = '" . $this->db->escape($data['customer_id']) . "'";
        }
        if (!empty($data['filter_name'])) {
            $sql .= " AND name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
        }

        $sort_data = array(
            'name',
            'sort_order'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY name";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * [getManufacturer to get particular manufacturer]
     * @param  [integer] $manufacturer_id [id of manufacturer]
     * @return [array]                  [details of manufacturer]
     */
    public function getManufacturer($manufacturer_id)
    {
        $query = $this->db->query("SELECT DISTINCT *, (SELECT keyword FROM " . DB_PREFIX . "seo_url WHERE query = 'manufacturer_id=" . (int)$manufacturer_id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "') AS keyword FROM " . DB_PREFIX . "manufacturer WHERE manufacturer_id = '" . (int)$manufacturer_id . "'");

        return $query->row;
    }

    /**
     * [getCategories to get categories]
     * @param  [array] $data [filter keywords]
     * @return [array]       [details of categories]
     */
    public function getCategories($data)
    {

        $marketplace_allowed_categories = '';

        $seller_category = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "customerpartner_to_category WHERE seller_id = " . (int)$this->customer->getId())->row;

        if (isset($seller_category['category_id'])) {
            $marketplace_allowed_categories = $seller_category['category_id'];
        } elseif (!$this->config->get('marketplace_allowed_seller_category_type') && $this->config->get('marketplace_allowed_categories')) {
            foreach ($this->config->get('marketplace_allowed_categories') as $key => $categories) {
                $marketplace_allowed_categories .= ',' . $key;
            }
            if ($marketplace_allowed_categories) {
                $marketplace_allowed_categories = ltrim($marketplace_allowed_categories, ',');
            }
        }

        $sql = "SELECT cp.category_id AS category_id, GROUP_CONCAT(cd1.name ORDER BY cp.level SEPARATOR ' &gt; ') AS name, c.parent_id, c.sort_order FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "category c ON (cp.path_id = c.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd1 ON (c.category_id = cd1.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd2 ON (cp.category_id = cd2.category_id) WHERE cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cd2.language_id = '" . (int)$this->config->get('config_language_id') . "'";

        if ($marketplace_allowed_categories) {
            $sql .= " AND cp.category_id IN (" . $marketplace_allowed_categories . ")";
        }

        if (!empty($data['filter_name'])) {
            $sql .= " AND cd2.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
        }

        $sql .= " GROUP BY cp.category_id ORDER BY name";

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        if (!$this->config->get('marketplace_allowed_seller_category_type') && $marketplace_allowed_categories = '') {

            return array();
        } else {
            $query = $this->db->query($sql);
            return $query->rows;
        }
    }

    /**
     * [getCategory to get particular category details]
     * @param  [integer] $category_id [category id of particular category]
     * @return [array]              [category details]
     */
    public function getCategory($category_id)
    {
        $query = $this->db->query("SELECT DISTINCT *, (SELECT GROUP_CONCAT(cd1.name ORDER BY level SEPARATOR ' &gt; ') FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "category_description cd1 ON (cp.path_id = cd1.category_id AND cp.category_id != cp.path_id) WHERE cp.category_id = c.category_id AND cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' GROUP BY cp.category_id) AS path, (SELECT keyword FROM " . DB_PREFIX . "seo_url WHERE query = 'category_id=" . (int)$category_id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "') AS keyword FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd2 ON (c.category_id = cd2.category_id) WHERE c.category_id = '" . (int)$category_id . "' AND cd2.language_id = '" . (int)$this->config->get('config_language_id') . "'");

        return $query->row;
    }

    /**
     * [getFilters to get filters]
     * @param  [array] $data [filter keywords]
     * @return [array]       [detail about filters]
     */
    public function getFilters($data)
    {
        $sql = "SELECT *, (SELECT name FROM " . DB_PREFIX . "filter_group_description fgd WHERE f.filter_group_id = fgd.filter_group_id AND fgd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS `group` FROM " . DB_PREFIX . "filter f LEFT JOIN " . DB_PREFIX . "filter_description fd ON (f.filter_id = fd.filter_id) WHERE fd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

        if (!empty($data['filter_name'])) {
            $sql .= " AND fd.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
        }

        $sql .= " ORDER BY f.sort_order ASC";

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * [getFilter to get detail about particular filter]
     * @param  [integer] $filter_id [id of particular filter]
     * @return [array]            [detail of particular filter]
     */
    public function getFilter($filter_id)
    {
        $query = $this->db->query("SELECT *, (SELECT name FROM " . DB_PREFIX . "filter_group_description fgd WHERE f.filter_group_id = fgd.filter_group_id AND fgd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS `group` FROM " . DB_PREFIX . "filter f LEFT JOIN " . DB_PREFIX . "filter_description fd ON (f.filter_id = fd.filter_id) WHERE f.filter_id = '" . (int)$filter_id . "' AND fd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

        return $query->row;
    }

    /**
     * [getDownloads to get downloads]
     * @param  [array]  $data [filter keywords]
     * @return [array]       [details of downloads]
     */
    public function getDownloads($data = array())
    {
        $sql = "SELECT * FROM " . DB_PREFIX . "customerpartner_download cd LEFT JOIN " . DB_PREFIX . "download d ON (cd.download_id = d.download_id) LEFT JOIN " . DB_PREFIX . "download_description dd ON (d.download_id = dd.download_id) WHERE dd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cd.seller_id = '" . (int)$this->customer->getId() . "'";

        if (!empty($data['filter_name'])) {
            $sql .= " AND dd.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
        }

        $sort_data = array(
            'dd.name',
            'd.remaining'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY dd.name";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * [getAttributes to get attributes]
     * @param  [array]  $data [filter keyowrds]
     * @return [array]       [details of attributes]
     */
    public function getAttributes($data = array())
    {
        $sql = "SELECT *, (SELECT agd.name FROM " . DB_PREFIX . "attribute_group_description agd WHERE agd.attribute_group_id = a.attribute_group_id AND agd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS attribute_group FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE ad.language_id = '" . (int)$this->config->get('config_language_id') . "'";

        if (!empty($data['filter_name'])) {
            $sql .= " AND ad.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
        }

        if (!empty($data['filter_attribute_group_id'])) {
            $sql .= " AND a.attribute_group_id = '" . $this->db->escape($data['filter_attribute_group_id']) . "'";
        }

        $sort_data = array(
            'ad.name',
            'attribute_group',
            'a.sort_order'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY attribute_group, ad.name";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * [getAttribute to get detail for particular attribute]
     * @param  [integer] $attribute_id [id of an attribute]
     * @return [array]               [details of attribute]
     */
    public function getAttribute($attribute_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE a.attribute_id = '" . (int)$attribute_id . "' AND ad.language_id = '" . (int)$this->config->get('config_language_id') . "'");

        return $query->row;
    }

    /**
     * [getOptions to get options]
     * @param  [array]  $data [filter keywords]
     * @return [array]       [details of options]
     */
    public function getOptions($data = array())
    {
        $sql = "SELECT * FROM `" . DB_PREFIX . "option` o LEFT JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id) WHERE od.language_id = '" . (int)$this->config->get('config_language_id') . "'";

        if (isset($data['filter_name']) && !is_null($data['filter_name'])) {
            $sql .= " AND od.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
        }

        $sort_data = array(
            'od.name',
            'o.type',
            'o.sort_order'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY od.name";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * [getOption to get detail of particular option]
     * @param  [integer] $option_id [id of an option]
     * @return [array]            [detail of particular option]
     */
    public function getOption($option_id)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "option` o LEFT JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id) WHERE o.option_id = '" . (int)$option_id . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "'");

        return $query->row;
    }

    /**
     * [getOptionValue to get value of an option]
     * @param  [integer] $option_value_id [id of option's value]
     * @return [array]                  [detail about the option value]
     */
    public function getOptionValue($option_value_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "option_value ov LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE ov.option_value_id = '" . (int)$option_value_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

        return $query->row;
    }

    /**
     * [getOptionValues to get option's values]
     * @param  [integer] $option_id [id of an option]
     * @return [array]            [detail of option values]
     */
    public function getOptionValues($option_id)
    {
        $option_value_data = array();

        $option_value_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "option_value ov LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE ov.option_id = '" . (int)$option_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY ov.sort_order ASC");

        foreach ($option_value_query->rows as $option_value) {
            $option_value_data[] = array(
                'option_value_id' => $option_value['option_value_id'],
                'name' => $option_value['name'],
                'image' => $option_value['image'],
                'sort_order' => $option_value['sort_order']
            );
        }

        return $option_value_data;
    }

    /**
     * [getCustomerGroups to get customer groups]
     * @param  [array]  $data [filter keywords]
     * @return [array]       [detail of customer groups]
     */
    public function getCustomerGroups($data = array())
    {
        $sql = "SELECT * FROM " . DB_PREFIX . "customer_group cg LEFT JOIN " . DB_PREFIX . "customer_group_description cgd ON (cg.customer_group_id = cgd.customer_group_id) WHERE cgd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

        $sort_data = array(
            'cgd.name',
            'cg.sort_order'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY cgd.name";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * [getProductSellerDetails Uses to check whether this product is a seller product or admin product and if seller product then return seller details]
     * @param  [type] $productId [product Id]
     * @return [type]            [array]
     */
    public function getProductSellerDetails($productId)
    {

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_product c2p LEFT JOIN " . DB_PREFIX . "customer oc ON (c2p.customer_id = oc.customer_id) WHERE c2p.product_id = '" . (int)$productId . "' AND c2p.customer_id > 0")->row;

        return $query;
    }

    /**
     * [addsellerorderproductstatus uses to change the order product status when seller changes his the order product status ]
     * @param  [type] $order_id      [order Id]
     * @param  [type] $orderstatusid [order status id]
     * @param  [type] $product_ids   [product Ids ]
     * @return [type]                [false]
     */
    public function addsellerorderproductstatus($order_id, $orderstatusid, $product_ids)
    {

        foreach ($product_ids as $value) {
            $this->db->query("UPDATE " . DB_PREFIX . "customerpartner_to_order SET order_product_status = '" . $orderstatusid . "' WHERE order_id = '" . (int)$order_id . "' AND product_id = '" . (int)$value . "'");
        }

        return false;

    }

    private function getWholeOrderStatusId($data = array())
    {

        $allOrderStatus = array();
        foreach ($data as $key => $value) {

            array_push($allOrderStatus, $value['order_product_status']);
        }

        $isSameSatus = array_unique($allOrderStatus);

        if (count($isSameSatus) == 1) {

            return $isSameSatus[0];
        } else {
            return false;
        }

    }

    /**
     * [getWholeOrderStatus get order status in which that
     *
     * 1) if all the order product status of that order is same status then whole order status of that order will be that status
     * 2) if some order prodcut status are canceled and rest product status are same then whole order status is same as rest product status
     * 3)if some order prodcut status are canceled and rest product status are not same then whole order status will be the previous status
     * @param  [type] $order_id                  [INT]
     * @param  [type] $admin_cancel_order_status [INT]
     * @return [type]                            [INT]
     */
    public function getWholeOrderStatus($order_id, $admin_cancel_order_status)
    {

        $allOrderStatus = array();

        $getAllStatus = $this->db->query("SELECT order_product_status FROM " . DB_PREFIX . "customerpartner_to_order WHERE order_id = '" . (int)$order_id . "'")->rows;

        foreach ($getAllStatus as $key => $value) {

            array_push($allOrderStatus, $value['order_product_status']);
        }

        $isSameSatus = array_unique($allOrderStatus);

        if (count($isSameSatus) == 1) {

            return $isSameSatus[0];

        } else {

            $check_cancel = $this->db->query("SELECT order_product_status FROM " . DB_PREFIX . "customerpartner_to_order WHERE order_id = '" . (int)$order_id . "' AND order_product_status != '" . $admin_cancel_order_status . "'")->rows;

            $id = $this->getWholeOrderStatusId($check_cancel);

            return $id;


            // $check_cancel = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_order WHERE order_id = '".$order_id."' AND order_product_status = '".$admin_cancel_order_status."'")->num_rows;

            // $check_total_order = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_order WHERE order_id = '".$order_id."'")->num_rows;


            // if ($check_total_order - $check_cancel == 1) {

            // 	 $getOtherStatus = $this->db->query("SELECT order_product_status FROM " . DB_PREFIX . "customerpartner_to_order WHERE order_id = '".$order_id."' AND order_product_status != '".$admin_cancel_order_status."'")->row;

            // 	 return $getOtherStatus['order_product_status'];

            // }else{

            // 	 return $this->config->get('config_order_status_id');
            // }


        }
    }

    /**
     * [changeWholeOrderStatus uses to update the whole order status]
     * @param [type] $order_id        [order Id]
     * @param [type] $order_status_id [order status Id]
     */
    public function changeWholeOrderStatus($order_id, $order_status_id)
    {
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);
        if ($order_info && !in_array($order_info['order_status_id'], array_merge($this->config->get('config_processing_status'), $this->config->get('config_complete_status'))) && in_array($order_status_id, array_merge($this->config->get('config_processing_status'), $this->config->get('config_complete_status')))) {
            // Stock subtraction
            $order_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");

            foreach ($order_product_query->rows as $order_product) {
                $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_id = '" . (int)$order_product['product_id'] . "' AND subtract = '1'");

                $order_option_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$order_product['order_product_id'] . "'");

                foreach ($order_option_query->rows as $option) {
                    $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_option_value_id = '" . (int)$option['product_option_value_id'] . "' AND subtract = '1'");
                }
            }
        }
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . (int)$order_status_id . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");

    }

    /**
     * [addSellerOrderStatus uses to update seller order product status]
     * @param [type] $order_id      [order Id]
     * @param [type] $orderstatusid [order status Id]
     * @param string $comment [comment]
     */
    public function addSellerOrderStatus($order_id, $orderstatusid, $post, $products = array(), $seller_change_order_status_name = '')
    {

        if ($products) {

            foreach ($products as $value) {
                $product_details = $this->getProduct($value);

                if ($post['comment']) {

                    $comment = $product_details['name'] . ' ' . ' status has been changed to' . ' ' . $seller_change_order_status_name . "\n\n";
                    $comment .= strip_tags(html_entity_decode($post['comment'], ENT_QUOTES, 'UTF-8')) . "\n\n";
                } else {
                    $comment = $product_details['name'] . ' ' . ' status has been changed to' . ' ' . $seller_change_order_status_name;
                }

                $this->db->query("INSERT INTO " . DB_PREFIX . "customerpartner_to_order_status SET order_id = '" . (int)$order_id . "',order_status_id = '" . (int)$orderstatusid . "',comment = '" . $this->db->escape($comment) . "',product_id = '" . (int)$product_details['product_id'] . "',customer_id = '" . (int)$this->customer->getId() . "',date_added = NOW()");


            }

        } else {
            $this->db->query("INSERT INTO " . DB_PREFIX . "customerpartner_to_order_status SET order_id = '" . (int)$order_id . "',comment = '" . $post['comment'] . "',customer_id = '" . (int)$this->customer->getId() . "',date_added = NOW()");
        }
        return false;

    }


    /**
     * [getOrderDetails uses to fetch the order details of an order]
     * @param  array $data [filter variable]
     * @param  [type] $order_id   [order Id]
     * @param  [type] $product_id [product Id]
     * @return [type]             [array]
     */
    public function getOrderDetails($data = array(), $order_id, $product_id)
    {

        $sql = "SELECT * FROM " . DB_PREFIX . "customerpartner_to_order_status WHERE order_id = '" . (int)$order_id . "' AND product_id = '" . (int)$product_id . "'";

        $sort_data = array(
            'date_added',
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY change_orderstatus_id";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * [getOrderDetailsTotal uses to count the total order changed status]
     * @param  array $data [filter variable]
     * @param  [type] $order_id   [Order Id]
     * @param  [type] $product_id [Product Id]
     * @return [type]             [array]
     */
    public function getOrderDetailsTotal($data = array(), $order_id, $product_id)
    {

        $sql = "SELECT * FROM " . DB_PREFIX . "customerpartner_to_order_status WHERE order_id = '" . (int)$order_id . "' AND product_id = '" . (int)$product_id . "'";

        $sort_data = array(
            'date_added',
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY change_orderstatus_id";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return count($query->rows);
    }

    /**
     * [getOrderStatusId uses to get order status id of an order]
     * @param  [type] $order_id [order Id]
     * @return [type]           [array]
     */
    public function getOrderStatusId($order_id)
    {

        $query = $this->db->query("SELECT order_status_id FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'")->row;

        return $query;

    }

    /**
     * [getProductStores uses to get store of the product]
     * @param  [type] $product_id [Product Id]
     * @return [type]             [array]
     */
    public function getProductStores($product_id)
    {
        $product_store_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_store WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_store_data[] = $result['store_id'];
        }

        return $product_store_data;
    }

    /**
     * [getUrlAlias uses to get seo url through keyword]
     * @param  [type] $keyword [keyword]
     * @return [type]          [array]
     */
    public function getUrlAlias($keyword, $language_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE keyword = '" . $this->db->escape($keyword) . "' AND language_id = " . (int)$language_id . " AND store_id = '" . $this->config->get('config_store_id') . "'");

        return $query->row;
    }

    /**
     * [copyProduct getting product data for cloning]
     * @param  [type] $product_id [description]
     * @return [type]             [description]
     */
    public function copyProduct($product_id)
    {
        $query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) WHERE p.product_id = '" . (int)$product_id . "' AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

        if ($query->num_rows) {
            $data = $query->row;

            $data['sku'] = '';
            $data['upc'] = '';
            $data['viewed'] = '0';
            $data['keyword'] = '';
            $data['status'] = '0';

            $data['product_attribute'] = $this->getProductAttributes($product_id);
            $data['product_description'] = $this->getProductDescriptions($product_id);
            $data['product_discount'] = $this->getProductDiscounts($product_id);
            $data['product_filter'] = $this->getProductFilters($product_id);
            $data['product_image'] = $this->getProductImages($product_id);
            $data['product_option'] = $this->getProductOptions($product_id);
            $data['product_related'] = $this->getProductRelated($product_id);
            $data['product_reward'] = $this->getProductRewards($product_id);
            $data['product_special'] = $this->getProductSpecials($product_id);
            $data['product_category'] = $this->getProductCategories($product_id);
            $data['product_download'] = $this->getProductDownloads($product_id);
            $data['product_layout'] = $this->getProductLayouts($product_id);
            $data['product_store'] = $this->getProductStores($product_id);
            $data['product_recurrings'] = $this->getRecurrings($product_id);

            return $data;

        }
    }

    public function getProductImages($product_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "' ORDER BY sort_order ASC");

        return $query->rows;
    }

    /**
     * [getProductRewards get product reward ]
     * @param  [type] $product_id [description]
     * @return [type]             [description]
     */
    public function getProductRewards($product_id)
    {
        $product_reward_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_reward WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_reward_data[$result['customer_group_id']] = array('points' => $result['points']);
        }

        return $product_reward_data;
    }

    /**
     * [getProductLayouts get product layout]
     * @param  [type] $product_id [description]
     * @return [type]             [description]
     */
    public function getProductLayouts($product_id)
    {
        $product_layout_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_layout_data[$result['store_id']] = $result['layout_id'];
        }

        return $product_layout_data;
    }

    /**
     * [getRecurrings description]
     * @param  [type] $product_id [description]
     * @return [type]             [description]
     */
    public function getRecurrings($product_id)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_recurring` WHERE product_id = '" . (int)$product_id . "'");

        return $query->rows;
    }

    public function getMinDays($seller_id = 0, $postcode = 0, $weight = 0)
    {
        $sql = "SELECT max_days FROM " . DB_PREFIX . "customerpartner_shipping WHERE seller_id = " . (int)$seller_id . " AND zip_from <= " . (int)$postcode . " AND zip_to >= " . (int)$postcode . " AND weight_from <= " . (float)$weight . " AND weight_to >= " . (float)$weight . "";

        $query = $this->db->query($sql)->row;

        return $query;
    }

    public function getProductRestriction()
    {
        if ($this->cart->hasProducts()) {
            foreach ($this->cart->getProducts() as $key => $product) {

                // Product Discounts
                $product_discount_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product['product_id'] . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((date_start != '0000-00-00' AND date_start <= NOW()) AND (date_end != '0000-00-00' AND date_end >= NOW())) ORDER BY date_start")->row;

                // Product Specials
                $product_special_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$product['product_id'] . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((date_start != '0000-00-00' AND date_start <= NOW()) AND (date_end != '0000-00-00' AND date_end >= NOW())) ORDER BY date_start")->row;

                if ($product_discount_query && $product_special_query) {
                    $start_date = $product_discount_query['date_start'] < $product_special_query['date_start'] ? $product_discount_query['date_start'] : $product_special_query['date_start'];
                } elseif ($product_discount_query) {
                    $start_date = $product_discount_query['date_start'];
                } elseif ($product_special_query) {
                    $start_date = $product_special_query['date_start'];
                } else {
                    $start_date = '0000-00-00';
                }

                //if (isset($start_date) && $start_date > '0000-00-00') {
                //$order_product_quantity = $this->db->query("SELECT op.quantity FROM `" . DB_PREFIX . "order_product` op LEFT JOIN `".DB_PREFIX."order` o ON (op.order_id = o.order_id) WHERE o.customer_id = '" . (int)$this->customer->getId() . "' AND o.order_status_id > '0' AND o.store_id = '" . (int)$this->config->get('config_store_id') . "' AND o.date_added >= '".$start_date."' AND op.product_id = ".(int)$product['product_id'])->rows;

                $order_product_quantity = $this->db->query("SELECT op.quantity FROM `" . DB_PREFIX . "order_product` op LEFT JOIN `" . DB_PREFIX . "order` o ON (op.order_id = o.order_id) WHERE o.customer_id = '" . (int)$this->customer->getId() . "' AND o.order_status_id > '0' AND o.store_id = '" . (int)$this->config->get('config_store_id') . "' AND op.product_id = " . (int)$product['product_id'])->rows;

                $quantity = $product['quantity'];

                if ($order_product_quantity) {
                    foreach ($order_product_quantity as $key => $value) {
                        $quantity += $value['quantity'];
                    }
                }

                if ((int)$this->config->get('marketplace_product_quantity_restriction') && $quantity > (int)$this->config->get('marketplace_product_quantity_restriction')) {
                    return $product;
                }
                //}
            }
        }
        return false;
    }

    public function getCategoryAttribute($category_id = 0)
    {
        if ($category_id) {
            $category_attributes = $this->db->query("SELECT attribute_id FROM " . DB_PREFIX . "wk_category_attribute_mapping WHERE category_id =" . (int)$category_id)->row;

            if (isset($category_attributes['attribute_id']) && $category_attributes['attribute_id']) {
                $sql = "SELECT *, (SELECT agd.name FROM " . DB_PREFIX . "attribute_group_description agd WHERE agd.attribute_group_id = a.attribute_group_id AND agd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS attribute_group FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE ad.language_id = '" . (int)$this->config->get('config_language_id') . "' AND a.attribute_id IN (" . $category_attributes['attribute_id'] . ")";

                $query = $this->db->query($sql);

                return $query->rows;
            }
        }
    }

    /**
     * 获取分类
     *
     * @param int|null $category_id
     * @return array
     */
    public function getCategoryByParentCategoryId(?int $category_id = 0): array
    {
        $seller_category = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "customerpartner_to_category WHERE seller_id = " . (int)$this->customer->getId())->row;
        $marketplaceAllowedCategories = [];
        if (isset($seller_category['category_id'])) {
            $marketplaceAllowedCategories[] = $seller_category['category_id'];
        } elseif (!configDB('marketplace_allowed_seller_category_type') && configDB('marketplace_allowed_categories')) {
            $marketplaceAllowedCategories = array_merge($marketplaceAllowedCategories, configDB('marketplace_allowed_categories'));
        }
        $parentId = (int)$this->db->escape($category_id);
        return app(CategoryRepository::class)->getCategoryByParentCategoryId($parentId, $marketplaceAllowedCategories);
    }

    public function getCustomFieldOptionId($pro_id, $id)
    {
        $result = $this->db->query("SELECT option_id FROM " . DB_PREFIX . "wk_custom_field_product_options WHERE fieldId = '" . (int)$id . "' AND product_id = '" . (int)$pro_id . "' ")->rows;
        return $result;
    }

    public function getProductCustomFields($id)
    {
        $result = $this->db->query("SELECT fieldId FROM " . DB_PREFIX . "wk_custom_field_product WHERE productId = '" . (int)$id . "' ")->rows;
        return $result;
    }

    public function getCustomFieldName($id)
    {
        $result = $this->db->query("SELECT fieldName FROM " . DB_PREFIX . "wk_custom_field_description WHERE fieldId = '" . (int)$id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "' ")->row;
        if (isset($result['fieldName'])) {
            return $result['fieldName'];
        }
    }

    public function getCustomFieldOption($id)
    {
        $result = $this->db->query("SELECT optionValue FROM " . DB_PREFIX . "wk_custom_field_option_description WHERE optionId = '" . (int)$id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "' ")->row;
        if (isset($result['optionValue'])) {
            return $result['optionValue'];
        }
    }

    public function getLoginInInfoByCustomerId()
    {
        $query = $this->db->query("SELECT c.nickname,c.user_number,c2c.screenname FROM " . DB_PREFIX . "customer c LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id = c.customer_id WHERE c.customer_id = '" . (int)$this->customer->getId() . "'");

        return $query->row;
    }

    public function getItemCodeExist($itemCodeArr, $customer_id, $selfSupport)
    {
        $sql1 = "select count(1) as countNum from oc_product p left join oc_customerpartner_to_product c2p on c2p.product_id = p.product_id where ";
        if ($selfSupport == 0) {
            $sql1 .= " p.mpn in";
        } else {
            $sql1 .= " p.sku in";
        }
        $querySql = "(";
        $index = 0;
        foreach ($itemCodeArr as $itemCode) {
            $index++;
            if ($index < count($itemCodeArr)) {
                $querySql .= "'" . $itemCode['ItemCode'] . "',";
            } else {
                $querySql .= "'" . $itemCode['ItemCode'] . "')";
            }
        }
        $sql1 .= $querySql . " and c2p.customer_id = " . (int)$customer_id;
        $query1 = $this->db->query($sql1);
        return $query1->row;
    }

    public function getSelfSupportByCustomerId($customer_id)
    {
        $sql = "SELECT ifnull(c2c.self_support,0) self_support FROM " . DB_PREFIX . "customerpartner_to_customer c2c  WHERE c2c.customer_id = " . (int)$customer_id;
        $query = $this->db->query($sql);
        return $query->row;
    }

    public function addProductByTemplate($productArr, $customer_id, $selfSupport)
    {

        $model = $this->db->query("select screenname from oc_customerpartner_to_customer where customer_id = " . (int)$customer_id)->row;
        foreach ($productArr as $product) {
            if ($selfSupport == 0) {
                $sql = "INSERT INTO " . DB_PREFIX . "product SET model = '" . $model['screenname'] . "', sku = '', upc = '', ean = '', jan = '', isbn = '', mpn = '" . $product['ItemCode'] . "', location = '', quantity = 1, minimum = 1, subtract = 1, stock_status_id = 6, date_available = CURDATE() , manufacturer_id = '', shipping = '', price = '', points = '', weight = '" . (float)$product['Weight(Pounds)'] . "', weight_class_id = 1, length = '" . (float)$product['Length(Inches)'] . "', width = '" . (float)$product['Width(Inches)'] . "', height = '" . (float)$product['Height(Inches)'] . "', length_class_id = 1, status = 1, tax_class_id = '', sort_order = 1, date_added = NOW(), date_modified = NOW()";

            } else {
                $sql = "INSERT INTO " . DB_PREFIX . "product SET model = '', sku = '" . $product['ItemCode'] . "', upc = '', ean = '', jan = '', isbn = '', mpn = '', location = '', quantity = 1, minimum = 1, subtract = 1, stock_status_id = 6, date_available = CURDATE() , manufacturer_id = '', shipping = '', price = '', points = '', weight = '" . (float)$product['Weight(Pounds)'] . "', weight_class_id = 1, length = '" . (float)$product['Length(Inches)'] . "', width = '" . (float)$product['Width(Inches)'] . "', height = '" . (float)$product['Height(Inches)'] . "', length_class_id = 1, status = 1, tax_class_id = '', sort_order = 1, date_added = NOW(), date_modified = NOW()";

            }
            $this->db->query($sql);
            $product_id = $this->db->getLastId();
            //插入oc_customerpartner_to_product
            $sql1 = "insert into oc_customerpartner_to_product  set customer_id = " . (int)$customer_id . ",product_id = " . (int)$product_id . ",price=0,seller_price=0,currency_code='',quantity=0";
            $this->db->query($sql1);
            //插入oc_product_description
            $sql2 = "insert into oc_product_description  set product_id = " . (int)$product_id . ",language_id = 1,name='" . $product['ProductName'] . "',description='',tag='',meta_title='" . $product['ProductName'] . "',meta_description='',meta_keyword=''";
            $this->db->query($sql2);
        }
    }

    /**
     * @param string $relative_path 相对路径
     * @param int $productId 产品ID
     * @param string $file_name 文件名称
     * @param string $origin_name 原始文件名称
     */
    public function addProductPackageImage($relative_path, $productId, $file_name,$origin_name)
    {
        $sql = " insert into oc_product_package_image  set product_id = " . (int)$productId . ",image = '" . $relative_path . "',image_name = '" . $file_name . "',origin_image_name='".$origin_name."'";
        $this->db->query($sql);
    }

    /**
     * @param string $relative_path 相对路径
     * @param int $productId 产品ID
     * @param string $file_name 文件名称
     * @param string $origin_name 原始文件名称
     */
    public function addProductPackageFile($relative_path, $productId, $file_name,$origin_name)
    {
        $sql = " insert into oc_product_package_file set product_id = " . (int)$productId . ",file = '" . $relative_path . "',file_name = '" . $file_name . "',origin_file_name='".$origin_name."'";
        $this->db->query($sql);
    }

    /**
     * @param string $relative_path 相对路径
     * @param int $productId 产品ID
     * @param string $file_name 文件名称
     * @param string $origin_name 原始文件名称
     */
    public function addProductPackageVideo($relative_path, $productId, $file_name,$origin_name)
    {
        $sql = " insert into oc_product_package_video set product_id = " . (int)$productId . ",video = '" . $relative_path . "',video_name = '" . $file_name . "',origin_video_name='".$origin_name."'";
        $this->db->query($sql);
    }

    public function getProductPackageImage($productId)
    {
        $sql = " select image,image_name,origin_image_name,product_id,product_package_image_id from oc_product_package_image where product_id =" . (int)$productId;
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getProductPackageFile($productId)
    {
        $sql = " select file,file_name,origin_file_name,product_id,product_package_file_id from oc_product_package_file where product_id =" . (int)$productId;
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getProductPackageVideo($productId)
    {
        $sql = " select video,video_name,origin_video_name,product_id,product_package_video_id from oc_product_package_video where product_id =" . (int)$productId;
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function deletePackageImage($id)
    {
        $sql = " delete  from oc_product_package_image where product_package_image_id =" . (int)$id;
        $this->db->query($sql);
    }

    public function deletePackageFile($id)
    {
        $sql = " delete from oc_product_package_file where product_package_file_id =" . (int)$id;
        $this->db->query($sql);
    }

    public function deletePackageVideo($id)
    {
        $sql = " delete from oc_product_package_video where product_package_video_id =" . (int)$id;
        $this->db->query($sql);
    }

    public function getColorCategories(int $customer_id): array
    {
        //$sql = "select name,option_value_id from oc_option_value_description where option_id = 13";
        //$query = $this->db->query($sql);
        // return  $query->rows;
        $res = $this->orm
            ->table(DB_PREFIX . 'customer_option_description')
            ->where([
                'customer_id' => $customer_id,
                'option_id' => 13,
                'language_id' => (int)$this->config->get('config_language_id'),
            ])
            ->get();
        $res = $res->map(function ($item) {
            return get_object_vars($item);
        });

        return $res->toArray();
    }

    /**
     * @param string $mpn
     * @param int $precision 设定保留几位小数
     * @return array
     */
    public function checkMpn($mpn, $precision = 0)
    {
        $sql = "
select
 round(p.length,$precision) as length,
 round(p.width,$precision) as width,
 round(p.height,$precision) as height,
 round(p.weight,$precision) as weight,p.combo_flag
 from oc_product p
 LEFT JOIN oc_customerpartner_to_product c2p ON c2p.product_id = p.product_id
 where (p.mpn = '" . $mpn . "') and c2p.customer_id = " . $this->customer->getId();
        $query = $this->db->query($sql);
        return $query->row;
    }

    public function getComboProduct($product_id)
    {
        $sql = "select p.set_mpn,p.qty,round(p.length) as length,round(p.width) as width,round(p.height) as height,round(p.weight) as weight from tb_sys_product_set_info p where p.product_id = " . $product_id;
        $query = $this->db->query($sql);
        return $query->rows;
    }

    /**
     * @param int $product_id
     * @param int $precision combo子商品参数保留多少位小数
     * @return array
     */
    public function getComboProductByOrm($product_id, $precision = 2)
    {
        $res = $this->orm->table('tb_sys_product_set_info as p')
            ->select(['p.set_mpn as mpn', 'p.qty', 'p.qty as quantity', 'p.set_product_id as product_id'])
            ->selectRaw("{$precision} AS 'precision'")
            ->where(['p.product_id' => $product_id])
            ->get();
        $res = $res->map(function ($item) {
            $item = get_object_vars($item);
            $precision = $item['precision'];
            $info = $this->orm
                ->table(DB_PREFIX . 'product as p')
                ->leftJoin(DB_PREFIX . 'product_description as pd', ['pd.product_id' => 'p.product_id'])
                ->select(['pd.name', 'p.sku', 'p.mpn', 'p.image', 'p.product_id'])
                ->selectRaw(
                    "round(p.length,{$precision}) as length,
                    round(p.width,{$precision}) as width,
                    round(p.height,{$precision}) as height,
                    round(p.weight,{$precision}) as weight,
                    round(p.length_cm,{$precision}) as length_cm,
                    round(p.width_cm,{$precision}) as width_cm,
                    round(p.height_cm,{$precision}) as height_cm,
                    round(p.weight_kg,{$precision}) as weight_kg"
                )
                ->where(['p.product_id' => $item['product_id']])
                ->first();
            if ($info) $item = array_merge($item, get_object_vars($info));
            return $item;
        });

        return $res->toArray();
    }


    /**
     * @param array $productIds 已知的子产品Product_id 数组
     * @param int $precision combo子商品参数保留多少位小数
     * @return array
     * @see getComboProductByOrm()
     */
    public function getComboProductBySubProductIds($productIds, $precision = 2)
    {
        if (!$productIds) {
            return [];
        }
        $res = Product::query()->alias('p')
            ->leftJoinRelations(['description as pd'])
            ->select(['p.product_id', 'pd.name', 'p.sku', 'p.mpn', 'p.image', 'p.quantity', 'p.quantity as qty'])
            ->selectRaw("{$precision} AS 'precision'")
            ->selectRaw(
                "round(p.length,{$precision}) as length,
                round(p.width,{$precision}) as width,
                round(p.height,{$precision}) as height,
                round(p.weight,{$precision}) as weight,
                round(p.length_cm,{$precision}) as length_cm,
                round(p.width_cm,{$precision}) as width_cm,
                round(p.height_cm,{$precision}) as height_cm,
                round(p.weight_kg,{$precision}) as weight_kg"
            )
            ->whereIn('p.product_id', $productIds)
            ->get()
            ->toArray();

        return $res;
    }


    public function associate($data, $product_id = null)
    {
        $data = trim($data);
        $sql = "
    select
    round(p.length,2) as length,
    round(p.width,2) as width,
    round(p.height,2) as height,
    round(p.weight,2) as weight,
    p.product_id,p.mpn,p.sku,pd.name,p.image
    from oc_product p
    LEFT JOIN oc_customerpartner_to_product c2p ON c2p.product_id = p.product_id
    LEFT JOIN oc_product_description pd on pd.product_id = p.product_id
    WHERE p.status = 1 and p.is_deleted = 0 and  c2p.customer_id = " . $this->customer->getId() . "
    and (p.mpn like '" . $this->db->escape($data) . "%' or p.sku  like '" . $this->db->escape($data) . "%' or pd.name like '%" . $this->db->escape($data) . "%')";

        if ($product_id != null) {
            $tempStr = null;
            if (is_int($product_id) || is_string($product_id)) $tempStr = $product_id;
            if (is_array($product_id) && count($product_id) > 0) $tempStr = join(',', $product_id);
            $tempStr && $sql .= "and p.product_id not in (" . $this->db->escape($tempStr) . ")";
        }
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getColorAssociate($product_id)
    {
        $sql = "SELECT p.sku,p.mpn,p.image,p.product_size,pd.`name`,p.product_id FROM oc_product_associate pa LEFT JOIN oc_product p ON p.product_id = pa.associate_product_id LEFT JOIN oc_product_description pd on pd.product_id = pa.associate_product_id WHERE pa.product_id =" . $product_id . " AND pa.associate_product_id !=" . $product_id;
        $query = $this->db->query($sql);
        return $query->rows;
    }

    /**
     * @param string $mpn
     * @param int $precision 保留的小数位数
     * @return array
     */
    public function checkMpnNoComboFlag($mpn,$precision = 0)
    {
        $sql = "
select
round(p.length,$precision) as length,
round(p.width,$precision) as width,
round(p.height,$precision) as height,
round(p.weight,$precision) as weight,p.
combo_flag
from oc_product p
LEFT JOIN oc_customerpartner_to_product c2p ON c2p.product_id = p.product_id
where (p.mpn = '" . $mpn . "' or p.sku= '" . $mpn . "')
and c2p.customer_id = " . $this->customer->getId();
        $query = $this->db->query($sql);
        return $query->row;
    }

    public function getCreditlineAmendmentRecordCount($filter_data)
    {
        $sql = "select count(*) as total from tb_sys_credit_line_amendment_record where 1=1 ";
        if (isset($filter_data['customerId']) && $filter_data['customerId'] != '') {
            $sql .= " and customer_id = " . $filter_data['customerId'];
        }
        if (isset($filter_data['filterType'])) {
            if ($filter_data['filterType'] == 1) {//revenue
                $sql .= " and type_id in (" . ChargeType::getRevenueTypes(true) . ")";
            } else if ($filter_data['filterType'] == 2) {//payment
                $sql .= " and type_id in (" . ChargeType::getPaymentTypes(true) . ")";
            }
        }
        if (isset($filter_data['timeSpace'])) {
            if ($filter_data['timeSpace'] == 1) {//一周
                $sql .= " and date_added > '" . date("Y-m-d H:i:s", strtotime("-7 day")) . "'";
            } else if ($filter_data['timeSpace'] == 2) {//一个月
                $sql .= " and date_added > '" . date("Y-m-d H:i:s", strtotime("-1 month")) . "'";
            } else if ($filter_data['timeSpace'] == 3) {//一年
                $sql .= " and date_added > '" . date("Y-m-d H:i:s", strtotime("-1 year")) . "'";
            } else if ($filter_data['timeSpace'] == 4) {
                if (isset($filter_data['timeFrom'])) {
                    $sql = $sql . " and date_added >= '" . $filter_data['timeFrom'] .' 00:00:00'. "'";
                }
                if (isset($filter_data['timeTo'])) {
                    $sql = $sql . " and date_added <= '" . $filter_data['timeTo'] .' 23:59:59'. "'";
                }
            }
        }
        $query = $this->db->query($sql);
        return $query->row['total'];
    }

    /**
     * 获取充值消费记录明细
     * @param $filter_data
     * @return mixed
     */
    public function getCreditlineAmendmentRecordRow($filter_data)
    {
        $sql = "
    select
        concat(date_format(date_added,'%Y%m%d'),id) as id,
        serial_number as serial_number
        ,ROUND(old_line_of_credit,2) as old_line_of_credit
        ,ROUND(new_line_of_credit,2) as balance,date_added
        ,type_id
        ,header_id
        ,memo
    from tb_sys_credit_line_amendment_record
    where 1=1 ";
        if (isset($filter_data['customerId']) && $filter_data['customerId'] != '') {
            $sql .= " and customer_id = " . $filter_data['customerId'];
        }
        if (isset($filter_data['filterType'])) {
            if ($filter_data['filterType'] == 1) {//revenue
                $sql .= " and type_id in (" . ChargeType::getRevenueTypes(true) . ")";
            } else if ($filter_data['filterType'] == 2) {//payment
                $sql .= " and type_id in (" . ChargeType::getPaymentTypes(true) . ")";
            }
        }
        if (isset($filter_data['timeSpace'])) {
            if ($filter_data['timeSpace'] == 1) {//一周
                $sql .= " and date_added > '" . date("Y-m-d H:i:s", strtotime("-7 day")) . "'";
            } else if ($filter_data['timeSpace'] == 2) {//一个月
                $sql .= " and date_added > '" . date("Y-m-d H:i:s", strtotime("-1 month")) . "'";
            } else if ($filter_data['timeSpace'] == 3) {//一年
                $sql .= " and date_added > '" . date("Y-m-d H:i:s", strtotime("-1 year")) . "'";
            } else if ($filter_data['timeSpace'] == 4) {
                if (isset($filter_data['timeFrom'])) {
                    $sql = $sql . " and date_added >= '" . $filter_data['timeFrom'] .' 00:00:00'. "'";
                }
                if (isset($filter_data['timeTo'])) {
                    $sql = $sql . " and date_added <= '" . $filter_data['timeTo'] .' 23:59:59'. "'";
                }
            }
        }
        $sql .= " order by id desc";

        if (isset($filter_data['start']) || isset($filter_data['limit'])) {
            if ($filter_data['start'] < 0) {
                $filter_data['start'] = 0;
            }

            if ($filter_data['limit'] < 1) {
                $filter_data['limit'] = 50;
            }

            $sql .= " LIMIT " . (int)$filter_data['start'] . "," . (int)$filter_data['limit'];
        }

        $query = $this->db->query($sql);
        return $query->rows;
    }

    /**
     * @param int $customerId
     * @param array $data
     * @return \Illuminate\Support\Collection
     * user：wangjinxin
     * date：2019/8/5 11:52
     */
    public function getProductsBySeller($customerID, $data = [])
    {
        $where = [
            ['ctp.customer_id', '=', $customerID],
            ['p.status', 1],
            ['p.buyer_flag', 1],
        ];
        $builder = $this->orm->table('oc_customerpartner_to_product as ctp')
            ->join('oc_product as p', 'p.product_id', '=', 'ctp.product_id')
            ->join('oc_product_description as pd', 'pd.product_id', '=', 'ctp.product_id')
            ->select(['p.product_id', 'p.sku', 'p.mpn', 'pd.name', 'p.price'])
            ->where($where);
        if (isset($data['filter_name']) && !empty($data['filter_name'])) {
            $builder->where(function ($query) use ($data) {
                $query->where([['pd.name', 'like', '%' . trim($data['filter_name']) . '%']])
                    ->orWhere([['p.sku', 'like', '%' . trim($data['filter_name']) . '%']])
                    ->orWhere([['p.mpn', 'like', '%' . trim($data['filter_name']) . '%']]);
            });
        }
        $res = $builder->limit(5)->get();
        $res = $res->map(function ($item) {
            $item->name = html_entity_decode($item->name);
            return $item;
        });

        return $res;
    }

    /**
     * @param string $buyerNickname
     * @return \Illuminate\Support\Collection|null
     */
    public function getBuyerBySellerOrder($buyerNickname, $seller_id)
    {
        if (empty($buyerNickname)) {
            return null;
        }
        $buyerNickname = addslashes($buyerNickname);
        return $this->orm->table('oc_customerpartner_to_order as cto')
            ->join('oc_order as o', 'o.order_id', '=', 'cto.order_id')
            ->join('oc_customer as c', 'c.customer_id', '=', 'o.customer_id')
            ->selectRaw("concat(c.nickname,'(',c.user_number,')') as nickname,c.customer_id")
            ->where('cto.customer_id', $seller_id)
            ->havingRaw("nickname like '%" . $buyerNickname . "%'")
            ->distinct()
            ->take(4)
            ->get();
    }

    /**
     * 获取采购订单的退货
     *
     * @param int $order_id
     * @param int $product_id
     * @param int $buyer_id
     * @param int|null $sales_order_id 销售订单主键
     * @return array
     */
    public function getRMAIDByOrderProduct($order_id, $product_id, $buyer_id,$sales_order_id = null)
    {
        /**
         * Description
         * 采购单与销售单已关联，且是销售单退货
         */

        $saleOrderObj = $this->orm->table('oc_yzc_rma_order as ro')
            ->join('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->join('tb_sys_customer_sales_order as so', 'so.order_id', '=', 'ro.from_customer_order_id')
            ->select(['ro.id as rma_id', 'ro.rma_order_id', 'ro.buyer_id'])
            ->where([
                ['ro.order_id', '=', $order_id],
                ['ro.buyer_id', '=', $buyer_id],
                ['rop.product_id', '=', $product_id],
//                ['rop.status_refund', '=', 1],
//                ['so.order_status', '=', CustomerSalesOrderStatus::CANCELED],
                ['ro.order_type', '=', 1]
            ]);

        if(!isset($sales_order_id) || empty($sales_order_id)){
            return $this->orm->table('oc_yzc_rma_order as ro')
                ->join('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
                ->select(['ro.id as rma_id', 'ro.rma_order_id', 'ro.buyer_id'])
                ->where([
                    ['ro.order_id', '=', $order_id],
                    ['ro.buyer_id', '=', $buyer_id],
                    ['rop.product_id', '=', $product_id],
//                ['rop.status_refund', '=', 1],  // 未处理rma展示
                    ['ro.order_type', '=', 2]
                ])
                ->unionAll($saleOrderObj)
                ->get()
                ->toArray();
        }else{
            return $saleOrderObj->where([['so.id', '=', $sales_order_id]])->get()->toArray();
        }

    }


    /**
     * 获取采购订单+销售订单的退货
     * 参考自上方的 getRMAIDByOrderProduct()方法
     * @param int $order_id   oc_order表主键  oc_order.order_id==oc_yzc_rma_order.order_id
     * @param int $product_id
     * @param int $buyer_id
     * @param int|null $sales_order_id 销售订单主键
     * @return array
     */
    public function getALLRMAIDSByOrderProduct($order_id, $product_id, $buyer_id,$sales_order_id = null)
    {
        /**
         * Description
         * 采购单与销售单已关联，且是销售单退货
         */

        $saleOrderObj = $this->orm->table('oc_yzc_rma_order as ro')
            ->join('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->join('tb_sys_customer_sales_order as so', 'so.order_id', '=', 'ro.from_customer_order_id')
            ->select(['ro.id as rma_id', 'ro.rma_order_id', 'ro.buyer_id'])
            ->where([
                ['ro.order_id', '=', $order_id],
                ['ro.buyer_id', '=', $buyer_id],
                ['rop.product_id', '=', $product_id],
//                ['rop.status_refund', '=', 1],
//                ['so.order_status', '=', CustomerSalesOrderStatus::CANCELED],
                ['ro.order_type', '=', 1]
            ]);

        if(!isset($sales_order_id) || empty($sales_order_id)){
            return $this->orm->table('oc_yzc_rma_order as ro')
                ->join('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
                ->select(['ro.id as rma_id', 'ro.rma_order_id', 'ro.buyer_id'])
                ->where([
                    ['ro.order_id', '=', $order_id],
                    ['ro.buyer_id', '=', $buyer_id],
                    ['rop.product_id', '=', $product_id],
//                ['rop.status_refund', '=', 1],  // 未处理rma展示
                    ['ro.order_type', '=', 2]
                ])
                ->union($saleOrderObj)//不是unionAll
                ->get()
                ->toArray();
        }else{
            return $saleOrderObj->where([['so.id', '=', $sales_order_id]])->get()->toArray();
        }
    }


    /**
     * @param int $order_id
     * @param int $product_id
     * @return float|null 如果为null, 则代表没有参与议价
     */
    public function getQuotePrice($order_id, $product_id)
    {
        $obj = $this->orm->table('oc_product_quote')
            ->select(['price as quote_price'])
            ->where([
                ['order_id', $order_id],
                ['product_id', $product_id]
            ])
            ->first();
        if (empty($obj)) {
            return null;
        } else {
            return $obj->quote_price;
        }
    }

    /**
     * @param int $order_id oc_order表中的order_id
     * @param int $product_id
     * @return array
     */
    public function getQuoteAmountAndService($order_id, $product_id)
    {
        $obj = $this->orm->table('oc_product_quote')
            ->select([
                'price', 'amount_price_per', 'amount_service_fee_per', 'quantity'
            ])
            ->where([
                ['order_id', $order_id],
                ['product_id', $product_id]
            ])
            ->first();
        if (empty($obj)) {
            return [];
        }else{
            return obj2array($obj);
        }
    }

    /**
     * @param int $order_id
     * @param int $product_id
     * @return float|int
     */
    public function getServiceFee($order_id, $product_id)
    {
        $obj = $this->orm->table('oc_order_product')
            ->select(['quantity', 'service_fee'])
            ->where([
                ['order_id', $order_id],
                ['product_id', $product_id]
            ])
            ->first();
        if (empty($obj)) {
            return 0;
        } else {
            return bcdiv($obj->service_fee, $obj->quantity, 2);
        }
    }

    public function getRMAOrderInfo($order_id, $seller_id, $order_product_id)
    {
        $objs = $this->orm->table('oc_yzc_rma_order as ro')
            ->join('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->join('oc_product as p', 'p.product_id', '=', 'rop.product_id')
            ->join('oc_product_description as pd', 'pd.product_id', '=', 'rop.product_id')
            ->join('oc_customer as c', 'c.customer_id', '=', 'ro.buyer_id')
            ->select([
                'ro.order_id',
                'p.sku',
                'p.mpn',
                'pd.name as product_name',
                'rop.quantity',
                'rop.actual_refund_amount',
                'rop.update_time',
                'c.nickname',
                'c.user_number'
            ])
            ->where([
                ['ro.order_id', '=', $order_id],
                ['ro.seller_id', '=', $seller_id],
                ['rop.order_product_id', '=', $order_product_id],
//                ['ro.order_type', '=', 2],
//                ['rop.status_refund', '=', 1]
            ])
            ->get()
            ->toArray();
        return $objs;
    }

    /**
     * [getSellerAgreeRmaOrderInfo description]
     * @param int $order_id oc_order表的order_id
     * @param int $seller_id
     * @param int $order_product_id
     * @return array
     */
    public function getSellerAgreeRmaOrderInfo($order_id, $seller_id, $order_product_id){
        //1.表格里展示的退返品应该只有被Seller同意的（状态是Approved）=
        //2.针对采购订单的退款，是会退库存的，且只能同意一次，Quantity为-退货数量，Total金额为-退款总金额
        //3.针对Canceled销售订单的退款，是会退库存的，且可以申请多次并同意多次，退库存成功的那次Quantity为-退货数量，其他没有退库存的申请，Quantity都为0，Total金额为-每次退款金额
        //4.针对Completed销售订单的退款，是不会退库存的，且可以申请多次并同意多次，每条的Quantity都为0，Total金额为-退款金额。
        //5.针对Completed销售订单的重发，是不会退库存的，且可以申请多次并同意多次，每条的Quantity都为0，Total金额为0。
        //6.表格中的Is Return挪到表格最后一列，在这列后面再加RMA Type（Reshipment、Refund）和 RMA ID
        //7.如果是RMA记录，Purchase Date 为Seller同意RMA的时间（对于Seller 的 Order History，如果是RMA记录，Sales Date 也改为Seller同意RMA的时间）
        $cancel_arr = [];
        $objs = $this->orm->table('oc_yzc_rma_order as ro')
            ->join('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->join('oc_product as p', 'p.product_id', '=', 'rop.product_id')
            ->join('oc_product_description as pd', 'pd.product_id', '=', 'rop.product_id')
            ->join('oc_customer as c', 'c.customer_id', '=', 'ro.buyer_id')
            ->leftJoin('tb_sys_customer_sales_order AS cso', [
                ['cso.order_id', '=', 'ro.from_customer_order_id'],
                ['cso.buyer_id', '=', 'ro.buyer_id']
            ])
            ->leftJoin('tb_sys_credit_line_amendment_record AS clar', function ($join) {
                $join->on('clar.header_id', '=', 'ro.id')
                    ->where([
                        ['clar.type_id', '=', 3]
                    ]);
            })
            ->select([
                'ro.buyer_id',
                'ro.rma_order_id',
                'ro.order_id',
                'ro.from_customer_order_id',
                'ro.processed_date',
                'clar.date_added AS credit_date_added',
                'rop.order_product_id',
                'rop.product_id',
                'p.sku',
                'p.mpn',
                'pd.name as product_name',
                'rop.quantity',
                'ro.order_type',
                'rop.actual_refund_amount',
                'rop.update_time',
                'c.nickname',
                'c.user_number',
                'rop.rma_type',
                'rop.refund_type',
                'rop.status_reshipment',
                'cso.order_status AS cso_order_status',
                'cso.id AS cso_id',
            ])
            ->where([
                ['ro.order_id', '=', $order_id],
                ['ro.seller_id', '=', $seller_id],
                ['rop.order_product_id', '=', $order_product_id],
            ])
            ->whereIn('ro.seller_status', [2, 3])
            ->where(function ($query) {
                //rop.rma_type          RMA类型  1:仅重发; 2:仅退款; 3:即退款又重发
                //rop.status_refund     返金状态 0:初始状态 1:同意 2:拒绝
                //rop.status_reshipment 重发状态 0:初始 1:同意 2:拒绝
                $query->where([['rop.rma_type','=',3],['rop.status_refund','=',1]])//Buyer想即退款又重发，Seller同意退款
                    ->orWhere([['rop.rma_type','=',2],['rop.status_refund','=',1]])//Buyer想仅退款，Seller同意退款，
                    //->orWhere([['rop.rma_type','=',1],['rop.status_reshipment','=',1]]);//Buyer想仅重发，Seller同意重发 不展示
                ;
            })
            ->get()
            ->map(function ($value){
                return (array)$value;
            })
            ->toArray();


        foreach ($objs as $key => $value) {
            if ($value['order_type'] == 1) {
                //销售单RMA
                // 销售订单状态

                //$order_info             =
                //    $this->orm->table('tb_sys_customer_sales_order')
                //        ->where([
                //            'order_id' => $value['from_customer_order_id'],
                //            'buyer_id' => $value['buyer_id']
                //        ])
                //
                //
                //
                //        ->select('order_status', 'id')
                //        ->first();
                //$from_customer_order_id     = $order_info->id;
                //$from_customer_order_status = $order_info->order_status;


                $from_customer_order_id     = $value['cso_id'];
                $from_customer_order_status = $value['cso_order_status'];


                if ($from_customer_order_status == CustomerSalesOrderStatus::CANCELED) {
                    //销售单已取消，rma中最早处理的时间。
                    if (array_key_exists($from_customer_order_id, $cancel_arr)) {
                        if ($value['processed_date'] < $cancel_arr[$from_customer_order_id]) {
                            $cancel_arr[$from_customer_order_id] = $value['processed_date'];
                        }
                    } else {
                        $cancel_arr[$from_customer_order_id] = $value['processed_date'];
                    }
                }
            }
        }
        unset($value);


        //查询结果满足第一条
        foreach($objs as $key => &$value){
            if($value['rma_type'] == 1){
                $objs[$key]['rma_name'] = 'Reshipment';//筛选条件已过滤，不会显示这条
            }elseif($value['rma_type'] == 2){
                $objs[$key]['rma_name'] = 'Refund';
            }else{
                //Buyer想即退款又重发，下载表格只显示 Refund
                $objs[$key]['rma_name'] = 'Refund';
            }
            //满足第二条
            if($value['order_type'] == 2){
                //采购订单的rma
            }else{
                //销售订单的rma
                // 销售订单状态

                //$order_info =
                //    $this->orm->table('tb_sys_customer_sales_order')
                //        ->where([
                //            'order_id' => $value['from_customer_order_id']
                //        ])
                //        ->select('order_status','id')
                //        ->first();
                //$from_customer_order_id     = $order_info->id;
                //$from_customer_order_status = $order_info->order_status;


                $from_customer_order_id     = $value['cso_id'];
                $from_customer_order_status = $value['cso_order_status'];


                if($from_customer_order_status == CustomerSalesOrderStatus::CANCELED){
                    //取消的销售订单
                    //3.针对Canceled销售订单的退款，是会退库存的，且可以申请多次并同意多次，
                    //退库存成功的那次Quantity为-退货数量，其他没有退库存的申请，Quantity都为0，Total金额为-每次退款金额
                    if($value['processed_date'] == $cancel_arr[$from_customer_order_id]){
                        $value['quantity'] =
                            $this->orm->table('tb_sys_order_associated')
                                ->where([
                                    'order_id' => $order_id,
                                    'order_product_id' => $order_product_id,
                                    'sales_order_id' => $from_customer_order_id,
                                ])
                                ->value('qty');
                    }else{
                        $value['quantity'] = 0;
                    }
                }elseif($from_customer_order_status == CustomerSalesOrderStatus::COMPLETED){
                    //Completed的销售订单
                    //满足 .4 .5
                    //rma_type 字段说明 1.仅重发;2.仅退款;3.即退款又重发
                    if($value['rma_type'] == 1){
                        $value['quantity'] = 0;
                        $value['actual_refund_amount'] = 0;
                    }elseif ($value['rma_type'] == 2){
                        $value['quantity'] = 0;
                    }else{
                        $value['quantity'] = 0;
                    }

                }else{
                    //和complete一样
                    //rma_type 字段说明 1.仅重发;2.仅退款;3.即退款又重发
                    if($value['rma_type'] == 1){
                        $value['quantity'] = 0;
                        $value['actual_refund_amount'] = 0;
                    }elseif ($value['rma_type'] == 2){
                        $value['quantity'] = 0;
                    }else{
                        $value['quantity'] = 0;
                    }
                }

            }
        }
        unset($value);

        return $objs;

    }

    /**
     * [getSellerAgreeRmaOrderInfoInOrderHistory description]
     * @param int $order_id oc_order表的order_id
     * @param int $seller_id
     * @param int $order_product_id
     * @return array
     */
    public function getSellerAgreeRmaOrderInfoInOrderHistory($order_id, $seller_id, $order_product_id){
        //1.表格里展示的退返品应该只有被Seller同意的（状态是Approved）=
        //2.针对采购订单的退款，是会退库存的，且只能同意一次，Quantity为-退货数量，金额为-退款总金额
        //3.针对Canceled销售订单的退款，是会退库存的，且可以申请多次并同意多次，退库存成功的那次Quantity为-退货数量，其他没有退库存的申请，Quantity都为0，金额为-每次退款金额
        //4.针对Completed销售订单的退款，是不会退库存的，且可以申请多次并同意多次，每条的Quantity都为0，金额为-退款金额。
        //5.针对Completed销售订单的重发，是不会退库存的，且可以申请多次并同意多次，每条的Quantity都为0，金额为0。
        //6.表格中的Is Return挪到表格最后一列，在这列后面再加RMA Type（Reshipment、Refund）和 RMA ID
        //7.如果是RMA记录，Sales Date 空着
        $cancel_arr = [];
        $objs = $this->orm->table('oc_yzc_rma_order as ro')
            ->join('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->join('oc_product as p', 'p.product_id', '=', 'rop.product_id')
            ->join('oc_product_description as pd', 'pd.product_id', '=', 'rop.product_id')
            ->join('oc_customer as c', 'c.customer_id', '=', 'ro.buyer_id')
            ->select([
                'ro.id',
                'ro.rma_order_id',
                'ro.order_id',
                'ro.buyer_id',
                'ro.from_customer_order_id',
                'rop.product_id',
                'p.sku',
                'p.mpn',
                'pd.name as product_name',
                'rop.quantity',
                'ro.order_type',
                'rop.actual_refund_amount',
                'rop.update_time',
                'rop.coupon_amount',
                'c.nickname',
                'c.user_number',
                'rop.rma_type',
                'ro.processed_date',
            ])
            ->where([
                ['ro.order_id', '=', $order_id],
                ['ro.seller_id', '=', $seller_id],
                ['rop.order_product_id', '=', $order_product_id],
            ])->where(function ($query) {
                //返金和重发同时申请
                $query->where([['rop.rma_type','=',3],['rop.status_refund','=',1]])
                    ->orWhere([['rop.rma_type','=',2],['rop.status_refund','=',1]]);
                //->orWhere([['rop.rma_type','=',1],['rop.status_reshipment','=',1]]);
            })
            ->whereIn('ro.seller_status',[2,3])  // seller  [approved , pending]
            ->orderBy( 'ro.processed_date','asc')
            ->get()
            ->map(function ($value){
                return (array)$value;
            })
            ->toArray();

        //查询结果满足第一条
        foreach($objs as $key => &$value){
            if($value['rma_type'] == 1){
                $objs[$key]['rma_name'] = 'Reshipment';
            }elseif($value['rma_type'] == 2){
                $objs[$key]['rma_name'] = 'Refund';
            }else{
                $objs[$key]['rma_name'] = 'Reshipment&Refund';
            }
            //满足第二条
            if($value['order_type'] == 2){
                //$value['quantity'] =
                //    $this->orm->table('oc_order_product')
                //    ->where([
                //        'order_id'   => $order_id,
                //        'product_id' => $value['product_id'],
                //    ])
                //    ->value('quantity');
            }else{
                //销售订单的rma
                // 销售订单状态
                $order_info =
                    $this->orm->table('tb_sys_customer_sales_order')
                        ->where([
                            'order_id' => $value['from_customer_order_id'],
                            'buyer_id' => $value['buyer_id'],
                        ])
                        ->select('order_status','id')
                        ->first();
                $from_customer_order_id = $order_info->id;

                if($order_info->order_status == CustomerSalesOrderStatus::CANCELED){
                    //取消的销售订单
                    //3.针对Canceled销售订单的退款，是会退库存的，且可以申请多次并同意多次，退库存成功的那次Quantity为-退货数量，其他没有退库存的申请，Quantity都为0，金额为-每次退款金额
                    if(!isset($cancel_arr[$from_customer_order_id])){
                        $value['quantity'] =
                            $this->orm->table('tb_sys_order_associated')
                                ->where([
                                    'order_id' => $order_id,
                                    'order_product_id' => $order_product_id,
                                    'sales_order_id' => $from_customer_order_id,
                                ])
                                ->value('qty');
                        $cancel_arr[ $from_customer_order_id] = 1;
                    }else{
                        $value['quantity'] = 0;
                    }
                }elseif($order_info->order_status == CustomerSalesOrderStatus::COMPLETED){
                    //complete的销售订单
                    //满足 .4 .5
                    if($value['rma_type'] == 1){
                        $value['quantity'] = 0;
                        $value['actual_refund_amount'] = 0;
                    }elseif ($value['rma_type'] == 2){
                        $value['quantity'] = 0;
                    }else{
                        //需要更改 processed_date 假如没有时间的话
                        if(!$value['processed_date']){
                            $value['processed_date'] =
                                    $this->orm->table('tb_sys_credit_line_amendment_record')
                                    ->where(
                                        [
                                            'type_id'=>3,
                                            'header_id'=>$value['id'],
                                        ]
                                    )
                                    ->value('date_added');
                        }
                        $value['quantity'] = 0;
                    }

                }else{
                    //和complete一样
                    if($value['rma_type'] == 1){
                        $value['quantity'] = 0;
                        $value['actual_refund_amount'] = 0;
                    }elseif ($value['rma_type'] == 2){
                        $value['quantity'] = 0;
                    }else{
                        $value['quantity'] = 0;
                    }
                }

            }
        }
        return $objs;

    }

    public function getRmaStockQtyByOrderId($order_id,$order_product_id)
    {
        $cancel_arr = [];
        $objs = $this->orm->table('oc_yzc_rma_order as ro')
            ->join('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->join('oc_product as p', 'p.product_id', '=', 'rop.product_id')
            ->join('oc_product_description as pd', 'pd.product_id', '=', 'rop.product_id')
            ->join('oc_customer as c', 'c.customer_id', '=', 'ro.buyer_id')
            ->select([
                'ro.id',
                'ro.rma_order_id',
                'ro.order_id',
                'ro.buyer_id',
                'ro.from_customer_order_id',
                'rop.product_id',
                'p.sku',
                'p.mpn',
                'pd.name as product_name',
                'rop.quantity',
                'ro.order_type',
                'rop.actual_refund_amount',
                'rop.update_time',
                'c.nickname',
                'c.user_number',
                'rop.rma_type',
                'ro.processed_date',
            ])
            ->where([
                ['ro.order_id', '=', $order_id],
                ['rop.order_product_id', '=', $order_product_id],
            ])->where(function ($query) {
                //返金和重发同时申请
                $query->where([['rop.rma_type','=',3],['rop.status_refund','=',1]])
                    ->orWhere([['rop.rma_type','=',2],['rop.status_refund','=',1]]);
                //->orWhere([['rop.rma_type','=',1],['rop.status_reshipment','=',1]]);
            })
            ->whereIn('ro.seller_status',[2,3])  // seller  [approved , pending]
            ->orderBy( 'ro.processed_date','asc')
            ->get()
            ->map(function ($value){
                return (array)$value;
            })
            ->toArray();

        //查询结果满足第一条
        foreach($objs as $key => &$value){
            //if($value['rma_type'] == 1){
            //    $objs[$key]['rma_name'] = 'Reshipment';
            //}elseif($value['rma_type'] == 2){
            //    $objs[$key]['rma_name'] = 'Refund';
            //}else{
            //    $objs[$key]['rma_name'] = 'Reshipment&Refund';
            //}
            //满足第二条
            if($value['order_type'] == 2){
                //$value['quantity'] =
                //    $this->orm->table('oc_order_product')
                //    ->where([
                //        'order_id'   => $order_id,
                //        'product_id' => $value['product_id'],
                //    ])
                //    ->value('quantity');
            }else{
                //销售订单的rma
                // 销售订单状态
                $order_info =
                    $this->orm->table('tb_sys_customer_sales_order')
                        ->where([
                            'order_id' => $value['from_customer_order_id'],
                            'buyer_id' => $value['buyer_id'],
                        ])
                        ->select('order_status','id')
                        ->first();
                $from_customer_order_id = $order_info->id;

                if($order_info->order_status == CustomerSalesOrderStatus::CANCELED){
                    //取消的销售订单
                    //3.针对Canceled销售订单的退款，是会退库存的，且可以申请多次并同意多次，退库存成功的那次Quantity为-退货数量，其他没有退库存的申请，Quantity都为0，金额为-每次退款金额
                    if(!isset($cancel_arr[$from_customer_order_id])){
                        $value['quantity'] =
                            $this->orm->table('tb_sys_order_associated')
                                ->where([
                                    'order_id' => $order_id,
                                    'order_product_id' => $order_product_id,
                                    'sales_order_id' => $from_customer_order_id,
                                ])
                                ->value('qty');
                        $cancel_arr[ $from_customer_order_id] = 1;
                    }else{
                        $value['quantity'] = 0;
                    }
                }elseif($order_info->order_status == CustomerSalesOrderStatus::COMPLETED){
                    //complete的销售订单
                    //满足 .4 .5
                    if($value['rma_type'] == 1){
                        $value['quantity'] = 0;
                        $value['actual_refund_amount'] = 0;
                    }elseif ($value['rma_type'] == 2){
                        $value['quantity'] = 0;
                    }else{
                        //需要更改 processed_date 假如没有时间的话
                        //if(!$value['processed_date']){
                        //    $value['processed_date'] =
                        //        $this->orm->table('tb_sys_credit_line_amendment_record')
                        //            ->where(
                        //                [
                        //                    'type_id'=>3,
                        //                    'header_id'=>$value['id'],
                        //                ]
                        //            )
                        //            ->value('date_added');
                        //}
                        $value['quantity'] = 0;
                    }

                }else{
                    //和complete一样
                    if($value['rma_type'] == 1){
                        $value['quantity'] = 0;
                        $value['actual_refund_amount'] = 0;
                    }elseif ($value['rma_type'] == 2){
                        $value['quantity'] = 0;
                    }else{
                        $value['quantity'] = 0;
                    }
                }

            }
        }
        if($objs){
            return array_sum(array_column($objs,'quantity'));
        }

        return 0;

    }


    // region 软删除相关方法

    /**
     * 批量设置产品为软删除
     *
     * @param string|int|array $productId
     * @param int $sellerId
     * @param bool $includeComboProducts
     *
     * @return int
     * @throws Exception
     */
    public function setProductIsDeleted($productId,int $sellerId, bool $includeComboProducts = false): int
    {
        $orm = $this->orm;
        if (is_string($productId) || is_int($productId)) {
            $productId = [(int)$productId];
        }
        if (!$this->checkSellerProductIds($productId, (int)$this->customer->getId())) {
            throw new Exception('Invalid operation');
        }
        if ($includeComboProducts) {
            $temp = [];
            foreach ($productId as $id) {
                $temp = array_merge($temp, $this->getComboProductIds($id));
            }
            $productId = array_unique(array_merge($productId, $temp));
        }
        // 商品下架需要变动库存
        $this->load->model('customerpartner/product_manage');
        /** @var ModelCustomerpartnerProductManage $modelCtpProductManage */
        $modelCtpProductManage = $this->model_customerpartner_product_manage;
        $modelCtpProductManage->setProductsOffShelf($productId);


        /**
         * 2020年9月9日
         * huangweinan
         * 软删除产品时，需要将该产品配置的模板等数据进行删除
         */
        //region load model
        $this->load->model('account/customerpartner/rebates');
        $this->load->model('customerpartner/DelicacyManagement');
        $this->load->model('customerpartner/spot_price');
        $this->load->model('account/customerpartner/margin');
        $this->load->model('account/customerpartner/futures');
        //endregion
        $orm->getConnection()->beginTransaction();
        try{
            $del_res = $orm->table(DB_PREFIX . 'product')
                ->whereIn('product_id', $productId)
                ->update([
                    // 软删除标志位置为1
                    'is_deleted' => 1,
                    // 商品设置为下架状态
                    'status' => 0,
                    // 记录删除时间
                    'date_modified' => Carbon::now()->toDateTimeString()
                ]);
            if(!$del_res){
                $orm->getConnection()->rollBack();
                return false;
            }
            //产品审核记录，待审核->取消
            ProductAudit::query()->whereIn('product_id', $productId)
                ->where('customer_id', $sellerId)
                ->where('is_delete', YesNoEnum::NO)
                ->where('status', ProductAuditStatus::PENDING)
                ->update(['status' => ProductAuditStatus::CANCEL, 'update_time' => Carbon::now()]);
            //精细化
            $this->model_customerpartner_DelicacyManagement->batchRemoveByProducts($productId, $sellerId);
            //现货保证金
            $this->model_account_customerpartner_margin->delete_templater_from_productid($sellerId, $productId);
            //期货保证金
            $this->model_account_customerpartner_futures->deleteFuturesByProduct($sellerId, $productId);
            //议价
            $this->model_customerpartner_spot_price->deleteNegotiatedPriceByProducts($sellerId, $productId);
            foreach($productId as $tmp_product_id){
                //阶梯价格
                $this->model_customerpartner_spot_price->delTieredPrice($sellerId, $tmp_product_id);
                //返点
                $this->model_account_customerpartner_rebates->del_product_tpl($tmp_product_id);
            }
            $this->orm->getConnection()->commit();
        }catch (Exception $e) {
            $this->orm->getConnection()->rollBack();
            return false;
        }
        return true;
    }

    /**
     * 批量恢复商品为未软删除状态
     *
     * @param string|int|array $productId
     * @param bool $includeComboProducts
     * @return int
     * @throws Exception
     */
    public function setProductIsNotDeleted($productId, bool $includeComboProducts = false): int
    {
        $orm = $this->orm;
        if (is_string($productId) || is_int($productId)) {
            $productId = [(int)$productId];
        }
        if (!$this->checkSellerProductIds($productId, (int)$this->customer->getId())) {
            throw new Exception('Invalid operation');
        }
        if ($includeComboProducts) {
            $temp = [];
            foreach ($productId as $id) {
                $temp = array_merge($temp, $this->getComboProductIds($id));
            }
            $productId = array_unique(array_merge($productId, $temp));
        }
        return $orm->table(DB_PREFIX . 'product')
            ->whereIn('product_id', $productId)
            ->update([
                // 软删除标志位置为0
                'is_deleted' => 0,
            ]);
    }

    /**
     * 获取商品id对应的子id
     *
     * @param int $productId
     * @return array
     */
    public function getComboProductIds(int $productId): array
    {
        $orm = $this->orm;
        $ret = $orm->table(DB_PREFIX . 'product as p')
            ->leftJoin('tb_sys_product_set_info as ps', ['p.product_id' => 'ps.product_id'])
            ->where(['p.product_id' => $productId, 'p.combo_flag' => 1])
            ->distinct()
            ->pluck('set_product_id')
            ->toArray();

        return array_filter($ret);
    }

    /**
     *  校验产品是不是属于seller本身
     *
     * @param array $productIds
     * @param int $customerId
     * @return bool
     */
    public function checkSellerProductIds(array $productIds, int $customerId): bool
    {
        $customerProductIds = $this->orm
            ->table(DB_PREFIX . 'product as p')
            ->leftJoin(DB_PREFIX . 'customerpartner_to_product as ctp', ['ctp.product_id' => 'p.product_id'])
            ->where(['ctp.customer_id' => $customerId])
            ->whereIn('p.product_id', $productIds)
            ->pluck('p.product_id')
            ->toArray();

        return (bool)(count($customerProductIds) == count($productIds));
    }
    // end region

    /**
     * w外部seller生成combo品sku
     * @author xxl
     * @param $comboNum
     * @param $storeName
     * @param int $customer_id
     * @param int $product_id
     */
    public function outerSellerCombo($comboNum,$storeName,$customer_id,$product_id){
        $sku = $storeName . 'S' . str_pad($comboNum + 1, 5, "0", STR_PAD_LEFT);
        $count = $this->orm->table("oc_product as op")
            ->leftJoin('oc_customerpartner_to_product as ctp','op.product_id','=','ctp.product_id')
            ->where([['op.sku','=',$sku],['ctp.customer_id','=',$customer_id]])
            ->count();
        if($count>0){
            $this->outerSellerCombo($comboNum+1,$storeName,$customer_id,$product_id);
        }else{
            $this->db->query("update oc_product set sku='" . $sku . "' where product_id =" . $product_id);
        }
    }

    /**
     * 获取seller专属的cc客服参数
     *
     * @param array $get The get of request.
     * @return array ['cc_web_id','cc_wc']
     */
    public function getCCServiceParams($get)
    {
        $params = [];
        if (isset($get['route']) && $get['route'] == 'customerpartner/profile' && isset_and_not_empty($get, 'id')) {
            $obj = $this->orm->table('oc_customerpartner_to_customer')
                ->select(['cc_web_id', 'cc_wc'])
                ->where([
                    ['customer_id', '=', $get['id']]
                ])
                ->first();
            if (isset_and_not_empty($obj, 'cc_web_id') && isset_and_not_empty($obj, 'cc_wc')) {
                $params = [
                    'cc_web_id' => $obj->cc_web_id,
                    'cc_wc' => $obj->cc_wc,
                ];
            }
        } elseif (isset($get['route']) && $get['route'] == 'product/product' && isset_and_not_empty($get, 'product_id')) {
            $obj = $this->orm->table('oc_customerpartner_to_customer as ctc')
                ->join('oc_customerpartner_to_product as ctp', 'ctp.customer_id', '=', 'ctc.customer_id')
                ->select(['ctc.cc_web_id', 'ctc.cc_wc'])
                ->where([
                    ['ctp.product_id', '=', $get['product_id']]
                ])
                ->first();
            if (isset_and_not_empty($obj, 'cc_web_id') && isset_and_not_empty($obj, 'cc_wc')) {
                $params = [
                    'cc_web_id' => $obj->cc_web_id,
                    'cc_wc' => $obj->cc_wc,
                ];
            }
        }
        return $params;
    }

    /**
     * 订阅该产品的所有买家的查看状态
     * @param int $product_id
     * @param int $seller_id
     * @return array
     */
    public function  getAllWishListBuyersProductInfo($product_id,$seller_id){
        //1. 查询订阅此产品的buyer 且buyer seller 建立了联系
        $map = [
            ['bts.seller_id', '=', $seller_id],
            ['cw.product_id', '=', $product_id],
            ['bts.buy_status','=','1'],
            ['bts.buyer_control_status','=','1'],
            ['bts.seller_control_status','=','1']
        ];
        $res = $this->orm->table(DB_PREFIX . 'customer_wishlist as cw')
            ->leftJoin(DB_PREFIX . 'buyer_to_seller as bts', 'bts.buyer_id', '=', 'cw.customer_id')
            ->where($map)
            ->select('bts.buyer_id', 'bts.seller_id', 'bts.discount')
            ->get()
            ->map(function ($item){
                return get_object_vars($item);
            })
            ->toArray();

        $product_info = $this->orm->table(DB_PREFIX . 'product as p')
            ->leftJoin('oc_customerpartner_to_product as ctp','ctp.product_id','=','p.product_id')
            ->leftJoin('oc_customer as oc','oc.customer_id','=','ctp.customer_id')
            ->leftJoin('oc_product_description as pd', 'pd.product_id', '=', 'p.product_id')
            ->where('p.product_id', $product_id)
            ->select('p.status', 'p.buyer_flag', 'oc.status as customerStatus','p.sku','pd.name','p.quantity','p.price','p.freight')
            ->first();
        $product_info = obj2array($product_info);
        if (!$res) {
            return [];
        }
        $buyerCanBuyArr = array();
        $productStatus = isset($product_info['status']) ? $product_info['status'] : 0;
        $productBuyerFlag = isset($product_info['buyer_flag']) ? $product_info['buyer_flag'] : 0;
        $productCustomerStatus = isset($product_info['customerStatus']) ? $product_info['customerStatus'] : 0;
        if($productStatus == 1 && $productBuyerFlag == 1 && $productCustomerStatus == 1){
            $canBuyerFlag = 1;
        }else{
            $canBuyerFlag = 0;
        }
        foreach ($res as $buyer){
            if($canBuyerFlag==1) {
                $result = $this->commonFunction->getDelicacyManagementInfoByNoView($product_id, $buyer['buyer_id'], $seller_id);
                if (isset($result)) {
                    $buyerCanBuyArr[$buyer['buyer_id']] = $result['product_display'] == 1?1:0;
                } else {
                    $buyerCanBuyArr[$buyer['buyer_id']] = $canBuyerFlag;
                }
            }else{
                $buyerCanBuyArr[$buyer['buyer_id']] = $canBuyerFlag;
            }
        }
        return $buyerCanBuyArr;
    }

    /**
     * 获取某个采购订单涉及的seller_id和product_id
     *
     * @param int $order_id oc_order表的order_id
     * @return array|null
     */
    public function getPurchaseOrderSellerId($order_id)
    {
        if (empty($order_id)) {
            return null;
        }
        $ret = $this->orm->table('oc_customerpartner_to_order as cto')
            ->where('cto.order_id', '=', $order_id)
            ->select('cto.customer_id as seller_id', 'cto.product_id')
            ->get();

        $ret_array = $ret->toArray();
        $map = [];
        if (!empty($ret_array)) {
            foreach ($ret_array as $item) {
                $map[$item->product_id] = $item->seller_id;
            }
        }
        return $map;
    }

    /**
     * 某个seller是否关联特殊美国本土销售账户经理
     * @param int $sellerId
     * @return bool
     */
    public function isRelationUsaSellerSpecialAccountManagerBySellerId(int $sellerId)
    {
        $emailArr = configDB('relation_usa_seller_special_account_manager_emails', []);
        return $this->orm->table(DB_PREFIX . 'buyer_to_seller as bts')
            ->join(DB_PREFIX . 'customer as c', 'bts.buyer_id', '=', 'c.customer_id')
            ->where('bts.seller_id', $sellerId)
            ->when($emailArr, function (Builder $q) use ($emailArr) {
                $q->whereIn('c.email', $emailArr);
            })
            ->where('c.customer_group_id', 14)
            ->exists();
    }
}

?>
