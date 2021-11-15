<?php

use App\Enums\Order\OcOrderStatus;
use App\Enums\SalesOrder\CustomerSalesOrderLineItemStatus;
use App\Models\Product\Product;
use App\Models\Product\ProductSetInfo;
use App\Repositories\Product\CategoryRepository;
use App\Repositories\Product\ProductInfo\ProductPriceRangeFactory;
use App\Repositories\Product\ProductOptionRepository;
use App\Repositories\Product\ProductPriceRepository;
use App\Repositories\Product\ProductRepository;

use App\Enums\Warehouse\ReceiptOrderStatus;
use App\Repositories\ProductLock\ProductLockRepository;
use App\Services\Product\ProductService;
use Illuminate\Support\Collection;

/**
 * Class ModelCatalogProduct
 * @property ModelAccountCustomerpartnerOrder $model_account_customerpartnerorder
 * @property ModelExtensionModuleProductShow $model_extension_module_product_show
 * @property ModelCatalogBuyertoseller $model_catalog_buyer_to_seller
 * @property ModelToolImage $model_tool_image
 * @property ModelExtensionModuleProductHome $model_extension_module_product_home
 */
class ModelCatalogProduct extends Model
{
    /**
     * @var string
     */
    public $table;

    /**
     * ModelBuyerToSeller constructor.
     * @param $registry
     */
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->table = DB_PREFIX . 'product';
        $this->registry = $registry;
    }

    public function addProduct($data)
    {
        $this->db->query("INSERT INTO "
            . DB_PREFIX . "product SET model = '" . $this->db->escape($data['model'])
            . "', sku = '" . $this->db->escape($data['sku'])
            . "', upc = '" . $this->db->escape($data['upc'])
            . "', ean = '" . $this->db->escape($data['ean'])
            . "', jan = '" . $this->db->escape($data['jan'])
            . "', isbn = '" . $this->db->escape($data['isbn'])
            . "', mpn = '" . $this->db->escape($data['mpn'])
            . "',asin='" . trim($data['asin']) . "', location = '" . $this->db->escape($data['location'])
            . "', quantity = '" . (int)$data['quantity'] . "', minimum = '" . (int)$data['minimum']
            . "', subtract = '" . (int)$data['subtract'] . "', stock_status_id = '"
            . (int)$data['stock_status_id'] . "', date_available = '" . $this->db->escape($data['date_available'])
            . "', manufacturer_id = '" . (int)$data['manufacturer_id'] . "',brand='" . $data['brand']
            . "', shipping = '" . (int)$data['shipping'] . "', price = '" . (float)$data['price']
            . "', points = '" . (int)$data['points'] . "', weight = '" . (float)$data['weight']
            . "', weight_class_id = '" . (int)$data['weight_class_id'] . "', length = '" . (float)$data['length']
            . "', width = '" . (float)$data['width'] . "', height = '" . (float)$data['height']
            . "', length_class_id = '" . (int)$data['length_class_id']
            . "', status = '" . (int)$data['status'] . "', tax_class_id = '" . (int)$data['tax_class_id']
            . "', sort_order = '" . (int)$data['sort_order']
            . "', product_type = '" . (int)($data['product_type'] ?? 0)
            . "', date_added = NOW(), date_modified = NOW(), combo_flag='" . intval($data['combo_flag'])
            . "' , buyer_flag='" . intval($data['buyer_flag']) . "', is_deleted='" . intval($data['is_deleted'])
            . "', part_flag='" . $data['part_flag'] . "',is_once_available='" . intval($data['is_once_available'])
            . "', freight='" . $data['freight'] . "', package_fee='" . (float)$data['package_fee'] . "'");

        $product_id = $this->db->getLastId();

        if (isset($data['image'])) {
            $this->db->query("UPDATE " . DB_PREFIX . "product SET image = '" . $this->db->escape($data['image']) . "' WHERE product_id = '" . (int)$product_id . "'");
        }

        foreach ($data['product_description'] as $language_id => $value) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_description SET product_id = '" . (int)$product_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "', description = '" . $this->db->escape($value['description']) . "', tag = '" . $this->db->escape($value['tag']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
        }

        if (isset($data['product_store'])) {
            foreach ($data['product_store'] as $store_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "'");
            }
        }

        if (isset($data['product_attribute'])) {
            foreach ($data['product_attribute'] as $product_attribute) {
                if ($product_attribute['attribute_id']) {
                    // Removes duplicates
                    $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$product_attribute['attribute_id'] . "'");

                    foreach ($product_attribute['product_attribute_description'] as $language_id => $product_attribute_description) {
                        $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$product_attribute['attribute_id'] . "' AND language_id = '" . (int)$language_id . "'");

                        $this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = '" . (int)$product_id . "', attribute_id = '" . (int)$product_attribute['attribute_id'] . "', language_id = '" . (int)$language_id . "', text = '" . $this->db->escape($product_attribute_description['text']) . "'");
                    }
                }
            }
        }

        if (isset($data['product_option'])) {
            foreach ($data['product_option'] as $product_option) {
                if ($product_option['type'] == 'select' || $product_option['type'] == 'radio' || $product_option['type'] == 'checkbox' || $product_option['type'] == 'image') {
                    if (isset($product_option['product_option_value'])) {
                        $this->db->query("INSERT INTO " . DB_PREFIX . "product_option SET product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', required = '" . (int)$product_option['required'] . "'");

                        $product_option_id = $this->db->getLastId();

                        foreach ($product_option['product_option_value'] as $product_option_value) {
                            $this->db->query("INSERT INTO " . DB_PREFIX . "product_option_value SET product_option_id = '" . (int)$product_option_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', option_value_id = '" . (int)$product_option_value['option_value_id'] . "', quantity = '" . (int)$product_option_value['quantity'] . "', subtract = '" . (int)$product_option_value['subtract'] . "', price = '" . (float)$product_option_value['price'] . "', price_prefix = '" . $this->db->escape($product_option_value['price_prefix']) . "', points = '" . (int)$product_option_value['points'] . "', points_prefix = '" . $this->db->escape($product_option_value['points_prefix']) . "', weight = '" . (float)$product_option_value['weight'] . "', weight_prefix = '" . $this->db->escape($product_option_value['weight_prefix']) . "'");
                        }
                    }
                } else {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_option SET product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', value = '" . $this->db->escape($product_option['value']) . "', required = '" . (int)$product_option['required'] . "'");
                }
            }
        }

        if (isset($data['product_recurring'])) {
            foreach ($data['product_recurring'] as $recurring) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "product_recurring` SET `product_id` = " . (int)$product_id . ", customer_group_id = " . (int)$recurring['customer_group_id'] . ", `recurring_id` = " . (int)$recurring['recurring_id']);
            }
        }

        if (isset($data['product_discount'])) {
            foreach ($data['product_discount'] as $product_discount) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_discount SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$product_discount['customer_group_id'] . "', quantity = '" . (int)$product_discount['quantity'] . "', priority = '" . (int)$product_discount['priority'] . "', price = '" . (float)$product_discount['price'] . "', date_start = '" . $this->db->escape($product_discount['date_start']) . "', date_end = '" . $this->db->escape($product_discount['date_end']) . "'");
            }
        }

        if (isset($data['product_special'])) {
            foreach ($data['product_special'] as $product_special) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_special SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$product_special['customer_group_id'] . "', priority = '" . (int)$product_special['priority'] . "', price = '" . (float)$product_special['price'] . "', date_start = '" . $this->db->escape($product_special['date_start']) . "', date_end = '" . $this->db->escape($product_special['date_end']) . "'");
            }
        }

        if (isset($data['product_image'])) {
            foreach ($data['product_image'] as $product_image) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_image SET product_id = '" . (int)$product_id . "', image = '" . $this->db->escape($product_image['image']) . "', sort_order = '" . (int)$product_image['sort_order'] . "'");
            }
        }

        if (isset($data['product_download'])) {
            foreach ($data['product_download'] as $download_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_download SET product_id = '" . (int)$product_id . "', download_id = '" . (int)$download_id . "'");
            }
        }

        if (isset($data['product_category'])) {
            foreach ($data['product_category'] as $category_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$category_id . "'");
            }
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

        if (isset($data['product_reward'])) {
            foreach ($data['product_reward'] as $customer_group_id => $product_reward) {
                if ((int)$product_reward['points'] > 0) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_reward SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$customer_group_id . "', points = '" . (int)$product_reward['points'] . "'");
                }
            }
        }

        // SEO URL
        if (isset($data['product_seo_url'])) {
            foreach ($data['product_seo_url'] as $store_id => $language) {
                foreach ($language as $language_id => $keyword) {
                    if (!empty($keyword)) {
                        $this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'product_id=" . (int)$product_id . "', keyword = '" . $this->db->escape($keyword) . "'");
                    }
                }
            }
        }

        if (isset($data['product_layout'])) {
            foreach ($data['product_layout'] as $store_id => $layout_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_layout SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout_id . "'");
            }
        }


        $this->cache->delete('product');

        return $product_id;
    }

    public function editProduct($product_id, $data)
    {

    }

    public function copyProduct($product_id)
    {
        $query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "product p WHERE p.product_id = '" . (int)$product_id . "'");

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

            $this->addProduct($data);
        }
    }

    //购买保证金后，复制产品
    public function copyProductMargin($product_id, $param)
    {
        $query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "product p WHERE p.product_id = '" . (int)$product_id . "'");
        $product_id_new = 0;
        if ($query->num_rows) {
            $data = $query->row;

            if (isset($param['num'])) {
                $data['quantity'] = $param['num'];
            }
            if (isset($param['price'])) {
                $data['price'] = $param['price'];
            }
            if (isset($param['sku'])) {
                $data['sku'] = $param['sku'];
                $data['mpn'] = $param['sku'];
            }
            if (isset($param['freight'])) {
                $data['freight'] = $param['freight'];
            }
            if (isset($param['package_fee'])) {
                $data['package_fee'] = $param['package_fee'];
            }
            $product_name = null;
            if (isset($param['product_name'])) {
                $product_name = $param['product_name'];
            }
            if (isset($param['product_type'])) {
                $data['product_type'] = $param['product_type'];
            }

            $data['product_attribute'] = $this->getProductAttributes($product_id);
            $data['product_description'] = $this->getProductDescriptions($product_id, $product_name);
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

            $product_id_new = $this->addProduct($data);
        }
        return $product_id_new;
    }

    public function addSellersToSeller($seller_id, $customer_ids)
    {
        if ($customer_ids && count($customer_ids) > 0) {
            $sql = "INSERT INTO `" . DB_PREFIX . "buyer_to_seller` (buyer_id, seller_id, buy_status, price_status, buyer_control_status, seller_control_status, discount) SELECT " . $seller_id . ",customer_id, 1,1,1,1,1 FROM `oc_customer` c WHERE c.`customer_id` IN (";
            foreach ($customer_ids as $customer_id) {
                $sql .= $customer_id . ',';
            }
            $sql = substr($sql, 0, strlen($sql) - 1);
            $sql .= ')';
            $this->db->query($sql);
        }
    }

    public function getProductDescriptions($product_id, $new_name = null)
    {
        $product_description_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_description WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_description_data[$result['language_id']] = array(
                'name' => isset($new_name) ? $new_name : $result['name'],
                'description' => $result['description'],
                'meta_title' => $result['meta_title'],
                'meta_description' => $result['meta_description'],
                'meta_keyword' => $result['meta_keyword'],
                'tag' => $result['tag']
            );
        }

        return $product_description_data;
    }

    public function getProductFilters($product_id)
    {
        $product_filter_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_filter WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_filter_data[] = $result['filter_id'];
        }

        return $product_filter_data;
    }

    public function getProductRewards($product_id)
    {
        $product_reward_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_reward WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_reward_data[$result['customer_group_id']] = array('points' => $result['points']);
        }

        return $product_reward_data;
    }

    public function getProductCategories($product_id)
    {
        $product_category_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_category_data[] = $result['category_id'];
        }

        return $product_category_data;
    }

    public function getProductDownloads($product_id)
    {
        $product_download_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_download WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_download_data[] = $result['download_id'];
        }

        return $product_download_data;
    }

    public function getProductLayouts($product_id)
    {
        $product_layout_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_layout_data[$result['store_id']] = $result['layout_id'];
        }

        return $product_layout_data;
    }

    public function getProductStores($product_id)
    {
        $product_store_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_store WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_store_data[] = $result['store_id'];
        }

        return $product_store_data;
    }

    public function getRecurrings($product_id)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_recurring` WHERE product_id = '" . (int)$product_id . "'");

        return $query->rows;
    }

    public function getCustomFieldOptionId($pro_id, $id)
    {
        $result = $this->db->query("SELECT option_id FROM " . DB_PREFIX . "wk_custom_field_product_options WHERE fieldId = '" . (int)$id . "' AND product_id = '" . (int)$pro_id . "' ")->rows;
        return $result;
    }

    public function getProductCustomFields($id)
    {
        $result = $this->db->query("SELECT fieldId FROM " . DB_PREFIX . "wk_custom_field_product WHERE productId = " . (int)$id)->rows;
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

    public function updateViewed($product_id)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "product SET viewed = (viewed + 1) WHERE product_id = '" . (int)$product_id . "'");
    }

    public function checkProduct($product_id, $country = null, $customFields = null)
    {
        if (!$customFields) {
            $country_id = $this->orm->table('oc_country')
                ->where('iso_code_3', $country)
                ->value('country_id') ?: 0;
            $res = $this->orm->table('oc_customerpartner_to_product as ctp')
                ->leftJoin('oc_customer as cu', 'ctp.customer_id', '=', 'cu.customer_id')
                ->where(['cu.country_id' => $country_id, 'ctp.product_id' => $product_id])
                ->get();
            return (bool)$res->count();
        } else {
            $result = $this->getDelicacyManagementInfoByNoView($product_id, $customFields);
            if (empty($result) || $result['product_display'] != 0) {
                return true;
            } else {
                return false;
            }
        }
    }


    /**
     * 不根据视图直接查询 精细化相关设置
     * 注：
     *    该方法 需要针对具体 product-buyer 才能使用
     *
     * @param int $product_id
     * @param int $buyer_id
     * @param int $seller_id
     * @return array|null 如果为 null, 则代表没有参与精细化管理
     * @todo 如果 vw_delicacy_management 发生修改, 此处也要修改
     */
    public function getDelicacyManagementInfoByNoView($product_id, $buyer_id, $seller_id = null)
    {
        if (empty($product_id) || empty($buyer_id)) {
            return null;
        }

        $dm_sql = "select product_id,product_display,current_price,price
from oc_delicacy_management
where product_id = $product_id and buyer_id = $buyer_id and expiration_time > NOW() order by id DESC limit 1";

        $dmg_sql = "select dmg.id from oc_delicacy_management_group as dmg
join oc_customerpartner_product_group_link as pgl on pgl.product_group_id = dmg.product_group_id
join oc_customerpartner_buyer_group_link as bgl on bgl.buyer_group_id = dmg.buyer_group_id
where dmg.status =1 and pgl.status=1 and bgl.status=1 and pgl.product_id = $product_id and bgl.buyer_id = $buyer_id ";
        $seller_id && $dmg_sql .= " and dmg.seller_id = " . $seller_id;
        if ($this->db->query($dmg_sql)->num_rows > 0) {
            $result = [
                'product_display' => 0,
            ];
        } else {
            $dm_res = $this->db->query($dm_sql);
            if ($dm_res->num_rows > 0) {
                $result = [
                    'product_display' => $dm_res->row['product_display'],
                    'current_price' => $dm_res->row['current_price'],
                ];
            } else {
                $result = null;
            }
        }
        return $result;
    }

    /**
     * 获取一组商品的精细化数据
     * @param array $productIds
     * @param int $buyerId
     * @return array
     */
    private function getDmInfoByProductIds(array $productIds, int $buyerId): array
    {
        if (empty($productIds)) {
            return [];
        }
        return db('oc_delicacy_management')
            ->where('buyer_id', $buyerId)
            ->whereIn('product_id', $productIds)
            ->whereRaw('expiration_time > NOW()')
            ->orderBy('id')
            ->groupBy(['product_id'])
            ->get()
            ->keyBy('product_id')
            ->map(function ($v) {
                return get_object_vars($v);
            })
            ->toArray();
    }

    /**
     * 获取一组商品的组精细化数据
     * @param array $productIds
     * @param int $buyerId
     * @return array
     */
    private function getDmgInfoByProductIds(array $productIds, int $buyerId): array
    {
        if (empty($productIds)) {
            return [];
        }
        return db('oc_delicacy_management_group as dmg')
            ->join('oc_customerpartner_product_group_link as pgl', 'pgl.product_group_id', '=', 'dmg.product_group_id')
            ->join('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->where([
                'dmg.status' => 1,
                'pgl.status' => 1,
                'bgl.status' => 1,
                'bgl.buyer_id' => $buyerId,
            ])
            ->whereIn('pgl.product_id', $productIds)
            ->groupBy(['pgl.product_id'])
            ->get()
            ->keyBy('product_id')
            ->map(function ($v) {
                return get_object_vars($v);
            })
            ->toArray();
    }

    /**
     * 过滤精细化不可见，返回可见的产品id
     * @param array $productIds
     * @param int $buyerId
     * @return array
     * @throws Exception
     */
    public function getProductIdsByDm(array $productIds, int $buyerId): array
    {
        if (empty($productIds)) {
            return [];
        }

        $arrDmg = $this->getDmgInfoByProductIds($productIds, $buyerId);
        if ($arrDmg) {
            foreach ($arrDmg as $productId => $value) {
                unset($productIds[array_search($productId, $productIds)]);
            }
        }
        $arrDm = $this->getDmInfoByProductIds($productIds, $buyerId);
        if ($arrDm) {
            //每一项自带 product_display、current_price
            foreach ($arrDm as $productId => $value) {
                if (!$value['product_display']) {
                    unset($productIds[array_search($productId, $productIds)]);
                }
            }
        }
        return $productIds;
    }

    /**
     * 获取combo品的组成数量
     * @param array $productIds
     * @return array [product_id => package_quantity..]
     */
    private function getPackageQuantityByProductIds(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }
        return ProductSetInfo::query()
            ->whereIn('product_id', $productIds)
            ->select(['product_id'])
            ->selectRaw('sum(qty) as package_quantity')
            ->groupBy(['product_id'])
            ->get()
            ->keyBy('product_id')
            ->map(function ($v) {
                return (int)$v->package_quantity;
            })
            ->toArray();
    }

    /**
     * 获取quote flag
     * @param array $productIds
     * @return array
     */
    private function getQuoteFlagByProductIds(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }
        return db('oc_wk_pro_quote_details AS pd')
            ->leftJoin('oc_wk_pro_quote AS pq', 'pq.seller_id', '=', 'pd.seller_id')
            ->where('pq.status', 0)
            ->whereIn('pd.product_id', $productIds)
            ->groupBy(['pd.product_id'])
            ->pluck('pd.product_id')
            ->toArray();
    }

    /**
     * 获取 product 信息
     *
     * @param int $product_id
     * @param null|int $buyer_id
     * @return array|bool
     * @throws Exception
     */
    public function getProduct($product_id, $buyer_id = null)
    {
        $product_id = (int)$product_id;
        $product_status = 1;
        $product_buyer_flag = 1;
        $commission_amount = 0;
        $customer_group_id = (int)$this->config->get('config_customer_group_id');
        $language_id = (int)$this->config->get('config_language_id');
        $store_id = (int)$this->config->get('config_store_id');

        $check_seller_product = $this->db->query("SELECT customer_id FROM oc_customerpartner_to_product WHERE product_id = " . (int)$product_id . " limit 1 ")->row;

        if (($this->config->get('module_marketplace_status') && isset($this->request->get['user_token']) && isset($this->session->data['user_token']) && isset($this->session->data['user_id']) && $this->request->get['user_token'] == $this->session->data['user_token']) || ($this->config->get('module_marketplace_status') && isset($this->request->get['product_token']) && isset($this->session->data['product_token']) && $this->request->get['product_token'] == $this->session->data['product_token'])) {
            $product_status_array = $this->db->query("SELECT status,buyer_flag FROM " . DB_PREFIX . "product WHERE product_id = " . (int)$product_id)->row;
            if (isset($product_status_array['status'])) {
                $product_status = $product_status_array['status'];
            }
            if (isset($product_status_array['buyer_flag'])) {
                $product_buyer_flag = $product_status_array['buyer_flag'];
            }
            if (!$product_status) {
                $this->db->query("UPDATE " . DB_PREFIX . "product SET status = '1' WHERE product_id = " . $product_id);
            }
            if (!$product_buyer_flag) {
                $this->db->query("UPDATE " . DB_PREFIX . "product SET buyer_flag = '1' WHERE product_id = " . $product_id);
            }
        }

        $dm_info = null;
        if ($buyer_id) {
            $dm_info = $this->getDelicacyManagementInfoByNoView($product_id, $buyer_id, $check_seller_product['customer_id'] ?? null);
            $sql = " SELECT DISTINCT
	c2c.screenname,cus.status as seller_status,
	p.product_id AS productId,p.price,p.price_display,p.quantity_display,p.status,
	p.sku,p.upc,p.ean,p.jan,p.isbn,p.mpn,p.location,p.quantity,p.image,p.manufacturer_id,p.viewed,p.model,p.tax_class_id,p.date_available,
	p.weight,p.weight_class_id,p.length,p.length_class_id,p.width,p.height,p.points,
	p.weight_kg,p.length_cm,p.width_cm,p.height_cm,
	p.aHref,p.length_class_id,p.subtract,p.minimum,p.date_added,p.date_modified,p.freight,
	ifnull( c2c.self_support, 0 ) AS self_support,
	pd.summary_description,pd.name,pd.description,pd.meta_title,pd.meta_description,pd.meta_keyword,pd.tag,pd.return_warranty_text,
	c2p.seller_price,c2p.customer_id,
	c2p.quantity AS c2pQty,
	b2s.id as canSell,
	p.buyer_flag,p.combo_flag,p.part_flag,p.need_install,p.product_size,
	p.status,
	null as discount,
	m.name AS manufacturer,
	(
		SELECT price FROM oc_product_special ps
		WHERE  ps.product_id = p.product_id AND ps.customer_group_id = $customer_group_id
		AND ( ( ps.date_start = '0000-00-00' OR ps.date_start < NOW( ) ) AND ( ps.date_end = '0000-00-00' OR ps.date_end > NOW( ) ) )
			ORDER BY ps.priority ASC, ps.price ASC  LIMIT 1
	) AS special,
	( SELECT points FROM oc_product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = $customer_group_id ) AS reward,
	( SELECT ss.NAME FROM oc_stock_status ss WHERE ss.stock_status_id = p.stock_status_id AND ss.language_id = $language_id ) AS stock_status,
	( SELECT wcd.unit FROM oc_weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = $language_id ) AS weight_class,
	( SELECT lcd.unit FROM oc_length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = $language_id ) AS length_class,
	( SELECT AVG( rating ) AS total FROM oc_review r1 WHERE r1.product_id = p.product_id AND r1.status = '1'  GROUP BY r1.product_id ) AS rating,
	( SELECT COUNT( * ) AS total  FROM oc_review r2  WHERE r2.product_id = p.product_id  AND r2.status = '1'  GROUP BY r2.product_id ) AS reviews,
	p.sort_order
FROM
	oc_product p
	LEFT JOIN oc_product_description pd ON ( p.product_id = pd.product_id )
	LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
	LEFT JOIN oc_manufacturer m ON ( p.manufacturer_id = m.manufacturer_id )
	LEFT JOIN oc_customerpartner_to_product c2p ON ( c2p.product_id = p.product_id )
	LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )
	LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id )
	LEFT JOIN oc_buyer_to_seller AS b2s ON (b2s.seller_id = c2p.customer_id AND b2s.buyer_id = " . $buyer_id . " AND b2s.buy_status = 1 AND b2s.buyer_control_status = 1 AND b2s.seller_control_status = 1 )
WHERE
	p.product_id = $product_id
	AND c2c.`show` = 1
	AND cus.status = 1
	AND pd.language_id = $language_id
	AND p.status = '1'
	AND p.buyer_flag = '1'
	AND p.date_available <= NOW()
	AND p2s.store_id = $store_id ";
//            $sql = "SELECT DISTINCT *,c2c.screenname,p.product_id as productId,p.aHref,ifnull(c2c.self_support,0) as self_support,pd.summary_description,c2p.seller_price,c2p.quantity as c2pQty,case when c2p.customer_id in (select seller_id  from oc_buyer_to_seller b2s where b2s.buyer_id = " . $buyer_id . " and b2s.buy_status = 1 and b2s.buyer_control_status =1 and b2s.seller_control_status = 1 ) then 1 else 0 end as canSell, pd.name AS name, p.image, m.name AS manufacturer, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special, (SELECT points FROM " . DB_PREFIX . "product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "') AS reward, (SELECT ss.name FROM " . DB_PREFIX . "stock_status ss WHERE ss.stock_status_id = p.stock_status_id AND ss.language_id = '" . (int)$this->config->get('config_language_id') . "') AS stock_status, (SELECT wcd.unit FROM " . DB_PREFIX . "weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS weight_class, (SELECT lcd.unit FROM " . DB_PREFIX . "length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS length_class, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r2 WHERE r2.product_id = p.product_id AND r2.status = '1' GROUP BY r2.product_id) AS reviews, p.sort_order,IFNULL((select current_price from vw_delicacy_management dm where dm.product_id=p.product_id and dm.buyer_id=" . $buyer_id . " and product_display=1 AND dm.expiration_time >= NOW()),p.price) as current_price FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)  LEFT JOIN " . DB_PREFIX . "customerpartner_to_product c2p ON (c2p.product_id = p.product_id )  LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )  LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id ) WHERE p.product_id = '" . (int)$product_id . "' AND c2c.`show` = 1 and cus.status=1 AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.buyer_flag = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";
        } else {
//            $sql = "SELECT DISTINCT *,c2c.screenname,p.product_id as productId,p.aHref,ifnull(c2c.self_support,0) as self_support,0 as canSell,pd.summary_description,c2p.seller_price,c2p.quantity as c2pQty, pd.name AS name, p.image, m.name AS manufacturer, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special, (SELECT points FROM " . DB_PREFIX . "product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "') AS reward, (SELECT ss.name FROM " . DB_PREFIX . "stock_status ss WHERE ss.stock_status_id = p.stock_status_id AND ss.language_id = '" . (int)$this->config->get('config_language_id') . "') AS stock_status, (SELECT wcd.unit FROM " . DB_PREFIX . "weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS weight_class, (SELECT lcd.unit FROM " . DB_PREFIX . "length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS length_class, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r2 WHERE r2.product_id = p.product_id AND r2.status = '1' GROUP BY r2.product_id) AS reviews, p.sort_order FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)  LEFT JOIN " . DB_PREFIX . "customerpartner_to_product c2p ON (c2p.product_id = p.product_id ) LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )  LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id )  WHERE p.product_id = '" . (int)$product_id . "' AND c2c.`show` = 1  and cus.status=1 AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1'  AND p.buyer_flag = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";
            $sql = " SELECT DISTINCT
	c2c.screenname,cus.status as seller_status,
	p.product_id AS productId,p.price,p.price_display,p.quantity_display,p.status,
	p.sku,p.upc,p.ean,p.jan,p.isbn,p.mpn,p.location,p.quantity,p.image,p.manufacturer_id,p.viewed,p.model,p.tax_class_id,p.date_available,
	p.weight,p.weight_class_id,p.length,p.length_class_id,p.width,p.height,p.points,
	p.weight_kg,p.length_cm,p.width_cm,p.height_cm,
	p.aHref,p.length_class_id,p.subtract,p.minimum,p.date_added,p.date_modified,p.freight,
	ifnull( c2c.self_support, 0 ) AS self_support,
	pd.summary_description,pd.name,pd.description,pd.meta_title,pd.meta_description,pd.meta_keyword,pd.tag,pd.return_warranty_text,
	c2p.seller_price,c2p.customer_id,
	c2p.quantity AS c2pQty,
	0 as canSell,
	p.buyer_flag,p.combo_flag,p.part_flag,p.need_install,p.product_size,
	p.status,
	null as discount,
	m.name AS manufacturer,
	(
		SELECT price FROM oc_product_special ps
		WHERE  ps.product_id = p.product_id AND ps.customer_group_id = $customer_group_id
		AND ( ( ps.date_start = '0000-00-00' OR ps.date_start < NOW( ) ) AND ( ps.date_end = '0000-00-00' OR ps.date_end > NOW( ) ) )
			ORDER BY ps.priority ASC, ps.price ASC  LIMIT 1
	) AS special,
	( SELECT points FROM oc_product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = $customer_group_id ) AS reward,
	( SELECT ss.NAME FROM oc_stock_status ss WHERE ss.stock_status_id = p.stock_status_id AND ss.language_id = $language_id ) AS stock_status,
	( SELECT wcd.unit FROM oc_weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = $language_id ) AS weight_class,
	( SELECT lcd.unit FROM oc_length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = $language_id ) AS length_class,
	( SELECT AVG( rating ) AS total FROM oc_review r1 WHERE r1.product_id = p.product_id AND r1.status = '1'  GROUP BY r1.product_id ) AS rating,
	( SELECT COUNT( * ) AS total  FROM oc_review r2  WHERE r2.product_id = p.product_id  AND r2.status = '1'  GROUP BY r2.product_id ) AS reviews,
	p.sort_order
FROM
	oc_product p
	LEFT JOIN oc_product_description pd ON ( p.product_id = pd.product_id )
	LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
	LEFT JOIN oc_manufacturer m ON ( p.manufacturer_id = m.manufacturer_id )
	LEFT JOIN oc_customerpartner_to_product c2p ON ( c2p.product_id = p.product_id )
	LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )
	LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id )
WHERE
	p.product_id = $product_id
	AND c2c.`show` = 1
	AND cus.status = 1
	AND pd.language_id = $language_id
	AND p.status = '1'
	AND p.buyer_flag = '1'
	AND p.date_available <= NOW()
	AND p2s.store_id = $store_id ";
        }
        $query = $this->db->query($sql);
        /**
         * Marketplace Code Starts Here
         */
        if ($this->config->get('module_marketplace_status') && $query->num_rows) {

            if ($check_seller_product) {

                $this->load->model('account/customerpartnerorder');

                if ($this->config->get('marketplace_commission_tax')) {
                    $commission_array = $this->model_account_customerpartnerorder->calculateCommission(array('product_id' => $product_id, 'product_total' => $this->tax->calculate($query->row['price'], $query->row['tax_class_id'], $this->config->get('config_tax'))), $check_seller_product['customer_id']);
                } else {
                    $commission_array = $this->model_account_customerpartnerorder->calculateCommission(array('product_id' => $product_id, 'product_total' => $query->row['price']), $check_seller_product['customer_id']);
                }

                if ($commission_array && isset($commission_array['commission']) && $this->config->get('marketplace_commission_unit_price')) {
                    $commission_amount = $commission_array['commission'];
                }
                $check_seller_status = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_customer WHERE customer_id = '" . (int)$check_seller_product['customer_id'] . "' AND is_partner = '1'")->row;

                if ($this->config->get('module_marketplace_status') && !$this->config->get('marketplace_sellerproductshow') && !$check_seller_status) {

                    return false;
                }
            }

        }

        if (!$product_status) {
            $this->db->query("UPDATE " . DB_PREFIX . "product SET status = '0' WHERE product_id = " . $product_id);
        }
        if (!$product_buyer_flag) {
            $this->db->query("UPDATE " . DB_PREFIX . "product SET buyer_flag = '0' WHERE product_id = " . $product_id);
        }
        /**
         * Marketplace Code Ends Here
         */

        //add by xxli
        $resultTotal = $this->db->query("select ifnull(sum(c2o.quantity),0) as totalSales from oc_customerpartner_to_order c2o left join oc_order ord on ord.order_id = c2o.order_id where ord.order_status_id = ".OcOrderStatus::COMPLETED." and c2o.product_id = " . $product_id)->row;
        if ($resultTotal['totalSales'] > 99999) {
            $totalSale = '99999+';
        } else {
            $totalSale = $resultTotal['totalSales'];
        }
        $result30 = $this->db->query("select ifnull(sum(c2o.quantity),0) as 30Day from oc_customerpartner_to_order c2o left join oc_order ord on ord.order_id = c2o.order_id where ord.order_status_id = ".OcOrderStatus::COMPLETED." and ord.date_added >=DATE_SUB(CURDATE(),INTERVAL 30 DAY) and c2o.product_id = " . $product_id)->row;
        if ($result30['30Day'] > 999) {
            $day30Sale = '999+';
        } else {
            $day30Sale = $result30['30Day'];
        }
        if (isset($query->row['viewed'])) {
            if ($query->row['viewed'] > 99999) {
                $pageView = '99999+';
            } else {
                $pageView = $query->row['viewed'];
            }
        }
        //end by xxli

        if ($query->num_rows) {

            /**
             * product 的当前价格
             * 如果 有精细化价格，则取该值(前提是该 buyer 对该 product 可见)。
             */
            $price = $query->row['price'];
            if ($dm_info && $dm_info['product_display'] && isset($dm_info['current_price'])) {
                $price = $dm_info['current_price'];
            }

            return array(
                'screenname' => $query->row['screenname'],
                'freight' => $query->row['freight'],   //运费
                'original_price' => $query->row['price'], //未更改过的价格
                'dm_info' => $dm_info,
                'totalSale' => $totalSale,
                '30Day' => $day30Sale,
                'pageView' => $pageView,
                'customer_id' => $query->row['customer_id'],
                'seller_status' => $query->row['seller_status'],
                'self_support' => $query->row['self_support'],
                'summary_description' => $query->row['summary_description'],
                'price_display' => $query->row['price_display'],
                'quantity_display' => $query->row['quantity_display'],
                'aHref' => $query->row['aHref'],
                'canSell' => $query->row['canSell'] ? 1 : 0,    // bts 是否建立关联
                'seller_price' => $query->row['seller_price'],
                'c2pQty' => $query->row['c2pQty'],
                'product_id' => $query->row['productId'],
                'name' => $query->row['name'],
                'description' => $query->row['description'],
                'meta_title' => $query->row['meta_title'],
                'meta_description' => $query->row['meta_description'],
                'meta_keyword' => $query->row['meta_keyword'],
                'tag' => $query->row['tag'],
                'return_warranty_text' => $query->row['return_warranty_text'],
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

                'price' => ($query->row['discount'] ? $query->row['discount'] : $price) + $commission_amount,

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
                'weight_kg' => $query->row['weight_kg'],
                'length_cm' => $query->row['length_cm'],
                'width_cm' => $query->row['width_cm'],
                'height_cm' => $query->row['height_cm'],
                'subtract' => $query->row['subtract'],
                'rating' => round($query->row['rating']),
                'reviews' => $query->row['reviews'] ? $query->row['reviews'] : 0,
                'minimum' => $query->row['minimum'],
                'sort_order' => $query->row['sort_order'],
                'status' => $query->row['status'],
                'date_added' => $query->row['date_added'],
                'date_modified' => $query->row['date_modified'],

                'commission_amount' => $commission_amount,

                'viewed' => $query->row['viewed'],
                'dm_display' => empty($dm_info) ? 1 : ($dm_info['product_display'] ?? 1),
                'buyer_flag' => $query->row['buyer_flag'],
                'combo_flag' => $query->row['combo_flag'],
                'part_flag' => $query->row['part_flag'],
                'need_install' => $query->row['need_install'],
                'product_size' => $query->row['product_size'],
            );
        } else {
            return false;
        }
    }


    /**
     * 只用于产品详情页 产品详情
     * @param int $product_id
     * @param null $buyer_id
     * @param int $is_available
     * @return array|bool
     * @throws Exception
     */
    public function getProductByDetails($product_id, $buyer_id = null, $is_available = 1)
    {

        $product_status = 1;
        $product_buyer_flag = 1;
        $commission_amount = 0;
        $return_rate_standard = 10; //产品退返品率标准
        $customer_group_id = (int)$this->config->get('config_customer_group_id');
        $language_id = (int)$this->config->get('config_language_id');
        $store_id = (int)$this->config->get('config_store_id');

        $check_seller_product = $this->db->query("SELECT customer_id FROM oc_customerpartner_to_product WHERE product_id = " . (int)$product_id . " limit 1 ")->row;
        //后台进入会进行status 和 buyer_flag 更新
        if (
            (
                $this->config->get('module_marketplace_status')
                && isset($this->request->get['user_token'])
                && isset($this->session->data['user_token'])
                && isset($this->session->data['user_id'])
                && $this->request->get['user_token'] == $this->session->data['user_token']
            )
            ||
            (
                $this->config->get('module_marketplace_status')
                && isset($this->request->get['product_token'])
                && isset($this->session->data['product_token'])
                && $this->request->get['product_token'] == $this->session->data['product_token']
            )
        ) {
            $product_status_array = $this->db->query("SELECT status,buyer_flag FROM " . DB_PREFIX . "product WHERE product_id = " . (int)$product_id)->row;
            if (isset($product_status_array['status'])) {
                $product_status = $product_status_array['status'];
            }
            if (isset($product_status_array['buyer_flag'])) {
                $product_buyer_flag = $product_status_array['buyer_flag'];
            }
            if (!$product_status) {
                $this->db->query("UPDATE " . DB_PREFIX . "product SET status = '1' WHERE product_id = " . $product_id);
            }
            if (!$product_buyer_flag) {
                $this->db->query("UPDATE " . DB_PREFIX . "product SET buyer_flag = '1' WHERE product_id = " . $product_id);
            }
        }

        $dm_info = null;
        if ($buyer_id) {
            $dm_info = $this->getDelicacyManagementInfoByNoView($product_id, $buyer_id, $check_seller_product['customer_id'] ?? null);
            //AND b2s.buy_status = 1 AND b2s.buyer_control_status = 1 AND b2s.seller_control_status = 1
            $sql = "
    SELECT DISTINCT
        c2c.screenname,c2c.customer_id as seller_id,
        cus.customer_group_id,cus.status AS seller_status,cus.accounting_type AS seller_accounting_type,cus.country_id,cus.email as seller_email,
        TRIM(CONCAT(cus.firstname, cus.lastname)) AS store_code,
        p.product_id AS productId,p.price,p.price AS rawPrice,p.price_display,p.quantity_display,p.status,
        p.sku,p.upc,p.ean,p.jan,p.isbn,p.mpn,p.location,p.quantity,p.image,p.manufacturer_id,p.viewed,p.model,p.tax_class_id,p.date_available,
        p.weight,p.weight_class_id,p.length,p.length_class_id,p.width,p.height,p.points,
        p.aHref,p.length_class_id,p.subtract,p.minimum,p.date_added,p.date_modified,p.freight,p.package_fee,p.product_type,p.danger_flag,
        p.is_deleted,
        ifnull( c2c.self_support, 0 ) AS self_support,
        pd.summary_description,pd.name,pd.description,pd.meta_title,pd.meta_description,pd.meta_keyword,pd.tag,pd.return_warranty_text,
        c2p.seller_price,c2p.customer_id,
        c2p.quantity AS c2pQty,
        p.combo_flag, p.buyer_flag, p.part_flag,
        p.weight_kg, p.length_cm, p.width_cm, p.height_cm,
        p.need_install, p.product_size,p.non_sellable_on,
        CASE
            when b2s.seller_id is not null
            and b2s.buy_status = 1
            and b2s.buyer_control_status = 1
            and b2s.seller_control_status = 1
            Then
                b2s.id
            ELSE
                0
            END  as canSell,

        null as discount,
        m.name AS manufacturer,
        p.downloaded AS download_cnt,
        (
            SELECT price FROM oc_product_special ps
            WHERE  ps.product_id = p.product_id AND ps.customer_group_id = $customer_group_id
            AND ( ( ps.date_start = '0000-00-00' OR ps.date_start < NOW( ) ) AND ( ps.date_end = '0000-00-00' OR ps.date_end > NOW( ) ) )
                ORDER BY ps.priority ASC, ps.price ASC  LIMIT 1
        ) AS special,
        ( SELECT points FROM oc_product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = $customer_group_id ) AS reward,
        ( SELECT ss.NAME FROM oc_stock_status ss WHERE ss.stock_status_id = p.stock_status_id AND ss.language_id = $language_id ) AS stock_status,
        ( SELECT wcd.unit FROM oc_weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = $language_id ) AS weight_class,
        ( SELECT lcd.unit FROM oc_length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = $language_id ) AS length_class,
        ( SELECT AVG( rating ) AS total FROM oc_review r1 WHERE r1.product_id = p.product_id AND r1.status = '1'  GROUP BY r1.product_id ) AS rating,
        ( SELECT COUNT( * ) AS total  FROM oc_review r2  WHERE r2.product_id = p.product_id  AND r2.status = '1'  GROUP BY r2.product_id ) AS reviews,
        p.sort_order,
        CASE
            WHEN ro.receive_order_id IS NOT NULL
            AND DATEDIFF(Now(),p.date_added) <= " . NEW_ARRIVAL_DAY . "
            THEN
            1
            ELSE
            0
        END AS is_new
    FROM
        oc_product p
        LEFT JOIN oc_product_description pd ON ( p.product_id = pd.product_id )
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_manufacturer m ON ( p.manufacturer_id = m.manufacturer_id )
        LEFT JOIN oc_customerpartner_to_product c2p ON ( c2p.product_id = p.product_id )
        LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )
        LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id )
        LEFT JOIN oc_buyer_to_seller AS b2s ON (b2s.seller_id = c2p.customer_id AND b2s.buyer_id = " . $buyer_id . " )
        LEFT JOIN tb_sys_receipts_order_detail AS rod ON rod.product_id = p.product_id
        LEFT JOIN tb_sys_receipts_order AS ro ON ro.receive_order_id = rod.receive_order_id
            AND ro.`expected_date` IS NOT NULL
            AND rod.`expected_qty` IS NOT NULL
            AND ro.`expected_date` > NOW()
            AND ro.`status` = " . ReceiptOrderStatus::TO_BE_RECEIVED . "
    WHERE
        p.product_id = $product_id
        AND c2c.`show` = 1
        AND pd.language_id = $language_id
        AND p.date_available <= NOW()
        AND p2s.store_id = $store_id ";
            //139 无效产品只能通过链接进入产品详情，不能通过搜索从缩略图进入
            if ($is_available) {
                $sql .= " AND cus.status = 1 AND p.status = '1' AND p.buyer_flag = '1'";
            }
            //            $sql = "SELECT DISTINCT *,c2c.screenname,p.product_id as productId,p.aHref,ifnull(c2c.self_support,0) as self_support,pd.summary_description,c2p.seller_price,c2p.quantity as c2pQty,case when c2p.customer_id in (select seller_id  from oc_buyer_to_seller b2s where b2s.buyer_id = " . $buyer_id . " and b2s.buy_status = 1 and b2s.buyer_control_status =1 and b2s.seller_control_status = 1 ) then 1 else 0 end as canSell, pd.name AS name, p.image, m.name AS manufacturer, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special, (SELECT points FROM " . DB_PREFIX . "product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "') AS reward, (SELECT ss.name FROM " . DB_PREFIX . "stock_status ss WHERE ss.stock_status_id = p.stock_status_id AND ss.language_id = '" . (int)$this->config->get('config_language_id') . "') AS stock_status, (SELECT wcd.unit FROM " . DB_PREFIX . "weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS weight_class, (SELECT lcd.unit FROM " . DB_PREFIX . "length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS length_class, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r2 WHERE r2.product_id = p.product_id AND r2.status = '1' GROUP BY r2.product_id) AS reviews, p.sort_order,IFNULL((select current_price from vw_delicacy_management dm where dm.product_id=p.product_id and dm.buyer_id=" . $buyer_id . " and product_display=1 AND dm.expiration_time >= NOW()),p.price) as current_price FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)  LEFT JOIN " . DB_PREFIX . "customerpartner_to_product c2p ON (c2p.product_id = p.product_id )  LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )  LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id ) WHERE p.product_id = '" . (int)$product_id . "' AND c2c.`show` = 1 and cus.status=1 AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.buyer_flag = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";
        } else {
            //            $sql = "SELECT DISTINCT *,c2c.screenname,p.product_id as productId,p.aHref,ifnull(c2c.self_support,0) as self_support,0 as canSell,pd.summary_description,c2p.seller_price,c2p.quantity as c2pQty, pd.name AS name, p.image, m.name AS manufacturer, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special, (SELECT points FROM " . DB_PREFIX . "product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "') AS reward, (SELECT ss.name FROM " . DB_PREFIX . "stock_status ss WHERE ss.stock_status_id = p.stock_status_id AND ss.language_id = '" . (int)$this->config->get('config_language_id') . "') AS stock_status, (SELECT wcd.unit FROM " . DB_PREFIX . "weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS weight_class, (SELECT lcd.unit FROM " . DB_PREFIX . "length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS length_class, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r2 WHERE r2.product_id = p.product_id AND r2.status = '1' GROUP BY r2.product_id) AS reviews, p.sort_order FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)  LEFT JOIN " . DB_PREFIX . "customerpartner_to_product c2p ON (c2p.product_id = p.product_id ) LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )  LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id )  WHERE p.product_id = '" . (int)$product_id . "' AND c2c.`show` = 1  and cus.status=1 AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1'  AND p.buyer_flag = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";
            $sql = "
    SELECT DISTINCT
        c2c.screenname,c2c.customer_id as seller_id,
        cus.customer_group_id,cus.status AS seller_status,cus.accounting_type AS seller_accounting_type,cus.country_id,cus.email as seller_email,
        TRIM(CONCAT(cus.firstname, cus.lastname)) AS store_code,
        p.product_id AS productId,p.price,p.price AS rawPrice,p.price_display,p.quantity_display,p.status,
        p.sku,p.upc,p.ean,p.jan,p.isbn,p.mpn,p.location,p.quantity,p.image,p.manufacturer_id,p.viewed,p.model,p.tax_class_id,p.date_available,
        p.weight,p.weight_class_id,p.length,p.length_class_id,p.width,p.height,p.points,
        p.aHref,p.length_class_id,p.subtract,p.minimum,p.date_added,p.date_modified,p.freight,p.package_fee,p.product_type,p.danger_flag,
        p.is_deleted,
        ifnull( c2c.self_support, 0 ) AS self_support,
        pd.summary_description,pd.name,pd.description,pd.meta_title,pd.meta_description,pd.meta_keyword,pd.tag,pd.return_warranty_text,
        c2p.seller_price,c2p.customer_id,
        c2p.quantity AS c2pQty,
        p.combo_flag, p.buyer_flag, p.part_flag,
        p.weight_kg, p.length_cm, p.width_cm, p.height_cm,
        p.need_install, p.product_size,p.non_sellable_on,
        0 as canSell,
        null as discount,
        m.name AS manufacturer,
        p.downloaded AS download_cnt,
        (
            SELECT price FROM oc_product_special ps
            WHERE  ps.product_id = p.product_id AND ps.customer_group_id = $customer_group_id
            AND ( ( ps.date_start = '0000-00-00' OR ps.date_start < NOW( ) ) AND ( ps.date_end = '0000-00-00' OR ps.date_end > NOW( ) ) )
                ORDER BY ps.priority ASC, ps.price ASC  LIMIT 1
        ) AS special,
        ( SELECT points FROM oc_product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = $customer_group_id ) AS reward,
        ( SELECT ss.NAME FROM oc_stock_status ss WHERE ss.stock_status_id = p.stock_status_id AND ss.language_id = $language_id ) AS stock_status,
        ( SELECT wcd.unit FROM oc_weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = $language_id ) AS weight_class,
        ( SELECT lcd.unit FROM oc_length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = $language_id ) AS length_class,
        ( SELECT AVG( rating ) AS total FROM oc_review r1 WHERE r1.product_id = p.product_id AND r1.status = '1'  GROUP BY r1.product_id ) AS rating,
        ( SELECT COUNT( * ) AS total  FROM oc_review r2  WHERE r2.product_id = p.product_id  AND r2.status = '1'  GROUP BY r2.product_id ) AS reviews,
        p.sort_order,
        CASE
            WHEN ro.receive_order_id IS NOT NULL
            AND DATEDIFF(Now(),p.date_added) <= " . NEW_ARRIVAL_DAY . "
            THEN
            1
            ELSE
            0
        END AS is_new
    FROM
        oc_product p
        LEFT JOIN oc_product_description pd ON ( p.product_id = pd.product_id )
        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
        LEFT JOIN oc_manufacturer m ON ( p.manufacturer_id = m.manufacturer_id )
        LEFT JOIN oc_customerpartner_to_product c2p ON ( c2p.product_id = p.product_id )
        LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )
        LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id )
        LEFT JOIN tb_sys_receipts_order_detail AS rod ON rod.product_id = p.product_id
        LEFT JOIN tb_sys_receipts_order AS ro ON ro.receive_order_id = rod.receive_order_id
            AND ro.`expected_date` IS NOT NULL
            AND rod.`expected_qty` IS NOT NULL
            AND ro.`expected_date` > NOW()
            AND ro.`status` = " . ReceiptOrderStatus::TO_BE_RECEIVED . "
    WHERE
        p.product_id = $product_id
        AND c2c.`show` = 1
        AND pd.language_id = $language_id
        AND p.date_available <= NOW()
        AND p2s.store_id = $store_id ";
            ///139 无效产品只能通过链接进入产品详情，不能通过搜索从缩略图进入
            if ($is_available) {
                $sql .= " AND cus.status = 1 AND p.status = '1' AND p.buyer_flag = '1'";
            }
        }

        $query = $this->db->query($sql);
        /**
         * Marketplace Code Starts Here
         */
        if ($this->config->get('module_marketplace_status') && $query->num_rows) {

            if ($check_seller_product) {

                $this->load->model('account/customerpartnerorder');

                if ($this->config->get('marketplace_commission_tax')) {
                    $commission_array = $this->model_account_customerpartnerorder->calculateCommission(array('product_id' => $product_id, 'product_total' => $this->tax->calculate($query->row['price'], $query->row['tax_class_id'], $this->config->get('config_tax'))), $check_seller_product['customer_id']);
                } else {
                    $commission_array = $this->model_account_customerpartnerorder->calculateCommission(array('product_id' => $product_id, 'product_total' => $query->row['price']), $check_seller_product['customer_id']);
                }

                if ($commission_array && isset($commission_array['commission']) && $this->config->get('marketplace_commission_unit_price')) {
                    $commission_amount = $commission_array['commission'];
                }
                $check_seller_status = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_customer WHERE customer_id = '" . (int)$check_seller_product['customer_id'] . "' AND is_partner = '1'")->row;

                if ($this->config->get('module_marketplace_status') && !$this->config->get('marketplace_sellerproductshow') && !$check_seller_status) {

                    return false;
                }
            }

        }

        if (!$product_status) {
            $this->db->query("UPDATE " . DB_PREFIX . "product SET status = '0' WHERE product_id = " . $product_id);
        }
        if (!$product_buyer_flag) {
            $this->db->query("UPDATE " . DB_PREFIX . "product SET buyer_flag = '0' WHERE product_id = " . $product_id);
        }
        /**
         * Marketplace Code Ends Here
         */

        //$param_info_query = $this->getProductParamTogetherByBuilder($product_id);
        //$param_info_query = obj2array($param_info_query);
        //$day30Sale = $param_info_query[0]['data'];
        ////Return Rate 14320 商品列表缩略图显示优化
        //$param_info_sql = $this->getProductParamTogether($product_id);
        //$param_info_query = $this->db->query($param_info_sql)->rows;
        //$day30Sale = $param_info_query[0]['data'];
        //if($param_info_query[1]['data'] == 0 || $param_info_query[2]['data'] == 0){
        //    $return_rate = 0;
        //}else{
        //    $return_rate = sprintf('%.2f',$param_info_query[2]['data']*100/$param_info_query[1]['data']);
        //}
        $this->load->model('extension/module/product_show');
        $returnData = $this->model_extension_module_product_show->getProductReturnRate($product_id);//商品退返率
        $return_rate = $returnData['return_rate'];
        $return_rate_str = $returnData['return_rate_str'];
        $allDaysSale = $returnData['purchase_num'];
        $day30Sale = $this->thirtyDaysSale($product_id);
        //$allDaysSale = $this->allDaysSale($product_id);
        if ($query->num_rows) {
            /**
             * product 的当前价格
             * 如果 有精细化价格，则取该值(前提是该 buyer 对该 product 可见)。
             */
            $price = $query->row['price'];
            $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
            $exists = $this->orm->table('oc_rebate_agreement as a')
                ->join('oc_rebate_agreement_item as i', 'i.agreement_id', '=', 'a.id')
                ->join('oc_delicacy_management as dm', [['a.buyer_id', '=', 'dm.buyer_id'], ['a.seller_id', '=', 'dm.seller_id'], ['i.product_id', '=', 'dm.product_id'], ['a.effect_time', '=', 'dm.effective_time']])
                ->where([
                    ['a.status', '=', 3],
                    ['a.buyer_id', '=', $buyer_id],
                    ['dm.product_id', '=', $product_id],
                    ['a.expire_time', '>', date('Y-m-d H:i:s')],
                    ['dm.product_display', '=', 1]
                ])
                ->exists();

            /**
             * 打包费添加 附件打包费
             *
             * @since 101457
             */
            $package_fee = $this->getNewPackageFee($product_id, $isCollectionFromDomicile);

            $isDelicacyPrice = 0;
            if ($dm_info && $dm_info['product_display'] && isset($dm_info['current_price']) && !$exists) {
                $price = $dm_info['current_price'];
                $isDelicacyPrice = 1;
            } else {
                $price = round($price, 2) > 0 ? round($price, 2) : 0;
            }

            //14103 所有上过架的产品都可以被检索到，如果产品已失效，则在检索结果页和商品详情页标注出商品无效的原因
            $unsee = 0;
            if ($query->row['buyer_flag'] == 0) {
                $unsee = 1;
            } elseif ($query->row['status'] != 1) {
                $unsee = 1;
            } elseif ($dm_info && $dm_info['product_display'] == 0) {
                $unsee = 1;
            } elseif ($query->row['seller_status'] == 0) {
                $unsee = 1;
            }

            return array(
                'screenname' => $query->row['screenname'],
                'store_code' => $query->row['store_code'],
                'rebate_exists' => $exists,
                'freight' => $query->row['freight'],
                'package_fee' => $package_fee,
                'seller_id' => $query->row['seller_id'],
                'group_id' => $query->row['customer_group_id'],
                'seller_status' => $query->row['seller_status'],
                'seller_accounting_type' => $query->row['seller_accounting_type'],
                'seller_email'=>$query->row['seller_email'],
                'country_id' => $query->row['country_id'],
                'product_type' => $query->row['product_type'],
                'unsee' => $unsee,
                //'totalSale' => $totalSale,
                '30Day' => $day30Sale,
                'all_days_sale' => $allDaysSale,
                'return_rate' => $return_rate,
                'return_rate_str' => $return_rate_str,
                //'return_approval_rate' => $return_approval_rate,
                //'qa_rate'     => $qa_rate,
                //'pageView' => $pageView,
                'customer_id' => $query->row['customer_id'],
                'self_support' => $query->row['self_support'],
                'summary_description' => $query->row['summary_description'],
                'price_display' => $query->row['price_display'],
                'quantity_display' => $query->row['quantity_display'],
                'aHref' => $query->row['aHref'],
                'canSell' => $query->row['canSell'] ? 1 : 0,    // bts 是否建立关联
                'seller_price' => $query->row['seller_price'],
                'c2pQty' => $query->row['c2pQty'],
                'product_id' => $query->row['productId'],
                'name' => $query->row['name'],
                'description' => $query->row['description'],
                'meta_title' => $query->row['meta_title'],
                'meta_description' => $query->row['meta_description'],
                'meta_keyword' => $query->row['meta_keyword'],
                'tag' => $query->row['tag'],
                'return_warranty_text' => $query->row['return_warranty_text'],
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
                'rawPrice' => $query->row['rawPrice'],
                'price' => ($query->row['discount'] ? $query->row['discount'] : $price) + $commission_amount,
                //'max_price' => ($query->row['discount'] ? $query->row['discount'] : $max_price)  + $commission_amount,
                //'min_price' => ($query->row['discount'] ? $query->row['discount'] : $min_price)  + $commission_amount,
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
                'is_deleted' => $query->row['is_deleted'],
                'date_added' => $query->row['date_added'],
                'date_modified' => $query->row['date_modified'],
                'commission_amount' => $commission_amount,
                'viewed' => $query->row['viewed'],
                'dm_display' => empty($dm_info) ? 1 : ($dm_info['product_display'] ?? 1),
                'combo_flag' => $query->row['combo_flag'],
                'buyer_flag' => $query->row['buyer_flag'],
                'part_flag' => $query->row['part_flag'],
                'weight_kg' => $query->row['weight_kg'],
                'length_cm' => $query->row['length_cm'],
                'width_cm' => $query->row['width_cm'],
                'height_cm' => $query->row['height_cm'],
                'need_install' => $query->row['need_install'],
                'product_size' => $query->row['product_size'],
                'download_cnt' => $query->row['download_cnt'],
                'is_new' => $query->row['is_new'],
                'non_sellable_on' => $query->row['non_sellable_on'],
                'is_delicacy_price' => $isDelicacyPrice,
                'danger_flag' => $query->row['danger_flag'],
            );
        } else {
            return false;
        }
    }

    /**
     * [getHistoryHighestPrice description]
     * @param int $product_id
     * @return array
     */
    public function getHistoryHighestPrice($product_id)
    {

        $res = $this->orm->table(DB_PREFIX . 'order_product as op')
            ->leftJoin(DB_PREFIX . 'product_quote as pq', [['pq.product_id', '=', 'op.product_id'], ['pq.order_id', '=', 'op.order_id']])
            ->selectRaw("case when pq.price is not null then pq.price when op.service_fee_per != '0.00' then round(op.price + op.service_fee_per,2) else
            op.price end as price_h")->where('op.product_id', $product_id)->orderBy('price_h', 'desc')->limit(1)->first();
        if ($res) {
            return $res->price_h;
        }
        return false;
    }

    /**
     * [getHistoryLowestPrice description]
     * @param int $product_id
     * @return array
     */
    public function getHistoryLowestPrice($product_id)
    {

        $res = $this->orm->table(DB_PREFIX . 'order_product as op')
            ->leftJoin(DB_PREFIX . 'product_quote as pq', [['pq.product_id', '=', 'op.product_id'], ['pq.order_id', '=', 'op.order_id']])
            ->selectRaw("case when pq.price is not null then pq.price when op.service_fee_per != '0.00' then round(op.price + op.service_fee_per,2) else
            op.price end as price_l")->where('op.product_id', $product_id)->orderBy('price_l', 'asc')->limit(1)->first();
        if ($res) {
            return $res->price_l;
        }
        return false;
    }

    /**
     * @param int $product_id rma计算公式已变化，此方法失效
     * @return array
     */
    public function getProductParamTogetherByBuilder($product_id)
    {
        $map = [
            ['product_id', '=', $product_id],
            ['date_added', '>=', date('Y-m-d H:i:s', time() - 86400 * 30)],
            //['order_product_status','=',5], //30天售卖数
        ];

        $objQuery2 = $this->orm->table(DB_PREFIX . 'order_product')
            ->where('product_id', $product_id)
            ->selectRaw('ifnull(count(distinct order_id),0) as data,product_id');
        $objQuery3 = $this->orm->table(DB_PREFIX . 'yzc_rma_order_product as op')
            ->leftJoin(DB_PREFIX . 'yzc_rma_order as ro', 'ro.id', '=', 'op.rma_id')
            ->where('op.product_id', $product_id)
            ->selectRaw('ifnull(count(distinct ro.order_id),0) as data,op.product_id');
        return $this->orm->table('oc_customerpartner_to_order')
            ->where($map)
            ->whereIn('order_product_status', [5, 13])
            ->selectRaw('ifnull(sum(quantity),0) as data,product_id')
            ->unionAll($objQuery2)
            ->unionAll($objQuery3)
            ->get()
            ->toArray();
    }

    /**
     * [qaRate description]
     * @return array
     */
    public function qaRate($seller_id)
    {
        //系统邮箱配置

        //加索引email
        $tmp = $this->orm->table(DB_PREFIX . 'wk_communication_message as m')->
        leftjoin(DB_PREFIX . 'customer as c', 'c.email', '=', 'm.message_from')->
        where('c.customer_id', '=', $seller_id)->selectRaw('group_concat(m.message_id) as message_str')
            ->first();

        if ($tmp->message_str == '') {
            $this->cache->set($this->customer->getId() . '_qa_rate_' . $seller_id, 0);
            return 0;
        } else {
            $res = $this->orm->table(DB_PREFIX . 'wk_communication_thread')
                ->whereIn('message_id', explode(',', $tmp->message_str))
                ->selectRaw('count(distinct parent_message_id) as t_m')->first()->t_m;
            //buyer 发给店铺的不包含需要回复的
            $systemEmail = configDB('system_email');
            $buyer_amount = $this->orm->table(DB_PREFIX . 'wk_communication_message as m')
                ->leftjoin(DB_PREFIX . 'customer as c', 'c.email', '=', 'm.message_to')
                ->where('c.customer_id', '=', $seller_id)
                ->when(!is_null($systemEmail), function ($q) use ($systemEmail) {
                    $q->where('m.message_to', '!=', $systemEmail);
                })
                ->count();
            if ($buyer_amount == 0) {
                $this->cache->set($this->customer->getId() . '_qa_rate_' . $seller_id, 0);
                return 0;
            } else {
                if ($buyer_amount == 0) {
                    $this->cache->set($this->customer->getId() . '_qa_rate_' . $seller_id, 0);
                    return 0;
                } else {
                    $this->cache->set($this->customer->getId() . '_qa_rate_' . $seller_id, sprintf('%.2f', $res * 100 / $buyer_amount));
                    return sprintf('%.2f', $res * 100 / $buyer_amount);
                }
            }

        }

    }

    /**
     * [returnApprovalRate description]
     * @param int $seller_id
     * @return int|string
     */
    public function returnApprovalRate($seller_id)
    {

        //加索引seller_id
        $res = $this->orm->table(DB_PREFIX . 'yzc_rma_order as ro')->where('ro.seller_id', $seller_id)->count();
        if ($res == 0) {
            //$this->cache->set($this->customer->getId().'_return_approval_rate_'.$seller_id,0);
            return 0;
        } else {
            //获取
            $approval = $this->orm->table(DB_PREFIX . 'yzc_rma_order_product as op')->
            leftJoin(DB_PREFIX . 'yzc_rma_order as ro', 'ro.id', '=', 'op.rma_id')
                ->where('ro.seller_id', $seller_id)->where('ro.seller_status', 2)->where(function ($query) {
                    //rma_type RMA类型 1:仅重发;2:仅退款;3:即退款又重发
                    //status_refund 返金状态 0:初始状态;1:同意;2:拒绝
                    //status_reshipment 重发状态 0:初始;1:同意;2:拒绝
                    $query->where([['rma_type', '=', 3], ['status_refund', '=', 1], ['status_reshipment', '=', 1]])
                        ->orWhere([['rma_type', '=', 2], ['status_refund', '=', 1]])
                        ->orWhere([['rma_type', '=', 1], ['status_reshipment', '=', 1]]);
                })->count();
            //$this->cache->set($this->customer->getId().'_return_approval_rate_'.$seller_id,sprintf('%.2f',$approval*100/$res));
            return sprintf('%.2f', $approval * 100 / $res);
        }


    }

    /**
     * [getProducts description] 更改getProducts方法务必更改getProductsBySearch(),getProductsBySearch()，包含下架产品
     * @param array $data
     * @param null $customFields
     * @param int $isPartner
     * @return array
     * @throws Exception
     */
    public function getProducts($data = array(), $customFields = null, $isPartner = 0)
    {
        if ($customFields) {
            $column = 'pgl.id,';
            $case = ' WHEN pgl.id is not null THEN
                    1';
        } else {
            $column = '';
            $case = '';
        }

        //非登陆的游客
        $sql = "SELECT p.product_id,
                ro.receive_order_id,
                DATEDIFF(Now(),p.date_added) as date_df,
                p.part_flag,
                p.quantity," .
            $column . "
                case " . $case;
        if ($customFields) {
            $sql .= " WHEN dm.product_display = 0 THEN 1  WHEN dm.expiration_time is not null and dm.expiration_time < NOW() THEN 0 ";
        }
        $sql .= "WHEN p.status = 0 THEN
                    1
                    WHEN p.buyer_flag = 0 THEN
                    1
                    ELSE
                        0
                    END  as unsee,
                CASE
                    WHEN c.customer_group_id = 2 THEN
                        0
                    WHEN c.customer_group_id = 3 THEN
                        0
                    WHEN c.customer_group_id IN (17, 18, 19, 20) THEN
                        1
                    ELSE
                        0
                    END  as   customer_group_sort,
                CASE
                when b2s.seller_id is not null
                and b2s.buy_status = 1
                and b2s.buyer_control_status = 1
                and b2s.seller_control_status = 1
                Then
                    0
                ELSE
                    1
                END as no_association,
                CASE
                WHEN rod.receive_order_id IS NOT NULL
                AND DATEDIFF(Now(),p.date_added) <= " . NEW_ARRIVAL_DAY . "
                THEN
                1
                ELSE
                0
                END AS is_new,

(SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating,
(SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount,
(SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special";

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";
            } else {
                $sql .= " FROM " . DB_PREFIX . "product_to_category p2c";
            }

            if (!empty($data['filter_filter'])) {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product_filter pf ON (p2c.product_id = pf.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (pf.product_id = p.product_id)";
            } else {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
            }
        } else {
            $sql .= " FROM " . DB_PREFIX . "product p";
        }

        $filterArr = [
            'min_price',
            'max_price',
            'min_quantity',
            'max_quantity'
        ];

        $flag = false;
        foreach ($filterArr as $f) {
            if (isset($data[$f]) && (!empty($data[$f]) or (int)$data[$f] === 0)) {
                $flag = true;
            }
        }

        //13739 当Buyer通过品类去筛选产品时，和Buyer建立关联关系的产品排在前面
        if ($flag == false) {
            $sql .= ' LEFT JOIN ' . DB_PREFIX . 'customerpartner_to_product as c2p ON p.product_id = c2p.product_id';
            $sql .= ' LEFT JOIN ' . DB_PREFIX . 'customer as c on c.customer_id = c2p.customer_id';
            if ($customFields != null) {
                $str = 'and b2s.buyer_id =' . $customFields;
            } else {
                $str = ' ';
            }
            $sql .= ' LEFT JOIN ' . DB_PREFIX . 'buyer_to_seller as b2s ON b2s.seller_id = c2p.customer_id ' . $str . ' and  b2s.buy_status = 1 and b2s.buyer_control_status = 1 and b2s.seller_control_status = 1';
        } else {
            $sql .= ' JOIN ' . DB_PREFIX . 'customerpartner_to_product as c2p ON p.product_id = c2p.product_id';
            $sql .= ' JOIN ' . DB_PREFIX . 'buyer_to_seller as b2s ON b2s.seller_id = c2p.customer_id';
        }

        // 精细化管理(只有buyer)
        if ($this->customer->isLogged()) {
            $sql .= ' LEFT JOIN oc_delicacy_management as dm ON (dm.buyer_id = ' . $this->customer->getId() . ' and dm.product_id = p.product_id and dm.expiration_time>NOW()) ';    // Add by Lester.you 2019-6-6 11:48:21 价格筛选（discount&精细化管理）
            //由于精细化二期进行的更改，所有获取产品是否精细化，以及排序需要产生变化
            $sql .= ' LEFT JOIN oc_customerpartner_buyer_group_link as bgl on bgl.buyer_id = b2s.buyer_id and bgl.status=1 and bgl.seller_id = b2s.seller_id and bgl.buyer_id = ' . $customFields . '
                      LEFT JOIN oc_delicacy_management_group as dmg on bgl.buyer_group_id = dmg.buyer_group_id AND dmg.status =1
                      LEFT JOIN oc_customerpartner_product_group_link as pgl on pgl.product_group_id = dmg.product_group_id and pgl.status=1 and pgl.product_id = p.product_id';
        }

        $sql .= ' LEFT JOIN tb_sys_receipts_order_detail AS rod ON rod.product_id = p.product_id
                LEFT JOIN tb_sys_receipts_order AS ro ON ro.receive_order_id = rod.receive_order_id
                AND ro.`expected_date` IS NOT NULL
                AND rod.`expected_qty` IS NOT NULL
                AND ro.`expected_date` > NOW()
                AND ro.`status` = ' . eceiptOrderStatus::TO_BE_RECEIVED;

        $sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_product ctp ON (p.product_id = ctp.product_id) LEFT JOIN " . DB_PREFIX . "customer cus ON (ctp.customer_id = cus.customer_id) LEFT JOIN " . DB_PREFIX . "country cou ON (cou.country_id = cus.country_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer c2c on (c2c.customer_id = ctp.customer_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND c2c.`show` = 1 and cus.status=1 AND p.buyer_flag = '1' ";
        if (!empty($data['country'])) {
            $sql .= " AND cou.iso_code_3 = '" . $data['country'] . "'";
        }
        if (!empty($data['country_id'])) {
            $sql .= " AND cou.country_id='" . $data['country_id'] . "'";
        }

        if ($flag) {
            $sql .= ' AND b2s.buyer_id = ' . $data['customer_id'];
            $sql .= ' AND b2s.buy_status = 1';
            $sql .= ' AND b2s.buyer_control_status = 1';
            $sql .= ' AND b2s.seller_control_status = 1';
        }

        // 精细化管理
        if ($this->customer->isLogged() && !$this->customer->isPartner()) {
            $sql .= " AND ( dm.product_display=1 OR dm.id is null )";
            $sql .= " AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                    JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                    JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                    dmg.seller_id = c2p.customer_id  AND bgl.seller_id = c2p.customer_id AND pgl.seller_id = c2p.customer_id
                    AND bgl.buyer_id = " . $this->customer->getId() . " AND pgl.product_id = p.product_id
                    AND dmg.status=1 and bgl.status=1 and pgl.status=1
            )";
        }

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
            } else {
                //假如filter_category_id为string的话
                if (is_string($data['filter_category_id'])) {
                    $sql .= " AND p2c.category_id IN (" . $data['filter_category_id'] . ")";
                } else {
                    $sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
                }
            }

            if (!empty($data['filter_filter'])) {
                $implode = array();

                $filters = explode(',', $data['filter_filter']);

                foreach ($filters as $filter_id) {
                    $implode[] = (int)$filter_id;
                }

                $sql .= " AND pf.filter_id IN (" . implode(',', $implode) . ")";
            }
        }

        if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {

            $this->load->model('tool/sphinx');
            $s = $this->model_tool_sphinx->getSearchProductId(trim($data['filter_name']));
            if ($s !== false && count($s)) {
                if (count($s)) {
                    $str = implode(',', $s);
                    $sql .= "AND p.product_id in (" . $str . ")";
                } else {
                    $sql .= "AND p.product_id  = 0";
                }

            } else {
                //万一异常了还是需要有之前的sql的
                $sql .= " AND (";

                if (!empty($data['filter_name'])) {
                    $implode = array();

                    $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

                    foreach ($words as $word) {
                        $implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
                    }

                    if ($implode) {
                        $sql .= " " . implode(" AND ", $implode) . "";
                    }

                    if (!empty($data['filter_description'])) {
                        $sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
                    }
                }

                if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
                    $sql .= " OR ";
                }

                if (!empty($data['filter_tag'])) {
                    $implode = array();

                    $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_tag'])));

                    foreach ($words as $word) {
                        $implode[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
                    }

                    if ($implode) {
                        $sql .= " " . implode(" AND ", $implode) . "";
                    }
                }

                if (!empty($data['filter_name'])) {
                    $sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                    $sql .= " OR LCASE(p.sku) like '%" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
                    $sql .= " OR LCASE(p.upc) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                    $sql .= " OR LCASE(p.ean) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                    $sql .= " OR LCASE(p.jan) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                    $sql .= " OR LCASE(p.isbn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                    $sql .= " OR LCASE(p.mpn) like '%" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
                }

                $sql .= ")";
            }
        }

        if (!empty($data['filter_manufacturer_id'])) {
            $sql .= " AND p.manufacturer_id = '" . (int)$data['filter_manufacturer_id'] . "'";
        }

        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        /**
         * 添加价格和销量筛选条件
         * @since 2019-6-6 11:49:26 价格筛选(discount&精细化)
         */
        if (isset($data['min_price']) && (!empty($data['min_price']) or $data['min_price'] == 0)) {
//            $sql .= " AND p.price >= " . $this->db->escape($data['min_price']);
            if (!$isCollectionFromDomicile) {
                //$sql .= " AND
                //        ifnull(b2s.discount,1)*
                //        (ifnull(dm.current_price,p.price) + ifnull(p.freight,0))
                //        >= " . $this->db->escape($data['min_price']);
                $sql .= " AND (ifnull(b2s.discount,1)* ifnull(dm.current_price,p.price))  >= " . $this->db->escape($data['min_price']);
            } else {
                $sql .= " AND (ifnull(b2s.discount,1)* ifnull(dm.current_price,p.price))  >= " . $this->db->escape($data['min_price']);
            }
        }
        if (isset($data['max_price']) && (!empty($data['max_price']) or $data['max_price'] == 0)) {
//            $sql .= " AND p.price <= " . $this->db->escape($data['max_price']);
            if (!$isCollectionFromDomicile) {
                //$sql .= " AND
                //        ifnull(b2s.discount,1)*
                //        (ifnull(dm.current_price,p.price) + ifnull(p.freight,0))
                //        <= " . $this->db->escape($data['max_price']);
                $sql .= " AND (ifnull(b2s.discount,1)* ifnull(dm.current_price,p.price)) <= " . $this->db->escape($data['max_price']);
            } else {
                $sql .= " AND (ifnull(b2s.discount,1)* ifnull(dm.current_price,p.price)) <= " . $this->db->escape($data['max_price']);
            }
        }
        if (isset($data['min_quantity']) && (!empty($data['min_quantity'] or $data['min_quantity'] == 0))) {
            $sql .= " AND p.quantity >= " . (int)$this->db->escape($data['min_quantity']);
        }
        if (isset($data['max_quantity']) && (!empty($data['max_quantity'] or $data['max_quantity'] == 0))) {
            $sql .= " AND p.quantity <= " . (int)$this->db->escape($data['max_quantity']);
        }

//        // 品牌精细化管理
//        $extractProductIds =  $this->getBuyerForbiddenProductIds($this->customer->getId()) ;
//        if (!empty($extractProductIds)){
//            $sql .= ' AND p.product_id NOT IN ('.join(',',$extractProductIds).')';
//        }

        $sql .= " GROUP BY p.product_id";

        $sort_data = array(
            'pd.name',
            'p.model',
            'p.quantity',
            'p.price',
            'rating',
            'p.sort_order',
            'p.date_added'
        );
        // 13739 当Buyer通过品类去筛选产品时，和Buyer建立关联关系的产品排在前面
        if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model' || $flag == true) {

            $sql .= " ORDER BY";

        } else {
            if ($data['sort'] != 'p.sort_order') {

                $sql .= " ORDER BY CASE
                        " . $case . "
                        WHEN dm.product_display = 0 THEN
                        1
                        WHEN p.status = 0 THEN
                        1
                        WHEN p.buyer_flag = 0 THEN
                        1
                        WHEN dm.expiration_time is not null and dm.expiration_time<NOW() THEN
                        1
                        ELSE
                            0
                        END, ";

                $sql .= "CASE
                        WHEN c.customer_group_id = 2 THEN
                            0
                        WHEN c.customer_group_id = 3 THEN
                            0
                        WHEN c.customer_group_id IN (17, 18, 19, 20) THEN
                            1
                        ELSE
                            0
                        END, ";
                if ($isPartner == 0 && $customFields != null) {
                    $sql .= 'CASE
                    when b2s.seller_id is not null
                    and b2s.buy_status = 1
                    and b2s.buyer_control_status = 1
                    and b2s.seller_control_status = 1
                    Then
                    0
                    ELSE
                    1
                    END,';
                }
            }


        }
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
                $sql .= "  LCASE(" . $data['sort'] . ")";
            } elseif ($data['sort'] == 'p.price') {
                if ($flag == false) {
                    if (!$isCollectionFromDomicile) {
                        //$sql .= " p.price + ifnull(p.freight,0)";
                        $sql .= " p.price";
                    } else {
                        $sql .= " p.price";
                    }

                } else {
                    if (!$isCollectionFromDomicile) {
                        //$sql .= "  (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price + ifnull(p.freight,0) END)";
                        $sql .= "  (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
                    } else {
                        $sql .= "  (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
                    }

                }
            } elseif ($data['sort'] == 'p.sort_order') {
                //默认排序
            } else {
                $sql .= " " . $data['sort'];
            }
        } else {
            //$sql .= "  p.sort_order";
            if ($flag == false) {
                if ($isCollectionFromDomicile) {
                    //$sql .= " p.price + ifnull(p.freight,0)";
                    $sql .= " p.price";
                } else {
                    $sql .= " p.price";
                }
            } else {
                if ($isCollectionFromDomicile) {
                    //$sql .= "  (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price + ifnull(p.freight,0) END)";
                    $sql .= "  (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
                } else {
                    $sql .= "  (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
                }
            }
        }

        if ($data['sort'] != 'p.sort_order') {
            if (isset($data['order']) && ($data['order'] == 'DESC')) {
                $sql .= " DESC, LCASE(pd.name) DESC";
            } else {
                $sql .= " ASC, LCASE(pd.name) ASC";
            }
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            if ($data['sort'] != 'p.sort_order') {
                $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
            }
        }
        $product_data = array();
        $sql = trim($sql, ',');
        $query = $this->db->query($sql);
        if ($data['sort'] == 'p.sort_order') {
            $query->rows = $this->searchSort($query->rows, $data['limit'], $data['start']);
        }
        $receipt_array = $this->getReceptionProduct();
        $this->load->model('extension/module/product_show');
        foreach ($query->rows as $result) {
            //$product_data[$result['product_id']] = $this->getProduct($result['product_id'], $customFields);
            $temp = $this->model_extension_module_product_show->getIdealProductInfo($result['product_id'], $customFields, $receipt_array);
            if ($temp['unsee'] == 0) {
                $product_data[$result['product_id']] = $temp;
            } else {
                continue;
            }

            /**
             * Marketplace Code Starts Here
             */
            if ($this->config->get('module_marketplace_status') && !$product_data[$result['product_id']]) {

                unset($product_data[$result['product_id']]);
            }
            /**
             * Marketplace Code Ends Here
             */

        }

        return $product_data;
    }


    /**
     * [getProductsBySearch description] 所有上过架的产品都可以被搜索到，产品失效，放在最后
     * @param array $data
     * @param null $customFields
     * @param null $isPartner
     * @return array
     */
    public function getProductsBySearch($data = array(), $customFields = null, $isPartner = 0)
    {
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        if ($customFields) {
            $column = 'pgl.id,';
            $case = ' WHEN pgl.id is not null THEN
                    1';
        } else {
            $column = '';
            $case = '';
        }
        $sql = "SELECT p.product_id,
                ro.receive_order_id,
                DATEDIFF(Now(),p.date_added) as date_df,
                p.part_flag,
                p.quantity," .
            $column . "
                case " . $case . "
                    WHEN dm.product_display = 0 THEN
                    1
                    WHEN p.status = 0 THEN
                    1
                    WHEN p.buyer_flag = 0 THEN
                    1
                    WHEN dm.expiration_time is not null and dm.expiration_time < NOW() THEN
                    0
                    ELSE
                        0
                    END  as unsee,
                CASE
                    WHEN c.customer_group_id = 2 THEN
                        0
                    WHEN c.customer_group_id = 3 THEN
                        0
                    WHEN c.customer_group_id IN (17, 18, 19, 20) THEN
                        1
                    ELSE
                        0
                    END  as   customer_group_sort,
                CASE
                when b2s.seller_id is not null
                and b2s.buy_status = 1
                and b2s.buyer_control_status = 1
                and b2s.seller_control_status = 1
                Then
                    0
                ELSE
                    1
                END as no_association,
                CASE
                WHEN rod.receive_order_id IS NOT NULL
                AND DATEDIFF(Now(),p.date_added) <= " . NEW_ARRIVAL_DAY . "
                THEN
                1
                ELSE
                0
                END AS is_new,
                dm.product_display,
                b2s.buy_status,
                b2s.buyer_control_status,
                b2s.seller_control_status,
                b2s.seller_id,
                dm.expiration_time,
                p.status,
                p.buyer_flag,
                (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special";
        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";
            } else {
                $sql .= " FROM " . DB_PREFIX . "product_to_category p2c";
            }

            if (!empty($data['filter_filter'])) {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product_filter pf ON (p2c.product_id = pf.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (pf.product_id = p.product_id)";
            } else {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
            }
        } else {
            $sql .= " FROM " . DB_PREFIX . "product p";
        }

        $flag = false;


        //13739 当Buyer通过品类去筛选产品时，和Buyer建立关联关系的产品排在前面
        if ($flag == false) {
            $sql .= ' LEFT JOIN ' . DB_PREFIX . 'customerpartner_to_product as c2p ON p.product_id = c2p.product_id';
            $sql .= ' LEFT JOIN ' . DB_PREFIX . 'customer as c on c.customer_id = c2p.customer_id';
            if ($customFields != null) {
                $str = 'and b2s.buyer_id =' . $customFields;
            } else {
                $str = 'and b2s.buyer_id =0';
            }
            $sql .= ' LEFT JOIN ' . DB_PREFIX . 'buyer_to_seller as b2s ON b2s.seller_id = c2p.customer_id ' . $str;
            $sql .= ' LEFT JOIN oc_delicacy_management as dm ON (dm.buyer_id = b2s.buyer_id and dm.product_id = p.product_id) ';    // Add by Lester.you 2019-6-6 11:48:21 价格筛选（discount&精细化管理）
            //由于精细化二期进行的更改，所有获取产品是否精细化，以及排序需要产生变化
            if ($customFields) {
                $sql .= ' LEFT JOIN oc_customerpartner_buyer_group_link as bgl on bgl.buyer_id = b2s.buyer_id and bgl.status=1 and bgl.seller_id = b2s.seller_id and bgl.buyer_id = ' . $customFields . '
                          LEFT JOIN oc_delicacy_management_group as dmg on bgl.buyer_group_id = dmg.buyer_group_id AND dmg.status =1
                          LEFT JOIN oc_customerpartner_product_group_link as pgl on pgl.product_group_id = dmg.product_group_id and pgl.status=1 and pgl.product_id = p.product_id';

            }
        }
        $sql .= ' LEFT JOIN tb_sys_receipts_order_detail AS rod ON rod.product_id = p.product_id
                LEFT JOIN tb_sys_receipts_order AS ro ON ro.receive_order_id = rod.receive_order_id
                AND ro.`expected_date` IS NOT NULL
                AND rod.`expected_qty` IS NOT NULL
                AND ro.`expected_date` > NOW()
                AND ro.`status` =  ' . ReceiptOrderStatus::TO_BE_RECEIVED;
        $sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_product ctp ON (p.product_id = ctp.product_id) LEFT JOIN " . DB_PREFIX . "customer cus ON (ctp.customer_id = cus.customer_id) LEFT JOIN " . DB_PREFIX . "country cou ON (cou.country_id = cus.country_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer c2c on (c2c.customer_id = ctp.customer_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND c2c.`show` = 1 and cus.status=1  ";
        if (!empty($data['country'])) {
            $sql .= " AND cou.iso_code_3 = '" . $data['country'] . "'";
        }
        if (!empty($data['country_id'])) {
            $sql .= " AND cou.country_id='" . $data['country_id'] . "'";
        }

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
            } else {
                $sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
            }

            if (!empty($data['filter_filter'])) {
                $implode = array();

                $filters = explode(',', $data['filter_filter']);

                foreach ($filters as $filter_id) {
                    $implode[] = (int)$filter_id;
                }

                $sql .= " AND pf.filter_id IN (" . implode(',', $implode) . ")";
            }
        }

        if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
            $this->load->model('tool/sphinx');
            $s = $this->model_tool_sphinx->getSearchProductId(trim($data['filter_name']));
            if ($s !== false && count($s)) {

                if (count($s)) {
                    $str = implode(',', $s);
                    $sql .= "AND p.product_id in (" . $str . ")";
                } else {
                    $sql .= "AND p.product_id  = 0";
                }


            } else {
                //万一异常了还是需要有之前的sql的
                $sql .= " AND (";

                if (!empty($data['filter_name'])) {
                    $implode = array();

                    $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

                    foreach ($words as $word) {
                        $implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
                    }

                    if ($implode) {
                        $sql .= " " . implode(" AND ", $implode) . "";
                    }

                    if (!empty($data['filter_description'])) {
                        $sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
                    }
                }

                if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
                    $sql .= " OR ";
                }

                if (!empty($data['filter_tag'])) {
                    $implode = array();

                    $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_tag'])));

                    foreach ($words as $word) {
                        $implode[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
                    }

                    if ($implode) {
                        $sql .= " " . implode(" AND ", $implode) . "";
                    }
                }

                if (!empty($data['filter_name'])) {
                    $sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                    $sql .= " OR LCASE(p.sku) like '%" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
                    $sql .= " OR LCASE(p.upc) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                    $sql .= " OR LCASE(p.ean) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                    $sql .= " OR LCASE(p.jan) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                    $sql .= " OR LCASE(p.isbn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                    $sql .= " OR LCASE(p.mpn) like '%" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
                }

                $sql .= ")";
            }

        }

        if (!empty($data['filter_manufacturer_id'])) {
            $sql .= " AND p.manufacturer_id = '" . (int)$data['filter_manufacturer_id'] . "'";
        }
        ////14103所有上过架的产品都可以被检索到，如果产品已失效，则在检索结果页和商品详情页标注出商品无效的原因
        $sql .= " AND p.product_type IN (0,3) ";
        $sql .= " GROUP BY p.product_id";

        $sort_data = array(
            'pd.name',
            'p.model',
            'p.quantity',
            'p.price',
            'rating',
            'p.sort_order',
            'p.date_added'
        );
        // 13739 当Buyer通过品类去筛选产品时，和Buyer建立关联关系的产品排在前面
        // dm.product_display,b2s.buy_status,b2s.buyer_control_status,b2s.seller_control_status,dm.expiration_time, p.status, p.buyer_flag
        if ($data['sort'] != 'p.sort_order') {
            $sql .= " ORDER BY CASE
                        " . $case . "
                        WHEN dm.product_display = 0 THEN
                        1
                        WHEN p.status = 0 THEN
                        1
                        WHEN p.buyer_flag = 0 THEN
                        1
                        WHEN dm.expiration_time is not null and dm.expiration_time<NOW() THEN
                        1
                        ELSE
                            0
                        END, ";

            $sql .= "CASE
                        WHEN c.customer_group_id = 2 THEN
                            0
                        WHEN c.customer_group_id = 3 THEN
                            0
                        WHEN c.customer_group_id IN (17, 18, 19, 20) THEN
                            1
                        ELSE
                            0
                        END, ";
            if ($isPartner == 0 && $customFields != null) {
                $sql .= 'CASE
                    when b2s.seller_id is not null
                    and b2s.buy_status = 1
                    and b2s.buyer_control_status = 1
                    and b2s.seller_control_status = 1
                    Then
                    0
                    ELSE
                    1
                    END,';
            }
        }

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
                $sql .= "  LCASE(" . $data['sort'] . ")";
            } elseif ($data['sort'] == 'p.price') {
                if ($flag == false) {
                    if (!$isCollectionFromDomicile) {
                        //$sql .= " p.price + ifnull(p.freight,0)";
                        $sql .= " p.price";
                    } else {
                        $sql .= " p.price";
                    }
                } else {
                    if (!$isCollectionFromDomicile) {
                        //$sql .= "  (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price + ifnull(p.freight,0) END)";
                        $sql .= "  (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
                    } else {
                        $sql .= "  (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
                    }
                }
            } elseif ($data['sort'] == 'p.sort_order') {
                //默认排序
            } else {
                $sql .= " " . $data['sort'];
            }
        } else {
            //$sql .= "  p.sort_order";
            if ($flag == false) {
                if (!$isCollectionFromDomicile) {
                    //$sql .= " p.price + ifnull(p.freight,0)";
                    $sql .= " p.price";
                } else {
                    $sql .= " p.price";
                }
            } else {
                if (!$isCollectionFromDomicile) {
                    //$sql .= "  (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price + ifnull(p.freight,0) END)";
                    $sql .= "  (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
                } else {
                    $sql .= "  (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
                }
            }
        }

        if ($data['sort'] != 'p.sort_order') {
            if (isset($data['order']) && ($data['order'] == 'DESC')) {
                $sql .= " DESC, LCASE(pd.name) DESC";
            } else {
                $sql .= " ASC, LCASE(pd.name) ASC";
            }
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            if ($data['sort'] != 'p.sort_order') {
                $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
            }
        }
        $product_data = array();
        $sql = trim($sql, ',');
        $query = $this->db->query($sql);

        if ($data['sort'] == 'p.sort_order') {
            $query->rows = $this->searchSort($query->rows, $data['limit'], $data['start']);
        }
        $receipt_array = $this->getReceptionProduct();
        $this->load->model('extension/module/product_show');
        foreach ($query->rows as $result) {
            if ($result['unsee'] == 0) {
                $temp = $this->model_extension_module_product_show->getIdealProductInfo($result['product_id'], $customFields, $receipt_array);
                if ($temp['unsee'] == 0) {
                    $product_data[$result['product_id']] = $temp;
                } else {
                    continue;
                }
                /**
                 * Marketplace Code Starts Here
                 */
                if ($this->config->get('module_marketplace_status') && !$product_data[$result['product_id']]) {

                    unset($product_data[$result['product_id']]);
                }
                /**
                 * Marketplace Code Ends Here
                 */
            }


        }
        return $product_data;
    }

    /**
     * [searchSort description]
     * @param $data
     * @param $limit
     * @param $start
     * @return array
     */
    public function searchSort($data, $limit, $start)
    {

        foreach ($data as $key => $value) {
            // 产品是否可见
            $data[$key]['weight'] = 0;
            if ($value['unsee'] == 0) {
                $data[$key]['weight'] += 100000;
            } else {
                $data[$key]['weight'] += 50000;
            }

            if ($value['customer_group_sort'] == 0) {
                $data[$key]['weight'] += 10000;
            } else {
                $data[$key]['weight'] += 5000;
            }

            if ($value['no_association']) {
                $data[$key]['weight'] += 500;
            } else {
                $data[$key]['weight'] += 1000;
            }

            if ($value['is_new']) {
                $data[$key]['weight'] += 100;
                if ($value['part_flag'] == 1) {
                    $data[$key]['weight'] += 10;
                } else {
                    $data[$key]['weight'] += 20;
                }
                $data[$key]['weight'] += $value['product_id'] / 100000000;
            } else {
                $data[$key]['weight'] += 50;
                if ($value['quantity'] > 0) {
                    $data[$key]['weight'] += 20;
                } else {
                    $data[$key]['weight'] += 10;
                }

                if ($value['part_flag'] == 1) {
                    $data[$key]['weight'] += 1;
                } else {
                    $data[$key]['weight'] += 2;
                }

                //按照多少来
                $data[$key]['weight'] += $value['quantity'] / 10000;
                $data[$key]['weight'] += $value['product_id'] / 100000000;
            }

        }
        if ($data) {

            foreach ($data as $key => $value) {
                $sort_order[$key] = $value['weight'];
            }

            array_multisort($sort_order, SORT_DESC, SORT_REGULAR, $data);
        }
        $ret = array_slice($data, $start, $limit);

        return $ret;
    }

    public function getProductSpecials($data = array())
    {
        $sql = "SELECT DISTINCT ps.product_id, (SELECT AVG(rating) FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = ps.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating FROM " . DB_PREFIX . "product_special ps LEFT JOIN " . DB_PREFIX . "product p ON (ps.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND ps.customer_group_id = " . (int)$this->config->get('config_customer_group_id') . " AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) GROUP BY ps.product_id";

        $sort_data = array(
            'pd.name',
            'p.model',
            'ps.price',
            'rating',
            'p.sort_order'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
                $sql .= " ORDER BY LCASE(" . $data['sort'] . ")";
            } else {
                $sql .= " ORDER BY " . $data['sort'];
            }
        } else {
            $sql .= " ORDER BY p.sort_order";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC, LCASE(pd.name) DESC";
        } else {
            $sql .= " ASC, LCASE(pd.name) ASC";
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

        $product_data = array();

        $query = $this->db->query($sql);

        foreach ($query->rows as $result) {
            $product_data[$result['product_id']] = $this->getProduct($result['product_id']);

            /**
             * Marketplace Code Starts Here
             */
            if ($this->config->get('module_marketplace_status') && !$product_data[$result['product_id']]) {

                unset($product_data[$result['product_id']]);
            }
            /**
             * Marketplace Code Ends Here
             */

        }

        return $product_data;
    }

    public function getLatestProducts($limit)
    {
        $product_data = $this->cache->get('product.latest.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit);

        if (!$product_data) {
            $query = $this->db->query("SELECT p.product_id FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ORDER BY p.date_added DESC LIMIT " . (int)$limit);

            foreach ($query->rows as $result) {
                $product_data[$result['product_id']] = $this->getProduct($result['product_id']);

                /**
                 * Marketplace Code Starts Here
                 */
                if ($this->config->get('module_marketplace_status') && !$product_data[$result['product_id']]) {

                    unset($product_data[$result['product_id']]);
                }
                /**
                 * Marketplace Code Ends Here
                 */

            }

            $this->cache->set('product.latest.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit, $product_data);
        }

        return $product_data;
    }

    public function getPopularProducts($limit)
    {
        $product_data = $this->cache->get('product.popular.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit);

        if (!$product_data) {
            $query = $this->db->query("SELECT p.product_id FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ORDER BY p.viewed DESC, p.date_added DESC LIMIT " . (int)$limit);

            foreach ($query->rows as $result) {
                $product_data[$result['product_id']] = $this->getProduct($result['product_id']);

                /**
                 * Marketplace Code Starts Here
                 */
                if ($this->config->get('module_marketplace_status') && !$product_data[$result['product_id']]) {

                    unset($product_data[$result['product_id']]);
                }
                /**
                 * Marketplace Code Ends Here
                 */

            }

            $this->cache->set('product.popular.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit, $product_data);
        }

        return $product_data;
    }

    //2020.06.16 店铺热销(Download)的5个产品信息展示
    public function get5HotDownloadProductsFromShop($seller_ids, $unavailable_products_id, $limit = 5)
    {
        $products_query = [];
        foreach ($seller_ids as $seller_id) {
            //1. 有有效库存 quantity_30 desc,quantity desc
            $products_query[] = db('oc_product AS p')
                ->leftJoin('oc_customerpartner_to_product AS c2p', 'p.product_id', '=', 'c2p.product_id')
                ->leftJoin('oc_product_to_store as p2s', 'p2s.product_id', '=', 'p.product_id')
                ->leftJoin('oc_product_description as d', 'd.product_id', '=', 'p.product_id')
                ->leftJoin('tb_sys_product_sales as ps', 'ps.product_id', '=', 'p.product_id')
                ->selectRaw('c2p.customer_id,p.product_id,p.sku,p.price,p.tax_class_id,p.image,d.name,p.quantity,ifnull(ps.quantity_30,0) AS quantity_30,p.date_added')
                ->whereRaw('c2p.customer_id=' . $seller_id)
                ->where(function ($q) use ($unavailable_products_id) { //精细化管理
                    strlen($unavailable_products_id) && $q->whereRaw('p.product_id NOT IN (' . $unavailable_products_id . ')');
                })
                ->whereRaw('p.status=1 AND p.buyer_flag=1 AND p.is_deleted=0 AND p2s.store_id=0 AND d.language_id=1')
                ->whereRaw('p.quantity>0')
                ->orderByDesc('quantity_30')
                ->orderByDesc('p.quantity')
                ->orderByDesc('p.date_added')
                ->limit($limit);
        }
        $res = [];
        if ($products_query) {
            $query = $products_query[0];
            for ($i = 1; $i < count($products_query); $i++) {
                $query->unionAll($products_query[$i]);
            }
            $products = $query->get();
            $products = obj2array($products);
            foreach ($products as $p) {
                $res[$p['customer_id']][] = $p;
            }
        }
        $products_query = [];
        foreach ($seller_ids as $seller_id) {
            //2. 上面产品不足，按销量
            $products_query[] = $this->orm->table('oc_product AS p')
                ->leftJoin('oc_customerpartner_to_product AS c2p', 'p.product_id', '=', 'c2p.product_id')
                ->leftJoin('oc_product_to_store as p2s', 'p2s.product_id', '=', 'p.product_id')
                ->leftJoin('oc_product_description as d', 'd.product_id', '=', 'p.product_id')
                ->leftJoin('tb_sys_product_sales as ps', 'ps.product_id', '=', 'p.product_id')
                ->selectRaw('c2p.customer_id,p.product_id,p.sku,p.price,p.tax_class_id,p.image,d.name,p.quantity,ifnull(ps.quantity_30,0) AS quantity_30,p.date_added')
                ->whereRaw('c2p.customer_id=' . $seller_id)
                ->where(function ($q) use ($unavailable_products_id) { //精细化管理
                    strlen($unavailable_products_id) && $q->whereRaw('p.product_id NOT IN (' . $unavailable_products_id . ')');
                })
                ->whereRaw('p.status=1 AND p.buyer_flag=1 AND p.is_deleted=0 AND p2s.store_id=0 AND d.language_id=1')
                ->orderByDesc('quantity_30')
                ->orderByDesc('p.quantity')
                ->orderByDesc('p.date_added')
                ->limit($limit);
        }
        $query = $products_query[0];
        for ($i = 1; $i < count($products_query); $i++) {
            $query->unionAll($products_query[$i]);
        }
        $products = $query->get();
        $products = obj2array($products);
        $res2 = [];
        foreach ($products as $p) {
            $res2[$p['customer_id']][] = $p;
        }
        $end_res = [];
        foreach ($res2 as $customer_id => $ps) {
            if (isset($res['customer_id']) && (count($res[$customer_id]) == $limit)) {
                $end_res = array_merge_recursive($end_res, $res[$customer_id]);
            } else {
                $end_res = array_merge_recursive($end_res, $ps);
            }
        }
        $products = $end_res;
        unset($res, $res2, $end_res, $query, $products_query);
        $this->load->model('tool/image');
        $this->load->model('extension/module/product_home');
        $seller_products = array();
        $customer_id = $this->customer->getId();
        $productIds = array_column($products, 'product_id');

        /** @var ProductPriceRangeFactory $productPriceFactory */
        [, $productPriceFactory] = app(ProductRepository::class)->getProductBaseInfosAndPriceRanges($productIds, $customer_id);
        // 价格区间
        $priceLists = $productPriceFactory->getRanges();

        foreach ($products as $product) {
            if (!isset($priceLists[$product['product_id']])) {
                continue;
            }
            $productPriceInfo = $priceLists[$product['product_id']];
            $product['max_price_show'] = $this->currency->format($productPriceInfo[1] ?? 0, session('currency'));
            $product['min_price_show'] = $this->currency->format($productPriceInfo[0] ?? 0, session('currency'));
            $product['href'] = url(['product/product', 'product_id' => $product['product_id']]);
            $product['thumb'] = $product['image'] = $this->model_tool_image->resize($product['image'], 100, 100);
            $seller_products[$product['customer_id']][] = $product;
        }
        return $seller_products;
    }

    /*
     * seller店铺的商品分类 2020.06.18
     * */
    public function getSellerMainCat($sellersId)
    {
        //全部查询
        $sellers_cat_info = $products_query = array();
        foreach ($sellersId as $k => $seller_id) {
            //$cache = $this->cache->get('catalog.customer_sale.cat.'.$seller_id);
            if (empty($cache)) {
                $products_query[] = $t = $this->orm->table('oc_product AS p')
                    ->leftJoin('oc_customerpartner_to_product AS c2p', 'p.product_id', '=', 'c2p.product_id')
                    ->leftJoin('oc_product_to_category AS p2c', 'p2c.product_id', '=', 'p.product_id')
                    ->leftJoin('oc_category AS cc', 'cc.category_id', '=', 'p2c.category_id')
                    ->selectRaw('c2p.customer_id,cc.parent_id,cc.category_id,cc.category_level,count(p.product_id) AS product_sum')
                    ->groupBy(['cc.category_id'])
                    ->where([
                        'p.status' => 1, 'p.buyer_flag' => 1, 'p.is_deleted' => 0,
                        'c2p.customer_id' => $seller_id
                    ]);
            } else {
                $sellers_cat_info[$seller_id] = json_decode($cache, true);
            }
        }
        if (count($products_query) > 0) {
            $query = $products_query[0];
            for ($i = 1; $i < count($products_query); $i++) {
                $query->union($products_query[$i]);
            }
            $sellers_sale_info = $query->get()->toArray();
            $total_products = array();
            foreach ($sellers_sale_info as $item) {
                $total_products[$item->customer_id][] = $item;
            }
            foreach ($total_products as $customer_id => $customer_has) {
                $this->cache->set('catalog.customer_sale.cat.' . $customer_id, json_encode($customer_has));
            }
            if ($sellers_cat_info && $total_products) {
                $sellers_cat_info = array_combine($sellers_cat_info, $total_products);
            } elseif (empty($sellers_cat_info)) {
                $sellers_cat_info = $total_products;
            }
            unset($total_products, $sellers_sale_info);
        }

        $cat_all_name = $this->cache->get('catalog.customer_sale.cat_all_name');
        $Furniture_ids = $this->cache->get('catalog.customer_sale.Furniture_ids');
        if (empty($cat_all_name) || empty($Furniture_ids)) {
            $cat_all_name = $this->orm->table('oc_category_description')
                ->select(['category_id', 'name'])
                ->get()->toArray();
            $cat_all_name = array_combine(
                array_column($cat_all_name, 'category_id'),
                array_column($cat_all_name, 'name')
            );
            $this->cache->set('catalog.customer_sale.cat_all_name', json_encode($cat_all_name));
            $Furniture_ids = $this->orm->table('oc_category')
                ->select(['category_id'])
                ->whereRaw('parent_id=255')
                ->get()->toArray();
            $Furniture_ids = array_column($Furniture_ids, 'category_id');
            $this->cache->set('catalog.customer_sale.Furniture_ids', implode(',', $Furniture_ids));
        } else {
            $cat_all_name = json_decode($cat_all_name, true);
            $Furniture_ids = explode(',', $Furniture_ids);
        }

        $sellers_cat = array();
        $sellers_cat_info = obj2array($sellers_cat_info);
        foreach ($sellers_cat_info as $seller_id => $item) {
            $sellers_cat[$seller_id] = '';
            $weights = array();
            foreach ($item as $v) {
                ($v['category_level'] < 3 || $v['category_level'] == null) && $weights[$v['category_id']] = $v['product_sum']; //不统计3+级分类category_id
                $weights[$v['parent_id']] = isset($weights[$v['parent_id']]) ? ($weights[$v['parent_id']] + $v['product_sum']) : $v['product_sum']; //简单加权到父级
            }
            foreach ($item as $v) {
                $weights[$v['parent_id']] = $weights[$v['parent_id']] + $v['product_sum']; //再次加权到父级
            }
            $key = $weights;
            rsort($key);
            $current_cat = array_search($key[0], $weights);
            $sellers_cat[$seller_id] = isset($cat_all_name[$current_cat]) ? $cat_all_name[$current_cat] : '';
            if (empty($sellers_cat[$seller_id])) {
                $i = 1;
                while (empty($sellers_cat[$seller_id])) {
                    $current_cat = array_search($key[$i], $weights);
                    if ($current_cat !== false && isset($cat_all_name[$current_cat])) {
                        $sellers_cat[$seller_id] = $cat_all_name[$current_cat];
                    }
                    if ($i > 5) {
                        $sellers_cat[$seller_id] = 'Others';
                        unset($current_cat);
                        break;
                    } else {
                        $i++;
                    }
                }
            }
            if (isset($current_cat) && in_array($current_cat, $Furniture_ids)) { //Furniture二级的三级子类过多情况
                $sellers_cat[$seller_id] = 'Furniture > ' . $cat_all_name[$current_cat];
                continue;
            }
            if (isset($current_cat) && $sellers_cat[$seller_id] == 'Furniture') { //Furniture直接二级子类过多情况
                for ($i = 0; $i < count($key) - 1; $i++) {
                    $current_cat = array_search($key[$i], $weights);
                    if (in_array($current_cat, $Furniture_ids)) {
                        $sellers_cat[$seller_id] .= ' > ' . $cat_all_name[$current_cat];
                        break;
                    }
                }
            }
        }
        return $sellers_cat;
    }

    // GET-SALE TOTAL 2020.6.24 改造于searchProductId
    public function getSellersTotalProducts($seller_id, $unavailable_products_id)
    {
        $isPartner = $this->customer->isPartner();
        $filterData['seller_id'] = $seller_id;
        $filterData['country'] = session('country');
        $notIn = '';
        if (!$isPartner) {//buyer
            $notIn = $unavailable_products_id;
        }
        $query = $this->orm->table('oc_product as p')
            ->leftjoin('oc_customerpartner_to_product as c2p', 'p.product_id', 'c2p.product_id')
            ->leftjoin('oc_customer as c', 'c.customer_id', 'c2p.customer_id')
            ->leftjoin('oc_product_description as pd', 'p.product_id', 'pd.product_id')
            ->leftjoin('oc_product_to_store as p2s', 'p.product_id', 'p2s.product_id')
            ->leftjoin('oc_country as cou', 'cou.country_id', 'c.country_id')
            ->leftjoin('oc_customerpartner_to_customer as c2c', 'c2c.customer_id', 'c2p.customer_id')
            ->selectRaw("COUNT(p.product_id) AS total")
            ->whereRaw("p.status=1 AND p.is_deleted=0 AND p.buyer_flag AND pd.language_id=" . (int)$this->config->get('config_language_id')
                . ' AND p2s.store_id=' . (int)$this->config->get('config_store_id') . ' AND c2c.show=1 AND c.status=1 AND p.product_type IN (0,3)'
            )
            ->whereRaw('c2p.customer_id=' . $filterData['seller_id'])
            ->whereRaw("p.date_available <='" . date('Y-m-d H:i:s') . "'")
            ->where(function ($query) use ($notIn) {
                strlen($notIn) && $query->whereRaw("p.product_id NOT IN({$notIn})");
            })
            ->where(function ($query) use ($filterData) {
                !empty($filterData['country']) && $query->where('cou.iso_code_3', $filterData['country']);
            });
        $total = $query->get()->toArray();
        return (int)$total[0]->total;
    }

    public function getBestSellerProducts($limit)
    {
        $product_data = $this->cache->get('product.bestseller.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit);

        if (!$product_data) {
            $product_data = array();

            $query = $this->db->query("SELECT op.product_id, SUM(op.quantity) AS total FROM " . DB_PREFIX . "order_product op LEFT JOIN `" . DB_PREFIX . "order` o ON (op.order_id = o.order_id) LEFT JOIN `" . DB_PREFIX . "product` p ON (op.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE o.order_status_id > '0' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' GROUP BY op.product_id ORDER BY total DESC LIMIT " . (int)$limit);

            foreach ($query->rows as $result) {
                $product_data[$result['product_id']] = $this->getProduct($result['product_id']);

                /**
                 * Marketplace Code Starts Here
                 */
                if ($this->config->get('module_marketplace_status') && !$product_data[$result['product_id']]) {

                    unset($product_data[$result['product_id']]);
                }
                /**
                 * Marketplace Code Ends Here
                 */

            }

            $this->cache->set('product.bestseller.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit, $product_data);
        }

        return $product_data;
    }

    public function getProductAttributes($product_id)
    {
        $product_attribute_group_data = array();

        $product_attribute_group_query = $this->db->query("SELECT ag.attribute_group_id, agd.name FROM " . DB_PREFIX . "product_attribute pa LEFT JOIN " . DB_PREFIX . "attribute a ON (pa.attribute_id = a.attribute_id) LEFT JOIN " . DB_PREFIX . "attribute_group ag ON (a.attribute_group_id = ag.attribute_group_id) LEFT JOIN " . DB_PREFIX . "attribute_group_description agd ON (ag.attribute_group_id = agd.attribute_group_id) WHERE pa.product_id = '" . (int)$product_id . "' AND agd.language_id = '" . (int)$this->config->get('config_language_id') . "' GROUP BY ag.attribute_group_id ORDER BY ag.sort_order, agd.name");

        foreach ($product_attribute_group_query->rows as $product_attribute_group) {
            $product_attribute_data = array();

            $product_attribute_query = $this->db->query("SELECT a.attribute_id, ad.name, pa.text FROM " . DB_PREFIX . "product_attribute pa LEFT JOIN " . DB_PREFIX . "attribute a ON (pa.attribute_id = a.attribute_id) LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE pa.product_id = '" . (int)$product_id . "' AND a.attribute_group_id = '" . (int)$product_attribute_group['attribute_group_id'] . "' AND ad.language_id = '" . (int)$this->config->get('config_language_id') . "' AND pa.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY a.sort_order, ad.name");

            foreach ($product_attribute_query->rows as $product_attribute) {
                $product_attribute_data[] = array(
                    'attribute_id' => $product_attribute['attribute_id'],
                    'name' => $product_attribute['name'],
                    'text' => $product_attribute['text']
                );
            }

            $product_attribute_group_data[] = array(
                'attribute_group_id' => $product_attribute_group['attribute_group_id'],
                'name' => $product_attribute_group['name'],
                'attribute' => $product_attribute_data
            );
        }

        return $product_attribute_group_data;
    }

    public function getProductOptions($product_id)
    {
        $product_option_data = array();

        $product_option_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_option po LEFT JOIN `" . DB_PREFIX . "option` o ON (po.option_id = o.option_id) LEFT JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id) WHERE po.product_id = '" . (int)$product_id . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY o.sort_order");

        foreach ($product_option_query->rows as $product_option) {
            $product_option_value_data = array();

            $product_option_value_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_option_value pov LEFT JOIN " . DB_PREFIX . "option_value ov ON (pov.option_value_id = ov.option_value_id) LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE pov.product_id = '" . (int)$product_id . "' AND pov.product_option_id = '" . (int)$product_option['product_option_id'] . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY ov.sort_order");

            foreach ($product_option_value_query->rows as $product_option_value) {
                // 覆盖自定义属性
                $product_option_value['name'] = $this->getProductOptionValue(
                    (int)$product_option_value['product_id'],
                    (int)$product_option_value['option_id'],
                    $this->getProductCustomerId((int)$product_option_value['product_id'])
                );
                $product_option_value_data[] = array(
                    'product_option_value_id' => $product_option_value['product_option_value_id'],
                    'option_value_id' => $product_option_value['option_value_id'],
                    'name' => $product_option_value['name'],
                    'image' => $product_option_value['image'],
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
                'product_option_value' => $product_option_value_data,
                'option_id' => $product_option['option_id'],
                'name' => $product_option['name'],
                'type' => $product_option['type'],
                'value' => $product_option['VALUE'] ?? ($product_option['value'] ?? null),
                'required' => $product_option['required']
            );
        }

        return $product_option_data;
    }

    public function getProductDiscounts($product_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product_id . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND quantity > 1 AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY quantity ASC, priority ASC, price ASC");

        return $query->rows;
    }

    public function getProductImages($product_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "' ORDER BY sort_order ASC");

        return $query->rows;
    }

    public function getProductRelated($product_id)
    {

        $product_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_related pr LEFT JOIN " . DB_PREFIX . "product p ON (pr.related_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pr.product_id = '" . (int)$product_id . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");

        // 精细化管理
        $forbidDisplayProductIds = $this->getBuyerForbiddenProductIds($this->customer->getId());
        foreach ($query->rows as $result) {
            if (!in_array($result['related_id'], $forbidDisplayProductIds)) {
                $product_data[$result['related_id']] = $this->getProduct($result['related_id']);
            }
        }

        return $product_data;
    }

    public function getProductLayoutId($product_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int)$product_id . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

        if ($query->num_rows) {
            return (int)$query->row['layout_id'];
        } else {
            return 0;
        }
    }

    public function getCategories($product_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");

        return $query->rows;
    }


    /**
     * 获取产品的分类层级顺序
     * @param int $product_id
     * @return array
     */
    public function getCategoriesOrder($product_id)
    {
        $sql = "
    SELECT p2c.product_id, c.category_id, cd.name
    FROM oc_category AS c
    JOIN oc_category_description AS cd ON cd.category_id=c.category_id
    JOIN oc_product_to_category AS p2c ON p2c.category_id=c.category_id
    WHERE p2c.product_id={$product_id}
    AND c.status=1 AND c.is_deleted=0
    ORDER BY c.category_level ASC";

        $query = $this->db->query($sql);

        return $query->rows;
    }


    public function getTotalProducts($data = array(), $str_flag = 0)
    {
        $sql = "SELECT COUNT(DISTINCT p.product_id) AS total, group_concat(distinct p.product_id) AS id_str";

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";
            } else {
                $sql .= " FROM " . DB_PREFIX . "product_to_category p2c";
            }

            if (!empty($data['filter_filter'])) {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product_filter pf ON (p2c.product_id = pf.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (pf.product_id = p.product_id)";
            } else {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
            }
        } else {
            $sql .= " FROM " . DB_PREFIX . "product p";
        }

        $filterArr = [
            'min_price',
            'max_price',
            'min_quantity',
            'max_quantity'
        ];

        $flag = false;
        foreach ($filterArr as $f) {
            if (isset($data[$f]) && (!empty($data[$f]) or (int)$data[$f] === 0)) {
                $flag = true;
            }
        }
        if ($flag) {
            $sql .= ' JOIN ' . DB_PREFIX . 'customerpartner_to_product as c2p ON p.product_id = c2p.product_id';
            $sql .= ' JOIN ' . DB_PREFIX . 'buyer_to_seller as b2s ON b2s.seller_id = c2p.customer_id';
        }

        if ($this->customer->isLogged() && !$this->customer->isPartner()) {
            $sql .= ' LEFT JOIN oc_delicacy_management as dm ON (dm.buyer_id =' . $this->customer->getId() . ' and dm.product_id = p.product_id and dm.expiration_time>NOW()) ';    // Add by Lester.you 2019-6-6 11:48:21 价格筛选（discount&精细化管理）
        }

        $sql .= ' LEFT JOIN tb_sys_receipts_order_detail AS rod ON rod.product_id = p.product_id
                LEFT JOIN tb_sys_receipts_order AS ro ON ro.receive_order_id = rod.receive_order_id
                AND ro.`expected_date` IS NOT NULL
                AND rod.`expected_qty` IS NOT NULL
                AND ro.`expected_date` > NOW()
                AND ro.`status` =  ' . ReceiptOrderStatus::TO_BE_RECEIVED;

        $sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_product ctp ON (p.product_id = ctp.product_id) LEFT JOIN " . DB_PREFIX . "customer cu ON (ctp.customer_id=cu.customer_id) LEFT JOIN " . DB_PREFIX . "country cou ON (cu.country_id=cou.country_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer c2c on (c2c.customer_id = ctp.customer_id)
        WHERE pd.language_id = '" . (int)$this->config->get('config_language_id')
            . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '"
            . (int)$this->config->get('config_store_id')
            . "' AND c2c.`show` = 1 and cu.status=1 AND pd.language_id = '1' AND p.buyer_flag = '1' ";

        if (!empty($data['country'])) {
            $sql .= " AND cou.iso_code_3='" . $data['country'] . "'";
        }
        if (!empty($data['country_id'])) {
            $sql .= " AND cou.country_id='" . $data['country_id'] . "'";
        }

        if ($flag) {
            $sql .= ' AND b2s.buyer_id = ' . (int)$data['customer_id'];
            $sql .= ' AND b2s.buy_status = 1';
            $sql .= ' AND b2s.buyer_control_status = 1';
            $sql .= ' AND b2s.seller_control_status = 1';
        }

        if ($this->customer->isLogged() && !$this->customer->isPartner()) {
            $sql .= " AND ( dm.product_display=1 OR dm.id is null )"; // Add by Lester.You 精细化管管理
            $sql .= " AND NOT EXISTS (
                SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                    JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                    JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                WHERE
                  dmg.seller_id = ctp.customer_id  AND bgl.seller_id = ctp.customer_id AND pgl.seller_id = ctp.customer_id
                    AND bgl.buyer_id = " . $this->customer->getId() . "   AND pgl.product_id = p.product_id
                    AND dmg.status = 1 and bgl.status = 1 and pgl.status = 1
                )";
        }


        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        /**
         * 添加价格和销量筛选条件
         * @since 2019-6-6 11:49:26 价格筛选(discount&精细化)
         */
        if (isset($data['min_price']) && (!empty($data['min_price']) or $data['min_price'] == 0)) {
//            $sql .= " AND p.price >= " . $this->db->escape($data['min_price']);
            if (!$isCollectionFromDomicile) {
                //$sql .= " AND
                //        ifnull(b2s.discount,1)*
                //        (ifnull(dm.current_price,p.price) + ifnull(p.freight,0))
                //        >= " . $this->db->escape($data['min_price']);
                $sql .= " AND (ifnull(b2s.discount,1)* ifnull(dm.current_price,p.price))  >= " . $this->db->escape($data['min_price']);
            } else {
                $sql .= " AND (ifnull(b2s.discount,1)* ifnull(dm.current_price,p.price))  >= " . $this->db->escape($data['min_price']);
            }

        }
        if (isset($data['max_price']) && (!empty($data['max_price']) or $data['max_price'] == 0)) {
//            $sql .= " AND p.price <= " . $this->db->escape($data['max_price']);
            if (!$isCollectionFromDomicile) {
                //$sql .= " AND
                //         ifnull(b2s.discount,1)*
                //         (ifnull(dm.current_price,p.price) + ifnull(p.freight,0))
                //         <= " . $this->db->escape($data['max_price']);
                $sql .= " AND (ifnull(b2s.discount,1)* ifnull(dm.current_price,p.price)) <= " . $this->db->escape($data['max_price']);
            } else {
                $sql .= " AND (ifnull(b2s.discount,1)* ifnull(dm.current_price,p.price)) <= " . $this->db->escape($data['max_price']);
            }
        }
        if (isset($data['min_quantity']) && (!empty($data['min_quantity'] or $data['min_quantity'] == 0))) {
            $sql .= " AND p.quantity >= " . (int)$this->db->escape($data['min_quantity']);
        }
        if (isset($data['max_quantity']) && (!empty($data['max_quantity'] or $data['max_quantity'] == 0))) {
            $sql .= " AND p.quantity <= " . (int)$this->db->escape($data['max_quantity']);
        }

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
            } else {
                //假如filter_category_id为string的话
                if (is_string($data['filter_category_id'])) {
                    $sql .= " AND p2c.category_id IN (" . $data['filter_category_id'] . ")";
                } else {
                    $sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
                }

            }

            if (!empty($data['filter_filter'])) {
                $implode = array();

                $filters = explode(',', $data['filter_filter']);

                foreach ($filters as $filter_id) {
                    $implode[] = (int)$filter_id;
                }

                $sql .= " AND pf.filter_id IN (" . implode(',', $implode) . ")";
            }
        }

        if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
            //
            $this->load->model('tool/sphinx');
            $s = $this->model_tool_sphinx->getSearchProductId(trim($data['filter_name']));
            if ($s !== false && count($s)) {
                if (count($s)) {
                    $str = implode(',', $s);
                    $sql .= "AND p.product_id in (" . $str . ")";
                } else {
                    $sql .= "AND p.product_id  = 0";
                }

            } else {

                $sql .= " AND (";

                if (!empty($data['filter_name'])) {
                    $implode = array();

                    $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

                    foreach ($words as $word) {
                        $implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
                    }

                    if ($implode) {
                        $sql .= " " . implode(" AND ", $implode) . "";
                    }

                    if (!empty($data['filter_description'])) {
                        $sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
                    }
                }

                if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
                    $sql .= " OR ";
                }

                if (!empty($data['filter_tag'])) {
                    $implode = array();

                    $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_tag'])));

                    foreach ($words as $word) {
                        $implode[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
                    }

                    if ($implode) {
                        $sql .= " " . implode(" AND ", $implode) . "";
                    }
                }

                if (!empty($data['filter_name'])) {
                    $sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                    $sql .= " OR LCASE(p.sku) like '%" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
                    $sql .= " OR LCASE(p.upc) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                    $sql .= " OR LCASE(p.ean) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                    $sql .= " OR LCASE(p.jan) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                    $sql .= " OR LCASE(p.isbn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                    $sql .= " OR LCASE(p.mpn) like '%" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
                }

                $sql .= ")";
            }


        }

        if (!empty($data['filter_manufacturer_id'])) {
            $sql .= " AND p.manufacturer_id = '" . (int)$data['filter_manufacturer_id'] . "'";
        }
        // 排除头款产品
        $sql .= " AND p.product_type IN (0,3) ";
        $query = $this->db->query($sql);
        //add by allen.tai 获取
        if ($str_flag) {
            $res['product_total_str'] = $query->row['id_str'];
            $res['total'] = $query->row['total'];
            return $res;
        }


        return $query->row['total'];
    }

    /**
     * [getTotalIdProducts description] group_concat_max_len = 102400 php.ini需要设置
     * @param array $data
     * @return array
     * @deprecated
     */
    public function getTotalIdProducts($data = array())
    {
        $sql = "SELECT group_concat(distinct p2c.product_id) AS total";

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";
            } else {
                $sql .= " FROM " . DB_PREFIX . "product_to_category p2c";
            }

            if (!empty($data['filter_filter'])) {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product_filter pf ON (p2c.product_id = pf.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (pf.product_id = p.product_id)";
            } else {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
            }
        } else {
            $sql .= " FROM " . DB_PREFIX . "product p";
        }

        $filterArr = [
            'min_price',
            'max_price',
            'min_quantity',
            'max_quantity'
        ];

        $flag = false;
        foreach ($filterArr as $f) {
            if (isset($data[$f]) && (!empty($data[$f]) or (int)$data[$f] === 0)) {
                $flag = true;
            }
        }
        if ($flag) {
            $sql .= ' JOIN ' . DB_PREFIX . 'customerpartner_to_product as c2p ON p.product_id = c2p.product_id';
            $sql .= ' JOIN ' . DB_PREFIX . 'buyer_to_seller as b2s ON b2s.seller_id = c2p.customer_id';
        }

        $sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_product ctp ON (p.product_id = ctp.product_id) LEFT JOIN " . DB_PREFIX . "customer cu ON (ctp.customer_id=cu.customer_id) LEFT JOIN " . DB_PREFIX . "country cou ON (cu.country_id=cou.country_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer c2c on (c2c.customer_id = ctp.customer_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND c2c.`show` = 1 and cu.status=1 AND pd.language_id = '1' AND p.buyer_flag = '1' ";
        if (!empty($data['country'])) {
            $sql .= " AND cou.iso_code_3='" . $data['country'] . "'";
        }
        if (!empty($data['country_id'])) {
            $sql .= " AND cou.country_id='" . $data['country_id'] . "'";
        }

        if ($flag) {
            $sql .= ' AND b2s.buyer_id = ' . (int)$data['customer_id'];
            $sql .= ' AND b2s.buy_status = 1';
            $sql .= ' AND b2s.buyer_control_status = 1';
            $sql .= ' AND b2s.seller_control_status = 1';
        }

        // 添加价格和销量筛选条件
        if (isset($data['min_price']) && (!empty($data['min_price']) or $data['min_price'] == 0)) {
            $sql .= " AND p.price >= " . $this->db->escape($data['min_price']);
        }
        if (isset($data['max_price']) && (!empty($data['max_price']) or $data['max_price'] == 0)) {
            $sql .= " AND p.price <= " . $this->db->escape($data['max_price']);
        }
        if (isset($data['min_quantity']) && (!empty($data['min_quantity'] or $data['min_quantity'] == 0))) {
            $sql .= " AND p.quantity >= " . (int)$this->db->escape($data['min_quantity']);
        }
        if (isset($data['max_quantity']) && (!empty($data['max_quantity'] or $data['max_quantity'] == 0))) {
            $sql .= " AND p.quantity <= " . (int)$this->db->escape($data['max_quantity']);
        }

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
            } else {
                $sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
            }

            if (!empty($data['filter_filter'])) {
                $implode = array();

                $filters = explode(',', $data['filter_filter']);

                foreach ($filters as $filter_id) {
                    $implode[] = (int)$filter_id;
                }

                $sql .= " AND pf.filter_id IN (" . implode(',', $implode) . ")";
            }
        }

        if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
            $sql .= " AND (";

            if (!empty($data['filter_name'])) {
                $implode = array();

                $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

                foreach ($words as $word) {
                    $implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
                }

                if ($implode) {
                    $sql .= " " . implode(" AND ", $implode) . "";
                }

                if (!empty($data['filter_description'])) {
                    $sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
                }
            }

            if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
                $sql .= " OR ";
            }

            if (!empty($data['filter_tag'])) {
                $implode = array();

                $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_tag'])));

                foreach ($words as $word) {
                    $implode[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
                }

                if ($implode) {
                    $sql .= " " . implode(" AND ", $implode) . "";
                }
            }

            if (!empty($data['filter_name'])) {
                $sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.sku) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.upc) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.ean) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.jan) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.isbn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.mpn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
            }

            $sql .= ")";
        }

        if (!empty($data['filter_manufacturer_id'])) {
            $sql .= " AND p.manufacturer_id = '" . (int)$data['filter_manufacturer_id'] . "'";
        }

        $query = $this->db->query($sql);

        return $query->row['total'];
    }

    public function getProfile($product_id, $recurring_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "recurring r JOIN " . DB_PREFIX . "product_recurring pr ON (pr.recurring_id = r.recurring_id AND pr.product_id = '" . (int)$product_id . "') WHERE pr.recurring_id = '" . (int)$recurring_id . "' AND status = '1' AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'");

        return $query->row;
    }

    public function getProfiles($product_id)
    {   //付款周期 $this->language->get('error_recurring_required')
        $query = $this->db->query("SELECT rd.* FROM " . DB_PREFIX . "product_recurring pr JOIN " . DB_PREFIX . "recurring_description rd ON (rd.language_id = " . (int)$this->config->get('config_language_id') . " AND rd.recurring_id = pr.recurring_id) JOIN " . DB_PREFIX . "recurring r ON r.recurring_id = rd.recurring_id WHERE pr.product_id = " . (int)$product_id . " AND status = '1' AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' ORDER BY sort_order ASC");

        return $query->rows;
    }

    public function getTotalProductSpecials()
    {
        $query = $this->db->query("SELECT COUNT(DISTINCT ps.product_id) AS total FROM " . DB_PREFIX . "product_special ps LEFT JOIN " . DB_PREFIX . "product p ON (ps.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))");

        if (isset($query->row['total'])) {
            return $query->row['total'];
        } else {
            return 0;
        }
    }

    public function getProductInfoByProductId($product_id)
    {
        $sql = "SELECT round(p.height,2) height,round(p.weight,2) weight,round(p.length,2) length,round(p.width,2) width FROM " . DB_PREFIX . "product p WHERE p.product_id = " . (int)$product_id;
        $query = $this->db->query($sql);

        return $query->row;
    }

    public function getFreight($weight)
    {
        $sql = "select round(p.AccountingFreight,2) AccountingFreight from tb_sys_national_account_freight p where p.Ibs = ceil(" . $weight . ")";
        $query = $this->db->query($sql);

        return $query->row;
    }

    public function getAllFreight()
    {
        $sql = "select ceil(p.Ibs) weight,round(p.AccountingFreight,2) freight from tb_sys_national_account_freight p ";
        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getIsSelfSupport($product_id)
    {
        $sql = "SELECT ifnull(c2c.self_support,0) self_support FROM " . DB_PREFIX . "customerpartner_to_product p LEFT JOIN oc_customerpartner_to_customer c2c  on c2c.customer_id = p.customer_id  WHERE p.product_id = " . (int)$product_id;
        $query = $this->db->query($sql);

        return $query->row;
    }

    /**
     * 获取一组商品的属性值
     * @param array $productIds
     * @param int $optionId
     * @param int $customerId
     * @return array
     */
    public function getProductOptionValueByProductIds(array $productIds, int $optionId, int $customerId = 0): array
    {
        $queryOrigin = $this->orm
            ->table(DB_PREFIX . 'product_option_value as p')
            ->select(['op.name as attr', 'p.product_id'])
            ->leftJoin(DB_PREFIX . 'option_description as od', ['p.option_id' => 'od.option_id'])
            ->leftJoin(
                DB_PREFIX . 'option_value_description as op',
                [
                    'op.option_id' => 'p.option_id',
                    'op.option_value_id' => 'p.option_value_id'
                ]
            )
            ->where([
                'od.option_id' => $optionId,
                'op.language_id' => (int)$this->config->get('config_language_id'),
            ])
            ->whereIn('p.product_id', $productIds);
        // 覆盖自定义颜色
        if ($customerId) {
            $queryCustom = $this->orm
                ->table(DB_PREFIX . 'product_option_value as p')
                ->select(['cod.name as attr', 'p.product_id'])
                ->leftJoin(
                    DB_PREFIX . 'customer_option_description as cod',
                    [
                        'cod.option_id' => 'p.option_id',
                        'cod.option_value_id' => 'p.option_value_id',
                    ]
                )
                ->where([
                    'cod.customer_id' => $customerId,
                    'p.option_id' => $optionId,
                    'cod.language_id' => (int)$this->config->get('config_language_id'),
                ])
                ->whereIn('p.product_id', $productIds);
        }
        if (isset($queryCustom)) {
            $queryOrigin = $queryOrigin->union($queryCustom);
        }
        $res = $queryOrigin->get();
        if ($res->isEmpty()) {
            return [];
        }
        $ret = [];
        $res->each(function ($val) use (&$ret) {
            $ret[$val->product_id] = htmlspecialchars_decode($val->attr);
        });

        return $ret;
    }

    /**
     * 获取商品特定属性的值
     * 如果customerId存在 并且能够找到用户自定义的数据，商品的属性值将会被用户自定义的数据替换
     *
     * @param int $productId
     * @param int $optionId
     * @param int $customerId
     * @return string
     */
    public function getProductOptionValue(int $productId, int $optionId, int $customerId = 0): string
    {
        $res = $this->getProductOptionValueByProductIds([$productId], $optionId, $customerId);
        return $res[$productId] ?? '';
    }

    /**
     * 根据商品id获取单个商品的颜色
     *
     * @param int $product_id
     * @param int $customer_id
     * @return array
     */
    public function getProductColor($product_id, int $customer_id = 0)
    {
        return [
            'color' => $this->getProductOptionValue((int)$product_id, 13, $customer_id),
        ];
    }

    /**
     * 根据商品id获取单个商品的颜色 新颜色，上面的方法可能老代码还需要调用，单独开个新方法
     *
     * @param int $productId
     * @param int $customerId
     * @param int $optindId
     * @return array
     */
    public function getProductNewColorAndMaterial($productId, $customerId, $optindId)
    {
        return [
            'color_material' => $this->getProductOptionValue($productId, $optindId, $customerId),
        ];
    }

    public function getItemsInfoByProductId($product_id)
    {
        $sql = " SELECT
        psi.*
        , ROUND(p.length, 2) AS length, ROUND(p.width, 2) AS width, ROUND(p.height, 2) AS height, ROUND(p.weight, 2) AS weight
        , ROUND(p.length_cm, 2) AS length_cm, ROUND(p.width_cm, 2) AS width_cm, ROUND(p.height_cm, 2) AS height_cm, ROUND(p.weight_kg, 2) AS weight_kg
    FROM tb_sys_product_set_info AS psi
    LEFT JOIN oc_product AS p ON p.product_id=psi.set_product_id
    WHERE psi.product_id=" . (int)$product_id;
        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * @param int $product_id
     * @return array
     * @deprecated
     */
    public function getProductDescriptionByProductId($product_id)
    {
        $sql = " SELECT *
        ,round(p.length,2) as length,round(p.width,2) as width,round(p.height,2) as height,round(p.weight,2) as weight
        ,cd.name as product_category
        FROM oc_product p
        LEFT JOIN oc_product_description pd on p.product_id = pd.product_id
        LEFT JOIN oc_product_to_category p2c ON  p2c.product_id = p.product_id
        LEFT JOIN oc_category_description cd ON cd.category_id = p2c.category_id
        WHERE p.product_id = " . (int)$product_id;
        $query = $this->db->query($sql);

        return $query->row;
    }

    /**
     * @param array $productInfo oc_roduct表的一条记录
     * @return string
     */
    public function specificationForDownload($productInfo)
    {
        $productId = $productInfo['product_id'];
        $page_product_type_name = app(ProductRepository::class)->getProductTypeNameForBuyer($productInfo);
        $page_package_size_list = app(ProductRepository::class)->getPackageSizeForBuyer($productInfo, $this->customer->getCountryId());

        //region 产品的颜色材质等信息
        $productOption = app(ProductOptionRepository::class)->getProductOptionByProductId($productId);
        $color_name = isset($productOption['color_name']) ? $productOption['color_name'] : '';
        $material_name = isset($productOption['material_name']) ? $productOption['material_name'] : '';
        $pageColorName = __('主要颜色', [], 'catalog/view/pro/product/addproduct');
        $pageMaterialName = __('主要材质', [], 'catalog/view/pro/product/addproduct');
        $installationManualName = __('是否安装手册是必须的', [], 'catalog/view/pro/product/addproduct');
        //endregion

        $html = '
<table style="border: 1px solid #dbdbdb;border-collapse: collapse;width: 60%; margin-bottom: 20px;line-height: 35px;">
    <tbody>
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Item Code</td>
            <td style="border: 1px solid #dbdbdb;">' . $productInfo['sku'] . '</td>
        </tr>
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">MPN</td>
            <td style="border: 1px solid #dbdbdb;">' . $productInfo['mpn'] . '</td>
        <tr>
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Product Type</td>
            <td style="border: 1px solid #dbdbdb;">' . $page_product_type_name . '</td>
        </tr>
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Product Name</td>
            <td style="border: 1px solid #dbdbdb;">' . $productInfo['name'] . '</td>
        </tr>';
        if ($color_name) {
            $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">' . $pageColorName . '</td>
            <td style="border: 1px solid #dbdbdb;">' . $color_name . '</td>
        </tr>
            ';
        }
        if ($material_name) {
            $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">' . $pageMaterialName . '</td>
            <td style="border: 1px solid #dbdbdb;">' . $material_name . '</td>
        </tr>
            ';
        }
        if (mb_strlen($productInfo['product_size'])) {
            $html .= '
        <tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Product Size</td>
            <td style="border: 1px solid #dbdbdb;">' . $productInfo['product_size'] . '</td>
        </tr>
            ';
        }

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
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">Package Size</td>
            <td style="border: 1px solid #dbdbdb;">' . $item['msg'] . '</td>
        </tr>';
            }
        }
        $YesNo = ($productInfo['need_install']) ? 'Yes' : 'No';
        if ($productInfo['need_install'] == 1) {
            $html .= '<tr>
            <td style="border: 1px solid #dbdbdb; background-color:#f5f5f5;">' . $installationManualName . '</td>
            <td style="border: 1px solid #dbdbdb;">' . $YesNo . '</td>
        </tr>';
        }
        $html .= '
        </tbody>
        </table>';
        return $html;
    }


    /**
     * 根据父combo的productId查询出子商品的尺寸信息
     * @param int $product_id
     * @return
     */
    public function getComboDimensionByProductId($product_id)
    {
        $sql = 'SELECT round(p.length,2) as length,round(p.width,2) as width,round(p.height,2) as height,round(p.weight,2) as weight,psi.qty
            FROM tb_sys_product_set_info psi INNER JOIN oc_product p ON p.product_id = psi.set_product_id
            WHERE psi.product_id = ' . (int)$product_id;
        $query = $this->db->query($sql);

        return $query->rows;
    }

    //add by xxli
    public function getDiscount($buyer_id = null, $seller_id = null)
    {
        static $mapRes = [];
        $key = (int)$buyer_id . '_' . (int)$seller_id;
        if (isset($mapRes[$key])) {
            return $mapRes[$key];
        }
        $sql = "Select discount from oc_buyer_to_seller where buyer_id = " . (int)$buyer_id . " and seller_id =" . (int)$seller_id;
        $query = $this->db->query($sql);
        $mapRes[$key] = $query->row;
        return $mapRes[$key];
    }

    public function getDiscountByProductId($customer_id, $product_id)
    {
        $sql = "SELECT b2c.discount FROM oc_buyer_to_seller b2c INNER JOIN oc_customerpartner_to_product ctp ON b2c.seller_id = ctp.customer_id AND b2c.buyer_id = " . (int)$customer_id . " AND ctp.product_id = " . (int)$product_id;
        $query = $this->db->query($sql);

        return $query->row;
    }

    public function getDiscountPrice($price, $discountResult)
    {
        if ($discountResult) {
            $discount = $discountResult['discount'];
            $discountPrice = $price * $discount;
            $price = round($discountPrice, 2);

        }
        return $price;
    }

    public function getTag($product_id, $tag_status = 1)
    {
        $query = $this->db->query("SELECT tag.description,tag.icon,tag.tag_id,tag.class_style FROM oc_product p INNER JOIN oc_product_to_tag pt ON p.product_id = pt.product_id LEFT JOIN oc_tag tag ON tag.tag_id = pt.tag_id WHERE tag.status = " . (int)$tag_status . " AND p.product_id = " . (int)$product_id . " ORDER BY tag.sort_order ASC");

        $this->load->model('tool/image');
        if (isset($query->rows)) {
            $tag = array();
            foreach ($query->rows as $value) {
                $tag[] = array(
                    'description' => $value['description'],
                    'icon' => $this->model_tool_image->resize($value['icon'], 15, 15),
                    'tag_id' => $value['tag_id'],
                    'origin_icon' => $value['icon'],
                    'class_style' => $value['class_style'],
                );
            }
            return $tag;
        }
        return [];
    }

    public function getProductSpecificTag($product_id, $tag_id = null, $tag_status = 1)
    {
        $sql = 'SELECT tag.tag_id,tag.class_style,tag.description,tag.icon FROM oc_product p INNER JOIN oc_product_to_tag pt ON p.product_id = pt.product_id LEFT JOIN oc_tag tag ON tag.tag_id = pt.tag_id WHERE tag.status = ' . (int)$tag_status . ' AND p.product_id = ' . (int)$product_id;
        if (isset($tag_id)) {
            $sql .= ' AND tag.tag_id = ' . (int)$tag_id;
        }
        $sql .= ' GROUP BY tag.description,tag.icon';
        $query = $this->db->query($sql);
        $this->load->model('tool/image');
        //两种数据方式
        if (!isset($tag_id)) {
            if (isset($query->rows)) {
                $tag = array();
                foreach ($query->rows as $value) {
                    $tag[] = array(
                        'description' => $value['description'],
                        'icon' => $this->model_tool_image->resize($value['icon'], 15, 15),
                        'origin_icon' => $value['icon'], //扩展个原始icon路径
                        'tag_id' => $value['tag_id'],
                        'class_style' => $value['class_style'],
                    );
                }
                return $tag;
            }
        } else {
            if (isset($query->rows) && !empty($query->rows)) {
                $row = $query->row;
                $tag = array(
                    'description' => $row['description'],
                    'icon' => $this->model_tool_image->resize($row['icon'], 15, 15),
                    'origin_icon' => $row['icon'],
                    'tag_id' => $row['tag_id'],
                    'class_style' => $row['class_style'],
                );
                return $tag;
            }
        }
        return [];
    }

    /**
     * 获取订单明细是否包含特定标签
     * @param $line_id
     * @param $tag_id
     * @param int $tag_status
     * @return array
     */
    public function getProductSpecificTagByOrderLineId($line_id, $tag_id = null, $tag_status = 1)
    {
        // 需要根据国别
        $countryId = Customer()->getCountryId() ?? AMERICAN_COUNTRY_ID;
        $sql = 'SELECT tag.tag_id,tag.class_style,tag.description,tag.icon
                    FROM tb_sys_customer_sales_order tbo
                    INNER JOIN tb_sys_customer_sales_order_line tbl ON tbo.id = tbl.header_id
                    INNER JOIN oc_product p ON p.sku = tbl.item_code
                    INNER JOIN oc_customerpartner_to_product ctp on ctp.product_id = p.product_id
                    INNER JOIN oc_customer c on c.customer_id = ctp.customer_id
                    INNER JOIN oc_product_to_tag ptt ON ptt.product_id = p.product_id
                    INNER JOIN oc_tag tag ON tag.tag_id = ptt.tag_id
                WHERE tbl.id = ' . (int)$line_id . ' AND tag.status = ' . (int)$tag_status . ' AND c.country_id=' . $countryId;
        if (isset($tag_id)) {
            $sql .= ' AND tag.tag_id = ' . (int)$tag_id;
        }
        $sql .= ' GROUP BY tag.description,tag.icon';
        $query = $this->db->query($sql);
        $this->load->model('tool/image');
        //两种数据方式
        if (!isset($tag_id)) {
            if (isset($query->rows)) {
                $tag = array();
                foreach ($query->rows as $value) {
                    $tag[] = array(
                        'description' => $value['description'],
                        'icon' => $this->model_tool_image->resize($value['icon'], 15, 15),
                        'tag_id' => $value['tag_id'],
                        'origin_icon' => $value['icon'],
                        'class_style' => $value['class_style'],
                    );
                }
                return $tag;
            }
        } else {
            if (isset($query->rows) && !empty($query->rows)) {
                $row = $query->row;
                $tag = array(
                    'description' => $row['description'],
                    'icon' => $this->model_tool_image->resize($row['icon'], 15, 15),
                    'tag_id' => $row['tag_id'],
                    'origin_icon' => $row['icon'],
                    'class_style' => $row['class_style'],
                );
                return $tag;
            }
        }
        return [];
    }

    /**
     * 获取订单整体是否包含特定标签
     *
     *
     * @param int $header_id
     * @param $tag_id
     * @param int $tag_status
     * @return array
     */
    public function getProductSpecificTagByOrderHeaderId($header_id, $tag_id = null, $tag_status = 1)
    {
        $sql = 'SELECT tag.tag_id,tag.class_style,tag.description,tag.icon
FROM tb_sys_customer_sales_order tbo
INNER JOIN tb_sys_customer_sales_order_line tbl ON tbo.id = tbl.header_id
INNER JOIN oc_product p ON p.sku = tbl.item_code
INNER JOIN oc_product_to_tag ptt ON ptt.product_id = p.product_id
INNER JOIN oc_tag tag ON tag.tag_id = ptt.tag_id
WHERE tbo.id = ' . (int)$header_id . ' AND tag.status = ' . (int)$tag_status . ' AND tbl.item_status != ' . CustomerSalesOrderLineItemStatus::DELETED;
        if (isset($tag_id)) {
            $sql .= ' AND tag.tag_id = ' . (int)$tag_id;
        }
        $sql .= ' GROUP BY tag.description,tag.icon';
        $query = $this->db->query($sql);

        $this->load->model('tool/image');
        //两种数据方式
        if (!isset($tag_id)) {
            if (isset($query->rows)) {
                $tag = array();
                foreach ($query->rows as $value) {
                    $tag[] = array(
                        'description' => $value['description'],
                        'icon' => $this->model_tool_image->resize($value['icon'], 15, 15),
                        'tag_id' => $value['tag_id'],
                        'origin_icon' => $value['icon'],
                        'class_style' => $value['class_style'],
                    );
                }
                return $tag;
            }
        } else {
            if (isset($query->rows) && !empty($query->rows)) {
                $row = $query->row;
                $tag = array(
                    'description' => $row['description'],
                    'icon' => $this->model_tool_image->resize($row['icon'], 15, 15),
                    'tag_id' => $row['tag_id'],
                    'origin_icon' => $row['icon'],
                    'class_style' => $row['class_style'],
                );
                return $tag;
            }
        }
        return [];
    }

    /**
     * 查询oc_order 采购订单的特定标签
     * @param $oc_order_id
     * @param $tag_id
     * @param null $seller_id
     * @param int $tag_status
     * @param null $product_id
     * @return array
     * @throws Exception
     */
    public function getOcOrderSpecificTag($oc_order_id, $tag_id = null, $seller_id = null, $tag_status = 1, $product_id = null)
    {
        if (isset($seller_id)) {
            $sql = "SELECT
                      tag.tag_id,
                      tag.description,
                      tag.icon
                    FROM
                      oc_order co,
                      oc_order_product cop,
                      oc_product_to_tag ptt,
                      oc_tag tag,
                      oc_customerpartner_to_product ctp
                    WHERE co.order_id = cop.order_id
                      AND cop.product_id = ptt.product_id
                      AND tag.tag_id = ptt.tag_id
                      AND ctp.product_id = cop.product_id" . " AND ctp.customer_id = " . (int)$seller_id;
        } else {
            $sql = "SELECT
                      tag.tag_id,
                      tag.description,
                      tag.icon
                    FROM
                      oc_order co,
                      oc_order_product cop,
                      oc_product_to_tag ptt,
                      oc_tag tag
                    WHERE co.order_id = cop.order_id
                      AND cop.product_id = ptt.product_id
                      AND tag.tag_id = ptt.tag_id";
        }

        if (isset($tag_id)) {
            $sql .= ' AND tag.tag_id = ' . (int)$tag_id;
        }
        if (isset($oc_order_id)) {
            $sql .= ' AND co.order_id = ' . (int)$oc_order_id;
        }
        if (isset($tag_status)) {
            $sql .= ' AND tag.status = ' . (int)$tag_status;
        }
        if (isset($product_id)) {
            $sql .= ' AND cop.product_id = ' . (int)$product_id;
        }

        $sql .= ' GROUP BY tag.description,tag.icon';
        $query = $this->db->query($sql);
        $this->load->model('tool/image');
        //两种数据方式
        if (!isset($tag_id)) {
            if (isset($query->rows)) {
                $tag = array();
                foreach ($query->rows as $value) {
                    $tag[] = array(
                        'tag_id' => $value['tag_id'],
                        'description' => $value['description'],
                        'icon' => $this->model_tool_image->resize($value['icon'], 15, 15)
                    );
                }
                return $tag;
            }
        } else {
            if (isset($query->rows) && !empty($query->rows)) {
                $row = $query->row;
                $tag = array(
                    'tag_id' => $row['tag_id'],
                    'description' => $row['description'],
                    'icon' => $this->model_tool_image->resize($row['icon'], 15, 15)
                );
                return $tag;
            }
        }
    }

    public function checkIsOversizeItem($product_id)
    {
        $query = $this->db->query("SELECT IF(COUNT(*) = 0,'false','true') AS is_oversize FROM oc_product p INNER JOIN oc_product_to_tag pt ON p.product_id = pt.product_id LEFT JOIN oc_tag tag ON tag.tag_id = pt.tag_id WHERE tag.status = 1 AND tag.tag_id = 1 AND p.product_id = " . (int)$product_id);
        $is_oversize = false;
        if (isset($query->row)) {
            $row = $query->row;
            if ('true' === $row['is_oversize']) {
                $is_oversize = true;
            } else {
                $is_oversize = false;
            }
        }
        return $is_oversize;
    }

    public function checkIsComboItem($product_id)
    {
        $query = $this->db->query("SELECT p.combo_flag FROM oc_product p WHERE p.product_id = " . (int)$product_id);
        $is_oversize = false;
        if (isset($query->row)) {
            $row = $query->row;
            if ('1' == $row['combo_flag']) {
                $is_oversize = true;
            } else {
                $is_oversize = false;
            }
        }
        return $is_oversize;
    }


    /**
     * 返回商品待更新的生效时间
     * @param int $product_id
     * @return array
     * @author chenyang
     */
    public function productPriceChangeTime($product_id)
    {
        $sql = $this->db->query("SELECT new_price,effect_time FROM oc_seller_price WHERE product_id = " . (int)$product_id . " AND `status` = 1");

        if (isset($sql->rows) && $sql->num_rows === 1) {
            $date_str = $sql->row;
            $price_change = array(
                'new_price' => $date_str['new_price'],
                'effect_time' => strtotime($date_str['effect_time'])
            );
            return $price_change;
        }
    }

    /**
     * 判断该商品价格是否会变化 // 暂时使用oc_delicacy_management allen.tai 2019.09.04
     *
     * @param int $product_id
     * @param int $customer_id
     * @return mixed
     */
    public function checkPriceWillChange($product_id, $customer_id)
    {
        $sql = "SELECT 'sp' as `type`,sp.new_price,sp.effect_time FROM oc_seller_price sp WHERE sp.effect_time > NOW() AND sp.product_id = " . (int)$product_id . " AND sp.status = 1
                UNION ALL
                SELECT 'dm',dm.price,dm.effective_time FROM oc_delicacy_management dm WHERE dm.buyer_id = " . (int)$customer_id . " AND dm.product_id = " . (int)$product_id . " AND dm.effective_time > NOW()
                AND NOT EXISTS (
                    SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                        JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                        JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                    WHERE
                        bgl.buyer_id = " . (int)$customer_id . " AND pgl.product_id = " . (int)$product_id . "
                        AND dmg.status=1 and bgl.status=1 and pgl.status=1
                )";
        $query = $this->db->query($sql);
        return $query->rows;
    }

    /**
     * 查询商品的原始价格
     *
     * @param int $product_id
     * @return mixed
     */
    public function getProductCurrentPrice($product_id)
    {
        $sql = "SELECT price FROM oc_product WHERE product_id = " . (int)$product_id;
        $query = $this->db->query($sql);
        return $query->row;
    }


    public function getMaterial($product_id)
    {
        $sql1 = "select count(1) as imageCount from oc_product_package_image where product_id = " . (int)$product_id;
        $imageCount = $this->db->query($sql1)->row;
        if ($imageCount['imageCount'] > 0) {
            return 1;
        }
        $sql2 = "select count(1) as fileCount from oc_product_package_file where product_id = " . (int)$product_id;
        $fileCount = $this->db->query($sql2)->row;
        if ($fileCount['fileCount'] > 0) {
            return 1;
        }
        $sql3 = "select count(1) as videoCount from oc_product_package_video where product_id = " . (int)$product_id;
        $videoCount = $this->db->query($sql3)->row;
        if ($videoCount['videoCount'] > 0) {
            return 1;
        }
        return 0;
    }

    public function getAuthorStatusByBuyerIdAndProductId($buyer_id, $product_id)
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customerpartner_to_product ctp INNER JOIN " . DB_PREFIX . "buyer_to_seller bts ON ctp.`customer_id` = bts.`seller_id` WHERE ctp.`product_id` = '" . $product_id . "' AND bts.`buyer_id` = " . $buyer_id);
        if (isset($query->row['total'])) {
            return $query->row['total'];
        } else {
            return 0;
        }
    }
    //end

    /**
     * 获取特定商品的关联商品信息
     *
     * @param int $product_id
     * @return array
     */
    public function getAssociateProduct(int $product_id): array
    {
        $res = $this->orm
            ->table(DB_PREFIX . 'product_associate as pa')
            ->select('pa.associate_product_id')
            ->leftJoin(DB_PREFIX . 'product as p', ['pa.associate_product_id' => 'p.product_id'])
            ->where([
                'pa.product_id' => $product_id,
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
            ])
            ->get();
        $colors = $this->getProductOptionValueByProductIds(
            $res->pluck('associate_product_id')->toArray(),
            13, // 硬编码 color在attribute里id 为13
            $this->getProductCustomerId($product_id)
        );
        $res = $res->map(function ($item) use ($colors) {
            $item = get_object_vars($item);
            $item['name'] = $colors[$item['associate_product_id']] ?? '';
            return $item;
        });

        return $res->toArray();
    }

    /**
     * 获取商品关联用户id
     * @param int $productId
     * @return int
     */
    public function getProductCustomerId(int $productId): int
    {
        $customer = $this->orm
            ->table(DB_PREFIX . 'customerpartner_to_product')
            ->where(['product_id' => $productId])
            ->first();

        return (int)($customer ? $customer->customer_id : 0);
    }

    /**
     * 根据product_id查询在库数量（包括combo产品）
     * @param int $product_id
     * @return array
     */
    public function queryStockByProductId($product_id)
    {
        $product_id = intval($product_id);
        $result = ['total_onhand_qty' => 0, 'total_original_qty' => 0];
        $product_sql = "SELECT * from oc_product where product_id = $product_id";
        $product = $this->db->query($product_sql)->row;
        if (!empty($product)) {
            if ($product['combo_flag'] != '1') {
                //查询在库数量
                $stockSql = "SELECT t1.product_id,
SUM(onhand_qty) AS total_onhand_qty,
SUM(original_qty) AS total_original_qty
  FROM `tb_sys_batch` t1 WHERE t1.product_id = $product_id";
                $stock_row = $this->db->query($stockSql)->row;
                if (isset($stock_row['total_onhand_qty'])) {
                    $result['total_onhand_qty'] = (int)$stock_row['total_onhand_qty'];
                }
                if (isset($stock_row['total_original_qty'])) {
                    $result['total_original_qty'] = (int)$stock_row['total_original_qty'];
                }
            } else {
                //查询combo产品在库数量
                //从子mpn中找到最少的在库数量  即为combo的在库数量
                //      product_id       combo_flag      tb_sys_product_set_info表     tb_sys_batch表
                //       a                  1               product_id:a                    null
                //       |- a1              0               set_product_id : a1             有记录
                //       |- a2              0               set_product_id : a2             有记录
                $set_info_rows = $this->db->query('select set_product_id,qty from tb_sys_product_set_info WHERE product_id =' . $product_id . ' and set_product_id is not null')->rows;
                if (!empty($set_info_rows)) {
                    $set_infos = [];
                    foreach ($set_info_rows as $row) {
                        $set_infos[$row['set_product_id']] = $row;
                    }
                    $stock_rows = $this->db->query('SELECT bach.product_id,
SUM(bach.onhand_qty) AS total_onhand_qty,
SUM(bach.original_qty) AS total_original_qty
FROM  tb_sys_batch bach
WHERE bach.product_id   in (' . implode(',', array_keys($set_infos)) . ')
group by bach.product_id')->rows;
                    if (!empty($stock_rows)) {
                        $stocks = [];
                        foreach ($stock_rows as $row) {
                            $stocks[$row['product_id']] = $row;
                        }
                        $min_set_onhand_qty = null;
                        $min_set_original_qty = null;
                        foreach ($set_infos as $product_id => $set_info) {
                            if (!isset($stocks[$product_id])) {
                                //如果有一个子combo没有库存  那整个combo库存就为0
                                return ['total_onhand_qty' => 0, 'total_original_qty' => 0];
                            } else {
                                //找到最小库存  即为整个combo库存
                                $set_onhand_qty = floor((int)$stocks[$product_id]['total_onhand_qty'] / (int)$set_info['qty']);
                                $set_original_qty = floor((int)$stocks[$product_id]['total_original_qty'] / (int)$set_info['qty']);
                                if (is_null($min_set_onhand_qty) || $set_onhand_qty < $min_set_onhand_qty) {
                                    $min_set_onhand_qty = $set_onhand_qty;
                                }
                                if (is_null($min_set_original_qty) || $set_original_qty < $min_set_original_qty) {
                                    $min_set_original_qty = $set_original_qty;
                                }
                            }
                        }
                        if (!is_null($min_set_onhand_qty)) {
                            $result['total_onhand_qty'] = $min_set_onhand_qty;
                        }
                        if (!is_null($min_set_original_qty)) {
                            $result['total_original_qty'] = $min_set_original_qty;
                        }
                    }
                }
            }
        }
        return $result;
    }

    public function getProductForOrderHistory($product_id, $customFields = null)
    {
        $product_id = (int)$product_id;
        $product_status = 1;
        $product_buyer_flag = 1;
        $commission_amount = 0;

        if (($this->config->get('module_marketplace_status') && isset($this->request->get['user_token']) && isset($this->session->data['user_token']) && isset($this->session->data['user_id']) && $this->request->get['user_token'] == $this->session->data['user_token']) || ($this->config->get('module_marketplace_status') && isset($this->request->get['product_token']) && isset($this->session->data['product_token']) && $this->request->get['product_token'] == $this->session->data['product_token'])) {
            $product_status_array = $this->db->query("SELECT status,buyer_flag FROM " . DB_PREFIX . "product WHERE product_id = " . (int)$product_id)->row;
            if (isset($product_status_array['status'])) {
                $product_status = $product_status_array['status'];
            }
            if (isset($product_status_array['buyer_flag'])) {
                $product_buyer_flag = $product_status_array['buyer_flag'];
            }
            if (!$product_status) {
                $this->db->query("UPDATE " . DB_PREFIX . "product SET status = '1' WHERE product_id = " . $product_id);
            }
            if (!$product_buyer_flag) {
                $this->db->query("UPDATE " . DB_PREFIX . "product SET buyer_flag = '1' WHERE product_id = " . $product_id);
            }
        }
        $sql = "";
        if ($customFields) {
            $sql .= "SELECT DISTINCT *,c2c.screenname,p.product_id as productId,p.aHref,ifnull(c2c.self_support,0) as self_support,pd.summary_description,c2p.seller_price,c2p.quantity as c2pQty,case when c2p.customer_id in (select seller_id  from oc_buyer_to_seller b2s where b2s.buyer_id = " . $customFields . " and b2s.buy_status = 1 and b2s.buyer_control_status =1 and b2s.seller_control_status = 1 ) then 1 else 0 end as canSell, pd.name AS name, p.image, m.name AS manufacturer, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special, (SELECT points FROM " . DB_PREFIX . "product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "') AS reward, (SELECT ss.name FROM " . DB_PREFIX . "stock_status ss WHERE ss.stock_status_id = p.stock_status_id AND ss.language_id = '" . (int)$this->config->get('config_language_id') . "') AS stock_status, (SELECT wcd.unit FROM " . DB_PREFIX . "weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS weight_class, (SELECT lcd.unit FROM " . DB_PREFIX . "length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS length_class, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r2 WHERE r2.product_id = p.product_id AND r2.status = '1' GROUP BY r2.product_id) AS reviews, p.sort_order FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)  LEFT JOIN " . DB_PREFIX . "customerpartner_to_product c2p ON (c2p.product_id = p.product_id )  LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )  LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id ) WHERE p.product_id = '" . (int)$product_id . "' AND c2c.`show` = 1  AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";
        } else {
            $sql .= "SELECT DISTINCT *,c2c.screenname,p.product_id as productId,p.aHref,ifnull(c2c.self_support,0) as self_support,0 as canSell,pd.summary_description,c2p.seller_price,c2p.quantity as c2pQty, pd.name AS name, p.image, m.name AS manufacturer, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special, (SELECT points FROM " . DB_PREFIX . "product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "') AS reward, (SELECT ss.name FROM " . DB_PREFIX . "stock_status ss WHERE ss.stock_status_id = p.stock_status_id AND ss.language_id = '" . (int)$this->config->get('config_language_id') . "') AS stock_status, (SELECT wcd.unit FROM " . DB_PREFIX . "weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS weight_class, (SELECT lcd.unit FROM " . DB_PREFIX . "length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS length_class, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r2 WHERE r2.product_id = p.product_id AND r2.status = '1' GROUP BY r2.product_id) AS reviews, p.sort_order FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)  LEFT JOIN " . DB_PREFIX . "customerpartner_to_product c2p ON (c2p.product_id = p.product_id ) LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )  LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id )  WHERE p.product_id = '" . (int)$product_id . "' AND c2c.`show` = 1   AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";
        }
        $query = $this->db->query($sql);
        /**
         * Marketplace Code Starts Here
         */
        if ($this->config->get('module_marketplace_status') && $query->num_rows) {
            $check_seller_product = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_product WHERE product_id = '" . (int)$product_id . "'")->row;

            if ($check_seller_product) {

                $this->load->model('account/customerpartnerorder');

                if ($this->config->get('marketplace_commission_tax')) {

                    $commission_array = $this->model_account_customerpartnerorder->calculateCommission(array('product_id' => $product_id, 'product_total' => $this->tax->calculate($query->row['price'], $query->row['tax_class_id'], $this->config->get('config_tax'))), $check_seller_product['customer_id']);
                } else {
                    $commission_array = $this->model_account_customerpartnerorder->calculateCommission(array('product_id' => $product_id, 'product_total' => $query->row['price']), $check_seller_product['customer_id']);

                }

                if ($commission_array && isset($commission_array['commission']) && $this->config->get('marketplace_commission_unit_price')) {
                    $commission_amount = $commission_array['commission'];
                }
                $check_seller_status = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_customer WHERE customer_id = '" . (int)$check_seller_product['customer_id'] . "' AND is_partner = '1'")->row;

                if ($this->config->get('module_marketplace_status') && !$this->config->get('marketplace_sellerproductshow') && !$check_seller_status) {

                    return false;
                }
            }

        }

        if (!$product_status) {
            $this->db->query("UPDATE " . DB_PREFIX . "product SET status = '0' WHERE product_id = " . $product_id);
        }
        if (!$product_buyer_flag) {
            $this->db->query("UPDATE " . DB_PREFIX . "product SET buyer_flag = '0' WHERE product_id = " . $product_id);
        }
        /**
         * Marketplace Code Ends Here
         */

        //add by xxli
        $resultTotal = $this->db->query("select ifnull(sum(c2o.quantity),0) as totalSales from oc_customerpartner_to_order c2o left join oc_order ord on ord.order_id = c2o.order_id where ord.order_status_id = ".OcOrderStatus::COMPLETED." and c2o.product_id = " . $product_id)->row;
        if ($resultTotal['totalSales'] > 99999) {
            $totalSale = '99999+';
        } else {
            $totalSale = $resultTotal['totalSales'];
        }
        $result30 = $this->db->query("select ifnull(sum(c2o.quantity),0) as 30Day from oc_customerpartner_to_order c2o left join oc_order ord on ord.order_id = c2o.order_id where ord.order_status_id = ".OcOrderStatus::COMPLETED." and ord.date_added >=DATE_SUB(CURDATE(),INTERVAL 30 DAY) and c2o.product_id = " . $product_id)->row;
        if ($result30['30Day'] > 999) {
            $day30Sale = '999+';
        } else {
            $day30Sale = $result30['30Day'];
        }
        if (isset($query->row['viewed'])) {
            if ($query->row['viewed'] > 99999) {
                $pageView = '99999+';
            } else {
                $pageView = $query->row['viewed'];
            }
        }
        //end by xxli

        if ($query->num_rows) {
            return array(
                'screenname' => $query->row['screenname'],
                'totalSale' => $totalSale,
                '30Day' => $day30Sale,
                'pageView' => $pageView,
                'customer_id' => $query->row['customer_id'],
                'self_support' => $query->row['self_support'],
                'summary_description' => $query->row['summary_description'],
                'price_display' => $query->row['price_display'],
                'quantity_display' => $query->row['quantity_display'],
                'aHref' => $query->row['aHref'],
                'canSell' => $query->row['canSell'],
                'seller_price' => $query->row['seller_price'],
                'c2pQty' => $query->row['c2pQty'],
                'product_id' => $query->row['productId'],
                'name' => $query->row['name'],
                'description' => $query->row['description'],
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

                'price' => ($query->row['discount'] ? $query->row['discount'] : $query->row['price']) + $commission_amount,

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

                'commission_amount' => $commission_amount,

                'viewed' => $query->row['viewed'],
                //配件标识
                'part_flag' => $query->row['part_flag']
            );
        } else {
            return false;
        }
    }

    public function getBestSellProduct($country, $customFields = null)
    {
        if ($customFields == null) {
            $sql = "SELECT cto.product_id,sum(cto.quantity) AS qty FROM oc_customerpartner_to_order cto LEFT JOIN oc_product op ON op.product_id = cto.product_id LEFT JOIN oc_customer c ON c.customer_id = cto.customer_id LEFT JOIN oc_country cou ON cou.country_id = c.country_id WHERE op.`status` = 1 AND op.buyer_flag = 1 ";
            $sql .= " AND op.quantity > 20 AND cou.iso_code_3 = '" . $country . "' GROUP BY cto.product_id ORDER BY qty DESC LIMIT 12";
        } else {
            $sql = "SELECT
                        cto.product_id,
                        sum(cto.quantity) AS qty,
                        dm.current_price
                    FROM
                        oc_customerpartner_to_order cto
                    LEFT JOIN oc_product op ON op.product_id = cto.product_id
                    LEFT JOIN oc_customer c ON c.customer_id = cto.customer_id
                    LEFT JOIN oc_country cou ON cou.country_id = c.country_id
                    LEFT JOIN oc_delicacy_management dm on op.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id=" . $customFields . "
                    WHERE
                        op.`status` = 1
                    AND op.buyer_flag = 1
                    AND op.quantity > 20
                    AND cou.iso_code_3 = '" . $country . "'
                    and (dm.product_display = 1 or dm.product_display is null)
                    AND NOT EXISTS (
                        SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                        JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                        JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                        WHERE
                            dmg.seller_id = cto.customer_id  AND bgl.seller_id = cto.customer_id AND pgl.seller_id = cto.customer_id
                            AND bgl.buyer_id = " . $customFields . "   AND pgl.product_id = cto.product_id
                            AND dmg.status=1 and bgl.status=1 and pgl.status=1
                    )
                    GROUP BY
                        cto.product_id
                    ORDER BY
                        qty DESC
                    LIMIT 12";
        }
        return $this->db->query($sql)->rows;
    }

    public function getNewArrivalProduct($country, $customFields)
    {
        if ($customFields == null) {
            $sql = "select DISTINCT (tsb.product_id) from tb_sys_batch tsb LEFT JOIN oc_customer c on tsb.customer_id = c.customer_id LEFT JOIN oc_country cou on cou.country_id = c.country_id  LEFT JOIN oc_product p ON p.product_id = tsb.product_id where c.customer_group_id = 2 AND p.quantity>20 AND p.`status` = 1  AND p.buyer_flag = 1 AND cou.iso_code_3 = '" . $country . "'ORDER BY 	tsb.receive_date DESC limit 12";
        } else {
            $sql = "SELECT DISTINCT
                            (tsb.product_id)
                        FROM
                            tb_sys_batch tsb
                        LEFT JOIN oc_customer c ON tsb.customer_id = c.customer_id
                        LEFT JOIN oc_country cou ON cou.country_id = c.country_id
                        LEFT JOIN oc_product p ON p.product_id = tsb.product_id
                        LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id=" . $customFields . "
                        WHERE
                            c.customer_group_id = 2
                        AND p.quantity > 20
                        AND p.`status` = 1
                        AND p.buyer_flag = 1
                        AND cou.iso_code_3 = '" . $country . "'
                        and (dm.product_display = 1 or dm.product_display is null)
                        AND NOT EXISTS (
                            SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                            JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                            JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                            WHERE
                                dmg.seller_id = tsb.customer_id  AND bgl.seller_id = tsb.customer_id AND pgl.seller_id = tsb.customer_id
                                AND bgl.buyer_id = " . $customFields . " AND pgl.product_id = tsb.product_id
                                AND dmg.status=1 and bgl.status=1 and pgl.status=1
                        )
                        ORDER BY
                            tsb.receive_date DESC
                        LIMIT 12";
        }
        return $this->db->query($sql)->rows;
    }

    public function packageDownloadHistory($product_id)
    {
        $this->db->query("update oc_product set downloaded = downloaded+1 where product_id =" . $product_id);
        $this->db->query("insert into tb_sys_product_package_info (product_id,customer_id,createUserName,CreateTime,ProgramCode) VALUES (" . $product_id . "," . $this->customer->getId() . "," . $this->customer->getId() . ",now(),'" . PROGRAM_CODE . "')");
    }


    /**
     * 获取product的基本信息
     * @param int $product_id
     * @return array
     */
    public function getProductBaseInfo($product_id)
    {
        $result = $this->orm->table('oc_product as p')
            ->leftJoin('oc_product_description as pd', 'pd.product_id', '=', 'p.product_id')
            ->select(['p.product_id', 'p.sku', 'p.mpn', 'p.quantity', 'pd.name', 'p.price', 'p.freight'])
            ->where('p.product_id', (int)$product_id)
            ->first();
        if (!$result) return [];
        $result->name = html_entity_decode($result->name);

        return obj2array($result);
    }

    /**
     * 处理20210430batch download临时问题处理
     * @param string $id_str
     * @param int $custom_id BuyerId
     * @param bool $seller_flag
     * @return array
     * @throws Exception
     */
    public function getProductCategoryInfoByMySeller($id_str, $custom_id, $seller_flag = false)
    {
        //13642 【需求】一览界面批量下载功能优化 明细：增加输入关键字检索页面的下载功能，更新下载表格的英文表述

        if ($seller_flag == false) {
            $column = 'p.product_id';
        } else {
            $column = 'cp.customer_id';
        }

        $map = [
            ['p.buyer_flag', '=', 1],
            ['p.status', '=', 1],
            ['p.is_deleted', '=', 0],
            ['p.date_available', '<=', date('Y-m-d H:i:s', time())],
            ['p2s.store_id', '=', 0],
            ['d.language_id', '=', 1],
        ];
        $is_hp = $this->customer->isCollectionFromDomicile();
        $data = $this->orm->table('oc_product as p')
            ->leftJoin('oc_product_description as d', 'd.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_product as cp', 'cp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_customer as otc', 'cp.customer_id', '=', 'otc.customer_id')
            ->leftJoin('oc_product_to_tag as ot', 'ot.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_to_store as p2s', 'p2s.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customer  as c', 'c.customer_id', '=', 'cp.customer_id')
            ->leftJoin('oc_buyer_to_seller as bts', function ($join) use ($custom_id) {
                $join->on('bts.seller_id', '=', 'cp.customer_id')->where('bts.buyer_id', '=', $custom_id);
            })
            ->leftJoin('oc_product_fee as pf', function ($join) use ($is_hp) {
                $join->on('p.product_id', '=', 'pf.product_id')->where('pf.type', '=', $is_hp ? 2 : 1);
            })
            ->where($map)
            ->whereIn($column, explode(',', $id_str))->orderBy($column, 'asc')
            ->select('p.product_id', 'p.sku as item_code', 'd.name as product_name',
                'p.length', 'p.width', 'p.height', 'p.weight', 'p.price as unit_price', 'p.freight', 'pf.fee as package_fee'
                , 'p.combo_flag', 'p.product_id', 'p.price_display', 'p.quantity_display', 'cp.customer_id', 'otc.screenname', 'c.customer_group_id')
            ->selectRaw("group_concat(ot.tag_id) as tag_id")
            ->groupBy('p.product_id')
            ->get();
        $data = obj2array($data);
        $this->load->model('catalog/buyer_to_seller');
        $arr_privilege = $arr_common = [];
        $isSpecialBuyer = $this->checkIsEuropeanSpecialBuyer();
        $country_id = $this->customer->getCountryId();
        $coefficient = 1;
        $warehouse = [];
        if ($is_hp) {
            $this->load->model('extension/module/product_show');
            $warehouse = $this->model_extension_module_product_show->getWarehouseCodeByCountryId($country_id);
        }
        foreach ($data as $key => $value) {
            // 增加一个精细化验证
            //确认是否有最高权限
            $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
            $dm_info = $this->getDelicacyManagementInfoByNoView($value['product_id'], $custom_id);
            if (isset($dm_info) && $dm_info['product_display'] == 0) {
                continue;
            }
            $data[$key]['discount'] = $this->model_catalog_buyer_to_seller->getIsConnected($custom_id, $value['customer_id']);
            if (null != $data[$key]['discount'] && 0 != $data[$key]['discount'] && '' != $data[$key]['discount']) {
                $data[$key]['buyer_privilege'] = 1;
            } else {
                $data[$key]['buyer_privilege'] = 0;
            }
            //13642 【需求】一览界面批量下载功能优化 明细：增加输入关键字检索页面的下载功能，更新下载表格的英文表述
            //验证超大件处理
            if (null == $value['tag_id']) {
                $data[$key]['over_size_flag'] = 0;
            } else {
                $data[$key]['over_size_flag'] = substr($value['tag_id'], 0, 1) == 1 ? 1 : 0;
            }

            if ($value['combo_flag'] == 1) {
                //包裹
                $data[$key]['package_quantity'] = $this->getComboProductChildrenAmount($value['product_id']);
            } else {
                $data[$key]['package_quantity'] = 1;
            }
            if (isset($dm_info) && $dm_info['product_display'] == 1) {
                if (!$isCollectionFromDomicile) {

                    $unit_price = $dm_info['current_price'];
                } else {
                    $unit_price = $dm_info['current_price'];
                }
            } else {
                if (!$isCollectionFromDomicile) {

                    $unit_price = sprintf('%.2f', $value['unit_price']);
                    if ($unit_price < 0) {
                        $unit_price = 0;
                    }
                }
            }

            if (isset($unit_price)) {
                $data[$key]['unit_price'] = $unit_price;
            }
            if ($data[$key]['discount'] != 0)
                $data[$key]['unit_price'] = $data[$key]['discount'] * $data[$key]['unit_price'];
            //价格处理和 分组有关 European special buyer 价格显示为 总价的0.48
            if ($isSpecialBuyer)
                $data[$key]['unit_price'] = sprintf('%.2f', $coefficient * $data[$key]['unit_price']);
            $data[$key]['qty_avaliable'] = $this->getComboProductAvailableAmount($value['product_id'], $value['combo_flag']);
            //获取quote_flag
            $data[$key]['quote_flag'] = $this->getProductQuoteFlag($value['product_id']);
            if ($isCollectionFromDomicile) {
                $data[$key]['freight_per'] = $value['package_fee'];
            } else {
                $data[$key]['freight_per'] = $value['package_fee'] + $value['freight'];
            }
            if ($is_hp && $warehouse && $data[$key]['buyer_privilege']) {
                //$wareInfo = $this->model_extension_module_product_show->getWarehouseQty($value['product_id'], $warehouse);
                $wareInfo = $this->model_extension_module_product_show->getWarehouseDistributionByProductId($value['product_id']);
                $ware = [];
                foreach ($wareInfo as $kk => $vv) {
                    $ware[$vv['warehouse_id']] = $vv['stock_qty'];
                }

                foreach ($warehouse as $id => $code) {
                    $data[$key][$code] = isset($ware[$id]) ? $ware[$id] : 0;
                }
            }

            unset($unit_price);
            if ($data[$key]['buyer_privilege'] == 1) {
                $arr_privilege[] = $data[$key];
            } else {
                $arr_common[] = $data[$key];
            }
        }
        $res = array_merge($arr_privilege, $arr_common);
        //云送仓运费
        $product_id_list = array_column($res, 'product_id');
        $cwf_freight = $this->freight->getFreightAndPackageFeeByProducts($product_id_list);
        foreach ($res as $k => &$v) {
            if (isset($cwf_freight[$v['product_id']])) {
                if ($v['combo_flag'] == 0) {   //非combo
                    $v['cwf_freight'] = $cwf_freight[$v['product_id']]['freight'] + $cwf_freight[$v['product_id']]['package_fee'] + ($cwf_freight[$v['product_id']]['overweight_surcharge'] ?? 0);
                } else {    //combo
                    $v['cwf_freight'] = 0;
                    foreach ($cwf_freight[$v['product_id']] as $cwf_k => $cwf_v) {
                        $v['cwf_freight'] += ($cwf_v['freight'] + $cwf_v['package_fee']) * $cwf_v['qty'] + ($cwf_v['overweight_surcharge'] ?? 0);
                    }
                }
            } else {
                $v['cwf_freight'] = '';
            }
        }
        return $res;

    }

    /**
     * [getProductCategoryInfo description]
     * @param array|string $id_str
     * @param int $custom_id BuyerId
     * @param bool $seller_flag
     * @return array
     */
    public function getProductCategoryInfo($id_str, $custom_id, $seller_flag = false)
    {
        //13642 【需求】一览界面批量下载功能优化 明细：增加输入关键字检索页面的下载功能，更新下载表格的英文表述
        if ($seller_flag == false) {
            $column = 'p.product_id';
        } else {
            $column = 'cp.customer_id';
        }
        $map = [
            ['p.buyer_flag', '=', 1],
            ['p.status', '=', 1],
            ['p.is_deleted', '=', 0],
            ['p.date_available', '<=', date('Y-m-d H:i:s', time())],
            ['p2s.store_id', '=', 0],
            ['d.language_id', '=', 1],
            ['p.product_type', '=', 0],
        ];
        $is_hp = $this->customer->isCollectionFromDomicile();
        $data = $this->orm->table('oc_product as p')
            ->leftJoin('oc_product_description as d', 'd.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_product as cp', 'cp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_customer as otc', 'cp.customer_id', '=', 'otc.customer_id')
            ->leftJoin('oc_product_to_tag as ot', 'ot.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_to_store as p2s', 'p2s.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customer  as c', 'c.customer_id', '=', 'cp.customer_id')
            ->leftJoin('oc_buyer_to_seller as bts', function ($join) use ($custom_id) {
                $join->on('bts.seller_id', '=', 'cp.customer_id')->where('bts.buyer_id', '=', $custom_id);
            })
            ->leftJoin('oc_product_fee as pf', function ($join) use ($is_hp) {
                $join->on('p.product_id', '=', 'pf.product_id')->where('pf.type', '=', $is_hp ? 2 : 1);
            })
            ->where($map)
            ->whereIn($column, is_array($id_str) ? $id_str : explode(',', $id_str))
            ->orderBy($column, 'asc')
            ->select('bts.id as is_contacted','p.product_id', 'p.sku as item_code', 'd.name as product_name', 'd.color', 'd.material', 'p.product_size', 'p.need_install',
                'p.length', 'p.width', 'p.height', 'p.weight', 'p.weight_kg', 'p.length_cm', 'p.width_cm', 'p.height_cm', 'p.price as unit_price', 'p.freight', 'pf.fee as package_fee'
                , 'p.combo_flag', 'p.product_id', 'p.price_display', 'p.quantity_display', 'cp.customer_id', 'otc.screenname', 'c.customer_group_id')
            ->selectRaw("group_concat(ot.tag_id) as tag_id")->groupBy(['p.product_id'])
            ->get();
        $data = obj2array($data);
        $this->load->model('catalog/buyer_to_seller');
        $arr_privilege = [];
        $isSpecialBuyer = $this->checkIsEuropeanSpecialBuyer();
        $country_id = $this->customer->getCountryId();
        $warehouse = [];
        if ($is_hp) {
            $this->load->model('extension/module/product_show');
            $warehouse = $this->model_extension_module_product_show->getWarehouseCodeByCountryId($country_id);
        }
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        $productIds = array_column($data, 'product_id');
        $data = array_combine($productIds, $data);
        $productDmInfos = $this->getDmInfoByProductIds($productIds, $custom_id);
        $productDmgInfos = $this->getDmgInfoByProductIds($productIds, $custom_id);
        $productPackageQuantityInfos = $this->getPackageQuantityByProductIds($productIds);
        $productQuoteFlagInfos = $this->getQuoteFlagByProductIds($productIds);
        $cateRepo = app(CategoryRepository::class);
        $productService = app(ProductService::class);
        Product::query()
            ->with(['customerPartner', 'combos', 'batches', 'categories', 'images'])
            ->whereIn('product_id', $productIds)
            ->chunk(2000, function ($products) use (
                $data, $productDmInfos, $productDmgInfos,
                $productPackageQuantityInfos, $productQuoteFlagInfos,
                $isCollectionFromDomicile, $isSpecialBuyer, $is_hp, $warehouse, $cateRepo, $productService,
                &$arr_privilege, $custom_id
            ) {
                // 获取产品的锁定库存 （上门取货下载wishlist需要使用，之前是单个产品根据不同业务搜索，为了减少sql，改用批量查询）
                $productsComputeLockQtyMap = [];
                if ($is_hp && $warehouse) {
                    $productsComputeLockQtyMap = app(ProductLockRepository::class)->getProductsComputeLockQty($products);
                }
                /** @var Product $product */
                foreach ($products as $product) {
                    $tempProductId = $product->product_id;
                    $key = $tempProductId;
                    $value = $data[$key] ?? [];
                    if (empty($value)) {
                        continue;
                    }
                    $data[$key]['description'] = ''; // 由于不需要description 直接值为空
                    // 增加一个精细化验证
                    $dm_info = null;
                    if (array_key_exists($tempProductId, $productDmgInfos)) {
                        $dm_info = ['product_display' => 0,];
                    } else {
                        if (array_key_exists($tempProductId, $productDmInfos)) {
                            $dm_info = [
                                'product_display' => $productDmInfos[$tempProductId]['product_display'],
                                'current_price' => $productDmInfos[$tempProductId]['current_price'],
                            ];
                        }
                    }
                    if (isset($dm_info) && $dm_info['product_display'] == 0) {
                        continue;
                    }
                    $data[$key]['discount'] = 1;
                    $data[$key]['buyer_privilege'] = 1;
                    //13642 【需求】一览界面批量下载功能优化 明细：增加输入关键字检索页面的下载功能，更新下载表格的英文表述
                    //验证超大件处理
                    if (null == $value['tag_id']) {
                        $data[$key]['over_size_flag'] = 0;
                    } else {
                        $data[$key]['over_size_flag'] = substr($value['tag_id'], 0, 1) == 1 ? 1 : 0;
                    }
                    $data[$key]['package_quantity'] = $productPackageQuantityInfos[$value['product_id']] ?? 1;
                    if (isset($dm_info) && $dm_info['product_display'] == 1) {
                        $unit_price = $dm_info['current_price'];
                    } else {
                        if (!$isCollectionFromDomicile) {
                            $unit_price = sprintf('%.2f', $value['unit_price']);
                        }
                    }

                    if (isset($unit_price)) {
                        $data[$key]['unit_price'] = $unit_price;
                    }
                    //价格处理和 分组有关 European special buyer 价格显示为 总价的0.48
                    if ($isSpecialBuyer) {
                        $data[$key]['unit_price'] = sprintf('%.2f', $data[$key]['unit_price']);
                    }
                    $data[$key]['qty_avaliable'] = $this->getComboProductAvailableAmount($product, $value['combo_flag']);
                    //获取quote_flag
                    $data[$key]['quote_flag'] = in_array($tempProductId, $productQuoteFlagInfos) ? 1 : 0;
                    if ($isCollectionFromDomicile) {
                        $data[$key]['freight_per'] = $value['package_fee'];
                    } else {
                        $data[$key]['freight_per'] = $value['package_fee'] + $value['freight'];
                    }
                    if ($is_hp && $warehouse && $data[$key]['buyer_privilege']) {
                        $wareInfo = $this->model_extension_module_product_show->getWarehouseDistributionByProductId($product, $productsComputeLockQtyMap);
                        $ware = [];
                        foreach ($wareInfo as $vv) {
                            $ware[$vv['warehouse_id']] = $vv['stock_qty'];
                        }
                        //CA3关仓临时使用
                        foreach ($warehouse as $id => $code) {
                            $data[$key][$code] = $ware[$id] ?? 0;
                        }
                    }
                    unset($unit_price);
                    // category
                    $data[$key]['categoryInfo'] = $cateRepo->getCategoryByProductId($product);
                    // image
                    $data[$key]['imageInfo'] = $productService->getProductImageAndMain($product);
                    $data[$key]['store_code'] = $product->customerPartner ? $product->customerPartner->firstname . $product->customerPartner->lastname : '';

                    // #31737 下载产品价格设置免税价 (精细化价格未排除返点的)
                    $data[$key]['unit_price'] = app(ProductPriceRepository::class)
                        ->getProductActualPriceByBuyer($product->customerPartner->customer_id, $custom_id, $data[$key]['unit_price']);

                    $arr_privilege[] = $data[$key];
                }
            });

        $res = $arr_privilege;
        //云送仓运费
        $cwf_freight = $this->freight->getFreightAndPackageFeeByProducts($productIds);
        foreach ($res as &$v) {
            if (isset($cwf_freight[$v['product_id']])) {
                if ($v['combo_flag'] == 0) {   //非combo
                    $v['cwf_freight'] = $cwf_freight[$v['product_id']]['freight'] + $cwf_freight[$v['product_id']]['package_fee'] + ($cwf_freight[$v['product_id']]['overweight_surcharge'] ?? 0);
                } else {    //combo
                    $v['cwf_freight'] = 0;
                    foreach ($cwf_freight[$v['product_id']] as $cwf_v) {
                        $v['cwf_freight'] += ($cwf_v['freight'] + $cwf_v['package_fee']) * $cwf_v['qty'] + ($cwf_v['overweight_surcharge'] ?? 0);
                    }
                }
            } else {
                $v['cwf_freight'] = '';
            }
            $v['cwf_total'] = $v['unit_price'] + floatval($v['cwf_freight']);
        }
        unset($productDmInfos, $productDmgInfos, $productPackageQuantityInfos, $productQuoteFlagInfos, $products);
        return $res;
    }

    protected function checkIsEuropeanSpecialBuyer()
    {
        $mapGroupName['name'] = EUROPEAN_SPECIAL_BUYER;
        $custom_group_id = $this->customer->getGroupId();
        $need_group_id = $this->orm->table(DB_PREFIX . 'customer_group_description')->where($mapGroupName)->value('customer_group_id');
        if ($custom_group_id == $need_group_id) {
            return true;
        }
        return false;
    }

    /**
     * [thirtyDaysSale description]30天售卖数
     * @param int $product_id
     * @return array
     */
    public function thirtyDaysSale($product_id)
    {
        $map = [
            ['product_id', '=', $product_id],
            ['date_added', '>=', date('Y-m-d H:i:s', time() - 86400 * 30)],
            //['order_product_status','=',5], //30天售卖数
        ];
        $data = $this->orm->table('oc_customerpartner_to_order')
            ->where($map)->whereIn('order_product_status', [5, 13])->sum('quantity');
        return $data;


    }


    /**
     * [allDaysSale description] 所有天数的售卖数
     * @param int $product_id
     * @return array
     */
    public function allDaysSale($product_id)
    {
        $map = [
            ['product_id', '=', $product_id],
            //['date_added','>=',date('Y-m-d H:i:s',time()-86400*30)],
            //['order_product_status','=',5], //全部售卖数
        ];
        $data = $this->orm->table('oc_customerpartner_to_order')
            ->where($map)->whereIn('order_product_status', [5, 13])->sum('quantity');
        return $data;

    }

    /**
     * [getComboProductChildrenAmount description] 获取combo的子数量
     * @param int $product_id
     * @return array
     */

    protected function getComboProductChildrenAmount($product_id)
    {
        $map = [
            ['product_id', '=', $product_id],
        ];
        $data = $this->orm->table('tb_sys_product_set_info')->where($map)
            ->whereNotNull('set_product_id')->sum('qty');
        return $data;

    }

    /**
     * [getComboProductAvailableAmount description] 获取combo下的各个子产品数量和库存数
     * @param int|Product $product
     * @param int $flag combo_flag
     * @param int $use_batch 是否使用批次库存计算
     * @return array
     */
    public function getComboProductAvailableAmount($product, $flag, $use_batch = 0)
    {
        $product = is_object($product) ? $product : Product::find($product);
        // 保持页面和导出数量一致combo品不需要计算
        if ($use_batch == 0) {
            $flag = 0;
        }
        $data = 0;
        if ($flag == 0) {
            if ($use_batch == 1) {
                $data = $product->batches->sum('onhand_qty');
            } else {
                $data = $product->quantity;
            }
        } elseif ($flag == 1) {
            //含有包裹
            $map = [['i.product_id', '=', $product->product_id],];
            $data = $this->orm->table('tb_sys_product_set_info as i')
                ->leftJoin('tb_sys_batch as b', 'b.product_id', '=', 'i.set_product_id')
                ->where($map)
                ->whereNotNull('i.set_product_id')
                ->groupBy(['b.product_id', 'child_amount'])
                ->select(["i.qty as child_amount", 'b.product_id'])
                ->selectRaw('sum(b.onhand_qty) as all_qty')
                ->get();
            $data = obj2array($data);
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
     * @param int $product_id
     * @return int
     */
    protected function getProductQuoteFlag($product_id)
    {
        $res = $this->orm->table(DB_PREFIX . 'wk_pro_quote_details AS pd')
            ->leftJoin(DB_PREFIX . 'wk_pro_quote AS pq', ['pd.seller_id' => 'pq.seller_id'])
            ->where([
                'pd.product_id' => $product_id,
                'pq.status' => 0,
            ])
            ->first();

        return $res ? 1 : 0;
    }


    /**
     * 获取product的基本信息(使用事务的时候注意)
     * @param int $product_id
     * @return mixed
     */
    public function getProductBaseInfoByDB($product_id)
    {
        $sql = "select p.product_id,p.sku,p.mpn,p.quantity,pd.name from oc_product as p left join oc_product_description as pd on pd.product_id=p.product_id
where p.product_id=" . (int)$product_id;
        return $this->db->query($sql)->row;
    }

    /**
     * 购买用户登录后获取被隐藏或者不可见的商品id
     * @param int $customerId
     * @return array
     */
    public function getBuyerForbiddenProductIds($customerId): array
    {
        if (!$customerId) {
            return [];
        }
        $this->load->model('account/customer');
        /** @var ModelAccountCustomer $modelAccountCustomer */
        $modelAccountCustomer = $this->model_account_customer;
        if ($modelAccountCustomer->checkIsSeller($customerId)) {
            return [];
        }
        $dm_product_ids = $this->orm->table('oc_delicacy_management')
            ->where([
                ['buyer_id', '=', $customerId],
                ['product_display', '=', 0]
            ])
            ->pluck('product_id')
            ->toArray();
        $dmg_product_ids = $this->orm->table('oc_delicacy_management_group as dmg')
            ->join('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->join('oc_customerpartner_product_group_link as pgl', 'pgl.product_group_id', '=', 'dmg.product_group_id')
            ->where([
                ['bgl.buyer_id', '=', $customerId],
                ['dmg.status', '=', 1],
                ['pgl.status', '=', 1],
                ['bgl.status', '=', 1]
            ])
            ->pluck('pgl.product_id')
            ->toArray();
        return array_unique(array_merge($dm_product_ids, $dmg_product_ids));
    }

    /**
     * 获取商品精细化价格，并返回是否在生效期内
     * @param int $productId 商品id
     * @param int $buyerId 购买用户id
     * @return float| null  null存在2种情况 1：不存在商品精细化 2：参与商品精细化但是不显示
     */
    public function getDelicacyManagePrice(int $productId, int $buyerId, int $sellerId)
    {
        $res = $this->orm
            ->table(DB_PREFIX . 'delicacy_management')
            ->where([
                'buyer_id' => $buyerId,
                'product_id' => $productId,
                'product_display' => 1,
            ])
            ->whereRaw('NOW() < expiration_time')
            ->first();
        if (!$res) {
            return null;
        }
        $res = obj2array($res);

        //参考购物车
        $fineData = $this->cart->getDelicacyManagementInfoByNoView((int)$productId, (int)$buyerId, (int)$sellerId);

        // 查找是否是返点得精细化
        // getDelicacyManagementInfoByNoView 中已经添加了对is_rebate的判断，不需要再次处理
        $exists = isset($fineData['is_rebate']) ? $fineData['is_rebate'] : 0;
        $is_delicacy_effected = false; // 精细化价格是否正在使用
        if ($fineData && !$exists) {
            if ($fineData['product_display'] != 1) {
                // 用户无权下单购买该产品
            } else {
                //  查出当前价格使用的是精细化价格， 1.第一次设置精细化价格直接生效 2.已设置过精细化价格，但有设置后期涨价
                if ($fineData['effective_time'] <= date('Y-m-d H:i:s')) {
                    $is_delicacy_effected = true;
                } elseif ($this->orm->table('oc_delicacy_management_history')
                    ->where('origin_id', $fineData['id'])
                    ->where('effective_time', '<', date('Y-m-d H:i:s'))
                    ->where('expiration_time', '>', date('Y-m-d H:i:s'))
                    ->whereRaw('current_price = price')
                    ->exists()) {
                    $is_delicacy_effected = true;
                }
            }
        }

        $result = [
            'price' => floatval($res['current_price']),
            'is_delicacy_effected' => $is_delicacy_effected,
        ];
        return $result;
    }

    /**
     * 是否订阅了该产品
     * @param int $productId
     * @param int|null $customerId
     * @return bool
     */
    public function getWishListProduct($productId, $customerId = null)
    {
        if ($customerId == null) {
            return false;
        } else {
            return $this->orm->table(DB_PREFIX . 'customer_wishlist')
                ->where([
                    'customer_id' => $customerId,
                    'product_id' => $productId,
                ])->exists();
        }
    }


    /**
     * 是否是配件
     * @param int $product_id
     * @param int $tag_status
     * @return array
     */
    public function getPart($product_id, $tag_status = 1)
    {
        $query = $this->db->query("SELECT product_id FROM oc_product WHERE part_flag = 1 AND product_id=" . $product_id);

        if (!empty($query->rows)) {
            $tag = $this->db->query("SELECT description,icon FROM oc_tag WHERE tag_id = 2 and status=1")->row;
            return $tag;
        }
    }

    public function isPart($id, $item_code)
    {
        $query = $this->db->query("SELECT product_id FROM oc_product WHERE part_flag = 1 AND sku='" . $item_code . "'");

        if (!empty($query->rows)) {
            $this->db->query("UPDATE tb_sys_customer_sales_order_line SET part_flag = 1  WHERE id=" . $id);
            $tag = $this->db->query("SELECT description,icon FROM oc_tag WHERE tag_id = 2 and status=1")->row;
            return $tag;
        }
    }

    /**
     * 订单是否有配件
     * @param int $id
     * @return array|void
     */
    public function isPartByOrderHeaderId($id)
    {
        $query = $this->db->query("SELECT op.product_id FROM tb_sys_customer_sales_order cso
                LEFT JOIN  tb_sys_customer_sales_order_line csol on cso.id=csol.header_id
                LEFT JOIN oc_product op on op.sku=csol.item_code
                WHERE op.part_flag = 1 AND cso.id=" . $id);
        if (!empty($query->rows)) {
            $tag = $this->db->query("SELECT description,icon FROM oc_tag WHERE tag_id = 2 and status=1")->row;
            return $tag;
        }
    }

    public function isPartByOrderId($orderId, $customer_id = null)
    {
        if ($customer_id == null) {
            $query = $this->db->query('SELECT * FROM oc_order_product oop LEFT JOIN oc_product op ON op.product_id=oop.product_id WHERE op.part_flag = 1 AND oop.order_id=' . $orderId);
        } else {
            $query = $this->db->query('SELECT * FROM oc_customerpartner_to_order cto LEFT JOIN oc_product op ON op.product_id=cto.product_id WHERE op.part_flag = 1 AND cto.customer_id= ' . $customer_id . ' AND cto.order_id=' . $orderId);
        }
        if (!empty($query->rows)) {
            $tag = $this->db->query("SELECT description,icon FROM oc_tag WHERE tag_id = 2 and status=1")->row;
            return $tag;
        }
    }

    /**
     * 获取资源包
     *
     * @param int $product_id
     * @param string $type
     * @return array|Collection
     */
    public function getProductPackages($product_id, $type = 'image')
    {
        if (!in_array($type, ['image', 'file', 'video'])) {
            return [];
        }

        return $this->orm->table('oc_product_package_' . $type)
            ->where('product_id', $product_id)
            ->get(['*']);
    }

    /**
     * 获取预期入库的商品时间和数量
     *
     * @return array
     */
    public function getReceptionProduct()
    {
        $sql = "SELECT
                  rod.`product_id`,
                  SUM(rod.`expected_qty`) AS expect_qty,
                  ro.`expected_date` AS expect_date
                FROM
                  tb_sys_receipts_order ro
                  INNER JOIN tb_sys_receipts_order_detail rod
                    ON ro.`receive_order_id` = rod.`receive_order_id`
                WHERE ro.`status` =  " . ReceiptOrderStatus::TO_BE_RECEIVED . "
                  AND ro.`expected_date` IS NOT NULL
                  AND rod.`expected_qty` IS NOT NULL
                  AND ro.`expected_date` > NOW()
                GROUP BY rod.`product_id`";
        $query = $this->db->query($sql);
        $receipt_product = array();
        if (!empty($query->rows)) {
            foreach ($query->rows as $row) {
                $product_id = $row['product_id'];
                $expect_qty = $row['expect_qty'];
                $expect_date = $row['expect_date'];

                $receipt_product[$product_id] = array(
                    'expect_qty' => $expect_qty,
                    'expect_date' => date('Y-m-d', strtotime($expect_date))
                );
            }
        }
        return $receipt_product;
    }

    public function getMarketPromotionDescription($product_id)
    {
        $sql = "SELECT op.`name` AS promotion_name,pd.`description`
                FROM oc_promotions_description pd
                INNER JOIN oc_promotions_to_seller pts ON pd.`promotions_id` = pts.`promotions_id`
                INNER JOIN oc_promotions op ON op.`promotions_id` = pts.`promotions_id`
                INNER JOIN oc_customerpartner_to_product ctp ON ctp.`customer_id` = pts.`seller_id` AND ctp.`product_id` = pd.`product_id`
                WHERE
                ctp.`product_id` = " . (int)$product_id . "
                AND op.`promotions_status` = 1
                AND pts.`status` = 1
                ORDER BY op.`sort_order` ASC";
        $query = $this->db->query($sql);
        return $query->rows;
    }

    /**
     * 获取所有标签信息，以及促销业务标签
     * 于本类的getTag方法很类似，差别在于合并了促销业务的结果
     *
     * @param int $product_id
     * @param int $tag_status
     * @return array
     */
    public function getTagAndPromotion($product_id)
    {
        $sql = "SELECT
                  tag.description,
                  tag.icon,
                  tag.tag_id,
                  tag.class_style,
                  tag.sort_order,
                  NULL AS link,
                  1 AS tag_type
                FROM
                  oc_product p
                  INNER JOIN oc_product_to_tag pt
                    ON p.product_id = pt.product_id
                  LEFT JOIN oc_tag tag
                    ON tag.tag_id = pt.tag_id
                WHERE tag.status = 1
                  AND p.product_id = " . (int)$product_id . "
                UNION ALL
                SELECT pro.name,pro.image,pro.promotions_id,2 AS class_style,pro.sort_order,pro.link,2 AS tag_type FROM oc_promotions pro
                INNER JOIN oc_promotions_to_seller pts ON pts.promotions_id = pro.promotions_id
                INNER JOIN oc_customerpartner_to_product ctp ON ctp.customer_id = pts.seller_id
                INNER JOIN oc_promotions_description pd ON pd.product_id = ctp.product_id AND pd.promotions_id = pro.promotions_id
                WHERE pd.product_id = " . (int)$product_id . "  AND pro.promotions_status = 1 AND pts.status = 1 ORDER BY tag_type,sort_order ASC";
        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * 搜索、类别等商品缩略图页的标签HTML style="display: inline;" 首页 块状元素会分行
     * @param int $product_id
     * @return array
     * @throws Exception
     */
    public function getProductTagHtmlForThumb($product_id)
    {
        $tag_multi_array = $this->getTagAndPromotion($product_id);
        $tag_array = array();
        if (isset($tag_multi_array)) {
            $this->load->model('tool/image');
            foreach ($tag_multi_array as $tags) {
                if (isset($tags['icon']) && !empty($tags['icon'])) {
                    $tag_html = '<a data-toggle="tooltip" style="display: inline;" data-original-title="' . $tags['description'] . '"';
                    if (!empty($tags['link'])) {
                        $tag_html .= ' href = "' . $tags['link'];
                    }
                    $tag_html .= '">';
                    //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                    if ($tags['tag_type'] == 1) {
                        $class = $tags['class_style'];
                        $img_url = $this->model_tool_image->getOriginImageProductTags($tags['icon']);
                    } else {
                        $class = '';
                        $img_url = $this->model_tool_image->resize($tags['icon'], 15, 15);
                    }
                    $tag_html .= '<img class="' . $class . '" src="' . $img_url . '"/></a>';
                    $tag_array[] = $tag_html;
                }
            }
        }
        return $tag_array;
    }

    /**
     * 商品详情页标签的HTML拼接
     * @param int $product_id
     * @return array
     */
    public function getProductTagHtmlForDetailPage($product_id)
    {
        $tag_multi_array = $this->getTagAndPromotion($product_id);
        $tag_array = array();
        if (isset($tag_multi_array)) {
            $this->load->model('tool/image');
            foreach ($tag_multi_array as $tags) {
                if (isset($tags['icon']) && !empty($tags['icon'])) {
                    $tag_html = '<a data-toggle="tooltip" data-original-title="' . $tags['description'] . '" ';
                    if (!empty($tags['link'])) {
                        $tag_html .= ' href = "' . $tags['link'];
                    }
                    $tag_html .= '">';

                    //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                    if ($tags['tag_type'] == 1) {
                        $class = $tags['class_style'];
                        $img_url = $this->model_tool_image->getOriginImageProductTags($tags['icon']);
                    } else {
                        $class = '';
                        $img_url = $this->model_tool_image->resize($tags['icon'], 15, 15);
                    }

                    $tag_html .= '<img class="' . $class . '" src="' . $img_url . '"/></a>';
                    $tag_array[] = $tag_html;
                }
            }
        }
        return $tag_array;
    }

    /**
     * 添加搜索记录
     *
     * @param int $customer_id
     * @param string $content
     */
    public function addSearchRecord($customer_id, $content)
    {
        $this->orm->table('oc_search_record')
            ->insert([
                'customer_id' => $customer_id ?: 0,
                'content' => $content ?: '',
                'add_time' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * [getAllCategoryId description]
     * @param $path_string
     * @return string
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getAllCategoryId($path_string)
    {
        $all = [];
        $category_list = $this->getCategoryInfo();
        foreach ($category_list as $key => $value) {
            if (stripos($value['all_pid'], $path_string) !== false) {
                $all[] = $key;
            }
        }

        return implode(',', array_unique($all));
    }

    /**
     * [getCategoryInfo description] 有cache取cache ，没cache自动获取
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getCategoryInfo()
    {
        $max_id = $this->orm->table(DB_PREFIX . 'category as c')->max('category_id');
        if ($this->cache->get('category_max_id') == null || $max_id != $this->cache->get('category_max_id')) {

            $res = $this->orm->table(DB_PREFIX . 'category as c')
                ->leftJoin(DB_PREFIX . 'category_description as d', 'c.category_id', '=', 'd.category_id')
                ->select('c.category_id', 'c.parent_id', 'c.image', 'd.name')
                ->get()
                ->map(
                    function ($value) {
                        return (array)$value;
                    })
                ->toArray();
            $category_list = [];
            foreach ($res as $key => $value) {
                $category_list[$value['category_id']]['parent_id'] = $value['parent_id'];
                $category_list[$value['category_id']]['name'] = $value['name'];
                $category_list[$value['category_id']]['image'] = $value['image'];
                $category_list[$value['category_id']]['self_id'] = $value['category_id'];
                $category_list[$value['category_id']]['all_pid'] = $value['category_id'];
            }
            foreach ($category_list as $key => $value) {
                $this->dealWithCategoryData($category_list, $key, $key);
            }
            $this->cache->set('category_max_id', $max_id);
            $this->cache->set('category_list', $category_list);

        } else {

            if ($this->cache->get('category_list') == null) {

                $res = $this->orm->table(DB_PREFIX . 'category as c')
                    ->select('c.category_id', 'c.parent_id')
                    ->get()
                    ->map(
                        function ($value) {
                            return (array)$value;
                        })
                    ->toArray();
                $category_list = [];
                foreach ($res as $key => $value) {
                    $category_list[$value['category_id']]['parent_id'] = $value['parent_id'];
                    $category_list[$value['category_id']]['self_id'] = $value['category_id'];
                    $category_list[$value['category_id']]['all_pid'] = $value['category_id'];
                }
                foreach ($category_list as $key => $value) {
                    $this->dealWithCategoryData($category_list, $key, $key);
                }

                $this->cache->set('category_list', $category_list);

            } else {

                $category_list = $this->cache->get('category_list');
            }
        }

        return $category_list;
    }

    /**
     * [dealWithCategoryData description] 处理父类id
     * @param array $category_list
     * @param $origin_key
     * @param $key
     */
    function dealWithCategoryData(&$category_list, $origin_key, $key)
    {

        if ($category_list[$key]['parent_id'] == 0) {

        } else {
            $category_list[$origin_key]['all_pid'] = $category_list[$key]['parent_id'] . '_' . $category_list[$origin_key]['all_pid'];
            $this->dealWithCategoryData($category_list, $origin_key, $category_list[$key]['parent_id']);
        }

    }

    /**
     * @param int $product_id
     * @param int|bool $isHomePickup
     * @return int|mixed|null
     */
    public function getNewPackageFee($product_id, $isHomePickup)
    {
        return $this->orm->table('oc_product_fee')
            ->where([
                ['product_id', '=', $product_id],
                ['type', '=', $isHomePickup ? 2 : 1]
            ])
            ->value('fee') ?: 0;
    }

    public function getProducTypetById($product_id)
    {
        return $this->orm->table('oc_product')
            ->where('product_id', $product_id)
            ->value('product_type');
    }
}
