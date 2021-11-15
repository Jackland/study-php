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
- {quote_id} - will be your Quote Id for this Quote
- {link} - will be <a> link for your site according to condition 
- {admin_message} - will be Admin Message which will send to customer
[NIk] 
*/

//admin send message to customer
$_['message_to_customer_subject']  = 'Message regarding Quote ID %d'; 
$_['message_to_customer_message']  = 
'{config_logo}
Hi {customer_name},

{config_name} changed Quote #{quote_id} status to following - %s .
{admin_message}

Thanks,
{config_name}';

?>
