<?php

use App\Models\Margin\MarginAgreement;
use App\Repositories\Margin\MarginRepository;

/**
 * Class ModelAccountProductQuotesMarginAgreement
 */
class ModelAccountProductQuotesMarginAgreement extends Model
{

    public function getProductInformationByProductId($product_Id)
    {
        $sql = "SELECT p.`product_id`,p.`sku`,ctp.`customer_id` AS seller_id,p.`quantity`,p.`price`
                    ,p.`status`, p.`is_deleted`, p.buyer_flag,
                    c.status AS seller_status
                FROM oc_product p
                INNER JOIN oc_customerpartner_to_product ctp ON p.`product_id` = ctp.`product_id`
                INNER JOIN oc_customer c ON c.customer_id=ctp.customer_id
                WHERE p.`product_id` = " . (int)$product_Id;
        return $this->db->query($sql)->row;
    }

    public function saveMarginAgreement($post_data, $buyer_id, $product)
    {
        $payment_ratio = trim($post_data['input_margin_payment_ratio'], '%');
        $bond_template_info = $this->getBondTemplateInfo($post_data['input_bond_template_id']);
        $rest_price = round(($post_data['input_margin_price'] - $post_data['deposit_per']), 2);
        $data = [
            'agreement_id' => date('Ymd') . rand(100000, 999999),
            'seller_id' => $product['seller_id'],
            'buyer_id' => $buyer_id,
            'product_id' => $product['product_id'],
            'clauses_id' => 1,
            'price' => $post_data['input_margin_price'],
            'payment_ratio' => $payment_ratio,
            'day' => $post_data['input_margin_day'],
            'num' => $post_data['input_margin_qty'],
            'money' => $post_data['input_margin_front_money'],
            'deposit_per' => $post_data['deposit_per'],
            'rest_price' => $rest_price,
            'status' => 1,
            'period_of_application' => $bond_template_info['period_of_application'],
            //'bond_template_number'  => $bond_template_info['bond_template_number'],
            'create_user' => $buyer_id,
            'create_time' => date('Y-m-d H:i:s'),
            'update_user' => $buyer_id,
            'update_time' => date('Y-m-d H:i:s'),
            'program_code' => MarginAgreement::PROGRAM_CODE_V4,
        ];

        $last_id = $this->insert('tb_sys_margin_agreement', $data);
        $last_id = is_numeric($last_id) ? $last_id : 0;
        if ($last_id && !empty($post_data['input_margin_message'])) {
            $message = [
                'margin_agreement_id' => $last_id,
                'customer_id' => $buyer_id,
                'message' => $post_data['input_margin_message'],
                'create_time' => date('Y-m-d H:i:s')
            ];

            $this->insert('tb_sys_margin_message', $message);
        }

        return $last_id;
    }

    public function getBondTemplateInfo($bond_template_id)
    {
        $sql = "SELECT bond_template_number,period_of_application FROM `tb_bond_template` WHERE id = $bond_template_id";
        $info = $this->db->query($sql);

        return $info->row;
    }


}
