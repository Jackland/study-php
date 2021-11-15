<?php
################################################################################################
# Return for Opencart 1.5.1.x From webkul http://webkul.com  	  	       #
################################################################################################
/*
This language file also contain some data which will be used to send more information data to admin / customer and that's why because we can not write that data directly because that will change condition to condition so we used sort codes.
i explaining few here like
- {config_logo} - will be your Store logo
- {config_name} - will be your Store Name
- {customer_name} - will be your Customer Name related to this Return
- {order_id} - will be your Order Id for this Return
- {link} - will be <a> link for your site according to condition
- {rma_id} - will be your Return Id for this Return
- {admin_message} - will be Admin Message which will send to customer
[NIk]
*/

//admin send message to customer
$_['message_to_customer_subject']    	  = 'Message regarding Return';
$_['message_to_customer_message']  =
'{config_logo}
Hi {customer_name},

{config_name} changed Return #{rma_id} status to following - %s .
{admin_message}

Thanks,
{config_name}';

//customer changed status
$_['label_to_customer_subject']    	  =  'Shipping label Regarding your Return';
$_['label_to_customer_message']  =
'{config_logo}
Hi {customer_name},

{config_name} sent you shipping label regarding Return #{rma_id}, you can use that to return product(s).
<a  href="{link}">here</a>

Thanks,
{config_name}';

//Return Generate
$_['generate_admin_subject']    	  = $_['generate_customer_subject']    	  = 'Return has been Generated For Order Id';
$_['generate_admin_message']  =
'{config_logo}
Hi {config_owner},

Customer {customer_name} has been generated Return for Order #{order_id}.
Reply ASAP.

Thanks,
{config_name}';

$_['generate_customer_message']  =
'{config_logo}
Hi {customer_name},

Your Return has been generated for Order #{order_id}.
We will reply you, as soon as possible after reviewing your request.
You can check your Return details <a  href="{link}">here</a>.

Thanks,
{config_name}';

//customer send message to admin
$_['message_to_admin_subject']    	  = 'Customer Send Message regarding Return';
$_['message_to_admin_message']  =
'{config_logo}
Hi {config_owner},

Customer {customer_name} sent message regarding Return Id - #{rma_id}.
{customer_message}

Thanks,
{config_name}';

//customer changed status
$_['status_to_admin_subject']    	  = $_['status_to_customer_subject']    	  = 'Return Status has been Changed';
$_['status_to_admin_message']  =
'{config_logo}
Hi {config_owner},

Customer {customer_name} changed Return #{rma_id} status to following - %s.

Thanks,
{config_name}';

$_['status_to_customer_message']  =
'{config_logo}
Hi {customer_name},

Your Return #{rma_id} status has been successfully changed to following - %s.

Thanks,
{config_name}';

$_['generate_admin_subject']    	  = $_['generate_customer_subject']    	  = 'Return has been Generated For Order Id';
$_['generate_admin_message']  =
'{config_logo}
Hi {config_owner},

Customer {customer_name} has been generated Return for Order #{order_id}.
Reply ASAP.

Thanks,
{config_name}';

$_['generate_customer_message']  =
'{config_logo}
Hi {customer_name},

Your Return has been generated for Order #{order_id}.
We will reply you, as soon as possible after reviewing your request.
You can check your Return details here <a  href="{link}">here</a>.

Thanks,
{config_name}';

//customer send message to admin
$_['message_to_admin_subject']    	  = 'Customer Send Message regarding Return';
$_['message_to_admin_message']  =
'{config_logo}
Hi {config_owner},

Customer {customer_name} sent message regarding Return Id - #{rma_id}.
{customer_message}

Thanks,
{config_name}';

//customer changed status
$_['status_to_admin_subject']    	  = $_['status_to_customer_subject']    	  = 'Return Status has been Changed';
$_['status_to_admin_message']  =
'{config_logo}
Hi {config_owner},

Customer {customer_name} changed Return #{rma_id} status to following - %s.

Thanks,
{config_name}';

$_['status_to_customer_message']  =
'{config_logo}
Hi {customer_name},

Your Return #{rma_id} status has been successfully changed to following - %s.

Thanks,
{config_name}';
$_['error_status_in_use'] = 'RMA status is being used. Therfore it can\'t be deleted.';
$_['error_reason_in_use'] = 'RMA reason is being used. Therfore it can\'t be deleted.';
