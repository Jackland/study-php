<?php

namespace Framework\Debug;

use DebugBar\OpenHandler;
use Framework\Controller\Controller;

class DebugBarController extends Controller
{
    // openHandler
    // @link http://phpdebugbar.com/docs/openhandler.html#open-handler
    public function open()
    {
        debugBar()->enable();

        $openHandler = new OpenHandler(debugBar());
        return $this->json($openHandler->handle(request()->get(), false));
    }
}
