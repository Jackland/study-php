<?php
################################################################################################
# Product Quote for Opencart 2.x From webkul http://webkul.com  	  	       #
################################################################################################
/*
This language file also contain some data which will be used to send more information data to admin / customer and that's why because we can not write that data directly because that will change condition to condition so we used sort codes.
i explaining few here like
- {config_logo} - will be your Store logo
- {config_name} - will be your Store Name
- {customer_name} - will be your Customer Name related to this Quote
- {quote_id} - will be your Quote ID for this Quote
- {quote_price} - will be your Quote Price for this Quote
- {quote_quantity} - will be Quantity for this Quote
- {product_name} - will be Product Name for this Quote
- {product_price} - will be Price for this Quote
- {link} - will be <a> link for your site according to condition
- {customer_message} - will be Customer Message which will send to admin
[NIk]
*/

//Quote Generate
$_['generate_quote_to_admin_subject']  = $_['generate_quote_to_customer_subject']    	  = 'Quote has been Added For Product %s';
$_['generate_quote_to_admin_message']  =
'{config_logo}
Hi {config_owner},

Customer {customer_name} has been added Quote for Product {product_name}.
Message - {customer_message}
Quote Price - {quote_price}
Quote Quantity - {quote_price}
Reply ASAP.

Thanks,
{config_name}';

$_['generate_quote_to_customer_message']  =
'{config_logo}
Hi {customer_name},

Your Quote has been successfully added for Product {product_name}.
We will reply you, as soon as possible after reviewing your request.
You can check your Quote details here {link}

Thanks,
{config_name}';

//customer send message to admin
$_['message_to_admin_subject']    	  = 'Customer Send Message regarding Quote ID %d';
$_['message_to_admin_message']  =
'{config_logo}
Hi {config_owner},

Customer {customer_name} sent message regarding Quote ID - #{quote_id}.
{customer_message}

Thanks,
{config_name}';
//customer send message to seller
$_['message_to_seller_subject']    	  = 'Customer Send Message regarding Quote ID %d';
$_['message_to_seller_message']  =
'{config_logo}
Hi {seller_name},

Customer {customer_name} sent message regarding Quote ID - #{quote_id}.
{customer_message}

Thanks,
{config_name}';



?>
