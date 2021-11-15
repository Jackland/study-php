<?php

class ControllerCommonPagination extends Controller
{
    public function index($list)
    {
        $data['page'] = intval(get_value_or_default($this->request->get, 'page', 1));
        $data['page_limit'] = intval(get_value_or_default($this->request->request, 'page_limit', 15));
        if ($data['page_limit'] <= 0) {
            $data['page_limit'] = 15;
        }
        $data['total_page'] = ceil($list['total'] / $data['page_limit']);
        $data['total_page'] = $list['total'] ? $data['total_page'] : 1;
        $data['total_num'] = $list['total'];
        $data['page_id'] = $list['page_id'] ?? 'pagination';
        return $this->load->view('common/pagination', $data);
    }
}
