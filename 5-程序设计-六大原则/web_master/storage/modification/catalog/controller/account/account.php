<?php

class ControllerAccountAccount extends Controller
{
    /**
     * description:为了保证之前配置链接生效 现在buyer和seller跳转到自己的中心页面
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function index()
    {
        //如果未登录直接跳转到首页
        if (!$this->customer->isLogged()) {
            return $this->redirect('account/login');
        }
        //seller
        if ($this->customer->isPartner()) {
            return $this->redirect('customerpartner/seller_center/index');
        }
        //buyer
        return $this->redirect('account/buyer_central');
    }

    /**
     * 下载订单模板文件
     */
    public function manual()
    {
        // 判断用户是否登录
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/account/manual', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }
        // 判断登录用户是否是Seller
        $this->load->model('account/customerpartner');
        $chkIsPartner = $this->customer->isPartner();
        if ($chkIsPartner) {
            $file = DIR_DOWNLOAD . "Manual(Seller).pdf";
        } else {
            $file = DIR_DOWNLOAD . "Manual.pdf";
        }
        if (!headers_sent()) {
            if (file_exists($file)) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($file));

                if (ob_get_level()) {
                    ob_end_clean();
                }

                readfile($file, 'rb');

                exit();
            } else {
                exit('Error: Could not find file ' . $file . '!');
            }
        } else {
            exit('Error: Headers already sent out!');
        }
    }

    public function country()
    {
        $json = array();

        $this->load->model('localisation/country');

        $country_info = $this->model_localisation_country->getCountry($this->request->get['country_id']);

        if ($country_info) {
            $this->load->model('localisation/zone');

            $json = array(
                'country_id' => $country_info['country_id'],
                'name' => $country_info['name'],
                'iso_code_2' => $country_info['iso_code_2'],
                'iso_code_3' => $country_info['iso_code_3'],
                'address_format' => $country_info['address_format'],
                'postcode_required' => $country_info['postcode_required'],
                'zone' => $this->model_localisation_zone->getZonesByCountryId($this->request->get['country_id']),
                'status' => $country_info['status']
            );
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }


    public function getMyInfo()
    {
        if (!$this->customer->isLogged()) {
            $json=[
                'UserName' => $this->customer->getNickName(),
            ];
        }else{
            $json=[
                'UserName' => $this->customer->getNickName(),
            ];
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
