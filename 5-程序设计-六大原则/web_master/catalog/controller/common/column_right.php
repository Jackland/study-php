<?php

use App\Repositories\Setting\LayoutRepository;

class ControllerCommonColumnRight extends Controller
{
    public function index()
    {
        $layoutRepo = app(LayoutRepository::class);
        $layoutId = $layoutRepo->getLayoutIdByRequest($this->request);
        $modules = $layoutRepo->getModules($layoutId, 'column_right');
        if (!$modules) {
            return '';
        }
        $data['modules'] = $layoutRepo->loadModules($modules);

        return $this->load->view('common/column_right', $data);
    }
}
