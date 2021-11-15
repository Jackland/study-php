<?php

use App\Repositories\Information\UploadInformationRepository;


/**
 * Class ModelCustomerpartnerPartner
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCustomerpartnerMail $model_customerpartner_mail
 * @property ModelCustomerpartnerHtmlfilter $model_customerpartner_htmlfilter
 * @property ModelSettingEvent $model_setting_event
 */
class ModelCustomerpartnerPartner extends Model {

	private $data = array();

	public function removeCustomerpartnerTable(){

		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_commission_category`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_to_customer`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_to_commission`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_to_product`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_to_feedback`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_to_order`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_to_category`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_to_order_status`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_to_transaction`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_to_payment`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_download`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_flatshipping`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_shipping`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_sold_tracking`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_mail`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."wk_custom_field_description`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."wk_custom_field_options`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."wk_custom_field_option_description`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."wk_custom_field_product`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."wk_custom_field_product_options`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."seller_activity`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."mp_customer_activity`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."viewed_activity`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."wk_category_attribute_mapping`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."wk_feedback_attribute`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."wk_feedback_attribute_values`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_to_sellercategory`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX ."customerpartner_to_information`");
	}

	public function upgradeMarketplace(){

        /**
         * [$check_product_seller_price uses to check whether seller_price column exists]
         * @var [type]
         */
		$check_product_seller_price = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '".DB_DATABASE."' AND TABLE_NAME = '".DB_PREFIX."customerpartner_to_product' AND COLUMN_NAME = 'seller_price'")->row;

		if (!$check_product_seller_price) {

			$this->db->query("ALTER TABLE  " . DB_PREFIX . "customerpartner_to_product
				ADD COLUMN (seller_price float NOT NULL, currency_code varchar(11) NOT NULL);
		   ");

		}

        /**
         * [$check_feedback_status uses to check whether status column exists]
         * @var [type]
         */
		$check_feedback_status = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '".DB_DATABASE."' AND TABLE_NAME = '".DB_PREFIX."customerpartner_to_feedback' AND COLUMN_NAME = 'status'")->row;

		if (!$check_feedback_status) {

			$this->db->query("ALTER TABLE  " . DB_PREFIX . "customerpartner_to_feedback ADD COLUMN status int(1) NOT NULL AFTER review
		   ");

		}

		$check_order_status_column = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '".DB_DATABASE."' AND TABLE_NAME = '".DB_PREFIX."customerpartner_to_order' AND COLUMN_NAME = 'order_product_status'")->row;

        if (!$check_order_status_column) {

        	$this->db->query("ALTER TABLE  " . DB_PREFIX . "customerpartner_to_order
				ADD COLUMN order_product_status INT(11) NOT NULL AFTER date_added;
		   ");

        	//Table structure for table `customerpartner_to_order_status`
			$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_to_order_status` (
			  `change_orderstatus_id` int(11) NOT NULL AUTO_INCREMENT,
			  `order_id` int(11) NOT NULL,
			  `product_id` int(11) NOT NULL,
			  `customer_id` int(11) NOT NULL,
			  `order_status_id` int(11) NOT NULL,
			  `comment` text NOT NULL,
			  `date_added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			  PRIMARY KEY (`change_orderstatus_id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

			$orderStatuses = $this->db->query("SELECT order_id,order_status_id FROM ".DB_PREFIX."order")->rows;

			foreach ($orderStatuses as $key => $value) {

				$this->db->query("UPDATE ".DB_PREFIX."customerpartner_to_order SET order_product_status = '".$value['order_status_id']."' WHERE order_id = '".(int)$value['order_id']."'");

			}
	    }

	    $check_order_status = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '".DB_DATABASE."' AND TABLE_NAME = '".DB_PREFIX."customerpartner_to_order' AND COLUMN_NAME = 'shipping_applied'")->row;

		if (!$check_order_status) {

			$this->db->query("ALTER TABLE  " . DB_PREFIX . "customerpartner_to_order
				ADD COLUMN (shipping_applied float NOT NULL,
				commission_applied decimal(10,2) NOT NULL,
				currency_code varchar(10) NOT NULL,
				currency_value decimal(15,8) NOT NULL,
				option_data text NOT NULL,
				seller_access int(1) NOT NULL)
		   ");

		}

		//Table structure for table `customerpartner_to_category`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_to_category` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `seller_id` int(11) NOT NULL,
		  `category_id` varchar(1000) NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;


		//Table structure for table `wk_category_attribute_mapping`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."wk_category_attribute_mapping` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `category_id` int(11) NOT NULL,
		  `attribute_id` varchar(1000) NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

		if (!$this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '".DB_DATABASE."' AND TABLE_NAME = '".DB_PREFIX."customerpartner_to_customer' AND COLUMN_NAME = 'taxinfo'")->row) {
			$this->db->query("ALTER TABLE  " . DB_PREFIX . "customerpartner_to_customer ADD COLUMN taxinfo varchar(500) AFTER otherpayment;");
		}

		if (!$this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '".DB_DATABASE."' AND TABLE_NAME = '".DB_PREFIX."customerpartner_to_customer' AND COLUMN_NAME = 'paypalfirstname'")->row) {
		  $this->db->query("ALTER TABLE  " . DB_PREFIX . "customerpartner_to_customer ADD COLUMN paypalfirstname varchar(255) AFTER paypalid;");
		}

		if (!$this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '".DB_DATABASE."' AND TABLE_NAME = '".DB_PREFIX."customerpartner_to_customer' AND COLUMN_NAME = 'paypallastname'")->row) {
		  $this->db->query("ALTER TABLE  " . DB_PREFIX . "customerpartner_to_customer ADD COLUMN paypallastname varchar(255) AFTER paypalfirstname;");
		}

		if (!$this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '".DB_DATABASE."' AND TABLE_NAME = '".DB_PREFIX."customerpartner_shipping' AND COLUMN_NAME = 'max_days'")->row) {
			$this->db->query("ALTER TABLE  " . DB_PREFIX . "customerpartner_shipping ADD COLUMN max_days int(10) NOT NULL AFTER weight_to;");
		}

		//Table structure for table `seller_activity`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."seller_activity` (
		  `seller_activity_id` int(11) NOT NULL AUTO_INCREMENT,
			`activity_id` int(11) NOT NULL,
		  `seller_id` int(11) NOT NULL,
		  PRIMARY KEY (`seller_activity_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci") ;

		//Table structure for table `wk_feedback_attribute`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."wk_feedback_attribute` (
		  `field_id` int(11) NOT NULL AUTO_INCREMENT,
		  `field_name` varchar(100) NOT NULL,
		  `field_status` int(11) NOT NULL,
		  PRIMARY KEY (`field_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

		$feedback_attribute_array = array('Value','Price');

		foreach ($feedback_attribute_array as $key => $value) {
			if (!$this->db->query("SELECT * FROM `" . DB_PREFIX . "wk_feedback_attribute` WHERE `field_name` = '" . $value . "'")->num_rows) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "wk_feedback_attribute` SET `field_name` = '" . $value . "', `field_status` = '1'");
			}
		}

		//Table structure for table `wk_feedback_attribute_values`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."wk_feedback_attribute_values` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `feedback_id` int(11) NOT NULL,
		  `field_id` int(11) NOT NULL,
		  `field_value` int(11) NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

		$check_feedback_column = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '".DB_DATABASE."' AND TABLE_NAME = '".DB_PREFIX."customerpartner_to_feedback' AND COLUMN_NAME = 'feedprice'")->row;

		if ($check_feedback_column) {
			$this->db->query("ALTER TABLE  " . DB_PREFIX . "customerpartner_to_feedback DROP COLUMN feedprice");
			$this->db->query("ALTER TABLE  " . DB_PREFIX . "customerpartner_to_feedback DROP COLUMN feedvalue");
			$this->db->query("ALTER TABLE  " . DB_PREFIX . "customerpartner_to_feedback DROP COLUMN feedquality");
		}

		if (!$this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '".DB_DATABASE."' AND TABLE_NAME = '".DB_PREFIX."customerpartner_flatshipping' AND COLUMN_NAME = 'status'")->row) {
		  $this->db->query("ALTER TABLE  " . DB_PREFIX . "customerpartner_flatshipping ADD COLUMN status int(1) NOT NULL AFTER tax_class_id;");
		}

		//Table structure for table `wk_feedback_attribute`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."wk_feedback_attribute` (
		  `field_id` int(11) NOT NULL AUTO_INCREMENT,
		  `field_name` varchar(100) NOT NULL,
		  `field_status` int(11) NOT NULL,
		  PRIMARY KEY (`field_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

		//Table structure for table `wk_feedback_attribute_values`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."wk_feedback_attribute_values` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `feedback_id` int(11) NOT NULL,
		  `field_id` int(11) NOT NULL,
		  `field_value` int(11) NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

		//Table structure for table `mp_customer_activity`
		//id used for customer_id(key=order_status) or product_id(key=product_stock)
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."mp_customer_activity` (
		  `customer_activity_id` int(11) NOT NULL AUTO_INCREMENT,
		  `id` int(11) NOT NULL,
		  `key` varchar(64) NOT NULL,
		  `data` text NOT NULL,
		  `ip` varchar(40) NOT NULL,
		  `date_added` datetime NOT NULL,
		  PRIMARY KEY (`customer_activity_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci") ;

		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."viewed_activity` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `customer_id` int(11) NOT NULL,
		  `notification_viewed` int(11) NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci") ;

		if (!$this->db->query("SELECT * FROM `" . DB_PREFIX . "event` WHERE code = 'webkul_marketplace'")->num_rows) {
			$this->load->model('setting/event');

			$this->model_setting_event->addEvent('webkul_marketplace', 'catalog/model/checkout/order/deleteOrder/after','event/marketplace/deleteMPOrder');
		}

		//Table structure for table `customerpartner_to_sellercategory`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_to_sellercategory` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `seller_id` int(11) NOT NULL,
		  `category_id` int(11) NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

		//Table structure for table `customerpartner_to_information`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_to_information` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `seller_id` int(11) NOT NULL,
		  `information_id` int(11) NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;
	}

	public function createCustomerpartnerTable(){

		//Table structure for table `customerpartner_commission_category`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_commission_category` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `category_id` int(100) NOT NULL,
		  `fixed` float NOT NULL,
		  `percentage` float NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;


		//Table structure for table `customerpartner_to_customer`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_to_customer` (
		  `customer_id` int(11) NOT NULL,
		  `is_partner` int(1) NOT NULL,
		  `screenname` varchar(255) NOT NULL,
		  `gender` varchar(255) NOT NULL,
		  `shortprofile` text NOT NULL,
		  `avatar` varchar(255) NOT NULL,
		  `twitterid` varchar(255) NOT NULL,
		  `paypalid` varchar(255) NOT NULL,
			`paypalfirstname` varchar(255),
			`paypallastname` varchar(255),
		  `country` varchar(255) NOT NULL,
		  `facebookid` varchar(255) NOT NULL,
		  `backgroundcolor` varchar(255) NOT NULL,
		  `companybanner` varchar(255) NOT NULL,
		  `companylogo` varchar(255) NOT NULL,
		  `companylocality` varchar(255) NOT NULL,
		  `companyname` varchar(255) NOT NULL,
		  `companydescription` text NOT NULL,
		  `countrylogo` varchar(1000) NOT NULL,
		  `otherpayment` text NOT NULL,
		  `taxinfo` varchar(500),
		  `commission` decimal(10,2) NOT NULL,
		  PRIMARY KEY (`customer_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1");

		//Table structure for table `customerpartner_to_commission`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_to_commission` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `customer_id` int(100) NOT NULL,
		  `commission_id` int(100) NOT NULL,
		  `fixed` float NOT NULL,
		  `percentage` float NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

		//Table structure for table `customerpartner_to_product`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_to_product` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `customer_id` int(100) NOT NULL,
		  `product_id` int(100) NOT NULL,
		  `price` float NOT NULL,
		  `seller_price` float NOT NULL,
		  `currency_code` varchar(11) NOT NULL,
		  `quantity` int(100) NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

		//Table structure for table `customerpartner_feedbacks`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_to_feedback` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `customer_id` smallint(6) NOT NULL,
		  `seller_id` smallint(6) NOT NULL,
		  `nickname` varchar(255) NOT NULL,
		  `summary` text NOT NULL,
		  `review` text NOT NULL,
		  `status` int(1) NOT NULL,
		  `createdate` datetime NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

		//Table structure for table `customerpartner_orders`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_to_order` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `order_id` int(11) NOT NULL,
		  `customer_id` int(11) NOT NULL,
		  `product_id` int(11) NOT NULL,
		  `order_product_id` int(100) NOT NULL,
		  `price` float NOT NULL,
		  `quantity` float(11) NOT NULL,
		  `shipping` varchar(255) NOT NULL,
		  `shipping_rate` float NOT NULL,
		  `payment` varchar(255) NOT NULL,
		  `payment_rate` float NOT NULL,
		  `admin` float NOT NULL,
		  `customer` float NOT NULL,
		  `shipping_applied` float NOT NULL,
		  `commission_applied` decimal(10,2) NOT NULL,
		  `currency_code` varchar(10) NOT NULL,
		  `currency_value` decimal(15,8) NOT NULL,
		  `details` varchar(255) NOT NULL,
		  `paid_status` tinyint(1) NOT NULL,
		  `date_added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		  `order_product_status` INT(11) NOT NULL,
		  `option_data` varchar(5000),
		  `seller_access` int(1) NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

        //Table structure for table `customerpartner_to_order_status`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_to_order_status` (
		  `change_orderstatus_id` int(11) NOT NULL AUTO_INCREMENT,
		  `order_id` int(11) NOT NULL,
		  `product_id` int(11) NOT NULL,
		  `customer_id` int(11) NOT NULL,
		  `order_status_id` int(11) NOT NULL,
		  `comment` text NOT NULL,
		  `date_added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		  PRIMARY KEY (`change_orderstatus_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

		//Table structure for table `customerpartner_transaction`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_to_transaction` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `customer_id` int(11) NOT NULL,
		  `order_id` varchar(500) NOT NULL,
		  `order_product_id` varchar(500) NOT NULL,
		  `amount` float NOT NULL,
		  `text` varchar(255) NOT NULL,
		  `details` varchar(255) NOT NULL,
		  `date_added` date NOT NULL DEFAULT '0000-00-00',
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

		//Table structure for table `customerpartner_payment`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_to_payment` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `customer_id` int(11) NOT NULL,
		  `amount` float NOT NULL,
		  `text` varchar(255) NOT NULL,
		  `details` varchar(255) NOT NULL,
		  `request_type` varchar(255) NOT NULL,
		  `paid` int(10) NOT NULL,
		  `balance_reduced` int(10) NOT NULL,
		  `payment` varchar(255) NOT NULL,
		  `date_added` date NOT NULL DEFAULT '0000-00-00',
		  `date_modified` date NOT NULL DEFAULT '0000-00-00',
		  `added_by` varchar(255) NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

		//Table structure for table `customerpartner_download
		$this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX ."customerpartner_download (
		  `download_id` int(11) NOT NULL AUTO_INCREMENT,
		  `seller_id` int(11) NOT NULL,
		  PRIMARY KEY (`download_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

		//Table structure for table `customerpartner_flatshipping
		$this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX ."customerpartner_flatshipping (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`partner_id` int(11) NOT NULL,
			`amount` float,
			`tax_class_id` float NOT NULL,
			`status` int(1) NOT NULL,
			PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci") ;

		//Table structure for table `customerpartner_shipping
		$this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "customerpartner_shipping (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `seller_id` int(10) NOT NULL ,
            `country_code` varchar(100) NOT NULL ,
            `zip_to` varchar(100) NOT NULL ,
            `zip_from` varchar(100) NOT NULL ,
            `price` float NOT NULL ,
            `weight_from` decimal (10,2) NOT NULL ,
            `weight_to` decimal (10,2) NOT NULL ,
            `max_days` int(10) NOT NULL ,
            PRIMARY KEY (`id`) ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

		//Table structure for table `customerpartner_sold_tracking
		$this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX ."customerpartner_sold_tracking (
			`product_id` int(11) NOT NULL,
			`order_id` int(11) NOT NULL,
			`customer_id` int(11) NOT NULL,
			`date_added` date NOT NULL DEFAULT '0000-00-00',
			`tracking` varchar(100) NOT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci") ;


		//Table structure for table `customerpartner_mail`
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_mail` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `name` varchar(100) NOT NULL,
		  `subject` varchar(1000) NOT NULL,
		  `message` varchar(5000) NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

		$this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "wk_custom_field (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `forSeller` varchar(10) NOT NULL,
			  `fieldType` varchar(100) NOT NULL,
			  `isRequired` varchar(10) NOT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ");

			$this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "wk_custom_field_description (
			  `fieldId` int(10) NOT NULL,
			  `fieldDescription` varchar(5000) NOT NULL,
			  `fieldName` varchar(100) NOT NULL,
			  `language_id` int(10) NOT NULL
			) ENGINE=MyISAM DEFAULT CHARSET=utf8");

			$this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "wk_custom_field_options (
			  `optionId` int(11) NOT NULL AUTO_INCREMENT,
			  `fieldId` int(11) NOT NULL,
			  PRIMARY KEY (`optionId`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ");

			$this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "wk_custom_field_option_description (
			  `optionId` int(10) NOT NULL,
			  `optionValue` varchar(100) NOT NULL,
			  `language_id` int(10) NOT NULL
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

			$this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "wk_custom_field_product (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `fieldId` int(11) NOT NULL,
			  `productId` int(11) NOT NULL,
			  `fieldType` varchar(100) NOT NULL,
			  `fieldDescription` varchar(5000) NOT NULL,
			  `fieldName` varchar(100) NOT NULL,
			  `isRequired` varchar(50) NOT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ");

			$this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "wk_custom_field_product_options (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `pro_id` int(100) NOT NULL,
			  `product_id` int(100) NOT NULL,
			  `fieldId` int(100) NOT NULL,
			  `option_id` varchar(100) CHARACTER SET utf8 NOT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ");

			//Table structure for table `customerpartner_to_order_status`
			$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_to_order_status` (
			  `change_orderstatus_id` int(11) NOT NULL AUTO_INCREMENT,
			  `order_id` int(11) NOT NULL,
			  `product_id` int(11) NOT NULL,
			  `customer_id` int(11) NOT NULL,
			  `order_status_id` int(11) NOT NULL,
			  `comment` text NOT NULL,
			  `date_added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			  PRIMARY KEY (`change_orderstatus_id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

			//Table structure for table `customerpartner_to_category`
			$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_to_category` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `seller_id` int(11) NOT NULL,
			  `category_id` varchar(1000) NOT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

			//Table structure for table `wk_category_attribute_mapping`
			$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."wk_category_attribute_mapping` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `category_id` int(11) NOT NULL,
			  `attribute_id` varchar(1000) NOT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

			//Table structure for table `seller_activity`
			$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."seller_activity` (
			  `seller_activity_id` int(11) NOT NULL AUTO_INCREMENT,
				`activity_id` int(11) NOT NULL,
			  `seller_id` int(11) NOT NULL,
			  PRIMARY KEY (`seller_activity_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci") ;

			//Table structure for table `mp_customer_activity`
			//id used for customer_id(key=order_status) or product_id(key=product_stock)
			$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."mp_customer_activity` (
			  `customer_activity_id` int(11) NOT NULL AUTO_INCREMENT,
			  `id` int(11) NOT NULL,
			  `key` varchar(64) NOT NULL,
			  `data` text NOT NULL,
			  `ip` varchar(40) NOT NULL,
			  `date_added` datetime NOT NULL,
			  PRIMARY KEY (`customer_activity_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci") ;

			$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."viewed_activity` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `customer_id` int(11) NOT NULL,
			  `notification_viewed` int(11) NOT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci") ;

			//Table structure for table `customerpartner_to_sellercategory`
			$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_to_sellercategory` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `seller_id` int(11) NOT NULL,
			  `category_id` int(11) NOT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;

			//Table structure for table `customerpartner_to_information`
			$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX ."customerpartner_to_information` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `seller_id` int(11) NOT NULL,
			  `information_id` int(11) NOT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1") ;
	}

	public function deleteCustomer($customer_id) {
		$partner_products = $this->db->query("SELECT product_id FROM ".DB_PREFIX."customerpartner_to_product WHERE customer_id = '".(int)$customer_id."'")->rows;
			$this->deleteCustomerActivity($customer_id);
	    $this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_download WHERE seller_id = '" . (int)$customer_id . "'");
	    $this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_flatshipping WHERE partner_id = '" . (int)$customer_id . "'");
	    $this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_shipping WHERE seller_id = '" . (int)$customer_id . "'");
	    $this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_to_feedback WHERE seller_id = '" . (int)$customer_id . "'");
	    $this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_to_customer WHERE customer_id = '" . (int)$customer_id . "'");
	    $this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_sold_tracking WHERE customer_id = '" . (int)$customer_id . "'");
	    $this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_to_commission WHERE customer_id = '" . (int)$customer_id . "'");
	    $this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_to_order WHERE customer_id = '" . (int)$customer_id . "'");
	    $this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_to_order_status WHERE customer_id = '" . (int)$customer_id . "'");
	    $this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_to_payment WHERE customer_id = '" . (int)$customer_id . "'");
	    $this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_to_product WHERE customer_id = '" . (int)$customer_id . "'");
	    $this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_to_transaction WHERE customer_id = '" . (int)$customer_id . "'");

	    /**
	     * Membership code for delete seller data from membership tables
	     */
    	if($this->config->get('module_wk_seller_group_status')) {
    		$this->db->query("DELETE FROM " . DB_PREFIX . "seller_group_customer WHERE customer_id = '" . (int)$customer_id . "'");
    		$this->db->query("DELETE FROM " . DB_PREFIX . "seller_group_commission_categorywise WHERE seller_id = '" . (int)$customer_id . "'");
    		$this->db->query("DELETE FROM " . DB_PREFIX . "seller_group_payment WHERE customer_id = '" . (int)$customer_id . "'");
    		$this->db->query("DELETE FROM " . DB_PREFIX . "seller_group_product_listing WHERE seller_id = '" . (int)$customer_id . "'");
    		$this->db->query("DELETE FROM " . DB_PREFIX . "seller_group_product_listing_duration WHERE seller_id = '" . (int)$customer_id . "'");
    		$this->db->query("DELETE FROM " . DB_PREFIX . "seller_group_product_listing_fee WHERE seller_id = '" . (int)$customer_id . "'");
    		$this->db->query("DELETE FROM " . DB_PREFIX . "seller_group_product_quantity WHERE seller_id = '" . (int)$customer_id . "'");
    		$this->db->query("DELETE FROM " . DB_PREFIX . "seller_group_product_setting WHERE seller_id = '" . (int)$customer_id . "'");
    		$this->db->query("DELETE FROM " . DB_PREFIX . "seller_group_customer_seller_group WHERE seller_id = '" . (int)$customer_id . "'");
    	}
    	/**
	     * Membership code for delete seller data from membership tables
	     */

	    if($partner_products){
	      	foreach($partner_products as $products){
		      	if($this->config->get('wk_mpaddproduct_status')) {
			      	$this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_to_product WHERE product_id = '" . (int)$products['product_id'] . "' AND customer_id = '" . (int)$customer_id . "' ");
			    } else if(!$this->config->get('marketplace_sellerproductdelete')){
		        	$this->db->query("DELETE FROM " . DB_PREFIX . "customerpartner_to_product WHERE product_id = '" . (int)$products['product_id'] . "'");

		        	$this->db->query("UPDATE " . DB_PREFIX . "product SET status = '0' WHERE product_id = '" . (int)$products['product_id'] . "'");
		       	}else{

                    $this->load->model('catalog/product');
                    $this->model_catalog_product->deleteProduct($products['product_id']);
		       	}
	      	}
	    }

	}

	public function getCustomer($customer_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int)$customer_id . "'");

		return $query->row;
	}


	public function getAllCustomers() {

		$sql = $this->db->query("SELECT c.email FROM " . DB_PREFIX . "customerpartner_to_customer c2c LEFT JOIN " . DB_PREFIX . "customer c ON (c2c.customer_id = c.customer_id) WHERE c2c.is_partner = '1'");

		return $sql->rows;

	}

	public function getCustomers($data = array()) {

		if(isset($data['filter_all']) AND $data['filter_all'] == '1'){
			$add = '';
		}elseif(isset($data['filter_all']) AND $data['filter_all'] == '2'){
			$add = ' c2c.is_partner = 0 AND';
		}else{
			$add = ' c2c.is_partner = 1 AND';
		}

		$sql = "SELECT *,c.status, CONCAT(c.firstname, ' ', c.lastname) AS name,c.customer_id AS customer_id, c2c.is_partner,cgd.name AS customer_group FROM " . DB_PREFIX . "customer c LEFT JOIN " . DB_PREFIX . "customer_group_description cgd ON (c.customer_group_id = cgd.customer_group_id) LEFT JOIN ". DB_PREFIX . "customerpartner_to_customer c2c ON (c2c.customer_id = c.customer_id) WHERE ". $add ." cgd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		$implode = array();

		if (!empty($data['filter_name'])) {
			$implode[] = "LCASE(CONCAT(c.firstname, ' ', c.lastname)) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
		}

		if (!empty($data['filter_email'])) {
			$implode[] = "LCASE(c.email) LIKE '" . $this->db->escape(utf8_strtolower($data['filter_email'])) . "%'";
		}

		if (isset($data['filter_newsletter']) && !is_null($data['filter_newsletter'])) {
			$implode[] = "c.newsletter = '" . (int)$data['filter_newsletter'] . "'";
		}

		if (isset($data['filter_customer_group_id']) && !empty($data['filter_customer_group_id'])) {
			$implode[] = "c.customer_group_id = '" . (int)$data['filter_customer_group_id'] . "'";
		}

		if (isset($data['filter_ip']) && !empty($data['filter_ip'])) {
			$implode[] = "c.customer_id IN (SELECT customer_id FROM " . DB_PREFIX . "customer_ip WHERE ip = '" . $this->db->escape($data['filter_ip']) . "')";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "c.status = '" . (int)$data['filter_status'] . "'";
		}

		if (isset($data['filter_approved']) && !is_null($data['filter_approved'])) {
			$implode[] = "c2c.is_partner = '" . (int)$data['filter_approved'] . "'";
		}

		if (isset($data['filter_date_added']) && !empty($data['filter_date_added'])) {
			$implode[] = "DATE(c.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		if (isset($data['filter_category']) && !empty($data['filter_category'])) {
			$implode[] = "c.customer_id NOT IN (SELECT seller_id FROM " . DB_PREFIX . "customerpartner_to_category)";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'c.customer_id',
			'name',
			'c.email',
			'customer_group',
			'c.status',
			'c.approved',
			'c.ip',
			'c.date_added'
		);


		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY c.customer_id";
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

	public function getTotalCustomers($data = array()) {

      	if(isset($data['filter_all']) AND $data['filter_all'] == '1'){
			$add = '';
		}elseif(isset($data['filter_all']) AND $data['filter_all'] == '2'){
			$add = ' c2c.is_partner = 0 AND';
		}else{
			$add = ' c2c.is_partner = 1 AND';
		}

		$sql = "SELECT *,c.status, CONCAT(c.firstname, ' ', c.lastname) AS name,c.customer_id AS customer_id, c2c.is_partner,cgd.name AS customer_group FROM " . DB_PREFIX . "customer c LEFT JOIN " . DB_PREFIX . "customer_group_description cgd ON (c.customer_group_id = cgd.customer_group_id) LEFT JOIN ". DB_PREFIX . "customerpartner_to_customer c2c ON (c2c.customer_id = c.customer_id) WHERE ". $add ." cgd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		$implode = array();

		$implode = array();

		if (!empty($data['filter_name'])) {
			$implode[] = "LCASE(CONCAT(c.firstname, ' ', c.lastname)) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
		}

		if (!empty($data['filter_email'])) {
			$implode[] = "LCASE(c.email) LIKE '" . $this->db->escape(utf8_strtolower($data['filter_email'])) . "%'";
		}

		if (isset($data['filter_newsletter']) && !is_null($data['filter_newsletter'])) {
			$implode[] = "c.newsletter = '" . (int)$data['filter_newsletter'] . "'";
		}

		if (!empty($data['filter_customer_group_id'])) {
			$implode[] = "c.customer_group_id = '" . (int)$data['filter_customer_group_id'] . "'";
		}

		if (!empty($data['filter_ip'])) {
			$implode[] = "c.customer_id IN (SELECT customer_id FROM " . DB_PREFIX . "customer_ip WHERE ip = '" . $this->db->escape($data['filter_ip']) . "')";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "c.status = '" . (int)$data['filter_status'] . "'";
		}

		if (isset($data['filter_approved']) && !is_null($data['filter_approved'])) {
			$implode[] = "c2c.is_partner = '" . (int)$data['filter_approved'] . "'";
		}

		if (!empty($data['filter_date_added'])) {
			$implode[] = "DATE(c.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'c.customer_id',
			'name',
			'c.email',
			'customer_group',
			'c.status',
			'c.approved',
			'c.ip',
			'c.date_added'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY c.customer_id";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		$query = $this->db->query($sql);

		return count($query->rows);
	}

	public function approve($customer_id,$setstatus = 1) {

		$customer_info = $this->getCustomer($customer_id);

		if ($customer_info) {

			$commission = (int)$this->config->get('marketplace_commission') ? (int)$this->config->get('marketplace_commission') : 0;

			$seller_info = $this->getPartner($customer_id);

			if($seller_info){
				$this->db->query("UPDATE " . DB_PREFIX . "customerpartner_to_customer SET is_partner='".(int)$setstatus."',commission = '".(float)$commission."' WHERE customer_id = '" . (int)$customer_id . "', accounting_type = " . (int)$customer_info['accounting_type']);

				// membership modification after partner
	        	if($this->config->get('module_wk_seller_group_status') && !$this->db->query("SELECT * FROM `" . DB_PREFIX . "seller_group_customer_seller_group` WHERE seller_id = '" . (int)$customer_id . "'")->num_rows) {
			        $this->load->model('customerpartner/sellergroup');
			        $freeMembership = $this->db->query("SELECT * FROM ".DB_PREFIX."seller_group WHERE autoApprove = 1 ")->row;

			        if($freeMembership && isset($freeMembership['product_id']) && $freeMembership['product_id']) {
								$group_id = $freeMembership['groupid'];
								$this->model_customerpartner_sellergroup->update_customer_quantity($customer_id,$group_id,'unpaid',true);
					}
				}

			}else{
				$name = $customer_info['firstname'].'-'.$customer_info['lastname'].'-'. md5(mt_rand());

				$this->db->query("INSERT INTO " . DB_PREFIX . "customerpartner_to_customer SET customer_id = '" . (int)$customer_id . "', is_partner='".(int)$setstatus."', commission = '".(float)$commission."', screenname = '" . $name . "', companyname = '" . $name . "', accounting_type = " . (int)$customer_info['accounting_type']);

				// membership modification after partner
	        	if($this->config->get('module_wk_seller_group_status') && !$this->db->query("SELECT * FROM `" . DB_PREFIX . "seller_group_customer_seller_group` WHERE seller_id = '" . (int)$customer_id . "'")->num_rows) {
			        $this->load->model('customerpartner/sellergroup');
			        $freeMembership = $this->db->query("SELECT * FROM ".DB_PREFIX."seller_group WHERE autoApprove = 1 ")->row;

			        if($freeMembership && isset($freeMembership['product_id']) && $freeMembership['product_id']) {
								$group_id = $freeMembership['groupid'];
								$this->model_customerpartner_sellergroup->update_customer_quantity($customer_id,$group_id,'unpaid',true);
					}
				}

			}

			if(!$this->config->get('marketplace_mail_partner_approve'))
				return;

			$data = array_merge($customer_info,$seller_info);

			//send mail to Customer after request for Partnership
			if(!$data['is_partner'] ) {
				$data['mail_id'] = $this->config->get('marketplace_mail_partner_approve');
				$data['mail_from'] = $this->config->get('marketplace_adminmail');
				$data['mail_to'] = $data['email'];
				$data['seller_id'] = $customer_id;
				$value_index = array(
		      'commission' => (int)$this->config->get('marketplace_commission') ? (int)$this->config->get('marketplace_commission') : 0,
					'seller_id' => $customer_id
		    );

				$this->load->model('customerpartner/mail');

				$this->model_customerpartner_mail->mail($data,$value_index);
			}

	  }

 }

	//for get commission
	public function getPartner($partner_id){
		$query = $this->db->query("SELECT * FROM ".DB_PREFIX ."customerpartner_to_customer where customer_id='".(int)$partner_id."'");
		return($query->row);
	}

	public function getPartnerCustomerInfo($partner_id){
		$query = $this->db->query("SELECT * FROM ".DB_PREFIX ."customerpartner_to_customer c2c LEFT JOIN ".DB_PREFIX."customer c ON (c2c.customer_id = c.customer_id) where c.customer_id='".(int)$partner_id."'");
		return($query->row);
	}

	public function updatePartner($partner_id,$data){
        /**
         *迁移oss 重新读取数据库地址
         */
        $imgIds = [
            'avatar' => $data['customer']['avatar'],
            'qr_code' => $data['customer']['qr_code'],
            'companybanner' => $data['customer']['companybanner'],
            'companylogo' => $data['customer']['companylogo']
        ];
        $catalogImg = app(UploadInformationRepository::class)->getListPath(array_map('intval',array_values($imgIds)));
        foreach ($imgIds as $index => $val) {
            if (is_numeric($data['customer'][$index]) && $catalogImg[intval($data['customer'][$index])]) {
                $data['customer'][$index] = ltrim($catalogImg[$data['customer'][$index]]['file_path'], 'image/');
            }
        }

        $this->load->model('customerpartner/htmlfilter');

		if(isset($data['customer']) AND $data['customer']){

			$data = $data['customer'];

			$sql = "UPDATE ".DB_PREFIX ."customerpartner_to_customer SET ";

			foreach ($data as $key => $value) {
				if ($key == 'shortprofile' || $key == 'companydescription') {
					$sql .= $key." = '".$this->db->escape(htmlentities($this->model_customerpartner_htmlfilter->HTMLFilter(html_entity_decode($value),'',true)))."' ,";
				} else {
					$sql .= $key." = '".$this->db->escape($value)."' ,";
				}
			}

			$sql = substr($sql,0,-2);
			$sql .= " WHERE customer_id = '".(int)$partner_id."'";

			$this->db->query($sql);
		}

	}

	//for update product seller
	public function updateProductSeller($partner_id,$product){
		$product_ids = array();
		if($product AND is_array($product)){
			foreach($product as $individual_product){
				$product_ids[] = $individual_product['selected'];
			}
		}else
			$product_ids[] = $product;

			$this->load->model('catalog/product');
			foreach($product_ids as $product_id){
				$getProductDetails = $this->model_catalog_product->getProduct($product_id);
				$price = $quantity = 0;
				if(!empty($getProductDetails) && isset($getProductDetails['price']) && isset($getProductDetails['quantity'])){
						$price 		= $getProductDetails['price'];
						$quantity = $getProductDetails['quantity'];
				}

				$status = $this->chkProduct($product_id);

				if ($this->config->get('wk_seller_group_status')) {
					if($status==1){
						$this->db->query( "INSERT INTO ".DB_PREFIX ."customerpartner_to_product SET product_id = '".(int)$product_id."', `price` = '".(float)$price."', `seller_price` = '".(float)$price."', `quantity` = '".(int)$quantity."', customer_id = '".(int)$partner_id."', `current_status` = '" . (isset($getProductDetails['status']) && $getProductDetails['status'] ? 'active' : '') . "'");

					}else{
						$this->db->query( "UPDATE ".DB_PREFIX ."customerpartner_to_product SET `price` = '".(float)$price."', `seller_price` = '".(float)$price."', `quantity` = '".(int)$quantity."', customer_id = '".(int)$partner_id."', `current_status` = '" . (isset($getProductDetails['status']) && $getProductDetails['status'] ? 'active' : '') . "' WHERE product_id = '".(int)$product_id."' ORDER BY id ASC LIMIT 1 ");

					}
				} else {
					if($status==1){
						$this->db->query( "INSERT INTO ".DB_PREFIX ."customerpartner_to_product SET product_id = '".(int)$product_id."', `price` = '".(float)$price."', `seller_price` = '".(float)$price."', `quantity` = '".(int)$quantity."', customer_id = '".(int)$partner_id."'");

					}else{
						$this->db->query( "UPDATE ".DB_PREFIX ."customerpartner_to_product SET `price` = '".(float)$price."', `seller_price` = '".(float)$price."', `quantity` = '".(int)$quantity."', customer_id = '".(int)$partner_id."' WHERE product_id = '".(int)$product_id."' ORDER BY id ASC LIMIT 1 ");
					}
				}
		}
	}

	public function addproduct($partner_id,$data){
		$product_ids = $data['product_ids'];
		$this->load->model('catalog/product');
		foreach($product_ids as $product_id){
			$getProductDetails = $this->model_catalog_product->getProduct($product_id);
			$price = $quantity = 0;
			if(!empty($getProductDetails) && isset($getProductDetails['price']) && isset($getProductDetails['quantity'])){
					$price 		= $getProductDetails['price'];
					$quantity = $getProductDetails['quantity'];
			}
			$status = $this->chkProduct($product_id);

			if ($this->config->get('wk_seller_group_status')) {
				if($status==1){
					$this->db->query( "INSERT INTO ".DB_PREFIX ."customerpartner_to_product SET product_id = '".(int)$product_id."', `price` = '".(float)$price."', `seller_price` = '".(float)$price."', `quantity` = '".(int)$quantity."', customer_id = '".(int)$partner_id."', `current_status` = '" . (isset($getProductDetails['status']) && $getProductDetails['status'] ? 'active' : '') . "'");

				}else{
					$this->db->query( "UPDATE ".DB_PREFIX ."customerpartner_to_product SET `price` = '".(float)$price."', `seller_price` = '".(float)$price."', `quantity` = '".(int)$quantity."', customer_id = '".(int)$partner_id."', `current_status` = '" . (isset($getProductDetails['status']) && $getProductDetails['status'] ? 'active' : '') . "' WHERE product_id = '".(int)$product_id."' ORDER BY id ASC LIMIT 1 ");

				}
			} else {
				if($status==1){
					$this->db->query( "INSERT INTO ".DB_PREFIX ."customerpartner_to_product SET product_id = '".(int)$product_id."', `price` = '".(float)$price."', `seller_price` = '".(float)$price."', `quantity` = '".(int)$quantity."', customer_id = '".(int)$partner_id."'");

				}else{
					$this->db->query( "UPDATE ".DB_PREFIX ."customerpartner_to_product SET `price` = '".(float)$price."', `seller_price` = '".(float)$price."', `quantity` = '".(int)$quantity."', customer_id = '".(int)$partner_id."' WHERE product_id = '".(int)$product_id."' ORDER BY id ASC LIMIT 1 ");
				}
			}
		}
	}


	public function chkProduct($pid){

		$sql = $this->db->query("SELECT quantity FROM ".DB_PREFIX ."product WHERE product_id='".(int)$pid."'");

		if(isset($sql->row['quantity'])){
			$sql = $this->db->query("SELECT customer_id FROM ".DB_PREFIX ."customerpartner_to_product WHERE product_id='".(int)$pid."'");
			if(isset($sql->row['customer_id'])){
				if($sql->row['customer_id'])
					return 0; //already exists
				else
					return 2; //for update return cp product id
			}else{
				return 1; //not exists so copy
			}
		}else{
			return 0; //already exists
		}

	}

	public function getPartnerAmount($partner_id){
		$total = $this->db->query("SELECT SUM(c2o.quantity) quantity,SUM(c2o.price) total,SUM(c2o.admin) admin,SUM(c2o.customer) customer FROM ".DB_PREFIX ."customerpartner_to_order c2o WHERE c2o.customer_id ='".(int)$partner_id."'")->row;

		$paid = $this->db->query("SELECT SUM(c2t.amount) total FROM ".DB_PREFIX ."customerpartner_to_transaction c2t WHERE c2t.customer_id ='".(int)$partner_id."'")->row;

		$total['paid'] = $paid['total'];

		return($total);
	}

	public function getPartnerTotal($partner_id,$filter_data = array() ) {

		$sub_query = "(SELECT SUM(c2t.amount) as total FROM ".DB_PREFIX ."customerpartner_to_transaction c2t WHERE c2t.customer_id ='".(int)$partner_id."'";

		if(isset($filter_data['date_added_from']) || isset($filter_data['date_added_to'])) {
			if($filter_data['date_added_from'] && $filter_data['date_added_to']){
				$sub_query .= " AND c2t.date_added >= '".$filter_data['date_added_from']."' && c2t.date_added <= '".$filter_data['date_added_to']."' ";
			} else if($filter_data['date_added_from'] && !$filter_data['date_added_to']) {
				$sub_query .= " AND c2t.date_added >= '".$filter_data['date_added_from']."' ";
			} else if(!$filter_data['date_added_from'] && $filter_data['date_added_to']) {
				$sub_query .= " AND c2t.date_added <= '".$filter_data['date_added_to']."' ";
			}
		}

		if(isset($filter_data['paid_to_seller_from']) || isset($filter_data['paid_to_seller_to']) ) {
			if($filter_data['paid_to_seller_from'] && $filter_data['paid_to_seller_to']) {
				$sub_query .= " HAVING SUM(c2t.amount) > ".$filter_data['paid_to_seller_from']." && SUM(c2t.amount) < ".$filter_data['paid_to_seller_to']." )";
			} else if($filter_data['paid_to_seller_from'] && !$filter_data['paid_to_seller_to']) {
				$sub_query .= " HAVING SUM(c2t.amount) > ".$filter_data['paid_to_seller_from']." )";
			} else if(!$filter_data['paid_to_seller_from'] && $filter_data['paid_to_seller_to']) {
				$sub_query .= " HAVING SUM(c2t.amount) > ".$filter_data['paid_to_seller_to']." )";
			} else {
				$sub_query .= " )";
			}
		} else {
			$sub_query .= " )";
		}

		$sql = "SELECT SUM(c2o.quantity) quantity,(SUM(c2o.customer) + SUM(c2o.admin)) as total,SUM(c2o.admin) admin,SUM(c2o.customer) as customer, ".$sub_query." as paid FROM ".DB_PREFIX ."customerpartner_to_order c2o WHERE c2o.customer_id ='".(int)$partner_id."' ";

		if ($this->config->get('marketplace_complete_order_status')) {
		  $sql .= " AND c2o.order_id IN (SELECT DISTINCT order_id FROM `" . DB_PREFIX . "order` o LEFT JOIN `" . DB_PREFIX . "order_status` os ON (o.order_status_id = os.order_status_id) WHERE os.order_status_id = '". $this->config->get('marketplace_complete_order_status') ."') ";
		}

		if(isset($filter_data['date_added_from']) || isset($filter_data['date_added_to'])) {
			if($filter_data['date_added_from'] && $filter_data['date_added_to']){
				$sql .= " AND c2o.date_added >= '".$filter_data['date_added_from']."' && c2o.date_added <= '".$filter_data['date_added_to']."' ";
			} else if($filter_data['date_added_from'] && !$filter_data['date_added_to']) {
				$sql .= " AND c2o.date_added >= '".$filter_data['date_added_from']."' ";
			} else if(!$filter_data['date_added_from'] && $filter_data['date_added_to']) {
				$sql .= " AND c2o.date_added <= '".$filter_data['date_added_to']."' ";
			}
		}

		$sql .= " HAVING SUM(c2o.quantity) >= 0 ";

		if(isset($filter_data['total_amount_from']) || isset($filter_data['total_amount_to']) ) {
			if($filter_data['total_amount_from'] && $filter_data['total_amount_to']){
				$sql .= " AND (SUM(c2o.customer) + SUM(c2o.admin)) > ".$filter_data['total_amount_from']." && (SUM(c2o.customer) + SUM(c2o.admin)) < ".$filter_data['total_amount_to']." ";
			} else if($filter_data['total_amount_from'] && !$filter_data['total_amount_to']) {
				$sql .= " AND (SUM(c2o.customer) + SUM(c2o.admin)) > ".$filter_data['total_amount_from']." ";
			} else if(!$filter_data['total_amount_from'] && $filter_data['total_amount_to']) {
				$sql .= " AND (SUM(c2o.customer) + SUM(c2o.admin)) < ".$filter_data['total_amount_to']."";
			}
		}

		if(isset($filter_data['seller_amount_from']) || isset($filter_data['seller_amount_to']) ) {
			if($filter_data['seller_amount_from'] && $filter_data['seller_amount_to']){
				$sql .= " AND SUM(c2o.customer) > ".$filter_data['seller_amount_from']." && SUM(c2o.customer) < ".$filter_data['seller_amount_to']." ";
			} else if($filter_data['seller_amount_from'] && !$filter_data['seller_amount_to']) {
				$sql .= " AND SUM(c2o.customer) > ".$filter_data['seller_amount_from']." ";
			} else if(!$filter_data['seller_amount_from'] && $filter_data['seller_amount_to']) {
				$sql .= " AND SUM(c2o.customer) < ".$filter_data['seller_amount_to']."";
			}
		}

		if(isset($filter_data['admin_amount_from']) || isset($filter_data['admin_amount_to']) ) {
			if($filter_data['admin_amount_from'] && $filter_data['admin_amount_to']){
				$sql .= " AND SUM(c2o.admin) > ".$filter_data['admin_amount_from']." && SUM(c2o.admin) < ".$filter_data['admin_amount_to']." ";
			} else if($filter_data['admin_amount_from'] && !$filter_data['admin_amount_to']) {
				$sql .= " AND SUM(c2o.admin) > ".$filter_data['admin_amount_from']." ";
			} else if(!$filter_data['admin_amount_from'] && $filter_data['admin_amount_to']) {
				$sql .= " AND SUM(c2o.admin) < ".$filter_data['admin_amount_to']."";
			}
		}

		// echo $sql;
		$total = $this->db->query($sql)->row;

		return($total);
	}

	public function getPartnerAmountTotal($partner_id,$filter_data = array() ){
		$sql = "SELECT SUM(c2o.quantity) quantity,SUM(c2o.price) total,SUM(c2o.admin) admin,SUM(c2o.customer) customer FROM ".DB_PREFIX ."customerpartner_to_order c2o WHERE c2o.customer_id ='".(int)$partner_id."' ";
		echo $sql;

		if($filter_data['commission_from'] && $filter_data['commission_to']){
			$url .= '';
		} else if($filter_data['commission_from'] && !$filter_data['commission_to']) {
			$url .= '';
		} else if(!$filter_data['commission_from'] && $filter_data['commission_to']) {
			$url .= '';
		}

		$total = $this->db->query($sql)->row;

		$paid = $this->db->query("SELECT SUM(c2t.amount) total FROM ".DB_PREFIX ."customerpartner_to_transaction c2t WHERE c2t.customer_id ='".(int)$partner_id."'")->row;

		$total['paid'] = $paid['total'];

		return(count($total));
	}

	public function getSellerOrdersListPaypal($seller_id){

		if ($this->config->get('marketplace_complete_order_status')) {
			$complete_order_status = $this->config->get('marketplace_complete_order_status');
		} else {
			$complete_order_status = '5';
		}

		$sql = "SELECT DISTINCT op.order_id,c2o.paid_status,c2o.commission_applied,c2o.customer as need_to_pay,o.date_added, CONCAT(o.firstname ,' ',o.lastname) name ,os.order_status_id orderstatus,os.name orderstatus_name,op.*, (SELECT group_concat( concat( value) SEPARATOR ', ') FROM ".DB_PREFIX."order_option oo WHERE oo.order_product_id=c2o.order_product_id ) as value  FROM " . DB_PREFIX ."order_status os LEFT JOIN `".DB_PREFIX ."order` o ON (os.order_status_id = o.order_status_id) LEFT JOIN ".DB_PREFIX ."customerpartner_to_order c2o ON (o.order_id = c2o.order_id) LEFT JOIN ".DB_PREFIX."order_product op ON op.order_product_id=c2o.order_product_id WHERE c2o.customer_id = '".(int)$seller_id."' AND os.language_id = '".$this->config->get('config_language_id')."' AND c2o.paid_status = 0 AND os.order_status_id = '".$complete_order_status."'";

		$result = $this->db->query($sql);

		return($result->rows);
	}

	public function getSellerOrdersList($seller_id,$filter_data){

		$sql = "SELECT DISTINCT op.order_id,c2o.paid_status,c2o.commission_applied,c2o.customer as need_to_pay,o.date_added, CONCAT(o.firstname ,' ',o.lastname) name ,os.order_status_id orderstatus,os.name orderstatus_name,op.*, (SELECT group_concat( concat( value) SEPARATOR ', ') FROM ".DB_PREFIX."order_option oo WHERE oo.order_product_id=c2o.order_product_id ) as value  FROM " . DB_PREFIX ."order_status os LEFT JOIN `".DB_PREFIX ."order` o ON (os.order_status_id = o.order_status_id) LEFT JOIN ".DB_PREFIX ."customerpartner_to_order c2o ON (o.order_id = c2o.order_id) LEFT JOIN ".DB_PREFIX."order_product op ON op.order_product_id=c2o.order_product_id WHERE c2o.customer_id = '".(int)$seller_id."' AND os.language_id = '".$this->config->get('config_language_id')."' ";

		if ($this->config->get('marketplace_complete_order_status')) {
		  $sql .= " AND os.order_status_id = '" . $this->config->get('marketplace_complete_order_status') . "' ";
		}

		if(isset($filter_data['date_added_from']) || isset($filter_data['date_added_to'])) {
			if($filter_data['date_added_from'] && $filter_data['date_added_to']){
				$sql .= " AND o.date_added >= '".$filter_data['date_added_from']."' && o.date_added <= '".$filter_data['date_added_to']."' ";
			} else if($filter_data['date_added_from'] && !$filter_data['date_added_to']) {
				$sql .= " AND o.date_added >= '".$filter_data['date_added_from']."' ";
			} else if(!$filter_data['date_added_from'] && $filter_data['date_added_to']) {
				$sql .= " AND o.date_added <= '".$filter_data['date_added_to']."' ";
			}
		}

		if($filter_data['order_id']) {
			$sql .= " AND op.order_id = '".$filter_data['order_id']."' " ;
		}

		if($filter_data['payable_amount']) {
			$sql .= " AND c2o.customer = ".(float)$filter_data['payable_amount']." " ;
		}

		if($filter_data['quantity']) {
			$sql .= " AND op.quantity = '".$filter_data['quantity']."' " ;
		}

		if($filter_data['order_status']) {
			$sql .= " AND os.name = '".$filter_data['order_status']."' " ;
		}

		if($filter_data['paid_status']) {
			if($filter_data['paid_status'] == 'paid')
				$filter_data['paid_status'] = 1;
			else
				$filter_data['paid_status'] = 0;
			$sql .= " AND c2o.paid_status = '".$filter_data['paid_status']."' " ;
		}
		if($filter_data['order_by'] && $filter_data['sort_by']) {
			$sql .= "ORDER BY ".$filter_data['order_by']." ".$filter_data['sort_by']." LIMIT ".$filter_data['start'].", ".$filter_data['limit']."";
		} else {
			$sql .= "ORDER BY o.order_id asc LIMIT ".$filter_data['start'].", ".$filter_data['limit']."";
		}
		$result = $this->db->query($sql);
		return($result->rows);
	}

	public function getProductOptions($order_product_id){
		return $this->db->query("SELECT oo.value FROM ".DB_PREFIX."order_option oo WHERE oo.order_product_id = '".(int)$order_product_id."'")->rows;
	}

	public function getSellerOrders($seller_id){

		$sql = $this->db->query("SELECT DISTINCT o.order_id ,o.date_added, CONCAT(o.firstname ,' ',o.lastname) name ,os.name orderstatus  FROM " . DB_PREFIX ."order_status os LEFT JOIN `".DB_PREFIX ."order` o ON (os.order_status_id = o.order_status_id) LEFT JOIN ".DB_PREFIX ."customerpartner_to_order c2o ON (o.order_id = c2o.order_id) WHERE c2o.customer_id = '".(int)$seller_id."' AND os.language_id = '".$this->config->get('config_language_id')."' ORDER BY o.order_id DESC ");

		return($sql->rows);
	}

	public function getTotalSellerOrders($seller_id,$filter_data){

		$sql = "SELECT DISTINCT op.order_id,c2o.paid_status,c2o.customer as need_to_pay,o.date_added, CONCAT(o.firstname ,' ',o.lastname) name ,os.name orderstatus,op.*, (SELECT group_concat( concat( value) SEPARATOR ', ') FROM ".DB_PREFIX."order_option oo WHERE oo.order_product_id=c2o.order_product_id ) as value  FROM " . DB_PREFIX ."order_status os LEFT JOIN `".DB_PREFIX ."order` o ON (os.order_status_id = o.order_status_id) LEFT JOIN ".DB_PREFIX ."customerpartner_to_order c2o ON (o.order_id = c2o.order_id) LEFT JOIN ".DB_PREFIX."order_product op ON op.order_product_id=c2o.order_product_id WHERE c2o.customer_id = '".(int)$seller_id."' AND os.language_id = '".$this->config->get('config_language_id')."' ";

		if ($this->config->get('marketplace_complete_order_status')) {
		  $sql .= " AND os.order_status_id = '" . $this->config->get('marketplace_complete_order_status') . "' ";
		}

		if(isset($filter_data['date_added_from']) || isset($filter_data['date_added_to'])) {
			if($filter_data['date_added_from'] && $filter_data['date_added_to']){
				$sql .= " AND o.date_added >= '".$filter_data['date_added_from']."' && o.date_added <= '".$filter_data['date_added_to']."' ";
			} else if($filter_data['date_added_from'] && !$filter_data['date_added_to']) {
				$sql .= " AND o.date_added >= '".$filter_data['date_added_from']."' ";
			} else if(!$filter_data['date_added_from'] && $filter_data['date_added_to']) {
				$sql .= " AND o.date_added <= '".$filter_data['date_added_to']."' ";
			}
		}

		if($filter_data['order_id']) {
			$sql .= " AND op.order_id = '".$filter_data['order_id']."' " ;
		}

		if($filter_data['payable_amount']) {
			$sql .= " AND c2o.customer = '".$filter_data['payable_amount']."' " ;
		}

		if($filter_data['quantity']) {
			$sql .= " AND op.quantity = '".$filter_data['quantity']."' " ;
		}

		if($filter_data['order_status']) {
			$sql .= " AND os.name = '".$filter_data['order_status']."' " ;
		}

		if($filter_data['paid_status']) {
			$sql .= " AND c2o.paid_status = '".$filter_data['paid_status']."' " ;
		}

		$result = $this->db->query($sql);

		return(count($result->rows));
	}

	public function getSellerOrderProducts($order_id){

		$sql = $this->db->query("SELECT op.*,c2o.price c2oprice FROM " . DB_PREFIX ."customerpartner_to_order c2o LEFT JOIN " . DB_PREFIX . "order_product op ON (c2o.order_product_id = op.order_product_id AND c2o.order_id = op.order_id) WHERE c2o.order_id = '".(int)$order_id."' ORDER BY op.product_id ");

		return($sql->rows);
	}

	public function getCategories($data = array(), $allowed_categories){

		if (isset($this->request->get['category_mapping']) && $this->request->get['category_mapping']) {
			$categories = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "wk_category_attribute_mapping")->rows;
			if ($categories) {
				$category_ids = array();

				foreach ($categories as $key => $category) {
					$category_ids[] = $category['category_id'];
				}

				$allowed_categories = implode(',',$category_ids);
			}
		}

		if ($allowed_categories) {

			$sql = "SELECT cp.category_id AS category_id, GROUP_CONCAT(cd1.name ORDER BY cp.level SEPARATOR '&nbsp;&nbsp;&gt;&nbsp;&nbsp;') AS name, c1.parent_id, c1.sort_order FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "category c1 ON (cp.category_id = c1.category_id) LEFT JOIN " . DB_PREFIX . "category c2 ON (cp.path_id = c2.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd1 ON (cp.path_id = cd1.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd2 ON (cp.category_id = cd2.category_id) WHERE cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cd2.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cp.category_id NOT IN (" . $allowed_categories . ")";

		}else{

            $sql = "SELECT cp.category_id AS category_id, GROUP_CONCAT(cd1.name ORDER BY cp.level SEPARATOR '&nbsp;&nbsp;&gt;&nbsp;&nbsp;') AS name, c1.parent_id, c1.sort_order FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "category c1 ON (cp.category_id = c1.category_id) LEFT JOIN " . DB_PREFIX . "category c2 ON (cp.path_id = c2.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd1 ON (cp.path_id = cd1.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd2 ON (cp.category_id = cd2.category_id) WHERE cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cd2.language_id = '" . (int)$this->config->get('config_language_id') . "'";
		}

		if (!empty($data['filter_name'])) {
			$sql .= " AND cd2.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}

		$sql .= " GROUP BY cp.category_id";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getAdminProducts($seller_id,$data){

		$marketplace_allowed_categories = '';

		$seller_category = $this->db->query("SELECT category_id FROM ".DB_PREFIX."customerpartner_to_category WHERE seller_id = ".(int)$seller_id)->row;

		if (isset($seller_category['category_id'])) {
		  $marketplace_allowed_categories = $seller_category['category_id'];
		} elseif (!$this->config->get('marketplace_allowed_seller_category_type') && $this->config->get('marketplace_allowed_categories')) {
		  foreach ($this->config->get('marketplace_allowed_categories') as $key => $categories) {
		    $marketplace_allowed_categories .= ','. $key;
		  }
		  if ($marketplace_allowed_categories) {
		    $marketplace_allowed_categories = ltrim($marketplace_allowed_categories, ',');
		  }
		}

		$sub_query = "";

		if ($marketplace_allowed_categories) {
		  $sub_query = " AND p.product_id IN (SELECT DISTINCT product_id FROM ".DB_PREFIX."product_to_category WHERE category_id IN (" . $marketplace_allowed_categories . "))";
		}

		if (isset($data['filter_name']) && $data['filter_name']) {
		  $sub_query .= " AND pd.name LIKE '%" . $data['filter_name'] . "%'";
		}

		$sql = "SELECT DISTINCT p.product_id,p.*,pd.name FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.product_id NOT IN (SELECT product_id FROM ".DB_PREFIX."customerpartner_to_product) " . $sub_query . " GROUP BY p.product_id ORDER BY pd.name";

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql)->rows;

		return $query;
	}

	public function getAdminProductsTotal($seller_id, $data = array()){

		$marketplace_allowed_categories = '';

		$seller_category = $this->db->query("SELECT category_id FROM ".DB_PREFIX."customerpartner_to_category WHERE seller_id = ".(int)$seller_id)->row;

		if (isset($seller_category['category_id'])) {
		  $marketplace_allowed_categories = $seller_category['category_id'];
		} elseif (!$this->config->get('marketplace_allowed_seller_category_type') && $this->config->get('marketplace_allowed_categories')) {
		  foreach ($this->config->get('marketplace_allowed_categories') as $key => $categories) {
		    $marketplace_allowed_categories .= ','. $key;
		  }
		  if ($marketplace_allowed_categories) {
		    $marketplace_allowed_categories = ltrim($marketplace_allowed_categories, ',');
		  }
		}

		$sub_query = "";

		if ($marketplace_allowed_categories) {
		  $sub_query = " AND p.product_id IN (SELECT DISTINCT product_id FROM ".DB_PREFIX."product_to_category WHERE category_id IN (" . $marketplace_allowed_categories . "))";
		}

		if (isset($data['filter_name']) && $data['filter_name']) {
		  $sub_query .= " AND pd.name LIKE '%" . $data['filter_name'] . "%'";
		}

		$sql = "SELECT DISTINCT p.product_id,p.*,pd.name FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.product_id NOT IN (SELECT product_id FROM ".DB_PREFIX."customerpartner_to_product) " . $sub_query . " GROUP BY p.product_id ORDER BY pd.name";

		$query = $this->db->query($sql)->num_rows;

		return $query;
	}

	public function checkComanyNameExists($companyname){
		$sql = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_customer where companyname = '" . $this->db->escape($companyname) . "'")->row;
		if($sql)
			return $sql;
		return false;
	}

	public function addCategory($categories){

	  foreach ($categories as $key => $value) {
	    $category_id = $key;
	    while(1){
				$parent_category = $this->db->query("SELECT c.parent_id, (SELECT cd.name FROM " . DB_PREFIX . "category_description cd WHERE cd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cd.category_id = c.parent_id) AS name FROM " . DB_PREFIX . "category c WHERE c.category_id = " . (int)$category_id)->row;

	      if (isset($parent_category['parent_id']) && $parent_category['parent_id']) {
	        $categories[$parent_category['parent_id']] = $parent_category['name'];
	        $category_id = $parent_category['parent_id'];
	      } else {
	        break;
	      }
	    }
	  }
		return $categories;
	}
	public function deleteCustomerActivity($customer_id) {
		$customer_activities = $this->db->query("SELECT activity_id FROM " . DB_PREFIX . "seller_activity WHERE seller_id = " . (int)$customer_id . "")->rows;
		if ($customer_activities) {
			foreach ($customer_activities as $activity) {
				$this->db->query("DELETE FROM " . DB_PREFIX . "mp_customer_activity WHERE customer_activity_id = " . (int)$activity['activity_id'] . "");
			}
		}
		$this->db->query("DELETE FROM " . DB_PREFIX . "seller_activity WHERE seller_id = " . (int)$customer_id . "");
	}
}
?>
