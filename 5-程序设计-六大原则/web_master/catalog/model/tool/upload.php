<?php

/**
 * Class ModelToolUpload
 */
class ModelToolUpload extends Model {
	public function addUpload($name, $filename) {
		$code = sha1(uniqid(mt_rand(), true));

		$this->db->query("INSERT INTO `" . DB_PREFIX . "upload` SET `name` = '" . $this->db->escape($name) . "', `filename` = '" . $this->db->escape($filename) . "', `code` = '" . $this->db->escape($code) . "', `date_added` = NOW()");

		return $code;
	}

	public function getUploadByCode($code) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "upload` WHERE code = '" . $this->db->escape($code) . "'");

		return $query->row;
	}

    public function saveUploadFileRecord($data)
    {
        $sql = "INSERT INTO " . "tb_sys_customer_upload_record (file_name, size, file_path, customer_id, run_id, memo, create_user_name, create_time, program_code) VALUES(";
        if (isset($data['file_name'])) {
            $sql .= "'" . $data['file_name'] . "',";
        } else {
            $sql .= " '',";
        }
        if (isset($data['size'])) {
            $sql .= $data['size'] . ",";
        } else {
            $sql .= 0 . ",";
        }
        if (isset($data['file_path'])) {
            $sql .= "'" . $data['file_path'] . "',";
        } else {
            $sql .= " '', ";
        }
        if (isset($data['customer_id'])) {
            $sql .= $data['customer_id'] . ",";
        } else {
            $sql .= "null,";
        }
        if (isset($data['run_id'])) {
            $sql .= $data['run_id'] . ",";
        } else {
            $sql .= "'" . time() . "',";
        }
        if (isset($data['memo'])) {
            $sql .= "'" . $data['memo'] . "',";
        } else {
            $sql .= "null,";
        }
        if (isset($data['create_user_name'])) {
            $sql .= "'" . $data['create_user_name'] . "',";
        } else {
            $sql .= 'null,';
        }
        if (isset($data['create_time'])) {
            $sql .= "'" . $data['create_time'] . "',";
        } else {
            $sql .= "NOW(),";
        }
        $sql .= "'" . PROGRAM_CODE . "')";
        $this->db->query($sql);
    }
}