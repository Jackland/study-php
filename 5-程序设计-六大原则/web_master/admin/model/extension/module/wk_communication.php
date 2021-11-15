<?php

/**
 * Class ModelExtensionModulewkcommunication
 */
class ModelExtensionModulewkcommunication  extends Model {
	
	public function createTableCommunication() {
			$query = $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wk_communication_message` (
								  `message_id` int(11) NOT NULL AUTO_INCREMENT,
								  `message_subject` varchar(1000) CHARACTER SET utf8,
								  `message_body` varchar(16000) CHARACTER SET utf8,
								  `message_date` datetime NOT NULL,
								  `message_to` varchar(200) CHARACTER SET utf8 NOT NULL,
								  `message_from` varchar(200) CHARACTER SET utf8 NOT NULL,
								  `secure` int(11) NOT NULL,
								  `user_id` varchar(255),
								  PRIMARY KEY (`message_id`)
								) ENGINE=MyISAM  DEFAULT CHARSET=utf8"); 
			$query = $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wk_communication_attachment` (
								  `attachment_id` int(11) NOT NULL AUTO_INCREMENT,
								  `message_id` int(11) NOT NULL,
								  `filename` varchar(500) CHARACTER SET utf8,
								  `maskname` varchar(500) CHARACTER SET utf8 NOT NULL,
								  `date_added` datetime  NOT NULL,
								  PRIMARY KEY (`attachment_id`)
								) ENGINE=MyISAM  DEFAULT CHARSET=utf8"); 

			$query = $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wk_communication_placeholder` (
								  `id` int(11) NOT NULL AUTO_INCREMENT,
								  `user_id` int(11) NOT NULL,
								  `user_name` varchar(500) CHARACTER SET utf8,
								  `placeholder_id` int(11) NOT NULL,
								  `placeholder_name` varchar(500) CHARACTER SET utf8 NOT NULL,
								  `message_id` int(11) NOT NULL,
								  `status` int(11) NOT NULL,
								  PRIMARY KEY (`id`)
								) ENGINE=MyISAM  DEFAULT CHARSET=utf8"); 
			$query = $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wk_communication_thread` (
								  `thread_id` int(11) NOT NULL AUTO_INCREMENT,
								  `message_id` int(11) NOT NULL,
								  `parent_message_id` int(11) NOT NULL,
								  `thread_time` datetime NOT NULL,
								  PRIMARY KEY (`thread_id`)
								) ENGINE=MyISAM  DEFAULT CHARSET=utf8"); 

	}
public function deleteTableCommunication() {
	$this->db->query("DROP TABLE ".DB_PREFIX."wk_communication_message");
	$this->db->query("DROP TABLE ".DB_PREFIX."wk_communication_attachment");
	$this->db->query("DROP TABLE ".DB_PREFIX."wk_communication_placeholder");
	$this->db->query("DROP TABLE ".DB_PREFIX."wk_communication_thread");
}
}
?>