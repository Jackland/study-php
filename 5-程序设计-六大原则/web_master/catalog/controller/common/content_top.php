<?php

use App\Repositories\Setting\LayoutRepository;

class ControllerCommonContentTop extends Controller {
	public function index() {
        $layoutRepo = app(LayoutRepository::class);
        $layoutId = $layoutRepo->getLayoutIdByRequest($this->request);
        $modules = $layoutRepo->getModules($layoutId, 'content_top');
        if (!$modules) {
            return '';
        }
        // carousel 16460本次移除
        $data['modules'] = $layoutRepo->loadModules($modules);

		return $this->load->view('common/content_top', $data);
	}
}
