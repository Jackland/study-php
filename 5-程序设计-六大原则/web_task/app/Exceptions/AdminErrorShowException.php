<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class AdminErrorShowException extends Exception
{
    /**
     * 报告异常
     *
     * @return void
     */
    public function report()
    {

    }

    /**
     * @param $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function render($request)
    {
        return back()->withErrors(['error_message' => $this->getMessage()])->withInput(request()->all());
    }
}
