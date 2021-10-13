<?php
/**
 * Created by WelcomeController.php.
 * User: fuyunnan
 * Date: 2021/10/11
 * Time: 15:58
 */

namespace App\Http\Controllers;

use Illuminate\Container\Container;

use App\Models\Admin;

class WelcomeController
{
    public function index()
    {
        $admin = Admin::query()->first();
        $data = $admin->getAttributes();
        $app = Container::getInstance();
        $factory = $app->make('view');

        return $factory->make('welcome')->with('data', $data);

    }

}