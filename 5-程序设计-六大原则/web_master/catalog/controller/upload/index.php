<?php

class ControllerUploadIndex extends Controller
{
    public function index()
    {
        $upload_input= $this->load->controller('upload/upload_component/upload_input');

        $this->response->setOutput($this->load->view('upload/index', compact('upload_input')));
    }
}