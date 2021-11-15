<?php

use App\Repositories\Setting\LayoutRepository;

class ControllerCommonColumnLeft extends Controller
{
    public function index()
    {
        $layoutRepo = app(LayoutRepository::class);
        $layoutId = $layoutRepo->getLayoutIdByRequest($this->request);
        $modules = $layoutRepo->getModules($layoutId, 'column_left');
        if (!$modules) {
            return '';
        }
        $data['modules'] = $layoutRepo->loadModules($modules);

        $data['isJapanAddMoney'] = '';
        if (!empty($this->customer->getCountryId()) && $this->customer->getCountryId() == 107) {
            $data['isJapanAddMoney'] = '00';
        }
        $data['symbol_left'] = $this->currency->getSymbolLeft($this->session->data['currency']);
        $data['symbol_right'] = $this->currency->getSymbolRight($this->session->data['currency']);

        return $this->load->view('common/column_left', $data);
    }
}
