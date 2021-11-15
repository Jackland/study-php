<?php
use App\Repositories\Information\UploadInformationRepository;

/**
 * Class ModelDesignBanner
 */
class ModelDesignBanner extends Model {
	public function addBanner($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "banner SET name = '" . html_entity_decode($this->db->escape($data['name'])) . "', status = '" . (int)$data['status'] . "'");

		$banner_id = $this->db->getLastId();
        /**
         *迁移oss 图片地址
         */
        $imgIds = [];
        foreach ($data['banner_image'] as $v) {
            foreach ($v as $item) {
                $imgIds[] =intval($item['image']);
            }
        }
        $catalogImg = app(UploadInformationRepository::class)
            ->getListPath(array_filter($imgIds));

		if (isset($data['banner_image'])) {
			foreach ($data['banner_image'] as $language_id => $value) {
				foreach ($value as $banner_image) {
                    if (is_numeric($banner_image['image']) && $catalogImg[intval($banner_image['image'])]) {
                        $banner_image['image'] = ltrim($catalogImg[$banner_image['image']]['file_path'], 'image/');
                    }

					$this->db->query("INSERT INTO " . DB_PREFIX . "banner_image SET banner_id = '" . (int)$banner_id . "', language_id = '" . (int)$language_id . "', title = '" .  $this->db->escape(html_entity_decode($banner_image['title'])) . "', link = '" .  $this->db->escape(html_entity_decode($banner_image['link'])) . "', image = '" .  $this->db->escape(html_entity_decode($banner_image['image'])) . "', sort_order = '" .  (int)$banner_image['sort_order'] . "'");
				}
			}
		}

		return $banner_id;
	}

	public function editBanner($banner_id, $data) {

		$this->db->query("UPDATE " . DB_PREFIX . "banner SET name = '" . $this->db->escape(html_entity_decode($data['name'])) . "', status = '" . (int)$data['status'] . "' WHERE banner_id = '" . (int)$banner_id . "'");

		if (isset($data['banner_image'])) {
            $imgIds = [];
            foreach ($data['banner_image'] as $v) {
                foreach ($v as $item) {
                    $imgIds[] =intval($item['image']);
                }
            }
            $catalogImg = app(UploadInformationRepository::class)
                ->getListPath(array_filter($imgIds));

			foreach ($data['banner_image'] as $language_id => &$value) {
				foreach ($value as $banner_image) {
                    if (is_numeric($banner_image['image']) && $catalogImg[intval($banner_image['image'])]) {
                        $banner_image['image'] = ltrim($catalogImg[$banner_image['image']]['file_path'], 'image/');
                    }
				    if(!empty($banner_image['banner_image_id'])){
				        $image_sql = 'UPDATE ';
                    }else{
				        $image_sql = 'INSERT INTO  ';
                    }

                    $image_sql .= DB_PREFIX . "banner_image SET banner_id = '" . (int)$banner_id . "', language_id = '" . (int)$language_id . "', title = '" .  $this->db->escape(html_entity_decode($banner_image['title'])) . "', link = '" .  $this->db->escape(html_entity_decode($banner_image['link'])) . "', image = '" .  $this->db->escape($banner_image['image']) . "', sort_order = '" . (int)$banner_image['sort_order'] . "',
					country_id=".$banner_image['country_id'].",status=".$banner_image['status'];
                    if(!empty($banner_image['banner_image_id'])){
                        $image_sql .= ' where banner_image_id = '.$banner_image['banner_image_id'];
                    }
					$this->db->query($image_sql);
				}
			}
		}
	}

	public function deleteBanner($banner_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "banner WHERE banner_id = '" . (int)$banner_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "banner_image WHERE banner_id = '" . (int)$banner_id . "'");
	}
	public function deleteBannerImage($banner_image_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "banner_image WHERE banner_image_id = '" . (int)$banner_image_id . "'");
	}

	public function getBanner($banner_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "banner WHERE banner_id = '" . (int)$banner_id . "'");

		return $query->row;
	}

	public function getBanners($data = array()) {
		$sql = "SELECT * FROM " . DB_PREFIX . "banner";

		$sort_data = array(
			'name',
			'status'
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

	public function getBannerImages($banner_id, $param) {
		$banner_image_data = array();
        $sql="SELECT * FROM " . DB_PREFIX . "banner_image WHERE banner_id = '" . (int)$banner_id . "'";
        if(isset($param['filter_title'])){
            $sql .= ' and title like \'%'.$param['filter_title'].'%\'';
        }
        if(isset($param['filter_country'])){
            $sql .= ' and country_id = '.(int)$param['filter_country'];
        }
        if(isset($param['filter_status'])){
            $sql .= ' and status = '.(int)$param['filter_status'];
        }
        $sql.=" ORDER BY sort_order ASC";
		$banner_image_query = $this->db->query($sql);

		foreach ($banner_image_query->rows as $banner_image) {
			$banner_image_data[$banner_image['language_id']][] = array(
				'banner_image_id'      => $banner_image['banner_image_id'],
				'title'      => $banner_image['title'],
				'link'       => $banner_image['link'],
				'image'      => $banner_image['image'],
				'sort_order' => $banner_image['sort_order'],
				'status' => $banner_image['status'],
				'country_id' => $banner_image['country_id']
			);
		}

		return $banner_image_data;
	}

	public function getTotalBanners() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "banner");

		return $query->row['total'];
	}
}
