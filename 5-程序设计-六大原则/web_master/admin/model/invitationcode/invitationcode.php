<?php

/**
 * Class ModelInvitationcodeInvitationcode
 */
class ModelInvitationcodeInvitationcode extends Model{
    public function getCustomerId($customerName){
        $sql = "SELECT customer_id FROM oc_customer WHERE LCASE(CONCAT(firstname, ' ', lastname)) LIKE '%$customerName%' and customer_group_id = 14";
        $query = $this->db->query($sql);
        if($query->num_rows == 1){
            return $query->row["customer_id"];
        }
    }

    public function saveInvitationcode($invitationCode,$managementId){
        $sql = "INSERT INTO oc_invitation (customer_id,invitation_code) values(" .$managementId. ",'" .$invitationCode. "')";
        $this->db->query($sql);
    }
}