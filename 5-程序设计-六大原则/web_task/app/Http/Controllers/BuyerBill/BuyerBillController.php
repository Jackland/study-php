<?php

namespace App\Http\Controllers\BuyerBill;

//use App\Models\BuyerBill\BuyerBill;
use App\Models\SalesOrder\SalesOrder;
use App\Http\Controllers\Controller;

class BuyerBillController extends Controller
{
    //private $model;
    //private $sphinx_model;

    public function __construct()
    {
        //$this->model = new BuyerBill();
        //$this->sphinx_model = new Sphinx();
        $this->model = new SalesOrder();
    }

    public function test()
    {
        $this->model->updateSalesOrderOnHold();
    }

    //public function test()
    //{
    //    $this->sphinx_model->updateSphinx();
    //}

}
