<?php

use App\Repositories\Setting\LayoutRepository;

class ControllerCommonContentBottom extends Controller
{
    public function index()
    {
        $layoutRepo = app(LayoutRepository::class);
        $layoutId = $layoutRepo->getLayoutIdByRequest($this->request);
        $modules = $layoutRepo->getModules($layoutId, 'content_bottom');
        if (!$modules) {
            return '';
        }
        $data['modules'] = $layoutRepo->loadModules($modules);

        return $this->load->view('common/content_bottom', $data);
    }
}
